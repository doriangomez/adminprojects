<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filterOptions ?? null) ? $filterOptions : [];
$summary = is_array($portfolioSummary ?? null) ? $portfolioSummary : [];
$alerts = is_array($alerts ?? null) ? $alerts : [];
$recommendations = is_array($recommendations ?? null) ? $recommendations : [];
$projectRanking = is_array($projectRanking ?? null) ? $projectRanking : [];
$teamCapacity = is_array($teamCapacity ?? null) ? $teamCapacity : ['rows' => [], 'talents_without_report' => 0];
$teamRows = is_array($teamCapacity['rows'] ?? null) ? $teamCapacity['rows'] : [];
$activeAlert = (string) ($filters['alert'] ?? '');

$currencyFormatter = static function (float $amount, string $currency): string {
    return $currency . ' ' . number_format($amount, 2, ',', '.');
};

$impactClass = static function (string $impact): string {
    $impact = strtolower(trim($impact));
    return match ($impact) {
        'alto', 'high' => 'impact-high',
        'medio', 'medium' => 'impact-medium',
        default => 'impact-low',
    };
};

$utilizationClass = static function (float $utilization): string {
    if ($utilization > 100) {
        return 'tone-danger';
    }
    if ($utilization > 90) {
        return 'tone-warning';
    }
    if ($utilization >= 70) {
        return 'tone-info';
    }
    return 'tone-ok';
};

$truncateText = static function (string $text, int $width = 70): string {
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $width, '...');
    }
    return strlen($text) > $width ? (substr($text, 0, $width - 3) . '...') : $text;
};
?>

