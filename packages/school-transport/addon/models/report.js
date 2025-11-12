import Model, { attr } from '@ember-data/model';
import { computed } from '@ember/object';

export default class ReportModel extends Model {
  /** @ids */
  @attr('string') public_id;
  @attr('string') created_by_uuid;

  /** @attributes */
  @attr('string') name;
  @attr('string') description;
  @attr('string') report_type;
  @attr('string') category;
  @attr('string') format;
  @attr() parameters;
  @attr() filters;
  @attr('date') start_date;
  @attr('date') end_date;
  @attr('string') status;
  @attr() data;
  @attr() summary;
  @attr('string') file_url;
  @attr('number') file_size;
  @attr('date') generated_at;
  @attr('boolean') is_scheduled;
  @attr() schedule;
  @attr() recipients;
  @attr('date') last_sent_at;
  @attr('string') visibility;
  @attr() meta;

  /** @computed */
  @computed('status')
  get is_generating() {
    return this.status === 'generating';
  }

  @computed('status')
  get is_completed() {
    return this.status === 'completed';
  }

  @computed('status')
  get is_failed() {
    return this.status === 'failed';
  }

  @computed('report_type')
  get type_display() {
    const types = {
      'attendance': 'Attendance Report',
      'route_efficiency': 'Route Efficiency Report',
      'student_transportation': 'Student Transportation Report',
      'driver_performance': 'Driver Performance Report',
      'vehicle_maintenance': 'Vehicle Maintenance Report',
      'safety_compliance': 'Safety & Compliance Report',
      'incident_summary': 'Incident Summary Report',
      'parent_communication': 'Parent Communication Report',
      'utilization': 'Route Utilization Report',
      'cost_analysis': 'Cost Analysis Report',
      'custom': 'Custom Report'
    };
    return types[this.report_type] || this.report_type;
  }

  @computed('category')
  get category_display() {
    const categories = {
      'operational': 'Operational',
      'financial': 'Financial',
      'safety': 'Safety',
      'compliance': 'Compliance',
      'performance': 'Performance',
      'analytics': 'Analytics'
    };
    return categories[this.category] || this.category;
  }

  @computed('format')
  get format_display() {
    const formats = {
      'pdf': 'PDF',
      'csv': 'CSV',
      'excel': 'Excel',
      'json': 'JSON',
      'html': 'HTML'
    };
    return formats[this.format] || this.format?.toUpperCase();
  }

  @computed('status')
  get status_display() {
    const statuses = {
      'pending': 'Pending',
      'generating': 'Generating',
      'completed': 'Completed',
      'failed': 'Failed',
      'scheduled': 'Scheduled'
    };
    return statuses[this.status] || this.status;
  }

  @computed('status')
  get status_color() {
    const colors = {
      'pending': 'text-yellow-600',
      'generating': 'text-blue-600',
      'completed': 'text-green-600',
      'failed': 'text-red-600',
      'scheduled': 'text-indigo-600'
    };
    return colors[this.status] || 'text-gray-600';
  }

  @computed('file_size')
  get formatted_file_size() {
    if (!this.file_size) return 'Unknown';
    
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    let size = this.file_size;
    let i = 0;
    
    while (size >= 1024 && i < sizes.length - 1) {
      size /= 1024;
      i++;
    }
    
    return `${size.toFixed(2)} ${sizes[i]}`;
  }

  @computed('start_date', 'end_date')
  get date_range_display() {
    if (!this.start_date || !this.end_date) return 'Not specified';
    
    const start = new Date(this.start_date).toLocaleDateString();
    const end = new Date(this.end_date).toLocaleDateString();
    
    return `${start} - ${end}`;
  }

  @computed('start_date', 'end_date')
  get days_in_range() {
    if (!this.start_date || !this.end_date) return 0;
    
    const start = new Date(this.start_date);
    const end = new Date(this.end_date);
    const diffTime = end - start;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    
    return diffDays;
  }

  @computed('is_scheduled', 'schedule')
  get schedule_display() {
    if (!this.is_scheduled || !this.schedule) return 'Not Scheduled';
    
    const { frequency, time } = this.schedule;
    const frequencies = {
      'daily': 'Daily',
      'weekly': 'Weekly',
      'biweekly': 'Bi-weekly',
      'monthly': 'Monthly',
      'quarterly': 'Quarterly'
    };
    
    return `${frequencies[frequency] || frequency}${time ? ` at ${time}` : ''}`;
  }

  @computed('recipients')
  get recipients_count() {
    if (!this.recipients || !Array.isArray(this.recipients)) return 0;
    return this.recipients.length;
  }

  @computed('summary')
  get has_summary() {
    return this.summary && Object.keys(this.summary).length > 0;
  }

  @computed('generated_at')
  get time_since_generation() {
    if (!this.generated_at) return null;
    
    const now = new Date();
    const generated = new Date(this.generated_at);
    const diffMinutes = Math.floor((now - generated) / (1000 * 60));
    
    if (diffMinutes < 60) return `${diffMinutes} minute${diffMinutes !== 1 ? 's' : ''} ago`;
    
    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
    
    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
  }

  @computed('last_sent_at')
  get was_sent() {
    return this.last_sent_at !== null && this.last_sent_at !== undefined;
  }

  /** @relationships */
  // Created by relationship would be defined through created_by_uuid

  /** @methods */
  async generate() {
    this.status = 'generating';
    await this.save();
    
    // This would typically trigger a background job
    // For now, we'll just update the status
    return this;
  }

  async regenerate() {
    this.status = 'pending';
    this.generated_at = null;
    this.file_url = null;
    this.file_size = null;
    await this.save();
    return this.generate();
  }

  async download() {
    if (!this.file_url) {
      throw new Error('Report file not available');
    }
    
    // This would typically trigger a download
    window.open(this.file_url, '_blank');
    return true;
  }

  async send(recipients = null) {
    if (!this.is_completed) {
      throw new Error('Report must be completed before sending');
    }
    
    const recipientList = recipients || this.recipients;
    if (!recipientList || recipientList.length === 0) {
      throw new Error('No recipients specified');
    }
    
    this.last_sent_at = new Date();
    this.recipients = recipientList;
    return this.save();
  }

  async schedule(scheduleConfig) {
    this.is_scheduled = true;
    this.schedule = scheduleConfig;
    this.status = 'scheduled';
    return this.save();
  }

  async unschedule() {
    this.is_scheduled = false;
    this.schedule = null;
    return this.save();
  }

  async updateFilters(filters) {
    this.filters = { ...this.filters, ...filters };
    return this.save();
  }

  async updateDateRange(startDate, endDate) {
    this.start_date = startDate;
    this.end_date = endDate;
    return this.save();
  }

  getFormattedGeneratedDate() {
    if (!this.generated_at) return 'Not Generated';
    return new Date(this.generated_at).toLocaleString();
  }

  getFormattedLastSentDate() {
    if (!this.last_sent_at) return 'Never Sent';
    return new Date(this.last_sent_at).toLocaleString();
  }

  canBeGenerated() {
    return ['pending', 'failed'].includes(this.status);
  }

  canBeDownloaded() {
    return this.is_completed && this.file_url;
  }

  canBeSent() {
    return this.is_completed && this.file_url;
  }

  canBeScheduled() {
    return !this.is_generating;
  }

  getSummaryValue(key) {
    return this.summary ? this.summary[key] : null;
  }

  getFilterValue(key) {
    return this.filters ? this.filters[key] : null;
  }

  getParameterValue(key) {
    return this.parameters ? this.parameters[key] : null;
  }
}
