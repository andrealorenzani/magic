<?php
declare(strict_types=1);

namespace Magic\Content;

/** Short plain-language explanations shown in the "?" popovers next to fields and result items. */
final class Help
{
    /** Keys built from a prefix and a list of ids: prefix => ids. */
    public const DYNAMIC = [
        'self.planet' => ['mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto'],
    ];

    /** @var array<string, array{title:string, text:string}> */
    public const TEXT = [
        'field.name' => ['title' => 'Name', 'text' => 'The name you want shown on the reading. In Love mode the names are also compared with each other.'],
        'field.date' => ['title' => 'Birth date', 'text' => 'The day you were born. It gives your Sun sign, your planets and your biorhythm cycles.'],
        'field.time' => ['title' => 'Birth time', 'text' => 'The local clock time of your birth, as written on a birth certificate. It places your Moon and Ascendant more exactly.'],
        'field.city' => ['title' => 'Birth city', 'text' => 'The town where you were born. Pick a suggestion so the place and its time zone are filled in for you.'],
        'field.import' => ['title' => 'Import hidden details', 'text' => 'Paste the link someone shared with you, or scan their QR code. Their details are not shown on your screen; you only see the match results.'],
        'field.pos_city' => ['title' => 'Current city', 'text' => 'Where you are right now. It gives you the right "today" for your time zone and lets us show distances.'],

        'menu.clean' => ['title' => 'Clean browser data', 'text' => 'Forgets what this browser remembers about you and your saved people, and withdraws your acceptance of the Terms.'],
        'menu.share_hidden' => ['title' => 'Share my hidden data', 'text' => 'Makes a link and QR code with your own details so another person can compare themselves with you. Their screen will not show your details.'],
        'share.link' => ['title' => 'Link', 'text' => 'The address of this exact reading. Anyone who opens it sees the same reading; it contains the details you entered.'],
        'share.qr' => ['title' => 'QR code', 'text' => 'The same link as a picture. Point a phone camera at it to open the reading, or click it to copy the link.'],
        'share.live' => ['title' => 'Live link', 'text' => 'A link to the same people but with the values of the day it is opened, so the reading keeps up with the calendar.'],

        'self.sun' => ['title' => 'Sun sign', 'text' => 'Your core identity: what drives you and how you like to shine. It is the sign people usually mean by "your sign".'],
        'self.ascendant' => ['title' => 'Ascendant', 'text' => 'The sign rising on the horizon when you were born. It describes the first impression you give and how you meet new things.'],
        'self.moon' => ['title' => 'Moon sign', 'text' => 'Your inner weather: feelings, habits and what makes you feel safe. It changes sign every couple of days, so birth time matters.'],
        'self.midheaven' => ['title' => 'Midheaven', 'text' => 'The highest point of your chart. It points to your public side: the work you are known for and the direction you aim at.'],
        'self.houses' => ['title' => 'Houses', 'text' => 'The chart is split into twelve areas of life, such as home, work and relationships. Each planet falls in one of them.'],
        'self.wheel' => ['title' => 'Chart wheel', 'text' => 'A picture of your sky at birth. Signs run around the outer ring, planets sit inside, and the numbers are the twelve houses.'],
        'self.node' => ['title' => 'North Node', 'text' => 'A point, not a planet. It is read as a hint of the direction you are growing toward and the lessons of this life.'],
        'self.retrograde' => ['title' => 'Retrograde', 'text' => 'A planet marked R seems to move backwards in the sky for a while. Traditionally it turns that planet\'s themes inward.'],
        'self.planet.mercury' => ['title' => 'Mercury', 'text' => 'The planet of thinking and talking. Its sign shows how you learn, speak and make everyday choices.'],
        'self.planet.venus' => ['title' => 'Venus', 'text' => 'The planet of love and beauty. Its sign shows what you value, how you give affection and what you find lovely.'],
        'self.planet.mars' => ['title' => 'Mars', 'text' => 'The planet of energy and desire. Its sign shows how you go after what you want and how you react to a challenge.'],
        'self.planet.jupiter' => ['title' => 'Jupiter', 'text' => 'The planet of growth and good luck. Its sign shows where you are optimistic and where opportunities tend to find you.'],
        'self.planet.saturn' => ['title' => 'Saturn', 'text' => 'The planet of effort and limits. Its sign shows where you work hardest and what patience slowly teaches you.'],
        'self.planet.uranus' => ['title' => 'Uranus', 'text' => 'The planet of surprise and change. It stays in a sign for years, so it describes a whole generation more than one person.'],
        'self.planet.neptune' => ['title' => 'Neptune', 'text' => 'The planet of dreams and imagination. It stays in a sign for many years, so it colours a whole generation.'],
        'self.planet.pluto' => ['title' => 'Pluto', 'text' => 'The planet of deep transformation. It moves very slowly, so its sign is shared by everyone born in the same era.'],
        'self.affinity.signs' => ['title' => 'Signs in tune with you', 'text' => 'The three signs that, by tradition, get along best with your own. The percentage is a playful score of how well they blend.'],
        'self.affinity.soulmate' => ['title' => 'Heart sign', 'text' => 'The sign tradition links with the love of your life. Take it as a story to enjoy, not as a rule for who you should love.'],
        'self.born_under' => ['title' => 'Born under', 'text' => 'The shape of the Moon on the day you were born, and how much of it was lit.'],
        'self.sky' => ['title' => 'Today\'s sky', 'text' => 'Where the Moon is today and what mood it suggests. It is a light daily note for entertainment, not a forecast.'],
        'self.geo' => ['title' => 'Distance and geography', 'text' => 'How far apart the places are in a straight line, and how many hours separate their clocks.'],

        'bio.physical' => ['title' => 'Physical cycle', 'text' => 'A rhythm of 23 days that follows your energy, strength and stamina. High days feel active, low days call for rest.'],
        'bio.emotional' => ['title' => 'Emotional cycle', 'text' => 'A rhythm of 28 days that follows mood and sensitivity. High days feel warm and open, low days feel more fragile.'],
        'bio.intellectual' => ['title' => 'Intellectual cycle', 'text' => 'A rhythm of 33 days that follows focus and clear thinking. It is a popular theory, not something science has confirmed.'],

        'love.name_affinity' => ['title' => 'Name affinity', 'text' => 'A playful score of how the two names go together. It is just for fun and says nothing about the people.'],
        'love.sync' => ['title' => 'Biorhythm synchrony', 'text' => 'Shows whether your three cycles rise and fall together or take turns. Being out of step is not a verdict on a relationship.'],
        'love.sync_overall' => ['title' => 'Overall synchrony', 'text' => 'One number that sums up how closely your three cycles move together: higher means more in step.'],
        'love.common' => ['title' => 'In common', 'text' => 'Compares your Sun, Moon and Ascendant signs with theirs and shows what you share, from the same sign to a complementary one.'],
        'love.common_level' => ['title' => 'Level of match', 'text' => 'How close the two signs are: the same sign, the same element or style, or opposites that complete each other.'],
        'love.synastry' => ['title' => 'Synastry', 'text' => 'Compares your two skies planet by planet. The closest links show where you ease each other and where you rub.'],
        'love.aspect' => ['title' => 'Aspect', 'text' => 'The angle between one planet of yours and one of theirs. A smaller gap from the exact angle means a stronger link.'],
        'love.match_hidden' => ['title' => 'Your match', 'text' => 'This person shared their details with you in a link. They are not shown anywhere on your screen; only the results of comparing you two appear.'],
        'love.tarot.past' => ['title' => 'Past card', 'text' => 'The card for what lies behind the two of you: the roots and history that shaped the connection.'],
        'love.tarot.present' => ['title' => 'Present card', 'text' => 'The card for where the connection stands today: what is alive between you right now.'],
        'love.tarot.future' => ['title' => 'Future card', 'text' => 'The card for where the connection may be heading, if it keeps going the way it is. A suggestion, not a promise.'],
        'love.tarot_reversed' => ['title' => 'Reversed card', 'text' => 'A card shown upside down. By tradition its message turns inward, becomes blocked or asks for a different look.'],
    ];

    /** @return list<string> every key, with the dynamic ones expanded */
    public static function keys(): array
    {
        return array_keys(self::TEXT);
    }

    /** @return ?array{title:string, text:string} */
    public static function get(string $key): ?array
    {
        return self::TEXT[$key] ?? null;
    }

    /** @return list<string> the keys DYNAMIC stands for */
    public static function dynamicKeys(): array
    {
        $out = [];
        foreach (self::DYNAMIC as $prefix => $ids) {
            foreach ($ids as $id) {
                $out[] = $prefix . '.' . $id;
            }
        }
        return $out;
    }
}
