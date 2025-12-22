<div class="card portfolio-card">
    <div class="toolbar">
        <div>
            <h2 style="margin:0;">Portafolios por cliente</h2>
            <p style="margin:0;color:var(--muted);">Control operativo centralizado. La asignación de talento vive en sus pantallas dedicadas.</p>
        </div>
        <div class="toolbar-actions">
            <a href="<?= $basePath ?>/clients/create" class="button ghost">Crear cliente</a>
            <a href="<?= $basePath ?>/projects" class="button">Crear proyecto</a>
            <a href="<?= $basePath ?>/talents" class="button secondary">Gestionar talento</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert danger" style="margin-bottom:12px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="portfolio-columns">
        <div class="card subtle-card stretch">
            <div class="toolbar">
                <div>
                    <p class="badge neutral" style="margin:0;">Nuevo portafolio</p>
                    <h4 style="margin:4px 0 0 0;">Un portafolio por cliente, con límites y adjuntos</h4>
                </div>
            </div>
            <form action="<?= $basePath ?>/portfolio" method="POST" enctype="multipart/form-data" class="config-form-grid">
                <label>Cliente
                    <select name="client_id" required>
                        <option value="">Selecciona un cliente</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int) $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Nombre
                    <input name="name" placeholder="Portafolio anual" required>
                </label>
                <label>Fecha inicio
                    <input type="date" name="start_date">
                </label>
                <label>Fecha fin
                    <input type="date" name="end_date">
                </label>
                <label>Límite de horas
                    <input type="number" step="0.01" name="hours_limit" placeholder="Ej. 1200">
                </label>
                <label>Límite de presupuesto
                    <input type="number" step="0.01" name="budget_limit" placeholder="Ej. 250000">
                </label>
                <label>Adjunto (SOW / alcance)
                    <input type="file" name="attachment" accept="application/pdf,image/*">
                </label>
                <div class="form-footer">
                    <span class="text-muted">Notificamos vencimientos y excesos según reglas operativas.</span>
                    <button class="button primary" type="submit">Crear portafolio</button>
                </div>
            </form>
        </div>
        <div class="card subtle-card stretch">
            <div class="toolbar">
                <div>
                    <p class="badge neutral" style="margin:0;">Reglas de operación</p>
                    <h4 style="margin:4px 0 0 0;">Semáforo y alertas configurables</h4>
                </div>
            </div>
            <ul class="legend-list">
                <li>Semáforo automático por avance, horas y costo según Configuración &gt; Reglas operativas.</li>
                <li>Alertas de vencimiento de portafolio y consumo de límites de horas / presupuesto.</li>
                <li>La asignación de talento se gestiona en Talento o en el tab dedicado del proyecto.</li>
                <li>Tipos de proyecto soportados: convencional, scrum e híbrido.</li>
            </ul>
        </div>
    </div>

    <div class="portfolio-accordion">
        <?php if (empty($portfolios)): ?>
            <div class="alert neutral">Aún no hay portafolios registrados. Crea el primero para un cliente.</div>
        <?php endif; ?>
        <?php foreach ($portfolios as $portfolio): ?>
            <details open>
                <summary>
                    <div class="client-header">
                        <div class="client-title">
                            <span class="signal-badge <?= $portfolio['signal']['code'] ?>" title="<?= htmlspecialchars($portfolio['signal']['summary']) ?>">● <?= $portfolio['signal']['label'] ?></span>
                            <div>
                                <strong><?= htmlspecialchars($portfolio['client_name']) ?></strong>
                                <p style="margin:0; color: var(--muted);">Portafolio: <?= htmlspecialchars($portfolio['name']) ?></p>
                            </div>
                        </div>
                        <div class="kpi-row">
                            <span class="badge">Avance: <?= $portfolio['kpis']['avg_progress'] ?>%</span>
                            <span class="badge <?= $portfolio['kpis']['risk_level'] === 'alto' ? 'danger' : ($portfolio['kpis']['risk_level'] === 'medio' ? 'warning' : 'success') ?>">Riesgo: <?= ucfirst($portfolio['kpis']['risk_level']) ?></span>
                            <span class="badge ghost">Capacidad: <?= $portfolio['kpis']['capacity_used'] ?>h / <?= $portfolio['kpis']['capacity_available'] ?>h (<?= $portfolio['kpis']['capacity_percent'] ?>%)</span>
                        </div>
                    </div>
                </summary>
                <div class="client-body">
                    <div class="client-kpis">
                        <div class="kpi-card">
                            <small>Fechas</small>
                            <strong><?= $portfolio['start_date'] ? htmlspecialchars($portfolio['start_date']) : 'Sin inicio' ?> → <?= $portfolio['end_date'] ? htmlspecialchars($portfolio['end_date']) : 'Sin fin' ?></strong>
                        </div>
                        <div class="kpi-card">
                            <small>Límite de horas</small>
                            <strong><?= $portfolio['hours_limit'] ?: 'N/D' ?></strong>
                            <span class="subtext">Usadas: <?= round((float) ($portfolio['kpis']['capacity_used']), 1) ?>h</span>
                        </div>
                        <div class="kpi-card">
                            <small>Límite de presupuesto</small>
                            <strong><?= $portfolio['budget_limit'] ?: 'N/D' ?></strong>
                            <span class="subtext">Gasto real: <?= round((float) ($portfolio['projects'] ? array_sum(array_column($portfolio['projects'], 'actual_cost')) : 0), 1) ?></span>
                        </div>
                        <div class="kpi-card">
                            <small>Adjunto</small>
                            <?php if (!empty($portfolio['attachment_path'])): ?>
                                <a class="badge ghost" href="<?= htmlspecialchars($portfolio['attachment_path']) ?>" target="_blank" rel="noopener">Ver archivo</a>
                            <?php else: ?>
                                <span class="subtext">Sin adjuntos</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($portfolio['alerts'])): ?>
                        <div class="alert warning">
                            <strong>Alertas del portafolio:</strong>
                            <ul>
                                <?php foreach ($portfolio['alerts'] as $alert): ?>
                                    <li><?= htmlspecialchars($alert) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="projects-stack">
                        <?php foreach ($portfolio['projects'] as $project): ?>
                            <?php $projectAssignments = $portfolio['assignments'][$project['id']] ?? []; ?>
                            <?php $assignedHours = array_sum(array_map(fn($a) => (float) ($a['weekly_hours'] ?? 0), $projectAssignments)); ?>
                            <?php $assignedPercent = array_sum(array_map(fn($a) => (float) ($a['allocation_percent'] ?? 0), $projectAssignments)); ?>

                            <details class="project-accordion">
                                <summary>
                                    <div class="project-summary">
                                        <div class="project-title">
                                            <span class="signal-dot <?= $project['signal']['code'] ?>" title="<?= htmlspecialchars(implode(' · ', $project['signal']['reasons'])) ?>"></span>
                                            <div>
                                                <strong><?= htmlspecialchars($project['name']) ?></strong>
                                                <p class="muted">Tipo: <?= ucfirst($project['project_type'] ?? 'convencional') ?> · PM: <?= htmlspecialchars($project['pm_name'] ?? 'N/D') ?></p>
                                            </div>
                                        </div>
                                        <div class="project-badges">
                                            <span class="badge ghost">Semáforo: <?= $project['signal']['label'] ?></span>
                                            <span class="badge <?= $project['health'] === 'on_track' ? 'success' : ($project['health'] === 'at_risk' ? 'warning' : 'danger') ?>"><?= htmlspecialchars($project['health_label'] ?? $project['health']) ?></span>
                                            <span class="badge ghost">Avance <?= $project['progress'] ?>%</span>
                                            <span class="badge secondary">Estado <?= htmlspecialchars($project['status_label'] ?? $project['status']) ?></span>
                                        </div>
                                    </div>
                                </summary>
                                <div class="project-body">
                                    <div class="project-meta-grid">
                                    <div>
                                            <small>Tipo de proyecto</small>
                                            <strong><?= htmlspecialchars(ucfirst($project['project_type'] ?? 'convencional')) ?></strong>
                                        </div>
                                        <div>
                                            <small>Capacidad asignada</small>
                                            <strong><?= $assignedHours ?>h / <?= $assignedPercent ?>%</strong>
                                        </div>
                                        <div>
                                            <small>Control operativo</small>
                                            <strong class="badge <?= $project['signal']['code'] === 'red' ? 'danger' : ($project['signal']['code'] === 'yellow' ? 'warning' : 'success') ?>" style="padding:4px 10px;">Semáforo <?= $project['signal']['label'] ?></strong>
                                            <span class="subtext">Costos: <?= $project['signal']['cost_deviation'] !== null ? round($project['signal']['cost_deviation'] * 100, 1) . '%' : 'N/D' ?> · Horas: <?= $project['signal']['hours_deviation'] !== null ? round($project['signal']['hours_deviation'] * 100, 1) . '%' : 'N/D' ?></span>
                                        </div>
                                        <div>
                                            <small>Gestión de talento</small>
                                            <strong>Disponible en módulo Talento</strong>
                                            <span class="subtext">Sin formularios embebidos en el portafolio</span>
                                        </div>
                                    </div>

                                    <div class="project-alerts">
                                        <small>Alertas operativas</small>
                                        <ul>
                                            <?php foreach ($project['signal']['reasons'] as $reason): ?>
                                                <li><?= htmlspecialchars($reason) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <?php if ($projectAssignments): ?>
                                        <div class="assignment-chips readonly">
                                            <?php foreach ($projectAssignments as $assignment): ?>
                                                <span class="pill">
                                                    <?= htmlspecialchars($assignment['talent_name']) ?> — <?= htmlspecialchars($assignment['role']) ?> (<?= $assignment['weekly_hours'] ?>h / <?= $assignment['allocation_percent'] ?>%)
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="muted" style="margin:0;">Sin talento asignado. Gestiona desde Talento o el tab Talento del proyecto.</p>
                                    <?php endif; ?>

                                    <div class="project-actions">
                                        <div>
                                            <p class="muted" style="margin:0;">Detalle del proyecto con tabs dedicados: Resumen, Talento, Scrum, Costos, Reportes.</p>
                                        </div>
                                        <div class="action-buttons">
                                            <a class="button ghost" href="<?= $basePath ?>/projects">Ver detalle del proyecto</a>
                                            <a class="button secondary" href="<?= $basePath ?>/talents">Asignar talento</a>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .portfolio-card { padding: 20px; }
    .toolbar { align-items: center; }
    .toolbar-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .portfolio-columns { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px; margin: 14px 0; }
    .legend-list { margin: 0; padding-left: 18px; color: var(--muted); display: grid; gap: 6px; }
    .portfolio-accordion details { border: 1px solid var(--border); border-radius: 14px; padding: 12px; background: var(--surface); }
    .portfolio-accordion details + details { margin-top: 12px; }
    .client-body { margin-top: 12px; display: flex; flex-direction: column; gap: 14px; }
    .client-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
    .kpi-card { background: var(--surface-2); padding: 12px; border-radius: 10px; }
    .kpi-card .subtext { color: var(--muted); font-size: 12px; display: block; margin-top: 4px; }
    .projects-stack { display: flex; flex-direction: column; gap: 10px; }
    .project-accordion { border: 1px dashed var(--border); border-radius: 12px; padding: 10px; background: var(--surface-1); }
    .project-accordion summary { cursor: pointer; }
    .project-summary { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .project-summary .muted { margin: 0; color: var(--muted); }
    .project-title { display: flex; align-items: center; gap: 8px; }
    .client-title { display: flex; align-items: center; gap: 10px; }
    .signal-badge { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; padding: 6px 10px; border-radius: 999px; }
    .signal-badge.green { background: rgba(26, 148, 49, 0.12); color: #1a9431; }
    .signal-badge.yellow { background: rgba(250, 184, 20, 0.16); color: #b38100; }
    .signal-badge.red { background: rgba(235, 87, 87, 0.15); color: #b42318; }
    .signal-dot { width: 14px; height: 14px; border-radius: 999px; display: inline-block; border: 2px solid var(--surface-2); }
    .signal-dot.green { background: #2ecc71; }
    .signal-dot.yellow { background: #f5a524; }
    .signal-dot.red { background: #e2554d; }
    .project-badges { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .project-body { margin-top: 10px; display: flex; flex-direction: column; gap: 12px; }
    .project-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
    .project-meta-grid small { color: var(--muted); }
    .project-alerts ul { margin: 6px 0 0; padding-left: 18px; color: var(--muted); }
    .project-alerts li { margin-bottom: 4px; }
    .assignment-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .assignment-chips.readonly .pill { background: var(--surface-2); color: var(--muted); }
    .project-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-top: 1px solid var(--border); padding-top: 10px; }
    .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
