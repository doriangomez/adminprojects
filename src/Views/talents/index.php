<?php
$basePath = $basePath ?? '/project/public';
$talents = is_array($talents ?? null) ? $talents : [];
$editingTalent = is_array($editingTalent ?? null) ? $editingTalent : null;
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$services = is_array($services ?? null) ? $services : [];
$documentsByService = is_array($documentsByService ?? null) ? $documentsByService : [];
$flashMessage = (string) ($flashMessage ?? '');
$isEditing = !empty($editingTalent);

$serviceStatusLabels = [
    'active' => 'Activo',
    'paused' => 'Pausado',
    'ended' => 'Finalizado',
];
$healthLabels = [
    'green' => 'Verde',
    'yellow' => 'Amarillo',
    'red' => 'Rojo',
];
$healthBadge = static function (?string $status): string {
    return match ($status) {
        'green' => 'status-success',
        'yellow' => 'status-warning',
        'red' => 'status-danger',
        default => 'status-muted',
    };
};
$formatDate = static function (?string $value): string {
    if (!$value) {
        return 'Sin registro';
    }
    $timestamp = strtotime($value);
    if (!$timestamp) {
        return 'Sin registro';
    }
    return date('d/m/Y', $timestamp);
};
$flashMessageText = match ($flashMessage) {
    'created' => 'Talento registrado y listo para asignaciones de outsourcing.',
    'created_outsourcing' => 'Talento registrado. Asígnalo a un servicio desde el módulo Outsourcing.',
    'updated' => 'Talento actualizado. Gestiona sus asignaciones desde Outsourcing.',
    default => '',
};
?>

<section class="talent-shell">
    <header class="talent-header">
        <div>
            <p class="eyebrow">Gestión de talentos</p>
            <h2>Centro de recursos de outsourcing</h2>
            <small class="section-muted">Registra talentos y revisa el seguimiento por cliente/proyecto.</small>
        </div>
        <span class="badge neutral">PMO / Gestión de talento</span>
    </header>

    <section class="talent-form-section">
        <div class="section-head">
            <div>
                <h3><?= $isEditing ? 'Editar talento' : 'Registrar talento' ?></h3>
                <small class="section-muted">Completa la ficha del talento y define si es de outsourcing.</small>
            </div>
            <?php if ($isEditing): ?>
                <a class="action-btn" href="<?= $basePath ?>/talents">Cancelar edición</a>
            <?php endif; ?>
        </div>
        <?php if ($flashMessageText): ?>
            <div class="alert success"><?= htmlspecialchars($flashMessageText) ?></div>
        <?php endif; ?>
        <form method="POST" action="<?= $basePath ?>/talents/<?= $isEditing ? 'update' : 'create' ?>" class="talent-form">
            <?php if ($isEditing): ?>
                <input type="hidden" name="talent_id" value="<?= (int) ($editingTalent['id'] ?? 0) ?>">
            <?php endif; ?>
            <div class="grid">
                <label>Nombre completo
                    <input name="name" required value="<?= htmlspecialchars((string) ($editingTalent['name'] ?? '')) ?>">
                </label>
                <label>Correo corporativo
                    <input type="email" name="email" value="<?= htmlspecialchars((string) ($editingTalent['user_email'] ?? '')) ?>" placeholder="talento@empresa.com">
                </label>
            </div>
            <div class="grid">
                <label>Rol principal
                    <input name="role" required value="<?= htmlspecialchars((string) ($editingTalent['role'] ?? '')) ?>" placeholder="Ej. Analista de servicio">
                </label>
                <label>Seniority
                    <input name="seniority" value="<?= htmlspecialchars((string) ($editingTalent['seniority'] ?? '')) ?>" placeholder="Ej. Senior">
                </label>
            </div>
            <div class="grid">
                <label>Capacidad semanal (h)
                    <input type="number" name="weekly_capacity" value="<?= htmlspecialchars((string) ($editingTalent['weekly_capacity'] ?? 40)) ?>">
                </label>
                <label>Disponibilidad (%)
                    <input type="number" name="availability" value="<?= htmlspecialchars((string) ($editingTalent['availability'] ?? 100)) ?>">
                </label>
            </div>
            <div class="grid">
                <label>Costo hora
                    <input type="number" step="0.01" name="hourly_cost" value="<?= htmlspecialchars((string) ($editingTalent['hourly_cost'] ?? 0)) ?>">
                </label>
                <label>Tarifa hora
                    <input type="number" step="0.01" name="hourly_rate" value="<?= htmlspecialchars((string) ($editingTalent['hourly_rate'] ?? 0)) ?>">
                </label>
            </div>
            <label class="checkbox">
                <input type="checkbox" name="is_outsourcing" value="1" <?= !empty($editingTalent['is_outsourcing']) ? 'checked' : '' ?>>
                Talento de outsourcing
            </label>

            <div class="divider"></div>
            <div class="alert">
                La asignación a servicios se gestiona desde el módulo Outsourcing.
            </div>
            <button type="submit" class="action-btn primary"><?= $isEditing ? 'Actualizar talento' : 'Guardar talento' ?></button>
        </form>
    </section>

    <section class="talent-grid">
        <div class="section-head">
            <div>
                <h3>Talentos registrados</h3>
                <small class="section-muted">Gestiona perfiles y disponibilidad.</small>
            </div>
        </div>
        <?php if (empty($talents)): ?>
            <p class="section-muted">Aún no se han registrado talentos.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($talents as $talent): ?>
                    <div class="card">
                        <div class="toolbar">
                            <strong><?= htmlspecialchars($talent['name'] ?? '') ?></strong>
                            <span class="status-badge <?= !empty($talent['is_outsourcing']) ? 'status-info' : 'status-muted' ?>">
                                <?= !empty($talent['is_outsourcing']) ? 'Outsourcing' : 'Interno' ?>
                            </span>
                        </div>
                        <p class="section-muted">Rol: <?= htmlspecialchars($talent['role'] ?? '') ?> · Seniority: <?= htmlspecialchars($talent['seniority'] ?? 'N/A') ?></p>
                        <p class="section-muted">Capacidad semanal: <?= htmlspecialchars((string) ($talent['weekly_capacity'] ?? 0)) ?>h · Disponibilidad <?= htmlspecialchars((string) ($talent['availability'] ?? 0)) ?>%</p>
                        <p class="section-muted">Costo: $<?= number_format((float) ($talent['hourly_cost'] ?? 0), 0, ',', '.') ?> · Tarifa: $<?= number_format((float) ($talent['hourly_rate'] ?? 0), 0, ',', '.') ?></p>
                        <p class="section-muted">Skills: <?= htmlspecialchars($talent['skills'] ?? 'n/a') ?></p>
                        <p class="section-muted">Email: <?= htmlspecialchars($talent['user_email'] ?? 'Sin usuario') ?></p>
                        <a class="action-btn small" href="<?= $basePath ?>/talents?edit=<?= (int) ($talent['id'] ?? 0) ?>">Editar</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="talent-tracking">
        <div class="section-head">
            <div>
                <h3>Seguimiento por talento y servicio</h3>
                <small class="section-muted">Estado actual, último seguimiento y evidencias asociadas.</small>
            </div>
        </div>
        <?php if (empty($services)): ?>
            <p class="section-muted">No hay servicios de outsourcing registrados.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Talento</th>
                            <th>Cliente / Proyecto</th>
                            <th>Periodo</th>
                            <th>Estado actual</th>
                            <th>Semáforo</th>
                            <th>Último seguimiento</th>
                            <th>Documentos asociados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <?php
                            $serviceId = (int) ($service['id'] ?? 0);
                            $documents = $documentsByService[$serviceId]['files'] ?? [];
                            $documentsTitle = $documentsByService[$serviceId]['node_title'] ?? '';
                            $documentsPreview = array_slice($documents, 0, 3);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($service['talent_name'] ?? 'Talento') ?></strong>
                                    <small class="section-muted"><?= htmlspecialchars($service['talent_email'] ?? '') ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($service['client_name'] ?? '') ?><br>
                                    <small class="section-muted"><?= htmlspecialchars($service['project_name'] ?? 'Sin proyecto') ?></small>
                                </td>
                                <td><?= htmlspecialchars((string) ($service['start_date'] ?? '')) ?> → <?= htmlspecialchars((string) ($service['end_date'] ?? 'Actual')) ?></td>
                                <td><?= htmlspecialchars($serviceStatusLabels[$service['service_status'] ?? 'active'] ?? 'Activo') ?></td>
                                <td>
                                    <span class="status-badge <?= $healthBadge($service['current_health'] ?? null) ?>">
                                        <?= htmlspecialchars($healthLabels[$service['current_health'] ?? ''] ?? 'Sin seguimiento') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($formatDate($service['last_followup_end'] ?? $service['health_updated_at'] ?? null)) ?></td>
                                <td>
                                    <?php if (empty($documents)): ?>
                                        <span class="section-muted">Sin evidencias</span>
                                    <?php else: ?>
                                        <?php if ($documentsTitle): ?>
                                            <strong><?= htmlspecialchars($documentsTitle) ?></strong>
                                        <?php endif; ?>
                                        <ul class="doc-list">
                                            <?php foreach ($documentsPreview as $doc): ?>
                                                <li><?= htmlspecialchars($doc['file_name'] ?? '') ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if (count($documents) > 3): ?>
                                            <small class="section-muted">+<?= count($documents) - 3 ?> documentos adicionales</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div>
                                        <a class="link" href="<?= $basePath ?>/outsourcing/<?= $serviceId ?>">Ver servicio</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>

