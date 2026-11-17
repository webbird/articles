# articles – Konzept

Stand: 2026-11-11. Grundlage ist der Code von `news_img`, nicht die alte Doku. Abschnitt 2 ist die Abnahmecheckliste, Abschnitt 11 hält die getroffenen Entscheidungen fest.

## Ziel

`articles` ist ein neues Seitenmodul für WBCE 1.7 und PHP 8.5. Es bietet den Funktionsumfang von `news_img` und läuft parallel dazu, wie `reservations` neben `bookings_v2`.

- `news_img` bleibt unverändert installiert und wird upstream als 5.0.x weitergepflegt.
- Der Umstieg passiert pro Section über einen Import. Die Quelle bleibt dabei unangetastet.
- Weil das Modul neu ist, gibt es keine Altlasten: eigene Tabellen, eigenes Schema, kein Upgrade-Pfad aus `news_img`.

Neue Funktionen gehören nicht in Version 1.0. Geplantes wie Mehrfach-Gruppen, Bild-Credits oder die View-README kommt danach. Das Schema darf es nur nicht verbauen.

## 1. Ausgangslage

| Quelle | Stand | Beitrag |
|---|---|---|
| `News-with-Images`, Branch `v6.0` | 80 Commits nach 5.0.30 | Funktionsumfang und Sicherheitsarbeit von 2026 |
| `News-with-Images`, Upstream `main` | 5.0.34 | Fixes aus 5.0.31 bis 5.0.34, fehlen in `v6.0` |
| `WBCE_CMS`, Branch `1.7.0` | 5.0.32 | 1.7-Anpassungen: `LinkResolver.php`, `install_struct.sql`, flatpickr, PDO |
| WBCE-Portable | 5.0.32 | Laufende 1.7-Testumgebung, dazu die Besitzprüfungen |
| `root/wbce` | 5.0.32 plus Reste | Nur noch Quelle für den Slider: `droplet_nwi_slider.zip`, `templates/slides/` |

Aus den Upstream-Fixes werden Anforderungen an `articles`:

- **5.0.32:** Jede Aktion prüft, ob das Zielobjekt auf einer Seite liegt, die die Person verwalten darf. „Einstellungen nur für Admins“ gilt auch serverseitig. `gallery` und `view` sind Whitelists.
- **5.0.34:** Der RSS-Feed liefert nur, wenn die Section zur Seite gehört, die Seite für den Besucher sichtbar ist und die Section im Publikationszeitraum liegt. Gruppen nur, wenn sie aktiv sind und zur Section gehören. `]]>` im Inhalt bricht die CDATA nicht.
- **Fix vom 03.11.:** Vorhandene Access-Dateien werden beim Anzeigen nicht neu geschrieben, nur fehlende angelegt.

## 2. Funktionsumfang (Soll)

Diese Liste ist die Abnahmecheckliste.

### Backend: Artikel

- [x] Liste mit Gruppe, Status, Tag-Anzahl und Zeitraum. Statt des Zeitraum-Icons nennt der Status den Grund: sichtbar, inaktiv, geplant, abgelaufen, versteckt (inaktive Gruppe). Dazu Filter nach Gruppe, Status und Titel
- [x] Sortieren per Drag & Drop und Pfeilen, nur bei benutzerdefinierter Reihenfolge und ohne Filter
- [x] Aktivieren/Deaktivieren und Löschen einzeln
- [x] Massenaktionen: kopieren und verschieben (je mit oder ohne Tags), auch in andere Sections und Seiten; löschen; aktivieren; deaktivieren; Gruppe setzen; Tags setzen; Start- oder Enddatum leeren; Verschieben in eine andere Section legt dort fehlende lokale Tags mit gleichem Namen und gleicher Farbe an und nutzt vorhandene
- [x] Kopien sind inaktiv, ihre Bilder werden mitkopiert
- [x] Editor: Titel, Slug, Vorschaubild, Aktiv, Start und Ende, Tags, Gruppe inklusive Wechsel der Section, Kurztext, Langtext, Block 2
- [x] „Speichern“ und „Speichern und zurück“, dazu der Ansehen-Link aufs Frontend
- [x] Galerie: Mehrfach-Upload, Bildtexte, Sortierung, Löschen. Hochgeladen wird sofort pro Datei, ohne JS und bei neuen Artikeln mit „Speichern“
- [x] Wartung: Access-Dateien erneuern, Artikel ins aktuelle Verzeichnis verschieben, Bildgrößen neu berechnen

### Backend: Gruppen, Tags, Einstellungen

- [x] Gruppen: anlegen, bearbeiten, löschen, sortieren, Bild, Aktiv-Schalter
- [x] Tags: anlegen, bearbeiten, löschen, Hintergrund- und Schriftfarbe, global oder pro Section; globale Tags nur für Admins
- [x] Einstellungen: Expertenmodus, Sortierung (6 Varianten), Artikel pro Seite, alle Template-Felder, drei Bildgrößen mit je eigenem Zuschnitt (Abschnitt 4, „Bilder“), maximale Upload-Größe, Galerie, View, Block 2, Zielverzeichnis der Access-Dateien, Standard-Vorschaubild (Upload oder Kopie des Vorschaubilds eines Artikels der Section), „nur für Admins“
- [x] Wechsel von View oder Galerie lädt deren Vorgaben, Wechsel der Galerie nur nach Warnung
- [x] Import in leere Sections aus `news_img`, `articles` und Topics (Abschnitt 6), im Reiter „Wartung“
- [x] Demo-Packs in leere Sections, mit Allowlist für Settings (`Import::DEMO_SETTINGS`, Werte über `Settings::save()`)

### Frontend

