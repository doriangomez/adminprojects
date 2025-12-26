<?php
$project = $project ?? [];
?>

<section style="display:flex; flex-direction:column; gap:12px; background: var(--surface); border:1px solid var(--border); padding:16px; border-radius:14px;">
    <header style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <p class="eyebrow" style="margin:0; color: var(--muted); font-weight:800; text-transform: uppercase; letter-spacing: 0.05em;">Costos</p>
            <h3 style="margin:4px 0; color: var(--text-strong);"><?= htmlspecialchars($project['name'] ?? '') ?></h3>
        </div>
    </header>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:10px;">
        <div class="info-item" style="border:1px solid var(--border); padding:12px; border-radius:12px; background:#f8fafc;">
            <strong>Presupuesto</strong>
            <div style="font-size:20px; font-weight:800;">$<?= number_format((float) ($project['budget'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:12px; border-radius:12px; background:#f8fafc;">
            <strong>Costo real</strong>
            <div style="font-size:20px; font-weight:800;">$<?= number_format((float) ($project['actual_cost'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:12px; border-radius:12px; background:#f8fafc;">
            <strong>Diferencia</strong>
            <?php $diff = (float) ($project['budget'] ?? 0) - (float) ($project['actual_cost'] ?? 0); ?>
            <div style="font-size:20px; font-weight:800; color: <?= $diff >= 0 ? '#15803d' : '#b91c1c' ?>;">
                $<?= number_format($diff, 0, ',', '.') ?>
            </div>
        </div>
    </div>
    <p style="margin:0; color: var(--muted);">Registra tiempos y gastos desde timesheets para mantener la trazabilidad financiera.</p>
</section>
