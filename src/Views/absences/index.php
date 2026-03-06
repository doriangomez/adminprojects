<?php
$basePath = $basePath ?? '';
$absences = is_array($absences ?? null) ? $absences : [];
$teamCapacity = is_array($teamCapacity ?? null) ? $teamCapacity : [];
$talents = is_array($talents ?? null) ? $talents : [];
$canApprove = !empty($canApprove);
$canManage = !empty($canManage);
$isPrivileged = !empty($isPrivileged);
$filters = is_array($filters ?? null) ? $filters : [];
$absenceTypes = is_array($absenceTypes ?? null) ? $absenceTypes : [];

$statusLabels = [
    'pendiente' => ['label' => 'Pendiente', 'class' => 'status-pending'],
    'aprobado' => ['label' => 'Aprobado', 'class' => 'status-approved'],
    'rechazado' => ['label' => 'Rechazado', 'class' => 'status-rejected'],
    'cancelado' => ['label' => 'Cancelado', 'class' => 'status-cancelled'],
];
?>

<style>
.absences-page { display: flex; flex-direction: column; gap: 20px; }
.absences-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 4px; }
.absences-tabs .tab { padding: 10px 20px; font-weight: 600; font-size: 14px; color: var(--text-secondary); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.18s; cursor: pointer; background: none; border-top: none; border-left: none; border-right: none; }
.absences-tabs .tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.absences-tabs .tab:hover { color: var(--primary); }

