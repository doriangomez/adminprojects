<?php
$basePath = $basePath ?? '';
$absences = is_array($absences ?? null) ? $absences : [];
$talents = is_array($talents ?? null) ? $talents : [];
$editingAbsence = is_array($editingAbsence ?? null) ? $editingAbsence : null;
$flashMessage = (string) ($flashMessage ?? '');
$canCreateAbsence = (bool) ($canCreateAbsence ?? false);
$canEditAbsence = (bool) ($canEditAbsence ?? false);
$canDeleteAbsence = (bool) ($canDeleteAbsence ?? false);
$canApproveAbsence = (bool) ($canApproveAbsence ?? false);
$vacationsEnabled = (bool) ($vacationsEnabled ?? true);
$isEditing = !empty($editingAbsence);

$statusLabels = [
    'pendiente' => ['label' => 'Pendiente', 'class' => 'status-warning'],
    'aprobado' => ['label' => 'Aprobado', 'class' => 'status-success'],
    'rechazado' => ['label' => 'Rechazado', 'class' => 'status-danger'],
    'approved' => ['label' => 'Aprobado', 'class' => 'status-success'],
    'rejected' => ['label' => 'Rechazado', 'class' => 'status-danger'],
];
$typeLabels = [
    'vacaciones' => 'Vacaciones',
    'permiso_personal' => 'Permiso personal',
    'permiso_medico' => 'Permiso médico',
    'incapacidad' => 'Incapacidad',
    'licencia' => 'Licencia',
    'capacitacion' => 'Capacitación',
];
$flashMessageText = match ($flashMessage) {
    'created' => 'Ausencia registrada correctamente.',
    'updated' => 'Ausencia actualizada correctamente.',
    'deleted' => 'Ausencia eliminada correctamente.',
    'approved' => 'Estado de ausencia actualizado correctamente.',
    default => '',
};

$editableOrCreatable = (!$isEditing && $canCreateAbsence) || ($isEditing && $canEditAbsence);
$selectedStatus = strtolower(trim((string) ($editingAbsence['status'] ?? 'pendiente')));
$selectedType = strtolower(trim((string) ($editingAbsence['absence_type'] ?? '')));
$selectedIsFullDay = !isset($editingAbsence['is_full_day']) || (int) ($editingAbsence['is_full_day'] ?? 1) === 1;
?>

