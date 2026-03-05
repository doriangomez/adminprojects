<?php
$basePath = '';
$score = (int) ($summary['portfolio_score'] ?? 0);
$scoreColor = $score >= 80 ? 'var(--success)' : ($score >= 60 ? 'var(--warning)' : 'var(--danger)');
$scoreLabel = $score >= 80 ? 'Saludable' : ($score >= 60 ? 'Atención' : 'Crítico');

$filterFrom = htmlspecialchars($filters['from'] ?? date('Y-m-01'));
$filterTo = htmlspecialchars($filters['to'] ?? date('Y-m-t'));
$filterClientId = (int) ($filters['client_id'] ?? 0);
$filterPmId = (int) ($filters['pm_id'] ?? 0);
?>

<style>
.dc-filters { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; padding: 14px 18px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface); margin-bottom: 6px; }
.dc-filters .input { min-width: 140px; flex: 1; }
.dc-filters .input span { font-size: 12px; }
.dc-filters .btn { height: 42px; }

.dc-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; }
.dc-kpi-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 6px; position: relative; overflow: hidden; box-shadow: 0 8px 20px color-mix(in srgb, #0f172a 8%, transparent); }
.dc-kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--kpi-accent, var(--primary)); border-radius: 14px 14px 0 0; }
.dc-kpi-value { font-size: 32px; font-weight: 800; color: var(--text-primary); line-height: 1.1; }
.dc-kpi-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); font-weight: 700; }
.dc-kpi-icon { position: absolute; top: 16px; right: 16px; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--kpi-accent, var(--primary)); background: color-mix(in srgb, var(--kpi-accent, var(--primary)) 14%, var(--background)); }
.dc-kpi-icon svg { width: 22px; height: 22px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.dc-score-ring { width: 110px; height: 110px; position: relative; margin: 0 auto; }
.dc-score-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.dc-score-ring .ring-bg { fill: none; stroke: color-mix(in srgb, var(--border) 60%, var(--background)); stroke-width: 8; }
.dc-score-ring .ring-fg { fill: none; stroke-width: 8; stroke-linecap: round; transition: stroke-dashoffset 1s ease; }
.dc-score-number { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.dc-score-number .value { font-size: 28px; font-weight: 800; }
.dc-score-number .label { font-size: 11px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; }

.dc-alerts-band { display: flex; gap: 10px; flex-wrap: wrap; padding: 14px 0; }
.dc-chip { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; font-size: 13px; font-weight: 700; border: 1px solid var(--border); cursor: pointer; transition: all 0.2s ease; background: var(--surface); color: var(--text-primary); }
.dc-chip:hover { transform: translateY(-1px); box-shadow: 0 6px 14px color-mix(in srgb, #0f172a 14%, transparent); }
.dc-chip.green { border-color: color-mix(in srgb, var(--success) 40%, var(--border)); background: color-mix(in srgb, var(--success) 10%, var(--surface)); color: var(--success); }
.dc-chip.warning { border-color: color-mix(in srgb, var(--warning) 40%, var(--border)); background: color-mix(in srgb, var(--warning) 10%, var(--surface)); color: var(--warning); }
.dc-chip.danger { border-color: color-mix(in srgb, var(--danger) 40%, var(--border)); background: color-mix(in srgb, var(--danger) 10%, var(--surface)); color: var(--danger); }
.dc-chip .dc-chip-count { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; border-radius: 999px; font-size: 11px; font-weight: 800; background: currentColor; color: var(--surface); }
.dc-chip.green .dc-chip-count { background: var(--success); color: #fff; }
.dc-chip.warning .dc-chip-count { background: var(--warning); color: #fff; }
.dc-chip.danger .dc-chip-count { background: var(--danger); color: #fff; }

.dc-recommendations { display: flex; flex-direction: column; gap: 10px; }
.dc-rec-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface); transition: all 0.2s ease; }
.dc-rec-item:hover { border-color: color-mix(in srgb, var(--primary) 40%, var(--border)); box-shadow: 0 6px 16px color-mix(in srgb, #0f172a 8%, transparent); }
.dc-rec-body { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.dc-rec-title { font-weight: 700; color: var(--text-primary); font-size: 14px; }
.dc-rec-reason { font-size: 13px; color: var(--text-secondary); }
.dc-rec-impact { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
.dc-rec-impact.alto { background: color-mix(in srgb, var(--danger) 14%, var(--background)); color: var(--danger); }
.dc-rec-impact.medio { background: color-mix(in srgb, var(--warning) 14%, var(--background)); color: var(--warning); }
.dc-rec-impact.bajo { background: color-mix(in srgb, var(--success) 14%, var(--background)); color: var(--success); }

.dc-ai-panel { border: 1px solid color-mix(in srgb, var(--info) 30%, var(--border)); border-radius: 14px; padding: 20px; background: linear-gradient(160deg, color-mix(in srgb, var(--info) 6%, var(--surface)), var(--surface)); }
.dc-ai-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.dc-ai-header h3 { margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px; }
.dc-ai-cached { font-size: 11px; color: var(--text-secondary); font-weight: 600; padding: 3px 10px; border-radius: 999px; background: color-mix(in srgb, var(--info) 12%, var(--background)); }
.dc-ai-body { display: flex; flex-direction: column; gap: 14px; font-size: 14px; line-height: 1.7; }
.dc-ai-section { display: flex; flex-direction: column; gap: 6px; }
.dc-ai-section-title { font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); }
.dc-ai-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; }
.dc-ai-list li { padding-left: 16px; position: relative; }
.dc-ai-list li::before { content: '→'; position: absolute; left: 0; color: var(--primary); font-weight: 700; }

.dc-sim-panel { border: 1px dashed var(--border); border-radius: 14px; padding: 20px; background: var(--surface); }
.dc-sim-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end; }
.dc-sim-results { margin-top: 18px; display: flex; flex-direction: column; gap: 14px; }
.dc-sim-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.dc-sim-kpi { text-align: center; padding: 14px; border: 1px solid var(--border); border-radius: 12px; background: color-mix(in srgb, var(--surface) 94%, var(--background)); }
.dc-sim-kpi .value { font-size: 24px; font-weight: 800; }
.dc-sim-kpi .label { font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; }

.dc-section-title { font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px; }
.dc-section-subtitle { font-size: 13px; color: var(--text-secondary); margin: 0 0 12px 0; }

.dc-tooltip { position: relative; cursor: help; }
.dc-tooltip .dc-tooltip-text { visibility: hidden; opacity: 0; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: color-mix(in srgb, var(--text-primary) 90%, var(--background)); color: var(--background); padding: 8px 12px; border-radius: 8px; font-size: 12px; white-space: normal; min-width: 200px; max-width: 320px; z-index: 100; transition: opacity 0.2s ease; line-height: 1.5; font-weight: 500; box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
.dc-tooltip:hover .dc-tooltip-text { visibility: visible; opacity: 1; }

.dc-note-preview { max-width: 220px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 12px; color: var(--text-secondary); }

.dc-util-bar { width: 100%; height: 6px; border-radius: 999px; background: color-mix(in srgb, var(--border) 40%, var(--background)); overflow: hidden; }
.dc-util-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; }
.dc-util-fill.green { background: var(--success); }
.dc-util-fill.yellow { background: var(--warning); }
.dc-util-fill.red { background: var(--danger); }

.dc-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 1100px) { .dc-two-col { grid-template-columns: 1fr; } }

.dc-status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
.dc-status-dot.green { background: var(--success); }
.dc-status-dot.yellow { background: var(--warning); }
.dc-status-dot.red { background: var(--danger); }
.dc-status-dot.gray { background: var(--text-secondary); }

table.dc-table { font-size: 13px; }
table.dc-table th { font-size: 11px; padding: 10px 12px; }
table.dc-table td { padding: 10px 12px; }
</style>

<!-- AI Analysis Panel -->
<?php if ($canAi && !empty($aiAnalysis)): ?>
<div class="dc-ai-panel">
    <div class="dc-ai-header">
        <h3>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 0-4 4c0 2 1.5 3.5 3 4.5V12h2v-1.5c1.5-1 3-2.5 3-4.5a4 4 0 0 0-4-4Z"/><path d="M8 14h8"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
            Análisis inteligente
        </h3>
        <div style="display:flex;gap:8px;align-items:center;">
            <?php if (!empty($aiAnalysis['cached'])): ?>
                <span class="dc-ai-cached">En caché</span>
            <?php endif; ?>
            <span class="dc-ai-cached"><?= htmlspecialchars($aiAnalysis['generated_at'] ?? '') ?></span>
            <a href="<?= $basePath ?>/pmo/decision-center?from=<?= $filterFrom ?>&to=<?= $filterTo ?>&client_id=<?= $filterClientId ?>&pm_id=<?= $filterPmId ?>&_refresh=1" class="btn sm">Regenerar</a>
        </div>
    </div>
    <div class="dc-ai-body">
        <div class="dc-ai-section">
            <span class="dc-ai-section-title">Diagnóstico</span>
            <p style="margin:0;"><?= htmlspecialchars($aiAnalysis['diagnosis'] ?? '') ?></p>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="dc-ai-section">
                <span class="dc-ai-section-title">Recomendaciones prioritarias</span>
                <ul class="dc-ai-list">
                    <?php foreach (($aiAnalysis['recommendations'] ?? []) as $rec): ?>
                        <li><?= htmlspecialchars($rec) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="dc-ai-section">
                <span class="dc-ai-section-title">Alertas detectadas</span>
                <ul class="dc-ai-list">
                    <?php foreach (($aiAnalysis['detected_alerts'] ?? []) as $alert): ?>
                        <li><?= htmlspecialchars($alert) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<form class="dc-filters" method="GET" action="<?= $basePath ?>/pmo/decision-center">
    <div class="input">
        <span>Desde</span>
        <input type="date" name="from" value="<?= $filterFrom ?>">
    </div>
    <div class="input">
        <span>Hasta</span>
        <input type="date" name="to" value="<?= $filterTo ?>">
    </div>
    <div class="input">
        <span>Cliente</span>
        <select name="client_id">
            <option value="">Todos</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?= (int) $client['id'] ?>" <?= $filterClientId === (int) $client['id'] ? 'selected' : '' ?>><?= htmlspecialchars($client['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="input">
        <span>PM</span>
        <select name="pm_id">
            <option value="">Todos</option>
            <?php foreach ($pms as $pm): ?>
                <option value="<?= (int) $pm['id'] ?>" <?= $filterPmId === (int) $pm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pm['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn primary" type="submit">Filtrar</button>
</form>

<!-- A. Portfolio Status KPIs -->
<div class="card">
    <h3 class="dc-section-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h7v8H3z"/><path d="M14 3h7v18h-7z"/><path d="M3 3h7v6H3z"/></svg>
        Estado del portafolio
    </h3>
    <p class="dc-section-subtitle">Indicadores clave del periodo seleccionado</p>

    <div class="dc-kpi-grid">
        <!-- Portfolio Score -->
        <div class="dc-kpi-card" style="--kpi-accent: <?= $scoreColor ?>; grid-row: span 2;">
            <div class="dc-kpi-label">Score portafolio</div>
            <div class="dc-score-ring">
                <?php
                $circumference = 2 * M_PI * 42;
                $dashOffset = $circumference - ($circumference * $score / 100);
                ?>
                <svg viewBox="0 0 100 100">
                    <circle class="ring-bg" cx="50" cy="50" r="42" />
                    <circle class="ring-fg" cx="50" cy="50" r="42"
                        stroke="<?= $scoreColor ?>"
                        stroke-dasharray="<?= round($circumference, 2) ?>"
                        stroke-dashoffset="<?= round($dashOffset, 2) ?>" />
                </svg>
                <div class="dc-score-number">
                    <span class="value" style="color:<?= $scoreColor ?>"><?= $score ?></span>
                    <span class="label"><?= $scoreLabel ?></span>
                </div>
            </div>
        </div>

        <div class="dc-kpi-card" style="--kpi-accent: var(--info);">
            <div class="dc-kpi-label">Proyectos activos</div>
            <div class="dc-kpi-value"><?= (int) ($summary['active_projects'] ?? 0) ?></div>
            <div class="dc-kpi-icon"><svg viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8.5a2.5 2.5 0 0 1-2.5 2.5H5.5A2.5 2.5 0 0 1 3 17.5z"/></svg></div>
        </div>

        <div class="dc-kpi-card" style="--kpi-accent: var(--danger);">
            <div class="dc-kpi-label">Proyectos en riesgo</div>
            <div class="dc-kpi-value"><?= (int) ($summary['at_risk_projects'] ?? 0) ?></div>
            <div class="dc-kpi-icon"><svg viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></div>
        </div>

        <div class="dc-kpi-card" style="--kpi-accent: var(--warning);">
            <div class="dc-kpi-label">Bloqueos activos</div>
            <div class="dc-kpi-value"><?= (int) ($summary['open_blockers'] ?? 0) ?></div>
            <div class="dc-kpi-icon"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
        </div>

        <div class="dc-kpi-card" style="--kpi-accent: #f59e0b;">
            <div class="dc-kpi-label">Facturación pendiente</div>
            <div class="dc-kpi-value">$<?= number_format((float) ($summary['billing_pending_amount'] ?? 0), 0) ?></div>
            <div class="dc-kpi-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        </div>

        <div class="dc-kpi-card" style="--kpi-accent: #8b5cf6;">
            <div class="dc-kpi-label">Utilización equipo</div>
            <div class="dc-kpi-value"><?= (float) ($summary['utilization_pct'] ?? 0) ?>%</div>
            <?php $utilPct = (float) ($summary['utilization_pct'] ?? 0); ?>
            <div class="dc-util-bar" style="margin-top:4px;">
                <div class="dc-util-fill <?= $utilPct > 90 ? 'red' : ($utilPct >= 70 ? 'yellow' : 'green') ?>" style="width:<?= min(100, $utilPct) ?>%"></div>
            </div>
            <div class="dc-kpi-icon" style="color:#8b5cf6;background:color-mix(in srgb,#8b5cf6 14%,var(--background))"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        </div>
    </div>
</div>

<!-- B. Automatic Alerts -->
<div class="card">
    <h3 class="dc-section-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        Alertas automáticas
    </h3>
    <p class="dc-section-subtitle">Condiciones que requieren atención inmediata</p>
    <div class="dc-alerts-band">
        <?php foreach ($alerts as $alert): ?>
            <?php
            $chipClass = ($alert['status'] ?? 'green');
            $chipCount = (int) ($alert['count'] ?? 0);
            ?>
            <span class="dc-chip <?= $chipClass ?>" data-alert-key="<?= htmlspecialchars($alert['key'] ?? '') ?>" onclick="dcFilterByAlert('<?= htmlspecialchars($alert['key'] ?? '') ?>')">
                <span class="dc-chip-count"><?= $chipCount ?></span>
                <?= htmlspecialchars($alert['label'] ?? '') ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<!-- C. Recommended Decisions -->
<div class="card">
    <h3 class="dc-section-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>
        Decisiones recomendadas
    </h3>
    <p class="dc-section-subtitle">Acciones priorizadas para la dirección/PMO</p>
    <div class="dc-recommendations">
        <?php if (empty($recommendations)): ?>
            <div class="alert" style="text-align:center;">No hay decisiones pendientes en este momento. El portafolio se encuentra bajo control.</div>
        <?php else: ?>
            <?php foreach ($recommendations as $i => $rec): ?>
                <div class="dc-rec-item">
                    <span style="font-size:20px;font-weight:800;color:var(--text-secondary);min-width:28px;text-align:center;"><?= $i + 1 ?></span>
                    <div class="dc-rec-body">
                        <span class="dc-rec-title"><?= htmlspecialchars($rec['title'] ?? '') ?></span>
                        <span class="dc-rec-reason"><?= htmlspecialchars($rec['reason'] ?? '') ?></span>
                    </div>
                    <span class="dc-rec-impact <?= strtolower($rec['impact'] ?? 'bajo') ?>"><?= htmlspecialchars($rec['impact'] ?? '') ?></span>
                    <a href="<?= $basePath . htmlspecialchars($rec['action_url'] ?? '#') ?>" class="btn sm"><?= htmlspecialchars($rec['action_label'] ?? 'Ver') ?></a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- D. Analytical Tables -->
<div class="dc-two-col">
    <!-- Project Ranking -->
    <div class="card" style="overflow:hidden;">
        <h3 class="dc-section-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg>
            Ranking de proyectos
        </h3>
        <p class="dc-section-subtitle">Portafolio activo ordenado por avance</p>
        <div class="table-wrapper">
            <table class="dc-table">
                <thead>
                    <tr>
                        <th>Proyecto</th>
                        <th>Cliente</th>
                        <th>PM</th>
                        <th>Avance</th>
                        <th>Salud</th>
                        <th>Bloqueos</th>
                        <th>Última nota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projectRanking)): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--text-secondary);">Sin datos de proyectos</td></tr>
                    <?php else: ?>
                        <?php foreach ($projectRanking as $project): ?>
                            <?php
                            $health = $project['health'] ?? '';
                            $healthDot = match ($health) {
                                'green', 'on_track' => 'green',
                                'yellow', 'at_risk' => 'yellow',
                                'red', 'critical' => 'red',
                                default => 'gray',
                            };
                            $progress = (int) ($project['progress'] ?? 0);
                            ?>
                            <tr data-project-id="<?= (int) $project['id'] ?>">
                                <td>
                                    <div class="dc-tooltip">
                                        <a href="<?= $basePath ?>/projects/<?= (int) $project['id'] ?>" style="color:var(--text-primary);font-weight:700;text-decoration:none;">
                                            <?= htmlspecialchars($project['name'] ?? '') ?>
                                        </a>
                                        <?php if (!empty($project['last_note']['text'])): ?>
                                            <div class="dc-tooltip-text">
                                                <strong>Última nota:</strong><br>
                                                <?= htmlspecialchars($project['last_note']['text']) ?><br>
                                                <small><?= htmlspecialchars($project['last_note']['author'] ?? '') ?> — <?= htmlspecialchars($project['last_note']['date'] ?? '') ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($project['client'] ?? '') ?></td>
                                <td><?= htmlspecialchars($project['pm'] ?? '') ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <div class="dc-util-bar" style="width:60px;">
                                            <div class="dc-util-fill <?= $progress >= 70 ? 'green' : ($progress >= 40 ? 'yellow' : 'red') ?>" style="width:<?= $progress ?>%"></div>
                                        </div>
                                        <span style="font-weight:700;font-size:12px;"><?= $progress ?>%</span>
                                    </div>
                                </td>
                                <td><span class="dc-status-dot <?= $healthDot ?>"></span></td>
                                <td>
                                    <?php if ($project['blockers_count'] > 0): ?>
                                        <div class="dc-tooltip">
                                            <span class="badge <?= strtolower($project['blockers_max_severity'] ?? '') === 'crítico' ? 'danger' : (strtolower($project['blockers_max_severity'] ?? '') === 'alto' ? 'warning' : '') ?>" style="cursor:help;">
                                                <?= $project['blockers_count'] ?> <?= htmlspecialchars($project['blockers_max_severity'] ?? '') ?>
                                            </span>
                                            <?php if (!empty($project['top_blockers'])): ?>
                                                <div class="dc-tooltip-text">
                                                    <?php foreach ($project['top_blockers'] as $blocker): ?>
                                                        <div style="margin-bottom:4px;">
                                                            <strong>[<?= htmlspecialchars($blocker['impact_level'] ?? '') ?>]</strong>
                                                            <?= htmlspecialchars($blocker['title'] ?? '') ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($project['last_note'])): ?>
                                        <div class="dc-note-preview">
                                            <?= htmlspecialchars($project['last_note']['text'] ?? '') ?>
                                        </div>
                                        <small style="color:var(--text-secondary);"><?= htmlspecialchars(substr($project['last_note']['date'] ?? '', 0, 10)) ?></small>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:12px;">Sin notas</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Team Capacity -->
    <div class="card" style="overflow:hidden;">
        <h3 class="dc-section-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Capacidad del equipo
        </h3>
        <p class="dc-section-subtitle">
            <?= (int) ($teamCapacity['summary']['total'] ?? 0) ?> talentos |
            <?= (int) ($teamCapacity['summary']['available'] ?? 0) ?> disponibles |
            <?= (int) ($teamCapacity['summary']['overloaded'] ?? 0) ?> sobrecargados
        </p>
        <div class="table-wrapper">
            <table class="dc-table">
                <thead>
                    <tr>
                        <th>Talento</th>
                        <th>Rol</th>
                        <th>Capacidad</th>
                        <th>Usado</th>
                        <th>Disponible</th>
                        <th>Utilización</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teamCapacity['talents'])): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--text-secondary);">Sin datos de talento</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($teamCapacity['talents'], 0, 20) as $talent): ?>
                            <?php
                            $util = (float) ($talent['utilization'] ?? 0);
                            $utilClass = $util > 100 ? 'red' : ($util > 90 ? 'red' : ($util >= 70 ? 'yellow' : 'green'));
                            $statusLabel = match ($talent['status'] ?? '') {
                                'critical' => 'Crítico',
                                'overloaded' => 'Sobrecargado',
                                default => 'Disponible',
                            };
                            $statusBadge = match ($talent['status'] ?? '') {
                                'critical' => 'danger',
                                'overloaded' => 'warning',
                                default => 'success',
                            };
                            ?>
                            <tr>
                                <td style="font-weight:700;"><?= htmlspecialchars($talent['name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($talent['role'] ?? '') ?></td>
                                <td><?= number_format((float) ($talent['capacity'] ?? 0), 0) ?>h</td>
                                <td><?= number_format((float) ($talent['used_hours'] ?? 0), 0) ?>h</td>
                                <td><?= number_format((float) ($talent['free_hours'] ?? 0), 0) ?>h</td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <div class="dc-util-bar" style="width:50px;">
                                            <div class="dc-util-fill <?= $utilClass ?>" style="width:<?= min(100, $util) ?>%"></div>
                                        </div>
                                        <span style="font-weight:700;font-size:12px;"><?= round($util, 0) ?>%</span>
                                    </div>
                                </td>
                                <td><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- E. Capacity Simulation -->
<div class="dc-sim-panel">
    <h3 class="dc-section-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
        Simular carga
    </h3>
    <p class="dc-section-subtitle">Evalúa el impacto de agregar horas sin afectar datos reales</p>
    <form id="dc-sim-form" class="dc-sim-form" onsubmit="dcRunSimulation(event)">
        <div class="input">
            <span>Área / Rol (opcional)</span>
            <input type="text" id="sim-area" name="area" placeholder="Ej: Desarrollo, QA...">
        </div>
        <div class="input">
            <span>Horas estimadas nuevas</span>
            <input type="number" id="sim-hours" name="estimated_hours" value="200" min="0" step="1" required>
        </div>
        <div class="input">
            <span>Desde</span>
            <input type="date" id="sim-from" name="date_from" value="<?= $filterFrom ?>">
        </div>
        <div class="input">
            <span>Hasta</span>
            <input type="date" id="sim-to" name="date_to" value="<?= $filterTo ?>">
        </div>
        <div class="input">
            <span>Recursos requeridos (opcional)</span>
            <input type="number" id="sim-resources" name="required_resources" value="0" min="0">
        </div>
        <button class="btn primary" type="submit">Simular</button>
    </form>
    <div id="dc-sim-results" class="dc-sim-results" style="display:none;"></div>
</div>

<script>
function dcFilterByAlert(key) {
    const rows = document.querySelectorAll('[data-project-id]');
    rows.forEach(r => r.style.display = '');
}

function dcRunSimulation(e) {
    e.preventDefault();
    const container = document.getElementById('dc-sim-results');
    container.style.display = 'block';
    container.innerHTML = '<div class="alert" style="text-align:center;">Ejecutando simulación...</div>';

    const payload = {
        area: document.getElementById('sim-area').value,
        estimated_hours: parseFloat(document.getElementById('sim-hours').value) || 0,
        date_from: document.getElementById('sim-from').value,
        date_to: document.getElementById('sim-to').value,
        required_resources: parseInt(document.getElementById('sim-resources').value) || 0,
    };

    fetch('<?= $basePath ?>/api/pmo/decision-center/simulate', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            container.innerHTML = '<div class="alert error">' + data.error + '</div>';
            return;
        }
        renderSimulationResults(container, data);
    })
    .catch(err => {
        container.innerHTML = '<div class="alert error">Error al ejecutar la simulación</div>';
    });
}

