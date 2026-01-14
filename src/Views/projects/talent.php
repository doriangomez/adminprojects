<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$talents = is_array($talents ?? null) ? $talents : [];
?>

<section style="display:flex; flex-direction:column; gap:12px; background: var(--surface); border:1px solid var(--border); padding:16px; border-radius:14px;">
    <header style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <div>
            <p class="eyebrow" style="margin:0; color: var(--muted); font-weight:800; text-transform: uppercase; letter-spacing: 0.05em;">Talento</p>
            <h3 style="margin:4px 0; color: var(--text-strong);">Asignaciones para <?= htmlspecialchars($project['name'] ?? '') ?></h3>
        </div>
        <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>">Volver al detalle</a>
    </header>

    <?php if (empty($assignments)): ?>
        <p style="margin:0; color: var(--muted);">Sin asignaciones actuales.</p>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($assignments as $assignment): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border); padding:10px; border-radius:10px; background:#f8fafc;">
                    <div>
                        <strong><?= htmlspecialchars($assignment['talent_name'] ?? 'Talento') ?></strong>
                        <div style="color:var(--muted); font-weight:600;"><?= htmlspecialchars($assignment['role'] ?? '') ?></div>
                        <small style="color:var(--muted);">Estado: <?= htmlspecialchars((string) ($assignment['assignment_status'] ?? 'active')) ?></small>
                    </div>
                    <div style="text-align:right;">
                        <div><?= htmlspecialchars($assignment['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($assignment['end_date'] ?? 'N/A') ?></div>
                        <small style="color:var(--muted);">Asignación: <?= htmlspecialchars((string) ($assignment['allocation_percent'] ?? 0)) ?>% | Horas: <?= htmlspecialchars((string) ($assignment['weekly_hours'] ?? 0)) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent" method="POST" style="margin-top:16px; display:flex; flex-direction:column; gap:12px; background: var(--surface); border:1px solid var(--border); padding:16px; border-radius:14px;">
    <h4 style="margin:0;">Nueva asignación</h4>
    <label>Talento
        <select name="talent_id" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
            <option value="">Selecciona un talento</option>
            <?php foreach ($talents as $talent): ?>
                <option value="<?= (int) $talent['id'] ?>"><?= htmlspecialchars($talent['name'] ?? '') ?> (<?= htmlspecialchars($talent['role'] ?? '') ?>)</option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Rol en el proyecto
        <input name="role" required placeholder="Ej. Líder técnico" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
        <label>Inicio
            <input type="date" name="start_date" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
        </label>
        <label>Fin
            <input type="date" name="end_date" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
        </label>
    </div>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
        <label>Porcentaje de dedicación (%)
            <input type="number" step="0.1" name="allocation_percent" placeholder="Ej. 50" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
        </label>
        <label>Horas semanales
            <input type="number" step="0.1" name="weekly_hours" placeholder="Ej. 20" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
        </label>
    </div>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
        <label>Tipo de costo
            <select name="cost_type" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                <option value="por_horas">Por horas</option>
                <option value="fijo">Fijo</option>
            </select>
        </label>
        <label>Estado
            <select name="assignment_status" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                <option value="active">Activo</option>
                <option value="paused">En pausa</option>
                <option value="removed">Retirado</option>
            </select>
        </label>
        <label>Valor
            <input type="number" step="0.01" name="cost_value" placeholder="0" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
        </label>
    </div>
    <label style="display:flex; gap:8px; align-items:center;">
        <input type="checkbox" name="is_external" value="1"> Es externo
    </label>
    <label style="display:flex; gap:8px; align-items:center;">
        <input type="checkbox" name="requires_timesheet" value="1"> Requiere timesheet
    </label>
    <label style="display:flex; gap:8px; align-items:center;">
        <input type="checkbox" name="requires_approval" value="1"> Requiere aprobación
    </label>
    <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Guardar asignación</button>
</form>
