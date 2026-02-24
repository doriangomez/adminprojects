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
        $weekStart = $this->parseWeekValue($weekValue) ?? new DateTimeImmutable('monday this week');
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
            'weeklyGrid' => $canReport ? $repo->weeklyGridForUser($userId, $weekStart) : ['days' => [], 'rows' => [], 'day_totals' => [], 'week_total' => 0, 'weekly_capacity' => 0],
            'canApprove' => $canApprove,
            'canReport' => $canReport,
            'timesheetsEnabled' => $timesheetsEnabled,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekValue' => $weekValue,
        ]);
    }

    public function saveCell(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        $projectId = (int) ($_POST['project_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $hours = max(0, (float) ($_POST['hours'] ?? 0));
        $comment = trim((string) ($_POST['comment'] ?? ''));

        if ($projectId <= 0 || $date === '') {
            http_response_code(400);
            exit('Proyecto y fecha son requeridos.');
        }

        try {
            $repo->upsertDraftCell($userId, $projectId, $date, $hours, $comment);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('Error autoguardando celda de timesheet: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'No se pudo guardar la celda.']);
        }
    }

    public function submitWeek(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        $weekValue = trim((string) ($_POST['week'] ?? ''));
        $weekStart = $this->parseWeekValue($weekValue);
        if (!$weekStart instanceof DateTimeImmutable) {
            http_response_code(400);
            exit('Semana inválida.');
        }

        try {
            $repo->submitWeek($userId, $weekStart);
            header('Location: /timesheets?week=' . urlencode($weekStart->format('o-\\WW')));
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function create(): void
    {
        // Mantener compatibilidad con formulario anterior.
        $this->saveCell();
    }

    public function approveWeek(): void
    {
        if (!$this->auth->canApproveTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? 'approved'));
        $weekStart = trim((string) ($_POST['week_start'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));

        if (!in_array($status, ['approved', 'rejected'], true)) {
            http_response_code(400);
            exit('Estado inválido.');
        }
        if ($status === 'rejected' && $comment === '') {
            http_response_code(400);
            exit('Debes indicar un comentario para rechazar la semana.');
        }

        try {
            $repo->updateWeekApprovalStatus($userId, $weekStart, $status, $comment !== '' ? $comment : null);
            header('Location: /approvals');
        } catch (\Throwable $e) {
            error_log('Error al aprobar semana de timesheets: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo actualizar la aprobación semanal.');
        }
    }



    private function parseWeekValue(string $weekValue): ?DateTimeImmutable
    {
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $weekValue, $matches)) {
            return null;
        }

        $year = (int) $matches[1];
        $week = (int) $matches[2];
        if ($week < 1 || $week > 53) {
            return null;
        }

        return (new DateTimeImmutable('now'))->setISODate($year, $week, 1)->setTime(0, 0);
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
