<?php
$basePath = $basePath ?? '';
$absences = is_array($absences ?? null) ? $absences : [];
$talents = is_array($talents ?? null) ? $talents : [];
$absenceTypes = is_array($absenceTypes ?? null) ? $absenceTypes : [];
$typeColors = is_array($typeColors ?? null) ? $typeColors : [];
$isAdmin = !empty($isAdmin);
$statusFilter = (string) ($statusFilter ?? '');
$fromFilter = (string) ($fromFilter ?? '');
$toFilter = (string) ($toFilter ?? '');
$successMsg = trim((string) ($_GET['success'] ?? ''));
$errorMsg = trim((string) ($_GET['error'] ?? ''));

$statusLabels = [
    'pendiente' => ['label' => 'Pendiente', 'class' => 'warning'],
    'aprobado'  => ['label' => 'Aprobado',  'class' => 'success'],
    'rechazado' => ['label' => 'Rechazado', 'class' => 'danger'],
];
?>

<style>
.absence-filters{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.absence-filters label{display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:600}
.absence-table-wrapper{overflow-x:auto}
.absence-type-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid transparent}
.absence-color-dot{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0}
.absence-actions{display:flex;gap:6px;flex-wrap:wrap}
.absence-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;align-items:end}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group label{font-weight:600;font-size:13px;color:var(--text-primary)}
.capacity-tooltip-info{font-size:12px;color:var(--text-secondary);background:color-mix(in srgb,var(--surface) 90%,var(--background));border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:8px}
.legend-row{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}
.legend-item{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600}
</style>

<?php if ($successMsg !== ''): ?>
    <div class="alert" style="border-color:var(--success);background:color-mix(in srgb,var(--success) 12%,var(--background));color:var(--success);">
        <?= htmlspecialchars($successMsg) ?>
    </div>
