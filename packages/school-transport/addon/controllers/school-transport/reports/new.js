import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportReportsNewController extends Controller {
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
    return titles[this.currentStep] || 'Create Report';
  }

  get stepDescription() {
    const descriptions = {
      1: 'Enter basic information about your report',
      2: 'Configure date range and filters',
      3: 'Set report-specific options and parameters',
      4: 'Configure scheduling and output settings'
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
    // Rough estimation based on selections
    let size = 1; // Base size

    if (this.selectedRoutes.length > 0) size *= this.selectedRoutes.length;
    if (this.selectedStudents.length > 0) size *= this.selectedStudents.length;
    if (this.selectedDrivers.length > 0) size *= this.selectedDrivers.length;

    if (size > 1000) return 'Large (>1000 records)';
    if (size > 100) return 'Medium (100-1000 records)';
    return 'Small (<100 records)';
  }

  constructor() {
    super(...arguments);
    this.initializeFromQueryParams();
    this.loadAvailableData();
  }

  initializeFromQueryParams() {
    const queryParams = this.router.currentRoute.queryParams;

    if (queryParams.type) {
      this.reportType = queryParams.type;
    }

    if (queryParams.duplicate_id) {
      this.loadDuplicateReport(queryParams.duplicate_id);
    }
  }

  async loadAvailableData() {
    try {
      // Load available routes, students, drivers, schools
      this.availableRoutes = await this.store.query('school-transport/route', {});
      this.availableStudents = await this.store.query('school-transport/student', {});
      this.availableDrivers = await this.store.query('school-transport/driver', {});
      this.availableSchools = await this.store.query('school-transport/school', {});
    } catch (error) {
      console.error('Error loading available data:', error);
    }
  }

  async loadDuplicateReport(reportId) {
    try {
      const originalReport = await this.store.findRecord('school-transport/report', reportId);

      this.reportName = `Copy of ${originalReport.name}`;
      this.reportDescription = originalReport.description;
      this.reportType = originalReport.type;
      this.reportCategory = originalReport.category;
      this.dateRangeType = originalReport.date_range_type || 'preset';
      this.presetRange = originalReport.preset_range || 'last_30_days';
      this.customStartDate = originalReport.custom_start_date || '';
      this.customEndDate = originalReport.custom_end_date || '';
      this.selectedRoutes = originalReport.selected_routes || [];
      this.selectedStudents = originalReport.selected_students || [];
      this.selectedDrivers = originalReport.selected_drivers || [];
      this.selectedSchools = originalReport.selected_schools || [];
      this.includeInactive = originalReport.include_inactive || false;
      this.groupBy = originalReport.group_by || 'none';

      // Load type-specific options
      if (originalReport.options) {
        switch (this.reportType) {
          case 'attendance':
            this.attendanceOptions = { ...this.attendanceOptions, ...originalReport.options };
            break;
          case 'route_efficiency':
            this.routeEfficiencyOptions = { ...this.routeEfficiencyOptions, ...originalReport.options };
            break;
          case 'safety_compliance':
            this.safetyOptions = { ...this.safetyOptions, ...originalReport.options };
            break;
        }
      }

      // Scheduling options
      if (originalReport.schedule) {
        this.isScheduled = true;
        this.scheduleFrequency = originalReport.schedule.frequency || 'weekly';
        this.scheduleTime = originalReport.schedule.time || '09:00';
        this.scheduleDays = originalReport.schedule.days || ['monday'];
        this.scheduleRecipients = originalReport.schedule.recipients || [];
      }

      // Output options
      this.outputFormat = originalReport.output_format || 'pdf';
      this.includeCharts = originalReport.include_charts !== false;
      this.includeRawData = originalReport.include_raw_data || false;
      this.emailOnCompletion = originalReport.email_on_completion !== false;

    } catch (error) {
      console.error('Error loading duplicate report:', error);
      this.notifications.error('Failed to load report for duplication');
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
        // Report options validation - depends on report type
        break;

      case 4:
        if (this.isScheduled && this.scheduleRecipients.length === 0) {
          this.errors.scheduleRecipients = 'At least one recipient is required for scheduled reports';
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
  }

  @action
  updateReportType(type) {
    this.reportType = type;
    // Reset type-specific options when changing type
    this.resetTypeSpecificOptions();
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
  }

  @action
  toggleRoute(routeId, isSelected) {
    if (isSelected) {
      this.selectedRoutes = [...this.selectedRoutes, routeId];
    } else {
      this.selectedRoutes = this.selectedRoutes.filter(id => id !== routeId);
    }
  }

  @action
  toggleStudent(studentId, isSelected) {
    if (isSelected) {
      this.selectedStudents = [...this.selectedStudents, studentId];
    } else {
      this.selectedStudents = this.selectedStudents.filter(id => id !== studentId);
    }
  }

  @action
  toggleDriver(driverId, isSelected) {
    if (isSelected) {
      this.selectedDrivers = [...this.selectedDrivers, driverId];
    } else {
      this.selectedDrivers = this.selectedDrivers.filter(id => id !== driverId);
    }
  }

  @action
  toggleSchool(schoolId, isSelected) {
    if (isSelected) {
      this.selectedSchools = [...this.selectedSchools, schoolId];
    } else {
      this.selectedSchools = this.selectedSchools.filter(id => id !== schoolId);
    }
  }

  @action
  selectAllRoutes() {
    if (this.selectedRoutes.length === this.availableRoutes.length) {
      this.selectedRoutes = [];
    } else {
      this.selectedRoutes = this.availableRoutes.map(r => r.id);
    }
  }

  @action
  selectAllStudents() {
    if (this.selectedStudents.length === this.availableStudents.length) {
      this.selectedStudents = [];
    } else {
      this.selectedStudents = this.availableStudents.map(s => s.id);
    }
  }

  @action
  clearAllSelections() {
    this.selectedRoutes = [];
    this.selectedStudents = [];
    this.selectedDrivers = [];
    this.selectedSchools = [];
  }

  @action
  updateOption(category, option, value) {
    this[category][option] = value;
  }

  @action
  toggleScheduleDay(day, isSelected) {
    if (isSelected) {
      this.scheduleDays = [...this.scheduleDays, day];
    } else {
      this.scheduleDays = this.scheduleDays.filter(d => d !== day);
    }
  }

  @action
  addScheduleRecipient(recipient) {
    this.scheduleRecipients = [...this.scheduleRecipients, recipient];
  }

  @action
  removeScheduleRecipient(index) {
    this.scheduleRecipients = this.scheduleRecipients.filter((_, i) => i !== index);
  }

  @action
  async createReport() {
    if (!this.validateCurrentStep()) {
      this.notifications.error('Please fix the validation errors before creating the report');
      return;
    }

    this.isLoading = true;
    try {
      const reportData = {
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
        email_on_completion: this.emailOnCompletion,
        status: 'processing'
      };

      const newReport = this.store.createRecord('school-transport/report', reportData);
      await newReport.save();

      // Create schedule if requested
      if (this.isScheduled) {
        await this.createSchedule(newReport.id);
      }

      this.notifications.success('Report created successfully');
      this.router.transitionTo('school-transport.reports.view', newReport.id);
    } catch (error) {
      console.error('Error creating report:', error);
      this.notifications.error('Failed to create report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async createSchedule(reportId) {
    try {
      const scheduleData = {
        report_id: reportId,
        frequency: this.scheduleFrequency,
        time: this.scheduleTime,
        days: this.scheduleDays,
        recipients: this.scheduleRecipients,
        is_active: true
      };

      await this.store.createRecord('school-transport/scheduled-report', scheduleData).save();
    } catch (error) {
      console.error('Error creating schedule:', error);
      // Don't fail the whole operation for schedule creation error
    }
  }

  @action
  async saveAsDraft() {
    this.isLoading = true;
    try {
      const reportData = {
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
        email_on_completion: this.emailOnCompletion,
        status: 'draft'
      };

      const draftReport = this.store.createRecord('school-transport/report', reportData);
      await draftReport.save();

      this.notifications.success('Report saved as draft');
      this.router.transitionTo('school-transport.reports.index');
    } catch (error) {
      console.error('Error saving draft:', error);
      this.notifications.error('Failed to save draft');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  cancelCreation() {
    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
      this.router.transitionTo('school-transport.reports.index');
    }
  }

  @action
  async validateAndProceed() {
    if (this.validateCurrentStep()) {
      if (this.isLastStep) {
        await this.createReport();
      } else {
        this.nextStep();
      }
    } else {
      this.notifications.error('Please fix the validation errors');
    }
  }

  @action
  async previewReport() {
    // This would generate a preview of the report
    this.notifications.info('Report preview functionality coming soon');
  }

  @action
  async testFilters() {
    // This would test the current filters and show estimated results
    this.notifications.info('Filter testing functionality coming soon');
  }
}