.capacity-overview { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
.capacity-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 8px; transition: box-shadow 0.18s; }
.capacity-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.capacity-card-header { display: flex; justify-content: space-between; align-items: center; }
.capacity-card-name { font-weight: 700; font-size: 15px; color: var(--text-primary); }
.capacity-card-role { font-size: 12px; color: var(--text-secondary); }
.capacity-bar-container { background: color-mix(in srgb, var(--border) 40%, var(--background)); border-radius: 6px; height: 8px; overflow: hidden; }
.capacity-bar { height: 100%; border-radius: 6px; transition: width 0.3s ease; }
.capacity-bar.green { background: var(--success, #10B981); }
.capacity-bar.yellow { background: var(--warning, #F59E0B); }
.capacity-bar.red { background: var(--danger, #EF4444); }
.capacity-stats { display: flex; gap: 12px; flex-wrap: wrap; font-size: 13px; }
.capacity-stat { display: flex; flex-direction: column; gap: 2px; }
.capacity-stat-label { color: var(--text-secondary); font-size: 11px; }
.capacity-stat-value { font-weight: 700; color: var(--text-primary); }
.capacity-stat-value.overload { color: var(--danger, #EF4444); }
.capacity-stat-value.available { color: var(--success, #10B981); }
.risk-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.risk-badge.sobrecarga { background: color-mix(in srgb, var(--danger) 15%, var(--surface)); color: var(--danger); }
.risk-badge.riesgo { background: color-mix(in srgb, var(--warning) 15%, var(--surface)); color: var(--warning); }
.risk-badge.normal { background: color-mix(in srgb, var(--success) 15%, var(--surface)); color: var(--success); }

.absence-filters { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.absence-filters select, .absence-filters input[type="date"] { padding: 6px 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; background: var(--surface); color: var(--text-primary); }
.absence-filters .btn-filter { padding: 6px 14px; border: 1px solid var(--primary); background: var(--primary); color: #fff; border-radius: 8px; font-size: 13px; cursor: pointer; font-weight: 600; }

.absence-list { display: flex; flex-direction: column; gap: 8px; }
.absence-row { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; transition: box-shadow 0.15s; }
.absence-row:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.05); }
.absence-type-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 700; color: #fff; white-space: nowrap; }
.absence-info { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.absence-talent { font-weight: 600; font-size: 14px; color: var(--text-primary); }
.absence-dates { font-size: 13px; color: var(--text-secondary); }
.absence-hours { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.absence-notes { font-size: 12px; color: var(--text-secondary); font-style: italic; }
.absence-status { padding: 3px 10px; border-radius: 99px; font-size: 12px; font-weight: 700; }
.status-pending { background: color-mix(in srgb, var(--warning) 15%, var(--surface)); color: var(--warning); }
.status-approved { background: color-mix(in srgb, var(--success) 15%, var(--surface)); color: var(--success); }
.status-rejected { background: color-mix(in srgb, var(--danger) 15%, var(--surface)); color: var(--danger); }
.status-cancelled { background: color-mix(in srgb, var(--text-secondary) 15%, var(--surface)); color: var(--text-secondary); }
.absence-actions { display: flex; gap: 6px; }
.absence-actions form { display: inline; }
.absence-actions .btn-sm { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: var(--surface); color: var(--text-primary); transition: all 0.15s; }
.absence-actions .btn-sm.approve { border-color: var(--success); color: var(--success); }
.absence-actions .btn-sm.approve:hover { background: var(--success); color: #fff; }
.absence-actions .btn-sm.reject { border-color: var(--danger); color: var(--danger); }
.absence-actions .btn-sm.reject:hover { background: var(--danger); color: #fff; }
.absence-actions .btn-sm.cancel-btn { border-color: var(--text-secondary); color: var(--text-secondary); }

.create-absence-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
.create-absence-form { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; align-items: end; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group label { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.form-group input, .form-group select, .form-group textarea { padding: 8px 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; background: var(--background); color: var(--text-primary); }
.form-group textarea { resize: vertical; min-height: 60px; }
.btn-create { padding: 8px 18px; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
.btn-create:hover { opacity: 0.9; }

.breakdown-tooltip { position: relative; cursor: pointer; }
.breakdown-tooltip .tooltip-content { display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: var(--text-primary); color: var(--background); padding: 10px 14px; border-radius: 8px; font-size: 12px; white-space: nowrap; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
.breakdown-tooltip:hover .tooltip-content { display: block; }
.tooltip-row { display: flex; justify-content: space-between; gap: 12px; }
.tooltip-row-label { opacity: 0.8; }
.tooltip-row-value { font-weight: 700; }

.tab-content { display: none; }
.tab-content.active { display: block; }

.empty-state { text-align: center; padding: 40px 20px; color: var(--text-secondary); }
.empty-state strong { display: block; font-size: 16px; color: var(--text-primary); margin-bottom: 6px; }

.section-title { font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px; }
.section-subtitle { font-size: 13px; color: var(--text-secondary); margin: 0 0 16px 0; }
</style>

<section class="absences-page">
    <div class="page-heading">
        <h2>Ausencias y Capacidad Real del Talento</h2>
        <p class="section-muted">Gestiona vacaciones, permisos, incapacidades y consulta la capacidad real del equipo.</p>
    </div>

    <div class="absences-tabs">
        <button class="tab active" data-tab="capacity" type="button">Capacidad del equipo</button>
        <button class="tab" data-tab="absences" type="button">Registro de ausencias</button>
        <?php if ($canManage): ?>
            <button class="tab" data-tab="create" type="button">+ Nueva ausencia</button>
        <?php endif; ?>
    </div>

    <!-- TAB: Team Capacity -->
    <div class="tab-content active" id="tab-capacity">
        <?php if (empty($teamCapacity)): ?>
            <div class="card empty-state">
                <strong>Sin datos de capacidad</strong>
                <small>No hay talentos registrados o no tienes permisos para ver la capacidad del equipo.</small>
            </div>
        <?php else: ?>
            <h3 class="section-title">Capacidad real de la semana actual</h3>
            <p class="section-subtitle">Capacidad base menos festivos y ausencias aprobadas. La capacidad real determina cuántas horas puede trabajar cada talento.</p>
            <div class="capacity-overview">
                <?php foreach ($teamCapacity as $talent): ?>
                    <?php
                    $utilization = (float) ($talent['utilization_percent'] ?? 0);
                    $barColor = $utilization > 100 ? 'red' : ($utilization >= 80 ? 'yellow' : 'green');
                    $barWidth = min(100, $utilization);
                    $breakdown = is_array($talent['breakdown'] ?? null) ? $talent['breakdown'] : [];
                    $deductions = is_array($breakdown['absence_deductions'] ?? null) ? $breakdown['absence_deductions'] : [];
                    $holidays = is_array($breakdown['holidays'] ?? null) ? $breakdown['holidays'] : [];
                    $risk = (string) ($talent['risk'] ?? 'normal');
                    ?>
                    <article class="capacity-card">
                        <div class="capacity-card-header">
                            <div>
                                <div class="capacity-card-name"><?= htmlspecialchars((string) ($talent['name'] ?? '')) ?></div>
                                <div class="capacity-card-role"><?= htmlspecialchars((string) ($talent['role'] ?? '')) ?></div>
                            </div>
                            <span class="risk-badge <?= htmlspecialchars($risk) ?>">
                                <?= $risk === 'sobrecarga' ? 'Sobrecarga' : ($risk === 'riesgo' ? 'Riesgo' : 'Normal') ?>
                            </span>
                        </div>

                        <div class="capacity-bar-container">
                            <div class="capacity-bar <?= $barColor ?>" style="width: <?= $barWidth ?>%"></div>
                        </div>

                        <div class="capacity-stats">
                            <div class="capacity-stat breakdown-tooltip">
                                <span class="capacity-stat-label">Capacidad real</span>
                                <span class="capacity-stat-value"><?= round((float) ($talent['real_capacity'] ?? 0), 1) ?>h</span>
                                <div class="tooltip-content">
                                    <div class="tooltip-row"><span class="tooltip-row-label">Capacidad base</span><span class="tooltip-row-value"><?= round((float) ($breakdown['base_capacity'] ?? 0), 1) ?>h</span></div>
                                    <?php foreach ($holidays as $holiday): ?>
                                        <div class="tooltip-row"><span class="tooltip-row-label">Festivo: <?= htmlspecialchars((string) ($holiday['name'] ?? '')) ?></span><span class="tooltip-row-value">-<?= round((float) ($holiday['hours'] ?? 0), 1) ?>h</span></div>
                                    <?php endforeach; ?>
                                    <?php foreach ($deductions as $ded): ?>
                                        <div class="tooltip-row"><span class="tooltip-row-label"><?= htmlspecialchars((string) ($ded['icon'] ?? '')) ?> <?= htmlspecialchars((string) ($ded['label'] ?? '')) ?> (<?= (int) ($ded['days'] ?? 0) ?> d)</span><span class="tooltip-row-value">-<?= round((float) ($ded['total_hours'] ?? 0), 1) ?>h</span></div>
                                    <?php endforeach; ?>
                                    <div class="tooltip-row" style="border-top:1px solid rgba(255,255,255,0.2); padding-top:4px; margin-top:4px;"><span class="tooltip-row-label"><strong>Capacidad real</strong></span><span class="tooltip-row-value"><strong><?= round((float) ($talent['real_capacity'] ?? 0), 1) ?>h</strong></span></div>
                                </div>
                            </div>
                            <div class="capacity-stat">
                                <span class="capacity-stat-label">Registradas</span>
                                <span class="capacity-stat-value"><?= round((float) ($talent['registered_hours'] ?? 0), 1) ?>h</span>
                            </div>
                            <div class="capacity-stat">
                                <span class="capacity-stat-label">Disponible</span>
                                <span class="capacity-stat-value available"><?= round((float) ($talent['available_capacity'] ?? 0), 1) ?>h</span>
                            </div>
                            <?php if ((float) ($talent['overload'] ?? 0) > 0): ?>
                                <div class="capacity-stat">
                                    <span class="capacity-stat-label">Sobrecarga</span>
                                    <span class="capacity-stat-value overload"><?= round((float) ($talent['overload'] ?? 0), 1) ?>h</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Absences List -->
    <div class="tab-content" id="tab-absences">
        <h3 class="section-title">Registro de ausencias</h3>

        <form method="GET" class="absence-filters card">
            <select name="status">
                <option value="">Todos los estados</option>
                <option value="pendiente" <?= ($filters['status'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="aprobado" <?= ($filters['status'] ?? '') === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
                <option value="rechazado" <?= ($filters['status'] ?? '') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                <option value="cancelado" <?= ($filters['status'] ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
            </select>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" placeholder="Desde">
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" placeholder="Hasta">
            <button type="submit" class="btn-filter">Filtrar</button>
        </form>

        <?php if (empty($absences)): ?>
            <div class="card empty-state">
                <strong>Sin ausencias registradas</strong>
                <small>No hay ausencias para los filtros seleccionados.</small>
            </div>
        <?php else: ?>
            <div class="absence-list">
                <?php foreach ($absences as $absence): ?>
                    <?php
                    $type = (string) ($absence['absence_type'] ?? 'permiso_personal');
                    $typeMeta = $absenceTypes[$type] ?? ['label' => $type, 'color' => '#6B7280', 'icon' => ''];
                    $status = (string) ($absence['status'] ?? 'pendiente');
                    $statusMeta = $statusLabels[$status] ?? $statusLabels['pendiente'];
                    $dateStart = (string) ($absence['date_start'] ?? '');
                    $dateEnd = (string) ($absence['date_end'] ?? '');
                    $totalHours = (float) ($absence['total_hours'] ?? 0);
                    $hoursPerDay = (float) ($absence['hours_per_day'] ?? 8);
                    $notes = trim((string) ($absence['notes'] ?? ''));
                    $talentName = trim((string) ($absence['talent_name'] ?? $absence['user_name'] ?? ''));
                    $absenceId = (int) ($absence['id'] ?? 0);
                    ?>
                    <div class="absence-row">
                        <span class="absence-type-badge" style="background: <?= htmlspecialchars($typeMeta['color']) ?>">
                            <?= $typeMeta['icon'] ?> <?= htmlspecialchars($typeMeta['label']) ?>
                        </span>
                        <div class="absence-info">
                            <?php if ($isPrivileged && $talentName !== ''): ?>
                                <div class="absence-talent"><?= htmlspecialchars($talentName) ?></div>
                            <?php endif; ?>
                            <div class="absence-dates"><?= htmlspecialchars($dateStart) ?> — <?= htmlspecialchars($dateEnd) ?></div>
                            <div class="absence-hours"><?= round($totalHours, 1) ?>h total (<?= round($hoursPerDay, 1) ?>h/día)</div>
                            <?php if ($notes !== ''): ?>
                                <div class="absence-notes"><?= htmlspecialchars($notes) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="absence-status <?= htmlspecialchars($statusMeta['class']) ?>"><?= htmlspecialchars($statusMeta['label']) ?></span>
                        <div class="absence-actions">
                            <?php if ($canApprove && $status === 'pendiente'): ?>
                                <form method="POST" action="<?= $basePath ?>/absences/approve">
                                    <input type="hidden" name="absence_id" value="<?= $absenceId ?>">
                                    <button type="submit" class="btn-sm approve">Aprobar</button>
                                </form>
                                <form method="POST" action="<?= $basePath ?>/absences/reject">
                                    <input type="hidden" name="absence_id" value="<?= $absenceId ?>">
                                    <button type="submit" class="btn-sm reject">Rechazar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($status === 'pendiente' || $status === 'aprobado'): ?>
                                <form method="POST" action="<?= $basePath ?>/absences/cancel">
                                    <input type="hidden" name="absence_id" value="<?= $absenceId ?>">
                                    <button type="submit" class="btn-sm cancel-btn">Cancelar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($isPrivileged): ?>
                                <form method="POST" action="<?= $basePath ?>/absences/delete" onsubmit="return confirm('¿Eliminar esta ausencia?')">
                                    <input type="hidden" name="absence_id" value="<?= $absenceId ?>">
                                    <button type="submit" class="btn-sm reject">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Create Absence -->
    <?php if ($canManage): ?>
        <div class="tab-content" id="tab-create">
            <div class="create-absence-card">
                <h3 class="section-title">Registrar nueva ausencia</h3>
                <p class="section-subtitle">Registra vacaciones, permisos, incapacidades u otras ausencias. Las ausencias aprobadas reducen la capacidad real del talento.</p>

                <form method="POST" action="<?= $basePath ?>/absences/create" class="create-absence-form">
                    <?php if ($isPrivileged): ?>
                        <div class="form-group">
                            <label for="talent_id">Talento</label>
                            <select name="talent_id" id="talent_id" required>
                                <option value="">Seleccionar talento</option>
                                <?php foreach ($talents as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>" data-user-id="<?= (int) ($t['user_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($t['name'] ?? '')) ?> (<?= htmlspecialchars((string) ($t['role'] ?? '')) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="absence_type">Tipo de ausencia</label>
                        <select name="absence_type" id="absence_type" required>
                            <option value="">Seleccionar tipo</option>
                            <?php foreach ($absenceTypes as $key => $meta): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= $meta['icon'] ?> <?= htmlspecialchars($meta['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_start">Fecha inicio</label>
                        <input type="date" name="date_start" id="date_start" required>
                    </div>
                    <div class="form-group">
                        <label for="date_end">Fecha fin</label>
                        <input type="date" name="date_end" id="date_end" required>
                    </div>
                    <div class="form-group">
                        <label for="hours_per_day">Horas por día</label>
                        <input type="number" name="hours_per_day" id="hours_per_day" min="0.5" max="24" step="0.5" value="8" required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="absence_notes">Notas</label>
                        <textarea name="notes" id="absence_notes" placeholder="Detalle opcional sobre la ausencia"></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-create">Registrar ausencia</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
(function() {
    const tabs = document.querySelectorAll('.absences-tabs .tab');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab');
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            tab.classList.add('active');
            const targetEl = document.getElementById('tab-' + target);
            if (targetEl) targetEl.classList.add('active');
        });
    });

    const typeSelect = document.getElementById('absence_type');
    const hoursInput = document.getElementById('hours_per_day');
    const defaultHours = <?= json_encode(array_map(fn($m) => $m['default_hours'], $absenceTypes)) ?>;

    if (typeSelect && hoursInput) {
        typeSelect.addEventListener('change', function() {
            const type = this.value;
            if (defaultHours[type] !== undefined) {
                hoursInput.value = defaultHours[type];
            }
        });
    }

    const talentSelect = document.getElementById('talent_id');
    if (talentSelect) {
        let userIdInput = document.querySelector('input[name="user_id"]');
        if (!userIdInput) {
            userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            talentSelect.closest('form').appendChild(userIdInput);
        }
        talentSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            userIdInput.value = opt ? (opt.getAttribute('data-user-id') || '') : '';
        });
    }
})();
</script>
