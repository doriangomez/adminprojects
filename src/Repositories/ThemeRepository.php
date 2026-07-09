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
        $theme['primary'] = $this->pickThemeColor('primary', $theme['primary'] ?? null, $defaultTheme['primary'] ?? null);
        $theme['secondary'] = $this->pickThemeColor('secondary', $theme['secondary'] ?? null, $defaultTheme['secondary'] ?? null);
        $theme['accent'] = $this->pickThemeColor('accent', $theme['accent'] ?? null, $defaultTheme['accent'] ?? null, $theme['primary']);
        $theme['background'] = $this->pickThemeColor('background', $theme['background'] ?? null, $defaultTheme['background'] ?? null);
        $theme['surface'] = $this->pickThemeColor('surface', $theme['surface'] ?? null, $defaultTheme['surface'] ?? null);
        $theme['border'] = $this->pickThemeColor('border', $theme['border'] ?? null, $defaultTheme['border'] ?? null);
        $theme['success'] = $this->pickThemeColor('success', $theme['success'] ?? null, $defaultTheme['success'] ?? null, $theme['accent']);
        $theme['warning'] = $this->pickThemeColor('warning', $theme['warning'] ?? null, $defaultTheme['warning'] ?? null, $theme['accent']);
        $theme['danger'] = $this->pickThemeColor('danger', $theme['danger'] ?? null, $defaultTheme['danger'] ?? null, $theme['secondary']);
        $theme['info'] = $this->pickThemeColor('info', $theme['info'] ?? null, $defaultTheme['info'] ?? null, $theme['primary']);
        $theme['neutral'] = $this->pickThemeColor('neutral', $theme['neutral'] ?? null, $defaultTheme['neutral'] ?? null, $theme['secondary']);

        $textPrimary = $this->pickThemeValue(
            $theme['textPrimary'] ?? null,
            $theme['text_primary'] ?? null,
            $theme['text_main'] ?? null,
            $defaultTheme['textPrimary'] ?? null,
            $defaultTheme['text_primary'] ?? null,
            $defaultTheme['text_main'] ?? null
        );
        $textSecondary = $this->pickThemeColor(
            'textSecondary',
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

    private function pickThemeColor(string $token, mixed ...$values): string
    {
        $value = $this->pickThemeValue(...$values);
        if ($this->isBrokenSavedColor($token, $value)) {
            return $this->semanticFallback($token);
        }

        return $value;
    }

    private function isBrokenSavedColor(string $token, string $value): bool
    {
        $hex = ltrim(strtolower(trim($value)), '#');
        return match ($token) {
            'secondary' => in_array($hex, ['fdfcfc', 'fcf7f7', 'ffffff'], true),
            'danger' => in_array($hex, ['fcf7f7', 'fdfcfc', 'ffffff'], true),
            'textSecondary' => in_array($hex, ['151414', '0d0d0d', '111110'], true),
            default => false,
        };
    }

    private function semanticFallback(string $token): string
    {
        return match ($token) {
            'secondary' => '#D6336C',
            'danger' => '#EF4444',
            'textSecondary' => '#6B7280',
            default => '',
        };
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
