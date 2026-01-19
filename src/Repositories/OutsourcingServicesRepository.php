<?php

declare(strict_types=1);

class OutsourcingServicesRepository
{
    private const FREQUENCIES = ['weekly', 'monthly'];
    private const SERVICE_STATUSES = ['active', 'paused', 'ended'];
    private const HEALTH_STATUSES = ['green', 'yellow', 'red'];

    public function __construct(private Database $db)
    {
    }

    public function listServices(array $user, array $filters = []): array
    {
        if (!$this->db->tableExists('outsourcing_services')) {
            return [];
        }

        $conditions = [];
        $params = [];
        $clientId = (int) ($filters['client_id'] ?? 0);
        if ($clientId > 0) {
            $conditions[] = 's.client_id = :client_id';
            $params[':client_id'] = $clientId;
        }
        $talentId = (int) ($filters['talent_id'] ?? 0);
        if ($talentId > 0) {
            $conditions[] = 's.talent_id = :talent_id';
            $params[':talent_id'] = $talentId;
        }
        $projectId = (int) ($filters['project_id'] ?? 0);
        if ($projectId > 0) {
            $conditions[] = 's.project_id = :project_id';
            $params[':project_id'] = $projectId;
        }
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $services = $this->db->fetchAll(
            'SELECT s.id, s.talent_id, s.client_id, s.project_id, s.start_date, s.end_date, s.followup_frequency,
                    s.service_status, s.observations, s.created_at, s.updated_at,
                    u.name AS talent_name, u.email AS talent_email,
                    c.name AS client_name,
                    p.name AS project_name
             FROM outsourcing_services s
             JOIN users u ON u.id = s.talent_id
             JOIN clients c ON c.id = s.client_id
             LEFT JOIN projects p ON p.id = s.project_id
             ' . $where . '
             ORDER BY s.created_at DESC',
            $params
        );

        $healthMap = [];
        if ($this->db->tableExists('outsourcing_followups')) {
            $healthRows = $this->db->fetchAll(
                'SELECT f.id, f.service_id, f.service_health, f.period_end, f.document_node_id, f.observations, f.created_at
                 FROM outsourcing_followups f
                 JOIN (
                    SELECT service_id, MAX(created_at) AS max_created
                    FROM outsourcing_followups
                    GROUP BY service_id
                 ) latest ON latest.service_id = f.service_id AND latest.max_created = f.created_at'
            );

            foreach ($healthRows as $row) {
                $healthMap[(int) $row['service_id']] = [
                    'followup_id' => $row['id'] ?? null,
                    'service_health' => $row['service_health'] ?? null,
                    'period_end' => $row['period_end'] ?? null,
                    'document_node_id' => $row['document_node_id'] ?? null,
                    'observations' => $row['observations'] ?? null,
                    'created_at' => $row['created_at'] ?? null,
                ];
            }
        }

        foreach ($services as &$service) {
            $health = $healthMap[(int) $service['id']] ?? [];
            $service['last_followup_id'] = $health['followup_id'] ?? null;
            $service['current_health'] = $health['service_health'] ?? null;
            $service['health_updated_at'] = $health['created_at'] ?? null;
            $service['last_followup_end'] = $health['period_end'] ?? null;
            $service['last_followup_document_node_id'] = $health['document_node_id'] ?? null;
            $service['last_followup_observations'] = $health['observations'] ?? null;
        }
        unset($service);

        $serviceHealth = strtolower(trim((string) ($filters['service_health'] ?? '')));
        if (in_array($serviceHealth, self::HEALTH_STATUSES, true)) {
            $services = array_values(array_filter(
                $services,
                static fn (array $service): bool => ($service['current_health'] ?? null) === $serviceHealth
            ));
        }

        return $services;
    }

