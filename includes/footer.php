<?php
declare(strict_types=1);
$assistantApiKey = getenv('GEMINI_API_KEY');
if (!is_string($assistantApiKey) || trim($assistantApiKey) === '') {
    $assistantApiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
}
$assistantGeminiEnabled = is_string($assistantApiKey) && trim($assistantApiKey) !== '';
?>
</main>
<footer class="site-footer">
  <div class="shell footer-grid">
    <a href="<?= e(url()) ?>" class="brand brand-light"><span class="brand-mark">G</span><span>giftly</span></a>
    <p>Matching good clothes with their next great chapter.</p>
    <p class="footer-note">© <?= date('Y') ?> Giftly · Built for neighbourhood impact.</p>
  </div>
</footer>
<div class="assistant-widget" id="giftly-assistant" data-endpoint="<?=e(url('assistant.php'))?>" data-base-url="<?=e(url())?>" data-csrf="<?=e(csrf_token())?>">
  <button class="assistant-launcher" type="button" aria-label="Open GIFTLY Assistant" aria-expanded="false" aria-controls="assistant-panel"><span class="assistant-launcher-mark">G</span><span class="assistant-launcher-label">Ask Giftly</span><span class="assistant-launcher-spark">✦</span></button>
  <section class="assistant-panel" id="assistant-panel" role="dialog" aria-label="GIFTLY Assistant" aria-modal="false" hidden>
    <header class="assistant-header"><span class="assistant-avatar">G</span><div><strong>GIFTLY Assistant</strong><small>Friendly help · always here</small></div><button class="assistant-clear" type="button" aria-label="Clear conversation">Clear</button><button class="assistant-close" type="button" aria-label="Close assistant">×</button></header>
    <div class="assistant-messages" role="log" aria-live="polite" aria-relevant="additions text"></div>
    <div class="assistant-suggestions" aria-label="Suggested questions"><button type="button">Find winter jackets in size M</button><button type="button">How do pledges work?</button><button type="button">Explain donation statuses</button></div>
    <form class="assistant-form"><label class="sr-only" for="assistant-input">Message the GIFTLY Assistant</label><textarea id="assistant-input" rows="1" maxlength="1000" placeholder="Ask about clothing needs…" required></textarea><button type="submit" aria-label="Send message">↑</button></form>
    <p class="assistant-disclaimer"><?= $assistantGeminiEnabled ? 'AI replies use Google Gemini. Messages you send are shared with Google.' : 'Local rule-based help. Gemini is not configured.' ?></p>
  </section>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?: '1' ?>"></script>
<script src="<?= e(url('assets/js/upgrade.js')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/upgrade.js') ?: '1' ?>"></script>
<script src="<?= e(url('assets/js/assistant.js')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/assistant.js') ?: '1' ?>"></script>
</body>
</html>
