<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\TimesheetsRepository;

class TimesheetsController extends Controller
{
    public function analytics(): void
    {
        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
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
        $activityTypeBreakdown = $repo->activityTypeBreakdownByPeriod($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null);
        $phaseBreakdown = $repo->phaseBreakdownByPeriod($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null);
        $projectsForFilter = $repo->projectsCatalog();
        $weeksHistory = $repo->weeksHistoryForUser($userId);

        $weeksRegistered = count($approvedWeeks);
        $weeksApproved = count(array_filter($approvedWeeks, static fn($w): bool => ((int) ($w['status_weight'] ?? 0)) >= 5));
        $weeksPending = max(0, $weeksRegistered - $weeksApproved);
        $approvedPercent = (float) ($executiveSummary['approved_percent'] ?? 0);
        $compliancePercent = (float) ($executiveSummary['compliance_percent'] ?? 0);

        $statusMap = [
            'approved' => ['label' => 'Aprobada', 'class' => 'approved'],
            'rejected' => ['label' => 'Rechazada', 'class' => 'rejected'],
            'submitted' => ['label' => 'Pendiente', 'class' => 'submitted'],
            'draft' => ['label' => 'Borrador', 'class' => 'draft'],
        ];

        $this->render('timesheets/analytics', [
            'title' => 'Timesheets – Analítica',
            'basePath' => '',
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekValue' => $weekValue,
            'periodType' => $periodType,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'projectFilter' => $projectFilter,
            'projectsForFilter' => $projectsForFilter,
            'talentSort' => $talentSort,
            'executiveSummary' => $executiveSummary,
            'approvedWeeks' => $approvedWeeks,
            'talentBreakdown' => $talentBreakdown,
            'projectBreakdown' => $projectBreakdown,
            'activityTypeBreakdown' => $activityTypeBreakdown,
            'phaseBreakdown' => $phaseBreakdown,
            'weeksHistory' => $weeksHistory,
            'weeksRegistered' => $weeksRegistered,
            'weeksApproved' => $weeksApproved,
            'weeksPending' => $weeksPending,
            'approvedPercent' => $approvedPercent,
            'compliancePercent' => $compliancePercent,
            'statusMap' => $statusMap,
        ]);
    }

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
        $activityTypeBreakdown = $repo->activityTypeBreakdownByPeriod($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null);
        $phaseBreakdown = $repo->phaseBreakdownByPeriod($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null);
        $projectsForFilter = $repo->projectsCatalog();
        $talentFilter = (int) ($_GET['talent_id'] ?? 0);
        $managedWeekEntries = ($canApprove || $canManageAdvanced) ? $repo->managedWeekEntries($weekStart, $talentFilter > 0 ? $talentFilter : null) : [];
        $talentOptions = $this->db->fetchAll('SELECT id, name FROM talents ORDER BY name ASC');
        $projectBreakdownWeek = $repo->projectBreakdownByPeriod($user, $weekStart, $weekEnd);

