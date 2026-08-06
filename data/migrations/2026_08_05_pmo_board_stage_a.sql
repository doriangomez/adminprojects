-- =============================================================================
-- Etapa A — Tablero PMO (up)
-- =============================================================================
-- Ejecución manual:
--   mysql adminprojects < data/migrations/2026_08_05_pmo_board_stage_a.sql
--
-- Aplica:
--   - projects.description
--   - project_pmo_settings + backfill por proyecto
--   - columnas / índices / FK PMO en project_schedule_activities
--   - 12 permisos pmo.* + grants por rol
--   - flag config_settings.pmo_board_stage_a
-- =============================================================================

-- 1) projects.description
ALTER TABLE projects
  ADD COLUMN description TEXT NULL AFTER name;

-- 2) Config PMO por proyecto
CREATE TABLE IF NOT EXISTS project_pmo_settings (
    project_id INT NOT NULL,
    board_title VARCHAR(180) NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/Bogota',
    target_date DATE NULL,
    deviation_threshold_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    enabled_indicators_json JSON NOT NULL,
    applicable_statuses_json JSON NOT NULL,
    critical_path_mode ENUM('auto','manual_override','hybrid') NOT NULL DEFAULT 'hybrid',
    velocity_window_days INT NOT NULL DEFAULT 5,
    work_week_mask CHAR(7) NOT NULL DEFAULT '0111110',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id),
    CONSTRAINT fk_project_pmo_settings_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill settings por proyecto (solo faltantes)
INSERT INTO project_pmo_settings (
    project_id, board_title, timezone, target_date, deviation_threshold_pct,
    enabled_indicators_json, applicable_statuses_json, critical_path_mode,
    velocity_window_days, work_week_mask, created_at, updated_at
)
SELECT
    p.id,
    NULL,
    'America/Bogota',
    NULL,
    5.00,
    '["avance_real","avance_planificado","desviacion_vs_plan","adelantadas","a_tiempo","atrasadas","hitos_totales","hitos_cumplidos","hitos_vencidos","ruta_critica","stoppers_abiertos","actividades_bloqueadas","riesgos_por_severidad","cambios_alcance_pendientes","dias_restantes","avance_por_frente","avance_por_organizacion","avance_por_responsable","curva_s","evolucion_diaria","salud_general"]',
    '["todo","in_progress","review","blocked","done","cancelled"]',
    'hybrid',
    5,
    '0111110',
    NOW(),
    NOW()
FROM projects p
WHERE NOT EXISTS (
    SELECT 1 FROM project_pmo_settings s WHERE s.project_id = p.id
);

-- 3) Columnas PMO en cronograma
ALTER TABLE project_schedule_activities
  ADD COLUMN code VARCHAR(40) NULL,
  ADD COLUMN phase_code VARCHAR(80) NULL,
  ADD COLUMN front_label VARCHAR(120) NULL,
  ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'todo',
  ADD COLUMN is_critical_auto TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN is_critical_manual TINYINT(1) NULL,
  ADD COLUMN critical_manual_by INT NULL,
  ADD COLUMN critical_manual_at DATETIME NULL,
  ADD COLUMN critical_manual_reason VARCHAR(255) NULL,
  ADD COLUMN responsible_user_id INT NULL;

ALTER TABLE project_schedule_activities
  ADD UNIQUE KEY uq_project_schedule_activities_project_code (project_id, code),
  ADD UNIQUE KEY uq_project_schedule_activities_id_project (id, project_id),
  ADD INDEX idx_project_schedule_activities_project_status (project_id, status);

ALTER TABLE project_schedule_activities
  ADD CONSTRAINT fk_project_schedule_activities_critical_manual_by
    FOREIGN KEY (critical_manual_by) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_project_schedule_activities_responsible_user
    FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- 4) 12 permisos
INSERT INTO permissions (code, name)
SELECT v.code, v.name
FROM (
    SELECT 'pmo.board.view' AS code, 'Ver tablero PMO de proyecto' AS name UNION ALL
    SELECT 'pmo.board.update_progress', 'Actualizar avance/estado PMO' UNION ALL
    SELECT 'pmo.portfolio.view', 'Ver portafolio PMO' UNION ALL
    SELECT 'pmo.settings.manage', 'Gestionar configuración PMO del proyecto' UNION ALL
    SELECT 'pmo.critical_path.manage', 'Gestionar ruta crítica (override)' UNION ALL
    SELECT 'pmo.raci.manage', 'Gestionar matriz RACI' UNION ALL
    SELECT 'pmo.scope_changes.view', 'Ver cambios de alcance' UNION ALL
    SELECT 'pmo.scope_changes.manage', 'Crear/editar cambios de alcance' UNION ALL
    SELECT 'pmo.scope_changes.approve', 'Aprobar/rechazar cambios de alcance' UNION ALL
    SELECT 'pmo.organizations.manage', 'Gestionar organizaciones' UNION ALL
    SELECT 'pmo.technologies.manage', 'Gestionar catálogo de tecnologías' UNION ALL
    SELECT 'pmo.access_log.view', 'Ver registro de accesos'
) v
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.code = v.code);

-- Grants: Administrador + PMO = todos
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.nombre IN ('Administrador', 'PMO')
  AND p.code LIKE 'pmo.%'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

-- Grants: Líder de Proyecto
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'pmo.board.view',
    'pmo.board.update_progress',
    'pmo.portfolio.view',
    'pmo.scope_changes.view',
    'pmo.scope_changes.manage'
)
WHERE r.nombre = 'Líder de Proyecto'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

-- Grants: Visualizador
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'pmo.board.view',
    'pmo.portfolio.view',
    'pmo.scope_changes.view'
)
WHERE r.nombre = 'Visualizador'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

-- 5) Flag de versión
INSERT INTO config_settings (config_key, config_value, updated_at)
VALUES (
    'pmo_board_stage_a',
    JSON_OBJECT('version', 'stage_a_1', 'applied_at', DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-%dT%H:%i:%s+00:00')),
    NOW()
)
ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    updated_at = NOW();
