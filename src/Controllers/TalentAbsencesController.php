<?php

declare(strict_types=1);

use App\Repositories\TalentAbsencesRepository;
use App\Repositories\TalentsRepository;

class TalentAbsencesController extends Controller
{
    public function index(): void
    {
        $this->ensureAbsenceViewPermission();

        $repo = new TalentAbsencesRepository($this->db);
        $editingId = (int) ($_GET['edit'] ?? 0);
        $editingAbsence = $editingId > 0 ? $repo->find($editingId) : null;
        $vacationsEnabled = (bool) (((new ConfigService($this->db))->getConfig()['operational_rules']['absences']['enable_vacations'] ?? true));

        $this->render('talent_absences/index', [
            'title' => 'Talento · Ausencias',
            'absences' => $repo->all(),
            'talents' => $repo->talentsCatalog(),
            'editingAbsence' => $editingAbsence,
            'flashMessage' => (string) ($_GET['saved'] ?? ''),
            'canCreateAbsence' => $this->auth->canCreateTalentAbsences(),
            'canEditAbsence' => $this->auth->canEditTalentAbsences(),
            'canDeleteAbsence' => $this->auth->canDeleteTalentAbsences(),
            'canApproveAbsence' => $this->auth->canApproveTalentAbsences(),
            'vacationsEnabled' => $vacationsEnabled,
        ]);
    }

    public function store(): void
    {
        $this->ensureAbsenceCreatePermission();

        try {
            $repo = new TalentAbsencesRepository($this->db);
            $payload = $this->absencePayload($_POST);
            if (!$this->auth->canApproveTalentAbsences()) {
                $payload['status'] = 'pendiente';
                $payload['approved_by'] = null;
            } else {
                $payload['approved_by'] = $payload['status'] === 'aprobado'
                    ? (int) (($this->auth->user()['id'] ?? 0) ?: 0)
                    : null;
            }
            $payload['user_id'] = (int) (($this->auth->user()['id'] ?? 0) ?: 0);

            $repo->create($payload);
            header('Location: /talent-absences?saved=created');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function update(): void
    {
        $this->ensureAbsenceEditPermission();

        $id = (int) ($_POST['id'] ?? 0);
        $repo = new TalentAbsencesRepository($this->db);
        $current = $repo->find($id);
        if (!$current) {
            http_response_code(404);
            exit('La ausencia no existe.');
        }

        try {
            $payload = $this->absencePayload($_POST);
            if (!$this->auth->canApproveTalentAbsences()) {
                $payload['status'] = (string) ($current['status'] ?? 'pendiente');
                $payload['approved_by'] = $current['approved_by'] ?? null;
            } else {
                $payload['approved_by'] = $payload['status'] === 'aprobado'
                    ? (int) (($this->auth->user()['id'] ?? 0) ?: 0)
                    : null;
            }

            $repo->update($id, $payload);
            header('Location: /talent-absences?saved=updated');
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit($e->getMessage());
        }
    }

    public function destroy(): void
    {
        $this->ensureAbsenceDeletePermission();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            exit('Ausencia inválida.');
        }

        $repo = new TalentAbsencesRepository($this->db);
        $repo->delete($id);
        header('Location: /talent-absences?saved=deleted');
    }

    public function approve(): void
    {
        $this->ensureAbsenceApprovePermission();

        $id = (int) ($_POST['id'] ?? 0);
        $status = $this->normalizeStatus((string) ($_POST['status'] ?? 'pendiente'));
        if (!in_array($status, ['aprobado', 'rechazado'], true)) {
            http_response_code(400);
            exit('Estado inválido para aprobación.');
        }

        $repo = new TalentAbsencesRepository($this->db);
        $approvedBy = $status === 'aprobado'
            ? (int) (($this->auth->user()['id'] ?? 0) ?: 0)
            : null;
        $repo->updateStatus($id, $status, $approvedBy);
        header('Location: /talent-absences?saved=approved');
    }

    private function absencePayload(array $input): array
    {
        $talentId = (int) ($input['talent_id'] ?? 0);
        if ($talentId <= 0 || !(new TalentsRepository($this->db))->find($talentId)) {
            throw new \InvalidArgumentException('Selecciona un talento válido.');
        }

        $type = $this->normalizeAbsenceType((string) ($input['absence_type'] ?? ''));
        if ($type === '') {
            throw new \InvalidArgumentException('Selecciona un tipo de ausencia.');
        }
        if ($type === 'vacaciones' && !$this->vacationsEnabled()) {
            throw new \InvalidArgumentException('La opción de vacaciones está deshabilitada en configuración.');
        }

        $startDate = trim((string) ($input['start_date'] ?? ''));
        $endDate = trim((string) ($input['end_date'] ?? ''));
        if (!$this->isIsoDate($startDate) || !$this->isIsoDate($endDate)) {
            throw new \InvalidArgumentException('Debes indicar fechas válidas (YYYY-MM-DD).');
        }
        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('La fecha de inicio no puede ser mayor que la fecha fin.');
        }

        $isFullDay = !array_key_exists('is_full_day', $input) || !empty($input['is_full_day']);
        $hours = null;
        if (!$isFullDay) {
            $hoursRaw = trim((string) ($input['hours'] ?? '0'));
            if (!is_numeric($hoursRaw)) {
                throw new \InvalidArgumentException('Las horas de ausencia parcial son inválidas.');
            }
            $hours = max(0.0, min(24.0, (float) $hoursRaw));
            if ($hours <= 0) {
                throw new \InvalidArgumentException('Para ausencia parcial, indica horas mayores a 0.');
            }
        }

        $status = $this->normalizeStatus((string) ($input['status'] ?? 'pendiente'));
        $reason = trim((string) ($input['reason'] ?? ''));

        return [
            'talent_id' => $talentId,
            'absence_type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'hours' => $hours,
            'is_full_day' => $isFullDay ? 1 : 0,
            'status' => $status,
            'reason' => $reason !== '' ? $reason : null,
            'approved_by' => null,
        ];
    }

