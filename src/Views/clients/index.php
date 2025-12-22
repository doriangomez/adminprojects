<div class="toolbar">
    <h3 style="margin:0;">Clientes</h3>
    <form class="inline" method="POST" action="/project/public/clients/create">
        <input type="text" name="name" placeholder="Nombre" required>
        <input type="text" name="industry" placeholder="Sector" required>
        <select name="priority">
            <option value="high">Alta</option>
            <option value="medium">Media</option>
            <option value="low">Baja</option>
        </select>
        <input type="number" name="satisfaction" placeholder="Satisfacción" min="0" max="100">
        <input type="number" name="nps" placeholder="NPS" min="-100" max="100">
        <button type="submit" class="btn primary">Nuevo</button>
    </form>
</div>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Sector</th>
                <th>Prioridad</th>
                <th>Satisfacción</th>
                <th>NPS</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($clients as $client): ?>
                <tr>
                    <td><?= htmlspecialchars($client['name']) ?></td>
                    <td><?= htmlspecialchars($client['industry']) ?></td>
                    <td><span class="pill <?= htmlspecialchars($client['priority']) ?>"><?= htmlspecialchars(ucfirst($client['priority'])) ?></span></td>
                    <td><?= $client['satisfaction'] ?? '-' ?></td>
                    <td><?= $client['nps'] ?? '-' ?></td>
                    <td>
                        <form class="inline" method="POST" action="/project/public/clients/delete" onsubmit="return confirm('Eliminar cliente?');">
                            <input type="hidden" name="id" value="<?= $client['id'] ?>">
                            <button type="submit" class="btn secondary">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
