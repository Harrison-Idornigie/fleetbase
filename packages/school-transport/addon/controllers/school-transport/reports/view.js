import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportReportsViewController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked selectedTab = 'overview';

  // Report data
  @tracked report = null;
  @tracked reportData = null;
  @tracked reportStats = null;
  @tracked generatedCharts = [];
  @tracked rawData = [];

  // Display options
  @tracked showFilters = false;
  @tracked selectedView = 'summary';
  @tracked chartType = 'bar';
  @tracked dateGrouping = 'day';

  // Export options
  @tracked showExportModal = false;
  @tracked exportFormat = 'pdf';
  @tracked includeCharts = true;
  @tracked includeRawData = false;

  // Schedule info
  @tracked scheduleInfo = null;
  @tracked nextRunDate = null;

  get isCompleted() {
    return this.report?.status === 'completed';
  }

  get isProcessing() {
    return this.report?.status === 'processing';
  }

  get isFailed() {
    return this.report?.status === 'failed';
  }

  get isScheduled() {
    return this.report?.status === 'scheduled';
  }

  get canDownload() {
    return this.isCompleted;
  }

  get canRegenerate() {
    return this.isCompleted || this.isFailed;
  }

  get canEdit() {
    return !this.isProcessing;
  }

  get reportTypeLabel() {
    const labels = {
      attendance: 'Attendance Report',
      route_efficiency: 'Route Efficiency Report',
      safety_compliance: 'Safety & Compliance Report',
      financial: 'Financial Report',
      custom: 'Custom Report'
    };
    return labels[this.report?.type] || 'Report';
  }

  get statusColor() {
    const colors = {
      completed: 'green',
      processing: 'blue',
      failed: 'red',
      scheduled: 'orange',
      draft: 'gray'
    };
    return colors[this.report?.status] || 'gray';
  }

  get statusLabel() {
    const labels = {
      completed: 'Completed',
      processing: 'Processing',
      failed: 'Failed',
      scheduled: 'Scheduled',
      draft: 'Draft'
    };
    return labels[this.report?.status] || 'Unknown';
  }

  get fileSize() {
    if (!this.report?.file_size) return null;

    const size = this.report.file_size;
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  }

  get generationTime() {
    if (!this.report?.completed_at || !this.report?.created_at) return null;

    const start = new Date(this.report.created_at);
    const end = new Date(this.report.completed_at);
    const diffMs = end - start;
    const diffMins = Math.floor(diffMs / (1000 * 60));
    const diffSecs = Math.floor((diffMs % (1000 * 60)) / 1000);

    if (diffMins > 0) {
      return `${diffMins}m ${diffSecs}s`;
    }
    return `${diffSecs}s`;
  }

  get dateRangeDisplay() {
    if (this.report?.date_range_type === 'preset') {
      return this.report.preset_range.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    } else if (this.report?.custom_start_date && this.report?.custom_end_date) {
      return `${this.report.custom_start_date} to ${this.report.custom_end_date}`;
    }
    return 'N/A';
  }

  get hasCharts() {
    return this.generatedCharts && this.generatedCharts.length > 0;
  }

  get hasRawData() {
    return this.rawData && this.rawData.length > 0;
  }

  get summaryStats() {
    if (!this.reportStats) return [];

    const stats = [];
    switch (this.report?.type) {
      case 'attendance':
        stats.push(
          { label: 'Total Students', value: this.reportStats.total_students || 0 },
          { label: 'Present Days', value: this.reportStats.present_days || 0 },
          { label: 'Absent Days', value: this.reportStats.absent_days || 0 },
          { label: 'Attendance Rate', value: `${this.reportStats.attendance_rate || 0}%` }
        );
        break;
      case 'route_efficiency':
        stats.push(
          { label: 'Total Routes', value: this.reportStats.total_routes || 0 },
          { label: 'Total Distance', value: `${this.reportStats.total_distance || 0} km` },
          { label: 'Average Duration', value: `${this.reportStats.avg_duration || 0} min` },
          { label: 'Fuel Efficiency', value: `${this.reportStats.fuel_efficiency || 0} L/100km` }
        );
        break;
      case 'safety_compliance':
        stats.push(
          { label: 'Total Incidents', value: this.reportStats.total_incidents || 0 },
          { label: 'Compliance Rate', value: `${this.reportStats.compliance_rate || 0}%` },
          { label: 'Inspections Passed', value: this.reportStats.inspections_passed || 0 },
          { label: 'Training Completed', value: this.reportStats.training_completed || 0 }
        );
        break;
    }
    return stats;
  }

  constructor() {
    super(...arguments);
    this.report = this.model.report;
    this.loadReportData();
  }

  async loadReportData() {
    this.isLoading = true;
    try {
      // Load report data and stats
      this.reportData = await this.store.queryRecord('school-transport/report-data', {
        report_id: this.report.id
      });

      this.reportStats = await this.store.queryRecord('school-transport/report-stat', {
        report_id: this.report.id
      });

      // Load charts if available
      if (this.report.include_charts) {
        this.generatedCharts = await this.store.query('school-transport/report-chart', {
          report_id: this.report.id
        });
      }

      // Load raw data if available
      if (this.report.include_raw_data) {
        this.rawData = await this.store.query('school-transport/report-raw-data', {
          report_id: this.report.id
        });
      }

      // Load schedule info if scheduled
      if (this.isScheduled) {
        this.scheduleInfo = await this.store.queryRecord('school-transport/scheduled-report', {
          report_id: this.report.id
        });
        this.calculateNextRun();
      }

    } catch (error) {
      console.error('Error loading report data:', error);
      this.notifications.error('Failed to load report data');
    } finally {
      this.isLoading = false;
    }
  }

  calculateNextRun() {
    if (!this.scheduleInfo) return;

    // This would calculate the next run date based on schedule
    // For now, just set a placeholder
    this.nextRunDate = 'Next Monday at 9:00 AM';
  }

  @action
  selectTab(tab) {
    this.selectedTab = tab;
  }

  @action
  selectView(view) {
    this.selectedView = view;
  }

  @action
  updateChartType(type) {
    this.chartType = type;
  }

  @action
  updateDateGrouping(grouping) {
    this.dateGrouping = grouping;
  }

  @action
  toggleFilters() {
    this.showFilters = !this.showFilters;
  }

  @action
  async downloadReport() {
    if (!this.canDownload) return;

    try {
      // This would trigger file download
      this.notifications.success('Report download started');
    } catch (error) {
      console.error('Error downloading report:', error);
      this.notifications.error('Failed to download report');
    }
  }

  @action
  async regenerateReport() {
    if (!this.canRegenerate) return;

    if (!confirm('Are you sure you want to regenerate this report?')) {
      return;
    }

    this.isLoading = true;
    try {
      this.report.status = 'processing';
      await this.report.save();

      // In a real implementation, this would trigger report regeneration
      // For now, simulate completion
      setTimeout(async () => {
        this.report.status = 'completed';
        this.report.completed_at = new Date().toISOString();
        await this.report.save();
        this.loadReportData();
        this.notifications.success('Report regenerated successfully');
      }, 3000);

    } catch (error) {
      console.error('Error regenerating report:', error);
      this.notifications.error('Failed to regenerate report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  editReport() {
    this.router.transitionTo('school-transport.reports.edit', this.report.id);
  }

  @action
  duplicateReport() {
    this.router.transitionTo('school-transport.reports.new', {
      queryParams: {
        duplicate_id: this.report.id
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
      await this.report.destroyRecord();

      this.notifications.success('Report deleted successfully');
      this.router.transitionTo('school-transport.reports.index');
    } catch (error) {
      console.error('Error deleting report:', error);
      this.notifications.error('Failed to delete report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async archiveReport() {
    this.isLoading = true;
    try {
      this.report.archived = true;
      await this.report.save();

      this.notifications.success('Report archived successfully');
      this.router.transitionTo('school-transport.reports.index');
    } catch (error) {
      console.error('Error archiving report:', error);
      this.notifications.error('Failed to archive report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async shareReport() {
    // This would open a share modal
    this.notifications.info('Report sharing functionality coming soon');
  }

  @action
  exportData() {
    this.showExportModal = true;
  }

  @action
  async exportDataAction() {
    this.isLoading = true;
    try {
      const exportData = {
        report_id: this.report.id,
        format: this.exportFormat,
        include_charts: this.includeCharts,
        include_raw_data: this.includeRawData
      };

      // This would trigger the export process
      this.notifications.success('Data export started');
      this.showExportModal = false;
    } catch (error) {
      console.error('Error exporting data:', error);
      this.notifications.error('Failed to export data');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async emailReport() {
    // This would open an email modal
    this.notifications.info('Email report functionality coming soon');
  }

  @action
  async printReport() {
    // This would trigger print dialog
    window.print();
  }

  @action
  async refreshReport() {
    await this.loadReportData();
    this.notifications.success('Report data refreshed');
  }

  @action
  async viewChartFullscreen(chartId) {
    // This would open chart in fullscreen modal
    this.notifications.info('Fullscreen chart view coming soon');
  }

  @action
  async exportChart(chartId) {
    try {
      const chart = this.generatedCharts.find(c => c.id === chartId);
      if (chart) {
        // This would export the chart as image
        this.notifications.success('Chart exported successfully');
      }
    } catch (error) {
      console.error('Error exporting chart:', error);
      this.notifications.error('Failed to export chart');
    }
  }

  @action
  async filterData(filters) {
    // This would apply filters to the displayed data
    this.notifications.info('Data filtering functionality coming soon');
  }

  @action
  async sortData(sortBy, sortOrder) {
    // This would sort the displayed data
    this.notifications.info('Data sorting functionality coming soon');
  }

  @action
  async searchData(query) {
    // This would search through the data
    this.notifications.info('Data search functionality coming soon');
  }

  @action
  async viewRawData() {
    // This would show raw data in a modal or new tab
    this.notifications.info('Raw data view functionality coming soon');
  }

  @action
  async compareWithPrevious() {
    // This would show comparison with previous report
    this.notifications.info('Report comparison functionality coming soon');
  }

  @action
  async scheduleReport() {
    // This would open schedule modal for existing report
    this.notifications.info('Report scheduling functionality coming soon');
  }

  @action
  async pauseSchedule() {
    if (!this.scheduleInfo) return;

    this.isLoading = true;
    try {
      this.scheduleInfo.is_active = false;
      await this.scheduleInfo.save();

      this.notifications.success('Report schedule paused');
      this.loadReportData();
    } catch (error) {
      console.error('Error pausing schedule:', error);
      this.notifications.error('Failed to pause schedule');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async resumeSchedule() {
    if (!this.scheduleInfo) return;

    this.isLoading = true;
    try {
      this.scheduleInfo.is_active = true;
      await this.scheduleInfo.save();

      this.notifications.success('Report schedule resumed');
      this.loadReportData();
    } catch (error) {
      console.error('Error resuming schedule:', error);
      this.notifications.error('Failed to resume schedule');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  closeModal() {
    this.showExportModal = false;
  }

  @action
  async bookmarkReport() {
    // This would add report to user's bookmarks
    this.notifications.info('Report bookmarking functionality coming soon');
  }
}