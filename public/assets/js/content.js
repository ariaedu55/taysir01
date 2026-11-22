// Listes de métiers, d'écoles et du blog : recherche. Sans JavaScript, tout reste affiché.
(() => {
    document.querySelectorAll('[data-filter]').forEach((zone) => {
        const bar = zone.querySelector('[data-filter-bar]');
        const search = zone.querySelector('[data-filter-search]');
        const items = [...zone.querySelectorAll('[data-filter-items] > li')];
        const status = zone.querySelector('[data-filter-status]');
        const empty = zone.querySelector('[data-filter-empty]');

        // Recherche sans tenir compte des accents ni des majuscules.
        const normalize = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        items.forEach((item) => { item.dataset.normalized = normalize(item.dataset.search || item.textContent); });

        const apply = () => {
            const query = normalize(search?.value.trim() ?? '');
            let shown = 0;
            items.forEach((item) => {
                const visible = query === '' || item.dataset.normalized.includes(query);
                item.hidden = !visible;
                shown += visible ? 1 : 0;
            });
            empty.hidden = shown > 0;
            status.textContent = `${shown} résultat${shown > 1 ? 's' : ''}`;
        };

        bar.hidden = false;
        search?.addEventListener('input', apply);
    });
})();

// Vidéo externe : l'iframe YouTube (sans cookies) n'est chargée qu'au clic.
(() => {
    document.querySelectorAll('[data-video]').forEach((frame) => {
        frame.querySelector('button')?.addEventListener('click', () => {
            const iframe = document.createElement('iframe');
            iframe.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(frame.dataset.video)}?autoplay=1&rel=0`;
            iframe.title = frame.dataset.videoTitle || 'Vidéo';
            iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture';
            iframe.allowFullscreen = true;
            frame.replaceChildren(iframe);
            frame.classList.add('is-playing');
        }, { once: true });
    });
})();
