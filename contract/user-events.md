<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: 2026 Bodo Steffen -->
<!-- Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt -->

# Calendar of the signed-in person

The core route `GET users/{id}/events` returns only the personal calendar
entries. The web calendar also shows the dates of the courses a person has
put into the calendar and their cancelled dates, and each course has a colour
the person chose (`seminar_user.gruppe`). This plugin route returns all of it
in one call, in the resource shapes of the core route, so an app can switch
from the core route by changing the path. No change to the Stud.IP core is
needed.

## Announcement

```json
"features": { "userEvents": { "version": 1, "route": "app-connect/user-events" } }
```

The route is relative to the JSON:API base (`…/jsonapi.php/v1/`). Without this
feature an app reads `users/{id}/events` and the dates per course
(`courses/{id}/events`) and takes the colour from `course-memberships`.

## Request

`GET /jsonapi.php/v1/app-connect/user-events?filter[timestamp]=<unix>&page[offset]=0&page[limit]=30`

- Authenticated like any JSON:API call; always the calendar of the caller.
- `filter[timestamp]`: start of the window as Unix time; default today 00:00
  (server time). The window lasts two weeks, as in the core route.
- `page[offset]`, `page[limit]`: default 0 and 30, the limit is capped at 100.

| Status | `code` | When |
|---|---|---|
| `401` | `app-connect-sign-in-required` | not signed in |
| `404` | `app-connect-disabled` | app connection switched off |
| `404` | `app-connect-feature-off` | feature `userEvents` switched off |
| `400` | `app-connect-invalid-timestamp` | `filter[timestamp]` is not a non-negative integer |
| `400` | `app-connect-invalid-page` | `page[offset]`/`page[limit]` not a number, or limit 0 |

## Sources

As the web calendar assembles them:

1. personal entries (`calendar_date_assignments` of the person, not declined),
   series once with their first date and `recurrence`;
2. dates of courses where the person has "show in calendar" on
   (`seminar_user.bind_calendar = 1`), without dates of hidden schedule
   entries and of groups the person is not in;
3. cancelled dates of those courses that carry a reason.

## Response

`200`, `Content-Type: application/vnd.api+json`, `Cache-Control: private, no-store`.
Resources are sorted by start time.

```json
{
  "data": [
    {
      "type": "course-events",
      "id": "<termin_id>",
      "attributes": {
        "title": "Mathematik I", "description": "", "start": "2026-11-02T10:15:00+01:00",
        "end": "2026-11-02T11:45:00+01:00", "type": 1, "categories": "Sitzung", "location": "Raum 1",
        "is-cancelled": false, "color": 4, "mkdate": "…", "chdate": "…", "recurrence": "…"
      },
      "relationships": { "owner": { "data": { "type": "courses", "id": "<course_id>" } } }
    },
    {
      "type": "calendar-events",
      "id": "<calendar_date_id>",
      "attributes": {
        "title": "Lerngruppe", "description": "", "start": "…", "end": "…", "categories": "",
        "location": "Bibliothek", "mkdate": "…", "chdate": "…", "recurrence": ""
      },
      "relationships": { "owner": { "data": { "type": "users", "id": "<user_id>" } } }
    }
  ],
  "meta": { "page": { "offset": 0, "limit": 30, "total": 2 } }
}
```

- `course-events` carry the attributes of the core schema plus `color`: the
  person's colour group of the course, 0–8 as in the web interface (0 is a
  colour too). `is-cancelled` is `true` for cancelled dates.
- `calendar-events` carry the attributes of the core schema; their `id` is the
  calendar date id.
