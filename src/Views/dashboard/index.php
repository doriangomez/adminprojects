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
            color: var(--text-main);
            margin: 0 0 10px;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        }
        .card.elevated { box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06); }
        .kpi-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: linear-gradient(135deg, rgba(226, 232, 240, 0.4), rgba(248, 250, 252, 0.92));
            border: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
        }
        .kpi-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: color-mix(in srgb, var(--primary) 14%, transparent);
            color: var(--primary-strong);
            border: 1px solid color-mix(in srgb, var(--primary) 22%, transparent);
        }
        .kpi-icon svg { width: 26px; height: 26px; stroke: currentColor; }
        .kpi-card .meta { display: flex; flex-direction: column; gap: 6px; }
        .kpi-card .label { font-size: 13px; color: var(--text-muted); letter-spacing: 0.01em; }
        .kpi-card .value { font-size: 24px; font-weight: 800; color: var(--text-main); }
        .kpi-card[data-tone="green"] .kpi-icon { background: rgba(52, 211, 153, 0.16); color: #0f766e; border-color: rgba(16, 185, 129, 0.24); }
        .kpi-card[data-tone="amber"] .kpi-icon { background: rgba(251, 191, 36, 0.18); color: #b45309; border-color: rgba(251, 191, 36, 0.24); }
        .kpi-card[data-tone="slate"] .kpi-icon { background: rgba(148, 163, 184, 0.16); color: var(--text-main); border-color: rgba(148, 163, 184, 0.24); }
        .kpi-card[data-tone="blue"] .kpi-icon { background: rgba(59, 130, 246, 0.16); color: #1d4ed8; border-color: rgba(59, 130, 246, 0.24); }
        .kpi-card[data-tone="purple"] .kpi-icon { background: rgba(168, 85, 247, 0.16); color: #6d28d9; border-color: rgba(168, 85, 247, 0.24); }

        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
        .chart-card h4 { margin: 0 0 6px 0; font-size: 15px; color: var(--text-main); font-weight: 800; }
        .chart-legend { font-size: 13px; color: var(--text-muted); margin: 6px 0 0; }

        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .metric-item { display: flex; flex-direction: column; gap: 6px; }
        .metric-item .label { font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
        .metric-item .value { font-size: 20px; font-weight: 800; color: var(--text-main); }

        .list { margin: 0; padding-left: 18px; color: var(--text-main); font-size: 14px; }
        .list li { margin-bottom: 6px; }
        .muted { color: var(--text-muted); font-size: 14px; margin: 0; }

        .section-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
        .pill { display: inline-flex; align-items:center; gap: 8px; padding: 6px 10px; border-radius: 999px; font-weight: 700; font-size: 12px; }
        .pill.soft-green { background: rgba(16, 185, 129, 0.12); color: #0f766e; border: 1px solid rgba(16, 185, 129, 0.2); }
        .pill.soft-amber { background: rgba(251, 191, 36, 0.12); color: #92400e; border: 1px solid rgba(251, 191, 36, 0.2); }
        .pill.soft-blue { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; border: 1px solid rgba(59, 130, 246, 0.2); }
        .pill.soft-red { background: rgba(248, 113, 113, 0.12); color: #b91c1c; border: 1px solid rgba(248, 113, 113, 0.2); }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); padding: 10px 8px; border-bottom: 1px solid color-mix(in srgb, var(--border) 80%, transparent); }
        tbody td { padding: 12px 8px; border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, transparent); font-weight: 600; color: var(--text-main); }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: color-mix(in srgb, var(--bg-card) 90%, var(--bg-app) 10%); }
        .text-right { text-align: right; }
    </style>

    <?php
    $statusCounts = $projects['status_counts'] ?? ['planning' => 0, 'en_curso' => 0, 'en_riesgo' => 0, 'cerrado' => 0];
    $progressByClient = $projects['progress_by_client'] ?? [];
    $staleProjects = $projects['stale_projects'] ?? [];
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
                        <span class="label">Internos vs externos</span>
                        <span class="value"><?= $timesheets['internal_talents'] ?? 0 ?> / <?= $timesheets['external_talents'] ?? 0 ?></span>
                    </div>
                </div>
                <p class="muted">Semana <?= htmlspecialchars($timesheets['period_start'] ?? '') ?> - <?= htmlspecialchars($timesheets['period_end'] ?? '') ?></p>
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

    const cssVars = getComputedStyle(document.documentElement);
    const primary = cssVars.getPropertyValue('--primary').trim();
    const secondary = cssVars.getPropertyValue('--secondary').trim();
    const accent = cssVars.getPropertyValue('--accent').trim();
    const textMain = cssVars.getPropertyValue('--text-main').trim();
    const textMuted = cssVars.getPropertyValue('--text-muted').trim();
    const gridBorder = cssVars.getPropertyValue('--border').trim();
    const toRgba = (color, alpha) => {
        if (!color) {
            return '';
        }
        if (color.startsWith('#')) {
            const hex = color.replace('#', '');
            const value = hex.length === 3
                ? hex.split('').map(char => char + char).join('')
                : hex;
            const intVal = parseInt(value, 16);
            const r = (intVal >> 16) & 255;
            const g = (intVal >> 8) & 255;
            const b = intVal & 255;
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
        if (color.startsWith('rgb(')) {
            return color.replace('rgb(', 'rgba(').replace(')', `, ${alpha})`);
        }
        if (color.startsWith('rgba(')) {
            return color.replace(/rgba\(([^,]+,[^,]+,[^,]+),[^)]+\)/, `rgba($1, ${alpha})`);
        }
        return color;
    };
    const gridBorderSoft = toRgba(gridBorder, 0.6);
    const primaryFill = toRgba(primary, 0.55);
    const primaryBorder = toRgba(primary, 0.9);
    const secondaryFill = toRgba(secondary, 0.5);
    const secondaryBorder = toRgba(secondary, 0.9);
    const accentFill = toRgba(accent, 0.55);
    const accentBorder = toRgba(accent, 0.9);

    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Planning', 'En curso', 'En riesgo', 'Cerrado'],
            datasets: [{ data: statusData, backgroundColor: [secondaryFill, primaryFill, accentFill, toRgba('#94a3b8', 0.5)], borderColor: [secondaryBorder, primaryBorder, accentBorder, toRgba('#94a3b8', 0.9)], borderWidth: 1.4 }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: textMain } } }, cutout: '62%' }
    });

    const progressCtx = document.getElementById('progressChart').getContext('2d');
    new Chart(progressCtx, {
        type: 'bar',
        data: {
            labels: progressLabels,
            datasets: [{ label: 'Avance %', data: progressData, backgroundColor: primaryFill, borderColor: primaryBorder, borderWidth: 1.4, borderRadius: 10 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: gridBorderSoft }, ticks: { color: textMuted, callback: value => value + '%' } },
                x: { grid: { display: false }, ticks: { color: textMuted } }
            }
        }
    });
</script>
