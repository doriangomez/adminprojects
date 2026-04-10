<?php
$basePath = $basePath ?? '';
$projects = is_array($projects ?? null) ? $projects : [];
$zoom = strtolower(trim((string) ($zoom ?? 'month')));
if (!in_array($zoom, ['week', 'month', 'quarter'], true)) {
    $zoom = 'month';
}
$activeFilter = ($activeFilter ?? 'active') === 'all' ? 'all' : 'active';
$selectedClient = (string) ($selectedClient ?? '');
$selectedPm = (string) ($selectedPm ?? '');
$clientOptions = is_array($clientOptions ?? null) ? $clientOptions : [];
$pmOptions = is_array($pmOptions ?? null) ? $pmOptions : [];
?>

<section class="gantt-global-shell" data-gantt-global-root data-zoom="<?= htmlspecialchars($zoom) ?>">
    <header class="gantt-global-header">
        <div>
            <h2>Gantt global</h2>
            <p class="section-muted">Vista consolidada del cronograma de proyectos, actividades e hitos.</p>
        </div>
        <button type="button" class="action-btn" data-export-png>Exportar PNG</button>
    </header>

    <form method="GET" action="<?= $basePath ?>/pmo/gantt-global" class="gantt-filters">
        <label>
            Proyectos
            <select name="active">
                <option value="active" <?= $activeFilter === 'active' ? 'selected' : '' ?>>Solo activos</option>
                <option value="all" <?= $activeFilter === 'all' ? 'selected' : '' ?>>Todos</option>
            </select>
        </label>
        <label>
            Cliente
            <select name="client">
                <option value="">Todos</option>
                <?php foreach ($clientOptions as $client): ?>
                    <option value="<?= htmlspecialchars((string) $client) ?>" <?= $selectedClient === (string) $client ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $client) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            PM
            <select name="pm">
                <option value="">Todos</option>
                <?php foreach ($pmOptions as $pm): ?>
                    <option value="<?= htmlspecialchars((string) $pm) ?>" <?= $selectedPm === (string) $pm ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $pm) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Zoom
            <select name="zoom">
                <option value="week" <?= $zoom === 'week' ? 'selected' : '' ?>>Semanas</option>
                <option value="month" <?= $zoom === 'month' ? 'selected' : '' ?>>Meses</option>
                <option value="quarter" <?= $zoom === 'quarter' ? 'selected' : '' ?>>Trimestres</option>
            </select>
        </label>
        <div class="gantt-filters__actions">
            <button type="submit" class="action-btn primary">Aplicar</button>
            <a href="<?= $basePath ?>/pmo/gantt-global" class="action-btn">Limpiar</a>
        </div>
    </form>

    <?php if ($projects === []): ?>
        <article class="gantt-empty">
            <h3>No hay cronogramas para mostrar</h3>
            <p>Cuando los proyectos tengan actividades o hitos en su cronograma aparecerán en esta vista global.</p>
        </article>
    <?php else: ?>
        <section class="gantt-global-layout" id="gantt-global-export-area">
            <div class="gantt-left">
                <div class="gantt-left__header">
                    <div>Proyecto</div>
                    <div>Cliente</div>
                    <div>PM</div>
                    <div>Avance %</div>
                    <div>Estado</div>
                </div>
                <div class="gantt-left__body" data-gantt-rows>
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $projectId = (int) ($project['id'] ?? 0);
                        $children = is_array($project['children'] ?? null) ? $project['children'] : [];
                        ?>
                        <div class="gantt-left__row project-row" data-project-row="<?= $projectId ?>">
                            <button type="button" class="expand-btn" data-expand-toggle="<?= $projectId ?>" aria-label="Expandir proyecto">▸</button>
                            <div class="project-cell">
                                <strong><?= htmlspecialchars((string) ($project['name'] ?? 'Proyecto')) ?></strong>
                            </div>
                            <div><?= htmlspecialchars((string) ($project['client'] ?? 'Sin cliente')) ?></div>
                            <div><?= htmlspecialchars((string) ($project['pm'] ?? 'Sin PM')) ?></div>
                            <div><?= number_format((float) ($project['progress_percent'] ?? 0), 1) ?>%</div>
                            <div><span class="health-pill health-<?= htmlspecialchars((string) ($project['health'] ?? 'green')) ?>"><?= htmlspecialchars((string) ($project['status_label'] ?? 'Sin estado')) ?></span></div>
                        </div>
                        <?php foreach ($children as $child): ?>
                            <?php $childType = (string) ($child['item_type'] ?? 'activity'); ?>
                            <div class="gantt-left__row child-row" data-child-row="<?= $projectId ?>" hidden>
                                <span class="child-indent"><?= $childType === 'milestone' ? '◆' : '—' ?></span>
                                <div class="project-cell">
                                    <?= htmlspecialchars((string) ($child['name'] ?? 'Actividad')) ?>
                                </div>
                                <div></div>
                                <div><?= htmlspecialchars((string) ($child['responsible_name'] ?? '')) ?></div>
                                <div><?= number_format((float) ($child['progress_percent'] ?? 0), 1) ?>%</div>
                                <div><?= $childType === 'milestone' ? 'Hito' : 'Actividad' ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="gantt-right">
                <div class="gantt-right__canvas" data-gantt-canvas data-gantt-projects='<?= htmlspecialchars(json_encode($projects, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'></div>
            </div>
        </section>
    <?php endif; ?>
