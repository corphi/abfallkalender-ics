<?php

/**
 * Skript zur Umwandlung des Online-Abfallkalenders des Bremer Entsorgungsbetriebe
 * in das iCalendar-Format.
 * 
 * @author Philipp Cordes <pc@irgendware.net>
 * @license MIT
 */

/**
 * Adresse des Skripts, das die Termine kennt.
 * 
 * @var string
 */
const BASE_URL = 'http://213.168.213.236/bremereb/bify/bify.jsp?';


date_default_timezone_set('Europe/Berlin');


/**
 * VEVENT für den Termin ausgeben.
 * 
 * @param \DateTime $day
 * @param string $summary
 * @return int
 */
function printEvent(\DateTime $day, $summary)
{
	static $format = null;
	if (!$format) {
		$format = "BEGIN:VEVENT\r\n"
			. "UID:%s\r\n"
			. "DTSTART;TZID=Europe/Berlin;VALUE=DATE:%s\r\n"
			. "SUMMARY:%s\r\n"
			. "END:VEVENT\r\n";
	}

	// Bei abweichenden Wochentagen verkürzt
	$summary = str_replace('G.Sack', 'Gelber Sack', $summary);

	$slug = preg_replace('/\\W+/', '', $summary);
	return printf(
		$format,
		$slug . '#' . $day->format('Y-m-d') . '@' . $_SERVER['HTTP_HOST'],
		$day->format('Ymd'),
		$summary
	);
}


// Der Server arbeitet in ISO-8859-1; also konvertieren wir, falls nötig
if (strpos($_SERVER['QUERY_STRING'], '%C3%') !== false) {
	$_SERVER['QUERY_STRING'] = utf8_decode($_SERVER['QUERY_STRING']);
}

// Abfragen, Ausgabe nach UTF-8 konvertieren
$html = utf8_encode(file_get_contents(BASE_URL . $_SERVER['QUERY_STRING']));


// Jahre einsammeln
$posi = 0;
$jahr = 0;
while (
	// 'Start Titel Jahr' steht am Beginn jedes Jahresabschnitts im Kommentar
	($posi = strpos($html, 'Start Titel Jahr', $posi)) !== false
) {
	if ($jahr) {
		$jahre[$jahr] = $posi;
	}

	// Alle vierstelligen Zahlen direkt danach sind Jahreszahlen
	if (preg_match(
		'/\\d{4}/',
		$html,
		$treffer,
		PREG_OFFSET_CAPTURE,
		$posi
	) !== 1) {
		break;
	}

	$jahr = $treffer[0][0];
	$posi = $treffer[0][1];
}
$jahre[$jahr] = strlen($html); // Bis zum Ende gehen



// ------ Ausgabe ------

header('Content-Type: text/calendar'); // Standardmäßig UTF-8

echo "BEGIN:VCALENDAR\r\n"
	. "VERSION:2.0\r\n"
	. "PRODID:-//github.com/corphi//NONSGML Abfallkalender v1//DE\r\n";


if (array_keys($jahre) === array(0)) {
	// Es wurden keine Jahre gefunden: Dummy-Eintrag ausgeben.
	printEvent(
		new \DateTime(), // Heute
		'Keine Abfuhrtermine gefunden.'
	);
} else {
	$posi = 0;
	foreach ($jahre as $jahr => $bis) {
		while ($posi < $bis) {
			if (preg_match(
				'/(\\d+).(\\d+).&nbsp;([^<]+)/',
				$html,
				$treffer,
				PREG_OFFSET_CAPTURE,
				$posi
			) !== 1) {
				break;
			}

			printEvent(
				new \DateTime(
					"$jahr-{$treffer[2][0]}-{$treffer[1][0]}"
				),
				html_entity_decode(
					$treffer[3][0],
					ENT_COMPAT | ENT_HTML401,
					'UTF-8'
				)
			);

			$posi = $treffer[3][1];
		}
	}
}


echo "END:VCALENDAR\r\n";
