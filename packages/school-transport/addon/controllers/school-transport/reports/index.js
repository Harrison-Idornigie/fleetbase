import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportReportsIndexController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked selectedTab = 'overview';

  // Filters and search
  @tracked searchQuery = '';
  @tracked selectedReportType = 'all';
  @tracked selectedDateRange = 'last_30_days';
  @tracked selectedStatus = 'all';
  @tracked selectedCategory = 'all';

  // Reports data
  @tracked reports = [];
  @tracked reportStats = null;
  @tracked recentReports = [];
  @tracked scheduledReports = [];

  // Pagination
  @tracked currentPage = 1;
  @tracked pageSize = 20;
  @tracked totalReports = 0;

  // Bulk actions
  @tracked selectedReports = [];
  @tracked showBulkActions = false;

  // Modal states
  @tracked showCreateReportModal = false;
  @tracked showScheduleReportModal = false;
  @tracked showExportModal = false;

  get reportTypeOptions() {
    return [
      { value: 'all', label: 'All Reports' },
      { value: 'attendance', label: 'Attendance Reports' },
      { value: 'route_efficiency', label: 'Route Efficiency' },
      { value: 'safety_compliance', label: 'Safety & Compliance' },
      { value: 'custom', label: 'Custom Reports' },
      { value: 'financial', label: 'Financial Reports' }
    ];
  }

  get dateRangeOptions() {
    return [
      { value: 'today', label: 'Today' },
      { value: 'yesterday', label: 'Yesterday' },
      { value: 'last_7_days', label: 'Last 7 Days' },
      { value: 'last_30_days', label: 'Last 30 Days' },
      { value: 'last_90_days', label: 'Last 90 Days' },
      { value: 'this_month', label: 'This Month' },
      { value: 'last_month', label: 'Last Month' },
      { value: 'this_year', label: 'This Year' },
      { value: 'custom', label: 'Custom Range' }
    ];
  }

  get statusOptions() {
    return [
      { value: 'all', label: 'All Status' },
      { value: 'completed', label: 'Completed' },
      { value: 'processing', label: 'Processing' },
      { value: 'scheduled', label: 'Scheduled' },
      { value: 'failed', label: 'Failed' }
    ];
  }

  get categoryOptions() {
    return [
      { value: 'all', label: 'All Categories' },
      { value: 'daily', label: 'Daily' },
      { value: 'weekly', label: 'Weekly' },
      { value: 'monthly', label: 'Monthly' },
      { value: 'quarterly', label: 'Quarterly' },
      { value: 'annual', label: 'Annual' }
    ];
  }

  get filteredReports() {
    let filtered = this.reports;

    // Filter by search query
    if (this.searchQuery) {
      const query = this.searchQuery.toLowerCase();
      filtered = filtered.filter(report =>
        report.name.toLowerCase().includes(query) ||
        report.description.toLowerCase().includes(query) ||
        report.type.toLowerCase().includes(query)
      );
    }

    // Filter by report type
    if (this.selectedReportType !== 'all') {
      filtered = filtered.filter(report => report.type === this.selectedReportType);
    }

    // Filter by status
    if (this.selectedStatus !== 'all') {
      filtered = filtered.filter(report => report.status === this.selectedStatus);
    }

    // Filter by category
    if (this.selectedCategory !== 'all') {
      filtered = filtered.filter(report => report.category === this.selectedCategory);
    }

    return filtered;
  }

  get paginatedReports() {
    const start = (this.currentPage - 1) * this.pageSize;
    const end = start + this.pageSize;
    return this.filteredReports.slice(start, end);
  }

  get totalPages() {
    return Math.ceil(this.filteredReports.length / this.pageSize);
  }

  get hasSelectedReports() {
    return this.selectedReports.length > 0;
  }

  get selectedReportsCount() {
    return this.selectedReports.length;
  }

  get allReportsSelected() {
    return this.selectedReports.length === this.filteredReports.length && this.filteredReports.length > 0;
  }

  get someReportsSelected() {
    return this.selectedReports.length > 0 && this.selectedReports.length < this.filteredReports.length;
  }

  constructor() {
    super(...arguments);
    this.loadReports();
    this.loadReportStats();
  }

  async loadReports() {
    this.isLoading = true;
    try {
      const reports = await this.store.query('school-transport/report', {
        include: 'creator,template',
        sort: '-created_at'
      });

      this.reports = reports.toArray();
      this.totalReports = this.reports.length;

      // Separate recent and scheduled reports
      this.recentReports = this.reports.filter(report =>
        report.status === 'completed' && this.isRecent(report.created_at)
      ).slice(0, 5);

      this.scheduledReports = this.reports.filter(report =>
        report.status === 'scheduled'
      ).slice(0, 5);

    } catch (error) {
      console.error('Error loading reports:', error);
      this.notifications.error('Failed to load reports');
    } finally {
      this.isLoading = false;
    }
  }

  async loadReportStats() {
    try {
      this.reportStats = await this.store.queryRecord('school-transport/report-stat', {});
    } catch (error) {
      console.error('Error loading report stats:', error);
    }
  }

  isRecent(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays <= 7;
  }

  @action
  selectTab(tab) {
    this.selectedTab = tab;
  }

  @action
  updateSearchQuery(query) {
    this.searchQuery = query;
    this.currentPage = 1; // Reset to first page
  }

  @action
  updateReportType(type) {
    this.selectedReportType = type;
    this.currentPage = 1;
  }

  @action
  updateDateRange(range) {
    this.selectedDateRange = range;
    this.currentPage = 1;
  }

  @action
  updateStatus(status) {
    this.selectedStatus = status;
    this.currentPage = 1;
  }

  @action
  updateCategory(category) {
    this.selectedCategory = category;
    this.currentPage = 1;
  }

  @action
  goToPage(page) {
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page;
    }
  }

  @action
  nextPage() {
    if (this.currentPage < this.totalPages) {
      this.currentPage++;
    }
  }

  @action
  previousPage() {
    if (this.currentPage > 1) {
      this.currentPage--;
    }
  }

  @action
  toggleReportSelection(reportId, isSelected) {
    if (isSelected) {
      this.selectedReports = [...this.selectedReports, reportId];
    } else {
      this.selectedReports = this.selectedReports.filter(id => id !== reportId);
    }
  }

  @action
  toggleAllReports() {
    if (this.allReportsSelected) {
      this.selectedReports = [];
    } else {
      this.selectedReports = this.filteredReports.map(report => report.id);
    }
  }

  @action
  clearSelection() {
    this.selectedReports = [];
  }

  @action
  createReport() {
    this.showCreateReportModal = true;
  }

  @action
  async generateReport(reportType, config = {}) {
    this.isLoading = true;
    try {
      const reportData = {
        type: reportType,
        name: config.name || `${reportType.replace('_', ' ')} Report`,
        description: config.description || '',
        config: config,
        status: 'processing'
      };

      const newReport = this.store.createRecord('school-transport/report', reportData);
      await newReport.save();

      this.notifications.success('Report generation started');
      this.showCreateReportModal = false;
      this.loadReports(); // Refresh the list
    } catch (error) {
      console.error('Error generating report:', error);
      this.notifications.error('Failed to generate report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  scheduleReport() {
    this.showScheduleReportModal = true;
  }

  @action
  async scheduleReportAction(scheduleData) {
    this.isLoading = true;
    try {
      const scheduledReport = this.store.createRecord('school-transport/scheduled-report', scheduleData);
      await scheduledReport.save();

      this.notifications.success('Report scheduled successfully');
      this.showScheduleReportModal = false;
      this.loadReports();
    } catch (error) {
      console.error('Error scheduling report:', error);
      this.notifications.error('Failed to schedule report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async runReport(reportId) {
    const report = this.reports.find(r => r.id === reportId);
    if (!report) return;

    this.isLoading = true;
    try {
      report.status = 'processing';
      await report.save();

      // In a real implementation, this would trigger the report generation
      // For now, we'll simulate completion
      setTimeout(async () => {
        report.status = 'completed';
        report.completed_at = new Date().toISOString();
        await report.save();
        this.loadReports();
        this.notifications.success('Report completed successfully');
      }, 2000);

    } catch (error) {
      console.error('Error running report:', error);
      this.notifications.error('Failed to run report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async downloadReport(reportId) {
    const report = this.reports.find(r => r.id === reportId);
    if (!report) return;

    try {
      // This would trigger file download
      this.notifications.success('Report download started');
    } catch (error) {
      console.error('Error downloading report:', error);
      this.notifications.error('Failed to download report');
    }
  }

  @action
  async deleteReport(reportId) {
    const report = this.reports.find(r => r.id === reportId);
    if (!report) return;

    if (!confirm('Are you sure you want to delete this report?')) {
      return;
    }

    this.isLoading = true;
    try {
      await report.destroyRecord();

      this.notifications.success('Report deleted successfully');
      this.selectedReports = this.selectedReports.filter(id => id !== reportId);
      this.loadReports();
    } catch (error) {
      console.error('Error deleting report:', error);
      this.notifications.error('Failed to delete report');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async bulkDeleteReports() {
    if (!confirm(`Are you sure you want to delete ${this.selectedReportsCount} reports?`)) {
      return;
    }

    this.isLoading = true;
    try {
      const deletePromises = this.selectedReports.map(reportId => {
        const report = this.reports.find(r => r.id === reportId);
        return report ? report.destroyRecord() : Promise.resolve();
      });

      await Promise.all(deletePromises);

      this.notifications.success(`${this.selectedReportsCount} reports deleted successfully`);
      this.selectedReports = [];
      this.loadReports();
    } catch (error) {
      console.error('Error deleting reports:', error);
      this.notifications.error('Failed to delete some reports');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async bulkRunReports() {
    this.isLoading = true;
    try {
      const runPromises = this.selectedReports.map(reportId => {
        return this.runReport(reportId);
      });

      await Promise.all(runPromises);

      this.notifications.success(`${this.selectedReportsCount} reports queued for processing`);
      this.selectedReports = [];
    } catch (error) {
      console.error('Error running reports:', error);
      this.notifications.error('Failed to run some reports');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async exportReports() {
    this.showExportModal = true;
  }

  @action
  async exportReportsAction(exportConfig) {
    this.isLoading = true;
    try {
      const exportData = {
        report_ids: this.selectedReports.length > 0 ? this.selectedReports : this.filteredReports.map(r => r.id),
        format: exportConfig.format,
        include_data: exportConfig.includeData
      };

      // This would trigger the export process
      this.notifications.success('Reports export started');
      this.showExportModal = false;
      this.selectedReports = [];
    } catch (error) {
      console.error('Error exporting reports:', error);
      this.notifications.error('Failed to export reports');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  viewReport(reportId) {
    this.router.transitionTo('school-transport.reports.view', reportId);
  }

  @action
  editReport(reportId) {
    this.router.transitionTo('school-transport.reports.edit', reportId);
  }

  @action
  duplicateReport(reportId) {
    const report = this.reports.find(r => r.id === reportId);
    if (report) {
      this.router.transitionTo('school-transport.reports.new', {
        queryParams: {
          duplicate_id: reportId
        }
      });
    }
  }

  @action
  refreshReports() {
    this.loadReports();
    this.loadReportStats();
    this.notifications.success('Reports refreshed');
  }

  @action
  closeModal() {
    this.showCreateReportModal = false;
    this.showScheduleReportModal = false;
    this.showExportModal = false;
  }

  @action
  async generateQuickReport(reportType) {
    const config = {
      name: `${reportType.replace('_', ' ')} Report - ${new Date().toLocaleDateString()}`,
      date_range: this.selectedDateRange
    };

    await this.generateReport(reportType, config);
  }
}