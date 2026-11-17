# articles – Dokumentation

`articles` ist ein Seitenmodul für WBCE 1.7 mit Artikeln, Bildern, Gruppen und Tags, der Nachfolger von `news_img`. Diese Seiten beschreiben die Bedienung für Redakteure und Administratoren.

| Seite | Inhalt |
|---|---|
| [Artikel](Artikel.md) | Liste, Status, Filter, Sortieren, Massenaktionen, Editor, Bilder und Galerie |
| [Gruppen und Tags](Gruppen-und-Tags.md) | Gruppen mit Bild und Aktiv-Schalter, lokale und globale Tags, Farben |
| [Einstellungen](Einstellungen.md) | Liste, Darstellung, Bildgrößen, Standardbild, Verzeichnis der Detailseiten, Templates |
| [Platzhalter](Platzhalter.md) | Alle Platzhalter der Templates, URL-Parameter der Liste |
| [Wartung, Import und Demo-Inhalte](Wartung.md) | Access-Dateien, Bildgrößen neu berechnen, Umzug der Detailseiten, Import aus `news_img` und `articles`, Demo-Pakete |
| [Droplets, RSS und Links](Droplets.md) | `[[articles_list]]`, `[[articles_slider]]`, RSS-Feed, Links auf Artikel |

Die Dateien liegen im Modul unter `modules/articles/docs/`. Konzept und Hinweise für Entwickler stehen im Repository von `articles`.

## Auf einen Blick

- Jeder Abschnitt mit `articles` hat oben die Reiter **Artikel**, **Gruppen**, **Tags** und **Wartung** sowie die Buttons **Einstellungen** und **Artikel hinzufügen**.
- Ein Artikel ist auf der Website sichtbar, wenn er aktiv ist, einen Titel hat, in seinem Zeitraum liegt und seine Gruppe aktiv ist (oder er keine Gruppe hat). Diese eine Regel gilt überall: Liste, Detailseite, Vor/Zurück, RSS, Suche und Droplets.
- Jeder sichtbare Artikel hat eine eigene Detailseite unter `/pages/…`. Sie entsteht und verschwindet automatisch mit der Sichtbarkeit.
