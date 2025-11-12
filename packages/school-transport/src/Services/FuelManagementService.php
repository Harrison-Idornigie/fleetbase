<?php

namespace Fleetbase\SchoolTransportEngine\Services;

use Fleetbase\SchoolTransportEngine\Models\Bus;
use Fleetbase\FleetOps\Models\FuelReport;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fuel Management Service
 *
 * Extends FleetOps fuel reporting with school transport specific features:
 * - Fuel cost analysis per route/mile
 * - Fuel efficiency tracking
 * - Driver fuel usage patterns
 * - Cost optimization recommendations
 */
class FuelManagementService
{
    /**
     * Record a fuel report for a bus.
     * Leverages FleetOps FuelReport model with school transport enhancements.
     *
     * @param Bus $bus
     * @param array $data
     * @return FuelReport
     */
    public function recordFuelReport(Bus $bus, array $data): FuelReport
    {
        return FuelReport::create([
            'company_uuid' => $bus->company_uuid,
            'vehicle_uuid' => $bus->uuid,
            'driver_uuid' => $data['driver_uuid'] ?? $bus->driver_uuid,
            'reported_by_uuid' => $data['reported_by_uuid'] ?? auth()->id(),
            'odometer' => $data['odometer'] ?? $bus->odometer,
            'location' => $data['location'] ?? $bus->location,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'volume' => $data['volume'],
            'metric_unit' => $data['metric_unit'] ?? 'liters',
            'report' => $data['report'] ?? null,
            'status' => $data['status'] ?? 'pending',
        ]);
    }

    /**
     * Get fuel efficiency analytics for a bus.
     *
     * @param Bus $bus
     * @param array $filters
     * @return array
     */
    public function getFuelAnalytics(Bus $bus, array $filters = []): array
    {
        $query = FuelReport::where('vehicle_uuid', $bus->uuid)
            ->where('status', 'approved')
            ->orderBy('created_at', 'asc');

        // Apply date filters
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        $fuelReports = $query->get();

        if ($fuelReports->isEmpty()) {
            return $this->getEmptyAnalytics();
        }

        return [
            'bus_uuid' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'period' => [
                'start' => $fuelReports->first()->created_at->format('Y-m-d'),
                'end' => $fuelReports->last()->created_at->format('Y-m-d'),
            ],
            'summary' => [
                'total_fuel_volume' => $fuelReports->sum('volume'),
                'total_fuel_cost' => $fuelReports->sum('amount'),
                'average_cost_per_liter' => $this->calculateAverageCostPerLiter($fuelReports),
                'total_reports' => $fuelReports->count(),
            ],
            'efficiency' => $this->calculateFuelEfficiency($bus, $fuelReports),
            'cost_analysis' => $this->analyzeFuelCosts($fuelReports),
            'recommendations' => $this->generateFuelRecommendations($bus, $fuelReports),
        ];
    }

    /**
     * Get fuel efficiency metrics per route.
     *
     * @param Bus $bus
     * @param string $routeUuid
     * @return array
     */
    public function getRouteFuelEfficiency(Bus $bus, string $routeUuid): array
    {
        // Get fuel reports during route assignments
        $fuelReports = FuelReport::where('vehicle_uuid', $bus->uuid)
            ->whereHas('vehicle.positions', function (Builder $query) use ($routeUuid) {
                $query->where('order_uuid', 'like', "%{$routeUuid}%");
            })
            ->where('status', 'approved')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($fuelReports->isEmpty()) {
            return [
                'route_uuid' => $routeUuid,
                'bus_uuid' => $bus->uuid,
                'efficiency' => null,
                'message' => 'No fuel data available for this route'
            ];
        }

        return [
            'route_uuid' => $routeUuid,
            'bus_uuid' => $bus->uuid,
            'fuel_efficiency_mpg' => $this->calculateMilesPerGallon($bus, $fuelReports),
            'fuel_efficiency_lpk' => $this->calculateLitersPerKilometer($bus, $fuelReports),
            'cost_per_mile' => $this->calculateCostPerMile($fuelReports),
            'cost_per_kilometer' => $this->calculateCostPerKilometer($fuelReports),
        ];
    }

