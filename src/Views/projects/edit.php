<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$deliveryConfig = is_array($delivery ?? null) ? $delivery : ['methodologies' => [], 'phases' => [], 'risks' => []];
$methodologies = $deliveryConfig['methodologies'] ?? [];
$phasesByMethodology = $deliveryConfig['phases'] ?? [];
$riskCatalog = $deliveryConfig['risks'] ?? [];
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
        <input name="health" value="<?= htmlspecialchars($project['health'] ?? '') ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
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
    <label id="progressGroup">Progreso (%)
        <input type="number" step="0.1" name="progress" id="progressInput" value="<?= htmlspecialchars((string) ($project['progress'] ?? 0)) ?>" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;" placeholder="Seguimiento porcentual o por sprint">
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
</script>
