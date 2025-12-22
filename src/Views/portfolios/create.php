<?php
$alertWindow = (int) ($operationalRules['alerts']['portfolio_days_before_end'] ?? 15);
$warningRatio = (float) ($operationalRules['portfolio_limits']['warning_ratio'] ?? 0.85);
?>

<div class="card portfolio-card">
    <div class="toolbar portfolio-toolbar">
        <div>
            <p class="eyebrow">Crear ≠ Analizar</p>
            <h2 class="portfolio-title">Nuevo portafolio guiado</h2>
            <p class="muted">Un cliente puede tener múltiples portafolios. Esta experiencia es solo para creación paso a paso.</p>
        </div>
        <div class="wizard-actions">
            <a class="button ghost" href="<?= $basePath ?>/portfolio">Volver a análisis</a>
            <span class="badge neutral">4 pasos</span>
        </div>
    </div>

    <form class="wizard" method="POST" action="<?= $basePath ?>/portfolio" enctype="multipart/form-data" id="portfolioWizard">
        <div class="wizard-steps" aria-label="Pasos del asistente">
            <div class="wizard-step active" data-step="1">1. Datos generales</div>
            <div class="wizard-step" data-step="2">2. Alcance</div>
            <div class="wizard-step" data-step="3">3. Proyectos incluidos</div>
            <div class="wizard-step" data-step="4">4. Reglas y alertas</div>
        </div>

        <div class="wizard-body">
            <section class="wizard-panel" data-step-panel="1">
                <h3>Datos generales</h3>
                <p class="muted">Identifica al cliente y nombra el portafolio. No se crean clientes aquí, solo se asocian.</p>
                <div class="grid two-cols">
                    <label>Cliente
                        <select name="client_id" required>
                            <option value="">Selecciona un cliente</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Nombre del portafolio
                        <input name="name" required maxlength="150" placeholder="Ej. Transformación Digital 2024">
                    </label>
                    <label>Descripción ejecutiva (opcional)
                        <textarea name="rules_notes" rows="2" placeholder="Contexto general, sponsors, objetivos clave"></textarea>
                    </label>
                </div>
            </section>

            <section class="wizard-panel hidden" data-step-panel="2">
                <h3>Alcance</h3>
                <p class="muted">Define fechas, horas y costos esperados del portafolio.</p>
                <div class="grid three-cols">
                    <label>Inicio
                        <input type="date" name="start_date">
                    </label>
                    <label>Fin
                        <input type="date" name="end_date">
                        <small class="subtext">Se alertará <?= $alertWindow ?> días antes.</small>
                    </label>
                    <label>Adjunto de referencia
                        <input type="file" name="attachment" accept="application/pdf,image/png,image/jpeg">
                        <small class="subtext">SOW o alcance firmado.</small>
                    </label>
                    <label>Límite de horas
                        <input type="number" step="0.1" name="hours_limit" placeholder="Ej. 1200">
                    </label>
                    <label>Presupuesto límite
                        <input type="number" step="0.01" name="budget_limit" placeholder="Ej. 250000">
                    </label>
                    <div class="tip-box">
                        <strong>Umbral preventivo</strong>
                        <p class="muted">Se alerta al alcanzar el <?= (int) ($warningRatio * 100) ?>% de horas o costos.</p>
                    </div>
                </div>
            </section>

            <section class="wizard-panel hidden" data-step-panel="3">
                <h3>Proyectos incluidos</h3>
                <p class="muted">Selecciona los proyectos que pertenecen al portafolio. Puedes agregar o quitar después.</p>
                <?php foreach ($clients as $client): ?>
                    <?php $projects = $projectsByClient[$client['id']] ?? []; ?>
                    <details class="project-picker" open>
                        <summary>
                            <strong><?= htmlspecialchars($client['name']) ?></strong>
                            <span class="muted">Proyectos activos: <?= count($projects) ?></span>
                        </summary>
                        <?php if (empty($projects)): ?>
                            <p class="muted">Sin proyectos visibles para este cliente.</p>
                        <?php else: ?>
                            <div class="pillset">
                                <?php foreach ($projects as $project): ?>
                                    <label class="pill option">
                                    <input type="checkbox" name="projects_included[]" value="<?= (int) $project['id'] ?>">
                                        <?= htmlspecialchars($project['name']) ?> (<?= $project['progress'] ?>%)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            </section>

            <section class="wizard-panel hidden" data-step-panel="4">
                <h3>Reglas y alertas</h3>
                <p class="muted">Define cómo se medirá y avisará el desempeño. Texto claro, sin depender de colores.</p>
                <div class="grid two-cols">
                    <label>Política de alertas
                        <textarea name="alerting_policy" rows="3" placeholder="Ej. Escalar a PMO al superar 90% de horas, enviar briefing semanal."></textarea>
                    </label>
                    <div class="checklist">
                        <strong>Recordatorios</strong>
                        <ul>
                            <li>Establece responsables para responder alertas.</li>
                            <li>Define qué tab usarás para análisis (Resumen, Costos, Reportes).</li>
                            <li>Guarda evidencia en el adjunto o repositorio habitual.</li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>

        <div class="wizard-footer">
            <button type="button" class="button ghost" id="prevStep" disabled>Anterior</button>
            <div class="spacer"></div>
            <button type="button" class="button secondary" id="nextStep">Siguiente</button>
            <button type="submit" class="button primary hidden" id="submitWizard">Crear portafolio</button>
        </div>
    </form>
</div>

<script>
const steps = Array.from(document.querySelectorAll('[data-step]'));
const panels = Array.from(document.querySelectorAll('[data-step-panel]'));
const prevBtn = document.getElementById('prevStep');
const nextBtn = document.getElementById('nextStep');
const submitBtn = document.getElementById('submitWizard');
let currentStep = 1;

function updateStep(direction) {
    currentStep = Math.min(4, Math.max(1, currentStep + direction));
    steps.forEach(step => step.classList.toggle('active', Number(step.dataset.step) === currentStep));
    panels.forEach(panel => panel.classList.toggle('hidden', Number(panel.dataset.stepPanel) !== currentStep));
    prevBtn.disabled = currentStep === 1;
    nextBtn.classList.toggle('hidden', currentStep === 4);
    submitBtn.classList.toggle('hidden', currentStep !== 4);
}

prevBtn?.addEventListener('click', () => updateStep(-1));
nextBtn?.addEventListener('click', () => updateStep(1));
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
.pill.option { display: inline-flex; gap: 6px; align-items: center; }
.project-picker { border: 1px solid var(--border); border-radius: 10px; padding: 10px; background: var(--surface-1); }
.project-picker summary { cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
.checklist ul { margin: 8px 0 0; padding-left: 18px; color: var(--muted); }
.wizard-actions { display: flex; align-items: center; gap: 8px; }
</style>
