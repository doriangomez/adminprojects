<?php

declare(strict_types=1);

class ProjectNodesRepository
{
    private const REQUIRED_CLAUSES = ['8.3.2', '8.3.4', '8.3.5', '8.3.6'];
    private const TREE_VERSION = '2024.10';
    private const SCRUM_SPRINT_CONTAINER_CODE = '03-SPRINTS';
    private const SCRUM_SPRINT_CONTAINER_TITLE = '03 · Sprints';
    private const SCRUM_SPRINT_CONTAINER_ORDER = 30;

    public function __construct(private Database $db)
    {
    }

    public function createBaseTree(int $projectId, string $methodology): void
    {
        if (!$this->db->tableExists('project_nodes')) {
            return;
        }

        $normalizedMethodology = $this->normalizeMethodology($methodology);
        $metadata = $this->treeMetadata($projectId);
        $hasNodes = $this->projectHasNodes($projectId);
        $needsReset = !$hasNodes
            || ($metadata['version'] ?? null) !== self::TREE_VERSION
            || ($metadata['methodology'] ?? null) !== $normalizedMethodology;

        if ($needsReset) {
            $this->resetProjectTree($projectId);
            foreach ($this->baseTreeDefinition($normalizedMethodology) as $definition) {
                $this->materializeNodeTree(
                    $projectId,
                    $definition,
                    null,
                    $normalizedMethodology !== 'scrum'
                );
            }
            $this->persistTreeMetadata($projectId, $normalizedMethodology);
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

    public function createSprint(int $projectId, ?int $userId = null): array
    {
        $this->assertTable();

        $container = $this->ensureSprintsContainer($projectId, $userId);
        $nextNumber = $this->nextSprintNumber($projectId, (int) ($container['id'] ?? 0));
        $paddedNumber = str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
        $sprintCode = 'SPRINT-' . $paddedNumber;

        if ($this->codeExists($projectId, $sprintCode)) {
            throw new \InvalidArgumentException('Ya existe un sprint con el número ' . $paddedNumber . '.');
        }

        $sprintId = $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, code, node_type, iso_clause, title, description, sort_order, created_by)
             VALUES (:project_id, :parent_id, :code, :node_type, :iso_clause, :title, :description, :sort_order, :created_by)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $container['id'],
                ':code' => $sprintCode,
                ':node_type' => 'folder',
                ':iso_clause' => null,
                ':title' => 'Sprint ' . $paddedNumber,
                ':description' => null,
                ':sort_order' => $this->nextSortOrder($projectId, $container['id']),
                ':created_by' => $userId,
            ]
        );

        foreach ($this->sprintStructureDefinition() as $child) {
            $this->ensureNode(
                $projectId,
                $sprintCode . '-' . $child['code'],
                $child['title'],
                $child['node_type'],
                $sprintCode,
                $child['iso_clause'] ?? null,
                $child['description'] ?? null,
                (int) ($child['sort_order'] ?? 0),
                $userId
            );
        }

