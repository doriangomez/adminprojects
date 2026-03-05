<?php

declare(strict_types=1);

namespace App\Services;

class TalentCapacityInsightsService
{
    /**
     * Generate all analytical insights from the talent capacity dashboard data.
     * Returns structured interpretations for each dimension of the team's workload.
     */
    public function generate(array $dashboard): array
    {
        $summary  = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $talents  = is_array($dashboard['talents'] ?? null) ? $dashboard['talents'] : [];

        $avgUtil          = (float) ($summary['avg_team_utilization'] ?? 0);
        $idleCapacity     = (float) ($summary['idle_capacity'] ?? 0);
        $overassigned     = (float) ($summary['overassigned_hours'] ?? 0);
        $riskTalents      = (int)   ($summary['risk_talents'] ?? 0);
        $teamCount        = count($talents);

        $talentStats   = $this->computeTalentStats($talents);
        $weeklyBuckets = $this->computeWeeklyBuckets($talents);

        $totalCapacity = (float) array_sum(array_column($talentStats, 'capacity'));
        $totalHours    = (float) array_sum(array_column($talentStats, 'hours'));

        return [
            'team_utilization'  => $this->teamUtilizationInsight($avgUtil, $teamCount, $riskTalents),
            'peak_weeks'        => $this->peakWeeksInsight($weeklyBuckets),
            'top_utilized'      => $this->topUtilizedInsight($talentStats),
            'available_talents' => $this->availableTalentsInsight($talentStats, $teamCount),
            'free_capacity'     => $this->freeCapacityInsight($idleCapacity, $overassigned, $totalCapacity, $totalHours),
            'diagnosis'         => $this->overallDiagnosis($avgUtil, $riskTalents, $teamCount, $overassigned, $totalCapacity),
        ];
    }

    // -------------------------------------------------------------------------
    // Private: data aggregators
    // -------------------------------------------------------------------------

    /**
     * Build a flat stats array from the latest monthly snapshot of each talent.
     */
    private function computeTalentStats(array $talents): array
    {
        $stats = [];
        foreach ($talents as $talent) {
            $monthly = is_array($talent['monthly'] ?? null) ? $talent['monthly'] : [];
            $latest  = !empty($monthly) ? end($monthly) : ['hours' => 0, 'capacity' => 0, 'utilization' => 0];
            $hours    = (float) ($latest['hours']    ?? 0);
            $capacity = (float) ($latest['capacity'] ?? 0);
            $stats[]  = [
                'name'        => (string) ($talent['name'] ?? ''),
                'utilization' => (float) ($latest['utilization'] ?? 0),
                'hours'       => $hours,
                'capacity'    => $capacity,
                'available'   => max(0.0, $capacity - $hours),
                'overload'    => max(0.0, $hours - $capacity),
            ];
        }

        return $stats;
    }

    /**
     * Aggregate weekly utilization across all talents into team-level buckets.
     */
    private function computeWeeklyBuckets(array $talents): array
    {
        $raw = [];
        foreach ($talents as $talent) {
            $weekly = is_array($talent['weekly'] ?? null) ? $talent['weekly'] : [];
            foreach ($weekly as $weekKey => $bucket) {
                $raw[$weekKey]['utilization_sum'] = ($raw[$weekKey]['utilization_sum'] ?? 0.0) + (float) ($bucket['utilization'] ?? 0);
                $raw[$weekKey]['count']           = ($raw[$weekKey]['count'] ?? 0) + 1;
            }
        }

        $result = [];
        foreach ($raw as $weekKey => $bucket) {
            $count        = (int) ($bucket['count'] ?? 0);
            $avg          = $count > 0 ? ((float) ($bucket['utilization_sum'] ?? 0) / $count) : 0.0;
            $result[$weekKey] = ['week' => (string) $weekKey, 'utilization' => round($avg, 1)];
        }

        uasort($result, static fn (array $a, array $b): int => $b['utilization'] <=> $a['utilization']);

        return array_values($result);
    }

    // -------------------------------------------------------------------------
    // Private: insight builders
    // -------------------------------------------------------------------------

