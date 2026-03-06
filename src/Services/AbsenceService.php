<?php

declare(strict_types=1);

class AbsenceService
{
    public const ABSENCE_TYPES = [
        'vacaciones' => 'Vacaciones',
        'permiso' => 'Permiso',
        'permiso_personal' => 'Permiso personal',
        'permiso_medico' => 'Permiso médico',
        'incapacidad' => 'Incapacidad',
        'capacitacion' => 'Capacitación',
        'licencia' => 'Licencia',
        'festivo' => 'Festivo',
        'ausencia' => 'Ausencia',
    ];

    public const STATUSES = [
        'pendiente' => 'Pendiente',
        'aprobado' => 'Aprobado',
        'rechazado' => 'Rechazado',
    ];

    public function __construct(private Database $db)
    {
    }

    public function createAbsence(array $payload): array
    {
        $this->validateAbsencePayload($payload);

        $repo = new AbsencesRepository($this->db);
        $id = $repo->create([
            'talent_id' => (int) ($payload['talent_id'] ?? 0),
            'user_id' => !empty($payload['user_id']) ? (int) $payload['user_id'] : null,
            'absence_type' => $this->normalizeAbsenceType((string) ($payload['absence_type'] ?? 'ausencia')),
            'start_date' => $payload['start_date'] ?? '',
            'end_date' => $payload['end_date'] ?? '',
            'hours' => isset($payload['hours']) ? (float) $payload['hours'] : null,
            'is_full_day' => !empty($payload['is_full_day']),
            'status' => 'pendiente',
            'reason' => trim((string) ($payload['reason'] ?? '')),
        ]);

        return $repo->find($id) ?? [];
    }

    public function updateAbsence(int $id, array $payload): array
    {
        $repo = new AbsencesRepository($this->db);
        $existing = $repo->find($id);
        if (!$existing) {
            throw new \InvalidArgumentException('Ausencia no encontrada.');
        }

        $this->validateAbsencePayload(array_merge($existing, $payload));

        $updatePayload = [
            'talent_id' => (int) ($payload['talent_id'] ?? $existing['talent_id']),
            'absence_type' => $this->normalizeAbsenceType((string) ($payload['absence_type'] ?? $existing['absence_type'])),
            'start_date' => $payload['start_date'] ?? $existing['start_date'],
            'end_date' => $payload['end_date'] ?? $existing['end_date'],
            'hours' => array_key_exists('hours', $payload) ? ($payload['hours'] !== '' ? (float) $payload['hours'] : null) : $existing['hours'],
            'is_full_day' => array_key_exists('is_full_day', $payload) ? !empty($payload['is_full_day']) : (bool) ($existing['is_full_day'] ?? true),
            'reason' => trim((string) ($payload['reason'] ?? $existing['reason'] ?? '')),
        ];

        if ($this->canChangeStatus($existing)) {
            $updatePayload['status'] = $payload['status'] ?? $existing['status'];
        }

        $repo->update($id, $updatePayload);

        return $repo->find($id) ?? [];
    }

    public function deleteAbsence(int $id): bool
    {
        $repo = new AbsencesRepository($this->db);
        $existing = $repo->find($id);
        if (!$existing) {
            throw new \InvalidArgumentException('Ausencia no encontrada.');
        }

        return $repo->delete($id);
    }

    public function approveAbsence(int $id, int $userId): array
    {
        $repo = new AbsencesRepository($this->db);
        $existing = $repo->find($id);
        if (!$existing) {
            throw new \InvalidArgumentException('Ausencia no encontrada.');
        }

        $repo->approve($id, $userId);

        return $repo->find($id) ?? [];
    }

    public function rejectAbsence(int $id): array
    {
        $repo = new AbsencesRepository($this->db);
        $existing = $repo->find($id);
        if (!$existing) {
            throw new \InvalidArgumentException('Ausencia no encontrada.');
        }

        $repo->reject($id);

        return $repo->find($id) ?? [];
    }

    private function validateAbsencePayload(array $payload): void
    {
        $talentId = (int) ($payload['talent_id'] ?? 0);
        if ($talentId <= 0) {
            throw new \InvalidArgumentException('Selecciona un talento.');
        }

        $talentsRepo = new TalentsRepository($this->db);
        if (!$talentsRepo->find($talentId)) {
            throw new \InvalidArgumentException('Talento no encontrado.');
        }

        $startDate = trim((string) ($payload['start_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));

        if ($startDate === '' || !$this->isValidDate($startDate)) {
            throw new \InvalidArgumentException('Fecha de inicio inválida.');
        }
        if ($endDate === '' || !$this->isValidDate($endDate)) {
            throw new \InvalidArgumentException('Fecha de fin inválida.');
        }

        $start = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        if ($end < $start) {
            throw new \InvalidArgumentException('La fecha de fin debe ser posterior o igual a la de inicio.');
        }
    }

    private function isValidDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && \DateTimeImmutable::createFromFormat('Y-m-d', $date) !== false;
    }

    private function normalizeAbsenceType(string $type): string
    {
        $type = strtolower(trim($type));
        $type = strtr($type, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $type = preg_replace('/[^a-z0-9]+/', '_', $type) ?? '';
        $type = trim($type, '_');

        return array_key_exists($type, self::ABSENCE_TYPES) ? $type : ($type !== '' ? $type : 'ausencia');
    }

    private function canChangeStatus(array $absence): bool
    {
        return in_array(strtolower((string) ($absence['status'] ?? '')), ['pendiente', 'aprobado', 'rechazado'], true);
    }
}
