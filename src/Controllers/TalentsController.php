<?php

declare(strict_types=1);

class TalentsController extends Controller
{
    public function index(): void
    {
        $repo = new TalentsRepository($this->db);
        $this->requirePermission('talents.view');
        $this->render('talents/index', [
            'title' => 'Talento',
            'talents' => $repo->summary(),
        ]);
    }
}
