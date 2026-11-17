# Gruppen und Tags

## Gruppen

Gruppen teilen die Artikel eines Abschnitts ein, etwa in „Aktuelles“, „Veranstaltungen“ und „Archiv“. Ein Artikel gehört zu höchstens einer Gruppe.

Im Reiter **Gruppen**:

- **Gruppe anlegen:** Titel eingeben, optional ein Bild hochladen, „Speichern“. Neue Gruppen stehen am Ende.
- **Bearbeiten:** Klick auf den Titel öffnet die Gruppe im Formular unter der Liste. Dort lassen sich Titel, Aktiv-Schalter und Bild ändern.
- **Reihenfolge:** mit den Pfeilen. Sie gilt für die Auswahllisten im Backend.
- **Aktiv-Schalter:** Ein Klick auf den Status schaltet die Gruppe. **Eine inaktive Gruppe versteckt ihre Artikel überall auf der Website**: in Liste, Detailseite, RSS, Suche und Droplets. Die Artikel behalten ihren eigenen Status und zeigen in der Artikelliste „versteckt“. Wird die Gruppe wieder aktiv, erscheinen sie wieder.
- **Löschen:** Die Gruppe verschwindet, ihre Artikel bleiben ohne Gruppe erhalten und werden dadurch womöglich sichtbar.

**Gruppenbild:** Es erscheint als `[GROUP_IMAGE]` und als Ersatz für Artikel ohne eigenes Vorschaubild. Es wird in der Vorschau-Größe aus den [Einstellungen](Einstellungen.md) erzeugt. Für das Hochladen gilt dasselbe wie beim Vorschaubild (siehe [Artikel](Artikel.md#vorschaubild)).

Auf der Website filtert `?g=<Gruppennummer>` die Liste nach einer Gruppe. Vor/Zurück auf der Detailseite bleibt dann innerhalb der Gruppe.

## Tags

Tags sind Schlagworte. Ein Artikel kann beliebig viele haben. Auf der Website erscheinen sie als farbige Etiketten. Ein Klick darauf zeigt alle Artikel mit diesem Tag (`?tags=<Name>`). Die Tags der angezeigten Artikel landen außerdem im Meta-Tag `keywords` der Seite.

Im Reiter **Tags**:

- **Tag anlegen:** Titel, optional Hintergrund- und Schriftfarbe, „Speichern“. Die Vorschau zeigt, wie das Etikett aussieht.
- **Bearbeiten** und **Löschen** über die Liste. Ein gelöschter Tag verschwindet bei allen Artikeln.
- Die Spalte „Artikel“ zählt, wie viele Artikel dieses Abschnitts den Tag nutzen.

**Farben:** am einfachsten als `#rrggbb` (z. B. `#314B68`). Erlaubt sind auch `#rgb`, `rgb(…)`, `hsl(…)` und Farbnamen wie `red`. Andere Angaben werden ignoriert. Ohne Schriftfarbe wählt `articles` Schwarz oder Weiß, je nachdem, was auf dem Hintergrund besser lesbar ist.

**Lokal oder global:**
- Ein Tag gilt normalerweise nur in diesem Abschnitt („diesem Abschnitt“).
- Administratoren können einen Tag mit „In allen Abschnitten verwenden (global)“ in allen `articles`-Abschnitten verfügbar machen.
- Globale Tags können nur Administratoren ändern oder löschen.
- Ein globaler Tag lässt sich nur wieder auf diesen Abschnitt beschränken, solange ihn kein anderer Abschnitt nutzt.

**Namen** sind je Abschnitt eindeutig, ohne Unterschied zwischen Groß- und Kleinschreibung. Ein lokaler Tag kann also nicht so heißen wie ein globaler.

Tags weist du im Editor des Artikels zu oder für mehrere Artikel auf einmal mit der Massenaktion „Tags setzen“.
