<?php
$theme = $branding['theme'] ?? [];
$config = $configData ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso | <?= htmlspecialchars($appName ?? 'PMO') ?></title>
    <style>
        :root {
            --primary: <?= htmlspecialchars($theme['primary']) ?>;
            --secondary: <?= htmlspecialchars($theme['secondary']) ?>;
            --accent: <?= htmlspecialchars($theme['accent'] ?? $theme['primary']) ?>;
            --background: <?= htmlspecialchars($theme['background']) ?>;
            --surface: <?= htmlspecialchars($theme['surface'] ?? $theme['background']) ?>;
            --font-family: <?= htmlspecialchars($theme['font_family'] ?? "'Inter', sans-serif") ?>;
            --text: color-mix(in srgb, var(--secondary) 75%, var(--background) 25%);
            --muted: color-mix(in srgb, var(--secondary) 55%, var(--background) 45%);
            --border: color-mix(in srgb, var(--surface) 60%, var(--background) 40%);
        }
        body {
            font-family: var(--font-family);
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin:0;
            color: var(--text);
            padding: 24px;
        }
        .panel {
            background: var(--surface);
            padding: 28px;
            border-radius: 6px;
            width: min(520px, 100%);
            border: 1px solid var(--border);
        }
        h1 { margin: 0 0 8px 0; color: var(--text); font-size: 24px; }
        p { margin: 0 0 16px 0; color: var(--muted); }
        label { display:block; margin-bottom: 6px; font-weight: 600; color: var(--text); }
        input {
            width: 100%;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid var(--border);
            margin-bottom: 14px;
            background: var(--background);
            color: var(--text);
        }
        input::placeholder { color: var(--muted); }
        button {
            width: 100%;
            padding: 12px;
            border-radius: 4px;
            background: var(--primary);
            color: white;
            border: 1px solid var(--primary);
            font-weight:700;
            cursor: pointer;
        }
        .error { background: transparent; color: var(--accent); padding: 10px; border-radius: 4px; margin-bottom: 12px; border:1px solid var(--border); }
        .hero { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
        .hero img { height: 44px; }
        .pill { display:inline-block; padding:6px 10px; border-radius:4px; background: transparent; color: var(--muted); font-size:12px; margin-right:6px; border:1px solid var(--border); }
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
            <span class="pill">Roles: <?= htmlspecialchars(implode(' · ', $config['access']['roles'] ?? [])) ?></span>
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