<section class="decision-center">
    <style>
        .decision-center { display: flex; flex-direction: column; gap: 14px; }
        .dc-card {
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: 14px;
            padding: 14px;
            box-shadow: 0 12px 22px color-mix(in srgb, var(--text-primary) 8%, transparent);
        }
        .dc-card h3 { margin: 0; font-size: 18px; color: var(--text-primary); }
        .dc-subtitle { margin: 4px 0 0; color: var(--text-secondary); font-size: 13px; }
        .dc-toolbar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
            align-items: end;
        }
        .dc-toolbar label {
            display: flex;
            flex-direction: column;
            gap: 6px;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .dc-toolbar .actions { display: flex; gap: 8px; align-items: end; }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        .kpi {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            background: color-mix(in srgb, var(--surface) 90%, var(--background));
        }
        .kpi .label {
            color: var(--text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 700;
        }
        .kpi .value {
            color: var(--text-primary);
            font-size: 28px;
            font-weight: 900;
            line-height: 1.1;
        }
        .kpi .meta { color: var(--text-secondary); font-size: 12px; }
        .alerts-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .alert-chip {
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 7px 12px;
            background: color-mix(in srgb, var(--surface) 90%, var(--background));
            color: var(--text-primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
        }
        .alert-chip.active {
            border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
            background: color-mix(in srgb, var(--primary) 14%, var(--surface));
            color: var(--primary);
        }
        .alert-chip .count {
            min-width: 22px;
            text-align: center;
            border-radius: 999px;
            padding: 2px 8px;
            background: color-mix(in srgb, var(--background) 80%, var(--surface));
        }
        .recommendation-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .recommendation-item {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            background: color-mix(in srgb, var(--surface) 92%, var(--background));
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .recommendation-item h4 { margin: 0; font-size: 14px; color: var(--text-primary); }
        .recommendation-item p { margin: 0; color: var(--text-secondary); font-size: 13px; }
        .impact-pill {
            display: inline-flex;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
            border: 1px solid transparent;
        }
        .impact-pill.impact-high { background: color-mix(in srgb, var(--danger) 14%, var(--surface)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--border)); }
        .impact-pill.impact-medium { background: color-mix(in srgb, var(--warning) 16%, var(--surface)); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 38%, var(--border)); }
        .impact-pill.impact-low { background: color-mix(in srgb, var(--success) 14%, var(--surface)); color: var(--success); border-color: color-mix(in srgb, var(--success) 38%, var(--border)); }
        .analytics-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 10px;
        }
        .table-wrap { overflow-x: auto; margin-top: 10px; }
        .tag {
            display: inline-flex;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 700;
            border: 1px solid var(--border);
            background: color-mix(in srgb, var(--surface) 90%, var(--background));
        }
        .risk-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
            border: 1px solid transparent;
        }
        .risk-pill.is-risk {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }
        .risk-pill.is-stable {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }
        .tone-danger { color: var(--danger); }
        .tone-warning { color: var(--warning); }
        .tone-info { color: var(--info); }
        .tone-ok { color: var(--success); }
        .sim-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
            margin-top: 10px;
            align-items: end;
        }
        .sim-result {
            margin-top: 10px;
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 10px;
            background: color-mix(in srgb, var(--surface) 88%, var(--background));
        }
        .sim-result ul { margin: 6px 0 0; padding-left: 18px; color: var(--text-primary); }
        .ai-box {
            border: 1px solid color-mix(in srgb, var(--primary) 46%, var(--border));
            background: linear-gradient(130deg, color-mix(in srgb, var(--primary) 14%, var(--surface)), color-mix(in srgb, var(--info) 10%, var(--surface)));
        }
        .ai-box pre {
            white-space: pre-wrap;
            margin: 10px 0 0;
            font: inherit;
            color: var(--text-primary);
            line-height: 1.45;
        }
        @media (max-width: 1100px) {
            .analytics-grid { grid-template-columns: 1fr; }
        }
    </style>

    <?php if (!empty($canUseAi) && is_array($intelligentAnalysis ?? null)): ?>
        <article class="dc-card ai-box">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <div>
                    <h3>Análisis inteligente</h3>
                    <p class="dc-subtitle">
                        <?= !empty($intelligentAnalysis['cached']) ? 'Resultado desde caché (15 min).' : 'Resultado generado en tiempo real.' ?>
                        <?= !empty($intelligentAnalysis['generated_at']) ? ' · ' . htmlspecialchars((string) $intelligentAnalysis['generated_at']) : '' ?>
                    </p>
                </div>
                <a class="btn secondary" href="/pmo/decision-center?<?= http_build_query(array_merge($filters, ['refresh_ai' => 1])) ?>">Generar análisis</a>
            </div>
            <pre><?= htmlspecialchars((string) ($intelligentAnalysis['text'] ?? 'No se pudo generar análisis.')) ?></pre>
        </article>
    <?php endif; ?>

    <article class="dc-card">
        <h3>Centro de Decisiones PMO</h3>
        <p class="dc-subtitle">Filtros de portafolio y periodo operativo.</p>
        <form class="dc-toolbar" method="GET" action="/pmo/decision-center" id="dc-filter-form">
            <input type="hidden" name="alert" id="dc-alert-input" value="<?= htmlspecialchars($activeAlert) ?>">
            <label>Desde
                <input type="date" name="from" value="<?= htmlspecialchars((string) ($filters['from'] ?? '')) ?>">
            </label>
            <label>Hasta
                <input type="date" name="to" value="<?= htmlspecialchars((string) ($filters['to'] ?? '')) ?>">
            </label>
            <label>Cliente
                <select name="client_id">
                    <option value="0">Todos</option>
                    <?php foreach (($filterOptions['clients'] ?? []) as $client): ?>
                        <option value="<?= (int) $client['id'] ?>" <?= ((int) ($filters['client_id'] ?? 0) === (int) $client['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($client['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>PM
                <select name="pm_id">
                    <option value="0">Todos</option>
                    <?php foreach (($filterOptions['pms'] ?? []) as $pm): ?>
                        <option value="<?= (int) $pm['id'] ?>" <?= ((int) ($filters['pm_id'] ?? 0) === (int) $pm['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($pm['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Estado
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach (($filterOptions['statuses'] ?? []) as $status): ?>
                        <?php $code = (string) ($status['code'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= (($filters['status'] ?? '') === $code) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($code) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Área
                <select name="area">
                    <option value="">Todas</option>
                    <?php foreach (($filterOptions['areas'] ?? []) as $area): ?>
                        <?php $code = (string) ($area['area_code'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= (($filters['area'] ?? '') === $code) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($code) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Rol
                <select name="role">
                    <option value="">Todos</option>
                    <?php foreach (($filterOptions['roles'] ?? []) as $role): ?>
                        <?php $roleName = (string) ($role['role'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($roleName) ?>" <?= (($filters['role'] ?? '') === $roleName) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($roleName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="actions">
                <button class="btn primary" type="submit">Aplicar</button>
                <a class="btn secondary" href="/pmo/decision-center">Limpiar</a>
            </div>
        </form>
    </article>

    <article class="dc-card">
        <h3>A. Estado del portafolio</h3>
        <p class="dc-subtitle">Lectura ejecutiva de KPIs principales.</p>
        <div class="kpi-grid">
            <div class="kpi"><span class="label">Score general</span><span class="value"><?= (int) ($summary['portfolio_score'] ?? 0) ?></span><span class="meta">Delta: <?= number_format((float) ($summary['delta_vs_previous'] ?? 0), 1, ',', '.') ?> pts</span></div>
            <div class="kpi"><span class="label">Proyectos activos</span><span class="value"><?= (int) ($summary['active_projects'] ?? 0) ?></span><span class="meta">Portafolio vigente</span></div>
            <div class="kpi"><span class="label">Proyectos en riesgo</span><span class="value"><?= (int) ($summary['at_risk_projects'] ?? 0) ?></span><span class="meta">Umbral score: <?= (int) ($summary['risk_threshold'] ?? 70) ?></span></div>
            <div class="kpi"><span class="label">Bloqueos activos</span><span class="value"><?= (int) ($summary['active_blockers'] ?? 0) ?></span><span class="meta">Críticos + altos priorizados</span></div>
            <div class="kpi"><span class="label">Facturación pendiente</span><span class="value" style="font-size:24px;"><?= htmlspecialchars($currencyFormatter((float) ($summary['billing_pending'] ?? 0), (string) ($summary['billing_currency'] ?? 'USD'))) ?></span><span class="meta">Monto por facturar</span></div>
            <div class="kpi"><span class="label">Utilización promedio equipo</span><span class="value"><?= number_format((float) ($summary['avg_team_utilization'] ?? 0), 1, ',', '.') ?>%</span><span class="meta">Capacidad del periodo</span></div>
        </div>
    </article>

    <article class="dc-card">
        <h3>B. Alertas automáticas</h3>
        <p class="dc-subtitle">Click en un chip para filtrar las tablas analíticas.</p>
        <div class="alerts-strip" id="dc-alerts-strip">
            <?php foreach ($alerts as $alert): ?>
                <?php $key = (string) ($alert['key'] ?? ''); ?>
                <button
                    type="button"
                    class="alert-chip <?= $activeAlert === $key ? 'active' : '' ?>"
                    data-alert="<?= htmlspecialchars($key) ?>">
                    <span><?= htmlspecialchars((string) ($alert['label'] ?? 'Alerta')) ?></span>
                    <span class="count"><?= (int) ($alert['count'] ?? 0) ?></span>
                </button>
            <?php endforeach; ?>
            <button type="button" class="alert-chip <?= $activeAlert === '' ? 'active' : '' ?>" data-alert="">Limpiar filtro</button>
        </div>
    </article>

    <article class="dc-card">
        <h3>C. Decisiones recomendadas</h3>
        <p class="dc-subtitle">Top de acciones priorizadas para PMO.</p>
        <div class="recommendation-list">
            <?php foreach ($recommendations as $recommendation): ?>
                <article class="recommendation-item">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <h4><?= htmlspecialchars((string) ($recommendation['title'] ?? 'Decisión')) ?></h4>
                        <span class="impact-pill <?= htmlspecialchars($impactClass((string) ($recommendation['impact'] ?? 'Bajo'))) ?>">
                            Impacto <?= htmlspecialchars((string) ($recommendation['impact'] ?? 'Bajo')) ?>
                        </span>
                    </div>
                    <p><?= htmlspecialchars((string) ($recommendation['reason'] ?? 'Sin detalle.')) ?></p>
                    <div>
                        <a class="btn secondary" href="<?= htmlspecialchars((string) ($recommendation['action_url'] ?? '/projects')) ?>">
                            <?= htmlspecialchars((string) ($recommendation['action_label'] ?? 'Ver proyecto')) ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($recommendations === []): ?>
                <article class="recommendation-item"><p>No hay recomendaciones para el corte actual.</p></article>
            <?php endif; ?>
        </div>
    </article>

    <section class="analytics-grid">
        <article class="dc-card">
            <h3>D1. Ranking de proyectos</h3>
            <p class="dc-subtitle">Incluye seguimiento, bloqueos, riesgos y facturación pendiente.</p>
            <div class="table-wrap">
                <table id="dc-project-table">
                    <thead>
                    <tr>
                        <th>Proyecto</th>
                        <th>Cliente / PM</th>
                        <th>Estado</th>
                        <th>Score</th>
                        <th>Riesgo</th>
                        <th>Bloqueos</th>
                        <th>Última nota</th>
                        <th>Pendiente</th>
                        <th>Acción</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projectRanking as $row): ?>
                        <?php
                        $flags = is_array($row['alert_flags'] ?? null) ? $row['alert_flags'] : [];
                        $noteTooltip = trim((string) ($row['last_note_preview'] ?? ''));
                        $stoppersTop = is_array($row['stoppers_top'] ?? null) ? $row['stoppers_top'] : [];
                        $stoppersTooltip = $stoppersTop === [] ? 'Sin bloqueos destacados' : implode(' | ', $stoppersTop);
                        ?>
                        <tr
                            data-stale="<?= !empty($flags['stale_updates']) ? '1' : '0' ?>"
                            data-critical-blockers="<?= !empty($flags['critical_blockers']) ? '1' : '0' ?>"
                            data-critical-risks="<?= !empty($flags['critical_risks']) ? '1' : '0' ?>"
                            data-billing-pending="<?= !empty($flags['billing_pending']) ? '1' : '0' ?>">
                            <td title="<?= htmlspecialchars((string) ($row['name'] ?? '')) ?>">
                                <strong><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></strong>
                                <div class="dc-subtitle"><?= htmlspecialchars((string) ($row['area_code'] ?? '')) ?></div>
                            </td>
                            <td>
                                <?= htmlspecialchars((string) ($row['client_name'] ?? '-')) ?><br>
                                <span class="dc-subtitle"><?= htmlspecialchars((string) ($row['pm_name'] ?? '-')) ?></span>
                            </td>
                            <td><?= htmlspecialchars((string) ($row['status'] ?? '-')) ?></td>
                            <td><strong><?= number_format((float) ($row['portfolio_score'] ?? 0), 1, ',', '.') ?></strong></td>
                            <td>
                                <span class="risk-pill <?= !empty($row['is_project_at_risk']) ? 'is-risk' : 'is-stable' ?>">
                                    <?= !empty($row['is_project_at_risk']) ? 'En riesgo' : 'Estable' ?>
                                </span>
                            </td>
                            <td title="<?= htmlspecialchars($stoppersTooltip) ?>">
                                <?= (int) ($row['open_stoppers'] ?? 0) ?> · <?= htmlspecialchars((string) ($row['max_stopper_severity'] ?? 'N/A')) ?>
                            </td>
                            <td title="<?= htmlspecialchars($noteTooltip !== '' ? $noteTooltip : 'Sin nota') ?>">
                                <?= htmlspecialchars((string) ($row['last_note_at'] ?? '-')) ?><br>
                                <span class="dc-subtitle"><?= htmlspecialchars($truncateText($noteTooltip !== '' ? $noteTooltip : 'Sin nota', 70)) ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($currencyFormatter((float) ($row['pending_to_invoice'] ?? 0), (string) ($row['currency_code'] ?? 'USD'))) ?>
                            </td>
                            <td><a class="btn sm secondary" href="/projects/<?= (int) ($row['id'] ?? 0) ?>">Ver proyecto</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($projectRanking === []): ?>
                        <tr><td colspan="9">No hay proyectos para el filtro actual.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="dc-card">
            <h3>D2. Capacidad del equipo</h3>
            <p class="dc-subtitle">Utilización, horas disponibles y sobrecarga por talento.</p>
            <div class="table-wrap">
                <table id="dc-team-table">
                    <thead>
                    <tr>
                        <th>Talento</th>
                        <th>Rol</th>
                        <th>Asignadas</th>
                        <th>Capacidad</th>
                        <th>Utilización</th>
                        <th>Disponibles</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($teamRows as $row): ?>
                        <?php $util = (float) ($row['utilization'] ?? 0); ?>
                        <tr data-talent-overload="<?= $util > 90 ? '1' : '0' ?>">
                            <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['role'] ?? '-')) ?></td>
                            <td><?= number_format((float) ($row['assigned_hours'] ?? 0), 1, ',', '.') ?>h</td>
                            <td><?= number_format((float) ($row['capacity_hours'] ?? 0), 1, ',', '.') ?>h</td>
                            <td class="<?= htmlspecialchars($utilizationClass($util)) ?>"><strong><?= number_format($util, 1, ',', '.') ?>%</strong></td>
                            <td><?= number_format((float) ($row['available_hours'] ?? 0), 1, ',', '.') ?>h</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($teamRows === []): ?>
                        <tr><td colspan="6">No hay datos de capacidad para el filtro actual.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="dc-subtitle" style="margin-top:8px;">Talentos sin reporte en periodo: <strong><?= (int) ($teamCapacity['talents_without_report'] ?? 0) ?></strong></p>

            <hr style="border:none;border-top:1px solid var(--border);margin:10px 0;">
            <h3 style="font-size:16px;">Simular carga (MVP)</h3>
            <p class="dc-subtitle">Cálculo en memoria, sin escribir en BD.</p>
            <form class="sim-form" id="dc-sim-form">
                <label>Área
                    <input name="area" value="<?= htmlspecialchars((string) ($filters['area'] ?? '')) ?>" placeholder="Opcional">
                </label>
                <label>Rol
                    <input name="role" value="<?= htmlspecialchars((string) ($filters['role'] ?? '')) ?>" placeholder="Opcional">
                </label>
                <label>Horas nuevas
                    <input type="number" min="0" step="1" name="estimated_hours" value="120" required>
                </label>
                <label>Desde
                    <input type="date" name="from" value="<?= htmlspecialchars((string) ($filters['from'] ?? '')) ?>">
                </label>
                <label>Hasta
                    <input type="date" name="to" value="<?= htmlspecialchars((string) ($filters['to'] ?? '')) ?>">
                </label>
                <label>Recursos req.
                    <input type="number" min="0" step="1" name="required_resources" value="0">
                </label>
                <button class="btn primary" type="submit">Ejecutar simulación</button>
            </form>
            <div class="sim-result" id="dc-sim-result">
                <div class="dc-subtitle">Completa los datos y ejecuta la simulación.</div>
            </div>
        </article>
    </section>

    <script>
        (() => {
            const alertInput = document.getElementById('dc-alert-input');
            const form = document.getElementById('dc-filter-form');
            const chips = Array.from(document.querySelectorAll('.alert-chip[data-alert]'));
            const projectRows = Array.from(document.querySelectorAll('#dc-project-table tbody tr'));
            const teamRows = Array.from(document.querySelectorAll('#dc-team-table tbody tr'));

            function matchProjectRow(row, alertKey) {
                if (!alertKey) return true;
                if (alertKey === 'stale_updates') return row.dataset.stale === '1';
                if (alertKey === 'critical_blockers') return row.dataset.criticalBlockers === '1';
                if (alertKey === 'critical_risks') return row.dataset.criticalRisks === '1';
                if (alertKey === 'billing_pending') return row.dataset.billingPending === '1';
                return true;
            }

            function matchTeamRow(row, alertKey) {
                if (!alertKey) return true;
                if (alertKey === 'talent_overload') return row.dataset.talentOverload === '1';
                return true;
            }

            function applyClientFilter(alertKey) {
                projectRows.forEach((row) => {
                    row.style.display = matchProjectRow(row, alertKey) ? '' : 'none';
                });
                teamRows.forEach((row) => {
                    row.style.display = matchTeamRow(row, alertKey) ? '' : 'none';
                });
                chips.forEach((chip) => {
                    chip.classList.toggle('active', chip.dataset.alert === alertKey);
                });
            }

            applyClientFilter(alertInput?.value ?? '');

            chips.forEach((chip) => {
                chip.addEventListener('click', () => {
                    const nextAlert = chip.dataset.alert || '';
                    if (alertInput) {
                        alertInput.value = nextAlert;
                    }
                    applyClientFilter(nextAlert);
                    if (form) form.requestSubmit();
                });
            });
        })();

        (() => {
            const simForm = document.getElementById('dc-sim-form');
            const simResult = document.getElementById('dc-sim-result');
            if (!simForm || !simResult) return;

            simForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const payload = Object.fromEntries(new FormData(simForm).entries());
                simResult.innerHTML = '<div class="dc-subtitle">Calculando simulación...</div>';
                try {
                    const response = await fetch('/api/pmo/decision-center/simulate', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                        body: JSON.stringify(payload),
                    });
                    const json = await response.json();
                    if (!response.ok) {
                        throw new Error(json.message || 'No se pudo ejecutar la simulación.');
                    }
                    const data = json.data || {};
                    const riskTalents = Array.isArray(data.talents_at_risk) ? data.talents_at_risk : [];
                    const affectedProjects = Array.isArray(data.affected_projects) ? data.affected_projects : [];

                    simResult.innerHTML = `
                        <div><strong>Utilización estimada:</strong> ${Number(data.estimated_team_utilization || 0).toFixed(1)}%</div>
                        <div style="margin-top:6px;"><strong>Talentos en riesgo:</strong> ${riskTalents.length}</div>
                        <ul>${riskTalents.slice(0, 8).map((t) => `<li>${t.name} (${Number(t.utilization || 0).toFixed(1)}%)</li>`).join('') || '<li>Sin talentos en riesgo</li>'}</ul>
                        <div style="margin-top:6px;"><strong>Proyectos potencialmente afectados:</strong></div>
                        <ul>${affectedProjects.slice(0, 8).map((p) => `<li>${p}</li>`).join('') || '<li>Sin proyectos afectados</li>'}</ul>
                    `;
                } catch (error) {
                    simResult.innerHTML = `<div class="tone-danger">No se pudo simular capacidad: ${String(error.message || error)}</div>`;
                }
            });
        })();
    </script>
</section>
