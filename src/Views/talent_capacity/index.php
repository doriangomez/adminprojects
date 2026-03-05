<?php
$basePath    = $basePath ?? '';
$filters     = is_array($filters ?? null) ? $filters : [];
$dashboard   = is_array($dashboard ?? null) ? $dashboard : [];
$talents     = is_array($dashboard['talents'] ?? null) ? $dashboard['talents'] : [];
$summary     = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
$options     = is_array($dashboard['filter_options'] ?? null) ? $dashboard['filter_options'] : [];
$range       = is_array($dashboard['range'] ?? null) ? $dashboard['range'] : ['start' => date('Y-m-01'), 'end' => date('Y-m-t')];
$granularity = $filters['heatmap_granularity'] ?? 'week';

$columns = [];
$cursor  = new DateTimeImmutable($range['start']);
$limit   = new DateTimeImmutable($range['end']);
while ($cursor <= $limit) {
    if ((int) $cursor->format('N') > 5) {
        $cursor = $cursor->modify('+1 day');
        continue;
    }
    if ($granularity === 'day') {
        $key = $cursor->format('Y-m-d');
        $columns[$key] = $cursor->format('d M');
    } else {
        $key = $cursor->format('o-\WW');
        $columns[$key] = 'Sem ' . $cursor->format('W');
    }
    $cursor = $cursor->modify('+1 day');
}
$columns = array_unique($columns);

$statusLabel = [
    'overload' => 'Sobrecarga',
    'risk'     => 'Alto',
    'healthy'  => 'Saludable',
    'balanced' => 'En rango',
    'under'    => 'Bajo',
    'none'     => 'Sin carga',
];

$monthNames = [
    '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
    '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
    '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic',
];

// Enrich each talent with computed aggregates
$talentsEnriched = array_map(function ($t) {
    $monthly   = $t['monthly'] ?? [];
    $utils     = array_map('floatval', array_column($monthly, 'utilization'));
    $avgUtil   = count($utils) ? array_sum($utils) / count($utils) : 0;
    $lastKey   = !empty($monthly) ? array_key_last($monthly) : null;
    $lastMonth = $lastKey !== null
        ? $monthly[$lastKey]
        : ['hours' => 0, 'capacity' => 0, 'utilization' => 0, 'status' => 'none'];
    $freeHours = max(0, (float)($lastMonth['capacity'] ?? 0) - (float)($lastMonth['hours'] ?? 0));
    $u = $avgUtil;
    $derivedStatus = $u >= 100 ? 'overload'
        : ($u >= 90  ? 'risk'
        : ($u >= 70  ? 'healthy'
        : ($u >= 60  ? 'balanced'
        : ($u > 0    ? 'under' : 'none'))));
    return $t + [
        '_avg_util'       => $avgUtil,
        '_last_month'     => $lastMonth,
        '_free_hours'     => $freeHours,
        '_derived_status' => $derivedStatus,
    ];
}, $talents);

// Ranked: highest utilization first
$ranked = $talentsEnriched;
usort($ranked, function ($a, $b) { return $b['_avg_util'] <=> $a['_avg_util']; });

// Critical talents: avg utilization >= 90%
$critical = array_values(array_filter($talentsEnriched, function ($t) { return $t['_avg_util'] >= 90; }));
usort($critical, function ($a, $b) { return $b['_avg_util'] <=> $a['_avg_util']; });

// Available: most free hours first
$byAvail = $talentsEnriched;
usort($byAvail, function ($a, $b) { return $b['_free_hours'] <=> $a['_free_hours']; });
?>

