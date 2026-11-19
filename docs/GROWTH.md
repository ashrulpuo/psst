# Distribution playbook

Features do not spread a link; people do. This is the part the owner runs. It assumes the product as built: a public
launch with no closed beta (design doc, section 9), so Day 0 is a same-day public seeding, not a beta.

Fields marked `[FILL IN]` are the owner's decisions. Numbers here are targets and guesses, not benchmarks. No growth
figure is promised.

Background: `docs/superpowers/specs/2026-11-17-production-launch-design.md` (section 9 is the loop and metrics),
`docs/DEPLOY.md` (Turnstile, rollback).

## 0. What the product gives you

| Piece | Where | Notes |
|---|---|---|
| Story card | Link page, "Share story card" (five styles: Sunset, Mint, Ink, Candy, Paper) | 9:16 PNG that shows the link. |
| Received-note story card | Inbox, "Share this note to your story" on a received note | The first share shows a notice: anyone who sees the story can read the note; the card hides the sender, but the wording might not. |
| Conversion card | After a guest sends: "Want your own link?" with "Get my link" | Shown only to guests who are not signed in. |
| Link previews | `/preview/{username}` | Crawlers only. Meta's crawlers (Instagram, Threads previews) get a username-only preview; real people in Instagram's in-app browser get the app. |
| Prompt chips | Send form, "Prompt ideas" | Four built in: "say something nice", "confess", "roast me", "a secret". |

## 1. Pick one beachhead

One tight community beats a broad launch: people send when they see others' links, and a link seen ten times in one
place pulls more than the same ten spread over ten places.

Beachhead: `[FILL IN: community + platform]`

| Option | Pros | Cons |
|---|---|---|
| One campus or school (class, club, year group) | Dense social graph, everyone sees everyone's stories, easy same-day seeding | Highest moderation risk (bullying); needs the section 8 routine from hour one |
| One creator's followers | Built-in audience, one post reaches everyone | Weak peer density; followers send, few get their own link |
| One friend network (your circle and their circles) | Fastest to start, you can ask favours, lowest abuse risk | Small ceiling; the numbers stay noisy |
| One interest community (a Discord, a fandom, a game group) | Shared in-jokes make good prompts; people already post publicly | Needs a trusted insider; community rules may ban promo |

Recommendation: a friend network that overlaps one campus or interest group. It guarantees Day 0 volume (you can
message 20 to 30 people directly) and gives you an edge into a denser community. Pick the one where you can personally
message 20 to 30 people today.

Before announcing:
- [ ] Beachhead chosen and written above.
- [ ] Smoke checks in `docs/DEPLOY.md` pass and your admin login works.
- [ ] `TURNSTILE_SECRET` and `QCLI_TURNSTILE_SITE_KEY` set (section 8), so guest sends are not open to bots.
- [ ] `ADMIN_ALERT_EMAIL` set, so you hear about flagged messages.
- [ ] You have done the restore drill (`docs/DEPLOY.md` section 9).

## 2. Day 0 seeding (public, same day)

Goal: for a few hours the beachhead sees links everywhere. This is not a beta: the site is open to anyone.

Announcement community: `[FILL IN: where you post the announcement]`
Date and time: `[FILL IN]`

Plan:
1. Morning: message 20 to 30 people from the beachhead. Template:

```text
Hey, I'm launching psst today. It's a link where people can message you without giving their name.
Could you sign in at <domain>, tap "Share story card", and post it on your story at <time> with the rest of us?
One post is all I'm asking. Reply here when it's up.
```

2. At the agreed time everyone posts their story card. You post first.
3. Post the announcement in the community above, with your own link.
4. Evening: check the admin Growth tiles and the Flags page (sections 7 and 8).

Do not claim spots are limited or that it is "going viral". There are no spots.

## 3. The note-post habit

The loop runs only if receivers post. Give early users one habit: post the first note you get, with your link.

Honest one-liner to use everywhere:

```text
People can message you without giving their name. Psst admins can open conversations to deal with abuse, and if
someone sends as a guest and later signs in on the same browser, those messages are linked to their account.
```

Script for early users:

```text
Got my first note on psst. The card doesn't show who sent it, but the wording might give it away.
Want your own link? <domain>/<username>
```

Rules:
- Never say "100% anonymous" or "untraceable". Match the Privacy page.
- Sharing a received note is the receiver's choice. The app warns them that anyone who sees the story can read the
  note; do not tell people to skip that.
- Ask before posting someone else's note. No invented notes or testimonials.

## 4. Micro-creator outreach

Target: creators with a few thousand to tens of thousands of followers inside the beachhead. Offer: a story card to
post and a custom username.

```text
Hi <name>, I made a small thing called psst: a link where your followers can message you without giving their name.
Would you try it? Sign in at <domain>, pick the username you want, and post the story card. No payment, no strings.
(Admins can open conversations to handle abuse, and you can block or report any sender.)
```

