<?php

declare(strict_types=1);

use App\Repositories\ProjectNodesRepository;
use App\Repositories\TimesheetsRepository;

class ApprovalsController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('projects.view');

        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $roleFlags = $this->documentRoleFlags($userId);

        $repo = new ProjectNodesRepository($this->db);
        $reviewQueue = $roleFlags['can_review']
            ? $repo->inboxDocumentsForUser('en_revision', 'reviewer_id', $userId)
            : [];
        $validationQueue = $roleFlags['can_validate']
            ? $repo->inboxDocumentsForUser('en_validacion', 'validator_id', $userId)
            : [];
        $approvalQueue = $roleFlags['can_approve']
            ? $repo->inboxDocumentsForUser('en_aprobacion', 'approver_id', $userId)
            : [];

        $dispatchQueue = [];
        if ($this->auth->can('projects.manage')) {
            $reviewed = $repo->inboxDocumentsByStatus('revisado');
            $validated = $repo->inboxDocumentsByStatus('validado');
            $dispatchQueue = [
                'send_validation' => array_values(array_filter($reviewed, static fn (array $doc): bool => !empty($doc['validator_id']))),
                'send_approval' => array_values(array_filter($validated, static fn (array $doc): bool => !empty($doc['approver_id']))),
            ];
        }

        $timesheetApprovals = $this->auth->canApproveTimesheets()
            ? (new TimesheetsRepository($this->db))->pendingApprovals($user)
            : [];

        $this->render('approvals/index', [
            'title' => 'Bandeja de Aprobaciones',
            'reviewQueue' => $reviewQueue,
            'validationQueue' => $validationQueue,
            'approvalQueue' => $approvalQueue,
            'dispatchQueue' => $dispatchQueue,
            'roleFlags' => $roleFlags,
            'timesheetApprovals' => $timesheetApprovals,
        ]);
    }
}
