# articles

Seitenmodul für WBCE 1.7 mit Artikeln, Bildern, Gruppen und Tags. Nachfolger von `news_img`, läuft parallel dazu. Das Konzept steht in [docs/Konzept.md](docs/Konzept.md).

## Aufbau

| Ordner | Inhalt |
|---|---|
| `docs/` | Konzept und Dokumentation |
| `wbce/` | Das Modul selbst. Wird zum Entwickeln per Junction als `modules/articles` in die WBCE-Testinstallation eingebunden |
| `tests/fixtures/` | Testdaten für `news_img` als Importquelle |
| `tests/reference/` | Referenzausgaben von `news_img` für den Vergleich nach dem Import |

## Testdaten und Referenzausgaben

Voraussetzung ist eine WBCE-1.7-Installation mit `news_img` 5.0.x und einer Seite mit einem `news_img`-Abschnitt. Ein zusätzlicher WYSIWYG-Abschnitt auf derselben Seite wird mit Droplet-Aufrufen gefüllt.

```bash
php tests/fixtures/seed_news_img.php <wbce-verzeichnis> <section_id>
php tests/reference/capture.php <wbce-verzeichnis> <section_id> <wbce-url>
```

Das Seed-Skript ersetzt alle `news_img`-Daten dieses einen Abschnitts. Andere Abschnitte bleiben unberührt.

## Entwickeln

`wbce/` wird per Junction als Modul eingebunden und einmal installiert, danach wirken Änderungen sofort:

```powershell
New-Item -ItemType Junction -Path <wbce-verzeichnis>\modules\articles -Target <repo>\wbce
```

Installiert wird im Backend unter Erweiterungen, im Bereich für per FTP hochgeladene Module (`install` manuell ausführen). `install.php` lässt sich gefahrlos wiederholen und legt fehlende Tabellen und Indizes an.
