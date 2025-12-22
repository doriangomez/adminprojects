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

$kpiDefaults = [
    'projects_total' => 0,
    'projects_active' => 0,
    'progress_avg' => 0.0,
    'risk_level' => 'bajo',
    'avg_progress' => 0.0,
    'active_projects' => 0,
    'total_projects' => 0,
    'budget_used' => 0.0,
    'budget_planned' => 0.0,
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
    <div class="portfolio-toolbar">
        <div>
            <p class="eyebrow">Crear ≠ Analizar</p>
            <h2 class="portfolio-title">Portafolio por cliente</h2>
            <p class="muted">Vista ejecutiva de análisis. Sin formularios ni creación aquí: solo lectura, resumen y drilldown.</p>
        </div>
        <div class="portfolio-actions">
            <a class="action-button ghost" href="<?= $basePath ?>/projects">
                <span class="icon" aria-hidden="true">↗</span>
                Ir a proyectos
            </a>
            <a class="action-button primary" href="<?= $basePath ?>/portfolio/create">
                <span class="icon" aria-hidden="true">＋</span>
                Nuevo portafolio (wizard)
            </a>
        </div>
    </div>

    <section class="intro-card">
        <div class="intro-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7">
                <path d="M4 7h16M4 12h10M4 17h6" />
                <path d="m16 11 4 1-4 1 4 1-4 1" />
            </svg>
        </div>
        <div>
            <p class="eyebrow">Visión ejecutiva</p>
            <h3 class="intro-title">Portafolio por cliente</h3>
            <p class="muted">Vista ejecutiva de análisis. Sin formularios ni creación aquí: solo lectura, resumen y drilldown.</p>
        </div>
        <div class="intro-pillset">
            <span class="pill soft-blue">Ejecución</span>
            <span class="pill soft-green">Claridad</span>
            <span class="pill soft-amber">Seguimiento</span>
        </div>
    </section>

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
            <?php
                $client = $group['client'];
                $clientName = $client['name'] ?? 'Cliente sin nombre';
                $portfolioCount = count($group['portfolios']);
                $activeProjectsCount = array_sum(array_map(fn ($p) => count(is_array($p['projects'] ?? null) ? array_filter($p['projects'], fn ($proj) => ($proj['status'] ?? 'activo') === 'activo') : []), $group['portfolios']));
                $allProgress = [];
                $riskLevels = [];
                foreach ($group['portfolios'] as $p) {
                    $kpisLoop = array_merge($kpiDefaults, is_array($p['kpis'] ?? null) ? $p['kpis'] : []);
                    if (isset($kpisLoop['avg_progress'])) {
                        $allProgress[] = (float) $kpisLoop['avg_progress'];
                    }
                    $riskLevels[] = $kpisLoop['risk_level'] ?? null;
                }
                $overallProgress = $allProgress ? round(array_sum($allProgress) / count($allProgress), 1) : 0;
                $riskPriority = ['alto' => 3, 'medio' => 2, 'bajo' => 1];
                $topRiskLevel = 'bajo';
                foreach ($riskLevels as $level) {
                    if (($riskPriority[$level] ?? 0) > ($riskPriority[$topRiskLevel] ?? 0)) {
                        $topRiskLevel = $level;
                    }
                }
                $topRiskText = $riskLevelText[$topRiskLevel] ?? 'Riesgo no calculado';
                $clientInitial = strtoupper(mb_substr($clientName, 0, 1, 'UTF-8'));
            ?>
            <section class="client-block">
                <header class="client-header">
                    <div class="client-identity">
                        <?php if (!empty($client['logo'])): ?>
                            <div class="client-avatar has-logo">
                                <img src="<?= htmlspecialchars($client['logo']) ?>" alt="Logo <?= htmlspecialchars($clientName) ?>">
                            </div>
                        <?php else: ?>
                            <div class="client-avatar" aria-hidden="true"><?= htmlspecialchars($clientInitial) ?></div>
                        <?php endif; ?>
                        <div>
                            <p class="eyebrow">Cliente</p>
                            <h3><?= htmlspecialchars($clientName) ?></h3>
                            <p class="muted"><?= htmlspecialchars($client['sector_label'] ?? 'Sector no registrado') ?> · <?= htmlspecialchars($client['category_label'] ?? 'Categoría no registrada') ?></p>
                        </div>
                    </div>
                    <div class="meta-badges">
                        <span class="pill kpi-pill soft-blue">
                            <span class="icon" aria-hidden="true">📁</span>
                            Portafolios: <?= $portfolioCount ?>
                        </span>
                        <span class="pill kpi-pill soft-green">
                            <span class="icon" aria-hidden="true">🚀</span>
                            Proyectos activos: <?= $activeProjectsCount ?>
                        </span>
                        <span class="pill kpi-pill soft-amber">
                            <span class="icon" aria-hidden="true">📊</span>
                            Avance global: <?= $overallProgress ?>%
                        </span>
                        <span class="pill kpi-pill soft-slate">
                            <span class="icon" aria-hidden="true">⚠️</span>
                            Riesgo: <?= htmlspecialchars($topRiskText) ?>
                        </span>
                    </div>
                </header>

                <div class="portfolio-grid">
                    <?php foreach ($group['portfolios'] as $portfolio): ?>
                        <?php
                            $projects = is_array($portfolio['projects'] ?? null) ? $portfolio['projects'] : [];
                            $kpis = array_merge($kpiDefaults, is_array($portfolio['kpis'] ?? null) ? $portfolio['kpis'] : []);
                            $budgetTotal = (float) ($portfolio['budget_total'] ?? 0);
                            $budgetUsed = (float) ($kpis['budget_used'] ?? 0);
                            $budgetRatio = $budgetTotal > 0 ? round(($budgetUsed / $budgetTotal) * 100, 1) : null;
                            $riskText = $riskLevelText[$kpis['risk_level'] ?? ''] ?? 'Riesgo no calculado';
                            $generalStatus = $signalTextMap[$portfolio['signal']['code'] ?? ''] ?? 'Estado no disponible';
                            $portfolioId = 'pf-' . $portfolio['id'];
                            $hasScrum = array_filter($projects, fn ($project) => in_array($project['project_type'] ?? '', ['agil', 'scrum', 'agile'], true));
                            $alerts = is_array($portfolio['alerts'] ?? null) ? $portfolio['alerts'] : [];
                        ?>
                        <article class="portfolio-card-grid" id="<?= htmlspecialchars($portfolioId) ?>">
                            <header class="portfolio-summary">
                                <div class="portfolio-heading">
                                    <div class="portfolio-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6">
                                            <path d="M4 8h16v10H4z" />
                                            <path d="M4 6h6l2 2h8" />
                                            <path d="M8 12h8" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="eyebrow">Portafolio</p>
                                        <h4><?= htmlspecialchars($portfolio['name']) ?></h4>
                                        <p class="muted"><?= htmlspecialchars($portfolio['signal']['summary'] ?? 'Sin resumen cargado') ?></p>
                                    </div>
                                </div>
                                <div class="kpi-strip">
                                    <div class="kpi-chip">
                                        <div class="chip-top">
                                            <span class="icon" aria-hidden="true">📈</span>
                                            <small>Avance global</small>
                                        </div>
                                        <strong><?= $kpis['avg_progress'] ?>%</strong>
                                        <div class="micro-track" aria-hidden="true">
                                            <span class="micro-bar" style="width: <?= max(0, min(100, (float) $kpis['avg_progress'])) ?>%"></span>
                                        </div>
                                    </div>
                                    <div class="kpi-chip">
                                        <div class="chip-top">
                                            <span class="icon" aria-hidden="true">💰</span>
                                            <small>Ejecución vs presupuesto</small>
                                        </div>
                                        <strong><?= $budgetRatio !== null ? $budgetRatio . '%' : 'N/D' ?></strong>
                                        <div class="micro-track" aria-hidden="true">
                                            <span class="micro-bar soft-amber" style="width: <?= $budgetRatio !== null ? max(0, min(100, $budgetRatio)) : 0 ?>%"></span>
                                        </div>
                                        <span class="subtext">Real: $<?= number_format((float) $budgetUsed, 0, ',', '.') ?> <?= $budgetTotal ? '/ $' . number_format((float) $budgetTotal, 0, ',', '.') : '' ?></span>
                                    </div>
                                    <div class="kpi-chip">
                                        <div class="chip-top">
                                            <span class="icon" aria-hidden="true">🛡</span>
                                            <small>Nivel de riesgo</small>
                                        </div>
                                        <strong><?= $riskText ?></strong>
                                        <span class="subtext">Estado general: <?= $generalStatus ?></span>
                                    </div>
                                    <div class="kpi-chip">
                                        <div class="chip-top">
                                            <span class="icon" aria-hidden="true">📁</span>
                                            <small>Proyectos activos</small>
                                        </div>
                                        <strong><?= $kpis['active_projects'] ?>/<?= $kpis['total_projects'] ?></strong>
                                        <span class="subtext">Operación se controla en cada proyecto.</span>
                                    </div>
                                </div>
                            </header>

                            <div class="tab-nav" role="tablist" aria-label="Drilldown del portafolio">
                                <button class="tab-button active" data-tab="summary-<?= $portfolioId ?>">Resumen</button>
                                <button class="tab-button" data-tab="projects-<?= $portfolioId ?>">Proyectos</button>
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
                                            </div>
                                            <div class="project-detail">
                                                <small>Alertas</small>
                                                <p class="muted"><?= htmlspecialchars(implode(' · ', $project['signal']['reasons'])) ?></p>
                                                <small>Operación</small>
                                                <p class="muted">Los detalles de costos y horas se gestionan en el proyecto.</p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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
    :root {
        --blue-soft: #e6f0ff;
        --green-soft: #e5f6ef;
        --amber-soft: #fff4e5;
        --slate-soft: #eef2f7;
        --border-strong: rgba(15, 23, 42, 0.08);
        --text-strong: #0f172a;
        --muted-strong: #475569;
    }

    .portfolio-card {
        padding: 22px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 60%);
        border: 1px solid var(--border-strong);
        border-radius: 16px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    }
    .portfolio-toolbar {
        display:flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        padding-bottom: 6px;
    }
    .portfolio-title { margin: 0; color: var(--text-strong); }
    .eyebrow { margin: 0; text-transform: uppercase; letter-spacing: 0.02em; color: var(--muted-strong); font-size: 12px; }
    .portfolio-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .action-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        border: 1px solid rgba(59, 130, 246, 0.24);
        color: #1d4ed8;
        background: rgba(59, 130, 246, 0.12);
        box-shadow: 0 8px 18px rgba(59, 130, 246, 0.12);
    }
    .action-button.ghost {
        background: #ffffff;
        color: #0f172a;
        border-color: rgba(15, 23, 42, 0.12);
        box-shadow: none;
    }
    .action-button.primary {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.16), rgba(16, 185, 129, 0.16));
        color: #0f172a;
        border-color: rgba(59, 130, 246, 0.28);
    }
    .action-button .icon { font-size: 14px; }

    .intro-card {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 14px;
        align-items: center;
        padding: 16px 18px;
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(229, 243, 255, 0.9), rgba(237, 247, 237, 0.9));
        border: 1px solid rgba(148, 163, 184, 0.35);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }
    .intro-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(59, 130, 246, 0.12);
        color: #1d4ed8;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    .intro-icon svg { width: 32px; height: 32px; stroke: currentColor; }
    .intro-title { margin: 4px 0 6px; font-size: 20px; color: var(--text-strong); }
    .muted { color: var(--muted-strong); font-size: 14px; margin: 0; }
    .intro-pillset, .pillset { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }

    .principles { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin: 4px 0 8px; }
    .card.subtle-card { background: #ffffff; border: 1px dashed rgba(148, 163, 184, 0.3); border-radius: 12px; box-shadow: none; }
    .badge.neutral { background: var(--slate-soft); padding: 6px 10px; border-radius: 10px; font-weight: 700; color: #0f172a; display: inline-block; }

    .portfolio-stack { display: flex; flex-direction: column; gap: 16px; }
    .client-block {
        border: 1px solid rgba(148, 163, 184, 0.24);
        border-radius: 16px;
        padding: 14px;
        background: linear-gradient(180deg, #ffffff, #f9fbff);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
    }
    .client-header { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom:14px; }
    .client-identity { display: flex; gap: 10px; align-items: center; }
    .client-avatar {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: var(--slate-soft);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        color: #0f172a;
        border: 1px solid rgba(148, 163, 184, 0.4);
    }
    .client-avatar.has-logo { padding: 6px; background: #ffffff; }
    .client-avatar img { width: 100%; height: 100%; object-fit: contain; border-radius: 10px; }
    .meta-badges { display: flex; gap: 8px; flex-wrap: wrap; }
    .pill {
        padding: 7px 12px;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.4);
        background: #ffffff;
        font-weight: 700;
        display: inline-flex;
        gap: 6px;
        align-items: center;
        color: #0f172a;
    }
    .pill.subtle { background: var(--slate-soft); font-weight: 600; }
    .pill.neutral { background: transparent; border-style: dashed; color: var(--muted-strong); font-weight: 600; }
    .pill.kpi-pill { box-shadow: 0 6px 14px rgba(148, 163, 184, 0.14); }
    .pill.soft-blue { background: var(--blue-soft); border-color: rgba(59, 130, 246, 0.3); color: #1d4ed8; }
    .pill.soft-green { background: var(--green-soft); border-color: rgba(16, 185, 129, 0.28); color: #0f766e; }
    .pill.soft-amber { background: var(--amber-soft); border-color: rgba(251, 191, 36, 0.34); color: #b45309; }
    .pill.soft-slate { background: var(--slate-soft); border-color: rgba(148, 163, 184, 0.4); color: #0f172a; }

    .portfolio-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 12px; }
    .portfolio-card-grid {
        border: 1px solid rgba(148, 163, 184, 0.28);
        border-radius: 14px;
        padding: 12px 14px;
        background: linear-gradient(180deg, #ffffff, #f6f9ff);
        display: flex;
        flex-direction: column;
        gap: 12px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
    }
    .portfolio-summary { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; }
    .portfolio-heading { display: flex; gap: 10px; align-items: center; }
    .portfolio-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(59, 130, 246, 0.12);
        color: #1d4ed8;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    .portfolio-icon svg { width: 26px; height: 26px; stroke: currentColor; }

    .kpi-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
    .kpi-chip {
        padding: 10px;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        background: #ffffff;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 8px 16px rgba(148, 163, 184, 0.12);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .chip-top { display: flex; align-items: center; gap: 6px; color: #475569; font-weight: 700; }
    .micro-track {
        width: 100%;
        height: 8px;
        border-radius: 999px;
        background: rgba(226, 232, 240, 0.7);
        overflow: hidden;
    }
    .micro-bar {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.75), rgba(16, 185, 129, 0.75));
        border-radius: 999px;
    }
    .micro-bar.soft-blue { background: linear-gradient(90deg, rgba(59, 130, 246, 0.8), rgba(59, 130, 246, 0.6)); }
    .micro-bar.soft-amber { background: linear-gradient(90deg, rgba(251, 191, 36, 0.85), rgba(249, 115, 22, 0.7)); }

    .tab-nav { display: flex; flex-wrap: wrap; gap: 8px; }
    .tab-button {
        padding: 9px 12px;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.35);
        background: #ffffff;
        cursor: pointer;
        font-weight: 700;
        color: #0f172a;
        box-shadow: 0 6px 14px rgba(148, 163, 184, 0.16);
    }
    .tab-button.active { background: linear-gradient(135deg, rgba(59, 130, 246, 0.16), rgba(16, 185, 129, 0.12)); color: #0f172a; border-color: rgba(59, 130, 246, 0.35); }
    .tab-content { border: 1px dashed rgba(148, 163, 184, 0.4); border-radius: 12px; padding: 12px; background: #ffffff; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8); }
    .hidden { display: none; }
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
    .subtext { display: block; color: #64748b; font-size: 12px; margin-top: 2px; }
    .projects-list { display: flex; flex-direction: column; gap: 10px; }
    .project-row { border: 1px solid rgba(148, 163, 184, 0.3); border-radius: 12px; padding: 12px; background: #f8fafc; display: grid; gap: 6px; box-shadow: 0 6px 12px rgba(148, 163, 184, 0.1); }
    .project-kpis { display: flex; flex-wrap: wrap; gap: 8px; }
    .project-detail { display: grid; gap: 2px; }
    .chip-grid { display: flex; flex-wrap: wrap; gap: 8px; }
</style>
