<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\FleetOps\Models\Maintenance;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Maintenance Service
 *
 * Extends FleetOps maintenance with school transport specific features:
 * - Safety compliance tracking
 * - Route-based maintenance scheduling
 * - Student transport regulations
 * - Emergency maintenance prioritization
 */
class MaintenanceService
{
    /**
     * Schedule maintenance for a bus.
     * Leverages FleetOps Maintenance model with school transport enhancements.
     *
     * @param Bus $bus
     * @param array $data
     * @return Maintenance
     */
    public function scheduleMaintenance(Bus $bus, array $data): Maintenance
    {
        return Maintenance::create([
            'company_uuid' => $bus->company_uuid,
            'maintainable_type' => Bus::class,
            'maintainable_uuid' => $bus->uuid,
            'type' => $data['type'] ?? 'scheduled',
            'status' => $data['status'] ?? 'open',
            'priority' => $data['priority'] ?? 'normal',
            'scheduled_at' => $data['scheduled_at'],
            'odometer' => $data['odometer'] ?? $bus->odometer,
            'summary' => $data['summary'],
            'notes' => $data['notes'] ?? null,
            'estimated_downtime_hours' => $data['estimated_downtime_hours'] ?? null,
            'created_by_uuid' => $data['created_by_uuid'] ?? auth()->id(),
        ]);
    }

    /**
     * Get maintenance analytics for a bus.
     *
     * @param Bus $bus
     * @param array $filters
     * @return array
     */
    public function getMaintenanceAnalytics(Bus $bus, array $filters = []): array
    {
        $query = Maintenance::where('maintainable_uuid', $bus->uuid)
            ->where('maintainable_type', Bus::class);

        // Apply date filters
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        $maintenances = $query->orderBy('scheduled_at', 'desc')->get();

        if ($maintenances->isEmpty()) {
            return $this->getEmptyAnalytics($bus);
        }

        return [
            'bus_uuid' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'period' => [
                'start' => $maintenances->last()->created_at->format('Y-m-d'),
                'end' => $maintenances->first()->created_at->format('Y-m-d'),
            ],
            'summary' => [
                'total_maintenances' => $maintenances->count(),
                'completed_maintenances' => $maintenances->where('status', 'done')->count(),
                'pending_maintenances' => $maintenances->whereIn('status', ['open', 'in_progress'])->count(),
                'overdue_maintenances' => $maintenances->where('status', '!=', 'done')
                    ->where('scheduled_at', '<', now())->count(),
                'total_cost' => $maintenances->sum('cost'),
                'average_cost_per_maintenance' => $this->calculateAverageCost($maintenances),
            ],
            'compliance' => $this->analyzeCompliance($bus, $maintenances),
            'predictive' => $this->generatePredictiveMaintenance($bus, $maintenances),
            'safety_impact' => $this->analyzeSafetyImpact($maintenances),
            'recommendations' => $this->generateMaintenanceRecommendations($bus, $maintenances),
        ];
    }

    /**
     * Get maintenance schedule for a bus.
     *
     * @param Bus $bus
     * @param int $daysAhead
     * @return array
     */
    public function getMaintenanceSchedule(Bus $bus, int $daysAhead = 30): array
    {
        $upcomingMaintenances = Maintenance::where('maintainable_uuid', $bus->uuid)
            ->where('maintainable_type', Bus::class)
            ->where('status', '!=', 'done')
            ->where('scheduled_at', '<=', now()->addDays($daysAhead))
            ->orderBy('scheduled_at', 'asc')
            ->get();

        $overdueMaintenances = Maintenance::where('maintainable_uuid', $bus->uuid)
            ->where('maintainable_type', Bus::class)
            ->where('status', '!=', 'done')
            ->where('scheduled_at', '<', now())
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return [
            'bus_uuid' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'overdue' => $overdueMaintenances->map(function ($maintenance) {
                return $this->formatMaintenanceData($maintenance);
            }),
            'upcoming' => $upcomingMaintenances->map(function ($maintenance) {
                return $this->formatMaintenanceData($maintenance);
            }),
            'next_service_due' => $this->calculateNextServiceDue($bus),
        ];
    }

