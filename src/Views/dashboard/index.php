<div class="executive-dashboard">
    <style>
        .executive-dashboard {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        .card {
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }
        .card.elevated { box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06); }
        .kpi-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: linear-gradient(135deg, rgba(226, 232, 240, 0.45), rgba(248, 250, 252, 0.9));
            border: 1px solid rgba(148, 163, 184, 0.4);
        }
        .kpi-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(59, 130, 246, 0.12);
            color: #1d4ed8;
            border: 1px solid rgba(59, 130, 246, 0.18);
        }
        .kpi-icon svg { width: 26px; height: 26px; stroke: currentColor; }
        .kpi-card .meta { display: flex; flex-direction: column; gap: 6px; }
        .kpi-card .label { font-size: 13px; color: #4b5563; letter-spacing: 0.01em; }
        .kpi-card .value { font-size: 26px; font-weight: 800; color: #111827; }
        .kpi-card[data-tone="green"] .kpi-icon { background: rgba(52, 211, 153, 0.16); color: #0f766e; border-color: rgba(16, 185, 129, 0.24); }
        .kpi-card[data-tone="amber"] .kpi-icon { background: rgba(251, 191, 36, 0.18); color: #b45309; border-color: rgba(251, 191, 36, 0.24); }
        .kpi-card[data-tone="slate"] .kpi-icon { background: rgba(148, 163, 184, 0.16); color: #0f172a; border-color: rgba(148, 163, 184, 0.24); }

        .section-grid { display: grid; grid-template-columns: 2fr 1.1fr; gap: 14px; align-items: stretch; }
        .section-grid .card { height: 100%; }
        .toolbar { display:flex; align-items:center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
        .toolbar h3 { margin:0; color:#0f172a; font-size:17px; font-weight:800; }
        .muted { color: #475569; font-size: 14px; margin: 0; }
        .progress-track { width: 100%; background: rgba(148, 163, 184, 0.18); border-radius: 999px; height: 10px; overflow: hidden; margin: 10px 0 6px; }
        .progress-bar { height: 100%; border-radius: 999px; background: linear-gradient(90deg, rgba(14, 165, 233, 0.8), rgba(16, 185, 129, 0.8)); }
        .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px; }
        .pill { display: inline-flex; align-items:center; gap: 8px; padding: 8px 12px; border-radius: 999px; font-weight: 700; font-size: 13px; }
        .pill.soft-green { background: rgba(16, 185, 129, 0.12); color: #0f766e; border: 1px solid rgba(16, 185, 129, 0.2); }
        .pill.soft-amber { background: rgba(251, 191, 36, 0.12); color: #92400e; border: 1px solid rgba(251, 191, 36, 0.2); }
        .pill.soft-blue { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; border: 1px solid rgba(59, 130, 246, 0.2); }

        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; }
        .chart-card h4 { margin: 0 0 6px 0; font-size: 15px; color: #0f172a; font-weight: 800; }
        .chart-legend { font-size: 13px; color: #475569; margin: 6px 0 0; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead th { text-align: left; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; padding: 10px 8px; border-bottom: 1px solid rgba(148, 163, 184, 0.35); }
        tbody td { padding: 12px 8px; border-bottom: 1px solid rgba(148, 163, 184, 0.16); font-weight: 600; color: #0f172a; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: rgba(226, 232, 240, 0.35); }
        .text-right { text-align: right; }
        .badge { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 10px; font-size: 13px; font-weight: 700; }
        .badge.success { background: rgba(16, 185, 129, 0.12); color: #0f766e; border: 1px solid rgba(16, 185, 129, 0.18); }
        .badge.danger { background: rgba(248, 113, 113, 0.12); color: #b91c1c; border: 1px solid rgba(248, 113, 113, 0.2); }
        .badge.soft { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; border: 1px solid rgba(59, 130, 246, 0.18); }

        .timesheet-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-top: 8px; }
        .tiny { font-size: 12px; color: #475569; letter-spacing: 0.02em; text-transform: uppercase; }
    </style>
    <?php
    $riskLevels = is_array($portfolio['risk_levels'] ?? null)
        ? array_merge(['alto' => 0, 'medio' => 0, 'bajo' => 0], $portfolio['risk_levels'])
        : ['alto' => 0, 'medio' => 0, 'bajo' => 0];
    ?>

    <div class="kpi-grid">
        <div class="card kpi-card" data-tone="blue">
            <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/><path d="M8 7v10"/></svg></span>
            <div class="meta">
                <span class="label">Clientes activos</span>
                <span class="value"><?= $summary['clientes_activos'] ?></span>
            </div>
        </div>
        <div class="card kpi-card" data-tone="slate">
            <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 8h16v9H4z"/><path d="M9 8V5h6v3"/><path d="m10 13 2 2 4-4"/></svg></span>
            <div class="meta">
                <span class="label">Proyectos en ejecución</span>
                <span class="value"><?= $summary['proyectos_activos'] ?></span>
            </div>
        </div>
        <div class="card kpi-card" data-tone="amber">
            <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 6h16v12H4z"/><path d="M8 10a2 2 0 1 0 0 4h8a2 2 0 1 0 0-4Z"/></svg></span>
            <div class="meta">
                <span class="label">Ingresos totales</span>
                <span class="value">$<?= number_format($summary['ingresos_totales'], 0, ',', '.') ?></span>
            </div>
        </div>
        <div class="card kpi-card" data-tone="green">
            <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M12 3v18"/><path d="M6 9h6M6 15h6"/><path d="m12 15 6-4v8Z"/></svg></span>
            <div class="meta">
                <span class="label">Proyectos en riesgo</span>
                <span class="value"><?= $summary['proyectos_riesgo'] ?></span>
            </div>
        </div>
    </div>

    <div class="section-grid">
        <div class="card elevated">
            <div class="toolbar">
                <h3>Salud de proyectos</h3>
                <span class="pill <?= $portfolio['at_risk'] > 0 ? 'soft-amber' : 'soft-green' ?>">Riesgo: <?= $portfolio['at_risk'] ?></span>
            </div>
            <p class="muted">Avance promedio</p>
            <div class="progress-track" aria-hidden="true">
                <div class="progress-bar" style="width: <?= max(0, min(100, $portfolio['avg_progress'])) ?>%"></div>
            </div>
            <div class="stat-row">
                <div class="pill soft-blue">Promedio: <?= $portfolio['avg_progress'] ?>%</div>
                <div class="pill soft-green">Horas planificadas: <?= $portfolio['planned_hours'] ?></div>
                <div class="pill soft-amber">Horas reales: <?= $portfolio['actual_hours'] ?></div>
                <div class="pill soft-blue">Score riesgo prom.: <?= number_format((float) ($portfolio['avg_risk_score'] ?? 0), 1) ?></div>
                <div class="pill soft-amber">Niveles: A <?= $riskLevels['alto'] ?> • M <?= $riskLevels['medio'] ?> • B <?= $riskLevels['bajo'] ?></div>
            </div>
        </div>
        <div class="card">
            <div class="toolbar">
                <h3>Timesheets</h3>
                <span class="tiny">Seguimiento semanal</span>
            </div>
            <div class="timesheet-grid">
                <div class="card kpi-card" data-tone="slate" style="margin:0;">
                    <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8"/><path d="M8 12h8"/></svg></span>
                    <div class="meta">
                        <span class="label">Borradores</span>
                        <span class="value"><?= $timesheetKpis['draft'] ?></span>
                    </div>
                </div>
                <div class="card kpi-card" data-tone="blue" style="margin:0;">
                    <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M5 5h14v14H5z"/><path d="M8 9h8"/><path d="M8 13h4"/></svg></span>
                    <div class="meta">
                        <span class="label">Pendientes</span>
                        <span class="value"><?= $timesheetKpis['pending'] ?? 0 ?></span>
                    </div>
                </div>
                <div class="card kpi-card" data-tone="green" style="margin:0;">
                    <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M5 5h14v14H5z"/><path d="m9 12 2.2 2L15 10"/></svg></span>
                    <div class="meta">
                        <span class="label">Aprobadas</span>
                        <span class="value"><?= $timesheetKpis['approved'] ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="card chart-card">
            <h4>Horas planificadas vs reales</h4>
            <canvas id="hoursChart" height="140"></canvas>
            <p class="chart-legend">Comparativo de esfuerzo global de proyectos.</p>
        </div>
        <div class="card chart-card">
            <h4>Proyectos por estado</h4>
            <canvas id="statusChart" height="140"></canvas>
            <p class="chart-legend">Distribución entre proyectos activos y en riesgo.</p>
        </div>
        <div class="card chart-card">
            <h4>Avance promedio de proyectos</h4>
            <canvas id="progressChart" height="140"></canvas>
            <p class="chart-legend">Seguimiento ejecutivo del avance consolidado.</p>
        </div>
    </div>

    <div class="card elevated">
        <div class="toolbar">
            <h3>Rentabilidad por proyecto</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Proyecto</th>
                    <th class="text-right">Presupuesto</th>
                    <th class="text-right">Costo real</th>
                    <th class="text-right">Margen</th>
                    <th class="text-right">Horas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($profitability as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td class="text-right">$<?= number_format($row['budget'], 0, ',', '.') ?></td>
                        <td class="text-right">$<?= number_format($row['actual_cost'], 0, ',', '.') ?></td>
                        <td class="text-right"><span class="badge <?= $row['margin'] >= 0 ? 'success' : 'danger' ?>">$<?= number_format($row['margin'], 0, ',', '.') ?></span></td>
                        <td class="text-right"><?= $row['actual_hours'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const plannedHours = <?= (int) $portfolio['planned_hours'] ?>;
    const actualHours = <?= (int) $portfolio['actual_hours'] ?>;
    const atRisk = <?= (int) $portfolio['at_risk'] ?>;
    const activeProjects = <?= (int) $summary['proyectos_activos'] ?>;
    const avgProgress = <?= (float) $portfolio['avg_progress'] ?>;

    const statusData = [activeProjects, atRisk];
    const progressData = [avgProgress];

    const pastelPalette = {
        blue: 'rgba(99, 179, 237, 0.65)',
        blueBorder: 'rgba(59, 130, 246, 0.9)',
        green: 'rgba(134, 239, 172, 0.7)',
        greenBorder: 'rgba(16, 185, 129, 0.9)',
        amber: 'rgba(252, 211, 77, 0.7)',
        amberBorder: 'rgba(217, 119, 6, 0.9)'
    };

    const hoursCtx = document.getElementById('hoursChart').getContext('2d');
    new Chart(hoursCtx, {
        type: 'bar',
        data: {
            labels: ['Proyectos'],
            datasets: [
                { label: 'Planificadas', data: [plannedHours], backgroundColor: pastelPalette.blue, borderColor: pastelPalette.blueBorder, borderWidth: 1.4, borderRadius: 10 },
                { label: 'Reales', data: [actualHours], backgroundColor: pastelPalette.green, borderColor: pastelPalette.greenBorder, borderWidth: 1.4, borderRadius: 10 }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 14, color: '#0f172a' } } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, 0.2)' }, ticks: { color: '#475569' } },
                x: { grid: { display: false }, ticks: { color: '#475569' } }
            }
        }
    });

    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['En ejecución', 'En riesgo'],
            datasets: [{ data: statusData, backgroundColor: [pastelPalette.blue, pastelPalette.amber], borderColor: ['rgba(59, 130, 246, 0.6)', 'rgba(217, 119, 6, 0.6)'], borderWidth: 1.4 }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#0f172a' } } }, cutout: '62%' }
    });

    const progressCtx = document.getElementById('progressChart').getContext('2d');
    new Chart(progressCtx, {
        type: 'line',
        data: {
            labels: ['Proyectos'],
            datasets: [{ label: 'Avance promedio', data: progressData, borderColor: pastelPalette.blueBorder, backgroundColor: 'rgba(59, 130, 246, 0.18)', fill: true, tension: 0.35, borderWidth: 2 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 0, max: 100, grid: { color: 'rgba(148, 163, 184, 0.18)' }, ticks: { color: '#475569', callback: value => value + '%' } },
                x: { grid: { display: false }, ticks: { color: '#475569' } }
            }
        }
    });
</script>
