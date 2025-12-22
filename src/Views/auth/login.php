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
        :root {
            --primary: <?= htmlspecialchars($theme['primary']) ?>;
            --secondary: <?= htmlspecialchars($theme['secondary']) ?>;
            --accent: <?= htmlspecialchars($theme['accent'] ?? $theme['primary']) ?>;
            --background: <?= htmlspecialchars($theme['background']) ?>;
            --surface: <?= htmlspecialchars($theme['surface'] ?? $theme['background']) ?>;
            --font-family: <?= htmlspecialchars($theme['font_family'] ?? "'Inter', sans-serif") ?>;
            --text: color-mix(in srgb, var(--secondary) 78%, var(--background) 22%);
            --muted: color-mix(in srgb, var(--text) 65%, var(--background) 35%);
            --panel: color-mix(in srgb, var(--surface) 88%, var(--background) 12%);
            --border: color-mix(in srgb, var(--surface) 60%, var(--background) 40%);
            --glow: color-mix(in srgb, var(--primary) 18%, transparent);
            --soft-secondary: color-mix(in srgb, var(--secondary) 14%, var(--panel) 86%);
            --positive: color-mix(in srgb, var(--primary) 55%, var(--accent) 45%);
        }
        body {
            font-family: var(--font-family);
            background: radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--secondary) 10%, transparent), transparent 42%),
                        radial-gradient(circle at 85% 10%, color-mix(in srgb, var(--primary) 12%, transparent), transparent 40%),
                        linear-gradient(135deg, color-mix(in srgb, var(--background) 40%, var(--surface) 60%), color-mix(in srgb, var(--surface) 54%, var(--secondary) 46%));
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin:0;
            color: color-mix(in srgb, white 88%, var(--secondary) 12%);
            padding: 22px;
        }
        .panel {
            background: color-mix(in srgb, var(--panel) 18%, rgba(0,0,0,0.6));
            padding: 36px;
            border-radius: 18px;
            width: min(480px, 100%);
            box-shadow: 0 30px 70px color-mix(in srgb, var(--secondary) 22%, transparent);
            backdrop-filter: blur(14px);
            border: 1px solid color-mix(in srgb, var(--panel) 28%, transparent);
        }
        h1 { margin: 0 0 8px 0; color: white; font-size: 28px; letter-spacing: 0.03em; }
        p { margin: 0 0 18px 0; color: color-mix(in srgb, white 78%, transparent); }
        label { display:block; margin-bottom: 6px; font-weight: 700; color: white; letter-spacing: 0.01em; }
        input { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid color-mix(in srgb, var(--panel) 36%, transparent); margin-bottom: 14px; background: color-mix(in srgb, var(--panel) 28%, rgba(255,255,255,0.02)); color: white; }
        input::placeholder { color: color-mix(in srgb, white 70%, transparent); }
        button { width: 100%; padding: 12px; border-radius: 12px; background: color-mix(in srgb, var(--primary) 82%, var(--secondary) 18%); color: white; border: 1px solid color-mix(in srgb, var(--primary) 30%, transparent); font-weight:700; cursor: pointer; box-shadow: 0 12px 34px color-mix(in srgb, var(--primary) 20%, transparent); }
        .error { background: color-mix(in srgb, var(--positive) 12%, rgba(255,255,255,0.04)); color: var(--positive); padding: 10px; border-radius: 10px; margin-bottom: 12px; border:1px solid color-mix(in srgb, var(--positive) 30%, transparent); }
        .hero { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
        .hero img { height: 48px; background: color-mix(in srgb, var(--panel) 30%, transparent); padding: 10px; border-radius: 14px; box-shadow: 0 12px 30px color-mix(in srgb, var(--secondary) 20%, transparent); }
        .pill { display:inline-block; padding:6px 10px; border-radius:999px; background: color-mix(in srgb, white 10%, transparent); color:white; font-size:12px; margin-right:6px; border:1px solid color-mix(in srgb, var(--panel) 32%, transparent); }
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
