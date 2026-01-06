<?php

declare(strict_types=1);

class ProjectLifecycleService
{
    private const ACTION_PHASE_ORDER = [
        'design_inputs' => 0,
        'design_review' => 1,
        'design_verification' => 2,
        'design_validation' => 3,
        'design_changes' => -1,
    ];

    private const PHASE_NODE_MAP = [
        'cascada' => [
            'inicio' => ['code' => '01-INICIO', 'title' => '01 · Inicio', 'iso' => null, 'sort' => 10],
            'planificacion' => ['code' => '02-PLANIFICACION', 'title' => '02 · Planificación', 'iso' => '8.3', 'sort' => 20],
            'ejecucion' => ['code' => '03-EJECUCION', 'title' => '03 · Ejecución', 'iso' => null, 'sort' => 30],
            'seguimiento' => ['code' => '04-SEGUIMIENTO', 'title' => '04 · Seguimiento y Control', 'iso' => '8.3.4', 'sort' => 40],
            'seguimiento y control' => ['code' => '04-SEGUIMIENTO', 'title' => '04 · Seguimiento y Control', 'iso' => '8.3.4', 'sort' => 40],
            'cierre' => ['code' => '05-CIERRE', 'title' => '05 · Cierre', 'iso' => null, 'sort' => 50],
        ],
        'scrum' => [
            'discovery' => ['code' => '01-DISCOVERY', 'title' => '01 · Discovery', 'iso' => null, 'sort' => 5],
            'backlog' => ['code' => '02-BACKLOG', 'title' => '02 · Backlog', 'iso' => '8.3.2', 'sort' => 10],
            'sprints' => ['code' => '03-SPRINTS', 'title' => '03 · Sprints', 'iso' => '8.3.4', 'sort' => 20],
            'review' => ['code' => '04-REVIEW', 'title' => '04 · Review', 'iso' => '8.3.4', 'sort' => 30],
            'release' => ['code' => '05-RELEASE', 'title' => '05 · Release', 'iso' => '8.3.5', 'sort' => 40],
            'descubrimiento' => ['code' => '01-DISCOVERY', 'title' => '01 · Discovery', 'iso' => null, 'sort' => 5],
            'backlog listo' => ['code' => '02-BACKLOG', 'title' => '02 · Backlog', 'iso' => '8.3.2', 'sort' => 10],
            'sprint' => ['code' => '03-SPRINTS', 'title' => '03 · Sprints', 'iso' => '8.3.4', 'sort' => 20],
            'deploy' => ['code' => '05-RELEASE', 'title' => '05 · Release', 'iso' => '8.3.5', 'sort' => 40],
        ],
        'kanban' => [
            'por hacer' => ['code' => '01-BACKLOG', 'title' => 'Backlog', 'iso' => '8.3.2', 'sort' => 10],
            'en curso' => ['code' => '02-EN-CURSO', 'title' => 'En curso', 'iso' => '8.3.4', 'sort' => 20],
            'en revision' => ['code' => '03-EN-REVISION', 'title' => 'En revisión', 'iso' => '8.3.5', 'sort' => 30],
            'hecho' => ['code' => '04-HECHO', 'title' => 'Hecho', 'iso' => '8.3.5', 'sort' => 40],
        ],
    ];

    private const ISO_ACTION_NODE_MAP = [
        'design_inputs' => [
            ['code' => '01-entradas-diseno', 'title' => 'Entradas de diseño', 'iso_clause' => '8.3.3'],
        ],
        'design_review' => [
            ['code' => '03-controles', 'title' => 'Controles de diseño', 'iso_clause' => '8.3.4'],
            ['code' => '03-controles-revision', 'title' => 'Revisión de diseño', 'iso_clause' => '8.3.4'],
        ],
        'design_verification' => [
            ['code' => '03-controles', 'title' => 'Controles de diseño', 'iso_clause' => '8.3.4'],
            ['code' => '03-controles-verificacion', 'title' => 'Verificación de diseño', 'iso_clause' => '8.3.4'],
        ],
        'design_validation' => [
            ['code' => '03-controles', 'title' => 'Controles de diseño', 'iso_clause' => '8.3.5'],
            ['code' => '03-controles-validacion', 'title' => 'Validación de diseño', 'iso_clause' => '8.3.5'],
        ],
        'design_changes' => [
            ['code' => '05-cambios', 'title' => 'Control de cambios', 'iso_clause' => '8.3.6'],
        ],
    ];

