/* Formulaires du portail : liste des documents choisis, contrôle du nombre, de la taille et du format
   avant l'envoi, et bouton désactivé pendant l'envoi. Le serveur refait tous ces contrôles. */
(() => {
    const allowed = /\.(pdf|jpe?g|png)$/i;
    const size = (bytes) => (bytes < 1048576 ? `${Math.max(1, Math.round(bytes / 1024))} Ko` : `${(bytes / 1048576).toFixed(1).replace('.', ',')} Mo`);

    document.querySelectorAll('[data-dropzone]').forEach((zone) => {
        const input = zone.querySelector('input[type="file"]');
        const list = zone.parentElement.querySelector('[data-dropzone-files]');
        const maxFiles = Number(input.dataset.maxFiles || 3);
        const maxSize = Number(input.dataset.maxSize || 5242880);

        const render = () => {
            list.replaceChildren();
            const files = [...input.files];
            let problem = '';
            files.forEach((file) => {
                const item = document.createElement('li');
                const name = document.createElement('strong');
                const info = document.createElement('span');
                name.textContent = file.name;
                info.textContent = size(file.size);
                if (!allowed.test(file.name)) {
                    problem = 'Formats acceptés : PDF, JPG et PNG.';
                    item.classList.add('is-invalid');
                } else if (file.size > maxSize) {
                    problem = 'Chaque document doit peser 5 Mo au maximum.';
                    item.classList.add('is-invalid');
                }
                item.append(name, info);
                list.append(item);
            });
            if (files.length > maxFiles) {
                problem = `Vous pouvez joindre ${maxFiles} documents au maximum.`;
            }
            input.setCustomValidity(problem);
            if (problem) {
                const item = document.createElement('li');
                item.className = 'is-invalid';
                item.textContent = problem;
                list.append(item);
            }
        };

        input.addEventListener('change', render);
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
                button.dataset.label = button.textContent;
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

    // Retour arrière du navigateur après un envoi : le bouton redevient utilisable.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-portal-form] button[aria-busy="true"]').forEach((button) => {
            button.removeAttribute('aria-busy');
            button.textContent = button.dataset.label || button.textContent;
        });
    });
})();
