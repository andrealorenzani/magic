<?php
declare(strict_types=1);

namespace Magic\Content;

/** The 22 Major Arcana with love-oriented readings. Entertainment, not prediction. */
final class TarotDeck
{
    /** The full deck: 22 major cards (0-21), then the minor suits (22-77). */
    public const COUNT = 78;

    public const CARDS = [
        ['id' => 'fool', 'number' => 0, 'name' => 'The Fool', 'upright' => 'A fresh start: step forward with an open heart and a light bag.', 'reversed' => 'Hesitation or recklessness: look before you leap, then leap anyway.'],
        ['id' => 'magician', 'number' => 1, 'name' => 'The Magician', 'upright' => 'You have everything you need to charm and create: speak your wish clearly.', 'reversed' => 'Mixed signals or unused talent: say what you actually mean.'],
        ['id' => 'high-priestess', 'number' => 2, 'name' => 'The High Priestess', 'upright' => 'Listen to intuition and silence: what is unsaid matters today.', 'reversed' => 'Secrets or ignored gut feelings: ask the gentle question.'],
        ['id' => 'empress', 'number' => 3, 'name' => 'The Empress', 'upright' => 'Abundance, tenderness and beauty: love grows when it is nourished.', 'reversed' => 'Smothering or neglecting self-care: give and receive in balance.'],
        ['id' => 'emperor', 'number' => 4, 'name' => 'The Emperor', 'upright' => 'Stability and commitment: a calm, dependable presence builds trust.', 'reversed' => 'Rigidity or control: loosen the grip and make room for the other.'],
        ['id' => 'hierophant', 'number' => 5, 'name' => 'The Hierophant', 'upright' => 'Shared values and tradition: a promise or ritual deepens the bond.', 'reversed' => 'Breaking the mould: find your own way of being together.'],
        ['id' => 'lovers', 'number' => 6, 'name' => 'The Lovers', 'upright' => 'A meaningful connection and an honest choice made from the heart.', 'reversed' => 'Misalignment or doubt: check that your words and values agree.'],
        ['id' => 'chariot', 'number' => 7, 'name' => 'The Chariot', 'upright' => 'Move forward together: determination wins the day.', 'reversed' => 'Pulling in two directions: agree on where you are going.'],
        ['id' => 'strength', 'number' => 8, 'name' => 'Strength', 'upright' => 'Gentle courage and patience: tenderness is stronger than force.', 'reversed' => 'Self-doubt or temper: be as kind to yourself as to the other.'],
        ['id' => 'hermit', 'number' => 9, 'name' => 'The Hermit', 'upright' => 'A moment of reflection: know yourself and the light will find you.', 'reversed' => 'Isolation: reach out, someone is waiting for your call.'],
        ['id' => 'wheel', 'number' => 10, 'name' => 'Wheel of Fortune', 'upright' => 'A turn of luck and good timing: be ready for a happy surprise.', 'reversed' => 'A delay or a repeated pattern: patience, the wheel keeps turning.'],
        ['id' => 'justice', 'number' => 11, 'name' => 'Justice', 'upright' => 'Fairness and honesty: a balanced give-and-take keeps love healthy.', 'reversed' => 'Imbalance or avoided truth: name what feels unfair, kindly.'],
        ['id' => 'hanged-man', 'number' => 12, 'name' => 'The Hanged Man', 'upright' => 'Pause and see it from another side: patience brings insight.', 'reversed' => 'Stalling: a decision is ready to be made.'],
        ['id' => 'death', 'number' => 13, 'name' => 'Death', 'upright' => 'An ending that clears space: let an old pattern go and renew.', 'reversed' => 'Clinging to the past: change is gentler when welcomed.'],
        ['id' => 'temperance', 'number' => 14, 'name' => 'Temperance', 'upright' => 'Harmony and moderation: blend your differences into something sweet.', 'reversed' => 'Excess or impatience: find the middle path again.'],
        ['id' => 'devil', 'number' => 15, 'name' => 'The Devil', 'upright' => 'Strong attraction, perhaps a little too strong: enjoy it with open eyes.', 'reversed' => 'Freeing yourself from an unhealthy habit or attachment.'],
        ['id' => 'tower', 'number' => 16, 'name' => 'The Tower', 'upright' => 'A sudden shake-up reveals what is real: honesty clears the air.', 'reversed' => 'A tension you have been avoiding: face it before it builds.'],
        ['id' => 'star', 'number' => 17, 'name' => 'The Star', 'upright' => 'Hope and healing: trust that love is guiding you.', 'reversed' => 'Discouragement: small acts of care restore your faith.'],
        ['id' => 'moon', 'number' => 18, 'name' => 'The Moon', 'upright' => 'Dreams and mixed feelings: move slowly, things are not yet clear.', 'reversed' => 'Fog lifting: a worry fades and the picture sharpens.'],
        ['id' => 'sun', 'number' => 19, 'name' => 'The Sun', 'upright' => 'Joy, warmth and openness: a bright day for love.', 'reversed' => 'Clouded cheer: allow yourself to enjoy the simple things.'],
        ['id' => 'judgement', 'number' => 20, 'name' => 'Judgement', 'upright' => 'A call to answer honestly: forgive and begin again.', 'reversed' => 'Self-criticism: release old verdicts about yourself or others.'],
        ['id' => 'world', 'number' => 21, 'name' => 'The World', 'upright' => 'Completion and wholeness: a cycle closes beautifully.', 'reversed' => 'Almost there: one last step before the celebration.'],
    ];

    /**
     * Any card of the 78-card deck, with the same keys for major and minor cards.
     * @return array{id:string, number:int, name:string, upright:string, reversed:string, arcana:string, suit:?string, rank:?string, mark:string}
     */
    public static function card(int $n): array
    {
        if ($n >= 0 && $n < count(self::CARDS)) {
            return self::CARDS[$n] + ['arcana' => 'major', 'suit' => null, 'rank' => null, 'mark' => (string) $n];
        }
        return TarotMinor::card($n);
    }
}
