<?php
/**
 * articles – demo pack "stadtverwaltung" (taken over from news_img v6.0)
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

$thisyear = date('Y');
$lastyear = $thisyear - 1;

// Liefert einen Unix-Timestamp innerhalb der letzten 90 Tage. Wird pro Post-
// Definition einmal aufgerufen, damit die Beispieldaten beim Import frisch
// aussehen statt mit längst vergangenen Hardcoded-Daten daherzukommen.
$random_recent = function () {
    return time() - mt_rand(0, 90 * 86400);
};

return [

    'groups' => [
        ['title' => 'Aktuelles',         'active' => 1, 'position' => 1],
        ['title' => 'Veranstaltungen',   'active' => 1, 'position' => 2],
        ['title' => 'Pressemitteilungen','active' => 1, 'position' => 3],
    ],

    'tags' => [
        ['title' => 'Stadtentwicklung', 'color' => '#4a90d9', 'text_color' => '#ffffff'],
        ['title' => 'Veranstaltung',    'color' => '#e67e22', 'text_color' => '#ffffff'],
        ['title' => 'Presse',           'color' => '#8e44ad', 'text_color' => '#ffffff'],
        ['title' => 'Verwaltung',       'color' => '#27ae60', 'text_color' => '#ffffff'],
    ],

    'posts' => [

        // ---------- Aktuelles ----------
        [
            'slug'            => 'fruehjahrsputz-stadtpark',
            'title'           => 'Frühjahrsputz im Stadtpark',
            'group'           => 'Aktuelles',
            'tags'            => ['Verwaltung'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Am kommenden Samstag findet der alljährliche Frühjahrsputz im Stadtpark statt. Alle Bürgerinnen und Bürger sind herzlich eingeladen, mitzumachen.</p>',
            'content'         => '<p>Treffpunkt ist um 9:00 Uhr am Haupteingang. Werkzeug und Handschuhe werden gestellt. Im Anschluss gibt es für alle Helfer eine kleine Stärkung.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'neue-oeffnungszeiten-mai',
            'title'           => 'Neue Öffnungszeiten ab Mai',
            'group'           => 'Aktuelles',
            'tags'            => ['Verwaltung'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Das Bürgeramt passt ab dem 1. Mai seine Öffnungszeiten an. Die neuen Zeiten gelten für alle Standorte im Stadtgebiet.</p>',
            'content'         => '<p>Montag bis Freitag: 8:00 – 18:00 Uhr, Samstag: 9:00 – 13:00 Uhr. Termine können weiterhin online gebucht werden.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'bauprojekt-hauptstrasse',
            'title'           => 'Bauprojekt Hauptstraße startet',
            'group'           => 'Aktuelles',
            'tags'            => ['Stadtentwicklung'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Ab dem 15. April beginnen die Bauarbeiten zur Erneuerung der Hauptstraße. Mit Einschränkungen im Straßenverkehr ist zu rechnen.</p>',
            'content'         => '<p>Die Baumaßnahme umfasst Fahrbahn, Gehwege sowie die Erneuerung der Leitungsinfrastruktur. Geplante Dauer: 6 Monate. Eine Umleitung ist ausgeschildert.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'digitalisierung-verwaltung',
            'title'           => 'Digitalisierung der Verwaltung schreitet voran',
            'group'           => 'Aktuelles',
            'tags'            => ['Verwaltung', 'Stadtentwicklung'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Die Stadt hat einen weiteren Meilenstein bei der Digitalisierung ihrer Verwaltungsleistungen erreicht. Über 40 Services sind nun online verfügbar.</p>',
            'content'         => '<p>Bürgerinnen und Bürger können Anträge, Ummeldungen und Genehmigungen künftig vollständig digital beantragen. Das neue Portal ist unter service.beispielstadt.de erreichbar.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],

        // ---------- Veranstaltungen ----------
        [
            'slug'            => 'stadtfest-'.$thisyear,
            'title'           => 'Stadtfest '. $thisyear .' – alle Infos',
            'group'           => 'Veranstaltungen',
            'tags'            => ['Veranstaltung'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Das diesjährige Stadtfest findet vom 20. bis 22. Juni auf dem Marktplatz statt. Drei Tage Musik, Kulinarik und Programm für die ganze Familie.</p>',
            'content'         => '<p>Auf vier Bühnen treten lokale und überregionale Künstler auf. Der Eintritt ist frei. Das vollständige Programm wird ab Mai auf der städtischen Website veröffentlicht.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'buergerversammlung-april',
            'title'           => 'Bürgerversammlung am 28. April',
            'group'           => 'Veranstaltungen',
            'tags'            => ['Verwaltung', 'Stadtentwicklung'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Die nächste Bürgerversammlung findet am 28. April um 19:00 Uhr in der Stadthalle statt. Thema: Stadtentwicklung 2030.</p>',
            'content'         => '<p>Alle Einwohnerinnen und Einwohner sind eingeladen, Fragen zu stellen und Anregungen einzubringen. Eine vorherige Anmeldung ist nicht erforderlich.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'konzert-kurpark-open-air',
            'title'           => 'Konzert im Kurpark – Open Air Saison startet',
            'group'           => 'Veranstaltungen',
            'tags'            => ['Veranstaltung'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Die Open-Air-Saison im Kurpark beginnt am 1. Mai mit einem klassischen Konzert des Stadtorchesters. Eintritt frei, Spenden willkommen.</p>',
            'content'         => '<p>Das Programm umfasst Werke von Mozart, Vivaldi und Beethoven. Beginn ist um 17:00 Uhr, bei schlechtem Wetter findet das Konzert in der Stadthalle statt.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],

        // ---------- Pressemitteilungen ----------
        [
            'slug'            => 'jahresbericht-'.$lastyear,
            'title'           => 'Jahresbericht '. $lastyear .' veröffentlicht',
            'group'           => 'Pressemitteilungen',
            'tags'            => ['Presse'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Der Jahresbericht ' . $lastyear . ' der Stadtverwaltung ist ab sofort zum Download verfügbar. Er fasst die wichtigsten Projekte und Zahlen des vergangenen Jahres zusammen.</p>',
            'content'         => '<p>Schwerpunkte des Berichts sind die Bereiche Wohnen, Mobilität und Klimaschutz. Der Bericht kann als PDF auf der städtischen Website heruntergeladen werden.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'auszeichnung-nachhaltige-stadtentwicklung',
            'title'           => 'Auszeichnung für nachhaltige Stadtentwicklung',
            'group'           => 'Pressemitteilungen',
            'tags'            => ['Presse', 'Stadtentwicklung'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Die Stadt wurde beim Bundeswettbewerb „Nachhaltige Kommunen" mit dem ersten Platz ausgezeichnet. Die Jury würdigte besonders das Klimaschutzkonzept 2040.</p>',
            'content'         => '<p>Bürgermeisterin Dr. Müller nahm den Preis in Berlin entgegen: „Diese Auszeichnung ist Ansporn und Verpflichtung zugleich. Wir werden unseren Weg konsequent fortsetzen."</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],
        [
            'slug'            => 'statement-haushaltsdebatte-'.$thisyear,
            'title'           => 'Statement zur Haushaltsdebatte '. $thisyear,
            'group'           => 'Pressemitteilungen',
            'tags'            => ['Presse'],
            'active'          => 1,
            'preview_image'   => '',
            'images'          => [],
            'teaser'          => '<p>Im Rahmen der aktuellen Haushaltsdebatte hat die Stadtspitze ein Statement zur Finanzlage und den geplanten Investitionen veröffentlicht.</p>',
            'content'         => '<p>Trotz angespannter Haushaltslage sollen die Investitionen in Bildung und Infrastruktur aufrechterhalten werden. Details werden in der Stadtratssitzung am 5. Mai vorgestellt.</p>',
            'block2'          => '',
            'published_from'  => $random_recent(),
            'published_until' => 0,
        ],

    ],
];
