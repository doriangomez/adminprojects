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
                <small class="section-muted">Completa la ficha del talento y define su flujo de reporte de horas.</small>
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
                <label>Capacidad horaria (h/semana)
                    <input type="number" step="0.5" name="capacidad_horaria" value="<?= htmlspecialchars((string) ($editingTalent['capacidad_horaria'] ?? 40)) ?>">
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
            <div class="grid">
                <label>Tipo de talento
                    <select name="tipo_talento">
                        <?php $selectedTipo = $editingTalent['tipo_talento'] ?? 'interno'; ?>
                        <option value="interno" <?= $selectedTipo === 'interno' ? 'selected' : '' ?>>Interno</option>
                        <option value="externo" <?= $selectedTipo === 'externo' ? 'selected' : '' ?>>Externo</option>
                        <option value="otro" <?= $selectedTipo === 'otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </label>
                <label>Reporte de horas
                    <?php $requiresReport = $editingTalent['requiere_reporte_horas'] ?? 1; ?>
                    <select name="requiere_reporte_horas">
                        <option value="1" <?= !empty($requiresReport) ? 'selected' : '' ?>>Requiere reporte</option>
                        <option value="0" <?= empty($requiresReport) ? 'selected' : '' ?>>No reporta</option>
                    </select>
                </label>
            </div>
            <label class="checkbox">
                <input type="checkbox" name="requiere_aprobacion_horas" value="1" <?= !empty($editingTalent['requiere_aprobacion_horas']) ? 'checked' : '' ?>>
                Requiere aprobación de horas
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
                            <?php
                            $tipoTalento = $talent['tipo_talento'] ?? 'interno';
                            $tipoClass = $tipoTalento === 'externo' ? 'status-info' : ($tipoTalento === 'otro' ? 'status-warning' : 'status-muted');
                            ?>
                            <span class="status-badge <?= $tipoClass ?>">
                                <?= htmlspecialchars(ucfirst((string) $tipoTalento)) ?>
                            </span>
                        </div>
                        <p class="section-muted">Rol: <?= htmlspecialchars($talent['role'] ?? '') ?> · Seniority: <?= htmlspecialchars($talent['seniority'] ?? 'N/A') ?></p>
                        <p class="section-muted">Tipo: <?= htmlspecialchars($talent['tipo_talento'] ?? 'interno') ?> · Capacidad: <?= htmlspecialchars((string) ($talent['capacidad_horaria'] ?? 0)) ?>h · Disponibilidad <?= htmlspecialchars((string) ($talent['availability'] ?? 0)) ?>%</p>
                        <p class="section-muted">Reporte: <?= !empty($talent['requiere_reporte_horas']) ? 'Sí' : 'No' ?> · Aprobación: <?= !empty($talent['requiere_aprobacion_horas']) ? 'Sí' : 'No' ?></p>
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
    .talent-form-section, .talent-grid, .talent-tracking { border:1px solid var(--border); border-radius:16px; padding:16px; background:var(--surface); display:flex; flex-direction:column; gap:12px; }
    .section-head { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); }
    select, input { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); font-family:inherit; }
    .checkbox { flex-direction:row; align-items:center; gap:8px; }
    .divider { border-top:1px dashed var(--border); margin:8px 0; }
    .card { border:1px solid var(--border); border-radius:14px; padding:14px; background:color-mix(in srgb, var(--surface) 92%, var(--background) 8%); display:flex; flex-direction:column; gap:6px; }
    .toolbar { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:8px 12px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
    .action-btn.primary { background: var(--primary); color:var(--text-primary); border-color: var(--primary); }
    .action-btn.small { padding:6px 10px; font-size:12px; width:max-content; }
    .badge.neutral { background:color-mix(in srgb, var(--neutral) 12%, var(--surface) 88%); color:var(--text-secondary); border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid var(--background); display:inline-flex; }
    .status-muted { background:color-mix(in srgb, var(--neutral) 12%, var(--surface) 88%); color:var(--text-secondary); border-color:color-mix(in srgb, var(--neutral) 40%, var(--surface) 60%); }
    .status-success { background:color-mix(in srgb, var(--success) 15%, var(--surface) 85%); color:var(--success); border-color:color-mix(in srgb, var(--success) 40%, var(--surface) 60%); }
    .status-warning { background:color-mix(in srgb, var(--warning) 15%, var(--surface) 85%); color:var(--warning); border-color:color-mix(in srgb, var(--warning) 40%, var(--surface) 60%); }
    .status-danger { background:color-mix(in srgb, var(--danger) 15%, var(--surface) 85%); color:var(--danger); border-color:color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); }
    .status-info { background:color-mix(in srgb, var(--info) 15%, var(--surface) 85%); color:var(--info); border-color:color-mix(in srgb, var(--info) 40%, var(--surface) 60%); }
    .table-wrapper { overflow:auto; }
    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--border); font-size:14px; vertical-align:top; }
    th { font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color: var(--text-secondary); }
    .doc-list { margin:6px 0; padding-left:18px; color: var(--text-primary); }
    .link { color: var(--primary); font-weight:600; text-decoration:none; }
    .link:hover { text-decoration:underline; }
    .alert.success { padding:10px 12px; border-radius:12px; background:color-mix(in srgb, var(--success) 15%, var(--surface) 85%); color:var(--success); border:1px solid color-mix(in srgb, var(--success) 40%, var(--surface) 60%); font-weight:600; }
</style>
