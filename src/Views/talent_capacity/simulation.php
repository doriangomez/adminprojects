<?php
$basePath = $basePath ?? '';
$filters = is_array($filters ?? null) ? $filters : [];
$data = is_array($data ?? null) ? $data : [];
$simulation = is_array($simulation ?? null) ? $simulation : null;

$talents = $data['talents'] ?? [];
$range = $data['range'] ?? ['start' => date('Y-m-01'), 'end' => date('Y-m-t')];
$totalCapacity = (float) ($data['total_capacity'] ?? 0);
$committedCapacity = (float) ($data['committed_capacity'] ?? 0);
$availableCapacity = (float) ($data['available_capacity'] ?? 0);

$monthNames = ['01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'];
$periodLabel = '';
if (!empty($range['start']) && !empty($range['end'])) {
    $startM = substr($range['start'], 5, 2);
    $endM = substr($range['end'], 5, 2);
    $periodLabel = ($monthNames[$startM] ?? $startM) . ' - ' . ($monthNames[$endM] ?? $endM);
}

$projectName = $simulation ? ($simulation['project_name'] ?? '') : ($_POST['project_name'] ?? '');
$estimatedHours = $simulation ? ($simulation['estimated_hours'] ?? 0) : ($_POST['estimated_hours'] ?? 200);
$periodFrom = $filters['date_from'] ?? $range['start'] ?? date('Y-m-01');
$periodTo = $filters['date_to'] ?? $range['end'] ?? date('Y-m-t');

$activeTab = 'simulacion';
require __DIR__ . '/_tabs.php';
?>

<section class="simulation-shell">
    <header class="simulation-header">
        <div>
            <p class="eyebrow">Simulación</p>
            <h2>Simulación de Capacidad</h2>
            <small class="section-muted">Simula el impacto de nuevos proyectos en la carga del equipo antes de asignarlos. No modifica datos reales.</small>
        </div>
        <span class="badge neutral">Solo lectura</span>
    </header>

    <section class="simulation-form-card">
        <h3>Proyecto a simular</h3>
        <form method="POST" action="<?= $basePath ?>/talent-capacity/simulation" class="simulation-form">
            <div class="form-row">
                <label>
                    Nombre del proyecto
                    <input type="text" name="project_name" value="<?= htmlspecialchars($projectName) ?>" placeholder="Ej: Proyecto Alpha">
                </label>
                <label>
                    Horas estimadas
                    <input type="number" name="estimated_hours" value="<?= htmlspecialchars((string) $estimatedHours) ?>" min="0" step="0.5" required>
                </label>
                <label>
                    Periodo (desde)
                    <input type="date" name="period_from" value="<?= htmlspecialchars($periodFrom) ?>">
                </label>
                <label>
                    Periodo (hasta)
                    <input type="date" name="period_to" value="<?= htmlspecialchars($periodTo) ?>">
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="action-btn primary">Ejecutar simulación</button>
            </div>
        </form>
    </section>

    <?php if ($simulation): ?>
        <section class="simulation-indicators">
            <h3>Indicadores de capacidad</h3>
            <div class="indicator-grid">
                <article class="indicator-card">
                    <strong><?= number_format((float) ($simulation['total_capacity'] ?? 0), 1) ?>h</strong>
                    <span>Capacidad total equipo</span>
                </article>
                <article class="indicator-card">
                    <strong><?= number_format((float) ($simulation['committed_capacity'] ?? 0), 1) ?>h</strong>
                    <span>Capacidad comprometida</span>
                </article>
                <article class="indicator-card">
                    <strong><?= number_format((float) ($simulation['available_capacity'] ?? 0), 1) ?>h</strong>
                    <span>Capacidad disponible (actual)</span>
                </article>
                <article class="indicator-card highlight">
                    <strong><?= number_format((float) ($simulation['simulated_available'] ?? 0), 1) ?>h</strong>
                    <span>Capacidad restante (simulada)</span>
                </article>
            </div>
        </section>

        <section class="simulation-impact">
            <h3>Impacto en el equipo</h3>
            <div class="table-wrap">
                <table class="impact-table">
                    <thead>
                        <tr>
                            <th>Talento</th>
                            <th>Actual</th>
                            <th>Simulado</th>
                            <th>Utilización final</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($simulation['results'] ?? []) as $row): ?>
                            <?php $level = (string) ($row['level'] ?? 'green'); ?>
                            <tr class="util-<?= htmlspecialchars($level) ?>">
                                <td><strong><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></strong></td>
                                <td><?= number_format((float) ($row['hours_actual'] ?? 0), 1) ?>h</td>
                                <td><?= number_format((float) ($row['hours_simulated'] ?? 0), 1) ?>h</td>
                                <td>
                                    <span class="util-badge util-<?= htmlspecialchars($level) ?>"><?= number_format((float) ($row['utilization'] ?? 0), 1) ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="legend">
                <span class="legend-item"><i class="dot util-green"></i>Verde &lt;70%</span>
                <span class="legend-item"><i class="dot util-yellow"></i>Amarillo 70-90%</span>
                <span class="legend-item"><i class="dot util-red"></i>Rojo &gt;90%</span>
            </div>
        </section>

        <section class="simulation-insights">
            <h3>Insights del sistema</h3>
            <ul>
                <?php foreach (($simulation['insights'] ?? []) as $insight): ?>
                    <li><?= htmlspecialchars((string) $insight) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php else: ?>
        <section class="simulation-placeholder">
            <p class="section-muted">Ingresa los datos del proyecto y haz clic en <strong>Ejecutar simulación</strong> para ver el impacto en la carga del equipo.</p>
            <?php if (!empty($talents)): ?>
                <div class="preview-stats">
                    <p>Capacidad total del equipo en el periodo: <strong><?= number_format($totalCapacity, 1) ?>h</strong></p>
                    <p>Capacidad disponible: <strong><?= number_format($availableCapacity, 1) ?>h</strong></p>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>

