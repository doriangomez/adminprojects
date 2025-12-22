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
            --text: color-mix(in srgb, var(--secondary) 72%, var(--background) 28%);
            --muted: color-mix(in srgb, var(--text) 62%, var(--background) 38%);
            --panel: color-mix(in srgb, var(--surface) 90%, var(--background) 10%);
            --border: color-mix(in srgb, var(--surface) 55%, var(--background) 45%);
        }
        body {
            font-family: var(--font-family);
            background: color-mix(in srgb, var(--background) 96%, var(--surface) 4%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin:0;
            color: var(--text);
            padding: 24px;
        }
        .panel {
            background: color-mix(in srgb, var(--surface) 95%, var(--background) 5%);
            padding: 28px;
            border-radius: 14px;
            width: min(520px, 100%);
            border: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
        }
        h1 { margin: 0 0 6px 0; color: var(--text); font-size: 26px; letter-spacing: 0.01em; }
        p { margin: 0 0 16px 0; color: var(--muted); }
        label { display:block; margin-bottom: 6px; font-weight: 700; color: var(--text); letter-spacing: 0.01em; }
        input {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
            margin-bottom: 14px;
            background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%);
            color: var(--text);
        }
        input::placeholder { color: color-mix(in srgb, var(--muted) 80%, var(--background) 20%); }
        button {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            background: color-mix(in srgb, var(--primary) 86%, var(--secondary) 14%);
            color: white;
            border: 1px solid color-mix(in srgb, var(--primary) 55%, transparent);
            font-weight:700;
            cursor: pointer;
        }
        .error { background: transparent; color: var(--accent); padding: 10px; border-radius: 10px; margin-bottom: 12px; border:1px solid color-mix(in srgb, var(--accent) 40%, var(--border) 60%); }
        .hero { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
        .hero img { height: 44px; background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%); padding: 10px; border-radius: 10px; }
        .pill { display:inline-block; padding:6px 10px; border-radius:999px; background: transparent; color: var(--muted); font-size:12px; margin-right:6px; border:1px solid color-mix(in srgb, var(--border) 70%, transparent); }
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
