<?php
$basePath = $basePath ?? '';
$filters = is_array($filters ?? null) ? $filters : [];
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$talents = is_array($dashboard['talents'] ?? null) ? $dashboard['talents'] : [];
$summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
$options = is_array($dashboard['filter_options'] ?? null) ? $dashboard['filter_options'] : [];
$range = is_array($dashboard['range'] ?? null) ? $dashboard['range'] : ['start' => date('Y-m-01'), 'end' => date('Y-m-t')];
$granularity = $filters['heatmap_granularity'] ?? 'week';

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

$insights = is_array($insights ?? null) ? $insights : [];
$insTeamUtil = is_array($insights['team_utilization'] ?? null) ? $insights['team_utilization'] : [];
$insPeakWeeks = is_array($insights['peak_weeks'] ?? null) ? $insights['peak_weeks'] : [];
$insTopTalents = is_array($insights['top_utilized_talents'] ?? null) ? $insights['top_utilized_talents'] : [];
$insAvailable = is_array($insights['available_talents'] ?? null) ? $insights['available_talents'] : [];
$insFreeCap = is_array($insights['free_capacity'] ?? null) ? $insights['free_capacity'] : [];

$insightLevelIcon = static function (string $level): string {
    $icons = [
        'critical' => '&#xe160;',
        'high' => '&#xe002;',
        'optimal' => '&#xe86c;',
        'moderate' => '&#xe8b2;',
        'low' => '&#xe15b;',
        'idle' => '&#xe88e;',
    ];
    return $icons[$level] ?? '&#xe8b2;';
};

