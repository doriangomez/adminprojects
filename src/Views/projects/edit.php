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

<form action="<?= htmlspecialchars($formAction) ?>" method="POST" style="display:flex; flex-direction:column; gap:12px; background: var(--surface); border:1px solid var(--border); padding:16px; border-radius:14px;">
    <h3 style="margin:0;"><?= htmlspecialchars($formTitle) ?></h3>
    <label>Nombre
        <input name="name" value="<?= htmlspecialchars($project['name'] ?? '') ?>" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Estado
        <input name="status" value="<?= htmlspecialchars($project['status'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Riesgo
        <input name="health" value="<?= htmlspecialchars($project['health'] ?? '') ?>" readonly aria-readonly="true" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Prioridad
        <input name="priority" value="<?= htmlspecialchars($project['priority'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>PM (ID)
        <input type="number" name="pm_id" value="<?= (int) ($project['pm_id'] ?? 0) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Tipo de proyecto
        <select name="project_type" id="projectTypeSelect" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
            <option value="convencional" <?= $projectType === 'convencional' ? 'selected' : '' ?>>Convencional (fechas y fases)</option>
            <option value="scrum" <?= $projectType === 'scrum' ? 'selected' : '' ?>>Scrum (sprints y backlog)</option>
            <option value="hibrido" <?= $projectType === 'hibrido' ? 'selected' : '' ?>>Híbrido (mixto)</option>
        </select>
        <small class="subtext">Convencional usa hitos secuenciales; Scrum trabaja en sprints sin fecha fin rígida.</small>
    </label>
    <label>Metodología
        <select name="methodology" id="methodologySelect" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
            <?php foreach ($methodologies as $methodology): ?>
                <option value="<?= htmlspecialchars($methodology) ?>" <?= $methodology === $currentMethodology ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($methodology)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label data-role="phase-label"><span class="label-text">Fase / sprint</span>
        <select name="phase" id="phaseSelect" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
            <option value="">Sin fase</option>
            <?php foreach ($currentPhases as $phase): ?>
                <option value="<?= htmlspecialchars($phase) ?>" <?= ($project['phase'] ?? '') === $phase ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($phase)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Presupuesto
        <input type="number" step="0.01" name="budget" value="<?= htmlspecialchars((string) ($project['budget'] ?? 0)) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Costo real
        <input type="number" step="0.01" name="actual_cost" value="<?= htmlspecialchars((string) ($project['actual_cost'] ?? 0)) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Horas planificadas
        <input type="number" step="0.01" name="planned_hours" value="<?= htmlspecialchars((string) ($project['planned_hours'] ?? 0)) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <label>Horas reales
        <input type="number" step="0.01" name="actual_hours" value="<?= htmlspecialchars((string) ($project['actual_hours'] ?? 0)) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
    </label>
    <fieldset style="border:1px solid var(--border); padding:10px; border-radius:12px;">
        <legend style="font-weight:700; color:var(--text);">Riesgos (catálogo global)</legend>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <?php foreach ($riskGroups as $category => $risks): ?>
                <div style="border:1px solid var(--border); padding:10px; border-radius:10px; background:#f8fafc;">
                    <strong style="display:block; margin-bottom:6px;"><?= htmlspecialchars($category) ?></strong>
                    <div style="display:flex; flex-wrap:wrap; gap:10px;">
                        <?php foreach ($risks as $risk): ?>
                            <?php $riskCode = $risk['code'] ?? ''; $riskLabel = $risk['label'] ?? $riskCode; ?>
                            <label style="display:flex; gap:6px; align-items:center; background:#fff; padding:8px 10px; border-radius:10px; border:1px solid var(--border);">
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
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
        <label>Inicio
            <input type="date" name="start_date" value="<?= htmlspecialchars((string) ($project['start_date'] ?? '')) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
        </label>
        <label data-role="end-date">Fin
            <input type="date" name="end_date" id="endDateInput" value="<?= htmlspecialchars((string) ($project['end_date'] ?? '')) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
        </label>
    </div>
    <div id="scrumHint" style="display: <?= $projectType === 'scrum' ? 'block' : 'none' ?>; background:#ecfeff; border:1px solid #06b6d4; color:#0f172a; padding:10px; border-radius:10px;">
        Para proyectos Scrum, administra sprints y backlog sin fecha de cierre fija. El progreso refleja el avance del sprint actual.
    </div>
    <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Guardar cambios</button>
