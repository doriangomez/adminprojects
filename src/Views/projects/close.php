<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$isClosed = in_array(strtolower(trim((string) ($project['status'] ?? ''))), ['closed', 'cerrado', 'finalizado', 'finalized'], true);
?>

<section style="display:flex; flex-direction:column; gap:12px; background: var(--surface); border:1px solid var(--border); padding:16px; border-radius:14px;">
    <h3 style="margin:0;">Cerrar proyecto</h3>
    <p style="margin:0; color: var(--text-secondary);">
        <?php if ($isClosed): ?>
            <strong><?= htmlspecialchars($project['name'] ?? '') ?></strong> ya está cerrado.
        <?php else: ?>
            Confirmarás el cierre de <strong><?= htmlspecialchars($project['name'] ?? '') ?></strong>. El proyecto no se eliminará: se conservarán sus tareas, horas, documentos, costos, asignaciones y trazabilidad. El avance permanecerá según la última actualización manual.
        <?php endif; ?>
    </p>

    <form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/close" method="POST" style="display:flex; gap:10px; align-items:center;">
        <?php if (!$isClosed): ?>
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Confirmar cierre</button>
        <?php endif; ?>
        <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>">Cancelar</a>
    </form>
</section>
