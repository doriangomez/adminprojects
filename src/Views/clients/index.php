<div class="toolbar">
    <div>
        <h3 style="margin:0;">Clientes</h3>
        <p style="margin:4px 0 0 0; color: var(--muted);">El cliente gobierna la relación estratégica; los contratos viven en cada proyecto.</p>
    </div>
    <?php if($canManage): ?>
        <div>
            <a href="/project/public/clients/create" class="btn primary">Nuevo cliente</a>
        </div>
    <?php endif; ?>
</div>

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
                    <td style="display:flex; align-items:center; gap:12px;">
                        <?php if(!empty($client['logo_path'])): ?>
                            <img src="<?= $basePath . $client['logo_path'] ?>" alt="Logo de <?= htmlspecialchars($client['name']) ?>" style="width:42px; height:42px; object-fit:contain; border:1px solid var(--border); border-radius:10px; background: #fff;">
                        <?php else: ?>
                            <div style="width:42px; height:42px; display:flex; align-items:center; justify-content:center; border:1px solid var(--border); border-radius:10px; background:#fff; color:var(--muted); font-weight:700;">
                                <?= strtoupper(substr($client['name'] ?? 'C', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <strong><?= htmlspecialchars($client['name']) ?></strong><br>
                            <small style="color: var(--muted);">Área: <?= htmlspecialchars($client['area'] ?? 'No definida') ?></small>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($client['sector_label'] ?? $client['sector_code'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($client['category_label'] ?? $client['category_code'] ?? '-') ?></td>
                    <td><span class="pill <?= htmlspecialchars($client['priority']) ?>"><?= htmlspecialchars($client['priority_label'] ?? ucfirst($client['priority'])) ?></span></td>
                    <td><?= htmlspecialchars($client['pm_name'] ?? 'Sin asignar') ?></td>
                    <td><span class="badge neutral"><?= htmlspecialchars($client['status_label'] ?? $client['status_code'] ?? '-') ?></span></td>
                    <td><?= $client['satisfaction'] !== null ? (int) $client['satisfaction'] : '-' ?></td>
                    <td><?= $client['nps'] !== null ? (int) $client['nps'] : '-' ?></td>
                    <td style="text-align:right; display:flex; gap:6px; justify-content:flex-end;">
                        <a class="btn secondary" href="/project/public/clients/<?= (int) $client['id'] ?>">Ver detalle</a>
                        <?php if($canManage): ?>
                            <a class="btn ghost" href="/project/public/clients/<?= (int) $client['id'] ?>/edit">Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
