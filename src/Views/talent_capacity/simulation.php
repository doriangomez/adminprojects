<?php
$basePath   = $basePath ?? '';
$snapshot   = is_array($snapshot ?? null) ? $snapshot : [];
$simulation = is_array($simulation ?? null) ? $simulation : null;
$form       = is_array($form ?? null) ? $form : [];
$periodFrom = (string) ($period_from ?? date('Y-m'));
$periodTo   = (string) ($period_to ?? $periodFrom);

$hasResults = $simulation !== null && !empty($simulation['talents']);

$snapshotTotalCap   = 0.0;
$snapshotCommitted  = 0.0;
$snapshotAvailable  = 0.0;
foreach ($snapshot as $t) {
    $snapshotTotalCap  += (float) ($t['period_capacity'] ?? 0);
    $snapshotCommitted += (float) ($t['current_hours'] ?? 0);
    $snapshotAvailable += (float) ($t['available_hours'] ?? 0);
}
$snapshotUtilPct = $snapshotTotalCap > 0 ? round(($snapshotCommitted / $snapshotTotalCap) * 100, 1) : 0.0;

$monthNames = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
    '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
    '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
    '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
];

$formatMonth = static function (string $ym) use ($monthNames): string {
    [$y, $m] = explode('-', $ym . '-01');
    return ($monthNames[$m] ?? $m) . ' ' . $y;
};

$insightIcon = [
    'success' => '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>',
    'warning' => '<svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    'danger'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    'info'    => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
];

$statusClass = static function (string $status): string {
    return match($status) {
        'risk'    => 'sim-status-red',
        'warning' => 'sim-status-yellow',
        'ok'      => 'sim-status-green',
        default   => 'sim-status-none',
    };
};

$statusLabel = static function (string $status): string {
    return match($status) {
        'risk'    => 'Riesgo',
        'warning' => 'Atención',
        'ok'      => 'Saludable',
        default   => 'Sin carga',
    };
};
?>

