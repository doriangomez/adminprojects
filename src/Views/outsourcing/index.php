<?php
$basePath = $basePath ?? '/project/public';
$services = is_array($services ?? null) ? $services : [];
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$talents = is_array($talents ?? null) ? $talents : [];
$canManage = !empty($canManage);
$filters = is_array($filters ?? null) ? $filters : [];
$preselectedTalentId = (int) ($preselectedTalentId ?? 0);
$talentCreatedMessage = $talentCreatedMessage ?? null;

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
?>

<section class="outsourcing-shell">
    <header class="outsourcing-header">
        <div>
            <p class="eyebrow">Módulo de outsourcing</p>
            <h2>Control de servicios de talento</h2>
            <small class="section-muted">Visibilidad centralizada de clientes, periodos, salud de servicio y evidencias.</small>
        </div>
        <span class="badge neutral">PMO / ISO</span>
    </header>

    <section class="outsourcing-list">
        <div class="section-head">
            <div>
                <h3>Servicios activos y en seguimiento</h3>
                <small class="section-muted">Consulta el estado actual de cada asignación de outsourcing.</small>
            </div>
        </div>
        <form method="GET" action="<?= $basePath ?>/outsourcing" class="outsourcing-filters">
            <label>Cliente
                <select name="client_id">
                    <option value="">Todos</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>" <?= ((int) ($filters['client_id'] ?? 0) === (int) $client['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($client['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Talento
                <select name="talent_id">
                    <option value="">Todos</option>
                    <?php foreach ($talents as $talent): ?>
                        <option value="<?= (int) $talent['id'] ?>" <?= ((int) ($filters['talent_id'] ?? 0) === (int) $talent['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($talent['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Proyecto
                <select name="project_id">
                    <option value="">Todos</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= (int) $project['id'] ?>" <?= ((int) ($filters['project_id'] ?? 0) === (int) $project['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Estado del servicio
                <select name="service_health">
                    <option value="">Todos</option>
                    <?php foreach ($healthLabels as $healthKey => $healthLabel): ?>
                        <option value="<?= htmlspecialchars($healthKey) ?>" <?= (($filters['service_health'] ?? '') === $healthKey) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($healthLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="filter-actions">
                <button type="submit" class="action-btn">Filtrar</button>
                <a class="action-btn" href="<?= $basePath ?>/outsourcing">Limpiar</a>
            </div>
        </form>
        <?php if (empty($services)): ?>
            <p class="section-muted">Aún no hay servicios de outsourcing registrados.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Talento</th>
                            <th>Cliente</th>
                            <th>Proyecto</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Estado del servicio</th>
                            <th>Último seguimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <a class="link" href="<?= $basePath ?>/outsourcing/<?= (int) ($service['id'] ?? 0) ?>">
                                        <?= htmlspecialchars($service['talent_name'] ?? 'Talento') ?>
                                    </a>
                                    <small class="section-muted"><?= htmlspecialchars($service['talent_email'] ?? '') ?></small>
                                </td>
                                <td><?= htmlspecialchars($service['client_name'] ?? 'Cliente') ?></td>
                                <td><?= htmlspecialchars($service['project_name'] ?? 'Sin proyecto') ?></td>
                                <td><?= htmlspecialchars($formatDate($service['start_date'] ?? null)) ?></td>
                                <td><?= htmlspecialchars($formatDate($service['end_date'] ?? null)) ?></td>
                                <td>
                                    <span class="status-badge <?= $healthBadge($service['current_health'] ?? null) ?>">
                                        <?= htmlspecialchars($healthLabels[$service['current_health'] ?? ''] ?? 'Sin seguimiento') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($formatDate($service['last_followup_end'] ?? $service['health_updated_at'] ?? null)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($canManage): ?>
        <section class="outsourcing-form">
            <div>
                <h3>Registrar nuevo talento</h3>
                <small class="section-muted">Crea un talento sin salir del módulo para asignarlo al servicio.</small>
            </div>
            <?php if ($talentCreatedMessage): ?>
                <div class="alert success"><?= htmlspecialchars($talentCreatedMessage) ?></div>
            <?php endif; ?>
            <form method="POST" action="<?= $basePath ?>/outsourcing/talents" class="talent-form">
                <div class="grid">
                    <label>Nombre
                        <input name="name" required>
                    </label>
                    <label>Correo
                        <input type="email" name="email" required>
                    </label>
                </div>
                <div class="grid">
                    <label>Rol
                        <input name="role" required placeholder="Ej. Analista, DevOps">
                    </label>
                    <label>Seniority
                        <input name="seniority" placeholder="Ej. Senior">
                    </label>
                </div>
                <div class="grid">
                    <label>Capacidad semanal (h)
                        <input type="number" name="weekly_capacity" value="40">
                    </label>
                    <label>Disponibilidad (%)
                        <input type="number" name="availability" value="100">
                    </label>
                </div>
                <div class="grid">
                    <label>Costo hora
                        <input type="number" step="0.01" name="hourly_cost" value="0">
                    </label>
                    <label>Tarifa hora
                        <input type="number" step="0.01" name="hourly_rate" value="0">
                    </label>
                </div>
                <label class="checkbox">
                    <input type="checkbox" name="is_outsourcing" value="1" checked>
                    Talento de outsourcing
                </label>
                <button type="submit" class="action-btn primary">Guardar talento</button>
            </form>
        </section>
        <section class="outsourcing-form">
            <div>
                <h3>Nuevo servicio de outsourcing</h3>
                <small class="section-muted">Registra una asignación de talento con su cliente y periodo de servicio.</small>
            </div>
            <form method="POST" action="<?= $basePath ?>/outsourcing">
                <div class="grid">
                    <label>Talento
                        <select name="talent_id" required>
                            <option value="">Selecciona un talento</option>
                            <?php foreach ($talents as $talent): ?>
                                <option value="<?= (int) $talent['id'] ?>" <?= $preselectedTalentId === (int) $talent['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($talent['name'] ?? '') ?> (<?= htmlspecialchars($talent['role_name'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Cliente
                        <select name="client_id" required>
                            <option value="">Selecciona un cliente</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>">
                                    <?= htmlspecialchars($client['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label>Proyecto relacionado (opcional)
                    <select name="project_id">
                        <option value="">Sin proyecto</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>">
                                <?= htmlspecialchars($project['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="grid">
                    <label>Inicio del servicio
                        <input type="date" name="start_date" required>
                    </label>
                    <label>Fin del servicio
                        <input type="date" name="end_date">
                    </label>
                </div>
                <div class="grid">
                    <label>Frecuencia de seguimiento
                        <select name="followup_frequency" required>
                            <option value="weekly">Semanal</option>
                            <option value="monthly" selected>Mensual</option>
                        </select>
                    </label>
                    <label>Estado del servicio
                        <select name="service_status" required>
                            <option value="active">Activo</option>
                            <option value="paused">En pausa</option>
                            <option value="ended">Finalizado</option>
                        </select>
                    </label>
                </div>
                <label>Observaciones
                    <textarea name="observations" rows="3" placeholder="Notas del servicio, acuerdos o restricciones."></textarea>
                </label>
                <button type="submit" class="action-btn primary">Guardar servicio</button>
            </form>
        </section>
    <?php endif; ?>
</section>

<style>
    .outsourcing-shell { display:flex; flex-direction:column; gap:18px; }
    .outsourcing-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .outsourcing-list, .outsourcing-form { border:1px solid var(--border); border-radius:16px; padding:16px; background:#fff; display:flex; flex-direction:column; gap:12px; }
    .outsourcing-filters { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; padding:12px; border-radius:12px; border:1px dashed var(--border); background:#f8fafc; }
    .outsourcing-filters label { font-weight:600; display:flex; flex-direction:column; gap:6px; color: var(--text-strong); }
    .outsourcing-filters select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); }
    .filter-actions { display:flex; gap:8px; align-items:flex-end; }
    .table-wrapper { overflow:auto; }
    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--border); font-size:14px; }
    th { font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color: var(--muted); }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid transparent; display:inline-flex; }
    .status-muted { background:#f3f4f6; color:#374151; border-color:#e5e7eb; }
    .status-success { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
    .status-warning { background:#fef9c3; color:#854d0e; border-color:#fde047; }
    .status-danger { background:#fee2e2; color:#991b1b; border-color:#fecdd3; }
    .status-pill { font-size:12px; font-weight:600; padding:4px 10px; border-radius:999px; background:#eef2ff; color:#4338ca; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-strong); }
    select, input { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); font-family:inherit; }
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 12px; cursor:pointer; font-weight:600; }
    .action-btn.primary { background: var(--primary); color:#fff; border-color: var(--primary); }
    .link { color: var(--primary); font-weight:600; text-decoration:none; }
    .link:hover { text-decoration:underline; }
    .badge.neutral { background:#f1f5f9; color:#475569; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; }
    .talent-form { display:flex; flex-direction:column; gap:12px; }
    .checkbox { flex-direction:row; align-items:center; gap:8px; font-weight:600; }
    .alert.success { padding:10px 12px; border-radius:12px; background:#dcfce7; color:#166534; border:1px solid #bbf7d0; font-weight:600; }
</style>
