import JSONAPISerializer from '@ember-data/serializer/json-api';

export default class SchoolRouteSerializer extends JSONAPISerializer {
  /**
   * Serialize route attributes
   */
  attrs = {
    publicId: 'public_id',
    routeName: 'route_name',
    routeNumber: 'route_number',
    routeType: 'route_type',
    startTime: 'start_time',
    endTime: 'end_time',
    estimatedDuration: 'estimated_duration',
    estimatedDistance: 'estimated_distance',
    vehicleUuid: 'vehicle_uuid',
    driverUuid: 'driver_uuid',
    wheelchairAccessible: 'wheelchair_accessible',
    isActive: 'is_active',
    daysOfWeek: 'days_of_week',
    effectiveDate: 'effective_date',
    endDate: 'end_date',
    specialInstructions: 'special_instructions',
    busAssignments: 'bus_assignments',
    attendanceRecords: 'attendance_records'
  };

  /**
   * Normalize the response from API
   */
  normalize(modelClass, resourceHash, prop) {
    // Convert snake_case to camelCase
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

    // Convert camelCase to snake_case
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
