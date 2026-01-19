<?php
$documentFlowId = $documentFlowId ?? ('document-flow-' . bin2hex(random_bytes(4)));
$documentNode = is_array($documentNode ?? null) ? $documentNode : [];
$documentExpectedDocs = is_array($documentExpectedDocs ?? null) ? $documentExpectedDocs : [];
$documentTagOptions = is_array($documentTagOptions ?? null) ? $documentTagOptions : [];
$documentKeyTags = is_array($documentKeyTags ?? null) ? $documentKeyTags : $documentExpectedDocs;
$documentDefaultTags = is_array($documentDefaultTags ?? null) ? $documentDefaultTags : [];
$documentCanManage = !empty($documentCanManage);
$documentMode = $documentMode ?? null;
$documentProjectId = (int) ($documentProjectId ?? 0);
$documentBasePath = $documentBasePath ?? '/project/public';
$documentCurrentUser = is_array($documentCurrentUser ?? null) ? $documentCurrentUser : [];
$documentContextLabel = $documentContextLabel ?? 'SUBFASE';
$documentContextDescription = $documentContextDescription ?? 'Documentos guiados por metodología, fase y subfase con trazabilidad ISO 9001.';
$documentExpectedTitle = $documentExpectedTitle ?? 'Documentos sugeridos por subfase';
$documentExpectedDescription = $documentExpectedDescription ?? 'Guía basada en metodología, fase y subfase. Puedes adjuntar documentos adicionales.';
$documentFiles = is_array($documentNode['files'] ?? null) ? $documentNode['files'] : [];
if ($documentMode === '03-CONTROLES') {
    $documentFiles = array_values(array_filter($documentFiles, static function (array $file): bool {
        $status = (string) ($file['document_status'] ?? '');
        $hasFlow = !empty($file['reviewer_id']) || !empty($file['validator_id']) || !empty($file['approver_id']);
        return $hasFlow || in_array($status, ['en_revision', 'revisado', 'en_validacion', 'validado', 'en_aprobacion', 'aprobado', 'rechazado'], true);
    }));
}
$documentNodeName = (string) ($documentNode['name'] ?? $documentNode['title'] ?? $documentNode['code'] ?? 'Subfase');
$documentNodeCode = (string) ($documentNode['code'] ?? '');
$fileIconMap = [
    'pdf' => '📕',
    'doc' => '📘',
    'docx' => '📘',
    'xls' => '📊',
    'xlsx' => '📊',
    'ppt' => '📽️',
    'pptx' => '📽️',
    'png' => '🖼️',
    'jpg' => '🖼️',
    'jpeg' => '🖼️',
    'gif' => '🖼️',
];
$resolveFileIcon = static function (string $fileName, string $docType) use ($fileIconMap): string {
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($extension && isset($fileIconMap[$extension])) {
        return $fileIconMap[$extension];
    }
    $normalized = strtolower($docType);
    if (str_contains($normalized, 'plan')) {
        return '🗂️';
    }
    if (str_contains($normalized, 'acta')) {
        return '📝';
    }
    return '📄';
};
$normalizeDoc = static function (string $value): string {
    return strtolower(trim($value));
};
$normalizeExpectedDoc = static function (mixed $doc) use ($normalizeDoc): ?array {
    if (is_string($doc)) {
        return [
            'name' => $doc,
            'document_type' => $doc,
            'requires_approval' => true,
        ];
    }
    if (!is_array($doc)) {
        return null;
    }
    $name = trim((string) ($doc['name'] ?? $doc['label'] ?? $doc['document_type'] ?? ''));
    if ($name === '') {
        return null;
    }
    return [
        'name' => $name,
        'document_type' => trim((string) ($doc['document_type'] ?? $name)) ?: $name,
        'requires_approval' => array_key_exists('requires_approval', $doc) ? (bool) $doc['requires_approval'] : true,
    ];
};
$documentExpectedItems = array_values(array_filter(array_map($normalizeExpectedDoc, $documentExpectedDocs)));
$documentKeyTags = array_values(array_map(
    static fn (array $doc): string => $doc['document_type'] ?? $doc['name'],
    array_filter($documentExpectedItems, static fn (array $doc): bool => (bool) ($doc['requires_approval'] ?? false))
));
$documentTypeOptions = array_values(array_unique(array_merge(
    array_map(static fn (array $doc): string => $doc['document_type'] ?? $doc['name'], $documentExpectedItems),
    $documentTagOptions
)));
$normalizedDefaultTags = array_values(array_unique(array_filter(array_map(
    static fn (string $tag): string => trim($tag),
    $documentDefaultTags
))));
$expectedSummary = [];
foreach ($documentExpectedItems as $doc) {
    $normalized = $normalizeDoc((string) ($doc['document_type'] ?? $doc['name']));
    $matches = array_filter($documentFiles, static function (array $file) use ($normalized, $normalizeDoc): bool {
        $tags = array_map($normalizeDoc, $file['tags'] ?? []);
        $type = $normalizeDoc((string) ($file['document_type'] ?? ''));
        return $type === $normalized || in_array($normalized, $tags, true);
    });
    $loaded = !empty($matches);
    $approved = (bool) array_filter($matches, static fn (array $file): bool => ($file['document_status'] ?? '') === 'aprobado');
    $expectedSummary[] = [
        'name' => $doc['name'],
        'requires_approval' => (bool) ($doc['requires_approval'] ?? false),
        'loaded' => $loaded,
        'approved' => $approved,
    ];
}

?>

