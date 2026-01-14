<?php
$basePath = $basePath ?? '/project/public';
$service = is_array($service ?? null) ? $service : [];
$followups = is_array($followups ?? null) ? $followups : [];
$users = is_array($users ?? null) ? $users : [];
$documentFlowConfig = is_array($documentFlowConfig ?? null) ? $documentFlowConfig : [];
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];
$canManage = !empty($canManage);

$serviceStatusLabels = [
    'active' => 'Activo',
    'paused' => 'En pausa',
    'ended' => 'Finalizado',
];
$healthLabels = [
    'green' => 'GREEN (Normal)',
    'yellow' => 'YELLOW (En riesgo)',
    'red' => 'RED (Crítico)',
];
$followupStatusLabels = [
    'open' => 'Abierto',
    'closed' => 'Cerrado',
    'observed' => 'Observado',
];
$healthBadge = static function (?string $status): string {
    return match ($status) {
        'green' => 'status-success',
        'yellow' => 'status-warning',
        'red' => 'status-danger',
        default => 'status-muted',
    };
};
$formatTimestamp = static function (?string $value): string {
    if (!$value) {
        return 'Sin registro';
    }

    $timestamp = strtotime($value);
    if (!$timestamp) {
        return 'Sin registro';
    }

    return date('d/m/Y H:i', $timestamp);
};
$latestHealth = $followups[0]['service_health'] ?? null;
$projectProgress = isset($service['project_progress']) ? (float) $service['project_progress'] : null;
?>