    private function teamUtilizationInsight(float $avgUtil, int $teamCount, int $riskTalents): array
    {
        $status = $this->utilizationStatus($avgUtil);

        if ($avgUtil <= 0) {
            $label          = 'Sin carga registrada';
            $interpretation = 'El equipo no tiene carga asignada en el período seleccionado. '
                . 'Puede que no existan asignaciones activas o el rango de fechas no contenga datos.';
            $recommendation = 'Verifique que las asignaciones estén configuradas correctamente y que el rango de fechas sea el adecuado.';
        } elseif ($avgUtil < 60) {
            $label          = 'Equipo subutilizado';
            $interpretation = "El equipo opera al {$avgUtil}% de su capacidad, por debajo del umbral óptimo del 60%. "
                . 'Existe una brecha significativa entre la capacidad disponible y la carga actual.';
            $recommendation = 'Evalúe la incorporación de nuevas iniciativas para aprovechar la capacidad ociosa. '
                . 'Es un buen momento para asignar trabajo de deuda técnica o capacitaciones.';
        } elseif ($avgUtil < 70) {
            $label          = 'Zona de estabilidad';
            $interpretation = "El equipo opera al {$avgUtil}% de su capacidad, dentro de la zona de balance. "
                . 'Hay margen para absorber nuevas asignaciones sin riesgo de saturación.';
            $recommendation = 'Zona ideal para planificar nuevas incorporaciones de alcance. '
                . 'Se puede asumir trabajo adicional de forma controlada.';
        } elseif ($avgUtil < 90) {
            $riskNote       = $riskTalents > 0
                ? " Sin embargo, {$riskTalents} talento(s) supera(n) el 90% individualmente."
                : '';
            $label          = 'Zona saludable';
            $interpretation = "El equipo opera al {$avgUtil}% de su capacidad, dentro del rango óptimo. "
                . "La productividad es alta y el equipo mantiene margen de maniobra.{$riskNote}";
            $recommendation = $riskTalents > 0
                ? 'Atienda los talentos con sobrecarga individual antes de sumar nuevas asignaciones al equipo.'
                : 'Mantenga la distribución actual y monitoree preventivamente ante nuevas solicitudes.';
        } elseif ($avgUtil <= 100) {
            $riskNote       = $riskTalents > 0 ? " {$riskTalents} talento(s) ya superan el umbral crítico." : '';
            $label          = 'Equipo al límite';
            $interpretation = "El equipo promedia un {$avgUtil}% de utilización, operando al borde de su capacidad. "
                . "Cualquier nueva asignación puede desestabilizar la operación.{$riskNote}";
            $recommendation = 'Evite nuevas asignaciones sin antes redistribuir o desafectar carga existente. '
                . 'Revise prioridades con el cliente y el equipo de gestión.';
        } else {
            $label          = 'Equipo sobrecargado';
            $interpretation = "El equipo promedia un {$avgUtil}% de utilización, superando su capacidad máxima. "
                . "El riesgo de burnout y retrasos es alto. {$riskTalents} talento(s) están en zona crítica.";
            $recommendation = 'Acción inmediata requerida: redistribuir carga, diferir entregables no críticos '
                . 'o incorporar recursos adicionales. Escale la situación a la gerencia.';
        }

        return [
            'status'         => $status,
            'value'          => $avgUtil,
            'label'          => $label,
            'interpretation' => $interpretation,
            'recommendation' => $recommendation,
            'team_count'     => $teamCount,
        ];
    }

    private function peakWeeksInsight(array $weeklyBuckets): array
    {
        $criticalWeeks = array_filter($weeklyBuckets, static fn (array $w): bool => (float) ($w['utilization'] ?? 0) >= 90);
        $highWeeks     = array_filter($weeklyBuckets, static fn (array $w): bool => (float) ($w['utilization'] ?? 0) >= 70);
        $topWeeks      = array_slice($weeklyBuckets, 0, 5);

        $criticalCount = count($criticalWeeks);
        $highCount     = count($highWeeks);

        if (empty($weeklyBuckets)) {
            $interpretation = 'No hay datos semanales en el rango seleccionado.';
            $recommendation = 'Amplíe el rango de fechas para obtener información semanal.';
        } elseif ($criticalCount === 0) {
            $interpretation = 'No se detectaron semanas con carga crítica (≥90%) en el período. '
                . 'El flujo de trabajo se distribuye de forma regular.';
            $recommendation = 'El ritmo semanal es estable. Monitoree preventivamente ante incorporación de nuevos proyectos.';
        } elseif ($criticalCount <= 2) {
            $interpretation = "Se identificaron {$criticalCount} semana(s) con carga promedio ≥90%. "
                . 'Los picos son acotados y manejables con planificación anticipada.';
            $recommendation = 'Prevenga estos picos redistribuyendo tareas hacia semanas con menor carga. '
                . 'Comunique con anticipación al equipo afectado.';
        } elseif ($criticalCount <= 4) {
            $interpretation = "El período muestra {$criticalCount} semanas críticas. "
                . 'Hay un patrón recurrente de alta presión que puede afectar la calidad y el bienestar del equipo.';
            $recommendation = 'Revise la planificación de entregas para espaciar los picos. '
                . 'Considere incorporar recursos o renegociar fechas con los clientes.';
        } else {
            $interpretation = "Se detectaron {$criticalCount} semanas con sobrecarga crítica. "
                . 'El equipo enfrenta una presión sostenida que requiere intervención estructural.';
            $recommendation = 'Acción urgente: reestructure la planificación del portafolio, redistribuya la carga '
                . 'entre talentos y evalúe la incorporación de recursos externos.';
        }

        return [
            'top_weeks'      => $topWeeks,
            'critical_count' => $criticalCount,
            'high_count'     => $highCount,
            'interpretation' => $interpretation,
            'recommendation' => $recommendation,
        ];
    }

