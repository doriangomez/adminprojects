<div class="toolbar">
    <div>
        <a href="/project/public/clients" class="btn ghost">← Volver</a>
        <h3 style="margin:8px 0 0 0;">Detalle del cliente</h3>
        <p style="margin:4px 0 0 0; color: var(--muted);">Gobierno y contexto de la relación. La información contractual permanece en los proyectos.</p>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <?php if($canManage): ?>
            <a class="btn secondary" href="/project/public/clients/<?= (int) $client['id'] ?>/edit">Editar</a>
        <?php endif; ?>
        <?php if($canDelete): ?>
            <button type="button" class="btn ghost" style="color:#b91c1c; border-color: #fecaca; background: #fef2f2;" data-open-delete>
                Eliminar cliente
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
                <img src="<?= $basePath . $client['logo_path'] ?>" alt="Logo de <?= htmlspecialchars($client['name']) ?>" style="width:64px; height:64px; object-fit:contain; border:1px solid var(--border); border-radius:12px; background:#fff;">
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
                <small style="color: var(--muted);">Email: <?= htmlspecialchars($client['pm_email'] ?? '-') ?></small>
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

<?php if($canDelete): ?>
    <div id="delete-modal" class="modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(17,24,39,0.45); align-items:center; justify-content:center; padding:16px;">
        <div class="card" style="max-width:520px; width:100%; border:1px solid #fecaca; box-shadow:0 20px 40px rgba(0,0,0,0.18);">
            <div class="toolbar">
                <div>
                    <p class="badge danger" style="margin:0;">Acción crítica</p>
                    <h4 style="margin:4px 0 0 0;">Eliminar cliente</h4>
                    <p style="margin:4px 0 0 0; color:var(--muted);">Esta acción es irreversible y eliminará proyectos, portafolios y registros asociados.</p>
                </div>
                <button type="button" class="btn ghost" data-close-delete aria-label="Cerrar" style="color:var(--muted);">✕</button>
            </div>
            <form method="POST" action="/project/public/clients/delete" class="grid" style="gap:12px;">
                <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
                <div>
                    <p style="margin:0 0 4px 0; color:var(--text); font-weight:600;">Confirmación obligatoria</p>
                    <p style="margin:0 0 8px 0; color:var(--muted);">Escriba <strong><?= htmlspecialchars($client['name']) ?></strong> para confirmar la eliminación.</p>
                    <input type="text" name="confirmation_name" id="confirmation_name" data-expected="<?= htmlspecialchars($client['name']) ?>" placeholder="<?= htmlspecialchars($client['name']) ?>" style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--border);">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" class="btn ghost" data-close-delete>Cancelar</button>
                    <button type="submit" class="btn ghost" id="confirm-delete-btn" style="color:#b91c1c; border-color:#fecaca; background:#fef2f2;" disabled>Eliminar permanentemente</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const modal = document.getElementById('delete-modal');
            const openBtn = document.querySelector('[data-open-delete]');
            const closeButtons = document.querySelectorAll('[data-close-delete]');
            const confirmationInput = document.getElementById('confirmation_name');
            const confirmButton = document.getElementById('confirm-delete-btn');
            const expected = confirmationInput ? confirmationInput.getAttribute('data-expected') : '';

            const syncState = () => {
                if (!confirmationInput || !confirmButton) return;
                confirmButton.disabled = confirmationInput.value.trim() !== expected;
            };

            openBtn?.addEventListener('click', () => {
                if (!modal) return;
                modal.style.display = 'flex';
                confirmationInput?.focus();
                syncState();
            });

            closeButtons.forEach((btn) => btn.addEventListener('click', () => {
                if (modal) {
                    modal.style.display = 'none';
                }
            }));

            confirmationInput?.addEventListener('input', syncState);
        })();
    </script>
<?php endif; ?>