<?php endif; ?>
<?php if ($errorMsg !== ''): ?>
    <div class="alert error"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<div class="capacity-tooltip-info">
    <strong>¿Cómo afectan las ausencias a la capacidad?</strong><br>
    La capacidad semanal real se calcula restando los días de ausencia aprobados al total de horas laborales.
    Ejemplo: 40h base – 8h vacaciones – 4h permiso médico = <strong>28h capacidad real</strong>.
    <div class="legend-row">
        <?php foreach ($absenceTypes as $type => $label): ?>
            <?php $color = $typeColors[$type] ?? '#6b7280'; ?>
            <span class="legend-item">
                <span class="absence-color-dot" style="background:<?= htmlspecialchars($color) ?>"></span>
                <?= htmlspecialchars($label) ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<div class="section-grid twothirds" style="gap:20px">
    <section class="card">
        <h3 style="margin:0 0 14px;font-size:16px;font-weight:700">Registrar ausencia</h3>
        <form method="POST" action="<?= $basePath ?>/absences" class="absence-form-grid">
            <?php if ($isAdmin): ?>
                <div class="form-group">
                    <label for="absence_talent">Talento</label>
                    <select id="absence_talent" name="talent_id" required>
                        <option value="">Seleccionar talento...</option>
                        <?php foreach ($talents as $talent): ?>
                            <option value="<?= (int) $talent['id'] ?>"><?= htmlspecialchars((string) $talent['name']) ?><?= !empty($talent['role']) ? ' — ' . htmlspecialchars((string) $talent['role']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="absence_type">Tipo de ausencia</label>
                <select id="absence_type" name="type" required>
                    <option value="">Seleccionar tipo...</option>
                    <?php foreach ($absenceTypes as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="absence_from">Desde</label>
                <input type="date" id="absence_from" name="date_from" required value="<?= htmlspecialchars(date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label for="absence_to">Hasta</label>
                <input type="date" id="absence_to" name="date_to" required value="<?= htmlspecialchars(date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label for="absence_hours">Horas por día (vacío = día completo)</label>
                <input type="number" id="absence_hours" name="hours_per_day" min="0.5" max="24" step="0.5" placeholder="Ej: 4">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label for="absence_reason">Motivo / Observación</label>
                <textarea id="absence_reason" name="reason" rows="2" placeholder="Descripción opcional..."></textarea>
            </div>
            <?php if ($isAdmin): ?>
                <div class="form-group" style="grid-column:1/-1">
                    <label class="toggle compact-toggle">
                        <input type="checkbox" name="auto_approve" value="1">
                        <span class="toggle-ui"></span>
                        <span style="font-size:13px;color:var(--text-primary);font-weight:600">Aprobar automáticamente al registrar</span>
                    </label>
                </div>
            <?php endif; ?>
            <div style="grid-column:1/-1">
                <button type="submit" class="btn primary">Registrar ausencia</button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="toolbar">
            <h3 style="margin:0;font-size:16px;font-weight:700">Ausencias registradas</h3>
        </div>
        <form method="GET" class="absence-filters" style="margin-bottom:14px">
            <?php if ($isAdmin): ?>
                <label>Estado
                    <select name="status">
                        <option value="">Todos</option>
                        <option value="pendiente" <?= $statusFilter === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="aprobado" <?= $statusFilter === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
                        <option value="rechazado" <?= $statusFilter === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                    </select>
                </label>
            <?php endif; ?>
            <label>Desde
                <input type="date" name="from" value="<?= htmlspecialchars($fromFilter) ?>">
            </label>
            <label>Hasta
                <input type="date" name="to" value="<?= htmlspecialchars($toFilter) ?>">
            </label>
            <button type="submit" class="btn sm">Filtrar</button>
            <a href="<?= $basePath ?>/absences" class="btn sm">Limpiar</a>
        </form>

        <?php if (empty($absences)): ?>
            <div class="alert">No hay ausencias registradas con los filtros actuales.</div>
        <?php else: ?>
            <div class="absence-table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <?php if ($isAdmin): ?>
                                <th>Talento</th>
                            <?php endif; ?>
                            <th>Tipo</th>
                            <th>Desde</th>
                            <th>Hasta</th>
                            <th>Horas/día</th>
                            <th>Estado</th>
                            <th>Creado por</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $abs): ?>
                            <?php
                            $absType = (string) ($abs['type'] ?? '');
                            $absColor = $typeColors[$absType] ?? '#6b7280';
                            $absLabel = $absenceTypes[$absType] ?? $absType;
                            $absStatus = (string) ($abs['status'] ?? 'pendiente');
                            $statusInfo = $statusLabels[$absStatus] ?? ['label' => $absStatus, 'class' => 'neutral'];
                            $hoursPerDay = $abs['hours_per_day'] ?? null;
                            ?>
                            <tr>
                                <?php if ($isAdmin): ?>
                                    <td><strong><?= htmlspecialchars((string) ($abs['talent_name'] ?? '-')) ?></strong></td>
                                <?php endif; ?>
                                <td>
                                    <span class="absence-type-pill" style="background:<?= htmlspecialchars($absColor) ?>18;color:<?= htmlspecialchars($absColor) ?>;border-color:<?= htmlspecialchars($absColor) ?>40">
                                        <span class="absence-color-dot" style="background:<?= htmlspecialchars($absColor) ?>"></span>
                                        <?= htmlspecialchars($absLabel) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string) ($abs['date_from'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($abs['date_to'] ?? '')) ?></td>
                                <td><?= $hoursPerDay !== null ? round((float) $hoursPerDay, 2) . 'h' : '<span class="muted">Día completo</span>' ?></td>
                                <td><span class="badge <?= htmlspecialchars($statusInfo['class']) ?>"><?= htmlspecialchars($statusInfo['label']) ?></span></td>
                                <td><?= htmlspecialchars((string) ($abs['created_by_name'] ?? '-')) ?></td>
                                <td class="absence-actions">
                                    <?php if ($isAdmin && $absStatus !== 'aprobado'): ?>
                                        <form method="POST" action="<?= $basePath ?>/absences/<?= (int) $abs['id'] ?>/approve" style="display:inline">
                                            <button type="submit" class="btn sm success" title="Aprobar">✓ Aprobar</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($isAdmin && $absStatus !== 'rechazado'): ?>
                                        <button type="button" class="btn sm danger" onclick="showRejectModal(<?= (int) $abs['id'] ?>)" title="Rechazar">✗ Rechazar</button>
                                    <?php endif; ?>
                                    <?php if ($isAdmin || $absStatus !== 'aprobado'): ?>
                                        <form method="POST" action="<?= $basePath ?>/absences/<?= (int) $abs['id'] ?>/delete" style="display:inline" onsubmit="return confirm('¿Eliminar esta ausencia?')">
                                            <button type="submit" class="btn sm danger" title="Eliminar">🗑</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($abs['reason'])): ?>
                                <tr style="background:color-mix(in srgb,var(--surface) 96%,var(--background))">
                                    <td colspan="<?= $isAdmin ? 8 : 7 ?>" style="font-size:12px;color:var(--text-secondary);padding:4px 12px">
                                        <em>Motivo: <?= htmlspecialchars((string) $abs['reason']) ?></em>
                                        <?php if (!empty($abs['rejection_reason'])): ?>
                                            — <em style="color:var(--danger)">Rechazo: <?= htmlspecialchars((string) $abs['rejection_reason']) ?></em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<div id="reject-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center">
    <div class="card" style="max-width:400px;width:90%;padding:24px;display:flex;flex-direction:column;gap:16px">
        <h3 style="margin:0;font-size:16px;font-weight:700">Rechazar ausencia</h3>
        <form id="reject-form" method="POST">
            <div class="form-group" style="margin-bottom:14px">
                <label for="reject_reason">Motivo del rechazo</label>
                <textarea id="reject_reason" name="reason" rows="3" placeholder="Explica el motivo..."></textarea>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn danger">Rechazar</button>
                <button type="button" class="btn" onclick="closeRejectModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(id) {
    const modal = document.getElementById('reject-modal');
    const form = document.getElementById('reject-form');
    form.action = '<?= $basePath ?>/absences/' + id + '/reject';
    modal.style.display = 'flex';
}
function closeRejectModal() {
    document.getElementById('reject-modal').style.display = 'none';
}
document.getElementById('reject-modal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});
</script>
