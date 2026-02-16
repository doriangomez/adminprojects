<?php
$basePath = $basePath ?? '';
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
$serviceStatusLabels = [
    'active' => 'Activo',
    'paused' => 'En pausa',
    'ended' => 'Finalizado',
];
$serviceStatusBadge = static function (?string $status): string {
    return match ($status) {
        'active' => 'badge-active',
        'paused' => 'badge-paused',
        'ended' => 'badge-ended',
        default => 'badge-muted',
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
        <div class="header-identity">
            <span class="header-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M7.5 7.5a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0Zm-4.5 13a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </span>
            <div class="header-text">
                <p class="eyebrow">Outsourcing</p>
                <h2>Outsourcing</h2>
                <p class="header-subtitle">Gestión de talento externo por servicio</p>
            </div>
        </div>
        <div class="header-actions">
            <div class="header-meta">
                <span class="context-badge">PMO / ISO</span>
                <span class="header-count"><?= count($services) ?> servicios</span>
            </div>
            <div class="header-quick-actions">
                <a class="action-btn ghost" href="#outsourcing-filters">
                    <span class="btn-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="presentation">
                            <path d="M4 6h16M7 12h10M10 18h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </span>
                    Filtros
                </a>
                <?php if ($canManage): ?>
                    <a class="action-btn ghost" href="#registrar-talento">
                        <span class="btn-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation">
                                <path d="M12 12.5a3.5 3.5 0 1 0-3.5-3.5 3.5 3.5 0 0 0 3.5 3.5Zm-6 7.5a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                <path d="M19 7.5V5m0 0V2.5M19 5h2.5M19 5h-2.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                        </span>
                        Nuevo talento
                    </a>
                    <a class="action-btn primary" href="#registrar-servicio">
                        <span class="btn-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation">
                                <path d="M4 7.5h16M4 12h16M4 16.5h10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                <path d="M18 14.5V12m0 0V9.5m0 2.5h2.5m-2.5 0h-2.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                        </span>
                        Nuevo servicio
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="outsourcing-kpis">
        <article class="kpi-card">
            <div class="kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M4 6.75A2.75 2.75 0 0 1 6.75 4h10.5A2.75 2.75 0 0 1 20 6.75v10.5A2.75 2.75 0 0 1 17.25 20H6.75A2.75 2.75 0 0 1 4 17.25Z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M8 9.5h8M8 12h5M8 14.5h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </div>
            <div>
                <p class="kpi-value"><?= $activeServicesCount ?></p>
                <p class="kpi-label">Servicios activos</p>
            </div>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M12 12.5a3.5 3.5 0 1 0-3.5-3.5 3.5 3.5 0 0 0 3.5 3.5Zm-6 7.5a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </div>
            <div>
                <p class="kpi-value"><?= $externalTalentsCount ?></p>
                <p class="kpi-label">Talentos externos</p>
            </div>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M7 4.5h10M7 12h10M7 19.5h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    <circle cx="5" cy="4.5" r="1" fill="currentColor"/>
                    <circle cx="5" cy="12" r="1" fill="currentColor"/>
                    <circle cx="5" cy="19.5" r="1" fill="currentColor"/>
                </svg>
            </div>
            <div>
                <p class="kpi-value"><?= $pendingFollowupsCount ?></p>
                <p class="kpi-label">Pendientes de seguimiento</p>
            </div>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="presentation">
                    <path d="M12 4 20 19H4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    <path d="M12 9v4.5M12 17.5v.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </div>
            <div>
                <p class="kpi-value"><?= $riskServicesCount ?></p>
                <p class="kpi-label">Servicios en riesgo</p>
            </div>
        </article>
    </section>

    <section class="outsourcing-list">
        <div class="section-head">
            <div class="section-title">
                <span class="section-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M6 7.5h12M6 12h12M6 16.5h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </span>
                <div>
                    <h3>Servicios activos y en seguimiento</h3>
                    <small class="section-muted">Consulta el estado actual de cada asignación de outsourcing.</small>
                </div>
            </div>
        </div>
        <form method="GET" action="<?= $basePath ?>/outsourcing" class="outsourcing-filters" id="outsourcing-filters">
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
                <button type="submit" class="action-btn">
                    <span class="btn-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="presentation">
                            <path d="M4 6h16M7 12h10M10 18h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </span>
                    Filtrar
                </button>
                <a class="action-btn" href="<?= $basePath ?>/outsourcing">
                    <span class="btn-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="presentation">
                            <path d="M6 6h12M6 12h12M6 18h7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </span>
                    Limpiar
                </a>
            </div>
        </form>
        <?php if (empty($services)): ?>
            <p class="section-muted">Aún no hay servicios de outsourcing registrados.</p>
        <?php else: ?>
            <div class="service-grid">
                <?php foreach ($services as $service): ?>
                    <article class="service-card <?= $healthBadge($service['current_health'] ?? null) ?>">
                        <header class="service-card-header">
                            <div class="service-title">
                                <span class="service-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" role="presentation">
                                        <path d="M4 6.75A2.75 2.75 0 0 1 6.75 4h10.5A2.75 2.75 0 0 1 20 6.75v10.5A2.75 2.75 0 0 1 17.25 20H6.75A2.75 2.75 0 0 1 4 17.25Z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                        <path d="M8 9.5h8M8 12h5M8 14.5h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                    </svg>
                                </span>
                                <div>
                                    <h4><?= htmlspecialchars($service['project_name'] ?? 'Servicio de outsourcing') ?></h4>
                                    <div class="service-badges">
                                        <span class="service-badge <?= $serviceStatusBadge($service['service_status'] ?? null) ?>">
                                            <span class="badge-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" role="presentation">
                                                    <path d="M4.5 12.5 9 17l10.5-10.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </span>
                                            <?= htmlspecialchars($serviceStatusLabels[$service['service_status'] ?? ''] ?? 'Sin estado') ?>
                                        </span>
                                        <span class="status-badge <?= $healthBadge($service['current_health'] ?? null) ?>">
                                            <span class="badge-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" role="presentation">
                                                    <path d="M12 4 20 19H4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                                    <path d="M12 9v4.5M12 17.5v.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                                </svg>
                                            </span>
                                            <?= htmlspecialchars($healthLabels[$service['current_health'] ?? ''] ?? 'Sin seguimiento') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </header>
                        <div class="service-body">
                            <div class="service-meta">
                                <div class="meta-item">
                                    <span class="meta-label">
                                        <span class="meta-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" role="presentation">
                                                <path d="M12 12.5a3.5 3.5 0 1 0-3.5-3.5 3.5 3.5 0 0 0 3.5 3.5Zm-6 7.5a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                            </svg>
                                        </span>
                                        Talento
                                    </span>
                                    <strong><?= htmlspecialchars($service['talent_name'] ?? 'Talento') ?></strong>
                                    <small class="section-muted"><?= htmlspecialchars($service['talent_email'] ?? '') ?></small>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">
                                        <span class="meta-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" role="presentation">
                                                <path d="M4.5 19.5V9.5L12 4l7.5 5.5v10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M9.5 19.5V12h5v7.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                            </svg>
                                        </span>
                                        Cliente
                                    </span>
                                    <strong><?= htmlspecialchars($service['client_name'] ?? 'Cliente') ?></strong>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">
                                        <span class="meta-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" role="presentation">
                                                <path d="M7 4.5h10M7 12h10M7 19.5h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                                <circle cx="5" cy="4.5" r="1" fill="currentColor"/>
                                                <circle cx="5" cy="12" r="1" fill="currentColor"/>
                                                <circle cx="5" cy="19.5" r="1" fill="currentColor"/>
                                            </svg>
                                        </span>
                                        Periodo
                                    </span>
                                    <strong><?= htmlspecialchars($formatDate($service['start_date'] ?? null)) ?> · <?= htmlspecialchars($formatDate($service['end_date'] ?? null)) ?></strong>
                                    <small class="section-muted">Último seguimiento: <?= htmlspecialchars($formatDate($service['last_followup_end'] ?? $service['health_updated_at'] ?? null)) ?></small>
                                </div>
                            </div>
                            <div class="service-indicators">
                                <span class="indicator-pill <?= $healthBadge($service['current_health'] ?? null) ?>">
                                    <span class="indicator-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" role="presentation">
                                            <path d="M4 6.5h16M4 12h16M4 17.5h10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    Seguimiento: <?= htmlspecialchars($healthLabels[$service['current_health'] ?? ''] ?? 'Sin seguimiento') ?>
                                </span>
                                <span class="indicator-pill <?= $serviceStatusBadge($service['service_status'] ?? null) ?>">
                                    <span class="indicator-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" role="presentation">
                                            <path d="M6 5.5h12v13H6z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                            <path d="M9 9.5h6M9 13.5h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    Estado: <?= htmlspecialchars($serviceStatusLabels[$service['service_status'] ?? ''] ?? 'Sin estado') ?>
                                </span>
                            </div>
                        </div>
                        <footer class="service-footer">
                            <div class="service-actions">
                                <a class="icon-action" href="<?= $basePath ?>/outsourcing/<?= (int) ($service['id'] ?? 0) ?>">
                                    <span class="btn-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" role="presentation">
                                            <path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6Z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                            <circle cx="12" cy="12" r="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                        </svg>
                                    </span>
                                    Ver
                                </a>
                                <a class="icon-action" href="<?= $basePath ?>/outsourcing/<?= (int) ($service['id'] ?? 0) ?>#resumen">
                                    <span class="btn-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" role="presentation">
                                            <path d="M4 16.5V20h3.5L19 8.5 15.5 5 4 16.5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    Editar
                                </a>
                                <a class="icon-action" href="<?= $basePath ?>/outsourcing/<?= (int) ($service['id'] ?? 0) ?>#seguimientos">
                                    <span class="btn-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" role="presentation">
                                            <path d="M4 6.5h16M4 12h16M4 17.5h10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    Seguimiento
                                </a>
                            </div>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($canManage): ?>
        <details class="outsourcing-form" open id="registrar-talento">
            <summary>
                <div class="section-title">
                    <span class="section-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="presentation">
                            <path d="M12 12.5a3.5 3.5 0 1 0-3.5-3.5 3.5 3.5 0 0 0 3.5 3.5Zm-6 7.5a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <div>
                        <h3>Registrar talento</h3>
                        <small class="section-muted">Crea un talento sin salir del módulo para asignarlo al servicio.</small>
                    </div>
                </div>
                <span class="summary-indicator" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </summary>
            <?php if ($talentCreatedMessage): ?>
                <div class="alert success"><?= htmlspecialchars($talentCreatedMessage) ?></div>
            <?php endif; ?>
            <form method="POST" action="<?= $basePath ?>/outsourcing/talents" class="talent-form">
                <details class="form-accordion" open>
                    <summary>
                        <span class="accordion-title">
                            <span class="section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="presentation">
                                    <path d="M12 12.5a3.5 3.5 0 1 0-3.5-3.5 3.5 3.5 0 0 0 3.5 3.5Zm-6 7.5a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Datos del talento
                        </span>
                        <span class="summary-indicator" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation">
                                <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="form-section">
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
                </details>
                <details class="form-accordion" open>
                    <summary>
                        <span class="accordion-title">
                            <span class="section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="presentation">
                                    <path d="M5 7.5h14M7 12h10M9 16.5h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Capacidad y horas
                        </span>
                        <span class="summary-indicator" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation">
                                <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="form-section">
                        <div class="grid">
                            <label>Capacidad horaria (h/semana)
                                <input type="number" step="0.5" name="capacidad_horaria" value="40">
                            </label>
                            <label>Disponibilidad (%)
                                <input type="number" name="availability" value="100">
                            </label>
                        </div>
                    </div>
                </details>
                <details class="form-accordion" open>
                    <summary>
                        <span class="accordion-title">
                            <span class="section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="presentation">
                                    <path d="M6 8.5h12M6 12h12M6 15.5h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Costos
                        </span>
                        <span class="summary-indicator" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation">
                                <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="form-section">
                        <div class="grid">
                            <label>Costo hora
                                <input type="number" step="0.01" name="hourly_cost" value="0">
                            </label>
                            <label>Tarifa hora
                                <input type="number" step="0.01" name="hourly_rate" value="0">
                            </label>
                        </div>
                    </div>
                </details>
                <details class="form-accordion" open>
                    <summary>
                        <span class="accordion-title">
                            <span class="section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="presentation">
                                    <path d="M7.5 4.5h9A2.5 2.5 0 0 1 19 7v9a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 16V7A2.5 2.5 0 0 1 7.5 4.5Z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                    <path d="M9.5 12l1.8 1.8 3.2-3.6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            Reglas
                        </span>
                        <span class="summary-indicator" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation">
                                <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="form-section">
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
                </details>
                <button type="submit" class="action-btn primary">Guardar talento</button>
            </form>
        </details>
        <details class="outsourcing-form" id="registrar-servicio">
            <summary>
                <div class="section-title">
                    <span class="section-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="presentation">
                            <path d="M4 6.75A2.75 2.75 0 0 1 6.75 4h10.5A2.75 2.75 0 0 1 20 6.75v10.5A2.75 2.75 0 0 1 17.25 20H6.75A2.75 2.75 0 0 1 4 17.25Z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                            <path d="M8 9.5h8M8 12h5M8 14.5h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <div>
                        <h3>Registrar servicio de outsourcing</h3>
                        <small class="section-muted">Registra una asignación de talento con su cliente y periodo de servicio.</small>
                    </div>
                </div>
                <span class="summary-indicator" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation">
                        <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </summary>
            <form method="POST" action="<?= $basePath ?>/outsourcing">
                <details class="form-accordion" open>
                    <summary>
                        <span class="accordion-title">
                            <span class="section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="presentation">
                                    <path d="M6 7.5h12M6 12h12M6 16.5h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Asignación
                        </span>
                        <span class="summary-indicator" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation">
                                <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="form-section">
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
                </details>
                <details class="form-accordion" open>
                    <summary>
                        <span class="accordion-title">
                            <span class="section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="presentation">
                                    <path d="M7 4.5h10M7 12h10M7 19.5h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                    <circle cx="5" cy="4.5" r="1" fill="currentColor"/>
                                    <circle cx="5" cy="12" r="1" fill="currentColor"/>
                                    <circle cx="5" cy="19.5" r="1" fill="currentColor"/>
                                </svg>
                            </span>
                            Periodo
                        </span>
                        <span class="summary-indicator" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation">
                                <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="form-section">
                        <div class="grid">
                            <label>Inicio del servicio
                                <input type="date" name="start_date" required>
                            </label>
                            <label>Fin del servicio
                                <input type="date" name="end_date">
                            </label>
                        </div>
                    </div>
                </details>
                <details class="form-accordion" open>
                    <summary>
                        <span class="accordion-title">
                            <span class="section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="presentation">
                                    <path d="M12 4v8l5 3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                </svg>
                            </span>
                            Seguimiento
                        </span>
                        <span class="summary-indicator" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation">
                                <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="form-section">
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
                </details>
                <details class="form-accordion" open>
                    <summary>
                        <span class="accordion-title">
                            <span class="section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="presentation">
                                    <path d="M5 6.5h14M5 12h14M5 17.5h10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            Observaciones
                        </span>
                        <span class="summary-indicator" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="presentation">
                                <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </summary>
                    <div class="form-section">
                        <label>Observaciones
                            <textarea name="observations" rows="3" placeholder="Notas del servicio, acuerdos o restricciones."></textarea>
                        </label>
                    </div>
                </details>
                <button type="submit" class="action-btn primary">Guardar servicio</button>
            </form>
        </details>
    <?php endif; ?>
</section>

<style>
    .outsourcing-shell { display:flex; flex-direction:column; gap:18px; }
    .outsourcing-header { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; border:1px solid var(--border); border-radius:18px; padding:18px; background: var(--surface); }
    .header-identity { display:flex; gap:14px; align-items:center; }
    .header-icon { width:56px; height:56px; border-radius:16px; display:flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--primary) 18%, var(--surface) 82%); color: var(--primary); }
    .header-icon svg { width:28px; height:28px; }
    .header-text h2 { margin:0; font-size:24px; color: var(--text-primary); }
    .header-subtitle { margin:6px 0 0; color: var(--text-secondary); }
    .header-actions { display:flex; flex-direction:column; gap:12px; align-items:flex-end; }
    .header-meta { display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .context-badge { background: color-mix(in srgb, var(--secondary) 20%, var(--surface) 80%); color: var(--secondary); border-radius:999px; padding:6px 12px; font-size:12px; font-weight:700; border:1px solid color-mix(in srgb, var(--secondary) 40%, var(--border) 60%); }
    .header-count { font-size:12px; color: var(--text-secondary); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
    .header-quick-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
    .outsourcing-kpis { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    .kpi-card { border:1px solid var(--border); border-radius:16px; padding:14px; background: var(--surface); display:flex; gap:12px; align-items:center; }
    .kpi-icon { width:48px; height:48px; border-radius:14px; background: color-mix(in srgb, var(--surface) 78%, var(--background) 22%); display:flex; align-items:center; justify-content:center; color: var(--text-primary); }
    .kpi-icon svg { width:24px; height:24px; }
    .kpi-label { margin:2px 0 0; font-size:11px; color: var(--text-secondary); text-transform:uppercase; letter-spacing:0.05em; }
    .kpi-value { margin:0; font-size:22px; font-weight:800; color: var(--text-primary); }
    .outsourcing-list, .outsourcing-form { border:1px solid var(--border); border-radius:18px; padding:18px; background: var(--surface); display:flex; flex-direction:column; gap:14px; }
    .section-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
    .section-title { display:flex; gap:10px; align-items:center; }
    .section-title h3 { margin:0; font-size:18px; color: var(--text-primary); }
    .section-icon { width:36px; height:36px; border-radius:12px; display:flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--surface) 70%, var(--background) 30%); color: var(--text-primary); }
    .section-icon svg { width:18px; height:18px; }
    .outsourcing-filters { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; padding:12px; border-radius:14px; border:1px dashed var(--border); background: color-mix(in srgb, var(--surface) 80%, var(--background) 20%); }
    .outsourcing-filters label { font-weight:600; display:flex; flex-direction:column; gap:6px; color: var(--text-primary); }
    .outsourcing-filters select { padding:9px 10px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); }
    .filter-actions { display:flex; gap:8px; align-items:flex-end; }
    .service-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:14px; }
    .service-card { border:1px solid color-mix(in srgb, var(--border) 70%, transparent 30%); border-radius:20px; padding:16px; background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%); display:flex; flex-direction:column; gap:12px; position:relative; box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08); }
    .service-card::before { content:""; position:absolute; inset:0; border-radius:20px; border:1px solid transparent; pointer-events:none; }
    .service-card:hover { border-color: color-mix(in srgb, var(--primary) 30%, var(--border) 70%); box-shadow: 0 16px 30px rgba(15, 23, 42, 0.12); }
    .service-card.status-muted { border-left:4px solid color-mix(in srgb, var(--text-secondary) 40%, transparent 60%); }
    .service-card.status-success { border-left:4px solid color-mix(in srgb, var(--primary) 70%, transparent 30%); }
    .service-card.status-warning { border-left:4px solid color-mix(in srgb, var(--accent) 70%, transparent 30%); }
    .service-card.status-danger { border-left:4px solid color-mix(in srgb, var(--secondary) 70%, transparent 30%); }
    .service-card-header { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
    .service-title { display:flex; gap:12px; align-items:flex-start; }
    .service-title h4 { margin:0; font-size:16px; color: var(--text-primary); }
    .service-icon { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--primary) 14%, var(--surface) 86%); color: var(--primary); }
    .service-icon svg { width:20px; height:20px; }
    .service-badges { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
    .service-body { border-top:1px dashed var(--border); padding-top:12px; }
    .service-meta { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
    .meta-item { display:flex; flex-direction:column; gap:4px; }
    .meta-label { display:inline-flex; align-items:center; gap:6px; font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color: var(--text-secondary); font-weight:700; }
    .meta-icon { width:20px; height:20px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--surface) 70%, var(--background) 30%); color: var(--text-primary); }
    .meta-icon svg { width:12px; height:12px; }
    .service-meta strong { color: var(--text-primary); font-size:13px; }
    .service-indicators { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
    .indicator-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.03em; border:1px solid var(--border); }
    .indicator-icon { width:14px; height:14px; display:inline-flex; }
    .indicator-icon svg { width:14px; height:14px; }
    .service-footer { border-top:1px dashed var(--border); padding-top:12px; display:flex; justify-content:flex-end; }
    .service-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .icon-action { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:12px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); text-decoration:none; font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.04em; }
    .icon-action:hover { border-color: var(--primary); background: color-mix(in srgb, var(--surface) 80%, var(--background) 20%); }
    .status-badge,
    .service-badge { font-size:11px; font-weight:800; padding:5px 10px; border-radius:999px; border:1px solid transparent; display:inline-flex; text-transform:uppercase; letter-spacing:0.04em; align-items:center; gap:6px; }
    .badge-icon { width:14px; height:14px; display:inline-flex; }
    .badge-icon svg { width:14px; height:14px; }
    .status-muted { background: color-mix(in srgb, var(--surface) 70%, var(--background) 30%); color: var(--text-secondary); border-color: color-mix(in srgb, var(--text-secondary) 20%, var(--border) 80%); }
    .status-success { background: color-mix(in srgb, var(--primary) 22%, var(--surface) 78%); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 40%, var(--border) 60%); }
    .status-warning { background: color-mix(in srgb, var(--accent) 22%, var(--surface) 78%); color: var(--accent); border-color: color-mix(in srgb, var(--accent) 40%, var(--border) 60%); }
    .status-danger { background: color-mix(in srgb, var(--secondary) 24%, var(--surface) 76%); color: var(--secondary); border-color: color-mix(in srgb, var(--secondary) 45%, var(--border) 55%); }
    .badge-muted { background: color-mix(in srgb, var(--surface) 70%, var(--background) 30%); color: var(--text-secondary); border-color: color-mix(in srgb, var(--text-secondary) 20%, var(--border) 80%); }
    .badge-active { background: color-mix(in srgb, var(--primary) 20%, var(--surface) 80%); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 38%, var(--border) 62%); }
    .badge-paused { background: color-mix(in srgb, var(--accent) 20%, var(--surface) 80%); color: var(--accent); border-color: color-mix(in srgb, var(--accent) 38%, var(--border) 62%); }
    .badge-ended { background: color-mix(in srgb, var(--secondary) 20%, var(--surface) 80%); color: var(--secondary); border-color: color-mix(in srgb, var(--secondary) 38%, var(--border) 62%); }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    label { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); font-size:13px; }
    select, input { padding:10px 12px; border-radius:10px; border:1px solid var(--border); background: var(--surface); color: var(--text-primary); }
    textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); font-family:inherit; background: var(--surface); color: var(--text-primary); }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:10px; padding:8px 12px; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:6px; text-decoration:none; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .action-btn.ghost { background: color-mix(in srgb, var(--surface) 85%, var(--background) 15%); }
    .btn-icon { display:inline-flex; width:16px; height:16px; }
    .btn-icon svg { width:16px; height:16px; }
    .talent-form { display:flex; flex-direction:column; gap:12px; }
    .checkbox { flex-direction:row; align-items:center; gap:8px; font-weight:600; }
    .alert.success { padding:10px 12px; border-radius:12px; background: color-mix(in srgb, var(--primary) 12%, var(--surface) 88%); color: var(--text-primary); border:1px solid color-mix(in srgb, var(--primary) 30%, var(--border) 70%); font-weight:600; }
    .outsourcing-form summary { cursor:pointer; list-style:none; display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .outsourcing-form summary::-webkit-details-marker { display:none; }
    .outsourcing-form summary h3 { margin:0; font-size:16px; color: var(--text-primary); }
    .summary-indicator { display:inline-flex; width:18px; height:18px; }
    .summary-indicator svg { width:18px; height:18px; }
    .form-accordion { border:1px solid var(--border); border-radius:14px; padding:10px; background: color-mix(in srgb, var(--surface) 90%, var(--background) 10%); }
    .form-accordion summary { cursor:pointer; list-style:none; display:flex; justify-content:space-between; align-items:center; gap:10px; font-weight:700; color: var(--text-primary); }
    .form-accordion summary::-webkit-details-marker { display:none; }
    .accordion-title { display:inline-flex; gap:8px; align-items:center; }
    .form-section { border:1px dashed var(--border); border-radius:12px; padding:12px; display:flex; flex-direction:column; gap:10px; background: color-mix(in srgb, var(--surface) 88%, var(--background) 12%); margin-top:10px; }
</style>
