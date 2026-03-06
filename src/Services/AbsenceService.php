<?php

declare(strict_types=1);

use App\Repositories\AbsencesRepository;

class AbsenceService
{
    private AbsencesRepository $repo;

    public function __construct(private Database $db)
    {
        $this->repo = new AbsencesRepository($db);
    }

    public function getRepository(): AbsencesRepository
    {
        return $this->repo;
    }

    public function createAbsence(array $input, int $createdByUserId, bool $autoApprove = false): int
    {
        $type = trim((string) ($input['type'] ?? ''));
        if (!array_key_exists($type, AbsencesRepository::ABSENCE_TYPES)) {
            throw new InvalidArgumentException('Tipo de ausencia inválido.');
        }

        $dateFrom = trim((string) ($input['date_from'] ?? ''));
        $dateTo   = trim((string) ($input['date_to'] ?? ''));
        if (!$this->isIsoDate($dateFrom) || !$this->isIsoDate($dateTo)) {
            throw new InvalidArgumentException('Las fechas son inválidas.');
        }
        if ($dateFrom > $dateTo) {
            throw new InvalidArgumentException('La fecha de inicio debe ser anterior o igual a la fecha de fin.');
        }

        $talentId = (int) ($input['talent_id'] ?? 0);
        if ($talentId <= 0) {
            throw new InvalidArgumentException('Talento inválido.');
        }

        $talent = $this->db->fetchOne('SELECT id, user_id FROM talents WHERE id = :id LIMIT 1', [':id' => $talentId]);
        if (!$talent) {
            throw new InvalidArgumentException('Talento no encontrado.');
        }

        $hoursPerDay = null;
        if (isset($input['hours_per_day']) && $input['hours_per_day'] !== '' && $input['hours_per_day'] !== null) {
            $hoursPerDay = max(0.5, min(24.0, (float) $input['hours_per_day']));
        }

        $status = $autoApprove ? 'aprobado' : 'pendiente';

        $id = $this->repo->create([
            'talent_id'    => $talentId,
            'user_id'      => (int) $talent['user_id'],
            'type'         => $type,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'hours_per_day' => $hoursPerDay,
            'reason'       => trim((string) ($input['reason'] ?? '')),
            'status'       => $status,
            'created_by'   => $createdByUserId,
        ]);

        return $id;
    }

    public function approve(int $id, int $approverId): void
    {
        $absence = $this->repo->findById($id);
        if (!$absence) {
            throw new InvalidArgumentException('Ausencia no encontrada.');
        }
        if ($absence['status'] === 'aprobado') {
            return;
        }
        $this->repo->approve($id, $approverId);
    }

    public function reject(int $id, int $approverId, string $reason = ''): void
    {
        $absence = $this->repo->findById($id);
        if (!$absence) {
            throw new InvalidArgumentException('Ausencia no encontrada.');
        }
        $this->repo->reject($id, $approverId, $reason);
    }

    public function delete(int $id): void
    {
        $absence = $this->repo->findById($id);
        if (!$absence) {
            throw new InvalidArgumentException('Ausencia no encontrada.');
        }
        $this->repo->delete($id);
    }

    public function talentOptions(): array
    {
        return $this->db->fetchAll(
            'SELECT t.id, t.name, t.role FROM talents t ORDER BY t.name ASC'
        );
    }

    private function isIsoDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        try {
            new DateTimeImmutable($date);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
