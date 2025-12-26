<?php
$theme = $branding['theme'] ?? [];
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
            --background: <?= htmlspecialchars($theme['background'] ?? '#f5f7fb') ?>;
            --surface: <?= htmlspecialchars($theme['surface'] ?? '#ffffff') ?>;
            --surface-muted: color-mix(in srgb, var(--surface) 92%, white 8%);
            --font-family: <?= htmlspecialchars($theme['font_family'] ?? "'Inter', sans-serif") ?>;
            --text-strong: color-mix(in srgb, #1f2937 88%, var(--secondary) 12%);
            --text: color-mix(in srgb, #374151 82%, var(--secondary) 18%);
            --muted: color-mix(in srgb, #6b7280 82%, var(--secondary) 18%);
            --border: color-mix(in srgb, #e5e7eb 70%, var(--primary) 30%);
            --shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
            --page-gradient: linear-gradient(135deg, #f5f7fb 0%, #e9edf5 100%);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--page-gradient);
            font-family: var(--font-family);
            color: var(--text);
            display: flex;
        }
        .page {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            width: 100%;
            min-height: 100vh;
        }
        .branding {
            padding: 64px 72px;
            background:
                radial-gradient(circle at 18% 22%, color-mix(in srgb, var(--primary) 18%, #f7f9fc 82%), #e6ebf2),
                linear-gradient(135deg, color-mix(in srgb, var(--background) 85%, white 15%), #f5f7fb);
            color: #1f2937;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 24px;
            overflow: hidden;
        }
        .branding::after {
            content: "";
            position: absolute;
            inset: 32px;
            border: 1px solid rgba(31, 41, 55, 0.06);
            border-radius: 28px;
            pointer-events: none;
        }
        .branding-content { position: relative; z-index: 1; max-width: 520px; }
        .logo {
            height: 56px;
            margin-bottom: 12px;
            filter: drop-shadow(0 8px 20px rgba(148, 163, 184, 0.25));
        }
        .claim {
            font-size: 32px;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0 0 10px 0;
            color: #1f2937;
        }
        .subtitle {
            font-size: 16px;
            color: #4b5563;
            margin: 0 0 20px 0;
            max-width: 440px;
        }
        .bullets { display: grid; gap: 12px; }
        .bullet {
            display: grid;
            grid-template-columns: 36px 1fr;
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 14px;
            backdrop-filter: blur(4px);
        }
        .bullet-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 32%, #e0e7ff 68%), color-mix(in srgb, var(--accent) 28%, #cffafe 72%));
            color: #0f172a;
            font-weight: 800;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.14);
        }
        .bullet-text { margin: 0; color: #1f2937; font-weight: 600; letter-spacing: -0.01em; }

        .auth { display: grid; place-items: center; padding: 48px; background: color-mix(in srgb, #ffffff 70%, var(--background) 30%); }
        .card {
            width: min(520px, 100%);
            background: var(--surface);
            border-radius: 20px;
            padding: 32px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--primary) 12%, white 88%);
            color: var(--text-strong);
            font-size: 13px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        h1 { margin: 0 0 6px 0; color: var(--text-strong); font-size: 26px; letter-spacing: -0.01em; }
        .subtitle-card { margin: 0 0 20px 0; color: var(--muted); }
        label { display:block; margin-bottom: 8px; font-weight: 600; color: var(--text-strong); }
        input {
            width: 100%;
            padding: 14px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
            color: var(--text-strong);
            margin-bottom: 16px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        input:focus {
            outline: none;
            border-color: color-mix(in srgb, var(--primary) 70%, var(--accent) 30%);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 12%, white 88%);
        }
        input::placeholder { color: var(--muted); }
        .actions { display:flex; flex-direction:column; gap:12px; }
        button {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 80%, white 20%), color-mix(in srgb, var(--accent) 65%, var(--primary) 35%));
            color: white;
            border: 1px solid color-mix(in srgb, var(--primary) 80%, var(--accent) 20%);
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.01em;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.25);
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease, border-color 0.15s ease;
        }
        button:hover {
            transform: translateY(-1px);
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 72%, #0f172a 28%), color-mix(in srgb, var(--accent) 68%, var(--primary) 32%));
            border-color: color-mix(in srgb, var(--primary) 86%, var(--accent) 14%);
            box-shadow: 0 14px 32px rgba(37, 99, 235, 0.32);
        }
        button:active { transform: translateY(0); }
        .link { text-align: center; }
        .link a { color: color-mix(in srgb, var(--primary) 80%, var(--accent) 20%); text-decoration: none; font-weight: 600; font-size: 14px; }
        .error { background: color-mix(in srgb, var(--accent) 8%, white 92%); color: color-mix(in srgb, var(--accent) 80%, var(--secondary) 20%); padding: 12px 14px; border-radius: 12px; margin-bottom: 14px; border:1px solid color-mix(in srgb, var(--accent) 50%, white 50%); }

        @media (max-width: 960px) {
            .branding { padding: 48px 32px; }
            .card { padding: 28px; }
            .claim { font-size: 26px; }
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="branding">
            <div class="branding-content">
                <?php if(!empty($theme['logo'])): ?>
                    <img class="logo" src="<?= htmlspecialchars($theme['logo']) ?>" alt="Logo AOS" onerror="this.style.display='none'">
                <?php endif; ?>
                <h2 class="claim"><?= htmlspecialchars($theme['login_hero']) ?></h2>
                <p class="subtitle">Sistema PMO con visión ejecutiva, control de clientes y proyectos en un solo lugar.</p>
                <div class="bullets">
                    <div class="bullet">
                        <div class="bullet-icon">✦</div>
                        <p class="bullet-text">Panel ejecutivo alineado a los KPIs críticos de la organización.</p>
                    </div>
                    <div class="bullet">
                        <div class="bullet-icon">⬢</div>
                        <p class="bullet-text">Orquestación de clientes y proyectos con trazabilidad completa.</p>
                    </div>
                    <div class="bullet">
                        <div class="bullet-icon">➜</div>
                        <p class="bullet-text">Seguridad y gobierno de accesos centralizado para la PMO.</p>
                    </div>
                </div>
            </div>
        </section>
        <section class="auth">
            <div class="card">
                <div class="pill">Acceso seguro · PMO</div>
                <h1>Acceso a la plataforma</h1>
                <p class="subtitle-card">Sistema de Gestión de Proyectos</p>
                <?php if(isset($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="/project/public/login" class="form">
                    <label for="email">Correo</label>
                    <input type="email" name="email" id="email" placeholder="admin@compania.com" required>
                    <label for="password">Contraseña</label>
                    <input type="password" name="password" id="password" placeholder="••••••" required>
                    <div class="actions">
                        <button type="submit">Ingresar</button>
                        <div class="link"><a href="#">¿Olvidaste tu contraseña?</a></div>
                    </div>
                </form>
            </div>
        </section>
    </div>
</body>
</html>
