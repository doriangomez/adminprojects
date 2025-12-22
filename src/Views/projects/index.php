<?php
$projectsList = is_array($projects ?? null) ? $projects : [];
$basePath = $basePath ?? '/project/public';

$activeStatuses = ['active', 'activo', 'en_ejecucion', 'en_progreso', 'in_progress', 'running', 'ejecucion'];
$completedStatuses = ['completed', 'completado', 'cerrado', 'closed', 'finalizado', 'archivado', 'archived'];
$riskHealth = ['at_risk', 'riesgo', 'risk', 'yellow', 'red', 'warning', 'critical'];

$activeProjects = 0;
$riskProjects = 0;
$completedProjects = 0;
$hoursUsed = 0;
$plannedHours = 0;
$budgetTotal = 0;
$actualCostTotal = 0;

foreach ($projectsList as $project) {
    $status = strtolower((string) ($project['status'] ?? ''));
    $health = strtolower((string) ($project['health'] ?? ''));

    if (in_array($status, $activeStatuses, true)) {
        $activeProjects++;
    }

    if (in_array($status, $completedStatuses, true)) {
        $completedProjects++;
    }

    if (in_array($health, $riskHealth, true)) {
        $riskProjects++;
    }

    $hoursUsed += (float) ($project['actual_hours'] ?? 0);
    $plannedHours += (float) ($project['planned_hours'] ?? 0);
    $budgetTotal += (float) ($project['budget'] ?? 0);
    $actualCostTotal += (float) ($project['actual_cost'] ?? 0);
}

$budgetCoverage = $budgetTotal > 0
    ? min(999, round(($actualCostTotal / $budgetTotal) * 100, 1))
    : 0;
$progressAverage = $projectsList
    ? round(array_sum(array_map(fn ($p) => (float) ($p['progress'] ?? 0), $projectsList)) / count($projectsList), 1)
    : 0;

$healthBadgeClass = static function (string $health): string {
    return match ($health) {
        'red', 'critical' => 'danger',
        'yellow', 'warning', 'at_risk', 'risk', 'riesgo' => 'warning',
        default => 'success',
    };
};

$statusPillClass = static function (string $status) use ($activeStatuses, $completedStatuses): string {
    $status = strtolower($status);
    if (in_array($status, $completedStatuses, true)) {
        return 'soft-green';
    }

    if (in_array($status, $activeStatuses, true)) {
        return 'soft-blue';
    }

    return 'soft-slate';
};
?>

