import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportStudentsNewRoute extends Route {
  @service store;

  model() {
    return this.store.createRecord('student', {
      is_active: true,
      special_needs: [],
      medical_info: {}
    });
  }

  setupController(controller, model) {
    super.setupController(controller, model);
    
    // Load options for dropdowns
    this.loadFormOptions().then(options => {
      controller.setProperties(options);
    });
  }

  async loadFormOptions() {
    try {
      // Get existing schools and grades for form options
      const students = await this.store.query('student', { limit: 1000 });
      const schools = [...new Set(students.map(s => s.school))].filter(Boolean);
      const grades = [...new Set(students.map(s => s.grade))].filter(Boolean);

      return {
        schoolOptions: schools.sort(),
        gradeOptions: grades.sort(),
        specialNeedsOptions: [
          'wheelchair_accessible',
          'medical_alert', 
          'behavioral_support',
          'early_dismissal',
          'late_arrival'
        ],
        genderOptions: [
          { value: 'male', label: 'Male' },
          { value: 'female', label: 'Female' },
          { value: 'other', label: 'Other' }
        ]
      };
    } catch (error) {
      console.error('Error loading form options:', error);
      return {
        schoolOptions: [],
        gradeOptions: [],
        specialNeedsOptions: [],
        genderOptions: []
      };
    }
  }
}