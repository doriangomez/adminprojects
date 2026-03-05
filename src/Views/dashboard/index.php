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
            background: color-mix(in srgb, var(--primary) 18%, var(--background));
            border: 1px solid color-mix(in srgb, var(--primary) 30%, var(--background));
            color: var(--primary);
            flex-shrink: 0;
        }
        .kpi-icon svg { width: 24px; height: 24px; stroke: currentColor; }
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

        /* ── Análisis automático ── */
        .auto-analysis-card {
            border: 1px solid color-mix(in srgb, var(--primary) 35%, var(--border));
            background: linear-gradient(130deg, color-mix(in srgb, var(--primary) 8%, var(--surface)), color-mix(in srgb, #0ea5e9 6%, var(--surface)));
        }
        .auto-analysis-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .auto-analysis-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: color-mix(in srgb, var(--primary) 16%, var(--background));
            border: 1px solid color-mix(in srgb, var(--primary) 28%, var(--border));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .auto-analysis-meta { flex: 1; }
        .auto-analysis-meta .title { font-size: 16px; font-weight: 800; color: var(--text-primary); margin: 0; }
        .auto-analysis-meta .subtitle { font-size: 12px; color: var(--text-secondary); margin-top: 2px; text-transform: uppercase; letter-spacing: .05em; }
        .conclusion-list { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .conclusion-list li {
            padding: 10px 14px;
            border-radius: 10px;
            background: color-mix(in srgb, var(--surface) 85%, var(--background));
            border: 1px solid color-mix(in srgb, var(--border) 60%, var(--background));
            border-left: 3px solid var(--primary);
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.45;
        }

        /* ── Alertas del portafolio ── */
        .alert-cards-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(200px, 1fr));
            gap: 10px;
        }
        .alert-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            border-radius: 14px;
            border: 1px solid;
        }
        .alert-card.green {
            background: color-mix(in srgb, #16a34a 8%, var(--surface));
            border-color: color-mix(in srgb, #16a34a 35%, var(--border));
        }
        .alert-card.yellow {
            background: color-mix(in srgb, #f59e0b 10%, var(--surface));
            border-color: color-mix(in srgb, #f59e0b 35%, var(--border));
        }
        .alert-card.red {
            background: color-mix(in srgb, #dc2626 10%, var(--surface));
            border-color: color-mix(in srgb, #dc2626 35%, var(--border));
        }
        .alert-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .alert-card.green .alert-icon-box { background: color-mix(in srgb, #16a34a 16%, var(--background)); }
        .alert-card.yellow .alert-icon-box { background: color-mix(in srgb, #f59e0b 16%, var(--background)); }
        .alert-card.red .alert-icon-box { background: color-mix(in srgb, #dc2626 16%, var(--background)); }
        .alert-body { flex: 1; min-width: 0; }
        .alert-status-row { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
        .alert-dot { width: 8px; height: 8px; border-radius: 999px; flex-shrink: 0; }
        .alert-card.green .alert-dot { background: #16a34a; box-shadow: 0 0 6px #16a34a88; }
        .alert-card.yellow .alert-dot { background: #f59e0b; box-shadow: 0 0 6px #f59e0b88; }
        .alert-card.red .alert-dot { background: #dc2626; box-shadow: 0 0 6px #dc262688; }
        .alert-title { font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .alert-card.green .alert-title { color: #15803d; }
        .alert-card.yellow .alert-title { color: #92400e; }
        .alert-card.red .alert-title { color: #991b1b; }
        .alert-condition { font-size: 11px; color: var(--text-secondary); margin-top: 2px; font-style: italic; }
        .alert-message { font-size: 13px; font-weight: 600; color: var(--text-primary); margin-top: 6px; line-height: 1.4; }

        /* ── Recomendaciones ── */
        .rec-card {
            border: 1px solid color-mix(in srgb, #f59e0b 28%, var(--border));
            background: linear-gradient(130deg, color-mix(in srgb, #f59e0b 6%, var(--surface)), color-mix(in srgb, #f97316 4%, var(--surface)));
        }
        .rec-list { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .rec-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: color-mix(in srgb, var(--surface) 85%, var(--background));
            border: 1px solid color-mix(in srgb, var(--border) 60%, var(--background));
        }
        .rec-number {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: color-mix(in srgb, #f59e0b 20%, var(--background));
            border: 1px solid color-mix(in srgb, #f59e0b 40%, var(--border));
            color: #92400e;
            font-size: 12px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .rec-text { font-size: 14px; font-weight: 600; color: var(--text-primary); line-height: 1.45; padding-top: 2px; }

        /* ── Semáforo ── */
        .semaforo {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            background: rgba(0,0,0,.25);
            border: 1px solid rgba(255,255,255,.1);
        }
        .semaforo-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            opacity: .25;
            transition: opacity .2s, box-shadow .2s;
        }
        .semaforo-dot.s-green { background: #22c55e; }
        .semaforo-dot.s-yellow { background: #f59e0b; }
        .semaforo-dot.s-red { background: #ef4444; }
        .semaforo-dot.active { opacity: 1; box-shadow: 0 0 8px currentColor; }
        .semaforo-dot.s-green.active { color: #22c55e; }
        .semaforo-dot.s-yellow.active { color: #f59e0b; }
        .semaforo-dot.s-red.active { color: #ef4444; }
        .semaforo-label { font-size: 11px; font-weight: 700; margin-left: 4px; }

        /* ── Score panel ── */
        .score-info-btn {
            background: rgba(148,163,184,.15);
            border: 1px solid rgba(148,163,184,.3);
            border-radius: 999px;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 6px;
            vertical-align: middle;
            flex-shrink: 0;
            transition: background .15s;
        }
        .score-info-btn:hover { background: rgba(148,163,184,.3); color: #f1f5f9; }
        .score-methodology-panel {
            background: color-mix(in srgb, var(--surface) 95%, var(--background));
            border: 1px solid color-mix(in srgb, var(--primary) 30%, var(--border));
            border-radius: 14px;
            padding: 16px;
            margin-top: 10px;
            display: none;
        }
        .score-methodology-panel.active { display: block; }
        .score-methodology-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .score-factor-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .score-factor-name { font-size: 13px; font-weight: 600; color: var(--text-primary); min-width: 180px; }
        .score-factor-bar-wrap { flex: 1; height: 8px; background: color-mix(in srgb, var(--border) 60%, var(--background)); border-radius: 999px; overflow: hidden; }
        .score-factor-fill { height: 100%; border-radius: 999px; background: var(--primary); transition: width .4s ease; }
        .score-factor-pct { font-size: 12px; font-weight: 800; color: var(--primary); min-width: 34px; text-align: right; }
        .score-methodology-note { font-size: 12px; color: var(--text-secondary); margin: 12px 0 0; padding-top: 10px; border-top: 1px solid color-mix(in srgb, var(--border) 50%, var(--background)); }

        @media (max-width: 1200px) {
            .hero-grid, .layout-two, .inner-two { grid-template-columns: 1fr; }
            .kpi-grid { grid-template-columns: repeat(2, minmax(220px, 1fr)); }
            .hero-main { grid-template-columns: 1fr; }
            .ai-grid { grid-template-columns: 1fr; }
            .alert-cards-grid { grid-template-columns: repeat(2, minmax(200px, 1fr)); }
            .score-factor-name { min-width: 140px; }
        }
        @media (max-width: 760px) {
            .kpi-grid, .split-three, .gov-grid, .hero-side, .alert-cards-grid { grid-template-columns: 1fr; }
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
    $autoConclusiones = $intelligentAnalysis['auto_conclusions'] ?? [];
    $portfolioAlerts = $intelligentAnalysis['portfolio_alerts'] ?? [];
    $systemRecommendations = $intelligentAnalysis['system_recommendations'] ?? [];

    $semaforo = $score > 85 ? 'green' : ($score >= 70 ? 'yellow' : 'red');
    $semaforoLabel = $score > 85 ? 'Estado óptimo' : ($score >= 70 ? 'En observación' : 'Requiere atención');
    $semaforoColor = $score > 85 ? '#22c55e' : ($score >= 70 ? '#f59e0b' : '#ef4444');
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

    <!-- ═══════════════════════════════════════════
         ANÁLISIS AUTOMÁTICO DEL PORTAFOLIO
    ═══════════════════════════════════════════ -->
    <section>
        <h2 class="section-title">Análisis Automático del Portafolio</h2>
        <div class="card auto-analysis-card">
            <div class="auto-analysis-header">
                <div class="auto-analysis-icon">🔍</div>
                <div class="auto-analysis-meta">
                    <p class="title">Diagnóstico automatizado del portafolio</p>
                    <p class="subtitle">Generado el <?= date('d/m/Y') ?> a las <?= date('H:i') ?> · Basado en datos actuales del sistema</p>
                </div>
            </div>
            <ul class="conclusion-list">
                <?php if (!empty($autoConclusiones)): ?>
                    <?php foreach ($autoConclusiones as $conclusion): ?>
                        <li>• <?= htmlspecialchars((string) $conclusion) ?></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>• No hay suficientes datos disponibles para generar conclusiones automáticas.</li>
                <?php endif; ?>
            </ul>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════
         ALERTAS DEL PORTAFOLIO
    ═══════════════════════════════════════════ -->
    <section>
        <h2 class="section-title">Alertas del Portafolio</h2>
        <div class="alert-cards-grid">
            <?php
            $alertIcons = [
                'progress_risk'    => '📉',
                'critical_blocker' => '⛔',
                'talent_overload'  => '👥',
                'financial_risk'   => '💰',
            ];
            $alertLevelLabels = [
                'green'  => 'Normal',
                'yellow' => 'Advertencia',
                'red'    => 'Crítico',
            ];
            foreach ($portfolioAlerts as $alert):
                $lvl  = htmlspecialchars((string) ($alert['level'] ?? 'green'));
                $icon = $alertIcons[$alert['type'] ?? ''] ?? '⚠';
            ?>
                <div class="alert-card <?= $lvl ?>">
                    <div class="alert-icon-box"><?= $icon ?></div>
                    <div class="alert-body">
                        <div class="alert-status-row">
                            <span class="alert-dot"></span>
                            <span class="alert-title"><?= htmlspecialchars((string) ($alert['title'] ?? '')) ?></span>
                        </div>
                        <div class="alert-condition">Condición: <?= htmlspecialchars((string) ($alert['condition'] ?? '')) ?></div>
                        <div class="alert-message"><?= htmlspecialchars((string) ($alert['message'] ?? '')) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════
         RECOMENDACIONES DEL SISTEMA
    ═══════════════════════════════════════════ -->
    <section>
        <h2 class="section-title">Recomendaciones del Sistema</h2>
        <div class="card rec-card">
            <div class="auto-analysis-header">
                <div class="auto-analysis-icon" style="background:color-mix(in srgb, #f59e0b 14%, var(--background)); border-color:color-mix(in srgb, #f59e0b 30%, var(--border));">💡</div>
                <div class="auto-analysis-meta">
                    <p class="title">Acciones recomendadas para el portafolio</p>
                    <p class="subtitle">Generadas automáticamente · Actualizadas al cargar el dashboard</p>
                </div>
            </div>
            <ol class="rec-list">
                <?php foreach ($systemRecommendations as $i => $rec): ?>
                    <li class="rec-item">
                        <span class="rec-number"><?= (int) $i + 1 ?></span>
                        <span class="rec-text"><?= htmlspecialchars((string) $rec) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

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
                <div class="gov-item"><div class="metric-label">⚠ Sin actualización &gt; 7 días</div><strong style="color:var(--danger)"><?= (int) ($alertStrip['stale_projects'] ?? 0) ?></strong></div>
                <div class="gov-item"><div class="metric-label">🔴 Proyectos con riesgo alto</div><strong style="color:var(--danger)"><?= (int) ($alertStrip['high_risk_projects'] ?? 0) ?></strong></div>
                <div class="gov-item"><div class="metric-label">⛔ Bloqueos críticos abiertos</div><strong style="color:var(--danger)"><?= (int) ($alertStrip['critical_blockers'] ?? 0) ?></strong></div>
                <div class="gov-item"><div class="metric-label">💰 Facturación pendiente</div><strong style="color:var(--danger)"><?= (int) ($alertStrip['billing_pending'] ?? 0) ?></strong></div>
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
                            <div class="semaforo">
                                <span class="semaforo-dot s-green<?= $semaforo === 'green' ? ' active' : '' ?>"></span>
                                <span class="semaforo-dot s-yellow<?= $semaforo === 'yellow' ? ' active' : '' ?>"></span>
                                <span class="semaforo-dot s-red<?= $semaforo === 'red' ? ' active' : '' ?>"></span>
                                <span class="semaforo-label" style="color:<?= htmlspecialchars($semaforoColor) ?>"><?= htmlspecialchars($semaforoLabel) ?></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="hero-title">Control de Portafolio
                            <button class="score-info-btn" id="scoreInfoBtn" title="Cómo se calcula el score del portafolio" onclick="document.getElementById('scoreMethodologyPanel').classList.toggle('active');this.style.background=document.getElementById('scoreMethodologyPanel').classList.contains('active')?'rgba(37,99,235,.35)':'rgba(148,163,184,.15)'">?</button>
                        </h3>
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

    <!-- Score methodology panel (toggleable) -->
    <div class="score-methodology-panel" id="scoreMethodologyPanel">
        <h4 class="score-methodology-title">
            <span>📊</span> Metodología de cálculo del score del portafolio
        </h4>
        <?php
        $scoreFactors = [
            ['name' => 'Avance de proyectos',     'weight' => 25, 'color' => '#2563eb', 'desc' => 'Desviación del avance real vs el esperado según cronograma.'],
            ['name' => 'Documentación',            'weight' => 25, 'color' => '#7c3aed', 'desc' => 'Porcentaje de nodos documentales en estado aprobado.'],
            ['name' => 'Horas ejecutadas',         'weight' => 20, 'color' => '#0891b2', 'desc' => 'Desviación de horas reales vs las esperadas al nivel de avance actual.'],
            ['name' => 'Seguimiento y notas',      'weight' => 15, 'color' => '#059669', 'desc' => 'Recencia (65%) y frecuencia (35%) de notas de seguimiento en el proyecto.'],
            ['name' => 'Calidad de requisitos',    'weight' => 15, 'color' => '#d97706', 'desc' => 'Porcentaje de requisitos aprobados en primera entrega.'],
            ['name' => 'Riesgos activos',          'weight' => 10, 'color' => '#dc2626', 'desc' => 'Nivel de riesgo registrado (bajo=90pts, medio=60pts, alto=25pts).'],
        ];
        $totalWeight = array_sum(array_column($scoreFactors, 'weight'));
        ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap:8px; margin-bottom:14px;">
            <?php foreach ($scoreFactors as $factor): ?>
                <div>
                    <div class="score-factor-row">
                        <span class="score-factor-name"><?= htmlspecialchars($factor['name']) ?></span>
                        <div class="score-factor-bar-wrap">
                            <div class="score-factor-fill" style="width:<?= ($factor['weight'] / $totalWeight) * 100 ?>%; background:<?= $factor['color'] ?>;"></div>
                        </div>
                        <span class="score-factor-pct" style="color:<?= $factor['color'] ?>"><?= $factor['weight'] ?>%</span>
                    </div>
                    <p style="font-size:11px; color:var(--text-secondary); margin: -4px 0 8px 0; padding-left:2px;"><?= htmlspecialchars($factor['desc']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="score-methodology-note">
            <strong>Penalización por bloqueos:</strong> Se descuentan hasta 12 puntos por bloqueos críticos vencidos y hasta 8 puntos adicionales si hay más de 3 bloqueos activos simultáneos. El score final se normaliza en escala 0–100.
            <br><strong>Semáforo:</strong> <span style="color:#22c55e; font-weight:700;">Verde</span> = score &gt; 85 &nbsp;|&nbsp; <span style="color:#f59e0b; font-weight:700;">Amarillo</span> = score 70–85 &nbsp;|&nbsp; <span style="color:#ef4444; font-weight:700;">Rojo</span> = score &lt; 70.
        </div>
    </div>

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
                ['label' => 'Documentos pendientes', 'value' => (int) (($governance['documents_revision'] ?? 0) + ($governance['documents_validacion'] ?? 0) + ($governance['documents_aprobacion'] ?? 0)), 'icon' => '📄'],
                ['label' => 'Cambios sin aprobar', 'value' => (int) ($governance['scope_changes_pending'] ?? 0), 'icon' => '🔄'],
                ['label' => 'Riesgos críticos', 'value' => (int) ($governance['critical_risks'] ?? 0), 'icon' => '⛔'],
                ['label' => 'Facturación pendiente', 'value' => (int) ($outsourcing['open_followups'] ?? 0), 'icon' => '💳'],
            ];
            foreach ($govItems as $item):
                $tone = $item['value'] > 0 ? 'var(--danger)' : 'var(--success)';
            ?>
                <article class="gov-item">
                    <div class="metric-label"><?= $item['icon'] ?> <?= htmlspecialchars($item['label']) ?></div>
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
