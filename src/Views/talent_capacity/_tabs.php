<?php
$basePath = $basePath ?? '';
$activeTab = $activeTab ?? 'carga';
?>
<nav class="capacity-tabs">
    <a class="capacity-tab <?= $activeTab === 'carga' ? 'active' : '' ?>" href="<?= htmlspecialchars($basePath) ?>/talent-capacity">
        <span class="capacity-tab__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20h16"/><rect x="6" y="12" width="3" height="6" rx="1"/><rect x="11" y="8" width="3" height="10" rx="1"/><rect x="16" y="5" width="3" height="13" rx="1"/></svg></span>
        <span>Carga y Capacidad</span>
    </a>
    <a class="capacity-tab <?= $activeTab === 'simulacion' ? 'active' : '' ?>" href="<?= htmlspecialchars($basePath) ?>/talent-capacity/simulation">
        <span class="capacity-tab__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2v4"/><path d="M12 18v4"/><path d="m4.93 4.93 2.83 2.83"/><path d="m16.24 16.24 2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="m4.93 19.07 2.83-2.83"/><path d="m16.24 7.76 2.83-2.83"/><circle cx="12" cy="12" r="4"/><path d="M12 8v8"/><path d="m8 12 4-4 4 4"/></svg></span>
        <span>Simulación de Capacidad</span>
    </a>
</nav>
<style>
.capacity-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 14px; }
.capacity-tab { padding: 10px 16px; border-radius: 999px; border: 1px solid color-mix(in srgb, var(--border) 84%, transparent); text-decoration: none; color: var(--text-primary); font-weight: 600; font-size: 14px; background: linear-gradient(160deg, color-mix(in srgb, var(--surface) 90%, var(--background) 10%), color-mix(in srgb, var(--surface) 82%, var(--background) 18%)); display: inline-flex; align-items: center; gap: 8px; transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease; }
.capacity-tab:hover { transform: translateY(-1px); border-color: color-mix(in srgb, var(--primary) 42%, var(--border)); box-shadow: 0 8px 16px color-mix(in srgb, var(--primary) 16%, transparent); }
.capacity-tab__icon { width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; color: var(--primary); background: color-mix(in srgb, var(--primary) 15%, var(--surface)); }
.capacity-tab__icon svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; }
.capacity-tab.active { background: var(--primary); color: var(--text-primary); border-color: var(--primary); box-shadow: 0 8px 18px color-mix(in srgb, var(--primary) 24%, transparent); }
.capacity-tab.active .capacity-tab__icon { color: var(--text-primary); background: color-mix(in srgb, var(--surface) 25%, transparent); }
</style>
