<?php
declare(strict_types=1);

namespace Magic\Content;

/** The Greek myth behind each zodiac sign, in a sentence or two. */
final class Myths
{
    /** @return ?array{title:string, text:string} the popup of a sign's "History" link */
    public static function get(string $id): ?array
    {
        return isset(self::TEXT[$id]) ? ['title' => ucfirst($id), 'text' => self::TEXT[$id]] : null;
    }

    /** @var array<string,string> sign id => story */
    public const TEXT = [
        'aries' => 'Aries is the golden ram that flew young Phrixus and Helle to safety. Its shining fleece was later the prize Jason and the Argonauts sailed to win.',
        'taurus' => 'Taurus is Zeus himself, turned into a gentle white bull to win Princess Europa and carry her across the sea to Crete.',
        'gemini' => 'Gemini are the twins Castor and Pollux. When Castor died, Pollux shared his own immortality with him so they could stay together among the stars.',
        'cancer' => 'Cancer is the crab Hera sent to bite Heracles while he fought the Hydra. He crushed it, and Hera thanked it with a place in the sky.',
        'leo' => 'Leo is the lion of Nemea, whose skin no weapon could pierce. Defeating it was the first of the twelve labours of Heracles, who then wore its hide as armour.',
        'virgo' => 'Virgo is Astraea, goddess of justice, the last immortal to leave the Earth when people grew cruel. She still watches over us from the sky.',
        'libra' => 'Libra is the pair of scales held by Astraea, the goddess of justice, to weigh what is right and fair.',
        'scorpio' => 'Scorpio is the scorpion Gaia sent to stop the proud hunter Orion. The two were set on opposite sides of the sky, so one sets as the other rises.',
        'sagittarius' => 'Sagittarius is Chiron, the wisest of the centaurs and teacher of heroes. He gave up his immortality, and Zeus placed him among the stars with his bow.',
        'capricorn' => 'Capricorn is the goat Amalthea, who nursed baby Zeus in a cave on Crete. One of her horns became the horn of plenty, always full.',
        'aquarius' => 'Aquarius is Ganymede, the beautiful youth Zeus brought to Olympus to pour drinks for the gods, forever carrying his jar of water.',
        'pisces' => 'Pisces are Aphrodite and her son Eros, who turned into two fish tied by a cord to escape the monster Typhon without losing each other.',
    ];
}
