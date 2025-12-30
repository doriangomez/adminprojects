<?php

declare(strict_types=1);

class ProjectNodesRepository
{
    private const STATUSES = ['pendiente', 'en_progreso', 'completado', 'bloqueado'];

    public function __construct(
        private Database $db,
        private ?ProjectsRepository $projectsRepo = null
    ) {
        $this->projectsRepo ??= new ProjectsRepository($this->db);
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
        $this->ensureBaseTree($projectId, $phases);
        $this->syncPhaseNodes($projectId, $phase, $phases);
        $isoData = $this->syncIsoNodes($projectId);

        return [
            'flags' => $isoData['flags'],
            'nodes' => $this->treeWithFiles($projectId),
            'pending_critical' => $isoData['pending_critical'],
        ];
    }

    public function criticalPendingNodes(int $projectId): array
    {
        if (!$this->db->tableExists('project_nodes')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT id, name, code, status
             FROM project_nodes
             WHERE project_id = :project
               AND critical = 1
               AND status != :completed
             ORDER BY sort_order ASC, id ASC',
            [
                ':project' => $projectId,
                ':completed' => 'completado',
            ]
        );
    }

    public function treeWithFiles(int $projectId): array
    {
        if (!$this->db->tableExists('project_nodes')) {
            return [];
        }

        $nodes = $this->db->fetchAll(
            'SELECT id, parent_id, name, code, node_type, iso_code, status, critical, sort_order, created_at, updated_at
             FROM project_nodes
             WHERE project_id = :project
             ORDER BY parent_id IS NULL DESC, sort_order ASC, id ASC',
            [':project' => $projectId]
        );

        if (empty($nodes)) {
            return [];
        }

        $nodeIds = array_column($nodes, 'id');
        $files = [];
        if ($this->db->tableExists('project_files')) {
            $files = $this->db->fetchAll(
                'SELECT id, project_node_id, file_name, storage_path, mime_type, size_bytes, uploaded_by, created_at
                 FROM project_files
                 WHERE project_node_id IN (' . implode(',', array_fill(0, count($nodeIds), '?')) . ')
                 ORDER BY created_at DESC',
                $nodeIds
            );
        }

        $fileIndex = [];
        foreach ($files as $file) {
            $nodeId = (int) ($file['project_node_id'] ?? 0);
            $fileIndex[$nodeId][] = $file;
        }

        $indexed = [];
        foreach ($nodes as $node) {
            $node['children'] = [];
            $nodeId = (int) $node['id'];
            $node['files'] = $fileIndex[$nodeId] ?? [];
            $indexed[$nodeId] = $node;
        }

        $tree = [];
        foreach ($indexed as $nodeId => &$node) {
            $parentId = $node['parent_id'];
            if ($parentId !== null && isset($indexed[$parentId])) {
                $indexed[$parentId]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return $tree;
    }

    public function storeFile(int $projectId, int $nodeId, array $file, ?int $userId = null): array
    {
        if (!$this->db->tableExists('project_files')) {
            throw new \InvalidArgumentException('No se encontró el repositorio de archivos del proyecto.');
        }

        $node = $this->db->fetchOne(
            'SELECT id, project_id, code, name FROM project_nodes WHERE id = :id AND project_id = :project',
            [':id' => $nodeId, ':project' => $projectId]
        );

        if (!$node) {
            throw new \InvalidArgumentException('El nodo de proyecto no existe.');
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Selecciona un archivo válido para adjuntar.');
        }

        $originalName = (string) ($file['name'] ?? 'archivo');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $safeName = $safeName !== '' ? $safeName : 'adjunto';
        $targetDir = __DIR__ . '/../../public/uploads/projects/' . $projectId . '/' . $nodeId;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de adjuntos.');
        }

        $targetPath = $targetDir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el nodo.');
        }

        $publicPath = '/project/public/uploads/projects/' . $projectId . '/' . $nodeId . '/' . $safeName;
        $mimeType = (string) ($file['type'] ?? mime_content_type($targetPath));
        $sizeBytes = (int) ($file['size'] ?? filesize($targetPath));

        $fileId = $this->db->insert(
            'INSERT INTO project_files (project_node_id, file_name, storage_path, mime_type, size_bytes, uploaded_by)
             VALUES (:node, :file_name, :path, :mime, :size_bytes, :uploaded_by)',
            [
                ':node' => $nodeId,
                ':file_name' => $safeName,
                ':path' => $publicPath,
                ':mime' => $mimeType !== '' ? $mimeType : null,
                ':size_bytes' => $sizeBytes > 0 ? $sizeBytes : null,
                ':uploaded_by' => $userId ?: null,
            ]
        );

        return [
            'id' => $fileId,
            'file_name' => $safeName,
            'storage_path' => $publicPath,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ];
    }

    private function ensureBaseTree(int $projectId, array $phases): void
    {
        $phasesRoot = $this->ensureNode($projectId, null, 'Fases', 'carpeta', 'phases', 0, 0, null);
        foreach ($phases as $index => $phase) {
            $this->ensureNode(
                $projectId,
                $phasesRoot,
                ucfirst($phase),
                'fase',
                'phase:' . $phase,
                0,
                $index
            );
        }

        $isoRoot = $this->ensureNode($projectId, null, 'ISO 9001 - 8.3', 'carpeta', 'iso_8_3', 0, 100);
        $this->ensureNode($projectId, $isoRoot, 'Entradas de diseño', 'control', 'design_inputs', 1, 110, '8.3.3');
        $this->ensureNode($projectId, $isoRoot, 'Revisión de diseño', 'control', 'design_review', 1, 120, '8.3.4');
        $this->ensureNode($projectId, $isoRoot, 'Verificación de diseño', 'control', 'design_verification', 1, 130, '8.3.4');
        $this->ensureNode($projectId, $isoRoot, 'Validación de diseño', 'control', 'design_validation', 1, 140, '8.3.4');
        $this->ensureNode($projectId, $isoRoot, 'Control de cambios de diseño', 'cambio', 'design_changes', 1, 150, '8.3.6');
    }

