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
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fauna:import:taxonomy',
    description: 'Imports taxonomy data from GBIF backbone taxonomy'
)]
class ImportTaxonomyCommand extends Command
{
    private const IMPORT_RANKS = [
        'kingdom',
        'phylum',
        'class',
        'order',
        'family',
        'genus',
    ];

    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaxonomyRepository $taxonomyRepository
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {

        $path = 'data/gbif/Taxon.tsv';

        if (!file_exists($path)) {
            $output->writeln('<error>Taxon.tsv not found</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Starting taxonomy import...</info>');

        $handle = fopen($path, 'r');

        if (!$handle) {
            $output->writeln('<error>Unable to open file</error>');
            return Command::FAILURE;
        }

        $header = fgetcsv($handle, separator: "\t");

        if (!$header) {
            $output->writeln('<error>Invalid TSV header</error>');
            return Command::FAILURE;
        }

        $count = 0;

        /*
         |--------------------------------------------------------------------------
         | PASS 1
         |--------------------------------------------------------------------------
         | Create all taxonomy entities WITHOUT parent relations
         |--------------------------------------------------------------------------
         */

        $output->writeln('<comment>Pass 1: Creating taxonomy nodes...</comment>');

        while (($row = fgetcsv($handle, separator: "\t")) !== false) {

            $data = array_combine($header, $row);

            if (!$data) {
                continue;
            }

            /*
             |--------------------------------------------------------------------------
             | Only Animalia
             |--------------------------------------------------------------------------
             */

            if (($data['kingdom'] ?? null) !== 'Animalia') {
                continue;
            }

            /*
             |--------------------------------------------------------------------------
             | Only supported ranks
             |--------------------------------------------------------------------------
             */

            $rank = strtolower(trim($data['taxonRank'] ?? ''));

            if (!in_array($rank, self::IMPORT_RANKS, true)) {
                continue;
            }

            /*
             |--------------------------------------------------------------------------
             | Skip duplicates
             |--------------------------------------------------------------------------
             */

            $externalId = (int) $data['taxonID'];

            $existing = $this->taxonomyRepository->findOneBy([
                'externalId' => $externalId,
            ]);

            if ($existing) {
                continue;
            }

            /*
             |--------------------------------------------------------------------------
             | Create taxonomy entity
             |--------------------------------------------------------------------------
             */

            $taxonomy = new Taxonomy();

            $taxonomy
                ->setName($data['scientificName'] ?? '')
                ->setType(TaxonomyType::fromValue($rank))
                ->setCommon($data['vernacularName'] ?: null);

            /*
             |--------------------------------------------------------------------------
             | Reflection workaround until setters are added
             |--------------------------------------------------------------------------
             */

            $this->setPrivateProperty(
                $taxonomy,
                'externalId',
                $externalId
            );

            $this->setPrivateProperty(
                $taxonomy,
                'accepted',
                ($data['taxonomicStatus'] ?? '') === 'accepted'
            );

            $this->setPrivateProperty(
                $taxonomy,
                'canonicalName',
                $data['canonicalName'] ?: null
            );

            $this->entityManager->persist($taxonomy);

            ++$count;

            if (($count % self::BATCH_SIZE) === 0) {

                $this->entityManager->flush();
                $this->entityManager->clear();

                $output->writeln(sprintf(
                    'Imported %d taxonomy nodes...',
                    $count
                ));
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        fclose($handle);

        /*
         |--------------------------------------------------------------------------
         | PASS 2
         |--------------------------------------------------------------------------
         | Link parent relationships
         |--------------------------------------------------------------------------
         */

        $output->writeln('<comment>Pass 2: Linking parent relationships...</comment>');

        $handle = fopen($path, 'r');

        $header = fgetcsv($handle, separator: "\t");

        $linked = 0;

        while (($row = fgetcsv($handle, separator: "\t")) !== false) {

            $data = array_combine($header, $row);

            if (!$data) {
                continue;
            }

            if (($data['kingdom'] ?? null) !== 'Animalia') {
                continue;
            }

            $rank = strtolower(trim($data['taxonRank'] ?? ''));

            if (!in_array($rank, self::IMPORT_RANKS, true)) {
                continue;
            }

            $externalId = (int) $data['taxonID'];
            $parentExternalId = (int) ($data['parentNameUsageID'] ?? 0);

            if (!$parentExternalId) {
                continue;
            }

            $taxonomy = $this->taxonomyRepository->findOneBy([
                'externalId' => $externalId,
            ]);

            if (!$taxonomy) {
                continue;
            }

            $parent = $this->taxonomyRepository->findOneBy([
                'externalId' => $parentExternalId,
            ]);

            if (!$parent) {
                continue;
            }

            $taxonomy->setParent($parent);

            ++$linked;

            if (($linked % self::BATCH_SIZE) === 0) {

                $this->entityManager->flush();
                $this->entityManager->clear();

                $output->writeln(sprintf(
                    'Linked %d taxonomy relationships...',
                    $linked
                ));
            }
        }

        $this->entityManager->flush();

        fclose($handle);

        $output->writeln(sprintf(
            '<info>Import complete. Imported %d taxonomy nodes.</info>',
            $count
        ));

        return Command::SUCCESS;
    }

    private function setPrivateProperty(
        object $object,
        string $property,
        mixed $value
    ): void {

        $reflection = new \ReflectionProperty($object, $property);

        $reflection->setAccessible(true);

        $reflection->setValue($object, $value);
    }
}