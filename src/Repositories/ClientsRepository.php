<?php

declare(strict_types=1);

class ClientsRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    public function __construct(private Database $db)
    {
    }

    public function listForUser(array $user): array
    {
        $params = [];
        $conditions = [];
        $hasPmColumn = $this->db->columnExists('clients', 'pm_id');
        $hasActiveColumn = $this->db->columnExists('clients', 'active');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'c.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        if ($hasActiveColumn) {
            $conditions[] = 'c.active = 1';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $priorityField = 'c.priority_code AS priority';
        $priorityJoin = 'LEFT JOIN priorities pr ON pr.code = c.priority_code';
        $pmFields = $hasPmColumn ? 'u.name AS pm_name' : 'NULL AS pm_name';
        $pmJoin = $hasPmColumn ? 'LEFT JOIN users u ON u.id = c.pm_id' : '';

        return $this->db->fetchAll(
            'SELECT c.*, ' . $priorityField . ', sec.label AS sector_label, cat.label AS category_label, pr.label AS priority_label, st.label AS status_label, risk.label AS risk_label, area.label AS area_label, ' . $pmFields . '
             FROM clients c
             LEFT JOIN client_sectors sec ON sec.code = c.sector_code
             LEFT JOIN client_categories cat ON cat.code = c.category_code
             ' . $priorityJoin . '
             LEFT JOIN client_status st ON st.code = c.status_code
             LEFT JOIN client_risk risk ON risk.code = c.risk_code
             LEFT JOIN client_areas area ON area.code = c.area_code
             ' . $pmJoin . '
             ' . $where . '
             ORDER BY c.created_at DESC',
            $params
        );
    }

    public function findForUser(int $id, array $user): ?array
    {
        $params = [':id' => $id];
        $conditions = ['c.id = :id'];
        $hasPmColumn = $this->db->columnExists('clients', 'pm_id');
        $hasActiveColumn = $this->db->columnExists('clients', 'active');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'c.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        if ($hasActiveColumn) {
            $conditions[] = 'c.active = 1';
        }

        $priorityField = 'c.priority_code AS priority';
        $priorityJoin = 'LEFT JOIN priorities pr ON pr.code = c.priority_code';
        $pmFields = $hasPmColumn ? 'u.name AS pm_name, u.email AS pm_email' : 'NULL AS pm_name, NULL AS pm_email';
        $pmJoin = $hasPmColumn ? 'LEFT JOIN users u ON u.id = c.pm_id' : '';

        $client = $this->db->fetchOne(
            'SELECT c.*, ' . $priorityField . ', sec.label AS sector_label, cat.label AS category_label, pr.label AS priority_label, st.label AS status_label, risk.label AS risk_label, area.label AS area_label, ' . $pmFields . '
             FROM clients c
             LEFT JOIN client_sectors sec ON sec.code = c.sector_code
             LEFT JOIN client_categories cat ON cat.code = c.category_code
             ' . $priorityJoin . '
             LEFT JOIN client_status st ON st.code = c.status_code
             LEFT JOIN client_risk risk ON risk.code = c.risk_code
             LEFT JOIN client_areas area ON area.code = c.area_code
             ' . $pmJoin . '
             WHERE ' . implode(' AND ', $conditions),
            $params
        );

        return $client ?: null;
    }

    public function projectsForClient(int $clientId, array $user = []): array
    {
        $params = [':clientId' => $clientId];
        $where = 'WHERE p.client_id = :clientId';
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        $hasPriorityColumn = $this->db->columnExists('projects', 'priority_code');
        $hasStatusColumn = $this->db->columnExists('projects', 'status_code');
        $hasHealthColumn = $this->db->columnExists('projects', 'health_code');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $where .= ' AND p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $select = [
            'p.id',
            'p.name',
            'p.progress',
            'p.pm_id',
            'p.client_id',
            'p.project_type',
            'p.created_at',
            'p.updated_at',
        ];

        $joins = [];

        if ($hasPriorityColumn) {
            $select[] = 'p.priority_code AS priority';
            $select[] = 'pr.label AS priority_label';
            $joins[] = 'LEFT JOIN priorities pr ON pr.code = p.priority_code';
        } else {
            $select[] = 'NULL AS priority';
            $select[] = 'NULL AS priority_label';
        }

        if ($hasStatusColumn) {
            $select[] = 'p.status_code AS status';
            $select[] = 'st.label AS status_label';
            $joins[] = 'LEFT JOIN project_status st ON st.code = p.status_code';
        } else {
            $select[] = "'' AS status";
            $select[] = 'NULL AS status_label';
        }

        if ($hasHealthColumn) {
            $select[] = 'p.health_code AS health';
            $select[] = 'h.label AS health_label';
            $joins[] = 'LEFT JOIN project_health h ON h.code = p.health_code';
        } else {
            $select[] = 'NULL AS health';
            $select[] = 'NULL AS health_label';
        }

        $sql = sprintf(
            'SELECT %s FROM projects p %s %s ORDER BY p.created_at DESC',
            implode(', ', $select),
            implode(' ', $joins),
            $where
        );

        return $this->db->fetchAll($sql, $params);
    }

    public function projectSnapshot(int $clientId, array $user = []): array
    {
        $params = [':clientId' => $clientId];
        $conditions = ['client_id = :clientId'];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $snapshot = $this->db->fetchOne(
            "SELECT COUNT(*) AS total, AVG(progress) AS avg_progress,
                    SUM(CASE WHEN health_code IN ('at_risk','critical','red','yellow') THEN 1 ELSE 0 END) AS at_risk,
                    SUM(CASE WHEN status_code = 'closed' THEN 1 ELSE 0 END) AS closed
             FROM projects WHERE " . implode(' AND ', $conditions),
            $params
        );

        return [
            'total' => (int) ($snapshot['total'] ?? 0),
            'avg_progress' => round((float) ($snapshot['avg_progress'] ?? 0), 1),
            'at_risk' => (int) ($snapshot['at_risk'] ?? 0),
            'closed' => (int) ($snapshot['closed'] ?? 0),
        ];
    }

    public function create(array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO clients (name, sector_code, category_code, priority_code, status_code, pm_id, satisfaction, nps, risk_code, tags, area_code, feedback_notes, feedback_history, operational_context, logo_path, created_at, updated_at)'
             . ' VALUES (:name, :sector_code, :category_code, :priority_code, :status_code, :pm_id, :satisfaction, :nps, :risk_code, :tags, :area_code, :feedback_notes, :feedback_history, :operational_context, :logo_path, NOW(), NOW())',
            [
                ':name' => $payload['name'],
                ':sector_code' => $payload['sector_code'],
                ':category_code' => $payload['category_code'],
                ':priority_code' => $payload['priority_code'],
                ':status_code' => $payload['status_code'],
                ':pm_id' => (int) $payload['pm_id'],
                ':satisfaction' => $payload['satisfaction'] ?? null,
                ':nps' => $payload['nps'] ?? null,
                ':risk_code' => $payload['risk_code'] ?? null,
                ':tags' => $payload['tags'] ?? null,
                ':area_code' => $payload['area_code'] ?? null,
                ':feedback_notes' => $payload['feedback_notes'] ?? null,
                ':feedback_history' => $payload['feedback_history'] ?? null,
                ':operational_context' => $payload['operational_context'] ?? null,
                ':logo_path' => $payload['logo_path'] ?? null,
            ]
        );
    }

    public function update(int $id, array $payload): void
    {
        $this->db->execute(
            'UPDATE clients SET name = :name, sector_code = :sector_code, category_code = :category_code, priority_code = :priority_code, status_code = :status_code, pm_id = :pm_id, satisfaction = :satisfaction, nps = :nps, risk_code = :risk_code, tags = :tags, area_code = :area_code, feedback_notes = :feedback_notes, feedback_history = :feedback_history, operational_context = :operational_context, logo_path = :logo_path, updated_at = NOW() WHERE id = :id',
            [
                ':name' => $payload['name'],
                ':sector_code' => $payload['sector_code'],
                ':category_code' => $payload['category_code'],
                ':priority_code' => $payload['priority_code'],
                ':status_code' => $payload['status_code'],
                ':pm_id' => (int) $payload['pm_id'],
                ':satisfaction' => $payload['satisfaction'] ?? null,
                ':nps' => $payload['nps'] ?? null,
                ':risk_code' => $payload['risk_code'] ?? null,
                ':tags' => $payload['tags'] ?? null,
                ':area_code' => $payload['area_code'] ?? null,
                ':feedback_notes' => $payload['feedback_notes'] ?? null,
                ':feedback_history' => $payload['feedback_history'] ?? null,
                ':operational_context' => $payload['operational_context'] ?? null,
                ':logo_path' => $payload['logo_path'] ?? null,
                ':id' => $id,
            ]
        );
    }

    public function find(int $id): ?array
    {
        $client = $this->db->fetchOne('SELECT * FROM clients WHERE id = :id', [':id' => $id]);

        return $client ?: null;
    }

    public function dependencySummary(int $id): array
    {
        $projects = 0;
        $portfolios = 0;
        $contracts = 0;

        if ($this->db->tableExists('projects')) {
            $projects = (int) ($this->db->fetchOne('SELECT COUNT(*) AS total FROM projects WHERE client_id = :id', [':id' => $id])['total'] ?? 0);
        }

        if ($this->db->tableExists('client_portfolios')) {
            $portfolios = (int) ($this->db->fetchOne('SELECT COUNT(*) AS total FROM client_portfolios WHERE client_id = :id', [':id' => $id])['total'] ?? 0);
        }

        if ($this->db->tableExists('contracts') && $this->db->columnExists('contracts', 'client_id')) {
            $contracts = (int) ($this->db->fetchOne('SELECT COUNT(*) AS total FROM contracts WHERE client_id = :id', [':id' => $id])['total'] ?? 0);
        }

        $hasDependencies = ($projects + $portfolios + $contracts) > 0;

        return [
            'projects' => $projects,
            'portfolios' => $portfolios,
            'contracts' => $contracts,
            'has_dependencies' => $hasDependencies,
        ];
    }

    public function deleteClient(int $id, ?string $clientLogoPath = null, bool $forceDelete = false): array
    {
        $dependencies = $this->dependencySummary($id);

        if ($dependencies['has_dependencies'] && !$forceDelete) {
            return [
                'success' => false,
                'error_code' => 'DEPENDENCIES',
                'dependencies' => $dependencies,
            ];
        }

        return $forceDelete
            ? $this->forceDeleteWithCascade($id, $clientLogoPath)
            : $this->deleteWithoutDependencies($id, $clientLogoPath);
    }

    public function inactivate(int $id): bool
    {
        return $this->db->execute('UPDATE clients SET active = 0, updated_at = NOW() WHERE id = :id', [':id' => $id]);
    }

    public function deleteWithoutDependencies(int $id, ?string $clientLogoPath = null): array
    {
        try {
            $this->db->execute('DELETE FROM clients WHERE id = :id', [':id' => $id]);

            if ($clientLogoPath) {
                $this->deleteFiles([$clientLogoPath]);
            }

            return ['success' => true];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', self::ADMIN_ROLES, true);
    }

    private function placeholders(array $items): string
    {
        return implode(', ', array_fill(0, count($items), '?'));
    }

    private function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $fullPath = $this->normalizePath($path);
            if ($fullPath && file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    private function forceDeleteWithCascade(int $clientId, ?string $clientLogoPath = null): array
    {
        $pdo = $this->db->connection();
        $attachments = $this->portfolioAttachments($clientId);

        if ($clientLogoPath) {
            $attachments[] = $clientLogoPath;
        }

        try {
            $pdo->beginTransaction();

            $this->deleteTimesheets($clientId);
            $this->deleteTasks($clientId);
            $this->deleteAssignments($clientId);
            $this->deleteProjects($clientId);
            $this->deletePortfolios($clientId);
            $this->deleteContracts($clientId);

            $this->db->execute('DELETE FROM clients WHERE id = :id', [':id' => $clientId]);

            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }

        if ($attachments) {
            $this->deleteFiles($attachments);
        }

        return ['success' => true];
    }

    private function deleteTimesheets(int $clientId): void
    {
        if (!$this->db->tableExists('timesheets') || !$this->db->tableExists('tasks') || !$this->db->tableExists('projects')) {
            return;
        }

        $this->db->execute(
            'DELETE ts FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             JOIN projects p ON p.id = t.project_id
             WHERE p.client_id = :clientId',
            [':clientId' => $clientId]
        );
    }

    private function deleteTasks(int $clientId): void
    {
        if (!$this->db->tableExists('tasks') || !$this->db->tableExists('projects')) {
            return;
        }

        $this->db->execute(
            'DELETE t FROM tasks t JOIN projects p ON p.id = t.project_id WHERE p.client_id = :clientId',
            [':clientId' => $clientId]
        );
    }

    private function deleteAssignments(int $clientId): void
    {
        if (!$this->db->tableExists('project_talent_assignments') || !$this->db->tableExists('projects')) {
            return;
        }

        $this->db->execute(
            'DELETE a FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             WHERE p.client_id = :clientId',
            [':clientId' => $clientId]
        );
    }

    private function deleteProjects(int $clientId): void
    {
        if (!$this->db->tableExists('projects')) {
            return;
        }

        $this->db->execute('DELETE FROM projects WHERE client_id = :clientId', [':clientId' => $clientId]);
    }

    private function deletePortfolios(int $clientId): void
    {
        if (!$this->db->tableExists('client_portfolios')) {
            return;
        }

        $this->db->execute('DELETE FROM client_portfolios WHERE client_id = :clientId', [':clientId' => $clientId]);
    }

    private function deleteContracts(int $clientId): void
    {
        if (!$this->db->tableExists('contracts') || !$this->db->columnExists('contracts', 'client_id')) {
            return;
        }

        $this->db->execute('DELETE FROM contracts WHERE client_id = :clientId', [':clientId' => $clientId]);
    }

    private function portfolioAttachments(int $clientId): array
    {
        if (!$this->db->tableExists('client_portfolios') || !$this->db->columnExists('client_portfolios', 'attachment_path')) {
            return [];
        }

        $attachments = $this->db->fetchAll(
            'SELECT attachment_path FROM client_portfolios WHERE client_id = :clientId AND attachment_path IS NOT NULL',
            [':clientId' => $clientId]
        );

        return array_unique(array_filter(array_map(fn ($row) => $row['attachment_path'] ?? null, $attachments)));
    }

    private function normalizePath(string $path): ?string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '/project/public/')) {
            $relative = substr($trimmed, strlen('/project/public/'));
            return __DIR__ . '/../../public/' . ltrim($relative, '/');
        }

        if (str_starts_with($trimmed, '/uploads/')) {
            return __DIR__ . '/../../public/' . ltrim($trimmed, '/');
        }

        return null;
    }
}
