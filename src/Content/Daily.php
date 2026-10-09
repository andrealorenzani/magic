<?php
declare(strict_types=1);

namespace Magic\Content;

/** Copy for the moon phase and the daily "Today's sky" reading. */
final class Daily
{
    public const PHASE_NAMES = [
        'new' => 'New Moon',
        'waxing-crescent' => 'Waxing Crescent Moon',
        'first-quarter' => 'First Quarter Moon',
        'waxing-gibbous' => 'Waxing Gibbous Moon',
        'full' => 'Full Moon',
        'waning-gibbous' => 'Waning Gibbous Moon',
        'last-quarter' => 'Last Quarter Moon',
        'waning-crescent' => 'Waning Crescent Moon',
    ];

    /** Symbols of the phases, for decoration only. */
    public const PHASE_SYMBOLS = [
        'new' => "\u{1F311}", 'waxing-crescent' => "\u{1F312}", 'first-quarter' => "\u{1F313}", 'waxing-gibbous' => "\u{1F314}",
        'full' => "\u{1F315}", 'waning-gibbous' => "\u{1F316}", 'last-quarter' => "\u{1F317}", 'waning-crescent' => "\u{1F318}",
    ];

    /** The mood of the day, by the sign the Moon is in. */
    public const MOOD = [
        'aries' => 'The Moon crosses Aries: feelings arrive fast and bright, so act on the honest ones and let the hasty ones pass.',
        'taurus' => 'The Moon rests in Taurus: comfort, good food and familiar company are what the heart asks for today.',
        'gemini' => 'The Moon travels through Gemini: the mind is chatty and curious, and a good conversation can lift your whole mood.',
        'cancer' => 'The Moon is home in Cancer: emotions run deep and tender, and the people you trust feel especially close.',
        'leo' => 'The Moon shines in Leo: warmth, generosity and a wish to be seen set the tone of the day.',
        'virgo' => 'The Moon moves through Virgo: small, useful acts of care feel better than grand gestures.',
        'libra' => 'The Moon glides through Libra: harmony, beauty and fairness matter, and kind words go a long way.',
        'scorpio' => 'The Moon deepens in Scorpio: feelings are intense and perceptive, and honesty is worth more than smooth talk.',
        'sagittarius' => 'The Moon roams through Sagittarius: the heart wants space, laughter and a bigger view of things.',
        'capricorn' => 'The Moon steadies in Capricorn: patience and responsibility come easily, and feelings are shown through deeds.',
        'aquarius' => 'The Moon drifts through Aquarius: you feel curious and a little detached, ready for friends and fresh ideas.',
        'pisces' => 'The Moon dissolves into Pisces: sensitivity is high, so dreams, music and compassion are good company.',
    ];

    /** How the sign of the Moon today relates to the Sun sign of the person, by relation level. */
    public const RELATION = [
        'sign' => 'That is your own Sun sign, so the sky mirrors you today and you feel understood.',
        'element' => 'That sign shares the element of your Sun, so the day moves in your natural temperament.',
        'complement' => 'That sign has an element that feeds your Sun, so the day gives you a gentle push in a helpful direction.',
        'modality' => 'That sign keeps the rhythm of your Sun sign, so you can find your pace without much effort.',
        'none' => 'That sign sits far from your Sun sign, so the day invites you to try a different point of view.',
    ];

    /** An invitation for each phase. */
    public const INVITATION = [
        'new' => 'Under a New Moon, plant a quiet intention and keep it to yourself for now.',
        'waxing-crescent' => 'With the Moon growing, take the first small step toward something you want.',
        'first-quarter' => 'At the First Quarter, face the small obstacle and choose with courage.',
        'waxing-gibbous' => 'As the Moon swells, refine your plans and be patient with the last details.',
        'full' => 'Under a Full Moon, celebrate what has ripened and say what you feel out loud.',
        'waning-gibbous' => 'As the light begins to wane, share what you have learned and give thanks.',
        'last-quarter' => 'At the Last Quarter, forgive, tidy up and let go of what no longer fits.',
        'waning-crescent' => 'In the final crescent, rest, dream and prepare for a fresh beginning.',
    ];
}
