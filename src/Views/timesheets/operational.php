<?php
/**
 * Vista operativa de Timesheet – Registro rápido de horas
 * Layout SaaS: Header fijo + 2 columnas (70% calendario | 30% Quick Add)
 */
$basePath = $basePath ?? '';
$canReport = !empty($canReport);
$canApprove = !empty($canApprove);
$canManageWorkflow = !empty($canManageWorkflow);
$weekStart = $weekStart ?? new DateTimeImmutable('monday this week');
$weekEnd = $weekEnd ?? $weekStart->modify('+6 days');
$weekValue = $weekValue ?? $weekStart->format('o-\\WW');
$gridDays = is_array($weeklyGrid['days'] ?? null) ? $weeklyGrid['days'] : [];
$dayTotals = is_array($weeklyGrid['day_totals'] ?? null) ? $weeklyGrid['day_totals'] : [];
$activitiesByDay = is_array($weeklyGrid['activities_by_day'] ?? null) ? $weeklyGrid['activities_by_day'] : [];
$weekTotal = (float) ($weeklyGrid['week_total'] ?? 0);
$weeklyCapacity = (float) ($weeklyGrid['weekly_capacity'] ?? 0);
$compliancePercent = $weeklyCapacity > 0 ? min(100, round(($weekTotal / $weeklyCapacity) * 100, 2)) : 0;
$activityTypes = is_array($activityTypes ?? null) ? $activityTypes : ($weeklyGrid['activity_types'] ?? []);
$selectedWeekSummary = is_array($selectedWeekSummary ?? null) ? $selectedWeekSummary : [];
$projectsForTimesheet = is_array($projectsForTimesheet ?? null) ? $projectsForTimesheet : [];
$tasksForTimesheet = is_array($tasksForTimesheet ?? null) ? $tasksForTimesheet : [];
$recentActivitySuggestions = is_array($recentActivitySuggestions ?? null) ? $recentActivitySuggestions : [];
$projectBreakdownWeek = is_array($projectBreakdownWeek ?? null) ? $projectBreakdownWeek : [];

$statusMap = [
    'approved' => ['label' => 'Aprobada', 'class' => 'approved'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'rejected'],
    'submitted' => ['label' => 'Enviada', 'class' => 'submitted'],
    'draft' => ['label' => 'Borrador', 'class' => 'draft'],
];
$selectedStatus = (string) ($selectedWeekSummary['status'] ?? 'draft');
$selectedMeta = $statusMap[$selectedStatus] ?? $statusMap['draft'];

$weekIsLocked = $selectedStatus === 'approved';
$weekCanWithdraw = in_array($selectedStatus, ['submitted'], true);
$topProject = $projectBreakdownWeek[0] ?? null;
?>

<?php if (!$canReport): ?>
<section class="ts-operational">
    <div class="card ts-no-access">
        <h2>Registro de horas</h2>
        <p>No tienes permiso para registrar horas. Puedes revisar la analítica o las aprobaciones pendientes.</p>
        <div style="display:flex;gap:12px;margin-top:16px;">
            <a href="<?= $basePath ?>/timesheets/analytics" class="primary-button">Ver analítica</a>
            <a href="<?= $basePath ?>/approvals" class="secondary-button">Aprobaciones</a>
        </div>
    </div>
</section>
<?php return; endif; ?>

