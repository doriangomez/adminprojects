<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$deliveryConfig = is_array($delivery ?? null) ? $delivery : ['methodologies' => [], 'phases' => [], 'risks' => []];
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

    <details class="accordion" open>
        <summary class="accordion-summary">
            <div>
                <p class="section-label">Datos generales</p>
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
                <label>PM (ID)
                    <input type="number" name="pm_id" value="<?= (int) ($project['pm_id'] ?? 0) ?>">
                </label>
            </div>
        </div>
    </details>

    <details class="accordion" open>
        <summary class="accordion-summary">
            <div>
                <p class="section-label">Metodología</p>
                <strong>Tipo, enfoque y fase activa</strong>
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
            <div id="scrumHint" class="hint-box" style="display: <?= $projectType === 'scrum' ? 'block' : 'none' ?>;">
                Para proyectos Scrum, administra sprints y backlog sin fecha de cierre fija. El progreso refleja el avance del sprint actual.
            </div>
        </div>
    </details>

    <details class="accordion" open>
        <summary class="accordion-summary">
            <div>
                <p class="section-label">Fechas y presupuesto</p>
                <strong>Planificación financiera y tiempos</strong>
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
                <label>Inicio
                    <input type="date" name="start_date" value="<?= htmlspecialchars((string) ($project['start_date'] ?? '')) ?>">
                </label>
                <label data-role="end-date">Fin
                    <input type="date" name="end_date" id="endDateInput" value="<?= htmlspecialchars((string) ($project['end_date'] ?? '')) ?>">
                </label>
            </div>
        </div>
    </details>

    <details class="accordion" open>
        <summary class="accordion-summary">
            <div>
                <p class="section-label">Riesgos</p>
                <strong>Catálogo y evaluación actual</strong>
            </div>
        </summary>
        <div class="accordion-body">
            <div class="grid">
                <label>Riesgo
                    <input name="health" value="<?= htmlspecialchars($project['health'] ?? '') ?>" readonly aria-readonly="true">
                </label>
            </div>
            <fieldset class="risk-fieldset">
                <legend>Riesgos (catálogo global)</legend>
                <div class="risk-stack">
                    <?php foreach ($riskGroups as $category => $risks): ?>
                        <div class="risk-group">
                            <strong><?= htmlspecialchars($category) ?></strong>
                            <div class="risk-chips">
                                <?php foreach ($risks as $risk): ?>
                                    <?php $riskCode = $risk['code'] ?? ''; $riskLabel = $risk['label'] ?? $riskCode; ?>
                                    <label class="risk-chip">
                                        <input type="checkbox" name="risks[]" value="<?= htmlspecialchars($riskCode) ?>" <?= in_array($riskCode, $selectedRisks, true) ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($riskLabel) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($riskCatalog)): ?>
                        <small class="subtext">Configura riesgos desde el módulo de configuración.</small>
                    <?php endif; ?>
                </div>
            </fieldset>
        </div>
    </details>
</form>

<?php if ($canDelete || $canInactivate): ?>
    <details class="accordion danger-zone" open>
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

            <?php if ($canDelete): ?>
                <form method="POST" action="<?= $basePath ?>/projects/delete" id="danger-delete-form" class="grid">
                    <input type="hidden" name="id" value="<?= (int) ($project['id'] ?? 0) ?>">
                    <input type="hidden" name="math_operand1" value="<?= (int) $mathOperand1 ?>">
                    <input type="hidden" name="math_operand2" value="<?= (int) $mathOperand2 ?>">
                    <input type="hidden" name="math_operator" value="<?= htmlspecialchars($mathOperator) ?>">
                    <input type="hidden" name="force_delete" value="1">
                    <div>
                        <p class="danger-title">Confirmación obligatoria</p>
                        <p class="section-muted">Resuelve la operación para habilitar la eliminación definitiva.</p>
                        <div class="danger-math">
                            <div class="danger-math__operand">
                                <?= (int) $mathOperand1 ?> <?= htmlspecialchars($mathOperator) ?> <?= (int) $mathOperand2 ?> =
                            </div>
                            <input type="number" name="math_result" id="math_result" inputmode="numeric" aria-label="Resultado de la operación" placeholder="Resultado">
                        </div>
                    </div>

                    <div id="delete-feedback" class="danger-feedback"></div>

                    <div class="danger-actions">
                        <button type="submit" class="btn danger" id="confirm-delete-btn" disabled>Eliminar permanentemente</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="section-muted">Solo administradores o PMO pueden eliminar definitivamente un proyecto. Solicita asistencia a un administrador.</p>
            <?php endif; ?>
        </div>
    </details>
