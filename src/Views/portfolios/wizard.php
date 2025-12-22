<?php
$projectEntries = $oldInput['projects'] ?? [
    ['name' => '', 'project_type' => 'convencional', 'pm_id' => '', 'start_date' => '', 'end_date' => '', 'budget' => '', 'priority' => $priorities[0]['code'] ?? '']
];
if (empty($projectEntries)) {
    $projectEntries = [
        ['name' => '', 'project_type' => 'convencional', 'pm_id' => '', 'start_date' => '', 'end_date' => '', 'budget' => '', 'priority' => $priorities[0]['code'] ?? ''],
    ];
}
$defaultClientMode = ($oldInput['client_mode'] ?? '') === 'new' ? 'new' : 'existing';
?>
<div class="card portfolio-card">
    <div class="toolbar portfolio-toolbar">
        <div>
            <p class="eyebrow">Crear portafolio estratégico</p>
            <h2 class="portfolio-title">Wizard unificado de cliente → portafolio → proyectos</h2>
            <p class="muted">Flujo completo: registra o selecciona cliente, arma el portafolio, vincula riesgos y crea proyectos.
                No se gestionan horas, tareas ni talento desde aquí.</p>
        </div>
        <div class="wizard-actions">
            <a class="button ghost" href="<?= $basePath ?>/portfolio">Volver a análisis</a>
            <span class="badge neutral">4 pasos</span>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form class="wizard" method="POST" action="<?= $basePath ?>/portfolio/wizard" enctype="multipart/form-data" id="portfolioWizard">
        <div class="wizard-steps" aria-label="Pasos del asistente">
            <div class="wizard-step active" data-step="1">1. Cliente</div>
            <div class="wizard-step" data-step="2">2. Portafolio</div>
            <div class="wizard-step" data-step="3">3. Riesgos</div>
            <div class="wizard-step" data-step="4">4. Proyectos</div>
        </div>

        <div class="wizard-body">
            <section class="wizard-panel" data-step-panel="1">
                <div class="panel-header">
                    <div>
                        <h3>Cliente</h3>
                        <p class="muted">Selecciona un cliente existente o registra uno nuevo con los campos mínimos.</p>
                    </div>
                    <div class="radio-group inline">
                        <label class="radio">
                            <input type="radio" name="client_mode" value="existing" <?= $defaultClientMode === 'existing' ? 'checked' : '' ?>>
                            Usar existente
                        </label>
                        <label class="radio">
                            <input type="radio" name="client_mode" value="new" <?= $defaultClientMode === 'new' ? 'checked' : '' ?>>
                            Crear nuevo
                        </label>
                    </div>
                </div>
                <div class="grid two-cols" id="existingClientBox">
                    <label>Cliente existente
                        <select name="client_id">
                            <option value="">Selecciona un cliente</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>" <?= (($oldInput['client_id'] ?? '') == $client['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="subtext">Se respetarán PM y reglas existentes.</small>
                    </label>
                </div>

                <div class="grid three-cols" id="newClientBox">
                    <label>Nombre del cliente
                        <input name="client_name" value="<?= htmlspecialchars($oldInput['client_name'] ?? '') ?>" placeholder="Ej. Contoso" autocomplete="organization">
                    </label>
                    <label>Sector
                        <select name="client_sector">
                            <option value="">Elige sector</option>
                            <?php foreach ($sectors as $sector): ?>
                                <option value="<?= htmlspecialchars($sector['code']) ?>" <?= (($oldInput['client_sector'] ?? '') === $sector['code']) ? 'selected' : '' ?>><?= htmlspecialchars($sector['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Categoría
                        <select name="client_category">
                            <option value="">Elige categoría</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['code']) ?>" <?= (($oldInput['client_category'] ?? '') === $category['code']) ? 'selected' : '' ?>><?= htmlspecialchars($category['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Prioridad
                        <select name="client_priority">
                            <option value="">Elige prioridad</option>
                            <?php foreach ($priorities as $priority): ?>
                                <option value="<?= htmlspecialchars($priority['code']) ?>" <?= (($oldInput['client_priority'] ?? '') === $priority['code']) ? 'selected' : '' ?>><?= htmlspecialchars($priority['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Estado
                        <select name="client_status">
                            <option value="">Elige estado</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= htmlspecialchars($status['code']) ?>" <?= (($oldInput['client_status'] ?? '') === $status['code']) ? 'selected' : '' ?>><?= htmlspecialchars($status['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>PM responsable
                        <select name="client_pm">
                            <option value="">Selecciona PM</option>
                            <?php foreach ($projectManagers as $pm): ?>
                                <option value="<?= (int) $pm['id'] ?>" <?= (($oldInput['client_pm'] ?? '') == $pm['id']) ? 'selected' : '' ?>><?= htmlspecialchars($pm['name']) ?> (<?= htmlspecialchars($pm['role_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </section>

            <section class="wizard-panel hidden" data-step-panel="2">
                <h3>Portafolio</h3>
                <p class="muted">Define objetivo, alcance temporal y presupuesto. Adjunta la propuesta o contrato si aplica.</p>
                <div class="grid two-cols">
                    <label>Nombre del portafolio
                        <input name="portfolio_name" required value="<?= htmlspecialchars($oldInput['portfolio_name'] ?? '') ?>" placeholder="Ej. Crecimiento LATAM 2024">
                    </label>
                    <label>Objetivo estratégico
                        <input name="portfolio_objective" value="<?= htmlspecialchars($oldInput['portfolio_objective'] ?? '') ?>" placeholder="Ej. Consolidar expansión regional">
                    </label>
                    <label>Fecha de inicio
                        <input type="date" name="portfolio_start" value="<?= htmlspecialchars($oldInput['portfolio_start'] ?? '') ?>">
                    </label>
                    <label>Fecha de cierre
                        <input type="date" name="portfolio_end" value="<?= htmlspecialchars($oldInput['portfolio_end'] ?? '') ?>">
                        <small class="subtext">Se alertará <?= (int) ($operationalRules['alerts']['portfolio_days_before_end'] ?? 15) ?> días antes.</small>
                    </label>
                    <label>Presupuesto total (<?= htmlspecialchars('USD') ?>)
                        <input type="number" step="0.01" name="portfolio_budget" value="<?= htmlspecialchars($oldInput['portfolio_budget'] ?? '') ?>" placeholder="Ej. 250000">
                    </label>
                    <label>Documento de referencia
                        <input type="file" name="portfolio_attachment" accept="application/pdf,image/png,image/jpeg">
                        <small class="subtext">Propuesta firmada o contrato marco.</small>
                    </label>
                    <label class="full">Descripción ejecutiva
                        <textarea name="portfolio_description" rows="3" placeholder="Contexto, sponsors, dependencias clave"><?= htmlspecialchars($oldInput['portfolio_description'] ?? '') ?></textarea>
                    </label>
                </div>
            </section>

            <section class="wizard-panel hidden" data-step-panel="3">
                <h3>Riesgos</h3>
                <p class="muted">Selecciona los riesgos relevantes configurados. El nivel se calcula automáticamente en texto.</p>
                <div class="risk-grid">
                    <?php foreach ($riskCatalog as $risk): ?>
                        <label class="pill option">
                            <input type="checkbox" name="portfolio_risks[]" value="<?= htmlspecialchars($risk['code']) ?>" <?= in_array($risk['code'], $oldInput['portfolio_risks'] ?? [], true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($risk['label']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="risk-summary">
                    <p class="muted">Nivel calculado</p>
                    <div class="risk-level" id="riskLevel">Bajo</div>
                    <p class="muted small">Se almacena como texto para reportes y no depende de colores.</p>
                </div>
            </section>

            <section class="wizard-panel hidden" data-step-panel="4">
                <div class="panel-header">
                    <div>
                        <h3>Proyectos</h3>
                        <p class="muted">Crea uno o varios proyectos dentro del portafolio. No se asignan horas ni talento aquí.</p>
                    </div>
                    <button type="button" class="button secondary" id="addProject">Agregar proyecto</button>
                </div>
                <div id="projectsContainer" class="project-grid">
                    <?php foreach ($projectEntries as $index => $project): ?>
                        <div class="project-row" data-index="<?= (int) $index ?>">
                            <div class="row-header">
                                <strong>Proyecto <?= (int) ($index + 1) ?></strong>
                                <button type="button" class="icon-button remove-project" aria-label="Eliminar proyecto">✕</button>
                            </div>
                            <div class="grid three-cols">
                                <label>Nombre
                                    <input name="projects[<?= (int) $index ?>][name]" value="<?= htmlspecialchars($project['name'] ?? '') ?>" placeholder="Ej. Implementación CRM" required>
                                </label>
                                <label>Tipo
                                    <select name="projects[<?= (int) $index ?>][project_type]">
                                        <?php foreach ($projectTypes as $typeCode => $typeLabel): ?>
                                            <option value="<?= htmlspecialchars($typeCode) ?>" <?= (($project['project_type'] ?? '') === $typeCode) ? 'selected' : '' ?>><?= htmlspecialchars($typeLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>PM
                                    <select name="projects[<?= (int) $index ?>][pm_id]">
                                        <option value="">Selecciona</option>
                                        <?php foreach ($projectManagers as $pm): ?>
                                            <option value="<?= (int) $pm['id'] ?>" <?= (($project['pm_id'] ?? '') == $pm['id']) ? 'selected' : '' ?>><?= htmlspecialchars($pm['name']) ?> (<?= htmlspecialchars($pm['role_name']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Inicio
                                    <input type="date" name="projects[<?= (int) $index ?>][start_date]" value="<?= htmlspecialchars($project['start_date'] ?? '') ?>">
                                </label>
                                <label>Fin
                                    <input type="date" name="projects[<?= (int) $index ?>][end_date]" value="<?= htmlspecialchars($project['end_date'] ?? '') ?>">
                                </label>
                                <label>Presupuesto
                                    <input type="number" step="0.01" name="projects[<?= (int) $index ?>][budget]" value="<?= htmlspecialchars($project['budget'] ?? '') ?>" placeholder="0">
                                </label>
                                <label>Prioridad
                                    <select name="projects[<?= (int) $index ?>][priority]">
                                        <?php foreach ($priorities as $priority): ?>
                                            <option value="<?= htmlspecialchars($priority['code']) ?>" <?= (($project['priority'] ?? '') === $priority['code']) ? 'selected' : '' ?>><?= htmlspecialchars($priority['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="muted">Los proyectos se crean ligados al portafolio. No se permite existir sin esta relación.</small>
            </section>
        </div>

        <div class="wizard-footer">
            <button type="button" class="button ghost" id="prevStep" disabled>Anterior</button>
            <div class="spacer"></div>
            <button type="button" class="button secondary" id="nextStep">Siguiente</button>
            <button type="submit" class="button primary hidden" id="submitWizard">Finalizar y ver portafolio</button>
        </div>
    </form>
</div>

<template id="projectTemplate">
    <div class="project-row" data-index="__INDEX__">
        <div class="row-header">
            <strong>Proyecto __NUMBER__</strong>
            <button type="button" class="icon-button remove-project" aria-label="Eliminar proyecto">✕</button>
        </div>
        <div class="grid three-cols">
            <label>Nombre
                <input name="projects[__INDEX__][name]" placeholder="Ej. Nueva iniciativa" required>
            </label>
            <label>Tipo
                <select name="projects[__INDEX__][project_type]">
                    <?php foreach ($projectTypes as $typeCode => $typeLabel): ?>
                        <option value="<?= htmlspecialchars($typeCode) ?>"><?= htmlspecialchars($typeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>PM
                <select name="projects[__INDEX__][pm_id]">
                    <option value="">Selecciona</option>
                    <?php foreach ($projectManagers as $pm): ?>
                        <option value="<?= (int) $pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?> (<?= htmlspecialchars($pm['role_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Inicio
                <input type="date" name="projects[__INDEX__][start_date]">
            </label>
            <label>Fin
                <input type="date" name="projects[__INDEX__][end_date]">
            </label>
            <label>Presupuesto
                <input type="number" step="0.01" name="projects[__INDEX__][budget]" placeholder="0">
            </label>
            <label>Prioridad
                <select name="projects[__INDEX__][priority]">
                    <?php foreach ($priorities as $priority): ?>
                        <option value="<?= htmlspecialchars($priority['code']) ?>"><?= htmlspecialchars($priority['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </div>
</template>

<script>
const steps = Array.from(document.querySelectorAll('[data-step]'));
const panels = Array.from(document.querySelectorAll('[data-step-panel]'));
const prevBtn = document.getElementById('prevStep');
const nextBtn = document.getElementById('nextStep');
const submitBtn = document.getElementById('submitWizard');
const riskLevelBadge = document.getElementById('riskLevel');
const projectContainer = document.getElementById('projectsContainer');
const projectTemplate = document.getElementById('projectTemplate').innerHTML;
let currentStep = 1;

function updateStep(direction) {
    currentStep = Math.min(4, Math.max(1, currentStep + direction));
    steps.forEach(step => step.classList.toggle('active', Number(step.dataset.step) === currentStep));
    panels.forEach(panel => panel.classList.toggle('hidden', Number(panel.dataset.stepPanel) !== currentStep));
    prevBtn.disabled = currentStep === 1;
    nextBtn.classList.toggle('hidden', currentStep === 4);
    submitBtn.classList.toggle('hidden', currentStep !== 4);
}

function toggleClientMode() {
    const mode = document.querySelector('input[name="client_mode"]:checked')?.value || 'existing';
    document.getElementById('existingClientBox').style.display = mode === 'existing' ? 'grid' : 'none';
    document.getElementById('newClientBox').style.display = mode === 'new' ? 'grid' : 'none';
}

function riskLevelFromSelection(selected) {
    if (selected.includes('high')) return 'Alto';
    if (selected.includes('moderate')) return 'Medio';
    if (selected.length > 0) return 'Bajo';
    return 'Bajo';
}

function updateRiskLevel() {
    const selected = Array.from(document.querySelectorAll('input[name="portfolio_risks[]"]:checked')).map(i => i.value);
    riskLevelBadge.textContent = riskLevelFromSelection(selected);
}

function refreshProjectTitles() {
    Array.from(projectContainer.querySelectorAll('.project-row')).forEach((row, idx) => {
        row.dataset.index = idx;
        const title = row.querySelector('.row-header strong');
        if (title) title.textContent = `Proyecto ${idx + 1}`;
        row.querySelectorAll('input, select').forEach(input => {
            input.name = input.name.replace(/projects\[[^\]]+\]/, `projects[${idx}]`);
        });
    });
}

function addProjectRow() {
    const nextIndex = projectContainer.querySelectorAll('.project-row').length;
    const html = projectTemplate.replace(/__INDEX__/g, nextIndex).replace(/__NUMBER__/g, nextIndex + 1);
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    const row = wrapper.firstElementChild;
    projectContainer.appendChild(row);
    attachRowEvents(row);
    refreshProjectTitles();
}

function attachRowEvents(row) {
    const removeBtn = row.querySelector('.remove-project');
    removeBtn?.addEventListener('click', () => {
        if (projectContainer.querySelectorAll('.project-row').length === 1) return;
        row.remove();
        refreshProjectTitles();
    });
}

prevBtn?.addEventListener('click', () => updateStep(-1));
nextBtn?.addEventListener('click', () => updateStep(1));
document.querySelectorAll('input[name="client_mode"]').forEach(r => r.addEventListener('change', toggleClientMode));
document.querySelectorAll('input[name="portfolio_risks[]"]').forEach(r => r.addEventListener('change', updateRiskLevel));
document.getElementById('addProject')?.addEventListener('click', addProjectRow);
Array.from(projectContainer.querySelectorAll('.project-row')).forEach(attachRowEvents);

toggleClientMode();
updateRiskLevel();
</script>

<style>
.portfolio-card { padding: 20px; }
.portfolio-toolbar { border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 12px; display:flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; }
.portfolio-title { margin: 0; }
.eyebrow { margin: 0; text-transform: uppercase; letter-spacing: 0.02em; color: var(--muted); font-size: 12px; }
.wizard { display: flex; flex-direction: column; gap: 12px; }
.wizard-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px; }
.wizard-step { padding: 10px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-1); color: var(--muted); font-weight: 600; text-align: center; }
.wizard-step.active { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 10%, var(--surface-1)); color: var(--primary); }
.wizard-panel { border: 1px solid var(--border); padding: 16px; border-radius: 12px; background: var(--surface); display: flex; flex-direction: column; gap: 10px; }
.wizard-panel.hidden { display: none; }
.grid { display: grid; gap: 10px; }
.two-cols { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
.three-cols { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
label { display: flex; flex-direction: column; gap: 6px; font-weight: 600; color: var(--text); }
input, select, textarea { width: 100%; }
.tip-box { padding: 10px; border-radius: 10px; background: var(--surface-2); border: 1px dashed var(--border); }
.wizard-footer { display: flex; align-items: center; gap: 8px; }
.hidden { display: none; }
.pill.option { display: inline-flex; gap: 6px; align-items: center; padding: 6px 10px; border: 1px solid var(--border); border-radius: 999px; }
.project-row { border: 1px solid var(--border); border-radius: 10px; padding: 12px; background: var(--surface-1); }
.project-grid { display: flex; flex-direction: column; gap: 12px; }
.row-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.icon-button { background: transparent; border: none; cursor: pointer; font-size: 16px; color: var(--muted); }
.icon-button:hover { color: var(--primary); }
.risk-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.risk-summary { border: 1px solid var(--border); border-radius: 10px; padding: 12px; background: var(--surface-1); max-width: 280px; }
.risk-level { font-weight: 800; font-size: 20px; color: var(--secondary); }
.panel-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.badge.neutral { background: var(--surface-1); border: 1px solid var(--border); padding: 6px 10px; border-radius: 999px; color: var(--muted); font-weight: 700; }
.radio-group { display: flex; gap: 10px; align-items: center; }
.radio { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
.subtext { color: var(--muted); font-weight: 500; font-size: 12px; }
.small { font-size: 12px; }
.full { grid-column: 1 / -1; }
.alert.error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 10px; }
</style>
