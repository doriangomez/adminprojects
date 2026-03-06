<?php
$basePath = $basePath ?? '';
$reviewQueue = is_array($reviewQueue ?? null) ? $reviewQueue : [];
$validationQueue = is_array($validationQueue ?? null) ? $validationQueue : [];
$approvalQueue = is_array($approvalQueue ?? null) ? $approvalQueue : [];
$dispatchQueue = is_array($dispatchQueue ?? null) ? $dispatchQueue : [];
$timesheetApprovals = is_array($timesheetApprovals ?? null) ? $timesheetApprovals : [];
$timesheetHistory = is_array($timesheetHistory ?? null) ? $timesheetHistory : [];
$canApproveTimesheets = (bool) ($canApproveTimesheets ?? false);
$talentApprovalSummary = is_array($talentApprovalSummary ?? null) ? $talentApprovalSummary : [];
$talentApprovalWeeks = is_array($talentApprovalWeeks ?? null) ? $talentApprovalWeeks : [];
$talentApprovalPeriod = is_array($talentApprovalPeriod ?? null) ? $talentApprovalPeriod : [];
$canManageTimesheetWorkflow = (bool) ($canManageTimesheetWorkflow ?? false);
$canDeleteTimesheetWorkflowRecords = (bool) ($canDeleteTimesheetWorkflowRecords ?? false);
$roleFlags = is_array($roleFlags ?? null) ? $roleFlags : [];

$statusMeta = [
    'borrador' => ['label' => 'Borrador', 'class' => 'status-muted'],
    'final' => ['label' => 'Final', 'class' => 'status-success'],
    'publicado' => ['label' => 'Publicado', 'class' => 'status-success'],
    'en_revision' => ['label' => 'En revisión', 'class' => 'status-info'],
    'revisado' => ['label' => 'Revisado', 'class' => 'status-info'],
    'en_validacion' => ['label' => 'En validación', 'class' => 'status-info'],
    'validado' => ['label' => 'Validado', 'class' => 'status-success'],
    'en_aprobacion' => ['label' => 'En aprobación', 'class' => 'status-warning'],
    'aprobado' => ['label' => 'Aprobado', 'class' => 'status-success'],
    'rechazado' => ['label' => 'Rechazado', 'class' => 'status-danger'],
];