<style>
    /* ── Simulation module layout ── */
    .sim-shell {
        padding: 28px 32px 48px;
        display: flex;
        flex-direction: column;
        gap: 28px;
        max-width: 1280px;
    }
    .sim-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .sim-header-left { display: flex; flex-direction: column; gap: 6px; }
    .sim-header-left .eyebrow {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #3b82f6;
        margin: 0;
    }
    .sim-header-left h2 {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        color: var(--text-primary);
        line-height: 1.2;
    }
    .sim-header-left p {
        margin: 0;
        font-size: 14px;
        color: var(--text-secondary);
    }
    .sim-header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

    /* ── Sub-tabs ── */
    .sim-tabs {
        display: flex;
        gap: 4px;
        background: color-mix(in srgb, var(--surface) 30%, var(--background));
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 5px;
        width: fit-content;
    }
    .sim-tab {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        text-decoration: none;
        transition: background 0.18s, color 0.18s, box-shadow 0.18s;
    }
    .sim-tab svg {
        width: 15px; height: 15px;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        fill: none;
        flex-shrink: 0;
    }
    .sim-tab:hover {
        background: color-mix(in srgb, var(--surface) 60%, var(--background));
        color: var(--text-primary);
    }
    .sim-tab.active {
        background: color-mix(in srgb, #3b82f6 18%, var(--surface));
        color: #3b82f6;
        box-shadow: 0 2px 8px color-mix(in srgb, #3b82f6 20%, transparent);
    }

    /* ── Period filter bar ── */
    .sim-period-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        background: color-mix(in srgb, var(--surface) 22%, var(--background));
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px 18px;
        flex-wrap: wrap;
    }
    .sim-period-bar label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    .sim-period-bar input[type="month"] {
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 13px;
        background: var(--background);
        color: var(--text-primary);
        font-weight: 500;
    }
    .sim-period-bar .sep {
        font-size: 13px;
        color: var(--text-secondary);
    }

    /* ── KPI row ── */
    .sim-kpi-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
    }
    .sim-kpi {
        background: color-mix(in srgb, var(--surface) 22%, var(--background));
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 20px 22px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        position: relative;
        overflow: hidden;
    }
    .sim-kpi::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(135deg, color-mix(in srgb, var(--kpi-color, #3b82f6) 8%, transparent), transparent 60%);
        pointer-events: none;
    }
    .sim-kpi .kpi-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-secondary);
        margin: 0;
    }
    .sim-kpi .kpi-value {
        font-size: 28px;
        font-weight: 800;
        color: var(--kpi-color, #3b82f6);
        line-height: 1;
        margin: 0;
    }
    .sim-kpi .kpi-sub {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0;
    }
    .sim-kpi-blue   { --kpi-color: #3b82f6; }
    .sim-kpi-green  { --kpi-color: #22c55e; }
    .sim-kpi-amber  { --kpi-color: #f59e0b; }
    .sim-kpi-red    { --kpi-color: #ef4444; }

    /* ── Form card ── */
    .sim-form-card {
        background: color-mix(in srgb, var(--surface) 22%, var(--background));
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 28px;
        display: flex;
        flex-direction: column;
        gap: 22px;
    }
    .sim-form-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0;
    }
    .sim-form-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: linear-gradient(145deg, color-mix(in srgb, #3b82f6 28%, #fff), color-mix(in srgb, #3b82f6 18%, var(--surface)));
        border: 1px solid color-mix(in srgb, #3b82f6 50%, var(--border));
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .sim-form-icon svg {
        width: 18px; height: 18px;
        stroke: #3b82f6;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        fill: none;
    }
    .sim-form-title-text { display: flex; flex-direction: column; gap: 2px; }
    .sim-form-title-text strong { font-size: 16px; font-weight: 800; color: var(--text-primary); }
    .sim-form-title-text small { font-size: 13px; color: var(--text-secondary); font-weight: 500; }

    .sim-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        align-items: end;
    }
    .sim-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .sim-field label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-secondary);
    }
    .sim-field input {
        padding: 10px 14px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: var(--background);
        color: var(--text-primary);
        font-size: 14px;
        font-weight: 600;
        transition: border-color 0.18s, box-shadow 0.18s;
        width: 100%;
        box-sizing: border-box;
    }
    .sim-field input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px color-mix(in srgb, #3b82f6 18%, transparent);
    }
    .sim-field input[type="month"] {
        cursor: pointer;
    }
    .sim-field .field-hint {
        font-size: 11px;
        color: var(--text-secondary);
        font-weight: 500;
    }
    .sim-period-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .sim-period-group .sim-field { flex: 1; }
    .sim-period-sep {
        font-size: 13px;
        color: var(--text-secondary);
        padding-top: 24px;
        white-space: nowrap;
    }
    .sim-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 24px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.18s, box-shadow 0.18s, background 0.18s;
        border: none;
        white-space: nowrap;
    }
    .sim-btn svg {
        width: 16px; height: 16px;
        stroke: currentColor; stroke-width: 2.2;
        stroke-linecap: round; stroke-linejoin: round;
        fill: none; flex-shrink: 0;
    }
    .sim-btn-primary {
        background: linear-gradient(140deg, #3b82f6, color-mix(in srgb, #3b82f6 70%, #1d4ed8));
        color: #fff;
        box-shadow: 0 4px 14px color-mix(in srgb, #3b82f6 35%, transparent);
    }
    .sim-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 20px color-mix(in srgb, #3b82f6 45%, transparent);
    }
    .sim-btn-secondary {
        background: color-mix(in srgb, var(--surface) 40%, var(--background));
        color: var(--text-primary);
        border: 1px solid var(--border);
    }
    .sim-btn-secondary:hover {
        transform: translateY(-1px);
        background: color-mix(in srgb, var(--surface) 55%, var(--background));
    }
    .sim-form-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

    /* ── Notice banner ── */
    .sim-notice {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        background: color-mix(in srgb, #f59e0b 10%, var(--background));
        border: 1px solid color-mix(in srgb, #f59e0b 35%, var(--border));
        color: color-mix(in srgb, #f59e0b 80%, var(--text-primary));
    }
    .sim-notice svg {
        width: 16px; height: 16px;
        stroke: currentColor; stroke-width: 2;
        stroke-linecap: round; stroke-linejoin: round;
        fill: none; flex-shrink: 0;
    }

    /* ── Section heading ── */
    .sim-section-head {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        flex-direction: column;
        margin-bottom: 4px;
    }
    .sim-section-head h3 { margin: 0; font-size: 17px; font-weight: 800; color: var(--text-primary); }
    .sim-section-head p  { margin: 0; font-size: 13px; color: var(--text-secondary); }

    /* ── Impact table ── */
    .sim-table-wrap {
        background: color-mix(in srgb, var(--surface) 22%, var(--background));
        border: 1px solid var(--border);
        border-radius: 20px;
        overflow: hidden;
    }
    .sim-table-header {
        padding: 20px 24px 0;
    }
    .sim-table { width: 100%; border-collapse: collapse; }
    .sim-table thead tr {
        background: color-mix(in srgb, var(--surface) 40%, var(--background));
    }
    .sim-table th {
        padding: 12px 16px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--text-secondary);
        text-align: left;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }
    .sim-table th.center { text-align: center; }
    .sim-table td {
        padding: 14px 16px;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, transparent);
        vertical-align: middle;
    }
    .sim-table td.center { text-align: center; }
    .sim-table tbody tr:last-child td { border-bottom: none; }
    .sim-table tbody tr:hover {
        background: color-mix(in srgb, var(--surface) 14%, var(--background));
    }
    .sim-talent-name { font-weight: 700; }
    .sim-talent-role { font-size: 12px; color: var(--text-secondary); font-weight: 500; }

    /* ── Progress bar ── */
    .sim-bar-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .sim-bar-track {
        flex: 1;
        height: 8px;
        background: color-mix(in srgb, var(--border) 60%, var(--background));
        border-radius: 99px;
        overflow: hidden;
        min-width: 80px;
    }
    .sim-bar-fill {
        height: 100%;
        border-radius: 99px;
        transition: width 0.4s ease;
    }
    .sim-bar-pct { font-size: 13px; font-weight: 700; white-space: nowrap; min-width: 42px; text-align: right; }
    .bar-green  { background: linear-gradient(90deg, #22c55e, #16a34a); }
    .bar-yellow { background: linear-gradient(90deg, #f59e0b, #d97706); }
    .bar-red    { background: linear-gradient(90deg, #ef4444, #dc2626); }
    .bar-none   { background: color-mix(in srgb, var(--border) 80%, var(--background)); }

    /* ── Status chip ── */
    .sim-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }
    .sim-chip::before {
        content: '';
        width: 6px; height: 6px;
        border-radius: 50%;
        background: currentColor;
        opacity: 0.9;
        flex-shrink: 0;
    }
    .sim-status-green  { background: color-mix(in srgb, #22c55e 14%, var(--background)); color: #16a34a; border: 1px solid color-mix(in srgb, #22c55e 30%, var(--border)); }
    .sim-status-yellow { background: color-mix(in srgb, #f59e0b 14%, var(--background)); color: #d97706; border: 1px solid color-mix(in srgb, #f59e0b 30%, var(--border)); }
    .sim-status-red    { background: color-mix(in srgb, #ef4444 14%, var(--background)); color: #dc2626; border: 1px solid color-mix(in srgb, #ef4444 30%, var(--border)); }
    .sim-status-none   { background: color-mix(in srgb, var(--border) 20%, var(--background)); color: var(--text-secondary); border: 1px solid var(--border); }

    /* ── Arrow indicator ── */
    .sim-arrow {
        display: inline-flex; align-items: center; gap: 3px;
        font-size: 12px; font-weight: 700;
    }
    .sim-arrow.up   { color: #ef4444; }
    .sim-arrow.same { color: var(--text-secondary); }
    .sim-arrow svg {
        width: 12px; height: 12px;
        stroke: currentColor; stroke-width: 2.5;
        stroke-linecap: round; stroke-linejoin: round;
        fill: none; flex-shrink: 0;
    }

    /* ── Insights panel ── */
    .sim-insights-wrap {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .sim-insight {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 18px;
        border-radius: 14px;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.5;
    }
    .sim-insight svg {
        width: 18px; height: 18px;
        stroke: currentColor; stroke-width: 2;
        stroke-linecap: round; stroke-linejoin: round;
        fill: none; flex-shrink: 0;
        margin-top: 1px;
    }
    .sim-insight.success {
        background: color-mix(in srgb, #22c55e 10%, var(--background));
        border: 1px solid color-mix(in srgb, #22c55e 28%, var(--border));
        color: color-mix(in srgb, #16a34a 90%, var(--text-primary));
    }
    .sim-insight.warning {
        background: color-mix(in srgb, #f59e0b 10%, var(--background));
        border: 1px solid color-mix(in srgb, #f59e0b 30%, var(--border));
        color: color-mix(in srgb, #d97706 90%, var(--text-primary));
    }
    .sim-insight.danger {
        background: color-mix(in srgb, #ef4444 10%, var(--background));
        border: 1px solid color-mix(in srgb, #ef4444 28%, var(--border));
        color: color-mix(in srgb, #dc2626 90%, var(--text-primary));
    }
    .sim-insight.info {
        background: color-mix(in srgb, #3b82f6 10%, var(--background));
        border: 1px solid color-mix(in srgb, #3b82f6 28%, var(--border));
        color: color-mix(in srgb, #2563eb 90%, var(--text-primary));
    }

    /* ── Snapshot table ── */
    .sim-snapshot-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 12px;
    }
    .sim-snapshot-card {
        background: color-mix(in srgb, var(--surface) 18%, var(--background));
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 16px 18px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .sim-snapshot-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .sim-snapshot-name { font-size: 14px; font-weight: 700; color: var(--text-primary); }
    .sim-snapshot-role { font-size: 12px; color: var(--text-secondary); }
    .sim-snapshot-hours {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        text-align: right;
    }
    .sim-snapshot-hours strong { display: block; font-size: 15px; color: var(--text-primary); }
    .sim-snapshot-bar-wrap {
        display: flex; flex-direction: column; gap: 4px;
    }
    .sim-snapshot-bar-track {
        height: 6px;
        background: color-mix(in srgb, var(--border) 60%, var(--background));
        border-radius: 99px;
        overflow: hidden;
    }
    .sim-snapshot-bar-fill { height: 100%; border-radius: 99px; }
    .sim-snapshot-pct { font-size: 11px; font-weight: 700; color: var(--text-secondary); }

    /* ── Empty state ── */
    .sim-empty {
        text-align: center;
        padding: 48px 24px;
        color: var(--text-secondary);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    .sim-empty svg {
        width: 48px; height: 48px;
        stroke: color-mix(in srgb, var(--border) 80%, var(--text-secondary));
        stroke-width: 1.5;
        stroke-linecap: round; stroke-linejoin: round;
        fill: none;
    }
    .sim-empty strong { font-size: 16px; font-weight: 700; color: var(--text-primary); }
    .sim-empty p { font-size: 13px; margin: 0; }

    /* ── Result badge ── */
    .sim-result-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 14px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .sim-result-badge.viable {
        background: color-mix(in srgb, #22c55e 14%, var(--background));
        color: #16a34a;
        border: 1px solid color-mix(in srgb, #22c55e 30%, var(--border));
    }
    .sim-result-badge.risk {
        background: color-mix(in srgb, #ef4444 14%, var(--background));
        color: #dc2626;
        border: 1px solid color-mix(in srgb, #ef4444 30%, var(--border));
    }
    .sim-result-badge svg {
        width: 13px; height: 13px;
        stroke: currentColor; stroke-width: 2.2;
        stroke-linecap: round; stroke-linejoin: round;
        fill: none;
    }

    /* ── Divider ── */
    .sim-divider {
        height: 1px;
        background: color-mix(in srgb, var(--border) 60%, transparent);
        margin: 4px 0;
    }
</style>

<section class="sim-shell">

    <!-- Header -->
    <header class="sim-header">
        <div class="sim-header-left">
            <p class="eyebrow">Gestión de Carga y Capacidad</p>
            <h2>Simulación de Capacidad</h2>
            <p>Simula el impacto de un nuevo proyecto en el equipo sin modificar datos reales.</p>
        </div>
        <div class="sim-header-actions">
            <!-- Sub-navigation tabs -->
            <nav class="sim-tabs" aria-label="Módulos de carga">
                <a href="<?= $basePath ?>/talent-capacity" class="sim-tab">
                    <svg viewBox="0 0 24 24"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg>
                    Vista de capacidad
                </a>
                <a href="<?= $basePath ?>/talent-capacity/simulation" class="sim-tab active">
                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/></svg>
                    Simulación
                </a>
            </nav>
        </div>
    </header>

    <!-- Notice: read-only simulation -->
    <div class="sim-notice">
        <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Solo simulación — esta vista no modifica proyectos, asignaciones ni datos reales del equipo.
    </div>

    <!-- KPI current snapshot -->
    <div class="sim-kpi-row">
        <div class="sim-kpi sim-kpi-blue">
            <p class="kpi-label">Capacidad total equipo</p>
            <p class="kpi-value"><?= number_format($snapshotTotalCap, 0) ?>h</p>
            <p class="kpi-sub">en el periodo seleccionado</p>
        </div>
        <div class="sim-kpi sim-kpi-amber">
            <p class="kpi-label">Capacidad comprometida</p>
            <p class="kpi-value"><?= number_format($snapshotCommitted, 0) ?>h</p>
            <p class="kpi-sub"><?= $snapshotUtilPct ?>% del total del equipo</p>
        </div>
        <div class="sim-kpi sim-kpi-green">
            <p class="kpi-label">Capacidad disponible</p>
            <p class="kpi-value"><?= number_format($snapshotAvailable, 0) ?>h</p>
            <p class="kpi-sub">libre para nuevas asignaciones</p>
        </div>
        <?php if ($hasResults): ?>
        <div class="sim-kpi sim-kpi-<?= ($simulation['summary']['overloaded_count'] ?? 0) > 0 ? 'red' : 'green' ?>">
            <p class="kpi-label">Capacidad restante (simulada)</p>
            <p class="kpi-value"><?= number_format((float) ($simulation['summary']['remaining_after'] ?? 0), 0) ?>h</p>
            <p class="kpi-sub">tras absorber "<?= htmlspecialchars($simulation['project_name'] ?? '') ?>"</p>
        </div>
        <?php else: ?>
        <div class="sim-kpi sim-kpi-blue" style="opacity:0.45;">
            <p class="kpi-label">Capacidad restante (simulada)</p>
            <p class="kpi-value">—</p>
            <p class="kpi-sub">ejecuta la simulación para ver</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Simulation Form -->
    <div class="sim-form-card">
        <div class="sim-form-title">
            <div class="sim-form-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/></svg>
            </div>
            <div class="sim-form-title-text">
                <strong>Proyecto a simular</strong>
                <small>Define los parámetros del proyecto hipotético para evaluar su impacto en el equipo.</small>
            </div>
        </div>

        <form method="POST" action="<?= $basePath ?>/talent-capacity/simulation">
            <div class="sim-form-grid">
                <div class="sim-field" style="grid-column: span 2; min-width: 0;">
                    <label for="sim_project_name">Nombre del proyecto</label>
                    <input
                        type="text"
                        id="sim_project_name"
                        name="project_name"
                        placeholder="Ej: Portal ecommerce B2B"
                        maxlength="120"
                        value="<?= htmlspecialchars((string) ($form['project_name'] ?? '')) ?>"
                        required
                    >
                    <span class="field-hint">Solo para identificar la simulación, no crea el proyecto.</span>
                </div>
                <div class="sim-field">
                    <label for="sim_hours">Horas estimadas del proyecto</label>
                    <input
                        type="number"
                        id="sim_hours"
                        name="estimated_hours"
                        min="1"
                        max="99999"
                        step="1"
                        placeholder="200"
                        value="<?= htmlspecialchars((string) ($form['estimated_hours'] ?? '')) ?>"
                        required
                    >
                    <span class="field-hint">Total de horas a distribuir entre el equipo.</span>
                </div>
                <div class="sim-field">
                    <label>Periodo de simulación</label>
                    <div class="sim-period-group">
                        <div class="sim-field" style="gap:0;">
                            <input
                                type="month"
                                name="period_from"
                                value="<?= htmlspecialchars((string) ($form['period_from'] ?? $periodFrom)) ?>"
                                required
                            >
                        </div>
                        <span class="sim-period-sep">→</span>
                        <div class="sim-field" style="gap:0;">
                            <input
                                type="month"
                                name="period_to"
                                value="<?= htmlspecialchars((string) ($form['period_to'] ?? $periodTo)) ?>"
                                required
                            >
                        </div>
                    </div>
                    <span class="field-hint">Mes inicial y mes final de ejecución.</span>
                </div>
            </div>

            <div class="sim-divider"></div>
            <div class="sim-form-actions">
                <button type="submit" class="sim-btn sim-btn-primary">
                    <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Ejecutar simulación
                </button>
                <?php if ($hasResults): ?>
                    <a href="<?= $basePath ?>/talent-capacity/simulation" class="sim-btn sim-btn-secondary">
                        <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                        Nueva simulación
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($hasResults): ?>
    <!-- ── SIMULATION RESULTS ── -->

    <!-- Result summary header -->
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <h3 style="margin:0; font-size:17px; font-weight:800; color:var(--text-primary);">
            Resultados — "<?= htmlspecialchars($simulation['project_name']) ?>"
        </h3>
        <span class="sim-chip <?= $statusClass($simulation['summary']['overloaded_count'] > 0 ? 'risk' : 'ok') ?>">
            <?= $simulation['estimated_hours'] ?>h estimadas
        </span>
        <span class="sim-result-badge <?= ($simulation['summary']['is_viable'] ?? false) ? 'viable' : 'risk' ?>">
            <?php if ($simulation['summary']['is_viable'] ?? false): ?>
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                Viable
            <?php else: ?>
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Riesgo detectado
            <?php endif; ?>
        </span>
        <span style="font-size:13px; color:var(--text-secondary); margin-left:auto;">
            Periodo: <?= htmlspecialchars($simulation['period_label']) ?>
        </span>
    </div>

    <!-- Impact table -->
    <div class="sim-table-wrap">
        <div class="sim-table-header">
            <div class="sim-section-head">
                <h3>Impacto en el equipo</h3>
                <p>Las horas simuladas se distribuyen proporcionalmente a la capacidad disponible de cada talento.</p>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table class="sim-table">
                <thead>
                    <tr>
                        <th>Talento</th>
                        <th class="center">Capacidad periodo</th>
                        <th class="center">Horas actuales</th>
                        <th class="center">+Horas simuladas</th>
                        <th class="center">Horas finales</th>
                        <th style="min-width:200px;">Utilización final</th>
                        <th class="center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($simulation['talents'] as $t): ?>
                        <?php
                            $util    = (float) ($t['utilization_final'] ?? 0);
                            $utilCur = (float) ($t['utilization_current'] ?? 0);
                            $diff    = $util - $utilCur;
                            $barClass = $util > 90 ? 'bar-red' : ($util >= 70 ? 'bar-yellow' : 'bar-green');
                            $barW = min(100, $util);
                            $stFinal  = (string) ($t['status_final'] ?? 'none');
                        ?>
                        <tr>
                            <td>
                                <div class="sim-talent-name"><?= htmlspecialchars((string) ($t['name'] ?? '')) ?></div>
                                <?php if (!empty($t['role'])): ?>
                                    <div class="sim-talent-role"><?= htmlspecialchars((string) $t['role']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="center"><?= number_format((float) ($t['period_capacity'] ?? 0), 0) ?>h</td>
                            <td class="center"><?= number_format((float) ($t['current_hours'] ?? 0), 1) ?>h</td>
                            <td class="center" style="color:#3b82f6; font-weight:700;">
                                +<?= number_format((float) ($t['simulated_hours'] ?? 0), 1) ?>h
                            </td>
                            <td class="center" style="font-weight:700;"><?= number_format((float) ($t['final_hours'] ?? 0), 1) ?>h</td>
                            <td>
                                <div class="sim-bar-wrap">
                                    <div class="sim-bar-track">
                                        <div class="sim-bar-fill <?= $barClass ?>" style="width:<?= min(100, $barW) ?>%;"></div>
                                    </div>
                                    <span class="sim-bar-pct" style="color:<?= $util > 90 ? '#ef4444' : ($util >= 70 ? '#f59e0b' : '#22c55e') ?>;">
                                        <?= number_format($util, 1) ?>%
                                    </span>
                                    <?php if ($diff > 0.5): ?>
                                        <span class="sim-arrow up">
                                            <svg viewBox="0 0 24 24"><path d="m18 15-6-6-6 6"/></svg>
                                            +<?= number_format($diff, 1) ?>%
                                        </span>
                                    <?php elseif ($diff < -0.5): ?>
                                        <span class="sim-arrow same">—</span>
                                    <?php else: ?>
                                        <span class="sim-arrow same">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="center">
                                <span class="sim-chip <?= $statusClass($stFinal) ?>">
                                    <?= $statusLabel($stFinal) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:color-mix(in srgb, var(--surface) 40%, var(--background));">
                        <td style="font-weight:800; font-size:13px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.05em;">TOTAL EQUIPO</td>
                        <td class="center" style="font-weight:700;"><?= number_format((float) ($simulation['summary']['total_capacity'] ?? 0), 0) ?>h</td>
                        <td class="center" style="font-weight:700;"><?= number_format((float) ($simulation['summary']['committed'] ?? 0), 1) ?>h</td>
                        <td class="center" style="color:#3b82f6; font-weight:800;">+<?= number_format((float) ($simulation['summary']['distributed_hours'] ?? 0), 1) ?>h</td>
                        <td class="center" style="font-weight:800;">
                            <?= number_format(
                                (float) ($simulation['summary']['committed'] ?? 0) + (float) ($simulation['summary']['distributed_hours'] ?? 0),
                                1
                            ) ?>h
                        </td>
                        <td colspan="2" style="font-size:13px; color:var(--text-secondary);">
                            <?= number_format((float) ($simulation['summary']['remaining_after'] ?? 0), 1) ?>h disponibles tras simulación
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Insights -->
    <?php if (!empty($simulation['insights'])): ?>
    <div>
        <div class="sim-section-head" style="margin-bottom:14px;">
            <h3>Insights del sistema</h3>
            <p>Análisis automático basado en los datos de capacidad actuales y la simulación ejecutada.</p>
        </div>
        <div class="sim-insights-wrap">
            <?php foreach ($simulation['insights'] as $insight): ?>
                <?php $type = (string) ($insight['type'] ?? 'info'); ?>
                <div class="sim-insight <?= htmlspecialchars($type) ?>">
                    <?= $insightIcon[$type] ?? $insightIcon['info'] ?>
                    <span><?= htmlspecialchars((string) ($insight['text'] ?? '')) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- ── NO RESULTS YET: Show current snapshot ── -->
    <div>
        <div class="sim-section-head" style="margin-bottom:16px;">
            <h3>Estado actual del equipo</h3>
            <p>Capacidad y ocupación del equipo en el periodo <?= htmlspecialchars($formatMonth($periodFrom)) ?><?= $periodFrom !== $periodTo ? ' – ' . htmlspecialchars($formatMonth($periodTo)) : '' ?>. Ingresa los datos del proyecto y ejecuta la simulación para ver el impacto.</p>
        </div>
        <?php if (empty($snapshot)): ?>
            <div class="sim-table-wrap">
                <div class="sim-empty">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                    <strong>Sin talentos activos</strong>
                    <p>No hay talentos activos registrados en el sistema. Agrega talentos en el módulo de Talento primero.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="sim-snapshot-grid">
                <?php foreach ($snapshot as $t): ?>
                    <?php
                        $util   = (float) ($t['utilization_current'] ?? 0);
                        $st     = (string) ($t['status_current'] ?? 'none');
                        $barClass = $util > 90 ? 'bar-red' : ($util >= 70 ? 'bar-yellow' : 'bar-green');
                    ?>
                    <div class="sim-snapshot-card">
                        <div class="sim-snapshot-card-top">
                            <div>
                                <div class="sim-snapshot-name"><?= htmlspecialchars((string) ($t['name'] ?? '')) ?></div>
                                <?php if (!empty($t['role'])): ?>
                                    <div class="sim-snapshot-role"><?= htmlspecialchars((string) $t['role']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="sim-snapshot-hours">
                                <strong><?= number_format((float) ($t['current_hours'] ?? 0), 0) ?>h</strong>
                                de <?= number_format((float) ($t['period_capacity'] ?? 0), 0) ?>h
                            </div>
                        </div>
                        <div class="sim-snapshot-bar-wrap">
                            <div class="sim-snapshot-bar-track">
                                <div class="sim-snapshot-bar-fill <?= $barClass ?>" style="width:<?= min(100, $util) ?>%;"></div>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span class="sim-snapshot-pct"><?= number_format($util, 1) ?>% utilizado</span>
                                <span class="sim-chip <?= $statusClass($st) ?>" style="font-size:10px; padding:2px 8px;">
                                    <?= $statusLabel($st) ?>
                                </span>
                            </div>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text-secondary);">
                            <span><?= number_format((float) ($t['available_hours'] ?? 0), 0) ?>h disponibles</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</section>
