// Espace équipe Taysir (docs/gestion-phase-1.md) : comportements communs à toutes les pages.
// Barre latérale repliable, tiroir mobile, menus, recherche Ctrl+K, onglets, sélection multiple, panneau latéral,
// notifications éphémères. Dans la maquette, les actions [data-g-demo] montrent leur effet sans rien enregistrer.
(() => {
    const shell = document.querySelector('[data-g-shell]');
    const STORE = 'taysir-gestion-replie';

    /* ---------- Notifications éphémères ---------- */
    const toasts = document.querySelector('[data-g-toasts]');
    // Compteur de l'onglet « Pièces » : une pièce traitée (validée ou refusée) de moins
    const decrementPieces = () => {
        const count = document.querySelector('#tab-pieces .g-count');
        if (count) {
            const left = Number(count.textContent) - 1;
            left > 0 ? (count.textContent = left) : count.remove();
        }
    };
    const toast = (message, undo) => {
        if (!toasts) {
            return;
        }
        const item = document.createElement('div');
        item.className = 'g-toast';
        item.innerHTML = '<span class="g-toast-ok" aria-hidden="true">✓</span><span></span>';
        item.lastChild.textContent = message;
        if (undo) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Annuler';
            button.addEventListener('click', () => {
                undo();
                item.remove();
            });
            item.append(button);
        }
        toasts.append(item);
        setTimeout(() => item.remove(), 5000);
    };
    window.gestionToast = toast;

    document.addEventListener('click', (event) => {
        const soon = event.target.closest('[data-g-soon]');
        if (soon) {
            event.preventDefault();
            toast('Cet écran sera construit dans une phase suivante.');
        }
    });

    /* ---------- Barre latérale : repli mémorisé, tiroir sur tablette et téléphone ---------- */
    if (shell) {
        try {
            if (localStorage.getItem(STORE) === '1') {
                shell.classList.add('is-collapsed');
            }
        } catch {
            // stockage indisponible : barre dépliée
        }
        shell.querySelector('[data-g-collapse]')?.addEventListener('click', () => {
            const collapsed = shell.classList.toggle('is-collapsed');
            try {
                localStorage.setItem(STORE, collapsed ? '1' : '0');
            } catch {
                // sans conséquence
            }
        });
        let drawerReturn = null;
        const openDrawer = (event) => {
            drawerReturn = event?.currentTarget || document.activeElement;
            shell.classList.add('is-drawer');
            shell.querySelector('.g-nav-link')?.focus();
        };
        const closeDrawer = () => {
            if (!shell.classList.contains('is-drawer')) {
                return;
            }
            shell.classList.remove('is-drawer');
            // Le focus revient au bouton qui a ouvert le menu
            drawerReturn?.focus();
            drawerReturn = null;
        };
        document.querySelectorAll('[data-g-drawer-open]').forEach((b) => b.addEventListener('click', openDrawer));
        document.querySelectorAll('[data-g-drawer-close]').forEach((b) => b.addEventListener('click', closeDrawer));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeDrawer();
            }
        });
    }

    /* ---------- Sélecteur « Voir en tant que » (maquette) ---------- */
    document.querySelector('[data-g-poste]')?.addEventListener('change', (event) => {
        const url = new URL(location.href);
        url.searchParams.set('poste', event.target.value);
        location.href = url.toString();
    });

    /* ---------- Menus déroulants (« + Créer », notifications) ---------- */
    const closeMenus = (except) => document.querySelectorAll('[data-g-menu][aria-expanded="true"]').forEach((button) => {
        if (button !== except) {
            button.setAttribute('aria-expanded', 'false');
            button.nextElementSibling.hidden = true;
        }
    });
    document.querySelectorAll('[data-g-menu]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = button.getAttribute('aria-expanded') !== 'true';
            closeMenus(button);
            button.setAttribute('aria-expanded', String(open));
            button.nextElementSibling.hidden = !open;
            if (open) {
                button.nextElementSibling.querySelector('button, a')?.focus();
            }
        });
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.g-menu')) {
            closeMenus();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const open = document.querySelector('[data-g-menu][aria-expanded="true"]');
            if (open) {
                closeMenus();
                open.focus();
            }
        }
    });

    /* ---------- Recherche globale (Ctrl+K) ---------- */
    const palette = document.querySelector('[data-g-palette]');
    if (palette) {
        const input = palette.querySelector('[data-g-palette-input]');
        const list = palette.querySelector('[data-g-palette-results]');
        let data = [];
        try {
            data = JSON.parse(document.getElementById('g-search-data').textContent);
        } catch {
            data = [];
        }
        let active = 0;
        let returnFocus = null;
        const normalize = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, '');
        const render = () => {
            const query = normalize(input.value);
            const results = data.filter((item) => !query || normalize(item.titre + item.detail).includes(query)).slice(0, 8);
            active = Math.min(active, Math.max(0, results.length - 1));
            list.innerHTML = results.length ? '' : '<li class="g-palette-empty">Aucun résultat dans votre périmètre.</li>';
            results.forEach((item, index) => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = item.url;
                a.setAttribute('role', 'option');
                a.className = index === active ? 'is-active' : '';
                a.innerHTML = '<span class="g-type"></span><span><strong></strong><small></small></span><span aria-hidden="true">↵</span>';
                a.querySelector('.g-type').textContent = item.type;
                a.querySelector('strong').textContent = item.titre;
                a.querySelector('small').textContent = ' ' + item.detail;
                li.append(a);
                list.append(li);
            });
        };
        const open = () => {
            returnFocus = document.activeElement;
            palette.hidden = false;
            input.value = '';
            active = 0;
            render();
            input.focus();
        };
        const close = () => {
            palette.hidden = true;
            returnFocus?.focus();
        };
        document.querySelectorAll('[data-g-search-open]').forEach((b) => b.addEventListener('click', open));
        document.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                palette.hidden ? open() : close();
            }
        });
        palette.addEventListener('click', (event) => {
            if (event.target === palette) {
                close();
            }
        });
        input.addEventListener('input', () => {
            active = 0;
            render();
        });
        input.addEventListener('keydown', (event) => {
            const links = [...list.querySelectorAll('a')];
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                active = (active + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % Math.max(1, links.length);
                links.forEach((link, i) => link.classList.toggle('is-active', i === active));
                links[active]?.scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'Enter' && links[active]) {
                event.preventDefault();
                location.href = links[active].href;
            } else if (event.key === 'Escape') {
                close();
            }
        });
    }

    /* ---------- Onglets ([data-g-tabs]) : ?onglet=… à l'ouverture ---------- */
    document.querySelectorAll('[data-g-tabs]').forEach((tablist) => {
        const tabs = [...tablist.querySelectorAll('[role="tab"]')];
        const select = (tab, focus = false) => {
            tabs.forEach((other) => {
                const selected = other === tab;
                other.setAttribute('aria-selected', String(selected));
                other.tabIndex = selected ? 0 : -1;
                document.getElementById(other.getAttribute('aria-controls')).hidden = !selected;
            });
            if (focus) {
                tab.focus();
            }
        };
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => {
                select(tab);
                const url = new URL(location.href);
                url.searchParams.set('onglet', tab.dataset.name);
                history.replaceState(null, '', url);
            });
            tab.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                    event.preventDefault();
                    select(tabs[(index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length], true);
                }
            });
        });
        const wanted = new URLSearchParams(location.search).get('onglet');
        select(tabs.find((tab) => tab.dataset.name === wanted) || tabs[0]);
    });

    /* ---------- Sélection multiple dans les tableaux ---------- */
    document.querySelectorAll('[data-g-selectable]').forEach((table) => {
        const all = table.querySelector('[data-g-select-all]');
        const boxes = [...table.querySelectorAll('[data-g-select]')];
        const bar = document.querySelector(table.dataset.gSelectable);
        const count = bar?.querySelector('[data-g-selected-count]');
        const update = () => {
            const checked = boxes.filter((box) => box.checked);
            boxes.forEach((box) => box.closest('tr').classList.toggle('is-selected', box.checked));
            if (all) {
                const shown = visible().length;
                all.checked = shown > 0 && checked.length === shown;
                all.indeterminate = checked.length > 0 && checked.length < shown;
            }
            if (bar) {
                bar.hidden = checked.length === 0;
                count.textContent = checked.length;
            }
        };
        // « Tout sélectionner » ne touche que les lignes visibles (filtres)
        const visible = () => boxes.filter((box) => !box.closest('tr').hidden);
        all?.addEventListener('change', () => {
            visible().forEach((box) => { box.checked = all.checked; });
            update();
        });
        boxes.forEach((box) => {
            box.addEventListener('change', update);
            box.addEventListener('click', (event) => event.stopPropagation());
        });
        table.gestionSelection = { boxes, update };
    });

    /* ---------- Panneau latéral ---------- */
    const panel = document.querySelector('[data-g-panel]');
    const panelScrim = document.querySelector('[data-g-panel-scrim]');
    let panelReturn = null;
    const closePanel = () => {
        panel?.classList.remove('is-open');
        panelScrim?.classList.remove('is-open');
        panelReturn?.focus();
    };
    window.gestionPanel = {
        open(fill, from) {
            if (!panel) {
                return;
            }
            panelReturn = from || document.activeElement;
            fill(panel);
            panel.classList.add('is-open');
            panelScrim?.classList.add('is-open');
            panel.querySelector('[data-g-panel-close]')?.focus();
        },
        close: closePanel,
    };
    document.querySelectorAll('[data-g-panel-close]').forEach((b) => b.addEventListener('click', closePanel));
    panelScrim?.addEventListener('click', closePanel);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && panel?.classList.contains('is-open')) {
            closePanel();
        }
    });

    /* ---------- Actions de démonstration (maquette) ---------- */
    document.addEventListener('click', (event) => {
        const action = event.target.closest('[data-g-demo]');
        if (!action) {
            return;
        }
        event.preventDefault();
        const kind = action.dataset.gDemo;
        const item = action.closest('[data-g-item]');

        if (kind === 'confirmer' && item) {
            // T1 : confirmer un rendez-vous depuis « Ma journée »
            const badge = item.querySelector('.g-badge');
            item.classList.add('is-done');
            setTimeout(() => { item.hidden = true; }, 350);
            const counter = document.querySelector('[data-g-confirm-count]');
            if (counter) {
                counter.textContent = Math.max(0, Number(counter.textContent) - 1);
            }
            toast(`Rendez-vous confirmé : ${item.dataset.gItem}. Un e-mail de confirmation part au client.`, () => {
                item.hidden = false;
                item.classList.remove('is-done');
                if (badge) {
                    badge.lastChild.textContent = 'À confirmer';
                }
                if (counter) {
                    counter.textContent = Number(counter.textContent) + 1;
                }
            });
        } else if (kind === 'valider' && item) {
            // T2 : valider une pièce déposée
            item.classList.remove('is-recue');
            item.querySelector('.g-doc-actions').innerHTML = '<span class="g-badge is-succes"><span class="g-dot is-succes"></span>Validée</span>';
            item.querySelector('p').textContent = 'Validée à l’instant par vous. La cliente est prévenue.';
            decrementPieces();
            toast(`Pièce validée : ${item.dataset.gItem}. La cliente voit l’avancement dans son espace.`);
        } else if (kind === 'refuser' && item) {
            item.classList.add('is-refusing');
            item.querySelector('.g-refuse').hidden = false;
            item.querySelector('.g-refuse textarea').focus();
        } else if (kind === 'refuser-envoyer' && item) {
            const motif = item.querySelector('.g-refuse textarea').value.trim();
            const field = item.querySelector('.g-refuse textarea');
            const error = item.querySelector('.g-field-error');
            if (!motif) {
                // État d'erreur explicite : le motif est obligatoire, il part à la cliente
                field.setAttribute('aria-invalid', 'true');
                if (error) { error.hidden = false; }
                field.focus();
                return;
            }
            field.removeAttribute('aria-invalid');
            if (error) { error.hidden = true; }
            item.classList.remove('is-recue');
            item.querySelector('.g-refuse').hidden = true;
            item.querySelector('.g-doc-actions').innerHTML = '<span class="g-badge is-urgent"><span class="g-dot is-urgent"></span>Refusée</span>';
            item.querySelector('p').textContent = `Refusée : ${motif}`;
            item.classList.remove('is-refusing');
            decrementPieces();
            toast('Pièce refusée. Le motif est envoyé à la cliente avec un lien pour déposer une nouvelle version.');
        } else if (kind === 'attribuer') {
            // T4 : attribuer un lot de prospects
            const table = document.querySelector(action.dataset.gTable);
            const selection = table?.gestionSelection;
            const who = document.querySelector(action.dataset.gWho)?.value;
            if (!selection || !who) {
                return;
            }
            const chosen = selection.boxes.filter((box) => box.checked);
            chosen.forEach((box) => {
                const row = box.closest('tr');
                row.querySelector('[data-g-owner]').innerHTML = '';
                row.querySelector('[data-g-owner]').textContent = who;
                box.checked = false;
            });
            selection.update();
            table.dispatchEvent(new CustomEvent('gestion:attribue'));
            toast(`${chosen.length} prospect${chosen.length > 1 ? 's' : ''} attribué${chosen.length > 1 ? 's' : ''} à ${who}. Ils apparaissent dans sa liste « Mes prospects ».`);
        } else if (kind === 'affecter') {
            // T5 : transmettre un dossier orienté au traitement
            const who = document.querySelector(action.dataset.gWho)?.value;
            action.closest('form')?.querySelectorAll('input, select, textarea, button').forEach((el) => { el.disabled = true; });
            document.querySelectorAll('[data-g-owner-traitement]').forEach((el) => { el.textContent = who; });
            // Trace durable à la place du bouton : le dossier est parti
            const done = document.createElement('p');
            done.className = 'g-handoff';
            done.setAttribute('role', 'status');
            done.textContent = `Transmis à ${who} à l’instant. La recommandation est figée ; pour la modifier, ajoutez une note. (Maquette : rien n’est enregistré, un rechargement efface la transmission.)`;
            action.replaceWith(done);
            toast(`Dossier transmis à ${who} (agent de traitement). Maquette : la transmission n’est gardée que dans cette page ; en 1B, elle sera enregistrée et visible dans sa « Ma journée ».`);
        } else if (kind === 'deposer') {
            // T3, côté client : dépôt d'une pièce
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.pdf,.jpg,.jpeg,.png';
            input.addEventListener('change', () => {
                if (!input.files.length) {
                    return;
                }
                const row = action.closest('[data-g-item]');
                row.classList.add('is-sent');
                action.replaceWith(Object.assign(document.createElement('span'), { className: 'c-sent', textContent: '✓ Envoyée : en cours de vérification' }));
                // La mise en avant « À faire en premier » passe à l'action suivante
                if (row.classList.contains('is-next')) {
                    const badge = row.querySelector('.c-next');
                    row.classList.remove('is-next');
                    const next = [...document.querySelectorAll('.c-todo-item')].find((li) => !li.classList.contains('is-sent') && li.querySelector('.c-btn'));
                    if (next && badge) {
                        next.classList.add('is-next');
                        next.querySelector('div').prepend(badge);
                        next.querySelector('.c-btn').classList.remove('c-btn-outline', 'c-btn-light');
                    } else {
                        badge?.remove();
                    }
                }
                // Une chose de moins à faire
                const left = document.querySelector('.c-todo .c-count');
                if (left) {
                    const n = Number(left.textContent) - 1;
                    n > 0 ? (left.textContent = n) : left.remove();
                }
                toast(`« ${input.files[0].name} » envoyée. Votre chargé de dossier la vérifie.`);
            });
            input.click();
        } else {
            toast(action.dataset.gMessage || 'Action enregistrée (démonstration).');
        }
    });
})();
