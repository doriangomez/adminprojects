<?php

declare(strict_types=1);

use App\Repositories\AdminTimesheetsRepository;

class AdminTimesheetsController extends Controller
{
    public function index(): void
    {
        if (!$this->isAdminOrPmo()) {
            $this->denyAccess('Solo PMO y Administradores pueden acceder a esta vista.');
        }

        $repo = new AdminTimesheetsRepository($this->db);

        $weekValue = trim((string) ($_GET['week'] ?? ''));
        $weekStart = $this->parseWeekValue($weekValue);
        $weekEnd = $weekStart ? $weekStart->modify('+6 days') : null;
        $weekValue = $weekStart ? $weekStart->format('o-\\WW') : '';

        $filters = [
            'user_id' => ((int) ($_GET['user_id'] ?? 0)) ?: null,
            'project_id' => ((int) ($_GET['project_id'] ?? 0)) ?: null,
            'client_id' => ((int) ($_GET['client_id'] ?? 0)) ?: null,
            'status' => trim((string) ($_GET['status'] ?? '')) ?: null,
            'week_start' => $weekStart ? $weekStart->format('Y-m-d') : null,
            'week_end' => $weekEnd ? $weekEnd->format('Y-m-d') : null,
        ];

        $viewTab = trim((string) ($_GET['tab'] ?? 'entries'));
        if (!in_array($viewTab, ['entries', 'by_user', 'by_project', 'by_client'], true)) {
            $viewTab = 'entries';
        }

        $entries = $repo->adminTimesheetEntries($filters);
        $summaryByUser = $repo->summaryByUser($filters);
        $summaryByProject = $repo->summaryByProject($filters);
        $summaryByClient = $repo->summaryByClient($filters);
        $globalStats = $repo->globalStats($filters);

        $allUsers = $repo->allUsers();
        $allProjects = $repo->allProjects();
        $allClients = $repo->allClients();

        $this->render('admin/timesheets', [
            'title' => 'Timesheets · Vista Administrativa',
            'entries' => $entries,
            'summaryByUser' => $summaryByUser,
            'summaryByProject' => $summaryByProject,
            'summaryByClient' => $summaryByClient,
            'globalStats' => $globalStats,
            'filters' => $filters,
            'weekValue' => $weekValue,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'viewTab' => $viewTab,
            'allUsers' => $allUsers,
            'allProjects' => $allProjects,
            'allClients' => $allClients,
        ]);
    }

    private function isAdminOrPmo(): bool
    {
        return $this->auth->hasRole('Administrador') || $this->auth->hasRole('PMO');
    }

    private function parseWeekValue(string $weekValue): ?\DateTimeImmutable
    {
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $weekValue, $matches)) {
            return null;
        }

        $year = (int) $matches[1];
        $week = (int) $matches[2];
        if ($week < 1 || $week > 53) {
            return null;
        }

        return (new \DateTimeImmutable('now'))->setISODate($year, $week, 1)->setTime(0, 0);
    }
}
