<?php
$basePath = $basePath ?? '';
$absence = is_array($absence ?? null) ? $absence : [];
$talents = is_array($talents ?? null) ? $talents : [];
$errorMessage = (string) ($errorMessage ?? '');
$isEdit = !empty($absence['id']);

$typeOptions = AbsenceService::ABSENCE_TYPES;
$statusOptions = AbsenceService::STATUSES;
?>
<section class="section-grid">
    <header class="toolbar">
        <div>
            <p class="badge neutral">Talento</p>
            <h2><?= $isEdit ? 'Editar ausencia' : 'Nueva ausencia' ?></h2>
            <small class="text-muted"><?= $isEdit ? 'Modifica los datos de la ausencia.' : 'Registra una nueva ausencia para un talento.' ?></small>
        </div>
        <a href="<?= $basePath ?>/absences" class="btn secondary">← Volver</a>
    </header>

    <?php if ($errorMessage): ?>
        <div class="alert danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-content">
            <form method="POST" action="<?= $basePath ?>/absences/<?= $isEdit ? (int) ($absence['id'] ?? 0) . '/update' : 'create' ?>">
                <div class="form-grid">
                    <label>Talento <span class="required">*</span>
                        <select name="talent_id" required>
                            <option value="">Selecciona un talento</option>
                            <?php foreach ($talents as $t): ?>
                                <?php $sel = ((int) ($absence['talent_id'] ?? 0)) === ((int) ($t['id'] ?? 0)); ?>
                                <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= $sel ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Tipo de ausencia
                        <select name="absence_type">
                            <?php foreach ($typeOptions as $code => $label): ?>
                                <?php $sel = ($absence['absence_type'] ?? 'ausencia') === $code; ?>
                                <option value="<?= htmlspecialchars($code) ?>" <?= $sel ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="form-grid">
                    <label>Fecha inicio <span class="required">*</span>
                        <input type="date" name="start_date" required value="<?= htmlspecialchars((string) ($absence['start_date'] ?? '')) ?>">
                    </label>
                    <label>Fecha fin <span class="required">*</span>
                        <input type="date" name="end_date" required value="<?= htmlspecialchars((string) ($absence['end_date'] ?? '')) ?>">
                    </label>
                </div>
                <div class="form-grid">
                    <label class="toggle-switch toggle-switch--state">
                        <span class="toggle-label">Día completo</span>
                        <input type="checkbox" name="is_full_day" value="1" <?= !isset($absence['is_full_day']) || !empty($absence['is_full_day']) ? 'checked' : '' ?>>
                        <span class="toggle-track" aria-hidden="true"></span>
                        <span class="toggle-state"></span>
                    </label>
                    <label>Horas (si no es día completo)
                        <input type="number" name="hours" step="0.5" min="0" value="<?= htmlspecialchars((string) ($absence['hours'] ?? '')) ?>" placeholder="Opcional">
                    </label>
                </div>
                <label>Motivo (opcional)
                    <textarea name="reason" rows="3" placeholder="Motivo de la ausencia"><?= htmlspecialchars((string) ($absence['reason'] ?? '')) ?></textarea>
                </label>
                <?php if ($isEdit && isset($absence['status'])): ?>
                    <label>Estado
                        <select name="status">
                            <?php foreach ($statusOptions as $code => $label): ?>
                                <?php $sel = ($absence['status'] ?? '') === $code; ?>
                                <option value="<?= htmlspecialchars($code) ?>" <?= $sel ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <div class="form-actions">
                    <button type="submit" class="btn primary"><?= $isEdit ? 'Actualizar' : 'Guardar' ?></button>
                    <a href="<?= $basePath ?>/absences" class="btn secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</section>

<style>
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px; }
.form-actions { display: flex; gap: 12px; margin-top: 20px; }
.required { color: var(--danger); }
</style>