- [x] Liste mit Paging, Filter über `g`, `tags`, `m`/`y`/`method`
- [x] Detailseite über Access-Dateien, Vor/Zurück innerhalb der Gruppe
- [x] Block 2 über `MODULES_BLOCK2`, `NEWS_BLOCK2`, `TOPIC_BLOCK2`; die Core-Templates fragen `MODULES_BLOCK2` ab
- [x] Alle Platzhalter (Abschnitt 3), `{SYSVAR:MEDIA_REL}`, Tag-Farben mit Auto-Kontrast
- [x] „Weiterlesen“ nur bei Langtext oder Galerie
- [x] Bild-Fallback: Artikel, Gruppe, Section-Standardbild, in Listen danach eine Platzhalterkachel mit Initiale (Reihenfolge wie `news_img` v6.0)
- [x] Views `default`, `grid`, `avatar` und eigene Views. `default` im Vergleich mit news_img, `grid` und `avatar` am 2026-11-13 im Browser geprüft (Umschalten über die Einstellungen, Liste, Detailseite, Platzhalterkachel, keine offenen Platzhalter, keine JS-Fehler)
- [x] Galerien Fotorama, Masonry, Lightcase, keine. Am 2026-11-13 im Browser geprüft. Masonry griff nie, weil das Skript vor dem Galerie-HTML lief (schon in news_img v6.0); jetzt erst nach DOM-Ready
- [x] Keywords-Meta aus den Tags
- [x] Sichtbarkeit überall gleich: aktiv, Titel vorhanden, im Zeitraum, Gruppe aktiv oder keine Gruppe

### Anbindung

- [x] RSS mit Filter nach Gruppe, Suche, LinkResolver für TinyMCE und `[articles:NN]`. `ajax_link_items.php` liefert die Artikel. Im Link-Dialog erscheinen sie aber nicht: `tinymce_wbce/plugins/link/plugin.min.js` erkennt in `loadPageItems()` (und beim Vorbelegen eines bestehenden Links) nur `[wblinkNN]`, die Seitenauswahl liefert `[pagelink:NN]`. Core-Fehler, betrifft alle Module; Fix gehört nach `WBCE_CMS` (Stand 2026-11-13)
- [x] Droplets für Artikellisten (Gegenstück zu `fetchNewsItems`) und Slider
- [ ] WBCE Search (Core-Modul `wbce_search`, WBCE 1.7): `search_index.php` nach dem Vorbild von `news_img` liefert einen Eintrag pro sichtbarem Artikel (`Articles::visibleSql`) mit Detailseite und Vorschaubild, ohne sichtbare Artikel einen Eintrag für die Seite. Liegt der Abschnitt außerhalb seines Zeitraums, kommen keine Artikel in den Index (WBCE Search prüft nur die Seite). Abgeglichen mit `wbce_search/SEARCH_INDEX_HOWTO.md` (0.6.1), mit Platzhalter-Klassen geprüft (2026-11-16). `backend.php` meldet beim Kopieren und Verschieben auch den Ziel-Abschnitt als geändert. Der Index bleibt nur durch `touchSection()` frisch; Artikel, die allein durch ihr Datum sichtbar werden oder ablaufen, folgen erst mit der nächsten Änderung oder einem „Full rebuild“.<br>**Zurückgestellt (2026-11-16):** Der echte Test gegen `wbce_search` wartet, bis dessen nötige Core-Änderungen im öffentlichen Repository von WBCE stehen. Dann auch entscheiden, ob `search.php` entfällt: WBCE 1.7 liest sie laut HOWTO nicht mehr

## 3. Verhältnis zu news_img

### Bleibt gleich

So laufen importierte Sections ohne Nacharbeit, und Redakteure müssen nichts neu lernen.

- **Platzhalter:** Syntax und alle 52 Namen, dazu die Bild-Schleife (Liste unten)
- **URL-Parameter** `g`, `p`, `tags`, `m`, `y`, `method`
- **Access-Dateien** mit Verzeichnis aus dem Link der Seite, per Einstellung überschreibbar
- **Views** `default`, `grid`, `avatar` und die Galerien
- **Ordner für eigene Erweiterungen:** `views/<name>/`, `js/galleries/<name>/`, `templates/slides/<tpl>.txt`

Die 52 Platzhalter:

`BACK CONTENT CONTENT_BLOCK2 CONTENT_LONG CONTENT_SHORT CREATED_DATE CREATED_TIME DISPLAY_GROUP DISPLAY_IMAGE DISPLAY_NAME DISPLAY_PREVIOUS_NEXT_LINKS EMAIL GROUP_ID GROUP_IMAGE GROUP_IMAGE_URL GROUP_TITLE IMAGE IMAGE_URL IMAGES LINK HREF AOPEN ACLOSE MODI_DATE MODI_TIME NEXT_LINK NEXT_PAGE_LINK OF OUT_OF PAGE_TITLE POST_ID POST_OR_GROUP_IMAGE PREVIOUS_LINK PREVIOUS_PAGE_LINK PUBLISHED_DATE PUBLISHED_TIME SHORT SHOW_READ_MORE TAGS TAGLIST TEXT_AT TEXT_BACK TEXT_LAST_CHANGED TEXT_NEXT_POST TEXT_O_CLOCK TEXT_ON TEXT_POSTED_BY TEXT_PREV_POST TEXT_READ_MORE TITLE USER_ID USERNAME`

In der Bild-Schleife: `[IMAGE] [DESCRIPTION] [THUMB] [THUMBWIDTH] [THUMBHEIGHT] [WB_URL]`. `POST_ID` heißt weiter so, obwohl es um Artikel geht.

### Neu bzw. anders

