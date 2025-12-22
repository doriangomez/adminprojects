<div class="card">
    <div class="toolbar">
        <div>
            <h2 style="margin:0;">Portafolio por cliente</h2>
            <p style="margin:0;color:var(--muted);">Visibilidad filtrada por PM y consolidado de carga</p>
        </div>
        <a href="<?= $basePath ?>/projects" class="button ghost">Ver listado</a>
    </div>
    <?php if (!empty($error)): ?>
        <div class="alert danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="accordion">
        <?php foreach ($clients as $client): ?>
            <details>
                <summary>
                    <div class="client-header">
                        <div>
                            <strong><?= htmlspecialchars($client['name']) ?></strong>
                            <p style="margin:0; color: var(--muted);">Proyectos activos: <?= $client['kpis']['active_projects'] ?> / <?= $client['kpis']['total_projects'] ?></p>
                        </div>
                        <div class="kpi-row">
                            <span class="badge">Avance: <?= $client['kpis']['avg_progress'] ?>%</span>
                            <span class="badge <?= $client['kpis']['risk_level'] === 'alto' ? 'danger' : ($client['kpis']['risk_level'] === 'medio' ? 'warning' : 'success') ?>">Riesgo: <?= ucfirst($client['kpis']['risk_level']) ?></span>
                            <span class="badge ghost">Capacidad: <?= $client['kpis']['capacity_used'] ?>h / <?= $client['kpis']['capacity_available'] ?>h (<?= $client['kpis']['capacity_percent'] ?>%)</span>
                        </div>
                    </div>
                </summary>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Proyecto</th>
                                <th>Tipo</th>
                                <th>PM</th>
                                <th>Avance</th>
                                <th>Riesgo</th>
                                <th>Estado</th>
                                <th>Capacidad asignada</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client['projects'] as $project): ?>
                                <?php $projectAssignments = $client['assignments'][$project['id']] ?? []; ?>
                                <?php $assignedHours = array_sum(array_map(fn($a) => (float) ($a['weekly_hours'] ?? 0), $projectAssignments)); ?>
                                <?php $assignedPercent = array_sum(array_map(fn($a) => (float) ($a['allocation_percent'] ?? 0), $projectAssignments)); ?>
                                <tr>
                                    <td><?= htmlspecialchars($project['name']) ?></td>
                                    <td><?= ucfirst($project['project_type'] ?? 'convencional') ?></td>
                                    <td><?= htmlspecialchars($project['pm_name'] ?? 'N/D') ?></td>
                                    <td><?= $project['progress'] ?>%</td>
                                    <td><span class="badge <?= $project['health'] === 'on_track' ? 'success' : ($project['health'] === 'at_risk' ? 'warning' : 'danger') ?>"><?= htmlspecialchars($project['health_label'] ?? $project['health']) ?></span></td>
                                    <td><?= htmlspecialchars($project['status_label'] ?? $project['status']) ?></td>
                                    <td><?= $assignedHours ?>h / <?= $assignedPercent ?>%</td>
                                    <td><a class="button ghost" href="#">Ver detalle</a></td>
                                </tr>
                                <tr>
                                    <td colspan="8">
                                        <form method="POST" action="<?= $basePath ?>/projects/assign-talent" class="inline-form">
                                            <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                            <label>
                                                Talento
                                                <select name="talent_id" required>
                                                    <option value="">Seleccionar</option>
                                                    <?php foreach ($talents as $talent): ?>
                                                        <option value="<?= $talent['id'] ?>"><?= htmlspecialchars($talent['name']) ?> (<?= $talent['availability'] ?>% libre)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>
                                                Rol
                                                <input type="text" name="role" placeholder="Rol en el proyecto" required>
                                            </label>
                                            <label>
                                                Inicio
                                                <input type="date" name="start_date">
                                            </label>
                                            <label>
                                                Fin
                                                <input type="date" name="end_date">
                                            </label>
                                            <label>
                                                % Asignación
                                                <input type="number" name="allocation_percent" min="0" max="100" step="0.1">
                                            </label>
                                            <label>
                                                Horas/semana
                                                <input type="number" name="weekly_hours" min="0" step="0.1">
                                            </label>
                                            <label>
                                                Tipo de costo
                                                <select name="cost_type">
                                                    <option value="fijo_mensual">Fijo mensual</option>
                                                    <option value="por_horas">Por horas</option>
                                                </select>
                                            </label>
                                            <label>
                                                Valor costo
                                                <input type="number" name="cost_value" min="0" step="0.01" required>
                                            </label>
                                            <label class="inline-checkbox">
                                                <input type="checkbox" name="is_external" value="1"> Externo
                                            </label>
                                            <label class="inline-checkbox">
                                                <input type="checkbox" name="requires_timesheet" value="1"> Requiere timesheet
                                            </label>
                                            <label class="inline-checkbox">
                                                <input type="checkbox" name="requires_approval" value="1"> Requiere aprobación
                                            </label>
                                            <button type="submit" class="button">Asignar talento</button>
                                        </form>
                                        <?php if ($projectAssignments): ?>
                                            <div class="assignment-chips">
                                                <?php foreach ($projectAssignments as $assignment): ?>
                                                    <span class="pill">
                                                        <?= htmlspecialchars($assignment['talent_name']) ?> — <?= htmlspecialchars($assignment['role']) ?> (<?= $assignment['weekly_hours'] ?>h / <?= $assignment['allocation_percent'] ?>%)
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</div>
