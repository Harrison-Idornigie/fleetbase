import Model, { attr, belongsTo } from '@ember-data/model';

export default class TrackingLogModel extends Model {
  @attr('string') public_id;
  @attr('string') company_uuid;
  @attr('string') trip_uuid;
  @attr('string') bus_uuid;
  @attr('string') driver_uuid;
  @attr('') location;
  @attr('number') latitude;
  @attr('number') longitude;
  @attr('number') altitude;
  @attr('number') speed;
  @attr('number') heading;
  @attr('number') accuracy;
  @attr('date') timestamp;
  @attr('string') event_type;
  @attr('') telemetry;
  @attr('string') status;
  @attr('string') notes;
  @attr('') meta;
  @attr('date') created_at;
  @attr('date') updated_at;

  // Relationships
  @belongsTo('trip', { async: true, inverse: 'trackingLogs' }) trip;
  @belongsTo('bus', { async: true, inverse: null }) bus;
  @belongsTo('driver', { async: true, inverse: null }) driver;

  // Computed properties
  get coordinates() {
    return {
      lat: this.latitude,
      lng: this.longitude
    };
  }

  get hasValidLocation() {
    return this.latitude && this.longitude;
  }

  get speedInMph() {
    // Convert m/s to mph
    return this.speed ? Math.round(this.speed * 2.23694) : 0;
  }

  get speedInKmh() {
    // Convert m/s to km/h
    return this.speed ? Math.round(this.speed * 3.6) : 0;
  }

  get isMoving() {
    return this.speed && this.speed > 1; // More than 1 m/s
  }

  get formattedTimestamp() {
    if (!this.timestamp) return '';
    return new Date(this.timestamp).toLocaleString();
  }
}
