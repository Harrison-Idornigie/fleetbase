import Model, { attr, belongsTo } from '@ember-data/model';
import { computed } from '@ember/object';

export default class DriverCertificationModel extends Model {
  /** @ids */
  @attr('string') public_id;
  @attr('string') driver_uuid;

  /** @attributes */
  @attr('string') certification_type;
  @attr('string') certification_number;
  @attr('string') issuing_authority;
  @attr('date') issue_date;
  @attr('date') expiration_date;
  @attr('string') status;
  @attr('string') document_url;
  @attr() verification_details;
  @attr('string') notes;
  @attr('boolean') requires_renewal;
  @attr('date') last_verified_at;
  @attr('string') verified_by_uuid;
  @attr() meta;

  /** @computed */
  @computed('expiration_date')
  get days_until_expiration() {
    if (!this.expiration_date) return null;
    
    const today = new Date();
    const expDate = new Date(this.expiration_date);
    const diffTime = expDate - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return diffDays;
  }

  @computed('days_until_expiration')
  get is_expired() {
    return this.days_until_expiration !== null && this.days_until_expiration < 0;
  }

  @computed('days_until_expiration')
  get is_expiring_soon() {
    return this.days_until_expiration !== null && 
           this.days_until_expiration >= 0 && 
           this.days_until_expiration <= 30;
  }

  @computed('is_expired', 'is_expiring_soon', 'status')
  get compliance_status() {
    if (this.is_expired || this.status === 'expired') return 'expired';
    if (this.is_expiring_soon) return 'expiring_soon';
    if (this.status === 'active') return 'compliant';
    return 'non_compliant';
  }

  @computed('compliance_status')
  get compliance_color() {
    const colors = {
      'compliant': 'text-green-600',
      'expiring_soon': 'text-yellow-600',
      'expired': 'text-red-600',
      'non_compliant': 'text-gray-600'
    };
    return colors[this.compliance_status] || 'text-gray-600';
  }

  @computed('certification_type')
  get type_display() {
    const types = {
      'cdl_a': 'CDL Class A',
      'cdl_b': 'CDL Class B',
      'cdl_c': 'CDL Class C',
      'school_bus_endorsement': 'School Bus Endorsement',
      'passenger_endorsement': 'Passenger Endorsement',
      'first_aid': 'First Aid Certification',
      'cpr': 'CPR Certification',
      'defensive_driving': 'Defensive Driving',
      'background_check': 'Background Check',
      'physical_exam': 'Physical Examination',
      'drug_test': 'Drug Test'
    };
    return types[this.certification_type] || this.certification_type;
  }

  @computed('status')
  get status_display() {
    const statuses = {
      'active': 'Active',
      'expired': 'Expired',
      'pending': 'Pending',
      'revoked': 'Revoked',
      'suspended': 'Suspended'
    };
    return statuses[this.status] || this.status;
  }

  @computed('status')
  get status_color() {
    const colors = {
      'active': 'text-green-600',
      'expired': 'text-red-600',
      'pending': 'text-yellow-600',
      'revoked': 'text-red-600',
      'suspended': 'text-orange-600'
    };
    return colors[this.status] || 'text-gray-600';
  }

  /** @relationships */
  // Driver relationship would be defined through driver_uuid

  /** @methods */
  async renew(newExpirationDate, documentUrl = null) {
    this.status = 'active';
    this.expiration_date = newExpirationDate;
    if (documentUrl) {
      this.document_url = documentUrl;
    }
    this.last_verified_at = new Date();
    return this.save();
  }

  async verify(verifiedByUuid = null) {
    this.last_verified_at = new Date();
    if (verifiedByUuid) {
      this.verified_by_uuid = verifiedByUuid;
    }
    return this.save();
  }

  async revoke(reason = null) {
    this.status = 'revoked';
    if (reason) {
      this.notes = this.notes ? `${this.notes}; Revoked: ${reason}` : `Revoked: ${reason}`;
    }
    return this.save();
  }

  async suspend(reason = null) {
    this.status = 'suspended';
    if (reason) {
      this.notes = this.notes ? `${this.notes}; Suspended: ${reason}` : `Suspended: ${reason}`;
    }
    return this.save();
  }

  needsRenewal(daysBeforeExpiration = 30) {
    return this.days_until_expiration !== null && 
           this.days_until_expiration <= daysBeforeExpiration;
  }

  isValid() {
    return this.status === 'active' && !this.is_expired;
  }

  getFormattedExpirationDate() {
    if (!this.expiration_date) return 'N/A';
    return new Date(this.expiration_date).toLocaleDateString();
  }

  getFormattedIssueDate() {
    if (!this.issue_date) return 'N/A';
    return new Date(this.issue_date).toLocaleDateString();
  }

  getFormattedLastVerified() {
    if (!this.last_verified_at) return 'Never';
    return new Date(this.last_verified_at).toLocaleDateString();
  }

  calculateDaysValid() {
    if (!this.issue_date || !this.expiration_date) return 0;
    
    const issueDate = new Date(this.issue_date);
    const expDate = new Date(this.expiration_date);
    const diffTime = expDate - issueDate;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return diffDays;
  }
}
