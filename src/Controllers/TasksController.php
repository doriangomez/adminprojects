<?php

declare(strict_types=1);

class TasksController extends Controller
{
    public function index(): void
    {
        $repo = new TasksRepository($this->db);
        $this->requirePermission('tasks.view');
        $this->render('tasks/index', [
            'title' => 'Tareas',
            'tasks' => $repo->listAll($this->auth->user() ?? []),
        ]);
    }
}
