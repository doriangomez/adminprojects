<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\OrganizationsRepository;
use App\Repositories\UsersRepository;

class OrganizationsController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('pmo.organizations.manage');
        $repo = new OrganizationsRepository($this->db);

        $this->render('organizations/index', [
            'title' => 'Organizaciones',
            'organizations' => $repo->all(),
            'canManage' => true,
            'orgTypes' => OrganizationsRepository::ORG_TYPES,
            'flash' => $this->flashMessage(),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('pmo.organizations.manage');

        $this->render('organizations/form', [
            'title' => 'Nueva organización',
            'organization' => null,
            'orgTypes' => OrganizationsRepository::ORG_TYPES,
            'mode' => 'create',
            'error' => null,
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('pmo.organizations.manage');
        $repo = new OrganizationsRepository($this->db);
        $userId = (int) ($this->auth->user()['id'] ?? 0);

        try {
            $payload = $this->collectPayload();
            $id = $repo->create($payload);
            (new AuditLogRepository($this->db))->log($userId, 'organization', $id, 'created', $payload);
            header('Location: /organizations?saved=1');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('organizations/form', [
                'title' => 'Nueva organización',
                'organization' => $payload ?? $_POST,
                'orgTypes' => OrganizationsRepository::ORG_TYPES,
                'mode' => 'create',
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            error_log('organizations.store: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo crear la organización.');
        }
    }

    public function edit(int $id): void
    {
        $this->requirePermission('pmo.organizations.manage');
        $repo = new OrganizationsRepository($this->db);
        $organization = $repo->find($id);
        if ($organization === null) {
            http_response_code(404);
            exit('Organización no encontrada');
        }

        $this->render('organizations/form', [
            'title' => 'Editar organización',
            'organization' => $organization,
            'orgTypes' => OrganizationsRepository::ORG_TYPES,
            'mode' => 'edit',
            'error' => null,
            'linkCounts' => $repo->linkCounts($id),
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('pmo.organizations.manage');
        $repo = new OrganizationsRepository($this->db);
        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $before = $repo->find($id);
        if ($before === null) {
            http_response_code(404);
            exit('Organización no encontrada');
        }

        try {
            $payload = $this->collectPayload();
            $repo->update($id, $payload);
            (new AuditLogRepository($this->db))->log($userId, 'organization', $id, 'updated', [
                'before' => $before,
                'after' => $payload,
            ]);
            header('Location: /organizations?updated=1');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('organizations/form', [
                'title' => 'Editar organización',
                'organization' => array_merge($before, $payload ?? $_POST),
                'orgTypes' => OrganizationsRepository::ORG_TYPES,
                'mode' => 'edit',
                'error' => $e->getMessage(),
                'linkCounts' => $repo->linkCounts($id),
            ]);
        } catch (\Throwable $e) {
            error_log('organizations.update: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo actualizar la organización.');
        }
    }

    public function inactivate(int $id): void
    {
        $this->requirePermission('pmo.organizations.manage');
        $repo = new OrganizationsRepository($this->db);
        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $before = $repo->find($id);
        if ($before === null) {
            http_response_code(404);
            exit('Organización no encontrada');
        }

        try {
            $repo->inactivate($id);
            (new AuditLogRepository($this->db))->log($userId, 'organization', $id, 'inactivated', [
                'name' => $before['name'] ?? null,
            ]);
            header('Location: /organizations?inactivated=1');
        } catch (\Throwable $e) {
            error_log('organizations.inactivate: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo inactivar la organización.');
        }
    }

    public function assignToUser(int $userId): void
    {
        $this->requirePermission('pmo.organizations.manage');
        $orgId = (int) ($_POST['organization_id'] ?? 0);
        $isPrimary = isset($_POST['is_primary']);
        $actorId = (int) ($this->auth->user()['id'] ?? 0);

        $user = (new UsersRepository($this->db))->find($userId);
        if ($user === null) {
            http_response_code(404);
            exit('Usuario no encontrado');
        }

        try {
            $repo = new OrganizationsRepository($this->db);
            $linkId = $repo->assignToUser($userId, $orgId, $isPrimary);
            (new AuditLogRepository($this->db))->log($actorId, 'user_organization', $linkId, 'assigned', [
                'user_id' => $userId,
                'organization_id' => $orgId,
                'is_primary' => $isPrimary,
            ]);
            if ($isPrimary) {
                (new AuditLogRepository($this->db))->log($actorId, 'user_organization', $userId, 'primary_changed', [
                    'user_id' => $userId,
                    'organization_id' => $orgId,
                ]);
            }
            header('Location: /config?tab=gobierno&org_saved=1#usuarios');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('organizations.assignToUser: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo asignar la organización al usuario.');
        }
    }

    public function setPrimaryForUser(int $userId): void
    {
        $this->requirePermission('pmo.organizations.manage');
        $orgId = (int) ($_POST['organization_id'] ?? 0);
        $actorId = (int) ($this->auth->user()['id'] ?? 0);

        try {
            $repo = new OrganizationsRepository($this->db);
            $repo->setPrimaryForUser($userId, $orgId);
            (new AuditLogRepository($this->db))->log($actorId, 'user_organization', $userId, 'primary_changed', [
                'user_id' => $userId,
                'organization_id' => $orgId,
            ]);
            header('Location: /config?tab=gobierno&org_primary=1#usuarios');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        } catch (\Throwable $e) {
            error_log('organizations.setPrimaryForUser: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo actualizar la organización principal.');
        }
    }

    public function unassignFromUser(int $userId): void
    {
        $this->requirePermission('pmo.organizations.manage');
        $orgId = (int) ($_POST['organization_id'] ?? 0);
        $actorId = (int) ($this->auth->user()['id'] ?? 0);

        try {
            $repo = new OrganizationsRepository($this->db);
            $removed = $repo->unassignFromUser($userId, $orgId);
            if ($removed) {
                (new AuditLogRepository($this->db))->log($actorId, 'user_organization', $userId, 'unassigned', [
                    'user_id' => $userId,
                    'organization_id' => $orgId,
                ]);
            }
            header('Location: /config?tab=gobierno&org_removed=1#usuarios');
        } catch (\Throwable $e) {
            error_log('organizations.unassignFromUser: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo retirar la organización del usuario.');
        }
    }

    private function collectPayload(): array
    {
        return [
            'name' => $_POST['name'] ?? '',
            'legal_name' => $_POST['legal_name'] ?? '',
            'tax_identifier' => $_POST['tax_identifier'] ?? '',
            'org_type' => $_POST['org_type'] ?? '',
            'description' => $_POST['description'] ?? '',
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function flashMessage(): ?string
    {
        if (!empty($_GET['saved'])) {
            return 'Organización creada correctamente.';
        }
        if (!empty($_GET['updated'])) {
            return 'Organización actualizada correctamente.';
        }
        if (!empty($_GET['inactivated'])) {
            return 'Organización inactivada correctamente.';
        }

        return null;
    }
}