<style>
    .talent-shell { display:flex; flex-direction:column; gap:18px; }
    .talent-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .talent-form-section, .talent-grid, .talent-tracking { border:1px solid var(--border); border-radius:16px; padding:16px; background:#fff; display:flex; flex-direction:column; gap:12px; }
    .section-head { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-strong); }
    select, input { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); font-family:inherit; }
    .checkbox { flex-direction:row; align-items:center; gap:8px; }
    .divider { border-top:1px dashed var(--border); margin:8px 0; }
    .card { border:1px solid var(--border); border-radius:14px; padding:14px; background:#f8fafc; display:flex; flex-direction:column; gap:6px; }
    .toolbar { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 12px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
    .action-btn.primary { background: var(--primary); color:#fff; border-color: var(--primary); }
    .action-btn.small { padding:6px 10px; font-size:12px; width:max-content; }
    .badge.neutral { background:#f1f5f9; color:#475569; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid transparent; display:inline-flex; }
    .status-muted { background:#f3f4f6; color:#374151; border-color:#e5e7eb; }
    .status-success { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
    .status-warning { background:#fef9c3; color:#854d0e; border-color:#fde047; }
    .status-danger { background:#fee2e2; color:#991b1b; border-color:#fecdd3; }
    .status-info { background:#dbeafe; color:#1e40af; border-color:#bfdbfe; }
    .table-wrapper { overflow:auto; }
    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--border); font-size:14px; vertical-align:top; }
    th { font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color: var(--muted); }
    .doc-list { margin:6px 0; padding-left:18px; color: var(--text-strong); }
    .link { color: var(--primary); font-weight:600; text-decoration:none; }
    .link:hover { text-decoration:underline; }
    .alert.success { padding:10px 12px; border-radius:12px; background:#dcfce7; color:#166534; border:1px solid #bbf7d0; font-weight:600; }
</style>
