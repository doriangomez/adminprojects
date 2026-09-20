<?php
$organization = $organization ?? [];
$orgTypes = $orgTypes ?? [];
$mode = $mode ?? 'create';
$error = $error ?? null;
$basePath = $basePath ?? '';
$action = $mode === 'edit'
    ? $basePath . '/organizations/' . (int) ($organization['id'] ?? 0) . '/edit'
    : $basePath . '/organizations/create';
$orgTypeLabels = [
    'cliente' => 'Cliente',
    'proveedor' => 'Proveedor',
    'aliado' => 'Aliado',
    'area_interna' => 'Área interna',
    'equipo' => 'Equipo',
];
$isActive = array_key_exists('active', $organization)
    ? (int) $organization['active'] === 1
    : true;
?>
<section class="card">
    <div class="card-content">
        <header class="catalog-header">
            <div>
                <p class="eyebrow">Catálogo PMO</p>
                <h3><?= $mode === 'edit' ? 'Editar organización' : 'Nueva organización' ?></h3>
            </div>
            <a class="button secondary" href="<?= $basePath ?>/organizations">Volver</a>
        </header>

        <?php if ($error): ?>
            <div class="alert danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($action) ?>" class="catalog-form">
            <div class="form-grid">
                <label>
                    <span>Nombre *</span>
                    <input name="name" required value="<?= htmlspecialchars((string) ($organization['name'] ?? '')) ?>">
                </label>
                <label>
                    <span>Tipo *</span>
                    <select name="org_type" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($orgTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= (($organization['org_type'] ?? '') === $type) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($orgTypeLabels[$type] ?? $type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Razón social</span>
                    <input name="legal_name" value="<?= htmlspecialchars((string) ($organization['legal_name'] ?? '')) ?>">
                </label>
                <label>
                    <span>Identificador fiscal</span>
                    <input name="tax_identifier" value="<?= htmlspecialchars((string) ($organization['tax_identifier'] ?? '')) ?>">
                </label>
                <label class="full">
                    <span>Descripción</span>
                    <textarea name="description" rows="3"><?= htmlspecialchars((string) ($organization['description'] ?? '')) ?></textarea>
                </label>
                <label class="checkbox-row">
                    <input type="checkbox" name="active" value="1" <?= $isActive ? 'checked' : '' ?>>
                    <span>Activa</span>
                </label>
            </div>
            <div class="form-footer">
                <button class="button primary" type="submit"><?= $mode === 'edit' ? 'Guardar cambios' : 'Crear' ?></button>
            </div>
        </form>
    </div>
</section>
<style>
    .catalog-header { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
    .catalog-form .form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:14px; }
    .catalog-form label { display:flex; flex-direction:column; gap:6px; font-weight:600; color:var(--text-secondary); }
    .catalog-form input, .catalog-form select, .catalog-form textarea {
        border:1px solid var(--border); border-radius:10px; padding:10px 12px; background:var(--surface); color:var(--text-primary);
    }
    .catalog-form .full { grid-column:1 / -1; }
    .catalog-form .checkbox-row { flex-direction:row; align-items:center; gap:8px; }
    .form-footer { margin-top:16px; }
    .alert.danger { padding:10px 12px; border-radius:10px; background: color-mix(in srgb, var(--danger) 14%, var(--surface)); border:1px solid color-mix(in srgb, var(--danger) 35%, var(--border)); margin-bottom:12px; }
    @media (max-width: 720px) {
        .catalog-form .form-grid { grid-template-columns:1fr; }
    }
</style>
