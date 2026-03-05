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

$heatColor = static function (string $status): string {
    return match ($status) {
        'overload' => 'hm-red',
        'risk' => 'hm-yellow',
        'healthy', 'balanced', 'under' => 'hm-green',
        default => 'hm-white',
    };
};

$talentMetrics = [];
foreach ($talents as $t) {
    $monthly = $t['monthly'] ?? [];
    $latestMonth = !empty($monthly) ? end($monthly) : ['utilization' => 0, 'hours' => 0, 'capacity' => 0, 'status' => 'none'];
    $util = (float) ($latestMonth['utilization'] ?? 0);
    $hours = (float) ($latestMonth['hours'] ?? 0);
    $cap = (float) ($latestMonth['capacity'] ?? 0);
    $talentMetrics[] = [
        'name' => (string) ($t['name'] ?? ''),
        'role' => (string) ($t['role'] ?? ''),
        'utilization' => $util,
        'hours' => $hours,
        'capacity' => $cap,
        'free_hours' => max(0, $cap - $hours),
        'status' => (string) ($latestMonth['status'] ?? 'none'),
    ];
}

$ranking = $talentMetrics;
usort($ranking, static fn(array $a, array $b) => $b['utilization'] <=> $a['utilization']);

$critical = array_values(array_filter($talentMetrics, static fn(array $t) => $t['utilization'] >= 90));
usort($critical, static fn(array $a, array $b) => $b['utilization'] <=> $a['utilization']);

$available = array_values(array_filter($talentMetrics, static fn(array $t) => $t['utilization'] < 80 && $t['capacity'] > 0));
usort($available, static fn(array $a, array $b) => $b['free_hours'] <=> $a['free_hours']);

$columnAvgs = [];
foreach (array_keys($columns) as $key) {
    $sum = 0.0;
    $count = 0;
    foreach ($talents as $t) {
        $source = $granularity === 'day' ? ($t['daily'] ?? []) : ($t['weekly'] ?? []);
        if (isset($source[$key])) {
            $sum += (float) ($source[$key]['utilization'] ?? 0);
            $count++;
        }
    }
    $columnAvgs[$key] = $count > 0 ? round($sum / $count, 1) : 0.0;
}

$totalFreeHours = 0.0;
foreach ($available as $av) {
    $totalFreeHours += $av['free_hours'];
}
?>

