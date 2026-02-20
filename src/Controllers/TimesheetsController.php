<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\TimesheetsRepository;

class TimesheetsController extends Controller
{
    public function index(): void
    {
        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $canReport = $this->auth->canAccessTimesheets();
        $canApprove = $this->auth->canApproveTimesheets();
        $timesheetsEnabled = $this->auth->isTimesheetsEnabled();
        $weekValue = trim((string) ($_GET['week'] ?? ''));
        $weekStart = new DateTimeImmutable('monday this week');
        if ($weekValue !== '') {
            $parsedWeek = DateTimeImmutable::createFromFormat('o-\\WW', $weekValue);
            if ($parsedWeek instanceof DateTimeImmutable) {
                $weekStart = $parsedWeek->modify('monday this week');
            }
        }
        $weekEnd = $weekStart->modify('+6 days');
        $weekValue = $weekStart->format('o-\\WW');

        if (!$timesheetsEnabled) {
            http_response_code(404);
            exit('El módulo de timesheets no está habilitado.');
        }

        $this->render('timesheets/index', [
            'title' => 'Timesheets',
            'rows' => $repo->weekly($user),
            'kpis' => $repo->kpis($user),
            'projectsForTimesheet' => $canReport ? $repo->projectsForTimesheetEntry($userId) : [],
            'pendingApprovals' => $canApprove ? $repo->pendingApprovals($user) : [],
            'canApprove' => $canApprove,
            'canReport' => $canReport,
            'timesheetsEnabled' => $timesheetsEnabled,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekValue' => $weekValue,
        ]);
    }

    public function create(): void
    {
        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        if (!$this->auth->canAccessTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $projectId = (int) ($_POST['project_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $hours = (float) ($_POST['hours'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $billable = isset($_POST['billable']) ? 1 : 0;

        if ($projectId <= 0 || $date === '' || $hours <= 0 || $comment === '') {
            http_response_code(400);
            exit('Completa los datos requeridos para registrar horas.');
        }

        $assignment = $repo->assignmentForProject($projectId, $userId);
        if (!$assignment) {
            http_response_code(403);
            exit('No tienes una asignación habilitada para reportar horas en este proyecto.');
        }

        $talentId = (int) ($assignment['talent_id'] ?? 0);
        if ($talentId <= 0) {
            http_response_code(400);
            exit('Tu usuario no tiene un talento asociado.');
        }

        $requiresApproval = (int) ($assignment['requires_timesheet_approval'] ?? 0) === 1;
        $status = $requiresApproval ? 'pending_approval' : 'approved';
        $approvedBy = $requiresApproval ? null : $userId;
        $approvedAt = $requiresApproval ? null : date('Y-m-d H:i:s');

        try {
            $taskId = $repo->resolveTimesheetTaskId($projectId);
            $timesheetId = $repo->createTimesheet([
                'task_id' => $taskId,
                'project_id' => $projectId,
                'talent_id' => $talentId,
                'user_id' => $userId,
                'assignment_id' => $assignment['id'] ?? null,
                'date' => $date,
                'hours' => $hours,
                'status' => $status,
                'comment' => $comment,
                'billable' => $billable,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
            ]);

            (new AuditLogRepository($this->db))->log(
                $userId,
                'timesheet',
                $timesheetId,
                $requiresApproval ? 'submitted' : 'auto_approved',
                [
                    'task_id' => $taskId,
                    'project_id' => $projectId,
                    'hours' => $hours,
                    'date' => $date,
                    'comment' => $comment,
                ]
            );

            try {
                (new NotificationService($this->db))->notify(
                    'timesheet.submitted',
                    [
                        'timesheet_id' => $timesheetId,
                        'task_id' => $taskId,
                        'project_id' => $projectId,
                        'hours' => $hours,
                        'date' => $date,
                        'status' => $status,
                        'target_user_id' => $userId,
                    ],
                    $userId
                );
            } catch (\Throwable $e) {
                error_log('Error al notificar timesheet: ' . $e->getMessage());
            }

            header('Location: /timesheets');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al registrar horas: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo registrar el timesheet.');
        }
    }

    public function updateStatus(int $timesheetId, string $action): void
    {
        if (!$this->auth->canApproveTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        $status = $action === 'approve' ? 'approved' : 'rejected';
        $comment = trim((string) ($_POST['comment'] ?? ''));

        if ($status === 'rejected' && $comment === '') {
            http_response_code(400);
            exit('Debes indicar un comentario para rechazar horas.');
        }

        try {
            $repo->updateApprovalStatus($timesheetId, $status, $userId, $comment !== '' ? $comment : null);

            (new AuditLogRepository($this->db))->log(
                $userId,
                'timesheet',
                $timesheetId,
                $status,
                [
                    'approved_by' => $status === 'approved' ? $userId : null,
                    'rejected_by' => $status === 'rejected' ? $userId : null,
                    'comment' => $comment !== '' ? $comment : null,
                ]
            );

            try {
                (new NotificationService($this->db))->notify(
                    $status === 'approved' ? 'timesheet.approved' : 'timesheet.rejected',
                    [
                        'timesheet_id' => $timesheetId,
                        'status' => $status,
                        'comment' => $comment !== '' ? $comment : null,
                        'target_user_id' => $repo->findOwnerId($timesheetId),
                    ],
                    $userId
                );
            } catch (\Throwable $e) {
                error_log('Error al notificar aprobación de timesheet: ' . $e->getMessage());
            }

            header('Location: /timesheets');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error al actualizar estado de timesheet: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo actualizar el estado del timesheet.');
        }
    }
}
