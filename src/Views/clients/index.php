<div class="toolbar">
    <div>
        <h3 style="margin:0;">Clientes</h3>
        <p style="margin:4px 0 0 0; color: var(--muted);">El cliente gobierna la relación estratégica; los contratos viven en cada proyecto.</p>
    </div>
</div>

<?php if($canManage): ?>
    <div class="card">
        <h4 style="margin-top:0;">Registrar cliente</h4>
        <form class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px;" method="POST" action="/project/public/clients/create">
            <input type="text" name="name" placeholder="Nombre del cliente" required>
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
                <span>Riesgo de la relación</span>
                <input type="text" name="risk_level" placeholder="Ej: moderado, alto">
            </label>
            <label class="input">
                <span>Etiquetas</span>
                <input type="text" name="tags" placeholder="separar por coma">
            </label>
            <label class="input">
                <span>Área</span>
                <input type="text" name="area" placeholder="Unidad / dominio de negocio">
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
            <div style="grid-column:1 / -1; text-align:right;">
                <button type="submit" class="btn primary">Crear cliente</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="card">
    <div class="toolbar" style="gap:12px;">
        <div>
            <p class="badge neutral" style="margin:0;">Relaciones activas</p>
            <h4 style="margin:4px 0 0 0;">Resumen de clientes</h4>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Sector</th>
                <th>Categoría</th>
                <th>Prioridad</th>
                <th>PM a cargo</th>
                <th>Estado</th>
                <th>Satisfacción</th>
                <th>NPS</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($clients as $client): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($client['name']) ?></strong><br>
                        <small style="color: var(--muted);">Área: <?= htmlspecialchars($client['area'] ?? 'No definida') ?></small>
                    </td>
                    <td><?= htmlspecialchars($client['sector_label'] ?? $client['sector_code'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($client['category_label'] ?? $client['category_code'] ?? '-') ?></td>
                    <td><span class="pill <?= htmlspecialchars($client['priority']) ?>"><?= htmlspecialchars($client['priority_label'] ?? ucfirst($client['priority'])) ?></span></td>
                    <td><?= htmlspecialchars($client['pm_name'] ?? 'Sin asignar') ?></td>
                    <td><span class="badge neutral"><?= htmlspecialchars($client['status_label'] ?? $client['status_code'] ?? '-') ?></span></td>
                    <td><?= $client['satisfaction'] !== null ? (int) $client['satisfaction'] : '-' ?></td>
                    <td><?= $client['nps'] !== null ? (int) $client['nps'] : '-' ?></td>
                    <td style="text-align:right;">
                        <a class="btn secondary" href="/project/public/clients/<?= (int) $client['id'] ?>">Ver detalle</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
