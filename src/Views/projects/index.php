<div class="card">
    <table>
        <thead>
            <tr>
                <th>Proyecto</th>
                <th>Cliente</th>
                <th>Estado</th>
                <th>Salud</th>
                <th>Prioridad</th>
                <th>Avance</th>
                <th>Presupuesto</th>
                <th>Costo real</th>
                <th>Horas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($projects as $project): ?>
                <tr>
                    <td><?= htmlspecialchars($project['name']) ?></td>
                    <td><?= htmlspecialchars($project['client']) ?></td>
                    <td><?= htmlspecialchars($project['status']) ?></td>
                    <td><span class="badge <?= $project['health'] === 'on_track' ? 'success' : ($project['health'] === 'at_risk' ? 'warning' : 'danger') ?>"><?= htmlspecialchars($project['health']) ?></span></td>
                    <td><span class="pill <?= htmlspecialchars($project['priority']) ?>"><?= htmlspecialchars($project['priority']) ?></span></td>
                    <td><?= $project['progress'] ?>%</td>
                    <td>$<?= number_format($project['budget'], 0, ',', '.') ?></td>
                    <td>$<?= number_format($project['actual_cost'], 0, ',', '.') ?></td>
                    <td><?= $project['actual_hours'] ?>/<?= $project['planned_hours'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
