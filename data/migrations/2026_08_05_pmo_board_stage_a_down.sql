-- =============================================================================
-- Etapa A — Tablero PMO (down)
-- =============================================================================
-- ADVERTENCIA:
--   - Este script elimina configuración PMO (project_pmo_settings), columnas e
--     índices PMO de project_schedule_activities, projects.description,
--     permisos pmo.* de la Etapa A y el flag pmo_board_stage_a.
--   - NO debe utilizarse después de que etapas posteriores (B–J) dependan de
--     estos datos, salvo que exista un respaldo y autorización específica.
--   - Requiere autorización explícita.
--   - NUNCA se ejecuta automáticamente desde la aplicación.
--   - No toca tasks, stoppers, project_pmo_snapshots ni audit_log históricos.
-- =============================================================================

-- Flag
DELETE FROM config_settings WHERE config_key = 'pmo_board_stage_a';

-- Permisos (solo códigos creados por la Etapa A)
DELETE rp FROM role_permissions rp
INNER JOIN permissions p ON p.id = rp.permission_id
WHERE p.code IN (
    'pmo.board.view',
    'pmo.board.update_progress',
    'pmo.portfolio.view',
    'pmo.settings.manage',
    'pmo.critical_path.manage',
    'pmo.raci.manage',
    'pmo.scope_changes.view',
    'pmo.scope_changes.manage',
    'pmo.scope_changes.approve',
    'pmo.organizations.manage',
    'pmo.technologies.manage',
    'pmo.access_log.view'
);

DELETE FROM permissions
WHERE code IN (
    'pmo.board.view',
    'pmo.board.update_progress',
    'pmo.portfolio.view',
    'pmo.settings.manage',
    'pmo.critical_path.manage',
    'pmo.raci.manage',
    'pmo.scope_changes.view',
    'pmo.scope_changes.manage',
    'pmo.scope_changes.approve',
    'pmo.organizations.manage',
    'pmo.technologies.manage',
    'pmo.access_log.view'
);

-- FK del cronograma (eliminación segura por nombre vía information_schema)
SET @fk_critical := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'project_schedule_activities'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
      AND CONSTRAINT_NAME = 'fk_project_schedule_activities_critical_manual_by'
    LIMIT 1
);
SET @sql_critical := IF(
    @fk_critical IS NULL,
    'SELECT 1',
    'ALTER TABLE project_schedule_activities DROP FOREIGN KEY fk_project_schedule_activities_critical_manual_by'
);
PREPARE stmt_critical FROM @sql_critical;
EXECUTE stmt_critical;
DEALLOCATE PREPARE stmt_critical;

SET @fk_responsible := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'project_schedule_activities'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
      AND CONSTRAINT_NAME = 'fk_project_schedule_activities_responsible_user'
    LIMIT 1
);
SET @sql_responsible := IF(
    @fk_responsible IS NULL,
    'SELECT 1',
    'ALTER TABLE project_schedule_activities DROP FOREIGN KEY fk_project_schedule_activities_responsible_user'
);
PREPARE stmt_responsible FROM @sql_responsible;
EXECUTE stmt_responsible;
DEALLOCATE PREPARE stmt_responsible;

-- Índices Stage A (si existen)
SET @idx_code := (
    SELECT INDEX_NAME FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'project_schedule_activities'
      AND INDEX_NAME = 'uq_project_schedule_activities_project_code'
    LIMIT 1
);
SET @sql_idx_code := IF(
    @idx_code IS NULL,
    'SELECT 1',
    'ALTER TABLE project_schedule_activities DROP INDEX uq_project_schedule_activities_project_code'
);
PREPARE stmt_idx_code FROM @sql_idx_code;
EXECUTE stmt_idx_code;
DEALLOCATE PREPARE stmt_idx_code;

SET @idx_id_project := (
    SELECT INDEX_NAME FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'project_schedule_activities'
      AND INDEX_NAME = 'uq_project_schedule_activities_id_project'
    LIMIT 1
);
SET @sql_idx_id_project := IF(
    @idx_id_project IS NULL,
    'SELECT 1',
    'ALTER TABLE project_schedule_activities DROP INDEX uq_project_schedule_activities_id_project'
);
PREPARE stmt_idx_id_project FROM @sql_idx_id_project;
EXECUTE stmt_idx_id_project;
DEALLOCATE PREPARE stmt_idx_id_project;

SET @idx_status := (
    SELECT INDEX_NAME FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'project_schedule_activities'
      AND INDEX_NAME = 'idx_project_schedule_activities_project_status'
    LIMIT 1
);
SET @sql_idx_status := IF(
    @idx_status IS NULL,
    'SELECT 1',
    'ALTER TABLE project_schedule_activities DROP INDEX idx_project_schedule_activities_project_status'
);
PREPARE stmt_idx_status FROM @sql_idx_status;
EXECUTE stmt_idx_status;
DEALLOCATE PREPARE stmt_idx_status;

-- Columnas Stage A (drop condicional por columna)
DROP PROCEDURE IF EXISTS pmo_board_stage_a_drop_column;
DELIMITER //
CREATE PROCEDURE pmo_board_stage_a_drop_column(IN p_table VARCHAR(64), IN p_column VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL pmo_board_stage_a_drop_column('project_schedule_activities', 'code');
CALL pmo_board_stage_a_drop_column('project_schedule_activities', 'phase_code');
CALL pmo_board_stage_a_drop_column('project_schedule_activities', 'front_label');
CALL pmo_board_stage_a_drop_column('project_schedule_activities', 'status');
CALL pmo_board_stage_a_drop_column('project_schedule_activities', 'is_critical_auto');
CALL pmo_board_stage_a_drop_column('project_schedule_activities', 'is_critical_manual');
CALL pmo_board_stage_a_drop_column('project_schedule_activities', 'critical_manual_by');
CALL pmo_board_stage_a_drop_column('project_schedule_activities', 'critical_manual_at');
CALL pmo_board_stage_a_drop_column('project_schedule_activities', 'critical_manual_reason');
CALL pmo_board_stage_a_drop_column('project_schedule_activities', 'responsible_user_id');
CALL pmo_board_stage_a_drop_column('projects', 'description');

DROP PROCEDURE IF EXISTS pmo_board_stage_a_drop_column;

-- Settings PMO
DROP TABLE IF EXISTS project_pmo_settings;
