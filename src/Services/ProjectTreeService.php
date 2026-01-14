<?php

declare(strict_types=1);

class ProjectTreeService
{
    public const NODE_TYPE_FOLDER = 'folder';
    public const NODE_TYPE_FILE = 'file';
    public const NODE_TYPE_ISO_CONTROL = 'iso_control';
    public const NODE_TYPE_METADATA = 'metadata';

    public const CONTENT_TYPE_DOCUMENT = 'document';
    public const CONTENT_TYPE_CONTROL = 'control';
    public const CONTENT_TYPE_EVIDENCE = 'evidence';
    public const CONTENT_TYPE_CHANGE = 'change';

    private const SCRUM_SPRINT_PREFIX = 'SPRINT-';
    private const SCRUM_SPRINT_CONTAINER = '03-SPRINTS';

    public function __construct(private Database $db)
    {
    }

    public function bootstrapFreshTree(int $projectId, string $methodology, ?int $createdBy = null): array
    {
        $nodesRepo = new ProjectNodesRepository($this->db);
        $normalizedMethodology = $this->normalizeMethodology($methodology);

        $nodesRepo->hardResetTree($projectId);

        $rootId = $nodesRepo->createNode([
            'project_id' => $projectId,
            'parent_id' => null,
            'code' => 'ROOT',
            'title' => 'Raíz del proyecto',
            'node_type' => self::NODE_TYPE_FOLDER,
            'iso_clause' => null,
            'description' => null,
            'sort_order' => 1,
            'created_by' => $createdBy,
        ]);

        $phases = $this->phaseDefinitions($normalizedMethodology);

        foreach ($phases as $index => $phase) {
            $phaseId = $nodesRepo->createNode([
                'project_id' => $projectId,
                'parent_id' => $rootId,
                'code' => $phase['code'],
                'title' => $phase['title'],
                'node_type' => self::NODE_TYPE_FOLDER,
                'iso_clause' => $phase['iso_clause'],
                'description' => $phase['description'],
                'sort_order' => ($index + 1) * 10,
                'created_by' => $createdBy,
            ]);

            foreach ($this->phaseSubfolders($phase['code']) as $subIndex => $child) {
                $nodesRepo->createNode([
                    'project_id' => $projectId,
                    'parent_id' => $phaseId,
                    'code' => $phase['code'] . '-' . $child['code'],
                    'title' => $child['title'],
                    'node_type' => self::NODE_TYPE_FOLDER,
                    'iso_clause' => $child['iso_clause'],
                    'description' => $child['description'],
                    'sort_order' => ($subIndex + 1) * 10,
                    'created_by' => $createdBy,
                ]);
            }

            if ($normalizedMethodology === 'scrum' && ($phase['code'] ?? '') === self::SCRUM_SPRINT_CONTAINER) {
                $this->createSprintNodes($projectId, $phaseId, 1, $createdBy);
            }
        }

        return [];
    }

    public function createSprintNodes(int $projectId, int $parentId, int $number, ?int $createdBy = null): array
    {
        $nodesRepo = new ProjectNodesRepository($this->db);
        $code = self::SCRUM_SPRINT_PREFIX . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        $title = 'Sprint ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);

        $sprintId = $nodesRepo->createNode([
            'project_id' => $projectId,
            'parent_id' => $parentId,
            'code' => $code,
            'title' => $title,
            'node_type' => self::NODE_TYPE_FOLDER,
            'iso_clause' => null,
            'description' => null,
            'sort_order' => $number * 10,
            'created_by' => $createdBy,
        ]);

        foreach ($this->phaseSubfolders($code) as $subIndex => $child) {
            $nodesRepo->createNode([
                'project_id' => $projectId,
                'parent_id' => $sprintId,
                'code' => $code . '-' . $child['code'],
                'title' => $child['title'],
                'node_type' => self::NODE_TYPE_FOLDER,
                'iso_clause' => $child['iso_clause'],
                'description' => $child['description'],
                'sort_order' => ($subIndex + 1) * 10,
                'created_by' => $createdBy,
            ]);
        }

        return $nodesRepo->findNodeByCode($projectId, $code) ?? [];
    }

    public function summarizeProgress(int $projectId): array
    {
        $nodesRepo = new ProjectNodesRepository($this->db);
        $tree = $nodesRepo->treeWithFiles($projectId);

        if (empty($tree)) {
            return ['project_progress' => 0.0, 'phases' => []];
        }

        $phaseProgress = [];
        $projectAccum = 0.0;

        foreach ($tree[0]['children'] ?? [] as $phase) {
            $metrics = $this->computeNodeProgress($phase);
            $phaseProgress[] = [
                'code' => $phase['code'] ?? '',
                'title' => $phase['name'] ?? $phase['title'] ?? $phase['code'] ?? 'Fase',
                'progress' => $metrics['percent'],
                'status' => $metrics['status'],
                'pending' => $metrics['pending'],
                'completed' => $metrics['completed'],
                'total' => $metrics['total'],
            ];
            $projectAccum += $metrics['percent'];
        }

        $projectProgress = empty($phaseProgress) ? 0.0 : round($projectAccum / count($phaseProgress), 1);

        return [
            'project_progress' => $projectProgress,
            'phases' => $phaseProgress,
        ];
    }

