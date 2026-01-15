<?php

declare(strict_types=1);

class DashboardController extends Controller
{
    public function index(): void
    {
        $service = new DashboardService($this->db);
        $user = $this->auth->user() ?? [];

        $this->render('dashboard/index', [
            'title' => 'Dashboard Ejecutivo',
            'summary' => $service->executiveSummary($user),
            'projects' => $service->projectHealth($user),
            'timesheets' => $service->timesheetOverview($user),
            'outsourcing' => $service->outsourcingOverview($user),
            'governance' => $service->governanceOverview($user),
            'alerts' => $service->alerts($user),
        ]);
    }
}
