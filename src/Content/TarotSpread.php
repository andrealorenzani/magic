<?php
declare(strict_types=1);

namespace Magic\Content;

/** The Past / Present / Future spread: position titles and the position-specific card texts. */
final class TarotSpread
{
    public const ORDER = ['past', 'present', 'future'];

    public const POSITIONS = [
        'past' => ['title' => 'Past', 'question' => 'What shaped things?', 'intro' => 'The roots of your connection: what brought you here.'],
        'present' => ['title' => 'Present', 'question' => 'What is happening now?', 'intro' => 'The current mood between you two.'],
        'future' => ['title' => 'Future', 'question' => 'What is coming?', 'intro' => 'A direction, or an invitation, for what grows next.'],
    ];

    private const SOURCES = [
        'past' => TarotPast::TEXT,
        'present' => TarotPresent::TEXT,
        'future' => TarotFuture::TEXT,
    ];

    /** The reading for a card in a position. Throws when the card or position does not exist. */
    public static function text(string $cardId, string $position, bool $reversed): string
    {
        $t = self::SOURCES[$position][$cardId][$reversed ? 'rev' : 'up'] ?? null;
        if ($t === null) {
            throw new \InvalidArgumentException("No tarot text for {$cardId} / {$position}");
        }
        return $t;
    }
}
