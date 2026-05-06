<?php

declare(strict_types=1);

class DashboardController extends Controller
{
    public function index(): void
    {
        $service = new DashboardService($this->db);
        $user = $this->auth->user() ?? [];
        $projectTypeFilter = strtolower(trim((string) ($_GET['project_type'] ?? '')));
        if (!in_array($projectTypeFilter, ['', 'proyecto', 'poc'], true)) {
            $projectTypeFilter = in_array($projectTypeFilter, ['convencional', 'scrum', 'hibrido', 'outsourcing'], true)
                ? 'proyecto'
                : '';
        }
        $filters = [
            'project_type' => $projectTypeFilter,
        ];

        $this->render('dashboard/index', [
            'title' => 'Dashboard Ejecutivo',
            'filters' => $filters,
            'summary' => $service->executiveSummary($user, $projectTypeFilter),
            'projects' => $service->projectHealth($user, $projectTypeFilter),
            'portfolioHealth' => $service->portfolioHealthAverage($user, $projectTypeFilter),
            'portfolioInsights' => $service->portfolioHealthInsights($user, $projectTypeFilter),
            'timesheets' => $service->timesheetOverview($user, $projectTypeFilter),
            'outsourcing' => $service->outsourcingOverview($user, $projectTypeFilter),
            'governance' => $service->governanceOverview($user, $projectTypeFilter),
            'requirements' => $service->requirementsOverview($user, $projectTypeFilter),
            'alerts' => $service->alerts($user, $projectTypeFilter),
            'stoppers' => $service->stoppersOverview($user, $projectTypeFilter),
            'executiveIntel' => $service->executiveIntelligence($user, $projectTypeFilter),
        ]);
    }
}
