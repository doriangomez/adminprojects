<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class TalentAbsencesRepository
{
    public const TYPES = [
        'vacaciones',
        'incapacidad',
        'permiso_personal',
        'permiso_medico',
        'capacitacion',
        'licencia',
        'festivo',
    ];

    public const STATUSES = ['pendiente', 'aprobado', 'rechazado'];

    public function __construct(private Database $db)
    {
    }

    public function create(
        int $talentId,
        int $userId,
        string $type,
        string $startDate,
        string $endDate,
        ?float $hours = null,
        bool $isFullDay = true,
        ?string $comment = null,
        ?int $createdBy = null
    ): array {
        if (!$this->db->tableExists('talent_absences')) {
            throw new \RuntimeException('Tabla talent_absences no existe.');
        }

        $type = strtolower(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Tipo de ausencia no válido: ' . $type);
        }

        $this->db->execute(
            'INSERT INTO talent_absences (talent_id, user_id, type, start_date, end_date, hours, is_full_day, status, comment, created_by)
             VALUES (:talent_id, :user_id, :type, :start_date, :end_date, :hours, :is_full_day, :status, :comment, :created_by)',
            [
                ':talent_id' => $talentId,
                ':user_id' => $userId,
                ':type' => $type,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':hours' => $hours,
                ':is_full_day' => $isFullDay ? 1 : 0,
                ':status' => 'pendiente',
                ':comment' => $comment,
                ':created_by' => $createdBy,
            ]
        );

        $id = (int) $this->db->lastInsertId();
        return $this->find($id);
    }

    public function find(int $id): ?array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return null;
        }

        $row = $this->db->fetchOne('SELECT * FROM talent_absences WHERE id = :id', [':id' => $id]);
        return $row ?: null;
    }

    public function approve(int $id): bool
    {
        if (!$this->db->tableExists('talent_absences')) {
            return false;
        }

        $this->db->execute(
            'UPDATE talent_absences SET status = :status, updated_at = NOW() WHERE id = :id',
            [':status' => 'aprobado', ':id' => $id]
        );
        return true;
    }

    public function listByTalent(int $talentId, ?string $from = null, ?string $to = null): array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return [];
        }

        $sql = 'SELECT * FROM talent_absences WHERE talent_id = :talent_id';
        $params = [':talent_id' => $talentId];

        if ($from !== null) {
            $sql .= ' AND end_date >= :from';
            $params[':from'] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND start_date <= :to';
            $params[':to'] = $to;
        }

        $sql .= ' ORDER BY start_date DESC';
        return $this->db->fetchAll($sql, $params);
    }

    public function listByUser(int $userId, ?string $from = null, ?string $to = null): array
    {
        if (!$this->db->tableExists('talent_absences')) {
            return [];
        }

        $sql = 'SELECT * FROM talent_absences WHERE user_id = :user_id';
        $params = [':user_id' => $userId];

        if ($from !== null) {
            $sql .= ' AND end_date >= :from';
            $params[':from'] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND start_date <= :to';
            $params[':to'] = $to;
        }

        $sql .= ' ORDER BY start_date DESC';
        return $this->db->fetchAll($sql, $params);
    }
}
