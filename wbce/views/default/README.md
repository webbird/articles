# articles – View „default"

Die Standard-Ansicht des Moduls `articles`. Sie rendert sowohl die
**Artikelliste** (Übersicht eines Abschnitts) als auch die **Leseansicht**
eines einzelnen Artikels.

## Dateien

| Datei          | Zweck                                                                 |
|----------------|-----------------------------------------------------------------------|
| `templates.php`   | Default-Templates (Listen-Loop, Header/Footer, Post-Header/-Content/-Footer). |
| `frontend.css` | Optik der Ausgabe. Wird im Frontend automatisch eingebunden.          |

> **Wichtig:** `templates.php` liefert nur die **Vorlagen**. Beim Anlegen eines
> Abschnitts und beim Wechsel der View werden sie in die Abschnittseinstellungen
> kopiert, gerendert wird danach immer von dort. Änderungen an `templates.php`
> wirken deshalb erst, wenn du in den Einstellungen des Abschnitts
> **Templates neu laden** anhakst und speicherst. Eigene Änderungen an den
> Templates dieses Abschnitts gehen dabei verloren. Die `frontend.css` wirkt
> dagegen sofort (ggf. Browser-Cache mit Strg+F5 leeren).

## Optik / Designkonzept

Bewusst zurückhaltend gehalten, damit sich die Ausgabe in das umgebende
Seiten-Template einfügt. Akzente kommen aus einer einheitlichen **Navy-Palette**,
die auch die Tags einfärbt.

### Farbpalette

| Verwendung            | Hex        |
|-----------------------|------------|
| Akzent / Tag 1        | `#314B68`  |
| Tag 2                 | `#49627E`  |
| Tag 3                 | `#6F839A`  |
| Tag (weitere)         | `#9BA9B9`  |
| Teaser-Box Hintergrund| `#f3f5f8`  |
| Teaser-Box Rahmen     | `#e2e8f0`  |

Der Akzent `#314B68` wird durchgängig genutzt: erster Tag, „Weiterlesen"-Button
und Platzhalter-Kachel.

### Komponenten

- **Artikelliste** (`.mod_articles_group`)
  Vorschaubild links als quadratische, beschnittene Kachel
  (`clamp(120px, 22%, 180px)`, `aspect-ratio: 1/1`, `object-fit: cover`),
  Titel/Teaser rechts. Unten eine Flex-Zeile (`.mod_articles_bottom`) mit Tags links
  und „Weiterlesen"-Button rechts.

- **„Weiterlesen"-Button** (`.mod_articles_readmore a`)
  Schlanker Outline-Button im Tag-Stil (Navy, `border-radius: 3px`), Pfeil per
  CSS (`::after { content: "\2192" }`). Über `margin-left: auto` rechtsbündig;
  bricht bei vielen Tags / schmalem Viewport sauber in eine eigene Zeile um.

- **Leseansicht – Teaser-Box** (`.mod_articles_content_short`)
  Heller, kühler Lead-Block (`#f3f5f8`, dezenter Rahmen, abgerundet) mit
  Lead-Typografie. Das Artikelbild wird einheitlich gerahmt
  (`aspect-ratio: 4/3`, `object-fit: cover`, abgerundet).

- **Navigation** (`.mod_articles_nav`)
  Vor/Zurück/Übersicht unter dem Artikel bzw. die Paginierung der Liste.
  Drei gleich breite Flex-Zonen (links/mitte/rechts) – die Mitte bleibt
  zentriert, auch wenn eine Seite leer ist. Neutral gehalten: nur eine dünne
  Trennlinie oben, Links erben Farbe/Schrift, Hover = Unterstreichung.
  Richtungspfeile (← / →) kommen per CSS an Prev/Next-Links.

- **Tags** (`.mod_articles_tag`)
  Kompakte Pills, die ersten drei in abgestuftem Navy, weitere in `#9BA9B9`.

### Fehlende Artikelbilder

Ist weder ein Artikel-, Gruppen- noch ein Standardbild des Abschnitts gesetzt, wird
**kein** Platzhalter-Pixel mehr skaliert:

- **Leseansicht:** gar kein Bild – der Teasertext nutzt die volle Breite.
- **Liste / Grid:** eine schlichte **Platzhalter-Kachel** mit der Titel-Initiale
  auf Navy (`.mod_articles_noimage`), damit das Raster optisch gleichmäßig bleibt.

Die Unterscheidung erfolgt in `Articles\Frontend` je nach Liste oder Detailseite
(in der Leseansicht gesetzt, in der Liste nicht). Die Kachel-Optik liegt inline
am Element, damit sie in allen Views ohne zusätzliches CSS einheitlich aussieht.

## Dunkle Templates

Flächen, Rahmen und die Akzentschrift richten sich nach der Schriftfarbe des
Templates. Auf einem dunklen Template werden die Teaser-Box und die Karten
dadurch dunkel statt hell, ohne eigene Einstellungen. Tags, Buttons und die
Platzhalter-Kachel bringen Hintergrund und Schriftfarbe selbst mit und bleiben
gleich. Die Hex-Werte in der Tabelle sehen nur Browser ohne `color-mix()`
(vor 2023).

| Variable            | Wofür                                   | ohne `color-mix()` |
|---------------------|-----------------------------------------|--------------------|
| `--art-surface`     | Teaser-Box auf der Detailseite          | `#f3f5f8`          |
| `--art-card`        | Karten (grid, avatar)                   | `#fff`             |
| `--art-line`        | Rahmen von Box und Karten               | `#e2e8f0`          |
| `--art-placeholder` | Grund hinter Vorschaubildern            | `#f4f4f4`          |
| `--art-accent`      | Schrift und Rahmen von „Weiterlesen"    | `#314B68`          |

Eigene Werte setzt du im CSS des Templates, etwa
`.mod_articles_default { --art-surface: #fff8e1; }`. Sie gelten dann in hellen und
dunklen Templates gleich.

## Anpassen

- **Akzentfarbe ändern:** `--art-accent` für die Schrift von „Weiterlesen"
  (siehe „Dunkle Templates“), dazu `#314B68` in `frontend.css` (Tag 1,
  Button-Hover) und ggf. die Inline-Farbe der Platzhalter-Kachel in
  `Articles\Frontend` anpassen.
- **Eigene Optik ohne Core-Änderung:** am besten eine **eigene View** anlegen
  (Verzeichnis unter `views/` kopieren) und in den Abschnittseinstellungen auswählen,
  statt diese Default-Dateien zu überschreiben – so bleiben Updates problemlos.
