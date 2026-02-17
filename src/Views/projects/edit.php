<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$deliveryConfig = is_array($delivery ?? null) ? $delivery : ['methodologies' => [], 'phases' => [], 'risks' => []];
$stageOptions = is_array($stageOptions ?? null) ? $stageOptions : [];
$methodologies = $deliveryConfig['methodologies'] ?? [];
$phasesByMethodology = $deliveryConfig['phases'] ?? [];
$riskCatalog = $deliveryConfig['risks'] ?? [];
$canDelete = !empty($canDelete);
$canInactivate = !empty($canInactivate);
$dependencies = $dependencies ?? [];
$hasDependencies = !empty($hasDependencies);
$mathOperand1 = (int) ($mathOperand1 ?? 0);
$mathOperand2 = (int) ($mathOperand2 ?? 0);
$mathOperator = $mathOperator ?? '+';
$dangerActionUrl = $canDelete
    ? ($basePath . '/projects/delete')
    : ($basePath . '/projects/' . (int) ($project['id'] ?? 0) . '/inactivate');
$dangerButtonLabel = $canDelete ? 'Eliminar permanentemente' : 'Inactivar proyecto';
$dangerActionText = $canDelete ? 'eliminación definitiva' : 'inactivación';
$riskGroups = [];
foreach ($riskCatalog as $risk) {
    $category = $risk['category'] ?? 'Otros';
    $riskGroups[$category][] = $risk;
}
$currentMethodology = $project['methodology'] ?? ($methodologies[0] ?? '');
$currentPhases = is_array($phasesByMethodology[$currentMethodology] ?? null) ? $phasesByMethodology[$currentMethodology] : [];
$selectedRisks = is_array($project['risks'] ?? null) ? $project['risks'] : [];
$projectType = $project['project_type'] ?? 'convencional';
$formAction = $formAction ?? ($basePath . '/projects/' . (int) ($project['id'] ?? 0) . '/edit');
$formTitle = $formTitle ?? 'Editar proyecto';
?>

