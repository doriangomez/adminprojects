<?php
$basePath = $basePath ?? '';
$canApprove = !empty($canApprove);
$canManageWorkflow = !empty($canManageWorkflow);
$canDeleteWeek = !empty($canDeleteWeek);
$canManageAdvanced = !empty($canManageAdvanced);
$weekStart = $weekStart ?? new DateTimeImmutable('monday this week');
$weekEnd = $weekEnd ?? $weekStart->modify('+6 days');
$weekValue = $weekValue ?? $weekStart->format('o-\\WW');
$talentFilter = (int) ($talentFilter ?? 0);
$talentOptions = is_array($talentOptions ?? null) ? $talentOptions : [];
$managedWeekEntries = is_array($managedWeekEntries ?? null) ? $managedWeekEntries : [];
$pendingApprovalsByWeek = is_array($pendingApprovalsByWeek ?? null) ? $pendingApprovalsByWeek : [];
$weekApprovalHistory = is_array($weekApprovalHistory ?? null) ? $weekApprovalHistory : [];

$statusMap = [
    'approved' => ['label' => 'Aprobada', 'class' => 'ts-approved'],
    'rejected' => ['label' => 'Rechazada', 'class' => 'ts-rejected'],
    'submitted' => ['label' => 'Enviada', 'class' => 'ts-submitted'],
    'draft' => ['label' => 'Borrador', 'class' => 'ts-draft'],
    'partial' => ['label' => 'Parcial', 'class' => 'ts-partial'],
    'pending' => ['label' => 'Pendiente', 'class' => 'ts-submitted'],
    'pending_approval' => ['label' => 'Pendiente', 'class' => 'ts-submitted'],
];
$prevWeek = $weekStart->modify('-7 days')->format('o-\\WW');
$nextWeek = $weekStart->modify('+7 days')->format('o-\\WW');
?>