<section class="ts-operational">
    <!-- Header fijo -->
    <header class="ts-header card">
        <div class="ts-header-main">
            <div class="ts-header-title">
                <h2>Registro de horas</h2>
                <p class="ts-subtitle">Registra tu actividad en menos de 10 segundos</p>
            </div>
            <form method="GET" class="ts-week-form">
                <label>Semana
                    <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                </label>
                <button type="submit" class="primary-button">Ir</button>
            </form>
        </div>
        <div class="ts-header-badges">
            <span class="ts-badge ts-badge-hours"><?= round($weekTotal, 2) ?>h</span>
            <span class="ts-badge ts-badge-status <?= htmlspecialchars($selectedMeta['class']) ?>"><?= htmlspecialchars($selectedMeta['label']) ?></span>
            <span class="ts-badge ts-badge-capacity">Capacidad: <?= round($weeklyCapacity, 2) ?>h · <?= round($compliancePercent, 2) ?>%</span>
        </div>
        <div class="ts-header-actions">
            <?php if ($canReport && !$weekIsLocked): ?>
            <form method="POST" action="<?= $basePath ?>/timesheets/submit-week" class="inline-form">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <button type="submit" class="primary-button">Enviar semana</button>
            </form>
            <?php if ($weekCanWithdraw): ?>
            <form method="POST" action="<?= $basePath ?>/timesheets/cancel-week" class="inline-form">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <button type="submit" class="secondary-button">Retirar envío</button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
            <a href="<?= $basePath ?>/timesheets/analytics" class="secondary-button">Ver analítica</a>
        </div>
    </header>

    <!-- Panel principal 2 columnas -->
    <div class="ts-panel">
        <!-- Columna izquierda 70%: Calendario semanal -->
        <section class="ts-calendar">
            <div class="ts-calendar-grid">
                <?php foreach ($gridDays as $day): ?>
                <?php
                $dayDate = (string) ($day['key'] ?? '');
                $dayItems = is_array($activitiesByDay[$dayDate] ?? null) ? $activitiesByDay[$dayDate] : [];
                $totalDayHours = (float) ($dayTotals[$dayDate] ?? 0);
                $canEdit = $canReport && !$weekIsLocked && !in_array($selectedStatus, ['submitted'], true);
                ?>
                <article class="ts-day-card" data-date="<?= htmlspecialchars($dayDate) ?>">
                    <header class="ts-day-header">
                        <strong><?= htmlspecialchars((string) ($day['label'] ?? '')) ?> <?= htmlspecialchars((string) ($day['number'] ?? '')) ?></strong>
                        <span class="ts-day-total"><?= round($totalDayHours, 2) ?>h</span>
                    </header>
                    <div class="ts-day-activities">
                        <?php if ($dayItems === []): ?>
                        <p class="ts-empty">Sin actividad</p>
                        <?php else: ?>
                        <?php foreach ($dayItems as $item): ?>
                        <?php
                        $itemId = (int) ($item['id'] ?? 0);
                        $itemStatus = (string) ($item['status'] ?? 'draft');
                        $itemEditable = $canEdit && in_array($itemStatus, ['draft', 'rejected'], true);
                        $taskLabel = trim((string) ($item['task_title'] ?? ''));
                        if ($taskLabel === '') $taskLabel = 'Registro general';
                        ?>
                        <div class="ts-activity-chip <?= $itemEditable ? 'editable' : '' ?>" data-id="<?= $itemId ?>" data-date="<?= htmlspecialchars($dayDate) ?>">
                            <div class="ts-chip-content">
                                <span class="ts-chip-project"><?= htmlspecialchars((string) ($item['project'] ?? '')) ?></span>
                                <span class="ts-chip-task"><?= htmlspecialchars($taskLabel) ?></span>
                                <span class="ts-chip-desc"><?= htmlspecialchars((string) (($item['activity_description'] ?? '') !== '' ? $item['activity_description'] : 'Actividad')) ?></span>
                                <span class="ts-chip-hours"><?= round((float) ($item['hours'] ?? 0), 2) ?>h</span>
                                <div class="ts-chip-flags">
                                    <?php if (!empty($item['had_blocker'])): ?><span class="ts-flag blocker" title="Bloqueo">🔒</span><?php endif; ?>
                                    <?php if (!empty($item['had_significant_progress'])): ?><span class="ts-flag progress" title="Avance">📈</span><?php endif; ?>
                                    <?php if (!empty($item['generated_deliverable'])): ?><span class="ts-flag deliverable" title="Entregable">📦</span><?php endif; ?>
                                </div>
                            </div>
                            <?php if ($itemEditable): ?>
                            <div class="ts-chip-actions">
                                <button type="button" class="ts-action-btn edit" title="Editar" data-action="edit">✏️</button>
                                <button type="button" class="ts-action-btn duplicate" title="Duplicar a otro día" data-action="duplicate">📋</button>
                                <button type="button" class="ts-action-btn move" title="Mover" data-action="move">↔️</button>
                                <form method="POST" action="<?= $basePath ?>/timesheets/activity/delete" class="ts-action-form" onsubmit="return confirm('¿Eliminar esta actividad?');">
                                    <input type="hidden" name="timesheet_id" value="<?= $itemId ?>">
                                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                    <button type="submit" class="ts-action-btn delete" title="Eliminar">🗑️</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($canEdit): ?>
                    <div class="ts-duplicate-day">
                        <form method="POST" action="<?= $basePath ?>/timesheets/duplicate-day">
                            <input type="hidden" name="source_date" value="<?= htmlspecialchars($dayDate) ?>">
                            <?php
                            $nextDay = (new DateTimeImmutable($dayDate))->modify('+1 day');
                            $targetDate = $nextDay->format('Y-m-d');
                            ?>
                            <input type="hidden" name="target_date" value="<?= htmlspecialchars($targetDate) ?>">
                            <button type="submit" class="ts-link-btn" title="Duplicar al día siguiente">+ Duplicar día</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Columna derecha 30%: Quick Add -->
        <?php if ($canReport && !$weekIsLocked): ?>
        <aside class="ts-quick-add card">
            <h3>Quick Add</h3>
            <p class="ts-quick-desc">Registro en &lt;10 segundos</p>
            <form method="POST" action="<?= $basePath ?>/timesheets/activity" class="ts-quick-form" id="quick-activity-form">
                <input type="hidden" name="sync_operational" value="1">
                <label>Fecha
                    <input type="date" name="date" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>" required>
                </label>
                <label>Proyecto <span class="required">*</span>
                    <select name="project_id" id="quick-project" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($projectsForTimesheet as $project): ?>
                        <option value="<?= (int) ($project['project_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($project['project'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Tarea <span class="required">*</span>
                    <select name="task_id" id="quick-task">
                        <option value="0">Registro general</option>
                        <?php foreach ($tasksForTimesheet as $task): ?>
                        <option value="<?= (int) ($task['task_id'] ?? 0) ?>" data-project-id="<?= (int) ($task['project_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($task['project'] ?? '')) ?> · <?= htmlspecialchars((string) ($task['task_title'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Horas <span class="required">*</span>
                    <input type="number" name="hours" step="0.25" min="0.25" max="24" required placeholder="0">
                </label>
                <label>Descripción breve <span class="required">*</span>
                    <input type="text" name="activity_description" maxlength="255" placeholder="Qué se hizo" required>
                </label>
                <label>Comentario
                    <input type="text" name="comment" placeholder="Opcional">
                </label>
                <div class="ts-toggles">
                    <label class="ts-toggle"><input type="checkbox" name="had_blocker" value="1" id="quick-had-blocker"> Bloqueo</label>
                    <label id="quick-blocker-wrap" class="ts-blocker-wrap hidden">Descripción
                        <input type="text" name="blocker_description" maxlength="500" placeholder="Describe el impedimento">
                    </label>
                    <label class="ts-toggle"><input type="checkbox" name="had_significant_progress" value="1"> Avance significativo</label>
                    <label class="ts-toggle"><input type="checkbox" name="generated_deliverable" value="1"> Entregable</label>
                </div>
                <div class="ts-quick-buttons">
                    <button type="submit" class="primary-button">Guardar</button>
                    <button type="submit" name="save_and_duplicate" value="1" class="secondary-button">Guardar y duplicar</button>
                    <button type="submit" name="save_and_add" value="1" class="secondary-button">Guardar y agregar otra</button>
                </div>
            </form>
            <?php if ($recentActivitySuggestions !== []): ?>
            <div class="ts-recent">
                <strong>Recientes</strong>
                <div class="ts-recent-list">
                    <?php foreach ($recentActivitySuggestions as $recent): ?>
                    <button type="button" class="ts-recent-chip" data-project-id="<?= (int) ($recent['project_id'] ?? 0) ?>" data-task-id="<?= (int) ($recent['task_id'] ?? 0) ?>" data-activity-description="<?= htmlspecialchars((string) ($recent['activity_description'] ?? ''), ENT_QUOTES) ?>">
                        <?= htmlspecialchars((string) ($recent['project'] ?? '')) ?> · <?= htmlspecialchars((string) ($recent['activity_description'] ?? 'Actividad')) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>
        <?php else: ?>
        <aside class="ts-quick-add card ts-locked">
            <p class="ts-subtitle">La semana está <?= htmlspecialchars(strtolower($selectedMeta['label'])) ?>. No se pueden agregar actividades.</p>
            <a href="<?= $basePath ?>/timesheets/analytics" class="primary-button">Ver analítica</a>
        </aside>
        <?php endif; ?>
    </div>

    <!-- Indicadores básicos -->
    <section class="ts-indicators card">
        <h4>Indicadores de la semana</h4>
        <div class="ts-indicators-grid">
            <div><span>Horas registradas</span><strong><?= round($weekTotal, 2) ?>h</strong></div>
            <div><span>Capacidad semanal</span><strong><?= round($weeklyCapacity, 2) ?>h</strong></div>
            <div><span>% cumplimiento</span><strong><?= round($compliancePercent, 2) ?>%</strong></div>
            <div><span>Proyecto con mayor consumo</span><strong><?= htmlspecialchars((string) ($topProject['project'] ?? '—')) ?> (<?= round((float) ($topProject['total_hours'] ?? 0), 2) ?>h)</strong></div>
        </div>
    </section>
