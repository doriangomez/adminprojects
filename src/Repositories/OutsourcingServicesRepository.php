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

    public function listServices(array $user): array
    {
        if (!$this->db->tableExists('outsourcing_services')) {
            return [];
        }

        $services = $this->db->fetchAll(
            'SELECT s.id, s.talent_id, s.client_id, s.project_id, s.start_date, s.end_date, s.followup_frequency,
                    s.service_status, s.created_at, s.updated_at,
                    u.name AS talent_name, u.email AS talent_email,
                    c.name AS client_name,
                    p.name AS project_name
             FROM outsourcing_services s
             JOIN users u ON u.id = s.talent_id
             JOIN clients c ON c.id = s.client_id
             LEFT JOIN projects p ON p.id = s.project_id
             ORDER BY s.created_at DESC'
        );

        if (!$this->db->tableExists('outsourcing_followups')) {
            return $services;
        }

        $healthRows = $this->db->fetchAll(
            'SELECT f.service_id, f.service_health, f.period_end, f.created_at
             FROM outsourcing_followups f
             JOIN (
                SELECT service_id, MAX(created_at) AS max_created
                FROM outsourcing_followups
                GROUP BY service_id
             ) latest ON latest.service_id = f.service_id AND latest.max_created = f.created_at'
        );

        $healthMap = [];
        foreach ($healthRows as $row) {
            $healthMap[(int) $row['service_id']] = [
                'service_health' => $row['service_health'] ?? null,
                'period_end' => $row['period_end'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        foreach ($services as &$service) {
            $health = $healthMap[(int) $service['id']] ?? [];
            $service['current_health'] = $health['service_health'] ?? null;
            $service['health_updated_at'] = $health['created_at'] ?? null;
        }
        unset($service);

        return $services;
    }

    public function findService(int $serviceId): ?array
    {
        if (!$this->db->tableExists('outsourcing_services')) {
            return null;
        }

        $service = $this->db->fetchOne(
            'SELECT s.id, s.talent_id, s.client_id, s.project_id, s.start_date, s.end_date, s.followup_frequency,
                    s.service_status, s.created_at, s.updated_at,
                    u.name AS talent_name, u.email AS talent_email,
                    c.name AS client_name,
                    p.name AS project_name
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

        return $this->db->insert(
            'INSERT INTO outsourcing_services
                (talent_id, client_id, project_id, start_date, end_date, followup_frequency, service_status, created_by, created_at, updated_at)
             VALUES
                (:talent_id, :client_id, :project_id, :start_date, :end_date, :followup_frequency, :service_status, :created_by, NOW(), NOW())',
            [
                ':talent_id' => (int) $payload['talent_id'],
                ':client_id' => (int) $payload['client_id'],
                ':project_id' => $payload['project_id'] ? (int) $payload['project_id'] : null,
                ':start_date' => $startDate,
                ':end_date' => $endDate !== '' ? $endDate : null,
                ':followup_frequency' => $frequency,
                ':service_status' => $status,
                ':created_by' => (int) ($payload['created_by'] ?? 0),
            ]
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
                    f.service_health, f.observations, f.responsible_user_id, f.created_by, f.created_at,
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

        return $this->db->insert(
            'INSERT INTO outsourcing_followups
                (service_id, document_node_id, period_start, period_end, followup_frequency, service_health, observations, responsible_user_id, created_by, created_at)
             VALUES
                (:service_id, :document_node_id, :period_start, :period_end, :followup_frequency, :service_health, :observations, :responsible_user_id, :created_by, NOW())',
            [
                ':service_id' => (int) $payload['service_id'],
                ':document_node_id' => $payload['document_node_id'] ? (int) $payload['document_node_id'] : null,
                ':period_start' => $periodStart,
                ':period_end' => $periodEnd,
                ':followup_frequency' => $frequency,
                ':service_health' => $serviceHealth,
                ':observations' => $observations,
                ':responsible_user_id' => (int) ($payload['responsible_user_id'] ?? 0),
                ':created_by' => (int) ($payload['created_by'] ?? 0),
            ]
        );
    }
}