<style>
.simulation-shell { display: flex; flex-direction: column; gap: 20px; }
.simulation-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.simulation-form-card { border: 1px solid var(--border); background: var(--surface); border-radius: 14px; padding: 18px; }
.simulation-form-card h3 { margin: 0 0 14px 0; font-size: 1.1rem; }
.simulation-form .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 14px; }
.simulation-form label { display: flex; flex-direction: column; gap: 6px; font-size: .9rem; color: var(--text-secondary); }
.simulation-form input { background: var(--background); border: 1px solid var(--border); border-radius: 10px; padding: 10px; color: var(--text-primary); }
.simulation-form input.readonly { background: color-mix(in srgb, var(--background) 80%, var(--surface)); cursor: not-allowed; }
.simulation-form .form-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.simulation-indicators { border: 1px solid var(--border); background: var(--surface); border-radius: 14px; padding: 18px; }
.simulation-indicators h3 { margin: 0 0 14px 0; font-size: 1.1rem; }
.indicator-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.indicator-card { border: 1px solid var(--border); border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 6px; background: color-mix(in srgb, var(--surface) 95%, var(--background)); }
.indicator-card.highlight { border-color: color-mix(in srgb, var(--primary) 50%, var(--border)); background: color-mix(in srgb, var(--primary) 10%, var(--surface)); }
.indicator-card strong { font-size: 1.4rem; color: var(--primary); }
.indicator-card span { font-size: .85rem; color: var(--text-secondary); }
.simulation-impact { border: 1px solid var(--border); background: var(--surface); border-radius: 14px; padding: 18px; }
.simulation-impact h3 { margin: 0 0 14px 0; font-size: 1.1rem; }
.table-wrap { overflow: auto; }
.impact-table { width: 100%; border-collapse: collapse; }
.impact-table th, .impact-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); }
.impact-table thead th { background: color-mix(in srgb, var(--surface) 70%, var(--background)); font-weight: 600; }
.impact-table tbody tr.util-red { background: rgba(239, 68, 68, .08); }
.impact-table tbody tr.util-yellow { background: rgba(250, 204, 21, .08); }
.impact-table tbody tr.util-green { background: rgba(34, 197, 94, .06); }
.util-badge { padding: 4px 10px; border-radius: 999px; font-weight: 600; font-size: .9rem; }
.util-badge.util-green { background: rgba(34, 197, 94, .25); color: #15803d; }
.util-badge.util-yellow { background: rgba(250, 204, 21, .35); color: #854d0e; }
.util-badge.util-red { background: rgba(239, 68, 68, .35); color: #b91c1c; }
.legend { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 12px; font-size: .85rem; color: var(--text-secondary); }
.legend-item { display: inline-flex; align-items: center; gap: 6px; }
.dot { width: 10px; height: 10px; border-radius: 999px; display: inline-block; }
.dot.util-green { background: rgba(34, 197, 94, .6); }
.dot.util-yellow { background: rgba(250, 204, 21, .6); }
.dot.util-red { background: rgba(239, 68, 68, .6); }
.simulation-insights { border: 1px solid var(--border); background: linear-gradient(130deg, color-mix(in srgb, var(--surface) 92%, #dbeafe), color-mix(in srgb, var(--surface) 94%, #f1f5f9)); border-radius: 14px; padding: 18px; }
.simulation-insights h3 { margin: 0 0 12px 0; font-size: 1.1rem; }
.simulation-insights ul { margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 8px; }
.simulation-insights li { font-size: .95rem; color: var(--text-primary); line-height: 1.45; }
.simulation-placeholder { border: 1px dashed var(--border); border-radius: 14px; padding: 24px; text-align: center; color: var(--text-secondary); }
.simulation-placeholder .preview-stats { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border); }
</style>