$insightLevelClass = static function (string $level): string {
    $classes = [
        'critical' => 'insight-critical',
        'high' => 'insight-high',
        'optimal' => 'insight-optimal',
        'moderate' => 'insight-moderate',
        'low' => 'insight-low',
        'idle' => 'insight-idle',
    ];
    return $classes[$level] ?? 'insight-moderate';
};
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

    <section class="insights-panel">
        <div class="insights-header">
            <div>
                <p class="eyebrow">Capa analítica</p>
                <h3>Insights y Análisis Automático del Equipo</h3>
                <small class="section-muted">Interpretaciones generadas automáticamente a partir del estado actual de carga y capacidad.</small>
            </div>
            <span class="badge insight-badge">Auto-generado</span>
        </div>

        <div class="insights-grid">
            <!-- Nivel de utilización del equipo -->
            <article class="insight-card <?= $insightLevelClass((string) ($insTeamUtil['level'] ?? 'moderate')) ?>">
                <div class="insight-card-header">
                    <div class="insight-icon-wrap">
                        <span class="material-icons insight-icon"><?= $insightLevelIcon((string) ($insTeamUtil['level'] ?? 'moderate')) ?></span>
                    </div>
                    <div>
                        <h4>Nivel de Utilización del Equipo</h4>
                        <span class="insight-metric"><?= number_format((float) ($insTeamUtil['avg_utilization'] ?? 0), 1) ?>%</span>
                    </div>
                </div>
                <?php $dist = $insTeamUtil['distribution'] ?? []; ?>
                <?php if (!empty($dist)): ?>
                    <div class="insight-distribution">
                        <?php
                        $distLabels = ['overload' => 'Sobrecarga', 'risk' => 'Riesgo', 'healthy' => 'Saludable', 'under' => 'Subutilizado', 'none' => 'Sin carga'];
                        $distColors = ['overload' => '#ef4444', 'risk' => '#f59e0b', 'healthy' => '#22c55e', 'under' => '#60a5fa', 'none' => '#cbd5e1'];
                        $totalDist = max(1, (int) ($insTeamUtil['total_talents'] ?? 1));
                        ?>
                        <div class="dist-bar">
                            <?php foreach ($distLabels as $dKey => $dLabel): ?>
                                <?php $dCount = (int) ($dist[$dKey] ?? 0); $dPct = ($dCount / $totalDist) * 100; ?>
                                <?php if ($dPct > 0): ?>
                                    <span class="dist-segment" style="width: <?= number_format($dPct, 1) ?>%; background: <?= $distColors[$dKey] ?>;" title="<?= $dLabel ?>: <?= $dCount ?>"></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="dist-legend">
                            <?php foreach ($distLabels as $dKey => $dLabel): ?>
                                <?php $dCount = (int) ($dist[$dKey] ?? 0); ?>
                                <?php if ($dCount > 0): ?>
                                    <span class="dist-legend-item"><i class="dist-dot" style="background: <?= $distColors[$dKey] ?>;"></i><?= $dCount ?> <?= $dLabel ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <p class="insight-interpretation"><?= htmlspecialchars((string) ($insTeamUtil['interpretation'] ?? '')) ?></p>
            </article>

            <!-- Semanas con mayor carga -->
            <article class="insight-card">
                <div class="insight-card-header">
                    <div class="insight-icon-wrap insight-icon-weeks">
                        <span class="material-icons insight-icon">&#xe916;</span>
                    </div>
                    <div>
                        <h4>Semanas con Mayor Carga</h4>
                        <span class="insight-metric"><?= (int) ($insPeakWeeks['critical_weeks'] ?? 0) ?> / <?= (int) ($insPeakWeeks['total_weeks'] ?? 0) ?> semanas críticas</span>
                    </div>
                </div>
                <?php $topWeeks = $insPeakWeeks['top_weeks'] ?? []; ?>
                <?php if (!empty($topWeeks)): ?>
                    <div class="insight-mini-table">
                        <?php foreach ($topWeeks as $tw): ?>
                            <div class="insight-mini-row">
                                <span class="insight-mini-label"><?= htmlspecialchars((string) ($tw['week'] ?? '')) ?></span>
                                <div class="insight-mini-bar-track">
                                    <span class="insight-mini-bar-fill <?= ((float) ($tw['avg_utilization'] ?? 0)) >= 90 ? 'bar-critical' : (((float) ($tw['avg_utilization'] ?? 0)) >= 70 ? 'bar-warning' : 'bar-ok') ?>"
                                          style="width: <?= min(100, max(0, (float) ($tw['avg_utilization'] ?? 0))) ?>%"></span>
                                </div>
                                <span class="insight-mini-value"><?= number_format((float) ($tw['avg_utilization'] ?? 0), 1) ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="insight-interpretation"><?= htmlspecialchars((string) ($insPeakWeeks['interpretation'] ?? '')) ?></p>
            </article>

            <!-- Talentos con mayor utilización -->
            <article class="insight-card">
                <div class="insight-card-header">
                    <div class="insight-icon-wrap insight-icon-top">
                        <span class="material-icons insight-icon">&#xe7fd;</span>
                    </div>
                    <div>
                        <h4>Talentos con Mayor Utilización</h4>
                        <span class="insight-metric">
                            <?= (int) ($insTopTalents['overloaded_count'] ?? 0) ?> sobrecargado(s),
                            <?= (int) ($insTopTalents['at_risk_count'] ?? 0) ?> en riesgo
                        </span>
                    </div>
                </div>
                <?php $topTalents = $insTopTalents['top_talents'] ?? []; ?>
                <?php if (!empty($topTalents)): ?>
                    <div class="insight-talent-list">
                        <?php foreach ($topTalents as $idx => $tt): ?>
                            <div class="insight-talent-row">
                                <span class="insight-talent-rank">#<?= $idx + 1 ?></span>
                                <div class="insight-talent-info">
                                    <strong><?= htmlspecialchars((string) ($tt['name'] ?? '')) ?></strong>
                                    <small><?= htmlspecialchars((string) ($tt['role'] ?? '')) ?></small>
                                </div>
                                <div class="insight-talent-bar-wrap">
                                    <div class="insight-mini-bar-track">
                                        <span class="insight-mini-bar-fill <?= ((float) ($tt['utilization'] ?? 0)) > 100 ? 'bar-critical' : (((float) ($tt['utilization'] ?? 0)) >= 90 ? 'bar-warning' : 'bar-ok') ?>"
                                              style="width: <?= min(100, max(0, (float) ($tt['utilization'] ?? 0))) ?>%"></span>
                                    </div>
                                </div>
                                <span class="insight-talent-pct <?= ((float) ($tt['utilization'] ?? 0)) > 100 ? 'pct-critical' : (((float) ($tt['utilization'] ?? 0)) >= 90 ? 'pct-warning' : '') ?>"><?= number_format((float) ($tt['utilization'] ?? 0), 1) ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="insight-interpretation"><?= htmlspecialchars((string) ($insTopTalents['interpretation'] ?? '')) ?></p>
            </article>

            <!-- Talentos disponibles para asignación -->
            <article class="insight-card">
                <div class="insight-card-header">
                    <div class="insight-icon-wrap insight-icon-available">
                        <span class="material-icons insight-icon">&#xe7fe;</span>
                    </div>
                    <div>
                        <h4>Talentos Disponibles para Asignación</h4>
                        <span class="insight-metric"><?= (int) ($insAvailable['total_available'] ?? 0) ?> talento(s) · <?= number_format((float) ($insAvailable['total_free_hours'] ?? 0), 1) ?>h libres</span>
                    </div>
                </div>
                <?php $availTalents = $insAvailable['talents'] ?? []; ?>
                <?php if (!empty($availTalents)): ?>
                    <?php $maxFree = max(1, (float) ($availTalents[0]['free_hours'] ?? 1)); ?>
                    <div class="insight-talent-list">
                        <?php foreach ($availTalents as $at): ?>
                            <div class="insight-talent-row">
                                <div class="insight-talent-info">
                                    <strong><?= htmlspecialchars((string) ($at['name'] ?? '')) ?></strong>
                                    <small><?= htmlspecialchars((string) ($at['role'] ?? '')) ?> · <?= number_format((float) ($at['utilization'] ?? 0), 1) ?>% utilizado</small>
                                </div>
                                <div class="insight-talent-bar-wrap">
                                    <div class="insight-mini-bar-track">
                                        <span class="insight-mini-bar-fill bar-available" style="width: <?= min(100, ((float) ($at['free_hours'] ?? 0) / $maxFree) * 100) ?>%"></span>
                                    </div>
                                </div>
                                <span class="insight-talent-free"><?= number_format((float) ($at['free_hours'] ?? 0), 1) ?>h</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="insight-interpretation"><?= htmlspecialchars((string) ($insAvailable['interpretation'] ?? '')) ?></p>
            </article>

            <!-- Capacidad libre del equipo -->
            <article class="insight-card insight-card-wide">
                <div class="insight-card-header">
                    <div class="insight-icon-wrap insight-icon-capacity">
                        <span class="material-icons insight-icon">&#xe1b1;</span>
                    </div>
                    <div>
                        <h4>Capacidad Libre del Equipo</h4>
                        <span class="insight-metric"><?= number_format((float) ($insFreeCap['idle_hours'] ?? 0), 1) ?>h libres de <?= number_format((float) ($insFreeCap['total_capacity'] ?? 0), 1) ?>h totales</span>
                    </div>
                </div>
                <div class="capacity-gauge-row">
                    <div class="capacity-gauge">
                        <div class="gauge-track">
                            <span class="gauge-fill gauge-used" style="width: <?= min(100, max(0, (float) ($insFreeCap['used_percentage'] ?? 0))) ?>%"></span>
                            <span class="gauge-fill gauge-free" style="width: <?= min(100, max(0, (float) ($insFreeCap['free_percentage'] ?? 0))) ?>%"></span>
                        </div>
                        <div class="gauge-labels">
                            <span class="gauge-label"><i class="dist-dot" style="background: var(--primary);"></i>Asignado: <?= number_format((float) ($insFreeCap['used_percentage'] ?? 0), 1) ?>%</span>
                            <span class="gauge-label"><i class="dist-dot" style="background: #22c55e;"></i>Libre: <?= number_format((float) ($insFreeCap['free_percentage'] ?? 0), 1) ?>%</span>
                        </div>
                    </div>
                    <?php $topFreeWeeks = $insFreeCap['top_free_weeks'] ?? []; ?>
                    <?php if (!empty($topFreeWeeks)): ?>
                        <div class="capacity-free-weeks">
                            <small class="section-muted">Semanas con mayor disponibilidad:</small>
                            <div class="free-week-chips">
                                <?php foreach ($topFreeWeeks as $fwKey => $fwHours): ?>
                                    <span class="free-week-chip"><?= htmlspecialchars((string) $fwKey) ?> · <?= number_format((float) $fwHours, 1) ?>h</span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="insight-interpretation"><?= htmlspecialchars((string) ($insFreeCap['interpretation'] ?? '')) ?></p>
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

