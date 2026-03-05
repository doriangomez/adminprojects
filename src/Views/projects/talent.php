<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$talents = is_array($talents ?? null) ? $talents : [];
$talentAllocationMap = $talentAllocationMap ?? [];
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
                    $talentId = (int) ($assignment['talent_id'] ?? 0);
                    $allocation = $talentAllocationMap[$talentId] ?? ['total_percent' => 0, 'assignments' => []];
                    $capacidadSemana = (float) ($assignment['capacidad_horaria'] ?? 40);
                    if ($capacidadSemana <= 0) {
                        $capacidadSemana = 40.0;
                    }
                    $canEditDedication = $assignmentStatus === 'active';
                    ?>
                    <div class="talent-row" data-assignment-id="<?= (int) ($assignment['id'] ?? 0) ?>">
                        <div>
                            <strong><?= htmlspecialchars($assignment['talent_name'] ?? 'Talento') ?></strong>
                            <small class="section-muted"><?= htmlspecialchars($assignment['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($assignment['end_date'] ?? 'N/A') ?></small>
                            <?php if (!empty($allocation['assignments'])): ?>
                                <div class="talent-capacity-summary">
                                    <?php foreach ($allocation['assignments'] as $a): ?>
                                        <small><?= htmlspecialchars((string) round($a['percent'], 0)) ?>% <?= htmlspecialchars($a['project_name']) ?></small>
                                    <?php endforeach; ?>
                                    <small class="capacity-divider"><?= htmlspecialchars((string) round($allocation['total_percent'], 0)) ?>% ocupado · <?= htmlspecialchars((string) max(0, round(100 - $allocation['total_percent'], 0))) ?>% disponible</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span><?= htmlspecialchars($assignment['role'] ?? '') ?></span>
                        <div class="dedication-cell">
                            <?php if ($canEditDedication): ?>
                                <div class="dedication-slider-wrap" data-capacidad="<?= htmlspecialchars((string) $capacidadSemana) ?>">
                                    <input type="range" class="dedication-slider" min="0" max="100" step="1"
                                           value="<?= (int) round((float) ($assignment['allocation_percent'] ?? 0)) ?>"
                                           data-assignment-id="<?= (int) ($assignment['id'] ?? 0) ?>">
                                    <div class="dedication-display">
                                        <span class="dedication-percent"><?= (int) round((float) ($assignment['allocation_percent'] ?? 0)) ?>%</span>
                                        <span class="dedication-hours"><?= number_format((float) ($assignment['weekly_hours'] ?? 0), 1) ?>h/sem</span>
                                    </div>
                                    <button type="button" class="dedication-save-btn action-btn primary small" style="display:none">Guardar</button>
                                </div>
                            <?php else: ?>
                                <span><?= htmlspecialchars((string) round((float) ($assignment['allocation_percent'] ?? 0))) ?>% · <?= htmlspecialchars((string) ($assignment['weekly_hours'] ?? 0)) ?>h/sem</span>
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
    .talent-row { display:grid; grid-template-columns: minmax(160px, 1.4fr) minmax(120px, 1fr) minmax(120px, 1fr) minmax(90px, 0.7fr) minmax(90px, 0.6fr) minmax(90px, 0.6fr) minmax(120px, 0.8fr); gap:10px; align-items:center; border:1px solid var(--border); border-radius:12px; padding:10px; background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); }
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
    .talent-capacity-summary { display:flex; flex-direction:column; gap:2px; margin-top:6px; }
    .talent-capacity-summary small { font-size:11px; color: var(--text-secondary); }
    .capacity-divider { border-top:1px dashed var(--border); padding-top:4px; margin-top:2px; font-weight:600; }
    .dedication-cell { display:flex; flex-direction:column; gap:4px; min-width:140px; }
    .dedication-slider-wrap { display:flex; flex-direction:column; gap:6px; }
    .dedication-slider { width:100%; min-width:100px; height:8px; -webkit-appearance:none; appearance:none; background: color-mix(in srgb, var(--text-secondary) 25%, var(--background)); border-radius:4px; }
    .dedication-slider::-webkit-slider-thumb { -webkit-appearance:none; width:18px; height:18px; border-radius:50%; background: var(--primary); cursor:pointer; border:2px solid var(--surface); box-shadow:0 1px 3px rgba(0,0,0,0.2); }
    .dedication-slider::-moz-range-thumb { width:18px; height:18px; border-radius:50%; background: var(--primary); cursor:pointer; border:2px solid var(--surface); }
    .dedication-display { display:flex; flex-direction:column; gap:0; font-size:13px; }
    .dedication-percent { font-weight:700; }
    .dedication-hours { font-size:11px; color: var(--text-secondary); }
    .dedication-save-btn { align-self:flex-start; }
    .timesheet-exceeds-modal { position:fixed; inset:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
    .timesheet-exceeds-modal__content { background: var(--surface); border-radius:16px; padding:24px; max-width:420px; box-shadow:0 8px 32px rgba(0,0,0,0.2); }
    .timesheet-exceeds-modal__title { font-size:18px; font-weight:700; margin:0 0 12px; }
    .timesheet-exceeds-modal__msg { color: var(--text-secondary); margin-bottom:20px; line-height:1.5; }
    .timesheet-exceeds-modal__actions { display:flex; gap:10px; }
    @media (max-width: 900px) {
        .talent-row { grid-template-columns: 1fr; }
        .talent-head { display:none; }
    }
</style>
<script>
(function() {
    const basePath = <?= json_encode($basePath) ?>;
    const projectId = <?= (int) ($project['id'] ?? 0) ?>;

    document.querySelectorAll('.dedication-slider-wrap').forEach(function(wrap) {
        const slider = wrap.querySelector('.dedication-slider');
        const percentEl = wrap.querySelector('.dedication-percent');
        const hoursEl = wrap.querySelector('.dedication-hours');
        const saveBtn = wrap.querySelector('.dedication-save-btn');
        const capacidad = parseFloat(wrap.dataset.capacidad || 40);
        const assignmentId = parseInt(slider.dataset.assignmentId, 10);

        function updateDisplay() {
            const pct = parseInt(slider.value, 10);
            const hours = capacidad * (pct / 100);
            percentEl.textContent = pct + '%';
            hoursEl.textContent = hours.toFixed(1) + 'h/sem';
        }

        slider.addEventListener('input', function() {
            updateDisplay();
            saveBtn.style.display = 'inline-flex';
        });

        updateDisplay();

        saveBtn.addEventListener('click', function() {
            const pct = parseFloat(slider.value);
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';

            fetch(basePath + '/projects/' + projectId + '/talent/assignments/' + assignmentId + '/allocation', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ allocation_percent: pct })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    percentEl.textContent = Math.round(data.allocation_percent) + '%';
                    hoursEl.textContent = data.weekly_hours.toFixed(1) + 'h/sem';
                    slider.value = Math.round(data.allocation_percent);
                    saveBtn.style.display = 'none';
                    window.location.reload();
                } else if (data.code === 'TIMESHEET_EXCEEDS') {
                    showTimesheetExceedsModal(data, slider, saveBtn, wrap, capacidad, assignmentId);
                } else {
                    alert(data.error || 'Error al guardar');
                }
            })
            .catch(function() { alert('Error de conexión'); })
            .finally(function() { saveBtn.disabled = false; saveBtn.textContent = 'Guardar'; });
        });
    });

    function showTimesheetExceedsModal(data, slider, saveBtn, wrap, capacidad, assignmentId) {
        const existing = document.getElementById('timesheet-exceeds-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'timesheet-exceeds-modal';
        modal.className = 'timesheet-exceeds-modal';
        modal.innerHTML = '<div class="timesheet-exceeds-modal__content">' +
            '<h3 class="timesheet-exceeds-modal__title">Advertencia</h3>' +
            '<p class="timesheet-exceeds-modal__msg">' + (data.error || 'Las horas registradas en timesheet superan la nueva dedicación.') + '</p>' +
            '<div class="timesheet-exceeds-modal__actions">' +
            '<button type="button" class="action-btn" data-action="cancel">Cancelar</button>' +
            '<button type="button" class="action-btn primary" data-action="adjust">Ajustar dedicación</button>' +
            '</div></div>';
        document.body.appendChild(modal);

        modal.querySelector('[data-action="cancel"]').addEventListener('click', function() {
            modal.remove();
            saveBtn.style.display = 'none';
            slider.value = slider.dataset.originalValue || 0;
            wrap.querySelector('.dedication-percent').textContent = (slider.dataset.originalValue || 0) + '%';
            const h = capacidad * ((slider.dataset.originalValue || 0) / 100);
            wrap.querySelector('.dedication-hours').textContent = h.toFixed(1) + 'h/sem';
        });

        modal.querySelector('[data-action="adjust"]').addEventListener('click', function() {
            const minPct = data.min_percent || 0;
            slider.value = Math.ceil(minPct);
            slider.dispatchEvent(new Event('input'));
            modal.remove();
            saveBtn.click();
        });

        slider.dataset.originalValue = slider.value;
    }
})();
</script>
