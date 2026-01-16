<div class="grid">
    <div class="card kpi">
        <span class="label">Borrador</span>
        <span class="value"><?= $kpis['draft'] ?? 0 ?></span>
    </div>
    <div class="card kpi">
        <span class="label">Pendiente</span>
        <span class="value"><?= $kpis['pending'] ?? 0 ?></span>
    </div>
    <div class="card kpi">
        <span class="label">Aprobado</span>
        <span class="value"><?= $kpis['approved'] ?? 0 ?></span>
    </div>
    <div class="card kpi">
        <span class="label">Rechazado</span>
        <span class="value"><?= $kpis['rejected'] ?? 0 ?></span>
    </div>
</div>

<?php if (!empty($canReport)): ?>
    <div class="card" style="margin-top: 12px;">
        <h3 style="margin-top:0;">Mis horas</h3>
        <?php if (empty($tasksForTimesheet)): ?>
            <p style="margin:0; color: var(--muted);">No hay tareas disponibles para registrar horas.</p>
        <?php else: ?>
            <form method="POST" action="<?= $basePath ?>/timesheets/create" style="display:flex; flex-direction:column; gap:10px;">
                <label>Tarea
                    <select name="task_id" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                        <option value="">Selecciona una tarea</option>
                        <?php foreach ($tasksForTimesheet as $task): ?>
                            <option value="<?= (int) $task['id'] ?>"><?= htmlspecialchars($task['project']) ?> · <?= htmlspecialchars($task['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <small style="color: var(--muted);">Solo se muestran tareas asignadas al talento y en estado Doing (En curso).</small>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px;">
                    <label>Fecha
                        <input type="date" name="date" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                    </label>
                    <label>Horas
                        <input type="number" name="hours" step="0.25" min="0.25" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                    </label>
                </div>
                <label style="display:flex; gap:8px; align-items:center;">
                    <input type="checkbox" name="billable" value="1"> Facturable
                </label>
                <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Registrar horas</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($canApprove)): ?>
    <div class="card" style="margin-top: 12px;">
        <h3 style="margin-top:0;">Horas pendientes de aprobación</h3>
        <?php if (empty($pendingApprovals)): ?>
            <p style="margin:0; color: var(--muted);">No hay horas pendientes.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Proyecto</th>
                        <th>Tarea</th>
                        <th>Talento</th>
                        <th>Horas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pendingApprovals as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['project']) ?></td>
                            <td><?= htmlspecialchars($row['task']) ?></td>
                            <td><?= htmlspecialchars($row['talent']) ?></td>
                            <td><?= $row['hours'] ?></td>
                            <td><span class="badge warning">pendiente</span></td>
                            <td>
                                <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int) $row['id'] ?>/approve" style="display:inline;">
                                    <button type="submit" class="primary-button" style="border:none; cursor:pointer; padding:6px 10px;">Aprobar</button>
                                </form>
                                <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int) $row['id'] ?>/reject" style="display:inline;">
                                    <button type="submit" class="secondary-button" style="border:none; cursor:pointer; padding:6px 10px;">Rechazar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card" style="margin-top: 12px;">
    <h3 style="margin-top:0;">Registro de horas</h3>
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
                <?php
                $status = $row['status'] === 'submitted' || $row['status'] === 'pending_approval' ? 'pending' : $row['status'];
                $badgeClass = $status === 'approved' ? 'success' : ($status === 'pending' ? 'warning' : ($status === 'rejected' ? 'danger' : ''));
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td><?= htmlspecialchars($row['project']) ?></td>
                    <td><?= htmlspecialchars($row['task']) ?></td>
                    <td><?= htmlspecialchars($row['talent']) ?></td>
                    <td><?= $row['hours'] ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
                    <td><?= $row['billable'] ? 'Sí' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
