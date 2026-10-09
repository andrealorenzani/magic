<?php
declare(strict_types=1);

namespace Magic\Content;

/**
 * The 56 minor arcana: suit and rank names, one essence line per rank, and the position texts,
 * composed from a rank sentence followed by a suit sentence. Entertainment, not prediction.
 */
final class TarotMinor
{
    /** Cards 22-35 are the first suit, 36-49 the second, and so on. */
    public const FIRST = 22;
    public const PER_SUIT = 14;

    public const SUITS = [
        'wands' => 'Wands',
        'cups' => 'Cups',
        'swords' => 'Swords',
        'pentacles' => 'Pentacles',
    ];

    public const RANKS = [
        'ace' => 'Ace', 'two' => 'Two', 'three' => 'Three', 'four' => 'Four', 'five' => 'Five',
        'six' => 'Six', 'seven' => 'Seven', 'eight' => 'Eight', 'nine' => 'Nine', 'ten' => 'Ten',
        'page' => 'Page', 'knight' => 'Knight', 'queen' => 'Queen', 'king' => 'King',
    ];

    /** Short rank marks for the card face. */
    public const RANK_MARKS = [
        'ace' => 'A', 'two' => '2', 'three' => '3', 'four' => '4', 'five' => '5',
        'six' => '6', 'seven' => '7', 'eight' => '8', 'nine' => '9', 'ten' => '10',
        'page' => 'Pg', 'knight' => 'Kn', 'queen' => 'Q', 'king' => 'K',
    ];

    public const RANK_ESSENCE = [
        'ace' => ['up' => 'A fresh beginning and a gift of possibility.', 'rev' => 'A beginning that is delayed or doubted.'],
        'two' => ['up' => 'Balance, choice and partnership.', 'rev' => 'Indecision or a lopsided give-and-take.'],
        'three' => ['up' => 'Growth through working together.', 'rev' => 'Plans out of step: teamwork is needed.'],
        'four' => ['up' => 'Stability, rest and a firm foundation.', 'rev' => 'Stagnation: comfort that became a rut.'],
        'five' => ['up' => 'Tension that clears the air.', 'rev' => 'A quarrel that is ready to be settled.'],
        'six' => ['up' => 'Harmony and a kind passage forward.', 'rev' => 'Slow progress and a load to share.'],
        'seven' => ['up' => 'Perseverance and holding your ground.', 'rev' => 'Defensiveness, or spreading yourself thin.'],
        'eight' => ['up' => 'Momentum, focus and growing skill.', 'rev' => 'Scattered effort and small delays.'],
        'nine' => ['up' => 'Resilience, and nearly there.', 'rev' => 'Worry and tiredness before the last step.'],
        'ten' => ['up' => 'Fulfilment and a cycle completed.', 'rev' => 'A heavy load that is ready to be set down.'],
        'page' => ['up' => 'Curiosity and a hopeful message.', 'rev' => 'Daydreaming and delayed news.'],
        'knight' => ['up' => 'Devoted pursuit and bold action.', 'rev' => 'Haste and restless moves.'],
        'queen' => ['up' => 'Warm, wise care and inner mastery.', 'rev' => 'Moodiness, or care that smothers.'],
        'king' => ['up' => 'Calm authority and generous judgement.', 'rev' => 'Control and stubborn pride.'],
    ];

