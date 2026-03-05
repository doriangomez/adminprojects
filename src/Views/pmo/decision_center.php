<?php
/**
 * Centro de Decisiones PMO
 * Main executive decision-support view.
 */

$score      = (int) ($summary['score_general']          ?? 0);
$scoreLabel = $score >= 85 ? 'Excelente' : ($score >= 70 ? 'Bueno' : ($score >= 50 ? 'En riesgo' : 'Crítico'));
$scoreColor = $score >= 85 ? 'var(--success)' : ($score >= 70 ? 'var(--warning)' : 'var(--danger)');

$activeFilter   = $_GET['alert_filter'] ?? '';
$fromDate       = htmlspecialchars($filters['from'] ?? date('Y-m-01'));
$toDate         = htmlspecialchars($filters['to']   ?? date('Y-m-t'));

function dc_pill(string $level, string $text): string {
    $cls = match($level) {
        'red'   => 'dc-pill--red',
        'amber' => 'dc-pill--amber',
        default => 'dc-pill--green',
    };
    return '<span class="dc-pill ' . $cls . '">' . htmlspecialchars($text) . '</span>';
}

function dc_impact_badge(string $impact): string {
    $cls = match($impact) {
        'Alto'  => 'badge-red',
        'Medio' => 'badge-amber',
        default => 'badge-blue',
    };
    return '<span class="dc-badge ' . $cls . '">' . htmlspecialchars($impact) . '</span>';
}

function dc_status_pill(string $status): string {
    $map = [
        'critico'    => ['dc-pill--red',   'Crítico'],
        'riesgo'     => ['dc-pill--amber',  'En riesgo'],
        'normal'     => ['dc-pill--blue',   'Normal'],
        'disponible' => ['dc-pill--green',  'Disponible'],
    ];
    [$cls, $label] = $map[$status] ?? ['dc-pill--green', ucfirst($status)];
    return '<span class="dc-pill ' . $cls . '">' . $label . '</span>';
}

function dc_action_label(string $action): string {
    return match($action) {
        'ver_proyecto'   => 'Ver proyecto',
        'abrir_bloqueos' => 'Abrir bloqueos',
        'asignar_recurso'=> 'Asignar recurso',
        'ir_facturacion' => 'Ir a facturación',
        default          => 'Ver detalle',
    };
}

function dc_action_url(string $action, ?int $projectId): string {
    return match($action) {
        'ver_proyecto'   => '/projects/' . (int)$projectId,
        'abrir_bloqueos' => '/projects/' . (int)$projectId . '?view=bloqueos',
        'asignar_recurso'=> '/talent-capacity',
        'ir_facturacion' => $projectId ? '/projects/' . (int)$projectId . '/billing' : '/projects/billing-report',
        default          => '#',
    };
}

function dc_health_pill(string $health): string {
    return match($health) {
        'on_track', 'green'  => '<span class="dc-pill dc-pill--green">En curso</span>',
        'at_risk',  'yellow' => '<span class="dc-pill dc-pill--amber">En riesgo</span>',
        'critical', 'red'    => '<span class="dc-pill dc-pill--red">Crítico</span>',
        default               => '<span class="dc-pill dc-pill--blue">' . htmlspecialchars(ucfirst($health) ?: '—') . '</span>',
    };
}

function dc_severity_pill(string $severity): string {
    return match($severity) {
        'critico' => '<span class="dc-pill dc-pill--red">Crítico</span>',
        'alto'    => '<span class="dc-pill dc-pill--amber">Alto</span>',
        'medio'   => '<span class="dc-pill dc-pill--blue">Medio</span>',
        'bajo'    => '<span class="dc-pill dc-pill--green">Bajo</span>',
        default   => '<span class="dc-pill dc-pill--blue">—</span>',
    };
}
?>

