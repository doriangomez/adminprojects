<?php

declare(strict_types=1);

class ThemeRepository
{
    private ConfigService $configService;

    public function __construct(?Database $db = null)
    {
        $this->configService = new ConfigService($db);
    }

    public function getActiveTheme(): array
    {
        $config = $this->configService->getConfig();
        $defaults = $this->configService->getDefaults();
        $defaultTheme = $defaults['theme'] ?? [];
        $theme = array_merge($defaultTheme, $config['theme'] ?? []);
        $theme = $this->normalizeTheme($theme, $defaultTheme);

        $logoUrl = $this->normalizeLogoUrl((string) ($theme['logo'] ?? ''), (string) ($defaultTheme['logo'] ?? ''));
        $theme['logo_url'] = $logoUrl;

        return $theme;
    }

    private function normalizeLogoUrl(string $logoUrl, string $fallback): string
    {
        $logoUrl = trim($logoUrl);
        if ($logoUrl === '') {
            return $fallback;
        }

        if (preg_match('/^https?:\/\//i', $logoUrl)) {
            return $logoUrl;
        }

        $basePublicPath = '/project/public';
        $uploadsPrefix = $basePublicPath . '/uploads/logos/';

        if (str_starts_with($logoUrl, '/uploads/logos/')) {
            $logoUrl = $basePublicPath . $logoUrl;
        }

        if (!str_starts_with($logoUrl, $uploadsPrefix)) {
            return $fallback;
        }

        $logoPath = $this->publicPathFromUrl($logoUrl, $basePublicPath);
        if (!$logoPath || !is_file($logoPath)) {
            return $fallback;
        }

        return $logoUrl;
    }

    private function publicPathFromUrl(string $url, string $basePublicPath): ?string
    {
        if (!str_starts_with($url, $basePublicPath)) {
            return null;
        }

        $relativePath = substr($url, strlen($basePublicPath));

        return __DIR__ . '/../../public' . $relativePath;
    }

    private function normalizeTheme(array $theme, array $defaultTheme): array
    {
        $theme['primary'] = $theme['primary'] ?? $defaultTheme['primary'] ?? '';
        $theme['secondary'] = $theme['secondary'] ?? $defaultTheme['secondary'] ?? '';
        $theme['accent'] = $theme['accent'] ?? $defaultTheme['accent'] ?? $theme['primary'];
        $theme['background'] = $theme['background'] ?? $defaultTheme['background'] ?? '';
        $theme['surface'] = $theme['surface'] ?? $defaultTheme['surface'] ?? '';
        $theme['border'] = $theme['border'] ?? $defaultTheme['border'] ?? '';
        $theme['success'] = $theme['success'] ?? $defaultTheme['success'] ?? $theme['accent'];
        $theme['warning'] = $theme['warning'] ?? $defaultTheme['warning'] ?? $theme['accent'];
        $theme['danger'] = $theme['danger'] ?? $defaultTheme['danger'] ?? $theme['secondary'];
        $theme['info'] = $theme['info'] ?? $defaultTheme['info'] ?? $theme['primary'];
        $theme['neutral'] = $theme['neutral'] ?? $defaultTheme['neutral'] ?? $theme['secondary'];

        $textPrimary = $theme['textPrimary']
            ?? $theme['text_primary']
            ?? $theme['text_main']
            ?? $defaultTheme['textPrimary']
            ?? $defaultTheme['text_primary']
            ?? $defaultTheme['text_main']
            ?? '';
        $textSecondary = $theme['textSecondary']
            ?? $theme['text_secondary']
            ?? $theme['text_muted']
            ?? $defaultTheme['textSecondary']
            ?? $defaultTheme['text_secondary']
            ?? $defaultTheme['text_muted']
            ?? '';
        $disabled = $theme['disabled']
            ?? $theme['text_disabled']
            ?? $theme['text_soft']
            ?? $defaultTheme['disabled']
            ?? $defaultTheme['text_disabled']
            ?? $defaultTheme['text_soft']
            ?? '';

        $theme['textPrimary'] = $textPrimary;
        $theme['textSecondary'] = $textSecondary;
        $theme['disabled'] = $disabled;
        $theme['text_main'] = $textPrimary;
        $theme['text_muted'] = $textSecondary;
        $theme['text_soft'] = $disabled;
        $theme['text_disabled'] = $disabled;

        return $theme;
    }
}
