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
        }
        .sidebar {
            width: 280px;
            background: var(--surface-strong);
            color: var(--muted);
            min-height: 100vh;
            padding: 22px 20px;
            position: sticky;
            top: 0;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .sidebar .user-panel {
            padding: 14px 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            background: color-mix(in srgb, var(--primary) 16%, var(--surface) 84%);
            color: var(--text-strong);
            border: 1px solid color-mix(in srgb, var(--primary) 30%, var(--border) 70%);
        }
        .user-meta { display: flex; flex-direction: column; gap: 4px; }
        .user-meta strong { color: var(--text-strong); font-size: 14px; }
        .user-meta small { color: var(--muted); font-size: 13px; }
        .nav-title {
            margin: 0;
            font-size: 13px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--muted);
            padding-inline: 8px;
        }
        .sidebar nav { display:flex; flex-direction:column; gap:8px; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--muted);
            text-decoration: none;
            padding: 12px 12px;
            border-radius: 12px;
            position: relative;
            border: 1px solid transparent;
            font-weight: 600;
            font-size: 15px;
        }
        .nav-link::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 10px;
            bottom: 10px;
            width: 3px;
            border-radius: 8px;
            background: transparent;
        }
        .nav-link:hover {
            color: var(--text);
            background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%);
            border-color: color-mix(in srgb, var(--border) 75%, var(--surface) 25%);
        }
        .nav-link.active {
            color: var(--text-strong);
            font-weight: 700;
            background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
            border-color: color-mix(in srgb, var(--primary) 35%, var(--border) 65%);
        }
        .nav-link.active::before { background: var(--primary); }
        .nav-icon {
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: inherit;
        }
        .nav-icon svg { width: 100%; height: 100%; stroke: currentColor; }
        .topbar {
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .brand-logo { height: 28px; width: auto; object-fit: contain; }
        .brand-title {
            font-weight: 600;
            color: var(--text-strong);
            font-size: 16px;
            white-space: nowrap;
        }
        .topbar .spacer { flex: 1; }
        .user-actions { display: flex; align-items: center; gap: 12px; }
        .user-summary { display:flex; align-items:center; gap:10px; padding:8px 10px; border:1px solid var(--border); border-radius:12px; background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%); }
        .user-identity { display:flex; flex-direction:column; gap:4px; }
        .user-identity strong { color: var(--text-strong); font-size: 14px; }
        .role-badge { display:inline-flex; align-items:center; padding:4px 8px; border-radius:8px; border:1px solid var(--border); background: color-mix(in srgb, var(--primary) 12%, var(--surface) 88%); color: var(--text-strong); font-size:12px; font-weight:600; }
        .logout-btn { padding:10px 14px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-strong); text-decoration:none; font-weight:600; }
        .logout-btn:hover { background: color-mix(in srgb, var(--primary) 8%, var(--surface) 92%); border-color: color-mix(in srgb, var(--primary) 25%, var(--border) 75%); }
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
        .page-heading h2 { margin:0 0 6px 0; font-size: 22px; color: var(--text-strong); }
        .page-heading p { margin:0; color: var(--muted); }
        .section-grid { display:grid; gap:20px; align-items:start; }
        .section-grid.twothirds { grid-template-columns: 3fr 2fr; }
        .section-grid.wide { grid-template-columns: 2fr 3fr; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .grid.tight { gap: 10px; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            box-shadow: var(--shadow-soft);
        }
        .card.ghosted { background: transparent; border-style: dashed; box-shadow: none; }
        .kpi { display: flex; flex-direction: column; gap: 6px; }
        .kpi .label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi .value { font-weight: 700; font-size: 28px; color: var(--text-strong); }
        .kpi .meta { color: var(--muted); font-size: 13px; }
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
        @media (max-width: 1180px) {
            .section-grid.twothirds, .section-grid.wide { grid-template-columns: 1fr; }
        }
        .menu-toggle { display: none; align-items: center; gap: 10px; font-weight: 700; color: var(--text-strong); }
        .menu-toggle label { padding: 8px 10px; border-radius: 10px; border: 1px solid var(--border); background: var(--surface); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
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
            <a href="<?= $basePath ?>/dashboard" class="nav-link <?= ($normalizedPath === '/dashboard' || $normalizedPath === '/') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 13h5v7H4zM10 4h4v16h-4zM16 9h4v11h-4z"/></svg></span>
                <span>Dashboard Ejecutivo</span>
            </a>
            <a href="<?= $basePath ?>/clients" class="nav-link <?= str_starts_with($normalizedPath, '/clients') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M5 7a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm8 1h6l-1 12h-4zM3 21a4 4 0 0 1 8 0"/></svg></span>
                <span>Clientes</span>
            </a>
            <a href="<?= $basePath ?>/projects" class="nav-link <?= str_starts_with($normalizedPath, '/projects') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 6h16v5H4zM4 13h9v5H4zM14 13l6 5"/></svg></span>
                <span>Proyectos</span>
            </a>
            <a href="<?= $basePath ?>/tasks" class="nav-link <?= str_starts_with($normalizedPath, '/tasks') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M7 5h14M7 12h14M7 19h14M3 5h.01M3 12h.01M3 19h.01"/></svg></span>
                <span>Tareas / Kanban</span>
            </a>
            <a href="<?= $basePath ?>/talents" class="nav-link <?= str_starts_with($normalizedPath, '/talents') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-6 8a6 6 0 0 1 12 0"/></svg></span>
                <span>Talento</span>
            </a>
            <a href="<?= $basePath ?>/timesheets" class="nav-link <?= str_starts_with($normalizedPath, '/timesheets') ? 'active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M7 4h10v2H7zM5 7h14v13H5zM12 11v5m-3-3h6"/></svg></span>
                <span>Timesheet</span>
            </a>
            <?php if(in_array($user['role'] ?? '', ['Administrador', 'PMO'], true)): ?>
                <a href="<?= $basePath ?>/config" class="nav-link <?= str_starts_with($normalizedPath, '/config') ? 'active' : '' ?>">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M12 9a3 3 0 1 0 3 3 3 3 0 0 0-3-3Zm8-1-1.5-.5a2 2 0 0 1-1.3-1.3L17 4l-2-.5-1 1.8a2 2 0 0 1-1.7 1L10 6l-.5 2 1.8 1a2 2 0 0 1 1 1.7L12 14l2 .5 1-1.8a2 2 0 0 1 1.7-1l1.8-.2Z"/></svg></span>
                    <span>Gestión de Configuración</span>
                </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/logout" class="nav-link">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M15 4h-5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5"/><path d="m10 9 3 3-3 3"/><path d="M13 12H4"/></svg></span>
                <span>Salir</span>
            </a>
        </nav>
    </aside>
    <main>
        <header class="topbar">
            <div class="brand">
                <?php if(!empty($theme['logo'])): ?>
                    <img src="<?= htmlspecialchars($theme['logo']) ?>" alt="Logo AOS" class="brand-logo" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="brand-title">Sistema de Gestión de Proyectos</div>
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
                <p>Operaciones críticas del portafolio</p>
            </div>
