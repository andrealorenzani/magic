<?php
declare(strict_types=1);

namespace Magic\Content;

/** Copy for the planets (sign-independent). */
final class Bodies
{
    public const INFO = [
        'sun' => ['glyph' => "\u{2609}", 'title' => 'Sun', 'tagline' => 'Identity and vitality', 'meaning' => 'The Sun is your core self, the part of you that wants to shine.'],
        'moon' => ['glyph' => "\u{263D}", 'title' => 'Moon', 'tagline' => 'Feelings and instincts', 'meaning' => 'The Moon is your emotional weather: what makes you feel safe and nourished.'],
        'mercury' => ['glyph' => "\u{263F}", 'title' => 'Mercury', 'tagline' => 'Mind and words', 'meaning' => 'Mercury shows how you think, learn and talk to the world.'],
        'venus' => ['glyph' => "\u{2640}", 'title' => 'Venus', 'tagline' => 'Love and beauty', 'meaning' => 'Venus is what you love, how you love and what you find beautiful.'],
        'mars' => ['glyph' => "\u{2642}", 'title' => 'Mars', 'tagline' => 'Drive and desire', 'meaning' => 'Mars is your energy: how you go after what you want.'],
        'jupiter' => ['glyph' => "\u{2643}", 'title' => 'Jupiter', 'tagline' => 'Luck and growth', 'meaning' => 'Jupiter points to where you expand, trust and find good fortune.'],
        'saturn' => ['glyph' => "\u{2644}", 'title' => 'Saturn', 'tagline' => 'Discipline and lessons', 'meaning' => 'Saturn is the patient teacher: structure, limits and mastery.'],
        'uranus' => ['glyph' => "\u{2645}", 'title' => 'Uranus', 'tagline' => 'Change and originality', 'meaning' => 'Uranus brings sudden insight and the urge to break the mould (a generational sign).'],
        'neptune' => ['glyph' => "\u{2646}", 'title' => 'Neptune', 'tagline' => 'Dreams and mystery', 'meaning' => 'Neptune is imagination, compassion and the mist between worlds (a generational sign).'],
        'pluto' => ['glyph' => "\u{2647}", 'title' => 'Pluto', 'tagline' => 'Depth and rebirth', 'meaning' => 'Pluto speaks of deep transformation and what must be let go (a generational sign).'],
        'ascendant' => ['glyph' => 'AC', 'title' => 'Ascendant', 'tagline' => 'Mask and first impression', 'meaning' => 'The Ascendant is the sign rising on the eastern horizon at your birth: how you meet the world.'],
        'midheaven' => ['glyph' => 'MC', 'title' => 'Midheaven', 'tagline' => 'Calling and public life', 'meaning' => 'The Midheaven is the highest point of your chart: the path you are known for and the direction of your ambitions.'],
        'node' => ['glyph' => "\u{260A}", 'title' => 'North Node', 'tagline' => 'Direction of growth', 'meaning' => 'The mean North Node is a symbolic pointer to the lessons you are growing toward.'],
    ];
}
