<?php

declare(strict_types=1);

class TimesheetsController extends Controller
{
    public function index(): void
    {
        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $isTalent = $this->auth->isTalentUser();
        $canReport = $isTalent && $this->auth->canAccessTimesheets();
        $canApprove = $this->auth->canApproveTimesheets();

        if ($isTalent && !$canReport) {
            http_response_code(403);
            exit('Tu perfil no requiere reporte de horas.');
        }

        if (!$isTalent && !$canApprove) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $this->render('timesheets/index', [
            'title' => 'Timesheets',
            'rows' => $repo->weekly($user),
            'kpis' => $repo->kpis($user),
            'tasksForTimesheet' => $canReport ? $repo->tasksForTimesheetEntry($userId) : [],
            'pendingApprovals' => $canApprove ? $repo->pendingApprovals($user) : [],
            'canApprove' => $canApprove,
            'canReport' => $canReport,
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

        $taskId = (int) ($_POST['task_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $hours = (float) ($_POST['hours'] ?? 0);
        $billable = isset($_POST['billable']) ? 1 : 0;

        if ($taskId <= 0 || $date === '' || $hours <= 0) {
            http_response_code(400);
            exit('Completa los datos requeridos para registrar horas.');
        }

        $assignment = $repo->assignmentForTask($taskId, $userId);
        if (!$assignment || (int) ($assignment['requiere_reporte_horas'] ?? 0) !== 1) {
            http_response_code(403);
            exit('No tienes una asignación habilitada para reportar horas en esta tarea.');
        }
        if (($assignment['task_status'] ?? '') !== 'in_progress') {
            http_response_code(400);
            exit('Solo puedes registrar horas en tareas en estado Doing (En curso).');
        }

        $talentId = (int) ($assignment['talent_id'] ?? 0);
        if ($talentId <= 0) {
            http_response_code(400);
            exit('Tu usuario no tiene un talento asociado.');
        }

        $requiresApproval = (int) ($assignment['requiere_aprobacion_horas'] ?? 0) === 1;
        $status = $requiresApproval ? 'pending_approval' : 'approved';
        $approvedBy = $requiresApproval ? null : $userId;
        $approvedAt = $requiresApproval ? null : date('Y-m-d H:i:s');

        try {
            $timesheetId = $repo->createTimesheet([
                'task_id' => $taskId,
                'talent_id' => $talentId,
                'assignment_id' => null,
                'date' => $date,
                'hours' => $hours,
                'status' => $status,
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
                    'hours' => $hours,
                    'date' => $date,
                ]
            );

            header('Location: /project/public/timesheets');
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

        try {
            $repo->updateApprovalStatus($timesheetId, $status, $userId);

            (new AuditLogRepository($this->db))->log(
                $userId,
                'timesheet',
                $timesheetId,
                $status,
                [
                    'approved_by' => $status === 'approved' ? $userId : null,
                    'rejected_by' => $status === 'rejected' ? $userId : null,
                ]
            );

            header('Location: /project/public/timesheets');
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
