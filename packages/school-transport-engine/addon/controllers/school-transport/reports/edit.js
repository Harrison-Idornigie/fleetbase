import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportReportsEditController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked currentStep = 1;
  @tracked totalSteps = 4;
  @tracked selectedTab = 'basic';

  // Basic report info
  @tracked reportName = '';
  @tracked reportDescription = '';
  @tracked reportType = 'attendance';
  @tracked reportCategory = 'daily';
  @tracked isScheduled = false;

  // Date range configuration
  @tracked dateRangeType = 'preset';
  @tracked presetRange = 'last_30_days';
  @tracked customStartDate = '';
  @tracked customEndDate = '';

  // Filters and parameters
  @tracked selectedRoutes = [];
  @tracked selectedStudents = [];
  @tracked selectedDrivers = [];
  @tracked selectedSchools = [];
  @tracked includeInactive = false;
  @tracked groupBy = 'none';

  // Report-specific options
  @tracked attendanceOptions = {
    include_absences: true,
    include_tardiness: true,
    include_early_departures: true,
    include_no_shows: true
  };

  @tracked routeEfficiencyOptions = {
    include_distance: true,
    include_duration: true,
    include_fuel_cost: true,
    include_delays: true,
    include_optimization_suggestions: true
  };

  @tracked safetyOptions = {
    include_incidents: true,
    include_violations: true,
    include_inspections: true,
    include_training: true,
    include_compliance_status: true
  };

  // Scheduling options
  @tracked scheduleFrequency = 'weekly';
  @tracked scheduleTime = '09:00';
  @tracked scheduleDays = ['monday'];
  @tracked scheduleRecipients = [];

  // Output options
  @tracked outputFormat = 'pdf';
  @tracked includeCharts = true;
  @tracked includeRawData = false;
  @tracked emailOnCompletion = true;

  // Original report data
  @tracked originalReport = null;
  @tracked hasUnsavedChanges = false;

  // Validation errors
  @tracked errors = {};

  // Available data
  @tracked availableRoutes = [];
  @tracked availableStudents = [];
  @tracked availableDrivers = [];
  @tracked availableSchools = [];

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
      1: 'Report Details',
      2: 'Date & Filters',
      3: 'Report Options',
      4: 'Schedule & Output'
    };
    return titles[this.currentStep] || 'Edit Report';
  }

  get stepDescription() {
    const descriptions = {
      1: 'Update basic information about your report',
      2: 'Modify date range and filters',
      3: 'Adjust report-specific options and parameters',
      4: 'Update scheduling and output settings'
    };
    return descriptions[this.currentStep] || '';
  }

  get reportTypeOptions() {
    return [
      { value: 'attendance', label: 'Attendance Report', description: 'Student attendance, absences, and punctuality' },
      { value: 'route_efficiency', label: 'Route Efficiency', description: 'Route performance, distance, and optimization' },
      { value: 'safety_compliance', label: 'Safety & Compliance', description: 'Safety incidents, inspections, and compliance' },
      { value: 'financial', label: 'Financial Report', description: 'Costs, revenue, and financial metrics' },
      { value: 'custom', label: 'Custom Report', description: 'Build your own report with custom metrics' }
    ];
  }

  get categoryOptions() {
    return [
      { value: 'daily', label: 'Daily' },
      { value: 'weekly', label: 'Weekly' },
      { value: 'monthly', label: 'Monthly' },
      { value: 'quarterly', label: 'Quarterly' },
      { value: 'annual', label: 'Annual' }
    ];
  }

  get presetRangeOptions() {
    return [
      { value: 'today', label: 'Today' },
      { value: 'yesterday', label: 'Yesterday' },
      { value: 'last_7_days', label: 'Last 7 Days' },
      { value: 'last_30_days', label: 'Last 30 Days' },
      { value: 'last_90_days', label: 'Last 90 Days' },
      { value: 'this_week', label: 'This Week' },
      { value: 'last_week', label: 'Last Week' },
      { value: 'this_month', label: 'This Month' },
      { value: 'last_month', label: 'Last Month' },
      { value: 'this_year', label: 'This Year' }
    ];
  }

  get groupByOptions() {
    return [
      { value: 'none', label: 'No Grouping' },
      { value: 'route', label: 'By Route' },
      { value: 'school', label: 'By School' },
      { value: 'driver', label: 'By Driver' },
      { value: 'date', label: 'By Date' },
      { value: 'week', label: 'By Week' },
      { value: 'month', label: 'By Month' }
    ];
  }

  get frequencyOptions() {
    return [
      { value: 'daily', label: 'Daily' },
      { value: 'weekly', label: 'Weekly' },
      { value: 'monthly', label: 'Monthly' },
      { value: 'quarterly', label: 'Quarterly' }
    ];
  }

  get outputFormatOptions() {
    return [
      { value: 'pdf', label: 'PDF Document' },
      { value: 'excel', label: 'Excel Spreadsheet' },
      { value: 'csv', label: 'CSV File' },
      { value: 'json', label: 'JSON Data' }
    ];
  }

  get selectedRoutesCount() {
    return this.selectedRoutes.length;
  }

  get selectedStudentsCount() {
    return this.selectedStudents.length;
  }

  get selectedDriversCount() {
    return this.selectedDrivers.length;
  }

  get selectedSchoolsCount() {
    return this.selectedSchools.length;
  }

  get currentOptions() {
    switch (this.reportType) {
      case 'attendance':
        return this.attendanceOptions;
      case 'route_efficiency':
        return this.routeEfficiencyOptions;
      case 'safety_compliance':
        return this.safetyOptions;
      default:
        return {};
    }
  }

  get dateRangePreview() {
    if (this.dateRangeType === 'preset') {
      return this.presetRange.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    } else {
      if (this.customStartDate && this.customEndDate) {
        return `${this.customStartDate} to ${this.customEndDate}`;
      }
      return 'Select date range';
    }
  }

  get estimatedReportSize() {
    let size = 1;

    if (this.selectedRoutes.length > 0) size *= this.selectedRoutes.length;
    if (this.selectedStudents.length > 0) size *= this.selectedStudents.length;
    if (this.selectedDrivers.length > 0) size *= this.selectedDrivers.length;

    if (size > 1000) return 'Large (>1000 records)';
    if (size > 100) return 'Medium (100-1000 records)';
    return 'Small (<100 records)';
  }

  get hasChanges() {
    if (!this.originalReport) return false;

    return (
      this.reportName !== this.originalReport.name ||
      this.reportDescription !== this.originalReport.description ||
      this.reportType !== this.originalReport.type ||
      this.reportCategory !== this.originalReport.category ||
      this.dateRangeType !== this.originalReport.date_range_type ||
      this.presetRange !== this.originalReport.preset_range ||
      this.customStartDate !== this.originalReport.custom_start_date ||
      this.customEndDate !== this.originalReport.custom_end_date ||
      JSON.stringify(this.selectedRoutes) !== JSON.stringify(this.originalReport.selected_routes || []) ||
      JSON.stringify(this.selectedStudents) !== JSON.stringify(this.originalReport.selected_students || []) ||
      JSON.stringify(this.selectedDrivers) !== JSON.stringify(this.originalReport.selected_drivers || []) ||
      JSON.stringify(this.selectedSchools) !== JSON.stringify(this.originalReport.selected_schools || []) ||
      this.includeInactive !== this.originalReport.include_inactive ||
      this.groupBy !== this.originalReport.group_by ||
      JSON.stringify(this.currentOptions) !== JSON.stringify(this.originalReport.options || {}) ||
      this.outputFormat !== this.originalReport.output_format ||
      this.includeCharts !== this.originalReport.include_charts ||
      this.includeRawData !== this.originalReport.include_raw_data ||
      this.emailOnCompletion !== this.originalReport.email_on_completion
    );
  }

  get canSaveDraft() {
    return this.originalReport?.status === 'draft' || this.originalReport?.status === 'failed';
  }

  get canRegenerate() {
    return this.originalReport?.status === 'completed' || this.originalReport?.status === 'failed';
  }

  constructor() {
    super(...arguments);
    this.originalReport = this.model.report;
    this.initializeFromReport();
    this.loadAvailableData();
  }

  initializeFromReport() {
    if (!this.originalReport) return;

    this.reportName = this.originalReport.name || '';
    this.reportDescription = this.originalReport.description || '';
    this.reportType = this.originalReport.type || 'attendance';
    this.reportCategory = this.originalReport.category || 'daily';
    this.dateRangeType = this.originalReport.date_range_type || 'preset';
    this.presetRange = this.originalReport.preset_range || 'last_30_days';
    this.customStartDate = this.originalReport.custom_start_date || '';
    this.customEndDate = this.originalReport.custom_end_date || '';
    this.selectedRoutes = this.originalReport.selected_routes || [];
    this.selectedStudents = this.originalReport.selected_students || [];
    this.selectedDrivers = this.originalReport.selected_drivers || [];
    this.selectedSchools = this.originalReport.selected_schools || [];
    this.includeInactive = this.originalReport.include_inactive || false;
    this.groupBy = this.originalReport.group_by || 'none';
    this.outputFormat = this.originalReport.output_format || 'pdf';
    this.includeCharts = this.originalReport.include_charts !== false;
    this.includeRawData = this.originalReport.include_raw_data || false;
    this.emailOnCompletion = this.originalReport.email_on_completion !== false;

    // Load type-specific options
    if (this.originalReport.options) {
      switch (this.reportType) {
        case 'attendance':
          this.attendanceOptions = { ...this.attendanceOptions, ...this.originalReport.options };
          break;
        case 'route_efficiency':
          this.routeEfficiencyOptions = { ...this.routeEfficiencyOptions, ...this.originalReport.options };
          break;
        case 'safety_compliance':
          this.safetyOptions = { ...this.safetyOptions, ...this.originalReport.options };
          break;
      }
    }
  }

  async loadAvailableData() {
    try {
      this.availableRoutes = await this.store.query('school-transport/route', {});
      this.availableStudents = await this.store.query('school-transport/student', {});
      this.availableDrivers = await this.store.query('school-transport/driver', {});
      this.availableSchools = await this.store.query('school-transport/school', {});
    } catch (error) {
      console.error('Error loading available data:', error);
    }
  }

  validateCurrentStep() {
    this.errors = {};
    let isValid = true;

    switch (this.currentStep) {
      case 1:
        if (!this.reportName.trim()) {
          this.errors.reportName = 'Report name is required';
          isValid = false;
        }
        if (!this.reportDescription.trim()) {
          this.errors.reportDescription = 'Report description is required';
          isValid = false;
        }
        break;

      case 2:
        if (this.dateRangeType === 'custom') {
          if (!this.customStartDate) {
            this.errors.customStartDate = 'Start date is required';
            isValid = false;
          }
          if (!this.customEndDate) {
            this.errors.customEndDate = 'End date is required';
            isValid = false;
          }
          if (this.customStartDate && this.customEndDate && this.customStartDate > this.customEndDate) {
            this.errors.customEndDate = 'End date must be after start date';
            isValid = false;
          }
        }
        break;

      case 3:
        // Report options validation
        break;

      case 4:
        // Schedule and output validation
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
    this.hasUnsavedChanges = this.hasChanges;
    if (this.errors[field]) {
      delete this.errors[field];
    }
  }

  @action
  updateReportType(type) {
    this.reportType = type;
    this.resetTypeSpecificOptions();
    this.hasUnsavedChanges = this.hasChanges;
  }

  resetTypeSpecificOptions() {
    this.attendanceOptions = {
      include_absences: true,
      include_tardiness: true,
      include_early_departures: true,
      include_no_shows: true
    };

    this.routeEfficiencyOptions = {
      include_distance: true,
      include_duration: true,
      include_fuel_cost: true,
      include_delays: true,
      include_optimization_suggestions: true
    };

    this.safetyOptions = {
      include_incidents: true,
      include_violations: true,
      include_inspections: true,
      include_training: true,
      include_compliance_status: true
    };
  }

  @action
  updateDateRangeType(type) {
    this.dateRangeType = type;
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  toggleRoute(routeId, isSelected) {
    if (isSelected) {
      this.selectedRoutes = [...this.selectedRoutes, routeId];
    } else {
      this.selectedRoutes = this.selectedRoutes.filter(id => id !== routeId);
    }
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  toggleStudent(studentId, isSelected) {
    if (isSelected) {
      this.selectedStudents = [...this.selectedStudents, studentId];
    } else {
      this.selectedStudents = this.selectedStudents.filter(id => id !== studentId);
    }
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  toggleDriver(driverId, isSelected) {
    if (isSelected) {
      this.selectedDrivers = [...this.selectedDrivers, driverId];
    } else {
      this.selectedDrivers = this.selectedDrivers.filter(id => id !== driverId);
    }
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  toggleSchool(schoolId, isSelected) {
    if (isSelected) {
      this.selectedSchools = [...this.selectedSchools, schoolId];
    } else {
      this.selectedSchools = this.selectedSchools.filter(id => id !== schoolId);
    }
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  selectAllRoutes() {
    if (this.selectedRoutes.length === this.availableRoutes.length) {
      this.selectedRoutes = [];
    } else {
      this.selectedRoutes = this.availableRoutes.map(r => r.id);
    }
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  selectAllStudents() {
    if (this.selectedStudents.length === this.availableStudents.length) {
      this.selectedStudents = [];
    } else {
      this.selectedStudents = this.availableStudents.map(s => s.id);
    }
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  clearAllSelections() {
    this.selectedRoutes = [];
    this.selectedStudents = [];
    this.selectedDrivers = [];
    this.selectedSchools = [];
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  updateOption(category, option, value) {
    this[category][option] = value;
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  toggleScheduleDay(day, isSelected) {
    if (isSelected) {
      this.scheduleDays = [...this.scheduleDays, day];
    } else {
      this.scheduleDays = this.scheduleDays.filter(d => d !== day);
    }
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  addScheduleRecipient(recipient) {
    this.scheduleRecipients = [...this.scheduleRecipients, recipient];
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  removeScheduleRecipient(index) {
    this.scheduleRecipients = this.scheduleRecipients.filter((_, i) => i !== index);
    this.hasUnsavedChanges = this.hasChanges;
  }

  @action
  async updateReport() {
    if (!this.validateCurrentStep()) {
      this.notifications.error('Please fix the validation errors before updating');
      return;
    }

    this.isLoading = true;
    try {
      const updateData = {
        name: this.reportName,
        description: this.reportDescription,
        type: this.reportType,
        category: this.reportCategory,
        date_range_type: this.dateRangeType,
        preset_range: this.presetRange,
        custom_start_date: this.customStartDate,
        custom_end_date: this.customEndDate,
        selected_routes: this.selectedRoutes,
        selected_students: this.selectedStudents,
        selected_drivers: this.selectedDrivers,
        selected_schools: this.selectedSchools,
        include_inactive: this.includeInactive,
        group_by: this.groupBy,
        options: this.currentOptions,
        output_format: this.outputFormat,
        include_charts: this.includeCharts,
        include_raw_data: this.includeRawData,
        email_on_completion: this.emailOnCompletion
      };

      Object.assign(this.originalReport, updateData);
      await this.originalReport.save();

      this.notifications.success('Report updated successfully');
      this.hasUnsavedChanges = false;
      this.router.transitionTo('school-transport.reports.view', this.originalReport.id);
    } catch (error) {
      console.error('Error updating report:', error);
      this.notifications.error('Failed to update report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async regenerateReport() {
    if (!this.canRegenerate) {
      this.notifications.error('Cannot regenerate report in current status');
      return;
    }

    if (!confirm('Are you sure you want to regenerate this report?')) {
      return;
    }

    this.isLoading = true;
    try {
      this.originalReport.status = 'processing';
      await this.originalReport.save();

      // Simulate regeneration completion
      setTimeout(async () => {
        this.originalReport.status = 'completed';
        this.originalReport.completed_at = new Date().toISOString();
        await this.originalReport.save();
        this.notifications.success('Report regenerated successfully');
        this.router.transitionTo('school-transport.reports.view', this.originalReport.id);
      }, 3000);

    } catch (error) {
      console.error('Error regenerating report:', error);
      this.notifications.error('Failed to regenerate report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async saveAsDraft() {
    if (!this.canSaveDraft) {
      this.notifications.error('Cannot save draft for completed reports');
      return;
    }

    this.isLoading = true;
    try {
      this.originalReport.status = 'draft';
      await this.originalReport.save();

      this.notifications.success('Report saved as draft');
      this.hasUnsavedChanges = false;
    } catch (error) {
      console.error('Error saving draft:', error);
      this.notifications.error('Failed to save draft');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  cancelEdit() {
    if (this.hasUnsavedChanges) {
      if (!confirm('You have unsaved changes. Are you sure you want to cancel?')) {
        return;
      }
    }

    this.router.transitionTo('school-transport.reports.view', this.originalReport.id);
  }

  @action
  async validateAndProceed() {
    if (this.validateCurrentStep()) {
      if (this.isLastStep) {
        await this.updateReport();
      } else {
        this.nextStep();
      }
    } else {
      this.notifications.error('Please fix the validation errors');
    }
  }

  @action
  async previewReport() {
    this.notifications.info('Report preview functionality coming soon');
  }

  @action
  async testFilters() {
    this.notifications.info('Filter testing functionality coming soon');
  }

  @action
  async duplicateReport() {
    this.router.transitionTo('school-transport.reports.new', {
      queryParams: {
        duplicate_id: this.originalReport.id
      }
    });
  }

  @action
  async deleteReport() {
    if (!confirm('Are you sure you want to delete this report? This action cannot be undone.')) {
      return;
    }

    this.isLoading = true;
    try {
      await this.originalReport.destroyRecord();

      this.notifications.success('Report deleted successfully');
      this.router.transitionTo('school-transport.reports.index');
    } catch (error) {
      console.error('Error deleting report:', error);
      this.notifications.error('Failed to delete report');
    } finally {
      this.isLoading = false;
    }
  }
}