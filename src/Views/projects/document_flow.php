<?php
$documentFlowId = $documentFlowId ?? ('document-flow-' . bin2hex(random_bytes(4)));
$documentNode = is_array($documentNode ?? null) ? $documentNode : [];
$documentExpectedDocs = is_array($documentExpectedDocs ?? null) ? $documentExpectedDocs : [];
$documentTagOptions = is_array($documentTagOptions ?? null) ? $documentTagOptions : [];
$documentKeyTags = is_array($documentKeyTags ?? null) ? $documentKeyTags : $documentExpectedDocs;
$documentCanManage = !empty($documentCanManage);
$documentProjectId = (int) ($documentProjectId ?? 0);
$documentBasePath = $documentBasePath ?? '/project/public';
$documentCurrentUser = is_array($documentCurrentUser ?? null) ? $documentCurrentUser : [];
$documentFiles = is_array($documentNode['files'] ?? null) ? $documentNode['files'] : [];
$documentNodeName = (string) ($documentNode['name'] ?? $documentNode['title'] ?? $documentNode['code'] ?? 'Subfase');
$documentNodeCode = (string) ($documentNode['code'] ?? '');

?>

<section class="document-flow" data-document-flow="<?= htmlspecialchars($documentFlowId) ?>">
    <header class="document-header">
        <div>
            <p class="eyebrow" style="margin:0; color: var(--muted);">SUBFASE · <?= htmlspecialchars($documentNodeCode) ?></p>
            <h4 style="margin:4px 0 0;">Gestión documental de <?= htmlspecialchars($documentNodeName) ?></h4>
            <small style="color: var(--muted);">Carga libre, tagueo manual y flujo de revisión por documento.</small>
        </div>
    </header>

    <div class="document-grid">
        <section class="document-section">
            <h5>Documentos esperados en esta subfase</h5>
            <p class="section-muted">Referencia informativa (sin carga ni bloqueo).</p>
            <ul class="expected-list">
                <?php foreach ($documentExpectedDocs as $doc): ?>
                    <li><?= htmlspecialchars($doc) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="document-section">
            <div class="section-header">
                <div>
                    <h5>Carga libre de documentos</h5>
                    <p class="section-muted">PDF, Word, Excel o imágenes. Se almacenan en esta subfase.</p>
                </div>
                <?php if ($documentCanManage): ?>
                    <form class="upload-form" method="POST" action="<?= $documentBasePath ?>/projects/<?= $documentProjectId ?>/nodes/<?= (int) ($documentNode['id'] ?? 0) ?>/files" enctype="multipart/form-data">
                        <input type="file" name="node_files[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.bmp,.tiff">
                        <button type="submit" class="action-btn primary">Subir archivos</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="upload-preview" data-upload-preview hidden>
                <strong>Archivos listos para cargar:</strong>
                <ul></ul>
            </div>
        </section>
    </div>

    <section class="document-files">
        <div class="section-header">
            <div>
                <h5>Listado de archivos</h5>
                <p class="section-muted">Administra tags, versión y flujo de revisión.</p>
            </div>
            <div class="document-alert" data-document-alert hidden>
                <strong>⚠️ Documentos clave pendientes</strong>
                <span data-document-alert-detail></span>
            </div>
        </div>

        <?php if (!empty($documentFiles)): ?>
            <div class="document-file-table">
                <div class="document-file-row document-file-head">
                    <span>Archivo</span>
                    <span>Tags</span>
                    <span>Versión</span>
                    <span>Estado</span>
                    <span>Acciones</span>
                </div>
                <?php foreach ($documentFiles as $file): ?>
                    <div class="document-file-row" data-file-row data-file-id="<?= (int) ($file['id'] ?? 0) ?>"
                         data-reviewer-id="<?= htmlspecialchars((string) ($file['reviewer_id'] ?? '')) ?>"
                         data-validator-id="<?= htmlspecialchars((string) ($file['validator_id'] ?? '')) ?>"
                         data-approver-id="<?= htmlspecialchars((string) ($file['approver_id'] ?? '')) ?>"
                         data-document-status="<?= htmlspecialchars((string) ($file['document_status'] ?? 'pendiente_revision')) ?>"
                         data-reviewed-by="<?= htmlspecialchars((string) ($file['reviewed_by'] ?? '')) ?>"
                         data-reviewed-at="<?= htmlspecialchars((string) ($file['reviewed_at'] ?? '')) ?>"
                         data-validated-by="<?= htmlspecialchars((string) ($file['validated_by'] ?? '')) ?>"
                         data-validated-at="<?= htmlspecialchars((string) ($file['validated_at'] ?? '')) ?>"
                         data-approved-by="<?= htmlspecialchars((string) ($file['approved_by'] ?? '')) ?>"
                         data-approved-at="<?= htmlspecialchars((string) ($file['approved_at'] ?? '')) ?>">
                        <div>
                            <strong><?= htmlspecialchars($file['file_name'] ?? $file['title'] ?? '') ?></strong>
                            <small class="section-muted">Subido: <?= htmlspecialchars((string) ($file['created_at'] ?? '')) ?></small>
                            <div class="file-trace" data-file-trace>Sin trazabilidad registrada.</div>
                        </div>
                        <div class="tag-editor" data-tag-editor>
                            <div class="tag-pills" data-tag-pills>
                                <span class="tag-pill">Documento libre</span>
                            </div>
                            <div class="tag-controls">
                                <select multiple data-tag-select>
                                    <?php foreach ($documentTagOptions as $tag): ?>
                                        <option value="<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($tag) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" placeholder="Otro (texto libre)" data-tag-custom>
                            </div>
                        </div>
                        <div>
                            <input type="text" class="version-input" placeholder="v1, v2, final" data-version-input>
                        </div>
                        <div>
                            <span class="status-pill status-pending" data-status-label>Pendiente</span>
                            <?php if ($documentCanManage): ?>
                                <button type="button" class="action-btn small" data-send-review>Enviar a revisión</button>
                            <?php endif; ?>
                            <div class="review-actions" data-review-actions hidden>
                                <button type="button" class="action-btn small" data-action="reviewed">Aprobar revisión</button>
                                <button type="button" class="action-btn small" data-action="validated">Validar documento</button>
                                <button type="button" class="action-btn small primary" data-action="approved">Aprobar documento</button>
                                <button type="button" class="action-btn small danger" data-action="rejected">Rechazar</button>
                            </div>
                        </div>
                        <div class="file-actions">
                            <a class="action-btn small" href="<?= $documentBasePath ?>/projects/<?= $documentProjectId ?>/nodes/<?= (int) ($file['id'] ?? 0) ?>/download">Ver</a>
                            <?php if ($documentCanManage): ?>
                                <form method="POST" action="<?= $documentBasePath ?>/projects/<?= $documentProjectId ?>/nodes/<?= (int) ($file['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar archivo?');">
                                    <button class="action-btn danger small" type="submit">Eliminar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($documentCanManage): ?>
                                <button type="button" class="action-btn small" data-toggle-flow>Asignar flujo</button>
                            <?php endif; ?>
                        </div>
                        <div class="flow-panel" data-flow-panel hidden>
                            <div class="flow-grid">
                                <label>
                                    <span>Revisor</span>
                                    <select data-role-select="reviewer">
                                        <option value="">Seleccionar</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Validador</span>
                                    <select data-role-select="validator">
                                        <option value="">Seleccionar</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Aprobador</span>
                                    <select data-role-select="approver">
                                        <option value="">Seleccionar</option>
                                    </select>
                                </label>
                            </div>
                            <div class="flow-actions">
                                <button type="button" class="action-btn small primary" data-save-flow>Guardar flujo</button>
                            </div>
                            <small class="section-muted">Asigna responsables para habilitar las acciones por rol.</small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="section-muted">Aún no hay archivos cargados en esta subfase.</p>
        <?php endif; ?>
    </section>
