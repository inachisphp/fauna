<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Command;

use SplFileObject;
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
    /**
     * @var const The number of records to process before flushing to the database.
     */
    private const BATCH_SIZE = 20;

    /**
     * @var array In-memory cache to map taxonomy uniqueness keys to their database IDs, to minimize redundant queries during import.
     */
    private array $taxonomyIdCache = [];

    /**
     * @var array In-memory cache to store pending taxonomy records before flushing to the database.
     */
    private array $pendingTaxonomyCache = [];

    /**
     * @var array In-memory cache to store country IDs by their codes.
     */
    private array $countryIdCache = [];

    /**
     * @var array A rolling log of recently encountered malformed rows during TSV parsing, to aid in debugging without overwhelming memory. Each entry includes line number, expected vs actual column counts, and a preview of the row data.
     */
    private array $recentMalformedRows = [];

    /**
     * @var integer The number of records processed since the last flush to the database.
     */
    private int $flushCount = 0;

    /**
     * Constructor for the ImportTaxonomyCommand.
     *
     * @param EntityManagerInterface $entityManager
     * @param Connection $connection
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    /**
     * Configures the command.
     *
     * @return void
     */
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
                'patch-ids',
                null,
                InputOption::VALUE_NONE,
                'One-time patch to backfill missing external_id links on high-level taxonomy entries based on Taxon.tsv data'
            )

            ->addOption(
                'import-taxonomy',
                null,
                InputOption::VALUE_NONE,
                'Import Taxon.tsv'
            )
            ->addOption(
                'import-vernacular',
                null,
                InputOption::VALUE_NONE,
                'Import VernacularName.tsv'
            )
            ->addOption(
                'import-distribution',
                null,
                InputOption::VALUE_NONE,
                'Import Distribution.tsv'
            )
            ->addOption(
                'import-description',
                null,
                InputOption::VALUE_NONE,
                'Import Description.tsv'
            );
    }

    /**
     * Executes the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        ini_set('memory_limit', '1024M');

        gc_enable();

        // CRITICAL: Prevent Doctrine DBAL middleware memory accumulation.
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

        $patchIds = (bool) $input->getOption('patch-ids');
        $importTaxa = (bool) $input->getOption('import-taxonomy');
        $importVernacular = (bool) $input->getOption('import-vernacular');
        $importDistribution = (bool) $input->getOption('import-distribution');
        $importDescription = (bool) $input->getOption('import-description');

        // If no specific import options are provided, import all.
        if (
            !$patchIds
            && !$importTaxa
            && !$importVernacular
            && !$importDistribution
            && !$importDescription
        ) {
            $importTaxa = true;
            $importVernacular = true;
            $importDistribution = true;
            $importDescription = true;
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
            'description_updated' => 0,
        ];

        if ($patchIds) {
            $this->patchTaxonomyIds(
                $directory . '/Taxon.tsv',
                $dryRun,
                $output
            );
        }

        if ($importTaxa) {
            $io->section(sprintf('Importing Taxon.tsv (%d lines)', $this->countLines($directory . '/Taxon.tsv')));

            $this->importTaxa(
                $directory . '/Taxon.tsv',
                $stats,
                $dryRun,
                $output
            );
        }

        if ($importVernacular) {
            $io->section(sprintf('Importing VernacularName.tsv (%d lines)', $this->countLines($directory . '/VernacularName.tsv')));

            $this->importVernacular(
                $directory . '/VernacularName.tsv',
                $stats,
                $dryRun,
                $output
            );
        }

        if ($importDistribution) {
            $io->section(sprintf('Importing Distribution.tsv (%d lines)', $this->countLines($directory . '/Distribution.tsv')));

            $this->importDistribution(
                $directory . '/Distribution.tsv',
                $stats,
                $dryRun,
                $output
            );
        }

        if ($importDescription) {
            $io->section(sprintf('Importing Description.tsv (%d lines)', $this->countLines($directory . '/Description.tsv')));

            $this->importDescription(
                $directory . '/Description.tsv',
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
                ['Description Updated', number_format($stats['description_updated'])],
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

    /**
     * One-time helper to scan Taxon.tsv and backfill external_id attributes 
     * for matching high-level taxonomy rows already present in the database.
     */
    /**
     * One-time helper to scan Taxon.tsv and backfill external_id attributes 
     * for matching high-level taxonomy rows already present in the database,
     * validating parent associations to safely bypass homonyms.
     */
    private function patchTaxonomyIds(
        string $file,
        bool $dryRun,
        OutputInterface $output
    ): void {
        if (!is_file($file)) {
            $output->writeln('<error>Taxon.tsv not found for patching.</error>');
            return;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return;
        }

        $headers = fgetcsv($handle, 0, "\t", '"', '\\');
        $headers = array_map('trim', $headers);
        
        $output->writeln('<info>Safe-patching missing high-level taxonomy external_id rows...</info>');
        $progress = new ProgressBar($output);
        $progress->start();

        $updated = 0;
        $batch = 0;

        // Map TSV lower-case ranks to your internal database Enum values
        $enumTypeMap = [
            'kingdom' => 'kingdom',
            'phylum'  => 'phylum',
            'class'   => 'class',
            'order'   => 'order',
            'family'  => 'family',
            'genus'   => 'genus',
        ];

        while (($row = fgetcsv($handle, 0, "\t", '"', '\\')) !== false) {
            $progress->advance();
            if (count($row) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, $row);
            $rank = strtolower(trim((string) ($data['taxonRank'] ?? '')));
            
            // Skip terminal leaves like species or subspecies
            if (in_array($rank, ['species', 'subspecies'], true)) {
                continue;
            }

            $taxonId = isset($data['taxonID']) ? (int) $data['taxonID'] : null;
            $canonicalName = trim((string) ($data['canonicalName'] ?? ''));

            if ($taxonId === null || $canonicalName === '' || !isset($enumTypeMap[$rank])) {
                continue;
            }

            $internalType = $enumTypeMap[$rank];

            // --- DETECT PARENT VALUE FOR TREE VERIFICATION ---
            // Build the tier priorities to discover who this item's immediate populated parent is
            $levelOrder = ['genus', 'family', 'order', 'class', 'phylum', 'kingdom'];
            $currentIndex = array_search($rank, $levelOrder, true);
            
            $parentName = null;
            $parentType = null;

            if ($currentIndex !== false) {
                // Read backwards up the columns to find the nearest non-empty ancestor
                for ($i = $currentIndex + 1; $i < count($levelOrder); $i++) {
                    $ancestorColumn = $levelOrder[$i];
                    if ($ancestorColumn === 'class') {
                        $ancestorColumn = 'class_'; // Account for class keyword extensions if needed
                    }
                    
                    // Normalize keyword mapping back to standard column names
                    $colKey = ($ancestorColumn === 'class_') ? 'class' : $ancestorColumn;
                    $ancestorValue = trim((string) ($data[$colKey] ?? ''));

                    if ($ancestorValue !== '') {
                        $parentName = $ancestorValue;
                        $parentType = $enumTypeMap[($colKey === 'class') ? 'class' : $colKey];
                        break;
                    }
                }
            }

            // Resolve the active target entity ID matching both self criteria and parent tree
            $targetId = null;
            if ($parentName !== null && $parentType !== null) {
                $targetId = $this->connection->fetchOne(
                    'SELECT child.id 
                     FROM fauna_taxonomy child
                     JOIN fauna_taxonomy parent ON child.parent_id = parent.id
                     WHERE child.name = :name 
                       AND child.type = :type 
                       AND child.external_id IS NULL
                       AND parent.name = :p_name 
                       AND parent.type = :p_type
                     LIMIT 1',
                    [
                        'name'   => $canonicalName,
                        'type'   => $internalType,
                        'p_name' => $parentName,
                        'p_type' => $parentType
                    ]
                );
            } else {
                // Root nodes (e.g. Kingdom Animalia) won't have a parent
                $targetId = $this->connection->fetchOne(
                    'SELECT id 
                     FROM fauna_taxonomy 
                     WHERE name = :name 
                       AND type = :type 
                       AND parent_id IS NULL 
                       AND external_id IS NULL
                     LIMIT 1',
                    [
                        'name' => $canonicalName,
                        'type' => $internalType
                    ]
                );
            }

            // --- EXECUTE SAFE BOUNDED UPDATE ---
            if (!$dryRun && $targetId !== false) {
                // MariaDB allows LIMIT 1 constraints directly on primary key evaluations 
                $affected = $this->connection->executeStatement(
                    'UPDATE fauna_taxonomy 
                     SET external_id = :ext_id 
                     WHERE id = :id 
                     LIMIT 1',
                    [
                        'ext_id' => $taxonId,
                        'id'     => $targetId
                    ]
                );

                if ($affected > 0) {
                    $updated++;
                    $batch++;
                    if ($batch >= 250) {
                        $batch = 0;
                        gc_collect_cycles();
                    }
                }
            }
        }

        fclose($handle);
        $progress->finish();
        $output->writeln('');
        $output->writeln(sprintf('<info>Patch complete. Safely backfilled external_id constraints on %s taxonomy tiers.</info>', number_format($updated)));
    }

    /**
     * Imports taxonomy data from a TSV file.
     *
     * @param string $file
     * @param array $stats
     * @param boolean $dryRun
     * @param OutputInterface $output
     * @return void
     */
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
            $rowRank = strtolower(trim((string) ($data['taxonRank'] ?? '')));
            $taxonIdAttr = isset($data['taxonID']) ? (int) $data['taxonID'] : null;

            $levels = [
                'kingdom' => TaxonomyType::KINGDOM,
                'phylum' => TaxonomyType::PHYLUM,
                'class' => TaxonomyType::CLASS_,
                'order' => TaxonomyType::ORDER,
                'family' => TaxonomyType::FAMILY,
                'genus' => TaxonomyType::GENUS,
            ];

            foreach ($levels as $column => $meta) {
                [$type, $rankName] = $meta;
                $value = trim((string) ($data[$column] ?? ''));

                if ($value === '') {
                    continue;
                }

                $passId = ($rowRank === $rankName) ? $taxonIdAttr : null;

                $parent = $this->getOrCreateTaxonomy(
                    $value,
                    $type,
                    $parent,
                    $dryRun,
                    $stats,
                    $passId
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

    /**
     * Imports vernacular names from a TSV file.
     *
     * @param string $file
     * @param array $stats
     * @param bool $dryRun
     * @param OutputInterface $output
     * @return void
     */
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
        
        // Memory cache to keep track of names assigned during this script run 
        // to minimize database SELECT overhead across fragmented files.
        $assignedNamesCache = [];

        // Helper closure to evaluate which of two names is superior
        $isBetterName = function(string $newCandidate, ?string $existingName): bool {
            if ($existingName === null || trim($existingName) === '') {
                return true;
            }

            // 1. Prioritize Title Case / Uppercase starting letters over lowercase
            $isCapNew = ctype_upper(substr($newCandidate, 0, 1));
            $isCapOld = ctype_upper(substr($existingName, 0, 1));
            
            if ($isCapNew !== $isCapOld) {
                return $isCapNew && !$isCapOld;
            }

            // 2. Prefer longer/descriptive strings (e.g., "European Peacock Butterfly" > "Peacock")
            $lenNew = strlen($newCandidate);
            $lenOld = strlen($existingName);
            if ($lenNew !== $lenOld) {
                return $lenNew > $lenOld;
            }

            // 3. Fallback to case-insensitive alphabetical sorting for consistency
            return strcasecmp($newCandidate, $existingName) < 0;
        };

        while (($row = fgetcsv($handle, 0, "\t", '"', '\\')) !== false) {
            ++$stats['processed'];
            $progress->advance();

            if (count($row) !== count($headers)) {
                ++$stats['malformed_rows'];
                continue;
            }

            $data = array_combine($headers, $row);
            if ($data === false) {
                ++$stats['malformed_rows'];
                continue;
            }

            // Strict filter for English language only
            $language = trim((string) ($data['language'] ?? ''));
            if ($language !== 'en') {
                continue;
            }

            $taxonId = isset($data['taxonID']) ? (int) $data['taxonID'] : null;
            $vernacular = trim((string) ($data['vernacularName'] ?? ''));

            if ($taxonId === null || $vernacular === '') {
                continue;
            }

            // --- RESOLVE TARGET DETAILS ---
            $speciesId = $this->getSpeciesIdByExternalId($taxonId);
            $taxonomyId = null;
            $tableName = null;
            $targetId = null;

            if ($speciesId !== null) {
                $targetId = $speciesId;
                $tableName = 'fauna_species';
            } else {
                // If not found in species, fallback to check high-level taxonomy structural rows
                $taxonomyId = $this->getTaxonomyIdByExternalId($taxonId);
                if ($taxonomyId !== null) {
                    $targetId = $taxonomyId;
                    $tableName = 'fauna_taxonomy';
                }
            }

            // If it maps to neither, skip out early
            if ($targetId === null) {
                ++$stats['skipped'];
                continue;
            }

            // Determine what name this target currently has (using cache first, then database)
            $currentName = null;
            $cacheKey = $tableName . '|' . $targetId;

            if (isset($assignedNamesCache[$cacheKey])) {
                $currentName = $assignedNamesCache[$cacheKey];
            } else {
                // For fauna_taxonomy, we read from the 'common' column; for fauna_species, from 'name'
                $columnName = ($tableName === 'fauna_taxonomy') ? 'common' : 'name';
                
                $dbName = $this->connection->fetchOne(
                    "SELECT {$columnName} FROM {$tableName} WHERE id = :id LIMIT 1",
                    ['id' => $targetId]
                );
                $currentName = $dbName !== false ? $dbName : null;
            }

            // Compare names using our preference rules
            if ($isBetterName($vernacular, $currentName)) {
                if ($tableName === 'fauna_species') {
                    $species = $this->entityManager->getReference(
                        Species::class,
                        \Ramsey\Uuid\Uuid::fromString($targetId)
                    );

                    if (method_exists($species, 'setName')) {
                        $species->setName($vernacular);
                        $assignedNamesCache[$cacheKey] = $vernacular;
                        ++$stats['vernacular_updated'];
                        ++$batch;
                    }
                } else {
                    $taxonomy = $this->entityManager->getReference(
                        Taxonomy::class,
                        \Ramsey\Uuid\Uuid::fromString($targetId)
                    );

                    // Handles whichever setter name matches your entity structure ('setCommon' vs 'setName')
                    if (method_exists($taxonomy, 'setCommon')) {
                        $taxonomy->setCommon($vernacular);
                        $assignedNamesCache[$cacheKey] = $vernacular;
                        ++$stats['vernacular_updated'];
                        ++$batch;
                    } elseif (method_exists($taxonomy, 'setName')) {
                        // Optional fallback if your taxonomy model uses setName instead of setCommon
                        $taxonomy->setName($vernacular);
                        $assignedNamesCache[$cacheKey] = $vernacular;
                        ++$stats['vernacular_updated'];
                        ++$batch;
                    }
                }

                // Batch flushing management
                if ($batch >= self::BATCH_SIZE) {
                    if (!$dryRun) {
                        $this->flushAndClear();
                        // Clear local memory lookup bounds selectively if file explodes
                        if (count($assignedNamesCache) > 50000) {
                            $assignedNamesCache = [];
                        }
                    }
                    $batch = 0;
                }
            } else {
                ++$stats['skipped'];
            }
        }

        if (!$dryRun) {
            $this->flushAndClear();
        }

        fclose($handle);
        $progress->finish();
        $output->writeln('');
    }

    /**
     * Helper to look up a taxonomy record ID by its external_id
     */
    private function getTaxonomyIdByExternalId(int $externalId): ?string
    {
        static $taxCache = [];

        if (isset($taxCache[$externalId])) {
            return $taxCache[$externalId];
        }

        $id = $this->connection->fetchOne(
            'SELECT id FROM fauna_taxonomy WHERE external_id = :external_id LIMIT 1',
            ['external_id' => $externalId]
        );

        if (!$id) {
            return null;
        }

        return $taxCache[$externalId] = (string) $id;
    }

    /**
     * Imports distribution data from a TSV file.
     *
     * @param string $file
     * @param array $stats
     * @param bool $dryRun
     * @param OutputInterface $output
     * @return void
     */
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

        // High efficiency line-based batching boundary
        $processedBatchCount = 0;
        $lineBatchSize = 1000; 

        // In-memory runtime caches to handle fragmentation across non-sequential rows
        $assignedDistributionsCache = [];
        $assignedThreatStatusCache = [];

        // Define IUCN Severity Levels (higher numbers = more critical threat)
        $iucnSeverity = [
            'EX' => 7, // Extinct
            'EW' => 6, // Extinct in the Wild
            'CR' => 5, // Critically Endangered
            'EN' => 4, // Endangered
            'VU' => 3, // Vulnerable
            'NT' => 2, // Near Threatened
            'LC' => 1, // Least Concern
        ];

        // Normalizer to convert full text to clean 2-letter codes expected by IucnStatus enum
        $normalizeThreatStatus = function(string $status) {
            $status = strtoupper(trim($status));
            $mapping = [
                'EXTINCT' => 'EX',
                'EXTINCT IN THE WILD' => 'EW',
                'CRITICALLY ENDANGERED' => 'CR',
                'CRITICAL' => 'CR',
                'ENDANGERED' => 'EN',
                'VULNERABLE' => 'VU',
                'NEAR THREATENED' => 'NT',
                'LEAST CONCERN' => 'LC'
            ];
            return $mapping[$status] ?? $status;
        };

        // Helper closure to see if a candidate IUCN status is more severe than what is currently set
        $isMoreEndangered = function(?string $newStatus, ?string $currentStatus) use ($iucnSeverity, $normalizeThreatStatus): bool {
            if ($newStatus === null || trim($newStatus) === '') {
                return false;
            }
            
            $newCode = $normalizeThreatStatus($newStatus);
            if (!isset($iucnSeverity[$newCode])) {
                return false; // Not a recognized threat tier (e.g. DD, NE)
            }

            if ($currentStatus === null || trim($currentStatus) === '') {
                return true;
            }
            
            $currentCode = $normalizeThreatStatus($currentStatus);
            
            $newRank = $iucnSeverity[$newCode] ?? 0;
            $currentRank = $iucnSeverity[$currentCode] ?? 0;

            return $newRank > $currentRank;
        };

        while (($row = fgetcsv($handle, 0, "\t", '"', '\\')) !== false) {
            ++$stats['processed'];
            ++$processedBatchCount;
            $progress->advance();

            if (count($row) !== count($headers)) {
                ++$stats['malformed_rows'];
                continue;
            }

            $data = array_combine($headers, $row);
            if ($data === false) {
                ++$stats['malformed_rows'];
                continue;
            }

            $taxonId = isset($data['taxonID']) ? (int) $data['taxonID'] : null;
            $countryCode = trim((string) ($data['countryCode'] ?? ''));
            $threatStatus = trim((string) ($data['threatStatus'] ?? ''));

            if ($taxonId === null) {
                ++$stats['skipped'];
                continue;
            }

            $speciesId = $this->getSpeciesIdByExternalId($taxonId);
            if ($speciesId === null) {
                ++$stats['skipped'];
                continue;
            }

            // --- 1. HANDLE IUCN THREAT STATUS OVERLAPS ---
            if ($threatStatus !== '') {
                $currentThreat = null;

                if (isset($assignedThreatStatusCache[$speciesId])) {
                    $currentThreat = $assignedThreatStatusCache[$speciesId];
                } else {
                    // Fetch existing status from database field via DBAL connection
                    $dbThreat = $this->connection->fetchOne(
                        'SELECT iucn FROM fauna_species WHERE id = :id LIMIT 1',
                        ['id' => $speciesId]
                    );
                    $currentThreat = $dbThreat !== false ? $dbThreat : null;
                }

                if ($isMoreEndangered($threatStatus, $currentThreat)) {
                    $species = $this->entityManager->getReference(
                        Species::class,
                        \Ramsey\Uuid\Uuid::fromString($speciesId)
                    );

                    // Normalize to the shortcode (e.g., "Vulnerable" -> "VU") before Enum factory check
                    $cleanCode = $normalizeThreatStatus($threatStatus);
                    $enumValue = IucnStatus::tryFromValue($cleanCode);
                    
                    if ($enumValue !== null && method_exists($species, 'setIucn')) {
                        $species->setIucn($enumValue);
                        $assignedThreatStatusCache[$speciesId] = $cleanCode;
                    }
                }
            }

            // --- 2. HANDLE GEOGRAPHIC COUNTRY MAPPING ---
            if ($countryCode !== '') {
                $countryId = $this->getCountryIdByCode($countryCode);

                if ($countryId !== null) {
                    $uniqueComboKey = $speciesId . '|' . $countryId;

                    // Verify if this combination has already been matched during this run
                    if (!isset($assignedDistributionsCache[$uniqueComboKey])) {
                        
                        // Fallback check against the database bridge table
                        $existsInDb = (bool) $this->connection->fetchOne(
                            'SELECT 1 FROM fauna_species_to_country WHERE species_id = :s_id AND country_id = :c_id LIMIT 1',
                            ['s_id' => $speciesId, 'c_id' => $countryId]
                        );

                        if (!$existsInDb) {
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
                                    // Safeguard catch
                                }
                            }
                            
                            $assignedDistributionsCache[$uniqueComboKey] = true;
                            ++$stats['distribution_updated'];
                        } else {
                            $assignedDistributionsCache[$uniqueComboKey] = true;
                            ++$stats['duplicates_skipped'];
                        }
                    } else {
                        ++$stats['duplicates_skipped'];
                    }
                } else {
                    ++$stats['skipped'];
                }
            }

            // --- 3. BOUNDED PROCESSING FLUSH ---
            // Flush exactly every 1,000 processed input lines to save managed entities safely
            if ($processedBatchCount >= $lineBatchSize) {
                if (!$dryRun) {
                    $this->flushAndClear();
                }
                $processedBatchCount = 0;
            }
        }

        if (!$dryRun) {
            $this->flushAndClear();
        }

        fclose($handle);
        $progress->finish();
        $output->writeln('');
    }

    private function importDescription(
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
        
        // Memory cache to track description updates during this execution run
        $assignedDescriptionsCache = [];

        // Inline helper to convert standard HTML tags directly into clean Markdown syntax
        $htmlToMarkdown = function(string $html): string {
            // 1. Normalize linebreaks and paragraph tags
            $md = preg_replace('/<(br|br \/|p)>/i', "\n", $html);
            $md = preg_replace('/<\/p>/i', "\n\n", $md);

            // 2. Convert headers (e.g. <h3>Title</h3> to ### Title)
            $md = preg_replace_callback('/<h([1-6])>(.*?)<\/h\1>/i', function($matches) {
                return "\n" . str_repeat('#', (int)$matches[1]) . ' ' . trim($matches[2]) . "\n";
            }, $md);

            // 3. Handle list elements
            $md = preg_replace('/<(ul|ol)>/i', "\n", $md);
            $md = preg_replace('/<\/(ul|ol)>/i', "\n\n", $md);
            $md = preg_replace('/<li>(.*?)<\/li>/i', "* $1\n", $md);

            // 4. Handle emphasis variables (Bold and Italics)
            $md = preg_replace('/<([bi]|strong|em)>(.*?)<\/\1>/i', '**$2**', $md);

            // 5. Clean up any remaining rogue unmapped tags and trim whitespace artifacts
            $md = strip_tags($md);
            
            // Clean up excess consecutive newlines down to a max of two
            $md = preg_replace("/\n{3,}/", "\n\n", $md);

            return trim($md);
        };

        while (($row = fgetcsv($handle, 0, "\t", '"', '\\')) !== false) {
            ++$stats['processed'];
            $progress->advance();

            if (count($row) !== count($headers)) {
                ++$stats['malformed_rows'];
                continue;
            }

            $data = array_combine($headers, $row);
            if ($data === false) {
                ++$stats['malformed_rows'];
                continue;
            }

            // Strict filter for English language only
            $language = trim((string) ($data['language'] ?? ''));
            if ($language !== 'en') {
                continue;
            }

            // Strict filter for "description" type only
            $type = strtolower(trim((string) ($data['type'] ?? '')));
            if ($type !== 'description') {
                continue;
            }

            $taxonId = isset($data['taxonID']) ? (int) $data['taxonID'] : null;
            $rawDescription = trim((string) ($data['description'] ?? ''));

            if ($taxonId === null || $rawDescription === '') {
                continue;
            }

            // Convert incoming text to standard Markdown format
            $cleanedText = $htmlToMarkdown($rawDescription);

            $speciesId = $this->getSpeciesIdByExternalId($taxonId);
            if ($speciesId === null) {
                ++$stats['skipped'];
                continue;
            }

            // Retrieve what text this species currently holds (check memory cache first)
            $currentText = null;
            if (isset($assignedDescriptionsCache[$speciesId])) {
                $currentText = $assignedDescriptionsCache[$speciesId];
            } else {
                $dbText = $this->connection->fetchOne(
                    'SELECT description FROM fauna_species WHERE id = :id LIMIT 1',
                    ['id' => $speciesId]
                );
                $currentText = $dbText !== false ? $dbText : null;
            }

            // Guard against appending identical description duplicates
            if ($currentText !== null && str_contains($currentText, $cleanedText)) {
                ++$stats['skipped'];
                continue;
            }

            // Concatenate sequentially if multiple 'description'-typed blocks belong to this taxon
            if ($currentText === null || trim($currentText) === '') {
                $newDescriptionText = $cleanedText;
            } else {
                $newDescriptionText = $currentText . "\n\n" . $cleanedText;
            }

            // Acquire proxy and prepare changes
            $species = $this->entityManager->getReference(
                Species::class,
                \Ramsey\Uuid\Uuid::fromString($speciesId)
            );

            if (method_exists($species, 'setDescription')) {
                $species->setDescription($newDescriptionText);
                $assignedDescriptionsCache[$speciesId] = $newDescriptionText;
                
                ++$stats['description_updated'];
                ++$batch;

                if ($batch >= self::BATCH_SIZE) {
                    if (!$dryRun) {
                        $this->flushAndClear();
                        if (count($assignedDescriptionsCache) > 30000) {
                            $assignedDescriptionsCache = [];
                        }
                    }
                    $batch = 0;
                }
            }
        }

        if (!$dryRun) {
            $this->flushAndClear();
        }

        fclose($handle);
        $progress->finish();
        $output->writeln('');
    }

    /**
     * Gets or creates a taxonomy record.
     *
     * @param string $name
     * @param TaxonomyType $type
     * @param Taxonomy|null $parent
     * @param boolean $dryRun
     * @param array $stats
     * @return Taxonomy
     */
    private function getOrCreateTaxonomy(
        string $name,
        TaxonomyType $type,
        ?Taxonomy $parent,
        bool $dryRun,
        array &$stats,
        ?int $externalId = null
    ): Taxonomy {
        $parentId = $parent?->getId()?->toString();

        $cacheKey = sprintf(
            '%s|%s|%s',
            $type->value,
            strtolower($name),
            $parentId ?? 'root'
        );

        if (isset($this->pendingTaxonomyCache[$cacheKey])) {
            // Update external ID if discovered on a cached object
            if ($externalId !== null && method_exists($this->pendingTaxonomyCache[$cacheKey], 'setExternalId')) {
                $this->pendingTaxonomyCache[$cacheKey]->setExternalId($externalId);
            }
            return $this->pendingTaxonomyCache[$cacheKey];
        }

        if (isset($this->taxonomyIdCache[$cacheKey])) {
            $ref = $this->entityManager->getReference(Taxonomy::class, Uuid::fromString($this->taxonomyIdCache[$cacheKey]));
            if ($externalId !== null) {
                // If already in DB, update external_id immediately via a simple update statement
                $this->connection->executeStatement(
                    'UPDATE fauna_taxonomy SET external_id = :ext_id WHERE id = :id AND external_id IS NULL',
                    ['ext_id' => $externalId, 'id' => $this->taxonomyIdCache[$cacheKey]]
                );
            }
            return $ref;
        }

        $existingId = $this->connection->fetchOne(
            'SELECT id FROM fauna_taxonomy WHERE name = :name AND type = :type AND ((:parent IS NULL AND parent_id IS NULL) OR parent_id = :parent) LIMIT 1',
            ['name' => $name, 'type' => $type->value, 'parent' => $parentId]
        );

        if ($existingId !== false) {
            $this->taxonomyIdCache[$cacheKey] = $existingId;
            if ($externalId !== null) {
                $this->connection->executeStatement(
                    'UPDATE fauna_taxonomy SET external_id = :ext_id WHERE id = :id AND external_id IS NULL',
                    ['ext_id' => $externalId, 'id' => $existingId]
                );
            }
            return $this->entityManager->getReference(Taxonomy::class, Uuid::fromString($existingId));
        }

        $taxonomy = new Taxonomy(name: $name, type: $type, parent: $parent);
        $taxonomy->setCanonicalName($name);
        $taxonomy->setAccepted(true);
        if ($externalId !== null && method_exists($taxonomy, 'setExternalId')) {
            $taxonomy->setExternalId($externalId);
        }

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

    private function getSpeciesIdByExternalId(int $externalId): ?string
    {
        static $cache = [];

        if (isset($cache[$externalId])) {
            return $cache[$externalId];
        }

        $id = $this->connection->fetchOne(
            '
            SELECT id
            FROM fauna_species
            WHERE external_id = :external_id
            LIMIT 1
            ',
            [
                'external_id' => $externalId,
            ]
        );

        if (!$id) {
            return null;
        }

        return $cache[$externalId] = $id;
    }

    private function getCountryIdByCode(string $countryCode): ?string
    {
        $countryCode = strtoupper(trim($countryCode));

        if ($countryCode === '') {
            return null;
        }

        if (isset($this->countryIdCache[$countryCode])) {
            return $this->countryIdCache[$countryCode];
        }

        $id = $this->connection->fetchOne(
            'SELECT id FROM fauna_country WHERE code = :code LIMIT 1',
            ['code' => $countryCode]
        );

        if (!$id) {
            return null;
        }

        return $this->countryIdCache[$countryCode] = (string) $id;
    }

    private function countLines(string $file): int
    {
        $spl = new SplFileObject($file, 'rb');

        $spl->seek(PHP_INT_MAX);

        // line numbers are 0-based, so add 1
        return $spl->key() + 1;
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
