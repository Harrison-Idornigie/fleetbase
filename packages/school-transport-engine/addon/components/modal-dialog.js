import Component from '@glimmer/component';
import { action } from '@ember/object';

export default class ModalDialogComponent extends Component {
  @action
  handleOverlayClick() {
    if (this.args.closeOnOverlayClick && this.args.onClose) {
      this.args.onClose();
    }
  }

  @action
  stopPropagation(event) {
    event.stopPropagation();
  }
}
