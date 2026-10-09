<?php
declare(strict_types=1);

namespace Magic\Content;

/** Tarot spread, position Present: the current mood of the bond (22 cards, upright and reversed). Entertainment, not prediction. */
final class TarotPresent
{
    public const TEXT = [
        'fool' => [
            'up' => "Right now there is a playful, open spirit between you two; it is a good season for trying something new and trusting the moment.",
            'rev' => "The mood is a little scattered or reckless at the moment, so slow down together and keep the lightness from turning into carelessness.",
        ],
        'magician' => [
            'up' => "You two have everything you need today: clear words, shared intention and a touch of charm turn good ideas into real moments.",
            'rev' => "Mixed signals are floating around now, and talents between you are not quite being used, so say plainly what you mean.",
        ],
        'high-priestess' => [
            'up' => "The present asks for listening: much is felt but not yet spoken, and quiet moments together will tell you more than debate.",
            'rev' => "Something is being kept inside at the moment and a gut feeling is being brushed aside; a gentle question could open the door.",
        ],
        'empress' => [
            'up' => "Tenderness is in the air: this is a warm, sensual, nourishing time, and small acts of care are especially welcome between you.",
            'rev' => "Care may be tipping out of balance now, too much fussing or too little attention to yourselves, so look after each of you equally.",
        ],
        'emperor' => [
            'up' => "The mood is steady and dependable at the moment; you two can lean on each other and build something solid.",
            'rev' => "Some rigidity has crept in, a need to control or to be right, and loosening your grip will make space for both of you.",
        ],
        'hierophant' => [
            'up' => "Shared values are at the centre now; a ritual, a promise or a simple tradition of your own can deepen what you share.",
            'rev' => "You two are questioning the usual rules at the moment, and it is fine to shape a way of being together that fits only you.",
        ],
        'lovers' => [
            'up' => "The heart is clearly engaged: there is real connection between you and an honest choice made from feeling, not habit.",
            'rev' => "Your words and your values may not quite agree at the moment, and a little doubt is asking you to check that you want the same things.",
        ],
        'chariot' => [
            'up' => "There is forward momentum between you; with a common aim and steady determination, the two of you move well together.",
            'rev' => "You may be pulling in two directions at the moment, so before anyone speeds up, agree on where you are going.",
        ],
        'strength' => [
            'up' => "Gentle courage defines the present: patience, kindness and a soft touch are doing more for you two than any argument could.",
            'rev' => "Self-doubt or a short temper is stirring at the moment, so be as kind to yourselves as you are to each other.",
        ],
        'hermit' => [
            'up' => "The present calls for a thoughtful pause: each of you is looking inward, and that quiet time can bring a clearer light to the bond.",
            'rev' => "One of you may be withdrawing at the moment and loneliness can creep in, so reach out and let the other know you are there.",
        ],
        'wheel' => [
            'up' => "Timing is on your side now: something is turning in your favour, so stay alert for a pleasant surprise between you.",
            'rev' => "A delay or a familiar pattern is showing up right now; be patient with each other, since the wheel is still turning.",
        ],
        'justice' => [
            'up' => "Honesty and fairness set the tone now, and a balanced give and take is keeping things clear and healthy between you.",
            'rev' => "Something feels uneven at the moment, or a truth is being avoided; name it kindly and it will lose its weight.",
        ],
        'hanged-man' => [
            'up' => "You two are in a pause, and it is a useful one: looking at things from the other side now brings real insight.",
            'rev' => "You may be stalling at the moment, waiting for a sign, while a decision is ready and you both know it.",
        ],
        'death' => [
            'up' => "A page is turning between you; letting an old pattern go now clears space for something truer and lighter.",
            'rev' => "Holding on to what has already passed is making this moment heavier, and change comes more gently when it is welcomed.",
        ],
        'temperance' => [
            'up' => "Harmony is the mood now: you two are blending your differences into something sweet, and moderation serves you well.",
            'rev' => "A little impatience or excess is unbalancing the present, so take a breath together and look for the middle path again.",
        ],
        'devil' => [
            'up' => "The attraction between you is strong at the moment, perhaps very strong; enjoy it, and keep your eyes open as you do.",
            'rev' => "A habit or an attachment is loosening its hold now, and you may feel a welcome sense of freedom growing between you.",
        ],
        'tower' => [
            'up' => "A sudden revelation is shaking the present; it may feel unsettling, but honesty is clearing the air and showing what is real.",
            'rev' => "A tension you have both been avoiding is pressing close, and facing it now, softly, can spare you a louder moment later.",
        ],
        'star' => [
            'up' => "Hope and healing colour this moment: you two feel calmer, more guided, and more willing to believe in what is growing.",
            'rev' => "Some discouragement is around at the moment, and small acts of care, a message or a shared meal, can restore your faith.",
        ],
        'moon' => [
            'up' => "Feelings are mixed and a little dreamlike now; things are not fully clear, so move slowly and trust what you sense.",
            'rev' => "A fog is lifting between you: a worry is fading and the picture is becoming sharper day by day.",
        ],
        'sun' => [
            'up' => "This is a bright, open moment: warmth, laughter and ease are with you two, so let yourselves enjoy it fully.",
            'rev' => "Your cheer is a little clouded at the moment, so give yourselves permission to enjoy the simple things anyway.",
        ],
        'judgement' => [
            'up' => "A call to answer honestly is in the air, and forgiving and starting again feels possible between you now.",
            'rev' => "Self-criticism is loud at the moment; release the old verdicts you hold on yourselves and on each other.",
        ],
        'world' => [
            'up' => "There is a sense of wholeness now: you two feel complete in each other's company, and a cycle is closing beautifully.",
            'rev' => "You are very close to a milestone but not quite there, and one last step stands between the two of you and the celebration.",
        ],
    ];
}
