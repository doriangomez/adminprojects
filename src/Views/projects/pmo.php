<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$projectId = (int) ($project['id'] ?? 0);
$pmoBoard = is_array($pmoBoard ?? null) ? $pmoBoard : [];
$pmoHistory = is_array($pmoHistory ?? null) ? $pmoHistory : [];
$scheduleActivities = is_array($scheduleActivities ?? null) ? $scheduleActivities : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$canEditPmo = !empty($canEditPmo);
$canViewPmo = !empty($canViewPmo) || $canEditPmo;
$boardActivities = is_array($pmoBoard['activities'] ?? null) ? $pmoBoard['activities'] : $scheduleActivities;
$counts = is_array($pmoBoard['counts'] ?? null) ? $pmoBoard['counts'] : [];
$curve = is_array($pmoBoard['curve'] ?? null) ? $pmoBoard['curve'] : [];
$section = strtolower(trim((string) ($_GET['section'] ?? 'resumen')));
if (!in_array($section, ['resumen', 'cronograma', 'importacion', 'actividades', 'controles', 'historial'], true)) {
    $section = 'resumen';
}
$filterStatus = strtolower(trim((string) ($_GET['f_status'] ?? '')));
$filterClass = strtolower(trim((string) ($_GET['f_class'] ?? '')));
$filterResponsible = trim((string) ($_GET['f_responsible'] ?? ''));
$filterCritical = (string) ($_GET['f_critical'] ?? '');
$filterMilestone = (string) ($_GET['f_milestone'] ?? '');

$filtered = array_values(array_filter($boardActivities, static function (array $row) use ($filterStatus, $filterClass, $filterResponsible, $filterCritical, $filterMilestone): bool {
    if ($filterStatus !== '' && strtolower((string) ($row['status'] ?? '')) !== $filterStatus) {
        return false;
    }
    if ($filterClass !== '' && strtolower((string) ($row['schedule_class'] ?? '')) !== $filterClass) {
        return false;
    }
    if ($filterResponsible !== '') {
        $resp = (string) ($row['responsible_name'] ?? $row['responsible_user_name'] ?? '');
        if (stripos($resp, $filterResponsible) === false) {
            return false;
        }
    }
    if ($filterCritical === '1' && empty($row['is_critical'])) {
        return false;
    }
    if ($filterMilestone === '1' && (($row['item_type'] ?? '') !== 'milestone')) {
        return false;
    }
    return true;
}));