    public const RANK_TEXT = [
        'ace' => [
            'past' => [
                'up' => 'Your story began with a pure, fresh offer, a seed of possibility that one of you dared to accept.',
                'rev' => 'A beginning may have been offered at the wrong moment, or fumbled, and something promising never fully took root.',
            ],
            'present' => [
                'up' => 'A new door is opening right now; the beginning you feel is real, so welcome it.',
                'rev' => 'A fresh chance is close but hesitant; you may be doubting a gift that is already in your hands.',
            ],
            'future' => [
                'up' => 'A new chapter is on its way: stay open, because something promising is ready to begin.',
                'rev' => 'A beginning is waiting for better timing; do not force the seed, but do not bury it either.',
            ],
        ],
        'two' => [
            'past' => [
                'up' => 'You learned early to weigh your options and to choose each other, balancing two worlds with care.',
                'rev' => 'Old indecision or a lopsided give-and-take left a mark; for a while you were not quite on the same page.',
            ],
            'present' => [
                'up' => 'You are weighing two paths or two needs right now, and a thoughtful balance is within reach.',
                'rev' => 'You feel pulled in two directions, and the delay is costing some peace; a clear, small choice would help.',
            ],
            'future' => [
                'up' => 'A meaningful choice or a harmonious pairing lies ahead; weigh it calmly and trust the partnership.',
                'rev' => 'Avoiding a decision could leave things in limbo; naming your preference will free you both.',
            ],
        ],
        'three' => [
            'past' => [
                'up' => 'Early joint effort bore its first fruits, and the pleasure of making something together still warms the memory.',
                'rev' => 'Some early plans were out of step, or one of you worked alone, so the first results came slowly.',
            ],
            'present' => [
                'up' => 'Teamwork and shared creation are visible now; what you started together is beginning to show.',
                'rev' => 'Your efforts are not quite meeting; check that you are both building the same thing.',
            ],
            'future' => [
                'up' => 'Cooperation will bring something to life; let your contributions grow side by side.',
                'rev' => 'Plans may stall unless you work as a team; invite the other in rather than going solo.',
            ],
        ],
        'four' => [
            'past' => [
                'up' => 'You built stable ground together, and a time of rest and security gave your bond its first firm walls.',
                'rev' => 'A season of holding back, where comfort turned into standing still, taught you what restlessness feels like.',
            ],
            'present' => [
                'up' => 'This is a moment of pause and security; savour what is steady and let yourselves rest.',
                'rev' => 'Restlessness is stirring; the calm you have may be asking for one small change.',
            ],
            'future' => [
                'up' => 'A period of calm and consolidation is coming; build gently and enjoy a well-earned rest.',
                'rev' => 'A rut could settle in unless you shake things up; plan something that brings new life.',
            ],
        ],
        'five' => [
            'past' => [
                'up' => 'A bumpy stretch of disagreement tested you early on, and it taught you how each of you argues and forgives.',
                'rev' => 'An old quarrel was swept aside rather than settled, and its echo still lingers beneath the surface.',
            ],
            'present' => [
                'up' => 'Some tension or rivalry is in the air; it is rough, but it clears out what was left unsaid.',
                'rev' => 'The worst of a rough patch is passing; the way out is honest words and a willingness to let go.',
            ],
            'future' => [
                'up' => 'A challenge is coming that will test your teamwork; meet it kindly and it will make you stronger.',
                'rev' => 'An argument is ready to be resolved; choose peace over winning and a hard chapter closes.',
            ],
        ],
        'six' => [
            'past' => [
                'up' => 'A time of kindness and shared progress carried you to calmer waters, and the generosity you showed is remembered.',
                'rev' => 'A hoped-for improvement was slow or uneven, and some old burdens travelled with you into the next stage.',
            ],
            'present' => [
                'up' => 'Things are easing; you are moving toward a gentler, more harmonious place together.',
                'rev' => 'Progress feels sluggish, or one of you carries too much; share the load to move on.',
            ],
            'future' => [
                'up' => 'Smoother days and a helpful passage are ahead, with kindness given and received along the way.',
                'rev' => 'The change you hope for needs a little more patience; do not rush the crossing.',
            ],
        ],
        'seven' => [
            'past' => [
                'up' => 'You stood your ground for each other when it mattered, and the persistence you showed built respect.',
                'rev' => 'You each defended yourselves more than you listened, and some old defences have never come down.',
            ],
            'present' => [
                'up' => 'You are holding your position, and your perseverance is being noticed; keep at it.',
                'rev' => 'You may be spread thin or on the defensive; ease up, because not everything is an attack.',
            ],
            'future' => [
                'up' => 'A test of resolve lies ahead; keep faith in what you want and the effort will pay off.',
                'rev' => 'Weariness could tempt you to give up too soon; rest, then try once more.',
            ],
        ],
        'eight' => [
            'past' => [
                'up' => 'Things moved quickly once you were in motion, and steady effort or a flurry of attention carried you forward.',
                'rev' => 'Mixed signals and rushed steps sometimes sent you in circles, and a few chances passed unused.',
            ],
            'present' => [
                'up' => 'Events are picking up speed and your focus is sharp; use the momentum wisely.',
                'rev' => 'Delays and scattered attention slow you down; choose one thing and give it your best.',
            ],
            'future' => [
                'up' => 'Movement and learning are coming; put in practice and you will gain skill and ease.',
                'rev' => 'Too many things at once may drain you; slow down and polish just one.',
            ],
        ],
        'nine' => [
            'past' => [
                'up' => 'You drew on your own strength to get this far, and the independence you each earned is part of who you are together.',
                'rev' => 'Lingering worry or loneliness shadowed the past, and some old fears still whisper from time to time.',
            ],
            'present' => [
                'up' => 'You are nearly there, and your resilience is real; trust how far you have already come.',
                'rev' => 'Anxiety or fatigue is louder than the facts; speak your worries, because they shrink when shared.',
            ],
            'future' => [
                'up' => 'A hard-won sense of comfort and fulfilment is near; keep going and the reward will be yours.',
                'rev' => 'Fear of the last step could hold you back; look at what you have already achieved.',
            ],
        ],
        'ten' => [
            'past' => [
                'up' => 'A cycle came to a full close, and the completeness you reached then is still the ground you stand on.',
                'rev' => 'Too much was carried for too long, and a chapter ended in weight and exhaustion rather than in joy.',
            ],
            'present' => [
                'up' => 'A cycle is reaching its fullness, bringing a sense of completion and shared reward.',
                'rev' => 'You may be overloaded or at the end of your reserves; lay down what is not yours to carry.',
            ],
            'future' => [
                'up' => 'A chapter is about to close in fullness, making room for something new to begin.',
                'rev' => 'A heavy load is nearly done; lighten it, share it and look for the finish line.',
            ],
        ],
        'page' => [
            'past' => [
                'up' => 'A message, a curious first step or a youthful spark brought you together, and that beginner\'s wonder still lives in you.',
                'rev' => 'Naive moments, a promise half meant or a note left unanswered, taught you to take things seriously.',
            ],
            'present' => [
                'up' => 'A curious, hopeful message is near: stay open and playful, and let yourselves learn something new.',
                'rev' => 'Daydreaming or a delayed message holds things back; ask plainly instead of waiting for a sign.',
            ],
            'future' => [
                'up' => 'Good news and a student\'s enthusiasm are on their way; welcome the invitation to explore.',
                'rev' => 'Distraction could slow a promising start; take the first step with care.',
            ],
        ],
        'knight' => [
            'past' => [
                'up' => 'A devoted pursuit or a bold gesture marked your story, and the drive of one of you gave it momentum.',
                'rev' => 'Impulsive moves and a restless heart led to stops and starts, and some enthusiasm burned out too fast.',
            ],
            'present' => [
                'up' => 'Someone is on the move: a determined, energetic push is shaping the mood between you.',
                'rev' => 'Haste or a fickle pace is stirring the waters; slow down and check where you are heading.',
            ],
            'future' => [
                'up' => 'A purposeful step forward is coming; follow it with commitment and keep your promises.',
                'rev' => 'A rush could take you off course; steady your pace so the journey ends where you want it to.',
            ],
        ],
        'queen' => [
            'past' => [
                'up' => 'A warm, mature presence guided you, and the way one of you cared with grace and good judgement still shapes your bond.',
                'rev' => 'Moods and unspoken needs tinted the past, and care sometimes turned into control or withdrawal.',
            ],
            'present' => [
                'up' => 'Care, confidence and emotional wisdom are strong right now; lead with warmth.',
                'rev' => 'Insecurity is clouding your kindness; look after yourself first, then after others.',
            ],
            'future' => [
                'up' => 'A season of grace and mastery is ahead; be generous with your attention and secure in who you are.',
                'rev' => 'Watch for self-doubt or smothering care; nurture without needing to hold on.',
            ],
        ],
        'king' => [
            'past' => [
                'up' => 'Steady leadership and a sense of responsibility gave your bond its authority; someone showed up and led fairly.',
                'rev' => 'Pride or rigidity once took charge, and the need to be right stood between you at times.',
            ],
            'present' => [
                'up' => 'Calm authority and good judgement are available now; decide with a clear and generous mind.',
                'rev' => 'Control or stubbornness is flaring; soften the grip and let the other be heard.',
            ],
            'future' => [
                'up' => 'You are growing into wisdom and command; lead gently and your example will be followed.',
                'rev' => 'A heavy hand or a bruised ego could damage trust; choose understanding over power.',
            ],
        ],
    ];

