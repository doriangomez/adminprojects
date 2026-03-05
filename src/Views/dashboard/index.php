<div class="executive-dashboard">
    <style>
        .executive-dashboard {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-bottom: 10px;
        }
        .section-title {
            margin: 0 0 12px;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: .01em;
            color: var(--text-primary);
        }
        .card {
            background: color-mix(in srgb, var(--surface) 92%, var(--background));
            border: 1px solid color-mix(in srgb, var(--border) 86%, var(--background));
            border-radius: 16px;
            padding: 14px;
            box-shadow: 0 12px 26px color-mix(in srgb, var(--text-primary) 10%, transparent);
        }
        .card.dark {
            background: linear-gradient(140deg, #0f172a 0%, #111827 50%, #1e293b 100%);
            border-color: rgba(148, 163, 184, 0.25);
            color: #e2e8f0;
        }
        .hero-grid {
            display: grid;
            grid-template-columns: 1.25fr 1fr;
            gap: 16px;
        }
        .hero-main {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 10px;
            align-items: center;
        }
        .score-wrap { position: relative; width: 220px; height: 220px; margin: 0 auto; }
        .score-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .score-main { font-size: 42px; font-weight: 900; line-height: 1; }
        .score-sub { font-size: 12px; letter-spacing: .07em; text-transform: uppercase; opacity: .75; }
        .hero-title { margin: 0; font-size: 26px; font-weight: 900; color: #f8fafc; }
        .hero-copy { margin: 8px 0 0; color: #cbd5e1; font-size: 14px; line-height: 1.5; }
        .hero-side {
            display: grid;
            grid-template-columns: repeat(2, minmax(140px, 1fr));
            gap: 12px;
        }
        .stat-tile {
            padding: 14px;
            border-radius: 12px;
            background: color-mix(in srgb, var(--surface) 85%, var(--background));
            border: 1px solid color-mix(in srgb, var(--border) 70%, var(--background));
        }
        .dark .stat-tile {
            background: rgba(15, 23, 42, .5);
            border-color: rgba(148, 163, 184, .2);
        }
        .stat-label { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; opacity: .75; }
        .stat-value { margin-top: 4px; font-size: 30px; font-weight: 900; color: var(--text-primary); }
        .dark .stat-value { color: #f8fafc; }
        .trend-positive { color: var(--success); }
        .trend-negative { color: var(--danger); }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(220px, 1fr));
            gap: 10px;
        }
        .kpi-card { display: flex; align-items: center; gap: 12px; }
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 22%, var(--surface)), color-mix(in srgb, var(--primary) 14%, var(--background)));
            border: 1px solid color-mix(in srgb, var(--primary) 25%, var(--border));
            color: var(--primary);
            flex-shrink: 0;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 12%, transparent);
        }
        .kpi-icon svg { width: 24px; height: 24px; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .kpi-meta { display: flex; flex-direction: column; gap: 4px; }
        .kpi-meta .label { color: var(--text-secondary); font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
        .kpi-meta .value { color: var(--text-primary); font-size: 30px; font-weight: 900; line-height: 1; }
        .kpi-meta .variation { font-size: 13px; font-weight: 700; }
        .layout-two {
            display: grid;
            grid-template-columns: 1.3fr .9fr;
            gap: 10px;
        }
        .inner-two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .chart-card h4 { margin: 0 0 10px; font-size: 16px; color: var(--text-primary); }
        .chart-card canvas { width: 100%; min-height: 250px; }
        .alerts-critical {
            border: 1px solid color-mix(in srgb, var(--danger) 60%, var(--background));
            background: linear-gradient(120deg, color-mix(in srgb, var(--danger) 20%, var(--surface)), color-mix(in srgb, var(--danger) 10%, var(--surface)));
        }
        .alerts-critical h3 {
            margin: 0 0 8px;
            color: var(--danger);
            font-size: 18px;
        }
        .alerts-list { margin: 0; padding-left: 20px; }
        .alerts-list li { margin-bottom: 6px; font-weight: 600; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 8px; border-bottom: 1px solid color-mix(in srgb, var(--border) 72%, var(--background)); }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--text-secondary); text-align: left; }
        td { font-size: 14px; font-weight: 600; color: var(--text-primary); }
        .text-right { text-align: right; }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }
        .pill.green { background: color-mix(in srgb, var(--success) 18%, var(--background)); color: var(--success); }
        .pill.amber { background: color-mix(in srgb, var(--warning) 20%, var(--background)); color: #b45309; }
        .pill.red { background: color-mix(in srgb, var(--danger) 20%, var(--background)); color: var(--danger); }
        .split-three {
            display: grid;
            grid-template-columns: repeat(3, minmax(220px, 1fr));
            gap: 10px;
        }
        .metric-big { font-size: 34px; font-weight: 900; color: var(--text-primary); line-height: 1; }
        .metric-label { font-size: 12px; letter-spacing: .05em; color: var(--text-secondary); text-transform: uppercase; }
        .gov-grid { display: grid; grid-template-columns: repeat(4, minmax(160px, 1fr)); gap: 12px; }
        .gov-item { border-radius: 12px; padding: 12px; background: color-mix(in srgb, var(--surface) 85%, var(--background)); border: 1px solid color-mix(in srgb, var(--border) 70%, var(--background)); }
        .gov-item strong { display: block; font-size: 28px; color: var(--text-primary); }
        .gov-item-icon { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; margin-right: 6px; vertical-align: middle; }
        .gov-item-icon svg { width: 100%; height: 100%; stroke-linecap: round; stroke-linejoin: round; }
        .muted { color: var(--text-secondary); font-size: 13px; margin-top: 4px; }
        .ai-highlight {
            border: 1px solid color-mix(in srgb, #2563eb 45%, var(--border));
            background: linear-gradient(125deg, color-mix(in srgb, #1d4ed8 18%, var(--surface)), color-mix(in srgb, #0ea5e9 12%, var(--surface)));
        }
        .ai-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            color: var(--text-primary);
        }
        .ai-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            font-weight: 900;
            font-size: 13px;
            color: #1e3a8a;
            background: color-mix(in srgb, #93c5fd 70%, white);
        }
        .ai-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 12px; margin-top: 10px; }
        .ai-list { margin: 8px 0 0; padding-left: 18px; }
        .ai-list li { margin-bottom: 6px; font-weight: 600; color: var(--text-primary); }
        .criticality-pill {
            display: inline-flex;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            margin-left: 8px;
        }
        .criticality-pill.baja { background: color-mix(in srgb, var(--success) 20%, var(--background)); color: var(--success); }
        .criticality-pill.media { background: color-mix(in srgb, var(--warning) 24%, var(--background)); color: #b45309; }
        .criticality-pill.alta { background: color-mix(in srgb, var(--danger) 20%, var(--background)); color: var(--danger); }
        @media (max-width: 1200px) {
            .hero-grid, .layout-two, .inner-two { grid-template-columns: 1fr; }
            .kpi-grid { grid-template-columns: repeat(2, minmax(220px, 1fr)); }
            .hero-main { grid-template-columns: 1fr; }
            .ai-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .kpi-grid, .split-three, .gov-grid, .hero-side { grid-template-columns: 1fr; }
        }
    </style>

    <?php
    $statusCounts = $projects['status_counts'] ?? ['planning' => 0, 'en_curso' => 0, 'en_riesgo' => 0, 'cerrado' => 0];
    $progressByClient = $projects['progress_by_client'] ?? [];
    $staleProjects = $projects['stale_projects'] ?? [];
    $monthlyProgressTrend = $projects['monthly_progress_trend'] ?? [];
    $score = (int) ($portfolioHealth['average_score'] ?? 0);
    $scoreColor = $score > 80 ? '#16a34a' : ($score >= 60 ? '#f59e0b' : '#dc2626');
    $riskProjects = (int) ($summary['proyectos_riesgo'] ?? 0);
    $criticalProjects = count($portfolioInsights['top_risk'] ?? []);
    $trend = (int) ($portfolioInsights['portfolio_trend_avg'] ?? 0);
    $progressDelta = (float) ($summary['avance_promedio'] ?? 0) - 50;
    $hoursPlan = (float) ($summary['horas_planificadas'] ?? 0);
    $hoursReal = (float) ($summary['horas_reales'] ?? 0);
    $hoursDiffPct = $hoursPlan > 0 ? (($hoursReal - $hoursPlan) / $hoursPlan) * 100 : 0;
    $budget = (float) ($summary['presupuesto_total'] ?? 0);
    $cost = (float) ($summary['costo_real_total'] ?? 0);
    $costDiffPct = $budget > 0 ? (($cost - $budget) / $budget) * 100 : 0;
    $timesheetCompliance = (($timesheets['weekly_hours'] ?? 0) + ($timesheets['pending_hours'] ?? 0)) > 0
        ? (($timesheets['weekly_hours'] ?? 0) / (($timesheets['weekly_hours'] ?? 0) + ($timesheets['pending_hours'] ?? 0))) * 100
        : 100;
    $hasCriticalAlerts = !empty($staleProjects) || (($stoppers['critical_total'] ?? 0) > 0) || (($governance['critical_risks'] ?? 0) > 0) || (($timesheets['talents_without_report'] ?? 0) > 0);

    $portfolioRows = array_slice($portfolioInsights['ranking'] ?? [], 0, 10);
    usort($portfolioRows, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
    $executiveIntel = is_array($executiveIntel ?? null) ? $executiveIntel : [];
    $alertStrip = $executiveIntel['alerts'] ?? [];
    $movement = $executiveIntel['movement'] ?? [];
    $financialImpact = $executiveIntel['financial_impact'] ?? [];
    $riskExposure = (int) ($executiveIntel['risk_exposure'] ?? 0);
    $intelligentAnalysis = $executiveIntel['intelligent_analysis'] ?? [];
    $analysisInputs = $intelligentAnalysis['inputs'] ?? [];
    $analysisFlags = $intelligentAnalysis['flags'] ?? [];
    $analysisRecommendations = $intelligentAnalysis['recommendations'] ?? [];
    $analysisCriticality = strtolower((string) ($intelligentAnalysis['criticality'] ?? 'baja'));
    $projectHeatmapPoints = array_slice($portfolioInsights['ranking'] ?? [], 0, 30);
    $topBlockersProjects = array_slice($stoppers['top_active'] ?? [], 0, 5);
    $topTalents = array_slice($timesheets['hours_by_talent'] ?? [], 0, 5);

    $movementBadge = static function (array $metric, bool $inverse = false): string {
        $delta = (float) ($metric['delta_pct'] ?? 0);
        $isPositive = $inverse ? ($delta <= 0) : ($delta >= 0);
        $arrow = $delta >= 0 ? '↑' : '↓';
        $class = $isPositive ? 'trend-positive' : 'trend-negative';

        return '<span class="variation ' . $class . '">' . $arrow . ' ' . number_format(abs($delta), 1, ',', '.') . '% vs mes anterior</span>';
    };
    ?>

    <section>
        <div class="card ai-highlight">
            <h3 class="ai-title"><span class="ai-chip">IA</span> Análisis Inteligente
                <span class="criticality-pill <?= htmlspecialchars($analysisCriticality) ?>"><?= htmlspecialchars((string) ($intelligentAnalysis['criticality'] ?? 'Baja')) ?></span>
            </h3>
            <p class="muted" style="margin-top:6px;"><?= htmlspecialchars((string) ($intelligentAnalysis['diagnosis'] ?? 'Sin diagnóstico disponible.')) ?></p>
            <div class="ai-grid">
                <div>
                    <div class="metric-label">Diagnóstico resumido</div>
                    <div class="gov-grid" style="grid-template-columns:repeat(2,minmax(170px,1fr)); margin-top:8px;">
                        <div class="gov-item"><div class="metric-label">Score general</div><strong><?= number_format((float) ($analysisInputs['score_general'] ?? 0), 1, ',', '.') ?></strong></div>
                        <div class="gov-item"><div class="metric-label">Proyectos en riesgo</div><strong><?= (int) ($analysisInputs['projects_at_risk'] ?? 0) ?> (<?= number_format((float) ($analysisInputs['projects_at_risk_pct'] ?? 0), 1, ',', '.') ?>%)</strong></div>
                        <div class="gov-item"><div class="metric-label">Bloqueos activos</div><strong><?= (int) ($analysisInputs['active_blockers'] ?? 0) ?></strong></div>
                        <div class="gov-item"><div class="metric-label">Tendencia mensual</div><strong class="<?= ((float) ($analysisInputs['monthly_trend_pct'] ?? 0)) >= 0 ? 'trend-positive' : 'trend-negative' ?>"><?= ((float) ($analysisInputs['monthly_trend_pct'] ?? 0)) >= 0 ? '↑' : '↓' ?> <?= number_format(abs((float) ($analysisInputs['monthly_trend_pct'] ?? 0)), 1, ',', '.') ?>%</strong></div>
                        <div class="gov-item"><div class="metric-label">Facturación vs plan</div><strong><?= number_format((float) ($analysisInputs['billing_execution_pct'] ?? 0), 1, ',', '.') ?>%</strong></div>
                        <div class="gov-item"><div class="metric-label">Avance promedio</div><strong><?= number_format((float) ($analysisInputs['average_progress'] ?? 0), 1, ',', '.') ?>%</strong></div>
                    </div>
                </div>
                <div>
                    <div class="metric-label">Top 3 recomendaciones accionables</div>
                    <ol class="ai-list">
                        <?php foreach ($analysisRecommendations as $recommendation): ?>
                            <li><?= htmlspecialchars((string) $recommendation) ?></li>
                        <?php endforeach; ?>
                    </ol>
                    <?php if (!empty($analysisFlags)): ?>
                        <div class="metric-label" style="margin-top:12px;">Reglas activadas</div>
                        <ul class="ai-list">
                            <?php foreach ($analysisFlags as $flag): ?>
                                <li><strong><?= htmlspecialchars((string) ($flag['title'] ?? 'Alerta')) ?>:</strong> <?= htmlspecialchars((string) ($flag['detail'] ?? '')) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="card alerts-critical" style="padding:10px 14px;">
            <h3 style="margin-bottom:6px;">Centro de alertas</h3>
            <div class="gov-grid" style="grid-template-columns:repeat(4,minmax(170px,1fr));">
                <div class="gov-item gov-item--icon"><div class="metric-label"><span class="gov-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>Sin actualización &gt; 7 días</div><strong style="color:var(--danger)"><?= (int) ($alertStrip['stale_projects'] ?? 0) ?></strong></div>
                <div class="gov-item gov-item--icon"><div class="metric-label"><span class="gov-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>Proyectos con riesgo alto</div><strong style="color:var(--danger)"><?= (int) ($alertStrip['high_risk_projects'] ?? 0) ?></strong></div>
                <div class="gov-item gov-item--icon"><div class="metric-label"><span class="gov-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg></span>Bloqueos críticos abiertos</div><strong style="color:var(--danger)"><?= (int) ($alertStrip['critical_blockers'] ?? 0) ?></strong></div>
                <div class="gov-item gov-item--icon"><div class="metric-label"><span class="gov-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>Facturación pendiente</div><strong style="color:var(--danger)"><?= (int) ($alertStrip['billing_pending'] ?? 0) ?></strong></div>
            </div>
        </div>
    </section>

    <section>
        <h2 class="section-title">Resumen Ejecutivo</h2>
        <div class="hero-grid">
            <article class="card dark">
                <div class="hero-main">
                    <div class="score-wrap">
                        <canvas id="executiveScoreChart" width="220" height="220"></canvas>
                        <div class="score-center">
                            <span class="score-main"><?= $score ?> / 100</span>
                            <span class="score-sub">Score general</span>
                            <?= $movementBadge($movement['score'] ?? []) ?>
                        </div>
                    </div>
                    <div>
                        <h3 class="hero-title">Control de Portafolio</h3>
                        <p class="hero-copy">Visión integral de performance, riesgos y ejecución para toma de decisiones estratégicas del comité directivo.</p>
                    </div>
                </div>
            </article>
            <article class="card dark">
                <div class="hero-side">
                    <div class="stat-tile"><div class="stat-label">Proyectos activos</div><div class="stat-value"><?= (int) ($summary['proyectos_activos'] ?? 0) ?></div></div>
                    <div class="stat-tile"><div class="stat-label">En riesgo</div><div class="stat-value"><?= $riskProjects ?></div></div>
                    <div class="stat-tile"><div class="stat-label">Críticos</div><div class="stat-value"><?= $criticalProjects ?></div></div>
                    <div class="stat-tile">
                        <div class="stat-label">Exposición total al riesgo</div>
                        <div class="stat-value <?= $riskExposure > 40 ? 'trend-negative' : 'trend-positive' ?>"><?= $riskExposure ?></div>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section>
        <h2 class="section-title">Tendencia real vs mes anterior</h2>
        <div class="kpi-grid">
            <article class="card kpi-card"><div class="kpi-meta"><span class="label">Score general</span><span class="value"><?= number_format((float) (($movement['score']['current'] ?? 0)), 1, ',', '.') ?></span><?= $movementBadge($movement['score'] ?? []) ?></div></article>
            <article class="card kpi-card"><div class="kpi-meta"><span class="label">Proyectos en riesgo</span><span class="value"><?= (int) round((float) (($movement['risk_projects']['current'] ?? 0))) ?></span><?= $movementBadge($movement['risk_projects'] ?? [], true) ?></div></article>
            <article class="card kpi-card"><div class="kpi-meta"><span class="label">Bloqueos activos</span><span class="value"><?= (int) round((float) (($movement['active_blockers']['current'] ?? 0))) ?></span><?= $movementBadge($movement['active_blockers'] ?? [], true) ?></div></article>
            <article class="card kpi-card"><div class="kpi-meta"><span class="label">Facturación pendiente ($)</span><span class="value"><?= number_format((float) (($movement['billing_pending']['current'] ?? 0)), 0, ',', '.') ?></span><?= $movementBadge($movement['billing_pending'] ?? [], true) ?></div></article>
        </div>
    </section>

    <section>
        <h2 class="section-title">KPIs Financieros y Operativos</h2>
        <div class="kpi-grid">
            <article class="card kpi-card">
                <span class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 18h16"/><path d="M7 14V8"/><path d="M12 14V5"/><path d="M17 14v-3"/></svg></span>
                <div class="kpi-meta">
                    <span class="label">Avance promedio</span>
                    <span class="value"><?= number_format((float) ($summary['avance_promedio'] ?? 0), 1, ',', '.') ?>%</span>
                    <span class="variation <?= $progressDelta >= 0 ? 'trend-positive' : 'trend-negative' ?>"><?= $progressDelta >= 0 ? '↑' : '↓' ?> <?= number_format(abs($progressDelta), 1, ',', '.') ?> vs base</span>
                </div>
            </article>
            <article class="card kpi-card">
                <span class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M3 8h18"/><path d="M6 4v16"/><path d="M18 4v16"/></svg></span>
                <div class="kpi-meta">
                    <span class="label">Horas ejecutadas</span>
                    <span class="value"><?= number_format($hoursReal, 0, ',', '.') ?></span>
                    <span class="variation <?= $hoursDiffPct <= 0 ? 'trend-positive' : 'trend-negative' ?>"><?= $hoursDiffPct <= 0 ? '↓' : '↑' ?> <?= number_format(abs($hoursDiffPct), 1, ',', '.') ?>% vs plan</span>
                </div>
            </article>
            <article class="card kpi-card">
                <span class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg></span>
                <div class="kpi-meta">
                    <span class="label">Costo ejecutado</span>
                    <span class="value">$<?= number_format($cost, 0, ',', '.') ?></span>
                    <span class="variation <?= $costDiffPct <= 0 ? 'trend-positive' : 'trend-negative' ?>"><?= $costDiffPct <= 0 ? '↓' : '↑' ?> <?= number_format(abs($costDiffPct), 1, ',', '.') ?>% vs presupuesto</span>
                </div>
            </article>
            <article class="card kpi-card">
                <span class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M12 4a4 4 0 0 0-4 4v2a4 4 0 0 0 8 0V8a4 4 0 0 0-4-4Z"/><path d="M4 20a8 8 0 0 1 16 0"/></svg></span>
                <div class="kpi-meta">
                    <span class="label">Talento activo</span>
                    <span class="value"><?= (int) ($summary['talentos_activos'] ?? 0) ?></span>
                    <span class="variation <?= ($timesheets['talents_without_report'] ?? 0) > 0 ? 'trend-negative' : 'trend-positive' ?>"><?= ($timesheets['talents_without_report'] ?? 0) > 0 ? '↑' : '↓' ?> <?= (int) ($timesheets['talents_without_report'] ?? 0) ?> sin reporte</span>
                </div>
            </article>
        </div>
    </section>

    <section>
        <h2 class="section-title">Nivel 2: Riesgo + Finanzas</h2>
        <div class="split-three">
            <article class="card"><div class="metric-label">Total contratado</div><div class="metric-big">$<?= number_format((float) ($financialImpact['total_contracted'] ?? 0), 0, ',', '.') ?></div></article>
            <article class="card"><div class="metric-label">Total facturado</div><div class="metric-big">$<?= number_format((float) ($financialImpact['total_invoiced'] ?? 0), 0, ',', '.') ?></div></article>
            <article class="card"><div class="metric-label">Total cobrado</div><div class="metric-big">$<?= number_format((float) ($financialImpact['total_collected'] ?? 0), 0, ',', '.') ?></div></article>
        </div>
        <div class="split-three" style="margin-top:10px;">
            <article class="card"><div class="metric-label">% ejecución financiera</div><div class="metric-big"><?= number_format((float) ($financialImpact['execution_pct'] ?? 0), 1, ',', '.') ?>%</div></article>
            <article class="card"><div class="metric-label">Desviación presupuestal</div><div class="metric-big <?= ((float) ($financialImpact['budget_deviation_pct'] ?? 0)) <= 0 ? 'trend-positive' : 'trend-negative' ?>"><?= number_format((float) ($financialImpact['budget_deviation_pct'] ?? 0), 1, ',', '.') ?>%</div></article>
            <article class="card"><div class="metric-label">Exposición total al riesgo</div><div class="metric-big <?= $riskExposure > 40 ? 'trend-negative' : 'trend-positive' ?>"><?= $riskExposure ?></div></article>
        </div>
    </section>

    <section>
        <h2 class="section-title">Mapa de calor del portafolio (Riesgo x Avance)</h2>
        <article class="card chart-card"><canvas id="portfolioHeatmapChart" height="220"></canvas></article>
    </section>

    <section>
        <h2 class="section-title">Salud Operativa</h2>
        <div class="layout-two">
            <div class="inner-two">
                <article class="card chart-card">
                    <h4>Estado del portafolio</h4>
                    <canvas id="statusChart" height="260"></canvas>
                </article>
                <article class="card chart-card">
                    <h4>Avance por cliente</h4>
                    <canvas id="progressChart" height="260"></canvas>
                </article>
            </div>
            <div class="inner-two">
                <article class="card chart-card">
                    <h4>Tendencia mensual de avance</h4>
                    <canvas id="monthlyProgressChart" height="260"></canvas>
                </article>
                <article class="card chart-card">
                    <h4>Bloqueos por severidad</h4>
                    <canvas id="blockersSeverityChart" height="260"></canvas>
                </article>
            </div>
        </div>
    </section>

    <?php if ($hasCriticalAlerts): ?>
        <section>
            <div class="card alerts-critical">
                <h3>⚠ Alertas críticas</h3>
                <ul class="alerts-list">
                    <?php if (!empty($staleProjects)): ?>
                        <li><?= count($staleProjects) ?> proyectos sin avance reciente.</li>
                    <?php endif; ?>
                    <?php if (($stoppers['critical_total'] ?? 0) > 0): ?>
                        <li><?= (int) ($stoppers['critical_total'] ?? 0) ?> bloqueos críticos abiertos.</li>
                    <?php endif; ?>
                    <?php if (($governance['critical_risks'] ?? 0) > 0): ?>
                        <li><?= (int) ($governance['critical_risks'] ?? 0) ?> riesgos críticos registrados.</li>
                    <?php endif; ?>
                    <?php if (($timesheets['talents_without_report'] ?? 0) > 0): ?>
                        <li><?= (int) ($timesheets['talents_without_report'] ?? 0) ?> talentos sin seguimiento de horas.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>

    <section>
        <h2 class="section-title">Portfolio Ranking</h2>
        <div class="card table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Proyecto</th>
                        <th>Cliente</th>
                        <th class="text-right">Score</th>
                        <th>Riesgo</th>
                        <th class="text-right">Bloqueos</th>
                        <th class="text-right">Facturación</th>
                        <th>Última actualización</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($portfolioRows as $row): ?>
                        <?php
                        $riskLabel = (string) ($row['risk'] ?? 'Medio');
                        $riskClass = $riskLabel === 'Bajo' ? 'green' : ($riskLabel === 'Medio' ? 'amber' : 'red');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['client'] ?? '')) ?></td>
                            <td class="text-right"><?= (int) ($row['score'] ?? 0) ?></td>
                            <td><span class="pill <?= $riskClass ?>"><?= htmlspecialchars($riskLabel) ?></span></td>
                            <td class="text-right"><?= (int) ($row['blockers_open'] ?? 0) ?></td>
                            <td class="text-right">$<?= number_format((float) ($row['billing'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars((string) ($row['updated_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2 class="section-title">Stoppers (Bloqueos)</h2>
        <div class="split-three">
            <article class="card"><div class="metric-label">Total abiertos</div><div class="metric-big"><?= (int) ($stoppers['open_total'] ?? 0) ?></div></article>
            <article class="card"><div class="metric-label">Críticos</div><div class="metric-big" style="color:var(--danger)"><?= (int) ($stoppers['critical_total'] ?? 0) ?></div></article>
            <article class="card"><div class="metric-label">Tiempo promedio de resolución</div><div class="metric-big"><?= (int) ($stoppers['avg_open_days'] ?? 0) ?> días</div></article>
        </div>
        <article class="card chart-card" style="margin-top:10px;">
            <h4>Tendencia mensual de bloqueos</h4>
            <canvas id="stoppersTrendChart" height="220"></canvas>
        </article>
        <div class="split-three" style="margin-top:10px;">
            <article class="card">
                <div class="metric-label">Top proyectos con más bloqueos</div>
                <ul class="alerts-list"><?php foreach ($topBlockersProjects as $item): ?><li><?= htmlspecialchars((string) ($item['project'] ?? 'Proyecto')) ?> (<?= (int) ($item['active_total'] ?? 0) ?>)</li><?php endforeach; ?></ul>
            </article>
            <article class="card" style="grid-column: span 2;"><div class="metric-label">Distribución por tipo</div><canvas id="blockersByTypeChart" height="120"></canvas></article>
        </div>
    </section>

    <section>
        <h2 class="section-title">Talento y Timesheets</h2>
        <div class="split-three">
            <article class="card"><div class="metric-label">Cumplimiento</div><div class="metric-big"><?= number_format($timesheetCompliance, 1, ',', '.') ?>%</div></article>
            <article class="card"><div class="metric-label">Horas vs plan</div><div class="metric-big"><?= number_format((float) ($timesheets['weekly_hours'] ?? 0), 1, ',', '.') ?> / <?= number_format($hoursPlan > 0 ? $hoursPlan / 4 : 0, 1, ',', '.') ?></div></article>
            <article class="card"><div class="metric-label">Talentos sin reporte</div><div class="metric-big" style="color:<?= ($timesheets['talents_without_report'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= (int) ($timesheets['talents_without_report'] ?? 0) ?></div></article>
        </div>
        <p class="muted">Semana <?= htmlspecialchars((string) ($timesheets['period_start'] ?? '')) ?> - <?= htmlspecialchars((string) ($timesheets['period_end'] ?? '')) ?></p>
        <div class="split-three" style="margin-top:10px;">
            <article class="card"><div class="metric-label">Top 5 talentos por horas</div><ul class="alerts-list"><?php foreach ($topTalents as $talent): ?><li><?= htmlspecialchars((string) ($talent['talent'] ?? 'Talento')) ?> (<?= number_format((float) ($talent['total_hours'] ?? 0), 1, ',', '.') ?>h)</li><?php endforeach; ?></ul></article>
            <article class="card"><div class="metric-label">Talentos con menor cumplimiento</div><ul class="alerts-list"><?php foreach (array_reverse($topTalents) as $talent): ?><li><?= htmlspecialchars((string) ($talent['talent'] ?? 'Talento')) ?></li><?php endforeach; ?></ul></article>
            <article class="card"><div class="metric-label">% utilización promedio</div><div class="metric-big"><?= number_format($timesheetCompliance, 1, ',', '.') ?>%</div></article>
        </div>
    </section>

    <section>
        <h2 class="section-title">Gobierno y Control</h2>
        <div class="gov-grid">
            <?php
            $govItems = [
                ['label' => 'Documentos pendientes', 'value' => (int) (($governance['documents_revision'] ?? 0) + ($governance['documents_validacion'] ?? 0) + ($governance['documents_aprobacion'] ?? 0)), 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'],
                ['label' => 'Cambios sin aprobar', 'value' => (int) ($governance['scope_changes_pending'] ?? 0), 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-9-9"/><path d="M21 3v6h-6"/></svg>'],
                ['label' => 'Riesgos críticos', 'value' => (int) ($governance['critical_risks'] ?? 0), 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'],
                ['label' => 'Facturación pendiente', 'value' => (int) ($outsourcing['open_followups'] ?? 0), 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>'],
            ];
            foreach ($govItems as $item):
                $tone = $item['value'] > 0 ? 'var(--danger)' : 'var(--success)';
            ?>
                <article class="gov-item gov-item--icon">
                    <div class="metric-label"><span class="gov-item-icon"><?= $item['svg'] ?></span><?= htmlspecialchars($item['label']) ?></div>
                    <strong style="color:<?= $tone ?>"><?= (int) $item['value'] ?></strong>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const statusData = [
        <?= (int) ($statusCounts['planning'] ?? 0) ?>,
        <?= (int) ($statusCounts['en_curso'] ?? 0) ?>,
        <?= (int) ($statusCounts['en_riesgo'] ?? 0) ?>,
        <?= (int) ($statusCounts['cerrado'] ?? 0) ?>,
    ];
    const progressLabels = <?= json_encode(array_map(static fn ($row) => $row['client'] ?? '', $progressByClient)) ?>;
    const progressData = <?= json_encode(array_map(static fn ($row) => round((float) ($row['avg_progress'] ?? 0), 1), $progressByClient)) ?>;
    const monthlyProgressLabels = <?= json_encode(array_map(static fn ($row) => $row['month_key'] ?? '', $monthlyProgressTrend)) ?>;
    const monthlyProgressData = <?= json_encode(array_map(static fn ($row) => round((float) ($row['avg_progress'] ?? 0), 1), $monthlyProgressTrend)) ?>;
    const blockersSeverityData = <?= json_encode([
        (int) (($stoppers['severity_counts']['critico'] ?? 0)),
        (int) (($stoppers['severity_counts']['alto'] ?? 0)),
        (int) (($stoppers['severity_counts']['medio'] ?? 0)),
        (int) (($stoppers['severity_counts']['bajo'] ?? 0)),
    ]) ?>;
    const stoppersMonthlyLabels = <?= json_encode(array_map(static fn ($row) => $row['month_key'] ?? '', $stoppers['monthly_trend'] ?? [])) ?>;
    const stoppersMonthlyData = <?= json_encode(array_map(static fn ($row) => (int) ($row['total'] ?? 0), $stoppers['monthly_trend'] ?? [])) ?>;
    const heatmapPoints = <?= json_encode(array_map(static fn (array $row) => [
        'x' => max(0, min(100, 100 - (int) ($row['score'] ?? 0))),
        'y' => max(0, min(100, (int) ($row['score'] ?? 0))),
        'label' => ($row['name'] ?? 'Proyecto') . ' · ' . ($row['client'] ?? 'Cliente'),
    ], $projectHeatmapPoints)) ?>;
    const blockersByTypeData = <?= json_encode([
        (int) (($stoppers['severity_counts']['critico'] ?? 0)),
        (int) (($stoppers['severity_counts']['alto'] ?? 0)),
        (int) (($stoppers['severity_counts']['medio'] ?? 0)),
        (int) (($stoppers['severity_counts']['bajo'] ?? 0)),
    ]) ?>;

    new Chart(document.getElementById('executiveScoreChart'), {
        type: 'doughnut',
        data: {
            labels: ['Score', 'Pendiente'],
            datasets: [{ data: [<?= $score ?>, <?= max(0, 100 - $score) ?>], backgroundColor: ['<?= $scoreColor ?>', 'rgba(148,163,184,.24)'], borderWidth: 0 }]
        },
        options: { cutout: '78%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { labels: ['Planning', 'En curso', 'En riesgo', 'Cerrado'], datasets: [{ data: statusData, backgroundColor: ['#38bdf8', '#2563eb', '#f97316', '#64748b'] }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
    });

    new Chart(document.getElementById('progressChart'), {
        type: 'bar',
        data: { labels: progressLabels, datasets: [{ data: progressData, borderRadius: 10, backgroundColor: '#2563eb' }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
    });

    new Chart(document.getElementById('monthlyProgressChart'), {
        type: 'line',
        data: { labels: monthlyProgressLabels, datasets: [{ data: monthlyProgressData, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.15)', fill: true, tension: .35 }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
    });

    new Chart(document.getElementById('blockersSeverityChart'), {
        type: 'bar',
        data: {
            labels: ['Crítico', 'Alto', 'Medio', 'Bajo'],
            datasets: [{ data: blockersSeverityData, backgroundColor: ['#dc2626', '#f97316', '#facc15', '#22c55e'], borderRadius: 10 }]
        },
        options: { plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('stoppersTrendChart'), {
        type: 'line',
        data: {
            labels: stoppersMonthlyLabels,
            datasets: [{ label: 'Bloqueos', data: stoppersMonthlyData, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.18)', fill: true, tension: .35 }]
        },
        options: { plugins: { legend: { display: true } } }
    });

    new Chart(document.getElementById('portfolioHeatmapChart'), {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Proyectos',
                data: heatmapPoints,
                pointRadius: 6,
                backgroundColor: '#2563eb'
            }]
        },
        options: {
            scales: {
                x: { min: 0, max: 100, title: { display: true, text: 'Riesgo' } },
                y: { min: 0, max: 100, title: { display: true, text: 'Avance' } }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (ctx) => ctx.raw?.label || 'Proyecto'
                    }
                },
                legend: { display: false }
            }
        }
    });

    new Chart(document.getElementById('blockersByTypeChart'), {
        type: 'bar',
        data: {
            labels: ['Cliente', 'Técnico', 'Operativo', 'Otros'],
            datasets: [{ data: blockersByTypeData, backgroundColor: ['#dc2626', '#f97316', '#facc15', '#22c55e'], borderRadius: 8 }]
        },
        options: { plugins: { legend: { display: false } } }
    });
</script>
