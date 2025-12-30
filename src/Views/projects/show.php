<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$signal = $project['signal'] ?? ['label' => 'Verde', 'code' => 'green', 'reasons' => []];
$risks = is_array($project['risks'] ?? null) ? $project['risks'] : [];
$riskScore = (float) ($project['risk_score'] ?? 0);
$riskLevel = $project['risk_level'] ?? 'Sin dato';
$designInputs = is_array($designInputs ?? null) ? $designInputs : [];
$designInputTypes = is_array($designInputTypes ?? null) ? $designInputTypes : [];
$designControls = is_array($designControls ?? null) ? $designControls : [];
$designControlTypes = is_array($designControlTypes ?? null) ? $designControlTypes : [];
$designControlResults = is_array($designControlResults ?? null) ? $designControlResults : [];
$performers = is_array($performers ?? null) ? $performers : [];

$designLabels = [
    'requisitos_funcionales' => 'Requisitos funcionales',
    'requisitos_desempeno' => 'Requisitos de desempeño',
    'requisitos_legales' => 'Requisitos legales',
    'normativa' => 'Normativa',
    'referencias_previas' => 'Referencias previas',
    'input_cliente' => 'Input de cliente',
    'otro' => 'Otro',
    'revision' => 'Revisión',
    'verificacion' => 'Verificación',
    'validacion' => 'Validación',
    'aprobado' => 'Aprobado',
    'observaciones' => 'Con observaciones',
    'rechazado' => 'Rechazado',
];
?>

