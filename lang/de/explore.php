<?php

declare(strict_types=1);

return [
    'title' => 'Entdecken',
    'subtitle' => 'Deine Touren und verorteten Fotos auf einer privaten Karte.',

    // Views
    'view_media' => 'Medien',
    'view_tracks' => 'Touren',

    // Locked / empty states
    'locked' => 'Entsperre deinen Tresor, um die Karte zu sehen.',
    'empty_tracks' => 'Noch keine Touren. Importiere eine GPX-, KML-, KMZ-, TCX- oder FIT-Datei zum Loslegen.',
    'empty_media' => 'Keine verorteten Fotos gefunden. Importiere Touren, um Fotos automatisch anhand der Uhrzeit zu platzieren.',
    'load_failed' => 'Karte konnte nicht geladen werden.',
    'map_unavailable' => 'Die Karte ist nicht verfügbar (kein Tile-Server konfiguriert).',

    // Import
    'import' => 'Tour importieren',
    'importing' => 'Importiere…',
    'import_failed' => 'Import fehlgeschlagen',
    'import_done' => 'Tour importiert',
    'kmz_no_kml' => 'Keine KML-Datei im KMZ-Archiv gefunden.',

    // Track list / stats
    'tracks_heading' => 'Touren',
    'distance' => 'Distanz',
    'duration' => 'Dauer',
    'moving_time' => 'Bewegungszeit',
    'ascent' => 'Aufstieg',
    'descent' => 'Abstieg',
    'max_speed' => 'Max. Tempo',
    'avg_speed' => 'Ø Tempo',
    'points' => 'Punkte',
    'elevation' => 'Höhe',
    'elevation_profile' => 'Höhenprofil',
    'no_elevation' => 'Keine Höhendaten',
    'delete_track' => 'Tour löschen',
    'delete_track_confirm' => 'Diese Tour endgültig löschen? Das kann nicht rückgängig gemacht werden.',
    'rename_track' => 'Tour umbenennen',
    'started' => 'Start',
    'ended' => 'Ende',

    // Coupling
    'coupling' => 'Foto-Zuordnung',
    'match_photos' => 'Fotos den Touren zuordnen',
    'matching' => 'Ordne zu…',
    'matched' => ':n Foto(s) zugeordnet',
    'source_exif' => 'Aus Foto-GPS',
    'source_interpolated' => 'Aus Tour (nach Uhrzeit)',
    'source_manual' => 'Manuell gesetzt',
    'source_none' => 'Nicht platziert',
    'clear_coupling' => 'Zuordnung entfernen',
    'assign_to_track' => 'Tour zuweisen',

    // Settings
    'settings' => 'Zuordnungs-Einstellungen',
    'time_tolerance' => 'Zeit-Toleranz (Sekunden)',
    'distance_tolerance' => 'Distanz-Toleranz (Meter)',
    'settings_hint' => 'Wie nah ein Foto zeitlich und räumlich sein muss, um zu einer Tour zu zählen.',
    'save' => 'Speichern',
    'close' => 'Schließen',

    // Units
    'unit_km' => 'km',
    'unit_m' => 'm',
    'unit_kmh' => 'km/h',
];
