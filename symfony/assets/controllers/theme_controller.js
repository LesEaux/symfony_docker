import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'input', 'icon', 'label', 'status'];
    static values = { current: String };

    connect() {
        this.apply(this.currentValue);
    }

    apply(theme) {
        if (!['dark', 'light'].includes(theme)) return;
        this.currentValue = theme;
        document.documentElement.dataset.bsTheme = theme;
        document.querySelector('meta[name="color-scheme"]')?.setAttribute('content', theme);
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'dark' ? '#101d30' : '#ffffff');
        for (const input of this.inputTargets) input.value = theme === 'dark' ? 'light' : 'dark';
        for (const button of this.buttonTargets) button.setAttribute('aria-checked', String(theme === 'dark'));
        for (const icon of this.iconTargets) icon.className = `bi ${theme === 'dark' ? 'bi-moon-stars' : 'bi-sun'}`;
        for (const label of this.labelTargets) label.textContent = theme === 'dark' ? 'Sombre' : 'Clair';
    }

    async toggle(event) {
        event.preventDefault();
        if (this.saving) return;
        this.saving = true;
        for (const button of this.buttonTargets) button.disabled = true;
        try {
            const form = event.currentTarget;
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
            });
            if (!response.ok) throw new Error('Save failed');
            const data = await response.json();
            if (!['dark', 'light'].includes(data.theme)) throw new Error('Invalid theme');
            this.apply(data.theme);
            this.announce(`Thème ${data.theme === 'dark' ? 'sombre' : 'clair'} enregistré.`);
        } catch {
            this.announce('Impossible d’enregistrer le thème. Réessayez ou rechargez la page.');
        } finally {
            for (const button of this.buttonTargets) button.disabled = false;
            this.saving = false;
        }
    }

    announce(message) {
        for (const status of this.statusTargets) status.textContent = message;
    }
}
