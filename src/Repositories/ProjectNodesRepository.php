<?php

declare(strict_types=1);

class ProjectNodesRepository
{
    private const REQUIRED_CLAUSES = ['8.3.2', '8.3.4', '8.3.5', '8.3.6'];

    public function __construct(private Database $db)
    {
    }

    public function createBaseTree(int $projectId, string $methodology): void
    {
        if (!$this->db->tableExists('project_nodes')) {
            return;
        }

        if ($this->projectHasNodes($projectId)) {
            return;
        }

        foreach ($this->baseTreeDefinition($methodology) as $definition) {
            $this->materializeNodeTree($projectId, $definition, null);
        }
    }

    public function snapshot(int $projectId): array
    {
        if (!$this->db->tableExists('project_nodes')) {
            return [
                'nodes' => [],
                'pending_critical' => [],
            ];
        }

        return [
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

        $code = $this->generateChildCode($projectId, $parent, $title, 'folder');
        $sortOrder = $this->nextSortOrder($projectId, $parentId);

        return $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, code, node_type, iso_clause, title, description, sort_order, created_by)
             VALUES (:project_id, :parent_id, :code, :node_type, :iso_clause, :title, :description, :sort_order, :created_by)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
                ':code' => $code,
                ':node_type' => 'folder',
                ':iso_clause' => $isoClause ?: $this->resolveIsoClause($projectId, $parentId),
                ':title' => $title,
                ':description' => $description,
                ':sort_order' => $sortOrder,
                ':created_by' => $userId ?: null,
            ]
        );
    }

    public function storeFile(int $projectId, int $parentId, array $file, ?int $userId = null): array
    {
        $this->assertTable();
        $parent = $this->findNode($projectId, $parentId);

        if (!$parent || $parent['node_type'] !== 'folder' || $this->isRestrictedContainer((string) ($parent['code'] ?? ''))) {
            throw new \InvalidArgumentException('Selecciona una carpeta válida para adjuntar.');
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Selecciona un archivo válido para subir.');
        }

        $originalName = (string) ($file['name'] ?? 'archivo');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $safeName = $safeName !== '' ? $safeName : 'documento';

        $code = $this->generateChildCode($projectId, $parent, $safeName, 'file');
        $sortOrder = $this->nextSortOrder($projectId, $parentId);

        $nodeId = $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, code, node_type, iso_clause, title, description, sort_order, created_by)
             VALUES (:project_id, :parent_id, :code, :node_type, :iso_clause, :title, :description, :sort_order, :created_by)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
                ':code' => $code,
                ':node_type' => 'file',
                ':iso_clause' => $this->resolveIsoClause($projectId, $parentId),
                ':title' => $safeName,
                ':description' => null,
                ':sort_order' => $sortOrder,
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
            'SELECT id, parent_id, code, node_type, iso_clause, title, description, file_path, created_at
             FROM project_nodes
             WHERE project_id = :project
             ORDER BY parent_id IS NULL DESC, sort_order ASC, id ASC',
            [':project' => $projectId]
        );

        $folders = [];
        $filesByFolder = [];

        foreach ($nodes as $node) {
            if ($node['node_type'] === 'file') {
                $parent = (int) ($node['parent_id'] ?? 0);
                $filesByFolder[$parent][] = [
                    'id' => (int) $node['id'],
                    'code' => $node['code'] ?? '',
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
                'code' => $node['code'] ?? '',
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

    private function baseTreeDefinition(string $methodology): array
    {
        return [
            [
                'code' => '01-INICIO',
                'title' => '01 · Inicio',
                'node_type' => 'folder',
                'iso_clause' => null,
                'description' => null,
                'sort_order' => 10,
                'children' => [
                    [
                        'code' => '01-INICIO-PROPUESTA-CONTRATO',
                        'title' => 'Propuesta y contrato',
                        'node_type' => 'folder',
                        'iso_clause' => null,
                        'description' => null,
                        'sort_order' => 10,
                        'children' => [],
                    ],
                    [
                        'code' => '01-INICIO-CONTEXTO-NEGOCIO',
                        'title' => 'Contexto del negocio',
                        'node_type' => 'folder',
                        'iso_clause' => null,
                        'description' => null,
                        'sort_order' => 20,
                        'children' => [],
                    ],
                    [
                        'code' => '01-INICIO-STAKEHOLDERS',
                        'title' => 'Stakeholders',
                        'node_type' => 'folder',
                        'iso_clause' => null,
                        'description' => null,
                        'sort_order' => 30,
                        'children' => [],
                    ],
                    [
                        'code' => '01-INICIO-DECISIONES-INICIALES',
                        'title' => 'Decisiones iniciales',
                        'node_type' => 'folder',
                        'iso_clause' => null,
                        'description' => null,
                        'sort_order' => 40,
                        'children' => [],
                    ],
                ],
            ],
            [
                'code' => '02-PLANIFICACION',
                'title' => '02 · Planificación',
                'node_type' => 'folder',
                'iso_clause' => '8.3',
                'description' => null,
                'sort_order' => 20,
                'children' => [
                    [
                        'code' => '02-PLANIFICACION-ALCANCE',
                        'title' => 'Alcance',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.2',
                        'description' => null,
                        'sort_order' => 10,
                        'children' => [],
                    ],
                    [
                        'code' => '02-PLANIFICACION-CRONOGRAMA',
                        'title' => 'Cronograma',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.3',
                        'description' => null,
                        'sort_order' => 20,
                        'children' => [],
                    ],
                    [
                        'code' => '02-PLANIFICACION-PRESUPUESTO',
                        'title' => 'Presupuesto',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.3',
                        'description' => null,
                        'sort_order' => 30,
                        'children' => [],
                    ],
                    [
                        'code' => '02-PLANIFICACION-RIESGOS',
                        'title' => 'Riesgos',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.2',
                        'description' => null,
                        'sort_order' => 40,
                        'children' => [],
                    ],
                    [
                        'code' => '02-PLANIFICACION-PLAN-CALIDAD',
                        'title' => 'Plan de calidad',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 50,
                        'children' => [],
                    ],
                ],
            ],
            [
                'code' => '03-EJECUCION',
                'title' => '03 · Ejecución',
                'node_type' => 'folder',
                'iso_clause' => null,
                'description' => null,
                'sort_order' => 30,
                'children' => [
                    [
                        'code' => '03-EJECUCION-ENTREGABLES',
                        'title' => 'Entregables',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.5',
                        'description' => null,
                        'sort_order' => 10,
                        'children' => [],
                    ],
                    [
                        'code' => '03-EJECUCION-EVIDENCIAS-TECNICAS',
                        'title' => 'Evidencias técnicas',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.5',
                        'description' => null,
                        'sort_order' => 20,
                        'children' => [],
                    ],
                    [
                        'code' => '03-EJECUCION-PRUEBAS',
                        'title' => 'Pruebas',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.5',
                        'description' => null,
                        'sort_order' => 30,
                        'children' => [],
                    ],
                    [
                        'code' => '03-EJECUCION-APROBACIONES-PARCIALES',
                        'title' => 'Aprobaciones parciales',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 40,
                        'children' => [],
                    ],
                ],
            ],
            [
                'code' => '04-SEGUIMIENTO-CONTROL',
                'title' => '04 · Seguimiento y Control',
                'node_type' => 'folder',
                'iso_clause' => '8.3.4',
                'description' => null,
                'sort_order' => 40,
                'children' => [
                    [
                        'code' => '04-SEGUIMIENTO-CONTROL-AVANCE-KPIS',
                        'title' => 'Avance y KPIs',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 10,
                        'children' => [],
                    ],
                    [
                        'code' => '04-SEGUIMIENTO-CONTROL-CAMBIOS',
                        'title' => 'Control de cambios',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.6',
                        'description' => null,
                        'sort_order' => 20,
                        'children' => [],
                    ],
                    [
                        'code' => '04-SEGUIMIENTO-CONTROL-RIESGOS-ACTIVOS',
                        'title' => 'Riesgos activos',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 30,
                        'children' => [],
                    ],
                    [
                        'code' => '04-SEGUIMIENTO-CONTROL-REUNIONES-ACTAS',
                        'title' => 'Reuniones y actas',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 40,
                        'children' => [],
                    ],
                ],
            ],
            [
                'code' => '05-CIERRE',
                'title' => '05 · Cierre',
                'node_type' => 'folder',
                'iso_clause' => null,
                'description' => null,
                'sort_order' => 50,
                'children' => [
                    [
                        'code' => '05-CIERRE-ENTREGABLES-FINALES',
                        'title' => 'Entregables finales',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.5',
                        'description' => null,
                        'sort_order' => 10,
                        'children' => [],
                    ],
                    [
                        'code' => '05-CIERRE-APROBACION-FINAL',
                        'title' => 'Aprobación final',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 20,
                        'children' => [],
                    ],
                    [
                        'code' => '05-CIERRE-LECCIONES-APRENDIDAS',
                        'title' => 'Lecciones aprendidas',
                        'node_type' => 'folder',
                        'iso_clause' => null,
                        'description' => null,
                        'sort_order' => 30,
                        'children' => [],
                    ],
                    [
                        'code' => '05-CIERRE-CIERRE-ADMINISTRATIVO',
                        'title' => 'Cierre administrativo',
                        'node_type' => 'folder',
                        'iso_clause' => null,
                        'description' => null,
                        'sort_order' => 40,
                        'children' => [],
                    ],
                ],
            ],
        ];
    }

    private function materializeNodeTree(int $projectId, array $definition, ?string $parentCode): void
    {
        $this->ensureNode(
            $projectId,
            (string) $definition['code'],
            (string) $definition['title'],
            (string) $definition['node_type'],
            $parentCode,
            $definition['iso_clause'] ?? null,
            $definition['description'] ?? null,
            (int) ($definition['sort_order'] ?? 0)
        );

        foreach ($definition['children'] ?? [] as $child) {
            $this->materializeNodeTree($projectId, $child, (string) $definition['code']);
        }
    }

    private function ensureNode(
        int $projectId,
        string $code,
        string $title,
        string $nodeType,
        ?string $parentCode,
        ?string $isoClause,
        ?string $description,
        int $sortOrder
    ): int {
        $normalizedCode = trim($code);
        if ($normalizedCode === '') {
            throw new \InvalidArgumentException('El código del nodo no puede estar vacío.');
        }

        $parentId = null;
        if ($parentCode !== null) {
            $parent = $this->db->fetchOne(
                'SELECT id FROM project_nodes WHERE project_id = :project AND code = :code LIMIT 1',
                [
                    ':project' => $projectId,
                    ':code' => $parentCode,
                ]
            );
            $parentId = $parent ? (int) ($parent['id'] ?? 0) : null;
        }

        $existing = $this->db->fetchOne(
            'SELECT id, parent_id, node_type, iso_clause, title, description, sort_order FROM project_nodes WHERE project_id = :project AND code = :code LIMIT 1',
            [
                ':project' => $projectId,
                ':code' => $normalizedCode,
            ]
        );

        if ($existing) {
            $existingSort = (int) ($existing['sort_order'] ?? 0);
            if ($sortOrder > 0 && $existingSort !== $sortOrder) {
                $this->db->execute(
                    'UPDATE project_nodes SET sort_order = :sort_order WHERE id = :id',
                    [
                        ':sort_order' => $sortOrder,
                        ':id' => (int) $existing['id'],
                    ]
                );
            }

            return (int) $existing['id'];
        }

        $order = $sortOrder > 0 ? $sortOrder : $this->nextSortOrder($projectId, $parentId);

        return $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, code, node_type, iso_clause, title, description, sort_order)
             VALUES (:project_id, :parent_id, :code, :node_type, :iso_clause, :title, :description, :sort_order)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
                ':code' => $normalizedCode,
                ':node_type' => $nodeType,
                ':iso_clause' => $isoClause,
                ':title' => $title,
                ':description' => $description,
                ':sort_order' => $order,
            ]
        );
    }

    private function nextSortOrder(int $projectId, ?int $parentId): int
    {
        $sql = 'SELECT COALESCE(MAX(sort_order), 0) AS max_order FROM project_nodes WHERE project_id = :project AND parent_id ';
        $params = [':project' => $projectId];

        if ($parentId === null) {
            $sql .= 'IS NULL';
        } else {
            $sql .= '= :parent';
            $params[':parent'] = $parentId;
        }

        $result = $this->db->fetchOne($sql, $params);

        return ((int) ($result['max_order'] ?? 0)) + 1;
    }

    private function projectHasNodes(int $projectId): bool
    {
        $result = $this->db->fetchOne(
            'SELECT id FROM project_nodes WHERE project_id = :project LIMIT 1',
            [':project' => $projectId]
        );

        return $result !== null;
    }

    private function generateChildCode(int $projectId, ?array $parent, string $title, string $nodeType): string
    {
        $parentCode = $parent && !empty($parent['code']) ? $parent['code'] . '.' : '';
        $slug = $this->slugifyCode($title);
        $candidate = $parentCode . $slug;

        if (!$this->codeExists($projectId, $candidate)) {
            return $candidate;
        }

        $suffix = 2;
        while ($this->codeExists($projectId, $candidate . '-' . $suffix)) {
            $suffix++;
        }

        return $candidate . '-' . $suffix;
    }

    private function codeExists(int $projectId, string $code): bool
    {
        $found = $this->db->fetchOne(
            'SELECT id FROM project_nodes WHERE project_id = :project AND code = :code LIMIT 1',
            [
                ':project' => $projectId,
                ':code' => $code,
            ]
        );

        return (bool) $found;
    }

    private function slugifyCode(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        $normalized = strtolower(trim((string) $normalized));
        $normalized = preg_replace('/[^a-z0-9]+/', '.', $normalized);
        $normalized = trim((string) $normalized, '.');

        return $normalized !== '' ? $normalized : 'nodo';
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
            'SELECT id, project_id, parent_id, node_type, iso_clause, code FROM project_nodes WHERE id = :id AND project_id = :project_id',
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

    private function isRestrictedContainer(string $code): bool
    {
        $normalized = strtolower(trim($code));

        return $normalized === 'project'
            || str_starts_with($normalized, 'metodologia.')
            || str_starts_with($normalized, 'fase.');
    }
}
