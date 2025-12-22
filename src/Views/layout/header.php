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
            --text: color-mix(in srgb, var(--secondary) 70%, var(--background) 30%);
            --muted: color-mix(in srgb, var(--text) 60%, var(--background) 40%);
            --panel: color-mix(in srgb, var(--surface) 85%, var(--background) 15%);
            --border: color-mix(in srgb, var(--surface) 55%, var(--background) 45%);
            --positive: color-mix(in srgb, var(--primary) 52%, var(--accent) 48%);
            --warning: color-mix(in srgb, var(--accent) 60%, var(--secondary) 40%);
            --danger: color-mix(in srgb, var(--accent) 55%, var(--secondary) 45%);
        }
        * {
            box-sizing: border-box;
            font-family: var(--font-family);
        }
        body {
            margin: 0;
            display: flex;
            background: color-mix(in srgb, var(--background) 96%, var(--surface) 4%);
            color: var(--text);
        }
        .sidebar {
            width: 240px;
            background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
            color: var(--muted);
            min-height: 100vh;
            padding: 32px 20px;
            position: sticky;
            top: 0;
            border-right: 1px solid var(--border);
        }
        .sidebar h1 {
            margin: 0 0 24px 0;
            font-size: 18px;
            display:flex;
            align-items:center;
            gap:10px;
            letter-spacing: 0.02em;
            color: var(--text);
            font-weight: 700;
        }
        .sidebar h1 img {
            height: 30px;
            border-radius: 8px;
            background: color-mix(in srgb, var(--surface) 90%, transparent);
            padding:6px;
        }
        .sidebar nav {
            display:flex;
            flex-direction:column;
            gap:4px;
        }
        .sidebar a {
            display: block;
            color: var(--muted);
            text-decoration: none;
            padding: 10px 4px;
            border-radius: 6px;
            font-weight: 600;
            letter-spacing: 0.01em;
            border-left: 2px solid transparent;
        }
        .sidebar a:hover { color: var(--text); }
        .sidebar a.active {
            color: var(--text);
            border-left-color: var(--primary);
            font-weight: 700;
        }
        .topbar {
            padding: 22px 28px 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: sticky;
            top: 0;
            z-index: 10;
            background: color-mix(in srgb, var(--background) 98%, var(--surface) 2%);
            border-bottom: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
        }
        main {
            flex: 1;
            min-height: 100vh;
        }
        .content {
            padding: 12px 28px 40px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .page-heading h2 {
            margin:0 0 4px 0;
            font-size: 22px;
            letter-spacing: 0.01em;
        }
        .page-heading p {
            margin:0;
            color: var(--muted);
        }
        .user-chip {
            display:flex;
            flex-direction:column;
            gap:2px;
            text-align:right;
            padding: 8px 0;
            color: var(--muted);
        }
        .card {
            background: color-mix(in srgb, var(--surface) 96%, var(--background) 4%);
            border-radius: 12px;
            padding: 18px;
            border: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
        }
        .card.ghosted {
            background: transparent;
            border-style: dashed;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
        }
        .grid.tight { gap: 10px; }
        .kpi {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .kpi .label {
            color: var(--muted);
            font-size: 12px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .kpi .value {
            font-weight: 800;
            font-size: 30px;
            letter-spacing: -0.01em;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            background: transparent;
            color: var(--muted);
            border: 1px solid color-mix(in srgb, var(--border) 70%, transparent);
        }
        .badge.success { color: var(--positive); border-color: color-mix(in srgb, var(--positive) 40%, var(--border) 60%); }
        .badge.warning { color: var(--warning); border-color: color-mix(in srgb, var(--warning) 40%, var(--border) 60%); }
        .badge.danger { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 40%, var(--border) 60%); }
        .badge.neutral { color: color-mix(in srgb, var(--accent) 70%, var(--secondary) 30%); }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            background: transparent;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
            text-align: left;
        }
        th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            font-weight: 700;
            background: transparent;
        }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: color-mix(in srgb, var(--surface) 20%, transparent); }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            gap: 10px;
        }
        .btn {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid color-mix(in srgb, var(--border) 70%, transparent);
            cursor: pointer;
            font-weight: 700;
            letter-spacing: 0.01em;
            background: transparent;
            color: var(--text);
            transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        }
        .btn.primary {
            background: color-mix(in srgb, var(--primary) 85%, var(--secondary) 15%);
            color: white;
            border-color: color-mix(in srgb, var(--primary) 55%, transparent);
        }
        .btn.secondary {
            background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%);
        }
        .btn.ghost {
            background: transparent;
            border-style: dashed;
        }
        .btn:hover { background: color-mix(in srgb, var(--surface) 92%, transparent); }
        form.inline { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        input, select, textarea {
            padding: 10px 12px;
            border: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
            border-radius: 10px;
            width: 100%;
            background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%);
            color: var(--text);
        }
        input[type="file"] {
            border-style: dashed;
        }
        textarea { resize: vertical; }
        label { font-weight: 700; color: var(--text); display:block; margin-bottom:4px; }
        .hint { color: var(--muted); font-size: 13px; }
        .kanban { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
        .column { background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%); padding: 12px; border-radius: 12px; border: 1px solid color-mix(in srgb, var(--border) 80%, transparent); }
        .column h3 { margin: 0 0 8px 0; font-size: 13px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.03em; }
        .card-task { background: transparent; border-radius: 10px; padding: 12px; border: 1px solid color-mix(in srgb, var(--border) 80%, transparent); margin-bottom: 8px; }
        .pill { border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 700; background: transparent; color: var(--text); border: 1px solid color-mix(in srgb, var(--border) 70%, transparent); }
        .pill.high { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 40%, var(--border) 60%); }
        .pill.medium { color: var(--warning); border-color: color-mix(in srgb, var(--warning) 40%, var(--border) 60%); }
        .pill.low { color: var(--positive); border-color: color-mix(in srgb, var(--positive) 40%, var(--border) 60%); }
        .section-grid { display:grid; gap:16px; align-items:start; }
        .section-grid.twothirds { grid-template-columns: 3fr 2fr; }
        .section-grid.wide { grid-template-columns: 2fr 3fr; }
        .card-overlay { position:relative; overflow:hidden; }
        .card-overlay::after { content:''; position:absolute; inset:10px; border-radius:12px; border:1px solid color-mix(in srgb, var(--panel) 26%, transparent); opacity:0.5; }
        .stack { display:flex; flex-direction:column; gap:10px; }
        .pillset { display:flex; gap:6px; flex-wrap:wrap; }
        .text-muted { color: var(--muted); }
        .alert { padding:12px; border-radius: 12px; border:1px solid color-mix(in srgb, var(--border) 80%, transparent); background: color-mix(in srgb, var(--surface) 95%, var(--background) 5%); color: var(--text); }
        .alert.success { color: var(--positive); border-color: color-mix(in srgb, var(--positive) 40%, var(--border) 60%); }
        .table-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .chip-list { display:flex; flex-wrap:wrap; gap:8px; }
        .subtle-card { background: transparent; border: 1px dashed color-mix(in srgb, var(--border) 70%, transparent); box-shadow: none; }
        .table-wrapper { overflow-x: auto; }
        @media (max-width: 1024px) {
            body { flex-direction: column; }
            .sidebar { position: relative; width: 100%; flex-direction: row; display:flex; align-items:center; gap:14px; min-height: unset; border-bottom: 1px solid var(--border); }
            .sidebar nav { flex-direction: row; flex-wrap: wrap; }
            .sidebar a { flex: 1 1 140px; text-align: center; border-left: none; border-top: 2px solid transparent; }
            .sidebar a.active { border-top-color: var(--primary); }
            main { width: 100%; }
            .content { padding: 16px; }
        }
        @media (max-width: 720px) {
            .topbar { flex-direction: column; align-items: flex-start; gap:10px; padding-inline: 18px; }
            .user-chip { width: 100%; text-align:left; }
            .toolbar { flex-direction: column; align-items: flex-start; }
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
        <nav>
            <a href="<?= $basePath ?>/dashboard" class="<?= ($normalizedPath === '/dashboard' || $normalizedPath === '/') ? 'active' : '' ?>">Dashboard</a>
            <a href="<?= $basePath ?>/clients" class="<?= str_starts_with($normalizedPath, '/clients') ? 'active' : '' ?>">Clientes</a>
            <a href="<?= $basePath ?>/projects" class="<?= str_starts_with($normalizedPath, '/projects') ? 'active' : '' ?>">Proyectos</a>
            <a href="<?= $basePath ?>/tasks" class="<?= str_starts_with($normalizedPath, '/tasks') ? 'active' : '' ?>">Tareas / Kanban</a>
            <a href="<?= $basePath ?>/talents" class="<?= str_starts_with($normalizedPath, '/talents') ? 'active' : '' ?>">Talento</a>
            <a href="<?= $basePath ?>/timesheets" class="<?= str_starts_with($normalizedPath, '/timesheets') ? 'active' : '' ?>">Timesheet</a>
            <?php if(in_array($user['role'] ?? '', ['Administrador', 'PMO'], true)): ?>
                <a href="<?= $basePath ?>/config" class="<?= str_starts_with($normalizedPath, '/config') ? 'active' : '' ?>">Configuración</a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/logout">Salir</a>
        </nav>
    </aside>
    <main>
        <header class="topbar">
            <div class="page-heading">
                <h2><?= htmlspecialchars($title ?? 'Panel') ?></h2>
                <p>Operaciones críticas del portafolio</p>
            </div>
            <?php if(isset($user)): ?>
                <div class="user-chip">
                    <div style="font-weight:700;"><?= htmlspecialchars($user['name'] ?? '') ?></div>
                    <div class="text-muted" style="font-size: 13px;">Rol: <?= htmlspecialchars($user['role'] ?? '') ?></div>
                </div>
            <?php endif; ?>
        </header>
        <div class="content">
