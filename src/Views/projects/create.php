<?php
$basePath = $basePath ?? '/project/public';
$clientsList = is_array($clients ?? null) ? $clients : [];
$projectManagersList = is_array($projectManagers ?? null) ? $projectManagers : [];
$deliveryConfig = is_array($delivery ?? null) ? $delivery : ['methodologies' => [], 'phases' => [], 'risks' => []];
$prioritiesCatalog = is_array($priorities ?? null) ? $priorities : [];
$statusesCatalog = is_array($statuses ?? null) ? $statuses : [];
$healthCatalog = is_array($healthCatalog ?? null) ? $healthCatalog : [];
$defaults = is_array($defaults ?? null) ? $defaults : [];
$oldInput = is_array($old ?? null) ? $old : [];

$methodologies = $deliveryConfig['methodologies'] ?? [];
$phasesByMethodology = $deliveryConfig['phases'] ?? [];
$riskCatalog = $deliveryConfig['risks'] ?? [];
$riskGroups = [];
foreach ($riskCatalog as $risk) {
    $category = $risk['category'] ?? 'Otros';
    $riskGroups[$category][] = $risk;
}

$selectedMethodology = $oldInput['methodology'] ?? $defaults['methodology'] ?? ($methodologies[0] ?? 'scrum');
$currentPhases = is_array($phasesByMethodology[$selectedMethodology] ?? null) ? $phasesByMethodology[$selectedMethodology] : [];
$selectedPhase = $oldInput['phase'] ?? $defaults['phase'] ?? ($currentPhases[0] ?? '');
$selectedClientId = (int) ($oldInput['client_id'] ?? $defaults['client_id'] ?? ($clientsList[0]['id'] ?? 0));
$selectedPmId = (int) ($oldInput['pm_id'] ?? $defaults['pm_id'] ?? ($projectManagersList[0]['id'] ?? 0));
$selectedStatus = (string) ($oldInput['status'] ?? $defaults['status'] ?? ($statusesCatalog[0]['code'] ?? ''));
$selectedHealth = (string) ($oldInput['health'] ?? $defaults['health'] ?? ($healthCatalog[0]['code'] ?? ''));
$selectedPriority = (string) ($oldInput['priority'] ?? $defaults['priority'] ?? ($prioritiesCatalog[0]['code'] ?? ''));
$selectedProjectType = (string) ($oldInput['project_type'] ?? $defaults['project_type'] ?? 'convencional');
$clientParticipation = (string) ($oldInput['client_participation'] ?? $defaults['client_participation'] ?? 'media');

$canCreateProject = (bool) ($canCreate ?? false);

$fieldValue = function (string $field, $fallback = '') use ($oldInput, $defaults) {
    if (array_key_exists($field, $oldInput)) {
        return $oldInput[$field];
    }

    return $defaults[$field] ?? $fallback;
};
?>

<div class="toolbar">
    <div>
        <a href="<?= $basePath ?>/projects" class="btn ghost">← Volver</a>
        <h3 style="margin:8px 0 0 0;">Nuevo proyecto</h3>
        <p style="margin:4px 0 0 0; color: var(--muted);">Wizard guiado para registrar un proyecto sin depender de portafolios.</p>
    </div>
    <div class="pill soft-blue" style="align-self:center; gap:8px; display:inline-flex; align-items:center;">
        <span aria-hidden="true">🧭</span>
        Metodologías desde configuración
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$canCreateProject): ?>
    <div class="alert warning">
        Necesitas al menos un cliente y un PM activo para crear proyectos. Revisa el módulo de Clientes o Configuración.
    </div>
<?php endif; ?>

