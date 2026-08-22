<?php

declare(strict_types=1);

namespace App\Support;

final class LifestyleProfileOptions
{
    public static function identities(): array
    {
        return [
            'couple' => 'Couple / shared profile',
            'single_woman' => 'Single woman',
            'single_man' => 'Single man',
            'nonbinary' => 'Nonbinary / gender-diverse member',
            'individual' => 'Individual / prefer not to specify',
        ];
    }

    public static function relationshipStatuses(): array
    {
        return [
            'single' => 'Single',
            'dating' => 'Dating',
            'partnered' => 'Partnered',
            'married' => 'Married',
            'open_relationship' => 'Open relationship',
            'polyamorous' => 'Polyamorous',
            'prefer_not_to_say' => 'Prefer not to say',
        ];
    }

    public static function experienceLevels(): array
    {
        return [
            'curious' => 'Lifestyle curious',
            'new' => 'New to the lifestyle',
            'experienced' => 'Experienced',
            'long_term' => 'Long-term lifestyle member',
            'prefer_not_to_say' => 'Prefer not to say',
        ];
    }

    public static function lookingFor(): array
    {
        return [
            'couples' => 'Couples',
            'women' => 'Women',
            'men' => 'Men',
            'gender_diverse' => 'Gender-diverse members',
            'friendships' => 'Lifestyle friendships',
            'event_connections' => 'Event connections',
            'travel_connections' => 'Travel connections',
            'private_groups' => 'Private groups & communities',
        ];
    }

    public static function interests(): array
    {
        return [
            'social_only' => 'Social / friendship first',
            'same_room' => 'Same-room experiences',
            'separate_room' => 'Separate-room experiences',
            'soft_swap' => 'Soft swap',
            'full_swap' => 'Full swap',
            'voyeur_friendly' => 'Voyeur-friendly',
            'exhibition_friendly' => 'Exhibition-friendly',
            'bi_friendly' => 'Bi-friendly',
            'couples_only_events' => 'Couples-only events',
            'house_parties' => 'Private house parties',
            'club_nights' => 'Club nights',
            'travel' => 'Lifestyle travel',
            'dinners_socials' => 'Dinners & social mixers',
            'wellness' => 'Wellness & retreats',
        ];
    }

    public static function visibilityOptions(): array
    {
        return [
            'members' => 'Club members',
            'connections' => 'My connections only',
            'private' => 'Only me',
        ];
    }
}
