import Mixin from '@ember/object/mixin';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';

/**
 * Mixin for forms with validation support
 */
export default Mixin.create({
  @service validation;

  @tracked formData: {},
  @tracked errors: {},
  @tracked isValidating: false,
  @tracked isDirty: false,

  /**
   * Initialize form data
   */
  initializeForm(data = {}) {
    this.formData = { ...data };
    this.errors = {};
    this.isDirty = false;
  },

  /**
   * Validate entire form
   */
  validateForm() {
    this.isValidating = true;
    const validationResult = this.performValidation(this.formData);
    this.errors = validationResult.errors;
    this.isValidating = false;
    return validationResult.isValid;
  },

  /**
   * Validate a single field
   */
  validateField(fieldName) {
    const value = this.formData[fieldName];
    const fieldError = this.validateSingleField(fieldName, value);
    
    if (fieldError) {
      this.errors = {
        ...this.errors,
        [fieldName]: fieldError
      };
    } else {
      const newErrors = { ...this.errors };
      delete newErrors[fieldName];
      this.errors = newErrors;
    }
  },

  /**
   * Clear form errors
   */
  clearErrors() {
    this.errors = {};
  },

  /**
   * Clear specific field error
   */
  clearFieldError(fieldName) {
    const newErrors = { ...this.errors };
    delete newErrors[fieldName];
    this.errors = newErrors;
  },

  /**
   * Check if form has errors
   */
  get hasErrors() {
    return Object.keys(this.errors).length > 0;
  },

  /**
   * Check if field has error
   */
  hasFieldError(fieldName) {
    return !!this.errors[fieldName];
  },

  /**
   * Get field error message
   */
  getFieldError(fieldName) {
    return this.errors[fieldName];
  },

  /**
   * Check if form is valid
   */
  get isValid() {
    return !this.hasErrors && !this.isValidating;
  },

  /**
   * Mark form as dirty
   */
  markAsDirty() {
    this.isDirty = true;
  },

  /**
   * Mark form as pristine
   */
  markAsPristine() {
    this.isDirty = false;
  },

  @action
  updateField(fieldName, value) {
    this.formData = {
      ...this.formData,
      [fieldName]: value
    };
    this.markAsDirty();
    
    // Clear error when field is updated
    if (this.hasFieldError(fieldName)) {
      this.clearFieldError(fieldName);
    }
  },

  @action
  updateFieldWithValidation(fieldName, value) {
    this.updateField(fieldName, value);
    this.validateField(fieldName);
  },

  @action
  resetForm() {
    this.formData = {};
    this.errors = {};
    this.isDirty = false;
    this.isValidating = false;
  },

  /**
   * Override these methods in your controller/component
   */
  performValidation(data) {
    throw new Error('performValidation() must be implemented');
  },

  validateSingleField(fieldName, value) {
    // Override to provide field-specific validation
    return null;
  }
});