| Bereich | news_img | articles |
|---|---|---|
| Tabellen | `mod_news_img_*` | `mod_articles_*` (Abschnitt 5) |
| Medienordner | `.news_img/`, Vorschau-, Gruppen- und Standardbilder gemischt im Wurzelordner | `.articles/items/`, `groups/`, `sections/`, jeweils Original plus abgeleitete Größen |
| Bildgrößen | Vier Größenangaben, ein gemeinsamer Zuschnitt-Schalter, Originale werden überschrieben | Drei Größen mit eigenem Zuschnitt, Originale bleiben, Neuberechnung möglich |
| CSS-Klassen | `mod_nwi_*` | `mod_articles_*` |
| Konstanten in Access-Dateien | `POST_ID`, `POST_SECTION` | `ARTICLE_ID`, `ARTICLE_SECTION` |
| LinkResolver | `[news_img:NN]` | `[articles:NN]`, Klasse `ArticlesLinkResolver` |
| Droplets | `fetchNewsItems`, `nwi_slider` | `articles_list` mit denselben Parametern wie `fetchNewsItems`, `articles_slider?section_id=` |
| PHP-Schnittstelle für Droplets | `mod_nwi_get_news_items()` in `functions.inc.php` | Methoden der Klasse `Articles\Api` |
| Rechteprüfung | in jeder Aktionsdatei einzeln | zentral (Abschnitt 4) |

### Was an news_img hängt und nicht automatisch mitkommt

- **News with Images anywhere** und **Blog Menu for News with Images** (beide im Add-on-Repository). In 1.0 deckt das Listen-Droplet den ersten Fall ab. Für das Blog-Menü bleiben die URL-Parameter kompatibel, damit eine Anpassung später einfach ist.
- **`reservations`** liest Objektbeschreibungen aus `news_img`. Es bekommt eine zweite Quelle `articles:<section_id>`.
- **`simplepagehead`** (Core) kennt `news_img` fest verdrahtet. Für `articles` braucht es einen Pull Request an `WBCE_CMS`: eine Zeile `$module['articles'] = array('mod_articles_items', 'article_id', 'title', 'teaser', '');`. simplepagehead liest die ID als globale Variable; die Access-Dateien von `articles` setzen dafür seit Phase 7 neben `ARTICLE_ID` auch `$article_id`. Am 2026-11-16 in der Portable mit eingetragener Zeile geprüft (Titel und Kurztext erscheinen im `<head>`), danach zurückgesetzt. Ohne den PR zeigt ein Artikel weiter den Seitentitel.

## 4. Architektur

### Grundsatz

Ein WBCE-Modul mit wenigen Einstiegsdateien und klaren Klassen, ohne Composer und ohne CMS-unabhängigen Kern. Access-Dateien, Seitenrechte und Medienordner binden das Modul fest an WBCE, eine Trennung brächte nur Schichten.

```text
articles/
├─ info.php  precheck.php  install.php  install_struct.sql  upgrade.php  uninstall.php
├─ add.php  delete.php  modify.php  view.php  search.php  rss.php  LinkResolver.php
├─ search_index.php       Einträge für WBCE Search (wbce_search), nur von dort gelesen
├─ backend.php            zentraler Backend-Einstieg für Masken und Aktionen
├─ bootstrap.php          Autoloader, Sprachdateien, Twig
├─ src/
│  ├─ Access.php          Rechte: Section, Artikel, Gruppe, Tag
│  ├─ Articles.php        Lesen, Speichern, Kopieren, Verschieben, Löschen, Sichtbarkeit
│  ├─ Groups.php  Tags.php  Settings.php
│  ├─ Schema.php          Tabellen und Indizes anlegen und entfernen
│  ├─ Images.php          Upload, Prüfung, Neukodierung, Thumbnails, Galerie
│  ├─ AccessFiles.php     Pfade, Anlegen, Entfernen, Migration
│  ├─ Placeholders.php    Ersetzen, ohne Datenbank und ohne Globals
│  ├─ Frontend.php        Liste, Detail, Paging, Filter
│  ├─ Api.php             Einstieg für Droplets
│  ├─ Import.php  DemoPacks.php
│  └─ Backend.php         Routing der Masken und Aktionen
├─ twig/                  Backend-Templates
├─ languages/  views/  templates/slides/  js/galleries/  demodata/  droplets/  docs/
└─ backend.css, backend_body.js, Uploader
```