<section class="document-flow" data-document-flow="<?= htmlspecialchars($documentFlowId) ?>">
    <header class="document-header">
        <div>
            <p class="eyebrow" style="margin:0; color: var(--muted);"><?= htmlspecialchars($documentContextLabel) ?> · <?= htmlspecialchars($documentNodeCode) ?></p>
            <h4 style="margin:4px 0 0;">Gestión documental de <?= htmlspecialchars($documentNodeName) ?></h4>
            <small style="color: var(--muted);"><?= htmlspecialchars($documentContextDescription) ?></small>
        </div>
    </header>

    <div class="document-toast" data-document-toast hidden>
        <span data-document-toast-message></span>
    </div>

    <div class="document-grid">
        <section class="document-section">
            <h5><?= htmlspecialchars($documentExpectedTitle) ?></h5>
            <p class="section-muted"><?= htmlspecialchars($documentExpectedDescription) ?></p>
            <?php if (empty($expectedSummary)): ?>
                <p class="section-muted">No hay documentos sugeridos configurados para este contexto.</p>
            <?php else: ?>
                <ul class="expected-list">
                    <?php foreach ($expectedSummary as $summary): ?>
                        <li>
                            <span><?= htmlspecialchars($summary['name']) ?></span>
                            <?php if ($summary['loaded']): ?>
                                <span class="expected-pill <?= $summary['approved'] ? 'expected-approved' : 'expected-loaded' ?>">
                                    <?= $summary['approved'] ? 'Aprobado' : 'Cargado' ?>
                                </span>
                            <?php else: ?>
                                <span class="expected-pill expected-pending">Pendiente</span>
                            <?php endif; ?>
                            <?php if (!empty($summary['requires_approval'])): ?>
                                <span class="expected-pill expected-review">Requiere aprobación</span>
                            <?php else: ?>
                                <span class="expected-pill expected-optional">Opcional</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="document-section">
            <?php if ($documentCanManage): ?>
                <form class="upload-form" method="POST" action="<?= $documentBasePath ?>/projects/<?= $documentProjectId ?>/nodes/<?= (int) ($documentNode['id'] ?? 0) ?>/files" enctype="multipart/form-data" data-upload-form>
                    <div class="section-header">
                        <div>
                            <h5>Carga libre de documentos</h5>
                            <p class="section-muted">Solo se guardan en esta subfase. Campos obligatorios marcados con *.</p>
                        </div>
                        <button type="button" class="action-btn primary" id="btnUploadDocument" data-open-upload>Subir documento</button>
                    </div>
                    <div class="upload-preview" data-upload-preview hidden>
                        <strong>Archivos listos para cargar:</strong>
                        <ul></ul>
                    </div>
                    <input type="hidden" name="project_id" value="<?= $documentProjectId ?>">
                    <input type="hidden" name="node_id" value="<?= (int) ($documentNode['id'] ?? 0) ?>">
                    <input type="hidden" name="subfase_id" value="<?= (int) ($documentNode['id'] ?? 0) ?>">

                    <div class="modal" data-upload-modal hidden>
                        <div class="modal-backdrop" data-close-upload></div>
                        <div class="modal-panel">
                            <header>
                                <div>
                                    <h4>Subir documento</h4>
                                    <p class="section-muted">Completa los metadatos antes de guardar.</p>
                                </div>
                                <button type="button" class="action-btn small" data-close-upload>✕</button>
                            </header>
                            <div class="form-validation" data-upload-validation hidden>Revisa los campos obligatorios.</div>
                            <label class="field">
                                <span>Archivo *</span>
                                <input type="file" name="document" required accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.bmp,.tiff">
                            </label>
                            <div class="field-grid">
                                <label class="field">
                                    <span>Tipo documental *</span>
                                    <select data-document-type-select>
                                        <option value="">Seleccionar</option>
                                        <?php foreach ($documentTypeOptions as $type): ?>
                                            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" placeholder="Otro tipo" data-document-type-custom>
                                </label>
                                <label class="field">
                                    <span>Versión *</span>
                                    <input type="text" name="document_version" placeholder="v1, v2, final" required>
                                </label>
                            </div>
                            <label class="field">
                                <span>Tags *</span>
                                <select multiple data-upload-tag-select>
                                    <?php foreach ($documentTagOptions as $tag): ?>
                                        <option value="<?= htmlspecialchars($tag) ?>" <?= in_array($tag, $normalizedDefaultTags, true) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tag) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" placeholder="Otros tags (separados por coma)" data-upload-tag-custom>
                            </label>
                            <label class="field">
                                <span>Descripción corta *</span>
                                <textarea name="document_description" rows="3" placeholder="Describe el contenido del documento" required></textarea>
                            </label>
                            <div class="field">
                                <label class="switch">
                                    <input type="checkbox" name="start_flow" value="1" data-start-flow>
                                    <span class="slider"></span>
                                    <span>Iniciar flujo de aprobación al guardar</span>
                                </label>
                            </div>
                            <div class="flow-grid" data-upload-flow>
                                <label>
                                    <span>Revisor</span>
                                    <select name="reviewer_id" data-role-select="reviewer">
                                        <option value="">Seleccionar</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Validador</span>
                                    <select name="validator_id" data-role-select="validator">
                                        <option value="">Seleccionar</option>
                                    </select>
                                </label>
                                <label>
                                    <span>Aprobador</span>
                                    <select name="approver_id" data-role-select="approver">
                                        <option value="">Seleccionar</option>
                                    </select>
                                </label>
                            </div>
                            <input type="hidden" name="document_type" value="" data-document-type-hidden>
                            <input type="hidden" name="document_tags" value="" data-document-tags-hidden>
                            <div class="modal-actions">
                                <button type="button" class="action-btn" data-close-upload>Cancelar</button>
                                <button type="submit" class="action-btn primary" data-upload-submit>
                                    <span class="button-label">Guardar documento</span>
                                    <span class="button-spinner" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="section-header">
                    <div>
                        <h5>Carga libre de documentos</h5>
                        <p class="section-muted">Solo se guardan en esta subfase. Campos obligatorios marcados con *.</p>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="document-files">
        <div class="section-header">
            <div>
                <h5>Listado de archivos</h5>
                <p class="section-muted">Administra tags, versión y flujo de revisión.</p>
            </div>
            <div class="document-search">
                <input type="search" placeholder="Buscar por nombre, tag, tipo, estado o versión" data-document-search>
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
                    <span>Tipo / Descripción</span>
                    <span>Tags</span>
                    <span>Versión</span>
                    <span>Flujo & estado</span>
                    <span>Acciones</span>
                </div>
                <?php foreach ($documentFiles as $file): ?>
                    <?php
                    $fileName = (string) ($file['file_name'] ?? $file['title'] ?? '');
                    $docType = (string) ($file['document_type'] ?? '');
                    $fileIcon = $resolveFileIcon($fileName, $docType);
                    ?>
                    <div class="document-file-row" data-file-row data-file-id="<?= (int) ($file['id'] ?? 0) ?>"
                         data-reviewer-id="<?= htmlspecialchars((string) ($file['reviewer_id'] ?? '')) ?>"
                         data-validator-id="<?= htmlspecialchars((string) ($file['validator_id'] ?? '')) ?>"
                         data-approver-id="<?= htmlspecialchars((string) ($file['approver_id'] ?? '')) ?>"
                         data-document-status="<?= htmlspecialchars((string) ($file['document_status'] ?? 'borrador')) ?>"
                         data-tags="<?= htmlspecialchars(implode('|', array_map('strval', $file['tags'] ?? []))) ?>"
                         data-document-version="<?= htmlspecialchars((string) ($file['version'] ?? '')) ?>"
                         data-document-type="<?= htmlspecialchars((string) ($file['document_type'] ?? '')) ?>"
                         data-description="<?= htmlspecialchars((string) ($file['description'] ?? '')) ?>"
                         data-file-name="<?= htmlspecialchars((string) ($file['file_name'] ?? $file['title'] ?? '')) ?>"
                         data-reviewed-by="<?= htmlspecialchars((string) ($file['reviewed_by'] ?? '')) ?>"
                         data-reviewed-at="<?= htmlspecialchars((string) ($file['reviewed_at'] ?? '')) ?>"
                         data-validated-by="<?= htmlspecialchars((string) ($file['validated_by'] ?? '')) ?>"
                         data-validated-at="<?= htmlspecialchars((string) ($file['validated_at'] ?? '')) ?>"
                         data-approved-by="<?= htmlspecialchars((string) ($file['approved_by'] ?? '')) ?>"
                         data-approved-at="<?= htmlspecialchars((string) ($file['approved_at'] ?? '')) ?>">
                        <div>
                            <strong><span class="file-type-icon"><?= htmlspecialchars($fileIcon) ?></span><?= htmlspecialchars($fileName) ?></strong>
                            <small class="section-muted">Subido: <?= htmlspecialchars((string) ($file['created_at'] ?? '')) ?></small>
                            <div class="file-trace" data-file-trace>Sin trazabilidad registrada.</div>
                        </div>
                        <div class="file-meta">
                            <div><strong><?= htmlspecialchars($docType) ?></strong></div>
                            <small class="section-muted"><?= htmlspecialchars((string) ($file['description'] ?? 'Sin descripción')) ?></small>
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
                            <input type="text" class="version-input" placeholder="v1, v2, final" data-version-input value="<?= htmlspecialchars((string) ($file['version'] ?? '')) ?>">
                        </div>
                        <div>
                            <span class="status-pill status-pending" data-status-label>Borrador</span>
                            <?php if ($documentCanManage): ?>
                                <button type="button" class="action-btn small" data-send-review>🔍 Enviar a revisión</button>
                            <?php endif; ?>
                            <small class="flow-summary" data-flow-summary>
                                Revisor: <?= $file['reviewer_id'] ? 'Usuario #' . (int) $file['reviewer_id'] : 'No asignado' ?><br>
                                Validador: <?= $file['validator_id'] ? 'Usuario #' . (int) $file['validator_id'] : 'No asignado' ?><br>
                                Aprobador: <?= $file['approver_id'] ? 'Usuario #' . (int) $file['approver_id'] : 'No asignado' ?>
                            </small>
                            <button type="button" class="action-btn small" data-toggle-history>Historial</button>
                        </div>
                        <div class="file-actions">
                            <a class="action-btn small" href="<?= $documentBasePath ?>/projects/<?= $documentProjectId ?>/nodes/<?= (int) ($file['id'] ?? 0) ?>/download">👁️ Ver</a>
                            <?php if ($documentCanManage): ?>
                                <form method="POST" action="<?= $documentBasePath ?>/projects/<?= $documentProjectId ?>/nodes/<?= (int) ($file['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar archivo?');">
                                    <button class="action-btn danger small" type="submit">🗑️ Eliminar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($documentCanManage): ?>
                                <button type="button" class="action-btn small" data-toggle-flow>👤 Asignar flujo</button>
                            <?php endif; ?>
                        </div>
                        <div class="history-panel" data-history-panel hidden>
                            <strong>Historial</strong>
                            <ul class="history-list" data-history-list>
                                <li class="section-muted">Cargando historial...</li>
                            </ul>
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
    .document-toast { border-radius:10px; padding:10px 12px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; }
    .document-toast.success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .document-toast.warning { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
    .document-toast.error { background:#fee2e2; color:#991b1b; border:1px solid #fecdd3; }
    .document-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px; }
    .document-section { border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:8px; }
    .document-section h5 { margin:0; }
    .section-muted { color: var(--muted); margin:0; font-size:13px; }
    .expected-list { margin:0; padding-left:0; list-style:none; color: var(--text-strong); display:flex; flex-direction:column; gap:6px; }
    .expected-list li { display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; background:#fff; padding:6px 8px; border-radius:8px; border:1px solid var(--border); }
    .expected-pill { font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; background:#e5e7eb; color:#374151; }
    .expected-loaded { background:#dbeafe; color:#1d4ed8; }
    .expected-approved { background:#dcfce7; color:#166534; }
    .expected-pending { background:#fef3c7; color:#92400e; }
    .expected-review { background:#ede9fe; color:#5b21b6; }
    .expected-optional { background:#e2e8f0; color:#475569; }
    .section-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .document-search input { border:1px solid var(--border); border-radius:8px; padding:6px 10px; min-width:220px; }
    .upload-form { display:flex; flex-direction:column; gap:8px; }
    .upload-preview { margin-top:6px; background:#fff; border:1px dashed var(--border); padding:8px; border-radius:10px; font-size:13px; }
    .document-files { display:flex; flex-direction:column; gap:12px; }
    .document-file-table { display:grid; gap:8px; }
    .document-file-row { display:grid; grid-template-columns: minmax(180px, 1.4fr) minmax(160px, 1fr) minmax(160px, 1.2fr) minmax(120px, 0.6fr) minmax(190px, 1fr) minmax(140px, 0.8fr); gap:10px; padding:10px; border:1px solid var(--border); border-radius:12px; background:#fff; align-items:start; }
    .document-file-head { background:#f1f5f9; font-weight:700; }
    .document-file-head span { font-size:12px; text-transform:uppercase; color: var(--muted); }
    .file-type-icon { margin-right:6px; }
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
    .status-rejected { background:#fee2e2; color:#991b1b; }
    .file-actions { display:flex; flex-wrap:wrap; gap:6px; }
    .file-meta { display:flex; flex-direction:column; gap:4px; }
    .flow-summary { display:block; margin:6px 0; font-size:12px; color: var(--muted); }
    .flow-panel { margin-top:6px; background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:8px; grid-column: 1 / -1; }
    .history-panel { margin-top:6px; background:#f8fafc; border:1px dashed var(--border); border-radius:10px; padding:8px; grid-column: 1 / -1; }
    .history-list { margin:6px 0 0; padding-left:18px; color: var(--text-strong); font-size:12px; }
    .flow-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:8px; margin-bottom:6px; }
    .flow-grid label { display:flex; flex-direction:column; gap:4px; font-size:13px; color: var(--text-strong); }
    .flow-grid select { border:1px solid var(--border); border-radius:8px; padding:6px 8px; }
    .document-alert { background:#fef3c7; color:#92400e; padding:8px 10px; border-radius:10px; font-size:13px; display:flex; flex-direction:column; gap:4px; min-width:200px; }
    .file-trace { margin-top:6px; font-size:12px; color: var(--muted); }
    .flow-actions { display:flex; justify-content:flex-end; margin-bottom:6px; }
    .modal { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:50; }
    .modal[hidden] { display:none; }
    .modal-backdrop { position:absolute; inset:0; background:rgba(15, 23, 42, 0.45); }
    .modal-panel { position:relative; background:#fff; border-radius:14px; padding:16px; width:min(640px, 92vw); max-height:90vh; overflow:auto; display:flex; flex-direction:column; gap:12px; box-shadow:0 20px 40px rgba(15, 23, 42, 0.2); }
    .modal-panel header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .field { display:flex; flex-direction:column; gap:6px; font-size:13px; color: var(--text-strong); }
    .field input, .field select, .field textarea { border:1px solid var(--border); border-radius:8px; padding:6px 8px; }
    .field-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
    .form-validation { background:#fef3c7; color:#92400e; border:1px solid #fde68a; border-radius:8px; padding:8px 10px; font-size:12px; font-weight:600; }
    .switch { display:flex; align-items:center; gap:10px; font-size:13px; }
    .switch input { display:none; }
    .switch .slider { width:40px; height:22px; background:#e5e7eb; border-radius:999px; position:relative; }
    .switch .slider::after { content:''; position:absolute; width:18px; height:18px; background:#fff; border-radius:50%; top:2px; left:2px; transition:all 0.2s ease; box-shadow:0 1px 3px rgba(0,0,0,0.2); }
    .switch input:checked + .slider { background:#22c55e; }
    .switch input:checked + .slider::after { transform: translateX(18px); }
    .modal-actions { display:flex; justify-content:flex-end; gap:8px; }
    .action-btn { display:inline-flex; align-items:center; gap:8px; }
    .button-spinner { width:14px; height:14px; border-radius:50%; border:2px solid rgba(255,255,255,0.5); border-top-color:#fff; animation: spin 1s linear infinite; display:none; }
    .action-btn.is-loading .button-spinner { display:inline-block; }
    .action-btn.is-loading .button-label { opacity:0.8; }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    @media (max-width: 900px) {
        .document-file-row { grid-template-columns: 1fr; }
        .document-file-head { display:none; }
    }
</style>

<script>
    (() => {
        const root = document.querySelector('[data-document-flow="<?= htmlspecialchars($documentFlowId) ?>"]');
        if (!root) return;

        const currentUserName = <?= json_encode((string) ($documentCurrentUser['name'] ?? 'Usuario')) ?>;
        const keyTags = <?= json_encode(array_values($documentKeyTags)) ?>;
        const basePath = <?= json_encode((string) $documentBasePath) ?>;
        const documentProjectId = <?= json_encode($documentProjectId) ?>;
        const documentCanManage = <?= json_encode($documentCanManage) ?>;
        const roleCache = new Map();
        const saveTimers = new Map();
        const documentTagOptions = <?= json_encode(array_values($documentTagOptions)) ?>;
        const documentDefaultTags = <?= json_encode($normalizedDefaultTags) ?>;

        const statusConfig = {
            borrador: { label: 'Borrador', className: 'status-pending' },
            en_revision: { label: 'En revisión', className: 'status-review' },
            revisado: { label: 'Revisado', className: 'status-review' },
            en_validacion: { label: 'En validación', className: 'status-review' },
            validado: { label: 'Validado', className: 'status-validated' },
            en_aprobacion: { label: 'En aprobación', className: 'status-validated' },
            aprobado: { label: 'Aprobado', className: 'status-approved' },
            rechazado: { label: 'Rechazado', className: 'status-rejected' }
        };
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

        const parseTagString = (value) => {
            if (!value) return [];
            return value.split('|').map(tag => tag.trim()).filter(Boolean);
        };

        const toast = root.querySelector('[data-document-toast]');
        const toastMessage = root.querySelector('[data-document-toast-message]');
        let toastTimer;
        const showToast = (message, tone = 'success') => {
            if (!toast || !toastMessage) return;
            toastMessage.textContent = message;
            toast.className = `document-toast ${tone}`;
            toast.hidden = false;
            if (toastTimer) {
                clearTimeout(toastTimer);
            }
            toastTimer = setTimeout(() => {
                toast.hidden = true;
            }, 4000);
        };

        const sanitizeTags = (tags) => {
            const clean = tags.map(tag => tag.trim()).filter(Boolean);
            return Array.from(new Set(clean));
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
                const type = row.dataset.documentType ? [row.dataset.documentType] : [];
                const status = row.dataset.documentStatus || 'borrador';
                [...tags, ...type].forEach(tag => {
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
            row.dataset.tags = tags.join('|');
            updateAlert();
        };

        const updateFlowSummary = (row) => {
            const summary = row.querySelector('[data-flow-summary]');
            if (!summary) return;
            const reviewer = row.dataset.reviewerId ? `Usuario #${row.dataset.reviewerId}` : 'No asignado';
            const validator = row.dataset.validatorId ? `Usuario #${row.dataset.validatorId}` : 'No asignado';
            const approver = row.dataset.approverId ? `Usuario #${row.dataset.approverId}` : 'No asignado';
            const reviewedBy = row.dataset.reviewedBy ? `Usuario #${row.dataset.reviewedBy}` : null;
            const validatedBy = row.dataset.validatedBy ? `Usuario #${row.dataset.validatedBy}` : null;
            const approvedBy = row.dataset.approvedBy ? `Usuario #${row.dataset.approvedBy}` : null;
            const reviewedAt = row.dataset.reviewedAt || null;
            const validatedAt = row.dataset.validatedAt || null;
            const approvedAt = row.dataset.approvedAt || null;
            const reviewTrace = reviewedBy && reviewedAt ? `Revisado por ${reviewedBy} · ${reviewedAt}` : 'Revisión pendiente';
            const validationTrace = validatedBy && validatedAt ? `Validado por ${validatedBy} · ${validatedAt}` : 'Validación pendiente';
            const approvalTrace = approvedBy && approvedAt ? `Aprobado por ${approvedBy} · ${approvedAt}` : 'Aprobación pendiente';
            summary.innerHTML = `Revisor: ${reviewer}<br>Validador: ${validator}<br>Aprobador: ${approver}<br>${reviewTrace}<br>${validationTrace}<br>${approvalTrace}`;
        };

        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        };

        const createTagSelect = () => {
            const select = document.createElement('select');
            select.multiple = true;
            select.dataset.tagSelect = '';
            documentTagOptions.forEach(tag => {
                const option = document.createElement('option');
                option.value = tag;
                option.textContent = tag;
                select.appendChild(option);
            });
            return select;
        };

        const buildFileRow = (data) => {
            const row = document.createElement('div');
            row.className = 'document-file-row';
            row.dataset.fileRow = '';
            row.dataset.fileId = data.id ?? '';
            row.dataset.reviewerId = data.reviewer_id ?? '';
            row.dataset.validatorId = data.validator_id ?? '';
            row.dataset.approverId = data.approver_id ?? '';
            row.dataset.documentStatus = data.document_status ?? 'borrador';
            row.dataset.tags = Array.isArray(data.tags) ? data.tags.join('|') : '';
            row.dataset.documentVersion = data.version ?? '';
            row.dataset.documentType = data.document_type ?? '';
            row.dataset.description = data.description ?? '';
            row.dataset.fileName = data.file_name ?? '';
            row.dataset.reviewedBy = data.reviewed_by ?? '';
            row.dataset.reviewedAt = data.reviewed_at ?? '';
            row.dataset.validatedBy = data.validated_by ?? '';
            row.dataset.validatedAt = data.validated_at ?? '';
            row.dataset.approvedBy = data.approved_by ?? '';
            row.dataset.approvedAt = data.approved_at ?? '';

            row.innerHTML = `
                <div>
                    <strong>📄 ${escapeHtml(data.file_name ?? '')}</strong>
                    <small class="section-muted">Subido: ${escapeHtml(data.created_at ?? '')}</small>
                    <div class="file-trace" data-file-trace>Sin trazabilidad registrada.</div>
                </div>
                <div class="file-meta">
                    <div><strong>${escapeHtml(data.document_type ?? '')}</strong></div>
                    <small class="section-muted">${escapeHtml(data.description ?? 'Sin descripción')}</small>
                </div>
                <div class="tag-editor" data-tag-editor>
                    <div class="tag-pills" data-tag-pills></div>
                    <div class="tag-controls">
                        ${createTagSelect().outerHTML}
                        <input type="text" placeholder="Otro (texto libre)" data-tag-custom>
                    </div>
                </div>
                <div>
                    <input type="text" class="version-input" placeholder="v1, v2, final" data-version-input value="${escapeHtml(data.version ?? '')}">
                </div>
                <div>
                    <span class="status-pill status-pending" data-status-label>Borrador</span>
                    ${documentCanManage ? '<button type="button" class="action-btn small" data-send-review>Enviar a revisión</button>' : ''}
                    <small class="flow-summary" data-flow-summary></small>
                    <button type="button" class="action-btn small" data-toggle-history>Historial</button>
                </div>
                <div class="file-actions">
                    <a class="action-btn small" href="${basePath}/projects/${documentProjectId}/nodes/${data.id}/download">Ver</a>
                    ${documentCanManage ? `<form method="POST" action="${basePath}/projects/${documentProjectId}/nodes/${data.id}/delete" onsubmit="return confirm('¿Eliminar archivo?');"><button class="action-btn danger small" type="submit">Eliminar</button></form>` : ''}
                    ${documentCanManage ? '<button type="button" class="action-btn small" data-toggle-flow>Asignar flujo</button>' : ''}
                </div>
                <div class="history-panel" data-history-panel hidden>
                    <strong>Historial</strong>
                    <ul class="history-list" data-history-list>
                        <li class="section-muted">Cargando historial...</li>
                    </ul>
                </div>
                <div class="flow-panel" data-flow-panel hidden>
                    <div class="flow-grid">
                        <label>
                            <span>Revisor</span>
                            <select data-role-select="reviewer"><option value="">Seleccionar</option></select>
                        </label>
                        <label>
                            <span>Validador</span>
                            <select data-role-select="validator"><option value="">Seleccionar</option></select>
                        </label>
                        <label>
                            <span>Aprobador</span>
                            <select data-role-select="approver"><option value="">Seleccionar</option></select>
                        </label>
                    </div>
                    <div class="flow-actions">
                        <button type="button" class="action-btn small primary" data-save-flow>Guardar flujo</button>
                    </div>
                    <small class="section-muted">Asigna responsables para habilitar las acciones por rol.</small>
                </div>
            `;

            return row;
        };

        const initRow = (row) => {
            const existingTags = parseTagString(row.dataset.tags);
            updateTagsDisplay(row, existingTags);
            applyTagSelection(row, existingTags);
            const status = row.dataset.documentStatus || 'borrador';
            updateStatus(row, status);
            updateTraceFromData(row);
            updateFlowSummary(row);
            toggleSendReview(row);
            const versionInput = row.querySelector('[data-version-input]');
            if (versionInput) {
                versionInput.value = row.dataset.documentVersion || '';
            }
        };

        const applyTagSelection = (row, tags) => {
            const select = row.querySelector('[data-tag-select]');
            const customInput = row.querySelector('[data-tag-custom]');
            if (!select) return;
            const options = Array.from(select.options);
            const optionValues = new Set(options.map(option => option.value));
            const selected = tags.filter(tag => optionValues.has(tag));
            options.forEach(option => {
                option.selected = selected.includes(option.value);
            });
            if (customInput) {
                const customTags = tags.filter(tag => !optionValues.has(tag));
                customInput.value = customTags.join(', ');
            }
        };

        const collectTags = (row) => {
            const select = row.querySelector('[data-tag-select]');
            const customInput = row.querySelector('[data-tag-custom]');
            const selectedTags = select ? Array.from(select.selectedOptions).map(option => option.value) : [];
            const customTags = customInput && customInput.value.trim()
                ? customInput.value.split(',').map(tag => tag.trim()).filter(Boolean)
                : [];
            return sanitizeTags([...selectedTags, ...customTags]);
        };

        const scheduleMetadataSave = (row) => {
            const fileId = row.dataset.fileId;
            if (!fileId) return;
            if (saveTimers.has(fileId)) {
                clearTimeout(saveTimers.get(fileId));
            }
            saveTimers.set(fileId, setTimeout(() => {
                saveDocumentMetadata(row);
            }, 400));
        };

        const saveDocumentMetadata = (row) => {
            const fileId = row.dataset.fileId;
            if (!fileId) return;
            const tags = collectTags(row);
            const versionInput = row.querySelector('[data-version-input]');
            const version = versionInput ? versionInput.value.trim() : '';
            fetch(`${basePath}/projects/${documentProjectId}/nodes/${fileId}/document-metadata`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({
                    tags: JSON.stringify(tags),
                    version,
                }),
            })
                .then(response => response.json())
                .then(payload => {
                    if (payload.status !== 'ok') {
                        throw new Error(payload.message || 'No se pudo guardar la metadata del documento.');
                    }
                    row.dataset.tags = tags.join('|');
                    row.dataset.documentVersion = payload.data.document_version ?? '';
                    updateTagsDisplay(row, tags);
                    showToast('Metadatos actualizados.', 'success');
                })
                .catch(error => {
                    alert(error.message);
                });
        };

        const updateStatus = (row, statusKey, traceNote) => {
            const config = statusConfig[statusKey] || statusConfig.borrador;
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
            const status = row.dataset.documentStatus || 'borrador';
            const traceMap = [
                { status: 'aprobado', by: row.dataset.approvedBy, at: row.dataset.approvedAt, label: 'Aprobado' },
                { status: 'en_aprobacion', by: row.dataset.validatedBy, at: row.dataset.validatedAt, label: 'En aprobación' },
                { status: 'validado', by: row.dataset.validatedBy, at: row.dataset.validatedAt, label: 'Validado' },
                { status: 'en_validacion', by: row.dataset.reviewedBy, at: row.dataset.reviewedAt, label: 'En validación' },
                { status: 'revisado', by: row.dataset.reviewedBy, at: row.dataset.reviewedAt, label: 'Revisado' },
                { status: 'en_revision', by: row.dataset.reviewedBy, at: row.dataset.reviewedAt, label: 'En revisión' },
                { status: 'rechazado', by: row.dataset.approvedBy || row.dataset.validatedBy || row.dataset.reviewedBy, at: row.dataset.approvedAt || row.dataset.validatedAt || row.dataset.reviewedAt, label: 'Rechazado' },
            ];

            const entry = traceMap.find(item => item.status === status && item.by && item.at);
            if (!entry) {
                return;
            }

            trace.textContent = `${entry.label} · Usuario #${entry.by} · ${entry.at}`;
        };

        const toggleSendReview = (row) => {
            const button = row.querySelector('[data-send-review]');
            if (!button) return;
            const status = row.dataset.documentStatus || 'borrador';
            button.hidden = status !== 'borrador';
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
            initRow(row);
        });

        root.querySelectorAll('select[data-role-select]').forEach(select => {
            const role = select.dataset.roleSelect;
            loadRoleOptions(role, select);
        });

        root.addEventListener('change', (event) => {
            const select = event.target.closest('[data-tag-select]');
            if (select) {
                const row = select.closest('[data-file-row]');
                const tags = collectTags(row);
                updateTagsDisplay(row, tags);
                scheduleMetadataSave(row);
            }

            const customInput = event.target.closest('[data-tag-custom]');
            if (customInput) {
                const row = customInput.closest('[data-file-row]');
                const tags = collectTags(row);
                updateTagsDisplay(row, tags);
                scheduleMetadataSave(row);
            }

            const roleSelect = event.target.closest('[data-role-select]');
            if (roleSelect) {
                const row = roleSelect.closest('[data-file-row]');
                const role = roleSelect.dataset.roleSelect;
                row.dataset[`${role}Id`] = roleSelect.value;
                updateFlowSummary(row);
            }

            const versionInput = event.target.closest('[data-version-input]');
            if (versionInput) {
                const row = versionInput.closest('[data-file-row]');
                row.dataset.documentVersion = versionInput.value.trim();
                scheduleMetadataSave(row);
            }
        });

        root.addEventListener('blur', (event) => {
            const customInput = event.target.closest('[data-tag-custom]');
            if (customInput) {
                const row = customInput.closest('[data-file-row]');
                const tags = collectTags(row);
                updateTagsDisplay(row, tags);
                scheduleMetadataSave(row);
            }
            const versionInput = event.target.closest('[data-version-input]');
            if (versionInput) {
                const row = versionInput.closest('[data-file-row]');
                row.dataset.documentVersion = versionInput.value.trim();
                scheduleMetadataSave(row);
            }
        }, true);

        root.addEventListener('click', (event) => {
            const openUploadAction = event.target.closest('[data-open-upload]');
            if (openUploadAction) {
                openUploadModal(event);
                return;
            }
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
                        showToast('Documento enviado a revisión.', 'success');
                    })
                    .catch(error => {
                        alert(error.message);
                    })
                    .finally(() => {
                        sendReview.disabled = false;
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
                        showToast('Flujo documental guardado.', 'success');
                    })
                    .catch(error => {
                        alert(error.message);
                    })
                    .finally(() => {
                        saveFlow.disabled = false;
                    });
            }

            const toggleHistory = event.target.closest('[data-toggle-history]');
            if (toggleHistory) {
                const row = toggleHistory.closest('[data-file-row]');
                const panel = row.querySelector('[data-history-panel]');
                const list = row.querySelector('[data-history-list]');
                if (!panel || !list) return;
                panel.hidden = !panel.hidden;
                if (panel.hidden || panel.dataset.loaded === 'true') {
                    return;
                }
                const fileId = row.dataset.fileId;
                fetch(`${basePath}/projects/${documentProjectId}/nodes/${fileId}/document-history`, {
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

        const updateFlowRow = (row, data, traceNote) => {
            row.dataset.reviewerId = data.reviewer_id ?? '';
            row.dataset.validatorId = data.validator_id ?? '';
            row.dataset.approverId = data.approver_id ?? '';
            row.dataset.documentStatus = data.document_status ?? 'borrador';
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
            updateFlowSummary(row);
            toggleSendReview(row);
        };

        const uploadModal = root.querySelector('[data-upload-modal]');
        const openUpload = root.querySelector('[data-open-upload]');
        const closeUploadButtons = root.querySelectorAll('[data-close-upload]');
        const uploadPreview = root.querySelector('[data-upload-preview]');
        const uploadForm = root.querySelector('[data-upload-form]');
        const uploadInput = uploadForm ? uploadForm.querySelector('input[type="file"]') : null;
        const uploadSubmitButton = uploadForm ? uploadForm.querySelector('[data-upload-submit]') : null;
        const uploadValidation = uploadForm ? uploadForm.querySelector('[data-upload-validation]') : null;

        const closeModal = () => {
            if (uploadModal) {
                uploadModal.hidden = true;
            }
        };

        const setUploadValidation = (message) => {
            if (!uploadValidation) return;
            if (!message) {
                uploadValidation.hidden = true;
                return;
            }
            uploadValidation.textContent = message;
            uploadValidation.hidden = false;
        };

        const openUploadModal = (event) => {
            if (event) {
                event.preventDefault();
            }
            const trigger = event?.currentTarget ?? event?.target ?? null;
            const scopedForm = trigger?.closest('[data-upload-form]') || uploadForm;
            const scopedModal = scopedForm?.querySelector('[data-upload-modal]') || uploadModal;
            const scopedInput = scopedForm?.querySelector('input[type="file"]') || uploadInput;
            setUploadValidation('');
            console.log('Click en Subir documento: handler activo.');
            if (scopedModal) {
                scopedModal.hidden = false;
                return;
            }
            if (scopedInput) {
                scopedInput.click();
            }
        };

        closeUploadButtons.forEach(button => {
            button.addEventListener('click', closeModal);
        });

        if (openUpload) {
            openUpload.addEventListener('click', openUploadModal);
        }

        const uploadButtonById = document.getElementById('btnUploadDocument');
        if (uploadButtonById && uploadButtonById !== openUpload) {
            uploadButtonById.addEventListener('click', openUploadModal);
        }

        const collectUploadTags = () => {
            const select = uploadModal?.querySelector('[data-upload-tag-select]');
            const customInput = uploadModal?.querySelector('[data-upload-tag-custom]');
            const selectedTags = select ? Array.from(select.selectedOptions).map(option => option.value) : [];
            const customTags = customInput && customInput.value.trim()
                ? customInput.value.split(',').map(tag => tag.trim()).filter(Boolean)
                : [];
            return sanitizeTags([...documentDefaultTags, ...selectedTags, ...customTags]);
        };

        const collectUploadType = () => {
            const select = uploadModal?.querySelector('[data-document-type-select]');
            const customInput = uploadModal?.querySelector('[data-document-type-custom]');
            const custom = customInput ? customInput.value.trim() : '';
            if (custom !== '') {
                return custom;
            }
            return select ? select.value : '';
        };

        if (uploadForm) {
            if (uploadInput && uploadPreview) {
                uploadInput.addEventListener('change', () => {
                    const list = uploadPreview.querySelector('ul');
                    list.innerHTML = '';
                    Array.from(uploadInput.files || []).forEach(file => {
                        const item = document.createElement('li');
                        item.textContent = `${file.name} (${Math.round(file.size / 1024)} KB)`;
                        list.appendChild(item);
                    });
                    uploadPreview.hidden = list.children.length === 0;
                });
            }

            uploadForm.addEventListener('submit', (event) => {
                event.preventDefault();
                if (uploadModal && uploadModal.hidden) {
                    setUploadValidation('');
                    uploadModal.hidden = false;
                    return;
                }
                const documentType = collectUploadType();
                const tags = collectUploadTags();
                const versionValue = (uploadForm.querySelector('input[name="document_version"]')?.value ?? '').trim();
                const descriptionValue = (uploadForm.querySelector('textarea[name="document_description"]')?.value ?? '').trim();
                const fileInput = uploadForm.querySelector('input[type="file"]');
                const hiddenType = uploadForm.querySelector('[data-document-type-hidden]');
                const hiddenTags = uploadForm.querySelector('[data-document-tags-hidden]');
                if (hiddenType) hiddenType.value = documentType;
                if (hiddenTags) hiddenTags.value = JSON.stringify(tags);

                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                    setUploadValidation('Selecciona un archivo para continuar.');
                    return;
                }
                if (!documentType) {
                    setUploadValidation('Selecciona el tipo documental.');
                    return;
                }
                if (tags.length === 0) {
                    setUploadValidation('Selecciona al menos un tag.');
                    return;
                }
                if (!versionValue) {
                    setUploadValidation('Ingresa la versión del documento.');
                    return;
                }
                if (!descriptionValue) {
                    setUploadValidation('Ingresa una descripción corta.');
                    return;
                }
                setUploadValidation('');
                if (uploadSubmitButton && uploadSubmitButton.dataset.loading === 'true') {
                    return;
                }
                if (uploadSubmitButton) {
                    const label = uploadSubmitButton.querySelector('.button-label');
                    uploadSubmitButton.dataset.originalLabel = label?.textContent || 'Guardar documento';
                    if (label) {
                        label.textContent = 'Guardando documento...';
                    }
                    uploadSubmitButton.dataset.loading = 'true';
                    uploadSubmitButton.disabled = true;
                    uploadSubmitButton.classList.add('is-loading');
                }

                const formData = new FormData(uploadForm);
                if (fileInput && fileInput.files && fileInput.files.length > 0) {
                    Array.from(fileInput.files).forEach(file => formData.append('node_files[]', file));
                }
                fetch(uploadForm.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                })
                    .then(response => response.json())
                    .then(payload => {
                        if (!payload.success) {
                            throw new Error(payload.message || 'No se pudo subir el documento.');
                        }
                        let table = root.querySelector('.document-file-table');
                        if (!table) {
                            const filesSection = root.querySelector('.document-files');
                            if (filesSection) {
                                const emptyState = filesSection.querySelector('p.section-muted');
                                if (emptyState) {
                                    emptyState.remove();
                                }
                                table = document.createElement('div');
                                table.className = 'document-file-table';
                                table.innerHTML = `
                                    <div class="document-file-row document-file-head">
                                        <span>Archivo</span>
                                        <span>Tipo / Descripción</span>
                                        <span>Tags</span>
                                        <span>Versión</span>
                                        <span>Flujo & estado</span>
                                        <span>Acciones</span>
                                    </div>
                                `;
                                filesSection.appendChild(table);
                            }
                        }
                        if (table && Array.isArray(payload.data)) {
                            payload.data.forEach(item => {
                                const row = buildFileRow(item);
                                table.appendChild(row);
                                initRow(row);
                                row.querySelectorAll('select[data-role-select]').forEach(select => {
                                    loadRoleOptions(select.dataset.roleSelect, select);
                                });
                            });
                        }
                        uploadForm.reset();
                        if (uploadPreview) {
                            uploadPreview.hidden = true;
                        }
                        closeModal();
                        updateAlert();
                        showToast('Documento guardado en la subfase.', 'success');
                    })
                    .catch(error => {
                        setUploadValidation(error.message);
                    })
                    .finally(() => {
                        if (uploadSubmitButton) {
                            const label = uploadSubmitButton.querySelector('.button-label');
                            if (label) {
                                label.textContent = uploadSubmitButton.dataset.originalLabel || 'Guardar documento';
                            }
                            uploadSubmitButton.disabled = false;
                            uploadSubmitButton.classList.remove('is-loading');
                            uploadSubmitButton.dataset.loading = 'false';
                        }
                    });
            });
        }

        const searchInput = root.querySelector('[data-document-search]');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.trim().toLowerCase();
                root.querySelectorAll('[data-file-row]').forEach(row => {
                    if (!query) {
                        row.hidden = false;
                        return;
                    }
                    const tags = row.dataset.tags || '';
                    const type = row.dataset.documentType || '';
                    const version = row.dataset.documentVersion || '';
                    const status = row.dataset.documentStatus || '';
                    const name = row.dataset.fileName || '';
                    const description = row.dataset.description || '';
                    const haystack = `${name} ${tags} ${type} ${version} ${status} ${description}`.toLowerCase();
                    row.hidden = !haystack.includes(query);
                });
            });
        }

        updateAlert();
    })();
</script>
