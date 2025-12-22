<?php
$config = require __DIR__ . '/../../config.php';
$appName = $config['app']['name'];
$theme = $config['theme'];
$basePath = '/project/public';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = str_starts_with($requestPath, $basePath)
    ? (substr($requestPath, strlen($basePath)) ?: '/')
    : $requestPath;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? $appName) ?></title>
    <style>
        :root {
            --primary: <?= htmlspecialchars($theme['primary']) ?>;
            --secondary: <?= htmlspecialchars($theme['secondary']) ?>;
            --accent: <?= htmlspecialchars($theme['accent']) ?>;
            --background: <?= htmlspecialchars($theme['background']) ?>;
            --surface: <?= htmlspecialchars($theme['surface'] ?? $theme['background']) ?>;
            --font-family: <?= htmlspecialchars($theme['font_family'] ?? "'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif") ?>;
            --text-strong: color-mix(in srgb, var(--secondary) 80%, var(--background) 20%);
            --text: color-mix(in srgb, var(--secondary) 65%, var(--background) 35%);
            --muted: color-mix(in srgb, var(--secondary) 45%, var(--background) 55%);
            --border: color-mix(in srgb, var(--secondary) 14%, var(--background) 86%);
            --on-primary: color-mix(in srgb, var(--surface) 85%, var(--secondary) 15%);
            --surface-strong: color-mix(in srgb, var(--surface) 88%, var(--background) 12%);
            --shadow-soft: 0 16px 38px color-mix(in srgb, var(--secondary) 7%, var(--background) 93%);
            --icon-bg: color-mix(in srgb, var(--primary) 12%, var(--surface) 88%);
            --icon-secondary: color-mix(in srgb, var(--secondary) 28%, var(--background) 72%);
        }
        * {
            box-sizing: border-box;
            font-family: var(--font-family);
        }
        body {
            margin: 0;
            display: flex;
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
            font-size: 15px;
            letter-spacing: -0.01em;
        }
        .sidebar {
            width: 260px;
            background: var(--surface-strong);
            color: var(--muted);
            min-height: 100vh;
            padding: 30px 20px;
            position: sticky;
            top: 0;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 18px;
            box-shadow: 0 12px 30px color-mix(in srgb, var(--secondary) 8%, var(--background) 92%);
        }
        .sidebar h1 {
            margin: 0;
            font-size: 18px;
            display:flex;
            align-items:center;
            gap:12px;
            color: var(--text-strong);
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .sidebar h1 img { height: 30px; }
        .sidebar nav { display:flex; flex-direction:column; gap:6px; }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--muted);
            text-decoration: none;
            padding: 12px 12px 12px 14px;
            border-radius: 12px;
            position: relative;
            border: 1px solid transparent;
            font-weight: 600;
            font-size: 15px;
        }
        .nav-icon {
            width: 18px;
            height: 18px;
            color: var(--icon-secondary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .nav-label { flex:1; }
        .sidebar a::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 10px;
            bottom: 10px;
            width: 3px;
            border-radius: 8px;
            background: transparent;
        }
        .sidebar a:hover {
            color: var(--text);
            background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%);
            border-color: color-mix(in srgb, var(--border) 75%, var(--surface) 25%);
        }
        .sidebar a.active {
            color: var(--text-strong);
            font-weight: 700;
            background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
            border-color: color-mix(in srgb, var(--primary) 35%, var(--border) 65%);
        }
        .sidebar a.active::before { background: var(--primary); }
        .sidebar a.active .nav-icon { color: var(--primary); }
        .topbar {
            padding: 22px 32px 18px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--surface-strong);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--secondary) 6%, var(--background) 94%);
        }
        main {
            flex: 1;
            min-height: 100vh;
            background: color-mix(in srgb, var(--background) 94%, var(--surface) 6%);
        }
        .content {
            padding: 24px 32px 48px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .page-heading h2 { margin:0 0 6px 0; font-size: 24px; color: var(--text-strong); letter-spacing: -0.015em; }
        .page-heading p { margin:0; color: var(--muted); font-size: 15px; }
        .user-chip { display:flex; flex-direction:column; gap:4px; text-align:right; color: var(--muted); }
        .user-chip strong { color: var(--text-strong); font-size: 15px; letter-spacing: -0.01em; }
        .user-chip .text-muted { font-size: 13px; }
        .section-grid { display:grid; gap:20px; align-items:start; }
        .section-grid.twothirds { grid-template-columns: 3fr 2fr; }
        .section-grid.wide { grid-template-columns: 2fr 3fr; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        .grid.tight { gap: 10px; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-soft);
        }
        .card.ghosted { background: transparent; border-style: dashed; box-shadow: none; }
        .kpi { display: flex; align-items: center; gap: 14px; }
        .kpi.column { align-items: flex-start; }
        .kpi .label { color: var(--muted); font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi .value { font-weight: 800; font-size: 30px; color: var(--text-strong); letter-spacing: -0.02em; }
        .kpi .meta { color: var(--muted); font-size: 14px; }
        .kpi .title { font-size: 16px; color: var(--text-strong); font-weight: 700; }
        .card-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--icon-bg);
            color: var(--primary);
            flex-shrink: 0;
        }
        .card-icon.neutral { color: var(--icon-secondary); background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%);
            color: var(--text-strong);
            border: 1px solid var(--border);
        }
        .badge.success { background: color-mix(in srgb, var(--primary) 12%, var(--surface) 88%); color: var(--text-strong); }
        .badge.danger { background: color-mix(in srgb, var(--accent) 15%, var(--surface) 85%); color: var(--text-strong); }
        .section-title { font-size: 18px; margin:0; color: var(--text-strong); letter-spacing: -0.01em; }
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
        }
        th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--muted);
            font-weight: 700;
            background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
        }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 12px; flex-wrap: wrap; }
        .toolbar .title-stack { display:flex; align-items:center; gap:10px; }
        .btn {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            cursor: pointer;
            font-weight: 600;
            background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
            color: var(--text-strong);
            transition: background-color 120ms ease, border-color 120ms ease;
        }
        .btn.primary { background: var(--primary); color: var(--on-primary); border-color: color-mix(in srgb, var(--primary) 70%, var(--border) 30%); }
        .btn.secondary { background: var(--surface); }
        .btn.ghost { background: transparent; border-style: dashed; }
        .btn:hover { background: color-mix(in srgb, var(--primary) 8%, var(--surface) 92%); border-color: color-mix(in srgb, var(--primary) 25%, var(--border) 75%); }
        form.inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        input, select, textarea {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            width: 100%;
            background: var(--surface);
            color: var(--text-strong);
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: color-mix(in srgb, var(--primary) 50%, var(--border) 50%); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, var(--background) 82%); }
        textarea { resize: vertical; }
        label { font-weight: 600; color: var(--text-strong); display:block; margin-bottom:6px; }
        .hint { color: var(--muted); font-size: 13px; }
        .kanban { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .column { background: var(--surface); padding: 14px; border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--shadow-soft); }
        .column h3 { margin: 0 0 10px 0; font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .card-task { background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%); border-radius: 10px; padding: 12px; border: 1px solid var(--border); margin-bottom: 10px; }
        .pill { border-radius: 999px; padding: 6px 12px; font-size: 11px; font-weight: 600; background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); color: var(--text-strong); border: 1px solid var(--border); }
        .pillset { display:flex; gap:6px; flex-wrap:wrap; }
        .text-muted { color: var(--muted); }
        .alert { padding:12px; border-radius: 12px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%); color: var(--text-strong); }
        .table-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .chip-list { display:flex; flex-wrap:wrap; gap:8px; }
        .table-wrapper { overflow-x: auto; }
        .pill-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
            display: inline-block;
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 16%, var(--surface) 84%);
        }
        .card-group { display:flex; flex-direction:column; gap:8px; }
        .mini-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; }
        @media (max-width: 1180px) {
            .section-grid.twothirds, .section-grid.wide { grid-template-columns: 1fr; }
        }
        @media (max-width: 1024px) {
            body { flex-direction: column; }
            .sidebar { position: relative; width: 100%; flex-direction: column; gap:14px; min-height: unset; border-bottom: 1px solid var(--border); border-right: none; padding: 16px 18px; }
            .sidebar h1 { font-size: 16px; width: 100%; }
            .sidebar nav { flex-direction: column; gap: 8px; width: 100%; display: none; }
            .sidebar nav.open { display:flex; }
            .sidebar a { flex: 1 1 auto; border-radius: 10px; padding-inline: 12px; }
            .sidebar a::before { display: none; }
            .sidebar a.active { border-color: color-mix(in srgb, var(--primary) 25%, var(--border) 75%); }
            main { width: 100%; }
            .content { padding: 18px; }
            .topbar { position: relative; border-radius: 0; }
            .menu-toggle { display:flex; align-items:center; gap:10px; cursor:pointer; color: var(--text-strong); font-weight:700; padding: 10px 12px; border:1px solid var(--border); border-radius: 10px; background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); }
        }
        @media (max-width: 720px) {
            .topbar { flex-direction: column; align-items: flex-start; gap:12px; padding-inline: 18px; }
            .user-chip { width: 100%; text-align:left; }
            .toolbar { flex-direction: column; align-items: flex-start; }
            .sidebar { padding-inline: 14px; }
            .page-heading h2 { font-size: 20px; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <h1>
            <?php if(!empty($theme['logo'])): ?>
                <img src="<?= htmlspecialchars($theme['logo']) ?>" alt="Logo" onerror="this.style.display='none'">
            <?php endif; ?>
            <?= htmlspecialchars($appName) ?>
        </h1>
        <button class="menu-toggle" id="menuToggle" type="button" aria-expanded="false" aria-controls="sidebarNav" style="display:none;">
            <span class="nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </span>
            Menú
        </button>
        <nav id="sidebarNav" class="open">
            <a href="<?= $basePath ?>/dashboard" class="<?= ($normalizedPath === '/dashboard' || $normalizedPath === '/') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-5H10v5H5a1 1 0 0 1-1-1z"></path>
                    </svg>
                </span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?= $basePath ?>/clients" class="<?= str_starts_with($normalizedPath, '/clients') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4"></path>
                        <path d="M5 20a7 7 0 0 1 14 0"></path>
                    </svg>
                </span>
                <span class="nav-label">Clientes</span>
            </a>
            <a href="<?= $basePath ?>/projects" class="<?= str_starts_with($normalizedPath, '/projects') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16v6H4z"></path>
                        <path d="M4 14h9v6H4z"></path>
                        <path d="M15 14h5v6h-5z"></path>
                    </svg>
                </span>
                <span class="nav-label">Proyectos</span>
            </a>
            <a href="<?= $basePath ?>/tasks" class="<?= str_starts_with($normalizedPath, '/tasks') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 6h12"></path>
                        <path d="m4 6 1.5 1.5L8 5"></path>
                        <path d="M8 12h12"></path>
                        <path d="m4 12 1.5 1.5L8 11"></path>
                        <path d="M8 18h12"></path>
                        <path d="m4 18 1.5 1.5L8 17"></path>
                    </svg>
                </span>
                <span class="nav-label">Tareas / Kanban</span>
            </a>
            <a href="<?= $basePath ?>/talents" class="<?= str_starts_with($normalizedPath, '/talents') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="6.5" r="2.5"></circle>
                        <path d="M5 20a7 7 0 0 1 14 0"></path>
                        <path d="M6 8V5l-2 2.5"></path>
                    </svg>
                </span>
                <span class="nav-label">Talento</span>
            </a>
            <a href="<?= $basePath ?>/timesheets" class="<?= str_starts_with($normalizedPath, '/timesheets') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="13" r="7"></circle>
                        <path d="M12 10v4l2 2"></path>
                        <path d="M9 3h6M9 6h6"></path>
                    </svg>
                </span>
                <span class="nav-label">Timesheet</span>
            </a>
            <?php if(in_array($user['role'] ?? '', ['Administrador', 'PMO'], true)): ?>
                <a href="<?= $basePath ?>/config" class="<?= str_starts_with($normalizedPath, '/config') ? 'active' : '' ?>">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 15.5a3.5 3.5 0 1 1 3.5-3.5A3.5 3.5 0 0 1 12 15.5Z"></path>
                            <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.05.06a2 2 0 0 1-2.83 2.83l-.06-.05A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .3 1.7 1.7 0 0 0-.71 1.39V21a2 2 0 0 1-4 0v-.09a1.7 1.7 0 0 0-.71-1.39 1.7 1.7 0 0 0-1-.3 1.7 1.7 0 0 0-1.87.34l-.06.05A2 2 0 1 1 1 17.78l.05-.06A1.7 1.7 0 0 0 1.4 15a1.7 1.7 0 0 0-.3-1 1.7 1.7 0 0 0-1.39-.71H-.3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.39-.71 1.7 1.7 0 0 0 .3-1A1.7 1.7 0 0 0 1.1 6.13L1 6.07A2 2 0 0 1 3.83 3.24l.06.05A1.7 1.7 0 0 0 5.76 4a1.7 1.7 0 0 0 1-.3 1.7 1.7 0 0 0 .71-1.39V2.1a2 2 0 0 1 4 0v.09a1.7 1.7 0 0 0 .71 1.39 1.7 1.7 0 0 0 1 .3 1.7 1.7 0 0 0 1.87-.34l.06-.05A2 2 0 0 1 22.93 6l-.05.06A1.7 1.7 0 0 0 23 7.97a1.7 1.7 0 0 0 1.39.71H24.3a2 2 0 0 1 0 4h-.09a1.7 1.7 0 0 0-1.39.71 1.7 1.7 0 0 0-.3 1Z"></path>
                        </svg>
                    </span>
                    <span class="nav-label">Configuración</span>
                </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/logout">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 17 20 12 15 7"></path>
                        <path d="M20 12H9"></path>
                        <path d="M11 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6"></path>
                    </svg>
                </span>
                <span class="nav-label">Salir</span>
            </a>
        </nav>
        <script>
            const menuToggle = document.getElementById('menuToggle');
            const sidebarNav = document.getElementById('sidebarNav');
            const isMobile = window.matchMedia('(max-width: 1024px)');
            const syncMenuState = () => {
                const active = !isMobile.matches;
                sidebarNav.classList.toggle('open', active);
                menuToggle.style.display = isMobile.matches ? 'inline-flex' : 'none';
                menuToggle.setAttribute('aria-expanded', String(active));
            };
            syncMenuState();
            isMobile.addEventListener('change', syncMenuState);
            menuToggle.addEventListener('click', () => {
                const isOpen = sidebarNav.classList.toggle('open');
                menuToggle.setAttribute('aria-expanded', String(isOpen));
            });
        </script>
    </aside>
    <main>
        <header class="topbar">
            <div class="page-heading">
                <h2><?= htmlspecialchars($title ?? 'Panel') ?></h2>
                <p>Operaciones críticas del portafolio</p>
            </div>
            <?php if(isset($user)): ?>
                <div class="user-chip">
                    <strong><?= htmlspecialchars($user['name'] ?? '') ?></strong>
                    <div class="text-muted">Rol: <?= htmlspecialchars($user['role'] ?? '') ?></div>
                </div>
            <?php endif; ?>
        </header>
        <div class="content">
