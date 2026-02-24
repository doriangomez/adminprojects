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
        $canManageWorkflow = $this->auth->canManageTimesheetWorkflow();
        $canDeleteWeek = $this->auth->canDeleteTimesheetWorkflowRecords();
        $canManageAdvanced = $this->auth->canManageAdvancedTimesheets();
        $timesheetsEnabled = $this->auth->isTimesheetsEnabled();
        $weekValue = trim((string) ($_GET['week'] ?? ''));
        $weekStart = $this->parseWeekValue($weekValue) ?? new DateTimeImmutable('monday this week');
        $weekEnd = $weekStart->modify('+6 days');
        $weekValue = $weekStart->format('o-\\WW');

        $periodType = trim((string) ($_GET['period'] ?? 'month'));
        if (!in_array($periodType, ['month', 'custom'], true)) {
            $periodType = 'month';
        }
        $rangeStartRaw = trim((string) ($_GET['range_start'] ?? ''));
        $rangeEndRaw = trim((string) ($_GET['range_end'] ?? ''));
        $periodStart = $periodType === 'custom' ? $this->parseDateValue($rangeStartRaw) : null;
        $periodEnd = $periodType === 'custom' ? $this->parseDateValue($rangeEndRaw) : null;
        if (!$periodStart || !$periodEnd || $periodStart > $periodEnd) {
            $periodType = 'month';
            $periodStart = $weekStart->modify('first day of this month')->setTime(0, 0);
            $periodEnd = $weekStart->modify('last day of this month')->setTime(0, 0);
        }

        $projectFilter = (int) ($_GET['project_id'] ?? 0);
        $talentSort = trim((string) ($_GET['talent_sort'] ?? 'load_desc'));
        if (!in_array($talentSort, ['load_desc', 'compliance_asc'], true)) {
            $talentSort = 'load_desc';
        }

        if (!$timesheetsEnabled) {
            http_response_code(404);
            exit('El módulo de timesheets no está habilitado.');
        }

        $executiveSummary = $repo->executiveSummary($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null);
        $approvedWeeks = $repo->approvedWeeksByPeriod($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null);
        $talentBreakdown = $repo->talentBreakdownByPeriod($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null, $talentSort);
        $projectBreakdown = $repo->projectBreakdownByPeriod($user, $periodStart, $periodEnd);
        $projectsForFilter = $repo->projectsCatalog();
        $talentFilter = (int) ($_GET['talent_id'] ?? 0);
        $managedWeekEntries = ($canApprove || $canManageAdvanced) ? $repo->managedWeekEntries($weekStart, $talentFilter > 0 ? $talentFilter : null) : [];
        $talentOptions = $this->db->fetchAll('SELECT id, name FROM talents ORDER BY name ASC');

        $this->render('timesheets/index', [
            'title' => 'Timesheets',
            'rows' => $repo->weekly($user),
            'kpis' => $repo->kpis($user),
            'weeksHistory' => $repo->weeksHistoryForUser($userId),
            'selectedWeekSummary' => $repo->weekSummaryForUser($userId, $weekStart),
            'weekHistoryLog' => $repo->weekHistoryLogForUser($userId, $weekStart),
            'monthlySummary' => $repo->monthlySummaryForUser($userId, $weekStart),
            'projectsForTimesheet' => $canReport ? $repo->projectsForTimesheetEntry($userId) : [],
            'pendingApprovals' => $canApprove ? $repo->pendingApprovals($user) : [],
            'weeklyGrid' => $canReport ? $repo->weeklyGridForUser($userId, $weekStart) : ['days' => [], 'rows' => [], 'day_totals' => [], 'week_total' => 0, 'weekly_capacity' => 0],
            'canApprove' => $canApprove,
            'canReport' => $canReport,
            'canManageWorkflow' => $canManageWorkflow,
            'canDeleteWeek' => $canDeleteWeek,
            'canManageAdvanced' => $canManageAdvanced,
            'timesheetsEnabled' => $timesheetsEnabled,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekValue' => $weekValue,
            'periodType' => $periodType,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'projectFilter' => $projectFilter,
            'projectsForFilter' => $projectsForFilter,
            'executiveSummary' => $executiveSummary,
            'approvedWeeks' => $approvedWeeks,
            'talentBreakdown' => $talentBreakdown,
            'projectBreakdown' => $projectBreakdown,
            'talentSort' => $talentSort,
            'managedWeekEntries' => $managedWeekEntries,
            'talentOptions' => $talentOptions,
            'talentFilter' => $talentFilter,
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
            (new ProjectService($this->db))->recordHealthSnapshot($projectId);
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

    public function cancelWeekSubmission(): void
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

        $repo->cancelWeekSubmission($userId, $weekStart);
        header('Location: /timesheets?week=' . urlencode($weekStart->format('o-\\WW')));
    }

    public function reopenOwnWeek(): void
    {
        if (!$this->auth->canManageTimesheetWorkflow()) {
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

        $comment = trim((string) ($_POST['comment'] ?? ''));
        $repo->reopenOwnWeekToDraft($userId, $weekStart, $comment !== '' ? $comment : null);
        header('Location: /timesheets?week=' . urlencode($weekStart->format('o-\\WW')));
    }

    public function deleteOwnWeek(): void
    {
        if (!$this->auth->canDeleteTimesheetWorkflowRecords()) {
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

        $confirmToken = trim((string) ($_POST['confirm_token'] ?? ''));
        if ($confirmToken !== 'ELIMINAR') {
            http_response_code(400);
            exit('Confirmación inválida.');
        }

        $comment = trim((string) ($_POST['comment'] ?? ''));
        $repo->deleteWeekEntries($userId, $weekStart, $userId, $comment !== '' ? $comment : null);
        header('Location: /timesheets');
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


    public function adminAction(): void
    {
        if (!$this->auth->canManageAdvancedTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $action = trim((string) ($_POST['admin_action'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            http_response_code(400);
            exit('Debes indicar un motivo para garantizar trazabilidad.');
        }

        try {
            if ($action === 'update_hours') {
                $repo->adminUpdateHours((int) ($_POST['timesheet_id'] ?? 0), (float) ($_POST['hours'] ?? 0), $reason, $userId);
            } elseif ($action === 'delete_entry') {
                $repo->adminDeleteTimesheet((int) ($_POST['timesheet_id'] ?? 0), $reason, $userId);
            } elseif ($action === 'delete_week') {
                $confirm = trim((string) ($_POST['confirm_token'] ?? ''));
                if ($confirm !== 'ELIMINAR MASIVO') {
                    http_response_code(400);
                    exit('Confirmación inválida para eliminación masiva.');
                }
                $weekStart = trim((string) ($_POST['week_start'] ?? ''));
                $week = new DateTimeImmutable($weekStart);
                $talentId = (int) ($_POST['talent_id'] ?? 0);
                $repo->adminDeleteWeek($week, $talentId > 0 ? $talentId : null, $reason, $userId);
            } else {
                http_response_code(400);
                exit('Acción administrativa inválida.');
            }
            header('Location: /timesheets?week=' . urlencode((string) ($_POST['week'] ?? '')));
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error en acción administrativa de timesheets: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo ejecutar la acción administrativa.');
        }
    }

    public function reopenWeek(): void
    {
        if (!$this->auth->canManageTimesheetWorkflow()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $weekStart = trim((string) ($_POST['week_start'] ?? ''));
        $approverUserId = (int) ($_POST['approver_user_id'] ?? $userId);
        $comment = trim((string) ($_POST['comment'] ?? ''));

        try {
            $updated = $repo->reopenWeek($userId, $weekStart, $approverUserId, $comment !== '' ? $comment : null);
            if ($updated <= 0) {
                http_response_code(400);
                exit('No hay semanas aprobadas o rechazadas para reabrir.');
            }
            header('Location: /approvals');
        } catch (\Throwable $e) {
            error_log('Error al reabrir semana de timesheets: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo reabrir la semana.');
        }
    }

    public function deleteWeekWorkflow(): void
    {
        if (!$this->auth->canDeleteTimesheetWorkflowRecords()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $weekStart = trim((string) ($_POST['week_start'] ?? ''));
        $approverUserId = (int) ($_POST['approver_user_id'] ?? $userId);
        $comment = trim((string) ($_POST['comment'] ?? ''));

        try {
            $repo->softDeleteWeekWorkflow($userId, $weekStart, $approverUserId, $comment !== '' ? $comment : null);
            header('Location: /approvals');
        } catch (\Throwable $e) {
            error_log('Error al eliminar workflow de semana de timesheets: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo eliminar el workflow de la semana.');
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

    private function parseDateValue(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return (new DateTimeImmutable($value))->setTime(0, 0);
        } catch (\Throwable $e) {
            return null;
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
            $projectId = (int) ($repo->projectIdForTimesheet($timesheetId) ?? 0);
            if ($projectId > 0) {
                (new ProjectService($this->db))->recordHealthSnapshot($projectId);
            }

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
