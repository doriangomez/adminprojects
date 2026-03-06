<?php
$errorMessage = $errorMessage ?? 'No se puede procesar esta solicitud en este momento.';
$errorDetail = $errorDetail ?? null;
$showDetail = !empty($showDetail);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error del servidor</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 24px; background: #f5f5f5; color: #333; }
        .card { max-width: 560px; margin: 48px auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h1 { margin: 0 0 12px; font-size: 24px; color: #c00; }
        p { margin: 0 0 16px; line-height: 1.6; color: #555; }
        .detail { margin-top: 16px; padding: 12px; background: #f8f8f8; border-radius: 8px; font-family: monospace; font-size: 13px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
        .actions { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
        a { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Error del servidor</h1>
        <p><?= htmlspecialchars($errorMessage) ?></p>
        <?php if ($showDetail && $errorDetail !== null): ?>
        <div class="detail"><?= htmlspecialchars($errorDetail) ?></div>
        <?php endif; ?>
        <div class="actions">
            <a href="javascript:history.back()" class="btn-secondary">Regresar</a>
            <a href="/" class="btn-primary">Ir al inicio</a>
        </div>
    </div>
</body>
</html>
