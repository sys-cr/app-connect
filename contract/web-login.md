<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: 2026 Bodo Steffen -->
<!-- Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt -->

# Web login from the app

The apps hold an OAuth2 token, which works for the JSON:API only. When an app
opens a page of the Stud.IP web interface (course enrolment, the Courseware
fallback, "Open in Stud.IP"), the browser has no session and the person would
have to sign in again. A one-time ticket hands the app's sign-in over to a web
session in that browser.

## Announcement

```json
"features": { "webLogin": { "version": 1, "route": "app-connect/web-login" } }
```

The route is relative to the JSON:API base (`…/jsonapi.php/v1/`). The operator
switches it off with `APP_CONNECT_FEATURE_WEB_LOGIN`; the feature is then
`false`, and the apps open web pages without a ticket (the person signs in in
the browser).

## Request

`POST /jsonapi.php/v1/app-connect/web-login`

- Authenticated with the OAuth2 token of the app client. HTTP Basic is also
  accepted for the same person; the plugin checks the password itself, also
  when the request carries a web session. Tokens of other OAuth2 clients and
  web sessions are refused, so a third-party token never turns into a web
  session.
- Body (optional), `Content-Type: application/json`:

  ```json
  { "return_to": "dispatch.php/course/details/index?cid=…" }
  ```

  `return_to` is a path relative to the Stud.IP base URL. It must start with
  `dispatch.php/`, `plugins.php/`, `index.php` or `seminar_main.php` (the last
  two followed only by `?`, `#` or nothing), consist of RFC 3986 characters and
  may carry one fragment (`#/structural_element/7`). Refused: schemes, a
  leading `/`, `//` anywhere, backslashes, control characters, `.` and `..`
  segments – also percent-encoded or encoded several times. Without
  `return_to` the ticket leads to the start page.

## Response

`201`, `Content-Type: application/json`, `Cache-Control: no-store`:

```json
{ "data": { "url": "https://studip.example.org/plugins.php/appconnect/web/enter#<ticket>", "expiresIn": 60 } }
```

- `url`: the address to open in the browser. It starts with the instance's
  base URL (`https://` on any production instance); the ticket is in the
  fragment, so the browser never sends it in a URL.
- `expiresIn`: seconds the ticket is valid. It is valid once.

Errors (JSON:API error documents):

| Status | `code` | When |
|---|---|---|
| `401` | `app-connect-sign-in-required` / `app-connect-token-required` | not signed in; neither the app's token nor HTTP Basic |
| `403` | `app-connect-wrong-client` | OAuth2 token of another client |
| `404` | `app-connect-disabled` | app connection switched off |
| `404` | `app-connect-web-login-disabled` | `APP_CONNECT_FEATURE_WEB_LOGIN` off |
| `400` | `app-connect-invalid-return-to` | `return_to` refused |
| `400` | `app-connect-invalid-body` | body is not a JSON object |
| `429` | `app-connect-too-many-requests` | more than 10 tickets for the person in 5 minutes; `Retry-After: 300` |

## The browser side

`GET plugins.php/appconnect/web/enter` shows a short page. Its script takes the
ticket from `location.hash`, removes it from the address bar and the history,
and posts it with the CSRF token of the page to `web/redeem`. Without
JavaScript the page says the link needs JavaScript and links to the login.

`POST plugins.php/appconnect/web/redeem` checks the CSRF token, hashes the
ticket and claims it with one `UPDATE … WHERE used_at IS NULL AND expires_at >
now`; only the request that changed the row continues. It then requires:

- the account exists and may sign in now (not locked, not expired, no pending
  e-mail activation, the admin/root IP range check, no maintenance mode unless
  root) – the checks of the core password login;
- app connection and web login still switched on;
- the app sign-in behind the ticket is still there: the device row active and
  its access token not revoked; without a device row the access token the
  ticket was requested with must not be revoked.

If the same person is signed in already, the browser is only redirected
(`303`). Otherwise `redeem` does not sign in yet: it keeps the claimed ticket
in the session for two minutes and asks "Als <full name> (<username>)
anmelden?", with a warning naming the person who is signed in in that browser,
if any. Only `POST plugins.php/appconnect/web/confirm` with the CSRF token and
the button "Anmelden" signs in; "Abbrechen" drops the ticket. A link someone
else made from their own account can therefore not sign a browser into that
account unnoticed (login CSRF). On confirmation the checks above run again,
another person's session ends (session data cleared, new session id), the
person is signed in like the core login (`auth()->setAuthenticatedUser()`,
`sess()->regenerateId()`), and the browser goes (`303`) to `return_to` below
the configured base URL, or to the start page. The core's first-request
handling then runs as after any login: terms of use, visibility choice,
Stud.IP's own two-factor prompt.

Any failure shows one message, "Der Link ist abgelaufen oder ungültig. Bitte
melde dich an.", and a login button; it never says which check failed. All
pages send `Cache-Control: no-store`, `Referrer-Policy: no-referrer`,
`X-Frame-Options: DENY` and `Content-Security-Policy: frame-ancestors 'none'`.

## Storage and logs

Tickets are 32 random bytes (`random_bytes`) as base64url, 43 characters. The
table `app_connect_web_login_tickets` stores the SHA-256 hash, the person,
`return_to`, the access token id and device row, and `created_at`,
`expires_at`, `used_at` – never the ticket. The daily cleanup deletes tickets
a day after they were issued; deleting an account deletes its tickets at once.
Removing a device or revoking the app's token needs no extra step: the
redeem check above refuses every ticket from that sign-in.

The event log records "issued", "redeemed" and "rejected" with the person only
(rejected only for tickets that exist, e.g. used or expired); ticket values are
never logged. The core deletes these entries after 90 days (`log_actions.expires`,
migration 010).

## Trade-off

A ticket signs the person in without a password. A second factor that the
identity provider asks for at a web sign-in (Shibboleth, OIDC) is not asked
for; Stud.IP's own two-factor authentication still prompts on the first page.
The app's sign-in came either from a web sign-in or from pairing started in a
signed-in web session, and the ticket only lives for 60 seconds, once.
Operators who require a second factor for every web session switch the
feature off.
