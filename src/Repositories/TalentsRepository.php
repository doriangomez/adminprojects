<?php

declare(strict_types=1);

class TalentsRepository
{
    private const DEFAULT_OUTSOURCING_FLAG = 0;

    public function __construct(private Database $db)
    {
    }

    public function summary(): array
    {
        $select = [
            't.id',
            't.user_id',
            't.name',
            't.role',
            't.seniority',
            't.capacidad_horaria',
            't.hourly_cost',
            't.hourly_rate',
            't.availability',
            't.requiere_reporte_horas',
            't.requiere_aprobacion_horas',
            't.tipo_talento',
        ];

        if ($this->hasOutsourcingFlag()) {
            $select[] = 't.is_outsourcing';
        } else {
            $select[] = sprintf('%d AS is_outsourcing', self::DEFAULT_OUTSOURCING_FLAG);
        }

        return $this->db->fetchAll(
            'SELECT ' . implode(', ', $select) . ', u.email AS user_email, u.active AS user_active, GROUP_CONCAT(s.name) AS skills
             FROM talents t
             LEFT JOIN users u ON u.id = t.user_id
             LEFT JOIN talent_skills ts ON ts.talent_id = t.id
             LEFT JOIN skills s ON s.id = ts.skill_id
             GROUP BY t.id
             ORDER BY t.name'
        );
    }

    public function find(int $talentId): ?array
    {
        $select = [
            't.id',
            't.user_id',
            't.name',
            't.role',
            't.seniority',
            't.capacidad_horaria',
            't.hourly_cost',
            't.hourly_rate',
            't.availability',
            't.requiere_reporte_horas',
            't.requiere_aprobacion_horas',
            't.tipo_talento',
            'u.email AS user_email',
        ];

        if ($this->hasOutsourcingFlag()) {
            $select[] = 't.is_outsourcing';
        } else {
            $select[] = sprintf('%d AS is_outsourcing', self::DEFAULT_OUTSOURCING_FLAG);
        }

        $talent = $this->db->fetchOne(
            'SELECT ' . implode(', ', $select) . '
             FROM talents t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.id = :id
             LIMIT 1',
            [':id' => $talentId]
        );

        return $talent ?: null;
    }

    public function create(array $payload): int
    {
        $columns = [
            'user_id',
            'name',
            'role',
            'seniority',
            'capacidad_horaria',
            'availability',
            'requiere_reporte_horas',
            'requiere_aprobacion_horas',
            'tipo_talento',
            'hourly_cost',
            'hourly_rate',
        ];
        $params = [
            ':user_id' => $payload['user_id'] ?? null,
            ':name' => $payload['name'],
            ':role' => $payload['role'],
            ':seniority' => $payload['seniority'] !== '' ? $payload['seniority'] : null,
            ':capacidad_horaria' => $payload['capacidad_horaria'] ?? 0,
            ':availability' => $payload['availability'] ?? 0,
            ':requiere_reporte_horas' => $payload['requiere_reporte_horas'] ?? 0,
            ':requiere_aprobacion_horas' => $payload['requiere_aprobacion_horas'] ?? 0,
            ':tipo_talento' => $payload['tipo_talento'] ?? 'interno',
            ':hourly_cost' => $payload['hourly_cost'] ?? 0,
            ':hourly_rate' => $payload['hourly_rate'] ?? 0,
        ];

        if ($this->hasOutsourcingFlag()) {
            $columns[] = 'is_outsourcing';
            $params[':is_outsourcing'] = $payload['is_outsourcing'] ?? 0;
        }

        return $this->db->insert(
            'INSERT INTO talents (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', array_keys($params)) . ')',
            $params
        );
    }

    public function update(int $talentId, array $payload): void
    {
        $setParts = [
            'user_id = :user_id',
            'name = :name',
            'role = :role',
            'seniority = :seniority',
            'capacidad_horaria = :capacidad_horaria',
            'availability = :availability',
            'requiere_reporte_horas = :requiere_reporte_horas',
            'requiere_aprobacion_horas = :requiere_aprobacion_horas',
            'tipo_talento = :tipo_talento',
            'hourly_cost = :hourly_cost',
            'hourly_rate = :hourly_rate',
        ];
        $params = [
            ':user_id' => $payload['user_id'] ?? null,
            ':name' => $payload['name'],
            ':role' => $payload['role'],
            ':seniority' => $payload['seniority'] !== '' ? $payload['seniority'] : null,
            ':capacidad_horaria' => $payload['capacidad_horaria'] ?? 0,
            ':availability' => $payload['availability'] ?? 0,
            ':requiere_reporte_horas' => $payload['requiere_reporte_horas'] ?? 0,
            ':requiere_aprobacion_horas' => $payload['requiere_aprobacion_horas'] ?? 0,
            ':tipo_talento' => $payload['tipo_talento'] ?? 'interno',
            ':hourly_cost' => $payload['hourly_cost'] ?? 0,
            ':hourly_rate' => $payload['hourly_rate'] ?? 0,
            ':id' => $talentId,
        ];

        if ($this->hasOutsourcingFlag()) {
            $setParts[] = 'is_outsourcing = :is_outsourcing';
            $params[':is_outsourcing'] = $payload['is_outsourcing'] ?? 0;
        }

        $this->db->execute(
            'UPDATE talents SET ' . implode(', ', $setParts) . ', updated_at = NOW() WHERE id = :id',
            $params
        );
    }

    private function hasOutsourcingFlag(): bool
    {
        return $this->db->tableExists('talents') && $this->db->columnExists('talents', 'is_outsourcing');
    }
}
