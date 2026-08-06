<?php
$technologies = $technologies ?? [];
$canManage = $canManage ?? false;
$flash = $flash ?? null;
$basePath = $basePath ?? '';
?>
<section class="card">
    <div class="card-content catalog-page">
        <header class="catalog-header">
            <div>
                <p class="eyebrow">Catálogo PMO</p>
                <h3>Tecnologías</h3>
                <p class="section-muted">La versión se define por proyecto. Duplicados bloqueados por nombre+categoría.</p>
            </div>
            <?php if ($canManage): ?>
                <a class="button primary" href="<?= $basePath ?>/technologies/create">Nueva tecnología</a>
            <?php endif; ?>
        </header>

        <?php if ($flash): ?>
            <div class="alert success"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <div class="catalog-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($technologies)): ?>
                        <tr><td colspan="5">No hay tecnologías registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($technologies as $tech): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string) ($tech['name'] ?? '')) ?></strong></td>
                                <td><?= htmlspecialchars((string) ($tech['category'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($tech['description'] ?? '—')) ?></td>
                                <td>
                                    <span class="badge <?= ((int) ($tech['active'] ?? 0) === 1) ? 'success' : 'neutral' ?>">
                                        <?= ((int) ($tech['active'] ?? 0) === 1) ? 'Activa' : 'Inactiva' ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <?php if ($canManage): ?>
                                        <a href="<?= $basePath ?>/technologies/<?= (int) $tech['id'] ?>/edit">Editar</a>
                                        <?php if ((int) ($tech['active'] ?? 0) === 1): ?>
                                            <form method="POST" action="<?= $basePath ?>/technologies/<?= (int) $tech['id'] ?>/inactivate" onsubmit="return confirm('¿Inactivar esta tecnología?');">
                                                <button class="btn ghost" type="submit">Inactivar</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<style>
    .catalog-page { display:flex; flex-direction:column; gap:16px; }
    .catalog-header { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start; }
    .catalog-table-wrap { overflow-x:auto; }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th, .data-table td { padding:12px 10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .data-table .actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .alert.success { padding:10px 12px; border-radius:10px; background: color-mix(in srgb, var(--success) 16%, var(--surface)); border:1px solid color-mix(in srgb, var(--success) 35%, var(--border)); }
    @media (max-width: 720px) {
        .catalog-header { flex-direction:column; }
    }
</style>
