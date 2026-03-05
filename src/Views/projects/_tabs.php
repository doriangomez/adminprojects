<?php
$basePath = $basePath ?? '';
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
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    ],
    'documents' => [
        'label' => 'Documentos',
        'href' => $documentsHref,
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
    ],
    'seguimiento' => [
        'label' => 'Notas / Seguimiento',
        'href' => $basePath . '/projects/' . $projectId . '?view=seguimiento',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
    ],
    'bloqueos' => [
        'label' => 'Bloqueos',
        'href' => $basePath . '/projects/' . $projectId . '?view=bloqueos',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',
    ],
    'requisitos' => [
        'label' => 'Requisitos',
        'href' => $basePath . '/projects/' . $projectId . '/requirements',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
    ],
    'tareas' => [
        'label' => 'Tareas',
        'href' => $basePath . '/projects/' . $projectId . '/tasks',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
    ],
    'talento' => [
        'label' => 'Talento',
        'href' => $basePath . '/projects/' . $projectId . '/talent',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    ],
    'costos' => [
        'label' => 'Costos',
        'href' => $basePath . '/projects/' . $projectId . '/costs',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    ],
    'facturacion' => [
        'label' => 'Facturación',
        'href' => $basePath . '/projects/' . $projectId . '/billing',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
    ],
];
if ($projectType === 'outsourcing') {
    $tabs['outsourcing'] = [
        'label' => 'Outsourcing',
        'href' => $basePath . '/projects/' . $projectId . '/outsourcing',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
    ];
}
?>

<nav class="project-tabs">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="project-tab <?= $activeTab === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($tab['href']) ?>">
            <span class="project-tab__icon" aria-hidden="true"><?= $tab['icon'] ?? '' ?></span>
            <span><?= htmlspecialchars($tab['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<style>
    .project-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-bottom: 18px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border);
    }
    .project-tab {
        padding: 6px 12px;
        border-radius: 7px;
        border: 1px solid transparent;
        text-decoration: none;
        color: var(--text-secondary);
        font-weight: 500;
        font-size: 12.5px;
        background: transparent;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s, color 0.15s, border-color 0.15s;
        white-space: nowrap;
    }
    .project-tab:hover {
        background: color-mix(in srgb, var(--surface) 9%, transparent);
        color: var(--text-primary);
        border-color: color-mix(in srgb, var(--border) 60%, transparent);
    }
    .project-tab__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .project-tab__icon svg {
        width: 13px;
        height: 13px;
        stroke: currentColor;
        fill: none;
    }
    .project-tab.active {
        background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 72%, var(--accent)));
        color: #ffffff;
        border-color: transparent;
        font-weight: 600;
        box-shadow: 0 2px 8px color-mix(in srgb, var(--primary) 28%, transparent);
    }
    .project-tab.active .project-tab__icon svg { stroke: #ffffff; }
</style>
