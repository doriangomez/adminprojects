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
$riskCategoryOrder = ['Alcance', 'Costos', 'Calidad', 'Cronograma', 'Legal', 'Operaciones', 'Recursos', 'Stakeholders', 'Tecnología'];
$riskCategorySlugs = [
    'Alcance' => 'alcance',
    'Costos' => 'costos',
    'Calidad' => 'calidad',
    'Cronograma' => 'cronograma',
    'Tiempo' => 'cronograma',
    'Legal' => 'legal',
    'Operaciones' => 'operaciones',
    'Recursos' => 'recursos',
    'Stakeholders' => 'stakeholders',
    'Tecnología' => 'tecnologia',
    'Tecnologia' => 'tecnologia',
    'Dependencias' => 'operaciones',
    'Otros' => 'alcance',
];
$riskCategoryIcons = [
    'Alcance' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 4v10l-7 4-7-4V7l7-4Z"/><path d="M12 8v8"/><path d="M8 10l4-2 4 2"/></svg>',
    'Costos' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/></svg>',
    'Calidad' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/><path d="M18 18H6"/></svg>',
    'Cronograma' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'Tiempo' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'Legal' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M6 7h12"/><path d="m6 7-3 6h6L6 7Z"/><path d="m18 7-3 6h6l-3-6Z"/></svg>',
    'Operaciones' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2 3-.2-.1a1.8 1.8 0 0 0-2 .2 1.7 1.7 0 0 0-.8 1.6V22H9.2v-.3a1.7 1.7 0 0 0-.8-1.6 1.8 1.8 0 0 0-2-.2l-.2.1-2-3 .1-.1A1.7 1.7 0 0 0 4.6 15 1.7 1.7 0 0 0 3 13.8H2v-3.6h1a1.7 1.7 0 0 0 1.6-1.2 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2-3 .2.1a1.8 1.8 0 0 0 2-.2 1.7 1.7 0 0 0 .8-1.6V2h5.6v.3a1.7 1.7 0 0 0 .8 1.6 1.8 1.8 0 0 0 2 .2l.2-.1 2 3-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1.2h1v3.6h-1a1.7 1.7 0 0 0-1.6 1.2Z"/></svg>',
    'Recursos' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.8"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></svg>',
    'Stakeholders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M17 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M3 21a4 4 0 0 1 8 0"/><path d="M13 21a4 4 0 0 1 8 0"/></svg>',
    'Tecnología' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="11" rx="2"/><path d="M8 21h8"/><path d="M12 16v5"/></svg>',
    'Tecnologia' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="11" rx="2"/><path d="M8 21h8"/><path d="M12 16v5"/></svg>',
    'Dependencias' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/></svg>',
    'Otros' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>',
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
        <h3 class="project-create-title" style="margin:8px 0 0 0;">Nuevo proyecto</h3>
        <p class="project-create-subtitle" style="margin:4px 0 0 0; color: var(--text-secondary);">Wizard guiado para registrar un proyecto sin depender de portafolios.</p>
    </div>
    <div class="pill soft-blue" style="align-self:center; gap:8px; display:inline-flex; align-items:center;">
        <span aria-hidden="true">🧭</span>
        Metodologías desde configuración
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($debugError) && is_array($debugError)): ?>
    <div class="alert error" style="margin-top: 12px; white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
        <strong>Debug creación de proyecto</strong>
        <pre style="margin-top:8px;"><?= htmlspecialchars((string) json_encode($debugError, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    </div>
<?php endif; ?>

<div class="alert error" id="serverResponseAlert" style="display:none; margin-top: 12px;">
    <strong id="serverResponseTitle" style="display:block; margin-bottom:8px;"></strong>
    <a id="serverResponseLink" href="#" target="_self" rel="noopener" style="display:none; margin-bottom:8px;"></a>
    <pre id="serverResponseBody" style="margin:0; white-space:pre-wrap; word-break:break-word; max-height:280px; overflow:auto;"></pre>
</div>

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
                    <div class="section-heading">
                        <span class="section-heading__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h10"/></svg>
                        </span>
                        <div>
                        <p class="step-block__eyebrow">Datos generales</p>
                        <strong class="step-block__title">Información básica</strong>
                        <p class="step-block__help">Identifica el proyecto, cliente, responsable y fechas obligatorias.</p>
                        </div>
                    </div>
                    <span class="section-status section-status--required">Obligatorio</span>
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
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <select name="project_type" id="projectTypeSelect" required <?= $canCreateProject ? '' : 'disabled' ?>>
                            <option value="convencional" <?= $selectedProjectType === 'convencional' ? 'selected' : '' ?>>Convencional</option>
                            <option value="scrum" <?= $selectedProjectType === 'scrum' ? 'selected' : '' ?>>Scrum</option>
                            <option value="hibrido" <?= $selectedProjectType === 'hibrido' ? 'selected' : '' ?>>Híbrido</option>
                            <option value="outsourcing" <?= $selectedProjectType === 'outsourcing' ? 'selected' : '' ?>>Outsourcing</option>
                            <option value="poc" <?= $selectedProjectType === 'poc' ? 'selected' : '' ?>>POC</option>
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
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <input type="date" name="end_date" id="endDateInput" min="<?= htmlspecialchars((string) $fieldValue('start_date', '')) ?>" value="<?= htmlspecialchars((string) $fieldValue('end_date', '')) ?>" required <?= $canCreateProject ? '' : 'disabled' ?>>
                    </label>
                    </section>
                </div>
            </details>

            <details class="accordion step-block step-block--recommended" open>
                <summary class="accordion-summary">
                    <div>
                        <p class="step-block__eyebrow">Metodología</p>
                        <strong class="step-block__title">Diseño y contexto ejecutivo</strong>
                        <p class="step-block__help">Define alcance, entradas y participación sin saturar la pantalla.</p>
                    </div>
                    <span class="pill soft-slate">Contexto</span>
                </summary>
                <div class="accordion-body">
                    <section class="grid step-block__grid">
                    <label class="input" data-standard-only>
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🎯</span>Alcance del proyecto</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <textarea name="scope" rows="3" placeholder="Descripción resumida del alcance" required data-poc-optional-field="1" <?= $canCreateProject ? '' : 'disabled' ?>><?= htmlspecialchars((string) $fieldValue('scope', '')) ?></textarea>
                    </label>
                    <label class="input" data-standard-only>
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">📐</span>Entradas de diseño</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <textarea name="design_inputs" rows="3" placeholder="Requerimientos, insumos y lineamientos iniciales" required data-poc-optional-field="1" <?= $canCreateProject ? '' : 'disabled' ?>><?= htmlspecialchars((string) $fieldValue('design_inputs', '')) ?></textarea>
                    </label>
                    </section>
                </div>
            </details>

            <details class="accordion step-block step-block--poc" data-poc-section hidden open>
                <summary class="accordion-summary">
                    <div>
                        <p class="step-block__eyebrow">POC</p>
                        <strong class="step-block__title">Datos específicos de prueba de concepto</strong>
                        <p class="step-block__help">Este bloque aparece solo cuando el tipo de proyecto es POC.</p>
                    </div>
                    <span class="pill soft-purple">Solo POC</span>
                </summary>
                <div class="accordion-body">
                    <section class="grid step-block__grid">
                    <label class="input" data-poc-only hidden>
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🙋</span>Solicitante de la POC</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <input type="text" name="solicitante_poc" value="<?= htmlspecialchars((string) $fieldValue('solicitante_poc', '')) ?>" placeholder="Nombre del solicitante" <?= $canCreateProject ? '' : 'disabled' ?>>
                    </label>
                    <label class="input" data-poc-only hidden>
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">📆</span>Fecha de solicitud</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <input type="date" name="fecha_solicitud_poc" value="<?= htmlspecialchars((string) $fieldValue('fecha_solicitud_poc', '')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                    </label>
                    <label class="input" data-poc-only hidden>
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🧪</span>Tipo de POC</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <select name="tipo_poc" <?= $canCreateProject ? '' : 'disabled' ?>>
                            <option value="">Seleccionar</option>
                            <option value="gratuita" <?= (string) $fieldValue('tipo_poc', '') === 'gratuita' ? 'selected' : '' ?>>Gratuita</option>
                            <option value="con_costo" <?= (string) $fieldValue('tipo_poc', '') === 'con_costo' ? 'selected' : '' ?>>Con costo</option>
                        </select>
                    </label>
                    <label class="input" data-poc-only hidden>
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🧾</span>Descripción / alcance de la POC</span>
                            <span class="field-required" aria-hidden="true"><span class="field-required__icon">✳️</span>*</span>
                        </span>
                        <textarea name="descripcion_alcance_poc" rows="3" placeholder="Describe alcance, hipótesis y objetivos" <?= $canCreateProject ? '' : 'disabled' ?>><?= htmlspecialchars((string) $fieldValue('descripcion_alcance_poc', '')) ?></textarea>
                    </label>
                    <label class="input" data-poc-only hidden>
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">💵</span>Valor estimado (opcional)</span>
                        </span>
                        <input type="number" step="0.01" min="0" name="valor_estimado_poc" value="<?= htmlspecialchars((string) $fieldValue('valor_estimado_poc', '')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                    </label>
                    <label class="input" data-poc-only hidden>
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🔗</span>Repositorio Git (URL) (opcional)</span>
                        </span>
                        <input type="url" name="repositorio_git_poc" value="<?= htmlspecialchars((string) $fieldValue('repositorio_git_poc', '')) ?>" placeholder="https://github.com/organizacion/repo" <?= $canCreateProject ? '' : 'disabled' ?>>
                    </label>
                    <label class="input" data-poc-only hidden>
                        <span class="field-label">
                            <span class="field-title"><span class="field-icon">🏁</span>Resultado (opcional)</span>
                        </span>
                        <select name="resultado_poc" <?= $canCreateProject ? '' : 'disabled' ?>>
                            <option value="">Sin resultado</option>
                            <option value="en_curso" <?= (string) $fieldValue('resultado_poc', '') === 'en_curso' ? 'selected' : '' ?>>En curso</option>
                            <option value="exitosa" <?= (string) $fieldValue('resultado_poc', '') === 'exitosa' ? 'selected' : '' ?>>Exitosa</option>
                            <option value="no_exitosa" <?= (string) $fieldValue('resultado_poc', '') === 'no_exitosa' ? 'selected' : '' ?>>No exitosa</option>
                        </select>
                    </label>
                    </section>
                </div>
            </details>

            <details class="accordion step-block step-block--method" open>
                <summary class="accordion-summary">
                    <div class="section-heading">
                        <span class="section-heading__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M7 12h10"/><path d="M9 17h6"/></svg>
                        </span>
                        <div>
                        <p class="step-block__eyebrow">Metodología</p>
                        <strong class="step-block__title">Participación del cliente</strong>
                        <p class="step-block__help">Define el nivel de involucramiento esperado.</p>
                        </div>
                    </div>
                    <span class="section-status section-status--recommended">Recomendado</span>
                </summary>
                <div class="accordion-body">
                    <section class="grid step-block__grid">
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
                    <div class="section-heading">
                        <span class="section-heading__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                        </span>
                        <div>
                        <p class="step-block__eyebrow">Riesgos</p>
                        <strong class="step-block__title">Obligatorio (mínimo 5 en proyecto estándar)</strong>
                        <p class="step-block__help">Selecciona riesgos relevantes para monitorear desde el inicio.</p>
                        </div>
                    </div>
                    <span class="section-status section-status--optional">Opcional</span>
                    <div class="risk-summary">
                        <span class="pill soft-slate" id="riskCount">0 seleccionados</span>
                    </div>
                </summary>
                <div class="accordion-body">
                    <div class="risk-grid" id="riskChecklist">
                    <?php foreach ($orderedRiskGroups as $category => $risks): ?>
                        <?php
                            $categoryIcon = $riskCategoryIcons[$category] ?? $riskCategoryIcons['Otros'];
                            $categorySlug = $riskCategorySlugs[$category] ?? 'alcance';
                        ?>
                        <div class="risk-group risk-group--<?= htmlspecialchars($categorySlug) ?>" data-limit="5">
                            <div class="risk-group__header">
                                <span class="risk-group__icon" aria-hidden="true"><?= $categoryIcon ?></span>
                                <strong><?= htmlspecialchars($category) ?></strong>
                            </div>
                            <div class="risk-group__list">
                                <?php foreach ($risks as $risk): ?>
                                    <?php $riskCode = $risk['code'] ?? ''; $riskLabel = $risk['label'] ?? $riskCode; ?>
                                    <label class="risk-chip">
                                        <input type="checkbox" name="risks[]" value="<?= htmlspecialchars($riskCode) ?>" <?= in_array($riskCode, $fieldValue('risks', []), true) ? 'checked' : '' ?>>
                                        <span class="risk-chip__content">
                                            <span class="risk-chip__box" aria-hidden="true"></span>
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
                            <input type="number" step="0.01" min="0" name="budget" value="<?= htmlspecialchars((string) $fieldValue('budget', '0')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
                        </label>
                        <label class="input">
                            <span class="field-label">
                                <span class="field-title"><span class="field-icon">⏱️</span>Horas planificadas</span>
                            </span>
                            <input type="number" step="0.1" min="0" name="planned_hours" value="<?= htmlspecialchars((string) $fieldValue('planned_hours', '0')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
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
            <button type="submit" class="btn primary" data-nav="submit" <?= $canCreateProject ? '' : 'disabled' ?>>
                <span class="btn-spinner" aria-hidden="true"></span>
                <span data-submit-label>Crear proyecto</span>
            </button>
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
    .wizard-shell {
        --color-step-active: #4F6BED;
        --color-step-inactive: #E5E7EB;
        --color-required-badge: #EF4444;
        --color-recommended-badge: #F59E0B;
        --color-optional-badge: #6B7280;
        --color-section-general: #EFF6FF;
        --color-section-methodology: #F0FDF4;
        --color-section-risks: #FFF7ED;
        --color-risk-alcance: #6366F1;
        --color-risk-costos: #10B981;
        --color-risk-calidad: #3B82F6;
        --color-risk-cronograma: #F59E0B;
        --color-risk-legal: #EF4444;
        --color-risk-operaciones: #8B5CF6;
        --color-risk-recursos: #EC4899;
        --color-risk-stakeholders: #14B8A6;
        --color-risk-tecnologia: #F97316;
        display:flex;
        flex-direction:column;
        gap:18px;
        padding-bottom:84px;
    }
    .project-create-title { font-size:1.375rem; font-weight:700; color:#111827; line-height:1.2; }
    .project-create-subtitle { font-size:0.875rem; color:#6B7280 !important; }
    .wizard-header { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .wizard-header__title { display:flex; gap:12px; align-items:flex-start; }
    .wizard-header__icon { width:48px; height:48px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background: color-mix(in srgb, var(--color-step-active) 12%, #fff); color: var(--color-step-active); border:1px solid color-mix(in srgb, var(--color-step-active) 28%, #fff); }
    .wizard-header__title strong { display:block; margin:2px 0; font-size:1.125rem; color:#111827; }
    .wizard-header__meta { display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .wizard-steps { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:0; position:relative; padding:12px 48px 4px; align-items:start; }
    .wizard-steps::before { content:""; position:absolute; left:calc(25% + 18px); right:calc(25% + 18px); top:30px; height:3px; border-radius:999px; background:linear-gradient(90deg, var(--color-step-active) 0%, var(--color-step-active) 48%, var(--color-step-inactive) 100%); }
    .wizard-step { display:flex; flex-direction:column; align-items:center; gap:8px; padding:0; border:0; background:transparent; position:relative; overflow:visible; text-align:center; z-index:1; }
    .wizard-step::after { display:none; }
    .wizard-step__marker { display:flex; align-items:center; justify-content:center; position:relative; z-index:1; }
    .wizard-step__icon { display:none; }
    .wizard-step__number { width:36px; height:36px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; font-weight:800; background:#fff; color:#6B7280; border:2px solid var(--color-step-inactive); box-shadow:0 1px 2px rgba(0,0,0,.04); transition:background-color .2s ease, border-color .2s ease, color .2s ease, transform .2s ease; }
    .wizard-step__body { display:flex; flex-direction:column; gap:2px; position:relative; z-index:1; align-items:center; }
    .wizard-step__title { margin:0; font-weight:600; color:#6B7280; font-size:.75rem; line-height:1.25; }
    .wizard-step__subtitle { margin:0; color:#9CA3AF; font-size:.75rem; line-height:1.25; }
    .wizard-step.completed .wizard-step__number,
    .wizard-step.active .wizard-step__number { background:var(--color-step-active); color:#fff; border-color:var(--color-step-active); transform:scale(1.03); }
    .wizard-step.completed .wizard-step__title,
    .wizard-step.active .wizard-step__title { color:#111827; font-weight:800; }
    .wizard-content { display:none; flex-direction:column; gap:16px; }
    .wizard-content.active { display:flex; }
    .step-card { border:1px solid #F3F4F6; border-radius:12px; background:#fff; padding:16px; display:flex; flex-direction:column; gap:14px; box-shadow:0 10px 30px rgba(79,107,237,.06); }
    .step-card__header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .step-card__grid { grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); }
    .step-card__grid.compact { grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); }
    .accordion,
    .step-block { border:1px solid #F3F4F6; border-left:4px solid #D1D5DB; border-radius:12px; background:#fff; padding:0; display:flex; flex-direction:column; gap:12px; margin-bottom:1.5rem; box-shadow:0 1px 4px rgba(0,0,0,.06); overflow:visible; }
    .step-block--required { background:var(--color-section-general); border-left-color:var(--color-step-active); }
    .step-block--method,
    .step-block--recommended { background:var(--color-section-methodology); border-left-color:#10B981; }
    .step-block--optional { background:var(--color-section-risks); border-left-color:var(--color-risk-cronograma); position:relative; }
    .step-block--poc { background:color-mix(in srgb, #8B5CF6 10%, #fff); border-left-color:#8B5CF6; }
    .accordion-summary { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; cursor:pointer; list-style:none; padding:14px 16px; position:relative; }
    .accordion-summary::-webkit-details-marker { display:none; }
    .accordion-body { padding:0 16px 16px; display:flex; flex-direction:column; gap:12px; }
    .section-heading { display:flex; align-items:flex-start; gap:10px; min-width:0; }
    .section-heading__icon { flex:0 0 30px; width:30px; height:30px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:rgba(79,107,237,.12); color:var(--color-step-active); }
    .section-heading__icon svg { width:17px; height:17px; }
    .step-block--method .section-heading__icon { background:rgba(16,185,129,.12); color:#10B981; }
    .step-block--optional .section-heading__icon { background:rgba(245,158,11,.14); color:var(--color-risk-cronograma); }
    .step-block__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
    .step-block__eyebrow { margin:0; font-weight:700; font-size:.75rem; letter-spacing:.04em; text-transform:uppercase; color:#6B7280; }
    .step-block__title { display:block; font-size:.9375rem; font-weight:600; color:#111827; }
    .step-block__help,
    .hint { margin:4px 0 0; font-size:.75rem; color:#9CA3AF; }
    .section-status { display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; color:#fff; font-size:.6875rem; font-weight:800; line-height:1; white-space:nowrap; box-shadow:0 1px 2px rgba(0,0,0,.08); }
    .section-status--required { background:var(--color-required-badge); }
    .section-status--recommended { background:var(--color-recommended-badge); }
    .section-status--optional { background:var(--color-optional-badge); }
    .field-label { display:flex; align-items:center; justify-content:space-between; gap:12px; color:#374151; font-size:.8125rem; font-weight:700; }
    .field-title { display:flex; align-items:center; gap:8px; color:#374151; font-size:.8125rem; font-weight:700; }
    .field-icon { display:inline-flex; width:22px; height:22px; border-radius:6px; align-items:center; justify-content:center; background:rgba(79,107,237,.10); font-size:14px; }
    .field-required { display:inline-flex; align-items:center; color:var(--color-required-badge); background:transparent; padding:0; border:0; border-radius:0; font-size:.8125rem; font-weight:900; }
    .field-required__icon { display:none; }
    .help-icon,
    [data-tooltip] { position:relative; display:inline-flex; width:16px; height:16px; border-radius:999px; align-items:center; justify-content:center; background:#E5E7EB; color:#6B7280; font-size:11px; font-weight:800; cursor:help; }
    .help-icon:hover::after,
    [data-tooltip]:hover::after { content:attr(data-tooltip); position:absolute; left:50%; bottom:calc(100% + 8px); transform:translateX(-50%); min-width:160px; max-width:240px; padding:7px 9px; border-radius:8px; background:#111827; color:#fff; font-size:.75rem; line-height:1.3; box-shadow:0 8px 18px rgba(0,0,0,.18); z-index:30; }
    .wizard-shell input,
    .wizard-shell select,
    .wizard-shell textarea { border:1.5px solid #E5E7EB; border-radius:8px; padding:.5rem .75rem; font-size:.875rem; transition:border-color .2s ease, box-shadow .2s ease, background-color .2s ease; background:#fff; color:#111827; font-weight:500; }
    .wizard-shell input:focus,
    .wizard-shell select:focus,
    .wizard-shell textarea:focus { outline:none; border-color:var(--color-step-active); box-shadow:0 0 0 3px rgba(79,107,237,.15); }
    .input.is-invalid input,
    .input.is-invalid select,
    .input.is-invalid textarea { border-color:var(--color-required-badge) !important; box-shadow:0 0 0 3px rgba(239,68,68,.18); }
    .input.is-invalid .field-title { color:var(--color-required-badge); }
    .field-error { display:none; margin-top:6px; color:var(--color-required-badge); font-size:.75rem; font-weight:700; line-height:1.35; }
    .input.is-invalid .field-error { display:block; }
    .wizard-validation { display:none; }
    .wizard-validation.is-visible { display:block; }
    .wizard-docs-note { margin-top:4px; }
    .risk-summary { margin-left:auto; position:sticky; top:12px; z-index:5; }
    .risk-summary #riskCount { border:0; border-radius:999px; padding:7px 12px; color:#fff; font-size:.75rem; font-weight:800; box-shadow:0 6px 14px rgba(0,0,0,.10); transition:background-color .2s ease, color .2s ease, transform .2s ease; }
    .risk-summary #riskCount.soft-slate,
    .risk-summary #riskCount.soft-amber { background:var(--color-required-badge); }
    .risk-summary #riskCount.soft-green { background:#10B981; color:#fff; }
    .risk-grid { display:grid; gap:14px; grid-template-columns:repeat(3, minmax(0, 1fr)); }
    .risk-group { --risk-color:var(--color-risk-alcance); padding:1rem; border-radius:10px; border:1px solid #F3F4F6; border-top:3px solid var(--risk-color); background:#fff; display:flex; flex-direction:column; gap:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
    .risk-group:hover { transform:translateY(-2px); box-shadow:0 10px 22px rgba(17,24,39,.12); }
    .risk-group--alcance { --risk-color:var(--color-risk-alcance); }
    .risk-group--costos { --risk-color:var(--color-risk-costos); }
    .risk-group--calidad { --risk-color:var(--color-risk-calidad); }
    .risk-group--cronograma { --risk-color:var(--color-risk-cronograma); }
    .risk-group--legal { --risk-color:var(--color-risk-legal); }
    .risk-group--operaciones { --risk-color:var(--color-risk-operaciones); }
    .risk-group--recursos { --risk-color:var(--color-risk-recursos); }
    .risk-group--stakeholders { --risk-color:var(--color-risk-stakeholders); }
    .risk-group--tecnologia { --risk-color:var(--color-risk-tecnologia); }
    .risk-group__header { display:flex; align-items:center; gap:8px; font-weight:800; color:#111827; }
    .risk-group__icon { width:28px; height:28px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:color-mix(in srgb, var(--risk-color) 15%, #fff); color:var(--risk-color); }
    .risk-group__icon svg { width:16px; height:16px; }
    .risk-group__list { display:grid; gap:8px; grid-template-columns:1fr; }
    .risk-chip { position:relative; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; border-radius:8px; border:1px solid #F3F4F6; background:#fff; cursor:pointer; transition:background-color .2s ease, border-color .2s ease, box-shadow .2s ease; }
    .risk-chip:hover { border-color:color-mix(in srgb, var(--risk-color) 42%, #E5E7EB); box-shadow:0 6px 14px color-mix(in srgb, var(--risk-color) 12%, transparent); }
    .risk-chip input { position:absolute; opacity:0; pointer-events:none; }
    .risk-chip__content { display:flex; align-items:flex-start; gap:8px; font-weight:600; color:#374151; font-size:.8125rem; line-height:1.3; }
    .risk-chip__box { flex:0 0 16px; width:16px; height:16px; margin-top:1px; border-radius:4px; border:1.5px solid #D1D5DB; background:#fff; transition:background-color .2s ease, border-color .2s ease, box-shadow .2s ease; }
    .risk-chip__check { opacity:0; position:absolute; left:13px; top:8px; font-size:12px; font-weight:900; color:#fff; pointer-events:none; transition:opacity .2s ease; }
    .risk-chip:has(input:checked) { background:color-mix(in srgb, var(--risk-color) 8%, #fff); border-color:color-mix(in srgb, var(--risk-color) 38%, #E5E7EB); }
    .risk-chip input:checked ~ .risk-chip__content .risk-chip__box { background:var(--risk-color); border-color:var(--risk-color); box-shadow:0 0 0 3px color-mix(in srgb, var(--risk-color) 16%, transparent); }
    .risk-chip input:checked ~ .risk-chip__check { opacity:1; }
    .risk-chip.is-hidden { display:none; }
    .risk-group__toggle { align-self:flex-start; }
    .btn.small { padding:6px 10px; font-size:12px; }
    .wizard-footer { position:sticky; bottom:0; z-index:20; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-top:1px solid #F3F4F6; padding:12px 16px; margin:4px -16px -16px; background:#fff; box-shadow:0 -10px 20px rgba(17,24,39,.04); }
    .wizard-footer__nav,
    .wizard-footer__actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .wizard-shell .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:8px; padding:.625rem 1.5rem; font-weight:800; transition:filter .16s ease, transform .16s ease, background-color .16s ease, border-color .16s ease, color .16s ease, box-shadow .16s ease; }
    .wizard-shell .btn[data-nav="next"],
    .wizard-shell .btn.primary { background:var(--color-step-active); border-color:var(--color-step-active); color:#fff; }
    .wizard-shell .btn[data-nav="prev"] { background:#fff; border:1px solid var(--color-step-active); border-style:solid; color:var(--color-step-active); }
    .wizard-footer__actions > a.btn { background:transparent; border-color:transparent; border-style:solid; color:#6B7280; padding:.625rem .75rem; box-shadow:none; }
    .wizard-shell .btn:hover { filter:brightness(1.1); transform:none; box-shadow:0 8px 16px rgba(79,107,237,.16); }
    .wizard-footer__actions > a.btn:hover { background:transparent; color:#374151; box-shadow:none; }
    .wizard-shell .btn:active { transform:scale(.98); }
    .muted { color:#6B7280; font-weight:600; margin:0; font-size:.875rem; }
    .pill { display:inline-flex; align-items:center; padding:8px 12px; border-radius:999px; font-weight:700; border:1px solid #E5E7EB; background:#fff; }
    .ghosted-pill { background:#F9FAFB; color:#6B7280; }
    .soft-blue { background:rgba(79,107,237,.12); color:var(--color-step-active); }
    .soft-amber { background:rgba(245,158,11,.16); color:#B45309; }
    .soft-green { background:rgba(16,185,129,.16); color:#047857; }
    .soft-slate { background:#F3F4F6; color:#6B7280; }
    .soft-purple { background:rgba(139,92,246,.14); color:#7C3AED; }
    .alert { padding:12px 14px; border-radius:10px; margin-bottom:10px; font-weight:700; }
    .alert.error { background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.35); color:#B91C1C; }
    .alert.warning { background:rgba(245,158,11,.13); border:1px solid rgba(245,158,11,.35); color:#B45309; }
    .wizard-hidden-step { display:none; }
    .wizard-loader { position:fixed; inset:0; background:rgba(17,24,39,.45); display:none; align-items:center; justify-content:center; z-index:9999; padding:24px; }
    .wizard-loader.is-visible { display:flex; }
    .wizard-loader__card { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:20px 22px; display:flex; align-items:center; gap:14px; box-shadow:0 18px 40px rgba(17,24,39,.35); }
    .wizard-loader__spinner { width:28px; height:28px; border-radius:50%; border:3px solid rgba(79,107,237,.22); border-top-color:var(--color-step-active); animation:spin 1s linear infinite; }
    .btn .btn-spinner { display:none; width:14px; height:14px; border-radius:50%; border:2px solid rgba(255,255,255,.55); border-top-color:#fff; animation:spin .9s linear infinite; }
    .btn.is-loading .btn-spinner { display:inline-block; }
    .btn.is-loading [data-submit-label] { opacity:.92; }
    @keyframes spin { to { transform:rotate(360deg); } }
    @media (max-width: 1024px) { .risk-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 840px) {
        .wizard-steps { padding-inline:18px; }
        .wizard-steps::before { left:calc(25% + 6px); right:calc(25% + 6px); }
        .wizard-header__title { width:100%; }
        .wizard-header__meta { justify-content:flex-start; }
        .wizard-footer { position:sticky; }
    }
    @media (max-width: 640px) {
        .risk-grid { grid-template-columns:1fr; }
        .wizard-steps { padding-inline:4px; }
        .wizard-step__subtitle { display:none; }
        .accordion-summary { flex-direction:column; }
        .risk-summary { position:sticky; top:8px; margin-left:0; align-self:flex-end; }
        .wizard-footer,
        .wizard-footer__nav,
        .wizard-footer__actions { width:100%; }
        .wizard-footer__actions { justify-content:flex-end; }
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
    const minimumRequiredRisks = 5;
    const pocOnlyFields = Array.from(document.querySelectorAll('[data-poc-only]'));
    const pocSections = Array.from(document.querySelectorAll('[data-poc-section]'));
    const standardOnlyFields = Array.from(document.querySelectorAll('[data-standard-only]'));
    const pocOptionalFields = Array.from(document.querySelectorAll('[data-poc-optional-field="1"]'));
    const isPocType = () => projectTypeSelect && projectTypeSelect.value === 'poc';
    const wizardForm = document.getElementById('projectWizardForm');
    const wizardLoader = document.getElementById('wizardLoader');
    const methodologyMap = { convencional: 'cascada', scrum: 'scrum', hibrido: 'kanban', outsourcing: 'cascada', poc: 'cascada' };
    let isSubmitting = false;

    let currentStep = 0;
    let hasValidatedStep0 = false;
    const baseStep0RequiredFields = [
        { name: 'name', label: 'Nombre del proyecto', message: 'Debe ingresar el nombre del proyecto' },
        { name: 'client_id', label: 'Cliente', message: 'Debe seleccionar un cliente' },
        { name: 'pm_id', label: 'PM responsable', message: 'Debe seleccionar un PM responsable' },
        { name: 'project_type', label: 'Tipo de proyecto', message: 'Debe seleccionar el tipo de proyecto' },
        { name: 'methodology_display', label: 'Metodología', message: 'Debe seleccionar una metodología' },
        { name: 'start_date', label: 'Fecha de inicio', message: 'Debe seleccionar una fecha' },
        { name: 'end_date', label: 'Fecha fin', message: 'Debe seleccionar una fecha' },
    ];
    const standardProjectRequiredFields = [
        { name: 'scope', label: 'Alcance del proyecto', message: 'Debe ingresar el alcance del proyecto' },
        { name: 'design_inputs', label: 'Entradas de diseño', message: 'Debe ingresar las entradas de diseño' },
    ];
    const pocRequiredFields = [
        { name: 'solicitante_poc', label: 'Solicitante de la POC', message: 'Debe ingresar el solicitante' },
        { name: 'fecha_solicitud_poc', label: 'Fecha de solicitud', message: 'Debe seleccionar una fecha' },
        { name: 'tipo_poc', label: 'Tipo de POC', message: 'Debe seleccionar el tipo de POC' },
        { name: 'descripcion_alcance_poc', label: 'Descripción / alcance de la POC', message: 'Debe ingresar la descripción de la POC' },
    ];
    const fieldValidationMessages = new Map([
        ...baseStep0RequiredFields,
        ...standardProjectRequiredFields,
        ...pocRequiredFields,
        { name: 'project_stage', label: 'Stage-gate', message: 'Debe seleccionar el stage-gate' },
        { name: 'priority', label: 'Prioridad', message: 'Debe seleccionar una prioridad' },
    ].map((field) => [field.name, field.message]));

    function currentStep0RequiredFields() {
        return [
            ...baseStep0RequiredFields,
            ...(isPocType() ? pocRequiredFields : standardProjectRequiredFields),
        ];
    }

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
        endDateGroup.style.display = '';
        syncEndDateRequiredState();
    }


    function togglePocFields() {
        const poc = isPocType();
        pocSections.forEach((section) => {
            section.hidden = !poc;
        });

        pocOnlyFields.forEach((wrapper) => {
            wrapper.hidden = !poc;
            const controls = wrapper.querySelectorAll('input, select, textarea');
            controls.forEach((control) => {
                const isRequiredForPoc = pocRequiredFields.some(({ name }) => name === control.name);
                control.required = poc && isRequiredForPoc;
                control.disabled = !canCreateProject || !poc;
                if (!poc) {
                    clearFieldError(control);
                    control.setCustomValidity('');
                }
            });
        });

        standardOnlyFields.forEach((wrapper) => {
            wrapper.hidden = poc;
            const controls = wrapper.querySelectorAll('input, select, textarea');
            controls.forEach((control) => {
                control.disabled = !canCreateProject || poc;
                if (poc) {
                    clearFieldError(control);
                    control.setCustomValidity('');
                }
            });
        });

        pocOptionalFields.forEach((control) => {
            control.required = !poc;
            if (poc) {
                clearFieldError(control);
                control.setCustomValidity('');
            }
        });
    }

    function syncEndDateRequiredState() {
        if (!endDateInput || !projectTypeSelect) return;
        const isVisible = endDateInput.offsetParent !== null;
        endDateInput.required = isVisible;

        const startDateInput = document.querySelector('[name="start_date"]');
        if (startDateInput) {
            endDateInput.min = startDateInput.value || '';
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
        syncEndDateRequiredState();
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

    function getFieldMessageNode(field) {
        const wrapper = field?.closest('.input');
        if (!wrapper) return null;
        let message = wrapper.querySelector('.field-error');
        if (!message) {
            message = document.createElement('span');
            message.className = 'field-error';
            message.setAttribute('role', 'alert');
            wrapper.appendChild(message);
        }
        if (!message.id && field.name) {
            message.id = `${field.name.replace(/[^a-zA-Z0-9_-]/g, '-')}-error`;
        }
        return message;
    }

    function setFieldError(field, message) {
        if (!field) return;
        const wrapper = field.closest('.input');
        const messageNode = getFieldMessageNode(field);
        const finalMessage = message || fieldValidationMessages.get(field.name) || 'Este campo es obligatorio';
        field.setCustomValidity(finalMessage);
        field.setAttribute('aria-invalid', 'true');
        if (messageNode) {
            messageNode.textContent = finalMessage;
            field.setAttribute('aria-describedby', messageNode.id);
        }
        if (wrapper) {
            wrapper.classList.add('is-invalid');
        }
    }

    function clearFieldError(field) {
        if (!field) return;
        const wrapper = field.closest('.input');
        const messageNode = wrapper?.querySelector('.field-error');
        field.setCustomValidity('');
        field.removeAttribute('aria-invalid');
        if (messageNode) {
            messageNode.textContent = '';
        }
        if (wrapper) {
            wrapper.classList.remove('is-invalid');
        }
    }

    function scrollToFieldError(field) {
        if (!field) return;
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(() => field.focus({ preventScroll: true }), 250);
    }

    function isFieldHiddenOrDisabled(field) {
        return field.disabled || Boolean(field.closest('[hidden]'));
    }

    function clearStep0FieldErrors() {
        currentStep0RequiredFields().forEach(({ name }) => {
            const field = getStep0FieldNode(name);
            if (field) clearFieldError(field);
        });
    }

    function handleStep0FieldChange(field) {
        if (!field) return;
        const isStep0Field = currentStep0RequiredFields().some(({ name }) => name === field.name);
        if (!isStep0Field) return;
        if (field.value.trim() !== '') {
            clearFieldError(field);
        }
    }

    function isStep0Valid() {
        return currentStep0RequiredFields().every(({ name }) => {
            const field = getStep0FieldNode(name);
            return field && !isFieldHiddenOrDisabled(field) ? field.value.trim() !== '' : true;
        });
    }



    function validateMinimumRisks(showValidity = false) {
        const selected = Array.from(riskChecklist).filter((checkbox) => checkbox.checked).length;
        const threshold = isPocType() ? 0 : minimumRequiredRisks;
        const isValid = selected >= threshold;

        riskChecklist.forEach((checkbox) => {
            if (threshold <= 0) {
                checkbox.setCustomValidity('');
            } else {
                checkbox.setCustomValidity(isValid ? '' : `Selecciona al menos ${minimumRequiredRisks} riesgos.`);
            }
        });

        if (!isValid && showValidity) {
            const firstRisk = riskChecklist[0] || null;
            if (firstRisk) {
                firstRisk.reportValidity();
                firstRisk.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        return isValid;
    }

    function validateDateRange() {
        const startDateInput = document.querySelector('[name="start_date"]');
        if (!startDateInput || !endDateInput || !endDateInput.value) {
            return true;
        }

        if (endDateInput.value < startDateInput.value) {
            setFieldError(endDateInput, 'La fecha final no puede ser menor a la inicial');
            scrollToFieldError(endDateInput);
            return false;
        }

        clearFieldError(endDateInput);
        return true;
    }

    function validateStep0() {
        let firstInvalidField = null;
        const missingFields = [];
        clearStep0FieldErrors();
        hasValidatedStep0 = true;

        currentStep0RequiredFields().forEach(({ name, label, message }) => {
            const field = getStep0FieldNode(name);
            if (!field) {
                missingFields.push(label);
                return;
            }
            const fieldValue = field.value.trim();
            if (!fieldValue) {
                setFieldError(field, message || 'Este campo es obligatorio');
                missingFields.push(label);
                if (!firstInvalidField && !isFieldHiddenOrDisabled(field)) {
                    firstInvalidField = field;
                }
            } else {
                clearFieldError(field);
            }
        });

        if (missingFields.length > 0) {
            console.warn('[Wizard] Validación Paso 1 fallida. Faltan:', missingFields);
            updateValidationMessage(false);
            if (firstInvalidField) {
                scrollToFieldError(firstInvalidField);
            }
            return false;
        }

        if (!validateDateRange()) {
            updateValidationMessage(false);
            return false;
        }

        if (!validateMinimumRisks(true)) {
            updateValidationMessage(false);
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
        const requiredFields = Array.from(section.querySelectorAll('[required]'));
        for (const field of requiredFields) {
            if (isFieldHiddenOrDisabled(field)) {
                continue;
            }
            const fieldValue = field.value.trim();
            if (!fieldValue) {
                setFieldError(field, fieldValidationMessages.get(field.name) || 'Este campo es obligatorio');
                scrollToFieldError(field);
                return false;
            }
            field.setCustomValidity('');
            if (!field.checkValidity()) {
                setFieldError(field, field.validationMessage || 'Revisa el formato del campo');
                scrollToFieldError(field);
                return false;
            }
            clearFieldError(field);
        }
        return true;
    }

    function isStepValid(index) {
        if (index === 0) {
            return isStep0Valid() && validateDateRange() && validateMinimumRisks(false);
        }
        const section = wizardSections[index];
        if (!section) return true;
        const requiredFields = Array.from(section.querySelectorAll('[required]'));
        return requiredFields.every((field) => isFieldHiddenOrDisabled(field) || field.checkValidity());
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

    function setSubmittingState(submitting) {
        isSubmitting = submitting;
        if (wizardLoader) {
            wizardLoader.classList.toggle('is-visible', submitting);
            wizardLoader.setAttribute('aria-hidden', submitting ? 'false' : 'true');
        }

        if (!wizardForm) return;
        const actionButtons = wizardForm.querySelectorAll('button, a.btn');
        actionButtons.forEach((button) => {
            button.setAttribute('aria-disabled', submitting ? 'true' : 'false');
            if (button.tagName === 'BUTTON') {
                button.disabled = submitting;
            }
        });

        if (submitButton) {
            submitButton.classList.toggle('is-loading', submitting);
        }
    }

    function updateRiskCount() {
        if (!riskCount) return;
        const selected = Array.from(riskChecklist).filter((checkbox) => checkbox.checked).length;
        const threshold = isPocType() ? 0 : minimumRequiredRisks;
        const thresholdLabel = isPocType() ? 'sin mínimo' : `mínimo ${minimumRequiredRisks}`;
        riskCount.textContent = `${selected} seleccionados (${thresholdLabel})`;
        riskCount.classList.toggle('soft-amber', selected < threshold);
        riskCount.classList.toggle('soft-green', selected >= threshold);
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
    togglePocFields();
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
            togglePocFields();
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
            console.log('CLICK CREAR PROYECTO');
        });
    }

    wizardSections.forEach((section) => {
        section.querySelectorAll('input, select, textarea').forEach((field) => {
            field.addEventListener('input', () => {
                if (field.value.trim() !== '') {
                    clearFieldError(field);
                }
                handleStep0FieldChange(field);
                updateNavState();
            });
            field.addEventListener('change', () => {
                if (field.value.trim() !== '') {
                    clearFieldError(field);
                }
                handleStep0FieldChange(field);
                if (field.name === 'start_date' || field.name === 'end_date') {
                    validateDateRange();
                }
                updateNavState();
                updateRiskCount();
            });
        });
    });


    function validateAllRequiredFields() {
        const requiredFields = Array.from(wizardForm.querySelectorAll('[required]'));
        let firstInvalidField = null;
        requiredFields.forEach((field) => {
            if (isFieldHiddenOrDisabled(field)) {
                clearFieldError(field);
                return;
            }
            const fieldValue = field.value.trim();
            if (!fieldValue) {
                setFieldError(field, fieldValidationMessages.get(field.name) || 'Este campo es obligatorio');
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
                return;
            }
            field.setCustomValidity('');
            if (!field.checkValidity()) {
                setFieldError(field, field.validationMessage || 'Revisa el formato del campo');
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
                return;
            }
            clearFieldError(field);
        });

        if (firstInvalidField) {
            const step = firstInvalidField.closest('[data-step]');
            const stepIndex = wizardSections.indexOf(step);
            if (stepIndex >= 0 && stepIndex !== currentStep) {
                setStep(stepIndex);
            }
            updateValidationMessage(false);
            scrollToFieldError(firstInvalidField);
            return false;
        }

        return true;
    }

    function serializeFormData(formData) {
        const data = {};
        for (const [key, value] of formData.entries()) {
            if (Object.prototype.hasOwnProperty.call(data, key)) {
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }
        return data;
    }

    const serverResponseAlert = document.getElementById('serverResponseAlert');
    const serverResponseTitle = document.getElementById('serverResponseTitle');
    const serverResponseBody = document.getElementById('serverResponseBody');
    const serverResponseLink = document.getElementById('serverResponseLink');

    function showServerResponse(title, text, linkUrl = '') {
        if (!serverResponseAlert || !serverResponseTitle || !serverResponseBody) {
            return;
        }

        serverResponseTitle.textContent = title || 'Respuesta backend';
        serverResponseBody.textContent = text || '';

        if (serverResponseLink) {
            if (linkUrl) {
                serverResponseLink.href = linkUrl;
                serverResponseLink.textContent = 'Abrir respuesta en esta pestaña';
                serverResponseLink.style.display = 'inline-block';
            } else {
                serverResponseLink.href = '#';
                serverResponseLink.textContent = '';
                serverResponseLink.style.display = 'none';
            }
        }

        serverResponseAlert.style.display = 'block';
        serverResponseAlert.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function hideServerResponse() {
        if (!serverResponseAlert || !serverResponseTitle || !serverResponseBody) {
            return;
        }

        serverResponseAlert.style.display = 'none';
        serverResponseTitle.textContent = '';
        serverResponseBody.textContent = '';
        if (serverResponseLink) {
            serverResponseLink.href = '#';
            serverResponseLink.textContent = '';
            serverResponseLink.style.display = 'none';
        }
    }

    function normalizePath(pathname) {
        return (pathname || '/').replace(/\/+$/, '') || '/';
    }

    function isSameRoute(urlA, urlB) {
        try {
            const a = new URL(urlA, window.location.origin);
            const b = new URL(urlB, window.location.origin);
            return a.origin === b.origin && normalizePath(a.pathname) === normalizePath(b.pathname);
        } catch (error) {
            return false;
        }
    }

    if (wizardForm) {
        wizardForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (isSubmitting) {
                return;
            }

            syncEndDateRequiredState();
            const stepValid = validateStep(currentStep);
            const formValid = stepValid && validateAllRequiredFields();
            if (!formValid) {
                setSubmittingState(false);
                return;
            }

            setSubmittingState(true);
            hideServerResponse();

            try {
                wizardForm.submit();
            } catch (error) {
                setSubmittingState(false);
                showServerResponse(
                    'No fue posible enviar el formulario',
                    'Ocurrió un error al cargar el formulario del proyecto. Por favor recarga la página o contacta al administrador.'
                );
            }
        });
    }

    window.addEventListener('pageshow', () => {
        setSubmittingState(false);
    });
</script>
