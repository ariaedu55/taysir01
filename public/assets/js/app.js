// Comportements communs à toutes les pages. Aucune bibliothèque.
//  - en-tête : thème sur le bandeau, masquage au scroll, menus déroulants, menu plein écran ;
//  - apparitions au scroll : [data-reveal], [data-split], [data-stagger] ;
//  - effets liés au scroll : [data-scroll] (--p de 0 à 1), [data-parallax], [data-words] ;
//  - pointeur : [data-glow] (reflet), [data-magnetic] (aimantation), inclinaison 3D des cartes ([data-tilt] et cartes communes).
// Une seule boucle requestAnimationFrame, déclenchée par le scroll. window.Taysir permet aux scripts
// de page d'y ajouter leurs propres tâches.
(() => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));

    /* ---------- Boucle de scroll partagée ---------- */
    const tasks = new Set();
    let ticking = false;
    const frame = () => {
        ticking = false;
        const viewport = window.innerHeight;
        tasks.forEach((task) => task(viewport));
    };
    const requestFrame = () => {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(frame);
        }
    };
    window.addEventListener('scroll', requestFrame, { passive: true });
    window.addEventListener('resize', requestFrame, { passive: true });
    window.Taysir = {
        reduceMotion,
        clamp,
        addScrollTask(task) {
            tasks.add(task);
            requestFrame();
        },
        requestFrame,
    };

    /* ---------- En-tête ---------- */
    const header = document.querySelector('[data-header]');
    const body = document.body;
    if (header) {
        const hasHero = body.classList.contains('has-hero-header');
        const heroSection = hasHero ? document.querySelector('main > :first-child') : null;
        let lastY = window.scrollY;

        tasks.add(() => {
            const y = window.scrollY;
            const menuOpen = header.classList.contains('is-menu-open');
            const navOpen = header.querySelector('.nav-item.is-open') !== null;
            if (heroSection) {
                const heroEnd = heroSection.offsetTop + heroSection.offsetHeight - header.offsetHeight;
                header.classList.toggle('is-on-hero', y < heroEnd);
            }
            if (!menuOpen && !navOpen) {
                if (y > 320 && y > lastY + 6) {
                    header.classList.add('is-hidden');
                } else if (y < lastY - 6 || y <= 320) {
                    header.classList.remove('is-hidden');
                }
            }
            lastY = y;
        });
        header.addEventListener('focusin', () => header.classList.remove('is-hidden'));

        /* Menus déroulants (le survol et le focus sont gérés en CSS, le clic ici pour le tactile) */
        const items = [...header.querySelectorAll('[data-nav-item]')];
        const closeItems = (except) => items.forEach((item) => {
            if (item !== except) {
                item.classList.remove('is-open');
                item.querySelector('.nav-trigger').setAttribute('aria-expanded', 'false');
            }
        });
        items.forEach((item) => {
            const trigger = item.querySelector('.nav-trigger');
            trigger.addEventListener('click', () => {
                const open = !item.classList.contains('is-open');
                closeItems(item);
                item.classList.toggle('is-open', open);
                item.classList.remove('is-closed');
                trigger.setAttribute('aria-expanded', String(open));
            });
            item.addEventListener('mouseleave', () => item.classList.remove('is-closed'));
            item.addEventListener('focusout', (event) => {
                if (!item.contains(event.relatedTarget)) {
                    item.classList.remove('is-open', 'is-closed');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });
            item.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    item.classList.remove('is-open');
                    item.classList.add('is-closed');
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.focus();
                }
            });
        });
        document.addEventListener('click', (event) => {
            if (!header.contains(event.target)) {
                closeItems(null);
            }
        });

        /* Le dialogue natif gère Échap et l’isolement de la page. */
        const toggle = header.querySelector('[data-menu-toggle]');
        const menu = header.querySelector('[data-mobile-menu]');
        const compact = window.matchMedia('(max-width: 78rem)');
        if (toggle && menu) {
            const syncMenu = () => {
                toggle.setAttribute('aria-expanded', String(menu.open));
                header.classList.toggle('is-menu-open', menu.open);
            };
            toggle.addEventListener('click', () => {
                if (menu.open) {
                    return;
                }
                header.classList.remove('is-hidden');
                menu.showModal();
                menu.querySelector('.mobile-menu-scroll').scrollTop = 0;
                syncMenu();
            });
            menu.addEventListener('close', () => {
                syncMenu();
                if (compact.matches) {
                    toggle.focus({ preventScroll: true });
                }
            });
            const firstLink = menu.querySelector('.mobile-menu-brand');
            const lastLink = menu.querySelector('.mobile-menu-actions a:last-child');
            menu.addEventListener('keydown', (event) => {
                if (event.key !== 'Tab') {
                    return;
                }
                const end = event.shiftKey ? firstLink : lastLink;
                if (event.target === end) {
                    event.preventDefault();
                    (event.shiftKey ? lastLink : firstLink).focus();
                }
            });
            menu.addEventListener('click', (event) => {
                if (event.target === menu) {
                    const { left, right, top, bottom } = menu.getBoundingClientRect();
                    if (event.clientX < left || event.clientX > right || event.clientY < top || event.clientY > bottom) {
                        menu.close();
                    }
                }
                const link = event.target.closest('a');
                if (link && !event.defaultPrevented && !(event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) {
                    menu.close();
                }
            });
            compact.addEventListener('change', (event) => {
                if (!event.matches && menu.open) {
                    menu.close();
                    header.querySelector('.site-nav .nav-link')?.focus({ preventScroll: true });
                }
            });
            window.addEventListener('pageshow', () => {
                if (menu.open) {
                    menu.close();
                }
                syncMenu();
            });
        }
    }

    /* ---------- Découpage en mots ---------- */
    const splitWords = (element, className, indexVar) => {
        let index = 0;
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }
        nodes.forEach((node) => {
            const fragment = document.createDocumentFragment();
            node.textContent.split(/(\s+)/).forEach((part) => {
                if (part === '') {
                    return;
                }
                if (/^\s+$/.test(part)) {
                    fragment.append(part);
                    return;
                }
                const word = document.createElement('span');
                word.className = className;
                if (className === 'w') {
                    const inner = document.createElement('span');
                    inner.className = 'w-i';
                    inner.style.setProperty(indexVar, String(index));
                    inner.textContent = part;
                    word.append(inner);
                } else {
                    word.textContent = part;
                }
                fragment.append(word);
                index += 1;
            });
            node.replaceWith(fragment);
        });
        return index;
    };
    document.querySelectorAll('[data-split]').forEach((element) => splitWords(element, 'w', '--wi'));

    /* ---------- Cascades : --i pour chaque enfant ---------- */
    document.querySelectorAll('[data-stagger]').forEach((group) => {
        [...group.children].forEach((child, index) => child.style.setProperty('--i', String(index)));
    });

    /* ---------- Apparitions, rejouées à chaque passage ---------- */
    // .is-in quand l’élément est suffisamment visible, retiré dès qu’il sort complètement de l’écran,
    // par le haut comme par le bas : l’animation rejoue à chaque passage, en descendant et en remontant
    // (les transitions CSS la jouent à l’envers à la sortie).
    // [data-reveal-toggle] (scènes chorégraphiées) attend qu’une plus grande part soit visible.
    const revealables = document.querySelectorAll('[data-reveal], [data-split], [data-reveal-toggle]');
    if (reduceMotion.matches || !('IntersectionObserver' in window)) {
        revealables.forEach((element) => element.classList.add('is-in'));
    } else {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const element = entry.target;
                if (!entry.isIntersecting) {
                    element.classList.remove('is-in');
                    return;
                }
                // Part visible rapportée à la hauteur de l’élément, ou à celle de l’écran s’il est plus grand.
                const rootHeight = entry.rootBounds ? entry.rootBounds.height : window.innerHeight;
                const share = entry.intersectionRect.height / Math.max(1, Math.min(entry.boundingClientRect.height, rootHeight));
                if (share >= (element.hasAttribute('data-reveal-toggle') ? 0.25 : 0.1)) {
                    element.classList.add('is-in');
                }
            });
        }, { threshold: [0, 0.05, 0.1, 0.2, 0.25, 0.3, 0.5] });
        revealables.forEach((element) => revealObserver.observe(element));
    }

    /* ---------- Effets liés au scroll (uniquement pour les éléments visibles) ---------- */
    const visible = new Set();
    const scrollElements = document.querySelectorAll('[data-scroll], [data-parallax], [data-words], [data-type-scroll]');
    if ('IntersectionObserver' in window && scrollElements.length > 0) {
        const scrollObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => (entry.isIntersecting ? visible.add(entry.target) : visible.delete(entry.target)));
            requestFrame();
        }, { rootMargin: '20% 0px 20% 0px' });
        scrollElements.forEach((element) => scrollObserver.observe(element));
    }

    const wordGroups = new Map();
    document.querySelectorAll('[data-words]').forEach((element) => {
        splitWords(element, 'word');
        wordGroups.set(element, { words: [...element.querySelectorAll('.word')], lit: -1 });
    });

    // Machine à écrire liée au scroll : chaque caractère devient un span ; les mots restent insécables
    // et la mise en page ne bouge pas (les caractères non tapés sont transparents, pas retirés).
    const typeGroups = new Map();
    document.querySelectorAll('[data-type-scroll]').forEach((element) => {
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }
        nodes.forEach((node) => {
            const fragment = document.createDocumentFragment();
            node.textContent.split(/(\s+)/).forEach((part) => {
                if (part === '') {
                    return;
                }
                if (/^\s+$/.test(part)) {
                    const space = document.createElement('span');
                    space.className = 'ch';
                    space.textContent = ' ';
                    fragment.append(space);
                    return;
                }
                const word = document.createElement('span');
                word.className = 'tw-word';
                [...part].forEach((letter) => {
                    const char = document.createElement('span');
                    char.className = 'ch';
                    char.textContent = letter;
                    word.append(char);
                });
                fragment.append(word);
            });
            node.replaceWith(fragment);
        });
        const caret = document.createElement('span');
        caret.className = 'tw-caret';
        caret.setAttribute('aria-hidden', 'true');
        const chars = [...element.querySelectorAll('.ch')];
        element.classList.add('is-typed');
        typeGroups.set(element, { chars, caret, shown: -1 });
    });

    tasks.add((viewport) => {
        visible.forEach((element) => {
            const rect = element.getBoundingClientRect();
            const typed = typeGroups.get(element);
            if (typed) {
                // Écrit entre l'arrivée du haut du texte à 88 % de l'écran et son arrivée à 30 %.
                const progress = reduceMotion.matches ? 1 : clamp((viewport * 0.88 - rect.top) / (viewport * 0.58));
                const count = Math.round(progress * typed.chars.length);
                if (count !== typed.shown) {
                    const [from, to] = typed.shown < 0 ? [0, typed.chars.length] : [Math.min(count, typed.shown), Math.max(count, typed.shown)];
                    for (let i = from; i < to; i++) {
                        typed.chars[i].classList.toggle('on', i < count);
                    }
                    if (count === 0) {
                        typed.chars[0].before(typed.caret);
                    } else {
                        typed.chars[count - 1].after(typed.caret);
                    }
                    typed.caret.classList.toggle('is-done', count === typed.chars.length);
                    typed.shown = count;
                }
            }
            if (element.hasAttribute('data-scroll')) {
                // 0 quand le haut entre par le bas de l'écran, 1 quand le bas sort par le haut.
                // Mode « center » : de l'arrivée du haut au centre jusqu'à l'arrivée du bas au centre.
                const progress = element.dataset.scroll === 'center'
                    ? clamp((viewport * 0.55 - rect.top) / rect.height)
                    : clamp((viewport - rect.top) / (viewport + rect.height));
                element.style.setProperty('--p', progress.toFixed(4));
            }
            if (element.hasAttribute('data-parallax') && !reduceMotion.matches) {
                const speed = parseFloat(element.dataset.parallax) || 0.1;
                const offset = (rect.top + rect.height / 2 - viewport / 2) * -speed;
                element.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0)`;
            }
            const group = wordGroups.get(element);
            if (group) {
                const progress = clamp((viewport * 0.8 - rect.top) / (rect.height + viewport * 0.25));
                const lit = reduceMotion.matches ? group.words.length : Math.round(progress * group.words.length);
                if (lit !== group.lit) {
                    group.words.forEach((word, index) => word.classList.toggle('is-lit', index < lit));
                    group.lit = lit;
                }
            }
        });
    });

    /* ---------- Pointeur : reflet et aimantation ---------- */
    if (finePointer.matches) {
        document.addEventListener('pointermove', (event) => {
            const glow = event.target.closest?.('[data-glow]');
            if (glow) {
                const rect = glow.getBoundingClientRect();
                glow.style.setProperty('--mx', `${event.clientX - rect.left}px`);
                glow.style.setProperty('--my', `${event.clientY - rect.top}px`);
            }
        }, { passive: true });

        // Inclinaison 3D des cartes (docs/cartes-voyage.md) : --rx / --ry, 5° au plus, lus par le transform de chaque carte.
        if (!reduceMotion.matches) {
            const TILT = '[data-tilt], .pass-card, .article-card, .hub-card, .destination-card';
            let tiltCard = null;
            let tiltFrame = 0;
            let pointerX = 0;
            let pointerY = 0;
            const resetTilt = (card) => {
                card.style.removeProperty('--rx');
                card.style.removeProperty('--ry');
            };
            document.addEventListener('pointermove', (event) => {
                if (event.pointerType !== 'mouse') {
                    return;
                }
                const card = event.target.closest?.(TILT) ?? null;
                if (card !== tiltCard) {
                    if (tiltCard) {
                        resetTilt(tiltCard);
                    }
                    tiltCard = card;
                }
                if (!card) {
                    return;
                }
                pointerX = event.clientX;
                pointerY = event.clientY;
                if (!tiltFrame) {
                    tiltFrame = requestAnimationFrame(() => {
                        tiltFrame = 0;
                        if (!tiltCard) {
                            return;
                        }
                        const rect = tiltCard.getBoundingClientRect();
                        const x = clamp((pointerX - rect.left) / rect.width) - .5;
                        const y = clamp((pointerY - rect.top) / rect.height) - .5;
                        tiltCard.style.setProperty('--ry', `${(x * 10).toFixed(2)}deg`);
                        tiltCard.style.setProperty('--rx', `${(-y * 10).toFixed(2)}deg`);
                    });
                }
            }, { passive: true });
            document.addEventListener('mouseout', (event) => {
                if (!event.relatedTarget && tiltCard) {
                    resetTilt(tiltCard);
                    tiltCard = null;
                }
            });
        }

        if (!reduceMotion.matches) {
            document.querySelectorAll('[data-magnetic]').forEach((element) => {
                element.addEventListener('pointermove', (event) => {
                    const rect = element.getBoundingClientRect();
                    const x = (event.clientX - rect.left - rect.width / 2) * 0.22;
                    const y = (event.clientY - rect.top - rect.height / 2) * 0.32;
                    element.style.transform = `translate(${x.toFixed(1)}px, ${y.toFixed(1)}px)`;
                });
                element.addEventListener('pointerleave', () => {
                    element.style.transform = '';
                });
            });
        }
    }

    requestFrame();
})();
