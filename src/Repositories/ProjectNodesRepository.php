<?php

declare(strict_types=1);

class ProjectNodesRepository
{
    private const REQUIRED_CLAUSES = ['8.3.2', '8.3.4', '8.3.5', '8.3.6'];
    private const TREE_VERSION = '2025.02';
    private const STANDARD_SUBPHASE_SUFFIXES = [
        '01-ENTRADAS',
        '02-PLANIFICACION',
        '03-CONTROLES',
        '04-EVIDENCIAS',
        '05-CAMBIOS',
    ];
    private const SCRUM_DISCOVERY_CODE = '01-DISCOVERY';
    private const SCRUM_DISCOVERY_TITLE = '01 · Discovery';
    private const SCRUM_BACKLOG_CODE = '02-BACKLOG';
    private const SCRUM_BACKLOG_TITLE = '02 · Backlog';
    private const SCRUM_SPRINT_CONTAINER_CODE = '03-SPRINTS';
    private const SCRUM_SPRINT_CONTAINER_TITLE = '03 · Sprints';
    private const SCRUM_SPRINT_CONTAINER_ORDER = 20;
    private const SCRUM_REVIEW_CODE = '04-REVIEW';
    private const SCRUM_REVIEW_TITLE = '04 · Review';
    private const SCRUM_RELEASE_CODE = '05-RELEASE';
    private const SCRUM_RELEASE_TITLE = '05 · Release';

    public function __construct(private Database $db)
    {
    }

