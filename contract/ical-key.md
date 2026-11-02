<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: 2026 Bodo Steffen -->
<!-- Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt -->

# Calendar key of the signed-in person

Stud.IP serves a person's calendar as an iCal feed at
`dispatch.php/ical/index/<key>`, without a session: the key is the whole
authorisation, which is what lets a calendar app poll the feed at all. The
key is created on the calendar's publish page in the web interface, and
Stud.IP's JSON:API has no route that hands it out. Without this route the
way into a subscription leads through the browser and a copied string.

The key lives where the core keeps it (`ICAL_EXPORT_KEY` in the person's
configuration), so the core's export serves it unchanged. New keys are made
by the plugin, not by the core's `IcalExport::makeKey()`: that one builds
eight characters from `rand()`, whose state can be recovered from its output,
and a route that creates keys on request would let anyone with an app token
collect as many as they like.

## Announcement

```json
"features": { "icalKey": { "version": 1, "route": "app-connect/me/ical-key" } }
```

The route is relative to the JSON:API base (`…/jsonapi.php/v1/`). Without
this feature the apps offer the manual way instead: open the publish page in
the browser, paste the key.

Operators switch it off with `APP_CONNECT_FEATURE_ICAL_KEY` in the
configuration section "Stud.App".

## Request

`GET /jsonapi.php/v1/app-connect/me/ical-key`

- Only with the OAuth2 token of the app client. Web sessions, HTTP Basic and
  tokens of other OAuth2 clients are refused (`401
  app-connect-token-required`, `403 app-connect-wrong-client`): the key is a
  lasting read access, and a third-party token must not turn into one that
  outlives it. The same holds for `DELETE`.
- Returns the key of the token holder and no one else; there is no way to
  read another person's key.
- Without a key one is created, so the person does not have to find a page
  first. They asked for the subscription, and the call is idempotent.
- A key the person already has is returned as it is, even one the core made
  on the calendar page; replacing it would end the subscriptions built on
  it. A reset (below) gives a new key.
- At most five keys are created per person and hour. Beyond that → `429
  app-connect-too-many-requests` with `Retry-After` (seconds until the hour
  ends); a key that already exists is still returned.
- Without sign-in → `401`. App connection switched off → `404
  app-connect-disabled`. Feature switched off → `404
  app-connect-ical-key-disabled`.

## Response

`200`, `Content-Type: application/json`, `Cache-Control: no-store`:

```json
{
  "data": {
    "key": "k3v9q2m7x8c4w1z6t5b0n2r8h4j7d9f3",
    "subscribeUrl": "webcal://studip.example/dispatch.php/ical/index/k3v9q2m7x8c4w1z6t5b0n2r8h4j7d9f3",
    "active": true
  }
}
```

- `key`: letters and digits, 6 to 32 of them. Keys created here are 32
  lower-case letters and digits from a cryptographic random generator; a key
  the core made earlier has eight characters by default, or the length the
  site chose.
- `subscribeUrl`: the `webcal://` address for the system calendar, or `null`
  when the site cannot be reached the way a calendar app needs it — https on
  the default port. Apps then show the key instead of a link that would
  point somewhere else.
- `active`: `true` while a key is in place.

## Reset

`DELETE /jsonapi.php/v1/app-connect/me/ical-key` → `204`

Drops the key, which stops every subscription built on it: the way back once
the key has been passed on or a device was lost. The next `GET` makes a new
one, within the limit above. Idempotent — a person without a key gets `204` as well.

## What is logged

Creating and dropping a key each write one entry in the Stud.IP event log
(`APPCONNECT_ICAL_KEY_CREATE`, `APPCONNECT_ICAL_KEY_RESET`). They name the
person, never the key, and the core deletes them after 90 days like the
web-login entries.
