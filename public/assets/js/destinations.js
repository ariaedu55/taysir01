// Annuaire : le filtre agit sur les cartes. Le radar présente tous les trajets.
(() => {
    const controls = document.querySelector('[data-region-controls]');
    if (!controls) {
        return;
    }
    const select = controls.querySelector('select');
    const count = controls.querySelector('[data-region-count]');
    const cards = [...document.querySelectorAll('.destinations-list [data-country-region]')];

    const filter = () => {
        let visible = 0;
        cards.forEach((card) => {
            card.hidden = select.value !== '' && card.dataset.countryRegion !== select.value;
            visible += Number(!card.hidden);
        });
        count.textContent = `${visible} pays affiché${visible === 1 ? '' : 's'}`;
    };

    controls.hidden = false;
    select.addEventListener('change', filter);
    window.addEventListener('pageshow', filter);
    filter();
})();

// Radar « depuis Dakar ».
// Toutes les 2,6 s, une destination s'allume : son arc devient rouge, son nom s'affiche, un petit avion le parcourt.
// Survoler ou atteindre au clavier une carte d'embarquement allume sa destination et suspend le défilement.
(() => {
    const radar = document.querySelector('[data-radar]');
    if (!radar) {
        return;
    }
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const items = [...radar.querySelectorAll('[data-radar-item]')];
    const plane = radar.querySelector('.radar-plane');
    const STEP_MS = 2600;
    const FLIGHT_MS = 1300;
    let current = -1;
    let timer = null;
    let firstTimer = null;
    let resumeTimer = null;
    let frame = 0;
    let inView = false;
    let held = false;

    const fly = (item) => {
        cancelAnimationFrame(frame);
        const path = item.querySelector('.radar-arc');
        const length = path.getTotalLength();
        if (reduceMotion.matches || !inView || document.hidden || !length) {
            radar.classList.remove('is-flying');
            return;
        }
        const start = performance.now();
        radar.classList.add('is-flying');
        const step = (now) => {
            const t = Math.min(1, (now - start) / FLIGHT_MS);
            const eased = t < .5 ? 2 * t * t : 1 - (-2 * t + 2) ** 2 / 2;
            const at = length * eased;
            const p = path.getPointAtLength(at);
            const q = path.getPointAtLength(Math.min(length, at + 1));
            const angle = Math.atan2(q.y - p.y, q.x - p.x) * 180 / Math.PI;
            plane.setAttribute('transform', `translate(${p.x.toFixed(1)} ${p.y.toFixed(1)}) rotate(${angle.toFixed(1)})`);
            if (t < 1) {
                frame = requestAnimationFrame(step);
            } else {
                radar.classList.remove('is-flying');
            }
        };
        frame = requestAnimationFrame(step);
    };

    const activate = (index) => {
        if (index === current) {
            return;
        }
        current = index;
        items.forEach((item, i) => item.classList.toggle('is-active', i === index));
        fly(items[index]);
    };

    const stop = () => {
        clearInterval(timer);
        timer = null;
    };
    const start = () => {
        stop();
        if (inView && !held && !document.hidden && !reduceMotion.matches) {
            timer = setInterval(() => activate((current + 1) % items.length), STEP_MS);
        }
    };

    // Lien avec les cartes d'embarquement
    document.querySelectorAll('[data-pass]').forEach((card) => {
        const index = items.findIndex((item) => item.dataset.radarItem === card.dataset.pass);
        if (index < 0) {
            return;
        }
        const hold = () => {
            held = true;
            clearTimeout(resumeTimer);
            stop();
            activate(index);
        };
        const release = () => {
            clearTimeout(resumeTimer);
            resumeTimer = setTimeout(() => { held = false; start(); }, 2500);
        };
        card.addEventListener('mouseenter', hold);
        card.addEventListener('focus', hold);
        card.addEventListener('mouseleave', release);
        card.addEventListener('blur', release);
    });

    const updateVisibility = () => {
        radar.classList.toggle('is-playing', inView && !document.hidden && !reduceMotion.matches);
        clearTimeout(firstTimer);
        if (inView && !document.hidden) {
            if (current < 0) {
                // Première destination après le dessin des arcs.
                firstTimer = setTimeout(() => activate(0), reduceMotion.matches ? 0 : 1100);
            }
            start();
        } else {
            stop();
            cancelAnimationFrame(frame);
            radar.classList.remove('is-flying');
        }
    };

    if ('IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            inView = entry.isIntersecting;
            updateVisibility();
        }, { threshold: 0.3 }).observe(radar);
    } else {
        activate(0);
    }
    document.addEventListener('visibilitychange', updateVisibility);
    reduceMotion.addEventListener('change', updateVisibility);
})();
