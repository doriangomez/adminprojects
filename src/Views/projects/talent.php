<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$talents = is_array($talents ?? null) ? $talents : [];
$allocationSummaries = is_array($allocationSummaries ?? null) ? $allocationSummaries : [];
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
                    $aid            = (int) ($assignment['id'] ?? 0);
                    $pid            = (int) ($project['id'] ?? 0);
                    $tid            = (int) ($assignment['talent_id'] ?? 0);
                    $capacidad      = (float) ($assignment['capacidad_horaria'] ?? 0);
                    $allocPct       = (float) ($assignment['allocation_percent'] ?? 0);
                    $weeklyHours    = (float) ($assignment['weekly_hours'] ?? 0);
                    $assignmentStatus = (string) ($assignment['assignment_status'] ?? 'active');
                    $canEdit        = $assignmentStatus === 'active';
                    $summary        = $allocationSummaries[$tid] ?? [];
                    $totalPct       = array_sum(array_column($summary, 'allocation_percent'));
                    $availablePct   = max(0, 100 - $totalPct);
                    ?>
                    <div class="talent-row" id="row-<?= $aid ?>">
                        <div>
                            <strong><?= htmlspecialchars($assignment['talent_name'] ?? 'Talento') ?></strong>
                            <small class="section-muted"><?= htmlspecialchars($assignment['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($assignment['end_date'] ?? 'N/A') ?></small>
                        </div>
                        <span><?= htmlspecialchars($assignment['role'] ?? '') ?></span>

                        <!-- Dedicación: vista normal o editor inline -->
                        <div class="dedication-cell" id="ded-<?= $aid ?>">
                            <div class="ded-display" id="ded-display-<?= $aid ?>">
                                <div class="ded-track-wrap">
                                    <div class="ded-track">
                                        <div class="ded-fill" style="width:<?= min(100, $allocPct) ?>%"></div>
                                    </div>
                                    <span class="ded-pct"><?= number_format($allocPct, 0) ?>%</span>
                                </div>
                                <span class="ded-hours"><?= number_format($weeklyHours, 1) ?>h/sem</span>
                                <?php if ($canEdit): ?>
                                    <button type="button" class="ded-edit-btn" onclick="openDedicationEditor(<?= $aid ?>, <?= $allocPct ?>, <?= $weeklyHours ?>, <?= $capacidad ?>, '<?= $basePath ?>/projects/<?= $pid ?>/talent/assignments/<?= $aid ?>/dedication')" title="Editar dedicación">&#9998;</button>
                                <?php endif; ?>
                            </div>

                            <?php if ($canEdit): ?>
                            <div class="ded-editor" id="ded-editor-<?= $aid ?>" style="display:none;">
                                <div class="ded-editor-inner">
                                    <div class="ded-slider-row">
                                        <input
                                            type="range"
                                            class="ded-slider"
                                            id="slider-<?= $aid ?>"
                                            min="0" max="100" step="1"
                                            value="<?= (int) $allocPct ?>"
                                            oninput="onSliderChange(<?= $aid ?>, <?= $capacidad ?>)"
                                        >
                                        <input
                                            type="number"
                                            class="ded-pct-input"
                                            id="pct-input-<?= $aid ?>"
                                            min="0" max="100" step="0.1"
                                            value="<?= number_format($allocPct, 1) ?>"
                                            oninput="onPctInputChange(<?= $aid ?>, <?= $capacidad ?>)"
                                        >
                                        <span class="ded-pct-label">%</span>
                                    </div>
                                    <div class="ded-hours-row">
                                        <input
                                            type="number"
                                            class="ded-hours-input"
                                            id="hrs-input-<?= $aid ?>"
                                            min="0" step="0.5"
                                            value="<?= number_format($weeklyHours, 1) ?>"
                                            oninput="onHoursInputChange(<?= $aid ?>, <?= $capacidad ?>)"
                                        >
                                        <span class="ded-hrs-label">h/sem</span>
                                        <?php if ($capacidad > 0): ?>
                                            <span class="ded-capacity-hint">de <?= number_format($capacidad, 0) ?>h capacidad</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($summary)): ?>
                                    <div class="ded-capacity-breakdown">
                                        <p class="ded-breakdown-title">Capacidad de <?= htmlspecialchars($assignment['talent_name'] ?? '') ?></p>
                                        <?php foreach ($summary as $s): ?>
                                            <div class="ded-breakdown-row <?= $s['project_id'] == $pid ? 'ded-breakdown-current' : '' ?>">
                                                <span class="ded-breakdown-name"><?= htmlspecialchars($s['project_name'] ?? '') ?><?= $s['project_id'] == $pid ? ' ★' : '' ?></span>
                                                <div class="ded-breakdown-bar-wrap">
                                                    <div class="ded-breakdown-bar" style="width:<?= min(100, (float)$s['allocation_percent']) ?>%"></div>
                                                </div>
                                                <span class="ded-breakdown-pct"><?= number_format((float)$s['allocation_percent'], 0) ?>%</span>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="ded-breakdown-divider"></div>
                                        <div class="ded-breakdown-row ded-breakdown-total">
                                            <span class="ded-breakdown-name">Ocupado</span>
                                            <div class="ded-breakdown-bar-wrap">
                                                <div class="ded-breakdown-bar ded-bar-occupied" style="width:<?= min(100, $totalPct) ?>%"></div>
                                            </div>
                                            <span class="ded-breakdown-pct"><?= number_format($totalPct, 0) ?>%</span>
                                        </div>
                                        <div class="ded-breakdown-row ded-breakdown-avail">
                                            <span class="ded-breakdown-name">Disponible</span>
                                            <div class="ded-breakdown-bar-wrap">
                                                <div class="ded-breakdown-bar ded-bar-available" style="width:<?= min(100, $availablePct) ?>%"></div>
                                            </div>
                                            <span class="ded-breakdown-pct"><?= number_format($availablePct, 0) ?>%</span>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="ded-editor-actions">
                                        <button type="button" class="action-btn" onclick="closeDedicationEditor(<?= $aid ?>)">Cancelar</button>
                                        <button type="button" class="action-btn primary" onclick="saveDedication(<?= $aid ?>, '<?= $basePath ?>/projects/<?= $pid ?>/talent/assignments/<?= $aid ?>/dedication')">Guardar</button>
                                    </div>
                                    <p class="ded-editor-error" id="ded-error-<?= $aid ?>" style="display:none;"></p>
                                </div>
                            </div>
                            <?php endif; ?>
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
                                <form method="POST" action="<?= $basePath ?>/projects/<?= $pid ?>/talent/assignments/<?= $aid ?>/status" onsubmit="return confirm('¿Inactivar este talento en el proyecto?');">
                                    <input type="hidden" name="assignment_status" value="paused">
                                    <button type="submit" class="action-btn warning">Inactivar</button>
                                </form>
                            <?php elseif ($assignmentStatus === 'paused'): ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= $pid ?>/talent/assignments/<?= $aid ?>/status" onsubmit="return confirm('¿Retirar este talento del proyecto?');">
                                    <input type="hidden" name="assignment_status" value="removed">
                                    <button type="submit" class="action-btn danger">Retirar</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= $pid ?>/talent/assignments/<?= $aid ?>/delete" onsubmit="return confirm('¿Eliminar definitivamente esta asignación retirada? Esta acción no se puede deshacer.');">
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

<!-- Warning modal for timesheet conflict -->
<div id="ded-warning-modal" class="ded-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="ded-modal-title">
    <div class="ded-modal">
        <h3 id="ded-modal-title" class="ded-modal-title">&#9888; Advertencia de dedicación</h3>
        <p id="ded-modal-msg" class="ded-modal-msg"></p>
        <div class="ded-modal-actions">
            <button type="button" class="action-btn" id="ded-modal-cancel">Cancelar</button>
            <button type="button" class="action-btn primary" id="ded-modal-force">Ajustar dedicación</button>
        </div>
    </div>
</div>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; border:1px solid var(--border); padding:16px; border-radius:16px; background: var(--surface); }
    .project-title-block { display:flex; flex-direction:column; gap:6px; }
    .project-title-block h2 { margin:0; color: var(--text-primary); }
    .project-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .talent-overview, .talent-form { border:1px solid var(--border); padding:16px; border-radius:16px; background: var(--surface); display:flex; flex-direction:column; gap:12px; }
    .talent-table { display:grid; gap:8px; }
    .talent-row { display:grid; grid-template-columns: minmax(160px, 1.4fr) minmax(120px, 1fr) minmax(180px, 1.4fr) minmax(90px, 0.7fr) minmax(90px, 0.6fr) minmax(90px, 0.6fr) minmax(120px, 0.8fr); gap:10px; align-items:start; border:1px solid var(--border); border-radius:12px; padding:10px; background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); }
    .talent-head { align-items:center; background: color-mix(in srgb, var(--text-secondary) 14%, var(--background)); font-weight:700; font-size:12px; text-transform:uppercase; color: var(--text-secondary); }
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
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; font-size:13px; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .action-btn.warning { background: color-mix(in srgb, var(--warning) 16%, var(--surface) 84%); color: var(--text-primary); border-color: color-mix(in srgb, var(--warning) 35%, var(--border) 65%); }
    .action-btn.danger { background: color-mix(in srgb, var(--danger) 18%, var(--surface) 82%); color: var(--text-primary); border-color: color-mix(in srgb, var(--danger) 35%, var(--border) 65%); }
    .row-actions { display:flex; gap:6px; flex-wrap:wrap; }

    /* Dedication cell */
    .dedication-cell { display:flex; flex-direction:column; gap:6px; }
    .ded-display { display:flex; flex-direction:column; gap:4px; }
    .ded-track-wrap { display:flex; align-items:center; gap:8px; }
    .ded-track { flex:1; height:6px; border-radius:999px; background: color-mix(in srgb, var(--text-secondary) 20%, var(--background)); overflow:hidden; min-width:60px; }
    .ded-fill { height:100%; background: var(--primary); border-radius:999px; transition:width 0.3s ease; }
    .ded-pct { font-size:13px; font-weight:700; color: var(--text-primary); white-space:nowrap; }
    .ded-hours { font-size:12px; color: var(--text-secondary); }
    .ded-edit-btn { background:none; border:none; cursor:pointer; color: var(--text-secondary); font-size:14px; padding:2px 4px; border-radius:4px; transition:color 0.15s; line-height:1; }
    .ded-edit-btn:hover { color: var(--primary); }

    /* Inline editor */
    .ded-editor { margin-top:4px; }
    .ded-editor-inner { display:flex; flex-direction:column; gap:8px; padding:10px; border:1px solid var(--border); border-radius:10px; background: var(--background); }
    .ded-slider-row { display:flex; align-items:center; gap:8px; }
    .ded-slider { flex:1; accent-color: var(--primary); cursor:pointer; min-width:0; }
    .ded-pct-input { width:60px; padding:4px 6px; border:1px solid var(--border); border-radius:6px; background: var(--surface); color: var(--text-primary); font-size:13px; font-weight:700; text-align:right; }
    .ded-pct-label { font-size:13px; font-weight:700; color: var(--text-secondary); }
    .ded-hours-row { display:flex; align-items:center; gap:6px; }
    .ded-hours-input { width:70px; padding:4px 6px; border:1px solid var(--border); border-radius:6px; background: var(--surface); color: var(--text-primary); font-size:13px; text-align:right; }
    .ded-hrs-label { font-size:13px; color: var(--text-secondary); }
    .ded-capacity-hint { font-size:11px; color: var(--text-secondary); margin-left:4px; }
    .ded-editor-actions { display:flex; gap:6px; margin-top:4px; }
    .ded-editor-error { font-size:12px; color: var(--danger); margin:0; }

    /* Capacity breakdown */
    .ded-capacity-breakdown { display:flex; flex-direction:column; gap:5px; padding:8px; background: color-mix(in srgb, var(--text-secondary) 6%, var(--background)); border-radius:8px; border:1px solid var(--border); }
    .ded-breakdown-title { font-size:11px; font-weight:700; text-transform:uppercase; color: var(--text-secondary); margin:0 0 4px 0; }
    .ded-breakdown-row { display:grid; grid-template-columns: minmax(80px,1fr) 80px 36px; gap:6px; align-items:center; font-size:12px; }
    .ded-breakdown-name { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color: var(--text-primary); }
    .ded-breakdown-bar-wrap { height:6px; background: color-mix(in srgb, var(--text-secondary) 15%, var(--background)); border-radius:999px; overflow:hidden; }
    .ded-breakdown-bar { height:100%; background: var(--primary); border-radius:999px; transition:width 0.3s ease; }
    .ded-bar-occupied { background: color-mix(in srgb, var(--warning) 70%, var(--primary) 30%); }
    .ded-bar-available { background: var(--success); }
    .ded-breakdown-pct { font-size:11px; font-weight:700; text-align:right; color: var(--text-secondary); }
    .ded-breakdown-divider { border-top:1px solid var(--border); margin:2px 0; }
    .ded-breakdown-current .ded-breakdown-name { color: var(--primary); font-weight:600; }
    .ded-breakdown-avail .ded-breakdown-pct { color: var(--success); }

    /* Warning modal */
    .ded-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9000; display:flex; align-items:center; justify-content:center; }
    .ded-modal { background: var(--surface); border:1px solid var(--border); border-radius:16px; padding:24px; max-width:400px; width:90%; display:flex; flex-direction:column; gap:16px; box-shadow:0 8px 40px rgba(0,0,0,0.25); }
    .ded-modal-title { margin:0; font-size:16px; }
    .ded-modal-msg { margin:0; font-size:14px; color: var(--text-secondary); line-height:1.5; }
    .ded-modal-actions { display:flex; gap:8px; justify-content:flex-end; }

    @media (max-width: 900px) {
        .talent-row { grid-template-columns: 1fr; }
        .talent-head { display:none; }
    }
