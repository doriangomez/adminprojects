<div class="toolbar">
    <div>
        <a href="/project/public/clients" class="btn ghost">← Volver</a>
        <h3 style="margin:8px 0 0 0;">Registrar cliente</h3>
        <p style="margin:4px 0 0 0; color: var(--muted);">Ficha dedicada para registrar nuevos clientes sin mezclar con el listado.</p>
    </div>
</div>

<div class="card">
    <?php if (!empty($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px;" method="POST" action="/project/public/clients/create" enctype="multipart/form-data">
        <label class="input">
            <span>Nombre</span>
            <input type="text" name="name" placeholder="Nombre del cliente" required>
        </label>
        <label class="input">
            <span>Sector</span>
            <select name="sector_code" required>
                <option value="">Selecciona sector</option>
                <?php foreach($sectors as $sector): ?>
                    <option value="<?= htmlspecialchars($sector['code']) ?>"><?= htmlspecialchars($sector['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Categoría</span>
            <select name="category_code" required>
                <option value="">Selecciona categoría</option>
                <?php foreach($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category['code']) ?>"><?= htmlspecialchars($category['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Prioridad</span>
            <select name="priority" required>
                <?php foreach($priorities as $priority): ?>
                    <option value="<?= htmlspecialchars($priority['code']) ?>"><?= htmlspecialchars($priority['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Estado</span>
            <select name="status_code" required>
                <?php foreach($statuses as $status): ?>
                    <option value="<?= htmlspecialchars($status['code']) ?>"><?= htmlspecialchars($status['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>PM a cargo</span>
            <select name="pm_id" required>
                <?php foreach($projectManagers as $pm): ?>
                    <option value="<?= (int) $pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?> (<?= htmlspecialchars($pm['role_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Logo del cliente</span>
            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg">
            <small style="color: var(--muted);">Formatos: PNG, JPG o SVG. Se almacenará en uploads/clients/.</small>
        </label>
        <label class="input">
            <span>Riesgo de la relación</span>
            <select name="risk_level">
                <option value="">Selecciona riesgo</option>
                <?php foreach($risks as $risk): ?>
                    <option value="<?= htmlspecialchars($risk['code']) ?>"><?= htmlspecialchars($risk['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Etiquetas</span>
            <input type="text" name="tags" placeholder="separar por coma">
        </label>
        <label class="input">
            <span>Área</span>
            <select name="area">
                <option value="">Selecciona área</option>
                <?php foreach($areas as $area): ?>
                    <option value="<?= htmlspecialchars($area['code']) ?>"><?= htmlspecialchars($area['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="input">
            <span>Satisfacción</span>
            <input type="number" name="satisfaction" min="0" max="100" placeholder="0-100">
        </label>
        <label class="input">
            <span>NPS</span>
            <input type="number" name="nps" min="-100" max="100" placeholder="-100 a 100">
        </label>
        <label class="input" style="grid-column:1 / -1;">
            <span>Feedback (observaciones)</span>
            <textarea name="feedback_notes" rows="2" placeholder="Notas recientes de la relación"></textarea>
        </label>
        <label class="input" style="grid-column:1 / -1;">
            <span>Historial de feedback</span>
            <textarea name="feedback_history" rows="2" placeholder="Eventos clave, reuniones, aprendizajes"></textarea>
        </label>
        <label class="input" style="grid-column:1 / -1;">
            <span>Contexto operativo (sin detalles contractuales)</span>
            <textarea name="operational_context" rows="2" placeholder="Procesos, dinámicas de trabajo, dependencias"></textarea>
        </label>
        <div style="grid-column:1 / -1; display:flex; justify-content:flex-end; gap:8px;">
            <a class="btn ghost" href="/project/public/clients">Cancelar</a>
            <button type="submit" class="btn primary">Crear cliente</button>
        </div>
    </form>
</div>
