<?php
$basePath = $basePath ?? '';
$filters = is_array($filters ?? null) ? $filters : [];
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$talents = is_array($dashboard['talents'] ?? null) ? $dashboard['talents'] : [];
$summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
$analytics = is_array($dashboard['analytics'] ?? null) ? $dashboard['analytics'] : [];
$options = is_array($dashboard['filter_options'] ?? null) ? $dashboard['filter_options'] : [];
$range = is_array($dashboard['range'] ?? null) ? $dashboard['range'] : ['start' => date('Y-m-01'), 'end' => date('Y-m-t')];
$granularity = $filters['heatmap_granularity'] ?? 'week';
$insightTeamUtilization = is_array($analytics['team_utilization'] ?? null) ? $analytics['team_utilization'] : ['utilization' => 0, 'hours' => 0, 'capacity' => 0, 'interpretation' => ''];
$insightPeakWeeks = is_array($analytics['peak_weeks'] ?? null) ? $analytics['peak_weeks'] : ['items' => [], 'interpretation' => ''];
$insightTopUtilized = is_array($analytics['top_utilized_talents'] ?? null) ? $analytics['top_utilized_talents'] : ['items' => [], 'interpretation' => ''];
$insightAvailableTalents = is_array($analytics['available_talents'] ?? null) ? $analytics['available_talents'] : ['items' => [], 'interpretation' => ''];
$insightFreeCapacity = is_array($analytics['free_capacity'] ?? null) ? $analytics['free_capacity'] : ['hours' => 0, 'percentage' => 0, 'interpretation' => ''];

$columns = [];
$cursor = new DateTimeImmutable($range['start']);
$limit = new DateTimeImmutable($range['end']);
while ($cursor <= $limit) {
    if ((int) $cursor->format('N') > 5) {
        $cursor = $cursor->modify('+1 day');
        continue;
    }
    if ($granularity === 'day') {
        $key = $cursor->format('Y-m-d');
        $columns[$key] = $cursor->format('d M');
    } else {
        $key = $cursor->format('o-\\WW');
        $columns[$key] = 'Sem ' . $cursor->format('W');
    }
    $cursor = $cursor->modify('+1 day');
}
$columns = array_unique($columns);

$heatLevelForUtilization = static function (float $utilization): string {
    if ($utilization <= 0.1) {
        return 'none';
    }
    if ($utilization > 100) {
        return 'overload';
    }
    if ($utilization >= 80) {
        return 'high';
    }

    return 'healthy';
};

$statusLabel = [
    'none' => 'Sin carga',
    'healthy' => 'Saludable',
    'high' => 'Alto',
    'overload' => 'Sobrecarga',
];

$teamRanking = [];
$criticalTalents = [];
$availableTalents = [];
$weeklyRiskBuckets = [];
$teamMonthlyBuckets = [];

foreach ($talents as $talent) {
    $name = (string) ($talent['name'] ?? '');
    $weekly = is_array($talent['weekly'] ?? null) ? $talent['weekly'] : [];
    $monthly = is_array($talent['monthly'] ?? null) ? $talent['monthly'] : [];
    $latestMonth = !empty($monthly) ? end($monthly) : ['hours' => 0, 'capacity' => 0, 'utilization' => 0];
    $utilization = (float) ($latestMonth['utilization'] ?? 0);
    $hours = (float) ($latestMonth['hours'] ?? 0);
    $capacity = (float) ($latestMonth['capacity'] ?? 0);
    $available = max(0.0, $capacity - $hours);
    $overload = max(0.0, $hours - $capacity);
    $level = $heatLevelForUtilization($utilization);

    $teamRanking[] = [
        'name' => $name,
        'utilization' => $utilization,
        'hours' => $hours,
        'capacity' => $capacity,
        'available' => $available,
        'overload' => $overload,
        'level' => $level,
    ];

    if ($utilization > 90) {
        $criticalTalents[] = [
            'name' => $name,
            'utilization' => $utilization,
            'overload' => $overload,
        ];
    }
    if ($available > 0) {
        $availableTalents[] = [
            'name' => $name,
            'available' => $available,
            'utilization' => $utilization,
            'level' => $level,
        ];
    }

    foreach ($weekly as $weekKey => $bucket) {
        $weeklyRiskBuckets[$weekKey]['utilization_sum'] = ($weeklyRiskBuckets[$weekKey]['utilization_sum'] ?? 0) + (float) ($bucket['utilization'] ?? 0);
        $weeklyRiskBuckets[$weekKey]['count'] = ($weeklyRiskBuckets[$weekKey]['count'] ?? 0) + 1;
    }

    foreach ($monthly as $monthKey => $bucket) {
        $teamMonthlyBuckets[$monthKey]['hours'] = ($teamMonthlyBuckets[$monthKey]['hours'] ?? 0) + (float) ($bucket['hours'] ?? 0);
        $teamMonthlyBuckets[$monthKey]['capacity'] = ($teamMonthlyBuckets[$monthKey]['capacity'] ?? 0) + (float) ($bucket['capacity'] ?? 0);
    }
}