    private function topUtilizedInsight(array $talentStats): array
    {
        $sorted = $talentStats;
        usort($sorted, static fn (array $a, array $b): int => $b['utilization'] <=> $a['utilization']);
        $top = array_slice($sorted, 0, 5);

        $overloadedCount = count(array_filter($talentStats, static fn (array $t): bool => $t['utilization'] > 100));
        $criticalCount   = count(array_filter($talentStats, static fn (array $t): bool => $t['utilization'] >= 90 && $t['utilization'] <= 100));

        if (empty($talentStats)) {
            $interpretation = 'No hay talentos registrados en el período.';
            $recommendation = 'Asegúrese de que existan talentos activos con asignaciones en el rango seleccionado.';
        } elseif ($overloadedCount > 0) {
            $also           = $criticalCount > 0 ? " Además, {$criticalCount} talento(s) opera(n) en zona de riesgo (90–100%)." : '';
            $interpretation = "{$overloadedCount} talento(s) supera(n) el 100% de su capacidad.{$also} "
                . 'La concentración de carga en pocas personas es un riesgo operativo.';
            $recommendation = 'Distribuya inmediatamente la carga de los talentos sobrecargados. '
                . 'Identifique tareas transferibles y asígnelas a talentos con disponibilidad.';
        } elseif ($criticalCount > 0) {
            $interpretation = "{$criticalCount} talento(s) opera(n) entre el 90% y 100% de su capacidad. "
                . 'Están al límite y son vulnerables ante cualquier incremento de carga.';
            $recommendation = 'Monitoree semanalmente a estos talentos y evite nuevas asignaciones. '
                . 'Prepare un plan de contingencia si alguno se ausenta.';
        } elseif (!empty($top) && (float) ($top[0]['utilization'] ?? 0) >= 70) {
            $interpretation = 'Los talentos más cargados operan en zona saludable o moderada. '
                . 'No hay señales de alerta inmediata.';
            $recommendation = 'Mantenga el equilibrio actual. En futuras asignaciones, priorice talentos con '
                . 'menor utilización para mantener la distribución.';
        } else {
            $interpretation = 'El equipo tiene una carga general baja. Ningún talento está en zona de presión.';
            $recommendation = 'Aproveche la disponibilidad para asignar trabajo estratégico, capacitaciones o proyectos internos.';
        }

        return [
            'top_talents'     => $top,
            'overloaded_count' => $overloadedCount,
            'critical_count'  => $criticalCount,
            'interpretation'  => $interpretation,
            'recommendation'  => $recommendation,
        ];
    }

    private function availableTalentsInsight(array $talentStats, int $teamCount): array
    {
        $available = array_values(array_filter($talentStats, static fn (array $t): bool => $t['available'] > 0));
        usort($available, static fn (array $a, array $b): int => $b['available'] <=> $a['available']);

        $totalFreeHours  = round((float) array_sum(array_column($available, 'available')), 1);
        $availableCount  = count($available);

        if ($availableCount === 0) {
            $interpretation = 'No hay talentos con capacidad disponible en el período. El equipo está completamente asignado.';
            $recommendation = 'Para nuevas asignaciones, revise y renegocie la carga actual o incorpore nuevos recursos.';
        } elseif ($availableCount === 1) {
            $interpretation = "Solo 1 talento tiene horas disponibles, con un total de {$totalFreeHours}h libres. "
                . 'La disponibilidad del equipo es muy limitada.';
            $recommendation = 'Use esta capacidad con criterio para tareas críticas o urgentes únicamente.';
        } elseif ($totalFreeHours < 20) {
            $interpretation = "{$availableCount} talentos tienen disponibilidad, pero la capacidad total libre es muy baja "
                . "({$totalFreeHours}h). Solo apta para asignaciones menores.";
            $recommendation = 'Priorice estrictamente qué trabajo absorbe esta capacidad residual.';
        } elseif ($availableCount <= 3) {
            $interpretation = "{$availableCount} talentos tienen capacidad disponible con un total de "
                . "{$totalFreeHours}h libres en el período.";
            $recommendation = 'Canalice nuevas asignaciones hacia estos talentos, priorizando por horas disponibles y perfil requerido.';
        } else {
            $interpretation = "{$availableCount} de {$teamCount} talentos tienen capacidad libre, sumando "
                . "{$totalFreeHours}h disponibles. El equipo tiene buena absorción para nuevas iniciativas.";
            $recommendation = 'El equipo puede asumir nuevos proyectos. Distribuya asignaciones de forma equitativa '
                . 'para mantener el balance.';
        }

        return [
            'available_talents' => array_slice($available, 0, 8),
            'available_count'   => $availableCount,
            'total_free_hours'  => $totalFreeHours,
            'interpretation'    => $interpretation,
            'recommendation'    => $recommendation,
        ];
    }