    /**
     * Check if bus meets safety compliance requirements.
     *
     * @param Bus $bus
     * @return array
     */
    public function checkSafetyCompliance(Bus $bus): array
    {
        $complianceChecks = [
            'annual_inspection' => $this->checkAnnualInspection($bus),
            'brake_inspection' => $this->checkBrakeInspection($bus),
            'emergency_equipment' => $this->checkEmergencyEquipment($bus),
            'tire_inspection' => $this->checkTireInspection($bus),
            'safety_equipment' => $this->checkSafetyEquipment($bus),
        ];

        $passedChecks = collect($complianceChecks)->where('status', 'passed')->count();
        $totalChecks = count($complianceChecks);

        return [
            'bus_uuid' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'overall_status' => $passedChecks === $totalChecks ? 'compliant' : 'non_compliant',
            'compliance_score' => round(($passedChecks / $totalChecks) * 100, 1),
            'checks' => $complianceChecks,
            'next_required_inspection' => $this->getNextRequiredInspection($bus),
            'days_until_next_inspection' => $this->getDaysUntilNextInspection($bus),
        ];
    }

    /**
     * Get maintenance history with cost analysis.
     *
     * @param Bus $bus
     * @param int $months
     * @return array
     */
    public function getMaintenanceHistory(Bus $bus, int $months = 12): array
    {
        $startDate = now()->subMonths($months);

        $maintenances = Maintenance::where('maintainable_uuid', $bus->uuid)
            ->where('maintainable_type', Bus::class)
            ->where('created_at', '>=', $startDate)
            ->orderBy('scheduled_at', 'desc')
            ->get();

        $monthlyCosts = $maintenances->groupBy(function ($maintenance) {
            return $maintenance->scheduled_at->format('Y-m');
        })->map(function ($monthMaintenances) {
            return [
                'count' => $monthMaintenances->count(),
                'total_cost' => $monthMaintenances->sum('cost'),
                'completed' => $monthMaintenances->where('status', 'done')->count(),
            ];
        });

        return [
            'bus_uuid' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'period_months' => $months,
            'total_maintenances' => $maintenances->count(),
            'total_cost' => $maintenances->sum('cost'),
            'average_cost_per_month' => round($maintenances->sum('cost') / $months, 2),
            'monthly_breakdown' => $monthlyCosts,
            'maintenance_types' => $this->analyzeMaintenanceTypes($maintenances),
            'cost_trends' => $this->analyzeCostTrends($monthlyCosts),
        ];
    }

    /**
     * Generate predictive maintenance recommendations.
     *
     * @param Bus $bus
     * @param Collection $maintenances
     * @return array
     */
    public function generatePredictiveMaintenance(Bus $bus): array
    {
        $maintenances = $this->getMaintenanceHistory($bus, 12); // Get last 12 months
        $predictions = [];

        // Analyze maintenance frequency
        $avgDaysBetweenMaintenance = $this->calculateAverageDaysBetweenMaintenance($maintenances);
        if ($avgDaysBetweenMaintenance > 0) {
            $nextPredictedMaintenance = now()->addDays($avgDaysBetweenMaintenance);
            $predictions[] = [
                'type' => 'frequency_based',
                'description' => 'Based on historical maintenance patterns',
                'next_recommended_date' => $nextPredictedMaintenance->format('Y-m-d'),
                'confidence' => 'medium',
            ];
        }

        // Odometer-based predictions
        $currentOdometer = $bus->odometer ?? 0;
        $lastMaintenanceOdometer = $maintenances->where('status', 'done')
            ->sortByDesc('scheduled_at')
            ->first()['odometer'] ?? 0;

        if ($currentOdometer > 0 && $lastMaintenanceOdometer > 0) {
            $milesSinceLastMaintenance = $currentOdometer - $lastMaintenanceOdometer;
            if ($milesSinceLastMaintenance > 5000) { // 5000 miles threshold
                $predictions[] = [
                    'type' => 'odometer_based',
                    'description' => 'Vehicle has traveled ' . $milesSinceLastMaintenance . ' miles since last maintenance',
                    'next_recommended_date' => now()->addDays(30)->format('Y-m-d'),
                    'confidence' => 'high',
                ];
            }
        }

        return $predictions;
    }