$statusLabels = [
    'todo' => 'Por hacer',
    'in_progress' => 'En progreso',
    'review' => 'Revisión',
    'blocked' => 'Bloqueada',
    'done' => 'Completada',
    'cancelled' => 'Cancelada',
];
$classLabels = [
    'adelantada' => 'Adelantada',
    'a_tiempo' => 'A tiempo',
    'atrasada' => 'Atrasada',
    'done' => 'Completada',
    'cancelled' => 'Cancelada',
    'no_aplica' => 'N/A',
];
$healthLabels = ['verde' => 'Saludable', 'amarillo' => 'En observación', 'rojo' => 'En riesgo'];
$talentNames = array_values(array_unique(array_filter(array_map(
    static fn (array $a): string => trim((string) ($a['talent_name'] ?? $a['name'] ?? '')),
    $assignments
))));
$hasSchedule = !empty($boardActivities);
$flash = null;
if (!empty($_GET['saved'])) {
    $flash = 'Cronograma guardado. Indicadores actualizados.';
}
if (!empty($_GET['imported'])) {
    $flash = 'Importación aplicada. Indicadores actualizados.';
}
if (!empty($_GET['updated'])) {
    $flash = 'Actividad actualizada. Indicadores recalculados.';
}
?>
<section class="pmo-shell" data-schedule-root data-project-id="<?= $projectId ?>">
    <header class="pmo-header">
        <div>
            <p class="eyebrow">Gestión PMO</p>
            <h3><?= htmlspecialchars((string) ($pmoBoard['settings']['board_title'] ?? 'Tablero PMO')) ?></h3>
            <p class="section-muted">Fuente única: cronograma del proyecto. Organizaciones/tecnologías no son requeridas.</p>
        </div>
        <nav class="pmo-subnav">
            <?php foreach (['resumen' => 'Resumen', 'cronograma' => 'Cronograma', 'importacion' => 'Importación', 'actividades' => 'Actividades', 'controles' => 'Controles', 'historial' => 'Historial'] as $key => $label): ?>
                <a class="pmo-subnav__link <?= $section === $key ? 'active' : '' ?>" href="<?= $basePath ?>/projects/<?= $projectId ?>?view=pmo&section=<?= $key ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <?php if ($flash): ?>
        <div class="alert success"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if (!$canViewPmo): ?>
        <article class="schedule-empty"><p>No tienes permiso para ver el tablero PMO.</p></article>
    <?php elseif (!$hasSchedule && !in_array($section, ['cronograma', 'importacion'], true)): ?>
        <article class="schedule-empty">
            <h3>Este proyecto aún no tiene cronograma</h3>
            <p>Crea actividades manualmente o importa un Excel/CSV para generar los controles PMO.</p>
            <div class="schedule-actions">
                <?php if ($canEditPmo): ?>
                    <a class="action-btn primary" href="<?= $basePath ?>/projects/<?= $projectId ?>?view=pmo&section=cronograma">Crear cronograma</a>
                    <a class="action-btn" href="<?= $basePath ?>/projects/<?= $projectId ?>?view=pmo&section=importacion">Importar Excel/CSV</a>
                <?php endif; ?>
            </div>
        </article>
    <?php else: ?>

        <?php if ($section === 'resumen'): ?>
            <section class="pmo-kpi-grid">
                <article class="pmo-kpi">
                    <span>Avance real</span>
                    <strong><?= number_format((float) ($pmoBoard['avance_real'] ?? 0), 1) ?>%</strong>
                    <small>Promedio simple (no ponderado)</small>
                </article>
                <article class="pmo-kpi">
                    <span>Avance planificado</span>
                    <strong><?= number_format((float) ($pmoBoard['avance_planificado'] ?? 0), 1) ?>%</strong>
                    <small>Lineal por días hábiles</small>
                </article>
                <article class="pmo-kpi">
                    <span>Desviación</span>
                    <strong><?= number_format((float) ($pmoBoard['desviacion'] ?? 0), 1) ?>%</strong>
                    <small>Umbral ±<?= number_format((float) ($pmoBoard['threshold_pct'] ?? 5), 1) ?>%</small>
                </article>
                <article class="pmo-kpi pmo-kpi--<?= htmlspecialchars((string) ($pmoBoard['health'] ?? 'verde')) ?>">
                    <span>Estado general</span>
                    <strong><?= htmlspecialchars($healthLabels[$pmoBoard['health'] ?? 'verde'] ?? 'N/A') ?></strong>
                    <small>Al <?= htmlspecialchars((string) ($pmoBoard['as_of'] ?? '')) ?></small>
                </article>
            </section>
            <section class="pmo-kpi-grid pmo-kpi-grid--secondary">
                <article class="pmo-kpi"><span>Atrasadas</span><strong><?= (int) ($counts['atrasadas'] ?? 0) ?></strong></article>
                <article class="pmo-kpi"><span>A tiempo</span><strong><?= (int) ($counts['a_tiempo'] ?? 0) ?></strong></article>
                <article class="pmo-kpi"><span>Adelantadas</span><strong><?= (int) ($counts['adelantadas'] ?? 0) ?></strong></article>
                <article class="pmo-kpi"><span>Hitos</span><strong><?= (int) ($counts['milestones_done'] ?? 0) ?>/<?= (int) ($counts['milestones'] ?? 0) ?></strong></article>
                <article class="pmo-kpi"><span>Críticas (marcadas)</span><strong><?= (int) ($counts['critical'] ?? 0) ?></strong></article>
                <article class="pmo-kpi"><span>Stoppers abiertos</span><strong><?= (int) ($pmoBoard['stoppers_open'] ?? 0) ?></strong></article>
                <article class="pmo-kpi"><span>Riesgos activos</span><strong><?= (int) ($pmoBoard['risks_selected'] ?? 0) ?></strong></article>
            </section>
            <article class="card pmo-panel">
                <h4>Curva S</h4>
                <p class="section-muted"><?= htmlspecialchars((string) ($curve['note'] ?? '')) ?></p>
                <div class="pmo-curve">
                    <?php
                    $plannedPts = is_array($curve['planned'] ?? null) ? $curve['planned'] : [];
                    $maxVal = 100;
                    ?>
                    <?php if (empty($plannedPts)): ?>
                        <p class="section-muted">Sin fechas suficientes para trazar el plan acumulado.</p>
                    <?php else: ?>
                        <svg viewBox="0 0 420 160" role="img" aria-label="Curva S planificada">
                            <?php
                            $n = count($plannedPts);
                            $poly = [];
                            foreach ($plannedPts as $i => $pt) {
                                $x = 20 + ($n <= 1 ? 0 : ($i / ($n - 1)) * 380);
                                $y = 140 - (((float) ($pt['value'] ?? 0) / $maxVal) * 120);
                                $poly[] = number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
                            }
                            ?>
                            <polyline fill="none" stroke="currentColor" stroke-width="2" points="<?= htmlspecialchars(implode(' ', $poly)) ?>"></polyline>
                            <?php
                            $realY = 140 - (((float) ($curve['real_current'] ?? 0) / $maxVal) * 120);
                            ?>
                            <circle cx="400" cy="<?= number_format($realY, 1, '.', '') ?>" r="5"></circle>
                            <text x="20" y="18" font-size="11">Plan acumulado · Avance actual <?= number_format((float) ($curve['real_current'] ?? 0), 1) ?>%</text>
                        </svg>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>

        <?php if ($section === 'cronograma'): ?>
            <?php if (!$canEditPmo): ?>
                <p class="section-muted">Solo lectura: no puedes editar el cronograma.</p>
            <?php endif; ?>
            <article class="schedule-summary-grid">
                <div><span>Actividades</span><strong><?= (int) ($counts['active'] ?? count($boardActivities)) ?></strong></div>
                <div><span>Avance real</span><strong><?= number_format((float) ($pmoBoard['avance_real'] ?? 0), 1) ?>%</strong></div>
                <div><span>Avance plan</span><strong><?= number_format((float) ($pmoBoard['avance_planificado'] ?? 0), 1) ?>%</strong></div>
                <?php if ($canEditPmo): ?>
                    <div class="schedule-actions">
                        <button type="button" class="action-btn primary" data-schedule-action="create"><?= $hasSchedule ? 'Editar cronograma' : 'Crear cronograma' ?></button>
                    </div>
                <?php endif; ?>
            </article>
            <?php if ($hasSchedule): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th><th>Nombre</th><th>Tipo</th><th>Responsable</th><th>Inicio</th><th>Fin</th>
                                <th>Real %</th><th>Plan %</th><th>Estado</th><th>Clase</th><th>Crítica</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($boardActivities as $i => $activity): ?>
                                <tr>
                                    <td><?= (int) ($activity['sort_order'] ?? ($i + 1)) ?></td>
                                    <td><?= htmlspecialchars((string) ($activity['name'] ?? '')) ?></td>
                                    <td><?= (($activity['item_type'] ?? '') === 'milestone') ? 'Hito' : 'Actividad' ?></td>
                                    <td><?= htmlspecialchars((string) ($activity['responsible_name'] ?? $activity['responsible_user_name'] ?? '—')) ?></td>
                                    <td><?= htmlspecialchars((string) ($activity['start_date'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string) ($activity['end_date'] ?? '')) ?></td>
                                    <td><?= number_format((float) ($activity['progress_percent'] ?? 0), 0) ?>%</td>
                                    <td><?= number_format((float) ($activity['planned_percent'] ?? 0), 0) ?>%</td>
                                    <td><?= htmlspecialchars($statusLabels[$activity['status'] ?? 'todo'] ?? (string) ($activity['status'] ?? '')) ?></td>
                                    <td><span class="badge class-<?= htmlspecialchars((string) ($activity['schedule_class'] ?? '')) ?>"><?= htmlspecialchars($classLabels[$activity['schedule_class'] ?? ''] ?? '') ?></span></td>
                                    <td><?= !empty($activity['is_critical']) ? 'Sí' : 'No' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <article class="schedule-empty"><p>Aún no hay actividades. Usa el editor para crearlas.</p></article>
            <?php endif; ?>

            <?php if ($canEditPmo): ?>
                <article class="schedule-editor-panel" id="schedule-editor-panel" <?= $hasSchedule ? 'hidden' : '' ?>>
                    <form method="POST" action="<?= $basePath ?>/projects/<?= $projectId ?>/schedule/save" class="schedule-form">
                        <h3>Editor de cronograma</h3>
                        <p class="section-muted">Guarda para recalcular indicadores inmediatamente. `done` fuerza 100%.</p>
                        <datalist id="schedule-talents">
                            <?php foreach ($talentNames as $talentName): ?>
                                <option value="<?= htmlspecialchars((string) $talentName) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <div class="table-wrapper schedule-editor-table-wrap">
                            <table class="schedule-editor-table">
                                <thead>
                                    <tr>
                                        <th>N°</th><th>Nombre</th><th>Tipo</th><th>Inicio</th><th>Fin</th><th>Días</th>
                                        <th>Responsable</th><th>Avance %</th><th>Estado</th><th>Código</th><th>Crítica</th><th></th>
                                    </tr>
                                </thead>
                                <tbody id="schedule-editor-rows" data-existing='<?= htmlspecialchars(json_encode($boardActivities, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'></tbody>
                            </table>
                        </div>
                        <input type="hidden" name="activities_json" id="schedule-activities-json" />
                        <div class="schedule-editor-footer">
                            <button type="button" class="action-btn" data-schedule-add-row>＋ Agregar actividad</button>
                            <button type="submit" class="action-btn primary">Guardar cronograma</button>
                        </div>
                    </form>
                </article>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($section === 'importacion'): ?>
            <article class="card pmo-panel">
                <h4>Importar cronograma</h4>
                <?php if (!$canEditPmo): ?>
                    <p class="section-muted">No tienes permiso de edición.</p>
                <?php else: ?>
                    <p>Reutiliza el importador existente. Columnas soportadas: Nombre, Tipo, Fecha inicio, Fecha fin, Responsable, Porcentaje de avance, Estado, Código, Fase, Frente, Crítico.</p>
                    <a class="action-btn" download="plantilla_pmo_cronograma.csv" href="data:text/csv;charset=utf-8,Nombre%20de%20la%20actividad,Tipo,Fecha%20inicio,Fecha%20fin,Responsable,Porcentaje%20de%20avance,Estado,Codigo,Fase,Frente,Critico%0A">Descargar plantilla</a>
                    <form class="schedule-form" id="schedule-import-form" enctype="multipart/form-data">
                        <input type="file" name="excel_file" accept=".xlsx,.csv" required />
                        <div id="schedule-import-preview"></div>
                        <div class="schedule-actions">
                            <button type="submit" class="action-btn primary">Cargar y previsualizar</button>
                        </div>
                    </form>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <?php if ($section === 'actividades' || $section === 'controles'): ?>
            <form method="GET" class="pmo-filters">
                <input type="hidden" name="view" value="pmo" />
                <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>" />
                <select name="f_status">
                    <option value="">Estado</option>
                    <?php foreach ($statusLabels as $code => $label): ?>
                        <option value="<?= $code ?>" <?= $filterStatus === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="f_class">
                    <option value="">Clase</option>
                    <?php foreach (['atrasada','a_tiempo','adelantada','done','cancelled'] as $code): ?>
                        <option value="<?= $code ?>" <?= $filterClass === $code ? 'selected' : '' ?>><?= htmlspecialchars($classLabels[$code]) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="f_responsible" placeholder="Responsable" value="<?= htmlspecialchars($filterResponsible) ?>" />
                <label><input type="checkbox" name="f_critical" value="1" <?= $filterCritical === '1' ? 'checked' : '' ?> /> Críticas</label>
                <label><input type="checkbox" name="f_milestone" value="1" <?= $filterMilestone === '1' ? 'checked' : '' ?> /> Hitos</label>
                <button class="action-btn" type="submit">Filtrar</button>
            </form>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Actividad</th><th>Responsable</th><th>Real</th><th>Plan</th><th>Clase</th><th>Estado</th>
                            <?php if ($canEditPmo && $section === 'actividades'): ?><th>Actualizar</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filtered)): ?>
                            <tr><td colspan="7">Sin actividades para estos filtros.</td></tr>
                        <?php else: ?>
                            <?php foreach ($filtered as $activity): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars((string) ($activity['name'] ?? '')) ?></strong>
                                        <?php if (($activity['item_type'] ?? '') === 'milestone'): ?><span class="badge">Hito</span><?php endif; ?>
                                        <?php if (!empty($activity['is_critical'])): ?><span class="badge">Crítica</span><?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($activity['responsible_name'] ?? $activity['responsible_user_name'] ?? '—')) ?></td>
                                    <td><?= number_format((float) ($activity['progress_percent'] ?? 0), 0) ?>%</td>
                                    <td><?= number_format((float) ($activity['planned_percent'] ?? 0), 0) ?>%</td>
                                    <td><?= htmlspecialchars($classLabels[$activity['schedule_class'] ?? ''] ?? '') ?></td>
                                    <td><?= htmlspecialchars($statusLabels[$activity['status'] ?? 'todo'] ?? '') ?></td>
                                    <?php if ($canEditPmo && $section === 'actividades'): ?>
                                        <td>
                                            <form method="POST" action="<?= $basePath ?>/projects/<?= $projectId ?>/schedule/activities/<?= (int) ($activity['id'] ?? 0) ?>/update" class="inline-update">
                                                <input type="number" min="0" max="100" name="progress_percent" value="<?= number_format((float) ($activity['progress_percent'] ?? 0), 0, '.', '') ?>" />
                                                <select name="status">
                                                    <?php foreach ($statusLabels as $code => $label): ?>
                                                        <option value="<?= $code ?>" <?= (($activity['status'] ?? '') === $code) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn secondary" type="submit">Guardar</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($section === 'controles'): ?>
                <article class="card pmo-panel">
                    <h4>Ruta crítica (marcadas)</h4>
                    <p class="section-muted">Sin cálculo automático por dependencias en esta etapa. Se muestran flags `is_critical_auto` / `is_critical_manual`.</p>
                    <?php $critical = is_array($pmoBoard['critical_activities'] ?? null) ? $pmoBoard['critical_activities'] : []; ?>
                    <?php if (empty($critical)): ?>
                        <p class="section-muted">No hay actividades marcadas como críticas.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($critical as $item): ?>
                                <li><?= htmlspecialchars((string) ($item['name'] ?? '')) ?> — <?= number_format((float) ($item['progress_percent'] ?? 0), 0) ?>%</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($section === 'historial'): ?>
            <article class="card pmo-panel">
                <h4>Historial (audit_log)</h4>
                <?php if (empty($pmoHistory)): ?>
                    <p class="section-muted">Aún no hay cambios registrados del cronograma PMO.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th></tr></thead>
                            <tbody>
                                <?php foreach ($pmoHistory as $entry): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($entry['created_at'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($entry['user_name'] ?? 'Sistema')) ?></td>
                                        <td><?= htmlspecialchars((string) ($entry['action'] ?? '')) ?></td>
                                        <td><code><?= htmlspecialchars(json_encode($entry['payload'] ?? [], JSON_UNESCAPED_UNICODE)) ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        <?php endif; ?>
    <?php endif; ?>
</section>

<style>
.pmo-shell { display:flex; flex-direction:column; gap:16px; }
.pmo-header { display:flex; flex-direction:column; gap:12px; }
.pmo-subnav { display:flex; flex-wrap:wrap; gap:8px; }
.pmo-subnav__link { padding:8px 12px; border-radius:999px; border:1px solid var(--border); text-decoration:none; color:var(--text-primary); font-weight:700; font-size:13px; background:var(--surface); }
.pmo-subnav__link.active { background:var(--primary); color:var(--text-primary); border-color:var(--primary); }
.pmo-kpi-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
.pmo-kpi-grid--secondary { grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); }
.pmo-kpi { border:1px solid var(--border); border-radius:14px; padding:14px; background:color-mix(in srgb, var(--surface) 90%, var(--background)); display:flex; flex-direction:column; gap:4px; }
.pmo-kpi strong { font-size:28px; }
.pmo-kpi small,.pmo-kpi span { color:var(--text-secondary); }
.pmo-kpi--rojo { border-color:color-mix(in srgb, var(--danger) 40%, var(--border)); }
.pmo-kpi--amarillo { border-color:color-mix(in srgb, var(--warning) 40%, var(--border)); }
.pmo-kpi--verde { border-color:color-mix(in srgb, var(--success) 40%, var(--border)); }
.pmo-panel { padding:16px; }
.pmo-filters { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.pmo-filters input,.pmo-filters select,.inline-update input,.inline-update select,.schedule-editor-table input,.schedule-editor-table select { border:1px solid var(--border); border-radius:8px; padding:8px 10px; background:var(--surface); color:var(--text-primary); }
.inline-update { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
.schedule-empty { border:1px dashed var(--border); border-radius:14px; padding:24px; text-align:center; background:var(--surface); }
.schedule-summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; }
.schedule-summary-grid > div { border:1px solid var(--border); border-radius:12px; padding:12px; background:var(--surface); }
.schedule-editor-panel { border:1px solid var(--border); border-radius:12px; padding:12px; background:color-mix(in srgb, var(--surface) 90%, var(--background)); }
.schedule-editor-table-wrap,.table-wrapper { overflow:auto; }
.data-table { width:100%; border-collapse:collapse; }
.data-table th,.data-table td { padding:10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
.alert.success { padding:10px 12px; border-radius:10px; background:color-mix(in srgb, var(--success) 16%, var(--surface)); border:1px solid color-mix(in srgb, var(--success) 35%, var(--border)); }
.badge { display:inline-flex; padding:2px 8px; border-radius:999px; background:color-mix(in srgb, var(--primary) 14%, var(--surface)); font-size:12px; font-weight:700; }
.pmo-curve { color:var(--primary); }
@media (max-width:900px){ .pmo-kpi-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:640px){ .pmo-kpi-grid{ grid-template-columns:1fr; } .inline-update{ flex-direction:column; align-items:stretch; } }
</style>

<script>
(() => {
    const scheduleRoot = document.querySelector('[data-schedule-root]');
    if (!scheduleRoot) return;
    const projectId = scheduleRoot.dataset.projectId;
    const editorPanel = document.getElementById('schedule-editor-panel');
    const editorRows = document.getElementById('schedule-editor-rows');
    const hiddenActivities = document.getElementById('schedule-activities-json');

    const normalizeDate = (value) => String(value || '').slice(0, 10);
    const diffDays = (startValue, endValue) => {
        const start = new Date(normalizeDate(startValue));
        const end = new Date(normalizeDate(endValue));
        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return 0;
        return Math.floor((end - start) / 86400000) + 1;
    };

    const renderRow = (row = {}, index = 0) => {
        const wrapper = document.createElement('tr');
        wrapper.className = 'schedule-row';
        wrapper.innerHTML = `
            <td class="schedule-num">${index + 1}</td>
            <td><input placeholder="Nombre" data-field="name" value="${row.name || ''}" /></td>
            <td><select data-field="item_type"><option value="activity">Actividad</option><option value="milestone">Hito</option></select></td>
            <td><input type="date" data-field="start_date" value="${normalizeDate(row.start_date)}" /></td>
            <td><input type="date" data-field="end_date" value="${normalizeDate(row.end_date)}" /></td>
            <td><input type="number" data-field="duration_days" value="${Number(row.duration_days || diffDays(row.start_date, row.end_date))}" readonly /></td>
            <td><input list="schedule-talents" placeholder="Responsable" data-field="responsible_name" value="${row.responsible_name || ''}" /></td>
            <td><input type="number" min="0" max="100" data-field="progress_percent" value="${Number(row.progress_percent || 0)}" /></td>
            <td><select data-field="status">
                <option value="todo">Por hacer</option>
                <option value="in_progress">En progreso</option>
                <option value="review">Revisión</option>
                <option value="blocked">Bloqueada</option>
                <option value="done">Completada</option>
                <option value="cancelled">Cancelada</option>
            </select></td>
            <td><input data-field="code" value="${row.code || ''}" placeholder="Código" /></td>
            <td><input type="checkbox" data-field="is_critical_manual" ${Number(row.is_critical_manual || row.is_critical || 0) === 1 ? 'checked' : ''} /></td>
            <td><button type="button" class="action-btn danger" data-remove-row>Eliminar</button></td>
        `;
        wrapper.querySelector('[data-field="item_type"]').value = row.item_type || 'activity';
        wrapper.querySelector('[data-field="status"]').value = row.status || 'todo';
        wrapper.querySelector('[data-remove-row]').addEventListener('click', () => wrapper.remove());
        const startInput = wrapper.querySelector('[data-field="start_date"]');
        const endInput = wrapper.querySelector('[data-field="end_date"]');
        const durationInput = wrapper.querySelector('[data-field="duration_days"]');
        const updateDuration = () => { durationInput.value = String(diffDays(startInput.value, endInput.value)); };
        startInput.addEventListener('change', updateDuration);
        endInput.addEventListener('change', updateDuration);
        return wrapper;
    };

    const openEditor = () => {
        if (!editorPanel || !editorRows) return;
        editorRows.innerHTML = '';
        const existing = JSON.parse(editorRows.dataset.existing || '[]');
        (existing.length ? existing : [{}]).forEach((row, index) => editorRows.appendChild(renderRow(row, index)));
        editorPanel.hidden = false;
    };

    scheduleRoot.querySelectorAll('[data-schedule-action="create"]').forEach((btn) => btn.addEventListener('click', openEditor));
    document.querySelector('[data-schedule-add-row]')?.addEventListener('click', () => {
        const nextIndex = editorRows.querySelectorAll('.schedule-row').length;
        editorRows.appendChild(renderRow({}, nextIndex));
    });
    editorPanel?.querySelector('form')?.addEventListener('submit', () => {
        const rows = Array.from(editorRows.querySelectorAll('.schedule-row')).map((row, index) => ({
            sort_order: index + 1,
            name: row.querySelector('[data-field="name"]').value,
            item_type: row.querySelector('[data-field="item_type"]').value,
            start_date: row.querySelector('[data-field="start_date"]').value,
            end_date: row.querySelector('[data-field="end_date"]').value,
            progress_percent: Number(row.querySelector('[data-field="progress_percent"]').value || 0),
            responsible_name: row.querySelector('[data-field="responsible_name"]').value,
            duration_days: Number(row.querySelector('[data-field="duration_days"]').value || 0),
            status: row.querySelector('[data-field="status"]').value,
            code: row.querySelector('[data-field="code"]').value,
            is_critical_manual: row.querySelector('[data-field="is_critical_manual"]').checked ? 1 : 0,
        }));
        hiddenActivities.value = JSON.stringify(rows);
    });

    const importForm = document.getElementById('schedule-import-form');
    const preview = document.getElementById('schedule-import-preview');
    importForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(importForm);
        const response = await fetch(`/projects/${projectId}/schedule/import-preview`, { method: 'POST', body: form });
        const result = await response.json();
        if (result.status !== 'ok') {
            preview.innerHTML = `<p class="phase-warning">${result.message || 'No se pudo previsualizar.'}</p>`;
            return;
        }
        const errors = Array.isArray(result.errors) ? result.errors : [];
        preview.innerHTML = `<p>${errors.length ? 'Errores detectados' : 'Sin errores. Listo para importar.'}</p>` +
            errors.map((e) => `<p class="phase-warning">Fila ${e.row}: ${e.message}</p>`).join('') +
            `<div class="schedule-actions"><button type="button" class="action-btn primary" id="schedule-import-confirm">Confirmar importación</button></div>`;
        document.getElementById('schedule-import-confirm')?.addEventListener('click', async () => {
            const confirmResponse = await fetch(`/projects/${projectId}/schedule/import-confirm`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ mode: 'replace', rows_json: JSON.stringify(result.rows || []) }),
            });
            const confirmResult = await confirmResponse.json();
            window.location.href = confirmResult.redirect || `/projects/${projectId}?view=pmo&section=cronograma&imported=1`;
        });
    });
})();
</script>
