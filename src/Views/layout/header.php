<?php
$theme = $theme ?? (new ThemeRepository())->getActiveTheme();
$basePath = '/project/public';
$appDisplayName = $appName ?? 'PMO';
$logoUrl = !empty($theme['logo_url']) ? $theme['logo_url'] : '';
$logoCss = $logoUrl !== '' ? "url('{$logoUrl}')" : 'none';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = str_starts_with($requestPath, $basePath)
    ? (substr($requestPath, strlen($basePath)) ?: '/')
    : $requestPath;
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
    <style>
        :root {
            --primary: <?= htmlspecialchars($theme['primary'] ?? '#2563eb') ?>;
            --secondary: <?= htmlspecialchars($theme['secondary'] ?? '#111827') ?>;
            --accent: <?= htmlspecialchars($theme['accent'] ?? ($theme['primary'] ?? '#2563eb')) ?>;
            --bg-app: <?= htmlspecialchars($theme['background'] ?? '#f3f4f6') ?>;
            --bg-card: <?= htmlspecialchars($theme['surface'] ?? '#ffffff') ?>;
            --text-main: <?= htmlspecialchars($theme['text_main'] ?? '#0f172a') ?>;
            --text-muted: <?= htmlspecialchars($theme['text_muted'] ?? '#475569') ?>;
            --text-soft: <?= htmlspecialchars($theme['text_soft'] ?? ($theme['text_disabled'] ?? '#94a3b8')) ?>;
            --text-disabled: var(--text-soft);
            --border: <?= htmlspecialchars($theme['border'] ?? '#e5e7eb') ?>;
            --font-family: <?= htmlspecialchars($theme['font_family'] ?? '"Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif') ?>;
            --logo-url: <?= htmlspecialchars($logoCss) ?>;

            --primary-hover: color-mix(in srgb, var(--primary) 86%, var(--accent) 14%);
            --primary-strong: color-mix(in srgb, var(--primary) 78%, var(--secondary) 22%);
            --bg: var(--bg-app);
            --card: var(--bg-card);
            --surface: var(--bg-card);
            --text-strong: var(--text-main);
            --text: var(--text-muted);
            --muted: var(--text-muted);
            --on-primary: color-mix(in srgb, var(--bg-card) 94%, var(--text-main) 6%);
            --success: color-mix(in srgb, var(--accent) 28%, var(--primary) 72%);
            --warning: var(--accent);
            --danger: color-mix(in srgb, var(--accent) 25%, var(--secondary) 75%);
        }
        * {
            box-sizing: border-box;
            font-family: var(--font-family);
        }
        body {
            margin: 0;
            display: flex;
            background: var(--bg-app);
            color: var(--text-main);
            min-height: 100vh;
            font-weight: 500;
        }
        .sidebar {
            width: 280px;
            background: var(--secondary);
            color: var(--text-main);
            min-height: 100vh;
            padding: 20px 18px;
            position: sticky;
            top: 0;
            border-right: 1px solid color-mix(in srgb, var(--border) 70%, transparent);
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .sidebar.collapsed { width: 88px; }
        .sidebar.collapsed .brand-name,
        .sidebar.collapsed .user-meta,
        .sidebar.collapsed .nav-title,
        .sidebar.collapsed .nav-label,
        .sidebar.collapsed .nav-section-label,
        .sidebar.collapsed .nav-badge { display: none; }
        .sidebar.collapsed .nav-link { justify-content: center; }
        .sidebar.collapsed .nav-link::before { display: none; }
        .sidebar.collapsed .user-panel { justify-content: center; }
        .sidebar-toggle {
            border: 1px solid color-mix(in srgb, var(--border) 70%, transparent);
            background: color-mix(in srgb, var(--bg-card) 12%, transparent);
            color: var(--text-main);
            border-radius: 10px;
            padding: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-toggle svg { width: 18px; height: 18px; stroke: currentColor; }
        .brand-box { display:flex; align-items:center; gap:10px; padding: 10px 8px; border-radius:12px; border:1px solid color-mix(in srgb, var(--border) 70%, transparent); background: color-mix(in srgb, var(--bg-card) 10%, transparent); }
        .brand-mark { display:flex; align-items:center; justify-content:center; min-width: 36px; }
        .brand-box img { height: 32px; max-height: 40px; object-fit: contain; }
        .brand-name { font-weight: 800; color: var(--text-main); font-size: 15px; }
        .brand-fallback { font-weight: 800; color: var(--text-main); font-size: 18px; letter-spacing: 0.02em; }
        .brand-fallback.is-hidden { display: none; }
        .sidebar .user-panel {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 8px;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            background: color-mix(in srgb, var(--bg-card) 12%, transparent);
            color: var(--text-main);
            border: 1px solid color-mix(in srgb, var(--border) 60%, transparent);
        }
        .user-meta { display: flex; flex-direction: column; gap: 3px; }
        .user-meta strong { color: var(--text-main); font-size: 15px; font-weight: 700; }
        .user-meta small { color: var(--text-main); font-size: 13px; font-weight: 500; }
        .nav-title {
            margin: 0;
            font-size: 13px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--text-main);
            padding-inline: 10px;
            font-weight: 600;
        }
        .nav-section-label { font-size: 11px; text-transform: uppercase; color: var(--text-main); font-weight: 800; padding-inline: 10px; letter-spacing: 0.08em; margin-top: 6px; }
        .nav-divider { height: 1px; background: color-mix(in srgb, var(--border) 65%, transparent); margin: 4px 10px; }
        .sidebar nav { display:flex; flex-direction:column; gap:12px; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-muted);
            text-decoration: none;
            padding: 14px 12px;
            border-radius: 12px;
            position: relative;
            border: 1px solid transparent;
            font-weight: 600;
            font-size: 16px;
        }
        .nav-link::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 10px;
            bottom: 10px;
            width: 3px;
            border-radius: 8px;
            background: transparent;
        }
        .nav-link:hover {
            color: var(--text-muted);
            background: color-mix(in srgb, var(--bg-card) 12%, transparent);
            border-color: color-mix(in srgb, var(--border) 70%, transparent);
        }
        .nav-link.active {
            color: var(--primary);
            font-weight: 700;
            background: color-mix(in srgb, var(--bg-card) 12%, transparent);
            border-color: color-mix(in srgb, var(--border) 80%, transparent);
        }
        .nav-link.active::before { background: var(--primary); }
        .nav-icon {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: inherit;
        }
        .nav-label { white-space: nowrap; }
        .nav-badge {
            margin-left: auto;
            min-width: 24px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: var(--on-primary);
            background: var(--accent);
            text-align: center;
        }
        .nav-icon svg { width: 100%; height: 100%; stroke: currentColor; stroke-width: 2; }
        .topbar {
            padding: 10px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .brand-logo { height: 36px; max-height: 40px; width: auto; object-fit: contain; }
        .brand-title {
            font-weight: 800;
            color: var(--text-main);
            font-size: 20px;
            white-space: nowrap;
        }
        .topbar .spacer { flex: 1; }
        .user-actions { display: flex; align-items: center; gap: 12px; }
        .user-summary { display:flex; align-items:center; gap:10px; padding:6px 0; }
        .user-identity { display:flex; flex-direction:column; gap:3px; }
        .user-identity strong { color: var(--text-main); font-size: 14px; font-weight: 700; }
        .role-badge { display:inline-flex; align-items:center; padding:6px 10px; border-radius:8px; border:1px solid var(--border); background: color-mix(in srgb, var(--primary) 12%, transparent); color: var(--primary); font-size:12px; font-weight:600; }
        .logout-btn { padding:10px 14px; border-radius:10px; border:1px solid var(--border); background: var(--bg-card); color: var(--text-main); text-decoration:none; font-weight:600; }
        .logout-btn:hover { background: color-mix(in srgb, var(--primary) 12%, transparent); border-color: color-mix(in srgb, var(--primary) 24%, transparent); color: var(--primary); }
        main {
            flex: 1;
            min-height: 100vh;
            background: var(--bg-app);
        }
        .content {
            padding: 24px 32px 48px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .page-heading h2 { margin:0 0 6px 0; font-size: 25px; color: var(--text-main); font-weight: 700; }
        .page-heading p { margin:0; color: var(--text-muted); font-weight: 500; }
        .section-grid { display:grid; gap:20px; align-items:start; }
        .section-grid.twothirds { grid-template-columns: 3fr 2fr; }
        .section-grid.wide { grid-template-columns: 2fr 3fr; }
        .config-columns { display:grid; gap:20px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); align-items:stretch; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .grid.tight { gap: 10px; }
        .config-form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; align-items:start; }
        .config-form-grid.tight { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
        .card.stretch { height:100%; display:flex; flex-direction:column; }
        .card .card-content { display:flex; flex-direction:column; gap:14px; flex:1; }
        .form-block { display:flex; flex-direction:column; gap:10px; padding:12px; border:1px solid var(--border); border-radius:10px; background: color-mix(in srgb, var(--bg-card) 90%, var(--bg-app) 10%); }
        .section-label { font-size:12px; letter-spacing:0.05em; text-transform:uppercase; color: var(--muted); font-weight:700; }
        .input-stack { display:flex; flex-direction:column; gap:8px; }
        .palette-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap:10px; }
        .option-row { display:flex; gap:12px; flex-wrap:wrap; }
        .option { display:flex; align-items:center; gap:8px; font-weight:600; color: var(--text-strong); }
        .option.compact { gap:4px; font-size:13px; }
        .form-footer { display:flex; justify-content:space-between; align-items:center; gap:12px; grid-column:1 / -1; }
        .card-stack { display:flex; flex-direction:column; gap:12px; }
        .cards-grid { align-items:stretch; }
        .preview-pane { background: color-mix(in srgb, white 12%, transparent); padding:16px; border-radius:12px; display:flex; flex-direction:column; gap:10px; }
        .preview-header { display:flex; align-items:center; gap:12px; }
        .sidebar.collapsed .brand-name { display: none; }
        .sidebar.collapsed .brand-box { justify-content: center; }
        .sidebar.collapsed .brand-mark img { height: 32px; }
        .sidebar.collapsed .brand-fallback { font-size: 16px; }
        .preview-logo { height:42px; background:var(--panel); padding:8px; border-radius:10px; box-shadow:0 8px 20px var(--glow); }
        .preview-subtitle { color: color-mix(in srgb, white 80%, transparent); font-size:13px; }
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--text-main) 12%, transparent);
        }
        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: color-mix(in srgb, var(--bg-card) 92%, var(--bg-app) 8%);
            color: var(--text-main);
        }
        .alert.error {
            border-color: rgb(248, 113, 113);
            background: rgb(254, 226, 226);
            color: rgb(153, 27, 27);
        }
        .card.ghosted { background: transparent; border-style: dashed; }
        .kpi { display: flex; align-items: center; gap: 14px; }
        .kpi .label { color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
        .kpi .value { font-weight: 700; font-size: 34px; color: var(--text-main); }
        .kpi .meta { color: var(--text-muted); font-size: 13px; font-weight: 500; }
        .kpi-icon { width: 56px; height: 56px; border-radius: 12px; background: color-mix(in srgb, var(--primary) 16%, transparent); display: inline-flex; align-items: center; justify-content: center; color: var(--primary); }
        .kpi-icon svg { width: 32px; height: 32px; stroke: currentColor; }
        .kpi-body { display: flex; flex-direction: column; gap: 4px; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            background: color-mix(in srgb, var(--bg-card) 88%, var(--bg-app) 12%);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .badge.success { background: color-mix(in srgb, var(--success) 16%, transparent); color: var(--success); }
        .badge.danger { background: color-mix(in srgb, var(--danger) 16%, transparent); color: var(--danger); }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: transparent;
        }
        th, td {
            padding: 12px 12px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            line-height: 1.5;
            white-space: normal;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--text-muted);
            font-weight: 700;
            background: color-mix(in srgb, var(--bg-card) 90%, var(--bg-app) 10%);
        }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: color-mix(in srgb, var(--bg-card) 84%, var(--bg-app) 16%); }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 12px; flex-wrap: wrap; }
        .btn {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            cursor: pointer;
            font-weight: 600;
            background: var(--bg-card);
            color: var(--text-main);
            transition: background-color 120ms ease, border-color 120ms ease, color 120ms ease;
        }
        .btn.primary { background: var(--primary); color: var(--on-primary); border-color: var(--primary); }
        .btn.secondary { background: var(--card); }
        .btn.ghost { background: transparent; border-style: dashed; }
        .btn:hover { background: var(--primary-hover); border-color: var(--primary-hover); color: var(--on-primary); }
        .btn:active { background: var(--primary-strong); border-color: var(--primary-strong); color: var(--on-primary); }
        form.inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        input, select, textarea {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            width: 100%;
            background: var(--bg-card);
            color: var(--text-main);
            font-weight: 500;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary-hover); box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 30%, transparent); }
        textarea { resize: vertical; }
        label { font-weight: 600; color: var(--text-main); display:block; margin-bottom:6px; }
        .input { display:flex; flex-direction:column; gap:6px; }
        .input span { color: var(--text-main); font-weight:700; font-size: 14px; }
        .muted { color: var(--text-muted); font-size: 13px; }
        .hint { color: var(--text-muted); font-size: 13px; }
        .kanban { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .column { background: var(--bg-card); padding: 14px; border-radius: 12px; border: 1px solid var(--border); box-shadow: none; }
        .column h3 { margin: 0 0 10px 0; font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
        .card-task { background: color-mix(in srgb, var(--bg-card) 92%, var(--bg-app) 8%); border-radius: 10px; padding: 12px; border: 1px solid var(--border); margin-bottom: 10px; }
        .pill { border-radius: 999px; padding: 6px 12px; font-size: 11px; font-weight: 600; background: color-mix(in srgb, var(--bg-card) 90%, var(--bg-app) 10%); color: var(--text-main); border: 1px solid var(--border); }
        .pillset { display:flex; gap:6px; flex-wrap:wrap; }
        .text-muted { color: var(--text-muted); }
        .alert { padding:12px; border-radius: 12px; border:1px solid var(--border); background: color-mix(in srgb, var(--bg-card) 92%, var(--bg-app) 8%); color: var(--text-main); }
        .table-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .chip-list { display:flex; flex-wrap:wrap; gap:8px; }
        .table-wrapper { overflow-x: auto; }
        .config-flow-roles { display:flex; flex-direction:column; gap:6px; padding:8px 10px; border:1px dashed var(--border); border-radius:10px; background: var(--bg-card); }
        .config-flow-roles strong { font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); }
        @media (max-width: 1180px) {
            .section-grid.twothirds, .section-grid.wide { grid-template-columns: 1fr; }
        }
        .menu-toggle { display: none; align-items: center; gap: 10px; font-weight: 700; color: color-mix(in srgb, var(--bg-card) 90%, var(--text-main) 10%); }
        .menu-toggle label { padding: 8px 10px; border-radius: 10px; border: 1px solid color-mix(in srgb, var(--border) 70%, transparent); background: color-mix(in srgb, var(--bg-card) 12%, transparent); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; color: color-mix(in srgb, var(--bg-card) 90%, var(--text-main) 10%); }
        .menu-toggle svg { width: 18px; height: 18px; stroke: currentColor; }
        #menu-toggle { display: none; }
        @media (max-width: 1024px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: unset; border-right: none; border-bottom: 1px solid var(--border); padding: 16px 18px; gap: 14px; }
            .menu-toggle { display: flex; justify-content: space-between; align-items: center; }
            .sidebar nav { display: none; }
            #menu-toggle:checked ~ nav { display: flex; }
            .sidebar nav { flex-direction: column; }
            .nav-link { padding: 12px 10px; }
            main { width: 100%; }
            .content { padding: 18px; }
            .topbar { padding-inline: 18px; }
        }
        @media (max-width: 720px) {
            .topbar { flex-wrap: wrap; gap: 12px; }
            .user-actions { width: 100%; justify-content: space-between; }
            .user-summary { flex: 1; }
            .toolbar { flex-direction: column; align-items: flex-start; }
            .sidebar { padding-inline: 14px; }
            .page-heading h2 { font-size: 20px; }
        }
    </style>
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
            <a href="<?= $basePath ?>/dashboard" class="nav-link <?= ($normalizedPath === '/dashboard' || $normalizedPath === '/') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 13h5v7H4zM10 4h4v16h-4zM16 9h4v11h-4z"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?= $basePath ?>/projects" class="nav-link <?= str_starts_with($normalizedPath, '/projects') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 6h16v5H4zM4 13h9v5H4zM14 13l6 5"/></svg></span>
                <span class="nav-label">Proyectos</span>
            </a>
            <a href="<?= $basePath ?>/clients" class="nav-link <?= str_starts_with($normalizedPath, '/clients') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M5 7a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm8 1h6l-1 12h-4zM3 21a4 4 0 0 1 8 0"/></svg></span>
                <span class="nav-label">Clientes</span>
            </a>

            <div class="nav-divider" aria-hidden="true"></div>
            <span class="nav-section-label">Gestión</span>
            <?php if ($auth->canAccessOutsourcing()): ?>
                <a href="<?= $basePath ?>/outsourcing" class="nav-link <?= str_starts_with($normalizedPath, '/outsourcing') ? 'active' : '' ?>">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 7h16M4 12h16M4 17h10"/><path d="M16 17l2 2 4-4"/></svg></span>
                    <span class="nav-label">Outsourcing</span>
                </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/approvals" class="nav-link <?= str_starts_with($normalizedPath, '/approvals') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 12h6l2 3 4-6h4"/><path d="M5 20h14"/><path d="M7 4h10v4H7z"/></svg></span>
                <span class="nav-label">Aprobaciones</span>
                <?php if (!empty($approvalBadgeCount)): ?>
                    <span class="nav-badge" aria-label="Aprobaciones pendientes"><?= (int) $approvalBadgeCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $basePath ?>/tasks" class="nav-link <?= str_starts_with($normalizedPath, '/tasks') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M7 5h14M7 12h14M7 19h14M3 5h.01M3 12h.01M3 19h.01"/></svg></span>
                <span class="nav-label">Tareas</span>
            </a>
            <a href="<?= $basePath ?>/talents" class="nav-link <?= str_starts_with($normalizedPath, '/talents') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-6 8a6 6 0 0 1 12 0"/></svg></span>
                <span class="nav-label">Talento</span>
            </a>
            <?php if ($auth->canAccessTimesheets()): ?>
                <a href="<?= $basePath ?>/timesheets" class="nav-link <?= str_starts_with($normalizedPath, '/timesheets') ? 'active' : '' ?>">
                    <span class="nav-icon" aria-hidden="true">⏱️</span>
                    <span class="nav-label">Timesheet</span>
                </a>
            <?php endif; ?>

            <?php if(in_array($user['role'] ?? '', ['Administrador', 'PMO'], true)): ?>
                <div class="nav-divider" aria-hidden="true"></div>
                <span class="nav-section-label">Admin</span>
                <a href="<?= $basePath ?>/config" class="nav-link <?= str_starts_with($normalizedPath, '/config') ? 'active' : '' ?>">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M12 9a3 3 0 1 0 3 3 3 3 0 0 0-3-3Zm8-1-1.5-.5a2 2 0 0 1-1.3-1.3L17 4l-2-.5-1 1.8a2 2 0 0 1-1.7 1L10 6l-.5 2 1.8 1a2 2 0 0 1 1 1.7L12 14l2 .5 1-1.8a2 2 0 0 1 1.7-1l1.8-.2Z"/></svg></span>
                    <span class="nav-label">Configuración</span>
                </a>
            <?php endif; ?>
            <div class="nav-divider" aria-hidden="true"></div>
            <a href="<?= $basePath ?>/logout" class="nav-link">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M15 4h-5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5"/><path d="m10 9 3 3-3 3"/><path d="M13 12H4"/></svg></span>
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
