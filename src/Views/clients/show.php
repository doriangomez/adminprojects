<?php
$projects = is_array($projects ?? null) ? $projects : [];
$snapshot = is_array($snapshot ?? null) ? $snapshot : [];

$totalProjects = (int) ($snapshot['total'] ?? count($projects));
$avgProgress = (float) ($snapshot['avg_progress'] ?? 0);
$atRiskProjects = (int) ($snapshot['at_risk'] ?? 0);
$closedProjects = (int) ($snapshot['closed'] ?? 0);

$healthScoreMap = [
    'on_track' => 90,
    'at_risk' => 55,
    'critical' => 20,
    'blocked' => 15,
    'completed' => 100,
];
$statusDistribution = [];
$healthDistribution = ['on_track' => 0, 'at_risk' => 0, 'critical' => 0];
$healthScoreTotal = 0;

foreach ($projects as $project) {
    $statusKey = trim((string) ($project['status_label'] ?? $project['status'] ?? 'Sin estado'));
    if ($statusKey === '') {
        $statusKey = 'Sin estado';
    }
    $statusDistribution[$statusKey] = ($statusDistribution[$statusKey] ?? 0) + 1;

    $healthKey = (string) ($project['health'] ?? 'at_risk');
    if (!isset($healthDistribution[$healthKey])) {
        $healthDistribution[$healthKey] = 0;
    }
    $healthDistribution[$healthKey]++;

    $healthScoreTotal += $healthScoreMap[$healthKey] ?? 50;
}

arsort($statusDistribution);
$healthAverage = $totalProjects > 0 ? (int) round($healthScoreTotal / $totalProjects) : 0;
$healthCircumference = 2 * pi() * 52;
$healthOffset = $healthCircumference - (($healthAverage / 100) * $healthCircumference);
$riskRatio = $totalProjects > 0 ? (int) round(($atRiskProjects / $totalProjects) * 100) : 0;
$riskTone = $riskRatio >= 45 ? 'danger' : ($riskRatio >= 20 ? 'warning' : 'success');
$activeProjects = max(0, $totalProjects - $closedProjects);
?>

