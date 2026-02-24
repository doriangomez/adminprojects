<?php
$basePath = $basePath ?? '';
$rows = is_array($rows ?? null) ? $rows : [];
$kpis = is_array($kpis ?? null) ? $kpis : [];
$canReport = !empty($canReport);
$canApprove = !empty($canApprove);
$canManageWorkflow = !empty($canManageWorkflow);
$canDeleteWeek = !empty($canDeleteWeek);
$weekStart = $weekStart ?? new DateTimeImmutable('monday this week');
$weekEnd = $weekEnd ?? $weekStart->modify('+6 days');
$weekValue = $weekValue ?? $weekStart->format('o-\\WW');
$weeklyGrid = is_array($weeklyGrid ?? null) ? $weeklyGrid : [];
$gridDays = is_array($weeklyGrid['days'] ?? null) ? $weeklyGrid['days'] : [];
$gridRows = is_array($weeklyGrid['rows'] ?? null) ? $weeklyGrid['rows'] : [];
$dayTotals = is_array($weeklyGrid['day_totals'] ?? null) ? $weeklyGrid['day_totals'] : [];
$weekTotal = (float) ($weeklyGrid['week_total'] ?? 0);
$weeklyCapacity = (float) ($weeklyGrid['weekly_capacity'] ?? 0);
$requiresFullReport = !empty($weeklyGrid['requires_full_report']);
$weeksHistory = is_array($weeksHistory ?? null) ? $weeksHistory : [];
$selectedWeekSummary = is_array($selectedWeekSummary ?? null) ? $selectedWeekSummary : [];
$weekHistoryLog = is_array($weekHistoryLog ?? null) ? $weekHistoryLog : [];
$monthlySummary = is_array($monthlySummary ?? null) ? $monthlySummary : [];

$statusMap = [
    'approved' => ['label' => 'Aprobada', 'class' => 'approved'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'rejected'],
    'submitted' => ['label' => 'Enviada', 'class' => 'submitted'],
    'draft' => ['label' => 'Borrador', 'class' => 'draft'],
    'partial' => ['label' => 'Parcial', 'class' => 'partial'],
];

$selectedStatus = (string) ($selectedWeekSummary['status'] ?? 'draft');
if (!isset($statusMap[$selectedStatus])) {
    $selectedStatus = 'draft';
}
$selectedMeta = $statusMap[$selectedStatus];
?>

