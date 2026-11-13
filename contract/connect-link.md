<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: 2026 Bodo Steffen -->
<!-- Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt -->

# Connect link and landing page URL

How a Stud.IP instance hands its address – and optionally a pairing code – to the
app. The plugin produces these values, the iOS and Android apps parse them. The
test vectors in [`test-vectors/connect-link.json`](test-vectors/connect-link.json) are
shared by all three code bases; a change here goes together with the matching
tests on every side.

## 1. Forms

| Form | Where it appears | Example |
|---|---|---|
| Landing page URL | content of the QR code on the profile page | `https://studip.example.org/plugins.php/appconnect/get#p=7K2M9QX4T8WZ3RBN6FJ5HCDV0A` |
| Connect link | "Open in the app" on the landing page | `de.studapp2.open:/connect?host=studip.example.org&p=7K2M9QX4T8WZ3RBN6FJ5HCDV0A` |
| Connect link without code | address hand-over without pairing | `de.studapp2.open:/connect?host=studip.example.org` |
| Instance below a path | both forms carry the base path | `https://example.org/studip/plugins.php/appconnect/get#p=…`, `de.studapp2.open:/connect?host=example.org&path=%2Fstudip` |

The pairing code travels in the URL **fragment** of the landing page, so it never
reaches server logs, proxies or a `Referer` header. Opening the landing page does
not consume the code.

## 2. Host

The `host` parameter of the connect link, and the host of a landing page URL:

1. A DNS host name only: no scheme, no user info, no path, no query, no fragment.
2. Lower case. Internationalised names in Punycode (`xn--…`); non-ASCII characters
   are rejected, not converted.
3. Labels of 1–63 characters from `a–z`, `0–9` and `-`, not starting or ending with
   `-`; at least two labels; at most 253 characters in total.
4. No IP literals.
5. A port (`:1–65535`) is accepted **only in DEBUG builds**, and only together with
   the loopback exception below.
6. **DEBUG only:** `localhost` and `127.0.0.1`, with or without a port, for a local
   test instance over plain `http`. Release builds reject both.

## 3. Base path

Stud.IP may be installed below a path of its host, such as `https://example.org/studip/`.
That path is the **base path** (`/studip`); the root of the host has the empty base path.

1. Optional. Left out, the instance sits at the root of the host.
2. Starts with `/` and does not end with `/`; one to eight segments separated by `/`;
   each segment 1–63 characters from `A–Z`, `a–z`, `0–9`, `-`, `.`, `_`, `~`, and not `.`
   or `..`; at most 200 characters in total. Case is kept: paths are case-sensitive.
3. Connect link: parameter `path`, at most once, percent-encoded like any query value
   (`path=%2Fstudip`; an unencoded `/` is accepted too). The decoded value must follow
   rule 2; an empty value is rejected.
4. Landing page URL: its path is the base path followed by `/plugins.php/appconnect/get`.
   The base path is taken as it stands in the URL, without decoding, and must follow
   rule 2.
5. Every address the app builds starts with `<scheme>://<host>[:<port>]<base path>`,
   the discovery for example at `<…><base path>/jsonapi.php/v1/app-connect/discovery`.
   The addresses in the discovery stay below the base path.
6. The app identifies an instance by host, port and base path (`example.org/studip`). For
   the root this is the host as before, so existing connections keep their identity.
7. Apps that predate the base path ignore the unknown `path` parameter and ask the root
   of the host, which fails with "no Stud.App connection" instead of connecting
   elsewhere.

## 4. Pairing code

1. 26 characters of the Crockford Base32 alphabet
   `0123456789ABCDEFGHJKMNPQRSTVWXYZ` (no `I`, `L`, `O`, `U`), i.e. 130 random bits.
2. Generated in upper case. Parsers accept lower case and normalise to upper case;
   any other character – including `I`, `L`, `O`, `U`, `-` and spaces – is rejected.
3. Valid for 5 minutes and once. The first successful redemption wins.

## 5. Parameters

- Connect link: path exactly `/connect`, no authority (`de.studapp2.open:/connect`,
  not `de.studapp2.open://connect`).
- `host` exactly once; `path` and `p` at most once each. Unknown parameters are ignored.
- Landing page URL: `https`, path `<base path>/plugins.php/appconnect/get`, code in the
  fragment as `p=<code>`; no query. DEBUG builds also accept `http://localhost` and
  `http://127.0.0.1` with a port.

## 6. Behaviour of the apps

- A link never connects on its own. The app fetches the discovery of the host,
  shows the instance name and host, and connects only after the user confirms.
- A rejected link is dropped silently; logs name the reason, never the value.
- The code is never logged or persisted; it is used once for the pairing grant of
  the token endpoint.
