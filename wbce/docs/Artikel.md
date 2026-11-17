# Artikel

## Die Liste

Der Reiter **Artikel** zeigt alle Artikel des Abschnitts mit Gruppe, Anzahl der Tags, Zeitraum und Status.

**Status** sagt, ob und warum ein Artikel auf der Website erscheint:

| Status | Bedeutung |
|---|---|
| sichtbar | Der Artikel erscheint auf der Website. |
| inaktiv | Der Artikel ist abgeschaltet. |
| geplant | „Sichtbar ab“ liegt in der Zukunft. Der Artikel erscheint dann von selbst. |
| abgelaufen | „Sichtbar bis“ ist vorbei. |
| versteckt | Der Artikel ist aktiv, aber seine Gruppe ist inaktiv. |

Ein Klick auf den Status schaltet den Artikel aktiv oder inaktiv.

**Filter:** Über der Liste lässt sich nach Gruppe, Status und einem Teil des Titels filtern. „Filter zurücksetzen“ zeigt wieder alle.

**Reihenfolge:** Mit der Sortierung „Benutzerdefiniert“ (siehe [Einstellungen](Einstellungen.md)) lassen sich Artikel mit den Pfeilen oder per Ziehen am Griff ⋮⋮ umsortieren. Bei einem aktiven Filter und bei den anderen Sortierungen geht das nicht, die Reihenfolge ergibt sich dann aus den Daten.

**Löschen** entfernt den Artikel mit allen Bildern und seiner Detailseite. Das lässt sich nicht rückgängig machen.

## Massenaktionen

Artikel in der Liste ankreuzen (oder „Alle auswählen“), unter „Ausgewählte Artikel“ eine Aktion wählen und „Ausführen“:

| Aktion | Wirkung |
|---|---|
| Aktivieren, Deaktivieren | schaltet alle ausgewählten Artikel |
| Löschen | löscht sie mit Bildern, nach Rückfrage |
| Gruppe setzen | ordnet sie einer Gruppe des Abschnitts zu oder keiner |
| Tags setzen | ersetzt ihre Tags durch die gewählten |
| Startdatum leeren, Enddatum leeren | entfernt „Sichtbar ab“ bzw. „Sichtbar bis“ |
| Kopieren, Kopieren mit Tags | legt Kopien an, auf Wunsch auch in einem anderen Abschnitt (Ziel) |
| Verschieben, Verschieben mit Tags | verschiebt die Artikel in einen anderen Abschnitt |

**Kopien** sind zunächst inaktiv und stehen oben in der Liste. Sie bekommen eigene Kopien aller Bilder. Die Gruppe behalten sie nur im selben Abschnitt.

**Verschobene Artikel** stehen im Ziel oben, ohne Gruppe, und bekommen eine neue Adresse unter der Zielseite. Die alte Adresse funktioniert danach nicht mehr.

**Mit Tags:** Globale Tags und Tags, die es im Ziel schon gibt, bleiben erhalten. Lokale Tags des alten Abschnitts werden im Ziel über ihren Namen gesucht und, falls nötig, dort neu angelegt. Ohne „mit Tags“ haben die Artikel im Ziel keine Tags.

Als Ziel stehen nur Abschnitte zur Auswahl, deren Seite du bearbeiten darfst.

## Der Editor

„Artikel hinzufügen“ oder ein Klick auf den Titel öffnet den Editor.

**Titel** ist Pflicht.

**Adresse:** Aus dem Titel entsteht die Adresse der Detailseite, etwa `/pages/aktuelles/fruehlingskarte-38.php`. Die Nummer am Ende ist die Artikelnummer, sie macht die Adresse eindeutig. Bei einem neuen Artikel folgt die Adresse dem Titel. Bei einem bestehenden ist sie gesperrt, damit Links nicht kaputt gehen, und „Adresse ändern“ gibt sie frei.