<section class="ts-module">
    <nav class="ts-view-tabs">
        <a href="<?= $basePath ?>/timesheets?week=<?= urlencode($weekValue) ?>" class="ts-tab">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            Registro de horas
        </a>
        <a href="<?= $basePath ?>/timesheets/approval?week=<?= urlencode($weekValue) ?>" class="ts-tab active">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 5 6v5c0 5.3 3.4 8.8 7 10 3.6-1.2 7-4.7 7-10V6z"/><path d="m9 12 2 2 4-4"/></svg>
            Aprobación
        </a>
        <a href="<?= $basePath ?>/timesheets/analytics?week=<?= urlencode($weekValue) ?>" class="ts-tab">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg>
            Analítica
        </a>
    </nav>

    <header class="ts-header">
        <div class="ts-header-left">
            <h3 style="margin:0;font-size:16px">Aprobación de horas</h3>
            <div class="ts-week-nav">
                <a href="<?= $basePath ?>/timesheets/approval?week=<?= urlencode($prevWeek) ?>" class="ts-nav-btn">‹</a>
                <form method="GET" action="<?= $basePath ?>/timesheets/approval" class="ts-week-form">
                    <input type="week" name="week" value="<?= htmlspecialchars($weekValue) ?>" class="ts-week-input" onchange="this.form.submit()">
                </form>
                <a href="<?= $basePath ?>/timesheets/approval?week=<?= urlencode($nextWeek) ?>" class="ts-nav-btn">›</a>
            </div>
            <span class="ts-week-range"><?= htmlspecialchars($weekStart->format('d M')) ?> – <?= htmlspecialchars($weekEnd->format('d M, Y')) ?></span>
        </div>
        <div class="ts-header-right">
            <form method="GET" action="<?= $basePath ?>/timesheets/approval" class="ts-inline">
                <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                <select name="talent_id" class="ts-filter-select" onchange="this.form.submit()">
                    <option value="0">Todos los talentos</option>
                    <?php foreach ($talentOptions as $tal): ?>
                    <option value="<?= (int) ($tal['id'] ?? 0) ?>" <?= $talentFilter === (int) ($tal['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($tal['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </header>

    <?php if (!empty($pendingApprovalsByWeek)): ?>
    <section class="ts-approval-section">
        <h3>Semanas pendientes de aprobación</h3>
        <?php foreach ($pendingApprovalsByWeek as $weekGroup): ?>
        <div class="ts-approval-week-card">
            <div class="ts-approval-week-header">
                <strong>Semana <?= htmlspecialchars((string) ($weekGroup['week_label'] ?? '')) ?></strong>
                <span class="ts-total-badge-sm"><?= round((float) ($weekGroup['total_hours'] ?? 0), 1) ?>h</span>
            </div>
            <?php if (!empty($weekGroup['project_summary'])): ?>
            <div class="ts-approval-projects">
                <?php foreach ($weekGroup['project_summary'] as $ps): ?>
                <span class="ts-approval-project-chip"><?= htmlspecialchars((string) ($ps['project'] ?? '')) ?> · <?= round((float) ($ps['hours'] ?? 0), 1) ?>h</span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="ts-approval-actions">
                <form method="POST" action="<?= $basePath ?>/timesheets/approve-week" class="ts-inline">
                    <input type="hidden" name="week_start" value="<?= htmlspecialchars((string) ($weekGroup['week_start'] ?? '')) ?>">
                    <input type="hidden" name="status" value="approved">
                    <button type="submit" class="ts-btn ts-btn-primary ts-btn-sm">Aprobar semana</button>
                </form>
                <form method="POST" action="<?= $basePath ?>/timesheets/approve-week" class="ts-inline">
                    <input type="hidden" name="week_start" value="<?= htmlspecialchars((string) ($weekGroup['week_start'] ?? '')) ?>">
                    <input type="hidden" name="status" value="rejected">
                    <input type="text" name="comment" placeholder="Motivo de rechazo" required class="ts-reject-input">
                    <button type="submit" class="ts-btn ts-btn-danger ts-btn-sm">Rechazar</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="ts-approval-section">
        <h3>Registros de la semana</h3>
        <?php if (empty($managedWeekEntries)): ?>
        <p class="ts-empty-msg">No hay registros para la semana seleccionada.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Talento</th>
                        <th>Proyecto</th>
                        <th>Horas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($managedWeekEntries as $entry): $entryStatus = (string) ($entry['status'] ?? 'draft'); $meta = $statusMap[$entryStatus] ?? $statusMap['draft']; ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($entry['date'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($entry['talent_name'] ?? $entry['user_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($entry['project_name'] ?? '')) ?></td>
                        <td><?= round((float) ($entry['hours'] ?? 0), 2) ?>h</td>
                        <td><span class="ts-status-badge <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label']) ?></span></td>
                        <td class="ts-entry-actions">
                            <?php if ($canApprove && in_array($entryStatus, ['submitted', 'pending', 'pending_approval'], true)): ?>
                                <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int) ($entry['id'] ?? 0) ?>/approve" class="ts-inline">
                                    <button type="submit" class="ts-btn ts-btn-primary ts-btn-sm">Aprobar</button>
                                </form>
                                <form method="POST" action="<?= $basePath ?>/timesheets/<?= (int) ($entry['id'] ?? 0) ?>/reject" class="ts-inline">
                                    <input type="text" name="comment" placeholder="Motivo" required class="ts-reject-input">
                                    <button type="submit" class="ts-btn ts-btn-danger ts-btn-sm">Rechazar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canManageAdvanced): ?>
                                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="ts-inline">
                                    <input type="hidden" name="admin_action" value="update_hours">
                                    <input type="hidden" name="timesheet_id" value="<?= (int) ($entry['id'] ?? 0) ?>">
                                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                    <input type="number" step="0.25" min="0" max="24" name="hours" value="<?= htmlspecialchars((string) ($entry['hours'] ?? 0)) ?>" required class="ts-hours-input">
                                    <input type="text" name="reason" placeholder="Motivo" required class="ts-reject-input">
                                    <button type="submit" class="ts-btn ts-btn-outline ts-btn-sm">Editar</button>
                                </form>
                                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="ts-inline">
                                    <input type="hidden" name="admin_action" value="delete_entry">
                                    <input type="hidden" name="timesheet_id" value="<?= (int) ($entry['id'] ?? 0) ?>">
                                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                                    <input type="text" name="reason" placeholder="Motivo" required class="ts-reject-input">
                                    <button type="submit" class="ts-btn ts-btn-danger ts-btn-sm">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($canManageAdvanced): ?>
    <section class="ts-approval-section">
        <h3>Acciones avanzadas</h3>
        <div class="ts-admin-grid">
            <div class="ts-admin-card">
                <h4>Reapertura controlada</h4>
                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="ts-admin-form">
                    <input type="hidden" name="admin_action" value="reopen_week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                    <div class="ts-field">
                        <label>Talento</label>
                        <select name="talent_id">
                            <option value="0">Toda la semana</option>
                            <?php foreach ($talentOptions as $tal): ?>
                            <option value="<?= (int) ($tal['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($tal['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ts-field">
                        <label>Motivo</label>
                        <input type="text" name="reason" required placeholder="Ej: corrección de horas aprobadas">
                    </div>
                    <button type="submit" class="ts-btn ts-btn-outline">Reabrir semana</button>
                </form>
            </div>
            <div class="ts-admin-card">
                <h4>Eliminación masiva</h4>
                <form method="POST" action="<?= $basePath ?>/timesheets/admin-action" class="ts-admin-form">
                    <input type="hidden" name="admin_action" value="delete_week">
                    <input type="hidden" name="week" value="<?= htmlspecialchars($weekValue) ?>">
                    <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart->format('Y-m-d')) ?>">
                    <div class="ts-field">
                        <label>Talento</label>
                        <select name="talent_id">
                            <option value="0">Toda la semana</option>
                            <?php foreach ($talentOptions as $tal): ?>
                            <option value="<?= (int) ($tal['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($tal['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ts-field">
                        <label>Motivo</label>
                        <input type="text" name="reason" required placeholder="Ej: limpieza de carga masiva">
                    </div>
                    <div class="ts-field">
                        <label>Confirmación</label>
                        <input type="text" name="confirm_token" required placeholder="Escribe: ELIMINAR MASIVO">
                    </div>
                    <button type="submit" class="ts-btn ts-btn-danger">Eliminar masivo</button>
                </form>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($weekApprovalHistory)): ?>
    <section class="ts-approval-section">
        <h3>Historial de aprobaciones</h3>
        <div class="table-wrap">
            <table class="clean-table">
                <thead>
                    <tr><th>Semana</th><th>Acción</th><th>Actor</th><th>Aprobador</th><th>Comentario</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($weekApprovalHistory, 0, 20) as $hist): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($hist['week_start'] ?? '')) ?></td>
                        <td><span class="ts-status-badge <?= ($statusMap[(string) ($hist['resulting_status'] ?? 'draft')] ?? $statusMap['draft'])['class'] ?>"><?= htmlspecialchars((string) ($hist['action'] ?? '')) ?></span></td>
                        <td><?= htmlspecialchars((string) ($hist['actor_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($hist['approver_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($hist['action_comment'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($hist['created_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</section>

<style>
.ts-module{display:flex;flex-direction:column;gap:0}
.ts-view-tabs{display:flex;gap:2px;border-bottom:2px solid var(--border);margin-bottom:16px}
.ts-tab{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;text-decoration:none;color:var(--text-secondary);font-weight:600;font-size:14px;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s}
.ts-tab:hover{color:var(--text-primary);border-color:color-mix(in srgb,var(--primary) 40%,transparent)}
.ts-tab.active{color:var(--primary);border-color:var(--primary);font-weight:700}
.ts-tab svg{opacity:.7}.ts-tab.active svg{opacity:1}

.ts-header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 18px;background:var(--surface);border:1px solid var(--border);border-radius:14px;flex-wrap:wrap;margin-bottom:16px}
.ts-header-left{display:flex;align-items:center;gap:12px}
.ts-header-right{display:flex;align-items:center;gap:8px}
.ts-week-nav{display:flex;align-items:center;gap:4px}
.ts-nav-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text-primary);text-decoration:none;font-size:18px;font-weight:700;transition:all .15s}
.ts-nav-btn:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.ts-week-form{display:inline-flex}
.ts-week-input{padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text-primary);font-weight:600;font-size:14px;width:160px}
.ts-week-range{font-size:13px;color:var(--text-secondary);font-weight:500}
.ts-inline{display:inline-flex;align-items:center;gap:6px}
.ts-btn{padding:8px 14px;border-radius:10px;border:1px solid var(--border);cursor:pointer;font-weight:600;font-size:13px;background:var(--surface);color:var(--text-primary);transition:all .15s;white-space:nowrap}
.ts-btn:hover{transform:translateY(-1px)}
.ts-btn-primary{background:var(--primary);color:#fff;border-color:var(--primary)}
.ts-btn-outline{background:transparent;border-color:var(--border)}.ts-btn-outline:hover{border-color:var(--primary);color:var(--primary)}
.ts-btn-danger{color:#fff;background:#dc2626;border-color:#dc2626}.ts-btn-danger:hover{background:#b91c1c}
.ts-btn-sm{font-size:12px;padding:6px 10px}
.ts-status-badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
.ts-approved{background:#dcfce7;color:#166534}.ts-rejected{background:#fee2e2;color:#991b1b}.ts-submitted{background:#fef3c7;color:#92400e}.ts-draft{background:#f1f5f9;color:#475569}.ts-partial{background:#dbeafe;color:#1e40af}
.ts-filter-select{padding:6px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text-primary)}

.ts-approval-section{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px}
.ts-approval-section h3{margin:0 0 14px;font-size:16px;font-weight:800}
.ts-empty-msg{color:var(--text-secondary);font-size:13px;font-style:italic}
.ts-approval-week-card{border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:10px;background:color-mix(in srgb,var(--surface) 95%,var(--background) 5%)}
.ts-approval-week-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.ts-total-badge-sm{font-size:13px;font-weight:700;color:var(--primary);background:color-mix(in srgb,var(--primary) 12%,transparent);padding:3px 10px;border-radius:8px}
.ts-approval-projects{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.ts-approval-project-chip{font-size:12px;padding:3px 8px;border-radius:6px;background:color-mix(in srgb,var(--border) 40%,transparent);color:var(--text-primary);font-weight:500}
.ts-approval-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.ts-reject-input{padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;width:160px}
.ts-hours-input{padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;width:60px}
.ts-entry-actions{display:flex;gap:4px;flex-wrap:wrap}
.ts-admin-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.ts-admin-card{border:1px solid var(--border);border-radius:12px;padding:16px;background:color-mix(in srgb,var(--surface) 96%,var(--background) 4%)}
.ts-admin-card h4{margin:0 0 12px;font-size:14px;font-weight:700}
.ts-admin-form{display:flex;flex-direction:column;gap:10px}
.ts-field{display:flex;flex-direction:column;gap:4px}
.ts-field label{font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.03em}
.ts-field input,.ts-field select{padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text-primary);width:100%}
@media(max-width:768px){.ts-admin-grid{grid-template-columns:1fr}.ts-header{flex-direction:column;align-items:stretch}}
</style>
