<?php
$basePath = $basePath ?? '';
$absences = is_array($absences ?? null) ? $absences : [];
$talents = is_array($talents ?? null) ? $talents : [];
$absenceTypes = is_array($absenceTypes ?? null) ? $absenceTypes : [];
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : [];
$filters = is_array($filters ?? null) ? $filters : [];
$stats = is_array($stats ?? null) ? $stats : [];
$canManage = (bool) ($canManage ?? false);
$canApprove = (bool) ($canApprove ?? false);
$canDelete = (bool) ($canDelete ?? false);
$absenceConfig = is_array($absenceConfig ?? null) ? $absenceConfig : [];
$flashMessage = (string) ($flashMessage ?? '');

$statusBadge = static function (?string $status): string {
    return match ($status) {
        'aprobado', 'approved' => 'success',
        'rechazado', 'rejected' => 'danger',
        'pendiente' => 'warning',
        default => 'neutral',
    };
};

$formatDate = static function (?string $value): string {
    if (!$value) return '—';
    $timestamp = strtotime($value);
    if (!$timestamp) return '—';
    return date('d/m/Y', $timestamp);
};

$flashMessageText = match ($flashMessage) {
    'created' => 'Ausencia registrada correctamente.',
    'updated' => 'Ausencia actualizada correctamente.',
    'approved' => 'Ausencia aprobada correctamente.',
    'rejected' => 'Ausencia rechazada.',
    'deleted' => 'Ausencia eliminada correctamente.',
    'imported' => 'Importación completada. Registros importados: ' . ($_GET['count'] ?? 0),
    default => '',
};

$totalAbsences = count($absences);
$pendingCount = $stats['pendiente'] ?? 0;
$approvedCount = $stats['aprobado'] ?? 0;
?>

<style>
    .absences-shell { display: flex; flex-direction: column; gap: 20px; }
    .absences-header { display: flex; flex-direction: column; gap: 6px; }
    .absences-header .eyebrow { margin: 0; font-size: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; }
    .absences-header h2 { margin: 0; }
    .absences-header small { color: var(--text-secondary); }
    .absences-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; }
    .absences-kpi { padding: 16px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface); display: flex; flex-direction: column; gap: 4px; }
    .absences-kpi .kpi-value { font-size: 28px; font-weight: 800; color: var(--text-primary); }
    .absences-kpi .kpi-label { font-size: 13px; color: var(--text-secondary); font-weight: 600; }
    .absences-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .absences-toolbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .absences-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; padding: 14px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface); }
    .absences-filters label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; font-weight: 600; color: var(--text-secondary); }
    .absences-filters select, .absences-filters input[type="date"] { padding: 8px 10px; border-radius: 10px; border: 1px solid var(--border); background: var(--background); color: var(--text-primary); font-size: 13px; }
    .absences-table-wrap { overflow-x: auto; }
    .absence-form-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 999; background: rgba(0,0,0,0.4); align-items: center; justify-content: center; }
    .absence-form-modal.open { display: flex; }
    .absence-form-panel { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; width: 560px; max-width: 95vw; max-height: 90vh; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
    .absence-form-panel h3 { margin: 0; }
    .absence-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .absence-form-grid label { display: flex; flex-direction: column; gap: 6px; font-weight: 600; color: var(--text-secondary); font-size: 13px; }
    .absence-form-grid .full-width { grid-column: 1 / -1; }
    .absence-form-actions { display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 14px; }
    .absence-row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .absence-row-actions form { display: inline; }
    .absence-row-actions .btn { padding: 6px 10px; font-size: 12px; border-radius: 8px; }
    .import-section { padding: 16px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface); display: flex; flex-direction: column; gap: 12px; }
    .import-section h4 { margin: 0; }
    .import-section textarea { min-height: 120px; font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace; font-size: 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--background); padding: 10px; color: var(--text-primary); resize: vertical; }
    .flash-banner { padding: 12px 16px; border-radius: 12px; background: color-mix(in srgb, var(--success) 14%, var(--surface)); border: 1px solid color-mix(in srgb, var(--success) 30%, var(--border)); color: var(--success); font-weight: 600; }
    @media (max-width: 720px) {
        .absence-form-grid { grid-template-columns: 1fr; }
        .absences-kpis { grid-template-columns: 1fr 1fr; }
    }
</style>

