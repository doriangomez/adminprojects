<div class="executive-dashboard">
    <style>
        .executive-dashboard {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 10px;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--text-primary) 12%, var(--background));
        }
        .card.elevated { box-shadow: 0 12px 28px color-mix(in srgb, var(--text-primary) 12%, var(--background)); }
        .kpi-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%);
            border: 1px solid color-mix(in srgb, var(--border) 80%, var(--background));
        }
        .kpi-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: color-mix(in srgb, var(--primary) 14%, var(--background));
            color: var(--primary);
            border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--background));
        }
        .kpi-icon svg { width: 26px; height: 26px; stroke: currentColor; }
        .kpi-card .meta { display: flex; flex-direction: column; gap: 6px; }
        .kpi-card .label { font-size: 13px; color: var(--text-secondary); letter-spacing: 0.01em; }
        .kpi-card .value { font-size: 24px; font-weight: 800; color: var(--text-primary); }
        .kpi-card[data-tone="green"] .kpi-icon { background: color-mix(in srgb, var(--success) 18%, var(--background)); color: var(--success); border-color: color-mix(in srgb, var(--success) 30%, var(--background)); }
        .kpi-card[data-tone="amber"] .kpi-icon { background: color-mix(in srgb, var(--warning) 18%, var(--background)); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 30%, var(--background)); }
        .kpi-card[data-tone="slate"] .kpi-icon { background: color-mix(in srgb, var(--text-secondary) 18%, var(--background)); color: var(--text-primary); border-color: color-mix(in srgb, var(--text-secondary) 30%, var(--background)); }
        .kpi-card[data-tone="blue"] .kpi-icon { background: color-mix(in srgb, var(--primary) 18%, var(--background)); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 30%, var(--background)); }
        .kpi-card[data-tone="purple"] .kpi-icon { background: color-mix(in srgb, var(--secondary) 18%, var(--background)); color: var(--secondary); border-color: color-mix(in srgb, var(--secondary) 30%, var(--background)); }

        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
        .chart-card h4 { margin: 0 0 6px 0; font-size: 15px; color: var(--text-primary); font-weight: 800; }
        .chart-legend { font-size: 13px; color: var(--text-secondary); margin: 6px 0 0; }

        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .metric-item { display: flex; flex-direction: column; gap: 6px; }
        .metric-item .label { font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; }
        .metric-item .value { font-size: 20px; font-weight: 800; color: var(--text-primary); }

        .list { margin: 0; padding-left: 18px; color: var(--text-primary); font-size: 14px; }
        .list li { margin-bottom: 6px; }
        .muted { color: var(--text-secondary); font-size: 14px; margin: 0; }

        .section-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
        .pill { display: inline-flex; align-items:center; gap: 8px; padding: 6px 10px; border-radius: 999px; font-weight: 700; font-size: 12px; }
        .pill.soft-green { background: color-mix(in srgb, var(--success) 14%, var(--background)); color: var(--success); border: 1px solid color-mix(in srgb, var(--success) 24%, var(--background)); }
        .pill.soft-amber { background: color-mix(in srgb, var(--warning) 14%, var(--background)); color: var(--warning); border: 1px solid color-mix(in srgb, var(--warning) 24%, var(--background)); }
        .pill.soft-blue { background: color-mix(in srgb, var(--primary) 14%, var(--background)); color: var(--primary); border: 1px solid color-mix(in srgb, var(--primary) 24%, var(--background)); }
        .pill.soft-red { background: color-mix(in srgb, var(--danger) 14%, var(--background)); color: var(--danger); border: 1px solid color-mix(in srgb, var(--danger) 24%, var(--background)); }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); padding: 10px 8px; border-bottom: 1px solid color-mix(in srgb, var(--border) 80%, var(--background)); }
        tbody td { padding: 12px 8px; border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--background)); font-weight: 600; color: var(--text-primary); }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); }
        .text-right { text-align: right; }
    </style>

    <?php
    $statusCounts = $projects['status_counts'] ?? ['planning' => 0, 'en_curso' => 0, 'en_riesgo' => 0, 'cerrado' => 0];
    $progressByClient = $projects['progress_by_client'] ?? [];
    $staleProjects = $projects['stale_projects'] ?? [];
    $stageDistribution = $projects['stage_distribution'] ?? [];
    ?>

    <div>
        <h2 class="section-title">Resumen ejecutivo</h2>
        <div class="kpi-grid">
            <div class="card kpi-card" data-tone="blue">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 6h16v12H4z"/><path d="M8 10h8"/><path d="M8 14h4"/></svg></span>
                <div class="meta">
                    <span class="label">Proyectos activos</span>
                    <span class="value"><?= $summary['proyectos_activos'] ?? 0 ?></span>
                </div>
            </div>
            <div class="card kpi-card" data-tone="amber">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M12 3v18"/><path d="M5 9h7"/><path d="M5 15h7"/><path d="m12 15 7-4v8Z"/></svg></span>
                <div class="meta">
                    <span class="label">Proyectos en riesgo</span>
                    <span class="value"><?= $summary['proyectos_riesgo'] ?? 0 ?></span>
                </div>
            </div>
            <div class="card kpi-card" data-tone="green">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 12h16"/><path d="M8 6v12"/><path d="m14 9 3 3-3 3"/></svg></span>
                <div class="meta">
                    <span class="label">Avance promedio</span>
                    <span class="value"><?= number_format($summary['avance_promedio'] ?? 0, 1, ',', '.') ?>%</span>
                </div>
            </div>
            <div class="card kpi-card" data-tone="slate">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8"/><path d="M8 12h4"/></svg></span>
                <div class="meta">
                    <span class="label">Horas reales vs plan</span>
                    <span class="value"><?= number_format($summary['horas_reales'] ?? 0, 0, ',', '.') ?> / <?= number_format($summary['horas_planificadas'] ?? 0, 0, ',', '.') ?></span>
                </div>
            </div>
            <div class="card kpi-card" data-tone="purple">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 6h16v12H4z"/><path d="M8 10a2 2 0 1 0 0 4h8a2 2 0 1 0 0-4Z"/></svg></span>
                <div class="meta">
                    <span class="label">Costo real vs presupuesto</span>
                    <span class="value">$<?= number_format($summary['costo_real_total'] ?? 0, 0, ',', '.') ?> / $<?= number_format($summary['presupuesto_total'] ?? 0, 0, ',', '.') ?></span>
                </div>
            </div>
            <div class="card kpi-card" data-tone="blue">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M12 4a4 4 0 0 0-4 4v2a4 4 0 0 0 8 0V8a4 4 0 0 0-4-4Z"/><path d="M4 20a8 8 0 0 1 16 0"/></svg></span>
                <div class="meta">
                    <span class="label">Talentos activos</span>
                    <span class="value"><?= $summary['talentos_activos'] ?? 0 ?></span>
                </div>
            </div>
            <div class="card kpi-card" data-tone="slate">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M6 6h12v12H6z"/><path d="M9 9h6v6H9z"/></svg></span>
                <div class="meta">
                    <span class="label">Outsourcing activo</span>
                    <span class="value"><?= $summary['outsourcing_activo'] ?? 0 ?></span>
                </div>
            </div>
        </div>
    </div>

    <div>
        <h2 class="section-title">Salud operativa</h2>
        <div class="charts-grid">
            <div class="card chart-card">
                <h4>Proyectos por estado</h4>
                <canvas id="statusChart" height="150"></canvas>
                <p class="chart-legend">Planning vs ejecución, riesgo y cierre.</p>
            </div>
            <div class="card chart-card">
                <h4>Avance promedio por cliente</h4>
                <canvas id="progressChart" height="150"></canvas>
                <p class="chart-legend">Promedio de avance consolidado.</p>
            </div>
            <div class="card chart-card">
                <h4>Proyectos por Stage-gate</h4>
                <?php if ($stageDistribution): ?>
                    <ul class="list">
                        <?php foreach ($stageDistribution as $stageRow): ?>
                            <li><?= htmlspecialchars((string) ($stageRow['stage'] ?? 'Discovery')) ?>: <strong><?= (int) ($stageRow['total'] ?? 0) ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">Sin datos de Stage-gate para mostrar.</p>
                <?php endif; ?>
                <p class="chart-legend">Dimensión opcional para análisis ejecutivo por fase de innovación.</p>
            </div>
            <div class="card chart-card">
                <h4>Proyectos sin avance reciente</h4>
                <p class="muted"><?= count($staleProjects) ?> proyectos sin actualización en 14 días.</p>
                <?php if ($staleProjects): ?>
                    <ul class="list">
                        <?php foreach ($staleProjects as $project): ?>
                            <li><?= htmlspecialchars($project['name'] ?? '') ?> <span class="muted">(<?= htmlspecialchars($project['client'] ?? '') ?>)</span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">Sin proyectos críticos por actualización.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <h2 class="section-title">Talento y timesheets</h2>
        <div class="section-grid">
            <div class="card">
                <div class="metric-grid">
                    <div class="metric-item">
                        <span class="label">Horas reportadas hoy</span>
                        <span class="value"><?= number_format($timesheets['today_hours'] ?? 0, 1, ',', '.') ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Horas cargadas semana</span>
                        <span class="value"><?= number_format($timesheets['weekly_hours'] ?? 0, 1, ',', '.') ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Horas pendientes</span>
                        <span class="value"><?= number_format($timesheets['pending_hours'] ?? 0, 1, ',', '.') ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Talentos sin reporte</span>
                        <span class="value"><?= $timesheets['talents_without_report'] ?? 0 ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Aprobaciones pendientes</span>
                        <span class="value"><?= $timesheets['pending_approvals_count'] ?? 0 ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Internos vs externos</span>
                        <span class="value"><?= $timesheets['internal_talents'] ?? 0 ?> / <?= $timesheets['external_talents'] ?? 0 ?></span>
                    </div>
                </div>
                <p class="muted">Semana <?= htmlspecialchars($timesheets['period_start'] ?? '') ?> - <?= htmlspecialchars($timesheets['period_end'] ?? '') ?></p>
            </div>
            <div class="card">
                <h4>Horas por proyecto</h4>
                <canvas id="hoursProjectChart" height="130"></canvas>
                <?php if (!empty($timesheets['hours_by_project'])): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Proyecto</th>
                                <th class="text-right">Horas aprobadas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timesheets['hours_by_project'] as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['project'] ?? '') ?></td>
                                    <td class="text-right"><?= number_format((float) ($row['total_hours'] ?? 0), 1, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="muted">Sin horas aprobadas para mostrar.</p>
                <?php endif; ?>
            </div>
            <div class="card">
                <h4>Horas por talento</h4>
                <canvas id="hoursTalentChart" height="130"></canvas>
                <?php if (!empty($timesheets['hours_by_talent'])): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Talento</th>
                                <th class="text-right">Horas aprobadas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timesheets['hours_by_talent'] as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['talent'] ?? '') ?></td>
                                    <td class="text-right"><?= number_format((float) ($row['total_hours'] ?? 0), 1, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="muted">Sin horas aprobadas para mostrar.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <h2 class="section-title">Outsourcing</h2>
        <div class="section-grid">
            <div class="card">
                <div class="metric-grid">
                    <div class="metric-item">
                        <span class="label">Servicios activos</span>
                        <span class="value"><?= $outsourcing['active_services'] ?? 0 ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Seguimientos abiertos</span>
                        <span class="value"><?= $outsourcing['open_followups'] ?? 0 ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Servicios amarillo/rojo</span>
                        <span class="value"><?= $outsourcing['attention_services'] ?? 0 ?></span>
                    </div>
                </div>
            </div>
            <div class="card">
                <h4>Último seguimiento por cliente</h4>
                <?php if (!empty($outsourcing['last_followups'])): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Estado</th>
                                <th class="text-right">Periodo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outsourcing['last_followups'] as $followup): ?>
                                <tr>
                                    <td><?= htmlspecialchars($followup['client'] ?? '') ?></td>
                                    <td>
                                        <span class="pill <?= ($followup['service_health'] ?? '') === 'red' ? 'soft-red' : ((($followup['service_health'] ?? '') === 'yellow') ? 'soft-amber' : 'soft-green') ?>">
                                            <?= htmlspecialchars($followup['service_health'] ?? 'green') ?>
                                        </span>
                                    </td>
                                    <td class="text-right"><?= htmlspecialchars($followup['period_end'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="muted">Sin seguimientos recientes registrados.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <h2 class="section-title">Gobierno y control</h2>
        <div class="section-grid">
            <div class="card">
                <div class="metric-grid">
                    <div class="metric-item">
                        <span class="label">Docs en revisión</span>
                        <span class="value"><?= $governance['documents_revision'] ?? 0 ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Docs en validación</span>
                        <span class="value"><?= $governance['documents_validacion'] ?? 0 ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Docs en aprobación</span>
                        <span class="value"><?= $governance['documents_aprobacion'] ?? 0 ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Cambios de alcance</span>
                        <span class="value"><?= $governance['scope_changes_pending'] ?? 0 ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Riesgos críticos</span>
                        <span class="value"><?= $governance['critical_risks'] ?? 0 ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Outsourcing vencido</span>
                        <span class="value"><?= $governance['outsourcing_overdue'] ?? 0 ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <h2 class="section-title">Alertas inteligentes</h2>
        <div class="card elevated">
            <ul class="list">
                <?php foreach ($alerts as $alert): ?>
                    <li><?= htmlspecialchars($alert) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
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
    const projectHourLabels = <?= json_encode(array_map(static fn ($row) => $row['project'] ?? '', $timesheets['hours_by_project'] ?? [])) ?>;
    const projectHourData = <?= json_encode(array_map(static fn ($row) => round((float) ($row['total_hours'] ?? 0), 1), $timesheets['hours_by_project'] ?? [])) ?>;
    const talentHourLabels = <?= json_encode(array_map(static fn ($row) => $row['talent'] ?? '', $timesheets['hours_by_talent'] ?? [])) ?>;
    const talentHourData = <?= json_encode(array_map(static fn ($row) => round((float) ($row['total_hours'] ?? 0), 1), $timesheets['hours_by_talent'] ?? [])) ?>;

    const chartPalette = {
        grid: 'rgba(148, 163, 184, 0.6)',
        textMain: '#0f172a',
        textMuted: '#475569',
        primaryFill: 'rgba(37, 99, 235, 0.9)',
        primaryBorder: 'rgba(30, 64, 175, 0.95)',
        secondaryFill: 'rgba(16, 185, 129, 0.9)',
        secondaryBorder: 'rgba(4, 120, 87, 0.95)',
        accentFill: 'rgba(245, 158, 11, 0.9)',
        accentBorder: 'rgba(180, 83, 9, 0.95)',
        mutedFill: 'rgba(100, 116, 139, 0.9)',
        mutedBorder: 'rgba(51, 65, 85, 0.95)'
    };

    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Planning', 'En curso', 'En riesgo', 'Cerrado'],
            datasets: [{ data: statusData, backgroundColor: [chartPalette.secondaryFill, chartPalette.primaryFill, chartPalette.accentFill, chartPalette.mutedFill], borderColor: [chartPalette.secondaryBorder, chartPalette.primaryBorder, chartPalette.accentBorder, chartPalette.mutedBorder], borderWidth: 1.4 }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: chartPalette.textMain } } }, cutout: '62%' }
    });

    const progressCtx = document.getElementById('progressChart').getContext('2d');
    new Chart(progressCtx, {
        type: 'bar',
        data: {
            labels: progressLabels,
            datasets: [{ label: 'Avance %', data: progressData, backgroundColor: chartPalette.primaryFill, borderColor: chartPalette.primaryBorder, borderWidth: 1.4, borderRadius: 10 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: chartPalette.grid }, ticks: { color: chartPalette.textMuted, callback: value => value + '%' } },
                x: { grid: { display: false }, ticks: { color: chartPalette.textMuted } }
            }
        }
    });

    const hoursProjectCanvas = document.getElementById('hoursProjectChart');
    if (hoursProjectCanvas && projectHourLabels.length) {
        new Chart(hoursProjectCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: projectHourLabels,
                datasets: [{ label: 'Horas', data: projectHourData, backgroundColor: chartPalette.secondaryFill, borderColor: chartPalette.secondaryBorder, borderWidth: 1.2, borderRadius: 8 }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: chartPalette.grid }, ticks: { color: chartPalette.textMuted } },
                    x: { grid: { display: false }, ticks: { color: chartPalette.textMuted } }
                }
            }
        });
    }

    const hoursTalentCanvas = document.getElementById('hoursTalentChart');
    if (hoursTalentCanvas && talentHourLabels.length) {
        new Chart(hoursTalentCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: talentHourLabels,
                datasets: [{ label: 'Horas', data: talentHourData, backgroundColor: chartPalette.accentFill, borderColor: chartPalette.accentBorder, borderWidth: 1.2, borderRadius: 8 }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: chartPalette.grid }, ticks: { color: chartPalette.textMuted } },
                    x: { grid: { display: false }, ticks: { color: chartPalette.textMuted } }
                }
            }
        });
    }
</script>
