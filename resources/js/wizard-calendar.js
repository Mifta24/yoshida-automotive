/**
 * The reservation wizard's dates: the same calendar the advisor shows in the
 * chat, in place of the browser's own date fields. The two dates stay in the
 * form as hidden inputs, so the wizard, its draft and the server see no change.
 */
const DAY_MS = 86400000;

const toIso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const fromIso = (iso) => new Date(`${iso}T00:00:00`);
const addDays = (iso, days) => toIso(new Date(fromIso(iso).getTime() + days * DAY_MS));
const daysBetween = (from, to) => Math.round((fromIso(to) - fromIso(from)) / DAY_MS) + 1;

function h(tag, props = {}, ...children) {
    const el = document.createElement(tag);

    for (const [key, value] of Object.entries(props)) {
        if (value == null || value === false) continue;

        if (key === 'class') el.className = value;
        else if (key === 'text') el.textContent = value;
        else if (key.startsWith('on')) el.addEventListener(key.slice(2).toLowerCase(), value);
        else if (key in el) el[key] = value;
        else el.setAttribute(key, value === true ? '' : value);
    }

    el.append(...children.flat().filter((child) => child != null && child !== false));

    return el;
}

function initWizardCalendar() {
    const root = document.querySelector('[data-wizard]');
    const host = root?.querySelector('[data-calendar-host]');
    if (!host) return;

    const form = root.querySelector('[data-wizard-form]');
    const startInput = form.elements.drop_off_date;
    const endInput = form.elements.pickup_date;
    const labels = JSON.parse(root.querySelector('[data-wizard-labels]').textContent);
    const locale = root.dataset.locale;
    const today = root.dataset.today;
    const maxDays = Number(root.dataset.maxDays) || 30;
    const numberLocale = locale === 'ja' ? 'ja-JP' : locale === 'en' ? 'en-US' : 'id-ID';
    const mondayFirst = locale !== 'en';

    const monthName = (date) => date.toLocaleDateString(numberLocale, { month: 'long', year: 'numeric' });
    const dayName = (index) => new Date(2023, 0, 1 + index + (mondayFirst ? 1 : 0)).toLocaleDateString(numberLocale, { weekday: 'short' });
    const longDay = (iso) => fromIso(iso).toLocaleDateString(numberLocale, { weekday: 'short', day: 'numeric', month: 'short' });

    const state = { start: '', end: '', view: fromIso(today) };
    state.view.setDate(1);

    const title = h('p', { class: 'sp-title', text: `${labels.drop_off_date} → ${labels.pickup_date}` });
    const hint = h('p', { class: 'sp-hint', 'aria-live': 'polite' });
    const months = h('div', { class: 'sp-months' });
    const prev = h('button', { type: 'button', class: 'sp-nav', 'aria-label': '‹', text: '‹' });
    const next = h('button', { type: 'button', class: 'sp-nav', 'aria-label': '›', text: '›' });

    function commit() {
        startInput.value = state.start;
        endInput.value = state.end || '';
        form.dispatchEvent(new Event('input', { bubbles: true }));
        paint();
    }

    function pick(iso) {
        if (!state.start || state.end || iso < state.start) {
            state.start = iso;
            state.end = '';
        } else {
            state.end = iso;
        }

        commit();
    }

    function month(first) {
        const year = first.getFullYear();
        const index = first.getMonth();
        const offset = (first.getDay() + (mondayFirst ? 6 : 0)) % 7;
        const total = new Date(year, index + 1, 0).getDate();
        const cells = [];

        for (let i = 0; i < offset; i++) cells.push(h('span', { class: 'sp-blank', 'aria-hidden': 'true' }));

        for (let day = 1; day <= total; day++) {
            const iso = toIso(new Date(year, index, day));
            const tooFar = state.start && !state.end && iso > addDays(state.start, maxDays - 1);
            const classes = ['sp-day'];

            if (iso === state.start) classes.push('is-start');
            if (iso === (state.end || '')) classes.push('is-end');
            if (state.start && state.end && iso > state.start && iso < state.end) classes.push('is-between');
            if (iso === today) classes.push('is-today');

            cells.push(h('button', {
                type: 'button',
                class: classes.join(' '),
                disabled: iso < today || tooFar,
                'aria-pressed': String(iso === state.start || iso === state.end),
                'aria-label': longDay(iso),
                text: String(day),
                onClick: () => pick(iso),
            }));
        }

        return h('div', { class: 'sp-month' },
            h('p', { class: 'sp-month-name', text: monthName(first) }),
            h('div', { class: 'sp-grid' }, [...Array(7).keys()].map((i) => h('span', { class: 'sp-weekday', 'aria-hidden': 'true', text: dayName(i) })), cells));
    }

    function paint() {
        const second = new Date(state.view);
        second.setMonth(second.getMonth() + 1);
        months.replaceChildren(month(state.view), month(second));

        const thisMonth = fromIso(today);
        thisMonth.setDate(1);
        prev.disabled = state.view <= thisMonth;

        if (state.start) {
            const end = state.end || state.start;
            hint.textContent = `${longDay(state.start)} → ${longDay(end)} · ${daysBetween(state.start, end)} ${labels.days}`;
        } else {
            hint.textContent = labels.drop_off_date;
        }
    }

    function sync() {
        state.start = startInput.value && startInput.value >= today ? startInput.value : '';
        state.end = state.start && endInput.value && endInput.value >= state.start ? endInput.value : '';
        state.view = fromIso(state.start || today);
        state.view.setDate(1);
        paint();
    }

    prev.addEventListener('click', () => { state.view.setMonth(state.view.getMonth() - 1); paint(); });
    next.addEventListener('click', () => { state.view.setMonth(state.view.getMonth() + 1); paint(); });
    form.addEventListener('reset', () => setTimeout(sync));

    host.append(h('section', { class: 'sp', 'aria-label': labels.dates || title.textContent },
        h('header', { class: 'sp-head' }, prev, title, next), months, hint));

    sync();
}

document.addEventListener('DOMContentLoaded', initWizardCalendar);