<style>
    .projects-hero {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    }

    .projects-hero h1 {
        margin: 0 0 6px;
        font-size: 28px;
        color: var(--text-strong);
    }

    .projects-hero p {
        margin: 0;
        color: var(--muted);
        font-weight: 500;
    }

    .hero-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .ghost-button,
    .primary-button,
    .secondary-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 14px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        border: 1px solid transparent;
        font-size: 14px;
    }

    .primary-button {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
        box-shadow: 0 10px 25px rgba(37, 99, 235, 0.25);
    }

    .primary-button:hover { transform: translateY(-1px); }

    .secondary-button {
        background: #0f172a;
        color: #fff;
    }

    .secondary-button:hover { transform: translateY(-1px); }

    .ghost-button {
        background: #eef2ff;
        color: #312e81;
        border-color: #c7d2fe;
    }

    .ghost-button:hover { transform: translateY(-1px); }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 14px;
    }

    .kpi-card {
        padding: 16px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface);
        display: flex;
        flex-direction: column;
        gap: 10px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
    }

    .kpi-card .label { color: var(--muted); font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
    .kpi-card .value { margin: 0; font-size: 26px; color: var(--text-strong); font-weight: 800; }
    .kpi-trend { display: flex; align-items: center; gap: 8px; color: var(--text); font-weight: 600; }
    .kpi-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; }

    .soft-blue { background: #e0e7ff; color: #1d4ed8; }
    .soft-green { background: #dcfce7; color: #15803d; }
    .soft-amber { background: #fef3c7; color: #b45309; }
    .soft-rose { background: #ffe4e6; color: #be123c; }
    .soft-slate { background: #e5e7eb; color: #374151; }

    .project-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 16px;
    }

    .project-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 18px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
    }

    .project-card header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }

    .project-title { margin: 0 0 4px; color: var(--text-strong); font-size: 18px; }
    .project-meta { margin: 0; color: var(--muted); font-weight: 600; font-size: 13px; }

    .pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 12px;
        border: 1px solid transparent;
    }

    .badge { border-radius: 10px; padding: 4px 8px; font-weight: 700; font-size: 12px; display: inline-flex; gap: 6px; align-items: center; }
    .badge.success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
    .badge.warning { background: #fef9c3; color: #92400e; border: 1px solid #fde68a; }
    .badge.danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
    .info-item { display: flex; align-items: center; gap: 8px; padding: 10px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .info-item .icon { width: 30px; height: 30px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: #e5e7eb; color: #111827; font-weight: 700; }
    .info-item span { font-weight: 700; color: var(--text); }
    .info-item small { display: block; color: var(--muted); font-weight: 600; }

    .progress-track { width: 100%; height: 10px; background: #e5e7eb; border-radius: 999px; overflow: hidden; }
    .progress-bar { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #6366f1, #22c55e); }
    .progress-row { display: flex; align-items: center; justify-content: space-between; }

    .actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .action-btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border); background: #f8fafc; color: var(--text-strong); font-weight: 700; text-decoration: none; font-size: 13px; }
    .action-btn:hover { background: #eef2ff; color: #312e81; border-color: #c7d2fe; }

    .empty-state { padding: 18px; border-radius: 14px; background: #f8fafc; border: 1px solid var(--border); color: var(--muted); font-weight: 600; }
</style>

<div class="projects-hero">
    <div>
        <p class="eyebrow" style="margin:0; color: var(--muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Panel</p>
        <h1>Proyectos</h1>
        <p>Ejecución y control operativo</p>
    </div>
    <div class="hero-actions">
        <a class="primary-button" href="<?= $basePath ?>/projects/create">
            <span aria-hidden="true">＋</span>
            Nuevo proyecto
        </a>
        <a class="ghost-button" href="<?= $basePath ?>/projects/portfolio">
            <span aria-hidden="true">↗</span>
            Ir a portafolios
        </a>
        <a class="secondary-button" href="<?= $basePath ?>/tasks">
            <span aria-hidden="true">📊</span>
            Ver tablero
        </a>
    </div>
</div>

<section class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon soft-blue" aria-hidden="true">🚀</div>
        <p class="label">Proyectos activos</p>
        <p class="value"><?= $activeProjects ?></p>
        <div class="kpi-trend">Visión en ejecución • Promedio avance <?= $progressAverage ?>%</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon soft-amber" aria-hidden="true">⚠️</div>
        <p class="label">Proyectos en riesgo</p>
        <p class="value"><?= $riskProjects ?></p>
        <div class="kpi-trend">Analiza señales • Salud expresada en texto y badge</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon soft-green" aria-hidden="true">✅</div>
        <p class="label">Proyectos completados</p>
        <p class="value"><?= $completedProjects ?></p>
        <div class="kpi-trend">Incluye cierres y archivados recientes</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon soft-slate" aria-hidden="true">⏱️</div>
        <p class="label">Horas consumidas</p>
        <p class="value"><?= number_format($hoursUsed, 0, ',', '.') ?>h / <?= number_format($plannedHours, 0, ',', '.') ?>h</p>
        <div class="kpi-trend">Seguimiento operativo sin perder detalle</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon soft-rose" aria-hidden="true">💰</div>
        <p class="label">Presupuesto vs costo real</p>
        <p class="value">$<?= number_format($actualCostTotal, 0, ',', '.') ?> / $<?= number_format($budgetTotal, 0, ',', '.') ?></p>
        <div class="kpi-trend">Cobertura <?= $budgetCoverage ?>% • Controla desviaciones</div>
    </div>
</section>

<?php if (empty($projectsList)): ?>
    <div class="empty-state">Aún no hay proyectos registrados. Crea uno nuevo para comenzar a monitorear ejecución y riesgos.</div>
<?php else: ?>
    <div class="project-grid">
        <?php foreach ($projectsList as $project): ?>
            <?php
                $statusLabel = $project['status_label'] ?? $project['status'] ?? 'Estado no registrado';
                $healthLabel = $project['health_label'] ?? $project['health'] ?? 'Salud no registrada';
                $riskClass = $healthBadgeClass(strtolower((string) ($project['health'] ?? '')));
                $progress = (float) ($project['progress'] ?? 0);
                $pmName = $project['pm_name'] ?? 'Sin PM asignado';
                $portfolioName = $project['portfolio'] ?? 'Portafolio no asignado';
            ?>
            <article class="project-card">
                <header>
                    <div>
                        <p class="eyebrow" style="margin:0; color: var(--muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Proyecto</p>
                        <h3 class="project-title"><?= htmlspecialchars($project['name']) ?></h3>
                        <p class="project-meta"><?= htmlspecialchars($project['client'] ?? 'Cliente no registrado') ?></p>
                    </div>
                    <span class="pill <?= $statusPillClass((string) $project['status']) ?>" aria-label="Estado: <?= htmlspecialchars($statusLabel) ?>">
                        <span aria-hidden="true">●</span>
                        <?= htmlspecialchars($statusLabel) ?>
                    </span>
                </header>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="icon" aria-hidden="true">👥</div>
                        <div>
                            <span><?= htmlspecialchars($portfolioName) ?></span>
                            <small>Portafolio</small>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="icon" aria-hidden="true">🧭</div>
                        <div>
                            <span><?= htmlspecialchars($pmName) ?></span>
                            <small>PM a cargo</small>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="icon" aria-hidden="true">💡</div>
                        <div>
                            <span><?= htmlspecialchars($project['priority_label'] ?? $project['priority'] ?? 'Prioridad no registrada') ?></span>
                            <small>Prioridad</small>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="icon" aria-hidden="true">⚠️</div>
                        <div>
                            <span><?= htmlspecialchars($healthLabel) ?></span>
                            <small>Nivel de riesgo</small>
                        </div>
                    </div>
                </div>

                <div class="progress-row">
                    <div class="badge <?= $riskClass ?>">
                        <span aria-hidden="true">◎</span>
                        Riesgo: <?= htmlspecialchars($healthLabel) ?>
                    </div>
                    <strong><?= $progress ?>% avance</strong>
                </div>
                <div class="progress-track" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Avance del proyecto">
                    <div class="progress-bar" style="width: <?= max(0, min(100, $progress)) ?>%;"></div>
                </div>

                <div class="actions">
                    <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>">
                        <span aria-hidden="true">👁️</span>
                        Ver detalle
                    </a>
                    <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit">
                        <span aria-hidden="true">✏️</span>
                        Editar
                    </a>
                    <a class="action-btn" href="<?= $basePath ?>/projects/assign-talent">
                        <span aria-hidden="true">👤</span>
                        Gestionar talento
                    </a>
                    <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/costs">
                        <span aria-hidden="true">💵</span>
                        Costos
                    </a>
                    <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/close">
                        <span aria-hidden="true">🛑</span>
                        Cerrar proyecto
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