</section>

<!-- Modal editar actividad -->
<dialog id="ts-edit-modal" class="ts-modal">
    <form method="dialog" class="ts-modal-body">
        <h4>Editar actividad</h4>
        <input type="hidden" id="ts-edit-id">
        <label>Horas <input type="number" id="ts-edit-hours" step="0.25" min="0.25" max="24" required></label>
        <label>Descripción <input type="text" id="ts-edit-desc" maxlength="255" required></label>
        <label>Comentario <input type="text" id="ts-edit-comment"></label>
        <div class="ts-modal-actions">
            <button type="button" class="secondary-button" id="ts-edit-cancel">Cancelar</button>
            <button type="button" class="primary-button" id="ts-edit-save">Guardar</button>
        </div>
    </form>
</dialog>

<!-- Modal duplicar/mover -->
<dialog id="ts-move-modal" class="ts-modal">
    <form method="dialog" class="ts-modal-body">
        <h4 id="ts-move-title">Duplicar actividad</h4>
        <input type="hidden" id="ts-move-id">
        <input type="hidden" id="ts-move-action" value="duplicate">
        <label>Fecha destino <input type="date" id="ts-move-date" required></label>
        <div class="ts-modal-actions">
            <button type="button" class="secondary-button" id="ts-move-cancel">Cancelar</button>
            <button type="button" class="primary-button" id="ts-move-confirm">Confirmar</button>
        </div>
    </form>
