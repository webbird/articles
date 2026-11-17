# Einstellungen

Der Button **Einstellungen** öffnet die Einstellungen des Abschnitts. Ist „Einstellungen nur für Admins“ gesetzt, sehen nur Administratoren den Button.

## Liste

- **Sortierung:** in welcher Reihenfolge die Artikel erscheinen, auf der Website und im Backend.

  | Sortierung | Reihenfolge |
  |---|---|
  | Benutzerdefiniert | wie im Backend per Pfeil oder Ziehen festgelegt, neue Artikel oben |
  | Startdatum, neueste zuerst | nach „Sichtbar ab“ |
  | Enddatum, späteste zuerst | nach „Sichtbar bis“ |
  | Letzte Änderung, neueste zuerst | nach dem letzten Speichern |
  | Angelegt, neueste zuerst | nach dem Anlegen |
  | Enddatum, bald ablaufende zuerst | nach „Sichtbar bis“, aufsteigend |

- **Artikel pro Seite:** `0` zeigt alle auf einer Seite. Sonst blättern die Platzhalter für Vor/Zurück in Kopf und Fuß der Liste (siehe [Platzhalter](Platzhalter.md)).

## Darstellung

- **View** bestimmt die Templates und das CSS:
  - `default`: Liste mit Bild links
  - `grid`: Kacheln in zwei Spalten
  - `avatar`: Liste mit runden Bildern

  Beim Wechsel ersetzt `articles` nach einer Rückfrage alle Templates durch die der neuen View. Eigene Anpassungen an den Templates gehen dabei verloren.
- **Galerie** bestimmt, wie die Bilder der Galerie auf der Detailseite erscheinen:
  - **Fotorama:** Diashow mit Vorschaubildern
  - **Masonry:** Raster mit unterschiedlich hohen Bildern
  - **Tobii:** Raster aus Vorschaubildern, ein Klick öffnet das Bild groß in einer Lightbox. Dort lässt sich mit Pfeiltasten oder Wischen blättern, der Bildtext steht darunter
  - **Keine:** keine Galerie

  Der Wechsel ersetzt nach einer Rückfrage das Template „Detailseite: Inhalt“ und die Bild-Schleife.
- **Block 2 verwenden** zeigt im Editor das Feld „Block 2“. Sein Inhalt erscheint nur, wenn das Template der Website einen zweiten Inhaltsbereich ausgibt. Die mitgelieferten Core-Templates tun das über `MODULES_BLOCK2`.

**Eigene Views:** Ein Ordner `modules/articles/views/<name>/` mit einer `config.php` (Variablen für die Templates, im Format der mitgelieferten Views) und einer `frontend.css` erscheint automatisch in der Auswahl. Das CSS wird im Frontend eingebunden. Die Ausgabe steckt in `<div class="mod_articles_<name>">`, darauf sollte das CSS beschränkt sein.

## Bildgrößen

Jede Größe hat Breite, Höhe und einen Schalter „Zuschneiden“.

| Größe | Verwendet für |
|---|---|
| Vorschau | Vorschaubild des Artikels, Gruppenbild, Standardbild |
| Thumbnail | kleine Vorschau der Galeriebilder |
| Großansicht | Galeriebilder |

- **Ohne Zuschneiden** wird das Bild in die Größe eingepasst, das Seitenverhältnis bleibt.
- **Mit Zuschneiden** wird es auf genau diese Größe gebracht und dabei aus der Mitte beschnitten.
- Vergrößert wird nie: Ein kleineres Bild bleibt klein.

Von jedem Bild wird das Original aufbewahrt, die Größen werden daraus erzeugt. Neue Werte gelten für neue Uploads. Vorhandene Bilder passt „Bildgrößen neu berechnen“ im Reiter [Wartung](Wartung.md) an, die Einstellungen weisen nach dem Speichern darauf hin.

**Maximale Upload-Größe** in MB, höchstens so viel, wie PHP auf dem Server erlaubt (steht im Hinweis darunter).

## Standardbild

Es erscheint bei Artikeln, die weder ein eigenes Vorschaubild noch ein Gruppenbild haben.
- Du kannst ein Bild hochladen oder das Vorschaubild eines Artikels übernehmen.
- Übernommen wird eine Kopie, sie bleibt also auch, wenn der Artikel gelöscht wird.
- „Bild entfernen“ löscht es wieder.

## Detailseiten

**Verzeichnis unter /pages:** Hier liegen die Dateien der Detailseiten.
- Leer heißt: abgeleitet aus der Adresse der Seite, etwa `/pages/aktuelles/` für die Seite „Aktuelles“.
- Erlaubt sind Buchstaben, Ziffern, `-` und `_`.

Ein neues Verzeichnis gilt für neue Artikel und für Artikel, die neu gespeichert werden. Alle übrigen verschiebt „Artikel ins aktuelle Verzeichnis“ im Reiter [Wartung](Wartung.md). Dabei ändern sich ihre Adressen.

## Optionen

- **Expertenmodus (Templates bearbeiten):** Nach dem Speichern erscheinen die Templates zum Bearbeiten.
- **Einstellungen nur für Admins** (nur für Administratoren sichtbar): Redakteure können dann weder die Einstellungen öffnen noch importieren oder Demo-Inhalte anlegen, weil beides die Einstellungen überschreibt.

## Templates

Im Expertenmodus stehen acht Templates zur Verfügung:

| Template | Wo |
|---|---|
| Liste: Kopf, Liste: Fuß | über und unter der Liste, einmal pro Seite |
| Liste: Artikel | einmal pro Artikel in der Liste |
| Detailseite: Kopf, Inhalt, Fuß | auf der Detailseite, nacheinander |
| Bild-Schleife der Galerie | einmal pro Galeriebild, Ergebnis landet in `[IMAGES]` |
| Block 2 | Vorlage für den zweiten Inhaltsbereich der Detailseite |

Die Templates sind HTML mit Platzhaltern in eckigen Klammern, etwa `[TITLE]`. Alle Platzhalter stehen unter [Platzhalter](Platzhalter.md). Für eigenes CSS beginnen die Klassen mit `mod_articles_`.
