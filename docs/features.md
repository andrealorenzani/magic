# Features

What the page does, in visitor language. For how it is built, see [architecture.md](architecture.md) and [code.md](code.md). Everything here is for wonder, not science.

## Before you start

On the first visit a popup asks you to accept the Terms and Conditions. Until you do, nothing you type is processed or recorded. The Terms were updated in v0.8 (they now describe hidden-details links), so everybody is asked to accept once again. The Terms popup text scrolls inside its box and the Accept button never covers it, and the "Terms and Conditions" section at the end of the page opens when you click it.

## Two modes

- **Self discovery**: you enter your birth date, birth time and birth city, and optionally a **name**. The name appears in the heading and in your links.

Required fields are marked with `*`, with a "* required" note under each form.
- **Love**: you enter your own details and a loved person. For the loved person the name is enough; date, time and city are optional, and each extra detail unlocks more results.

A **Menu** above the mode choice offers "Clean browser data" and "Share my Self Discovery hidden data" (see below). Every field and result item has a small **?** button that explains it in a sentence or two; it needs JavaScript and the page is complete without it.

Both forms have an optional **Current city** field (see "Where you are now"). Results can be printed (a print button and a print layout without forms).

## Results

| Block | Mode | Needs |
|---|---|---|
| Sun, Ascendant, Moon signs | Self | date, time, city |
| Midheaven and the house of each body | Self | date, time, city |
| Chart wheel | Self | date, time, city |
| Mercury to Pluto and North Node, with retrograde marks | Self | date, time, city (planets for births 1800-2100) |
| Signs with most affinity, "love of your life" sign | Self | date, time, city |
| Born under (moon phase at your birth) | Self | date, time, city |
| Biorhythms (physical, emotional, intellectual) | Self | birth date |
| Today's sky (moon phase, Moon sign, a short reading) | Self, compact in Love | nothing more |
| Name affinity | Love | both names |
| Biorhythm synchrony | Love | both birth dates |
| In common (Sun, Moon, Ascendant values) | Love | what each person provided |
| Synastry (aspects between the two charts) | Love | the loved person's birth date; more detail with time and city |
| Distance and geography | Self, Love | current city (and birth city, or the other person's current city) |
| Past / Present / Future tarot spread | Love | both names |
| Share section (links and QR) | both | a result (not for a result built from hidden details) |

Details worth knowing:

- **Midheaven, houses and wheel**: the Midheaven is shown with the other signs; each planet card says which of the twelve houses it falls in, with a one-line theme. The wheel is a picture of the same data, with a table beside it. It is smaller and brighter than before and can be collapsed.
- **Born under**: the Moon phase on your birth day, in plain words.
- **Today's sky**: the Moon phase and illumination of the reading day, the Moon sign of the day, and a short reading related to your Sun sign (in Love, one sentence per person). The same day always gives the same text.
- **Synastry**: a table of the closest links between the two charts (for example "Venus trine Mars"), each with a meaning sentence and a harmonious / tense / neutral count. If the loved person has only a date, the table is marked approximate; with no date a card invites you to add it.
- **Distance and geography**: shown only when positions are known, labelled approximate and in a straight line, in km and miles, with the time difference.
- **Tarot**: the full 78-card deck. Each of Past, Present and Future has its own text for the card, upright or reversed. The same people on the same day always draw the same cards.

## Where you are now (optional)

Entering a current city is never required; links and forms without it work as before. When given, it:

- decides which calendar day counts as "today" for biorhythms, tarot, Today's sky and moon phase. In Love the day follows **your** current city (person A), not the loved person's. The result shows "Today for you: <date> (time zone ...)".
- enables the distance card.
- Without it, "today" is the server's UTC date, so it can be a day off for visitors far from UTC. A date given as `on=YYYY-MM-DD` in the link always wins.

If a current city cannot be found, it is ignored with a note; the rest of the reading is shown.

## Sharing

Under each result, "Share this reading" shows:

- **Copy link** and **Share** buttons (where the browser supports them) and a small **QR code**.
- A collapsed "Show the link" box with the full link and a live link.
- **Frozen link** (short `?c=` code): opens exactly what you saw, same day and, in Love, the same tarot cards. **Live link**: same people, but the recipient's own "today".
- Old long links keep working. If a short code in a link is damaged it is ignored with a note, and any readable parts of the link still apply.
- A link too long for a QR shows the link only.
- Opening a shared link is not recorded again (`noaudit`). You can add `noaudit` to any address to skip recording that request.
- Links contain the names and birth details you entered: share them only with people you trust. The short code is not encryption.
- The QR code can be clicked (or activated with the keyboard) to copy the link, shows "Link copied", and can be collapsed.

## Hidden details (share and import)

You can let another person compare themselves with you without typing your data.

- **Share:** Menu, "Share my Self Discovery hidden data". It uses the details your browser remembers; the button is grey, and leads to Self discovery, until you have submitted a complete Self form (date, time and birth city). It needs JavaScript. A panel shows a link and a QR code to copy or scan, with a warning to share it only with someone you trust.
- **Import:** in Love, "Import hidden details": paste the link (it works without JavaScript; there is a second submit button in that box) or scan the QR code. Scanning needs a browser that supports it (for example Chrome on Android); pasting works everywhere. Opening such a link directly also lands in Love.
- **What the receiver sees:** the shared person's name, birth date, time and city are not shown; the loved-person fields are replaced by a note "Details shared with you are loaded and hidden" with a "Remove them" link, and the results call them "Your match". There is no share section on such a result. If the link is damaged it is ignored with a note.
- **Honest note:** "hidden" only means not shown on screen. The link contains the details and can be decoded by anyone who has it, it stays in the receiver's address bar and history, and the match results themselves say something about the person. Opening such a link is recorded like any Love result, with both people's details (add `noaudit` to skip it).

## Menu: clean browser data

"Clean browser data" (needs JavaScript) asks for confirmation in the page, then forgets the details remembered in this browser and your acceptance of the Terms; the Terms popup appears again.

## Remembered details

If your browser allows it, the page remembers, **in your browser only**, the details you enter for yourself and a short list of people you looked up. It saves automatically when you submit a form you filled in yourself; details that arrived through a shared link are saved only if you press "Remember these details". In Love a "Saved people" list lets you fill in a loved person with one choice, or remove an entry. "Forget my data" erases everything the page keeps; withdrawing your acceptance of the Terms erases it too. If the browser blocks storage, the page works as usual with no memory bar.

## Terms (version 3) and what is recorded

Each result is recorded on the server in an audit log: what you enter (names, birth date, time and place, the loved person's details if entered, and your current city if entered) and a summary of the result. Not recorded: IP address, user agent, referrer, the address you visited. Nothing is recorded without acceptance, or for requests with `noaudit`. Creating a hidden link sends your details once to the site, which does not store or record them; opening a hidden link is recorded. A single functional cookie remembers your acceptance. The remembered details above stay in your browser and are sent only when you submit a form.

## Known limits

- Planets are available for births 1800-2100 and are approximate; Sun and Moon are very accurate.
- The Ascendant (and houses and wheel) are approximate at extreme latitudes and are marked so.
- A partner with only a birth date has an approximate chart: no Ascendant, Midheaven or reliable Moon.
- Place labels in short codes are cut at 32 bytes; the recipient sees the cut label.
- Short codes are limited to 400 characters; a longer reading needs the long link.
- Houses use a single system; there is no choice of house system.
- English only, tropical zodiac only.
