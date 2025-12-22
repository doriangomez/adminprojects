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
            background: radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--primary) 12%, transparent), transparent 40%),
                        radial-gradient(circle at 80% 0%, color-mix(in srgb, var(--accent) 10%, transparent), transparent 35%),
                        linear-gradient(135deg, color-mix(in srgb, var(--background) 70%, var(--surface) 30%), color-mix(in srgb, var(--surface) 60%, var(--secondary) 40%));
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin:0;
            color: color-mix(in srgb, white 90%, var(--secondary) 10%);
            padding: 18px;
        }
        .panel {
            background: color-mix(in srgb, var(--panel) 40%, transparent);
            padding: 32px;
            border-radius: 20px;
            width: min(460px, 100%);
            box-shadow: 0 26px 60px var(--glow);
            backdrop-filter: blur(16px);
            border: 1px solid color-mix(in srgb, var(--panel) 30%, transparent);
        }
        h1 { margin: 0 0 8px 0; color: white; font-size: 28px; letter-spacing: 0.02em; }
        p { margin: 0 0 16px 0; color: color-mix(in srgb, white 82%, transparent); }
        label { display:block; margin-bottom: 6px; font-weight: 700; color: white; }
        input { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid color-mix(in srgb, var(--panel) 40%, transparent); margin-bottom: 12px; background: color-mix(in srgb, var(--panel) 22%, transparent); color: white; }
        input::placeholder { color: color-mix(in srgb, white 70%, transparent); }
        button { width: 100%; padding: 12px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 70%, var(--accent) 30%)); color: white; border: none; font-weight:700; cursor: pointer; box-shadow: 0 10px 30px var(--glow); }
        .error { background: color-mix(in srgb, var(--positive) 16%, var(--panel) 84%); color: var(--positive); padding: 10px; border-radius: 10px; margin-bottom: 12px; border:1px solid color-mix(in srgb, var(--positive) 40%, transparent); }
        .hero { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
        .hero img { height: 48px; background: var(--panel); padding: 10px; border-radius: 14px; box-shadow: 0 12px 30px var(--glow); }
        .pill { display:inline-block; padding:6px 10px; border-radius:999px; background: color-mix(in srgb, white 16%, transparent); color:white; font-size:12px; margin-right:6px; border:1px solid color-mix(in srgb, var(--panel) 40%, transparent); }
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
