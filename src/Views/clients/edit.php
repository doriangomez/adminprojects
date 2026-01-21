<div class="toolbar">
    <div>
        <a href="/project/public/clients/<?= (int) $client['id'] ?>" class="btn ghost">← Volver</a>
        <h3 style="margin:8px 0 0 0;">Editar cliente</h3>
        <p style="margin:4px 0 0 0; color: var(--text-secondary);">Actualiza la ficha sin mezclarla con el listado ni el detalle.</p>
    </div>
</div>

<div class="card">
    <?php if (!empty($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px;" method="POST" action="/project/public/clients/<?= (int) $client['id'] ?>/edit" enctype="multipart/form-data">
        <input type="hidden" name="current_logo" value="<?= htmlspecialchars($client['logo_path'] ?? '') ?>">
        <label class="input">
            <span>Nombre</span>
            <input type="text" name="name" value="<?= htmlspecialchars($client['name']) ?>" required>
        </label>
        <label class="input">
            <span>Sector</span>
            <select name="sector_code" required>
                <?php foreach($sectors as $sector): ?>
                    <option value="<?= htmlspecialchars($sector['code']) ?>" <?= $sector['code'] === $client['sector_code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sector['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Categoría</span>
            <select name="category_code" required>
                <?php foreach($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category['code']) ?>" <?= $category['code'] === $client['category_code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Prioridad</span>
            <select name="priority_code" required>
                <?php foreach($priorities as $priority): ?>
                    <option value="<?= htmlspecialchars($priority['code']) ?>" <?= $priority['code'] === $client['priority'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($priority['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Estado</span>
            <select name="status_code" required>
                <?php foreach($statuses as $status): ?>
                    <option value="<?= htmlspecialchars($status['code']) ?>" <?= $status['code'] === $client['status_code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($status['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>PM a cargo</span>
            <select name="pm_id" required>
                <?php foreach($projectManagers as $pm): ?>
                    <option value="<?= (int) $pm['id'] ?>" <?= (int) $pm['id'] === (int) $client['pm_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pm['name']) ?> (<?= htmlspecialchars($pm['role_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Logo del cliente</span>
            <?php if(!empty($client['logo_path'])): ?>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <img src="<?= $basePath . $client['logo_path'] ?>" alt="Logo actual" style="width:64px; height:64px; object-fit:contain; border:1px solid var(--border); border-radius:12px; background:var(--surface);">
                    <small style="color: var(--text-secondary);">Logo actual</small>
                </div>
            <?php endif; ?>
            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg">
            <small style="color: var(--text-secondary);">Sube PNG, JPG o SVG para reemplazar el logo.</small>
        </label>
        <label class="input">
            <span>Riesgo de la relación</span>
            <select name="risk_code">
                <option value="">Selecciona riesgo</option>
                <?php foreach($risks as $risk): ?>
                    <option value="<?= htmlspecialchars($risk['code']) ?>" <?= ($risk['code'] ?? '') === ($client['risk_code'] ?? '') ? 'selected' : '' ?>>
                        <?= htmlspecialchars($risk['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Etiquetas</span>
            <input type="text" name="tags" value="<?= htmlspecialchars($client['tags'] ?? '') ?>">
        </label>
        <label class="input">
            <span>Área</span>
            <select name="area_code">
                <option value="">Selecciona área</option>
                <?php foreach($areas as $area): ?>
                    <option value="<?= htmlspecialchars($area['code']) ?>" <?= ($area['code'] ?? '') === ($client['area_code'] ?? '') ? 'selected' : '' ?>>
                        <?= htmlspecialchars($area['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Satisfacción</span>
            <input type="number" name="satisfaction" min="0" max="100" value="<?= htmlspecialchars((string) ($client['satisfaction'] ?? '')) ?>">
        </label>
        <label class="input">
            <span>NPS</span>
            <input type="number" name="nps" min="-100" max="100" value="<?= htmlspecialchars((string) ($client['nps'] ?? '')) ?>">
        </label>
        <label class="input" style="grid-column:1 / -1;">
            <span>Feedback (observaciones)</span>
            <textarea name="feedback_notes" rows="2"><?= htmlspecialchars($client['feedback_notes'] ?? '') ?></textarea>
        </label>
        <label class="input" style="grid-column:1 / -1;">
            <span>Historial</span>
            <textarea name="feedback_history" rows="2"><?= htmlspecialchars($client['feedback_history'] ?? '') ?></textarea>
        </label>
        <label class="input" style="grid-column:1 / -1;">
            <span>Contexto operativo (sin detalles contractuales)</span>
            <textarea name="operational_context" rows="2"><?= htmlspecialchars($client['operational_context'] ?? '') ?></textarea>
        </label>
        <div style="grid-column:1 / -1; display:flex; justify-content:flex-end; gap:8px;">
            <a class="btn ghost" href="/project/public/clients/<?= (int) $client['id'] ?>">Cancelar</a>
            <button type="submit" class="btn primary">Guardar cambios</button>
        </div>
    </form>
</div>
