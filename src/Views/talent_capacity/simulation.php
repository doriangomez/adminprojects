<?php
$basePath = $basePath ?? '';
$filters = is_array($filters ?? null) ? $filters : [];
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$options = is_array($dashboard['filter_options'] ?? null) ? $dashboard['filter_options'] : [];
$result = is_array($simulationResult ?? null) ? $simulationResult : null;
$impact = ($result && isset($result['impact']) && is_array($result['impact'])) ? $result['impact'] : [];
$insights = ($result && isset($result['insights']) && is_array($result['insights'])) ? $result['insights'] : [];

$periodFrom = ($result && isset($result['period_from'])) ? $result['period_from'] : ($filters['date_from'] ?? date('Y-m-01'));
$periodTo = ($result && isset($result['period_to'])) ? $result['period_to'] : ($filters['date_to'] ?? date('Y-m-t'));
$periodLabel = '';
if ($periodFrom && $periodTo) {
    $from = DateTimeImmutable::createFromFormat('Y-m-d', $periodFrom);
    $to = DateTimeImmutable::createFromFormat('Y-m-d', $periodTo);
    if ($from && $to) {
        $months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $periodLabel = ($months[(int)$from->format('n')] ?? $from->format('M')) . ' - ' . ($months[(int)$to->format('n')] ?? $to->format('M'));
    }
}
if ($periodLabel === '') {
    $periodLabel = $periodFrom . ' a ' . $periodTo;
}
$activeTab = 'simulacion';
?>
<?php include __DIR__ . '/_tabs.php'; ?>

