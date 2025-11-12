import Model, { attr, belongsTo } from '@ember-data/model';
import { computed } from '@ember/object';

export default class IncidentReportModel extends Model {
  /** @ids */
  @attr('string') public_id;
  @attr('string') route_uuid;
  @attr('string') vehicle_uuid;
  @attr('string') driver_uuid;
  @attr('string') reported_by_uuid;

  /** @attributes */
  @attr('string') incident_type;
  @attr('string') severity;
  @attr('date') incident_date;
  @attr('string') incident_time;
  @attr('string') location;
  @attr() coordinates;
  @attr('string') description;
  @attr() involved_parties;
  @attr() witnesses;
  @attr() students_involved;
  @attr('boolean') injuries_reported;
  @attr() injury_details;
  @attr('boolean') property_damage;
  @attr() damage_details;
  @attr('boolean') police_notified;
  @attr('string') police_report_number;
  @attr('boolean') emergency_services_called;
  @attr() emergency_service_details;
  @attr() actions_taken;
  @attr() photos;
  @attr() documents;
  @attr('string') status;
  @attr('string') resolution;
  @attr('date') resolved_at;
  @attr('string') resolved_by_uuid;
  @attr() follow_up_actions;
  @attr('string') notes;
  @attr() meta;

  /** @computed */
  @computed('severity')
  get is_critical() {
    return ['critical', 'high', 'severe'].includes(this.severity?.toLowerCase());
  }

  @computed('severity')
  get severity_display() {
    const severities = {
      'critical': 'Critical',
      'high': 'High',
      'severe': 'Severe',
      'medium': 'Medium',
      'moderate': 'Moderate',
      'low': 'Low',
      'minor': 'Minor'
    };
    return severities[this.severity?.toLowerCase()] || this.severity;
  }

  @computed('severity')
  get severity_color() {
    const colors = {
      'critical': 'text-red-600',
      'high': 'text-red-500',
      'severe': 'text-red-600',
      'medium': 'text-orange-600',
      'moderate': 'text-orange-500',
      'low': 'text-yellow-600',
      'minor': 'text-yellow-500'
    };
    return colors[this.severity?.toLowerCase()] || 'text-gray-600';
  }

  @computed('incident_type')
  get type_display() {
    const types = {
      'accident': 'Vehicle Accident',
      'collision': 'Collision',
      'mechanical_failure': 'Mechanical Failure',
      'student_injury': 'Student Injury',
      'student_behavior': 'Student Behavior Issue',
      'unauthorized_person': 'Unauthorized Person',
      'route_deviation': 'Route Deviation',
      'late_arrival': 'Late Arrival',
      'missed_stop': 'Missed Stop',
      'safety_violation': 'Safety Violation',
      'weather_related': 'Weather Related',
      'vandalism': 'Vandalism',
      'theft': 'Theft',
      'other': 'Other'
    };
    return types[this.incident_type] || this.incident_type;
  }

  @computed('status')
  get status_display() {
    const statuses = {
      'reported': 'Reported',
      'under_investigation': 'Under Investigation',
      'pending_review': 'Pending Review',
      'resolved': 'Resolved',
      'closed': 'Closed',
      'escalated': 'Escalated'
    };
    return statuses[this.status] || this.status;
  }

  @computed('status')
  get status_color() {
    const colors = {
      'reported': 'text-yellow-600',
      'under_investigation': 'text-blue-600',
      'pending_review': 'text-indigo-600',
      'resolved': 'text-green-600',
      'closed': 'text-gray-600',
      'escalated': 'text-red-600'
    };
    return colors[this.status] || 'text-gray-600';
  }

  @computed('students_involved')
  get students_count() {
    if (!this.students_involved || !Array.isArray(this.students_involved)) return 0;
    return this.students_involved.length;
  }

  @computed('witnesses')
  get witnesses_count() {
    if (!this.witnesses || !Array.isArray(this.witnesses)) return 0;
    return this.witnesses.length;
  }

  @computed('involved_parties')
  get involved_parties_count() {
    if (!this.involved_parties || !Array.isArray(this.involved_parties)) return 0;
    return this.involved_parties.length;
  }

  @computed('photos')
  get photos_count() {
    if (!this.photos || !Array.isArray(this.photos)) return 0;
    return this.photos.length;
  }

  @computed('documents')
  get documents_count() {
    if (!this.documents || !Array.isArray(this.documents)) return 0;
    return this.documents.length;
  }

