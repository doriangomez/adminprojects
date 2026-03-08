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
        $canViewProjects = $this->auth->can('projects.view');
        $canAccessTimesheets = $this->auth->canAccessTimesheets();

        if (!$canViewProjects && !$canAccessTimesheets) {
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
            ? $repo->inboxDocumentsForUser('en_revision', 'reviewer_id', $userId)
            : [];
        $validationQueue = $canViewProjects && $roleFlags['can_validate']
            ? $repo->inboxDocumentsForUser('en_validacion', 'validator_id', $userId)
            : [];
        $approvalQueue = $canViewProjects && $roleFlags['can_approve']
            ? $repo->inboxDocumentsForUser('en_aprobacion', 'approver_id', $userId)
            : [];

        $dispatchQueue = [];
        if ($canViewProjects && $this->auth->can('projects.manage')) {
            $reviewed = $repo->inboxDocumentsByStatus('revisado');
            $validated = $repo->inboxDocumentsByStatus('validado');
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
        $talentApprovalSummary = [];
        $talentApprovalWeeks = [];
        $talentApprovalPeriod = [];
        if (!$canApproveTimesheets && $canAccessTimesheets) {
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