        $this->render('timesheets/operational', [
            'title' => 'Timesheets',
            'rows' => $repo->weekly($user),
            'kpis' => $repo->kpis($user),
            'weeksHistory' => $repo->weeksHistoryForUser($userId),
            'selectedWeekSummary' => $repo->weekSummaryForUser($userId, $weekStart),
            'weekHistoryLog' => $repo->weekHistoryLogForUser($userId, $weekStart),
            'monthlySummary' => $repo->monthlySummaryForUser($userId, $weekStart),
            'projectsForTimesheet' => $canReport ? $repo->projectsForTimesheetEntry($userId) : [],
            'tasksForTimesheet' => $canReport ? $repo->tasksForTimesheetEntry($userId) : [],
            'recentActivitySuggestions' => $canReport ? $repo->recentActivitySuggestions($userId, 8) : [],
            'activityTypes' => $repo->activityTypesCatalog(),
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
            'projectBreakdownWeek' => $projectBreakdownWeek,
            'activityTypeBreakdown' => $activityTypeBreakdown,
            'phaseBreakdown' => $phaseBreakdown,
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
        $syncOperational = filter_var($_POST['sync_operational'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $metadata = [];
        if (array_key_exists('task_id', $_POST)) {
            $metadata['task_id'] = (int) $_POST['task_id'];
        }
        foreach (['phase_name', 'subphase_name', 'activity_type', 'activity_description', 'blocker_description', 'operational_comment'] as $field) {
            if (array_key_exists($field, $_POST)) {
                $metadata[$field] = trim((string) $_POST[$field]);
            }
        }
        foreach (['had_blocker', 'had_significant_progress', 'generated_deliverable'] as $flag) {
            if (array_key_exists($flag, $_POST)) {
                $metadata[$flag] = filter_var($_POST[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if ($projectId <= 0 || $date === '') {
            http_response_code(400);
            exit('Proyecto y fecha son requeridos.');
        }

        try {
            $repo->upsertDraftCell($userId, $projectId, $date, $hours, $comment, $metadata, $syncOperational);
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

        $config = (new ConfigService($this->db))->getConfig();
        $timesheetRules = $config['operational_rules']['timesheets'] ?? [];
        $minimumWeeklyHours = max(0, (float) ($timesheetRules['minimum_weekly_hours'] ?? 0));
        $lockIncompleteWeek = (bool) ($timesheetRules['lock_incomplete_week'] ?? true);

        try {
            $repo->submitWeek($userId, $weekStart, $minimumWeeklyHours, $lockIncompleteWeek);
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

    public function createActivity(): void
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
        $metadata = [
            'task_id' => (int) ($_POST['task_id'] ?? 0),
            'phase_name' => trim((string) ($_POST['phase_name'] ?? '')),
            'subphase_name' => trim((string) ($_POST['subphase_name'] ?? '')),
            'activity_type' => trim((string) ($_POST['activity_type'] ?? '')),
            'activity_description' => trim((string) ($_POST['activity_description'] ?? '')),
            'had_blocker' => filter_var($_POST['had_blocker'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'blocker_description' => trim((string) ($_POST['blocker_description'] ?? '')),
            'had_significant_progress' => filter_var($_POST['had_significant_progress'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'generated_deliverable' => filter_var($_POST['generated_deliverable'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'operational_comment' => trim((string) ($_POST['operational_comment'] ?? '')),
        ];

        if ($projectId <= 0 || $date === '' || $hours <= 0) {
            http_response_code(400);
            exit('Proyecto, fecha y horas son requeridos para registrar actividad.');
        }

        try {
            $newId = $repo->createActivityEntry($userId, $projectId, $date, $hours, $comment, $metadata, true);
            (new ProjectService($this->db))->recordHealthSnapshot($projectId);

            $saveAndDuplicate = filter_var($_POST['save_and_duplicate'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($saveAndDuplicate && $newId > 0) {
                $dateObj = $this->parseDateValue($date);
                if ($dateObj) {
                    $nextDay = $dateObj->modify('+1 day')->format('Y-m-d');
                    try {
                        $repo->duplicateActivity($userId, $newId, $nextDay);
                    } catch (\Throwable $e) {
                        error_log('Timesheet duplicate after save: ' . $e->getMessage());
                    }
                }
            }

            $weekDate = $this->parseDateValue($date);
            $weekValue = $weekDate ? $weekDate->modify('monday this week')->format('o-\\WW') : (new DateTimeImmutable('monday this week'))->format('o-\\WW');
            header('Location: /timesheets?week=' . urlencode($weekValue));
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('Error guardando actividad de timesheet: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo registrar la actividad.');
        }
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
                try {
                    $week = new DateTimeImmutable($weekStart);
                } catch (\Throwable $e) {
                    http_response_code(400);
                    exit('Semana inválida.');
                }
                $talentId = (int) ($_POST['talent_id'] ?? 0);
                $repo->adminDeleteWeek($week, $talentId > 0 ? $talentId : null, $reason, $userId);
            } elseif ($action === 'reopen_week') {
                $weekStart = trim((string) ($_POST['week_start'] ?? ''));
                try {
                    $week = new DateTimeImmutable($weekStart);
                } catch (\Throwable $e) {
                    http_response_code(400);
                    exit('Semana inválida.');
                }
                $talentId = (int) ($_POST['talent_id'] ?? 0);
                $reopened = $repo->adminReopenWeek($week, $talentId > 0 ? $talentId : null, $reason, $userId);
                if ($reopened <= 0) {
                    http_response_code(400);
                    exit('No hay registros aprobados/enviados para reabrir en la semana seleccionada.');
                }
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

    public function updateActivity(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $timesheetId = (int) ($_POST['timesheet_id'] ?? 0);
        $data = [
            'hours' => isset($_POST['hours']) ? (float) $_POST['hours'] : null,
            'comment' => trim((string) ($_POST['comment'] ?? '')),
            'task_id' => (int) ($_POST['task_id'] ?? 0),
            'activity_description' => trim((string) ($_POST['activity_description'] ?? '')),
            'had_blocker' => filter_var($_POST['had_blocker'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'blocker_description' => trim((string) ($_POST['blocker_description'] ?? '')),
            'had_significant_progress' => filter_var($_POST['had_significant_progress'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'generated_deliverable' => filter_var($_POST['generated_deliverable'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
        $data = array_filter($data, static fn($v) => $v !== null);

        try {
            $repo->updateOwnActivity($userId, $timesheetId, $data);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deleteActivity(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $timesheetId = (int) ($_POST['timesheet_id'] ?? 0);
        $weekValue = trim((string) ($_POST['week'] ?? ''));

        try {
            $repo->deleteOwnActivity($userId, $timesheetId);
            if ($weekValue !== '') {
                header('Location: /timesheets?week=' . urlencode($weekValue));
            } else {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
            }
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function duplicateDay(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $sourceDate = trim((string) ($_POST['source_date'] ?? ''));
        $targetDate = trim((string) ($_POST['target_date'] ?? ''));

        $source = $this->parseDateValue($sourceDate);
        $target = $this->parseDateValue($targetDate);
        if (!$source || !$target) {
            http_response_code(400);
            exit('Fechas inválidas.');
        }

        try {
            $count = $repo->duplicateDay($userId, $sourceDate, $targetDate);
            $weekValue = $target->modify('monday this week')->format('o-\\WW');
            header('Location: /timesheets?week=' . urlencode($weekValue) . '&duplicated=' . $count);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function duplicateActivity(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $timesheetId = (int) ($_POST['timesheet_id'] ?? 0);
        $targetDate = trim((string) ($_POST['target_date'] ?? ''));

        $target = $this->parseDateValue($targetDate);
        if (!$target) {
            http_response_code(400);
            exit('Fecha destino inválida.');
        }

        try {
            $repo->duplicateActivity($userId, $timesheetId, $targetDate);
            $weekValue = $target->modify('monday this week')->format('o-\\WW');
            header('Location: /timesheets?week=' . urlencode($weekValue));
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function moveActivity(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $timesheetId = (int) ($_POST['timesheet_id'] ?? 0);
        $targetDate = trim((string) ($_POST['target_date'] ?? ''));

        $target = $this->parseDateValue($targetDate);
        if (!$target) {
            http_response_code(400);
            exit('Fecha destino inválida.');
        }

        try {
            $repo->moveActivity($userId, $timesheetId, $targetDate);
            $weekValue = $target->modify('monday this week')->format('o-\\WW');
            header('Location: /timesheets?week=' . urlencode($weekValue));
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
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