  @computed('injuries_reported', 'property_damage', 'police_notified', 'emergency_services_called')
  get requires_immediate_attention() {
    return this.injuries_reported || 
           this.property_damage || 
           this.police_notified || 
           this.emergency_services_called ||
           this.is_critical;
  }

  @computed('follow_up_actions')
  get has_pending_actions() {
    if (!this.follow_up_actions || !Array.isArray(this.follow_up_actions)) return false;
    return this.follow_up_actions.some(action => action.status === 'pending' || action.status === 'in_progress');
  }

  @computed('follow_up_actions')
  get pending_actions_count() {
    if (!this.follow_up_actions || !Array.isArray(this.follow_up_actions)) return 0;
    return this.follow_up_actions.filter(action => action.status === 'pending' || action.status === 'in_progress').length;
  }

  @computed('incident_date', 'incident_time')
  get formatted_incident_datetime() {
    if (!this.incident_date) return 'Unknown';
    
    const date = new Date(this.incident_date);
    const dateStr = date.toLocaleDateString();
    
    if (this.incident_time) {
      return `${dateStr} at ${this.incident_time}`;
    }
    
    return dateStr;
  }

  @computed('incident_date')
  get days_since_incident() {
    if (!this.incident_date) return null;
    
    const today = new Date();
    const incidentDate = new Date(this.incident_date);
    const diffTime = today - incidentDate;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return diffDays;
  }

  /** @relationships */
  @belongsTo('school-route', { inverse: null }) route;
  // Vehicle, driver, and reporter relationships would be defined through UUIDs

  /** @methods */
  async resolve(resolution, resolvedByUuid = null) {
    this.status = 'resolved';
    this.resolution = resolution;
    this.resolved_at = new Date();
    if (resolvedByUuid) {
      this.resolved_by_uuid = resolvedByUuid;
    }
    return this.save();
  }

  async close() {
    this.status = 'closed';
    if (!this.resolved_at) {
      this.resolved_at = new Date();
    }
    return this.save();
  }

  async escalate(reason = null) {
    this.status = 'escalated';
    if (reason) {
      this.notes = this.notes ? `${this.notes}; Escalated: ${reason}` : `Escalated: ${reason}`;
    }
    return this.save();
  }

  async addFollowUpAction(action) {
    const actions = this.follow_up_actions || [];
    actions.push({
      ...action,
      created_at: new Date().toISOString(),
      id: Date.now(),
      status: action.status || 'pending'
    });
    this.follow_up_actions = actions;
    return this.save();
  }

  async updateFollowUpAction(actionId, updates) {
    if (!this.follow_up_actions) return;
    
    const actions = this.follow_up_actions.map(action => {
      if (action.id === actionId) {
        return { ...action, ...updates, updated_at: new Date().toISOString() };
      }
      return action;
    });
    
    this.follow_up_actions = actions;
    return this.save();
  }

  async addPhoto(photoUrl, caption = null) {
    const photos = this.photos || [];
    photos.push({
      url: photoUrl,
      caption,
      uploaded_at: new Date().toISOString(),
      id: Date.now()
    });
    this.photos = photos;
    return this.save();
  }

  async addDocument(documentUrl, title, type = null) {
    const documents = this.documents || [];
    documents.push({
      url: documentUrl,
      title,
      type,
      uploaded_at: new Date().toISOString(),
      id: Date.now()
    });
    this.documents = documents;
    return this.save();
  }

  async addWitness(witness) {
    const witnesses = this.witnesses || [];
    witnesses.push({
      ...witness,
      added_at: new Date().toISOString(),
      id: Date.now()
    });
    this.witnesses = witnesses;
    return this.save();
  }

  getFormattedDate() {
    if (!this.incident_date) return 'Unknown';
    return new Date(this.incident_date).toLocaleDateString();
  }

  getFormattedResolvedDate() {
    if (!this.resolved_at) return 'Not Resolved';
    return new Date(this.resolved_at).toLocaleDateString();
  }

  isResolved() {
    return ['resolved', 'closed'].includes(this.status);
  }

  canBeResolved() {
    return !this.isResolved() && !this.has_pending_actions;
  }

  needsParentNotification() {
    return this.students_involved && this.students_involved.length > 0;
  }

  generateSummary() {
    return {
      id: this.id,
      type: this.type_display,
      severity: this.severity_display,
      date: this.formatted_incident_datetime,
      location: this.location,
      status: this.status_display,
      injuries: this.injuries_reported,
      property_damage: this.property_damage,
      students_involved: this.students_count,
      requires_attention: this.requires_immediate_attention
    };
  }
}
