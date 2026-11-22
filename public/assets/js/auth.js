// Formulaires du portail : bouton pour afficher ou masquer le mot de passe.
(() => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        const label = button.querySelector('.sr-only');
        if (!input) {
            return;
        }
        input.dataset.password = '';
        button.hidden = false;
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            label.textContent = show ? 'Masquer le mot de passe' : 'Afficher le mot de passe';
            input.focus();
        });
    });
})();
