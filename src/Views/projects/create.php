<?php
$basePath = $basePath ?? '';
$clientsList = is_array($clients ?? null) ? $clients : [];
$projectManagersList = is_array($projectManagers ?? null) ? $projectManagers : [];
$deliveryConfig = is_array($delivery ?? null) ? $delivery : ['methodologies' => [], 'phases' => [], 'risks' => []];
$prioritiesCatalog = is_array($priorities ?? null) ? $priorities : [];
$statusesCatalog = is_array($statuses ?? null) ? $statuses : [];
$healthCatalog = is_array($healthCatalog ?? null) ? $healthCatalog : [];
$stageOptions = is_array($stageOptions ?? null) ? $stageOptions : [];
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
$riskCategoryOrder = ['Alcance', 'Costos', 'Calidad', 'Tiempo', 'Dependencias'];
$riskCategoryIcons = [
    'Alcance' => '🧭',
    'Costos' => '💸',
    'Calidad' => '✅',
    'Tiempo' => '⏱️',
    'Dependencias' => '🔗',
    'Otros' => '⚠️',
];
$orderedRiskGroups = [];
foreach ($riskCategoryOrder as $category) {
    if (isset($riskGroups[$category])) {
        $orderedRiskGroups[$category] = $riskGroups[$category];
        unset($riskGroups[$category]);
    }
}
foreach ($riskGroups as $category => $risks) {
    $orderedRiskGroups[$category] = $risks;
}

