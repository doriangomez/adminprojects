<?php

declare(strict_types=1);

class DecisionCenterController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('pmo_decision_center_view');

        $filters = $this->filtersFromRequest();
        $user = $this->auth->user() ?? [];
        $service = new DecisionCenterService($this->db);
        $serviceFilters = array_merge($filters, ['__user' => $user]);

        $portfolioSummary = $this->safeSection(
            fn (): array => $service->getPortfolioSummary($serviceFilters),
            [
                'portfolio_score' => 0,
                'active_projects' => 0,
                'at_risk_projects' => 0,
                'active_blockers' => 0,
                'billing_pending' => 0,
                'billing_currency' => 'USD',
                'avg_team_utilization' => 0,
                'delta_vs_previous' => 0,
                'risk_threshold' => 70,
                'error' => 'No se pudo calcular Estado del portafolio.',
            ],
            'No se pudo calcular estado del portafolio'
        );

        $alerts = $this->safeSection(
            fn (): array => $service->getAlerts($serviceFilters),
            [],
            'No se pudo calcular alertas del decision center'
        );
        if ($alerts === []) {
            $alerts = [['key' => 'none', 'label' => 'No se pudo calcular alertas.', 'count' => 0]];
        }

        $recommendations = $this->safeSection(
            fn (): array => $service->getRecommendations($serviceFilters),
            [],
            'No se pudo calcular recomendaciones del decision center'
        );

        $projectRanking = $this->safeSection(
            fn (): array => $service->getProjectRanking($serviceFilters),
            [],
            'No se pudo calcular ranking de proyectos'
        );

        $teamCapacity = $this->safeSection(
            fn (): array => $service->getTeamCapacity($serviceFilters),
            ['rows' => [], 'talents_without_report' => 0],
            'No se pudo calcular capacidad de equipo'
        );

        $canUseAi = $this->auth->can('pmo_decision_center_ai');
        $forceRefreshAi = (string) ($_GET['refresh_ai'] ?? '') === '1';
        $intelligentAnalysis = null;
        if ($canUseAi) {
            $intelligentAnalysis = $this->safeSection(
                fn (): array => $service->getIntelligentAnalysis($serviceFilters, $forceRefreshAi),
                ['text' => 'No se pudo generar el análisis inteligente.', 'generated_at' => date('c'), 'cached' => false],
                'No se pudo generar análisis inteligente del decision center'
            );
        }

        $this->render('pmo/decision_center', [
            'title' => 'Centro de Decisiones PMO',
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'portfolioSummary' => $portfolioSummary,
            'alerts' => $alerts,
            'recommendations' => $recommendations,
            'projectRanking' => $projectRanking,
            'teamCapacity' => $teamCapacity,
            'canUseAi' => $canUseAi,
            'intelligentAnalysis' => $intelligentAnalysis,
        ]);
    }

    public function summaryApi(): void
    {
        $this->requirePermission('pmo_decision_center_view');
        $filters = $this->filtersFromRequest();
        $user = $this->auth->user() ?? [];
        $service = new DecisionCenterService($this->db);
        $serviceFilters = array_merge($filters, ['__user' => $user]);

        try {
            $payload = [
                'filters' => $filters,
                'summary' => $service->getPortfolioSummary($serviceFilters),
                'alerts' => $service->getAlerts($serviceFilters),
                'recommendations' => $service->getRecommendations($serviceFilters),
                'project_ranking' => $service->getProjectRanking($serviceFilters),
                'team_capacity' => $service->getTeamCapacity($serviceFilters),
            ];
            if ($this->auth->can('pmo_decision_center_ai')) {
                $payload['intelligent_analysis'] = $service->getIntelligentAnalysis($serviceFilters);
            }
            $this->json($payload);
        } catch (\Throwable $e) {
            error_log('Error API decision center summary: ' . $e->getMessage());
            $this->json([
                'message' => 'No se pudo calcular el Centro de Decisiones.',
            ], 500);
        }
    }

    public function simulateApi(): void
    {
        $this->requirePermission('pmo_decision_center_view');
        $user = $this->auth->user() ?? [];
        $service = new DecisionCenterService($this->db);

        $payload = $this->parsedJsonBody();
        if ($payload === []) {
            $payload = $_POST;
        }
        $payload['__user'] = $user;

        try {
            $data = $service->simulateCapacity($payload);
            $this->json(['data' => $data]);
        } catch (\Throwable $e) {
            error_log('Error API decision center simulation: ' . $e->getMessage());
            $this->json([
                'message' => 'No se pudo simular capacidad.',
            ], 500);
        }
    }

    private function filtersFromRequest(): array
    {
        return [
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
            'client_id' => (int) ($_GET['client_id'] ?? 0),
            'pm_id' => (int) ($_GET['pm_id'] ?? 0),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'alert' => trim((string) ($_GET['alert'] ?? '')),
            'area' => trim((string) ($_GET['area'] ?? '')),
            'role' => trim((string) ($_GET['role'] ?? '')),
        ];
    }

    private function filterOptions(): array
    {
        $clients = [];
        if ($this->db->tableExists('clients')) {
            $clients = $this->db->fetchAll(
                'SELECT id, name
                 FROM clients
                 ORDER BY name ASC'
            );
        }

        $projectStatuses = [];
        if ($this->db->tableExists('projects')) {
            if ($this->db->columnExists('projects', 'status_code')) {
                $projectStatuses = $this->db->fetchAll(
                    'SELECT DISTINCT status_code AS code
                     FROM projects
                     WHERE status_code IS NOT NULL AND status_code <> ""
                     ORDER BY status_code ASC'
                );
            } elseif ($this->db->columnExists('projects', 'status')) {
                $projectStatuses = $this->db->fetchAll(
                    'SELECT DISTINCT status AS code
                     FROM projects
                     WHERE status IS NOT NULL AND status <> ""
                     ORDER BY status ASC'
                );
            }
        }

        $pms = [];
        if ($this->db->tableExists('users') && $this->db->tableExists('roles')) {
            $pms = $this->db->fetchAll(
                'SELECT u.id, u.name
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE u.active = 1
                   AND r.nombre IN ("PMO", "Líder de Proyecto", "Administrador")
                 ORDER BY u.name ASC'
            );
        }

        $areas = [];
        if ($this->db->tableExists('clients') && $this->db->columnExists('clients', 'area_code')) {
            $areas = $this->db->fetchAll(
                'SELECT DISTINCT area_code
                 FROM clients
                 WHERE area_code IS NOT NULL AND area_code <> ""
                 ORDER BY area_code ASC'
            );
        }

        $roles = [];
        if ($this->db->tableExists('talents')) {
            $roles = $this->db->fetchAll(
                'SELECT DISTINCT role
                 FROM talents
                 WHERE role IS NOT NULL AND role <> ""
                 ORDER BY role ASC'
            );
        }

        return [
            'clients' => $clients,
            'pms' => $pms,
            'statuses' => $projectStatuses,
            'areas' => $areas,
            'roles' => $roles,
        ];
    }

    private function parsedJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function safeSection(callable $callback, array $fallback, string $logMessage): array
    {
        try {
            $result = $callback();
            return is_array($result) ? $result : $fallback;
        } catch (\Throwable $e) {
            error_log($logMessage . ': ' . $e->getMessage());
            return $fallback;
        }
    }
}
