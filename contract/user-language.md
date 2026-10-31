<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: 2026 Bodo Steffen -->
<!-- Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt -->

# Language of the signed-in person

The language a person uses Stud.IP in, so the apps can follow it instead of
the device language when the person switches that on. Stud.IP's JSON:API does
not carry the profile language; this plugin route does.

## Announcement

```json
"features": { "userLanguage": { "version": 1, "route": "app-connect/user-language" } }
```

The route is relative to the JSON:API base (`…/jsonapi.php/v1/`). Without this
feature the apps keep the device language and offer no "follow Stud.IP"
setting for that site.

## Request

`GET /jsonapi.php/v1/app-connect/user-language`

- Authenticated like any JSON:API call (OAuth2 token of the app client).
- Without sign-in → `401`. App connection switched off → `404 app-connect-disabled`.

## Response

`200`, `Content-Type: application/json`, `Cache-Control: private, no-store`:

```json
{ "data": { "language": "en_GB", "source": "profile" } }
```

- `language`: a Stud.IP locale (`de_DE`, `en_GB`, …). It is the language
  chosen in the profile while the site still offers it, otherwise the site's
  default language.
- `source`: `profile` or `default`, telling the two cases apart.
- Apps map the locale to a language they ship (today `de`, `en`) and keep the
  device language for any other.
