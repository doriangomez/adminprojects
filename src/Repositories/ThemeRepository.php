<?php

declare(strict_types=1);

namespace App\Repositories;

use ConfigService;
use Database;

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

        $legacyBasePublicPath = '/project/public';

        if (str_starts_with($logoUrl, $legacyBasePublicPath . '/')) {
            $logoUrl = substr($logoUrl, strlen($legacyBasePublicPath));
        }

        if (!str_starts_with($logoUrl, '/uploads/logos/')) {
            return $fallback;
        }

        $logoPath = $this->publicPathFromUrl($logoUrl);
        if (!$logoPath || !is_file($logoPath)) {
            return $fallback;
        }

        return $logoUrl;
    }

    private function publicPathFromUrl(string $url): ?string
    {
        if (!str_starts_with($url, '/uploads/')) {
            return null;
        }

        return __DIR__ . '/../../public' . $url;
    }

    private function normalizeTheme(array $theme, array $defaultTheme): array
    {
        $theme['primary'] = $this->pickThemeValue($theme['primary'] ?? null, $defaultTheme['primary'] ?? null);
        $theme['secondary'] = $this->pickThemeValue($theme['secondary'] ?? null, $defaultTheme['secondary'] ?? null);
        $theme['accent'] = $this->pickThemeValue($theme['accent'] ?? null, $defaultTheme['accent'] ?? null, $theme['primary']);
        $theme['background'] = $this->pickThemeValue($theme['background'] ?? null, $defaultTheme['background'] ?? null);
        $theme['surface'] = $this->pickThemeValue($theme['surface'] ?? null, $defaultTheme['surface'] ?? null);
        $theme['border'] = $this->pickThemeValue($theme['border'] ?? null, $defaultTheme['border'] ?? null);
        $theme['success'] = $this->pickThemeValue($theme['success'] ?? null, $defaultTheme['success'] ?? null, $theme['accent']);
        $theme['warning'] = $this->pickThemeValue($theme['warning'] ?? null, $defaultTheme['warning'] ?? null, $theme['accent']);
        $theme['danger'] = $this->pickThemeValue($theme['danger'] ?? null, $defaultTheme['danger'] ?? null, $theme['secondary']);
        $theme['info'] = $this->pickThemeValue($theme['info'] ?? null, $defaultTheme['info'] ?? null, $theme['primary']);
        $theme['neutral'] = $this->pickThemeValue($theme['neutral'] ?? null, $defaultTheme['neutral'] ?? null, $theme['secondary']);

        $textPrimary = $this->pickThemeValue(
            $theme['textPrimary'] ?? null,
            $theme['text_primary'] ?? null,
            $theme['text_main'] ?? null,
            $defaultTheme['textPrimary'] ?? null,
            $defaultTheme['text_primary'] ?? null,
            $defaultTheme['text_main'] ?? null
        );
        $textSecondary = $this->pickThemeValue(
            $theme['textSecondary'] ?? null,
            $theme['text_secondary'] ?? null,
            $theme['text_muted'] ?? null,
            $defaultTheme['textSecondary'] ?? null,
            $defaultTheme['text_secondary'] ?? null,
            $defaultTheme['text_muted'] ?? null
        );
        $disabled = $this->pickThemeValue(
            $theme['disabled'] ?? null,
            $theme['text_disabled'] ?? null,
            $theme['text_soft'] ?? null,
            $defaultTheme['disabled'] ?? null,
            $defaultTheme['text_disabled'] ?? null,
            $defaultTheme['text_soft'] ?? null
        );

        $theme['textPrimary'] = $textPrimary;
        $theme['textSecondary'] = $textSecondary;
        $theme['disabled'] = $disabled;
        $theme['text_main'] = $textPrimary;
        $theme['text_muted'] = $textSecondary;
        $theme['text_soft'] = $disabled;
        $theme['text_disabled'] = $disabled;

        return $theme;
    }

    private function pickThemeValue(mixed ...$values): string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $candidate = trim((string) $value);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