    public function findService(int $serviceId): ?array
    {
        if (!$this->db->tableExists('outsourcing_services')) {
            return null;
        }

        $service = $this->db->fetchOne(
            'SELECT s.id, s.talent_id, s.client_id, s.project_id, s.start_date, s.end_date, s.followup_frequency,
                    s.service_status, s.observations, s.created_at, s.updated_at,
                    u.name AS talent_name, u.email AS talent_email,
                    c.name AS client_name,
                    p.name AS project_name,
                    p.progress AS project_progress
             FROM outsourcing_services s
             JOIN users u ON u.id = s.talent_id
             JOIN clients c ON c.id = s.client_id
             LEFT JOIN projects p ON p.id = s.project_id
             WHERE s.id = :id
             LIMIT 1',
            [':id' => $serviceId]
        );

        return $service ?: null;
    }

    public function timesheetSummary(array $service): array
    {
        if (!$this->db->tableExists('timesheets') || !$this->db->tableExists('tasks') || !$this->db->tableExists('talents')) {
            return [
                'total_hours' => 0.0,
                'approved_hours' => 0.0,
                'pending_hours' => 0.0,
                'hours_by_project' => [],
                'hours_by_talent' => [],
                'period_start' => null,
                'period_end' => null,
            ];
        }

        $projectId = (int) ($service['project_id'] ?? 0);
        $userId = (int) ($service['talent_id'] ?? 0);
        if ($userId <= 0) {
            return [
                'total_hours' => 0.0,
                'approved_hours' => 0.0,
                'pending_hours' => 0.0,
                'hours_by_project' => [],
                'hours_by_talent' => [],
                'period_start' => null,
                'period_end' => null,
            ];
        }

        $talent = $this->db->fetchOne(
            'SELECT id, name FROM talents WHERE user_id = :user LIMIT 1',
            [':user' => $userId]
        );

        if (!$talent) {
            return [
                'total_hours' => 0.0,
                'approved_hours' => 0.0,
                'pending_hours' => 0.0,
                'hours_by_project' => [],
                'hours_by_talent' => [],
                'period_start' => null,
                'period_end' => null,
            ];
        }

        $periodStart = $service['start_date'] ?? null;
        $periodEnd = $service['end_date'] ?? date('Y-m-d');
        $conditions = ['ts.talent_id = :talent'];
        $params = [':talent' => (int) $talent['id']];
        $usesTimesheetProject = $this->db->columnExists('timesheets', 'project_id');
        $needsTaskJoin = false;
        if ($projectId > 0) {
            if ($usesTimesheetProject) {
                $conditions[] = 'ts.project_id = :project';
            } else {
                $conditions[] = 't.project_id = :project';
                $needsTaskJoin = true;
            }
            $params[':project'] = $projectId;
        }
        if ($periodStart) {
            $conditions[] = 'ts.date BETWEEN :start AND :end';
            $params[':start'] = $periodStart;
            $params[':end'] = $periodEnd;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $row = $this->db->fetchOne(
            "SELECT SUM(ts.hours) AS total_hours,
                    SUM(CASE WHEN ts.status = 'approved' THEN ts.hours ELSE 0 END) AS approved_hours,
                    SUM(CASE WHEN ts.status IN ('pending','submitted','pending_approval') THEN ts.hours ELSE 0 END) AS pending_hours
             FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             {$where}",
            $params
        );

        $hoursByProject = $this->db->fetchAll(
            "SELECT p.name AS project, SUM(ts.hours) AS total_hours
             FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             JOIN projects p ON p.id = t.project_id
             {$where}
             GROUP BY p.id
             ORDER BY total_hours DESC",
            $params
        );

        $hoursByTalent = $this->db->fetchAll(
            "SELECT ta.name AS talent, SUM(ts.hours) AS total_hours
             FROM timesheets ts
             " . ($needsTaskJoin ? 'JOIN tasks t ON t.id = ts.task_id' : '') . "
             JOIN talents ta ON ta.id = ts.talent_id
             {$where}
             GROUP BY ta.id
             ORDER BY total_hours DESC",
            $params
        );

        return [
            'total_hours' => (float) ($row['total_hours'] ?? 0),
            'approved_hours' => (float) ($row['approved_hours'] ?? 0),
            'pending_hours' => (float) ($row['pending_hours'] ?? 0),
            'hours_by_project' => $hoursByProject,
            'hours_by_talent' => $hoursByTalent,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    public function createService(array $payload): int
    {
        if (!$this->db->tableExists('outsourcing_services')) {
            throw new \RuntimeException('La tabla de servicios outsourcing no está disponible.');
        }

        $status = strtolower(trim((string) ($payload['service_status'] ?? 'active')));
        if (!in_array($status, self::SERVICE_STATUSES, true)) {
            throw new \InvalidArgumentException('El estado del servicio no es válido.');
        }

        $frequency = strtolower(trim((string) ($payload['followup_frequency'] ?? 'monthly')));
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            throw new \InvalidArgumentException('La frecuencia de seguimiento no es válida.');
        }

        $startDate = trim((string) ($payload['start_date'] ?? ''));
        if ($startDate === '') {
            throw new \InvalidArgumentException('La fecha de inicio es obligatoria.');
        }

        $endDate = trim((string) ($payload['end_date'] ?? ''));
        if ($endDate !== '' && strtotime($endDate) < strtotime($startDate)) {
            throw new \InvalidArgumentException('La fecha de fin no puede ser anterior al inicio.');
        }

        $columns = [
            'talent_id',
            'client_id',
            'project_id',
            'start_date',
            'end_date',
            'followup_frequency',
            'service_status',
            'created_by',
        ];

        $params = [
            ':talent_id' => (int) $payload['talent_id'],
            ':client_id' => (int) $payload['client_id'],
            ':project_id' => $payload['project_id'] ? (int) $payload['project_id'] : null,
            ':start_date' => $startDate,
            ':end_date' => $endDate !== '' ? $endDate : null,
            ':followup_frequency' => $frequency,
            ':service_status' => $status,
            ':created_by' => (int) ($payload['created_by'] ?? 0),
        ];

        if ($this->db->columnExists('outsourcing_services', 'observations')) {
            $columns[] = 'observations';
            $params[':observations'] = trim((string) ($payload['observations'] ?? '')) ?: null;
        }

        return $this->db->insert(
            'INSERT INTO outsourcing_services
                (' . implode(', ', $columns) . ')
             VALUES
                (' . implode(', ', array_keys($params)) . ')',
            $params
        );
    }

    public function updateServiceStatus(int $serviceId, string $status): void
    {
        if (!$this->db->tableExists('outsourcing_services')) {
            throw new \RuntimeException('La tabla de servicios outsourcing no está disponible.');
        }

        $normalized = strtolower(trim($status));
        if (!in_array($normalized, self::SERVICE_STATUSES, true)) {
            throw new \InvalidArgumentException('El estado del servicio no es válido.');
        }

        $this->db->execute(
            'UPDATE outsourcing_services SET service_status = :status, updated_at = NOW() WHERE id = :id',
            [
                ':status' => $normalized,
                ':id' => $serviceId,
            ]
        );
    }

    public function updateServiceFrequency(int $serviceId, string $frequency): void
    {
        if (!$this->db->tableExists('outsourcing_services')) {
            throw new \RuntimeException('La tabla de servicios outsourcing no está disponible.');
        }

        $normalized = strtolower(trim($frequency));
        if (!in_array($normalized, self::FREQUENCIES, true)) {
            throw new \InvalidArgumentException('La frecuencia de seguimiento no es válida.');
        }

        $this->db->execute(
            'UPDATE outsourcing_services SET followup_frequency = :frequency, updated_at = NOW() WHERE id = :id',
            [
                ':frequency' => $normalized,
                ':id' => $serviceId,
            ]
        );
    }

    public function followupsForService(int $serviceId): array
    {
        if (!$this->db->tableExists('outsourcing_followups')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT f.id, f.service_id, f.document_node_id, f.period_start, f.period_end, f.followup_frequency,
                    f.service_health, f.observations, f.responsible_user_id, f.followup_status, f.closed_at, f.created_by, f.created_at,
                    ru.name AS responsible_name, cu.name AS created_by_name
             FROM outsourcing_followups f
             LEFT JOIN users ru ON ru.id = f.responsible_user_id
             LEFT JOIN users cu ON cu.id = f.created_by
             WHERE f.service_id = :service_id
             ORDER BY f.period_start DESC, f.created_at DESC',
            [':service_id' => $serviceId]
        );
    }

    public function createFollowup(array $payload): int
    {
        if (!$this->db->tableExists('outsourcing_followups')) {
            throw new \RuntimeException('La tabla de seguimientos outsourcing no está disponible.');
        }

        $serviceHealth = strtolower(trim((string) ($payload['service_health'] ?? '')));
        if (!in_array($serviceHealth, self::HEALTH_STATUSES, true)) {
            throw new \InvalidArgumentException('El estado de salud del servicio no es válido.');
        }

        $observations = trim((string) ($payload['observations'] ?? ''));
        if ($observations === '') {
            throw new \InvalidArgumentException('Las observaciones son obligatorias.');
        }

        $periodStart = trim((string) ($payload['period_start'] ?? ''));
        $periodEnd = trim((string) ($payload['period_end'] ?? ''));
        if ($periodStart === '' || $periodEnd === '') {
            throw new \InvalidArgumentException('El periodo del seguimiento es obligatorio.');
        }
        if (strtotime($periodEnd) < strtotime($periodStart)) {
            throw new \InvalidArgumentException('El periodo del seguimiento es inválido.');
        }

        $frequency = strtolower(trim((string) ($payload['followup_frequency'] ?? 'monthly')));
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            throw new \InvalidArgumentException('La frecuencia de seguimiento no es válida.');
        }

        $followupStatus = strtolower(trim((string) ($payload['followup_status'] ?? 'open')));
        if (!in_array($followupStatus, ['open', 'observed'], true)) {
            throw new \InvalidArgumentException('El estado del seguimiento no es válido.');
        }

        return $this->db->insert(
            'INSERT INTO outsourcing_followups
                (service_id, document_node_id, period_start, period_end, followup_frequency, service_health, observations, responsible_user_id, followup_status, created_by, created_at)
             VALUES
                (:service_id, :document_node_id, :period_start, :period_end, :followup_frequency, :service_health, :observations, :responsible_user_id, :followup_status, :created_by, NOW())',
            [
                ':service_id' => (int) $payload['service_id'],
                ':document_node_id' => $payload['document_node_id'] ? (int) $payload['document_node_id'] : null,
                ':period_start' => $periodStart,
                ':period_end' => $periodEnd,
                ':followup_frequency' => $frequency,
                ':service_health' => $serviceHealth,
                ':observations' => $observations,
                ':responsible_user_id' => (int) ($payload['responsible_user_id'] ?? 0),
                ':followup_status' => $followupStatus,
                ':created_by' => (int) ($payload['created_by'] ?? 0),
            ]
        );
    }

    public function findFollowup(int $followupId, int $serviceId): ?array
    {
        if (!$this->db->tableExists('outsourcing_followups')) {
            return null;
        }

        $followup = $this->db->fetchOne(
            'SELECT id, service_id, followup_status, closed_at
             FROM outsourcing_followups
             WHERE id = :id AND service_id = :service_id
             LIMIT 1',
            [
                ':id' => $followupId,
                ':service_id' => $serviceId,
            ]
        );

        return $followup ?: null;
    }

    public function closeFollowup(int $followupId): void
    {
        if (!$this->db->tableExists('outsourcing_followups')) {
            throw new \RuntimeException('La tabla de seguimientos outsourcing no está disponible.');
        }

        $this->db->execute(
            'UPDATE outsourcing_followups
             SET followup_status = :status, closed_at = NOW()
             WHERE id = :id',
            [
                ':status' => 'closed',
                ':id' => $followupId,
            ]
        );
    }
}
