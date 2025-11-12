import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class SchoolTransportSettingsReportingPreferencesController extends Controller {
    @service fetch;
    @service notifications;
    
    @tracked dailyAttendanceReports = true;
    @tracked weeklyRouteSummaries = true;
    @tracked monthlySafetyReports = true;
    @tracked incidentAlerts = 'immediate';
    @tracked parentWeeklySummaries = true;
    @tracked performanceMetrics = true;
    @tracked complianceReporting = true;
    @tracked automatedReportDistribution = true;
    @tracked customReportTemplates = true;
    @tracked dataExportFormats = ['pdf', 'excel', 'csv'];
    
    @tracked alertFrequencyOptions = [
        { label: 'Immediate', value: 'immediate' },
        { label: 'Hourly', value: 'hourly' },
        { label: 'Daily', value: 'daily' },
        { label: 'Weekly', value: 'weekly' },
    ];
    
    @tracked exportFormatOptions = [
        { label: 'PDF', value: 'pdf', selected: true },
        { label: 'Excel', value: 'excel', selected: true },
        { label: 'CSV', value: 'csv', selected: true },
        { label: 'JSON', value: 'json', selected: false },
        { label: 'XML', value: 'xml', selected: false },
    ];

    constructor() {
        super(...arguments);
        this.getSettings.perform();
    }

    @task *saveSettings() {
        try {
            const selectedFormats = this.exportFormatOptions.filter(opt => opt.selected).map(opt => opt.value);
            
            yield this.fetch.post('school-transport/settings/reporting-preferences-settings', {
                reportingSettings: {
                    daily_attendance_reports: this.dailyAttendanceReports,
                    weekly_route_summaries: this.weeklyRouteSummaries,
                    monthly_safety_reports: this.monthlySafetyReports,
                    incident_alerts: this.incidentAlerts,
                    parent_weekly_summaries: this.parentWeeklySummaries,
                    performance_metrics: this.performanceMetrics,
                    compliance_reporting: this.complianceReporting,
                    automated_report_distribution: this.automatedReportDistribution,
                    custom_report_templates: this.customReportTemplates,
                    data_export_formats: selectedFormats,
                }
            });

            this.notifications.success('Reporting preferences settings saved.');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *getSettings() {
        try {
            const settings = yield this.fetch.get('school-transport/settings/reporting-preferences-settings');
            
            this.dailyAttendanceReports = settings.daily_attendance_reports !== false;
            this.weeklyRouteSummaries = settings.weekly_route_summaries !== false;
            this.monthlySafetyReports = settings.monthly_safety_reports !== false;
            this.incidentAlerts = settings.incident_alerts || 'immediate';
            this.parentWeeklySummaries = settings.parent_weekly_summaries !== false;
            this.performanceMetrics = settings.performance_metrics !== false;
            this.complianceReporting = settings.compliance_reporting !== false;
            this.automatedReportDistribution = settings.automated_report_distribution !== false;
            this.customReportTemplates = settings.custom_report_templates !== false;
            this.dataExportFormats = settings.data_export_formats || ['pdf', 'excel', 'csv'];
            
            // Update export format options based on saved settings
            this.exportFormatOptions.forEach(option => {
                option.selected = this.dataExportFormats.includes(option.value);
            });
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}