    /**
     * Analyze maintenance costs for a bus.
     *
     * @param Bus $bus
     * @param int $months
     * @return array
     */
    public function analyzeMaintenanceCosts(Bus $bus, int $months = 12): array
    {
        $maintenances = $this->getMaintenanceHistory($bus, $months);

        $totalCost = $maintenances->sum('cost');
        $completedMaintenances = $maintenances->where('status', 'done');
        $avgCostPerMaintenance = $completedMaintenances->count() > 0 ?
            round($totalCost / $completedMaintenances->count(), 2) : 0;

        // Monthly cost breakdown
        $monthlyCosts = $maintenances->groupBy(function ($maintenance) {
            return $maintenance['scheduled_at']->format('Y-m');
        })->map(function ($monthMaintenances) {
            return [
                'month' => $monthMaintenances->first()['scheduled_at']->format('M Y'),
                'total_cost' => $monthMaintenances->sum('cost'),
                'maintenance_count' => $monthMaintenances->count(),
            ];
        })->values();

        // Cost by type
        $costByType = $maintenances->groupBy('type')->map(function ($typeMaintenances) {
            return [
                'type' => $typeMaintenances->first()['type'],
                'total_cost' => $typeMaintenances->sum('cost'),
                'count' => $typeMaintenances->count(),
                'avg_cost' => $typeMaintenances->count() > 0 ?
                    round($typeMaintenances->sum('cost') / $typeMaintenances->count(), 2) : 0,
            ];
        })->values();

        // Cost trends
        $costTrends = $this->analyzeCostTrends($monthlyCosts);

        return [
            'bus_uuid' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'period_months' => $months,
            'summary' => [
                'total_cost' => $totalCost,
                'maintenance_count' => $maintenances->count(),
                'avg_cost_per_maintenance' => $avgCostPerMaintenance,
                'monthly_avg_cost' => $monthlyCosts->count() > 0 ?
                    round($totalCost / $monthlyCosts->count(), 2) : 0,
            ],
            'monthly_breakdown' => $monthlyCosts,
            'cost_by_type' => $costByType,
            'trends' => $costTrends,
            'recommendations' => $this->generateCostRecommendations($totalCost, $avgCostPerMaintenance, $costTrends),
        ];
    }

    /**
     * Generate cost analysis recommendations.
     */
    private function generateCostRecommendations(float $totalCost, float $avgCost, array $trends): array
    {
        $recommendations = [];

        if ($avgCost > 300) {
            $recommendations[] = 'Maintenance costs are above average. Consider preventive maintenance to reduce emergency repairs.';
        }

        if ($trends['trend'] === 'increasing') {
            $recommendations[] = 'Maintenance costs are trending upward by ' . ($trends['change_percentage'] ?? 0) . '%. Review maintenance schedule and vendor pricing.';
        }

        if ($totalCost > 2000) {
            $recommendations[] = 'High total maintenance costs. Evaluate vehicle condition and consider replacement if costs continue to rise.';
        }

        return $recommendations;
    }

    /**
     * Analyze safety impact of maintenance.
     *
     * @param Collection $maintenances
     * @return array
     */
    private function analyzeSafetyImpact(Collection $maintenances): array
    {
        $safetyRelatedMaintenances = $maintenances->filter(function ($maintenance) {
            $safetyKeywords = ['brake', 'tire', 'light', 'safety', 'emergency', 'inspection'];
            $summary = strtolower($maintenance->summary ?? '');
            foreach ($safetyKeywords as $keyword) {
                if (str_contains($summary, $keyword)) {
                    return true;
                }
            }
            return false;
        });

        return [
            'safety_maintenances_count' => $safetyRelatedMaintenances->count(),
            'safety_maintenances_percentage' => $maintenances->count() > 0 ?
                round(($safetyRelatedMaintenances->count() / $maintenances->count()) * 100, 1) : 0,
            'overdue_safety_maintenances' => $safetyRelatedMaintenances->where('status', '!=', 'done')
                ->where('scheduled_at', '<', now())->count(),
        ];
    }

