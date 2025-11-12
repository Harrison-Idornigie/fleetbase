import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportStudentsIndexController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked searchQuery = '';
  @tracked selectedGrade = '';
  @tracked selectedSchool = '';
  @tracked selectedStatus = '';
  @tracked selectedSortBy = 'name';
  @tracked selectedSortOrder = 'asc';
  @tracked currentPage = 1;
  @tracked pageSize = 25;
  @tracked isLoading = false;
  @tracked showBulkActions = false;
  @tracked selectedStudents = new Set();

  get filteredStudents() {
    let students = this.model.students || [];

    // Apply search filter
    if (this.searchQuery) {
      const query = this.searchQuery.toLowerCase();
      students = students.filter(student =>
        student.full_name?.toLowerCase().includes(query) ||
        student.student_id?.toLowerCase().includes(query) ||
        student.school?.toLowerCase().includes(query)
      );
    }

    // Apply grade filter
    if (this.selectedGrade) {
      students = students.filter(student => student.grade === this.selectedGrade);
    }

    // Apply school filter
    if (this.selectedSchool) {
      students = students.filter(student => student.school === this.selectedSchool);
    }

    // Apply status filter
    if (this.selectedStatus) {
      students = students.filter(student => student.status === this.selectedStatus);
    }

    // Apply sorting
    students.sort((a, b) => {
      let aValue = a[this.selectedSortBy];
      let bValue = b[this.selectedSortBy];

      if (this.selectedSortBy === 'full_name') {
        aValue = aValue || '';
        bValue = bValue || '';
      }

      if (this.selectedSortOrder === 'asc') {
        return aValue > bValue ? 1 : -1;
      } else {
        return aValue < bValue ? 1 : -1;
      }
    });

    return students;
  }

  get paginatedStudents() {
    const start = (this.currentPage - 1) * this.pageSize;
    const end = start + this.pageSize;
    return this.filteredStudents.slice(start, end);
  }

  get totalPages() {
    return Math.ceil(this.filteredStudents.length / this.pageSize);
  }

  get startIndex() {
    return (this.currentPage - 1) * this.pageSize + 1;
  }

  get endIndex() {
    return Math.min(this.currentPage * this.pageSize, this.filteredStudents.length);
  }

  get hasPreviousPage() {
    return this.currentPage > 1;
  }

  get hasNextPage() {
    return this.currentPage < this.totalPages;
  }

  get uniqueGrades() {
    const grades = [...new Set(this.model.students?.map(s => s.grade).filter(Boolean))];
    return grades.sort();
  }

  get uniqueSchools() {
    const schools = [...new Set(this.model.students?.map(s => s.school).filter(Boolean))];
    return schools.sort();
  }

  get selectedCount() {
    return this.selectedStudents.size;
  }

  get hasSelections() {
    return this.selectedStudents.size > 0;
  }

  @action
  updateSearch(event) {
    this.searchQuery = event.target.value;
    this.currentPage = 1; // Reset to first page
  }

  @action
  updateGradeFilter(event) {
    this.selectedGrade = event.target.value;
    this.currentPage = 1;
  }

  @action
  updateSchoolFilter(event) {
    this.selectedSchool = event.target.value;
    this.currentPage = 1;
  }

  @action
  updateStatusFilter(event) {
    this.selectedStatus = event.target.value;
    this.currentPage = 1;
  }

  @action
  updateSortBy(event) {
    this.selectedSortBy = event.target.value;
  }

  @action
  toggleSortOrder() {
    this.selectedSortOrder = this.selectedSortOrder === 'asc' ? 'desc' : 'asc';
  }

  @action
  previousPage() {
    if (this.hasPreviousPage) {
      this.currentPage--;
    }
  }

  @action
  nextPage() {
    if (this.hasNextPage) {
      this.currentPage++;
    }
  }

  @action
  goToPage(page) {
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page;
    }
  }

  @action
  toggleStudentSelection(studentId, isSelected) {
    if (isSelected) {
      this.selectedStudents.add(studentId);
    } else {
      this.selectedStudents.delete(studentId);
    }
    this.selectedStudents = new Set(this.selectedStudents); // Trigger reactivity
  }

  @action
  toggleAllSelections() {
    if (this.selectedStudents.size === this.paginatedStudents.length) {
      // Deselect all
      this.selectedStudents.clear();
    } else {
      // Select all visible
      this.paginatedStudents.forEach(student => {
        this.selectedStudents.add(student.id);
      });
    }
    this.selectedStudents = new Set(this.selectedStudents);
  }

  @action
  clearSelections() {
    this.selectedStudents.clear();
    this.selectedStudents = new Set(this.selectedStudents);
  }

  @action
  viewStudent(studentId) {
    this.router.transitionTo('school-transport.students.view', studentId);
  }

  @action
  editStudent(studentId) {
    this.router.transitionTo('school-transport.students.edit', studentId);
  }

  @action
  async deleteStudent(studentId) {
    if (!confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
      return;
    }

    this.isLoading = true;
    try {
      const student = this.store.peekRecord('student', studentId);
      await student.destroyRecord();
      this.notifications.success('Student deleted successfully');
      this.router.refresh();
    } catch (error) {
      console.error('Error deleting student:', error);
      this.notifications.error('Failed to delete student');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async bulkDeleteStudents() {
    if (!confirm(`Are you sure you want to delete ${this.selectedCount} students? This action cannot be undone.`)) {
      return;
    }

    this.isLoading = true;
    try {
      const deletePromises = Array.from(this.selectedStudents).map(studentId => {
        const student = this.store.peekRecord('student', studentId);
        return student.destroyRecord();
      });

      await Promise.all(deletePromises);
      this.notifications.success(`${this.selectedCount} students deleted successfully`);
      this.clearSelections();
      this.router.refresh();
    } catch (error) {
      console.error('Error bulk deleting students:', error);
      this.notifications.error('Failed to delete some students');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async exportStudents() {
    this.isLoading = true;
    try {
      // This would call an export API endpoint
      const response = await fetch('/api/school-transport/students/export', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          filters: {
            search: this.searchQuery,
            grade: this.selectedGrade,
            school: this.selectedSchool,
            status: this.selectedStatus
          },
          format: 'csv'
        })
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'students-export.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Students exported successfully');
      } else {
        throw new Error('Export failed');
      }
    } catch (error) {
      console.error('Error exporting students:', error);
      this.notifications.error('Failed to export students');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async importStudents() {
    // This would open a file picker and handle CSV import
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.csv';
    input.onchange = async (event) => {
      const file = event.target.files[0];
      if (file) {
        this.isLoading = true;
        try {
          const formData = new FormData();
          formData.append('file', file);

          const response = await fetch('/api/school-transport/students/import', {
            method: 'POST',
            body: formData
          });

          if (response.ok) {
            const result = await response.json();
            this.notifications.success(`Successfully imported ${result.imported_count} students`);
            this.router.refresh();
          } else {
            throw new Error('Import failed');
          }
        } catch (error) {
          console.error('Error importing students:', error);
          this.notifications.error('Failed to import students');
        } finally {
          this.isLoading = false;
        }
      }
    };
    input.click();
  }

  @action
  resetFilters() {
    this.searchQuery = '';
    this.selectedGrade = '';
    this.selectedSchool = '';
    this.selectedStatus = '';
    this.selectedSortBy = 'name';
    this.selectedSortOrder = 'asc';
    this.currentPage = 1;
    this.clearSelections();
  }
}