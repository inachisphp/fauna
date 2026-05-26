<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Command;

use Doctrine\ORM\EntityManagerInterface;
use Inachis\Fauna\Entity\Taxonomy;
use Inachis\Fauna\Enum\TaxonomyType;
use Inachis\Fauna\Repository\TaxonomyRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fauna:import:taxonomy',
    description: 'Imports taxonomy data from GBIF backbone taxonomy'
)]
class ImportTaxonomyCommand extends Command
{
    private const DEFAULT_PATH = 'data/Taxon.tsv';

    private const IMPORT_TYPES = [
        'domain' => TaxonomyType::DOMAIN,
        'kingdom' => TaxonomyType::KINGDOM,
        'phylum' => TaxonomyType::PHYLUM,
        'class' => TaxonomyType::CLASS_,
        'order' => TaxonomyType::ORDER,
        'family' => TaxonomyType::FAMILY,
        'genus' => TaxonomyType::GENUS,
    ];

    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaxonomyRepository $taxonomyRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Parse and validate the source file without writing to the database.'
        );

        $this->addOption(
            'clear',
            null,
            InputOption::VALUE_NONE,
            'Clear existing taxonomy data before importing.'
        );

        $this->addOption(
            'path',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to the TSV source file.',
            self::DEFAULT_PATH
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $dryRun = (bool) $input->getOption('dry-run');
        $clear = (bool) $input->getOption('clear');
        $path = $this->resolvePath((string) $input->getOption('path'));

        if (!is_file($path) || !is_readable($path)) {
            $output->writeln(sprintf('<error>Unable to read taxonomy source file: %s</error>', $path));
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Reading taxonomy source: %s</info>', $path));

        if ($clear) {
            if ($dryRun) {
                $output->writeln('<comment>Dry-run: would clear existing taxonomy table.</comment>');
            } else {
                $this->clearTaxonomyTable($output);
            }
        }

        $rows = $this->loadTaxonomyRows($path, $output);

        if ($rows === []) {
            $output->writeln('<comment>No supported taxonomy rows were found in the source file.</comment>');
            return Command::SUCCESS;
        }

        $importIds = $this->resolveAnimaliaImportIds($rows, $output);

        if ($importIds === []) {
            $output->writeln('<comment>No Animalia taxonomy branch was found in the source file.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Importing %d Animalia taxonomy entries.</info>', count($importIds)));

        if ($dryRun) {
            $output->writeln('<comment>Dry-run complete. No database changes were made.</comment>');
            return Command::SUCCESS;
        }

        $created = $this->createTaxonomyNodes($rows, $importIds, $output);
        $linked = $this->linkTaxonomyParents($rows, $importIds, $output);

        $output->writeln(sprintf(
            '<info>Import complete. Created %d nodes and linked %d parent relationships.</info>',
            $created,
            $linked
        ));

        return Command::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\/]/', $path)) {
            return $path;
        }

        return getcwd() . DIRECTORY_SEPARATOR . $path;
    }

    private function loadTaxonomyRows(string $path, OutputInterface $output): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $output->writeln('<error>Unable to open taxonomy source file.</error>');
            return [];
        }

        $header = fgetcsv($handle, 0, "\t");

        if ($header === false) {
            fclose($handle);
            $output->writeln('<error>Unable to read TSV header.</error>');
            return [];
        }

        $header = array_map(static fn ($value) => trim((string) $value), $header);

        $required = ['taxonID', 'taxonRank', 'kingdom'];

        foreach ($required as $column) {
            if (!in_array($column, $header, true)) {
                fclose($handle);
                $output->writeln(sprintf('<error>Missing required column: %s</error>', $column));
                return [];
            }
        }

        $hasDomainColumn = in_array('domain', $header, true);
        $rows = [];
        $domainName = null;

        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $row);

            if ($data === false) {
                continue;
            }

            $taxonId = (int) ($data['taxonID'] ?? 0);

            if ($taxonId <= 0) {
                continue;
            }

            $rank = strtolower(trim((string) ($data['taxonRank'] ?? '')));
            $type = self::IMPORT_TYPES[$rank] ?? null;

            if ($type === null) {
                continue;
            }

            $kingdom = strtolower(trim((string) ($data['kingdom'] ?? '')));
            $isAnimalia = $kingdom === 'animalia';

            if (!$isAnimalia && $type !== TaxonomyType::DOMAIN) {
                continue;
            }

            if ($hasDomainColumn && $isAnimalia && $domainName === null) {
                $domainName = trim((string) ($data['domain'] ?? '')) ?: 'Eukarya';
            }

            $rows[$taxonId] = [
                'externalId' => $taxonId,
                'parentExternalId' => (int) ($data['parentNameUsageID'] ?? 0),
                'type' => $type,
                'name' => trim((string) ($data['scientificName'] ?? '')),
                'common' => trim((string) ($data['vernacularName'] ?? '')) ?: null,
                'accepted' => strtolower(trim((string) ($data['taxonomicStatus'] ?? ''))) === 'accepted',
                'canonicalName' => trim((string) ($data['canonicalName'] ?? '')) ?: null,
                'kingdom' => $kingdom,
                'rank' => $rank,
                'domainName' => $domainName,
            ];
        }

        fclose($handle);

        return $rows;
    }

    private function resolveAnimaliaImportIds(array $rows, OutputInterface $output): array
    {
        $importIds = [];
        $domainAncestorFound = false;

        foreach ($rows as $externalId => $data) {
            if ($data['kingdom'] !== 'animalia') {
                continue;
            }

            $this->collectAncestorIds($externalId, $rows, $importIds, $domainAncestorFound);
        }

        if ($importIds === []) {
            return [];
        }

        if (!$domainAncestorFound) {
            $this->ensureDomainRoot($rows, $importIds);
        }

        $output->writeln(sprintf('<info>Resolved %d Animalia taxonomy entries.</info>', count($importIds)));

        return $this->sortImportIdsByRank($importIds, $rows);
    }

    private function collectAncestorIds(int $externalId, array $rows, array &$importIds, bool &$domainAncestorFound): void
    {
        if (isset($importIds[$externalId])) {
            return;
        }

        if (!isset($rows[$externalId])) {
            return;
        }

        $importIds[$externalId] = true;

        if ($rows[$externalId]['type'] === TaxonomyType::DOMAIN) {
            $domainAncestorFound = true;
        }

        $parentExternalId = $rows[$externalId]['parentExternalId'];

        if ($parentExternalId > 0) {
            $this->collectAncestorIds($parentExternalId, $rows, $importIds, $domainAncestorFound);
        }
    }

    private function ensureDomainRoot(array &$rows, array &$importIds): void
    {
        $domainName = 'Eukarya';

        foreach ($rows as $data) {
            if ($data['kingdom'] === 'animalia' && !empty($data['domainName'])) {
                $domainName = $data['domainName'];
                break;
            }
        }

        $rows[0] = [
            'externalId' => 0,
            'parentExternalId' => 0,
            'type' => TaxonomyType::DOMAIN,
            'name' => $domainName,
            'common' => null,
            'accepted' => true,
            'canonicalName' => null,
            'kingdom' => 'animalia',
            'rank' => 'domain',
            'domainName' => $domainName,
        ];

        foreach ($rows as &$row) {
            if ($row['type'] === TaxonomyType::KINGDOM && $row['kingdom'] === 'animalia' && !isset($rows[$row['parentExternalId']])) {
                $row['parentExternalId'] = 0;
            }
        }
        unset($row);

        $importIds[0] = true;
    }

    private function sortImportIdsByRank(array $importIds, array $rows): array
    {
        $order = array_flip(array_keys(self::IMPORT_TYPES));

        usort($importIds, static function (int $a, int $b) use ($rows, $order): int {
            $rankA = $rows[$a]['rank'];
            $rankB = $rows[$b]['rank'];

            $positionA = $order[$rankA] ?? PHP_INT_MAX;
            $positionB = $order[$rankB] ?? PHP_INT_MAX;

            if ($positionA !== $positionB) {
                return $positionA <=> $positionB;
            }

            return $rows[$a]['name'] <=> $rows[$b]['name'];
        });

        return array_values($importIds);
    }

    private function createTaxonomyNodes(array $rows, array $importIds, OutputInterface $output): int
    {
        $created = 0;

        foreach ($importIds as $externalId) {
            $row = $rows[$externalId];

            $existing = $this->taxonomyRepository->findOneBy(['externalId' => $externalId]);

            if ($existing !== null) {
                continue;
            }

            $taxonomy = new Taxonomy();
            $taxonomy->setName($row['name']);
            $taxonomy->setType($row['type']);
            $taxonomy->setCommon($row['common']);
            $taxonomy->setAccepted($row['accepted']);
            $taxonomy->setCanonicalName($row['canonicalName']);
            $taxonomy->setExternalId($row['externalId']);

            $this->entityManager->persist($taxonomy);
            ++$created;

            if (($created % self::BATCH_SIZE) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $output->writeln(sprintf('Persisted %d taxonomy nodes...', $created));
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        return $created;
    }

    private function linkTaxonomyParents(array $rows, array $importIds, OutputInterface $output): int
    {
        $linked = 0;

        foreach ($importIds as $externalId) {
            $row = $rows[$externalId];
            $parentId = $row['parentExternalId'];

            if ($parentId < 0 || $parentId === $externalId || !in_array($parentId, $importIds, true)) {
                continue;
            }

            $taxonomy = $this->taxonomyRepository->findOneBy(['externalId' => $externalId]);

            if ($taxonomy === null) {
                continue;
            }

            $parent = $this->taxonomyRepository->findOneBy(['externalId' => $parentId]);

            if ($parent === null) {
                continue;
            }

            $taxonomy->setParent($parent);
            ++$linked;

            if (($linked % self::BATCH_SIZE) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $output->writeln(sprintf('Linked %d taxonomy parent relationships...', $linked));
            }
        }

        $this->entityManager->flush();

        return $linked;
    }

    private function clearTaxonomyTable(OutputInterface $output): void
    {
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $this->entityManager->beginTransaction();

        try {
            $connection->executeStatement($platform->getTruncateTableSQL('fauna_taxonomy', true));
            $this->entityManager->commit();
            $output->writeln('<info>Cleared existing fauna_taxonomy table.</info>');
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
    }
}
