<?php
$clients = is_array($clients ?? null) ? $clients : [];
$portfolios = is_array($portfolios ?? null) ? $portfolios : [];

$signalTextMap = [
    'green' => 'Operación estable',
    'yellow' => 'Atención requerida',
    'red' => 'Riesgo crítico',
];

$riskLevelText = [
    'bajo' => 'Operación estable',
    'medio' => 'Atención requerida',
    'alto' => 'Riesgo crítico',
];

$clientsIndex = [];
foreach ($clients as $client) {
    $clientsIndex[(int) $client['id']] = $client;
}

$grouped = [];
foreach ($portfolios as $portfolio) {
    $clientId = (int) $portfolio['client_id'];
    if (!isset($grouped[$clientId])) {
        $grouped[$clientId] = [
            'client' => $clientsIndex[$clientId] ?? ['name' => $portfolio['client_name']],
            'portfolios' => [],
        ];
    }
    $grouped[$clientId]['portfolios'][] = $portfolio;
}

$groupedPortfolios = array_values($grouped);
?>

<div class="card portfolio-card">
    <div class="toolbar portfolio-toolbar">
        <div>
            <p class="eyebrow">Crear ≠ Analizar</p>
            <h2 class="portfolio-title">Portafolio por cliente</h2>
            <p class="muted">Vista ejecutiva de análisis. Sin formularios ni creación aquí: solo lectura, resumen y drilldown.</p>
        </div>
        <div class="portfolio-actions">
            <a class="button ghost" href="<?= $basePath ?>/projects">Ir a proyectos</a>
            <a class="button primary" href="<?= $basePath ?>/portfolio/create">Nuevo portafolio (wizard)</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert danger" style="margin-bottom:12px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="principles">
        <div class="card subtle-card">
            <p class="badge neutral" style="margin:0;">Principio</p>
            <strong>Crear es guiado, analizar es limpio.</strong>
            <p class="muted">La creación ocurre en el wizard. Aquí solo se interpretan resultados y riesgos en texto.</p>
        </div>
        <div class="card subtle-card">
            <p class="badge neutral" style="margin:0;">Estructura</p>
            <strong>Cliente → Portafolios → Proyectos</strong>
            <p class="muted">Expande cada cliente y navega por tabs de cada portafolio.</p>
        </div>
        <div class="card subtle-card">
            <p class="badge neutral" style="margin:0;">Cobertura</p>
            <strong>Resumen ejecutivo + Drilldown</strong>
            <p class="muted">Avance, horas, costos y riesgo expresados con palabras, no solo colores.</p>
        </div>
    </div>

    <?php if (empty($groupedPortfolios)): ?>
        <div class="alert neutral">Aún no hay portafolios registrados. Crea uno desde el wizard para empezar a analizarlos.</div>
    <?php endif; ?>

    <div class="portfolio-stack">
        <?php foreach ($groupedPortfolios as $group): ?>
            <?php $client = $group['client']; ?>
            <section class="client-block">
                <header class="client-header">
                    <div>
                        <p class="eyebrow">Cliente</p>
                        <h3><?= htmlspecialchars($client['name'] ?? 'Cliente sin nombre') ?></h3>
                        <p class="muted"><?= htmlspecialchars($client['sector_label'] ?? 'Sector no registrado') ?> · <?= htmlspecialchars($client['category_label'] ?? 'Categoría no registrada') ?></p>
                    </div>
                    <div class="meta-badges">
                        <span class="pill neutral">Portafolios: <?= count($group['portfolios']) ?></span>
                        <span class="pill neutral">Proyectos activos: <?= array_sum(array_map(fn ($p) => count(is_array($p['projects'] ?? null) ? $p['projects'] : []), $group['portfolios'])) ?></span>
                    </div>
                </header>

                <div class="portfolio-grid">
                    <?php foreach ($group['portfolios'] as $portfolio): ?>
                        <?php
                            $projects = is_array($portfolio['projects'] ?? null) ? $portfolio['projects'] : [];
                            $hoursUsed = array_sum(array_map(fn ($p) => (float) ($p['actual_hours'] ?? 0), $projects));
                            $plannedHours = array_sum(array_map(fn ($p) => (float) ($p['planned_hours'] ?? 0), $projects));
                            $budgetUsed = array_sum(array_map(fn ($p) => (float) ($p['actual_cost'] ?? 0), $projects));
                            $budgetPlanned = array_sum(array_map(fn ($p) => (float) ($p['budget'] ?? 0), $projects));
                            $hoursCap = (float) ($portfolio['hours_limit'] ?? 0) ?: $plannedHours;
                            $budgetCap = (float) ($portfolio['budget_limit'] ?? 0) ?: $budgetPlanned;
                            $hoursRatio = $hoursCap > 0 ? round(($hoursUsed / $hoursCap) * 100, 1) : null;
                            $budgetRatio = $budgetCap > 0 ? round(($budgetUsed / $budgetCap) * 100, 1) : null;
                            $riskText = $riskLevelText[$portfolio['kpis']['risk_level'] ?? ''] ?? 'Riesgo no calculado';
                            $generalStatus = $signalTextMap[$portfolio['signal']['code'] ?? ''] ?? 'Estado no disponible';
                            $portfolioId = 'pf-' . $portfolio['id'];
                            $hasScrum = array_filter($projects, fn ($project) => in_array($project['project_type'] ?? '', ['agil', 'scrum', 'agile'], true));
                            $alerts = is_array($portfolio['alerts'] ?? null) ? $portfolio['alerts'] : [];
                            $assignmentsByProject = is_array($portfolio['assignments'] ?? null) ? $portfolio['assignments'] : [];
                        ?>
                        <article class="portfolio-card-grid" id="<?= htmlspecialchars($portfolioId) ?>">
                            <header class="portfolio-summary">
                                <div>
                                    <p class="eyebrow">Portafolio</p>
                                    <h4><?= htmlspecialchars($portfolio['name']) ?></h4>
                                    <p class="muted"><?= htmlspecialchars($portfolio['signal']['summary'] ?? 'Sin resumen cargado') ?></p>
                                </div>
                                <div class="kpi-strip">
                                    <div class="kpi-chip">
                                        <small>Avance global</small>
                                        <strong><?= $portfolio['kpis']['avg_progress'] ?>%</strong>
                                    </div>
                                    <div class="kpi-chip">
                                        <small>Consumo de horas</small>
                                        <strong><?= $hoursRatio !== null ? $hoursRatio . '%' : 'N/D' ?></strong>
                                        <span class="subtext">Usadas: <?= $hoursUsed ?>h <?= $hoursCap ? '/ ' . $hoursCap . 'h' : '' ?></span>
                                    </div>
                                    <div class="kpi-chip">
                                        <small>Estado de costos</small>
                                        <strong><?= $budgetRatio !== null ? $budgetRatio . '%' : 'N/D' ?></strong>
                                        <span class="subtext">Real: $<?= number_format((float) $budgetUsed, 0, ',', '.') ?> <?= $budgetCap ? '/ $' . number_format((float) $budgetCap, 0, ',', '.') : '' ?></span>
                                    </div>
                                    <div class="kpi-chip">
                                        <small>Nivel de riesgo</small>
                                        <strong><?= $riskText ?></strong>
                                        <span class="subtext">Estado general: <?= $generalStatus ?></span>
                                    </div>
                                </div>
                            </header>

                            <div class="tab-nav" role="tablist" aria-label="Drilldown del portafolio">
                                <button class="tab-button active" data-tab="summary-<?= $portfolioId ?>">Resumen</button>
                                <button class="tab-button" data-tab="projects-<?= $portfolioId ?>">Proyectos</button>
                                <button class="tab-button" data-tab="talent-<?= $portfolioId ?>">Talento</button>
                                <button class="tab-button" data-tab="costs-<?= $portfolioId ?>">Costos</button>
                                <?php if ($hasScrum): ?>
                                    <button class="tab-button" data-tab="scrum-<?= $portfolioId ?>">Scrum</button>
                                <?php endif; ?>
                                <button class="tab-button" data-tab="reports-<?= $portfolioId ?>">Reportes</button>
                            </div>

                            <div class="tab-content" id="summary-<?= $portfolioId ?>">
                                <div class="summary-grid">
                                    <div>
                                        <small>Periodo</small>
                                        <strong><?= $portfolio['start_date'] ?: 'Sin inicio' ?> → <?= $portfolio['end_date'] ?: 'Sin fin' ?></strong>
                                        <span class="subtext">Alertas configuradas: <?= count($alerts) ?>
                                            <?= $alerts ? ' · ' . htmlspecialchars(implode(' · ', $alerts)) : '' ?></span>
                                    </div>
                                    <div>
                                        <small>Adjunto</small>
                                        <?php if ($portfolio['attachment_path']): ?>
                                            <strong><a href="<?= htmlspecialchars($portfolio['attachment_path']) ?>" target="_blank" rel="noreferrer">Ver documento</a></strong>
                                            <span class="subtext">Alcance o SOW referencial.</span>
                                        <?php else: ?>
                                            <strong>Sin adjunto</strong>
                                            <span class="subtext">Cárgalo desde configuración de portafolios.</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <small>Reglas</small>
                                        <strong><?= $portfolio['rules_notes'] ? htmlspecialchars($portfolio['rules_notes']) : 'No registradas' ?></strong>
                                        <span class="subtext">Alertas: <?= $portfolio['alerting_policy'] ? htmlspecialchars($portfolio['alerting_policy']) : 'No definido' ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-content hidden" id="projects-<?= $portfolioId ?>">
                                <div class="projects-list">
                                    <?php foreach ($projects as $project): ?>
                                        <?php
                                            $projectStatus = $signalTextMap[$project['signal']['code'] ?? ''] ?? 'Estado no disponible';
                                            $costDeviation = $project['signal']['cost_deviation'];
                                            $hoursDeviation = $project['signal']['hours_deviation'];
                                            $riskTextProject = $signalTextMap[$project['signal']['code'] ?? ''] ?? 'Riesgo no calculado';
                                        ?>
                                        <div class="project-row">
                                            <div>
                                                <strong><?= htmlspecialchars($project['name']) ?></strong>
                                                <p class="muted">Tipo <?= htmlspecialchars(ucfirst($project['project_type'] ?? 'convencional')) ?> · PM <?= htmlspecialchars($project['pm_name'] ?? 'N/D') ?></p>
                                            </div>
                                            <div class="project-kpis">
                                                <span class="pill">Estado: <?= $projectStatus ?></span>
                                                <span class="pill subtle">Riesgo: <?= $riskTextProject ?></span>
                                                <span class="pill subtle">Avance <?= $project['progress'] ?>%</span>
                                                <span class="pill neutral">Horas <?= $project['actual_hours'] ?>/<?= $project['planned_hours'] ?></span>
                                            </div>
                                            <div class="project-detail">
                                                <small>Alertas</small>
                                                <p class="muted"><?= htmlspecialchars(implode(' · ', $project['signal']['reasons'])) ?></p>
                                                <small>Costos</small>
                                                <p class="muted">Desvío costo: <?= $costDeviation !== null ? round($costDeviation * 100, 1) . '%' : 'N/D' ?> · Desvío horas: <?= $hoursDeviation !== null ? round($hoursDeviation * 100, 1) . '%' : 'N/D' ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="tab-content hidden" id="talent-<?= $portfolioId ?>">
                                <div class="chip-grid">
                                    <?php foreach ($assignmentsByProject as $projectId => $assignments): ?>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <span class="pill neutral">
                                                <?= htmlspecialchars($assignment['talent_name']) ?> — <?= htmlspecialchars($assignment['role']) ?> (<?= $assignment['weekly_hours'] ?>h / <?= $assignment['allocation_percent'] ?>%)
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                    <?php if (empty(array_filter($assignmentsByProject))): ?>
                                        <p class="muted">Sin talento asignado. Gestiona desde Talento o los tabs del proyecto.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="tab-content hidden" id="costs-<?= $portfolioId ?>">
                                <div class="summary-grid">
                                    <div>
                                        <small>Capacidad de horas</small>
                                        <strong><?= $hoursCap ?: 'Sin tope' ?></strong>
                                        <span class="subtext">Consumidas: <?= $hoursUsed ?>h <?= $hoursRatio !== null ? '(' . $hoursRatio . '%)' : '' ?></span>
                                    </div>
                                    <div>
                                        <small>Presupuesto</small>
                                        <strong><?= $budgetCap ? '$' . number_format((float) $budgetCap, 0, ',', '.') : 'Sin tope' ?></strong>
                                        <span class="subtext">Real: $<?= number_format((float) $budgetUsed, 0, ',', '.') ?> <?= $budgetRatio !== null ? '(' . $budgetRatio . '%)' : '' ?></span>
                                    </div>
                                    <div>
                                        <small>Estado de costos</small>
                                        <strong><?= $generalStatus ?></strong>
                                        <span class="subtext">Desviaciones monitoreadas con reglas configuradas.</span>
                                    </div>
                                </div>
                            </div>

                            <?php if ($hasScrum): ?>
                                <div class="tab-content hidden" id="scrum-<?= $portfolioId ?>">
                                    <p class="muted">Scrum aplica en proyectos ágiles del portafolio.</p>
                                    <ul class="muted">
                                        <?php foreach ($projects as $project): ?>
                                            <?php if (in_array($project['project_type'] ?? '', ['agil', 'scrum', 'agile'], true)): ?>
                                                <li><strong><?= htmlspecialchars($project['name']) ?>:</strong> Avance <?= $project['progress'] ?>%, salud <?= $signalTextMap[$project['signal']['code'] ?? ''] ?? 'N/D' ?>.</li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="tab-content hidden" id="reports-<?= $portfolioId ?>">
                                <p class="muted">Usa este espacio como punto de partida para reportes ejecutivos.</p>
                                <div class="pillset">
                                    <span class="pill">Resumen PDF</span>
                                    <span class="pill neutral">Bitácora semanal</span>
                                    <span class="pill neutral">Alertas recientes</span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<script>