**Kurztext** erscheint in der Liste, **Langtext** auf der Detailseite. Beide bearbeitest du im WYSIWYG-Editor der Website. **Block 2** gibt es nur, wenn er in den Einstellungen eingeschaltet ist. Er erscheint in einem zweiten Bereich des Templates, sofern das Template ihn vorsieht. Bleibt er leer, gilt der Block 2 aus den Einstellungen.

**Veröffentlichung:**
- **Aktiv** schaltet den Artikel ein oder aus.
- **Sichtbar ab** und **Sichtbar bis** begrenzen den Zeitraum, in deiner Zeitzone. Leer heißt ohne Beschränkung.
- Ein geplanter Artikel erscheint zur eingestellten Zeit von selbst, ein abgelaufener verschwindet von selbst.

**Einordnung:**
- **Abschnitt und Gruppe** wählt die Gruppe. Über diese Auswahl lässt sich der Artikel auch in einen anderen `articles`-Abschnitt verschieben.
- Tags, die es dort nicht gibt, fallen dabei weg. Mitnehmen lassen sie sich mit der Massenaktion „Verschieben mit Tags“.
- **Tags** sind die Tags dieses Abschnitts und die globalen Tags.

**Speichern** bleibt im Editor, **Speichern und zurück** führt zur Liste, **Ansehen** öffnet die Detailseite in einem neuen Fenster (nur bei sichtbaren Artikeln). Fehlt etwas oder ist ein Datum ungültig, bleibt die Eingabe im Formular stehen.

## Vorschaubild

Das Vorschaubild steht rechts im Editor und wird mit „Speichern“ hochgeladen. „Bild ersetzen“ lädt ein neues hoch, „Bild entfernen“ löscht es.

Erlaubt sind jpg, png, gif und webp bis zur maximalen Upload-Größe aus den Einstellungen. Jedes Bild wird geprüft und neu gespeichert:
- Die lange Kante des Originals wird auf höchstens 2560 Pixel begrenzt.
- Eingebettete Daten wie EXIF gehen dabei verloren. Die Drehung aus EXIF wird vorher angewendet.
- Animierte GIFs verlieren ihre Animation.

Hat ein Artikel kein Vorschaubild, zeigt die Website das Bild seiner Gruppe, sonst das Standardbild des Abschnitts. In Listen erscheint zuletzt eine farbige Kachel mit dem ersten Buchstaben des Titels.

## Galerie

Die Galerie steht unter dem Inhalt. Bei einem neuen Artikel geht sie erst nach dem ersten Speichern.

- **Bilder hinzufügen:** Bilder auf das Feld ziehen oder auswählen. Jedes Bild wird sofort hochgeladen und erscheint in der Liste. Was nicht passt (falsches Format, zu groß), wird mit Grund angezeigt.
- **Bildtext, Reihenfolge, Löschen** gelten mit „Speichern“. Die Reihenfolge änderst du per Ziehen am Griff ⋮⋮ oder mit den Pfeilen.
- Wer speichert, während noch Bilder hochgeladen werden, wird gefragt, weil der Rest dann abbricht.
- Ohne JavaScript werden die ausgewählten Bilder mit „Speichern“ hochgeladen. Der Server nimmt dabei nur eine begrenzte Zahl Dateien auf einmal an, meist 20.

Welche Galerie die Website zeigt (Fotorama, Masonry, Lightcase oder keine), legen die [Einstellungen](Einstellungen.md) fest.

## Detailseite und Sichtbarkeit

Jeder sichtbare Artikel bekommt eine eigene Datei unter `/pages/…`, die Detailseite. Sie entsteht, sobald der Artikel sichtbar wird, und verschwindet, wenn er es nicht mehr ist. Ein geplanter Artikel bekommt seine Datei beim ersten Aufruf der Liste nach seinem Startzeitpunkt.

„Weiterlesen“ erscheint in der Liste nur, wenn der Artikel einen Langtext oder eine Galerie hat. Über den Platzhalter `[LINK]` ist die Detailseite trotzdem immer erreichbar, die mitgelieferten Views verlinken damit den Titel (siehe [Platzhalter](Platzhalter.md)).
