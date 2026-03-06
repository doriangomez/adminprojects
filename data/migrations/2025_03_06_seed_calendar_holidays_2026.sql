-- Festivos de ejemplo para 2026 (Colombia / España)
-- Ejecutar manualmente o integrar en el seeder si se desea

INSERT INTO calendar_holidays (holiday_date, name) VALUES
('2026-01-01', 'Año Nuevo'),
('2026-01-06', 'Reyes'),
('2026-03-19', 'San José')
ON DUPLICATE KEY UPDATE name = VALUES(name);
