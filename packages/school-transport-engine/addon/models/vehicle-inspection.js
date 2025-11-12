import Model, { attr, belongsTo } from '@ember-data/model';
import { computed } from '@ember/object';

export default class VehicleInspectionModel extends Model {
  /** @ids */
  @attr('string') public_id;
  @attr('string') vehicle_uuid;
  @attr('string') inspector_uuid;

  /** @attributes */
  @attr('string') inspection_type;
  @attr('date') inspection_date;
  @attr('date') next_inspection_date;
  @attr('string') status;
  @attr('string') result;
  @attr('number') odometer_reading;
  @attr() checklist_items;
  @attr() issues_found;
  @attr() recommendations;
  @attr('string') notes;
  @attr('string') document_url;
  @attr('boolean') requires_followup;
  @attr('date') followup_date;
  @attr('string') followup_status;
  @attr('string') signature_url;
  @attr() meta;

  /** @computed */
  @computed('result')
  get passed() {
    return ['passed', 'pass', 'approved'].includes(this.result?.toLowerCase());
  }

  @computed('result')
  get failed() {
    return ['failed', 'fail', 'rejected'].includes(this.result?.toLowerCase());
  }

  @computed('result')
  get needs_repairs() {
    return ['needs_repairs', 'conditional', 'minor_issues'].includes(this.result?.toLowerCase());
  }

  @computed('issues_found')
  get has_critical_issues() {
    if (!this.issues_found || !Array.isArray(this.issues_found)) return false;
    return this.issues_found.some(issue => issue.severity === 'critical' || issue.severity === 'high');
  }

  @computed('issues_found')
  get critical_issues_count() {
    if (!this.issues_found || !Array.isArray(this.issues_found)) return 0;
    return this.issues_found.filter(issue => issue.severity === 'critical' || issue.severity === 'high').length;
  }

  @computed('checklist_items')
  get completion_rate() {
    if (!this.checklist_items || !Array.isArray(this.checklist_items)) return 0;
    
    const totalItems = this.checklist_items.length;
    const completedItems = this.checklist_items.filter(item => item.checked || item.completed).length;
    
    return totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;
  }

  @computed('next_inspection_date')
  get days_until_next_inspection() {
    if (!this.next_inspection_date) return null;
    
    const today = new Date();
    const nextDate = new Date(this.next_inspection_date);
    const diffTime = nextDate - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return diffDays;
  }

  @computed('days_until_next_inspection')
  get is_overdue() {
    return this.days_until_next_inspection !== null && this.days_until_next_inspection < 0;
  }

  @computed('days_until_next_inspection')
  get is_due_soon() {
    return this.days_until_next_inspection !== null && 
           this.days_until_next_inspection >= 0 && 
           this.days_until_next_inspection <= 7;
  }

  @computed('inspection_type')
  get type_display() {
    const types = {
      'pre_trip': 'Pre-Trip Inspection',
      'post_trip': 'Post-Trip Inspection',
      'annual': 'Annual Safety Inspection',
      'monthly': 'Monthly Inspection',
      'preventive_maintenance': 'Preventive Maintenance',
      'dot': 'DOT Inspection',
      'brake': 'Brake Inspection',
      'tire': 'Tire Inspection',
      'emission': 'Emission Test',
      'comprehensive': 'Comprehensive Inspection'
    };
    return types[this.inspection_type] || this.inspection_type;
  }

  @computed('result')
  get result_display() {
    const results = {
      'passed': 'Passed',
      'pass': 'Passed',
      'approved': 'Approved',
      'failed': 'Failed',
      'fail': 'Failed',
      'rejected': 'Rejected',
      'needs_repairs': 'Needs Repairs',
      'conditional': 'Conditional Pass',
      'minor_issues': 'Minor Issues Found'
    };
    return results[this.result?.toLowerCase()] || this.result;
  }

