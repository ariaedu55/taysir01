<?php
/**
 * Assistant IA Taysir (docs/assistant-ia.md) : bouton flottant et panneau de conversation, sur toutes les pages.
 * Le panneau est rempli par public/assets/js/assistant.js ; les réponses arrivent en flux depuis POST /assistant.
 */
use App\Core\Session;

$suggestions = [
    'Quelles études après un bac S2 au Sénégal ?',
    'Comment obtenir un visa étudiant pour la France ?',
    'Quelles universités publiques pour étudier l’informatique ?',
    'Quel budget prévoir pour étudier au Canada ?',
];
$portrait = asset('images/assistant/conseillere-taysir.webp');
?>
<div class="assistant" data-assistant data-endpoint="<?= e(url('/assistant/')) ?>" data-csrf="<?= e(Session::csrfToken()) ?>">
    <button class="assistant-launcher" type="button" aria-haspopup="dialog" aria-controls="assistant-panel" aria-expanded="false" data-assistant-open>
        <span class="assistant-launcher-icon" aria-hidden="true">
            <img class="assistant-portrait" src="<?= e($portrait) ?>" width="192" height="192" alt="" decoding="async">
        </span>
        <span class="assistant-launcher-label">Assistant Taysir</span>
        <span class="assistant-greeting" data-assistant-greeting aria-hidden="true">Comment puis-je vous aider ?</span>
    </button>

    <section class="assistant-panel" id="assistant-panel" role="dialog" aria-modal="false" aria-labelledby="assistant-titre" hidden data-assistant-panel>
        <header class="assistant-head">
            <span class="assistant-avatar" aria-hidden="true">
                <img class="assistant-portrait" src="<?= e($portrait) ?>" width="192" height="192" alt="" decoding="async">
            </span>
            <div>
                <h2 id="assistant-titre">Assistant Taysir</h2>
                <p>Orientation, études, visa, voyage : posez votre question.</p>
            </div>
            <button class="assistant-icon-button" type="button" data-assistant-reset title="Nouvelle conversation" aria-label="Nouvelle conversation">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <button class="assistant-icon-button" type="button" data-assistant-close aria-label="Fermer l’assistant">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="assistant-thread" data-assistant-thread aria-live="off">
            <div class="assistant-welcome" data-assistant-welcome>
                <p class="assistant-welcome-title">Bonjour, je suis <em>l’assistant</em> de Taysir.</p>
                <p>Je connais nos destinations, universités, fiches métiers et démarches, et je peux chercher en ligne une information récente.</p>
                <ul class="assistant-suggestions">
                    <?php foreach ($suggestions as $suggestion): ?>
                        <li><button type="button" data-assistant-suggestion><?= e($suggestion) ?></button></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <p class="sr-only" aria-live="polite" data-assistant-announce></p>

        <form class="assistant-form" data-assistant-form>
            <label class="sr-only" for="assistant-question">Votre question</label>
            <textarea id="assistant-question" name="question" rows="1" maxlength="1500" placeholder="Posez votre question…" required data-assistant-input></textarea>
            <div class="assistant-form-bar">
                <label class="assistant-deep">
                    <input type="checkbox" data-assistant-deep>
                    <span class="assistant-switch" aria-hidden="true"></span>
                    Recherche approfondie
                </label>
                <button class="assistant-send" type="submit" aria-label="Envoyer" data-assistant-send>
                    <svg class="assistant-send-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    <svg class="assistant-stop-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2" fill="currentColor"/></svg>
                </button>
            </div>
            <p class="assistant-note">L’IA peut se tromper : vérifiez les informations importantes avec un conseiller.</p>
        </form>
    </section>
</div>
