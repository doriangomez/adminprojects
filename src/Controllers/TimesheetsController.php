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
            'rows' => $repo->weekly(),
            'kpis' => $repo->kpis(),
        ]);
    }
}
