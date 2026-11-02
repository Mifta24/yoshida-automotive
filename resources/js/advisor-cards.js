/**
 * The tappable cards the AI advisor shows in the chat: a rail of service cards
 * (photo, details that open in place, a reserve button), facility, service-photo and
 * menu cards, the location card, the reservation form
 * the customer can finish by tapping, and the confirmation that follows. Prices
 * and availability always come from the server; nothing here decides them.
 *
 * Everything is built with DOM nodes and textContent, never HTML strings, so
 * service names and anything the customer typed cannot inject markup.
 */
import { calendarFile } from './advisor-celebrate';
import { DONE, haptic } from './haptics';
import { openLightbox } from './advisor-lightbox';

const PHONE_PATTERN = /^\+?[0-9\s\-().]{6,20}$/;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const CONTACT_TYPES = ['whatsapp', 'phone', 'email'];

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

const svgCheck = () => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M5 12.5l4.2 4.2L19 7');
    svg.appendChild(path);

    return svg;
};

const isoDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

const daysBetween = (from, to) => Math.round((new Date(`${to}T00:00:00`) - new Date(`${from}T00:00:00`)) / 86400000) + 1;

/**
 * @param {object} ctx
 * @param {object} ctx.config      the chat's config (locale, labels, urls)
 * @param {object} ctx.wizard      the reservation wizard's labels, in the customer's language
 * @param {object} ctx.terms       vehicle class / inclusion names, in the customer's language
 * @param {(value: number) => string} ctx.money
 * @param {(url: string, body: object) => Promise<any>} ctx.api
 * @param {(text: string) => void} ctx.ask          sends a message as the customer
 * @param {(node: Element) => void} ctx.append      adds a card to the transcript
 * @param {(node: Element, options?: {back?: boolean}) => void} ctx.present  puts a card in the showcase
 * @param {() => void} ctx.celebrate               a reservation request just went through
 * @param {(options: object) => Element} ctx.picker   builds the calendar card the advisor offers for dates
 * @param {() => void} ctx.recorded                 a message was saved server-side that the chat did not receive
 */
