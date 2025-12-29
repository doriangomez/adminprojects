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

$selectedMethodology = $oldInput['methodology'] ?? $defaults['methodology'] ?? ($methodologies[0] ?? 'scrum');
$currentPhases = is_array($phasesByMethodology[$selectedMethodology] ?? null) ? $phasesByMethodology[$selectedMethodology] : [];
$selectedPhase = $oldInput['phase'] ?? $defaults['phase'] ?? ($currentPhases[0] ?? '');
$selectedClientId = (int) ($oldInput['client_id'] ?? $defaults['client_id'] ?? ($clientsList[0]['id'] ?? 0));
$selectedPmId = (int) ($oldInput['pm_id'] ?? $defaults['pm_id'] ?? ($projectManagersList[0]['id'] ?? 0));
$selectedStatus = (string) ($oldInput['status'] ?? $defaults['status'] ?? ($statusesCatalog[0]['code'] ?? ''));
$selectedHealth = (string) ($oldInput['health'] ?? $defaults['health'] ?? ($healthCatalog[0]['code'] ?? ''));
$selectedPriority = (string) ($oldInput['priority'] ?? $defaults['priority'] ?? ($prioritiesCatalog[0]['code'] ?? ''));
$selectedProjectType = (string) ($oldInput['project_type'] ?? $defaults['project_type'] ?? 'convencional');

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

<form action="<?= $basePath ?>/projects/create" method="POST" class="card" id="projectWizardForm" style="display:flex; flex-direction:column; gap:18px; opacity: <?= $canCreateProject ? '1' : '0.65' ?>;">
    <div class="wizard-steps" role="list">
        <div class="wizard-step" data-step-indicator="0" role="listitem">
            <div class="badge soft-blue">1</div>
            <div>
                <strong>Contexto base</strong>
                <p class="muted">Cliente, nombre, responsable</p>
            </div>
        </div>
        <div class="wizard-step" data-step-indicator="1" role="listitem">
            <div class="badge soft-amber">2</div>
            <div>
                <strong>Flujo y estado</strong>
                <p class="muted">Metodología, fase y salud</p>
            </div>
        </div>
        <div class="wizard-step" data-step-indicator="2" role="listitem">
            <div class="badge soft-green">3</div>
            <div>
                <strong>Planeación</strong>
                <p class="muted">Horas, presupuesto y fechas</p>
            </div>
        </div>
    </div>

    <div class="wizard-content" data-step="0">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div>
                <p class="section-label">Paso 1</p>
                <strong style="margin:0;">Contexto base</strong>
                <p class="muted" style="margin:4px 0 0 0;">Completa los datos clave del proyecto antes de avanzar.</p>
            </div>
        </div>

        <section class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:14px;">
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
                <select name="methodology" id="methodologySelect" required <?= $canCreateProject ? '' : 'disabled' ?>>
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
    </div>

    <div class="wizard-content" data-step="1">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <div>
                <p class="section-label">Paso 2</p>
                <strong style="margin:0;">Flujo de entrega</strong>
                <p class="muted" style="margin:4px 0 0 0;">Selecciona la fase y el estado base del proyecto.</p>
            </div>
            <div class="pill soft-slate" id="phaseStatusPill" aria-live="polite"></div>
        </div>
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:14px;">
            <label class="input">
                <span>Fase</span>
                <select name="phase" id="phaseSelect" <?= $canCreateProject ? '' : 'disabled' ?>>
                    <option value="">Sin fase</option>
                    <?php foreach ($currentPhases as $phase): ?>
                        <option value="<?= htmlspecialchars($phase) ?>" <?= $selectedPhase === $phase ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($phase)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="input">
                <span>Estado</span>
                <select name="status" required <?= $canCreateProject ? '' : 'disabled' ?>>
                    <?php foreach ($statusesCatalog as $status): ?>
                        <?php $code = $status['code'] ?? ''; ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= $selectedStatus === $code ? 'selected' : '' ?>>
                            <?= htmlspecialchars($status['label'] ?? $code) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="input">
                <span>Salud</span>
                <select name="health" required <?= $canCreateProject ? '' : 'disabled' ?>>
                    <?php foreach ($healthCatalog as $health): ?>
                        <?php $code = $health['code'] ?? ''; ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= $selectedHealth === $code ? 'selected' : '' ?>>
                            <?= htmlspecialchars($health['label'] ?? $code) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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

    <div class="wizard-content" data-step="2">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div>
                <p class="section-label">Paso 3</p>
                <strong style="margin:0;">Planeación y seguimiento</strong>
                <p class="muted" style="margin:4px 0 0 0;">Horas, presupuesto y progreso inicial del proyecto.</p>
            </div>
        </div>

        <section class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
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
            <label class="input">
                <span>Progreso (%)</span>
                <input type="number" step="0.1" min="0" max="100" name="progress" value="<?= htmlspecialchars((string) $fieldValue('progress', '0')) ?>" <?= $canCreateProject ? '' : 'disabled' ?>>
            </label>
        </section>
    </div>

    <div class="wizard-footer">
        <div style="display:flex; gap:8px; align-items:center;">
            <button type="button" class="btn ghost" data-nav="prev">← Paso anterior</button>
            <span class="muted" aria-live="polite">Paso <span id="wizardStepLabel">1</span> de 3</span>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <a class="btn ghost" href="<?= $basePath ?>/projects">Cancelar</a>
            <button type="button" class="btn" data-nav="next" <?= $canCreateProject ? '' : 'disabled' ?>>Siguiente</button>
            <button type="submit" class="btn primary" data-nav="submit" <?= $canCreateProject ? '' : 'disabled' ?>>Crear proyecto</button>
        </div>
    </div>
