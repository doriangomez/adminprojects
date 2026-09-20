<?php
$project = $project ?? [];
$assignedOrganizations = $assignedOrganizations ?? [];
$availableOrganizations = $availableOrganizations ?? [];
$canManageOrganizations = $canManageOrganizations ?? false;
$flash = $flash ?? null;
$basePath = $basePath ?? '';
$projectId = (int) ($project['id'] ?? 0);
$assignedIds = array_map(static fn ($row) => (int) ($row['organization_id'] ?? 0), $assignedOrganizations);
$activeTab = 'organizaciones';
require __DIR__ . '/_tabs.php';
$orgTypeLabels = [
    'cliente' => 'Cliente',
    'proveedor' => 'Proveedor',
    'aliado' => 'Aliado',
    'area_interna' => 'Área interna',
    'equipo' => 'Equipo',
];
?>
<section class="card">
    <div class="card-content catalog-page">
        <header class="catalog-header">
            <div>
                <p class="eyebrow">Proyecto</p>
                <h3>Organizaciones — <?= htmlspecialchars((string) ($project['name'] ?? '')) ?></h3>
                <p class="section-muted">Consulta y asignación de organizaciones vinculadas al proyecto.</p>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="alert success"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <?php if ($canManageOrganizations): ?>
            <form method="POST" action="<?= $basePath ?>/projects/<?= $projectId ?>/organizations/assign" class="assign-form">
                <label>
                    <span>Asignar organización activa</span>
                    <select name="organization_id" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($availableOrganizations as $org): ?>
                            <?php if (in_array((int) $org['id'], $assignedIds, true)) { continue; } ?>
                            <option value="<?= (int) $org['id'] ?>">
                                <?= htmlspecialchars((string) $org['name']) ?> (<?= htmlspecialchars($orgTypeLabels[$org['org_type'] ?? ''] ?? (string) ($org['org_type'] ?? '')) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button primary" type="submit">Asignar</button>
            </form>
        <?php endif; ?>

        <div class="catalog-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Organización</th>
                        <th>Tipo</th>
                        <th>Estado catálogo</th>
                        <?php if ($canManageOrganizations): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignedOrganizations)): ?>
                        <tr><td colspan="<?= $canManageOrganizations ? 4 : 3 ?>">Sin organizaciones asignadas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignedOrganizations as $org): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string) ($org['name'] ?? '')) ?></strong></td>
                                <td><?= htmlspecialchars($orgTypeLabels[$org['org_type'] ?? ''] ?? (string) ($org['org_type'] ?? '')) ?></td>
                                <td><?= ((int) ($org['active'] ?? 0) === 1) ? 'Activa' : 'Inactiva' ?></td>
                                <?php if ($canManageOrganizations): ?>
                                    <td>
                                        <form method="POST" action="<?= $basePath ?>/projects/<?= $projectId ?>/organizations/unassign" onsubmit="return confirm('¿Retirar esta organización del proyecto?');">
                                            <input type="hidden" name="organization_id" value="<?= (int) ($org['organization_id'] ?? 0) ?>">
                                            <button class="btn ghost" type="submit">Retirar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
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
    .catalog-header { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .assign-form { display:flex; gap:12px; flex-wrap:wrap; align-items:end; }
    .assign-form label { display:flex; flex-direction:column; gap:6px; min-width:min(100%, 320px); font-weight:600; color:var(--text-secondary); }
    .assign-form select { border:1px solid var(--border); border-radius:10px; padding:10px 12px; background:var(--surface); color:var(--text-primary); }
    .catalog-table-wrap { overflow-x:auto; }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th, .data-table td { padding:12px 10px; border-bottom:1px solid var(--border); text-align:left; }
    .alert.success { padding:10px 12px; border-radius:10px; background: color-mix(in srgb, var(--success) 16%, var(--surface)); border:1px solid color-mix(in srgb, var(--success) 35%, var(--border)); }
</style>
