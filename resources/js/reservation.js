/**
 * Guided reservation wizard: five short steps in its own scene — dates,
 * vehicle, service, contact, summary — then a reference number and
 * WhatsApp / phone / email hand-over. Progress survives leaving the page and
 * switching language (sessionStorage), and every rule is enforced again on the
 * server, which is the only source of price and availability.
 */
function initReservationWizard() {
    const root = document.querySelector('[data-wizard]');
    if (!root) return;

    const labels = JSON.parse(root.querySelector('[data-wizard-labels]').textContent);
    const form = root.querySelector('[data-wizard-form]');
    const flow = root.querySelector('[data-wizard-flow]');
    const done = root.querySelector('[data-wizard-done]');
    const steps = [...root.querySelectorAll('[data-step]')];
    const progress = [...root.querySelectorAll('[data-progress-step]')];
    const stepLabel = root.querySelector('[data-wizard-step-label]');
    const daysHint = root.querySelector('[data-days-hint]');
    const backButton = root.querySelector('[data-wizard-back]');
    const nextButton = root.querySelector('[data-wizard-next]');
    const submitButton = root.querySelector('[data-wizard-submit]');
    const errorBox = root.querySelector('[data-wizard-error]');
    const errorText = root.querySelector('[data-wizard-error-text]');
    const alternativesBox = root.querySelector('[data-wizard-alternatives]');
    const serviceOptions = [...root.querySelectorAll('[data-service-option]')];
    const towingField = root.querySelector('[data-towing-field]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const config = {
        quoteUrl: root.dataset.quoteUrl,
        submitUrl: root.dataset.submitUrl,
        locale: root.dataset.locale,
        currency: root.dataset.currency,
        today: root.dataset.today,
        maxDays: Number(root.dataset.maxDays),
        draftKey: `reservation_draft_${root.dataset.workshopSlug}`,
        tokenKey: `advisor_token_${root.dataset.workshopSlug}`,
    };

    const fieldStep = {
        drop_off_date: 1, pickup_date: 1, vehicle_model: 2, license_plate: 2, vehicles: 2, service_type_slug: 3, towing: 3,
        customer_name: 4, contact_type: 4, contact_value: 4, special_request: 4,
    };

    let current = 1;
    let quote = null;
    let busy = false;

    const text = (template, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), template);
    const money = (value) => (config.currency === 'JPY'
        ? `¥${new Intl.NumberFormat('ja-JP', { maximumFractionDigits: 0 }).format(Number(value) || 0)}`
        : `${config.currency} ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value) || 0)}`);

    function formatDate(iso) {
        const [year, month, day] = iso.split('-').map(Number);
        return new Intl.DateTimeFormat(config.locale, { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(year, month - 1, day));
    }

    function daysBetween(dropOffDate, pickupDate) {
        if (!dropOffDate) return 0;
        pickupDate = pickupDate || dropOffDate;
        const [y1, m1, d1] = dropOffDate.split('-').map(Number);
        const [y2, m2, d2] = pickupDate.split('-').map(Number);
        return Math.round((Date.UTC(y2, m2 - 1, d2) - Date.UTC(y1, m1 - 1, d1)) / 86400000) + 1;
    }

    function values() {
        const data = new FormData(form);
        return {
            drop_off_date: data.get('drop_off_date') || '',
            pickup_date: data.get('pickup_date') || data.get('drop_off_date') || '',
            vehicle_model: (data.get('vehicle_model') || '').trim(),
            license_plate: (data.get('license_plate') || '').trim(),
            vehicles: Number(data.get('vehicles')) || 0,
            service_type_slug: data.get('service_type_slug') || '',
            towing: form.elements.towing.checked && !towingField.hidden,
            customer_name: (data.get('customer_name') || '').trim(),
            contact_type: data.get('contact_type') || 'whatsapp',
            contact_value: (data.get('contact_value') || '').trim(),
            special_request: (data.get('special_request') || '').trim(),
        };
    }

    function saveDraft() {
        try {
            sessionStorage.setItem(config.draftKey, JSON.stringify({ step: current, values: values() }));
        } catch { /* storage unavailable: the wizard still works without it */ }
    }

    function clearDraft() {
        try {
            sessionStorage.removeItem(config.draftKey);
        } catch { /* nothing to clear */ }
    }

    function restoreDraft() {
        try {
            const draft = JSON.parse(sessionStorage.getItem(config.draftKey) || 'null');
            if (!draft) return;
            const { values: saved } = draft;
            ['drop_off_date', 'pickup_date', 'vehicle_model', 'license_plate', 'vehicles', 'customer_name', 'contact_value', 'special_request'].forEach((name) => {
                if (saved[name] !== undefined && saved[name] !== '' && saved[name] !== 0) form.elements[name].value = saved[name];
            });
            if (saved.contact_type) form.elements.contact_type.value = saved.contact_type;
            if (saved.service_type_slug) form.elements.service_type_slug.value = saved.service_type_slug;
            form.elements.towing.checked = Boolean(saved.towing);
            current = Math.min(Math.max(Number(draft.step) || 1, 1), steps.length);
        } catch { /* ignore a corrupt draft */ }
    }

    function clearError() {
        errorBox.hidden = true;
        errorText.textContent = '';
        alternativesBox.hidden = true;
        alternativesBox.querySelector('ul').replaceChildren();
    }

    function showError(message, alternatives = []) {
        errorText.textContent = message;
        errorBox.hidden = false;

        const list = alternativesBox.querySelector('ul');
        list.replaceChildren();
        alternatives.forEach((alternative) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'wizard-secondary';
            button.textContent = `${alternative.name} · ${money(alternative.total)}`;
            button.addEventListener('click', () => {
                form.elements.service_type_slug.value = alternative.slug;
                refreshServices();
                clearError();
                saveDraft();
            });
            item.appendChild(button);
            list.appendChild(item);
        });
        alternativesBox.hidden = alternatives.length === 0;
    }

    function selectedOption() {
        const slug = form.elements.service_type_slug.value;
        return serviceOptions.find((option) => option.querySelector('input').value === slug) || null;
    }

    function refreshServices() {
        const chosen = selectedOption();
        towingField.hidden = !chosen || chosen.dataset.towing !== '1';
        if (towingField.hidden) form.elements.towing.checked = false;
    }

    function refreshDays() {
        const days = daysBetween(form.elements.drop_off_date.value, form.elements.pickup_date.value);
        daysHint.textContent = days > 0 ? `${days} ${labels.days}` : '';
    }

    function validate(step) {
        const data = values();

        if (step === 1) {
            if (!data.drop_off_date || data.drop_off_date < config.today) return { field: 'drop_off_date', message: labels.drop_off_past };
            if (data.pickup_date < data.drop_off_date) return { field: 'pickup_date', message: labels.pickup_after };
            if (daysBetween(data.drop_off_date, data.pickup_date) > config.maxDays) return { field: 'pickup_date', message: text(labels.too_long, { max: config.maxDays }) };
        }

        if (step === 2) {
            if (!data.vehicle_model) return { field: 'vehicle_model', message: labels.invalid };
            if (!data.license_plate) return { field: 'license_plate', message: labels.invalid };
            if (data.vehicles < 1) return { field: 'vehicles', message: labels.invalid };
        }

        if (step === 3) {
            const chosen = selectedOption();
            if (!chosen) return { field: 'service_type_slug', message: labels.select_service };
        }

        if (step === 4) {
            if (!data.customer_name) return { field: 'customer_name', message: labels.invalid };
            const valid = data.contact_type === 'email'
                ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.contact_value)
                : /^\+?[0-9\s\-().]{6,20}$/.test(data.contact_value);
            if (!valid) return { field: 'contact_value', message: data.contact_type === 'email' ? labels.contact_email : labels.contact_phone };
        }

        return null;
    }

    function setBusy(value, label = null) {
        busy = value;
        [backButton, nextButton, submitButton].forEach((button) => { button.disabled = value; });
        if (label) nextButton.firstChild.textContent = `${label} `;
        else nextButton.firstChild.textContent = `${labels.next} `;
    }

    function showStep(step, focus = false) {
        current = step;
        steps.forEach((element, index) => { element.hidden = index + 1 !== step; });
        progress.forEach((element, index) => {
            element.classList.toggle('is-current', index + 1 === step);
            element.classList.toggle('is-done', index + 1 < step);
            if (index + 1 === step) element.setAttribute('aria-current', 'step');
            else element.removeAttribute('aria-current');
        });
        stepLabel.textContent = `${text(labels.step_of, { current: step, total: steps.length })} · ${labels.steps[step - 1]}`;
        backButton.hidden = step === 1;
        nextButton.hidden = step === steps.length;
        submitButton.hidden = step !== steps.length;

        if (step === 2 || step === 3) refreshServices();
        if (step === steps.length) renderSummary();
        if (focus) steps[step - 1].querySelector('input:not([disabled]), textarea')?.focus({ preventScroll: true });
        saveDraft();
    }

    function goToField(field) {
        showStep(fieldStep[field] || current, true);
    }

    async function post(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken || '' },
            body: JSON.stringify(payload),
        });
        const body = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, body };
    }

    function visitPayload() {
        const { drop_off_date, pickup_date, vehicles, service_type_slug, towing } = values();
        return { drop_off_date, pickup_date, vehicles, service_type_slug, towing, locale: config.locale };
    }

    function handleFailure(result) {
        if (result.status === 422 && result.body.errors) {
            const [field, messages] = Object.entries(result.body.errors)[0];
            showError(messages[0], result.body.alternatives || []);
            if (fieldStep[field] && fieldStep[field] !== current) showStep(fieldStep[field]);
            return;
        }
        showError(labels.error_generic);
    }

    async function next() {
        if (busy || current >= steps.length) return;
        clearError();

        const problem = validate(current);
        if (problem) {
            showError(problem.message);
            form.elements[problem.field]?.focus?.();
            return;
        }

        if (current === 3) {
            setBusy(true, labels.checking);
            try {
                const result = await post(config.quoteUrl, visitPayload());
                if (!result.ok) {
                    handleFailure(result);
                    return;
                }
                quote = result.body;
            } catch {
                showError(labels.error_network);
                return;
            } finally {
                setBusy(false);
            }
        }

        showStep(current + 1, true);
    }

    function summaryRow(label, value, step) {
        const wrapper = document.createElement('div');
        const term = document.createElement('dt');
        term.textContent = label;
        const detail = document.createElement('dd');
        const span = document.createElement('span');
        span.textContent = value;
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.textContent = labels.edit;
        edit.addEventListener('click', () => showStep(step, true));
        detail.append(span, edit);
        wrapper.append(term, detail);
        return wrapper;
    }

    function renderSummary() {
        const data = values();
        const chosen = selectedOption();
        const days = daysBetween(data.drop_off_date, data.pickup_date);

        const service = `${chosen ? chosen.dataset.name : ''}${data.towing ? ` + ${labels.towing.toLowerCase()}` : ''}`;
        const rows = [
            summaryRow(labels.dates, `${formatDate(data.drop_off_date)} → ${formatDate(data.pickup_date)} (${days} ${labels.days})`, 1),
            summaryRow(labels.vehicle, `${data.vehicle_model} · ${data.license_plate} · ${labels.vehicles}: ${data.vehicles}`, 2),
            summaryRow(labels.service, service, 3),
            summaryRow(labels.contact, `${data.customer_name} · ${labels[data.contact_type]}: ${data.contact_value}`, 4),
        ];
        if (data.special_request) rows.push(summaryRow(labels.special, data.special_request, 4));
        if (quote && quote.member_discount_percent > 0) {
            rows.push(summaryRow(labels.member_discount, `${quote.member_discount_percent}% (−${money(quote.discount_amount)})`, 3));
        }

        root.querySelector('[data-summary]').replaceChildren(...rows);
        root.querySelector('[data-summary-total]').textContent = quote ? money(quote.grand_total) : '';
    }

    function showDone(result) {
        flow.hidden = true;
        done.hidden = false;
        root.querySelector('[data-done-reference]').textContent = result.reference;

        [['whatsapp', 'whatsapp_url'], ['phone', 'phone_url'], ['email', 'email_url']].forEach(([name, key]) => {
            const link = root.querySelector(`[data-done-${name}]`);
            const url = result.handover?.[key];
            link.hidden = !url;
            if (url) link.href = url;
        });
    }

    async function submit(event) {
        event.preventDefault();
        if (busy || current !== steps.length) return;
        clearError();

        const problem = [1, 2, 3, 4].map(validate).find(Boolean);
        if (problem) {
            showError(problem.message);
            goToField(problem.field);
            return;
        }

        setBusy(true);
        submitButton.textContent = labels.sending;

        try {
            const result = await post(config.submitUrl, {
                ...visitPayload(),
                vehicle_model: values().vehicle_model,
                license_plate: values().license_plate,
                customer_name: values().customer_name,
                contact_type: values().contact_type,
                contact_value: values().contact_value,
                special_request: values().special_request,
                customer_token: localStorage.getItem(config.tokenKey) || undefined,
            });

            if (!result.ok) {
                handleFailure(result);
                return;
            }

            clearDraft();
            showDone(result.body);
        } catch {
            showError(labels.error_network);
        } finally {
            setBusy(false);
            submitButton.textContent = labels.submit;
        }
    }

    function reset() {
        form.reset();
        quote = null;
        clearError();
        done.hidden = true;
        flow.hidden = false;
        clearDraft();
        refreshDays();
        showStep(1, true);
    }

    function preselect(slug) {
        if (!flow.hidden) {
            refreshServices();
            const option = serviceOptions.find((candidate) => candidate.querySelector('input').value === slug);
            if (option && !option.querySelector('input').disabled) {
                form.elements.service_type_slug.value = slug;
                refreshServices();
                saveDraft();
            }
        }
    }

    form.addEventListener('input', () => { refreshDays(); if (current <= 3) refreshServices(); saveDraft(); });
    form.addEventListener('change', () => { refreshServices(); saveDraft(); });
    form.addEventListener('submit', submit);
    nextButton.addEventListener('click', next);
    backButton.addEventListener('click', () => { clearError(); showStep(Math.max(1, current - 1), true); });
    root.querySelector('[data-wizard-reset]').addEventListener('click', reset);
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && current < steps.length) {
            event.preventDefault();
            next();
        }
    });

    restoreDraft();
    refreshDays();
    refreshServices();
    showStep(current);
    preselect(root.dataset.preselectService || '');
}

document.addEventListener('DOMContentLoaded', initReservationWizard);
