<?php

declare(strict_types=1);

class TalentService
{
    public function __construct(private Database $db)
    {
    }

    public function createTalent(array $payload): array
    {
        $this->assertTalentPayload($payload);

        $usersRepo = new UsersRepository($this->db);
        $talentsRepo = new TalentsRepository($this->db);

        $userResolution = $this->resolveUserForTalentCreation($payload, $usersRepo, $talentsRepo);
        $talentId = $talentsRepo->create($this->talentAttributes($payload, $userResolution['user_id']));

        return [
            'talent_id' => $talentId,
            'user_id' => $userResolution['user_id'],
            'status' => $userResolution['status'],
        ];
    }

    public function updateTalent(int $talentId, array $payload): array
    {
        $this->assertTalentPayload($payload);

        $usersRepo = new UsersRepository($this->db);
        $talentsRepo = new TalentsRepository($this->db);

        $talent = $talentsRepo->find($talentId);
        if (!$talent) {
            throw new InvalidArgumentException('El talento no existe.');
        }

        $userId = (int) ($talent['user_id'] ?? 0);
        $email = trim((string) ($payload['email'] ?? ''));
        $name = (string) ($payload['name'] ?? '');

        if ($userId > 0) {
            $currentUser = $usersRepo->find($userId) ?? [];
            $usersRepo->update($userId, [
                'name' => $name !== '' ? $name : ($currentUser['name'] ?? ''),
                'email' => $email !== '' ? strtolower($email) : ($currentUser['email'] ?? ''),
                'role_id' => $this->talentRoleId(),
                'active' => $currentUser['active'] ?? 1,
                'can_review_documents' => $currentUser['can_review_documents'] ?? 0,
                'can_validate_documents' => $currentUser['can_validate_documents'] ?? 0,
                'can_approve_documents' => $currentUser['can_approve_documents'] ?? 0,
                'can_update_project_progress' => $currentUser['can_update_project_progress'] ?? 0,
                'can_access_outsourcing' => $currentUser['can_access_outsourcing'] ?? 0,
            ]);
        } elseif ($email !== '') {
            $resolution = $this->resolveUserForTalentCreation($payload, $usersRepo, $talentsRepo);
            $userId = (int) ($resolution['user_id'] ?? 0);
        }

        $talentAttributes = $this->talentAttributes($payload, $userId > 0 ? $userId : null);
        if ((int) ($talentAttributes['timesheet_approver_user_id'] ?? 0) > 0 && (int) ($talentAttributes['timesheet_approver_user_id'] ?? 0) === $userId) {
            throw new InvalidArgumentException('El jefe aprobador debe ser distinto al talento.');
        }

        $talentsRepo->update($talentId, $talentAttributes);

        return [
            'talent_id' => $talentId,
            'user_id' => $userId > 0 ? $userId : null,
        ];
    }

    private function assertTalentPayload(array $payload): void
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $role = trim((string) ($payload['role'] ?? ''));

        if ($name === '' || $role === '') {
            throw new InvalidArgumentException('El nombre y el rol del talento son obligatorios.');
        }

        $requiresReport = !empty($payload['requiere_reporte_horas']);
        $requiresApproval = !empty($payload['requiere_aprobacion_horas']);
        $approverUserId = (int) ($payload['timesheet_approver_user_id'] ?? 0);

        if ($requiresApproval && !$requiresReport) {
            throw new InvalidArgumentException('Para requerir aprobación, primero debes habilitar el reporte de horas.');
        }

        if ($requiresApproval && $approverUserId <= 0) {
            throw new InvalidArgumentException('Debes seleccionar un jefe aprobador activo cuando el talento requiere aprobación de horas.');
        }

        if ($approverUserId > 0) {
            $activeApprover = (new UsersRepository($this->db))->findActiveById($approverUserId);
            if (!$activeApprover) {
                throw new InvalidArgumentException('El jefe aprobador seleccionado no está activo en el sistema.');
            }

            $talentUserId = (int) ($payload['user_id'] ?? 0);
            if ($talentUserId > 0 && $talentUserId === $approverUserId) {
                throw new InvalidArgumentException('El jefe aprobador debe ser distinto al talento.');
            }
        }
    }

    private function resolveUserForTalentCreation(array $payload, UsersRepository $usersRepo, TalentsRepository $talentsRepo): array
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email === '') {
            return [
                'user_id' => null,
                'status' => 'talent_without_access',
            ];
        }

        $existingUser = $usersRepo->findByEmail($email);
        if ($existingUser) {
            $existingTalent = $talentsRepo->findByUserId((int) ($existingUser['id'] ?? 0));
            if ($existingTalent) {
                throw new InvalidArgumentException('El usuario ya está registrado como talento');
            }

            return [
                'user_id' => (int) $existingUser['id'],
                'status' => 'existing_user_reused',
            ];
        }

        return [
            'user_id' => $this->createTalentUser($payload, $usersRepo, $email),
            'status' => 'new_user_created',
        ];
    }

    private function createTalentUser(array $payload, UsersRepository $usersRepo, string $email): int
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $password = bin2hex(random_bytes(6));

        return $usersRepo->create([
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'role_id' => $this->talentRoleId(),
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'active' => 1,
            'can_review_documents' => 0,
            'can_validate_documents' => 0,
            'can_approve_documents' => 0,
            'can_update_project_progress' => 0,
            'can_access_outsourcing' => 0,
        ]);
    }

    private function talentRoleId(): int
    {
        $role = (new RolesRepository($this->db))->findByName('Talento');
        if (!$role) {
            throw new InvalidArgumentException('No se encontró el rol Talento.');
        }

        return (int) ($role['id'] ?? 0);
    }

    private function talentAttributes(array $payload, ?int $userId): array
    {
        $tipoTalento = $payload['tipo_talento'] ?? 'interno';
        if (!in_array($tipoTalento, ['interno', 'externo', 'otro'], true)) {
            $tipoTalento = 'interno';
        }

        $requiresReport = !empty($payload['requiere_reporte_horas']) ? 1 : 0;
        $requiresApproval = !empty($payload['requiere_aprobacion_horas']) ? 1 : 0;
        if ($requiresReport === 0) {
            $requiresApproval = 0;
        }

        return [
            'user_id' => $userId,
            'name' => trim((string) ($payload['name'] ?? '')),
            'role' => trim((string) ($payload['role'] ?? '')),
            'seniority' => trim((string) ($payload['seniority'] ?? '')),
            'capacidad_horaria' => (float) ($payload['capacidad_horaria'] ?? 0),
            'availability' => (int) ($payload['availability'] ?? 0),
            'requiere_reporte_horas' => $requiresReport,
            'requiere_aprobacion_horas' => $requiresApproval,
            'timesheet_approver_user_id' => $requiresApproval ? (int) ($payload['timesheet_approver_user_id'] ?? 0) ?: null : null,
            'tipo_talento' => $tipoTalento,
            'hourly_cost' => (float) ($payload['hourly_cost'] ?? 0),
            'hourly_rate' => (float) ($payload['hourly_rate'] ?? 0),
            'is_outsourcing' => $tipoTalento === 'externo' ? 1 : 0,
        ];
    }
}
