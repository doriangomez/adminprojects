<?php
$basePath = $basePath ?? '';
$filters = is_array($filters ?? null) ? $filters : [];
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$talents = is_array($dashboard['talents'] ?? null) ? $dashboard['talents'] : [];
$summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
$criticalTalents = is_array($dashboard['critical_talents'] ?? $summary['critical_talents'] ?? null) ? ($dashboard['critical_talents'] ?? $summary['critical_talents']) : [];
$availableTalents = is_array($dashboard['available_talents'] ?? $summary['available_talents'] ?? null) ? ($dashboard['available_talents'] ?? $summary['available_talents']) : [];
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

// Mapa de status a clase visual: blanco / verde / amarillo / rojo
$heatmapClass = static function (string $status): string {
    return match ($status) {
        'overload' => 'heat-red',
        'risk' => 'heat-yellow',
        'healthy', 'balanced', 'under' => 'heat-green',
        default => 'heat-white',
    };
};

// Ranking: talentos ordenados por utilización (mayor primero)
$rankingTalents = $talents;
usort($rankingTalents, static function ($a, $b) {
    $ua = (float) (end($a['monthly'] ?? []) ?: ['utilization' => 0])['utilization'];
    $ub = (float) (end($b['monthly'] ?? []) ?: ['utilization' => 0])['utilization'];
    return (int) ($ub <=> $ua);
});

$heatmapLegend = [
    'heat-white' => 'Sin carga',
    'heat-green' => 'Saludable',
    'heat-yellow' => 'Alto (90-100%)',
    'heat-red' => 'Sobrecarga',
];

// Semanas con riesgo: al menos un talento en amarillo o rojo
$riskWeeks = [];
foreach ($columns as $key => $label) {
    foreach ($talents as $talent) {
        $source = $granularity === 'day' ? ($talent['daily'] ?? []) : ($talent['weekly'] ?? []);
        $cell = $source[$key] ?? ['status' => 'none'];
        $st = (string) ($cell['status'] ?? 'none');
        if ($st === 'risk' || $st === 'overload') {
            $riskWeeks[$key] = $label;
            break;
        }
    }
}
?>