export function createCards(ctx) {
    const { config, wizard, terms, money, api } = ctx;
    const { labels } = config;
    const numberLocale = config.locale === 'ja' ? 'ja-JP' : config.locale === 'en' ? 'en-US' : 'id-ID';
    const services = new Map();
    const openForms = new Map();

    const text = (template, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), template);
    const day = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString(numberLocale, { day: 'numeric', month: 'short' });
    const vehicleClass = (service) => (service.vehicle_class ? terms.vehicle_class?.[service.vehicle_class] || service.vehicle_class : '');
    const inclusion = (key) => terms.inclusion?.[key] || key.replace(/_/g, ' ');
    const photos = (service) => (service.images?.length ? service.images : service.thumbnail_url ? [{ url: service.thumbnail_url, alt: service.name }] : []);

    function priceLine(service, visit) {
        if (visit && service.total_price != null) {
            return h('p', { class: 'rc-price' }, money(service.total_price), h('span', { text: `${visit.days ?? service.days} ${labels.day}` }));
        }

        return h('p', { class: 'rc-price' },
            labels.from ? h('span', { class: 'rc-from', text: labels.from }) : null,
            money(service.starting_price),
            h('span', { text: labels.priceSuffix }));
    }

    /** The photo walks to its own page, growing into the big photo there. */
    function openPage(event, photo) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;

        event.preventDefault();
        const href = event.currentTarget.href;

        if (window.workshopStage) window.workshopStage.leave(href, null, null, { photo: photo?.isConnected && photo.naturalWidth ? photo : null });
        else window.location.href = href;
    }

    function serviceCard(service, { index = 0, visit = null, expanded = false } = {}) {
        services.set(service.service_type_slug, service);

        const gallery = photos(service);
        const photo = h('img', {
            class: 'rc-photo-img',
            src: gallery[0]?.url,
            alt: gallery[0]?.alt || service.name,
            loading: 'lazy',
            onLoad: (event) => event.target.classList.add('is-loaded'),
            onError: (event) => event.target.remove(),
        });

        const thumbs = gallery.length > 1
            ? h('div', { class: 'rc-thumbs' }, gallery.map((image, position) => h('button', {
                type: 'button',
                'aria-label': `${service.name} ${position + 1}`,
                'aria-current': position === 0 ? 'true' : null,
                onClick: (event) => {
                    photo.src = image.url;
                    photo.alt = image.alt || service.name;
                    event.currentTarget.parentElement.querySelectorAll('button').forEach((other) => other.toggleAttribute('aria-current', other === event.currentTarget));
                },
            }, h('img', { src: image.url, alt: '', loading: 'lazy' }))))
            : null;

        const details = h('div', { class: `rc-details${expanded ? ' is-open' : ''}`, inert: !expanded },
            h('div', { class: 'rc-details-inner' },
                service.description ? h('p', { class: 'rc-desc', text: service.description }) : null,
                service.inclusions?.length ? h('ul', { class: 'rc-chips' }, service.inclusions.map((key) => h('li', { text: inclusion(key) }))) : null,
                thumbs));

        const toggle = h('button', {
            type: 'button',
            class: 'rc-btn',
            'aria-expanded': String(expanded),
            text: expanded ? labels.hideDetails : labels.viewDetails,
            onClick: () => {
                const open = !details.classList.contains('is-open');
                details.classList.toggle('is-open', open);
                details.inert = !open;
                toggle.setAttribute('aria-expanded', String(open));
                toggle.textContent = open ? labels.hideDetails : labels.viewDetails;
            },
        });

        return h('article', { class: 'rc', style: `--i:${index}`, role: 'listitem' },
            h('a', {
                class: 'rc-photo',
                href: config.serviceUrlTemplate.replace('__SLUG__', encodeURIComponent(service.service_type_slug)),
                'aria-label': service.name,
                onClick: (event) => openPage(event, photo),
            },
                gallery.length ? photo : h('span', { class: 'rc-photo-empty', 'aria-hidden': 'true' }),
                h('span', { class: 'rc-photo-cue', 'aria-hidden': 'true', text: '↗' }),
                h('div', { class: 'rc-badges' },
                    vehicleClass(service) ? h('span', { text: vehicleClass(service) }) : null,
                    service.warranty_included ? h('span', { text: labels.warrantyIncluded }) : null)),
            h('div', { class: 'rc-body' },
                h('h4', { class: 'rc-name', text: service.name }),
                service.duration_minutes ? h('p', { class: 'rc-meta', text: text(wizard.fits, { count: service.duration_minutes }) }) : null,
                priceLine(service, visit),
                details,
                h('div', { class: 'rc-actions' },
                    toggle,
                    h('button', {
                        type: 'button',
                        class: 'rc-btn rc-btn-primary',
                        text: labels.bookNow,
                        onClick: () => openForm({ service, values: visit ? { drop_off_date: visit.drop_off_date, pickup_date: visit.pickup_date } : {} }),
                    }))));
    }

    const lightboxLabels = () => ({ prev: labels.photoPrev, next: labels.photoNext, close: labels.photoClose });

    /**
     * A photo that grows full-screen when tapped, with `select` to swap which
     * photo of the gallery it shows (for the thumbnail strip under it).
     */
    function photoFrame(gallery, name, { items = null, start = 0 } = {}) {
        let at = 0;
        const photo = h('img', {
            class: 'rc-photo-img',
            src: gallery[0]?.url,
            alt: gallery[0]?.alt || name,
            loading: 'lazy',
            onLoad: (event) => event.target.classList.add('is-loaded'),
            onError: (event) => event.target.remove(),
        });
        const frame = h('button', {
            type: 'button',
            class: 'rc-photo rc-photo-zoom',
            'aria-label': name,
            onClick: () => openLightbox(items ?? gallery.map((image) => ({ url: image.url, alt: image.alt || name, caption: image.caption || name })), items ? start : at, lightboxLabels()),
        }, photo, h('span', { class: 'rc-photo-cue', 'aria-hidden': 'true', text: '⤢' }));

        return {
            frame,
            select(position) {
                at = position;
                photo.src = gallery[position].url;
                photo.alt = gallery[position].alt || name;
            },
        };
    }

    function thumbStrip(gallery, name, select) {
        if (gallery.length < 2) return null;

        return h('div', { class: 'rc-thumbs' }, gallery.map((image, position) => h('button', {
            type: 'button',
            'aria-label': `${name} ${position + 1}`,
            'aria-current': position === 0 ? 'true' : null,
            onClick: (event) => {
                select(position);
                event.currentTarget.parentElement.querySelectorAll('button').forEach((other) => other.toggleAttribute('aria-current', other === event.currentTarget));
            },
        }, h('img', { src: image.url, alt: '', loading: 'lazy' }))));
    }

    /** A facility's photos (tap to enlarge), when it is open, and a way to its own page. */
    function facilityCard(facility, index) {
        const gallery = (facility.images?.length ? facility.images : [facility.image_url]).map((url) => ({ url }));
        const { frame, select } = photoFrame(gallery, facility.title);
        const href = config.facilityUrlTemplate.replace('__ID__', encodeURIComponent(facility.id));

        return h('article', { class: 'rc rc-facility', style: `--i:${index}`, role: 'listitem' },
            frame,
            h('div', { class: 'rc-body' },
                h('h4', { class: 'rc-name', text: facility.title }),
                facility.hours ? h('p', { class: 'rc-fact', text: `${labels.hours}: ${facility.hours}` }) : null,
                h('p', { class: 'rc-desc rc-clamp', text: facility.body }),
                thumbStrip(gallery, facility.title, select),
                h('div', { class: 'rc-actions' },
                    h('a', { class: 'rc-btn rc-btn-primary', href, text: labels.viewDetails, onClick: (event) => openPage(event, frame.querySelector('img')) }))));
    }

    /** One photo of a service, labelled with which service it is. */
    function servicePhotoCard(photo, index, all) {
        const { frame } = photoFrame([{ url: photo.url, alt: photo.alt }], photo.service_name, {
            items: all.map((item) => ({ url: item.url, alt: item.alt, caption: item.service_name })),
            start: index,
        });
        const href = config.serviceUrlTemplate.replace('__SLUG__', encodeURIComponent(photo.service_type_slug));

        return h('article', { class: 'rc rc-facility', style: `--i:${index}`, role: 'listitem' },
            frame,
            h('div', { class: 'rc-body' },
                h('h4', { class: 'rc-name', text: photo.service_name }),
                photo.tags?.length ? h('ul', { class: 'rc-chips' }, photo.tags.slice(0, 3).map((tag) => h('li', { text: inclusion(tag) }))) : null,
                h('div', { class: 'rc-actions' },
                    h('a', { class: 'rc-btn rc-btn-primary', href, text: labels.viewDetails, onClick: (event) => openPage(event, frame.querySelector('img')) }))));
    }

    /** A restaurant: photos, opening hours and the menu the workshop listed. */
    function diningCard(entry, index) {
        const gallery = entry.images.map((url) => ({ url }));
        const { frame, select } = photoFrame(gallery, entry.title);
        const href = config.facilityUrlTemplate.replace('__ID__', encodeURIComponent(entry.id));

        return h('article', { class: 'rc rc-facility', style: `--i:${index}`, role: 'listitem' },
            gallery.length ? frame : null,
            h('div', { class: 'rc-body' },
                h('h4', { class: 'rc-name', text: entry.title }),
                entry.hours ? h('p', { class: 'rc-fact', text: `${labels.hours}: ${entry.hours}` }) : null,
                h('p', { class: 'rc-desc', text: entry.body }),
                entry.menu.length ? h('ul', { class: 'rc-menu' }, entry.menu.map((dish) => h('li', {},
                    h('span', { class: 'rc-menu-name', text: dish.name }),
                    dish.price ? h('span', { class: 'rc-menu-price', text: dish.price }) : null,
                    dish.description ? h('span', { class: 'rc-menu-desc', text: dish.description }) : null))) : null,
                thumbStrip(gallery, entry.title, select),
                h('div', { class: 'rc-actions' },
                    h('a', { class: 'rc-btn rc-btn-primary', href, text: labels.viewDetails, onClick: (event) => openPage(event, null) }))));
    }

    /** The workshop's address with buttons that open the map, and how to get there. */
    function locationCard(location) {
        return h('article', { class: 'rc rc-location', role: 'group', 'aria-label': location.name },
            h('div', { class: 'rc-body' },
                h('h4', { class: 'rc-name', text: location.name }),
                h('p', { class: 'rc-desc', text: location.address }),
                location.phone ? h('a', { class: 'rc-fact', href: `tel:${location.phone.replace(/[^+\d]/g, '')}`, text: location.phone }) : null,
                location.getting_there?.length ? h('div', { class: 'rc-route' },
                    h('p', { class: 'rc-label', text: labels.gettingThere }),
                    h('ul', {}, location.getting_there.map((way) => h('li', {}, h('strong', { text: way.title }), h('span', { text: way.body }))))) : null,
                h('div', { class: 'rc-actions' },
                    h('a', { class: 'rc-btn', href: location.maps_url, target: '_blank', rel: 'noopener', text: labels.openMap }),
                    h('a', { class: 'rc-btn rc-btn-primary', href: location.directions_url, target: '_blank', rel: 'noopener', text: labels.directions }))));
    }

    function rail(items, build) {
        const list = h('div', { class: 'rc-rail', tabindex: items.length > 2 ? 0 : null, role: 'list' }, items.map(build));

        if (items.length < 3) return list;

        const arrow = (direction, symbol) => h('button', {
            type: 'button',
            class: `rc-arrow is-${direction}`,
            tabindex: -1,
            'aria-hidden': 'true',
            text: symbol,
            onClick: () => list.scrollBy({ left: (direction === 'next' ? 1 : -1) * list.clientWidth * 0.8, behavior: 'smooth' }),
        });

        return h('div', { class: 'rc-rail-wrap' }, list, arrow('prev', '‹'), arrow('next', '›'));
    }

    function serviceRail(items, visit) {
        return rail(items, (service, index) => serviceCard(service, { index, visit: visit && { ...visit, days: service.days } }));
    }

    function availabilityCard(payload) {
        if (!payload.available) {
            return h('div', { class: 'rc-note', text: labels.noAvailability });
        }

        const quote = payload.quote;
        const service = payload.service || { service_type_slug: quote.service_type_slug, name: quote.name };
        services.set(service.service_type_slug, service);

        return h('div', { class: 'rc-note rc-avail' },
            h('p', { class: 'rc-name', text: quote.name }),
            h('p', { class: 'rc-meta', text: `${day(quote.drop_off_date)} → ${day(quote.pickup_date)} · ${quote.days} ${labels.day}` }),
            h('p', { class: 'rc-price' }, money(quote.grand_total), quote.member_discount_percent > 0 ? h('span', { text: ` ${wizard.member_discount} ${quote.member_discount_percent}%` }) : null),
            h('button', {
                type: 'button',
                class: 'rc-btn rc-btn-primary',
                text: labels.bookNow,
                onClick: () => openForm({ service, values: { drop_off_date: quote.drop_off_date, pickup_date: quote.pickup_date } }),
            }));
    }

    function confirmationCard(payload) {
        const booking = payload.booking;
        const whatsapp = payload.handover?.whatsapp_url;

        return h('div', { class: 'rc-done' },
            h('div', { class: 'rc-done-head' },
                h('span', { class: 'rc-done-check' }, svgCheck()),
                h('div', {},
                    h('p', { class: 'rc-done-title', text: labels.bookingReceived }),
                    h('p', { class: 'rc-done-sub', text: wizard.awaiting }))),
            h('dl', { class: 'rc-done-rows' },
                h('div', {}, h('dt', { text: wizard.reference }), h('dd', { class: 'rc-ref', text: booking.booking_reference })),
                h('div', {}, h('dt', { text: wizard.service }), h('dd', { text: booking.name })),
                h('div', {}, h('dt', { text: wizard.dates }), h('dd', { text: `${day(booking.drop_off_date)} → ${day(booking.pickup_date)} · ${booking.days} ${labels.day}` })),
                h('div', {}, h('dt', { text: wizard.estimated_total }), h('dd', { class: 'rc-total', text: money(booking.total_price) }))),
            whatsapp ? h('a', { class: 'rc-btn rc-btn-primary rc-btn-wide', href: whatsapp, target: '_blank', rel: 'noopener', text: wizard.send_whatsapp }) : null,
            h('div', { class: 'rc-done-actions' },
                h('button', { type: 'button', class: 'rc-btn', text: labels.addCalendar, onClick: () => saveToCalendar(booking) }),
                h('button', { type: 'button', class: 'rc-btn', text: labels.share, onClick: () => shareVisit(booking) })));
    }

    function saveToCalendar(booking) {
        const link = h('a', { href: URL.createObjectURL(calendarFile(booking, config.workshopName)), download: `${booking.booking_reference}.ics` });
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 4000);
    }

    /** The system share sheet where there is one; WhatsApp otherwise. */
    function shareVisit(booking) {
        const message = labels.shareText
            .replace(':workshop', config.workshopName)
            .replace(':service', booking.name)
            .replace(':in', day(booking.drop_off_date))
            .replace(':out', day(booking.pickup_date))
            .replace(':ref', booking.booking_reference);

        if (navigator.share) navigator.share({ text: message }).catch(() => {});
        else window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank', 'noopener');
    }

    /**
     * The reservation form for a service. One that is still unfinished is handed
     * back as it was (with what the customer already typed) instead of a second.
     */
    function formFor({ service, values = {} }) {
        const existing = openForms.get(service.service_type_slug);

        if (existing && !existing.dataset.done) return existing;

        services.set(service.service_type_slug, service);
        const form = bookingForm({ service, values });
        openForms.set(service.service_type_slug, form);

        return form;
    }

    /** Opens the form in the showcase, with a way back to what was being shown. */
    function openForm(details) {
        ctx.present(formFor(details), { back: true });
    }

    function bookingForm({ service, values = {} }) {
        const state = {
            drop_off_date: values.drop_off_date || '',
            pickup_date: values.pickup_date || '',
            vehicle_model: values.vehicle_model || '',
            license_plate: values.license_plate || '',
            customer_name: values.customer_name || config.memberName || '',
            contact_type: 'whatsapp',
            contact_value: values.contact_value || config.memberPhone || '',
        };
        const fields = {};
        let quoteTicket = 0;
        let quoteTimer = null;
        let submitting = false;

        const root = h('form', { class: 'rf', novalidate: true, 'aria-label': text(labels.formTitle, { service: service.name }) });
        const summary = h('p', { class: 'rf-summary', 'aria-live': 'polite' });
        const alternatives = h('div', { class: 'rf-alts' });
        const formError = h('p', { class: 'rf-error', role: 'alert', hidden: true });
        const submit = h('button', { type: 'submit', class: 'rc-btn rc-btn-primary rc-btn-wide', text: labels.formConfirm });

        function field(name, label, input) {
            const message = h('span', { class: 'rf-msg', role: 'alert' });
            fields[name] = { input, message };

            return h('label', { class: 'rf-field' }, h('span', { class: 'rf-label', text: label }), input, message);
        }

        function markInvalid(name, message) {
            const target = fields[name];
            if (!target) return false;

            target.input.classList.add('is-invalid');
            target.message.textContent = message;

            return true;
        }

        function clearErrors() {
            Object.values(fields).forEach(({ input, message }) => {
                input.classList.remove('is-invalid');
                message.textContent = '';
            });
            formError.hidden = true;
            formError.textContent = '';
        }

        const dropOffDate = h('input', { type: 'date', name: 'drop_off_date', min: config.today, value: state.drop_off_date, required: true });
        const pickupDate = h('input', { type: 'date', name: 'pickup_date', min: config.today, value: state.pickup_date });
        const vehicleInput = h('input', { type: 'text', name: 'vehicle_model', maxLength: 100, placeholder: wizard.vehicle_model_placeholder, value: state.vehicle_model, required: true });
        const plateInput = h('input', { type: 'text', name: 'license_plate', maxLength: 20, placeholder: 'B 1234 XYZ', value: state.license_plate, required: true });
        const nameInput = h('input', { type: 'text', name: 'customer_name', autocomplete: 'name', maxLength: 100, value: state.customer_name, required: true });
        const contactInput = h('input', { type: 'tel', name: 'contact_value', autocomplete: 'tel', maxLength: 120, value: state.contact_value, required: true });

        const contactChips = h('div', { class: 'rf-chips', role: 'group', 'aria-label': wizard.contact_method },
            CONTACT_TYPES.map((type) => h('button', {
                type: 'button',
                'data-contact': type,
                'aria-pressed': String(type === state.contact_type),
                text: wizard[type],
                onClick: () => {
                    state.contact_type = type;
                    contactInput.type = type === 'email' ? 'email' : 'tel';
                    contactInput.autocomplete = type === 'email' ? 'email' : 'tel';
                    contactChips.querySelectorAll('button').forEach((chip) => chip.setAttribute('aria-pressed', String(chip.dataset.contact === type)));
                },
            })));

        dropOffDate.addEventListener('input', () => {
            state.drop_off_date = dropOffDate.value;
            pickupDate.min = dropOffDate.value || config.today;
            if (pickupDate.value && dropOffDate.value && pickupDate.value < dropOffDate.value) {
                pickupDate.value = dropOffDate.value;
                state.pickup_date = pickupDate.value;
            }
            scheduleQuote();
        });
        pickupDate.addEventListener('input', () => {
            state.pickup_date = pickupDate.value;
            scheduleQuote();
        });
        vehicleInput.addEventListener('input', () => (state.vehicle_model = vehicleInput.value));
        plateInput.addEventListener('input', () => (state.license_plate = plateInput.value));
        nameInput.addEventListener('input', () => (state.customer_name = nameInput.value));
        contactInput.addEventListener('input', () => (state.contact_value = contactInput.value));
        if (state.drop_off_date) pickupDate.min = state.drop_off_date;

        const thumb = photos(service)[0];
        root.append(
            h('header', { class: 'rf-head' },
                thumb ? h('img', { src: thumb.url, alt: '', loading: 'lazy' }) : h('span', { class: 'rc-photo-empty', 'aria-hidden': 'true' }),
                h('div', {},
                    h('p', { class: 'rf-title', text: text(labels.formTitle, { service: service.name }) }),
                    service.starting_price != null ? h('p', { class: 'rf-sub' }, [labels.from, money(service.starting_price), labels.priceSuffix].filter(Boolean).join(' ')) : null)),
            h('div', { class: 'rf-grid' },
                field('drop_off_date', wizard.drop_off_date, dropOffDate),
                field('pickup_date', wizard.pickup_date, pickupDate)),
            h('div', { class: 'rf-grid' },
                field('vehicle_model', wizard.vehicle_model, vehicleInput),
                field('license_plate', wizard.license_plate, plateInput)),
            field('customer_name', wizard.name, nameInput),
            h('div', { class: 'rf-field' },
                h('span', { class: 'rf-label', text: wizard.contact_method }),
                contactChips,
                field('contact_value', wizard.contact_value, contactInput)),
            summary,
            alternatives,
            formError,
            submit,
            h('p', { class: 'rf-disclaimer', text: wizard.disclaimer }),
        );

        function datesError() {
            if (!state.drop_off_date || state.drop_off_date < config.today) return ['drop_off_date', wizard.drop_off_past];
            if (state.pickup_date && state.pickup_date < state.drop_off_date) return ['pickup_date', wizard.pickup_after];
            if (daysBetween(state.drop_off_date, state.pickup_date || state.drop_off_date) > config.maxDays) return ['pickup_date', text(wizard.too_long, { max: config.maxDays })];

            return null;
        }

        function visitBody() {
            return { service_type_slug: service.service_type_slug, drop_off_date: state.drop_off_date, pickup_date: state.pickup_date || state.drop_off_date, vehicles: 1, locale: config.locale };
        }

        function scheduleQuote() {
            clearTimeout(quoteTimer);
            alternatives.replaceChildren();
            quoteTicket++;

            if (datesError()) {
                summary.textContent = '';

                return;
            }

            summary.textContent = wizard.checking;
            quoteTimer = setTimeout(refreshQuote, 350);
        }

        async function refreshQuote() {
            const ticket = ++quoteTicket;

            try {
                const data = await api(config.quoteUrl, visitBody());
                if (ticket !== quoteTicket) return;

                const discount = data.member_discount_percent > 0 ? ` · ${wizard.member_discount} ${data.member_discount_percent}%` : '';
                summary.textContent = `${wizard.estimated_total}: ${money(data.grand_total)} · ${data.days} ${labels.day}${discount}`;
            } catch (error) {
                if (ticket !== quoteTicket) return;

                summary.textContent = '';
                showFailure(error);
            }
        }

        function showFailure(error) {
            const payload = error.payload || {};
            const failures = Object.entries(payload.errors || {});

            const say = (message) => {
                formError.textContent = message;
                formError.hidden = false;
            };

            failures.forEach(([name, messages]) => {
                if (name === 'service_type_slug' || !markInvalid(name, messages[0])) say(messages[0]);
            });

            if (!failures.length) {
                say(error.status === 429 ? labels.slow : error.status === 0 || error.status === 408 ? wizard.error_network : payload.message || wizard.error_generic);
            }

            if (payload.alternatives?.length) {
                alternatives.replaceChildren(
                    h('p', { class: 'rf-alts-title', text: wizard.available_instead }),
                    h('div', { class: 'rf-chips' }, payload.alternatives.map((alt) => h('button', {
                        type: 'button',
                        text: `${alt.name} · ${money(alt.total)}`,
                        onClick: () => {
                            const known = services.get(alt.slug);
                            if (known) openForm({ service: known, values: { ...state } });
                            else ctx.ask(text(labels.serviceDetailsQuestion, { service: alt.name }));
                        },
                    }))));
            }
        }

        function validate() {
            const dates = datesError();
            if (dates) return dates;
            if (!state.vehicle_model.trim()) return ['vehicle_model', wizard.invalid];
            if (!state.license_plate.trim()) return ['license_plate', wizard.invalid];
            if (!state.customer_name.trim()) return ['customer_name', wizard.invalid];

            const contact = state.contact_value.trim();
            const valid = state.contact_type === 'email' ? EMAIL_PATTERN.test(contact) : PHONE_PATTERN.test(contact);

            return valid ? null : ['contact_value', state.contact_type === 'email' ? wizard.contact_email : wizard.contact_phone];
        }

        root.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submitting) return;

            clearErrors();
            const problem = validate();

            if (problem) {
                markInvalid(problem[0], problem[1]);
                fields[problem[0]].input.focus();

                return;
            }

            submitting = true;
            submit.disabled = true;
            submit.textContent = wizard.sending;
            clearTimeout(quoteTimer);

            try {
                const data = await api(config.reserveUrl, {
                    ...visitBody(),
                    customer_name: state.customer_name.trim(),
                    contact_type: state.contact_type,
                    contact_value: state.contact_value.trim(),
                    customer_token: localStorage.getItem(config.storageKey) || undefined,
                    via_chat: true,
                });

                const confirmation = data.confirmation || {
                    type: 'booking_confirmation',
                    booking: {
                        booking_reference: data.reference,
                        name: service.name,
                        drop_off_date: state.drop_off_date,
                        pickup_date: state.pickup_date,
                        days: daysBetween(state.drop_off_date, state.pickup_date || state.drop_off_date),
                        total_price: data.total,
                    },
                    handover: data.handover,
                };

                root.dataset.done = 'true';
                root.className = 'rf-done';
                root.replaceChildren(confirmationCard(confirmation));
                ctx.append(confirmationCard(confirmation));
                ctx.celebrate();
                haptic(DONE);
                window.workshopSound?.play('incoming');
                if (data.confirmation) ctx.recorded();
            } catch (error) {
                showFailure(error);
                submitting = false;
                submit.disabled = false;
                submit.textContent = labels.formConfirm;
            }
        });

        if (!datesError()) {
            summary.textContent = wizard.checking;
            refreshQuote();
        }

        return root;
    }

    /**
     * What the advisor puts in the showcase in the middle of the screen: the
     * services, one service, or a reservation form. `recap` is the one line the
     * transcript keeps, and `build` makes the content afresh each time it is shown.
     *
     * @param {object} payload  one entry of a message's ui_payload
     * @returns {{recap: string, build: () => Element}|null}
     */
    function describe(payload) {
        switch (payload.type) {
            case 'service_results':
                return {
                    recap: text(labels.showingServices, { count: payload.services.length }),
                    build: () => serviceRail(payload.services, payload.drop_off_date ? { drop_off_date: payload.drop_off_date, pickup_date: payload.pickup_date } : null),
                };
            case 'location':
                return { recap: payload.location.name, build: () => locationCard(payload.location) };
            case 'service_photos':
                return {
                    recap: text(labels.showingPhotos, { count: payload.photos.length }),
                    build: () => rail(payload.photos, servicePhotoCard),
                };
            case 'price_list':
                return { recap: payload.entries[0].title, build: () => rail(payload.entries, diningCard) };
            case 'facility_results':
                return {
                    recap: text(labels.showingFacilities, { count: payload.facilities.length }),
                    build: () => rail(payload.facilities, facilityCard),
                };
            case 'service_detail':
                return { recap: payload.service.name, build: () => serviceCard(payload.service, { expanded: true }) };
            case 'visit_picker':
                if (payload.service) services.set(payload.service.service_type_slug, payload.service);

                return { recap: labels.pickerTitle, build: () => ctx.picker({ service: payload.service, values: payload.values }) };
            case 'booking_form':
                services.set(payload.service.service_type_slug, payload.service);

                return { recap: text(labels.formTitle, { service: payload.service.name }), build: () => formFor(payload) };
            default:
                return null;
        }
    }

    /**
     * What stays in the transcript itself: the smaller cards.
     *
     * @param {object} payload  one entry of a message's ui_payload
     * @returns {Element|null}
     */
    function render(payload) {
        switch (payload.type) {
            case 'availability':
                return availabilityCard(payload);
            case 'booking_confirmation':
                return confirmationCard(payload);
            default:
                return null;
        }
    }

    /** Placeholder cards that shimmer while the advisor is still finding the services. */
    function skeleton() {
        return h('div', { class: 'rc-rail', 'aria-hidden': 'true' }, [0, 1].map((index) => h('article', { class: 'rc rc-skeleton', style: `--i:${index}` },
            h('div', { class: 'rc-photo' }),
            h('div', { class: 'rc-body' },
                h('span', { class: 'rc-line is-wide' }),
                h('span', { class: 'rc-line is-mid' }),
                h('span', { class: 'rc-line is-short' }),
                h('div', { class: 'rc-actions' }, h('span', { class: 'rc-pill' }), h('span', { class: 'rc-pill' }))))));
    }

    return { describe, render, skeleton };
}
