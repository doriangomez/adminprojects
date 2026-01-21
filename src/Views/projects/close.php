<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
?>

<section style="display:flex; flex-direction:column; gap:12px; background: var(--surface); border:1px solid var(--border); padding:16px; border-radius:14px;">
    <h3 style="margin:0;">Cerrar proyecto</h3>
    <p style="margin:0; color: var(--text-secondary);">Confirmarás el cierre de <strong><?= htmlspecialchars($project['name'] ?? '') ?></strong>. Se marcará como cerrado y el avance permanecerá según la última actualización manual.</p>

    <form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/close" method="POST" style="display:flex; gap:10px; align-items:center;">
        <input type="hidden" name="confirm" value="yes">
        <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Confirmar cierre</button>
        <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>">Cancelar</a>
    </form>
</section>
