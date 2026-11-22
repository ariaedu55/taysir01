// Accueil : diaporama, parallaxe du bandeau et carrousel des destinations.
// S'appuie sur window.Taysir (app.js) pour la boucle de scroll.
(() => {
    const { reduceMotion, clamp, addScrollTask, requestFrame } = window.Taysir;

    /* ---------- Diaporama ---------- */
    const hero = document.querySelector('[data-hero]');
    if (hero) {
        const slides = [...hero.querySelectorAll('[data-slide]')];
        const infos = [...hero.querySelectorAll('[data-slide-info]')];
        const dots = [...hero.querySelectorAll('.hero-dot')];
        const playButton = hero.querySelector('[data-slide-play]');
        const announcement = hero.querySelector('[data-slide-announcement]');
        const flight = hero.querySelector('[data-hero-flight]');
        const routes = hero.querySelector('[data-routes]');
        const title = hero.querySelector('.hero-title');
        let current = 0;
        let userPaused = false;
        let inView = true;
        let flightReady = false;
        let returnArmed = false;
        let flightMode = '';
        let flightTimer;
        let openingPending = !reduceMotion.matches && !document.hidden && !location.hash && window.scrollY < 24;
        let titlePending = false;

        const controls = hero.querySelector('[data-slide-controls]');
        if (controls) {
            controls.hidden = false;
        }
        hero.querySelector('[data-slide-dots]').hidden = false;

        // Précharge les autres photos une fois la page chargée, pour des transitions sans attente.
        window.addEventListener('load', () => slides.forEach((slide) => { slide.loading = 'eager'; }), { once: true });

        // Machine à écrire : efface jusqu'au début commun (« au Canada » → « au Maroc » garde « au »), puis tape la suite.
        const typer = hero.querySelector('[data-typewriter]');
        const typerLine = hero.querySelector('[data-typewriter-line]');
        let typingRun = 0;
        const typeTo = (text) => {
            const run = ++typingRun;
            if (reduceMotion.matches) {
                typer.textContent = text;
                typerLine.classList.remove('is-typing');
                return;
            }
            typerLine.classList.add('is-typing');
            const step = () => {
                if (run !== typingRun) {
                    return;
                }
                const shown = typer.textContent;
                if (!text.startsWith(shown)) {
                    typer.textContent = shown.slice(0, -1);
                    setTimeout(step, 32);
                } else if (shown.length < text.length) {
                    typer.textContent = text.slice(0, shown.length + 1);
                    setTimeout(step, 70 + Math.random() * 40);
                } else {
                    typerLine.classList.remove('is-typing');
                }
            };
            step();
        };

        const updatePlayState = () => {
            hero.classList.toggle('is-paused', openingPending || flightMode === 'opening' || userPaused || !inView || document.hidden);
            playButton?.setAttribute('aria-label', userPaused ? 'Lancer le diaporama' : 'Mettre le diaporama en pause');
            if (reduceMotion.matches || !inView || document.hidden) {
                routes.pauseAnimations?.();
            } else {
                routes.unpauseAnimations?.();
            }
        };

        const revealTitle = () => {
            if (titlePending) {
                titlePending = false;
                typeTo(infos[current].dataset.place);
            }
        };
        const finishFlight = () => {
            clearTimeout(flightTimer);
            openingPending = false;
            flightMode = '';
            hero.classList.remove('is-flight-pending');
            hero.removeAttribute('data-flight');
            revealTitle();
            updatePlayState();
        };
        const startFlight = (mode) => {
            if (!flightReady || reduceMotion.matches || document.hidden) {
                return;
            }
            clearTimeout(flightTimer);
            openingPending = false;
            flightMode = mode;
            if (mode === 'opening') {
                titlePending = true;
                typer.textContent = '';
            }
            hero.setAttribute('data-flight', mode);
            hero.classList.remove('is-flight-pending');
            updatePlayState();
            // Secours si l’animation est interrompue par un changement de contexte.
            flightTimer = setTimeout(finishFlight, mode === 'opening' ? 3700 : 1900);
        };
        title.addEventListener('animationstart', (event) => {
            if (event.animationName === 'flight-content') {
                revealTitle();
            }
        });
        flight.addEventListener('animationend', (event) => {
            if (event.target === flight && event.animationName === 'flight-scene') {
                finishFlight();
            }
        });
        if (openingPending) {
            hero.classList.add('is-flight-pending');
            flightTimer = setTimeout(finishFlight, 470);
        }
        Promise.all([...flight.querySelectorAll('img')].map((img) => img.decode())).then(() => {
            flightReady = true;
            if (openingPending) {
                startFlight('opening');
            }
        }).catch(() => {
            if (openingPending) {
                finishFlight();
            }
        });

        const show = (index, announce = false) => {
            if (openingPending || flightMode === 'opening') {
                finishFlight();
            }
            current = (index + slides.length) % slides.length;
            slides.forEach((slide, i) => slide.classList.toggle('is-current', i === current));
            infos.forEach((info, i) => {
                const active = i === current;
                info.classList.toggle('is-current', active);
                info.inert = !active;
                info.setAttribute('aria-hidden', String(!active));
            });
            dots.forEach((dot, i) => {
                dot.classList.remove('is-current');
                dot.setAttribute('aria-pressed', String(i === current));
            });
            // Relance l'animation de la barre de progression de la ville active.
            void dots[current].offsetWidth;
            dots[current].classList.add('is-current');
            typeTo(infos[current].dataset.place);
            if (announce) {
                const info = infos[current];
                announcement.textContent = info.querySelector('h2').textContent.trim();
            }
        };

        // La barre de progression de la ville active donne le tempo : à la fin de son animation, ville suivante.
        // Sur mobile, les points sont invisibles mais toujours rendus, leur animation continue donc de tourner.
        dots.forEach((dot, i) => {
            dot.querySelector('.hero-dot-bar i').addEventListener('animationend', () => {
                if (i === current && !hero.classList.contains('is-paused')) {
                    show(current + 1);
                }
            });
            dot.addEventListener('click', () => show(i, true));
        });
        hero.querySelectorAll('[data-slide-step]').forEach((button) => {
            button.addEventListener('click', () => show(current + Number(button.dataset.slideStep), true));
        });
        playButton?.addEventListener('click', () => {
            userPaused = !userPaused;
            updatePlayState();
        });
        document.addEventListener('focusin', () => {
            if (openingPending || flightMode === 'opening') {
                finishFlight();
            }
        });
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && (openingPending || flightMode)) {
                finishFlight();
            }
            updatePlayState();
        });
        reduceMotion.addEventListener('change', () => {
            if (reduceMotion.matches) {
                finishFlight();
                typeTo(infos[current].dataset.place);
            }
            updatePlayState();
            requestFrame();
        });
        new IntersectionObserver(([entry]) => {
            const ratio = entry.intersectionRatio;
            inView = ratio >= .25;
            if (ratio <= .12 && entry.boundingClientRect.top <= 0) {
                returnArmed = true;
                if (openingPending || flightMode) {
                    finishFlight();
                }
            } else if (ratio >= .48 && returnArmed) {
                returnArmed = false;
                startFlight('return');
            }
            updatePlayState();
        }, { threshold: [0, .12, .25, .48] }).observe(hero);
        updatePlayState();

        // Parallaxe : la photo s'enfonce et le texte s'efface au scroll.
        addScrollTask(() => {
            const progress = clamp(window.scrollY / hero.offsetHeight);
            if (progress > .2 && (openingPending || flightMode === 'opening')) {
                finishFlight();
            }
            hero.style.setProperty('--s', reduceMotion.matches ? '0' : progress.toFixed(4));
        });
    }


    /* ---------- Destinations : carrousel en profondeur (docs/accueil-destinations.md) ---------- */
    // Une carte au centre, deux de chaque côté en retrait. Chaque carte reçoit sa distance circulaire à la carte
    // active : la carte de droite prend la place du centre, celle du centre part à gauche, et ainsi de suite.
    const coverflow = document.querySelector('[data-coverflow]');
    if (coverflow) {
        const section = coverflow.closest('section');
        const items = [...coverflow.querySelectorAll('[data-cover-item]')];
        const count = items.length;
        const announce = section.querySelector('[data-cover-announce]');
        const AUTOPLAY_MS = 4500;
        const RESUME_MS = 6000;
        let current = 0;
        let timer = null;
        let resumeTimer = null;
        let inView = false;

        // [décalage, échelle, luminosité, opacité] selon la distance au centre (0, 1, 2, au-delà).
        const places = [['0%', 1, 1, 1], ['var(--step-1)', .8, .78, 1], ['var(--step-2)', .64, .6, 1], ['calc(var(--step-2) * 1.25)', .5, .5, 0]];

        const render = (announceIt = false) => {
            items.forEach((item, i) => {
                let offset = (i - current) % count;
                if (offset > count / 2) offset -= count;
                if (offset < -count / 2) offset += count;
                const distance = Math.min(Math.abs(offset), 3);
                const [x, scale, light, opacity] = places[distance];
                const sign = offset < 0 ? -1 : 1;
                item.style.setProperty('--x', distance === 0 ? '0%' : `calc(${x} * ${sign})`);
                item.style.setProperty('--sc', scale);
                item.style.setProperty('--br', light);
                item.style.setProperty('--op', opacity);
                item.style.setProperty('--a', distance);
                item.style.setProperty('--o', Math.max(-3, Math.min(3, offset)));
                item.classList.toggle('is-current', offset === 0);
                item.classList.toggle('is-far', distance > 2);
                item.setAttribute('aria-hidden', String(offset !== 0));
                const link = item.querySelector('a');
                link.tabIndex = offset === 0 ? 0 : -1;
            });
            if (announceIt && announce) {
                announce.textContent = items[current].querySelector('.dest-name').textContent.trim();
            }
        };

        const go = (step, fromUser = false) => {
            current = (current + step + count) % count;
            render(fromUser);
            if (fromUser) {
                hold();
            }
        };

        // Défilement automatique : il reprend quelques secondes après la dernière action.
        const stop = () => {
            clearInterval(timer);
            timer = null;
        };
        const start = () => {
            stop();
            if (inView && !document.hidden) {
                timer = setInterval(() => go(1), AUTOPLAY_MS);
            }
        };
        const hold = () => {
            stop();
            clearTimeout(resumeTimer);
            resumeTimer = setTimeout(start, RESUME_MS);
        };

        // Boutons
        section.querySelectorAll('[data-cover-step]').forEach((button) => {
            button.addEventListener('click', () => go(Number(button.dataset.coverStep), true));
        });

        // Clic sur une carte de côté : elle vient au centre (seule la carte du centre ouvre la fiche).
        items.forEach((item, i) => {
            item.querySelector('a').addEventListener('click', (event) => {
                if (moved || i !== current) {
                    event.preventDefault();
                }
                if (!moved && i !== current) {
                    let offset = (i - current) % count;
                    if (offset > count / 2) offset -= count;
                    if (offset < -count / 2) offset += count;
                    go(offset, true);
                }
            });
        });

        // Clavier
        coverflow.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                event.preventDefault();
                go(event.key === 'ArrowRight' ? 1 : -1, true);
            }
        });

        // Défilement horizontal (pavé tactile, molette inclinable, ou Maj + molette) : un pas par geste.
        let wheelSum = 0;
        let wheelLock = 0;
        coverflow.addEventListener('wheel', (event) => {
            const dx = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : (event.shiftKey ? event.deltaY : 0);
            if (dx === 0) {
                return; // le défilement vertical de la page n'est pas bloqué
            }
            event.preventDefault();
            const now = Date.now();
            if (now < wheelLock) {
                return;
            }
            wheelSum += dx;
            if (Math.abs(wheelSum) > 40) {
                go(wheelSum > 0 ? 1 : -1, true);
                wheelSum = 0;
                wheelLock = now + 450;
            }
        }, { passive: false });

        // Glisser au doigt ou à la souris
        let startX = 0;
        let dragging = false;
        let moved = false;
        coverflow.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) {
                return;
            }
            dragging = true;
            moved = false;
            startX = event.clientX;
        });
        window.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }
            const dx = event.clientX - startX;
            if (Math.abs(dx) > 8) {
                moved = true;
                coverflow.classList.add('is-dragging');
            }
            if (Math.abs(dx) > 50) {
                go(dx < 0 ? 1 : -1, true);
                startX = event.clientX;
            }
        });
        window.addEventListener('pointerup', () => {
            dragging = false;
            coverflow.classList.remove('is-dragging');
            // « moved » reste vrai jusqu'au clic qui suit un glissement, pour ne pas ouvrir la carte.
            setTimeout(() => { moved = false; }, 0);
        });

        // Lecture automatique seulement quand la section est visible et l'onglet actif.
        new IntersectionObserver(([entry]) => {
            inView = entry.isIntersecting;
            inView ? start() : stop();
        }, { threshold: 0.35 }).observe(coverflow);
        document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));

        render();
        window.addEventListener('load', () => items.forEach((item) => { const img = item.querySelector('img'); img.loading = 'eager'; }), { once: true });
    }

    /* ---------- Secteurs : panneaux photo qui s’ouvrent (docs/accueil-secteurs.md) ---------- */
    // Un seul panneau ouvert. Survol (souris), clic ou toucher l’ouvrent ; la fin de sa barre de progression
    // passe au suivant. Pause au survol, au focus, hors écran et onglet caché.
    const sectorBox = document.querySelector('[data-sector-panels]');
    if (sectorBox) {
        const panels = [...sectorBox.querySelectorAll('[data-sector-panel]')];
        const canHover = window.matchMedia('(hover: hover) and (pointer: fine)');
        let current = Math.max(0, panels.findIndex((panel) => panel.classList.contains('is-open')));
        let hovering = false;
        let focused = false;
        let inView = false;
        let hoverTimer = null;

        const open = (index) => {
            current = index;
            panels.forEach((panel, i) => {
                const isOpen = i === index;
                panel.classList.toggle('is-open', isOpen);
                panel.querySelector('.sector-toggle').setAttribute('aria-expanded', String(isOpen));
                panel.querySelector('.sector-body').inert = !isOpen;
            });
        };
        const syncPause = () => {
            sectorBox.classList.toggle('is-paused', hovering || focused || !inView || document.hidden);
        };

        panels.forEach((panel, i) => {
            panel.querySelector('.sector-toggle').addEventListener('click', () => open(i));
            // Petit délai au survol : traverser les panneaux avec la souris ne les ouvre pas tous.
            panel.addEventListener('mouseenter', () => {
                if (!canHover.matches) {
                    return;
                }
                clearTimeout(hoverTimer);
                hoverTimer = setTimeout(() => open(i), 140);
            });
            panel.querySelector('.sector-progress').addEventListener('animationend', () => {
                if (i === current) {
                    open((current + 1) % panels.length);
                }
            });
        });

        sectorBox.addEventListener('mouseenter', () => { hovering = canHover.matches; syncPause(); });
        sectorBox.addEventListener('mouseleave', () => { hovering = false; clearTimeout(hoverTimer); syncPause(); });
        sectorBox.addEventListener('focusin', () => { focused = true; syncPause(); });
        sectorBox.addEventListener('focusout', (event) => {
            focused = sectorBox.contains(event.relatedTarget);
            syncPause();
        });
        new IntersectionObserver(([entry]) => {
            inView = entry.isIntersecting;
            syncPause();
        }, { threshold: 0.4 }).observe(sectorBox);
        document.addEventListener('visibilitychange', syncPause);

        syncPause();
        window.addEventListener('load', () => panels.forEach((panel) => { panel.querySelector('img').loading = 'eager'; }), { once: true });
    }
})();