<section class="simulation-shell">
    <header class="simulation-header">
        <div>
            <p class="eyebrow">Simulación</p>
            <h2>Gestión de Carga y Capacidad</h2>
            <h3 class="simulation-subtitle">Simulación de Capacidad</h3>
            <small class="section-muted">Simula el impacto de nuevos proyectos en la carga del equipo antes de asignarlos. No modifica datos reales.</small>
        </div>
        <span class="badge neutral">Solo simulación</span>
    </header>

    <form method="POST" action="<?= htmlspecialchars($basePath) ?>/talent-capacity/simulation" class="simulation-form card-grid">
        <input type="hidden" name="project_id" value="0">
        <div class="form-section">
            <h4>Filtros del equipo (opcional)</h4>
            <label>
                Área
                <select name="area">
                    <option value="">Todas</option>
                    <?php foreach (($options['areas'] ?? []) as $area): ?>
                        <?php $code = (string) ($area['code'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= ($filters['area'] ?? '') === $code ? 'selected' : '' ?>><?= htmlspecialchars($code) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Rol
                <select name="role">
                    <option value="">Todos</option>
                    <?php foreach (($options['roles'] ?? []) as $role): ?>
                        <?php $roleName = (string) ($role['role'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($roleName) ?>" <?= ($filters['role'] ?? '') === $roleName ? 'selected' : '' ?>><?= htmlspecialchars($roleName) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-section">
            <h4>Proyecto a simular</h4>
            <label>
                Nombre del proyecto
                <input type="text" name="project_name" value="<?= htmlspecialchars((string) ($result ? ($result['project_name'] ?? '') : '')) ?>" placeholder="Ej: Proyecto Alpha">
            </label>
            <label>
                Horas estimadas
                <input type="number" name="estimated_hours" value="<?= htmlspecialchars((string) ($result ? ($result['estimated_hours'] ?? '200') : '200')) ?>" min="0" step="1" placeholder="200">
            </label>
            <label>
                Periodo desde
                <input type="date" name="period_from" value="<?= htmlspecialchars($periodFrom) ?>">
            </label>
            <label>
                Periodo hasta
                <input type="date" name="period_to" value="<?= htmlspecialchars($periodTo) ?>">
            </label>
            <button type="submit" class="action-btn primary">Ejecutar simulación</button>
        </div>
    </form>

    <?php if (!empty($impact)): ?>
    <section class="simulation-block">
        <h3>Impacto en el equipo</h3>
        <div class="impact-table-wrap">
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
                <?php foreach ($impact as $row): ?>
                    <?php $util = (float) ($row['simulated_utilization'] ?? 0); ?>
                    <?php $isOverload = $util > 90; ?>
                    <tr class="<?= $isOverload ? 'overload' : '' ?>">
                        <th><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></th>
                        <td><?= number_format((float) ($row['current_hours'] ?? 0), 0) ?>h</td>
                        <td><?= number_format((float) ($row['simulated_hours'] ?? 0), 0) ?>h</td>
                        <td>
                            <span class="util-badge <?= $isOverload ? 'overload' : ($util >= 70 ? 'high' : 'normal') ?>">
                                <?= number_format($util, 0) ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="simulation-block insights-block">
        <h3>Insights del sistema</h3>
        <ul class="insights-list">
            <?php foreach ($insights as $insight): ?>
                <li><?= htmlspecialchars((string) $insight) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php else: ?>
    <section class="simulation-block simulation-empty">
        <p class="section-muted">Ingresa los datos del proyecto y haz clic en <strong>Ejecutar simulación</strong> para ver el impacto en la carga del equipo.</p>
    </section>
    <?php endif; ?>
</section>

<style>
.simulation-shell { display: flex; flex-direction: column; gap: 24px; }
.simulation-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.simulation-header .eyebrow { font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; color: var(--text-secondary); margin: 0 0 4px 0; }
.simulation-header h2 { margin: 0; font-size: 1.5rem; }
.simulation-subtitle { margin: 4px 0 8px 0; font-size: 1.15rem; font-weight: 600; color: var(--primary); }
.section-muted { color: var(--text-secondary); font-size: .9rem; }
.badge.neutral { padding: 6px 12px; border-radius: 999px; background: color-mix(in srgb, var(--border) 40%, var(--surface)); font-size: .8rem; font-weight: 600; }
.simulation-form { border: 1px solid var(--border); background: var(--surface); border-radius: 14px; padding: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
.simulation-form .form-section { display: flex; flex-direction: column; gap: 14px; max-width: 420px; }
.simulation-form select { background: var(--background); border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px; color: var(--text-primary); }
.simulation-form h4 { margin: 0 0 8px 0; font-size: 1rem; }
.simulation-form label { display: flex; flex-direction: column; gap: 6px; color: var(--text-secondary); font-size: .9rem; }
.simulation-form input[type="text"],
.simulation-form input[type="number"],
.simulation-form input[type="date"] { background: var(--background); border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px; color: var(--text-primary); }
.action-btn.primary { padding: 10px 20px; background: var(--primary); color: var(--text-primary); border: none; border-radius: 10px; font-weight: 600; cursor: pointer; align-self: flex-start; }
.action-btn.primary:hover { filter: brightness(1.08); }
.simulation-block { border: 1px solid var(--border); background: var(--surface); border-radius: 14px; padding: 18px; }
.simulation-block h3 { margin: 0 0 14px 0; font-size: 1.1rem; }
.impact-table-wrap { overflow-x: auto; }
.impact-table { width: 100%; border-collapse: collapse; }
.impact-table th, .impact-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); }
.impact-table thead th { background: color-mix(in srgb, var(--surface) 70%, var(--background)); font-weight: 600; }
.impact-table tbody th { font-weight: 500; }
.impact-table tr.overload { background: rgba(239, 68, 68, .08); }
.util-badge { display: inline-block; padding: 4px 10px; border-radius: 8px; font-weight: 600; font-size: .9rem; }
.util-badge.normal { background: rgba(34, 197, 94, .2); color: #15803d; }
.util-badge.high { background: rgba(250, 204, 21, .25); color: #a16207; }
.util-badge.overload { background: rgba(239, 68, 68, .25); color: #b91c1c; }
.insights-block { background: linear-gradient(135deg, color-mix(in srgb, var(--surface) 92%, #dbeafe), color-mix(in srgb, var(--surface) 94%, #f1f5f9)); }
.insights-list { margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 8px; }
.insights-list li { color: var(--text-primary); line-height: 1.5; }
.simulation-empty { text-align: center; padding: 32px; }
</style>