    /**
     * Get fuel usage patterns by driver.
     *
     * @param Bus $bus
     * @return array
     */
    public function getDriverFuelPatterns(Bus $bus): array
    {
        $driverFuelData = FuelReport::where('vehicle_uuid', $bus->uuid)
            ->where('status', 'approved')
            ->whereNotNull('driver_uuid')
            ->selectRaw('
                driver_uuid,
                COUNT(*) as total_reports,
                SUM(volume) as total_volume,
                SUM(amount) as total_cost,
                AVG(volume) as avg_volume_per_report,
                AVG(amount) as avg_cost_per_report
            ')
            ->groupBy('driver_uuid')
            ->with('driver:id,name')
            ->get();

        return $driverFuelData->map(function ($data) {
            return [
                'driver_uuid' => $data->driver_uuid,
                'driver_name' => $data->driver->name ?? 'Unknown',
                'total_reports' => $data->total_reports,
                'total_volume' => round($data->total_volume, 2),
                'total_cost' => round($data->total_cost, 2),
                'avg_volume_per_report' => round($data->avg_volume_per_report, 2),
                'avg_cost_per_report' => round($data->avg_cost_per_report, 2),
                'efficiency_rating' => $this->calculateDriverEfficiencyRating($data),
            ];
        })->toArray();
    }

    /**
     * Calculate average cost per liter.
     */
    private function calculateAverageCostPerLiter(Collection $fuelReports): float
    {
        $totalCost = $fuelReports->sum('amount');
        $totalVolume = $fuelReports->sum('volume');

        return $totalVolume > 0 ? round($totalCost / $totalVolume, 3) : 0;
    }

    /**
     * Calculate fuel efficiency metrics.
     */
    private function calculateFuelEfficiency(Bus $bus, Collection $fuelReports): array
    {
        $totalVolume = $fuelReports->sum('volume');
        $totalDistance = $this->calculateTotalDistance($bus, $fuelReports);

        return [
            'total_distance_miles' => $totalDistance,
            'total_fuel_volume' => $totalVolume,
            'miles_per_gallon' => $totalVolume > 0 ? round($totalDistance / $totalVolume, 2) : 0,
            'liters_per_100km' => $this->calculateLitersPer100Km($bus, $fuelReports),
        ];
    }

    /**
     * Calculate total distance driven during fuel period.
     */
    private function calculateTotalDistance(Bus $bus, Collection $fuelReports): float
    {
        if ($fuelReports->isEmpty()) {
            return 0;
        }

        $firstReport = $fuelReports->first();
        $lastReport = $fuelReports->last();

        $startOdometer = $firstReport->odometer ?? 0;
        $endOdometer = $lastReport->odometer ?? 0;

        return max(0, $endOdometer - $startOdometer);
    }

    /**
     * Calculate liters per 100 kilometers.
     */
    private function calculateLitersPer100Km(Bus $bus, Collection $fuelReports): float
    {
        $totalVolume = $fuelReports->sum('volume');
        $totalDistanceKm = $this->calculateTotalDistance($bus, $fuelReports) * 1.60934; // Convert miles to km

        if ($totalDistanceKm <= 0) {
            return 0;
        }

        return round(($totalVolume / $totalDistanceKm) * 100, 2);
    }

    /**
     * Calculate miles per gallon.
     */
    private function calculateMilesPerGallon(Bus $bus, Collection $fuelReports): float
    {
        $totalVolume = $fuelReports->sum('volume');
        $totalDistance = $this->calculateTotalDistance($bus, $fuelReports);

        // Convert liters to gallons if needed
        $totalGallons = $totalVolume * 0.264172; // 1 liter = 0.264172 gallons

        return $totalGallons > 0 ? round($totalDistance / $totalGallons, 2) : 0;
    }

    /**
     * Calculate liters per kilometer.
     */
    private function calculateLitersPerKilometer(Bus $bus, Collection $fuelReports): float
    {
        $totalVolume = $fuelReports->sum('volume');
        $totalDistanceKm = $this->calculateTotalDistance($bus, $fuelReports) * 1.60934;

        return $totalDistanceKm > 0 ? round($totalVolume / $totalDistanceKm, 3) : 0;
    }

    /**
     * Calculate cost per mile.
     */
    private function calculateCostPerMile(Collection $fuelReports): float
    {
        $totalCost = $fuelReports->sum('amount');
        $totalDistance = $this->calculateTotalDistanceFromReports($fuelReports);

        return $totalDistance > 0 ? round($totalCost / $totalDistance, 3) : 0;
    }

    /**
     * Calculate cost per kilometer.
     */
    private function calculateCostPerKilometer(Collection $fuelReports): float
    {
        $totalCost = $fuelReports->sum('amount');
        $totalDistanceKm = $this->calculateTotalDistanceFromReports($fuelReports) * 1.60934;

        return $totalDistanceKm > 0 ? round($totalCost / $totalDistanceKm, 3) : 0;
    }

    /**
     * Calculate total distance from odometer readings.
     */
    private function calculateTotalDistanceFromReports(Collection $fuelReports): float
    {
        if ($fuelReports->isEmpty()) {
            return 0;
        }

        $firstReport = $fuelReports->first();
        $lastReport = $fuelReports->last();

        $startOdometer = $firstReport->odometer ?? 0;
        $endOdometer = $lastReport->odometer ?? 0;

        return max(0, $endOdometer - $startOdometer);
    }

    /**
     * Analyze fuel cost patterns.
     */
    private function analyzeFuelCosts(Collection $fuelReports): array
    {
        $costs = $fuelReports->pluck('amount')->toArray();

        return [
            'average_cost_per_fill' => round(array_sum($costs) / count($costs), 2),
            'min_cost' => round(min($costs), 2),
            'max_cost' => round(max($costs), 2),
            'cost_variance' => $this->calculateVariance($costs),
        ];
    }

    /**
     * Generate fuel optimization recommendations.
     */
    private function generateFuelRecommendations(Bus $bus, Collection $fuelReports): array
    {
        $recommendations = [];

        $efficiency = $this->calculateMilesPerGallon($bus, $fuelReports);
        if ($efficiency < 8) {
            $recommendations[] = 'Fuel efficiency is below average. Consider driver training or vehicle maintenance.';
        }

        $avgCostPerLiter = $this->calculateAverageCostPerLiter($fuelReports);
        if ($avgCostPerLiter > 1.5) {
            $recommendations[] = 'Fuel costs are high. Consider bulk purchasing or alternative suppliers.';
        }

        if ($fuelReports->count() < 5) {
            $recommendations[] = 'Limited fuel data available. More frequent reporting recommended for better analysis.';
        }

        return $recommendations;
    }

    /**
     * Calculate driver efficiency rating.
     */
    private function calculateDriverEfficiencyRating($driverData): string
    {
        $avgVolume = $driverData->avg_volume_per_report ?? 0;

        if ($avgVolume < 20) {
            return 'Excellent';
        } elseif ($avgVolume < 30) {
            return 'Good';
        } elseif ($avgVolume < 40) {
            return 'Average';
        } else {
            return 'Needs Improvement';
        }
    }

    /**
     * Calculate statistical variance.
     */
    private function calculateVariance(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $variance = 0;

        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }

        return round($variance / count($values), 2);
    }

