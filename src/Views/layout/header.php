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
            --text: color-mix(in srgb, var(--secondary) 74%, var(--background) 26%);
            --muted: color-mix(in srgb, var(--text) 62%, var(--background) 38%);
            --panel: color-mix(in srgb, var(--surface) 86%, var(--background) 14%);
            --panel-strong: color-mix(in srgb, var(--surface) 70%, var(--background) 30%);
            --border: color-mix(in srgb, var(--surface) 58%, var(--background) 42%);
            --border-strong: color-mix(in srgb, var(--secondary) 18%, var(--border) 82%);
            --shadow: 0 18px 46px color-mix(in srgb, var(--secondary) 12%, transparent);
            --soft-primary: color-mix(in srgb, var(--primary) 16%, var(--panel) 84%);
            --soft-secondary: color-mix(in srgb, var(--secondary) 12%, var(--panel) 88%);
            --soft-accent: color-mix(in srgb, var(--accent) 16%, var(--panel) 84%);
            --positive: color-mix(in srgb, var(--primary) 55%, var(--accent) 45%);
            --warning: color-mix(in srgb, var(--accent) 64%, var(--secondary) 36%);
            --danger: color-mix(in srgb, var(--accent) 55%, var(--secondary) 45%);
            --surface-veil: color-mix(in srgb, var(--panel) 82%, transparent);
        }
        * {
            box-sizing: border-box;
            font-family: var(--font-family);
        }
        body {
            margin: 0;
            display: flex;
            background: linear-gradient(165deg, color-mix(in srgb, var(--background) 88%, var(--surface) 12%), color-mix(in srgb, var(--surface) 90%, var(--background) 10%));
            color: var(--text);
        }
        .sidebar {
            width: 270px;
            background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%);
            color: var(--muted);
            min-height: 100vh;
            padding: 30px 22px;
            position: sticky;
            top: 0;
            border-right: 1px solid var(--border);
            box-shadow: 10px 0 26px color-mix(in srgb, var(--secondary) 8%, transparent);
        }
        .sidebar h1 {
            margin: 0 0 28px 0;
            font-size: 19px;
            display:flex;
            align-items:center;
            gap:12px;
            letter-spacing: 0.04em;
            color: var(--text);
        }
        .sidebar h1 img {
            height: 32px;
            border-radius: 10px;
            background: var(--panel);
            padding:6px;
            box-shadow: 0 10px 20px color-mix(in srgb, var(--secondary) 12%, transparent);
        }
        .sidebar nav {
            display:flex;
            flex-direction:column;
            gap:10px;
        }
        .sidebar a {
            display: flex;
            align-items:center;
            color: var(--muted);
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 12px;
            font-weight: 700;
            letter-spacing: 0.01em;
            transition: all 0.18s ease;
            border: 1px solid transparent;
            background: color-mix(in srgb, var(--surface) 6%, transparent);
        }
        .sidebar a:hover {
            color: var(--text);
            background: color-mix(in srgb, var(--surface) 12%, transparent);
            border-color: color-mix(in srgb, var(--border) 80%, var(--surface) 20%);
        }
        .sidebar a.active {
            color: var(--text);
            background: color-mix(in srgb, var(--surface) 18%, transparent);
            border-color: color-mix(in srgb, var(--border) 60%, var(--primary) 40%);
            box-shadow: inset 3px 0 0 var(--primary);
        }
        .topbar {
            background: color-mix(in srgb, var(--panel) 90%, transparent);
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--secondary) 10%, transparent);
        }
        main {
            flex: 1;
            min-height: 100vh;
        }
        .content {
            padding: 28px 32px 40px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            background: linear-gradient(180deg, color-mix(in srgb, var(--background) 88%, var(--surface) 12%), color-mix(in srgb, var(--surface) 92%, var(--background) 8%));
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
            background: color-mix(in srgb, var(--surface) 14%, transparent);
            border: 1px solid color-mix(in srgb, var(--border) 86%, var(--surface) 14%);
        }
        .card {
            background: color-mix(in srgb, var(--panel) 94%, transparent);
            border-radius: 18px;
            padding: 22px;
            box-shadow: var(--shadow);
            border: 1px solid color-mix(in srgb, var(--border) 78%, var(--surface) 22%);
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
            gap: 8px;
        }
        .kpi .label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .kpi .value {
            font-weight: 800;
            font-size: 32px;
            letter-spacing: -0.01em;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
            color: var(--muted);
            border: 1px solid var(--border);
        }
        .badge.success { background: color-mix(in srgb, var(--positive) 12%, var(--panel) 88%); color: var(--positive); }
        .badge.warning { background: color-mix(in srgb, var(--warning) 12%, var(--panel) 88%); color: var(--warning); }
        .badge.danger { background: color-mix(in srgb, var(--danger) 14%, var(--panel) 86%); color: var(--danger); }
        .badge.neutral { background: color-mix(in srgb, var(--accent) 16%, var(--panel) 84%); color: color-mix(in srgb, var(--accent) 78%, var(--secondary) 22%); }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            background: color-mix(in srgb, var(--panel) 96%, transparent);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            text-align: left;
        }
        th {
            background: color-mix(in srgb, var(--surface) 82%, var(--background) 18%);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
        }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: color-mix(in srgb, var(--surface) 16%, transparent); }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            gap: 10px;
        }
        .btn {
            padding: 10px 16px;
            border-radius: 12px;
            border: 1px solid transparent;
            cursor: pointer;
            font-weight: 700;
            letter-spacing: 0.01em;
            transition: transform 0.12s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
        }
        .btn.primary {
            background: color-mix(in srgb, var(--primary) 82%, var(--secondary) 18%);
            color: white;
            box-shadow: 0 10px 26px color-mix(in srgb, var(--primary) 16%, transparent);
            border-color: color-mix(in srgb, var(--primary) 36%, transparent);
        }
        .btn.secondary {
            background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn.ghost {
            background: transparent;
            color: var(--text);
            border: 1px dashed var(--border-strong);
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 10px 24px color-mix(in srgb, var(--secondary) 12%, transparent); }
        form.inline { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        input, select, textarea {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            width: 100%;
            background: color-mix(in srgb, var(--surface) 92%, transparent);
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
        .column { background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); padding: 12px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 6px 14px color-mix(in srgb, var(--secondary) 10%, transparent); }
        .column h3 { margin: 0 0 8px 0; font-size: 14px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; }
        .card-task { background: color-mix(in srgb, var(--panel) 96%, transparent); border-radius: 12px; padding: 12px; box-shadow: 0 8px 18px color-mix(in srgb, var(--secondary) 10%, transparent); margin-bottom: 8px; border: 1px solid var(--border); }
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
