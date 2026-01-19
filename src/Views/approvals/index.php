<?php
$basePath = $basePath ?? '/project/public';
$reviewQueue = is_array($reviewQueue ?? null) ? $reviewQueue : [];
$validationQueue = is_array($validationQueue ?? null) ? $validationQueue : [];
$approvalQueue = is_array($approvalQueue ?? null) ? $approvalQueue : [];
$dispatchQueue = is_array($dispatchQueue ?? null) ? $dispatchQueue : [];
$roleFlags = is_array($roleFlags ?? null) ? $roleFlags : [];

$statusMeta = [
    'borrador' => ['label' => 'Borrador', 'class' => 'status-muted'],
    'en_revision' => ['label' => 'En revisión', 'class' => 'status-info'],
    'revisado' => ['label' => 'Revisado', 'class' => 'status-info'],
    'en_validacion' => ['label' => 'En validación', 'class' => 'status-info'],
    'validado' => ['label' => 'Validado', 'class' => 'status-success'],
    'en_aprobacion' => ['label' => 'En aprobación', 'class' => 'status-warning'],
    'aprobado' => ['label' => 'Aprobado', 'class' => 'status-success'],
    'rechazado' => ['label' => 'Rechazado', 'class' => 'status-danger'],
];

$renderRow = static function (array $doc) use ($basePath, $statusMeta): void {
    $status = (string) ($doc['document_status'] ?? 'borrador');
    $meta = $statusMeta[$status] ?? ['label' => $status, 'class' => 'status-muted'];
    $tags = $doc['document_tags'] ?? [];
    $tagList = !empty($tags) ? implode(', ', array_map('strval', $tags)) : 'Sin tags';
    $phaseLabel = trim((string) ($doc['phase_name'] ?? $doc['phase_code'] ?? ''));
    $subphaseLabel = trim((string) ($doc['subphase_name'] ?? $doc['subphase_code'] ?? ''));
    $location = trim($phaseLabel . ' · ' . $subphaseLabel, ' ·');
    $reviewed = ($doc['reviewed_name'] ?? null) ?: ($doc['reviewed_by'] ? 'Usuario #' . (int) $doc['reviewed_by'] : null);
    $validated = ($doc['validated_name'] ?? null) ?: ($doc['validated_by'] ? 'Usuario #' . (int) $doc['validated_by'] : null);
    $approved = ($doc['approved_name'] ?? null) ?: ($doc['approved_by'] ? 'Usuario #' . (int) $doc['approved_by'] : null);
    ?>
    <article class="inbox-card" data-document-row data-document-id="<?= (int) ($doc['id'] ?? 0) ?>" data-project-id="<?= (int) ($doc['project_id'] ?? 0) ?>" data-document-status="<?= htmlspecialchars($status) ?>">
        <header class="inbox-card__header">
            <div>
                <strong>📄 <?= htmlspecialchars($doc['file_name'] ?? '') ?></strong>
                <div class="meta-line">Proyecto: <?= htmlspecialchars($doc['project_name'] ?? '') ?></div>
                <?php if ($location !== ''): ?>
                    <div class="meta-line">Ubicación: <?= htmlspecialchars($location) ?></div>
                <?php endif; ?>
            </div>
            <div class="badge <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></div>
        </header>
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
        <div class="inbox-card__footer">
            <a class="action-btn small" href="<?= $basePath ?>/projects/<?= (int) ($doc['project_id'] ?? 0) ?>/nodes/<?= (int) ($doc['id'] ?? 0) ?>/download">Ver</a>
            <button class="action-btn small" type="button" data-toggle-history>Historial</button>
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
                    <?php $renderRow($doc); ?>
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
                    <?php $renderRow($doc); ?>
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
                    <?php $renderRow($doc); ?>
                <?php endforeach; ?>
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
                        <?php $renderRow($doc); ?>
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
                        <?php $renderRow($doc); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>

<style>
    .approvals-shell { display:flex; flex-direction:column; gap:16px; }
    .approvals-grid { display:flex; flex-direction:column; gap:20px; }
    .approvals-section { background: var(--surface); border:1px solid var(--border); border-radius:16px; padding:16px; display:flex; flex-direction:column; gap:12px; }
    .approvals-section header h3 { margin:0; }
    .section-muted { color: var(--muted); margin:0; font-size:13px; }
    .section-muted.empty { padding:10px; border:1px dashed var(--border); border-radius:10px; background:#f8fafc; }
    .inbox-card { border:1px solid var(--border); border-radius:14px; padding:12px; background:#fff; display:flex; flex-direction:column; gap:10px; }
    .inbox-card__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .badge { padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
    .status-muted { background:#e2e8f0; color:#475569; }
    .status-info { background:#e0f2fe; color:#075985; }
    .status-warning { background:#fef3c7; color:#92400e; }
    .status-success { background:#dcfce7; color:#166534; }
    .status-danger { background:#fee2e2; color:#991b1b; }
    .meta-line { font-size:12px; color: var(--muted); margin-top:4px; }
    .inbox-card__grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
    .meta-label { text-transform:uppercase; font-size:11px; letter-spacing:0.04em; color: var(--muted); display:block; margin-bottom:4px; font-weight:700; }
    .inbox-card__footer { display:flex; gap:8px; flex-wrap:wrap; }
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color:#fff; border-color: var(--primary); }
    .action-btn.danger { background:#fee2e2; color:#991b1b; border-color:#fecdd3; }
    .action-btn.small { padding:6px 8px; font-size:13px; }
    .action-panel { display:flex; flex-direction:column; gap:8px; }
    .action-panel textarea { border:1px solid var(--border); border-radius:8px; padding:6px 8px; font-size:12px; width:100%; }
    .action-panel__buttons { display:flex; gap:8px; flex-wrap:wrap; }
    .history-panel { background:#f8fafc; border:1px dashed var(--border); border-radius:10px; padding:8px; }
    .history-list { margin:6px 0 0; padding-left:18px; color: var(--text-strong); font-size:12px; }
    .toast { position:sticky; top:12px; align-self:flex-start; background:#dcfce7; color:#166534; padding:8px 12px; border-radius:10px; border:1px solid #bbf7d0; font-weight:600; font-size:13px; }
    .toast.error { background:#fee2e2; color:#991b1b; border-color:#fecdd3; }
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
