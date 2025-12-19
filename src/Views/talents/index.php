<div class="grid">
    <?php foreach($talents as $talent): ?>
        <div class="card">
            <div class="toolbar" style="margin-bottom:8px;">
                <strong><?= htmlspecialchars($talent['name']) ?></strong>
                <span class="badge <?= $talent['availability'] >= 80 ? 'success' : ($talent['availability'] >= 50 ? 'warning' : 'danger') ?>">Disponibilidad <?= $talent['availability'] ?>%</span>
            </div>
            <p style="margin:0; color: var(--gray);">Rol: <?= htmlspecialchars($talent['role']) ?> | Seniority: <?= htmlspecialchars($talent['seniority']) ?></p>
            <p style="margin:0; color: var(--gray);">Capacidad semanal: <?= $talent['weekly_capacity'] ?>h</p>
            <p style="margin:0; color: var(--gray);">Costo: $<?= number_format($talent['hourly_cost'], 0, ',', '.') ?> / Tarifa: $<?= number_format($talent['hourly_rate'], 0, ',', '.') ?></p>
            <p style="margin:0; color: var(--gray);">Skills: <?= htmlspecialchars($talent['skills'] ?? 'n/a') ?></p>
        </div>
    <?php endforeach; ?>
</div>
