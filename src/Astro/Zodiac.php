<?php
declare(strict_types=1);

namespace Arcana\Astro;

final class Zodiac
{
    public const SIGNS = [
        ['id' => 'aries', 'name' => 'Aries', 'symbol' => '♈', 'element' => 'Fire', 'modality' => 'Cardinal'],
        ['id' => 'taurus', 'name' => 'Taurus', 'symbol' => '♉', 'element' => 'Earth', 'modality' => 'Fixed'],
        ['id' => 'gemini', 'name' => 'Gemini', 'symbol' => '♊', 'element' => 'Air', 'modality' => 'Mutable'],
        ['id' => 'cancer', 'name' => 'Cancer', 'symbol' => '♋', 'element' => 'Water', 'modality' => 'Cardinal'],
        ['id' => 'leo', 'name' => 'Leo', 'symbol' => '♌', 'element' => 'Fire', 'modality' => 'Fixed'],
        ['id' => 'virgo', 'name' => 'Virgo', 'symbol' => '♍', 'element' => 'Earth', 'modality' => 'Mutable'],
        ['id' => 'libra', 'name' => 'Libra', 'symbol' => '♎', 'element' => 'Air', 'modality' => 'Cardinal'],
        ['id' => 'scorpio', 'name' => 'Scorpio', 'symbol' => '♏', 'element' => 'Water', 'modality' => 'Fixed'],
        ['id' => 'sagittarius', 'name' => 'Sagittarius', 'symbol' => '♐', 'element' => 'Fire', 'modality' => 'Mutable'],
        ['id' => 'capricorn', 'name' => 'Capricorn', 'symbol' => '♑', 'element' => 'Earth', 'modality' => 'Cardinal'],
        ['id' => 'aquarius', 'name' => 'Aquarius', 'symbol' => '♒', 'element' => 'Air', 'modality' => 'Fixed'],
        ['id' => 'pisces', 'name' => 'Pisces', 'symbol' => '♓', 'element' => 'Water', 'modality' => 'Mutable'],
    ];

    /** Map an ecliptic longitude to its tropical sign and position inside it. */
    public static function fromLongitude(float $longitude): array
    {
        $lon = Angles::norm360($longitude);
        $index = min(11, (int) floor($lon / 30));
        $within = $lon - $index * 30;
        $degree = (int) floor($within);
        $minute = (int) floor(($within - $degree) * 60);
        return self::SIGNS[$index] + ['index' => $index, 'longitude' => $lon, 'degree' => $degree, 'minute' => $minute];
    }
}
