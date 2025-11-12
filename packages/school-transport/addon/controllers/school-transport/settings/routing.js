import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class SchoolTransportSettingsRoutingController extends Controller {
    @service fetch;
    @service notifications;
    @service currentUser;
    @service leafletRoutingControl;
    
    // Base FleetOps routing settings
    @tracked routerService = 'osrm';
    @tracked routingUnit = 'km';
    @tracked routingUnitOptions = [
        { label: 'Kilometers', value: 'km' },
        { label: 'Miles', value: 'mi' },
    ];
    
    // School-specific routing settings
    @tracked schoolZoneSpeedLimit = 25;
    @tracked busStopDwellTime = 2;
    @tracked routeOptimizationEnabled = true;
    @tracked trafficAvoidance = true;
    @tracked weatherRouting = true;
    @tracked accessibilityRouting = true;
    @tracked minimizeWalkDistance = true;
    @tracked considerGradeLevels = true;
    
    @tracked speedLimitOptions = [
        { label: '15 mph', value: 15 },
        { label: '20 mph', value: 20 },
        { label: '25 mph', value: 25 },
        { label: '30 mph', value: 30 },
        { label: '35 mph', value: 35 },
    ];
    
    @tracked dwellTimeOptions = [
        { label: '1 minute', value: 1 },
        { label: '2 minutes', value: 2 },
        { label: '3 minutes', value: 3 },
        { label: '4 minutes', value: 4 },
        { label: '5 minutes', value: 5 },
    ];

    constructor() {
        super(...arguments);
        this.getSettings.perform();
    }

    /**
     * Save school transport routing settings.
     * Extends FleetOps routing with school-specific options.
     *
     * @memberof SchoolTransportSettingsRoutingController
     */
    @task *saveSettings() {
        try {
            // Save school-specific routing settings
            yield this.fetch.post('school-transport/settings/routing-settings', { 
                routingSettings: {
                    school_zone_speed_limit: this.schoolZoneSpeedLimit,
                    bus_stop_dwell_time: this.busStopDwellTime,
                    route_optimization_enabled: this.routeOptimizationEnabled,
                    traffic_avoidance: this.trafficAvoidance,
                    weather_routing: this.weatherRouting,
                    accessibility_routing: this.accessibilityRouting,
                    minimize_walk_distance: this.minimizeWalkDistance,
                    consider_grade_levels: this.considerGradeLevels,
                }
            });

            // Also save base FleetOps routing settings
            yield this.fetch.post('fleet-ops/settings/routing-settings', { 
                router: this.routerService, 
                unit: this.routingUnit 
            });

            // Save in local memory too
            this.currentUser.setOption('routing', { 
                router: this.routerService, 
                unit: this.routingUnit,
                school_zone_speed_limit: this.schoolZoneSpeedLimit,
                bus_stop_dwell_time: this.busStopDwellTime,
            });

            this.notifications.success('School transport routing settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    /**
     * Get school transport routing settings.
     *
     * @memberof SchoolTransportSettingsRoutingController
     */
    @task *getSettings() {
        try {
            const settings = yield this.fetch.get('school-transport/settings/routing-settings');
            
            // Set base FleetOps settings
            this.routerService = settings.router || 'osrm';
            this.routingUnit = settings.unit || 'km';
            
            // Set school-specific settings
            this.schoolZoneSpeedLimit = settings.school_zone_speed_limit || 25;
            this.busStopDwellTime = settings.bus_stop_dwell_time || 2;
            this.routeOptimizationEnabled = settings.route_optimization_enabled !== false;
            this.trafficAvoidance = settings.traffic_avoidance !== false;
            this.weatherRouting = settings.weather_routing !== false;
            this.accessibilityRouting = settings.accessibility_routing !== false;
            this.minimizeWalkDistance = settings.minimize_walk_distance !== false;
            this.considerGradeLevels = settings.consider_grade_levels !== false;
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}