<?php

declare(strict_types=1);

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

        $this->render('approvals/index', [
            'title' => 'Bandeja de Aprobaciones',
            'reviewQueue' => $reviewQueue,
            'validationQueue' => $validationQueue,
            'approvalQueue' => $approvalQueue,
            'dispatchQueue' => $dispatchQueue,
            'roleFlags' => $roleFlags,
        ]);
    }

    private function documentRoleFlags(int $userId): array
    {
        $row = $this->db->fetchOne(
            'SELECT can_review_documents, can_validate_documents, can_approve_documents FROM users WHERE id = :id LIMIT 1',
            [':id' => $userId]
        ) ?: [];

        return [
            'can_review' => ((int) ($row['can_review_documents'] ?? 0)) === 1,
            'can_validate' => ((int) ($row['can_validate_documents'] ?? 0)) === 1,
            'can_approve' => ((int) ($row['can_approve_documents'] ?? 0)) === 1,
            'can_manage' => $this->auth->can('projects.manage'),
        ];
    }
}
