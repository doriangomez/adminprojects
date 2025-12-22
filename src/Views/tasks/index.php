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
                    <div style="display:flex; gap: 8px; align-items:center;">
                        <span class="badge">Estimado: <?= $task['estimated_hours'] ?>h</span>
                        <span class="badge <?= ($task['actual_hours'] ?? 0) > ($task['estimated_hours'] ?? 0) ? 'danger' : 'success' ?>">Usadas: <?= $task['actual_hours'] ?>h</span>
                    </div>
                    <small style="color: var(--muted);">Vence: <?= htmlspecialchars($task['due_date']) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
