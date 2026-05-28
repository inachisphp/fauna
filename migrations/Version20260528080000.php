<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial Fauna schema for MariaDB';
    }

    public function up(Schema $schema): void
    {
        // -----------------------------------------------------------------
        // fauna_country
        // -----------------------------------------------------------------

        $this->addSql(<<<'SQL'
CREATE TABLE fauna_country (
    id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
    name VARCHAR(100) NOT NULL,
    code VARCHAR(2) NOT NULL,
    PRIMARY KEY(id),
    UNIQUE INDEX UNIQ_FAUNA_COUNTRY_CODE (code)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        // -----------------------------------------------------------------
        // fauna_taxonomy
        // -----------------------------------------------------------------

        $this->addSql(<<<'SQL'
CREATE TABLE fauna_taxonomy (
    id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
    parent_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
    name VARCHAR(100) NOT NULL,
    type VARCHAR(10) NOT NULL,
    common VARCHAR(100) DEFAULT NULL,
    accepted TINYINT(1) NOT NULL DEFAULT 1,
    canonical_name VARCHAR(100) DEFAULT NULL,
    external_id INT DEFAULT NULL,
    PRIMARY KEY(id),
    UNIQUE INDEX UNIQ_FAUNA_TAXONOMY_EXTERNAL_ID (external_id),
    INDEX IDX_FAUNA_TAXONOMY_PARENT (parent_id),
    INDEX IDX_FAUNA_TAXONOMY_TYPE (type),
    INDEX IDX_FAUNA_TAXONOMY_NAME_TYPE (name, type),
    INDEX IDX_FAUNA_TAXONOMY_CANONICAL (canonical_name),
    INDEX IDX_FAUNA_TAXONOMY_PARENT_TYPE (parent_id, type),
    CONSTRAINT FK_FAUNA_TAXONOMY_PARENT
        FOREIGN KEY (parent_id)
        REFERENCES fauna_taxonomy (id)
        ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        // -----------------------------------------------------------------
        // fauna_species
        // -----------------------------------------------------------------

        $this->addSql(<<<'SQL'
CREATE TABLE fauna_species (
    id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
    genus_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
    name VARCHAR(255) NOT NULL,
    latin VARCHAR(255) NOT NULL,
    iucn VARCHAR(2) DEFAULT NULL,
    description LONGTEXT DEFAULT NULL,
    external_id INT DEFAULT NULL,
    date_added DATETIME NOT NULL,
    date_updated DATETIME NOT NULL,
    PRIMARY KEY(id),
    UNIQUE INDEX UNIQ_FAUNA_SPECIES_LATIN (latin),
    UNIQUE INDEX UNIQ_FAUNA_SPECIES_EXTERNAL_ID (external_id),
    INDEX IDX_FAUNA_SPECIES_GENUS (genus_id),
    INDEX IDX_FAUNA_SPECIES_LATIN (latin),
    INDEX IDX_FAUNA_SPECIES_EXTERNAL_ID (external_id),
    CONSTRAINT FK_FAUNA_SPECIES_GENUS
        FOREIGN KEY (genus_id)
        REFERENCES fauna_taxonomy (id)
        ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        // -----------------------------------------------------------------
        // fauna_species_to_country
        // -----------------------------------------------------------------

        $this->addSql(<<<'SQL'
CREATE TABLE fauna_species_to_country (
    species_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
    country_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
    PRIMARY KEY(species_id, country_id),
    INDEX IDX_SPECIES_COUNTRY_SPECIES (species_id),
    INDEX IDX_SPECIES_COUNTRY_COUNTRY (country_id),
    CONSTRAINT FK_SPECIES_COUNTRY_SPECIES
        FOREIGN KEY (species_id)
        REFERENCES fauna_species (id)
        ON DELETE CASCADE,
    CONSTRAINT FK_SPECIES_COUNTRY_COUNTRY
        FOREIGN KEY (country_id)
        REFERENCES fauna_country (id)
        ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        // -----------------------------------------------------------------
        // fauna_species_image
        // -----------------------------------------------------------------

        $this->addSql(<<<'SQL'
CREATE TABLE fauna_species_image (
    id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
    species_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
    url VARCHAR(512) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY(id),
    INDEX IDX_FAUNA_SPECIES_IMAGE_SPECIES (species_id),
    CONSTRAINT FK_FAUNA_SPECIES_IMAGE_SPECIES
        FOREIGN KEY (species_id)
        REFERENCES fauna_species (id)
        ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        // -----------------------------------------------------------------
        // fauna_trip
        // -----------------------------------------------------------------

        $this->addSql(<<<'SQL'
CREATE TABLE fauna_trip (
    id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
    user_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
    name VARCHAR(255) NOT NULL,
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY(id),
    INDEX IDX_FAUNA_TRIP_USER (user_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        // -----------------------------------------------------------------
        // fauna_sighting
        // -----------------------------------------------------------------

        $this->addSql(<<<'SQL'
CREATE TABLE fauna_sighting (
    id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
    user_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
    trip_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
    species_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
    country_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
    location VARCHAR(255) DEFAULT NULL,
    latitude DOUBLE DEFAULT NULL,
    longitude DOUBLE DEFAULT NULL,
    notes LONGTEXT DEFAULT NULL,
    count INT NOT NULL DEFAULT 1,
    photograph VARCHAR(512) DEFAULT NULL,
    date DATETIME NOT NULL,
    PRIMARY KEY(id),
    INDEX IDX_FAUNA_SIGHTING_USER (user_id),
    INDEX IDX_FAUNA_SIGHTING_TRIP (trip_id),
    INDEX IDX_FAUNA_SIGHTING_SPECIES (species_id),
    INDEX IDX_FAUNA_SIGHTING_COUNTRY (country_id),
    INDEX IDX_SIGHTING_DATE (date),
    INDEX IDX_SIGHTING_LOCATION (location),
    INDEX IDX_SIGHTING_COORDS (latitude, longitude)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        // -----------------------------------------------------------------
        // fauna_featured_species
        // -----------------------------------------------------------------

        $this->addSql(<<<'SQL'
CREATE TABLE fauna_featured_species (
    id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
    species_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
    title VARCHAR(255) NOT NULL,
    image_link VARCHAR(512) DEFAULT NULL,
    description LONGTEXT DEFAULT NULL,
    date_added DATETIME NOT NULL,
    schedule_start_date DATETIME DEFAULT NULL,
    schedule_end_date DATETIME DEFAULT NULL,
    is_live TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY(id),
    INDEX IDX_FAUNA_FEATURED_SPECIES_SPECIES (species_id),
    INDEX IDX_FEATURED_LIVE (is_live),
    INDEX IDX_FEATURED_SCHEDULE (schedule_start_date, schedule_end_date),
    CONSTRAINT FK_FAUNA_FEATURED_SPECIES_SPECIES
        FOREIGN KEY (species_id)
        REFERENCES fauna_species (id)
        ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        // -----------------------------------------------------------------
        // Foreign keys added after dependent tables exist
        // -----------------------------------------------------------------

        $this->addSql(<<<'SQL'
ALTER TABLE fauna_sighting
    ADD CONSTRAINT FK_FAUNA_SIGHTING_USER
        FOREIGN KEY (user_id)
        REFERENCES user (id)
        ON DELETE CASCADE
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE fauna_sighting
    ADD CONSTRAINT FK_FAUNA_SIGHTING_TRIP
        FOREIGN KEY (trip_id)
        REFERENCES fauna_trip (id)
        ON DELETE SET NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE fauna_sighting
    ADD CONSTRAINT FK_FAUNA_SIGHTING_SPECIES
        FOREIGN KEY (species_id)
        REFERENCES fauna_species (id)
        ON DELETE CASCADE
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE fauna_sighting
    ADD CONSTRAINT FK_FAUNA_SIGHTING_COUNTRY
        FOREIGN KEY (country_id)
        REFERENCES fauna_country (id)
        ON DELETE SET NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE fauna_trip
    ADD CONSTRAINT FK_FAUNA_TRIP_USER
        FOREIGN KEY (user_id)
        REFERENCES user (id)
        ON DELETE CASCADE
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS fauna_featured_species');
        $this->addSql('DROP TABLE IF EXISTS fauna_sighting');
        $this->addSql('DROP TABLE IF EXISTS fauna_species_image');
        $this->addSql('DROP TABLE IF EXISTS fauna_species_to_country');
        $this->addSql('DROP TABLE IF EXISTS fauna_species');
        $this->addSql('DROP TABLE IF EXISTS fauna_trip');
        $this->addSql('DROP TABLE IF EXISTS fauna_taxonomy');
        $this->addSql('DROP TABLE IF EXISTS fauna_country');
    }
}