$renderRow = static function (array $doc, string $queue) use ($basePath, $statusMeta): void {
    $status = (string) ($doc['document_status'] ?? 'final');
    $meta = $statusMeta[$status] ?? ['label' => $status, 'class' => 'status-muted'];
    $tags = $doc['document_tags'] ?? [];
    $tagList = !empty($tags) ? implode(', ', array_map('strval', $tags)) : 'Sin tags';
    $phaseLabel = trim((string) ($doc['phase_name'] ?? $doc['phase_code'] ?? ''));
    $subphaseLabel = trim((string) ($doc['subphase_name'] ?? $doc['subphase_code'] ?? ''));
    $location = trim($phaseLabel . ' · ' . $subphaseLabel, ' ·');
    $reviewed = ($doc['reviewed_name'] ?? null) ?: ($doc['reviewed_by'] ? 'Usuario #' . (int) $doc['reviewed_by'] : null);
    $validated = ($doc['validated_name'] ?? null) ?: ($doc['validated_by'] ? 'Usuario #' . (int) $doc['validated_by'] : null);
    $approved = ($doc['approved_name'] ?? null) ?: ($doc['approved_by'] ? 'Usuario #' . (int) $doc['approved_by'] : null);
    $queueLabels = [
        'review' => 'Revisión',
        'validation' => 'Validación',
        'approval' => 'Aprobación',
        'dispatch-validation' => 'Envío a validación',
        'dispatch-approval' => 'Envío a aprobación',
    ];
    $queueLabel = $queueLabels[$queue] ?? 'Pendiente';
    ?>
    <article class="inbox-card" data-document-row data-queue-type="<?= htmlspecialchars($queue) ?>" data-document-id="<?= (int) ($doc['id'] ?? 0) ?>" data-project-id="<?= (int) ($doc['project_id'] ?? 0) ?>" data-document-status="<?= htmlspecialchars($status) ?>">
        <header class="inbox-card__header">
            <div class="inbox-card__heading">
                <span class="inbox-card__type"><?= htmlspecialchars($queueLabel) ?></span>
                <strong class="inbox-card__title"><?= htmlspecialchars($doc['file_name'] ?? '') ?></strong>
                <div class="meta-line">Proyecto: <?= htmlspecialchars($doc['project_name'] ?? '') ?></div>
                <?php if ($location !== ''): ?>
                    <div class="meta-line">Ubicación: <?= htmlspecialchars($location) ?></div>
                <?php endif; ?>
            </div>
            <div class="badge <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></div>
        </header>
        <div class="inbox-card__body">
            <div class="inbox-card__summary">
                <div>
                    <span class="meta-label">Estado actual</span>
                    <div class="status-pill <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></div>
                </div>
                <div>
                    <span class="meta-label">Responsable anterior</span>
                    <div><?= htmlspecialchars($reviewed ?? $validated ?? $approved ?? 'Sin registro') ?></div>
                </div>
                <div>
                    <span class="meta-label">Fecha</span>
                    <div><?= htmlspecialchars((string) ($doc['approved_at'] ?? $doc['validated_at'] ?? $doc['reviewed_at'] ?? '')) ?></div>
                </div>
            </div>
            <div class="inbox-card__grid">
                <div>
                    <span class="meta-label">Tipo documental</span>
                    <div><?= htmlspecialchars((string) ($doc['document_type'] ?? '')) ?></div>
                </div>
                <div>
                    <span class="meta-label">Versión</span>
                    <div><?= htmlspecialchars((string) ($doc['document_version'] ?? '')) ?></div>
                </div>
                <div>
                    <span class="meta-label">Tags</span>
                    <div><?= htmlspecialchars($tagList) ?></div>
                </div>
                <div>
                    <span class="meta-label">Flujo asignado</span>
                    <div>Revisor: <?= htmlspecialchars($doc['reviewer_name'] ?? ($doc['reviewer_id'] ? 'Usuario #' . (int) $doc['reviewer_id'] : 'No asignado')) ?></div>
                    <div>Validador: <?= htmlspecialchars($doc['validator_name'] ?? ($doc['validator_id'] ? 'Usuario #' . (int) $doc['validator_id'] : 'No asignado')) ?></div>
                    <div>Aprobador: <?= htmlspecialchars($doc['approver_name'] ?? ($doc['approver_id'] ? 'Usuario #' . (int) $doc['approver_id'] : 'No asignado')) ?></div>
                </div>
                <div>
                    <span class="meta-label">Trazabilidad</span>
                    <div><?= $reviewed ? 'Revisado por ' . htmlspecialchars($reviewed) . ' · ' . htmlspecialchars((string) ($doc['reviewed_at'] ?? '')) : 'Revisión pendiente' ?></div>
                    <div><?= $validated ? 'Validado por ' . htmlspecialchars($validated) . ' · ' . htmlspecialchars((string) ($doc['validated_at'] ?? '')) : 'Validación pendiente' ?></div>
                    <div><?= $approved ? 'Aprobado por ' . htmlspecialchars($approved) . ' · ' . htmlspecialchars((string) ($doc['approved_at'] ?? '')) : 'Aprobación pendiente' ?></div>
                </div>
            </div>
        </div>
        <div class="inbox-card__footer">
            <a class="action-btn small action-btn--view" href="<?= $basePath ?>/projects/<?= (int) ($doc['project_id'] ?? 0) ?>/nodes/<?= (int) ($doc['id'] ?? 0) ?>/download">Ver</a>
            <button class="action-btn small action-btn--history" type="button" data-toggle-history>Historial</button>
        </div>
        <div class="history-panel" data-history-panel hidden>
            <strong>Historial</strong>
            <ul class="history-list" data-history-list>
                <li class="section-muted">Cargando historial...</li>
            </ul>
        </div>
        <div class="action-panel">
            <textarea rows="2" placeholder="Comentario opcional" data-comment-input></textarea>
            <div class="action-panel__buttons" data-action-slot></div>
        </div>
    </article>
    <?php
};
?>

