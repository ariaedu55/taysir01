/* Formulaires du portail : documents choisis (ajout en plusieurs fois, aperçu des images et des PDF, retrait d'un
   fichier), contrôle du nombre, de la taille et du format avant l'envoi, et bouton désactivé pendant l'envoi.
   Le serveur refait tous ces contrôles. */
(() => {
    const allowed = /\.(pdf|jpe?g|png)$/i;
    const size = (bytes) => (bytes < 1048576 ? `${Math.max(1, Math.round(bytes / 1024))} Ko` : `${(bytes / 1048576).toFixed(1).replace('.', ',')} Mo`);
    const isImage = (file) => /^image\/(jpeg|png)$/.test(file.type) || /\.(jpe?g|png)$/i.test(file.name);
    const isPdf = (file) => file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
    // Remplacer la sélection d'un champ fichier n'est possible qu'avec DataTransfer (navigateurs récents)
    const canEdit = (() => { try { return typeof DataTransfer === 'function' && new DataTransfer().files !== undefined; } catch { return false; } })();
    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    // Fenêtre d'aperçu partagée : image affichée, PDF dans un cadre, lien d'ouverture en secours (téléphones)
    let preview;
    const openPreview = (file, url) => {
        if (!preview) {
            preview = el('dialog', 'dz-preview');
            preview.setAttribute('aria-label', 'Aperçu du document');
            const head = el('div', 'dz-preview-head');
            const title = el('strong', 'dz-preview-title');
            const close = el('button', 'dz-preview-close', 'Fermer');
            close.type = 'button';
            close.addEventListener('click', () => preview.close());
            head.append(title, close);
            const body = el('div', 'dz-preview-body');
            const open = el('a', 'dz-preview-open', 'Ouvrir dans un nouvel onglet');
            open.target = '_blank';
            open.rel = 'noopener';
            preview.append(head, body, open);
            preview.addEventListener('click', (event) => { if (event.target === preview) preview.close(); });
            preview.addEventListener('close', () => preview.querySelector('.dz-preview-body').replaceChildren());
            document.body.append(preview);
        }
        preview.querySelector('.dz-preview-title').textContent = file.name;
        preview.querySelector('.dz-preview-open').href = url;
        const body = preview.querySelector('.dz-preview-body');
        if (isImage(file)) {
            const image = el('img');
            image.src = url;
            image.alt = `Aperçu de ${file.name}`;
            body.replaceChildren(image);
        } else {
            const frame = el('iframe');
            frame.src = url;
            frame.title = `Aperçu de ${file.name}`;
            body.replaceChildren(frame);
        }
        if (typeof preview.showModal === 'function') preview.showModal(); else window.open(url, '_blank', 'noopener');
    };

    document.querySelectorAll('[data-dropzone]').forEach((zone) => {
        const input = zone.querySelector('input[type="file"]');
        const list = zone.parentElement.querySelector('[data-dropzone-files]');
        const maxFiles = Number(input.dataset.maxFiles || 3);
        const maxSize = Number(input.dataset.maxSize || 5242880);
        const urls = new Map();
        const key = (file) => `${file.name}|${file.size}|${file.lastModified}`;
        let chosen = [];

        // Bouton explicite « Ajouter un fichier » (beaucoup ne savent pas qu'il faut cliquer dans la zone)
        const form = zone.closest('form');
        const buttons = form?.querySelector('[data-dz-buttons]');
        const save = form?.querySelector('[data-save-button]');
        const add = el('button', 'button dz-add');
        add.type = 'button';
        add.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> <span>Ajouter un fichier</span>';
        add.addEventListener('click', () => input.click());
        if (buttons) buttons.prepend(add); else list.after(add);

        const urlOf = (file) => {
            if (!urls.has(key(file))) urls.set(key(file), URL.createObjectURL(file));
            return urls.get(key(file));
        };
        const sync = () => {
            if (!canEdit) return;
            const transfer = new DataTransfer();
            chosen.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
        };

        const render = () => {
            list.replaceChildren();
            const files = canEdit ? chosen : [...input.files];
            let problem = '';
            files.forEach((file) => {
                const item = el('li', 'dz-file');
                const thumb = el('span', 'dz-thumb');
                if (isImage(file)) {
                    const image = el('img');
                    image.src = urlOf(file);
                    image.alt = '';
                    thumb.append(image);
                } else {
                    thumb.classList.add('is-pdf');
                    thumb.textContent = isPdf(file) ? 'PDF' : '?';
                }
                const info = el('span', 'dz-info');
                info.append(el('strong', '', file.name), el('span', '', size(file.size)));
                if (!allowed.test(file.name)) {
                    problem = 'Formats acceptés : PDF, JPG et PNG.';
                    item.classList.add('is-invalid');
                } else if (file.size > maxSize) {
                    problem = 'Chaque document doit peser 5 Mo au maximum.';
                    item.classList.add('is-invalid');
                }
                const actions = el('span', 'dz-actions');
                if (allowed.test(file.name)) {
                    const show = el('button', 'dz-btn', 'Aperçu');
                    show.type = 'button';
                    show.setAttribute('aria-label', `Aperçu de ${file.name}`);
                    show.addEventListener('click', () => openPreview(file, urlOf(file)));
                    actions.append(show);
                }
                if (canEdit) {
                    const remove = el('button', 'dz-btn is-remove', 'Retirer');
                    remove.type = 'button';
                    remove.setAttribute('aria-label', `Retirer ${file.name}`);
                    remove.addEventListener('click', () => {
                        chosen = chosen.filter((other) => key(other) !== key(file));
                        if (urls.has(key(file))) {
                            URL.revokeObjectURL(urls.get(key(file)));
                            urls.delete(key(file));
                        }
                        sync();
                        render();
                    });
                    actions.append(remove);
                }
                item.append(thumb, info, actions);
                list.append(item);
            });
            if (files.length > maxFiles) {
                problem = `Vous pouvez joindre ${maxFiles} documents au maximum.`;
            }
            input.setCustomValidity(problem);
            if (files.length > 0 && maxFiles > 1) {
                list.append(el('li', 'dz-count', `${files.length} fichier${files.length > 1 ? 's' : ''} sur ${maxFiles} au maximum${files.length < maxFiles ? ' · vous pouvez en ajouter' : ''}`));
            }
            if (problem) {
                list.append(el('li', 'is-invalid', problem));
            }
            // « Ajouter » devient « Ajouter un autre fichier » puis disparaît au maximum ; « Enregistrer » attend un fichier
            add.querySelector('span').textContent = files.length === 0 ? 'Ajouter un fichier'
                : input.multiple && canEdit ? 'Ajouter un autre fichier' : 'Remplacer la sélection';
            add.hidden = input.multiple && canEdit && files.length >= maxFiles;
            if (save) {
                save.disabled = files.length === 0 || problem !== '';
                const label = save.querySelector('span') || save;
                label.textContent = files.length > 1 ? `Enregistrer les ${files.length} fichiers` : save.dataset.label;
            }
        };
        render();

        input.addEventListener('change', () => {
            if (canEdit) {
                const incoming = [...input.files];
                if (!input.multiple) {
                    chosen = incoming.slice(0, 1);
                } else {
                    // Un nouveau choix s'ajoute aux fichiers déjà retenus (sans doublon)
                    incoming.forEach((file) => {
                        if (!chosen.some((other) => key(other) === key(file))) chosen.push(file);
                    });
                }
                sync();
            }
            render();
        });
        ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, () => zone.classList.add('is-over')));
        ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, () => zone.classList.remove('is-over')));
    });

    document.querySelectorAll('[data-portal-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const invalidFile = form.querySelector('input[type="file"]:invalid');
            if (invalidFile) {
                event.preventDefault();
                invalidFile.closest('.field')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.setAttribute('aria-busy', 'true');
                button.dataset.busyLabel = button.innerHTML;
                button.textContent = 'Envoi en cours…';
            }
        });
    });

    document.querySelectorAll('[data-direct-slots]').forEach((block) => {
        const form = block.closest('form');
        const select = block.querySelector('select');
        const status = block.querySelector('[data-slot-status]');
        const submit = form.querySelector('[data-appointment-submit]');
        let request;
        const updateSubmit = () => {
            submit.textContent = select.value ? 'Confirmer ce rendez-vous' : 'Demander le rendez-vous';
            form.querySelectorAll('[name="preferred_slot"]').forEach((input) => {
                input.required = !select.value;
            });
        };
        const load = async () => {
            request?.abort();
            select.options.length = 1;
            status.textContent = '';
            updateSubmit();
            const fields = new FormData(form);
            const mode = fields.get('mode');
            if (!fields.get('motive') || !fields.get('preferred_date') || !mode) return;
            const controller = new AbortController();
            request = controller;
            const url = new URL(block.dataset.directSlots, window.location.href);
            url.search = new URLSearchParams({ date: fields.get('preferred_date'), motif: fields.get('motive'), mode });
            status.textContent = 'Recherche des créneaux…';
            try {
                const response = await fetch(url, { signal: controller.signal });
                if (!response.ok) throw new Error('Créneaux indisponibles');
                const times = await response.json();
                if (request !== controller) return;
                const previous = select.dataset.previousTime;
                select.dataset.previousTime = '';
                times.forEach((time) => select.add(new Option(time, time)));
                if (previous && times.includes(previous)) {
                    select.value = previous;
                    updateSubmit();
                } else if (previous) {
                    status.textContent = 'Le créneau choisi a changé. Sélectionnez une autre heure ou envoyez une demande.';
                    return;
                }
                status.textContent = times.length ? '' : 'Aucun créneau immédiat. Vous pouvez envoyer une demande.';
            } catch (error) {
                if (error.name !== 'AbortError' && request === controller) {
                    status.textContent = 'Les créneaux sont indisponibles. Vous pouvez envoyer une demande.';
                }
            }
        };
        form.addEventListener('change', (event) => {
            if (['motive', 'mode', 'preferred_date'].includes(event.target.name)) load();
            if (event.target === select) updateSubmit();
        });
        load();
    });

    // Phase 12 : « Autre pays » ouvre la saisie libre ; les documents à préparer suivent le pays et le motif choisis
    document.querySelectorAll('[data-demarche-pieces]').forEach((box) => {
        const form = box.closest('form');
        const pays = form.querySelector('select[name="destination"]');
        const motif = form.querySelector('select[name="purpose"]');
        const autre = form.querySelector('[data-autre-pays]');
        let request;
        const toggleAutre = () => {
            if (!autre || !pays) return;
            autre.hidden = pays.value !== '__autre';
            if (autre.hidden) autre.querySelector('input').value = '';
        };
        const refresh = async () => {
            request?.abort();
            const controller = new AbortController();
            request = controller;
            const url = new URL(box.dataset.url, window.location.href);
            url.search = new URLSearchParams({ service: box.dataset.service, pays: pays?.value || '', motif: motif?.value || '' });
            try {
                const response = await fetch(url, { signal: controller.signal });
                if (!response.ok) return;
                const html = await response.text();
                if (request === controller) box.innerHTML = html;
            } catch (error) {
                // Réseau indisponible : la liste générale reste affichée
            }
        };
        [pays, motif].forEach((field) => field?.addEventListener('change', () => { toggleAutre(); refresh(); }));
        toggleAutre();
        if (pays?.value || motif?.value) refresh();
    });

    // Retour arrière du navigateur après un envoi : le bouton redevient utilisable.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-portal-form] button[aria-busy="true"]').forEach((button) => {
            button.removeAttribute('aria-busy');
            if (button.dataset.busyLabel) button.innerHTML = button.dataset.busyLabel;
        });
    });
})();