const tabButtons = document.querySelectorAll('.tab-button');
tabButtons.forEach(button => {
    button.addEventListener('click', () => {
        const tabId = button.dataset.tab;
        const container = button.closest('.portfolio-card-grid');
        if (!container) return;

        container.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        container.querySelectorAll('.tab-content').forEach(panel => panel.classList.add('hidden'));

        button.classList.add('active');
        const panel = container.querySelector('#' + tabId);
        if (panel) {
            panel.classList.remove('hidden');
        }
    });
});
</script>

<style>
.portfolio-card { padding: 20px; }
.portfolio-toolbar { border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 12px; display:flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; }
.portfolio-title { margin: 0; }
.eyebrow { margin: 0; text-transform: uppercase; letter-spacing: 0.02em; color: var(--muted); font-size: 12px; }
.principles { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin: 12px 0; }
.portfolio-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.portfolio-stack { display: flex; flex-direction: column; gap: 16px; }
.client-block { border: 1px solid var(--border); border-radius: 14px; padding: 12px; background: var(--surface); }
.client-header { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.meta-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.portfolio-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 10px; }
.portfolio-card-grid { border: 1px solid var(--border); border-radius: 12px; padding: 12px; background: var(--surface-1); display: flex; flex-direction: column; gap: 10px; }
.portfolio-summary { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; }
.kpi-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; }
.kpi-chip { padding: 8px; border-radius: 10px; border: 1px solid var(--border); background: var(--surface-2); }
.tab-nav { display: flex; flex-wrap: wrap; gap: 6px; }
.tab-button { padding: 8px 10px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface-1); cursor: pointer; }
.tab-button.active { background: color-mix(in srgb, var(--primary) 12%, var(--surface-1)); color: var(--primary); border-color: var(--primary); }
.tab-content { border: 1px dashed var(--border); border-radius: 10px; padding: 10px; background: var(--surface); }
.hidden { display: none; }
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
.subtext { display: block; color: var(--muted); font-size: 12px; margin-top: 2px; }
.projects-list { display: flex; flex-direction: column; gap: 8px; }
.project-row { border: 1px solid var(--border); border-radius: 10px; padding: 10px; background: var(--surface-2); display: grid; gap: 6px; }
.project-kpis { display: flex; flex-wrap: wrap; gap: 6px; }
.pill { padding: 6px 10px; border-radius: 999px; background: var(--surface-2); border: 1px solid var(--border); font-weight: 600; }
.pill.subtle { background: var(--surface-1); font-weight: 500; }
.pill.neutral { background: transparent; border-style: dashed; color: var(--muted); font-weight: 500; }
.project-detail { display: grid; gap: 2px; }
.chip-grid { display: flex; flex-wrap: wrap; gap: 6px; }
.pillset { display: flex; gap: 6px; flex-wrap: wrap; }
</style>