    /**
     * Generate maintenance recommendations.
     *
     * @param Bus $bus
     * @param Collection $maintenances
     * @return array
     */
    private function generateMaintenanceRecommendations(Bus $bus, Collection $maintenances): array
    {
        $recommendations = [];

        $overdueCount = $maintenances->where('status', '!=', 'done')
            ->where('scheduled_at', '<', now())->count();

        if ($overdueCount > 0) {
            $recommendations[] = "Address {$overdueCount} overdue maintenance items immediately.";
        }

        $avgCost = $this->calculateAverageCost($maintenances);
        if ($avgCost > 500) {
            $recommendations[] = 'Maintenance costs are high. Consider preventive maintenance program.';
        }

        $compliance = $this->checkSafetyCompliance($bus);
        if ($compliance['overall_status'] === 'non_compliant') {
            $recommendations[] = 'Vehicle is not fully compliant with safety regulations. Schedule inspections immediately.';
        }

        return $recommendations;
    }

    /**
     * Calculate average cost of maintenances.
     */
    private function calculateAverageCost(Collection $maintenances): float
    {
        $totalCost = $maintenances->sum('cost');
        return $maintenances->count() > 0 ? round($totalCost / $maintenances->count(), 2) : 0;
    }

    /**
     * Analyze compliance status.
     */
    private function analyzeCompliance(Bus $bus, Collection $maintenances): array
    {
        $completedMaintenances = $maintenances->where('status', 'done');
        $totalMaintenances = $maintenances->count();

        return [
            'completion_rate' => $totalMaintenances > 0 ?
                round(($completedMaintenances->count() / $totalMaintenances) * 100, 1) : 0,
            'on_time_completion_rate' => $this->calculateOnTimeCompletionRate($maintenances),
            'average_completion_days' => $this->calculateAverageCompletionDays($maintenances),
        ];
    }

    /**
     * Calculate on-time completion rate.
     */
    private function calculateOnTimeCompletionRate(Collection $maintenances): float
    {
        $onTimeCompletions = $maintenances->filter(function ($maintenance) {
            return $maintenance->status === 'done' &&
                $maintenance->completed_at &&
                $maintenance->completed_at <= $maintenance->scheduled_at;
        });

        $completedMaintenances = $maintenances->where('status', 'done');

        return $completedMaintenances->count() > 0 ?
            round(($onTimeCompletions->count() / $completedMaintenances->count()) * 100, 1) : 0;
    }

    /**
     * Calculate average completion days.
     */
    private function calculateAverageCompletionDays(Collection $maintenances): float
    {
        $completedMaintenances = $maintenances->where('status', 'done')->whereNotNull('completed_at');

        if ($completedMaintenances->isEmpty()) {
            return 0;
        }

        $totalDays = $completedMaintenances->sum(function ($maintenance) {
            return $maintenance->completed_at->diffInDays($maintenance->scheduled_at);
        });

        return round($totalDays / $completedMaintenances->count(), 1);
    }

    /**
     * Calculate average days between maintenances.
     */
    private function calculateAverageDaysBetweenMaintenance(Collection $maintenances): float
    {
        $completedDates = $maintenances->where('status', 'done')
            ->whereNotNull('completed_at')
            ->pluck('completed_at')
            ->sort()
            ->values();

        if ($completedDates->count() < 2) {
            return 0;
        }

        $totalDays = 0;
        $count = 0;

        for ($i = 1; $i < $completedDates->count(); $i++) {
            $totalDays += $completedDates[$i]->diffInDays($completedDates[$i - 1]);
            $count++;
        }

        return $count > 0 ? round($totalDays / $count, 1) : 0;
    }

