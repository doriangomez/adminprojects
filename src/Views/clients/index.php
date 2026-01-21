<style>
    .clients-header {
        align-items: flex-start;
        gap: 12px;
    }
    .clients-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .clients-title .icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: color-mix(in srgb, var(--info) 18%, var(--surface) 82%);
        color: var(--primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid color-mix(in srgb, var(--info) 35%, var(--surface) 65%);
    }
    .clients-title h3 {
        margin: 0;
        font-size: 24px;
        color: var(--text-strong);
    }
    .clients-subtitle {
        margin: 4px 0 0 0;
        color: var(--muted);
        font-weight: 500;
    }
    .clients-cardlist {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 4px;
    }
    .client-card {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 14px;
        padding: 16px;
        border: 1px solid var(--border);
        border-radius: 14px;
        background: linear-gradient(135deg, color-mix(in srgb, var(--info) 18%, var(--surface) 82%), var(--surface));
        transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
    }
    .client-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px color-mix(in srgb, var(--text-strong) 6%, transparent);
        border-color: color-mix(in srgb, var(--primary) 25%, var(--border));
    }
    .client-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(180deg, color-mix(in srgb, var(--info) 12%, var(--surface) 88%), color-mix(in srgb, var(--info) 22%, var(--surface) 78%));
        border: 1px solid color-mix(in srgb, var(--info) 35%, var(--surface) 65%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        font-weight: 800;
        font-size: 20px;
        color: var(--primary);
        text-transform: uppercase;
    }
    .client-avatar img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: var(--surface);
        border-radius: 50%;
    }
    .client-body {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }
    .client-header {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .client-name {
        font-size: 18px;
        font-weight: 800;
        color: var(--text-strong);
        margin: 0 0 2px 0;
    }
    .client-meta, .client-secondary {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        color: var(--muted);
        font-weight: 600;
        font-size: 13px;
    }
    .meta-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--surface) 92%, var(--bg-app) 8%);
        border: 1px solid var(--border);
    }
    .meta-item svg { width: 14px; height: 14px; stroke: currentColor; }
    .badge-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .priority-badge, .status-badge, .health-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid var(--border);
    }
    .priority-badge.alta { background: color-mix(in srgb, var(--danger) 15%, var(--surface) 85%); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); }
    .priority-badge.media { background: color-mix(in srgb, var(--warning) 15%, var(--surface) 85%); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 40%, var(--surface) 60%); }
    .priority-badge.baja { background: color-mix(in srgb, var(--success) 15%, var(--surface) 85%); color: var(--success); border-color: color-mix(in srgb, var(--success) 40%, var(--surface) 60%); }
    .priority-badge.default { background: color-mix(in srgb, var(--neutral) 12%, var(--surface) 88%); color: var(--text-strong); }
    .status-badge { background: color-mix(in srgb, var(--info) 15%, var(--surface) 85%); color: var(--info); border-color: color-mix(in srgb, var(--info) 40%, var(--surface) 60%); }
    .health-badge { background: color-mix(in srgb, var(--warning) 15%, var(--surface) 85%); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 40%, var(--surface) 60%); }
    .client-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        justify-content: center;
        min-width: 160px;
    }
    .client-actions .btn { padding: 9px 12px; border-radius: 10px; }
    .client-metrics {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .metric-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 12px;
        background: color-mix(in srgb, var(--surface) 92%, var(--bg-app) 8%);
        border: 1px solid var(--border);
        color: var(--text-strong);
        font-weight: 700;
    }
    .metric-chip svg { width: 16px; height: 16px; stroke: currentColor; }
    @media (max-width: 900px) {
        .client-card { grid-template-columns: 1fr; }
        .client-actions { flex-direction: row; justify-content: flex-start; align-items: center; }
        .clients-title h3 { font-size: 20px; }
    }
</style>

