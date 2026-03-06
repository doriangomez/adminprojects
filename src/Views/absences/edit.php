<?php
function fmtDateValue(string $date): string {
    if (!$date) return '';
    try {
        return (new DateTime($date))->format('Y-m-d');
    } catch (\Throwable) {
        return $date;
    }
}
?>
<style>
    .edit-absence-card { max-width:640px; margin:0 auto; }
    .edit-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .edit-form-grid .full { grid-column:1/-1; }
    .edit-footer { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:8px; }
</style>

<div class="edit-absence-card">
    <div class="card">
        <div class="card-content">
            <header style="margin-bottom:6px;">
                <h3 style="margin:0 0 4px;">Editar ausencia</h3>
                <p class="muted">Talento: <strong><?= htmlspecialchars($absence['talent_name'] ?? '—') ?></strong></p>
            </header>

            <form method="POST" action="/absences/<?= (int) $absence['id'] ?>/edit">
                <div class="edit-form-grid">
                    <div class="full input">
                        <span>Talento *</span>
                        <select name="talent_id" required>
                            <?php foreach ($talents as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $absence['talent_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['name']) ?> — <?= htmlspecialchars($t['role'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input">
                        <span>Tipo de ausencia *</span>
                        <select name="absence_type" required>
                            <?php foreach ($absenceTypes as $key => $label): ?>
                                <?php if ($key === 'vacaciones' && empty($absenceConfig['vacations_enabled'])): continue; endif; ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= ($absence['absence_type'] ?? '') === $key ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input">
                        <span>Estado actual</span>
                        <?php
                        $statusBadges = ['pendiente' => 'warning', 'aprobado' => 'success', 'rechazado' => 'danger'];
                        $st = $absence['status'] ?? 'pendiente';
                        $cls = $statusBadges[$st] ?? 'neutral';
                        ?>
                        <span class="badge <?= $cls ?>" style="width:fit-content;"><?= htmlspecialchars(ucfirst($st)) ?></span>
                        <small class="hint">El estado se cambia desde la tabla de ausencias (aprobar/rechazar).</small>
                    </div>
                    <div class="input">
                        <span>Fecha inicio *</span>
                        <input type="date" name="start_date" value="<?= htmlspecialchars(fmtDateValue((string) ($absence['start_date'] ?? ''))) ?>" required>
                    </div>
                    <div class="input">
                        <span>Fecha fin *</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars(fmtDateValue((string) ($absence['end_date'] ?? ''))) ?>" required>
                    </div>
                    <div class="input">
                        <label style="display:flex;align-items:center;gap:8px;margin:0;">
                            <input type="checkbox" name="is_full_day" value="1" <?= !empty($absence['is_full_day']) ? 'checked' : '' ?> style="width:auto;" id="edit-full-day">
                            <span>Día(s) completo(s)</span>
                        </label>
                    </div>
                    <div class="input" id="edit-hours-row" style="<?= !empty($absence['is_full_day']) ? 'display:none;' : '' ?>">
                        <span>Horas</span>
                        <input type="number" name="hours" min="0.5" max="24" step="0.5"
                               value="<?= $absence['hours'] !== null ? htmlspecialchars((string) $absence['hours']) : '' ?>">
                    </div>
                    <div class="full input">
                        <span>Motivo / observaciones</span>
                        <textarea name="reason" rows="3"><?= htmlspecialchars($absence['reason'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="edit-footer">
                    <a href="/absences" class="btn ghost">Cancelar</a>
                    <button type="submit" class="btn primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const fullDayChk = document.getElementById('edit-full-day');
    const hoursRow = document.getElementById('edit-hours-row');
    if (!fullDayChk || !hoursRow) return;
    fullDayChk.addEventListener('change', function() {
        hoursRow.style.display = this.checked ? 'none' : '';
    });
})();
</script>