<section class="card" style="padding:16px; border:1px solid var(--border); border-radius:14px; background: var(--surface); display:flex; flex-direction:column; gap:12px;">
    <header style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <div>
            <p class="eyebrow" style="margin:0; color: var(--muted); font-weight:800; text-transform: uppercase; letter-spacing: 0.05em;">Proyecto</p>
            <h3 style="margin:4px 0; color: var(--text-strong);"><?= htmlspecialchars($project['name'] ?? '') ?></h3>
            <p style="margin:0; color: var(--muted); font-weight:600;">Cliente: <?= htmlspecialchars($project['client_name'] ?? '') ?></p>
        </div>
        <span class="pill soft-<?= htmlspecialchars($signal['code'] ?? 'green') ?>" aria-label="Señal">
            <span aria-hidden="true">●</span>
            <?= htmlspecialchars($signal['label'] ?? 'Verde') ?>
        </span>
    </header>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>PM</strong>
            <div><?= htmlspecialchars($project['pm_name'] ?? 'No asignado') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Estado</strong>
            <div><?= htmlspecialchars($project['status_label'] ?? $project['status'] ?? 'Pendiente') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Riesgo</strong>
            <div><?= htmlspecialchars($project['health_label'] ?? $project['health'] ?? 'Sin dato') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Prioridad</strong>
            <div><?= htmlspecialchars($project['priority_label'] ?? $project['priority'] ?? 'N/A') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Avance</strong>
            <div><?= (float) ($project['progress'] ?? 0) ?>%</div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Tipo</strong>
            <div><?= htmlspecialchars($project['project_type'] ?? 'convencional') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Metodología</strong>
            <div><?= htmlspecialchars($project['methodology'] ?? 'No definida') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Fase</strong>
            <div><?= htmlspecialchars($project['phase'] ?? 'Sin fase') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Riesgos</strong>
            <div><?= htmlspecialchars($risks ? implode(', ', $risks) : 'Sin riesgos asociados') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Score de riesgo</strong>
            <div><?= number_format($riskScore, 1) ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Nivel de riesgo</strong>
            <div><?= htmlspecialchars(ucfirst((string) $riskLevel)) ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Fechas</strong>
            <div><?= htmlspecialchars($project['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($project['end_date'] ?? 'N/A') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Presupuesto</strong>
            <div>$<?= number_format((float) ($project['budget'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div class="info-item" style="border:1px solid var(--border); padding:10px; border-radius:12px; background:#f8fafc;">
            <strong>Costo real</strong>
            <div>$<?= number_format((float) ($project['actual_cost'] ?? 0), 0, ',', '.') ?></div>
        </div>
    </div>
</section>

<section class="card" style="margin-top:16px; padding:16px; border:1px solid var(--border); border-radius:14px; background: var(--surface); display:flex; flex-direction:column; gap:12px;">
    <header style="display:flex; justify-content:space-between; align-items:center;">
        <h4 style="margin:0;">Talento asignado</h4>
        <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/talent">Gestionar talento</a>
    </header>
    <?php if (empty($assignments)): ?>
        <p style="margin:0; color: var(--muted);">Aún no hay asignaciones registradas.</p>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($assignments as $assignment): ?>
                <div style="display:flex; justify-content:space-between; border:1px solid var(--border); padding:10px; border-radius:10px; background:#f8fafc;">
                    <div>
                        <strong><?= htmlspecialchars($assignment['talent_name'] ?? 'Talento') ?></strong>
                        <div style="color:var(--muted); font-weight:600;"><?= htmlspecialchars($assignment['role'] ?? '') ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div><?= htmlspecialchars($assignment['start_date'] ?? 'N/A') ?> → <?= htmlspecialchars($assignment['end_date'] ?? 'N/A') ?></div>
                        <small style="color:var(--muted);">Horas semanales: <?= htmlspecialchars((string) ($assignment['weekly_hours'] ?? '0')) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card" style="margin-top:16px; padding:16px; border:1px solid var(--border); border-radius:14px; background: var(--surface); display:flex; flex-direction:column; gap:12px;">
    <header style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <div>
            <p class="eyebrow" style="margin:0; color: var(--muted); font-weight:800; text-transform: uppercase; letter-spacing: 0.05em;">ISO 9001 · 8.3</p>
            <h4 style="margin:4px 0;">Diseño y Desarrollo</h4>
        </div>
    </header>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
        <div style="border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h5 style="margin:0;">Entradas del diseño</h5>
                <span class="pill" aria-label="Estatus de entradas" style="background: <?= (int) ($project['design_inputs_defined'] ?? 0) === 1 ? '#dcfce7' : '#fee2e2' ?>; color: <?= (int) ($project['design_inputs_defined'] ?? 0) === 1 ? '#166534' : '#991b1b' ?>;">
                    <?= (int) ($project['design_inputs_defined'] ?? 0) === 1 ? 'Completas' : 'Pendiente' ?>
                </span>
            </div>
            <?php if (!empty($designInputError)): ?>
                <div style="color:#b91c1c; font-weight:600;"><?= htmlspecialchars($designInputError) ?></div>
            <?php endif; ?>
            <?php if (empty($designInputs)): ?>
                <p style="margin:0; color: var(--muted);">No hay entradas registradas.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php foreach ($designInputs as $input): ?>
                        <div style="border:1px solid var(--border); padding:10px; border-radius:10px; background:#fff; display:flex; justify-content:space-between; gap:8px; align-items:flex-start;">
                            <div>
                                <strong><?= htmlspecialchars($designLabels[$input['input_type']] ?? $input['input_type']) ?></strong>
                                <div style="color:var(--muted);"><?= nl2br(htmlspecialchars($input['description'] ?? '')) ?></div>
                                <?php if (!empty($input['source'])): ?>
                                    <small style="color:var(--muted); display:block;">Fuente: <?= htmlspecialchars((string) $input['source']) ?></small>
                                <?php endif; ?>
                                <?php if ((int) ($input['resolved_conflict'] ?? 0) === 1): ?>
                                    <small style="color:#15803d; font-weight:700; display:block;">Conflicto resuelto</small>
                                <?php endif; ?>
                                <small style="color:var(--muted); display:block;">Registrado: <?= htmlspecialchars(substr((string) ($input['created_at'] ?? ''), 0, 16)) ?></small>
                            </div>
                            <?php if (!empty($canManage)): ?>
                                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/design-inputs/<?= (int) ($input['id'] ?? 0) ?>/delete" style="margin:0;">
                                    <button type="submit" class="action-btn" style="background:#fee2e2; color:#b91c1c; border:none;">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($canManage)): ?>
                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/design-inputs" style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                    <label>Tipo
                        <select name="input_type" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                            <option value="">Selecciona</option>
                            <?php foreach ($designInputTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($designLabels[$type] ?? $type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Descripción
                        <textarea name="description" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;"></textarea>
                    </label>
                    <label>Fuente
                        <input type="text" name="source" placeholder="Cliente, normativa, referencia" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                    </label>
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="resolved_conflict" value="1"> Conflicto resuelto
                    </label>
                    <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Agregar entrada</button>
                </form>
            <?php endif; ?>
        </div>

        <div style="border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h5 style="margin:0;">Controles del diseño</h5>
                <span class="pill" style="background:#e0f2fe; color:#075985;">Seguimiento</span>
            </div>
            <?php if (!empty($designControlError)): ?>
                <div style="color:#b91c1c; font-weight:600;"><?= htmlspecialchars($designControlError) ?></div>
            <?php endif; ?>
            <?php if (empty($designControls)): ?>
                <p style="margin:0; color: var(--muted);">Aún no hay controles registrados.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php foreach ($designControls as $control): ?>
                        <div style="border:1px solid var(--border); padding:10px; border-radius:10px; background:#fff; display:flex; flex-direction:column; gap:4px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong><?= htmlspecialchars($designLabels[$control['control_type']] ?? $control['control_type']) ?></strong>
                                <span class="pill" style="background: <?= $control['result'] === 'aprobado' ? '#dcfce7' : ($control['result'] === 'rechazado' ? '#fee2e2' : '#e0f2fe') ?>; color: <?= $control['result'] === 'aprobado' ? '#166534' : ($control['result'] === 'rechazado' ? '#991b1b' : '#075985') ?>;">
                                    <?= htmlspecialchars($designLabels[$control['result']] ?? $control['result']) ?>
                                </span>
                            </div>
                            <div style="color:var(--muted);"><?= nl2br(htmlspecialchars($control['description'] ?? '')) ?></div>
                            <?php if (!empty($control['corrective_action'])): ?>
                                <small style="color:#b45309; display:block;">Acción correctiva: <?= htmlspecialchars((string) $control['corrective_action']) ?></small>
                            <?php endif; ?>
                            <small style="color:var(--muted); display:block;">Responsable: <?= htmlspecialchars($control['performer_name'] ?? 'N/D') ?> · <?= htmlspecialchars(substr((string) ($control['performed_at'] ?? ''), 0, 16)) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($canManage)): ?>
                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/design-controls" style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                    <label>Tipo de control
                        <select name="control_type" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                            <option value="">Selecciona</option>
                            <?php foreach ($designControlTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($designLabels[$type] ?? $type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Resultado
                        <select name="result" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                            <option value="">Selecciona</option>
                            <?php foreach ($designControlResults as $result): ?>
                                <option value="<?= htmlspecialchars($result) ?>"><?= htmlspecialchars($designLabels[$result] ?? $result) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Descripción
                        <textarea name="description" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;"></textarea>
                    </label>
                    <label>Acción correctiva (solo si aplica)
                        <textarea name="corrective_action" rows="2" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;"></textarea>
                    </label>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:8px;">
                        <label>Responsable
                            <select name="performed_by" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                                <option value="">Selecciona</option>
                                <?php foreach ($performers as $person): ?>
                                    <option value="<?= (int) ($person['id'] ?? 0) ?>"><?= htmlspecialchars($person['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Fecha
                            <input type="datetime-local" name="performed_at" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
                        </label>
                    </div>
                    <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Registrar control</button>
                </form>
            <?php endif; ?>
        </div>

        <div style="border:1px solid var(--border); border-radius:12px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h5 style="margin:0;">Salidas del diseño</h5>
                <span class="pill" style="background: <?= (int) ($project['design_validation_done'] ?? 0) === 1 ? '#dcfce7' : '#fef9c3' ?>; color: <?= (int) ($project['design_validation_done'] ?? 0) === 1 ? '#166534' : '#92400e' ?>;">
                    <?= (int) ($project['design_validation_done'] ?? 0) === 1 ? 'Aprobadas' : 'Pendiente' ?>
                </span>
            </div>
            <?php if (!empty($designOutputError)): ?>
                <div style="color:#b91c1c; font-weight:600;"><?= htmlspecialchars($designOutputError) ?></div>
            <?php endif; ?>
            <ul style="margin:0; padding-left:16px; color:var(--muted); display:flex; flex-direction:column; gap:4px;">
                <li>Revisión completada: <?= (int) ($project['design_review_done'] ?? 0) === 1 ? 'Sí' : 'No' ?></li>
                <li>Verificación completada: <?= (int) ($project['design_verification_done'] ?? 0) === 1 ? 'Sí' : 'No' ?></li>
                <li>Validación completada: <?= (int) ($project['design_validation_done'] ?? 0) === 1 ? 'Sí' : 'No' ?></li>
            </ul>
            <?php if (!empty($canManage)): ?>
                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/design-outputs" style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="design_review_done" value="1" <?= (int) ($project['design_review_done'] ?? 0) === 1 ? 'checked' : '' ?>> Marcar revisión completada
                    </label>
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="design_verification_done" value="1" <?= (int) ($project['design_verification_done'] ?? 0) === 1 ? 'checked' : '' ?>> Marcar verificación completada
                    </label>
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="design_validation_done" value="1" <?= (int) ($project['design_validation_done'] ?? 0) === 1 ? 'checked' : '' ?>> Marcar validación completada
                    </label>
                    <button type="submit" class="primary-button" style="border:none; cursor:pointer;">Actualizar salidas</button>
                </form>
            <?php endif; ?>
            <?php if (empty($canManage)): ?>
                <p style="margin:0; color: var(--muted);">Solo lectura para tu rol. Solicita a un responsable de gestión las actualizaciones.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
    <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit">Editar</a>
    <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/costs">Costos</a>
    <form action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/close" method="GET" style="margin:0;">
        <button class="action-btn" type="submit">Cerrar proyecto</button>
    </form>
</section>
