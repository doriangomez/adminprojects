<?php
$basePath = $basePath ?? '';
$activeTab = $activeTab ?? 'vista';
$tabs = [
    'vista' => [
        'label' => 'Vista de capacidad',
        'href' => $basePath . '/talent-capacity',
    ],
    'simulacion' => [
        'label' => 'Simulación de capacidad',
        'href' => $basePath . '/talent-capacity/simulation',
    ],
];
?>
<nav class="capacity-tabs">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="capacity-tab <?= $activeTab === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($tab['href']) ?>">
            <?= htmlspecialchars($tab['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<style>
.capacity-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; border-bottom: 1px solid var(--border); padding-bottom: 12px; }
.capacity-tab { padding: 9px 14px; border-radius: 999px; border: 1px solid color-mix(in srgb, var(--border) 84%, transparent); text-decoration: none; color: var(--text-primary); font-weight: 700; font-size: 13px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); transition: all 0.16s ease; }
.capacity-tab:hover { border-color: color-mix(in srgb, var(--primary) 42%, var(--border)); }
.capacity-tab.active { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
</style>
