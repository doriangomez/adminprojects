<?php
$basePath = $basePath ?? '';
$project = $project ?? [];
$projectNodes = is_array($projectNodes ?? null) ? $projectNodes : [];
$progressPhases = is_array($progressPhases ?? null) ? $progressPhases : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];
$canManage = !empty($canManage);
$canDelete = !empty($canDelete);
$canInactivate = !empty($canInactivate);
$isManualProgress = ($project['progress_mode'] ?? $project['progress_type'] ?? 'manual') !== 'automatic';
$canUpdateProgress = !empty($canUpdateProgress) && $isManualProgress;
$progressHistory = is_array($progressHistory ?? null) ? $progressHistory : [];
$progressIndicators = is_array($progressIndicators ?? null) ? $progressIndicators : [];
$billingConfig = is_array($billingConfig ?? null) ? $billingConfig : [];
$projectInvoices = is_array($projectInvoices ?? null) ? $projectInvoices : [];
$billingTotals = is_array($billingTotals ?? null) ? $billingTotals : [];
$missingMonthlyPeriods = is_array($missingMonthlyPeriods ?? null) ? $missingMonthlyPeriods : [];
$approvedHoursPendingInvoicing = (float) ($approvedHoursPendingInvoicing ?? 0);
$canViewBilling = !empty($canViewBilling);
$canManageBilling = !empty($canManageBilling);
$canMarkInvoicePaid = !empty($canMarkInvoicePaid);
$canVoidInvoice = !empty($canVoidInvoice);
$projectNotes = is_array($projectNotes ?? null) ? $projectNotes : [];
$stoppers = is_array($stoppers ?? null) ? $stoppers : [];
$stopperMetrics = is_array($stopperMetrics ?? null) ? $stopperMetrics : [];
$stopperBoard = is_array($stopperBoard ?? null) ? $stopperBoard : [];
$stopperTypeOptions = is_array($stopperTypeOptions ?? null) ? $stopperTypeOptions : [];
$stopperImpactOptions = is_array($stopperImpactOptions ?? null) ? $stopperImpactOptions : [];
$stopperAreaOptions = is_array($stopperAreaOptions ?? null) ? $stopperAreaOptions : [];
$stopperStatusOptions = is_array($stopperStatusOptions ?? null) ? $stopperStatusOptions : [];
$responsibleUsers = is_array($responsibleUsers ?? null) ? $responsibleUsers : [];
$canCloseStoppers = !empty($canCloseStoppers);
$pmoSnapshot = is_array($pmoSnapshot ?? null) ? $pmoSnapshot : [];
$pmoAlerts = is_array($pmoAlerts ?? null) ? $pmoAlerts : [];
$pmoHoursTrend = is_array($pmoHoursTrend ?? null) ? $pmoHoursTrend : [];
$pmoActiveBlockers = is_array($pmoActiveBlockers ?? null) ? $pmoActiveBlockers : [];
$detailWarnings = is_array($detailWarnings ?? null) ? $detailWarnings : [];
$timesheetEntries = is_array($timesheetEntries ?? null) ? $timesheetEntries : [];
$view = $_GET['view'] ?? 'documentos';
$returnUrl = $_GET['return'] ?? ($basePath . '/projects');
$view = in_array($view, ['resumen', 'documentos', 'seguimiento', 'bloqueos', 'horas'], true) ? $view : 'documentos';

$methodology = strtolower((string) ($project['methodology'] ?? 'cascada'));
if ($methodology === 'convencional' || $methodology === '') {
    $methodology = 'cascada';
}

$badgeLabel = $methodology === 'scrum' ? 'Scrum' : 'Tradicional';
$badgeClass = $methodology === 'scrum' ? 'pill methodology scrum' : 'pill methodology traditional';

$flatten = static function (array $nodes) use (&$flatten): array {
    $items = [];
    foreach ($nodes as $node) {
        $items[] = $node;
        if (!empty($node['children'])) {
            $items = array_merge($items, $flatten($node['children']));
        }
    }
    return $items;
};

$tree = $projectNodes;
$allNodes = $flatten($tree);
$nodesById = [];
foreach ($allNodes as $node) {
    if (isset($node['id'])) {
        $nodesById[(int) $node['id']] = $node;
    }
}

$rootNodeId = null;
$rootNode = null;
foreach ($allNodes as $node) {
    if (($node['code'] ?? '') === 'ROOT') {
        $rootNodeId = (int) ($node['id'] ?? 0);
        $rootNode = $node;
        break;
    }
}

$selectedNodeId = $selectedNodeId ?? (isset($_GET['node']) ? (int) $_GET['node'] : null);
$selectedNode = $selectedNodeId && isset($nodesById[$selectedNodeId]) ? $nodesById[$selectedNodeId] : null;

$rootChildren = $rootNode['children'] ?? $tree;
$rootNodesByCode = [];
foreach ($rootChildren as $child) {
    if (($child['code'] ?? '') === 'ROOT') {
        continue;
    }
    $rootNodesByCode[(string) ($child['code'] ?? '')] = $child;
}

$traditionalPhaseOrder = [
    '01-INICIO',
    '02-PLANIFICACION',
    '03-DISEÑO',
    '04-EJECUCION',
    '05-SEGUIMIENTO_Y_CONTROL',
    '06-CIERRE',
];
$scrumPhaseOrder = [
    '01-INICIO',
    '02-BACKLOG',
    '03-SPRINTS',
    '04-CIERRE',
];
$phaseOrder = $methodology === 'scrum' ? $scrumPhaseOrder : $traditionalPhaseOrder;

$phaseNodes = [];
foreach ($phaseOrder as $code) {
    if (!empty($rootNodesByCode[$code])) {
        $phaseNodes[] = $rootNodesByCode[$code];
    }
}
if (empty($phaseNodes)) {
    $phaseNodes = $rootChildren;
}

$sprintGroupNode = $rootNodesByCode['03-SPRINTS'] ?? null;
$sprintNodes = $sprintGroupNode['children'] ?? [];

if (!$selectedNode) {
    if ($methodology === 'scrum' && !empty($sprintNodes)) {
        $selectedNode = $sprintNodes[0];
    } else {
        $selectedNode = $phaseNodes[0] ?? null;
    }
}

$resolvePhaseNode = static function (?array $node, ?int $rootId, ?array $sprintGroup, array $nodesById): ?array {
    if (!$node) {
        return null;
    }
    $sprintGroupId = $sprintGroup['id'] ?? null;
    $cursor = $node;
    while (is_array($cursor)) {
        $parentId = $cursor['parent_id'] ?? null;
        if ($parentId === $rootId) {
            return $cursor;
        }
        if ($sprintGroupId !== null && $parentId === $sprintGroupId) {
            return $cursor;
        }
        if ($parentId === null) {
            break;
        }
        $cursor = $nodesById[(int) $parentId] ?? null;
    }
    return $node;
};

$activePhaseNode = $resolvePhaseNode($selectedNode, $rootNodeId, $sprintGroupNode, $nodesById);
$isSprint = $activePhaseNode && $sprintGroupNode && (int) ($activePhaseNode['parent_id'] ?? 0) === (int) ($sprintGroupNode['id'] ?? 0);

$subphaseLabelMap = [
    '01-ENTRADAS' => 'Entradas',
    '04-EVIDENCIAS' => 'Evidencias',
    '05-CAMBIOS' => 'Cambios',
];
$standardSubphaseSuffixes = array_keys($subphaseLabelMap);
$subphaseNodes = [];
if ($activePhaseNode) {
    foreach ($activePhaseNode['children'] ?? [] as $child) {
        $parts = explode('-', (string) ($child['code'] ?? ''));
        if (count($parts) >= 2) {
            $suffix = implode('-', array_slice($parts, -2));
            if (in_array($suffix, $standardSubphaseSuffixes, true)) {
                $subphaseNodes[$suffix] = $child;
            }
        }
    }
}

$selectedSuffix = null;
if (is_array($selectedNode)) {
    $parts = explode('-', (string) ($selectedNode['code'] ?? ''));
    if (count($parts) >= 2) {
        $selectedSuffix = implode('-', array_slice($parts, -2));
    }
}
$activeSubphaseSuffix = $selectedSuffix && isset($subphaseNodes[$selectedSuffix]) ? $selectedSuffix : (array_key_first($subphaseNodes) ?: null);
$activeSubphaseNode = $activeSubphaseSuffix ? ($subphaseNodes[$activeSubphaseSuffix] ?? null) : null;
$isSubphase = $activeSubphaseNode !== null;
$phaseCodeForFlow = (string) ($activePhaseNode['code'] ?? '');

$documentFlowConfig = is_array($documentFlowConfig ?? null) ? $documentFlowConfig : [];
$documentFlowExpectedDocs = is_array($documentFlowConfig['expected_docs'] ?? null) ? $documentFlowConfig['expected_docs'] : [];
$documentFlowTagOptions = is_array($documentFlowConfig['tag_options'] ?? null) ? $documentFlowConfig['tag_options'] : [];
$expectedDocsForSubphase = [];
if ($isSubphase && $activeSubphaseSuffix) {
    $methodDocs = is_array($documentFlowExpectedDocs[$methodology] ?? null) ? $documentFlowExpectedDocs[$methodology] : [];
    $phaseDocs = is_array($methodDocs[$phaseCodeForFlow] ?? null) ? $methodDocs[$phaseCodeForFlow] : ($methodDocs['default'] ?? []);
    $expectedDocsForSubphase = is_array($phaseDocs[$activeSubphaseSuffix] ?? null) ? $phaseDocs[$activeSubphaseSuffix] : [];
}

$statusLabels = [
    'sin_actividad' => 'Sin actividad',
    'en_ejecucion' => 'En ejecución',
    'cerrado' => 'Cerrado',
    'pendiente' => 'Sin actividad',
    'en_progreso' => 'En ejecución',
    'completado' => 'Cerrado',
];

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'cerrado', 'completado' => 'status-success',
        'en_ejecucion', 'en_progreso' => 'status-info',
        default => 'status-muted',
    };
};

$normalizeExpectedDoc = static function (mixed $doc): ?array {
    if (is_string($doc)) {
        return [
            'name' => $doc,
            'document_type' => $doc,
            'requires_approval' => true,
        ];
    }
    if (!is_array($doc)) {
        return null;
    }
    $name = trim((string) ($doc['name'] ?? $doc['label'] ?? $doc['document_type'] ?? ''));
    if ($name === '') {
        return null;
    }
    return [
        'name' => $name,
        'document_type' => trim((string) ($doc['document_type'] ?? $name)) ?: $name,
        'requires_approval' => array_key_exists('requires_approval', $doc) ? (bool) $doc['requires_approval'] : true,
    ];
};

$phaseProgressByCode = [];
foreach ($progressPhases as $phaseProgress) {
    if (isset($phaseProgress['code'])) {
        $phaseProgressByCode[$phaseProgress['code']] = $phaseProgress;
    }
}

$countNodeDocuments = static function (array $node) use (&$countNodeDocuments): int {
    $count = count($node['files'] ?? []);
    foreach ($node['children'] ?? [] as $childNode) {
        if (!is_array($childNode)) {
            continue;
        }
        $count += $countNodeDocuments($childNode);
    }

    return $count;
};

