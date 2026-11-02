/**
 * Small UI sounds, synthesised with the Web Audio API (no audio files), all
 * borrowed from the car: a relay tick for every tap and for sending, the
 * tick-tock of a turn signal for an incoming reply, and the soft two-note
 * door chime when a page opens. Sound only ever follows a customer action,
 * never plays on its own, and can be muted with the header toggle (remembered
 * per browser).
 */
const STORAGE_KEY = 'workshop_sound';

let context = null;
let master = null;

function isEnabled() {
    try {
        return localStorage.getItem(STORAGE_KEY) !== 'off';
    } catch {
        return true;
    }
}

function ensureContext() {
    if (!context) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return null;

        context = new AudioContextClass();
        master = context.createGain();
        master.gain.value = 0.6;
        master.connect(context.destination);
    }

    if (context.state === 'suspended') context.resume().catch(() => {});

    return context;
}

function tone(frequency, start, duration, gain) {
    const oscillator = context.createOscillator();
    const amp = context.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.value = frequency;
    amp.gain.setValueAtTime(0.0001, start);
    amp.gain.exponentialRampToValueAtTime(gain, start + 0.012);
    amp.gain.exponentialRampToValueAtTime(0.0001, start + duration);

    oscillator.connect(amp).connect(master);
    oscillator.start(start);
    oscillator.stop(start + duration + 0.05);
}

let noise = null;

function noiseBuffer() {
    if (!noise) {
        noise = context.createBuffer(1, Math.floor(context.sampleRate * 0.06), context.sampleRate);
        const data = noise.getChannelData(0);
        for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
    }

    return noise;
}

/** A relay contact closing: a few milliseconds of band-passed noise over a short low knock. */
function relay(start, frequency, gain, knock) {
    const source = context.createBufferSource();
    const filter = context.createBiquadFilter();
    const amp = context.createGain();

    source.buffer = noiseBuffer();
    filter.type = 'bandpass';
    filter.frequency.value = frequency;
    filter.Q.value = 5;
    amp.gain.setValueAtTime(gain * 3, start);
    amp.gain.exponentialRampToValueAtTime(0.0001, start + 0.035);

    source.connect(filter).connect(amp).connect(master);
    source.start(start);
    source.stop(start + 0.06);
    tone(knock, start, 0.05, gain * 0.8);
}

/** One note of the door chime: a soft sine with a quiet octave above it. */
function chime(frequency, start, gain, duration) {
    tone(frequency, start, duration, gain);
    tone(frequency * 2, start, duration * 0.5, gain * 0.18);
}

const sounds = {
    enter(now) {
        relay(now, 2200, 0.2, 140);
        chime(880, now + 0.05, 0.1, 0.32);
        chime(659.25, now + 0.3, 0.09, 0.5);
    },
    incoming(now) {
        relay(now, 1900, 0.22, 170);
        relay(now + 0.12, 1300, 0.2, 120);
    },
    sent(now) {
        relay(now, 2000, 0.16, 150);
    },
    click(now) {
        relay(now, 2400, 0.1, 180);
    },
};

function play(name) {
    if (!isEnabled() || !sounds[name]) return;

    try {
        const audio = ensureContext();
        if (audio) sounds[name](audio.currentTime + 0.02);
    } catch {
        /* audio unavailable: the interface works the same without it */
    }
}

function syncToggles() {
    const enabled = isEnabled();

    document.querySelectorAll('[data-sound-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(enabled));
        button.setAttribute('aria-label', enabled ? button.dataset.labelOn : button.dataset.labelOff);
        button.classList.toggle('is-muted', !enabled);
    });
}

function initSoundToggle() {
    syncToggles();

    document.querySelectorAll('[data-sound-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            try {
                localStorage.setItem(STORAGE_KEY, isEnabled() ? 'off' : 'on');
            } catch { /* preference just won't persist */ }

            syncToggles();
            play('sent');
        });
    });
}

/** Every tap on a control gets the relay tick; the toggle and the chat's own sounds speak for themselves. */
function initClickTick() {
    document.addEventListener('click', (event) => {
        const control = event.target.closest?.('button, a[href], summary, [role="tab"]');

        if (!control || control.matches('[data-sound-toggle], [data-chat-submit], [disabled]')) return;

        play('click');
    });
}

window.workshopSound = { play, isEnabled };

document.addEventListener('DOMContentLoaded', () => {
    initSoundToggle();
    initClickTick();
});