<section class="timesheets-shell">
    <header class="timesheets-header card">
        <div>
            <h2>Timesheets</h2>
            <p class="section-muted">Gestión semanal profesional con historial completo, trazabilidad y control operativo.</p>
        </div>
        <div class="header-actions">
            <form method="GET" class="week-selector">
                <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <button type="submit" class="secondary-button small">Ver semana</button>
            </form>
            <div class="view-toggle">
                <button type="button" class="secondary-button small" data-view="table">Vista Tabla</button>
                <button type="button" class="secondary-button small" data-view="calendar">Vista Calendario semanal</button>
            </div>
        </div>
    </header>

    <section class="card weeks-history">
        <header><h3>Historial de semanas</h3></header>
        <?php if (empty($weeksHistory)): ?>
            <p class="section-muted">Semanas registradas: sin información disponible.</p>
        <?php else: ?>
            <div class="weeks-row">
                <?php foreach ($weeksHistory as $week):
                    $start = new DateTimeImmutable((string) ($week['week_start'] ?? 'now'));
                    $end = new DateTimeImmutable((string) ($week['week_end'] ?? 'now'));
                    $statusWeight = (int) ($week['status_weight'] ?? 2);
                    $status = $statusWeight >= 5 ? 'approved' : ($statusWeight >= 4 ? 'rejected' : ($statusWeight >= 3 ? 'submitted' : 'draft'));
                    $isCurrent = $start->format('Y-m-d') === $weekStart->format('Y-m-d');
                    $meta = $statusMap[$status] ?? $statusMap['draft'];
                ?>
                    <a class="week-card <?= $meta['class'] ?> <?= $isCurrent ? 'active' : '' ?>" href="<?= $basePath ?>/timesheets?week=<?= urlencode($start->format('o-\\WW')) ?>">
                        <strong>Semana <?= htmlspecialchars($start->format('W')) ?></strong>
                        <span><?= htmlspecialchars($start->format('d/m')) ?> - <?= htmlspecialchars($end->format('d/m')) ?></span>
                        <span><?= htmlspecialchars((string) round((float) ($week['total_hours'] ?? 0), 2)) ?>h</span>
                        <span class="badge-state"><?= htmlspecialchars($meta['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="timesheet-layout">
        <section class="main-column">
            <section class="card week-summary <?= $selectedMeta['class'] ?>">
                <h3>Semana <?= htmlspecialchars($weekStart->format('W')) ?> (<?= htmlspecialchars($weekStart->format('d/m')) ?> – <?= htmlspecialchars($weekEnd->format('d/m')) ?>)</h3>
                <p><strong>Estado actual:</strong> <?= htmlspecialchars($selectedMeta['label']) ?></p>
                <p><strong>Total horas:</strong> <?= htmlspecialchars((string) round($weekTotal, 2)) ?></p>
                <p><strong>Aprobado por:</strong> <?= htmlspecialchars((string) ($selectedWeekSummary['approver_name'] ?? '—')) ?></p>
                <p><strong>Fecha aprobación:</strong> <?= htmlspecialchars((string) ($selectedWeekSummary['approved_at'] ?? '—')) ?></p>
                <?php if (!empty($selectedWeekSummary['approval_comment'])): ?>
                    <p><strong>Motivo de rechazo / comentario:</strong> <?= htmlspecialchars((string) $selectedWeekSummary['approval_comment']) ?></p>
                <?php endif; ?>

                <div class="actions-inline wrap">
                    <?php if ($selectedStatus === 'draft'): ?>
                        <form method="POST" action="<?= $basePath ?>/timesheets/submit-week">
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                            <button type="submit" class="primary-button">Enviar semana</button>
                        </form>
                        <?php if ($canDeleteWeek): ?>
                            <button type="button" class="danger-button" data-open-delete>Eliminar semana</button>
                        <?php endif; ?>
                    <?php elseif ($selectedStatus === 'submitted'): ?>
                        <?php if ($canApprove): ?>
                            <form method="POST" action="<?= $basePath ?>/timesheets/approve-week">
                                <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="primary-button">Aprobar semana</button>
                            </form>
                            <form method="POST" action="<?= $basePath ?>/timesheets/approve-week" class="inline-form">
                                <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                                <input type="hidden" name="status" value="rejected">
                                <input type="text" name="comment" placeholder="Motivo rechazo" required>
                                <button type="submit" class="danger-button">Rechazar semana</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= $basePath ?>/timesheets/cancel-week">
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                            <button type="submit" class="secondary-button">Cancelar envío</button>
                        </form>
                    <?php elseif ($selectedStatus === 'approved'): ?>
                        <?php if ($canManageWorkflow): ?>
                            <form method="POST" action="<?= $basePath ?>/timesheets/reopen-own-week" class="inline-form">
                                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                <input type="text" name="comment" placeholder="Comentario reapertura (opcional)">
                                <button type="submit" class="secondary-button">Reabrir semana</button>
                            </form>
                        <?php endif; ?>
                        <button type="button" class="secondary-button" data-open-history>Ver detalle histórico</button>
                        <button type="button" class="secondary-button" onclick="window.print()">Descargar resumen</button>
                    <?php elseif ($selectedStatus === 'rejected'): ?>
                        <form method="POST" action="<?= $basePath ?>/timesheets/submit-week">
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                            <button type="submit" class="primary-button">Reenviar semana</button>
                        </form>
                        <button type="button" class="secondary-button" data-open-history>Ver motivo de rechazo</button>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($canReport): ?>
            <section class="card" id="table-view">
                <header class="grid-header">
                    <h3>Carga semanal</h3>
                    <?php if ($weeklyCapacity > 0 && $weekTotal > $weeklyCapacity): ?>
                        <span class="warn">⚠️ Supera capacidad semanal (<?= htmlspecialchars((string) $weeklyCapacity) ?>h)</span>
                    <?php endif; ?>
                </header>
                <?php if (empty($gridRows)): ?>
                    <p class="section-muted">No hay proyectos activos asignados para registrar horas.</p>
                <?php else: ?>
                    <div class="table-wrap"><table class="clean-table week-grid"><thead><tr><th>Proyecto</th><?php foreach ($gridDays as $day): ?><th><?= htmlspecialchars($day['label']) ?><br><small><?= htmlspecialchars($day['number']) ?></small></th><?php endforeach; ?><th>Total</th></tr></thead><tbody>
                    <?php foreach ($gridRows as $row): ?><tr><td><?= htmlspecialchars((string) ($row['project'] ?? '')) ?></td><?php foreach ($gridDays as $day): $date=$day['key']; $cell=$row['cells'][$date] ?? ['hours'=>0,'status'=>'draft','comment'=>'']; $hours=(float)($cell['hours']??0); ?><td class="cell <?= $hours > 8 ? 'overload' : '' ?> <?= (($cell['status'] ?? 'draft') !== 'draft') ? 'locked' : '' ?>"><input type="number" step="0.25" min="0" max="24" value="<?= htmlspecialchars(rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.')) ?>" data-date="<?= htmlspecialchars($date) ?>" data-project="<?= (int) ($row['project_id'] ?? 0) ?>" data-comment="<?= htmlspecialchars((string) ($cell['comment'] ?? ''), ENT_QUOTES) ?>" class="hour-input" <?= (($cell['status'] ?? 'draft') !== 'draft') ? 'disabled' : '' ?>><button type="button" class="comment-btn" data-comment-edit <?= (($cell['status'] ?? 'draft') !== 'draft') ? 'disabled' : '' ?>>💬</button></td><?php endforeach; ?><td><strong><?= htmlspecialchars((string) round((float) ($row['total'] ?? 0), 2)) ?></strong></td></tr><?php endforeach; ?>
                    <tr class="total-row"><td><strong>TOTAL DÍA</strong></td><?php foreach ($gridDays as $day): $total=(float)($dayTotals[$day['key']] ?? 0); ?><td><strong><?= htmlspecialchars((string) round($total, 2)) ?></strong></td><?php endforeach; ?><td><strong><?= htmlspecialchars((string) round($weekTotal, 2)) ?></strong></td></tr>
                    </tbody></table></div>
                    <?php if ($requiresFullReport): ?><p class="section-muted">* Requiere reporte completo: no puedes enviar semanas con días vacíos.</p><?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="card hidden" id="calendar-view">
                <h3>Vista Calendario semanal</h3>
                <div class="calendar-grid">
                    <?php foreach ($gridDays as $day): $total=(float)($dayTotals[$day['key']] ?? 0); ?>
                        <article class="calendar-day">
                            <header><?= htmlspecialchars($day['label']) ?> <small><?= htmlspecialchars($day['number']) ?></small></header>
                            <p>Horas: <strong><?= htmlspecialchars((string) round($total, 2)) ?></strong></p>
                            <p>Estado día: <span class="badge-state draft">Editable</span></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="card" id="history-panel" hidden>
                <h3>Ver historial de cambios</h3>
                <?php if (empty($weekHistoryLog)): ?>
                    <p class="section-muted">Sin eventos registrados para esta semana.</p>
                <?php else: ?>
                    <ul class="history-list">
                        <?php foreach ($weekHistoryLog as $event): ?>
                            <li><strong><?= htmlspecialchars((string) ($event['created_at'] ?? '')) ?></strong> · <?= htmlspecialchars((string) ($event['action'] ?? '')) ?> · <?= htmlspecialchars((string) ($event['actor_name'] ?? 'Sistema')) ?> · <?= htmlspecialchars((string) ($event['action_comment'] ?? 'Sin comentario')) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </section>

        <aside class="card side-column">
            <h3>Resumen del mes actual</h3>
            <ul class="summary-list">
                <li>Total horas mes: <strong><?= htmlspecialchars((string) round((float) ($monthlySummary['month_total'] ?? 0), 2)) ?></strong></li>
                <li>Horas aprobadas: <strong><?= htmlspecialchars((string) round((float) ($monthlySummary['approved'] ?? 0), 2)) ?></strong></li>
                <li>Horas rechazadas: <strong><?= htmlspecialchars((string) round((float) ($monthlySummary['rejected'] ?? 0), 2)) ?></strong></li>
                <li>Horas en borrador: <strong><?= htmlspecialchars((string) round((float) ($monthlySummary['draft'] ?? 0), 2)) ?></strong></li>
                <li>Capacidad mes: <strong><?= htmlspecialchars((string) round((float) ($monthlySummary['capacity'] ?? 0), 2)) ?></strong></li>
                <li>Porcentaje cumplimiento: <strong><?= htmlspecialchars((string) ($monthlySummary['compliance'] ?? 0)) ?>%</strong></li>
            </ul>
        </aside>
    </div>
</section>

<dialog id="comment-modal"><form method="dialog" class="comment-form"><h4>Comentario de celda</h4><textarea id="comment-text" rows="4" placeholder="Detalle de lo realizado"></textarea><menu><button value="cancel" class="secondary-button small">Cancelar</button><button value="save" class="primary-button small">Guardar</button></menu></form></dialog>

<dialog id="delete-week-modal">
    <form method="POST" action="<?= $basePath ?>/timesheets/delete-week" class="comment-form">
        <h4>Eliminar semana completa</h4>
        <p>Advertencia: esta acción eliminará todas las horas de la semana seleccionada.</p>
        <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
        <label>Confirma escribiendo <strong>ELIMINAR</strong></label>
        <input type="text" name="confirm_token" required>
        <input type="text" name="comment" placeholder="Motivo (opcional)">
        <menu><button type="button" class="secondary-button small" data-close-delete>Cancelar</button><button type="submit" class="danger-button small">Confirmar eliminación</button></menu>
    </form>
</dialog>

<style>
.timesheets-shell{display:flex;flex-direction:column;gap:16px}.timesheet-layout{display:grid;grid-template-columns:2fr 1fr;gap:16px}.main-column{display:flex;flex-direction:column;gap:16px}.side-column{height:max-content}.weeks-row{display:flex;gap:10px;overflow:auto;padding-bottom:6px}.week-card{min-width:170px;display:flex;flex-direction:column;gap:4px;border:1px solid var(--border);border-radius:12px;padding:10px;text-decoration:none;color:inherit}.week-card.active{outline:2px solid var(--accent)}.week-card.approved{border-left:6px solid #16a34a}.week-card.rejected{border-left:6px solid #dc2626}.week-card.submitted{border-left:6px solid #2563eb}.week-card.draft{border-left:6px solid #6b7280}.week-card.partial{border-left:6px solid #eab308}.week-summary.approved{border-top:4px solid #16a34a}.week-summary.rejected{border-top:4px solid #dc2626}.week-summary.submitted{border-top:4px solid #2563eb}.week-summary.draft{border-top:4px solid #6b7280}.week-summary.partial{border-top:4px solid #eab308}.badge-state{font-size:12px;font-weight:700}.calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(110px,1fr));gap:10px}.calendar-day{border:1px solid var(--border);border-radius:10px;padding:8px}.hidden{display:none}.history-list,.summary-list{margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px}.timesheets-header{display:flex;justify-content:space-between;flex-wrap:wrap}.header-actions{display:flex;gap:10px;align-items:center}.view-toggle{display:flex;gap:6px}.table-wrap{overflow:auto}.week-grid .cell{min-width:90px}.hour-input{width:54px;padding:4px;border:1px solid var(--border);border-radius:8px}.comment-btn{border:0;background:transparent;cursor:pointer}.cell.overload{background:#fef9c3}.cell.locked{background:#e0f2fe}.total-row{background:color-mix(in srgb,var(--surface) 80%, var(--background) 20%)}.warn{color:#b45309;font-weight:700}.actions-inline{display:flex;gap:10px;align-items:center}.actions-inline.wrap{flex-wrap:wrap}.section-muted{color:var(--text-secondary)}
</style>

<script>
(() => {
  const modal = document.getElementById('comment-modal');
  const deleteModal = document.getElementById('delete-week-modal');
  const commentText = document.getElementById('comment-text');
  let currentInput = null;

  async function saveCell(input) {
    const hours = Number(input.value || 0);
    if (hours > 24) { alert('No puedes registrar más de 24 horas por día.'); return; }
    const payload = new URLSearchParams({ project_id: input.dataset.project, date: input.dataset.date, hours: String(hours), comment: input.dataset.comment || '' });
    const res = await fetch('<?= $basePath ?>/timesheets/cell', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload });
    const data = await res.json();
    if (!res.ok || !data.ok) { alert(data.message || 'No se pudo guardar.'); }
  }

  document.querySelectorAll('.hour-input').forEach(input => input.addEventListener('change', () => saveCell(input)));
  document.querySelectorAll('[data-comment-edit]').forEach(btn => btn.addEventListener('click', () => {
    currentInput = btn.closest('td')?.querySelector('.hour-input');
    if (!currentInput || !modal) return;
    commentText.value = currentInput.dataset.comment || '';
    modal.showModal();
  }));
  modal?.addEventListener('close', () => {
    if (modal.returnValue === 'save' && currentInput) { currentInput.dataset.comment = commentText.value; saveCell(currentInput); }
  });

  const tableView = document.getElementById('table-view');
  const calendarView = document.getElementById('calendar-view');
  document.querySelectorAll('[data-view]').forEach(btn => btn.addEventListener('click', () => {
    const isCalendar = btn.dataset.view === 'calendar';
    tableView?.classList.toggle('hidden', isCalendar);
    calendarView?.classList.toggle('hidden', !isCalendar);
  }));

  const historyPanel = document.getElementById('history-panel');
  document.querySelectorAll('[data-open-history]').forEach(btn => btn.addEventListener('click', () => {
    if (!historyPanel) return;
    historyPanel.hidden = !historyPanel.hidden;
    historyPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }));

  document.querySelector('[data-open-delete]')?.addEventListener('click', () => deleteModal?.showModal());
  document.querySelector('[data-close-delete]')?.addEventListener('click', () => deleteModal?.close());
})();
</script>