<form action="<?= $basePath ?>/projects/create" method="POST" class="card wizard-shell" id="projectWizardForm" style="opacity: <?= $canCreateProject ? '1' : '0.65' ?>;">
    <div class="wizard-header">
        <div class="wizard-header__title">
            <div class="wizard-header__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 6h7l2 2h7v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z" />
                    <path d="M12 11h6" />
                    <path d="M12 15h4" />
                </svg>
            </div>
            <div>
                <p class="section-label">Wizard de proyectos</p>
                <strong>Diseño guiado y profesional para PMO</strong>
                <p class="muted">Sigue los pasos para capturar la información clave del proyecto.</p>
            </div>
        </div>
        <div class="wizard-header__meta">
            <div class="pill soft-blue" style="align-self:center; gap:8px; display:inline-flex; align-items:center;">
                <span aria-hidden="true">🧭</span>
                Metodologías desde configuración
            </div>
            <div class="pill soft-slate ghosted-pill">Estructura limpia y reconstruida por metodología</div>
        </div>
    </div>

    <div class="wizard-steps" role="list">
        <div class="wizard-step" data-step-indicator="0" role="listitem">
            <div class="wizard-step__marker">
                <div class="wizard-step__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 7h16" />
                        <path d="M5 11h14" />
                        <path d="M7 15h10" />
                        <path d="M9 19h6" />
                    </svg>
                </div>
                <span class="wizard-step__number">1</span>
            </div>
            <div class="wizard-step__body">
                <p class="wizard-step__title">Contexto base</p>
                <p class="wizard-step__subtitle">Cliente, nombre, responsable</p>
            </div>
        </div>
        <div class="wizard-step" data-step-indicator="1" role="listitem">
            <div class="wizard-step__marker">
                <div class="wizard-step__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 6v6" />
                        <path d="M8 12h8" />
                        <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z" />
                    </svg>
                </div>
                <span class="wizard-step__number">2</span>
            </div>
            <div class="wizard-step__body">
                <p class="wizard-step__title">Flujo y estado</p>
                <p class="wizard-step__subtitle">Metodología, fase y salud</p>
            </div>
        </div>
        <div class="wizard-step" data-step-indicator="2" role="listitem">
            <div class="wizard-step__marker">
                <div class="wizard-step__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9h18" />
                        <path d="M9 13h6" />
                        <path d="M8 17h8" />
                        <path d="M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                    </svg>
                </div>
                <span class="wizard-step__number">3</span>
            </div>
            <div class="wizard-step__body">
                <p class="wizard-step__title">Planeación</p>
                <p class="wizard-step__subtitle">Horas, presupuesto y fechas</p>
            </div>
        </div>
    </div>

    <div class="wizard-content" data-step="0">
        <div class="step-card">
            <div class="step-card__header">
                <div>
                    <p class="section-label">Paso 1</p>
                    <strong>Contexto base</strong>
                    <p class="muted">Completa los datos clave del proyecto antes de avanzar.</p>
                </div>
                <div class="badge soft-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 4h16v6H4z" />
                        <path d="M4 14h10v6H4z" />
                        <path d="M14 14l6 6" />
                    </svg>
                    Datos base
                </div>
            </div>
            <section class="grid step-card__grid">
                <label class="input">
                    <span>Nombre del proyecto</span>
                    <input type="text" name="name" value="<?= htmlspecialchars((string) $fieldValue('name', '')) ?>" placeholder="Implementación, despliegue, etc." required <?= $canCreateProject ? '' : 'disabled' ?>>
                </label>
                <label class="input">
                    <span>Cliente</span>
                    <select name="client_id" required <?= $canCreateProject ? '' : 'disabled' ?>>
                        <?php if (empty($clientsList)): ?>
                            <option value="">Registra un cliente primero</option>
                        <?php else: ?>
                            <?php foreach ($clientsList as $client): ?>
                                <option value="<?= (int) $client['id'] ?>" <?= $selectedClientId === (int) $client['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name'] ?? 'Cliente') ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </label>
                <label class="input">
                    <span>PM responsable</span>
                    <select name="pm_id" required <?= $canCreateProject ? '' : 'disabled' ?>>
                        <?php if (empty($projectManagersList)): ?>
                            <option value="">Sin PM disponible</option>
                        <?php else: ?>
                            <?php foreach ($projectManagersList as $pm): ?>
                                <option value="<?= (int) $pm['id'] ?>" <?= $selectedPmId === (int) $pm['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pm['name'] ?? 'PM') ?> (<?= htmlspecialchars($pm['role_name'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </label>
                <label class="input">
                    <span>Tipo de proyecto</span>
                    <select name="project_type" id="projectTypeSelect" <?= $canCreateProject ? '' : 'disabled' ?>>
                        <option value="convencional" <?= $selectedProjectType === 'convencional' ? 'selected' : '' ?>>Convencional</option>
                        <option value="scrum" <?= $selectedProjectType === 'scrum' ? 'selected' : '' ?>>Scrum</option>
                        <option value="hibrido" <?= $selectedProjectType === 'hibrido' ? 'selected' : '' ?>>Híbrido</option>
                    </select>
                </label>
                <label class="input">
                    <span>Metodología</span>
                    <select name="methodology_display" id="methodologySelect" required <?= $canCreateProject ? '' : 'disabled' ?>>
                        <?php if (empty($methodologies)): ?>
                            <option value="<?= htmlspecialchars($selectedMethodology) ?>" selected><?= htmlspecialchars(ucfirst($selectedMethodology)) ?></option>
                        <?php else: ?>
                            <?php foreach ($methodologies as $methodology): ?>
                                <option value="<?= htmlspecialchars($methodology) ?>" <?= $selectedMethodology === $methodology ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($methodology)) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <input type="hidden" name="methodology" id="methodologyHidden" value="<?= htmlspecialchars($selectedMethodology) ?>">
                </label>
                <label class="input">
                    <span>Inicio</span>
                    <input type="date" name="start_date" value="<?= htmlspecialchars((string) $fieldValue('start_date', '')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                </label>
                <label class="input" data-role="end-date">
                    <span>Fin</span>
                    <input type="date" name="end_date" id="endDateInput" value="<?= htmlspecialchars((string) $fieldValue('end_date', '')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                </label>
            </section>
            <section class="grid step-card__grid">
                <label class="input">
                    <span>Alcance del proyecto</span>
                    <textarea name="scope" rows="3" placeholder="Descripción resumida del alcance" required <?= $canCreateProject ? '' : 'disabled' ?>><?= htmlspecialchars((string) $fieldValue('scope', '')) ?></textarea>
                </label>
                <label class="input">
                    <span>Entradas de diseño</span>
                    <textarea name="design_inputs" rows="3" placeholder="Requerimientos, insumos y lineamientos iniciales" required <?= $canCreateProject ? '' : 'disabled' ?>><?= htmlspecialchars((string) $fieldValue('design_inputs', '')) ?></textarea>
                </label>
                <label class="input">
                    <span>Participación del cliente</span>
                    <select name="client_participation" required <?= $canCreateProject ? '' : 'disabled' ?>>
                        <option value="alta" <?= $clientParticipation === 'alta' ? 'selected' : '' ?>>Alta (cocreación activa)</option>
                        <option value="media" <?= $clientParticipation === 'media' ? 'selected' : '' ?>>Media (revisiones programadas)</option>
                        <option value="baja" <?= $clientParticipation === 'baja' ? 'selected' : '' ?>>Baja (solo aprobaciones clave)</option>
                    </select>
                </label>
            </section>
            <section class="grid step-card__grid">
                <div class="input" style="grid-column: 1 / -1;">
                    <span>Riesgos iniciales</span>
                    <div id="riskChecklist" style="display:flex; flex-direction:column; gap:10px; margin-top:8px;">
                        <?php foreach ($riskGroups as $category => $risks): ?>
                            <div style="border:1px solid var(--border); border-radius:10px; padding:10px; background: color-mix(in srgb, var(--surface) 94%, transparent);">
                                <strong style="display:block; margin-bottom:6px;"><?= htmlspecialchars($category) ?></strong>
                                <div style="display:flex; flex-wrap:wrap; gap:10px;">
                                    <?php foreach ($risks as $risk): ?>
                                        <?php $riskCode = $risk['code'] ?? ''; $riskLabel = $risk['label'] ?? $riskCode; ?>
                                        <label style="display:flex; gap:6px; align-items:center; background: color-mix(in srgb, var(--surface) 92%, transparent); padding:8px 10px; border-radius:10px; border:1px solid var(--border);">
                                            <input type="checkbox" name="risks[]" value="<?= htmlspecialchars($riskCode) ?>" <?= in_array($riskCode, $fieldValue('risks', []), true) ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($riskLabel) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($riskCatalog)): ?>
                            <span class="muted">Configura el catálogo de riesgos en el módulo de configuración.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="wizard-content" data-step="1">
        <div class="step-card">
            <div class="step-card__header">
                <div>
                    <p class="section-label">Paso 2</p>
                    <strong>Flujo de entrega</strong>
                    <p class="muted">La metodología define la fase y el estado inicial conforme al ciclo ISO 9001 8.3.</p>
                </div>
                <div class="pill soft-slate" id="phaseStatusPill" aria-live="polite"></div>
            </div>
            <div class="grid step-card__grid compact">
                <label class="input">
                    <span>Fase</span>
                    <select name="phase_display" id="phaseSelect" <?= $canCreateProject ? '' : 'disabled' ?>>
                        <option value="">Sin fase</option>
                        <?php foreach ($currentPhases as $phase): ?>
                            <option value="<?= htmlspecialchars($phase) ?>" <?= $selectedPhase === $phase ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($phase)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="phase" id="phaseHidden" value="<?= htmlspecialchars((string) $selectedPhase) ?>">
                </label>
                <label class="input">
                    <span>Estado</span>
                    <select name="status_display" id="statusSelect" required <?= $canCreateProject ? '' : 'disabled' ?>>
                        <?php foreach ($statusesCatalog as $status): ?>
                            <?php $code = $status['code'] ?? ''; ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $selectedStatus === $code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status['label'] ?? $code) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="status" id="statusHidden" value="<?= htmlspecialchars($selectedStatus) ?>">
                </label>
                <label class="input">
                    <span>Salud</span>
                    <select name="health_display" id="healthSelect" disabled <?= $canCreateProject ? '' : 'disabled' ?>>
                        <?php foreach ($healthCatalog as $health): ?>
                            <?php $code = $health['code'] ?? ''; ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $selectedHealth === $code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($health['label'] ?? $code) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="health" id="healthHidden" value="<?= htmlspecialchars($selectedHealth) ?>">
                </label>
                <label class="input">
                    <span>Prioridad</span>
                    <select name="priority" required <?= $canCreateProject ? '' : 'disabled' ?>>
                        <?php foreach ($prioritiesCatalog as $priority): ?>
                            <?php $code = $priority['code'] ?? ''; ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $selectedPriority === $code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($priority['label'] ?? $code) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>
    </div>

    <div class="wizard-content" data-step="2">
        <div class="step-card">
            <div class="step-card__header">
                <div>
                    <p class="section-label">Paso 3</p>
                    <strong>Planeación y seguimiento</strong>
                    <p class="muted">Horas, presupuesto y progreso inicial del proyecto.</p>
                </div>
                <div class="badge soft-green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 20a8 8 0 1 0-8-8" />
                        <path d="M12 6v6l3 3" />
                    </svg>
                    Seguimiento
                </div>
            </div>

            <section class="grid step-card__grid compact">
                <label class="input">
                    <span>Presupuesto plan</span>
                    <input type="number" step="0.01" name="budget" value="<?= htmlspecialchars((string) $fieldValue('budget', '0')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                </label>
                <label class="input">
                    <span>Costo real</span>
                    <input type="number" step="0.01" name="actual_cost" value="<?= htmlspecialchars((string) $fieldValue('actual_cost', '0')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                </label>
                <label class="input">
                    <span>Horas planificadas</span>
                    <input type="number" step="0.1" name="planned_hours" value="<?= htmlspecialchars((string) $fieldValue('planned_hours', '0')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                </label>
                <label class="input">
                    <span>Horas reales</span>
                    <input type="number" step="0.1" name="actual_hours" value="<?= htmlspecialchars((string) $fieldValue('actual_hours', '0')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                </label>
            </section>
        </div>
    </div>

    <div class="wizard-footer">
        <div class="wizard-footer__nav">
            <button type="button" class="btn ghost" data-nav="prev">← Paso anterior</button>
            <span class="muted" aria-live="polite">Paso <span id="wizardStepLabel">1</span> de 3</span>
        </div>
        <div class="wizard-footer__actions">
            <a class="btn ghost" href="<?= $basePath ?>/projects">Cancelar</a>
            <button type="button" class="btn" data-nav="next" <?= $canCreateProject ? '' : 'disabled' ?>>Siguiente</button>
            <button type="submit" class="btn primary" data-nav="submit" <?= $canCreateProject ? '' : 'disabled' ?>>Crear proyecto</button>
        </div>
    </div>