  @computed('result')
  get result_color() {
    if (this.passed) return 'text-green-600';
    if (this.failed) return 'text-red-600';
    if (this.needs_repairs) return 'text-yellow-600';
    return 'text-gray-600';
  }

  @computed('status')
  get status_display() {
    const statuses = {
      'completed': 'Completed',
      'pending': 'Pending',
      'in_progress': 'In Progress',
      'scheduled': 'Scheduled',
      'cancelled': 'Cancelled'
    };
    return statuses[this.status] || this.status;
  }

  @computed('status')
  get status_color() {
    const colors = {
      'completed': 'text-green-600',
      'pending': 'text-yellow-600',
      'in_progress': 'text-blue-600',
      'scheduled': 'text-indigo-600',
      'cancelled': 'text-gray-600'
    };
    return colors[this.status] || 'text-gray-600';
  }

  @computed('requires_followup', 'followup_status')
  get followup_display() {
    if (!this.requires_followup) return 'No Follow-up Required';
    
    const statuses = {
      'scheduled': 'Follow-up Scheduled',
      'completed': 'Follow-up Completed',
      'pending': 'Follow-up Pending',
      'overdue': 'Follow-up Overdue'
    };
    
    return statuses[this.followup_status] || 'Follow-up Required';
  }

  /** @relationships */
  // Vehicle relationship would be defined through vehicle_uuid
  // Inspector relationship would be defined through inspector_uuid

  /** @methods */
  async complete(result, issues = [], recommendations = []) {
    this.status = 'completed';
    this.result = result;
    this.issues_found = issues;
    this.recommendations = recommendations;
    
    if (issues.length > 0) {
      this.requires_followup = true;
      this.followup_status = 'pending';
    }
    
    return this.save();
  }

  async scheduleFollowup(followupDate) {
    this.requires_followup = true;
    this.followup_date = followupDate;
    this.followup_status = 'scheduled';
    return this.save();
  }

  async completeFollowup(notes = null) {
    this.followup_status = 'completed';
    if (notes) {
      this.notes = this.notes ? `${this.notes}; Follow-up: ${notes}` : `Follow-up: ${notes}`;
    }
    return this.save();
  }

  async addIssue(issue) {
    const issues = this.issues_found || [];
    issues.push({
      ...issue,
      reported_at: new Date().toISOString(),
      id: Date.now()
    });
    this.issues_found = issues;
    
    if (issue.severity === 'critical' || issue.severity === 'high') {
      this.requires_followup = true;
      this.followup_status = 'pending';
    }
    
    return this.save();
  }

  async addRecommendation(recommendation) {
    const recommendations = this.recommendations || [];
    recommendations.push({
      ...recommendation,
      recommended_at: new Date().toISOString(),
      id: Date.now()
    });
    this.recommendations = recommendations;
    return this.save();
  }

  getFormattedInspectionDate() {
    if (!this.inspection_date) return 'N/A';
    return new Date(this.inspection_date).toLocaleDateString();
  }

  getFormattedNextInspectionDate() {
    if (!this.next_inspection_date) return 'N/A';
    return new Date(this.next_inspection_date).toLocaleDateString();
  }

  getFormattedFollowupDate() {
    if (!this.followup_date) return 'N/A';
    return new Date(this.followup_date).toLocaleDateString();
  }

  getCriticalIssues() {
    if (!this.issues_found || !Array.isArray(this.issues_found)) return [];
    return this.issues_found.filter(issue => issue.severity === 'critical' || issue.severity === 'high');
  }

  getMinorIssues() {
    if (!this.issues_found || !Array.isArray(this.issues_found)) return [];
    return this.issues_found.filter(issue => issue.severity === 'low' || issue.severity === 'minor');
  }

  isCompleted() {
    return this.status === 'completed';
  }

  needsImmediateAction() {
    return this.has_critical_issues || this.failed;
  }

  canBeCompleted() {
    return ['pending', 'in_progress', 'scheduled'].includes(this.status);
  }
}
