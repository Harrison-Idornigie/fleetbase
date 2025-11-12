import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportStudentsEditRoute extends Route {
  @service store;
  @service notifications;

  async model(params) {
    try {
      const [student, schools] = await Promise.all([
        this.store.findRecord('student', params.student_id, {
          include: 'school,emergency_contacts',
          reload: true
        }),
        this.store.findAll('school').catch(() => [])
      ]);

      return {
        student,
        schools,
        gradeOptions: ['K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
        statusOptions: [
          { value: 'active', label: 'Active' },
          { value: 'inactive', label: 'Inactive' },
          { value: 'graduated', label: 'Graduated' },
          { value: 'transferred', label: 'Transferred' }
        ],
        specialNeedsOptions: [
          { value: 'wheelchair', label: 'Wheelchair Access' },
          { value: 'medical', label: 'Medical Condition' },
          { value: 'behavioral', label: 'Behavioral Support' },
          { value: 'dietary', label: 'Dietary Restrictions' },
          { value: 'allergy', label: 'Allergies' },
          { value: 'other', label: 'Other' }
        ]
      };
    } catch (error) {
      console.error('Error loading student for editing:', error);
      this.notifications.error('Failed to load student');
      this.transitionTo('school-transport.students.index');
    }
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Reset controller state
    controller.setProperties({
      isLoading: false,
      errors: {},
      selectedTab: 'basic-info'
    });

    // Initialize form fields from model
    if (model.student) {
      controller.setProperties({
        student: model.student,
        firstName: model.student.first_name || '',
        lastName: model.student.last_name || '',
        studentId: model.student.student_id || '',
        dateOfBirth: model.student.date_of_birth || '',
        grade: model.student.grade || '',
        schoolId: model.student.school_id || '',
        status: model.student.status || 'active',
        address: model.student.address || '',
        city: model.student.city || '',
        state: model.student.state || '',
        zipCode: model.student.zip_code || '',
        parentName: model.student.parent_name || '',
        parentEmail: model.student.parent_email || '',
        parentPhone: model.student.parent_phone || '',
        emergencyContacts: model.student.emergency_contacts ? [...model.student.emergency_contacts] : [],
        specialNeeds: model.student.special_needs ? [...model.student.special_needs] : [],
        medicalInfo: model.student.medical_info ? { ...model.student.medical_info } : {},
        notes: model.student.notes || ''
      });
    }
  }

  resetController(controller, isExiting) {
    if (isExiting) {
      // Rollback any unsaved changes
      const student = this.modelFor(this.routeName)?.student;
      if (student && student.get('hasDirtyAttributes')) {
        student.rollbackAttributes();
      }
    }
  }
}

