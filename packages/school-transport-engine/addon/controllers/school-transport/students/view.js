import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class SchoolTransportStudentsViewController extends Controller {
  @service notifications;
  @service router;
  @service store;

  @tracked isLoading = false;
  @tracked showEditModal = false;
  @tracked showDeleteModal = false;
  @tracked showEmergencyContactModal = false;
  @tracked selectedTab = 'overview';

  get student() {
    return this.model.student;
  }

  get assignments() {
    return this.model.assignments || [];
  }

  get attendance() {
    return this.model.attendance || [];
  }

  get communications() {
    return this.model.communications || [];
  }

  get recentAssignments() {
    return this.assignments.slice(0, 5);
  }

  get attendanceSummary() {
    const total = this.attendance.length;
    const present = this.attendance.filter(a => a.status === 'present').length;
    const absent = this.attendance.filter(a => a.status === 'absent').length;
    const late = this.attendance.filter(a => a.status === 'late').length;

    return {
      total,
      present,
      absent,
      late,
      presentPercentage: total > 0 ? Math.round((present / total) * 100) : 0,
      absentPercentage: total > 0 ? Math.round((absent / total) * 100) : 0,
      latePercentage: total > 0 ? Math.round((late / total) * 100) : 0
    };
  }

  get activeAssignments() {
    return this.assignments.filter(assignment => assignment.status === 'active');
  }

  get inactiveAssignments() {
    return this.assignments.filter(assignment => assignment.status !== 'active');
  }

  get recentCommunications() {
    return this.communications.slice(0, 10);
  }

  get emergencyContacts() {
    const contacts = [];

    if (this.student.parent_first_name && this.student.parent_phone) {
      contacts.push({
        name: `${this.student.parent_first_name} ${this.student.parent_last_name || ''}`.trim(),
        relationship: this.student.parent_relationship || 'Parent',
        phone: this.student.parent_phone,
        email: this.student.parent_email,
        is_primary: true
      });
    }

    if (this.student.emergency_contact_name && this.student.emergency_contact_phone) {
      contacts.push({
        name: this.student.emergency_contact_name,
        relationship: this.student.emergency_contact_relationship || 'Emergency Contact',
        phone: this.student.emergency_contact_phone,
        email: null,
        is_primary: false
      });
    }

    return contacts;
  }

  @action
  selectTab(tab) {
    this.selectedTab = tab;
  }

  @action
  editStudent() {
    this.router.transitionTo('school-transport.students.edit', this.student.id);
  }

  @action
  async deleteStudent() {
    if (!confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
      return;
    }

    this.isLoading = true;
    try {
      await this.student.destroyRecord();
      this.notifications.success('Student deleted successfully');
      this.router.transitionTo('school-transport.students.index');
    } catch (error) {
      console.error('Error deleting student:', error);
      this.notifications.error('Failed to delete student');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async updateStudentStatus(newStatus) {
    this.isLoading = true;
    try {
      this.student.status = newStatus;
      await this.student.save();
      this.notifications.success(`Student status updated to ${newStatus}`);
    } catch (error) {
      console.error('Error updating student status:', error);
      this.notifications.error('Failed to update student status');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async createAssignment() {
    this.router.transitionTo('school-transport.assignments.new', {
      queryParams: { student_id: this.student.id }
    });
  }

  @action
  viewAssignment(assignmentId) {
    this.router.transitionTo('school-transport.assignments.view', assignmentId);
  }

  @action
  async sendMessage() {
    this.router.transitionTo('school-transport.communications.new', {
      queryParams: {
        recipient_type: 'student',
        recipient_id: this.student.id
      }
    });
  }

  @action
  async callParent(phoneNumber) {
    if (phoneNumber) {
      window.open(`tel:${phoneNumber}`);
    }
  }

  @action
  async emailParent(email) {
    if (email) {
      window.open(`mailto:${email}`);
    }
  }

  @action
  async exportStudentData() {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/students/${this.student.id}/export`, {
        method: 'GET'
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.student.full_name}-data.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Student data exported successfully');
      } else {
        throw new Error('Export failed');
      }
    } catch (error) {
      console.error('Error exporting student data:', error);
      this.notifications.error('Failed to export student data');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async generateReportCard() {
    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/students/${this.student.id}/report-card`, {
        method: 'GET'
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.student.full_name}-report-card.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.notifications.success('Report card generated successfully');
      } else {
        throw new Error('Report generation failed');
      }
    } catch (error) {
      console.error('Error generating report card:', error);
      this.notifications.error('Failed to generate report card');
    } finally {
      this.isLoading = false;
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
  async addNote(note) {
    if (!note?.trim()) return;

    this.isLoading = true;
    try {
      const response = await fetch(`/api/school-transport/students/${this.student.id}/notes`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ note })
      });

      if (response.ok) {
        const result = await response.json();
        // Refresh the model to get updated notes
        await this.router.refresh();
        this.notifications.success('Note added successfully');
      } else {
        throw new Error('Failed to add note');
      }
    } catch (error) {
      console.error('Error adding note:', error);
      this.notifications.error('Failed to add note');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async updateMedicalInfo(medicalData) {
    this.isLoading = true;
    try {
      this.student.medical_conditions = medicalData.conditions;
      this.student.allergies = medicalData.allergies;
      this.student.medications = medicalData.medications;
      this.student.doctor_name = medicalData.doctorName;
      this.student.doctor_phone = medicalData.doctorPhone;
      this.student.insurance_provider = medicalData.insuranceProvider;
      this.student.insurance_policy_number = medicalData.policyNumber;

      await this.student.save();
      this.notifications.success('Medical information updated successfully');
    } catch (error) {
      console.error('Error updating medical info:', error);
      this.notifications.error('Failed to update medical information');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async updateEmergencyContact(contactData) {
    this.isLoading = true;
    try {
      this.student.emergency_contact_name = contactData.name;
      this.student.emergency_contact_phone = contactData.phone;
      this.student.emergency_contact_relationship = contactData.relationship;

      await this.student.save();
      this.notifications.success('Emergency contact updated successfully');
    } catch (error) {
      console.error('Error updating emergency contact:', error);
      this.notifications.error('Failed to update emergency contact');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  async deactivateStudent() {
    if (!confirm('Are you sure you want to deactivate this student? They will no longer be eligible for transportation.')) {
      return;
    }

    await this.updateStudentStatus('inactive');
  }

  @action
  async reactivateStudent() {
    await this.updateStudentStatus('active');
  }

  @action
  showEmergencyContacts() {
    this.showEmergencyContactModal = true;
  }

  @action
  closeEmergencyContactModal() {
    this.showEmergencyContactModal = false;
  }
}