<?php
$theme = $theme ?? (new ThemeRepository())->getActiveTheme();
$timesheetsEnabled = $timesheetsEnabled ?? false;
$basePath = '';
$appDisplayName = $appName ?? 'PMO';
$logoUrl = !empty($theme['logo_url']) ? $theme['logo_url'] : '';
$logoCss = $logoUrl !== '' ? "url('{$logoUrl}')" : 'none';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = str_starts_with($requestPath, $basePath)
    ? (substr($requestPath, strlen($basePath)) ?: '/')
    : $requestPath;
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
        /* ── Reset & Base ── */
        *, *::before, *::after { box-sizing: border-box; }
        * { font-family: var(--font-family); }
        html { scroll-behavior: smooth; }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: color-mix(in srgb, var(--text-secondary) 22%, transparent); border-radius: 999px; }
        ::-webkit-scrollbar-thumb:hover { background: color-mix(in srgb, var(--text-secondary) 45%, transparent); }

        body {
            margin: 0;
            display: flex;
            background: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            font-weight: 400;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.5;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 258px;
            background: var(--secondary);
            color: var(--text-primary);
            height: 100vh;
            padding: 0 10px 24px;
            position: sticky;
            top: 0;
            overflow-y: auto;
            overflow-x: hidden;
            border-right: 1px solid color-mix(in srgb, var(--border) 40%, transparent);
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex-shrink: 0;
            transition: width 0.28s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar.collapsed { width: 66px; padding-inline: 7px; }
        .sidebar.collapsed .brand-name,
        .sidebar.collapsed .user-meta,
        .sidebar.collapsed .nav-title,
        .sidebar.collapsed .nav-label,
        .sidebar.collapsed .nav-section-label,
        .sidebar.collapsed .nav-badge { display: none; }
        .sidebar.collapsed .nav-link { justify-content: center; padding: 9px; }
        .sidebar.collapsed .nav-link::before { display: none; }
        .sidebar.collapsed .user-panel { justify-content: center; padding: 8px 4px; }
        .sidebar.collapsed .brand-box { justify-content: center; padding: 16px 4px; }
        .sidebar.collapsed .brand-mark img { height: 26px; }
        .sidebar.collapsed .brand-fallback { font-size: 13px; }

        /* Brand area */
        .brand-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 10px 14px;
            border-bottom: 1px solid color-mix(in srgb, var(--border) 25%, transparent);
            flex-shrink: 0;
        }
        .brand-mark { display: flex; align-items: center; justify-content: center; min-width: 30px; flex-shrink: 0; }
        .brand-box img { height: 26px; max-height: 32px; object-fit: contain; }
        .brand-name { font-weight: 700; color: var(--text-primary); font-size: 13.5px; letter-spacing: -0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .brand-fallback { font-weight: 800; color: var(--text-primary); font-size: 16px; letter-spacing: 0.01em; }
        .brand-fallback.is-hidden { display: none; }

        /* Sidebar toggle */
        .sidebar-toggle {
            border: 1px solid color-mix(in srgb, var(--border) 35%, transparent);
            background: transparent;
            color: var(--text-secondary);
            border-radius: 7px;
            padding: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, color 0.15s;
            margin-left: auto;
            flex-shrink: 0;
        }
        .sidebar-toggle:hover { background: color-mix(in srgb, var(--surface) 10%, transparent); color: var(--text-primary); }
        .sidebar-toggle svg { width: 15px; height: 15px; stroke: currentColor; }

        /* User panel */
        .sidebar .user-panel {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 10px;
            border-radius: 10px;
            margin: 6px 0;
            background: color-mix(in srgb, var(--surface) 6%, transparent);
            border: 1px solid color-mix(in srgb, var(--border) 22%, transparent);
            transition: background 0.15s;
        }
        .sidebar .user-panel:hover { background: color-mix(in srgb, var(--surface) 10%, transparent); }

        .avatar {
            width: 33px;
            height: 33px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 52%, var(--accent)));
            color: #ffffff;
            flex-shrink: 0;
            letter-spacing: 0.02em;
            box-shadow: 0 2px 8px color-mix(in srgb, var(--primary) 35%, transparent);
        }
        .user-meta { display: flex; flex-direction: column; gap: 1px; min-width: 0; overflow: hidden; }
        .user-meta strong { color: var(--text-primary); font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-meta small { color: var(--text-secondary); font-size: 10.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; opacity: 0.8; }

        /* Nav sections */
        .nav-title { display: none; }
        .nav-section-label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 700;
            padding: 16px 10px 4px;
            letter-spacing: 0.1em;
            opacity: 0.55;
        }
        .nav-divider { height: 1px; background: color-mix(in srgb, var(--border) 22%, transparent); margin: 5px 0; }
        .sidebar nav { display: flex; flex-direction: column; gap: 1px; padding: 4px 0; }

        /* Nav links */
        .nav-link {
            --nav-tone: var(--primary);
            display: flex;
            align-items: center;
            gap: 9px;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 8px 10px;
            border-radius: 8px;
            position: relative;
            border: 1px solid transparent;
            font-weight: 500;
            font-size: 13px;
            overflow: hidden;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
        }
        .nav-link::before { display: none; }
        .nav-link::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(115deg, color-mix(in srgb, var(--nav-tone) 8%, transparent), transparent 52%);
            opacity: 0;
            transition: opacity 0.15s;
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
        .nav-link:hover { color: var(--text-primary); background: color-mix(in srgb, var(--surface) 9%, transparent); border-color: color-mix(in srgb, var(--nav-tone) 18%, transparent); }
        .nav-link:hover::after { opacity: 1; }
        .nav-link.active { color: var(--text-primary); font-weight: 600; background: color-mix(in srgb, var(--nav-tone) 13%, transparent); border-color: color-mix(in srgb, var(--nav-tone) 26%, transparent); }
        .nav-link.active::after { opacity: 1; }

        /* Nav icon */
        .nav-icon {
            width: 28px;
            height: 28px;
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--nav-tone);
            border-radius: 7px;
            background: color-mix(in srgb, var(--nav-tone) 13%, transparent);
            border: 1px solid color-mix(in srgb, var(--nav-tone) 18%, transparent);
            flex-shrink: 0;
            transition: background 0.15s, box-shadow 0.15s, color 0.15s;
        }
        .nav-link:hover .nav-icon { background: color-mix(in srgb, var(--nav-tone) 19%, transparent); border-color: color-mix(in srgb, var(--nav-tone) 32%, transparent); }
        .nav-link.active .nav-icon {
            background: linear-gradient(135deg, var(--nav-tone), color-mix(in srgb, var(--nav-tone) 70%, #0f172a));
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 3px 10px color-mix(in srgb, var(--nav-tone) 38%, transparent);
        }
        .nav-icon svg { width: 56%; height: 56%; stroke: currentColor; stroke-width: 2.1; fill: none; }
        .nav-icon--emoji { font-size: 13px; line-height: 1; }
        .nav-label { white-space: nowrap; position: relative; z-index: 1; flex: 1; overflow: hidden; text-overflow: ellipsis; }
        .nav-badge {
            margin-left: auto;
            min-width: 18px;
            padding: 1px 5px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            color: #ffffff;
            background: var(--accent);
            text-align: center;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }

        /* ── Topbar ── */
        .topbar {
            padding: 0 24px;
            height: 54px;
            display: flex;
            align-items: center;
            gap: 14px;
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--surface);
            border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, var(--surface));
            box-shadow: 0 1px 0 color-mix(in srgb, var(--text-primary) 4%, transparent), 0 2px 8px color-mix(in srgb, var(--text-primary) 3%, transparent);
        }
        .brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .brand-logo { height: 28px; max-height: 34px; width: auto; object-fit: contain; }
        .brand-title { font-weight: 700; color: var(--secondary); font-size: 15px; white-space: nowrap; letter-spacing: -0.01em; }
        .topbar .spacer { flex: 1; }
        .user-actions { display: flex; align-items: center; gap: 10px; }
        .user-summary { display: flex; align-items: center; gap: 9px; padding: 4px 0; }
        .user-identity { display: flex; flex-direction: column; gap: 2px; }
        .user-identity strong { color: var(--secondary); font-size: 13px; font-weight: 600; }
        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 5px;
            border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
            background: color-mix(in srgb, var(--primary) 9%, var(--surface));
            color: var(--primary);
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .logout-btn {
            padding: 6px 12px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: transparent;
            color: color-mix(in srgb, var(--secondary) 70%, var(--border));
            text-decoration: none;
            font-weight: 500;
            font-size: 12.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
            cursor: pointer;
        }
        .logout-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; flex-shrink: 0; }
        .logout-btn:hover { background: color-mix(in srgb, var(--danger) 8%, var(--surface)); border-color: color-mix(in srgb, var(--danger) 28%, var(--border)); color: var(--danger); }

        /* ── Layout ── */
        .impersonation-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin: 16px 24px 0;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid color-mix(in srgb, var(--accent) 38%, var(--border));
            background: color-mix(in srgb, var(--accent) 9%, var(--surface));
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 600;
        }
        .impersonation-banner strong { font-weight: 700; }
        .impersonation-banner .btn { padding: 6px 10px; font-size: 12px; }

        main { flex: 1; min-width: 0; min-height: 100vh; background: var(--background); display: flex; flex-direction: column; }
        .content { padding: 22px 28px 56px; display: flex; flex-direction: column; gap: 18px; flex: 1; }

        .page-heading { display: flex; flex-direction: column; gap: 4px; }
        .page-heading h2 { margin: 0; font-size: 21px; color: var(--text-primary); font-weight: 700; letter-spacing: -0.02em; line-height: 1.25; }
        .page-heading p { margin: 0; color: var(--text-secondary); font-size: 13px; font-weight: 400; }

        /* ── Grid helpers ── */
        .section-grid { display: grid; gap: 18px; align-items: start; }
        .section-grid.twothirds { grid-template-columns: 3fr 2fr; }
        .section-grid.wide { grid-template-columns: 2fr 3fr; }
        .config-columns { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); align-items: stretch; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .grid.tight { gap: 10px; }
        .config-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; align-items: start; }
        .config-form-grid.tight { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .config-form-grid.compact-grid { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
        .card.stretch { height: 100%; display: flex; flex-direction: column; }
        .card .card-content { display: flex; flex-direction: column; gap: 14px; flex: 1; }
        .form-block { display: flex; flex-direction: column; gap: 10px; padding: 12px; border: 1px solid var(--border); border-radius: 10px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); }
        .section-label { font-size: 11px; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-secondary); font-weight: 700; }
        .input-stack { display: flex; flex-direction: column; gap: 8px; }
        .palette-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
        .option-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .option { display: flex; align-items: center; gap: 8px; font-weight: 500; color: var(--text-primary); }
        .option.compact { gap: 4px; font-size: 13px; }
        .form-footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; grid-column: 1 / -1; }
        .card-stack { display: flex; flex-direction: column; gap: 12px; }
        .cards-grid { align-items: stretch; }
        .preview-pane { background: color-mix(in srgb, var(--surface) 10%, var(--background)); padding: 16px; border-radius: 12px; display: flex; flex-direction: column; gap: 10px; }
        .preview-header { display: flex; align-items: center; gap: 12px; }
        .preview-logo { height: 38px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); padding: 7px; border-radius: 8px; }
        .preview-subtitle { color: color-mix(in srgb, var(--surface) 72%, var(--background)); font-size: 12px; }

        /* ── Card ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 1px 2px color-mix(in srgb, var(--text-primary) 4%, transparent),
                        0 4px 16px color-mix(in srgb, var(--text-primary) 3%, transparent);
        }
        .card.ghosted { background: var(--background); border-style: dashed; }
        .alert { padding: 11px 14px; border-radius: 10px; border: 1px solid var(--border); background: color-mix(in srgb, var(--surface) 92%, var(--background)); color: var(--text-primary); font-size: 13.5px; }
        .alert.error { border-color: color-mix(in srgb, var(--danger) 32%, var(--border)); background: color-mix(in srgb, var(--danger) 9%, var(--surface)); color: var(--danger); }

        /* ── KPI ── */
        .kpi { display: flex; align-items: center; gap: 14px; }
        .kpi .label { color: var(--text-secondary); font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; }
        .kpi .value { font-weight: 700; font-size: 28px; color: var(--text-primary); letter-spacing: -0.025em; line-height: 1.1; }
        .kpi .meta { color: var(--text-secondary); font-size: 12px; }
        .kpi-icon {
            width: 48px; height: 48px; border-radius: 11px;
            background: color-mix(in srgb, var(--primary) 12%, var(--surface));
            display: inline-flex; align-items: center; justify-content: center;
            color: var(--primary); flex-shrink: 0;
            border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));
        }
        .kpi-icon svg { width: 22px; height: 22px; stroke: currentColor; fill: none; }
        .kpi-body { display: flex; flex-direction: column; gap: 3px; }

        /* ── Badges ── */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 600;
            background: color-mix(in srgb, var(--surface) 85%, var(--background));
            color: var(--text-primary); border: 1px solid var(--border);
        }
        .badge.success { background: color-mix(in srgb, var(--success) 12%, var(--surface)); color: var(--success); border-color: color-mix(in srgb, var(--success) 22%, var(--border)); }
        .badge.warning { background: color-mix(in srgb, var(--warning) 12%, var(--surface)); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 22%, var(--border)); }
        .badge.info { background: color-mix(in srgb, var(--info) 12%, var(--surface)); color: var(--info); border-color: color-mix(in srgb, var(--info) 22%, var(--border)); }
        .badge.danger { background: color-mix(in srgb, var(--danger) 12%, var(--surface)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 22%, var(--border)); }
        .badge.neutral { background: color-mix(in srgb, var(--neutral) 10%, var(--surface)); color: var(--text-primary); }

        /* ── Tables ── */
        table { width: 100%; border-collapse: collapse; background: var(--surface); margin-top: 8px; }
        th, td { padding: 11px 14px; border-bottom: 1px solid var(--border); text-align: left; line-height: 1.5; white-space: normal; overflow-wrap: break-word; word-break: break-word; font-size: 13.5px; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); font-weight: 700; background: color-mix(in srgb, var(--surface) 93%, var(--background)); }
        tr:last-child td { border-bottom: none; }
        tbody tr { transition: background 0.1s; }
        tbody tr:hover { background: color-mix(in srgb, var(--primary) 4%, var(--surface)); }

        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 12px; flex-wrap: wrap; }

        /* ── Buttons ── */
        .btn {
            padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border);
            cursor: pointer; font-weight: 600; font-size: 13px;
            background: var(--surface); color: var(--text-primary);
            display: inline-flex; align-items: center; gap: 6px;
            transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s, transform 0.1s;
            text-decoration: none; white-space: nowrap; line-height: 1.4;
        }
        .btn svg { width: 14px; height: 14px; stroke: currentColor; flex-shrink: 0; fill: none; }
        .btn.sm { padding: 4px 10px; border-radius: 6px; font-size: 11.5px; }
        .btn.sm svg { width: 12px; height: 12px; }
        .btn.primary {
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 72%, var(--accent)));
            color: #ffffff; border-color: transparent;
            box-shadow: 0 1px 5px color-mix(in srgb, var(--primary) 24%, transparent);
        }
        .btn.primary:hover { box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 34%, transparent); transform: translateY(-1px); filter: brightness(1.06); }
        .btn.primary:active { transform: translateY(0); }
        .btn.secondary { background: var(--surface); }
        .btn.ghost { background: transparent; border-style: dashed; }
        .btn.ghost:hover { background: color-mix(in srgb, var(--surface) 6%, transparent); }
        .btn.danger { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 26%, var(--border)); background: color-mix(in srgb, var(--danger) 7%, var(--surface)); }
        .btn.warning { color: var(--warning); border-color: color-mix(in srgb, var(--warning) 26%, var(--border)); background: color-mix(in srgb, var(--warning) 7%, var(--surface)); }
        .btn.danger:hover { background: color-mix(in srgb, var(--danger) 14%, var(--surface)); border-color: color-mix(in srgb, var(--danger) 40%, var(--border)); }
        .btn.warning:hover { background: color-mix(in srgb, var(--warning) 14%, var(--surface)); border-color: color-mix(in srgb, var(--warning) 40%, var(--border)); }
        .btn:not(.primary):not(.danger):not(.warning):not(.ghost):hover { background: color-mix(in srgb, var(--primary) 7%, var(--surface)); border-color: color-mix(in srgb, var(--primary) 20%, var(--border)); color: var(--primary); }

        /* ── Forms ── */
        form.inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        input, select, textarea {
            padding: 9px 12px; border: 1px solid var(--border); border-radius: 8px;
            width: 100%; background: var(--surface); color: var(--text-primary);
            font-size: 13.5px; font-weight: 400;
            transition: border-color 0.15s, box-shadow 0.15s; outline: none;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 15%, transparent); }
        input::placeholder { color: var(--text-secondary); opacity: 0.65; }
        textarea { resize: vertical; line-height: 1.5; }
        label { font-weight: 600; font-size: 13px; color: var(--text-primary); display: block; margin-bottom: 5px; }
        .input { display: flex; flex-direction: column; gap: 5px; }
        .input span { color: var(--text-primary); font-weight: 600; font-size: 13px; }
        .muted { color: var(--text-secondary); font-size: 13px; }
        .hint { color: var(--text-secondary); font-size: 12px; line-height: 1.4; }

        /* ── Kanban ── */
        .kanban { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .column { background: var(--surface); padding: 14px; border-radius: 12px; border: 1px solid var(--border); }
        .column h3 { margin: 0 0 10px; font-size: 10.5px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }
        .card-task { background: color-mix(in srgb, var(--surface) 90%, var(--background)); border-radius: 9px; padding: 10px 12px; border: 1px solid var(--border); margin-bottom: 8px; transition: box-shadow 0.15s; }
        .card-task:hover { box-shadow: 0 4px 12px color-mix(in srgb, var(--text-primary) 7%, transparent); }

        /* ── Pills ── */
        .pill { border-radius: 999px; padding: 4px 11px; font-size: 11px; font-weight: 600; background: color-mix(in srgb, var(--surface) 85%, var(--background)); color: var(--text-secondary); border: 1px solid var(--border); display: inline-flex; align-items: center; gap: 5px; }
        .pillset { display: flex; gap: 6px; flex-wrap: wrap; }
        .text-muted { color: var(--text-secondary); }
        .table-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .chip-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .table-wrapper { overflow-x: auto; }

        /* ── Catalog components ── */
        .catalog-domain-list { display: flex; flex-direction: column; gap: 12px; }
        .catalog-domain-card { border: 1px solid var(--border); border-radius: 14px; background: var(--surface); box-shadow: 0 2px 8px color-mix(in srgb, var(--text-primary) 4%, transparent); }
        .catalog-domain-card summary { list-style: none; display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; cursor: pointer; user-select: none; }
        .catalog-domain-card summary::-webkit-details-marker { display: none; }
        .catalog-domain-card[open] summary { border-bottom: 1px solid var(--border); background: color-mix(in srgb, var(--surface) 96%, var(--background)); border-radius: 14px 14px 0 0; }
        .catalog-domain-title { display: flex; align-items: center; gap: 10px; font-size: 13.5px; font-weight: 600; }
        .catalog-domain-icon { font-size: 16px; }
        .catalog-domain-body { padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; }
        .catalog-stack { display: flex; flex-direction: column; gap: 12px; }
        .catalog-list { display: flex; flex-direction: column; gap: 10px; }
        .catalog-subgroup { display: flex; flex-direction: column; gap: 8px; }
        .catalog-subgroup-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .catalog-domain-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px; }
        .catalog-block { padding: 12px; }
        .catalog-table-block { border: 1px solid var(--border); border-radius: 12px; padding: 12px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); display: flex; flex-direction: column; gap: 12px; }
        .catalog-table-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .catalog-meta { font-size: 12px; color: var(--text-secondary); }
        .catalog-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
        .catalog-field { font-size: 12px; color: var(--text-secondary); margin: 0; }
        .catalog-field input, .catalog-field select { margin-top: 5px; }
        .catalog-impact { display: flex; flex-direction: column; gap: 6px; }
        .catalog-impact.compact-impact { gap: 4px; }
        .catalog-impact-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; }
        .catalog-toggle { justify-content: space-between; padding: 6px 10px; border-radius: 10px; border: 1px solid color-mix(in srgb, var(--primary) 15%, var(--border)); background: color-mix(in srgb, var(--primary) 5%, var(--surface)); }
        .catalog-toggle .toggle-label { font-size: 12px; }
        .impact-switches { display: flex; flex-direction: column; gap: 8px; }
        .catalog-table-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)) auto; gap: 8px; align-items: center; }
        .catalog-table { display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--surface); }
        .catalog-table-row { display: grid; grid-template-columns: minmax(140px, 1fr) minmax(180px, 1.6fr) minmax(120px, 0.7fr) minmax(160px, 0.9fr); gap: 10px; align-items: center; padding: 10px 12px; }
        .catalog-table-row--header { background: color-mix(in srgb, var(--surface) 94%, var(--background)); font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; }
        .catalog-table-row--empty { grid-template-columns: 1fr; }
        .catalog-table-row--empty span { color: var(--text-secondary); font-size: 12px; }
        .catalog-table-row + .catalog-table-row { border-top: 1px solid var(--border); }
        .catalog-table-toggle { justify-content: space-between; }
        .catalog-table-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
        .catalog-empty { margin: 0; color: var(--text-secondary); font-size: 13px; }

        /* ── Risk matrix ── */
        .risk-matrix { display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .risk-matrix-header, .risk-matrix-row { display: grid; grid-template-columns: 1.7fr 0.8fr 1.4fr 0.7fr 0.7fr 0.9fr; gap: 12px; align-items: center; padding: 11px 14px; }
        .risk-matrix-header { background: color-mix(in srgb, var(--surface) 94%, var(--background)); font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; }
        .risk-matrix-row { border-top: 1px solid var(--border); background: var(--surface); transition: background 0.1s; }
        .risk-matrix-row:nth-child(even) { background: color-mix(in srgb, var(--surface) 96%, var(--background)); }
        .risk-matrix-row:hover { background: color-mix(in srgb, var(--primary) 4%, var(--surface)); }
        .risk-main { display: flex; flex-direction: column; gap: 5px; }
        .risk-meta-grid { display: grid; grid-template-columns: repeat(2, minmax(120px, 1fr)); gap: 8px; }
        .risk-matrix .catalog-field { font-size: 11px; margin: 0; }
        .risk-matrix .catalog-field input, .risk-matrix .catalog-field select { margin-top: 4px; padding: 7px 10px; }
        .risk-level { display: flex; flex-direction: column; gap: 5px; align-items: flex-start; }
        .risk-level-pill { padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; border: 1px solid var(--border); }
        .risk-level-meta { font-size: 11px; color: var(--text-secondary); }
        .risk-level-high { background: color-mix(in srgb, var(--danger) 11%, var(--surface)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 32%, var(--border)); }
        .risk-level-mid { background: color-mix(in srgb, var(--warning) 13%, var(--surface)); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 38%, var(--border)); }
        .risk-level-low { background: color-mix(in srgb, var(--success) 11%, var(--surface)); color: var(--success); border-color: color-mix(in srgb, var(--success) 32%, var(--border)); }

        .notification-recipient-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 8px; }
        .toggle.compact-toggle { justify-content: flex-start; }
        .pillset.compact-pills { gap: 6px; }
        .pillset-title { font-weight: 700; color: var(--text-secondary); font-size: 11px; margin: 0 0 5px; letter-spacing: 0.05em; text-transform: uppercase; }

        /* ── Toggle switch ── */
        .toggle { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); cursor: pointer; }
        .toggle input { position: absolute; opacity: 0; pointer-events: none; }
        .toggle-ui { width: 34px; height: 19px; border-radius: 999px; background: color-mix(in srgb, var(--text-secondary) 18%, var(--background)); border: 1px solid var(--border); position: relative; transition: background 0.18s, border-color 0.18s; }
        .toggle-ui::after { content: ""; position: absolute; width: 13px; height: 13px; border-radius: 50%; background: #ffffff; top: 2px; left: 2px; transition: transform 0.18s cubic-bezier(0.4,0,0.2,1); box-shadow: 0 1px 4px rgba(0,0,0,0.18); }
        .toggle-text::before { content: "OFF"; }
        .toggle input:checked + .toggle-ui { background: var(--success); border-color: color-mix(in srgb, var(--success) 55%, var(--border)); }
        .toggle input:checked + .toggle-ui::after { transform: translateX(15px); }
        .toggle input:checked + .toggle-ui + .toggle-text::before { content: "ON"; color: var(--success); }
        .toggle input:disabled + .toggle-ui { opacity: 0.6; }

        .config-flow-roles { display: flex; flex-direction: column; gap: 6px; padding: 8px 10px; border: 1px dashed var(--border); border-radius: 9px; background: var(--surface); }
        .config-flow-roles strong { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); }

        /* ── Responsive ── */
        @media (max-width: 980px) {
            .risk-matrix-header { display: none; }
            .risk-matrix-row { grid-template-columns: 1fr; }
            .risk-matrix-row > * { width: 100%; }
            .catalog-table-form { grid-template-columns: 1fr; }
            .catalog-table-row { grid-template-columns: 1fr; }
            .catalog-table-actions { justify-content: flex-start; }
        }
        @media (max-width: 1180px) {
            .section-grid.twothirds, .section-grid.wide { grid-template-columns: 1fr; }
        }
        .menu-toggle { display: none; align-items: center; gap: 10px; font-weight: 700; color: var(--text-primary); }
        .menu-toggle label { padding: 7px; border-radius: 8px; border: 1px solid color-mix(in srgb, var(--border) 40%, transparent); background: color-mix(in srgb, var(--surface) 9%, transparent); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; color: var(--text-primary); }
        .menu-toggle svg { width: 17px; height: 17px; stroke: currentColor; }
        #menu-toggle { display: none; }
        @media (max-width: 1024px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; min-height: unset; overflow: visible; border-right: none; border-bottom: 1px solid var(--border); padding: 12px 14px; gap: 8px; }
            .menu-toggle { display: flex; justify-content: space-between; align-items: center; }
            .sidebar nav { display: none; }
            #menu-toggle:checked ~ nav { display: flex; }
            .sidebar nav { flex-direction: column; }
            .nav-link { padding: 9px 10px; }
            main { width: 100%; }
            .content { padding: 16px 18px 40px; }
            .topbar { padding-inline: 18px; }
        }
        @media (max-width: 720px) {
            .topbar { flex-wrap: wrap; gap: 10px; height: auto; padding-block: 10px; }
            .user-actions { width: 100%; justify-content: space-between; }
            .user-summary { flex: 1; }
            .toolbar { flex-direction: column; align-items: flex-start; }
            .sidebar { padding-inline: 12px; }
            .page-heading h2 { font-size: 18px; }
            .content { padding: 14px 14px 40px; }
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
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 13h5v7H4zM10 4h4v16h-4zM16 9h4v11h-4z"/></svg></span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?= $basePath ?>/projects" class="nav-link <?= str_starts_with($normalizedPath, '/projects') ? 'active' : '' ?>" data-tone="sky">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 6h16v5H4zM4 13h9v5H4zM14 13l6 5"/></svg></span>
                <span class="nav-label">Proyectos</span>
            </a>
            <a href="<?= $basePath ?>/clients" class="nav-link <?= str_starts_with($normalizedPath, '/clients') ? 'active' : '' ?>" data-tone="cyan">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M5 7a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm8 1h6l-1 12h-4zM3 21a4 4 0 0 1 8 0"/></svg></span>
                <span class="nav-label">Clientes</span>
            </a>

            <div class="nav-divider" aria-hidden="true"></div>
            <span class="nav-section-label">Gestión</span>
            <a href="<?= $basePath ?>/outsourcing" class="nav-link <?= str_starts_with($normalizedPath, '/outsourcing') ? 'active' : '' ?>" data-tone="teal">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 7h16M4 12h16M4 17h10"/><path d="M16 17l2 2 4-4"/></svg></span>
                <span class="nav-label">Outsourcing</span>
            </a>
            <a href="<?= $basePath ?>/approvals" class="nav-link <?= str_starts_with($normalizedPath, '/approvals') ? 'active' : '' ?>" data-tone="amber">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 12h6l2 3 4-6h4"/><path d="M5 20h14"/><path d="M7 4h10v4H7z"/></svg></span>
                <span class="nav-label">Aprobaciones</span>
                <?php if (!empty($approvalBadgeCount)): ?>
                    <span class="nav-badge" aria-label="Aprobaciones pendientes"><?= (int) $approvalBadgeCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $basePath ?>/tasks" class="nav-link <?= str_starts_with($normalizedPath, '/tasks') ? 'active' : '' ?>" data-tone="violet">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M7 5h14M7 12h14M7 19h14M3 5h.01M3 12h.01M3 19h.01"/></svg></span>
                <span class="nav-label">Tareas</span>
            </a>
            <?php if ($timesheetsEnabled): ?>
                <a href="<?= $basePath ?>/timesheets" class="nav-link <?= str_starts_with($normalizedPath, '/timesheets') ? 'active' : '' ?>" data-tone="pink">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                    <span class="nav-label">Timesheet</span>
                    <?php if (!empty($timesheetPendingCount)): ?>
                        <span class="nav-badge" aria-label="Timesheets pendientes"><?= (int) $timesheetPendingCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/talents" class="nav-link <?= str_starts_with($normalizedPath, '/talents') ? 'active' : '' ?>" data-tone="green">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-6 8a6 6 0 0 1 12 0"/></svg></span>
                <span class="nav-label">Talento</span>
            </a>
            <a href="<?= $basePath ?>/talent-capacity" class="nav-link <?= str_starts_with($normalizedPath, '/talent-capacity') ? 'active' : '' ?>" data-tone="blue">
                <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M4 18h16"/><path d="M7 18v-5"/><path d="M12 18V6"/><path d="M17 18v-9"/></svg></span>
                <span class="nav-label">Carga talento</span>
            </a>

            <?php if(in_array($user['role'] ?? '', ['Administrador', 'PMO'], true)): ?>
                <div class="nav-divider" aria-hidden="true"></div>
                <span class="nav-section-label">Admin</span>
                <a href="<?= $basePath ?>/config" class="nav-link <?= str_starts_with($normalizedPath, '/config') ? 'active' : '' ?>" data-tone="orange">
                    <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6"><path d="M12 9a3 3 0 1 0 3 3 3 3 0 0 0-3-3Zm8-1-1.5-.5a2 2 0 0 1-1.3-1.3L17 4l-2-.5-1 1.8a2 2 0 0 1-1.7 1L10 6l-.5 2 1.8 1a2 2 0 0 1 1 1.7L12 14l2 .5 1-1.8a2 2 0 0 1 1.7-1l1.8-.2Z"/></svg></span>
                    <span class="nav-label">Configuración</span>
                </a>
            <?php endif; ?>
            <div class="nav-divider" aria-hidden="true"></div>
            <a href="<?= $basePath ?>/logout" class="nav-link" data-tone="red">
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
                    <a class="logout-btn" href="<?= $basePath ?>/logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Salir
                    </a>
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
