<?php
$project = $project ?? [];
$assignedTechnologies = $assignedTechnologies ?? [];
$availableTechnologies = $availableTechnologies ?? [];
$canManageTechnologies = $canManageTechnologies ?? false;
$flash = $flash ?? null;
$basePath = $basePath ?? '';
$projectId = (int) ($project['id'] ?? 0);
$assignedIds = array_map(static fn ($row) => (int) ($row['technology_id'] ?? 0), $assignedTechnologies);
$activeTab = 'tecnologias';
require __DIR__ . '/_tabs.php';
?>
<section class="card">
    <div class="card-content catalog-page">
        <header class="catalog-header">
            <div>
                <p class="eyebrow">Proyecto</p>
                <h3>Tecnologías — <?= htmlspecialchars((string) ($project['name'] ?? '')) ?></h3>
                <p class="section-muted">Versión y notas se definen por proyecto, no en el catálogo.</p>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="alert success"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <?php if ($canManageTechnologies): ?>
            <form method="POST" action="<?= $basePath ?>/projects/<?= $projectId ?>/technologies/assign" class="assign-form">
                <label>
                    <span>Tecnología</span>
                    <select name="technology_id" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($availableTechnologies as $tech): ?>
                            <?php if (in_array((int) $tech['id'], $assignedIds, true)) { continue; } ?>
                            <option value="<?= (int) $tech['id'] ?>">
                                <?= htmlspecialchars((string) $tech['name']) ?> — <?= htmlspecialchars((string) $tech['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Versión</span>
                    <input name="version" placeholder="Ej. 8.2">
                </label>
                <label class="grow">
                    <span>Notas</span>
                    <input name="notes" placeholder="Opcional">
                </label>
                <button class="button primary" type="submit">Asignar</button>
            </form>
        <?php endif; ?>

        <div class="catalog-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tecnología</th>
                        <th>Categoría</th>
                        <th>Versión</th>
                        <th>Notas</th>
                        <?php if ($canManageTechnologies): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignedTechnologies)): ?>
                        <tr><td colspan="<?= $canManageTechnologies ? 5 : 4 ?>">Sin tecnologías asignadas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignedTechnologies as $tech): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string) ($tech['name'] ?? '')) ?></strong></td>
                                <td><?= htmlspecialchars((string) ($tech['category'] ?? '')) ?></td>
                                <td>
                                    <?php if ($canManageTechnologies): ?>
                                        <form method="POST" action="<?= $basePath ?>/projects/<?= $projectId ?>/technologies/update" class="inline-update">
                                            <input type="hidden" name="technology_id" value="<?= (int) ($tech['technology_id'] ?? 0) ?>">
                                            <input name="version" value="<?= htmlspecialchars((string) ($tech['version'] ?? '')) ?>" placeholder="Versión">
                                            <input name="notes" value="<?= htmlspecialchars((string) ($tech['notes'] ?? '')) ?>" placeholder="Notas">
                                            <button class="btn secondary" type="submit">Actualizar</button>
                                        </form>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string) ($tech['version'] ?? '—')) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $canManageTechnologies ? '' : htmlspecialchars((string) ($tech['notes'] ?? '—')) ?></td>
                                <?php if ($canManageTechnologies): ?>
                                    <td>
                                        <form method="POST" action="<?= $basePath ?>/projects/<?= $projectId ?>/technologies/unassign" onsubmit="return confirm('¿Retirar esta tecnología del proyecto?');">
                                            <input type="hidden" name="technology_id" value="<?= (int) ($tech['technology_id'] ?? 0) ?>">
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
    .assign-form label { display:flex; flex-direction:column; gap:6px; min-width:160px; font-weight:600; color:var(--text-secondary); }
    .assign-form label.grow { flex:1; min-width:min(100%, 220px); }
    .assign-form select, .assign-form input, .inline-update input {
        border:1px solid var(--border); border-radius:10px; padding:10px 12px; background:var(--surface); color:var(--text-primary);
    }
    .inline-update { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .catalog-table-wrap { overflow-x:auto; }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th, .data-table td { padding:12px 10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .alert.success { padding:10px 12px; border-radius:10px; background: color-mix(in srgb, var(--success) 16%, var(--surface)); border:1px solid color-mix(in srgb, var(--success) 35%, var(--border)); }
    @media (max-width: 720px) {
        .assign-form, .inline-update { flex-direction:column; align-items:stretch; }
    }
</style>
