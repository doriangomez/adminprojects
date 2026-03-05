<?php

declare(strict_types=1);

class TalentCapacityInsightsService
{
    public function generate(array $dashboard): array
    {
        $talents = $dashboard['talents'] ?? [];
        $summary = $dashboard['summary'] ?? [];

        if (empty($talents)) {
            return $this->emptyInsights();
        }

        return [
            'team_utilization' => $this->teamUtilizationInsight($talents, $summary),
            'peak_weeks' => $this->peakWeeksInsight($talents),
            'top_utilized_talents' => $this->topUtilizedTalentsInsight($talents),
            'available_talents' => $this->availableTalentsInsight($talents),
            'free_capacity' => $this->freeCapacityInsight($talents, $summary),
        ];
    }

    private function teamUtilizationInsight(array $talents, array $summary): array
    {
        $avg = (float) ($summary['avg_team_utilization'] ?? 0);
        $totalTalents = count($talents);

        $buckets = ['overload' => 0, 'risk' => 0, 'healthy' => 0, 'under' => 0, 'none' => 0];
        foreach ($talents as $talent) {
            $monthly = $talent['monthly'] ?? [];
            $latest = !empty($monthly) ? end($monthly) : ['utilization' => 0];
            $util = (float) ($latest['utilization'] ?? 0);
            if ($util > 100) {
                $buckets['overload']++;
            } elseif ($util >= 90) {
                $buckets['risk']++;
            } elseif ($util >= 60) {
                $buckets['healthy']++;
            } elseif ($util > 0) {
                $buckets['under']++;
            } else {
                $buckets['none']++;
            }
        }

        $level = $this->utilizationLevel($avg);
        $interpretation = $this->buildTeamInterpretation($avg, $level, $totalTalents, $buckets);

        return [
            'avg_utilization' => round($avg, 1),
            'total_talents' => $totalTalents,
            'distribution' => $buckets,
            'level' => $level,
            'interpretation' => $interpretation,
        ];
    }

    private function buildTeamInterpretation(float $avg, string $level, int $total, array $buckets): string
    {
        $parts = [];

        $levelLabels = [
            'critical' => 'El equipo se encuentra en un nivel de utilización crítico (' . number_format($avg, 1) . '%).',
            'high' => 'El equipo opera a una utilización alta (' . number_format($avg, 1) . '%), cercana al límite.',
            'optimal' => 'La utilización del equipo es óptima (' . number_format($avg, 1) . '%), dentro del rango saludable.',
            'moderate' => 'La utilización del equipo es moderada (' . number_format($avg, 1) . '%), con margen de crecimiento.',
            'low' => 'La utilización del equipo es baja (' . number_format($avg, 1) . '%). Existe capacidad ociosa significativa.',
            'idle' => 'El equipo presenta carga mínima o nula (' . number_format($avg, 1) . '%).',
        ];
        $parts[] = $levelLabels[$level] ?? $levelLabels['moderate'];

        if ($buckets['overload'] > 0) {
            $pct = round(($buckets['overload'] / $total) * 100);
            $parts[] = $buckets['overload'] . ' talento(s) (' . $pct . '%) están sobrecargados y requieren redistribución inmediata.';
        }
        if ($buckets['risk'] > 0) {
            $parts[] = $buckets['risk'] . ' talento(s) están en zona de riesgo (≥90%) y podrían saturarse pronto.';
        }
        if ($buckets['none'] > 0 && $total > 1) {
            $parts[] = $buckets['none'] . ' talento(s) no tienen carga asignada en este periodo.';
        }

        if ($level === 'critical' || $level === 'high') {
            $parts[] = 'Se recomienda evaluar la redistribución de tareas o incorporar recursos adicionales.';
        } elseif ($level === 'low' || $level === 'idle') {
            $parts[] = 'Es un buen momento para asignar nuevos proyectos o adelantar trabajo planificado.';
        }

        return implode(' ', $parts);
    }

