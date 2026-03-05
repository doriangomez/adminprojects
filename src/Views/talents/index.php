<?php
$basePath = $basePath ?? '';
$talents = is_array($talents ?? null) ? $talents : [];
$editingTalent = is_array($editingTalent ?? null) ? $editingTalent : null;
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$services = is_array($services ?? null) ? $services : [];
$documentsByService = is_array($documentsByService ?? null) ? $documentsByService : [];
$canDeleteOutsourcingRecords = (bool) ($canDeleteOutsourcingRecords ?? false);
$flashMessage = (string) ($flashMessage ?? '');
$timesheetApproverOptions = is_array($timesheetApproverOptions ?? null) ? $timesheetApproverOptions : [];
$isEditing = !empty($editingTalent);

$serviceStatusLabels = [
    'active' => 'Activo',
    'paused' => 'Pausado',
    'ended' => 'Finalizado',
];
$healthLabels = [
    'green' => 'Verde',
    'yellow' => 'Amarillo',
    'red' => 'Rojo',
];
$healthBadge = static function (?string $status): string {
    return match ($status) {
        'green' => 'status-success',
        'yellow' => 'status-warning',
        'red' => 'status-danger',
        default => 'status-muted',
    };
};
$formatDate = static function (?string $value): string {
    if (!$value) {
        return 'Sin registro';
    }
    $timestamp = strtotime($value);
    if (!$timestamp) {
        return 'Sin registro';
    }
    return date('d/m/Y', $timestamp);
};
$flashMessageText = match ($flashMessage) {
    'created' => 'Talento registrado y listo para asignaciones de outsourcing.',
    'created_outsourcing' => 'Talento registrado. Asígnalo a un servicio desde el módulo Outsourcing.',
    'created_existing_user' => 'Talento registrado usando un usuario existente.',
    'created_without_access' => 'Talento registrado sin acceso al sistema (sin usuario/login).',
    'updated' => 'Talento actualizado. Gestiona sus asignaciones desde Outsourcing.',
    'deleted' => 'Talento eliminado en cascada correctamente.',
    'outsourcing_service_deleted' => 'Registro de servicio/seguimiento eliminado correctamente.',
    'inactivated' => 'Talento inactivado correctamente.',
    default => '',
};
?>