<section class="cap-shell">

    <header class="cap-header">
        <div>
            <p class="eyebrow">Módulo visual</p>
            <h2>Gestión Visual de Carga y Capacidad del Talento</h2>
            <small class="section-muted">Vista ejecutiva: sobrecarga, disponibilidad y balance del equipo en tiempo real.</small>
        </div>
        <span class="badge neutral">Vista ejecutiva</span>
    </header>

    <form method="GET" class="cap-filters">
        <label>Área
            <select name="area">
                <option value="">Todas</option>
                <?php foreach (($options['areas'] ?? []) as $area): ?>
                    <?php $code = (string)($area['code'] ?? ''); ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= ($filters['area'] ?? '') === $code ? 'selected' : '' ?>><?= htmlspecialchars($code) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Proyecto
            <select name="project_id">
                <option value="0">Todos</option>
                <?php foreach (($options['projects'] ?? []) as $project): ?>
                    <option value="<?= (int)$project['id'] ?>" <?= ((int)($filters['project_id'] ?? 0) === (int)$project['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)($project['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Rol
            <select name="role">
                <option value="">Todos</option>
                <?php foreach (($options['roles'] ?? []) as $role): ?>
                    <?php $rn = (string)($role['role'] ?? ''); ?>
                    <option value="<?= htmlspecialchars($rn) ?>" <?= ($filters['role'] ?? '') === $rn ? 'selected' : '' ?>><?= htmlspecialchars($rn) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Desde
            <input type="date" name="date_from" value="<?= htmlspecialchars((string)($filters['date_from'] ?? $range['start'])) ?>">
        </label>
        <label>Hasta
            <input type="date" name="date_to" value="<?= htmlspecialchars((string)($filters['date_to'] ?? $range['end'])) ?>">
        </label>
        <label>Heatmap
            <select name="heatmap_granularity">
                <option value="week" <?= $granularity === 'week' ? 'selected' : '' ?>>Semanas</option>
                <option value="day" <?= $granularity === 'day' ? 'selected' : '' ?>>Días</option>
            </select>
        </label>
        <div class="cap-filter-btn">
            <button type="submit" class="action-btn primary">Aplicar filtros</button>
        </div>
    </form>

    <!-- KPI Summary -->
    <section class="kpi-row">
        <article class="kpi-card">
            <svg class="kpi-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
            <div class="kpi-body">
                <strong><?= number_format((float)($summary['avg_team_utilization'] ?? 0), 1) ?>%</strong>
                <span>Utilización promedio del equipo</span>
            </div>
        </article>
        <article class="kpi-card kpi-danger">
            <svg class="kpi-icon" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div class="kpi-body">
                <strong><?= number_format((float)($summary['overassigned_hours'] ?? 0), 1) ?>h</strong>
                <span>Horas sobreasignadas</span>
            </div>
        </article>
        <article class="kpi-card kpi-warning">
            <svg class="kpi-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            <div class="kpi-body">
                <strong><?= (int)($summary['risk_talents'] ?? 0) ?></strong>
                <span>Talentos en riesgo (+90%)</span>
            </div>
        </article>
        <article class="kpi-card kpi-success">
            <svg class="kpi-icon" viewBox="0 0 24 24"><rect x="3" y="12" width="5" height="9" rx="1"/><rect x="10" y="7" width="5" height="14" rx="1"/><rect x="17" y="3" width="5" height="18" rx="1"/></svg>
            <div class="kpi-body">
                <strong><?= number_format((float)($summary['idle_capacity'] ?? 0), 1) ?>h</strong>
                <span>Capacidad ociosa global</span>
            </div>
        </article>
    </section>

    <!-- Critical Talents Alert -->
    <?php if (!empty($critical)): ?>
    <section class="critical-section">
        <div class="critical-header">
            <svg class="critical-icon-svg" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div>
                <strong>Talentos Críticos — Carga superior al 90%</strong>
                <small><?= count($critical) ?> talento<?= count($critical) !== 1 ? 's' : '' ?> requieren atención inmediata</small>
            </div>
        </div>
        <div class="critical-list">
            <?php foreach ($critical as $ct): ?>
                <?php
                    $cUtil   = (float)$ct['_avg_util'];
                    $cStatus = $ct['_derived_status'];
                    $lm      = $ct['_last_month'];
                    $lmCap   = (float)($lm['capacity'] ?? 0);
                    $lmHrs   = (float)($lm['hours'] ?? 0);
                ?>
                <div class="critical-item">
                    <span class="critical-name"><?= htmlspecialchars((string)($ct['name'] ?? '')) ?></span>
                    <div class="critical-bar-wrap">
                        <div class="critical-bar cbar-<?= $cStatus ?>" style="width:<?= min(100, $cUtil) ?>%"></div>
                    </div>
                    <span class="critical-pct"><?= number_format($cUtil, 0) ?>%</span>
                    <span class="critical-badge cbadge-<?= $cStatus ?>"><?= $statusLabel[$cStatus] ?? $cStatus ?></span>
                    <?php if ($lmCap > 0): ?>
                        <span class="critical-detail"><?= number_format($lmHrs, 0) ?>h / <?= number_format($lmCap, 0) ?>h</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Heatmap Visual -->
    <section class="cap-block">
        <div class="cap-block-header">
            <h3>Heatmap de carga <?= $granularity === 'day' ? 'diaria' : 'semanal' ?></h3>
            <div class="hm-legend">
                <?php foreach (['none' => 'Sin carga', 'under' => 'Bajo', 'balanced' => 'En rango', 'healthy' => 'Saludable', 'risk' => 'Alto', 'overload' => 'Sobrecarga'] as $lk => $lv): ?>
                    <span class="hm-legend-item"><i class="hm-dot hm-<?= $lk ?>"></i><?= $lv ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="heatmap-scroll">
            <table class="heatmap-tbl">
                <thead>
                    <tr>
                        <th class="hm-name-col">Talento</th>
                        <?php foreach ($columns as $label): ?>
                            <th><?= htmlspecialchars($label) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($talents as $talent): ?>
                        <?php $source = $granularity === 'day' ? ($talent['daily'] ?? []) : ($talent['weekly'] ?? []); ?>
                        <tr>
                            <th class="hm-name-col"><?= htmlspecialchars((string)($talent['name'] ?? '')) ?></th>
                            <?php foreach ($columns as $key => $label): ?>
                                <?php
                                    $cell   = $source[$key] ?? ['utilization' => 0, 'status' => 'none'];
                                    $util   = (float)($cell['utilization'] ?? 0);
                                    $status = (string)($cell['status'] ?? 'none');
                                    $tip    = htmlspecialchars((string)($talent['name'] ?? '')) . ' · ' . $label . ': ' . number_format($util, 1) . '%';
                                ?>
                                <td class="hm-cell hm-<?= $status ?>" title="<?= $tip ?>">
                                    <span class="hm-pct"><?= $util > 0 ? number_format($util, 0) . '%' : '' ?></span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($talents)): ?>
                        <tr><td colspan="100" class="empty-state">Sin datos para el período seleccionado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Ranking + Available Capacity (two columns) -->
    <div class="two-col-grid">

        <section class="cap-block">
            <div class="cap-block-header">
                <h3>Ranking de carga del equipo</h3>
                <small class="section-muted">Mayor a menor utilización</small>
            </div>
            <div class="ranking-list">
                <?php foreach ($ranked as $i => $talent): ?>
                    <?php
                        $avgUtil = (float)$talent['_avg_util'];
                        $status  = $talent['_derived_status'];
                    ?>
                    <div class="rank-row">
                        <span class="rank-pos"><?= $i + 1 ?></span>
                        <span class="rank-name"><?= htmlspecialchars((string)($talent['name'] ?? '')) ?></span>
                        <div class="rank-track">
                            <div class="rank-fill hm-<?= $status ?>" style="width:<?= min(100, max(0, $avgUtil)) ?>%"></div>
                        </div>
                        <span class="rank-pct"><?= number_format($avgUtil, 0) ?>%</span>
                        <span class="rank-badge rank-badge-<?= $status ?>"><?= $statusLabel[$status] ?? $status ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($ranked)): ?>
                    <p class="empty-state">Sin datos disponibles.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="cap-block">
            <div class="cap-block-header">
                <h3>Capacidad disponible del equipo</h3>
                <small class="section-muted">Horas libres en el período</small>
            </div>
            <div class="avail-list">
                <?php foreach ($byAvail as $talent): ?>
                    <?php
                        $lm      = $talent['_last_month'];
                        $cap     = (float)($lm['capacity'] ?? 0);
                        $hrs     = (float)($lm['hours'] ?? 0);
                        $free    = $talent['_free_hours'];
                        $usedPct = $cap > 0 ? min(100, ($hrs / $cap) * 100) : 0;
                        $status  = $talent['_derived_status'];
                    ?>
                    <div class="avail-row">
                        <span class="avail-name"><?= htmlspecialchars((string)($talent['name'] ?? '')) ?></span>
                        <div class="avail-track">
                            <div class="avail-used hm-<?= $status ?>" style="width:<?= $usedPct ?>%"></div>
                        </div>
                        <span class="avail-free <?= $free > 0 ? 'free-positive' : 'free-zero' ?>"><?= number_format($free, 0) ?>h libres</span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($byAvail)): ?>
                    <p class="empty-state">Sin datos de capacidad.</p>
                <?php endif; ?>
            </div>
        </section>

    </div>

    <!-- Monthly Compact View -->
    <section class="cap-block">
        <div class="cap-block-header">
            <h3>Resumen mensual compacto</h3>
            <small class="section-muted">Evolución de carga por talento y mes</small>
        </div>
        <div class="monthly-grid">
            <?php foreach ($talentsEnriched as $talent): ?>
                <?php $monthly = $talent['monthly'] ?? []; ?>
                <?php if (empty($monthly)) continue; ?>
                <div class="mc-card">
                    <div class="mc-name"><?= htmlspecialchars((string)($talent['name'] ?? '')) ?></div>
                    <?php foreach ($monthly as $mKey => $mData): ?>
                        <?php
                            $mUtil   = (float)($mData['utilization'] ?? 0);
                            $mStatus = (string)($mData['status'] ?? 'none');
                            $mParts  = explode('-', $mKey);
                            $mLabel  = ($monthNames[$mParts[1] ?? '01'] ?? $mKey);
                            if (isset($mParts[0])) $mLabel .= " '" . substr($mParts[0], 2);
                        ?>
                        <div class="mc-row" title="<?= htmlspecialchars($mLabel) ?>: <?= number_format($mUtil, 1) ?>%">
                            <span class="mc-month-label"><?= htmlspecialchars($mLabel) ?></span>
                            <div class="mc-bar-track">
                                <div class="mc-bar-fill hm-<?= $mStatus ?>" style="width:<?= min(100, max(0, $mUtil)) ?>%"></div>
                            </div>
                            <span class="mc-pct"><?= number_format($mUtil, 0) ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <?php if (empty($talentsEnriched)): ?>
                <p class="empty-state">Sin datos mensuales disponibles.</p>
            <?php endif; ?>
        </div>
    </section>

