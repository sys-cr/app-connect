<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: 2026 Bodo Steffen -->
<!-- Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt -->

# Places of course dates

Where a course date takes place. Stud.IP's JSON:API only carries the free-text
room of a date; booked rooms, their coordinates and links to a site's room
finder come from this plugin route. The iOS and Android apps read it.

## Announcement

The discovery lists the route as a feature:

```json
"features": { "eventPlaces": { "version": 1, "route": "app-connect/course-dates/places" } }
```

The route is relative to the JSON:API base (`…/jsonapi.php/v1/`). Without this
feature the apps show the free-text room and do not ask.

## Request

`GET /jsonapi.php/v1/app-connect/course-dates/places?filter[ids]=<id>,<id>,…`

- Authenticated like any JSON:API call (OAuth2 token of the app client).
- 1 to 50 date ids (32 hex characters), comma separated. More, none or no valid
  id → `400 app-connect-invalid-ids`. Without sign-in → `401`. App connection
  switched off → `404 app-connect-disabled`.
- Dates the person may not see, and unknown ids, are left out of the answer
  without an error.
- Apps ask for all dates of a detail view in one request, never per list row.

## Response

`200`, `Content-Type: application/json`, `Cache-Control: private, no-store`:

```json
{
  "data": [
    {
      "dateId": "a410c413a1f394ff7a3d1d9ffa6aa073",
      "places": [
        {
          "name": "Raum Hörsaal 1",
          "coordinates": { "lat": 52.3822, "lon": 9.7178 },
          "link": "https://uni.example/raumfinder?r=HS1"
        }
      ]
    }
  ]
}
```

- `places`: one entry per booked room, named as the web interface shows it.
  Without a booking, one entry with the free-text room; without either, `[]`.
- `coordinates` (optional): decimal degrees from the room's position, else its
  building's, else its location's, as stored by the room management.
- `link` (optional): the value of the room property named in the configuration
  entry `APP_CONNECT_ROOM_FINDER_PROPERTY`, only if it is an `https` address.

## App behaviour

Apps accept only `https` links and coordinates in range and skip entries without
a name. Coordinates and room name go to a maps service only when the person taps
"open in maps"; the link opens in the browser.
