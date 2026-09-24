<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924104307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tracks ADD COLUMN recorded_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__tracks AS SELECT id, name, "key", filename, total_points, elevation, distance, created_at, user_id FROM tracks');
        $this->addSql('DROP TABLE tracks');
        $this->addSql('CREATE TABLE tracks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, "key" VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, total_points INTEGER DEFAULT NULL, elevation DOUBLE PRECISION DEFAULT NULL, distance DOUBLE PRECISION DEFAULT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_246D2A2EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tracks (id, name, "key", filename, total_points, elevation, distance, created_at, user_id) SELECT id, name, "key", filename, total_points, elevation, distance, created_at, user_id FROM __temp__tracks');
        $this->addSql('DROP TABLE __temp__tracks');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_246D2A2E8A90ABA9 ON tracks ("key")');
        $this->addSql('CREATE INDEX IDX_246D2A2EA76ED395 ON tracks (user_id)');
    }
}
