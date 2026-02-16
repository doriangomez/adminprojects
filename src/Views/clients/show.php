<div class="toolbar">
    <div>
        <a href="/clients" class="btn ghost">← Volver</a>
        <h3 style="margin:8px 0 0 0;">Detalle del cliente</h3>
        <p style="margin:4px 0 0 0; color: var(--text-secondary);">Gobierno y contexto de la relación. La información contractual permanece en los proyectos.</p>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <?php if($canManage): ?>
            <a class="btn secondary" href="/clients/<?= (int) $client['id'] ?>/edit">Editar</a>
        <?php endif; ?>
        <?php if($auth->canDeleteClients()): ?>
            <button type="button" class="btn ghost danger" data-open-action="delete">
                Eliminar cliente
            </button>
        <?php endif; ?>
        <?php if($canInactivate): ?>
            <button type="button" class="btn ghost warning" data-open-action="inactivate">
                Inactivar cliente
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="section-grid twothirds">
    <div class="card">
        <div class="toolbar">
            <div>
                <p class="badge neutral" style="margin:0;">Ficha estratégica</p>
                <h4 style="margin:4px 0 0 0;">Relación con <?= htmlspecialchars($client['name']) ?></h4>
            </div>
            <?php if(!empty($client['logo_path'])): ?>
                <img src="<?= $basePath . $client['logo_path'] ?>" alt="Logo de <?= htmlspecialchars($client['name']) ?>" style="width:64px; height:64px; object-fit:contain; border:1px solid var(--border); border-radius:12px; background:var(--surface);">
            <?php endif; ?>
        </div>
        <div class="grid tight" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <div>
                <p class="muted">Sector</p>
                <strong><?= htmlspecialchars($client['sector_label'] ?? $client['sector_code']) ?></strong>
            </div>
            <div>
                <p class="muted">Categoría</p>
                <strong><?= htmlspecialchars($client['category_label'] ?? $client['category_code']) ?></strong>
            </div>
            <div>
                <p class="muted">Prioridad</p>
                <span class="pill <?= htmlspecialchars($client['priority']) ?>"><?= htmlspecialchars($client['priority_label'] ?? ucfirst($client['priority'])) ?></span>
            </div>
            <div>
                <p class="muted">Estado</p>
                <span class="badge neutral"><?= htmlspecialchars($client['status_label'] ?? $client['status_code']) ?></span>
            </div>
            <div>
                <p class="muted">PM a cargo</p>
                <strong><?= htmlspecialchars($client['pm_name'] ?? 'Sin asignar') ?></strong><br>
                <small style="color: var(--text-secondary);">Email: <?= htmlspecialchars($client['pm_email'] ?? '-') ?></small>
            </div>
            <div>
                <p class="muted">Riesgo</p>
                <strong><?= htmlspecialchars($client['risk_label'] ?? ($client['risk_code'] ?? 'Sin definir')) ?></strong>
            </div>
            <div>
                <p class="muted">Etiquetas</p>
                <strong><?= htmlspecialchars($client['tags'] ?? 'Sin etiquetas') ?></strong>
            </div>
            <div>
                <p class="muted">Área</p>
                <strong><?= htmlspecialchars($client['area_label'] ?? ($client['area_code'] ?? 'No registrada')) ?></strong>
            </div>
        </div>
        <div style="margin-top:12px;">
            <p class="muted" style="margin:0 0 4px 0;">Contexto operativo</p>
            <p style="margin:0; line-height:1.5;"><?= nl2br(htmlspecialchars($client['operational_context'] ?? 'Sin información operativa')) ?></p>
        </div>
    </div>

    <div class="card">
        <div class="toolbar">
            <div>
                <p class="badge neutral" style="margin:0;">Feedback continuo</p>
                <h4 style="margin:4px 0 0 0;">Percepción y señales</h4>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <div class="kpi">
                    <div class="kpi-body">
                        <span class="label">Satisfacción</span>
                        <span class="value"><?= $client['satisfaction'] !== null ? (int) $client['satisfaction'] : '-' ?></span>
                    </div>
                </div>
                <div class="kpi">
                    <div class="kpi-body">
                        <span class="label">NPS</span>
                        <span class="value"><?= $client['nps'] !== null ? (int) $client['nps'] : '-' ?></span>
                    </div>
                </div>
            </div>
        </div>
        <p style="margin:0 0 8px 0;">Observaciones actuales</p>
        <p style="margin:0; line-height:1.5;"><?= nl2br(htmlspecialchars($client['feedback_notes'] ?? 'Sin observaciones registradas')) ?></p>
        <hr style="margin:16px 0; border:1px solid var(--border);">
        <p style="margin:0 0 8px 0;">Historial</p>
        <p style="margin:0; line-height:1.5;"><?= nl2br(htmlspecialchars($client['feedback_history'] ?? 'Aún no hay historial documentado')) ?></p>
    </div>
