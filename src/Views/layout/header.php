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
            --text: color-mix(in srgb, var(--secondary) 78%, var(--background) 22%);
            --muted: color-mix(in srgb, var(--text) 65%, var(--background) 35%);
            --panel: color-mix(in srgb, var(--surface) 88%, var(--background) 12%);
            --panel-strong: color-mix(in srgb, var(--surface) 72%, var(--background) 28%);
            --border: color-mix(in srgb, var(--surface) 60%, var(--background) 40%);
            --border-strong: color-mix(in srgb, var(--secondary) 22%, var(--border) 78%);
            --glow: color-mix(in srgb, var(--primary) 18%, transparent);
            --shadow: 0 18px 42px var(--glow);
            --soft-primary: color-mix(in srgb, var(--primary) 18%, var(--panel) 82%);
            --soft-secondary: color-mix(in srgb, var(--secondary) 14%, var(--panel) 86%);
            --soft-accent: color-mix(in srgb, var(--accent) 18%, var(--panel) 82%);
            --positive: color-mix(in srgb, var(--primary) 55%, var(--accent) 45%);
            --warning: color-mix(in srgb, var(--accent) 70%, var(--secondary) 30%);
            --danger: color-mix(in srgb, var(--accent) 55%, var(--secondary) 45%);
            --surface-veil: color-mix(in srgb, var(--panel) 86%, transparent);
        }
        * {
            box-sizing: border-box;
            font-family: var(--font-family);
        }
        body {
            margin: 0;
            display: flex;
            background: radial-gradient(circle at 12% 20%, color-mix(in srgb, var(--primary) 6%, transparent), transparent 35%),
                        radial-gradient(circle at 82% 0%, color-mix(in srgb, var(--accent) 6%, transparent), transparent 30%),
                        linear-gradient(135deg, color-mix(in srgb, var(--background) 80%, var(--surface) 20%), color-mix(in srgb, var(--surface) 68%, var(--background) 32%));
            color: var(--text);
        }
        .sidebar {
            width: 270px;
            background: linear-gradient(180deg, color-mix(in srgb, var(--secondary) 90%, var(--primary) 10%), color-mix(in srgb, var(--secondary) 74%, var(--background) 26%));
            color: color-mix(in srgb, white 90%, var(--secondary) 10%);
            min-height: 100vh;
            padding: 28px 20px;
            position: sticky;
            top: 0;
            box-shadow: 10px 0 30px var(--glow);
            backdrop-filter: blur(10px);
        }
        .sidebar h1 {
            margin: 0 0 32px 0;
            font-size: 20px;
            display:flex;
            align-items:center;
            gap:12px;
            letter-spacing: 0.5px;
        }
        .sidebar h1 img {
            height: 32px;
            border-radius: 10px;
            background: var(--panel);
            padding:6px;
            box-shadow: 0 10px 26px var(--glow);
        }
        .sidebar nav {
            display:flex;
            flex-direction:column;
            gap:6px;
        }
        .sidebar a {
            display: flex;
            align-items:center;
            color: color-mix(in srgb, white 82%, var(--secondary) 18%);
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 12px;
            font-weight: 700;
            letter-spacing: 0.01em;
            transition: all 0.18s ease;
            border: 1px solid transparent;
            background: color-mix(in srgb, transparent, var(--surface-veil));
        }
        .sidebar a:hover, .sidebar a.active {
            background: color-mix(in srgb, var(--soft-accent) 30%, transparent);
            color: white;
            border-color: color-mix(in srgb, var(--accent) 40%, var(--primary) 60%);
            transform: translateX(2px);
            box-shadow: inset 0 1px 0 color-mix(in srgb, var(--panel) 20%, transparent);
        }
        .topbar {
            background: color-mix(in srgb, var(--panel) 88%, transparent);
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 24px color-mix(in srgb, var(--secondary) 8%, transparent);
        }
        main {
            flex: 1;
            min-height: 100vh;
        }
        .content {
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .page-heading h2 {
            margin:0;
            font-size: 22px;
            letter-spacing: 0.02em;
        }
        .page-heading p {
            margin:0;
            color: var(--muted);
        }
        .user-chip {
            display:flex;
            flex-direction:column;
            gap:4px;
            text-align:right;
            padding: 10px 14px;
            border-radius: 12px;
            background: var(--soft-secondary);
            border: 1px solid var(--border);
        }
        .card {
            background: var(--panel);
            border-radius: 16px;
            padding: 18px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .card.ghosted {
            background: color-mix(in srgb, var(--panel) 70%, transparent);
            border-style: dashed;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }
        .grid.tight { gap: 12px; }
        .kpi {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .kpi .label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .kpi .value {
            font-weight: 800;
            font-size: 28px;
        }
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            background: var(--soft-secondary);
            color: var(--text);
            border: 1px solid var(--border);
        }
        .badge.success { background: color-mix(in srgb, var(--positive) 16%, var(--panel) 84%); color: var(--positive); }
        .badge.warning { background: color-mix(in srgb, var(--warning) 16%, var(--panel) 84%); color: var(--warning); }
        .badge.danger { background: color-mix(in srgb, var(--danger) 16%, var(--panel) 84%); color: var(--danger); }
        .badge.neutral { background: var(--soft-accent); color: color-mix(in srgb, var(--accent) 80%, var(--secondary) 20%); }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            text-align: left;
        }
        th {
            background: color-mix(in srgb, var(--soft-secondary) 65%, var(--panel) 35%);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
        }
        tr:last-child td { border-bottom: none; }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            gap: 10px;
        }
        .btn {
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid transparent;
            cursor: pointer;
            font-weight: 700;
            letter-spacing: 0.01em;
            transition: transform 0.15s ease, box-shadow 0.2s ease;
        }
        .btn.primary {
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 70%, var(--accent) 30%));
            color: white;
            box-shadow: 0 10px 30px var(--glow);
        }
        .btn.secondary {
            background: var(--panel);
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn.ghost {
            background: transparent;
            color: color-mix(in srgb, var(--accent) 70%, var(--secondary) 30%);
            border: 1px dashed var(--border-strong);
        }
        .btn:hover { transform: translateY(-1px); }
        form.inline { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        input, select, textarea {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            width: 100%;
            background: color-mix(in srgb, var(--panel) 94%, transparent);
            color: var(--text);
        }
        input[type="file"] {
            border: 1px dashed var(--border-strong);
            background: var(--soft-secondary);
        }
        textarea { resize: vertical; }
        label { font-weight: 700; color: var(--text); display:block; margin-bottom:4px; }
        .hint { color: var(--muted); font-size: 13px; }
        .kanban { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .column { background: var(--soft-secondary); padding: 12px; border-radius: 12px; border: 1px solid var(--border); }
        .column h3 { margin: 0 0 8px 0; font-size: 14px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; }
        .card-task { background: var(--panel); border-radius: 12px; padding: 12px; box-shadow: 0 1px 2px var(--glow); margin-bottom: 8px; border: 1px solid var(--border); }
        .pill { border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 700; background: var(--soft-secondary); color: var(--text); border: 1px solid var(--border); }
        .pill.high { background: color-mix(in srgb, var(--danger) 16%, var(--panel) 84%); color: var(--danger); }
        .pill.medium { background: color-mix(in srgb, var(--warning) 16%, var(--panel) 84%); color: var(--warning); }
        .pill.low { background: color-mix(in srgb, var(--positive) 16%, var(--panel) 84%); color: var(--positive); }
        .section-grid { display:grid; gap:18px; align-items:start; }
        .section-grid.twothirds { grid-template-columns: 3fr 2fr; }
        .section-grid.wide { grid-template-columns: 2fr 3fr; }
        .card-overlay { position:relative; overflow:hidden; }
        .card-overlay::after { content:''; position:absolute; inset:10px; border-radius:14px; border:1px solid color-mix(in srgb, var(--panel) 30%, transparent); opacity:0.6; }
        .stack { display:flex; flex-direction:column; gap:12px; }
        .pillset { display:flex; gap:8px; flex-wrap:wrap; }
        .text-muted { color: var(--muted); }
        .alert { padding:12px; border-radius: 12px; border:1px solid var(--border); background: var(--soft-secondary); color: var(--text); }
        .alert.success { background: color-mix(in srgb, var(--positive) 14%, var(--panel) 86%); color: var(--positive); border-color: color-mix(in srgb, var(--positive) 40%, var(--border) 60%); }
        .table-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .chip-list { display:flex; flex-wrap:wrap; gap:8px; }
        .subtle-card { background: var(--soft-secondary); border: 1px dashed var(--border-strong); box-shadow: none; }
        .table-wrapper { overflow-x: auto; }
        @media (max-width: 1024px) {
            body { flex-direction: column; }
            .sidebar { position: relative; width: 100%; flex-direction: row; display:flex; align-items:center; gap:16px; min-height: unset; border-bottom: 1px solid var(--border); box-shadow: none; }
            .sidebar nav { flex-direction: row; flex-wrap: wrap; }
            .sidebar a { flex: 1 1 140px; justify-content: center; }
            main { width: 100%; }
            .content { padding: 20px; }
        }
        @media (max-width: 720px) {
            .topbar { flex-direction: column; align-items: flex-start; gap:10px; }
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
