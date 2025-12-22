<div class="card portfolio-card">
    <div class="toolbar">
        <div>
            <h2 style="margin:0;">Portafolio por cliente</h2>
            <p style="margin:0;color:var(--muted);">Vista jerárquica y de solo lectura. Acciones clave separadas por flujo.</p>
        </div>
        <div class="toolbar-actions">
            <a href="<?= $basePath ?>/clients/create" class="button ghost">Crear cliente</a>
            <a href="<?= $basePath ?>/projects" class="button">Ir a crear proyecto</a>
            <a href="<?= $basePath ?>/projects" class="button secondary">Ver listado</a>
        </div>
    </div>

    <div class="portfolio-legend">
        <div class="legend-item">Creación de proyectos y clientes en pantallas dedicadas.</div>
        <div class="legend-item">Asignación de talento desde el detalle del proyecto (tab Talento en modal).</div>
        <div class="legend-item">Navegación de detalle por tabs: Resumen, Talento, Scrum (según tipo), Costos, Reportes.</div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="accordion portfolio-accordion">
        <?php foreach ($clients as $client): ?>
            <details>
                <summary>
                    <div class="client-header">
                        <div>
                            <strong><?= htmlspecialchars($client['name']) ?></strong>
                            <p style="margin:0; color: var(--muted);">Proyectos activos: <?= $client['kpis']['active_projects'] ?> / <?= $client['kpis']['total_projects'] ?></p>
                        </div>
                        <div class="kpi-row">
                            <span class="badge">Avance: <?= $client['kpis']['avg_progress'] ?>%</span>
                            <span class="badge <?= $client['kpis']['risk_level'] === 'alto' ? 'danger' : ($client['kpis']['risk_level'] === 'medio' ? 'warning' : 'success') ?>">Riesgo: <?= ucfirst($client['kpis']['risk_level']) ?></span>
                            <span class="badge ghost">Capacidad: <?= $client['kpis']['capacity_used'] ?>h / <?= $client['kpis']['capacity_available'] ?>h (<?= $client['kpis']['capacity_percent'] ?>%)</span>
                        </div>
                    </div>
                </summary>
                <div class="client-body">
                    <div class="client-kpis">
                        <div class="kpi-card">
                            <small>Proyectos activos</small>
                            <strong><?= $client['kpis']['active_projects'] ?> / <?= $client['kpis']['total_projects'] ?></strong>
                        </div>
                        <div class="kpi-card">
                            <small>Avance promedio</small>
                            <strong><?= $client['kpis']['avg_progress'] ?>%</strong>
                        </div>
                        <div class="kpi-card">
                            <small>Capacidad utilizada</small>
                            <strong><?= $client['kpis']['capacity_percent'] ?>%</strong>
                            <span class="subtext"><?= $client['kpis']['capacity_used'] ?>h de <?= $client['kpis']['capacity_available'] ?>h</span>
                        </div>
                        <div class="kpi-card">
                            <small>Nivel de riesgo</small>
                            <strong class="badge <?= $client['kpis']['risk_level'] === 'alto' ? 'danger' : ($client['kpis']['risk_level'] === 'medio' ? 'warning' : 'success') ?>" style="padding: 4px 10px;"><?= ucfirst($client['kpis']['risk_level']) ?></strong>
                        </div>
                    </div>

                    <div class="projects-stack">
                        <?php foreach ($client['projects'] as $project): ?>
                            <?php $projectAssignments = $client['assignments'][$project['id']] ?? []; ?>
                            <?php $assignedHours = array_sum(array_map(fn($a) => (float) ($a['weekly_hours'] ?? 0), $projectAssignments)); ?>
                            <?php $assignedPercent = array_sum(array_map(fn($a) => (float) ($a['allocation_percent'] ?? 0), $projectAssignments)); ?>

                            <details class="project-accordion">
                                <summary>
                                    <div class="project-summary">
                                        <div>
                                            <strong><?= htmlspecialchars($project['name']) ?></strong>
                                            <p class="muted">Tipo: <?= ucfirst($project['project_type'] ?? 'convencional') ?> · PM: <?= htmlspecialchars($project['pm_name'] ?? 'N/D') ?></p>
                                        </div>
                                        <div class="project-badges">
                                            <span class="badge <?= $project['health'] === 'on_track' ? 'success' : ($project['health'] === 'at_risk' ? 'warning' : 'danger') ?>"><?= htmlspecialchars($project['health_label'] ?? $project['health']) ?></span>
                                            <span class="badge ghost">Avance <?= $project['progress'] ?>%</span>
                                            <span class="badge secondary">Estado <?= htmlspecialchars($project['status_label'] ?? $project['status']) ?></span>
                                        </div>
                                    </div>
                                </summary>
                                <div class="project-body">
                                    <div class="project-meta-grid">
                                        <div>
                                            <small>Capacidad asignada</small>
                                            <strong><?= $assignedHours ?>h / <?= $assignedPercent ?>%</strong>
                                        </div>
                                        <div>
                                            <small>Seguimiento</small>
                                            <strong><?= htmlspecialchars($project['status_label'] ?? $project['status']) ?></strong>
                                            <span class="subtext">Salud: <?= htmlspecialchars($project['health_label'] ?? $project['health']) ?></span>
                                        </div>
                                        <div>
                                            <small>Gestión de talento</small>
                                            <strong>Solo desde tab Talento</strong>
                                            <span class="subtext">Abre modal corto para asignar recursos</span>
                                        </div>
                                    </div>

                                    <div class="tab-guidance">
                                        <span class="tab-pill active">Resumen</span>
                                        <span class="tab-pill">Talento</span>
                                        <?php if (in_array($project['project_type'] ?? '', ['scrum', 'hibrido', 'híbrido'], true)): ?>
                                            <span class="tab-pill">Scrum</span>
                                        <?php endif; ?>
                                        <span class="tab-pill">Costos</span>
                                        <span class="tab-pill">Reportes</span>
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
                                        <p class="muted" style="margin:0;">Sin talento asignado. Administrar desde el tab Talento del proyecto.</p>
                                    <?php endif; ?>

                                    <div class="project-actions">
                                        <div>
                                            <p class="muted" style="margin:0;">Detalle del proyecto con tabs y modal de asignación.</p>
                                        </div>
                                        <div class="action-buttons">
                                            <a class="button ghost" href="<?= $basePath ?>/projects">Ver detalle del proyecto</a>
                                            <a class="button secondary" href="<?= $basePath ?>/projects">Abrir tab "Talento"</a>
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
    .portfolio-legend { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px; margin: 16px 0; }
    .legend-item { background: var(--surface-2); padding: 10px 12px; border-radius: 10px; color: var(--muted); }
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
    .project-badges { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .project-body { margin-top: 10px; display: flex; flex-direction: column; gap: 12px; }
    .project-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
    .project-meta-grid small { color: var(--muted); }
    .tab-guidance { display: flex; gap: 6px; flex-wrap: wrap; }
    .tab-pill { padding: 6px 10px; border-radius: 999px; border: 1px solid var(--border); color: var(--muted); background: var(--surface-2); }
    .tab-pill.active { border-color: var(--primary); color: var(--primary); background: rgba(74, 144, 226, 0.08); }
    .assignment-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .assignment-chips.readonly .pill { background: var(--surface-2); color: var(--muted); }
    .project-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-top: 1px solid var(--border); padding-top: 10px; }
    .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
    @media (max-width: 780px) {
        .project-summary { flex-direction: column; align-items: flex-start; }
        .project-actions { flex-direction: column; align-items: flex-start; }
    }
</style>
