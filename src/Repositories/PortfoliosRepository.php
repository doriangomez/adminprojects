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

    public function create(array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO client_portfolios (client_id, name, start_date, end_date, budget_total, risk_level, attachment_path, projects_included, rules_notes, alerting_policy, created_at, updated_at)
             VALUES (:client_id, :name, :start_date, :end_date, :budget_total, :risk_level, :attachment_path, :projects_included, :rules_notes, :alerting_policy, NOW(), NOW())',
            [
                ':client_id' => (int) $payload['client_id'],
                ':name' => $payload['name'],
                ':start_date' => $payload['start_date'] ?: null,
                ':end_date' => $payload['end_date'] ?: null,
                ':budget_total' => $payload['budget_total'] ?? null,
                ':risk_level' => $payload['risk_level'] ?? null,
                ':attachment_path' => $payload['attachment_path'] ?? null,
                ':projects_included' => $payload['projects_included'] ?? null,
                ':rules_notes' => $payload['rules_notes'] ?? null,
                ':alerting_policy' => $payload['alerting_policy'] ?? null,
            ]
        );
    }

    public function update(int $id, array $payload): void
    {
        $this->db->execute(
            'UPDATE client_portfolios
             SET name = :name, start_date = :start_date, end_date = :end_date,
                 budget_total = :budget_total, risk_level = :risk_level, attachment_path = :attachment_path,
                 projects_included = :projects_included, rules_notes = :rules_notes, alerting_policy = :alerting_policy,
                 updated_at = NOW()
             WHERE id = :id',
            [
                ':id' => $id,
                ':name' => $payload['name'],
                ':start_date' => $payload['start_date'] ?: null,
                ':end_date' => $payload['end_date'] ?: null,
                ':budget_total' => $payload['budget_total'] ?? null,
                ':risk_level' => $payload['risk_level'] ?? null,
                ':attachment_path' => $payload['attachment_path'] ?? null,
                ':projects_included' => $payload['projects_included'] ?? null,
                ':rules_notes' => $payload['rules_notes'] ?? null,
                ':alerting_policy' => $payload['alerting_policy'] ?? null,
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

    private function visibilityConditions(array $user): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('clients', 'pm_id');

        if ($hasPmColumn && !in_array($user['role'] ?? '', self::ADMIN_ROLES, true)) {
            $conditions[] = 'c.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
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
}
