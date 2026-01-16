<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$talents = is_array($talents ?? null) ? $talents : [];
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Talento</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Equipo asignado, roles y dedicaciones críticas.</small>
        </div>
        <div class="project-actions">
            <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?view=resumen">Volver al resumen</a>
            <a class="action-btn primary" href="#talent-management">Gestionar talento</a>
        </div>
    </header>

    <?php
    $activeTab = 'talento';
    require __DIR__ . '/_tabs.php';
    ?>

    <section class="talent-overview">
        <div>
            <p class="eyebrow">Talentos asignados</p>
            <h4>Roles y dedicación</h4>
        </div>
        <?php if (empty($assignments)): ?>
            <p class="section-muted">Sin asignaciones actuales.</p>
        <?php else: ?>
            <div class="talent-table">
                <div class="talent-row talent-head">
                    <span>Talento</span>
                    <span>Rol</span>
                    <span>Dedicación</span>
                    <span>Reporte</span>
                    <span>Aprobación</span>
                </div>
                <?php foreach ($assignments as $assignment): ?>
                    <div class="talent-row">
                        <div>
                            <strong><?= htmlspecialchars($assignment['talent_name'] ?? 'Talento') ?></strong>
                            <small class="section-muted"><?= htmlspecialchars($assignment['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($assignment['end_date'] ?? 'N/A') ?></small>
                        </div>
                        <span><?= htmlspecialchars($assignment['role'] ?? '') ?></span>
                        <span><?= htmlspecialchars((string) ($assignment['allocation_percent'] ?? 0)) ?>% · <?= htmlspecialchars((string) ($assignment['weekly_hours'] ?? 0)) ?>h/sem</span>
                        <span class="badge <?= !empty($assignment['requiere_reporte_horas']) ? 'status-success' : 'status-muted' ?>">
                            <?= !empty($assignment['requiere_reporte_horas']) ? 'Sí' : 'No' ?>
                        </span>
                        <span class="badge <?= !empty($assignment['requiere_aprobacion_horas']) ? 'status-warning' : 'status-muted' ?>">
                            <?= !empty($assignment['requiere_aprobacion_horas']) ? 'Sí' : 'No' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent" method="POST" class="talent-form" id="talent-management">
        <div>
            <p class="eyebrow">Nueva asignación</p>
            <h4>Gestionar talento</h4>
        </div>
        <div class="grid">
            <label>Talento
                <select name="talent_id" required>
                    <option value="">Selecciona un talento</option>
                    <?php foreach ($talents as $talent): ?>
                        <option value="<?= (int) $talent['id'] ?>"><?= htmlspecialchars($talent['name'] ?? '') ?> (<?= htmlspecialchars($talent['role'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Rol en el proyecto
                <input name="role" required placeholder="Ej. Líder técnico">
            </label>
        </div>
        <div class="grid">
            <label>Inicio
                <input type="date" name="start_date">
            </label>
            <label>Fin
                <input type="date" name="end_date">
            </label>
        </div>
        <div class="grid">
            <label>Porcentaje de dedicación (%)
                <input type="number" step="0.1" name="allocation_percent" placeholder="Ej. 50">
            </label>
            <label>Horas semanales
                <input type="number" step="0.1" name="weekly_hours" placeholder="Ej. 20">
            </label>
        </div>
        <div class="grid">
            <label>Tipo de costo
                <select name="cost_type">
                    <option value="por_horas">Por horas</option>
                    <option value="fijo">Fijo</option>
                </select>
            </label>
            <label>Estado
                <select name="assignment_status">
                    <option value="active">Activo</option>
                    <option value="paused">En pausa</option>
                    <option value="removed">Retirado</option>
                </select>
            </label>
            <label>Valor
                <input type="number" step="0.01" name="cost_value" placeholder="0">
            </label>
        </div>
        <div class="checkbox-grid">
            <label><input type="checkbox" name="is_external" value="1"> Es externo</label>
            <span class="section-muted">El reporte y la aprobación de horas se definen en la ficha del talento.</span>
        </div>
        <button type="submit" class="action-btn primary">Guardar asignación</button>
    </form>
</section>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; border:1px solid var(--border); padding:16px; border-radius:16px; background: var(--surface); }
    .project-title-block { display:flex; flex-direction:column; gap:6px; }
    .project-title-block h2 { margin:0; color: var(--text-strong); }
    .project-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .talent-overview, .talent-form { border:1px solid var(--border); padding:16px; border-radius:16px; background:#fff; display:flex; flex-direction:column; gap:12px; }
    .talent-table { display:grid; gap:8px; }
    .talent-row { display:grid; grid-template-columns: minmax(160px, 1.4fr) minmax(120px, 1fr) minmax(120px, 1fr) minmax(90px, 0.6fr) minmax(90px, 0.6fr); gap:10px; align-items:center; border:1px solid var(--border); border-radius:12px; padding:10px; background:#f8fafc; }
    .talent-head { background:#f1f5f9; font-weight:700; font-size:12px; text-transform:uppercase; color: var(--muted); }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px; }
    .checkbox-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:8px; }
    .badge { display:inline-flex; align-items:center; justify-content:center; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid transparent; }
    .status-muted { background:#f3f4f6; color:#374151; border-color:#e5e7eb; }
    .status-success { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
    .status-warning { background:#fef9c3; color:#854d0e; border-color:#fde047; }
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color:#fff; border-color: var(--primary); }
    @media (max-width: 900px) {
        .talent-row { grid-template-columns: 1fr; }
        .talent-head { display:none; }
    }
</style>