<section class="absence-shell">
    <header class="absence-header">
        <div>
            <p class="eyebrow">Talento</p>
            <h2>CRUD visual de ausencias</h2>
            <small class="section-muted">Administra vacaciones y permisos con control de estado y permisos por rol.</small>
        </div>
        <?php if ($canCreateAbsence): ?>
            <a class="action-btn primary" href="#absence-form">+ Nueva ausencia</a>
        <?php endif; ?>
    </header>

    <?php if ($flashMessageText !== ''): ?>
        <div class="alert success"><?= htmlspecialchars($flashMessageText) ?></div>
    <?php endif; ?>

    <section class="absence-form-section" id="absence-form">
        <div class="section-head">
            <h3><?= $isEditing ? 'Editar ausencia' : 'Registrar ausencia' ?></h3>
            <?php if ($isEditing): ?>
                <a class="action-btn" href="<?= $basePath ?>/talent-absences">Cancelar edición</a>
            <?php endif; ?>
        </div>

        <?php if ($editableOrCreatable): ?>
            <form method="POST" action="<?= $basePath ?>/talent-absences/<?= $isEditing ? 'update' : 'create' ?>" class="absence-form">
                <?php if ($isEditing): ?>
                    <input type="hidden" name="id" value="<?= (int) ($editingAbsence['id'] ?? 0) ?>">
                <?php endif; ?>
                <div class="grid">
                    <label>Talento
                        <select name="talent_id" required>
                            <option value="">Selecciona talento</option>
                            <?php foreach ($talents as $talent): ?>
                                <?php $selected = (int) ($editingAbsence['talent_id'] ?? 0) === (int) ($talent['id'] ?? 0); ?>
                                <option value="<?= (int) ($talent['id'] ?? 0) ?>" <?= $selected ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($talent['name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Tipo
                        <select name="absence_type" required>
                            <option value="">Selecciona tipo</option>
                            <?php if ($vacationsEnabled): ?>
                                <option value="vacaciones" <?= $selectedType === 'vacaciones' ? 'selected' : '' ?>>Vacaciones</option>
                            <?php endif; ?>
                            <option value="permiso_personal" <?= $selectedType === 'permiso_personal' ? 'selected' : '' ?>>Permiso personal</option>
                            <option value="permiso_medico" <?= $selectedType === 'permiso_medico' ? 'selected' : '' ?>>Permiso médico</option>
                            <option value="incapacidad" <?= $selectedType === 'incapacidad' ? 'selected' : '' ?>>Incapacidad</option>
                            <option value="licencia" <?= $selectedType === 'licencia' ? 'selected' : '' ?>>Licencia</option>
                            <option value="capacitacion" <?= $selectedType === 'capacitacion' ? 'selected' : '' ?>>Capacitación</option>
                        </select>
                    </label>
                </div>
                <div class="grid">
                    <label>Inicio
                        <input type="date" name="start_date" required value="<?= htmlspecialchars((string) ($editingAbsence['start_date'] ?? '')) ?>">
                    </label>
                    <label>Fin
                        <input type="date" name="end_date" required value="<?= htmlspecialchars((string) ($editingAbsence['end_date'] ?? '')) ?>">
                    </label>
                </div>
                <div class="grid">
                    <label class="toggle-switch toggle-switch--row">
                        <span class="toggle-label">Ausencia de día completo</span>
                        <input type="checkbox" name="is_full_day" value="1" data-full-day-toggle <?= $selectedIsFullDay ? 'checked' : '' ?>>
                        <span class="toggle-slider" aria-hidden="true"></span>
                    </label>
                    <label data-hours-field <?= $selectedIsFullDay ? 'hidden' : '' ?>>Horas (solo parcial)
                        <input type="number" name="hours" step="0.25" min="0.25" max="24" value="<?= htmlspecialchars((string) ($editingAbsence['hours'] ?? '')) ?>">
                    </label>
                </div>
                <div class="grid">
                    <?php if ($canApproveAbsence): ?>
                        <label>Estado
                            <select name="status">
                                <option value="pendiente" <?= $selectedStatus === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                <option value="aprobado" <?= in_array($selectedStatus, ['aprobado', 'approved'], true) ? 'selected' : '' ?>>Aprobado</option>
                                <option value="rechazado" <?= in_array($selectedStatus, ['rechazado', 'rejected'], true) ? 'selected' : '' ?>>Rechazado</option>
                            </select>
                        </label>
                    <?php else: ?>
                        <input type="hidden" name="status" value="pendiente">
                        <div class="hint-block">Estado inicial: <strong>Pendiente</strong>. Solo perfiles con permiso de aprobación pueden cambiarlo.</div>
                    <?php endif; ?>
                </div>
                <label>Motivo / Comentario
                    <textarea name="reason" rows="3" placeholder="Ej. Vacaciones planificadas, permiso médico, diligencia personal"><?= htmlspecialchars((string) ($editingAbsence['reason'] ?? '')) ?></textarea>
                </label>
                <button type="submit" class="action-btn primary"><?= $isEditing ? 'Guardar cambios' : 'Crear ausencia' ?></button>
            </form>
        <?php else: ?>
            <div class="alert">Tu perfil no tiene permisos para crear o editar ausencias.</div>
        <?php endif; ?>
    </section>

    <section class="absence-table-section">
        <div class="section-head">
            <h3>Ausencias registradas</h3>
        </div>

        <?php if (empty($absences)): ?>
            <p class="section-muted">No hay ausencias registradas.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Talento</th>
                            <th>Tipo</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $absence): ?>
                            <?php
                            $statusKey = strtolower(trim((string) ($absence['status'] ?? 'pendiente')));
                            $statusMeta = $statusLabels[$statusKey] ?? ['label' => ucfirst($statusKey), 'class' => 'status-muted'];
                            $type = strtolower(trim((string) ($absence['absence_type'] ?? 'ausencia')));
                            $typeLabel = $typeLabels[$type] ?? ucfirst(str_replace('_', ' ', $type));
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($absence['talent_name'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($typeLabel) ?></td>
                                <td><?= htmlspecialchars((string) ($absence['start_date'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($absence['end_date'] ?? '')) ?></td>
                                <td><span class="status-badge <?= htmlspecialchars($statusMeta['class']) ?>"><?= htmlspecialchars($statusMeta['label']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <?php if ($canEditAbsence): ?>
                                            <a class="action-btn small" href="<?= $basePath ?>/talent-absences?edit=<?= (int) ($absence['id'] ?? 0) ?>#absence-form">Editar</a>
                                        <?php endif; ?>
                                        <?php if ($canDeleteAbsence): ?>
                                            <form method="POST" action="<?= $basePath ?>/talent-absences/delete" class="inline-form" onsubmit="return confirm('¿Eliminar esta ausencia?');">
                                                <input type="hidden" name="id" value="<?= (int) ($absence['id'] ?? 0) ?>">
                                                <button type="submit" class="action-btn small danger">Eliminar</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($canApproveAbsence && $statusKey === 'pendiente'): ?>
                                            <form method="POST" action="<?= $basePath ?>/talent-absences/approve" class="inline-form">
                                                <input type="hidden" name="id" value="<?= (int) ($absence['id'] ?? 0) ?>">
                                                <input type="hidden" name="status" value="aprobado">
                                                <button type="submit" class="action-btn small primary">Aprobar</button>
                                            </form>
                                            <form method="POST" action="<?= $basePath ?>/talent-absences/approve" class="inline-form">
                                                <input type="hidden" name="id" value="<?= (int) ($absence['id'] ?? 0) ?>">
                                                <input type="hidden" name="status" value="rechazado">
                                                <button type="submit" class="action-btn small warning">Rechazar</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>

<style>
    .absence-shell { display:flex; flex-direction:column; gap:16px; }
    .absence-header, .absence-form-section, .absence-table-section { border:1px solid var(--border); border-radius:16px; background:var(--surface); padding:16px; }
    .absence-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .section-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
    .absence-form { display:flex; flex-direction:column; gap:12px; }
    label { display:flex; flex-direction:column; gap:6px; color:var(--text-primary); font-weight:600; }
    input, select, textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text-primary); }
    .hint-block { border:1px dashed var(--border); border-radius:10px; padding:10px; color:var(--text-secondary); font-size:13px; }
    .table-wrapper { overflow:auto; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:10px 12px; border-bottom:1px solid var(--border); text-align:left; }
    th { font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color:var(--text-secondary); }
    .table-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .inline-form { display:inline-flex; margin:0; }
    .action-btn { background:var(--surface); border:1px solid var(--border); color:var(--text-primary); border-radius:10px; padding:8px 12px; text-decoration:none; cursor:pointer; font-weight:600; }
    .action-btn.small { padding:6px 10px; font-size:12px; }
    .action-btn.primary { background:var(--primary); border-color:var(--primary); color:var(--text-primary); }
    .action-btn.warning { background:color-mix(in srgb, var(--warning) 20%, var(--surface) 80%); border-color:color-mix(in srgb, var(--warning) 40%, var(--border) 60%); }
    .action-btn.danger { background:color-mix(in srgb, var(--danger) 18%, var(--surface) 82%); border-color:color-mix(in srgb, var(--danger) 40%, var(--border) 60%); }
    .status-badge { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
    .status-warning { background:color-mix(in srgb, var(--warning) 20%, var(--surface) 80%); }
    .status-success { background:color-mix(in srgb, var(--success) 20%, var(--surface) 80%); }
    .status-danger { background:color-mix(in srgb, var(--danger) 20%, var(--surface) 80%); }
    .status-muted { background:color-mix(in srgb, var(--neutral) 20%, var(--surface) 80%); }
    .toggle-switch--row { flex-direction:row; align-items:center; justify-content:space-between; border:1px solid var(--border); border-radius:10px; padding:8px 10px; }
    .toggle-slider { width:44px; height:24px; border-radius:999px; background:color-mix(in srgb, var(--neutral) 40%, var(--surface) 60%); position:relative; border:1px solid var(--border); }
    .toggle-slider::after { content:''; position:absolute; left:2px; top:2px; width:18px; height:18px; border-radius:50%; background:#fff; transition:transform .2s ease; }
    .toggle-switch--row input { position:absolute; opacity:0; width:1px; height:1px; }
    .toggle-switch--row input:checked + .toggle-slider { background:var(--primary); border-color:var(--primary); }
    .toggle-switch--row input:checked + .toggle-slider::after { transform:translateX(20px); }
</style>

<script>
(() => {
    const toggle = document.querySelector('[data-full-day-toggle]');
    const hoursField = document.querySelector('[data-hours-field]');
    if (!toggle || !hoursField) return;

    const syncHoursField = () => {
        hoursField.hidden = toggle.checked;
    };

    toggle.addEventListener('change', syncHoursField);
    syncHoursField();
})();
</script>
