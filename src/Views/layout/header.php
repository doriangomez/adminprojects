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
            --gray: #6b7280;
            --bg: #f3f4f6;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        body { margin: 0; display: flex; background: var(--bg); color: #111827; }
        .sidebar { width: 240px; background: linear-gradient(180deg, var(--secondary), #0b1224); color: white; min-height: 100vh; padding: 24px 16px; position: sticky; top: 0; }
        .sidebar h1 { margin: 0 0 24px 0; font-size: 20px; display:flex; align-items:center; gap:8px; }
        .sidebar h1 img { height: 28px; border-radius: 6px; background:white; padding:4px; }
        .sidebar a { display: block; color: #cbd5e1; text-decoration: none; padding: 10px 12px; border-radius: 8px; margin-bottom: 6px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.08); color: white; }
        header { background: white; padding: 16px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        main { flex: 1; min-height: 100vh; }
        .content { padding: 24px; }
        .card { background: white; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
        .kpi { display: flex; flex-direction: column; gap: 4px; }
        .kpi .label { color: var(--gray); font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi .value { font-weight: 700; font-size: 24px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge.success { background: #dcfce7; color: #15803d; }
        .badge.warning { background: #fef3c7; color: #b45309; }
        .badge.danger { background: #fee2e2; color: #b91c1c; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .btn { padding: 10px 14px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; }
        .btn.primary { background: var(--primary); color: white; }
        .btn.secondary { background: white; color: #0f172a; border: 1px solid #e5e7eb; }
        form.inline { display: flex; gap: 8px; align-items: center; }
        input, select { padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; }
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
