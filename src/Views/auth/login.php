<?php
$theme = $branding['theme'] ?? [];
$loginHero = $theme['login_hero'] ?? 'Orquesta tus operaciones críticas';
$loginSubtitle = $theme['login_subtitle'] ?? 'Controla proyectos, recursos y decisiones clave desde una sola plataforma.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso | <?= htmlspecialchars($appName ?? 'PMO') ?></title>
    <link rel="stylesheet" href="/project/public/assets/css/auth.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($theme['primary']) ?>;
            --secondary: <?= htmlspecialchars($theme['secondary']) ?>;
            --accent: <?= htmlspecialchars($theme['accent'] ?? $theme['primary']) ?>;
            --background: <?= htmlspecialchars($theme['background'] ?? '#f5f7fb') ?>;
            --surface: <?= htmlspecialchars($theme['surface'] ?? '#ffffff') ?>;
            --font-family: <?= htmlspecialchars($theme['font_family'] ?? "'Inter', sans-serif") ?>;
            --text-strong: color-mix(in srgb, #1f2937 88%, var(--secondary) 12%);
            --text: color-mix(in srgb, #374151 82%, var(--secondary) 18%);
            --muted: color-mix(in srgb, #6b7280 82%, var(--secondary) 18%);
            --border: color-mix(in srgb, #e5e7eb 70%, var(--primary) 30%);
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="branding">
            <div class="brand-card">
                <div class="branding-content">
                    <?php if(!empty($theme['logo'])): ?>
                        <img class="logo" src="<?= htmlspecialchars($theme['logo']) ?>" alt="Logo AOS" onerror="this.style.display='none'">
                    <?php else: ?>
                        <div class="logo-fallback">PMO</div>
                    <?php endif; ?>
                    <h1 class="claim"><?= htmlspecialchars($loginHero) ?></h1>
                    <p class="subtitle"><?= htmlspecialchars($loginSubtitle) ?></p>
                    <div class="bullets">
                        <div class="bullet">
                            <div class="bullet-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M12 3l2.5 5 5.5.8-4 3.9.9 5.6-4.9-2.6-4.9 2.6.9-5.6-4-3.9 5.5-.8L12 3z"/>
                                </svg>
                            </div>
                            <p class="bullet-text">Panel ejecutivo alineado a los KPIs críticos de la organización.</p>
                        </div>
                        <div class="bullet">
                            <div class="bullet-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M4 12l6-6 6 6-6 6-6-6z"/>
                                </svg>
                            </div>
                            <p class="bullet-text">Orquestación de clientes y proyectos con trazabilidad completa.</p>
                        </div>
                        <div class="bullet">
                            <div class="bullet-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M4 12h16M14 6l6 6-6 6"/>
                                </svg>
                            </div>
                            <p class="bullet-text">Seguridad y gobierno de accesos centralizado para la PMO.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="auth">
            <div class="card">
                <div class="pill">Acceso seguro · PMO</div>
                <h1>Acceso a la plataforma</h1>
                <p class="subtitle-card">Sistema de Gestión de Proyectos</p>
                <form method="POST" action="/project/public/login" class="form" data-login-form>
                    <div class="field">
                        <label for="email">Correo</label>
                        <div class="input-wrapper">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M4 6h16v12H4z"/>
                                    <path d="M4 7l8 6 8-6"/>
                                </svg>
                            </span>
                            <input type="email" name="email" id="email" placeholder="admin@compania.com" required autocomplete="email">
                        </div>
                    </div>
                    <div class="field">
                        <label for="password">Contraseña</label>
                        <div class="input-wrapper">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <rect x="4" y="11" width="16" height="9" rx="2"/>
                                    <path d="M8 11V8a4 4 0 118 0v3"/>
                                </svg>
                            </span>
                            <input type="password" name="password" id="password" placeholder="••••••" required autocomplete="current-password">
                        </div>
                    </div>
                    <?php if(isset($error)): ?>
                        <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <div class="actions">
                        <button type="submit" class="primary-btn">
                            <span class="btn-spinner" aria-hidden="true"></span>
                            <span class="btn-text">Ingresar</span>
                        </button>
                        <div class="link"><a href="#">¿Olvidaste tu contraseña?</a></div>
                    </div>
                </form>
            </div>
        </section>
    </div>
    <script>
        const loginForm = document.querySelector('[data-login-form]');
        if (loginForm) {
            loginForm.addEventListener('submit', () => {
                const button = loginForm.querySelector('button[type="submit"]');
                const buttonText = loginForm.querySelector('.btn-text');
                if (button && buttonText) {
                    button.disabled = true;
                    button.classList.add('is-loading');
                    buttonText.textContent = 'Ingresando…';
                }
            });
        }
    </script>
</body>
</html>
