import JSONAPISerializer from '@ember-data/serializer/json-api';

export default class BusAssignmentSerializer extends JSONAPISerializer {
  /**
   * Serialize assignment attributes
   */
  attrs = {
    publicId: 'public_id',
    stopSequence: 'stop_sequence',
    pickupStop: 'pickup_stop',
    pickupCoordinates: 'pickup_coordinates',
    pickupTime: 'pickup_time',
    dropoffStop: 'dropoff_stop',
    dropoffCoordinates: 'dropoff_coordinates',
    dropoffTime: 'dropoff_time',
    assignmentType: 'assignment_type',
    effectiveDate: 'effective_date',
    endDate: 'end_date',
    requiresAssistance: 'requires_assistance',
    specialInstructions: 'special_instructions',
    attendanceTracking: 'attendance_tracking',
    attendanceRecords: 'attendance_records'
  };

  /**
   * Normalize the response from API
   */
  normalize(modelClass, resourceHash, prop) {
    if (resourceHash.attributes) {
      Object.keys(this.attrs).forEach(camelKey => {
        const snakeKey = this.attrs[camelKey];
        if (resourceHash.attributes[snakeKey] !== undefined) {
          resourceHash.attributes[camelKey] = resourceHash.attributes[snakeKey];
          delete resourceHash.attributes[snakeKey];
        }
      });
    }

    return super.normalize(modelClass, resourceHash, prop);
  }

  /**
   * Serialize the record before sending to API
   */
  serialize(snapshot, options) {
    const json = super.serialize(snapshot, options);

    if (json.data && json.data.attributes) {
      Object.keys(this.attrs).forEach(camelKey => {
        const snakeKey = this.attrs[camelKey];
        if (json.data.attributes[camelKey] !== undefined) {
          json.data.attributes[snakeKey] = json.data.attributes[camelKey];
          delete json.data.attributes[camelKey];
        }
      });
    }

    return json;
  }
}