</section>

<style>
/* ── Shell & Header ─────────────────────────────────────────── */
.cap-shell{display:flex;flex-direction:column;gap:20px}
.cap-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}

/* ── Filters ─────────────────────────────────────────────────── */
.cap-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(158px,1fr));gap:12px;padding:14px;border:1px solid var(--border);background:var(--surface);border-radius:14px}
.cap-filters label{display:flex;flex-direction:column;gap:5px;font-size:.88rem;color:var(--text-secondary)}
.cap-filters input,.cap-filters select{background:var(--background);border:1px solid var(--border);border-radius:8px;padding:8px 10px;color:var(--text-primary);font-size:.88rem}
.cap-filter-btn{display:flex;align-items:flex-end}

/* ── KPI Row ─────────────────────────────────────────────────── */
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.kpi-card{display:flex;align-items:center;gap:14px;padding:16px 18px;background:var(--surface);border:1px solid var(--border);border-left:4px solid var(--border);border-radius:14px}
.kpi-card.kpi-danger{border-left-color:#ef4444}
.kpi-card.kpi-warning{border-left-color:#f59e0b}
.kpi-card.kpi-success{border-left-color:#22c55e}
.kpi-icon{width:26px;height:26px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;color:var(--text-secondary)}
.kpi-danger .kpi-icon{color:#ef4444}
.kpi-warning .kpi-icon{color:#f59e0b}
.kpi-success .kpi-icon{color:#22c55e}
.kpi-body{display:flex;flex-direction:column;gap:2px}
.kpi-body strong{font-size:1.5rem;line-height:1.1;color:var(--text-primary);font-weight:700}
.kpi-body span{font-size:.78rem;color:var(--text-secondary)}

/* ── Critical Section ────────────────────────────────────────── */
.critical-section{border-radius:14px;border:1.5px solid #fca5a5;background:#fef2f2;padding:16px 18px;display:flex;flex-direction:column;gap:14px}
.critical-header{display:flex;align-items:flex-start;gap:12px}
.critical-icon-svg{width:22px;height:22px;flex-shrink:0;stroke:#dc2626;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;margin-top:2px}
.critical-header strong{color:#991b1b;font-size:.95rem;display:block}
.critical-header small{color:#b91c1c;font-size:.8rem}
.critical-list{display:flex;flex-direction:column;gap:9px}
.critical-item{display:grid;align-items:center;gap:10px;grid-template-columns:1fr 2fr 46px 78px auto}
.critical-name{font-weight:600;font-size:.88rem;color:#7f1d1d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.critical-bar-wrap{height:10px;background:#fee2e2;border-radius:999px;overflow:hidden}
.critical-bar{height:100%;border-radius:999px}
.cbar-overload{background:#ef4444}
.cbar-risk{background:#f59e0b}
.critical-pct{font-weight:700;font-size:.9rem;color:#dc2626;text-align:right}
.critical-badge{padding:2px 10px;border-radius:999px;font-size:.72rem;font-weight:600;text-align:center;white-space:nowrap}
.cbadge-overload{background:#fecaca;color:#991b1b}
.cbadge-risk{background:#fef3c7;color:#92400e}
.critical-detail{font-size:.75rem;color:#b91c1c;white-space:nowrap}

/* ── Generic Block Card ──────────────────────────────────────── */
.cap-block{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:14px}
.cap-block-header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.cap-block-header h3{margin:0;font-size:1rem;color:var(--text-primary);font-weight:600}

/* ── Heatmap ─────────────────────────────────────────────────── */
.heatmap-scroll{overflow-x:auto}
.heatmap-tbl{width:max-content;min-width:100%;border-collapse:separate;border-spacing:3px}
.heatmap-tbl thead th{font-size:.72rem;font-weight:600;color:var(--text-secondary);padding:4px 8px;text-align:center;background:transparent;border-radius:0}
.hm-name-col{text-align:left!important;min-width:130px;padding:6px 10px!important;position:sticky;left:0;z-index:2;background:var(--surface);font-weight:600;font-size:.83rem;color:var(--text-primary);border-radius:8px!important}
.hm-cell{width:60px;min-width:60px;height:40px;vertical-align:middle;cursor:default;border-radius:8px;transition:transform .12s,box-shadow .12s;position:relative}
.hm-cell:hover{transform:scale(1.1);z-index:5;box-shadow:0 3px 10px rgba(0,0,0,.18)}
.hm-pct{display:block;font-size:.71rem;font-weight:700;line-height:1;text-align:center}

/* ── Shared status color classes (heatmap, bars, badges) ─────── */
/* white  = sin carga  */
.hm-none    {background:#f3f4f6;color:#9ca3af}
/* light green = bajo  */
.hm-under   {background:#dcfce7;color:#15803d}
/* medium green = en rango */
.hm-balanced{background:#86efac;color:#14532d}
/* green = saludable   */
.hm-healthy {background:#22c55e;color:#fff}
/* yellow/amber = alto */
.hm-risk    {background:#fbbf24;color:#78350f}
/* red = sobrecarga    */
.hm-overload{background:#ef4444;color:#fff}

/* ── Legend ─────────────────────────────────────────────────── */
.hm-legend{display:flex;flex-wrap:wrap;gap:10px}
.hm-legend-item{display:inline-flex;align-items:center;gap:5px;font-size:.75rem;color:var(--text-secondary)}
.hm-dot{width:14px;height:14px;border-radius:4px;display:inline-block;flex-shrink:0;border:1px solid rgba(0,0,0,.08)}

/* ── Two-col grid ────────────────────────────────────────────── */
.two-col-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:820px){.two-col-grid{grid-template-columns:1fr}}

/* ── Ranking ─────────────────────────────────────────────────── */
.ranking-list{display:flex;flex-direction:column;gap:7px}
.rank-row{display:grid;align-items:center;gap:8px;grid-template-columns:22px minmax(90px,1fr) 2fr 42px 78px}
.rank-pos{font-size:.75rem;font-weight:700;color:var(--text-secondary);text-align:center}
.rank-name{font-size:.83rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rank-track{height:12px;background:var(--border);border-radius:999px;overflow:hidden}
.rank-fill{height:100%;border-radius:999px;transition:width .3s}
.rank-pct{font-size:.82rem;font-weight:700;text-align:right;color:var(--text-primary)}
.rank-badge{padding:2px 8px;border-radius:999px;font-size:.7rem;font-weight:600;text-align:center;white-space:nowrap}
.rank-badge-overload{background:#fecaca;color:#991b1b}
.rank-badge-risk{background:#fef3c7;color:#92400e}
.rank-badge-healthy{background:#dcfce7;color:#166534}
.rank-badge-balanced{background:#bbf7d0;color:#14532d}
.rank-badge-under{background:#f1f5f9;color:#475569}
.rank-badge-none{background:#f3f4f6;color:#9ca3af}

/* ── Available Capacity ──────────────────────────────────────── */
.avail-list{display:flex;flex-direction:column;gap:8px}
.avail-row{display:grid;align-items:center;gap:8px;grid-template-columns:minmax(90px,1fr) 2fr auto}
.avail-name{font-size:.83rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.avail-track{height:10px;background:var(--border);border-radius:999px;overflow:hidden}
.avail-used{height:100%;border-radius:999px}
.avail-free{font-size:.78rem;font-weight:700;white-space:nowrap}
.free-positive{color:#16a34a}
.free-zero{color:var(--text-secondary)}

/* ── Monthly Compact ─────────────────────────────────────────── */
.monthly-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px}
.mc-card{border:1px solid var(--border);border-radius:12px;padding:12px;background:color-mix(in srgb,var(--surface) 90%,var(--background));display:flex;flex-direction:column;gap:5px}
.mc-name{font-weight:600;font-size:.85rem;color:var(--text-primary);margin-bottom:4px}
.mc-row{display:grid;grid-template-columns:44px 1fr 34px;align-items:center;gap:6px}
.mc-month-label{font-size:.72rem;color:var(--text-secondary)}
.mc-bar-track{height:8px;background:var(--border);border-radius:999px;overflow:hidden}
.mc-bar-fill{height:100%;border-radius:999px}
.mc-pct{font-size:.72rem;font-weight:600;color:var(--text-secondary);text-align:right}

/* ── Misc ────────────────────────────────────────────────────── */
.empty-state{color:var(--text-secondary);font-size:.85rem;text-align:center;padding:24px}
</style>
