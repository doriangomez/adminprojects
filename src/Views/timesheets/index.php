<div class="grid">
    <div class="card kpi">
        <span class="label">Borrador</span>
        <span class="value"><?= $kpis['draft'] ?></span>
    </div>
    <div class="card kpi">
        <span class="label">Enviado</span>
        <span class="value"><?= $kpis['submitted'] ?></span>
    </div>
    <div class="card kpi">
        <span class="label">Aprobado</span>
        <span class="value"><?= $kpis['approved'] ?></span>
    </div>
</div>

<div class="card" style="margin-top: 12px;">
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Proyecto</th>
                <th>Tarea</th>
                <th>Talento</th>
                <th>Horas</th>
                <th>Estado</th>
                <th>Facturable</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td><?= htmlspecialchars($row['project']) ?></td>
                    <td><?= htmlspecialchars($row['task']) ?></td>
                    <td><?= htmlspecialchars($row['talent']) ?></td>
                    <td><?= $row['hours'] ?></td>
                    <td><span class="badge <?= $row['status'] === 'approved' ? 'success' : ($row['status'] === 'submitted' ? 'warning' : '') ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                    <td><?= $row['billable'] ? 'Sí' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
