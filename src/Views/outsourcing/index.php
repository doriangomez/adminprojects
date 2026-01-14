<?php
$basePath = $basePath ?? '/project/public';
$services = is_array($services ?? null) ? $services : [];
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$talents = is_array($talents ?? null) ? $talents : [];
$canManage = !empty($canManage);

$serviceStatusLabels = [
    'active' => 'Activo',
    'paused' => 'En pausa',
    'ended' => 'Finalizado',
];
$healthLabels = [
    'green' => 'GREEN',
    'yellow' => 'YELLOW',
    'red' => 'RED',
];
$healthBadge = static function (?string $status): string {
    return match ($status) {
        'green' => 'status-success',
        'yellow' => 'status-warning',
        'red' => 'status-danger',
        default => 'status-muted',
    };
};
$formatPeriod = static function (?string $start, ?string $end): string {
    $startLabel = $start ?: 'Sin inicio';
    $endLabel = $end ?: 'Sin fin';
    return sprintf('%s → %s', $startLabel, $endLabel);
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
                            <th>Periodo de servicio</th>
                            <th>Salud actual</th>
                            <th>Estado</th>
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
                                <td><?= htmlspecialchars($formatPeriod($service['start_date'] ?? null, $service['end_date'] ?? null)) ?></td>
                                <td>
                                    <span class="status-badge <?= $healthBadge($service['current_health'] ?? null) ?>">
                                        <?= htmlspecialchars($healthLabels[$service['current_health'] ?? ''] ?? 'Sin seguimiento') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill">
                                        <?= htmlspecialchars($serviceStatusLabels[$service['service_status'] ?? 'active'] ?? 'Activo') ?>
                                    </span>
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
                <h3>Nuevo servicio de outsourcing</h3>
                <small class="section-muted">Registra una asignación de talento con su cliente y periodo de servicio.</small>
            </div>
            <form method="POST" action="<?= $basePath ?>/outsourcing">
                <div class="grid">
                    <label>Talento
                        <select name="talent_id" required>
                            <option value="">Selecciona un talento</option>
                            <?php foreach ($talents as $talent): ?>
                                <option value="<?= (int) $talent['id'] ?>">
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
                <button type="submit" class="action-btn primary">Guardar servicio</button>
            </form>
        </section>
    <?php endif; ?>
</section>

<style>
    .outsourcing-shell { display:flex; flex-direction:column; gap:18px; }
    .outsourcing-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .outsourcing-list, .outsourcing-form { border:1px solid var(--border); border-radius:16px; padding:16px; background:#fff; display:flex; flex-direction:column; gap:12px; }
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
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 12px; cursor:pointer; font-weight:600; }
    .action-btn.primary { background: var(--primary); color:#fff; border-color: var(--primary); }
    .link { color: var(--primary); font-weight:600; text-decoration:none; }
    .link:hover { text-decoration:underline; }
    .badge.neutral { background:#f1f5f9; color:#475569; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; }
</style>
