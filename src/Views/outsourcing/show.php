<?php
$basePath = $basePath ?? '/project/public';
$service = is_array($service ?? null) ? $service : [];
$followups = is_array($followups ?? null) ? $followups : [];
$users = is_array($users ?? null) ? $users : [];
$documentFlowConfig = is_array($documentFlowConfig ?? null) ? $documentFlowConfig : [];
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];
$timesheetSummary = is_array($timesheetSummary ?? null) ? $timesheetSummary : ['total_hours' => 0, 'approved_hours' => 0, 'pending_hours' => 0];
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
$followupIcon = static function (?string $status, ?string $health): string {
    return match (true) {
        $status === 'closed' => '<svg viewBox="0 0 24 24" role="presentation"><path d="M5 12.5l4 4L19 7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        $status === 'observed' => '<svg viewBox="0 0 24 24" role="presentation"><path d="M12 4 20 19H4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 9v4.5M12 17.5v.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        $health === 'red' => '<svg viewBox="0 0 24 24" role="presentation"><path d="M12 4 20 19H4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 9v4.5M12 17.5v.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        $health === 'yellow' => '<svg viewBox="0 0 24 24" role="presentation"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v4M12 16v.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        default => '<svg viewBox="0 0 24 24" role="presentation"><path d="M6 7.5h12M6 12h12M6 16.5h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    };
};
$latestHealth = $followups[0]['service_health'] ?? null;
$projectProgress = isset($service['project_progress']) ? (float) $service['project_progress'] : null;
$approvalState = ($timesheetSummary['pending_hours'] ?? 0) > 0
    ? 'Pendiente'
    : (($timesheetSummary['approved_hours'] ?? 0) > 0 ? 'Aprobado' : 'Sin reportes');
?>

<section class="outsourcing-shell">
    <header class="outsourcing-header">
        <div class="header-identity">
            <span class="header-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M4 6.75A2.75 2.75 0 0 1 6.75 4h10.5A2.75 2.75 0 0 1 20 6.75v10.5A2.75 2.75 0 0 1 17.25 20H6.75A2.75 2.75 0 0 1 4 17.25Z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M8 9.5h8M8 12h5M8 14.5h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </span>
            <div class="header-text">
                <p class="eyebrow">Servicio de outsourcing</p>
                <h2><?= htmlspecialchars($service['talent_name'] ?? 'Talento') ?></h2>
                <small class="section-muted">Cliente: <?= htmlspecialchars($service['client_name'] ?? '') ?> · Proyecto: <?= htmlspecialchars($service['project_name'] ?? 'Sin proyecto') ?></small>
            </div>
        </div>
        <div class="header-actions">
            <a class="action-btn" href="<?= $basePath ?>/outsourcing">
                <span class="btn-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M15 6 9 12l6 6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                Volver
            </a>
            <a class="action-btn ghost" href="#nuevo-seguimiento">
                <span class="btn-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M4 6.5h16M4 12h16M4 17.5h10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </span>
                Nuevo seguimiento
            </a>
            <a class="action-btn ghost" href="#evidencias">
                <span class="btn-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M6 5.5h12v13H6z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M9 9h6M9 12h6M9 15h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </span>
                Evidencias
            </a>
        </div>
    </header>

    <nav class="outsourcing-tabs">
        <a href="#resumen" class="tab-link">
            <span class="btn-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M4.5 6.5h15M4.5 12h15M4.5 17.5h9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </span>
            Resumen
        </a>
        <a href="#seguimientos" class="tab-link">
            <span class="btn-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M6 7.5h12M6 12h12M6 16.5h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </span>
            Seguimientos
        </a>
        <a href="#evidencias" class="tab-link">
            <span class="btn-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M6 5.5h12v13H6z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M9 9h6M9 12h6M9 15h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </span>
            Evidencias
        </a>
    </nav>

    <section class="outsourcing-overview" id="resumen">
        <div class="overview-card">
            <h4>
                <span class="section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M4.5 6.5h15M4.5 12h15M4.5 17.5h9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </span>
                Resumen
            </h4>
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
                <div class="summary-note">
                    <span class="section-muted">Observaciones del servicio</span>
                    <p><?= nl2br(htmlspecialchars((string) ($service['observations'] ?? 'Sin observaciones registradas.'))) ?></p>
                </div>
                <div>
                    <span class="section-muted">Avance del proyecto (manual)</span>
                    <strong><?= $projectProgress !== null ? htmlspecialchars((string) $projectProgress) . '%' : 'Sin proyecto' ?></strong>
                </div>
                <div>
                    <span class="section-muted">Horas reportadas</span>
                    <strong><?= number_format((float) ($timesheetSummary['total_hours'] ?? 0), 1, ',', '.') ?>h</strong>
                </div>
                <div>
                    <span class="section-muted">Estado de aprobación</span>
                    <strong><?= htmlspecialchars($approvalState) ?></strong>
                </div>
            </div>
        </div>
        <div class="overview-card">
            <h4>
                <span class="section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M12 4 20 19H4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M12 9v4.5M12 17.5v.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </span>
                Estado del servicio
            </h4>
            <p class="section-muted">Salud actual según último seguimiento.</p>
            <div class="status-row">
                <span class="status-badge <?= $healthBadge($latestHealth) ?>">
                    <?= htmlspecialchars($healthLabels[$latestHealth ?? ''] ?? 'Sin seguimiento') ?>
                </span>
            </div>
        </div>
        <div class="overview-card">
            <h4>
                <span class="section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M12 4v8l5 3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.6"/>
                    </svg>
                </span>
                Frecuencia de seguimiento
            </h4>
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
            <h4>
                <span class="section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M7.5 4.5h9A2.5 2.5 0 0 1 19 7v9a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 16V7A2.5 2.5 0 0 1 7.5 4.5Z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M9.5 12l1.8 1.8 3.2-3.6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                Estado operativo
            </h4>
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
        <div class="section-head">
            <div class="section-title">
                <span class="section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M6 7.5h12M6 12h12M6 16.5h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </span>
                <div>
                    <p class="eyebrow">Seguimientos periódicos</p>
                    <h3>Bitácora inmutable del servicio</h3>
                    <small class="section-muted">Cada seguimiento registra observaciones obligatorias, salud del servicio y evidencia documental.</small>
                </div>
            </div>
        </div>

        <?php if ($canManage): ?>
            <form method="POST" action="<?= $basePath ?>/outsourcing/<?= (int) ($service['id'] ?? 0) ?>/followups" class="followup-form" id="nuevo-seguimiento">
                <h5>
                    <span class="section-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="presentation">
                            <path d="M4 6.5h16M4 12h16M4 17.5h10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </span>
                    Nuevo seguimiento
                </h5>
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
            <h4 class="section-title" id="evidencias">
                <span class="section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M6 5.5h12v13H6z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M9 9h6M9 12h6M9 15h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </span>
                Evidencias
            </h4>
            <ol class="followup-timeline">
                <?php foreach ($followups as $followup): ?>
                    <li class="timeline-item">
                        <div class="timeline-marker" aria-hidden="true">
                            <?= $followupIcon($followup['followup_status'] ?? null, $followup['service_health'] ?? null) ?>
                        </div>
                        <div class="timeline-card">
                            <header class="followup-header">
                                <div>
                                    <h5>Periodo <?= htmlspecialchars((string) ($followup['period_start'] ?? '')) ?> → <?= htmlspecialchars((string) ($followup['period_end'] ?? '')) ?></h5>
                                    <div class="followup-meta">
                                        <span class="meta-item">Responsable: <?= htmlspecialchars($followup['responsible_name'] ?? 'Sin asignar') ?></span>
                                        <span class="meta-item">Registrado por <?= htmlspecialchars($followup['created_by_name'] ?? 'Sistema') ?></span>
                                        <span class="meta-item"><?= htmlspecialchars($formatTimestamp($followup['created_at'] ?? null)) ?></span>
                                        <span class="meta-item">Estado: <?= htmlspecialchars($followupStatusLabels[$followup['followup_status'] ?? 'open'] ?? 'Abierto') ?></span>
                                        <?php if (!empty($followup['closed_at'])): ?>
                                            <span class="meta-item">Cerrado el <?= htmlspecialchars($formatTimestamp($followup['closed_at'] ?? null)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="followup-actions">
                                    <span class="status-badge <?= $healthBadge((string) ($followup['service_health'] ?? '')) ?>">
                                        <?= htmlspecialchars($healthLabels[(string) ($followup['service_health'] ?? '')] ?? 'Sin estado') ?>
                                    </span>
                                    <?php if ($canManage && ($followup['followup_status'] ?? '') !== 'closed'): ?>
                                        <form method="POST" action="<?= $basePath ?>/outsourcing/<?= (int) ($service['id'] ?? 0) ?>/followups/<?= (int) ($followup['id'] ?? 0) ?>/close" onsubmit="return confirm('¿Cerrar este seguimiento?');">
                                            <button type="submit" class="action-btn small">
                                                <span class="btn-icon" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" role="presentation">
                                                        <path d="M5 12.5l4 4L19 7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </span>
                                                Cerrar
                                            </button>
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
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</section>

<style>
    .outsourcing-shell { display:flex; flex-direction:column; gap:18px; }
    .outsourcing-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border:1px solid var(--border); border-radius:18px; padding:18px; background: var(--bg-card); }
    .header-identity { display:flex; align-items:center; gap:14px; }
    .header-icon { width:56px; height:56px; border-radius:16px; display:flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--primary) 18%, var(--bg-card) 82%); color: var(--primary); }
    .header-icon svg { width:26px; height:26px; }
    .header-text h2 { margin:0; font-size:22px; color: var(--text-main); }
    .header-actions { display:flex; flex-direction:column; gap:10px; align-items:flex-end; }
    .outsourcing-tabs { display:flex; gap:12px; flex-wrap:wrap; }
    .tab-link { text-decoration:none; padding:6px 12px; border-radius:999px; border:1px solid var(--border); background: color-mix(in srgb, var(--bg-card) 85%, var(--bg-app) 15%); color: var(--text-main); font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:6px; }
    .outsourcing-overview { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    .overview-card { border:1px solid var(--border); border-radius:16px; padding:14px; background: var(--bg-card); display:flex; flex-direction:column; gap:8px; }
    .overview-card h4 { display:flex; align-items:center; gap:8px; margin:0; font-size:15px; }
    .summary-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; }
    .summary-grid strong { font-size:14px; color: var(--text-main); }
    .summary-note p { margin:4px 0 0; font-size:13px; color: var(--text-main); }
    .status-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .outsourcing-followups { border:1px solid var(--border); border-radius:18px; padding:18px; background: var(--bg-card); display:flex; flex-direction:column; gap:12px; }
    .section-head { display:flex; justify-content:space-between; align-items:flex-start; }
    .section-title { display:flex; align-items:center; gap:12px; }
    .section-icon { width:36px; height:36px; border-radius:12px; display:flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--bg-card) 70%, var(--bg-app) 30%); color: var(--text-main); }
    .section-icon svg { width:18px; height:18px; }
    .section-title h3 { margin:0; font-size:18px; color: var(--text-main); }
    .section-title .eyebrow { margin:0; text-transform:uppercase; letter-spacing:0.08em; font-size:11px; color: var(--text-muted); }
    .section-title small { color: var(--text-muted); display:block; margin-top:4px; font-size:13px; line-height:1.4; }
    .followup-form { border:1px dashed var(--border); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:12px; background: color-mix(in srgb, var(--bg-card) 90%, var(--bg-app) 10%); }
    .followup-form h5 { display:flex; align-items:center; gap:8px; margin:0; font-size:15px; color: var(--text-main); }
    .followup-form label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-main); }
    .followup-form input,
    .followup-form select,
    .followup-form textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); background: var(--bg-card); color: var(--text-main); }
    .followup-timeline { display:flex; flex-direction:column; gap:16px; list-style:none; padding:0; margin:0; }
    .timeline-item { display:grid; grid-template-columns: 28px 1fr; gap:12px; position:relative; }
    .timeline-item::before { content:""; position:absolute; left:13px; top:30px; bottom:-16px; width:2px; background: color-mix(in srgb, var(--border) 70%, transparent 30%); }
    .timeline-item:last-child::before { display:none; }
    .timeline-marker { width:28px; height:28px; border-radius:50%; border:1px solid var(--border); background: var(--bg-card); display:flex; align-items:center; justify-content:center; color: var(--text-main); }
    .timeline-marker svg { width:16px; height:16px; }
    .timeline-card { border:1px solid var(--border); border-radius:16px; padding:16px; background: color-mix(in srgb, var(--bg-card) 86%, var(--bg-app) 14%); display:flex; flex-direction:column; gap:12px; }
    .followup-header { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
    .followup-meta { display:flex; flex-wrap:wrap; gap:8px 12px; margin-top:6px; color: var(--text-muted); font-size:12px; }
    .meta-item { display:inline-flex; align-items:center; gap:6px; }
    .followup-actions { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
    .followup-body { display:grid; gap:12px; }
    .status-badge { font-size:11px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid transparent; text-transform:uppercase; letter-spacing:0.03em; }
    .status-muted { background: color-mix(in srgb, var(--bg-card) 80%, var(--bg-app) 20%); color: var(--text-muted); border-color: var(--border); }
    .status-success { background: color-mix(in srgb, var(--primary) 16%, var(--bg-card) 84%); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 30%, var(--border) 70%); }
    .status-warning { background: color-mix(in srgb, var(--accent) 16%, var(--bg-card) 84%); color: var(--accent); border-color: color-mix(in srgb, var(--accent) 30%, var(--border) 70%); }
    .status-danger { background: color-mix(in srgb, var(--secondary) 18%, var(--bg-card) 82%); color: var(--secondary); border-color: color-mix(in srgb, var(--secondary) 35%, var(--border) 65%); }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
    .action-btn { background: var(--bg-card); color: var(--text-main); border:1px solid var(--border); border-radius:10px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color: var(--text-main); border-color: var(--primary); }
    .action-btn.ghost { background: color-mix(in srgb, var(--bg-card) 85%, var(--bg-app) 15%); }
    .action-btn.small { padding:6px 8px; font-size:13px; }
    .btn-icon { display:inline-flex; width:16px; height:16px; }
    .btn-icon svg { width:16px; height:16px; }
    .inline-form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
</style>
