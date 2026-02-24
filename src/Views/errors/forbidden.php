<div class="card" style="max-width: 760px; margin: 12px auto;">
    <span class="badge danger">Error 403</span>
    <h3 style="margin:12px 0 6px; font-size: 28px;"><?= htmlspecialchars($errorTitle ?? 'Acceso denegado') ?></h3>
    <p style="margin:0; color: var(--text-secondary); font-size: 16px; line-height:1.6;">
        <?= htmlspecialchars($errorMessage ?? 'No cuentas con permisos para visualizar esta sección del sistema.') ?>
    </p>

    <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="/dashboard">Volver al dashboard</a>
        <a class="btn secondary" href="javascript:history.back()">Regresar</a>
    </div>
</div>