    /**
     * Get empty analytics structure.
     */
    private function getEmptyAnalytics(): array
    {
        return [
            'bus_uuid' => null,
            'bus_number' => null,
            'period' => null,
            'summary' => [
                'total_fuel_volume' => 0,
                'total_fuel_cost' => 0,
                'average_cost_per_liter' => 0,
                'total_reports' => 0,
            ],
            'efficiency' => [
                'total_distance_miles' => 0,
                'total_fuel_volume' => 0,
                'miles_per_gallon' => 0,
                'liters_per_100km' => 0,
            ],
            'cost_analysis' => [
                'average_cost_per_fill' => 0,
                'min_cost' => 0,
                'max_cost' => 0,
                'cost_variance' => 0,
            ],
            'recommendations' => ['No fuel data available for analysis'],
        ];
    }

    /**
     * Analyze fuel efficiency trends for a bus.
     *
     * @param Bus $bus
     * @param int $months
     * @return array
     */
    public function analyzeFuelEfficiencyTrends(Bus $bus, int $months = 12): array
    {
        $fuelReports = $this->getFuelReports($bus, $months);

        if ($fuelReports->isEmpty()) {
            return [
                'bus_uuid' => $bus->uuid,
                'bus_number' => $bus->bus_number,
                'period_months' => $months,
                'message' => 'No fuel data available for analysis',
                'trends' => ['trend' => 'insufficient_data'],
                'recommendations' => ['Start recording fuel reports to enable efficiency analysis'],
            ];
        }

        // Calculate MPG for each report
        $reportsWithMPG = $fuelReports->map(function ($report) {
            $mpg = $this->calculateMilesPerGallon($report);
            return array_merge($report, ['mpg' => $mpg]);
        })->filter(function ($report) {
            return $report['mpg'] > 0;
        });

        // Monthly efficiency trends
        $monthlyEfficiency = $reportsWithMPG->groupBy(function ($report) {
            return Carbon::parse($report['created_at'])->format('Y-m');
        })->map(function ($monthReports) {
            $avgMPG = $monthReports->avg('mpg');
            $totalGallons = $monthReports->sum('volume');
            $totalMiles = $monthReports->sum(function ($report) {
                return $report['mpg'] * $report['volume'];
            });

            return [
                'month' => Carbon::parse($monthReports->first()['created_at'])->format('M Y'),
                'avg_mpg' => round($avgMPG, 2),
                'total_gallons' => round($totalGallons, 2),
                'total_miles' => round($totalMiles, 2),
                'report_count' => $monthReports->count(),
            ];
        })->values();

        // Overall trend analysis
        $mpgValues = $monthlyEfficiency->pluck('avg_mpg')->filter()->values();
        $trend = $this->analyzeEfficiencyTrend($mpgValues);

        // Driver efficiency comparison
        $driverEfficiency = $reportsWithMPG->groupBy('driver_uuid')->map(function ($driverReports) {
            return [
                'driver_uuid' => $driverReports->first()['driver_uuid'],
                'avg_mpg' => round($driverReports->avg('mpg'), 2),
                'total_reports' => $driverReports->count(),
                'total_gallons' => $driverReports->sum('volume'),
            ];
        })->sortByDesc('avg_mpg')->values();

        // Seasonal analysis
        $seasonalEfficiency = $this->analyzeSeasonalEfficiency($monthlyEfficiency);

        return [
            'bus_uuid' => $bus->uuid,
            'bus_number' => $bus->bus_number,
            'period_months' => $months,
            'summary' => [
                'overall_avg_mpg' => round($reportsWithMPG->avg('mpg'), 2),
                'best_month_mpg' => $monthlyEfficiency->max('avg_mpg'),
                'worst_month_mpg' => $monthlyEfficiency->min('avg_mpg'),
                'total_reports' => $reportsWithMPG->count(),
                'total_gallons' => round($reportsWithMPG->sum('volume'), 2),
            ],
            'monthly_trends' => $monthlyEfficiency,
            'trend_analysis' => $trend,
            'driver_efficiency' => $driverEfficiency,
            'seasonal_analysis' => $seasonalEfficiency,
            'recommendations' => $this->generateEfficiencyRecommendations($trend, $driverEfficiency),
        ];
    }

