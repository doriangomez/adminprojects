<?php

declare(strict_types=1);

class ProjectNodesRepository
{
    private const REQUIRED_CLAUSES = ['8.3.2', '8.3.3', '8.3.4', '8.3.5'];

    public function __construct(private Database $db)
    {
    }

    public function synchronizeFromProject(int $projectId, string $methodology, ?string $phase, array $phasesByMethodology): array
    {
        if (!$this->db->tableExists('project_nodes')) {
            return [
                'flags' => $this->defaultIsoFlags(),
                'nodes' => [],
                'pending_critical' => [],
            ];
        }

        $phases = is_array($phasesByMethodology[$methodology] ?? null) ? $phasesByMethodology[$methodology] : [];
        $this->ensureBaseTree($projectId, $phases, $phase);

        return [
            'flags' => $this->defaultIsoFlags(),
            'nodes' => $this->treeWithFiles($projectId),
            'pending_critical' => $this->pendingCriticalNodes($projectId),
        ];
    }

    public function createFolder(int $projectId, string $title, ?int $parentId, ?string $isoClause, ?string $description, ?int $userId = null): int
    {
        $this->assertTable();
        $parent = null;

        if ($parentId !== null) {
            $parent = $this->findNode($projectId, $parentId);
            if (!$parent || $parent['node_type'] !== 'folder') {
                throw new \InvalidArgumentException('La carpeta padre no es válida.');
            }
        }

        return $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, node_type, iso_clause, title, description, created_by)
             VALUES (:project_id, :parent_id, :node_type, :iso_clause, :title, :description, :created_by)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
                ':node_type' => 'folder',
                ':iso_clause' => $isoClause ?: $this->resolveIsoClause($projectId, $parentId),
                ':title' => $title,
                ':description' => $description,
                ':created_by' => $userId ?: null,
            ]
        );
    }

    public function storeFile(int $projectId, int $parentId, array $file, ?int $userId = null): array
    {
        $this->assertTable();
        $parent = $this->findNode($projectId, $parentId);

        if (!$parent || $parent['node_type'] !== 'folder') {
            throw new \InvalidArgumentException('Selecciona una carpeta válida para adjuntar.');
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Selecciona un archivo válido para subir.');
        }

        $originalName = (string) ($file['name'] ?? 'archivo');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $safeName = $safeName !== '' ? $safeName : 'documento';

        $nodeId = $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, node_type, iso_clause, title, description, created_by)
             VALUES (:project_id, :parent_id, :node_type, :iso_clause, :title, :description, :created_by)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
                ':node_type' => 'file',
                ':iso_clause' => $this->resolveIsoClause($projectId, $parentId),
                ':title' => $safeName,
                ':description' => null,
                ':created_by' => $userId ?: null,
            ]
        );

        $targetDir = __DIR__ . '/../../public/storage/projects/' . $projectId . '/' . $nodeId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('No se pudo preparar el directorio de almacenamiento.');
        }

        $targetPath = $targetDir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        $publicPath = '/storage/projects/' . $projectId . '/' . $nodeId . '/' . $safeName;
        $this->db->execute(
            'UPDATE project_nodes SET file_path = :file_path WHERE id = :id',
            [
                ':file_path' => $publicPath,
                ':id' => $nodeId,
            ]
        );

        return [
            'id' => $nodeId,
            'file_name' => $safeName,
            'storage_path' => $publicPath,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function deleteNode(int $projectId, int $nodeId): void
    {
        $this->assertTable();
        $node = $this->findNode($projectId, $nodeId);
        if (!$node) {
            return;
        }

        foreach ($this->descendantIds($projectId, $nodeId) as $idToRemove) {
            $this->removePhysicalFiles($projectId, $idToRemove);
        }

        $this->db->execute('DELETE FROM project_nodes WHERE id = :id AND project_id = :project_id', [
            ':id' => $nodeId,
            ':project_id' => $projectId,
        ]);
    }

    public function treeWithFiles(int $projectId): array
    {
        if (!$this->db->tableExists('project_nodes')) {
            return [];
        }

        $nodes = $this->db->fetchAll(
            'SELECT id, parent_id, node_type, iso_clause, title, description, file_path, created_at
             FROM project_nodes
             WHERE project_id = :project
             ORDER BY parent_id IS NULL DESC, created_at ASC, id ASC',
            [':project' => $projectId]
        );

        $folders = [];
        $filesByFolder = [];

        foreach ($nodes as $node) {
            if ($node['node_type'] === 'file') {
                $parent = (int) ($node['parent_id'] ?? 0);
                $filesByFolder[$parent][] = [
                    'id' => (int) $node['id'],
                    'file_name' => $node['title'],
                    'storage_path' => $node['file_path'],
                    'created_at' => $node['created_at'],
                    'iso_clause' => $node['iso_clause'] ?? null,
                ];
                continue;
            }

            $nodeId = (int) $node['id'];
            $folders[$nodeId] = [
                'id' => $nodeId,
                'parent_id' => $node['parent_id'],
                'name' => $node['title'],
                'iso_code' => $node['iso_clause'],
                'status' => 'pendiente',
                'description' => $node['description'] ?? null,
                'files' => [],
                'children' => [],
            ];
        }

        foreach ($folders as $folderId => &$folder) {
            $folder['files'] = $filesByFolder[$folderId] ?? [];
        }
        unset($folder);

        $tree = [];
        foreach ($folders as $folderId => &$folder) {
            $parentId = $folder['parent_id'];
            if ($parentId !== null && isset($folders[(int) $parentId])) {
                $folders[(int) $parentId]['children'][] = &$folder;
            } else {
                $tree[] = &$folder;
            }
        }
        unset($folder);

        foreach ($tree as &$root) {
            $this->refreshFolderStatus($root);
        }
        unset($root);

        return $tree;
    }

    public function pendingCriticalNodes(int $projectId): array
    {
        $missing = [];
        foreach (self::REQUIRED_CLAUSES as $clause) {
            if ($this->countEvidenceForClause($projectId, $clause) === 0) {
                $missing[] = [
                    'iso_clause' => $clause,
                    'title' => 'Evidencia requerida en ' . $clause,
                ];
            }
        }

        return $missing;
    }

    private function ensureBaseTree(int $projectId, array $phases, ?string $currentPhase): void
    {
        $rootId = $this->ensureFolder($projectId, null, 'Diseño y Desarrollo (ISO 9001 · 8.3)', '8.3', 'Estructura documental ISO 9001 8.3');
        $clauses = [
            '8.3.2' => 'Planificación',
            '8.3.3' => 'Entradas',
            '8.3.4' => 'Controles',
            '8.3.5' => 'Salidas',
            '8.3.6' => 'Cambios',
        ];

        foreach ($clauses as $code => $label) {
            $this->ensureFolder($projectId, $rootId, $code . ' ' . $label, $code, null);
        }

        $phasesRoot = $this->ensureFolder($projectId, null, 'Fases del Proyecto', null, 'Estructura por fases');
        foreach ($phases as $phase) {
            $this->ensureFolder($projectId, $phasesRoot, ucfirst((string) $phase), null, null);
        }

        $this->ensureFolder($projectId, null, 'Riesgos', null, 'Documentos de riesgos del proyecto');
    }

    private function ensureFolder(int $projectId, ?int $parentId, string $title, ?string $isoClause, ?string $description): int
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM project_nodes WHERE project_id = :project AND parent_id ' . ($parentId === null ? 'IS NULL' : '= :parent') . ' AND title = :title AND node_type = "folder" LIMIT 1',
            $parentId === null
                ? [
                    ':project' => $projectId,
                    ':title' => $title,
                ]
                : [
                    ':project' => $projectId,
                    ':parent' => $parentId,
                    ':title' => $title,
                ]
        );

        if ($existing) {
            return (int) $existing['id'];
        }

        return $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, node_type, iso_clause, title, description)
             VALUES (:project_id, :parent_id, :node_type, :iso_clause, :title, :description)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
                ':node_type' => 'folder',
                ':iso_clause' => $isoClause,
                ':title' => $title,
                ':description' => $description,
            ]
        );
    }

    private function folderStatus(int $folderId, array $filesByFolder): string
    {
        $hasFiles = !empty($filesByFolder[$folderId]);
        return $hasFiles ? 'completado' : 'pendiente';
    }

    private function refreshFolderStatus(array &$folder): bool
    {
        $hasEvidence = !empty($folder['files']);

        foreach ($folder['children'] as &$child) {
            $hasEvidence = $this->refreshFolderStatus($child) || $hasEvidence;
        }
        unset($child);

        $folder['status'] = $hasEvidence ? 'completado' : 'pendiente';

        return $hasEvidence;
    }

    private function findNode(int $projectId, int $nodeId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, project_id, parent_id, node_type, iso_clause FROM project_nodes WHERE id = :id AND project_id = :project_id',
            [
                ':id' => $nodeId,
                ':project_id' => $projectId,
            ]
        );
    }

    private function resolveIsoClause(int $projectId, ?int $nodeId): ?string
    {
        $currentId = $nodeId;

        while ($currentId !== null) {
            $node = $this->findNode($projectId, $currentId);
            if (!$node) {
                break;
            }

            if (!empty($node['iso_clause'])) {
                return (string) $node['iso_clause'];
            }

            $currentId = $node['parent_id'] !== null ? (int) $node['parent_id'] : null;
        }

        return null;
    }

    private function removePhysicalFiles(int $projectId, int $nodeId): void
    {
        $baseDir = __DIR__ . '/../../public/storage/projects/' . $projectId . '/' . $nodeId;
        if (is_dir($baseDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getRealPath());
                } else {
                    @unlink($file->getRealPath());
                }
            }

            @rmdir($baseDir);
        }
    }

    private function descendantIds(int $projectId, int $nodeId): array
    {
        $nodes = $this->db->fetchAll(
            'SELECT id, parent_id FROM project_nodes WHERE project_id = :project',
            [':project' => $projectId]
        );

        $childrenByParent = [];
        foreach ($nodes as $node) {
            $parent = $node['parent_id'] === null ? null : (int) $node['parent_id'];
            $childrenByParent[$parent][] = (int) $node['id'];
        }

        $stack = [$nodeId];
        $collected = [];

        while ($stack) {
            $current = array_pop($stack);
            $collected[] = $current;
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $collected;
    }

    private function countEvidenceForClause(int $projectId, string $clause): int
    {
        $result = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_nodes WHERE project_id = :project_id AND node_type = "file" AND iso_clause = :clause',
            [
                ':project_id' => $projectId,
                ':clause' => $clause,
            ]
        );

        return (int) ($result['total'] ?? 0);
    }

    private function assertTable(): void
    {
        if (!$this->db->tableExists('project_nodes')) {
            throw new \InvalidArgumentException('No se encontró el repositorio documental del proyecto.');
        }
    }

    private function defaultIsoFlags(): array
    {
        return [
            'design_inputs_defined' => 0,
            'design_review_done' => 0,
            'design_verification_done' => 0,
            'design_validation_done' => 0,
            'legal_requirements' => 0,
            'change_control_required' => 0,
        ];
    }
}
