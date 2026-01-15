<?php
if (!function_exists('render_brand_logo')) {
    function render_brand_logo(string $logoUrl, string $fallbackText, string $imgClass = 'brand-logo', string $fallbackClass = 'brand-fallback'): void
    {
        $hasLogo = trim($logoUrl) !== '';
        $fallbackClassAttr = $fallbackClass . ($hasLogo ? ' is-hidden' : '');
        ?>
        <?php if ($hasLogo): ?>
            <img class="<?= htmlspecialchars($imgClass) ?>" src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($fallbackText) ?> logo" onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('is-hidden');">
        <?php endif; ?>
        <span class="<?= htmlspecialchars($fallbackClassAttr) ?>"><?= htmlspecialchars($fallbackText) ?></span>
        <?php
    }
}