<?php endif; ?>

<style>
    .project-form { display:flex; flex-direction:column; gap:16px; background: var(--surface); border:1px solid var(--border); padding:16px; border-radius:16px; }
    .form-header { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; }
    .form-header h3 { margin:0; color: var(--text-strong); }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    .accordion { border:1px solid var(--border); border-radius:14px; background:#fff; }
    .accordion-summary { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:12px 14px; cursor:pointer; list-style:none; }
    .accordion-summary::-webkit-details-marker { display:none; }
    .accordion-body { padding:0 14px 14px; display:flex; flex-direction:column; gap:12px; }
    .hint-box { background:#ecfeff; border:1px solid #06b6d4; color:#0f172a; padding:10px; border-radius:10px; }
    .risk-fieldset { border:1px solid var(--border); padding:12px; border-radius:12px; }
    .risk-fieldset legend { font-weight:700; color: var(--text-strong); padding:0 6px; }
    .risk-stack { display:flex; flex-direction:column; gap:10px; }
    .risk-group { border:1px solid var(--border); padding:10px; border-radius:10px; background:#f8fafc; display:flex; flex-direction:column; gap:6px; }
    .risk-chips { display:flex; flex-wrap:wrap; gap:8px; }
    .risk-chip { display:flex; gap:6px; align-items:center; background:#fff; padding:8px 10px; border-radius:10px; border:1px solid var(--border); }
    .danger-zone { margin-top:16px; border-color:#fecaca; background:#fff7ed; }
    .danger-header { display:flex; gap:12px; align-items:flex-start; }
    .danger-icon { width:34px; height:34px; border-radius:10px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; display:inline-flex; align-items:center; justify-content:center; font-weight:800; }
    .danger-title { margin:0; font-weight:700; color:#b91c1c; }
    .danger-text { margin:4px 0 0 0; color:#7f1d1d; }
    .danger-box { border:1px solid #fed7aa; background:#fffbeb; border-radius:12px; padding:12px; }
    .danger-subtitle { margin:0 0 6px 0; font-weight:600; color:#92400e; }
    .danger-grid { margin:0; padding-left:18px; color:#b45309; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:4px 12px; }
    .danger-note { margin:8px 0 0 0; color:#9a3412; font-size:14px; }
    .danger-math { display:flex; align-items:center; gap:10px; }
    .danger-math__operand { padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:rgb(249, 250, 251); font-weight:700; }
    .danger-feedback { display:none; padding:10px 12px; border:1px solid #fecaca; background:#fef2f2; color:#b91c1c; border-radius:10px; font-weight:600; }
    .danger-actions { display:flex; justify-content:flex-end; gap:8px; }
    .btn.danger { color:#b91c1c; border-color:#fecaca; background:#fef2f2; }
    .action-btn { background: var(--surface); color: var(--text-strong); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color:#fff; border-color: var(--primary); }
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
            deleteButton.disabled = !isValid;
        };

        deleteResult.addEventListener('input', syncDeleteState);

        deleteForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (deleteButton.disabled) return;

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
                    alert(data.message || 'Proyecto eliminado correctamente.');
                    window.location.href = '<?= $basePath ?>/projects';
                    return;
                }

                deleteFeedback.textContent = data?.message || 'No se pudo completar la eliminación.';
                deleteFeedback.style.display = 'block';
            } catch (error) {
                deleteFeedback.textContent = 'No se pudo completar la eliminación. Intenta nuevamente o contacta al administrador.';
                deleteFeedback.style.display = 'block';
            }
        });

        syncDeleteState();
    }
</script>
