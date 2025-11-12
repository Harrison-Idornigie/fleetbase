import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class ReportGeneratorComponent extends Component {
  @tracked reportTypes = [
    {
      value: 'attendance',
      label: 'Attendance Report',
      description: 'Student attendance and absence tracking',
      icon: 'calendar-check'
    },
    {
      value: 'route_efficiency',
      label: 'Route Efficiency Report',
      description: 'Route performance and optimization metrics',
      icon: 'route'
    },
    {
      value: 'student_transportation',
      label: 'Student Transportation Report',
      description: 'Complete student transportation details',
      icon: 'users'
    },
    {
      value: 'driver_performance',
      label: 'Driver Performance Report',
      description: 'Driver metrics and performance data',
      icon: 'user-tie'
    },
    {
      value: 'vehicle_maintenance',
      label: 'Vehicle Maintenance Report',
      description: 'Vehicle inspection and maintenance records',
      icon: 'tools'
    },
    {
      value: 'safety_compliance',
      label: 'Safety & Compliance Report',
      description: 'Safety incidents and compliance status',
      icon: 'shield-alt'
    },
    {
      value: 'incident_summary',
      label: 'Incident Summary Report',
      description: 'Summary of all reported incidents',
      icon: 'exclamation-triangle'
    },
    {
      value: 'cost_analysis',
      label: 'Cost Analysis Report',
      description: 'Transportation cost breakdown and analysis',
      icon: 'dollar-sign'
    }
  ];

  @tracked formatOptions = [
    { value: 'pdf', label: 'PDF', icon: 'file-pdf' },
    { value: 'excel', label: 'Excel', icon: 'file-excel' },
    { value: 'csv', label: 'CSV', icon: 'file-csv' },
    { value: 'html', label: 'HTML', icon: 'file-code' }
  ];

  @tracked frequencyOptions = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'biweekly', label: 'Bi-weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' }
  ];

  @tracked gradeOptions = ['K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

  get showSchoolFilter() {
    const types = ['attendance', 'student_transportation', 'route_efficiency'];
    return types.includes(this.args.data?.reportType);
  }

  get showRouteFilter() {
    const types = ['route_efficiency', 'attendance', 'driver_performance'];
    return types.includes(this.args.data?.reportType);
  }

  get showGradeFilter() {
    const types = ['attendance', 'student_transportation'];
    return types.includes(this.args.data?.reportType);
  }

  get showDriverFilter() {
    const types = ['driver_performance', 'route_efficiency', 'safety_compliance'];
    return types.includes(this.args.data?.reportType);
  }

  @action
  handleSubmit(event) {
    event.preventDefault();
    if (this.args.onSubmit) {
      this.args.onSubmit();
    }
  }
}