</form>

<style>
    .wizard-shell { display:flex; flex-direction:column; gap:18px; }
    .wizard-header { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .wizard-header__title { display:flex; gap:12px; align-items:flex-start; }
    .wizard-header__icon { width:48px; height:48px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--primary) 12%, transparent); color: var(--primary); border:1px solid color-mix(in srgb, var(--primary) 28%, transparent); }
    .wizard-header__title strong { display:block; margin:2px 0; font-size:18px; color: var(--text-strong); }
    .wizard-header__meta { display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .wizard-steps { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; position:relative; padding:8px 4px; }
    .wizard-step { display:flex; gap:12px; padding:12px; border-radius:12px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 92%, transparent); position:relative; overflow:hidden; }
    .wizard-step::after { content:""; position:absolute; inset:0; background: linear-gradient(120deg, color-mix(in srgb, var(--primary) 10%, transparent), transparent 40%, color-mix(in srgb, var(--primary) 6%, transparent)); opacity:0; transition:opacity 160ms ease; }
    .wizard-step.active::after { opacity:1; }
    .wizard-step__marker { display:flex; align-items:center; gap:10px; position:relative; z-index:1; }
    .wizard-step__icon { width:42px; height:42px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); background: color-mix(in srgb, var(--primary) 8%, transparent); color: var(--primary); }
    .wizard-step__number { width:26px; height:26px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-weight:800; background: #e5e7eb; color: #111827; border:1px solid var(--border); }
    .wizard-step__body { display:flex; flex-direction:column; gap:4px; position:relative; z-index:1; }
    .wizard-step__title { margin:0; font-weight:800; color: var(--text-strong); }
    .wizard-step__subtitle { margin:0; color: var(--muted); font-size:13px; }
    .wizard-step.completed .wizard-step__icon { background: color-mix(in srgb, #16a34a 12%, transparent); color: #15803d; border-color: color-mix(in srgb, #16a34a 40%, transparent); }
    .wizard-step.completed .wizard-step__number { background:#dcfce7; color:#166534; border-color: color-mix(in srgb, #16a34a 50%, transparent); }
    .wizard-step.active { border-color: var(--primary); box-shadow: 0 8px 24px color-mix(in srgb, var(--primary) 8%, transparent); }
    .wizard-step.active .wizard-step__icon { background: color-mix(in srgb, var(--primary) 18%, white 12%); color: var(--primary-strong); border-color: color-mix(in srgb, var(--primary) 35%, transparent); }
    .wizard-step.active .wizard-step__number { background: var(--primary); color: var(--on-primary); border-color: var(--primary); }
    .wizard-content { display:none; flex-direction:column; gap:16px; }
    .wizard-content.active { display:flex; }
    .step-card { border:1px solid var(--border); border-radius:12px; background: color-mix(in srgb, var(--surface) 96%, transparent); padding:16px; display:flex; flex-direction:column; gap:14px; box-shadow: 0 10px 30px color-mix(in srgb, var(--primary) 6%, transparent); }
    .step-card__header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .step-card__grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    .step-card__grid.compact { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .wizard-footer { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-top:1px dashed var(--border); padding-top:12px; }
    .wizard-footer__nav, .wizard-footer__actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .muted { color: var(--muted); font-weight:600; margin:0; }
    .pill { display:inline-flex; align-items:center; padding:8px 12px; border-radius:999px; font-weight:700; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 94%, transparent); }
    .ghosted-pill { background: color-mix(in srgb, var(--surface) 85%, transparent); color: var(--muted); }
    .soft-blue { background:#e0e7ff; color:#1d4ed8; }
    .soft-amber { background:#fef3c7; color:#b45309; }
    .soft-green { background:#dcfce7; color:#15803d; }
    .soft-slate { background:#e5e7eb; color:#374151; }
    .alert { padding:12px 14px; border-radius:10px; margin-bottom:10px; font-weight:700; }
    .alert.error { background:#fee2e2; border:1px solid #fecaca; color:#991b1b; }
    .alert.warning { background:#fef9c3; border:1px solid #fde68a; color:#92400e; }
    @media (max-width: 840px) {
        .wizard-steps { grid-template-columns: 1fr; }
        .wizard-step { align-items:center; }
        .wizard-header__title { width:100%; }
        .wizard-header__meta { justify-content:flex-start; }
    }
</style>

<script>
    const phasesByMethodology = <?= json_encode($phasesByMethodology) ?>;
    const allMethodologies = <?= json_encode($methodologies) ?>;
    const phaseSelect = document.getElementById('phaseSelect');
    const phaseHidden = document.getElementById('phaseHidden');
    const methodologySelect = document.getElementById('methodologySelect');
    const methodologyHidden = document.getElementById('methodologyHidden');
    const phaseStatusPill = document.getElementById('phaseStatusPill');
    const projectTypeSelect = document.getElementById('projectTypeSelect');
    const endDateGroup = document.querySelector('[data-role="end-date"]');
    const endDateInput = document.getElementById('endDateInput');
    const wizardSections = Array.from(document.querySelectorAll('[data-step]'));
    const stepIndicators = Array.from(document.querySelectorAll('[data-step-indicator]'));
    const prevButton = document.querySelector('[data-nav="prev"]');
    const nextButton = document.querySelector('[data-nav="next"]');
    const submitButton = document.querySelector('[data-nav="submit"]');
    const stepLabel = document.getElementById('wizardStepLabel');
    const canCreateProject = <?= $canCreateProject ? 'true' : 'false' ?>;
    const statusSelect = document.getElementById('statusSelect');
    const statusHidden = document.getElementById('statusHidden');
    const healthSelect = document.getElementById('healthSelect');
    const healthHidden = document.getElementById('healthHidden');
    const riskChecklist = document.querySelectorAll('#riskChecklist input[type="checkbox"]');
    const initialRiskGroupPresent = riskChecklist.length > 0;
    const methodologyMap = { convencional: 'cascada', scrum: 'scrum', hibrido: 'kanban' };

    let currentStep = 0;

    function refreshPhases() {
        if (!phaseSelect || !methodologySelect) return;

        const selected = methodologySelect.value;
        const phases = phasesByMethodology[selected] || [];
        const previousValue = phaseSelect.value;

        phaseSelect.innerHTML = '';
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = 'Sin fase';
        phaseSelect.appendChild(emptyOption);

        phases.forEach(phase => {
            const option = document.createElement('option');
            option.value = phase;
            option.textContent = phase.charAt(0).toUpperCase() + phase.slice(1);
            if (phase === previousValue) {
                option.selected = true;
            }
            phaseSelect.appendChild(option);
        });

        const assignedPhase = phases[0] || '';
        if (phaseHidden) {
            phaseHidden.value = assignedPhase;
        }
        phaseSelect.value = assignedPhase;
        phaseSelect.setAttribute('disabled', 'disabled');

        const hasPhases = phases.length > 0;
        if (!hasPhases) {
            phaseStatusPill.textContent = 'Sin fases configuradas: se guardará con valores por defecto';
            phaseStatusPill.className = 'pill soft-amber';
        } else {
            phaseStatusPill.textContent = 'Fases disponibles: ' + phases.length;
            phaseStatusPill.className = 'pill soft-blue';
        }
    }

    function toggleEndDateByType() {
        if (!projectTypeSelect || !endDateGroup) return;
        const selectedType = projectTypeSelect.value;
        if (selectedType === 'scrum') {
            endDateGroup.style.display = 'none';
            if (endDateInput) {
                endDateInput.value = '';
                endDateInput.required = false;
            }
        } else {
            endDateGroup.style.display = '';
            if (endDateInput) {
                endDateInput.required = selectedType === 'convencional';
            }
        }
    }

    function resolveMethodology(type) {
        const preferred = methodologyMap[type] || allMethodologies[0] || 'scrum';
        if (allMethodologies.includes(preferred)) {
            return preferred;
        }
        return allMethodologies[0] || preferred;
    }

    function syncMethodology() {
        if (!projectTypeSelect || !methodologySelect) return;
        const resolved = resolveMethodology(projectTypeSelect.value);
        methodologySelect.value = resolved;
        methodologySelect.setAttribute('disabled', 'disabled');
        if (methodologyHidden) {
            methodologyHidden.value = resolved;
        }
        refreshPhases();
    }

    function syncStatusHealth() {
        if (statusSelect && statusHidden) {
            statusHidden.value = statusSelect.value;
            statusSelect.setAttribute('disabled', 'disabled');
        }
        if (healthSelect && healthHidden) {
            healthHidden.value = healthSelect.value;
            healthSelect.setAttribute('disabled', 'disabled');
        }
    }

    function setStep(index) {
        if (index < 0 || index >= wizardSections.length) return;
        currentStep = index;
        wizardSections.forEach((section, position) => {
            const isActive = position === currentStep;
            section.classList.toggle('active', isActive);
            section.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
        stepIndicators.forEach((indicator, position) => {
            indicator.classList.toggle('active', position === currentStep);
            indicator.classList.toggle('completed', position < currentStep);
        });
        if (prevButton) {
            prevButton.disabled = currentStep === 0 || !canCreateProject;
        }
        if (nextButton) {
            nextButton.style.display = currentStep < wizardSections.length - 1 ? 'inline-flex' : 'none';
            nextButton.disabled = !canCreateProject;
        }
        if (submitButton) {
            submitButton.style.display = currentStep === wizardSections.length - 1 ? 'inline-flex' : 'none';
            submitButton.disabled = !canCreateProject;
        }
        if (stepLabel) {
            stepLabel.textContent = (currentStep + 1).toString();
        }
    }

    function validateStep(index) {
        const section = wizardSections[index];
        if (!section) return true;
        const requiredFields = section.querySelectorAll('[required]');
        for (const field of requiredFields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
        if (index === 0 && initialRiskGroupPresent) {
            const hasRisk = Array.from(riskChecklist).some((checkbox) => checkbox.checked);
            if (!hasRisk) {
                alert('Define al menos un riesgo inicial antes de continuar.');
                return false;
            }
        }
        return true;
    }

    refreshPhases();
    syncMethodology();
    syncStatusHealth();
    toggleEndDateByType();
    setStep(0);

    if (methodologySelect) {
        methodologySelect.addEventListener('change', () => {
            if (methodologyHidden) {
                methodologyHidden.value = methodologySelect.value;
            }
            refreshPhases();
        });
    }

    if (projectTypeSelect) {
        projectTypeSelect.addEventListener('change', () => {
            syncMethodology();
            toggleEndDateByType();
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => setStep(Math.max(0, currentStep - 1)));
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            if (!validateStep(currentStep)) {
                return;
            }
            setStep(Math.min(wizardSections.length - 1, currentStep + 1));
        });
    }
</script>