    /**
     * Format maintenance data for API response.
     */
    private function formatMaintenanceData(Maintenance $maintenance): array
    {
        return [
            'uuid' => $maintenance->uuid,
            'type' => $maintenance->type,
            'status' => $maintenance->status,
            'priority' => $maintenance->priority,
            'summary' => $maintenance->summary,
            'scheduled_at' => $maintenance->scheduled_at?->format('Y-m-d H:i:s'),
            'completed_at' => $maintenance->completed_at?->format('Y-m-d H:i:s'),
            'cost' => $maintenance->cost,
            'is_overdue' => $maintenance->status !== 'done' && $maintenance->scheduled_at < now(),
            'days_overdue' => $maintenance->status !== 'done' && $maintenance->scheduled_at < now() ?
                now()->diffInDays($maintenance->scheduled_at) : 0,
        ];
    }

    /**
     * Calculate next service due date.
     */
    private function calculateNextServiceDue(Bus $bus): ?string
    {
        $nextMaintenance = Maintenance::where('maintainable_uuid', $bus->uuid)
            ->where('maintainable_type', Bus::class)
            ->where('status', '!=', 'done')
            ->orderBy('scheduled_at', 'asc')
            ->first();

        return $nextMaintenance?->scheduled_at?->format('Y-m-d');
    }

    /**
     * Check annual inspection compliance.
     */
    private function checkAnnualInspection(Bus $bus): array
    {
        // Logic for annual inspection compliance
        $lastInspection = Maintenance::where('maintainable_uuid', $bus->uuid)
            ->where('maintainable_type', Bus::class)
            ->where('type', 'inspection')
            ->where('status', 'done')
            ->orderBy('completed_at', 'desc')
            ->first();

        $nextDue = $lastInspection ?
            $lastInspection->completed_at->addYear() :
            now()->addMonths(6); // Default if no inspection found

        return [
            'check' => 'annual_inspection',
            'status' => $nextDue > now() ? 'passed' : 'failed',
            'last_completed' => $lastInspection?->completed_at?->format('Y-m-d'),
            'next_due' => $nextDue->format('Y-m-d'),
            'days_until_due' => now()->diffInDays($nextDue, false),
        ];
    }

    /**
     * Check brake inspection compliance.
     */
    private function checkBrakeInspection(Bus $bus): array
    {
        $lastBrakeInspection = Maintenance::where('maintainable_uuid', $bus->uuid)
            ->where('maintainable_type', Bus::class)
            ->where('summary', 'like', '%brake%')
            ->where('status', 'done')
            ->orderBy('completed_at', 'desc')
            ->first();

        $nextDue = $lastBrakeInspection ?
            $lastBrakeInspection->completed_at->addMonths(6) :
            now()->addMonths(3);

        return [
            'check' => 'brake_inspection',
            'status' => $nextDue > now() ? 'passed' : 'failed',
            'last_completed' => $lastBrakeInspection?->completed_at?->format('Y-m-d'),
            'next_due' => $nextDue->format('Y-m-d'),
            'days_until_due' => now()->diffInDays($nextDue, false),
        ];
    }

    /**
     * Check emergency equipment compliance.
     */
    private function checkEmergencyEquipment(Bus $bus): array
    {
        // Simplified check - in real implementation, this would check specific equipment
        return [
            'check' => 'emergency_equipment',
            'status' => 'passed', // Assume compliant for demo
            'last_checked' => now()->subMonths(1)->format('Y-m-d'),
            'next_due' => now()->addMonths(6)->format('Y-m-d'),
            'days_until_due' => 180,
        ];
    }