    private function computeNodeProgress(array $node): array
    {
        $standardSubfolders = $this->standardSubfolderSuffixes();
        $children = $node['children'] ?? [];
        $childrenBySuffix = [];

        foreach ($children as $child) {
            $suffix = $this->extractStandardSuffix((string) ($child['code'] ?? ''));
            if ($suffix !== null && in_array($suffix, $standardSubfolders, true)) {
                $childrenBySuffix[$suffix] = $child;
            }
        }

        $completed = 0;
        $pending = [];

        foreach ($standardSubfolders as $suffix) {
            if (!isset($childrenBySuffix[$suffix])) {
                $pending[] = $suffix;
                continue;
            }

            $child = $childrenBySuffix[$suffix];
            if ($this->childHasEvidence($child)) {
                $completed++;
            } else {
                $pending[] = $child['name'] ?? $child['title'] ?? $suffix;
            }
        }

        $total = count($standardSubfolders);
        $percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
        $status = $completed >= $total && $total > 0 ? 'completado' : ($completed > 0 ? 'en_progreso' : 'pendiente');

        return [
            'percent' => $percent,
            'status' => $status,
            'pending' => $pending,
            'completed' => $completed,
            'total' => $total,
        ];
    }

    private function standardSubfolderSuffixes(): array
    {
        return [
            '01-ENTRADAS',
            '02-PLANIFICACION',
            '03-CONTROLES',
            '04-EVIDENCIAS',
            '05-CAMBIOS',
        ];
    }

    private function extractStandardSuffix(string $code): ?string
    {
        if ($code === '') {
            return null;
        }

        $parts = explode('-', $code);
        if (count($parts) < 2) {
            return null;
        }

        return implode('-', array_slice($parts, -2));
    }

    private function childHasEvidence(array $child): bool
    {
        foreach (($child['files'] ?? []) as $file) {
            if (($file['document_status'] ?? '') === 'aprobado') {
                return true;
            }
        }

        foreach (($child['children'] ?? []) as $nested) {
            if (($nested['node_type'] ?? '') === self::NODE_TYPE_ISO_CONTROL && ($nested['status'] ?? '') === 'completado') {
                return true;
            }
        }

        return false;
    }

    private function phaseDefinitions(string $methodology): array
    {
        if ($methodology === 'scrum') {
            return [
                ['code' => '01-INICIO', 'title' => '01 · Inicio', 'iso_clause' => null, 'description' => null],
                ['code' => '02-BACKLOG', 'title' => '02 · Backlog', 'iso_clause' => '8.3.2', 'description' => null],
                ['code' => '03-SPRINTS', 'title' => '03 · Sprints', 'iso_clause' => '8.3.4', 'description' => 'Contenedor de sprints'],
                ['code' => '04-CIERRE', 'title' => '04 · Cierre', 'iso_clause' => '8.3.5', 'description' => null],
            ];
        }

        return [
            ['code' => '01-INICIO', 'title' => '01 · Inicio', 'iso_clause' => null, 'description' => null],
            ['code' => '02-PLANIFICACION', 'title' => '02 · Planificación', 'iso_clause' => '8.3', 'description' => null],
            ['code' => '03-DISEÑO', 'title' => '03 · Diseño', 'iso_clause' => '8.3.4', 'description' => 'Controles de diseño'],
            ['code' => '04-EJECUCION', 'title' => '04 · Ejecución', 'iso_clause' => '8.3.5', 'description' => null],
            ['code' => '05-SEGUIMIENTO_Y_CONTROL', 'title' => '05 · Seguimiento y Control', 'iso_clause' => '8.3.4', 'description' => null],
            ['code' => '06-CIERRE', 'title' => '06 · Cierre', 'iso_clause' => '8.3.5', 'description' => null],
        ];
    }

    private function phaseSubfolders(string $phaseCode): array
    {
        $base = [
            ['code' => '01-ENTRADAS', 'title' => '01-Entradas', 'iso_clause' => '8.3.3', 'description' => null],
            ['code' => '02-PLANIFICACION', 'title' => '02-Planificación', 'iso_clause' => '8.3.2', 'description' => null],
            ['code' => '03-CONTROLES', 'title' => '03-Controles', 'iso_clause' => '8.3.4', 'description' => 'Controles ISO 9001'],
            ['code' => '04-EVIDENCIAS', 'title' => '04-Evidencias', 'iso_clause' => '8.3.5', 'description' => null],
            ['code' => '05-CAMBIOS', 'title' => '05-Cambios', 'iso_clause' => '8.3.6', 'description' => null],
        ];

        return $base;
    }

    private function normalizeMethodology(string $methodology): string
    {
        $normalized = strtolower(trim($methodology));

        if ($normalized === 'scrum') {
            return 'scrum';
        }

        return 'cascada';
    }
}
