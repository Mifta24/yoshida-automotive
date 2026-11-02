/**
 * The visit picker: a calendar and a vehicle counter the advisor puts on screen
 * when it needs dates, so the customer taps instead of typing.
 * The choice goes back to the advisor as the customer's next message, in the
 * customer's own language with the dates in plain ISO form (2026-10-23) so
 * nothing is lost in translation.
 */
import { haptic, PRESS, TAP } from './haptics';

const DAY_MS = 86400000;

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

const toIso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const fromIso = (iso) => new Date(`${iso}T00:00:00`);
const daysBetween = (from, to) => Math.round((fromIso(to) - fromIso(from)) / DAY_MS) + 1;
const addDays = (iso, days) => toIso(new Date(fromIso(iso).getTime() + days * DAY_MS));

/**
 * @param {object} ctx
 * @param {object} ctx.config     the chat's config (locale, labels, today, maxDays)
 * @param {object} ctx.wizard     the reservation wizard's labels
 * @param {(message: string) => void} ctx.submit   sends the customer's choice as their message
 */
export function createPicker({ config, wizard, submit }) {
    const { labels } = config;
    const numberLocale = config.locale === 'ja' ? 'ja-JP' : config.locale === 'en' ? 'en-US' : 'id-ID';
    const mondayFirst = config.locale !== 'en';

    const monthName = (date) => date.toLocaleDateString(numberLocale, { month: 'long', year: 'numeric' });
    const dayName = (index) => new Date(2023, 0, 1 + index + (mondayFirst ? 1 : 0)).toLocaleDateString(numberLocale, { weekday: 'short' });
    const longDay = (iso) => fromIso(iso).toLocaleDateString(numberLocale, { weekday: 'short', day: 'numeric', month: 'short' });

    /**
     * @param {{service?: object|null, values?: object}} options
     * @returns {Element}
     */
    function build({ service = null, values = {} } = {}) {
        const state = {
            start: values.drop_off_date && values.drop_off_date >= config.today ? values.drop_off_date : '',
            end: values.pickup_date && values.pickup_date >= (values.drop_off_date || '') ? values.pickup_date : '',
            vehicles: Math.min(Math.max(Number(values.vehicles) || 1, 1), 10),
            view: fromIso(values.drop_off_date && values.drop_off_date >= config.today ? values.drop_off_date : config.today),
        };
        state.view.setDate(1);

        const months = h('div', { class: 'sp-months' });
        const title = h('p', { class: 'sp-title', text: service ? service.name : labels.pickerTitle });
        const hint = h('p', { class: 'sp-hint', 'aria-live': 'polite' });
        const go = h('button', { type: 'button', class: 'rc-btn rc-btn-primary rc-btn-wide', text: labels.pickerContinue, disabled: true });
        const prev = h('button', { type: 'button', class: 'sp-nav', 'aria-label': '‹', text: '‹' });
        const next = h('button', { type: 'button', class: 'sp-nav', 'aria-label': '›', text: '›' });

        const lastStart = () => addDays(state.start, config.maxDays - 1);

        function pick(iso) {
            haptic(TAP);
            if (!state.start || state.end || iso < state.start) {
                state.start = iso;
                state.end = '';
            } else {
                state.end = iso;
            }

            paint();
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
                const past = iso < config.today;
                const tooFar = state.start && !state.end && iso > lastStart();
                const classes = ['sp-day'];

                if (iso === state.start) classes.push('is-start');
                if (iso === state.end) classes.push('is-end');
                if (state.start && state.end && iso > state.start && iso < state.end) classes.push('is-between');
                if (iso === config.today) classes.push('is-today');

                cells.push(h('button', {
                    type: 'button',
                    class: classes.join(' '),
                    disabled: past || tooFar,
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

            const thisMonth = fromIso(config.today);
            thisMonth.setDate(1);
            prev.disabled = state.view <= thisMonth;

            if (state.start && state.end) {
                const days = daysBetween(state.start, state.end);
                hint.textContent = `${longDay(state.start)} → ${longDay(state.end)} · ${days} ${labels.day}`;
            } else {
                hint.textContent = state.start ? labels.pickEnd : labels.pickStart;
            }

            go.disabled = !(state.start && state.end);
        }

        function stepper(name, label, min, max) {
            const value = h('output', { class: 'rf-step-value', text: String(state[name]) });
            const set = (to) => {
                haptic(TAP);
                state[name] = Math.min(Math.max(to, min), max);
                value.textContent = String(state[name]);
                minus.disabled = state[name] <= min;
                plus.disabled = state[name] >= max;
            };
            const minus = h('button', { type: 'button', class: 'rf-step-btn', 'aria-label': `− ${label}`, text: '−', onClick: () => set(state[name] - 1) });
            const plus = h('button', { type: 'button', class: 'rf-step-btn', 'aria-label': `+ ${label}`, text: '+', onClick: () => set(state[name] + 1) });
            minus.disabled = state[name] <= min;
            plus.disabled = state[name] >= max;

            return h('div', { class: 'rf-stepper' }, h('span', { class: 'rf-label', text: label }), h('div', {}, minus, value, plus));
        }

        prev.addEventListener('click', () => { state.view.setMonth(state.view.getMonth() - 1); paint(); });
        next.addEventListener('click', () => { state.view.setMonth(state.view.getMonth() + 1); paint(); });

        go.addEventListener('click', () => {
            haptic(PRESS);
            const days = daysBetween(state.start, state.end);

            submit(`${labels.pickerMessage} ${service ? `(${service.name}) ` : ''}${state.start} → ${state.end} (${days} ${labels.day}), ${wizard.vehicles}: ${state.vehicles}`);
        });

        paint();

        return h('section', { class: 'sp', 'aria-label': labels.pickerTitle },
            h('header', { class: 'sp-head' }, prev, title, next),
            months,
            hint,
            h('div', { class: 'sp-foot' }, stepper('vehicles', wizard.vehicles, 1, 10), go));
    }

    return { build };
}
