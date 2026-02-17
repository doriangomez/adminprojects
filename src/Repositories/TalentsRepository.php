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
            'COALESCE(pmo.active_projects, 0) AS active_projects',
            'COALESCE(pmo.total_assignments, 0) AS total_assignments',
            'COALESCE(pmo.pending_timesheets, 0) AS pending_timesheets',
            'COALESCE(pmo.reported_hours, 0) AS reported_hours',
            'COALESCE(pmo.outsourcing_services, 0) AS outsourcing_services',
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
             LEFT JOIN (
                SELECT
                    ta.id AS talent_id,
                    COALESCE(pa.active_projects, 0) AS active_projects,
                    COALESCE(pa.total_assignments, 0) AS total_assignments,
                    COALESCE(ts.pending_timesheets, 0) AS pending_timesheets,
                    COALESCE(ts.reported_hours, 0) AS reported_hours,
                    COALESCE(os.outsourcing_services, 0) AS outsourcing_services
                FROM talents ta
                LEFT JOIN (
                    SELECT
                        a.talent_id,
                        COUNT(DISTINCT CASE WHEN p.active = 1 THEN p.id END) AS active_projects,
                        COUNT(DISTINCT a.id) AS total_assignments
                    FROM project_talent_assignments a
                    LEFT JOIN projects p ON p.id = a.project_id
                    GROUP BY a.talent_id
                ) pa ON pa.talent_id = ta.id
                LEFT JOIN (
                    SELECT
                        tms.talent_id,
                        SUM(CASE WHEN tms.status IN (\'submitted\', \'pending\') THEN 1 ELSE 0 END) AS pending_timesheets,
                        SUM(COALESCE(tms.hours, 0)) AS reported_hours
                    FROM timesheets tms
                    GROUP BY tms.talent_id
                ) ts ON ts.talent_id = ta.id
                LEFT JOIN (
                    SELECT talent_id, COUNT(*) AS outsourcing_services
                    FROM outsourcing_services
                    GROUP BY talent_id
                ) os ON os.talent_id = ta.user_id
             ) pmo ON pmo.talent_id = t.id
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



    public function inactivateTalent(int $talentId): bool
    {
        $pdo = $this->db->connection();

        try {
            $pdo->beginTransaction();

            if ($this->db->tableExists('talents') && $this->db->columnExists('talents', 'active')) {
                $this->db->execute(
                    'UPDATE talents SET active = 0, updated_at = NOW() WHERE id = :id',
                    [':id' => $talentId]
                );
            }

            $talent = $this->find($talentId);
            $userId = (int) ($talent['user_id'] ?? 0);
            if ($userId > 0 && $this->db->tableExists('users') && $this->db->columnExists('users', 'active')) {
                $this->db->execute(
                    'UPDATE users SET active = 0, updated_at = NOW() WHERE id = :user_id',
                    [':user_id' => $userId]
                );
            }

            $pdo->commit();

            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return false;
        }
    }

    public function deleteTalentCascade(int $talentId): array
    {
        $pdo = $this->db->connection();
        $talent = $this->find($talentId);
        $userId = (int) ($talent['user_id'] ?? 0);
        $deleted = [
            'timesheets' => 0,
            'assignments' => 0,
            'skills' => 0,
            'outsourcing_services' => 0,
            'talent' => 0,
        ];

        try {
            $pdo->beginTransaction();

            if ($this->db->tableExists('timesheets') && $this->db->columnExists('timesheets', 'talent_id')) {
                $deleted['timesheets'] = $this->db->execute(
                    'DELETE FROM timesheets WHERE talent_id = :id',
                    [':id' => $talentId]
                ) ? $this->countAffectedRows() : 0;
            }

            if ($this->db->tableExists('project_talent_assignments') && $this->db->columnExists('project_talent_assignments', 'talent_id')) {
                $deleted['assignments'] = $this->db->execute(
                    'DELETE FROM project_talent_assignments WHERE talent_id = :id',
                    [':id' => $talentId]
                ) ? $this->countAffectedRows() : 0;
            }

            if ($this->db->tableExists('talent_skills') && $this->db->columnExists('talent_skills', 'talent_id')) {
                $deleted['skills'] = $this->db->execute(
                    'DELETE FROM talent_skills WHERE talent_id = :id',
                    [':id' => $talentId]
                ) ? $this->countAffectedRows() : 0;
            }

            if ($userId > 0 && $this->db->tableExists('outsourcing_services') && $this->db->columnExists('outsourcing_services', 'talent_id')) {
                $deleted['outsourcing_services'] = $this->db->execute(
                    'DELETE FROM outsourcing_services WHERE talent_id = :user_id',
                    [':user_id' => $userId]
                ) ? $this->countAffectedRows() : 0;
            }

            $deleted['talent'] = $this->db->execute(
                'DELETE FROM talents WHERE id = :id',
                [':id' => $talentId]
            ) ? $this->countAffectedRows() : 0;

            $pdo->commit();

            return [
                'success' => $deleted['talent'] > 0,
                'deleted' => $deleted,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'deleted' => $deleted,
            ];
        }
    }

    private function countAffectedRows(): int
    {
        $statement = $this->db->connection()->query('SELECT ROW_COUNT() AS affected_rows');
        $row = $statement ? $statement->fetch() : false;

        return (int) ($row['affected_rows'] ?? 0);
    }

    private function hasOutsourcingFlag(): bool
    {
        return $this->db->tableExists('talents') && $this->db->columnExists('talents', 'is_outsourcing');
    }
}