</section>

<style>
    .document-flow { display:flex; flex-direction:column; gap:16px; }
    .document-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .document-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px; }
    .document-section { border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:8px; }
    .document-section h5 { margin:0; }
    .section-muted { color: var(--muted); margin:0; font-size:13px; }
    .expected-list { margin:0; padding-left:18px; color: var(--text-strong); }
    .section-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .upload-form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .upload-preview { margin-top:6px; background:#fff; border:1px dashed var(--border); padding:8px; border-radius:10px; font-size:13px; }
    .document-files { display:flex; flex-direction:column; gap:12px; }
    .document-file-table { display:grid; gap:8px; }
    .document-file-row { display:grid; grid-template-columns: minmax(180px, 1.4fr) minmax(160px, 1.2fr) minmax(120px, 0.6fr) minmax(160px, 0.8fr) minmax(140px, 0.8fr); gap:10px; padding:10px; border:1px solid var(--border); border-radius:12px; background:#fff; align-items:start; }
    .document-file-head { background:#f1f5f9; font-weight:700; }
    .document-file-head span { font-size:12px; text-transform:uppercase; color: var(--muted); }
    .tag-editor { display:flex; flex-direction:column; gap:6px; }
    .tag-pills { display:flex; flex-wrap:wrap; gap:6px; }
    .tag-pill { background:#e0f2fe; color:#075985; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:700; }
    .tag-controls { display:flex; flex-direction:column; gap:6px; }
    .tag-controls select, .tag-controls input, .version-input { width:100%; border:1px solid var(--border); border-radius:8px; padding:6px 8px; }
    .version-input { font-size:13px; }
    .status-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; margin-bottom:6px; }
    .status-pending { background:#fef9c3; color:#854d0e; }
    .status-review { background:#e0f2fe; color:#075985; }
    .status-validated { background:#dcfce7; color:#166534; }
    .status-approved { background:#ede9fe; color:#5b21b6; }
    .file-actions { display:flex; flex-wrap:wrap; gap:6px; }
    .flow-panel { margin-top:6px; background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:8px; grid-column: 1 / -1; }
    .flow-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:8px; margin-bottom:6px; }
    .flow-grid label { display:flex; flex-direction:column; gap:4px; font-size:13px; color: var(--text-strong); }
    .flow-grid select { border:1px solid var(--border); border-radius:8px; padding:6px 8px; }
    .review-actions { display:flex; flex-direction:column; gap:6px; margin-top:6px; }
    .review-actions .danger { background:#fee2e2; color:#991b1b; border-color:#fecdd3; }
    .document-alert { background:#fef3c7; color:#92400e; padding:8px 10px; border-radius:10px; font-size:13px; display:flex; flex-direction:column; gap:4px; min-width:200px; }
    .file-trace { margin-top:6px; font-size:12px; color: var(--muted); }
    .flow-actions { display:flex; justify-content:flex-end; margin-bottom:6px; }
    @media (max-width: 900px) {
        .document-file-row { grid-template-columns: 1fr; }
        .document-file-head { display:none; }
    }
</style>

<script>
    (() => {
        const root = document.querySelector('[data-document-flow="<?= htmlspecialchars($documentFlowId) ?>"]');
        if (!root) return;

        const currentUserId = <?= (int) ($documentCurrentUser['id'] ?? 0) ?>;
        const currentUserName = <?= json_encode((string) ($documentCurrentUser['name'] ?? 'Usuario')) ?>;
        const keyTags = <?= json_encode(array_values($documentKeyTags)) ?>;
        const basePath = <?= json_encode((string) $documentBasePath) ?>;
        const roleCache = new Map();

        const statusConfig = {
            pendiente_revision: { label: 'Pendiente de revisión', className: 'status-pending' },
            en_revision: { label: 'En revisión', className: 'status-review' },
            validacion_pendiente: { label: 'Validación pendiente', className: 'status-review' },
            aprobacion_pendiente: { label: 'Aprobación pendiente', className: 'status-review' },
            aprobado: { label: 'Aprobado', className: 'status-approved' },
            rechazado: { label: 'Rechazado', className: 'status-pending' }
        };

        const updateAlert = () => {
            const alertBox = root.querySelector('[data-document-alert]');
            const alertDetail = root.querySelector('[data-document-alert-detail]');
            if (!alertBox || !alertDetail || keyTags.length === 0) return;

            const summary = {};
            keyTags.forEach(tag => {
                summary[tag] = { approved: false };
            });

            root.querySelectorAll('[data-file-row]').forEach(row => {
                const tags = row.dataset.tags ? row.dataset.tags.split('|').filter(Boolean) : [];
                const status = row.dataset.documentStatus || 'pendiente_revision';
                tags.forEach(tag => {
                    if (summary[tag]) {
                        if (status === 'aprobado') {
                            summary[tag].approved = true;
                        }
                    }
                });
            });

            const pending = keyTags.filter(tag => !summary[tag].approved);
            if (pending.length === 0) {
                alertBox.hidden = true;
                return;
            }

            alertBox.hidden = false;
            alertDetail.textContent = `Pendientes de aprobación: ${pending.join(', ')}.`;
        };

        const updateTagsDisplay = (row, tags) => {
            const pills = row.querySelector('[data-tag-pills]');
            if (!pills) return;
            pills.innerHTML = '';
            const finalTags = tags.length ? tags : ['Documento libre'];
            finalTags.forEach(tag => {
                const pill = document.createElement('span');
                pill.className = 'tag-pill';
                pill.textContent = tag;
                pills.appendChild(pill);
            });
            row.dataset.tags = finalTags.join('|');
            updateAlert();
        };

        const updateStatus = (row, statusKey, traceNote) => {
            const config = statusConfig[statusKey] || statusConfig.pendiente_revision;
            const label = row.querySelector('[data-status-label]');
            if (label) {
                label.textContent = config.label;
                label.className = `status-pill ${config.className}`;
            }
            row.dataset.documentStatus = statusKey;
            const trace = row.querySelector('[data-file-trace]');
            if (trace && traceNote) {
                const now = new Date();
                trace.textContent = `${traceNote} · ${currentUserName} · ${now.toLocaleString()}`;
            }
            updateAlert();
        };

        const updateTraceFromData = (row) => {
            const trace = row.querySelector('[data-file-trace]');
            if (!trace) return;
            const status = row.dataset.documentStatus || 'pendiente_revision';
            const traceMap = [
                { status: 'aprobado', by: row.dataset.approvedBy, at: row.dataset.approvedAt, label: 'Aprobado' },
                { status: 'aprobacion_pendiente', by: row.dataset.validatedBy, at: row.dataset.validatedAt, label: 'Validado' },
                { status: 'validacion_pendiente', by: row.dataset.reviewedBy, at: row.dataset.reviewedAt, label: 'Revisado' },
                { status: 'en_revision', by: row.dataset.reviewedBy, at: row.dataset.reviewedAt, label: 'En revisión' },
                { status: 'rechazado', by: row.dataset.approvedBy || row.dataset.validatedBy || row.dataset.reviewedBy, at: row.dataset.approvedAt || row.dataset.validatedAt || row.dataset.reviewedAt, label: 'Rechazado' },
            ];

            const entry = traceMap.find(item => item.status === status && item.by && item.at);
            if (!entry) {
                return;
            }

            trace.textContent = `${entry.label} · Usuario #${entry.by} · ${entry.at}`;
        };

        const updateRoleActions = (row) => {
            const actions = row.querySelector('[data-review-actions]');
            if (!actions) return;
            const reviewer = row.dataset.reviewerId;
            const validator = row.dataset.validatorId;
            const approver = row.dataset.approverId;
            const status = row.dataset.documentStatus || 'pendiente_revision';
            const shouldShow = (status === 'en_revision' && Number(reviewer) === currentUserId)
                || (status === 'validacion_pendiente' && Number(validator) === currentUserId)
                || (status === 'aprobacion_pendiente' && Number(approver) === currentUserId);
            actions.hidden = !shouldShow;
        };

        const toggleSendReview = (row) => {
            const button = row.querySelector('[data-send-review]');
            if (!button) return;
            const status = row.dataset.documentStatus || 'pendiente_revision';
            button.hidden = status !== 'pendiente_revision';
        };

        const setRoleSelectLoading = (select) => {
            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Cargando...';
            placeholder.disabled = true;
            placeholder.selected = true;
            select.appendChild(placeholder);
        };

        const setRoleSelectEmpty = (select) => {
            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Seleccionar';
            select.appendChild(placeholder);

            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = 'No hay usuarios disponibles para este rol';
            emptyOption.disabled = true;
            select.appendChild(emptyOption);
        };

        const setRoleSelectOptions = (select, users) => {
            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Seleccionar';
            select.appendChild(placeholder);

            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                const label = user.name ? `${user.name} (#${user.id})` : `Usuario (#${user.id})`;
                option.textContent = label;
                select.appendChild(option);
            });
        };

        const applyRoleSelection = (select) => {
            const row = select.closest('[data-file-row]');
            if (!row) return;
            const role = select.dataset.roleSelect;
            const key = `${role}Id`;
            const value = row.dataset[key];
            if (value) {
                select.value = value;
            }
        };

        const loadRoleOptions = async (role, select) => {
            if (!role) return;
            if (roleCache.has(role)) {
                const cached = roleCache.get(role);
                if (cached.length === 0) {
                    setRoleSelectEmpty(select);
                } else {
                    setRoleSelectOptions(select, cached);
                    applyRoleSelection(select);
                }
                return;
            }

            setRoleSelectLoading(select);
            try {
                const response = await fetch(`${basePath}/users?role=${encodeURIComponent(role)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    roleCache.set(role, []);
                    setRoleSelectEmpty(select);
                    return;
                }
                const payload = await response.json();
                const users = Array.isArray(payload.users) ? payload.users : [];
                roleCache.set(role, users);
                if (users.length === 0) {
                    setRoleSelectEmpty(select);
                } else {
                    setRoleSelectOptions(select, users);
                    applyRoleSelection(select);
                }
            } catch (error) {
                roleCache.set(role, []);
                setRoleSelectEmpty(select);
            }
        };

        root.querySelectorAll('[data-file-row]').forEach(row => {
            updateTagsDisplay(row, []);
            const status = row.dataset.documentStatus || 'pendiente_revision';
            updateStatus(row, status);
            updateTraceFromData(row);
            updateRoleActions(row);
            toggleSendReview(row);
        });

        root.querySelectorAll('select[data-role-select]').forEach(select => {
            const role = select.dataset.roleSelect;
            loadRoleOptions(role, select);
        });

        root.addEventListener('change', (event) => {
            const select = event.target.closest('[data-tag-select]');
            if (select) {
                const row = select.closest('[data-file-row]');
                const customInput = row.querySelector('[data-tag-custom]');
                const tags = Array.from(select.selectedOptions).map(option => option.value);
                if (customInput && customInput.value.trim()) {
                    tags.push(customInput.value.trim());
                }
                updateTagsDisplay(row, tags);
            }

            const customInput = event.target.closest('[data-tag-custom]');
            if (customInput) {
                const row = customInput.closest('[data-file-row]');
                const select = row.querySelector('[data-tag-select]');
                const tags = Array.from(select.selectedOptions).map(option => option.value);
                if (customInput.value.trim()) {
                    tags.push(customInput.value.trim());
                }
                updateTagsDisplay(row, tags);
            }

            const roleSelect = event.target.closest('[data-role-select]');
            if (roleSelect) {
                const row = roleSelect.closest('[data-file-row]');
                const role = roleSelect.dataset.roleSelect;
                row.dataset[`${role}Id`] = roleSelect.value;
                updateRoleActions(row);
            }
        });

        root.addEventListener('click', (event) => {
            const toggleFlow = event.target.closest('[data-toggle-flow]');
            if (toggleFlow) {
                const row = toggleFlow.closest('[data-file-row]');
                const panel = row.querySelector('[data-flow-panel]');
                if (panel) {
                    panel.hidden = !panel.hidden;
                }
            }

            const sendReview = event.target.closest('[data-send-review]');
            if (sendReview) {
                const row = sendReview.closest('[data-file-row]');
                const fileId = row.dataset.fileId;
                sendReview.disabled = true;
                fetch(`${basePath}/projects/${documentProjectId}/nodes/${fileId}/document-status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ action: 'send_review' }),
                })
                    .then(response => response.json())
                    .then(payload => {
                        if (payload.status !== 'ok') {
                            throw new Error(payload.message || 'No se pudo enviar a revisión.');
                        }
                        updateFlowRow(row, payload.data, 'Enviado a revisión');
                    })
                    .catch(error => {
                        alert(error.message);
                    })
                    .finally(() => {
                        sendReview.disabled = false;
                    });
            }

            const actionBtn = event.target.closest('[data-action]');
            if (actionBtn) {
                const row = actionBtn.closest('[data-file-row]');
                const action = actionBtn.dataset.action;
                const fileId = row.dataset.fileId;
                actionBtn.disabled = true;
                fetch(`${basePath}/projects/${documentProjectId}/nodes/${fileId}/document-status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ action }),
                })
                    .then(response => response.json())
                    .then(payload => {
                        if (payload.status !== 'ok') {
                            throw new Error(payload.message || 'No se pudo actualizar el estado.');
                        }
                        updateFlowRow(row, payload.data, 'Estado actualizado');
                    })
                    .catch(error => {
                        alert(error.message);
                    })
                    .finally(() => {
                        actionBtn.disabled = false;
                    });
            }

            const saveFlow = event.target.closest('[data-save-flow]');
            if (saveFlow) {
                const row = saveFlow.closest('[data-file-row]');
                const fileId = row.dataset.fileId;
                const reviewerId = row.dataset.reviewerId || '';
                const validatorId = row.dataset.validatorId || '';
                const approverId = row.dataset.approverId || '';
                saveFlow.disabled = true;
                fetch(`${basePath}/projects/${documentProjectId}/nodes/${fileId}/document-flow`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({
                        reviewer_id: reviewerId,
                        validator_id: validatorId,
                        approver_id: approverId,
                    }),
                })
                    .then(response => response.json())
                    .then(payload => {
                        if (payload.status !== 'ok') {
                            throw new Error(payload.message || 'No se pudo guardar el flujo.');
                        }
                        updateFlowRow(row, payload.data, 'Flujo guardado');
                    })
                    .catch(error => {
                        alert(error.message);
                    })
                    .finally(() => {
                        saveFlow.disabled = false;
                    });
            }
        });

        const updateFlowRow = (row, data, traceNote) => {
            row.dataset.reviewerId = data.reviewer_id ?? '';
            row.dataset.validatorId = data.validator_id ?? '';
            row.dataset.approverId = data.approver_id ?? '';
            row.dataset.documentStatus = data.document_status ?? 'pendiente_revision';
            row.dataset.reviewedBy = data.reviewed_by ?? '';
            row.dataset.reviewedAt = data.reviewed_at ?? '';
            row.dataset.validatedBy = data.validated_by ?? '';
            row.dataset.validatedAt = data.validated_at ?? '';
            row.dataset.approvedBy = data.approved_by ?? '';
            row.dataset.approvedAt = data.approved_at ?? '';
            updateStatus(row, row.dataset.documentStatus, traceNote);
            if (!traceNote) {
                updateTraceFromData(row);
            }
            updateRoleActions(row);
            toggleSendReview(row);
        };

        const uploadInput = root.querySelector('.upload-form input[type="file"]');
        const preview = root.querySelector('[data-upload-preview]');
        if (uploadInput && preview) {
            uploadInput.addEventListener('change', () => {
                const list = preview.querySelector('ul');
                list.innerHTML = '';
                Array.from(uploadInput.files || []).forEach(file => {
                    const item = document.createElement('li');
                    item.textContent = `${file.name} (${Math.round(file.size / 1024)} KB)`;
                    list.appendChild(item);
                });
                preview.hidden = list.children.length === 0;
            });
        }

        updateAlert();
    })();
</script>