    /**
     * Analyze efficiency trend.
     */
    private function analyzeEfficiencyTrend(Collection $mpgValues): array
    {
        if ($mpgValues->count() < 3) {
            return ['trend' => 'insufficient_data'];
        }

        $recentValues = $mpgValues->slice(-3); // Last 3 months
        $olderValues = $mpgValues->slice(0, -3);

        if ($olderValues->isEmpty()) {
            return ['trend' => 'stable'];
        }

        $recentAvg = $recentValues->avg();
        $olderAvg = $olderValues->avg();

        $change = $recentAvg - $olderAvg;
        $changePercent = $olderAvg > 0 ? round(($change / $olderAvg) * 100, 1) : 0;

        if ($change > 0.5) {
            return [
                'trend' => 'improving',
                'change_mpg' => round($change, 2),
                'change_percentage' => $changePercent,
            ];
        } elseif ($change < -0.5) {
            return [
                'trend' => 'declining',
                'change_mpg' => round($change, 2),
                'change_percentage' => $changePercent,
            ];
        } else {
            return ['trend' => 'stable'];
        }
    }

    /**
     * Analyze seasonal efficiency patterns.
     */
    private function analyzeSeasonalEfficiency(Collection $monthlyEfficiency): array
    {
        $byMonth = $monthlyEfficiency->groupBy(function ($month) {
            return Carbon::parse($month['month'])->month;
        });

        return $byMonth->map(function ($monthData, $monthNum) {
            return [
                'month' => $monthNum,
                'month_name' => Carbon::create()->month($monthNum)->format('F'),
                'avg_mpg' => round($monthData->avg('avg_mpg'), 2),
                'data_points' => $monthData->count(),
            ];
        })->sortBy('month')->values();
    }

    /**
     * Generate efficiency recommendations.
     */
    private function generateEfficiencyRecommendations(array $trend, Collection $driverEfficiency): array
    {
        $recommendations = [];

        if ($trend['trend'] === 'declining') {
            $recommendations[] = 'Fuel efficiency is declining by ' . abs($trend['change_percentage']) . '%. Check for maintenance issues or driving habits.';
        } elseif ($trend['trend'] === 'improving') {
            $recommendations[] = 'Fuel efficiency is improving by ' . $trend['change_percentage'] . '%. Continue current practices.';
        }

        if ($driverEfficiency->count() > 1) {
            $bestDriver = $driverEfficiency->first();
            $worstDriver = $driverEfficiency->last();

            if ($bestDriver['avg_mpg'] - $worstDriver['avg_mpg'] > 2) {
                $recommendations[] = 'Significant efficiency difference between drivers. Consider training for drivers with lower MPG.';
            }
        }

        return $recommendations;
    }

    /**
     * Get fuel reports for analysis.
     */
    private function getFuelReports(Bus $bus, int $months): Collection
    {
        // This would integrate with FleetOps FuelReport model
        // For now, return empty collection as placeholder
        return collect([]);
    }
}