    public const SUIT_TEXT = [
        'wands' => [
            'past' => 'The fire of the Wands runs through this memory: passion, ambition and the courage to begin together.',
            'present' => 'The fire of the Wands colours today: energy, desire and a wish to act are close to the surface.',
            'future' => 'The fire of the Wands lights the road ahead: expect spark, movement and a call to adventure.',
        ],
        'cups' => [
            'past' => 'The water of the Cups runs through this chapter: feelings, tenderness and the way you opened your hearts.',
            'present' => 'The water of the Cups fills today: emotions, intuition and tenderness are asking to be heard.',
            'future' => 'The water of the Cups flows toward you: the heart will lead, and feelings will show the way.',
        ],
        'swords' => [
            'past' => 'The air of the Swords sharpened this memory: words, ideas and plain truths shaped what was said and unsaid.',
            'present' => 'The air of the Swords stirs today: thoughts, conversations and the need for clarity are in focus.',
            'future' => 'The air of the Swords clears the road ahead: honest words and a steady mind will matter.',
        ],
        'pentacles' => [
            'past' => 'The earth of the Pentacles grounds this memory: home, work and the patient building of trust.',
            'present' => 'The earth of the Pentacles anchors today: daily life, security and practical care are what count.',
            'future' => 'The earth of the Pentacles steadies the road ahead: patient effort, shared resources and lasting foundations.',
        ],
    ];

