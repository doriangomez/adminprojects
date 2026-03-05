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
$insightsDiagnosis      = is_array($insights['diagnosis']         ?? null) ? $insights['diagnosis']         : [];
$insightsUtil           = is_array($insights['team_utilization']  ?? null) ? $insights['team_utilization']  : [];
$insightsPeakWeeks      = is_array($insights['peak_weeks']        ?? null) ? $insights['peak_weeks']        : [];
$insightsTopUtil        = is_array($insights['top_utilized']      ?? null) ? $insights['top_utilized']      : [];
$insightsAvailable      = is_array($insights['available_talents'] ?? null) ? $insights['available_talents'] : [];
$insightsFreeCapacity   = is_array($insights['free_capacity']     ?? null) ? $insights['free_capacity']     : [];

$diagnosisLevel = (string) ($insightsDiagnosis['level'] ?? 'under');
$diagnosisLabel = (string) ($insightsDiagnosis['label'] ?? '');
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

    <?php if (!empty($insights)): ?>
    <section class="insights-shell">
        <div class="insights-header">
            <div class="insights-header-text">
                <p class="eyebrow">Capa analítica</p>
                <h3>Insights automáticos del equipo</h3>
                <small class="section-muted">Interpretaciones generadas a partir del estado actual de carga y capacidad para facilitar la toma de decisiones.</small>
            </div>
            <span class="diagnosis-badge diagnosis-<?= htmlspecialchars($diagnosisLevel) ?>">
                <?= htmlspecialchars($diagnosisLabel) ?>
            </span>
        </div>

        <div class="insights-grid">

            <?php /* ── 1. Nivel de utilización del equipo ── */ ?>
            <?php if (!empty($insightsUtil)): ?>
            <article class="insight-card">
                <div class="insight-card-header">
                    <div class="insight-icon-wrap insight-icon-util">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    </div>
                    <div>
                        <h4 class="insight-title">Utilización del equipo</h4>
                        <span class="insight-status-badge util-status-<?= htmlspecialchars((string) ($insightsUtil['status'] ?? 'none')) ?>">
                            <?= htmlspecialchars((string) ($insightsUtil['label'] ?? '')) ?>
                        </span>
                    </div>
                    <strong class="insight-big-metric"><?= number_format((float) ($insightsUtil['value'] ?? 0), 1) ?>%</strong>
                </div>
                <p class="insight-interpretation"><?= htmlspecialchars((string) ($insightsUtil['interpretation'] ?? '')) ?></p>
                <div class="insight-recommendation">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    <?= htmlspecialchars((string) ($insightsUtil['recommendation'] ?? '')) ?>
                </div>
            </article>
            <?php endif; ?>

            <?php /* ── 2. Semanas con mayor carga ── */ ?>
            <?php if (!empty($insightsPeakWeeks)): ?>
            <article class="insight-card">
                <div class="insight-card-header">
                    <div class="insight-icon-wrap insight-icon-weeks">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div>
                        <h4 class="insight-title">Semanas con mayor carga</h4>
                        <?php $critCount = (int) ($insightsPeakWeeks['critical_count'] ?? 0); ?>
                        <span class="insight-status-badge <?= $critCount > 2 ? 'util-status-risk' : ($critCount > 0 ? 'util-status-balanced' : 'util-status-healthy') ?>">
                            <?= $critCount ?> semana<?= $critCount !== 1 ? 's' : '' ?> crítica<?= $critCount !== 1 ? 's' : '' ?>
                        </span>
                    </div>
                    <strong class="insight-big-metric"><?= (int) ($insightsPeakWeeks['high_count'] ?? 0) ?><small> ≥70%</small></strong>
                </div>
                <?php if (!empty($insightsPeakWeeks['top_weeks'])): ?>
                <div class="insight-week-chips">
                    <?php foreach (array_slice($insightsPeakWeeks['top_weeks'], 0, 5) as $w): ?>
                        <?php $wu = (float) ($w['utilization'] ?? 0); ?>
                        <span class="week-chip <?= $wu >= 90 ? 'week-chip-critical' : ($wu >= 70 ? 'week-chip-high' : 'week-chip-ok') ?>">
                            <?= htmlspecialchars((string) ($w['week'] ?? '')) ?> · <?= number_format($wu, 1) ?>%
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="insight-interpretation"><?= htmlspecialchars((string) ($insightsPeakWeeks['interpretation'] ?? '')) ?></p>
                <div class="insight-recommendation">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    <?= htmlspecialchars((string) ($insightsPeakWeeks['recommendation'] ?? '')) ?>
                </div>
            </article>
            <?php endif; ?>

            <?php /* ── 3. Talentos con mayor utilización ── */ ?>
            <?php if (!empty($insightsTopUtil)): ?>
            <article class="insight-card">
                <div class="insight-card-header">
                    <div class="insight-icon-wrap insight-icon-top">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div>
                        <h4 class="insight-title">Talentos más utilizados</h4>
                        <?php $olCount = (int) ($insightsTopUtil['overloaded_count'] ?? 0); $crCount = (int) ($insightsTopUtil['critical_count'] ?? 0); ?>
                        <span class="insight-status-badge <?= $olCount > 0 ? 'util-status-overload' : ($crCount > 0 ? 'util-status-risk' : 'util-status-healthy') ?>">
                            <?= $olCount > 0 ? "{$olCount} sobrecargado(s)" : ($crCount > 0 ? "{$crCount} en límite" : 'Sin alertas') ?>
                        </span>
                    </div>
                    <strong class="insight-big-metric"><?= $olCount + $crCount ?><small> alerta<?= ($olCount + $crCount) !== 1 ? 's' : '' ?></small></strong>
                </div>
                <?php if (!empty($insightsTopUtil['top_talents'])): ?>
                <div class="insight-talent-list">
                    <?php foreach ($insightsTopUtil['top_talents'] as $t): ?>
                        <?php $tu = (float) ($t['utilization'] ?? 0); ?>
                        <div class="insight-talent-row">
                            <span class="insight-talent-name"><?= htmlspecialchars((string) ($t['name'] ?? '')) ?></span>
                            <div class="insight-talent-bar-wrap">
                                <div class="insight-talent-bar <?= $tu > 100 ? 'ibar-overload' : ($tu >= 90 ? 'ibar-risk' : ($tu >= 70 ? 'ibar-healthy' : 'ibar-low')) ?>"
                                     style="width:<?= min(100, max(2, $tu)) ?>%"></div>
                            </div>
                            <span class="insight-talent-pct"><?= number_format($tu, 1) ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="insight-interpretation"><?= htmlspecialchars((string) ($insightsTopUtil['interpretation'] ?? '')) ?></p>
                <div class="insight-recommendation">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    <?= htmlspecialchars((string) ($insightsTopUtil['recommendation'] ?? '')) ?>
                </div>
            </article>
            <?php endif; ?>

            <?php /* ── 4. Talentos disponibles para asignación ── */ ?>
            <?php if (!empty($insightsAvailable)): ?>
            <article class="insight-card">
                <div class="insight-card-header">
                    <div class="insight-icon-wrap insight-icon-avail">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div>
                        <h4 class="insight-title">Disponibles para asignar</h4>
                        <span class="insight-status-badge <?= (int) ($insightsAvailable['available_count'] ?? 0) > 3 ? 'util-status-healthy' : ((int) ($insightsAvailable['available_count'] ?? 0) > 0 ? 'util-status-balanced' : 'util-status-risk') ?>">
                            <?= (int) ($insightsAvailable['available_count'] ?? 0) ?> talento<?= (int) ($insightsAvailable['available_count'] ?? 0) !== 1 ? 's' : '' ?> libre<?= (int) ($insightsAvailable['available_count'] ?? 0) !== 1 ? 's' : '' ?>
                        </span>
                    </div>
                    <strong class="insight-big-metric"><?= number_format((float) ($insightsAvailable['total_free_hours'] ?? 0), 0) ?><small>h</small></strong>
                </div>
                <?php if (!empty($insightsAvailable['available_talents'])): ?>
                <div class="insight-talent-list">
                    <?php foreach (array_slice($insightsAvailable['available_talents'], 0, 5) as $t): ?>
                        <div class="insight-talent-row">
                            <span class="insight-talent-name"><?= htmlspecialchars((string) ($t['name'] ?? '')) ?></span>
                            <span class="insight-avail-badge"><?= number_format((float) ($t['available'] ?? 0), 1) ?>h libres</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="insight-interpretation"><?= htmlspecialchars((string) ($insightsAvailable['interpretation'] ?? '')) ?></p>
                <div class="insight-recommendation">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    <?= htmlspecialchars((string) ($insightsAvailable['recommendation'] ?? '')) ?>
                </div>
            </article>
            <?php endif; ?>

            <?php /* ── 5. Capacidad libre del equipo ── */ ?>
            <?php if (!empty($insightsFreeCapacity)): ?>
            <article class="insight-card">
                <div class="insight-card-header">
                    <div class="insight-icon-wrap insight-icon-cap">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>
                    <div>
                        <h4 class="insight-title">Capacidad libre del equipo</h4>
                        <?php $fp = (float) ($insightsFreeCapacity['free_percent'] ?? 0); ?>
                        <span class="insight-status-badge <?= $fp < 10 ? 'util-status-risk' : ($fp < 25 ? 'util-status-balanced' : 'util-status-healthy') ?>">
                            <?= number_format($fp, 1) ?>% disponible
                        </span>
                    </div>
                    <strong class="insight-big-metric"><?= number_format((float) ($insightsFreeCapacity['idle_hours'] ?? 0), 0) ?><small>h</small></strong>
                </div>
                <div class="insight-cap-meter">
                    <div class="insight-cap-bar">
                        <?php
                            $totalCap   = (float) ($insightsFreeCapacity['total_capacity'] ?? 0);
                            $idleH      = (float) ($insightsFreeCapacity['idle_hours'] ?? 0);
                            $overH      = (float) ($insightsFreeCapacity['overassigned_hours'] ?? 0);
                            $usedH      = max(0, $totalCap - $idleH);
                            $usedPct    = $totalCap > 0 ? min(100, ($usedH  / $totalCap) * 100) : 0;
                            $overPct    = $totalCap > 0 ? min(100, ($overH  / $totalCap) * 100) : 0;
                            $idlePct    = $totalCap > 0 ? min(100, ($idleH  / $totalCap) * 100) : 0;
                        ?>
                        <div class="cap-used"   style="width:<?= round($usedPct, 1) ?>%"  title="Asignadas: <?= round($usedH, 1) ?>h"></div>
                        <div class="cap-over"   style="width:<?= round($overPct, 1) ?>%"  title="Sobreasignadas: <?= round($overH, 1) ?>h"></div>
                        <div class="cap-idle"   style="width:<?= round($idlePct, 1) ?>%"  title="Libres: <?= round($idleH, 1) ?>h"></div>
                    </div>
                    <div class="cap-meter-legend">
                        <span><i class="dot-sm cap-used-dot"></i> Asignadas <?= number_format($usedH, 1) ?>h</span>
                        <?php if ($overH > 0): ?>
                        <span><i class="dot-sm cap-over-dot"></i> Sobreasignadas <?= number_format($overH, 1) ?>h</span>
                        <?php endif; ?>
                        <span><i class="dot-sm cap-idle-dot"></i> Libres <?= number_format($idleH, 1) ?>h</span>
                    </div>
                </div>
                <p class="insight-interpretation"><?= htmlspecialchars((string) ($insightsFreeCapacity['interpretation'] ?? '')) ?></p>
                <div class="insight-recommendation">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    <?= htmlspecialchars((string) ($insightsFreeCapacity['recommendation'] ?? '')) ?>
                </div>
            </article>
            <?php endif; ?>

        </div>
    </section>
    <?php endif; ?>

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

