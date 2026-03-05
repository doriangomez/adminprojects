<?php
$basePath = $basePath ?? '';
$summary = $summary ?? [];
$alerts = $alerts ?? [];
$recommendations = $recommendations ?? [];
$projectRanking = $projectRanking ?? [];
$teamCapacity = $teamCapacity ?? [];
$filters = $filters ?? [];
$canExport = $canExport ?? false;
$canAi = $canAi ?? false;

$score = (int) ($summary['score_general'] ?? 0);
$scoreClass = $score > 85 ? 'green' : ($score >= 70 ? 'yellow' : 'red');
?>
<style>
.decision-center { display: flex; flex-direction: column; gap: 20px; padding-bottom: 24px; }
.dc-section-title { margin: 0 0 12px; font-size: 18px; font-weight: 800; color: var(--text-primary); }
.dc-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.dc-kpi-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    box-shadow: 0 8px 20px color-mix(in srgb, #0f172a 8%, transparent);
}
.dc-kpi-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--primary) 18%, var(--background));
    color: var(--primary);
    flex-shrink: 0;
}
.dc-kpi-icon svg { width: 22px; height: 22px; stroke: currentColor; }
.dc-kpi-body { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.dc-kpi-value { font-size: 28px; font-weight: 900; color: var(--text-primary); line-height: 1; }
.dc-kpi-label { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--text-secondary); font-weight: 600; }
.dc-alerts-band {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 14px;
    border-radius: 14px;
    border: 1px solid var(--border);
    background: color-mix(in srgb, var(--surface) 92%, var(--background));
}
.dc-alert-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid var(--border);
    background: color-mix(in srgb, var(--surface) 88%, var(--background));
    color: var(--text-primary);
    transition: all 0.2s ease;
}
.dc-alert-chip:hover {
    background: color-mix(in srgb, var(--primary) 14%, var(--background));
    border-color: color-mix(in srgb, var(--primary) 40%, var(--border));
}
.dc-alert-chip .count { min-width: 22px; padding: 2px 6px; border-radius: 999px; background: var(--accent); font-size: 11px; }
.dc-recommendations { display: flex; flex-direction: column; gap: 10px; }
.dc-rec-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: color-mix(in srgb, var(--surface) 92%, var(--background));
}
.dc-rec-body { flex: 1; min-width: 0; }
.dc-rec-title { font-weight: 700; color: var(--text-primary); margin: 0 0 4px; }
.dc-rec-reason { font-size: 13px; color: var(--text-secondary); margin: 0 0 6px; }
.dc-rec-impact { font-size: 11px; font-weight: 700; text-transform: uppercase; }
.dc-rec-impact.alto { color: var(--danger); }
.dc-rec-impact.medio { color: var(--warning); }
.dc-rec-impact.bajo { color: var(--success); }
.dc-tables-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 1100px) { .dc-tables-grid { grid-template-columns: 1fr; } }
.dc-table-card { padding: 16px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface); }
.dc-table-card .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.dc-table-card table { margin: 0; }
.dc-table-card th, .dc-table-card td { padding: 10px 12px; }
.dc-project-name { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dc-project-name[title] { cursor: help; }
.dc-note-preview { max-width: 180px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 12px; color: var(--text-secondary); }
.dc-simulate-panel {
    padding: 16px;
    border-radius: 14px;
    border: 1px dashed var(--border);
    background: color-mix(in srgb, var(--surface) 70%, var(--background));
}
.dc-simulate-panel h4 { margin: 0 0 12px; font-size: 15px; }
.dc-simulate-form { display: flex; flex-direction: column; gap: 10px; }
.dc-simulate-form .input-stack { display: flex; flex-direction: column; gap: 6px; }
.dc-simulate-form label { font-size: 12px; font-weight: 600; }
.dc-simulate-result { margin-top: 12px; padding: 12px; border-radius: 10px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); border: 1px solid var(--border); }
.dc-util-badge { padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.dc-util-badge.ok { background: color-mix(in srgb, var(--success) 18%, var(--background)); color: var(--success); }
.dc-util-badge.overload { background: color-mix(in srgb, var(--warning) 18%, var(--background)); color: var(--warning); }
.dc-util-badge.critical { background: color-mix(in srgb, var(--danger) 18%, var(--background)); color: var(--danger); }
</style>

<div class="decision-center">
    <!-- A. Estado del portafolio -->
    <section class="card">
        <h3 class="dc-section-title">Estado del portafolio</h3>
        <div class="dc-kpi-grid">
            <div class="dc-kpi-card">
                <div class="dc-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg></div>
                <div class="dc-kpi-body">
                    <span class="dc-kpi-value"><?= $score ?></span>
                    <span class="dc-kpi-label">Score general (0–100)</span>
                </div>
            </div>
            <div class="dc-kpi-card">
                <div class="dc-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
                <div class="dc-kpi-body">
                    <span class="dc-kpi-value"><?= (int) ($summary['proyectos_activos'] ?? 0) ?></span>
                    <span class="dc-kpi-label">Proyectos activos</span>
                </div>
            </div>
            <div class="dc-kpi-card">
                <div class="dc-kpi-icon" style="background: color-mix(in srgb, var(--danger) 18%, var(--background)); color: var(--danger);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div>
                <div class="dc-kpi-body">
                    <span class="dc-kpi-value"><?= (int) ($summary['proyectos_riesgo'] ?? 0) ?></span>
                    <span class="dc-kpi-label">Proyectos en riesgo</span>
                </div>
            </div>
            <div class="dc-kpi-card">
                <div class="dc-kpi-icon" style="background: color-mix(in srgb, var(--warning) 18%, var(--background)); color: var(--warning);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg></div>
                <div class="dc-kpi-body">
                    <span class="dc-kpi-value"><?= (int) ($summary['bloqueos_activos'] ?? 0) ?></span>
                    <span class="dc-kpi-label">Bloqueos activos</span>
                </div>
            </div>
            <div class="dc-kpi-card">
                <div class="dc-kpi-icon" style="background: color-mix(in srgb, var(--info) 18%, var(--background)); color: var(--info);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                <div class="dc-kpi-body">
                    <span class="dc-kpi-value"><?= number_format((float) ($summary['facturacion_pendiente'] ?? 0), 0) ?></span>
                    <span class="dc-kpi-label">Facturación pendiente</span>
                </div>
            </div>
            <div class="dc-kpi-card">
                <div class="dc-kpi-icon" style="background: color-mix(in srgb, var(--success) 18%, var(--background)); color: var(--success);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <div class="dc-kpi-body">
                    <span class="dc-kpi-value"><?= number_format((float) ($summary['utilizacion_promedio'] ?? 0), 1) ?>%</span>
                    <span class="dc-kpi-label">Utilización equipo</span>
                </div>
            </div>
        </div>
    </section>

    <!-- B. Alertas automáticas -->
    <section class="card">
        <h3 class="dc-section-title">Alertas automáticas</h3>
        <div class="dc-alerts-band" data-alert-filter>
            <?php foreach ($alerts as $alert): ?>
            <button type="button" class="dc-alert-chip" data-filter-key="<?= htmlspecialchars($alert['key'] ?? '') ?>">
                <?= htmlspecialchars($alert['label'] ?? '') ?>
                <?php if (!empty($alert['count'])): ?>
                <span class="count"><?= (int) $alert['count'] ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
            <?php if (empty($alerts)): ?>
            <span class="dc-alert-chip" style="cursor:default;">Sin alertas activas</span>
            <?php endif; ?>
        </div>
    </section>

    <!-- C. Decisiones recomendadas -->
    <section class="card">
        <h3 class="dc-section-title">Decisiones recomendadas</h3>
        <div class="dc-recommendations">
            <?php foreach ($recommendations as $rec): ?>
            <div class="dc-rec-item" data-rec-project="<?= (int) ($rec['project_id'] ?? 0) ?>">
                <div class="dc-rec-body">
                    <p class="dc-rec-title"><?= htmlspecialchars($rec['title'] ?? '') ?></p>
                    <p class="dc-rec-reason"><?= htmlspecialchars($rec['reason'] ?? '') ?></p>
                    <span class="dc-rec-impact <?= strtolower($rec['impact'] ?? 'medio') ?>"><?= htmlspecialchars($rec['impact'] ?? '') ?></span>
                </div>
                <div>
                    <?php
                    $action = $rec['action'] ?? 'view_project';
                    $pid = (int) ($rec['project_id'] ?? 0);
                    if ($action === 'view_project' && $pid > 0): ?>
                    <a href="<?= $basePath ?>/projects/<?= $pid ?>" class="btn primary sm">Ver proyecto</a>
                    <?php elseif ($action === 'open_blockers' && $pid > 0): ?>
                    <a href="<?= $basePath ?>/projects/<?= $pid ?>#stoppers" class="btn primary sm">Abrir bloqueos</a>
                    <?php elseif ($action === 'go_billing' && $pid > 0): ?>
                    <a href="<?= $basePath ?>/projects/<?= $pid ?>/billing" class="btn primary sm">Ir a facturación</a>
                    <?php elseif ($action === 'assign_resource'): ?>
                    <a href="<?= $basePath ?>/talent-capacity" class="btn primary sm">Asignar recurso</a>
                    <?php else: ?>
                    <a href="<?= $basePath ?>/projects/<?= $pid ?: '' ?>" class="btn primary sm">Ver</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recommendations)): ?>
            <p class="muted" style="margin:0;">No hay decisiones recomendadas en este momento.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- D. Tablas analíticas -->
    <section class="dc-tables-grid">
        <div class="dc-table-card">
            <div class="toolbar">
                <h3 class="dc-section-title" style="margin:0;">Ranking de proyectos</h3>
                <?php if ($canExport): ?>
                <a href="<?= $basePath ?>/api/pmo/decision-center?<?= http_build_query($filters) ?>" class="btn sm" download="decision-center-projects.json">Exportar</a>
                <?php endif; ?>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Cliente</th>
                            <th>Score</th>
                            <th>Última nota</th>
                            <th>Bloqueos</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($projectRanking, 0, 15) as $p): ?>
                        <tr>
                            <td><span class="dc-project-name" title="<?= htmlspecialchars($p['name'] ?? '') ?>"><?= htmlspecialchars($p['name'] ?? '') ?></span></td>
                            <td><?= htmlspecialchars($p['client'] ?? '') ?></td>
                            <td><?= (int) ($p['score'] ?? 0) ?></td>
                            <td>
                                <?php if (!empty($p['last_note'])): ?>
                                <span class="dc-note-preview" title="<?= htmlspecialchars($p['last_note']['preview'] ?? '') ?>"><?= htmlspecialchars(mb_substr($p['last_note']['preview'] ?? '', 0, 60)) ?><?= mb_strlen($p['last_note']['preview'] ?? '') > 60 ? '…' : '' ?></span>
                                <?php else: ?>
                                <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $st = $p['stoppers'] ?? ['count' => 0, 'max_severity' => '', 'top3' => []];
                                $cnt = (int) ($st['count'] ?? 0);
                                if ($cnt > 0):
                                    $sev = $st['max_severity'] ?? '';
                                    $top3 = implode('; ', array_slice($st['top3'] ?? [], 0, 3));
                                ?>
                                <span title="<?= htmlspecialchars($top3) ?>"><?= $cnt ?> <?= $sev ? "({$sev})" : '' ?></span>
                                <?php else: ?>
                                —
                                <?php endif; ?>
                            </td>
                            <td><a href="<?= $basePath ?>/projects/<?= (int) ($p['id'] ?? 0) ?>" class="btn sm">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($projectRanking)): ?>
                        <tr><td colspan="6" class="muted">No hay proyectos activos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dc-table-card">
            <div class="toolbar">
                <h3 class="dc-section-title" style="margin:0;">Capacidad del equipo</h3>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Talento</th>
                            <th>Rol</th>
                            <th>Asignadas</th>
                            <th>Capacidad</th>
                            <th>Utilización</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($teamCapacity['talents'] ?? [], 0, 15) as $t): ?>
                        <?php
                        $util = (float) ($t['utilization_pct'] ?? 0);
                        $utilClass = $util > 100 ? 'critical' : ($util > 90 ? 'overload' : 'ok');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($t['name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($t['role'] ?? '') ?></td>
                            <td><?= number_format((float) ($t['assigned_hours'] ?? 0), 1) ?>h</td>
                            <td><?= number_format((float) ($t['capacity_hours'] ?? 0), 1) ?>h</td>
                            <td><span class="dc-util-badge <?= $utilClass ?>"><?= number_format($util, 1) ?>%</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($teamCapacity['talents'])): ?>
                        <tr><td colspan="5" class="muted">No hay talentos registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Simulación de capacidad -->
    <section class="card">
        <h3 class="dc-section-title">Simular carga</h3>
        <div class="dc-simulate-panel">
            <h4>Impacto de cargar nuevas horas</h4>
            <form class="dc-simulate-form" id="dc-simulate-form">
                <div class="input-stack" style="max-width:200px;">
                    <label>Horas estimadas</label>
                    <input type="number" name="hours" min="0" step="1" value="40" required>
                </div>
                <div class="input-stack" style="max-width:200px;">
                    <label>Recursos requeridos</label>
                    <input type="number" name="resources_count" min="1" value="1">
                </div>
                <div class="input-stack" style="max-width:200px;">
                    <label>Desde</label>
                    <input type="date" name="period_start" value="<?= htmlspecialchars($filters['from'] ?? date('Y-m-01')) ?>">
                </div>
                <div class="input-stack" style="max-width:200px;">
                    <label>Hasta</label>
                    <input type="date" name="period_end" value="<?= htmlspecialchars($filters['to'] ?? date('Y-m-t')) ?>">
                </div>
                <button type="submit" class="btn primary">Simular</button>
            </form>
            <div id="dc-simulate-result" class="dc-simulate-result" style="display:none;"></div>
        </div>
    </section>
</div>

<script>
(function() {
    const form = document.getElementById('dc-simulate-form');
    const resultEl = document.getElementById('dc-simulate-result');
    const basePath = <?= json_encode($basePath) ?>;
    const apiBase = (basePath || '').replace(/\/$/, '') || (window.location.pathname.replace(/\/pmo\/decision-center.*$/i, '') || '');

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const payload = {
                hours: parseFloat(fd.get('hours') || 0),
                resources_count: parseInt(fd.get('resources_count') || 1, 10),
                period_start: fd.get('period_start') || '',
                period_end: fd.get('period_end') || '',
            };

            try {
                const res = await fetch(apiBase + '/api/pmo/decision-center/simulate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok) {
                    resultEl.innerHTML = '<p class="muted">Error: ' + (data.error || res.status) + '</p>';
                } else {
                    let html = '<p><strong>Utilización estimada:</strong> ' + (data.estimated_utilization_pct || 0) + '%</p>';
                    if ((data.talents_at_risk || []).length > 0) {
                        html += '<p><strong>Talentos en riesgo:</strong></p><ul>';
                        data.talents_at_risk.forEach(t => {
                            html += '<li>' + (t.name || '') + ': ' + (t.estimated_util || 0) + '%</li>';
                        });
                        html += '</ul>';
                    }
                    if ((data.affected_talents || []).length > 0) {
                        html += '<p><strong>Afectados:</strong> ' + data.affected_talents.join(', ') + '</p>';
                    }
                    resultEl.innerHTML = html;
                }
                resultEl.style.display = 'block';
            } catch (err) {
                resultEl.innerHTML = '<p class="muted">No se pudo calcular la simulación.</p>';
                resultEl.style.display = 'block';
            }
        });
    }
})();
</script>