<form action="<?= htmlspecialchars($formAction) ?>" method="POST" class="project-form">
    <header class="form-header">
        <div>
            <p class="eyebrow">Edición de proyecto</p>
            <h3><?= htmlspecialchars($formTitle) ?></h3>
            <small class="section-muted">Actualiza la información sin alterar la trazabilidad ISO.</small>
        </div>
        <button type="submit" class="action-btn primary">Guardar cambios</button>
    </header>

    <nav class="form-tabs">
        <a href="#datos-basicos">Datos básicos</a>
        <a href="#planificacion">Planificación</a>
        <a href="#costos">Costos</a>
        <a href="#riesgos">Riesgos</a>
        <?php if ($canDelete || $canInactivate): ?>
            <a href="#zona-critica" class="danger-tab">Zona crítica</a>
        <?php endif; ?>
    </nav>

    <details class="accordion" open id="datos-basicos">
        <summary class="accordion-summary">
            <div>
                <p class="section-label">Datos básicos</p>
                <strong>Información clave del proyecto</strong>
            </div>
        </summary>
        <div class="accordion-body">
            <div class="grid">
                <label>Nombre
                    <input name="name" value="<?= htmlspecialchars($project['name'] ?? '') ?>" required>
                </label>
                <label>Estado
                    <input name="status" value="<?= htmlspecialchars($project['status'] ?? '') ?>">
                </label>
                <label>Prioridad
                    <input name="priority" value="<?= htmlspecialchars($project['priority'] ?? '') ?>">
                </label>
                <label>Stage-gate
                    <select name="project_stage">
                        <?php foreach ($stageOptions as $stageOption): ?>
                            <option value="<?= htmlspecialchars($stageOption) ?>" <?= (($project['project_stage'] ?? 'Discovery') === $stageOption) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($stageOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>PM (ID)
                    <input type="number" name="pm_id" value="<?= (int) ($project['pm_id'] ?? 0) ?>">
                </label>
            </div>
        </div>
    </details>

    <details class="accordion" open id="planificacion">
        <summary class="accordion-summary">
            <div>
                <p class="section-label">Planificación</p>
                <strong>Metodología, fase y calendario</strong>
            </div>
        </summary>
        <div class="accordion-body">
            <div class="grid">
                <label>Tipo de proyecto
                    <select name="project_type" id="projectTypeSelect">
                        <option value="convencional" <?= $projectType === 'convencional' ? 'selected' : '' ?>>Convencional (fechas y fases)</option>
                        <option value="scrum" <?= $projectType === 'scrum' ? 'selected' : '' ?>>Scrum (sprints y backlog)</option>
                        <option value="hibrido" <?= $projectType === 'hibrido' ? 'selected' : '' ?>>Híbrido (mixto)</option>
                        <option value="outsourcing" <?= $projectType === 'outsourcing' ? 'selected' : '' ?>>Outsourcing (servicio continuo)</option>
                    </select>
                    <small class="subtext">Convencional usa hitos secuenciales; Scrum trabaja en sprints sin fecha fin rígida.</small>
                </label>
                <label>Metodología
                    <select name="methodology" id="methodologySelect">
                        <?php foreach ($methodologies as $methodology): ?>
                            <option value="<?= htmlspecialchars($methodology) ?>" <?= $methodology === $currentMethodology ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($methodology)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label data-role="phase-label"><span class="label-text">Fase / sprint</span>
                    <select name="phase" id="phaseSelect">
                        <option value="">Sin fase</option>
                        <?php foreach ($currentPhases as $phase): ?>
                            <option value="<?= htmlspecialchars($phase) ?>" <?= ($project['phase'] ?? '') === $phase ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($phase)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="grid">
                <label>Inicio
                    <input type="date" name="start_date" value="<?= htmlspecialchars((string) ($project['start_date'] ?? '')) ?>">
                </label>
                <label data-role="end-date">Fin
                    <input type="date" name="end_date" id="endDateInput" value="<?= htmlspecialchars((string) ($project['end_date'] ?? '')) ?>">
                </label>
            </div>
            <div id="scrumHint" class="hint-box" style="display: <?= $projectType === 'scrum' ? 'block' : 'none' ?>;">
                Para proyectos Scrum, administra sprints y backlog sin fecha de cierre fija. El progreso refleja el avance del sprint actual.
            </div>
        </div>
    </details>

    <details class="accordion" open id="costos">
        <summary class="accordion-summary">
            <div>
                <p class="section-label">Costos</p>
                <strong>Presupuesto, costos y horas</strong>
            </div>
        </summary>
        <div class="accordion-body">
            <div class="grid">
                <label>Presupuesto
                    <input type="number" step="0.01" name="budget" value="<?= htmlspecialchars((string) ($project['budget'] ?? 0)) ?>">
                </label>
                <label>Costo real
                    <input type="number" step="0.01" name="actual_cost" value="<?= htmlspecialchars((string) ($project['actual_cost'] ?? 0)) ?>">
                </label>
                <label>Horas planificadas
                    <input type="number" step="0.01" name="planned_hours" value="<?= htmlspecialchars((string) ($project['planned_hours'] ?? 0)) ?>">
                </label>
                <label>Horas reales
                    <input type="number" step="0.01" name="actual_hours" value="<?= htmlspecialchars((string) ($project['actual_hours'] ?? 0)) ?>">
                </label>
            </div>
        </div>
    </details>

    <details class="accordion" open id="riesgos">
        <summary class="accordion-summary">
            <div>
                <p class="section-label">Riesgos</p>
                <strong>Catálogo global por categoría</strong>
            </div>
        </summary>
        <div class="accordion-body">
            <div class="grid">
                <label>Riesgo consolidado
                    <input name="health" value="<?= htmlspecialchars($project['health'] ?? '') ?>" readonly aria-readonly="true">
                </label>
            </div>
            <fieldset class="risk-fieldset">
                <legend>Riesgos (catálogo global)</legend>
                <div class="risk-grid">
                    <?php foreach ($riskGroups as $category => $risks): ?>
                        <?php $riskCount = count($risks); ?>
                        <div class="risk-group" data-risk-group>
                            <div class="risk-group__header">
                                <strong><?= htmlspecialchars($category) ?></strong>
                                <?php if ($riskCount > 6): ?>
                                    <button type="button" class="link-button" data-risk-toggle>Ver más</button>
                                <?php endif; ?>
                            </div>
                            <div class="risk-chips<?= $riskCount > 6 ? ' is-collapsed' : '' ?>" data-collapsible="<?= $riskCount > 6 ? 'true' : 'false' ?>">
                                <?php foreach ($risks as $risk): ?>
                                    <?php $riskCode = $risk['code'] ?? ''; $riskLabel = $risk['label'] ?? $riskCode; ?>
                                    <label class="risk-chip">
                                        <input type="checkbox" name="risks[]" value="<?= htmlspecialchars($riskCode) ?>" <?= in_array($riskCode, $selectedRisks, true) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($riskLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($riskCatalog)): ?>
                        <div class="empty-state">No hay riesgos seleccionados.</div>
                    <?php endif; ?>
                </div>
            </fieldset>
        </div>
    </details>
</form>

<?php if ($canDelete || $canInactivate): ?>
    <details class="accordion danger-zone" open id="zona-critica">
        <summary class="accordion-summary">
            <div>
                <p class="section-label">Zona crítica</p>
                <strong>Eliminación y dependencias</strong>
            </div>
        </summary>
        <div class="accordion-body">
            <div class="danger-header">
                <span aria-hidden="true" class="danger-icon">!</span>
                <div>
                    <p class="danger-title">Zona crítica</p>
                    <p class="danger-text">Elimina el proyecto y todas sus dependencias (tareas, timesheets, nodos ISO, asignaciones, evidencias y archivos). Solo roles autorizados pueden continuar.</p>
                </div>
            </div>

            <div class="danger-box">
                <p class="danger-subtitle">Dependencias detectadas</p>
                <ul class="danger-grid">
                    <li><?= (int) ($dependencies['tasks'] ?? 0) ?> tareas</li>
                    <li><?= (int) ($dependencies['timesheets'] ?? 0) ?> timesheets</li>
                    <li><?= (int) ($dependencies['assignments'] ?? 0) ?> asignaciones</li>
                    <li><?= (int) ($dependencies['outsourcing_followups'] ?? 0) ?> seguimientos outsourcing</li>
                    <li><?= (int) ($dependencies['design_inputs'] ?? 0) ?> entradas de diseño</li>
                    <li><?= (int) ($dependencies['design_controls'] ?? 0) ?> controles de diseño</li>
                    <li><?= (int) ($dependencies['design_changes'] ?? 0) ?> cambios de diseño</li>
                    <li><?= (int) ($dependencies['nodes'] ?? 0) ?> nodos/evidencias ISO</li>
                </ul>
                <?php if ($hasDependencies): ?>
                    <p class="danger-note">La eliminación forzada borrará todo en cascada. No quedarán registros huérfanos.</p>
                <?php endif; ?>
            </div>

            <form method="POST" action="<?= htmlspecialchars($dangerActionUrl) ?>" id="danger-delete-form" class="danger-form">
                <input type="hidden" name="id" value="<?= (int) ($project['id'] ?? 0) ?>">
                <input type="hidden" name="math_operand1" value="<?= (int) $mathOperand1 ?>">
                <input type="hidden" name="math_operand2" value="<?= (int) $mathOperand2 ?>">
                <input type="hidden" name="math_operator" value="<?= htmlspecialchars($mathOperator) ?>">
                <input type="hidden" name="force_delete" value="<?= $canDelete ? '1' : '0' ?>">
                <div>
                    <p class="danger-title">Confirmación obligatoria</p>
                    <p class="section-muted">Resuelve la operación para habilitar la <?= htmlspecialchars($dangerActionText) ?>.</p>
                    <div class="danger-math">
                        <div class="danger-math__operand">
                            <?= (int) $mathOperand1 ?> <?= htmlspecialchars($mathOperator) ?> <?= (int) $mathOperand2 ?> =
                        </div>
                        <input type="number" name="math_result" id="math_result" inputmode="numeric" aria-label="Resultado de la operación" placeholder="Resultado">
                    </div>
                </div>

                <div id="delete-feedback" class="danger-feedback"></div>

                <div class="danger-actions">
                    <button type="submit" class="btn danger" id="confirm-delete-btn"><?= htmlspecialchars($dangerButtonLabel) ?></button>
                </div>
            </form>
        </div>
    </details>
<?php endif; ?>

<style>
    .project-form { display:flex; flex-direction:column; gap:16px; background: var(--surface); border:1px solid var(--border); padding:16px; border-radius:16px; }
    .form-header { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; }
    .form-header h3 { margin:0; color: var(--text-primary); }
    .form-tabs { display:flex; flex-wrap:wrap; gap:8px; border-bottom:1px solid var(--border); padding-bottom:8px; }
    .form-tabs a { padding:8px 12px; border-radius:999px; border:1px solid var(--border); text-decoration:none; color: var(--text-primary); font-weight:700; font-size:13px; background: color-mix(in srgb, var(--text-secondary) 14%, var(--background)); }
    .form-tabs a:hover { background: color-mix(in srgb, var(--primary) 12%, var(--background)); color: var(--primary); }
    .form-tabs a.danger-tab { border-color: color-mix(in srgb, var(--danger) 40%, var(--background)); color: var(--danger); }

    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    .accordion { border:1px solid var(--border); border-radius:14px; background: var(--surface); }
    .accordion-summary { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:12px 14px; cursor:pointer; list-style:none; }
    .accordion-summary::-webkit-details-marker { display:none; }
    .accordion-body { padding:0 14px 14px; display:flex; flex-direction:column; gap:12px; }
    .hint-box { background: color-mix(in srgb, var(--primary) 10%, var(--background)); border:1px solid color-mix(in srgb, var(--primary) 40%, var(--background)); color: var(--text-primary); padding:10px; border-radius:10px; }

    .risk-fieldset { border:1px solid var(--border); padding:12px; border-radius:12px; }
    .risk-fieldset legend { font-weight:700; color: var(--text-primary); padding:0 6px; }
    .risk-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    .risk-group { border:1px solid var(--border); padding:10px; border-radius:12px; background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); display:flex; flex-direction:column; gap:10px; }
    .risk-group__header { display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .risk-chips { display:grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap:8px; }
    .risk-chips.is-collapsed .risk-chip:nth-child(n+7) { display:none; }
    .risk-chip { display:flex; gap:8px; align-items:flex-start; background: var(--surface); padding:8px 10px; border-radius:10px; border:1px solid var(--border); }
    .risk-chip input { margin-top:2px; }
    .link-button { border:none; background: var(--background); color: var(--primary); font-weight:700; cursor:pointer; font-size:12px; }

    .empty-state { padding:10px 12px; border-radius:10px; background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); color: var(--text-secondary); font-weight:600; }

    .danger-zone { margin-top:16px; border-color: color-mix(in srgb, var(--danger) 35%, var(--background)); background: color-mix(in srgb, var(--danger) 10%, var(--surface) 90%); }
    .danger-header { display:flex; gap:12px; align-items:flex-start; }
    .danger-icon { width:34px; height:34px; border-radius:10px; background: color-mix(in srgb, var(--danger) 16%, var(--background)); color: var(--danger); border:1px solid color-mix(in srgb, var(--danger) 40%, var(--background)); display:inline-flex; align-items:center; justify-content:center; font-weight:800; }
    .danger-title { margin:0; font-weight:700; color: var(--danger); }
    .danger-text { margin:4px 0 0 0; color: color-mix(in srgb, var(--danger) 80%, var(--text-primary) 20%); }
    .danger-box { border:1px solid color-mix(in srgb, var(--warning) 40%, var(--background)); background: color-mix(in srgb, var(--warning) 10%, var(--surface) 90%); border-radius:12px; padding:12px; }
    .danger-subtitle { margin:0 0 6px 0; font-weight:600; color: var(--warning); }
    .danger-grid { margin:0; padding-left:18px; color: var(--warning); display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:4px 12px; }
    .danger-note { margin:8px 0 0 0; color: color-mix(in srgb, var(--warning) 80%, var(--text-primary) 20%); font-size:14px; }
    .danger-math { display:flex; align-items:center; gap:10px; }
    .danger-math__operand { padding:10px 12px; border:1px solid var(--border); border-radius:10px; background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); font-weight:700; }
    .danger-form { display:flex; flex-direction:column; gap:12px; }
    .danger-feedback { display:none; padding:10px 12px; border:1px solid color-mix(in srgb, var(--danger) 35%, var(--background)); background: color-mix(in srgb, var(--danger) 12%, var(--background)); color: var(--danger); border-radius:10px; font-weight:600; }
    .danger-actions { display:flex; justify-content:flex-start; gap:8px; }
    .danger-actions .btn { min-width: 240px; }
    .danger-actions .btn.danger { min-width: 240px; color: #7f1d1d; border-color: #b91c1c; background: #fee2e2; }
    .danger-actions .btn.danger:hover { color:#fff; border-color:#991b1b; background:#b91c1c; }
    .danger-actions .btn.danger.is-ready { color:#fff; border-color:#991b1b; background:#b91c1c; }
    .btn.danger { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--background)); background: color-mix(in srgb, var(--danger) 12%, var(--background)); }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
</style>

<script>
    const phasesByMethodology = <?= json_encode($phasesByMethodology) ?>;
    const phaseSelect = document.getElementById('phaseSelect');
    const methodologySelect = document.getElementById('methodologySelect');
    const projectTypeSelect = document.getElementById('projectTypeSelect');
    const endDateGroup = document.querySelector('[data-role="end-date"]');
    const phaseLabel = document.querySelector('[data-role="phase-label"]');
    const phaseLabelText = phaseLabel ? phaseLabel.querySelector('.label-text') : null;
    const scrumHint = document.getElementById('scrumHint');
    const progressInput = document.getElementById('progressInput');

    function refreshPhases() {
        const selected = methodologySelect.value;
        const phases = phasesByMethodology[selected] || [];
        const currentPhase = phaseSelect.value;

        phaseSelect.innerHTML = '';
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = 'Sin fase';
        phaseSelect.appendChild(emptyOption);

        phases.forEach(phase => {
            const option = document.createElement('option');
            option.value = phase;
            option.textContent = phase.charAt(0).toUpperCase() + phase.slice(1);
            if (phase === currentPhase) {
                option.selected = true;
            }
            phaseSelect.appendChild(option);
        });
    }

    function toggleByProjectType() {
        const selectedType = projectTypeSelect.value;
        if (selectedType === 'scrum') {
            if (endDateGroup) {
                endDateGroup.style.display = 'none';
                const endDateInput = document.getElementById('endDateInput');
                if (endDateInput) {
                    endDateInput.value = '';
                }
            }
            if (phaseLabelText) {
                phaseLabelText.textContent = 'Sprint / fase activa';
            }
            if (scrumHint) {
                scrumHint.style.display = 'block';
            }
            if (progressInput) {
                progressInput.placeholder = 'Seguimiento de sprint';
            }
        } else {
            if (endDateGroup) {
                endDateGroup.style.display = '';
            }
            if (phaseLabelText) {
                phaseLabelText.textContent = 'Fase';
            }
            if (scrumHint) {
                scrumHint.style.display = 'none';
            }
            if (progressInput) {
                progressInput.placeholder = 'Avance porcentual';
            }
        }
    }

    if (methodologySelect && phaseSelect) {
        methodologySelect.addEventListener('change', refreshPhases);
    }

    if (projectTypeSelect) {
        projectTypeSelect.addEventListener('change', toggleByProjectType);
        toggleByProjectType();
    }

    document.querySelectorAll('[data-risk-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const group = button.closest('[data-risk-group]');
            const chips = group?.querySelector('.risk-chips');
            if (!chips) return;
            const isExpanded = chips.classList.toggle('is-expanded');
            chips.classList.toggle('is-collapsed', !isExpanded);
            button.textContent = isExpanded ? 'Ver menos' : 'Ver más';
        });
    });

    const deleteForm = document.getElementById('danger-delete-form');
    const deleteResult = document.getElementById('math_result');
    const deleteButton = document.getElementById('confirm-delete-btn');
    const deleteFeedback = document.getElementById('delete-feedback');

    if (deleteForm && deleteResult && deleteButton) {
        const operand1 = Number(deleteForm.querySelector('[name="math_operand1"]')?.value || 0);
        const operand2 = Number(deleteForm.querySelector('[name="math_operand2"]')?.value || 0);
        const operator = (deleteForm.querySelector('[name="math_operator"]')?.value || '').trim();
        const expected = operator === '+' ? operand1 + operand2 : operand1 - operand2;

        const syncDeleteState = () => {
            const current = Number(deleteResult.value.trim());
            const isValid = !Number.isNaN(current) && current === expected;
            deleteButton.classList.toggle('is-ready', isValid);
        };

        deleteResult.addEventListener('input', syncDeleteState);

        deleteForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            deleteFeedback.style.display = 'none';
            deleteFeedback.textContent = '';

            try {
                const response = await fetch(deleteForm.getAttribute('action') || '', {
                    method: 'POST',
                    body: new FormData(deleteForm),
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (data?.success) {
                    alert(data.message || 'Operación completada correctamente.');
                    window.location.href = '<?= $basePath ?>/projects';
                    return;
                }

                deleteFeedback.textContent = data?.message || 'No se pudo completar la operación.';
                deleteFeedback.style.display = 'block';
            } catch (error) {
                deleteFeedback.textContent = 'No se pudo completar la operación. Intenta nuevamente o contacta al administrador.';
                deleteFeedback.style.display = 'block';
            }
        });

        syncDeleteState();
    }
</script>
