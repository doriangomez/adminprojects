<?php

declare(strict_types=1);

final class MonthlyTaskAutomationService
{
    public function __construct(private Database $db)
    {
    }

    public function settings(int $projectId): array
    {
        return $this->db->fetchOne(
            'SELECT enabled, generation_day FROM project_monthly_task_settings WHERE project_id = :project',
            [':project' => $projectId]
        ) ?? ['enabled' => 0, 'generation_day' => 1];
    }

    public function templates(int $projectId): array
    {
        return $this->db->fetchAll(
            'SELECT mt.*, ta.name AS assignee_name
             FROM project_monthly_task_templates mt
             LEFT JOIN talents ta ON ta.id = mt.assignee_id
             WHERE mt.project_id = :project AND mt.active = 1
             ORDER BY mt.id ASC',
            [':project' => $projectId]
        );
    }

    public function saveSettings(int $projectId, bool $enabled, int $generationDay): void
    {
        if ($generationDay < 1 || $generationDay > 28) {
            throw new InvalidArgumentException('El día de generación debe estar entre 1 y 28.');
        }
        $this->db->execute(
            'INSERT INTO project_monthly_task_settings (project_id, enabled, generation_day)
             VALUES (:project, :enabled, :day)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), generation_day = VALUES(generation_day)',
            [':project' => $projectId, ':enabled' => $enabled ? 1 : 0, ':day' => $generationDay]
        );
    }

    public function addTemplate(int $projectId, array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        $priority = strtolower((string) ($data['priority'] ?? 'medium'));
        $hours = (float) ($data['estimated_hours'] ?? 0);
        $dueDay = (int) ($data['due_day'] ?? 28);
        if ($title === '') {
            throw new InvalidArgumentException('El título de la tarea recurrente es obligatorio.');
        }
        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            throw new InvalidArgumentException('La prioridad indicada no es válida.');
        }
        if ($hours < 0 || $dueDay < 1 || $dueDay > 28) {
            throw new InvalidArgumentException('Las horas deben ser positivas y el vencimiento debe estar entre los días 1 y 28.');
        }

        return $this->db->insert(
            'INSERT INTO project_monthly_task_templates
                (project_id, title, description, assignee_id, estimated_hours, priority, due_day)
             VALUES (:project, :title, :description, :assignee, :hours, :priority, :due_day)',
            [
                ':project' => $projectId,
                ':title' => $title,
                ':description' => trim((string) ($data['description'] ?? '')) ?: null,
                ':assignee' => (int) ($data['assignee_id'] ?? 0) ?: null,
                ':hours' => $hours,
                ':priority' => $priority,
                ':due_day' => $dueDay,
            ]
        );
    }

    public function deleteTemplate(int $projectId, int $templateId): void
    {
        $this->db->execute(
            'UPDATE project_monthly_task_templates SET active = 0 WHERE id = :id AND project_id = :project',
            [':id' => $templateId, ':project' => $projectId]
        );
    }

    public function generateDueTasks(?DateTimeImmutable $today = null): int
    {
        $today ??= new DateTimeImmutable('today');
        $projects = $this->db->fetchAll(
            'SELECT s.project_id
             FROM project_monthly_task_settings s
             JOIN projects p ON p.id = s.project_id
             WHERE s.enabled = 1 AND s.generation_day <= :day
               AND p.methodology = \'scrum\' AND p.active = 1
               AND LOWER(p.status) NOT IN (\'closed\', \'cerrado\', \'finalizado\', \'finalized\')',
            [':day' => (int) $today->format('j')]
        );
        $created = 0;
        foreach ($projects as $project) {
            $created += $this->generateForProject((int) $project['project_id'], $today, false);
        }
        return $created;
    }

    public function generateForProject(int $projectId, ?DateTimeImmutable $today = null, bool $force = true): int
    {
        $today ??= new DateTimeImmutable('today');
        $project = $this->db->fetchOne(
            'SELECT p.methodology, p.status, p.active, s.enabled, s.generation_day
             FROM projects p LEFT JOIN project_monthly_task_settings s ON s.project_id = p.id
             WHERE p.id = :project',
            [':project' => $projectId]
        );
        if (!$project || strtolower((string) $project['methodology']) !== 'scrum') {
            throw new InvalidArgumentException('La automatización mensual solo está disponible para proyectos Scrum.');
        }
        if (!(bool) ($project['active'] ?? false) || in_array(strtolower((string) ($project['status'] ?? '')), ['closed', 'cerrado', 'finalizado', 'finalized'], true)) {
            throw new InvalidArgumentException('No se pueden generar tareas para un proyecto cerrado o inactivo.');
        }
        if (!$force && (!(bool) ($project['enabled'] ?? false) || (int) $today->format('j') < (int) ($project['generation_day'] ?? 1))) {
            return 0;
        }

        $period = $today->format('Y-m-01');
        $templates = $this->templates($projectId);
        $pdo = $this->db->connection();
        $created = 0;
        foreach ($templates as $template) {
            $pdo->beginTransaction();
            try {
                $inserted = $this->db->execute(
                    'INSERT IGNORE INTO project_monthly_task_runs (template_id, project_id, period_month)
                     VALUES (:template, :project, :period)',
                    [':template' => $template['id'], ':project' => $projectId, ':period' => $period]
                );
                $run = $this->db->fetchOne(
                    'SELECT id, task_id FROM project_monthly_task_runs WHERE template_id = :template AND period_month = :period FOR UPDATE',
                    [':template' => $template['id'], ':period' => $period]
                );
                if (!$inserted || !$run || (int) ($run['task_id'] ?? 0) > 0) {
                    $pdo->commit();
                    continue;
                }
                $dueDate = $today->setDate((int) $today->format('Y'), (int) $today->format('m'), (int) $template['due_day'])->format('Y-m-d');
                $taskId = $this->db->insert(
                    'INSERT INTO tasks (project_id, assignee_id, title, description, status, priority, estimated_hours, actual_hours, due_date, created_at, updated_at)
                     VALUES (:project, :assignee, :title, :description, \'todo\', :priority, :hours, 0, :due, NOW(), NOW())',
                    [
                        ':project' => $projectId,
                        ':assignee' => $template['assignee_id'] ?: null,
                        ':title' => $template['title'],
                        ':description' => $template['description'],
                        ':priority' => $template['priority'],
                        ':hours' => $template['estimated_hours'],
                        ':due' => $dueDate,
                    ]
                );
                $this->db->execute('UPDATE project_monthly_task_runs SET task_id = :task WHERE id = :id', [':task' => $taskId, ':id' => $run['id']]);
                $pdo->commit();
                $created++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
        return $created;
    }
}
