<?php
$theme = $theme ?? (new ThemeRepository())->getActiveTheme();
$timesheetsEnabled = $timesheetsEnabled ?? false;
$basePath = '';
$appDisplayName = $appName ?? 'PMO';
$logoUrl = !empty($theme['logo_url']) ? $theme['logo_url'] : '';
$logoCss = $logoUrl !== '' ? "url('{$logoUrl}')" : 'none';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestQuery = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY) ?: '';
$queryParams = [];
parse_str($requestQuery, $queryParams);
$normalizedPath = str_starts_with($requestPath, $basePath)
    ? (substr($requestPath, strlen($basePath)) ?: '/')
    : $requestPath;
$isTalentCapacityRoute = str_starts_with($normalizedPath, '/talent-capacity');
$isCapacitySimulationRoute = str_starts_with($normalizedPath, '/talent-capacity/simulation')
    || ($isTalentCapacityRoute && (($queryParams['tab'] ?? '') === 'simulation'));
$isCapacityOverviewRoute = $isTalentCapacityRoute && !$isCapacitySimulationRoute;
$isTalentAbsencesRoute = str_starts_with($normalizedPath, '/talent-absences');
$absencesEnabled = $auth->isAbsencesEnabled();
$canViewAbsences = $auth->canViewTalentAbsences();
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
require_once __DIR__ . '/logo_helper.php';
error_log(sprintf(
    'Theme active: primary=%s secondary=%s logo_url=%s',
    (string) ($theme['primary'] ?? ''),
    (string) ($theme['secondary'] ?? ''),
    (string) ($theme['logo_url'] ?? '')
));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? $appName) ?></title>
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
    <link rel="stylesheet" href="<?= $basePath ?>/design/global-theme.css">

