import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportStudentsEditController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked currentStep = 1;
  @tracked totalSteps = 4;
  @tracked showPhotoUpload = false;
  @tracked selectedTab = 'basic-info';

  // Form data
  @tracked firstName = '';
  @tracked lastName = '';
  @tracked dateOfBirth = '';
  @tracked grade = '';
  @tracked studentId = '';
  @tracked status = 'active';

  // Parent/Guardian info
  @tracked parentFirstName = '';
  @tracked parentLastName = '';
  @tracked parentPhone = '';
  @tracked parentEmail = '';
  @tracked parentRelationship = 'parent';

  // Emergency contact
  @tracked emergencyContactName = '';
  @tracked emergencyContactPhone = '';
  @tracked emergencyContactRelationship = '';

  // Address info
  @tracked address = '';
  @tracked city = '';
  @tracked state = '';
  @tracked zipCode = '';

  // Medical info
  @tracked medicalConditions = '';
  @tracked allergies = '';
  @tracked medications = '';
  @tracked doctorName = '';
  @tracked doctorPhone = '';
  @tracked insuranceProvider = '';
  @tracked insurancePolicyNumber = '';

  // Additional info
  @tracked specialNeeds = '';
  @tracked transportationNotes = '';
  @tracked pickupTime = '';
  @tracked dropoffTime = '';

  // Validation errors
  @tracked errors = {};

  get student() {
    return this.model.student;
  }

  get isFirstStep() {
    return this.currentStep === 1;
  }

  get isLastStep() {
    return this.currentStep === this.totalSteps;
  }

  get progressPercentage() {
    return Math.round((this.currentStep / this.totalSteps) * 100);
  }

  get canProceed() {
    return this.validateCurrentStep();
  }

  get stepTitle() {
    const titles = {
      1: 'Basic Information',
      2: 'Parent & Emergency Contact',
      3: 'Address & Medical Info',
      4: 'Additional Details'
    };
    return titles[this.currentStep] || 'Edit Student';
  }

  get stepDescription() {
    const descriptions = {
      1: 'Enter the student\'s basic personal information',
      2: 'Provide parent/guardian and emergency contact details',
      3: 'Add address and medical information',
      4: 'Include special needs and transportation preferences'
    };
    return descriptions[this.currentStep] || '';
  }

  constructor() {
    super(...arguments);
    this.initializeFormData();
  }

  initializeFormData() {
    if (this.student) {
      // Basic info
      this.firstName = this.student.first_name || '';
      this.lastName = this.student.last_name || '';
      this.dateOfBirth = this.student.date_of_birth || '';
      this.grade = this.student.grade || '';
      this.studentId = this.student.student_id || '';
      this.status = this.student.status || 'active';

      // Parent info
      this.parentFirstName = this.student.parent_first_name || '';
      this.parentLastName = this.student.parent_last_name || '';
      this.parentPhone = this.student.parent_phone || '';
      this.parentEmail = this.student.parent_email || '';
      this.parentRelationship = this.student.parent_relationship || 'parent';

      // Emergency contact
      this.emergencyContactName = this.student.emergency_contact_name || '';
      this.emergencyContactPhone = this.student.emergency_contact_phone || '';
      this.emergencyContactRelationship = this.student.emergency_contact_relationship || '';

      // Address
      this.address = this.student.address || '';
      this.city = this.student.city || '';
      this.state = this.student.state || '';
      this.zipCode = this.student.zip_code || '';

      // Medical
      this.medicalConditions = this.student.medical_conditions || '';
      this.allergies = this.student.allergies || '';
      this.medications = this.student.medications || '';
      this.doctorName = this.student.doctor_name || '';
      this.doctorPhone = this.student.doctor_phone || '';
      this.insuranceProvider = this.student.insurance_provider || '';
      this.insurancePolicyNumber = this.student.insurance_policy_number || '';

      // Additional
      this.specialNeeds = this.student.special_needs || '';
      this.transportationNotes = this.student.transportation_notes || '';
      this.pickupTime = this.student.pickup_time || '';
      this.dropoffTime = this.student.dropoff_time || '';
    }
  }

  validateCurrentStep() {
    this.errors = {};
    let isValid = true;

    switch (this.currentStep) {
      case 1:
        if (!this.firstName.trim()) {
          this.errors.firstName = 'First name is required';
          isValid = false;
        }
        if (!this.lastName.trim()) {
          this.errors.lastName = 'Last name is required';
          isValid = false;
        }
        if (!this.dateOfBirth) {
          this.errors.dateOfBirth = 'Date of birth is required';
          isValid = false;
        }
        if (!this.grade) {
          this.errors.grade = 'Grade is required';
          isValid = false;
        }
        break;

      case 2:
        if (!this.parentFirstName.trim()) {
          this.errors.parentFirstName = 'Parent first name is required';
          isValid = false;
        }
        if (!this.parentPhone.trim()) {
          this.errors.parentPhone = 'Parent phone is required';
          isValid = false;
        }
        if (this.parentEmail && !this.isValidEmail(this.parentEmail)) {
          this.errors.parentEmail = 'Invalid email format';
          isValid = false;
        }
        break;

      case 3:
        if (!this.address.trim()) {
          this.errors.address = 'Address is required';
          isValid = false;
        }
        if (!this.city.trim()) {
          this.errors.city = 'City is required';
          isValid = false;
        }
        if (!this.state.trim()) {
          this.errors.state = 'State is required';
          isValid = false;
        }
        if (!this.zipCode.trim()) {
          this.errors.zipCode = 'ZIP code is required';
          isValid = false;
        }
        break;

      case 4:
        // Additional details are optional
        break;
    }

    return isValid;
  }

  isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  @action
  selectTab(tab) {
    this.selectedTab = tab;
  }

  @action
  nextStep() {
    if (this.canProceed && !this.isLastStep) {
      this.currentStep++;
    }
  }

  @action
  previousStep() {
    if (!this.isFirstStep) {
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
  updateField(field, value) {
    this[field] = value;
    // Clear error for this field if it exists
    if (this.errors[field]) {
      delete this.errors[field];
    }
  }

  @action
  async saveStudent() {
    if (!this.validateCurrentStep()) {
      this.notifications.error('Please fix the validation errors before saving');
      return;
    }

    this.isLoading = true;
    try {
      // Update student model with form data
      this.student.first_name = this.firstName;
      this.student.last_name = this.lastName;
      this.student.date_of_birth = this.dateOfBirth;
      this.student.grade = this.grade;
      this.student.student_id = this.studentId;
      this.student.status = this.status;

      this.student.parent_first_name = this.parentFirstName;
      this.student.parent_last_name = this.parentLastName;
      this.student.parent_phone = this.parentPhone;
      this.student.parent_email = this.parentEmail;
      this.student.parent_relationship = this.parentRelationship;

      this.student.emergency_contact_name = this.emergencyContactName;
      this.student.emergency_contact_phone = this.emergencyContactPhone;
      this.student.emergency_contact_relationship = this.emergencyContactRelationship;

      this.student.address = this.address;
      this.student.city = this.city;
      this.student.state = this.state;
      this.student.zip_code = this.zipCode;

      this.student.medical_conditions = this.medicalConditions;
      this.student.allergies = this.allergies;
      this.student.medications = this.medications;
      this.student.doctor_name = this.doctorName;
      this.student.doctor_phone = this.doctorPhone;
      this.student.insurance_provider = this.insuranceProvider;
      this.student.insurance_policy_number = this.insurancePolicyNumber;

      this.student.special_needs = this.specialNeeds;
      this.student.transportation_notes = this.transportationNotes;
      this.student.pickup_time = this.pickupTime;
      this.student.dropoff_time = this.dropoffTime;

      await this.student.save();

      this.notifications.success('Student updated successfully');
      this.router.transitionTo('school-transport.students.view', this.student.id);
    } catch (error) {
      console.error('Error saving student:', error);
      this.notifications.error('Failed to update student');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  cancelEdit() {
    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
      this.router.transitionTo('school-transport.students.view', this.student.id);
    }
  }

  @action
  async updatePhoto(event) {
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

      this.isLoading = true;
      try {
        const formData = new FormData();
        formData.append('photo', file);

        const response = await fetch(`/api/school-transport/students/${this.student.id}/photo`, {
          method: 'POST',
          body: formData
        });

        if (response.ok) {
          const result = await response.json();
          this.student.photo_url = result.url;
          this.notifications.success('Photo updated successfully');
        } else {
          throw new Error('Upload failed');
        }
      } catch (error) {
        console.error('Error updating photo:', error);
        this.notifications.error('Failed to update photo');
      } finally {
        this.isLoading = false;
      }
    }
  }

  @action
  togglePhotoUpload() {
    this.showPhotoUpload = !this.showPhotoUpload;
  }

  @action
  async validateAndProceed() {
    if (this.validateCurrentStep()) {
      if (this.isLastStep) {
        await this.saveStudent();
      } else {
        this.nextStep();
      }
    } else {
      this.notifications.error('Please fix the validation errors');
    }
  }
}