    /** The card for a deck number 22-77, in the shape of TarotDeck::card(). @return array<string,mixed> */
    public static function card(int $n): array
    {
        if ($n < self::FIRST || $n >= self::FIRST + self::PER_SUIT * count(self::SUITS)) {
            throw new \InvalidArgumentException("No minor arcana card {$n}");
        }
        $suit = array_keys(self::SUITS)[intdiv($n - self::FIRST, self::PER_SUIT)];
        $rank = array_keys(self::RANKS)[($n - self::FIRST) % self::PER_SUIT];
        return [
            'id' => $rank . '-of-' . $suit,
            'number' => $n,
            'name' => self::RANKS[$rank] . ' of ' . self::SUITS[$suit],
            'upright' => self::RANK_ESSENCE[$rank]['up'],
            'reversed' => self::RANK_ESSENCE[$rank]['rev'],
            'arcana' => 'minor',
            'suit' => $suit,
            'rank' => $rank,
            'mark' => self::RANK_MARKS[$rank],
        ];
    }

    /** The reading for a minor card id in a position. Throws when the id or position does not exist. */
    public static function text(string $cardId, string $position, bool $reversed): string
    {
        $parts = explode('-of-', $cardId);
        $rank = $parts[0];
        $suit = $parts[1] ?? '';
        $o = $reversed ? 'rev' : 'up';
        $r = self::RANK_TEXT[$rank][$position][$o] ?? null;
        $s = self::SUIT_TEXT[$suit][$position] ?? null;
        if ($r === null || $s === null || count($parts) !== 2) {
            throw new \InvalidArgumentException("No tarot text for {$cardId} / {$position}");
        }
        return $r . ' ' . $s;
    }
}