</style>

<script>
(function () {
    const _pendingForce = {};

    window.openDedicationEditor = function (aid, pct, hrs, cap, url) {
        document.getElementById('ded-display-' + aid).style.display = 'none';
        document.getElementById('ded-editor-' + aid).style.display = '';
        const errEl = document.getElementById('ded-error-' + aid);
        if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
    };

    window.closeDedicationEditor = function (aid) {
        document.getElementById('ded-editor-' + aid).style.display = 'none';
        document.getElementById('ded-display-' + aid).style.display = '';
    };

    window.onSliderChange = function (aid, cap) {
        const slider = document.getElementById('slider-' + aid);
        const pctInput = document.getElementById('pct-input-' + aid);
        const hrsInput = document.getElementById('hrs-input-' + aid);
        const pct = parseFloat(slider.value) || 0;
        pctInput.value = pct.toFixed(1);
        if (cap > 0) {
            hrsInput.value = (cap * pct / 100).toFixed(1);
        }
    };

    window.onPctInputChange = function (aid, cap) {
        const pctInput = document.getElementById('pct-input-' + aid);
        const slider = document.getElementById('slider-' + aid);
        const hrsInput = document.getElementById('hrs-input-' + aid);
        let pct = parseFloat(pctInput.value) || 0;
        pct = Math.min(100, Math.max(0, pct));
        slider.value = Math.round(pct);
        if (cap > 0) {
            hrsInput.value = (cap * pct / 100).toFixed(1);
        }
    };

    window.onHoursInputChange = function (aid, cap) {
        const hrsInput = document.getElementById('hrs-input-' + aid);
        const pctInput = document.getElementById('pct-input-' + aid);
        const slider = document.getElementById('slider-' + aid);
        const hrs = parseFloat(hrsInput.value) || 0;
        if (cap > 0) {
            const pct = Math.min(100, Math.max(0, (hrs / cap) * 100));
            pctInput.value = pct.toFixed(1);
            slider.value = Math.round(pct);
        }
    };

    window.saveDedication = function (aid, url, force) {
        const pctInput = document.getElementById('pct-input-' + aid);
        const hrsInput = document.getElementById('hrs-input-' + aid);
        const errEl = document.getElementById('ded-error-' + aid);

        const pct = parseFloat(pctInput.value);
        const hrs = parseFloat(hrsInput.value);

        if (isNaN(pct) || pct < 0 || pct > 100) {
            showEditorError(aid, 'El porcentaje debe estar entre 0 y 100.');
            return;
        }
        if (isNaN(hrs) || hrs < 0) {
            showEditorError(aid, 'Las horas no pueden ser negativas.');
            return;
        }

        const body = new URLSearchParams();
        body.set('allocation_percent', pct.toString());
        body.set('weekly_hours', hrs.toString());
        if (force) { body.set('force', '1'); }

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.warning) {
                showTimesheetWarning(aid, url, data);
                return;
            }
            if (data.error) {
                showEditorError(aid, data.error);
                return;
            }
            if (data.success) {
                applyDedicationUpdate(aid, data.allocation_percent, data.weekly_hours);
                closeDedicationEditor(aid);
            }
        })
        .catch(function () {
            showEditorError(aid, 'Error de red. Intenta de nuevo.');
        });
    };

    function showEditorError(aid, msg) {
        const errEl = document.getElementById('ded-error-' + aid);
        if (!errEl) { return; }
        errEl.textContent = msg;
        errEl.style.display = '';
    }

    function applyDedicationUpdate(aid, pct, hrs) {
        const display = document.getElementById('ded-display-' + aid);
        if (!display) { return; }
        const fill = display.querySelector('.ded-fill');
        const pctSpan = display.querySelector('.ded-pct');
        const hrsSpan = display.querySelector('.ded-hours');
        if (fill) { fill.style.width = Math.min(100, pct) + '%'; }
        if (pctSpan) { pctSpan.textContent = Math.round(pct) + '%'; }
        if (hrsSpan) { hrsSpan.textContent = parseFloat(hrs).toFixed(1) + 'h/sem'; }
    }

    function showTimesheetWarning(aid, url, data) {
        const modal = document.getElementById('ded-warning-modal');
        const msg = document.getElementById('ded-modal-msg');
        const cancelBtn = document.getElementById('ded-modal-cancel');
        const forceBtn = document.getElementById('ded-modal-force');

        msg.textContent = data.message +
            ' (Timesheet: ' + parseFloat(data.timesheet_hours).toFixed(1) + 'h/sem' +
            ' > Nueva dedicación: ' + parseFloat(data.new_weekly_hours).toFixed(1) + 'h/sem)';

        modal.style.display = 'flex';

        const close = function () {
            modal.style.display = 'none';
            cancelBtn.removeEventListener('click', cancelHandler);
            forceBtn.removeEventListener('click', forceHandler);
        };
        const cancelHandler = function () { close(); };
        const forceHandler = function () {
            close();
            saveDedication(aid, url, true);
        };
        cancelBtn.addEventListener('click', cancelHandler);
        forceBtn.addEventListener('click', forceHandler);
    }
})();
</script>
