<?php

declare(strict_types=1);

class AbsencesController extends Controller
{
    public function index(): void
    {
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $isPrivileged = $this->isPrivileged($user);

        $service = new TalentAbsenceService($this->db);
        $workCalendar = new WorkCalendarService($this->db);

        $statusFilter = trim((string) ($_GET['status'] ?? ''));
        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));

        $absences = $isPrivileged
            ? $service->listAllAbsences(
                $statusFilter !== '' ? $statusFilter : null,
                $dateFrom !== '' ? $dateFrom : null,
                $dateTo !== '' ? $dateTo : null
            )
            : $service->listAbsences(
                $userId,
                $statusFilter !== '' ? $statusFilter : null,
                $dateFrom !== '' ? $dateFrom : null,
                $dateTo !== '' ? $dateTo : null
            );

        $weekStart = new \DateTimeImmutable('monday this week');
        $weekCalendarMap = $workCalendar->weekMap($weekStart);
        $teamCapacity = $isPrivileged
            ? $service->teamCapacityOverview($weekStart, $weekCalendarMap)
            : [];

        $talents = $this->db->fetchAll(
            'SELECT t.id, t.user_id, t.name, t.role FROM talents t JOIN users u ON u.id = t.user_id AND u.active = 1 ORDER BY t.name ASC'
        );

        $canApprove = $this->auth->can('absences.approve');
        $canManage = $this->auth->can('absences.manage') || $isPrivileged;

        $this->render('absences/index', [
            'title' => 'Gestión de Ausencias y Capacidad',
            'absences' => $absences,
            'teamCapacity' => $teamCapacity,
            'talents' => $talents,
            'canApprove' => $canApprove,
            'canManage' => $canManage,
            'isPrivileged' => $isPrivileged,
            'filters' => [
                'status' => $statusFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'absenceTypes' => TalentAbsenceService::ABSENCE_TYPES,
        ]);
    }

    public function store(): void
    {
        if (!$this->auth->can('absences.manage') && !$this->isPrivileged($this->auth->user() ?? [])) {
            $this->json(['ok' => false, 'message' => 'Sin permisos'], 403);
            return;
        }

        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $service = new TalentAbsenceService($this->db);

        $talentId = (int) ($_POST['talent_id'] ?? 0);
        $targetUserId = (int) ($_POST['user_id'] ?? 0);

        if ($talentId <= 0) {
            $talent = $this->db->fetchOne(
                'SELECT id, user_id FROM talents WHERE user_id = :uid LIMIT 1',
                [':uid' => $targetUserId > 0 ? $targetUserId : $userId]
            );
            if ($talent) {
                $talentId = (int) $talent['id'];
                $targetUserId = (int) $talent['user_id'];
            }
        }

        if ($targetUserId <= 0 && $talentId > 0) {
            $talent = $this->db->fetchOne(
                'SELECT user_id FROM talents WHERE id = :id LIMIT 1',
                [':id' => $talentId]
            );
            $targetUserId = (int) ($talent['user_id'] ?? $userId);
        }

        $autoApprove = $this->isPrivileged($user) || $this->auth->can('absences.approve');

        try {
            $id = $service->createAbsence([
                'talent_id' => $talentId,
                'user_id' => $targetUserId,
                'absence_type' => trim((string) ($_POST['absence_type'] ?? '')),
                'date_start' => trim((string) ($_POST['date_start'] ?? '')),
                'date_end' => trim((string) ($_POST['date_end'] ?? '')),
                'hours_per_day' => (float) ($_POST['hours_per_day'] ?? 0),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
                'status' => $autoApprove ? 'aprobado' : 'pendiente',
            ], $userId);

            if ($autoApprove) {
                $service->approveAbsence($id, $userId);
            }

            header('Location: /absences');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function approve(): void
    {
        if (!$this->auth->can('absences.approve') && !$this->isPrivileged($this->auth->user() ?? [])) {
            $this->json(['ok' => false, 'message' => 'Sin permisos'], 403);
            return;
        }

        $userId = (int) (($this->auth->user() ?? [])['id'] ?? 0);
        $absenceId = (int) ($_POST['absence_id'] ?? 0);
        $service = new TalentAbsenceService($this->db);

        try {
            $service->approveAbsence($absenceId, $userId);
            header('Location: /absences');
        } catch (\Throwable $e) {
            http_response_code(500);
            exit('No se pudo aprobar la ausencia.');
        }
    }

    public function reject(): void
    {
        if (!$this->auth->can('absences.approve') && !$this->isPrivileged($this->auth->user() ?? [])) {
            $this->json(['ok' => false, 'message' => 'Sin permisos'], 403);
            return;
        }

        $userId = (int) (($this->auth->user() ?? [])['id'] ?? 0);
        $absenceId = (int) ($_POST['absence_id'] ?? 0);
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $service = new TalentAbsenceService($this->db);

        try {
            $service->rejectAbsence($absenceId, $userId, $notes !== '' ? $notes : null);
            header('Location: /absences');
        } catch (\Throwable $e) {
            http_response_code(500);
            exit('No se pudo rechazar la ausencia.');
        }
    }

    public function cancel(): void
    {
        $userId = (int) (($this->auth->user() ?? [])['id'] ?? 0);
        $absenceId = (int) ($_POST['absence_id'] ?? 0);
        $service = new TalentAbsenceService($this->db);

        try {
            $service->cancelAbsence($absenceId, $userId);
            header('Location: /absences');
        } catch (\Throwable $e) {
            http_response_code(500);
            exit('No se pudo cancelar la ausencia.');
        }
    }

    public function delete(): void
    {
        if (!$this->isPrivileged($this->auth->user() ?? [])) {
            $this->json(['ok' => false, 'message' => 'Sin permisos'], 403);
            return;
        }

        $absenceId = (int) ($_POST['absence_id'] ?? 0);
        $service = new TalentAbsenceService($this->db);

        try {
            $service->deleteAbsence($absenceId);
            header('Location: /absences');
        } catch (\Throwable $e) {
            http_response_code(500);
            exit('No se pudo eliminar la ausencia.');
        }
    }

    public function capacityApi(): void
    {
        $user = $this->auth->user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        $weekValue = trim((string) ($_GET['week'] ?? ''));
        if (preg_match('/^(\d{4})-W(\d{2})$/', $weekValue, $m)) {
            $weekStart = (new \DateTimeImmutable())->setISODate((int) $m[1], (int) $m[2], 1)->setTime(0, 0);
        } else {
            $weekStart = new \DateTimeImmutable('monday this week');
        }

        $service = new TalentAbsenceService($this->db);
        $workCalendar = new WorkCalendarService($this->db);
        $weekCalendarMap = $workCalendar->weekMap($weekStart);

        $teamCapacity = $service->teamCapacityOverview($weekStart, $weekCalendarMap);

        $this->json(['ok' => true, 'data' => $teamCapacity]);
    }

    private function isPrivileged(array $user): bool
    {
        $role = trim((string) ($user['role'] ?? $user['role_name'] ?? ''));

        if (in_array($role, ['Administrador', 'PMO'], true)) {
            return true;
        }

        $roleId = (int) ($user['role_id'] ?? 0);
        if ($roleId > 0) {
            $roleName = $this->db->fetchOne('SELECT nombre FROM roles WHERE id = :id', [':id' => $roleId]);
            return in_array((string) ($roleName['nombre'] ?? ''), ['Administrador', 'PMO'], true);
        }

        return false;
    }
}