<div class="toolbar clients-header">
    <div>
        <div class="clients-title">
            <span class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 7a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm0 11a4 4 0 0 1 8 0" />
                    <path d="M14 13a4 4 0 1 1 6 0v5h-6z" />
                </svg>
            </span>
            <div>
                <h3>Clientes</h3>
                <p class="clients-subtitle">Relaciones ejecutivas y contexto estratégico. El cliente gobierna la relación; los contratos viven en cada proyecto.</p>
            </div>
        </div>
    </div>
    <?php if($canManage): ?>
        <div>
            <a href="/project/public/clients/create" class="btn primary" style="display:inline-flex; gap:8px; align-items:center;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Nuevo cliente
            </a>
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
    <div class="clients-cardlist">
        <?php foreach($clients as $client): ?>
            <article class="client-card">
                <div class="client-avatar" aria-hidden="true">
                    <?php if(!empty($client['logo_path'])): ?>
                        <img src="<?= $basePath . $client['logo_path'] ?>" alt="Logo de <?= htmlspecialchars($client['name']) ?>">
                    <?php else: ?>
                        <?= strtoupper(substr($client['name'] ?? 'C', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="client-body">
                    <div class="client-header">
                        <div>
                            <p class="client-name"><?= htmlspecialchars($client['name']) ?></p>
                            <div class="client-meta">
                                <span class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 11h18v8H3z"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    <?= htmlspecialchars($client['sector_label'] ?? $client['sector_code'] ?? '-') ?>
                                </span>
                                <span class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 3 8.5 12 14l9-5.5Z"/><path d="M3 15l9 5 9-5"/></svg>
                                    <?= htmlspecialchars($client['category_label'] ?? $client['category_code'] ?? '-') ?>
                                </span>
                            </div>
                        </div>
                        <div class="badge-row">
                            <span class="priority-badge <?= htmlspecialchars($client['priority'] ?? 'default') ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h9l5 5-5 5H5z"/><path d="M5 13v8"/></svg>
                                <?= htmlspecialchars($client['priority_label'] ?? ucfirst($client['priority'] ?? '')) ?>
                            </span>
                            <span class="status-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14m-7-7h14"/></svg>
                                <?= htmlspecialchars($client['status_label'] ?? $client['status_code'] ?? '-') ?>
                            </span>
                            <?php if(!empty($client['risk_label'] ?? $client['risk_code'])): ?>
                                <span class="health-badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/></svg>
                                    <?= htmlspecialchars($client['risk_label'] ?? $client['risk_code']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="client-secondary">
                        <span class="meta-item" style="background: transparent; border-color: transparent; padding: 0; gap:6px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-6 8a6 6 0 0 1 12 0"/></svg>
                            <?= htmlspecialchars($client['pm_name'] ?? 'Sin asignar') ?>
                        </span>
                        <span class="meta-item" style="background: transparent; border-color: transparent; padding: 0; gap:6px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/><path d="M9 7v10"/></svg>
                            Área: <?= htmlspecialchars($client['area_label'] ?? ($client['area_code'] ?? 'No definida')) ?>
                        </span>
                    </div>
                    <div class="client-metrics">
                        <span class="metric-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 17.5 7 20l1-5-4-4 5.5-.8L12 5l2.5 5.2 5.5.8-4 4 1 5z"/></svg>
                            Satisfacción: <?= $client['satisfaction'] !== null ? (int) $client['satisfaction'] : '-' ?>
                        </span>
                        <span class="metric-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 17 9 9l4 5 4-8 3 5"/><path d="M3 21h18"/></svg>
                            NPS: <?= $client['nps'] !== null ? (int) $client['nps'] : '-' ?>
                        </span>
                    </div>
                </div>
                <div class="client-actions">
                    <a class="btn secondary" href="/project/public/clients/<?= (int) $client['id'] ?>">Ver detalle</a>
                    <?php if($canManage): ?>
                        <a class="btn ghost" href="/project/public/clients/<?= (int) $client['id'] ?>/edit">Editar</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>
