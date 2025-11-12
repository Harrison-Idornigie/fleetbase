import Service from '@ember/service';

export default class ValidationService extends Service {
  /**
   * Validate student data
   * @param {Object} data - Student data to validate
   * @returns {Object} - Object containing isValid boolean and errors object
   */
  validateStudent(data) {
    const errors = {};

    // Required fields
    if (!data.firstName || !data.firstName.trim()) {
      errors.firstName = 'First name is required';
    } else if (data.firstName.length < 2) {
      errors.firstName = 'First name must be at least 2 characters';
    } else if (data.firstName.length > 50) {
      errors.firstName = 'First name must not exceed 50 characters';
    }

    if (!data.lastName || !data.lastName.trim()) {
      errors.lastName = 'Last name is required';
    } else if (data.lastName.length < 2) {
      errors.lastName = 'Last name must be at least 2 characters';
    } else if (data.lastName.length > 50) {
      errors.lastName = 'Last name must not exceed 50 characters';
    }

    if (!data.dateOfBirth) {
      errors.dateOfBirth = 'Date of birth is required';
    } else {
      const dob = new Date(data.dateOfBirth);
      const today = new Date();
      const age = Math.floor((today - dob) / (365.25 * 24 * 60 * 60 * 1000));
      
      if (age < 3 || age > 22) {
        errors.dateOfBirth = 'Student age must be between 3 and 22 years';
      }
    }

    if (!data.grade) {
      errors.grade = 'Grade is required';
    }

    // Address validation
    if (!data.address || !data.address.trim()) {
      errors.address = 'Home address is required';
    }

    if (!data.city || !data.city.trim()) {
      errors.city = 'City is required';
    }

    if (!data.state || !data.state.trim()) {
      errors.state = 'State is required';
    }

    if (!data.postalCode || !data.postalCode.trim()) {
      errors.postalCode = 'Postal code is required';
    } else if (!/^\d{5}(-\d{4})?$/.test(data.postalCode)) {
      errors.postalCode = 'Invalid postal code format (e.g., 12345 or 12345-6789)';
    }

    // Parent/Guardian validation
    if (!data.parentName || !data.parentName.trim()) {
      errors.parentName = 'Parent/guardian name is required';
    }

    if (!data.parentEmail || !data.parentEmail.trim()) {
      errors.parentEmail = 'Parent email is required';
    } else if (!this.isValidEmail(data.parentEmail)) {
      errors.parentEmail = 'Invalid email format';
    }

    if (!data.parentPhone || !data.parentPhone.trim()) {
      errors.parentPhone = 'Parent phone is required';
    } else if (!this.isValidPhone(data.parentPhone)) {
      errors.parentPhone = 'Invalid phone format (e.g., (123) 456-7890)';
    }

    // Emergency contact validation
    if (!data.emergencyContactName || !data.emergencyContactName.trim()) {
      errors.emergencyContactName = 'Emergency contact name is required';
    }

    if (!data.emergencyContactPhone || !data.emergencyContactPhone.trim()) {
      errors.emergencyContactPhone = 'Emergency contact phone is required';
    } else if (!this.isValidPhone(data.emergencyContactPhone)) {
      errors.emergencyContactPhone = 'Invalid phone format';
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  }

  /**
   * Validate route data
   * @param {Object} data - Route data to validate
   * @returns {Object} - Validation result
   */
  validateRoute(data) {
    const errors = {};

    if (!data.name || !data.name.trim()) {
      errors.name = 'Route name is required';
    } else if (data.name.length < 3) {
      errors.name = 'Route name must be at least 3 characters';
    }

    if (!data.routeNumber || !data.routeNumber.trim()) {
      errors.routeNumber = 'Route number is required';
    }

    if (!data.type) {
      errors.type = 'Route type is required';
    }

    if (!data.startTime) {
      errors.startTime = 'Start time is required';
    }

    if (!data.endTime) {
      errors.endTime = 'End time is required';
    } else if (data.startTime && data.endTime <= data.startTime) {
      errors.endTime = 'End time must be after start time';
    }

    if (!data.vehicleId) {
      errors.vehicleId = 'Vehicle assignment is required';
    }

    if (!data.driverId) {
      errors.driverId = 'Driver assignment is required';
    }

    if (data.stops && data.stops.length < 2) {
      errors.stops = 'Route must have at least 2 stops';
    }

    if (data.capacity && data.capacity < 1) {
      errors.capacity = 'Capacity must be at least 1';
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  }

  /**
   * Validate assignment data
   * @param {Object} data - Assignment data to validate
   * @returns {Object} - Validation result
   */
  validateAssignment(data) {
    const errors = {};

    if (!data.studentId) {
      errors.studentId = 'Student is required';
    }

    if (!data.routeId) {
      errors.routeId = 'Route is required';
    }

    if (!data.pickupStopId) {
      errors.pickupStopId = 'Pickup stop is required';
    }

    if (!data.dropoffStopId) {
      errors.dropoffStopId = 'Dropoff stop is required';
    }

    if (!data.startDate) {
      errors.startDate = 'Start date is required';
    }

    if (data.endDate && data.startDate && data.endDate < data.startDate) {
      errors.endDate = 'End date must be after start date';
    }

    if (!data.scheduleType) {
      errors.scheduleType = 'Schedule type is required';
    }

    if (data.scheduleType === 'specific_days' && (!data.scheduledDays || data.scheduledDays.length === 0)) {
      errors.scheduledDays = 'At least one day must be selected';
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  }

  /**
   * Validate communication data
   * @param {Object} data - Communication data to validate
   * @returns {Object} - Validation result
   */
  validateCommunication(data) {
    const errors = {};

    if (!data.subject || !data.subject.trim()) {
      errors.subject = 'Subject is required';
    } else if (data.subject.length > 200) {
      errors.subject = 'Subject must not exceed 200 characters';
    }

    if (!data.message || !data.message.trim()) {
      errors.message = 'Message content is required';
    } else if (data.message.length < 10) {
      errors.message = 'Message must be at least 10 characters';
    }

    if (data.type === 'sms' && data.message.length > 1600) {
      errors.message = 'SMS messages cannot exceed 1600 characters';
    }

    if (!data.type) {
      errors.type = 'Message type is required';
    }

    if (!data.recipientType) {
      errors.recipientType = 'Recipient type is required';
    }

    if (data.recipientType === 'custom' && (!data.customRecipients || !data.customRecipients.trim())) {
      errors.customRecipients = 'Custom recipients are required';
    }

    if (data.recipientType !== 'all_students' && 
        data.recipientType !== 'all_parents' && 
        (!data.recipients || data.recipients.length === 0)) {
      errors.recipients = 'At least one recipient must be selected';
    }

    if (data.scheduledSendDate && !data.scheduledSendTime) {
      errors.scheduledSendTime = 'Time is required when scheduling';
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  }

  /**
   * Validate report data
   * @param {Object} data - Report data to validate
   * @returns {Object} - Validation result
   */
  validateReport(data) {
    const errors = {};

    if (!data.name || !data.name.trim()) {
      errors.name = 'Report name is required';
    } else if (data.name.length < 3) {
      errors.name = 'Report name must be at least 3 characters';
    }

    if (!data.description || !data.description.trim()) {
      errors.description = 'Report description is required';
    }

    if (!data.type) {
      errors.type = 'Report type is required';
    }

    if (!data.category) {
      errors.category = 'Report category is required';
    }

    if (data.dateRangeType === 'custom') {
      if (!data.customStartDate) {
        errors.customStartDate = 'Start date is required';
      }
      if (!data.customEndDate) {
        errors.customEndDate = 'End date is required';
      }
      if (data.customStartDate && data.customEndDate && data.customStartDate > data.customEndDate) {
        errors.customEndDate = 'End date must be after start date';
      }
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  }

  /**
   * Validate email format
   * @param {String} email - Email address to validate
   * @returns {Boolean} - True if valid
   */
  isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  /**
   * Validate phone format
   * @param {String} phone - Phone number to validate
   * @returns {Boolean} - True if valid
   */
  isValidPhone(phone) {
    // Remove all non-numeric characters for validation
    const cleaned = phone.replace(/\D/g, '');
    // Check if it's a valid 10-digit US phone number
    return cleaned.length === 10 || cleaned.length === 11;
  }

  /**
   * Validate URL format
   * @param {String} url - URL to validate
   * @returns {Boolean} - True if valid
   */
  isValidUrl(url) {
    try {
      new URL(url);
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Validate date is not in the past
   * @param {String|Date} date - Date to validate
   * @returns {Boolean} - True if valid
   */
  isNotPastDate(date) {
    const inputDate = new Date(date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return inputDate >= today;
  }

  /**
   * Validate date range
   * @param {String|Date} startDate - Start date
   * @param {String|Date} endDate - End date
   * @returns {Object} - Validation result
   */
  validateDateRange(startDate, endDate) {
    const errors = {};

    if (!startDate) {
      errors.startDate = 'Start date is required';
    }

    if (!endDate) {
      errors.endDate = 'End date is required';
    }

    if (startDate && endDate) {
      const start = new Date(startDate);
      const end = new Date(endDate);

      if (end < start) {
        errors.endDate = 'End date must be after start date';
      }

      const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
      if (daysDiff > 365) {
        errors.endDate = 'Date range cannot exceed 365 days';
      }
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  }

  /**
   * Sanitize input string
   * @param {String} input - String to sanitize
   * @returns {String} - Sanitized string
   */
  sanitizeInput(input) {
    if (typeof input !== 'string') return input;
    
    return input
      .trim()
      .replace(/[<>]/g, '') // Remove potential HTML tags
      .replace(/[^\w\s@.,'-]/gi, ''); // Keep only safe characters
  }

  /**
   * Validate required fields
   * @param {Object} data - Data object to validate
   * @param {Array} requiredFields - Array of required field names
   * @returns {Object} - Validation result
   */
  validateRequiredFields(data, requiredFields) {
    const errors = {};

    requiredFields.forEach(field => {
      if (!data[field] || (typeof data[field] === 'string' && !data[field].trim())) {
        errors[field] = `${this.formatFieldName(field)} is required`;
      }
    });

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  }

  /**
   * Format field name for error messages
   * @param {String} fieldName - Field name to format
   * @returns {String} - Formatted field name
   */
  formatFieldName(fieldName) {
    return fieldName
      .replace(/([A-Z])/g, ' $1')
      .replace(/^./, str => str.toUpperCase())
      .trim();
  }

  /**
   * Validate numeric range
   * @param {Number} value - Value to validate
   * @param {Number} min - Minimum value
   * @param {Number} max - Maximum value
   * @returns {Boolean} - True if valid
   */
  isInRange(value, min, max) {
    return value >= min && value <= max;
  }

  /**
   * Validate string length
   * @param {String} value - String to validate
   * @param {Number} min - Minimum length
   * @param {Number} max - Maximum length
   * @returns {Object} - Validation result
   */
  validateLength(value, min, max) {
    const errors = {};
    
    if (value.length < min) {
      errors.length = `Must be at least ${min} characters`;
    }
    
    if (value.length > max) {
      errors.length = `Must not exceed ${max} characters`;
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    };
  }
}
