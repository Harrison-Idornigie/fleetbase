import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class SafetyComplianceDashboardComponent extends Component {
  @tracked activeTab = 'certifications';

  @action
  setActiveTab(tab) {
    this.activeTab = tab;
  }
}