</form>

<?php if ($canDelete || $canInactivate): ?>
    <section style="margin-top:16px; padding:16px; border:1px solid #fecaca; background:#fff7ed; border-radius:14px; display:flex; flex-direction:column; gap:12px;">
        <div style="display:flex; gap:12px; align-items:flex-start;">
            <span aria-hidden="true" style="width:34px; height:34px; border-radius:10px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; display:inline-flex; align-items:center; justify-content:center; font-weight:800;">!</span>
            <div>
                <p style="margin:0; font-weight:700; color:#b91c1c;">Zona crítica</p>
                <p style="margin:4px 0 0 0; color:#7f1d1d;">Elimina el proyecto y todas sus dependencias (tareas, timesheets, nodos ISO, asignaciones, evidencias y archivos). Solo roles autorizados pueden continuar.</p>
            </div>
        </div>

        <div style="border:1px solid #fed7aa; background:#fffbeb; border-radius:12px; padding:12px;">
            <p style="margin:0 0 6px 0; font-weight:600; color:#92400e;">Dependencias detectadas</p>
            <ul style="margin:0; padding-left:18px; color:#b45309; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:4px 12px;">
                <li><?= (int) ($dependencies['tasks'] ?? 0) ?> tareas</li>
                <li><?= (int) ($dependencies['timesheets'] ?? 0) ?> timesheets</li>
                <li><?= (int) ($dependencies['assignments'] ?? 0) ?> asignaciones</li>
                <li><?= (int) ($dependencies['design_inputs'] ?? 0) ?> entradas de diseño</li>
                <li><?= (int) ($dependencies['design_controls'] ?? 0) ?> controles de diseño</li>
                <li><?= (int) ($dependencies['design_changes'] ?? 0) ?> cambios de diseño</li>
                <li><?= (int) ($dependencies['nodes'] ?? 0) ?> nodos/evidencias ISO</li>
            </ul>
            <?php if ($hasDependencies): ?>
                <p style="margin:8px 0 0 0; color:#9a3412; font-size:14px;">La eliminación forzada borrará todo en cascada. No quedarán registros huérfanos.</p>
            <?php endif; ?>
        </div>

        <?php if ($canDelete): ?>
            <form method="POST" action="<?= $basePath ?>/projects/delete" id="danger-delete-form" class="grid" style="gap:12px;">
                <input type="hidden" name="id" value="<?= (int) ($project['id'] ?? 0) ?>">
                <input type="hidden" name="math_operand1" value="<?= (int) $mathOperand1 ?>">
                <input type="hidden" name="math_operand2" value="<?= (int) $mathOperand2 ?>">
                <input type="hidden" name="math_operator" value="<?= htmlspecialchars($mathOperator) ?>">
                <input type="hidden" name="force_delete" value="1">
                <div>
                    <p style="margin:0 0 4px 0; color:#7f1d1d; font-weight:700;">Confirmación obligatoria</p>
                    <p style="margin:0 0 8px 0; color:#9ca3af;">Resuelve la operación para habilitar la eliminación definitiva.</p>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="flex:1;">
                            <div style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:rgb(249, 250, 251); font-weight:700;">
                                <?= (int) $mathOperand1 ?> <?= htmlspecialchars($mathOperator) ?> <?= (int) $mathOperand2 ?> =
                            </div>
                        </div>
                        <input type="number" name="math_result" id="math_result" inputmode="numeric" aria-label="Resultado de la operación" placeholder="Resultado" style="width:140px; padding:10px 12px; border-radius:10px; border:1px solid var(--border);">
                    </div>
                </div>

                <div id="delete-feedback" style="display:none; padding:10px 12px; border:1px solid #fecaca; background:#fef2f2; color:#b91c1c; border-radius:10px; font-weight:600;"></div>

                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="submit" class="btn" id="confirm-delete-btn" style="color:#b91c1c; border-color:#fecaca; background:#fef2f2;" disabled>Eliminar permanentemente</button>
                </div>
            </form>
        <?php else: ?>
            <p style="margin:0; color:#92400e;">Solo administradores o PMO pueden eliminar definitivamente un proyecto. Solicita asistencia a un administrador.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

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
