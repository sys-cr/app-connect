<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: 2026 Bodo Steffen -->
<!-- Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt -->

# Token revocation

When a person signs out in the app, the app tells the instance that its
tokens are no longer wanted. The plugin serves a revocation endpoint per
RFC 7009 for the app client; the Stud.IP core has none.

## Announcement

```json
"oauth2": {
  "tokenEndpoint": "https://studip.example.org/jsonapi.php/v1/app-connect/token",
  "revocationEndpoint": "https://studip.example.org/jsonapi.php/v1/app-connect/revoke"
}
```

`oauth2.revocationEndpoint` is an absolute URL on the instance's origin, like
the token endpoint. Apps refuse a discovery whose revocation endpoint is on
another origin (test vector `cross-host-revocation-endpoint.json`). Without
the field, the app only deletes its tokens on the device.

## Request

`POST /jsonapi.php/v1/app-connect/revoke`,
`Content-Type: application/x-www-form-urlencoded`, without an `Authorization`
header:

| Parameter | Required | Meaning |
|---|---|---|
| `client_id` | yes | the `oauth2.clientId` of the discovery; the app is a public client and authenticates with it alone |
| `token` | yes | the refresh token or the access token to revoke |
| `token_type_hint` | no | `refresh_token` or `access_token`; the plugin recognises the kind by the token's form, so the hint changes nothing |

The apps send the refresh token with `token_type_hint=refresh_token`. Values
are form-encoded; the tokens of the core contain only `[0-9a-f]` (refresh
token) or `[A-Za-z0-9_.-]` (access token, a JWT), so they need no escaping.

## Effect

The named token and the other token of its pair are revoked in the core's
tables (`oauth2_access_tokens`, `oauth2_refresh_tokens`):

- a refresh token revokes itself and the access token it was issued with;
- an access token revokes itself and every refresh token issued with it.
  Its signature is checked, its expiry is not: an expired access token still
  ends its refresh token.

If a device on "Stud.App Verbindung" is bound to that pair, it counts as
removed, exactly as if the person had removed it there (successor tokens of a
lineage refreshed at the core endpoint included). A pair that is revoked
already stays revoked without an error. A token of an older step of the
lineage ends only that step, not the device's current tokens.

## Response

| Status | Body | When |
|---|---|---|
| `200` | empty | every well-formed request with the app's `client_id`: the token was revoked, or it is unknown, expired, revoked already, malformed, or belongs to another client |
| `400` | `{"error":"invalid_request", …}` | `token` missing or empty |
| `401` | `{"error":"invalid_client", …}` | `client_id` missing or not the app client |
| `503` | `{"error":"temporarily_unavailable", …}`, `Retry-After: 600` | rate limit reached (below) |
| `404` | JSON:API error, `code` `app-connect-disabled` | app connection switched off |

All answers carry `Cache-Control: no-store` and `Pragma: no-cache`. The `200`
never says whether a token existed or belonged to the app, so the endpoint
cannot be used to test tokens. RFC 7009 lets a server refuse a token issued
to another client with an error; the plugin answers `200` instead and leaves
the token alone, because an error would confirm that the token is valid.

On `503` the client must assume that the token still exists (RFC 7009
§ 2.2.1). The apps sign out on the device whatever the answer is.

## Rate limit

Every request with the app's `client_id` and a `token` counts, whether the
token is known or not: 30 per client address in 10 minutes and 300 per minute
for the whole instance. Opening a refresh token costs a key
derivation, so the limit protects the server, not the tokens; the tokens are
encrypted or signed and cannot be guessed. Addresses are counted as a keyed
HMAC in the Stud.IP cache, never in plain form. The address is `REMOTE_ADDR`;
behind a reverse proxy the operator must make it the client's address (see
the operator manual).

## Test vectors

`test-vectors/token-revocation.json` lists requests and the answers the
plugin gives; the discovery vectors `revocation-endpoint.json` and
`cross-host-revocation-endpoint.json` cover the announcement.
