// Assistant IA Taysir (docs/assistant-ia.md).
// La réponse arrive en flux (POST /assistant, text/event-stream) ; le texte reçu passe par une file et s'écrit
// caractère par caractère à vitesse régulière (machine à écrire), sans jamais prendre de retard sur le flux.
// La mise en forme (gras, listes, titres, liens) est construite à partir de texte échappé : aucun HTML de l'IA n'est injecté.
(() => {
    const root = document.querySelector('[data-assistant]');
    if (!root) {
        return;
    }
    const panel = root.querySelector('[data-assistant-panel]');
    const launcher = root.querySelector('[data-assistant-open]');
    const greeting = launcher.querySelector('[data-assistant-greeting]');
    const thread = root.querySelector('[data-assistant-thread]');
    const welcome = root.querySelector('[data-assistant-welcome]');
    const form = root.querySelector('[data-assistant-form]');
    const input = root.querySelector('[data-assistant-input]');
    const deep = root.querySelector('[data-assistant-deep]');
    const announce = root.querySelector('[data-assistant-announce]');
    const endpoint = root.dataset.endpoint;
    const csrf = root.dataset.csrf;
    const STORE = 'taysir-assistant';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const avatar = root.querySelector('.assistant-avatar').innerHTML;

    let conversation = [];      // [{role, content, sources}]
    let controller = null;      // AbortController de la réponse en cours

    /* ---------- Mise en forme Markdown sûre ---------- */
    const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const safeUrl = (url) => (/^(https?:\/\/|\/)/i.test(url) ? url : null);
    const inline = (text) => {
        let html = escapeHtml(text);
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        html = html.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (match, label, url) => {
            const href = safeUrl(url.replace(/&amp;/g, '&'));
            if (!href) {
                return label;
            }
            const external = /^https?:\/\//i.test(href) && !href.startsWith(location.origin);
            return `<a href="${escapeHtml(href)}"${external ? ' target="_blank" rel="noopener noreferrer"' : ''}>${label}</a>`;
        });
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/(^|[^*])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>');
        return html;
    };
    const markdown = (source) => {
        const lines = source.replace(/\r/g, '').split('\n');
        let html = '';
        let list = null;
        let paragraph = [];
        const flushParagraph = () => {
            if (paragraph.length) {
                html += `<p>${paragraph.map(inline).join('<br>')}</p>`;
                paragraph = [];
            }
        };
        const closeList = () => {
            if (list) {
                html += `</${list}>`;
                list = null;
            }
        };
        for (const raw of lines) {
            const line = raw.trimEnd();
            let match;
            if (!line.trim()) {
                flushParagraph();
                closeList();
            } else if ((match = line.match(/^\s*#{1,6}\s+(.*)$/))) {
                flushParagraph();
                closeList();
                html += `<h3>${inline(match[1])}</h3>`;
            } else if ((match = line.match(/^\s*(?:[-*•])\s+(.*)$/)) || (match = line.match(/^\s*\d+[.)]\s+(.*)$/))) {
                flushParagraph();
                const type = /^\s*\d/.test(line) ? 'ol' : 'ul';
                if (list !== type) {
                    closeList();
                    html += `<${type}>`;
                    list = type;
                }
                html += `<li>${inline(match[1])}</li>`;
            } else {
                closeList();
                paragraph.push(line.trim());
            }
        }
        flushParagraph();
        closeList();
        return html;
    };

    /* ---------- Fil de conversation ---------- */
    const nearBottom = () => thread.scrollHeight - thread.scrollTop - thread.clientHeight < 80;
    const scrollDown = (force = false) => {
        if (force || nearBottom()) {
            thread.scrollTop = thread.scrollHeight;
        }
    };

    const addUser = (text) => {
        welcome.hidden = true;
        const item = document.createElement('div');
        item.className = 'assistant-msg is-user';
        const bubble = document.createElement('div');
        bubble.className = 'assistant-bubble';
        bubble.textContent = text;
        item.append(bubble);
        thread.append(item);
        scrollDown(true);
    };

    const addBot = () => {
        const item = document.createElement('div');
        item.className = 'assistant-msg is-bot';
        item.innerHTML = `<span class="assistant-avatar" aria-hidden="true">${avatar}</span>
            <div>
                <ul class="assistant-steps" hidden></ul>
                <p class="assistant-status"><span class="assistant-status-dots" aria-hidden="true"><i></i><i></i><i></i></span><span>Réflexion</span></p>
                <div class="assistant-answer"></div>
                <ul class="assistant-sources" hidden></ul>
            </div>`;
        thread.append(item);
        scrollDown(true);
        return {
            steps: item.querySelector('.assistant-steps'),
            status: item.querySelector('.assistant-status'),
            statusText: item.querySelector('.assistant-status span:last-child'),
            answer: item.querySelector('.assistant-answer'),
            sources: item.querySelector('.assistant-sources'),
        };
    };

    const renderSources = (box, items) => {
        if (!items?.length) {
            return;
        }
        box.innerHTML = items.map((source) => {
            const href = safeUrl(source.url);
            if (!href) {
                return '';
            }
            const site = source.kind === 'site';
            let host = '';
            try {
                host = new URL(href, location.origin).hostname.replace(/^www\./, '');
            } catch {
                host = href;
            }
            const label = site ? source.title : (source.title && source.title !== source.url ? source.title : host);
            return `<li><a class="${site ? 'is-site' : 'is-web'}" href="${escapeHtml(href)}"${site ? '' : ' target="_blank" rel="noopener noreferrer"'} title="${escapeHtml(site ? source.title : host)}">`
                + `<span class="assistant-source-badge" aria-hidden="true">${site ? 'T' : escapeHtml(host.charAt(0))}</span><span>${escapeHtml(label)}</span></a></li>`;
        }).join('');
        box.hidden = false;
    };

    /* ---------- Machine à écrire ---------- */
    const typewriter = (target, onIdle) => {
        let received = '';
        let shown = 0;
        let ended = false;
        let frame = 0;
        const caret = '<span class="assistant-caret" aria-hidden="true"></span>';
        const tick = () => {
            frame = 0;
            const remaining = received.length - shown;
            if (remaining > 0) {
                // Vitesse régulière (environ 60 caractères/s), accélérée si le flux prend de l'avance.
                shown += reduceMotion.matches ? remaining : Math.max(1, Math.ceil(remaining / 45));
                target.innerHTML = markdown(received.slice(0, shown)) + caret;
                // Le curseur suit le dernier mot : il entre dans le dernier paragraphe, titre ou élément de liste.
                const cursor = target.lastElementChild;
                const block = cursor?.previousElementSibling;
                if (block) {
                    (/^(UL|OL)$/.test(block.tagName) && block.lastElementChild ? block.lastElementChild : block).append(cursor);
                }
                scrollDown();
            }
            if (shown < received.length || !ended) {
                frame = requestAnimationFrame(tick);
            } else {
                target.innerHTML = markdown(received);
                onIdle(received);
            }
        };
        frame = requestAnimationFrame(tick);
        return {
            push(text) {
                received += text;
            },
            end() {
                ended = true;
            },
            stop() {
                cancelAnimationFrame(frame);
                target.innerHTML = markdown(received.slice(0, Math.max(shown, 0)));
                return received.slice(0, shown);
            },
            get text() {
                return received;
            },
        };
    };

    /* ---------- Envoi et flux ---------- */
    const setBusy = (busy) => {
        root.classList.toggle('is-busy', busy);
        root.querySelector('[data-assistant-send]').setAttribute('aria-label', busy ? 'Arrêter la réponse' : 'Envoyer');
    };

    const save = () => {
        try {
            sessionStorage.setItem(STORE, JSON.stringify(conversation.slice(-30)));
        } catch {
            // Navigation privée ou stockage plein : la conversation reste en mémoire.
        }
    };

    const ask = async (question) => {
        const history = conversation.filter((turn) => turn.content).map(({ role, content }) => ({ role, content }));
        addUser(question);
        conversation.push({ role: 'user', content: question });
        save();
        const view = addBot();
        let sources = [];
        let errorText = '';
        let finished = false;
        setBusy(true);
        controller = new AbortController();

        const done = new Promise((resolve) => {
            const writer = typewriter(view.answer, (text) => resolve(text));
            const finish = () => {
                if (!finished) {
                    finished = true;
                    writer.end();
                }
            };

            const handle = (event) => {
                if (event.type === 'status') {
                    if (view.statusText.textContent && view.status.dataset.kind && view.status.dataset.kind !== 'platform') {
                        const step = document.createElement('li');
                        step.textContent = view.statusText.textContent;
                        view.steps.append(step);
                        view.steps.hidden = false;
                    }
                    view.status.dataset.kind = event.kind;
                    view.statusText.textContent = event.text;
                    view.status.hidden = false;
                } else if (event.type === 'delta') {
                    if (!view.status.hidden) {
                        if (view.status.dataset.kind && view.status.dataset.kind !== 'platform') {
                            const step = document.createElement('li');
                            step.textContent = view.statusText.textContent;
                            view.steps.append(step);
                            view.steps.hidden = false;
                        }
                        view.status.hidden = true;
                    }
                    writer.push(event.text);
                } else if (event.type === 'sources') {
                    sources = event.items || [];
                } else if (event.type === 'error') {
                    errorText = event.text;
                } else if (event.type === 'done') {
                    finish();
                }
            };

            (async () => {
                try {
                    const body = new FormData();
                    body.append('_csrf', csrf);
                    body.append('question', question);
                    body.append('history', JSON.stringify(history));
                    body.append('deep', deep.checked ? '1' : '0');
                    const response = await fetch(endpoint, { method: 'POST', body, credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'text/event-stream' } });
                    if (!response.ok || !(response.headers.get('content-type') || '').includes('text/event-stream')) {
                        errorText = response.status === 419 || response.status === 403
                            ? 'Votre session a expiré. Rechargez la page pour continuer la conversation.'
                            : 'L’assistant est indisponible pour le moment. Réessayez dans un instant.';
                        finish();
                        return;
                    }
                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';
                    for (;;) {
                        const { value, done: streamDone } = await reader.read();
                        if (streamDone) {
                            break;
                        }
                        buffer += decoder.decode(value, { stream: true });
                        let index;
                        while ((index = buffer.indexOf('\n\n')) !== -1) {
                            const block = buffer.slice(0, index);
                            buffer = buffer.slice(index + 2);
                            for (const line of block.split('\n')) {
                                if (line.startsWith('data:')) {
                                    try {
                                        handle(JSON.parse(line.slice(5)));
                                    } catch {
                                        // ligne incomplète ou inattendue : ignorée
                                    }
                                }
                            }
                        }
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        errorText = 'La connexion a été interrompue. Vérifiez votre réseau et réessayez.';
                    }
                }
                finish();
            })();

            controller.signal.addEventListener('abort', () => {
                finished = true;
                resolve(writer.stop());
            }, { once: true });
        });

        const text = await done;
        view.status.hidden = true;
        if (!text && errorText) {
            view.answer.classList.add('is-error');
            view.answer.innerHTML = markdown(errorText);
        } else if (!text && controller?.signal.aborted) {
            view.answer.innerHTML = '<p><em>Réponse interrompue.</em></p>';
        }
        renderSources(view.sources, sources);
        conversation.push({ role: 'assistant', content: text, sources, error: text ? '' : errorText });
        save();
        announce.textContent = (text || errorText).replace(/[#*`[\]()]/g, '').slice(0, 600);
        controller = null;
        setBusy(false);
        scrollDown();
        input.focus();
    };

    /* ---------- Restauration de la conversation de l'onglet ---------- */
    const restore = () => {
        try {
            conversation = JSON.parse(sessionStorage.getItem(STORE) || '[]');
        } catch {
            conversation = [];
        }
        if (!Array.isArray(conversation)) {
            conversation = [];
        }
        for (const turn of conversation) {
            if (turn.role === 'user') {
                addUser(turn.content);
            } else {
                const view = addBot();
                view.status.hidden = true;
                view.answer.innerHTML = markdown(turn.content || turn.error || '');
                view.answer.classList.toggle('is-error', !turn.content);
                renderSources(view.sources, turn.sources);
            }
        }
    };

    /* ---------- Ouverture, fermeture, saisie ---------- */
    const open = () => {
        greeting.hidden = true;
        panel.hidden = false;
        root.classList.add('is-open');
        launcher.setAttribute('aria-expanded', 'true');
        if (!thread.dataset.ready) {
            thread.dataset.ready = '1';
            restore();
        }
        scrollDown(true);
        setTimeout(() => input.focus(), 50);
    };
    const close = () => {
        panel.hidden = true;
        root.classList.remove('is-open');
        launcher.setAttribute('aria-expanded', 'false');
        launcher.focus();
    };

    launcher.addEventListener('click', open);
    root.querySelector('[data-assistant-close]').addEventListener('click', close);
    panel.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
    root.querySelector('[data-assistant-reset]').addEventListener('click', () => {
        controller?.abort();
        conversation = [];
        save();
        thread.querySelectorAll('.assistant-msg').forEach((item) => item.remove());
        welcome.hidden = false;
        input.focus();
    });
    root.querySelectorAll('[data-assistant-suggestion]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!controller) {
                ask(button.textContent.trim());
            }
        });
    });

    const autosize = () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 144)}px`;
    };
    input.addEventListener('input', autosize);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (controller) {
            controller.abort();
            return;
        }
        const question = input.value.trim();
        if (!question) {
            return;
        }
        input.value = '';
        autosize();
        ask(question);
    });
})();
