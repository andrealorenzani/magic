<?php
declare(strict_types=1);

namespace Magic\Content;

/** The twelve houses: a title and a line each (house 1 = the sign of the Ascendant). */
final class Houses
{
    public const THEMES = [
        1 => ['title' => 'Self', 'line' => 'How you appear and begin things: your body, style and first impulses.'],
        2 => ['title' => 'Resources', 'line' => 'What you own and value: money, talents and a sense of security.'],
        3 => ['title' => 'Communication', 'line' => 'Everyday words and travels: siblings, neighbours, learning and curiosity.'],
        4 => ['title' => 'Home', 'line' => 'Roots and family: where you feel you belong and what you inherited.'],
        5 => ['title' => 'Pleasure', 'line' => 'Romance, play and creation: whatever you do for the joy of it.'],
        6 => ['title' => 'Daily work', 'line' => 'Routines, craft and health: the small habits that shape your days.'],
        7 => ['title' => 'Partnership', 'line' => 'Committed relationships: the people who mirror you and complete you.'],
        8 => ['title' => 'Depth', 'line' => 'Shared resources, intimacy and change: what you merge with and let go of.'],
        9 => ['title' => 'Horizons', 'line' => 'Journeys, beliefs and higher learning: the search for meaning.'],
        10 => ['title' => 'Vocation', 'line' => 'Career and reputation: what you build in public and are known for.'],
        11 => ['title' => 'Community', 'line' => 'Friends, groups and hopes: the circles you belong to and the future you share.'],
        12 => ['title' => 'Inner life', 'line' => 'Solitude, dreams and the unseen: rest, retreat and what you keep to yourself.'],
    ];
}
