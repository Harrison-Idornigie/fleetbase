import Mixin from '@ember/object/mixin';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

/**
 * Mixin for controllers with pagination support
 */
export default Mixin.create({
  @service store;

  // Pagination properties
  currentPage: 1,
  pageSize: 25,
  totalItems: 0,
  sortBy: 'created_at',
  sortOrder: 'desc',

  /**
   * Calculate total pages
   */
  get totalPages() {
    return Math.ceil(this.totalItems / this.pageSize);
  },

  /**
   * Check if has previous page
   */
  get hasPreviousPage() {
    return this.currentPage > 1;
  },

  /**
   * Check if has next page
   */
  get hasNextPage() {
    return this.currentPage < this.totalPages;
  },

  /**
   * Get start index for display
   */
  get startIndex() {
    return (this.currentPage - 1) * this.pageSize + 1;
  },

  /**
   * Get end index for display
   */
  get endIndex() {
    return Math.min(this.currentPage * this.pageSize, this.totalItems);
  },

  /**
   * Get pagination info text
   */
  get paginationInfo() {
    return `Showing ${this.startIndex} to ${this.endIndex} of ${this.totalItems} items`;
  },

  /**
   * Build query parameters for pagination
   */
  buildQueryParams() {
    return {
      page: {
        number: this.currentPage,
        size: this.pageSize
      },
      sort: this.sortOrder === 'desc' ? `-${this.sortBy}` : this.sortBy
    };
  },

  @action
  goToPage(page) {
    if (page >= 1 && page <= this.totalPages) {
      this.set('currentPage', page);
      this.loadData();
    }
  },

  @action
  nextPage() {
    if (this.hasNextPage) {
      this.set('currentPage', this.currentPage + 1);
      this.loadData();
    }
  },

  @action
  previousPage() {
    if (this.hasPreviousPage) {
      this.set('currentPage', this.currentPage - 1);
      this.loadData();
    }
  },

  @action
  changePageSize(size) {
    this.set('pageSize', size);
    this.set('currentPage', 1);
    this.loadData();
  },

  @action
  changeSort(field) {
    if (this.sortBy === field) {
      this.set('sortOrder', this.sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      this.set('sortBy', field);
      this.set('sortOrder', 'asc');
    }
    this.set('currentPage', 1);
    this.loadData();
  },

  /**
   * Override this method in your controller
   */
  loadData() {
    throw new Error('loadData() must be implemented in the controller');
  }
});
