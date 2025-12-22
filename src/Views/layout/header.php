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
            --gray: #6b7280;
            --bg: #eef2ff;
            --panel: #ffffff;
            --muted: #94a3b8;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        }
        * { box-sizing: border-box; font-family: var(--font-family); }
        body { margin: 0; display: flex; background: linear-gradient(120deg, rgba(37,99,235,0.04), rgba(15,23,42,0.06)); color: #0f172a; }
        .sidebar { width: 260px; background: linear-gradient(180deg, var(--secondary), var(--background)); color: white; min-height: 100vh; padding: 28px 18px; position: sticky; top: 0; box-shadow: 6px 0 24px rgba(0,0,0,0.08); }
        .sidebar h1 { margin: 0 0 28px 0; font-size: 20px; display:flex; align-items:center; gap:10px; letter-spacing: 0.5px; }
        .sidebar h1 img { height: 30px; border-radius: 8px; background:white; padding:6px; box-shadow: 0 6px 18px rgba(0,0,0,0.2); }
        .sidebar a { display: block; color: rgba(255,255,255,0.8); text-decoration: none; padding: 11px 14px; border-radius: 12px; margin-bottom: 8px; font-weight: 600; transition: all 0.15s ease; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.12); color: white; transform: translateX(2px); }
        header { background: rgba(255,255,255,0.9); padding: 18px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; backdrop-filter: blur(12px); }
        main { flex: 1; min-height: 100vh; }
        .content { padding: 28px; display: flex; flex-direction: column; gap: 18px; }
        .card { background: var(--panel); border-radius: 16px; padding: 18px; box-shadow: var(--shadow); border: 1px solid #e2e8f0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .kpi { display: flex; flex-direction: column; gap: 6px; }
        .kpi .label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi .value { font-weight: 800; font-size: 26px; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge.success { background: #dcfce7; color: #15803d; }
        .badge.warning { background: #fef3c7; color: #b45309; }
        .badge.danger { background: #fee2e2; color: #b91c1c; }
        .badge.neutral { background: #e0f2fe; color: #0ea5e9; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 10px; }
        .btn { padding: 10px 14px; border-radius: 12px; border: none; cursor: pointer; font-weight: 700; letter-spacing: 0.01em; }
        .btn.primary { background: var(--primary); color: white; box-shadow: 0 10px 30px rgba(37,99,235,0.25); }
        .btn.secondary { background: white; color: #0f172a; border: 1px solid #e5e7eb; }
        .btn.ghost { background: transparent; color: var(--secondary); border: 1px dashed #cbd5e1; }
        form.inline { display: flex; gap: 8px; align-items: center; }
        input, select, textarea { padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 10px; width: 100%; }
        textarea { resize: vertical; }
        .kanban { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .column { background: #f8fafc; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .column h3 { margin: 0 0 8px 0; font-size: 14px; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; }
        .card-task { background: white; border-radius: 12px; padding: 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); margin-bottom: 8px; }
        .pill { border-radius: 999px; padding: 4px 8px; font-size: 11px; font-weight: 700; }
        .pill.high { background: #fee2e2; color: #b91c1c; }
        .pill.medium { background: #fef3c7; color: #b45309; }
        .pill.low { background: #e0f2fe; color: #0369a1; }
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
        <header>
            <div>
                <h2 style="margin:0; font-size:20px;"><?= htmlspecialchars($title ?? 'Panel') ?></h2>
                <p style="margin:0; color: var(--gray);">Operaciones críticas del portafolio</p>
            </div>
            <?php if(isset($user)): ?>
                <div style="text-align:right;">
                    <div style="font-weight:700;"><?= htmlspecialchars($user['name'] ?? '') ?></div>
                    <div style="color: var(--gray); font-size: 13px;">Rol: <?= htmlspecialchars($user['role'] ?? '') ?></div>
                </div>
            <?php endif; ?>
        </header>
        <div class="content">
