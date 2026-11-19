# Platzhalter

Die Templates (siehe [Einstellungen](Einstellungen.md#templates)) sind HTML mit Platzhaltern in eckigen Klammern. `articles` kennt dieselben Namen wie `news_img`, importierte Templates funktionieren daher ohne Umbau.

- Unbekannte Platzhalter bleiben als Text stehen.
- Bekannte ohne Wert werden leer.
- Ersetzt wird in einem Durchgang: Steht im Artikeltext selbst `[TITLE]`, bleibt das als Text erhalten.
- `{SYSVAR:MEDIA_REL}` wird überall zur Adresse des Medienordners.

**Text oder HTML:**
- Reine Textwerte (Titel, Namen, Bildtexte) sind für HTML maskiert und lassen sich direkt einsetzen, auch in Attribute.
- HTML-Werte (Inhalte, Bilder, Tags, fertige Links) kommen so, wie sie sind.

## Artikel

Gelten in „Liste: Artikel“, in den drei Templates der Detailseite und in „Block 2“.

| Platzhalter | Inhalt |
|---|---|
| `[POST_ID]` | Nummer des Artikels |
| `[TITLE]` | Titel |
| `[LINK]` | Adresse der Detailseite. Aus der Liste heraus mit `?p=`/`?g=`, damit „Zurück“ auf derselben Seite der Liste landet |
| `[HREF]` | `href="…"` zur Detailseite, nur wenn der Artikel einen Langtext oder eine Galerie hat, sonst leer |
| `[AOPEN]`, `[ACLOSE]` | `<a href="…">` und `</a>` zur Detailseite, unter derselben Bedingung wie `[HREF]` |
| `[SHOW_READ_MORE]` | `visible` oder `hidden`, für `style="visibility:[SHOW_READ_MORE]"` am Link „Weiterlesen“ |
| `[TEXT_READ_MORE]` | „Weiterlesen…“ |
| `[IMAGE]` | `<img>` mit dem Vorschaubild. Ohne eigenes Bild das Gruppenbild, sonst das Standardbild, in Listen zuletzt eine Kachel mit dem Anfangsbuchstaben, auf der Detailseite dann nichts |
| `[POST_OR_GROUP_IMAGE]` | wie `[IMAGE]` |
| `[IMAGE_URL]` | Adresse des eigenen Vorschaubilds, ohne Ersatzbilder, sonst leer |
| `[SHORT]`, `[CONTENT_SHORT]` | Kurztext (HTML) |
| `[CONTENT_LONG]` | Langtext (HTML) |
| `[CONTENT]` | Kurz- und Langtext hintereinander |
| `[CONTENT_BLOCK2]` | Block 2 des Artikels (HTML) |
| `[GROUP_ID]` | Nummer der Gruppe, `0` ohne Gruppe |
| `[GROUP_TITLE]` | Name der Gruppe |
| `[GROUP_IMAGE]` | `<img>` mit dem Gruppenbild |
| `[GROUP_IMAGE_URL]` | Adresse des Gruppenbilds |
| `[DISPLAY_GROUP]` | `none` ohne Gruppe, sonst `inherit`, für `style="display:[DISPLAY_GROUP]"` |
| `[DISPLAY_IMAGE]` | `none` ohne Gruppenbild, sonst `inherit` |
| `[TAGS]` | die Tags als farbige Etiketten mit Link auf die gefilterte Liste (HTML) |
| `[TAGLIST]` | die Namen der Tags, durch Komma getrennt |
| `[PUBLISHED_DATE]`, `[PUBLISHED_TIME]` | „Sichtbar ab“, ohne Startdatum der Zeitpunkt des Anlegens |
| `[CREATED_DATE]`, `[CREATED_TIME]` | Zeitpunkt des Anlegens |
| `[MODI_DATE]`, `[MODI_TIME]` | letzte Änderung (oder „Sichtbar ab“, falls später) |
| `[USER_ID]` | Nummer des Benutzers, der den Artikel angelegt hat |
| `[DISPLAY_NAME]`, `[USERNAME]` | sein Anzeigename bzw. Benutzername |
| `[EMAIL]` | seine E-Mail-Adresse. Achtung: steht damit öffentlich auf der Seite |
| `[PAGE_TITLE]` | Titel der Seite |
| `[BACK]` | Adresse der Liste, auf derselben Seite und Gruppe |
| `[TEXT_BACK]`, `[TEXT_POSTED_BY]`, `[TEXT_ON]`, `[TEXT_AT]`, `[TEXT_O_CLOCK]`, `[TEXT_LAST_CHANGED]`, `[TEXT_PREV_POST]`, `[TEXT_NEXT_POST]` | feste Texte in der Sprache der Seite, etwa „Zurück zur Übersicht“, „Veröffentlicht von“, „am“, „um“, „Uhr“ |

Datum und Uhrzeit erscheinen im Format und in der Zeitzone der Website.

## Nur auf der Detailseite

| Platzhalter | Inhalt |
|---|---|
| `[IMAGES]` | die Galerie: die Bild-Schleife für jedes Bild |
| `[PREVIOUS_LINK]`, `[NEXT_LINK]` | Adresse des vorigen bzw. nächsten Artikels (in der Reihenfolge der Liste, bei `?g=` innerhalb der Gruppe) |
| `[PREVIOUS_PAGE_LINK]`, `[NEXT_PAGE_LINK]` | fertiger Link mit „Voriger Beitrag“ bzw. „Nächster Beitrag“ |
| `[DISPLAY_PREVIOUS_NEXT_LINKS]` | `visible`, wenn es einen vorigen oder nächsten Artikel gibt, sonst `hidden` |

## Kopf und Fuß der Liste

In „Liste: Kopf“ und „Liste: Fuß“ gelten nur diese Platzhalter zum Blättern. Sie sind leer, wenn „Artikel pro Seite“ 0 ist.

| Platzhalter | Inhalt |
|---|---|
| `[PREVIOUS_LINK]`, `[NEXT_LINK]` | fertiger Link zur vorigen bzw. nächsten Seite der Liste, Text „vorherige“ bzw. „nächste“ |
| `[PREVIOUS_PAGE_LINK]`, `[NEXT_PAGE_LINK]` | dasselbe mit dem Text „vorherige Seite“ bzw. „nächste Seite“ |
| `[OF]`, `[OUT_OF]` | Angabe wie „1-5 von 12“; beide nutzen einen Text aus WBCE, im Deutschen jeweils „von“ |
| `[DISPLAY_PREVIOUS_NEXT_LINKS]` | `visible`, wenn es mehr als eine Seite gibt, sonst `hidden` |

Beachte: `[PREVIOUS_LINK]` und `[NEXT_LINK]` sind hier fertige Links, auf der Detailseite nur Adressen. So war es schon in `news_img`.

## Bild-Schleife der Galerie

| Platzhalter | Inhalt |
|---|---|
| `[IMAGE]` | Adresse des Bilds in der Großansicht |
| `[THUMB]` | Adresse des Thumbnails |
| `[DESCRIPTION]` | Bildtext |
| `[THUMBWIDTH]`, `[THUMBHEIGHT]` | Breite und Höhe der Thumbnail-Größe aus den Einstellungen |
| `[WB_URL]` | Adresse der Website |

## URL-Parameter der Liste

Die Liste versteht dieselben Parameter wie `news_img`. Ungültige Werte werden ignoriert.

| Parameter | Wirkung |
|---|---|
| `p=5` | Blättern: Liste ab dem 6. Artikel (bei „Artikel pro Seite“) |
| `g=3` | nur Artikel der Gruppe 3 |
| `tags=Region,Saisonal` | nur Artikel mit mindestens einem dieser Tags |
| `m=10&y=2026&method=1` | nur Artikel eines Monats. `method=1`: nach „Sichtbar ab“, `method=0`: nach der letzten Änderung |

Ist die Liste nach Gruppe, Tag oder Monat gefiltert, steht darüber ein Hinweis wie „Gefiltert nach Tag „Saisonal“. Alle Artikel anzeigen“. Der Link führt zur ungefilterten Liste. Der Hinweis steht außerhalb der Templates in `<p class="mod_articles_filter">`, per CSS lässt er sich gestalten oder ausblenden.