<section class="approvals-shell">
    <header class="page-heading">
        <h2>Bandeja de Aprobaciones</h2>
        <p>Gestiona revisiones, validaciones y aprobaciones desde un único lugar, con trazabilidad ISO 9001.</p>
    </header>

    <div class="toast" data-toast hidden></div>

    <div class="approvals-grid">
        <section class="approvals-section" data-queue="review">
            <header>
                <h3>Revisiones pendientes</h3>
                <p class="section-muted">Documentos en revisión asignados a ti.</p>
            </header>
            <?php if (empty($reviewQueue)): ?>
                <p class="section-muted empty">No tienes documentos en revisión.</p>
            <?php else: ?>
                <?php foreach ($reviewQueue as $doc): ?>
                    <?php $renderRow($doc, 'review'); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="approvals-section" data-queue="validation">
            <header>
                <h3>Validaciones pendientes</h3>
                <p class="section-muted">Documentos listos para validación asignados a ti.</p>
            </header>
            <?php if (empty($validationQueue)): ?>
                <p class="section-muted empty">No tienes documentos en validación.</p>
            <?php else: ?>
                <?php foreach ($validationQueue as $doc): ?>
                    <?php $renderRow($doc, 'validation'); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="approvals-section" data-queue="approval">
            <header>
                <h3>Aprobaciones pendientes</h3>
                <p class="section-muted">Documentos listos para aprobación asignados a ti.</p>
            </header>
            <?php if (empty($approvalQueue)): ?>
                <p class="section-muted empty">No tienes documentos en aprobación.</p>
            <?php else: ?>
                <?php foreach ($approvalQueue as $doc): ?>
                    <?php $renderRow($doc, 'approval'); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="approvals-section" data-queue="timesheets">
            <header>
                <?php if ($canApproveTimesheets): ?>
                    <h3>Timesheets por aprobar</h3>
                    <p class="section-muted">Horas agrupadas por semana con resumen por proyecto e historial auditable.</p>
                <?php else: ?>
                    <h3>Mis horas aprobadas</h3>
                    <p class="section-muted">Seguimiento de tus horas del mes actual, con detalle semanal de aprobación.</p>
                <?php endif; ?>
            </header>
            <?php if ($canApproveTimesheets): ?>
                <?php if (empty($timesheetApprovals)): ?>
                    <p class="section-muted empty">No hay horas pendientes de aprobación.</p>
                <?php else: ?>
                    <div class="timesheet-cards">
                        <?php foreach ($timesheetApprovals as $week): ?>
                            <article class="inbox-card timesheet-card" data-queue-type="timesheets">
                                <header class="inbox-card__header">
                                    <div class="inbox-card__heading">
                                        <span class="inbox-card__type">Semana</span>
                                        <strong class="inbox-card__title"><?= htmlspecialchars((string) ($week['week_label'] ?? '')) ?></strong>
                                        <div class="meta-line">Total: <?= htmlspecialchars((string) round((float) ($week['total_hours'] ?? 0), 2)) ?>h</div>
                                    </div>
                                    <div class="badge status-warning">Pendiente</div>
                                </header>
                                <div class="inbox-card__body">
                                    <div class="inbox-card__grid">
                                        <div>
                                            <span class="meta-label">Resumen por proyecto</span>
                                            <?php foreach (($week['project_summary'] ?? []) as $summary): ?>
                                                <div><?= htmlspecialchars((string) ($summary['project'] ?? '')) ?> · <?= htmlspecialchars((string) round((float) ($summary['hours'] ?? 0), 2)) ?>h</div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="inbox-card__footer">
                                    <a class="action-btn small action-btn--view" href="<?= $basePath ?>/timesheets?week=<?= htmlspecialchars((new DateTimeImmutable((string) ($week['week_start'] ?? 'now')))->format('o-\WW')) ?>">Ver semana</a>
                                    <form method="POST" action="<?= $basePath ?>/timesheets/approve-week" class="inline-form">
                                        <input type="hidden" name="week_start" value="<?= htmlspecialchars((string) ($week['week_start'] ?? '')) ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <input type="text" name="comment" placeholder="Detalle aprobación (opcional)">
                                        <button type="submit" class="action-btn small primary">✅ Aprobar semana</button>
                                    </form>
                                    <form method="POST" action="<?= $basePath ?>/timesheets/approve-week" class="inline-form">
                                        <input type="hidden" name="week_start" value="<?= htmlspecialchars((string) ($week['week_start'] ?? '')) ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <input type="text" name="comment" placeholder="Motivo rechazo" required>
                                        <button type="submit" class="action-btn small danger">❌ Rechazar semana</button>
                                    </form>
                                </div>

                                <?php
                                $rowsByDate = [];
                                foreach (($week['rows'] ?? []) as $row) {
                                    $d = (string) ($row['date'] ?? '');
                                    if ($d !== '') {
                                        $rowsByDate[$d] = ($rowsByDate[$d] ?? 0) + (float) ($row['hours'] ?? 0);
                                    }
                                }
                                ksort($rowsByDate);
                                ?>
                                <div class="timesheet-days-breakdown" style="margin-top:12px;padding:0 18px 12px;border-top:1px solid var(--border, #e5e7eb);">
                                    <span class="meta-label">Aprobar por día</span>
                                    <?php foreach ($rowsByDate as $dayDate => $dayHours): ?>
                                        <div class="day-approval-row" style="display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap;">
                                            <span><?= htmlspecialchars($dayDate) ?> · <?= htmlspecialchars((string) round($dayHours, 2)) ?>h</span>
                                            <form method="POST" action="<?= $basePath ?>/timesheets/approve-day" class="inline-form" style="margin:0;">
                                                <input type="hidden" name="date" value="<?= htmlspecialchars($dayDate) ?>">
                                                <button type="submit" class="action-btn small primary">Aprobar día</button>
                                            </form>
                                            <form method="POST" action="<?= $basePath ?>/timesheets/reject-day" class="inline-form" style="margin:0;">
                                                <input type="hidden" name="date" value="<?= htmlspecialchars($dayDate) ?>">
                                                <input type="text" name="comment" placeholder="Motivo rechazo" required style="min-width:120px;">
                                                <button type="submit" class="action-btn small danger">Rechazar día</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="meta-label" style="padding:0 18px 6px;">Detalle de registros</div>
                                <?php foreach (($week['rows'] ?? []) as $row): ?>
                                    <div class="meta-line" style="padding:0 18px 10px;">• <?= htmlspecialchars((string) ($row['date'] ?? '')) ?> · <?= htmlspecialchars((string) ($row['project'] ?? '')) ?> · <?= htmlspecialchars((string) round((float) ($row['hours'] ?? 0), 2)) ?>h</div>
                                <?php endforeach; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="history-panel" style="margin-top:16px;">
                    <span class="meta-label">Historial de decisiones semanales</span>
                    <?php if (empty($timesheetHistory)): ?>
                        <p class="section-muted" style="margin:4px 0 0;">Aún no hay eventos registrados.</p>
                    <?php else: ?>
                        <ul class="history-list">
                            <?php foreach ($timesheetHistory as $event): ?>
                                <?php
                                $action = (string) ($event['action'] ?? 'updated');
                                $labels = [
                                    'approved' => 'Aprobado',
                                    'rejected' => 'Rechazado',
                                    'reopened' => 'Reabierto',
                                    'deleted' => 'Eliminado',
                                ];
                                $statusClass = $action === 'approved' ? 'status-success' : ($action === 'rejected' ? 'status-danger' : ($action === 'reopened' ? 'status-info' : 'status-muted'));
                                ?>
                                <li>
                                    <span class="status-pill <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($labels[$action] ?? ucfirst($action)) ?></span>
                                    Semana <?= htmlspecialchars((string) ($event['week_start'] ?? '')) ?> a <?= htmlspecialchars((string) ($event['week_end'] ?? '')) ?> ·
                                    por <?= htmlspecialchars((string) ($event['actor_name'] ?? 'Sistema')) ?> ·
                                    <?= htmlspecialchars((string) ($event['created_at'] ?? '')) ?>
                                    <?php if (!empty($event['action_comment'])): ?>
                                        <div class="meta-line">Comentario: <?= htmlspecialchars((string) $event['action_comment']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($event['previous_status']) || !empty($event['resulting_status'])): ?>
                                        <div class="meta-line">Cambio: <?= htmlspecialchars((string) ($event['previous_status'] ?? 'n/a')) ?> → <?= htmlspecialchars((string) ($event['resulting_status'] ?? 'n/a')) ?></div>
                                    <?php endif; ?>
                                    <?php if ($canManageTimesheetWorkflow): ?>
                                        <form method="POST" action="<?= $basePath ?>/timesheets/reopen-week" class="inline-form" style="margin-top:6px;">
                                            <input type="hidden" name="week_start" value="<?= htmlspecialchars((string) ($event['week_start'] ?? '')) ?>">
                                            <input type="hidden" name="approver_user_id" value="<?= (int) ($event['target_approver_user_id'] ?? 0) ?>">
                                            <input type="text" name="comment" placeholder="Comentario de reapertura (opcional)">
                                            <button type="submit" class="action-btn small">↩️ Reabrir semana</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canDeleteTimesheetWorkflowRecords): ?>
                                        <form method="POST" action="<?= $basePath ?>/timesheets/delete-week-workflow" class="inline-form" style="margin-top:6px;">
                                            <input type="hidden" name="week_start" value="<?= htmlspecialchars((string) ($event['week_start'] ?? '')) ?>">
                                            <input type="hidden" name="approver_user_id" value="<?= (int) ($event['target_approver_user_id'] ?? 0) ?>">
                                            <input type="text" name="comment" placeholder="Motivo eliminación (opcional)">
                                            <button type="submit" class="action-btn small danger">🗑️ Eliminar registro</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php
                $periodStart = $talentApprovalPeriod['start'] ?? null;
                $periodEnd = $talentApprovalPeriod['end'] ?? null;
                $approvedMonthHours = (float) ($talentApprovalSummary['approved'] ?? 0);
                $pendingMonthHours = (float) ($talentApprovalSummary['pending'] ?? 0);
                $rejectedMonthHours = (float) ($talentApprovalSummary['rejected'] ?? 0);
                ?>
                <div class="inbox-card timesheet-card" data-queue-type="timesheets">
                    <header class="inbox-card__header">
                        <div class="inbox-card__heading">
                            <span class="inbox-card__type">Resumen mensual</span>
                            <strong class="inbox-card__title">
                                <?= $periodStart instanceof DateTimeImmutable && $periodEnd instanceof DateTimeImmutable
                                    ? htmlspecialchars($periodStart->format('d/m/Y') . ' - ' . $periodEnd->format('d/m/Y'))
                                    : 'Mes actual' ?>
                            </strong>
                        </div>
                    </header>
                    <div class="inbox-card__body">
                        <div class="inbox-card__summary">
                            <div><span class="meta-label">Aprobadas</span><div><?= round($approvedMonthHours, 2) ?>h</div></div>
                            <div><span class="meta-label">Pendientes</span><div><?= round($pendingMonthHours, 2) ?>h</div></div>
                            <div><span class="meta-label">Rechazadas</span><div><?= round($rejectedMonthHours, 2) ?>h</div></div>
                        </div>
                    </div>
                </div>

                <?php if (empty($talentApprovalWeeks)): ?>
                    <p class="section-muted empty">Aún no tienes semanas registradas en este periodo.</p>
                <?php else: ?>
                    <div class="timesheet-cards">
                        <?php foreach ($talentApprovalWeeks as $week): ?>
                            <?php
                            $start = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                            $stateWeight = (int) ($week['status_weight'] ?? 0);
                            $state = $stateWeight >= 5 ? 'Aprobada' : ($stateWeight >= 4 ? 'Rechazada' : 'Pendiente');
                            $stateClass = $stateWeight >= 5 ? 'status-success' : ($stateWeight >= 4 ? 'status-danger' : 'status-warning');
                            ?>
                            <article class="inbox-card timesheet-card" data-queue-type="timesheets">
                                <header class="inbox-card__header">
                                    <div class="inbox-card__heading">
                                        <span class="inbox-card__type">Semana</span>
                                        <strong class="inbox-card__title">Semana <?= htmlspecialchars($start->format('W')) ?></strong>
                                        <div class="meta-line">Total: <?= round((float) ($week['total_hours'] ?? 0), 2) ?>h</div>
                                    </div>
                                    <div class="badge <?= htmlspecialchars($stateClass) ?>"><?= htmlspecialchars($state) ?></div>
                                </header>
                                <div class="inbox-card__footer">
                                    <a class="action-btn small action-btn--view" href="<?= $basePath ?>/timesheets?week=<?= htmlspecialchars($start->format('o-\WW')) ?>">Ver semana</a>
                                    <span class="meta-line">Aprobador: <?= htmlspecialchars((string) ($week['approver_name'] ?? 'Sin asignar')) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if (!empty($dispatchQueue) && ($roleFlags['can_manage'] ?? false)): ?>
            <section class="approvals-section" data-queue="dispatch-validation">
                <header>
                    <h3>Envíos pendientes a validación</h3>
                    <p class="section-muted">Documentos revisados que requieren envío a validación.</p>
                </header>
                <?php if (empty($dispatchQueue['send_validation'])): ?>
                    <p class="section-muted empty">Sin documentos revisados pendientes.</p>
                <?php else: ?>
                    <?php foreach ($dispatchQueue['send_validation'] as $doc): ?>
                        <?php $renderRow($doc, 'dispatch-validation'); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
            <section class="approvals-section" data-queue="dispatch-approval">
                <header>
                    <h3>Envíos pendientes a aprobación</h3>
                    <p class="section-muted">Documentos validados que requieren envío a aprobación.</p>
                </header>
                <?php if (empty($dispatchQueue['send_approval'])): ?>
                    <p class="section-muted empty">Sin documentos validados pendientes.</p>
                <?php else: ?>
                    <?php foreach ($dispatchQueue['send_approval'] as $doc): ?>
                        <?php $renderRow($doc, 'dispatch-approval'); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>

