<?php
$basePath = $basePath ?? '';
$absences = is_array($absences ?? null) ? $absences : [];
$talents = is_array($talents ?? null) ? $talents : [];
$canCreate = (bool) ($canCreate ?? false);
$canEdit = (bool) ($canEdit ?? false);
$canDelete = (bool) ($canDelete ?? false);
$canApprove = (bool) ($canApprove ?? false);
$flashMessage = (string) ($flashMessage ?? '');

$typeLabels = AbsenceService::ABSENCE_TYPES;
$statusLabels = AbsenceService::STATUSES;

$formatDate = static function (?string $value): string {
    if (!$value) return '-';
    $ts = strtotime($value);
    return $ts ? date('d/m/Y', $ts) : '-';
};

$statusClass = static function (string $status): string {
    return match (strtolower($status)) {
        'aprobado', 'approved' => 'status-success',
        'rechazado', 'rejected' => 'status-danger',
        'pendiente' => 'status-warning',
        default => 'status-muted',
    };
};

$flashText = match ($flashMessage) {
    'created' => 'Ausencia registrada correctamente.',
    'updated' => 'Ausencia actualizada correctamente.',
    'deleted' => 'Ausencia eliminada correctamente.',
    'approved' => 'Ausencia aprobada.',
    'rejected' => 'Ausencia rechazada.',
    default => '',
};
?>
<section class="section-grid">
    <header class="toolbar">
        <div>
            <p class="badge neutral">Talento</p>
            <h2>Gestión de ausencias</h2>
            <small class="text-muted">Registra y administra vacaciones, permisos y otras ausencias del talento.</small>
        </div>
        <?php if ($canCreate): ?>
            <a href="<?= $basePath ?>/absences/create" class="btn primary">+ Nueva ausencia</a>
        <?php endif; ?>
    </header>

    <?php if ($flashText): ?>
        <div class="alert success"><?= htmlspecialchars($flashText) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-content">
            <?php if (empty($absences)): ?>
                <p class="section-muted">No hay ausencias registradas. <?php if ($canCreate): ?><a href="<?= $basePath ?>/absences/create">Crear la primera</a><?php endif; ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Talento</th>
                                <th>Tipo</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Estado</th>
                                <?php if ($canEdit || $canDelete || $canApprove): ?>
                                    <th>Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($absences as $a): ?>
                                <?php
                                $typeLabel = $typeLabels[$a['absence_type'] ?? ''] ?? ucfirst(str_replace('_', ' ', (string) ($a['absence_type'] ?? 'ausencia')));
                                $status = (string) ($a['status'] ?? 'pendiente');
                                $statusLabel = $statusLabels[$status] ?? ucfirst($status);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($a['talent_name'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($typeLabel) ?></td>
                                    <td><?= $formatDate($a['start_date'] ?? null) ?></td>
                                    <td><?= $formatDate($a['end_date'] ?? null) ?></td>
                                    <td><span class="badge <?= $statusClass($status) ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                                    <?php if ($canEdit || $canDelete || $canApprove): ?>
                                        <td class="actions-cell">
                                            <?php if ($canEdit): ?>
                                                <a href="<?= $basePath ?>/absences/<?= (int) ($a['id'] ?? 0) ?>/edit" class="action-btn small">Editar</a>
                                            <?php endif; ?>
                                            <?php if ($canApprove && in_array(strtolower($status), ['pendiente'], true)): ?>
                                                <form method="POST" action="<?= $basePath ?>/absences/<?= (int) ($a['id'] ?? 0) ?>/approve" style="display:inline;" onsubmit="return confirm('¿Aprobar esta ausencia?');">
                                                    <button type="submit" class="action-btn small success">Aprobar</button>
                                                </form>
                                                <form method="POST" action="<?= $basePath ?>/absences/<?= (int) ($a['id'] ?? 0) ?>/reject" style="display:inline;" onsubmit="return confirm('¿Rechazar esta ausencia?');">
                                                    <button type="submit" class="action-btn small danger">Rechazar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($canDelete): ?>
                                                <form method="POST" action="<?= $basePath ?>/absences/<?= (int) ($a['id'] ?? 0) ?>/delete" style="display:inline;" onsubmit="return confirm('¿Eliminar esta ausencia?');">
                                                    <button type="submit" class="action-btn small danger">Eliminar</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.data-table th, .data-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); }
.data-table th { font-weight: 700; color: var(--text-secondary); background: color-mix(in srgb, var(--surface) 92%, var(--background) 8%); }
.data-table tbody tr:hover { background: color-mix(in srgb, var(--primary) 6%, var(--surface) 94%); }
.actions-cell { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.actions-cell form { margin: 0; }
</style>