</section>

<div class="gantt-tooltip" id="gantt-tooltip" hidden></div>

<script>
(() => {
    const root = document.querySelector('[data-gantt-global-root]');
    if (!root) return;
    const canvas = root.querySelector('[data-gantt-canvas]');
    const rowsContainer = root.querySelector('[data-gantt-rows]');
    const tooltip = document.getElementById('gantt-tooltip');
    if (!canvas || !rowsContainer) return;

    const projects = JSON.parse(canvas.dataset.ganttProjects || '[]');
    const zoom = root.dataset.zoom || 'month';

    const visibleRows = () => Array.from(rowsContainer.querySelectorAll('.gantt-left__row')).filter((row) => !row.hidden);

    const render = () => {
        const rowHeight = 30;
        const rows = [];
        visibleRows().forEach((row) => {
            if (row.classList.contains('project-row')) {
                const projectId = Number(row.getAttribute('data-project-row') || 0);
                const project = projects.find((item) => Number(item.id || 0) === projectId);
                if (project) {
                    rows.push({ type: 'project', project, row });
                }
            } else {
                const projectId = Number(row.getAttribute('data-child-row') || 0);
                const project = projects.find((item) => Number(item.id || 0) === projectId);
                if (!project) return;
                const name = row.querySelector('.project-cell')?.textContent?.trim() || '';
                const child = (project.children || []).find((item) => String(item.name || '').trim() === name);
                if (child) {
                    rows.push({ type: 'child', project, child, row });
                }
            }
        });

        const dateValues = [];
        rows.forEach((entry) => {
            if (entry.type === 'project') {
                if (entry.project.start_date) dateValues.push(new Date(entry.project.start_date));
                if (entry.project.end_date) dateValues.push(new Date(entry.project.end_date));
            } else {
                if (entry.child.start_date) dateValues.push(new Date(entry.child.start_date));
                if (entry.child.end_date) dateValues.push(new Date(entry.child.end_date));
            }
        });
        if (!dateValues.length) {
            canvas.innerHTML = '<p class="section-muted">Sin fechas para representar.</p>';
            return;
        }
        const minDate = new Date(Math.min(...dateValues.map((date) => date.getTime())));
        const maxDate = new Date(Math.max(...dateValues.map((date) => date.getTime())));
        const totalDays = Math.max(1, Math.floor((maxDate - minDate) / 86400000) + 1);
        const scale = zoom === 'week' ? 1.7 : (zoom === 'quarter' ? 0.75 : 1);

        const toX = (dateValue) => {
            const value = new Date(dateValue);
            const day = Math.floor((value - minDate) / 86400000);
            return (day / totalDays) * 100 * scale;
        };

        const todayX = toX(new Date().toISOString().slice(0, 10));
        let svgRows = '';
        rows.forEach((entry, index) => {
            const y = index * rowHeight + 8;
            if (entry.type === 'project') {
                const start = entry.project.start_date;
                const end = entry.project.end_date || start;
                if (!start || !end) return;
                const x = toX(start);
                const width = Math.max(0.6, toX(end) - x + (100 / totalDays));
                const health = String(entry.project.health || 'green');
                const color = health === 'red' ? '#ef4444' : (health === 'yellow' ? '#f59e0b' : '#10b981');
                const meta = {
                    name: entry.project.name || 'Proyecto',
                    client: entry.project.client || 'Sin cliente',
                    pm: entry.project.pm || 'Sin PM',
                    start_date: start,
                    end_date: end,
                    progress_percent: entry.project.progress_percent || 0,
                    status_label: entry.project.status_label || '',
                    project_id: entry.project.id || 0,
                };
                svgRows += `<rect x="${x}%" y="${y}" width="${width}%" height="14" rx="5" fill="${color}" class="gantt-project-bar" data-project='${JSON.stringify(meta)}'></rect>`;
            } else {
                const start = entry.child.start_date;
                const end = entry.child.end_date || start;
                if (!start || !end) return;
                if (String(entry.child.item_type || '') === 'milestone') {
                    const x = toX(start);
                    svgRows += `<polygon points="${x}%,${y + 1} ${x + 0.8}%,${y + 8} ${x}%,${y + 15} ${x - 0.8}%,${y + 8}" fill="#6366f1"></polygon>`;
                } else {
                    const x = toX(start);
                    const width = Math.max(0.4, toX(end) - x + (100 / totalDays));
                    svgRows += `<rect x="${x}%" y="${y + 2}" width="${width}%" height="10" rx="4" fill="#3b82f6"></rect>`;
                }
            }
        });

        canvas.innerHTML = `<svg viewBox="0 0 ${100 * scale} ${rows.length * rowHeight + 20}" preserveAspectRatio="none"><line x1="${todayX}" y1="0" x2="${todayX}" y2="${rows.length * rowHeight + 20}" stroke="#2563eb" stroke-width="0.35" stroke-dasharray="1 1"></line>${svgRows}</svg>`;

        canvas.querySelectorAll('.gantt-project-bar').forEach((bar) => {
            bar.addEventListener('click', (event) => {
                const payload = JSON.parse(bar.getAttribute('data-project') || '{}');
                tooltip.innerHTML = `<strong>${payload.name || ''}</strong><br>Cliente: ${payload.client || ''}<br>PM: ${payload.pm || ''}<br>Fechas: ${payload.start_date || ''} → ${payload.end_date || ''}<br>Avance: ${Number(payload.progress_percent || 0).toFixed(1)}%<br>Estado: ${payload.status_label || ''}`;
                tooltip.style.left = `${event.clientX + 10}px`;
                tooltip.style.top = `${event.clientY + 10}px`;
                tooltip.hidden = false;
            });
            bar.addEventListener('dblclick', () => {
                const payload = JSON.parse(bar.getAttribute('data-project') || '{}');
                if (!payload.project_id) return;
                window.location.href = `/projects/${payload.project_id}`;
            });
        });
    };

    document.addEventListener('click', (event) => {
        if (!tooltip || tooltip.hidden) return;
        if (!(event.target instanceof Element) || !event.target.closest('.gantt-project-bar')) {
            tooltip.hidden = true;
        }
    });

    rowsContainer.querySelectorAll('[data-expand-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const projectId = button.getAttribute('data-expand-toggle');
            const children = rowsContainer.querySelectorAll(`[data-child-row="${projectId}"]`);
            const expanded = button.classList.toggle('is-expanded');
            button.textContent = expanded ? '▾' : '▸';
            children.forEach((row) => {
                row.hidden = !expanded;
            });
            render();
        });
    });

    document.querySelector('[data-export-png]')?.addEventListener('click', () => {
        const svg = canvas.querySelector('svg');
        if (!svg) return;
        const data = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg.outerHTML);
        const link = document.createElement('a');
        link.href = data;
        link.download = 'gantt-global.png';
        link.click();
    });

    render();
})();
</script>