<style>
    .client-dashboard { display:flex; flex-direction:column; gap:16px; }
    .client-dashboard .hero { padding:18px; border-radius:14px; background:linear-gradient(145deg, color-mix(in srgb, var(--primary) 20%, var(--surface)), color-mix(in srgb, var(--accent) 8%, var(--surface))); border:1px solid color-mix(in srgb, var(--primary) 30%, var(--border)); }
    .executive-kpi-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .executive-kpi { border:1px solid var(--border); border-radius:12px; padding:12px; background:color-mix(in srgb, var(--surface) 88%, var(--background) 12%); }
    .executive-kpi strong { font-size:26px; line-height:1.1; display:block; margin-top:6px; }
    .dashboard-grid { display:grid; grid-template-columns: 2fr 1fr; gap:16px; }
    .metric-panel { border:1px solid var(--border); border-radius:12px; padding:14px; background:color-mix(in srgb, var(--surface) 92%, var(--background) 8%); }
    .metric-title { margin:0 0 10px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-secondary); font-weight:700; }
    .health-gauge { display:flex; gap:14px; align-items:center; }
    .health-gauge svg { width:128px; height:128px; }
    .health-gauge .track { fill:none; stroke:color-mix(in srgb, var(--border) 60%, var(--background)); stroke-width:10; }
    .health-gauge .value { fill:none; stroke:var(--primary); stroke-width:10; stroke-linecap:round; transform:rotate(-90deg); transform-origin:64px 64px; }
    .progress-block { display:flex; flex-direction:column; gap:8px; }
    .progress-track { height:12px; border-radius:999px; overflow:hidden; background:color-mix(in srgb, var(--background) 85%, var(--surface)); border:1px solid var(--border); }
    .progress-track .fill { display:block; height:100%; background:linear-gradient(90deg, var(--info), var(--success)); }
    .status-bars { display:flex; flex-direction:column; gap:8px; }
    .status-bar-item { display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center; }
    .status-bar-item .bar { height:9px; border-radius:999px; background:color-mix(in srgb, var(--background) 88%, var(--surface)); overflow:hidden; }
    .status-bar-item .bar span { display:block; height:100%; background:color-mix(in srgb, var(--primary) 50%, var(--accent) 50%); }
    .risk-card { border-color: color-mix(in srgb, var(--danger) 30%, var(--border)); }
    .risk-indicator { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:10px; }
    .risk-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-weight:700; border:1px solid transparent; }
    .risk-pill.success { background:color-mix(in srgb, var(--success) 15%, var(--surface)); border-color:color-mix(in srgb, var(--success) 35%, var(--surface)); color:var(--success); }
    .risk-pill.warning { background:color-mix(in srgb, var(--warning) 15%, var(--surface)); border-color:color-mix(in srgb, var(--warning) 35%, var(--surface)); color:var(--warning); }
    .risk-pill.danger { background:color-mix(in srgb, var(--danger) 15%, var(--surface)); border-color:color-mix(in srgb, var(--danger) 35%, var(--surface)); color:var(--danger); }
    .btn.executive-danger {
        background:#ef4444;
        border:1px solid #dc2626;
        color:#ffffff;
        border-style:solid;
        font-weight:700;
        box-shadow:0 4px 10px color-mix(in srgb, #ef4444 30%, transparent);
    }
    .btn.executive-danger:hover {
        background:#dc2626;
        color:#ffffff;
        border-color:#b91c1c;
    }
    #confirm-delete-btn { min-width:220px; font-weight:700; border-style:solid; }
    #confirm-delete-btn[data-mode="delete"] { background:#dc2626; color:#ffffff; border-color:#b91c1c; }
    #confirm-delete-btn[data-mode="delete"]:hover:not(:disabled) { background:#b91c1c; color:#ffffff; border-color:#991b1b; }
    #confirm-delete-btn[data-mode="inactivate"] { background:color-mix(in srgb, var(--warning) 18%, var(--surface)); color:var(--warning); border-color:color-mix(in srgb, var(--warning) 42%, var(--border)); }
    #confirm-delete-btn[data-mode="inactivate"]:hover:not(:disabled) { background:color-mix(in srgb, var(--warning) 26%, var(--surface)); }
    #confirm-delete-btn:disabled { opacity:1; color:var(--text-secondary); background:color-mix(in srgb, var(--surface) 92%, var(--background) 8%); border-color:var(--border); cursor:not-allowed; }
    @media (max-width: 980px) { .dashboard-grid { grid-template-columns: 1fr; } }
</style>

<div class="toolbar">
    <div>
        <a href="/clients" class="btn ghost">← Volver</a>
        <h3 style="margin:8px 0 0 0;">Detalle ejecutivo del cliente</h3>
        <p style="margin:4px 0 0 0; color: var(--text-secondary);">Vista estratégica de salud, ejecución y riesgos de la cuenta.</p>
    </div>
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <?php if($canManage): ?>
            <a class="btn secondary" href="/clients/<?= (int) $client['id'] ?>/edit">Editar</a>
        <?php endif; ?>
        <?php if($auth->canDeleteClients()): ?>
            <button type="button" class="btn executive-danger" data-open-action="delete">Eliminar cliente</button>
        <?php endif; ?>
        <?php if($canInactivate): ?>
            <button type="button" class="btn warning" data-open-action="inactivate">Inactivar cliente</button>
        <?php endif; ?>
    </div>
</div>

<section class="client-dashboard">
    <div class="hero" data-aos="fade-up">
        <div class="toolbar" style="margin:0 0 12px 0;">
            <div>
                <p class="badge neutral" style="margin:0;">Información estratégica</p>
                <h4 style="margin:6px 0 0 0;"><?= htmlspecialchars($client['name']) ?></h4>
                <p style="margin:6px 0 0 0; color:var(--text-secondary);">Sector <?= htmlspecialchars($client['sector_label'] ?? $client['sector_code']) ?> · <?= htmlspecialchars($client['category_label'] ?? $client['category_code']) ?></p>
            </div>
            <?php if(!empty($client['logo_path'])): ?>
                <img src="<?= $basePath . $client['logo_path'] ?>" alt="Logo de <?= htmlspecialchars($client['name']) ?>" style="width:66px; height:66px; object-fit:contain; border:1px solid var(--border); border-radius:12px; background:var(--surface);">
            <?php endif; ?>
        </div>

        <div class="executive-kpi-grid">
            <article class="executive-kpi">
                <small class="metric-title">Salud promedio</small>
                <strong><?= $healthAverage ?>%</strong>
            </article>
            <article class="executive-kpi">
                <small class="metric-title">Avance agregado</small>
                <strong><?= number_format($avgProgress, 1) ?>%</strong>
            </article>
            <article class="executive-kpi">
                <small class="metric-title">Proyectos activos</small>
                <strong><?= $activeProjects ?></strong>
            </article>
            <article class="executive-kpi">
                <small class="metric-title">Riesgo cartera</small>
                <strong><?= $riskRatio ?>%</strong>
            </article>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="section-grid" style="gap:16px;">
            <article class="card" data-aos="fade-up" data-aos-delay="30">
                <div class="toolbar">
                    <div>
                        <p class="badge neutral" style="margin:0;">Operación</p>
                        <h4 style="margin:4px 0 0 0;">Ejecución consolidada</h4>
                    </div>
                </div>
                <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr; gap:12px;">
                    <div class="metric-panel">
                        <p class="metric-title">Gauge de salud</p>
                        <div class="health-gauge">
                            <svg viewBox="0 0 128 128" role="img" aria-label="Salud promedio del cliente">
                                <circle class="track" cx="64" cy="64" r="52"></circle>
                                <circle class="value" cx="64" cy="64" r="52" stroke-dasharray="<?= $healthCircumference ?>" stroke-dashoffset="<?= $healthOffset ?>"></circle>
                                <text x="64" y="69" text-anchor="middle" font-size="24" font-weight="700" fill="var(--text-primary)"><?= $healthAverage ?>%</text>
                            </svg>
                            <p style="margin:0; color:var(--text-secondary); line-height:1.45;">Indicador ejecutivo compuesto a partir de la salud de proyectos vinculados.</p>
                        </div>
                    </div>
                    <div class="metric-panel">
                        <p class="metric-title">Progreso agregado</p>
                        <div class="progress-block">
                            <div style="display:flex; justify-content:space-between; font-weight:600;">
                                <span>Total ejecución</span>
                                <span><?= number_format($avgProgress, 1) ?>%</span>
                            </div>
                            <div class="progress-track"><span class="fill" style="width: <?= max(0, min(100, $avgProgress)) ?>%"></span></div>
                            <small style="color:var(--text-secondary);">Promedio simple de avance entre todos los proyectos vinculados.</small>
                        </div>
                    </div>
                </div>
            </article>

            <article class="card" data-aos="fade-up" data-aos-delay="60">
                <div class="toolbar">
                    <div>
                        <p class="badge neutral" style="margin:0;">Proyectos vinculados</p>
                        <h4 style="margin:4px 0 0 0;">Distribución por estado</h4>
                    </div>
                </div>
                <div class="status-bars">
                    <?php if (!empty($statusDistribution)): ?>
                        <?php foreach ($statusDistribution as $statusLabel => $count): ?>
                            <?php $percent = $totalProjects > 0 ? round(($count / $totalProjects) * 100, 1) : 0; ?>
                            <div class="status-bar-item">
                                <div>
                                    <div style="display:flex; justify-content:space-between; gap:8px; margin-bottom:5px;">
                                        <strong><?= htmlspecialchars($statusLabel) ?></strong>
                                        <small style="color:var(--text-secondary);"><?= (int) $count ?> · <?= $percent ?>%</small>
                                    </div>
                                    <div class="bar"><span style="width: <?= $percent ?>%"></span></div>
                                </div>
                                <span class="badge neutral"><?= (int) $count ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="margin:0; color:var(--text-secondary);">Sin proyectos para graficar.</p>
                    <?php endif; ?>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Estado</th>
                            <th>Salud</th>
                            <th>Prioridad</th>
                            <th>Avance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($projects as $project): ?>
                            <tr>
                                <td><?= htmlspecialchars($project['name']) ?></td>
                                <td><?= htmlspecialchars($project['status_label'] ?? $project['status']) ?></td>
                                <td><span class="badge <?= $project['health'] === 'on_track' ? 'success' : ($project['health'] === 'at_risk' ? 'warning' : 'danger') ?>"><?= htmlspecialchars($project['health_label'] ?? $project['health']) ?></span></td>
                                <td><span class="pill <?= htmlspecialchars($project['priority']) ?>"><?= htmlspecialchars($project['priority_label'] ?? $project['priority']) ?></span></td>
                                <td><?= number_format((float) $project['progress'], 1) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </article>
        </div>

        <div class="section-grid" style="gap:16px;">
            <article class="card risk-card" data-aos="fade-up" data-aos-delay="30">
                <p class="badge danger" style="margin:0;">Riesgos</p>
                <h4 style="margin:6px 0 0 0;">Indicador visual de riesgo</h4>
                <div class="risk-indicator">
                    <div>
                        <p style="margin:0; color:var(--text-secondary);">Proyectos en riesgo/críticos</p>
                        <strong style="font-size:28px;"><?= $atRiskProjects ?>/<?= $totalProjects ?></strong>
                    </div>
                    <span class="risk-pill <?= $riskTone ?>"><?= $riskRatio ?>%</span>
                </div>
                <div class="progress-track" style="margin-top:10px;"><span class="fill" style="width: <?= max(0, min(100, $riskRatio)) ?>%; background: linear-gradient(90deg, var(--success), var(--warning), var(--danger));"></span></div>
                <p style="margin:10px 0 0 0; color:var(--text-secondary); line-height:1.5;">Riesgo reportado: <strong><?= htmlspecialchars($client['risk_label'] ?? ($client['risk_code'] ?? 'Sin definir')) ?></strong>.</p>
            </article>

            <article class="card" data-aos="fade-up" data-aos-delay="60">
                <p class="badge neutral" style="margin:0;">Información estratégica</p>
                <h4 style="margin:6px 0 10px 0;">Ficha ejecutiva</h4>
                <div class="grid tight" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                    <div><p class="muted">Prioridad</p><span class="pill <?= htmlspecialchars($client['priority']) ?>"><?= htmlspecialchars($client['priority_label'] ?? ucfirst($client['priority'])) ?></span></div>
                    <div><p class="muted">Estado</p><span class="badge neutral"><?= htmlspecialchars($client['status_label'] ?? $client['status_code']) ?></span></div>
                    <div><p class="muted">PM a cargo</p><strong><?= htmlspecialchars($client['pm_name'] ?? 'Sin asignar') ?></strong></div>
                    <div><p class="muted">Área</p><strong><?= htmlspecialchars($client['area_label'] ?? ($client['area_code'] ?? 'No registrada')) ?></strong></div>
                    <div><p class="muted">Etiquetas</p><strong><?= htmlspecialchars($client['tags'] ?? 'Sin etiquetas') ?></strong></div>
                </div>
                <p class="muted" style="margin:12px 0 4px 0;">Contexto operativo</p>
                <p style="margin:0; line-height:1.5;"><?= nl2br(htmlspecialchars($client['operational_context'] ?? 'Sin información operativa')) ?></p>
            </article>

            <article class="card" data-aos="fade-up" data-aos-delay="90">
                <p class="badge neutral" style="margin:0;">Feedback continuo</p>
                <h4 style="margin:6px 0 10px 0;">Percepción y señales</h4>
                <div class="grid tight" style="grid-template-columns: repeat(2, minmax(120px, 1fr));">
                    <div class="kpi"><div class="kpi-body"><span class="label">Satisfacción</span><span class="value" style="font-size:26px;"><?= $client['satisfaction'] !== null ? (int) $client['satisfaction'] : '-' ?></span></div></div>
                    <div class="kpi"><div class="kpi-body"><span class="label">NPS</span><span class="value" style="font-size:26px;"><?= $client['nps'] !== null ? (int) $client['nps'] : '-' ?></span></div></div>
                </div>
                <p style="margin:10px 0 4px 0;">Observaciones actuales</p>
                <p style="margin:0; line-height:1.5;"><?= nl2br(htmlspecialchars($client['feedback_notes'] ?? 'Sin observaciones registradas')) ?></p>
                <hr style="margin:14px 0; border:1px solid var(--border);">
                <p style="margin:0 0 4px 0;">Historial</p>
                <p style="margin:0; line-height:1.5;"><?= nl2br(htmlspecialchars($client['feedback_history'] ?? 'Aún no hay historial documentado')) ?></p>
            </article>
        </div>
    </div>
</section>

<?php if($auth->canDeleteClients()): ?>
    <div id="delete-modal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:color-mix(in srgb, var(--text-primary) 45%, var(--background)); align-items:center; justify-content:center; padding:16px;">
        <div class="card" style="max-width:560px; width:100%; max-height:90vh; overflow:auto; border:1px solid color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); box-shadow:0 20px 40px color-mix(in srgb, var(--text-primary) 18%, var(--background));">
            <div class="toolbar">
                <div style="display:flex; gap:10px; align-items:flex-start;">
                    <span aria-hidden="true" style="width:36px; height:36px; border-radius:12px; background:color-mix(in srgb, var(--danger) 12%, var(--surface) 88%); color:var(--danger); border:1px solid color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); display:inline-flex; align-items:center; justify-content:center; font-weight:800;">!</span>
                    <div>
                        <p class="badge danger" style="margin:0;" data-modal-context>Acción crítica</p>
                        <h4 style="margin:4px 0 0 0;" data-modal-title>Eliminar cliente</h4>
                        <p style="margin:4px 0 0 0; color:var(--text-secondary);" data-modal-subtitle>Esta acción es irreversible y eliminará en cascada proyectos, asignaciones de talento, timesheets, costos y adjuntos asociados.</p>
                    </div>
                </div>
                <button type="button" class="btn ghost" data-close-delete aria-label="Cerrar" style="color:var(--text-secondary);">✕</button>
            </div>
            <form method="POST" action="/clients/delete" class="grid" style="gap:12px; grid-template-columns:1fr;" id="delete-form">
                <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
                <input type="hidden" name="math_operand1" id="math_operand1" value="<?= $mathOperand1 ?>">
                <input type="hidden" name="math_operand2" id="math_operand2" value="<?= $mathOperand2 ?>">
                <input type="hidden" name="math_operator" id="math_operator" value="<?= $mathOperator ?>">
                <input type="hidden" name="force_delete" id="force_delete" value="1">
                <div id="dependency-notice" style="display: none; padding: 10px 12px; border:1px solid color-mix(in srgb, var(--warning) 40%, var(--surface) 60%); background:color-mix(in srgb, var(--warning) 12%, var(--surface) 88%); border-radius:10px; color:var(--warning); font-weight:600;">El cliente tiene dependencias activas. La eliminación permanente las borrará en cascada.</div>
                <div>
                    <p style="margin:0 0 4px 0; color:var(--text-secondary); font-weight:600;">Confirmación obligatoria</p>
                    <p style="margin:0 0 8px 0; color:var(--text-secondary);">Resuelve la siguiente operación para confirmar. Solo los administradores pueden ejecutar esta acción.</p>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <div style="flex:1;">
                            <div style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:color-mix(in srgb, var(--surface) 92%, var(--background) 8%); font-weight:700;">
                                <?= $mathOperand1 ?> <?= $mathOperator ?> <?= $mathOperand2 ?> =
                            </div>
                        </div>
                        <input type="number" name="math_result" id="math_result" inputmode="numeric" aria-label="Resultado de la operación" placeholder="Resultado" style="width:160px; max-width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--border);">
                    </div>
                </div>
                <div id="action-feedback" style="display:none; padding:10px 12px; border-radius:10px; border:1px solid color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); background:color-mix(in srgb, var(--danger) 12%, var(--surface) 88%); color:var(--danger); font-weight:600;"></div>
                <div style="display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn ghost" data-close-delete>Cancelar</button>
                    <button type="submit" class="btn ghost danger" id="confirm-delete-btn" disabled>Eliminar permanentemente</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const modal = document.getElementById('delete-modal');
            const openButtons = document.querySelectorAll('[data-open-action]');
            const closeButtons = document.querySelectorAll('[data-close-delete]');
            const resultInput = document.getElementById('math_result');
            const confirmButton = document.getElementById('confirm-delete-btn');
            const form = document.getElementById('delete-form');
            const operand1 = Number(document.getElementById('math_operand1')?.value || 0);
            const operand2 = Number(document.getElementById('math_operand2')?.value || 0);
            const operator = (document.getElementById('math_operator')?.value || '').trim();
            const expected = operator === '+' ? operand1 + operand2 : operand1 - operand2;
            const dependencyNotice = document.getElementById('dependency-notice');
            const actionFeedback = document.getElementById('action-feedback');
            const modalTitle = document.querySelector('[data-modal-title]');
            const modalSubtitle = document.querySelector('[data-modal-subtitle]');
            const modalContext = document.querySelector('[data-modal-context]');
            const clientId = <?= (int) $client['id'] ?>;
            const hasDependencies = <?= $dependencies['has_dependencies'] ? 'true' : 'false' ?>;
            const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
            const forceDeleteInput = document.getElementById('force_delete');
            const dependencyMessage = 'El cliente tiene dependencias activas. La eliminación permanente borrará todo lo relacionado de forma definitiva.';

            const actions = {
                delete: {
                    title: 'Eliminar cliente',
                    subtitle: 'Esta acción elimina definitivamente la ficha. Los administradores pueden forzar la eliminación incluso con dependencias activas.',
                    context: 'Acción crítica',
                    actionUrl: '/clients/delete',
                    buttonLabel: 'Eliminar permanentemente',
                    buttonMode: 'delete'
                },
                inactivate: {
                    title: 'Inactivar cliente',
                    subtitle: 'Se deshabilita el cliente y se conserva la información asociada.',
                    context: 'Acción segura',
                    actionUrl: `/clients/${clientId}/inactivate`,
                    buttonLabel: 'Inactivar cliente',
                    buttonMode: 'inactivate'
                }
            };

            let currentAction = hasDependencies && !isAdmin ? 'inactivate' : 'delete';

            const syncState = () => {
                if (!resultInput || !confirmButton) return;
                const current = Number(resultInput.value.trim());
                const isValid = !Number.isNaN(current) && current === expected;
                confirmButton.disabled = !isValid;
            };

            const setAction = (action) => {
                currentAction = action;
                const config = actions[action];
                if (!config) return;

                if (modalTitle) modalTitle.textContent = config.title;
                if (modalSubtitle) modalSubtitle.textContent = config.subtitle;
                if (modalContext) modalContext.textContent = config.context;
                if (confirmButton) {
                    confirmButton.textContent = config.buttonLabel;
                    confirmButton.setAttribute('data-mode', config.buttonMode || 'delete');
                }
                if (form) {
                    form.setAttribute('action', config.actionUrl);
                }

                if (dependencyNotice) {
                    dependencyNotice.textContent = dependencyMessage;
                    dependencyNotice.style.display = hasDependencies || action === 'inactivate' ? 'block' : 'none';
                }

                if (forceDeleteInput) {
                    forceDeleteInput.value = action === 'delete' ? '1' : '0';
                }
            };

            openButtons.forEach((btn) => btn.addEventListener('click', (event) => {
                if (!modal) return;
                const action = event.currentTarget?.getAttribute('data-open-action') || currentAction;
                setAction(action);
                modal.style.display = 'flex';
                if (resultInput) {
                    resultInput.value = '';
                }
                actionFeedback.style.display = 'none';
                actionFeedback.textContent = '';
                resultInput?.focus();
                syncState();
            }));

            closeButtons.forEach((btn) => btn.addEventListener('click', () => {
                if (modal) {
                    modal.style.display = 'none';
                }
            }));

            resultInput?.addEventListener('input', syncState);

            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!form) return;
                actionFeedback.style.display = 'none';
                actionFeedback.textContent = '';

                if (currentAction === 'delete' && hasDependencies && !isAdmin) {
                    actionFeedback.textContent = dependencyMessage;
                    actionFeedback.style.display = 'block';
                    setAction('inactivate');
                    return;
                }

                const payload = new FormData(form);
                let responseData;

                try {
                    const response = await fetch(form.getAttribute('action') || '', {
                        method: 'POST',
                        body: payload,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    responseData = await response.json();
                } catch (error) {
                    actionFeedback.textContent = 'No se pudo completar la acción. Intenta nuevamente.';
                    actionFeedback.style.display = 'block';
                    return;
                }

                if (responseData?.success) {
                    alert(responseData.message || 'Acción completada.');
                    window.location.href = '/clients';
                    return;
                }

                const message = responseData?.message || 'No se pudo completar la acción.';
                actionFeedback.textContent = message;
                actionFeedback.style.display = 'block';

                if (responseData?.can_inactivate) {
                    setAction('inactivate');
                }
            });

            setAction(currentAction);
        })();
    </script>
<?php endif; ?>
