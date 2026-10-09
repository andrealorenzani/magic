<?php
declare(strict_types=1);

namespace Magic\Content;

/** Interpretive copy, kept apart from the maths so text can change without touching astronomy. */
final class Signs
{
    public const TEXT = [
        'aries' => 'Bold, direct and quick to begin. A spark that lights the first fire.',
        'taurus' => 'Steady, sensual and loyal. Patience is your quiet kind of power.',
        'gemini' => 'Curious, witty and restless. Your mind is a crossroads of stories.',
        'cancer' => 'Protective, intuitive and deeply feeling. Home is something you carry.',
        'leo' => 'Warm, generous and radiant. You are born to be seen and to share the light.',
        'virgo' => 'Precise, caring and observant. You find magic in the details.',
        'libra' => 'Graceful, fair-minded and relational. You seek harmony in every room.',
        'scorpio' => 'Intense, perceptive and transformative. You are drawn to what lies beneath.',
        'sagittarius' => 'Adventurous, honest and hopeful. Your arrow always points to a horizon.',
        'capricorn' => 'Disciplined, patient and ambitious. You build what will outlast you.',
        'aquarius' => 'Original, independent and humane. You dream the future into being.',
        'pisces' => 'Dreamy, compassionate and mystical. You swim easily between worlds.',
    ];

    public const ROLES = [
        'sun' => ['title' => 'Sun sign', 'tagline' => 'Your core self and vital spark'],
        'ascendant' => ['title' => 'Ascendant', 'tagline' => 'The mask you wear and the first impression you give'],
        'moon' => ['title' => 'Moon sign', 'tagline' => 'Your inner world, instincts and emotional needs'],
        'midheaven' => ['title' => 'Midheaven', 'tagline' => 'Your calling and the way the world sees your achievements'],
    ];
}
