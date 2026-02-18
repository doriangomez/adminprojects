<?php
$basePath = $basePath ?? '';
$talents = is_array($talents ?? null) ? $talents : [];
$editingTalent = is_array($editingTalent ?? null) ? $editingTalent : null;
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$services = is_array($services ?? null) ? $services : [];
$documentsByService = is_array($documentsByService ?? null) ? $documentsByService : [];
$flashMessage = (string) ($flashMessage ?? '');
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
    'updated' => 'Talento actualizado. Gestiona sus asignaciones desde Outsourcing.',
    'deleted' => 'Talento eliminado en cascada correctamente.',
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
                <span class="section-icon" aria-hidden="true">🧾</span>
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
                        <button type="submit" class="action-btn small warning solid">⏸️ Inactivar talento</button>
                    </form>

                    <form method="POST" action="<?= $basePath ?>/talents/delete" class="talent-delete-form" onsubmit="return confirm('¿Seguro que deseas eliminar este talento? Esta acción elimina asignaciones, timesheets y skills relacionados.');">
                        <input type="hidden" name="talent_id" value="<?= (int) ($editingTalent['id'] ?? 0) ?>">
                        <input type="hidden" name="math_operand1" value="<?= $dangerOp1 ?>">
                        <input type="hidden" name="math_operand2" value="<?= $dangerOp2 ?>">
                        <input type="hidden" name="math_operator" value="<?= $dangerOperator ?>">
                        <label>Confirmación matemática para eliminar: <?= $dangerOp1 . ' ' . $dangerOperator . ' ' . $dangerOp2 ?> = ?
                            <input type="number" name="math_result" required>
                        </label>
                        <button type="submit" class="action-btn small danger solid">🗑️ Eliminar talento en cascada</button>
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
            <label class="checkbox">
                <input type="checkbox" name="requiere_aprobacion_horas" value="1" <?= !empty($editingTalent['requiere_aprobacion_horas']) ? 'checked' : '' ?>>
                Requiere aprobación de horas
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
                <span class="section-icon" aria-hidden="true">🧑‍💼</span>
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
                                    <span class="icon" aria-hidden="true">🧩</span>
                                    <?= htmlspecialchars($talent['role'] ?? '') ?>
                                </span>
                            </div>
                            <span class="status-badge <?= $tipoClass ?>">
                                <span class="icon" aria-hidden="true">🏷️</span>
                                <?= htmlspecialchars(ucfirst((string) $tipoTalento)) ?>
                            </span>
                        </header>

                        <div class="talent-card__body">
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true">🏢</span>
                                <div>
                                    <span class="talent-card__label">Tipo</span>
                                    <strong><?= htmlspecialchars($talent['tipo_talento'] ?? 'interno') ?></strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true">⭐</span>
                                <div>
                                    <span class="talent-card__label">Seniority</span>
                                    <strong><?= htmlspecialchars($talent['seniority'] ?? 'N/A') ?></strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true">📊</span>
                                <div>
                                    <span class="talent-card__label">Disponibilidad</span>
                                    <strong><?= htmlspecialchars((string) ($talent['availability'] ?? 0)) ?>%</strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true">⏱️</span>
                                <div>
                                    <span class="talent-card__label">Capacidad</span>
                                    <strong><?= htmlspecialchars((string) ($talent['capacidad_horaria'] ?? 0)) ?>h/sem</strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true">💲</span>
                                <div>
                                    <span class="talent-card__label">Costo / Tarifa</span>
                                    <strong>$<?= number_format((float) ($talent['hourly_cost'] ?? 0), 0, ',', '.') ?> · $<?= number_format((float) ($talent['hourly_rate'] ?? 0), 0, ',', '.') ?></strong>
                                </div>
                            </div>
                            <div class="talent-card__item">
                                <span class="icon" aria-hidden="true">✉️</span>
                                <div>
                                    <span class="talent-card__label">Contacto</span>
                                    <strong><?= htmlspecialchars($talent['user_email'] ?? 'Sin usuario') ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="talent-card__indicators">
                            <span class="pill <?= $requiresReport ? 'pill-success' : 'pill-muted' ?>">
                                <span class="icon" aria-hidden="true">📝</span>
                                <?= $requiresReport ? 'Reporta horas' : 'Sin reporte' ?>
                            </span>
                            <span class="pill <?= $requiresApproval ? 'pill-warning' : 'pill-muted' ?>">
                                <span class="icon" aria-hidden="true">✅</span>
                                <?= $requiresApproval ? 'Requiere aprobación' : 'Sin aprobación' ?>
                            </span>
                            <span class="pill pill-muted">
                                <span class="icon" aria-hidden="true">🧠</span>
                                <?= htmlspecialchars($talent['skills'] ?? 'Skills n/a') ?>
                            </span>
                        </div>

                        <div class="talent-pmo-health">
                            <span class="pmo-chip">📁 Proyectos activos: <strong><?= (int) ($talent['active_projects'] ?? 0) ?></strong></span>
                            <span class="pmo-chip">🧩 Asignaciones: <strong><?= (int) ($talent['total_assignments'] ?? 0) ?></strong></span>
                            <span class="pmo-chip">⏳ Timesheets pendientes: <strong><?= (int) ($talent['pending_timesheets'] ?? 0) ?></strong></span>
                            <span class="pmo-chip">🕒 Horas reportadas: <strong><?= number_format((float) ($talent['reported_hours'] ?? 0), 1, ',', '.') ?>h</strong></span>
                            <span class="pmo-chip">📌 Servicios outsourcing: <strong><?= (int) ($talent['outsourcing_services'] ?? 0) ?></strong></span>
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
                                <button type="submit" class="action-btn small warning solid">⏸️ Inactivar talento</button>
                            </form>

                            <form method="POST" action="<?= $basePath ?>/talents/delete" class="talent-inline-action-form" onsubmit="return talentInlineMathConfirm(this, 'eliminar', '¿Seguro que deseas eliminar este talento? Esta acción elimina asignaciones, timesheets y skills relacionados.');">
                                <input type="hidden" name="talent_id" value="<?= (int) ($talent['id'] ?? 0) ?>">
                                <input type="hidden" name="math_operand1" value="<?= $deleteOp1 ?>">
                                <input type="hidden" name="math_operand2" value="<?= $deleteOp2 ?>">
                                <input type="hidden" name="math_operator" value="<?= $deleteOperator ?>">
                                <input type="hidden" name="math_result" value="">
                                <button type="submit" class="action-btn small danger solid">🗑️ Eliminar talento</button>
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
                <span class="section-icon" aria-hidden="true">📌</span>
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
    .talent-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border:1px solid var(--border); border-radius:18px; padding:18px; background: var(--surface); box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06); }
    .talent-form-section, .talent-grid, .talent-tracking { border:1px solid var(--border); border-radius:18px; padding:18px; background:var(--surface); display:flex; flex-direction:column; gap:14px; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05); }
    .section-head { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .section-title { display:flex; align-items:flex-start; gap:12px; }
    .section-icon { width:36px; height:36px; border-radius:12px; background:color-mix(in srgb, var(--primary) 15%, var(--surface)); display:inline-flex; align-items:center; justify-content:center; font-size:18px; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); }
    select, input { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); font-family:inherit; }
    .checkbox { flex-direction:row; align-items:center; gap:8px; }
    .divider { border-top:1px dashed var(--border); margin:8px 0; }
    .talent-cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px; }
    .talent-card { border:1px solid color-mix(in srgb, var(--border) 70%, var(--surface) 30%); border-radius:18px; padding:16px; background:color-mix(in srgb, var(--surface) 92%, var(--background) 8%); display:flex; flex-direction:column; gap:14px; }
    .talent-card__header { display:flex; justify-content:space-between; gap:12px; }
    .talent-card__name { display:block; font-size:16px; font-weight:700; color:var(--text-primary); }
    .talent-card__role { display:inline-flex; align-items:center; gap:6px; font-size:13px; color:var(--text-secondary); }
    .talent-card__body { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .talent-card__item { display:flex; gap:10px; align-items:center; background: var(--surface); border-radius:12px; padding:10px 12px; border:1px solid color-mix(in srgb, var(--border) 65%, var(--surface) 35%); }
    .talent-card__label { display:block; font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.04em; }
    .talent-card__indicators { display:flex; flex-wrap:wrap; gap:8px; }
    .talent-pmo-health { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:8px; }
    .pmo-chip { display:inline-flex; align-items:center; justify-content:space-between; gap:8px; border:1px solid color-mix(in srgb, var(--info) 28%, var(--border) 72%); background:color-mix(in srgb, var(--info) 8%, var(--surface) 92%); border-radius:10px; padding:7px 9px; font-size:12px; color:var(--text-primary); }
    .talent-card__footer { display:flex; flex-wrap:wrap; gap:8px; align-items:center; border-top:1px dashed var(--border); padding-top:10px; }
    .danger-text { color: var(--danger); font-weight:600; display:block; margin-top:4px; }
    .icon { font-size:15px; }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid transparent; }
    .pill-muted { background:color-mix(in srgb, var(--neutral) 12%, var(--surface) 88%); color:var(--text-secondary); border-color:color-mix(in srgb, var(--neutral) 30%, var(--surface) 70%); }
    .pill-success { background:color-mix(in srgb, var(--success) 15%, var(--surface) 85%); color:var(--success); border-color:color-mix(in srgb, var(--success) 35%, var(--surface) 65%); }
    .pill-warning { background:color-mix(in srgb, var(--warning) 15%, var(--surface) 85%); color:var(--warning); border-color:color-mix(in srgb, var(--warning) 35%, var(--surface) 65%); }
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
    .alert.success { padding:10px 12px; border-radius:12px; background:color-mix(in srgb, var(--success) 15%, var(--surface) 85%); color:var(--success); border:1px solid color-mix(in srgb, var(--success) 40%, var(--surface) 60%); font-weight:600; }
</style>
