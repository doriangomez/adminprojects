<div class="kanban">
    <?php foreach($columns as $status => $items): ?>
        <div class="column">
            <h3><?= ucfirst(str_replace('_', ' ', $status)) ?> (<?= count($items) ?>)</h3>
            <?php foreach($items as $task): ?>
                <div class="card-task">
                    <div style="display:flex; justify-content: space-between;">
                        <strong><?= htmlspecialchars($task['title']) ?></strong>
                        <span class="pill <?= htmlspecialchars($task['priority']) ?>"><?= htmlspecialchars($task['priority']) ?></span>
                    </div>
                    <p style="margin:4px 0; color: var(--muted);">Proyecto: <?= htmlspecialchars($task['project']) ?></p>
                    <p style="margin:4px 0; color: var(--muted);">Responsable: <?= htmlspecialchars($task['assignee'] ?? 'Sin asignar') ?></p>
                    <?php
                    $estimated = (float) ($task['estimated_hours'] ?? 0);
                    $actual = (float) ($task['actual_hours'] ?? 0);
                    $deviation = $actual - $estimated;
                    $deviationText = $deviation === 0.0 ? 'En línea' : (($deviation > 0) ? '+' . $deviation . 'h' : $deviation . 'h');
                    ?>
                    <div style="display:flex; gap: 8px; align-items:center; flex-wrap:wrap;">
                        <span class="badge">Estimado: <?= $estimated ?>h</span>
                        <span class="badge <?= $actual > $estimated ? 'danger' : 'success' ?>">Registradas: <?= $actual ?>h</span>
                        <span class="badge <?= $deviation > 0 ? 'danger' : ($deviation < 0 ? 'success' : '') ?>">Desvío: <?= $deviationText ?></span>
                    </div>
                    <small style="color: var(--muted);">Vence: <?= htmlspecialchars($task['due_date']) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
