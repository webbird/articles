# articles – View „grid"

Karten-Ansicht des Moduls `articles`. Die **Artikelliste** wird als
responsives Karten-Raster ausgegeben; die **Leseansicht** eines einzelnen
Artikels entspricht der default-View.

## Dateien

| Datei          | Zweck                                                                 |
|----------------|-----------------------------------------------------------------------|
| `templates.php`   | Default-Templates (Grid-Wrapper, Karten-Loop, Header/Footer, Post-Header/-Content/-Footer). |
| `frontend.css` | Optik der Ausgabe. Wird im Frontend automatisch eingebunden.          |

> **Wichtig:** `templates.php` liefert nur die **Vorlagen**. Beim Anlegen eines
> Abschnitts und beim Wechsel der View werden sie in die Abschnittseinstellungen
> kopiert, gerendert wird danach immer von dort. Änderungen an `templates.php`
> wirken deshalb erst, wenn du in den Einstellungen des Abschnitts
> **Templates neu laden** anhakst und speicherst. Eigene Änderungen an den
> Templates dieses Abschnitts gehen dabei verloren. Die `frontend.css` wirkt
> dagegen sofort (ggf. Browser-Cache mit Strg+F5 leeren).

## Optik / Designkonzept

Gleiche zurückhaltende **Navy-Palette** wie die default-View, nur als
Karten-Raster statt als Liste. Jede View lädt ausschließlich ihre eigene
`frontend.css` – die grid-Regeln sind daher auf `.mod_articles_grid` gescoped und
bewusst eigenständig (kein Erben von `.mod_articles_default`).

### Farbpalette

| Verwendung            | Hex        |
|-----------------------|------------|
| Akzent / Tag 1        | `#314B68`  |
| Tag 2                 | `#49627E`  |
| Tag 3                 | `#6F839A`  |
| Tag (weitere)         | `#9BA9B9`  |
| Karte/Box Hintergrund | `#fff` / `#f3f5f8` (Lead-Box) |
| Rahmen                | `#e2e8f0`  |

### Raster

- Spaltenzahl über die Wrapper-Klasse `mod_articles_grid_2columns` …
  `mod_articles_grid_5columns` (im `$header` der `templates.php` gesetzt).
- **Responsive:** unter 600px Breite immer einspaltig, unabhängig von der
  gewählten Spaltenzahl.

### Komponenten

- **Karte** (`section.mod_articles_grid_box`)
  Weißer Hintergrund, dezenter Rahmen, abgerundet, Bild randlos oben.
  Als Flex-Spalte aufgebaut → Karten einer Reihe sind gleich hoch, der
  „Weiterlesen"-Button sitzt per `margin-top: auto` immer am unteren Rand.

- **Bildbereich / Header** (`.mod_articles_teaserpic`)
  Feste `aspect-ratio: 3/2` mit `object-fit: cover`. Dadurch hat der Header
  **mit Bild und mit Platzhalter-Kachel die gleiche Höhe** – Artikel ohne
  Bild bekommen keinen schmaleren Kopf.

- **Textbereich** (`.mod_articles_teasertext`)
  Titel, Metadaten, Teaser. Unten der gestapelte Bereich (`.mod_articles_bottom`):
  **erst die Tags, darunter in eigener Zeile der „Weiterlesen"-Button**
  (linksbündig) – passt besser in schmale Kacheln als nebeneinander.

- **Tags** (`.mod_articles_tag`)
  Kompakte Navy-Pills, die ersten drei abgestuft, weitere `#9BA9B9`.
  Der Abstand `margin-top` greift nur in der **Detailansicht** (Abstand zur
  Vor/Zurück-Navigation); in der Listen-Kachel ist er via
  `.mod_articles_bottom .mod_articles_tags` auf 0 gesetzt.

- **„Weiterlesen"-Button** (`.mod_articles_readmore a`)
  Schlanker Outline-Button im Tag-Stil (Navy, `border-radius: 3px`), Pfeil per
  CSS (`::after { content: "\2192" }`), Hover füllt sich mit Navy.

- **Leseansicht – Teaser-Box** (`.mod_articles_content_short`)
  Identisch zur default-View: heller, kühler Lead-Block (`#f3f5f8`, dezenter
  Rahmen, abgerundet) mit Lead-Typografie; Artikelbild einheitlich gerahmt
  (`aspect-ratio: 4/3`, `object-fit: cover`).

- **Navigation** (`.mod_articles_nav`)
  Vor/Zurück/Übersicht bzw. Paginierung als neutrale Flex-`<nav>` mit drei
  gleich breiten Zonen – die Mitte bleibt zentriert, auch wenn eine Seite
  leer ist. Richtungspfeile (← / →) per CSS an Prev/Next-Links.

### Fehlende Artikelbilder

Ist weder ein Artikel-, Gruppen- noch ein Standardbild des Abschnitts gesetzt, wird
**kein** Platzhalter-Pixel mehr skaliert:

- **Leseansicht:** gar kein Bild – der Teasertext nutzt die volle Breite.
- **Liste (Karten):** eine schlichte **Platzhalter-Kachel** mit der
  Titel-Initiale auf Navy (`.mod_articles_noimage`), die den Bildbereich der Karte
  voll ausfüllt – das Raster bleibt optisch gleichmäßig.

Die Unterscheidung erfolgt in `Articles\Frontend` je nach Liste oder Detailseite
(in der Leseansicht gesetzt, in der Liste nicht). Die Kachel-Optik liegt inline
am Element, damit sie in allen Views ohne zusätzliches CSS einheitlich aussieht;
grid passt sie zusätzlich an den Kartenkopf an (`min-height: 0`, kein
Eck-Radius, größere Initiale).

## Anpassen

- **Spaltenzahl:** Wrapper-Klasse im `$header` der `templates.php` bzw. in den
  Abschnittseinstellungen.
- **Bildproportion des Headers:** `aspect-ratio` bei `.mod_articles_teaserpic`
  (Standard `3 / 2`).
- **Akzentfarbe ändern:** `#314B68` in `frontend.css` (Tag 1, Button) und ggf.
  die Inline-Farbe der Platzhalter-Kachel in `Articles\Frontend`.
- **Eigene Optik ohne Core-Änderung:** am besten eine **eigene View** anlegen
  (Verzeichnis unter `views/` kopieren) und in den Abschnittseinstellungen auswählen,
  statt diese Dateien zu überschreiben – so bleiben Updates problemlos.