    private function ensureNode(
        int $projectId,
        ?int $parentId,
        string $name,
        string $nodeType,
        string $code,
        int $critical,
        int $sortOrder,
        ?string $isoCode = null
    ): int {
        $existing = $this->db->fetchOne(
            'SELECT id FROM project_nodes WHERE project_id = :project AND code = :code LIMIT 1',
            [':project' => $projectId, ':code' => $code]
        );

        if ($existing) {
            return (int) $existing['id'];
        }

        return $this->db->insert(
            'INSERT INTO project_nodes (project_id, parent_id, code, name, node_type, iso_code, status, critical, sort_order)
             VALUES (:project_id, :parent_id, :code, :name, :node_type, :iso_code, :status, :critical, :sort_order)',
            [
                ':project_id' => $projectId,
                ':parent_id' => $parentId,
                ':code' => $code,
                ':name' => $name,
                ':node_type' => $nodeType,
                ':iso_code' => $isoCode,
                ':status' => 'pendiente',
                ':critical' => $critical,
                ':sort_order' => $sortOrder,
            ]
        );
    }

    private function syncPhaseNodes(int $projectId, ?string $currentPhase, array $phases): void
    {
        if (empty($phases)) {
            return;
        }

        $currentIndex = $currentPhase !== null ? array_search($currentPhase, $phases, true) : false;

        foreach ($phases as $index => $phase) {
            $status = 'pendiente';
            if ($currentIndex !== false) {
                if ($index < $currentIndex) {
                    $status = 'completado';
                } elseif ($index === $currentIndex) {
                    $status = 'en_progreso';
                }
            } elseif ($index === 0) {
                $status = 'en_progreso';
            }

            $this->updateNodeStatusByCode($projectId, 'phase:' . $phase, $status);
        }
    }

    private function syncIsoNodes(int $projectId): array
    {
        $flags = $this->defaultIsoFlags();
        $designInputsCount = $this->safeCount(fn () => (new DesignInputsRepository($this->db))->countByProject($projectId));
        $controlsRepo = new DesignControlsRepository($this->db);
        $revisionStatus = $this->nodeStatusFromControls($controlsRepo, $projectId, 'revision');
        $verificationStatus = $this->nodeStatusFromControls($controlsRepo, $projectId, 'verificacion');
        $validationStatus = $this->nodeStatusFromControls($controlsRepo, $projectId, 'validacion');

        $changesRepo = new DesignChangesRepository($this->db);
        $changeSummary = $this->designChangeSummary($changesRepo, $projectId);

        $flags['design_inputs_defined'] = $designInputsCount > 0 ? 1 : 0;
        $flags['design_review_done'] = $revisionStatus === 'completado' || $revisionStatus === 'en_progreso' ? 1 : 0;
        $flags['design_verification_done'] = $verificationStatus === 'completado' ? 1 : 0;
        $flags['design_validation_done'] = $validationStatus === 'completado' ? 1 : 0;

        $this->updateNodeStatusByCode($projectId, 'design_inputs', $designInputsCount > 0 ? 'completado' : 'pendiente');
        $this->updateNodeStatusByCode($projectId, 'design_review', $revisionStatus);
        $this->updateNodeStatusByCode($projectId, 'design_verification', $verificationStatus);
        $this->updateNodeStatusByCode($projectId, 'design_validation', $validationStatus);

        $changeStatus = $changeSummary['pending'] > 0 ? 'bloqueado' : 'completado';
        $this->updateNodeStatusByCode($projectId, 'design_changes', $changeStatus);

        $this->projectsRepo?->persistIsoFlags($projectId, $flags, null);

        return [
            'flags' => $flags,
            'pending_critical' => $this->criticalPendingNodes($projectId),
        ];
    }

    private function updateNodeStatusByCode(int $projectId, string $code, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }

        $this->db->execute(
            'UPDATE project_nodes SET status = :status WHERE project_id = :project AND code = :code',
            [
                ':status' => $status,
                ':project' => $projectId,
                ':code' => $code,
            ]
        );
    }

    private function nodeStatusFromControls(DesignControlsRepository $repo, int $projectId, string $type): string
    {
        $total = $repo->countByType($projectId, $type);
        if ($total <= 0) {
            return 'pendiente';
        }

        $approved = $repo->countByTypeAndResult($projectId, $type, 'aprobado');
        if ($approved > 0) {
            return 'completado';
        }

        return 'en_progreso';
    }

    private function designChangeSummary(DesignChangesRepository $repo, int $projectId): array
    {
        $summary = $repo->statusSummary($projectId);

        return [
            'pending' => (int) ($summary['pending'] ?? 0),
            'approved' => (int) ($summary['approved'] ?? 0),
            'total' => (int) ($summary['total'] ?? 0),
        ];
    }

    private function safeCount(callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (\Throwable) {
            return 0;
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
