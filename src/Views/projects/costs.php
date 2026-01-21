<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$budget = (float) ($project['budget'] ?? 0);
$actualCost = (float) ($project['actual_cost'] ?? 0);
$diff = $budget - $actualCost;
$diffLabel = $diff >= 0 ? 'A favor' : 'Sobrecosto';
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Costos</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Seguimiento financiero para control operativo.</small>
        </div>
        <div class="project-actions">
            <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?view=resumen">Volver al resumen</a>
        </div>
    </header>

    <?php
    $activeTab = 'costos';
    require __DIR__ . '/_tabs.php';
    ?>

    <section class="costs-grid">
        <article class="cost-card">
            <p class="section-label">Presupuesto</p>
            <strong>$<?= number_format($budget, 0, ',', '.') ?></strong>
            <span class="cost-hint">Total planificado</span>
        </article>
        <article class="cost-card">
            <p class="section-label">Costo real</p>
            <strong>$<?= number_format($actualCost, 0, ',', '.') ?></strong>
            <span class="cost-hint">Ejecutado a la fecha</span>
        </article>
        <article class="cost-card <?= $diff >= 0 ? 'is-positive' : 'is-negative' ?>">
            <p class="section-label"><?= $diffLabel ?></p>
            <strong>$<?= number_format($diff, 0, ',', '.') ?></strong>
            <span class="cost-hint">Diferencia vs presupuesto</span>
        </article>
    </section>

    <div class="info-box">
        <strong>Recomendación</strong>
        <p>Registra tiempos y gastos desde timesheets para mantener la trazabilidad financiera.</p>
    </div>
</section>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--card); }
    .project-title-block { display:flex; flex-direction:column; gap:6px; }
    .project-title-block h2 { margin:0; color: var(--text-strong); }
    .project-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .action-btn { background: var(--card); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }

    .costs-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    .cost-card { border:1px solid var(--border); padding:14px; border-radius:14px; background: var(--card); display:flex; flex-direction:column; gap:6px; }
    .cost-card strong { font-size:20px; color: var(--text-strong); }
    .cost-card.is-positive strong { color: var(--success); }
    .cost-card.is-negative strong { color: var(--danger); }
    .cost-hint { font-size:12px; color: var(--muted); }

    .info-box { border:1px solid var(--border); border-radius:12px; padding:12px; background: color-mix(in srgb, var(--primary) 10%, transparent); }
    .info-box p { margin:6px 0 0 0; color: var(--muted); }
</style>