<section class="outsourcing-shell">
    <header class="outsourcing-header">
        <div>
            <p class="eyebrow">Servicio de outsourcing</p>
            <h2><?= htmlspecialchars($service['talent_name'] ?? 'Talento') ?></h2>
            <small class="section-muted">Cliente: <?= htmlspecialchars($service['client_name'] ?? '') ?> · Proyecto: <?= htmlspecialchars($service['project_name'] ?? 'Sin proyecto') ?></small>
        </div>
        <a class="action-btn" href="<?= $basePath ?>/outsourcing">Volver al listado</a>
    </header>

    <nav class="outsourcing-tabs">
        <a href="#resumen" class="tab-link">Resumen</a>
        <a href="#seguimientos" class="tab-link">Seguimientos</a>
        <a href="#evidencias" class="tab-link">Evidencias</a>
    </nav>

    <section class="outsourcing-overview" id="resumen">
        <div class="overview-card">
            <h4>Resumen</h4>
            <div class="summary-grid">
                <div>
                    <span class="section-muted">Talento</span>
                    <strong><?= htmlspecialchars($service['talent_name'] ?? 'Talento') ?></strong>
                </div>
                <div>
                    <span class="section-muted">Cliente</span>
                    <strong><?= htmlspecialchars($service['client_name'] ?? '') ?></strong>
                </div>
                <div>
                    <span class="section-muted">Proyecto</span>
                    <strong><?= htmlspecialchars($service['project_name'] ?? 'Sin proyecto') ?></strong>
                </div>
                <div>
                    <span class="section-muted">Periodo del servicio</span>
                    <strong><?= htmlspecialchars($service['start_date'] ?? '') ?> → <?= htmlspecialchars($service['end_date'] ?? 'Actual') ?></strong>
                </div>
                <div>
                    <span class="section-muted">Estado actual</span>
                    <strong><?= htmlspecialchars($serviceStatusLabels[$service['service_status'] ?? 'active'] ?? 'Activo') ?></strong>
                </div>
                <div>
                    <span class="section-muted">Avance del proyecto (manual)</span>
                    <strong><?= $projectProgress !== null ? htmlspecialchars((string) $projectProgress) . '%' : 'Sin proyecto' ?></strong>
                </div>
            </div>
        </div>
        <div class="overview-card">
            <h4>Estado del servicio</h4>
            <p class="section-muted">Salud actual según último seguimiento.</p>
            <div class="status-row">
                <span class="status-badge <?= $healthBadge($latestHealth) ?>">
                    <?= htmlspecialchars($healthLabels[$latestHealth ?? ''] ?? 'Sin seguimiento') ?>
                </span>
            </div>
        </div>
        <div class="overview-card">
            <h4>Frecuencia de seguimiento</h4>
            <p class="section-muted">Cadencia actual: <?= htmlspecialchars(($service['followup_frequency'] ?? 'monthly') === 'weekly' ? 'Semanal' : 'Mensual') ?></p>
            <?php if ($canManage): ?>
                <form method="POST" action="<?= $basePath ?>/outsourcing/<?= (int) ($service['id'] ?? 0) ?>/frequency" class="inline-form">
                    <select name="followup_frequency" required>
                        <option value="weekly" <?= ($service['followup_frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Semanal</option>
                        <option value="monthly" <?= ($service['followup_frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Mensual</option>
                    </select>
                    <button type="submit" class="action-btn small">Actualizar</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="overview-card">
            <h4>Estado operativo</h4>
            <p class="section-muted">Define si el servicio sigue activo o finalizado.</p>
            <?php if ($canManage): ?>
                <form method="POST" action="<?= $basePath ?>/outsourcing/<?= (int) ($service['id'] ?? 0) ?>/status" class="inline-form">
                    <select name="service_status" required>
                        <option value="active" <?= ($service['service_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Activo</option>
                        <option value="paused" <?= ($service['service_status'] ?? '') === 'paused' ? 'selected' : '' ?>>En pausa</option>
                        <option value="ended" <?= ($service['service_status'] ?? '') === 'ended' ? 'selected' : '' ?>>Finalizado</option>
                    </select>
                    <button type="submit" class="action-btn small">Actualizar</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="outsourcing-followups" id="seguimientos">
        <div>
            <p class="eyebrow">Seguimientos periódicos</p>
            <h3>Bitácora inmutable del servicio</h3>
            <small class="section-muted">Cada seguimiento registra observaciones obligatorias, salud del servicio y evidencia documental.</small>
        </div>

        <?php if ($canManage): ?>
            <form method="POST" action="<?= $basePath ?>/outsourcing/<?= (int) ($service['id'] ?? 0) ?>/followups" class="followup-form">
                <h5>Nuevo seguimiento</h5>
                <div class="grid">
                    <label>Periodo inicio
                        <input type="date" name="period_start" required>
                    </label>
                    <label>Periodo fin
                        <input type="date" name="period_end" required>
                    </label>
                </div>
                <label>Responsable
                    <select name="responsible_user_id" required>
                        <option value="">Selecciona un responsable</option>
                        <?php foreach ($users as $userRow): ?>
                            <option value="<?= (int) ($userRow['id'] ?? 0) ?>">
                                <?= htmlspecialchars($userRow['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Salud del servicio
                    <select name="service_health" required>
                        <option value="green">GREEN (Normal)</option>
                        <option value="yellow">YELLOW (En riesgo)</option>
                        <option value="red">RED (Crítico)</option>
                    </select>
                </label>
                <label>Estado del seguimiento
                    <select name="followup_status" required>
                        <option value="open" selected>Abierto</option>
                        <option value="observed">Observado</option>
                    </select>
                </label>
                <label>Observaciones / análisis
                    <textarea name="observations" rows="4" required></textarea>
                </label>
                <button type="submit" class="action-btn primary">Guardar seguimiento</button>
            </form>
        <?php endif; ?>

        <?php if (empty($followups)): ?>
            <p class="section-muted">Aún no hay seguimientos registrados.</p>
        <?php else: ?>
            <h4 class="section-title" id="evidencias">Evidencias</h4>
            <div class="followup-list">
                <?php foreach ($followups as $followup): ?>
                    <article class="followup-card">
                        <header class="followup-header">
                            <div>
                                <h5>Periodo <?= htmlspecialchars((string) ($followup['period_start'] ?? '')) ?> → <?= htmlspecialchars((string) ($followup['period_end'] ?? '')) ?></h5>
                                <p class="section-muted">Responsable: <?= htmlspecialchars($followup['responsible_name'] ?? 'Sin asignar') ?></p>
                                <p class="section-muted">Registrado por <?= htmlspecialchars($followup['created_by_name'] ?? 'Sistema') ?> · <?= htmlspecialchars($formatTimestamp($followup['created_at'] ?? null)) ?></p>
                                <p class="section-muted">Estado del seguimiento: <?= htmlspecialchars($followupStatusLabels[$followup['followup_status'] ?? 'open'] ?? 'Abierto') ?></p>
                                <?php if (!empty($followup['closed_at'])): ?>
                                    <p class="section-muted">Cerrado el <?= htmlspecialchars($formatTimestamp($followup['closed_at'] ?? null)) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="followup-actions">
                                <span class="status-badge <?= $healthBadge((string) ($followup['service_health'] ?? '')) ?>">
                                    <?= htmlspecialchars($healthLabels[(string) ($followup['service_health'] ?? '')] ?? 'Sin estado') ?>
                                </span>
                                <?php if ($canManage && ($followup['followup_status'] ?? '') !== 'closed'): ?>
                                    <form method="POST" action="<?= $basePath ?>/outsourcing/<?= (int) ($service['id'] ?? 0) ?>/followups/<?= (int) ($followup['id'] ?? 0) ?>/close" onsubmit="return confirm('¿Cerrar este seguimiento?');">
                                        <button type="submit" class="action-btn small">Cerrar seguimiento</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </header>
                        <div class="followup-body">
                            <div>
                                <h6>Observaciones</h6>
                                <p><?= nl2br(htmlspecialchars((string) ($followup['observations'] ?? ''))) ?></p>
                            </div>
                        </div>
                        <?php if (!empty($followup['document_node'])): ?>
                            <?php
                            $documentFlowId = 'outsourcing-followup-' . (int) ($followup['id'] ?? 0);
                            $documentNode = $followup['document_node'];
                            $documentExpectedDocs = [];
                            $documentTagOptions = array_values(array_unique(array_merge(
                                $documentFlowConfig['tag_options'] ?? [],
                                ['outsourcing']
                            )));
                            $documentKeyTags = [];
                            $documentDefaultTags = ['outsourcing'];
                            $documentCanManage = $canManage;
                            $documentMode = 'outsourcing';
                            $documentProjectId = (int) ($service['project_id'] ?? 0);
                            $documentBasePath = $basePath;
                            $documentCurrentUser = $currentUser;
                            $documentContextLabel = 'SEGUIMIENTO';
                            $documentContextDescription = 'Evidencias y documentos de soporte para el seguimiento del servicio.';
                            $documentExpectedTitle = 'Documentos sugeridos por seguimiento';
                            $documentExpectedDescription = 'Adjunta reportes, actas, evidencias o reportes de servicio relacionados.';
                            require __DIR__ . '/../projects/document_flow.php';
                            ?>
                        <?php else: ?>
                            <p class="section-muted">No hay carpeta documental asociada o el servicio no tiene proyecto vinculado.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<style>
    .outsourcing-shell { display:flex; flex-direction:column; gap:18px; }
    .outsourcing-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .outsourcing-tabs { display:flex; gap:12px; flex-wrap:wrap; }
    .tab-link { text-decoration:none; padding:6px 12px; border-radius:999px; background:#e0f2fe; color:#0369a1; font-weight:700; font-size:13px; }
    .outsourcing-overview { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    .overview-card { border:1px solid var(--border); border-radius:16px; padding:14px; background:#fff; display:flex; flex-direction:column; gap:8px; }
    .summary-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; }
    .summary-grid strong { font-size:14px; color: var(--text-strong); }
    .status-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .outsourcing-followups { border:1px solid var(--border); border-radius:16px; padding:16px; background:#fff; display:flex; flex-direction:column; gap:12px; }
    .section-title { margin:0; font-size:16px; color: var(--text-strong); }
    .followup-form { border:1px dashed var(--border); border-radius:12px; padding:14px; display:flex; flex-direction:column; gap:12px; background:#fff; }
    .followup-form label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-strong); }
    .followup-form input,
    .followup-form select,
    .followup-form textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    .followup-list { display:flex; flex-direction:column; gap:16px; }
    .followup-card { border:1px solid var(--border); border-radius:16px; padding:16px; background:#f8fafc; display:flex; flex-direction:column; gap:12px; }
    .followup-header { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
    .followup-actions { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
    .followup-body { display:grid; gap:12px; }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid transparent; }
    .status-muted { background:#f3f4f6; color:#374151; border-color:#e5e7eb; }
    .status-success { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
    .status-warning { background:#fef9c3; color:#854d0e; border-color:#fde047; }
    .status-danger { background:#fee2e2; color:#991b1b; border-color:#fecdd3; }
    .status-pill { font-size:12px; font-weight:600; padding:4px 10px; border-radius:999px; background:#eef2ff; color:#4338ca; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color:#fff; border-color: var(--primary); }
    .action-btn.small { padding:6px 8px; font-size:13px; }
    .inline-form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
</style>
