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

$activeServicesCount = count(array_filter(
    $services,
    static fn (array $service): bool => ($service['service_status'] ?? '') === 'active'
));
$externalTalentsCount = count($talents);
$pendingFollowupsCount = count(array_filter(
    $services,
    static fn (array $service): bool => empty($service['last_followup_end'])
));
$riskServicesCount = count(array_filter(
    $services,
    static fn (array $service): bool => in_array($service['current_health'] ?? '', ['yellow', 'red'], true)
));
?>

<section class="outsourcing-shell">
    <header class="outsourcing-header">
        <div class="header-main">
            <p class="eyebrow">Outsourcing</p>
            <h2>Outsourcing</h2>
            <p class="section-muted">Gestión de talento externo</p>
        </div>
        <div class="header-meta">
            <span class="context-badge">PMO / ISO</span>
            <span class="header-count"><?= count($services) ?> servicios</span>
        </div>
    </header>

    <section class="outsourcing-kpis">
        <article class="kpi-card">
            <span class="kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M4 6.75A2.75 2.75 0 0 1 6.75 4h10.5A2.75 2.75 0 0 1 20 6.75v10.5A2.75 2.75 0 0 1 17.25 20H6.75A2.75 2.75 0 0 1 4 17.25Z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M8 9.5h8M8 12h5M8 14.5h6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </span>
            <div>
                <p class="kpi-label">Servicios activos</p>
                <p class="kpi-value"><?= $activeServicesCount ?></p>
            </div>
        </article>
        <article class="kpi-card">
            <span class="kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M12 12.5a3.5 3.5 0 1 0-3.5-3.5 3.5 3.5 0 0 0 3.5 3.5Zm-6 7.5a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </span>
            <div>
                <p class="kpi-label">Talentos externos</p>
                <p class="kpi-value"><?= $externalTalentsCount ?></p>
            </div>
        </article>
        <article class="kpi-card">
            <span class="kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M7 4.5h10M7 12h10M7 19.5h6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <circle cx="5" cy="4.5" r="1" fill="currentColor"/>
                    <circle cx="5" cy="12" r="1" fill="currentColor"/>
                    <circle cx="5" cy="19.5" r="1" fill="currentColor"/>
                </svg>
            </span>
            <div>
                <p class="kpi-label">Seguimientos pendientes</p>
                <p class="kpi-value"><?= $pendingFollowupsCount ?></p>
            </div>
        </article>
        <article class="kpi-card">
            <span class="kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M12 4 20 19H4Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M12 9v4.5M12 17.5v.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </span>
            <div>
                <p class="kpi-label">Servicios en riesgo</p>
                <p class="kpi-value"><?= $riskServicesCount ?></p>
            </div>
        </article>
    </section>

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
        <details class="outsourcing-form" open>
            <summary>
                <div>
                    <h3>Registrar talento</h3>
                    <small class="section-muted">Crea un talento sin salir del módulo para asignarlo al servicio.</small>
                </div>
            </summary>
            <?php if ($talentCreatedMessage): ?>
                <div class="alert success"><?= htmlspecialchars($talentCreatedMessage) ?></div>
            <?php endif; ?>
            <form method="POST" action="<?= $basePath ?>/outsourcing/talents" class="talent-form">
                <div class="form-section">
                    <h4>Datos generales</h4>
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
                </div>
                <div class="form-section">
                    <h4>Capacidad y costos</h4>
                    <div class="grid">
                        <label>Capacidad horaria (h/semana)
                            <input type="number" step="0.5" name="capacidad_horaria" value="40">
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
                </div>
                <div class="form-section">
                    <h4>Reglas</h4>
                    <div class="grid">
                        <label>Tipo de talento
                            <select name="tipo_talento">
                                <option value="externo" selected>Externo</option>
                                <option value="interno">Interno</option>
                                <option value="otro">Otro</option>
                            </select>
                        </label>
                        <label>Reporte de horas
                            <select name="requiere_reporte_horas">
                                <option value="1" selected>Requiere reporte</option>
                                <option value="0">No reporta</option>
                            </select>
                        </label>
                    </div>
                    <label class="checkbox">
                        <input type="checkbox" name="requiere_aprobacion_horas" value="1" checked>
                        Requiere aprobación de horas
                    </label>
                </div>
                <button type="submit" class="action-btn primary">Guardar talento</button>
            </form>
        </details>
        <details class="outsourcing-form">
            <summary>
                <div>
                    <h3>Registrar servicio de outsourcing</h3>
                    <small class="section-muted">Registra una asignación de talento con su cliente y periodo de servicio.</small>
                </div>
            </summary>
            <form method="POST" action="<?= $basePath ?>/outsourcing">
                <div class="form-section">
                    <h4>Asignación</h4>
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
                </div>
                <div class="form-section">
                    <h4>Periodo</h4>
                    <div class="grid">
                        <label>Inicio del servicio
                            <input type="date" name="start_date" required>
                        </label>
                        <label>Fin del servicio
                            <input type="date" name="end_date">
                        </label>
                    </div>
                </div>
                <div class="form-section">
                    <h4>Seguimiento</h4>
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
                </div>
                <div class="form-section">
                    <h4>Observaciones</h4>
                    <label>Observaciones
                        <textarea name="observations" rows="3" placeholder="Notas del servicio, acuerdos o restricciones."></textarea>
                    </label>
                </div>
                <button type="submit" class="action-btn primary">Guardar servicio</button>
            </form>
        </details>
    <?php endif; ?>