$computePhaseMetrics = static function (array $phaseNode) use ($documentFlowExpectedDocs, $methodology, $standardSubphaseSuffixes, $normalizeExpectedDoc, $countNodeDocuments): array {
    $methodDocs = is_array($documentFlowExpectedDocs[$methodology] ?? null) ? $documentFlowExpectedDocs[$methodology] : [];
    $phaseCode = (string) ($phaseNode['code'] ?? '');
    $phaseDocs = is_array($methodDocs[$phaseCode] ?? null) ? $methodDocs[$phaseCode] : ($methodDocs['default'] ?? []);

    $childrenBySuffix = [];
    foreach ($phaseNode['children'] ?? [] as $child) {
        $parts = explode('-', (string) ($child['code'] ?? ''));
        if (count($parts) >= 2) {
            $suffix = implode('-', array_slice($parts, -2));
            $childrenBySuffix[$suffix] = $child;
        }
    }

    $subphaseKeys = array_keys($phaseDocs);
    $availableSubphases = array_keys($childrenBySuffix);
    if (!empty($availableSubphases)) {
        $subphaseKeys = !empty($subphaseKeys)
            ? array_values(array_intersect($subphaseKeys, $availableSubphases))
            : $availableSubphases;
    }
    if (empty($subphaseKeys)) {
        $subphaseKeys = $standardSubphaseSuffixes;
    }

    $approvedRequired = 0;
    $totalRequired = 0;

    foreach ($subphaseKeys as $suffix) {
        $docs = is_array($phaseDocs[$suffix] ?? null) ? $phaseDocs[$suffix] : [];
        $expectedDocs = array_values(array_filter(array_map($normalizeExpectedDoc, $docs)));
        $requiredDocs = array_values(array_filter($expectedDocs, static fn (array $doc): bool => (bool) ($doc['requires_approval'] ?? false)));
        $totalRequired += count($requiredDocs);
        $files = $childrenBySuffix[$suffix]['files'] ?? [];
        foreach ($requiredDocs as $doc) {
            $expectedType = strtolower(trim((string) ($doc['document_type'] ?? $doc['name'] ?? '')));
            if ($expectedType === '') {
                continue;
            }
            foreach ($files as $file) {
                if (($file['document_status'] ?? '') !== 'aprobado') {
                    continue;
                }
                $type = strtolower(trim((string) ($file['document_type'] ?? '')));
                $tags = array_map(static fn (string $tag) => strtolower(trim($tag)), $file['tags'] ?? []);
                if ($type === $expectedType || in_array($expectedType, $tags, true)) {
                    $approvedRequired++;
                    break;
                }
            }
        }
    }

    $progress = $totalRequired > 0 ? round(($approvedRequired / $totalRequired) * 100, 1) : 0;
    $evidenceCount = $countNodeDocuments($phaseNode);
    $isClosed = in_array((string) ($phaseNode['status'] ?? ''), ['completado', 'cerrado'], true) || !empty($phaseNode['completed_at']);
    $status = $isClosed ? 'cerrado' : ($evidenceCount > 0 ? 'en_ejecucion' : 'sin_actividad');

    return [
        'status' => $status,
        'progress' => $progress,
        'approved_required' => $approvedRequired,
        'total_required' => $totalRequired,
        'document_count' => $evidenceCount,
    ];
};

$projectProgress = (float) ($project['progress'] ?? 0);
$projectStatusLabel = $project['status_label'] ?? $project['status'] ?? 'Estado no registrado';
$projectClient = $project['client_name'] ?? $project['client'] ?? '';
$clientLogoPath = trim((string) ($project['client_logo_path'] ?? ''));
$clientLogoUrl = $clientLogoPath !== ''
    ? (str_starts_with($clientLogoPath, 'http://') || str_starts_with($clientLogoPath, 'https://') ? $clientLogoPath : $basePath . $clientLogoPath)
    : '';
$clientInitialRaw = $projectClient !== '' ? $projectClient : 'C';
$clientInitial = function_exists('mb_substr')
    ? mb_substr($clientInitialRaw, 0, 1, 'UTF-8')
    : substr($clientInitialRaw, 0, 1);
$clientInitial = function_exists('mb_strtoupper')
    ? mb_strtoupper((string) $clientInitial, 'UTF-8')
    : strtoupper((string) $clientInitial);
$projectMethodLabel = $badgeLabel;
$projectPmName = $project['pm_name'] ?? 'Sin PM asignado';
$projectStage = (string) ($project['project_stage'] ?? 'Discovery');
$projectRiskLabel = $project['health_label'] ?? $project['health'] ?? 'Sin riesgo';
$projectRiskLevel = strtolower((string) ($project['risk_level'] ?? ''));
$riskClass = match ($projectRiskLevel) {
    'alto' => 'status-danger',
    'medio' => 'status-warning',
    'bajo' => 'status-success',
    default => 'status-muted',
};
$activePhaseName = $activePhaseNode['name'] ?? $activePhaseNode['title'] ?? $activePhaseNode['code'] ?? 'Fase';
$activePhaseMetrics = ['status' => 'sin_actividad', 'progress' => 0, 'approved_required' => 0, 'total_required' => 0];
if ($activePhaseNode) {
    $phaseCode = (string) ($activePhaseNode['code'] ?? '');
    $activePhaseMetrics = $phaseProgressByCode[$phaseCode] ?? $computePhaseMetrics($activePhaseNode);
}
$activePhaseStatusLabel = $statusLabels[$activePhaseMetrics['status']] ?? $activePhaseMetrics['status'];
$activeSubphaseIso = $activeSubphaseNode['iso_code'] ?? $activePhaseNode['iso_code'] ?? null;
$activeSubphaseName = $activeSubphaseNode['name'] ?? $activeSubphaseNode['title'] ?? '';
$activeTabLabel = $activeSubphaseSuffix ? ($subphaseLabelMap[$activeSubphaseSuffix] ?? 'Subfase') : 'Subfase';
$tabDescriptions = [
    '01-ENTRADAS' => 'Documentos base para iniciar la fase: propuestas, acuerdos y requisitos.',
    '04-EVIDENCIAS' => 'Entregables, actas e informes que evidencian la ejecución.',
    '05-CAMBIOS' => 'Registros de cambios, impacto y aprobaciones asociadas.',
];
$activeTabDescription = $activeSubphaseSuffix ? ($tabDescriptions[$activeSubphaseSuffix] ?? '') : '';
$approvedDocuments = (int) ($progressIndicators['approved_documents'] ?? 0);
$pendingControls = (int) ($progressIndicators['pending_controls'] ?? 0);
$loggedHours = $progressIndicators['logged_hours'] ?? null;
$estimatedHours = (float) ($project['planned_hours'] ?? 0);
$hoursProgressPercent = ($loggedHours !== null && $estimatedHours > 0)
    ? max(0.0, min(100.0, (((float) $loggedHours) / $estimatedHours) * 100))
    : null;
$lastProgressEntry = $progressHistory[0] ?? null;
$lastProgressUser = $lastProgressEntry['user_name'] ?? 'Sistema';
$lastProgressPayload = is_array($lastProgressEntry['payload'] ?? null) ? $lastProgressEntry['payload'] : [];
$lastProgressJustification = trim((string) ($lastProgressPayload['justification'] ?? ''));
$formatTimestamp = static function (?string $value): string {
    if (!$value) {
        return 'Sin registro';
    }

    $timestamp = strtotime($value);
    if (!$timestamp) {
        return 'Sin registro';
    }

    return date('d/m/Y H:i', $timestamp);
};
$lastProgressDate = $lastProgressEntry ? $formatTimestamp($lastProgressEntry['created_at'] ?? null) : 'Sin registro';

$healthScore = is_array($healthScore ?? null) ? $healthScore : [
    'total_score' => 0,
    'documental_score' => 0,
    'avance_score' => 0,
    'horas_score' => 0,
    'seguimiento_score' => 0,
    'riesgo_score' => 0,
    'calidad_requisitos_score' => 0,
];
$healthTotal = (int) ($healthScore['total_score'] ?? 0);
$healthTone = $healthTotal >= 90 ? 'health-green' : ($healthTotal >= 75 ? 'health-yellow' : 'health-red');
$healthLabel = $healthTotal >= 90 ? 'Salud óptima' : ($healthTotal >= 75 ? 'Atención' : 'Crítico');
$healthBreakdown = is_array($healthScore['breakdown'] ?? null) ? $healthScore['breakdown'] : [];
$healthRecommendations = is_array($healthScore['recommendations'] ?? null) ? $healthScore['recommendations'] : [];
$healthHistory = is_array($healthHistory ?? null) ? $healthHistory : [];
$healthLevel = (string) ($healthScore['level'] ?? ($healthTotal >= 90 ? 'optimal' : ($healthTotal >= 75 ? 'attention' : 'critical')));
$progressHoursAuto = isset($pmoSnapshot['progress_hours']) ? (float) $pmoSnapshot['progress_hours'] : null;
$progressTasksAuto = isset($pmoSnapshot['progress_tasks']) ? (float) $pmoSnapshot['progress_tasks'] : null;
$riskPmoScore = (int) ($pmoSnapshot['risk_score'] ?? 0);
$riskPmoTone = $riskPmoScore >= 70 ? 'red' : ($riskPmoScore >= 40 ? 'yellow' : 'green');

$requiredDocumentsMetaCode = '99-REQDOCS-META';
$requiredDocuments = [
    ['key' => 'propuesta_aceptada', 'name' => 'Propuesta aceptada por el cliente', 'description' => 'Versión final aprobada de la propuesta comercial y alcance.', 'icon' => '📄', 'document_type' => 'Propuesta', 'tag' => 'Propuesta aceptada por el cliente'],
    ['key' => 'acta_inicio', 'name' => 'Acta de inicio de proyecto', 'description' => 'Documento formal de arranque y compromiso del proyecto.', 'icon' => '📝', 'document_type' => 'Acta', 'tag' => 'Acta de inicio de proyecto'],
    ['key' => 'kickoff', 'name' => 'Kickoff', 'description' => 'Acta o presentación de la reunión de inicio con stakeholders.', 'icon' => '🚀', 'document_type' => 'Acta', 'tag' => 'Kickoff'],
    ['key' => 'actas_seguimiento', 'name' => 'Actas de seguimiento', 'description' => 'Evidencias de acuerdos y seguimiento periódico del proyecto.', 'icon' => '🗒️', 'document_type' => 'Acta', 'tag' => 'Actas de seguimiento'],
    ['key' => 'cronograma', 'name' => 'Cronograma de trabajo', 'description' => 'Plan temporal de entregables, hitos y responsables.', 'icon' => '📆', 'document_type' => 'Cronograma', 'tag' => 'Cronograma de trabajo'],
    ['key' => 'pruebas_funcionales', 'name' => 'Pruebas funcionales con el cliente (acta)', 'description' => 'Resultados y conformidad de pruebas funcionales con cliente.', 'icon' => '✅', 'document_type' => 'Acta', 'tag' => 'Pruebas funcionales con el cliente (acta)'],
    ['key' => 'acta_cierre', 'name' => 'Acta de cierre', 'description' => 'Cierre formal del proyecto y aceptación final.', 'icon' => '🏁', 'document_type' => 'Acta', 'tag' => 'Acta de cierre'],
    ['key' => 'lecciones_aprendidas', 'name' => 'Lecciones aprendidas', 'description' => 'Resumen de hallazgos para mejora continua.', 'icon' => '📚', 'document_type' => 'Lecciones aprendidas', 'tag' => 'Lecciones aprendidas'],
    ['key' => 'diagrama_flujo', 'name' => 'Diagrama de flujo', 'description' => 'Representación visual del flujo operativo o funcional.', 'icon' => '🔀', 'document_type' => 'Diagrama', 'tag' => 'Diagrama de flujo'],
    ['key' => 'diagrama_arquitectura', 'name' => 'Diagrama de arquitectura', 'description' => 'Vista estructural de componentes y su interacción.', 'icon' => '🏗️', 'document_type' => 'Diagrama', 'tag' => 'Diagrama de arquitectura'],
    ['key' => 'documento_arquitectura', 'name' => 'Documento de arquitectura', 'description' => 'Documento técnico con decisiones de arquitectura.', 'icon' => '🧱', 'document_type' => 'Arquitectura', 'tag' => 'Documento de arquitectura'],
    ['key' => 'repositorio_git', 'name' => 'Repositorio Git', 'description' => 'URL oficial del repositorio de código fuente del proyecto.', 'icon' => '🔗', 'document_type' => 'Repositorio', 'tag' => 'Repositorio Git', 'is_git' => true],
];

