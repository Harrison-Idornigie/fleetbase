import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportAssignmentsNewController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked currentStep = 1;
  @tracked totalSteps = 3;
  @tracked selectedTab = 'basic-info';

  // Basic assignment info
  @tracked routeId = '';
  @tracked vehicleId = '';
  @tracked driverId = '';
  @tracked scheduledDate = '';
  @tracked scheduledTime = '';
  @tracked estimatedDuration = '';
  @tracked status = 'scheduled';

  // Additional details
  @tracked notes = '';
  @tracked specialInstructions = '';
  @tracked weatherDependent = false;
  @tracked backupDriverId = '';
  @tracked backupVehicleId = '';

  // Student assignments
  @tracked selectedStudents = [];
  @tracked availableStudents = [];

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
      1: 'Route & Schedule',
      2: 'Vehicle & Driver',
      3: 'Students & Details'
    };
    return titles[this.currentStep] || 'Create Assignment';
  }

  get stepDescription() {
    const descriptions = {
      1: 'Select the route and schedule the assignment',
      2: 'Assign vehicle and driver to the route',
      3: 'Assign students and add additional details'
    };
    return descriptions[this.currentStep] || '';
  }

  get availableRoutes() {
    return this.model.routes || [];
  }

  get availableVehicles() {
    return this.model.vehicles || [];
  }

  get availableDrivers() {
    return this.model.drivers || [];
  }

  get selectedRoute() {
    return this.availableRoutes.find(route => route.id === this.routeId);
  }

  get selectedVehicle() {
    return this.availableVehicles.find(vehicle => vehicle.id === this.vehicleId);
  }

  get selectedDriver() {
    return this.availableDrivers.find(driver => driver.id === this.driverId);
  }

  get filteredStudents() {
    if (!this.selectedRoute) return [];

    // Get students assigned to this route
    // This would typically come from the backend
    return this.availableStudents.filter(student =>
      student.assigned_routes?.includes(this.routeId)
    );
  }

  get selectedStudentsCount() {
    return this.selectedStudents.length;
  }

  get maxCapacity() {
    return this.selectedVehicle?.capacity || this.selectedRoute?.max_capacity || 0;
  }

  get isOverCapacity() {
    return this.selectedStudentsCount > this.maxCapacity;
  }

  get capacityWarning() {
    if (this.isOverCapacity) {
      return `Warning: Selected ${this.selectedStudentsCount} students exceeds vehicle capacity of ${this.maxCapacity}`;
    }
    return null;
  }

  constructor() {
    super(...arguments);
    this.loadAvailableStudents();
  }

  async loadAvailableStudents() {
    try {
      // This would typically fetch from the backend
      this.availableStudents = [
        { id: '1', name: 'John Doe', grade: '5th', assigned_routes: ['route1', 'route2'] },
        { id: '2', name: 'Jane Smith', grade: '4th', assigned_routes: ['route1'] },
        { id: '3', name: 'Bob Johnson', grade: '3rd', assigned_routes: ['route2'] }
      ];
    } catch (error) {
      console.error('Error loading students:', error);
    }
  }

  validateCurrentStep() {
    this.errors = {};
    let isValid = true;

    switch (this.currentStep) {
      case 1:
        if (!this.routeId) {
          this.errors.routeId = 'Route selection is required';
          isValid = false;
        }
        if (!this.scheduledDate) {
          this.errors.scheduledDate = 'Scheduled date is required';
          isValid = false;
        }
        if (!this.scheduledTime) {
          this.errors.scheduledTime = 'Scheduled time is required';
          isValid = false;
        }
        break;

      case 2:
        if (!this.vehicleId) {
          this.errors.vehicleId = 'Vehicle selection is required';
          isValid = false;
        }
        if (!this.driverId) {
          this.errors.driverId = 'Driver selection is required';
          isValid = false;
        }
        break;

      case 3:
        if (this.selectedStudents.length === 0) {
          this.errors.selectedStudents = 'At least one student must be assigned';
          isValid = false;
        }
        if (this.isOverCapacity) {
          this.errors.capacity = 'Cannot exceed vehicle capacity';
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
    if (this.errors[field]) {
      delete this.errors[field];
    }

    // Special handling for route selection
    if (field === 'routeId') {
      this.onRouteChange();
    }
  }

  @action
  onRouteChange() {
    if (this.selectedRoute) {
      // Auto-populate estimated duration from route
      this.estimatedDuration = this.selectedRoute.estimated_duration || '';

      // Clear student selection when route changes
      this.selectedStudents = [];

      // Suggest vehicle and driver if available
      if (this.selectedRoute.assigned_vehicle_id && !this.vehicleId) {
        this.vehicleId = this.selectedRoute.assigned_vehicle_id;
      }
      if (this.selectedRoute.assigned_driver_id && !this.driverId) {
        this.driverId = this.selectedRoute.assigned_driver_id;
      }
    }
  }

  @action
  toggleStudentSelection(studentId, isSelected) {
    if (isSelected) {
      if (!this.isOverCapacity) {
        this.selectedStudents = [...this.selectedStudents, studentId];
      }
    } else {
      this.selectedStudents = this.selectedStudents.filter(id => id !== studentId);
    }
  }

  @action
  selectAllStudents() {
    if (this.selectedStudents.length === this.filteredStudents.length) {
      this.selectedStudents = [];
    } else {
      const availableIds = this.filteredStudents.map(s => s.id);
      const selectableCount = Math.min(availableIds.length, this.maxCapacity - this.selectedStudents.length);
      this.selectedStudents = [
        ...this.selectedStudents,
        ...availableIds.slice(0, selectableCount)
      ];
    }
  }

  @action
  clearStudentSelection() {
    this.selectedStudents = [];
  }

  @action
  async checkDriverAvailability() {
    if (!this.driverId || !this.scheduledDate || !this.scheduledTime) return;

    try {
      const response = await fetch(`/api/school-transport/drivers/${this.driverId}/availability`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          date: this.scheduledDate,
          time: this.scheduledTime,
          duration: this.estimatedDuration
        })
      });

      if (response.ok) {
        const result = await response.json();
        if (!result.available) {
          this.notifications.warning(`Driver is not available at this time. Conflicts: ${result.conflicts.join(', ')}`);
        }
      }
    } catch (error) {
      console.error('Error checking driver availability:', error);
    }
  }

  @action
  async checkVehicleAvailability() {
    if (!this.vehicleId || !this.scheduledDate || !this.scheduledTime) return;

    try {
      const response = await fetch(`/api/school-transport/vehicles/${this.vehicleId}/availability`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          date: this.scheduledDate,
          time: this.scheduledTime,
          duration: this.estimatedDuration
        })
      });

      if (response.ok) {
        const result = await response.json();
        if (!result.available) {
          this.notifications.warning(`Vehicle is not available at this time. Conflicts: ${result.conflicts.join(', ')}`);
        }
      }
    } catch (error) {
      console.error('Error checking vehicle availability:', error);
    }
  }

  @action
  async createRecurringAssignments() {
    // This would create multiple assignments based on a pattern
    this.notifications.info('Recurring assignment creation coming soon');
  }

  @action
  async saveAssignment() {
    if (!this.validateCurrentStep()) {
      this.notifications.error('Please fix the validation errors before saving');
      return;
    }

    this.isLoading = true;
    try {
      const assignmentData = {
        route_id: this.routeId,
        vehicle_id: this.vehicleId,
        driver_id: this.driverId,
        scheduled_date: this.scheduledDate,
        scheduled_time: this.scheduledTime,
        estimated_duration: this.estimatedDuration,
        status: this.status,
        notes: this.notes,
        special_instructions: this.specialInstructions,
        weather_dependent: this.weatherDependent,
        backup_driver_id: this.backupDriverId,
        backup_vehicle_id: this.backupVehicleId,
        assigned_students: this.selectedStudents
      };

      const newAssignment = this.store.createRecord('school-transport/assignment', assignmentData);
      await newAssignment.save();

      this.notifications.success('Assignment created successfully');
      this.router.transitionTo('school-transport.assignments.view', newAssignment.id);
    } catch (error) {
      console.error('Error saving assignment:', error);
      this.notifications.error('Failed to create assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  cancelCreation() {
    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
      this.router.transitionTo('school-transport.assignments.index');
    }
  }

  @action
  async validateAndProceed() {
    if (this.validateCurrentStep()) {
      if (this.isLastStep) {
        await this.saveAssignment();
      } else {
        this.nextStep();
      }
    } else {
      this.notifications.error('Please fix the validation errors');
    }
  }

  @action
  async quickAssignFromRoute(routeId) {
    // Pre-populate form with route data
    this.routeId = routeId;
    this.onRouteChange();

    // Set default date/time to next occurrence
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    this.scheduledDate = tomorrow.toISOString().split('T')[0];

    // Set time based on route type
    if (this.selectedRoute?.type === 'morning') {
      this.scheduledTime = '07:00';
    } else if (this.selectedRoute?.type === 'afternoon') {
      this.scheduledTime = '15:00';
    }

    this.notifications.info('Form pre-populated with route data');
  }

  @action
  async duplicateLastAssignment() {
    try {
      // This would fetch the last assignment and pre-populate the form
      const lastAssignment = await this.store.query('school-transport/assignment', {
        sort: '-created_at',
        limit: 1
      });

      if (lastAssignment.length > 0) {
        const assignment = lastAssignment[0];
        this.routeId = assignment.route_id;
        this.vehicleId = assignment.vehicle_id;
        this.driverId = assignment.driver_id;
        this.estimatedDuration = assignment.estimated_duration;
        this.selectedStudents = assignment.assigned_students || [];
        this.notes = assignment.notes;
        this.specialInstructions = assignment.special_instructions;

        this.notifications.success('Form populated with last assignment data');
      }
    } catch (error) {
      console.error('Error duplicating last assignment:', error);
      this.notifications.error('Failed to duplicate last assignment');
    }
  }

  @action
  async importStudentsFromRoute() {
    if (!this.routeId) {
      this.notifications.error('Please select a route first');
      return;
    }

    try {
      // This would fetch students assigned to the route
      const response = await fetch(`/api/school-transport/routes/${this.routeId}/students`);
      if (response.ok) {
        const students = await response.json();
        this.selectedStudents = students.map(s => s.id);
        this.notifications.success(`Imported ${students.length} students from route`);
      }
    } catch (error) {
      console.error('Error importing students:', error);
      this.notifications.error('Failed to import students from route');
    }
  }
}