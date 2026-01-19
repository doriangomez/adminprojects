<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$activeTab = $activeTab ?? 'documents';
$projectId = (int) ($project['id'] ?? 0);
$projectType = (string) ($project['project_type'] ?? '');
$viewParam = $_GET['view'] ?? null;
$summaryHref = $basePath . '/projects/' . $projectId . '?view=resumen';
$documentsHref = $basePath . '/projects/' . $projectId . ($viewParam === 'documentos' ? '?view=documentos' : '?view=documentos');
$tabs = [
    'resumen' => [
        'label' => 'Resumen',
        'href' => $summaryHref,
        'icon' => '📊',
    ],
    'documents' => [
        'label' => 'Documentos',
        'href' => $documentsHref,
        'icon' => '📂',
    ],
    'seguimiento' => [
        'label' => 'Notas / Seguimiento',
        'href' => $basePath . '/projects/' . $projectId . '?view=seguimiento',
        'icon' => '📝',
    ],
    'tareas' => [
        'label' => 'Tareas',
        'href' => $basePath . '/projects/' . $projectId . '/tasks',
        'icon' => '✅',
    ],
    'talento' => [
        'label' => 'Talento',
        'href' => $basePath . '/projects/' . $projectId . '/talent',
        'icon' => '👥',
    ],
    'costos' => [
        'label' => 'Costos',
        'href' => $basePath . '/projects/' . $projectId . '/costs',
        'icon' => '💵',
    ],
];
if ($projectType === 'outsourcing') {
    $tabs['outsourcing'] = [
        'label' => 'Outsourcing',
        'href' => $basePath . '/projects/' . $projectId . '/outsourcing',
        'icon' => '🧩',
    ];
}
?>

<nav class="project-tabs">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="project-tab <?= $activeTab === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($tab['href']) ?>">
            <span class="project-tab__icon" aria-hidden="true"><?= htmlspecialchars($tab['icon'] ?? '•') ?></span>
            <span><?= htmlspecialchars($tab['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<style>
    .project-tabs { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:10px; }
    .project-tab { padding:9px 14px; border-radius:999px; border:1px solid var(--border); text-decoration:none; color: var(--text-strong); font-weight:700; font-size:13px; background:rgba(148, 163, 184, 0.12); display:inline-flex; align-items:center; gap:8px; }
    .project-tab__icon { font-size:14px; }
    .project-tab.active { background: var(--primary); color:#fff; border-color: var(--primary); }
</style>