<section class="capacity-shell">
    <header class="capacity-header">
        <div>
            <p class="eyebrow">Módulo visual</p>
            <h2>Gestión Visual de Carga y Capacidad del Talento</h2>
            <small class="section-muted">Vista ejecutiva: quién está sobrecargado, quién libre, dónde hay capacidad y qué semanas tienen riesgo.</small>
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
        <article class="kpi-card kpi-risk"><strong><?= (int) ($summary['risk_talents'] ?? 0) ?></strong><span>Talentos críticos (&gt;90%)</span></article>
        <article class="kpi-card kpi-available"><strong><?= number_format((float) ($summary['idle_capacity'] ?? 0), 1) ?>h</strong><span>Capacidad disponible</span></article>
    </section>

    <div class="capacity-quick-scan">
        <section class="capacity-block capacity-critical">
            <div class="section-title"><h3>⚠️ Talentos críticos (carga &gt;90%)</h3></div>
            <?php if (empty($criticalTalents)): ?>
                <p class="section-muted">Ningún talento con carga crítica.</p>
            <?php else: ?>
                <ul class="critical-list">
                    <?php foreach ($criticalTalents as $ct): ?>
                        <li>
                            <strong><?= htmlspecialchars((string) ($ct['name'] ?? '')) ?></strong>
                            <span class="role-tag"><?= htmlspecialchars((string) ($ct['role'] ?? '')) ?></span>
                            <span class="util-badge util-overload"><?= number_format((float) ($ct['utilization'] ?? 0), 0) ?>%</span>
                            <small><?= number_format((float) ($ct['hours'] ?? 0), 1) ?>h / <?= number_format((float) ($ct['capacity'] ?? 0), 1) ?>h</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <section class="capacity-block capacity-available">
            <div class="section-title"><h3>✓ Capacidad disponible del equipo</h3></div>
            <?php if (empty($availableTalents)): ?>
                <p class="section-muted">No hay capacidad ociosa significativa (&lt;70% utilización).</p>
            <?php else: ?>
                <ul class="available-list">
                    <?php foreach (array_slice($availableTalents, 0, 10) as $at): ?>
                        <li>
                            <strong><?= htmlspecialchars((string) ($at['name'] ?? '')) ?></strong>
                            <span class="role-tag"><?= htmlspecialchars((string) ($at['role'] ?? '')) ?></span>
                            <span class="util-badge util-free"><?= number_format((float) ($at['available_hours'] ?? 0), 1) ?>h libres</span>
                            <small><?= number_format((float) ($at['utilization'] ?? 0), 0) ?>% usado</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($availableTalents) > 10): ?>
                    <p class="section-muted">+<?= count($availableTalents) - 10 ?> más con capacidad disponible.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>

    <section class="capacity-block">
        <div class="section-title"><h3>Ranking de carga del equipo</h3></div>
        <div class="ranking-bars">
            <?php foreach ($rankingTalents as $idx => $talent): ?>
                <?php $latestMonth = end($talent['monthly']) ?: ['hours' => 0, 'capacity' => 0, 'utilization' => 0, 'status' => 'none']; ?>
                <?php $util = (float) ($latestMonth['utilization'] ?? 0); ?>
                <?php $heatClass = $heatmapClass((string) ($latestMonth['status'] ?? 'none')); ?>
                <div class="ranking-row">
                    <span class="rank-num"><?= $idx + 1 ?></span>
                    <span class="rank-name"><?= htmlspecialchars((string) ($talent['name'] ?? '')) ?></span>
                    <div class="rank-track">
                        <span class="rank-fill <?= $heatClass ?>" style="width: <?= min(100, max(0, $util)) ?>%"></span>
                    </div>
                    <span class="rank-pct"><?= number_format($util, 0) ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="capacity-block">
        <div class="section-title">
            <h3>Heatmap de carga (semanas con riesgo en amarillo/rojo)</h3>
            <?php if (!empty($riskWeeks)): ?>
                <span class="risk-weeks-badge">Semanas con riesgo: <?= htmlspecialchars(implode(', ', $riskWeeks)) ?></span>
            <?php endif; ?>
        </div>
        <div class="heatmap-table-wrap">
            <table class="heatmap-table heatmap-visual">
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
                            <?php $hClass = $heatmapClass((string) ($cell['status'] ?? 'none')); ?>
                            <td class="heat-cell <?= $hClass ?>" title="<?= number_format((float) ($cell['utilization'] ?? 0), 1) ?>%">
                                <?= number_format((float) ($cell['utilization'] ?? 0), 0) ?>%
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="legend">
            <?php foreach ($heatmapLegend as $cls => $label): ?>
                <span class="legend-item"><i class="dot <?= htmlspecialchars($cls) ?>"></i><?= htmlspecialchars($label) ?></span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="capacity-block">
        <div class="section-title"><h3>Capacidad mensual (vista compacta)</h3></div>
        <div class="capacity-compact-grid">
            <?php foreach ($talents as $talent): ?>
                <?php $latestMonth = end($talent['monthly']) ?: ['hours' => 0, 'capacity' => 0, 'utilization' => 0, 'status' => 'none']; ?>
                <?php $util = (float) ($latestMonth['utilization'] ?? 0); ?>
                <?php $heatClass = $heatmapClass((string) ($latestMonth['status'] ?? 'none')); ?>
                <div class="compact-bar-item">
                    <span class="compact-name"><?= htmlspecialchars((string) ($talent['name'] ?? '')) ?></span>
                    <div class="compact-track">
                        <span class="compact-fill <?= $heatClass ?>" style="width: <?= min(100, max(0, $util)) ?>%"></span>
                    </div>
                    <span class="compact-pct"><?= number_format($util, 0) ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</section>