/* ── Insights analytics layer ── */
.insights-shell { display: flex; flex-direction: column; gap: 14px; border: 1px solid var(--border); border-radius: 16px; padding: 18px; background: color-mix(in srgb, var(--surface) 60%, var(--background)); }

.insights-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.insights-header-text { display: flex; flex-direction: column; gap: 2px; }
.insights-header h3 { margin: 0; font-size: 1rem; }

.diagnosis-badge { border-radius: 999px; padding: 6px 14px; font-size: .82rem; font-weight: 600; white-space: nowrap; align-self: center; }
.diagnosis-healthy  { background: rgba(34, 197, 94, .15); color: #15803d; border: 1px solid rgba(34, 197, 94, .35); }
.diagnosis-balanced { background: rgba(59, 130, 246, .12); color: #1d4ed8; border: 1px solid rgba(59, 130, 246, .3); }
.diagnosis-warning  { background: rgba(245, 158, 11, .14); color: #92400e; border: 1px solid rgba(245, 158, 11, .35); }
.diagnosis-critical { background: rgba(239, 68, 68, .14); color: #b91c1c; border: 1px solid rgba(239, 68, 68, .35); }
.diagnosis-under    { background: rgba(148, 163, 184, .14); color: var(--text-secondary); border: 1px solid var(--border); }

.insights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; }

.insight-card { border: 1px solid var(--border); border-radius: 14px; background: var(--surface); padding: 14px; display: flex; flex-direction: column; gap: 10px; }

.insight-card-header { display: flex; align-items: flex-start; gap: 10px; }
.insight-icon-wrap { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.insight-icon-util  { background: rgba(99, 102, 241, .14); color: #6366f1; }
.insight-icon-weeks { background: rgba(245, 158, 11, .14); color: #d97706; }
.insight-icon-top   { background: rgba(239, 68, 68, .12); color: #dc2626; }
.insight-icon-avail { background: rgba(34, 197, 94, .14); color: #16a34a; }
.insight-icon-cap   { background: rgba(59, 130, 246, .12); color: #2563eb; }

.insight-card-header > div:nth-child(2) { display: flex; flex-direction: column; gap: 4px; flex: 1; }
.insight-title { margin: 0; font-size: .88rem; font-weight: 600; color: var(--text-primary); }

.insight-big-metric { margin-left: auto; font-size: 1.4rem; color: var(--primary); white-space: nowrap; }
.insight-big-metric small { font-size: .7rem; font-weight: 400; color: var(--text-secondary); }

.insight-status-badge { display: inline-block; border-radius: 999px; padding: 2px 8px; font-size: .75rem; font-weight: 600; }
.util-status-none     { background: rgba(148, 163, 184, .15); color: var(--text-secondary); }
.util-status-under    { background: rgba(148, 163, 184, .15); color: var(--text-secondary); }
.util-status-balanced { background: rgba(59, 130, 246, .12); color: #1d4ed8; }
.util-status-healthy  { background: rgba(34, 197, 94, .15); color: #15803d; }
.util-status-risk     { background: rgba(245, 158, 11, .15); color: #92400e; }
.util-status-overload { background: rgba(239, 68, 68, .13); color: #b91c1c; }

.insight-interpretation { margin: 0; font-size: .84rem; color: var(--text-secondary); line-height: 1.55; }

.insight-recommendation { display: flex; align-items: flex-start; gap: 6px; font-size: .82rem; color: var(--text-secondary); background: color-mix(in srgb, var(--background) 80%, var(--surface)); border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px; line-height: 1.5; }
.insight-recommendation svg { flex-shrink: 0; margin-top: 2px; opacity: .6; }

/* Week chips */
.insight-week-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.week-chip { border-radius: 999px; padding: 3px 9px; font-size: .76rem; font-weight: 600; border: 1px solid transparent; }
.week-chip-critical { background: rgba(239, 68, 68, .13); color: #b91c1c; border-color: rgba(239, 68, 68, .3); }
.week-chip-high     { background: rgba(245, 158, 11, .13); color: #92400e; border-color: rgba(245, 158, 11, .3); }
.week-chip-ok       { background: rgba(34, 197, 94, .12); color: #15803d; border-color: rgba(34, 197, 94, .3); }

/* Talent mini-list */
.insight-talent-list { display: flex; flex-direction: column; gap: 6px; }
.insight-talent-row { display: flex; align-items: center; gap: 8px; }
.insight-talent-name { font-size: .82rem; min-width: 100px; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-primary); flex-shrink: 0; }
.insight-talent-bar-wrap { flex: 1; height: 8px; border-radius: 999px; background: color-mix(in srgb, var(--background) 85%, var(--surface)); overflow: hidden; }
.insight-talent-bar { height: 100%; border-radius: 999px; transition: width .3s; }
.ibar-overload { background: rgba(239, 68, 68, .7); }
.ibar-risk     { background: rgba(245, 158, 11, .7); }
.ibar-healthy  { background: rgba(34, 197, 94, .65); }
.ibar-low      { background: rgba(148, 163, 184, .5); }
.insight-talent-pct { font-size: .78rem; font-weight: 600; min-width: 42px; text-align: right; color: var(--text-secondary); }
.insight-avail-badge { margin-left: auto; font-size: .76rem; font-weight: 600; background: rgba(34, 197, 94, .12); color: #15803d; border: 1px solid rgba(34, 197, 94, .28); border-radius: 999px; padding: 2px 8px; white-space: nowrap; }

/* Capacity bar meter */
.insight-cap-meter { display: flex; flex-direction: column; gap: 6px; }
.insight-cap-bar { display: flex; height: 14px; border-radius: 999px; overflow: hidden; background: color-mix(in srgb, var(--background) 85%, var(--surface)); border: 1px solid var(--border); }
.cap-used { background: rgba(99, 102, 241, .55); height: 100%; }
.cap-over  { background: rgba(239, 68, 68, .65); height: 100%; }
.cap-idle  { background: rgba(34, 197, 94, .45); height: 100%; }
.cap-meter-legend { display: flex; flex-wrap: wrap; gap: 10px; }
.cap-meter-legend span { display: inline-flex; align-items: center; gap: 5px; font-size: .76rem; color: var(--text-secondary); }
.dot-sm { width: 8px; height: 8px; border-radius: 999px; display: inline-block; }
.cap-used-dot { background: rgba(99, 102, 241, .65); }
.cap-over-dot { background: rgba(239, 68, 68, .75); }
.cap-idle-dot { background: rgba(34, 197, 94, .6); }
</style>