<section class="cap-shell">
    <header class="cap-header">
        <div>
            <p class="eyebrow">Módulo visual</p>
            <h2>Gestión Visual de Carga y Capacidad del Talento</h2>
            <small class="section-muted">Visualiza saturación, capacidad ociosa y balance del equipo en tiempo real.</small>
        </div>
        <span class="badge neutral">Vista ejecutiva</span>
    </header>

    <form method="GET" class="cap-filters card-grid">
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
        <article class="kpi-card">
            <strong><?= number_format((float) ($summary['avg_team_utilization'] ?? 0), 1) ?>%</strong>
            <span>Utilización promedio</span>
        </article>
        <article class="kpi-card kpi-danger">
            <strong><?= number_format((float) ($summary['overassigned_hours'] ?? 0), 1) ?>h</strong>
            <span>Horas sobreasignadas</span>
        </article>
        <article class="kpi-card kpi-warning">
            <strong><?= (int) ($summary['risk_talents'] ?? 0) ?></strong>
            <span>Talentos críticos (&ge;90%)</span>
        </article>
        <article class="kpi-card kpi-success">
            <strong><?= number_format((float) ($summary['idle_capacity'] ?? 0), 1) ?>h</strong>
            <span>Capacidad ociosa</span>
        </article>
    </section>

    <?php if (!empty($critical)): ?>
    <section class="critical-alert">
        <div class="critical-head">
            <h3>Talentos Críticos</h3>
            <span class="critical-badge"><?= count($critical) ?> con carga &ge;90%</span>
        </div>
        <div class="critical-grid">
            <?php foreach ($critical as $ct): ?>
            <div class="critical-card">
                <div class="critical-top">
                    <div>
                        <strong><?= htmlspecialchars($ct['name']) ?></strong>
                        <?php if ($ct['role'] !== ''): ?><small><?= htmlspecialchars($ct['role']) ?></small><?php endif; ?>
                    </div>
                    <span class="critical-pct <?= $ct['utilization'] > 100 ? 'pct-over' : 'pct-risk' ?>"><?= number_format($ct['utilization'], 0) ?>%</span>
                </div>
                <div class="critical-track">
                    <div class="critical-fill <?= $ct['utilization'] > 100 ? 'fill-red' : 'fill-yellow' ?>" style="width:<?= min(100, max(0, $ct['utilization'])) ?>%"></div>
                </div>
                <small><?= number_format($ct['hours'], 1) ?>h asignadas / <?= number_format($ct['capacity'], 1) ?>h capacidad</small>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="cap-block">
        <div class="section-title"><h3>Heatmap de carga del equipo</h3></div>
        <div class="heatmap-wrap">
            <table class="heatmap">
                <thead>
                    <tr>
                        <th class="hm-name-col">Talento</th>
                        <?php foreach ($columns as $label): ?><th><?= htmlspecialchars($label) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($talents as $talent): ?>
                    <?php $source = $granularity === 'day' ? ($talent['daily'] ?? []) : ($talent['weekly'] ?? []); ?>
                    <tr>
                        <th class="hm-name-col"><?= htmlspecialchars((string) ($talent['name'] ?? '')) ?></th>
                        <?php foreach ($columns as $key => $label): ?>
                            <?php $cell = $source[$key] ?? ['utilization' => 0, 'status' => 'none']; ?>
                            <td class="hm-cell <?= $heatColor((string) ($cell['status'] ?? 'none')) ?>"
                                title="<?= htmlspecialchars((string) ($talent['name'] ?? '')) ?>: <?= number_format((float) ($cell['utilization'] ?? 0), 1) ?>%">
                                <?= number_format((float) ($cell['utilization'] ?? 0), 0) ?>%
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="hm-avg-row">
                        <th class="hm-name-col">Promedio</th>
                        <?php foreach (array_keys($columns) as $key): ?>
                            <?php $avg = $columnAvgs[$key] ?? 0; ?>
                            <td class="hm-cell hm-avg <?= $avg >= 90 ? 'hm-avg-risk' : '' ?>"
                                title="Promedio equipo: <?= number_format($avg, 1) ?>%">
                                <?= number_format($avg, 0) ?>%
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="hm-legend">
            <span class="legend-item"><i class="dot hm-white"></i>Sin carga</span>
            <span class="legend-item"><i class="dot hm-green"></i>Saludable</span>
            <span class="legend-item"><i class="dot hm-yellow"></i>Alto</span>
            <span class="legend-item"><i class="dot hm-red"></i>Sobrecarga</span>
        </div>
    </section>

    <div class="dual-panel">
        <section class="cap-block">
            <div class="section-title"><h3>Ranking de carga</h3></div>
            <div class="rank-list">
                <?php foreach ($ranking as $i => $r): ?>
                <div class="rank-row">
                    <span class="rank-pos"><?= $i + 1 ?></span>
                    <div class="rank-body">
                        <div class="rank-top">
                            <strong><?= htmlspecialchars($r['name']) ?></strong>
                            <span class="rank-pct <?= $r['utilization'] > 100 ? 'pct-over' : ($r['utilization'] >= 90 ? 'pct-risk' : ($r['utilization'] >= 70 ? 'pct-ok' : 'pct-low')) ?>">
                                <?= number_format($r['utilization'], 0) ?>%
                            </span>
                        </div>
                        <div class="rank-track">
                            <div class="rank-fill <?= $r['utilization'] > 100 ? 'fill-red' : ($r['utilization'] >= 90 ? 'fill-yellow' : 'fill-green') ?>"
                                 style="width:<?= min(100, max(0, $r['utilization'])) ?>%"></div>
                        </div>
                        <small><?= number_format($r['hours'], 1) ?>h / <?= number_format($r['capacity'], 1) ?>h</small>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($ranking)): ?>
                    <p class="empty-msg">Sin datos de carga.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="cap-block">
            <div class="section-title">
                <h3>Capacidad disponible</h3>
                <span class="avail-badge"><?= number_format($totalFreeHours, 0) ?>h libres</span>
            </div>
            <?php if (empty($available)): ?>
                <p class="empty-msg">Todo el equipo está al máximo de capacidad.</p>
            <?php else: ?>
                <div class="avail-list">
                    <?php foreach ($available as $av): ?>
                    <div class="avail-row">
                        <div class="avail-top">
                            <strong><?= htmlspecialchars($av['name']) ?></strong>
                            <span class="avail-free"><?= number_format($av['free_hours'], 0) ?>h libres</span>
                        </div>
                        <div class="avail-track">
                            <div class="avail-used" style="width:<?= min(100, max(0, $av['utilization'])) ?>%"></div>
                        </div>
                        <small><?= number_format($av['utilization'], 0) ?>% ocupado &middot; <?= number_format($av['hours'], 1) ?>h / <?= number_format($av['capacity'], 1) ?>h</small>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<style>
.cap-shell { display:flex; flex-direction:column; gap:18px; }
.cap-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }

.cap-filters { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; padding:14px; border:1px solid var(--border); background:var(--surface); border-radius:14px; }
.cap-filters label { display:flex; flex-direction:column; gap:6px; color:var(--text-secondary); font-size:.9rem; }
.cap-filters input,.cap-filters select { background:var(--background); border:1px solid var(--border); border-radius:10px; padding:10px; color:var(--text-primary); }
.cap-filters .actions { display:flex; align-items:end; }

.kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; }
.kpi-card { border:1px solid var(--border); background:var(--surface); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:8px; }
.kpi-card strong { font-size:1.6rem; color:var(--primary); }
.kpi-card.kpi-danger strong { color:#fa5252; }
.kpi-card.kpi-warning strong { color:#e67700; }
.kpi-card.kpi-success strong { color:#2b8a3e; }

.critical-alert { border:2px solid #e67700; background:color-mix(in srgb, #fab005 6%, var(--surface) 94%); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:12px; }
.critical-head { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
.critical-head h3 { margin:0; color:var(--text-primary); }
.critical-badge { background:#e67700; color:#fff; font-size:.78rem; font-weight:700; padding:4px 12px; border-radius:999px; }
.critical-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:10px; }
.critical-card { padding:12px; border:1px solid var(--border); border-radius:12px; background:var(--surface); display:flex; flex-direction:column; gap:8px; }
.critical-top { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; }
.critical-top small { display:block; color:var(--text-secondary); font-size:.8rem; }
.critical-pct { font-weight:800; font-size:1.2rem; white-space:nowrap; }
.pct-over { color:#fa5252; }
.pct-risk { color:#e67700; }
.critical-track { height:8px; background:color-mix(in srgb, var(--border) 50%, var(--background)); border-radius:999px; overflow:hidden; }
.critical-fill { height:100%; border-radius:999px; }
.fill-red { background:#fa5252; }
.fill-yellow { background:#fab005; }
.fill-green { background:#40c057; }

.cap-block { border:1px solid var(--border); background:var(--surface); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:12px; }
.section-title { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; }
.section-title h3 { margin:0; }

.heatmap-wrap { overflow:auto; }
.heatmap { width:max-content; min-width:100%; border-collapse:separate; border-spacing:3px; }
.heatmap th, .heatmap td { padding:6px 8px; text-align:center; font-size:.78rem; border-radius:6px; }
.hm-name-col { background:color-mix(in srgb, var(--surface) 70%, var(--background)); position:sticky; left:0; z-index:2; text-align:left !important; white-space:nowrap; min-width:120px; }
.hm-cell { min-width:54px; font-weight:600; cursor:default; transition:transform .12s, box-shadow .12s; }
.hm-cell:hover { transform:scale(1.15); z-index:3; box-shadow:0 4px 14px rgba(0,0,0,.22); }

.hm-white { background:color-mix(in srgb, var(--surface) 60%, var(--background)); color:var(--text-secondary); }
.hm-green { background:#40c057; color:#fff; }
.hm-yellow { background:#fab005; color:#1a1a1a; }
.hm-red { background:#fa5252; color:#fff; }

.hm-avg { background:color-mix(in srgb, var(--background) 80%, var(--border)); color:var(--text-secondary); font-weight:700; }
.hm-avg-risk { background:color-mix(in srgb, #fab005 22%, var(--background)); color:#e67700; }
.hm-avg-row th { font-style:italic; }

.hm-legend { display:flex; flex-wrap:wrap; gap:14px; padding-top:4px; }
.legend-item { display:inline-flex; align-items:center; gap:6px; font-size:.82rem; color:var(--text-secondary); }
.dot { width:12px; height:12px; border-radius:3px; display:inline-block; border:1px solid rgba(0,0,0,.08); }

.dual-panel { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media (max-width:860px) { .dual-panel { grid-template-columns:1fr; } }

.rank-list { display:flex; flex-direction:column; gap:6px; max-height:440px; overflow-y:auto; }
.rank-row { display:flex; align-items:flex-start; gap:10px; padding:8px 10px; border:1px solid var(--border); border-radius:10px; background:color-mix(in srgb, var(--surface) 90%, var(--background)); }
.rank-pos { font-weight:800; font-size:.88rem; color:var(--text-secondary); min-width:22px; text-align:center; padding-top:2px; }
.rank-body { flex:1; display:flex; flex-direction:column; gap:4px; min-width:0; }
.rank-top { display:flex; justify-content:space-between; align-items:center; gap:6px; }
.rank-pct { font-weight:700; font-size:.85rem; white-space:nowrap; }
.pct-ok { color:#2b8a3e; }
.pct-low { color:var(--text-secondary); }
.rank-track { height:10px; background:color-mix(in srgb, var(--border) 40%, var(--background)); border-radius:999px; overflow:hidden; }
.rank-fill { height:100%; border-radius:999px; transition:width .3s ease; }

.avail-badge { background:color-mix(in srgb, #40c057 18%, var(--surface)); color:#2b8a3e; font-size:.78rem; font-weight:700; padding:4px 12px; border-radius:999px; border:1px solid color-mix(in srgb, #40c057 30%, var(--border)); }
.avail-list { display:flex; flex-direction:column; gap:6px; max-height:440px; overflow-y:auto; }
.avail-row { padding:8px 10px; border:1px solid var(--border); border-radius:10px; background:color-mix(in srgb, var(--surface) 90%, var(--background)); display:flex; flex-direction:column; gap:4px; }
.avail-top { display:flex; justify-content:space-between; align-items:center; gap:6px; }
.avail-free { color:#2b8a3e; font-weight:700; font-size:.85rem; white-space:nowrap; }
.avail-track { height:10px; background:color-mix(in srgb, var(--border) 40%, var(--background)); border-radius:999px; overflow:hidden; }
.avail-used { height:100%; background:color-mix(in srgb, #339af0 70%, var(--surface)); border-radius:999px; }

.empty-msg { color:var(--text-secondary); font-size:.88rem; padding:10px 0; }
</style>