/* ── Insights Panel ── */
.insights-panel { display: flex; flex-direction: column; gap: 16px; border: 1px solid var(--border); background: var(--surface); border-radius: 16px; padding: 20px; }
.insights-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.insight-badge { background: linear-gradient(135deg, rgba(99,102,241,.12), rgba(168,85,247,.12)); color: #6366f1; border: 1px solid rgba(99,102,241,.25); font-size: .78rem; padding: 4px 12px; border-radius: 999px; font-weight: 600; }
.insights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 14px; }
.insight-card { border: 1px solid var(--border); background: color-mix(in srgb, var(--surface) 94%, var(--background)); border-radius: 14px; padding: 16px; display: flex; flex-direction: column; gap: 12px; transition: box-shadow .2s ease, border-color .2s ease; }
.insight-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.06); border-color: rgba(99,102,241,.3); }
.insight-card-wide { grid-column: 1 / -1; }
.insight-card-header { display: flex; align-items: center; gap: 12px; }
.insight-icon-wrap { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: linear-gradient(135deg, rgba(99,102,241,.15), rgba(168,85,247,.1)); }
.insight-icon-weeks { background: linear-gradient(135deg, rgba(245,158,11,.15), rgba(251,191,36,.1)); }
.insight-icon-top { background: linear-gradient(135deg, rgba(239,68,68,.12), rgba(248,113,113,.1)); }
.insight-icon-available { background: linear-gradient(135deg, rgba(34,197,94,.15), rgba(74,222,128,.1)); }
.insight-icon-capacity { background: linear-gradient(135deg, rgba(59,130,246,.15), rgba(96,165,250,.1)); }
.insight-icon { font-size: 1.3rem; color: var(--text-primary); font-family: 'Material Icons', sans-serif; font-weight: normal; font-style: normal; }
.insight-card-header h4 { margin: 0; font-size: .95rem; color: var(--text-primary); }
.insight-metric { font-size: .82rem; color: var(--text-secondary); font-weight: 500; }

