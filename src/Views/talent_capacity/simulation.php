<?php
$basePath = $basePath ?? '';
$activeTab = $activeTab ?? 'simulation';
$simTalents = is_array($simTalents ?? null) ? $simTalents : [];
$simRange = is_array($simRange ?? null) ? $simRange : [];

$months = [];
$cursor = new DateTimeImmutable($simRange['start'] ?? date('Y-m-01'));
$limit = new DateTimeImmutable($simRange['end'] ?? date('Y-m-t', strtotime('+2 months')));
while ($cursor <= $limit) {
    $key = $cursor->format('Y-m');
    $months[$key] = $cursor->format('F Y');
    $cursor = $cursor->modify('first day of next month');
}
?>

<?php include __DIR__ . '/_tabs.php'; ?>

<section class="sim-shell">
    <header class="sim-header">
        <div>
            <p class="eyebrow">Módulo de simulación</p>
            <h2>Simulación de Capacidad</h2>
            <small class="section-muted">Simula el impacto de nuevos proyectos en la carga del equipo antes de asignarlos. Solo simulación, no modifica datos reales.</small>
        </div>
        <span class="badge sim-badge-info">Solo simulación</span>
    </header>

    <section class="sim-block sim-form-block">
        <div class="section-title section-title-stack">
            <h3>Proyecto a simular</h3>
            <small class="section-muted">Ingresa los datos del proyecto hipotético para evaluar su impacto en el equipo.</small>
        </div>
        <div class="sim-form-grid">
            <div class="sim-field">
                <label for="sim-project-name">Nombre del proyecto</label>
                <input type="text" id="sim-project-name" placeholder="Ej: Rediseño portal web" autocomplete="off">
            </div>
            <div class="sim-field">
                <label for="sim-hours">Horas estimadas</label>
                <input type="number" id="sim-hours" min="1" max="99999" step="1" value="200" placeholder="200">
            </div>
            <div class="sim-field">
                <label for="sim-period-start">Periodo desde</label>
                <select id="sim-period-start">
                    <?php foreach ($months as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sim-field">
                <label for="sim-period-end">Periodo hasta</label>
                <select id="sim-period-end">
                    <?php $lastKey = array_key_last($months); ?>
                    <?php foreach ($months as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $key === $lastKey ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sim-field sim-field-action">
                <button type="button" id="sim-run-btn" class="action-btn primary sim-run-btn">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Ejecutar simulación
                </button>
            </div>
        </div>
    </section>

    <section class="sim-block sim-results-block" id="sim-results" style="display:none;">
        <div class="section-title section-title-stack">
            <h3>Impacto en el equipo</h3>
            <small class="section-muted" id="sim-results-subtitle"></small>
        </div>

        <div class="sim-kpi-row" id="sim-kpis"></div>

        <div class="sim-table-wrap">
            <table class="sim-table" id="sim-table">
                <thead>
                    <tr>
                        <th>Talento</th>
                        <th>Rol</th>
                        <th>Actual</th>
                        <th>Simulado</th>
                        <th>Capacidad</th>
                        <th>Utilización final</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="sim-table-body"></tbody>
            </table>
        </div>
    </section>

    <section class="sim-block sim-insights-block" id="sim-insights" style="display:none;">
        <div class="section-title section-title-stack">
            <h3>Insights del sistema</h3>
            <small class="section-muted">Análisis automático del resultado de la simulación.</small>
        </div>
        <div id="sim-insights-list" class="sim-insights-list"></div>
    </section>
</section>

<script>
(function () {
    var talents = <?= json_encode($simTalents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var runBtn = document.getElementById('sim-run-btn');
    var resultsSection = document.getElementById('sim-results');
    var insightsSection = document.getElementById('sim-insights');
    var tableBody = document.getElementById('sim-table-body');
    var kpisContainer = document.getElementById('sim-kpis');
    var insightsList = document.getElementById('sim-insights-list');
    var subtitle = document.getElementById('sim-results-subtitle');

    runBtn.addEventListener('click', runSimulation);

    function runSimulation() {
        var projectName = document.getElementById('sim-project-name').value.trim() || 'Proyecto simulado';
        var totalHours = parseFloat(document.getElementById('sim-hours').value) || 0;
        var periodStart = document.getElementById('sim-period-start').value;
        var periodEnd = document.getElementById('sim-period-end').value;

        if (totalHours <= 0) {
            alert('Ingresa un número de horas estimadas mayor a 0.');
            return;
        }
        if (periodStart > periodEnd) {
            alert('El periodo de inicio debe ser anterior o igual al periodo final.');
            return;
        }

        var activeTalents = talents.filter(function (t) { return t.period_capacity > 0; });
        if (activeTalents.length === 0) {
            alert('No hay talentos con capacidad registrada para simular.');
            return;
        }

        var totalAvailable = 0;
        activeTalents.forEach(function (t) {
            totalAvailable += Math.max(0, t.period_capacity - t.assigned_hours);
        });

        var hoursToDistribute = totalHours;
        var distribution = activeTalents.map(function (t) {
            var available = Math.max(0, t.period_capacity - t.assigned_hours);
            var proportion = totalAvailable > 0 ? available / totalAvailable : 1 / activeTalents.length;
            var share = Math.round(hoursToDistribute * proportion * 10) / 10;
            return {
                id: t.id,
                name: t.name,
                role: t.role,
                currentHours: t.assigned_hours,
                capacity: t.period_capacity,
                weeklyCapacity: t.weekly_capacity,
                currentUtil: t.utilization,
                simHours: share,
                totalHours: 0,
                simUtil: 0,
                status: ''
            };
        });

        distribution.forEach(function (d) {
            d.totalHours = Math.round((d.currentHours + d.simHours) * 10) / 10;
            d.simUtil = d.capacity > 0 ? Math.round((d.totalHours / d.capacity) * 1000) / 10 : 0;
            if (d.simUtil > 100) d.status = 'overload';
            else if (d.simUtil >= 90) d.status = 'high';
            else if (d.simUtil >= 60) d.status = 'healthy';
            else d.status = 'low';
        });

        distribution.sort(function (a, b) { return b.simUtil - a.simUtil; });

        subtitle.textContent = '"' + projectName + '" — ' + totalHours + 'h estimadas (' + periodStart + ' a ' + periodEnd + ')';

        renderTable(distribution);
        renderKPIs(distribution, totalHours);
        renderInsights(distribution, projectName, totalHours);

        resultsSection.style.display = '';
        insightsSection.style.display = '';
        resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderTable(rows) {
        tableBody.innerHTML = '';
        rows.forEach(function (r) {
            var tr = document.createElement('tr');
            var statusLabels = { overload: 'Sobrecarga', high: 'Alto', healthy: 'Saludable', low: 'Bajo' };
            var statusClass = 'sim-status-' + r.status;
            tr.innerHTML =
                '<td><strong>' + esc(r.name) + '</strong></td>' +
                '<td class="sim-td-muted">' + esc(r.role || '—') + '</td>' +
                '<td>' + r.currentHours.toFixed(1) + 'h</td>' +
                '<td class="sim-td-highlight">' + r.totalHours.toFixed(1) + 'h <small>(+' + r.simHours.toFixed(1) + ')</small></td>' +
                '<td>' + r.capacity.toFixed(1) + 'h</td>' +
                '<td><div class="sim-util-bar"><span class="sim-util-fill sim-util-' + r.status + '" style="width:' + Math.min(100, Math.max(0, r.simUtil)) + '%"></span></div><span class="sim-util-pct">' + r.simUtil.toFixed(1) + '%</span></td>' +
                '<td><span class="sim-status-pill ' + statusClass + '">' + statusLabels[r.status] + '</span></td>';
            tableBody.appendChild(tr);
        });
    }

    function renderKPIs(distribution, totalHours) {
        var overloaded = distribution.filter(function (d) { return d.simUtil > 90; }).length;
        var totalCapacity = 0;
        var totalAssigned = 0;
        distribution.forEach(function (d) {
            totalCapacity += d.capacity;
            totalAssigned += d.totalHours;
        });
        var remaining = Math.max(0, totalCapacity - totalAssigned);
        var avgUtil = totalCapacity > 0 ? (totalAssigned / totalCapacity) * 100 : 0;

        kpisContainer.innerHTML =
            '<article class="sim-kpi"><strong>' + avgUtil.toFixed(1) + '%</strong><span>Utilización promedio simulada</span></article>' +
            '<article class="sim-kpi ' + (overloaded > 0 ? 'sim-kpi-danger' : 'sim-kpi-success') + '"><strong>' + overloaded + '</strong><span>Talentos en riesgo (&gt;90%)</span></article>' +
            '<article class="sim-kpi"><strong>' + remaining.toFixed(0) + 'h</strong><span>Capacidad disponible restante</span></article>' +
            '<article class="sim-kpi"><strong>' + totalHours.toFixed(0) + 'h</strong><span>Horas del proyecto simulado</span></article>';
    }

    function renderInsights(distribution, projectName, totalHours) {
        var insights = [];
        var overloaded = distribution.filter(function (d) { return d.simUtil > 90; });
        var totalCapacity = 0;
        var totalAssigned = 0;
        distribution.forEach(function (d) {
            totalCapacity += d.capacity;
            totalAssigned += d.totalHours;
        });
        var remaining = Math.max(0, totalCapacity - totalAssigned);

        if (overloaded.length === 0) {
            insights.push({ type: 'success', text: 'Ningún talento supera el 90% de capacidad tras la simulación.' });
            insights.push({ type: 'success', text: 'El proyecto "' + projectName + '" es viable con el equipo actual.' });
        } else {
            insights.push({ type: 'danger', text: overloaded.length + ' talento(s) superaría(n) el 90% de capacidad: ' + overloaded.map(function (d) { return d.name + ' (' + d.simUtil.toFixed(1) + '%)'; }).join(', ') + '.' });
            if (overloaded.length > distribution.length / 2) {
                insights.push({ type: 'danger', text: 'El proyecto "' + projectName + '" podría generar sobrecarga significativa en el equipo. Se recomienda revisar alcance o ampliar capacidad.' });
            } else {
                insights.push({ type: 'warning', text: 'El proyecto es factible pero requiere redistribución de carga en los talentos en riesgo.' });
            }
        }

        insights.push({ type: 'info', text: 'Capacidad disponible restante tras simulación: ' + remaining.toFixed(0) + 'h de ' + totalCapacity.toFixed(0) + 'h totales.' });

        var maxUtil = distribution.length > 0 ? distribution[0] : null;
        if (maxUtil) {
            if (maxUtil.simUtil > 100) {
                insights.push({ type: 'danger', text: maxUtil.name + ' sería el talento más afectado con ' + maxUtil.simUtil.toFixed(1) + '% de utilización (sobrecarga).' });
            } else if (maxUtil.simUtil >= 90) {
                insights.push({ type: 'warning', text: maxUtil.name + ' sería el talento más cargado con ' + maxUtil.simUtil.toFixed(1) + '% de utilización.' });
            }
        }

        var minUtil = distribution.length > 0 ? distribution[distribution.length - 1] : null;
        if (minUtil && minUtil.simUtil < 60) {
            insights.push({ type: 'info', text: minUtil.name + ' mantendría la menor carga (' + minUtil.simUtil.toFixed(1) + '%), con margen para absorber más horas si se requiere.' });
        }

        insightsList.innerHTML = '';
        insights.forEach(function (insight) {
            var div = document.createElement('div');
            div.className = 'sim-insight sim-insight-' + insight.type;
            var icon = insight.type === 'success' ? '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                : insight.type === 'danger' ? '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
                : insight.type === 'warning' ? '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                : '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
            div.innerHTML = '<span class="sim-insight-icon">' + icon + '</span><span>' + esc(insight.text) + '</span>';
            insightsList.appendChild(div);
        });
    }

    function esc(str) {
        var el = document.createElement('span');
        el.textContent = str;
        return el.innerHTML;
    }
})();
</script>

<style>
.sim-shell { display: flex; flex-direction: column; gap: 18px; }
.sim-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.sim-header h2 { margin: 0; }
.sim-badge-info {
    background: color-mix(in srgb, var(--info) 14%, var(--background));
    color: var(--info);
    border-color: color-mix(in srgb, var(--info) 35%, var(--border));
    font-weight: 700;
}

.sim-block {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 14px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.sim-form-block {
    background: linear-gradient(130deg,
        color-mix(in srgb, var(--surface) 94%, #dbeafe),
        color-mix(in srgb, var(--surface) 96%, #f1f5f9));
}
.sim-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    align-items: end;
}
.sim-field { display: flex; flex-direction: column; gap: 6px; }
.sim-field label { font-size: .88rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.sim-field input,
.sim-field select {
    background: var(--background);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    color: var(--text-primary);
    font-weight: 500;
    width: 100%;
}
.sim-field-action { display: flex; align-items: flex-end; }
.sim-run-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 22px;
    border-radius: 12px;
    border: 1px solid var(--primary);
    background: var(--primary);
    color: var(--text-primary);
    font-weight: 700;
    font-size: .92rem;
    cursor: pointer;
    white-space: nowrap;
    transition: all .2s ease;
}
.sim-run-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px color-mix(in srgb, var(--primary) 30%, transparent);
    background: color-mix(in srgb, var(--primary) 88%, var(--accent) 12%);
}
.sim-run-btn svg { flex-shrink: 0; }

.sim-kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}
.sim-kpi {
    border: 1px solid var(--border);
    background: color-mix(in srgb, var(--surface) 90%, var(--background));
    border-radius: 12px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.sim-kpi strong { font-size: 1.5rem; color: var(--primary); }
.sim-kpi span { font-size: .82rem; color: var(--text-secondary); }
.sim-kpi-danger { border-color: color-mix(in srgb, var(--danger) 35%, var(--border)); }
.sim-kpi-danger strong { color: var(--danger); }
.sim-kpi-success { border-color: color-mix(in srgb, var(--success) 35%, var(--border)); }
.sim-kpi-success strong { color: var(--success); }

.sim-table-wrap { overflow-x: auto; }
.sim-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin: 0;
}
.sim-table th {
    padding: 10px 14px;
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--text-secondary);
    font-weight: 700;
    background: color-mix(in srgb, var(--surface) 92%, var(--background));
    text-align: left;
    border-bottom: 1px solid var(--border);
}
.sim-table td {
    padding: 10px 14px;
    font-size: .88rem;
    color: var(--text-primary);
    border-bottom: 1px solid color-mix(in srgb, var(--border) 60%, var(--surface));
    vertical-align: middle;
}
.sim-table tr:last-child td { border-bottom: none; }
.sim-table tbody tr:hover { background: color-mix(in srgb, var(--surface) 84%, var(--background)); }
.sim-td-muted { color: var(--text-secondary); }
.sim-td-highlight { font-weight: 700; }
.sim-td-highlight small { font-weight: 500; color: var(--text-secondary); font-size: .78rem; }

.sim-util-bar {
    height: 10px;
    background: color-mix(in srgb, var(--background) 90%, var(--surface));
    border-radius: 999px;
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--border) 50%, var(--surface));
    min-width: 80px;
    display: inline-block;
    vertical-align: middle;
    margin-right: 8px;
}
.sim-util-fill {
    height: 100%;
    display: block;
    border-radius: 999px;
    transition: width .4s ease;
}
.sim-util-low { background: rgba(148, 163, 184, .45); }
.sim-util-healthy { background: rgba(34, 197, 94, .55); }
.sim-util-high { background: rgba(250, 204, 21, .65); }
.sim-util-overload { background: rgba(239, 68, 68, .65); }
.sim-util-pct { font-weight: 700; font-size: .85rem; }

.sim-status-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 700;
    border: 1px solid var(--border);
}
.sim-status-low { background: rgba(148, 163, 184, .12); color: var(--text-secondary); }
.sim-status-healthy { background: rgba(34, 197, 94, .12); color: #15803d; border-color: rgba(34, 197, 94, .35); }
.sim-status-high { background: rgba(250, 204, 21, .14); color: #92400e; border-color: rgba(245, 158, 11, .35); }
.sim-status-overload { background: rgba(239, 68, 68, .12); color: #b91c1c; border-color: rgba(239, 68, 68, .35); }

.sim-insights-block {
    background: linear-gradient(130deg,
        color-mix(in srgb, var(--surface) 93%, #f0fdf4),
        color-mix(in srgb, var(--surface) 95%, #f1f5f9));
}
.sim-insights-list { display: flex; flex-direction: column; gap: 10px; }
.sim-insight {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: var(--surface);
    font-size: .88rem;
    line-height: 1.5;
    color: var(--text-primary);
}
.sim-insight-icon { flex-shrink: 0; margin-top: 1px; }
.sim-insight-success { border-color: rgba(34, 197, 94, .35); background: rgba(34, 197, 94, .06); }
.sim-insight-success .sim-insight-icon { color: #16a34a; }
.sim-insight-danger { border-color: rgba(239, 68, 68, .35); background: rgba(239, 68, 68, .06); }
.sim-insight-danger .sim-insight-icon { color: #dc2626; }
.sim-insight-warning { border-color: rgba(245, 158, 11, .35); background: rgba(245, 158, 11, .08); }
.sim-insight-warning .sim-insight-icon { color: #d97706; }
.sim-insight-info { border-color: color-mix(in srgb, var(--info) 30%, var(--border)); background: color-mix(in srgb, var(--info) 6%, var(--surface)); }
.sim-insight-info .sim-insight-icon { color: var(--info); }

.section-title-stack { display: flex; flex-direction: column; gap: 2px; }
.section-title-stack h3 { margin: 0; }
.eyebrow { font-size: .76rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-secondary); margin: 0 0 4px 0; }
.section-muted { color: var(--text-secondary); font-size: .84rem; }

@media (max-width: 768px) {
    .sim-form-grid { grid-template-columns: 1fr; }
    .sim-kpi-row { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
    .sim-kpi-row { grid-template-columns: 1fr; }
}
</style>
