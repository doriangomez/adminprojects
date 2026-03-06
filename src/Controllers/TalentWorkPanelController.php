<?php

declare(strict_types=1);

use App\Repositories\ProjectStoppersRepository;
use App\Repositories\ProjectsRepository;
use App\Repositories\TasksRepository;
use App\Repositories\TimesheetsRepository;

class TalentWorkPanelController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('tasks.view');

        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $timesheetsRepo = new TimesheetsRepository($this->db);
        $tasksRepo = new TasksRepository($this->db);
        $stoppersRepo = new ProjectStoppersRepository($this->db);
        $projectsRepo = new ProjectsRepository($this->db);

        $talentId = $timesheetsRepo->talentIdForUser($userId);
        $isPmo = in_array($user['role'] ?? '', ['Administrador', 'PMO'], true);

        $weekValue = trim((string) ($_GET['week'] ?? ''));
        $weekStart = $this->parseWeekValue($weekValue) ?? new DateTimeImmutable('monday this week');
        $weekEnd = $weekStart->modify('+6 days');
        $weekValue = $weekStart->format('o-\\WW');

        $projectFilter = (int) ($_GET['project_id'] ?? 0);
        $projectFilter = $projectFilter > 0 ? $projectFilter : null;

        $timesheetsEnabled = $this->auth->isTimesheetsEnabled();
        $canReport = $this->auth->canAccessTimesheets();

        $weeklyGrid = [
            'days' => [],
            'day_totals' => [],
            'week_total' => 0,
            'weekly_capacity' => 0,
            'activities_by_day' => [],
        ];
        $weekIndicators = [
            'week_total' => 0,
            'weekly_capacity' => 40,
            'remaining_capacity' => 40,
            'compliance_percent' => 0,
        ];
        $projectsForTimesheet = [];
        $tasksForTimesheet = [];
        $activityTypes = [];

        if ($timesheetsEnabled && $canReport) {
            $weeklyGrid = $timesheetsRepo->weeklyGridForUser($userId, $weekStart);
            $weekTotal = (float) ($weeklyGrid['week_total'] ?? 0);
            $weeklyCapacity = (float) ($weeklyGrid['weekly_capacity'] ?? 40);
            $weekIndicators = [
                'week_total' => $weekTotal,
                'weekly_capacity' => $weeklyCapacity,
                'remaining_capacity' => max(0, $weeklyCapacity - $weekTotal),
                'compliance_percent' => $weeklyCapacity > 0 ? round(($weekTotal / $weeklyCapacity) * 100, 2) : 0,
            ];
            $projectsForTimesheet = $timesheetsRepo->projectsForTimesheetEntry($userId);
            $tasksForTimesheet = $timesheetsRepo->tasksForTimesheetEntry($userId);
            $activityTypes = $timesheetsRepo->activityTypesCatalog();
        }

        if ($isPmo) {
            $kanban = $tasksRepo->kanbanForPmo($user, $projectFilter);
            $workloadByTalent = $tasksRepo->workloadByTalent($user);
            $projectOptions = $projectsRepo->summary($user);
        } else {
            $kanban = $talentId !== null
                ? $tasksRepo->kanbanByTalentId($talentId)
                : ['todo' => [], 'in_progress' => [], 'review' => [], 'blocked' => [], 'done' => []];
            $workloadByTalent = [];
            $projectOptions = [];
        }

        $allTaskIds = [];
        foreach ($kanban as $columnTasks) {
            foreach ($columnTasks as $task) {
                $allTaskIds[] = (int) ($task['id'] ?? 0);
            }
        }
        $stoppersByTask = $stoppersRepo->stoppersByTaskIds($allTaskIds);

        $projectStoppers = [];
        if ($talentId !== null && !$isPmo) {
            $userProjects = $this->db->fetchAll(
                'SELECT DISTINCT p.id FROM projects p
                 JOIN project_talent_assignments a ON a.project_id = p.id
                 WHERE a.talent_id = :talentId AND (a.assignment_status = \'active\' OR (a.assignment_status IS NULL AND a.active = 1))',
                [':talentId' => $talentId]
            );
            foreach ($userProjects as $row) {
                $pid = (int) ($row['id'] ?? 0);
                if ($pid > 0) {
                    $projectStoppers[$pid] = $stoppersRepo->forProject($pid);
                }
            }
        }

        $this->render('talent_work_panel/index', [
            'title' => 'Panel de trabajo del talento',
            'kanban' => $kanban,
            'stoppersByTask' => $stoppersByTask,
            'projectStoppers' => $projectStoppers,
            'weeklyGrid' => $weeklyGrid,
            'weekIndicators' => $weekIndicators,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekValue' => $weekValue,
            'projectsForTimesheet' => $projectsForTimesheet,
            'tasksForTimesheet' => $tasksForTimesheet,
            'activityTypes' => $activityTypes,
            'timesheetsEnabled' => $timesheetsEnabled,
            'canReport' => $canReport,
            'isPmo' => $isPmo,
            'workloadByTalent' => $workloadByTalent,
            'projectOptions' => $projectOptions,
            'projectFilter' => $projectFilter,
            'canManageTasks' => $this->auth->can('projects.manage'),
            'talents' => $isPmo ? (new \App\Repositories\TalentsRepository($this->db))->assignmentOptions() : [],
        ]);
    }

    private function parseWeekValue(string $weekValue): ?DateTimeImmutable
    {
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $weekValue, $matches)) {
            return null;
        }
        $year = (int) $matches[1];
        $week = (int) $matches[2];
        if ($week < 1 || $week > 53) {
            return null;
        }
        return (new DateTimeImmutable('now'))->setISODate($year, $week, 1)->setTime(0, 0);
    }
}
