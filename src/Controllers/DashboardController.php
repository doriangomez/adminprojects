<?php

declare(strict_types=1);

class DashboardController extends Controller
{
    public function index(): void
    {
        $service = new DashboardService($this->db);
        $projectsRepo = new ProjectsRepository($this->db);
        $timesheetsRepo = new TimesheetsRepository($this->db);

        $this->render('dashboard/index', [
            'title' => 'Dashboard Ejecutivo',
            'summary' => $service->executiveSummary(),
            'portfolio' => $projectsRepo->portfolioKpis(),
            'profitability' => $service->profitability(),
            'timesheetKpis' => $timesheetsRepo->kpis(),
        ]);
    }
}