$allProjectFiles = [];
$flattenFiles = static function (array $nodes) use (&$flattenFiles, &$allProjectFiles): void {
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        foreach (($node['files'] ?? []) as $file) {
            if (is_array($file)) {
                $allProjectFiles[] = $file;
            }
        }
        if (!empty($node['children'])) {
            $flattenFiles($node['children']);
        }
    }
};
$flattenFiles($tree);

$userNamesById = [];
foreach ($responsibleUsers as $responsibleUser) {
    $id = isset($responsibleUser['id']) ? (int) $responsibleUser['id'] : 0;
    if ($id <= 0) {
        continue;
    }
    $name = trim((string) ($responsibleUser['name'] ?? $responsibleUser['full_name'] ?? $responsibleUser['email'] ?? ''));
    if ($name !== '') {
        $userNamesById[$id] = $name;
    }
}
if (!empty($currentUser['id'])) {
    $userNamesById[(int) $currentUser['id']] = trim((string) ($currentUser['name'] ?? $currentUser['full_name'] ?? $currentUser['email'] ?? ('Usuario #' . (int) $currentUser['id'])));
}

$requiredDocumentsMeta = [];
foreach ($allNodes as $node) {
    if (($node['code'] ?? '') !== $requiredDocumentsMetaCode) {
        continue;
    }
    $decoded = json_decode((string) ($node['description'] ?? ''), true);
    if (is_array($decoded)) {
        $requiredDocumentsMeta = $decoded;
    }
    break;
}
$gitRepositoryUrl = trim((string) ($requiredDocumentsMeta['git_repository_url'] ?? ''));
$gitRepositoryUpdatedAt = $requiredDocumentsMeta['updated_at'] ?? null;
$gitRepositoryUpdatedBy = isset($requiredDocumentsMeta['updated_by']) ? (int) $requiredDocumentsMeta['updated_by'] : null;

