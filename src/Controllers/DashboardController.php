<?php

declare(strict_types=1);

class DashboardController extends Controller
{
    public function index(): void
    {
        $service = new DashboardService($this->db);
        $user = $this->auth->user() ?? [];

        $summary = $service->executiveSummary($user);
        $portfolioHealth = $service->portfolioHealthAverage($user);
        $timesheets = $service->timesheetOverview($user);
        $stoppers = $service->stoppersOverview($user);
        $executiveIntel = $service->executiveIntelligence($user);

        $portfolioAnalysis = $service->portfolioAutomaticAnalysis(
            $user, $summary, $portfolioHealth, $executiveIntel, $stoppers, $timesheets
        );

        $this->render('dashboard/index', [
            'title' => 'Dashboard Ejecutivo',
            'summary' => $summary,
            'projects' => $service->projectHealth($user),
            'portfolioHealth' => $portfolioHealth,
            'portfolioInsights' => $service->portfolioHealthInsights($user),
            'timesheets' => $timesheets,
            'outsourcing' => $service->outsourcingOverview($user),
            'governance' => $service->governanceOverview($user),
            'requirements' => $service->requirementsOverview($user),
            'alerts' => $service->alerts($user),
            'stoppers' => $stoppers,
            'executiveIntel' => $executiveIntel,
            'portfolioAnalysis' => $portfolioAnalysis,
        ]);
    }
}