One message per creator, no pestering, no "other creators already joined" unless it is true and you can name them.
Track: `[FILL IN: creator, platform, date sent, answer]`.

## 5. Content ideas for your own channels

Real notes only, and only with the receiver's consent.

| Idea | Format |
|---|---|
| Reply-to-note clip | Screen-record a note arriving, reply on camera |
| Most savage note | Weekly pick from notes people send in for this on purpose |
| Prompt of the day | A prompt as a story sticker, link in bio |
| Story card post | The card itself is the post |

Weekly rhythm (suggestion):

| Day | Post |
|---|---|
| Mon | Prompt of the day |
| Wed | Reply-to-note clip |
| Fri | Most savage note |
| Sun | Read the Growth tiles, pick next week's prompt |

## 6. Prompt packs

Today there are four built-in prompt chips (`profile.prompts` in `client/src/i18n/en-US/index.ts`, shown under
"Prompt ideas" on the send form). That is all.

There is no data file for seasonal or community-specific packs and no per-link prompt, so adding them needs a code
change. Treat it as a future item. Until then, carry seasonal and community prompts on your own channels with the
"Prompt of the day" posts above.

## 7. What to measure and when to change course

Admin dashboard, "Growth" section, the last 7 UTC days (today plus the 6 before it; events are stored by UTC day only and rows older than 30 days are pruned nightly). The data is cached for about 60 seconds.

| Tile | Meaning |
|---|---|
| Guest sends | Messages sent by guests |
| Link shares | Successful copy, share or story-card download of a link by signed-in owners |
| Note shares | The same, for received-note story cards |
| Own links after guest | Own links created after a guest send |
| Conversion | Own links per guest send. Shows an em dash (—) when there are no guest sends |

Link shares and Note shares are reported by signed-in clients, so treat them as directional. The target on the page is
10%.

| Conversion | What to do |
|---|---|
| Under 5% | Fix the sent-card conversion ("Want your own link?" wording and placement, the sign-in path) before buying attention |
| 5% to 10% | Keep seeding the beachhead and change one thing at a time on the sent card; compare week to week |
| 10% or more | Push more distribution: widen the beachhead, more creators. Keep the safety routine |

Caveat: on a tiny base the numbers are noisy. Under about 30 Guest sends in the window there is no signal; do not
change course on it.

Daily routine (about 10 minutes):
- [ ] 2 min: Growth section; write down the five numbers.
- [ ] 5 min: Flags and Reports pages; act on anything open.
- [ ] 3 min: reply to creators and early users; post the day's content.

## 8. Safety during growth

Abuse spikes after any viral post. Check the flag queue the same day.

What exists:
- Flags page (`/admin/flags`, "Flagged messages"): messages that matched a keyword. They are still delivered; this is
  the review queue. The keyword list is `moderation.keywords` in `server/config/psst.php`.
- Owner email alert (`ADMIN_ALERT_EMAIL`): at most one per 10 minutes, no message text in it.
- Reports (admin Reports page), user bans (admin), and receiver blocks. A blocked guest's messages are quietly dropped,
  per receiver only.
- Turnstile on guest sends (`server/app/Support/Turnstile.php`): off unless `TURNSTILE_SECRET` is set. The challenge
  widget needs `QCLI_TURNSTILE_SITE_KEY` in the client build too. With the secret set, a missing or failed token gets a
  422 and the send is refused. Setup: `docs/DEPLOY.md` section 6.
- Rate limits, the `messages` limiter in `server/app/Providers/AppServiceProvider.php`. Guests: 4 per minute and 30
  per hour per IP, 3 per minute per browser token, 200 per hour per target username. Signed-in users: 20 per minute and
  300 per day.

Levers (env in `deploy/.env`, read by `server/config/psst.php`): `PSST_GUEST_SENDS=false` pauses anonymous sending
(guests get a 503 "Anonymous sending is paused for a moment."; signed-in senders are unaffected), and the limits above
are `PSST_GUEST_PER_IP_MINUTE`, `PSST_GUEST_PER_IP_HOUR`, `PSST_GUEST_PER_TOKEN_MINUTE`, `PSST_GUEST_PER_TARGET_HOUR`,
`PSST_USER_PER_MINUTE` and `PSST_USER_PER_DAY`. After editing `.env` run
`docker compose up -d --force-recreate app` (a plain restart does not re-read it), and `php artisan config:cache` if
config is cached.

If abuse jumps:
1. Turn Turnstile on if it is off (set both keys, rebuild the client, redeploy).
2. Ban abusers, resolve reports, and tell receivers they can block.
3. Tighten the guest limits with the `PSST_GUEST_*` env values and recreate the `app` container.
4. Set `PSST_GUEST_SENDS=false` to pause anonymous sending outright, and stop posting your link until it calms down.
