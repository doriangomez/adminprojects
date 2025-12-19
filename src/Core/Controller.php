<?php

declare(strict_types=1);

abstract class Controller
{
    public function __construct(protected Database $db, protected Auth $auth)
    {
    }

    protected function render(string $view, array $data = []): void
    {
        extract($data);
        $user = $this->auth->user();
        $title = $data['title'] ?? 'PMO';
        include __DIR__ . '/../Views/layout/header.php';
        include __DIR__ . '/../Views/' . $view . '.php';
        include __DIR__ . '/../Views/layout/footer.php';
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }

    protected function requirePermission(string $permission): void
    {
        if (!$this->auth->can($permission)) {
            http_response_code(403);
            exit('Acceso denegado');
        }
    }
}