<style>
    .approvals-shell { display:flex; flex-direction:column; gap:16px; }
    .approvals-grid { display:flex; flex-direction:column; gap:20px; }
    .approvals-section { background: var(--surface); border:1px solid var(--border); border-radius:18px; padding:18px; display:flex; flex-direction:column; gap:14px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06); }
    .approvals-section header { display:flex; flex-direction:column; gap:4px; }
    .approvals-section header h3 { margin:0; display:flex; align-items:center; gap:8px; }
    .approvals-section[data-queue="review"] header h3::before { content:"🔎"; }
    .approvals-section[data-queue="validation"] header h3::before { content:"✅"; }
    .approvals-section[data-queue="approval"] header h3::before { content:"🟢"; }
    .approvals-section[data-queue="timesheets"] header h3::before { content:"🕒"; }
    .approvals-section[data-queue="dispatch-validation"] header h3::before { content:"📨"; }
    .approvals-section[data-queue="dispatch-approval"] header h3::before { content:"📬"; }
    .section-muted { color: var(--text-secondary); margin:0; font-size:13px; }
    .section-muted.empty { padding:10px; border:1px dashed var(--border); border-radius:10px; background: color-mix(in srgb, var(--surface) 84%, var(--background) 16%); }
    .inbox-card { border:1px solid var(--border); border-radius:16px; padding:14px; background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); display:flex; flex-direction:column; gap:12px; position:relative; }
    .inbox-card::before { content:""; position:absolute; inset:0; border-radius:16px; border:1px solid transparent; pointer-events:none; }
    .inbox-card[data-queue-type="review"]::before { border-color: color-mix(in srgb, var(--accent) 40%, transparent); }
    .inbox-card[data-queue-type="validation"]::before { border-color: color-mix(in srgb, var(--success) 40%, transparent); }
    .inbox-card[data-queue-type="approval"]::before { border-color: color-mix(in srgb, var(--warning) 50%, transparent); }
    .inbox-card[data-queue-type="timesheets"]::before { border-color: color-mix(in srgb, var(--accent) 30%, transparent); }
    .inbox-card[data-queue-type="dispatch-validation"]::before { border-color: color-mix(in srgb, var(--accent) 30%, transparent); }
    .inbox-card[data-queue-type="dispatch-approval"]::before { border-color: color-mix(in srgb, var(--accent) 30%, transparent); }
    .inbox-card__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .inbox-card__heading { display:flex; flex-direction:column; gap:4px; }
    .inbox-card__title { font-size:15px; }
    .inbox-card__type { font-size:11px; text-transform:uppercase; letter-spacing:0.08em; font-weight:700; color: var(--text-secondary); display:inline-flex; align-items:center; gap:6px; }
    .inbox-card[data-queue-type="review"] .inbox-card__type::before { content:"🔎"; }
    .inbox-card[data-queue-type="validation"] .inbox-card__type::before { content:"✅"; }
    .inbox-card[data-queue-type="approval"] .inbox-card__type::before { content:"🟢"; }
    .inbox-card[data-queue-type="timesheets"] .inbox-card__type::before { content:"🕒"; }
    .inbox-card[data-queue-type="dispatch-validation"] .inbox-card__type::before { content:"📨"; }
    .inbox-card[data-queue-type="dispatch-approval"] .inbox-card__type::before { content:"📬"; }
    .badge { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
    .status-muted { background: color-mix(in srgb, var(--surface) 80%, var(--border) 20%); color: var(--text-secondary); }
    .status-info { background: color-mix(in srgb, var(--accent) 18%, var(--surface) 82%); color: var(--text-primary); }
    .status-warning { background: color-mix(in srgb, var(--warning) 24%, var(--surface) 76%); color: var(--text-primary); }
    .status-success { background: color-mix(in srgb, var(--success) 24%, var(--surface) 76%); color: var(--text-primary); }
    .status-danger { background: color-mix(in srgb, var(--danger) 22%, var(--surface) 78%); color: var(--text-primary); }
    .status-pill { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
    .meta-line { font-size:12px; color: var(--text-secondary); margin-top:4px; }
    .inbox-card__body { display:flex; flex-direction:column; gap:12px; }
    .inbox-card__summary { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%); padding:10px; border-radius:12px; border:1px solid var(--border); }
    .inbox-card__grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
    .meta-label { text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color: var(--text-secondary); display:block; margin-bottom:4px; font-weight:700; }
    .inbox-card__footer { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:10px; padding:8px 12px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; box-shadow: 0 6px 14px rgba(15, 23, 42, 0.06); }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .action-btn.danger { background: color-mix(in srgb, var(--danger) 18%, var(--surface) 82%); color: var(--text-primary); border-color: color-mix(in srgb, var(--danger) 35%, var(--border) 65%); }
    .action-btn--view::before { content:"👁️"; }
    .action-btn--history::before { content:"🗂️"; }
    .action-btn[data-action="reviewed"]::before { content:"🔎"; }
    .action-btn[data-action="validated"]::before { content:"✅"; }
    .action-btn[data-action="approved"]::before { content:"🟢"; }
    .action-btn[data-action="send_validation"]::before { content:"📨"; }
    .action-btn[data-action="send_approval"]::before { content:"📬"; }
    .action-btn[data-action="rejected"]::before { content:"❌"; }
    .action-btn.small { padding:6px 8px; font-size:13px; }
    .action-panel { display:flex; flex-direction:column; gap:8px; }
    .action-panel textarea { border:1px solid var(--border); border-radius:8px; padding:6px 8px; font-size:12px; width:100%; }
    .action-panel__buttons { display:flex; gap:8px; flex-wrap:wrap; }
    .history-panel { background: color-mix(in srgb, var(--surface) 84%, var(--background) 16%); border:1px dashed var(--border); border-radius:10px; padding:8px; }
    .history-list { margin:6px 0 0; padding-left:18px; color: var(--text-primary); font-size:12px; }
    .toast { position:sticky; top:12px; align-self:flex-start; background: color-mix(in srgb, var(--success) 18%, var(--surface) 82%); color: var(--text-primary); padding:8px 12px; border-radius:10px; border:1px solid color-mix(in srgb, var(--success) 40%, var(--border) 60%); font-weight:600; font-size:13px; }
    .toast.error { background: color-mix(in srgb, var(--danger) 18%, var(--surface) 82%); color: var(--text-primary); border-color: color-mix(in srgb, var(--danger) 40%, var(--border) 60%); }
    .timesheet-cards { display:grid; gap:14px; }
    .wrap-anywhere { overflow-wrap:anywhere; max-width:240px; }
    .timesheet-card .inbox-card__footer { align-items:flex-start; }
    .inline-form { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .inline-form input { border:1px solid var(--border); border-radius:8px; padding:6px 8px; font-size:12px; background: var(--surface); color: var(--text-primary); }
    @media (max-width: 900px) {
        .inbox-card__header { flex-direction:column; align-items:flex-start; }
    }
</style>

<script>
    (() => {
        const basePath = <?= json_encode($basePath) ?>;
        const toast = document.querySelector('[data-toast]');
        const historyLabels = {
            file_created: 'Subido',
            document_uploaded: 'Subido',
            document_metadata_updated: 'Metadata actualizada',
            document_flow_assigned: 'Flujo asignado',
            document_sent_review: 'Enviado a revisión',
            document_reviewed: 'Revisado',
            document_sent_validation: 'Enviado a validación',
            document_validated: 'Validado',
            document_sent_approval: 'Enviado a aprobación',
            document_approved: 'Aprobado',
            document_rejected: 'Rechazado',
            document_status_updated: 'Estado actualizado',
            file_deleted: 'Eliminado',
        };

        const showToast = (message, tone = 'success') => {
            if (!toast) return;
            toast.textContent = message;
            toast.className = `toast ${tone === 'error' ? 'error' : ''}`;
            toast.hidden = false;
            setTimeout(() => {
                toast.hidden = true;
            }, 3500);
        };

        const buildActions = (queue) => {
            switch (queue) {
                case 'review':
                    return [
                        { action: 'reviewed', label: 'Revisar', tone: 'primary' },
                        { action: 'rejected', label: 'Rechazar', tone: 'danger' },
                    ];
                case 'validation':
                    return [
                        { action: 'validated', label: 'Validar', tone: 'primary' },
                        { action: 'rejected', label: 'Rechazar', tone: 'danger' },
                    ];
                case 'approval':
                    return [
                        { action: 'approved', label: 'Aprobar', tone: 'primary' },
                        { action: 'rejected', label: 'Rechazar', tone: 'danger' },
                    ];
                case 'dispatch-validation':
                    return [
                        { action: 'send_validation', label: 'Enviar a validación', tone: 'primary' },
                    ];
                case 'dispatch-approval':
                    return [
                        { action: 'send_approval', label: 'Enviar a aprobación', tone: 'primary' },
                    ];
                default:
                    return [];
            }
        };

        document.querySelectorAll('[data-queue]').forEach(section => {
            const queue = section.dataset.queue;
            section.querySelectorAll('[data-document-row]').forEach(row => {
                const slot = row.querySelector('[data-action-slot]');
                if (!slot) return;
                slot.innerHTML = '';
                buildActions(queue).forEach(config => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = `action-btn small ${config.tone === 'primary' ? 'primary' : ''} ${config.tone === 'danger' ? 'danger' : ''}`;
                    button.dataset.action = config.action;
                    button.textContent = config.label;
                    slot.appendChild(button);
                });
            });
        });

        document.addEventListener('click', (event) => {
            const actionBtn = event.target.closest('[data-action]');
            if (actionBtn) {
                const row = actionBtn.closest('[data-document-row]');
                if (!row) return;
                const action = actionBtn.dataset.action;
                const projectId = row.dataset.projectId;
                const documentId = row.dataset.documentId;
                const commentInput = row.querySelector('[data-comment-input]');
                const comment = commentInput ? commentInput.value.trim() : '';
                console.log('Click en comentario/seguimiento documental:', {
                    action,
                    projectId,
                    documentId,
                    hasComment: comment !== '',
                });
                actionBtn.disabled = true;
                fetch(`${basePath}/projects/${projectId}/nodes/${documentId}/document-status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ action, comment }),
                })
                    .then(response => response.json())
                    .then(payload => {
                        if (payload.status !== 'ok') {
                            throw new Error(payload.message || 'No se pudo actualizar el estado.');
                        }
                        row.remove();
                        showToast('Estado documental actualizado.', 'success');
                    })
                    .catch(error => {
                        showToast(error.message, 'error');
                    })
                    .finally(() => {
                        actionBtn.disabled = false;
                    });
            }

            const toggleHistory = event.target.closest('[data-toggle-history]');
            if (toggleHistory) {
                const row = toggleHistory.closest('[data-document-row]');
                const panel = row?.querySelector('[data-history-panel]');
                const list = row?.querySelector('[data-history-list]');
                if (!panel || !list) return;
                panel.hidden = !panel.hidden;
                if (panel.hidden || panel.dataset.loaded === 'true') {
                    return;
                }
                const projectId = row.dataset.projectId;
                const documentId = row.dataset.documentId;
                fetch(`${basePath}/projects/${projectId}/nodes/${documentId}/document-history`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(response => response.json())
                    .then(payload => {
                        if (payload.status !== 'ok') {
                            throw new Error(payload.message || 'No se pudo cargar el historial.');
                        }
                        list.innerHTML = '';
                        const entries = Array.isArray(payload.data) ? payload.data : [];
                        if (entries.length === 0) {
                            list.innerHTML = '<li class="section-muted">Sin historial.</li>';
                        } else {
                            entries.forEach(entry => {
                                const item = document.createElement('li');
                                const actor = entry.user_name ? entry.user_name : (entry.user_id ? `Usuario #${entry.user_id}` : 'Sistema');
                                const label = historyLabels[entry.action] || entry.action || 'Evento';
                                const note = entry.payload && entry.payload.comment ? ` · ${entry.payload.comment}` : '';
                                item.textContent = `${label} · ${actor} · ${entry.created_at}${note}`;
                                list.appendChild(item);
                            });
                        }
                        panel.dataset.loaded = 'true';
                    })
                    .catch(error => {
                        list.innerHTML = `<li class="section-muted">${error.message}</li>`;
                    });
            }
        });
    })();
</script>
