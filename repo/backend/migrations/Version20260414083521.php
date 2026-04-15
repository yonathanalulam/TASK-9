<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414083521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE administrative_areas (id BINARY(16) NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL, area_type VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, region_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_861FFAF477153098 (code), INDEX IDX_861FFAF498260155 (region_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE app_sessions (id BINARY(16) NOT NULL, token_hash VARCHAR(128) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, revocation_reason VARCHAR(50) DEFAULT NULL, user_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_92E0FDBEB3BC57DA (token_hash), INDEX IDX_92E0FDBEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE audit_event_hashes (id BINARY(16) NOT NULL, sequence_number BIGINT NOT NULL, previous_hash VARCHAR(128) DEFAULT NULL, event_hash VARCHAR(128) NOT NULL, chain_hash VARCHAR(128) NOT NULL, computed_at DATETIME NOT NULL, audit_event_id BINARY(16) NOT NULL, UNIQUE INDEX uq_audit_event_hash_event (audit_event_id), UNIQUE INDEX uq_audit_event_hash_sequence (sequence_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE audit_events (id BINARY(16) NOT NULL, sequence_number BIGINT NOT NULL, actor_id VARBINARY(16) DEFAULT NULL, actor_username VARCHAR(80) DEFAULT NULL, action VARCHAR(50) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_id VARBINARY(16) NOT NULL, old_values JSON DEFAULT NULL, new_values JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, occurred_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_70597B91F2803B3D (sequence_number), INDEX idx_audit_events_entity (entity_type, entity_id), INDEX idx_audit_events_actor_id (actor_id), INDEX idx_audit_events_occurred_at (occurred_at), INDEX idx_audit_events_action (action), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE boundary_files (id BINARY(16) NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id VARBINARY(16) NOT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, file_hash VARCHAR(128) NOT NULL, uploaded_at DATETIME NOT NULL, uploaded_by BINARY(16) NOT NULL, INDEX IDX_129E496CE3E73126 (uploaded_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE community_grids (id BINARY(16) NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, region_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_D8ED309977153098 (code), INDEX IDX_D8ED309998260155 (region_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE delivery_windows (id BINARY(16) NOT NULL, day_of_week SMALLINT NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, zone_id BINARY(16) NOT NULL, INDEX idx_delivery_windows_zone_id (zone_id), INDEX idx_delivery_windows_zone_day (zone_id, day_of_week), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE delivery_zone_versions (id BINARY(16) NOT NULL, version_number INT NOT NULL, change_type VARCHAR(20) NOT NULL, snapshot JSON NOT NULL, changed_at DATETIME NOT NULL, change_reason VARCHAR(500) DEFAULT NULL, zone_id BINARY(16) NOT NULL, changed_by BINARY(16) NOT NULL, INDEX IDX_36928BAF9F2C3FAB (zone_id), INDEX IDX_36928BAF10BC6D9F (changed_by), UNIQUE INDEX uq_delivery_zone_version (zone_id, version_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE delivery_zones (id BINARY(16) NOT NULL, name VARCHAR(120) NOT NULL, status VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL, min_order_threshold NUMERIC(10, 2) DEFAULT \'25.00\' NOT NULL, delivery_fee NUMERIC(10, 2) DEFAULT \'3.99\' NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, version INT DEFAULT 1 NOT NULL, store_id BINARY(16) NOT NULL, INDEX idx_delivery_zones_store_id (store_id), INDEX idx_delivery_zones_is_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE field_access_policies (id BINARY(16) NOT NULL, entity_type VARCHAR(80) NOT NULL, field_name VARCHAR(80) NOT NULL, can_read TINYINT DEFAULT 1 NOT NULL, can_write TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, role_id BINARY(16) NOT NULL, INDEX IDX_B18FB1D6D60322AC (role_id), UNIQUE INDEX UNIQ_B18FB1D6D60322ACC412EE024DEF17BC (role_id, entity_type, field_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mdm_region_versions (id BINARY(16) NOT NULL, version_number INT NOT NULL, change_type VARCHAR(20) NOT NULL, snapshot JSON NOT NULL, changed_at DATETIME NOT NULL, change_reason VARCHAR(500) DEFAULT NULL, region_id BINARY(16) NOT NULL, changed_by BINARY(16) NOT NULL, INDEX IDX_F0ECC0BF98260155 (region_id), INDEX IDX_F0ECC0BF10BC6D9F (changed_by), UNIQUE INDEX uq_mdm_region_version (region_id, version_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mdm_regions (id BINARY(16) NOT NULL, code VARCHAR(5) NOT NULL, name VARCHAR(150) NOT NULL, hierarchy_level INT DEFAULT 0 NOT NULL, effective_from DATE NOT NULL, effective_until DATE DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, version INT DEFAULT 1 NOT NULL, parent_id BINARY(16) DEFAULT NULL, UNIQUE INDEX UNIQ_36EAEFCD77153098 (code), INDEX idx_mdm_regions_parent_id (parent_id), INDEX idx_mdm_regions_is_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE roles (id BINARY(16) NOT NULL, name VARCHAR(50) NOT NULL, display_name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, is_system TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_B63E2EC75E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE store_versions (id BINARY(16) NOT NULL, version_number INT NOT NULL, change_type VARCHAR(20) NOT NULL, snapshot JSON NOT NULL, changed_at DATETIME NOT NULL, change_reason VARCHAR(500) DEFAULT NULL, store_id BINARY(16) NOT NULL, changed_by BINARY(16) NOT NULL, INDEX IDX_27AB3F28B092A811 (store_id), INDEX IDX_27AB3F2810BC6D9F (changed_by), UNIQUE INDEX uq_store_version (store_id, version_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stores (id BINARY(16) NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL, store_type VARCHAR(20) NOT NULL, status VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL, timezone VARCHAR(50) DEFAULT \'UTC\' NOT NULL, address_line1 VARCHAR(255) DEFAULT NULL, address_line2 VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, version INT DEFAULT 1 NOT NULL, region_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_D5907CCC77153098 (code), INDEX idx_stores_region_id (region_id), INDEX idx_stores_store_type (store_type), INDEX idx_stores_is_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_role_assignments (id BINARY(16) NOT NULL, scope_type VARCHAR(20) NOT NULL, scope_id VARBINARY(16) DEFAULT NULL, effective_from DATE NOT NULL, effective_until DATE DEFAULT NULL, granted_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, user_id BINARY(16) NOT NULL, role_id BINARY(16) NOT NULL, granted_by_id BINARY(16) NOT NULL, revoked_by_id BINARY(16) DEFAULT NULL, INDEX IDX_7FC84A893151C11F (granted_by_id), INDEX IDX_7FC84A89FB8FE773 (revoked_by_id), INDEX IDX_7FC84A89A76ED395 (user_id), INDEX IDX_7FC84A89D60322AC (role_id), INDEX IDX_7FC84A893159C776682B5931 (scope_type, scope_id), INDEX IDX_7FC84A893C98A526DA75346 (effective_from, effective_until), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id BINARY(16) NOT NULL, username VARCHAR(80) NOT NULL, display_name VARCHAR(150) NOT NULL, password_hash VARCHAR(255) NOT NULL, status VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL, failed_login_attempts INT DEFAULT 0 NOT NULL, locked_until DATETIME DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, password_changed_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, version INT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE zone_mappings (id BINARY(16) NOT NULL, mapping_type VARCHAR(30) NOT NULL, mapped_entity_id VARBINARY(16) NOT NULL, precedence INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, zone_id BINARY(16) NOT NULL, INDEX IDX_3E96CF849F2C3FAB (zone_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE zone_order_rules (id BINARY(16) NOT NULL, rule_type VARCHAR(50) NOT NULL, rule_config JSON NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, zone_id BINARY(16) NOT NULL, INDEX IDX_AA2EEEAA9F2C3FAB (zone_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE zone_product_rules (id BINARY(16) NOT NULL, rule_type VARCHAR(50) NOT NULL, rule_config JSON NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, zone_id BINARY(16) NOT NULL, INDEX IDX_37C6A19C9F2C3FAB (zone_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE administrative_areas ADD CONSTRAINT FK_861FFAF498260155 FOREIGN KEY (region_id) REFERENCES mdm_regions (id)');
        $this->addSql('ALTER TABLE app_sessions ADD CONSTRAINT FK_92E0FDBEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE audit_event_hashes ADD CONSTRAINT FK_AD2E374F21E3BB62 FOREIGN KEY (audit_event_id) REFERENCES audit_events (id)');
        $this->addSql('ALTER TABLE boundary_files ADD CONSTRAINT FK_129E496CE3E73126 FOREIGN KEY (uploaded_by) REFERENCES users (id)');
        $this->addSql('ALTER TABLE community_grids ADD CONSTRAINT FK_D8ED309998260155 FOREIGN KEY (region_id) REFERENCES mdm_regions (id)');
        $this->addSql('ALTER TABLE delivery_windows ADD CONSTRAINT FK_E9F93A459F2C3FAB FOREIGN KEY (zone_id) REFERENCES delivery_zones (id)');
        $this->addSql('ALTER TABLE delivery_zone_versions ADD CONSTRAINT FK_36928BAF9F2C3FAB FOREIGN KEY (zone_id) REFERENCES delivery_zones (id)');
        $this->addSql('ALTER TABLE delivery_zone_versions ADD CONSTRAINT FK_36928BAF10BC6D9F FOREIGN KEY (changed_by) REFERENCES users (id)');
        $this->addSql('ALTER TABLE delivery_zones ADD CONSTRAINT FK_95C157FAB092A811 FOREIGN KEY (store_id) REFERENCES stores (id)');
        $this->addSql('ALTER TABLE field_access_policies ADD CONSTRAINT FK_B18FB1D6D60322AC FOREIGN KEY (role_id) REFERENCES roles (id)');
        $this->addSql('ALTER TABLE mdm_region_versions ADD CONSTRAINT FK_F0ECC0BF98260155 FOREIGN KEY (region_id) REFERENCES mdm_regions (id)');
        $this->addSql('ALTER TABLE mdm_region_versions ADD CONSTRAINT FK_F0ECC0BF10BC6D9F FOREIGN KEY (changed_by) REFERENCES users (id)');
        $this->addSql('ALTER TABLE mdm_regions ADD CONSTRAINT FK_36EAEFCD727ACA70 FOREIGN KEY (parent_id) REFERENCES mdm_regions (id)');
        $this->addSql('ALTER TABLE store_versions ADD CONSTRAINT FK_27AB3F28B092A811 FOREIGN KEY (store_id) REFERENCES stores (id)');
        $this->addSql('ALTER TABLE store_versions ADD CONSTRAINT FK_27AB3F2810BC6D9F FOREIGN KEY (changed_by) REFERENCES users (id)');
        $this->addSql('ALTER TABLE stores ADD CONSTRAINT FK_D5907CCC98260155 FOREIGN KEY (region_id) REFERENCES mdm_regions (id)');
        $this->addSql('ALTER TABLE user_role_assignments ADD CONSTRAINT FK_7FC84A89A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE user_role_assignments ADD CONSTRAINT FK_7FC84A89D60322AC FOREIGN KEY (role_id) REFERENCES roles (id)');
        $this->addSql('ALTER TABLE user_role_assignments ADD CONSTRAINT FK_7FC84A893151C11F FOREIGN KEY (granted_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE user_role_assignments ADD CONSTRAINT FK_7FC84A89FB8FE773 FOREIGN KEY (revoked_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE zone_mappings ADD CONSTRAINT FK_3E96CF849F2C3FAB FOREIGN KEY (zone_id) REFERENCES delivery_zones (id)');
        $this->addSql('ALTER TABLE zone_order_rules ADD CONSTRAINT FK_AA2EEEAA9F2C3FAB FOREIGN KEY (zone_id) REFERENCES delivery_zones (id)');
        $this->addSql('ALTER TABLE zone_product_rules ADD CONSTRAINT FK_37C6A19C9F2C3FAB FOREIGN KEY (zone_id) REFERENCES delivery_zones (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE administrative_areas DROP FOREIGN KEY FK_861FFAF498260155');
        $this->addSql('ALTER TABLE app_sessions DROP FOREIGN KEY FK_92E0FDBEA76ED395');
        $this->addSql('ALTER TABLE audit_event_hashes DROP FOREIGN KEY FK_AD2E374F21E3BB62');
        $this->addSql('ALTER TABLE boundary_files DROP FOREIGN KEY FK_129E496CE3E73126');
        $this->addSql('ALTER TABLE community_grids DROP FOREIGN KEY FK_D8ED309998260155');
        $this->addSql('ALTER TABLE delivery_windows DROP FOREIGN KEY FK_E9F93A459F2C3FAB');
        $this->addSql('ALTER TABLE delivery_zone_versions DROP FOREIGN KEY FK_36928BAF9F2C3FAB');
        $this->addSql('ALTER TABLE delivery_zone_versions DROP FOREIGN KEY FK_36928BAF10BC6D9F');
        $this->addSql('ALTER TABLE delivery_zones DROP FOREIGN KEY FK_95C157FAB092A811');
        $this->addSql('ALTER TABLE field_access_policies DROP FOREIGN KEY FK_B18FB1D6D60322AC');
        $this->addSql('ALTER TABLE mdm_region_versions DROP FOREIGN KEY FK_F0ECC0BF98260155');
        $this->addSql('ALTER TABLE mdm_region_versions DROP FOREIGN KEY FK_F0ECC0BF10BC6D9F');
        $this->addSql('ALTER TABLE mdm_regions DROP FOREIGN KEY FK_36EAEFCD727ACA70');
        $this->addSql('ALTER TABLE store_versions DROP FOREIGN KEY FK_27AB3F28B092A811');
        $this->addSql('ALTER TABLE store_versions DROP FOREIGN KEY FK_27AB3F2810BC6D9F');
        $this->addSql('ALTER TABLE stores DROP FOREIGN KEY FK_D5907CCC98260155');
        $this->addSql('ALTER TABLE user_role_assignments DROP FOREIGN KEY FK_7FC84A89A76ED395');
        $this->addSql('ALTER TABLE user_role_assignments DROP FOREIGN KEY FK_7FC84A89D60322AC');
        $this->addSql('ALTER TABLE user_role_assignments DROP FOREIGN KEY FK_7FC84A893151C11F');
        $this->addSql('ALTER TABLE user_role_assignments DROP FOREIGN KEY FK_7FC84A89FB8FE773');
        $this->addSql('ALTER TABLE zone_mappings DROP FOREIGN KEY FK_3E96CF849F2C3FAB');
        $this->addSql('ALTER TABLE zone_order_rules DROP FOREIGN KEY FK_AA2EEEAA9F2C3FAB');
        $this->addSql('ALTER TABLE zone_product_rules DROP FOREIGN KEY FK_37C6A19C9F2C3FAB');
        $this->addSql('DROP TABLE administrative_areas');
        $this->addSql('DROP TABLE app_sessions');
        $this->addSql('DROP TABLE audit_event_hashes');
        $this->addSql('DROP TABLE audit_events');
        $this->addSql('DROP TABLE boundary_files');
        $this->addSql('DROP TABLE community_grids');
        $this->addSql('DROP TABLE delivery_windows');
        $this->addSql('DROP TABLE delivery_zone_versions');
        $this->addSql('DROP TABLE delivery_zones');
        $this->addSql('DROP TABLE field_access_policies');
        $this->addSql('DROP TABLE mdm_region_versions');
        $this->addSql('DROP TABLE mdm_regions');
        $this->addSql('DROP TABLE roles');
        $this->addSql('DROP TABLE store_versions');
        $this->addSql('DROP TABLE stores');
        $this->addSql('DROP TABLE user_role_assignments');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE zone_mappings');
        $this->addSql('DROP TABLE zone_order_rules');
        $this->addSql('DROP TABLE zone_product_rules');
    }
}
