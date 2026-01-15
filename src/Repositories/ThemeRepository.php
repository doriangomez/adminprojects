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
        $textSoft = $theme['text_soft'] ?? $theme['text_disabled'] ?? ($defaultTheme['text_soft'] ?? $defaultTheme['text_disabled'] ?? null);
        if ($textSoft !== null) {
            $theme['text_soft'] = $textSoft;
            $theme['text_disabled'] = $textSoft;
        }

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
}
