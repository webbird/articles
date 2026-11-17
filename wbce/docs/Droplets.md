# Droplets, RSS und Links

## Artikel auf anderen Seiten: `[[articles_list]]`

Das Droplet zeigt Artikel auf einer beliebigen Seite, etwa die vier neuesten auf der Startseite. Es versteht dieselben Parameter wie `fetchNewsItems` aus `news_img`; bestehende Aufrufe brauchen nur den neuen Namen.

```text
[[articles_list?group_id_type=section_id&group_id=8&max_news_items=4]]
```

| Parameter | Standard | Bedeutung |
|---|---|---|
| `group_id_type` | `group_id` | wonach `group_id` filtert: `group_id`, `section_id`, `page_id` oder `post_id` (Artikelnummer) |
| `group_id` | `0` | Nummern, durch Komma getrennt; `0` = alle Artikel aller Abschnitte |
| `max_news_items` | `10` | höchstens so viele Artikel (1 bis 999) |
| `start_news_item` | `0` | so viele überspringen; negativ zählt vom Ende |
| `sort_by` | `1` | `1` eigene Reihenfolge, `2` letzte Änderung, `3` Sichtbar ab, `4` Zufall, `5` Sichtbar bis |
| `sort_order` | `1` | `1` absteigend, `2` aufsteigend |
| `tags` | | nur Artikel mit mindestens einem dieser Tags (Namen, durch Komma). Ohne Angabe folgt das Droplet einem `?tags=` in der Adresse der Seite |
| `skip` | | Artikel mit diesen Tags weglassen |
| `groups_on_tags` | `0` | `1`: den Filter `group_id` auch bei `tags` oder `skip` anwenden |
| `not_older_than` | `0` | nur Artikel, deren „Sichtbar ab“ höchstens so viele Tage zurückliegt. Artikel ohne „Sichtbar ab“ fallen dabei heraus |
| `lang_filter`, `lang_id` | `0`, `AUTO` | `lang_filter=1`: nur Artikel von Seiten in der Sprache `lang_id` (`AUTO` = Sprache der aktuellen Seite) |
| `strip_tags`, `allowed_tags` | `1`, `<p><a><img>` | HTML aus den Texten entfernen, bis auf die erlaubten Tags |
| `max_news_length` | `-1` | Kurztext auf so viele Zeichen kürzen, als reiner Text; `-1` = ungekürzt |
| `view` | | CSS einer bestimmten View, sonst das des ersten gefundenen Abschnitts |
| `aslist` | `0` | `1`: nur die Titel als einfache Liste |

Jeder Artikel erscheint mit dem Template „Liste: Artikel“ seines eigenen Abschnitts. Kopf und Fuß kommen vom ersten Abschnitt. Gezeigt werden nur sichtbare Artikel, und nur von öffentlichen oder versteckten Seiten und aus Abschnitten in ihrem Veröffentlichungszeitraum. Artikel von Seiten, die nur für angemeldete Benutzer sind, erscheinen im Droplet nicht.

## Bildkarussell: `[[articles_slider]]`

```text
[[articles_slider?section_id=8]]
```

Zeigt die sichtbaren Artikel eines Abschnitts mit Vorschaubild als Karussell, in der Reihenfolge der Liste. Die mitgelieferte Vorlage `bs5carousel` braucht Bootstrap 5 im Template der Website. Mit `tpl=<name>` lässt sich eine eigene Vorlage aus `modules/articles/templates/slides/<name>.txt` wählen. Das Karussell zeigt die Originalbilder, ab dem zweiten Bild werden sie erst beim Blättern geladen.

## RSS-Feed

```text
https://example.com/modules/articles/rss.php?page_id=7&section_id=8
```

Der Feed enthält die sichtbaren Artikel des Abschnitts mit Titel, Kurztext und Link, die zuletzt geänderten zuerst. Mit `&group_id=3` nur die einer Gruppe. Für Seiten, die nicht öffentlich sind, für Abschnitte außerhalb ihres Zeitraums und für inaktive Gruppen antwortet er mit „nicht gefunden“.

## Suche

Die Suche der Website findet sichtbare Artikel über Titel, Kurztext, Langtext und Block 2 und verlinkt direkt auf ihre Detailseite. Gruppen mit sichtbaren Artikeln führen zur gefilterten Liste.

## Links auf Artikel

Im Text einer Seite verweist `[articles:46]` auf den Artikel Nummer 46. Beim Anzeigen wird daraus die aktuelle Adresse der Detailseite, auch wenn sich die Adresse später ändert. Solange der Artikel nicht sichtbar ist, bleibt `[articles:46]` als Text im Link stehen. Das gilt auch, wenn seine Seite im Papierkorb liegt oder keine Sichtbarkeit hat, oder wenn sein Abschnitt außerhalb seines Zeitraums ist. Auf privaten und nur für registrierte Besucher sichtbaren Seiten funktioniert der Link, angezeigt wird der Artikel dann wie die Seite nur angemeldeten Besuchern.

Der Link-Dialog des Editors bietet die sichtbaren und geplanten Artikel einer Seite zur Auswahl an. In WBCE 1.7.0 erscheint diese Auswahl wegen eines Fehlers im Core noch nicht (gemeldet). Bis zur Korrektur lässt sich `[articles:NN]` als Linkadresse auch von Hand eintragen.

## Titel und Beschreibung im Seitenkopf

Mit dem Core-Modul `simplepagehead` stehen auf der Detailseite der Titel und der Kurztext des Artikels in `<title>` und in der Beschreibung. Dafür braucht `simplepagehead` einen Eintrag für `articles`, der an WBCE gemeldet ist. Ohne ihn erscheint dort der Titel der Seite.