        return [
            'id' => $sprintId,
            'code' => $sprintCode,
            'title' => 'Sprint ' . $paddedNumber,
            'number' => $nextNumber,
        ];
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
        return match ($this->normalizeMethodology($methodology)) {
            'scrum' => $this->scrumTreeDefinition(),
            'kanban' => $this->kanbanTreeDefinition(),
            default => $this->cascadeTreeDefinition(),
        };
    }

    private function cascadeTreeDefinition(): array
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
                        'code' => '02-PLANIFICACION-ALCANCE-APROBADO',
                        'title' => 'Alcance aprobado',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.2',
                        'description' => null,
                        'sort_order' => 10,
                        'children' => [],
                    ],
                    [
                        'code' => '02-PLANIFICACION-EDT-WBS',
                        'title' => 'EDT / WBS',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.2',
                        'description' => null,
                        'sort_order' => 20,
                        'children' => [],
                    ],
                    [
                        'code' => '02-PLANIFICACION-CRONOGRAMA',
                        'title' => 'Cronograma',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.3',
                        'description' => null,
                        'sort_order' => 30,
                        'children' => [],
                    ],
                    [
                        'code' => '02-PLANIFICACION-PRESUPUESTO',
                        'title' => 'Presupuesto',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.3',
                        'description' => null,
                        'sort_order' => 40,
                        'children' => [],
                    ],
                    [
                        'code' => '02-PLANIFICACION-RIESGOS',
                        'title' => 'Riesgos',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.2',
                        'description' => null,
                        'sort_order' => 50,
                        'children' => [],
                    ],
                    [
                        'code' => '02-PLANIFICACION-PLAN-CALIDAD',
                        'title' => 'Plan de calidad',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 60,
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
                        'code' => '04-SEGUIMIENTO-CONTROL-AVANCE-PLAN',
                        'title' => 'Avance vs plan',
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
                        'code' => '04-SEGUIMIENTO-CONTROL-ACTAS-COMITES',
                        'title' => 'Actas y comités',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 40,
                        'children' => [],
                    ],
                    [
                        'code' => '04-SEGUIMIENTO-CONTROL-KPIS',
                        'title' => 'KPIs',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 50,
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

    private function sprintStructureDefinition(): array
    {
        return [
            [
                'code' => 'OBJETIVO',
                'title' => 'Objetivo del sprint',
                'node_type' => 'folder',
                'iso_clause' => '8.3.2',
                'description' => null,
                'sort_order' => 10,
                'children' => [],
            ],
            [
                'code' => 'HISTORIAS',
                'title' => 'Historias',
                'node_type' => 'folder',
                'iso_clause' => '8.3.4',
                'description' => null,
                'sort_order' => 20,
                'children' => [],
            ],
            [
                'code' => 'EVIDENCIAS',
                'title' => 'Evidencias',
                'node_type' => 'folder',
                'iso_clause' => '8.3.5',
                'description' => null,
                'sort_order' => 30,
                'children' => [],
            ],
            [
                'code' => 'REVIEW',
                'title' => 'Review',
                'node_type' => 'folder',
                'iso_clause' => '8.3.6',
                'description' => null,
                'sort_order' => 40,
                'children' => [],
            ],
            [
                'code' => 'RETROSPECTIVA',
                'title' => 'Retrospectiva',
                'node_type' => 'folder',
                'iso_clause' => '8.3.6',
                'description' => null,
                'sort_order' => 50,
                'children' => [],
            ],
        ];
    }

    private function scrumTreeDefinition(): array
    {
        return [
            [
                'code' => '01-DESCUBRIMIENTO',
                'title' => '01 · Descubrimiento',
                'node_type' => 'folder',
                'iso_clause' => null,
                'description' => null,
                'sort_order' => 10,
                'children' => [
                    [
                        'code' => '01-DESCUBRIMIENTO-VISION-PRODUCTO',
                        'title' => 'Visión del producto',
                        'node_type' => 'folder',
                        'iso_clause' => null,
                        'description' => null,
                        'sort_order' => 10,
                        'children' => [],
                    ],
                    [
                        'code' => '01-DESCUBRIMIENTO-CONTEXTO-NEGOCIO',
                        'title' => 'Contexto del negocio',
                        'node_type' => 'folder',
                        'iso_clause' => null,
                        'description' => null,
                        'sort_order' => 20,
                        'children' => [],
                    ],
                    [
                        'code' => '01-DESCUBRIMIENTO-STAKEHOLDERS',
                        'title' => 'Stakeholders',
                        'node_type' => 'folder',
                        'iso_clause' => null,
                        'description' => null,
                        'sort_order' => 30,
                        'children' => [],
                    ],
                    [
                        'code' => '01-DESCUBRIMIENTO-DECISIONES-INICIALES',
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
                'code' => '02-BACKLOG-PRODUCTO',
                'title' => '02 · Backlog del Producto',
                'node_type' => 'folder',
                'iso_clause' => '8.3.2',
                'description' => null,
                'sort_order' => 20,
                'children' => [
                    [
                        'code' => '02-BACKLOG-PRODUCTO-HISTORIAS-USUARIO',
                        'title' => 'Historias de usuario',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.2',
                        'description' => null,
                        'sort_order' => 10,
                        'children' => [],
                    ],
                    [
                        'code' => '02-BACKLOG-PRODUCTO-CRITERIOS-ACEPTACION',
                        'title' => 'Criterios de aceptación',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.2',
                        'description' => null,
                        'sort_order' => 20,
                        'children' => [],
                    ],
                    [
                        'code' => '02-BACKLOG-PRODUCTO-PRIORIZACION',
                        'title' => 'Priorización',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.2',
                        'description' => null,
                        'sort_order' => 30,
                        'children' => [],
                    ],
                    [
                        'code' => '02-BACKLOG-PRODUCTO-REFINAMIENTO',
                        'title' => 'Refinamiento',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.2',
                        'description' => null,
                        'sort_order' => 40,
                        'children' => [],
                    ],
                ],
            ],
            [
                'code' => self::SCRUM_SPRINT_CONTAINER_CODE,
                'title' => self::SCRUM_SPRINT_CONTAINER_TITLE,
                'node_type' => 'folder',
                'iso_clause' => null,
                'description' => null,
                'sort_order' => self::SCRUM_SPRINT_CONTAINER_ORDER,
                'children' => [],
            ],
            [
                'code' => '99-CIERRE',
                'title' => '99 · Cierre',
                'node_type' => 'folder',
                'iso_clause' => null,
                'description' => null,
                'sort_order' => 990,
                'children' => [
                    [
                        'code' => '99-CIERRE-INCREMENTO-FINAL',
                        'title' => 'Incremento final',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.5',
                        'description' => null,
                        'sort_order' => 10,
                        'children' => [],
                    ],
                    [
                        'code' => '99-CIERRE-APROBACION-CLIENTE',
                        'title' => 'Aprobación del cliente',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 20,
                        'children' => [],
                    ],
                    [
                        'code' => '99-CIERRE-METRICAS',
                        'title' => 'Métricas',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 30,
                        'children' => [],
                    ],
                    [
                        'code' => '99-CIERRE-LECCIONES-APRENDIDAS',
                        'title' => 'Lecciones aprendidas',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.6',
                        'description' => null,
                        'sort_order' => 40,
                        'children' => [],
                    ],
                ],
            ],
        ];
    }

    private function kanbanTreeDefinition(): array
    {
        return [
            [
                'code' => '01-BACKLOG',
                'title' => 'Backlog',
                'node_type' => 'folder',
                'iso_clause' => '8.3.2',
                'description' => null,
                'sort_order' => 10,
                'children' => [],
            ],
            [
                'code' => '02-EN-CURSO',
                'title' => 'En curso',
                'node_type' => 'folder',
                'iso_clause' => '8.3.4',
                'description' => null,
                'sort_order' => 20,
                'children' => [],
            ],
            [
                'code' => '03-EN-REVISION',
                'title' => 'En revisión',
                'node_type' => 'folder',
                'iso_clause' => '8.3.5',
                'description' => null,
                'sort_order' => 30,
                'children' => [],
            ],
            [
                'code' => '04-HECHO',
                'title' => 'Hecho',
                'node_type' => 'folder',
                'iso_clause' => '8.3.5',
                'description' => null,
                'sort_order' => 40,
                'children' => [],
            ],
            [
                'code' => '05-MEJORA-CONTINUA',
                'title' => 'Mejora continua',
                'node_type' => 'folder',
                'iso_clause' => '8.3.6',
                'description' => null,
                'sort_order' => 50,
                'children' => [],
            ],
        ];
    }

    private function ensureSprintsContainer(int $projectId, ?int $userId = null): array
    {
        $containerId = $this->ensureNode(
            $projectId,
            self::SCRUM_SPRINT_CONTAINER_CODE,
            self::SCRUM_SPRINT_CONTAINER_TITLE,
            'folder',
            null,
            null,
            null,
            self::SCRUM_SPRINT_CONTAINER_ORDER,
            $userId
        );

        return [
            'id' => $containerId,
            'code' => self::SCRUM_SPRINT_CONTAINER_CODE,
        ];
    }

    private function nextSprintNumber(int $projectId, ?int $containerId): int
    {
        $params = [':project_id' => $projectId];
        $parentFilter = '';

        if ($containerId !== null && $containerId > 0) {
            $parentFilter = ' AND parent_id = :parent_id';
            $params[':parent_id'] = $containerId;
        }

        $nodes = $this->db->fetchAll(
            'SELECT code, title FROM project_nodes WHERE project_id = :project_id AND node_type = "folder"' . $parentFilter,
            $params
        );

        $maxNumber = 0;

        foreach ($nodes as $node) {
            $code = (string) ($node['code'] ?? '');
            $title = (string) ($node['title'] ?? '');

            if (preg_match('/SPRINT[^0-9]*([0-9]+)/i', $code, $matches) || preg_match('/SPRINT[^0-9]*([0-9]+)/i', $title, $matches)) {
                $maxNumber = max($maxNumber, (int) $matches[1]);
            }
        }

        return $maxNumber + 1;
    }

    private function isSprintNode(string $code, ?string $parentCode): bool
    {
        $isSprint = preg_match('/^SPRINT-/i', $code) === 1;
        $parentIsSprint = $parentCode !== null && preg_match('/^SPRINT-/i', $parentCode) === 1;

        return $isSprint || $parentIsSprint;
    }

    private function treeMetadata(int $projectId): array
    {
        if (!$this->db->tableExists('projects')) {
            return [
                'version' => null,
                'methodology' => null,
            ];
        }

        $hasVersionColumn = $this->db->columnExists('projects', 'tree_version');
        $hasMethodologyColumn = $this->db->columnExists('projects', 'tree_methodology');

        if (!$hasVersionColumn && !$hasMethodologyColumn) {
            return [
                'version' => null,
                'methodology' => null,
            ];
        }

        $columns = array_filter([
            $hasVersionColumn ? 'tree_version' : null,
            $hasMethodologyColumn ? 'tree_methodology' : null,
        ]);

        $row = $this->db->fetchOne(
            sprintf('SELECT %s FROM projects WHERE id = :id LIMIT 1', implode(', ', $columns)),
            [':id' => $projectId]
        ) ?: [];

        return [
            'version' => $hasVersionColumn ? ($row['tree_version'] ?? null) : null,
            'methodology' => $hasMethodologyColumn ? $this->normalizeMethodology((string) ($row['tree_methodology'] ?? '')) : null,
        ];
    }

    private function persistTreeMetadata(int $projectId, string $methodology): void
    {
        $hasVersionColumn = $this->db->columnExists('projects', 'tree_version');
        $hasMethodologyColumn = $this->db->columnExists('projects', 'tree_methodology');

        if (!$hasVersionColumn && !$hasMethodologyColumn) {
            return;
        }

        $fields = [];
        $params = [':id' => $projectId];

        if ($hasVersionColumn) {
            $fields[] = 'tree_version = :tree_version';
            $params[':tree_version'] = self::TREE_VERSION;
        }

        if ($hasMethodologyColumn) {
            $fields[] = 'tree_methodology = :tree_methodology';
            $params[':tree_methodology'] = $methodology;
        }

        if (!empty($fields)) {
            $this->db->execute(
                'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = :id',
                $params
            );
        }
    }

    private function resetProjectTree(int $projectId): void
    {
        if (!$this->projectHasNodes($projectId)) {
            return;
        }

        $nodes = $this->db->fetchAll(
            'SELECT id FROM project_nodes WHERE project_id = :project',
            [':project' => $projectId]
        );

        foreach ($nodes as $node) {
            $this->removePhysicalFiles($projectId, (int) ($node['id'] ?? 0));
        }

        $this->db->execute(
            'DELETE FROM project_nodes WHERE project_id = :project',
            [':project' => $projectId]
        );
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

    private function materializeNodeTree(
        int $projectId,
        array $definition,
        ?string $parentCode,
        bool $allowSprintNodes = true
    ): void {
        $code = (string) ($definition['code'] ?? '');
        if (!$allowSprintNodes && $this->isSprintNode($code, $parentCode)) {
            return;
        }

        $this->ensureNode(
            $projectId,
            $code,
            (string) $definition['title'],
            (string) $definition['node_type'],
            $parentCode,
            $definition['iso_clause'] ?? null,
            $definition['description'] ?? null,
            (int) ($definition['sort_order'] ?? 0)
        );

        foreach ($definition['children'] ?? [] as $child) {
            $this->materializeNodeTree($projectId, $child, $code, $allowSprintNodes);
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
        int $sortOrder,
        ?int $createdBy = null
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
            'INSERT INTO project_nodes (project_id, parent_id, code, node_type, iso_clause, title, description, sort_order, created_by)
             VALUES (:project_id, :parent_id, :code, :node_type, :iso_clause, :title, :description, :sort_order, :created_by)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
                ':code' => $normalizedCode,
                ':node_type' => $nodeType,
                ':iso_clause' => $isoClause,
                ':title' => $title,
                ':description' => $description,
                ':sort_order' => $order,
                ':created_by' => $createdBy,
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