</section>

<style>
    .outsourcing-shell { display:flex; flex-direction:column; gap:18px; }
    .outsourcing-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--bg-card); }
    .header-main h2 { margin:0; font-size:22px; color: var(--text-main); }
    .header-main .section-muted { margin:6px 0 0; color: var(--text-muted); }
    .header-meta { display:flex; flex-direction:column; gap:8px; align-items:flex-end; }
    .context-badge { background: var(--secondary); color: var(--text-main); border-radius:999px; padding:6px 12px; font-size:12px; font-weight:700; border:1px solid var(--border); }
    .header-count { font-size:13px; color: var(--text-muted); font-weight:600; }
    .outsourcing-kpis { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .kpi-card { border:1px solid var(--border); border-radius:14px; padding:12px; background: var(--bg-card); display:flex; gap:12px; align-items:center; }
    .kpi-icon { width:42px; height:42px; border-radius:12px; background: color-mix(in srgb, var(--bg-card) 80%, var(--bg-app) 20%); display:flex; align-items:center; justify-content:center; color: var(--text-main); }
    .kpi-icon svg { width:22px; height:22px; }
    .kpi-label { margin:0; font-size:12px; color: var(--text-muted); text-transform:uppercase; letter-spacing:0.04em; }
    .kpi-value { margin:4px 0 0; font-size:20px; font-weight:700; color: var(--text-main); }
    .outsourcing-list, .outsourcing-form { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--bg-card); display:flex; flex-direction:column; gap:12px; }
    .outsourcing-filters { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; padding:12px; border-radius:12px; border:1px dashed var(--border); background: color-mix(in srgb, var(--bg-card) 80%, var(--bg-app) 20%); }
    .outsourcing-filters label { font-weight:600; display:flex; flex-direction:column; gap:6px; color: var(--text-main); }
    .outsourcing-filters select { padding:8px 10px; border-radius:10px; border:1px solid var(--border); background: var(--bg-card); color: var(--text-main); }
    .filter-actions { display:flex; gap:8px; align-items:flex-end; }
    .table-wrapper { overflow:auto; }
    table { width:100%; border-collapse:collapse; font-size:13px; }
    th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--border); font-size:13px; vertical-align:top; }
    td { color: var(--text-main); }
    td small { display:block; margin-top:4px; color: var(--text-muted); font-size:12px; }
    th { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color: var(--text-muted); font-weight:700; }
    .status-badge { font-size:11px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid transparent; display:inline-flex; text-transform:uppercase; letter-spacing:0.03em; }
    .status-muted { background: color-mix(in srgb, var(--bg-card) 80%, var(--bg-app) 20%); color: var(--text-muted); border-color: var(--border); }
    .status-success { background: color-mix(in srgb, var(--primary) 16%, var(--bg-card) 84%); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 30%, var(--border) 70%); }
    .status-warning { background: color-mix(in srgb, var(--accent) 16%, var(--bg-card) 84%); color: var(--accent); border-color: color-mix(in srgb, var(--accent) 30%, var(--border) 70%); }
    .status-danger { background: color-mix(in srgb, var(--secondary) 18%, var(--bg-card) 82%); color: var(--secondary); border-color: color-mix(in srgb, var(--secondary) 35%, var(--border) 65%); }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-main); font-size:13px; }
    select, input { padding:10px 12px; border-radius:10px; border:1px solid var(--border); background: var(--bg-card); color: var(--text-main); }
    textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); font-family:inherit; background: var(--bg-card); color: var(--text-main); }
    .action-btn { background: var(--bg-card); color: var(--text-main); border:1px solid var(--border); border-radius:8px; padding:8px 12px; cursor:pointer; font-weight:600; }
    .action-btn.primary { background: var(--primary); color: var(--text-main); border-color: var(--primary); }
    .link { color: var(--primary); font-weight:600; text-decoration:none; }
    .link:hover { text-decoration:underline; }
    .talent-form { display:flex; flex-direction:column; gap:12px; }
    .checkbox { flex-direction:row; align-items:center; gap:8px; font-weight:600; }
    .alert.success { padding:10px 12px; border-radius:12px; background: color-mix(in srgb, var(--primary) 12%, var(--bg-card) 88%); color: var(--text-main); border:1px solid color-mix(in srgb, var(--primary) 30%, var(--border) 70%); font-weight:600; }
    .outsourcing-form summary { cursor:pointer; list-style:none; }
    .outsourcing-form summary::-webkit-details-marker { display:none; }
    .outsourcing-form summary div { display:flex; flex-direction:column; gap:4px; }
    .outsourcing-form summary h3 { margin:0; font-size:16px; color: var(--text-main); }
    .form-section { border:1px dashed var(--border); border-radius:12px; padding:12px; display:flex; flex-direction:column; gap:10px; background: color-mix(in srgb, var(--bg-card) 88%, var(--bg-app) 12%); }
    .form-section h4 { margin:0; font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color: var(--text-muted); }
</style>