Der Namespace ist `Articles\`, der Autoloader steht wie bei `reservations` in `bootstrap.php`. Eine `functions.inc.php` gibt es nicht.

### Backend-Ablauf

`backend.php` ist der einzige Backend-Einstieg neben `modify.php`. Jeder Aufruf läuft durch denselben Weg:

1. `modules/admin.php` prüft die Seitenrechte.
2. Die Section muss zur Seite gehören und `articles` sein.
3. Jede Aktion nennt ihren Zieltyp. `Access` löst das Objekt auf dessen Seite auf und prüft sie. Bei Massenaktionen gilt das für jede ID, bei Kopieren und Verschieben auch für das Ziel.
4. Schreibende Aktionen laufen nur per POST mit `checkFTAN()`, danach folgt ein Redirect (PRG).

Damit gibt es keine Statusänderung per GET und kein IDKEY. Toggle, Löschen und Pfeile sind Formular-Buttons. Drag & Drop schickt die neue Reihenfolge als normales Formular ab, ebenfalls mit Redirect. Der Galerie-Upload (`gallery_upload`) nutzt denselben Einstieg und antwortet mit JSON. Vorschau-, Gruppen- und Standardbild gehen mit dem jeweiligen Formular mit. Ein abgelehntes Bild verhindert nicht, dass der Rest des Formulars gespeichert wird; die Meldung erscheint neben „Gespeichert“.

Umgesetzt in Phase 4: `Access` (Rechte), `Backend` (Masken, Texte, Flash-Meldungen), `Groups`, `Tags`, `Settings::save()` und die Schreibmethoden in `Articles`. Die Masken liegen in `twig/`, Verhalten und Optik in `assets/backend_body.js` und `assets/backend.css`; WBCE bindet beide selbst ein.

Das löst die Hauptursache der Sicherheitsfixes in `news_img`: Prüfungen stehen nicht mehr in jeder Datei einzeln.

### Datenbankzugriff

Nur Canonical-Methoden mit Parameterbindung: `fetchAll`, `fetchRow`, `fetchValue`, `insertRow`, `upsertRow`, `transaction`, `addField`, `addIndex`, `lastInsertId`. Kein String-Escaping, kein `sprintf`-SQL. Identifier wie die Sortierspalte kommen aus einer festen Map.

Das SQL läuft auf MySQL und SQLite: kein `INSERT IGNORE`, `SHOW TABLES`, `SHOW COLUMNS`, `DELETE … LIMIT` oder `ALTER … AFTER`. Zahlen werden beim Lesen auf `int` gecastet, weil PDO unter 1.7 echte Integer liefert.

Mehrschrittige Aktionen wie Kopieren, Verschieben und Import laufen in `transaction()`. Dateioperationen kommen danach.

### Sichtbarkeit und Access-Dateien

Eine einzige Bedingung in `Articles::visibleSql()` gilt für Liste, Zählung, Detail, Vor/Zurück, RSS, Suche und Droplets.

`AccessFiles::sync($article)` entscheidet, ob eine Datei existieren soll, und wird nach jedem Schreibvorgang aufgerufen. Das Frontend legt nur fehlende Dateien an, damit zeitgesteuerte Artikel ohne Cron erscheinen.

`AccessFiles` überschreibt keine Datei, die nicht von `articles` stammt. Das ist nötig, weil `news_img` und `articles` auf derselben Seite dasselbe Verzeichnis ableiten. Erkannt wird die Herkunft an der Konstante `ARTICLE_ID` in der Datei.

### Rendering

`Placeholders::render(string $template, array $values): string` ist eine reine Funktion, die unbekannte Platzhalter leert. `Frontend` baut die Werte pro Artikel einmal und lädt Tags, Gruppen und Autoren gebündelt. So bleiben Liste und Detail ohne N+1-Abfragen.

Ausgabe-Escaping nach Art des Werts:

- **HTML-Felder** (`CONTENT_*`, `IMAGE`, `TAGS`, Links als fertiges Markup): roh.
- **Reine Textwerte** (`TITLE`, `GROUP_TITLE`, `USERNAME`, Bildtexte): `htmlspecialchars()` mit `double_encode = false`, damit importierte Entities nicht doppelt kodiert werden.
- **URLs:** Parameter mit `http_build_query()`.

Das Frontend-CSS kommt über `I::insertCssFile()` statt über `<link>` im Inhalt.

### Backend-Oberfläche

Twig über `getTwig()` mit `setDefaultStrategy('html')`, wie in `reservations`. Die Bedienung mit Reitern bleibt als Ausgangspunkt, vor dem Umsetzen kommt aber ein Mockup. Das Backend-JS wird Vanilla und kommt ohne Inline-Handler aus.

### Abhängigkeiten

| In news_img | In articles |
|---|---|
| xdsoft- und nehakadam-Datepicker | Native Felder `datetime-local`, ohne Bibliothek (flatpickr aus dem Core wäre die Alternative) |
| Spectrum | Core: `include/wbeColoris` |
| Font Awesome | Core: `include/font-awesome` |
| Eigene GD-Routine | Core: `claviska\SimpleImage`, Upload-Prüfung im Modul |
| `jquery.slugit.js` | Einige Zeilen Vanilla JS |
| jQuery-UI-Drag-&-Drop, `XBTooltip` | Vanilla JS |
| dm-uploader | Uploader in `assets/backend_body.js`, nach dem Vorbild von `nwi-uploader.js` aus `v6.0` |
| Fotorama, Masonry, Lightcase | Bleiben, nur Laufzeitdateien. Lightcase ohne `scss`, `bower.json`, `package.json` und Changelog; `vendor/jQuery/jquery.events.touch.js` wird noch gebraucht |

Sprachen liegen in `languages/XX.php` mit dem Namespace `MOD_ARTICLES` und werden über `Lang::loadLanguage(__DIR__)` geladen. Die `TEXT_*`-Platzhalter kommen von dort.

### Bilder

**Drei Größen pro Section.** Jede hat Breite, Höhe und einen eigenen Zuschnitt-Schalter. Zugeschnitten wird mittig auf beiden Achsen. Die Zuordnung zu den Bildarten ist fest, es gibt keinen Sonderfall-Code pro Bildart.

| Größe | Verwendet für | Platzhalter |
|---|---|---|
| Vorschau | Vorschaubild des Artikels, Gruppenbild, Standardbild der Section | `[IMAGE]`, `[GROUP_IMAGE]`, `[POST_OR_GROUP_IMAGE]` |
| Thumbnail | Galerie-Vorschaubilder | `[THUMB]` in der Bild-Schleife |
| Großansicht | Galeriebilder | `[IMAGE]` in der Bild-Schleife |

`[IMAGE]` zeigt wie in `news_img` auch auf der Detailseite die Vorschau-Größe. Eine große Variante wie `[IMAGE_LARGE]` wäre ein neues Feature und gehört nicht in 1.0.

**Original plus abgeleitete Dateien.** Jedes Upload wird als Original gespeichert. Die lange Kante wird dabei auf einen festen Wert begrenzt (2560 px, keine Einstellung). Daraus entstehen die benötigten Größen in Unterordnern. Ändert sich eine Größe, berechnet der Button „Bildgrößen neu berechnen“ alle Bilder der Section aus ihren Originalen neu. Hochskaliert wird nie. Der Platzbedarf steigt dadurch etwa auf das Doppelte.

**Feste Ordner pro Besitzer:**

```text
.articles/
├─ items/<article_id>/      Vorschaubild und Galerie (Originale)
│   ├─ preview/  thumb/  large/
├─ groups/<group_id>/       Gruppenbild + preview/
└─ sections/<section_id>/   Standardbild + preview/
```

Der Artikelordner hängt nicht an der Section, deshalb muss beim Verschieben eines Artikels nichts umziehen. Beim Kopieren wird der ganze Ordner kopiert, sodass Kopien kein Bild mehr teilen. Gruppen- und Standardbild stehen mit ihrem Dateinamen in der Datenbank, statt über Endungen gesucht zu werden.

**Einstellungen an einer Stelle.** Ein Block „Bildgrößen“ im normalen Modus: drei Zeilen mit Breite, Höhe und Zuschnitt, dazu der Hinweis, welche Bilder die Größe nutzen, und die maximale Upload-Größe. Der Expertenmodus versteckt keine Bildeinstellungen mehr. Vor dem Umsetzen kommt ein Mockup.

**Upload.** Das Prüfrezept aus `news_img` 2026 gilt für alle Bildarten über `Images::upload()` und `Images::store()`:

1. Endungs-Whitelist für jpg, jpeg, gif, png, webp
2. `getimagesize()`-Typ muss zur Endung passen, und die Datei muss sich dekodieren lassen (`GIF89a<?php …` besteht `getimagesize()`)
3. Größenlimit, nur echte Uploads (`is_uploaded_file()`)
4. Immer neu kodieren, auch das Original (Qualität 85, EXIF-Drehung wird angewendet)
5. Fehler löschen die Datei wieder

Umgesetzt in Phase 5 mit `claviska\SimpleImage` aus dem Core. Zuschneiden nimmt die Mitte und vergrößert nie: Ist das Bild kleiner als die Box, wird die größte Fläche im Seitenverhältnis der Box ausgeschnitten (`Images::cropBox()`). „Bildgrößen neu berechnen“ läuft in einem Request, etwa 0,1 bis 0,2 Sekunden pro Bild und Größe; bei mehreren tausend Bildern wäre eine Aufteilung in Etappen nötig.

In `news_img` hat jeder Bildtyp eigenen Code, und Gruppenbilder werden nur anhand der Endung geprüft.

## 5. Datenbank

Eigene Tabellen mit klaren Namen und Typen:

| Tabelle | Inhalt |
|---|---|
| `mod_articles_settings` | Pro Section: Templates als `tpl_list_header`, `tpl_list_item`, `tpl_list_footer`, `tpl_detail_header`, `tpl_detail_content`, `tpl_detail_footer`, `tpl_image_loop`, `tpl_block2`, Sortierung, Seitengröße, die drei Bildgrößen als `preview_*`, `thumb_*`, `large_*` mit je Breite, Höhe und Zuschnitt, maximale Upload-Größe, Ja/Nein-Felder als `TINYINT`, View, Galerie, Zielverzeichnis, Standardbild, Expertenmodus |
| `mod_articles_items` | `article_id`, `section_id`, `group_id`, `position`, `active`, `title`, `link`, `image`, `teaser`, `content`, `block2`, `published_from`, `published_until`, `created_at`, `created_by`, `modified_at`, `modified_by` |
| `mod_articles_groups` | `group_id`, `section_id`, `position`, `active`, `title`, `image` |
| `mod_articles_images` | `image_id`, `article_id`, `file`, `caption`, `position` |
| `mod_articles_tags` | `tag_id`, `title`, `color`, `text_color` |
| `mod_articles_tag_sections` | `tag_id`, `section_id` (0 = global) |
| `mod_articles_item_tags` | `article_id`, `tag_id` |
| `mod_articles_import` | Herkunft importierter Artikel: Quellmodul, Quell-ID, Ziel-ID |

Indizes auf `section_id`, `group_id` und `article_id` überall dort, wo danach gefiltert wird. Sie entstehen per `addIndex()` in `Schema::install()`, weil `importSql()` Inline-`KEY`-Klauseln unter SQLite entfernt.

Getrennte Spalten für Erstellung und Änderung lösen zwei Altlasten aus `news_img`:
- `CREATED_DATE` kam dort aus dem Änderungsdatum der Access-Datei und ging bei Backups verloren.
- `posted_when` wurde bei jedem Speichern überschrieben.

`install_struct.sql` läuft über `importSql()`. Dummy-Zeilen und `{TP}search`-Einträge gibt es nicht, die Suche läuft über `search.php`. Geprüft in Phase 3: `search/search.php` liest `{TP}search` nur für Module ohne eigene Suchfunktion.

`precheck.php` verlangt WBCE ≥ 1.7.0 und PHP ≥ 8.2, wie `reservations`. `upgrade.php` ist nur für spätere eigene Versionen da.

## 6. Import

Der Import füllt eine leere `articles`-Section aus einer anderen Section. Die Quelle bleibt unverändert.

### Aus news_img

| Quelle | Ziel |
|---|---|
| Section-Settings | Überschreiben die Einstellungen der Ziel-Section. Templates mit `mod_nwi_` → `mod_articles_`, View und Galerie übernommen. Unbekannte Views, etwa `faq` aus 5.0.x, werden zu `default`, unbekannte Galerien zu `fotorama`, jeweils mit Hinweis im Bericht. `mode = advanced` wird zum Expertenmodus, `posts_dir` zum Zielverzeichnis |
| Bildgrößen | `resize_preview` → Vorschau, `imgthumbsize` → Thumbnail, `imgmaxwidth`/`imgmaxheight` → Großansicht. `crop_preview` landet auf allen drei Zuschnitt-Schaltern, damit das Ergebnis gleich aussieht |
| Gruppen | Mit Bild, Aktiv-Status und Reihenfolge |
| Tags | Die lokalen Tags der Quell-Section und alle Tags ihrer Beiträge. Lokale werden pro Section neu angelegt. Globale Tags werden einmal angelegt und über ihren Namen wiederverwendet |
| Beiträge | Alle, auch inaktive. `created_at` aus dem Dateidatum der Access-Datei, sonst aus `posted_when`. `modified_at` aus `posted_when`, Autor aus `posted_by`. Der Link entsteht neu aus Zielverzeichnis, Slug der Quelle und neuer ID |
| Bilder | Vorschau-, Galerie-, Gruppen- und Standardbilder werden kopiert, nicht verschoben. Originale gibt es in `news_img` nicht mehr, die bereits verkleinerten Dateien gelten daher als Original und zugleich als abgeleitete Größe: Vorschau nach `items/<id>/` und `preview/`, Galeriebild nach `items/<id>/` und `large/`, Galerie-Thumbnail nach `thumb/`. So sieht die importierte Section aus wie die Quelle, ohne neu zu kodieren. Heißt das Vorschaubild wie ein Galeriebild, bekommt es eine Nummer. Fehlende Dateien stehen im Bericht |
| Zuordnung | Jede Paarung landet in `mod_articles_import` |

Danach legt `AccessFiles` die Dateien für die sichtbaren neuen Artikel an. Die Datenbank wird in einer Transaktion geschrieben, die Dateien danach. Links in Inhalten auf `.news_img/` bleiben gültig, solange `news_img` mit seinen Medien installiert ist.

### Weitere Quellen

- Andere `articles`-Sections (`Import::fromArticles`, Phase 6): Einstellungen, Gruppen mit Bild, alle Artikel mit Status, Reihenfolge, Datumsangaben und Galerie, Standardbild. Nicht übernommen wird das Zielverzeichnis, die Links entstehen unter der Zielseite neu. Lokale Tags werden über ihren Titel im Ziel angelegt oder genutzt, globale bleiben. Zuordnung in `mod_articles_import` mit `source_module = 'articles'`
- Topics (`Import::fromTopics`, Phase 7, geprüft mit Topics 1.2.3): auch umbenannte Kopien, erkannt an `{TP}mod_<modul>` mit `topic_id` und der Tabelle `_settings`. Übernommen werden Seitengröße und Sortierung (Position, Datum; andere mit Hinweis zur benutzerdefinierten Reihenfolge), alle Topics mit Texten, Zeitraum und Autor, `content_extra` als Block 2. Aktiv sind die Stufen 4 bis 6, die Stufen 1 bis 3 werden mit Hinweis inaktiv. Bild und Zusatzbilder (Tabelle `_addpics`, sonst Ordner `topic<id>/` wie in Topics) laufen wie bei den Demo-Packs durch `Images::store()`, aus `zoom/`, falls vorhanden. Der Standardlink `topicNN` wird durch den Titel ersetzt. Anders als in `news_img` bleiben die Templates von `articles`: Die von Topics brauchen dessen CSS und eigene Platzhalter wie `{SEE_ALSO}` oder die Kommentare. Nicht übernommen: Kommentare (Anzahl als Hinweis), Schlüsselwörter, Beschreibungen, Gruppen, „Siehe auch“
- Kein Import aus News 3.x (gestrichen am 2026-11-13)
- Demo-Packs (`Import::demo`, Phase 6): „Restaurant“ und „Stadtverwaltung“ aus `news_img` v6.0, in `wbce/demodata/<paket>/` mit `data.php`, `meta.json` und `images/<slug>/`, Felder auf die Namen von articles umgestellt. Einstellungen nur aus `Import::DEMO_SETTINGS` und über `Settings::save()` geprüft. Tags entstehen lokal, ein vorhandener Tag gleichen Titels wird genutzt. Bilder laufen durch `Images::store()` (Prüfung, Neukodierung, alle Größen). Kein gemeinsamer Rollback: Bricht ein Paket mittendrin ab, bleibt ein teilweise gefüllter Abschnitt, den Löschen bereinigt. Die `copyright.csv` der Bilder liegt bei, wird aber noch nicht ausgewertet (geplantes Feature Bildquellen)

Ein Abschlussbericht nennt Anzahl und Hinweise, etwa abweichende Views oder fehlende Bilddateien.

### Bestehende Links

`[news_img:NN]`-Links und alte Beitrags-URLs funktionieren nur, solange `news_img` und die Quell-Section bestehen. Ein eigener Schritt „Umstellen“ nach Version 1.0 greift beides auf: Links umschreiben und Weiterleitungen von den alten URLs anlegen. 1.0 legt dafür die Zuordnung in `mod_articles_import` an.

## 7. Fehler aus news_img, die articles nicht übernimmt

Geprüft gegen `v6.0`:

- `published_when === '0'` greift unter PDO nie, Beiträge ohne Startdatum zeigen 1970.
- `lang_filter` und `group_id_type=page_id` im Droplet nutzen die entfernte Spalte `page_id`.
- `max_news_length` nutzt das undefinierte `$row` und ruft das nicht vorhandene `nia_truncate()`.
- `custom_placeholder` wird angenommen, aber nie ausgewertet.
- `include/jscalendar` und `framework/class.frontend.php` gibt es in 1.7 nicht mehr.
- `$tpl` des Slider-Droplets geht ungeprüft in einen `include`.
- Kopierte Beiträge teilen sich die Vorschaubild-Datei, weil Vorschaubilder im Wurzelordner liegen.
- `crop_preview` gilt für alle Bildarten und schneidet bei aktivem Schalter auch die großen Galeriebilder zu. Er steht im Expertenmodus, wirkt aber auf die Vorschaugröße aus dem normalen Modus.
- Bilder werden beim Verkleinern überschrieben. Größenänderungen wirken deshalb nur auf neue Uploads.
- Beim Zuschnitt hochkanter Bilder wird der obere Teil genommen statt der Mitte.
- Beim Löschen einer Section bleiben Tags zurück (TODO in `delete.php`).
- `mod_news_img_posts_img` wird beschrieben, aber nie gelesen.

## 8. Was es in articles nicht mehr gibt

- BlackCat-Zweige, `config/bc_config.php`, `headers.inc.php`
- `functions.inc.php` mit `mod_nwi_*`-Funktionen, `droplets.functions.php`, `droplet_getNewsItems.zip`
- `readme.html` zugunsten von `docs/`
- IDKEY, `section_key`, Statusänderungen per GET
- Die Droplet-Option `custom_placeholder`
- Toter Code wie der Galerie-Zweig in `mod_nwi_img_upload()` und das Feld `foto[]`

## 9. Umsetzung

Entwickelt wird in einem eigenen Repository, eingebunden per Junction in die Portable. Wegen der Junction nutzen Einstiegsdateien `dirname($_SERVER['SCRIPT_FILENAME'], 3)` für `config.php`, wie `reservations`.

| Phase | Inhalt | Fertig, wenn |
|---|---|---|
| 0 | Repository anlegen, Testdaten in der Portable-`news_img` anlegen, Referenzausgaben sichern | Vergleichsdaten liegen vor |
| 1 | Grundgerüst, Schema, Install, Uninstall, `precheck.php` | Installation auf 1.7 läuft |
| 2 | Import aus `news_img` | Testdaten sind vollständig übernommen |
| 3 | Lesepfad: Frontend, Platzhalter, Access-Dateien, RSS, Suche, Droplets, LinkResolver | Importierte Section rendert wie die Quelle |
| 4 | Mockup, danach Backend: Artikel, Gruppen, Tags, Einstellungen, Massenaktionen | Backend-Checkliste erfüllt |
| 5 | Bilder: Upload, abgeleitete Größen, Neuberechnung, Galerie, Standardbild, Wartungsaktionen | Bild-Checkliste erfüllt |
| 6 | Weitere Importe und Demo-Packs | Import aus `articles` und Topics |
| 7 | Tests, Doku, Anbindung `reservations`, Pull Request für `simplepagehead` | Abnahme nach Abschnitt 2 |

Der Import kommt vor dem Lesepfad. So liefert er die Daten, an denen sich der Lesepfad direkt gegen `news_img` vergleichen lässt.

## 10. Tests

- **Vergleich mit der Quelle:** Die importierte Section muss dasselbe HTML liefern wie die `news_img`-Quelle, bis auf die umbenannten CSS-Klassen. Das gilt für Liste, Detail, RSS und Droplet. Jede Abweichung wird begründet. `tests/reference/compare.php` ruft dieselben Ansichten der `articles`-Section ab, rechnet URLs, IDs und Bildordner um und legt beide Seiten zeilenweise für `diff` ab.
- **Abweichungen von 5.0.32** (Stand Phase 3), alle gewollt:
  - Tags bekommen ohne eigene Schriftfarbe eine Kontrastfarbe (v6.0).
  - Artikel ohne Langtext und Galerie verlinken trotzdem auf ihre Detailseite; der `#`-Link mit `onclick` entfällt. `[SHOW_READ_MORE]` blendet „Weiterlesen“ weiter aus, `[HREF]`, `[AOPEN]` und `[ACLOSE]` bleiben dann leer (v6.0).
  - Ohne Vorschaubild folgt das Gruppenbild, dann das Standardbild der Section, in Listen zuletzt eine Kachel mit Initiale, auf der Detailseite nichts. `nopic.png` gibt es nicht mehr (v6.0).
  - Eine inaktive Gruppe versteckt ihre Artikel überall, auch in Zählung, Vor/Zurück und RSS (Variante A).
  - Ungültige Parameter wie `?g=-1` werden ignoriert, die Liste erscheint ungefiltert (v6.0). 5.0.32 zeigt „Nichts gefunden“.
  - Titel, Gruppen- und Benutzernamen werden escaped; nach dem Dekodieren der Entities ist der Text gleich.
  - `[CREATED_DATE]` und `[CREATED_TIME]` sind gefüllt (in `news_img` immer leer), `[PUBLISHED_DATE]` ohne Startdatum zeigt das Erstelldatum statt 1970.
  - Droplet-Links übernehmen kein `?g=` aus der aufrufenden Seite, `aslist` nutzt die Klassen `mod_articles_list` und `mod_articles_list_item`.
  - RSS löst `[wblinkNN]` und `[schlüssel:NN]` über den Core-`LinkResolver` auf und antwortet bei unpassender Seite, Section oder Gruppe mit 404.