    private function peakWeeksInsight(array $talents): array
    {
        $weeklyAgg = [];

        foreach ($talents as $talent) {
            $weekly = $talent['weekly'] ?? [];
            foreach ($weekly as $weekKey => $bucket) {
                $weeklyAgg[$weekKey]['hours'] = ($weeklyAgg[$weekKey]['hours'] ?? 0) + (float) ($bucket['hours'] ?? 0);
                $weeklyAgg[$weekKey]['capacity'] = ($weeklyAgg[$weekKey]['capacity'] ?? 0) + (float) ($bucket['capacity'] ?? 0);
                $weeklyAgg[$weekKey]['count'] = ($weeklyAgg[$weekKey]['count'] ?? 0) + 1;
                $weeklyAgg[$weekKey]['utilization_sum'] = ($weeklyAgg[$weekKey]['utilization_sum'] ?? 0) + (float) ($bucket['utilization'] ?? 0);
            }
        }

        $weeks = [];
        foreach ($weeklyAgg as $weekKey => $agg) {
            $count = (int) ($agg['count'] ?? 1);
            $avgUtil = $count > 0 ? ($agg['utilization_sum'] / $count) : 0;
            $weeks[] = [
                'week' => $weekKey,
                'avg_utilization' => round($avgUtil, 1),
                'total_hours' => round((float) ($agg['hours'] ?? 0), 1),
                'total_capacity' => round((float) ($agg['capacity'] ?? 0), 1),
                'level' => $this->utilizationLevel($avgUtil),
            ];
        }

        usort($weeks, static fn(array $a, array $b) => $b['avg_utilization'] <=> $a['avg_utilization']);

        $top = array_slice($weeks, 0, 5);
        $peakWeek = $top[0] ?? null;

        $interpretation = 'No hay datos semanales disponibles para analizar.';
        if ($peakWeek !== null) {
            $totalWeeks = count($weeks);
            $criticalWeeks = count(array_filter($weeks, fn($w) => $w['avg_utilization'] >= 90));
            $interpretation = 'La semana con mayor carga es ' . $peakWeek['week']
                . ' con una utilización promedio de ' . number_format($peakWeek['avg_utilization'], 1) . '%.';

            if ($criticalWeeks > 0) {
                $interpretation .= ' Se detectan ' . $criticalWeeks . ' de ' . $totalWeeks
                    . ' semanas con utilización ≥90%, lo que indica periodos de alta presión.';
            } else {
                $interpretation .= ' Ninguna semana supera el umbral crítico del 90%, lo que refleja una distribución equilibrada de la carga.';
            }

            if ($criticalWeeks > ($totalWeeks / 2) && $totalWeeks > 1) {
                $interpretation .= ' Más de la mitad de las semanas están en zona de riesgo; se recomienda revisar la planificación.';
            }
        }

        return [
            'top_weeks' => $top,
            'total_weeks' => count($weeks),
            'critical_weeks' => count(array_filter($weeks, fn($w) => $w['avg_utilization'] >= 90)),
            'interpretation' => $interpretation,
        ];
    }

    private function topUtilizedTalentsInsight(array $talents): array
    {
        $ranked = [];
        foreach ($talents as $talent) {
            $monthly = $talent['monthly'] ?? [];
            $latest = !empty($monthly) ? end($monthly) : ['utilization' => 0, 'hours' => 0, 'capacity' => 0];
            $util = (float) ($latest['utilization'] ?? 0);
            $hours = (float) ($latest['hours'] ?? 0);
            $capacity = (float) ($latest['capacity'] ?? 0);
            $ranked[] = [
                'name' => (string) ($talent['name'] ?? ''),
                'role' => (string) ($talent['role'] ?? ''),
                'utilization' => round($util, 1),
                'hours' => round($hours, 1),
                'capacity' => round($capacity, 1),
                'overload' => round(max(0, $hours - $capacity), 1),
                'level' => $this->utilizationLevel($util),
            ];
        }

        usort($ranked, static fn(array $a, array $b) => $b['utilization'] <=> $a['utilization']);
        $top = array_slice($ranked, 0, 5);

        $overloaded = array_filter($top, fn($t) => $t['utilization'] > 100);
        $atRisk = array_filter($top, fn($t) => $t['utilization'] >= 90 && $t['utilization'] <= 100);

        $interpretation = '';
        if (!empty($top)) {
            $first = $top[0];
            $interpretation = 'El talento con mayor utilización es ' . $first['name']
                . ' (' . $first['role'] . ') con ' . number_format($first['utilization'], 1) . '%.';

            if (count($overloaded) > 0) {
                $names = array_map(fn($t) => $t['name'], $overloaded);
                $interpretation .= ' ' . count($overloaded) . ' talento(s) están sobrecargados (' . implode(', ', $names)
                    . '); se recomienda redistribuir sus asignaciones para evitar desgaste y riesgos en la entrega.';
            }
            if (count($atRisk) > 0) {
                $interpretation .= ' ' . count($atRisk) . ' talento(s) están en el límite de su capacidad y podrían saturarse con cualquier asignación adicional.';
            }
            if (empty($overloaded) && empty($atRisk)) {
                $interpretation .= ' Ningún talento supera el umbral crítico; la distribución actual es saludable.';
            }
        } else {
            $interpretation = 'No hay datos de talentos para este periodo.';
        }

        return [
            'top_talents' => $top,
            'overloaded_count' => count($overloaded),
            'at_risk_count' => count($atRisk),
            'interpretation' => $interpretation,
        ];
    }

