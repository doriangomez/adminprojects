<?php
$basePath = $basePath ?? '/project/public';
$project = $project ?? [];
$activeTab = $activeTab ?? 'documents';
$projectId = (int) ($project['id'] ?? 0);
$projectType = (string) ($project['project_type'] ?? '');
$tabs = [
    'documents' => [
        'label' => 'Documentos',
        'href' => $basePath . '/projects/' . $projectId,
    ],
    'tasks' => [
        'label' => 'Tareas',
        'href' => $basePath . '/projects/' . $projectId . '/tasks',
    ],
];
if ($projectType === 'outsourcing') {
    $tabs['outsourcing'] = [
        'label' => 'Outsourcing',
        'href' => $basePath . '/projects/' . $projectId . '/outsourcing',
    ];
}
?>

<nav class="project-tabs">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="project-tab <?= $activeTab === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($tab['href']) ?>">
            <?= htmlspecialchars($tab['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<style>
    .project-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
    .project-tab { padding:8px 14px; border-radius:999px; border:1px solid var(--border); text-decoration:none; color: var(--text-strong); font-weight:700; font-size:13px; background:#f8fafc; }
    .project-tab.active { background: var(--primary); color:#fff; border-color: var(--primary); }
</style>
