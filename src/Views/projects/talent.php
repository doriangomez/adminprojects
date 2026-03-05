<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$talents = is_array($talents ?? null) ? $talents : [];
$talentCapacityMap = is_array($talentCapacityMap ?? null) ? $talentCapacityMap : [];
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
$projectId = (int) ($project['id'] ?? 0);
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Talento</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Equipo asignado, roles y dedicaciones críticas.</small>
        </div>
        <div class="project-actions">
            <a class="action-btn" href="<?= $basePath ?>/projects/<?= $projectId ?>?view=resumen">Volver al resumen</a>
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
                <?php foreach ($assignments as $assignment):
                    $aId = (int) ($assignment['id'] ?? 0);
                    $talentId = (int) ($assignment['talent_id'] ?? 0);
                    $allocPercent = (float) ($assignment['allocation_percent'] ?? 0);
                    $wHours = (float) ($assignment['weekly_hours'] ?? 0);
                    $capacidad = (float) ($assignment['capacidad_horaria'] ?? 40);
                    if ($capacidad <= 0) { $capacidad = 40; }
                    $assignmentStatus = (string) ($assignment['assignment_status'] ?? 'active');
                    $isActive = $assignmentStatus === 'active';

                    $capData = $talentCapacityMap[$talentId] ?? null;
                    $allAssignments = $capData ? ($capData['assignments'] ?? []) : [];
                    $totalOccupied = 0;
                    foreach ($allAssignments as $ta) {
                        $totalOccupied += (float) ($ta['allocation_percent'] ?? 0);
                    }
                    $available = max(0, 100 - $totalOccupied);
                ?>
                    <div class="talent-row" data-assignment-id="<?= $aId ?>">
                        <div>
                            <strong><?= htmlspecialchars($assignment['talent_name'] ?? 'Talento') ?></strong>
                            <small class="section-muted"><?= htmlspecialchars($assignment['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($assignment['end_date'] ?? 'N/A') ?></small>
                        </div>
                        <span><?= htmlspecialchars($assignment['role'] ?? '') ?></span>
                        <div class="dedication-cell" data-assignment-id="<?= $aId ?>" data-project-id="<?= $projectId ?>" data-capacidad="<?= $capacidad ?>" data-talent-id="<?= $talentId ?>">
                            <?php if ($isActive): ?>
                                <div class="dedication-editor">
                                    <div class="slider-row">
                                        <input type="range" class="dedication-slider" min="0" max="100" step="1" value="<?= (int) round($allocPercent) ?>" data-assignment="<?= $aId ?>">
                                        <span class="slider-value"><?= (int) round($allocPercent) ?>%</span>
                                    </div>
                                    <div class="hours-display">
                                        <input type="number" class="hours-input" min="0" max="<?= $capacidad ?>" step="0.5" value="<?= number_format($wHours, 1, '.', '') ?>" data-assignment="<?= $aId ?>">
                                        <span class="hours-suffix">h/sem</span>
                                    </div>
                                    <div class="dedication-actions">
                                        <button type="button" class="save-dedication-btn action-btn primary" data-assignment="<?= $aId ?>" style="display:none;">Guardar</button>
                                    </div>
                                    <div class="capacity-breakdown" data-talent-id="<?= $talentId ?>">
                                        <?php foreach ($allAssignments as $ta): ?>
                                            <div class="capacity-line <?= ((int) ($ta['id'] ?? 0)) === $aId ? 'current-assignment' : '' ?>">
                                                <span class="cap-project"><?= htmlspecialchars($ta['project_name'] ?? '') ?></span>
                                                <span class="cap-percent" <?= ((int) ($ta['id'] ?? 0)) === $aId ? 'data-current-cap="true"' : '' ?>><?= (int) round((float) ($ta['allocation_percent'] ?? 0)) ?>%</span>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="capacity-separator"></div>
                                        <div class="capacity-line capacity-total">
                                            <span class="cap-project">Ocupado</span>
                                            <span class="cap-percent cap-occupied"><?= (int) round($totalOccupied) ?>%</span>
                                        </div>
                                        <div class="capacity-line capacity-available">
                                            <span class="cap-project">Disponible</span>
                                            <span class="cap-percent cap-available"><?= (int) round($available) ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span><?= htmlspecialchars((string) $allocPercent) ?>% · <?= htmlspecialchars((string) $wHours) ?>h/sem</span>
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
                                <form method="POST" action="<?= $basePath ?>/projects/<?= $projectId ?>/talent/assignments/<?= $aId ?>/status" onsubmit="return confirm('¿Inactivar este talento en el proyecto?');">
                                    <input type="hidden" name="assignment_status" value="paused">
                                    <button type="submit" class="action-btn warning">Inactivar</button>
                                </form>
                            <?php elseif ($assignmentStatus === 'paused'): ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= $projectId ?>/talent/assignments/<?= $aId ?>/status" onsubmit="return confirm('¿Retirar este talento del proyecto?');">
                                    <input type="hidden" name="assignment_status" value="removed">
                                    <button type="submit" class="action-btn danger">Retirar</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= $projectId ?>/talent/assignments/<?= $aId ?>/delete" onsubmit="return confirm('¿Eliminar definitivamente esta asignación retirada? Esta acción no se puede deshacer.');">
                                    <button type="submit" class="action-btn danger">Eliminar definitivo</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <form action="<?= $basePath ?>/projects/<?= $projectId ?>/talent" method="POST" class="talent-form" id="talent-management">
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

<!-- Timesheet warning modal -->
<div id="dedication-warning-modal" class="modal-overlay" style="display:none;">
    <div class="modal-card">
        <h4>Advertencia de timesheet</h4>
        <p id="dedication-warning-text"></p>
        <div class="modal-actions">
            <button type="button" class="action-btn" id="dedication-cancel-btn">Cancelar</button>
            <button type="button" class="action-btn warning" id="dedication-force-btn">Ajustar dedicación</button>
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
    .talent-row { display:grid; grid-template-columns: minmax(160px, 1.4fr) minmax(120px, 1fr) minmax(200px, 1.6fr) minmax(90px, 0.7fr) minmax(90px, 0.6fr) minmax(90px, 0.6fr) minmax(120px, 0.8fr); gap:10px; align-items:center; border:1px solid var(--border); border-radius:12px; padding:10px; background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); }
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
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .action-btn.warning { background: color-mix(in srgb, var(--warning) 16%, var(--surface) 84%); color: var(--text-primary); border-color: color-mix(in srgb, var(--warning) 35%, var(--border) 65%); }
    .action-btn.danger { background: color-mix(in srgb, var(--danger) 18%, var(--surface) 82%); color: var(--text-primary); border-color: color-mix(in srgb, var(--danger) 35%, var(--border) 65%); }
    .row-actions { display:flex; gap:6px; flex-wrap:wrap; }

    .dedication-cell { min-width: 200px; }
    .dedication-editor { display:flex; flex-direction:column; gap:6px; }
    .slider-row { display:flex; align-items:center; gap:8px; }
    .dedication-slider {
        flex:1; height:6px; -webkit-appearance:none; appearance:none; border-radius:999px;
        background: linear-gradient(to right, var(--primary) 0%, var(--primary) var(--val, 50%), color-mix(in srgb, var(--text-secondary) 20%, var(--background)) var(--val, 50%));
        outline:none; cursor:pointer;
    }
    .dedication-slider::-webkit-slider-thumb {
        -webkit-appearance:none; width:18px; height:18px; border-radius:50%;
        background: var(--primary); border:2px solid var(--surface); box-shadow:0 1px 4px rgba(0,0,0,0.3); cursor:pointer;
    }
    .dedication-slider::-moz-range-thumb {
        width:18px; height:18px; border-radius:50%;
        background: var(--primary); border:2px solid var(--surface); box-shadow:0 1px 4px rgba(0,0,0,0.3); cursor:pointer;
    }
    .slider-value { font-weight:700; font-size:14px; min-width:40px; text-align:right; color: var(--text-primary); }
    .hours-display { display:flex; align-items:center; gap:4px; }
    .hours-input {
        width:60px; padding:4px 6px; border:1px solid var(--border); border-radius:6px; font-size:13px;
        background: var(--surface); color: var(--text-primary); text-align:center;
    }
    .hours-input:focus { border-color: var(--primary); outline:none; box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 25%, transparent); }
    .hours-suffix { font-size:12px; color: var(--text-secondary); }
    .dedication-actions { display:flex; gap:4px; }
    .save-dedication-btn { font-size:12px; padding:4px 10px; }

    .capacity-breakdown {
        margin-top:4px; padding:6px 8px; border-radius:8px; font-size:11px;
        background: color-mix(in srgb, var(--text-secondary) 6%, var(--background));
        border: 1px solid color-mix(in srgb, var(--border) 50%, transparent);
    }
    .capacity-line { display:flex; justify-content:space-between; padding:1px 0; }
    .capacity-line.current-assignment { font-weight:700; color: var(--primary); }
    .cap-project { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:120px; }
    .cap-percent { font-weight:600; min-width:30px; text-align:right; }
    .capacity-separator { border-top:1px dashed var(--border); margin:3px 0; }
    .capacity-total .cap-percent { color: var(--text-primary); }
    .capacity-available .cap-percent { color: var(--success); }

    .modal-overlay {
        position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999;
        display:flex; align-items:center; justify-content:center; padding:20px;
    }
    .modal-card {
        background: var(--surface); border:1px solid var(--border); border-radius:16px;
        padding:24px; max-width:440px; width:100%; display:flex; flex-direction:column; gap:12px;
    }
    .modal-card h4 { margin:0; }
    .modal-actions { display:flex; gap:8px; justify-content:flex-end; }

    @media (max-width: 900px) {
        .talent-row { grid-template-columns: 1fr; }
        .talent-head { display:none; }
    }