<section class="talent-shell">
    <header class="talent-header">
        <div>
            <p class="eyebrow">Gestión de talentos</p>
            <h2>Centro de recursos de outsourcing</h2>
            <small class="section-muted">Registra talentos y revisa el seguimiento por cliente/proyecto.</small>
        </div>
        <span class="badge neutral">PMO / Gestión de talento</span>
    </header>

    <section class="talent-form-section">
        <div class="section-head">
            <div class="section-title">
                <span class="section-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                <div>
                    <h3><?= $isEditing ? 'Editar talento' : 'Registrar talento' ?></h3>
                    <small class="section-muted">Completa la ficha del talento y define su flujo de reporte de horas.</small>
                </div>
            </div>
            <?php if ($isEditing): ?>
                <a class="action-btn" href="<?= $basePath ?>/talents">Cancelar edición</a>
            <?php endif; ?>
        </div>
        <?php if ($flashMessageText): ?>
            <div class="alert success"><?= htmlspecialchars($flashMessageText) ?></div>
        <?php endif; ?>


        <?php if ($isEditing): ?>
            <?php
            $dangerOp1 = random_int(1, 10);
            $dangerOp2 = random_int(1, 10);
            $dangerOperator = random_int(0, 1) === 1 ? '+' : '-';
            $inactiveOp1 = random_int(1, 10);
            $inactiveOp2 = random_int(1, 10);
            $inactiveOperator = random_int(0, 1) === 1 ? '+' : '-';
            ?>
            <div class="talent-danger-zone" id="danger-zone">
                <h4>Zona de riesgo (edición de talento)</h4>
                <p class="section-muted">Desde aquí puedes inactivar o eliminar en cascada el talento que estás editando.</p>
                <div class="talent-danger-zone__actions">
                    <form method="POST" action="<?= $basePath ?>/talents/inactivate" class="talent-delete-form" onsubmit="return confirm('¿Seguro que deseas inactivar este talento?');">
                        <input type="hidden" name="talent_id" value="<?= (int) ($editingTalent['id'] ?? 0) ?>">
                        <input type="hidden" name="math_operand1" value="<?= $inactiveOp1 ?>">
                        <input type="hidden" name="math_operand2" value="<?= $inactiveOp2 ?>">
                        <input type="hidden" name="math_operator" value="<?= $inactiveOperator ?>">
                        <label>Confirmación matemática para inactivar: <?= $inactiveOp1 . ' ' . $inactiveOperator . ' ' . $inactiveOp2 ?> = ?
                            <input type="number" name="math_result" required>
                        </label>
                        <button type="submit" class="action-btn small warning solid"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg> Inactivar talento</button>
                    </form>

                    <form method="POST" action="<?= $basePath ?>/talents/delete" class="talent-delete-form" onsubmit="return confirm('¿Seguro que deseas eliminar este talento? Esta acción elimina asignaciones, timesheets y skills relacionados.');">
                        <input type="hidden" name="talent_id" value="<?= (int) ($editingTalent['id'] ?? 0) ?>">
                        <input type="hidden" name="math_operand1" value="<?= $dangerOp1 ?>">
                        <input type="hidden" name="math_operand2" value="<?= $dangerOp2 ?>">
                        <input type="hidden" name="math_operator" value="<?= $dangerOperator ?>">
                        <label>Confirmación matemática para eliminar: <?= $dangerOp1 . ' ' . $dangerOperator . ' ' . $dangerOp2 ?> = ?
                            <input type="number" name="math_result" required>
                        </label>
                        <button type="submit" class="action-btn small danger solid"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Eliminar en cascada</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $basePath ?>/talents/<?= $isEditing ? 'update' : 'create' ?>" class="talent-form">
            <?php if ($isEditing): ?>
                <input type="hidden" name="talent_id" value="<?= (int) ($editingTalent['id'] ?? 0) ?>">
            <?php endif; ?>
            <div class="grid">
                <label>Nombre completo
                    <input name="name" required value="<?= htmlspecialchars((string) ($editingTalent['name'] ?? '')) ?>">
                </label>
                <label>Correo corporativo
                    <input type="email" name="email" value="<?= htmlspecialchars((string) ($editingTalent['user_email'] ?? '')) ?>" placeholder="talento@empresa.com">
                </label>
            </div>
            <div class="grid">
                <label>Rol principal
                    <input name="role" required value="<?= htmlspecialchars((string) ($editingTalent['role'] ?? '')) ?>" placeholder="Ej. Analista de servicio">
                </label>
                <label>Seniority
                    <input name="seniority" value="<?= htmlspecialchars((string) ($editingTalent['seniority'] ?? '')) ?>" placeholder="Ej. Senior">
                </label>
            </div>
            <div class="grid">
                <label>Capacidad horaria (h/semana)
                    <input type="number" step="0.5" name="capacidad_horaria" value="<?= htmlspecialchars((string) ($editingTalent['capacidad_horaria'] ?? 40)) ?>">
                </label>
                <label>Disponibilidad (%)
                    <input type="number" name="availability" value="<?= htmlspecialchars((string) ($editingTalent['availability'] ?? 100)) ?>">
                </label>
            </div>
            <div class="grid">
                <label>Costo hora
                    <input type="number" step="0.01" name="hourly_cost" value="<?= htmlspecialchars((string) ($editingTalent['hourly_cost'] ?? 0)) ?>">
                </label>
                <label>Tarifa hora
                    <input type="number" step="0.01" name="hourly_rate" value="<?= htmlspecialchars((string) ($editingTalent['hourly_rate'] ?? 0)) ?>">
                </label>
            </div>
            <div class="grid">
                <label>Tipo de talento
                    <select name="tipo_talento">
                        <?php $selectedTipo = $editingTalent['tipo_talento'] ?? 'interno'; ?>
                        <option value="interno" <?= $selectedTipo === 'interno' ? 'selected' : '' ?>>Interno</option>
                        <option value="externo" <?= $selectedTipo === 'externo' ? 'selected' : '' ?>>Externo</option>
                        <option value="otro" <?= $selectedTipo === 'otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </label>
                <label>Reporte de horas
                    <?php $requiresReport = $editingTalent['requiere_reporte_horas'] ?? 1; ?>
                    <select name="requiere_reporte_horas">
                        <option value="1" <?= !empty($requiresReport) ? 'selected' : '' ?>>Requiere reporte</option>
                        <option value="0" <?= empty($requiresReport) ? 'selected' : '' ?>>No reporta</option>
                    </select>
                </label>
            </div>
            <?php
            $requiresApproval = !empty($editingTalent['requiere_aprobacion_horas']);
            $selectedApprover = (int) ($editingTalent['timesheet_approver_user_id'] ?? 0);
            ?>
            <label class="toggle-switch toggle-switch--state toggle-switch--row" aria-label="Requiere aprobación de horas">
                <span class="toggle-label">Requiere aprobación de horas</span>
                <input type="checkbox" id="requires-approval" name="requiere_aprobacion_horas" value="1" <?= $requiresApproval ? 'checked' : '' ?>>
                <span class="toggle-slider" aria-hidden="true"></span>
            </label>
            <label id="timesheet-approver-field" <?= !$requiresApproval ? 'hidden' : '' ?>>Jefe aprobador
                <select name="timesheet_approver_user_id" id="timesheet-approver-select" <?= $requiresApproval ? 'required' : '' ?> <?= !$requiresApproval ? 'disabled' : '' ?>>
                    <option value="">Selecciona un jefe aprobador</option>
                    <?php foreach ($timesheetApproverOptions as $approver): ?>
                        <option value="<?= (int) ($approver['id'] ?? 0) ?>" <?= $selectedApprover === (int) ($approver['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($approver['name'] ?? '')) ?> · <?= htmlspecialchars((string) ($approver['email'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="section-muted">Solo se muestran usuarios activos con permiso para aprobar timesheets.</small>
            </label>

            <div class="divider"></div>
            <div class="alert">
                La asignación a servicios se gestiona desde el módulo Outsourcing.
            </div>
            <button type="submit" class="action-btn primary"><?= $isEditing ? 'Actualizar talento' : 'Guardar talento' ?></button>
        </form>

    </section>

    <section class="talent-grid" id="talent-list">
        <div class="section-head">
            <div class="section-title">
                <span class="section-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <div>
                    <h3>Talentos registrados</h3>
                    <small class="section-muted">Gestiona perfiles y disponibilidad.</small>
                    <small class="section-muted danger-text">Para eliminar o inactivar: clic en <strong>Editar</strong> y usa la <strong>Zona de riesgo</strong>.</small>
                </div>
            </div>
        </div>
        <?php if (empty($talents)): ?>
            <p class="section-muted">Aún no se han registrado talentos.</p>
        <?php else: ?>
            <div class="talent-cards">
                <?php foreach ($talents as $talent): ?>
                    <?php
                    $tipoTalento = $talent['tipo_talento'] ?? 'interno';
                    $tipoClass = $tipoTalento === 'externo' ? 'status-info' : ($tipoTalento === 'otro' ? 'status-warning' : 'status-muted');
                    $requiresReport = !empty($talent['requiere_reporte_horas']);
                    $requiresApproval = !empty($talent['requiere_aprobacion_horas']);
                    ?>
                    <article class="talent-card">
                        <header class="talent-card__header">
                            <div>
                                <span class="talent-card__name"><?= htmlspecialchars($talent['name'] ?? '') ?></span>
                                <span class="talent-card__role">
                                    <span class="icon" aria-hidden="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></span>
                                    <?= htmlspecialchars($talent['role'] ?? '') ?>
                                </span>
                            </div>
                            <span class="status-badge <?= $tipoClass ?>">
                                <span class="icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                                <?= htmlspecialchars(ucfirst((string) $tipoTalento)) ?>
                            </span>
                        </header>

                        <div class="talent-card__body">
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.8"><path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M3 21h18"/><path d="M10 9h4M10 13h4"/></svg></span>
                                <div>
                                    <span class="talent-card__label">Tipo</span>
                                    <strong><?= htmlspecialchars($talent['tipo_talento'] ?? 'interno') ?></strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="1.8"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></span>
                                <div>
                                    <span class="talent-card__label">Seniority</span>
                                    <strong><?= htmlspecialchars($talent['seniority'] ?? 'N/A') ?></strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="1.8"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></span>
                                <div>
                                    <span class="talent-card__label">Disponibilidad</span>
                                    <strong><?= htmlspecialchars((string) ($talent['availability'] ?? 0)) ?>%</strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                                <div>
                                    <span class="talent-card__label">Capacidad</span>
                                    <strong><?= htmlspecialchars((string) ($talent['capacidad_horaria'] ?? 0)) ?>h/sem</strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                                <div>
                                    <span class="talent-card__label">Costo / Tarifa</span>
                                    <strong>$<?= number_format((float) ($talent['hourly_cost'] ?? 0), 0, ',', '.') ?> · $<?= number_format((float) ($talent['hourly_rate'] ?? 0), 0, ',', '.') ?></strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                                <div>
                                    <span class="talent-card__label">Contacto</span>
                                    <strong><?= htmlspecialchars($talent['user_email'] ?? 'Sin usuario') ?></strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--secondary)" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                                <div>
                                    <span class="talent-card__label">Jefe aprobador</span>
                                    <strong><?= htmlspecialchars((string) ($talent['timesheet_approver_name'] ?? 'Sin asignar')) ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="talent-card__indicators">
                            <span class="pill <?= $requiresReport ? 'pill-success' : 'pill-muted' ?>">
                                <span class="icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>
                                <?= $requiresReport ? 'Reporta horas' : 'Sin reporte' ?>
                            </span>
                            <span class="pill <?= $requiresApproval ? 'pill-warning' : 'pill-muted' ?>">
                                <span class="icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                                <?= $requiresApproval ? 'Requiere aprobación' : 'Sin aprobación' ?>
                            </span>
                            <span class="pill pill-muted">
                                <span class="icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg></span>
                                <?= htmlspecialchars($talent['skills'] ?? 'Skills n/a') ?>
                            </span>
                        </div>

                        <div class="talent-pmo-health">
                            <span class="pmo-chip"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> Proyectos activos: <strong><?= (int) ($talent['active_projects'] ?? 0) ?></strong></span>
                            <span class="pmo-chip"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg> Asignaciones: <strong><?= (int) ($talent['total_assignments'] ?? 0) ?></strong></span>
                            <span class="pmo-chip"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Timesheets pendientes: <strong><?= (int) ($talent['pending_timesheets'] ?? 0) ?></strong></span>
                            <span class="pmo-chip"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg> Horas reportadas: <strong><?= number_format((float) ($talent['reported_hours'] ?? 0), 1, ',', '.') ?>h</strong></span>
                            <span class="pmo-chip"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5"/><path d="M21 3l-7 7"/><path d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5"/></svg> Servicios outsourcing: <strong><?= (int) ($talent['outsourcing_services'] ?? 0) ?></strong></span>
                        </div>

                        <?php
                        $deleteOp1 = random_int(1, 10);
                        $deleteOp2 = random_int(1, 10);
                        $deleteOperator = random_int(0, 1) === 1 ? '+' : '-';
                        $inactivateOp1 = random_int(1, 10);
                        $inactivateOp2 = random_int(1, 10);
                        $inactivateOperator = random_int(0, 1) === 1 ? '+' : '-';
                        ?>
                        <footer class="talent-card__footer">
                            <a class="action-btn small" href="<?= $basePath ?>/talents?edit=<?= (int) ($talent['id'] ?? 0) ?>">Editar</a>
                            <a class="action-btn ghost small" href="#talent-tracking">Ver seguimiento</a>

                            <form method="POST" action="<?= $basePath ?>/talents/inactivate" class="talent-inline-action-form" onsubmit="return talentInlineMathConfirm(this, 'inactivar', '¿Seguro que deseas inactivar este talento?');">
                                <input type="hidden" name="talent_id" value="<?= (int) ($talent['id'] ?? 0) ?>">
                                <input type="hidden" name="math_operand1" value="<?= $inactivateOp1 ?>">
                                <input type="hidden" name="math_operand2" value="<?= $inactivateOp2 ?>">
                                <input type="hidden" name="math_operator" value="<?= $inactivateOperator ?>">
                                <input type="hidden" name="math_result" value="">
                                <button type="submit" class="action-btn small warning solid"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg> Inactivar</button>
                            </form>

                            <form method="POST" action="<?= $basePath ?>/talents/delete" class="talent-inline-action-form" onsubmit="return talentInlineMathConfirm(this, 'eliminar', '¿Seguro que deseas eliminar este talento? Esta acción elimina asignaciones, timesheets y skills relacionados.');">
                                <input type="hidden" name="talent_id" value="<?= (int) ($talent['id'] ?? 0) ?>">
                                <input type="hidden" name="math_operand1" value="<?= $deleteOp1 ?>">
                                <input type="hidden" name="math_operand2" value="<?= $deleteOp2 ?>">
                                <input type="hidden" name="math_operator" value="<?= $deleteOperator ?>">
                                <input type="hidden" name="math_result" value="">
                                <button type="submit" class="action-btn small danger solid"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Eliminar</button>
                            </form>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="talent-tracking" id="talent-tracking">
        <div class="section-head">
            <div class="section-title">
                <span class="section-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.8"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></span>
                <div>
                    <h3>Seguimiento por talento y servicio</h3>
                    <small class="section-muted">Estado actual, último seguimiento y evidencias asociadas.</small>
                </div>
            </div>
        </div>
        <?php if (empty($services)): ?>
            <p class="section-muted">No hay servicios de outsourcing registrados.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Talento</th>
                            <th>Cliente / Proyecto</th>
                            <th>Periodo</th>
                            <th>Estado actual</th>
                            <th>Semáforo</th>
                            <th>Último seguimiento</th>
                            <th>Documentos asociados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <?php
                            $serviceId = (int) ($service['id'] ?? 0);
                            $documents = $documentsByService[$serviceId]['files'] ?? [];
                            $documentsTitle = $documentsByService[$serviceId]['node_title'] ?? '';
                            $documentsPreview = array_slice($documents, 0, 3);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($service['talent_name'] ?? 'Talento') ?></strong>
                                    <small class="section-muted"><?= htmlspecialchars($service['talent_email'] ?? '') ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($service['client_name'] ?? '') ?><br>
                                    <small class="section-muted"><?= htmlspecialchars($service['project_name'] ?? 'Sin proyecto') ?></small>
                                </td>
                                <td><?= htmlspecialchars((string) ($service['start_date'] ?? '')) ?> → <?= htmlspecialchars((string) ($service['end_date'] ?? 'Actual')) ?></td>
                                <td><?= htmlspecialchars($serviceStatusLabels[$service['service_status'] ?? 'active'] ?? 'Activo') ?></td>
                                <td>
                                    <span class="status-badge <?= $healthBadge($service['current_health'] ?? null) ?>">
                                        <?= htmlspecialchars($healthLabels[$service['current_health'] ?? ''] ?? 'Sin seguimiento') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($formatDate($service['last_followup_end'] ?? $service['health_updated_at'] ?? null)) ?></td>
                                <td>
                                    <?php if (empty($documents)): ?>
                                        <span class="section-muted">Sin evidencias</span>
                                    <?php else: ?>
                                        <?php if ($documentsTitle): ?>
                                            <strong><?= htmlspecialchars($documentsTitle) ?></strong>
                                        <?php endif; ?>
                                        <ul class="doc-list">
                                            <?php foreach ($documentsPreview as $doc): ?>
                                                <li><?= htmlspecialchars($doc['file_name'] ?? '') ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if (count($documents) > 3): ?>
                                            <small class="section-muted">+<?= count($documents) - 3 ?> documentos adicionales</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div class="service-actions">
                                        <a class="link" href="<?= $basePath ?>/outsourcing/<?= $serviceId ?>">Ver servicio</a>
                                        <a class="link" href="<?= $basePath ?>/outsourcing/<?= $serviceId ?>#nuevo-seguimiento">Agregar seguimiento</a>
                                        <?php if ($canDeleteOutsourcingRecords): ?>
                                            <form method="POST" action="<?= $basePath ?>/outsourcing/<?= $serviceId ?>/delete" onsubmit="return confirm('¿Eliminar este servicio y sus seguimientos asociados?');" class="inline-action-form">
                                                <button type="submit" class="link danger">Eliminar registro</button>
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


<script>
function talentInlineMathConfirm(form, actionLabel, confirmMessage) {
    if (!window.confirm(confirmMessage)) {
        return false;
    }

    const operand1 = parseInt(form.querySelector('input[name="math_operand1"]').value || '0', 10);
    const operand2 = parseInt(form.querySelector('input[name="math_operand2"]').value || '0', 10);
    const operator = String(form.querySelector('input[name="math_operator"]').value || '+');
    const expected = operator === '+' ? operand1 + operand2 : operand1 - operand2;
    const answer = window.prompt(`Confirmación matemática para ${actionLabel}: ${operand1} ${operator} ${operand2} = ?`);

    if (answer === null || answer.trim() === '') {
        return false;
    }

    const parsed = parseInt(answer, 10);
    if (Number.isNaN(parsed) || parsed !== expected) {
        window.alert('La confirmación matemática es incorrecta.');
        return false;
    }

    form.querySelector('input[name="math_result"]').value = String(parsed);
    return true;
}
</script>

<style>
    .talent-shell { display:flex; flex-direction:column; gap:18px; }
    .talent-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border:1px solid var(--border); border-radius:16px; padding:20px; background: var(--surface); box-shadow: 0 1px 3px color-mix(in srgb, var(--text-primary) 4%, transparent), 0 6px 16px color-mix(in srgb, var(--text-primary) 3%, transparent); }
    .talent-header h2 { margin: 0; font-weight: 800; letter-spacing: -0.02em; }
    .talent-form-section, .talent-grid, .talent-tracking { border:1px solid var(--border); border-radius:16px; padding:20px; background:var(--surface); display:flex; flex-direction:column; gap:14px; box-shadow: 0 1px 3px color-mix(in srgb, var(--text-primary) 4%, transparent), 0 6px 16px color-mix(in srgb, var(--text-primary) 3%, transparent); }
    .section-head { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .section-title { display:flex; align-items:flex-start; gap:12px; }
    .section-icon { width:38px; height:38px; border-radius:12px; background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 16%, var(--surface)), color-mix(in srgb, var(--primary) 8%, var(--surface))); display:inline-flex; align-items:center; justify-content:center; font-size:18px; border:1px solid color-mix(in srgb, var(--primary) 18%, var(--border)); flex-shrink: 0; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); }
    select, input { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); font-family:inherit; }
    .divider { border-top:1px dashed var(--border); margin:8px 0; }
    .talent-cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px; }
    .talent-card { border:1px solid var(--border); border-radius:16px; padding:18px; background:var(--surface); display:flex; flex-direction:column; gap:14px; box-shadow: 0 1px 3px color-mix(in srgb, var(--text-primary) 4%, transparent); transition: box-shadow 0.25s ease, border-color 0.25s ease, transform 0.25s ease; }
    .talent-card:hover { box-shadow: 0 4px 16px color-mix(in srgb, var(--text-primary) 8%, transparent); border-color: color-mix(in srgb, var(--primary) 20%, var(--border)); transform: translateY(-2px); }
    .talent-card__header { display:flex; justify-content:space-between; gap:12px; }
    .talent-card__name { display:block; font-size:16px; font-weight:700; color:var(--text-primary); }
    .talent-card__role { display:inline-flex; align-items:center; gap:6px; font-size:13px; color:var(--text-secondary); }
    .talent-card__body { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .talent-card__item { display:flex; gap:10px; align-items:center; background: color-mix(in srgb, var(--surface) 95%, var(--background) 5%); border-radius:12px; padding:10px 12px; border:1px solid color-mix(in srgb, var(--border) 55%, var(--surface) 45%); transition: border-color 0.2s ease; }
    .talent-card__item:hover { border-color: color-mix(in srgb, var(--primary) 20%, var(--border)); }
    .talent-card__label { display:block; font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.04em; }
    .talent-card__indicators { display:flex; flex-wrap:wrap; gap:8px; }
    .talent-pmo-health { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:8px; }
    .pmo-chip { display:inline-flex; align-items:center; justify-content:space-between; gap:8px; border:1px solid color-mix(in srgb, var(--info) 28%, var(--border) 72%); background:color-mix(in srgb, var(--info) 8%, var(--surface) 92%); border-radius:10px; padding:7px 9px; font-size:12px; color:var(--text-primary); }
    .talent-card__footer { display:flex; flex-wrap:wrap; gap:8px; align-items:center; border-top:1px dashed var(--border); padding-top:10px; }
    .danger-text { color: var(--danger); font-weight:600; display:block; margin-top:4px; }
    .icon { font-size:15px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid transparent; }
    .pill-muted { background:color-mix(in srgb, var(--neutral) 12%, var(--surface) 88%); color:var(--text-secondary); border-color:color-mix(in srgb, var(--neutral) 30%, var(--surface) 70%); }
    .pill-success { background:color-mix(in srgb, var(--success) 15%, var(--surface) 85%); color:var(--success); border-color:color-mix(in srgb, var(--success) 35%, var(--surface) 65%); }
    .pill-warning { background:color-mix(in srgb, var(--warning) 15%, var(--surface) 85%); color:var(--warning); border-color:color-mix(in srgb, var(--warning) 35%, var(--surface) 65%); }
    .toggle-switch--row { justify-content:space-between; width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:12px; background:color-mix(in srgb, var(--surface) 92%, var(--background) 8%); }
    .toggle-switch { display:inline-flex; align-items:center; gap:10px; position:relative; }
    .toggle-switch .toggle-label { font-weight:600; color:var(--text-primary); }
    .toggle-switch input { position:absolute; opacity:0; width:1px; height:1px; }
    .toggle-switch .toggle-slider { width:46px; height:26px; border-radius:999px; background:color-mix(in srgb, var(--neutral) 30%, var(--surface) 70%); border:1px solid var(--border); position:relative; transition:all .2s ease; }
    .toggle-switch .toggle-slider::after { content:''; position:absolute; width:20px; height:20px; top:2px; left:2px; border-radius:50%; background:#fff; box-shadow:0 2px 6px rgba(15,23,42,.25); transition:transform .2s ease; }
    .toggle-switch input:checked + .toggle-slider { background:#0b2a6f; border-color:#0b2a6f; }
    .toggle-switch input:checked + .toggle-slider::after { transform:translateX(20px); }
    .toggle-switch input:focus-visible + .toggle-slider { outline:2px solid color-mix(in srgb, var(--primary) 45%, transparent); outline-offset:2px; }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:10px; padding:8px 12px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color:var(--text-primary); border-color: var(--primary); }
    .action-btn.small { padding:6px 10px; font-size:12px; width:max-content; }
    .action-btn.ghost { background:transparent; }

    .talent-inline-action-form { display:inline-flex; margin:0; }
    .talent-inline-action-form .action-btn.small { width:auto; }
    .talent-inline-action-form .action-btn.danger.solid,
    .talent-inline-action-form .action-btn.warning.solid { width:auto; }

    .action-btn.danger { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 50%, var(--surface) 50%); }
    .action-btn.danger.solid { background:var(--danger); color:#fff; border-color:var(--danger); width:100%; justify-content:center; }
    .action-btn.danger.solid:hover { filter:brightness(0.95); }
    .action-btn.warning.solid { background:var(--warning); color:#fff; border-color:var(--warning); width:100%; justify-content:center; }

    .talent-danger-zone { margin-top:14px; border:1px solid color-mix(in srgb, var(--danger) 35%, var(--border) 65%); border-radius:14px; padding:14px; background:color-mix(in srgb, var(--danger) 6%, var(--surface) 94%); }
    .talent-danger-zone h4 { margin:0 0 6px 0; color:var(--danger); }
    .talent-danger-zone__actions { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px; }
    .talent-delete-form { display:grid; gap:8px; border-top:1px dashed var(--border); padding-top:10px; }
    .talent-delete-form label { font-size:12px; color:var(--text-secondary); font-weight:600; }
    .talent-delete-form input { width:100%; }
    .badge.neutral { background:color-mix(in srgb, var(--neutral) 12%, var(--surface) 88%); color:var(--text-secondary); border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid var(--background); display:inline-flex; align-items:center; gap:6px; }
    .status-muted { background:color-mix(in srgb, var(--neutral) 12%, var(--surface) 88%); color:var(--text-secondary); border-color:color-mix(in srgb, var(--neutral) 40%, var(--surface) 60%); }
    .status-success { background:color-mix(in srgb, var(--success) 15%, var(--surface) 85%); color:var(--success); border-color:color-mix(in srgb, var(--success) 40%, var(--surface) 60%); }
    .status-warning { background:color-mix(in srgb, var(--warning) 15%, var(--surface) 85%); color:var(--warning); border-color:color-mix(in srgb, var(--warning) 40%, var(--surface) 60%); }
    .status-danger { background:color-mix(in srgb, var(--danger) 15%, var(--surface) 85%); color:var(--danger); border-color:color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); }
    .status-info { background:color-mix(in srgb, var(--info) 15%, var(--surface) 85%); color:var(--info); border-color:color-mix(in srgb, var(--info) 40%, var(--surface) 60%); }
    .table-wrapper { overflow:auto; }
    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--border); font-size:14px; vertical-align:top; }
    th { font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color: var(--text-secondary); }
    .doc-list { margin:6px 0; padding-left:18px; color: var(--text-primary); }
    .link { color: var(--primary); font-weight:600; text-decoration:none; }
    .link:hover { text-decoration:underline; }
    .service-actions { display:flex; flex-direction:column; gap:4px; margin-top:4px; }
    .inline-action-form { display:inline; margin:0; }
    .link.danger { color: var(--danger); font-weight: 600; background: transparent; border: 0; padding: 0; cursor: pointer; text-align:left; }
    .alert.success { padding:10px 12px; border-radius:12px; background:color-mix(in srgb, var(--success) 15%, var(--surface) 85%); color:var(--success); border:1px solid color-mix(in srgb, var(--success) 40%, var(--surface) 60%); font-weight:600; }
</style>


<script>
(() => {
    const approvalToggle = document.getElementById('requires-approval');
    const approverField = document.getElementById('timesheet-approver-field');
    const approverSelect = document.getElementById('timesheet-approver-select');
    if (!approvalToggle || !approverField || !approverSelect) return;

    const syncApproverField = () => {
        const requiresApproval = approvalToggle.checked;
        approverField.hidden = !requiresApproval;
        approverField.setAttribute('aria-hidden', requiresApproval ? 'false' : 'true');
        approverSelect.required = requiresApproval;
        approverSelect.disabled = !requiresApproval;
        if (!requiresApproval) {
            approverSelect.value = '';
        }
    };

    approvalToggle.addEventListener('change', syncApproverField);
    syncApproverField();
})();
</script>