$requiredDocumentsCards = [];
foreach ($requiredDocuments as $requiredDocument) {
    if (!empty($requiredDocument['is_git'])) {
        $isCompleted = $gitRepositoryUrl !== '';
        $requiredDocumentsCards[] = array_merge($requiredDocument, [
            'completed' => $isCompleted,
            'record' => $isCompleted
                ? [
                    'title' => $gitRepositoryUrl,
                    'storage_path' => $gitRepositoryUrl,
                    'created_at' => $gitRepositoryUpdatedAt,
                    'created_by' => $gitRepositoryUpdatedBy,
                ]
                : null,
        ]);
        continue;
    }

    $normalizedTag = mb_strtolower(trim((string) ($requiredDocument['tag'] ?? '')));
    $normalizedType = mb_strtolower(trim((string) ($requiredDocument['document_type'] ?? '')));
    $matches = array_values(array_filter($allProjectFiles, static function (array $file) use ($normalizedTag, $normalizedType): bool {
        $tags = array_map(static fn ($tag): string => mb_strtolower(trim((string) $tag)), is_array($file['tags'] ?? null) ? $file['tags'] : []);
        $type = mb_strtolower(trim((string) ($file['document_type'] ?? '')));
        return ($normalizedTag !== '' && in_array($normalizedTag, $tags, true))
            || ($normalizedType !== '' && $type === $normalizedType);
    }));

    usort($matches, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    $latest = $matches[0] ?? null;
    $requiredDocumentsCards[] = array_merge($requiredDocument, [
        'completed' => $latest !== null,
        'record' => $latest,
    ]);
}
$requiredDocumentsCompleted = count(array_filter($requiredDocumentsCards, static fn (array $doc): bool => !empty($doc['completed'])));
$requiredDocumentsTotal = count($requiredDocumentsCards);
$requiredDocumentsProgress = $requiredDocumentsTotal > 0 ? (int) round(($requiredDocumentsCompleted / $requiredDocumentsTotal) * 100) : 0;
?>

<section class="project-shell">
    <?php if (!empty($detailWarnings)): ?>
        <div class="project-inline-warning" role="alert">
            <?= htmlspecialchars((string) $detailWarnings[0]) ?>
        </div>
    <?php endif; ?>

    <header class="project-header">
        <div class="project-title-block">
            <p class="eyebrow">Detalle de proyecto</p>
            <h2><?= htmlspecialchars($project['name'] ?? '') ?></h2>
            <div class="project-client-identity">
                <?php if ($clientLogoUrl !== ''): ?>
                    <img class="project-client-logo" src="<?= htmlspecialchars($clientLogoUrl) ?>" alt="Logo de <?= htmlspecialchars($projectClient !== '' ? $projectClient : 'Cliente') ?>">
                <?php else: ?>
                    <span class="project-client-avatar" aria-hidden="true"><?= htmlspecialchars($clientInitial !== '' ? $clientInitial : 'C') ?></span>
                <?php endif; ?>
                <div>
                    <strong><?= htmlspecialchars($projectClient !== '' ? $projectClient : 'Cliente no registrado') ?></strong>
                    <small>PM: <?= htmlspecialchars($projectPmName) ?></small>
                </div>
            </div>
            <div class="project-badges">
                <span class="<?= $badgeClass ?>"><?= htmlspecialchars($badgeLabel) ?></span>
                <span class="pill neutral">Cliente: <?= htmlspecialchars($projectClient) ?></span>
                <span class="pill neutral">Estado: <?= htmlspecialchars((string) $projectStatusLabel) ?></span>
                <span class="pill neutral">Stage-gate: <?= htmlspecialchars($projectStage) ?></span>
            </div>
        </div>

        <div class="project-health-card <?= $healthTone ?>" data-health-popover-root>
            <div class="project-health-score">[ <?= $healthTotal ?> / 100 ]</div>
            <div class="project-health-title">Salud Integral</div>
            <div class="project-health-state"><?= $healthTone === 'health-green' ? '🟢' : ($healthTone === 'health-yellow' ? '🟡' : '🔴') ?> <?= htmlspecialchars($healthLabel) ?></div>
            <button type="button" class="action-btn" data-toggle-health-popover>Ver desglose</button>
            <button type="button" class="action-btn primary" data-open-modal="health-modal">Ver análisis completo</button>
            <div class="health-popover" data-health-popover>
                <h4>📊 Desglose Salud Integral</h4>
                <p><strong>Salud total: <?= $healthTotal ?> / 100</strong></p>
                <?php foreach ($healthBreakdown as $dimension => $item): ?>
                    <?php
                    $percentage = (int) ($item['percentage'] ?? 0);
                    $tone = $percentage >= 85 ? 'dim-green' : ($percentage >= 70 ? 'dim-yellow' : 'dim-red');
                    $label = ucfirst(str_replace('_', ' ', (string) $dimension));
                    $issues = is_array($item['issues'] ?? null) ? $item['issues'] : [];
                    ?>
                    <div class="health-dimension <?= $tone ?>">
                        <strong><?= htmlspecialchars($label) ?>:</strong> <?= $percentage ?>% (<?= (int) ($item['score'] ?? 0) ?>/<?= (int) ($item['max'] ?? 0) ?>)
                        <?php if ($issues): ?><div>⚠ <?= htmlspecialchars((string) $issues[0]) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="project-actions">
            <a class="action-btn" href="<?= htmlspecialchars($returnUrl) ?>">Volver al listado</a>
            <a class="action-btn" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit">Editar proyecto</a>
            <?php if ($canDelete || $canInactivate): ?>
                <a class="action-btn danger" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit#zona-critica">
                    <?= $canDelete ? 'Eliminar proyecto' : 'Inactivar proyecto' ?>
                </a>
            <?php endif; ?>
        </div>
    </header>


    <?php if ($canDelete || $canInactivate): ?>
        <section class="danger-shortcut" aria-label="Acción crítica del proyecto">
            <p>
                <?= $canDelete
                    ? '¿Necesitas eliminar el proyecto con todas sus dependencias? Usa el acceso directo a Zona crítica.'
                    : '¿Necesitas inactivar este proyecto? Usa el acceso directo a Zona crítica.' ?>
            </p>
            <a class="action-btn danger" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/edit#zona-critica">
                <?= $canDelete ? 'Ir a eliminar proyecto' : 'Ir a inactivar proyecto' ?>
            </a>
        </section>
    <?php endif; ?>

    <?php
    $activeTab = match ($view) {
        'resumen' => 'resumen',
        'horas' => 'horas',
        'seguimiento' => 'seguimiento',
        'bloqueos' => 'bloqueos',
        default => 'documents',
    };
    require __DIR__ . '/_tabs.php';
    ?>

    <?php if ($view === 'resumen'): ?>
        <section class="summary-layout">
            <article class="info-card">
                <div class="info-card__header">
                    <div>
                        <p class="eyebrow">Información base</p>
                        <h4>Ficha del proyecto</h4>
                    </div>
                </div>
                <div class="info-list">
                    <div>
                        <span>Cliente</span>
                        <strong><?= htmlspecialchars($projectClient ?: 'Sin cliente') ?></strong>
                    </div>
                    <div>
                        <span>Metodología</span>
                        <strong><?= htmlspecialchars($projectMethodLabel) ?></strong>
                    </div>
                    <div>
                        <span>Estado</span>
                        <strong><?= htmlspecialchars((string) $projectStatusLabel) ?></strong>
                    </div>
                    <div>
                        <span>Stage-gate</span>
                        <strong><?= htmlspecialchars($projectStage) ?></strong>
                    </div>
                    <div>
                        <span>PM</span>
                        <strong><?= htmlspecialchars($projectPmName) ?></strong>
                    </div>
                    <div>
                        <span>Fechas</span>
                        <strong><?= htmlspecialchars($project['start_date'] ?? 'Sin inicio') ?> → <?= htmlspecialchars($project['end_date'] ?? 'Sin fin') ?></strong>
                    </div>
                    <div>
                        <span>Riesgo</span>
                        <strong><?= htmlspecialchars($projectRiskLabel) ?></strong>
                        <span class="badge status-badge <?= $riskClass ?>">Nivel <?= htmlspecialchars($projectRiskLevel ?: 'N/A') ?></span>
                    </div>
                </div>
            </article>

            <article class="progress-card">
                <div class="progress-card__header">
                    <div>
                        <p class="eyebrow">Avance global manual</p>
                        <h4>Visión ejecutiva del progreso</h4>
                    </div>
                    <?php if ($canUpdateProgress): ?>
                        <button class="action-btn primary" type="button" data-open-modal="progress-modal">Actualizar avance</button>
                    <?php endif; ?>
                </div>
                <div class="project-progress">
                    <span class="project-progress__label">Avance global</span>
                    <div class="project-progress__bar" role="progressbar" aria-valuenow="<?= $projectProgress ?>" aria-valuemin="0" aria-valuemax="100">
                        <div style="width: <?= $projectProgress ?>%;"></div>
                    </div>
                    <span class="project-progress__value"><?= $projectProgress ?>% completado</span>
                </div>
                <div class="pmo-kpis">
                    <div class="pmo-kpi">
                        <span>Avance manual</span>
                        <strong><?= number_format($projectProgress, 1) ?>%</strong>
                    </div>
                    <div class="pmo-kpi">
                        <span>Avance por horas</span>
                        <strong><?= $progressHoursAuto !== null ? number_format($progressHoursAuto, 1) . '%' : 'N/A' ?></strong>
                    </div>
                    <div class="pmo-kpi">
                        <span>Avance por tareas</span>
                        <strong><?= $progressTasksAuto !== null ? number_format($progressTasksAuto, 1) . '%' : 'N/A' ?></strong>
                    </div>
                    <div class="pmo-kpi">
                        <span>Riesgo PMO</span>
                        <strong class="status-<?= $riskPmoTone ?>"><?= $riskPmoScore ?>/100</strong>
                    </div>
                </div>
                <div class="progress-meta">
                    <div class="progress-meta__item">
                        <span>Última actualización</span>
                        <strong><?= htmlspecialchars($lastProgressDate) ?></strong>
                    </div>
                    <div class="progress-meta__item">
                        <span>Actualizado por</span>
                        <strong><?= htmlspecialchars($lastProgressUser ?: 'Sistema') ?></strong>
                    </div>
                    <div class="progress-meta__item full">
                        <span>Justificación</span>
                        <p><?= $lastProgressJustification !== '' ? htmlspecialchars($lastProgressJustification) : 'Sin registro' ?></p>
                    </div>
                </div>
            </article>
        </section>

        <section class="indicator-grid">
            <article class="indicator-card">
                <span class="indicator-icon">📄</span>
                <div>
                    <span>Docs aprobados</span>
                    <strong><?= $approvedDocuments ?></strong>
                </div>
            </article>
            <article class="indicator-card">
                <span class="indicator-icon">⏳</span>
                <div>
                    <span>Controles pendientes</span>
                    <strong><?= $pendingControls ?></strong>
                </div>
            </article>
            <article class="indicator-card">
                <span class="indicator-icon">⏱️</span>
                <div>
                    <span>Horas (registradas / estimadas)</span>
                    <strong>
                        <?= $loggedHours !== null ? number_format((float) $loggedHours, 1) . 'h' : 'N/A' ?>
                        / <?= number_format($estimatedHours, 1) ?>h
                    </strong>
                    <small>
                        <?= $hoursProgressPercent !== null ? 'Consumo ' . number_format($hoursProgressPercent, 1) . '%' : 'Sin base estimada' ?>
                    </small>
                </div>
            </article>
        </section>

        <section class="pmo-grid">
            <article class="card">
                <h4>Alertas PMO</h4>
                <?php if ($pmoAlerts === []): ?>
                    <p class="section-muted">Sin alertas PMO activas.</p>
                <?php else: ?>
                    <ul class="alerts-list">
                        <?php foreach ($pmoAlerts as $alert): ?>
                            <li>
                                <strong>[<?= strtoupper((string) ($alert['severity'] ?? 'green')) ?>]</strong>
                                <?= htmlspecialchars((string) ($alert['title'] ?? 'Alerta')) ?>:
                                <?= htmlspecialchars((string) ($alert['message'] ?? '')) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
            <article class="card">
                <h4>Tendencia horas (últimas 4 semanas)</h4>
                <?php if ($pmoHoursTrend === []): ?>
                    <p class="section-muted">Aún no hay horas aprobadas para mostrar tendencia.</p>
                <?php else: ?>
                    <ul class="alerts-list">
                        <?php foreach ($pmoHoursTrend as $week): ?>
                            <li><?= htmlspecialchars((string) ($week['label'] ?? 'Semana')) ?>: <?= number_format((float) ($week['approved_hours'] ?? 0), 2) ?>h</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
            <article class="card">
                <h4>Bloqueos activos</h4>
                <?php if ($pmoActiveBlockers === []): ?>
                    <p class="section-muted">No hay bloqueos activos registrados.</p>
                <?php else: ?>
                    <ul class="alerts-list">
                        <?php foreach ($pmoActiveBlockers as $blocker): ?>
                            <li>
                                <?= htmlspecialchars((string) ($blocker['title'] ?? 'Bloqueo')) ?>
                                · <?= htmlspecialchars((string) ($blocker['impact_level'] ?? 'medio')) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </section>





        <section class="progress-history">
            <div class="history-card">
                <div class="history-header">
                    <div>
                        <p class="eyebrow">Historial de avance</p>
                        <h4>Bitácora de decisiones</h4>
                    </div>
                </div>
                <?php if (empty($progressHistory)): ?>
                    <p class="section-muted">Aún no hay actualizaciones registradas.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Anterior</th>
                                    <th>Nuevo</th>
                                    <th>Justificación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($progressHistory as $entry): ?>
                                    <?php
                                    $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
                                    $previous = $payload['previous_progress'] ?? null;
                                    $next = $payload['new_progress'] ?? null;
                                    $justification = trim((string) ($payload['justification'] ?? ''));
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($formatTimestamp($entry['created_at'] ?? null)) ?></td>
                                        <td><?= htmlspecialchars($entry['user_name'] ?? 'Sistema') ?></td>
                                        <td><?= $previous !== null ? number_format((float) $previous, 1) . '%' : '-' ?></td>
                                        <td><?= $next !== null ? number_format((float) $next, 1) . '%' : '-' ?></td>
                                        <td><?= $justification !== '' ? htmlspecialchars($justification) : 'Sin registro' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($view === 'bloqueos'): ?>
        <?php
        $impactLabel = ['bajo' => 'Bajo', 'medio' => 'Medio', 'alto' => 'Alto', 'critico' => 'Crítico'];
        $typeLabel = ['cliente' => 'Cliente', 'tecnico' => 'Técnico', 'interno' => 'Interno', 'proveedor' => 'Proveedor', 'financiero' => 'Financiero', 'legal' => 'Legal'];
        $areaLabel = ['tiempo' => 'Tiempo', 'alcance' => 'Alcance', 'costo' => 'Costo', 'calidad' => 'Calidad'];
        $statusLabel = ['abierto' => 'Abierto', 'en_gestion' => 'En gestión', 'escalado' => 'Escalado', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado'];
        $impactClass = static function (string $impact): string { return match ($impact) { 'critico' => 'status-danger', 'alto' => 'status-warning', 'medio' => 'status-info', default => 'status-success', }; };
        $impactMeta = [
            'critico' => ['label' => 'Críticos', 'icon' => '🚨', 'cardClass' => 'critical'],
            'alto' => ['label' => 'Altos', 'icon' => '⚠️', 'cardClass' => 'high'],
            'medio' => ['label' => 'Medios', 'icon' => '📋', 'cardClass' => 'medium'],
            'bajo' => ['label' => 'Bajos', 'icon' => '✅', 'cardClass' => 'low'],
        ];
        $today = new \DateTimeImmutable('today');
        $monthlyTrend = [];
        $impactTotals = ['critico' => 0, 'alto' => 0, 'medio' => 0, 'bajo' => 0];
        $impactAvgDays = ['critico' => 0.0, 'alto' => 0.0, 'medio' => 0.0, 'bajo' => 0.0];
        $impactCountsForAvg = ['critico' => 0, 'alto' => 0, 'medio' => 0, 'bajo' => 0];
        $openCount = 0;
        $closedCount = 0;
        $kanban = ['critico' => [], 'alto' => [], 'medio' => [], 'bajo' => []];

        foreach ($stoppers as $stopper) {
            $impact = (string) ($stopper['impact_level'] ?? 'bajo');
            if (!isset($impactTotals[$impact])) {
                $impact = 'bajo';
            }
            $detectedAtRaw = (string) ($stopper['detected_at'] ?? '');
            $detectedAt = $detectedAtRaw !== '' ? new \DateTimeImmutable($detectedAtRaw) : null;
            $daysOpen = $detectedAt ? max(0, (int) $detectedAt->diff($today)->format('%a')) : 0;
            $monthKey = $detectedAt ? $detectedAt->format('Y-m') : $today->format('Y-m');

            $monthlyTrend[$monthKey] = ($monthlyTrend[$monthKey] ?? 0) + 1;
            $impactTotals[$impact] += 1;
            $impactAvgDays[$impact] += $daysOpen;
            $impactCountsForAvg[$impact] += 1;

            if (($stopper['status'] ?? '') === 'cerrado') {
                $closedCount++;
                continue;
            }

            $openCount++;
            $kanban[$impact][] = [
                'title' => (string) ($stopper['title'] ?? 'Bloqueo sin título'),
                'responsible' => (string) ($stopper['responsible_name'] ?? 'Sin asignar'),
                'status' => $statusLabel[$stopper['status'] ?? ''] ?? (string) ($stopper['status'] ?? 'Pendiente'),
                'days_open' => $daysOpen,
            ];
        }

        ksort($monthlyTrend);
        $monthlyTrend = array_slice($monthlyTrend, -6, 6, true);
        $maxByImpact = max($impactTotals ?: [1]);
        $maxTrendValue = max($monthlyTrend ?: [1]);
        $totalForDonut = max(1, $openCount + $closedCount);
        $openPct = (int) round(($openCount / $totalForDonut) * 100);
        $circumference = 2 * pi() * 52;
        $donutOpen = ($openPct / 100) * $circumference;
        ?>
        <section class="stoppers-analytics card">
            <div class="analytics-grid">
                <article class="chart-panel">
                    <h4>Bloqueos por nivel de impacto</h4>
                    <div class="impact-bars" role="img" aria-label="Distribución de bloqueos por nivel de impacto">
                        <?php foreach ($impactMeta as $impact => $meta): ?>
                            <?php $barPct = $maxByImpact > 0 ? (int) round((($impactTotals[$impact] ?? 0) / $maxByImpact) * 100) : 0; ?>
                            <div class="impact-bar impact-bar--<?= htmlspecialchars($meta['cardClass']) ?>">
                                <div class="impact-bar__label"><span><?= htmlspecialchars($meta['icon']) ?> <?= htmlspecialchars($meta['label']) ?></span><strong><?= (int) ($impactTotals[$impact] ?? 0) ?></strong></div>
                                <div class="impact-bar__track"><div style="width: <?= $barPct ?>%"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="chart-panel">
                    <h4>Tendencia mensual de bloqueos</h4>
                    <svg class="trend-chart" viewBox="0 0 360 160" role="img" aria-label="Tendencia mensual de bloqueos">
                        <?php
                        $points = [];
                        $countMonths = max(1, count($monthlyTrend));
                        $index = 0;
                        foreach ($monthlyTrend as $value) {
                            $x = 30 + (($index / max(1, $countMonths - 1)) * 300);
                            $y = 130 - (($value / max(1, $maxTrendValue)) * 90);
                            $points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
                            $index++;
                        }
                        ?>
                        <polyline class="trend-line" points="<?= htmlspecialchars(implode(' ', $points)) ?>"></polyline>
                        <?php
                        $index = 0;
                        foreach ($monthlyTrend as $month => $value):
                            $x = 30 + (($index / max(1, $countMonths - 1)) * 300);
                            $y = 130 - (($value / max(1, $maxTrendValue)) * 90);
                        ?>
                            <circle cx="<?= number_format($x, 2, '.', '') ?>" cy="<?= number_format($y, 2, '.', '') ?>" r="4"></circle>
                            <text x="<?= number_format($x, 2, '.', '') ?>" y="150" text-anchor="middle"><?= htmlspecialchars(date('M', strtotime($month . '-01'))) ?></text>
                        <?php $index++; endforeach; ?>
                    </svg>
                </article>

                <article class="chart-panel donut-panel">
                    <h4>% abiertos vs cerrados</h4>
                    <svg width="140" height="140" viewBox="0 0 140 140" role="img" aria-label="Porcentaje de bloqueos abiertos y cerrados">
                        <circle cx="70" cy="70" r="52" class="donut-track"></circle>
                        <circle cx="70" cy="70" r="52" class="donut-open" stroke-dasharray="<?= number_format($donutOpen, 2, '.', '') . ' ' . number_format($circumference - $donutOpen, 2, '.', '') ?>"></circle>
                        <text x="70" y="70" text-anchor="middle" class="donut-value"><?= $openPct ?>%</text>
                        <text x="70" y="88" text-anchor="middle" class="donut-label">Abiertos</text>
                    </svg>
                    <div class="donut-legend">
                        <span><i class="legend-open"></i> Abiertos: <?= $openCount ?></span>
                        <span><i class="legend-closed"></i> Cerrados: <?= $closedCount ?></span>
                    </div>
                </article>
            </div>
        </section>

        <section class="impact-cards-grid">
            <?php foreach ($impactMeta as $impact => $meta): ?>
                <?php
                $totalByImpact = (int) ($impactTotals[$impact] ?? 0);
                $avgByImpact = $impactCountsForAvg[$impact] > 0 ? $impactAvgDays[$impact] / $impactCountsForAvg[$impact] : 0;
                $trendIcon = $avgByImpact >= 7 ? '↑' : '↓';
                ?>
                <article class="impact-card impact-card--<?= htmlspecialchars($meta['cardClass']) ?>">
                    <div class="impact-card__title">
                        <span><?= htmlspecialchars($meta['icon']) ?></span>
                        <strong><?= htmlspecialchars($meta['label']) ?></strong>
                        <span class="impact-trend"><?= $trendIcon ?></span>
                    </div>
                    <div class="impact-card__value"><?= $totalByImpact ?></div>
                    <p>Días promedio abiertos: <strong><?= number_format($avgByImpact, 1, ',', '.') ?></strong></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="kanban-block card">
            <h4>Tablero Kanban de bloqueos</h4>
            <div class="kanban-grid">
                <?php foreach ($impactMeta as $impact => $meta): ?>
                    <article class="kanban-column kanban-column--<?= htmlspecialchars($meta['cardClass']) ?>">
                        <header><span><?= htmlspecialchars($meta['icon']) ?> <?= htmlspecialchars($meta['label']) ?></span><strong><?= count($kanban[$impact] ?? []) ?></strong></header>
                        <?php if (empty($kanban[$impact])): ?>
                            <p class="kanban-empty">Sin bloqueos activos.</p>
                        <?php else: ?>
                            <?php foreach ($kanban[$impact] as $item): ?>
                                <div class="kanban-item">
                                    <h5><?= htmlspecialchars($item['title']) ?></h5>
                                    <p><strong>Responsable:</strong> <?= htmlspecialchars($item['responsible']) ?></p>
                                    <p><strong>Días abiertos:</strong> <?= (int) $item['days_open'] ?></p>
                                    <span class="badge"><?= htmlspecialchars($item['status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <h4>Registrar bloqueo</h4>
            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/stoppers" class="form-grid">
                <label>Título<input name="title" required></label>
                <label>Responsable
                    <select name="responsible_id" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($responsibleUsers as $responsible): ?>
                            <option value="<?= (int) ($responsible['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($responsible['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Tipo
                    <select name="stopper_type" required><?php foreach ($stopperTypeOptions as $option): ?><option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($typeLabel[$option] ?? ucfirst($option)) ?></option><?php endforeach; ?></select>
                </label>
                <label>Impacto
                    <select name="impact_level" required><?php foreach ($stopperImpactOptions as $option): ?><option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($impactLabel[$option] ?? ucfirst($option)) ?></option><?php endforeach; ?></select>
                </label>
                <label>Área
                    <select name="affected_area" required><?php foreach ($stopperAreaOptions as $option): ?><option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($areaLabel[$option] ?? ucfirst($option)) ?></option><?php endforeach; ?></select>
                </label>
                <label>Estado
                    <select name="status" required><?php foreach ($stopperStatusOptions as $option): ?><option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($statusLabel[$option] ?? ucfirst($option)) ?></option><?php endforeach; ?></select>
                </label>
                <label>Fecha detección<input type="date" name="detected_at" required></label>
                <label>Fecha estimada resolución<input type="date" name="estimated_resolution_at" required></label>
                <label style="grid-column: 1 / -1;">Descripción<textarea name="description" rows="3" required></textarea></label>
                <button type="submit" class="action-btn primary">Crear bloqueo</button>
            </form>
        </section>

        <section class="card">
            <h4>Listado de bloqueos</h4>
            <div class="table-wrapper"><table><thead><tr><th>Título</th><th>Tipo</th><th>Impacto</th><th>Área</th><th>Responsable</th><th>Estado</th><th>Detección</th><th>Resolución estimada</th><th>Cierre</th></tr></thead><tbody>
            <?php foreach ($stoppers as $stopper): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($stopper['title'] ?? '')) ?><br><small><?= htmlspecialchars((string) ($stopper['description'] ?? '')) ?></small></td>
                    <td><?= htmlspecialchars($typeLabel[$stopper['stopper_type'] ?? ''] ?? (string) ($stopper['stopper_type'] ?? '')) ?></td>
                    <td><span class="badge status-badge <?= $impactClass((string) ($stopper['impact_level'] ?? '')) ?>"><?= htmlspecialchars($impactLabel[$stopper['impact_level'] ?? ''] ?? '') ?></span></td>
                    <td><?= htmlspecialchars($areaLabel[$stopper['affected_area'] ?? ''] ?? '') ?></td>
                    <td><?= htmlspecialchars((string) ($stopper['responsible_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($statusLabel[$stopper['status'] ?? ''] ?? '') ?></td>
                    <td><?= htmlspecialchars((string) ($stopper['detected_at'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($stopper['estimated_resolution_at'] ?? '')) ?></td>
                    <td>
                        <?php if (($stopper['status'] ?? '') !== 'cerrado' && $canCloseStoppers): ?>
                            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/stoppers/<?= (int) ($stopper['id'] ?? 0) ?>/close">
                                <textarea name="closure_comment" rows="2" placeholder="Comentario de cierre" required></textarea>
                                <button class="action-btn" type="submit">Cerrar</button>
                            </form>
                        <?php else: ?>
                            <?= htmlspecialchars((string) ($stopper['closure_comment'] ?? '-')) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </section>

    <?php elseif ($view === 'horas'): ?>
        <section class="card">
            <h4>Horas registradas del proyecto</h4>
            <p class="section-muted">Detalle operativo consolidado desde el módulo de timesheets.</p>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Tarea</th>
                            <th>Horas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($timesheetEntries === []): ?>
                            <tr>
                                <td colspan="4" class="section-muted">No hay registros de horas para este proyecto.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($timesheetEntries as $entry): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($entry['date'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($entry['user_name'] ?? 'Sin usuario')) ?></td>
                                <td><?= htmlspecialchars((string) ($entry['task_name'] ?? 'Sin tarea')) ?></td>
                                <td><?= number_format((float) ($entry['hours'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php elseif ($view === 'seguimiento'): ?>
        <section class="notes-grid" id="project-notes">
            <article class="notes-card">
                <div class="notes-card__header">
                    <div>
                        <p class="eyebrow">Notas y seguimiento</p>
                        <h4>Registro operativo del proyecto</h4>
                    </div>
                    <span class="pill neutral">Historial activo</span>
                </div>
                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/notes" class="notes-form">
                    <label class="notes-field">
                        <span>Nueva nota</span>
                        <textarea name="note" rows="4" placeholder="Registra acuerdos, seguimiento y decisiones clave." required></textarea>
                    </label>
                    <div class="notes-actions">
                        <button type="submit" class="action-btn primary">Guardar nota</button>
                    </div>
                </form>
            </article>

            <article class="notes-card">
                <div class="notes-card__header">
                    <div>
                        <p class="eyebrow">Historial</p>
                        <h4>Seguimientos recientes</h4>
                    </div>
                </div>
                <?php if (empty($projectNotes)): ?>
                    <p class="section-muted">Aún no hay notas registradas para este proyecto.</p>
                <?php else: ?>
                    <div class="notes-timeline">
                        <?php foreach ($projectNotes as $note): ?>
                            <?php
                            $payload = is_array($note['payload'] ?? null) ? $note['payload'] : [];
                            $noteText = trim((string) ($payload['note'] ?? ''));
                            ?>
                            <div class="notes-entry">
                                <div>
                                    <strong><?= htmlspecialchars($note['user_name'] ?? 'Sistema') ?></strong>
                                    <span class="section-muted"><?= htmlspecialchars($formatTimestamp($note['created_at'] ?? null)) ?></span>
                                </div>
                                <p><?= $noteText !== '' ? nl2br(htmlspecialchars($noteText)) : 'Sin detalle registrado.' ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    <?php else: ?>
        <section class="required-documents-block">
            <div class="required-documents-block__header">
                <div>
                    <p class="eyebrow">Documentación base</p>
                    <h3>Documentos obligatorios del proyecto</h3>
                    <p class="section-muted"><?= $requiredDocumentsCompleted ?> / <?= $requiredDocumentsTotal ?> documentos obligatorios completados</p>
                </div>
            </div>
            <div class="required-documents-progress">
                <div class="project-progress__bar" role="progressbar" aria-valuenow="<?= $requiredDocumentsProgress ?>" aria-valuemin="0" aria-valuemax="100">
                    <div style="width: <?= $requiredDocumentsProgress ?>%;"></div>
                </div>
            </div>
            <div class="required-documents-grid">
                <?php foreach ($requiredDocumentsCards as $requiredCard): ?>
                    <?php
                    $record = is_array($requiredCard['record'] ?? null) ? $requiredCard['record'] : null;
                    $isCompleted = !empty($requiredCard['completed']);
                    $recordedBy = isset($record['created_by']) ? ($userNamesById[(int) $record['created_by']] ?? ('Usuario #' . (int) $record['created_by'])) : 'Sin usuario';
                    $recordDate = $formatTimestamp($record['created_at'] ?? null);
                    $isGitCard = !empty($requiredCard['is_git']);
                    ?>
                    <article class="required-doc-card">
                        <div class="required-doc-card__top">
                            <span class="required-doc-card__icon"><?= htmlspecialchars((string) ($requiredCard['icon'] ?? '📄')) ?></span>
                            <span class="expected-pill <?= $isCompleted ? 'expected-approved' : 'expected-pending' ?>"><?= $isCompleted ? 'Completado' : 'Pendiente' ?></span>
                        </div>
                        <div class="required-doc-card__body">
                            <strong><?= htmlspecialchars((string) ($requiredCard['name'] ?? 'Documento')) ?></strong>
                            <small><?= htmlspecialchars((string) ($requiredCard['description'] ?? '')) ?></small>
                        </div>
                        <div class="required-doc-card__actions">
                            <button
                                type="button"
                                class="action-btn primary"
                                data-required-document-action
                                data-required-doc-key="<?= htmlspecialchars((string) ($requiredCard['key'] ?? '')) ?>"
                                data-required-doc-name="<?= htmlspecialchars((string) ($requiredCard['name'] ?? '')) ?>"
                                data-required-doc-type="<?= htmlspecialchars((string) ($requiredCard['document_type'] ?? '')) ?>"
                                data-required-doc-tag="<?= htmlspecialchars((string) ($requiredCard['tag'] ?? '')) ?>"
                                data-required-doc-git="<?= $isGitCard ? '1' : '0' ?>"
                                data-required-doc-url="<?= htmlspecialchars((string) ($record['storage_path'] ?? '')) ?>"
                            >
                                <?= $isCompleted ? 'Ver / Editar' : 'Registrar documento' ?>
                            </button>
                            <?php if ($isCompleted && $record): ?>
                                <div class="required-doc-card__meta">
                                    <?php if ($isGitCard): ?>
                                        <a href="<?= htmlspecialchars((string) $record['storage_path']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $record['storage_path']) ?></a>
                                    <?php else: ?>
                                        <span><?= htmlspecialchars((string) ($record['file_name'] ?? 'Documento cargado')) ?></span>
                                    <?php endif; ?>
                                    <small><?= htmlspecialchars($recordDate) ?> · <?= htmlspecialchars($recordedBy) ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <div class="required-documents-divider" aria-hidden="true"></div>
        <div class="project-layout">
            <aside class="phase-sidebar">
                <div class="phase-sidebar__header">
                    <div>
                        <strong>Explorador de fases</strong>
                        <p class="section-muted">Accede a cada fase del expediente. Las subfases viven en los tabs centrales.</p>
                    </div>
                    <?php if ($canManage && $methodology === 'scrum'): ?>
                        <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/sprints">
                            <button class="action-btn small" type="submit">Nuevo sprint</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($phaseNodes)): ?>
                    <p class="section-muted">Sin fases disponibles.</p>
                <?php else: ?>
                    <ul class="phase-list">
                        <?php foreach ($phaseNodes as $phase): ?>
                            <?php
                            $phaseCode = (string) ($phase['code'] ?? '');
                            $phaseMetrics = $phaseProgressByCode[$phaseCode] ?? $computePhaseMetrics($phase);
                            $phaseDocumentCount = isset($phaseMetrics['document_count']) ? (int) $phaseMetrics['document_count'] : $countNodeDocuments($phase);
                            $phaseDocumentLabel = $phaseDocumentCount === 1 ? '1 documento' : $phaseDocumentCount . ' documentos';
                            $isPhaseActive = $activePhaseNode && (int) ($activePhaseNode['id'] ?? 0) === (int) ($phase['id'] ?? 0);
                            $phaseLink = $basePath . '/projects/' . (int) ($project['id'] ?? 0) . '?view=documentos&node=' . (int) ($phase['id'] ?? 0);
                            ?>
                            <?php if ($methodology === 'scrum' && ($phase['code'] ?? '') === '03-SPRINTS'): ?>
                                <li class="phase-item">
                                    <details class="phase-group" <?= $isSprint ? 'open' : '' ?>>
                                        <summary class="phase-link">
                                            <div class="phase-link__title">
                                                <span class="phase-icon">📁</span>
                                                <div>
                                                    <strong><?= htmlspecialchars($phase['name'] ?? $phase['title'] ?? 'Sprints') ?></strong>
                                                </div>
                                            </div>
                                            <?php if ($phaseDocumentCount > 0): ?>
                                                <span class="phase-doc-badge"><?= htmlspecialchars($phaseDocumentLabel) ?></span>
                                            <?php endif; ?>
                                        </summary>
                                        <ul class="phase-sublist">
                                            <?php foreach ($sprintNodes as $sprint): ?>
                                                <?php
                                                $sprintMetrics = $computePhaseMetrics($sprint);
                                                $sprintDocumentCount = isset($sprintMetrics['document_count']) ? (int) $sprintMetrics['document_count'] : $countNodeDocuments($sprint);
                                                $sprintDocumentLabel = $sprintDocumentCount === 1 ? '1 documento' : $sprintDocumentCount . ' documentos';
                                                $isSprintActive = $activePhaseNode && (int) ($activePhaseNode['id'] ?? 0) === (int) ($sprint['id'] ?? 0);
                                                $sprintLink = $basePath . '/projects/' . (int) ($project['id'] ?? 0) . '?view=documentos&node=' . (int) ($sprint['id'] ?? 0);
                                                ?>
                                                <li>
                                                    <a class="phase-link <?= $isSprintActive ? 'active' : '' ?>" href="<?= htmlspecialchars($sprintLink) ?>">
                                                        <div class="phase-link__title">
                                                            <span class="phase-icon">📁</span>
                                                            <div>
                                                                <strong><?= htmlspecialchars($sprint['name'] ?? $sprint['title'] ?? '') ?></strong>
                                                            </div>
                                                        </div>
                                                        <?php if ($sprintDocumentCount > 0): ?>
                                                            <span class="phase-doc-badge"><?= htmlspecialchars($sprintDocumentLabel) ?></span>
                                                        <?php endif; ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                </li>
                            <?php else: ?>
                                <li class="phase-item">
                                    <a class="phase-link <?= $isPhaseActive ? 'active' : '' ?>" href="<?= htmlspecialchars($phaseLink) ?>">
                                        <div class="phase-link__title">
                                            <span class="phase-icon">📁</span>
                                            <div>
                                                <strong><?= htmlspecialchars($phase['name'] ?? $phase['title'] ?? $phase['code'] ?? '') ?></strong>
                                            </div>
                                        </div>
                                        <?php if ($phaseDocumentCount > 0): ?>
                                            <span class="phase-doc-badge"><?= htmlspecialchars($phaseDocumentLabel) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </aside>

            <main class="phase-panel">
                <?php if (!$activePhaseNode): ?>
                    <p class="section-muted">Selecciona una fase para comenzar.</p>
                <?php else: ?>
                    <header class="phase-panel__header">
                        <div>
                            <nav class="breadcrumb">
                                <a href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>?view=documentos">Proyecto</a>
                                <span>›</span>
                                <?php if ($isSprint && $sprintGroupNode): ?>
                                    <span><?= htmlspecialchars($sprintGroupNode['name'] ?? $sprintGroupNode['title'] ?? 'Sprints') ?></span>
                                    <span>›</span>
                                    <span><?= htmlspecialchars($activePhaseName) ?></span>
                                <?php else: ?>
                                    <span><?= htmlspecialchars($activePhaseName) ?></span>
                                <?php endif; ?>
                            </nav>
                            <h3><?= htmlspecialchars($activePhaseName) ?> <?= $isSprint ? '(Sprint)' : '' ?></h3>
                            <div class="phase-meta">
                                <span class="badge status-badge <?= $statusBadgeClass($activePhaseMetrics['status']) ?>"><?= htmlspecialchars($activePhaseStatusLabel) ?></span>
                                <span class="count-pill">Avance <?= $activePhaseMetrics['progress'] ?>%</span>
                                <?php if ($activeSubphaseIso): ?>
                                    <span class="count-pill">ISO <?= htmlspecialchars((string) $activeSubphaseIso) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($canManage): ?>
                            <div class="phase-actions">
                                <?php if ($activePhaseNode): ?>
                                    <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($activePhaseNode['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar esta fase y su contenido?');">
                                        <button type="submit" class="action-btn danger">Eliminar fase</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($activeSubphaseNode): ?>
                                    <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/nodes/<?= (int) ($activeSubphaseNode['id'] ?? 0) ?>/delete" onsubmit="return confirm('¿Eliminar esta subfase y su contenido?');">
                                        <button type="submit" class="action-btn">Eliminar subfase</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </header>

                    <div class="phase-tabs">
                        <?php foreach ($subphaseLabelMap as $suffix => $label): ?>
                            <?php if (isset($subphaseNodes[$suffix])): ?>
                                <?php $tabLink = $basePath . '/projects/' . (int) ($project['id'] ?? 0) . '?view=documentos&node=' . (int) ($subphaseNodes[$suffix]['id'] ?? 0); ?>
                                <a class="phase-tab <?= $activeSubphaseSuffix === $suffix ? 'active' : '' ?>" href="<?= htmlspecialchars($tabLink) ?>">
                                    <?= htmlspecialchars($label) ?>
                                </a>
                            <?php else: ?>
                                <span class="phase-tab disabled"><?= htmlspecialchars($label) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($isSubphase && $activeSubphaseNode): ?>
                        <section class="phase-tab-panel">
                            <div class="phase-tab-panel__header">
                                <div>
                                    <p class="eyebrow">Subfase activa</p>
                                    <h4><?= htmlspecialchars($activeTabLabel) ?> · <?= htmlspecialchars($activeSubphaseName) ?></h4>
                                    <small class="section-muted"><?= htmlspecialchars($activeTabDescription ?: 'Documentación y control ISO 9001 para esta subfase.') ?></small>
                                </div>
                            </div>
                            <?php
                            $documentFlowId = 'document-flow-' . (int) ($activeSubphaseNode['id'] ?? 0);
                            $documentNode = $activeSubphaseNode;
                            $documentExpectedDocs = $expectedDocsForSubphase;
                            $documentTagOptions = $documentFlowTagOptions;
                            $documentKeyTags = $expectedDocsForSubphase;
                            $documentCanManage = $canManage;
                            $documentMode = $activeSubphaseSuffix;
                            $documentProjectId = (int) ($project['id'] ?? 0);
                            $documentBasePath = $basePath;
                            $documentCurrentUser = $currentUser;
                            require __DIR__ . '/document_flow.php';
                            ?>
                        </section>
                    <?php else: ?>
                        <section class="phase-tab-panel">
                            <h4>Gestión documental por subfase</h4>
                            <p class="section-muted">Esta fase aún no tiene subfases configuradas. Selecciona otra fase para continuar.</p>
                            <p class="phase-warning">No se permiten archivos sueltos fuera de subfases.</p>
                            <button class="action-btn primary" type="button" disabled>Subir documento (deshabilitado)</button>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>
</section>

<?php if ($canUpdateProgress): ?>

    <div class="progress-modal" id="health-modal" aria-hidden="true">
        <div class="modal__backdrop" data-close-modal></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="health-modal-title">
            <div class="modal__header">
                <h3 id="health-modal-title">Análisis completo de Salud Integral</h3>
                <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">✕</button>
            </div>
            <div class="modal__body">
                <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap;">
                    <svg width="120" height="120" viewBox="0 0 120 120" aria-label="Score general">
                        <circle cx="60" cy="60" r="50" stroke="var(--border)" stroke-width="12" fill="none"></circle>
                        <circle cx="60" cy="60" r="50" stroke="var(--primary)" stroke-width="12" fill="none" stroke-linecap="round" stroke-dasharray="314" stroke-dashoffset="<?= 314 - (314 * $healthTotal / 100) ?>" transform="rotate(-90 60 60)"></circle>
                        <text x="60" y="66" text-anchor="middle" font-size="24" font-weight="700"><?= $healthTotal ?></text>
                    </svg>
                    <div>
                        <p style="margin:0;"><strong><?= htmlspecialchars($healthLabel) ?></strong></p>
                        <p class="muted" style="margin:0;">Nivel: <?= htmlspecialchars($healthLevel) ?></p>
                    </div>
                </div>
                <div>
                    <?php foreach ($healthBreakdown as $dimension => $item): ?>
                        <?php $p = (int) ($item['percentage'] ?? 0); ?>
                        <div style="margin-bottom:8px;">
                            <div style="display:flex;justify-content:space-between;"><span><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $dimension))) ?></span><strong><?= $p ?>%</strong></div>
                            <div class="project-progress__bar"><div style="width: <?= $p ?>%;"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <h4>Recomendaciones</h4>
                    <ul>
                        <?php foreach ($healthRecommendations as $recommendation): ?>
                            <li><?= htmlspecialchars((string) $recommendation) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h4>Tendencia últimos 30 días</h4>
                    <p class="muted"><?= count($healthHistory) ?> snapshots registrados.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="progress-modal" id="progress-modal" aria-hidden="true">
        <div class="modal__backdrop" data-close-modal></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="progress-modal-title">
            <div class="modal__header">
                <h3 id="progress-modal-title">Actualizar avance</h3>
                <button type="button" class="icon-btn" data-close-modal aria-label="Cerrar">✕</button>
            </div>
            <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/progress" class="modal__body">
                <label class="modal__field">
                    <span>Nuevo porcentaje (0–100)</span>
                    <input type="number" name="progress" min="0" max="100" step="0.1" required>
                </label>
                <label class="modal__field">
                    <span>Justificación</span>
                    <textarea name="justification" rows="4" required></textarea>
                </label>
                <div class="modal__actions">
                    <button type="button" class="action-btn" data-close-modal>Cancelar</button>
                    <button type="submit" class="action-btn primary">Guardar avance</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    const progressModal = document.getElementById('progress-modal');
    const healthModal = document.getElementById('health-modal');
    const openHealthButtons = document.querySelectorAll('[data-open-modal="health-modal"]');
    const closeHealthButtons = healthModal ? healthModal.querySelectorAll('[data-close-modal]') : [];
    const healthPopoverRoot = document.querySelector('[data-health-popover-root]');
    const healthPopover = document.querySelector('[data-health-popover]');
    const toggleHealthPopover = document.querySelector('[data-toggle-health-popover]');
    const openProgressButtons = document.querySelectorAll('[data-open-modal="progress-modal"]');
    const closeProgressButtons = progressModal ? progressModal.querySelectorAll('[data-close-modal]') : [];
    const requiredDocumentButtons = document.querySelectorAll('[data-required-document-action]');

    requiredDocumentButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const documentFlowRoot = document.querySelector('[data-document-flow]');
            if (!documentFlowRoot) {
                window.alert('Selecciona una subfase para registrar el documento obligatorio.');
                return;
            }
            documentFlowRoot.dispatchEvent(new CustomEvent('required-document:open', {
                bubbles: false,
                detail: {
                    key: button.dataset.requiredDocKey || '',
                    name: button.dataset.requiredDocName || '',
                    documentType: button.dataset.requiredDocType || '',
                    tag: button.dataset.requiredDocTag || '',
                    isGit: button.dataset.requiredDocGit === '1',
                    repositoryUrl: button.dataset.requiredDocUrl || '',
                },
            }));
        });
    });

    const toggleProgressModal = (open) => {
        if (!progressModal) return;
        progressModal.classList.toggle('is-visible', open);
        progressModal.setAttribute('aria-hidden', open ? 'false' : 'true');
    };

    openProgressButtons.forEach((button) => {
        button.addEventListener('click', () => {
            console.log('Click en Actualizar avance: handler activo.');
            toggleProgressModal(true);
        });
    });

    closeProgressButtons.forEach((button) => {
        button.addEventListener('click', () => toggleProgressModal(false));
    });

    const progressForm = progressModal ? progressModal.querySelector('form') : null;
    if (progressForm) {
        progressForm.addEventListener('submit', () => {
            console.log('Click en Guardar avance: handler activo.');
        });
    }

    if (healthModal) {
        openHealthButtons.forEach((button) => button.addEventListener('click', () => {
            healthModal.classList.add('is-visible');
            healthModal.setAttribute('aria-hidden', 'false');
        }));
        closeHealthButtons.forEach((button) => button.addEventListener('click', () => {
            healthModal.classList.remove('is-visible');
            healthModal.setAttribute('aria-hidden', 'true');
        }));
    }

    if (toggleHealthPopover && healthPopover) {
        toggleHealthPopover.addEventListener('click', () => {
            healthPopover.classList.toggle('is-visible');
        });
        document.addEventListener('click', (event) => {
            if (healthPopoverRoot && !healthPopoverRoot.contains(event.target)) {
                healthPopover.classList.remove('is-visible');
            }
        });
    }

    if (progressModal) {
        progressModal.addEventListener('click', (event) => {
            if (event.target === progressModal) {
                toggleProgressModal(false);
            }
        });
    }
</script>

<style>
    .project-shell { display:flex; flex-direction:column; gap:16px; }
    .project-inline-warning {
        border:1px solid color-mix(in srgb, var(--warning) 45%, var(--surface) 55%);
        background:color-mix(in srgb, var(--warning) 14%, var(--surface) 86%);
        color:var(--text-primary);
        border-radius:12px;
        padding:10px 14px;
        font-weight:600;
    }
    .project-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); }
    .project-health-card { min-width: 180px; padding: 12px 14px; border-radius: 14px; border: 1px solid var(--border); background: color-mix(in srgb, var(--surface) 90%, var(--background)); }
    .project-health-score { font-size: 24px; font-weight: 800; }
    .project-health-title { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-secondary); }
    .project-health-state { font-size: 13px; font-weight: 700; margin-top: 6px; }
    .project-health-card.health-green { border-color: color-mix(in srgb, var(--success) 40%, var(--border)); }
    .project-health-card.health-yellow { border-color: color-mix(in srgb, var(--warning) 45%, var(--border)); }
    .project-health-card.health-red { border-color: color-mix(in srgb, var(--danger) 45%, var(--border)); }
    .health-popover { display:none; position:absolute; margin-top:8px; right:0; width:min(420px, 90vw); padding:12px; border-radius:12px; background:var(--surface); border:1px solid var(--border); box-shadow:0 10px 24px color-mix(in srgb, var(--text-primary) 20%, var(--background)); z-index:3; }
    .health-popover.is-visible { display:block; }
    .project-health-card { position:relative; }
    .health-dimension { font-size:12px; margin:8px 0; padding:6px 8px; border-radius:8px; }
    .dim-green { background: color-mix(in srgb, var(--success) 12%, var(--background)); }
    .dim-yellow { background: color-mix(in srgb, var(--warning) 12%, var(--background)); }
    .dim-red { background: color-mix(in srgb, var(--danger) 12%, var(--background)); }
    .project-title-block { display:flex; flex-direction:column; gap:8px; }
    .project-title-block h2 { margin:0; color: var(--text-primary); }
    .project-client-identity { display:flex; align-items:center; gap:10px; }
    .project-client-logo,
    .project-client-avatar {
        width:40px;
        height:40px;
        border-radius:10px;
        border:1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--background));
        display:inline-flex;
        align-items:center;
        justify-content:center;
    }
    .project-client-logo { object-fit: contain; padding: 6px; }
    .project-client-avatar { font-weight: 800; color: var(--primary); }
    .project-client-identity strong { display:block; color: var(--text-primary); }
    .project-client-identity small { color: var(--text-secondary); font-size: 12px; }
    .project-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .danger-shortcut { margin-top:12px; border:1px solid color-mix(in srgb, var(--danger) 30%, var(--background)); background: color-mix(in srgb, var(--danger) 10%, var(--surface) 90%); border-radius:12px; padding:12px; display:flex; gap:10px; align-items:center; justify-content:space-between; flex-wrap:wrap; }
    .danger-shortcut p { margin:0; color: var(--text-primary); font-weight:600; }
    .project-badges { display:flex; gap:8px; flex-wrap:wrap; }
    .pill.neutral { background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); border-color: var(--border); color: var(--text-primary); }
    .summary-layout { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; }
    .info-card { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:14px; }
    .info-list { display:grid; gap:10px; }
    .info-list span { font-size:12px; text-transform:uppercase; color: var(--text-secondary); font-weight:700; }
    .info-list strong { font-size:15px; color: var(--text-primary); }
    .progress-card { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:12px; }
    .progress-card__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .action-btn { background: var(--surface); color: var(--text-primary); border:1px solid var(--border); border-radius:8px; padding:8px 10px; cursor:pointer; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
    .action-btn.primary { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .action-btn.danger { background: color-mix(in srgb, var(--danger) 12%, var(--background)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--background)); }
    .action-btn.small { padding:6px 8px; font-size:13px; }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-weight:700; }
    .pill.methodology { border:1px solid var(--background); }
    .pill.methodology.scrum { background: color-mix(in srgb, var(--info) 18%, var(--background)); color: var(--info); border-color: color-mix(in srgb, var(--info) 40%, var(--background)); }
    .pill.methodology.traditional { background: color-mix(in srgb, var(--primary) 18%, var(--background)); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 40%, var(--background)); }
    .folder-meta { display:flex; gap:8px; align-items:center; margin-top:6px; flex-wrap:wrap; }
    .status-badge { font-size:12px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid var(--background); }
    .status-muted { background: color-mix(in srgb, var(--text-secondary) 14%, var(--background)); color: var(--text-primary); border-color: var(--border); }
    .status-info { background: color-mix(in srgb, var(--primary) 14%, var(--background)); color: var(--primary); border-color: color-mix(in srgb, var(--primary) 30%, var(--background)); }
    .status-success { background: color-mix(in srgb, var(--success) 16%, var(--background)); color: var(--success); border-color: color-mix(in srgb, var(--success) 32%, var(--background)); }
    .status-warning { background: color-mix(in srgb, var(--warning) 16%, var(--background)); color: var(--warning); border-color: color-mix(in srgb, var(--warning) 32%, var(--background)); }
    .status-danger { background: color-mix(in srgb, var(--danger) 16%, var(--background)); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 32%, var(--background)); }
    .count-pill { font-size:12px; font-weight:700; color: var(--text-primary); background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); border:1px solid var(--border); border-radius:999px; padding:4px 8px; }
    .breadcrumb { display:flex; flex-wrap:wrap; gap:6px; align-items:center; font-size:13px; color: var(--text-secondary); margin-bottom:8px; }
    .breadcrumb a { color: var(--text-primary); text-decoration:none; font-weight:600; }
    .breadcrumb span { color: var(--text-secondary); }
    .project-progress { display:flex; flex-direction:column; gap:4px; max-width:320px; }
    .project-progress__label { font-size:12px; text-transform:uppercase; color: var(--text-secondary); font-weight:700; }
    .project-progress__bar { background: color-mix(in srgb, var(--text-secondary) 22%, var(--background)); border-radius:999px; overflow:hidden; height:8px; }
    .project-progress__bar div { height:100%; background: var(--primary); border-radius:999px; }
    .project-progress__value { font-size:12px; color: var(--text-secondary); }
    .pmo-kpis { display:grid; grid-template-columns:repeat(4,minmax(120px,1fr)); gap:8px; }
    .pmo-kpi { border:1px solid var(--border); border-radius:10px; padding:8px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); }
    .pmo-kpi span { display:block; font-size:11px; color: var(--text-secondary); text-transform:uppercase; }
    .pmo-kpi strong { font-size:18px; color: var(--text-primary); }
    .pmo-kpi strong.status-red { color: var(--danger); }
    .pmo-kpi strong.status-yellow { color: var(--warning); }
    .pmo-kpi strong.status-green { color: var(--success); }
    .pmo-grid { display:grid; grid-template-columns:repeat(3,minmax(220px,1fr)); gap:12px; }
    .progress-meta { display:grid; gap:8px; width:100%; }
    .progress-meta__item { display:flex; flex-direction:column; gap:2px; font-size:12px; color: var(--text-secondary); }
    .progress-meta__item.full { grid-column: 1 / -1; }
    .progress-meta__item strong { font-size:13px; color: var(--text-primary); }
    .progress-meta__item.full p { margin:0; font-size:13px; color: var(--text-primary); }
    .indicator-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
    .indicator-card { border:1px solid var(--border); border-radius:14px; padding:12px; background: var(--surface); display:flex; align-items:center; gap:12px; }
    .indicator-card span { font-size:12px; text-transform:uppercase; color: var(--text-secondary); font-weight:700; }
    .indicator-card strong { font-size:18px; color: var(--text-primary); display:block; }
    .indicator-card small { font-size:12px; color: var(--text-secondary); }
    .indicator-icon { width:36px; height:36px; border-radius:10px; background: color-mix(in srgb, var(--primary) 12%, var(--background)); display:inline-flex; align-items:center; justify-content:center; }
    .progress-history { display:flex; }
    .history-card { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:12px; width:100%; }
    .history-header { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .required-documents-block { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:14px; margin-bottom:14px; }
    .required-documents-block__header h3 { margin:4px 0; }
    .required-documents-progress { display:flex; flex-direction:column; gap:6px; }
    .required-documents-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
    .required-doc-card { border:1px solid var(--border); border-radius:14px; padding:12px; display:flex; flex-direction:column; gap:10px; background: color-mix(in srgb, var(--surface) 92%, var(--background)); }
    .required-doc-card__top { display:flex; justify-content:space-between; align-items:center; }
    .required-doc-card__icon { font-size:20px; }
    .required-doc-card__body strong { display:block; margin-bottom:4px; color: var(--text-primary); }
    .required-doc-card__body small { color: var(--text-secondary); }
    .required-doc-card__actions { display:flex; flex-direction:column; gap:8px; }
    .required-doc-card__meta { display:flex; flex-direction:column; gap:4px; }
    .required-doc-card__meta span,
    .required-doc-card__meta a { font-size:12px; color: var(--text-primary); word-break:break-word; }
    .required-doc-card__meta small { font-size:11px; color: var(--text-secondary); }
    .required-documents-divider { height:1px; background: var(--border); margin: 2px 0 16px; }
    .project-layout { display:grid; grid-template-columns: 280px 1fr; gap:16px; }
    .phase-sidebar { border:1px solid var(--border); border-radius:16px; padding:14px; background: color-mix(in srgb, var(--text-secondary) 8%, var(--background)); display:flex; flex-direction:column; gap:12px; max-height:72vh; overflow:auto; }
    .phase-sidebar__header { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; }
    .phase-list, .phase-sublist { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:10px; }
    .phase-item { display:flex; flex-direction:column; gap:6px; }
    .phase-link { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:10px; border-radius:12px; text-decoration:none; color: var(--text-primary); border:1px solid var(--background); background: var(--surface); }
    .phase-link:hover { border-color: var(--border); }
    .phase-link.active { background: var(--secondary); color: var(--text-primary); border-color: var(--secondary); font-weight:700; }
    .phase-link__title { display:flex; gap:10px; align-items:center; }
    .phase-icon { font-size:18px; }
    .phase-doc-badge {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        white-space:nowrap;
        font-size:11px;
        font-weight:600;
        color: var(--text-secondary);
        background: color-mix(in srgb, var(--text-secondary) 11%, var(--background));
        border:1px solid color-mix(in srgb, var(--text-secondary) 18%, var(--background));
        border-radius:999px;
        padding:3px 8px;
    }
    .phase-group { border:1px solid var(--border); border-radius:12px; padding:8px; background: var(--surface); }
    .phase-group summary { list-style:none; cursor:pointer; }
    .phase-group summary::-webkit-details-marker { display:none; }
    .phase-sublist { margin-top:8px; padding-left:0; }
    .phase-panel { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); min-height:70vh; display:flex; flex-direction:column; gap:16px; }
    .phase-panel__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; border-bottom:1px solid var(--border); padding-bottom:12px; }
    .phase-panel__header h3 { margin:0; }
    .phase-meta { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .phase-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
    .phase-tabs { display:flex; flex-wrap:wrap; gap:8px; border-bottom:1px solid var(--border); padding-bottom:8px; }
    .phase-tab { padding:8px 12px; border-radius:999px; border:1px solid var(--border); text-decoration:none; color: var(--text-primary); font-weight:700; font-size:13px; background: color-mix(in srgb, var(--text-secondary) 10%, var(--background)); }
    .phase-tab.active { background: var(--primary); color: var(--text-primary); border-color: var(--primary); }
    .phase-tab.disabled { opacity:0.5; cursor:not-allowed; }
    .phase-tab-panel { display:flex; flex-direction:column; gap:12px; }
    .phase-tab-panel__header { border:1px solid var(--border); border-radius:12px; padding:12px; background: color-mix(in srgb, var(--text-secondary) 12%, var(--background)); }
    .stoppers-analytics { box-shadow: 0 10px 24px color-mix(in srgb, var(--text-primary) 10%, transparent); }
    .analytics-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:14px; }
    .chart-panel { border:1px solid var(--border); border-radius:14px; padding:14px; background: var(--surface); box-shadow: 0 8px 16px color-mix(in srgb, var(--text-primary) 8%, transparent); }
    .chart-panel h4 { margin:0 0 12px; }
    .impact-bars { display:flex; flex-direction:column; gap:10px; }
    .impact-bar { display:flex; flex-direction:column; gap:6px; }
    .impact-bar__label { display:flex; justify-content:space-between; gap:8px; font-weight:700; color:var(--text-primary); }
    .impact-bar__track { height:10px; border-radius:999px; background: color-mix(in srgb, var(--text-secondary) 18%, var(--background)); overflow:hidden; }
    .impact-bar__track div { height:100%; border-radius:999px; }
    .impact-bar--critical .impact-bar__track div { background:#dc2626; }
    .impact-bar--high .impact-bar__track div { background:#f97316; }
    .impact-bar--medium .impact-bar__track div { background:#facc15; }
    .impact-bar--low .impact-bar__track div { background:#16a34a; }
    .trend-chart { width:100%; height:auto; }
    .trend-chart .trend-line { fill:none; stroke:#2563eb; stroke-width:3; }
    .trend-chart circle { fill:#2563eb; }
    .trend-chart text { fill:var(--text-secondary); font-size:11px; }
    .donut-panel { display:flex; flex-direction:column; align-items:center; }
    .donut-track, .donut-open { fill:none; stroke-width:14; transform:rotate(-90deg); transform-origin:70px 70px; }
    .donut-track { stroke: color-mix(in srgb, var(--text-secondary) 25%, var(--background)); }
    .donut-open { stroke:#2563eb; stroke-linecap:round; }
    .donut-value { font-size:22px; font-weight:700; fill:var(--text-primary); }
    .donut-label { font-size:12px; fill:var(--text-secondary); }
    .donut-legend { display:flex; flex-direction:column; gap:6px; width:100%; }
    .donut-legend span { display:flex; align-items:center; gap:8px; color:var(--text-primary); font-weight:600; }
    .donut-legend i { width:10px; height:10px; border-radius:999px; display:inline-block; }
    .legend-open { background:#2563eb; }
    .legend-closed { background:#64748b; }
    .impact-cards-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin:14px 0; }
    .impact-card { border-radius:14px; padding:14px; box-shadow:0 8px 18px color-mix(in srgb, var(--text-primary) 10%, transparent); }
    .impact-card__title { display:flex; align-items:center; justify-content:space-between; font-size:15px; gap:8px; }
    .impact-card__value { font-size:32px; font-weight:800; line-height:1; margin:10px 0; }
    .impact-card p { margin:0; font-weight:500; }
    .impact-card--critical { background:#b91c1c; color:#fff; }
    .impact-card--high { background:#fb923c; color:#1f2937; }
    .impact-card--medium { background:#fde047; color:#1f2937; }
    .impact-card--low { background:#4ade80; color:#14532d; }
    .impact-card strong { color:inherit; }
    .impact-trend { font-size:18px; font-weight:800; }
    .kanban-block { box-shadow:0 10px 24px color-mix(in srgb, var(--text-primary) 10%, transparent); }
    .kanban-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:12px; }
    .kanban-column { border-radius:14px; padding:12px; border:1px solid var(--border); min-height:180px; display:flex; flex-direction:column; gap:10px; }
    .kanban-column header { display:flex; justify-content:space-between; align-items:center; font-weight:700; }
    .kanban-column--critical { background: color-mix(in srgb, #b91c1c 16%, var(--surface)); }
    .kanban-column--high { background: color-mix(in srgb, #fb923c 20%, var(--surface)); }
    .kanban-column--medium { background: color-mix(in srgb, #fde047 28%, var(--surface)); }
    .kanban-column--low { background: color-mix(in srgb, #4ade80 20%, var(--surface)); }
    .kanban-item { background:var(--surface); border:1px solid color-mix(in srgb, var(--text-secondary) 18%, var(--background)); border-radius:12px; padding:10px; box-shadow:0 4px 10px color-mix(in srgb, var(--text-primary) 8%, transparent); }
    .kanban-item h5 { margin:0 0 6px; color:var(--text-primary); font-size:14px; }
    .kanban-item p { margin:2px 0; color:var(--text-primary); font-size:13px; }
    .kanban-item .badge { margin-top:6px; display:inline-flex; background:#e2e8f0; color:#0f172a; }
    .kanban-empty { color:var(--text-primary); font-weight:600; opacity:0.75; margin:0; }
    .phase-warning { color: var(--danger); margin:0; }
    .progress-modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:50; }
    .progress-modal.is-visible { display:flex; }
    .progress-modal .modal__backdrop { position:absolute; inset:0; background: color-mix(in srgb, var(--text-primary) 45%, var(--background)); }
    .progress-modal .modal__panel { position:relative; background: var(--surface); border-radius:16px; padding:16px; width:min(520px, 90vw); box-shadow:0 20px 40px color-mix(in srgb, var(--text-primary) 25%, var(--background)); display:flex; flex-direction:column; gap:12px; z-index:1; }
    .modal__header { display:flex; justify-content:space-between; align-items:center; }
    .modal__header h3 { margin:0; }
    .modal__body { display:flex; flex-direction:column; gap:12px; }
    .modal__field { display:flex; flex-direction:column; gap:6px; font-weight:600; color: var(--text-primary); }
    .modal__field input,
    .modal__field textarea { padding:10px 12px; border-radius:10px; border:1px solid var(--border); }
    .modal__actions { display:flex; justify-content:flex-end; gap:8px; }
    .icon-btn { border:none; background:var(--background); cursor:pointer; font-size:16px; }
    .notes-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; }
    .notes-card { border:1px solid var(--border); border-radius:16px; padding:16px; background: var(--surface); display:flex; flex-direction:column; gap:14px; }
    .notes-card__header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .notes-form { display:flex; flex-direction:column; gap:12px; }
    .notes-field { display:flex; flex-direction:column; gap:6px; font-size:13px; color: var(--text-secondary); }
    .notes-field textarea { width:100%; border-radius:12px; border:1px solid var(--border); padding:10px; font-size:14px; color: var(--text-primary); background: var(--surface); }
    .notes-actions { display:flex; justify-content:flex-end; }
    .notes-timeline { display:flex; flex-direction:column; gap:12px; }
    .notes-entry { padding:12px; border-radius:12px; border:1px solid var(--border); background: color-mix(in srgb, var(--text-secondary) 8%, var(--background)); display:flex; flex-direction:column; gap:8px; }
    .notes-entry strong { color: var(--text-primary); }
    @media (max-width: 960px) {
        .required-documents-grid { grid-template-columns: 1fr; }
        .project-layout { grid-template-columns: 1fr; }
        .phase-sidebar { max-height:none; }
    }
</style>
