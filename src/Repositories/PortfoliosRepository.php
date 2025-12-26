<?php

declare(strict_types=1);

class PortfoliosRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    public function __construct(private Database $db, private array $rules)
    {
    }

    public function listWithUsage(array $user): array
    {
        [$conditions, $params] = $this->visibilityConditions($user);
        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $portfolios = $this->db->fetchAll(
            'SELECT p.*, c.name AS client_name, c.pm_id,
                    COUNT(pr.id) AS projects_total,
                    AVG(pr.progress) AS avg_progress,
                    SUM(pr.actual_cost) AS actual_cost,
                    SUM(pr.budget) AS budget_committed
             FROM client_portfolios p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN projects pr ON pr.portfolio_id = p.id
             ' . $whereClause . '
             GROUP BY p.id, c.name, c.pm_id
             ORDER BY c.name ASC, p.start_date ASC',
            $params
        );

        return array_map(fn (array $portfolio) => $this->attachAlerts($portfolio), $portfolios);
    }

    public function find(int $id): ?array
    {
        $portfolio = $this->db->fetchOne(
            'SELECT p.*, c.name AS client_name, c.pm_id
             FROM client_portfolios p
             JOIN clients c ON c.id = p.client_id
             WHERE p.id = :id',
            [':id' => $id]
        );

        return $portfolio ? $this->attachAlerts($portfolio) : null;
    }

    public function create(array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO client_portfolios (client_id, name, objective, description, start_date, end_date, hours_limit, budget_limit, attachment_path, projects_included, rules_notes, alerting_policy, risk_register, risk_level_text, created_at, updated_at)'
             . ' VALUES (:client_id, :name, :objective, :description, :start_date, :end_date, :hours_limit, :budget_limit, :attachment_path, :projects_included, :rules_notes, :alerting_policy, :risk_register, :risk_level_text, NOW(), NOW())',
            [
                ':client_id' => (int) $payload['client_id'],
                ':name' => $payload['name'],
                ':objective' => $payload['objective'] ?? null,
                ':description' => $payload['description'] ?? null,
                ':start_date' => $payload['start_date'] ?: null,
                ':end_date' => $payload['end_date'] ?: null,
                ':budget_total' => $payload['budget_total'] ?? null,
                ':risk_level' => $payload['risk_level'] ?? null,
                ':attachment_path' => $payload['attachment_path'] ?? null,
                ':projects_included' => $payload['projects_included'] ?? null,
                ':rules_notes' => $payload['rules_notes'] ?? null,
                ':alerting_policy' => $payload['alerting_policy'] ?? null,
                ':risk_register' => $payload['risk_register'] ?? null,
                ':risk_level_text' => $payload['risk_level_text'] ?? null,
            ]
        );
    }

    public function update(int $id, array $payload): void
    {
        $this->db->execute(
            'UPDATE client_portfolios
             SET name = :name, objective = :objective, description = :description, start_date = :start_date, end_date = :end_date,
                 hours_limit = :hours_limit, budget_limit = :budget_limit, attachment_path = :attachment_path,
                 projects_included = :projects_included, rules_notes = :rules_notes, alerting_policy = :alerting_policy,
                 risk_register = :risk_register, risk_level_text = :risk_level_text,
                 updated_at = NOW()
             WHERE id = :id',
            [
                ':id' => $id,
                ':name' => $payload['name'],
                ':objective' => $payload['objective'] ?? null,
                ':description' => $payload['description'] ?? null,
                ':start_date' => $payload['start_date'] ?: null,
                ':end_date' => $payload['end_date'] ?: null,
                ':budget_total' => $payload['budget_total'] ?? null,
                ':risk_level' => $payload['risk_level'] ?? null,
                ':attachment_path' => $payload['attachment_path'] ?? null,
                ':projects_included' => $payload['projects_included'] ?? null,
                ':rules_notes' => $payload['rules_notes'] ?? null,
                ':alerting_policy' => $payload['alerting_policy'] ?? null,
                ':risk_register' => $payload['risk_register'] ?? null,
                ':risk_level_text' => $payload['risk_level_text'] ?? null,
            ]
        );
    }

    public function storeAttachment(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No se pudo subir el archivo del portafolio.');
        }

        $allowedMime = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg'];
        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowedMime[$mime])) {
            throw new InvalidArgumentException('Adjunta PDF o una imagen (PNG/JPG).');
        }

        $targetDir = __DIR__ . '/../../public/uploads/portfolios';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('No se pudo preparar el directorio de adjuntos.');
        }

        $safeName = 'portfolio_' . time() . '.' . $allowedMime[$mime];
        $targetPath = $targetDir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }

        return '/project/public/uploads/portfolios/' . $safeName;
    }

    public function syncProjects(int $portfolioId, array $projectIds): void
    {
        if (empty($projectIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $params = array_merge([$portfolioId], array_map('intval', $projectIds));

        $this->db->execute(
            "UPDATE projects SET portfolio_id = ? WHERE id IN ($placeholders)",
            $params
        );
    }

    public function dependencySummary(int $portfolioId): array
    {
        $projects = 0;

        if ($this->db->tableExists('projects')) {
            $projects = (int) ($this->db->fetchOne('SELECT COUNT(*) AS total FROM projects WHERE portfolio_id = :id', [':id' => $portfolioId])['total'] ?? 0);
        }

        return [
            'projects' => $projects,
            'has_dependencies' => $projects > 0,
        ];
    }

    public function inactivate(int $id): bool
    {
        if (!$this->db->columnExists('client_portfolios', 'active')) {
            return false;
        }

        return $this->db->execute('UPDATE client_portfolios SET active = 0, updated_at = NOW() WHERE id = :id', [':id' => $id]);
    }

    public function delete(int $id): array
    {
        $portfolio = $this->db->fetchOne('SELECT attachment_path FROM client_portfolios WHERE id = :id', [':id' => $id]);

        if (!$portfolio) {
            return [
                'success' => false,
                'error' => 'PORTFOLIO_NOT_FOUND',
            ];
        }

        $pdo = $this->db->connection();

        try {
            $pdo->beginTransaction();

            $this->detachProjects($id);
            $this->db->execute('DELETE FROM client_portfolios WHERE id = :id', [':id' => $id]);

            $pdo->commit();

            $this->deleteAttachment($portfolio['attachment_path'] ?? null);

            return ['success' => true];
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
    }

    private function visibilityConditions(array $user): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('clients', 'pm_id');
        $hasActiveColumn = $this->db->columnExists('client_portfolios', 'active');
        $isAdmin = in_array($user['role'] ?? '', self::ADMIN_ROLES, true);

        if ($hasPmColumn && !$isAdmin) {
            $conditions[] = 'c.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        if ($hasActiveColumn && !$isAdmin) {
            $conditions[] = 'p.active = 1';
        }

        return [$conditions, $params];
    }

    private function attachAlerts(array $portfolio): array
    {
        $alertWindow = (int) ($this->rules['alerts']['portfolio_days_before_end'] ?? 15);
        $warningRatio = (float) ($this->rules['portfolio_limits']['warning_ratio'] ?? 0.85);
        $today = new DateTimeImmutable('today');
        $alerts = [];

        if (!empty($portfolio['end_date'])) {
            $endDate = new DateTimeImmutable($portfolio['end_date']);
            $daysRemaining = (int) $today->diff($endDate)->format('%r%a');
            if ($daysRemaining <= $alertWindow) {
                $alerts[] = "El portafolio vence en {$daysRemaining} días.";
            }
        }

        $portfolio['alerts'] = $alerts;
        $budgetLimit = (float) ($portfolio['budget_total'] ?? 0);
        $budgetUsed = (float) ($portfolio['actual_cost'] ?? 0);
        $portfolio['budget_ratio'] = $budgetLimit > 0 ? $budgetUsed / $budgetLimit : null;

        return $portfolio;
    }

    private function detachProjects(int $portfolioId): void
    {
        if (!$this->db->tableExists('projects')) {
            return;
        }

        $this->db->execute('UPDATE projects SET portfolio_id = NULL WHERE portfolio_id = :id', [':id' => $portfolioId]);
    }

    private function deleteAttachment(?string $path): void
    {
        $fullPath = $this->normalizePath($path);
        if ($fullPath && file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function normalizePath(?string $path): ?string
    {
        $cleanPath = trim((string) $path);
        if ($cleanPath === '') {
            return null;
        }

        if (str_starts_with($cleanPath, '/project/public/')) {
            $relative = substr($cleanPath, strlen('/project/public/'));
            return __DIR__ . '/../../public/' . ltrim($relative, '/');
        }

        if (str_starts_with($cleanPath, '/uploads/')) {
            return __DIR__ . '/../../public/' . ltrim($cleanPath, '/');
        }

        return null;
    }
}
