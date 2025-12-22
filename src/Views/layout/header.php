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
            --text: color-mix(in srgb, var(--secondary) 75%, var(--background) 25%);
            --muted: color-mix(in srgb, var(--secondary) 55%, var(--background) 45%);
            --border: color-mix(in srgb, var(--surface) 60%, var(--background) 40%);
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
        }
        .sidebar {
            width: 240px;
            background: var(--surface);
            color: var(--muted);
            min-height: 100vh;
            padding: 28px 18px;
            position: sticky;
            top: 0;
            border-right: 1px solid var(--border);
        }
        .sidebar h1 {
            margin: 0 0 20px 0;
            font-size: 18px;
            display:flex;
            align-items:center;
            gap:10px;
            color: var(--text);
            font-weight: 700;
        }
        .sidebar h1 img { height: 30px; }
        .sidebar nav { display:flex; flex-direction:column; gap:2px; }
        .sidebar a {
            display: block;
            color: var(--muted);
            text-decoration: none;
            padding: 10px 6px;
            border-left: 3px solid transparent;
        }
        .sidebar a:hover { color: var(--text); }
        .sidebar a.active {
            color: var(--text);
            border-left-color: var(--primary);
            font-weight: 700;
        }
        .topbar {
            padding: 18px 28px 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--background);
            border-bottom: 1px solid var(--border);
        }
        main {
            flex: 1;
            min-height: 100vh;
        }
        .content {
            padding: 16px 28px 40px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .page-heading h2 { margin:0 0 6px 0; font-size: 22px; }
        .page-heading p { margin:0; color: var(--muted); }
        .user-chip { display:flex; flex-direction:column; gap:4px; text-align:right; color: var(--muted); }
        .section-grid { display:grid; gap:16px; align-items:start; }
        .section-grid.twothirds { grid-template-columns: 3fr 2fr; }
        .section-grid.wide { grid-template-columns: 2fr 3fr; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .grid.tight { gap: 8px; }
        .card {
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 16px;
        }
        .card.ghosted { background: transparent; border-style: dashed; }
        .kpi { display: flex; flex-direction: column; gap: 6px; }
        .kpi .label { color: var(--muted); font-size: 12px; text-transform: uppercase; }
        .kpi .value { font-weight: 700; font-size: 26px; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            background: transparent;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            text-align: left;
        }
        th {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
            background: var(--surface);
        }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: var(--surface); }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; gap: 10px; flex-wrap: wrap; }
        .btn {
            padding: 10px 14px;
            border-radius: 4px;
            border: 1px solid var(--border);
            cursor: pointer;
            font-weight: 600;
            background: var(--surface);
            color: var(--text);
        }
        .btn.primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn.secondary { background: var(--background); }
        .btn.ghost { background: transparent; border-style: dashed; }
        form.inline { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        input, select, textarea {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            width: 100%;
            background: var(--background);
            color: var(--text);
        }
        textarea { resize: vertical; }
        label { font-weight: 600; color: var(--text); display:block; margin-bottom:4px; }
        .hint { color: var(--muted); font-size: 13px; }
        .kanban { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
        .column { background: var(--surface); padding: 12px; border-radius: 6px; border: 1px solid var(--border); }
        .column h3 { margin: 0 0 8px 0; font-size: 13px; color: var(--muted); text-transform: uppercase; }
        .card-task { background: var(--background); border-radius: 4px; padding: 12px; border: 1px solid var(--border); margin-bottom: 8px; }
        .pill { border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 600; background: transparent; color: var(--text); border: 1px solid var(--border); }
        .pillset { display:flex; gap:6px; flex-wrap:wrap; }
        .text-muted { color: var(--muted); }
        .alert { padding:12px; border-radius: 4px; border:1px solid var(--border); background: var(--surface); color: var(--text); }
        .table-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .chip-list { display:flex; flex-wrap:wrap; gap:8px; }
        .table-wrapper { overflow-x: auto; }
        @media (max-width: 1024px) {
            body { flex-direction: column; }
            .sidebar { position: relative; width: 100%; flex-direction: row; display:flex; align-items:center; gap:14px; min-height: unset; border-bottom: 1px solid var(--border); }
            .sidebar nav { flex-direction: row; flex-wrap: wrap; }
            .sidebar a { flex: 1 1 140px; text-align: center; border-left: none; border-top: 3px solid transparent; }
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
