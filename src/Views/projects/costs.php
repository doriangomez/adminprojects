<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$budget = (float) ($project['budget'] ?? 0);
$actualCost = (float) ($project['actual_cost'] ?? 0);
$timesheetHours = round((float) ($timesheetHours ?? 0.0), 2);
$manualHoursTotal = round((float) ($manualHoursTotal ?? 0.0), 2);
$realHoursTotal = round((float) ($realHoursTotal ?? ($timesheetHours + $manualHoursTotal)), 2);
$manualHours = is_array($manualHours ?? null) ? $manualHours : [];
$manualHoursCanManage = !empty($manualHoursCanManage);
$projectId = (int) ($project['id'] ?? 0);
$feedbackError = trim((string) ($_GET['error'] ?? ''));
$feedbackMessage = '';
if (isset($_GET['manual_hours_saved'])) {
    $feedbackMessage = 'Horas manuales registradas correctamente.';
} elseif (isset($_GET['manual_hours_updated'])) {
    $feedbackMessage = 'Registro manual actualizado correctamente.';
} elseif (isset($_GET['manual_hours_deleted'])) {
    $feedbackMessage = 'Registro manual eliminado correctamente.';
}
$diff = $budget - $actualCost;
$diffLabel = $diff >= 0 ? 'A favor' : 'Sobrecosto';
?>

