-- =============================================================================
-- Etapa B — Organizaciones y tecnologías (down)
-- =============================================================================
-- ADVERTENCIA:
--   Este script ELIMINA datos de catálogos y asociaciones de la Etapa B:
--   project_technologies, project_organizations, user_organizations,
--   technologies, organizations y el flag pmo_board_stage_b.
--   Requiere autorización explícita y respaldo previo.
--   NUNCA se ejecuta automáticamente desde la aplicación.
--   No toca permisos de Etapa A ni schedule_activity_organizations (aplazado).
-- =============================================================================

DELETE FROM config_settings WHERE config_key = 'pmo_board_stage_b';

DROP TABLE IF EXISTS project_technologies;
DROP TABLE IF EXISTS project_organizations;
DROP TABLE IF EXISTS user_organizations;
DROP TABLE IF EXISTS technologies;
DROP TABLE IF EXISTS organizations;
