# Wartung, Import und Demo-Inhalte

Der Reiter **Wartung** sammelt Aufgaben, die selten nötig sind.

## Access-Dateien

Jeder sichtbare Artikel hat eine Datei unter `/pages/…`, seine Detailseite. `articles` legt sie normalerweise selbst an und entfernt sie wieder. Die Karte zeigt, wie viele fehlen und wie viele überzählig sind.

**Erneuern** legt fehlende Dateien an, entfernt die von nicht sichtbaren Artikeln und bringt veraltete auf den aktuellen Stand. Nötig ist das etwa nach dem Zurückspielen einer Sicherung oder nach einem Update von `articles`.

Dateien, die nicht von `articles` stammen, rührt das Modul nie an. Das gilt zum Beispiel für die Beiträge von `news_img`, wenn beide Module dasselbe Verzeichnis nutzen.

## Bildgrößen neu berechnen

Erzeugt Vorschau, Thumbnail und Großansicht aller Bilder des Abschnitts neu aus den Originalen. Das gilt für Artikel-, Galerie-, Gruppen- und Standardbilder. Nötig ist das nach einer Änderung der Bildgrößen in den [Einstellungen](Einstellungen.md#bildgrößen).

Bei sehr vielen Bildern kann das eine Weile dauern. Aus `news_img` übernommene Bilder haben als Original nur die Größe, die `news_img` gespeichert hatte. Größer als diese werden sie also nicht.

## Artikel ins aktuelle Verzeichnis

Nach einer Änderung des Verzeichnisses der Detailseiten (siehe [Einstellungen](Einstellungen.md#detailseiten)) bleiben vorhandene Artikel zunächst unter ihrer alten Adresse. Diese Karte zeigt, wie viele das sind, und verschiebt sie nach einer Rückfrage ins aktuelle Verzeichnis.

**Die alten Adressen funktionieren danach nicht mehr.** Links von außen und Lesezeichen gehen ins Leere. Links innerhalb der Website über `[articles:NN]` (siehe [Droplets, RSS und Links](Droplets.md#links-auf-artikel)) passen sich von selbst an.

## Import

Der Import füllt einen **leeren** Abschnitt, also einen ohne Artikel und Gruppen, mit dem Inhalt eines anderen Abschnitts. Die Quelle bleibt unverändert, die Einstellungen dieses Abschnitts werden überschrieben. Zur Auswahl stehen `news_img`-, Topics- und andere `articles`-Abschnitte, deren Seite du bearbeiten darfst. Die Zahl in Klammern ist die Anzahl der Artikel.

Nach dem Import meldet `articles`, wie viele Artikel, Gruppen, Tags und Bilder übernommen wurden. Hinweise zeigen zum Beispiel fehlende Bilddateien.

### Aus news_img

| Was | Wie |
|---|---|
| Einstellungen | Templates, Sortierung, Seitengröße, Bildgrößen, Galerie, View, Verzeichnis. In den Templates wird `mod_nwi_` zu `mod_articles_`. Eigenes CSS außerhalb des Moduls, das sich auf `mod_nwi_` bezieht, muss angepasst werden. Eine View oder Galerie, die es in `articles` nicht gibt, wird zu `default` bzw. `fotorama`, mit Hinweis |
| Gruppen | mit Bild, Aktiv-Status und Reihenfolge |
| Tags | lokale Tags der Quelle und alle Tags ihrer Beiträge. Globale Tags werden über ihren Namen wiederverwendet |
| Beiträge | alle, auch inaktive, mit Zeitraum, Autor und Anlagedatum |
| Bilder | Vorschau-, Galerie-, Gruppen- und Standardbilder werden kopiert |

Die Adressen der Artikel entstehen neu. Alte Beitragsadressen und Links im Format `[news_img:NN]` funktionieren nur so lange, wie `news_img` und der Quellabschnitt bestehen.

### Aus einem anderen articles-Abschnitt

Übernommen werden die Einstellungen (ohne das Verzeichnis der Detailseiten), die Gruppen mit Bild, alle Artikel mit Status, Reihenfolge, Zeitraum, Autor und Galerie sowie das Standardbild. Lokale Tags der Quelle werden über ihren Namen in diesem Abschnitt angelegt oder genutzt, globale bleiben. Die Adressen der Artikel entstehen unter dieser Seite neu.

### Aus Topics

Auch umbenannte Kopien von Topics stehen zur Auswahl, unter ihrem Modulnamen.

| Was | Wie |
|---|---|
| Einstellungen | Topics pro Seite und die Sortierung: nach Position oder nach Datum. Andere Sortierungen (Titel, Wichtigkeit, Eventkalender, Kommentare) gibt es in `articles` nicht, dann gilt die benutzerdefinierte Reihenfolge, mit Hinweis. Die Templates von Topics brauchen dessen CSS und eigene Platzhalter, deshalb bleiben die von `articles` |
| Topics | alle, mit Zeitraum, Autor und Anlagedatum. Öffentlich, Aktuell und Top-Aktuell werden aktiv. Unveröffentlichte, versteckte und nur für angemeldete Besucher sichtbare Topics werden inaktiv, mit Hinweis. Das Zusatzfeld (Extra) landet in Block 2 |
| Bilder | Das Topic-Bild wird Vorschaubild, die Zusatzbilder werden Galerie, mit Bildtext. Übernommen wird jeweils die größte Fassung (aus `zoom/`), die Größen berechnet `articles` neu |
| Adresse | Der Dateiname der Quelle bleibt. Nur der Standardname `topicNN` wird durch den Titel ersetzt |

Nicht übernommen werden Kommentare (mit Hinweis auf die Anzahl), Schlüsselwörter, Beschreibungen, Gruppen und „Siehe auch“.

## Demo-Inhalte

Füllt einen leeren Abschnitt mit Beispieldaten, zum Ausprobieren oder als Vorlage.

| Paket | Inhalt |
|---|---|
| Restaurant | 3 Gruppen, 4 Tags, 10 Artikel, 4 davon mit Vorschau- und Galeriebildern. Setzt Vorschau- und Thumbnail-Größe. Die Daten liegen in den letzten 90 Tagen |
| Stadtverwaltung | 3 Gruppen, 4 Tags, 10 Artikel ohne Bilder. Die Daten passen sich dem aktuellen Jahr an |

- Die Tags entstehen in diesem Abschnitt. Gibt es schon einen Tag gleichen Namens, wird er genutzt.
- Die Bilder werden wie Uploads geprüft und in den eingestellten Größen erzeugt. Sie stammen von [Pixabay](https://pixabay.com), die Quellen stehen in `modules/articles/demodata/restaurant/images/*/copyright.csv`.
- Ein Rückgängig gibt es nicht. Die Inhalte lassen sich wie alle anderen bearbeiten und löschen, am schnellsten mit der Massenaktion „Löschen“ und dem Löschen der Gruppen.

Import und Demo-Inhalte erscheinen nur für Benutzer, die die Einstellungen ändern dürfen (siehe „Einstellungen nur für Admins“ in den [Einstellungen](Einstellungen.md#optionen)).