    private function freeCapacityInsight(float $idleCapacity, float $overassigned, float $totalCapacity, float $totalHours): array
    {
        $freePercent = $totalCapacity > 0 ? round(($idleCapacity / $totalCapacity) * 100, 1) : 0.0;
        $overPercent = $totalCapacity > 0 ? round(($overassigned  / $totalCapacity) * 100, 1) : 0.0;

        if ($totalCapacity <= 0) {
            $interpretation = 'No hay datos de capacidad suficientes para calcular la capacidad libre del equipo.';
            $recommendation = 'Verifique que los talentos tengan configurada su capacidad horaria semanal.';
        } elseif ($idleCapacity <= 0 && $overassigned > 0) {
            $interpretation = "El equipo no tiene capacidad libre: hay " . round($overassigned, 1) . "h sobreasignadas "
                . "({$overPercent}% sobre la capacidad). El equipo opera con déficit de recursos.";
            $recommendation = 'Revise urgentemente la planificación. Redistribuya carga, difiera entregas '
                . 'o incorpore recursos adicionales.';
        } elseif ($freePercent < 10) {
            $interpretation = "La capacidad libre es mínima: solo " . round($idleCapacity, 1) . "h ({$freePercent}% del total). "
                . 'El equipo opera cerca de su límite máximo.';
            $recommendation = 'No asuma nuevas iniciativas sin antes liberar capacidad o incorporar recursos. '
                . 'Reserve esta capacidad para imprevistos.';
        } elseif ($freePercent < 25) {
            $interpretation = "Hay " . round($idleCapacity, 1) . "h libres ({$freePercent}% de la capacidad total). "
                . 'Existe un margen operativo moderado.';
            $recommendation = 'Esta capacidad es suficiente para absorber imprevistos o tareas menores. '
                . 'Use con criterio antes de nuevas asignaciones grandes.';
        } elseif ($freePercent < 50) {
            $interpretation = "El equipo dispone de " . round($idleCapacity, 1) . "h libres ({$freePercent}% de capacidad). "
                . 'Es una reserva saludable que permite planificar con confianza.';
            $recommendation = 'Buen momento para planificar nuevas iniciativas. Distribuya la carga de forma '
                . 'que no supere el 80% en ningún talento.';
        } else {
            $interpretation = "El equipo tiene " . round($idleCapacity, 1) . "h libres ({$freePercent}% del total). "
                . 'La subutilización es considerable y representa un costo de oportunidad.';
            $recommendation = 'Revise si el pipeline de proyectos está activo. Aproveche esta capacidad para '
                . 'proyectos internos, mejoras o capacitaciones antes de que se pierda.';
        }

        return [
            'idle_hours'         => round($idleCapacity, 1),
            'overassigned_hours' => round($overassigned, 1),
            'free_percent'       => $freePercent,
            'total_capacity'     => round($totalCapacity, 1),
            'interpretation'     => $interpretation,
            'recommendation'     => $recommendation,
        ];
    }

    private function overallDiagnosis(float $avgUtil, int $riskTalents, int $teamCount, float $overassigned, float $totalCapacity): array
    {
        $overPercent = $totalCapacity > 0 ? (($overassigned / $totalCapacity) * 100) : 0.0;

        $level = match (true) {
            $avgUtil > 100 || $overPercent > 15          => 'critical',
            $avgUtil >= 90 || $riskTalents > max(1, (int) round($teamCount * 0.3)) => 'warning',
            $avgUtil >= 70 && $avgUtil < 90              => 'healthy',
            $avgUtil >= 60                               => 'balanced',
            default                                      => 'under',
        };

        $labels = [
            'critical' => 'Estado crítico',
            'warning'  => 'Atención requerida',
            'healthy'  => 'Operación saludable',
            'balanced' => 'Zona de balance',
            'under'    => 'Equipo subutilizado',
        ];

        return [
            'level' => $level,
            'label' => $labels[$level] ?? 'Sin diagnóstico',
        ];
    }

    private function utilizationStatus(float $utilization): string
    {
        if ($utilization <= 0) {
            return 'none';
        }
        if ($utilization > 100) {
            return 'overload';
        }
        if ($utilization >= 90) {
            return 'risk';
        }
        if ($utilization >= 70) {
            return 'healthy';
        }
        if ($utilization < 60) {
            return 'under';
        }

        return 'balanced';
    }
}
