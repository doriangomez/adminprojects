<div class="grid">
    <div class="card kpi">
        <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/><path d="M8 7v10"/></svg></span>
        <div class="kpi-body">
            <span class="label">Clientes activos</span>
            <span class="value"><?= $summary['clientes_activos'] ?></span>
        </div>
    </div>
    <div class="card kpi">
        <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 8h16v9H4z"/><path d="M9 8V5h6v3"/><path d="m10 13 2 2 4-4"/></svg></span>
        <div class="kpi-body">
            <span class="label">Proyectos en ejecución</span>
            <span class="value"><?= $summary['proyectos_activos'] ?></span>
        </div>
    </div>
    <div class="card kpi">
        <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M4 6h16v12H4z"/><path d="M8 10a2 2 0 1 0 0 4h8a2 2 0 1 0 0-4Z"/></svg></span>
        <div class="kpi-body">
            <span class="label">Ingresos totales</span>
            <span class="value">$<?= number_format($summary['ingresos_totales'], 0, ',', '.') ?></span>
        </div>
    </div>
    <div class="card kpi">
        <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M12 3v18"/><path d="M6 9h6M6 15h6"/><path d="m12 15 6-4v8Z"/></svg></span>
        <div class="kpi-body">
            <span class="label">Proyectos en riesgo</span>
            <span class="value"><?= $summary['proyectos_riesgo'] ?></span>
        </div>
    </div>
</div>

<div class="grid" style="margin-top: 14px;">
    <div class="card">
        <div class="toolbar">
            <h3 style="margin:0;">Salud del portafolio</h3>
            <div class="badge <?= $portfolio['at_risk'] > 0 ? 'danger' : 'success' ?>">Riesgo: <?= $portfolio['at_risk'] ?></div>
        </div>
        <p style="margin:0; color: var(--muted);">Promedio de avance: <?= $portfolio['avg_progress'] ?>%</p>
        <p style="margin:0; color: var(--muted);">Horas planificadas: <?= $portfolio['planned_hours'] ?> | Horas reales: <?= $portfolio['actual_hours'] ?></p>
    </div>
    <div class="card">
        <div class="toolbar">
            <h3 style="margin:0;">Timesheets</h3>
        </div>
        <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap:8px;">
            <div class="kpi">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8"/><path d="M8 12h8"/></svg></span>
                <div class="kpi-body">
                    <span class="label">Borradores</span>
                    <span class="value"><?= $timesheetKpis['draft'] ?></span>
                </div>
            </div>
            <div class="kpi">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M5 5h14v14H5z"/><path d="M8 9h8"/><path d="M8 13h4"/></svg></span>
                <div class="kpi-body">
                    <span class="label">Enviadas</span>
                    <span class="value"><?= $timesheetKpis['submitted'] ?></span>
                </div>
            </div>
            <div class="kpi">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M5 5h14v14H5z"/><path d="m9 12 2.2 2L15 10"/></svg></span>
                <div class="kpi-body">
                    <span class="label">Aprobadas</span>
                    <span class="value success"><?= $timesheetKpis['approved'] ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 14px;">
    <div class="toolbar">
        <h3 style="margin:0;">Rentabilidad por proyecto</h3>
    </div>
    <table>
        <thead>
            <tr>
                <th>Proyecto</th>
                <th>Presupuesto</th>
                <th>Costo real</th>
                <th>Margen</th>
                <th>Horas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($profitability as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td>$<?= number_format($row['budget'], 0, ',', '.') ?></td>
                    <td>$<?= number_format($row['actual_cost'], 0, ',', '.') ?></td>
                    <td><span class="badge <?= $row['margin'] >= 0 ? 'success' : 'danger' ?>">$<?= number_format($row['margin'], 0, ',', '.') ?></span></td>
                    <td><?= $row['actual_hours'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
