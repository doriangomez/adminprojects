<?php

declare(strict_types=1);

class ClientsController extends Controller
{
    public function index(): void
    {
        $repo = new ClientsRepository($this->db);
        $this->requirePermission('clients.view');
        $this->render('clients/index', [
            'title' => 'Clientes',
            'clients' => $repo->all(),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);
        $repo->create($_POST);
        header('Location: /clients');
    }

    public function update(): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);
        $repo->update((int) $_POST['id'], $_POST);
        header('Location: /clients');
    }

    public function destroy(): void
    {
        $this->requirePermission('clients.manage');
        $repo = new ClientsRepository($this->db);
        $repo->delete((int) $_POST['id']);
        header('Location: /clients');
    }
}
