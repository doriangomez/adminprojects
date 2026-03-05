<?php
$theme = $theme ?? [];
$loginHero = $theme['login_hero'] ?? 'Orquesta tus operaciones críticas';
$loginSubtitle = $theme['login_message'] ?? $theme['login_subtitle'] ?? 'Controla proyectos, recursos y decisiones clave desde una sola plataforma.';
$logoUrl = $theme['logo_url'] ?? '';
$logoCss = $logoUrl !== '' ? "url('{$logoUrl}')" : 'none';
$themeVariables = [
    'background' => (string) ($theme['background'] ?? ''),
    'surface' => (string) ($theme['surface'] ?? ''),
    'primary' => (string) ($theme['primary'] ?? ''),
    'secondary' => (string) ($theme['secondary'] ?? ''),
    'accent' => (string) ($theme['accent'] ?? ''),
    'font-family' => (string) ($theme['font_family'] ?? ''),
    'text-primary' => (string) ($theme['textPrimary'] ?? $theme['text_main'] ?? ''),
    'text-secondary' => (string) ($theme['textSecondary'] ?? $theme['text_muted'] ?? ''),
    'text-disabled' => (string) ($theme['disabled'] ?? $theme['text_soft'] ?? $theme['text_disabled'] ?? ''),
    'border' => (string) ($theme['border'] ?? ''),
    'success' => (string) ($theme['success'] ?? ''),
    'warning' => (string) ($theme['warning'] ?? ''),
    'danger' => (string) ($theme['danger'] ?? ''),
    'info' => (string) ($theme['info'] ?? ''),
    'neutral' => (string) ($theme['neutral'] ?? ''),
];
require_once __DIR__ . '/../layout/logo_helper.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso | <?= htmlspecialchars($appName ?? 'PMO') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/auth.css">
    <script>
        window.applyTheme = function(theme) {
            if (!theme || typeof theme !== 'object') {
                return;
            }
            Object.entries(theme).forEach(([key, value]) => {
                document.documentElement.style.setProperty(`--${key}`, value ?? '');
            });
        };
        window.loadAndApplyTheme = function() {
            const theme = window.__APP_THEME__ || {};
            window.applyTheme(theme);
        };
        window.__APP_THEME__ = <?= json_encode($themeVariables, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.loadAndApplyTheme();
        document.addEventListener('DOMContentLoaded', () => {
            window.loadAndApplyTheme();
        });
    </script>
</head>
<body>
    <div class="page">
        <section class="branding">
            <div class="brand-card">
                <div class="branding-content">
                    <div class="logo-wrapper" aria-hidden="true">
                        <?php render_brand_logo((string) $logoUrl, $appName ?? 'PMO', 'logo', 'logo-fallback'); ?>
                    </div>
                    <h1 class="claim"><?= htmlspecialchars($loginHero) ?></h1>
                    <p class="subtitle"><?= htmlspecialchars($loginSubtitle) ?></p>
                    <div class="bullets">
                        <div class="bullet">
                            <div class="bullet-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                                </svg>
                            </div>
                            <p class="bullet-text">Panel ejecutivo alineado a los KPIs críticos de la organización.</p>
                        </div>
                        <div class="bullet">
                            <div class="bullet-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/>
                                </svg>
                            </div>
                            <p class="bullet-text">Orquestación de clientes y proyectos con trazabilidad completa.</p>
                        </div>
                        <div class="bullet">
                            <div class="bullet-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
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
                <form method="POST" action="<?= htmlspecialchars($basePath) ?>/login" class="form" data-login-form>
                    <div class="field">
                        <label for="email">Correo</label>
                        <div class="input-wrapper">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </span>
                            <input type="email" name="email" id="email" placeholder="admin@compania.com" required autocomplete="email">
                        </div>
                    </div>
                    <div class="field">
                        <label for="password">Contraseña</label>
                        <div class="input-wrapper">
                            <span class="input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
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

                <?php $googleSettings = $configData['access']['google_workspace'] ?? []; ?>
                <?php if ((bool) ($googleSettings['enabled'] ?? false)): ?>
                    <div class="field" style="margin-top:8px; border-top:1px solid var(--border); padding-top:14px;">
                        <label for="google_email">Acceso Google Workspace</label>
                        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/login/google" class="form" style="padding:0; margin-top:8px;" data-google-login-form>
                            <div class="input-wrapper">
                                <span class="input-icon" aria-hidden="true">G</span>
                                <input type="email" name="google_email" id="google_email" placeholder="usuario@<?= htmlspecialchars($googleSettings['corporate_domain'] ?? 'aossas.com') ?>" autocomplete="email" required>
                            </div>
                            <button type="submit" class="primary-btn" style="margin-top:10px;">
                                <span class="btn-text">Ingresar con Google</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
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
