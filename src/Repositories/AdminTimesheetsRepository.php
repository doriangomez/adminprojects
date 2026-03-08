<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class AdminTimesheetsRepository
{
    public function __construct(private Database $db)
    {
    }

    public function adminTimesheetEntries(array $filters = []): array
    {
        $conditions = [];
        $params = [];
        $joins = [
            'LEFT JOIN users u ON u.id = ts.user_id',
            'LEFT JOIN tasks tk ON tk.id = ts.task_id',
            'LEFT JOIN projects p ON p.id = ts.project_id',
            'LEFT JOIN clients c ON c.id = p.client_id',
        ];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'ts.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['project_id'])) {
            $conditions[] = 'ts.project_id = :project_id';
            $params[':project_id'] = (int) $filters['project_id'];
        }

        if (!empty($filters['client_id'])) {
            $conditions[] = 'p.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'ts.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['week_start']) && !empty($filters['week_end'])) {
            $conditions[] = 'ts.date BETWEEN :week_start AND :week_end';
            $params[':week_start'] = $filters['week_start'];
            $params[':week_end'] = $filters['week_end'];
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT
                ts.id,
                ts.date,
                ts.hours,
                ts.status,
                ts.comment,
                ts.activity_type,
                ts.activity_description,
                ts.project_id,
                ts.task_id,
                ts.user_id,
                ts.talent_id,
                u.name AS user_name,
                u.email AS user_email,
                tk.title AS task_title,
                p.name AS project_name,
                c.name AS client_name,
                c.id AS client_id
             FROM timesheets ts
             ' . implode(' ', $joins) . '
             ' . $whereClause . '
             ORDER BY ts.date DESC, u.name ASC, ts.id DESC
             LIMIT 2000',
            $params
        );
    }

    public function summaryByUser(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['project_id'])) {
            $conditions[] = 'ts.project_id = :project_id';
            $params[':project_id'] = (int) $filters['project_id'];
        }

        if (!empty($filters['client_id'])) {
            $conditions[] = 'p.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }

        if (!empty($filters['week_start']) && !empty($filters['week_end'])) {
            $conditions[] = 'ts.date BETWEEN :week_start AND :week_end';
            $params[':week_start'] = $filters['week_start'];
            $params[':week_end'] = $filters['week_end'];
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT
                u.id AS user_id,
                u.name AS user_name,
                COALESCE(SUM(ts.hours), 0) AS total_hours,
                COUNT(DISTINCT ts.project_id) AS projects_count,
                COUNT(*) AS entries_count
             FROM timesheets ts
             LEFT JOIN users u ON u.id = ts.user_id
             LEFT JOIN projects p ON p.id = ts.project_id
             ' . $whereClause . '
             GROUP BY u.id, u.name
             ORDER BY total_hours DESC',
            $params
        );
    }

    public function summaryByProject(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'ts.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['client_id'])) {
            $conditions[] = 'p.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }

        if (!empty($filters['week_start']) && !empty($filters['week_end'])) {
            $conditions[] = 'ts.date BETWEEN :week_start AND :week_end';
            $params[':week_start'] = $filters['week_start'];
            $params[':week_end'] = $filters['week_end'];
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT
                p.id AS project_id,
                p.name AS project_name,
                c.name AS client_name,
                p.planned_hours,
                COALESCE(SUM(ts.hours), 0) AS total_hours,
                COUNT(DISTINCT ts.user_id) AS users_count,
                COUNT(*) AS entries_count
             FROM timesheets ts
             LEFT JOIN projects p ON p.id = ts.project_id
             LEFT JOIN clients c ON c.id = p.client_id
             ' . $whereClause . '
             GROUP BY p.id, p.name, c.name, p.planned_hours
             ORDER BY total_hours DESC',
            $params
        );
    }

    public function summaryByClient(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'ts.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['week_start']) && !empty($filters['week_end'])) {
            $conditions[] = 'ts.date BETWEEN :week_start AND :week_end';
            $params[':week_start'] = $filters['week_start'];
            $params[':week_end'] = $filters['week_end'];
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT
                c.id AS client_id,
                c.name AS client_name,
                COALESCE(SUM(ts.hours), 0) AS total_hours,
                COUNT(DISTINCT p.id) AS projects_count,
                COUNT(DISTINCT ts.user_id) AS users_count,
                COUNT(*) AS entries_count
             FROM timesheets ts
             LEFT JOIN projects p ON p.id = ts.project_id
             LEFT JOIN clients c ON c.id = p.client_id
             ' . $whereClause . '
             GROUP BY c.id, c.name
             ORDER BY total_hours DESC',
            $params
        );
    }

    public function allUsers(): array
    {
        return $this->db->fetchAll(
            'SELECT DISTINCT u.id, u.name
             FROM users u
             JOIN timesheets ts ON ts.user_id = u.id
             ORDER BY u.name ASC'
        );
    }

    public function allProjects(): array
    {
        return $this->db->fetchAll(
            'SELECT DISTINCT p.id, p.name
             FROM projects p
             JOIN timesheets ts ON ts.project_id = p.id
             ORDER BY p.name ASC'
        );
    }

    public function allClients(): array
    {
        return $this->db->fetchAll(
            'SELECT DISTINCT c.id, c.name
             FROM clients c
             JOIN projects p ON p.client_id = c.id
             JOIN timesheets ts ON ts.project_id = p.id
             ORDER BY c.name ASC'
        );
    }

    public function globalStats(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'ts.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['project_id'])) {
            $conditions[] = 'ts.project_id = :project_id';
            $params[':project_id'] = (int) $filters['project_id'];
        }

        if (!empty($filters['client_id'])) {
            $conditions[] = 'p.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'ts.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['week_start']) && !empty($filters['week_end'])) {
            $conditions[] = 'ts.date BETWEEN :week_start AND :week_end';
            $params[':week_start'] = $filters['week_start'];
            $params[':week_end'] = $filters['week_end'];
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $row = $this->db->fetchOne(
            'SELECT
                COALESCE(SUM(ts.hours), 0) AS total_hours,
                COUNT(*) AS total_entries,
                COUNT(DISTINCT ts.user_id) AS total_users,
                COUNT(DISTINCT ts.project_id) AS total_projects,
                SUM(CASE WHEN ts.status = "approved" THEN ts.hours ELSE 0 END) AS approved_hours,
                SUM(CASE WHEN ts.status IN ("pending", "submitted", "pending_approval") THEN ts.hours ELSE 0 END) AS pending_hours,
                SUM(CASE WHEN ts.status = "draft" THEN ts.hours ELSE 0 END) AS draft_hours
             FROM timesheets ts
             LEFT JOIN projects p ON p.id = ts.project_id
             ' . $whereClause,
            $params
        ) ?? [];

        return [
            'total_hours' => round((float) ($row['total_hours'] ?? 0), 2),
            'total_entries' => (int) ($row['total_entries'] ?? 0),
            'total_users' => (int) ($row['total_users'] ?? 0),
            'total_projects' => (int) ($row['total_projects'] ?? 0),
            'approved_hours' => round((float) ($row['approved_hours'] ?? 0), 2),
            'pending_hours' => round((float) ($row['pending_hours'] ?? 0), 2),
            'draft_hours' => round((float) ($row['draft_hours'] ?? 0), 2),
        ];
    }
}
