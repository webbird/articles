# articles

Seitenmodul für WBCE 1.7 mit Artikeln, Bildern, Gruppen und Tags. Nachfolger von `news_img`, läuft parallel dazu. Das Konzept steht in [docs/Konzept.md](docs/Konzept.md).

## Aufbau

| Ordner | Inhalt |
|---|---|
| `docs/` | Konzept und Dokumentation |
| `wbce/` | Das Modul selbst. Wird zum Entwickeln per Junction als `modules/articles` in die WBCE-Testinstallation eingebunden |
| `tests/fixtures/` | Testdaten für `news_img` als Importquelle |
| `tests/reference/` | Referenzausgaben von `news_img` für den Vergleich nach dem Import |
| `tests/import/` | Import aus `news_img` per Kommandozeile, bis das Backend ihn anbietet |
| `tests/unit/` | PHPUnit-Tests ohne WBCE |

## Testdaten und Referenzausgaben

Voraussetzung ist eine WBCE-1.7-Installation mit `news_img` 5.0.x und einer Seite mit einem `news_img`-Abschnitt. Ein zusätzlicher WYSIWYG-Abschnitt auf derselben Seite wird mit Droplet-Aufrufen gefüllt.

```bash
php tests/fixtures/seed_news_img.php <wbce-verzeichnis> <section_id>
php tests/reference/capture.php <wbce-verzeichnis> <section_id> <wbce-url>
```

Das Seed-Skript ersetzt alle `news_img`-Daten dieses einen Abschnitts. Andere Abschnitte bleiben unberührt.

Nach dem Import vergleicht `compare.php` die `articles`-Section mit der Referenz. Es ruft dieselben Ansichten ab, rechnet URLs, IDs und Bildordner um und schreibt beide Seiten zeilenweise in das Ausgabeverzeichnis, sodass `diff` nur echte Unterschiede zeigt. Die gewollten Abweichungen stehen im Konzept, Abschnitt 10.

```bash
php tests/reference/compare.php <wbce-verzeichnis> tests/reference/news_img-5.0.32 <ziel-section> [<ausgabe-verzeichnis>]
```

## Entwickeln

`wbce/` wird per Junction als Modul eingebunden und einmal installiert, danach wirken Änderungen sofort:

```powershell
New-Item -ItemType Junction -Path <wbce-verzeichnis>\modules\articles -Target <repo>\wbce
```

Installiert wird im Backend unter Erweiterungen, im Bereich für per FTP hochgeladene Module (`install` manuell ausführen). `install.php` lässt sich gefahrlos wiederholen und legt fehlende Tabellen und Indizes an.

Das Modul nie über die Modulverwaltung installieren oder aktualisieren, solange es per Junction eingebunden ist: Das schreibt direkt ins Repository.

## Import aus news_img

Im Backend steht der Import im Reiter „Wartung“, solange der Abschnitt leer ist. Für Tests geht er auch per Kommandozeile. Ziel ist ein leerer `articles`-Abschnitt. Die `news_img`-Quelle wird nur gelesen. Die Access-Dateien der sichtbaren Artikel entstehen beim Import.

```bash
php tests/import/import_news_img.php <wbce-verzeichnis> <quell-section> <ziel-section> <ziel-page>
```

## Droplets

`install.php` und `upgrade.php` legen die Droplets aus `wbce/droplets/` an oder aktualisieren sie.

- `[[articles_list?…]]` versteht dieselben Parameter wie `fetchNewsItems`, z. B. `group_id_type=section_id&group_id=8&max_news_items=4`, `tags=Saisonal` oder `aslist=1`.
- `[[articles_slider?section_id=8]]` zeigt die sichtbaren Artikel mit Vorschaubild als Bootstrap-5-Karussell. Mit `tpl=<name>` lässt sich eine andere Vorlage aus `wbce/templates/slides/` wählen.

## Unit-Tests

Ein eigenes `composer.json` gibt es noch nicht. Bis dahin läuft PHPUnit 11 aus einem anderen Projekt:

```bash
php <pfad-zu>/vendor/bin/phpunit -c phpunit.xml.dist
```
