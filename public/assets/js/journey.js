// Parcours de vol commun aux fiches et à l’accueil (docs/accueil-parcours.md).
(() => {
    const { reduceMotion, clamp, addScrollTask, requestFrame } = window.Taysir;

    // Les coordonnées de la route et de ses stations sont recalculées quand la mise en page change.
    const journey = document.querySelector('[data-journey-section]');
    if (journey) {
        const route = journey.querySelector('[data-journey]');
        const steps = [...journey.querySelectorAll('[data-step]')];
        const guide = route.querySelector('.route-map-guide');
        const trace = route.querySelector('.route-map-progress');
        const plane = route.querySelector('.route-plane');
        const counter = journey.querySelector('[data-journey-count]');
        const counterBox = counter.closest('.hud-count');
        let shown = 1;
        let stations = [];
        let samples = [];
        let routeHeight = 0;

        const setCount = (value) => {
            if (value === shown) {
                return;
            }
            shown = value;
            counter.textContent = String(value).padStart(2, '0');
            counterBox.classList.remove('is-flip');
            void counterBox.offsetWidth;
            counterBox.classList.add('is-flip');
        };

        const buildRoute = () => {
            routeHeight = route.clientHeight;
            if (!steps.length || !routeHeight) {
                return;
            }
            const firstNode = steps[0].querySelector('.route-node');
            const x = firstNode.offsetLeft + firstNode.offsetWidth / 2;
            const clearance = plane.offsetWidth * .55 + 3;
            const endInset = 12;
            let leftEdge = Math.min(0, 4 - route.getBoundingClientRect().left);
            let rightEdge = route.clientWidth;

            stations = steps.map((step) => {
                const node = step.querySelector('.route-node');
                const card = step.querySelector('.pass');
                const right = card.offsetLeft + card.offsetWidth;
                if (right <= x) {
                    leftEdge = Math.max(leftEdge, right);
                } else if (card.offsetLeft >= x) {
                    rightEdge = Math.min(rightEdge, card.offsetLeft);
                }
                return step.offsetTop + node.offsetTop + node.offsetHeight / 2;
            });

            const leftBend = Math.min(92, Math.max(0, x - leftEdge - clearance) / .75);
            const rightBend = Math.min(92, Math.max(0, rightEdge - x - clearance) / .75);
            const points = [endInset, ...stations, routeHeight - endInset];
            let d = `M ${x} ${points[0]}`;
            for (let i = 1; i < points.length; i++) {
                const from = points[i - 1];
                const to = points[i];
                const bend = x + (i % 2 ? -leftBend : rightBend);
                const lead = (to - from) * .28;
                d += ` C ${bend} ${from + lead}, ${bend} ${to - lead}, ${x} ${to}`;
            }
            guide.setAttribute('d', d);
            trace.setAttribute('d', d);

            // Échantillons à distance régulière pour suivre la courbe sans lecture SVG au défilement.
            const length = guide.getTotalLength();
            const segments = Math.max(1, Math.ceil(length / 8));
            samples = Array.from({ length: segments + 1 }, (_, i) => guide.getPointAtLength(length * i / segments));
            plane.classList.add('is-positioned');
            requestFrame();
        };

        addScrollTask((viewport) => {
            if (samples.length < 2) {
                return;
            }
            const target = reduceMotion.matches
                ? routeHeight
                : viewport * .55 - route.getBoundingClientRect().top;
            const y = Math.max(samples[0].y, Math.min(target, samples[samples.length - 1].y));
            let low = 0;
            let high = samples.length - 1;
            while (high - low > 1) {
                const middle = (low + high) >> 1;
                if (samples[middle].y <= y) {
                    low = middle;
                } else {
                    high = middle;
                }
            }
            const start = samples[low];
            const end = samples[high];
            const ratio = clamp((y - start.y) / (end.y - start.y));
            const x = start.x + (end.x - start.x) * ratio;
            const before = samples[Math.max(0, low - 1)];
            const after = samples[Math.min(samples.length - 1, high + 1)];
            const angle = Math.atan2(after.y - before.y, after.x - before.x) + Math.PI / 2;
            plane.style.transform = `translate3d(${x}px, ${y}px, 0) translate(-50%, -50%) rotate(${angle}rad)`;

            const progress = (low + ratio) / (samples.length - 1);
            route.style.setProperty('--route-offset', (1 - progress).toFixed(4));
            journey.style.setProperty('--jp', progress.toFixed(4));

            let active = 0;
            steps.forEach((step, index) => {
                const reached = target >= stations[index];
                step.classList.toggle('is-active', reached);
                active += reached ? 1 : 0;
            });
            setCount(Math.max(1, active));
        });

        const routeObserver = new ResizeObserver(buildRoute);
        routeObserver.observe(route);
        steps.forEach((step) => routeObserver.observe(step));
        reduceMotion.addEventListener('change', requestFrame);
    }
})();