<section class="absences-shell">
    <header class="absences-header">
        <p class="eyebrow">Talento / Ausencias</p>
        <h2>Gestión de ausencias</h2>
        <small>Registra, aprueba y controla las ausencias del equipo. Las ausencias aprobadas se integran automáticamente con capacidad y timesheets.</small>
    </header>

    <?php if ($flashMessageText !== ''): ?>
        <div class="flash-banner"><?= htmlspecialchars($flashMessageText) ?></div>
    <?php endif; ?>

    <div class="absences-kpis">
        <div class="absences-kpi">
            <span class="kpi-value"><?= $totalAbsences ?></span>
            <span class="kpi-label">Total ausencias</span>
        </div>
        <div class="absences-kpi">
            <span class="kpi-value" style="color: var(--warning);"><?= $pendingCount ?></span>
            <span class="kpi-label">Pendientes</span>
        </div>
        <div class="absences-kpi">
            <span class="kpi-value" style="color: var(--success);"><?= $approvedCount ?></span>
            <span class="kpi-label">Aprobadas</span>
        </div>
        <div class="absences-kpi">
            <span class="kpi-value" style="color: var(--danger);"><?= $stats['rechazado'] ?? 0 ?></span>
            <span class="kpi-label">Rechazadas</span>
        </div>
    </div>

    <form method="GET" action="<?= $basePath ?>/absences" class="absences-filters">
        <label>Talento
            <select name="talent_id">
                <option value="">Todos</option>
                <?php foreach ($talents as $talent): ?>
                    <option value="<?= (int) $talent['id'] ?>" <?= ($filters['talent_id'] ?? '') == $talent['id'] ? 'selected' : '' ?>><?= htmlspecialchars($talent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo
            <select name="absence_type">
                <option value="">Todos</option>
                <?php foreach ($absenceTypes as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= ($filters['absence_type'] ?? '') === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Estado
            <select name="status">
                <option value="">Todos</option>
                <?php foreach ($statusLabels as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= ($filters['status'] ?? '') === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Desde
            <input type="date" name="from_date" value="<?= htmlspecialchars($filters['from_date'] ?? '') ?>">
        </label>
        <label>Hasta
            <input type="date" name="to_date" value="<?= htmlspecialchars($filters['to_date'] ?? '') ?>">
        </label>
        <button type="submit" class="btn primary" style="align-self: flex-end;">Filtrar</button>
        <a href="<?= $basePath ?>/absences" class="btn" style="align-self: flex-end;">Limpiar</a>
    </form>

    <div class="absences-toolbar">
        <div>
            <strong><?= $totalAbsences ?></strong> registro<?= $totalAbsences !== 1 ? 's' : '' ?>
        </div>
        <?php if ($canManage): ?>
            <div class="absences-toolbar-actions">
                <button type="button" class="btn primary" onclick="document.getElementById('modal-create').classList.add('open')">+ Nueva ausencia</button>
                <button type="button" class="btn" onclick="document.getElementById('import-section').toggleAttribute('hidden')">Importar</button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
    <div id="import-section" class="import-section" hidden>
        <h4>Importar ausencias</h4>
        <p class="text-muted" style="margin:0; font-size:13px;">Pega datos CSV con columnas: <code>talent_id, absence_type, start_date, end_date, hours, is_full_day, status, reason</code>. La primera línea puede ser encabezados.</p>
        <form method="POST" action="<?= $basePath ?>/absences/import">
            <textarea name="csv_data" placeholder="talent_id,absence_type,start_date,end_date,hours,is_full_day,status,reason&#10;1,vacaciones,2026-03-10,2026-03-14,,1,aprobado,Vacaciones programadas"></textarea>
            <div style="display:flex; gap:10px; margin-top:10px;">
                <button type="submit" class="btn primary">Importar</button>
                <button type="button" class="btn" onclick="document.getElementById('import-section').setAttribute('hidden','')">Cancelar</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="absences-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Talento</th>
                    <th>Tipo</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Jornada</th>
                    <th>Estado</th>
                    <th>Motivo</th>
                    <th>Registrado por</th>
                    <th>Aprobado por</th>
                    <?php if ($canManage || $canApprove || $canDelete): ?>
                        <th>Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($absences)): ?>
                    <tr><td colspan="<?= ($canManage || $canApprove || $canDelete) ? 10 : 9 ?>" style="text-align:center; color:var(--text-secondary); padding:24px;">No se encontraron ausencias registradas.</td></tr>
                <?php endif; ?>
                <?php foreach ($absences as $absence): ?>
                    <?php
                    $absenceStatus = (string) ($absence['status'] ?? 'pendiente');
                    $isPending = in_array($absenceStatus, ['pendiente'], true);
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars((string) ($absence['talent_name'] ?? '—')) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars((string) ($absence['talent_role'] ?? '')) ?></small>
                        </td>
                        <td><?= htmlspecialchars($absenceTypes[$absence['absence_type']] ?? ucfirst((string) ($absence['absence_type'] ?? ''))) ?></td>
                        <td><?= $formatDate($absence['start_date'] ?? null) ?></td>
                        <td><?= $formatDate($absence['end_date'] ?? null) ?></td>
                        <td>
                            <?php if ((int) ($absence['is_full_day'] ?? 1)): ?>
                                <span class="badge neutral">Día completo</span>
                            <?php else: ?>
                                <span class="badge info"><?= number_format((float) ($absence['hours'] ?? 0), 1) ?>h</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $statusBadge($absenceStatus) ?>"><?= htmlspecialchars($statusLabels[$absenceStatus] ?? ucfirst($absenceStatus)) ?></span></td>
                        <td><small><?= htmlspecialchars(mb_strimwidth((string) ($absence['reason'] ?? '—'), 0, 60, '...')) ?></small></td>
                        <td><small><?= htmlspecialchars((string) ($absence['created_by_name'] ?? '—')) ?></small></td>
                        <td><small><?= htmlspecialchars((string) ($absence['approved_by_name'] ?? '—')) ?></small></td>
                        <?php if ($canManage || $canApprove || $canDelete): ?>
                            <td>
                                <div class="absence-row-actions">
                                    <?php if ($canManage && $isPending): ?>
                                        <button type="button" class="btn" onclick="openEditModal(<?= htmlspecialchars(json_encode($absence, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">Editar</button>
                                    <?php endif; ?>
                                    <?php if ($canApprove && $isPending): ?>
                                        <form method="POST" action="<?= $basePath ?>/absences/<?= (int) $absence['id'] ?>/approve">
                                            <button type="submit" class="btn primary">Aprobar</button>
                                        </form>
                                        <form method="POST" action="<?= $basePath ?>/absences/<?= (int) $absence['id'] ?>/reject">
                                            <button type="submit" class="btn danger">Rechazar</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <form method="POST" action="<?= $basePath ?>/absences/<?= (int) $absence['id'] ?>/delete" onsubmit="return confirm('¿Eliminar esta ausencia?');">
                                            <button type="submit" class="btn danger">Eliminar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canManage): ?>
<div id="modal-create" class="absence-form-modal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="absence-form-panel">
        <h3>Nueva ausencia</h3>
        <form method="POST" action="<?= $basePath ?>/absences/create">
            <div class="absence-form-grid">
                <label>Talento
                    <select name="talent_id" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($talents as $talent): ?>
                            <option value="<?= (int) $talent['id'] ?>"><?= htmlspecialchars($talent['name']) ?> — <?= htmlspecialchars($talent['role']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Tipo de ausencia
                    <select name="absence_type" required>
                        <?php foreach ($absenceTypes as $code => $label): ?>
                            <?php if ($code === 'vacaciones' && empty($absenceConfig['enable_vacations'])) continue; ?>
                            <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Fecha inicio
                    <input type="date" name="start_date" required>
                </label>
                <label>Fecha fin
                    <input type="date" name="end_date" required>
                </label>
                <label>
                    <span style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="is_full_day" checked onchange="document.getElementById('hours-field-create').style.display=this.checked?'none':'block'">
                        Día completo
                    </span>
                </label>
                <label id="hours-field-create" style="display:none;">Horas (parcial)
                    <input type="number" name="hours" step="0.5" min="0.5" max="24" placeholder="4">
                </label>
                <?php if ($canApprove): ?>
                <label>Estado
                    <select name="status">
                        <option value="pendiente">Pendiente</option>
                        <option value="aprobado">Aprobado</option>
                    </select>
                </label>
                <?php endif; ?>
                <label class="full-width">Motivo / observaciones
                    <textarea name="reason" rows="3" placeholder="Vacaciones programadas, cita médica, etc."></textarea>
                </label>
            </div>
            <div class="absence-form-actions">
                <button type="button" class="btn" onclick="document.getElementById('modal-create').classList.remove('open')">Cancelar</button>
                <button type="submit" class="btn primary">Registrar ausencia</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit" class="absence-form-modal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="absence-form-panel">
        <h3>Editar ausencia</h3>
        <form id="form-edit" method="POST" action="">
            <div class="absence-form-grid">
                <label>Tipo de ausencia
                    <select name="absence_type" id="edit-absence-type" required>
                        <?php foreach ($absenceTypes as $code => $label): ?>
                            <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>&nbsp;
                    <span style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="is_full_day" id="edit-is-full-day" checked onchange="document.getElementById('hours-field-edit').style.display=this.checked?'none':'block'">
                        Día completo
                    </span>
                </label>
                <label>Fecha inicio
                    <input type="date" name="start_date" id="edit-start-date" required>
                </label>
                <label>Fecha fin
                    <input type="date" name="end_date" id="edit-end-date" required>
                </label>
                <label id="hours-field-edit" style="display:none;">Horas (parcial)
                    <input type="number" name="hours" id="edit-hours" step="0.5" min="0.5" max="24">
                </label>
                <label class="full-width">Motivo / observaciones
                    <textarea name="reason" id="edit-reason" rows="3"></textarea>
                </label>
            </div>
            <div class="absence-form-actions">
                <button type="button" class="btn" onclick="document.getElementById('modal-edit').classList.remove('open')">Cancelar</button>
                <button type="submit" class="btn primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(absence) {
    const form = document.getElementById('form-edit');
    form.action = '<?= $basePath ?>/absences/' + absence.id + '/update';
    document.getElementById('edit-absence-type').value = absence.absence_type || '';
    document.getElementById('edit-start-date').value = absence.start_date || '';
    document.getElementById('edit-end-date').value = absence.end_date || '';
    document.getElementById('edit-reason').value = absence.reason || '';

    const isFullDay = parseInt(absence.is_full_day || '1') === 1;
    document.getElementById('edit-is-full-day').checked = isFullDay;
    document.getElementById('hours-field-edit').style.display = isFullDay ? 'none' : 'block';
    if (!isFullDay) {
        document.getElementById('edit-hours').value = absence.hours || '';
    }

    document.getElementById('modal-edit').classList.add('open');
}
</script>
<?php endif; ?>