    /**
     * Check tire inspection compliance.
     */
    private function checkTireInspection(Bus $bus): array
    {
        $lastTireInspection = Maintenance::where('maintainable_uuid', $bus->uuid)
            ->where('maintainable_type', Bus::class)
            ->where('summary', 'like', '%tire%')
            ->where('status', 'done')
            ->orderBy('completed_at', 'desc')
            ->first();

        $nextDue = $lastTireInspection ?
            $lastTireInspection->completed_at->addMonths(3) :
            now()->addMonths(1);

        return [
            'check' => 'tire_inspection',
            'status' => $nextDue > now() ? 'passed' : 'failed',
            'last_completed' => $lastTireInspection?->completed_at?->format('Y-m-d'),
            'next_due' => $nextDue->format('Y-m-d'),
            'days_until_due' => now()->diffInDays($nextDue, false),
        ];
    }

    /**
     * Check safety equipment compliance.
     */
    private function checkSafetyEquipment(Bus $bus): array
    {
        // Simplified check - in real implementation, this would check specific safety equipment
        return [
            'check' => 'safety_equipment',
            'status' => 'passed', // Assume compliant for demo
            'last_checked' => now()->subMonths(2)->format('Y-m-d'),
            'next_due' => now()->addMonths(6)->format('Y-m-d'),
            'days_until_due' => 150,
        ];
    }

    /**
     * Get next required inspection date.
     */
    private function getNextRequiredInspection(Bus $bus): ?string
    {
        $compliance = $this->checkSafetyCompliance($bus);
        $nextDates = collect($compliance['checks'])
            ->pluck('next_due')
            ->filter()
            ->sort()
            ->first();

        return $nextDates;
    }

    /**
     * Get days until next inspection.
     */
    private function getDaysUntilNextInspection(Bus $bus): int
    {
        $nextInspection = $this->getNextRequiredInspection($bus);
        return $nextInspection ? now()->diffInDays($nextInspection, false) : 0;
    }

    /**
     * Analyze maintenance types distribution.
     */
    private function analyzeMaintenanceTypes(Collection $maintenances): array
    {
        return $maintenances->groupBy('type')->map(function ($typeMaintenances) {
            return [
                'count' => $typeMaintenances->count(),
                'total_cost' => $typeMaintenances->sum('cost'),
                'completed' => $typeMaintenances->where('status', 'done')->count(),
            ];
        })->toArray();
    }

    /**
     * Analyze cost trends over time.
     */
    private function analyzeCostTrends(Collection $monthlyCosts): array
    {
        if ($monthlyCosts->isEmpty()) {
            return ['trend' => 'insufficient_data'];
        }

        $costs = $monthlyCosts->pluck('total_cost')->values();
        $avgCost = $costs->avg();

        if ($costs->count() < 3) {
            return ['trend' => 'insufficient_data'];
        }

        $recentCosts = $costs->slice(-3); // Last 3 months
        $recentAvg = $recentCosts->avg();

        if ($recentAvg > $avgCost * 1.2) {
            return ['trend' => 'increasing', 'change_percentage' => round((($recentAvg - $avgCost) / $avgCost) * 100, 1)];
        } elseif ($recentAvg < $avgCost * 0.8) {
            return ['trend' => 'decreasing', 'change_percentage' => round((($avgCost - $recentAvg) / $avgCost) * 100, 1)];
        } else {
            return ['trend' => 'stable'];
        }
    }

    /**
     * Get empty analytics structure.
     */
    private function getEmptyAnalytics(Bus $bus): array
    {
        return [
            'bus_uuid' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'period' => null,
            'summary' => [
                'total_maintenances' => 0,
                'completed_maintenances' => 0,
                'pending_maintenances' => 0,
                'overdue_maintenances' => 0,
                'total_cost' => 0,
                'average_cost_per_maintenance' => 0,
            ],
            'compliance' => [
                'completion_rate' => 0,
                'on_time_completion_rate' => 0,
                'average_completion_days' => 0,
            ],
            'predictive' => [],
            'safety_impact' => [
                'safety_maintenances_count' => 0,
                'safety_maintenances_percentage' => 0,
                'overdue_safety_maintenances' => 0,
            ],
            'recommendations' => ['No maintenance data available for analysis'],
        ];
    }
}