</style>

<script>
(function() {
    var projectId = <?= $projectId ?>;

    document.querySelectorAll('.dedication-slider').forEach(function(slider) {
        var cell = slider.closest('.dedication-cell');
        var capacidad = parseFloat(cell.dataset.capacidad) || 40;
        var hoursInput = cell.querySelector('.hours-input');
        var valueLabel = cell.querySelector('.slider-value');
        var saveBtn = cell.querySelector('.save-dedication-btn');
        var originalPercent = parseInt(slider.value, 10);
        var originalHours = parseFloat(hoursInput.value);

        function updateSliderTrack(s) {
            s.style.setProperty('--val', s.value + '%');
        }
        updateSliderTrack(slider);

        slider.addEventListener('input', function() {
            var pct = parseInt(this.value, 10);
            valueLabel.textContent = pct + '%';
            var hours = (capacidad * pct / 100).toFixed(1);
            hoursInput.value = hours;
            updateSliderTrack(this);
            updateCapacityBreakdown(cell, pct);
            toggleSaveButton(saveBtn, pct, parseFloat(hours), originalPercent, originalHours);
        });

        hoursInput.addEventListener('input', function() {
            var hours = parseFloat(this.value) || 0;
            if (hours < 0) { hours = 0; this.value = '0'; }
            if (hours > capacidad) { hours = capacidad; this.value = capacidad.toFixed(1); }
            var pct = capacidad > 0 ? Math.round((hours / capacidad) * 100) : 0;
            slider.value = pct;
            valueLabel.textContent = pct + '%';
            updateSliderTrack(slider);
            updateCapacityBreakdown(cell, pct);
            toggleSaveButton(saveBtn, pct, hours, originalPercent, originalHours);
        });

        saveBtn.addEventListener('click', function() {
            var assignmentId = this.dataset.assignment;
            var pct = parseInt(slider.value, 10);
            var hours = parseFloat(hoursInput.value) || 0;
            saveDedication(projectId, assignmentId, pct, hours, false, cell, function() {
                originalPercent = pct;
                originalHours = hours;
                saveBtn.style.display = 'none';
            });
        });
    });

    function toggleSaveButton(btn, pct, hours, origPct, origHours) {
        var changed = (pct !== origPct) || (Math.abs(hours - origHours) > 0.05);
        btn.style.display = changed ? '' : 'none';
    }

    function updateCapacityBreakdown(cell, newPct) {
        var currentCap = cell.querySelector('[data-current-cap]');
        if (currentCap) {
            currentCap.textContent = newPct + '%';
        }
        var breakdown = cell.querySelector('.capacity-breakdown');
        if (!breakdown) return;
        var lines = breakdown.querySelectorAll('.capacity-line:not(.capacity-total):not(.capacity-available)');
        var total = 0;
        lines.forEach(function(line) {
            if (line.classList.contains('capacity-separator')) return;
            var pctEl = line.querySelector('.cap-percent');
            if (pctEl) total += parseInt(pctEl.textContent, 10) || 0;
        });
        var occEl = breakdown.querySelector('.cap-occupied');
        var availEl = breakdown.querySelector('.cap-available');
        if (occEl) occEl.textContent = total + '%';
        if (availEl) availEl.textContent = Math.max(0, 100 - total) + '%';
    }

    function saveDedication(projectId, assignmentId, pct, hours, force, cell, onSuccess) {
        var url = '/projects/' + projectId + '/talent/assignments/' + assignmentId + '/dedication';
        var body = JSON.stringify({
            allocation_percent: pct,
            weekly_hours: hours,
            force: force ? true : false
        });

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: body
        })
        .then(function(resp) { return resp.json(); })
        .then(function(data) {
            if (data.success) {
                if (onSuccess) onSuccess();
                showSavedFeedback(cell);
            } else if (data.warning) {
                showWarningModal(data.error, function() {
                    saveDedication(projectId, assignmentId, pct, hours, true, cell, onSuccess);
                });
            } else {
                alert(data.error || 'Error al guardar.');
            }
        })
        .catch(function(err) {
            alert('Error de conexión: ' + err.message);
        });
    }

    function showSavedFeedback(cell) {
        var fb = document.createElement('span');
        fb.textContent = 'Guardado';
        fb.style.cssText = 'color:var(--success);font-size:11px;font-weight:700;margin-left:4px;';
        var actions = cell.querySelector('.dedication-actions');
        if (actions) {
            actions.appendChild(fb);
            setTimeout(function() { fb.remove(); }, 2000);
        }
    }

    var modal = document.getElementById('dedication-warning-modal');
    var warningText = document.getElementById('dedication-warning-text');
    var cancelBtn = document.getElementById('dedication-cancel-btn');
    var forceBtn = document.getElementById('dedication-force-btn');
    var pendingForceCallback = null;

    function showWarningModal(msg, onForce) {
        warningText.textContent = msg;
        modal.style.display = 'flex';
        pendingForceCallback = onForce;
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            pendingForceCallback = null;
        });
    }
    if (forceBtn) {
        forceBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            if (pendingForceCallback) {
                pendingForceCallback();
                pendingForceCallback = null;
            }
        });
    }
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                pendingForceCallback = null;
            }
        });
    }
})();
</script>
