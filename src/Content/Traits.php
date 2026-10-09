<?php
declare(strict_types=1);

namespace Magic\Content;

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

    /** Short labels for the comparison level shown as a pill. */
    public const LEVEL_LABELS = [
        'sign' => 'Same sign',
        'element' => 'Same element',
        'complement' => 'Complementary',
        'modality' => 'Same rhythm',
        'none' => 'Different paths',
    ];

    /** Lower bounds (inclusive) of the "in common" summary bands, highest first. */
    public const COMMON_BANDS = ['strong' => 80, 'good' => 60, 'mixed' => 40, 'contrasting' => 0];

    public const COMMON_VERDICTS = [
        'strong' => 'A strong resemblance: you share a great deal in your signs, and much of it comes naturally.',
        'good' => 'A good match: you share real common ground and complement each other where you differ.',
        'mixed' => 'A mixed picture: some things align and others differ, leaving room for both ease and discovery.',
        'contrasting' => 'Quite different signs: your common ground is smaller, but contrast can be a way to learn from each other.',
    ];

    /** One sentence per body and level, written for the couple. */
    public const COMMON_MEANING = [
        'sun' => [
            'sign' => 'Your core selves speak the same language, so you recognise each other\'s drive at once.',
            'element' => 'You are fuelled by the same kind of energy, which makes your daily rhythm feel natural.',
            'complement' => 'What drives one of you feeds the other, so you tend to bring out each other\'s best.',
            'modality' => 'You approach life at a similar pace, even if your styles differ.',
            'none' => 'Your core natures differ, and that difference can teach you both something new.',
        ],
        'moon' => [
            'sign' => 'You tend to feel things in the same way, and comfort comes easily between you.',
            'element' => 'You tend to need the same kind of comfort.',
            'complement' => 'Your emotional styles balance one another, each offering what the other lacks.',
            'modality' => 'You react to change in a similar way, which helps when life gets bumpy.',
            'none' => 'You process feelings differently, so patience and curiosity about each other\'s inner world matter.',
        ],
        'ascendant' => [
            'sign' => 'You come across to the world in much the same way, so people often see you as a pair.',
            'element' => 'Your first impressions share a temperament, and you meet new situations in a similar spirit.',
            'complement' => 'The way you each meet the world fits together, one manner warming the other.',
            'modality' => 'You step into new situations at a similar tempo.',
            'none' => 'You present yourselves quite differently, which can make you a surprising and balanced pair.',
        ],
    ];

    /** The summary band for a common score. */
    public static function commonBand(int $score): string
    {
        foreach (self::COMMON_BANDS as $band => $min) {
            if ($score >= $min) {
                return $band;
            }
        }
        return 'contrasting';
    }
}
