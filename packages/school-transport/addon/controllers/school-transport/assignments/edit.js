import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportAssignmentsEditController extends Controller {
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

  get assignment() {
    return this.model.assignment;
  }

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
    return titles[this.currentStep] || 'Edit Assignment';
  }

  get stepDescription() {
    const descriptions = {
      1: 'Update the route and schedule information',
      2: 'Change vehicle and driver assignments',
      3: 'Modify student assignments and additional details'
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
    this.initializeFormData();
    this.loadAvailableStudents();
  }

  initializeFormData() {
    if (this.assignment) {
      // Basic info
      this.routeId = this.assignment.route_id || '';
      this.vehicleId = this.assignment.vehicle_id || '';
      this.driverId = this.assignment.driver_id || '';
      this.scheduledDate = this.assignment.scheduled_date || '';
      this.scheduledTime = this.assignment.scheduled_time || '';
      this.estimatedDuration = this.assignment.estimated_duration || '';
      this.status = this.assignment.status || 'scheduled';

      // Additional details
      this.notes = this.assignment.notes || '';
      this.specialInstructions = this.assignment.special_instructions || '';
      this.weatherDependent = this.assignment.weather_dependent || false;
      this.backupDriverId = this.assignment.backup_driver_id || '';
      this.backupVehicleId = this.assignment.backup_vehicle_id || '';

      // Student assignments
      this.selectedStudents = this.assignment.assigned_students || [];
    }
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
      if (!this.estimatedDuration) {
        this.estimatedDuration = this.selectedRoute.estimated_duration || '';
      }

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
  async saveAssignment() {
    if (!this.validateCurrentStep()) {
      this.notifications.error('Please fix the validation errors before saving');
      return;
    }

    this.isLoading = true;
    try {
      // Update assignment model with form data
      this.assignment.route_id = this.routeId;
      this.assignment.vehicle_id = this.vehicleId;
      this.assignment.driver_id = this.driverId;
      this.assignment.scheduled_date = this.scheduledDate;
      this.assignment.scheduled_time = this.scheduledTime;
      this.assignment.estimated_duration = this.estimatedDuration;
      this.assignment.status = this.status;
      this.assignment.notes = this.notes;
      this.assignment.special_instructions = this.specialInstructions;
      this.assignment.weather_dependent = this.weatherDependent;
      this.assignment.backup_driver_id = this.backupDriverId;
      this.assignment.backup_vehicle_id = this.backupVehicleId;
      this.assignment.assigned_students = this.selectedStudents;

      await this.assignment.save();

      this.notifications.success('Assignment updated successfully');
      this.router.transitionTo('school-transport.assignments.view', this.assignment.id);
    } catch (error) {
      console.error('Error saving assignment:', error);
      this.notifications.error('Failed to update assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  cancelEdit() {
    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
      this.router.transitionTo('school-transport.assignments.view', this.assignment.id);
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
  async importStudentsFromRoute() {
    if (!this.routeId) {
      this.notifications.error('Please select a route first');
      return;
    }

    try {
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

  @action
  async reassignDriver() {
    // This would open a modal to reassign driver
    this.notifications.info('Driver reassignment feature coming soon');
  }

  @action
  async reassignVehicle() {
    // This would open a modal to reassign vehicle
    this.notifications.info('Vehicle reassignment feature coming soon');
  }

  @action
  async updateSchedule() {
    // This would allow updating the schedule with conflict checking
    this.notifications.info('Schedule update feature coming soon');
  }

  @action
  async addDelayNote() {
    const note = prompt('Enter delay reason:');
    if (note?.trim()) {
      this.notes = `${this.notes}\n\nDelay: ${note} (${new Date().toLocaleString()})`.trim();
      this.notifications.success('Delay note added');
    }
  }

  @action
  async requestBackup() {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/assignments/${this.assignment.id}/request-backup`, {
        method: 'POST'
      });

      if (response.ok) {
        this.notifications.success('Backup request sent successfully');
      } else {
        throw new Error('Backup request failed');
      }
    } catch (error) {
      console.error('Error requesting backup:', error);
      this.notifications.error('Failed to request backup');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async cancelAssignment() {
    if (!confirm('Are you sure you want to cancel this assignment?')) {
      return;
    }

    this.isLoading = true;
    try {
      this.assignment.status = 'cancelled';
      await this.assignment.save();
      this.notifications.success('Assignment cancelled successfully');
      this.router.transitionTo('school-transport.assignments.view', this.assignment.id);
    } catch (error) {
      console.error('Error cancelling assignment:', error);
      this.notifications.error('Failed to cancel assignment');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async duplicateAssignment() {
    this.isLoading = true;
    try {
      const duplicatedAssignment = this.store.createRecord('school-transport/assignment', {
        route_id: this.assignment.route_id,
        vehicle_id: this.assignment.vehicle_id,
        driver_id: this.assignment.driver_id,
        scheduled_date: null, // Will be set by user
        scheduled_time: this.assignment.scheduled_time,
        estimated_duration: this.assignment.estimated_duration,
        status: 'scheduled',
        notes: this.assignment.notes,
        special_instructions: this.assignment.special_instructions,
        weather_dependent: this.assignment.weather_dependent,
        assigned_students: [...(this.assignment.assigned_students || [])]
      });

      await duplicatedAssignment.save();
      this.notifications.success('Assignment duplicated successfully');
      this.router.transitionTo('school-transport.assignments.edit', duplicatedAssignment.id);
    } catch (error) {
      console.error('Error duplicating assignment:', error);
      this.notifications.error('Failed to duplicate assignment');
    } finally {
      this.isLoading = false;
    }
  }
}