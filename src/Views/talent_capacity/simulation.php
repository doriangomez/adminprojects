<?php $basePath = $basePath ?? ''; ?>

<section class="sim-shell">
    <header class="sim-header">
        <div>
            <p class="eyebrow">Módulo de simulación</p>
            <h2>Simulación de Capacidad</h2>
            <small class="section-muted">Simula el impacto de nuevos proyectos en la carga del equipo antes de asignarlos. Solo simulación — no modifica datos reales.</small>
        </div>
        <div class="sim-header-actions">
            <a href="<?= $basePath ?>/talent-capacity" class="action-btn secondary">Vista de capacidad</a>
            <span class="badge neutral">Solo simulación</span>
        </div>
    </header>

    <div class="sim-layout">
        <aside class="sim-form-panel">
            <div class="section-title section-title-stack">
                <h3>Proyecto a simular</h3>
                <small class="section-muted">Ingresa los datos del proyecto hipotético.</small>
            </div>
            <form id="simForm" onsubmit="return false;">
                <label>
                    Nombre del proyecto
                    <input type="text" id="simProjectName" placeholder="Ej: Rediseño portal web" required>
                </label>
                <label>
                    Horas estimadas
                    <input type="number" id="simHours" min="1" max="10000" value="200" required>
                </label>
                <label>
                    Periodo de simulación
                    <select id="simPeriod">
                        <?php
                        $now = new DateTimeImmutable();
                        for ($i = 0; $i < 6; $i++) {
                            $m = $now->modify("+{$i} month");
                            $mEnd = $now->modify("+{$i} month")->modify('+1 month');
                            $label = strftime('%B %Y', $m->getTimestamp());
                            if (function_exists('IntlDateFormatter') || class_exists('IntlDateFormatter')) {
                                $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM yyyy');
                                $label = ucfirst($fmt->format($m));
                            } else {
                                $months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                                $label = $months[(int)$m->format('n') - 1] . ' ' . $m->format('Y');
                            }
                            $val = $m->format('Y-m-d') . '|' . $m->format('Y-m-t');
                            echo '<option value="' . $val . '"' . ($i === 0 ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
                        }
                        ?>
                    </select>
                </label>
                <label>
                    Método de distribución
                    <select id="simDistribution">
                        <option value="proportional">Proporcional a disponibilidad</option>
                        <option value="equal">Equitativa entre todos</option>
                    </select>
                </label>
                <button type="button" id="simRunBtn" class="action-btn primary sim-run-btn" onclick="runSimulation()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Ejecutar simulación
                </button>
            </form>
        </aside>

        <div class="sim-results-panel">
            <div id="simLoading" class="sim-loading" style="display:none;">
                <div class="sim-spinner"></div>
                <span>Cargando datos del equipo…</span>
            </div>

            <div id="simEmpty" class="sim-empty-state">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                <h4>Configura y ejecuta una simulación</h4>
                <p>Ingresa el nombre, horas estimadas y periodo del proyecto para ver el impacto en la carga del equipo.</p>
            </div>

            <div id="simResults" style="display:none;">
                <section class="sim-kpi-grid" id="simKpis"></section>

                <section class="sim-block">
                    <div class="section-title section-title-stack">
                        <h3>Impacto en el equipo</h3>
                        <small class="section-muted">Comparativa de carga actual vs. carga simulada por talento.</small>
                    </div>
                    <div class="sim-table-wrap">
                        <table class="sim-table" id="simTable">
                            <thead>
                                <tr>
                                    <th>Talento</th>
                                    <th>Rol</th>
                                    <th>Actual</th>
                                    <th>+ Simulado</th>
                                    <th>Total</th>
                                    <th>Capacidad</th>
                                    <th>Utilización final</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="simTableBody"></tbody>
                        </table>
                    </div>
                </section>

                <section class="sim-block sim-insights-block">
                    <div class="section-title section-title-stack">
                        <h3>Insights del sistema</h3>
                        <small class="section-muted">Análisis automático de viabilidad del proyecto simulado.</small>
                    </div>
                    <ul id="simInsights" class="sim-insights-list"></ul>
                </section>
            </div>
        </div>
    </div>
</section>

<style>
.sim-shell { display: flex; flex-direction: column; gap: 18px; }
.sim-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.sim-header-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.sim-layout { display: grid; grid-template-columns: 320px 1fr; gap: 18px; align-items: start; }
@media (max-width: 900px) { .sim-layout { grid-template-columns: 1fr; } }

.sim-form-panel {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 14px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    position: sticky;
    top: 20px;
}
.sim-form-panel form { display: flex; flex-direction: column; gap: 14px; }
.sim-form-panel label { display: flex; flex-direction: column; gap: 6px; color: var(--text-secondary); font-size: .9rem; font-weight: 600; }
.sim-form-panel input,
.sim-form-panel select {
    background: var(--background);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    color: var(--text-primary);
    font-size: .92rem;
    font-weight: 400;
}
.sim-form-panel input:focus,
.sim-form-panel select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent); }
.sim-run-btn {
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: .95rem;
    padding: 12px 16px;
}