$selectedMethodology = $oldInput['methodology'] ?? $defaults['methodology'] ?? ($methodologies[0] ?? 'scrum');
$currentPhases = is_array($phasesByMethodology[$selectedMethodology] ?? null) ? $phasesByMethodology[$selectedMethodology] : [];
$selectedPhase = $oldInput['phase'] ?? $defaults['phase'] ?? ($currentPhases[0] ?? '');
$selectedClientId = (int) ($oldInput['client_id'] ?? $defaults['client_id'] ?? ($clientsList[0]['id'] ?? 0));
$selectedPmId = (int) ($oldInput['pm_id'] ?? $defaults['pm_id'] ?? ($projectManagersList[0]['id'] ?? 0));
$selectedStatus = (string) ($oldInput['status'] ?? $defaults['status'] ?? ($statusesCatalog[0]['code'] ?? ''));
$selectedHealth = (string) ($oldInput['health'] ?? $defaults['health'] ?? ($healthCatalog[0]['code'] ?? ''));
$selectedPriority = (string) ($oldInput['priority'] ?? $defaults['priority'] ?? ($prioritiesCatalog[0]['code'] ?? ''));
$selectedStage = (string) ($oldInput['project_stage'] ?? $defaults['project_stage'] ?? 'Discovery');
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
        <p style="margin:4px 0 0 0; color: var(--text-secondary);">Wizard guiado para registrar un proyecto sin depender de portafolios.</p>
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
                <p class="wizard-step__title">Datos del proyecto</p>
                <p class="wizard-step__subtitle">Cliente, nombre, responsable</p>
            </div>
        </div>
        <div class="wizard-step" data-step-indicator="1" role="listitem">
            <div class="wizard-step__marker">
                <div class="wizard-step__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9h18" />
                        <path d="M9 13h6" />
                        <path d="M8 17h8" />
                        <path d="M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                    </svg>
                </div>
                <span class="wizard-step__number">2</span>
            </div>
            <div class="wizard-step__body">
                <p class="wizard-step__title">Planeación inicial</p>
                <p class="wizard-step__subtitle">Completa y crea el proyecto</p>
            </div>
        </div>
    </div>

    <div class="wizard-content" data-step="0">
        <div class="step-card">
            <div class="step-card__header">
                <div>
                    <p class="section-label">Paso 1</p>
                    <strong>Datos del proyecto</strong>
                    <p class="muted">Tres bloques claros para capturar lo esencial sin fricción.</p>
                </div>
                <div class="badge soft-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 4h16v6H4z" />
                        <path d="M4 14h10v6H4z" />
                        <path d="M14 14l6 6" />
                    </svg>
                    Paso 1
                </div>
            </div>
            <div class="alert error wizard-validation" id="wizardValidationMessage" role="alert">
                Faltan datos obligatorios para continuar
            </div>
            <details class="accordion step-block step-block--required" open>
                <summary class="accordion-summary">
                    <div>
                        <p class="step-block__eyebrow">Datos generales</p>
                        <strong class="step-block__title">Obligatorios para crear el proyecto</strong>
                        <p class="step-block__help">Completa lo mínimo para habilitar el avance.</p>
                    </div>
                    <span class="pill soft-amber">Obligatorio</span>
                </summary>
                <div class="accordion-body">
                    <section class="grid step-block__grid">
                    <label class="input">
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">📝</span>Nombre del proyecto</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <input type="text" name="name" value="<?= htmlspecialchars((string) $fieldValue('name', '')) ?>" placeholder="Implementación, despliegue, etc." required <?= $canCreateProject ? '' : 'disabled' ?>>
                    </label>
                    <label class="input">
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🧑‍🤝‍🧑</span>Cliente</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
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
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🧑‍💼</span>PM responsable</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
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
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🧩</span>Tipo de proyecto</span>
                        </span>
                        <select name="project_type" id="projectTypeSelect" <?= $canCreateProject ? '' : 'disabled' ?>>
                            <option value="convencional" <?= $selectedProjectType === 'convencional' ? 'selected' : '' ?>>Convencional</option>
                            <option value="scrum" <?= $selectedProjectType === 'scrum' ? 'selected' : '' ?>>Scrum</option>
                            <option value="hibrido" <?= $selectedProjectType === 'hibrido' ? 'selected' : '' ?>>Híbrido</option>
                            <option value="outsourcing" <?= $selectedProjectType === 'outsourcing' ? 'selected' : '' ?>>Outsourcing</option>
                        </select>
                    </label>
                    <label class="input">
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🧭</span>Metodología</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
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
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🧪</span>Stage-gate</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <select name="project_stage" required <?= $canCreateProject ? '' : 'disabled' ?>>
                            <?php foreach ($stageOptions as $stageOption): ?>
                                <option value="<?= htmlspecialchars($stageOption) ?>" <?= $selectedStage === $stageOption ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($stageOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="input">
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">📅</span>Inicio</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <input type="date" name="start_date" value="<?= htmlspecialchars((string) $fieldValue('start_date', '')) ?>" required <?= $canCreateProject ? '' : 'disabled' ?>>
                    </label>
                    <label class="input" data-role="end-date">
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🏁</span>Fin</span>
                        </span>
                        <input type="date" name="end_date" id="endDateInput" value="<?= htmlspecialchars((string) $fieldValue('end_date', '')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                    </label>
                    </section>
                </div>
            </details>

            <details class="accordion step-block step-block--recommended" open>
                <summary class="accordion-summary">
                    <div>
                        <p class="step-block__eyebrow">Metodología</p>
                        <strong class="step-block__title">Contexto de diseño (ISO 9001 8.3)</strong>
                        <p class="step-block__help">Define alcance y entradas para evitar reprocesos.</p>
                    </div>
                    <span class="pill soft-blue">Recomendado</span>
                </summary>
                <div class="accordion-body">
                    <section class="grid step-block__grid">
                    <label class="input">
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🎯</span>Alcance del proyecto</span>
                        </span>
                        <textarea name="scope" rows="3" placeholder="Descripción resumida del alcance" <?= $canCreateProject ? '' : 'disabled' ?>><?= htmlspecialchars((string) $fieldValue('scope', '')) ?></textarea>
                    </label>
                    <label class="input">
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">📐</span>Entradas de diseño</span>
                        </span>
                        <textarea name="design_inputs" rows="3" placeholder="Requerimientos, insumos y lineamientos iniciales" <?= $canCreateProject ? '' : 'disabled' ?>><?= htmlspecialchars((string) $fieldValue('design_inputs', '')) ?></textarea>
                    </label>
                    <label class="input">
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🤝</span>Participación del cliente</span>
                        </span>
                        <select name="client_participation" <?= $canCreateProject ? '' : 'disabled' ?>>
                            <option value="alta" <?= $clientParticipation === 'alta' ? 'selected' : '' ?>>Alta (cocreación activa)</option>
                            <option value="media" <?= $clientParticipation === 'media' ? 'selected' : '' ?>>Media (revisiones programadas)</option>
                            <option value="baja" <?= $clientParticipation === 'baja' ? 'selected' : '' ?>>Baja (solo aprobaciones clave)</option>
                        </select>
                    </label>
                    </section>
                </div>
            </details>

            <details class="accordion step-block step-block--optional" open>
                <summary class="accordion-summary">
                    <div>
                        <p class="step-block__eyebrow">Riesgos</p>
                        <strong class="step-block__title">Opcional</strong>
                        <p class="step-block__help">Selecciona riesgos relevantes para monitorear desde el inicio.</p>
                    </div>
                    <div class="risk-summary">
                        <span class="pill soft-slate" id="riskCount">0 seleccionados</span>
                    </div>
                </summary>
                <div class="accordion-body">
                    <div class="risk-grid" id="riskChecklist">
                    <?php foreach ($orderedRiskGroups as $category => $risks): ?>
                        <?php $categoryIcon = $riskCategoryIcons[$category] ?? $riskCategoryIcons['Otros']; ?>
                        <div class="risk-group" data-limit="5">
                            <div class="risk-group__header">
                                <span class="risk-group__icon" aria-hidden="true"><?= htmlspecialchars($categoryIcon) ?></span>
                                <strong><?= htmlspecialchars($category) ?></strong>
                            </div>
                            <div class="risk-group__list">
                                <?php foreach ($risks as $risk): ?>
                                    <?php $riskCode = $risk['code'] ?? ''; $riskLabel = $risk['label'] ?? $riskCode; ?>
                                    <label class="risk-chip">
                                        <input type="checkbox" name="risks[]" value="<?= htmlspecialchars($riskCode) ?>" <?= in_array($riskCode, $fieldValue('risks', []), true) ? 'checked' : '' ?>>
                                        <span class="risk-chip__content">
                                            <span class="risk-chip__icon" aria-hidden="true"><?= htmlspecialchars($categoryIcon) ?></span>
                                            <span class="risk-chip__label"><?= htmlspecialchars($riskLabel) ?></span>
                                        </span>
                                        <span class="risk-chip__check" aria-hidden="true">✓</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn ghost small risk-group__toggle">Ver más</button>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($riskCatalog)): ?>
                        <span class="muted">Configura el catálogo de riesgos en el módulo de configuración.</span>
                    <?php endif; ?>
                    </div>
                </div>
            </details>
        </div>
    </div>

    <div class="wizard-hidden-step" aria-hidden="true">
        <select name="phase_display" id="phaseSelect" <?= $canCreateProject ? '' : 'disabled' ?>>
            <option value="">Sin fase</option>
            <?php foreach ($currentPhases as $phase): ?>
                <option value="<?= htmlspecialchars($phase) ?>" <?= $selectedPhase === $phase ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($phase)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="phase" id="phaseHidden" value="<?= htmlspecialchars((string) $selectedPhase) ?>">
        <select name="status_display" id="statusSelect" <?= $canCreateProject ? '' : 'disabled' ?>>
            <?php foreach ($statusesCatalog as $status): ?>
                <?php $code = $status['code'] ?? ''; ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= $selectedStatus === $code ? 'selected' : '' ?>>
                    <?= htmlspecialchars($status['label'] ?? $code) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="status" id="statusHidden" value="<?= htmlspecialchars($selectedStatus) ?>">
        <select name="health_display" id="healthSelect" disabled <?= $canCreateProject ? '' : 'disabled' ?>>
            <?php foreach ($healthCatalog as $health): ?>
                <?php $code = $health['code'] ?? ''; ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= $selectedHealth === $code ? 'selected' : '' ?>>
                    <?= htmlspecialchars($health['label'] ?? $code) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="health" id="healthHidden" value="<?= htmlspecialchars($selectedHealth) ?>">
        <input type="hidden" name="priority" id="priorityHidden" value="<?= htmlspecialchars($selectedPriority) ?>">
        <div class="pill soft-slate" id="phaseStatusPill" aria-live="polite"></div>
    </div>

    <div class="wizard-content" data-step="1">
        <div class="step-card">
            <div class="step-card__header">
                <div>
                    <p class="section-label">Paso 2</p>
                    <strong>Planeación inicial + Crear proyecto</strong>
                    <p class="muted">Define la planeación mínima y confirma la creación.</p>
                </div>
                <div class="badge soft-green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 20a8 8 0 1 0-8-8" />
                        <path d="M12 6v6l3 3" />
                    </svg>
                    Crear proyecto
                </div>
            </div>
            <div class="alert warning wizard-docs-note">
                Las evidencias, cotizaciones y planes se cargan después en el expediente del proyecto (carpeta principal &rarr; Evidencias).
            </div>

            <details class="accordion" open>
                <summary class="accordion-summary">
                    <div>
                        <p class="step-block__eyebrow">Fechas y presupuesto</p>
                        <strong class="step-block__title">Planeación mínima</strong>
                    </div>
                </summary>
                <div class="accordion-body">
                    <section class="grid step-card__grid compact">
                        <label class="input">
                            <span class="field-label">
                                <span class="field-title"><span class="field-icon">💰</span>Presupuesto plan</span>
                            </span>
                            <input type="number" step="0.01" name="budget" value="<?= htmlspecialchars((string) $fieldValue('budget', '0')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                        </label>
                        <label class="input">
                            <span class="field-label">
                                <span class="field-title"><span class="field-icon">⏱️</span>Horas planificadas</span>
                            </span>
                            <input type="number" step="0.1" name="planned_hours" value="<?= htmlspecialchars((string) $fieldValue('planned_hours', '0')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                        </label>
                    </section>
                </div>
            </details>
            <details class="accordion">
                <summary class="accordion-summary">
                    <div>
                        <p class="step-block__eyebrow">Zona crítica</p>
                        <strong class="step-block__title">Buenas prácticas de control</strong>
                    </div>
                </summary>
                <div class="accordion-body">
                    <div class="alert warning">
                        Los ajustes críticos (eliminación, cambios masivos o cierres) se habilitan luego de crear el proyecto y validar dependencias.
                    </div>
                </div>
            </details>
        </div>
    </div>

    <div class="wizard-footer">
        <div class="wizard-footer__nav">
            <button type="button" class="btn ghost" data-nav="prev">← Paso anterior</button>
            <span class="muted" aria-live="polite">Paso <span id="wizardStepLabel">1</span> de 2</span>
        </div>
        <div class="wizard-footer__actions">
            <a class="btn ghost" href="<?= $basePath ?>/projects">Cancelar</a>
            <button type="button" class="btn" data-nav="next" <?= $canCreateProject ? '' : 'disabled' ?>>Siguiente</button>
            <button type="button" class="btn primary" data-nav="submit" <?= $canCreateProject ? '' : 'disabled' ?>>Crear proyecto</button>
        </div>
    </div>
</form>
<div class="wizard-loader" id="wizardLoader" aria-live="assertive" aria-hidden="true">
    <div class="wizard-loader__card">
        <span class="wizard-loader__spinner" aria-hidden="true"></span>
        <div>
            <strong>Creando proyecto y estructura…</strong>
            <p class="muted">Por favor espera mientras preparamos todo.</p>
        </div>
    </div>
</div>

<style>
    .wizard-shell { display:flex; flex-direction:column; gap:18px; }
    .wizard-header { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .wizard-header__title { display:flex; gap:12px; align-items:flex-start; }
    .wizard-header__icon { width:48px; height:48px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--primary) 12%, var(--background)); color: var(--primary); border:1px solid color-mix(in srgb, var(--primary) 28%, var(--background)); }
    .wizard-header__title strong { display:block; margin:2px 0; font-size:18px; color: var(--text-primary); }
    .wizard-header__meta { display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .wizard-steps { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; position:relative; padding:8px 4px; }
    .wizard-step { display:flex; gap:12px; padding:12px; border-radius:12px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 92%, var(--background)); position:relative; overflow:hidden; }
    .wizard-step::after { content:""; position:absolute; inset:0; background: color-mix(in srgb, var(--primary) 8%, var(--background) 92%); opacity:0; transition:opacity 160ms ease; }
    .wizard-step.active::after { opacity:1; }
    .wizard-step__marker { display:flex; align-items:center; gap:10px; position:relative; z-index:1; }
    .wizard-step__icon { width:42px; height:42px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border); background: color-mix(in srgb, var(--primary) 8%, var(--background)); color: var(--primary); }
    .wizard-step__number { width:26px; height:26px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-weight:800; background: color-mix(in srgb, var(--neutral) 20%, var(--surface) 80%); color: var(--text-primary); border:1px solid var(--border); }
    .wizard-step__body { display:flex; flex-direction:column; gap:4px; position:relative; z-index:1; }
    .wizard-step__title { margin:0; font-weight:800; color: var(--text-primary); }
    .wizard-step__subtitle { margin:0; color: var(--text-secondary); font-size:13px; }
    .wizard-step.completed .wizard-step__icon { background: color-mix(in srgb, var(--success) 12%, var(--background)); color: var(--success); border-color: color-mix(in srgb, var(--success) 40%, var(--background)); }
    .wizard-step.completed .wizard-step__number { background:color-mix(in srgb, var(--success) 15%, var(--surface) 85%); color:var(--success); border-color: color-mix(in srgb, var(--success) 50%, var(--background)); }
    .wizard-step.active { border-color: var(--primary); box-shadow: 0 8px 24px color-mix(in srgb, var(--primary) 8%, var(--background)); }
    .wizard-step.active .wizard-step__icon { background: color-mix(in srgb, var(--primary) 18%, var(--surface) 12%); color: color-mix(in srgb, var(--primary) 78%, var(--secondary) 22%); border-color: color-mix(in srgb, var(--primary) 35%, var(--background)); }
    .wizard-step.active .wizard-step__number { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .wizard-content { display:none; flex-direction:column; gap:16px; }
    .wizard-content.active { display:flex; }
    .step-card { border:1px solid var(--border); border-radius:12px; background: color-mix(in srgb, var(--surface) 96%, var(--background)); padding:16px; display:flex; flex-direction:column; gap:14px; box-shadow: 0 10px 30px color-mix(in srgb, var(--primary) 6%, var(--background)); }
    .step-card__header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .step-card__grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    .step-card__grid.compact { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .step-block { border-radius:12px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 92%, var(--background)); display:flex; flex-direction:column; gap:12px; }
    .accordion { border:1px solid var(--border); border-radius:12px; background: color-mix(in srgb, var(--surface) 94%, var(--background)); padding:0; }
    .accordion-summary { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; cursor:pointer; list-style:none; padding:12px; }
    .accordion-summary::-webkit-details-marker { display:none; }
    .accordion-body { padding:0 12px 12px; display:flex; flex-direction:column; gap:12px; }
    .step-block__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
    .step-block__eyebrow { margin:0; font-weight:800; font-size:12px; letter-spacing:0.04em; text-transform:uppercase; color: var(--text-secondary); }
    .step-block__title { display:block; font-size:16px; color: var(--text-primary); }
    .step-block__help { margin:4px 0 0 0; font-size:13px; color: var(--text-secondary); }
    .step-block--required { background: color-mix(in srgb, var(--warning) 16%, var(--surface)); border-color: color-mix(in srgb, var(--warning) 35%, var(--border)); }
    .step-block--recommended { background: color-mix(in srgb, var(--info) 24%, var(--surface)); border-color: color-mix(in srgb, var(--info) 30%, var(--border)); }
    .step-block--optional { background: color-mix(in srgb, var(--success) 22%, var(--surface)); border-color: color-mix(in srgb, var(--success) 24%, var(--border)); }
    .field-label { display:flex; align-items:center; justify-content:space-between; gap:12px; font-weight:700; color: var(--text-primary); }
    .field-title { display:flex; align-items:center; gap:8px; }
    .field-icon { display:inline-flex; width:22px; height:22px; border-radius:6px; align-items:center; justify-content:center; background: color-mix(in srgb, var(--primary) 10%, var(--background)); font-size:14px; }
    .field-required { display:inline-flex; align-items:center; gap:4px; font-size:11px; color: var(--danger); background: color-mix(in srgb, var(--danger) 20%, var(--background)); padding:2px 6px; border-radius:999px; border:1px solid color-mix(in srgb, var(--danger) 45%, var(--background)); }
    .field-required__icon { font-size:12px; }
    .input.is-invalid input,
    .input.is-invalid select,
    .input.is-invalid textarea { border-color:var(--danger) !important; box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger) 25%, var(--background)); }
    .input.is-invalid .field-title { color:var(--danger); }
    .wizard-validation { display:none; }
    .wizard-validation.is-visible { display:block; }
    .wizard-docs-note { margin-top:4px; }
    .risk-grid { display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    .risk-group { padding:12px; border-radius:12px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 95%, var(--background)); display:flex; flex-direction:column; gap:10px; }
    .risk-group__header { display:flex; align-items:center; gap:8px; font-weight:800; color: var(--text-primary); }
    .risk-group__icon { width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--primary) 12%, var(--background)); }
    .risk-group__list { display:grid; gap:8px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
    .risk-chip { position:relative; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; border-radius:999px; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 92%, var(--background)); cursor:pointer; transition: all 160ms ease; }
    .risk-chip:hover { border-color: color-mix(in srgb, var(--primary) 40%, var(--border)); box-shadow: 0 6px 14px color-mix(in srgb, var(--primary) 12%, var(--background)); }
    .risk-chip input { position:absolute; opacity:0; pointer-events:none; }
    .risk-chip__content { display:flex; align-items:center; gap:8px; font-weight:600; color: var(--text-primary); }
    .risk-chip__icon { width:22px; height:22px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--primary) 10%, var(--background)); font-size:13px; }
    .risk-chip__check { opacity:0; font-weight:900; color: var(--primary); }
    .risk-chip input:checked ~ .risk-chip__check { opacity:1; }
    .risk-chip input:checked ~ .risk-chip__content { color: color-mix(in srgb, var(--primary) 78%, var(--secondary) 22%); }
    .risk-chip input:checked ~ .risk-chip__content .risk-chip__icon { background: color-mix(in srgb, var(--primary) 18%, var(--background)); }
    .risk-chip.is-hidden { display:none; }
    .risk-group__toggle { align-self:flex-start; }
    .btn.small { padding:6px 10px; font-size:12px; }
    .wizard-footer { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-top:1px dashed var(--border); padding-top:12px; }
    .wizard-footer__nav, .wizard-footer__actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .muted { color: var(--text-secondary); font-weight:600; margin:0; }
    .pill { display:inline-flex; align-items:center; padding:8px 12px; border-radius:999px; font-weight:700; border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 94%, var(--background)); }
    .ghosted-pill { background: color-mix(in srgb, var(--surface) 85%, var(--background)); color: var(--text-secondary); }
    .soft-blue { background:color-mix(in srgb, var(--info) 18%, var(--surface) 82%); color:var(--info); }
    .soft-amber { background:color-mix(in srgb, var(--warning) 18%, var(--surface) 82%); color:var(--warning); }
    .soft-green { background:color-mix(in srgb, var(--success) 18%, var(--surface) 82%); color:var(--success); }
    .soft-slate { background:color-mix(in srgb, var(--neutral) 18%, var(--surface) 82%); color:var(--text-secondary); }
    .alert { padding:12px 14px; border-radius:10px; margin-bottom:10px; font-weight:700; }
    .alert.error { background:color-mix(in srgb, var(--danger) 15%, var(--surface) 85%); border:1px solid color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); color:var(--danger); }
    .alert.warning { background:color-mix(in srgb, var(--warning) 15%, var(--surface) 85%); border:1px solid color-mix(in srgb, var(--warning) 40%, var(--surface) 60%); color:var(--warning); }
    .wizard-hidden-step { display:none; }
    .wizard-loader { position:fixed; inset:0; background: color-mix(in srgb, var(--text-primary) 45%, var(--background)); display:none; align-items:center; justify-content:center; z-index:9999; padding:24px; }
    .wizard-loader.is-visible { display:flex; }
    .wizard-loader__card { background: var(--surface); border:1px solid var(--border); border-radius:16px; padding:20px 22px; display:flex; align-items:center; gap:14px; box-shadow: 0 18px 40px color-mix(in srgb, var(--text-primary) 35%, var(--background)); }
    .wizard-loader__spinner { width:28px; height:28px; border-radius:50%; border:3px solid color-mix(in srgb, var(--primary) 25%, var(--background)); border-top-color: var(--primary); animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
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
    const riskCount = document.getElementById('riskCount');
    const riskGroups = document.querySelectorAll('.risk-group');
    const wizardValidationMessage = document.getElementById('wizardValidationMessage');
    const wizardForm = document.getElementById('projectWizardForm');
    const wizardLoader = document.getElementById('wizardLoader');
    const methodologyMap = { convencional: 'cascada', scrum: 'scrum', hibrido: 'kanban', outsourcing: 'cascada' };

    let currentStep = 0;
    let hasValidatedStep0 = false;
    const step0RequiredFields = [
        { name: 'name', label: 'Nombre del proyecto' },
        { name: 'client_id', label: 'Cliente' },
        { name: 'pm_id', label: 'PM responsable' },
        { name: 'methodology_display', label: 'Metodología' },
        { name: 'start_date', label: 'Fecha de inicio' },
    ];

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
        console.log('[Wizard] Cambio de paso:', currentStep + 1);
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
        updateNavState();
    }

    function getStep0FieldNode(name) {
        return document.querySelector(`[name="${name}"]`);
    }

    function updateValidationMessage(isValid) {
        if (!wizardValidationMessage) return;
        const shouldShow = hasValidatedStep0 && !isValid;
        wizardValidationMessage.classList.toggle('is-visible', shouldShow);
    }

    function clearStep0FieldErrors() {
        step0RequiredFields.forEach(({ name }) => {
            const field = getStep0FieldNode(name);
            if (!field) return;
            const wrapper = field.closest('.input');
            if (wrapper) {
                wrapper.classList.remove('is-invalid');
            }
        });
    }

    function handleStep0FieldChange(field) {
        if (!field) return;
        const isStep0Field = step0RequiredFields.some(({ name }) => name === field.name);
        if (!isStep0Field) return;
        if (field.value.trim() !== '') {
            field.setCustomValidity('');
            const wrapper = field.closest('.input');
            if (wrapper) {
                wrapper.classList.remove('is-invalid');
            }
        }
    }

    function isStep0Valid() {
        return step0RequiredFields.every(({ name }) => {
            const field = getStep0FieldNode(name);
            return field ? field.value.trim() !== '' : false;
        });
    }

    function validateStep0() {
        let firstInvalidField = null;
        const missingFields = [];
        clearStep0FieldErrors();
        hasValidatedStep0 = true;

        step0RequiredFields.forEach(({ name, label }) => {
            const field = getStep0FieldNode(name);
            if (!field) {
                missingFields.push(label);
                return;
            }
            const fieldValue = field.value.trim();
            if (!fieldValue) {
                field.setCustomValidity(`Completa el campo obligatorio: ${label}.`);
                missingFields.push(label);
                if (!firstInvalidField && !field.disabled) {
                    firstInvalidField = field;
                }
                const wrapper = field.closest('.input');
                if (wrapper) {
                    wrapper.classList.add('is-invalid');
                }
            } else {
                field.setCustomValidity('');
            }
        });

        if (missingFields.length > 0) {
            console.warn('[Wizard] Validación Paso 1 fallida. Faltan:', missingFields);
            updateValidationMessage(false);
            if (firstInvalidField) {
                firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalidField.reportValidity();
            }
            return false;
        }

        updateValidationMessage(true);
        console.log('[Wizard] Validación Paso 1 OK.');
        return true;
    }

    function validateStep(index) {
        if (index === 0) {
            return validateStep0();
        }
        const section = wizardSections[index];
        if (!section) return true;
        const requiredFields = section.querySelectorAll('[required]');
        for (const field of requiredFields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
        return true;
    }

    function isStepValid(index) {
        if (index === 0) {
            return isStep0Valid();
        }
        const section = wizardSections[index];
        if (!section) return true;
        const requiredFields = section.querySelectorAll('[required]');
        return Array.from(requiredFields).every((field) => field.checkValidity());
    }

    function updateNavState() {
        if (!canCreateProject) return;
        const stepValid = isStepValid(currentStep);
        updateValidationMessage(stepValid || currentStep !== 0);
        if (nextButton) {
            nextButton.disabled = false;
        }
        if (submitButton) {
            submitButton.disabled = false;
        }
    }

    function submitProjectCreation() {
        if (!wizardForm) return;
        const stepValid = validateStep(currentStep);
        if (!stepValid) {
            return;
        }
        if (!wizardForm.reportValidity()) {
            return;
        }
        if (wizardLoader) {
            wizardLoader.classList.add('is-visible');
            wizardLoader.setAttribute('aria-hidden', 'false');
        }
        const actionButtons = wizardForm.querySelectorAll('button, a.btn');
        actionButtons.forEach((button) => {
            button.setAttribute('aria-disabled', 'true');
            if (button.tagName === 'BUTTON') {
                button.disabled = true;
            }
        });
        if (typeof wizardForm.requestSubmit === 'function') {
            wizardForm.requestSubmit();
        } else {
            wizardForm.submit();
        }
    }

    function updateRiskCount() {
        if (!riskCount) return;
        const selected = Array.from(riskChecklist).filter((checkbox) => checkbox.checked).length;
        riskCount.textContent = `${selected} seleccionados`;
    }

    function configureRiskGroups() {
        riskGroups.forEach((group) => {
            const limit = Number.parseInt(group.dataset.limit || '5', 10);
            const items = Array.from(group.querySelectorAll('.risk-chip'));
            const toggle = group.querySelector('.risk-group__toggle');
            if (items.length > limit) {
                items.slice(limit).forEach((item) => item.classList.add('is-hidden'));
                if (toggle) {
                    toggle.dataset.expanded = 'false';
                    toggle.textContent = 'Ver más';
                    toggle.style.display = 'inline-flex';
                    toggle.addEventListener('click', () => {
                        const expanded = toggle.dataset.expanded === 'true';
                        toggle.dataset.expanded = expanded ? 'false' : 'true';
                        items.slice(limit).forEach((item) => item.classList.toggle('is-hidden', expanded));
                        toggle.textContent = expanded ? 'Ver más' : 'Ver menos';
                    });
                }
            } else if (toggle) {
                toggle.style.display = 'none';
            }
        });
    }

    refreshPhases();
    syncMethodology();
    syncStatusHealth();
    toggleEndDateByType();
    setStep(0);
    updateRiskCount();
    configureRiskGroups();
    updateNavState();

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
            updateNavState();
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => setStep(Math.max(0, currentStep - 1)));
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            const stepValid = validateStep(currentStep);
            console.log('[Wizard] Click Siguiente. Paso válido:', stepValid);
            if (!stepValid) {
                return;
            }
            setStep(Math.min(wizardSections.length - 1, currentStep + 1));
        });
    }

    if (submitButton) {
        submitButton.addEventListener('click', () => {
            submitProjectCreation();
        });
    }

    wizardSections.forEach((section) => {
        section.querySelectorAll('input, select, textarea').forEach((field) => {
            field.addEventListener('input', () => {
                handleStep0FieldChange(field);
                updateNavState();
            });
            field.addEventListener('change', () => {
                handleStep0FieldChange(field);
                updateNavState();
                updateRiskCount();
            });
        });
    });

    if (wizardForm) {
        wizardForm.addEventListener('submit', () => {
            if (wizardLoader) {
                wizardLoader.classList.add('is-visible');
                wizardLoader.setAttribute('aria-hidden', 'false');
            }
            const actionButtons = wizardForm.querySelectorAll('button, a.btn');
            actionButtons.forEach((button) => {
                button.setAttribute('aria-disabled', 'true');
                if (button.tagName === 'BUTTON') {
                    button.disabled = true;
                }
            });
        });
    }
</script>