    public function __construct(private Database $db)
    {
    }

    public function ensureIsoNode(int $projectId, string $action, ?array $project = null): int
    {
        $projectData = $project ?? (new ProjectsRepository($this->db))->find($projectId) ?? [];
        $definitions = $this->phaseDefinitions($projectData);
        $phaseAssignments = $this->actionPhaseAssignments($definitions);
        $phaseCode = $phaseAssignments[$action] ?? null;

        if ($phaseCode === null) {
            throw new \InvalidArgumentException('No se pudo determinar la fase para la acción ISO.');
        }

        $isoNodes = self::ISO_ACTION_NODE_MAP[$action] ?? [];
        if (empty($isoNodes)) {
            throw new \InvalidArgumentException('La acción ISO no está mapeada a un nodo de proyecto.');
        }

        $nodesRepo = new ProjectNodesRepository($this->db);
        $phaseDefinition = null;

        foreach ($definitions as $definition) {
            if (($definition['code'] ?? null) === $phaseCode) {
                $phaseDefinition = $definition;
                break;
            }
        }

        $phaseNode = $nodesRepo->ensurePhaseFolder(
            $projectId,
            $phaseCode,
            [
                'title' => (string) ($phaseDefinition['title'] ?? ''),
                'iso_clause' => $phaseDefinition['iso_clause'] ?? null,
                'sort_order' => (int) ($phaseDefinition['sort_order'] ?? 0),
            ]
        );

        $this->ensurePhaseFolders($projectId, $definitions, $nodesRepo);

        $phaseNodeId = (int) ($phaseNode['id'] ?? 0);
        if ($phaseNodeId <= 0) {
            throw new \InvalidArgumentException('No se pudo garantizar la carpeta de fase para vincular ISO.');
        }

        $controlsFolder = $nodesRepo->ensureFolderNode(
            $projectId,
            $phaseNodeId,
            '03-Controles',
            '03 · Controles',
            null,
            30
        );

        $controlsFolderId = (int) ($controlsFolder['id'] ?? 0);
        if ($controlsFolderId <= 0) {
            throw new \InvalidArgumentException('No se pudo garantizar la carpeta estándar de controles para ISO.');
        }

        $lastIsoNodeId = null;
        foreach ($isoNodes as $index => $definition) {
            $isoDefinition = array_merge(
                [
                    'node_type' => 'iso_item',
                    'sort_order' => ($index + 1) * 10,
                ],
                $definition
            );
            $lastIsoNodeId = $nodesRepo->ensureIsoItem(
                $projectId,
                $controlsFolderId,
                $isoDefinition
            );
        }

        if ($lastIsoNodeId === null) {
            throw new \InvalidArgumentException('No se pudo materializar el nodo ISO solicitado.');
        }

        return (int) $lastIsoNodeId;
    }

