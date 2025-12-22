<?php
$config = require __DIR__ . '/../../config.php';
$theme = $config['theme'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso | <?= htmlspecialchars($config['app']['name']) ?></title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.08), transparent 40%),
                        radial-gradient(circle at 80% 0%, rgba(255,255,255,0.05), transparent 35%),
                        linear-gradient(135deg, <?= htmlspecialchars($theme['background']) ?> 0%, <?= htmlspecialchars($theme['secondary']) ?> 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin:0;
            color: #e5e7eb;
        }
        .panel {
            background: rgba(255,255,255,0.06);
            padding: 32px;
            border-radius: 18px;
            width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        h1 { margin: 0 0 8px 0; color: white; font-size: 26px; }
        p { margin: 0 0 16px 0; color: rgba(255,255,255,0.8); }
        label { display:block; margin-bottom: 6px; font-weight: 600; color: #f8fafc; }
        input { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.2); margin-bottom: 12px; background: rgba(255,255,255,0.06); color: white; }
        input::placeholder { color: rgba(255,255,255,0.6); }
        button { width: 100%; padding: 12px; border-radius: 12px; background: <?= htmlspecialchars($theme['primary']) ?>; color: white; border: none; font-weight:700; cursor: pointer; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .error { background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
        .hero { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
        .hero img { height: 42px; background: white; padding: 8px; border-radius: 12px; }
        .pill { display:inline-block; padding:6px 10px; border-radius:999px; background: rgba(255,255,255,0.1); color:white; font-size:12px; margin-right:6px; border:1px solid rgba(255,255,255,0.2); }
    </style>
</head>
<body>
    <div class="panel">
        <div class="hero">
            <?php if(!empty($theme['logo'])): ?>
                <img src="<?= htmlspecialchars($theme['logo']) ?>" alt="Logo" onerror="this.style.display='none'">
            <?php endif; ?>
            <div>
                <h1><?= htmlspecialchars($theme['login_hero']) ?></h1>
                <p><?= htmlspecialchars($theme['login_message']) ?></p>
            </div>
        </div>
        <div style="margin-bottom:8px;">
            <span class="pill">Identidad PMO</span>
            <span class="pill">Roles: <?= htmlspecialchars(implode(' · ', $config['access']['roles'])) ?></span>
        </div>
        <?php if(isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="/project/public/login">
            <label for="email">Correo</label>
            <input type="email" name="email" id="email" placeholder="admin@compania.com" required>
            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" placeholder="••••••" required>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
