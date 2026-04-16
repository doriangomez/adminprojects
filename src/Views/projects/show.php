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
$scheduleActivities = is_array($scheduleActivities ?? null) ? $scheduleActivities : [];
$scheduleSummary = is_array($scheduleSummary ?? null) ? $scheduleSummary : [];
$scheduleCreatedBy = isset($scheduleCreatedBy) ? (int) $scheduleCreatedBy : null;
$scheduleCreatedAt = $scheduleCreatedAt ?? null;
$tasksForSchedule = is_array($tasksForSchedule ?? null) ? $tasksForSchedule : [];
$view = $_GET['view'] ?? 'documentos';
$returnUrl = $_GET['return'] ?? ($basePath . '/projects');
$view = in_array($view, ['resumen', 'documentos', 'cronograma', 'seguimiento', 'bloqueos', 'horas'], true) ? $view : 'documentos';

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
$formatDateShort = static function (?string $value): string {
    if (!$value) {
        return '-';
    }
    $timestamp = strtotime($value);
    if (!$timestamp) {
        return '-';
    }
    return date('d/m/Y', $timestamp);
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
$requiredDocumentsFilesCode = '99-REQDOCS-FILES';
$requiredDocuments = [
    ['key' => 'propuesta_aceptada', 'name' => 'Propuesta aceptada por el cliente', 'description' => 'Versión final aprobada de la propuesta comercial y alcance.', 'icon' => '📄', 'document_type' => 'Propuesta comercial', 'tag' => 'Propuesta comercial', 'match_tags' => ['Propuesta comercial', 'Propuesta aceptada por el cliente']],
    ['key' => 'contrato', 'name' => 'Contrato', 'description' => 'Documento legal del acuerdo con el cliente.', 'icon' => '📑', 'document_type' => 'Contrato', 'tag' => 'Contrato', 'match_types' => ['Contrato', 'Documento legal'], 'match_tags' => ['Contrato']],
    ['key' => 'acuerdo_confidencialidad', 'name' => 'Acuerdo de confidencialidad', 'description' => 'NDA firmado entre las partes para proteger información sensible.', 'icon' => '🔐', 'document_type' => 'NDA', 'tag' => 'Confidencialidad', 'match_types' => ['NDA', 'Acuerdo de confidencialidad'], 'match_tags' => ['NDA', 'Confidencialidad', 'Acuerdo de confidencialidad']],
    ['key' => 'presupuesto', 'name' => 'Presupuesto', 'description' => 'Documento formal del presupuesto aprobado.', 'icon' => '💵', 'document_type' => 'Presupuesto', 'tag' => 'Presupuesto', 'match_types' => ['Presupuesto'], 'match_tags' => ['Presupuesto']],
    ['key' => 'acta_inicio', 'name' => 'Acta de inicio de proyecto', 'description' => 'Documento formal de arranque y compromiso del proyecto.', 'icon' => '📝', 'document_type' => 'Acta', 'tag' => 'Acta de inicio', 'match_tags' => ['Acta de inicio', 'Acta de inicio de proyecto']],
    ['key' => 'kickoff', 'name' => 'Kickoff', 'description' => 'Acta o presentación de la reunión de inicio con stakeholders.', 'icon' => '🚀', 'document_type' => 'Kickoff', 'tag' => 'Kickoff'],
    ['key' => 'actas_seguimiento', 'name' => 'Actas de seguimiento', 'description' => 'Evidencias de acuerdos y seguimiento periódico del proyecto.', 'icon' => '🗒️', 'document_type' => 'Acta', 'tag' => 'Seguimiento', 'match_tags' => ['Seguimiento', 'Actas de seguimiento']],
    ['key' => 'cronograma', 'name' => 'Cronograma de trabajo', 'description' => 'Plan temporal de entregables, hitos y responsables.', 'icon' => '📆', 'document_type' => 'Cronograma', 'tag' => 'Cronograma de trabajo'],
    ['key' => 'pruebas_funcionales', 'name' => 'Pruebas funcionales con el cliente', 'description' => 'Resultados y conformidad de pruebas funcionales con cliente.', 'icon' => '✅', 'document_type' => 'Acta', 'tag' => 'Pruebas funcionales', 'match_tags' => ['Pruebas funcionales', 'Pruebas funcionales con el cliente (acta)']],
    ['key' => 'acta_cierre', 'name' => 'Acta de cierre', 'description' => 'Cierre formal del proyecto y aceptación final.', 'icon' => '🏁', 'document_type' => 'Acta', 'tag' => 'Cierre', 'match_tags' => ['Cierre', 'Acta de cierre']],
    ['key' => 'lecciones_aprendidas', 'name' => 'Lecciones aprendidas', 'description' => 'Resumen de hallazgos para mejora continua.', 'icon' => '📚', 'document_type' => 'Informe', 'tag' => 'Lecciones aprendidas', 'match_types' => ['Informe', 'Lecciones aprendidas']],
    ['key' => 'diagrama_flujo', 'name' => 'Diagrama de flujo', 'description' => 'Representación visual del flujo operativo o funcional.', 'icon' => '🔀', 'document_type' => 'Diagrama', 'tag' => 'Flujo', 'match_tags' => ['Flujo', 'Diagrama de flujo']],
    ['key' => 'diagrama_arquitectura', 'name' => 'Diagrama de arquitectura', 'description' => 'Vista estructural de componentes y su interacción.', 'icon' => '🏗️', 'document_type' => 'Diagrama', 'tag' => 'Arquitectura', 'match_tags' => ['Arquitectura', 'Diagrama de arquitectura']],
    ['key' => 'documento_arquitectura', 'name' => 'Documento de arquitectura', 'description' => 'Documento técnico con decisiones de arquitectura.', 'icon' => '🧱', 'document_type' => 'Documento técnico', 'tag' => 'Arquitectura', 'match_types' => ['Documento técnico', 'Arquitectura'], 'match_tags' => ['Arquitectura', 'Documento de arquitectura']],
    ['key' => 'repositorio_git', 'name' => 'Repositorio Git', 'description' => 'URL oficial del repositorio de código fuente del proyecto.', 'icon' => '🔗', 'document_type' => 'Repositorio', 'tag' => 'Repositorio Git', 'is_git' => true],
];

$requiredDocumentsFolderNode = null;
foreach ($allNodes as $node) {
    if (($node['code'] ?? '') !== $requiredDocumentsFilesCode || ($node['node_type'] ?? '') !== 'folder') {
        continue;
    }
    $requiredDocumentsFolderNode = $node;
    break;
}
$requiredDocumentsFolderFiles = is_array($requiredDocumentsFolderNode['files'] ?? null) ? $requiredDocumentsFolderNode['files'] : [];
$requiredDocumentsFilesByCode = [];
foreach ($requiredDocumentsFolderFiles as $file) {
    $fileCode = (string) ($file['code'] ?? '');
    if ($fileCode === '') {
        continue;
    }
    $requiredDocumentsFilesByCode[$fileCode] = $file;
}

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
$contractEndDate = trim((string) ($requiredDocumentsMeta['contract_end_date'] ?? ''));

$requiredDocumentsCards = [];
foreach ($requiredDocuments as $requiredDocument) {
    if (($requiredDocument['key'] ?? '') === 'cronograma') {
        $hasSchedule = !empty($scheduleActivities);
        $requiredDocumentsCards[] = array_merge($requiredDocument, [
            'completed' => $hasSchedule,
            'record' => $hasSchedule
                ? [
                    'file_name' => count($scheduleActivities) . ' actividades registradas',
                    'created_at' => $scheduleCreatedAt ?: ($scheduleActivities[0]['created_at'] ?? $scheduleActivities[0]['updated_at'] ?? null),
                    'created_by' => $scheduleCreatedBy,
                ]
                : null,
            'is_schedule' => true,
        ]);
        continue;
    }
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

    $fileCode = $requiredDocumentsFilesCode . '-FILE-' . strtoupper((string) ($requiredDocument['key'] ?? ''));
    $latest = is_array($requiredDocumentsFilesByCode[$fileCode] ?? null) ? $requiredDocumentsFilesByCode[$fileCode] : null;
    if (($requiredDocument['key'] ?? '') === 'contrato' && $latest !== null) {
        $latest['contract_end_date'] = $contractEndDate;
    }
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
            <a class="action-btn primary" href="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/executive-report.pdf">Exportar informe<br>gerencial</a>
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
        'cronograma' => 'cronograma',
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
    <?php elseif ($view === 'cronograma'): ?>
        <?php
        $hasSchedule = !empty($scheduleActivities);
        $talentNames = array_values(array_unique(array_filter(array_map(static fn (array $a): string => trim((string) ($a['talent_name'] ?? $a['name'] ?? '')), $assignments))));
        $zoom = $_GET['zoom'] ?? 'month';
        ?>
        <section class="schedule-shell" data-schedule-root data-project-id="<?= (int) ($project['id'] ?? 0) ?>">
            <?php if (!$hasSchedule): ?>
                <article class="schedule-empty">
                    <h3>Este proyecto aún no tiene cronograma. Puedes crearlo desde cero o importar un archivo Excel.</h3>
                    <div class="schedule-actions">
                        <button type="button" class="action-btn primary" data-schedule-action="create">Crear cronograma</button>
                        <button type="button" class="action-btn" data-schedule-action="import">Importar desde Excel</button>
                    </div>
                </article>
            <?php else: ?>
                <article class="schedule-summary-grid">
                    <div><span>Fecha de inicio</span><strong><?= htmlspecialchars((string) ($scheduleSummary['start_date'] ?? 'N/A')) ?></strong></div>
                    <div><span>Fecha de fin estimada</span><strong><?= htmlspecialchars((string) ($scheduleSummary['end_date'] ?? 'N/A')) ?></strong></div>
                    <div><span>Días transcurridos / total</span><strong><?= (int) ($scheduleSummary['days_elapsed'] ?? 0) ?> / <?= (int) ($scheduleSummary['days_total'] ?? 0) ?></strong></div>
                    <div><span>Avance general</span><strong><?= number_format((float) ($scheduleSummary['progress'] ?? 0), 1) ?>%</strong></div>
                    <div><span>Estados</span><strong>🔴 <?= (int) ($scheduleSummary['red'] ?? 0) ?> · 🟡 <?= (int) ($scheduleSummary['yellow'] ?? 0) ?> · 🟢 <?= (int) ($scheduleSummary['green'] ?? 0) ?></strong></div>
                    <div class="schedule-actions">
                        <button type="button" class="action-btn primary" data-schedule-action="create">Editar cronograma</button>
                        <button type="button" class="action-btn" data-schedule-action="import">Importar / Actualizar desde Excel</button>
                    </div>
                </article>
                <article class="gantt-wrapper" id="gantt-export-area">
                    <div class="gantt-controls">
                        <span>Zoom:</span>
                        <button class="action-btn small" data-zoom="week">Semanas</button>
                        <button class="action-btn small" data-zoom="month">Meses</button>
                        <button class="action-btn small" data-zoom="quarter">Trimestres</button>
                        <button class="action-btn small" data-export="png">Exportar</button>
                    </div>
                    <div class="gantt-grid" data-zoom="<?= htmlspecialchars((string) $zoom) ?>">
                        <div class="gantt-table">
                            <table>
                                <thead><tr><th>Nº</th><th>Nombre</th><th>Tipo</th><th>Responsable</th><th>Inicio</th><th>Fin</th><th>Avance</th><th>Estado</th><th>Acciones</th></tr></thead>
                                <tbody>
                                <?php foreach ($scheduleActivities as $i => $activity): ?>
                                    <tr>
                                        <td><?= (int) ($activity['sort_order'] ?? ($i + 1)) ?></td>
                                        <td><?= htmlspecialchars((string) ($activity['name'] ?? '')) ?></td>
                                        <td><?= (($activity['item_type'] ?? 'activity') === 'milestone') ? 'Hito' : 'Actividad' ?></td>
                                        <td><?= htmlspecialchars((string) ($activity['responsible_name'] ?? 'Sin asignar')) ?></td>
                                        <td><?= htmlspecialchars((string) ($activity['start_date'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($activity['end_date'] ?? '')) ?></td>
                                        <td><?= number_format((float) ($activity['progress_percent'] ?? 0), 0) ?>% <?= !empty($activity['progress_locked']) ? '🔒' : '' ?></td>
                                        <td><?= htmlspecialchars((string) ($activity['derived_status'] ?? 'green')) ?></td>
                                        <td>
                                            <button type="button" class="action-btn small" data-schedule-edit-row="<?= (int) $i ?>" title="Editar">✏️</button>
                                            <button type="button" class="action-btn small danger" data-schedule-delete-row="<?= (int) $i ?>" title="Eliminar">🗑️</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="gantt-bars" data-gantt-bars='<?= htmlspecialchars(json_encode($scheduleActivities, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>'></div>
                    </div>
                </article>
            <?php endif; ?>
            <article class="schedule-editor-panel" id="schedule-editor-panel" hidden>
                <form method="POST" action="<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/schedule/save" class="schedule-form">
                    <h3>Editor de cronograma</h3>
                    <p class="section-muted">Edita cada celda directamente. El avance se bloquea cuando hay tareas vinculadas.</p>
                    <datalist id="schedule-talents">
                        <?php foreach ($talentNames as $talentName): ?>
                            <option value="<?= htmlspecialchars((string) $talentName) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="table-wrapper schedule-editor-table-wrap">
                        <table class="schedule-editor-table">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Nombre de la actividad</th>
                                    <th>Tipo</th>
                                    <th>Fecha inicio</th>
                                    <th>Fecha fin</th>
                                    <th>Días</th>
                                    <th>Responsable</th>
                                    <th>Avance %</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="schedule-editor-rows" data-existing='<?= htmlspecialchars(json_encode($scheduleActivities, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>'></tbody>
                        </table>
                    </div>
                    <input type="hidden" name="activities_json" id="schedule-activities-json" />
                    <div class="schedule-editor-footer">
                        <button type="button" class="action-btn" data-schedule-add-row>＋ Agregar actividad</button>
                        <button type="submit" class="action-btn primary">Guardar cronograma</button>
                    </div>
                </form>
            </article>
        </section>
        <dialog id="schedule-import-modal">
            <form class="schedule-form" id="schedule-import-form" enctype="multipart/form-data">
                <h3>Importar cronograma desde Excel</h3>
                <p>Descarga plantilla con columnas: Nombre de la actividad, Tipo, Fecha inicio, Fecha fin, Responsable, Porcentaje de avance.</p>
                <a class="action-btn" download="plantilla_cronograma.csv" href="data:text/csv;charset=utf-8,Nombre%20de%20la%20actividad,Tipo,Fecha%20inicio,Fecha%20fin,Responsable,Porcentaje%20de%20avance%0A">Descargar plantilla</a>
                <input type="file" name="excel_file" accept=".xlsx,.csv" required />
                <div id="schedule-import-preview"></div>
                <div class="schedule-actions">
                    <button type="submit" class="action-btn primary">Cargar y previsualizar</button>
                </div>
            </form>
        </dialog>
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
        <?php $requiredDocCurrentUserName = trim((string) ($currentUser['name'] ?? $currentUser['full_name'] ?? $currentUser['email'] ?? 'Usuario')); ?>
        <section class="required-documents-block" data-required-documents-root data-required-doc-total="<?= (int) $requiredDocumentsTotal ?>" data-required-doc-current-user="<?= htmlspecialchars($requiredDocCurrentUserName) ?>">
            <div class="required-documents-block__header">
                <h3>Documentos obligatorios</h3>
                <div class="required-documents-block__summary">
                    <span data-required-doc-counter><?= $requiredDocumentsCompleted ?> / <?= $requiredDocumentsTotal ?> completados</span>
                    <div class="project-progress__bar required-documents-progress--compact" role="progressbar" aria-valuenow="<?= $requiredDocumentsProgress ?>" aria-valuemin="0" aria-valuemax="100" data-required-doc-progressbar>
                        <div style="width: <?= $requiredDocumentsProgress ?>%;" data-required-doc-progress-fill></div>
                    </div>
                </div>
            </div>
            <div class="required-documents-table-wrap">
                <table class="required-documents-table">
                    <thead>
                        <tr>
                            <th style="width:72px;">Estado</th>
                            <th>Documento</th>
                            <th style="width:180px;">Registrado por</th>
                            <th style="width:120px;">Fecha</th>
                            <th style="width:160px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requiredDocumentsCards as $requiredCard): ?>
                            <?php
                            $record = is_array($requiredCard['record'] ?? null) ? $requiredCard['record'] : null;
                            $isCompleted = !empty($requiredCard['completed']);
                            $isGitCard = !empty($requiredCard['is_git']);
                            $isScheduleCard = !empty($requiredCard['is_schedule']);
                            $recordedBy = '-';
                            if ($isCompleted && isset($record['created_by']) && (int) $record['created_by'] > 0) {
                                $recordedBy = $userNamesById[(int) $record['created_by']] ?? ('Usuario #' . (int) $record['created_by']);
                            }
                            $recordDate = $isCompleted ? $formatDateShort($record['created_at'] ?? null) : '-';
                            $documentDescription = (string) ($requiredCard['description'] ?? '');
                            $recordDescription = trim((string) ($record['description'] ?? ''));
                            $descriptionForDisplay = $recordDescription !== '' ? $recordDescription : $documentDescription;
                            $recordDocumentType = trim((string) ($record['document_type'] ?? ($requiredCard['document_type'] ?? '')));
                            $recordVersion = trim((string) ($record['version'] ?? ''));
                            $recordTags = array_values(array_filter(array_map(
                                static fn ($tag): string => trim((string) $tag),
                                is_array($record['tags'] ?? null) ? $record['tags'] : []
                            )));
                            if (empty($recordTags) && !empty($requiredCard['tag'])) {
                                $recordTags = [trim((string) $requiredCard['tag'])];
                            }
                            $repositoryUrl = (string) ($record['storage_path'] ?? '');
                            $recordFileId = isset($record['id']) ? (int) $record['id'] : 0;
                            $recordFileName = trim((string) ($record['file_name'] ?? ''));
                            $recordContractEndDate = trim((string) ($record['contract_end_date'] ?? ''));
                            $recordFileDownloadUrl = (!$isGitCard && $recordFileId > 0)
                                ? ($basePath . '/projects/' . (int) ($project['id'] ?? 0) . '/nodes/' . $recordFileId . '/download')
                                : '';
                            $hasRecordFile = $recordFileDownloadUrl !== '';
                            $actionLabel = $isScheduleCard
                                ? ($isCompleted ? 'Ver cronograma' : 'Crear cronograma')
                                : ($isGitCard
                                    ? ($isCompleted ? 'Editar' : 'Registrar')
                                    : ($isCompleted ? 'Ver / Editar' : 'Registrar'));
                            ?>
                            <tr
                                data-required-document-row
                                data-required-doc-key="<?= htmlspecialchars((string) ($requiredCard['key'] ?? '')) ?>"
                                data-required-doc-name="<?= htmlspecialchars((string) ($requiredCard['name'] ?? '')) ?>"
                                data-required-doc-type="<?= htmlspecialchars($recordDocumentType) ?>"
                                data-required-doc-tag="<?= htmlspecialchars((string) ($requiredCard['tag'] ?? '')) ?>"
                                data-required-doc-tags="<?= htmlspecialchars(implode('|', $recordTags)) ?>"
                                data-required-doc-version="<?= htmlspecialchars($recordVersion) ?>"
                                data-required-doc-git="<?= $isGitCard ? '1' : '0' ?>"
                                data-required-doc-schedule="<?= $isScheduleCard ? '1' : '0' ?>"
                                data-required-doc-completed="<?= $isCompleted ? '1' : '0' ?>"
                                data-required-doc-url="<?= htmlspecialchars($repositoryUrl) ?>"
                                data-required-doc-description="<?= htmlspecialchars($descriptionForDisplay) ?>"
                                data-required-doc-modal-description="<?= htmlspecialchars($descriptionForDisplay) ?>"
                                data-required-doc-file-id="<?= $recordFileId > 0 ? (string) $recordFileId : '' ?>"
                                data-required-doc-file-name="<?= htmlspecialchars($recordFileName) ?>"
                                data-required-doc-file-url="<?= htmlspecialchars($recordFileDownloadUrl) ?>"
                                data-required-doc-has-file="<?= $hasRecordFile ? '1' : '0' ?>"
                                data-required-doc-contract-end-date="<?= htmlspecialchars($recordContractEndDate) ?>"
                            >
                                <td>
                                    <span class="required-doc-state <?= $isCompleted ? 'required-doc-state--completed' : 'required-doc-state--pending' ?>" data-required-doc-state-icon aria-hidden="true"></span>
                                </td>
                                <td class="required-doc-table__document">
                                    <strong class="required-doc-name"><?= htmlspecialchars((string) ($requiredCard['name'] ?? 'Documento')) ?></strong>
                                    <?php if ($isGitCard && $isCompleted && $repositoryUrl !== ''): ?>
                                        <a class="required-doc-description required-doc-description--link" href="<?= htmlspecialchars($repositoryUrl) ?>" target="_blank" rel="noopener noreferrer" data-required-doc-description-node><?= htmlspecialchars($repositoryUrl) ?></a>
                                    <?php else: ?>
                                        <span class="required-doc-description" data-required-doc-description-node><?= htmlspecialchars($descriptionForDisplay) ?></span>
                                        <a
                                            class="required-doc-file-reference<?= $hasRecordFile ? '' : ' is-hidden' ?>"
                                            href="<?= htmlspecialchars($hasRecordFile ? $recordFileDownloadUrl : '#') ?>"
                                            data-required-doc-file-reference
                                            <?= $hasRecordFile ? 'download' : 'hidden' ?>
                                        >
                                            <?= htmlspecialchars($recordFileName !== '' ? $recordFileName : 'Archivo adjunto') ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><span data-required-doc-registered-by><?= htmlspecialchars($recordedBy) ?></span></td>
                                <td><span data-required-doc-date><?= htmlspecialchars($recordDate) ?></span></td>
                                <td>
                                    <button
                                        type="button"
                                        class="required-doc-action <?= $isCompleted ? 'required-doc-action--completed' : 'required-doc-action--pending' ?>"
                                        data-required-document-action
                                        data-required-doc-action-button
                                    >
                                        <?= htmlspecialchars($actionLabel) ?>
                                    </button>
                                </td>
                            </tr>
                            <?php if ($isGitCard): ?>
                                <tr class="required-doc-git-editor-row" data-required-doc-git-editor-row data-required-doc-key="<?= htmlspecialchars((string) ($requiredCard['key'] ?? '')) ?>">
                                    <td colspan="5">
                                        <div class="required-doc-git-editor" data-required-doc-git-editor>
                                            <div class="required-doc-git-editor__controls">
                                                <input
                                                    type="text"
                                                    value="<?= htmlspecialchars($repositoryUrl) ?>"
                                                    placeholder="Pega aquí la URL del repositorio (ej: https://github.com/...)"
                                                    data-required-doc-git-input
                                                >
                                                <button type="button" class="required-doc-action required-doc-action--save" data-required-doc-git-save>Guardar</button>
                                                <button type="button" class="required-doc-action required-doc-action--completed" data-required-doc-git-cancel>Cancelar</button>
                                            </div>
                                            <p class="required-doc-git-error" data-required-doc-git-error hidden>Ingresa una URL válida</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php if ($canManage): ?>
            <div class="modal" data-required-doc-modal hidden>
                <div class="modal-backdrop" data-required-doc-close></div>
                <div class="modal-panel">
                    <header>
                        <div>
                            <h4 data-required-doc-modal-title>Registrar documento obligatorio</h4>
                            <p class="section-muted">Completa los metadatos y sube el archivo.</p>
                        </div>
                        <button type="button" class="action-btn small" data-required-doc-close>✕</button>
                    </header>
                    <form method="POST" enctype="multipart/form-data" data-required-doc-upload-form>
                        <div class="form-validation" data-required-doc-upload-validation hidden>Revisa los campos obligatorios.</div>
                        <input type="hidden" name="required_document_key" data-required-doc-key-input>
                        <input type="hidden" name="document_type" value="" data-required-doc-document-type-hidden>
                        <input type="hidden" name="document_tags" value="" data-required-doc-upload-tags-hidden>
                        <input type="hidden" name="required_document_tag" value="" data-required-doc-tag-hidden>
                        <input type="hidden" name="remove_required_document_file" value="0" data-required-doc-remove-file-hidden>
                        <div class="required-doc-current-file" data-required-doc-current-file hidden>
                            <p class="required-doc-current-file__title">Archivo actual</p>
                            <div class="required-doc-current-file__actions">
                                <span data-required-doc-current-file-name></span>
                                <a class="action-btn small" href="#" data-required-doc-current-file-download target="_blank" rel="noopener noreferrer">Descargar</a>
                                <button type="button" class="action-btn small danger" data-required-doc-current-file-remove>Eliminar archivo</button>
                            </div>
                            <p class="section-muted" data-required-doc-current-file-removed-note hidden>El archivo actual se eliminará al guardar.</p>
                        </div>
                        <label class="field">
                            <span>Archivo *</span>
                            <input type="file" name="required_document_file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.bmp,.tiff">
                        </label>
                        <div class="upload-preview" data-required-doc-upload-preview hidden>
                            <strong>Archivo seleccionado:</strong>
                            <ul></ul>
                        </div>
                        <div class="field-grid">
                            <label class="field">
                                <span>Tipo documental *</span>
                                <select data-required-doc-document-type-select>
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($documentFlowTagOptions as $typeOption): ?>
                                        <option value="<?= htmlspecialchars($typeOption) ?>"><?= htmlspecialchars($typeOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" placeholder="Otro tipo" data-required-doc-document-type-custom>
                            </label>
                            <label class="field">
                                <span>Versión *</span>
                                <input type="text" name="document_version" placeholder="v1, v2, final" required>
                            </label>
                        </div>
                        <label class="field" data-required-doc-contract-end-date-field hidden>
                            <span>Fecha de finalización del contrato *</span>
                            <input type="date" name="contract_end_date" data-required-doc-contract-end-date-input>
                        </label>
                        <label class="field">
                            <span>Tags *</span>
                            <select multiple data-required-doc-upload-tag-select>
                                <?php foreach ($documentFlowTagOptions as $tagOption): ?>
                                    <option value="<?= htmlspecialchars($tagOption) ?>"><?= htmlspecialchars($tagOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" placeholder="Otros tags (separados por coma)" data-required-doc-upload-tag-custom>
                        </label>
                        <label class="field">
                            <span>Descripción corta *</span>
                            <textarea name="document_description" rows="3" placeholder="Describe el contenido del documento" required></textarea>
                        </label>
                        <div class="modal-actions">
                            <button type="button" class="action-btn" data-required-doc-close>Cancelar</button>
                            <button type="submit" class="action-btn primary" data-required-doc-upload-submit>
                                <span class="button-label">Guardar documento</span>
                                <span class="button-spinner" aria-hidden="true"></span>
                            </button>
                        </div>
                        <p class="section-muted">Si no seleccionas archivo en "Ver / Editar", solo se actualizarán los metadatos.</p>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <div class="required-documents-divider" aria-hidden="true"><span>Gestión documental por fases</span></div>
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
    const requiredDocumentsRoot = document.querySelector('[data-required-documents-root]');
    const requiredDocumentModal = document.querySelector('[data-required-doc-modal]');
    const requiredDocumentUploadForm = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-upload-form]') : null;
    const requiredDocumentModalCloseButtons = requiredDocumentModal ? requiredDocumentModal.querySelectorAll('[data-required-doc-close]') : [];
    const requiredDocumentModalValidation = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-upload-validation]') : null;
    const requiredDocumentUploadSubmitButton = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-upload-submit]') : null;
    const requiredDocumentTypeSelect = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-document-type-select]') : null;
    const requiredDocumentTypeCustom = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-document-type-custom]') : null;
    const requiredDocumentTagsSelect = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-upload-tag-select]') : null;
    const requiredDocumentTagsCustom = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-upload-tag-custom]') : null;
    const requiredDocumentPreview = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-upload-preview]') : null;
    const requiredDocumentInput = requiredDocumentModal ? requiredDocumentModal.querySelector('input[type="file"][name="required_document_file"]') : null;
    const requiredDocumentCurrentFile = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-current-file]') : null;
    const requiredDocumentCurrentFileName = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-current-file-name]') : null;
    const requiredDocumentCurrentFileDownload = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-current-file-download]') : null;
    const requiredDocumentCurrentFileRemoveButton = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-current-file-remove]') : null;
    const requiredDocumentCurrentFileRemovedNote = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-current-file-removed-note]') : null;
    const requiredDocumentRemoveFileHidden = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-remove-file-hidden]') : null;
    const requiredDocumentContractEndDateField = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-contract-end-date-field]') : null;
    const requiredDocumentContractEndDateInput = requiredDocumentModal ? requiredDocumentModal.querySelector('[data-required-doc-contract-end-date-input]') : null;
    let activeRequiredDocumentKey = '';
    let requiredDocumentRemoveFileRequested = false;
    const requiredDocumentTypePresetsByKey = {
        propuesta_aceptada: 'Propuesta comercial',
        contrato: 'Contrato',
        acuerdo_confidencialidad: 'NDA',
        presupuesto: 'Presupuesto',
        acta_inicio: 'Acta',
        kickoff: 'Kickoff',
        actas_seguimiento: 'Acta',
        pruebas_funcionales: 'Acta',
        acta_cierre: 'Acta',
        lecciones_aprendidas: 'Informe',
        diagrama_flujo: 'Diagrama',
        diagrama_arquitectura: 'Diagrama',
        documento_arquitectura: 'Documento técnico',
    };
    const getTodayDate = () => {
        const now = new Date();
        const day = String(now.getDate()).padStart(2, '0');
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const year = now.getFullYear();
        return `${day}/${month}/${year}`;
    };
    const selectorSafeValue = (value) => {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/["\\]/g, '\\$&');
    };

    const updateRequiredDocumentsSummary = () => {
        if (!requiredDocumentsRoot) return;
        const rows = Array.from(requiredDocumentsRoot.querySelectorAll('[data-required-document-row]'));
        const total = Number(requiredDocumentsRoot.dataset.requiredDocTotal || rows.length || 0);
        const completed = rows.filter((row) => row.dataset.requiredDocCompleted === '1').length;
        const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
        const counter = requiredDocumentsRoot.querySelector('[data-required-doc-counter]');
        const progressBar = requiredDocumentsRoot.querySelector('[data-required-doc-progressbar]');
        const progressFill = requiredDocumentsRoot.querySelector('[data-required-doc-progress-fill]');
        if (counter) {
            counter.textContent = `${completed} / ${total} completados`;
        }
        if (progressBar) {
            progressBar.setAttribute('aria-valuenow', String(percentage));
        }
        if (progressFill) {
            progressFill.style.width = `${percentage}%`;
        }
    };

    const setRequiredDocumentRowCompleted = (row, options = {}) => {
        if (!row) return;
        const actionButton = row.querySelector('[data-required-doc-action-button]');
        const stateIcon = row.querySelector('[data-required-doc-state-icon]');
        const registeredByNode = row.querySelector('[data-required-doc-registered-by]');
        const dateNode = row.querySelector('[data-required-doc-date]');
        const descriptionNode = row.querySelector('[data-required-doc-description-node]');
        const fileReferenceNode = row.querySelector('[data-required-doc-file-reference]');
        const currentUserName = requiredDocumentsRoot?.dataset.requiredDocCurrentUser || 'Usuario';
        const documentDescription = row.dataset.requiredDocDescription || '';
        const repositoryUrl = options.repositoryUrl || row.dataset.requiredDocUrl || '';
        const hasFile = typeof options.hasFile === 'boolean'
            ? options.hasFile
            : row.dataset.requiredDocHasFile === '1';
        const fileId = options.fileId !== undefined ? Number(options.fileId || 0) : Number(row.dataset.requiredDocFileId || 0);
        const fileName = String(options.fileName || row.dataset.requiredDocFileName || '').trim();
        const fileUrl = String(options.fileUrl || row.dataset.requiredDocFileUrl || '').trim();
        const isGit = row.dataset.requiredDocGit === '1';

        row.dataset.requiredDocCompleted = '1';
        row.dataset.requiredDocHasFile = hasFile ? '1' : '0';
        if (stateIcon) {
            stateIcon.classList.remove('required-doc-state--missing-file');
            stateIcon.classList.remove('required-doc-state--pending');
            stateIcon.classList.add(hasFile ? 'required-doc-state--completed' : 'required-doc-state--missing-file');
        }
        if (registeredByNode) {
            registeredByNode.textContent = options.recordedBy || currentUserName;
        }
        if (dateNode) {
            dateNode.textContent = options.recordedDate || getTodayDate();
        }
        if (actionButton) {
            actionButton.classList.remove('required-doc-action--pending');
            actionButton.classList.add('required-doc-action--completed');
            actionButton.textContent = isGit ? 'Editar' : 'Ver / Editar';
        }
        if (descriptionNode) {
            if (isGit && repositoryUrl !== '') {
                const link = document.createElement('a');
                link.className = 'required-doc-description required-doc-description--link';
                link.href = repositoryUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.dataset.requiredDocDescriptionNode = '';
                link.textContent = repositoryUrl;
                descriptionNode.replaceWith(link);
            } else if (!isGit) {
                descriptionNode.textContent = options.description || documentDescription;
            }
        }
        if (!isGit && fileReferenceNode) {
            if (hasFile && fileUrl !== '') {
                fileReferenceNode.hidden = false;
                fileReferenceNode.classList.remove('is-hidden');
                fileReferenceNode.href = fileUrl;
                fileReferenceNode.setAttribute('download', '');
                fileReferenceNode.textContent = fileName !== '' ? fileName : 'Archivo adjunto';
            } else {
                fileReferenceNode.hidden = true;
                fileReferenceNode.classList.add('is-hidden');
                fileReferenceNode.href = '#';
                fileReferenceNode.removeAttribute('download');
                fileReferenceNode.textContent = 'Archivo adjunto';
            }
        }
        if (isGit && repositoryUrl !== '') {
            row.dataset.requiredDocUrl = repositoryUrl;
        }
        if (!isGit) {
            row.dataset.requiredDocFileId = fileId > 0 ? String(fileId) : '';
            row.dataset.requiredDocFileName = fileName;
            row.dataset.requiredDocFileUrl = fileUrl;
        }
        updateRequiredDocumentsSummary();
    };

    const setRequiredDocumentRowPending = (row, options = {}) => {
        if (!row) return;
        const actionButton = row.querySelector('[data-required-doc-action-button]');
        const stateIcon = row.querySelector('[data-required-doc-state-icon]');
        const registeredByNode = row.querySelector('[data-required-doc-registered-by]');
        const dateNode = row.querySelector('[data-required-doc-date]');
        const descriptionNode = row.querySelector('[data-required-doc-description-node]');
        const fileReferenceNode = row.querySelector('[data-required-doc-file-reference]');
        row.dataset.requiredDocCompleted = '0';
        row.dataset.requiredDocHasFile = '0';
        row.dataset.requiredDocFileId = '';
        row.dataset.requiredDocFileName = '';
        row.dataset.requiredDocFileUrl = '';
        if (stateIcon) {
            stateIcon.classList.remove('required-doc-state--completed');
            stateIcon.classList.remove('required-doc-state--missing-file');
            stateIcon.classList.add('required-doc-state--pending');
        }
        if (actionButton) {
            actionButton.classList.remove('required-doc-action--completed');
            actionButton.classList.add('required-doc-action--pending');
            actionButton.textContent = options.actionLabel || 'Registrar';
        }
        if (registeredByNode) {
            registeredByNode.textContent = '-';
        }
        if (dateNode) {
            dateNode.textContent = '-';
        }
        if (!isGit && descriptionNode) {
            descriptionNode.textContent = row.dataset.requiredDocDescription || row.dataset.requiredDocModalDescription || '';
        }
        if (fileReferenceNode) {
            fileReferenceNode.hidden = true;
            fileReferenceNode.classList.add('is-hidden');
            fileReferenceNode.href = '#';
            fileReferenceNode.removeAttribute('download');
            fileReferenceNode.textContent = 'Archivo adjunto';
        }
        updateRequiredDocumentsSummary();
    };

    const syncRequiredDocumentCurrentFileUI = () => {
        if (!requiredDocumentCurrentFile || !requiredDocumentCurrentFileRemovedNote || !requiredDocumentCurrentFileRemoveButton) return;
        requiredDocumentCurrentFile.classList.toggle('is-marked-for-removal', requiredDocumentRemoveFileRequested);
        requiredDocumentCurrentFileRemovedNote.hidden = !requiredDocumentRemoveFileRequested;
        requiredDocumentCurrentFileRemoveButton.textContent = requiredDocumentRemoveFileRequested ? 'Conservar archivo' : 'Eliminar archivo';
        if (requiredDocumentRemoveFileHidden) {
            requiredDocumentRemoveFileHidden.value = requiredDocumentRemoveFileRequested ? '1' : '0';
        }
    };

    const closeAllGitEditors = () => {
        if (!requiredDocumentsRoot) return;
        requiredDocumentsRoot.querySelectorAll('[data-required-doc-git-editor-row]').forEach((editorRow) => {
            editorRow.classList.remove('is-open');
        });
    };

    const openGitEditor = (row) => {
        if (!requiredDocumentsRoot || !row) return;
        closeAllGitEditors();
        const key = row.dataset.requiredDocKey || '';
        const editorRow = requiredDocumentsRoot.querySelector(`[data-required-doc-git-editor-row][data-required-doc-key="${selectorSafeValue(key)}"]`);
        if (!editorRow) return;
        editorRow.classList.add('is-open');
        const input = editorRow.querySelector('[data-required-doc-git-input]');
        const error = editorRow.querySelector('[data-required-doc-git-error]');
        if (error) error.hidden = true;
        if (input) {
            input.value = row.dataset.requiredDocUrl || '';
            input.focus();
        }
    };

    const setRequiredDocumentValidation = (message) => {
        if (!requiredDocumentModalValidation) return;
        if (!message) {
            requiredDocumentModalValidation.hidden = true;
            return;
        }
        requiredDocumentModalValidation.textContent = message;
        requiredDocumentModalValidation.hidden = false;
    };

    const closeRequiredDocumentModal = () => {
        if (!requiredDocumentModal) return;
        requiredDocumentModal.hidden = true;
        setRequiredDocumentValidation('');
        requiredDocumentRemoveFileRequested = false;
        syncRequiredDocumentCurrentFileUI();
        if (requiredDocumentUploadForm) {
            requiredDocumentUploadForm.reset();
            const keyInput = requiredDocumentUploadForm.querySelector('[data-required-doc-key-input]');
            const tagInput = requiredDocumentUploadForm.querySelector('[data-required-doc-tag-hidden]');
            if (keyInput) keyInput.value = '';
            if (tagInput) tagInput.value = '';
            if (requiredDocumentRemoveFileHidden) requiredDocumentRemoveFileHidden.value = '0';
        }
        if (requiredDocumentPreview) {
            requiredDocumentPreview.hidden = true;
            const list = requiredDocumentPreview.querySelector('ul');
            if (list) list.innerHTML = '';
        }
        if (requiredDocumentCurrentFile) {
            requiredDocumentCurrentFile.hidden = true;
        }
        if (requiredDocumentCurrentFileName) {
            requiredDocumentCurrentFileName.textContent = '';
        }
        if (requiredDocumentCurrentFileDownload) {
            requiredDocumentCurrentFileDownload.href = '#';
            requiredDocumentCurrentFileDownload.removeAttribute('download');
        }
        if (requiredDocumentContractEndDateField) {
            requiredDocumentContractEndDateField.hidden = true;
        }
        if (requiredDocumentContractEndDateInput) {
            requiredDocumentContractEndDateInput.required = false;
            requiredDocumentContractEndDateInput.value = '';
        }
        activeRequiredDocumentKey = '';
    };

    const openRequiredDocumentModal = (row) => {
        if (!requiredDocumentModal || !requiredDocumentUploadForm || !row) return;
        const keyInput = requiredDocumentUploadForm.querySelector('[data-required-doc-key-input]');
        const tagInput = requiredDocumentUploadForm.querySelector('[data-required-doc-tag-hidden]');
        const title = requiredDocumentModal.querySelector('[data-required-doc-modal-title]');
        const isEdit = row.dataset.requiredDocCompleted === '1';
        const key = row.dataset.requiredDocKey || '';
        const name = row.dataset.requiredDocName || 'documento obligatorio';
        const recordedType = String(row.dataset.requiredDocType || '').trim();
        const presetType = String(requiredDocumentTypePresetsByKey[key] || '').trim();
        const expectedType = isEdit ? recordedType : (presetType || recordedType);
        const expectedTag = String(row.dataset.requiredDocTag || '').trim();
        const expectedTags = String(row.dataset.requiredDocTags || '').split('|').map((tag) => tag.trim()).filter(Boolean);
        const existingVersion = String(row.dataset.requiredDocVersion || '').trim();
        const existingDescription = String(row.dataset.requiredDocModalDescription || row.dataset.requiredDocDescription || '').trim();
        const existingFileName = String(row.dataset.requiredDocFileName || '').trim();
        const existingFileUrl = String(row.dataset.requiredDocFileUrl || '').trim();
        const existingContractEndDate = String(row.dataset.requiredDocContractEndDate || '').trim();
        const hasExistingFile = row.dataset.requiredDocHasFile === '1' && existingFileUrl !== '';
        const isContractDocument = key === 'contrato';
        activeRequiredDocumentKey = key;
        requiredDocumentRemoveFileRequested = false;
        syncRequiredDocumentCurrentFileUI();
        if (keyInput) keyInput.value = key;
        if (tagInput) tagInput.value = expectedTag;
        if (title) {
            title.textContent = isEdit ? `Ver / Editar ${name}` : `Registrar ${name}`;
        }
        if (requiredDocumentUploadForm) {
            requiredDocumentUploadForm.action = `<?= $basePath ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/required-documents/upload`;
            const hiddenType = requiredDocumentUploadForm.querySelector('[data-required-doc-document-type-hidden]');
            const hiddenTags = requiredDocumentUploadForm.querySelector('[data-required-doc-upload-tags-hidden]');
            if (hiddenType) hiddenType.value = '';
            if (hiddenTags) hiddenTags.value = '';
            if (requiredDocumentRemoveFileHidden) requiredDocumentRemoveFileHidden.value = '0';
        }
        if (requiredDocumentTypeCustom) requiredDocumentTypeCustom.value = '';
        if (requiredDocumentTypeSelect) {
            const options = Array.from(requiredDocumentTypeSelect.options || []);
            const hasType = options.some((option) => option.value === expectedType);
            requiredDocumentTypeSelect.value = hasType ? expectedType : '';
            if (isEdit && !hasType && requiredDocumentTypeCustom && expectedType !== '') {
                requiredDocumentTypeCustom.value = expectedType;
            }
        }
        if (requiredDocumentTagsSelect) {
            Array.from(requiredDocumentTagsSelect.options).forEach((option) => {
                option.selected = expectedTags.includes(option.value) || (expectedTags.length === 0 && expectedTag !== '' && option.value === expectedTag);
            });
            if (requiredDocumentTagsCustom) {
                const optionValues = new Set(Array.from(requiredDocumentTagsSelect.options).map((option) => option.value));
                const customTags = expectedTags.length > 0
                    ? expectedTags.filter((tag) => !optionValues.has(tag))
                    : (expectedTag !== '' && !optionValues.has(expectedTag) ? [expectedTag] : []);
                requiredDocumentTagsCustom.value = customTags.join(', ');
            }
        }
        const versionInput = requiredDocumentUploadForm.querySelector('input[name="document_version"]');
        if (versionInput) {
            versionInput.value = existingVersion;
        }
        const descriptionInput = requiredDocumentUploadForm.querySelector('textarea[name="document_description"]');
        if (descriptionInput) {
            descriptionInput.value = existingDescription;
        }
        if (requiredDocumentPreview) {
            requiredDocumentPreview.hidden = true;
            const list = requiredDocumentPreview.querySelector('ul');
            if (list) list.innerHTML = '';
        }
        if (requiredDocumentInput) {
            requiredDocumentInput.required = !isEdit;
        }
        if (requiredDocumentCurrentFile) {
            requiredDocumentCurrentFile.hidden = !isEdit || !hasExistingFile;
        }
        if (requiredDocumentCurrentFileName) {
            requiredDocumentCurrentFileName.textContent = existingFileName !== '' ? existingFileName : 'Archivo adjunto';
        }
        if (requiredDocumentCurrentFileDownload) {
            requiredDocumentCurrentFileDownload.href = hasExistingFile ? existingFileUrl : '#';
            if (hasExistingFile) {
                requiredDocumentCurrentFileDownload.setAttribute('download', '');
            } else {
                requiredDocumentCurrentFileDownload.removeAttribute('download');
            }
        }
        if (requiredDocumentContractEndDateField) {
            requiredDocumentContractEndDateField.hidden = !isContractDocument;
        }
        if (requiredDocumentContractEndDateInput) {
            requiredDocumentContractEndDateInput.required = isContractDocument;
            requiredDocumentContractEndDateInput.value = isContractDocument ? existingContractEndDate : '';
        }
        syncRequiredDocumentCurrentFileUI();
        setRequiredDocumentValidation('');
        requiredDocumentModal.hidden = false;
    };

    const collectRequiredDocumentType = () => {
        const custom = requiredDocumentTypeCustom ? requiredDocumentTypeCustom.value.trim() : '';
        if (custom !== '') {
            return custom;
        }
        return requiredDocumentTypeSelect ? requiredDocumentTypeSelect.value : '';
    };

    const collectRequiredDocumentTags = () => {
        const selectedTags = requiredDocumentTagsSelect
            ? Array.from(requiredDocumentTagsSelect.selectedOptions).map((option) => option.value.trim()).filter(Boolean)
            : [];
        const customTags = requiredDocumentTagsCustom && requiredDocumentTagsCustom.value.trim() !== ''
            ? requiredDocumentTagsCustom.value.split(',').map((tag) => tag.trim()).filter(Boolean)
            : [];
        const merged = Array.from(new Set([...selectedTags, ...customTags]));
        return merged.length > 0 ? merged : ['Documento obligatorio'];
    };

    if (requiredDocumentModal && requiredDocumentModalCloseButtons.length > 0) {
        requiredDocumentModalCloseButtons.forEach((button) => {
            button.addEventListener('click', closeRequiredDocumentModal);
        });
        requiredDocumentModal.addEventListener('click', (event) => {
            if (event.target === requiredDocumentModal) {
                closeRequiredDocumentModal();
            }
        });
    }

    if (requiredDocumentInput && requiredDocumentPreview) {
        requiredDocumentInput.addEventListener('change', () => {
            const list = requiredDocumentPreview.querySelector('ul');
            if (!list) return;
            list.innerHTML = '';
            Array.from(requiredDocumentInput.files || []).forEach((file) => {
                const item = document.createElement('li');
                item.textContent = `${file.name} (${Math.round(file.size / 1024)} KB)`;
                list.appendChild(item);
            });
            requiredDocumentPreview.hidden = list.children.length === 0;
        });
    }
    if (requiredDocumentCurrentFileRemoveButton) {
        requiredDocumentCurrentFileRemoveButton.addEventListener('click', () => {
            requiredDocumentRemoveFileRequested = !requiredDocumentRemoveFileRequested;
            syncRequiredDocumentCurrentFileUI();
        });
    }

    if (requiredDocumentUploadForm) {
        requiredDocumentUploadForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const keyInput = requiredDocumentUploadForm.querySelector('[data-required-doc-key-input]');
            const hiddenType = requiredDocumentUploadForm.querySelector('[data-required-doc-document-type-hidden]');
            const hiddenTags = requiredDocumentUploadForm.querySelector('[data-required-doc-upload-tags-hidden]');
            const removeFileValue = requiredDocumentRemoveFileHidden ? String(requiredDocumentRemoveFileHidden.value || '0') : '0';
            const key = String(keyInput?.value || activeRequiredDocumentKey || '').trim();
            const documentType = collectRequiredDocumentType();
            const tags = collectRequiredDocumentTags();
            const versionValue = (requiredDocumentUploadForm.querySelector('input[name="document_version"]')?.value || '').trim();
            const descriptionValue = (requiredDocumentUploadForm.querySelector('textarea[name="document_description"]')?.value || '').trim();
            const contractEndDateValue = (requiredDocumentContractEndDateInput?.value || '').trim();
            const fileInput = requiredDocumentUploadForm.querySelector('input[type="file"][name="required_document_file"]');
            const hasFile = Boolean(fileInput?.files && fileInput.files.length > 0);
            const targetRow = requiredDocumentsRoot
                ? requiredDocumentsRoot.querySelector(`[data-required-document-row][data-required-doc-key="${selectorSafeValue(key)}"]`)
                : null;
            const isEditing = Boolean(targetRow && targetRow.dataset.requiredDocCompleted === '1');
            if (!key) {
                setRequiredDocumentValidation('No se pudo identificar el documento obligatorio.');
                return;
            }
            if (!hasFile && !isEditing) {
                setRequiredDocumentValidation('Selecciona un archivo para continuar.');
                return;
            }
            if (!documentType) {
                setRequiredDocumentValidation('Selecciona el tipo documental.');
                return;
            }
            if (!versionValue) {
                setRequiredDocumentValidation('Ingresa la versión del documento.');
                return;
            }
            if (!descriptionValue) {
                setRequiredDocumentValidation('Ingresa una descripción corta.');
                return;
            }
            if (key === 'contrato' && !contractEndDateValue) {
                setRequiredDocumentValidation('Ingresa la fecha de finalización del contrato.');
                return;
            }
            if (hiddenType) hiddenType.value = documentType;
            if (hiddenTags) hiddenTags.value = JSON.stringify(tags);
            setRequiredDocumentValidation('');

            if (requiredDocumentUploadSubmitButton && requiredDocumentUploadSubmitButton.dataset.loading === 'true') {
                return;
            }
            if (requiredDocumentUploadSubmitButton) {
                const label = requiredDocumentUploadSubmitButton.querySelector('.button-label');
                requiredDocumentUploadSubmitButton.dataset.originalLabel = label?.textContent || 'Guardar documento';
                if (label) {
                    label.textContent = 'Guardando documento...';
                }
                requiredDocumentUploadSubmitButton.dataset.loading = 'true';
                requiredDocumentUploadSubmitButton.disabled = true;
                requiredDocumentUploadSubmitButton.classList.add('is-loading');
            }

            const formData = new FormData(requiredDocumentUploadForm);
            fetch(requiredDocumentUploadForm.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload.success) {
                        throw new Error(payload.message || 'No se pudo guardar el documento obligatorio.');
                    }
                    if (targetRow) {
                        const requiredDocumentPayload = payload.required_document || {};
                        const payloadDescription = String(requiredDocumentPayload.description || '').trim();
                        if (payloadDescription !== '') {
                            targetRow.dataset.requiredDocDescription = payloadDescription;
                            targetRow.dataset.requiredDocModalDescription = payloadDescription;
                        }
                        const payloadType = String(requiredDocumentPayload.document_type || '').trim();
                        if (payloadType !== '') {
                            targetRow.dataset.requiredDocType = payloadType;
                        }
                        const payloadVersion = String(requiredDocumentPayload.document_version || '').trim();
                        if (payloadVersion !== '') {
                            targetRow.dataset.requiredDocVersion = payloadVersion;
                        }
                        const payloadTags = Array.isArray(requiredDocumentPayload.document_tags)
                            ? requiredDocumentPayload.document_tags.map((tag) => String(tag).trim()).filter(Boolean)
                            : [];
                        if (payloadTags.length > 0) {
                            targetRow.dataset.requiredDocTags = payloadTags.join('|');
                            targetRow.dataset.requiredDocTag = payloadTags[0];
                        }
                        const payloadContractEndDate = String(requiredDocumentPayload.contract_end_date || '').trim();
                        if (payloadContractEndDate !== '') {
                            targetRow.dataset.requiredDocContractEndDate = payloadContractEndDate;
                        }
                        const payloadFileId = Number(requiredDocumentPayload.file_id || 0);
                        const payloadFileName = String(requiredDocumentPayload.file_name || '').trim();
                        const payloadFileUrl = String(requiredDocumentPayload.file_url || '').trim();
                        const payloadHasFile = Boolean(requiredDocumentPayload.has_file);
                        const payloadCompleted = Boolean(requiredDocumentPayload.completed);
                        if (payloadCompleted) {
                            setRequiredDocumentRowCompleted(targetRow, {
                                recordedBy: payload.required_document?.recorded_by || undefined,
                                recordedDate: payload.required_document?.recorded_date || undefined,
                                description: targetRow.dataset.requiredDocDescription || undefined,
                                hasFile: payloadHasFile,
                                fileId: payloadFileId,
                                fileName: payloadFileName,
                                fileUrl: payloadFileUrl,
                            });
                        } else {
                            setRequiredDocumentRowPending(targetRow);
                        }
                    }
                    closeRequiredDocumentModal();
                })
                .catch((requestError) => {
                    setRequiredDocumentValidation(requestError.message || 'No se pudo guardar el documento obligatorio.');
                })
                .finally(() => {
                    if (requiredDocumentUploadSubmitButton) {
                        const label = requiredDocumentUploadSubmitButton.querySelector('.button-label');
                        if (label) {
                            label.textContent = requiredDocumentUploadSubmitButton.dataset.originalLabel || 'Guardar documento';
                        }
                        requiredDocumentUploadSubmitButton.dataset.loading = 'false';
                        requiredDocumentUploadSubmitButton.disabled = false;
                        requiredDocumentUploadSubmitButton.classList.remove('is-loading');
                    }
                });
        });
    }

    if (requiredDocumentsRoot) {
        requiredDocumentsRoot.addEventListener('click', (event) => {
            const actionButton = event.target.closest('[data-required-document-action]');
            if (actionButton) {
                const row = actionButton.closest('[data-required-document-row]');
                if (!row) return;
                const isSchedule = row.dataset.requiredDocSchedule === '1';
                const isGit = row.dataset.requiredDocGit === '1';

                if (isSchedule) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('view', 'cronograma');
                    window.location.href = url.toString();
                    return;
                }

                if (isGit) {
                    openGitEditor(row);
                    return;
                }

                if (!requiredDocumentModal || !requiredDocumentUploadForm) {
                    return;
                }
                openRequiredDocumentModal(row);
                return;
            }

            const cancelButton = event.target.closest('[data-required-doc-git-cancel]');
            if (cancelButton) {
                const editorRow = cancelButton.closest('[data-required-doc-git-editor-row]');
                if (editorRow) {
                    editorRow.classList.remove('is-open');
                    const error = editorRow.querySelector('[data-required-doc-git-error]');
                    if (error) error.hidden = true;
                }
                return;
            }

            const saveButton = event.target.closest('[data-required-doc-git-save]');
            if (!saveButton) return;

            const editorRow = saveButton.closest('[data-required-doc-git-editor-row]');
            if (!editorRow) return;
            const key = editorRow.dataset.requiredDocKey || '';
            const targetRow = requiredDocumentsRoot.querySelector(`[data-required-document-row][data-required-doc-key="${selectorSafeValue(key)}"]`);
            if (!targetRow) return;
            const input = editorRow.querySelector('[data-required-doc-git-input]');
            const error = editorRow.querySelector('[data-required-doc-git-error]');
            const value = (input?.value || '').trim();
            const valid = /^https?:\/\//i.test(value);
            let parsedUrl = null;
            try {
                parsedUrl = new URL(value);
            } catch (_error) {
                parsedUrl = null;
            }
            if (!valid || !parsedUrl) {
                if (error) {
                    error.hidden = false;
                }
                return;
            }
            if (error) {
                error.hidden = true;
            }

            const projectId = <?= (int) ($project['id'] ?? 0) ?>;
            if (saveButton.dataset.loading === '1') return;
            saveButton.dataset.loading = '1';
            saveButton.disabled = true;
            const previousLabel = saveButton.textContent;
            saveButton.textContent = 'Guardando...';

            const formData = new FormData();
            formData.append('repository_url', value);
            fetch(`<?= $basePath ?>/projects/${projectId}/required-documents/git`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload.success) {
                        throw new Error(payload.message || 'No se pudo guardar el repositorio.');
                    }
                    targetRow.dataset.requiredDocUrl = value;
                    setRequiredDocumentRowCompleted(targetRow, { repositoryUrl: value });
                    editorRow.classList.remove('is-open');
                })
                .catch((requestError) => {
                    if (error) {
                        error.hidden = false;
                        error.textContent = requestError.message || 'Ingresa una URL válida';
                    }
                })
                .finally(() => {
                    saveButton.dataset.loading = '0';
                    saveButton.disabled = false;
                    saveButton.textContent = previousLabel;
                });
        });

    }

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

    const scheduleRoot = document.querySelector('[data-schedule-root]');
    if (scheduleRoot) {
        const editorPanel = document.getElementById('schedule-editor-panel');
        const importModal = document.getElementById('schedule-import-modal');
        const editorRows = document.getElementById('schedule-editor-rows');
        const hiddenActivities = document.getElementById('schedule-activities-json');
        const projectId = Number(scheduleRoot.dataset.projectId || 0);
        const tasks = <?= json_encode($tasksForSchedule ?? [], JSON_UNESCAPED_UNICODE) ?>;

        const normalizeDate = (value) => value || '';
        const diffDays = (startDate, endDate) => {
            if (!startDate || !endDate) return 0;
            const start = new Date(startDate);
            const end = new Date(endDate);
            if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return 0;
            return Math.floor((end - start) / 86400000) + 1;
        };

        const linkedProgress = (activityId) => {
            const linked = tasks.filter((task) => Number(task.schedule_activity_id || 0) === Number(activityId || 0));
            if (!linked.length) return null;
            const completed = linked.filter((task) => ['done', 'completed'].includes(String(task.status || '').toLowerCase())).length;
            return Math.round((completed / linked.length) * 100);
        };

        const renderRow = (row = {}, index = 0) => {
            const wrapper = document.createElement('tr');
            wrapper.className = 'schedule-row';
            const activityId = Number(row.id || 0);
            const calculatedProgress = linkedProgress(activityId);
            const isLocked = calculatedProgress !== null;
            wrapper.innerHTML = `
                <td class="schedule-num">${index + 1}</td>
                <td><input placeholder="Nombre" data-field="name" value="${row.name || ''}" /></td>
                <td><select data-field="item_type"><option value="activity">Actividad</option><option value="milestone">Hito</option></select></td>
                <td><input type="date" data-field="start_date" value="${normalizeDate(row.start_date)}" /></td>
                <td><input type="date" data-field="end_date" value="${normalizeDate(row.end_date)}" /></td>
                <td><input type="number" data-field="duration_days" value="${Number(row.duration_days || diffDays(row.start_date, row.end_date))}" readonly /></td>
                <td><input list="schedule-talents" placeholder="Responsable" data-field="responsible_name" value="${row.responsible_name || ''}" /></td>
                <td class="schedule-progress-cell"><input type="number" min="0" max="100" data-field="progress_percent" value="${Number(isLocked ? calculatedProgress : (row.progress_percent || 0))}" ${isLocked ? 'readonly' : ''} />${isLocked ? '<span title="Calculado desde tareas vinculadas">🔒</span>' : ''}</td>
                <td><button type="button" class="action-btn danger" data-remove-row>Eliminar</button></td>
            `;
            wrapper.querySelector('[data-field="item_type"]').value = row.item_type || 'activity';
            wrapper.querySelector('[data-remove-row]').addEventListener('click', () => wrapper.remove());
            const startInput = wrapper.querySelector('[data-field="start_date"]');
            const endInput = wrapper.querySelector('[data-field="end_date"]');
            const durationInput = wrapper.querySelector('[data-field="duration_days"]');
            const updateDuration = () => { durationInput.value = String(diffDays(startInput.value, endInput.value)); };
            startInput.addEventListener('change', updateDuration);
            endInput.addEventListener('change', updateDuration);
            return wrapper;
        };

        const openEditor = (rowToFocus = null) => {
            if (!editorPanel || !editorRows) return;
            editorRows.innerHTML = '';
            const existing = JSON.parse(editorRows.dataset.existing || '[]');
            (existing.length ? existing : [{}]).forEach((row, index) => editorRows.appendChild(renderRow(row, index)));
            editorPanel.hidden = false;
            if (rowToFocus !== null) {
                editorRows.querySelectorAll('.schedule-row')[rowToFocus]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        };

        scheduleRoot.querySelectorAll('[data-schedule-action]').forEach((btn) => btn.addEventListener('click', () => {
            if (btn.dataset.scheduleAction === 'create') openEditor();
            if (btn.dataset.scheduleAction === 'import' && importModal) importModal.showModal();
        }));
        scheduleRoot.querySelectorAll('[data-schedule-edit-row]').forEach((btn) => btn.addEventListener('click', () => openEditor(Number(btn.dataset.scheduleEditRow || 0))));
        scheduleRoot.querySelectorAll('[data-schedule-delete-row]').forEach((btn) => btn.addEventListener('click', async () => {
            openEditor();
            const idx = Number(btn.dataset.scheduleDeleteRow || -1);
            editorRows.querySelectorAll('.schedule-row')[idx]?.remove();
        }));
        document.querySelector('[data-schedule-add-row]')?.addEventListener('click', () => {
            const nextIndex = editorRows.querySelectorAll('.schedule-row').length;
            editorRows.appendChild(renderRow({}, nextIndex));
        });
        editorPanel?.querySelector('form')?.addEventListener('submit', () => {
            const rows = Array.from(editorRows.querySelectorAll('.schedule-row')).map((row, index) => ({
                sort_order: index + 1,
                name: row.querySelector('[data-field="name"]').value,
                item_type: row.querySelector('[data-field="item_type"]').value,
                start_date: row.querySelector('[data-field="start_date"]').value,
                end_date: row.querySelector('[data-field="end_date"]').value,
                progress_percent: Number(row.querySelector('[data-field="progress_percent"]').value || 0),
                responsible_name: row.querySelector('[data-field="responsible_name"]').value,
                duration_days: Number(row.querySelector('[data-field="duration_days"]').value || 0),
            }));
            hiddenActivities.value = JSON.stringify(rows);
        });

        const importForm = document.getElementById('schedule-import-form');
        const preview = document.getElementById('schedule-import-preview');
        importForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = new FormData(importForm);
            const response = await fetch(`/projects/${projectId}/schedule/import-preview`, { method: 'POST', body: form });
            const result = await response.json();
            if (result.status !== 'ok') {
                preview.innerHTML = `<p class="phase-warning">${result.message || 'No se pudo previsualizar.'}</p>`;
                return;
            }
            const errors = Array.isArray(result.errors) ? result.errors : [];
            preview.innerHTML = `<p>${errors.length ? 'Errores detectados' : 'Sin errores. Listo para importar.'}</p>` + errors.map((e) => `<p class="phase-warning">Fila ${e.row}: ${e.message}</p>`).join('') + `<div class="schedule-actions"><button type="button" class="action-btn primary" id="schedule-import-confirm">Confirmar importación</button></div>`;
            document.getElementById('schedule-import-confirm')?.addEventListener('click', async () => {
                const mode = <?= !empty($scheduleActivities) ? "'replace'" : "'replace'" ?>;
                await fetch(`/projects/${projectId}/schedule/import-confirm`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ mode, rows_json: JSON.stringify(result.rows || []) }),
                });
                window.location.reload();
            });
        });
        document.querySelectorAll('[data-export]').forEach((button) => button.addEventListener('click', () => {
            if (button.dataset.export === 'pdf') {
                window.print();
                return;
            }
            if (button.dataset.export === 'png') {
                const svg = document.querySelector('.gantt-bars svg');
                if (!svg) return;
                const data = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg.outerHTML);
                const link = document.createElement('a');
                link.href = data;
                link.download = 'cronograma-gantt.png';
                link.click();
            }
        }));

        const ganttBars = document.querySelector('.gantt-bars');
        if (ganttBars) {
            let items = JSON.parse(ganttBars.dataset.ganttBars || '[]');
            const zoomContainer = document.querySelector('.gantt-grid');
            const root = document.querySelector('[data-schedule-root]');
            const projectId = Number(root?.dataset.projectId || 0);
            const zoomFactor = () => {
                const zoom = zoomContainer?.dataset.zoom || 'month';
                if (zoom === 'week') return 1.8;
                if (zoom === 'quarter') return 0.75;
                return 1;
            };
            const addDays = (dateValue, days) => {
                const date = new Date(dateValue);
                if (Number.isNaN(date.getTime())) {
                    return null;
                }
                date.setDate(date.getDate() + days);
                return date;
            };
            const toIsoDate = (date) => {
                if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                    return '';
                }
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            let ganttContext = null;
            let dragging = null;

            const persistDates = async (activityId, startDate, endDate) => {
                const response = await fetch(`/api/projects/${projectId}/schedule/activities/${activityId}/dates`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        start_date: startDate,
                        end_date: endDate,
                    }),
                });
                const payload = await response.json();
                if (!response.ok || payload.status !== 'ok') {
                    throw new Error(payload.message || 'No se pudo actualizar la actividad.');
                }
            };

            const renderGantt = () => {
                if (!items.length) return;
                const starts = items.map((i) => new Date(i.start_date)).filter((d) => !Number.isNaN(d.getTime()));
                const ends = items.map((i) => new Date(i.end_date || i.start_date)).filter((d) => !Number.isNaN(d.getTime()));
                if (!starts.length || !ends.length) return;
                const min = new Date(Math.min(...starts));
                const max = new Date(Math.max(...ends));
                const totalDays = Math.max(1, Math.ceil((max - min) / 86400000) + 1);
                const scale = zoomFactor();
                ganttContext = { min, totalDays, scale };

                const rows = items.map((item, idx) => {
                    const s = new Date(item.start_date);
                    const e = new Date(item.end_date || item.start_date);
                    if (Number.isNaN(s.getTime()) || Number.isNaN(e.getTime())) {
                        return '';
                    }
                    const left = Math.max(0, Math.floor((s - min) / 86400000));
                    const width = Math.max(1, Math.floor((e - s) / 86400000) + 1);
                    const x = (left / totalDays) * 100 * scale;
                    const w = (width / totalDays) * 100 * scale;
                    const color = item.derived_status === 'red' ? '#ef4444' : (item.derived_status === 'yellow' ? '#f59e0b' : '#10b981');
                    const tooltip = `${item.name || 'Actividad'} · ${item.start_date || ''} → ${item.end_date || ''} · ${item.responsible_name || 'Sin asignar'} · ${Number(item.progress_percent || 0)}%`;
                    if (item.item_type === 'milestone') {
                        return `<polygon class="gantt-milestone-point" data-gantt-index="${idx}" points="${x},${idx * 28 + 6} ${x + 1},${idx * 28 + 12} ${x},${idx * 28 + 18} ${x - 1},${idx * 28 + 12}" fill="${color}"><title>${tooltip}</title></polygon>`;
                    }
                    return `<g data-gantt-index="${idx}">
                        <rect class="gantt-activity-bar" data-gantt-index="${idx}" x="${x}" y="${idx * 28 + 6}" width="${w}" height="12" rx="4" fill="${color}"><title>${tooltip}</title></rect>
                        <circle class="gantt-resize-handle" data-gantt-index="${idx}" data-edge="start" cx="${x}" cy="${idx * 28 + 12}" r="1.15"></circle>
                        <circle class="gantt-resize-handle" data-gantt-index="${idx}" data-edge="end" cx="${x + w}" cy="${idx * 28 + 12}" r="1.15"></circle>
                    </g>`;
                }).join('');
                ganttBars.innerHTML = `<svg viewBox="0 0 ${100 * scale} ${items.length * 28 + 20}" preserveAspectRatio="none"><line x1="${((Date.now() - min.getTime()) / 86400000) / totalDays * 100 * scale}" x2="${((Date.now() - min.getTime()) / 86400000) / totalDays * 100 * scale}" y1="0" y2="${items.length * 28 + 20}" stroke="#3b82f6" stroke-dasharray="2 2" />${rows}</svg>`;
            };

            const mouseToOffset = (event) => {
                const svg = ganttBars.querySelector('svg');
                if (!svg || !ganttContext) return 0;
                const rect = svg.getBoundingClientRect();
                if (rect.width <= 0) return 0;
                const x = ((event.clientX - rect.left) / rect.width) * (100 * ganttContext.scale);
                const day = Math.round((x / (100 * ganttContext.scale)) * ganttContext.totalDays);
                return Math.max(0, Math.min(ganttContext.totalDays - 1, day));
            };

            const onMouseMove = (event) => {
                if (!dragging || !ganttContext) return;
                const item = items[dragging.index];
                if (!item) return;
                const offset = mouseToOffset(event);
                let startOffset = Math.floor((new Date(item.start_date) - ganttContext.min) / 86400000);
                let endOffset = Math.floor((new Date(item.end_date || item.start_date) - ganttContext.min) / 86400000);
                if (dragging.edge === 'start') {
                    startOffset = Math.min(offset, endOffset);
                } else {
                    endOffset = Math.max(offset, startOffset);
                }
                const nextStart = addDays(ganttContext.min, startOffset);
                const nextEnd = addDays(ganttContext.min, endOffset);
                item.start_date = toIsoDate(nextStart);
                item.end_date = toIsoDate(nextEnd);
                renderGantt();
            };

            const onMouseUp = async () => {
                if (!dragging) return;
                const item = items[dragging.index];
                dragging = null;
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                if (!item || !item.id) {
                    return;
                }
                try {
                    await persistDates(Number(item.id), String(item.start_date || ''), String(item.end_date || item.start_date || ''));
                } catch (error) {
                    window.alert(error instanceof Error ? error.message : 'No se pudieron guardar las fechas.');
                }
            };

            const bindGanttInteractions = () => {
                const svg = ganttBars.querySelector('svg');
                if (!svg) return;
                svg.querySelectorAll('.gantt-resize-handle').forEach((handle) => {
                    handle.addEventListener('mousedown', (event) => {
                        const index = Number(handle.getAttribute('data-gantt-index') || -1);
                        const edge = String(handle.getAttribute('data-edge') || '');
                        if (index < 0 || !['start', 'end'].includes(edge)) return;
                        event.preventDefault();
                        dragging = { index, edge };
                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    });
                });
                svg.querySelectorAll('.gantt-milestone-point').forEach((milestone) => {
                    milestone.addEventListener('click', async () => {
                        const index = Number(milestone.getAttribute('data-gantt-index') || -1);
                        if (index < 0) return;
                        const item = items[index];
                        if (!item || !item.id) return;
                        const currentDate = String(item.start_date || item.end_date || '');
                        const picked = window.prompt('Fecha del hito (YYYY-MM-DD)', currentDate);
                        if (!picked || !/^\d{4}-\d{2}-\d{2}$/.test(picked)) {
                            return;
                        }
                        item.start_date = picked;
                        item.end_date = picked;
                        renderGantt();
                        try {
                            await persistDates(Number(item.id), picked, picked);
                        } catch (error) {
                            window.alert(error instanceof Error ? error.message : 'No se pudo actualizar el hito.');
                        }
                    });
                });
            };

            const rerender = () => {
                renderGantt();
                bindGanttInteractions();
            };

            rerender();
            document.querySelectorAll('[data-zoom]').forEach((button) => button.addEventListener('click', () => {
                if (!zoomContainer) return;
                zoomContainer.dataset.zoom = button.dataset.zoom;
                rerender();
            }));
        }
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
    .schedule-shell { border:1px solid var(--border); border-radius:16px; padding:16px; background:var(--surface); display:flex; flex-direction:column; gap:12px; }
    .schedule-empty { border:1px dashed var(--border); border-radius:12px; padding:20px; display:flex; flex-direction:column; gap:12px; align-items:flex-start; }
    .schedule-summary-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:10px; border:1px solid var(--border); border-radius:12px; padding:12px; }
    .schedule-summary-grid div { display:flex; flex-direction:column; gap:2px; }
    .schedule-summary-grid span { font-size:12px; color:var(--text-secondary); text-transform:uppercase; font-weight:700; }
    .schedule-summary-grid strong { color:var(--text-primary); }
    .schedule-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .schedule-editor-panel { border:1px solid var(--border); border-radius:12px; padding:12px; background: color-mix(in srgb, var(--surface) 90%, var(--background)); }
    .schedule-editor-table-wrap { overflow:auto; }
    .schedule-editor-table input, .schedule-editor-table select { width:100%; padding:8px; border-radius:8px; border:1px solid var(--border); background:var(--surface); color:var(--text-primary); }
    .schedule-editor-table td { vertical-align:middle; }
    .schedule-progress-cell { display:flex; align-items:center; gap:6px; }
    .schedule-editor-footer { display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap; }
    .gantt-wrapper { border:1px solid var(--border); border-radius:12px; padding:12px; display:flex; flex-direction:column; gap:8px; }
    .gantt-controls { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .gantt-grid { display:grid; grid-template-columns: minmax(420px, 1.2fr) minmax(320px, 1fr); gap:10px; }
    .gantt-table { overflow:auto; border:1px solid var(--border); border-radius:10px; }
    .gantt-bars { border:1px solid var(--border); border-radius:10px; min-height:220px; padding:4px; background: color-mix(in srgb, var(--surface) 85%, var(--background)); }
    .gantt-bars svg { width:100%; height:100%; min-height:220px; }
    .gantt-activity-bar { cursor: move; }
    .gantt-resize-handle { fill: #0f172a; stroke: #ffffff; stroke-width: 0.2; cursor: ew-resize; }
    .gantt-milestone-point { cursor: pointer; }
    .schedule-form { display:flex; flex-direction:column; gap:10px; width:100%; min-width:0; }
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
    .required-documents-block { border:1px solid var(--border); border-radius:16px; padding:14px; background: var(--surface); display:flex; flex-direction:column; gap:10px; margin-bottom:14px; }
    .required-documents-block__header { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .required-documents-block__header h3 { margin:0; font-size:15px; font-weight:600; }
    .required-documents-block__summary { display:flex; align-items:center; gap:10px; font-size:12px; color: var(--text-secondary); }
    .required-documents-progress--compact { width:120px; height:6px; }
    .required-documents-progress--compact div { background: var(--success); }
    .required-documents-table-wrap { width:100%; overflow-x:auto; }
    .required-documents-table { width:100%; border-collapse:collapse; }
    .required-documents-table thead th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color: var(--text-secondary); text-align:left; padding:8px 10px; border-bottom:1px solid var(--border); }
    .required-documents-table tbody td { padding:9px 10px; border-bottom:1px solid color-mix(in srgb, var(--border) 70%, transparent); vertical-align:middle; font-size:13px; color: var(--text-primary); }
    .required-documents-table tbody tr:last-child td { border-bottom:none; }
    .required-doc-table__document { min-width:280px; }
    .required-doc-name { display:block; font-size:14px; font-weight:500; color: var(--text-primary); line-height:1.25; }
    .required-doc-description { display:block; margin-top:2px; font-size:12px; color: var(--text-secondary); line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
    .required-doc-description--link { color: var(--primary); text-decoration:none; }
    .required-doc-description--link:hover { text-decoration:underline; }
    .required-doc-state { width:18px; height:18px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; position:relative; }
    .required-doc-state--pending { border:2px solid #f59e0b; background:transparent; }
    .required-doc-state--completed { border:2px solid var(--success); background: var(--success); }
    .required-doc-state--completed::after { content:'✓'; color:#fff; font-size:11px; line-height:1; font-weight:700; }
    .required-doc-state--missing-file { border:2px solid var(--warning); background: color-mix(in srgb, var(--warning) 18%, var(--background)); }
    .required-doc-state--missing-file::after { content:'!'; color:var(--warning); font-size:11px; line-height:1; font-weight:800; }
    .required-doc-action { border:1px solid; border-radius:8px; background:#fff; padding:4px 10px; font-size:12px; font-weight:600; line-height:1.2; cursor:pointer; white-space:nowrap; }
    .required-doc-action--pending { border-color: var(--primary); color: var(--primary); }
    .required-doc-action--completed { border-color: var(--border); color: var(--text-secondary); }
    .required-doc-action--save { border-color: var(--primary); background: var(--primary); color:#fff; }
    .required-doc-git-editor-row td { padding:0; border-bottom:none; }
    .required-doc-git-editor {
        max-height:0;
        opacity:0;
        overflow:hidden;
        padding:0 10px;
        border:0 solid transparent;
        border-radius:10px;
        background: color-mix(in srgb, var(--text-secondary) 6%, var(--background));
        transition:max-height .22s ease, opacity .2s ease, padding .2s ease, border-width .2s ease;
    }
    .required-doc-git-editor-row.is-open td { padding:0 10px 10px; }
    .required-doc-git-editor-row.is-open .required-doc-git-editor {
        max-height:140px;
        opacity:1;
        padding:10px;
        border-width:1px;
        border-color:var(--border);
    }
    .required-doc-git-editor-row.is-open .required-doc-git-editor { animation: required-doc-row-expand .18s ease-out; }
    .required-doc-git-editor__controls { display:flex; gap:8px; align-items:center; }
    .required-doc-git-editor__controls input { flex:1; min-width:220px; border:1px solid var(--border); border-radius:8px; padding:7px 10px; font-size:13px; }
    .required-doc-git-error { margin:6px 0 0; font-size:12px; color: var(--danger); }
    .required-documents-block .modal { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:60; }
    .required-documents-block .modal[hidden] { display:none; }
    .required-documents-block .modal-backdrop { position:absolute; inset:0; background: color-mix(in srgb, var(--text-primary) 45%, var(--background)); }
    .required-documents-block .modal-panel { position:relative; background: var(--surface); border-radius:14px; padding:16px; width:min(640px, 92vw); max-height:90vh; overflow:auto; display:flex; flex-direction:column; gap:12px; box-shadow:0 20px 40px color-mix(in srgb, var(--text-primary) 20%, var(--background)); z-index:1; }
    .required-documents-block .modal-panel header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .required-documents-block .field { display:flex; flex-direction:column; gap:6px; font-size:13px; color: var(--text-primary); }
    .required-documents-block .field input,
    .required-documents-block .field select,
    .required-documents-block .field textarea { border:1px solid var(--border); border-radius:8px; padding:6px 8px; }
    .required-documents-block .field-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
    .required-documents-block .upload-preview { margin-top:6px; background: var(--surface); border:1px dashed var(--border); padding:8px; border-radius:10px; font-size:13px; }
    .required-documents-block .form-validation { background: color-mix(in srgb, var(--warning) 16%, var(--background)); color: var(--warning); border:1px solid color-mix(in srgb, var(--warning) 35%, var(--background)); border-radius:8px; padding:8px 10px; font-size:12px; font-weight:600; }
    .required-doc-file-reference { display:block; margin-top:4px; font-size:11px; color: var(--text-secondary); text-decoration:none; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .required-doc-file-reference:hover { text-decoration:underline; color: var(--primary); }
    .required-doc-file-reference.is-hidden { display:none; }
    .required-doc-current-file { border:1px solid var(--border); border-radius:10px; padding:10px; background: color-mix(in srgb, var(--text-secondary) 8%, var(--background)); display:flex; flex-direction:column; gap:8px; }
    .required-doc-current-file__title { margin:0; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color: var(--text-secondary); }
    .required-doc-current-file__actions { display:flex; align-items:center; flex-wrap:wrap; gap:8px; font-size:13px; }
    .required-doc-current-file__actions span { font-weight:600; color: var(--text-primary); }
    .required-doc-current-file.is-marked-for-removal { border-color: color-mix(in srgb, var(--danger) 45%, var(--border)); background: color-mix(in srgb, var(--danger) 9%, var(--background)); }
    .required-documents-block .modal-actions { display:flex; justify-content:flex-end; gap:8px; }
    .required-documents-block .button-spinner { width:14px; height:14px; border-radius:50%; border:2px solid color-mix(in srgb, var(--text-primary) 50%, var(--background)); border-top-color: var(--text-primary); animation: spin 1s linear infinite; display:none; }
    .required-documents-block .action-btn.is-loading .button-spinner { display:inline-block; }
    .required-documents-block .action-btn.is-loading .button-label { opacity:0.8; }
    @keyframes required-doc-row-expand {
        from { opacity:0; transform: translateY(-4px); }
        to { opacity:1; transform: translateY(0); }
    }
    .required-documents-divider { position:relative; height:22px; margin: 2px 0 16px; }
    .required-documents-divider::before { content:''; position:absolute; left:0; right:0; top:50%; height:1px; background: color-mix(in srgb, var(--border) 80%, transparent); }
    .required-documents-divider span { position:absolute; left:50%; top:50%; transform:translate(-50%, -50%); padding:0 10px; background: var(--background); font-size:12px; letter-spacing:.08em; color: var(--text-secondary); text-transform:uppercase; }
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
        .required-documents-block__header { align-items:flex-start; flex-direction:column; }
        .required-documents-block__summary { width:100%; justify-content:space-between; }
        .required-doc-git-editor__controls { flex-wrap:wrap; }
        .project-layout { grid-template-columns: 1fr; }
        .phase-sidebar { max-height:none; }
    }
</style>
