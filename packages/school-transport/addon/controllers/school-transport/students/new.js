import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportStudentsNewController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isSaving = false;
  @tracked currentStep = 1;
  @tracked totalSteps = 4;

  // Form data
  @tracked studentForm = {
    // Basic Information
    first_name: '',
    last_name: '',
    student_id: '',
    date_of_birth: '',
    grade: '',
    school: '',

    // Contact Information
    address: '',
    city: '',
    state: '',
    zip_code: '',
    phone: '',
    email: '',

    // Parent/Guardian Information
    parent_first_name: '',
    parent_last_name: '',
    parent_relationship: '',
    parent_phone: '',
    parent_email: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    emergency_contact_relationship: '',

    // Transportation Details
    pickup_address: '',
    pickup_city: '',
    pickup_state: '',
    pickup_zip_code: '',
    dropoff_address: '',
    dropoff_city: '',
    dropoff_state: '',
    dropoff_zip_code: '',
    preferred_pickup_time: '',
    preferred_dropoff_time: '',

    // Special Needs & Medical
    has_special_needs: false,
    special_needs_description: '',
    medical_conditions: '',
    allergies: '',
    medications: '',
    doctor_name: '',
    doctor_phone: '',
    insurance_provider: '',
    insurance_policy_number: '',

    // Additional Information
    notes: '',
    photo_url: '',
    status: 'active'
  };

  // Validation errors
  @tracked errors = {};

  get fullName() {
    const first = this.studentForm.first_name || '';
    const last = this.studentForm.last_name || '';
    return `${first} ${last}`.trim();
  }

  get age() {
    if (!this.studentForm.date_of_birth) return null;

    const birthDate = new Date(this.studentForm.date_of_birth);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }

    return age;
  }

  get isStep1Valid() {
    return this.studentForm.first_name &&
           this.studentForm.last_name &&
           this.studentForm.student_id &&
           this.studentForm.date_of_birth &&
           this.studentForm.grade &&
           this.studentForm.school;
  }

  get isStep2Valid() {
    return this.studentForm.address &&
           this.studentForm.city &&
           this.studentForm.state &&
           this.studentForm.zip_code &&
           this.studentForm.parent_first_name &&
           this.studentForm.parent_last_name &&
           this.studentForm.parent_phone;
  }

  get isStep3Valid() {
    return this.studentForm.pickup_address &&
           this.studentForm.pickup_city &&
           this.studentForm.pickup_state &&
           this.studentForm.pickup_zip_code &&
           this.studentForm.dropoff_address &&
           this.studentForm.dropoff_city &&
           this.studentForm.dropoff_state &&
           this.studentForm.dropoff_zip_code;
  }

  get isFormValid() {
    return this.isStep1Valid && this.isStep2Valid && this.isStep3Valid;
  }

  get progressPercentage() {
    return Math.round((this.currentStep / this.totalSteps) * 100);
  }

  @action
  updateFormField(field, value) {
    this.studentForm = { ...this.studentForm, [field]: value };
    // Clear error for this field
    if (this.errors[field]) {
      this.errors = { ...this.errors };
      delete this.errors[field];
    }
  }

  @action
  nextStep() {
    if (this.currentStep < this.totalSteps) {
      this.currentStep++;
    }
  }

  @action
  previousStep() {
    if (this.currentStep > 1) {
      this.currentStep--;
    }
  }

  @action
  goToStep(step) {
    if (step >= 1 && step <= this.totalSteps) {
      this.currentStep = step;
    }
  }

  @action
  toggleSpecialNeeds() {
    this.studentForm = {
      ...this.studentForm,
      has_special_needs: !this.studentForm.has_special_needs
    };
  }

  @action
  async uploadPhoto(event) {
    const file = event.target.files[0];
    if (file) {
      // Validate file type
      if (!file.type.startsWith('image/')) {
        this.notifications.error('Please select a valid image file');
        return;
      }

      // Validate file size (max 5MB)
      if (file.size > 5 * 1024 * 1024) {
        this.notifications.error('File size must be less than 5MB');
        return;
      }

      try {
        const formData = new FormData();
        formData.append('photo', file);

        const response = await fetch('/api/school-transport/upload-photo', {
          method: 'POST',
          body: formData
        });

        if (response.ok) {
          const result = await response.json();
          this.studentForm = { ...this.studentForm, photo_url: result.url };
          this.notifications.success('Photo uploaded successfully');
        } else {
          throw new Error('Upload failed');
        }
      } catch (error) {
        console.error('Error uploading photo:', error);
        this.notifications.error('Failed to upload photo');
      }
    }
  }

  @action
  copyHomeAddress() {
    this.studentForm = {
      ...this.studentForm,
      pickup_address: this.studentForm.address,
      pickup_city: this.studentForm.city,
      pickup_state: this.studentForm.state,
      pickup_zip_code: this.studentForm.zip_code
    };
  }

  @action
  copyPickupToDropoff() {
    this.studentForm = {
      ...this.studentForm,
      dropoff_address: this.studentForm.pickup_address,
      dropoff_city: this.studentForm.pickup_city,
      dropoff_state: this.studentForm.pickup_state,
      dropoff_zip_code: this.studentForm.pickup_zip_code
    };
  }

  @action
  validateForm() {
    const errors = {};

    // Step 1 validation
    if (!this.studentForm.first_name?.trim()) {
      errors.first_name = 'First name is required';
    }
    if (!this.studentForm.last_name?.trim()) {
      errors.last_name = 'Last name is required';
    }
    if (!this.studentForm.student_id?.trim()) {
      errors.student_id = 'Student ID is required';
    }
    if (!this.studentForm.date_of_birth) {
      errors.date_of_birth = 'Date of birth is required';
    }
    if (!this.studentForm.grade) {
      errors.grade = 'Grade is required';
    }
    if (!this.studentForm.school?.trim()) {
      errors.school = 'School is required';
    }

    // Step 2 validation
    if (!this.studentForm.address?.trim()) {
      errors.address = 'Address is required';
    }
    if (!this.studentForm.city?.trim()) {
      errors.city = 'City is required';
    }
    if (!this.studentForm.state?.trim()) {
      errors.state = 'State is required';
    }
    if (!this.studentForm.zip_code?.trim()) {
      errors.zip_code = 'ZIP code is required';
    }
    if (!this.studentForm.parent_first_name?.trim()) {
      errors.parent_first_name = 'Parent first name is required';
    }
    if (!this.studentForm.parent_last_name?.trim()) {
      errors.parent_last_name = 'Parent last name is required';
    }
    if (!this.studentForm.parent_phone?.trim()) {
      errors.parent_phone = 'Parent phone is required';
    }

    // Step 3 validation
    if (!this.studentForm.pickup_address?.trim()) {
      errors.pickup_address = 'Pickup address is required';
    }
    if (!this.studentForm.pickup_city?.trim()) {
      errors.pickup_city = 'Pickup city is required';
    }
    if (!this.studentForm.pickup_state?.trim()) {
      errors.pickup_state = 'Pickup state is required';
    }
    if (!this.studentForm.pickup_zip_code?.trim()) {
      errors.pickup_zip_code = 'Pickup ZIP code is required';
    }
    if (!this.studentForm.dropoff_address?.trim()) {
      errors.dropoff_address = 'Drop-off address is required';
    }
    if (!this.studentForm.dropoff_city?.trim()) {
      errors.dropoff_city = 'Drop-off city is required';
    }
    if (!this.studentForm.dropoff_state?.trim()) {
      errors.dropoff_state = 'Drop-off state is required';
    }
    if (!this.studentForm.dropoff_zip_code?.trim()) {
      errors.dropoff_zip_code = 'Drop-off ZIP code is required';
    }

    // Special needs validation
    if (this.studentForm.has_special_needs && !this.studentForm.special_needs_description?.trim()) {
      errors.special_needs_description = 'Special needs description is required when special needs is checked';
    }

    this.errors = errors;
    return Object.keys(errors).length === 0;
  }

  @action
  async saveStudent() {
    if (!this.validateForm()) {
      this.notifications.error('Please fix the errors in the form');
      return;
    }

    this.isSaving = true;
    try {
      const student = this.store.createRecord('student', {
        ...this.studentForm,
        full_name: this.fullName,
        age: this.age
      });

      await student.save();

      this.notifications.success('Student created successfully');
      this.router.transitionTo('school-transport.students.view', student.id);
    } catch (error) {
      console.error('Error creating student:', error);
      this.notifications.error('Failed to create student');
    } finally {
      this.isSaving = false;
    }
  }

  @action
  async saveAndCreateAnother() {
    if (!this.validateForm()) {
      this.notifications.error('Please fix the errors in the form');
      return;
    }

    this.isSaving = true;
    try {
      const student = this.store.createRecord('student', {
        ...this.studentForm,
        full_name: this.fullName,
        age: this.age
      });

      await student.save();

      this.notifications.success('Student created successfully');

      // Reset form
      this.studentForm = {
        first_name: '',
        last_name: '',
        student_id: '',
        date_of_birth: '',
        grade: '',
        school: '',
        address: '',
        city: '',
        state: '',
        zip_code: '',
        phone: '',
        email: '',
        parent_first_name: '',
        parent_last_name: '',
        parent_relationship: '',
        parent_phone: '',
        parent_email: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        emergency_contact_relationship: '',
        pickup_address: '',
        pickup_city: '',
        pickup_state: '',
        pickup_zip_code: '',
        dropoff_address: '',
        dropoff_city: '',
        dropoff_state: '',
        dropoff_zip_code: '',
        preferred_pickup_time: '',
        preferred_dropoff_time: '',
        has_special_needs: false,
        special_needs_description: '',
        medical_conditions: '',
        allergies: '',
        medications: '',
        doctor_name: '',
        doctor_phone: '',
        insurance_provider: '',
        insurance_policy_number: '',
        notes: '',
        photo_url: '',
        status: 'active'
      };

      this.currentStep = 1;
      this.errors = {};
    } catch (error) {
      console.error('Error creating student:', error);
      this.notifications.error('Failed to create student');
    } finally {
      this.isSaving = false;
    }
  }

  @action
  cancel() {
    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
      this.router.transitionTo('school-transport.students.index');
    }
  }
}