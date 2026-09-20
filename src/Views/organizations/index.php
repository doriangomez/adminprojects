<?php
$organizations = $organizations ?? [];
$canManage = $canManage ?? false;
$flash = $flash ?? null;
$orgTypeLabels = [
    'cliente' => 'Cliente',
    'proveedor' => 'Proveedor',
    'aliado' => 'Aliado',
    'area_interna' => 'Área interna',
    'equipo' => 'Equipo',
];
$basePath = $basePath ?? '';
?>
<section class="card">
    <div class="card-content catalog-page">
        <header class="catalog-header">
            <div>
                <p class="eyebrow">Catálogo PMO</p>
                <h3>Organizaciones</h3>
                <p class="section-muted">Homónimos permitidos. Inactivación lógica; sin borrado físico.</p>
            </div>
            <?php if ($canManage): ?>
                <a class="button primary" href="<?= $basePath ?>/organizations/create">Nueva organización</a>
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
                        <th>Tipo</th>
                        <th>Razón social</th>
                        <th>Identificador</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($organizations)): ?>
                        <tr><td colspan="6">No hay organizaciones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($organizations as $org): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars((string) ($org['name'] ?? '')) ?></strong>
                                    <?php if (!empty($org['description'])): ?>
                                        <div class="section-muted"><?= htmlspecialchars((string) $org['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($orgTypeLabels[$org['org_type'] ?? ''] ?? (string) ($org['org_type'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($org['legal_name'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($org['tax_identifier'] ?? '—')) ?></td>
                                <td>
                                    <span class="badge <?= ((int) ($org['active'] ?? 0) === 1) ? 'success' : 'neutral' ?>">
                                        <?= ((int) ($org['active'] ?? 0) === 1) ? 'Activa' : 'Inactiva' ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <?php if ($canManage): ?>
                                        <a href="<?= $basePath ?>/organizations/<?= (int) $org['id'] ?>/edit">Editar</a>
                                        <?php if ((int) ($org['active'] ?? 0) === 1): ?>
                                            <form method="POST" action="<?= $basePath ?>/organizations/<?= (int) $org['id'] ?>/inactivate" onsubmit="return confirm('¿Inactivar esta organización?');">
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
