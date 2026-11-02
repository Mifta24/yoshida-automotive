/**
 * AI Advisor chat panel. Vanilla JS, no framework: sends customer messages to
 * the Laravel backend and renders both plain text and the structured
 * ui_payload the AI's tools attach (service cards, price quotes, booking
 * confirmations, handover notices) as real DOM, not markdown-in-a-bubble.
 */
import { createCards } from './advisor-cards';
import { confetti } from './advisor-celebrate';
import { haptic, TAP } from './haptics';
import { createPicker } from './advisor-picker';
import { createShowcase } from './advisor-showcase';
import { createTour } from './advisor-tour';

function initAdvisor() {
    const root = document.getElementById('advisor-app');
    const widget = document.querySelector('[data-chat-widget]');
    if (!root || !widget) return;

    const config = {
        startUrl: root.dataset.startUrl,
        messageUrl: root.dataset.messageUrl,
        historyUrl: root.dataset.historyUrl,
        feedbackUrl: root.dataset.feedbackUrl,
        storageKey: root.dataset.storageKey,
        locale: root.dataset.locale,
        currency: root.dataset.currency,
        garageUrl: root.dataset.garageUrl,
        serviceUrlTemplate: root.dataset.serviceUrl,
        facilityUrlTemplate: root.dataset.facilityUrl,
        staffUrl: root.dataset.staffUrl,
        today: root.dataset.today,
        maxDays: Number(root.dataset.maxDays),
        quoteUrl: root.dataset.quoteUrl,
        reserveUrl: root.dataset.reserveUrl,
        memberName: root.dataset.memberName || '',
        memberPhone: root.dataset.memberPhone || '',
        workshopName: root.dataset.workshopName,
        storageSlug: root.dataset.workshopSlug,
        labels: {
            placeholder: root.dataset.labelPlaceholder,
            send: root.dataset.labelSend,
            open: root.dataset.labelOpen,
            close: root.dataset.labelClose,
            intro: root.dataset.labelIntro,
            warrantyIncluded: root.dataset.labelWarrantyIncluded,
            serviceOnly: root.dataset.labelServiceOnly,
            day: root.dataset.labelDay,
            noAvailability: root.dataset.labelNoAvailability,
            bookingReceived: root.dataset.labelBookingReceived,
            reference: root.dataset.labelReference,
            thinking: root.dataset.labelThinking,
            handedOver: root.dataset.labelHandedOver,
            statusSent: root.dataset.labelStatusSent,
            statusWaiting: root.dataset.labelStatusWaiting,
            statusReplied: root.dataset.labelStatusReplied,
            draftTitle: root.dataset.labelDraftTitle,
            draftBody: root.dataset.labelDraftBody,
            draftKeep: root.dataset.labelDraftKeep,
            draftDiscard: root.dataset.labelDraftDiscard,
            viewDetails: root.dataset.labelViewDetails,
            hideDetails: root.dataset.labelHideDetails,
            formTitle: root.dataset.labelFormTitle,
            formConfirm: root.dataset.labelFormConfirm,
            from: root.dataset.labelFrom,
            priceSuffix: root.dataset.labelPriceSuffix,
            showingServices: root.dataset.labelShowingServices,
            showingFacilities: root.dataset.labelShowingFacilities,
            openMap: root.dataset.labelOpenMap,
            directions: root.dataset.labelDirections,
            gettingThere: root.dataset.labelGettingThere,
            showingPhotos: root.dataset.labelShowingPhotos,
            hours: root.dataset.labelHours,
            photoPrev: root.dataset.labelPhotoPrev,
            photoNext: root.dataset.labelPhotoNext,
            photoClose: root.dataset.labelPhotoClose,
            closeShowcase: root.dataset.labelCloseShowcase,
            pickerTitle: root.dataset.labelPickerTitle,
            pickerMessage: root.dataset.labelPickerMessage,
            pickStart: root.dataset.labelPickStart,
            pickEnd: root.dataset.labelPickEnd,
            pickerContinue: root.dataset.labelPickerContinue,
            tourProgress: root.dataset.labelTourProgress,
            tourNext: root.dataset.labelTourNext,
            tourStop: root.dataset.labelTourStop,
            tourDone: root.dataset.labelTourDone,
            addCalendar: root.dataset.labelAddCalendar,
            share: root.dataset.labelShare,
            shareText: root.dataset.labelShareText,
            newChat: root.dataset.labelChatNew,
            serviceDetailsQuestion: root.dataset.labelServiceDetailsQuestion,
            bookNow: root.dataset.labelBookNow,
            menuHeading: root.dataset.labelMenuHeading,
            staff: root.dataset.labelStaff,
            error: root.dataset.labelError,
            slow: root.dataset.labelSlow,
            retry: root.dataset.labelRetry,
            mic: root.dataset.labelMic,
            micListening: root.dataset.labelMicListening,
            micDenied: root.dataset.labelMicDenied,
            micNoSpeech: root.dataset.labelMicNospeech,
        },
    };

    const messagesEl = root.querySelector('[data-messages]');
    const formEl = root.querySelector('[data-chat-form]');
    const inputEl = root.querySelector('[data-chat-input]');
    const submitEl = root.querySelector('[data-chat-submit]');
    const micEl = root.querySelector('[data-chat-mic]');
    const statusBanner = root.querySelector('[data-status-banner]');
    const chatStatus = root.querySelector('[data-chat-status]');
    const thinkingIndicator = root.querySelector('[data-thinking-indicator]');
    const draftDialog = root.querySelector('[data-draft-dialog]');
    const draftKeep = root.querySelector('[data-draft-keep]');
    const draftDiscard = root.querySelector('[data-draft-discard]');
    const newChatButton = widget.querySelector('[data-chat-new]');
    const resetDialog = root.querySelector('[data-reset-dialog]');
    const resetKeep = root.querySelector('[data-reset-keep]');
    const resetConfirm = root.querySelector('[data-reset-confirm]');
    const chatLog = root.querySelector('#advisor-chat-log');
    const closeButton = widget.querySelector('[data-chat-close]');
    const launcher = widget.querySelector('[data-chat-launcher]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let ready = false;
    let busy = false;
    let handedOver = false;
    let knownMessageCount = 0;
    let pollTimer = null;
    let isOpen = false;
    let draftDialogOpen = false;
    let resetDialogOpen = false;

    function setChatStatus(text, tone = '') {
        if (!chatStatus) return;

        chatStatus.textContent = text || '';
        chatStatus.className = `chat-status${text ? ` is-${tone}` : ''}`;
        chatStatus.hidden = !text;
    }

    function showDraftDialog() {
        if (!draftDialog) return;

        draftDialogOpen = true;
        draftDialog.hidden = false;
        draftDialog.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => draftKeep?.focus());
    }

    function hideDraftDialog({ focusInput = false } = {}) {
        if (!draftDialog) return;

        draftDialogOpen = false;
        draftDialog.hidden = true;
        draftDialog.setAttribute('aria-hidden', 'true');
        if (focusInput) inputEl.focus();
    }

    function syncPanelState() {
        root.classList.toggle('is-open', isOpen);
        root.dataset.chatState = isOpen ? 'open' : 'closed';
        chatLog?.setAttribute('aria-hidden', String(!isOpen));
        formEl?.setAttribute('aria-hidden', String(!isOpen));
        launcher?.setAttribute('aria-expanded', String(isOpen));
    }

    function openPanel({ focus = false } = {}) {
        isOpen = true;
        syncPanelState();
        scrollToBottom();

        if (focus) {
            requestAnimationFrame(() => inputEl.focus());
        }
    }

    function finishClosePanel({ restoreFocus = true } = {}) {
        isOpen = false;
        stopListening({ discard: true });
        syncPanelState();
        hideDraftDialog();
        if (restoreFocus) launcher?.focus();
    }

    function closePanel({ confirmDraft = true, restoreFocus = true } = {}) {
        if (confirmDraft && inputEl?.value.trim()) {
            showDraftDialog();
            return;
        }

        finishClosePanel({ restoreFocus });
    }

    closeButton?.addEventListener('click', () => closePanel());
    launcher?.addEventListener('click', () => openPanel({ focus: true }));
    draftKeep?.addEventListener('click', () => hideDraftDialog({ focusInput: true }));
    draftDiscard?.addEventListener('click', () => {
        inputEl.value = '';
        finishClosePanel();
    });
    function showResetDialog() {
        if (!resetDialog) return;

        hideDraftDialog();
        resetDialogOpen = true;
        resetDialog.hidden = false;
        resetDialog.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => resetKeep?.focus());
    }

    function hideResetDialog({ focusButton = false } = {}) {
        if (!resetDialog) return;

        resetDialogOpen = false;
        resetDialog.hidden = true;
        resetDialog.setAttribute('aria-hidden', 'true');
        if (focusButton) newChatButton?.focus();
    }

    // Nothing to lose on an untouched chat, so only ask when there is a conversation.
    newChatButton?.addEventListener('click', () => {
        if (busy || !ready) return;

        if (knownMessageCount > 0 || inputEl.value.trim()) {
            showResetDialog();
            return;
        }

        startNewChat();
    });
    resetKeep?.addEventListener('click', () => hideResetDialog({ focusButton: true }));
    resetConfirm?.addEventListener('click', () => startNewChat());
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        if (resetDialogOpen) {
            hideResetDialog({ focusButton: true });
            return;
        }

        if (draftDialogOpen) {
            hideDraftDialog({ focusInput: true });
            return;
        }

        if (showcase.isOpen()) {
            showcase.hide();
            return;
        }

        if (isOpen) closePanel();
    });
    syncPanelState();

    function money(value) {
        if (config.currency === 'JPY') {
            return `¥${new Intl.NumberFormat('ja-JP', { maximumFractionDigits: 0 }).format(Number(value) || 0)}`;
        }

        const n = Number(value) || 0;
        const numberLocale = config.locale === 'ja' ? 'ja-JP' : config.locale === 'en' ? 'en-US' : 'id-ID';
        return `${config.currency} ${new Intl.NumberFormat(numberLocale, { maximumFractionDigits: 0 }).format(n)}`;
    }

    // Cloudflare drops a request that is still unanswered after 100s, so give
    // up just before that and let the customer retry instead of waiting forever.
    const API_TIMEOUT_MS = 95000;

    async function api(url, body) {
        let response;

        try {
            response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify(body || {}),
                signal: AbortSignal.timeout(API_TIMEOUT_MS),
            });
        } catch (err) {
            const error = new Error(err.name === 'TimeoutError' ? 'Request timed out' : 'Network error');
            error.status = err.name === 'TimeoutError' ? 408 : 0;
            throw error;
        }

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            const error = new Error(payload.message || `Request failed (${response.status})`);
            error.status = response.status;
            error.payload = payload;
            throw error;
        }

        return response.json();
    }

    function avatarMark() {
        const el = document.createElement('span');
        el.className = 'chat-host-avatar';
        el.setAttribute('aria-hidden', 'true');
        return el;
    }

    function bubble(role, text) {
        const wrap = document.createElement('div');
        wrap.className =
            role === 'customer' ? 'flex justify-end' : 'flex items-end justify-start gap-2';

        if (role !== 'customer') {
            wrap.appendChild(avatarMark());
        }

        const inner = document.createElement('div');
        inner.className =
            role === 'customer'
                ? 'max-w-[85%] rounded-2xl rounded-br-sm bg-stone-900 px-3 py-2 text-sm text-white'
                : 'max-w-[85%] rounded-2xl rounded-bl-sm bg-stone-100 px-3 py-2 text-sm text-stone-900';
        inner.textContent = text;

        wrap.appendChild(inner);
        return wrap;
    }

    function card(children) {
        const wrap = document.createElement('div');
        wrap.className = 'flex justify-start';
        const inner = document.createElement('div');
        inner.className = 'w-full max-w-[92%] space-y-2';
        children.forEach((c) => inner.appendChild(c));
        wrap.appendChild(inner);
        return wrap;
    }

    function handoverCard(payload) {
        const el = document.createElement('div');
        el.className = 'rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800';
        el.textContent = config.labels.handedOver;
        return el;
    }

    const wizard = JSON.parse(root.querySelector('script[data-wizard-labels]')?.textContent || '{}');
    const showcase = createShowcase({
        stage: root.closest('.stage') || document.body,
        closeLabel: config.labels.closeShowcase,
        backLabel: wizard.back,
    });

    const picker = createPicker({
        config,
        wizard,
        submit: (message) => sendMessage(message, { skeleton: true }),
    });

    const cards = createCards({
        config,
        wizard,
        terms: JSON.parse(root.querySelector('script[data-service-terms]')?.textContent || '{}'),
        money,
        api,
        ask: (text) => sendMessage(text),
        append: (node) => {
            messagesEl.appendChild(card([node]));
            scrollToBottom();
        },
        present: (content, options) => showcase.show(content, options),
        picker: (options) => picker.build(options),
        celebrate: confetti,
        // The confirmation made with the form is saved on the server like any reply.
        recorded: () => { knownMessageCount += 1; },
    });

    /**
     * The transcript keeps one tappable line for what the advisor put in the
     * showcase, so the customer can bring it back after closing it.
     */
    function recapChip(label, onClick) {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'rc-recap';
        chip.innerHTML = '<span aria-hidden="true">✦</span>';
        chip.append(document.createTextNode(` ${label} `));
        chip.insertAdjacentHTML('beforeend', '<span aria-hidden="true">›</span>');
        chip.addEventListener('click', onClick);

        return chip;
    }

    /** Chips whose answer is a set of service cards: the showcase opens on placeholders while the advisor looks. */
    const SHOWS_SERVICES = new Set(['cheapest', 'family', 'view', 'services']);

    const quickReplies = JSON.parse(root.querySelector('script[data-quick-replies]')?.textContent || '{}');

    /**
     * Tappable answers under a reply, so the customer can carry on without typing.
     * A key with no message is something the page does itself.
     */
    function quickChips(keys) {
        const items = (keys || []).filter((key) => quickReplies[key]);
        if (!items.length) return null;

        const wrap = document.createElement('div');
        wrap.className = 'qr';
        wrap.dataset.quickReplies = '';

        items.forEach((key) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'qr-chip';
            chip.textContent = quickReplies[key].label;
            chip.addEventListener('click', () => {
                haptic(TAP);
                if (key === 'pick_dates') showcase.show(picker.build());
                else if (key === 'tour') tour.start();
                else sendMessage(quickReplies[key].message, { skeleton: SHOWS_SERVICES.has(key) });
            });
            wrap.appendChild(chip);
        });

        return wrap;
    }

    /** Only the newest reply keeps its chips; older ones would answer a question that is gone. */
    function keepLatestQuickReplies() {
        [...messagesEl.querySelectorAll('[data-quick-replies]')].slice(0, -1).forEach((el) => el.parentElement?.remove());
    }

    const tour = createTour({
        stops: JSON.parse(root.querySelector('script[data-tour-stops]')?.textContent || '[]'),
        storageKey: `advisor_tour_${config.storageSlug}`,
        labels: { progress: config.labels.tourProgress, next: config.labels.tourNext, stop: config.labels.tourStop },
        stage: root.closest('.stage') || document.body,
        onFinish: () => showcase.show(picker.build(), { comment: config.labels.tourDone }),
    });

    function renderUiPayload(uiPayload, { fresh = false, comment = '', quick = [] } = {}) {
        if (!uiPayload || !uiPayload.length) return false;

        let latest = null;

        uiPayload.forEach((payload) => {
            if (payload.type === 'tour_start') {
                if (fresh) window.setTimeout(() => tour.start(), 2400);

                return;
            }

            if (fresh && payload.type === 'booking_confirmation') confetti();

            const item = cards.describe(payload);

            if (item) {
                const present = () => showcase.show(item.build(), { comment, chips: quickChips(quick) });
                messagesEl.appendChild(card([recapChip(item.recap, present)]));
                latest = present;

                return;
            }

            const node = payload.type === 'handover' ? handoverCard(payload) : cards.render(payload);
            if (node) messagesEl.appendChild(card([node]));
        });

        // Only the last thing the advisor brought up is put on show.
        if (fresh && latest) latest();

        return Boolean(latest);
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setBusy(value) {
        busy = value;
        const disabled = busy || !ready || handedOver;
        inputEl.disabled = disabled;
        submitEl.disabled = disabled;
        if (micEl) micEl.disabled = disabled;
        if (disabled) stopListening({ discard: true });
        if (launcher) launcher.disabled = busy || !ready;
        if (newChatButton) newChatButton.disabled = busy || !ready;
        root.setAttribute('aria-busy', String(busy));
        document.body.classList.toggle('is-thinking', busy);
        if (thinkingIndicator) {
            thinkingIndicator.hidden = !busy;
        }
        document.querySelectorAll('[data-hero-quick-message], [data-ask-ai-button], [data-quick-message]')
            .forEach((button) => { button.disabled = disabled; });
        submitEl.textContent = busy ? config.labels.thinking : config.labels.send;
        if (busy) scrollToBottom();
    }

    function showHandedOverBanner() {
        handedOver = true;
        statusBanner.textContent = config.labels.handedOver;
        statusBanner.classList.remove('hidden');
        setChatStatus(config.labels.statusWaiting, 'waiting');
        setBusy(busy);
        startPolling();
    }

    function clearHandedOverBanner() {
        handedOver = false;
        statusBanner.classList.add('hidden');
        setBusy(busy);
        stopPolling();
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollForStaffReplies, 4000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    async function pollForStaffReplies() {
        const customerToken = localStorage.getItem(config.storageKey);
        if (!customerToken) return;

        try {
            const response = await fetch(`${config.historyUrl}?customer_token=${encodeURIComponent(customerToken)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;

            const data = await response.json();

            if (data.messages.length > knownMessageCount) {
                const newMessages = data.messages.slice(knownMessageCount);
                newMessages.forEach((message) => renderMessage(message));
                if (newMessages.some((message) => message.role === 'staff')) {
                    setChatStatus(config.labels.statusReplied, 'replied');
                }
                window.workshopSound?.play('incoming');
                knownMessageCount = data.messages.length;
                scrollToBottom();
            }

            if (data.status !== 'handed_over') {
                clearHandedOverBanner();
            }
        } catch {
            // transient network hiccup — try again on the next tick
        }
    }

    const ratingWords = {
        id: { up: 'Jawaban membantu', down: 'Jawaban kurang membantu' },
        en: { up: 'Helpful answer', down: 'Not helpful' },
        ja: { up: '役に立った', down: '役に立たなかった' },
    };

    /**
     * Thumbs under a reply, so the workshop learns which answers help. Tapping
     * the same thumb again takes the rating back.
     */
    function ratingRow(message) {
        const words = ratingWords[config.locale] || ratingWords.en;
        const row = document.createElement('div');
        row.className = 'flex gap-1 pl-10 text-xs';
        let current = message.rating || 0;

        const buttons = [
            [1, '👍', words.up],
            [-1, '👎', words.down],
        ].map(([value, symbol, label]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = symbol;
            button.setAttribute('aria-label', label);
            button.title = label;
            button.addEventListener('click', async () => {
                const next = current === value ? 0 : value;
                try {
                    await api(config.feedbackUrl, { customer_token: localStorage.getItem(config.storageKey), message_id: message.id, rating: next });
                    current = next;
                    paint();
                } catch {
                    // the thumb is a courtesy; a failed save must never get in the customer's way
                }
            });
            row.appendChild(button);

            return [value, button];
        });

        function paint() {
            buttons.forEach(([value, button]) => {
                button.className = `rounded-full px-2 py-0.5 transition ${current === value ? 'bg-amber-100 opacity-100' : 'opacity-40 hover:opacity-100'}`;
                button.setAttribute('aria-pressed', String(current === value));
            });
        }

        paint();

        return row;
    }

    function renderMessage(message, { fresh = false } = {}) {
        let presented = false;

        if (message.role === 'customer') {
            messagesEl.appendChild(bubble('customer', message.content));
        } else if (message.role === 'assistant') {
            if (message.content) {
                messagesEl.appendChild(bubble('assistant', message.content));
                if (message.id && config.feedbackUrl) messagesEl.appendChild(ratingRow(message));
            }
            presented = renderUiPayload(message.ui_payload, { fresh, comment: message.content || '', quick: message.quick_replies });
            const chips = actionChips(message.suggested_actions);
            if (chips) messagesEl.appendChild(chips);
            const quick = quickChips(message.quick_replies);
            if (quick) messagesEl.appendChild(card([quick]));
            keepLatestQuickReplies();
        } else if (message.role === 'staff') {
            messagesEl.appendChild(card([staffBubble(message.content)]));
            setChatStatus(config.labels.statusReplied, 'replied');
        } else if (message.role === 'system') {
            messagesEl.appendChild(bubble('assistant', message.content));
        }

        return presented;
    }

    function quickMenuCard() {
        const template = document.querySelector('[data-quick-menu-items]');
        if (!template) return null;

        const el = document.createElement('div');
        el.className = 'overflow-hidden rounded-xl border border-stone-200 bg-white';

        const heading = document.createElement('p');
        heading.className = 'border-b border-stone-100 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-stone-400';
        heading.textContent = config.labels.menuHeading;
        el.appendChild(heading);

        const list = document.createElement('div');
        list.className = 'divide-y divide-stone-100';

        template.content.querySelectorAll('[data-quick-message]').forEach((source) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'flex w-full items-center justify-between px-3 py-2.5 text-left text-sm text-stone-700 hover:bg-amber-50';
            item.innerHTML = `<span>${source.textContent}</span><span class="text-stone-300">›</span>`;
            item.addEventListener('click', () => sendMessage(source.dataset.quickMessage));
            list.appendChild(item);
        });

        el.appendChild(list);
        return el;
    }

    function staffBubble(text) {
        const el = document.createElement('div');
        el.className = 'rounded-2xl rounded-bl-sm border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900';
        const label = document.createElement('p');
        label.className = 'mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-500';
        label.textContent = config.labels.staff;
        const body = document.createElement('span');
        body.textContent = text;
        el.append(label, body);
        return el;
    }

    const sceneNames = { home: 'reception', garage: 'reception', info: 'reception', services: 'services', service: 'service_detail', facilities: 'facilities', facility: 'facility_detail', reservation: 'reservation', staff: 'handover' };

    /**
     * Where the customer is in the garage, so the advisor can answer for "this
     * service" or the reservation on screen. Never includes name or contact data.
     */
    function uiContext() {
        const pageScene = document.querySelector('[data-garage]')?.dataset.scene || 'garage';
        const context = { scene: sceneNames[pageScene] || 'reception' };

        const lastSegment = decodeURIComponent(window.location.pathname.split('/').pop());
        if (pageScene === 'service') context.selected_service = lastSegment;
        if (pageScene === 'facility') context.selected_facility = Number(lastSegment);

        if (pageScene === 'reservation') {
            try {
                const draft = JSON.parse(sessionStorage.getItem(`reservation_draft_${root.dataset.workshopSlug}`) || 'null');
                const { drop_off_date, pickup_date, vehicle_model, license_plate, vehicles, service_type_slug } = draft?.values || {};
                context.reservation = { drop_off_date, pickup_date, vehicle_model, license_plate, vehicles, service_type_slug };
                if (service_type_slug) context.selected_service = service_type_slug;
            } catch { /* no draft to share */ }
        }

        return context;
    }

    function actionChips(actions) {
        if (!actions || !actions.length) return null;

        const serviceUrl = (slug) => config.serviceUrlTemplate.replace('__SLUG__', encodeURIComponent(slug));
        const targets = {
            view_service: [config.labels.viewDetails, (action) => serviceUrl(action.service)],
            staff: [config.labels.staff, () => config.staffUrl],
        };

        const wrap = document.createElement('div');
        wrap.className = 'flex flex-wrap gap-2 pl-10';

        actions.forEach((action) => {
            const target = targets[action.action];
            if (!target) return;
            const link = document.createElement('a');
            link.href = target[1](action);
            link.className = 'rounded-full border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 hover:bg-stone-50';
            link.textContent = target[0];
            link.addEventListener('click', (event) => {
                closePanel({ confirmDraft: false, restoreFocus: false });

                // Another page: walk there the way the menu does. A panel on
                // this page is just a hash change, so leave it to the browser.
                if (link.pathname !== window.location.pathname && window.workshopStage) {
                    event.preventDefault();
                    window.workshopStage.leave(link.href);
                }
            });
            wrap.appendChild(link);
        });

        return wrap.childElementCount ? wrap : null;
    }

    function errorCard(text, err) {
        const el = document.createElement('div');
        el.className = 'rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900';

        const message = document.createElement('p');
        message.textContent = err.status === 429 ? config.labels.slow : config.labels.error;

        const actions = document.createElement('div');
        actions.className = 'mt-3 flex flex-wrap gap-2';

        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'rounded-full bg-stone-900 px-3 py-1.5 text-xs font-medium text-white';
        retry.textContent = config.labels.retry;
        retry.addEventListener('click', () => {
            el.remove();
            sendMessage(text, { retry: true });
        });

        const staff = document.createElement('a');
        staff.href = config.staffUrl;
        staff.className = 'rounded-full border border-rose-300 bg-white px-3 py-1.5 text-xs font-medium text-rose-900';
        staff.textContent = config.labels.staff;
        staff.addEventListener('click', (event) => {
            closePanel({ confirmDraft: false, restoreFocus: false });

            if (window.workshopStage) {
                event.preventDefault();
                window.workshopStage.leave(staff.href);
            }
        });

        actions.append(retry, staff);
        el.append(message, actions);
        return el;
    }

    async function sendMessage(text, { retry = false, skeleton = false } = {}) {
        if (!text.trim() || handedOver || busy || !ready) return;

        const customerToken = localStorage.getItem(config.storageKey);
        if (!customerToken) return;

        // The customer is taking over from the tour.
        tour.stop();

        openPanel();
        if (!retry) messagesEl.appendChild(bubble('customer', text));
        window.workshopSound?.play('sent');
        scrollToBottom();
        setBusy(true);

        // The reply is expected to be cards: hold their place with placeholders until they arrive.
        if (skeleton) showcase.show(cards.skeleton(), { comment: config.labels.thinking });

        try {
            const data = await api(config.messageUrl, { customer_token: customerToken, message: text, ...uiContext() });
            const presented = renderMessage(data.message, { fresh: true });
            if (skeleton && !presented) showcase.hide();
            setChatStatus(config.labels.statusSent, 'sent');
            window.workshopSound?.play('incoming');
            knownMessageCount += 2; // the customer message just sent + the reply just rendered
            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
        } catch (err) {
            if (skeleton) showcase.hide();
            messagesEl.appendChild(errorCard(text, err));
        } finally {
            setBusy(false);
            scrollToBottom();
        }
    }

    function showIntro() {
        messagesEl.appendChild(bubble('assistant', config.labels.intro));
        const menu = quickMenuCard();
        if (menu) messagesEl.appendChild(card([menu]));
    }

    async function startConversation() {
        const data = await api(config.startUrl, { locale: config.locale });
        localStorage.setItem(config.storageKey, data.customer_token);
        showIntro();
    }

    /**
     * Leaves the current conversation behind and opens a fresh one. The old
     * one stays on the server, so staff can still see and answer a handover.
     */
    async function startNewChat() {
        if (busy || !ready) return;

        hideResetDialog();
        setBusy(true);

        try {
            const data = await api(config.startUrl, { locale: config.locale });

            stopListening({ discard: true });
            stopPolling();
            showcase.hide();
            localStorage.setItem(config.storageKey, data.customer_token);
            messagesEl.replaceChildren();
            inputEl.value = '';
            statusBanner.classList.add('hidden');
            handedOver = false;
            knownMessageCount = 0;
            setChatStatus('');
            showIntro();
        } catch {
            statusBanner.textContent = root.dataset.labelConnectionError;
            statusBanner.classList.remove('hidden');
        } finally {
            setBusy(false);
            scrollToBottom();
            inputEl.focus();
        }
    }

    async function boot() {
        const customerToken = localStorage.getItem(config.storageKey);

        if (!customerToken) {
            await startConversation();
            return;
        }

        try {
            const response = await fetch(`${config.historyUrl}?customer_token=${encodeURIComponent(customerToken)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('history_unavailable');

            const data = await response.json();

            if (!data.messages.length) {
                showIntro();
            } else {
                data.messages.forEach((message) => renderMessage(message));
            }
            knownMessageCount = data.messages.length;

            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
            if (data.messages.some((message) => message.role === 'staff')) {
                setChatStatus(config.labels.statusReplied, 'replied');
            }
        } catch {
            localStorage.removeItem(config.storageKey);
            return boot();
        }

        scrollToBottom();
    }

    // Voice input: the browser's speech recognition turns what the customer says
    // into text, which then goes through the same path as a typed message.
    // The button stays hidden where the browser has no speech recognition.
    const SpeechRecognitionClass = window.SpeechRecognition || window.webkitSpeechRecognition;
    const speechLocales = { id: 'id-ID', en: 'en-US', ja: 'ja-JP' };
    let recognition = null;
    let micFailure = null;
    let micDiscard = false;

    function setListening(value) {
        micEl.classList.toggle('is-listening', value);
        micEl.setAttribute('aria-pressed', String(value));
        const label = value ? config.labels.micListening : config.labels.mic;
        micEl.setAttribute('aria-label', label);
        micEl.title = label;
        inputEl.placeholder = value ? config.labels.micListening : config.labels.placeholder;
    }

    function stopListening({ discard = false } = {}) {
        micDiscard = discard;
        if (discard) recognition?.abort();
        else recognition?.stop();
    }

    function startListening() {
        if (!SpeechRecognitionClass || recognition || !ready || busy || handedOver) return;

        const session = new SpeechRecognitionClass();
        let transcript = '';
        micFailure = null;
        micDiscard = false;
        session.lang = speechLocales[config.locale] || 'en-US';
        session.interimResults = true;
        session.continuous = false;
        session.maxAlternatives = 1;

        session.onresult = (event) => {
            transcript = Array.from(event.results).map((result) => result[0].transcript).join('').trim();
            inputEl.value = transcript;
        };
        session.onerror = (event) => {
            micFailure = event.error;
        };
        session.onend = () => {
            recognition = null;
            setListening(false);

            if (micDiscard) return;

            if (micFailure === 'not-allowed' || micFailure === 'service-not-allowed') {
                setChatStatus(config.labels.micDenied, 'error');
            } else if (!transcript && micFailure !== 'aborted') {
                setChatStatus(config.labels.micNoSpeech, 'error');
            } else if (transcript && !busy && !handedOver) {
                inputEl.value = '';
                sendMessage(transcript);
            }
        };

        recognition = session;
        setListening(true);
        try {
            session.start();
        } catch {
            recognition = null;
            setListening(false);
        }
    }

    if (micEl && SpeechRecognitionClass) {
        micEl.hidden = false;
        micEl.addEventListener('click', () => (recognition ? stopListening() : startListening()));
    }

    formEl.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!ready || busy || handedOver || !inputEl.value.trim()) return;
        const text = inputEl.value;
        inputEl.value = '';
        sendMessage(text);
    });

    document.querySelectorAll('[data-ask-ai-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const serviceCard = button.closest('[data-service-card]');
            const serviceName = serviceCard?.dataset.serviceName || '';
            openPanel();
            sendMessage(`Tell me more about the ${serviceName}`);
        });
    });

    // Hero quick-start menu — same topics as the in-chat menu, always
    // visible up front so customers see what the AI can do immediately.
    document.querySelectorAll('[data-hero-quick-message]').forEach((button) => {
        button.addEventListener('click', () => {
            openPanel();
            sendMessage(button.dataset.heroQuickMessage);
        });
    });

    // A menu pick in the same scene: the advisor answers right where the customer stands.
    window.addEventListener('advisor:ask', (event) => sendMessage(event.detail.message));

    document.querySelectorAll('[data-tour-start]').forEach((button) => button.addEventListener('click', () => tour.start()));
    tour.resume();

    setBusy(true);
    boot().then(() => {
        ready = true;
        scrollToBottom();
    }).catch(() => {
        statusBanner.textContent = root.dataset.labelConnectionError;
        statusBanner.classList.remove('hidden');
        messagesEl.appendChild(bubble('assistant', config.labels.intro));
    }).finally(() => {
        setBusy(false);

        // The customer chose this topic from the menu on the previous scene:
        // let the new scene settle for a beat, then carry on the conversation.
        const topic = window.takePendingAdvisorTopic?.();
        if (topic && ready) window.setTimeout(() => sendMessage(topic), 650);
    });
}

document.addEventListener('DOMContentLoaded', initAdvisor);

function initServiceGallery() {
    document.querySelectorAll('[data-service-gallery]').forEach((gallery) => {
        const main = gallery.querySelector('[data-service-gallery-main]');
        const thumbs = [...gallery.querySelectorAll('[data-service-thumb]')];
        if (!main) return;

        thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
                main.src = thumb.dataset.src;
                main.alt = thumb.dataset.alt || '';
                thumbs.forEach((other) => other.toggleAttribute('aria-current', other === thumb));
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', initServiceGallery);