usort(
    $teamRanking,
    static fn (array $a, array $b): int => ($b['utilization'] <=> $a['utilization']) ?: strcmp($a['name'], $b['name'])
);
usort(
    $criticalTalents,
    static fn (array $a, array $b): int => ($b['utilization'] <=> $a['utilization']) ?: strcmp($a['name'], $b['name'])
);
usort(
    $availableTalents,
    static fn (array $a, array $b): int => ($b['available'] <=> $a['available']) ?: strcmp($a['name'], $b['name'])
);

$weeklyRisk = [];
foreach ($weeklyRiskBuckets as $weekKey => $bucket) {
    $count = (int) ($bucket['count'] ?? 0);
    $avg = $count > 0 ? ((float) ($bucket['utilization_sum'] ?? 0) / $count) : 0.0;
    if ($avg >= 90) {
        $weeklyRisk[] = [
            'week' => (string) $weekKey,
            'utilization' => round($avg, 1),
        ];
    }
}
usort($weeklyRisk, static fn (array $a, array $b): int => $b['utilization'] <=> $a['utilization']);
$weeklyRisk = array_slice($weeklyRisk, 0, 6);

ksort($teamMonthlyBuckets);
$teamMonthly = [];
foreach ($teamMonthlyBuckets as $monthKey => $bucket) {
    $hours = (float) ($bucket['hours'] ?? 0);
    $capacity = (float) ($bucket['capacity'] ?? 0);
    $utilization = $capacity > 0 ? (($hours / $capacity) * 100) : 0;
    $teamMonthly[] = [
        'month' => $monthKey,
        'hours' => round($hours, 1),
        'capacity' => round($capacity, 1),
        'available' => round(max(0, $capacity - $hours), 1),
        'utilization' => round($utilization, 1),
        'level' => $heatLevelForUtilization($utilization),
    ];
}

$maxAvailable = 0.0;
foreach ($availableTalents as $item) {
    $maxAvailable = max($maxAvailable, (float) ($item['available'] ?? 0));
}
?>

