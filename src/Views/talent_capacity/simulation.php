<?php
$basePath = $basePath ?? '';
$form = is_array($form ?? null) ? $form : [];
$simulation = is_array($simulation ?? null) ? $simulation : [];
$rows = is_array($simulation['rows'] ?? null) ? $simulation['rows'] : [];
$kpis = is_array($simulation['kpis'] ?? null) ? $simulation['kpis'] : [];
$insights = is_array($simulation['insights'] ?? null) ? $simulation['insights'] : [];

$projectName = (string) ($form['project_name'] ?? '');
$estimatedHours = (float) ($form['estimated_hours'] ?? 200);
$period = (string) ($form['period'] ?? '');

$remainingCapacity = (float) ($kpis['remaining_capacity'] ?? 0);
?>

<section class="sim-shell">
    <nav class="capacity-module-tabs" aria-label="Submódulos de carga talento">
        <a href="<?= $basePath ?>/talent-capacity" class="capacity-tab-link">Vista de capacidad</a>
        <a href="<?= $basePath ?>/talent-capacity/simulation" class="capacity-tab-link active">Simulación de capacidad</a>
    </nav>

    <section class="sim-card">
        <div class="section-title section-title-stack">
            <h3>Proyecto a simular</h3>
            <small class="section-muted">La simulación es referencial: no modifica datos reales de proyectos ni asignaciones.</small>
        </div>
        <form method="GET" action="<?= $basePath ?>/talent-capacity/simulation" class="sim-form">
            <label>Nombre del proyecto
                <input type="text" name="project_name" value="<?= htmlspecialchars($projectName) ?>" placeholder="Nuevo proyecto estratégico">
            </label>
            <label>Horas estimadas
                <input type="number" min="0" step="1" name="estimated_hours" value="<?= htmlspecialchars((string) $estimatedHours) ?>">
            </label>
            <label>Periodo
                <input type="text" name="period" value="<?= htmlspecialchars($period) ?>" placeholder="Marzo - Abril">
            </label>
            <div class="actions">
                <button type="submit" class="action-btn primary">Ejecutar simulación</button>
            </div>
        </form>
    </section>

    <section class="sim-kpi-grid">
        <article class="sim-kpi-card">
            <span>Capacidad total equipo</span>
            <strong><?= number_format((float) ($kpis['team_capacity'] ?? 0), 1) ?>h</strong>
        </article>
        <article class="sim-kpi-card">
            <span>Capacidad disponible</span>
            <strong><?= number_format((float) ($kpis['available_capacity'] ?? 0), 1) ?>h</strong>
        </article>
        <article class="sim-kpi-card">
            <span>Capacidad comprometida</span>
            <strong><?= number_format((float) ($kpis['committed_capacity'] ?? 0), 1) ?>h</strong>
        </article>
        <article class="sim-kpi-card <?= $remainingCapacity < 0 ? 'is-danger' : '' ?>">
            <span>Capacidad restante</span>
            <strong><?= number_format($remainingCapacity, 1) ?>h</strong>
        </article>
    </section>

    <section class="sim-card">
        <div class="section-title section-title-stack">
            <h3>Impacto en el equipo</h3>
            <small class="section-muted">Utilización final = (horas actuales + horas simuladas adicionales) / capacidad mensual del talento.</small>
        </div>
        <div class="sim-table-wrap">
            <table class="sim-table">
                <thead>
                    <tr>
                        <th>Talento</th>
                        <th>Actual</th>
                        <th>Simulado</th>
                        <th>Utilización final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="4" class="section-muted">No hay talentos disponibles para simular.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $traffic = (string) ($row['traffic_light'] ?? 'green');
                            $trafficClass = $traffic === 'red'
                                ? 'traffic-red'
                                : ($traffic === 'yellow' ? 'traffic-yellow' : 'traffic-green');
                            ?>
                            <tr class="<?= (bool) ($row['risk'] ?? false) ? 'row-risk' : '' ?>">
                                <td><?= htmlspecialchars((string) ($row['name'] ?? 'Talento')) ?></td>
                                <td><?= number_format((float) ($row['current_hours'] ?? 0), 1) ?>h</td>
                                <td><?= number_format((float) ($row['simulated_hours'] ?? 0), 1) ?>h</td>
                                <td>
                                    <span class="traffic-pill <?= $trafficClass ?>">
                                        <?= number_format((float) ($row['utilization'] ?? 0), 1) ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="sim-card">
        <div class="section-title section-title-stack">
            <h3>Insights del sistema</h3>
            <small class="section-muted">Detección automática de sobrecarga (&gt;90%) y viabilidad.</small>
        </div>
        <ul class="insights-list">
            <?php foreach ($insights as $insight): ?>
                <li><?= htmlspecialchars((string) $insight) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
</section>

<style>
.sim-shell { display: flex; flex-direction: column; gap: 14px; }
.capacity-module-tabs { display: inline-flex; flex-wrap: wrap; gap: 8px; }
.capacity-tab-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: color-mix(in srgb, var(--surface) 85%, var(--background) 15%);
    color: var(--text-secondary);
    font-weight: 700;
    font-size: .85rem;
}
.capacity-tab-link.active {
    color: var(--text-primary);
    border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
    background: color-mix(in srgb, var(--primary) 14%, var(--surface));
}
.sim-card {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 14px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.sim-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}
.sim-form label { display: flex; flex-direction: column; gap: 6px; color: var(--text-secondary); font-size: .9rem; }
.sim-form input {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px;
    background: var(--background);
    color: var(--text-primary);
}
.sim-form .actions { display: flex; align-items: end; }
.sim-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
.sim-kpi-card {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 14px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.sim-kpi-card span { color: var(--text-secondary); font-size: .82rem; font-weight: 600; }
.sim-kpi-card strong { color: var(--primary); font-size: 1.5rem; }
.sim-kpi-card.is-danger strong { color: #b91c1c; }
.sim-table-wrap { overflow: auto; }
.sim-table { width: 100%; border-collapse: collapse; margin-top: 0; }
.sim-table th,
.sim-table td {
    border-bottom: 1px solid color-mix(in srgb, var(--border) 80%, var(--background));
    padding: 10px 8px;
    text-align: left;
    font-size: .9rem;
}
.sim-table th { color: var(--text-secondary); font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
.traffic-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: .8rem;
    font-weight: 700;
    border: 1px solid transparent;
}
.traffic-green {
    background: rgba(34, 197, 94, .12);
    border-color: rgba(34, 197, 94, .4);
    color: #15803d;
}
.traffic-yellow {
    background: rgba(250, 204, 21, .16);
    border-color: rgba(234, 179, 8, .45);
    color: #a16207;
}
.traffic-red {
    background: rgba(239, 68, 68, .12);
    border-color: rgba(220, 38, 38, .35);
    color: #b91c1c;
}
.row-risk { background: rgba(239, 68, 68, .05); }
.insights-list {
    margin: 0;
    padding-left: 18px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: var(--text-primary);
}
</style>
