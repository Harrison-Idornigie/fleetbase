import Mixin from '@ember/object/mixin';
import { inject as service } from '@ember/service';

/**
 * Mixin for routes that need to load common school transport data
 */
export default Mixin.create({
  @service store;
  @service notifications;

  /**
   * Load schools for dropdown/filter
   */
  async loadSchools() {
    try {
      return await this.store.findAll('school');
    } catch (error) {
      console.error('Error loading schools:', error);
      this.notifications.error('Failed to load schools');
      return [];
    }
  },

  /**
   * Load vehicles for assignment
   */
  async loadVehicles() {
    try {
      return await this.store.query('vehicle', { 
        filter: { is_active: true } 
      });
    } catch (error) {
      console.error('Error loading vehicles:', error);
      this.notifications.error('Failed to load vehicles');
      return [];
    }
  },

  /**
   * Load drivers for assignment
   */
  async loadDrivers() {
    try {
      return await this.store.query('driver', { 
        filter: { is_active: true, is_certified: true } 
      });
    } catch (error) {
      console.error('Error loading drivers:', error);
      this.notifications.error('Failed to load drivers');
      return [];
    }
  },

  /**
   * Load students with optional filters
   */
  async loadStudents(filters = {}) {
    try {
      return await this.store.query('student', { filter: filters });
    } catch (error) {
      console.error('Error loading students:', error);
      this.notifications.error('Failed to load students');
      return [];
    }
  },

  /**
   * Load routes with optional filters
   */
  async loadRoutes(filters = {}) {
    try {
      return await this.store.query('school-route', { filter: filters });
    } catch (error) {
      console.error('Error loading routes:', error);
      this.notifications.error('Failed to load routes');
      return [];
    }
  }
});