    private function normalizeAbsenceType(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        $aliases = [
            'vacacion' => 'vacaciones',
            'vacaciones' => 'vacaciones',
            'permiso' => 'permiso_personal',
            'permiso_personal' => 'permiso_personal',
            'permiso_medico' => 'permiso_medico',
            'incapacidad' => 'incapacidad',
            'licencia' => 'licencia',
            'capacitacion' => 'capacitacion',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    private function normalizeStatus(string $status): string
    {
        $value = strtolower(trim($status));

        return match ($value) {
            'approved', 'aprobado' => 'aprobado',
            'rejected', 'rechazado' => 'rechazado',
            default => 'pendiente',
        };
    }

    private function vacationsEnabled(): bool
    {
        $config = (new ConfigService($this->db))->getConfig();
        return (bool) ($config['operational_rules']['absences']['enable_vacations'] ?? true);
    }

    private function ensureAbsenceViewPermission(): void
    {
        if (!$this->auth->isAbsencesEnabled()) {
            http_response_code(404);
            exit('El módulo de ausencias no está habilitado.');
        }

        if (!$this->auth->canViewTalentAbsences()) {
            $this->denyAccess('Tu perfil no tiene permisos para ver ausencias.');
        }
    }

    private function ensureAbsenceCreatePermission(): void
    {
        $this->ensureAbsenceViewPermission();
        if (!$this->auth->canCreateTalentAbsences()) {
            $this->denyAccess('Tu perfil no tiene permisos para crear ausencias.');
        }
    }

    private function ensureAbsenceEditPermission(): void
    {
        $this->ensureAbsenceViewPermission();
        if (!$this->auth->canEditTalentAbsences()) {
            $this->denyAccess('Tu perfil no tiene permisos para editar ausencias.');
        }
    }

    private function ensureAbsenceDeletePermission(): void
    {
        $this->ensureAbsenceViewPermission();
        if (!$this->auth->canDeleteTalentAbsences()) {
            $this->denyAccess('Tu perfil no tiene permisos para eliminar ausencias.');
        }
    }

    private function ensureAbsenceApprovePermission(): void
    {
        $this->ensureAbsenceViewPermission();
        if (!$this->auth->canApproveTalentAbsences()) {
            $this->denyAccess('Tu perfil no tiene permisos para aprobar ausencias.');
        }
    }

    private function isIsoDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        try {
            new DateTimeImmutable($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
