-- =============================================================================
-- Etapa B — Organizaciones y tecnologías (up)
-- =============================================================================
-- Ejecución manual (MariaDB):
--   mysql adminprojects < data/migrations/2026_08_06_pmo_board_stage_b.sql
--
-- Idempotente vía CREATE TABLE IF NOT EXISTS.
-- Compatible con MariaDB. Sin conversión JSON forzada, sin bloqueo por error intencional, sin CLI, sin App.php.
-- Todas las FK usan ON DELETE RESTRICT.
-- =============================================================================

CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    name_normalized VARCHAR(180) NOT NULL,
    legal_name VARCHAR(220) NULL,
    tax_identifier VARCHAR(64) NULL,
    org_type ENUM('cliente','proveedor','aliado','area_interna','equipo') NOT NULL,
    description TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_organizations_active (active),
    INDEX idx_organizations_org_type (org_type),
    INDEX idx_organizations_name_normalized (name_normalized),
    INDEX idx_organizations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS technologies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    name_normalized VARCHAR(120) NOT NULL,
    category VARCHAR(80) NOT NULL,
    description TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_technologies_name_category (name_normalized, category),
    INDEX idx_technologies_active (active),
    INDEX idx_technologies_category (category),
    INDEX idx_technologies_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    organization_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_organizations (project_id, organization_id),
    INDEX idx_project_organizations_organization (organization_id),
    CONSTRAINT fk_project_organizations_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_project_organizations_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization_id INT NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_organizations (user_id, organization_id),
    INDEX idx_user_organizations_organization (organization_id),
    INDEX idx_user_organizations_user_primary (user_id, is_primary),
    CONSTRAINT fk_user_organizations_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_user_organizations_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_technologies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    technology_id INT NOT NULL,
    version VARCHAR(40) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_technologies (project_id, technology_id),
    INDEX idx_project_technologies_technology (technology_id),
    CONSTRAINT fk_project_technologies_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_project_technologies_technology
        FOREIGN KEY (technology_id) REFERENCES technologies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO config_settings (config_key, config_value, updated_at)
VALUES (
    'pmo_board_stage_b',
    '{"version":"stage_b_1","applied_at":"manual"}',
    NOW()
)
ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    updated_at = NOW();
