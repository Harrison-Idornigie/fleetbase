import JSONAPISerializer from '@ember-data/serializer/json-api';

export default class StudentSerializer extends JSONAPISerializer {
  /**
   * Serialize student attributes
   */
  attrs = {
    publicId: 'public_id',
    studentId: 'student_id',
    firstName: 'first_name',
    lastName: 'last_name',
    dateOfBirth: 'date_of_birth',
    homeAddress: 'home_address',
    homeCoordinates: 'home_coordinates',
    parentName: 'parent_name',
    parentEmail: 'parent_email',
    parentPhone: 'parent_phone',
    emergencyContactName: 'emergency_contact_name',
    emergencyContactPhone: 'emergency_contact_phone',
    specialNeeds: 'special_needs',
    medicalInfo: 'medical_info',
    pickupLocation: 'pickup_location',
    pickupCoordinates: 'pickup_coordinates',
    dropoffLocation: 'dropoff_location',
    dropoffCoordinates: 'dropoff_coordinates',
    isActive: 'is_active',
    photoUrl: 'photo_url',
    busAssignments: 'bus_assignments',
    attendanceRecords: 'attendance_records',
    communications: 'communications'
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
