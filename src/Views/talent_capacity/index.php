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

$statusLabel = [
    'overload' => 'Sobreasignación',
    'risk' => 'Riesgo',
    'healthy' => 'Saludable',
    'under' => 'Subutilización',
    'balanced' => 'En rango',
    'none' => 'Sin carga',
];
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
        <article class="kpi-card"><strong><?= (int) ($summary['risk_talents'] ?? 0) ?></strong><span>Talentos en riesgo (90%-100%)</span></article>
        <article class="kpi-card"><strong><?= number_format((float) ($summary['idle_capacity'] ?? 0), 1) ?>h</strong><span>Capacidad ociosa global</span></article>
    </section>

    <section class="capacity-block">
        <div class="section-title"><h3>Heatmap de carga del equipo</h3></div>
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
                            <td class="status-<?= htmlspecialchars((string) ($cell['status'] ?? 'none')) ?>" title="<?= number_format((float) ($cell['utilization'] ?? 0), 1) ?>%">
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
                <span class="legend-item"><i class="dot status-<?= htmlspecialchars($key) ?>"></i><?= htmlspecialchars($label) ?></span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="capacity-block">
        <div class="section-title"><h3>Capacidad vs carga (vista mensual)</h3></div>
        <div class="bar-list">
            <?php foreach ($talents as $talent): ?>
                <?php $latestMonth = end($talent['monthly']) ?: ['hours' => 0, 'capacity' => 0, 'utilization' => 0, 'status' => 'none']; ?>
                <?php $util = (float) ($latestMonth['utilization'] ?? 0); ?>
                <article class="bar-item">
                    <header>
                        <strong><?= htmlspecialchars((string) ($talent['name'] ?? '')) ?></strong>
                        <span><?= number_format($util, 1) ?>%</span>
                    </header>
                    <div class="track"><span class="fill status-<?= htmlspecialchars((string) ($latestMonth['status'] ?? 'none')) ?>" style="width: <?= min(100, max(0, $util)) ?>%"></span></div>
                    <small>Asignadas: <?= number_format((float) ($latestMonth['hours'] ?? 0), 1) ?>h · Capacidad: <?= number_format((float) ($latestMonth['capacity'] ?? 0), 1) ?>h</small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</section>

<style>
.capacity-shell{display:flex;flex-direction:column;gap:18px}.capacity-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}.capacity-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;padding:14px;border:1px solid var(--border);background:var(--surface);border-radius:14px}.capacity-filters label{display:flex;flex-direction:column;gap:6px;color:var(--text-secondary);font-size:.9rem}.capacity-filters input,.capacity-filters select{background:var(--background);border:1px solid var(--border);border-radius:10px;padding:10px;color:var(--text-primary)}.capacity-filters .actions{display:flex;align-items:end}.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.kpi-card{border:1px solid var(--border);background:var(--surface);border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:8px}.kpi-card strong{font-size:1.6rem;color:var(--primary)}.capacity-block{border:1px solid var(--border);background:var(--surface);border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:12px}.heatmap-table-wrap{overflow:auto}.heatmap-table{width:max-content;min-width:100%;border-collapse:separate;border-spacing:4px}.heatmap-table th,.heatmap-table td{padding:8px 10px;text-align:center;font-size:.82rem;border-radius:8px}.heatmap-table th{background:color-mix(in srgb,var(--surface) 70%,var(--background));position:sticky;left:0;z-index:2}.heatmap-table td{min-width:60px;background:color-mix(in srgb,var(--background) 90%,var(--surface))}.legend{display:flex;flex-wrap:wrap;gap:10px}.legend-item{display:inline-flex;align-items:center;gap:6px;font-size:.82rem;color:var(--text-secondary)}.dot{width:10px;height:10px;border-radius:999px;display:inline-block}.bar-list{display:flex;flex-direction:column;gap:10px}.bar-item{padding:10px;border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--surface) 90%,var(--background));display:flex;flex-direction:column;gap:6px}.bar-item header{display:flex;justify-content:space-between}.track{height:12px;background:color-mix(in srgb,var(--background) 92%,var(--surface));border-radius:999px;overflow:hidden}.fill{height:100%;display:block}.status-overload{background:color-mix(in srgb,var(--danger) 60%,transparent)}.status-risk{background:color-mix(in srgb,var(--warning) 60%,transparent)}.status-healthy{background:color-mix(in srgb,var(--success) 60%,transparent)}.status-under{background:color-mix(in srgb,var(--neutral) 35%,var(--background))}.status-balanced{background:color-mix(in srgb,var(--info) 45%,var(--background))}.status-none{background:color-mix(in srgb,var(--border) 45%,var(--background))}
</style>
