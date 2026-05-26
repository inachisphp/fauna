<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Inachis\Fauna\Entity\Country;
use Inachis\Fauna\Entity\Species;
use Inachis\Fauna\Entity\Taxonomy;
use Inachis\Fauna\Enum\IucnStatus;
use Inachis\Fauna\Enum\TaxonomyType;
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
    private const BATCH_SIZE = 500;

    /**
     * Cached taxonomy entities
     *
     * @var array<string, Taxonomy>
     */
    private array $taxonomyCache = [];

    /**
     * Cached countries
     *
     * @var array<string, Country>
     */
    private array $countryCache = [];

    /**
     * Cached species IDs by external ID
     *
     * @var array<int, Species>
     */
    private array $speciesCache = [];

    /**
     * Accepted taxonomy hierarchy
     */
    private const TAXONOMIC_LEVELS = [
        'kingdom' => TaxonomyType::KINGDOM,
        'phylum' => TaxonomyType::PHYLUM,
        'class' => TaxonomyType::CLASS_,
        'order' => TaxonomyType::ORDER,
        'family' => TaxonomyType::FAMILY,
        'genus' => TaxonomyType::GENUS,
    ];

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
                InputArgument::REQUIRED,
                'Directory containing Taxon.tsv, VernacularName.tsv and Distribution.tsv'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Perform import without writing to database'
            )
            ->addOption(
                'clear',
                null,
                InputOption::VALUE_NONE,
                'Clear fauna taxonomy/species tables before import'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $directory = rtrim((string) $input->getArgument('directory'), DIRECTORY_SEPARATOR);
        $dryRun = (bool) $input->getOption('dry-run');
        $clear = (bool) $input->getOption('clear');

        $taxonFile = $directory . DIRECTORY_SEPARATOR . 'Taxon.tsv';
        $vernacularFile = $directory . DIRECTORY_SEPARATOR . 'VernacularName.tsv';
        $distributionFile = $directory . DIRECTORY_SEPARATOR . 'Distribution.tsv';

        foreach ([$taxonFile, $vernacularFile, $distributionFile] as $file) {
            if (!is_file($file)) {
                $io->error(sprintf('Missing required file: %s', $file));
                return Command::FAILURE;
            }
        }

        $io->title('Fauna Taxonomy Import');

        if ($dryRun) {
            $io->warning('Running in DRY RUN mode - no data will be persisted');
        }

        if ($clear) {
            $this->clearTables($io, $dryRun);
        }

        $stats = [
            'species_created' => 0,
            'taxonomy_created' => 0,
            'vernacular_added' => 0,
            'countries_added' => 0,
            'rows_processed' => 0,
            'rows_skipped' => 0,
        ];

        $this->warmCaches();

        $io->section('Importing Taxon.tsv');
        $this->importTaxa(
            $taxonFile,
            $stats,
            $dryRun,
            $output
        );

        $io->section('Importing VernacularName.tsv');
        $this->importVernacularNames(
            $vernacularFile,
            $stats,
            $dryRun,
            $output
        );

        $io->section('Importing Distribution.tsv');
        $this->importDistribution(
            $distributionFile,
            $stats,
            $dryRun,
            $output
        );

        if (!$dryRun) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $io->success('Import complete');

        $io->table(
            ['Metric', 'Value'],
            [
                ['Rows processed', number_format($stats['rows_processed'])],
                ['Rows skipped', number_format($stats['rows_skipped'])],
                ['Species created', number_format($stats['species_created'])],
                ['Taxonomy created', number_format($stats['taxonomy_created'])],
                ['Vernacular names added', number_format($stats['vernacular_added'])],
                ['Country associations added', number_format($stats['countries_added'])],
            ]
        );

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
            throw new \RuntimeException(sprintf('Unable to open %s', $file));
        }

        $headers = fgetcsv($handle, 0, "\t");

        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('Invalid TSV header');
        }

        $headers = array_map('trim', $headers);

        $progressBar = new ProgressBar($output);
        $progressBar->start();

        $batchCount = 0;

        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            ++$stats['rows_processed'];
            $progressBar->advance();

            $data = array_combine($headers, $row);

            if ($data === false) {
                ++$stats['rows_skipped'];
                continue;
            }

            if (!$this->shouldImportTaxon($data)) {
                ++$stats['rows_skipped'];
                continue;
            }

            $parent = null;

            foreach (self::TAXONOMIC_LEVELS as $column => $type) {
                $value = trim((string) ($data[$column] ?? ''));

                if ($value === '') {
                    continue;
                }

                $parent = $this->getOrCreateTaxonomy(
                    name: $value,
                    type: $type,
                    parent: $parent,
                    dryRun: $dryRun,
                    stats: $stats
                );
            }

            $species = new Species(
                name: '',
                latin: trim((string) $data['canonicalName']),
                iucn: IucnStatus::tryFromValue($data['threatStatus'] ?? null),
                genus: $parent
            );

            $species->setExternalId(
                isset($data['taxonID']) ? (int) $data['taxonID'] : null
            );

            if (!$dryRun) {
                $this->entityManager->persist($species);
            }

            $externalId = (int) ($data['taxonID'] ?? 0);

            if ($externalId > 0) {
                $this->speciesCache[$externalId] = $species;
            }

            ++$stats['species_created'];
            ++$batchCount;

            if (!$dryRun && $batchCount >= self::BATCH_SIZE) {
                $this->flushAndClear();
                $batchCount = 0;
            }
        }

        fclose($handle);

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $progressBar->finish();
        $output->writeln('');
    }

    private function importVernacularNames(
        string $file,
        array &$stats,
        bool $dryRun,
        OutputInterface $output
    ): void {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open %s', $file));
        }

        $headers = fgetcsv($handle, 0, "\t");

        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('Invalid TSV header');
        }

        $headers = array_map('trim', $headers);

        $progressBar = new ProgressBar($output);
        $progressBar->start();

        $batchCount = 0;

        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            $progressBar->advance();

            $data = array_combine($headers, $row);

            if ($data === false) {
                continue;
            }

            $taxonId = (int) ($data['taxonID'] ?? 0);

            if ($taxonId === 0 || !isset($this->speciesCache[$taxonId])) {
                continue;
            }

            $vernacular = trim((string) ($data['vernacularName'] ?? ''));

            if ($vernacular === '') {
                continue;
            }

            $species = $this->speciesCache[$taxonId];

            if ($species->getName() === '') {
                $species->setName($vernacular);
                ++$stats['vernacular_added'];
            }

            ++$batchCount;

            if (!$dryRun && $batchCount >= self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();

                $batchCount = 0;
            }
        }

        fclose($handle);

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $progressBar->finish();
        $output->writeln('');
    }

    private function importDistribution(
        string $file,
        array &$stats,
        bool $dryRun,
        OutputInterface $output
    ): void {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open %s', $file));
        }

        $headers = fgetcsv($handle, 0, "\t");

        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('Invalid TSV header');
        }

        $headers = array_map('trim', $headers);

        $progressBar = new ProgressBar($output);
        $progressBar->start();

        $batchCount = 0;

        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            $progressBar->advance();

            $data = array_combine($headers, $row);

            if ($data === false) {
                continue;
            }

            $taxonId = (int) ($data['taxonID'] ?? 0);
            $countryCode = strtoupper(trim((string) ($data['countryCode'] ?? '')));

            if (
                $taxonId === 0 ||
                $countryCode === '' ||
                !isset($this->speciesCache[$taxonId])
            ) {
                continue;
            }

            $species = $this->speciesCache[$taxonId];
            $country = $this->getCountryByCode($countryCode);

            if ($country === null) {
                continue;
            }

            $species->addCountry($country);

            ++$stats['countries_added'];
            ++$batchCount;

            if (!$dryRun && $batchCount >= self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();

                $batchCount = 0;
            }
        }

        fclose($handle);

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $progressBar->finish();
        $output->writeln('');
    }

    private function shouldImportTaxon(array $data): bool
    {
        if (($data['taxonomicStatus'] ?? '') !== 'accepted') {
            return false;
        }

        if (($data['kingdom'] ?? '') !== 'Animalia') {
            return false;
        }

        $canonicalName = trim((string) ($data['canonicalName'] ?? ''));

        if ($canonicalName === '') {
            return false;
        }

        $taxonRank = strtolower(trim((string) ($data['taxonRank'] ?? '')));

        return in_array(
            $taxonRank,
            ['species', 'subspecies'],
            true
        );
    }

    private function getOrCreateTaxonomy(
        string $name,
        TaxonomyType $type,
        ?Taxonomy $parent,
        bool $dryRun,
        array &$stats
    ): Taxonomy {
        $cacheKey = sprintf(
            '%s|%s|%s',
            $type->value,
            strtolower($name),
            $parent?->getName() ?? 'root'
        );

        if (isset($this->taxonomyCache[$cacheKey])) {
            return $this->taxonomyCache[$cacheKey];
        }

        $taxonomy = $this->entityManager
            ->getRepository(Taxonomy::class)
            ->findOneBy([
                'name' => $name,
                'type' => $type,
                'parent' => $parent,
            ]);

        if ($taxonomy instanceof Taxonomy) {
            $this->taxonomyCache[$cacheKey] = $taxonomy;
            return $taxonomy;
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

        ++$stats['taxonomy_created'];

        $this->taxonomyCache[$cacheKey] = $taxonomy;

        return $taxonomy;
    }

    private function getCountryByCode(string $code): ?Country
    {
        if (isset($this->countryCache[$code])) {
            return $this->countryCache[$code];
        }

        $country = $this->entityManager
            ->getRepository(Country::class)
            ->findOneBy([
                'code' => $code,
            ]);

        if ($country instanceof Country) {
            $this->countryCache[$code] = $country;
        }

        return $country;
    }

    private function clearTables(SymfonyStyle $io, bool $dryRun): void
    {
        $io->warning('Clearing fauna tables');

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

    private function warmCaches(): void
    {
        foreach (
            $this->entityManager
                ->getRepository(Country::class)
                ->findAll() as $country
        ) {
            $this->countryCache[$country->getCode()] = $country;
        }
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();

        gc_collect_cycles();
    }
}