.insight-card.insight-critical { border-left: 3px solid #ef4444; }
.insight-card.insight-high { border-left: 3px solid #f59e0b; }
.insight-card.insight-optimal { border-left: 3px solid #22c55e; }
.insight-card.insight-moderate { border-left: 3px solid #60a5fa; }
.insight-card.insight-low { border-left: 3px solid #94a3b8; }
.insight-card.insight-idle { border-left: 3px solid #cbd5e1; }

.insight-interpretation { margin: 0; padding: 10px 12px; font-size: .85rem; line-height: 1.55; color: var(--text-secondary); background: color-mix(in srgb, var(--background) 60%, var(--surface)); border-radius: 10px; border: 1px solid rgba(148,163,184,.15); }

/* Distribution bar */
.insight-distribution { display: flex; flex-direction: column; gap: 6px; }
.dist-bar { display: flex; height: 10px; border-radius: 999px; overflow: hidden; border: 1px solid rgba(148,163,184,.2); }
.dist-segment { display: block; min-width: 2px; }
.dist-legend { display: flex; flex-wrap: wrap; gap: 8px; }
.dist-legend-item { display: inline-flex; align-items: center; gap: 4px; font-size: .78rem; color: var(--text-secondary); }
.dist-dot { width: 8px; height: 8px; border-radius: 999px; display: inline-block; flex-shrink: 0; }

/* Mini table (weeks) */
.insight-mini-table { display: flex; flex-direction: column; gap: 6px; }
.insight-mini-row { display: flex; align-items: center; gap: 8px; }
.insight-mini-label { font-size: .8rem; color: var(--text-secondary); min-width: 68px; flex-shrink: 0; font-weight: 500; }
.insight-mini-bar-track { flex: 1; height: 8px; background: color-mix(in srgb, var(--background) 92%, var(--surface)); border-radius: 999px; overflow: hidden; border: 1px solid rgba(148,163,184,.15); }
.insight-mini-bar-fill { display: block; height: 100%; border-radius: 999px; }
.bar-critical { background: rgba(239,68,68,.55); }
.bar-warning { background: rgba(245,158,11,.50); }
.bar-ok { background: rgba(34,197,94,.45); }
.bar-available { background: rgba(59,130,246,.45); }
.insight-mini-value { font-size: .8rem; font-weight: 600; min-width: 48px; text-align: right; color: var(--text-primary); }

/* Talent list */
.insight-talent-list { display: flex; flex-direction: column; gap: 6px; }
.insight-talent-row { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 10px; border: 1px solid rgba(148,163,184,.12); background: color-mix(in srgb, var(--surface) 96%, var(--background)); }
.insight-talent-rank { font-size: .78rem; font-weight: 700; color: var(--text-secondary); min-width: 24px; text-align: center; }
.insight-talent-info { display: flex; flex-direction: column; gap: 1px; min-width: 0; flex-shrink: 1; }
.insight-talent-info strong { font-size: .84rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.insight-talent-info small { font-size: .74rem; color: var(--text-secondary); }
.insight-talent-bar-wrap { flex: 1; min-width: 60px; }
.insight-talent-pct { font-size: .82rem; font-weight: 600; min-width: 50px; text-align: right; }
.pct-critical { color: #ef4444; }
.pct-warning { color: #d97706; }
.insight-talent-free { font-size: .82rem; font-weight: 600; min-width: 50px; text-align: right; color: #059669; }

/* Capacity gauge */
.capacity-gauge-row { display: flex; gap: 20px; align-items: stretch; flex-wrap: wrap; }
.capacity-gauge { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: 8px; }
.gauge-track { display: flex; height: 18px; border-radius: 999px; overflow: hidden; border: 1px solid rgba(148,163,184,.2); }
.gauge-used { background: var(--primary); opacity: .7; }
.gauge-free { background: #22c55e; opacity: .5; }
.gauge-labels { display: flex; gap: 14px; }
.gauge-label { display: inline-flex; align-items: center; gap: 5px; font-size: .8rem; color: var(--text-secondary); }
.capacity-free-weeks { display: flex; flex-direction: column; gap: 6px; }
.free-week-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.free-week-chip { padding: 4px 10px; border-radius: 999px; font-size: .78rem; font-weight: 500; background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.25); color: #15803d; }
</style>