    public function refreshLifecycle(array $project, ?array $nodeSnapshot = null): array
    {
        $projectId = (int) ($project['id'] ?? 0);
        if ($projectId <= 0) {
            return ['project_progress' => (float) ($project['progress'] ?? 0), 'phases' => []];
        }

        $definitions = $this->phaseDefinitions($project);
        $phaseCodes = array_map(static fn ($definition) => $definition['code'], $definitions);

        $nodesRepo = new ProjectNodesRepository($this->db);
        $this->ensurePhaseFolders($projectId, $definitions, $nodesRepo);

        foreach (array_keys(self::ISO_ACTION_NODE_MAP) as $action) {
            $this->ensureIsoNode($projectId, $action, $project);
        }

        $snapshot = $nodesRepo->snapshot($projectId)['nodes'] ?? [];
        $nodesByCode = $this->indexNodesByCode($snapshot);
        $actionNodes = $this->ensureActionNodes($projectId, $project, $nodesByCode);
        $actionStatus = $this->actionStatuses($projectId);

        $this->updateIsoNodeStatuses($projectId, $actionNodes, $actionStatus);

        $pendingChanges = $actionStatus['design_changes']['pending'] ?? false;
        $phaseAssignments = $this->actionPhaseAssignments($definitions);
        $phases = [];

        foreach ($definitions as $key => $definition) {
            $actionsForPhase = array_keys(array_filter($phaseAssignments, static fn ($phaseCode) => $phaseCode === $definition['code']));
            $done = 0;
            $blockers = [];

            foreach ($actionsForPhase as $action) {
                $complete = (bool) ($actionStatus[$action]['complete'] ?? false);
                if ($complete) {
                    $done++;
                } else {
                    $blockers[] = $action;
                }
            }

            $progress = empty($actionsForPhase) ? 0.0 : ($done / count($actionsForPhase)) * 100.0;
            $status = 'pendiente';

            if (!empty($actionsForPhase)) {
                $status = $done === count($actionsForPhase) && !$pendingChanges ? 'completado' : 'en_progreso';
            }

            if ($pendingChanges) {
                $blockers[] = 'design_changes';
            }

            $phases[] = [
                'key' => $key,
                'label' => $definition['title'],
                'node' => $nodesByCode[$definition['code']] ?? null,
                'progress' => round($progress, 1),
                'status' => $status,
                'blockers' => array_values(array_unique($blockers)),
            ];

            $phaseNode = $nodesByCode[$definition['code']] ?? null;
            if ($phaseNode !== null) {
                $nodesRepo->updateNodeStatus($projectId, (int) ($phaseNode['id'] ?? 0), $status);
            }
        }

        $projectProgress = empty($phases)
            ? 0.0
            : array_sum(array_column($phases, 'progress')) / count($phases);

        (new ProjectsRepository($this->db))->persistProgress($projectId, $projectProgress);

        return [
            'project_progress' => round($projectProgress, 1),
            'phases' => $phases,
            'phase_codes' => $phaseCodes,
        ];
    }

    private function ensureActionNodes(int $projectId, array $project, array $nodesByCode): array
    {
        $definitions = $this->phaseDefinitions($project);
        $phaseAssignments = $this->actionPhaseAssignments($definitions);
        $nodes = [];

        foreach (self::ISO_ACTION_NODE_MAP as $action => $path) {
            $phaseCode = $phaseAssignments[$action] ?? null;
            if ($phaseCode === null) {
                continue;
            }

            $nodes[$action] = $this->ensureIsoNode($projectId, $action, $project);
        }

        return $nodes;
    }

    private function actionPhaseAssignments(array $definitions): array
    {
        $codes = array_map(static fn ($definition) => $definition['code'], $definitions);
        $assignments = [];

        foreach (self::ACTION_PHASE_ORDER as $action => $position) {
            if ($position === -1) {
                $assignments[$action] = end($codes) ?: null;
                continue;
            }

            $assignments[$action] = $codes[$position] ?? (end($codes) ?: null);
        }

        return $assignments;
    }