- **Unit-Tests:** PHPUnit (`phpunit.xml.dist`, vorerst mit dem PHPUnit 11 aus `reservations/core/vendor`) für `Placeholders`, Sichtbarkeit, Slug und Link, Bildgrößen, Upload-Validierung, Tag-Kontrast, die Abbildung im Import.
- **Rechte:** Für jede Aktion ein Test mit fremder Objekt-ID. Umgesetzt auf Ebene von `Access` in `tests/integration/access_rights.php` (Artikel, Gruppe, Tag, Abschnitt, Ziele, „nur für Admins“; dazu Speichern, Kopieren und Löschen). Die Prüfung, dass `backend.php` vor jeder Aktion `Access` fragt, läuft bisher nur von Hand im Browser. Verschieben läuft mit, wenn ein zweiter articles-Abschnitt derselben Seite als viertes Argument angegeben ist (am 2026-11-16 mit einem vorübergehenden Abschnitt geprüft).
- **Import im Frontend:** Am 2026-11-16 mit einem vorübergehenden Abschnitt auf der Testseite geprüft. Nach dem Import aus Abschnitt 8 sind Liste und die Detailseiten aller 8 sichtbaren Artikel identisch mit der Quelle (IDs und Bildordner herausgerechnet); das Demo-Paket „Restaurant“ zeigt Liste, Bilder, Tags und Galerie ohne offene Platzhalter oder JS-Fehler.
- **Droplets:** `tests/integration/droplet_visibility.php` prüft, dass Listen und Slider nur öffentliche oder versteckte Seiten und Abschnitte im Zeitraum zeigen.
- **Matrix:** PHP 8.3 und 8.5 mit `error_reporting=E_ALL`, jeweils MariaDB. Dazu SQLite: `pdo_sqlite` ist in PHP 8.5 aktiv und lässt sich in der Portable-PHP mit `-d extension=pdo_sqlite` laden.

