<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Inachis\Fauna\Entity\Country;
use Inachis\Fauna\Entity\Species;
use Inachis\Fauna\Entity\Taxonomy;
use Inachis\Fauna\Enum\IucnStatus;
use Inachis\Fauna\Enum\TaxonomyType;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fauna:import:taxonomy',
    description: 'Imports taxonomy/species/vernacular/distribution TSV files'
)]
class ImportTaxonomyCommand extends Command
{
    private const BATCH_SIZE = 20;

    private array $taxonomyIdCache = [];
    private array $pendingTaxonomyCache = [];
    private array $countryIdCache = [];

    private array $recentMalformedRows = [];

    private int $flushCount = 0;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'directory',
                InputArgument::REQUIRED
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE
            )
            ->addOption(
                'clear',
                null,
                InputOption::VALUE_NONE
            )
            
            ->addOption(
                'import-taxonomy',
                'taxa',
                InputOption::VALUE_NONE,
                'Import Taxon.tsv'
            )
            ->addOption(
                'import-vernacular',
                'vern',
                InputOption::VALUE_NONE,
                'Import VernacularName.tsv'
            )
            ->addOption(
                'import-distribution',
                'dist',
                InputOption::VALUE_NONE,
                'Import Distribution.tsv'
            );;
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        ini_set('memory_limit', '1024M');

        gc_enable();

        /*
         * CRITICAL:
         * Prevent Doctrine DBAL middleware
         * memory accumulation.
         */
        $this->connection
            ->getConfiguration()
            ->setMiddlewares([]);

        $io = new SymfonyStyle($input, $output);

        $directory = rtrim(
            (string) $input->getArgument('directory'),
            DIRECTORY_SEPARATOR
        );

        $dryRun = (bool) $input->getOption('dry-run');
        $clear = (bool) $input->getOption('clear');

        $importTaxa = (bool) $input->getOption('taxa');
        $importVernacular = (bool) $input->getOption('vernacular');
        $importDistribution = (bool) $input->getOption('distribution');

         /*
         * If no specific import options are provided, import all.
         */
        if (
            !$importTaxa
            && !$importVernacular
            && !$importDistribution
        ) {
            $importTaxa = true;
            $importVernacular = true;
            $importDistribution = true;
        }

        if ($clear) {
            $this->clearTables($io, $dryRun);
        }

        $stats = [
            'processed' => 0,
            'skipped' => 0,
            'duplicates_skipped' => 0,
            'malformed_rows' => 0,
            'species_created' => 0,
            'taxonomy_created' => 0,
            'vernacular_updated' => 0,
            'distribution_updated' => 0,
        ];

        if ($importTaxa) {
            $io->section('Importing Taxon.tsv');

            $this->importTaxa(
                $directory . '/Taxon.tsv',
                $stats,
                $dryRun,
                $output
            );
        }

        if ($importVernacular) {
            $io->section('Importing VernacularName.tsv');

            $this->importVernacular(
                $directory . '/VernacularName.tsv',
                $stats,
                $dryRun,
                $output
            );
        }

        if ($importDistribution) {
            $io->section('Importing Distribution.tsv');

            $this->importDistribution(
                $directory . '/Distribution.tsv',
                $stats,
                $dryRun,
                $output
            );
        }
        $io->success('Import complete');

        $io->table(
            ['Metric', 'Count'],
            [
                ['Processed', number_format($stats['processed'])],
                ['Skipped', number_format($stats['skipped'])],
                ['Species Created', number_format($stats['species_created'])],
                ['Taxonomy Created', number_format($stats['taxonomy_created'])],
                ['Vernacular Updated', number_format($stats['vernacular_updated'])],
                ['Distribution Updated', number_format($stats['distribution_updated'])],
                ['Malformed Rows', number_format($stats['malformed_rows'])],
                ['Duplicates', number_format($stats['duplicates_skipped'])],
            ]
        );

        if ($this->recentMalformedRows !== []) {
            $output->writeln('');
            $output->writeln('<comment>Recent malformed rows:</comment>');

            foreach ($this->recentMalformedRows as $row) {
                $output->writeln(sprintf(
                    'Line %d | expected=%d actual=%d | %s',
                    $row['line'],
                    $row['expected_columns'],
                    $row['actual_columns'],
                    $row['preview']
                ));
            }
        }

        return Command::SUCCESS;
    }

    private function importTaxa(
        string $file,
        array &$stats,
        bool $dryRun,
        OutputInterface $output
    ): void {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Cannot open Taxon.tsv');
        }

        $headers = fgetcsv($handle, 0, "\t", '"', '\\');

        if ($headers === false) {
            throw new \RuntimeException('Invalid TSV');
        }

        $headers = array_map('trim', $headers);

        $progress = new ProgressBar($output);

        $progress->start();

        $batch = 0;
        $lineNumber = 1; // Start at 1 to account for header

        while (($row = fgetcsv($handle, 0, "\t", '"', '\\')) !== false) {
            ++$stats['processed'];
            ++$lineNumber;

            $progress->advance();

            $headerCount = count($headers);
            $rowCount = count($row);

            /*
            * Skip malformed rows safely.
            */
            if ($rowCount !== $headerCount) {
                ++$stats['malformed_rows'];
                $this->recordMalformedRow(
                    $lineNumber,
                    $headerCount,
                    $rowCount,
                    $row
                );
                continue;
            }

            $data = array_combine($headers, $row);
            if ($data === false) {
                ++$stats['skipped'];
                continue;
            }

            if (!$this->shouldImportTaxon($data)) {
                ++$stats['skipped'];
                continue;
            }
            if ($this->speciesExists($data['canonicalName'])) {
                $stats['duplicates_skipped']++;
                continue;
            }

            $parent = null;

            $levels = [
                'kingdom' => TaxonomyType::KINGDOM,
                'phylum' => TaxonomyType::PHYLUM,
                'class' => TaxonomyType::CLASS_,
                'order' => TaxonomyType::ORDER,
                'family' => TaxonomyType::FAMILY,
                'genus' => TaxonomyType::GENUS,
            ];

            foreach ($levels as $column => $type) {
                $value = trim((string) ($data[$column] ?? ''));

                if ($value === '') {
                    continue;
                }

                $parent = $this->getOrCreateTaxonomy(
                    $value,
                    $type,
                    $parent,
                    $dryRun,
                    $stats
                );
            }

            $species = new Species(
                name: '',
                latin: trim((string) $data['canonicalName']),
                iucn: IucnStatus::tryFromValue(
                    $data['threatStatus'] ?? null
                ),
                genus: $parent
            );

            $species->setExternalId(
                isset($data['taxonID'])
                    ? (int) $data['taxonID']
                    : null
            );

            if (!$dryRun) {
                $this->entityManager->persist($species);
            }

            ++$stats['species_created'];
            ++$batch;

            if ($batch >= self::BATCH_SIZE) {
                if (!$dryRun) {
                    $this->flushAndClear();
                }

                $batch = 0;
            }
        }

        if (!$dryRun) {
            $this->flushAndClear();
        }

        fclose($handle);

        $progress->finish();

        $output->writeln('');
    }

    private function importVernacular(
        string $file,
        array &$stats,
        bool $dryRun,
        OutputInterface $output
    ): void {
        if (!is_file($file)) {
            return;
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return;
        }

        $headers = fgetcsv($handle, 0, "\t", '"', '\\');

        if ($headers === false) {
            fclose($handle);
            return;
        }

        $headers = array_map('trim', $headers);

        $progress = new ProgressBar($output);

        $progress->start();

        $batch = 0;

        while (($row = fgetcsv($handle, 0, "\t", '"', '\\')) !== false) {
            ++$stats['processed'];

            $progress->advance();

            if (count($row) !== count($headers)) {
                ++$stats['malformed_rows'];
                // $this->recordMalformedRow($row);
                continue;
            }

            $data = array_combine($headers, $row);

            if ($data === false) {
                ++$stats['malformed_rows'];
                continue;
            }

            $taxonId = isset($data['taxonID'])
                ? (int) $data['taxonID']
                : null;

            $vernacular = trim(
                (string) ($data['vernacularName'] ?? '')
            );

            if ($taxonId === null || $vernacular === '') {
                ++$stats['skipped'];
                continue;
            }

            $speciesId = $this->getSpeciesIdByExternalId($taxonId);

            if ($speciesId === null) {
                ++$stats['skipped'];
                continue;
            }

            $species = $this->entityManager->getReference(
                Species::class,
                Uuid::fromString($speciesId)
            );

            if (method_exists($species, 'setName')) {
                $species->setName($vernacular);
            }

            ++$stats['vernacular_updated'];
            ++$batch;

            if ($batch >= self::BATCH_SIZE) {
                if (!$dryRun) {
                    $this->flushAndClear();
                }

                $batch = 0;
            }
        }

        if (!$dryRun) {
            $this->flushAndClear();
        }

        fclose($handle);

        $progress->finish();

        $output->writeln('');
    }

    private function importDistribution(
        string $file,
        array &$stats,
        bool $dryRun,
        OutputInterface $output
    ): void {
        if (!is_file($file)) {
            return;
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return;
        }

        $headers = fgetcsv($handle, 0, "\t", '"', '\\');

        if ($headers === false) {
            fclose($handle);
            return;
        }

        $headers = array_map('trim', $headers);

        $progress = new ProgressBar($output);

        $progress->start();

        $batch = 0;

        while (($row = fgetcsv($handle, 0, "\t", '"', '\\')) !== false) {
            ++$stats['processed'];

            $progress->advance();

            if (count($row) !== count($headers)) {
                ++$stats['malformed_rows'];
                // $this->recordMalformedRow($row);
                continue;
            }

            $data = array_combine($headers, $row);

            if ($data === false) {
                ++$stats['malformed_rows'];
                continue;
            }

            $taxonId = isset($data['taxonID'])
                ? (int) $data['taxonID']
                : null;

            $countryCode = trim(
                (string) ($data['countryCode'] ?? '')
            );

            if ($taxonId === null || $countryCode === '') {
                ++$stats['skipped'];
                continue;
            }

            $speciesId = $this->getSpeciesIdByExternalId($taxonId);

            if ($speciesId === null) {
                ++$stats['skipped'];
                continue;
            }

            $countryId = $this->getCountryIdByCode($countryCode);

            if ($countryId === null) {
                ++$stats['skipped'];
                continue;
            }

            if (!$dryRun) {
                try {
                    $this->connection->insert(
                        'fauna_species_to_country',
                        [
                            'species_id' => $speciesId,
                            'country_id' => $countryId,
                        ]
                    );
                } catch (UniqueConstraintViolationException) {
                    ++$stats['duplicates_skipped'];
                }
            }

            ++$stats['distribution_updated'];
            ++$batch;

            if ($batch >= self::BATCH_SIZE) {
                if (!$dryRun) {
                    $this->flushAndClear();
                }

                $batch = 0;
            }
        }

        if (!$dryRun) {
            $this->flushAndClear();
        }

        fclose($handle);

        $progress->finish();

        $output->writeln('');
    }

    private function getOrCreateTaxonomy(
        string $name,
        TaxonomyType $type,
        ?Taxonomy $parent,
        bool $dryRun,
        array &$stats
    ): Taxonomy {
        $parentId = $parent?->getId()?->toString();

        $cacheKey = sprintf(
            '%s|%s|%s',
            $type->value,
            strtolower($name),
            $parentId ?? 'root'
        );

        if (isset($this->pendingTaxonomyCache[$cacheKey])) {
            return $this->pendingTaxonomyCache[$cacheKey];
        }

        if (isset($this->taxonomyIdCache[$cacheKey])) {
            return $this->entityManager->getReference(
                Taxonomy::class,
                Uuid::fromString(
                    $this->taxonomyIdCache[$cacheKey]
                )
            );
        }

        $existingId = $this->connection->fetchOne(
            '
            SELECT id
            FROM fauna_taxonomy
            WHERE name = :name
            AND type = :type
            AND (
                (:parent IS NULL AND parent_id IS NULL)
                OR parent_id = :parent
            )
            LIMIT 1
            ',
            [
                'name' => $name,
                'type' => $type->value,
                'parent' => $parentId,
            ]
        );

        if ($existingId !== false) {
            $this->taxonomyIdCache[$cacheKey] = $existingId;

            return $this->entityManager->getReference(
                Taxonomy::class,
                Uuid::fromString($existingId)
            );
        }

        $taxonomy = new Taxonomy(
            name: $name,
            type: $type,
            parent: $parent
        );

        $taxonomy->setCanonicalName($name);
        $taxonomy->setAccepted(true);

        if (!$dryRun) {
            $this->entityManager->persist($taxonomy);
        }

        $this->pendingTaxonomyCache[$cacheKey] = $taxonomy;

        ++$stats['taxonomy_created'];

        return $taxonomy;
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();

        foreach ($this->pendingTaxonomyCache as $key => $taxonomy) {
            $id = $taxonomy->getId()?->toString();

            if ($id !== null) {
                $this->taxonomyIdCache[$key] = $id;
            }
        }

        $this->pendingTaxonomyCache = [];

        $this->entityManager->clear();

        ++$this->flushCount;

        /*
         * Prevent cache explosion.
         */
        if ($this->flushCount % 100 === 0) {
            $this->taxonomyIdCache = [];
            $this->countryIdCache = [];
        }

        gc_collect_cycles();
    }

    private function shouldImportTaxon(array $data): bool
    {
        if (($data['taxonomicStatus'] ?? '') !== 'accepted') {
            return false;
        }

        if (($data['kingdom'] ?? '') !== 'Animalia') {
            return false;
        }

        $canonicalName = trim(
            (string) ($data['canonicalName'] ?? '')
        );

        if ($canonicalName === '') {
            return false;
        }

        $rank = strtolower(
            trim((string) ($data['taxonRank'] ?? ''))
        );

        return in_array(
            $rank,
            ['species', 'subspecies'],
            true
        );
    }

    private function clearTables(
        SymfonyStyle $io,
        bool $dryRun
    ): void {
        $io->warning('Clearing existing fauna tables');

        if ($dryRun) {
            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM fauna_species_to_country'
        );

        $this->connection->executeStatement(
            'DELETE FROM fauna_species'
        );

        $this->connection->executeStatement(
            'DELETE FROM fauna_taxonomy'
        );
    }

    private function speciesExists(string $latinName): bool
    {
        return (bool) $this->connection->fetchOne(
            '
            SELECT 1
            FROM fauna_species
            WHERE latin = :name
            LIMIT 1
            ',
            [
                'name' => $latinName,
            ]
        );
    }

    private function recordMalformedRow(
        int $line,
        int $expected,
        int $actual,
        array $row
    ): void {
        $this->recentMalformedRows[] = [
            'line' => $line,
            'expected_columns' => $expected,
            'actual_columns' => $actual,
            'preview' => mb_substr(
                json_encode($row, JSON_UNESCAPED_UNICODE) ?: '',
                0,
                500
            ),
        ];

        /*
        * Keep memory bounded.
        */
        if (count($this->recentMalformedRows) > 100) {
            array_shift($this->recentMalformedRows);
        }
    }
}
