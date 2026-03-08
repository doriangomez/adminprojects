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
        'icon' => 'summary',
    ],
    'documents' => [
        'label' => 'Documentos',
        'href' => $documentsHref,
        'icon' => 'documents',
    ],
    'seguimiento' => [
        'label' => 'Notas / Seguimiento',
        'href' => $basePath . '/projects/' . $projectId . '?view=seguimiento',
        'icon' => 'notes',
    ],
    'bloqueos' => [
        'label' => 'Bloqueos',
        'href' => $basePath . '/projects/' . $projectId . '?view=bloqueos',
        'icon' => 'blockers',
    ],
    'requisitos' => [
        'label' => 'Requisitos',
        'href' => $basePath . '/projects/' . $projectId . '/requirements',
        'icon' => 'requirements',
    ],
    'tareas' => [
        'label' => 'Tareas',
        'href' => $basePath . '/projects/' . $projectId . '/tasks',
        'icon' => 'tasks',
    ],
    'talento' => [
        'label' => 'Talento',
        'href' => $basePath . '/projects/' . $projectId . '/talent',
        'icon' => 'talent',
    ],
    'horas' => [
        'label' => 'Horas',
        'href' => $basePath . '/projects/' . $projectId . '/hours',
        'icon' => 'hours',
    ],
    'costos' => [
        'label' => 'Costos',
        'href' => $basePath . '/projects/' . $projectId . '/costs',
        'icon' => 'costs',
    ],
    'facturacion' => [
        'label' => 'Facturación',
        'href' => $basePath . '/projects/' . $projectId . '/billing',
        'icon' => 'billing',
    ],
];
if ($projectType === 'outsourcing') {
    $tabs['outsourcing'] = [
        'label' => 'Outsourcing',
        'href' => $basePath . '/projects/' . $projectId . '/outsourcing',
        'icon' => 'outsourcing',
    ];
}

$projectTabIcon = static function (string $icon): string {
    return match ($icon) {
        'summary' => '<svg viewBox="0 0 24 24"><path d="M4 13h6v7H4z"/><path d="M14 4h6v16h-6z"/><path d="M4 4h6v5H4z"/></svg>',
        'documents' => '<svg viewBox="0 0 24 24"><path d="M4 6a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v9.5A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5z"/><path d="M4 9h16"/></svg>',
        'notes' => '<svg viewBox="0 0 24 24"><path d="M7 3h10a2 2 0 0 1 2 2v14l-3-2-3 2-3-2-3 2V5a2 2 0 0 1 2-2z"/><path d="M9 8h6"/><path d="M9 12h6"/></svg>',
        'blockers' => '<svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8 8 8 8"/><path d="m16 8-8 8"/></svg>',
        'requirements' => '<svg viewBox="0 0 24 24"><path d="M6 4h12v16H6z"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>',
        'tasks' => '<svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h7"/><path d="M9 12h7"/><path d="M9 16h5"/><path d="m6.5 8 .5.5 1-1"/><path d="m6.5 12 .5.5 1-1"/></svg>',
        'talent' => '<svg viewBox="0 0 24 24"><path d="M9 11a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 9 11Z"/><path d="M16.5 10a2.5 2.5 0 1 0-2.5-2.5A2.5 2.5 0 0 0 16.5 10Z"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M13 20a4.5 4.5 0 0 1 8 0"/></svg>',
        'hours' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
        'costs' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v8"/><path d="M9.5 10c0-1 1-2 2.5-2s2.5.9 2.5 2-1 2-2.5 2-2.5.9-2.5 2 1 2 2.5 2 2.5-1 2.5-2"/></svg>',
        'billing' => '<svg viewBox="0 0 24 24"><path d="M7 3h10a2 2 0 0 1 2 2v14l-3-2-3 2-3-2-3 2V5a2 2 0 0 1 2-2z"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>',
        'outsourcing' => '<svg viewBox="0 0 24 24"><path d="M4 8h16"/><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><rect x="3" y="8" width="18" height="12" rx="2"/></svg>',
        default => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/></svg>',
    };
};
?>

<nav class="project-tabs">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="project-tab <?= $activeTab === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($tab['href']) ?>">
            <span class="project-tab__icon" aria-hidden="true"><?= $projectTabIcon((string) ($tab['icon'] ?? '')) ?></span>
            <span><?= htmlspecialchars($tab['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<style>
    .project-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 18px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 12px;
    }
    .project-tab {
        padding: 9px 14px;
        border-radius: 999px;
        border: 1px solid color-mix(in srgb, var(--border) 84%, transparent);
        text-decoration: none;
        color: var(--text-primary);
        font-weight: 700;
        font-size: 13px;
        background: linear-gradient(
            160deg,
            color-mix(in srgb, var(--surface) 90%, var(--background) 10%),
            color-mix(in srgb, var(--surface) 82%, var(--background) 18%)
        );
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
    }
    .project-tab:hover {
        transform: translateY(-1px);
        border-color: color-mix(in srgb, var(--primary) 42%, var(--border));
        box-shadow: 0 8px 16px color-mix(in srgb, var(--primary) 16%, transparent);
    }
    .project-tab__icon {
        width: 22px;
        height: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        color: var(--primary);
        background: color-mix(in srgb, var(--primary) 15%, var(--surface));
    }
    .project-tab__icon svg {
        width: 14px;
        height: 14px;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        fill: none;
    }
    .project-tab.active {
        background: var(--primary);
        color: var(--text-primary);
        border-color: var(--primary);
        box-shadow: 0 8px 18px color-mix(in srgb, var(--primary) 24%, transparent);
    }
    .project-tab.active .project-tab__icon {
        color: var(--text-primary);
        background: color-mix(in srgb, var(--surface) 25%, transparent);
    }
</style>
