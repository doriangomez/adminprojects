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
                    $availableAllocationPercent = max(0.0, 100.0 - $totalAllocationPercent);
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
                                   max="100"
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
                                <?= htmlspecialchars(number_format($totalAllocationPercent, 1, '.', '')) ?>% ocupado ·
                                <?= htmlspecialchars(number_format($availableAllocationPercent, 1, '.', '')) ?>% disponible
                            </small>
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

    editors.forEach((editor) => {
        const slider = editor.querySelector('.js-dedication-slider');
        const hoursInput = editor.querySelector('.js-dedication-hours');
        const percentLabel = editor.querySelector('.js-dedication-percent');
        const equationLabel = editor.querySelector('.js-dedication-equation');
        const saveButton = editor.querySelector('.js-save-dedication');
        const feedback = editor.querySelector('.js-dedication-feedback');
        const endpoint = editor.dataset.endpoint || '';
        const capacityWeek = parseFloat(editor.dataset.capacityWeek || '40') || 40;

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
            if (percent < 0 || percent > 100) {
                setFeedback('El porcentaje debe estar entre 0 y 100.', 'error');
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
                setFeedback('Cambios pendientes por guardar.');
            }
            if (!changed && !saving && !isReadOnly && !editor.classList.contains('has-error')) {
                setFeedback('');
            }
            return true;
        };

        const syncFromPercent = () => {
            const rawPercent = parseFloat(slider.value || '0');
            const percent = clamp(Number.isFinite(rawPercent) ? rawPercent : 0, 0, 100);
            const hours = capacityWeek * (percent / 100);
            renderValues(percent, hours);
            validate();
        };

        const syncFromHours = () => {
            const rawHours = parseFloat(hoursInput.value || '0');
            const hours = Number.isFinite(rawHours) ? rawHours : 0;
            const percent = capacityWeek > 0 ? (hours / capacityWeek) * 100 : 0;
            slider.value = String(clamp(percent, 0, 100));
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
                slider.value = String(clamp(finalPercent, 0, 100));
                renderValues(finalPercent, finalHours);
                setFeedback('Dedicación actualizada correctamente.', 'success');
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
