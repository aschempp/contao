import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

export default class extends Controller {
    static targets = ['primary', 'secondary'];

    static afterLoad (identifier, application) {
        const setupController = () => {
            document.querySelectorAll('.click2edit').forEach((el) => {
                el.classList.remove('click2edit');

                const primary = el.querySelector('a.edit');
                const secondary = el.querySelector('a.children');

                if (primary) {
                    primary.setAttribute(`data-${identifier}-target`, "primary");
                }

                if (secondary) {
                    secondary.setAttribute(`data-${identifier}-target`, "secondary");
                }

                el.dataset.controller = `${el.dataset.controller || ''} ${identifier}`;
            });
        };

        document.addEventListener('DOMContentLoaded', setupController);
        document.addEventListener('ajax_change', setupController);
        document.addEventListener('turbo:render', setupController);
        document.addEventListener('turbo:frame-render', setupController);
        setupController();

        Theme.setupCtrlClick = () => {
            console.warn('Using Theme.setupCtrlClick() is deprecated and will be removed in Contao 6. Apply the Stimulus actions instead.');
            setupController();
        }
    }

    initialize () {
        this.visitPrimary = this.visitPrimary.bind(this);
        this.visitSecondary = this.visitSecondary.bind(this);
    }

    connect () {
        this.element.addEventListener('click', this.visitPrimary);
        this.element.addEventListener('click', this.visitSecondary);
    }

    disconnect () {
        this.element.removeEventListener('click', this.visitPrimary);
        this.element.removeEventListener('click', this.visitSecondary);
    }

    visitPrimary (event) {
        const key = window.navigator.platform?.startsWith('Mac') ? 'metaKey' : 'ctrlKey';
        if (this.hasPrimaryTarget && this.primaryTarget.href && event[key] && this.isValid(event.target)) {
            Turbo.visit(this.primaryTarget.href);
        }
    }

    visitSecondary (event) {
        if (this.hasSecondaryTarget && this.secondaryTarget.href && event.shiftKey && this.isValid(event.target)) {
            Turbo.visit(this.secondaryTarget.href);
        }
    }

    isValid (element) {
        return element.tagName !== 'a' && !element.closest('a');
    }
}
