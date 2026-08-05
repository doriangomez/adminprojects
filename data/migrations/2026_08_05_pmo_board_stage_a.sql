-- =============================================================================
-- Etapa A — Tablero PMO (up)
-- =============================================================================
-- IMPORTANTE: este archivo NO es una migración SQL autónoma segura.
--
-- La ÚNICA vía admitida para aplicar la Etapa A es el ejecutor CLI idempotente:
--
--   php bin/migrate_pmo_board_stage_a.php
--
-- Ese comando:
--   - exige autorización explícita (no corre en el arranque HTTP),
--   - valida preflight,
--   - aplica DDL de forma idempotente,
--   - verifica permisos/FK/backfill,
--   - escribe el flag config_settings.pmo_board_stage_a solo al final.
--
-- No ejecute ALTER/CREATE manuales a partir de versiones anteriores de este
-- archivo. Si se ejecuta este script directamente, falla a propósito.
-- =============================================================================

SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Etapa A: use php bin/migrate_pmo_board_stage_a.php. Este SQL no aplica DDL.';
