import { Controller } from '@hotwired/stimulus';
import AccessibleMenu from 'accessible-menu';

export default class extends Controller {
    static targets = ['menu', 'container', 'controller'];

    initialize () {
        this.close = this.close.bind(this);
    }

    connect () {
        this.$menu = new AccessibleMenu.DisclosureMenu({
            menuElement: this.menuTarget,
            containerElement: this.element,
            controllerElement: this.controllerTarget,
        });

        this.$menu.dom.controller.addEventListener('accessibleMenuExpand', () => {
            this.element.classList.add('hover');
        });

        this.$menu.dom.controller.addEventListener('accessibleMenuCollapse', () => {
            this.element.classList.remove('hover');
        });

        this.menuTarget.addEventListener('contextmenu', e => e.stopPropagation());

        document.addEventListener('mousedown', this.close);
    }

    disconnect () {
        document.removeEventListener('mousedown', this.close);
    }

    contextmenu (event) {
        event.preventDefault();

        this.$contextmenu = this.menuTarget.clone();
        this.$contextmenu.classList.add('contextmenu');
        this.$contextmenu.classList.add('show');
        this.$contextmenu.style.top = `${window.scrollY + event.clientY}px`;
        this.$contextmenu.style.left = `${window.scrollX + event.clientX}px`;

        if (this.element.classList.contains('hover-div')) {
            this.element.classList.add('hover');
        }

        document.body.append(this.$contextmenu);

        document.addEventListener('mousedown', (event) => {
            if (!nav.contains(event.target)) {
                this.$contextmenu.remove();
            }
        });
    }

    close (event) {
        if (!this.element.contains(event.target)) {
            this.$menu.elements.controller.close();
        }

        if (this.$contextmenu && !this.$contextmenu.contains(event.target)) {
            this.$contextmenu.remove();
            this.element.classList.remove('hover');
            delete this.$contextmenu;
        }
    }
}
