<?php
declare(strict_types=1);

namespace Magic\Content;

/** Copy for the aspects between two charts. */
final class Aspects
{
    public const NAMES = [
        'conjunction' => 'conjunction',
        'sextile' => 'sextile',
        'square' => 'square',
        'trine' => 'trine',
        'opposition' => 'opposition',
    ];

    public const MEANING = [
        'conjunction' => 'The two energies merge and amplify each other, for better or for more intensity.',
        'sextile' => 'An easy opening: the two energies cooperate when you make a small effort.',
        'square' => 'Friction that asks for adjustment, and that can also spark growth.',
        'trine' => 'A natural flow: the two energies support each other without effort.',
        'opposition' => 'A pull between two poles: you notice in each other what you are missing, and balance is the lesson.',
    ];

    public const TONE = [
        'conjunction' => 'neutral',
        'sextile' => 'harmonious',
        'trine' => 'harmonious',
        'square' => 'tense',
        'opposition' => 'tense',
    ];

    public const TONE_LABELS = [
        'harmonious' => 'Harmonious',
        'tense' => 'Tense',
        'neutral' => 'Blending',
    ];
}
