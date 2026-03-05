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
    <style>
        * {
            box-sizing: border-box;
            font-family: var(--font-family);
        }
        body {
            margin: 0;
            display: flex;
            background:
                radial-gradient(circle at 10% -10%, color-mix(in srgb, var(--primary) 14%, transparent), transparent 38%),
                radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--accent) 12%, transparent), transparent 44%),
                var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            font-weight: 500;
        }
        .sidebar {
            width: 292px;
            background: linear-gradient(
                185deg,
                color-mix(in srgb, var(--secondary) 90%, #0f172a 10%),
                color-mix(in srgb, var(--secondary) 84%, #020617 16%)
            );
            color: var(--text-primary);
            min-height: 100vh;
            padding: 22px 18px;
            position: sticky;
            top: 0;
            border-right: 1px solid color-mix(in srgb, var(--border) 70%, var(--background));
            box-shadow: 20px 0 50px color-mix(in srgb, #020617 18%, transparent);
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
            border: 1px solid color-mix(in srgb, var(--border) 70%, var(--background));
            background: color-mix(in srgb, var(--surface) 18%, var(--background));
            color: var(--text-primary);
            border-radius: 12px;
            padding: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.18s ease, border-color 0.18s ease, background-color 0.18s ease;
        }
        .sidebar-toggle:hover {
            transform: translateY(-1px);
            border-color: color-mix(in srgb, var(--primary) 46%, var(--border));
            background: color-mix(in srgb, var(--surface) 28%, var(--background));
        }
        .sidebar-toggle svg { width: 18px; height: 18px; stroke: currentColor; }
        .brand-box { display:flex; align-items:center; gap:10px; padding: 11px 10px; border-radius:14px; border:1px solid color-mix(in srgb, var(--border) 70%, var(--background)); background: linear-gradient(160deg, color-mix(in srgb, var(--surface) 18%, var(--background)), color-mix(in srgb, var(--surface) 6%, var(--background))); box-shadow: 0 10px 24px color-mix(in srgb, #020617 20%, transparent); }
        .brand-mark { display:flex; align-items:center; justify-content:center; min-width: 36px; }
        .brand-box img { height: 32px; max-height: 40px; object-fit: contain; }
        .brand-name { font-weight: 800; color: var(--text-primary); font-size: 15px; }
        .brand-fallback { font-weight: 800; color: var(--text-primary); font-size: 18px; letter-spacing: 0.02em; }
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
            background: linear-gradient(150deg, color-mix(in srgb, var(--surface) 28%, var(--background)), color-mix(in srgb, var(--primary) 24%, var(--secondary)));
            color: var(--text-primary);
            border: 1px solid color-mix(in srgb, var(--border) 60%, var(--background));
            box-shadow: 0 8px 18px color-mix(in srgb, #020617 18%, transparent);
        }
        .user-meta { display: flex; flex-direction: column; gap: 3px; }
        .user-meta strong { color: var(--text-primary); font-size: 15px; font-weight: 700; }
        .user-meta small { color: var(--text-primary); font-size: 13px; font-weight: 500; }
        .nav-title {
            margin: 0;
            font-size: 13px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--text-primary);
            padding-inline: 10px;
            font-weight: 600;
        }
        .nav-section-label { font-size: 11px; text-transform: uppercase; color: var(--text-primary); font-weight: 800; padding-inline: 10px; letter-spacing: 0.08em; margin-top: 6px; }
        .nav-divider { height: 1px; background: color-mix(in srgb, var(--border) 65%, var(--background)); margin: 4px 10px; }
        .sidebar nav { display:flex; flex-direction:column; gap:12px; }
        .nav-link {
            --nav-tone: var(--primary);
            --nav-tone-soft: color-mix(in srgb, var(--nav-tone) 22%, var(--surface));
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 13px 12px;
            border-radius: 16px;
            position: relative;
            border: 1px solid color-mix(in srgb, var(--border) 72%, var(--background));
            font-weight: 600;
            font-size: 15px;
            overflow: hidden;
            transition: border-color 0.22s ease, transform 0.22s ease, background-color 0.22s ease, box-shadow 0.22s ease;
        }
        .nav-link::before {
            content: '';
            position: absolute;
            left: 9px;
            top: 9px;
            bottom: 9px;
            width: 3px;
            border-radius: 8px;
            background: color-mix(in srgb, var(--nav-tone) 28%, transparent);
            transition: background-color 0.22s ease;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(120deg, color-mix(in srgb, var(--nav-tone) 10%, transparent), transparent 45%);
            opacity: 0;
            transition: opacity 0.22s ease;
            pointer-events: none;
        }
        .nav-link[data-tone='indigo'] { --nav-tone: #6366f1; }
        .nav-link[data-tone='sky'] { --nav-tone: #0ea5e9; }
        .nav-link[data-tone='cyan'] { --nav-tone: #06b6d4; }
        .nav-link[data-tone='teal'] { --nav-tone: #14b8a6; }
        .nav-link[data-tone='amber'] { --nav-tone: #f59e0b; }
        .nav-link[data-tone='violet'] { --nav-tone: #8b5cf6; }
        .nav-link[data-tone='pink'] { --nav-tone: #ec4899; }
        .nav-link[data-tone='green'] { --nav-tone: #22c55e; }
        .nav-link[data-tone='blue'] { --nav-tone: #3b82f6; }
        .nav-link[data-tone='orange'] { --nav-tone: #f97316; }
        .nav-link[data-tone='red'] { --nav-tone: #ef4444; }
        .nav-link:hover {
            color: var(--text-primary);
            background: color-mix(in srgb, var(--surface) 16%, var(--background));
            border-color: color-mix(in srgb, var(--nav-tone) 52%, var(--border));
            transform: translateX(2px);
            box-shadow: 0 8px 18px color-mix(in srgb, var(--nav-tone) 24%, transparent);
        }
        .nav-link:hover::after { opacity: 1; }
        .nav-link.active {
            color: var(--text-primary);
            font-weight: 700;
            background: color-mix(in srgb, var(--surface) 24%, var(--background));
            border-color: color-mix(in srgb, var(--nav-tone) 60%, var(--border));
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--nav-tone) 24%, transparent);
        }
        .nav-link.active::before { background: var(--nav-tone); }
        .nav-group { display: flex; flex-direction: column; gap: 6px; }
        .nav-submenu {
            margin-left: 12px;
            padding-left: 10px;
            border-left: 1px dashed color-mix(in srgb, var(--border) 72%, var(--background));
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .nav-sublink {
            text-decoration: none;
            color: var(--text-secondary);
            padding: 7px 10px;
            border-radius: 10px;
            font-size: 13px;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .nav-sublink:hover {
            color: var(--text-primary);
            border-color: color-mix(in srgb, var(--primary) 44%, var(--border));
            background: color-mix(in srgb, var(--surface) 16%, var(--background));
        }
        .nav-sublink.active {
            color: var(--text-primary);
            border-color: color-mix(in srgb, #3b82f6 52%, var(--border));
            background: color-mix(in srgb, #3b82f6 16%, var(--background));
            font-weight: 700;
        }
        .nav-icon {
            width: 40px;
            height: 40px;
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--nav-tone);
            border-radius: 13px;
            background: linear-gradient(155deg, color-mix(in srgb, var(--nav-tone) 24%, #ffffff), var(--nav-tone-soft));
            border: 1px solid color-mix(in srgb, var(--nav-tone) 50%, var(--border));
            box-shadow: 0 5px 12px color-mix(in srgb, var(--nav-tone) 20%, transparent);
            transition: transform 0.22s ease, box-shadow 0.22s ease, background-color 0.22s ease, color 0.22s ease;
        }
        .nav-link:hover .nav-icon {
            color: #ffffff;
            background: linear-gradient(140deg, color-mix(in srgb, var(--nav-tone) 82%, #111827), var(--nav-tone));
            box-shadow: 0 10px 18px color-mix(in srgb, var(--nav-tone) 40%, transparent);
            transform: translateY(-1px) scale(1.06);
        }
        .nav-link.active .nav-icon {
            color: #ffffff;
            background: linear-gradient(145deg, var(--nav-tone), color-mix(in srgb, var(--nav-tone) 64%, #0f172a));
            border-color: color-mix(in srgb, var(--nav-tone) 78%, var(--surface));
            box-shadow: 0 12px 22px color-mix(in srgb, var(--nav-tone) 45%, transparent);
            transform: translateY(-1px);
        }
        .nav-label { white-space: nowrap; position: relative; z-index: 1; }
        .nav-badge {
            margin-left: auto;
            min-width: 24px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-primary);
            background: var(--accent);
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .nav-icon svg {
            width: 66%;
            height: 66%;
            stroke: currentColor;
            stroke-width: 2.1;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }
        .topbar {
            padding: 12px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 10;
            background: color-mix(in srgb, var(--surface) 92%, transparent);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 10px 24px color-mix(in srgb, #0f172a 10%, transparent);
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
            color: var(--text-primary);
            font-size: 20px;
            white-space: nowrap;
        }
        .topbar .spacer { flex: 1; }
        .user-actions { display: flex; align-items: center; gap: 12px; }
        .user-summary { display:flex; align-items:center; gap:10px; padding:6px 0; }
        .user-identity { display:flex; flex-direction:column; gap:3px; }
        .user-identity strong { color: var(--text-primary); font-size: 14px; font-weight: 700; }
        .role-badge { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; border:1px solid color-mix(in srgb, var(--primary) 38%, var(--border)); background: color-mix(in srgb, var(--primary) 14%, var(--background)); color: var(--primary); font-size:12px; font-weight:700; letter-spacing: 0.01em; }
        .logout-btn { padding:10px 14px; border-radius:12px; border:1px solid var(--border); background: linear-gradient(150deg, color-mix(in srgb, var(--surface) 95%, var(--background) 5%), color-mix(in srgb, var(--surface) 86%, var(--background) 14%)); color: var(--text-primary); text-decoration:none; font-weight:600; transition: all 0.2s ease; }
        .logout-btn:hover { transform: translateY(-1px); background: color-mix(in srgb, var(--primary) 12%, var(--background)); border-color: color-mix(in srgb, var(--primary) 24%, var(--background)); color: var(--primary); box-shadow: 0 10px 18px color-mix(in srgb, var(--primary) 16%, transparent); }
        .impersonation-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin: 18px 24px 0;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid color-mix(in srgb, var(--accent) 45%, var(--border) 55%);
            background: color-mix(in srgb, var(--accent) 18%, var(--surface) 82%);
            color: var(--text-primary);
            font-weight: 600;
        }
        .impersonation-banner strong { font-weight: 800; }
        .impersonation-banner .btn { padding: 8px 12px; }
        main {
            flex: 1;
            min-height: 100vh;
            background: var(--background);
        }
        .content {
            padding: 28px 34px 52px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }
        .page-heading h2 { margin:0 0 6px 0; font-size: 27px; color: var(--text-primary); font-weight: 800; letter-spacing: -0.015em; }
        .page-heading p { margin:0; color: var(--text-secondary); font-weight: 500; }
        .section-grid { display:grid; gap:20px; align-items:start; }
        .section-grid.twothirds { grid-template-columns: 3fr 2fr; }
        .section-grid.wide { grid-template-columns: 2fr 3fr; }
        .config-columns { display:grid; gap:20px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); align-items:stretch; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .grid.tight { gap: 10px; }
        .config-form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; align-items:start; }
        .config-form-grid.tight { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
        .config-form-grid.compact-grid { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
        .card.stretch { height:100%; display:flex; flex-direction:column; }
        .card .card-content { display:flex; flex-direction:column; gap:14px; flex:1; }
        .form-block { display:flex; flex-direction:column; gap:10px; padding:12px; border:1px solid var(--border); border-radius:10px; background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); }
        .section-label { font-size:12px; letter-spacing:0.05em; text-transform:uppercase; color: var(--text-secondary); font-weight:700; }
        .input-stack { display:flex; flex-direction:column; gap:8px; }
        .palette-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap:10px; }
        .option-row { display:flex; gap:12px; flex-wrap:wrap; }
        .option { display:flex; align-items:center; gap:8px; font-weight:600; color: var(--text-primary); }
        .option.compact { gap:4px; font-size:13px; }
        .form-footer { display:flex; justify-content:space-between; align-items:center; gap:12px; grid-column:1 / -1; }
        .card-stack { display:flex; flex-direction:column; gap:12px; }
        .cards-grid { align-items:stretch; }
        .preview-pane { background: color-mix(in srgb, var(--surface) 12%, var(--background)); padding:16px; border-radius:12px; display:flex; flex-direction:column; gap:10px; }
        .preview-header { display:flex; align-items:center; gap:12px; }
        .sidebar.collapsed .brand-name { display: none; }
        .sidebar.collapsed .brand-box { justify-content: center; }
        .sidebar.collapsed .brand-mark img { height: 32px; }
        .sidebar.collapsed .brand-fallback { font-size: 16px; }
        .sidebar.collapsed .nav-submenu { display: none; }
        .preview-logo { height:42px; background:color-mix(in srgb, var(--surface) 90%, var(--background) 10%); padding:8px; border-radius:10px; box-shadow:0 8px 20px color-mix(in srgb, var(--text-primary) 20%, var(--background) 80%); }
        .preview-subtitle { color: color-mix(in srgb, var(--surface) 80%, var(--background)); font-size:13px; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 19px;
            box-shadow: 0 14px 30px color-mix(in srgb, #0f172a 10%, transparent);
        }
        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
            color: var(--text-primary);
        }
        .alert.error {
            border-color: color-mix(in srgb, var(--danger) 40%, var(--surface) 60%);
            background: color-mix(in srgb, var(--danger) 15%, var(--surface) 85%);
            color: var(--danger);
        }
        .card.ghosted { background: var(--background); border-style: dashed; }
        .kpi { display: flex; align-items: center; gap: 14px; }
        .kpi .label { color: var(--text-secondary); font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
        .kpi .value { font-weight: 700; font-size: 34px; color: var(--text-primary); }
        .kpi .meta { color: var(--text-secondary); font-size: 13px; font-weight: 500; }
        .kpi-icon { width: 56px; height: 56px; border-radius: 12px; background: color-mix(in srgb, var(--primary) 16%, var(--background)); display: inline-flex; align-items: center; justify-content: center; color: var(--primary); }
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
            background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        .badge.success { background: color-mix(in srgb, var(--success) 16%, var(--background)); color: var(--success); }
        .badge.warning { background: color-mix(in srgb, var(--warning) 16%, var(--background)); color: var(--warning); }
        .badge.info { background: color-mix(in srgb, var(--info) 16%, var(--background)); color: var(--info); }
        .badge.danger { background: color-mix(in srgb, var(--danger) 16%, var(--background)); color: var(--danger); }
        .badge.neutral { background: color-mix(in srgb, var(--neutral) 12%, var(--background)); color: var(--text-primary); }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
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
            color: var(--text-secondary);
            font-weight: 700;
            background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%);
        }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: color-mix(in srgb, var(--surface) 84%, var(--background) 16%); }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 12px; flex-wrap: wrap; }
        .btn {
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            cursor: pointer;
            font-weight: 700;
            background: linear-gradient(150deg, color-mix(in srgb, var(--surface) 95%, var(--background) 5%), color-mix(in srgb, var(--surface) 86%, var(--background) 14%));
            color: var(--text-primary);
            transition: background-color 120ms ease, border-color 120ms ease, color 120ms ease, transform 120ms ease, box-shadow 120ms ease;
        }
        .btn.sm { padding: 6px 10px; border-radius: 8px; font-size: 12px; }
        .btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
        .btn.secondary { background: var(--surface); }
        .btn.ghost { background: var(--background); border-style: dashed; }
        .btn.danger { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--border)); background: color-mix(in srgb, var(--danger) 12%, var(--background)); }
        .btn.warning { color: var(--warning); border-color: color-mix(in srgb, var(--warning) 35%, var(--border)); background: color-mix(in srgb, var(--warning) 12%, var(--background)); }
        .btn.danger:hover { background: color-mix(in srgb, var(--danger) 20%, var(--background)); border-color: color-mix(in srgb, var(--danger) 45%, var(--border)); color: var(--danger); }
        .btn.warning:hover { background: color-mix(in srgb, var(--warning) 20%, var(--background)); border-color: color-mix(in srgb, var(--warning) 45%, var(--border)); color: var(--warning); }
        .btn:hover { transform: translateY(-1px); background: color-mix(in srgb, var(--primary) 86%, var(--accent) 14%); border-color: color-mix(in srgb, var(--primary) 86%, var(--accent) 14%); color: var(--text-primary); box-shadow: 0 10px 18px color-mix(in srgb, var(--primary) 18%, transparent); }
        .btn:active { background: color-mix(in srgb, var(--primary) 78%, var(--secondary) 22%); border-color: color-mix(in srgb, var(--primary) 78%, var(--secondary) 22%); color: var(--text-primary); }
        form.inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        input, select, textarea {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            width: 100%;
            background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%);
            color: var(--text-primary);
            font-weight: 500;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: color-mix(in srgb, var(--primary) 86%, var(--accent) 14%); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 24%, var(--background)); }
        textarea { resize: vertical; }
        label { font-weight: 600; color: var(--text-primary); display:block; margin-bottom:6px; }
        .input { display:flex; flex-direction:column; gap:6px; }
        .input span { color: var(--text-primary); font-weight:700; font-size: 14px; }
        .muted { color: var(--text-secondary); font-size: 13px; }
        .hint { color: var(--text-secondary); font-size: 13px; }
        .kanban { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .column { background: var(--surface); padding: 14px; border-radius: 12px; border: 1px solid var(--border); box-shadow: none; }
        .column h3 { margin: 0 0 10px 0; font-size: 12px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
        .card-task { background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); border-radius: 10px; padding: 12px; border: 1px solid var(--border); margin-bottom: 10px; }
        .pill { border-radius: 999px; padding: 6px 12px; font-size: 11px; font-weight: 600; background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); color: var(--text-primary); border: 1px solid var(--border); }
        .pillset { display:flex; gap:6px; flex-wrap:wrap; }
        .text-muted { color: var(--text-secondary); }
        .alert { padding:12px; border-radius: 12px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); color: var(--text-primary); }
        .table-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .chip-list { display:flex; flex-wrap:wrap; gap:8px; }
        .table-wrapper { overflow-x: auto; }
        .catalog-domain-list { display:flex; flex-direction:column; gap:14px; }
        .catalog-domain-card { border:1px solid var(--border); border-radius:16px; background: var(--surface); box-shadow: 0 10px 24px color-mix(in srgb, var(--text-primary) 10%, var(--background)); }
        .catalog-domain-card summary { list-style:none; display:flex; align-items:center; justify-content:space-between; padding:12px 16px; cursor:pointer; }
        .catalog-domain-card summary::-webkit-details-marker { display:none; }
        .catalog-domain-card[open] summary { border-bottom:1px solid var(--border); background: color-mix(in srgb, var(--surface) 96%, var(--background) 4%); }
        .catalog-domain-title { display:flex; align-items:center; gap:10px; font-size:14px; }
        .catalog-domain-icon { font-size:18px; }
        .catalog-domain-body { padding:14px 16px; display:flex; flex-direction:column; gap:12px; }
        .catalog-stack { display:flex; flex-direction:column; gap:12px; }
        .catalog-list { display:flex; flex-direction:column; gap:12px; }
        .catalog-subgroup { display:flex; flex-direction:column; gap:8px; }
        .catalog-subgroup-header { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .catalog-domain-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:12px; }
        .catalog-block { padding: 12px; }
        .catalog-table-block { border:1px solid var(--border); border-radius:14px; padding:12px; background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); display:flex; flex-direction:column; gap:12px; }
        .catalog-table-header { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .catalog-meta { font-size:12px; color: var(--text-secondary); }
        .catalog-fields { display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:10px; }
        .catalog-field { font-size:12px; color: var(--text-secondary); margin:0; }
        .catalog-field input, .catalog-field select { margin-top:6px; }
        .catalog-impact { display:flex; flex-direction:column; gap:6px; }
        .catalog-impact.compact-impact { gap:4px; }
        .catalog-impact-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:8px; }
        .catalog-toggle { justify-content:space-between; padding:6px 10px; border-radius:12px; border:1px solid color-mix(in srgb, var(--primary) 18%, var(--border)); background: color-mix(in srgb, var(--primary) 6%, var(--surface)); }
        .catalog-toggle .toggle-label { font-size:12px; }
        .impact-switches { display:flex; flex-direction:column; gap:8px; }
        .catalog-table-form { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)) auto; gap:8px; align-items:center; }
        .catalog-table { display:flex; flex-direction:column; border:1px solid var(--border); border-radius:12px; overflow:hidden; background: var(--surface); }
        .catalog-table-row { display:grid; grid-template-columns: minmax(140px, 1fr) minmax(180px, 1.6fr) minmax(120px, 0.7fr) minmax(160px, 0.9fr); gap:10px; align-items:center; padding:10px 12px; }
        .catalog-table-row--header { background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%); font-size:11px; font-weight:700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; }
        .catalog-table-row--empty { grid-template-columns: 1fr; }
        .catalog-table-row--empty span { color: var(--text-secondary); font-size:12px; }
        .catalog-table-row + .catalog-table-row { border-top:1px solid var(--border); }
        .catalog-table-toggle { justify-content:space-between; }
        .catalog-table-actions { display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
        .catalog-empty { margin:0; color: var(--text-secondary); font-size:13px; }
        .risk-matrix { display:flex; flex-direction:column; border:1px solid var(--border); border-radius:14px; overflow:hidden; }
        .risk-matrix-header, .risk-matrix-row { display:grid; grid-template-columns: 1.7fr 0.8fr 1.4fr 0.7fr 0.7fr 0.9fr; gap:12px; align-items:center; padding:12px 14px; }
        .risk-matrix-header { background: color-mix(in srgb, var(--surface) 94%, var(--background) 6%); font-size:12px; font-weight:700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; }
        .risk-matrix-row { border-top:1px solid var(--border); background: var(--surface); }
        .risk-matrix-row:nth-child(even) { background: color-mix(in srgb, var(--surface) 96%, var(--background) 4%); }
        .risk-main { display:flex; flex-direction:column; gap:6px; }
        .risk-meta-grid { display:grid; grid-template-columns: repeat(2, minmax(120px, 1fr)); gap:8px; }
        .risk-matrix .catalog-field { font-size:11px; margin:0; }
        .risk-matrix .catalog-field input, .risk-matrix .catalog-field select { margin-top:4px; padding:8px 10px; }
        .risk-level { display:flex; flex-direction:column; gap:6px; align-items:flex-start; }
        .risk-level-pill { padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; border:1px solid var(--border); }
        .risk-level-meta { font-size:11px; color: var(--text-secondary); }
        .risk-level-high { background: color-mix(in srgb, var(--danger) 15%, var(--surface)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 40%, var(--border)); }
        .risk-level-mid { background: color-mix(in srgb, var(--warning) 18%, var(--surface)); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 45%, var(--border)); }
        .risk-level-low { background: color-mix(in srgb, var(--success) 15%, var(--surface)); color: var(--success); border-color: color-mix(in srgb, var(--success) 40%, var(--border)); }
        .notification-recipient-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-top:8px; }
        .toggle.compact-toggle { justify-content:flex-start; }
        .pillset.compact-pills { gap:6px; }
        .pillset-title { font-weight:700; color: var(--text-secondary); font-size:12px; margin:0 0 6px 0; }
        .toggle { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color: var(--text-secondary); }
        .toggle input { position:absolute; opacity:0; pointer-events:none; }
        .toggle-ui { width:36px; height:20px; border-radius:999px; background: color-mix(in srgb, var(--text-secondary) 20%, var(--background)); border:1px solid var(--border); position:relative; transition: background 160ms ease; }
        .toggle-ui::after { content:\"\"; position:absolute; width:14px; height:14px; border-radius:50%; background: var(--surface); top:2px; left:2px; transition: transform 160ms ease; box-shadow: 0 2px 6px color-mix(in srgb, var(--text-primary) 20%, var(--background)); }
        .toggle-text::before { content:\"OFF\"; }
        .toggle input:checked + .toggle-ui { background: color-mix(in srgb, var(--success) 45%, var(--background)); border-color: color-mix(in srgb, var(--success) 60%, var(--border)); }
        .toggle input:checked + .toggle-ui::after { transform: translateX(16px); }
        .toggle input:checked + .toggle-ui + .toggle-text::before { content:\"ON\"; color: var(--success); }
        .toggle input:disabled + .toggle-ui { opacity:0.7; }
        .config-flow-roles { display:flex; flex-direction:column; gap:6px; padding:8px 10px; border:1px dashed var(--border); border-radius:10px; background: var(--surface); }
        .config-flow-roles strong { font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); }
        @media (max-width: 980px) {
            .risk-matrix-header { display:none; }
            .risk-matrix-row { grid-template-columns: 1fr; }
            .risk-matrix-row > * { width:100%; }
            .catalog-table-form { grid-template-columns: 1fr; }
            .catalog-table-row { grid-template-columns: 1fr; }
            .catalog-table-actions { justify-content:flex-start; }
        }
        @media (max-width: 1180px) {
            .section-grid.twothirds, .section-grid.wide { grid-template-columns: 1fr; }
        }
        .menu-toggle { display: none; align-items: center; gap: 10px; font-weight: 700; color: color-mix(in srgb, var(--surface) 90%, var(--text-primary) 10%); }
        .menu-toggle label { padding: 8px 10px; border-radius: 10px; border: 1px solid color-mix(in srgb, var(--border) 70%, var(--background)); background: color-mix(in srgb, var(--surface) 12%, var(--background)); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; color: color-mix(in srgb, var(--surface) 90%, var(--text-primary) 10%); }
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
            <a href="<?= $basePath ?>/dashboard" class="nav-link <?= ($normalizedPath === '/dashboard' || $normalizedPath === '/') ? 'active' : '' ?>" data-tone="indigo">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 13h7v8H3z"/><path d="M14 3h7v18h-7z"/><path d="M3 3h7v6H3z"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?= $basePath ?>/projects" class="nav-link <?= str_starts_with($normalizedPath, '/projects') ? 'active' : '' ?>" data-tone="sky">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8.5a2.5 2.5 0 0 1-2.5 2.5H5.5A2.5 2.5 0 0 1 3 17.5z"/><path d="M3 10h18"/></svg></span>
                <span class="nav-label">Proyectos</span>
            </a>
            <a href="<?= $basePath ?>/clients" class="nav-link <?= str_starts_with($normalizedPath, '/clients') ? 'active' : '' ?>" data-tone="cyan">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/><path d="M16 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3Z"/><path d="M2 20a6 6 0 0 1 12 0"/><path d="M13 20a5 5 0 0 1 9 0"/></svg></span>
                <span class="nav-label">Clientes</span>
            </a>

            <div class="nav-divider" aria-hidden="true"></div>
            <span class="nav-section-label">Gestión</span>
            <a href="<?= $basePath ?>/outsourcing" class="nav-link <?= str_starts_with($normalizedPath, '/outsourcing') ? 'active' : '' ?>" data-tone="teal">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 8h16"/><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M10 13h4"/></svg></span>
                <span class="nav-label">Outsourcing</span>
            </a>
            <a href="<?= $basePath ?>/approvals" class="nav-link <?= str_starts_with($normalizedPath, '/approvals') ? 'active' : '' ?>" data-tone="amber">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 5.3 3.4 8.8 7 10 3.6-1.2 7-4.7 7-10V6z"/><path d="m9 12 2 2 4-4"/></svg></span>
                <span class="nav-label">Aprobaciones</span>
                <?php if (!empty($approvalBadgeCount)): ?>
                    <span class="nav-badge" aria-label="Aprobaciones pendientes"><?= (int) $approvalBadgeCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $basePath ?>/tasks" class="nav-link <?= str_starts_with($normalizedPath, '/tasks') ? 'active' : '' ?>" data-tone="violet">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h7"/><path d="M9 12h7"/><path d="M9 16h5"/><path d="m6.5 8 .5.5 1-1"/><path d="m6.5 12 .5.5 1-1"/><path d="m6.5 16 .5.5 1-1"/></svg></span>
                <span class="nav-label">Tareas</span>
            </a>
            <?php if ($timesheetsEnabled): ?>
                <a href="<?= $basePath ?>/timesheets" class="nav-link <?= str_starts_with($normalizedPath, '/timesheets') ? 'active' : '' ?>" data-tone="pink">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 13V9"/><path d="m12 13 3 2"/><path d="M9 3h6"/><path d="M12 3v2"/></svg></span>
                    <span class="nav-label">Timesheet</span>
                    <?php if (!empty($timesheetPendingCount)): ?>
                        <span class="nav-badge" aria-label="Timesheets pendientes"><?= (int) $timesheetPendingCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/talents" class="nav-link <?= str_starts_with($normalizedPath, '/talents') ? 'active' : '' ?>" data-tone="green">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 11a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 9 11Z"/><path d="M16.5 10a2.5 2.5 0 1 0-2.5-2.5A2.5 2.5 0 0 0 16.5 10Z"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M13 20a4.5 4.5 0 0 1 8 0"/></svg></span>
                <span class="nav-label">Talento</span>
            </a>
            <div class="nav-group">
                <a href="<?= $basePath ?>/talent-capacity" class="nav-link <?= $isTalentCapacityRoute ? 'active' : '' ?>" data-tone="blue">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg></span>
                    <span class="nav-label">Carga talento</span>
                </a>
                <div class="nav-submenu">
                    <a href="<?= $basePath ?>/talent-capacity" class="nav-sublink <?= $isCapacityOverviewRoute ? 'active' : '' ?>">Vista de capacidad</a>
                    <a href="<?= $basePath ?>/talent-capacity/simulation" class="nav-sublink <?= $isCapacitySimulationRoute ? 'active' : '' ?>">Simulación de capacidad</a>
                </div>
            </div>

            <?php if(in_array($user['role'] ?? '', ['Administrador', 'PMO'], true)): ?>
                <div class="nav-divider" aria-hidden="true"></div>
                <span class="nav-section-label">Admin</span>
                <a href="<?= $basePath ?>/config" class="nav-link <?= str_starts_with($normalizedPath, '/config') ? 'active' : '' ?>" data-tone="orange">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.1a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.1a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.1a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6H20a2 2 0 1 1 0 4h-.1a1 1 0 0 0-.9.6"/></svg></span>
                    <span class="nav-label">Configuración</span>
                </a>
            <?php endif; ?>
            <div class="nav-divider" aria-hidden="true"></div>
            <a href="<?= $basePath ?>/logout" class="nav-link" data-tone="red">
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
