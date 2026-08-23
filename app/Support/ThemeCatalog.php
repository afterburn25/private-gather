<?php

declare(strict_types=1);

namespace App\Support;

final class ThemeCatalog
{
    public const PLATFORM_DEFAULT = 'midnight-luxe';
    public const TENANT_DEFAULT = 'velvet-lounge';

    public static function platformThemes(): array
    {
        return [
            'midnight-luxe'=>['label'=>'Midnight Luxe','description'=>'Cinematic black, plum and gold with split heroes and luxury event cards.','layout'=>'cinematic','preview'=>'dark-luxe'],
            'editorial-noir'=>['label'=>'Editorial Noir','description'=>'High-fashion editorial typography, asymmetric whitespace and magazine-style discovery.','layout'=>'editorial','preview'=>'editorial-light'],
            'velvet-neon'=>['label'=>'Velvet Neon','description'=>'Nightclub energy with saturated purple glow, compact controls and immersive card rails.','layout'=>'neon','preview'=>'neon-rail'],
            'golden-society'=>['label'=>'Golden Society','description'=>'Formal black-and-gold presentation with centered composition and private-club polish.','layout'=>'society','preview'=>'society-centered'],
            'modern-afterdark'=>['label'=>'Modern Afterdark','description'=>'Clean charcoal interface, wide content grid and contemporary social-network styling.','layout'=>'modern','preview'=>'modern-grid'],
        ];
    }

    public static function tenantThemes(): array
    {
        return [
            'velvet-lounge'=>['label'=>'Velvet Lounge','description'=>'Deep plum, warm gold and intimate lounge styling.','layout'=>'lounge','preview'=>'velvet'],
            'art-deco-house'=>['label'=>'Art Deco House','description'=>'Centered club crest, geometric gold lines and symmetrical event presentation.','layout'=>'artdeco','preview'=>'deco'],
            'neon-underground'=>['label'=>'Neon Underground','description'=>'Fixed side navigation, electric accents and club-night poster styling.','layout'=>'underground','preview'=>'neon'],
            'resort-night'=>['label'=>'Resort Night','description'=>'Full-width destination imagery, airy sections and luxury resort presentation.','layout'=>'resort','preview'=>'resort'],
            'gallery-minimal'=>['label'=>'Gallery Minimal','description'=>'Bright editorial canvas, sharp black type and gallery-like image blocks.','layout'=>'gallery','preview'=>'gallery'],
            'garden-estate'=>['label'=>'Garden Estate','description'=>'Forest, cream and brass styling for private estate and destination communities.','layout'=>'estate','preview'=>'estate'],
            'industrial-loft'=>['label'=>'Industrial Loft','description'=>'Steel, graphite, squared panels and bold condensed club typography.','layout'=>'industrial','preview'=>'industrial'],
            'cabaret-rouge'=>['label'=>'Cabaret Rouge','description'=>'Red velvet, champagne accents and theatrical section framing.','layout'=>'cabaret','preview'=>'cabaret'],
            'private-villa'=>['label'=>'Private Villa','description'=>'Warm ivory, burgundy and understated old-world hospitality.','layout'=>'villa','preview'=>'villa'],
            'social-club'=>['label'=>'Social Club','description'=>'Modern modular layout, brighter cards and member-first navigation.','layout'=>'social','preview'=>'social'],
        ];
    }

    public static function tenantPresets(): array { return self::tenantThemes(); }
    public static function platformPresets(): array { return self::platformThemes(); }

    public static function platform(?string $key): array
    {
        $themes=self::platformThemes(); $key=is_string($key)&&isset($themes[$key])?$key:self::PLATFORM_DEFAULT; return ['key'=>$key]+$themes[$key];
    }

    public static function tenant(?string $key): array
    {
        $themes=self::tenantThemes(); $key=is_string($key)&&isset($themes[$key])?$key:self::TENANT_DEFAULT; return ['key'=>$key]+$themes[$key];
    }

    public static function tenantPresetFromTheme(mixed $theme): string
    {
        if (is_array($theme) && is_string($theme['preset'] ?? null)) return self::tenant((string)$theme['preset'])['key'];
        if (is_string($theme)) {
            $candidate=str_starts_with($theme,'tenant:')?substr($theme,7):$theme;
            if (isset(self::tenantThemes()[$candidate])) return $candidate;
        }
        return self::TENANT_DEFAULT;
    }
}
