<?php

declare(strict_types=1);

class DashboardController extends Controller
{
    public function index(): void
    {
        $service = new DashboardService($this->db);
        $user = $this->auth->user() ?? [];

        $data = ['title' => 'Dashboard Ejecutivo'];

        $data['summary'] = $this->safeDashboardCall(static fn () => $service->executiveSummary($user), [
            'proyectos_activos' => 0, 'proyectos_riesgo' => 0, 'avance_promedio' => 0,
            'horas_planificadas' => 0, 'horas_reales' => 0, 'presupuesto_total' => 0,
            'costo_real_total' => 0, 'talentos_activos' => 0, 'outsourcing_activo' => 0,
        ]);

        $data['projects'] = $this->safeDashboardCall(static fn () => $service->projectHealth($user), [
            'status_counts' => ['planning' => 0, 'en_curso' => 0, 'en_riesgo' => 0, 'cerrado' => 0],
            'progress_by_client' => [], 'stale_projects' => [], 'stale_count' => 0,
            'stage_distribution' => [], 'monthly_progress_trend' => [],
        ]);

        $data['portfolioHealth'] = $this->safeDashboardCall(
            static fn () => $service->portfolioHealthAverage($user),
            ['average_score' => 0, 'projects_count' => 0]
        );

        $data['portfolioInsights'] = $this->safeDashboardCall(
            static fn () => $service->portfolioHealthInsights($user),
            ['ranking' => [], 'top_risk' => [], 'portfolio_trend_avg' => 0, 'client_heatmap' => []]
        );

        $data['timesheets'] = $this->safeDashboardCall(static fn () => $service->timesheetOverview($user), [
            'weekly_hours' => 0, 'pending_hours' => 0, 'today_hours' => 0,
            'pending_approvals_count' => 0, 'hours_by_project' => [], 'hours_by_talent' => [],
            'talents_without_report' => 0, 'internal_talents' => 0, 'external_talents' => 0,
            'period_start' => date('Y-m-d'), 'period_end' => date('Y-m-d'),
        ]);

        $data['outsourcing'] = $this->safeDashboardCall(static fn () => $service->outsourcingOverview($user), [
            'active_services' => 0, 'open_followups' => 0, 'attention_services' => 0, 'last_followups' => [],
        ]);

        $data['governance'] = $this->safeDashboardCall(static fn () => $service->governanceOverview($user), [
            'documents_revision' => 0, 'documents_validacion' => 0, 'documents_aprobacion' => 0,
            'scope_changes_pending' => 0, 'critical_risks' => 0, 'outsourcing_overdue' => 0,
        ]);

        $data['requirements'] = $this->safeDashboardCall(
            static fn () => $service->requirementsOverview($user),
            ['filters' => [], 'projects' => [], 'ranking' => [], 'trend' => []]
        );

        $data['alerts'] = $this->safeDashboardCall(
            static fn () => $service->alerts($user),
            ['No hay alertas críticas activas en este momento.']
        );

        $data['stoppers'] = $this->safeDashboardCall(static fn () => $service->stoppersOverview($user), [
            'top_active' => [], 'monthly_trend' => [], 'critical_projects' => [],
            'severity_counts' => ['critico' => 0, 'alto' => 0, 'medio' => 0, 'bajo' => 0],
            'open_total' => 0, 'critical_total' => 0, 'avg_open_days' => 0,
        ]);

        $data['executiveIntel'] = $this->safeDashboardCall(static fn () => $service->executiveIntelligence($user), [
            'alerts' => ['stale_projects' => 0, 'high_risk_projects' => 0, 'critical_blockers' => 0, 'billing_pending' => 0],
            'movement' => ['score' => ['current' => 0, 'previous' => 0, 'delta_pct' => 0], 'risk_projects' => ['current' => 0, 'previous' => 0, 'delta_pct' => 0], 'active_blockers' => ['current' => 0, 'previous' => 0, 'delta_pct' => 0], 'billing_pending' => ['current' => 0, 'previous' => 0, 'delta_pct' => 0]],
            'financial_impact' => ['total_contracted' => 0, 'total_invoiced' => 0, 'total_collected' => 0, 'execution_pct' => 0, 'budget_deviation_pct' => 0],
            'intelligent_analysis' => ['inputs' => [], 'flags' => [], 'diagnosis' => '', 'recommendations' => [], 'criticality' => 'Baja'],
            'automatic_portfolio_analysis' => ['title' => '', 'metrics' => [], 'conclusions' => []],
            'automatic_alerts' => [], 'system_recommendations' => [],
            'portfolio_score_card' => ['score' => 0, 'status' => 'green', 'label' => 'Verde', 'factors' => [], 'methodology' => ''],
            'risk_exposure' => 0,
        ]);

        $this->render('dashboard/index', $data);
    }

    private function safeDashboardCall(callable $fn, array $fallback): array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log(sprintf('[DashboardController] Error cargando módulo del dashboard: %s', $e->getMessage()));
            return $fallback;
        }
    }
}