## 11. Entscheidungen

Getroffen am 2026-11-11.

| Frage | Entscheidung |
|---|---|
| Frontend-Templates mit `[PLATZHALTER]` oder mit Twig? | Platzhalter. Der Import übernimmt Templates ohne Umbau, und von Redakteuren gepflegte Twig-Templates bräuchten eine Sandbox. |
| Platzhalter über den `LayoutParser` des Cores (`framework/LayoutParser.php`, ab 1.7.0)? (2026-11-12) | Nein, für 1.0 bleibt `Placeholders::render()`. Der `LayoutParser` escapt jeden Wert, solange im Template kein `\|raw` steht; importierte Templates mit `[CONTENT_LONG]`, `[IMAGE]` oder `[TAGS]` müssten dafür umgeschrieben werden. Er escapt außerdem mit `double_encode`, sodass Entities in importierten Titeln doppelt kodiert würden. In Templates, die Redakteure im Expertenmodus bearbeiten, gäbe `[_CONTEXT\|dump]` alle Variablen aus, und `{include}` ist nur ohne gesetzten Partials-Pfad abgeschaltet. Denkbar ist er später für eine eigene Template-Generation mit `{if}` und `{foreach}`, dann mit `\|raw` an den richtigen Stellen und gesperrtem `dump` und `include`.<br>**Nachtrag 2026-11-16:** Eine neue Fassung des `LayoutParser` (in der Portable, noch in keinem Branch von `WBCE_CMS` bis zum Fetch vom 2026-11-12) behebt alle drei Einwände: `setRawKeys()`, `setDoubleEncode(false)` und `setSandbox()` (kein `dump`, kein `include`); Werte werden nie als Template ausgewertet. Mit `setRawKeys(Placeholders::NAMES)`, `setDoubleEncode(false)` und `setSandbox()` ergeben alle 32 mitgelieferten Templates (3 Views, 4 Galerien) dieselbe Ausgabe wie `Placeholders::render()`. Einziger Unterschied: Unbekannte Platzhalter in Großbuchstaben (etwa `[NEU]` als Text im Template) werden geleert statt stehen gelassen. Etwa 0,02 statt 0,005 ms pro Listeneintrag. Für 1.0 bleibt es trotzdem bei `Placeholders`, weil die Fassung noch nicht veröffentlicht ist und `articles` sonst eine bestimmte WBCE-Version voraussetzen müsste. Kandidat für die Zeit nach 1.0 (Abschnitt 12, Darstellungsvarianten), sobald sie in einer WBCE-Version enthalten ist. |
| CSS-Klassen beim Import umschreiben? | Ja, `mod_nwi_` → `mod_articles_`. Eigenes CSS außerhalb des Moduls muss dann angepasst werden, der Bericht weist darauf hin. |
| „Umstellen“ (Links umschreiben, Weiterleitungen) in 1.0? | Nein. 1.0 speichert die Zuordnung, das Umstellen folgt als eigener Schritt. |
| Droplet-Namen | `articles_list` und `articles_slider` |
| Repository und Ort des Konzepts | Eigenes Repository `articles`, dieses Konzept zieht dorthin um |
| Auslieferung | Zunächst über das Add-on-Repository, nicht als Core-Modul |
| Slider-Dateien aus `root/wbce` | Werden übernommen, ohne `bs5carousel - Kopie.txt` |

