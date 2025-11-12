import ApplicationAdapter from './application';

export default class StudentAdapter extends ApplicationAdapter {
  /**
   * Custom URL for student import
   */
  urlForImport() {
    return `${this.buildURL()}/students/import`;
  }

  /**
   * Custom URL for student export
   */
  urlForExport(query) {
    const url = `${this.buildURL()}/students/export`;
    const params = new URLSearchParams(query);
    return `${url}?${params.toString()}`;
  }

  /**
   * Custom URL for bulk operations
   */
  urlForBulkDelete(ids) {
    return `${this.buildURL()}/students/bulk-delete`;
  }

  /**
   * Import students from file
   */
  async importStudents(file) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(this.urlForImport(), {
      method: 'POST',
      headers: {
        'Authorization': this.headers['Authorization']
      },
      body: formData
    });

    if (!response.ok) {
      throw new Error('Import failed');
    }

    return await response.json();
  }

  /**
   * Export students
   */
  async exportStudents(query = {}) {
    const response = await fetch(this.urlForExport(query), {
      method: 'GET',
      headers: this.headers
    });

    if (!response.ok) {
      throw new Error('Export failed');
    }

    return await response.blob();
  }

  /**
   * Bulk delete students
   */
  async bulkDelete(ids) {
    const response = await fetch(this.urlForBulkDelete(), {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify({ ids })
    });

    if (!response.ok) {
      throw new Error('Bulk delete failed');
    }

    return await response.json();
  }
}
