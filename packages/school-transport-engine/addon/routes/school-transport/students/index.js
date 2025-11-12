import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SchoolTransportStudentsIndexRoute extends Route {
  @service store;

  queryParams = {
    page: { refreshModel: true },
    limit: { refreshModel: true },
    search: { refreshModel: true },
    school: { refreshModel: true },
    grade: { refreshModel: true },
    special_needs: { refreshModel: true },
    active: { refreshModel: true }
  };

  async model(params) {
    const query = {
      page: params.page || 1,
      limit: params.limit || 25
    };

    // Add filters if provided
    if (params.search) query.search = params.search;
    if (params.school) query.school = params.school;
    if (params.grade) query.grade = params.grade;
    if (params.special_needs !== undefined) query.special_needs = params.special_needs;
    if (params.active !== undefined) query.active = params.active;

    const students = await this.store.query('student', query);

    // Load filter options
    const [schools, grades] = await Promise.all([
      this.loadSchools(),
      this.loadGrades()
    ]);

    return {
      students,
      schools,
      grades,
      meta: students.meta
    };
  }

  async loadSchools() {
    try {
      const students = await this.store.query('student', { limit: 1000 });
      const schools = [...new Set(students.map(s => s.school))].filter(Boolean);
      return schools.sort();
    } catch (error) {
      console.error('Error loading schools:', error);
      return [];
    }
  }

  async loadGrades() {
    try {
      const students = await this.store.query('student', { limit: 1000 });
      const grades = [...new Set(students.map(s => s.grade))].filter(Boolean);
      return grades.sort();
    } catch (error) {
      console.error('Error loading grades:', error);
      return [];
    }
  }
}