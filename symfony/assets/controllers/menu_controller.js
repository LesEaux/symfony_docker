import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'backdrop', 'toggle'];

    connect() {
        this.media = window.matchMedia('(min-width: 992px)');
        this.onResize = () => { this.close(); this.syncAccessibility(); };
        this.media.addEventListener('change', this.onResize);
        this.syncAccessibility();
    }

    disconnect() {
        this.media.removeEventListener('change', this.onResize);
        document.body.classList.remove('menu-open');
    }

    syncAccessibility() {
        this.panelTarget.inert = !this.media.matches && !this.panelTarget.classList.contains('is-open');
    }

    open() {
        this.panelTarget.classList.add('is-open');
        this.panelTarget.inert = false;
        this.backdropTarget.hidden = false;
        this.toggleTarget.setAttribute('aria-expanded', 'true');
        document.body.classList.add('menu-open');
        this.panelTarget.querySelector('button').focus();
        this.boundTrap = (event) => this.trapFocus(event);
        this.panelTarget.addEventListener('keydown', this.boundTrap);
    }

    close() {
        const wasOpen = this.panelTarget.classList.contains('is-open');
        this.panelTarget.classList.remove('is-open');
        this.backdropTarget.hidden = true;
        this.toggleTarget.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('menu-open');
        this.panelTarget.removeEventListener('keydown', this.boundTrap);
        this.syncAccessibility();
        if (wasOpen && !this.media.matches) this.toggleTarget.focus();
    }

    trapFocus(event) {
        if (event.key !== 'Tab') return;
        const items = [...this.panelTarget.querySelectorAll('a, button')].filter(el => el.offsetParent !== null);
        const first = items[0];
        const last = items.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
}