</head>
<body>
    <aside class="sidebar">
        <div class="brand-box" title="<?= htmlspecialchars($appDisplayName) ?>">
            <div class="brand-mark" aria-hidden="true">
                <?php render_brand_logo($logoUrl, $appDisplayName, 'brand-logo', 'brand-fallback'); ?>
            </div>
            <span class="brand-name"><?= htmlspecialchars($appDisplayName) ?></span>
        </div>
        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Colapsar menú">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                <path d="M8 4h12M8 12h12M8 20h12M4 4h.01M4 12h.01M4 20h.01" />
            </svg>
        </button>
        <div class="user-panel">
            <div class="avatar"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?></div>
            <div class="user-meta">
                <strong><?= htmlspecialchars($user['name'] ?? 'Usuario') ?></strong>
                <small><?= htmlspecialchars($user['email'] ?? 'usuario@correo.com') ?></small>
            </div>
        </div>
        <div class="menu-toggle">
            <span>Menú</span>
            <label for="menu-toggle" aria-label="Alternar menú">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </label>
        </div>
        <input type="checkbox" id="menu-toggle" hidden>
        <h3 class="nav-title">Navegación</h3>
        <nav>
            <span class="nav-section-label">Operación</span>
            <a href="<?= $basePath ?>/dashboard" class="nav-link <?= ($normalizedPath === '/dashboard' || $normalizedPath === '/') ? 'active' : '' ?>" data-tone="operation">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 13h7v8H3z"/><path d="M14 3h7v18h-7z"/><path d="M3 3h7v6H3z"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?= $basePath ?>/projects" class="nav-link <?= str_starts_with($normalizedPath, '/projects') ? 'active' : '' ?>" data-tone="operation">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8.5a2.5 2.5 0 0 1-2.5 2.5H5.5A2.5 2.5 0 0 1 3 17.5z"/><path d="M3 10h18"/></svg></span>
                <span class="nav-label">Proyectos</span>
            </a>
            <a href="<?= $basePath ?>/clients" class="nav-link <?= str_starts_with($normalizedPath, '/clients') ? 'active' : '' ?>" data-tone="operation">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/><path d="M16 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3Z"/><path d="M2 20a6 6 0 0 1 12 0"/><path d="M13 20a5 5 0 0 1 9 0"/></svg></span>
                <span class="nav-label">Clientes</span>
            </a>

            <div class="nav-divider" aria-hidden="true"></div>
            <span class="nav-section-label">Gestión</span>
            <a href="<?= $basePath ?>/outsourcing" class="nav-link <?= str_starts_with($normalizedPath, '/outsourcing') ? 'active' : '' ?>" data-tone="management">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 8h16"/><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M10 13h4"/></svg></span>
                <span class="nav-label">Outsourcing</span>
            </a>
            <a href="<?= $basePath ?>/approvals" class="nav-link <?= str_starts_with($normalizedPath, '/approvals') ? 'active' : '' ?>" data-tone="management">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 5.3 3.4 8.8 7 10 3.6-1.2 7-4.7 7-10V6z"/><path d="m9 12 2 2 4-4"/></svg></span>
                <span class="nav-label">Aprobaciones</span>
                <?php if (!empty($approvalBadgeCount)): ?>
                    <span class="nav-badge" aria-label="Aprobaciones pendientes"><?= (int) $approvalBadgeCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $basePath ?>/tasks" class="nav-link <?= str_starts_with($normalizedPath, '/tasks') ? 'active' : '' ?>" data-tone="management">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h7"/><path d="M9 12h7"/><path d="M9 16h5"/><path d="m6.5 8 .5.5 1-1"/><path d="m6.5 12 .5.5 1-1"/><path d="m6.5 16 .5.5 1-1"/></svg></span>
                <span class="nav-label">Tareas</span>
            </a>
            <a href="<?= $basePath ?>/tasks/kanban" class="nav-link <?= str_starts_with($normalizedPath, '/tasks/kanban') ? 'active' : '' ?>" data-tone="management">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="6" height="16" rx="1.5"/><rect x="10" y="7" width="6" height="13" rx="1.5"/><rect x="17" y="10" width="4" height="10" rx="1.5"/></svg></span>
                <span class="nav-label">Kanban</span>
            </a>
            <?php if ($timesheetsEnabled): ?>
                <a href="<?= $basePath ?>/timesheets" class="nav-link <?= str_starts_with($normalizedPath, '/timesheets') ? 'active' : '' ?>" data-tone="management">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 13V9"/><path d="m12 13 3 2"/><path d="M9 3h6"/><path d="M12 3v2"/></svg></span>
                    <span class="nav-label">Timesheet</span>
                    <?php if (!empty($timesheetPendingCount)): ?>
                        <span class="nav-badge" aria-label="Timesheets pendientes"><?= (int) $timesheetPendingCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/talents" class="nav-link <?= str_starts_with($normalizedPath, '/talents') ? 'active' : '' ?>" data-tone="management">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 11a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 9 11Z"/><path d="M16.5 10a2.5 2.5 0 1 0-2.5-2.5A2.5 2.5 0 0 0 16.5 10Z"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M13 20a4.5 4.5 0 0 1 8 0"/></svg></span>
                <span class="nav-label">Talento</span>
            </a>
            <div class="nav-group">
                <a href="<?= $basePath ?>/talent-capacity" class="nav-link <?= $isTalentCapacityRoute ? 'active' : '' ?>" data-tone="management">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg></span>
                    <span class="nav-label">Carga talento</span>
                </a>
                <div class="nav-submenu">
                    <a href="<?= $basePath ?>/talent-capacity" class="nav-sublink <?= $isCapacityOverviewRoute ? 'active' : '' ?>">Vista de capacidad</a>
                    <a href="<?= $basePath ?>/talent-capacity/simulation" class="nav-sublink <?= $isCapacitySimulationRoute ? 'active' : '' ?>">Simulación de capacidad</a>
                    <?php if ($absencesEnabled && $canViewAbsences): ?>
                        <a href="<?= $basePath ?>/talent-absences" class="nav-sublink <?= $isTalentAbsencesRoute ? 'active' : '' ?>">Ausencias</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($auth->can('pmo_decision_center_view') || $auth->can('pmo.organizations.manage') || $auth->can('pmo.technologies.manage')): ?>
                <div class="nav-divider" aria-hidden="true"></div>
                <span class="nav-section-label">PMO</span>
                <?php if ($auth->can('pmo_decision_center_view')): ?>
                <a href="<?= $basePath ?>/pmo/decision-center" class="nav-link <?= str_starts_with($normalizedPath, '/pmo/decision-center') ? 'active' : '' ?>" data-tone="pmo">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19h16"/><path d="M7 15V9"/><path d="M12 15V5"/><path d="M17 15v-3"/><path d="M4 9h3"/><path d="M10 11h4"/><path d="M16 7h4"/></svg></span>
                    <span class="nav-label">Centro de decisiones</span>
                </a>
                <a href="<?= $basePath ?>/pmo/gantt-global" class="nav-link <?= str_starts_with($normalizedPath, '/pmo/gantt-global') ? 'active' : '' ?>" data-tone="pmo">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 19h18"/><rect x="4" y="7" width="6" height="4" rx="1"/><rect x="11" y="10" width="6" height="4" rx="1"/><circle cx="20" cy="8" r="2"/></svg></span>
                    <span class="nav-label">Gantt global</span>
                </a>
                <?php endif; ?>
                <?php if ($auth->can('pmo.organizations.manage')): ?>
                <a href="<?= $basePath ?>/organizations" class="nav-link <?= str_starts_with($normalizedPath, '/organizations') ? 'active' : '' ?>" data-tone="pmo">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V8l7-4 7 4v13"/><path d="M9 21v-6h6v6"/></svg></span>
                    <span class="nav-label">Organizaciones</span>
                </a>
                <?php endif; ?>
                <?php if ($auth->can('pmo.technologies.manage')): ?>
                <a href="<?= $basePath ?>/technologies" class="nav-link <?= str_starts_with($normalizedPath, '/technologies') ? 'active' : '' ?>" data-tone="pmo">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6"/><path d="M9 12h6"/><path d="M9 15h3"/></svg></span>
                    <span class="nav-label">Tecnologías</span>
                </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if(in_array($user['role'] ?? '', ['Administrador', 'PMO'], true)): ?>
                <div class="nav-divider" aria-hidden="true"></div>
                <span class="nav-section-label">Admin</span>
                <a href="<?= $basePath ?>/admin/timesheets" class="nav-link <?= str_starts_with($normalizedPath, '/admin/timesheets') ? 'active' : '' ?>" data-tone="admin">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 5h18"/><path d="M3 12h18"/><path d="M3 19h18"/><path d="M7 5v14"/><path d="M17 5v14"/></svg></span>
                    <span class="nav-label">Admin Timesheets</span>
                </a>
                <a href="<?= $basePath ?>/config" class="nav-link <?= str_starts_with($normalizedPath, '/config') ? 'active' : '' ?>" data-tone="admin">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.1a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.1a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.1a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6H20a2 2 0 1 1 0 4h-.1a1 1 0 0 0-.9.6"/></svg></span>
                    <span class="nav-label">Configuración</span>
                </a>
            <?php endif; ?>
            <div class="nav-divider" aria-hidden="true"></div>
            <a href="<?= $basePath ?>/logout" class="nav-link" data-tone="danger">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 4h-4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M10 12h10"/><path d="m17 8 4 4-4 4"/></svg></span>
                <span class="nav-label">Salir</span>
            </a>
        </nav>
    </aside>
    <main>
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">
                    <?php render_brand_logo($logoUrl, $appDisplayName, 'brand-logo', 'brand-fallback'); ?>
                </div>
                <div class="brand-title"><?= htmlspecialchars($appDisplayName) ?></div>
            </div>
            <div class="spacer"></div>
            <?php if(isset($user)): ?>
                <div class="user-actions">
                    <div class="user-summary">
                        <div class="avatar"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?></div>
                        <div class="user-identity">
                            <strong><?= htmlspecialchars($user['name'] ?? '') ?></strong>
                            <span class="role-badge"><?= htmlspecialchars($user['role'] ?? '') ?></span>
                        </div>
                    </div>
                    <a class="logout-btn" href="<?= $basePath ?>/logout">Salir</a>
                </div>
            <?php endif; ?>
        </header>
        <?php if ($auth->isImpersonating()): ?>
            <div class="impersonation-banner">
                <div>
                    Estás viendo el sistema como: <strong><?= htmlspecialchars($user['name'] ?? '') ?></strong>
                </div>
                <form method="POST" action="<?= $basePath ?>/impersonate/stop">
                    <button class="btn secondary" type="submit">Volver a mi sesión</button>
                </form>
            </div>
        <?php endif; ?>
        <div class="content">
            <div class="page-heading">
                <h2><?= htmlspecialchars($title ?? 'Panel') ?></h2>
                <p>Operaciones críticas de proyectos</p>
            </div>
            <script>
                (() => {
                    const sidebar = document.querySelector('.sidebar');
                    const toggle = document.querySelector('[data-sidebar-toggle]');
                    if (!sidebar || !toggle) return;
                    const stored = localStorage.getItem('pmo.sidebar.collapsed');
                    if (stored === '1') {
                        sidebar.classList.add('collapsed');
                    }
                    toggle.addEventListener('click', () => {
                        sidebar.classList.toggle('collapsed');
                        localStorage.setItem('pmo.sidebar.collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
                    });
                })();
            </script>
