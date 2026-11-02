/**
 * The site chrome around the stage: the light and dark theme switch, the tabs
 * of a division panel, and the home page's question box, which hands its text
 * to the advisor chat.
 */
const THEME_KEY = 'yoshida-theme';

function initThemeToggle() {
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const paint = () => {
            const dark = document.documentElement.dataset.theme === 'dark';
            button.setAttribute('aria-pressed', String(dark));
            button.setAttribute('aria-label', dark ? button.dataset.labelLight : button.dataset.labelDark);
        };

        button.addEventListener('click', () => {
            const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;
            try {
                localStorage.setItem(THEME_KEY, next);
            } catch { /* the choice just lasts for this page */ }
            paint();
        });

        paint();
    });
}

function initTabs() {
    document.querySelectorAll('[data-site-tabs]').forEach((root) => {
        const tabs = [...root.querySelectorAll('[role="tab"]')];
        const panes = [...root.querySelectorAll('[role="tabpanel"]')];

        tabs.forEach((tab, index) => tab.addEventListener('click', () => {
            tabs.forEach((other, position) => other.setAttribute('aria-selected', String(position === index)));
            panes.forEach((pane, position) => { pane.hidden = position !== index; });
        }));
    });
}

function initAskBox() {
    document.querySelectorAll('[data-site-ask]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const input = form.querySelector('input');
            const message = input.value.trim();
            const launcher = document.querySelector('[data-chat-launcher]');
            if (!message || !launcher) return;

            input.value = '';
            launcher.click();
            window.dispatchEvent(new CustomEvent('advisor:ask', { detail: { message } }));
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initTabs();
    initAskBox();
});
