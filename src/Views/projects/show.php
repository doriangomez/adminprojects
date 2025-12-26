<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$signal = $project['signal'] ?? ['label' => 'Verde', 'code' => 'green', 'reasons' => []];
$risks = is_array($project['risks'] ?? null) ? $project['risks'] : [];
?>

<section class="card" style="padding:16px; border:1px solid var(--border); border-radius:14px; background: var(--surface); display:flex; flex-direction:column; gap:12px;">
    <header style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <div>
            <p class="eyebrow" style="margin:0; color: var(--muted); font-weight:800; text-transform: uppercase; letter-spacing: 0.05em;">Proyecto</p>
            <h3 style="margin:4px 0; color: var(--text-strong);"><?= htmlspecialchars($project['name'] ?? '') ?></h3>
            <p style="margin:0; color: var(--muted); font-weight:600;">Cliente: <?= htmlspecialchars($project['client_name'] ?? '') ?></p>
        </div>
        <span class="pill soft-<?= htmlspecialchars($signal['code'] ?? 'green') ?>" aria-label="Señal">
            <span aria-hidden="true">●</span>
            <?= htmlspecialchars($signal['label'] ?? 'Verde') ?>
        </span>
    </header>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>PM</strong>
            <div><?= htmlspecialchars($project['pm_name'] ?? 'No asignado') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Estado</strong>
            <div><?= htmlspecialchars($project['status_label'] ?? $project['status'] ?? 'Pendiente') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Riesgo</strong>
            <div><?= htmlspecialchars($project['health_label'] ?? $project['health'] ?? 'Sin dato') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Prioridad</strong>
            <div><?= htmlspecialchars($project['priority_label'] ?? $project['priority'] ?? 'N/A') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Avance</strong>
            <div><?= (float) ($project['progress'] ?? 0) ?>%</div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Tipo</strong>
            <div><?= htmlspecialchars($project['project_type'] ?? 'convencional') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Metodología</strong>
            <div><?= htmlspecialchars($project['methodology'] ?? 'No definida') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Fase</strong>
            <div><?= htmlspecialchars($project['phase'] ?? 'Sin fase') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Riesgos</strong>
            <div><?= htmlspecialchars($risks ? implode(', ', $risks) : 'Sin riesgos asociados') ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Fechas</strong>
            <div><?= htmlspecialchars($project['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($project['end_date'] ?? 'N/A') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Presupuesto</strong>
            <div>$<?= number_format((float) ($project['budget'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Costo real</strong>
            <div>$<?= number_format((float) ($project['actual_cost'] ?? 0), 0, ',', '.') ?></div>
        </div>
    </div>
</section>

<section class="card" style="margin-top:16px; padding:16px; border:1px solid var(--border); border-radius:14px; background: var(--surface); display:flex; flex-direction:column; gap:12px;">
    <header style="display:flex; justify-content:space-between; align-items:center;">
        <h4 style="margin:0;">Talento asignado</h4>
        <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent">Gestionar talento</a>
    </header>
    <?php if (empty($assignments)): ?>
        <p style="margin:0; color: var(--muted);">Aún no hay asignaciones registradas.</p>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($assignments as $assignment): ?>
                <div style="display:flex; justify-content:space-between; border:1px solid var(--border); padding:10px; border-radius:10px; background:#f8fafc;">
                    <div>
                        <strong><?= htmlspecialchars($assignment['talent_name'] ?? 'Talento') ?></strong>
                        <div style="color:var(--muted); font-weight:600;"><?= htmlspecialchars($assignment['role'] ?? '') ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div><?= htmlspecialchars($assignment['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($assignment['end_date'] ?? 'N/A') ?></div>
                        <small style="color:var(--muted);">Horas semanales: <?= htmlspecialchars((string) ($assignment['weekly_hours'] ?? '0')) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
    <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit">Editar</a>
    <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/costs">Costos</a>
    <form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/close" method="GET" style="margin:0;">
        <button class="action-btn" type="submit">Cerrar proyecto</button>
    </form>
</section>