<section class="capacity-shell">
    <header class="capacity-header">
        <div>
            <p class="eyebrow">Módulo visual</p>
            <h2>Gestión Visual de Carga y Capacidad del Talento</h2>
            <small class="section-muted">Visualiza saturación, capacidad ociosa y balance del equipo en tiempo real.</small>
        </div>
        <span class="badge neutral">Vista ejecutiva</span>
    </header>

    <form method="GET" class="capacity-filters card-grid">
        <label>Área
            <select name="area">
                <option value="">Todas</option>
                <?php foreach (($options['areas'] ?? []) as $area): ?>
                    <?php $code = (string) ($area['code'] ?? ''); ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= ($filters['area'] ?? '') === $code ? 'selected' : '' ?>><?= htmlspecialchars($code) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Proyecto
            <select name="project_id">
                <option value="0">Todos</option>
                <?php foreach (($options['projects'] ?? []) as $project): ?>
                    <option value="<?= (int) $project['id'] ?>" <?= ((int) ($filters['project_id'] ?? 0) === (int) $project['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($project['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Rol
            <select name="role">
                <option value="">Todos</option>
                <?php foreach (($options['roles'] ?? []) as $role): ?>
                    <?php $roleName = (string) ($role['role'] ?? ''); ?>
                    <option value="<?= htmlspecialchars($roleName) ?>" <?= ($filters['role'] ?? '') === $roleName ? 'selected' : '' ?>><?= htmlspecialchars($roleName) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Desde
            <input type="date" name="date_from" value="<?= htmlspecialchars((string) ($filters['date_from'] ?? $range['start'])) ?>">
        </label>
        <label>Hasta
            <input type="date" name="date_to" value="<?= htmlspecialchars((string) ($filters['date_to'] ?? $range['end'])) ?>">
        </label>
        <label>Heatmap
            <select name="heatmap_granularity">
                <option value="week" <?= $granularity === 'week' ? 'selected' : '' ?>>Semanas</option>
                <option value="day" <?= $granularity === 'day' ? 'selected' : '' ?>>Días</option>
            </select>
        </label>
        <div class="actions">
            <button type="submit" class="action-btn primary">Aplicar filtros</button>
        </div>
    </form>

    <section class="kpi-grid">
        <article class="kpi-card"><strong><?= number_format((float) ($summary['avg_team_utilization'] ?? 0), 1) ?>%</strong><span>Utilización promedio del equipo</span></article>
        <article class="kpi-card"><strong><?= number_format((float) ($summary['overassigned_hours'] ?? 0), 1) ?>h</strong><span>Total horas sobreasignadas</span></article>
        <article class="kpi-card"><strong><?= (int) ($summary['risk_talents'] ?? 0) ?></strong><span>Talentos críticos (&gt;90%)</span></article>
        <article class="kpi-card"><strong><?= number_format((float) ($summary['idle_capacity'] ?? 0), 1) ?>h</strong><span>Capacidad ociosa global</span></article>
    </section>
    <div style="background:color-mix(in srgb,var(--info) 10%,var(--background));border:1px solid color-mix(in srgb,var(--info) 30%,var(--border));border-radius:10px;padding:10px 14px;font-size:13px;color:var(--text-secondary)">
        La <strong>capacidad real</strong> de cada talento descuenta automáticamente festivos y ausencias aprobadas (vacaciones, permisos, incapacidades).
        Para gestionar ausencias ve a <a href="<?= $basePath ?>/absences" style="color:var(--primary);font-weight:700">Ausencias →</a>
    </div>

    <section class="capacity-block analytics-layer">
        <div class="section-title section-title-stack">
            <h3>Capa analítica automática de carga del talento</h3>
            <small class="section-muted">Insights e interpretación automática para apoyar decisiones de asignación y balance operativo.</small>
        </div>
        <div class="analytics-grid">
            <article class="analytics-card">
                <h4>Nivel de utilización del equipo</h4>
                <strong><?= number_format((float) ($insightTeamUtilization['utilization'] ?? 0), 1) ?>%</strong>
                <small><?= number_format((float) ($insightTeamUtilization['hours'] ?? 0), 1) ?>h de <?= number_format((float) ($insightTeamUtilization['capacity'] ?? 0), 1) ?>h de capacidad</small>
                <p><?= htmlspecialchars((string) ($insightTeamUtilization['interpretation'] ?? 'Sin interpretación disponible.')) ?></p>
            </article>

            <article class="analytics-card">
                <h4>Semanas con mayor carga</h4>
                <?php $peakItems = array_slice(is_array($insightPeakWeeks['items'] ?? null) ? $insightPeakWeeks['items'] : [], 0, 3); ?>
                <?php if (empty($peakItems)): ?>
                    <small>Sin semanas destacadas.</small>
                <?php else: ?>
                    <ul>
                        <?php foreach ($peakItems as $peak): ?>
                            <li><?= htmlspecialchars((string) ($peak['label'] ?? $peak['week'] ?? '-')) ?> · <?= number_format((float) ($peak['utilization'] ?? 0), 1) ?>%</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p><?= htmlspecialchars((string) ($insightPeakWeeks['interpretation'] ?? 'Sin interpretación disponible.')) ?></p>
            </article>

            <article class="analytics-card">
                <h4>Talentos con mayor utilización</h4>
                <?php $topUtilizedItems = array_slice(is_array($insightTopUtilized['items'] ?? null) ? $insightTopUtilized['items'] : [], 0, 3); ?>
                <?php if (empty($topUtilizedItems)): ?>
                    <small>Sin talentos destacados.</small>
                <?php else: ?>
                    <ul>
                        <?php foreach ($topUtilizedItems as $talent): ?>
                            <li><?= htmlspecialchars((string) ($talent['name'] ?? '-')) ?> · <?= number_format((float) ($talent['utilization'] ?? 0), 1) ?>%</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p><?= htmlspecialchars((string) ($insightTopUtilized['interpretation'] ?? 'Sin interpretación disponible.')) ?></p>
            </article>

            <article class="analytics-card">
                <h4>Talentos disponibles para asignación</h4>
                <?php $availableItems = array_slice(is_array($insightAvailableTalents['items'] ?? null) ? $insightAvailableTalents['items'] : [], 0, 3); ?>
                <?php if (empty($availableItems)): ?>
                    <small>No hay disponibilidad registrada.</small>
                <?php else: ?>
                    <ul>
                        <?php foreach ($availableItems as $talent): ?>
                            <li><?= htmlspecialchars((string) ($talent['name'] ?? '-')) ?> · <?= number_format((float) ($talent['free_hours'] ?? 0), 1) ?>h libres</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p><?= htmlspecialchars((string) ($insightAvailableTalents['interpretation'] ?? 'Sin interpretación disponible.')) ?></p>
            </article>

            <article class="analytics-card">
                <h4>Capacidad libre del equipo</h4>
                <strong><?= number_format((float) ($insightFreeCapacity['hours'] ?? 0), 1) ?>h</strong>
                <small><?= number_format((float) ($insightFreeCapacity['percentage'] ?? 0), 1) ?>% del total disponible</small>
                <p><?= htmlspecialchars((string) ($insightFreeCapacity['interpretation'] ?? 'Sin interpretación disponible.')) ?></p>
            </article>
        </div>
    </section>

    <section class="capacity-block">
        <div class="section-title section-title-stack">
            <h3>Heatmap visual de carga del equipo</h3>
            <small class="section-muted">Lectura rápida por semanas/días: blanco (sin carga), verde (saludable), amarillo (alto), rojo (sobrecarga).</small>
        </div>
        <div class="risk-strip">
            <strong>Semanas con riesgo (&gt;=90% promedio):</strong>
            <?php if (empty($weeklyRisk)): ?>
                <span class="risk-chip neutral">Sin semanas críticas</span>
            <?php else: ?>
                <?php foreach ($weeklyRisk as $week): ?>
                    <span class="risk-chip warning"><?= htmlspecialchars((string) ($week['week'] ?? '')) ?> · <?= number_format((float) ($week['utilization'] ?? 0), 1) ?>%</span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="heatmap-table-wrap">
            <table class="heatmap-table">
                <thead>
                    <tr><th>Talento</th><?php foreach ($columns as $label): ?><th><?= htmlspecialchars($label) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($talents as $talent): ?>
                    <?php $source = $granularity === 'day' ? ($talent['daily'] ?? []) : ($talent['weekly'] ?? []); ?>
                    <tr>
                        <th><?= htmlspecialchars((string) ($talent['name'] ?? '')) ?></th>
                        <?php foreach ($columns as $key => $label): ?>
                            <?php $cell = $source[$key] ?? ['utilization' => 0, 'status' => 'none']; ?>
                            <?php $cellUtilization = (float) ($cell['utilization'] ?? 0); ?>
                            <?php $level = $heatLevelForUtilization($cellUtilization); ?>
                            <td class="heat-cell heat-<?= htmlspecialchars($level) ?>" title="<?= number_format($cellUtilization, 1) ?>%">
                                <?= number_format((float) ($cell['utilization'] ?? 0), 0) ?>%
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="legend">
            <?php foreach ($statusLabel as $key => $label): ?>
                <span class="legend-item"><i class="dot heat-<?= htmlspecialchars($key) ?>"></i><?= htmlspecialchars($label) ?></span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="capacity-block">
        <div class="section-title section-title-stack">
            <h3>Ranking de carga del equipo (actual)</h3>
            <small class="section-muted">Ordenado de mayor a menor utilización para detectar sobrecargas y disponibilidad en segundos.</small>
        </div>
        <div class="bar-list">
            <?php foreach ($teamRanking as $item): ?>
                <article class="bar-item">
                    <header>
                        <strong><?= htmlspecialchars((string) ($item['name'] ?? '')) ?></strong>
                        <span><?= number_format((float) ($item['utilization'] ?? 0), 1) ?>%</span>
                    </header>
                    <div class="track">
                        <span class="fill heat-<?= htmlspecialchars((string) ($item['level'] ?? 'none')) ?>" style="width: <?= min(100, max(0, (float) ($item['utilization'] ?? 0))) ?>%"></span>
                    </div>
                    <small>
                        Asignadas: <?= number_format((float) ($item['hours'] ?? 0), 1) ?>h ·
                        Capacidad: <?= number_format((float) ($item['capacity'] ?? 0), 1) ?>h ·
                        <?php if ((float) ($item['overload'] ?? 0) > 0): ?>
                            Sobrecarga: <?= number_format((float) ($item['overload'] ?? 0), 1) ?>h
                        <?php else: ?>
                            Disponible: <?= number_format((float) ($item['available'] ?? 0), 1) ?>h
                        <?php endif; ?>
                    </small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="capacity-grid-two">
        <article class="capacity-block">
            <div class="section-title section-title-stack">
                <h3>Talentos críticos (&gt;90%)</h3>
                <small class="section-muted">Prioridad operativa inmediata.</small>
            </div>
            <?php if (empty($criticalTalents)): ?>
                <p class="section-muted">No hay talentos por encima del 90% en el periodo actual.</p>
            <?php else: ?>
                <ul class="critical-list">
                    <?php foreach ($criticalTalents as $critical): ?>
                        <li>
                            <span><?= htmlspecialchars((string) ($critical['name'] ?? '')) ?></span>
                            <strong><?= number_format((float) ($critical['utilization'] ?? 0), 1) ?>%</strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="capacity-block">
            <div class="section-title section-title-stack">
                <h3>Capacidad disponible del equipo</h3>
                <small class="section-muted">Quién está libre y cuántas horas puede absorber.</small>
            </div>
            <?php if (empty($availableTalents)): ?>
                <p class="section-muted">No hay capacidad libre registrada en el corte actual.</p>
            <?php else: ?>
                <div class="mini-bars">
                    <?php foreach (array_slice($availableTalents, 0, 8) as $free): ?>
                        <?php $freeWidth = $maxAvailable > 0 ? (((float) ($free['available'] ?? 0) / $maxAvailable) * 100) : 0; ?>
                        <article class="mini-bar-item">
                            <header>
                                <strong><?= htmlspecialchars((string) ($free['name'] ?? '')) ?></strong>
                                <span><?= number_format((float) ($free['available'] ?? 0), 1) ?>h libres</span>
                            </header>
                            <div class="track">
                                <span class="fill heat-healthy" style="width: <?= min(100, max(0, $freeWidth)) ?>%"></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="capacity-block">
        <div class="section-title section-title-stack">
            <h3>Capacidad mensual del equipo (compacta)</h3>
            <small class="section-muted">Vista agregada para evitar listas largas por talento.</small>
        </div>
        <?php if (empty($teamMonthly)): ?>
            <p class="section-muted">No hay datos mensuales en el rango seleccionado.</p>
        <?php else: ?>
            <div class="mini-bars">
                <?php foreach ($teamMonthly as $month): ?>
                    <article class="mini-bar-item">
                        <header>
                            <strong><?= htmlspecialchars((string) ($month['month'] ?? '')) ?></strong>
                            <span><?= number_format((float) ($month['utilization'] ?? 0), 1) ?>%</span>
                        </header>
                        <div class="track">
                            <span class="fill heat-<?= htmlspecialchars((string) ($month['level'] ?? 'none')) ?>" style="width: <?= min(100, max(0, (float) ($month['utilization'] ?? 0))) ?>%"></span>
                        </div>
                        <small>
                            Asignadas: <?= number_format((float) ($month['hours'] ?? 0), 1) ?>h ·
                            Capacidad: <?= number_format((float) ($month['capacity'] ?? 0), 1) ?>h ·
                            Disponible: <?= number_format((float) ($month['available'] ?? 0), 1) ?>h
                        </small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<style>
.capacity-shell { display: flex; flex-direction: column; gap: 18px; }
.capacity-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.capacity-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; padding: 14px; border: 1px solid var(--border); background: var(--surface); border-radius: 14px; }
.capacity-filters label { display: flex; flex-direction: column; gap: 6px; color: var(--text-secondary); font-size: .9rem; }
.capacity-filters input,
.capacity-filters select { background: var(--background); border: 1px solid var(--border); border-radius: 10px; padding: 10px; color: var(--text-primary); }
.capacity-filters .actions { display: flex; align-items: end; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
.kpi-card { border: 1px solid var(--border); background: var(--surface); border-radius: 14px; padding: 16px; display: flex; flex-direction: column; gap: 8px; }
.kpi-card strong { font-size: 1.6rem; color: var(--primary); }
.analytics-layer { background: linear-gradient(130deg, color-mix(in srgb, var(--surface) 92%, #dbeafe), color-mix(in srgb, var(--surface) 94%, #f1f5f9)); }
.analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 10px; }
.analytics-card { border: 1px solid color-mix(in srgb, var(--border) 80%, #bfdbfe); background: color-mix(in srgb, var(--surface) 92%, #eff6ff); border-radius: 12px; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.analytics-card h4 { margin: 0; font-size: .92rem; }
.analytics-card strong { font-size: 1.4rem; color: #1d4ed8; }
.analytics-card p { margin: 0; font-size: .84rem; color: var(--text-secondary); line-height: 1.45; }
.analytics-card small { color: var(--text-secondary); font-size: .78rem; }
.analytics-card ul { margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 4px; }
.analytics-card li { font-size: .82rem; color: var(--text-primary); }
.capacity-grid-two { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
.capacity-block { border: 1px solid var(--border); background: var(--surface); border-radius: 14px; padding: 14px; display: flex; flex-direction: column; gap: 12px; }
.section-title-stack { display: flex; flex-direction: column; gap: 2px; }

.risk-strip { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: .86rem; color: var(--text-secondary); }
.risk-chip { border-radius: 999px; padding: 4px 10px; border: 1px solid var(--border); font-size: .8rem; }
.risk-chip.warning { background: rgba(245, 158, 11, .12); border-color: rgba(245, 158, 11, .35); color: #92400e; }
.risk-chip.neutral { background: rgba(148, 163, 184, .12); color: var(--text-secondary); }

.heatmap-table-wrap { overflow: auto; }
.heatmap-table { width: max-content; min-width: 100%; border-collapse: separate; border-spacing: 4px; }
.heatmap-table th,
.heatmap-table td { padding: 8px 10px; text-align: center; font-size: .82rem; border-radius: 8px; border: 1px solid rgba(148, 163, 184, .25); }
.heatmap-table thead th { background: color-mix(in srgb, var(--surface) 70%, var(--background)); }
.heatmap-table tbody th { background: color-mix(in srgb, var(--surface) 70%, var(--background)); position: sticky; left: 0; z-index: 2; text-align: left; }
.heatmap-table td { min-width: 64px; font-weight: 600; }
.heat-cell { color: #111827; }

.legend { display: flex; flex-wrap: wrap; gap: 10px; }
.legend-item { display: inline-flex; align-items: center; gap: 6px; font-size: .82rem; color: var(--text-secondary); }
.dot { width: 10px; height: 10px; border-radius: 999px; display: inline-block; border: 1px solid rgba(17, 24, 39, .12); }

.bar-list,
.mini-bars { display: flex; flex-direction: column; gap: 10px; }
.bar-item,
.mini-bar-item { padding: 10px; border: 1px solid var(--border); border-radius: 12px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); display: flex; flex-direction: column; gap: 6px; }
.bar-item header,
.mini-bar-item header { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
.bar-item header span,
.mini-bar-item header span { white-space: nowrap; }
.track { height: 12px; background: color-mix(in srgb, var(--background) 92%, var(--surface)); border-radius: 999px; overflow: hidden; border: 1px solid rgba(148, 163, 184, .2); }
.fill { height: 100%; display: block; }

.critical-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.critical-list li { display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(239, 68, 68, .25); background: rgba(239, 68, 68, .08); border-radius: 10px; padding: 8px 10px; }
.critical-list strong { color: #b91c1c; }

.heat-none { background: #ffffff; }
.heat-healthy { background: rgba(34, 197, 94, .35); }
.heat-high { background: rgba(250, 204, 21, .45); }
.heat-overload { background: rgba(239, 68, 68, .45); }
</style>
