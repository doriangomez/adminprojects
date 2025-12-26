<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
?>

<form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit" method="POST" style="display:flex; flex-direction:column; gap:12px; background: var(--surface); border:1px solid var(--border); padding:16px; border-radius:14px;">
    <h3 style="margin:0;">Editar proyecto</h3>
    <label>Nombre
        <input name="name" value="<?= htmlspecialchars($project['name'] ?? '') ?>" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Estado
        <input name="status" value="<?= htmlspecialchars($project['status'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Riesgo
        <input name="health" value="<?= htmlspecialchars($project['health'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Prioridad
        <input name="priority" value="<?= htmlspecialchars($project['priority'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>PM (ID)
        <input type="number" name="pm_id" value="<?= (int) ($project['pm_id'] ?? 0) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Tipo de proyecto
        <input name="project_type" value="<?= htmlspecialchars($project['project_type'] ?? 'convencional') ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Presupuesto
        <input type="number" step="0.01" name="budget" value="<?= htmlspecialchars((string) ($project['budget'] ?? 0)) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Costo real
        <input type="number" step="0.01" name="actual_cost" value="<?= htmlspecialchars((string) ($project['actual_cost'] ?? 0)) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Horas planificadas
        <input type="number" step="0.01" name="planned_hours" value="<?= htmlspecialchars((string) ($project['planned_hours'] ?? 0)) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Horas reales
        <input type="number" step="0.01" name="actual_hours" value="<?= htmlspecialchars((string) ($project['actual_hours'] ?? 0)) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Progreso (%)
        <input type="number" step="0.1" name="progress" value="<?= htmlspecialchars((string) ($project['progress'] ?? 0)) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
        <label>Inicio
            <input type="date" name="start_date" value="<?= htmlspecialchars((string) ($project['start_date'] ?? '')) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
        </label>
        <label>Fin
            <input type="date" name="end_date" value="<?= htmlspecialchars((string) ($project['end_date'] ?? '')) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
        </label>
    </div>
    <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Guardar cambios</button>
</form>