    private function phaseDefinitions(array $project): array
    {
        $methodology = $this->normalizeMethodology($project['methodology'] ?? 'cascada');
        $configPhases = (new ConfigService($this->db))->getConfig()['delivery']['phases'][$methodology] ?? [];

        $definitions = [];
        foreach ($configPhases as $index => $phaseName) {
            $normalized = $this->normalizePhaseKey($phaseName);
            $base = self::PHASE_NODE_MAP[$methodology][$normalized] ?? null;
            $code = $base['code'] ?? strtoupper('PHASE-' . $normalized);
            $title = $base['title'] ?? $phaseName;
            $definitions[$normalized] = [
                'code' => $code,
                'title' => $title,
                'iso_clause' => $base['iso'] ?? null,
                'sort_order' => $base['sort'] ?? (($index + 1) * 10),
            ];
        }

        if (empty($definitions)) {
            $definitions['inicio'] = ['code' => '01-INICIO', 'title' => 'Inicio', 'iso_clause' => null, 'sort_order' => 10];
        }

        return $definitions;
    }

    private function actionStatuses(int $projectId): array
    {
        $inputsRepo = new DesignInputsRepository($this->db);
        $controlsRepo = new DesignControlsRepository($this->db);
        $changesRepo = new DesignChangesRepository($this->db);

        $reviewApproved = $controlsRepo->countByTypeAndResult($projectId, 'revision', 'aprobado') > 0;
        $verificationApproved = $controlsRepo->countByTypeAndResult($projectId, 'verificacion', 'aprobado') > 0;
        $validationApproved = $controlsRepo->countByTypeAndResult($projectId, 'validacion', 'aprobado') > 0;
        $pendingChanges = $changesRepo->pendingCount($projectId) > 0;

        return [
            'design_inputs' => ['complete' => $inputsRepo->countByProject($projectId) > 0],
            'design_review' => ['complete' => $reviewApproved],
            'design_verification' => ['complete' => $verificationApproved],
            'design_validation' => ['complete' => $validationApproved],
            'design_changes' => ['complete' => !$pendingChanges, 'pending' => $pendingChanges],
        ];
    }

    private function updateIsoNodeStatuses(int $projectId, array $actionNodeIds, array $actionStatuses): void
    {
        $nodesRepo = new ProjectNodesRepository($this->db);

        foreach ($actionNodeIds as $action => $nodeId) {
            $status = 'en_progreso';
            $complete = (bool) ($actionStatuses[$action]['complete'] ?? false);
            $pending = (bool) ($actionStatuses[$action]['pending'] ?? false);

            if ($complete && !$pending) {
                $status = 'completado';
            } elseif ($pending) {
                $status = 'bloqueado';
            }

            $nodesRepo->updateNodeStatus($projectId, (int) $nodeId, $status);
        }
    }

    private function normalizeMethodology(string $methodology): string
    {
        return match (strtolower(trim($methodology))) {
            'scrum' => 'scrum',
            'kanban' => 'kanban',
            'convencional', 'waterfall', 'cascada' => 'cascada',
            default => 'cascada',
        };
    }

    private function normalizePhaseKey(string $phase): string
    {
        $normalized = strtolower(trim($phase));
        $normalized = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $normalized);

        return preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: $normalized;
    }

    private function indexNodesByCode(array $nodes): array
    {
        $indexed = [];
        $stack = $nodes;

        while (!empty($stack)) {
            $current = array_pop($stack);
            $code = $current['code'] ?? '';
            if ($code !== '') {
                $indexed[$code] = $current;
            }

            foreach ($current['children'] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        return $indexed;
    }

    private function ensurePhaseFolders(int $projectId, array $definitions, ProjectNodesRepository $nodesRepo): array
    {
        $ids = [];

        foreach ($definitions as $definition) {
            $node = $nodesRepo->ensurePhaseFolder($projectId, (string) ($definition['code'] ?? ''), [
                'title' => (string) ($definition['title'] ?? ''),
                'iso_clause' => $definition['iso_clause'] ?? null,
                'sort_order' => (int) ($definition['sort_order'] ?? 0),
            ]);
            $ids[$definition['code']] = (int) ($node['id'] ?? 0);
        }

        return $ids;
    }
}
