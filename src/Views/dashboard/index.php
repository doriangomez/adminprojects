<div class="grid">
    <div class="card kpi">
        <span class="card-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 21v-7a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v7"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </span>
        <div class="card-group">
            <span class="label">Clientes activos</span>
            <span class="value"><?= $summary['clientes_activos'] ?></span>
            <span class="meta">Organizaciones con operaciones en curso</span>
        </div>
    </div>
    <div class="card kpi">
        <span class="card-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h6v6H4z"></path>
                <path d="M14 4h6v10h-6z"></path>
                <path d="M4 14h6v6H4z"></path>
                <path d="M14 16h6v4h-6z"></path>
            </svg>
        </span>
        <div class="card-group">
            <span class="label">Proyectos en ejecución</span>
            <span class="value"><?= $summary['proyectos_activos'] ?></span>
            <span class="meta">Entregas supervisadas por el PMO</span>
        </div>
    </div>
    <div class="card kpi">
        <span class="card-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12h16"></path>
                <path d="M4 12c4-4 6-6 8-6s4 2 8 6"></path>
                <path d="M4 12a9 9 0 0 0 16 0"></path>
            </svg>
        </span>
        <div class="card-group">
            <span class="label">Ingresos totales</span>
            <span class="value">$<?= number_format($summary['ingresos_totales'], 0, ',', '.') ?></span>
            <span class="meta">Facturación consolidada del portafolio</span>
        </div>
    </div>
    <div class="card kpi">
        <span class="card-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21 12 17 5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2Z"></path>
                <path d="M9 9h6"></path>
                <path d="M9 13h3"></path>
            </svg>
        </span>
        <div class="card-group">
            <span class="label">Proyectos en riesgo</span>
            <span class="value"><?= $summary['proyectos_riesgo'] ?></span>
            <span class="meta">Alertas por cumplimiento y presupuesto</span>
        </div>
    </div>
</div>

<div class="grid" style="margin-top: 14px;">
    <div class="card">
        <div class="toolbar">
            <div class="title-stack">
                <span class="card-icon neutral" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 7v6l3 3"></path>
                    </svg>
                </span>
                <h3 class="section-title">Salud del portafolio</h3>
            </div>
            <div class="badge <?= $portfolio['at_risk'] > 0 ? 'danger' : 'success' ?>">Riesgo: <?= $portfolio['at_risk'] ?></div>
        </div>
        <div class="mini-grid">
            <div class="kpi column">
                <span class="label">Avance promedio</span>
                <span class="value"><?= $portfolio['avg_progress'] ?>%</span>
                <span class="meta">Seguimiento de hitos críticos</span>
            </div>
            <div class="kpi column">
                <span class="label">Horas planificadas</span>
                <span class="value"><?= $portfolio['planned_hours'] ?></span>
                <span class="meta">Asignaciones presupuestadas</span>
            </div>
            <div class="kpi column">
                <span class="label">Horas reales</span>
                <span class="value"><?= $portfolio['actual_hours'] ?></span>
                <span class="meta">Ejecutado a la fecha</span>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="toolbar">
            <div class="title-stack">
                <span class="card-icon neutral" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7h18"></path>
                        <path d="M5 11h14"></path>
                        <path d="M7 15h10"></path>
                        <path d="M9 19h6"></path>
                    </svg>
                </span>
                <h3 class="section-title" style="margin:0;">Timesheets</h3>
            </div>
        </div>
        <div class="mini-grid">
            <div class="kpi column">
                <span class="label">Borradores</span>
                <span class="value"><?= $timesheetKpis['draft'] ?></span>
                <span class="meta">Pendientes de envío</span>
            </div>
            <div class="kpi column">
                <span class="label">Enviadas</span>
                <span class="value"><?= $timesheetKpis['submitted'] ?></span>
                <span class="meta">En aprobación</span>
            </div>
            <div class="kpi column">
                <span class="label">Aprobadas</span>
                <span class="value success"><?= $timesheetKpis['approved'] ?></span>
                <span class="meta">Disponibles para facturar</span>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 14px;">
    <div class="toolbar">
        <div class="title-stack">
            <span class="card-icon neutral" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3h18v4H3z"></path>
                    <path d="M3 11h18v10H3z"></path>
                    <path d="M7 15h4"></path>
                </svg>
            </span>
            <h3 class="section-title">Rentabilidad por proyecto</h3>
        </div>
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
