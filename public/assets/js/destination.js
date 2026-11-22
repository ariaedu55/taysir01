// Fiche pays : ouvre la rubrique visée par le sommaire et signale la rubrique en cours de lecture.
(() => {
    const sections = [...document.querySelectorAll('.country-section')];
    if (sections.length === 0) {
        return;
    }

    const openTarget = () => {
        const target = location.hash ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
        if (target instanceof HTMLDetailsElement) {
            target.open = true;
            target.scrollIntoView({ block: 'start' });
        }
    };
    window.addEventListener('hashchange', openTarget);
    openTarget();

    const links = new Map([...document.querySelectorAll('.country-toc a')].map((link) => [link.hash.slice(1), link]));
    if (links.size === 0 || !('IntersectionObserver' in window)) {
        return;
    }
    const visible = new Set();
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => (entry.isIntersecting ? visible.add(entry.target.id) : visible.delete(entry.target.id)));
        const current = sections.find((section) => visible.has(section.id));
        links.forEach((link, id) => {
            const active = current !== undefined && id === current.id;
            link.classList.toggle('is-active', active);
            if (active) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }, { rootMargin: '-10% 0px -60% 0px' });
    sections.forEach((section) => observer.observe(section));
})();