</div>

<div class="card">
    <div class="toolbar">
        <div>
            <p class="badge neutral" style="margin:0;">Proyectos vinculados</p>
            <h4 style="margin:4px 0 0 0;">Estado general</h4>
        </div>
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:10px; align-items:center;">
            <div class="kpi">
                <div class="kpi-body">
                    <span class="label">Proyectos</span>
                    <span class="value"><?= (int) $snapshot['total'] ?></span>
                </div>
            </div>
            <div class="kpi">
                <div class="kpi-body">
                    <span class="label">En riesgo / críticos</span>
                    <span class="value"><?= (int) $snapshot['at_risk'] ?></span>
                </div>
            </div>
            <div class="kpi">
                <div class="kpi-body">
                    <span class="label">Cerrados</span>
                    <span class="value"><?= (int) $snapshot['closed'] ?></span>
                </div>
            </div>
            <div class="kpi">
                <div class="kpi-body">
                    <span class="label">Avance promedio</span>
                    <span class="value"><?= number_format((float) $snapshot['avg_progress'], 1) ?>%</span>
                </div>
            </div>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Proyecto</th>
                <th>Estado</th>
                <th>Salud</th>
                <th>Prioridad</th>
                <th>Avance</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($projects as $project): ?>
                <tr>
                    <td><?= htmlspecialchars($project['name']) ?></td>
                    <td><?= htmlspecialchars($project['status_label'] ?? $project['status']) ?></td>
                    <td><span class="badge <?= $project['health'] === 'on_track' ? 'success' : ($project['health'] === 'at_risk' ? 'warning' : 'danger') ?>"><?= htmlspecialchars($project['health_label'] ?? $project['health']) ?></span></td>
                    <td><span class="pill <?= htmlspecialchars($project['priority']) ?>"><?= htmlspecialchars($project['priority_label'] ?? $project['priority']) ?></span></td>
                    <td><?= number_format((float) $project['progress'], 1) ?>%</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if($auth->canDeleteClients()): ?>
    <div id="delete-modal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:color-mix(in srgb, var(--text-primary) 45%, var(--background)); align-items:center; justify-content:center; padding:16px;">
        <div class="card" style="max-width:520px; width:100%; border:1px solid color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); box-shadow:0 20px 40px color-mix(in srgb, var(--text-primary) 18%, var(--background));">
            <div class="toolbar">
                <div style="display:flex; gap:10px; align-items:flex-start;">
                    <span aria-hidden="true" style="width:36px; height:36px; border-radius:12px; background:color-mix(in srgb, var(--danger) 12%, var(--surface) 88%); color:var(--danger); border:1px solid color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); display:inline-flex; align-items:center; justify-content:center; font-weight:800;">!</span>
                    <div>
                        <p class="badge danger" style="margin:0;" data-modal-context>Acción crítica</p>
                        <h4 style="margin:4px 0 0 0;" data-modal-title>Eliminar cliente</h4>
                        <p style="margin:4px 0 0 0; color:var(--text-secondary);" data-modal-subtitle>Esta acción es irreversible y eliminará en cascada proyectos, asignaciones de talento, timesheets, costos y adjuntos asociados.</p>
                    </div>
                </div>
                <button type="button" class="btn ghost" data-close-delete aria-label="Cerrar" style="color:var(--text-secondary);">✕</button>
            </div>
            <form method="POST" action="/clients/delete" class="grid" style="gap:12px;" id="delete-form">
                <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
                <input type="hidden" name="math_operand1" id="math_operand1" value="<?= $mathOperand1 ?>">
                <input type="hidden" name="math_operand2" id="math_operand2" value="<?= $mathOperand2 ?>">
                <input type="hidden" name="math_operator" id="math_operator" value="<?= $mathOperator ?>">
                <input type="hidden" name="force_delete" id="force_delete" value="1">
                <div id="dependency-notice" style="display: none; padding: 10px 12px; border:1px solid color-mix(in srgb, var(--warning) 40%, var(--surface) 60%); background:color-mix(in srgb, var(--warning) 12%, var(--surface) 88%); border-radius:10px; color:var(--warning); font-weight:600;">El cliente tiene dependencias activas. La eliminación permanente las borrará en cascada.</div>
                <div>
                    <p style="margin:0 0 4px 0; color:var(--text-secondary); font-weight:600;">Confirmación obligatoria</p>
                    <p style="margin:0 0 8px 0; color:var(--text-secondary);">Resuelve la siguiente operación para confirmar. Solo los administradores pueden ejecutar esta acción.</p>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="flex:1;">
                            <div style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:color-mix(in srgb, var(--surface) 92%, var(--background) 8%); font-weight:700;">
                                <?= $mathOperand1 ?> <?= $mathOperator ?> <?= $mathOperand2 ?> =
                            </div>
                        </div>
                        <input type="number" name="math_result" id="math_result" inputmode="numeric" aria-label="Resultado de la operación" placeholder="Resultado" style="width:120px; padding:10px 12px; border-radius:10px; border:1px solid var(--border);">
                    </div>
                </div>
                <div id="action-feedback" style="display:none; padding:10px 12px; border-radius:10px; border:1px solid color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); background:color-mix(in srgb, var(--danger) 12%, var(--surface) 88%); color:var(--danger); font-weight:600;"></div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" class="btn ghost" data-close-delete>Cancelar</button>
                    <button type="submit" class="btn ghost danger" id="confirm-delete-btn" disabled>Eliminar permanentemente</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const modal = document.getElementById('delete-modal');
            const openButtons = document.querySelectorAll('[data-open-action]');
            const closeButtons = document.querySelectorAll('[data-close-delete]');
            const resultInput = document.getElementById('math_result');
            const confirmButton = document.getElementById('confirm-delete-btn');
            const form = document.getElementById('delete-form');
            const operand1 = Number(document.getElementById('math_operand1')?.value || 0);
            const operand2 = Number(document.getElementById('math_operand2')?.value || 0);
            const operator = (document.getElementById('math_operator')?.value || '').trim();
            const expected = operator === '+' ? operand1 + operand2 : operand1 - operand2;
            const dependencyNotice = document.getElementById('dependency-notice');
            const actionFeedback = document.getElementById('action-feedback');
            const modalTitle = document.querySelector('[data-modal-title]');
            const modalSubtitle = document.querySelector('[data-modal-subtitle]');
            const modalContext = document.querySelector('[data-modal-context]');
            const clientId = <?= (int) $client['id'] ?>;
            const hasDependencies = <?= $dependencies['has_dependencies'] ? 'true' : 'false' ?>;
            const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
            const forceDeleteInput = document.getElementById('force_delete');
            const dependencyMessage = 'El cliente tiene dependencias activas. La eliminación permanente borrará todo lo relacionado de forma definitiva.';

                const actions = {
                    delete: {
                        title: 'Eliminar cliente',
                        subtitle: 'Esta acción elimina definitivamente la ficha. Los administradores pueden forzar la eliminación incluso con dependencias activas.',
                        context: 'Acción crítica',
                        actionUrl: '/clients/delete',
                    buttonLabel: 'Eliminar permanentemente',
                    buttonStyle: 'color:var(--danger); border-color:color-mix(in srgb, var(--danger) 40%, var(--surface) 60%); background:color-mix(in srgb, var(--danger) 12%, var(--surface) 88%);'
                },
                inactivate: {
                    title: 'Inactivar cliente',
                    subtitle: 'Se deshabilita el cliente y se conserva la información asociada.',
                    context: 'Acción segura',
                    actionUrl: `/clients/${clientId}/inactivate`,
                    buttonLabel: 'Inactivar cliente',
                    buttonStyle: 'color:var(--warning); border-color:color-mix(in srgb, var(--warning) 40%, var(--surface) 60%); background:color-mix(in srgb, var(--warning) 12%, var(--surface) 88%);'
                }
            };

            let currentAction = hasDependencies && !isAdmin ? 'inactivate' : 'delete';

            const syncState = () => {
                if (!resultInput || !confirmButton) return;
                const current = Number(resultInput.value.trim());
                const isValid = !Number.isNaN(current) && current === expected;
                confirmButton.disabled = !isValid;
            };

            const setAction = (action) => {
                currentAction = action;
                const config = actions[action];
                if (!config) return;

                if (modalTitle) modalTitle.textContent = config.title;
                if (modalSubtitle) modalSubtitle.textContent = config.subtitle;
                if (modalContext) modalContext.textContent = config.context;
                if (confirmButton) {
                    confirmButton.textContent = config.buttonLabel;
                    confirmButton.style.cssText = `${confirmButton.getAttribute('style') || ''}; ${config.buttonStyle}`;
                }
                if (form) {
                    form.setAttribute('action', config.actionUrl);
                }

                if (dependencyNotice) {
                    dependencyNotice.textContent = dependencyMessage;
                    dependencyNotice.style.display = hasDependencies || action === 'inactivate' ? 'block' : 'none';
                }

                if (forceDeleteInput) {
                    forceDeleteInput.value = action === 'delete' ? '1' : '0';
                }
            };

            openButtons.forEach((btn) => btn.addEventListener('click', (event) => {
                if (!modal) return;
                const action = event.currentTarget?.getAttribute('data-open-action') || currentAction;
                setAction(action);
                modal.style.display = 'flex';
                if (resultInput) {
                    resultInput.value = '';
                }
                actionFeedback.style.display = 'none';
                actionFeedback.textContent = '';
                resultInput?.focus();
                syncState();
            }));

            closeButtons.forEach((btn) => btn.addEventListener('click', () => {
                if (modal) {
                    modal.style.display = 'none';
                }
            }));

            resultInput?.addEventListener('input', syncState);

            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!form) return;
                actionFeedback.style.display = 'none';
                actionFeedback.textContent = '';

                if (currentAction === 'delete' && hasDependencies && !isAdmin) {
                    actionFeedback.textContent = dependencyMessage;
                    actionFeedback.style.display = 'block';
                    setAction('inactivate');
                    return;
                }

                const payload = new FormData(form);
                let responseData;

                try {
                    const response = await fetch(form.getAttribute('action') || '', {
                        method: 'POST',
                        body: payload,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    responseData = await response.json();
                } catch (error) {
                    actionFeedback.textContent = 'No se pudo completar la acción. Intenta nuevamente.';
                    actionFeedback.style.display = 'block';
                    return;
                }

                if (responseData?.success) {
                    alert(responseData.message || 'Acción completada.');
                    window.location.href = '/clients';
                    return;
                }

                const message = responseData?.message || 'No se pudo completar la acción.';
                actionFeedback.textContent = message;
                actionFeedback.style.display = 'block';

                if (responseData?.can_inactivate) {
                    setAction('inactivate');
                }
            });

            setAction(currentAction);
        })();
    </script>
<?php endif; ?>
