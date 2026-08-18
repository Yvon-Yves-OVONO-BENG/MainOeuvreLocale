import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        if (window.MOLChatOpen) {
            this.element.addEventListener('click', this.open);
        }
    }

    disconnect() {
        this.element.removeEventListener('click', this.open);
    }

    open(event) {
        event.preventDefault();
        window.MOLChatOpen?.();
    }
}
