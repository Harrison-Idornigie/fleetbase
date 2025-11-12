import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportRoutesNewController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked currentStep = 1;
  @tracked totalSteps = 4;
  @tracked selectedTab = 'basic-info';

  // Basic route info
  @tracked name = '';
  @tracked description = '';
  @tracked routeNumber = '';
  @tracked type = 'morning';
  @tracked status = 'active';
  @tracked distance = '';
  @tracked estimatedDuration = '';
  @tracked startTime = '';
  @tracked endTime = '';

  // Stops and waypoints
  @tracked stops = [];
  @tracked waypoints = [];

  // Vehicle assignment
  @tracked assignedVehicleId = '';
  @tracked assignedDriverId = '';

  // Schedule and recurrence
  @tracked isRecurring = false;
  @tracked recurrencePattern = 'daily';
  @tracked recurrenceDays = [];
  @tracked startDate = '';
  @tracked endDate = '';

  // Additional settings
  @tracked maxCapacity = '';
  @tracked specialInstructions = '';
  @tracked weatherDependent = false;
  @tracked backupRouteId = '';

  // Validation errors
  @tracked errors = {};

  get isFirstStep() {
    return this.currentStep === 1;
  }

  get isLastStep() {
    return this.currentStep === this.totalSteps;
  }

  get progressPercentage() {
    return Math.round((this.currentStep / this.totalSteps) * 100);
  }

  get canProceed() {
    return this.validateCurrentStep();
  }

  get stepTitle() {
    const titles = {
      1: 'Basic Information',
      2: 'Route Planning',
      3: 'Vehicle & Driver Assignment',
      4: 'Schedule & Settings'
    };
    return titles[this.currentStep] || 'Create Route';
  }

  get stepDescription() {
    const descriptions = {
      1: 'Enter the basic route information and settings',
      2: 'Plan the route stops and waypoints',
      3: 'Assign vehicle and driver to the route',
      4: 'Set up schedule and additional configurations'
    };
    return descriptions[this.currentStep] || '';
  }

  get typeOptions() {
    return [
      { value: 'morning', label: 'Morning Pickup' },
      { value: 'afternoon', label: 'Afternoon Drop-off' },
      { value: 'special', label: 'Special Route' },
      { value: 'field_trip', label: 'Field Trip' }
    ];
  }

  get statusOptions() {
    return [
      { value: 'active', label: 'Active' },
      { value: 'inactive', label: 'Inactive' },
      { value: 'maintenance', label: 'Maintenance' },
      { value: 'cancelled', label: 'Cancelled' }
    ];
  }

  get recurrenceOptions() {
    return [
      { value: 'daily', label: 'Daily' },
      { value: 'weekdays', label: 'Weekdays Only' },
      { value: 'weekly', label: 'Weekly' },
      { value: 'custom', label: 'Custom Days' }
    ];
  }

  get dayOptions() {
    return [
      { value: 'monday', label: 'Monday' },
      { value: 'tuesday', label: 'Tuesday' },
      { value: 'wednesday', label: 'Wednesday' },
      { value: 'thursday', label: 'Thursday' },
      { value: 'friday', label: 'Friday' },
      { value: 'saturday', label: 'Saturday' },
      { value: 'sunday', label: 'Sunday' }
    ];
  }

  get availableVehicles() {
    return this.model.vehicles || [];
  }

  get availableDrivers() {
    return this.model.drivers || [];
  }

  get availableBackupRoutes() {
    return this.model.routes?.filter(route => route.id !== this.route?.id) || [];
  }

  get totalStops() {
    return this.stops.length;
  }

  get totalDistance() {
    // Calculate total distance from stops
    let total = 0;
    for (let i = 1; i < this.stops.length; i++) {
      const prevStop = this.stops[i - 1];
      const currentStop = this.stops[i];
      if (prevStop.distance_to_next) {
        total += parseFloat(prevStop.distance_to_next) || 0;
      }
    }
    return total.toFixed(2);
  }

  validateCurrentStep() {
    this.errors = {};
    let isValid = true;

    switch (this.currentStep) {
      case 1:
        if (!this.name.trim()) {
          this.errors.name = 'Route name is required';
          isValid = false;
        }
        if (!this.type) {
          this.errors.type = 'Route type is required';
          isValid = false;
        }
        if (this.distance && isNaN(parseFloat(this.distance))) {
          this.errors.distance = 'Distance must be a valid number';
          isValid = false;
        }
        break;

      case 2:
        if (this.stops.length < 2) {
          this.errors.stops = 'At least 2 stops are required (start and end)';
          isValid = false;
        }
        // Validate stop addresses
        this.stops.forEach((stop, index) => {
          if (!stop.address?.trim()) {
            this.errors[`stop_${index}_address`] = `Stop ${index + 1} address is required`;
            isValid = false;
          }
        });
        break;

      case 3:
        if (!this.assignedVehicleId) {
          this.errors.assignedVehicleId = 'Vehicle assignment is required';
          isValid = false;
        }
        if (!this.assignedDriverId) {
          this.errors.assignedDriverId = 'Driver assignment is required';
          isValid = false;
        }
        break;

      case 4:
        if (this.isRecurring && !this.startDate) {
          this.errors.startDate = 'Start date is required for recurring routes';
          isValid = false;
        }
        if (this.maxCapacity && isNaN(parseInt(this.maxCapacity))) {
          this.errors.maxCapacity = 'Max capacity must be a valid number';
          isValid = false;
        }
        break;
    }

    return isValid;
  }

  @action
  selectTab(tab) {
    this.selectedTab = tab;
  }

  @action
  nextStep() {
    if (this.canProceed && !this.isLastStep) {
      this.currentStep++;
    }
  }

  @action
  previousStep() {
    if (!this.isFirstStep) {
      this.currentStep--;
    }
  }

  @action
  goToStep(step) {
    if (step >= 1 && step <= this.totalSteps) {
      this.currentStep = step;
    }
  }

  @action
  updateField(field, value) {
    this[field] = value;
    // Clear error for this field if it exists
    if (this.errors[field]) {
      delete this.errors[field];
    }
  }

  @action
  addStop() {
    const newStop = {
      id: Date.now().toString(),
      address: '',
      latitude: null,
      longitude: null,
      stop_order: this.stops.length + 1,
      estimated_arrival: '',
      estimated_departure: '',
      distance_to_next: '',
      students: [],
      special_instructions: ''
    };
    this.stops = [...this.stops, newStop];
  }

  @action
  removeStop(stopId) {
    this.stops = this.stops.filter(stop => stop.id !== stopId);
    // Reorder remaining stops
    this.stops.forEach((stop, index) => {
      stop.stop_order = index + 1;
    });
  }

  @action
  updateStop(stopId, field, value) {
    this.stops = this.stops.map(stop => {
      if (stop.id === stopId) {
        return { ...stop, [field]: value };
      }
      return stop;
    });
  }

  @action
  reorderStops(newOrder) {
    this.stops = newOrder.map((stop, index) => ({
      ...stop,
      stop_order: index + 1
    }));
  }

  @action
  addWaypoint() {
    const newWaypoint = {
      id: Date.now().toString(),
      address: '',
      latitude: null,
      longitude: null,
      instructions: ''
    };
    this.waypoints = [...this.waypoints, newWaypoint];
  }

  @action
  removeWaypoint(waypointId) {
    this.waypoints = this.waypoints.filter(waypoint => waypoint.id !== waypointId);
  }

  @action
  updateWaypoint(waypointId, field, value) {
    this.waypoints = this.waypoints.map(waypoint => {
      if (waypoint.id === waypointId) {
        return { ...waypoint, [field]: value };
      }
      return waypoint;
    });
  }

  @action
  toggleRecurrence() {
    this.isRecurring = !this.isRecurring;
    if (!this.isRecurring) {
      this.recurrencePattern = 'daily';
      this.recurrenceDays = [];
      this.startDate = '';
      this.endDate = '';
    }
  }

  @action
  updateRecurrenceDays(day, isSelected) {
    if (isSelected) {
      this.recurrenceDays = [...this.recurrenceDays, day];
    } else {
      this.recurrenceDays = this.recurrenceDays.filter(d => d !== day);
    }
  }

  @action
  async geocodeAddress(stopId, address) {
    if (!address?.trim()) return;

    try {
      // This would integrate with a geocoding service
      const response = await fetch(`/api/geocode?address=${encodeURIComponent(address)}`);
      if (response.ok) {
        const result = await response.json();
        this.updateStop(stopId, 'latitude', result.latitude);
        this.updateStop(stopId, 'longitude', result.longitude);
      }
    } catch (error) {
      console.error('Geocoding failed:', error);
    }
  }

  @action
  async calculateRoute() {
    if (this.stops.length < 2) {
      this.notifications.error('At least 2 stops are required to calculate route');
      return;
    }

    this.isLoading = true;
    try {
      const coordinates = this.stops.map(stop => ({
        lat: stop.latitude,
        lng: stop.longitude
      })).filter(coord => coord.lat && coord.lng);

      if (coordinates.length < 2) {
        this.notifications.error('Valid coordinates required for route calculation');
        return;
      }

      // This would integrate with a routing service
      const response = await fetch('/api/route/calculate', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ coordinates })
      });

      if (response.ok) {
        const routeData = await response.json();
        this.distance = routeData.totalDistance;
        this.estimatedDuration = routeData.totalDuration;

        // Update stop distances
        routeData.legs.forEach((leg, index) => {
          if (this.stops[index]) {
            this.updateStop(this.stops[index].id, 'distance_to_next', leg.distance);
          }
        });

        this.notifications.success('Route calculated successfully');
      } else {
        throw new Error('Route calculation failed');
      }
    } catch (error) {
      console.error('Error calculating route:', error);
      this.notifications.error('Failed to calculate route');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async saveRoute() {
    if (!this.validateCurrentStep()) {
      this.notifications.error('Please fix the validation errors before saving');
      return;
    }

    this.isLoading = true;
    try {
      const routeData = {
        name: this.name,
        description: this.description,
        route_number: this.routeNumber || null,
        type: this.type,
        status: this.status,
        distance: parseFloat(this.distance) || null,
        estimated_duration: this.estimatedDuration,
        start_time: this.startTime,
        end_time: this.endTime,
        stops: this.stops,
        waypoints: this.waypoints,
        assigned_vehicle_id: this.assignedVehicleId,
        assigned_driver_id: this.assignedDriverId,
        is_recurring: this.isRecurring,
        recurrence_pattern: this.recurrencePattern,
        recurrence_days: this.recurrenceDays,
        start_date: this.startDate,
        end_date: this.endDate,
        max_capacity: parseInt(this.maxCapacity) || null,
        special_instructions: this.specialInstructions,
        weather_dependent: this.weatherDependent,
        backup_route_id: this.backupRouteId
      };

      const newRoute = this.store.createRecord('school-transport/route', routeData);
      await newRoute.save();

      this.notifications.success('Route created successfully');
      this.router.transitionTo('school-transport.routes.view', newRoute.id);
    } catch (error) {
      console.error('Error saving route:', error);
      this.notifications.error('Failed to create route');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  cancelCreation() {
    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
      this.router.transitionTo('school-transport.routes.index');
    }
  }

  @action
  async validateAndProceed() {
    if (this.validateCurrentStep()) {
      if (this.isLastStep) {
        await this.saveRoute();
      } else {
        this.nextStep();
      }
    } else {
      this.notifications.error('Please fix the validation errors');
    }
  }

  @action
  importStopsFromCSV() {
    // This would open a file picker and parse CSV data
    this.notifications.info('CSV import functionality coming soon');
  }

  @action
  optimizeRoute() {
    // This would use route optimization algorithms
    this.notifications.info('Route optimization functionality coming soon');
  }
}