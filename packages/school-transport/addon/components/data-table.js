import Component from '@glimmer/component';
import { action } from '@ember/object';

export default class DataTableComponent extends Component {
  @action
  stopPropagation(event) {
    event.stopPropagation();
  }

  @action
  includes(array, item) {
    return array && array.includes(item);
  }
}
