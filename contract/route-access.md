<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: 2026 Bodo Steffen -->
<!-- Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt -->

# Availability and access of the routes

Which plugin routes of the JSON:API answer when, and who may call them.

## Availability

Every route answers `404 app-connect-disabled` unless the app connection is
switched on (`APP_CONNECT_ENABLED`) **and** the app client exists. Routes with
an operator switch answer `404` with their own code while it is off:

| Route | Switch | Code |
|---|---|---|
| `GET user-events` | `APP_CONNECT_FEATURE_USER_EVENTS` | `app-connect-feature-off` |
| `GET course-dates/places` | `APP_CONNECT_FEATURE_EVENT_PLACES` | `app-connect-feature-off` |
| `GET`/`DELETE me/ical-key` | `APP_CONNECT_FEATURE_ICAL_KEY` | `app-connect-ical-key-disabled` |
| `POST web-login` | `APP_CONNECT_FEATURE_WEB_LOGIN` | `app-connect-web-login-disabled` |

The codes differ for historical reasons and stay as they are: both apps read
them. A new switch uses `app-connect-feature-off`.

## Access

| Route | Access | Why |
|---|---|---|
| `GET discovery`, `GET logo` | none | public facts of the instance |
| `POST token`, `POST revoke` | none, the app's `client_id` | OAuth2 endpoints of a public client (RFC 6749, RFC 7009) |
| `POST devices` | OAuth2 token of the app client | binds the token's lineage to a device; no other credential has one |
| `GET`/`DELETE me/ical-key` | OAuth2 token of the app client | the key gives lasting access to the calendar, beyond the token; a third-party token must not turn into it |
| `POST web-login` | OAuth2 token of the app client, or HTTP Basic for the same person | the link becomes a web session; a third-party token must not turn into one ([web-login.md](web-login.md)) |
| `GET user-events`, `GET course-dates/places`, `GET user-language` | any authentication of the core: session, HTTP Basic, OAuth2 token of any client | they only read, and only what the core's JSON:API returns to the same person as well |

Refusals: `401 app-connect-sign-in-required` without authentication, `401
app-connect-token-required` when the app's token is required and missing or
invalid, `403 app-connect-wrong-client` for a token of another client.
