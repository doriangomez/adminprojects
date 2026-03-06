<?php $basePath = $basePath ?? ''; ?>
<section class="talent-panel-shell">
    <div class="empty-state-card">
        <div class="empty-icon">
            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--text-secondary)" stroke-width="1.5">
                <path d="M9 11a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 9 11Z"/>
                <path d="M16.5 10a2.5 2.5 0 1 0-2.5-2.5A2.5 2.5 0 0 0 16.5 10Z"/>
                <path d="M3 20a6 6 0 0 1 12 0"/><path d="M13 20a4.5 4.5 0 0 1 8 0"/>
            </svg>
        </div>
        <h3>No tienes un perfil de talento asociado</h3>
        <p class="muted">Para acceder al panel de trabajo, solicita a tu administrador que asocie tu usuario con un perfil de talento.</p>
    </div>
</section>
<style>
.talent-panel-shell { display:flex; justify-content:center; padding:60px 20px; }
.empty-state-card { text-align:center; max-width:440px; padding:40px; border:1px dashed var(--border); border-radius:16px; background: var(--surface); }
.empty-icon { margin-bottom:16px; }
.empty-state-card h3 { margin:0 0 8px; color: var(--text-primary); }
.empty-state-card .muted { color: var(--text-secondary); font-size:14px; line-height:1.5; }
</style>
