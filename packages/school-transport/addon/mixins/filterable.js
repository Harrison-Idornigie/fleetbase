import Mixin from '@ember/object/mixin';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';

/**
 * Mixin for controllers with filtering support
 */
export default Mixin.create({
  @tracked filters: {},
  @tracked searchQuery: '',

  /**
   * Apply filters to data
   */
  applyFilters(data) {
    let filtered = data;

    // Apply search query
    if (this.searchQuery && this.searchQuery.trim()) {
      const query = this.searchQuery.toLowerCase();
      filtered = filtered.filter(item => 
        this.searchInItem(item, query)
      );
    }

    // Apply other filters
    Object.keys(this.filters).forEach(key => {
      const value = this.filters[key];
      if (value !== null && value !== undefined && value !== '') {
        filtered = filtered.filter(item => {
          const itemValue = this.getNestedProperty(item, key);
          return this.matchFilter(itemValue, value);
        });
      }
    });

    return filtered;
  },

  /**
   * Search in item properties
   */
  searchInItem(item, query) {
    const searchableFields = this.getSearchableFields();
    
    return searchableFields.some(field => {
      const value = this.getNestedProperty(item, field);
      return value && value.toString().toLowerCase().includes(query);
    });
  },

  /**
   * Get nested property from object
   */
  getNestedProperty(obj, path) {
    return path.split('.').reduce((current, prop) => 
      current ? current[prop] : undefined, obj
    );
  },

  /**
   * Match filter value
   */
  matchFilter(itemValue, filterValue) {
    if (Array.isArray(filterValue)) {
      return filterValue.includes(itemValue);
    }
    return itemValue === filterValue;
  },

  /**
   * Override this method to specify searchable fields
   */
  getSearchableFields() {
    return ['name', 'id'];
  },

  @action
  updateFilter(key, value) {
    this.filters = {
      ...this.filters,
      [key]: value
    };
    this.onFilterChange();
  },

  @action
  updateSearch(event) {
    this.searchQuery = event.target.value;
    this.onFilterChange();
  },

  @action
  clearFilters() {
    this.filters = {};
    this.searchQuery = '';
    this.onFilterChange();
  },

  @action
  removeFilter(key) {
    const newFilters = { ...this.filters };
    delete newFilters[key];
    this.filters = newFilters;
    this.onFilterChange();
  },

  /**
   * Get active filter count
   */
  get activeFilterCount() {
    let count = Object.values(this.filters).filter(v => 
      v !== null && v !== undefined && v !== ''
    ).length;
    
    if (this.searchQuery && this.searchQuery.trim()) {
      count++;
    }
    
    return count;
  },

  /**
   * Check if has active filters
   */
  get hasActiveFilters() {
    return this.activeFilterCount > 0;
  },

  /**
   * Override this method to handle filter changes
   */
  onFilterChange() {
    // Implement in controller
  }
});