</form>

<style>
    .wizard-steps { display:flex; gap:12px; flex-wrap:wrap; }
    .wizard-step { display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px solid var(--border); border-radius:12px; background: #f8fafc; opacity:0.7; }
    .wizard-step.active { opacity:1; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15); }
    .wizard-step.completed { opacity:0.9; border-color: #16a34a; background:#ecfdf3; }
    .wizard-step .badge { border-radius:10px; padding:6px 10px; font-weight:800; }
    .muted { color: var(--muted); font-weight:600; margin:0; }
    .pill { display:inline-flex; align-items:center; padding:8px 12px; border-radius:999px; font-weight:700; border:1px solid var(--border); }
    .soft-blue { background:#e0e7ff; color:#1d4ed8; }
    .soft-amber { background:#fef3c7; color:#b45309; }
    .soft-green { background:#dcfce7; color:#15803d; }
    .soft-slate { background:#e5e7eb; color:#374151; }
    .alert { padding:12px 14px; border-radius:10px; margin-bottom:10px; font-weight:700; }
    .alert.error { background:#fee2e2; border:1px solid #fecaca; color:#991b1b; }
    .alert.warning { background:#fef9c3; border:1px solid #fde68a; color:#92400e; }
    .wizard-content { display:none; flex-direction:column; gap:18px; }
    .wizard-content.active { display:flex; }
    .wizard-footer { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-top:1px solid var(--border); padding-top:10px; }
</style>

<script>
    const phasesByMethodology = <?= json_encode($phasesByMethodology) ?>;
    const phaseSelect = document.getElementById('phaseSelect');
    const methodologySelect = document.getElementById('methodologySelect');
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
            }
        } else {
            endDateGroup.style.display = '';
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
        return true;
    }

    refreshPhases();
    toggleEndDateByType();
    setStep(0);

    if (methodologySelect) {
        methodologySelect.addEventListener('change', refreshPhases);
    }

    if (projectTypeSelect) {
        projectTypeSelect.addEventListener('change', toggleEndDateByType);
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
