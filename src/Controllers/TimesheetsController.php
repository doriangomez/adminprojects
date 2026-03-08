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

        if (!$timesheetsEnabled) {
            http_response_code(404);
            exit('El módulo de timesheets no está habilitado.');
        }
        $projectsForTimesheet = $canReport ? $repo->projectsForTimesheetEntry($userId) : [];
        $clientsForTimesheet = [];
        $seenClients = [];
        foreach ($projectsForTimesheet as $project) {
            $clientId = (int) ($project['client_id'] ?? 0);
            if (isset($seenClients[$clientId])) {
                continue;
            }
            $seenClients[$clientId] = true;
            $clientName = trim((string) ($project['client'] ?? ''));
            $clientsForTimesheet[] = [
                'client_id' => $clientId,
                'client' => $clientName !== '' ? $clientName : 'Sin cliente',
            ];
        }
        usort($clientsForTimesheet, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['client'] ?? ''), (string) ($right['client'] ?? ''));
        });
        $weeklyGrid = $canReport
            ? $repo->weeklyGridForUser($userId, $weekStart)
            : [
                'days' => [],
                'rows' => [],
                'day_totals' => [],
                'week_total' => 0,
                'weekly_capacity' => 0,
                'weekly_capacity_base' => 0,
                'weekly_capacity_breakdown' => [],
                'activities_by_day' => [],
            ];
        $selectedWeekSummary = $repo->weekSummaryForUser($userId, $weekStart);
        $weekIndicators = $this->buildWeekIndicators($weeklyGrid);
        $noticeCode = trim((string) ($_GET['notice'] ?? ''));
        $noticeDay = trim((string) ($_GET['day'] ?? ''));
        $timesheetNotice = null;
        if ($noticeCode === 'day_submitted') {
            $timesheetNotice = $noticeDay !== ''
                ? sprintf('Registro enviado para aprobación (%s).', $noticeDay)
                : 'Registro enviado para aprobación.';
        }

        $this->render('timesheets/index', [
            'title' => 'Timesheets · Registro',
            'projectsForTimesheet' => $projectsForTimesheet,
            'clientsForTimesheet' => $clientsForTimesheet,
            'tasksForTimesheet' => $canReport ? $repo->tasksForTimesheetEntry($userId) : [],
            'recentActivitySuggestions' => $canReport ? $repo->recentActivitySuggestions($userId, 10) : [],
            'activityTypes' => $repo->activityTypesCatalog(),
            'weeklyGrid' => $weeklyGrid,
            'canApprove' => $canApprove,
            'canReport' => $canReport,
            'canManageWorkflow' => $canManageWorkflow,
            'timesheetsEnabled' => $timesheetsEnabled,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekValue' => $weekValue,
            'selectedWeekSummary' => $selectedWeekSummary,
            'weekIndicators' => $weekIndicators,
            'weekHistoryLog' => $repo->weekHistoryLogForUser($userId, $weekStart),
            'canDeleteWeek' => $canDeleteWeek,
            'canManageAdvanced' => $canManageAdvanced,
            'currentUserName' => (string) ($user['name'] ?? 'Usuario'),
            'timesheetNotice' => $timesheetNotice,
        ]);
    }

    public function analytics(): void
    {
        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $timesheetsEnabled = $this->auth->isTimesheetsEnabled();
        $canApprove = $this->auth->canApproveTimesheets();

        if (!$timesheetsEnabled) {
            http_response_code(404);
            exit('El módulo de timesheets no está habilitado.');
        }

        if (!$canApprove) {
            header('Location: /approvals');
            return;
        }

        $weekValue = trim((string) ($_GET['week'] ?? ''));
        $weekStart = $this->parseWeekValue($weekValue) ?? new DateTimeImmutable('monday this week');
        $weekValue = $weekStart->format('o-\\WW');
        $weekEnd = $weekStart->modify('+6 days');

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

        $executiveSummary = $repo->executiveSummary($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null);
        $approvedWeeks = $repo->approvedWeeksByPeriod($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null);
        $talentBreakdown = $repo->talentBreakdownByPeriod($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null, $talentSort);
        $projectBreakdown = $repo->projectBreakdownByPeriod($user, $periodStart, $periodEnd);
        $activityTypeBreakdown = $repo->activityTypeBreakdownByPeriod($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null);
        $phaseBreakdown = $repo->phaseBreakdownByPeriod($user, $periodStart, $periodEnd, $projectFilter > 0 ? $projectFilter : null);
        $projectsForFilter = $repo->projectsCatalog();

        $this->render('timesheets/analytics', [
            'title' => 'Timesheets · Analítica',
            'timesheetsEnabled' => $timesheetsEnabled,
            'weekValue' => $weekValue,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'periodType' => $periodType,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'projectFilter' => $projectFilter,
            'projectsForFilter' => $projectsForFilter,
            'executiveSummary' => $executiveSummary,
            'approvedWeeks' => $approvedWeeks,
            'talentBreakdown' => $talentBreakdown,
            'projectBreakdown' => $projectBreakdown,
            'activityTypeBreakdown' => $activityTypeBreakdown,
            'phaseBreakdown' => $phaseBreakdown,
            'talentSort' => $talentSort,
        ]);
    }

    public function adminTimesheets(): void
    {
        $timesheetsEnabled = $this->auth->isTimesheetsEnabled();
        if (!$timesheetsEnabled) {
            http_response_code(404);
            exit('El módulo de timesheets no está habilitado.');
        }

        $canAccessAdminView = $this->auth->hasRole('Administrador')
            || $this->auth->hasRole('PMO')
            || $this->auth->canManageAdvancedTimesheets();
        if (!$canAccessAdminView) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $weekValueRaw = trim((string) ($_GET['week'] ?? ''));
        $weekStart = $this->parseWeekValue($weekValueRaw) ?? new DateTimeImmutable('monday this week');
        $weekEnd = $weekStart->modify('+6 days');
        $weekValue = $weekStart->format('o-\\WW');

        $filters = [
            'user_id' => (int) ($_GET['user_id'] ?? 0),
            'project_id' => (int) ($_GET['project_id'] ?? 0),
            'client_id' => (int) ($_GET['client_id'] ?? 0),
            'status' => strtolower(trim((string) ($_GET['status'] ?? ''))),
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
        ];

        $report = $repo->adminTimesheetsReport($filters);
        $weeklySummary = $repo->adminWeeklySummary($filters);

        $this->render('admin/timesheets', [
            'title' => 'Admin · Timesheets',
            'weekValue' => $weekValue,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'filters' => $filters,
            'report' => $report,
            'weeklySummary' => $weeklySummary,
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
            $this->capturePmoSnapshot($projectId);
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

    public function submitDay(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        $date = trim((string) ($_POST['date'] ?? ''));
        $day = $this->parseDateValue($date);
        if (!$day instanceof DateTimeImmutable) {
            http_response_code(400);
            exit('Día inválido.');
        }

        $weekStart = $day->modify('monday this week')->setTime(0, 0);
        try {
            $updated = $repo->submitDay($userId, $day->format('Y-m-d'));
            if ($updated <= 0) {
                throw new \InvalidArgumentException('No hay registros en borrador para enviar en este día.');
            }
            header(
                'Location: /timesheets?week=' . urlencode($weekStart->format('o-\\WW'))
                . '&notice=day_submitted&day=' . urlencode($day->format('Y-m-d'))
            );
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
            'task_management_mode' => trim((string) ($_POST['task_management_mode'] ?? 'existing')),
            'new_task_title' => trim((string) ($_POST['new_task_title'] ?? '')),
            'new_task_priority' => trim((string) ($_POST['new_task_priority'] ?? '')),
            'new_task_due_date' => trim((string) ($_POST['new_task_due_date'] ?? '')),
            'new_task_status' => trim((string) ($_POST['new_task_status'] ?? 'todo')),
            'activity_type' => trim((string) ($_POST['activity_type'] ?? '')),
            'activity_description' => trim((string) ($_POST['activity_description'] ?? '')),
            'had_blocker' => filter_var($_POST['had_blocker'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'blocker_description' => trim((string) ($_POST['blocker_description'] ?? '')),
            'had_significant_progress' => filter_var($_POST['had_significant_progress'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'generated_deliverable' => filter_var($_POST['generated_deliverable'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'operational_comment' => trim((string) ($_POST['operational_comment'] ?? '')),
        ];

        if ($projectId <= 0 || $date === '' || $hours <= 0 || $metadata['activity_type'] === '') {
            http_response_code(400);
            exit('Proyecto, fecha, horas y tipo de actividad son requeridos para registrar actividad.');
        }

        try {
            $repo->createDraftActivity($userId, $projectId, $date, $hours, $comment, $metadata);
            (new ProjectService($this->db))->recordHealthSnapshot($projectId);
            $this->capturePmoSnapshot($projectId);
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

    public function createActivityApi(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            $this->jsonResponse(403, ['ok' => false, 'message' => 'Acceso denegado']);
            return;
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
            'task_management_mode' => trim((string) ($_POST['task_management_mode'] ?? 'existing')),
            'new_task_title' => trim((string) ($_POST['new_task_title'] ?? '')),
            'new_task_priority' => trim((string) ($_POST['new_task_priority'] ?? '')),
            'new_task_due_date' => trim((string) ($_POST['new_task_due_date'] ?? '')),
            'new_task_status' => trim((string) ($_POST['new_task_status'] ?? 'todo')),
            'activity_type' => trim((string) ($_POST['activity_type'] ?? '')),
            'activity_description' => trim((string) ($_POST['activity_description'] ?? '')),
            'had_blocker' => filter_var($_POST['had_blocker'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'blocker_description' => trim((string) ($_POST['blocker_description'] ?? '')),
            'had_significant_progress' => filter_var($_POST['had_significant_progress'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'generated_deliverable' => filter_var($_POST['generated_deliverable'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'operational_comment' => trim((string) ($_POST['operational_comment'] ?? '')),
        ];

        if ($projectId <= 0 || $date === '' || $hours <= 0 || $metadata['activity_description'] === '' || $comment === '' || $metadata['activity_type'] === '') {
            $this->jsonResponse(400, ['ok' => false, 'message' => 'Proyecto, fecha, horas, descripción, tipo de actividad y comentario son obligatorios.']);
            return;
        }

        try {
            $activityId = $repo->createDraftActivity($userId, $projectId, $date, $hours, $comment, $metadata);
            (new ProjectService($this->db))->recordHealthSnapshot($projectId);
            $this->capturePmoSnapshot($projectId);
            $this->jsonResponse(200, ['ok' => true, 'id' => $activityId]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(400, ['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('Error creando actividad de timesheet (API): ' . $e->getMessage());
            $this->jsonResponse(500, ['ok' => false, 'message' => 'No se pudo registrar la actividad.']);
        }
    }

    public function updateActivityApi(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            $this->jsonResponse(403, ['ok' => false, 'message' => 'Acceso denegado']);
            return;
        }

        $repo = new TimesheetsRepository($this->db);
        $userId = (int) (($this->auth->user() ?? [])['id'] ?? 0);
        $activityId = (int) ($_POST['activity_id'] ?? 0);
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $hours = max(0, (float) ($_POST['hours'] ?? 0));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $metadata = [
            'task_id' => (int) ($_POST['task_id'] ?? 0),
            'activity_type' => trim((string) ($_POST['activity_type'] ?? '')),
            'activity_description' => trim((string) ($_POST['activity_description'] ?? '')),
            'had_blocker' => filter_var($_POST['had_blocker'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'blocker_description' => trim((string) ($_POST['blocker_description'] ?? '')),
            'had_significant_progress' => filter_var($_POST['had_significant_progress'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'generated_deliverable' => filter_var($_POST['generated_deliverable'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'operational_comment' => trim((string) ($_POST['operational_comment'] ?? '')),
        ];

        try {
            if ($metadata['activity_type'] === '') {
                throw new \InvalidArgumentException('Debes seleccionar un tipo de actividad.');
            }
            $updated = $repo->updateDraftActivity($activityId, $userId, $projectId, $date, $hours, $comment, $metadata);
            (new ProjectService($this->db))->recordHealthSnapshot($projectId);
            $this->capturePmoSnapshot($projectId);
            $this->jsonResponse(200, ['ok' => $updated]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(400, ['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('Error actualizando actividad de timesheet (API): ' . $e->getMessage());
            $this->jsonResponse(500, ['ok' => false, 'message' => 'No se pudo actualizar la actividad.']);
        }
    }

    public function deleteActivityApi(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            $this->jsonResponse(403, ['ok' => false, 'message' => 'Acceso denegado']);
            return;
        }

        $repo = new TimesheetsRepository($this->db);
        $userId = (int) (($this->auth->user() ?? [])['id'] ?? 0);
        $activityId = (int) ($_POST['activity_id'] ?? 0);

        try {
            $deleted = $repo->deleteDraftActivity($activityId, $userId);
            $this->jsonResponse(200, ['ok' => $deleted]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(400, ['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('Error eliminando actividad de timesheet (API): ' . $e->getMessage());
            $this->jsonResponse(500, ['ok' => false, 'message' => 'No se pudo eliminar la actividad.']);
        }
    }

    public function duplicateActivityApi(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            $this->jsonResponse(403, ['ok' => false, 'message' => 'Acceso denegado']);
            return;
        }

        $repo = new TimesheetsRepository($this->db);
        $userId = (int) (($this->auth->user() ?? [])['id'] ?? 0);
        $activityId = (int) ($_POST['activity_id'] ?? 0);
        $targetDate = trim((string) ($_POST['target_date'] ?? ''));

        try {
            $newId = $repo->duplicateDraftActivity($activityId, $userId, $targetDate);
            $this->jsonResponse(200, ['ok' => true, 'id' => $newId]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(400, ['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('Error duplicando actividad de timesheet (API): ' . $e->getMessage());
            $this->jsonResponse(500, ['ok' => false, 'message' => 'No se pudo duplicar la actividad.']);
        }
    }

    public function moveActivityApi(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            $this->jsonResponse(403, ['ok' => false, 'message' => 'Acceso denegado']);
            return;
        }

        $repo = new TimesheetsRepository($this->db);
        $userId = (int) (($this->auth->user() ?? [])['id'] ?? 0);
        $activityId = (int) ($_POST['activity_id'] ?? 0);
        $targetDate = trim((string) ($_POST['target_date'] ?? ''));

        try {
            $moved = $repo->moveDraftActivity($activityId, $userId, $targetDate);
            $this->jsonResponse(200, ['ok' => $moved]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(400, ['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('Error moviendo actividad de timesheet (API): ' . $e->getMessage());
            $this->jsonResponse(500, ['ok' => false, 'message' => 'No se pudo mover la actividad.']);
        }
    }

    public function duplicateDayApi(): void
    {
        if (!$this->auth->canAccessTimesheets()) {
            $this->jsonResponse(403, ['ok' => false, 'message' => 'Acceso denegado']);
            return;
        }

        $repo = new TimesheetsRepository($this->db);
        $userId = (int) (($this->auth->user() ?? [])['id'] ?? 0);
        $sourceDate = trim((string) ($_POST['source_date'] ?? ''));
        $targetDate = trim((string) ($_POST['target_date'] ?? ''));

        try {
            $created = $repo->duplicateDayActivities($userId, $sourceDate, $targetDate);
            $this->jsonResponse(200, ['ok' => true, 'created' => $created]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(400, ['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('Error duplicando día de timesheet (API): ' . $e->getMessage());
            $this->jsonResponse(500, ['ok' => false, 'message' => 'No se pudo duplicar el día.']);
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
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);

        if (!in_array($status, ['approved', 'rejected'], true)) {
            http_response_code(400);
            exit('Estado inválido.');
        }
        if ($status === 'rejected' && $comment === '') {
            http_response_code(400);
            exit('Debes indicar un comentario para rechazar la semana.');
        }

        try {
            $repo->updateWeekApprovalStatus(
                $userId,
                $weekStart,
                $status,
                $comment !== '' ? $comment : null,
                $targetUserId > 0 ? $targetUserId : null
            );
            header('Location: /approvals');
        } catch (\Throwable $e) {
            error_log('Error al aprobar semana de timesheets: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo actualizar la aprobación semanal.');
        }
    }

    public function approveDay(): void
    {
        if (!$this->auth->canApproveTimesheets()) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $repo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? 'approved'));
        $date = trim((string) ($_POST['date'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);

        if (!in_array($status, ['approved', 'rejected'], true)) {
            http_response_code(400);
            exit('Estado inválido.');
        }
        if ($date === '') {
            http_response_code(400);
            exit('Día inválido.');
        }
        if ($status === 'rejected' && $comment === '') {
            http_response_code(400);
            exit('Debes indicar un comentario para rechazar el día.');
        }

        try {
            $repo->updateDayApprovalStatus(
                $userId,
                $date,
                $status,
                $comment !== '' ? $comment : null,
                $targetUserId > 0 ? $targetUserId : null
            );
            header('Location: /approvals');
        } catch (\Throwable $e) {
            error_log('Error al aprobar día de timesheets: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo actualizar la aprobación diaria.');
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

    private function buildWeekIndicators(array $weeklyGrid): array
    {
        $weekTotal = (float) ($weeklyGrid['week_total'] ?? 0);
        $weeklyCapacity = (float) ($weeklyGrid['weekly_capacity'] ?? 0);
        $weeklyBaseCapacity = (float) ($weeklyGrid['weekly_capacity_base'] ?? $weeklyCapacity);
        $compliance = $weeklyCapacity > 0 ? round(($weekTotal / $weeklyCapacity) * 100, 2) : 0.0;
        $remainingCapacity = max(0.0, $weeklyCapacity - $weekTotal);
        $activityByDay = is_array($weeklyGrid['activities_by_day'] ?? null) ? $weeklyGrid['activities_by_day'] : [];
        $capacityBreakdown = is_array($weeklyGrid['weekly_capacity_breakdown'] ?? null) ? $weeklyGrid['weekly_capacity_breakdown'] : [];
        $absenceDetails = [];
        foreach ((array) ($capacityBreakdown['absence_details'] ?? []) as $detail) {
            if (!is_array($detail)) {
                continue;
            }
            $type = (string) ($detail['type'] ?? '');
            if ($type === '') {
                continue;
            }
            $absenceDetails[$type] = $detail;
        }

        $vacationsDays = (float) ($absenceDetails['vacaciones']['days'] ?? 0.0);
        $medicalHours = (float) ($absenceDetails['permiso_medico']['hours'] ?? 0.0);
        $holidayDays = (int) ($capacityBreakdown['holiday_days'] ?? 0);
        $vacationsDaysText = '0';
        if ($vacationsDays > 0) {
            $vacationsFormatted = abs($vacationsDays - floor($vacationsDays)) < 0.001
                ? number_format($vacationsDays, 0)
                : number_format($vacationsDays, 1);
            $vacationsDaysText = $vacationsFormatted . ' día' . ($vacationsDays > 1.001 ? 's' : '');
        }
        $capacityTooltipLines = [
            'Vacaciones: ' . $vacationsDaysText,
            'Permiso médico: ' . ($medicalHours > 0 ? number_format($medicalHours, 1) . 'h' : '0'),
            'Festivos: ' . $holidayDays,
        ];

        foreach ($absenceDetails as $type => $detail) {
            if (in_array($type, ['vacaciones', 'permiso_medico'], true)) {
                continue;
            }
            $hours = (float) ($detail['hours'] ?? 0);
            if ($hours <= 0) {
                continue;
            }
            $label = (string) ($detail['label'] ?? ucfirst(str_replace('_', ' ', $type)));
            $capacityTooltipLines[] = $label . ': ' . number_format($hours, 1) . 'h';
        }

        $byProject = [];
        foreach ($activityByDay as $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                $project = trim((string) ($item['project'] ?? 'Sin proyecto'));
                $byProject[$project] = ($byProject[$project] ?? 0.0) + (float) ($item['hours'] ?? 0);
            }
        }
        arsort($byProject);
        $topProject = $byProject !== [] ? (string) array_key_first($byProject) : 'Sin datos';
        $topProjectHours = $byProject !== [] ? (float) reset($byProject) : 0.0;

        return [
            'week_total' => $weekTotal,
            'weekly_capacity' => $weeklyCapacity,
            'weekly_capacity_base' => $weeklyBaseCapacity,
            'remaining_capacity' => $remainingCapacity,
            'compliance_percent' => $compliance,
            'top_project' => $topProject,
            'top_project_hours' => $topProjectHours,
            'capacity_tooltip_lines' => $capacityTooltipLines,
            'capacity_breakdown' => $capacityBreakdown,
        ];
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

    private function capturePmoSnapshot(int $projectId): void
    {
        if ($projectId <= 0) {
            return;
        }

        try {
            (new PmoAutomationService($this->db))->captureSnapshotForProject($projectId, new DateTimeImmutable('today'));
        } catch (\Throwable $e) {
            error_log('Error actualizando snapshot PMO: ' . $e->getMessage());
        }
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

    private function jsonResponse(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
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
                $this->capturePmoSnapshot($projectId);
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
