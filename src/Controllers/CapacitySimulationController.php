<?php

declare(strict_types=1);

use App\Repositories\CapacitySimulationRepository;

class CapacitySimulationController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('talents.view');

        $periodFrom = trim((string) ($_GET['period_from'] ?? date('Y-m')));
        $periodTo   = trim((string) ($_GET['period_to'] ?? $periodFrom));

        if (!preg_match('/^\d{4}-\d{2}$/', $periodFrom)) {
            $periodFrom = date('Y-m');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $periodTo)) {
            $periodTo = $periodFrom;
        }
        if ($periodFrom > $periodTo) {
            [$periodFrom, $periodTo] = [$periodTo, $periodFrom];
        }

        $repo    = new CapacitySimulationRepository($this->db);
        $snapshot = $repo->currentTeamSnapshot($periodFrom, $periodTo);

        $this->render('talent_capacity/simulation', [
            'title'       => 'Simulación de Capacidad',
            'snapshot'    => $snapshot,
            'period_from' => $periodFrom,
            'period_to'   => $periodTo,
            'simulation'  => null,
            'form'        => [],
        ]);
    }

    public function simulate(): void
    {
        $this->requirePermission('talents.view');

        $periodFrom = trim((string) ($_POST['period_from'] ?? date('Y-m')));
        $periodTo   = trim((string) ($_POST['period_to'] ?? $periodFrom));

        if (!preg_match('/^\d{4}-\d{2}$/', $periodFrom)) {
            $periodFrom = date('Y-m');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $periodTo)) {
            $periodTo = $periodFrom;
        }
        if ($periodFrom > $periodTo) {
            [$periodFrom, $periodTo] = [$periodTo, $periodFrom];
        }

        $form = [
            'project_name'    => trim((string) ($_POST['project_name'] ?? '')),
            'estimated_hours' => trim((string) ($_POST['estimated_hours'] ?? '')),
            'period_from'     => $periodFrom,
            'period_to'       => $periodTo,
        ];

        $repo       = new CapacitySimulationRepository($this->db);
        $snapshot   = $repo->currentTeamSnapshot($periodFrom, $periodTo);
        $simulation = $repo->simulate([
            'project_name'    => $form['project_name'],
            'estimated_hours' => (float) $form['estimated_hours'],
            'period_from'     => $periodFrom,
            'period_to'       => $periodTo,
        ]);

        $this->render('talent_capacity/simulation', [
            'title'       => 'Simulación de Capacidad',
            'snapshot'    => $snapshot,
            'period_from' => $periodFrom,
            'period_to'   => $periodTo,
            'simulation'  => $simulation,
            'form'        => $form,
        ]);
    }
}