.sim-results-panel { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

.sim-empty-state {
    border: 2px dashed var(--border);
    border-radius: 14px;
    padding: 48px 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    text-align: center;
    color: var(--text-secondary);
}
.sim-empty-state h4 { margin: 0; color: var(--text-primary); }
.sim-empty-state p { margin: 0; max-width: 380px; font-size: .9rem; line-height: 1.5; }

.sim-loading {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 14px;
    padding: 48px 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    color: var(--text-secondary);
}
.sim-spinner {
    width: 32px; height: 32px;
    border: 3px solid var(--border);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: sim-spin .7s linear infinite;
}
@keyframes sim-spin { to { transform: rotate(360deg); } }

.sim-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
.sim-kpi {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 14px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.sim-kpi strong { font-size: 1.5rem; }
.sim-kpi span { font-size: .82rem; color: var(--text-secondary); }
.sim-kpi.green strong { color: #16a34a; }
.sim-kpi.yellow strong { color: #d97706; }
.sim-kpi.red strong { color: #dc2626; }
.sim-kpi.blue strong { color: #2563eb; }

.sim-block {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 14px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.sim-table-wrap { overflow-x: auto; }
.sim-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .88rem; }
.sim-table th,
.sim-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap; }
.sim-table thead th {
    background: color-mix(in srgb, var(--surface) 70%, var(--background));
    font-weight: 700;
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: var(--text-secondary);
    position: sticky;
    top: 0;
    z-index: 1;
}
.sim-table tbody tr:hover { background: color-mix(in srgb, var(--background) 50%, var(--surface)); }

.sim-util-bar {
    display: flex;
    align-items: center;
    gap: 8px;
}
.sim-util-track {
    width: 80px;
    height: 8px;
    background: color-mix(in srgb, var(--background) 92%, var(--surface));
    border-radius: 999px;
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, .2);
}
.sim-util-fill { height: 100%; display: block; border-radius: 999px; transition: width .4s ease; }
.sim-util-fill.green { background: #22c55e; }
.sim-util-fill.yellow { background: #f59e0b; }
.sim-util-fill.red { background: #ef4444; }

.sim-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 600;
}
.sim-status-badge.green { background: rgba(34, 197, 94, .12); color: #15803d; border: 1px solid rgba(34, 197, 94, .3); }
.sim-status-badge.yellow { background: rgba(245, 158, 11, .12); color: #92400e; border: 1px solid rgba(245, 158, 11, .3); }
.sim-status-badge.red { background: rgba(239, 68, 68, .12); color: #b91c1c; border: 1px solid rgba(239, 68, 68, .3); }

.sim-delta { font-size: .82rem; color: var(--text-secondary); }
.sim-delta.warn { color: #d97706; }
.sim-delta.danger { color: #dc2626; }

.sim-insights-block { background: linear-gradient(130deg, color-mix(in srgb, var(--surface) 92%, #dbeafe), color-mix(in srgb, var(--surface) 94%, #f1f5f9)); }
.sim-insights-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.sim-insight-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: .88rem;
    line-height: 1.45;
    border: 1px solid color-mix(in srgb, var(--border) 80%, #bfdbfe);
    background: color-mix(in srgb, var(--surface) 92%, #eff6ff);
}
.sim-insight-item .insight-icon { flex-shrink: 0; width: 20px; height: 20px; margin-top: 1px; }
.sim-insight-item.positive { border-color: rgba(34, 197, 94, .3); background: rgba(34, 197, 94, .06); }
.sim-insight-item.warning { border-color: rgba(245, 158, 11, .3); background: rgba(245, 158, 11, .06); }
.sim-insight-item.danger { border-color: rgba(239, 68, 68, .3); background: rgba(239, 68, 68, .06); }
.sim-insight-item.info { border-color: rgba(59, 130, 246, .3); background: rgba(59, 130, 246, .06); }
</style>

<script>
(function() {
    let talentData = null;

    async function loadTalentData() {
        const loading = document.getElementById('simLoading');
        const empty = document.getElementById('simEmpty');
        loading.style.display = 'flex';
        empty.style.display = 'none';

        try {
            const res = await fetch('<?= $basePath ?>/talent-capacity/simulation/data');
            if (!res.ok) throw new Error('Error al cargar datos');
            talentData = await res.json();
        } catch (e) {
            loading.style.display = 'none';
            empty.style.display = 'flex';
            empty.querySelector('h4').textContent = 'Error al cargar datos del equipo';
            empty.querySelector('p').textContent = e.message;
            return false;
        }

        loading.style.display = 'none';
        return true;
    }

    function getColor(util) {
        if (util > 90) return 'red';
        if (util >= 70) return 'yellow';
        return 'green';
    }

    function getStatusLabel(util) {
        if (util > 100) return 'Sobrecarga';
        if (util > 90) return 'Riesgo';
        if (util >= 70) return 'Alto';
        return 'Normal';
    }

    function distribute(talents, totalHours, method) {
        const results = [];
        if (method === 'equal') {
            const perTalent = totalHours / talents.length;
            talents.forEach(t => {
                results.push({ ...t, simulated_hours: Math.round(perTalent * 10) / 10 });
            });
        } else {
            let totalAvailable = 0;
            talents.forEach(t => {
                const avail = Math.max(0, t.monthly_capacity - t.current_hours);
                totalAvailable += avail;
            });

            if (totalAvailable <= 0) {
                const perTalent = totalHours / talents.length;
                talents.forEach(t => {
                    results.push({ ...t, simulated_hours: Math.round(perTalent * 10) / 10 });
                });
            } else {
                talents.forEach(t => {
                    const avail = Math.max(0, t.monthly_capacity - t.current_hours);
                    const ratio = avail / totalAvailable;
                    const assigned = totalHours * ratio;
                    results.push({ ...t, simulated_hours: Math.round(assigned * 10) / 10 });
                });
            }
        }
        return results;
    }

    function renderResults(simTalents, projectName, totalHours) {
        const tbody = document.getElementById('simTableBody');
        const kpis = document.getElementById('simKpis');
        const insights = document.getElementById('simInsights');

        let teamCapacity = 0, teamCurrentHours = 0, teamSimHours = 0;
        let overloadCount = 0, riskCount = 0;

        tbody.innerHTML = '';
        simTalents.forEach(t => {
            const total = t.current_hours + t.simulated_hours;
            const util = t.monthly_capacity > 0 ? (total / t.monthly_capacity) * 100 : 0;
            const color = getColor(util);
            const status = getStatusLabel(util);
            teamCapacity += t.monthly_capacity;
            teamCurrentHours += t.current_hours;
            teamSimHours += t.simulated_hours;
            if (util > 100) overloadCount++;
            else if (util > 90) riskCount++;

            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${esc(t.name)}</strong></td>
                <td>${esc(t.role || '—')}</td>
                <td>${t.current_hours.toFixed(1)}h</td>
                <td class="sim-delta ${color === 'red' ? 'danger' : color === 'yellow' ? 'warn' : ''}">+${t.simulated_hours.toFixed(1)}h</td>
                <td><strong>${total.toFixed(1)}h</strong></td>
                <td>${t.monthly_capacity.toFixed(1)}h</td>
                <td>
                    <div class="sim-util-bar">
                        <div class="sim-util-track"><span class="sim-util-fill ${color}" style="width:${Math.min(100, util).toFixed(0)}%"></span></div>
                        <span>${util.toFixed(1)}%</span>
                    </div>
                </td>
                <td><span class="sim-status-badge ${color}">${status}</span></td>
            `;
            tbody.appendChild(row);
        });

        const teamUtil = teamCapacity > 0 ? ((teamCurrentHours + teamSimHours) / teamCapacity) * 100 : 0;
        const remaining = Math.max(0, teamCapacity - teamCurrentHours - teamSimHours);
        const committed = teamCurrentHours + teamSimHours;
        const commitColor = getColor(teamUtil);

        kpis.innerHTML = `
            <article class="sim-kpi blue"><strong>${teamCapacity.toFixed(0)}h</strong><span>Capacidad total equipo</span></article>
            <article class="sim-kpi ${commitColor}"><strong>${committed.toFixed(0)}h</strong><span>Capacidad comprometida</span></article>
            <article class="sim-kpi ${remaining > 0 ? 'green' : 'red'}"><strong>${remaining.toFixed(0)}h</strong><span>Capacidad restante</span></article>
            <article class="sim-kpi ${commitColor}"><strong>${teamUtil.toFixed(1)}%</strong><span>Utilización simulada</span></article>
        `;

        insights.innerHTML = '';
        const addInsight = (type, icon, text) => {
            const li = document.createElement('li');
            li.className = 'sim-insight-item ' + type;
            li.innerHTML = `<span class="insight-icon">${icon}</span><span>${text}</span>`;
            insights.appendChild(li);
        };

        const svgCheck = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
        const svgWarn = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        const svgDanger = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
        const svgInfo = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';

        if (overloadCount === 0 && riskCount === 0) {
            addInsight('positive', svgCheck, 'Ningún talento supera el 90% de capacidad con esta simulación.');
        }
        if (overloadCount > 0) {
            addInsight('danger', svgDanger, `${overloadCount} talento(s) superarían el 100% de capacidad. Se requiere redistribuir carga o ampliar el equipo.`);
        }
        if (riskCount > 0) {
            addInsight('warning', svgWarn, `${riskCount} talento(s) estarían en zona de riesgo (90-100%). Monitorear de cerca.`);
        }

        if (teamUtil <= 90 && overloadCount === 0) {
            addInsight('positive', svgCheck, `El proyecto "${esc(projectName)}" es viable con el equipo actual.`);
        } else if (teamUtil > 100) {
            addInsight('danger', svgDanger, `El proyecto "${esc(projectName)}" excede la capacidad actual del equipo. Considerar ampliar recursos.`);
        } else {
            addInsight('warning', svgWarn, `El proyecto "${esc(projectName)}" lleva al equipo a zona de alta utilización. Evaluar con cuidado.`);
        }

        addInsight('info', svgInfo, `Capacidad disponible restante después de la simulación: ${remaining.toFixed(0)}h (${(remaining / teamCapacity * 100).toFixed(1)}% del total).`);

        const topLoaded = [...simTalents]
            .map(t => ({ name: t.name, util: t.monthly_capacity > 0 ? ((t.current_hours + t.simulated_hours) / t.monthly_capacity) * 100 : 0 }))
            .sort((a, b) => b.util - a.util)
            .slice(0, 3);

        if (topLoaded.length > 0 && topLoaded[0].util > 70) {
            const names = topLoaded.map(t => `${t.name} (${t.util.toFixed(1)}%)`).join(', ');
            addInsight('info', svgInfo, `Talentos con mayor carga simulada: ${names}.`);
        }
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    window.runSimulation = async function() {
        const name = document.getElementById('simProjectName').value.trim();
        const hours = parseFloat(document.getElementById('simHours').value) || 0;
        const distribution = document.getElementById('simDistribution').value;

        if (!name) { alert('Ingresa un nombre de proyecto.'); return; }
        if (hours <= 0) { alert('Las horas estimadas deben ser mayores a 0.'); return; }

        if (!talentData) {
            const ok = await loadTalentData();
            if (!ok) return;
        }

        if (!talentData.talents || talentData.talents.length === 0) {
            document.getElementById('simEmpty').style.display = 'flex';
            document.getElementById('simEmpty').querySelector('h4').textContent = 'No hay talentos registrados';
            document.getElementById('simEmpty').querySelector('p').textContent = 'No se encontraron talentos para simular.';
            return;
        }

        const simTalents = distribute(talentData.talents, hours, distribution);
        document.getElementById('simEmpty').style.display = 'none';
        document.getElementById('simResults').style.display = 'flex';
        document.getElementById('simResults').style.flexDirection = 'column';
        document.getElementById('simResults').style.gap = '16px';
        renderResults(simTalents, name, hours);
    };

    document.addEventListener('DOMContentLoaded', function() {
        loadTalentData().then(function() {
            document.getElementById('simEmpty').style.display = 'flex';
        });
    });
})();
</script>
