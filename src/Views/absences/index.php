<?php
$statusLabels = [
    'pendiente'  => ['label' => 'Pendiente',  'class' => 'warning'],
    'aprobado'   => ['label' => 'Aprobado',   'class' => 'success'],
    'rechazado'  => ['label' => 'Rechazado',  'class' => 'danger'],
];

$flashMessages = [
    'created'  => 'Ausencia registrada correctamente.',
    'updated'  => 'Ausencia actualizada.',
    'approved' => 'Ausencia aprobada.',
    'rejected' => 'Ausencia rechazada.',
    'deleted'  => 'Ausencia eliminada.',
];

$flash = $flashMessages[$flashMessage ?? ''] ?? null;

function fmtDate(string $date): string {
    if (!$date) return '—';
    try {
        $d = new DateTime($date);
        return $d->format('d/m/Y');
    } catch (\Throwable) {
        return $date;
    }
}
?>
<style>
    .absences-header { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:4px; }
    .absences-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; }
    .absence-kpi { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px 18px; display:flex; flex-direction:column; gap:4px; }
    .absence-kpi .kpi-val { font-size:28px; font-weight:800; color:var(--text-primary); }
    .absence-kpi .kpi-lbl { font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-secondary); font-weight:700; }
    .filter-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
    .filter-bar .filter-field { display:flex; flex-direction:column; gap:4px; }
    .filter-bar label { font-size:12px; color:var(--text-secondary); font-weight:700; }
    .filter-bar select, .filter-bar input[type=date] { padding:8px 10px; font-size:13px; min-width:140px; }
    .filter-bar .btn.sm { align-self:flex-end; }
    .absence-table-wrapper { overflow-x:auto; }
    .absence-actions { display:flex; gap:6px; flex-wrap:wrap; }
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:200; align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:28px; width:100%; max-width:520px; box-shadow:0 24px 60px color-mix(in srgb,#020617 35%,transparent); display:flex; flex-direction:column; gap:18px; }
    .modal-box h3 { margin:0; font-size:18px; font-weight:800; color:var(--text-primary); }
    .modal-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .modal-form-grid .full { grid-column:1/-1; }
    .modal-footer { display:flex; justify-content:flex-end; gap:10px; }
    .badge-pendiente  { background:color-mix(in srgb,var(--warning) 16%,var(--background)); color:var(--warning); border-color:color-mix(in srgb,var(--warning) 35%,var(--border)); }
    .badge-aprobado   { background:color-mix(in srgb,var(--success) 16%,var(--background)); color:var(--success); border-color:color-mix(in srgb,var(--success) 35%,var(--border)); }
    .badge-rechazado  { background:color-mix(in srgb,var(--danger) 16%,var(--background)); color:var(--danger); border-color:color-mix(in srgb,var(--danger) 35%,var(--border)); }
</style>

<?php if ($flash): ?>
    <div class="alert success" style="border-color:color-mix(in srgb,var(--success) 40%,var(--border));background:color-mix(in srgb,var(--success) 12%,var(--surface));color:var(--success);">
        <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>

<div class="absences-header">
    <div>
        <p class="muted" style="margin:0;">Registro, aprobación y seguimiento de ausencias y vacaciones del equipo.</p>
    </div>
    <?php if ($canCreate): ?>
        <button class="btn primary" type="button" onclick="openAbsenceModal()">+ Nueva ausencia</button>
    <?php endif; ?>
</div>

<div class="absences-kpis">
    <div class="absence-kpi">
        <span class="kpi-val"><?= (int) (($counts['pendiente'] ?? 0) + ($counts['aprobado'] ?? 0) + ($counts['rechazado'] ?? 0)) ?></span>
        <span class="kpi-lbl">Total</span>
    </div>
    <div class="absence-kpi">
        <span class="kpi-val" style="color:var(--warning);"><?= (int) ($counts['pendiente'] ?? 0) ?></span>
        <span class="kpi-lbl">Pendientes</span>
    </div>
    <div class="absence-kpi">
        <span class="kpi-val" style="color:var(--success);"><?= (int) ($counts['aprobado'] ?? 0) ?></span>
        <span class="kpi-lbl">Aprobadas</span>
    </div>
    <div class="absence-kpi">
        <span class="kpi-val" style="color:var(--danger);"><?= (int) ($counts['rechazado'] ?? 0) ?></span>
        <span class="kpi-lbl">Rechazadas</span>
    </div>
</div>

<div class="card">
    <form method="GET" action="/absences" class="filter-bar" style="margin-bottom:14px;">
        <div class="filter-field">
            <label>Talento</label>
            <select name="talent_id">
                <option value="">Todos</option>
                <?php foreach ($talents as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= ((int) ($filters['talent_id'] ?? 0)) === (int) $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label>Estado</label>
            <select name="status">
                <option value="">Todos</option>
                <option value="pendiente"  <?= ($filters['status'] ?? '') === 'pendiente'  ? 'selected' : '' ?>>Pendiente</option>
                <option value="aprobado"   <?= ($filters['status'] ?? '') === 'aprobado'   ? 'selected' : '' ?>>Aprobado</option>
                <option value="rechazado"  <?= ($filters['status'] ?? '') === 'rechazado'  ? 'selected' : '' ?>>Rechazado</option>
            </select>
        </div>
        <div class="filter-field">
            <label>Tipo</label>
            <select name="absence_type">
                <option value="">Todos</option>
                <?php foreach ($absenceTypes as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= ($filters['absence_type'] ?? '') === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label>Desde</label>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>">
        </div>
        <div class="filter-field">
            <label>Hasta</label>
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>">
        </div>
        <button class="btn sm" type="submit">Filtrar</button>
        <a href="/absences" class="btn sm ghost">Limpiar</a>
    </form>

    <div class="absence-table-wrapper">
        <?php if (empty($absences)): ?>
            <div style="padding:32px;text-align:center;color:var(--text-secondary);">
                No se encontraron ausencias con los filtros aplicados.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Talento</th>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Horas</th>
                        <th>Estado</th>
                        <th>Aprobado por</th>
                        <th>Motivo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($absences as $absence): ?>
                        <?php
                            $st = $absence['status'] ?? 'pendiente';
                            $stInfo = $statusLabels[$st] ?? ['label' => ucfirst($st), 'class' => 'neutral'];
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($absence['talent_name'] ?? '—') ?></strong>
                                <div class="muted" style="font-size:12px;"><?= htmlspecialchars($absence['talent_role'] ?? '') ?></div>
                            </td>
                            <td><?= htmlspecialchars($absenceTypes[$absence['absence_type'] ?? ''] ?? ucfirst((string) ($absence['absence_type'] ?? '—'))) ?></td>
                            <td><?= fmtDate((string) ($absence['start_date'] ?? '')) ?></td>
                            <td><?= fmtDate((string) ($absence['end_date'] ?? '')) ?></td>
                            <td>
                                <?php if (!empty($absence['is_full_day'])): ?>
                                    <span class="badge neutral">Día completo</span>
                                <?php elseif ($absence['hours'] !== null): ?>
                                    <?= number_format((float) $absence['hours'], 1) ?>h
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($stInfo['label']) ?></span></td>
                            <td><?= htmlspecialchars($absence['approved_by_name'] ?? '—') ?></td>
                            <td style="max-width:200px;white-space:normal;"><?= htmlspecialchars($absence['reason'] ?? '—') ?></td>
                            <td>
                                <div class="absence-actions">
                                    <?php if ($canApprove && $st === 'pendiente'): ?>
                                        <form method="POST" action="/absences/<?= (int) $absence['id'] ?>/approve" style="display:inline;">
                                            <button class="btn sm" style="color:var(--success);border-color:color-mix(in srgb,var(--success) 35%,var(--border));background:color-mix(in srgb,var(--success) 10%,var(--background));" type="submit" title="Aprobar">✓ Aprobar</button>
                                        </form>
                                        <form method="POST" action="/absences/<?= (int) $absence['id'] ?>/reject" style="display:inline;">
                                            <button class="btn sm danger" type="submit" title="Rechazar">✗ Rechazar</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canEdit): ?>
                                        <a href="/absences/<?= (int) $absence['id'] ?>/edit" class="btn sm">Editar</a>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <form method="POST" action="/absences/<?= (int) $absence['id'] ?>/delete" style="display:inline;"
                                              onsubmit="return confirm('¿Eliminar esta ausencia?');">
                                            <button class="btn sm danger" type="submit">Eliminar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($canCreate): ?>
<!-- Modal: Nueva ausencia -->
<div class="modal-overlay" id="absence-modal" role="dialog" aria-modal="true" aria-label="Nueva ausencia">
    <div class="modal-box">
        <h3>Nueva ausencia</h3>
        <form method="POST" action="/absences/create" id="absence-form">
            <div class="modal-form-grid">
                <div class="full input">
                    <span>Talento *</span>
                    <select name="talent_id" required>
                        <option value="">Seleccionar talento...</option>
                        <?php foreach ($talents as $t): ?>
                            <option value="<?= (int) $t['id'] ?>">
                                <?= htmlspecialchars($t['name']) ?> — <?= htmlspecialchars($t['role'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input">
                    <span>Tipo de ausencia *</span>
                    <select name="absence_type" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($absenceTypes as $key => $label): ?>
                            <?php if ($key === 'vacaciones' && empty($absenceConfig['vacations_enabled'])): continue; endif; ?>
                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input">
                    <span>Estado</span>
                    <?php if ($canApprove): ?>
                        <select name="status_hint" style="display:none;"></select>
                        <span class="badge badge-aprobado" style="width:fit-content;">Se aprobará automáticamente</span>
                    <?php else: ?>
                        <span class="badge badge-pendiente" style="width:fit-content;">Quedará como Pendiente</span>
                    <?php endif; ?>
                </div>
                <div class="input">
                    <span>Fecha inicio *</span>
                    <input type="date" name="start_date" required>
                </div>
                <div class="input">
                    <span>Fecha fin *</span>
                    <input type="date" name="end_date" required>
                </div>
                <div class="input">
                    <label style="display:flex;align-items:center;gap:8px;margin:0;">
                        <input type="checkbox" name="is_full_day" value="1" checked style="width:auto;">
                        <span>Día(s) completo(s)</span>
                    </label>
                </div>
                <div class="input" id="hours-row" style="display:none;">
                    <span>Horas</span>
                    <input type="number" name="hours" min="0.5" max="24" step="0.5" placeholder="Ej. 4">
                </div>
                <div class="full input">
                    <span>Motivo / observaciones</span>
                    <textarea name="reason" rows="2" placeholder="Descripción opcional..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="margin-top:8px;">
                <button type="button" class="btn ghost" onclick="closeAbsenceModal()">Cancelar</button>
                <button type="submit" class="btn primary">Guardar ausencia</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAbsenceModal() {
    document.getElementById('absence-modal').classList.add('open');
}
function closeAbsenceModal() {
    document.getElementById('absence-modal').classList.remove('open');
}
document.getElementById('absence-modal').addEventListener('click', function(e) {
    if (e.target === this) closeAbsenceModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAbsenceModal();
});
(function() {
    const fullDayChk = document.querySelector('input[name="is_full_day"]');
    const hoursRow = document.getElementById('hours-row');
    if (!fullDayChk || !hoursRow) return;
    fullDayChk.addEventListener('change', function() {
        hoursRow.style.display = this.checked ? 'none' : '';
    });
})();
</script>
<?php endif; ?>