<style>
.capacity-shell{display:flex;flex-direction:column;gap:18px}
.capacity-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.capacity-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;padding:14px;border:1px solid var(--border);background:var(--surface);border-radius:14px}
.capacity-filters label{display:flex;flex-direction:column;gap:6px;color:var(--text-secondary);font-size:.9rem}
.capacity-filters input,.capacity-filters select{background:var(--background);border:1px solid var(--border);border-radius:10px;padding:10px;color:var(--text-primary)}
.capacity-filters .actions{display:flex;align-items:end}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.kpi-card{border:1px solid var(--border);background:var(--surface);border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:8px}
.kpi-card strong{font-size:1.6rem;color:var(--primary)}
.kpi-card.kpi-risk strong{color:var(--danger)}
.kpi-card.kpi-available strong{color:var(--success)}
.capacity-quick-scan{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:900px){.capacity-quick-scan{grid-template-columns:1fr}}
@media(max-width:640px){.ranking-row{font-size:.8rem;gap:6px}.compact-bar-item{grid-template-columns:1fr 60px 36px;font-size:.78rem}}
.capacity-block{border:1px solid var(--border);background:var(--surface);border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:12px}
.capacity-block .section-title{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
.capacity-block .section-title h3{margin:0}
.risk-weeks-badge{font-size:.8rem;color:var(--danger);font-weight:600;padding:4px 10px;background:color-mix(in srgb,var(--danger) 12%,var(--surface));border-radius:8px;border:1px solid color-mix(in srgb,var(--danger) 30%,var(--border))}
.capacity-critical{border-left:4px solid var(--danger)}
.capacity-available{border-left:4px solid var(--success)}
.critical-list,.available-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
.critical-list li,.available-list li{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;background:color-mix(in srgb,var(--background) 95%,var(--surface));font-size:.9rem}
.critical-list li{background:color-mix(in srgb,var(--danger) 8%,var(--surface) 92%)}
.available-list li{background:color-mix(in srgb,var(--success) 8%,var(--surface) 92%)}
.role-tag{font-size:.75rem;color:var(--text-secondary);padding:2px 6px;background:color-mix(in srgb,var(--border) 30%,transparent);border-radius:6px}
.util-badge{font-weight:700;font-size:.85rem;padding:2px 8px;border-radius:6px}
.util-overload{background:var(--danger);color:#fff}
.util-free{background:var(--success);color:#fff}
.ranking-bars{display:flex;flex-direction:column;gap:6px}
.ranking-row{display:grid;grid-template-columns:28px minmax(100px,1fr) minmax(120px,2fr) 50px;align-items:center;gap:10px;font-size:.88rem}
.rank-num{color:var(--text-secondary);font-weight:600}
.rank-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rank-track{height:10px;background:color-mix(in srgb,var(--background) 92%,var(--surface));border-radius:999px;overflow:hidden;min-width:80px}
.rank-fill{height:100%;display:block;border-radius:999px;transition:width .2s ease}
.rank-pct{font-weight:600;text-align:right}
.heatmap-table-wrap{overflow:auto}
.heatmap-table{width:max-content;min-width:100%;border-collapse:separate;border-spacing:4px}
.heatmap-table th,.heatmap-table td{padding:8px 10px;text-align:center;font-size:.82rem;border-radius:8px}
.heatmap-table th{background:color-mix(in srgb,var(--surface) 70%,var(--background));position:sticky;left:0;z-index:2}
.heatmap-table td{min-width:56px;font-weight:600}
.heat-cell.heat-white{background:#fff;color:var(--text-secondary);border:1px solid color-mix(in srgb,var(--border) 60%,transparent)}
.heat-cell.heat-green{background:#22c55e;color:#fff}
.heat-cell.heat-yellow{background:#eab308;color:#1a1a1a}
.heat-cell.heat-red{background:#ef4444;color:#fff}
.legend{display:flex;flex-wrap:wrap;gap:12px}
.legend-item{display:inline-flex;align-items:center;gap:6px;font-size:.82rem;color:var(--text-secondary)}
.dot{width:12px;height:12px;border-radius:999px;display:inline-block}
.dot.heat-white{background:#fff;border:1px solid var(--border)}
.dot.heat-green{background:#22c55e}
.dot.heat-yellow{background:#eab308}
.dot.heat-red{background:#ef4444}
.capacity-compact-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px}
.compact-bar-item{display:grid;grid-template-columns:1fr 80px 40px;align-items:center;gap:8px;font-size:.82rem}
.compact-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.compact-track{height:8px;background:color-mix(in srgb,var(--background) 92%,var(--surface));border-radius:999px;overflow:hidden}
.compact-fill{height:100%;display:block;border-radius:999px}
.compact-pct{font-weight:600;text-align:right}
</style>
