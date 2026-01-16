<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$talents = is_array($talents ?? null) ? $talents : [];
$users = is_array($users ?? null) ? $users : [];
$settings = is_array($settings ?? null) ? $settings : ['followup_frequency' => 'monthly'];
$followups = is_array($followups ?? null) ? $followups : [];
$indicators = is_array($indicators ?? null) ? $indicators : [];
$documentFlowConfig = is_array($documentFlowConfig ?? null) ? $documentFlowConfig : [];
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];
$canManage = !empty($canManage);

$frequency = (string) ($settings['followup_frequency'] ?? 'monthly');
$periodStart = (string) ($indicators['period_start'] ?? '');
$periodEnd = (string) ($indicators['period_end'] ?? '');
$loggedHours = $indicators['logged_hours'] ?? null;
$lastFollowupStatus = (string) ($indicators['last_followup_status'] ?? '');
$lastFollowupAt = (string) ($indicators['last_followup_at'] ?? '');
$openRisks = (int) ($indicators['open_risks'] ?? 0);
$activeTalents = (int) ($indicators['active_talents'] ?? 0);

$frequencyLabels = [
    'weekly' => 'Semanal',
    'biweekly' => 'Quincenal',
    'monthly' => 'Mensual',
];
$statusLabels = [
    'green' => 'GREEN (Normal)',
    'yellow' => 'YELLOW (En riesgo)',
    'red' => 'RED (Crítico)',
];
$statusBadge = static function (string $status): string {
    return match ($status) {
        'green' => 'status-success',
        'yellow' => 'status-warning',
        'red' => 'status-danger',
        default => 'status-muted',
    };
};
$assignmentLabels = [
    'active' => 'Activo',
    'paused' => 'En pausa',
    'removed' => 'Retirado',
];
$assignmentBadge = static function (string $status): string {
    return match ($status) {
        'active' => 'status-success',
        'paused' => 'status-warning',
        'removed' => 'status-danger',
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
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Control de outsourcing</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Gestión de talento, seguimiento de servicio y evidencias auditables.</small>
        </div>
        <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>">Volver a documentos</a>
    </header>

    <?php
    $activeTab = 'outsourcing';
    require __DIR__ . '/_tabs.php';
    ?>

    <section class="outsourcing-indicators">
        <div>
            <p class="eyebrow">Indicadores informativos</p>
            <h4>Contexto operativo del servicio</h4>
        </div>
        <div class="context-grid">
            <div class="context-item">
                <span>Talentos activos</span>
                <strong><?= $activeTalents ?></strong>
            </div>
            <div class="context-item">
                <span>Horas registradas</span>
                <strong><?= $loggedHours !== null ? number_format((float) $loggedHours, 1) : 'N/A' ?></strong>
                <small><?= $periodStart && $periodEnd ? htmlspecialchars($periodStart . ' → ' . $periodEnd) : 'Sin periodo disponible' ?></small>
            </div>
            <div class="context-item">
                <span>Último seguimiento</span>
                <strong><?= $lastFollowupStatus !== '' ? htmlspecialchars($statusLabels[$lastFollowupStatus] ?? strtoupper($lastFollowupStatus)) : 'Sin seguimiento' ?></strong>
                <small><?= htmlspecialchars($formatTimestamp($lastFollowupAt)) ?></small>
            </div>
            <div class="context-item">
                <span>Riesgos abiertos</span>
                <strong><?= $openRisks ?></strong>
            </div>
        </div>
    </section>

    <section class="outsourcing-settings">
        <div>
            <p class="eyebrow">Seguimiento periódico</p>
            <h4>Frecuencia de monitoreo</h4>
            <small class="section-muted">Define la cadencia de control del servicio.</small>
        </div>
        <?php if ($canManage): ?>
            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/outsourcing/settings" class="settings-form">
                <label>Frecuencia
                    <select name="followup_frequency">
                        <?php foreach ($frequencyLabels as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= $frequency === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="action-btn primary">Guardar frecuencia</button>
            </form>
        <?php else: ?>
            <p class="section-muted">Frecuencia actual: <?= htmlspecialchars($frequencyLabels[$frequency] ?? 'Mensual') ?></p>
        <?php endif; ?>
    </section>

    <section class="outsourcing-assignments">
        <div>
            <p class="eyebrow">Talento asignado</p>
            <h4>Equipo responsable del servicio</h4>
        </div>
        <?php if (empty($assignments)): ?>
            <p class="section-muted">No hay talentos asignados todavía.</p>
        <?php else: ?>
            <div class="assignment-list">
                <?php foreach ($assignments as $assignment): ?>
                    <div class="assignment-card">
                        <div>
                            <strong><?= htmlspecialchars($assignment['talent_name'] ?? 'Talento') ?></strong>
                            <div class="section-muted"><?= htmlspecialchars($assignment['role'] ?? '') ?></div>
                            <small class="section-muted"><?= htmlspecialchars($assignment['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($assignment['end_date'] ?? 'N/A') ?></small>
                        </div>
                        <div class="assignment-meta">
                            <span class="status-badge <?= $assignmentBadge((string) ($assignment['assignment_status'] ?? 'active')) ?>">
                                <?= htmlspecialchars($assignmentLabels[(string) ($assignment['assignment_status'] ?? 'active')] ?? 'Activo') ?>
                            </span>
                            <small class="section-muted">Dedicación <?= htmlspecialchars((string) ($assignment['allocation_percent'] ?? 0)) ?>% · <?= htmlspecialchars((string) ($assignment['weekly_hours'] ?? 0)) ?>h/sem</small>
                        </div>
                        <?php if ($canManage): ?>
                            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/outsourcing/assignments/<?= (int) ($assignment['id'] ?? 0) ?>/status" class="assignment-status-form">
                                <select name="assignment_status">
                                    <option value="active" <?= ($assignment['assignment_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Activo</option>
                                    <option value="paused" <?= ($assignment['assignment_status'] ?? '') === 'paused' ? 'selected' : '' ?>>En pausa</option>
                                    <option value="removed" <?= ($assignment['assignment_status'] ?? '') === 'removed' ? 'selected' : '' ?>>Retirado</option>
                                </select>
                                <button type="submit" class="action-btn small">Actualizar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/outsourcing/assignments" method="POST" class="assignment-form">
                <h5>Nueva asignación de talento</h5>
                <input type="hidden" name="redirect_to" value="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/outsourcing">
                <label>Talento
                    <select name="talent_id" required>
                        <option value="">Selecciona un talento</option>
                        <?php foreach ($talents as $talent): ?>
                            <option value="<?= (int) $talent['id'] ?>">
                                <?= htmlspecialchars($talent['name'] ?? '') ?> (<?= htmlspecialchars($talent['role'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Rol en el servicio
                    <input name="role" required placeholder="Ej. Analista de servicio">
                </label>
                <div class="grid">
                    <label>Inicio
                        <input type="date" name="start_date">
                    </label>
                    <label>Fin
                        <input type="date" name="end_date">
                    </label>
                </div>
                <div class="grid">
                    <label>Porcentaje de dedicación (%)
                        <input type="number" step="0.1" name="allocation_percent" placeholder="Ej. 50">
                    </label>
                    <label>Horas semanales
                        <input type="number" step="0.1" name="weekly_hours" placeholder="Ej. 20">
                    </label>
                </div>
                <div class="grid">
                    <label>Estado
                        <select name="assignment_status" required>
                            <option value="active">Activo</option>
                            <option value="paused">En pausa</option>
                            <option value="removed">Retirado</option>
                        </select>
                    </label>
                    <label>Tipo de costo
                        <select name="cost_type">
                            <option value="por_horas">Por horas</option>
                            <option value="fijo">Fijo</option>
                        </select>
                    </label>
                </div>
                <label>Valor
                    <input type="number" step="0.01" name="cost_value" placeholder="0">
                </label>
                <div class="checkbox-grid">
                    <label><input type="checkbox" name="is_external" value="1"> Es externo</label>
                    <span class="section-muted">El reporte y la aprobación de horas se controlan desde el talento asignado.</span>
                </div>
                <button type="submit" class="action-btn primary">Guardar asignación</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="outsourcing-followups">
        <div>
            <p class="eyebrow">Seguimientos</p>
            <h4>Bitácora inmutable de servicio</h4>
            <small class="section-muted">Los seguimientos guardados no pueden editarse ni eliminarse.</small>
        </div>

        <?php if ($canManage): ?>
            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/outsourcing/followups" class="followup-form">
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
                <label>Estado del servicio
                    <select name="service_status" required>
                        <option value="green">GREEN (Normal)</option>
                        <option value="yellow">YELLOW (En riesgo)</option>
                        <option value="red">RED (Crítico)</option>
                    </select>
                </label>
                <label>Observaciones / análisis
                    <textarea name="observations" rows="4" required></textarea>
                </label>
                <label>Decisiones o acciones tomadas
                    <textarea name="decisions" rows="3" required></textarea>
                </label>
                <button type="submit" class="action-btn primary">Guardar seguimiento</button>
            </form>
        <?php endif; ?>

        <?php if (empty($followups)): ?>
            <p class="section-muted">Aún no hay seguimientos registrados.</p>
        <?php else: ?>
            <div class="followup-timeline">
                <?php foreach ($followups as $followup): ?>
                    <article class="followup-card">
                        <div class="followup-marker" aria-hidden="true"></div>
                        <header class="followup-header">
                            <div>
                                <h5>Periodo <?= htmlspecialchars((string) ($followup['period_start'] ?? '')) ?> → <?= htmlspecialchars((string) ($followup['period_end'] ?? '')) ?></h5>
                                <p class="section-muted">Responsable: <?= htmlspecialchars($followup['responsible_name'] ?? 'Sin asignar') ?></p>
                                <p class="section-muted">Registrado por <?= htmlspecialchars($followup['created_by_name'] ?? 'Sistema') ?> · <?= htmlspecialchars($formatTimestamp($followup['created_at'] ?? null)) ?></p>
                            </div>
                            <span class="status-badge <?= $statusBadge((string) ($followup['service_status'] ?? '')) ?>">
                                <?= htmlspecialchars($statusLabels[(string) ($followup['service_status'] ?? '')] ?? 'Sin estado') ?>
                            </span>
                        </header>
                        <div class="followup-body">
                            <div>
                                <h6>Observaciones</h6>
                                <p><?= nl2br(htmlspecialchars((string) ($followup['observations'] ?? ''))) ?></p>
                            </div>
                            <div>
                                <h6>Decisiones / acciones</h6>
                                <p><?= nl2br(htmlspecialchars((string) ($followup['decisions'] ?? ''))) ?></p>
                            </div>
                        </div>
                        <?php if (!empty($followup['document_node'])): ?>
                            <?php
                            $documentFlowId = 'outsourcing-followup-' . (int) ($followup['id'] ?? 0);
                            $documentNode = $followup['document_node'];
                            $documentExpectedDocs = [];
                            $documentTagOptions = $documentFlowConfig['tag_options'] ?? [];
                            $documentKeyTags = [];
                            $documentCanManage = $canManage;
                            $documentMode = 'outsourcing';
                            $documentProjectId = (int) ($project['id'] ?? 0);
                            $documentBasePath = $basePath;
                            $documentCurrentUser = $currentUser;
                            $documentContextLabel = 'SEGUIMIENTO';
                            $documentContextDescription = 'Evidencias y documentos de soporte para el seguimiento del servicio.';
                            $documentExpectedTitle = 'Documentos sugeridos por seguimiento';
                            $documentExpectedDescription = 'Adjunta reportes, actas, evidencias o reportes de servicio relacionados.';
                            require __DIR__ . '/document_flow.php';
                            ?>
                        <?php else: ?>
                            <p class="section-muted">No se encontró la carpeta documental asociada.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .project-title-block { display:flex; flex-direction:column; gap:8px; }
    .project-title-block h2 { margin:0; color: var(--text-strong); }
    .outsourcing-indicators,
    .outsourcing-settings,
    .outsourcing-assignments,
    .outsourcing-followups { border:1px solid var(--border); border-radius:16px; padding:16px; background:#fff; display:flex; flex-direction:column; gap:12px; }
    .context-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .context-item { border:1px solid var(--border); border-radius:12px; padding:10px; background:#f8fafc; display:flex; flex-direction:column; gap:4px; }
    .context-item span { font-size:12px; text-transform:uppercase; color: var(--muted); font-weight:700; }
    .context-item strong { font-size:16px; color: var(--text-strong); }
    .settings-form { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
    .settings-form label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-strong); }
    .settings-form select { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    .assignment-list { display:flex; flex-direction:column; gap:10px; }
    .assignment-card { border:1px solid var(--border); border-radius:12px; padding:12px; display:flex; flex-wrap:wrap; gap:12px; justify-content:space-between; align-items:flex-start; background:#f8fafc; }
    .assignment-meta { display:flex; flex-direction:column; gap:6px; align-items:flex-end; }
    .assignment-status-form { display:flex; gap:8px; align-items:center; }
    .assignment-form, .followup-form { border:1px dashed var(--border); border-radius:12px; padding:14px; display:flex; flex-direction:column; gap:12px; background:#fff; }
    .assignment-form label, .followup-form label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-strong); }
    .assignment-form input,
    .assignment-form select,
    .followup-form input,
    .followup-form select,
    .followup-form textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
    .checkbox-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:8px; }
    .followup-timeline { display:flex; flex-direction:column; gap:16px; position:relative; padding-left:18px; }
    .followup-timeline::before { content:""; position:absolute; left:7px; top:0; bottom:0; width:2px; background: color-mix(in srgb, var(--primary) 30%, transparent); }
    .followup-card { border:1px solid var(--border); border-radius:16px; padding:16px; background:#f8fafc; display:flex; flex-direction:column; gap:12px; position:relative; }
    .followup-marker { width:14px; height:14px; border-radius:50%; background: var(--primary); position:absolute; left:-26px; top:18px; }
    .followup-header { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
    .followup-body { display:grid; gap:12px; }
    .followup-body h6 { margin:0 0 6px 0; }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid transparent; }
    .status-muted { background:#f3f4f6; color:#374151; border-color:#e5e7eb; }
    .status-success { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
    .status-warning { background:#fef9c3; color:#854d0e; border-color:#fde047; }
    .status-danger { background:#fee2e2; color:#991b1b; border-color:#fecdd3; }
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color:#fff; border-color: var(--primary); }
    .action-btn.small { padding:6px 8px; font-size:13px; }
</style>
