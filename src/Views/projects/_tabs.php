<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$activeTab = $activeTab ?? 'documents';
$projectId = (int) ($project['id'] ?? 0);
$projectType = (string) ($project['project_type'] ?? '');
$viewParam = $_GET['view'] ?? null;
$summaryHref = $basePath . '/projects/' . $projectId . '?view=resumen';
$documentsHref = $basePath . '/projects/' . $projectId . ($viewParam === 'documentos' ? '?view=documentos' : '?view=documentos');
$barChartSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>';
$folderSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
$editSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
$lockSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
$mapPinSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
$checkCircleSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
$usersSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
$dollarSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>';
$receiptSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/></svg>';
$puzzleSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
$tabs = [
    'resumen' => ['label' => 'Resumen', 'href' => $summaryHref, 'icon' => $barChartSvg],
    'documents' => ['label' => 'Documentos', 'href' => $documentsHref, 'icon' => $folderSvg],
    'seguimiento' => ['label' => 'Notas / Seguimiento', 'href' => $basePath . '/projects/' . $projectId . '?view=seguimiento', 'icon' => $editSvg],
    'bloqueos' => ['label' => 'Bloqueos', 'href' => $basePath . '/projects/' . $projectId . '?view=bloqueos', 'icon' => $lockSvg],
    'requisitos' => ['label' => 'Requisitos', 'href' => $basePath . '/projects/' . $projectId . '/requirements', 'icon' => $mapPinSvg],
    'tareas' => ['label' => 'Tareas', 'href' => $basePath . '/projects/' . $projectId . '/tasks', 'icon' => $checkCircleSvg],
    'talento' => ['label' => 'Talento', 'href' => $basePath . '/projects/' . $projectId . '/talent', 'icon' => $usersSvg],
    'costos' => ['label' => 'Costos', 'href' => $basePath . '/projects/' . $projectId . '/costs', 'icon' => $dollarSvg],
    'facturacion' => ['label' => 'Facturación', 'href' => $basePath . '/projects/' . $projectId . '/billing', 'icon' => $receiptSvg],
];
if ($projectType === 'outsourcing') {
    $tabs['outsourcing'] = ['label' => 'Outsourcing', 'href' => $basePath . '/projects/' . $projectId . '/outsourcing', 'icon' => $puzzleSvg];
}
?>

<nav class="project-tabs">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="project-tab <?= $activeTab === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($tab['href']) ?>">
            <span class="project-tab__icon" aria-hidden="true"><?= $tab['icon'] ?? '•' ?></span>
            <span><?= htmlspecialchars($tab['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<style>
    .project-tabs { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:10px; }
    .project-tab { padding:9px 14px; border-radius:999px; border:1px solid var(--border); text-decoration:none; color: var(--text-primary); font-weight:700; font-size:13px; background: color-mix(in srgb, var(--text-secondary) 14%, var(--background)); display:inline-flex; align-items:center; gap:8px; }
    .project-tab__icon { font-size:14px; }
    .project-tab.active { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
</style>
