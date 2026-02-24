<?php
$basePath = $basePath ?? '';
$rows = is_array($rows ?? null) ? $rows : [];
$kpis = is_array($kpis ?? null) ? $kpis : [];
$canReport = !empty($canReport);
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
$submittedWeek = false;
foreach ($gridRows as $r) {
    foreach (($r['cells'] ?? []) as $cell) {
        if (($cell['status'] ?? 'draft') !== 'draft') {
            $submittedWeek = true;
            break 2;
        }
    }
}
?>

<section class="timesheets-shell">
    <header class="timesheets-header">
        <div>
            <h2>Timesheets</h2>
            <p class="section-muted">Registro semanal por proyecto (autoguardado en borrador).</p>
        </div>
        <div class="header-actions">
            <form method="GET" class="week-selector">
                <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <button type="submit" class="secondary-button small">Ver semana</button>
            </form>
            <div class="week-pill <?= $submittedWeek ? 'submitted' : '' ?>">
                Semana <?= htmlspecialchars($weekStart->format('d/m')) ?> - <?= htmlspecialchars($weekEnd->format('d/m')) ?>
            </div>
        </div>
    </header>

    <div class="kpi-grid">
        <div class="card kpi"><span class="label">📝 Borrador</span><span class="value"><?= $kpis['draft'] ?? 0 ?></span></div>
        <div class="card kpi"><span class="label">⏳ Enviado</span><span class="value"><?= $kpis['pending'] ?? 0 ?></span></div>
        <div class="card kpi"><span class="label">✅ Aprobado</span><span class="value"><?= $kpis['approved'] ?? 0 ?></span></div>
        <div class="card kpi"><span class="label">❌ Rechazado</span><span class="value"><?= $kpis['rejected'] ?? 0 ?></span></div>
    </div>

    <?php if ($canReport): ?>
    <section class="card">
        <header class="grid-header">
            <h3>Carga semanal</h3>
            <div class="actions-inline">
                <?php if ($weeklyCapacity > 0 && $weekTotal > $weeklyCapacity): ?>
                    <span class="warn">⚠️ Supera capacidad semanal (<?= htmlspecialchars((string) $weeklyCapacity) ?>h)</span>
                <?php endif; ?>
                <form method="POST" action="<?= $basePath ?>/timesheets/submit-week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <button type="submit" class="primary-button" <?= $submittedWeek ? 'disabled' : '' ?>>Enviar semana</button>
                </form>
            </div>
        </header>

        <?php if (empty($gridRows)): ?>
            <p class="section-muted">No hay proyectos activos asignados para registrar horas.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="clean-table week-grid">
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <?php foreach ($gridDays as $day): ?>
                                <th><?= htmlspecialchars($day['label']) ?><br><small><?= htmlspecialchars($day['number']) ?></small></th>
                            <?php endforeach; ?>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gridRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($row['project'] ?? '')) ?></td>
                                <?php foreach ($gridDays as $day): $date=$day['key']; $cell=$row['cells'][$date] ?? ['hours'=>0,'status'=>'draft','comment'=>'']; $hours=(float)($cell['hours']??0); ?>
                                    <td class="cell <?= $hours > 8 ? 'overload' : '' ?> <?= (($cell['status'] ?? 'draft') !== 'draft') ? 'locked' : '' ?>" title="<?= htmlspecialchars((string) ($cell['comment'] ?? '')) ?>">
                                        <input
                                            type="number"
                                            step="0.25"
                                            min="0"
                                            max="24"
                                            value="<?= htmlspecialchars(rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.')) ?>"
                                            data-date="<?= htmlspecialchars($date) ?>"
                                            data-project="<?= (int) ($row['project_id'] ?? 0) ?>"
                                            data-comment="<?= htmlspecialchars((string) ($cell['comment'] ?? ''), ENT_QUOTES) ?>"
                                            class="hour-input"
                                            <?= (($cell['status'] ?? 'draft') !== 'draft') ? 'disabled' : '' ?>
                                        >
                                        <button type="button" class="comment-btn" data-comment-edit title="Editar comentario" <?= (($cell['status'] ?? 'draft') !== 'draft') ? 'disabled' : '' ?>>💬</button>
                                    </td>
                                <?php endforeach; ?>
                                <td><strong><?= htmlspecialchars((string) round((float) ($row['total'] ?? 0), 2)) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td><strong>TOTAL DÍA</strong></td>
                            <?php foreach ($gridDays as $day): $total=(float)($dayTotals[$day['key']] ?? 0); ?>
                                <td><strong><?= htmlspecialchars((string) round($total, 2)) ?></strong></td>
                            <?php endforeach; ?>
                            <td><strong><?= htmlspecialchars((string) round($weekTotal, 2)) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php if ($requiresFullReport): ?><p class="section-muted">* Requiere reporte completo: no puedes enviar semanas con días vacíos.</p><?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</section>

<dialog id="comment-modal">
    <form method="dialog" class="comment-form">
        <h4>Comentario de celda</h4>
        <textarea id="comment-text" rows="4" placeholder="Detalle de lo realizado"></textarea>
        <menu>
            <button value="cancel" class="secondary-button small">Cancelar</button>
            <button value="save" class="primary-button small">Guardar</button>
        </menu>
    </form>
</dialog>

<style>
.timesheets-shell{display:flex;flex-direction:column;gap:16px}.timesheets-header{display:flex;justify-content:space-between;flex-wrap:wrap}.header-actions{display:flex;gap:10px;align-items:center}.week-pill.submitted{background:#dbeafe}.table-wrap{overflow:auto}.week-grid .cell{min-width:90px}.hour-input{width:54px;padding:4px;border:1px solid var(--border);border-radius:8px}.comment-btn{border:0;background:transparent;cursor:pointer}.cell.overload{background:#fef9c3}.cell.locked{background:#e0f2fe}.total-row{background:color-mix(in srgb,var(--surface) 80%, var(--background) 20%)}.warn{color:#b45309;font-weight:700}.actions-inline{display:flex;gap:10px;align-items:center}.section-muted{color:var(--text-secondary)}
</style>

<script>
(() => {
  const modal = document.getElementById('comment-modal');
  const commentText = document.getElementById('comment-text');
  let currentInput = null;

  async function saveCell(input) {
    const td = input.closest('td');
    const hours = Number(input.value || 0);
    if (hours > 24) { alert('No puedes registrar más de 24 horas por día.'); return; }
    const payload = new URLSearchParams({
      project_id: input.dataset.project,
      date: input.dataset.date,
      hours: String(hours),
      comment: input.dataset.comment || ''
    });
    const res = await fetch('<?= $basePath ?>/timesheets/cell', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload });
    const data = await res.json();
    if (!res.ok || !data.ok) { alert(data.message || 'No se pudo guardar.'); return; }
    td.classList.add('saved');
    setTimeout(() => td.classList.remove('saved'), 600);
  }

  document.querySelectorAll('.hour-input').forEach(input => {
    input.addEventListener('change', () => { saveCell(input); });
  });

  document.querySelectorAll('[data-comment-edit]').forEach(btn => {
    btn.addEventListener('click', () => {
      currentInput = btn.closest('td')?.querySelector('.hour-input');
      if (!currentInput || !modal) return;
      commentText.value = currentInput.dataset.comment || '';
      modal.showModal();
    });
  });

  modal?.addEventListener('close', () => {
    if (modal.returnValue === 'save' && currentInput) {
      currentInput.dataset.comment = commentText.value;
      saveCell(currentInput);
    }
  });
})();
</script>