## 12. Nach 1.0

Featurewünsche, eingegangen am 2026-11-13. Sie gehören nicht in 1.0.

| Wunsch | Stand und Ansatz |
|---|---|
| Beitragsbild in mindestens zwei Größen: klein für die Übersicht, groß für die Detailseite | Seit Phase 5 liegt jedes Vorschaubild als Original vor. Eine vierte Größe (etwa `[IMAGE_LARGE]`, siehe Abschnitt 4 „Bilder“) braucht nur eine weitere Zeile im Block „Bildgrößen“ und einen Unterordner. „Bildgrößen neu berechnen“ erzeugt sie dann für alle vorhandenen Bilder. |
| Weitere Größen oder Seitenverhältnisse für verschiedene Ausgabegeräte | Baut auf dem vorigen Punkt auf: mehrere abgeleitete Größen, ausgegeben als `srcset` oder `<picture>`. Offen ist, ob die Größen fest sind oder pro Section frei angelegt werden. Davon hängen die Settings-Spalten ab. |
| Darstellungsvarianten pro Beitrag: mit oder ohne Bild in Übersicht und Detail, Beitrag hervorheben | Braucht neue Felder am Artikel (z. B. `show_image_list`, `show_image_detail`, `featured`) und Platzhalter oder CSS-Klassen dafür, etwa `[FEATURED_CLASS]`. Bedingungen im Template (`{if}`) gingen mit einer eigenen Template-Generation, siehe die Entscheidung zum `LayoutParser` in Abschnitt 11. |
| Beitrags-Link (Slug) unabhängig von der Überschrift | Seit Phase 4 umgesetzt: Die Adresse folgt nur bei neuen Artikeln dem Titel. Bei bestehenden ist sie gesperrt und ändert sich nur nach „Adresse ändern“. |
| „AnyArticles“-Droplet wie anynews für die Ausgabe auf anderen Seiten | Teilweise da: `[[articles_list?…]]` versteht die Parameter von `fetchNewsItems` (Section, Gruppe, Tags, Anzahl, Sortierung, View, `aslist`). Vor dem Umsetzen mit anynews und „News with Images anywhere“ vergleichen (Abschnitt 3), was fehlt, etwa eigene Ausgabe-Templates pro Aufruf. |
