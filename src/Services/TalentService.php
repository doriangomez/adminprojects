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

        $userId = $this->resolveUserId($payload, $usersRepo);
        $talentId = $talentsRepo->create($this->talentAttributes($payload, $userId));

        return [
            'talent_id' => $talentId,
            'user_id' => $userId,
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
                'email' => $email !== '' ? $email : ($currentUser['email'] ?? ''),
                'role_id' => $this->talentRoleId(),
                'active' => $currentUser['active'] ?? 1,
                'can_review_documents' => $currentUser['can_review_documents'] ?? 0,
                'can_validate_documents' => $currentUser['can_validate_documents'] ?? 0,
                'can_approve_documents' => $currentUser['can_approve_documents'] ?? 0,
                'can_update_project_progress' => $currentUser['can_update_project_progress'] ?? 0,
                'can_access_outsourcing' => $currentUser['can_access_outsourcing'] ?? 0,
            ]);
        } elseif ($email !== '') {
            $userId = $this->resolveUserId($payload, $usersRepo);
        }

        $talentsRepo->update($talentId, $this->talentAttributes($payload, $userId));

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
    }

    private function resolveUserId(array $payload, UsersRepository $usersRepo): ?int
    {
        $email = trim((string) ($payload['email'] ?? ''));
        if ($email === '') {
            return null;
        }

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
        return [
            'user_id' => $userId,
            'name' => trim((string) ($payload['name'] ?? '')),
            'role' => trim((string) ($payload['role'] ?? '')),
            'seniority' => trim((string) ($payload['seniority'] ?? '')),
            'weekly_capacity' => (int) ($payload['weekly_capacity'] ?? 0),
            'availability' => (int) ($payload['availability'] ?? 0),
            'hourly_cost' => (float) ($payload['hourly_cost'] ?? 0),
            'hourly_rate' => (float) ($payload['hourly_rate'] ?? 0),
            'is_outsourcing' => !empty($payload['is_outsourcing']) ? 1 : 0,
        ];
    }
}
