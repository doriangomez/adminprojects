<?php

declare(strict_types=1);

class ProjectsController extends Controller
{
    public function index(): void
    {
        $repo = new ProjectsRepository($this->db);
        $this->requirePermission('projects.view');
        $this->render('projects/index', [
            'title' => 'Portafolio de Proyectos',
            'projects' => $repo->summary(),
        ]);
    }
}
