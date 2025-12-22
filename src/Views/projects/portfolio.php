<?php
$signalTextMap = [
    'green' => 'Operación estable',
    'yellow' => 'Atención requerida',
    'red' => 'Riesgo crítico',
];

$signalIconMap = [
    'green' => '🛡️',
    'yellow' => '⚠️',
    'red' => '🚨',
];

$riskLevelText = [
    'bajo' => 'Operación estable',
    'medio' => 'Atención requerida',
    'alto' => 'Riesgo crítico',
];
?>

<div class="card portfolio-card">
    <div class="toolbar portfolio-toolbar">
        <div>
            <p class="eyebrow">Visión ejecutiva</p>
            <h2 class="portfolio-title">Portafolios por cliente</h2>
            <p class="muted">Lectura consolidada de operación. No hay acciones de creación ni formularios en esta vista.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert danger" style="margin-bottom:12px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="portfolio-columns">
        <div class="card subtle-card stretch info-card">
            <div class="toolbar">
                <div>
                    <p class="badge neutral" style="margin:0;">Cómo leer el portafolio</p>
                    <h4 style="margin:4px 0 0 0;">Estado explicado con palabras</h4>
                </div>
            </div>
            <ul class="legend-list">
                <li><strong>Operación estable:</strong> sin alertas operativas destacadas.</li>
                <li><strong>Atención requerida:</strong> se detectan desvíos preventivos o riesgos medios.</li>
                <li><strong>Riesgo crítico:</strong> hay incidentes o desvíos severos que comprometen la entrega.</li>
                <li>Los indicadores usan texto y pequeñas señales visuales como apoyo, sin depender solo del color.</li>
            </ul>
        </div>
        <div class="card subtle-card stretch info-card">
            <div class="toolbar">
                <div>
                    <p class="badge neutral" style="margin:0;">Principios de navegación</p>
                    <h4 style="margin:4px 0 0 0;">Portafolio solo lectura</h4>
                </div>
            </div>
            <ul class="legend-list">
                <li>Consulta rápida de clientes, proyectos y uso de capacidad.</li>
                <li>Para asignaciones o creación, usa los módulos dedicados de Proyectos o Talento.</li>
                <li>Accede al detalle completo desde los tabs del proyecto sin salir de este contexto.</li>
            </ul>
        </div>
    </div>

    <div class="portfolio-accordion">
        <?php if (empty($portfolios)): ?>
            <div class="alert neutral">Aún no hay portafolios registrados. Revisa los clientes existentes para crear uno desde su ficha.</div>
        <?php endif; ?>
        <?php foreach ($portfolios as $portfolio): ?>
            <?php
                $clientMeta = $portfolio['client_meta'] ?? [];
                $logoPath = $clientMeta['logo_path'] ?? null;
                $sector = $clientMeta['sector_label'] ?? 'Sector no registrado';
                $category = $clientMeta['category_label'] ?? 'Categoría no registrada';
                $pmName = $clientMeta['pm_name'] ?? 'PM sin asignar';
                $generalStatus = $signalTextMap[$portfolio['signal']['code'] ?? ''] ?? 'Estado no disponible';
                $generalIcon = $signalIconMap[$portfolio['signal']['code'] ?? ''] ?? 'ℹ️';
                $operativeRisk = $riskLevelText[$portfolio['kpis']['risk_level'] ?? ''] ?? 'Riesgo no calculado';
            ?>
            <details class="client-shell" open>
                <summary>
                    <div class="client-shell__header">
                        <div class="client-identity">
                            <div class="client-logo" aria-hidden="true">
                                <?php if ($logoPath): ?>
                                    <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo de <?= htmlspecialchars($portfolio['client_name']) ?>">
                                <?php else: ?>
                                    <span><?= strtoupper(substr($portfolio['client_name'], 0, 1)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="client-heading">
                                <div class="client-name-row">
                                    <strong><?= htmlspecialchars($portfolio['client_name']) ?></strong>
                                    <span class="portfolio-name">Portafolio: <?= htmlspecialchars($portfolio['name']) ?></span>
                                </div>
                                <p class="muted">Sector <?= htmlspecialchars($sector) ?> · Categoría <?= htmlspecialchars($category) ?> · PM <?= htmlspecialchars($pmName) ?></p>
                            </div>
                        </div>
                        <div class="client-kpi-band">
                            <div class="status-pill">
                                <span class="status-icon" aria-hidden="true"><?= $generalIcon ?></span>
                                <div>
                                    <small>Estado general</small>
                                    <strong><?= $generalStatus ?></strong>
                                    <span class="subtext"><?= htmlspecialchars($portfolio['signal']['summary']) ?></span>
                                </div>
                            </div>
                            <div class="status-pill subtle">
                                <span class="status-icon" aria-hidden="true">🧭</span>
                                <div>
                                    <small>Riesgo operativo</small>
                                    <strong><?= $operativeRisk ?></strong>
                                    <span class="subtext">Riesgo agregado de los proyectos del cliente.</span>
                                </div>
                            </div>
                            <div class="status-pill subtle">
                                <span class="status-icon" aria-hidden="true">📈</span>
                                <div>
                                    <small>Avance promedio</small>
                                    <strong><?= $portfolio['kpis']['avg_progress'] ?>%</strong>
                                    <span class="subtext">Progreso consolidado de proyectos activos.</span>
                                </div>
                            </div>
                            <div class="status-pill subtle">
                                <span class="status-icon" aria-hidden="true">⏱️</span>
                                <div>
                                    <small>Capacidad utilizada</small>
                                    <strong><?= $portfolio['kpis']['capacity_used'] ?>h de <?= $portfolio['kpis']['capacity_available'] ?>h</strong>
                                    <span class="subtext">Uso actual: <?= $portfolio['kpis']['capacity_percent'] ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </summary>
                <div class="client-body">
                    <div class="client-kpis">
                        <div class="kpi-card">
                            <small>Periodo operativo</small>
                            <strong><?= $portfolio['start_date'] ? htmlspecialchars($portfolio['start_date']) : 'Sin inicio' ?> → <?= $portfolio['end_date'] ? htmlspecialchars($portfolio['end_date']) : 'Sin fin' ?></strong>
                            <span class="subtext">Control de vigencia del portafolio.</span>
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
                            <?php if ($portfolio['attachment_path']): ?>
                                <strong><a href="<?= $basePath ?>/uploads/<?= htmlspecialchars($portfolio['attachment_path']) ?>" target="_blank" rel="noreferrer">Ver documento</a></strong>
                                <span class="subtext">Alcance o SOW referencial.</span>
                            <?php else: ?>
                                <strong>Sin adjunto</strong>
                                <span class="subtext">Puedes cargarlo desde Configuración &gt; Portafolios.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="projects-stack">
                        <?php foreach ($portfolio['projects'] as $project): ?>
                            <?php $projectAssignments = $portfolio['assignments'][$project['id']] ?? []; ?>
                            <?php $assignedHours = array_sum(array_map(fn($a) => (float) ($a['weekly_hours'] ?? 0), $projectAssignments)); ?>
                            <?php $assignedPercent = array_sum(array_map(fn($a) => (float) ($a['allocation_percent'] ?? 0), $projectAssignments)); ?>
                            <?php
                                $projectStatus = $signalTextMap[$project['signal']['code'] ?? ''] ?? 'Estado no disponible';
                                $projectIcon = $signalIconMap[$project['signal']['code'] ?? ''] ?? 'ℹ️';
                                $costDeviation = $project['signal']['cost_deviation'];
                                $costSituation = ($costDeviation !== null && $costDeviation > 0.05) ? 'En desviación' : 'Dentro del presupuesto';
                                $riskText = $signalTextMap[$project['signal']['code'] ?? ''] ?? 'Riesgo no calculado';
                            ?>

                            <details class="project-accordion" open>
                                <summary>
                                    <div class="project-summary">
                                        <div class="project-title">
                                            <span class="status-icon" aria-hidden="true"><?= $projectIcon ?></span>
                                            <div>
                                                <strong><?= htmlspecialchars($project['name']) ?></strong>
                                                <p class="muted">Tipo: <?= ucfirst($project['project_type'] ?? 'convencional') ?> · PM: <?= htmlspecialchars($project['pm_name'] ?? 'N/D') ?></p>
                                            </div>
                                        </div>
                                        <div class="project-badges">
                                            <span class="pill">Estado: <?= $projectStatus ?></span>
                                            <span class="pill subtle">Riesgo: <?= $riskText ?></span>
                                            <span class="pill subtle">Avance <?= $project['progress'] ?>%</span>
                                            <span class="pill neutral">Capacidad <?= $assignedHours ?>h / <?= $assignedPercent ?>%</span>
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
                                            <small>Estado operativo</small>
                                            <strong><?= $projectStatus ?></strong>
                                            <span class="subtext">Motivos: <?= htmlspecialchars(implode(' · ', $project['signal']['reasons'])) ?></span>
                                        </div>
                                        <div>
                                            <small>Capacidad utilizada</small>
                                            <strong><?= $assignedHours ?>h / <?= $assignedPercent ?>%</strong>
                                            <span class="subtext">Balance entre horas planificadas y reales.</span>
                                        </div>
                                        <div>
                                            <small>Situación de costos</small>
                                            <strong><?= $costSituation ?></strong>
                                            <span class="subtext">Desvío: <?= $project['signal']['cost_deviation'] !== null ? round($project['signal']['cost_deviation'] * 100, 1) . '%' : 'N/D' ?> · Horas: <?= $project['signal']['hours_deviation'] !== null ? round($project['signal']['hours_deviation'] * 100, 1) . '%' : 'N/D' ?></span>
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
                                                <span class="pill neutral">
                                                    <?= htmlspecialchars($assignment['talent_name']) ?> — <?= htmlspecialchars($assignment['role']) ?> (<?= $assignment['weekly_hours'] ?>h / <?= $assignment['allocation_percent'] ?>%)
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="muted" style="margin:0;">Sin talento asignado. Gestiona desde Talento o el tab Talento del proyecto.</p>
                                    <?php endif; ?>

                                    <div class="project-actions">
                                        <p class="muted" style="margin:0;">Vista de lectura. Para modificar planificación o costos abre el proyecto en sus tabs dedicados.</p>
                                        <div class="action-buttons">
                                            <a class="button ghost" href="<?= $basePath ?>/projects">Ver detalle del proyecto</a>
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
    .portfolio-toolbar { border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 12px; }
    .portfolio-title { margin: 0; }
    .eyebrow { margin: 0; text-transform: uppercase; letter-spacing: 0.02em; color: var(--muted); font-size: 12px; }
    .portfolio-columns { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px; margin: 14px 0; }
    .legend-list { margin: 0; padding-left: 18px; color: var(--muted); display: grid; gap: 6px; }
    .info-card { background: var(--surface-2); }
    .portfolio-accordion details { border: 1px solid var(--border); border-radius: 14px; padding: 12px; background: var(--surface); }
    .portfolio-accordion details + details { margin-top: 12px; }
    .client-shell summary { cursor: pointer; }
    .client-shell__header { display: flex; justify-content: space-between; gap: 14px; align-items: center; flex-wrap: wrap; }
    .client-identity { display: flex; gap: 10px; align-items: center; }
    .client-logo { width: 54px; height: 54px; border-radius: 12px; background: var(--surface-2); display: grid; place-items: center; font-weight: 700; color: var(--muted); border: 1px solid var(--border); overflow: hidden; }
    .client-logo img { width: 100%; height: 100%; object-fit: contain; }
    .client-heading { display: flex; flex-direction: column; gap: 4px; }
    .client-name-row { display: flex; gap: 8px; align-items: baseline; flex-wrap: wrap; }
    .portfolio-name { color: var(--muted); font-size: 14px; }
    .client-kpi-band { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; flex: 1; }
    .status-pill { display: flex; gap: 8px; align-items: flex-start; padding: 10px; border-radius: 12px; border: 1px solid var(--border); background: var(--surface-2); }
    .status-pill.subtle { background: var(--surface-1); }
    .status-icon { font-size: 18px; }
    .subtext { display: block; color: var(--muted); font-size: 12px; margin-top: 2px; }
    .client-body { margin-top: 12px; display: flex; flex-direction: column; gap: 14px; }
    .client-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
    .kpi-card { background: var(--surface-2); padding: 12px; border-radius: 10px; border: 1px solid var(--border); }
    .kpi-card .subtext { color: var(--muted); font-size: 12px; display: block; margin-top: 4px; }
    .projects-stack { display: flex; flex-direction: column; gap: 10px; }
    .project-accordion { border: 1px dashed var(--border); border-radius: 12px; padding: 10px; background: var(--surface-1); }
    .project-summary { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
    .project-summary .muted { margin: 0; color: var(--muted); }
    .project-title { display: flex; align-items: center; gap: 8px; }
    .project-badges { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .pill { padding: 6px 10px; border-radius: 999px; background: var(--surface-2); border: 1px solid var(--border); font-weight: 600; }
    .pill.subtle { background: var(--surface-1); font-weight: 500; }
    .pill.neutral { background: transparent; border-style: dashed; color: var(--muted); font-weight: 500; }
    .project-body { margin-top: 10px; display: flex; flex-direction: column; gap: 12px; }
    .project-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
    .project-meta-grid small { color: var(--muted); }
    .project-alerts ul { margin: 6px 0 0; padding-left: 18px; color: var(--muted); }
    .project-alerts li { margin-bottom: 4px; }
    .assignment-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .assignment-chips.readonly .pill { background: var(--surface-2); color: var(--muted); border: 1px solid var(--border); }
    .project-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-top: 1px solid var(--border); padding-top: 10px; }
    .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
    summary::-webkit-details-marker { display: none; }
</style>
