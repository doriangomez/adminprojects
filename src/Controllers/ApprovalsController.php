<?php

declare(strict_types=1);

use App\Repositories\ProjectNodesRepository;
use App\Repositories\TimesheetsRepository;

class ApprovalsController extends Controller
{
    public function index(): void
    {
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $projectTypeFilter = strtolower(trim((string) ($_GET['project_type'] ?? '')));
        if (!in_array($projectTypeFilter, ['', 'proyecto', 'poc'], true)) {
            $projectTypeFilter = in_array($projectTypeFilter, ['convencional', 'scrum', 'hibrido', 'outsourcing'], true)
                ? 'proyecto'
                : '';
        }
        $canViewProjects = $this->auth->can('projects.view');
        $canAccessTimesheets = $this->auth->canAccessTimesheets();
        $canViewOwnTimesheetApprovals = $canAccessTimesheets || $this->auth->hasRole('Talento');

        if (!$canViewProjects && !$canViewOwnTimesheetApprovals) {
            $this->denyAccess('No tienes permisos para acceder a la bandeja de aprobaciones.');
        }

        $roleFlags = $canViewProjects
            ? $this->documentRoleFlags($userId)
            : [
                'can_review' => false,
                'can_validate' => false,
                'can_approve' => false,
                'can_manage' => false,
            ];

        $repo = new ProjectNodesRepository($this->db);
        $reviewQueue = $canViewProjects && $roleFlags['can_review']
            ? $repo->inboxDocumentsForUser('en_revision', 'reviewer_id', $userId, $projectTypeFilter)
            : [];
        $validationQueue = $canViewProjects && $roleFlags['can_validate']
            ? $repo->inboxDocumentsForUser('en_validacion', 'validator_id', $userId, $projectTypeFilter)
            : [];
        $approvalQueue = $canViewProjects && $roleFlags['can_approve']
            ? $repo->inboxDocumentsForUser('en_aprobacion', 'approver_id', $userId, $projectTypeFilter)
            : [];

        $dispatchQueue = [];
        if ($canViewProjects && $this->auth->can('projects.manage')) {
            $reviewed = $repo->inboxDocumentsByStatus('revisado', $projectTypeFilter);
            $validated = $repo->inboxDocumentsByStatus('validado', $projectTypeFilter);
            $dispatchQueue = [
                'send_validation' => array_values(array_filter($reviewed, static fn (array $doc): bool => !empty($doc['validator_id']))),
                'send_approval' => array_values(array_filter($validated, static fn (array $doc): bool => !empty($doc['approver_id']))),
            ];
        }

        $timesheetsRepo = new TimesheetsRepository($this->db);
        $canApproveTimesheets = $this->auth->canApproveTimesheets();
        $timesheetApprovals = $canApproveTimesheets
            ? $timesheetsRepo->pendingApprovalsByWeek($user)
            : [];
        $timesheetHistory = $canApproveTimesheets
            ? $timesheetsRepo->weekApprovalHistoryByApprover($user)
            : [];
        if ($canApproveTimesheets) {
            $timesheetApprovalDebug = array_map(static function (array $week): array {
                $weekStart = (string) ($week['week_start'] ?? '');
                $weekEnd = '';
                if ($weekStart !== '') {
                    try {
                        $weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
                    } catch (Throwable) {
                        $weekEnd = 'invalid_week_start';
                    }
                }

                return [
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'owner_user_id' => (int) ($week['owner_user_id'] ?? 0),
                    'timesheet_ids' => array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), (array) ($week['rows'] ?? [])),
                    'records' => array_map(static fn (array $row): array => [
                        'id' => (int) ($row['id'] ?? 0),
                        'date' => (string) ($row['date'] ?? ''),
                        'user_id' => (int) ($row['user_id'] ?? 0),
                        'talent_id' => (int) ($row['talent_id'] ?? 0),
                        'approver_user_id' => (int) ($row['approver_user_id'] ?? 0),
                        'status' => (string) ($row['status'] ?? ''),
                    ], (array) ($week['rows'] ?? [])),
                ];
            }, $timesheetApprovals);
            error_log('Debug bandeja aprobaciones timesheets: ' . json_encode($timesheetApprovalDebug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
        }
        $talentApprovalSummary = [];
        $talentApprovalWeeks = [];
        $talentApprovalPeriod = [];
        if (!$canApproveTimesheets && $canViewOwnTimesheetApprovals) {
            $periodStart = (new DateTimeImmutable('first day of this month'))->setTime(0, 0);
            $periodEnd = (new DateTimeImmutable('last day of this month'))->setTime(0, 0);
            $talentApprovalSummary = $timesheetsRepo->executiveSummary($user, $periodStart, $periodEnd);
            $talentApprovalWeeks = $timesheetsRepo->approvedWeeksByPeriod($user, $periodStart, $periodEnd);
            $talentApprovalPeriod = [
                'start' => $periodStart,
                'end' => $periodEnd,
            ];
        }

        $this->render('approvals/index', [
            'title' => 'Bandeja de Aprobaciones',
            'filters' => ['project_type' => $projectTypeFilter],
            'reviewQueue' => $reviewQueue,
            'validationQueue' => $validationQueue,
            'approvalQueue' => $approvalQueue,
            'dispatchQueue' => $dispatchQueue,
            'roleFlags' => $roleFlags,
            'timesheetApprovals' => $timesheetApprovals,
            'timesheetHistory' => $timesheetHistory,
            'canApproveTimesheets' => $canApproveTimesheets,
            'talentApprovalSummary' => $talentApprovalSummary,
            'talentApprovalWeeks' => $talentApprovalWeeks,
            'talentApprovalPeriod' => $talentApprovalPeriod,
            'canManageTimesheetWorkflow' => $this->auth->canManageTimesheetWorkflow(),
            'canDeleteTimesheetWorkflowRecords' => $this->auth->canDeleteTimesheetWorkflowRecords(),
        ]);
    }
}
