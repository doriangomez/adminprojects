<?php

declare(strict_types=1);

class TimesheetsController extends Controller
{
    public function index(): void
    {
        $repo = new TimesheetsRepository($this->db);
        $this->requirePermission('timesheets.view');
        $this->render('timesheets/index', [
            'title' => 'Timesheets',
            'rows' => $repo->weekly($this->auth->user() ?? []),
            'kpis' => $repo->kpis($this->auth->user() ?? []),
        ]);
    }
}
