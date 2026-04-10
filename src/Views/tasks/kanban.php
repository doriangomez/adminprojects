<?php
$basePath = $basePath ?? '';
$kanbanColumns = is_array($kanbanColumns ?? null) ? $kanbanColumns : [];
$projectOptions = is_array($projectOptions ?? null) ? $projectOptions : [];
$assigneeOptions = is_array($assigneeOptions ?? null) ? $assigneeOptions : [];
$kanbanStatusOrder = is_array($kanbanStatusOrder ?? null) ? $kanbanStatusOrder : ['todo', 'in_progress', 'review', 'blocked', 'done'];
$kanbanStatusMeta = is_array($kanbanStatusMeta ?? null) ? $kanbanStatusMeta : [];
$selectedProjectId = (int) ($selectedProjectId ?? 0);
$selectedAssigneeId = (int) ($selectedAssigneeId ?? 0);
$selectedPriority = strtolower(trim((string) ($selectedPriority ?? '')));
$canManage = !empty($canManage);
$canCreateTasks = !empty($canCreateTasks);
$isTalentUser = !empty($isTalentUser);
$talents = is_array($talents ?? null) ? $talents : [];
?>

<section class="kanban-page">
    <header class="kanban-page__header">
        <div>
            <h2>Kanban global</h2>
            <p class="section-muted">Tablero de tareas por estado para todos los proyectos con acceso.</p>
        </div>
        <a class="action-btn" href="<?= $basePath ?>/tasks">Vista Tareas</a>
    </header>

    <form method="GET" action="<?= $basePath ?>/tasks/kanban" class="kanban-filters">
        <label>
            Proyecto
            <select name="project_id">
                <option value="0">Todos</option>
                <?php foreach ($projectOptions as $project): ?>
                    <option value="<?= (int) ($project['id'] ?? 0) ?>" <?= $selectedProjectId === (int) ($project['id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($project['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Responsable
            <select name="assignee_id">
                <option value="0">Todos</option>
                <?php foreach ($assigneeOptions as $option): ?>
                    <option value="<?= (int) ($option['id'] ?? 0) ?>" <?= $selectedAssigneeId === (int) ($option['id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($option['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Prioridad
            <select name="priority">
                <option value="">Todas</option>
                <option value="high" <?= $selectedPriority === 'high' ? 'selected' : '' ?>>Alta</option>
                <option value="medium" <?= $selectedPriority === 'medium' ? 'selected' : '' ?>>Media</option>
                <option value="low" <?= $selectedPriority === 'low' ? 'selected' : '' ?>>Baja</option>
            </select>
        </label>
        <div class="kanban-filters__actions">
            <button type="submit" class="action-btn primary">Aplicar</button>
            <a href="<?= $basePath ?>/tasks/kanban" class="action-btn">Limpiar</a>
        </div>
    </form>

    <section class="kanban-board" data-kanban-board>
        <?php
        $hasAnyTask = false;
        foreach ($kanbanStatusOrder as $statusKey) {
            if (!empty($kanbanColumns[$statusKey])) {
                $hasAnyTask = true;
                break;
            }
        }
        ?>

        <?php if (!$hasAnyTask): ?>
            <article class="kanban-empty">
                <h3>No hay tareas para los filtros seleccionados</h3>
                <p>Prueba quitando filtros o crea una tarea nueva para comenzar.</p>
            </article>
        <?php endif; ?>

        <?php foreach ($kanbanStatusOrder as $statusKey): ?>
            <?php
            $columnMeta = $kanbanStatusMeta[$statusKey] ?? ['label' => ucfirst($statusKey), 'icon' => '📌', 'accent' => 'var(--border)'];
            $items = is_array($kanbanColumns[$statusKey] ?? null) ? $kanbanColumns[$statusKey] : [];
            ?>
            <article class="kanban-column" data-status-column="<?= htmlspecialchars($statusKey) ?>">
                <header class="kanban-column__header">
                    <h3><?= htmlspecialchars((string) ($columnMeta['icon'] ?? '📌')) ?> <?= htmlspecialchars((string) ($columnMeta['label'] ?? $statusKey)) ?></h3>
                    <span class="count-pill" data-column-count><?= count($items) ?></span>
                </header>
                <div class="kanban-column__cards" data-dropzone="<?= htmlspecialchars($statusKey) ?>">
                    <?php foreach ($items as $task): ?>
                        <?php
                        $dueDate = trim((string) ($task['due_date'] ?? ''));
                        $taskStatusNormalized = strtolower(trim((string) ($task['status'] ?? 'todo')));
                        $taskStatusNormalized = $taskStatusNormalized === 'pending'
                            ? 'todo'
                            : ($taskStatusNormalized === 'completed' ? 'done' : $taskStatusNormalized);
                        $isOverdue = $dueDate !== '' && $dueDate < date('Y-m-d') && $taskStatusNormalized !== 'done';
                        $priority = strtolower(trim((string) ($task['priority'] ?? 'medium')));
                        $priorityColor = match ($priority) {
                            'high' => '#ef4444',
                            'medium' => '#f59e0b',
                            default => '#94a3b8',
                        };
                        $assignee = trim((string) ($task['assignee'] ?? ''));
                        $assigneeInitial = strtoupper(substr($assignee !== '' ? $assignee : 'S', 0, 1));
                        $subtasksCompleted = (int) ($task['subtasks_completed'] ?? 0);
                        $subtasksTotal = (int) ($task['subtasks_total'] ?? 0);
                        ?>
                        <article
                            class="kanban-card"
                            draggable="true"
                            data-task-id="<?= (int) ($task['id'] ?? 0) ?>"
                            data-task-status="<?= htmlspecialchars($statusKey) ?>"
                        >
                            <h4><?= htmlspecialchars((string) ($task['title'] ?? 'Sin título')) ?></h4>
                            <p class="kanban-card__project"><?= htmlspecialchars((string) ($task['project'] ?? 'Sin proyecto')) ?></p>
                            <div class="kanban-card__meta">
                                <span class="assignee-avatar" title="<?= htmlspecialchars($assignee !== '' ? $assignee : 'Sin asignar') ?>"><?= htmlspecialchars($assigneeInitial) ?></span>
                                <span class="priority-dot" style="background: <?= htmlspecialchars($priorityColor) ?>" title="Prioridad"></span>
                                <?php if ($dueDate !== ''): ?>
                                    <span class="<?= $isOverdue ? 'due-overdue' : 'due-normal' ?>">📅 <?= htmlspecialchars($dueDate) ?></span>
                                <?php else: ?>
                                    <span class="due-normal">📅 Sin fecha</span>
                                <?php endif; ?>
                                <?php if ($subtasksTotal > 0): ?>
                                    <span class="subtasks-pill"><?= $subtasksCompleted ?>/<?= $subtasksTotal ?></span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($canCreateTasks): ?>
                    <button type="button" class="action-btn small" data-add-task="<?= htmlspecialchars($statusKey) ?>">+ Agregar tarea</button>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</section>

<aside class="task-drawer" id="task-drawer" hidden>
    <div class="task-drawer__backdrop" data-drawer-close></div>
    <div class="task-drawer__panel">
        <header class="task-drawer__header">
            <h3 id="task-drawer-title">Detalle de tarea</h3>
            <button type="button" class="action-btn small" data-drawer-close>Cerrar</button>
        </header>
        <div class="task-drawer__body">
            <form id="task-edit-form">
                <input type="hidden" name="task_id" id="drawer-task-id" />
                <label>Título<input type="text" name="title" id="drawer-title" required></label>
                <label>Estado
                    <select name="status" id="drawer-status">
                        <?php foreach ($kanbanStatusOrder as $statusKey): ?>
                            <?php $meta = $kanbanStatusMeta[$statusKey] ?? ['label' => ucfirst($statusKey)]; ?>
                            <option value="<?= htmlspecialchars($statusKey) ?>"><?= htmlspecialchars((string) ($meta['label'] ?? $statusKey)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Prioridad
                    <select name="priority" id="drawer-priority">
                        <option value="high">Alta</option>
                        <option value="medium">Media</option>
                        <option value="low">Baja</option>
                    </select>
                </label>
                <label>Horas estimadas<input type="number" name="estimated_hours" id="drawer-estimated-hours" min="0" step="0.25"></label>
                <label>Fecha límite<input type="date" name="due_date" id="drawer-due-date"></label>
                <?php if ($canManage): ?>
                    <label>Responsable
                        <select name="assignee_id" id="drawer-assignee-id">
                            <option value="0">Sin asignar</option>
                            <?php foreach ($talents as $talent): ?>
                                <option value="<?= (int) ($talent['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($talent['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <label>Actividad cronograma
                    <select name="schedule_activity_id" id="drawer-schedule-activity">
                        <option value="0">Sin vincular</option>
                    </select>
                </label>
                <div class="task-drawer__actions">
                    <button type="submit" class="action-btn primary">Guardar</button>
                </div>
                <p id="task-drawer-message" class="section-muted"></p>
            </form>
        </div>
    </div>
</aside>

<?php if ($canCreateTasks): ?>
    <dialog id="create-task-modal">
        <form method="POST" action="<?= $basePath ?>/tasks/create" id="create-task-form">
            <h3>Nueva tarea</h3>
            <input type="hidden" name="status" id="create-task-status" value="todo">
            <input type="hidden" name="redirect_to" value="/tasks/kanban">
            <label>Proyecto
                <select name="project_id" required>
                    <option value="">Selecciona proyecto</option>
                    <?php foreach ($projectOptions as $project): ?>
                        <option value="<?= (int) ($project['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($project['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Título<input type="text" name="title" required maxlength="160"></label>
            <label>Prioridad
                <select name="priority">
                    <option value="medium">Media</option>
                    <option value="high">Alta</option>
                    <option value="low">Baja</option>
                </select>
            </label>
            <label>Horas estimadas<input type="number" name="estimated_hours" min="0" step="0.25" value="0"></label>
            <label>Fecha límite<input type="date" name="due_date"></label>
            <?php if ($canManage): ?>
                <label>Responsable
                    <select name="assignee_id">
                        <option value="0">Sin asignar</option>
                        <?php foreach ($talents as $talent): ?>
                            <option value="<?= (int) ($talent['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($talent['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <div class="task-drawer__actions">
                <button type="submit" class="action-btn primary">Crear</button>
                <button type="button" class="action-btn" data-close-create-task>Cancelar</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>

<script>
(() => {
    const board = document.querySelector('[data-kanban-board]');
    if (!board) return;

    const drawer = document.getElementById('task-drawer');
    const drawerForm = document.getElementById('task-edit-form');
    const drawerMessage = document.getElementById('task-drawer-message');
    let draggedCard = null;
    let dragStartedAt = 0;

    const normalizeStatus = (status) => {
        const value = String(status || '').toLowerCase().trim();
        if (value === 'pending') return 'todo';
        if (value === 'completed') return 'done';
        return value;
    };

    const closeDrawer = () => {
        if (!drawer) return;
        drawer.hidden = true;
    };

    drawer?.querySelectorAll('[data-drawer-close]').forEach((button) => {
        button.addEventListener('click', closeDrawer);
    });

    const applyCardPayload = (card, task) => {
        if (!card || !task) return;
        card.querySelector('h4').textContent = task.title || 'Sin título';
        card.querySelector('.kanban-card__project').textContent = task.project || 'Sin proyecto';
        const due = card.querySelector('.due-normal, .due-overdue');
        if (due) {
            due.textContent = task.due_date ? `📅 ${task.due_date}` : '📅 Sin fecha';
            due.className = (task.due_date && task.due_date < new Date().toISOString().slice(0, 10) && normalizeStatus(task.status) !== 'done') ? 'due-overdue' : 'due-normal';
        }
    };

    const refreshColumnCounts = () => {
        board.querySelectorAll('[data-status-column]').forEach((column) => {
            const count = column.querySelectorAll('.kanban-card').length;
            const badge = column.querySelector('[data-column-count]');
            if (badge) badge.textContent = String(count);
        });
    };

    const loadTaskDetail = async (taskId) => {
        const response = await fetch(`/api/tasks/${taskId}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('No se pudo cargar la tarea.');
        return response.json();
    };

    const saveTaskDetail = async (payload) => {
        const body = new URLSearchParams(payload);
        const response = await fetch(`/api/tasks/${payload.task_id}/update`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
        });
        const json = await response.json();
        if (!response.ok || json.status !== 'ok') {
            throw new Error(json.message || 'No se pudo guardar la tarea.');
        }
        return json;
    };

    const updateTaskStatus = async (taskId, status) => {
        const body = new URLSearchParams({ status, redirect_to: '/tasks/kanban' });
        const response = await fetch(`/tasks/${taskId}/status`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });
        const json = await response.json();
        if (!response.ok || json.status !== 'ok') {
            throw new Error(json.message || 'No se pudo mover la tarea.');
        }
        return json;
    };

    board.querySelectorAll('.kanban-card').forEach((card) => {
        card.addEventListener('dragstart', () => {
            draggedCard = card;
            dragStartedAt = Date.now();
            card.classList.add('is-dragging');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            setTimeout(() => {
                draggedCard = null;
            }, 0);
        });
        card.addEventListener('click', async () => {
            if (Date.now() - dragStartedAt < 120) {
                return;
            }
            const taskId = Number(card.dataset.taskId || 0);
            if (taskId <= 0 || !drawer) return;
            drawer.hidden = false;
            drawerMessage.textContent = 'Cargando...';

            try {
                const detail = await loadTaskDetail(taskId);
                const task = detail.task || {};
                document.getElementById('drawer-task-id').value = String(task.id || '');
                document.getElementById('drawer-title').value = task.title || '';
                document.getElementById('drawer-status').value = normalizeStatus(task.status || 'todo');
                document.getElementById('drawer-priority').value = String(task.priority || 'medium').toLowerCase();
                document.getElementById('drawer-estimated-hours').value = String(task.estimated_hours || 0);
                document.getElementById('drawer-due-date').value = task.due_date || '';
                const assigneeSelect = document.getElementById('drawer-assignee-id');
                if (assigneeSelect) {
                    assigneeSelect.value = String(task.assignee_id || 0);
                }
                const scheduleSelect = document.getElementById('drawer-schedule-activity');
                scheduleSelect.innerHTML = '<option value="0">Sin vincular</option>';
                const activities = Array.isArray(detail.schedule_activities) ? detail.schedule_activities : [];
                activities.forEach((activity) => {
                    const option = document.createElement('option');
                    option.value = String(activity.id || 0);
                    option.textContent = activity.name || 'Actividad';
                    scheduleSelect.appendChild(option);
                });
                scheduleSelect.value = String(task.schedule_activity_id || 0);
                drawerMessage.textContent = '';
                drawerForm.dataset.taskCard = String(taskId);
            } catch (error) {
                drawerMessage.textContent = error.message;
            }
        });
    });

    board.querySelectorAll('[data-dropzone]').forEach((dropzone) => {
        dropzone.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropzone.classList.add('is-drop-target');
        });
        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('is-drop-target');
        });
        dropzone.addEventListener('drop', async (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-drop-target');
            if (!draggedCard) return;
            const newStatus = normalizeStatus(dropzone.dataset.dropzone || 'todo');
            const oldStatus = normalizeStatus(draggedCard.dataset.taskStatus || 'todo');
            if (newStatus === oldStatus) return;

            const taskId = Number(draggedCard.dataset.taskId || 0);
            if (taskId <= 0) return;
            const originalParent = draggedCard.parentElement;
            dropzone.appendChild(draggedCard);
            draggedCard.dataset.taskStatus = newStatus;
            refreshColumnCounts();

            try {
                await updateTaskStatus(taskId, newStatus);
            } catch (error) {
                if (originalParent) {
                    originalParent.appendChild(draggedCard);
                    draggedCard.dataset.taskStatus = oldStatus;
                    refreshColumnCounts();
                }
                alert(error.message);
            }
        });
    });

    drawerForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(drawerForm);
        const payload = {};
        formData.forEach((value, key) => {
            payload[key] = String(value);
        });
        drawerMessage.textContent = 'Guardando...';
        try {
            const result = await saveTaskDetail(payload);
            const task = result.task || {};
            const card = board.querySelector(`.kanban-card[data-task-id="${task.id}"]`);
            if (card) {
                const targetStatus = normalizeStatus(task.status || 'todo');
                const targetColumn = board.querySelector(`[data-dropzone="${targetStatus}"]`);
                if (targetColumn && card.parentElement !== targetColumn) {
                    targetColumn.appendChild(card);
                    card.dataset.taskStatus = targetStatus;
                }
                applyCardPayload(card, task);
                refreshColumnCounts();
            }
            drawerMessage.textContent = 'Guardado correctamente.';
        } catch (error) {
            drawerMessage.textContent = error.message;
        }
    });

    const createTaskModal = document.getElementById('create-task-modal');
    board.querySelectorAll('[data-add-task]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!createTaskModal) return;
            const statusInput = document.getElementById('create-task-status');
            if (statusInput) {
                statusInput.value = normalizeStatus(button.dataset.addTask || 'todo');
            }
            createTaskModal.showModal();
        });
    });
    document.querySelector('[data-close-create-task]')?.addEventListener('click', () => {
        createTaskModal?.close();
    });
})();
</script>

<style>
    .kanban-page { display:flex; flex-direction:column; gap:14px; }
    .kanban-page__header { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; }
    .kanban-page__header h2 { margin:0; }
    .section-muted { color: var(--text-secondary); font-size:13px; margin:0; }
    .kanban-filters { border:1px solid var(--border); border-radius:14px; padding:12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; background:var(--surface); }
    .kanban-filters label { display:flex; flex-direction:column; gap:6px; font-size:12px; font-weight:700; color:var(--text-primary); }
    .kanban-filters select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text-primary); }
    .kanban-filters__actions { display:flex; gap:8px; align-items:flex-end; }

    .kanban-board { display:grid; grid-template-columns: repeat(5, minmax(190px, 1fr)); gap:10px; }
    .kanban-column { border:1px solid var(--border); border-radius:12px; padding:10px; background:var(--surface); display:flex; flex-direction:column; gap:8px; min-height:300px; }
    .kanban-column__header { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .kanban-column__header h3 { margin:0; font-size:13px; }
    .kanban-column__cards { display:flex; flex-direction:column; gap:8px; min-height:70px; }
    .kanban-column__cards.is-drop-target { outline:2px dashed var(--primary); border-radius:10px; }
    .kanban-card { border:1px solid var(--border); border-radius:10px; padding:10px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); display:flex; flex-direction:column; gap:6px; cursor:pointer; }
    .kanban-card.is-dragging { opacity:0.55; }
    .kanban-card h4 { margin:0; font-size:13px; }
    .kanban-card__project { margin:0; font-size:12px; color: var(--text-secondary); }
    .kanban-card__meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:12px; }
    .assignee-avatar { width:22px; height:22px; border-radius:999px; background: color-mix(in srgb, var(--primary) 20%, var(--surface)); display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:var(--primary); }
    .priority-dot { width:10px; height:10px; border-radius:999px; display:inline-block; }
    .due-overdue { color: var(--danger); font-weight:700; }
    .due-normal { color: var(--text-secondary); }
    .subtasks-pill { border:1px solid var(--border); border-radius:999px; padding:2px 6px; color: var(--text-secondary); }
    .count-pill { border:1px solid var(--border); border-radius:999px; padding:2px 8px; font-size:12px; font-weight:700; }
    .kanban-empty { grid-column: 1 / -1; border:1px dashed var(--border); border-radius:12px; padding:18px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); }
    .kanban-empty h3 { margin:0 0 4px 0; }
    .kanban-empty p { margin:0; color:var(--text-secondary); }

    .task-drawer[hidden] { display:none; }
    .task-drawer { position:fixed; inset:0; z-index:70; display:flex; justify-content:flex-end; }
    .task-drawer__backdrop { position:absolute; inset:0; background: color-mix(in srgb, var(--text-primary) 35%, transparent); }
    .task-drawer__panel { position:relative; width:min(420px, 100%); height:100%; background:var(--surface); border-left:1px solid var(--border); padding:14px; display:flex; flex-direction:column; gap:10px; overflow:auto; }
    .task-drawer__header { display:flex; justify-content:space-between; gap:8px; align-items:center; }
    .task-drawer__header h3 { margin:0; }
    .task-drawer__body form { display:flex; flex-direction:column; gap:10px; }
    .task-drawer__body label { display:flex; flex-direction:column; gap:6px; font-size:12px; font-weight:700; color:var(--text-primary); }
    .task-drawer__body input,
    .task-drawer__body select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text-primary); }
    .task-drawer__actions { display:flex; gap:8px; justify-content:flex-end; }

    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.small { padding:5px 8px; font-size:12px; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }

    #create-task-modal form { display:flex; flex-direction:column; gap:10px; min-width:min(520px, 90vw); }
    #create-task-modal label { display:flex; flex-direction:column; gap:6px; font-size:12px; font-weight:700; color:var(--text-primary); }
    #create-task-modal input,
    #create-task-modal select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text-primary); }

    @media (max-width: 1200px) {
        .kanban-board { grid-template-columns: repeat(3, minmax(190px, 1fr)); }
    }
    @media (max-width: 900px) {
        .kanban-board { grid-template-columns: 1fr; }
    }
</style>
