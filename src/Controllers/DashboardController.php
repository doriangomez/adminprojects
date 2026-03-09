<?php

declare(strict_types=1);

class DashboardController extends Controller
{
    public function index(): void
    {
        $service = new DashboardService($this->db);
        $user = $this->auth->user() ?? [];

        $safeCall = static function (callable $fn): mixed {
            try {
                return $fn();
            } catch (\Throwable $e) {
                error_log('[DashboardController] Error cargando sección del dashboard: ' . $e->getMessage());
                return [];
            }
        };

        $this->render('dashboard/index', [
            'title' => 'Dashboard Ejecutivo',
            'summary' => $safeCall(static fn () => $service->executiveSummary($user)),
            'projects' => $safeCall(static fn () => $service->projectHealth($user)),
            'portfolioHealth' => $safeCall(static fn () => $service->portfolioHealthAverage($user)),
            'portfolioInsights' => $safeCall(static fn () => $service->portfolioHealthInsights($user)),
            'timesheets' => $safeCall(static fn () => $service->timesheetOverview($user)),
            'outsourcing' => $safeCall(static fn () => $service->outsourcingOverview($user)),
            'governance' => $safeCall(static fn () => $service->governanceOverview($user)),
            'requirements' => $safeCall(static fn () => $service->requirementsOverview($user)),
            'alerts' => $safeCall(static fn () => $service->alerts($user)),
            'stoppers' => $safeCall(static fn () => $service->stoppersOverview($user)),
            'executiveIntel' => $safeCall(static fn () => $service->executiveIntelligence($user)),
        ]);
    }
}
