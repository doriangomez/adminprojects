<div class="executive-dashboard">
    <style>
        .executive-dashboard {
            display: flex;
            flex-direction: column;
            gap: 20px;
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
            padding: 18px;
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
            gap: 14px;
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
            gap: 14px;
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
            gap: 14px;
        }
        .inner-two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
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
            gap: 14px;
        }
        .metric-big { font-size: 34px; font-weight: 900; color: var(--text-primary); line-height: 1; }
        .metric-label { font-size: 12px; letter-spacing: .05em; color: var(--text-secondary); text-transform: uppercase; }
        .gov-grid { display: grid; grid-template-columns: repeat(4, minmax(160px, 1fr)); gap: 12px; }
        .gov-item { border-radius: 12px; padding: 12px; background: color-mix(in srgb, var(--surface) 85%, var(--background)); border: 1px solid color-mix(in srgb, var(--border) 70%, var(--background)); }
        .gov-item strong { display: block; font-size: 28px; color: var(--text-primary); }
        .muted { color: var(--text-secondary); font-size: 13px; margin-top: 4px; }
        @media (max-width: 1200px) {
            .hero-grid, .layout-two, .inner-two { grid-template-columns: 1fr; }
            .kpi-grid { grid-template-columns: repeat(2, minmax(220px, 1fr)); }
            .hero-main { grid-template-columns: 1fr; }
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
    ?>

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
                        <div class="stat-label">Tendencia vs mes anterior</div>
                        <div class="stat-value <?= $trend >= 0 ? 'trend-positive' : 'trend-negative' ?>"><?= $trend >= 0 ? '↑' : '↓' ?> <?= abs($trend) ?> pts</div>
                    </div>
                </div>
            </article>
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
            <article class="card"><div class="metric-label">Días promedio abiertos</div><div class="metric-big"><?= (int) ($stoppers['avg_open_days'] ?? 0) ?></div></article>
        </div>
        <article class="card chart-card" style="margin-top:14px;">
            <h4>Tendencia mensual de bloqueos</h4>
            <canvas id="stoppersTrendChart" height="220"></canvas>
        </article>
    </section>

    <section>
        <h2 class="section-title">Talento y Timesheets</h2>
        <div class="split-three">
            <article class="card"><div class="metric-label">Cumplimiento</div><div class="metric-big"><?= number_format($timesheetCompliance, 1, ',', '.') ?>%</div></article>
            <article class="card"><div class="metric-label">Horas vs plan</div><div class="metric-big"><?= number_format((float) ($timesheets['weekly_hours'] ?? 0), 1, ',', '.') ?> / <?= number_format($hoursPlan > 0 ? $hoursPlan / 4 : 0, 1, ',', '.') ?></div></article>
            <article class="card"><div class="metric-label">Talentos sin reporte</div><div class="metric-big" style="color:<?= ($timesheets['talents_without_report'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= (int) ($timesheets['talents_without_report'] ?? 0) ?></div></article>
        </div>
        <p class="muted">Semana <?= htmlspecialchars((string) ($timesheets['period_start'] ?? '')) ?> - <?= htmlspecialchars((string) ($timesheets['period_end'] ?? '')) ?></p>
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
</script>
