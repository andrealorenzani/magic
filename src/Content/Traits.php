<?php
declare(strict_types=1);

namespace Arcana\Content;

/** Keywords and short phrases used to describe signs and how two signs relate. */
final class Traits
{
    public const SIGNS = [
        'aries' => ['bold', 'direct', 'energetic', 'pioneering'],
        'taurus' => ['steady', 'sensual', 'loyal', 'patient'],
        'gemini' => ['curious', 'witty', 'adaptable', 'talkative'],
        'cancer' => ['protective', 'intuitive', 'nurturing', 'sentimental'],
        'leo' => ['warm', 'generous', 'proud', 'radiant'],
        'virgo' => ['precise', 'helpful', 'observant', 'modest'],
        'libra' => ['graceful', 'fair', 'sociable', 'romantic'],
        'scorpio' => ['intense', 'perceptive', 'private', 'transformative'],
        'sagittarius' => ['adventurous', 'honest', 'optimistic', 'free'],
        'capricorn' => ['disciplined', 'ambitious', 'practical', 'enduring'],
        'aquarius' => ['original', 'independent', 'humane', 'inventive'],
        'pisces' => ['dreamy', 'compassionate', 'artistic', 'mystical'],
    ];

    public const ELEMENTS = [
        'Fire' => ['passionate', 'enthusiastic', 'spontaneous'],
        'Earth' => ['grounded', 'reliable', 'sensual'],
        'Air' => ['curious', 'communicative', 'social'],
        'Water' => ['emotional', 'intuitive', 'empathic'],
    ];

    public const MODALITIES = [
        'Cardinal' => ['initiating', 'leading', 'restless'],
        'Fixed' => ['loyal', 'persistent', 'stubborn'],
        'Mutable' => ['flexible', 'adaptable', 'changeable'],
    ];

    /** Complementary element pairs, key = sorted names. */
    public const COMPLEMENT = [
        'Air|Fire' => ['Air feeds Fire: ideas fan the flames, and enthusiasm gives thoughts a push.'],
        'Earth|Water' => ['Water nourishes Earth: feelings find a steady shore and things can grow.'],
    ];

    public const LEVELS = [
        'sign' => 'Same sign: you see the world through the same lens.',
        'element' => 'Same element: you share a natural temperament.',
        'complement' => 'Complementary elements: you feed each other.',
        'modality' => 'Same modality: you move through life at a similar rhythm.',
        'none' => 'Different paths that can teach each other.',
    ];

    public const ASPECTS = [
        0 => 'conjunction', 1 => 'semi-sextile', 2 => 'sextile', 3 => 'square',
        4 => 'trine', 5 => 'quincunx', 6 => 'opposition',
    ];

    public const VERDICTS = [
        'tune' => 'In tune. Your cycles run almost together, so highs and lows tend to arrive at the same time. The offset between two birthdays never changes.',
        'complementary' => 'Complementary. Your cycles are partly shifted: one can lift the other when you dip. The offset between two birthdays never changes.',
        'apart' => 'Out of step. Your cycles are far apart, so you often swing in opposite directions, which can make you balance each other. The offset between two birthdays never changes, and it says nothing about the success of a relationship.',
    ];
}
