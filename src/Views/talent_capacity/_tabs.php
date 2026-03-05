<?php
$activeTab = $activeTab ?? 'capacity';
$basePath = $basePath ?? '';
?>
<nav class="capacity-tabs" role="tablist">
    <a href="<?= $basePath ?>/talent-capacity"
       class="capacity-tab <?= $activeTab === 'capacity' ? 'active' : '' ?>"
       role="tab"
       aria-selected="<?= $activeTab === 'capacity' ? 'true' : 'false' ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg>
        <span>Gestión de Carga y Capacidad</span>
    </a>
    <a href="<?= $basePath ?>/talent-capacity/simulation"
       class="capacity-tab <?= $activeTab === 'simulation' ? 'active' : '' ?>"
       role="tab"
       aria-selected="<?= $activeTab === 'simulation' ? 'true' : 'false' ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/><path d="M2 12h4"/><path d="M8 6h4"/><path d="M14 2h4"/></svg>
        <span>Simulación de Capacidad</span>
    </a>
</nav>
<style>
.capacity-tabs {
    display: flex;
    gap: 6px;
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 14px;
    padding: 6px;
}
.capacity-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: .92rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-decoration: none;
    border: 1px solid transparent;
    transition: all .2s ease;
}
.capacity-tab:hover {
    color: var(--text-primary);
    background: color-mix(in srgb, var(--primary) 8%, var(--background));
}
.capacity-tab.active {
    color: var(--text-primary);
    background: color-mix(in srgb, var(--primary) 12%, var(--background));
    border-color: color-mix(in srgb, var(--primary) 30%, var(--border));
    font-weight: 700;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 14%, transparent);
}
.capacity-tab svg { flex-shrink: 0; }
@media (max-width: 640px) {
    .capacity-tabs { flex-direction: column; }
    .capacity-tab { justify-content: center; }
}
</style>
