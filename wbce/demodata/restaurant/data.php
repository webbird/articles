<?php
/**
 * articles – demo pack "restaurant" (taken over from news_img v6.0)
 * Read only by Articles\Import::demo(), never executed otherwise.
 *
 * Keys:
 *   settings  optional; only the keys of Import::DEMO_SETTINGS are used, the
 *             values go through Settings::save() like the settings form
 *   groups    title, active, position
 *   tags      title, color, text_color; an existing tag of the same title is used
 *   posts     slug, title, group (title), tags (titles), active, preview_image,
 *             images (gallery, files in images/<slug>/), teaser, content, block2,
 *             published_from, published_until
 * Image credits are in images/<slug>/copyright.csv; the import does not read them yet.
 */
if (!defined('WB_PATH')) {
    exit('Cannot access this file directly');
}

// Zufalls-Timestamp innerhalb der letzten 90 Tage; pro Aufruf neu, damit
// die Demo-Posts beim Import jeweils mit frischen Daten erscheinen.
$random_recent = function () {
    return time() - mt_rand(0, 90 * 86400);
};

return [
    'settings' => [
        'thumb_width'    => 200,
        'thumb_height'   => 200,
        'preview_width'  => 300,
        'preview_height' => 300,
    ],

    'groups' => [
        ['title' => 'Wochenkarte',    'active' => 1, 'position' => 1],
        ['title' => 'Events',         'active' => 1, 'position' => 2],
        ['title' => 'Aus der Küche',  'active' => 1, 'position' => 3],
    ],

    'tags' => [
        ['title' => 'Saisonal', 'color' => '#5d9e6c', 'text_color' => '#ffffff'],
        ['title' => 'Event',    'color' => '#e67e22', 'text_color' => '#ffffff'],
        ['title' => 'Region',   'color' => '#27ae60', 'text_color' => '#ffffff'],
        ['title' => 'Wein',     'color' => '#8e44ad', 'text_color' => '#ffffff'],
    ],

    'posts' => [

        // ---------- Wochenkarte ----------
        [
            'slug'            => 'fruehlingskarte',
            'title'           => 'Frühlingskarte ist da',
            'group'           => 'Wochenkarte',
            'tags'            => ['Saisonal'],
            'active'          => 1,
            'preview_image'   => 'preview.jpg',
            'images'          => ['gericht-01.jpg', 'gericht-02.jpg', 'gericht-03.jpg'],
            'teaser'          => '<p>Die neue Frühlingskarte ist da: junge Salate, Bärlauch, erster Spargel und frische Kräuter aus der Region. Wir laden ein, die leichte Saison zu probieren.</p>',
            'content'         => '<p>Auf unserer aktuellen Karte stehen unter anderem ein Wildkräutersalat mit pochiertem Ei, Bärlauch-Tagliatelle mit gerösteten Pinienkernen sowie ein Frühlingsgemüse-Risotto mit gebratener Forelle aus heimischer Zucht. Dazu empfehlen wir einen jungen Grauburgunder aus dem Markgräflerland. Reservierungen telefonisch oder über unser Online-Formular.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'spargelzeit',
            'title'           => 'Spargelzeit – die Klassiker und Neues',
            'group'           => 'Wochenkarte',
            'tags'            => ['Saisonal', 'Region'],
            'active'          => 1,
            'preview_image'   => 'preview.jpg',
            'images'          => ['gericht-01.jpg', 'gericht-02.jpg'],
            'teaser'          => '<p>Vom Spargelhof Müller, nur wenige Kilometer entfernt: stangenfrisch geernteter weißer und grüner Spargel. Klassisch mit Sauce Hollandaise oder ungewöhnlich kombiniert.</p>',
            'content'         => '<p>Klassiker bleiben Klassiker: weißer Spargel mit Sauce Hollandaise, neuen Kartoffeln und entweder Schinken aus dem Schwarzwald oder einem Pochiertem Ei. Daneben experimentieren wir mit grünem Spargel im Risotto, als Bestandteil einer asiatisch inspirierten Wokpfanne und als feines Spargel-Carpaccio mit Parmesan und Olivenöl. Die Spargelsaison läuft – wie immer – bis Johannistag, dem 24. Juni.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'sonntagsbrunch',
            'title'           => 'Sonntagsbrunch ist zurück',
            'group'           => 'Wochenkarte',
            'tags'            => ['Event'],
            'active'          => 1,
            'preview_image'   => 'preview.jpg',
            'images'          => ['gericht-01.jpg', 'gericht-02.jpg', 'gericht-03.jpg', 'ritae-eclair-3366430_1280.jpg'],
            'teaser'          => '<p>Jeden Sonntag von 10 bis 14 Uhr: unser ausgiebiges Brunch-Buffet mit allem, was zu einem entspannten Sonntagmorgen gehört. Reservierung empfohlen.</p>',
            'content'         => '<p>Frisches Brot vom Holzofenbäcker, hausgemachte Marmeladen, regionale Käse- und Wurstplatten, Räucherfisch, eine kleine warme Auswahl (Rührei, Bratkartoffeln, Mini-Pancakes) und natürlich Kuchen und Süßes für danach. Kaffee, Tee, Säfte und ein Glas Sekt sind im Preis inbegriffen. Erwachsene 29,50 €, Kinder bis 12 Jahre die Hälfte, unter 6 essen kostenfrei mit.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],

        // ---------- Events ----------
        [
            'slug'            => 'weinabend',
            'title'           => 'Weinabend am Freitag',
            'group'           => 'Events',
            'tags'            => ['Event', 'Wein'],
            'active'          => 1,
            'preview_image'   => 'preview.jpg',
            'images'          => [],
            'teaser'          => '<p>An jedem ersten Freitag im Monat öffnen wir eine ausgewählte Weinregion – mit fünf Weinen, kleinen Begleitspeisen und ein paar Worten zur Erzeugerin oder zum Erzeuger.</p>',
            'content'         => '<p>Im kommenden Monat geht es ins Piemont: Arneis, Barbera, ein junger und ein gereifter Nebbiolo sowie ein Moscato zum Abschluss. Dazu hauchdünner Vitello Tonnato, Vitello mit Salbei, Risotto Milanese und ein kleines Dolce. Beginn 19:00 Uhr, Dauer ca. zweieinhalb Stunden, 65 € pro Person inkl. allem.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'live-musik',
            'title'           => 'Live-Musik am Wochenende',
            'group'           => 'Events',
            'tags'            => ['Event'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Samstags ab 20 Uhr Live-Musik bei uns – meistens akustisch, immer dezent: vom Jazz-Standard bis zur entspannten Singer-Songwriter-Nummer.</p>',
            'content'         => '<p>Die Auftritte sind im Restaurant integriert, kein Eintritt, keine Anmeldung. Reservierungen sind an Live-Abenden besonders zu empfehlen – die Plätze rund um die kleine Bühne sind schnell belegt. Das aktuelle Programm hängt im Gastraum aus und steht auf unserer Website.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'pasta-kochkurs',
            'title'           => 'Kochkurs: Pasta selbstgemacht',
            'group'           => 'Events',
            'tags'            => ['Event'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Einen Vormittag lang Nudeln machen wie in Italien: Teig kneten, ausrollen, formen – und am Ende selber essen. Maximal acht Plätze pro Termin.</p>',
            'content'         => '<p>Unser Sous-Chef Marco zeigt die Basics (klassischer Eiernudelteig, glutenfreie Variante mit Hartweizengrieß) und zwei einfache, beeindruckende Formen: Tagliatelle und Tortellini. Danach kochen wir gemeinsam eine Sauce und essen das Ergebnis zu Mittag. 95 € pro Person inkl. Schürze zum Mitnehmen, einem Glas Wein und einem Rezeptheft. Termine alle zwei Monate – aktuelle Daten siehe Veranstaltungsseite.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'weihnachtsfeiern',
            'title'           => 'Weihnachtsfeiern buchbar',
            'group'           => 'Events',
            'tags'            => ['Event'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Für Firmen, Vereine und Familien: Geschlossene Gesellschaften und Tisch­reservierungen für die Adventszeit sind ab sofort buchbar. Wir empfehlen frühe Reservierung.</p>',
            'content'         => '<p>Wir bieten drei Menü-Varianten (3, 4 und 5 Gänge), eine vegetarische und eine vegane Hauptgang-Alternative pro Variante sowie ein Buffet ab 25 Personen. Räume: Hauptgastraum bis 40 Personen, oberer Salon bis 20 Personen, Wintergarten bis 12 Personen. Anfragen am besten per E-Mail mit Wunschdatum und Personenzahl – wir melden uns innerhalb von 24 Stunden zurück.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],

        // ---------- Aus der Küche ----------
        [
            'slug'            => 'neue-lieferanten',
            'title'           => 'Neue Lieferanten aus der Region',
            'group'           => 'Aus der Küche',
            'tags'            => ['Region'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Wir freuen uns über zwei neue Partner: einen kleinen Bio-Hof aus dem Nachbarort und eine Käserei aus dem Tal. Beide liefern jetzt wöchentlich frisch zu uns.</p>',
            'content'         => '<p>Vom Bio-Hof Wiedemann kommen ab sofort Kartoffeln in fünf Sorten, Wurzelgemüse und im Sommer Salate. Die Sennerei Hoch&shy;berg liefert uns einen Bergkäse, der bei ihnen mindestens 12 Monate reift, sowie einen jungen Camembert aus Rohmilch. Wir sind dankbar, dass es solche Höfe und Hand­werks­betriebe in der Nähe noch gibt – und werden weiter daran arbeiten, möglichst viele unserer Zutaten von hier zu beziehen.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'team',
            'title'           => 'Das Team wächst',
            'group'           => 'Aus der Küche',
            'tags'            => [],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Wir freuen uns über Verstärkung: Seit diesem Monat gehören Lisa (Service) und Tobias (Patisserie) zum Team. Beide bringen viel Erfahrung mit – und gute Laune sowieso.</p>',
            'content'         => '<p>Lisa hat zuletzt in einem Hotel am Bodensee gearbeitet und kennt sich besonders gut mit Wein aus. Tobias hat eine klassische Patisserie-Ausbildung in Wien gemacht und entwickelt gerade unsere Dessertkarte weiter – die nächsten Wochen werden also süß. Sagt gerne Hallo, wenn ihr das nächste Mal da seid.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'hinter-kulissen',
            'title'           => 'Hinter den Kulissen',
            'group'           => 'Aus der Küche',
            'tags'            => [],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Was passiert eigentlich vor 18 Uhr, wenn die ersten Gäste kommen? Ein kleiner Blick in unseren Tagesablauf – vom Markt­einkauf bis zum „Mise en Place".</p>',
            'content'         => '<p>Der Tag beginnt früh: um 7 Uhr ist Marco auf dem Großmarkt, zurück gegen 9. Bis 11 Uhr werden Gemüse geputzt, Fonds angesetzt, Brot geknetet. Dann Mittagessen fürs Team (das wichtigste Essen des Tages, sagen wir). Zwischen 14 und 17 Uhr ist Vor­bereitungs­zeit: anrichten, vor­portionieren, die Karte fürs Abend­geschäft fertig­machen. Und dann geht\'s los. So sieht ein typischer Tag aus – bis auf Sonntag, da läuft alles eine Stunde später.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],

    ],
];