<div class="dc-root">
<style>
/* ── Decision Center Layout ─────────────────────────────── */
.dc-root {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-bottom: 24px;
}
.dc-card {
    background: color-mix(in srgb, var(--surface) 92%, var(--background));
    border: 1px solid color-mix(in srgb, var(--border) 85%, var(--background));
    border-radius: 16px;
    padding: 18px 20px;
    box-shadow: 0 8px 22px color-mix(in srgb, var(--text-primary) 8%, transparent);
}
.dc-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    gap: 12px;
    flex-wrap: wrap;
}
.dc-card-title {
    font-size: 16px;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dc-card-title svg { width: 18px; height: 18px; stroke: var(--primary); fill: none; stroke-width: 2; }

/* ── Filters bar ──────────────────────────────────────────── */
.dc-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
}
.dc-filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.dc-filter-group label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-secondary);
}
.dc-filter-group select,
.dc-filter-group input[type="date"] {
    padding: 7px 10px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 600;
    min-width: 140px;
}
.dc-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: opacity .15s;
}
.dc-btn:hover { opacity: .85; }
.dc-btn--primary { background: var(--primary); color: #fff; }
.dc-btn--secondary {
    background: color-mix(in srgb, var(--primary) 12%, var(--surface));
    color: var(--primary);
    border: 1px solid color-mix(in srgb, var(--primary) 30%, var(--border));
}
.dc-btn--sm { padding: 5px 12px; font-size: 12px; }

/* ── KPI Grid ─────────────────────────────────────────────── */
.dc-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}
.dc-kpi-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    border-radius: 14px;
    border: 1px solid color-mix(in srgb, var(--border) 70%, var(--background));
    background: color-mix(in srgb, var(--surface) 88%, var(--background));
}
.dc-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.dc-kpi-icon svg { width: 22px; height: 22px; fill: none; stroke-width: 2; stroke: currentColor; }
.dc-kpi-icon--blue   { background: color-mix(in srgb, #3b82f6 14%, var(--surface)); color: #3b82f6; }
.dc-kpi-icon--green  { background: color-mix(in srgb, var(--success) 14%, var(--surface)); color: var(--success); }
.dc-kpi-icon--red    { background: color-mix(in srgb, var(--danger) 14%, var(--surface)); color: var(--danger); }
.dc-kpi-icon--amber  { background: color-mix(in srgb, var(--warning) 14%, var(--surface)); color: #b45309; }
.dc-kpi-icon--indigo { background: color-mix(in srgb, #6366f1 14%, var(--surface)); color: #6366f1; }
.dc-kpi-icon--teal   { background: color-mix(in srgb, #14b8a6 14%, var(--surface)); color: #14b8a6; }
.dc-kpi-meta { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.dc-kpi-label { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--text-secondary); font-weight: 600; }
.dc-kpi-value { font-size: 28px; font-weight: 900; color: var(--text-primary); line-height: 1; }
.dc-kpi-sub   { font-size: 12px; color: var(--text-secondary); font-weight: 600; }

/* ── Score card ───────────────────────────────────────────── */
.dc-score-wrap {
    display: flex;
    align-items: center;
    gap: 18px;
}
.dc-score-circle {
    position: relative;
    width: 100px;
    height: 100px;
    flex-shrink: 0;
}
.dc-score-circle svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.dc-score-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.dc-score-num  { font-size: 26px; font-weight: 900; line-height: 1; }
.dc-score-text { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: var(--text-secondary); }

/* ── Alerts band ──────────────────────────────────────────── */
.dc-alerts-band {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
.dc-alert-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all .15s;
    text-decoration: none;
}
.dc-alert-chip:hover { transform: translateY(-1px); }
.dc-alert-chip.active { border-width: 2px; }
.dc-alert-chip--green {
    background: color-mix(in srgb, var(--success) 12%, var(--surface));
    color: var(--success);
    border-color: color-mix(in srgb, var(--success) 25%, transparent);
}
.dc-alert-chip--amber {
    background: color-mix(in srgb, var(--warning) 15%, var(--surface));
    color: #92400e;
    border-color: color-mix(in srgb, var(--warning) 40%, transparent);
}
.dc-alert-chip--red {
    background: color-mix(in srgb, var(--danger) 15%, var(--surface));
    color: var(--danger);
    border-color: color-mix(in srgb, var(--danger) 35%, transparent);
}
.dc-alert-chip.active {
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent);
}
.dc-chip-count {
    min-width: 22px;
    height: 22px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 900;
    background: rgba(0,0,0,.12);
    padding: 0 5px;
}

/* ── Recommendations ──────────────────────────────────────── */
.dc-decisions-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.dc-decision-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid color-mix(in srgb, var(--border) 72%, var(--background));
    background: color-mix(in srgb, var(--surface) 95%, var(--background));
    transition: border-color .15s;
}
.dc-decision-item:hover {
    border-color: color-mix(in srgb, var(--primary) 40%, var(--border));
}
.dc-decision-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 900;
    background: color-mix(in srgb, var(--primary) 14%, var(--surface));
    color: var(--primary);
    flex-shrink: 0;
}
.dc-decision-body { flex: 1; min-width: 0; }
.dc-decision-title { font-size: 14px; font-weight: 800; color: var(--text-primary); margin: 0 0 3px; }
.dc-decision-reason { font-size: 12px; color: var(--text-secondary); margin: 0; line-height: 1.4; }

/* ── Pills & badges ───────────────────────────────────────── */
.dc-pill {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
}
.dc-pill--green { background: color-mix(in srgb, var(--success) 15%, var(--surface)); color: var(--success); }
.dc-pill--amber { background: color-mix(in srgb, var(--warning) 18%, var(--surface)); color: #92400e; }
.dc-pill--red   { background: color-mix(in srgb, var(--danger) 18%, var(--surface)); color: var(--danger); }
.dc-pill--blue  { background: color-mix(in srgb, #3b82f6 14%, var(--surface)); color: #1d4ed8; }
.dc-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.badge-red   { background: color-mix(in srgb, var(--danger) 18%, var(--surface)); color: var(--danger); }
.badge-amber { background: color-mix(in srgb, var(--warning) 18%, var(--surface)); color: #92400e; }
.badge-blue  { background: color-mix(in srgb, #3b82f6 14%, var(--surface)); color: #1d4ed8; }

/* ── Tables ───────────────────────────────────────────────── */
.dc-table-wrap { overflow-x: auto; }
table.dc-table { width: 100%; border-collapse: collapse; }
table.dc-table th, table.dc-table td {
    padding: 10px 8px;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 65%, var(--background));
    text-align: left;
}
table.dc-table th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-secondary);
    font-weight: 700;
    white-space: nowrap;
}
table.dc-table td { font-size: 13px; font-weight: 600; color: var(--text-primary); }
table.dc-table tr:hover td { background: color-mix(in srgb, var(--primary) 4%, transparent); }
table.dc-table .dc-project-name { max-width: 220px; }
table.dc-table .dc-note-cell { max-width: 200px; }

.dc-note-preview {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: 12px;
    color: var(--text-secondary);
    cursor: pointer;
    position: relative;
}
.dc-note-preview:hover { color: var(--text-primary); }

/* ── Simulation Panel ─────────────────────────────────────── */
.dc-sim-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    align-items: start;
}
.dc-sim-form { display: flex; flex-direction: column; gap: 12px; }
.dc-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.dc-form-group { display: flex; flex-direction: column; gap: 5px; }
.dc-form-group label {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: .04em;
}
.dc-form-group input, .dc-form-group select {
    padding: 9px 12px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 600;
}
.dc-sim-result {
    padding: 18px;
    border-radius: 14px;
    border: 1px dashed var(--border);
    background: color-mix(in srgb, var(--surface) 90%, var(--background));
    min-height: 200px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.dc-sim-placeholder {
    margin: auto;
    text-align: center;
    color: var(--text-secondary);
    font-size: 13px;
}
.dc-sim-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.dc-sim-kpi  { text-align: center; }
.dc-sim-kpi-value { font-size: 26px; font-weight: 900; color: var(--text-primary); }
.dc-sim-kpi-label { font-size: 11px; color: var(--text-secondary); text-transform: uppercase; }
.dc-sim-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.dc-sim-list li { font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

/* ── Util bar ─────────────────────────────────────────────── */
.dc-util-bar {
    height: 8px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--border) 50%, var(--background));
    overflow: hidden;
    min-width: 80px;
}
.dc-util-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width .3s;
}

/* ── Two-column layout ────────────────────────────────────── */
.dc-two-col {
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    gap: 14px;
    align-items: start;
}

/* ── Errors banner ────────────────────────────────────────── */
.dc-errors-banner {
    padding: 12px 16px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--warning) 14%, var(--surface));
    border: 1px solid color-mix(in srgb, var(--warning) 35%, transparent);
    color: #92400e;
    font-size: 13px;
    font-weight: 600;
}
.dc-errors-banner ul { margin: 4px 0 0 18px; }

/* ── Tooltip ─────────────────────────────────────────────── */
.dc-tooltip-wrap { position: relative; display: inline-block; }
.dc-tooltip {
    visibility: hidden;
    opacity: 0;
    width: 260px;
    background: color-mix(in srgb, var(--surface) 95%, var(--background));
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-primary);
    position: absolute;
    bottom: calc(100% + 6px);
    left: 50%;
    transform: translateX(-50%);
    box-shadow: 0 8px 24px rgba(0,0,0,.15);
    z-index: 100;
    transition: opacity .15s;
    pointer-events: none;
    line-height: 1.5;
}
.dc-tooltip-wrap:hover .dc-tooltip { visibility: visible; opacity: 1; }

/* ── Responsive ───────────────────────────────────────────── */
@media (max-width: 1100px) {
    .dc-two-col { grid-template-columns: 1fr; }
    .dc-sim-grid { grid-template-columns: 1fr; }
}
@media (max-width: 760px) {
    .dc-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .dc-form-row { grid-template-columns: 1fr; }
    .dc-sim-kpis { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
    .dc-kpi-grid { grid-template-columns: 1fr; }
    .dc-alert-chip { width: 100%; }
    .dc-decision-item { flex-wrap: wrap; }
}
</style>

<!-- ═══ Errors Banner ═══════════════════════════════════════════════════════ -->
<?php if (!empty($errors)): ?>
<div class="dc-errors-banner">
    <strong>Algunas secciones no pudieron cargarse:</strong>
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- ═══ Filter Bar ══════════════════════════════════════════════════════════ -->
<div class="dc-card">
    <form method="GET" action="/pmo/decision-center" class="dc-filters">
        <div class="dc-filter-group">
            <label>Desde</label>
            <input type="date" name="from" value="<?= $fromDate ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="dc-filter-group">
            <label>Hasta</label>
            <input type="date" name="to" value="<?= $toDate ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <?php if (!empty($clients)): ?>
        <div class="dc-filter-group">
            <label>Cliente</label>
            <select name="client_id">
                <option value="">Todos los clientes</option>
                <?php foreach ($clients as $cl): ?>
                    <option value="<?= (int)$cl['id'] ?>" <?= ((int)($filters['client_id'] ?? 0) === (int)$cl['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if (!empty($pms)): ?>
        <div class="dc-filter-group">
            <label>PM</label>
            <select name="pm_id">
                <option value="">Todos los PMs</option>
                <?php foreach ($pms as $pm): ?>
                    <option value="<?= (int)$pm['id'] ?>" <?= ((int)($filters['pm_id'] ?? 0) === (int)$pm['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pm['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="dc-btn dc-btn--primary">Actualizar</button>
        <?php if (!empty($_GET) && (isset($_GET['from']) || isset($_GET['client_id']) || isset($_GET['pm_id']))): ?>
            <a href="/pmo/decision-center" class="dc-btn dc-btn--secondary">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<!-- ═══ A. Estado del Portafolio ════════════════════════════════════════════ -->
<div class="dc-card">
    <div class="dc-card-header">
        <h3 class="dc-card-title">
            <svg viewBox="0 0 24 24"><path d="M3 13h7v8H3z"/><path d="M14 3h7v18h-7z"/><path d="M3 3h7v6H3z"/></svg>
            Estado del Portafolio
        </h3>
        <span style="font-size:12px;color:var(--text-secondary);">
            <?= htmlspecialchars(date('d/m/Y', strtotime($fromDate))) ?> – <?= htmlspecialchars(date('d/m/Y', strtotime($toDate))) ?>
        </span>
    </div>

    <?php
    $circum = 2 * M_PI * 42;
    $dash   = $circum * ($score / 100);
    $gap    = $circum - $dash;
    ?>
    <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;margin-bottom:16px;">
        <!-- Score circle -->
        <div class="dc-score-wrap">
            <div class="dc-score-circle">
                <svg viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="42" fill="none"
                            stroke="color-mix(in srgb, var(--border) 50%, var(--background))"
                            stroke-width="9"/>
                    <circle cx="50" cy="50" r="42" fill="none"
                            stroke="<?= $scoreColor ?>"
                            stroke-width="9"
                            stroke-linecap="round"
                            stroke-dasharray="<?= round($dash, 2) ?> <?= round($gap, 2) ?>"/>
                </svg>
                <div class="dc-score-center">
                    <span class="dc-score-num" style="color:<?= $scoreColor ?>"><?= $score ?></span>
                    <span class="dc-score-text">Score</span>
                </div>
            </div>
            <div>
                <div style="font-size:20px;font-weight:900;color:<?= $scoreColor ?>"><?= htmlspecialchars($scoreLabel) ?></div>
                <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Score general portafolio</div>
                <div style="font-size:11px;color:var(--text-secondary);margin-top:6px;">
                    Basado en avance, riesgos, bloqueos, facturación y capacidad
                </div>
            </div>
        </div>
    </div>

    <div class="dc-kpi-grid">
        <!-- Proyectos activos -->
        <div class="dc-kpi-card">
            <div class="dc-kpi-icon dc-kpi-icon--blue">
                <svg viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8.5a2.5 2.5 0 0 1-2.5 2.5H5.5A2.5 2.5 0 0 1 3 17.5z"/><path d="M3 10h18"/></svg>
            </div>
            <div class="dc-kpi-meta">
                <span class="dc-kpi-label">Proyectos activos</span>
                <span class="dc-kpi-value"><?= (int)($summary['proyectos_activos'] ?? 0) ?></span>
            </div>
        </div>
        <!-- Proyectos en riesgo -->
        <div class="dc-kpi-card">
            <div class="dc-kpi-icon dc-kpi-icon--red">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            </div>
            <div class="dc-kpi-meta">
                <span class="dc-kpi-label">En riesgo</span>
                <span class="dc-kpi-value" style="color:<?= (int)($summary['proyectos_en_riesgo'] ?? 0) > 0 ? 'var(--danger)' : 'var(--text-primary)' ?>">
                    <?= (int)($summary['proyectos_en_riesgo'] ?? 0) ?>
                </span>
            </div>
        </div>
        <!-- Bloqueos activos -->
        <div class="dc-kpi-card">
            <div class="dc-kpi-icon dc-kpi-icon--amber">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <div class="dc-kpi-meta">
                <span class="dc-kpi-label">Bloqueos activos</span>
                <span class="dc-kpi-value" style="color:<?= (int)($summary['bloqueos_activos'] ?? 0) > 0 ? 'var(--warning)' : 'var(--text-primary)' ?>">
                    <?= (int)($summary['bloqueos_activos'] ?? 0) ?>
                </span>
                <?php if ((int)($summary['bloqueos_criticos'] ?? 0) > 0): ?>
                    <span class="dc-kpi-sub" style="color:var(--danger)"><?= (int)$summary['bloqueos_criticos'] ?> crítico(s)</span>
                <?php endif; ?>
            </div>
        </div>
        <!-- Facturación pendiente -->
        <div class="dc-kpi-card">
            <div class="dc-kpi-icon dc-kpi-icon--indigo">
                <svg viewBox="0 0 24 24"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="dc-kpi-meta">
                <span class="dc-kpi-label">Facturación pendiente</span>
                <span class="dc-kpi-value" style="font-size:22px;"><?= htmlspecialchars($summary['facturacion_pendiente_fmt'] ?? '$0') ?></span>
            </div>
        </div>
        <!-- Utilización equipo -->
        <div class="dc-kpi-card">
            <div class="dc-kpi-icon dc-kpi-icon--teal">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="dc-kpi-meta">
                <span class="dc-kpi-label">Utilización equipo</span>
                <?php $util = (float)($summary['utilizacion_equipo_pct'] ?? 0); ?>
                <span class="dc-kpi-value" style="color:<?= $util >= 90 ? 'var(--danger)' : ($util >= 75 ? 'var(--warning)' : 'var(--text-primary)') ?>">
                    <?= round($util, 1) ?>%
                </span>
                <?php if ((int)($summary['sobrecarga_count'] ?? 0) > 0): ?>
                    <span class="dc-kpi-sub" style="color:var(--danger)"><?= (int)$summary['sobrecarga_count'] ?> sobrecargado(s)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══ B. Alertas Automáticas ══════════════════════════════════════════════ -->
<div class="dc-card">
    <div class="dc-card-header">
        <h3 class="dc-card-title">
            <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            Alertas Automáticas
        </h3>
        <span style="font-size:12px;color:var(--text-secondary);">
            Haz clic en un chip para filtrar la tabla
        </span>
    </div>
    <div class="dc-alerts-band" id="alertsChips">
        <?php foreach ($alerts as $key => $alert): ?>
            <?php $level = $alert['level'] ?? 'green'; ?>
            <button type="button"
                    class="dc-alert-chip dc-alert-chip--<?= $level ?> <?= $activeFilter === $key ? 'active' : '' ?>"
                    data-filter="<?= htmlspecialchars($key) ?>"
                    onclick="dcToggleFilter('<?= htmlspecialchars($key) ?>')"
                    title="<?= htmlspecialchars($alert['label'] ?? '') ?>">
                <?php if ($level === 'red'): ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                <?php elseif ($level === 'amber'): ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                <?php else: ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?php endif; ?>
                <?= htmlspecialchars($alert['label'] ?? $key) ?>
                <span class="dc-chip-count"><?= (int)($alert['count'] ?? 0) ?></span>
            </button>
        <?php endforeach; ?>
        <?php if ($activeFilter): ?>
            <button type="button" class="dc-btn dc-btn--secondary dc-btn--sm" onclick="dcToggleFilter('')">
                Quitar filtro ✕
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ C. Decisiones Recomendadas ══════════════════════════════════════════ -->
<div class="dc-card">
    <div class="dc-card-header">
        <h3 class="dc-card-title">
            <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            Decisiones Recomendadas
        </h3>
        <span style="font-size:12px;color:var(--text-secondary);">Priorizadas por impacto operativo</span>
    </div>

    <?php if (empty($recommendations)): ?>
        <div style="text-align:center;padding:30px 0;color:var(--text-secondary);font-size:14px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:8px;opacity:.4;display:block;margin-left:auto;margin-right:auto;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Sin decisiones recomendadas para los filtros actuales.
        </div>
    <?php else: ?>
        <div class="dc-decisions-list">
        <?php foreach ($recommendations as $i => $rec): ?>
            <div class="dc-decision-item">
                <div class="dc-decision-num"><?= $i + 1 ?></div>
                <div class="dc-decision-body">
                    <p class="dc-decision-title"><?= htmlspecialchars($rec['title'] ?? '') ?></p>
                    <p class="dc-decision-reason"><?= htmlspecialchars($rec['reason'] ?? '') ?></p>
                </div>
                <?= dc_impact_badge((string)($rec['impact'] ?? 'Bajo')) ?>
                <?php
                    $action    = (string)($rec['action'] ?? 'ver_proyecto');
                    $projectId = $rec['project_id'] ?? null;
                    $url       = dc_action_url($action, $projectId ? (int)$projectId : null);
                    $label     = dc_action_label($action);
                ?>
                <a href="<?= htmlspecialchars($url) ?>" class="dc-btn dc-btn--secondary dc-btn--sm" style="white-space:nowrap;">
                    <?= htmlspecialchars($label) ?>
                </a>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ═══ D. Tablas Analíticas ════════════════════════════════════════════════ -->
<div class="dc-two-col">

    <!-- Ranking de Proyectos -->
    <div class="dc-card">
        <div class="dc-card-header">
            <h3 class="dc-card-title">
                <svg viewBox="0 0 24 24"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg>
                Ranking de Proyectos
            </h3>
            <?php if (!empty($canExport) && !empty($projectRanking)): ?>
                <button type="button" class="dc-btn dc-btn--secondary dc-btn--sm" onclick="dcExportTable('dc-projects-table','proyectos_pmo')">
                    Exportar CSV
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($projectRanking)): ?>
            <div style="text-align:center;padding:24px 0;color:var(--text-secondary);font-size:13px;">Sin proyectos para los filtros actuales.</div>
        <?php else: ?>
        <div class="dc-table-wrap">
            <table class="dc-table" id="dc-projects-table">
                <thead>
                    <tr>
                        <th>Proyecto</th>
                        <th>Cliente</th>
                        <th>Salud</th>
                        <th>Avance</th>
                        <th>Bloqueos</th>
                        <th>Última nota</th>
                        <th>Facturación</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="dc-projects-tbody">
                <?php foreach ($projectRanking as $proj): ?>
                    <?php
                        $isRisk   = !empty($proj['is_at_risk']);
                        $rowStyle = $isRisk ? 'border-left: 3px solid var(--danger);' : '';
                        $noteText = (string)($proj['last_note_preview'] ?? '');
                        $noteDate = $proj['last_note_date'] ?? null;
                        $noteAge  = $proj['days_since_note'] ?? null;
                        $notePrev = mb_substr($noteText, 0, 120) . (mb_strlen($noteText) > 120 ? '…' : '');
                        $bid      = (int)$proj['id'];
                    ?>
                    <tr style="<?= $rowStyle ?>"
                        data-risk="<?= $isRisk ? '1' : '0' ?>"
                        data-note-age="<?= $noteAge !== null ? (int)$noteAge : '' ?>"
                        data-blockers="<?= (int)($proj['blockers_count'] ?? 0) ?>">
                        <td class="dc-project-name">
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                <?php if ($isRisk): ?>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2.5" style="flex-shrink:0"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                <?php endif; ?>
                                <span title="<?= htmlspecialchars($proj['project_name'] ?? '') ?>" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;display:inline-block;">
                                    <?= htmlspecialchars($proj['project_name'] ?? '—') ?>
                                </span>
                            </div>
                            <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">
                                Score: <strong><?= (int)($proj['health_score'] ?? 0) ?>/100</strong>
                                · PM: <?= htmlspecialchars($proj['pm_name'] ?? '—') ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($proj['client_name'] ?? '—') ?></td>
                        <td><?= dc_health_pill((string)($proj['health'] ?? '')) ?></td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:4px;min-width:70px;">
                                <span><?= (int)($proj['progress'] ?? 0) ?>%</span>
                                <div class="dc-util-bar" style="width:80px">
                                    <div class="dc-util-bar-fill" style="width:<?= min(100,(int)($proj['progress'] ?? 0)) ?>%;background:<?= (int)($proj['progress'] ?? 0) >= 70 ? 'var(--success)' : ((int)($proj['progress'] ?? 0) >= 40 ? 'var(--warning)' : 'var(--danger)') ?>;"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php $bCount = (int)($proj['blockers_count'] ?? 0); ?>
                            <?php if ($bCount > 0): ?>
                                <?php $topBlockers = $proj['blockers_top'] ?? []; ?>
                                <div class="dc-tooltip-wrap">
                                    <span style="font-weight:800;cursor:default;">
                                        <?= $bCount ?> 
                                        <?= dc_severity_pill((string)($proj['blockers_max_severity'] ?? '')) ?>
                                    </span>
                                    <?php if (!empty($topBlockers)): ?>
                                    <div class="dc-tooltip">
                                        <?php foreach ($topBlockers as $bl): ?>
                                            <div style="margin-bottom:4px;">
                                                <strong><?= htmlspecialchars($bl['title'] ?? '') ?></strong>
                                                <?= dc_severity_pill((string)($bl['impact_level'] ?? '')) ?>
                                                · <?= (int)($bl['days_open'] ?? 0) ?>d
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--text-secondary)">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="dc-note-cell">
                            <?php if ($noteDate): ?>
                                <div>
                                    <div class="dc-tooltip-wrap">
                                        <div class="dc-note-preview" title="<?= htmlspecialchars($noteText) ?>">
                                            <?= htmlspecialchars($notePrev) ?>
                                        </div>
                                        <?php if ($noteText): ?>
                                        <div class="dc-tooltip"><?= htmlspecialchars(mb_substr($noteText, 0, 200)) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">
                                        <?php if ($noteAge !== null): ?>
                                            hace <?= (int)$noteAge ?>d
                                            <?php if ((int)$noteAge > 7): ?>
                                                · <?= dc_pill('red', '>' . (int)$noteAge . 'd') ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($proj['last_note_author'])): ?>
                                            · <?= htmlspecialchars($proj['last_note_author']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?= dc_pill('red', 'Sin nota') ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $pend = (float)($proj['billing_pending'] ?? 0); ?>
                            <?php if ($pend > 0): ?>
                                <span style="font-weight:700;">
                                    <?= number_format($pend, 0, ',', '.') ?>
                                    <span style="font-size:10px;color:var(--text-secondary);"><?= htmlspecialchars($proj['currency_code'] ?? '') ?></span>
                                </span>
                            <?php elseif (empty($proj['is_billable'])): ?>
                                <span style="color:var(--text-secondary);font-size:12px;">No facturable</span>
                            <?php else: ?>
                                <span style="color:var(--success);">Al día</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/projects/<?= $bid ?>" class="dc-btn dc-btn--secondary dc-btn--sm" style="white-space:nowrap;">Ver</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Capacidad del Equipo -->
    <div class="dc-card">
        <div class="dc-card-header">
            <h3 class="dc-card-title">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Capacidad del Equipo
            </h3>
            <?php if (!empty($canExport) && !empty($teamCapacity)): ?>
                <button type="button" class="dc-btn dc-btn--secondary dc-btn--sm" onclick="dcExportTable('dc-capacity-table','capacidad_equipo')">
                    Exportar CSV
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($teamCapacity)): ?>
            <div style="text-align:center;padding:24px 0;color:var(--text-secondary);font-size:13px;">Sin datos de talento disponibles.</div>
        <?php else: ?>
        <div class="dc-table-wrap">
            <table class="dc-table" id="dc-capacity-table">
                <thead>
                    <tr>
                        <th>Talento</th>
                        <th>Rol</th>
                        <th>Utilización</th>
                        <th>Libre</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="dc-capacity-tbody">
                <?php foreach ($teamCapacity as $talent): ?>
                    <?php
                        $tUtil   = (float)($talent['utilization_pct'] ?? 0);
                        $tStatus = (string)($talent['status'] ?? 'disponible');
                        $barClr  = $tUtil >= 100 ? 'var(--danger)' : ($tUtil >= 90 ? '#f59e0b' : ($tUtil >= 60 ? 'var(--primary)' : 'var(--success)'));
                    ?>
                    <tr data-status="<?= htmlspecialchars($tStatus) ?>">
                        <td><strong><?= htmlspecialchars($talent['name'] ?? '—') ?></strong></td>
                        <td style="font-size:12px;color:var(--text-secondary);"><?= htmlspecialchars($talent['role'] ?? '—') ?></td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <span style="font-weight:800;"><?= round($tUtil, 1) ?>%</span>
                                <div class="dc-util-bar">
                                    <div class="dc-util-bar-fill" style="width:<?= min(100, round($tUtil, 1)) ?>%;background:<?= $barClr ?>;"></div>
                                </div>
                                <span style="font-size:11px;color:var(--text-secondary);">
                                    <?= round((float)($talent['used_hours'] ?? 0), 1) ?>h / <?= round((float)($talent['capacity_hours'] ?? 0), 1) ?>h
                                </span>
                            </div>
                        </td>
                        <td style="font-weight:700;">
                            <span style="color:<?= (float)($talent['free_hours'] ?? 0) > 0 ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= round((float)($talent['free_hours'] ?? 0), 1) ?>h
                            </span>
                        </td>
                        <td><?= dc_status_pill($tStatus) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ E. Simulación de Capacidad ══════════════════════════════════════════ -->
<div class="dc-card">
    <div class="dc-card-header">
        <h3 class="dc-card-title">
            <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            Simulación de Carga (Sin afectar datos reales)
        </h3>
    </div>

    <div class="dc-sim-grid">
        <div class="dc-sim-form">
            <div class="dc-form-row">
                <div class="dc-form-group">
                    <label>Área / Rol (opcional)</label>
                    <input type="text" id="sim-area" placeholder="Ej: Backend, UX...">
                </div>
                <div class="dc-form-group">
                    <label>Horas estimadas</label>
                    <input type="number" id="sim-hours" min="1" step="1" placeholder="160">
                </div>
            </div>
            <div class="dc-form-row">
                <div class="dc-form-group">
                    <label>Desde</label>
                    <input type="date" id="sim-from" value="<?= $fromDate ?>">
                </div>
                <div class="dc-form-group">
                    <label>Hasta</label>
                    <input type="date" id="sim-to" value="<?= $toDate ?>">
                </div>
            </div>
            <div class="dc-form-row">
                <div class="dc-form-group">
                    <label>Recursos requeridos</label>
                    <input type="number" id="sim-resources" min="1" step="1" value="1" placeholder="1">
                </div>
                <div class="dc-form-group" style="justify-content:flex-end;">
                    <button type="button" class="dc-btn dc-btn--primary" onclick="dcRunSimulation()" id="dc-sim-btn">
                        Simular carga
                    </button>
                </div>
            </div>
        </div>

        <div class="dc-sim-result" id="dc-sim-result">
            <div class="dc-sim-placeholder">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.35;margin-bottom:8px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                <p>Completa el formulario y haz clic en <strong>Simular carga</strong> para ver el impacto estimado.</p>
            </div>
        </div>
    </div>
</div>

<script>
/* ── Alert filter ──────────────────────────────────────── */
function dcToggleFilter(key) {
    const url = new URL(window.location.href);
    if (!key || url.searchParams.get('alert_filter') === key) {
        url.searchParams.delete('alert_filter');
    } else {
        url.searchParams.set('alert_filter', key);
    }
    window.location.href = url.toString();
}

/* Apply alert filter to tables */
(function() {
    const filter = '<?= addslashes($activeFilter) ?>';
    if (!filter) return;

    const tbody = document.getElementById('dc-projects-tbody');
    if (!tbody) return;

    Array.from(tbody.querySelectorAll('tr')).forEach(function(row) {
        let show = true;
        if (filter === 'sin_actualizacion') {
            const age = parseInt(row.dataset.noteAge || '0', 10);
            show = isNaN(age) || age > 7;
        } else if (filter === 'bloqueos_criticos') {
            show = parseInt(row.dataset.blockers || '0', 10) > 0;
        } else if (filter === 'facturacion_pendiente') {
            // show all – billing is more complex to detect per-row
        }
        row.style.display = show ? '' : 'none';
    });

    // Capacity table: overload filter
    if (filter === 'sobrecarga_talento') {
        const ctbody = document.getElementById('dc-capacity-tbody');
        if (ctbody) {
            Array.from(ctbody.querySelectorAll('tr')).forEach(function(row) {
                const st = row.dataset.status || '';
                row.style.display = (st === 'critico' || st === 'riesgo') ? '' : 'none';
            });
        }
    }
})();

/* ── Simulation ────────────────────────────────────────── */
async function dcRunSimulation() {
    const btn    = document.getElementById('dc-sim-btn');
    const result = document.getElementById('dc-sim-result');
    const area   = document.getElementById('sim-area').value.trim();
    const hours  = parseFloat(document.getElementById('sim-hours').value) || 0;
    const from   = document.getElementById('sim-from').value;
    const to     = document.getElementById('sim-to').value;
    const res    = parseInt(document.getElementById('sim-resources').value) || 1;

    if (hours <= 0) {
        result.innerHTML = '<div class="dc-sim-placeholder" style="color:var(--danger);">Las horas estimadas deben ser mayores a 0.</div>';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Calculando…';
    result.innerHTML = '<div class="dc-sim-placeholder">Calculando simulación…</div>';

    const body = new URLSearchParams({ area, hours, from, to, resources_needed: res });

    try {
        const resp = await fetch('/pmo/decision-center/simulate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString(),
        });
        const data = await resp.json();

        if (data.error) {
            result.innerHTML = '<div class="dc-sim-placeholder" style="color:var(--danger);">' + dcEsc(data.error) + '</div>';
            return;
        }

        const estUtil  = parseFloat(data.estimated_util_pct || 0).toFixed(1);
        const currUtil = parseFloat(data.current_util_pct || 0).toFixed(1);
        const atRisk   = Array.isArray(data.talents_at_risk) ? data.talents_at_risk : [];
        const affected = Array.isArray(data.affected_projects) ? data.affected_projects : [];
        const utilColor = estUtil >= 100 ? 'var(--danger)' : (estUtil >= 90 ? 'var(--warning)' : 'var(--success)');

        let html = `
        <div class="dc-sim-kpis">
            <div class="dc-sim-kpi">
                <div class="dc-sim-kpi-value">${currUtil}%</div>
                <div class="dc-sim-kpi-label">Utilización actual</div>
            </div>
            <div class="dc-sim-kpi">
                <div class="dc-sim-kpi-value" style="color:${utilColor}">${estUtil}%</div>
                <div class="dc-sim-kpi-label">Utilización estimada</div>
            </div>
            <div class="dc-sim-kpi">
                <div class="dc-sim-kpi-value" style="color:${atRisk.length > 0 ? 'var(--danger)' : 'var(--success)'}">${atRisk.length}</div>
                <div class="dc-sim-kpi-label">Talentos en riesgo</div>
            </div>
        </div>`;

        if (data.insufficient) {
            html += `<div style="padding:8px 12px;background:color-mix(in srgb,var(--warning) 15%,var(--surface));border-radius:8px;font-size:12px;font-weight:700;color:#92400e;">
                Recursos insuficientes: solo ${data.resources_assigned} de ${res} recurso(s) asignado(s).
            </div>`;
        }

        if (atRisk.length > 0) {
            html += `<div><strong style="font-size:12px;color:var(--danger);">Talentos que quedarían en riesgo:</strong>
            <ul class="dc-sim-list" style="margin-top:6px;">`;
            atRisk.forEach(function(t) {
                html += `<li>
                    <span style="width:6px;height:6px;background:var(--danger);border-radius:50%;display:inline-block;flex-shrink:0;"></span>
                    <strong>${dcEsc(t.name)}</strong> · ${t.current_util}% → <span style="color:var(--danger)">${t.simulated_util}%</span>
                </li>`;
            });
            html += `</ul></div>`;
        }

        if (affected.length > 0) {
            html += `<div><strong style="font-size:12px;color:var(--text-secondary);">Proyectos potencialmente afectados:</strong>
            <ul class="dc-sim-list" style="margin-top:6px;">`;
            affected.forEach(function(p) {
                html += `<li>
                    <span style="width:6px;height:6px;background:var(--warning);border-radius:50%;display:inline-block;flex-shrink:0;"></span>
                    ${dcEsc(p.project_name)} <span style="color:var(--text-secondary);font-size:11px;">(${dcEsc(p.client_name)})</span>
                </li>`;
            });
            html += `</ul></div>`;
        }

        if (atRisk.length === 0 && affected.length === 0) {
            html += `<div style="padding:8px 12px;background:color-mix(in srgb,var(--success) 12%,var(--surface));border-radius:8px;font-size:13px;font-weight:700;color:var(--success);">
                El equipo puede absorber esta carga sin riesgo de sobrecarga.
            </div>`;
        }

        result.innerHTML = html;
    } catch (err) {
        result.innerHTML = '<div class="dc-sim-placeholder" style="color:var(--danger);">Error al ejecutar la simulación. Intenta de nuevo.</div>';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Simular carga';
    }
}

function dcEsc(str) {
    const d = document.createElement('div');
    d.textContent = String(str || '');
    return d.innerHTML;
}

/* ── CSV Export ────────────────────────────────────────── */
function dcExportTable(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const rows = Array.from(table.querySelectorAll('tr'));
    const csv  = rows.map(function(row) {
        return Array.from(row.querySelectorAll('th,td')).map(function(cell) {
            return '"' + cell.innerText.replace(/"/g, '""').replace(/\n/g, ' ') + '"';
        }).join(',');
    }).join('\n');

    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href  = URL.createObjectURL(blob);
    link.download = filename + '_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}
</script>
</div>