</dialog>

<style>
.ts-operational{display:flex;flex-direction:column;gap:16px;padding-bottom:24px}
.ts-header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px}
.ts-header-main{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.ts-header-title h2{margin:0;font-size:1.35rem}
.ts-subtitle,.ts-quick-desc{font-size:0.9rem;color:var(--text-secondary);margin:4px 0 0}
.ts-week-form{display:flex;align-items:flex-end;gap:8px}
.ts-week-form label{display:flex;flex-direction:column;gap:4px}
.ts-header-badges{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.ts-badge{padding:6px 12px;border-radius:8px;font-size:0.9rem;font-weight:600}
.ts-badge-hours{background:var(--accent);color:#fff}
.ts-badge-status.approved{background:#dcfce7;color:#166534}
.ts-badge-status.rejected{background:#fee2e2;color:#991b1b}
.ts-badge-status.submitted{background:#fef3c7;color:#92400e}
.ts-badge-status.draft{background:#e5e7eb;color:#374151}
.ts-badge-capacity{background:#f1f5f9;color:#475569}
.ts-header-actions{display:flex;gap:8px;flex-wrap:wrap}
.ts-panel{display:grid;grid-template-columns:1fr 340px;gap:20px}
@media (max-width: 1100px){.ts-panel{grid-template-columns:1fr}}
.ts-calendar{min-width:0}
.ts-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(140px,1fr));gap:10px}
@media (max-width: 1200px){.ts-calendar-grid{grid-template-columns:repeat(4,1fr)}}
@media (max-width: 768px){.ts-calendar-grid{grid-template-columns:1fr 1fr}}
.ts-day-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:10px;min-height:180px;display:flex;flex-direction:column}
.ts-day-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--border)}
.ts-day-total{font-weight:700;color:var(--accent)}
.ts-day-activities{flex:1;display:flex;flex-direction:column;gap:6px}
.ts-empty{font-size:0.85rem;color:var(--text-secondary);margin:0}
.ts-activity-chip{display:flex;justify-content:space-between;align-items:flex-start;gap:6px;padding:8px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0}
.ts-activity-chip.editable:hover{border-color:var(--accent)}
.ts-chip-content{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
.ts-chip-project{font-weight:600;font-size:0.9rem}
.ts-chip-task,.ts-chip-desc{font-size:0.8rem;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ts-chip-hours{font-weight:700;color:var(--accent);font-size:0.9rem}
.ts-chip-flags{display:flex;gap:4px;margin-top:4px}
.ts-flag{font-size:12px}
.ts-chip-actions{display:flex;gap:4px;flex-shrink:0}
.ts-action-btn{background:none;border:none;cursor:pointer;padding:4px;opacity:.7;font-size:14px}
.ts-action-btn:hover{opacity:1}
.ts-duplicate-day{margin-top:8px;padding-top:6px;border-top:1px dashed var(--border)}
.ts-link-btn{background:none;border:none;color:var(--accent);cursor:pointer;font-size:0.85rem;text-decoration:underline}
.ts-link-btn:hover{text-decoration:none}
.ts-quick-add{height:fit-content;position:sticky;top:80px}
.ts-quick-add h3{margin:0 0 4px;font-size:1.1rem}
.ts-quick-form{display:flex;flex-direction:column;gap:10px;margin-top:12px}
.ts-quick-form label{display:flex;flex-direction:column;gap:4px;font-size:0.9rem}
.ts-quick-form input,.ts-quick-form select{border:1px solid var(--border);border-radius:8px;padding:8px}
.required{color:#dc2626}
.ts-toggles{display:flex;flex-direction:column;gap:6px}
.ts-toggle{flex-direction:row !important;align-items:center}
.ts-blocker-wrap.hidden{display:none !important}
.ts-quick-buttons{display:flex;flex-direction:column;gap:8px;margin-top:8px}
.ts-recent{margin-top:14px;padding-top:12px;border-top:1px solid var(--border)}
.ts-recent strong{font-size:0.9rem}
.ts-recent-list{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.ts-recent-chip{padding:6px 10px;border:1px solid var(--border);border-radius:999px;background:var(--surface);cursor:pointer;font-size:0.85rem}
.ts-recent-chip:hover{background:#f1f5f9;border-color:var(--accent)}
.ts-indicators{padding:14px 18px}
.ts-indicators h4{margin:0 0 10px;font-size:1rem}
.ts-indicators-grid{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:12px}
.ts-indicators-grid div{display:flex;flex-direction:column;gap:2px}
.ts-indicators-grid span{font-size:0.85rem;color:var(--text-secondary)}
.ts-indicators-grid strong{font-size:1.1rem}
.ts-modal{border:none;border-radius:12px;max-width:400px;width:95%}
.ts-modal::backdrop{background:rgba(15,23,42,.4)}
.ts-modal-body{display:flex;flex-direction:column;gap:12px;padding:20px}
.ts-modal-body label{display:flex;flex-direction:column;gap:4px}
.ts-modal-body input{border:1px solid var(--border);border-radius:8px;padding:8px}
.ts-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:8px}
.ts-locked{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;min-height:200px;text-align:center}
.inline-form{display:inline}
</style>

<script>
(() => {
  const basePath = '<?= addslashes($basePath) ?>';
  const weekValue = '<?= addslashes($weekValue) ?>';
  const quickForm = document.getElementById('quick-activity-form');
  const quickProject = document.getElementById('quick-project');
  const quickTask = document.getElementById('quick-task');
  const blockerToggle = document.getElementById('quick-had-blocker');
  const blockerWrap = document.getElementById('quick-blocker-wrap');

  function filterTasksByProject() {
    if (!quickTask) return;
    const selectedProject = Number(quickProject?.value || 0);
    quickTask.querySelectorAll('option[data-project-id]').forEach((opt) => {
      opt.hidden = selectedProject <= 0 || Number(opt.dataset.projectId) === selectedProject ? false : true;
    });
    const sel = quickTask.selectedOptions[0];
    if (sel?.hidden) quickTask.value = '0';
  }
  quickProject?.addEventListener('change', filterTasksByProject);
  filterTasksByProject();

  blockerToggle?.addEventListener('change', () => {
    blockerWrap?.classList.toggle('hidden', !blockerToggle.checked);
    if (!blockerToggle.checked) blockerWrap?.querySelector('input')?.setAttribute('value', '');
  });

  document.querySelectorAll('.ts-recent-chip').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!quickForm) return;
      const p = quickForm.querySelector('[name="project_id"]');
      const t = quickForm.querySelector('[name="task_id"]');
      const d = quickForm.querySelector('[name="activity_description"]');
      if (p) p.value = btn.dataset.projectId || '';
      filterTasksByProject();
      if (t) t.value = btn.dataset.taskId || '0';
      if (d) d.value = btn.dataset.activityDescription || '';
      d?.focus();
    });
  });

  const editModal = document.getElementById('ts-edit-modal');
  const editId = document.getElementById('ts-edit-id');
  const editHours = document.getElementById('ts-edit-hours');
  const editDesc = document.getElementById('ts-edit-desc');
  const editComment = document.getElementById('ts-edit-comment');

  document.querySelectorAll('.ts-action-btn[data-action="edit"]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const chip = btn.closest('.ts-activity-chip');
      if (!chip || !editModal) return;
      const id = chip.dataset.id;
      const project = chip.querySelector('.ts-chip-project')?.textContent || '';
      const task = chip.querySelector('.ts-chip-task')?.textContent || '';
      const desc = chip.querySelector('.ts-chip-desc')?.textContent || '';
      const hours = chip.querySelector('.ts-chip-hours')?.textContent?.replace('h','') || '0';
      editId.value = id;
      editHours.value = hours;
      editDesc.value = desc;
      editModal.showModal();
    });
  });

  document.getElementById('ts-edit-cancel')?.addEventListener('click', () => editModal?.close());
  document.getElementById('ts-edit-save')?.addEventListener('click', async () => {
    const payload = new URLSearchParams({
      timesheet_id: editId.value,
      hours: editHours.value,
      activity_description: editDesc.value,
      comment: editComment.value
    });
    const res = await fetch(basePath + '/timesheets/activity/update', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload });
    const data = await res.json().catch(() => ({}));
    if (data.ok) location.reload();
    else alert(data.message || 'Error al guardar');
  });

  const moveModal = document.getElementById('ts-move-modal');
  const moveId = document.getElementById('ts-move-id');
  const moveAction = document.getElementById('ts-move-action');
  const moveDate = document.getElementById('ts-move-date');
  const moveTitle = document.getElementById('ts-move-title');
  const moveConfirm = document.getElementById('ts-move-confirm');

  function openMoveModal(action, id) {
    moveId.value = id;
    moveAction.value = action;
    moveTitle.textContent = action === 'move' ? 'Mover actividad' : 'Duplicar actividad';
    moveDate.value = '';
    moveModal?.showModal();
  }

  document.querySelectorAll('.ts-action-btn[data-action="duplicate"]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const chip = btn.closest('.ts-activity-chip');
      if (chip) openMoveModal('duplicate', chip.dataset.id);
    });
  });
  document.querySelectorAll('.ts-action-btn[data-action="move"]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const chip = btn.closest('.ts-activity-chip');
      if (chip) openMoveModal('move', chip.dataset.id);
    });
  });

  document.getElementById('ts-move-cancel')?.addEventListener('click', () => moveModal?.close());
  moveConfirm?.addEventListener('click', () => {
    const id = moveId.value;
    const date = moveDate.value;
    const action = moveAction.value;
    if (!date) { alert('Selecciona una fecha'); return; }
    const formData = new URLSearchParams({ timesheet_id: id, target_date: date });
    const url = action === 'move' ? basePath + '/timesheets/activity/move' : basePath + '/timesheets/activity/duplicate';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    formData.forEach((v,k) => { const i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=v; form.appendChild(i); });
    document.body.appendChild(form);
    form.submit();
  });
})();
</script>