<style>
    .gantt-global-shell { display:flex; flex-direction:column; gap:12px; }
    .gantt-global-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
    .gantt-global-header h2 { margin:0; }
    .section-muted { color: var(--text-secondary); margin:0; font-size:13px; }
    .gantt-filters { border:1px solid var(--border); border-radius:14px; padding:12px; background:var(--surface); display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap:10px; }
    .gantt-filters label { display:flex; flex-direction:column; gap:6px; font-size:12px; font-weight:700; color:var(--text-primary); }
    .gantt-filters select { padding:8px 10px; border:1px solid var(--border); border-radius:10px; background:var(--surface); color:var(--text-primary); }
    .gantt-filters__actions { display:flex; gap:8px; align-items:flex-end; }
    .gantt-empty { border:1px dashed var(--border); border-radius:12px; padding:18px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); }
    .gantt-empty h3 { margin:0 0 6px 0; }
    .gantt-empty p { margin:0; color: var(--text-secondary); }

    .gantt-global-layout { display:grid; grid-template-columns: minmax(500px, 1.2fr) minmax(360px, 1fr); gap:10px; }
    .gantt-left, .gantt-right { border:1px solid var(--border); border-radius:12px; background:var(--surface); overflow:hidden; }
    .gantt-left { display:flex; flex-direction:column; }
    .gantt-left__header, .gantt-left__row { display:grid; grid-template-columns: 2fr 1.2fr 1.2fr .8fr 1fr; gap:8px; align-items:center; padding:10px 12px; }
    .gantt-left__header { font-size:11px; text-transform:uppercase; color:var(--text-secondary); font-weight:700; border-bottom:1px solid var(--border); }
    .gantt-left__body { max-height:560px; overflow:auto; }
    .gantt-left__row { border-top:1px solid var(--border); font-size:13px; }
    .gantt-left__row:first-child { border-top:none; }
    .project-row .project-cell { display:flex; align-items:center; gap:8px; }
    .expand-btn { border:1px solid var(--border); border-radius:8px; padding:2px 6px; background:var(--surface); cursor:pointer; font-size:12px; }
    .child-row .project-cell { padding-left:24px; color:var(--text-secondary); }
    .child-indent { color:var(--text-secondary); font-size:12px; }
    .health-pill { display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; }
    .health-pill.health-green { background: color-mix(in srgb, var(--success) 20%, var(--surface)); color: var(--success); }
    .health-pill.health-yellow { background: color-mix(in srgb, var(--warning) 20%, var(--surface)); color: var(--warning); }
    .health-pill.health-red { background: color-mix(in srgb, var(--danger) 18%, var(--surface)); color: var(--danger); }

    .gantt-right__canvas { min-height:560px; padding:8px; }
    .gantt-right__canvas svg { width:100%; height:100%; min-height:560px; }
    .gantt-project-bar { cursor:pointer; }

    .gantt-tooltip {
        position: fixed;
        z-index: 80;
        min-width: 220px;
        max-width: 320px;
        background: var(--surface);
        border:1px solid var(--border);
        border-radius:10px;
        padding:10px;
        box-shadow:0 12px 26px color-mix(in srgb, var(--text-primary) 18%, transparent);
        font-size:12px;
        line-height:1.4;
        pointer-events:none;
    }

    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }

    @media (max-width: 1180px) {
        .gantt-global-layout { grid-template-columns: 1fr; }
    }
</style>
