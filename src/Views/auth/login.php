<?php session_start(); $config = require __DIR__ . '/../../config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso | <?= htmlspecialchars($config['app']['name']) ?></title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin:0; }
        .panel { background: white; padding: 32px; border-radius: 16px; width: 360px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        h1 { margin: 0 0 8px 0; }
        p { margin: 0 0 16px 0; color: #6b7280; }
        label { display:block; margin-bottom: 6px; font-weight: 600; color: #111827; }
        input { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #e5e7eb; margin-bottom: 12px; }
        button { width: 100%; padding: 12px; border-radius: 12px; background: #2563eb; color: white; border: none; font-weight: 700; cursor: pointer; }
        .error { background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="panel">
        <h1>Acceso</h1>
        <p>Control centralizado del portafolio</p>
        <?php if(isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="/login">
            <label for="email">Correo</label>
            <input type="email" name="email" id="email" required>
            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" required>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
