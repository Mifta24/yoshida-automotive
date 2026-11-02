/**
 * Full-screen stage chrome shared by the opening screen and the garage:
 * hides the "preparing" loader once the hero image is ready, and eases the
 * scene out before following an "enter" link so moving between screens feels
 * like walking through the garage rather than loading a page.
 *
 * A photo can travel with the customer: when the link or card they tap has a service
 * photo, it is named for the browser's view transition so that, on the service
 * page, it grows into the big photo instead of the page simply changing.
 *
 * A link may carry `data-topic`: the question the customer picked from the menu.
 * It is handed to the AI advisor, which raises it once the next scene is up
 * (or straight away when the customer is already in that scene).
 */
const TOPIC_KEY = 'advisor_pending_topic';

function initStage() {
    const stage = document.querySelector('.stage');
    if (!stage) return;

    const loader = stage.querySelector('[data-stage-loader]');
    const image = stage.querySelector('.stage-image');
    const tour = stage.querySelector('[data-stage-tour]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const showScene = () => loader?.classList.add('is-ready');

    if (!image || image.complete) {
        showScene();
    } else {
        image.addEventListener('load', showScene, { once: true });
        image.addEventListener('error', showScene, { once: true });
    }

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            leaving = false;
            stage.classList.remove('is-leaving', 'is-morphing');
            document.querySelectorAll('[data-morph]').forEach((photo) => {
                photo.style.viewTransitionName = '';
                photo.removeAttribute('data-morph');
            });
            tour?.classList.remove('is-visible');
        }
    });

    let leaving = false;

    /** Ease the stage out, then walk the customer over to `href`. */
    function leave(href, line = null, topic = null, { photo = null } = {}) {
        if (leaving) return;
        leaving = true;

        if (photo) {
            photo.style.viewTransitionName = 'service-photo';
            photo.setAttribute('data-morph', '');
            stage.classList.add('is-morphing');
        }

        try {
            if (topic) sessionStorage.setItem(TOPIC_KEY, topic);
            else sessionStorage.removeItem(TOPIC_KEY);
        } catch { /* private mode: the scene still changes, only the topic is dropped */ }

        // The advisor says where she is taking the customer, then the scene
        // walks forward into the next one.
        if (line && tour) {
            tour.textContent = line;
            tour.classList.add('is-visible');
        }

        stage.classList.add('is-leaving');

        const chime = window.workshopSound?.isEnabled() ?? false;
        if (chime) window.workshopSound.play('enter');

        const hold = reducedMotion ? 0 : line ? 820 : chime ? 560 : 320;
        window.setTimeout(() => { window.location.href = href; }, hold);
    }

    document.querySelectorAll('[data-stage-exit]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const modified = event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
            if (event.defaultPrevented || modified || link.target === '_blank') return;

            event.preventDefault();

            // Already in this scene: no walk needed, the advisor just takes
            // the topic up where the customer is standing.
            const samePlace = link.pathname === window.location.pathname;
            if (samePlace && link.dataset.topic) {
                window.dispatchEvent(new CustomEvent('advisor:ask', { detail: { message: link.dataset.topic } }));
                return;
            }
            if (samePlace) return;

            const photo = /\/services\/[^/]+$/.test(link.pathname) ? link.querySelector('img') : null;
            leave(link.href, link.dataset.tourLine, link.dataset.topic, { photo });
        });
    });

    window.workshopStage = { leave };
}

/**
 * The big service photo only carries a transition name while the page is being
 * revealed. Left on permanently, the browser paints it outside the scrolling
 * panel instead of clipping it to the panel's edge.
 */
window.addEventListener('pagereveal', (event) => {
    const photo = document.querySelector('[data-service-photo]');
    if (!photo || !event.viewTransition) return;

    photo.style.viewTransitionName = 'service-photo';
    event.viewTransition.finished.finally(() => { photo.style.viewTransitionName = ''; });
});

/** The topic the customer picked on the previous scene, if any (read once). */
window.takePendingAdvisorTopic = () => {
    try {
        const topic = sessionStorage.getItem(TOPIC_KEY);
        sessionStorage.removeItem(TOPIC_KEY);
        return topic;
    } catch {
        return null;
    }
};

document.addEventListener('DOMContentLoaded', initStage);