    private function availableTalentsInsight(array $talents): array
    {
        $available = [];
        foreach ($talents as $talent) {
            $monthly = $talent['monthly'] ?? [];
            $latest = !empty($monthly) ? end($monthly) : ['utilization' => 0, 'hours' => 0, 'capacity' => 0];
            $util = (float) ($latest['utilization'] ?? 0);
            $hours = (float) ($latest['hours'] ?? 0);
            $capacity = (float) ($latest['capacity'] ?? 0);
            $free = max(0, $capacity - $hours);

            if ($free > 0) {
                $available[] = [
                    'name' => (string) ($talent['name'] ?? ''),
                    'role' => (string) ($talent['role'] ?? ''),
                    'utilization' => round($util, 1),
                    'free_hours' => round($free, 1),
                    'capacity' => round($capacity, 1),
                ];
            }
        }

        usort($available, static fn(array $a, array $b) => $b['free_hours'] <=> $a['free_hours']);
        $top = array_slice($available, 0, 5);

        $totalFree = array_sum(array_column($available, 'free_hours'));
        $totalTalents = count($talents);
        $availableCount = count($available);

        if ($availableCount === 0) {
            $interpretation = 'No hay talentos con capacidad libre en el periodo actual. Todo el equipo está completamente asignado; '
                . 'cualquier nueva demanda requerirá redistribución o recursos adicionales.';
        } else {
            $pct = $totalTalents > 0 ? round(($availableCount / $totalTalents) * 100) : 0;
            $interpretation = $availableCount . ' de ' . $totalTalents . ' talentos (' . $pct . '%) tienen horas disponibles, '
                . 'con un total de ' . number_format($totalFree, 1) . 'h libres para asignación.';

            if (!empty($top)) {
                $firstName = $top[0]['name'];
                $firstHours = number_format($top[0]['free_hours'], 1);
                $interpretation .= ' ' . $firstName . ' lidera con ' . $firstHours . 'h disponibles.';
            }

            if ($pct >= 70) {
                $interpretation .= ' La mayoría del equipo tiene capacidad ociosa; se recomienda asignar nuevos proyectos para optimizar los recursos.';
            } elseif ($pct <= 20 && $availableCount > 0) {
                $interpretation .= ' Solo un pequeño porcentaje del equipo tiene margen; planificar nuevas asignaciones con cautela.';
            }
        }

        return [
            'talents' => $top,
            'total_available' => $availableCount,
            'total_free_hours' => round($totalFree, 1),
            'interpretation' => $interpretation,
        ];
    }

