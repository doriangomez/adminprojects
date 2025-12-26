<?php

declare(strict_types=1);

class DashboardController extends Controller
{
    public function index(): void
    {
        $service = new DashboardService($this->db);
        $projectsRepo = new ProjectsRepository($this->db);
        $timesheetsRepo = new TimesheetsRepository($this->db);
        $user = $this->auth->user() ?? [];

        $this->render('dashboard/index', [
            'title' => 'Dashboard Ejecutivo',
            'summary' => $service->executiveSummary($user),
            'portfolio' => $projectsRepo->aggregatedKpis($user),
            'profitability' => $service->profitability($user),
            'timesheetKpis' => $timesheetsRepo->kpis($user),
        ]);
    }
}
