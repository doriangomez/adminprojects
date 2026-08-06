<?php

declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\TechnologiesRepository;

class TechnologiesController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('pmo.technologies.manage');
        $repo = new TechnologiesRepository($this->db);

        $this->render('technologies/index', [
            'title' => 'Tecnologías',
            'technologies' => $repo->all(),
            'canManage' => true,
            'flash' => $this->flashMessage(),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('pmo.technologies.manage');

        $this->render('technologies/form', [
            'title' => 'Nueva tecnología',
            'technology' => null,
            'mode' => 'create',
            'error' => null,
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('pmo.technologies.manage');
        $repo = new TechnologiesRepository($this->db);
        $userId = (int) ($this->auth->user()['id'] ?? 0);

        try {
            $payload = $this->collectPayload();
            $id = $repo->create($payload);
            (new AuditLogRepository($this->db))->log($userId, 'technology', $id, 'created', $payload);
            header('Location: /technologies?saved=1');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('technologies/form', [
                'title' => 'Nueva tecnología',
                'technology' => $payload ?? $_POST,
                'mode' => 'create',
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            error_log('technologies.store: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo crear la tecnología.');
        }
    }

    public function edit(int $id): void
    {
        $this->requirePermission('pmo.technologies.manage');
        $repo = new TechnologiesRepository($this->db);
        $technology = $repo->find($id);
        if ($technology === null) {
            http_response_code(404);
            exit('Tecnología no encontrada');
        }

        $this->render('technologies/form', [
            'title' => 'Editar tecnología',
            'technology' => $technology,
            'mode' => 'edit',
            'error' => null,
            'linkCounts' => $repo->linkCounts($id),
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('pmo.technologies.manage');
        $repo = new TechnologiesRepository($this->db);
        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $before = $repo->find($id);
        if ($before === null) {
            http_response_code(404);
            exit('Tecnología no encontrada');
        }

        try {
            $payload = $this->collectPayload();
            $repo->update($id, $payload);
            (new AuditLogRepository($this->db))->log($userId, 'technology', $id, 'updated', [
                'before' => $before,
                'after' => $payload,
            ]);
            header('Location: /technologies?updated=1');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            $this->render('technologies/form', [
                'title' => 'Editar tecnología',
                'technology' => array_merge($before, $payload ?? $_POST),
                'mode' => 'edit',
                'error' => $e->getMessage(),
                'linkCounts' => $repo->linkCounts($id),
            ]);
        } catch (\Throwable $e) {
            error_log('technologies.update: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo actualizar la tecnología.');
        }
    }

    public function inactivate(int $id): void
    {
        $this->requirePermission('pmo.technologies.manage');
        $repo = new TechnologiesRepository($this->db);
        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $before = $repo->find($id);
        if ($before === null) {
            http_response_code(404);
            exit('Tecnología no encontrada');
        }

        try {
            $repo->inactivate($id);
            (new AuditLogRepository($this->db))->log($userId, 'technology', $id, 'inactivated', [
                'name' => $before['name'] ?? null,
                'category' => $before['category'] ?? null,
            ]);
            header('Location: /technologies?inactivated=1');
        } catch (\Throwable $e) {
            error_log('technologies.inactivate: ' . $e->getMessage());
            http_response_code(500);
            exit('No se pudo inactivar la tecnología.');
        }
    }

    private function collectPayload(): array
    {
        return [
            'name' => $_POST['name'] ?? '',
            'category' => $_POST['category'] ?? '',
            'description' => $_POST['description'] ?? '',
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function flashMessage(): ?string
    {
        if (!empty($_GET['saved'])) {
            return 'Tecnología creada correctamente.';
        }
        if (!empty($_GET['updated'])) {
            return 'Tecnología actualizada correctamente.';
        }
        if (!empty($_GET['inactivated'])) {
            return 'Tecnología inactivada correctamente.';
        }

        return null;
    }
}
