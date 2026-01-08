<?php

declare(strict_types=1);

class UsersController extends Controller
{
    public function index(): void
    {
        $role = strtolower(trim((string) ($_GET['role'] ?? '')));
        $repo = new UsersRepository($this->db);

        if ($role === '') {
            $this->json(['users' => []]);
            return;
        }

        $users = $repo->byDocumentRole($role);

        if (empty($users) && !in_array($role, ['reviewer', 'validator', 'approver'], true)) {
            $this->json(['message' => 'Rol inválido.', 'users' => []], 400);
            return;
        }

        $this->json(['users' => $users]);
    }
}