    private function freeCapacityInsight(array $talents, array $summary): array
    {
        $idleCapacity = (float) ($summary['idle_capacity'] ?? 0);
        $totalCapacity = 0.0;
        $totalHours = 0.0;

        foreach ($talents as $talent) {
            $monthly = $talent['monthly'] ?? [];
            $latest = !empty($monthly) ? end($monthly) : ['hours' => 0, 'capacity' => 0];
            $totalCapacity += (float) ($latest['capacity'] ?? 0);
            $totalHours += (float) ($latest['hours'] ?? 0);
        }

        $freePercentage = $totalCapacity > 0 ? (($idleCapacity / $totalCapacity) * 100) : 0;
        $usedPercentage = 100 - $freePercentage;

        $weeklyFree = [];
        $weeklyAgg = [];
        foreach ($talents as $talent) {
            foreach (($talent['weekly'] ?? []) as $weekKey => $bucket) {
                $weeklyAgg[$weekKey]['hours'] = ($weeklyAgg[$weekKey]['hours'] ?? 0) + (float) ($bucket['hours'] ?? 0);
                $weeklyAgg[$weekKey]['capacity'] = ($weeklyAgg[$weekKey]['capacity'] ?? 0) + (float) ($bucket['capacity'] ?? 0);
            }
        }
        foreach ($weeklyAgg as $weekKey => $agg) {
            $cap = (float) ($agg['capacity'] ?? 0);
            $hrs = (float) ($agg['hours'] ?? 0);
            $free = max(0, $cap - $hrs);
            $weeklyFree[$weekKey] = round($free, 1);
        }

        arsort($weeklyFree);
        $topFreeWeeks = array_slice($weeklyFree, 0, 3, true);

        $interpretation = 'El equipo dispone de ' . number_format($idleCapacity, 1) . 'h de capacidad libre '
            . '(' . number_format($freePercentage, 1) . '% de la capacidad total de ' . number_format($totalCapacity, 1) . 'h).';

        if ($freePercentage > 40) {
            $interpretation .= ' Este nivel de capacidad ociosa es elevado y representa una oportunidad para asumir nuevos proyectos o redistribuir recursos.';
        } elseif ($freePercentage > 20) {
            $interpretation .= ' El equipo mantiene un margen razonable para absorber nuevas asignaciones sin comprometer la calidad.';
        } elseif ($freePercentage > 5) {
            $interpretation .= ' El margen es limitado; nuevas asignaciones deben planificarse cuidadosamente.';
        } else {
            $interpretation .= ' El equipo opera a capacidad casi completa; cualquier incremento de carga requerirá redistribución o ampliación del equipo.';
        }

        if (!empty($topFreeWeeks)) {
            $weekLabels = [];
            foreach ($topFreeWeeks as $wk => $hrs) {
                $weekLabels[] = $wk . ' (' . number_format($hrs, 1) . 'h)';
            }
            $interpretation .= ' Las semanas con mayor disponibilidad son: ' . implode(', ', $weekLabels) . '.';
        }

        return [
            'idle_hours' => round($idleCapacity, 1),
            'total_capacity' => round($totalCapacity, 1),
            'total_assigned' => round($totalHours, 1),
            'free_percentage' => round($freePercentage, 1),
            'used_percentage' => round($usedPercentage, 1),
            'top_free_weeks' => $topFreeWeeks,
            'interpretation' => $interpretation,
        ];
    }

    private function utilizationLevel(float $utilization): string
    {
        if ($utilization > 100) {
            return 'critical';
        }
        if ($utilization >= 90) {
            return 'high';
        }
        if ($utilization >= 70) {
            return 'optimal';
        }
        if ($utilization >= 50) {
            return 'moderate';
        }
        if ($utilization > 0) {
            return 'low';
        }

        return 'idle';
    }

    private function emptyInsights(): array
    {
        $empty = [
            'team_utilization' => [
                'avg_utilization' => 0,
                'total_talents' => 0,
                'distribution' => ['overload' => 0, 'risk' => 0, 'healthy' => 0, 'under' => 0, 'none' => 0],
                'level' => 'idle',
                'interpretation' => 'No hay talentos registrados para generar un análisis. Agrega talentos al sistema para comenzar.',
            ],
            'peak_weeks' => [
                'top_weeks' => [],
                'total_weeks' => 0,
                'critical_weeks' => 0,
                'interpretation' => 'No hay datos semanales disponibles.',
            ],
            'top_utilized_talents' => [
                'top_talents' => [],
                'overloaded_count' => 0,
                'at_risk_count' => 0,
                'interpretation' => 'No hay datos de talentos para este periodo.',
            ],
            'available_talents' => [
                'talents' => [],
                'total_available' => 0,
                'total_free_hours' => 0,
                'interpretation' => 'No hay información de disponibilidad.',
            ],
            'free_capacity' => [
                'idle_hours' => 0,
                'total_capacity' => 0,
                'total_assigned' => 0,
                'free_percentage' => 0,
                'used_percentage' => 0,
                'top_free_weeks' => [],
                'interpretation' => 'No hay datos de capacidad en el periodo seleccionado.',
            ],
        ];

        return $empty;
    }
}
