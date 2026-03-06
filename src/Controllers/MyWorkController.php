<?php

declare(strict_types=1);

use App\Repositories\TasksRepository;

class MyWorkController extends Controller
{
    public function index(): void
    {
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $repo = new TasksRepository($this->db);

        $talentRecord = $repo->talentRecordForUser($userId);
        $talentId = $talentRecord ? (int) $talentRecord['id'] : null;

        $weekStart = new DateTimeImmutable('monday this week');
        $weeklyHours = $talentId ? $repo->weeklyHoursForTalent($talentId, $weekStart) : 0.0;
        $todayHours = $talentId ? $repo->todayHoursForTalent($talentId) : 0.0;
        $weeklyCapacity = (float) ($talentRecord['weekly_capacity'] ?? $talentRecord['capacidad_horaria'] ?? 40);
        $activeBlockers = $talentId ? $repo->activeBlockersForTalent($talentId) : [];
        $kanbanColumns = $repo->kanbanForUser($userId);

        $taskCounts = [
            'todo' => count($kanbanColumns['todo']),
            'in_progress' => count($kanbanColumns['in_progress']),
            'review' => count($kanbanColumns['review']),
            'blocked' => count($kanbanColumns['blocked']),
            'done' => count($kanbanColumns['done']),
        ];
        $totalTasks = array_sum($taskCounts);
        $totalActiveTasks = $taskCounts['todo'] + $taskCounts['in_progress'] + $taskCounts['review'] + $taskCounts['blocked'];

        $timesheetsEnabled = $this->auth->isTimesheetsEnabled();
        $canReport = $timesheetsEnabled && $this->auth->canAccessTimesheets();

        $this->render('my_work/index', [
            'title' => 'Mi panel de trabajo',
            'talentRecord' => $talentRecord,
            'talentId' => $talentId,
            'kanbanColumns' => $kanbanColumns,
            'taskCounts' => $taskCounts,
            'totalTasks' => $totalTasks,
            'totalActiveTasks' => $totalActiveTasks,
            'weeklyHours' => $weeklyHours,
            'todayHours' => $todayHours,
            'weeklyCapacity' => $weeklyCapacity,
            'activeBlockers' => $activeBlockers,
            'canReport' => $canReport,
            'timesheetsEnabled' => $timesheetsEnabled,
            'weekStart' => $weekStart,
        ]);
    }
}