function renderSimulationResults(container, data) {
    const kpis = data.kpis || {};
    const rows = data.rows || [];
    const riskTalents = data.risk_talents || [];
    const affectedProjects = data.affected_projects || [];

    let html = '<div class="dc-sim-kpis">';
    html += simKpi(kpis.estimated_utilization || 0, '%', 'Utilización estimada');
    html += simKpi(kpis.risk_talent_count || 0, '', 'Talentos en riesgo');
    html += simKpi(kpis.team_capacity || 0, 'h', 'Capacidad total');
    html += simKpi(kpis.remaining_capacity || 0, 'h', 'Capacidad restante');
    html += '</div>';

    if (riskTalents.length > 0) {
        html += '<div class="alert error" style="font-size:13px;">Talentos en riesgo: <strong>' + riskTalents.join(', ') + '</strong></div>';
    }
    if (affectedProjects.length > 0) {
        html += '<div class="alert" style="font-size:13px;border-color:color-mix(in srgb, var(--warning) 40%, var(--border));background:color-mix(in srgb, var(--warning) 8%, var(--surface));">Proyectos potencialmente afectados: <strong>' + affectedProjects.join(', ') + '</strong></div>';
    }

    if (rows.length > 0) {
        html += '<div class="table-wrapper"><table class="dc-table"><thead><tr><th>Talento</th><th>Actual</th><th>+ Nuevas</th><th>Simulado</th><th>Capacidad</th><th>Utilización</th><th>Estado</th></tr></thead><tbody>';
        rows.forEach(r => {
            const u = r.utilization || 0;
            const cls = u > 100 ? 'red' : (u > 90 ? 'red' : (u >= 70 ? 'yellow' : 'green'));
            const statusLabel = r.status === 'critical' ? 'Crítico' : (r.status === 'overloaded' ? 'Sobrecargado' : 'OK');
            const badgeCls = r.status === 'critical' ? 'danger' : (r.status === 'overloaded' ? 'warning' : 'success');
            html += '<tr>';
            html += '<td style="font-weight:700;">' + esc(r.name) + '</td>';
            html += '<td>' + num(r.current_hours) + 'h</td>';
            html += '<td style="color:var(--primary);font-weight:700;">+' + num(r.extra_hours) + 'h</td>';
            html += '<td>' + num(r.simulated_hours) + 'h</td>';
            html += '<td>' + num(r.capacity) + 'h</td>';
            html += '<td><div style="display:flex;align-items:center;gap:6px;"><div class="dc-util-bar" style="width:50px;"><div class="dc-util-fill ' + cls + '" style="width:' + Math.min(100, u) + '%"></div></div><span style="font-weight:700;font-size:12px;">' + Math.round(u) + '%</span></div></td>';
            html += '<td><span class="badge ' + badgeCls + '">' + statusLabel + '</span></td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
    }

    container.innerHTML = html;
}

function simKpi(value, suffix, label) {
    const v = typeof value === 'number' ? (suffix === '%' ? value.toFixed(1) : Math.round(value)) : value;
    return '<div class="dc-sim-kpi"><div class="value">' + v + suffix + '</div><div class="label">' + label + '</div></div>';
}
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function num(n) { return (n || 0).toLocaleString('es-MX', {maximumFractionDigits: 0}); }
</script>
