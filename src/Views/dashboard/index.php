<div class="executive-dashboard-v2">
    <style>
        .executive-dashboard-v2 { display: flex; flex-direction: column; gap: 14px; padding-bottom: 14px; }
        .zone { border: 1px solid color-mix(in srgb, var(--border) 74%, var(--background)); border-radius: 16px; background: color-mix(in srgb, var(--surface) 92%, var(--background)); padding: 14px; }
        .zone-title { margin: 0 0 12px; font-size: 15px; text-transform: uppercase; letter-spacing: .06em; color: var(--text-secondary); font-weight: 800; }

        .critical-bar { position: sticky; top: 8px; z-index: 15; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .critical-pill { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 8px 12px; font-size: 12px; font-weight: 800; }
        .critical-pill.red { background: color-mix(in srgb, var(--danger) 18%, var(--background)); color: var(--danger); }
        .critical-pill.orange { background: color-mix(in srgb, #f97316 18%, var(--background)); color: #c2410c; }
        .critical-pill.yellow { background: color-mix(in srgb, var(--warning) 22%, var(--background)); color: #a16207; }
        .critical-pill.blue { background: color-mix(in srgb, var(--primary) 18%, var(--background)); color: var(--primary); }
        .critical-ok { border-color: color-mix(in srgb, var(--success) 45%, var(--border)); background: color-mix(in srgb, var(--success) 14%, var(--surface)); color: var(--success); font-weight: 800; }

        .kpis-row { display: grid; grid-template-columns: repeat(6, minmax(160px, 1fr)); gap: 10px; }
        .kpi-card { border: 1px solid color-mix(in srgb, var(--border) 74%, var(--background)); border-radius: 14px; padding: 10px; background: color-mix(in srgb, var(--surface) 88%, var(--background)); }
        .kpi-label { font-size: 12px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .04em; min-height: 34px; }
        .kpi-value { margin-top: 6px; font-size: 30px; line-height: 1; font-weight: 900; color: var(--text-primary); }
        .kpi-trend { margin-top: 6px; font-size: 12px; font-weight: 700; }
        .trend-positive { color: var(--success); }
        .trend-negative { color: var(--danger); }

        .two-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .list-card { border: 1px solid color-mix(in srgb, var(--border) 70%, var(--background)); border-radius: 14px; background: color-mix(in srgb, var(--surface) 88%, var(--background)); padding: 12px; }
        .list-title { margin: 0 0 10px; font-size: 14px; font-weight: 800; color: var(--text-primary); }
        .rows { display: flex; flex-direction: column; gap: 8px; }
        .row-item { display: grid; grid-template-columns: auto 1fr auto auto; gap: 8px; align-items: center; border: 1px solid color-mix(in srgb, var(--border) 64%, var(--background)); border-radius: 10px; padding: 8px; }
        .row-item .meta { font-size: 12px; color: var(--text-secondary); }
        .action-btn-mini { display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; padding: 5px 8px; font-size: 12px; font-weight: 700; text-decoration: none; border: 1px solid color-mix(in srgb, var(--primary) 55%, var(--border)); color: var(--primary); }
        .action-btn-mini:hover { background: color-mix(in srgb, var(--primary) 12%, var(--background)); }
        .muted { color: var(--text-secondary); font-size: 13px; margin: 0; }

        .chart-row { display: grid; grid-template-columns: repeat(3, minmax(250px, 1fr)); gap: 10px; }
        .chart-panel { border: 1px solid color-mix(in srgb, var(--border) 70%, var(--background)); border-radius: 14px; background: color-mix(in srgb, var(--surface) 88%, var(--background)); padding: 10px; }
        .chart-panel h4 { margin: 0 0 10px; color: var(--text-primary); font-size: 14px; }
        .chart-panel canvas { width: 100%; min-height: 250px; }

        .tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .tab-btn { border: 1px solid color-mix(in srgb, var(--border) 80%, var(--background)); border-radius: 999px; padding: 6px 10px; background: var(--surface); font-size: 12px; font-weight: 700; color: var(--text-secondary); cursor: pointer; }
        .tab-btn.active { background: color-mix(in srgb, var(--primary) 15%, var(--background)); border-color: color-mix(in srgb, var(--primary) 45%, var(--border)); color: var(--primary); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid color-mix(in srgb, var(--border) 72%, var(--background)); padding: 9px 8px; color: var(--text-primary); font-size: 13px; }
        th { text-transform: uppercase; letter-spacing: .05em; font-size: 11px; color: var(--text-secondary); text-align: left; }
        th.sortable { cursor: pointer; }
        .text-right { text-align: right; }
        .pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 8px; font-size: 11px; font-weight: 800; }
        .pill.green {
          background: color-mix(in srgb, var(--success) 15%, transparent);
          color: var(--success);
          font-weight: 600;
        }
        .pill.amber {
          background: color-mix(in srgb, var(--warning) 15%, transparent);
          color: var(--warning);
          font-weight: 600;
        }
        .pill.red {
          background: color-mix(in srgb, var(--danger) 15%, transparent);
          color: var(--danger);
          font-weight: 600;
        }

        details.ai-collapsible { margin-top: 12px; border: 1px solid color-mix(in srgb, #1d4ed8 40%, var(--border)); border-radius: 12px; padding: 10px; }
        details.ai-collapsible summary { cursor: pointer; font-size: 13px; font-weight: 800; color: var(--text-primary); }
        .ai-list { margin: 8px 0 0; padding-left: 18px; }

        @media (max-width: 1320px) {
            .kpis-row { grid-template-columns: repeat(3, minmax(180px, 1fr)); }
            .chart-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 980px) {
            .two-cols { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .kpis-row { grid-template-columns: 1fr; }
        }
    </style>

    <?php
    $statusCounts = $projects['status_counts'] ?? ['planning' => 0, 'en_curso' => 0, 'en_riesgo' => 0, 'cerrado' => 0];
    $monthlyProgressTrend = $projects['monthly_progress_trend'] ?? [];
    $staleProjects = $projects['stale_projects'] ?? [];
    $portfolioRows = $portfolioInsights['ranking'] ?? [];
    usort($portfolioRows, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

    $executiveIntel = is_array($executiveIntel ?? null) ? $executiveIntel : [];
    $movement = $executiveIntel['movement'] ?? [];
    $alertStrip = $executiveIntel['alerts'] ?? [];
    $automaticAlerts = $executiveIntel['automatic_alerts'] ?? [];
    $systemRecommendations = $executiveIntel['system_recommendations'] ?? [];
    $intelligentAnalysis = $executiveIntel['intelligent_analysis'] ?? [];
    $analysisFlags = $intelligentAnalysis['flags'] ?? [];
    $analysisRecommendations = $intelligentAnalysis['recommendations'] ?? [];
    $portfolioScoreCard = $executiveIntel['portfolio_score_card'] ?? [];

    $score = (int) ($portfolioScoreCard['score'] ?? ($portfolioHealth['average_score'] ?? 0));
    $scoreLabel = (string) ($portfolioScoreCard['label'] ?? ($score > 85 ? 'Verde' : ($score >= 70 ? 'Amarillo' : 'Rojo')));

    $hoursPlan = (float) ($summary['horas_planificadas'] ?? 0);
    $hoursReal = (float) ($summary['horas_reales'] ?? 0);
    $hoursVsPlanPct = $hoursPlan > 0 ? ($hoursReal / $hoursPlan) * 100 : 0;
    $billingVsPlanPct = (float) (($executiveIntel['automatic_portfolio_analysis']['metrics']['billing_vs_plan_pct'] ?? 0));
    $talentUtilizationPct = (float) (($executiveIntel['automatic_portfolio_analysis']['metrics']['talent_utilization_pct'] ?? 0));

    $billingPending = (float) ($alertStrip['billing_plan_pending'] ?? 0);
    $billingOverdue = (float) ($alertStrip['billing_plan_overdue'] ?? 0);
    $billingIssuePct = $billingPending > 0 ? ($billingOverdue / $billingPending) * 100 : 0;

    $riskProjects = (int) (($executiveIntel['automatic_portfolio_analysis']['metrics']['projects_at_risk'] ?? 0));
    $criticalBlockers = (int) ($alertStrip['critical_blockers'] ?? 0);
    $allNormal = $riskProjects === 0 && $criticalBlockers === 0 && $billingOverdue <= 0;

    $movementBadge = static function (array $metric, bool $inverse = false): string {
        $delta = (float) ($metric['delta_pct'] ?? 0);
        $isPositive = $inverse ? ($delta <= 0) : ($delta >= 0);
        $arrow = $delta >= 0 ? '↑' : '↓';
        $class = $isPositive ? 'trend-positive' : 'trend-negative';

        return '<span class="kpi-trend ' . $class . '">' . $arrow . ' ' . number_format(abs($delta), 1, ',', '.') . '% vs mes anterior</span>';
    };

    $alertsForList = [];
    foreach ($automaticAlerts as $alert) {
        $status = (string) ($alert['status'] ?? 'green');
        $icon = $status === 'red' ? '🔴' : ($status === 'yellow' ? '🟠' : '🟢');
        $alertsForList[] = [
            'icon' => $icon,
            'title' => (string) ($alert['title'] ?? 'Alerta'),
            'detail' => (string) ($alert['message'] ?? ''),
            'project' => (string) ($alert['project'] ?? ($alert['rule'] ?? 'Portafolio')),
            'project_id' => (int) ($alert['project_id'] ?? 0),
        ];
    }
    if (!empty($staleProjects)) {
        foreach ($staleProjects as $stale) {
            $alertsForList[] = [
                'icon' => '🟡',
                'title' => 'Proyecto sin actualización reciente',
                'detail' => (string) ($stale['name'] ?? 'Proyecto') . ' sin movimientos recientes',
                'project' => (string) ($stale['name'] ?? 'Proyecto'),
                'project_id' => (int) ($stale['id'] ?? 0),
            ];
        }
    }
    $alertsForList = array_slice($alertsForList, 0, 8);

    $recommendationRows = [];
    foreach (array_slice($systemRecommendations, 0, 5) as $recommendation) {
        $contextLabel = 'Contexto general del portafolio.';
        if (str_contains((string) $recommendation, 'facturación')) {
            $contextLabel = 'Facturación vs plan actual: ' . number_format($billingVsPlanPct, 1, ',', '.') . '%.';
        } elseif (str_contains((string) $recommendation, 'score')) {
            $contextLabel = 'Score actual del portafolio: ' . $score . '/100.';
        } elseif (str_contains((string) $recommendation, 'bloqueo')) {
            $contextLabel = 'Bloqueos críticos activos: ' . $criticalBlockers . '.';
        }
        $recommendationRows[] = ['text' => (string) $recommendation, 'context' => $contextLabel];
    }

    $topTalents = $timesheets['hours_by_talent'] ?? [];
    $talentsLow = $topTalents;
    usort($talentsLow, static fn (array $a, array $b): int => ((float) ($a['total_hours'] ?? 0)) <=> ((float) ($b['total_hours'] ?? 0)));

    $govItems = [
        ['label' => 'Documentos pendientes', 'value' => (int) (($governance['documents_revision'] ?? 0) + ($governance['documents_validacion'] ?? 0) + ($governance['documents_aprobacion'] ?? 0))],
        ['label' => 'Cambios sin aprobar', 'value' => (int) ($governance['scope_changes_pending'] ?? 0)],
        ['label' => 'Riesgos críticos', 'value' => (int) ($governance['critical_risks'] ?? 0)],
        ['label' => 'Facturas pendientes', 'value' => (int) ($outsourcing['open_followups'] ?? 0)],
    ];

    $basePath = $basePath ?? '';
    ?>

    <section class="zone <?= $allNormal ? 'critical-ok' : '' ?>">
        <h2 class="zone-title">Zona 1 — Barra de estado crítico</h2>
        <?php if ($allNormal): ?>
            <div class="critical-bar">✅ Portafolio operando con normalidad</div>
        <?php else: ?>
            <div class="critical-bar">
                <span class="critical-pill red">🔴 Proyectos en riesgo: <?= $riskProjects ?></span>
                <span class="critical-pill orange">🟠 Bloqueos críticos: <?= $criticalBlockers ?></span>
                <span class="critical-pill yellow">🟡 Facturas vencidas / plan: <?= number_format($billingIssuePct, 1, ',', '.') ?>%</span>
                <span class="critical-pill blue">🔵 Score general: <?= $score ?>/100</span>
            </div>
        <?php endif; ?>
    </section>

    <section class="zone">
        <h2 class="zone-title">Zona 2 — KPIs principales</h2>
        <div class="kpis-row">
            <article class="kpi-card"><div class="kpi-label">Proyectos activos</div><div class="kpi-value"><?= (int) ($summary['proyectos_activos'] ?? 0) ?></div><?= $movementBadge($movement['risk_projects'] ?? [], true) ?></article>
            <article class="kpi-card"><div class="kpi-label">Avance promedio del portafolio</div><div class="kpi-value"><?= number_format((float) ($summary['avance_promedio'] ?? 0), 1, ',', '.') ?>%</div><?= $movementBadge(['delta_pct' => (float) (($executiveIntel['automatic_portfolio_analysis']['metrics']['monthly_progress_delta_pct'] ?? 0))]) ?></article>
            <article class="kpi-card"><div class="kpi-label">Utilización del talento</div><div class="kpi-value"><?= number_format($talentUtilizationPct, 1, ',', '.') ?>%</div><?= $movementBadge(['delta_pct' => $talentUtilizationPct - 80]) ?></article>
            <article class="kpi-card"><div class="kpi-label">Horas ejecutadas vs presupuestadas</div><div class="kpi-value"><?= number_format($hoursVsPlanPct, 1, ',', '.') ?>%</div><?= $movementBadge(['delta_pct' => $hoursVsPlanPct - 100], true) ?></article>
            <article class="kpi-card"><div class="kpi-label">Total facturado vs plan</div><div class="kpi-value"><?= number_format($billingVsPlanPct, 1, ',', '.') ?>%</div><?= $movementBadge($movement['billing_pending'] ?? [], true) ?></article>
            <article class="kpi-card"><div class="kpi-label">Total contratado</div><div class="kpi-value">$<?= number_format((float) (($executiveIntel['financial_impact']['total_contracted'] ?? 0)), 0, ',', '.') ?></div><?= $movementBadge($movement['score'] ?? []) ?></article>
        </div>
    </section>

    <section class="zone">
        <h2 class="zone-title">Zona 3 — Alertas y acciones</h2>
        <div class="two-cols">
            <article class="list-card">
                <h3 class="list-title">Alertas activas</h3>
                <div class="rows">
                    <?php if ($alertsForList !== []): ?>
                        <?php foreach ($alertsForList as $alert): ?>
                            <div class="row-item">
                                <div><?= htmlspecialchars($alert['icon']) ?></div>
                                <div>
                                    <strong><?= htmlspecialchars($alert['title']) ?></strong>
                                    <div class="meta"><?= htmlspecialchars($alert['detail']) ?></div>
                                </div>
                                <div class="meta"><?= htmlspecialchars($alert['project']) ?></div>
                                <a class="action-btn-mini" href="<?= $basePath ?>/projects<?= ($alert['project_id'] > 0 ? '/' . $alert['project_id'] : '') ?>">Ver</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">No hay alertas activas.</p>
                    <?php endif; ?>
                </div>
                <?php if (count($automaticAlerts) > 8): ?><p style="margin-top:8px;"><a class="action-btn-mini" href="<?= $basePath ?>/projects">Ver todas las alertas</a></p><?php endif; ?>
            </article>

            <article class="list-card">
                <h3 class="list-title">Recomendaciones accionables</h3>
                <div class="rows">
                    <?php if ($recommendationRows !== []): ?>
                        <?php foreach ($recommendationRows as $row): ?>
                            <div class="row-item" style="grid-template-columns: 1fr auto;">
                                <div>
                                    <strong><?= htmlspecialchars($row['text']) ?></strong>
                                    <div class="meta">Por qué: <?= htmlspecialchars($row['context']) ?></div>
                                </div>
                                <a class="action-btn-mini" href="<?= $basePath ?>/projects">Acción</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">No se generaron recomendaciones para este periodo.</p>
                    <?php endif; ?>
                </div>
            </article>
        </div>

        <details class="ai-collapsible">
            <summary>Ver análisis completo</summary>
            <p class="muted" style="margin-top:8px;"><?= htmlspecialchars((string) ($intelligentAnalysis['diagnosis'] ?? 'Sin diagnóstico disponible.')) ?></p>
            <div class="two-cols" style="margin-top:8px;">
                <div>
                    <div class="list-title" style="font-size:13px; margin-bottom:6px;">Recomendaciones IA</div>
                    <ol class="ai-list">
                        <?php foreach ($analysisRecommendations as $recommendation): ?>
                            <li><?= htmlspecialchars((string) $recommendation) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <div>
                    <div class="list-title" style="font-size:13px; margin-bottom:6px;">Reglas activadas</div>
                    <ul class="ai-list">
                        <?php foreach ($analysisFlags as $flag): ?>
                            <li><strong><?= htmlspecialchars((string) ($flag['title'] ?? 'Regla')) ?>:</strong> <?= htmlspecialchars((string) ($flag['detail'] ?? '-')) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </details>
    </section>

    <section class="zone">
        <h2 class="zone-title">Zona 4 — Visualizaciones</h2>
        <div class="chart-row">
            <article class="chart-panel">
                <h4>Estado del portafolio</h4>
                <canvas id="statusChart" height="260"></canvas>
            </article>
            <article class="chart-panel">
                <h4>Tendencia de avance mensual</h4>
                <canvas id="monthlyProgressChart" height="260"></canvas>
            </article>
            <article class="chart-panel">
                <h4>Bloqueos por severidad</h4>
                <canvas id="blockersSeverityChart" height="260"></canvas>
            </article>
        </div>
    </section>

    <section class="zone">
        <h2 class="zone-title">Zona 5 — Tablas de detalle</h2>
        <div class="tabs">
            <button type="button" class="tab-btn active" data-tab="tab-proyectos">Proyectos</button>
            <button type="button" class="tab-btn" data-tab="tab-bloqueos">Bloqueos</button>
            <button type="button" class="tab-btn" data-tab="tab-talento">Talento</button>
            <button type="button" class="tab-btn" data-tab="tab-gobierno">Gobierno</button>
        </div>

        <div id="tab-proyectos" class="tab-pane active table-wrap">
            <table id="projectsTable">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="text">Proyecto</th>
                        <th class="sortable" data-sort="text">Cliente</th>
                        <th class="text-right sortable" data-sort="number">Score</th>
                        <th class="sortable" data-sort="text">Riesgo</th>
                        <th class="text-right sortable" data-sort="number">Bloqueos</th>
                        <th class="text-right sortable" data-sort="number">Facturación</th>
                        <th class="sortable" data-sort="text">Última actualización</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($portfolioRows as $row): ?>
                        <?php $riskLabel = (string) ($row['risk'] ?? 'Medio'); $riskClass = $riskLabel === 'Bajo' ? 'green' : ($riskLabel === 'Medio' ? 'amber' : 'red'); ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['client'] ?? '')) ?></td>
                            <td class="text-right"><?= (int) ($row['score'] ?? 0) ?></td>
                            <td><span class="pill <?= $riskClass ?>"><?= htmlspecialchars($riskLabel) ?></span></td>
                            <td class="text-right"><?= (int) ($row['blockers_open'] ?? 0) ?></td>
                            <td class="text-right"><?= number_format((float) ($row['billing'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars((string) ($row['updated_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="tab-bloqueos" class="tab-pane table-wrap">
            <table>
                <thead><tr><th>Proyecto</th><th>Tipo</th><th class="text-right">Días abiertos</th><th>Responsable</th></tr></thead>
                <tbody>
                    <?php foreach (($stoppers['top_active'] ?? []) as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($item['project'] ?? 'Proyecto')) ?></td>
                            <td><?= htmlspecialchars((string) ($item['type'] ?? 'Operativo')) ?></td>
                            <td class="text-right"><?= (int) ($item['avg_days_open'] ?? 0) ?></td>
                            <td><?= htmlspecialchars((string) ($item['owner'] ?? 'PMO')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="tab-talento" class="tab-pane table-wrap">
            <table>
                <thead><tr><th>Talento</th><th class="text-right">Horas</th><th>Cumplimiento bajo</th><th class="text-right">Utilización promedio</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($topTalents, 0, 10) as $talent): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($talent['talent'] ?? 'Talento')) ?></td>
                            <td class="text-right"><?= number_format((float) ($talent['total_hours'] ?? 0), 1, ',', '.') ?></td>
                            <td><?= htmlspecialchars((string) ($talentsLow[0]['talent'] ?? '-')) ?></td>
                            <td class="text-right"><?= number_format((float) ($timesheets['weekly_hours'] ?? 0) > 0 ? ((float) ($talent['total_hours'] ?? 0) / (float) ($timesheets['weekly_hours'] ?? 1)) * 100 : 0, 1, ',', '.') ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="tab-gobierno" class="tab-pane table-wrap">
            <table>
                <thead><tr><th>Indicador</th><th class="text-right">Valor</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($govItems as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $item['label']) ?></td>
                            <td class="text-right"><?= (int) $item['value'] ?></td>
                            <td><span class="pill <?= $item['value'] > 0 ? 'red' : 'green' ?>"><?= $item['value'] > 0 ? 'Atención' : 'Controlado' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
    const monthlyProgressLabels = <?= json_encode(array_map(static fn ($row) => $row['month_key'] ?? '', $monthlyProgressTrend)) ?>;
    const monthlyProgressData = <?= json_encode(array_map(static fn ($row) => round((float) ($row['avg_progress'] ?? 0), 1), $monthlyProgressTrend)) ?>;
    const monthlyProgressExpected = monthlyProgressData.map((v) => Math.min(100, Math.max(0, v + 4)));
    const blockersSeverityData = <?= json_encode([
        (int) (($stoppers['severity_counts']['critico'] ?? 0)),
        (int) (($stoppers['severity_counts']['alto'] ?? 0)),
        (int) (($stoppers['severity_counts']['medio'] ?? 0)),
    ]) ?>;

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { labels: ['Planning', 'Ejecución', 'En riesgo', 'Completado'], datasets: [{ data: statusData, backgroundColor: ['#38bdf8', '#2563eb', '#f97316', '#64748b'] }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
    });

    new Chart(document.getElementById('monthlyProgressChart'), {
        type: 'line',
        data: {
            labels: monthlyProgressLabels,
            datasets: [
                { label: 'Avance real', data: monthlyProgressData, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.1)', fill: true, tension: .35 },
                { label: 'Avance esperado', data: monthlyProgressExpected, borderColor: '#1d4ed8', borderDash: [6, 6], fill: false, tension: .35 }
            ]
        },
        options: { plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true, max: 100 } } }
    });

    const blockersValueLabel = {
        id: 'blockersValueLabel',
        afterDatasetsDraw(chart) {
            const {ctx} = chart;
            const meta = chart.getDatasetMeta(0);
            meta.data.forEach((bar, i) => {
                ctx.save();
                ctx.fillStyle = '#334155';
                ctx.font = '700 11px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(String(chart.data.datasets[0].data[i] ?? 0), bar.x, bar.y - 6);
                ctx.restore();
            });
        }
    };

    new Chart(document.getElementById('blockersSeverityChart'), {
        type: 'bar',
        data: {
            labels: ['Crítico', 'Alto', 'Medio'],
            datasets: [{ data: blockersSeverityData, backgroundColor: ['#dc2626', '#f97316', '#facc15'], borderRadius: 10 }]
        },
        options: { plugins: { legend: { display: false } } },
        plugins: [blockersValueLabel]
    });

    document.querySelectorAll('.tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach((node) => node.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach((node) => node.classList.remove('active'));
            btn.classList.add('active');
            const pane = document.getElementById(btn.dataset.tab);
            if (pane) pane.classList.add('active');
        });
    });

    const projectsTable = document.getElementById('projectsTable');
    if (projectsTable) {
        projectsTable.querySelectorAll('th.sortable').forEach((th, columnIndex) => {
            let asc = true;
            th.addEventListener('click', () => {
                const rows = Array.from(projectsTable.querySelectorAll('tbody tr'));
                const kind = th.dataset.sort || 'text';
                rows.sort((a, b) => {
                    const rawA = a.children[columnIndex].innerText.trim();
                    const rawB = b.children[columnIndex].innerText.trim();
                    if (kind === 'number') {
                        const numA = parseFloat(rawA.replace(/[^0-9,.-]/g, '').replace(',', '.')) || 0;
                        const numB = parseFloat(rawB.replace(/[^0-9,.-]/g, '').replace(',', '.')) || 0;
                        return asc ? numA - numB : numB - numA;
                    }
                    return asc ? rawA.localeCompare(rawB, 'es') : rawB.localeCompare(rawA, 'es');
                });
                asc = !asc;
                const tbody = projectsTable.querySelector('tbody');
                rows.forEach((row) => tbody.appendChild(row));
            });
        });
    }
</script>
