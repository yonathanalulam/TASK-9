<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414092601 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE boundary_imports (id BINARY(16) NOT NULL, file_name VARCHAR(255) NOT NULL, file_type VARCHAR(20) NOT NULL, file_size INT NOT NULL, file_hash VARCHAR(64) NOT NULL, storage_path VARCHAR(512) NOT NULL, status VARCHAR(20) DEFAULT \'UPLOADED\' NOT NULL, failure_reason LONGTEXT DEFAULT NULL, validation_errors JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, applied_at DATETIME DEFAULT NULL, uploaded_by BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_A37883A558440AD6 (file_hash), INDEX idx_boundary_imports_status (status), INDEX idx_boundary_imports_uploaded_by (uploaded_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mutation_queue_log (id BINARY(16) NOT NULL, client_id VARCHAR(36) NOT NULL, mutation_id VARCHAR(36) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id VARCHAR(36) DEFAULT NULL, operation VARCHAR(10) NOT NULL, payload JSON NOT NULL, status VARCHAR(20) DEFAULT \'RECEIVED\' NOT NULL, conflict_detail LONGTEXT DEFAULT NULL, received_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_4AE615BA3FEEC0D7 (mutation_id), INDEX idx_mutation_queue_log_client_id (client_id), INDEX idx_mutation_queue_log_status (status), INDEX idx_mutation_queue_log_entity (entity_type, entity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE boundary_imports ADD CONSTRAINT FK_A37883A5E3E73126 FOREIGN KEY (uploaded_by) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE boundary_imports DROP FOREIGN KEY FK_A37883A5E3E73126');
        $this->addSql('DROP TABLE boundary_imports');
        $this->addSql('DROP TABLE mutation_queue_log');
    }
}
