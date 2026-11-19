# articles

Seitenmodul für WBCE 1.7 mit Artikeln, Bildern, Gruppen und Tags. Nachfolger von `news_img`, läuft parallel dazu. Die Bedienung beschreibt die [Dokumentation](wbce/docs/README.md), die mit dem Modul ausgeliefert wird. Das Konzept steht in [docs/Konzept.md](docs/Konzept.md).

## Aufbau

| Ordner | Inhalt |
|---|---|
| `docs/` | Konzept |
| `wbce/` | Das Modul selbst. Wird zum Entwickeln per Junction als `modules/articles` in die WBCE-Testinstallation eingebunden |
| `wbce/docs/` | Dokumentation für Redakteure und Administratoren, wird mit dem Modul ausgeliefert |
| `tests/fixtures/` | Testdaten für `news_img` als Importquelle |
| `tests/reference/` | Referenzausgaben von `news_img` für den Vergleich nach dem Import |
| `tests/import/` | Import aus `news_img` per Kommandozeile, bis das Backend ihn anbietet |
| `tests/integration/` | Prüfskripte gegen eine WBCE-Testinstallation, die ihre Änderungen selbst zurücksetzen |
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

## Paket bauen

`build.php` packt den Inhalt von `wbce/` in `dist/articles-<version>.zip`, die Version kommt aus `wbce/info.php`. Das ZIP lässt sich im Backend von WBCE unter Erweiterungen installieren. Dateien und Ordner, die mit einem Punkt beginnen, bleiben draußen. `dist/` ist von Git ausgenommen.

```bash
php build.php [<ausgabe-verzeichnis>]
```

Das Skript braucht die PHP-Erweiterung `zip`. Die PHP-Version der Portable hat sie, das System-PHP nicht.

## Import und Demo-Inhalte

Im Backend stehen der Import und die Demo-Inhalte im Reiter „Wartung“, solange der Abschnitt leer ist. Quelle ist ein `news_img`-, Topics- oder ein anderer `articles`-Abschnitt, den der Benutzer bearbeiten darf. Die Quelle wird nur gelesen. Die Access-Dateien der sichtbaren Artikel entstehen beim Import. Die Demo-Pakete liegen in `wbce/demodata/` (Bilder von Pixabay, Quellen in `copyright.csv`).

Der Import aus `news_img` geht für Tests auch per Kommandozeile:

```bash
php tests/import/import_news_img.php <wbce-verzeichnis> <quell-section> <ziel-section> <ziel-page>
```

## Droplets

`install.php` und `upgrade.php` legen die Droplets aus `wbce/droplets/` an oder aktualisieren sie.

- `[[articles_list?…]]` versteht dieselben Parameter wie `fetchNewsItems`, z. B. `group_id_type=section_id&group_id=8&max_news_items=4`, `tags=Saisonal` oder `aslist=1`.
- `[[articles_slider?section_id=8]]` zeigt die sichtbaren Artikel mit Vorschaubild als Bootstrap-5-Karussell. Mit `tpl=<name>` lässt sich eine andere Vorlage aus `wbce/templates/slides/` wählen.

## Prüfskripte

`droplet_visibility.php` prüft, dass die Droplets nur Artikel öffentlicher oder versteckter Seiten zeigen und Abschnitte außerhalb ihres Zeitraums auslassen. Dazu, dass Links per `[articles:NN]` auch auf privaten und registrierten Seiten aufgelöst werden, nicht aber im Papierkorb, auf Seiten ohne Sichtbarkeit und außerhalb des Zeitraums. Es ändert dafür kurz die Sichtbarkeit der Seite und den Zeitraum des Abschnitts und stellt beides danach wieder her. Der Abschnitt braucht sichtbare Artikel mit Vorschaubild.

```bash
php tests/integration/droplet_visibility.php <wbce-verzeichnis> <page_id> <section_id>
```

`access_rights.php` prüft die Rechte im Backend (`Articles\Access`) mit fremden IDs, mit einem Ersatz für den angemeldeten Benutzer: Redakteur der Seite, Benutzer ohne Rechte, Admin. Dazu Speichern, Kopieren und Löschen eines Artikels und, wenn ein zweiter articles-Abschnitt derselben Seite angegeben ist, das Verschieben dorthin und zurück. Es legt nur vorübergehend Zeilen in den articles-Tabellen an, keine Seiten oder Abschnitte. Der Abschnitt braucht mindestens einen Artikel und eine Gruppe.

```bash
php tests/integration/access_rights.php <wbce-verzeichnis> <page_id> <section_id> [<ziel-section>]
```

`import_articles_demo.php` importiert einen articles-Abschnitt und das Demo-Paket „Restaurant“ in die Abschnitts-IDs 999999 und 999998, die es nicht gibt, vergleicht das Ergebnis und löscht beide wieder. Es entstehen keine Seiten oder Abschnitte.

```bash
php tests/integration/import_articles_demo.php <wbce-verzeichnis> <quell-section>
```

`import_topics.php` importiert einen Topics-Abschnitt in die Abschnitts-ID 999997 und vergleicht Texte, Status, Daten, Bilder und Einstellungen. Für die Galerie legt es beim ersten Topic vorübergehend zwei Zusatzbilder an. Danach löscht es alles wieder.

```bash
php tests/integration/import_topics.php <wbce-verzeichnis> <topics-section>
```

## Unit-Tests

Ein eigenes `composer.json` gibt es noch nicht. Bis dahin läuft PHPUnit 11 aus einem anderen Projekt:

```bash
php <pfad-zu>/vendor/bin/phpunit -c phpunit.xml.dist
```