<section class="project-shell">
    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Costos</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <small class="section-muted">Seguimiento financiero para control operativo.</small>
        </div>
        <div class="project-actions">
            <a class="action-btn" href="<?= $basePath ?>/projects/<?= $projectId ?>?view=resumen">Volver al resumen</a>
        </div>
    </header>

    <?php
    $activeTab = 'costos';
    require __DIR__ . '/_tabs.php';
    ?>

    <section class="costs-grid">
        <article class="cost-card">
            <p class="section-label">Presupuesto</p>
            <strong>$<?= number_format($budget, 0, ',', '.') ?></strong>
            <span class="cost-hint">Total planificado</span>
        </article>
        <article class="cost-card">
            <p class="section-label">Costo real</p>
            <strong>$<?= number_format($actualCost, 0, ',', '.') ?></strong>
            <span class="cost-hint">Ejecutado a la fecha</span>
        </article>
        <article class="cost-card <?= $diff >= 0 ? 'is-positive' : 'is-negative' ?>">
            <p class="section-label"><?= $diffLabel ?></p>
            <strong>$<?= number_format($diff, 0, ',', '.') ?></strong>
            <span class="cost-hint">Diferencia vs presupuesto</span>
        </article>
    </section>

    <?php if ($feedbackMessage !== ''): ?>
        <div class="feedback-box is-success"><?= htmlspecialchars($feedbackMessage) ?></div>
    <?php endif; ?>
    <?php if ($feedbackError !== ''): ?>
        <div class="feedback-box is-error"><?= htmlspecialchars($feedbackError) ?></div>
    <?php endif; ?>

    <section class="hours-panel">
        <header class="hours-panel__header">
            <div>
                <p class="section-label">Horas reales</p>
                <h3>Total consolidado: <?= number_format($realHoursTotal, 2, ',', '.') ?> h</h3>
                <small class="section-muted">Horas por talento + horas manuales PM</small>
            </div>
            <?php if ($manualHoursCanManage): ?>
                <button type="button" class="action-btn action-btn-primary" data-open-manual-modal>
                    Registrar horas manuales
                </button>
            <?php endif; ?>
        </header>

        <div class="hours-breakdown-grid">
            <article class="cost-card">
                <p class="section-label">Horas por talento (timesheet)</p>
                <strong><?= number_format($timesheetHours, 2, ',', '.') ?> h</strong>
                <span class="cost-hint">Se conserva la lógica actual de timesheet</span>
            </article>
            <article class="cost-card">
                <p class="section-label">Horas manuales PM</p>
                <strong><?= number_format($manualHoursTotal, 2, ',', '.') ?> h</strong>
                <span class="cost-hint">Complemento para horas no reportadas</span>
            </article>
        </div>

        <div class="manual-hours-list">
            <div class="manual-hours-list__header">
                <h4>Registros manuales</h4>
                <span><?= count($manualHours) ?> registro(s)</span>
            </div>
            <?php if ($manualHours === []): ?>
                <p class="section-muted">Aún no hay horas manuales registradas.</p>
            <?php else: ?>
                <table class="manual-hours-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Horas</th>
                            <th>Descripción</th>
                            <th>Responsable</th>
                            <?php if ($manualHoursCanManage): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manualHours as $manualHour): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($manualHour['entry_date'] ?? '')) ?></td>
                                <td><?= number_format((float) ($manualHour['hours'] ?? 0), 2, ',', '.') ?> h</td>
                                <td><?= htmlspecialchars((string) ($manualHour['description'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($manualHour['responsible_name'] ?? '—')) ?></td>
                                <?php if ($manualHoursCanManage): ?>
                                    <td class="table-actions">
                                        <button
                                            type="button"
                                            class="action-btn action-btn-small"
                                            data-edit-manual-hour
                                            data-id="<?= (int) ($manualHour['id'] ?? 0) ?>"
                                            data-date="<?= htmlspecialchars((string) ($manualHour['entry_date'] ?? '')) ?>"
                                            data-hours="<?= htmlspecialchars((string) ($manualHour['hours'] ?? '0')) ?>"
                                            data-description="<?= htmlspecialchars((string) ($manualHour['description'] ?? '')) ?>"
                                            data-responsible="<?= htmlspecialchars((string) ($manualHour['responsible_name'] ?? '')) ?>"
                                        >
                                            Editar
                                        </button>
                                        <form method="post" action="<?= $basePath ?>/projects/<?= $projectId ?>/costs/manual-hours/<?= (int) ($manualHour['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar este registro manual de horas?');">
                                            <button type="submit" class="action-btn action-btn-small action-btn-danger">Eliminar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($manualHoursCanManage): ?>
        <div class="modal-overlay" data-manual-modal hidden>
            <div class="modal-card">
                <header class="modal-card__header">
                    <h4 data-modal-title>Registrar horas manuales</h4>
                    <button type="button" class="action-btn action-btn-small" data-close-manual-modal>Cerrar</button>
                </header>
                <form method="post" action="<?= $basePath ?>/projects/<?= $projectId ?>/costs/manual-hours" data-manual-form>
                    <div class="modal-form-grid">
                        <label>
                            Fecha
                            <input type="date" name="entry_date" required />
                        </label>
                        <label>
                            Cantidad de horas
                            <input type="number" name="hours" min="0.01" max="24" step="0.25" required />
                        </label>
                        <label class="field-full">
                            Descripción breve
                            <input type="text" name="description" maxlength="255" required />
                        </label>
                        <label class="field-full">
                            Responsable (opcional)
                            <input type="text" name="responsible_name" maxlength="140" />
                        </label>
                    </div>
                    <footer class="modal-card__footer">
                        <button type="submit" class="action-btn action-btn-primary">Guardar</button>
                    </footer>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>Recomendación</strong>
        <p>Registra tiempos y gastos desde timesheets para mantener la trazabilidad financiera.</p>
    </div>
</section>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .project-title-block { display:flex; flex-direction:column; gap:6px; }
    .project-title-block h2 { margin:0; color: var(--text-primary); }
    .project-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn-primary { background: var(--primary); border-color: var(--primary); color: #fff; }
    .action-btn-small { padding:6px 8px; font-size:12px; }
    .action-btn-danger { border-color: var(--danger); color: var(--danger); }

    .costs-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    .cost-card { border:1px solid var(--border); padding:14px; border-radius:14px; background: var(--surface); display:flex; flex-direction:column; gap:6px; }
    .cost-card strong { font-size:20px; color: var(--text-primary); }
    .cost-card.is-positive strong { color: var(--success); }
    .cost-card.is-negative strong { color: var(--danger); }
    .cost-hint { font-size:12px; color: var(--text-secondary); }

    .feedback-box { border-radius:10px; border:1px solid var(--border); padding:10px 12px; font-size:14px; }
    .feedback-box.is-success { border-color: color-mix(in srgb, var(--success) 50%, var(--border)); background: color-mix(in srgb, var(--success) 12%, var(--background)); }
    .feedback-box.is-error { border-color: color-mix(in srgb, var(--danger) 50%, var(--border)); background: color-mix(in srgb, var(--danger) 12%, var(--background)); }

    .hours-panel { display:flex; flex-direction:column; gap:14px; border:1px solid var(--border); border-radius:14px; padding:14px; background: var(--surface); }
    .hours-panel__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
    .hours-panel__header h3 { margin:2px 0 0 0; }
    .hours-breakdown-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    .manual-hours-list { border-top:1px solid var(--border); padding-top:12px; }
    .manual-hours-list__header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
    .manual-hours-list__header h4 { margin:0; }
    .manual-hours-table { width:100%; border-collapse:collapse; }
    .manual-hours-table th, .manual-hours-table td { text-align:left; border-bottom:1px solid var(--border); padding:8px; vertical-align:top; }
    .table-actions { display:flex; gap:8px; flex-wrap:wrap; }

    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; padding:16px; z-index:30; }
    .modal-overlay[hidden] { display:none !important; }
    .modal-card { width:min(560px, 100%); background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:14px; display:flex; flex-direction:column; gap:12px; }
    .modal-card__header { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .modal-card__header h4 { margin:0; }
    .modal-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
    .modal-form-grid label { display:flex; flex-direction:column; gap:6px; font-size:13px; }
    .modal-form-grid input { border:1px solid var(--border); border-radius:8px; padding:8px; background:var(--background); color:var(--text-primary); }
    .field-full { grid-column:1 / -1; }
    .modal-card__footer { display:flex; justify-content:flex-end; }

    @media (max-width: 720px) {
        .modal-form-grid { grid-template-columns: 1fr; }
    }

    body.modal-open { overflow: hidden; }
    .info-box { border:1px solid var(--border); border-radius:12px; padding:12px; background: color-mix(in srgb, var(--primary) 10%, var(--background)); }
    .info-box p { margin:6px 0 0 0; color: var(--text-secondary); }
</style>

<?php if ($manualHoursCanManage): ?>
<script>
(() => {
    const modal = document.querySelector('[data-manual-modal]');
    const openBtn = document.querySelector('[data-open-manual-modal]');
    const closeBtn = document.querySelector('[data-close-manual-modal]');
    const form = document.querySelector('[data-manual-form]');
    const title = document.querySelector('[data-modal-title]');
    const editButtons = Array.from(document.querySelectorAll('[data-edit-manual-hour]'));
    if (!modal || !openBtn || !closeBtn || !form || !title) {
        return;
    }

    const projectId = <?= $projectId ?>;
    const baseAction = '<?= $basePath ?>/projects/' + projectId + '/costs/manual-hours';

    const setCreateMode = () => {
        title.textContent = 'Registrar horas manuales';
        form.action = baseAction;
        form.reset();
        const today = new Date().toISOString().slice(0, 10);
        const dateInput = form.querySelector('input[name="entry_date"]');
        if (dateInput) {
            dateInput.value = today;
        }
    };

    const setModalState = (isOpen) => {
        modal.hidden = !isOpen;
        modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        document.body.classList.toggle('modal-open', isOpen);
    };

    const openModal = () => {
        setModalState(true);
    };

    const closeModal = () => {
        setModalState(false);
    };

    openBtn.addEventListener('click', () => {
        setCreateMode();
        openModal();
    });

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    editButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id') || '';
            const date = button.getAttribute('data-date') || '';
            const hours = button.getAttribute('data-hours') || '';
            const description = button.getAttribute('data-description') || '';
            const responsible = button.getAttribute('data-responsible') || '';

            title.textContent = 'Editar horas manuales';
            form.action = baseAction + '/' + id + '/update';
            const dateInput = form.querySelector('input[name="entry_date"]');
            const hoursInput = form.querySelector('input[name="hours"]');
            const descriptionInput = form.querySelector('input[name="description"]');
            const responsibleInput = form.querySelector('input[name="responsible_name"]');
            if (dateInput) dateInput.value = date;
            if (hoursInput) hoursInput.value = hours;
            if (descriptionInput) descriptionInput.value = description;
            if (responsibleInput) responsibleInput.value = responsible;
            openModal();
        });
    });
    // Estado inicial seguro: modal cerrado y sin overlay bloqueando interacción.
    setModalState(false);
})();
</script>
<?php endif; ?>