    public function createNode(array $attributes): int
    {
        $this->assertTable();

        $payload = [
            ':project_id' => (int) ($attributes['project_id'] ?? 0),
            ':parent_id' => $attributes['parent_id'] ?? null,
            ':code' => trim((string) ($attributes['code'] ?? '')),
            ':node_type' => $attributes['node_type'] ?? 'folder',
            ':iso_clause' => $attributes['iso_clause'] ?? null,
            ':title' => trim((string) ($attributes['title'] ?? '')),
            ':description' => $attributes['description'] ?? null,
            ':sort_order' => (int) ($attributes['sort_order'] ?? 0),
            ':file_path' => $attributes['file_path'] ?? null,
            ':created_by' => $attributes['created_by'] ?? null,
        ];

        if ($payload[':project_id'] <= 0 || $payload[':code'] === '' || $payload[':title'] === '') {
            throw new \InvalidArgumentException('El nodo requiere proyecto, código y título.');
        }

        return $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, code, node_type, iso_clause, title, description, sort_order, file_path, created_by)
             VALUES (:project_id, :parent_id, :code, :node_type, :iso_clause, :title, :description, :sort_order, :file_path, :created_by)',
            $payload
        );
    }

    public function hardResetTree(int $projectId): void
    {
        $this->resetProjectTree($projectId);
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

    public function findById(int $projectId, int $nodeId): ?array
    {
        $this->assertTable();

        return $this->findNode($projectId, $nodeId);
    }

    public function listChildren(int $projectId, ?int $parentId): array
    {
        $this->assertTable();
        if ($parentId !== null) {
            $parent = $this->findNode($projectId, $parentId);
            if (!$parent || $parent['node_type'] !== 'folder') {
                throw new \InvalidArgumentException('La carpeta solicitada no es válida.');
            }
        }

        $rows = $this->db->fetchAll(
            'SELECT id, parent_id, code, node_type, iso_clause, title, description, file_path, created_at, sort_order
             FROM project_nodes
             WHERE project_id = :project_id AND parent_id ' . ($parentId === null ? 'IS NULL' : '= :parent_id') . '
             ORDER BY sort_order ASC, id ASC',
            array_filter([
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
            ], static fn ($value) => $value !== null)
        );

        return array_map(static function (array $row) {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
                'content_type' => $row['node_type'],
                'code' => $row['code'] ?? '',
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? null,
                'iso_clause' => $row['iso_clause'] ?? null,
                'file_path' => $row['file_path'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }, $rows);
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

    public function createFileNode(int $projectId, int $parentId, array $uploadedFile, ?int $userId = null, array $meta = []): array
    {
        $this->assertTable();
        $this->assertDocumentFlowColumns();
        $parent = $this->findNode($projectId, $parentId);

        if (!$parent || $parent['node_type'] !== 'folder') {
            throw new \InvalidArgumentException('Selecciona una carpeta válida para adjuntar.');
        }
        if (!$this->isStandardSubphase($parent)) {
            throw new \InvalidArgumentException('Sube archivos dentro de una subfase.');
        }

        if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($uploadedFile['tmp_name']) || !is_file((string) $uploadedFile['tmp_name'])) {
            throw new \InvalidArgumentException('Selecciona un archivo válido para subir.');
        }

        $originalName = $this->sanitizeFileName((string) ($uploadedFile['name'] ?? 'archivo'));
        $uniqueBase = $this->uniqueFileIdentifier();
        $extension = $this->fileExtension($originalName);
        $storedName = $uniqueBase . ($extension !== '' ? '.' . $extension : '');

        $tempDir = $this->projectStoragePath($projectId) . '/tmp';
        $this->ensureDirectory($tempDir);
        $temporaryPath = $tempDir . '/' . $storedName;
        $this->moveUploadedFile($uploadedFile['tmp_name'], $temporaryPath);

        $pdo = $this->db->connection();
        $transactionStarted = false;
        $finalPath = null;
        $targetDir = null;

        try {
            $pdo->beginTransaction();
            $transactionStarted = true;

            $code = $this->generateFileCode($projectId);
            $sortOrder = $this->nextSortOrder($projectId, $parentId);
            $isoClause = $meta['iso_clause'] ?? $this->resolveIsoClause($projectId, $parentId);
            $description = $meta['description'] ?? null;

            $reviewerId = isset($meta['reviewer_id']) && $meta['reviewer_id'] !== '' ? (int) $meta['reviewer_id'] : null;
            $validatorId = isset($meta['validator_id']) && $meta['validator_id'] !== '' ? (int) $meta['validator_id'] : null;
            $approverId = isset($meta['approver_id']) && $meta['approver_id'] !== '' ? (int) $meta['approver_id'] : null;
            $documentStatus = $meta['document_status'] ?? 'pendiente_revision';
            $documentTags = $this->normalizeDocumentTags($meta['document_tags'] ?? $meta['tags'] ?? null);
            $documentVersion = trim((string) ($meta['document_version'] ?? $meta['version'] ?? ''));
            $documentVersion = $documentVersion !== '' ? $documentVersion : null;
            $documentType = trim((string) ($meta['document_type'] ?? $meta['type'] ?? ''));
            $documentType = $documentType !== '' ? $documentType : null;

            $nodeId = $this->db->insert(
                'INSERT INTO project_nodes (project_id, parent_id, code, node_type, iso_clause, title, description, sort_order, file_path, created_by, reviewer_id, validator_id, approver_id, document_status, document_tags, document_version, document_type)
                 VALUES (:project_id, :parent_id, :code, :node_type, :iso_clause, :title, :description, :sort_order, :file_path, :created_by, :reviewer_id, :validator_id, :approver_id, :document_status, :document_tags, :document_version, :document_type)',
                [
                    ':project_id' => $projectId,
                    ':parent_id' => $parentId,
                    ':code' => $code,
                    ':node_type' => 'file',
                    ':iso_clause' => $isoClause,
                    ':title' => $originalName,
                    ':description' => $description,
                    ':sort_order' => $sortOrder,
                    ':file_path' => null,
                    ':created_by' => $userId ?: null,
                    ':reviewer_id' => $reviewerId,
                    ':validator_id' => $validatorId,
                    ':approver_id' => $approverId,
                    ':document_status' => $documentStatus,
                    ':document_tags' => empty($documentTags) ? null : json_encode($documentTags, JSON_UNESCAPED_UNICODE),
                    ':document_version' => $documentVersion,
                    ':document_type' => $documentType,
                ]
            );

            $targetDir = $this->projectStoragePath($projectId) . '/' . $nodeId;
            $this->ensureDirectory($targetDir);
            $finalPath = $this->uniqueDestination($targetDir, $storedName);

            if (!@rename($temporaryPath, $finalPath)) {
                if (!@copy($temporaryPath, $finalPath) || !@unlink($temporaryPath)) {
                    throw new \RuntimeException('No se pudo guardar el archivo en el destino final.');
                }
            }

            $publicPath = '/storage/projects/' . $projectId . '/' . $nodeId . '/' . basename($finalPath);
            $this->db->execute(
                'UPDATE project_nodes SET file_path = :file_path WHERE id = :id',
                [
                    ':file_path' => $publicPath,
                    ':id' => $nodeId,
                ]
            );

            $pdo->commit();
            $transactionStarted = false;

            $this->logFileAudit($userId, $nodeId, 'file_created', [
                'project_id' => $projectId,
                'parent_id' => $parentId,
                'code' => $code,
                'file_name' => $originalName,
                'file_path' => $publicPath,
            ]);

            return [
                'id' => $nodeId,
                'code' => $code,
                'file_name' => $originalName,
                'storage_path' => $publicPath,
                'created_at' => date('Y-m-d H:i:s'),
                'description' => $description,
                'document_status' => $documentStatus,
                'tags' => $documentTags,
                'version' => $documentVersion,
                'document_type' => $documentType,
                'reviewer_id' => $reviewerId,
                'validator_id' => $validatorId,
                'approver_id' => $approverId,
            ];
        } catch (\Throwable $e) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($finalPath && is_file($finalPath)) {
                @unlink($finalPath);
                $this->cleanupEmptyDirectory($targetDir);
            }

            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw $e;
        }
    }

    public function storeFile(int $projectId, int $parentId, array $file, ?int $userId = null): array
    {
        return $this->createFileNode($projectId, $parentId, $file, $userId);
    }

    public function deleteNode(int $projectId, int $nodeId, ?int $userId = null): void
    {
        $this->assertTable();
        $node = $this->findNode($projectId, $nodeId);
        if (!$node) {
            return;
        }

        $fileNodes = $node['node_type'] === 'file'
            ? [$node]
            : $this->fileNodesUnder($projectId, $nodeId);

        $pdo = $this->db->connection();
        $transactionStarted = false;

        try {
            $pdo->beginTransaction();
            $transactionStarted = true;

            $this->db->execute('DELETE FROM project_nodes WHERE id = :id AND project_id = :project_id', [
                ':id' => $nodeId,
                ':project_id' => $projectId,
            ]);

            $pdo->commit();
            $transactionStarted = false;
        } finally {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        foreach ($fileNodes as $fileNode) {
            $this->removePhysicalFiles($projectId, (int) ($fileNode['id'] ?? 0));
            $this->logFileAudit($userId, (int) ($fileNode['id'] ?? 0), 'file_deleted', [
                'project_id' => $projectId,
                'code' => $fileNode['code'] ?? null,
                'file_name' => $fileNode['title'] ?? null,
                'file_path' => $fileNode['file_path'] ?? null,
            ]);
        }
    }

    public function treeWithFiles(int $projectId): array
    {
        if (!$this->db->tableExists('project_nodes')) {
            return [];
        }

        $selectColumns = [
            'id',
            'parent_id',
            'code',
            'node_type',
            'iso_clause',
            'title',
            'description',
            'file_path',
            'created_at',
            'status',
            'critical',
            'completed_at',
            'sort_order',
        ];

        $flowColumns = [
            'reviewer_id',
            'validator_id',
            'approver_id',
            'reviewed_by',
            'reviewed_at',
            'validated_by',
            'validated_at',
            'approved_by',
            'approved_at',
            'document_status',
        ];

        foreach ($flowColumns as $column) {
            $selectColumns[] = $this->db->columnExists('project_nodes', $column)
                ? $column
                : sprintf('NULL AS %s', $column);
        }

        $metadataColumns = [
            'document_tags',
            'document_version',
            'document_type',
        ];

        foreach ($metadataColumns as $column) {
            $selectColumns[] = $this->db->columnExists('project_nodes', $column)
                ? $column
                : sprintf('NULL AS %s', $column);
        }

        $nodes = $this->db->fetchAll(
            sprintf(
                'SELECT %s
                 FROM project_nodes
                 WHERE project_id = :project
                 ORDER BY parent_id IS NULL DESC, sort_order ASC, id ASC',
                implode(', ', $selectColumns)
            ),
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
                    'reviewer_id' => $node['reviewer_id'] !== null ? (int) $node['reviewer_id'] : null,
                    'validator_id' => $node['validator_id'] !== null ? (int) $node['validator_id'] : null,
                    'approver_id' => $node['approver_id'] !== null ? (int) $node['approver_id'] : null,
                    'reviewed_by' => $node['reviewed_by'] !== null ? (int) $node['reviewed_by'] : null,
                    'reviewed_at' => $node['reviewed_at'] ?? null,
                    'validated_by' => $node['validated_by'] !== null ? (int) $node['validated_by'] : null,
                    'validated_at' => $node['validated_at'] ?? null,
                    'approved_by' => $node['approved_by'] !== null ? (int) $node['approved_by'] : null,
                    'approved_at' => $node['approved_at'] ?? null,
                    'document_status' => $node['document_status'] ?? 'pendiente_revision',
                    'tags' => $this->normalizeDocumentTags($node['document_tags'] ?? null),
                    'version' => $node['document_version'] ?? null,
                    'document_type' => $node['document_type'] ?? null,
                    'description' => $node['description'] ?? null,
                ];
                continue;
            }

            $nodeId = (int) $node['id'];
            $folders[$nodeId] = [
                'id' => $nodeId,
                'parent_id' => $node['parent_id'],
                'code' => $node['code'] ?? '',
                'node_type' => $node['node_type'] ?? 'folder',
                'name' => $node['title'],
                'iso_code' => $node['iso_clause'],
                'status' => $node['status'] ?? 'pendiente',
                'critical' => (bool) ((int) ($node['critical'] ?? 0)),
                'completed_at' => $node['completed_at'] ?? null,
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

        return $tree;
    }

    public function updateDocumentFlow(int $projectId, int $fileNodeId, array $payload): array
    {
        $this->assertTable();
        $this->assertDocumentFlowColumns();
        $node = $this->findNode($projectId, $fileNodeId);
        if (!$node || ($node['node_type'] ?? '') !== 'file') {
            throw new \InvalidArgumentException('Documento no encontrado.');
        }

        $reviewerId = isset($payload['reviewer_id']) && $payload['reviewer_id'] !== '' ? (int) $payload['reviewer_id'] : null;
        $validatorId = isset($payload['validator_id']) && $payload['validator_id'] !== '' ? (int) $payload['validator_id'] : null;
        $approverId = isset($payload['approver_id']) && $payload['approver_id'] !== '' ? (int) $payload['approver_id'] : null;

        $this->db->execute(
            'UPDATE project_nodes
             SET reviewer_id = :reviewer_id,
                 validator_id = :validator_id,
                 approver_id = :approver_id,
                 reviewed_by = NULL,
                 reviewed_at = NULL,
                 validated_by = NULL,
                 validated_at = NULL,
                 approved_by = NULL,
                 approved_at = NULL,
                 document_status = :document_status
             WHERE id = :id AND project_id = :project_id',
            [
                ':reviewer_id' => $reviewerId,
                ':validator_id' => $validatorId,
                ':approver_id' => $approverId,
                ':document_status' => $payload['document_status'] ?? 'pendiente_revision',
                ':id' => $fileNodeId,
                ':project_id' => $projectId,
            ]
        );

        return $this->documentFlowForNode($projectId, $fileNodeId);
    }

    public function updateDocumentStatus(int $projectId, int $fileNodeId, array $payload): array
    {
        $this->assertTable();
        $node = $this->findNode($projectId, $fileNodeId);
        if (!$node || ($node['node_type'] ?? '') !== 'file') {
            throw new \InvalidArgumentException('Documento no encontrado.');
        }

        $fields = [
            'document_status' => $payload['document_status'] ?? 'pendiente_revision',
            'reviewed_by' => $payload['reviewed_by'] ?? null,
            'reviewed_at' => $payload['reviewed_at'] ?? null,
            'validated_by' => $payload['validated_by'] ?? null,
            'validated_at' => $payload['validated_at'] ?? null,
            'approved_by' => $payload['approved_by'] ?? null,
            'approved_at' => $payload['approved_at'] ?? null,
        ];

        $setClauses = [];
        $params = [
            ':id' => $fileNodeId,
            ':project_id' => $projectId,
        ];

        foreach ($fields as $field => $value) {
            $setClauses[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        $this->db->execute(
            'UPDATE project_nodes SET ' . implode(', ', $setClauses) . ' WHERE id = :id AND project_id = :project_id',
            $params
        );

        return $this->documentFlowForNode($projectId, $fileNodeId);
    }

    public function updateDocumentMetadata(int $projectId, int $fileNodeId, array $payload): array
    {
        $this->assertTable();
        $node = $this->findNode($projectId, $fileNodeId);
        if (!$node || ($node['node_type'] ?? '') !== 'file') {
            throw new \InvalidArgumentException('Documento no encontrado.');
        }

        $tags = $this->normalizeDocumentTags($payload['tags'] ?? $payload['document_tags'] ?? null);
        $version = trim((string) ($payload['version'] ?? $payload['document_version'] ?? ''));
        $version = $version !== '' ? $version : null;

        $this->db->execute(
            'UPDATE project_nodes
             SET document_tags = :document_tags,
                 document_version = :document_version
             WHERE id = :id AND project_id = :project_id',
            [
                ':document_tags' => empty($tags) ? null : json_encode($tags, JSON_UNESCAPED_UNICODE),
                ':document_version' => $version,
                ':id' => $fileNodeId,
                ':project_id' => $projectId,
            ]
        );

        return [
            'id' => $fileNodeId,
            'document_tags' => $tags,
            'document_version' => $version,
        ];
    }

    public function documentFlowForNode(int $projectId, int $fileNodeId): array
    {
        $this->assertTable();
        $row = $this->db->fetchOne(
            'SELECT id, reviewer_id, validator_id, approver_id, reviewed_by, reviewed_at, validated_by, validated_at, approved_by, approved_at, document_status
             FROM project_nodes
             WHERE id = :id AND project_id = :project_id',
            [
                ':id' => $fileNodeId,
                ':project_id' => $projectId,
            ]
        );

        if (!$row) {
            throw new \InvalidArgumentException('Documento no encontrado.');
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'reviewer_id' => $row['reviewer_id'] !== null ? (int) $row['reviewer_id'] : null,
            'validator_id' => $row['validator_id'] !== null ? (int) $row['validator_id'] : null,
            'approver_id' => $row['approver_id'] !== null ? (int) $row['approver_id'] : null,
            'reviewed_by' => $row['reviewed_by'] !== null ? (int) $row['reviewed_by'] : null,
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'validated_by' => $row['validated_by'] !== null ? (int) $row['validated_by'] : null,
            'validated_at' => $row['validated_at'] ?? null,
            'approved_by' => $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
            'approved_at' => $row['approved_at'] ?? null,
            'document_status' => $row['document_status'] ?? 'pendiente_revision',
        ];
    }

    public function updateNodeStatus(int $projectId, int $nodeId, string $status): void
    {
        $this->assertTable();
        $validStatuses = ['pendiente', 'en_progreso', 'completado', 'bloqueado'];
        $normalized = in_array($status, $validStatuses, true) ? $status : 'pendiente';
        $completedAt = $normalized === 'completado' ? date('Y-m-d H:i:s') : null;

        $this->db->execute(
            'UPDATE project_nodes SET status = :status, completed_at = :completed_at WHERE id = :id AND project_id = :project_id',
            [
                ':status' => $normalized,
                ':completed_at' => $completedAt,
                ':id' => $nodeId,
                ':project_id' => $projectId,
            ]
        );
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
                'children' => array_merge($this->isoStandardChildren('01-INICIO'), [
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
                ]),
            ],
            [
                'code' => '02-PLANIFICACION',
                'title' => '02 · Planificación',
                'node_type' => 'folder',
                'iso_clause' => '8.3',
                'description' => null,
                'sort_order' => 20,
                'children' => array_merge($this->isoStandardChildren('02-PLANIFICACION'), [
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
                ]),
            ],
            [
                'code' => '03-EJECUCION',
                'title' => '03 · Ejecución',
                'node_type' => 'folder',
                'iso_clause' => null,
                'description' => null,
                'sort_order' => 30,
                'children' => array_merge($this->isoStandardChildren('03-EJECUCION'), [
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
                ]),
            ],
            [
                'code' => '04-SEGUIMIENTO',
                'title' => '04 · Seguimiento y Control',
                'node_type' => 'folder',
                'iso_clause' => '8.3.4',
                'description' => null,
                'sort_order' => 40,
                'children' => array_merge($this->isoStandardChildren('04-SEGUIMIENTO'), [
                    [
                        'code' => '04-SEGUIMIENTO-AVANCE-PLAN',
                        'title' => 'Avance vs plan',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 10,
                        'children' => [],
                    ],
                    [
                        'code' => '04-SEGUIMIENTO-CAMBIOS',
                        'title' => 'Control de cambios',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.6',
                        'description' => null,
                        'sort_order' => 20,
                        'children' => [],
                    ],
                    [
                        'code' => '04-SEGUIMIENTO-RIESGOS-ACTIVOS',
                        'title' => 'Riesgos activos',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 30,
                        'children' => [],
                    ],
                    [
                        'code' => '04-SEGUIMIENTO-ACTAS-COMITES',
                        'title' => 'Actas y comités',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 40,
                        'children' => [],
                    ],
                    [
                        'code' => '04-SEGUIMIENTO-KPIS',
                        'title' => 'KPIs',
                        'node_type' => 'folder',
                        'iso_clause' => '8.3.4',
                        'description' => null,
                        'sort_order' => 50,
                        'children' => [],
                    ],
                ]),
            ],
            [
                'code' => '05-CIERRE',
                'title' => '05 · Cierre',
                'node_type' => 'folder',
                'iso_clause' => null,
                'description' => null,
                'sort_order' => 50,
                'children' => array_merge($this->isoStandardChildren('05-CIERRE'), [
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
                ]),
            ],
        ];
    }

    private function sprintStructureDefinition(): array
    {
        return [
            [
                'code' => '01-ENTRADAS',
                'title' => '01-Entradas',
                'node_type' => 'folder',
                'iso_clause' => '8.3.3',
                'description' => null,
                'sort_order' => 10,
                'children' => [],
            ],
            [
                'code' => '02-PLANIFICACION',
                'title' => '02-Planificación',
                'node_type' => 'folder',
                'iso_clause' => '8.3.2',
                'description' => null,
                'sort_order' => 20,
                'children' => [],
            ],
            [
                'code' => '03-CONTROLES',
                'title' => '03-Controles',
                'node_type' => 'folder',
                'iso_clause' => '8.3.4',
                'description' => 'Controles ISO 9001',
                'sort_order' => 30,
                'children' => [],
            ],
            [
                'code' => '04-EVIDENCIAS',
                'title' => '04-Evidencias',
                'node_type' => 'folder',
                'iso_clause' => '8.3.5',
                'description' => null,
                'sort_order' => 40,
                'children' => [],
            ],
            [
                'code' => '05-CAMBIOS',
                'title' => '05-Cambios',
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
                'code' => self::SCRUM_DISCOVERY_CODE,
                'title' => self::SCRUM_DISCOVERY_TITLE,
                'node_type' => 'folder',
                'iso_clause' => null,
                'description' => null,
                'sort_order' => 5,
                'children' => $this->isoStandardChildren(self::SCRUM_DISCOVERY_CODE, 5),
            ],
            [
                'code' => self::SCRUM_BACKLOG_CODE,
                'title' => self::SCRUM_BACKLOG_TITLE,
                'node_type' => 'folder',
                'iso_clause' => '8.3.2',
                'description' => null,
                'sort_order' => 10,
                'children' => $this->isoStandardChildren(self::SCRUM_BACKLOG_CODE, 5),
            ],
            [
                'code' => self::SCRUM_SPRINT_CONTAINER_CODE,
                'title' => self::SCRUM_SPRINT_CONTAINER_TITLE,
                'node_type' => 'folder',
                'iso_clause' => null,
                'description' => null,
                'sort_order' => self::SCRUM_SPRINT_CONTAINER_ORDER,
                'children' => $this->isoStandardChildren(self::SCRUM_SPRINT_CONTAINER_CODE, 5),
            ],
            [
                'code' => self::SCRUM_REVIEW_CODE,
                'title' => self::SCRUM_REVIEW_TITLE,
                'node_type' => 'folder',
                'iso_clause' => '8.3.4',
                'description' => null,
                'sort_order' => 30,
                'children' => $this->isoStandardChildren(self::SCRUM_REVIEW_CODE, 5),
            ],
            [
                'code' => self::SCRUM_RELEASE_CODE,
                'title' => self::SCRUM_RELEASE_TITLE,
                'node_type' => 'folder',
                'iso_clause' => '8.3.5',
                'description' => null,
                'sort_order' => 40,
                'children' => $this->isoStandardChildren(self::SCRUM_RELEASE_CODE, 5),
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
                'children' => $this->isoStandardChildren('01-BACKLOG', 5),
            ],
            [
                'code' => '02-EN-CURSO',
                'title' => 'En curso',
                'node_type' => 'folder',
                'iso_clause' => '8.3.4',
                'description' => null,
                'sort_order' => 20,
                'children' => $this->isoStandardChildren('02-EN-CURSO', 5),
            ],
            [
                'code' => '03-EN-REVISION',
                'title' => 'En revisión',
                'node_type' => 'folder',
                'iso_clause' => '8.3.5',
                'description' => null,
                'sort_order' => 30,
                'children' => $this->isoStandardChildren('03-EN-REVISION', 5),
            ],
            [
                'code' => '04-HECHO',
                'title' => 'Hecho',
                'node_type' => 'folder',
                'iso_clause' => '8.3.5',
                'description' => null,
                'sort_order' => 40,
                'children' => $this->isoStandardChildren('04-HECHO', 5),
            ],
            [
                'code' => '05-MEJORA-CONTINUA',
                'title' => 'Mejora continua',
                'node_type' => 'folder',
                'iso_clause' => '8.3.6',
                'description' => null,
                'sort_order' => 50,
                'children' => $this->isoStandardChildren('05-MEJORA-CONTINUA', 5),
            ],
        ];
    }

    private function isoStandardChildren(string $phaseCode, int $startSort = 10): array
    {
        return [
            $this->isoChildDefinition($phaseCode, '01-ENTRADAS', 'Entradas', '8.3.3', $startSort),
            $this->isoChildDefinition($phaseCode, '02-CONTROLES', 'Controles', '8.3.4', $startSort + 10),
            $this->isoChildDefinition($phaseCode, '03-EVIDENCIAS', 'Evidencias', '8.3.5', $startSort + 20),
            $this->isoChildDefinition($phaseCode, '04-CAMBIOS', 'Cambios', '8.3.6', $startSort + 30),
        ];
    }

    private function isoChildDefinition(string $phaseCode, string $suffix, string $title, ?string $clause, int $sortOrder): array
    {
        return [
            'code' => $phaseCode . '-' . $suffix,
            'title' => $title,
            'node_type' => 'folder',
            'iso_clause' => $clause,
            'description' => null,
            'sort_order' => $sortOrder,
            'children' => [],
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

    private function ensureScrumControlContainers(int $projectId): void
    {
        $this->upsertScrumPhaseNode($projectId, self::SCRUM_DISCOVERY_CODE, self::SCRUM_DISCOVERY_TITLE, null, 5);
        $this->upsertScrumPhaseNode($projectId, self::SCRUM_BACKLOG_CODE, self::SCRUM_BACKLOG_TITLE, '8.3.2', 10);
        $this->upsertScrumPhaseNode($projectId, self::SCRUM_SPRINT_CONTAINER_CODE, self::SCRUM_SPRINT_CONTAINER_TITLE, null, self::SCRUM_SPRINT_CONTAINER_ORDER);
        $this->upsertScrumPhaseNode($projectId, self::SCRUM_REVIEW_CODE, self::SCRUM_REVIEW_TITLE, '8.3.4', 30);
        $this->upsertScrumPhaseNode($projectId, self::SCRUM_RELEASE_CODE, self::SCRUM_RELEASE_TITLE, '8.3.5', 40);
    }

    private function upsertScrumPhaseNode(
        int $projectId,
        string $targetCode,
        string $title,
        ?string $isoClause,
        int $sortOrder
    ): void {
        $existing = $this->db->fetchOne(
            'SELECT id FROM project_nodes WHERE project_id = :project AND code = :code LIMIT 1',
            [':project' => $projectId, ':code' => $targetCode]
        );

        if ($existing) {
            $this->db->execute(
                'UPDATE project_nodes SET title = :title, parent_id = NULL, node_type = "folder", file_path = NULL, iso_clause = :iso_clause, sort_order = :sort_order WHERE id = :id AND project_id = :project',
                [
                    ':title' => $title,
                    ':iso_clause' => $isoClause,
                    ':sort_order' => $sortOrder,
                    ':id' => (int) ($existing['id'] ?? 0),
                    ':project' => $projectId,
                ]
            );

            return;
        }

        $this->ensureNode(
            $projectId,
            $targetCode,
            $title,
            'folder',
            null,
            $isoClause,
            null,
            $sortOrder
        );
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
                'phase' => null,
            ];
        }

        $hasVersionColumn = $this->db->columnExists('projects', 'tree_version');
        $hasMethodologyColumn = $this->db->columnExists('projects', 'tree_methodology');
        $hasPhaseColumn = $this->db->columnExists('projects', 'tree_phase');

        if (!$hasVersionColumn && !$hasMethodologyColumn && !$hasPhaseColumn) {
            return [
                'version' => null,
                'methodology' => null,
                'phase' => null,
            ];
        }

        $columns = array_filter([
            $hasVersionColumn ? 'tree_version' : null,
            $hasMethodologyColumn ? 'tree_methodology' : null,
            $hasPhaseColumn ? 'tree_phase' : null,
        ]);

        $row = $this->db->fetchOne(
            sprintf('SELECT %s FROM projects WHERE id = :id LIMIT 1', implode(', ', $columns)),
            [':id' => $projectId]
        ) ?: [];

        return [
            'version' => $hasVersionColumn ? ($row['tree_version'] ?? null) : null,
            'methodology' => $hasMethodologyColumn ? $this->normalizeMethodology((string) ($row['tree_methodology'] ?? '')) : null,
            'phase' => $hasPhaseColumn ? $this->normalizePhase($row['tree_phase'] ?? null) : null,
        ];
    }

    private function persistTreeMetadata(int $projectId, string $methodology, ?string $phase = null): void
    {
        $hasVersionColumn = $this->db->columnExists('projects', 'tree_version');
        $hasMethodologyColumn = $this->db->columnExists('projects', 'tree_methodology');
        $hasPhaseColumn = $this->db->columnExists('projects', 'tree_phase');

        if (!$hasVersionColumn && !$hasMethodologyColumn && !$hasPhaseColumn) {
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

        if ($hasPhaseColumn) {
            $fields[] = 'tree_phase = :tree_phase';
            $params[':tree_phase'] = $phase;
        }

        if (!empty($fields)) {
            $this->db->execute(
                'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = :id',
                $params
            );
        }
    }

    public function markTreeOutdated(int $projectId): void
    {
        if (!$this->db->tableExists('projects')) {
            return;
        }

        $fields = [];
        $params = [':id' => $projectId];

        if ($this->db->columnExists('projects', 'tree_version')) {
            $fields[] = 'tree_version = NULL';
        }

        if ($this->db->columnExists('projects', 'tree_methodology')) {
            $fields[] = 'tree_methodology = NULL';
        }

        if ($this->db->columnExists('projects', 'tree_phase')) {
            $fields[] = 'tree_phase = NULL';
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

    private function normalizePhase(mixed $phase): ?string
    {
        if ($phase === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $phase));

        return $normalized === '' ? null : $normalized;
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

    public function ensureNode(
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

    public function findNodeByCode(int $projectId, string $code): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, parent_id, node_type, iso_clause, code, title, description, sort_order FROM project_nodes WHERE project_id = :project AND code = :code LIMIT 1',
            [
                ':project' => $projectId,
                ':code' => $code,
            ]
        ) ?: null;
    }

    public function ensurePhaseRoot(int $projectId, array $definition): int
    {
        $node = $this->ensurePhaseFolder($projectId, (string) ($definition['code'] ?? ''), [
            'title' => (string) ($definition['title'] ?? ''),
            'iso_clause' => $definition['iso_clause'] ?? null,
            'sort_order' => (int) ($definition['sort_order'] ?? 0),
        ]);

        return (int) ($node['id'] ?? 0);
    }

    public function ensurePhaseFolder(int $projectId, string $phaseCode, array $attributes = []): array
    {
        $this->assertTable();
        $normalizedCode = trim($phaseCode);
        if ($normalizedCode === '') {
            throw new \InvalidArgumentException('La fase del ciclo de proyecto es obligatoria para vincular evidencia ISO.');
        }

        $title = trim((string) ($attributes['title'] ?? ''));
        $isoClause = $attributes['iso_clause'] ?? null;
        $sortOrder = $attributes['sort_order'] ?? null;
        $hasIsoClause = array_key_exists('iso_clause', $attributes);
        $hasSortOrder = array_key_exists('sort_order', $attributes);

        $existing = $this->db->fetchOne(
            'SELECT id, node_type, parent_id, iso_clause, title, sort_order FROM project_nodes WHERE project_id = :project AND code = :code LIMIT 1',
            [
                ':project' => $projectId,
                ':code' => $normalizedCode,
            ]
        );

        if ($existing) {
            if (($existing['node_type'] ?? '') !== 'folder') {
                throw new \InvalidArgumentException(
                    sprintf(
                        'La fase %s no puede asociarse al nodo %d porque no es una carpeta.',
                        $normalizedCode,
                        (int) ($existing['id'] ?? 0)
                    )
                );
            }

            $updates = [];
            if (($existing['parent_id'] ?? null) !== null) {
                $updates['parent_id'] = null;
            }

            if ($title !== '' && $title !== ($existing['title'] ?? '')) {
                $updates['title'] = $title;
            }

            if ($hasIsoClause && $isoClause !== ($existing['iso_clause'] ?? null)) {
                $updates['iso_clause'] = $isoClause;
            }

            $sortOrderValue = (int) ($sortOrder ?? 0);
            if ($hasSortOrder && $sortOrderValue > 0 && $sortOrderValue !== (int) ($existing['sort_order'] ?? 0)) {
                $updates['sort_order'] = $sortOrderValue;
            }

            if (!empty($updates)) {
                $setClauses = [];
                $params = [
                    ':id' => (int) ($existing['id'] ?? 0),
                    ':project_id' => $projectId,
                ];

                foreach ($updates as $field => $value) {
                    $setClauses[] = $field . ' = :' . $field;
                    $params[':' . $field] = $value;
                }

                $this->db->execute(
                    'UPDATE project_nodes SET ' . implode(', ', $setClauses) . ' WHERE id = :id AND project_id = :project_id',
                    $params
                );
            }

            return $this->findNode($projectId, (int) ($existing['id'] ?? 0)) ?? [
                'id' => (int) ($existing['id'] ?? 0),
                'code' => $normalizedCode,
                'node_type' => 'folder',
                'parent_id' => null,
            ];
        }

        $resolvedTitle = $title !== '' ? $title : $normalizedCode;
        $sortOrderValue = (int) ($sortOrder ?? 0);
        $nodeId = $this->ensureNode(
            $projectId,
            $normalizedCode,
            $resolvedTitle,
            'folder',
            null,
            $isoClause,
            null,
            $sortOrderValue > 0 ? $sortOrderValue : 0
        );

        return $this->findNode($projectId, $nodeId) ?? [
            'id' => $nodeId,
            'code' => $normalizedCode,
            'node_type' => 'folder',
            'parent_id' => null,
            'title' => $resolvedTitle,
            'iso_clause' => $isoClause,
        ];
    }

    public function ensureFolderNode(
        int $projectId,
        int $parentId,
        string $code,
        string $title,
        ?string $isoClause = null,
        ?int $sortOrder = null
    ): array {
        $this->assertTable();
        $this->assertFolderParent($projectId, $parentId);

        $normalizedCode = trim($code);
        $normalizedTitle = trim($title);

        if ($normalizedCode === '' || $normalizedTitle === '') {
            throw new \InvalidArgumentException('El código y el título de la carpeta son obligatorios.');
        }

        $existing = $this->db->fetchOne(
            'SELECT id, parent_id, node_type, iso_clause, title, sort_order FROM project_nodes WHERE project_id = :project AND code = :code LIMIT 1',
            [
                ':project' => $projectId,
                ':code' => $normalizedCode,
            ]
        );

        if ($existing) {
            if ((int) ($existing['parent_id'] ?? 0) !== $parentId) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'El nodo %s ya existe asociado al padre %d; se esperaba el padre %d.',
                        $normalizedCode,
                        (int) ($existing['parent_id'] ?? 0),
                        $parentId
                    )
                );
            }

            if (($existing['node_type'] ?? '') !== 'folder') {
                throw new \InvalidArgumentException(
                    sprintf(
                        'ISO no puede colgarse del nodo %d porque no es carpeta. node_type encontrado: \'%s\'',
                        (int) ($existing['id'] ?? 0),
                        (string) ($existing['node_type'] ?? 'desconocido')
                    )
                );
            }

            $updates = [];

            if ($normalizedTitle !== '' && $normalizedTitle !== ($existing['title'] ?? '')) {
                $updates['title'] = $normalizedTitle;
            }

            $hasIsoClause = $isoClause !== null;
            if ($hasIsoClause && $isoClause !== ($existing['iso_clause'] ?? null)) {
                $updates['iso_clause'] = $isoClause;
            }

            $sortOrderValue = (int) ($sortOrder ?? 0);
            if ($sortOrderValue > 0 && $sortOrderValue !== (int) ($existing['sort_order'] ?? 0)) {
                $updates['sort_order'] = $sortOrderValue;
            }

            if (!empty($updates)) {
                $setClauses = [];
                $params = [
                    ':id' => (int) ($existing['id'] ?? 0),
                    ':project_id' => $projectId,
                ];

                foreach ($updates as $field => $value) {
                    $setClauses[] = $field . ' = :' . $field;
                    $params[':' . $field] = $value;
                }

                $this->db->execute(
                    'UPDATE project_nodes SET ' . implode(', ', $setClauses) . ' WHERE id = :id AND project_id = :project_id',
                    $params
                );
            }

            return $this->findNode($projectId, (int) ($existing['id'] ?? 0)) ?? [
                'id' => (int) ($existing['id'] ?? 0),
                'parent_id' => $parentId,
                'code' => $normalizedCode,
                'node_type' => 'folder',
            ];
        }

        $order = (int) ($sortOrder ?? 0);
        $resolvedOrder = $order > 0 ? $order : $this->nextSortOrder($projectId, $parentId);
        $resolvedIsoClause = $isoClause ?? $this->resolveIsoClause($projectId, $parentId);

        $nodeId = $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, code, node_type, iso_clause, title, description, sort_order)
             VALUES (:project_id, :parent_id, :code, :node_type, :iso_clause, :title, :description, :sort_order)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
                ':code' => $normalizedCode,
                ':node_type' => 'folder',
                ':iso_clause' => $resolvedIsoClause,
                ':title' => $normalizedTitle,
                ':description' => null,
                ':sort_order' => $resolvedOrder,
            ]
        );

        return $this->findNode($projectId, $nodeId) ?? [
            'id' => $nodeId,
            'parent_id' => $parentId,
            'code' => $normalizedCode,
            'node_type' => 'folder',
            'iso_clause' => $resolvedIsoClause,
            'title' => $normalizedTitle,
            'sort_order' => $resolvedOrder,
        ];
    }

    public function ensureIsoItem(int $projectId, int $parentId, array $definition): int
    {
        $this->assertTable();
        $this->assertFolderParent($projectId, $parentId);

        $code = trim((string) ($definition['code'] ?? ''));
        $title = trim((string) ($definition['title'] ?? ''));
        $isoClause = $definition['iso_clause'] ?? null;
        $description = $definition['description'] ?? null;
        $sortOrder = (int) ($definition['sort_order'] ?? 0);
        $nodeType = $definition['node_type'] ?? 'iso_control';

        if ($code === '' || $title === '') {
            throw new \InvalidArgumentException('El nodo ISO carece de código o título.');
        }

        $existing = $this->db->fetchOne(
            'SELECT id, parent_id, node_type, iso_clause, title, sort_order FROM project_nodes WHERE project_id = :project AND code = :code LIMIT 1',
            [
                ':project' => $projectId,
                ':code' => $code,
            ]
        );

        if ($existing) {
            if ((int) ($existing['parent_id'] ?? 0) !== $parentId) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'El nodo ISO %s ya existe asociado al padre %d; no puede reasignarse automáticamente al padre %d.',
                        $code,
                        (int) ($existing['parent_id'] ?? 0),
                        $parentId
                    )
                );
            }

            if (($existing['node_type'] ?? '') !== $nodeType) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'El nodo ISO %s existe con node_type \'%s\'; se esperaba \'%s\'.',
                        $code,
                        (string) ($existing['node_type'] ?? 'desconocido'),
                        $nodeType
                    )
                );
            }

            $updates = [];

            if ($title !== '' && $title !== ($existing['title'] ?? '')) {
                $updates['title'] = $title;
            }

            if ($isoClause !== null && $isoClause !== ($existing['iso_clause'] ?? null)) {
                $updates['iso_clause'] = $isoClause;
            }

            if ($sortOrder > 0 && $sortOrder !== (int) ($existing['sort_order'] ?? 0)) {
                $updates['sort_order'] = $sortOrder;
            }

            if (!empty($updates)) {
                $setClauses = [];
                $params = [
                    ':id' => (int) ($existing['id'] ?? 0),
                    ':project_id' => $projectId,
                ];

                foreach ($updates as $field => $value) {
                    $setClauses[] = $field . ' = :' . $field;
                    $params[':' . $field] = $value;
                }

                $this->db->execute(
                    'UPDATE project_nodes SET ' . implode(', ', $setClauses) . ' WHERE id = :id AND project_id = :project_id',
                    $params
                );
            }

            return (int) ($existing['id'] ?? 0);
        }

        $order = $sortOrder > 0 ? $sortOrder : $this->nextSortOrder($projectId, $parentId);

        return $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, code, node_type, iso_clause, title, description, sort_order)
             VALUES (:project_id, :parent_id, :code, :node_type, :iso_clause, :title, :description, :sort_order)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
                ':code' => $code,
                ':node_type' => $nodeType,
                ':iso_clause' => $isoClause,
                ':title' => $title,
                ':description' => $description,
                ':sort_order' => $order,
            ]
        );
    }


    private function assertFolderParent(int $projectId, int $parentId): array
    {
        $parentNode = $this->findNode($projectId, $parentId);
        if (!$parentNode) {
            throw new \InvalidArgumentException('La carpeta inicial no es válida.');
        }

        if (($parentNode['node_type'] ?? '') !== 'folder') {
            throw new \InvalidArgumentException(
                sprintf(
                    'ISO no puede colgarse del nodo %d porque no es carpeta. node_type encontrado: \'%s\'',
                    $parentId,
                    (string) ($parentNode['node_type'] ?? 'desconocido')
                )
            );
        }

        return $parentNode;
    }

    public function ensureFolderPath(int $projectId, array $path, ?int $parentId = null): int
    {
        $this->assertTable();

        $parentCode = null;
        $lastNodeId = $parentId;

        if ($parentId !== null) {
            $parentNode = $this->assertFolderParent($projectId, $parentId);
            $parentCode = (string) ($parentNode['code'] ?? '');
            $lastNodeId = (int) ($parentNode['id'] ?? 0);
        }

        foreach ($path as $index => $definition) {
            $code = (string) ($definition['code'] ?? '');
            $title = (string) ($definition['title'] ?? '');
            if ($code === '' || $title === '') {
                throw new \InvalidArgumentException('La ruta ISO contiene nodos inválidos.');
            }

            $nodeId = $this->ensureNode(
                $projectId,
                $code,
                $title,
                $definition['node_type'] ?? 'folder',
                $parentCode,
                $definition['iso_clause'] ?? null,
                $definition['description'] ?? null,
                (int) ($definition['sort_order'] ?? (($index + 1) * 10))
            );

            $node = $this->findNodeByCode($projectId, $code) ?? ['id' => $nodeId];
            if (($node['node_type'] ?? '') !== 'folder') {
                throw new \InvalidArgumentException(
                    sprintf(
                        'El nodo %s no es una carpeta válida para la ruta solicitada. node_type encontrado: \'%s\'',
                        $code,
                        (string) ($node['node_type'] ?? 'desconocido')
                    )
                );
            }

            if ((int) ($node['parent_id'] ?? 0) !== $parentId) {
                $this->db->execute(
                    'UPDATE project_nodes SET parent_id = :parent_id WHERE id = :id AND project_id = :project_id',
                    [
                        ':parent_id' => $parentId,
                        ':id' => (int) ($node['id'] ?? $nodeId),
                        ':project_id' => $projectId,
                    ]
                );
                $node['parent_id'] = $parentId;
            }

            $parentCode = $code;
            $parentId = (int) ($node['id'] ?? $nodeId);
            $lastNodeId = $parentId;
        }

        return $lastNodeId;
    }

    private function slugifyCode(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        $normalized = strtolower(trim((string) $normalized));
        $normalized = preg_replace('/[^a-z0-9]+/', '.', $normalized);
        $normalized = trim((string) $normalized, '.');

        return $normalized !== '' ? $normalized : 'nodo';
    }

    private function findNode(int $projectId, int $nodeId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, project_id, parent_id, node_type, iso_clause, code, file_path, title, reviewer_id, validator_id, approver_id, document_status, document_tags, document_version
             FROM project_nodes WHERE id = :id AND project_id = :project_id',
            [
                ':id' => $nodeId,
                ':project_id' => $projectId,
            ]
        );
    }

    private function isStandardSubphase(array $node): bool
    {
        $code = (string) ($node['code'] ?? '');
        if ($code === '') {
            return false;
        }

        $parts = explode('-', $code);
        if (count($parts) < 4) {
            return false;
        }

        $suffix = implode('-', array_slice($parts, -2));

        return in_array($suffix, self::STANDARD_SUBPHASE_SUFFIXES, true);
    }

    private function normalizeDocumentTags(mixed $value): array
    {
        if (is_array($value)) {
            return $this->sanitizeTags($value);
        }

        if (!is_string($value)) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $this->sanitizeTags($decoded);
        }

        $parts = preg_split('/[|,]/', $trimmed) ?: [];

        return $this->sanitizeTags($parts);
    }

    private function sanitizeTags(array $tags): array
    {
        $clean = [];
        foreach ($tags as $tag) {
            $label = trim((string) $tag);
            if ($label === '') {
                continue;
            }
            $clean[] = $label;
        }

        return array_values(array_unique($clean));
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

    private function projectStoragePath(int $projectId): string
    {
        return __DIR__ . '/../../public/storage/projects/' . $projectId;
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo preparar el directorio de almacenamiento.');
        }
    }

    private function moveUploadedFile(string $source, string $destination): void
    {
        if (!is_file($source)) {
            throw new \InvalidArgumentException('Selecciona un archivo válido para subir.');
        }

        if (!@move_uploaded_file($source, $destination) && !@rename($source, $destination) && !(@copy($source, $destination) && @unlink($source))) {
            throw new \RuntimeException('No se pudo guardar el archivo en almacenamiento temporal.');
        }
    }

    private function uniqueDestination(string $directory, string $fileName): string
    {
        $candidate = rtrim($directory, '/') . '/' . $fileName;
        if (!file_exists($candidate)) {
            return $candidate;
        }

        $extension = $this->fileExtension($fileName);
        $nameOnly = $extension !== '' ? substr($fileName, 0, -(strlen($extension) + 1)) : $fileName;
        $suffix = 2;

        do {
            $candidate = rtrim($directory, '/') . '/' . $nameOnly . '-' . $suffix . ($extension !== '' ? '.' . $extension : '');
            $suffix++;
        } while (file_exists($candidate));

        return $candidate;
    }

    private function cleanupEmptyDirectory(?string $directory): void
    {
        if ($directory && is_dir($directory)) {
            @rmdir($directory);
        }
    }

    private function sanitizeFileName(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        $safe = trim((string) $safe, ' _');

        return $safe !== '' ? $safe : 'archivo';
    }

    private function fileExtension(string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return $extension !== '' ? $extension : '';
    }

    private function uniqueFileIdentifier(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Exception) {
            return uniqid('file', true);
        }
    }

    private function generateFileCode(int $projectId): string
    {
        do {
            $code = 'file:' . $this->uniqueFileIdentifier();
        } while ($this->codeExists($projectId, $code));

        return $code;
    }

    private function fileNodesUnder(int $projectId, int $nodeId): array
    {
        $ids = $this->descendantIds($projectId, $nodeId);
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->db->fetchAll(
            "SELECT id, code, title, file_path FROM project_nodes WHERE project_id = ? AND node_type = 'file' AND id IN ($placeholders)",
            array_merge([$projectId], $ids)
        );
    }

    private function logFileAudit(?int $userId, int $nodeId, string $action, array $payload = []): void
    {
        try {
            (new AuditLogRepository($this->db))->log(
                $userId,
                'project_node_file',
                $nodeId,
                $action,
                $payload
            );
        } catch (\Throwable) {
            // Evitar que el fallo de auditoría rompa el flujo principal
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

    private function assertDocumentFlowColumns(): void
    {
        $requiredColumns = [
            'reviewer_id',
            'validator_id',
            'approver_id',
            'reviewed_by',
            'document_status',
            'document_tags',
            'document_version',
            'document_type',
            'reviewed_at',
            'validated_by',
            'validated_at',
            'approved_by',
            'approved_at',
        ];

        $missing = [];
        foreach ($requiredColumns as $column) {
            if (!$this->db->columnExists('project_nodes', $column)) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                'Debes aplicar la migración de project_nodes (reviewer_id, validator_id, approver_id, reviewed_by, validated_by, approved_by, reviewed_at, validated_at, approved_at, document_status, document_tags, document_version, document_type) antes de guardar documentos o flujos.'
            );
        }
    }
}
