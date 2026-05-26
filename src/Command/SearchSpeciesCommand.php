<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fauna:search',
    description: 'Searches for species by common or latin name, displaying taxonomy path and countries'
)]
class SearchSpeciesCommand extends Command
{
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'query',
            InputArgument::REQUIRED,
            'The common name or scientific/latin name to search for'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $query = trim((string) $input->getArgument('query'));

        if ($query === '') {
            $io->error('Search query cannot be empty.');
            return Command::FAILURE;
        }

        $io->title(sprintf('Searching fauna database for: "%s"', $query));

        // 1. Fetch matching species records
        $speciesMatches = $this->connection->fetchAllAssociative(
            'SELECT id, name, latin, iucn, genus_id 
             FROM fauna_species 
             WHERE name LIKE :query OR latin LIKE :query 
             LIMIT 15',
            ['query' => '%' . $query . '%']
        );

        if (empty($speciesMatches)) {
            $io->warning('No species found matching your query.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Found %d matching results:', count($speciesMatches)));

        foreach ($speciesMatches as $index => $species) {
            $commonName = !empty($species['name']) ? $species['name'] : '<None Assigned>';
            $iucnStatus = !empty($species['iucn']) ? sprintf(' [%s]', $species['iucn']) : '';
            
            $io->writeln(sprintf(
                '<info>[%d]</info> <bold>%s</bold> (<comment>%s</comment>)%s', 
                $index + 1, 
                $commonName, 
                $species['latin'],
                $iucnStatus
            ));

            // 2. Resolve Full Taxonomy Tree/Path
            $taxonomyPath = $this->getTaxonomyPath($species['genus_id']);
            if (!empty($taxonomyPath)) {
                $io->writeln('    <comment>Taxonomy:</comment> ' . implode(' ➔ ', $taxonomyPath));
            } else {
                $io->writeln('    <comment>Taxonomy:</comment> Unassigned or Root level');
            }

            // 3. Fetch distribution countries
            $countries = $this->connection->fetchFirstColumn(
                'SELECT c.name 
                 FROM fauna_country c
                 JOIN fauna_species_to_country s2c ON s2c.country_id = c.id
                 WHERE s2c.species_id = :species_id
                 ORDER BY c.name ASC',
                ['species_id' => $species['id']]
            );

            if (!empty($countries)) {
                $io->writeln('    <comment>Countries:</comment> ' . implode(', ', $countries));
            } else {
                $io->writeln('    <comment>Countries:</comment> No distribution records mapped.');
            }

            $io->writeln(str_repeat('-', 60));
        }

        return Command::SUCCESS;
    }

    /**
     * Recursively traces back parental rows to generate the complete taxonomy array path.
     */
    private function getTaxonomyPath(?string $parentId): array
    {
        $path = [];
        
        while ($parentId !== null) {
            $row = $this->connection->fetchAssociative(
                'SELECT name, type, parent_id FROM fauna_taxonomy WHERE id = :id LIMIT 1',
                ['id' => $parentId]
            );

            if (!$row) {
                break;
            }

            // Format as "Type: Name" (e.g., "CLASS: Mammalia")
            $path[] = sprintf('%s: %s', strtoupper($row['type']), $row['name']);
            $parentId = $row['parent_id'];
        }

        // Since we climbed upwards from Genus to Kingdom, we reverse it to display left-to-right
        return array_reverse($path);
    }
}
