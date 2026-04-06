<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$talents = is_array($talents ?? null) ? $talents : [];
$assignmentLabels = [
    'active' => 'Activo',
    'paused' => 'Inactivo',
    'removed' => 'Retirado',
];
$assignmentBadge = static function (string $status): string {
    return match ($status) {
        'active' => 'status-success',
        'paused' => 'status-warning',
        'removed' => 'status-danger',
        default => 'status-muted',
    };
};
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Talento</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Equipo asignado, roles y dedicaciones críticas.</small>
        </div>
        <div class="project-actions">
            <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?view=resumen">Volver al resumen</a>
            <a class="action-btn primary" href="#talent-management">Gestionar talento</a>
        </div>
    </header>

    <?php
    $activeTab = 'talento';
    require __DIR__ . '/_tabs.php';
    ?>

    <section class="talent-overview">
        <div>
            <p class="eyebrow">Talentos asignados</p>
            <h4>Roles y dedicación</h4>
        </div>
        <?php if (empty($assignments)): ?>
            <p class="section-muted">Sin asignaciones actuales.</p>
        <?php else: ?>
            <div class="talent-table">
                <div class="talent-row talent-head">
                    <span>Talento</span>
                    <span>Rol</span>
                    <span>Dedicación</span>
                    <span>Estado</span>
                    <span>Reporte</span>
                    <span>Aprobación</span>
                    <span>Acciones</span>
                </div>
                <?php foreach ($assignments as $assignment): ?>
                    <?php
                    $assignmentStatus = (string) ($assignment['assignment_status'] ?? 'active');
                    $isEditable = $assignmentStatus !== 'removed';
                    $assignmentId = (int) ($assignment['id'] ?? 0);
                    $allocationPercent = (float) ($assignment['allocation_percent'] ?? 0);
                    $weeklyHours = (float) ($assignment['weekly_hours'] ?? 0);
                    $capacityWeek = (float) ($assignment['capacidad_horaria'] ?? 0);
                    if ($capacityWeek <= 0) {
                        $capacityWeek = (float) ($assignment['talent_weekly_capacity'] ?? 0);
                    }
                    if ($capacityWeek <= 0) {
                        $capacityWeek = 40.0;
                    }
                    $totalAllocationPercent = (float) ($assignment['total_allocation_percent'] ?? $allocationPercent);
                    $availableAllocationPercent = (float) ($assignment['available_allocation_percent'] ?? (100.0 - $totalAllocationPercent));
                    $overAllocationPercent = max(0.0, $totalAllocationPercent - 100.0);
                    $workloadBaseCapacity = (float) ($assignment['workload_base_capacity_weekly_hours'] ?? $capacityWeek);
                    if ($workloadBaseCapacity <= 0) {
                        $workloadBaseCapacity = $capacityWeek > 0 ? $capacityWeek : 40.0;
                    }
                    $workloadTotalHours = (float) ($assignment['workload_total_weekly_hours'] ?? 0);
                    $workloadBreakdown = is_array($assignment['workload_breakdown'] ?? null) ? $assignment['workload_breakdown'] : [];
                    usort($workloadBreakdown, static function (array $left, array $right): int {
                        $leftHours = (float) ($left['weekly_hours'] ?? 0);
                        $rightHours = (float) ($right['weekly_hours'] ?? 0);

                        return $rightHours <=> $leftHours;
                    });
                    $remainingHours = max(0.0, $workloadBaseCapacity - $workloadTotalHours);
                    $occupancyPercent = $workloadBaseCapacity > 0
                        ? ($workloadTotalHours / $workloadBaseCapacity) * 100
                        : 0.0;
                    $allocationSemaphore = 'green';
                    if ($totalAllocationPercent > 120.0) {
                        $allocationSemaphore = 'red';
                    } elseif ($totalAllocationPercent > 100.0) {
                        $allocationSemaphore = 'yellow';
                    }
                    $tooltipProgressState = 'ok';
                    if ($occupancyPercent > 100.0) {
                        $tooltipProgressState = 'overload';
                    } elseif ($occupancyPercent > 85.0) {
                        $tooltipProgressState = 'high';
                    }
                    $workloadEndpoint = $basePath
                        . '/projects/' . (int) ($project['id'] ?? 0)
                        . '/talent/assignments/' . $assignmentId
                        . '/workload';
                    ?>
                    <div class="talent-row">
                        <div>
                            <strong><?= htmlspecialchars($assignment['talent_name'] ?? 'Talento') ?></strong>
                            <small class="section-muted"><?= htmlspecialchars($assignment['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($assignment['end_date'] ?? 'N/A') ?></small>
                        </div>
                        <span><?= htmlspecialchars($assignment['role'] ?? '') ?></span>
                        <div class="dedication-editor"
                             data-assignment-id="<?= $assignmentId ?>"
                             data-endpoint="<?= htmlspecialchars($workloadEndpoint) ?>"
                             data-capacity-week="<?= htmlspecialchars(number_format($capacityWeek, 2, '.', '')) ?>">
                            <input type="range"
                                   class="dedication-slider js-dedication-slider"
                                   min="0"
                                   max="200"
                                   step="0.1"
                                   value="<?= htmlspecialchars(number_format($allocationPercent, 2, '.', '')) ?>"
                                   <?= $isEditable ? '' : 'disabled' ?>>
                            <div class="dedication-inline-values">
                                <strong class="js-dedication-percent"><?= htmlspecialchars(number_format($allocationPercent, 1, '.', '')) ?>%</strong>
                                <input type="number"
                                       class="dedication-hours-input js-dedication-hours"
                                       min="0"
                                       step="0.1"
                                       value="<?= htmlspecialchars(number_format($weeklyHours, 2, '.', '')) ?>"
                                       <?= $isEditable ? '' : 'disabled' ?>>
                                <span class="section-muted">h/sem</span>
                            </div>
                            <small class="section-muted js-dedication-equation">
                                <?= htmlspecialchars(number_format($allocationPercent, 1, '.', '')) ?>% → <?= htmlspecialchars(number_format($weeklyHours, 2, '.', '')) ?>h/sem
                            </small>
                            <small class="section-muted">
                                Capacidad estándar: <?= htmlspecialchars(number_format($capacityWeek, 1, '.', '')) ?>h/sem ·
                                <span class="workload-tooltip-wrap">
                                    <button type="button"
                                            class="workload-trigger allocation-value allocation-<?= htmlspecialchars($allocationSemaphore) ?>"
                                            data-tooltip-trigger
                                            aria-expanded="false">
                                        <?= htmlspecialchars(number_format($totalAllocationPercent, 1, '.', '')) ?>% ocupado
                                    </button>
                                    <button type="button"
                                            class="workload-tooltip-icon"
                                            data-tooltip-trigger
                                            aria-label="Ver carga del recurso"
                                            aria-expanded="false">ⓘ</button>
                                    <div class="workload-enterprise-tooltip" role="dialog" aria-hidden="true">
                                        <div class="workload-enterprise-header">Carga del recurso</div>
                                        <?php if ($workloadBreakdown !== []): ?>
                                            <ul class="workload-enterprise-list">
                                                <?php foreach ($workloadBreakdown as $workloadProject): ?>
                                                    <?php
                                                    $projectLabel = (string) ($workloadProject['project_name'] ?? 'Proyecto');
                                                    $projectHours = (float) ($workloadProject['weekly_hours'] ?? 0);
                                                    ?>
                                                    <li class="workload-enterprise-item">
                                                        <span><?= htmlspecialchars($projectLabel) ?></span>
                                                        <strong><?= htmlspecialchars(number_format($projectHours, 1, '.', '')) ?>h/sem</strong>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="workload-enterprise-empty">Sin carga activa registrada para este recurso.</p>
                                        <?php endif; ?>
                                        <div class="workload-enterprise-separator"></div>
                                        <div class="workload-enterprise-totals">
                                            <div><span>Total horas</span><strong><?= htmlspecialchars(number_format($workloadTotalHours, 1, '.', '')) ?>h/sem</strong></div>
                                            <div><span>Capacidad base</span><strong><?= htmlspecialchars(number_format($workloadBaseCapacity, 1, '.', '')) ?>h/sem</strong></div>
                                            <div><span>Ocupación</span><strong><?= htmlspecialchars(number_format($occupancyPercent, 1, '.', '')) ?>%</strong></div>
                                            <div><span>Disponibilidad</span><strong><?= htmlspecialchars(number_format($remainingHours, 1, '.', '')) ?>h libres</strong></div>
                                        </div>
                                        <div class="workload-enterprise-progress">
                                            <div class="workload-enterprise-progress-track">
                                                <span class="workload-enterprise-progress-fill is-<?= htmlspecialchars($tooltipProgressState) ?>"
                                                      style="width: <?= htmlspecialchars(number_format(min(100.0, max(0.0, $occupancyPercent)), 1, '.', '')) ?>%;"></span>
                                            </div>
                                            <?php if ($occupancyPercent > 100.0): ?>
                                                <small class="workload-enterprise-alert">⚠️ El recurso supera el 100% de capacidad.</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </span> ·
                                <span class="allocation-availability <?= $availableAllocationPercent < 0 ? 'is-negative' : '' ?>">
                                    <?= htmlspecialchars(number_format($availableAllocationPercent, 1, '.', '')) ?>% disponible
                                </span>
                            </small>
                            <?php if ($overAllocationPercent > 0): ?>
                                <small class="section-muted allocation-warning">
                                    🚦 Semáforo <?= strtoupper($allocationSemaphore) ?> · Sobreasignación <?= htmlspecialchars(number_format($overAllocationPercent, 1, '.', '')) ?>%.
                                    El recurso está sobreasignado, pero se permite continuar bajo responsabilidad del PM.
                                </small>
                            <?php endif; ?>
                            <div class="dedication-actions">
                                <button type="button" class="action-btn primary small js-save-dedication" <?= $isEditable ? '' : 'disabled' ?>>Guardar</button>
                                <small class="section-muted js-dedication-feedback" aria-live="polite">
                                    <?= $isEditable ? '' : 'Asignación retirada (solo lectura).' ?>
                                </small>
                            </div>
                        </div>
                        <span class="badge <?= $assignmentBadge($assignmentStatus) ?>">
                            <?= htmlspecialchars($assignmentLabels[$assignmentStatus] ?? ucfirst($assignmentStatus)) ?>
                        </span>
                        <span class="badge <?= !empty($assignment['requiere_reporte_horas']) ? 'status-success' : 'status-muted' ?>">
                            <?= !empty($assignment['requiere_reporte_horas']) ? 'Sí' : 'No' ?>
                        </span>
                        <span class="badge <?= !empty($assignment['requiere_aprobacion_horas']) ? 'status-warning' : 'status-muted' ?>">
                            <?= !empty($assignment['requiere_aprobacion_horas']) ? 'Sí' : 'No' ?>
                        </span>
                        <div class="row-actions">
                            <?php if ($assignmentStatus === 'active'): ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent/assignments/<?= (int) ($assignment['id'] ?? 0) ?>/status" onsubmit="return confirm('¿Inactivar este talento en el proyecto?');">
                                    <input type="hidden" name="assignment_status" value="paused">
                                    <button type="submit" class="action-btn warning">Inactivar</button>
                                </form>
                            <?php elseif ($assignmentStatus === 'paused'): ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent/assignments/<?= (int) ($assignment['id'] ?? 0) ?>/status" onsubmit="return confirm('¿Retirar este talento del proyecto?');">
                                    <input type="hidden" name="assignment_status" value="removed">
                                    <button type="submit" class="action-btn danger">Retirar</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent/assignments/<?= (int) ($assignment['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar definitivamente esta asignación retirada? Esta acción no se puede deshacer.');">
                                    <button type="submit" class="action-btn danger">Eliminar definitivo</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent" method="POST" class="talent-form" id="talent-management">
        <div>
            <p class="eyebrow">Nueva asignación</p>
            <h4>Gestionar talento</h4>
        </div>
        <div class="grid">
            <label>Talento
                <select name="talent_id" required>
                    <option value="">Selecciona un talento</option>
                    <?php foreach ($talents as $talent): ?>
                        <option value="<?= (int) $talent['id'] ?>"><?= htmlspecialchars($talent['name'] ?? '') ?> (<?= htmlspecialchars($talent['role'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Rol en el proyecto
                <input name="role" required placeholder="Ej. Líder técnico">
            </label>
        </div>
        <div class="grid">
            <label>Inicio
                <input type="date" name="start_date">
            </label>
            <label>Fin
                <input type="date" name="end_date">
            </label>
        </div>
        <div class="grid">
            <label>Porcentaje de dedicación (%)
                <input type="number" step="0.1" name="allocation_percent" placeholder="Ej. 50">
            </label>
            <label>Horas semanales
                <input type="number" step="0.1" name="weekly_hours" placeholder="Ej. 20">
            </label>
        </div>
        <div class="grid">
            <label>Tipo de costo
                <select name="cost_type">
                    <option value="por_horas">Por horas</option>
                    <option value="fijo">Fijo</option>
                </select>
            </label>
            <label>Estado
                <select name="assignment_status">
                    <option value="active">Activo</option>
                    <option value="paused">En pausa</option>
                    <option value="removed">Retirado</option>
                </select>
            </label>
            <label>Valor
                <input type="number" step="0.01" name="cost_value" placeholder="0">
            </label>
        </div>
        <div class="checkbox-grid">
            <label class="toggle-field" for="is-external">
                <input id="is-external" type="checkbox" name="is_external" value="1" class="toggle-input">
                <span class="toggle-switch" aria-hidden="true"></span>
                <span>Es externo</span>
            </label>
            <span class="section-muted">El reporte y la aprobación de horas se definen en la ficha del talento.</span>
        </div>
        <button type="submit" class="action-btn primary">Guardar asignación</button>
    </form>
</section>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; border:1px solid var(--border); padding:16px; border-radius:16px; background: var(--surface); }
    .project-title-block { display:flex; flex-direction:column; gap:6px; }
    .project-title-block h2 { margin:0; color: var(--text-primary); }
    .project-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .talent-overview, .talent-form { border:1px solid var(--border); padding:16px; border-radius:16px; background: var(--surface); display:flex; flex-direction:column; gap:12px; }
    .talent-table { display:grid; gap:8px; }
    .talent-row { display:grid; grid-template-columns: minmax(160px, 1.3fr) minmax(110px, 0.8fr) minmax(320px, 2fr) minmax(100px, 0.7fr) minmax(90px, 0.6fr) minmax(90px, 0.6fr) minmax(140px, 0.8fr); gap:10px; align-items:center; border:1px solid var(--border); border-radius:12px; padding:10px; background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); }
    .talent-head { background: color-mix(in srgb, var(--text-secondary) 14%, var(--background)); font-weight:700; font-size:12px; text-transform:uppercase; color: var(--text-secondary); }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px; }
    .checkbox-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:8px; align-items:center; }
    .toggle-field { display:inline-flex; align-items:center; gap:10px; font-weight:600; cursor:pointer; }
    .toggle-input { position:absolute; opacity:0; pointer-events:none; }
    .toggle-switch { width:44px; height:24px; border-radius:999px; background: color-mix(in srgb, var(--text-secondary) 40%, var(--background)); border:1px solid var(--border); position:relative; transition:background 0.2s ease; flex-shrink:0; }
    .toggle-switch::after { content:""; width:18px; height:18px; border-radius:50%; background: var(--surface); position:absolute; top:2px; left:2px; transition:transform 0.2s ease; box-shadow:0 1px 2px rgba(0,0,0,0.25); }
    .toggle-input:checked + .toggle-switch { background: var(--primary); border-color: var(--primary); }
    .toggle-input:checked + .toggle-switch::after { transform:translateX(20px); }
    .toggle-input:focus-visible + .toggle-switch { outline:2px solid color-mix(in srgb, var(--primary) 70%, white); outline-offset:2px; }
    .badge { display:inline-flex; align-items:center; justify-content:center; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid var(--background); }
    .status-muted { background: color-mix(in srgb, var(--text-secondary) 14%, var(--background)); color: var(--text-primary); border-color: var(--border); }
    .status-success { background: color-mix(in srgb, var(--success) 16%, var(--background)); color: var(--success); border-color: color-mix(in srgb, var(--success) 30%, var(--background)); }
    .status-warning { background: color-mix(in srgb, var(--warning) 16%, var(--background)); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 30%, var(--background)); }
    .status-danger { background: color-mix(in srgb, var(--danger) 22%, var(--surface) 78%); color: var(--text-primary); border-color: color-mix(in srgb, var(--danger) 35%, var(--border) 65%); }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.small { padding:4px 8px; font-size:12px; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .action-btn.warning { background: color-mix(in srgb, var(--warning) 16%, var(--surface) 84%); color: var(--text-primary); border-color: color-mix(in srgb, var(--warning) 35%, var(--border) 65%); }
    .action-btn.danger { background: color-mix(in srgb, var(--danger) 18%, var(--surface) 82%); color: var(--text-primary); border-color: color-mix(in srgb, var(--danger) 35%, var(--border) 65%); }
    .row-actions { display:flex; gap:6px; flex-wrap:wrap; }
    .dedication-editor { display:flex; flex-direction:column; gap:6px; }
    .dedication-slider { width:100%; accent-color: var(--primary); }
    .dedication-inline-values { display:flex; align-items:center; gap:6px; }
    .dedication-inline-values strong { min-width:48px; }
    .dedication-hours-input { width:90px; }
    .dedication-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .dedication-editor.has-error .dedication-hours-input { border-color: var(--danger); }
    .dedication-editor.has-error .js-dedication-feedback { color: var(--danger); font-weight:600; }
    .dedication-editor.is-success .js-dedication-feedback { color: var(--success); font-weight:600; }
    .allocation-value { font-weight:700; }
    .allocation-green { color: var(--success); }
    .allocation-yellow { color: var(--warning); }
    .allocation-red { color: var(--danger); }
    .allocation-availability.is-negative { color: var(--danger); font-weight:700; }
    .allocation-warning { color: var(--warning); font-weight:600; }
    .workload-tooltip-wrap { position:relative; display:inline-flex; align-items:center; gap:6px; }
    .workload-trigger { border:none; background:transparent; cursor:pointer; padding:0; font:inherit; }
    .workload-trigger:focus-visible, .workload-tooltip-icon:focus-visible { outline:2px solid color-mix(in srgb, var(--primary) 70%, white); outline-offset:2px; border-radius:8px; }
    .workload-tooltip-icon { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:50%; border:1px solid var(--border); font-size:11px; font-weight:700; cursor:pointer; color:var(--text-secondary); background:var(--surface); padding:0; }
    .workload-enterprise-tooltip { position:absolute; top:calc(100% + 10px); left:0; width:min(390px, 88vw); max-height:330px; overflow:hidden; background:color-mix(in srgb, var(--surface) 95%, #fff 5%); border:1px solid color-mix(in srgb, var(--border) 85%, #fff 15%); border-radius:14px; box-shadow:0 14px 32px rgba(15, 23, 42, 0.14); padding:14px; display:flex; flex-direction:column; gap:10px; opacity:0; transform:translateY(8px); pointer-events:none; visibility:hidden; transition:opacity .16s ease, transform .18s ease, visibility .18s linear; z-index:15; }
    .workload-tooltip-wrap.is-open .workload-enterprise-tooltip { opacity:1; transform:translateY(0); pointer-events:auto; visibility:visible; }
    .workload-enterprise-header { font-size:13px; letter-spacing:.02em; text-transform:uppercase; font-weight:700; color:var(--text-secondary); }
    .workload-enterprise-list { margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:6px; max-height:130px; overflow:auto; }
    .workload-enterprise-item { display:flex; justify-content:space-between; align-items:center; gap:8px; font-size:13px; padding:6px 8px; border-radius:8px; background:color-mix(in srgb, var(--text-secondary) 8%, var(--surface) 92%); }
    .workload-enterprise-empty { margin:0; font-size:13px; color:var(--text-secondary); }
    .workload-enterprise-separator { height:1px; background:color-mix(in srgb, var(--border) 88%, transparent); }
    .workload-enterprise-totals { display:grid; gap:6px; font-size:12px; }
    .workload-enterprise-totals > div { display:flex; justify-content:space-between; align-items:center; gap:8px; }
    .workload-enterprise-progress { display:flex; flex-direction:column; gap:6px; }
    .workload-enterprise-progress-track { height:8px; border-radius:999px; background:color-mix(in srgb, var(--text-secondary) 12%, var(--surface) 88%); overflow:hidden; }
    .workload-enterprise-progress-fill { display:block; height:100%; border-radius:inherit; transition:width .28s ease; }
    .workload-enterprise-progress-fill.is-ok { background:color-mix(in srgb, var(--success) 74%, #7dd3fc 26%); }
    .workload-enterprise-progress-fill.is-high { background:color-mix(in srgb, var(--warning) 80%, #facc15 20%); }
    .workload-enterprise-progress-fill.is-overload { background:color-mix(in srgb, var(--danger) 82%, #ef4444 18%); }
    .workload-enterprise-alert { color:var(--danger); font-weight:600; }
    @media (max-width: 900px) {
        .talent-row { grid-template-columns: 1fr; }
        .talent-head { display:none; }
    }
</style>
<script>
(() => {
    const editors = document.querySelectorAll('.dedication-editor');
    if (editors.length === 0) {
        return;
    }

    const round = (value, decimals) => {
        const factor = 10 ** decimals;
        return Math.round(value * factor) / factor;
    };

    const formatValue = (value, decimals) => {
        const normalized = round(value, decimals).toFixed(decimals);
        return normalized.replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.0+$/, '');
    };

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const tooltipWrappers = document.querySelectorAll('.workload-tooltip-wrap');
    let activeTooltip = null;

    const closeTooltip = (wrapper) => {
        if (!wrapper) {
            return;
        }
        wrapper.classList.remove('is-open');
        wrapper.querySelectorAll('[data-tooltip-trigger]').forEach((trigger) => {
            trigger.setAttribute('aria-expanded', 'false');
        });
        const panel = wrapper.querySelector('.workload-enterprise-tooltip');
        if (panel) {
            panel.setAttribute('aria-hidden', 'true');
        }
        if (activeTooltip === wrapper) {
            activeTooltip = null;
        }
    };

    const openTooltip = (wrapper) => {
        if (!wrapper) {
            return;
        }
        if (activeTooltip && activeTooltip !== wrapper) {
            closeTooltip(activeTooltip);
        }
        wrapper.classList.add('is-open');
        wrapper.querySelectorAll('[data-tooltip-trigger]').forEach((trigger) => {
            trigger.setAttribute('aria-expanded', 'true');
        });
        const panel = wrapper.querySelector('.workload-enterprise-tooltip');
        if (panel) {
            panel.setAttribute('aria-hidden', 'false');
        }
        activeTooltip = wrapper;
    };

    tooltipWrappers.forEach((wrapper) => {
        const triggers = wrapper.querySelectorAll('[data-tooltip-trigger]');
        if (triggers.length === 0) {
            return;
        }
        let hoverTimeout = null;

        wrapper.addEventListener('mouseenter', () => {
            if (hoverTimeout) {
                window.clearTimeout(hoverTimeout);
                hoverTimeout = null;
            }
            openTooltip(wrapper);
        });
        wrapper.addEventListener('mouseleave', () => {
            hoverTimeout = window.setTimeout(() => closeTooltip(wrapper), 90);
        });

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (wrapper.classList.contains('is-open')) {
                    closeTooltip(wrapper);
                    return;
                }
                openTooltip(wrapper);
            });
        });
    });

    document.addEventListener('click', (event) => {
        if (!activeTooltip) {
            return;
        }
        if (event.target instanceof Element && activeTooltip.contains(event.target)) {
            return;
        }
        closeTooltip(activeTooltip);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activeTooltip) {
            closeTooltip(activeTooltip);
        }
    });

    const semaphoreState = (percent) => {
        if (percent > 120) {
            return 'rojo';
        }
        if (percent > 100) {
            return 'amarillo';
        }
        return 'verde';
    };

    editors.forEach((editor) => {
        const slider = editor.querySelector('.js-dedication-slider');
        const hoursInput = editor.querySelector('.js-dedication-hours');
        const percentLabel = editor.querySelector('.js-dedication-percent');
        const equationLabel = editor.querySelector('.js-dedication-equation');
        const saveButton = editor.querySelector('.js-save-dedication');
        const feedback = editor.querySelector('.js-dedication-feedback');
        const endpoint = editor.dataset.endpoint || '';
        const capacityWeek = parseFloat(editor.dataset.capacityWeek || '40') || 40;
        const baseSliderMax = parseFloat(slider?.max || '200') || 200;

        if (!slider || !hoursInput || !percentLabel || !equationLabel || !saveButton || endpoint === '') {
            return;
        }

        const isReadOnly = slider.disabled || hoursInput.disabled;
        let editedField = 'allocation_percent';
        let saving = false;
        let initialPercent = round(parseFloat(slider.value || '0') || 0, 2);
        let initialHours = round(parseFloat(hoursInput.value || '0') || 0, 2);

        const setFeedback = (message, state = 'neutral') => {
            feedback.textContent = message;
            editor.classList.remove('has-error', 'is-success');
            if (state === 'error') {
                editor.classList.add('has-error');
            }
            if (state === 'success') {
                editor.classList.add('is-success');
            }
        };

        const clearErrorState = () => {
            if (!editor.classList.contains('has-error')) {
                return;
            }
            editor.classList.remove('has-error');
            feedback.textContent = '';
        };

        const computeCurrentValues = () => {
            const percentRaw = parseFloat(percentLabel.textContent.replace('%', '').trim());
            const hoursRaw = parseFloat(hoursInput.value || '0');
            return {
                percent: round(Number.isFinite(percentRaw) ? percentRaw : 0, 2),
                hours: round(Number.isFinite(hoursRaw) ? hoursRaw : 0, 2),
            };
        };

        const syncSliderRange = (percent) => {
            const safePercent = Number.isFinite(percent) ? Math.max(0, percent) : 0;
            const nextMax = Math.max(baseSliderMax, Math.ceil(safePercent / 10) * 10);
            slider.max = String(nextMax);
        };

        const renderValues = (percent, hours) => {
            percentLabel.textContent = `${formatValue(percent, 1)}%`;
            hoursInput.value = round(hours, 2).toFixed(2);
            equationLabel.textContent = `${formatValue(percent, 1)}% \u2192 ${formatValue(hours, 2)}h/sem`;
        };

        const validate = () => {
            const { percent, hours } = computeCurrentValues();
            if (hours < 0) {
                setFeedback('Las horas semanales no pueden ser negativas.', 'error');
                saveButton.disabled = true;
                return false;
            }
            if (percent < 0) {
                setFeedback('El porcentaje no puede ser negativo.', 'error');
                saveButton.disabled = true;
                return false;
            }
            const changed = round(percent, 2) !== round(initialPercent, 2)
                || round(hours, 2) !== round(initialHours, 2);
            saveButton.disabled = !changed || saving || isReadOnly;
            if (changed && editor.classList.contains('is-success')) {
                editor.classList.remove('is-success');
            }
            if (!saving && changed && !editor.classList.contains('has-error')) {
                if (percent > 100) {
                    const over = percent - 100;
                    const semaphore = semaphoreState(percent);
                    setFeedback(`⚠️ Sobreasignación ${formatValue(over, 1)}% (semáforo ${semaphore}). El recurso está sobreasignado, pero se permite continuar bajo responsabilidad del PM.`);
                } else {
                    setFeedback('Cambios pendientes por guardar.');
                }
            }
            if (!changed && !saving && !isReadOnly && !editor.classList.contains('has-error')) {
                setFeedback('');
            }
            return true;
        };

        const syncFromPercent = () => {
            const rawPercent = parseFloat(slider.value || '0');
            const percent = Math.max(0, Number.isFinite(rawPercent) ? rawPercent : 0);
            const hours = capacityWeek * (percent / 100);
            syncSliderRange(percent);
            renderValues(percent, hours);
            validate();
        };

        const syncFromHours = () => {
            const rawHours = parseFloat(hoursInput.value || '0');
            const hours = Number.isFinite(rawHours) ? rawHours : 0;
            const percent = capacityWeek > 0 ? (hours / capacityWeek) * 100 : 0;
            syncSliderRange(percent);
            slider.value = String(clamp(percent, 0, Number(slider.max)));
            renderValues(percent, hours);
            validate();
        };

        const save = async (forceUpdate = false) => {
            if (isReadOnly || !validate()) {
                return;
            }

            saving = true;
            saveButton.disabled = true;
            setFeedback('Guardando cambios...');

            try {
                const { percent, hours } = computeCurrentValues();
                const payload = new URLSearchParams();
                payload.set('allocation_percent', String(round(percent, 2)));
                payload.set('weekly_hours', String(round(hours, 2)));
                payload.set('edited_field', editedField);
                if (forceUpdate) {
                    payload.set('force_update', '1');
                }

                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: payload.toString(),
                });

                const result = await response.json().catch(() => ({}));
                if (response.status === 409 && result.requires_confirmation) {
                    const data = result.data || {};
                    const warningText = [
                        result.message || 'Las horas registradas en timesheet superan la nueva dedicación.',
                        `Horas asignadas: ${formatValue(Number(data.assigned_weekly_hours || hours), 2)}h/sem`,
                        `Máximo registrado: ${formatValue(Number(data.max_logged_weekly_hours || 0), 2)}h/sem`,
                        '',
                        'Aceptar: Ajustar dedicación',
                        'Cancelar: Mantener valor actual',
                    ].join('\n');

                    if (window.confirm(warningText)) {
                        await save(true);
                        return;
                    }

                    setFeedback('Cambio cancelado por el usuario.');
                    return;
                }

                if (!response.ok || result.success !== true) {
                    throw new Error(result.message || 'No se pudo guardar la dedicación.');
                }

                const saved = result.data || {};
                const after = saved.after || {};
                const finalPercent = Number(after.allocation_percent ?? percent);
                const finalHours = Number(after.weekly_hours ?? hours);

                initialPercent = round(finalPercent, 2);
                initialHours = round(finalHours, 2);
                syncSliderRange(finalPercent);
                slider.value = String(clamp(finalPercent, 0, Number(slider.max)));
                renderValues(finalPercent, finalHours);
                if (finalPercent > 100) {
                    const over = finalPercent - 100;
                    const semaphore = semaphoreState(finalPercent);
                    setFeedback(`Guardado con sobreasignación ${formatValue(over, 1)}% (semáforo ${semaphore}). El recurso está sobreasignado, pero se permite continuar bajo responsabilidad del PM.`, 'success');
                } else {
                    setFeedback('Dedicación actualizada correctamente.', 'success');
                }
            } catch (error) {
                const message = error instanceof Error ? error.message : 'No se pudo guardar la dedicación.';
                setFeedback(message, 'error');
            } finally {
                saving = false;
                validate();
            }
        };

        slider.addEventListener('input', () => {
            clearErrorState();
            editedField = 'allocation_percent';
            syncFromPercent();
        });

        hoursInput.addEventListener('input', () => {
            clearErrorState();
            editedField = 'weekly_hours';
            syncFromHours();
        });

        saveButton.addEventListener('click', () => {
            save(false);
        });

        validate();
    });
})();
</script>
