<?php

declare(strict_types=1);

return [
    'title' => 'Entdecken',
    'subtitle' => 'Deine Touren und verorteten Fotos auf einer privaten Karte.',
    'search_ph' => 'Ort, POI, Koordinaten oder Google-Maps-Link suchen',
    'search_go' => 'Suchen',
    'search_result' => 'Gefunden: :place',
    'search_not_found' => 'Nichts zu dieser Suche gefunden.',
    'search_failed' => 'Suche fehlgeschlagen. Erneut versuchen.',

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
    'min_elevation' => 'Min. Höhe',
    'max_elevation' => 'Max. Höhe',
    'delete_track' => 'Tour löschen',
    'delete_track_confirm' => 'Diese Tour endgültig löschen? Das kann nicht rückgängig gemacht werden.',
    'rename_track' => 'Tour umbenennen',
    'edit_name' => 'Name bearbeiten',
    'cancel' => 'Abbrechen',
    'started' => 'Start',
    'ended' => 'Ende',
    'note' => 'Notiz',
    'note_placeholder' => 'Notiz hinzufügen…',
    'details' => 'Details',
    'back' => 'Zurück',
    'coupled_photos' => 'Fotos auf dieser Tour',
    'no_coupled_photos' => 'Noch keine Fotos verknüpft. Über „Fotos hinzufügen" anhängen.',
    'add_photos' => 'Fotos hinzufügen',
    'search_photos' => 'Fotos suchen…',
    'no_photos' => 'Keine Fotos in deiner Galerie.',
    'done' => 'Fertig',

    // Search
    'search_tracks' => 'Touren suchen…',
    'search_media' => 'Fotos suchen…',
    'no_search_results' => 'Keine Treffer.',

    // Tour planning
    'plan_tour' => 'Tour planen',
    'planning_hint' => 'Klicke auf die Karte, um Wegpunkte hinzuzufügen.',
    'undo_point' => 'Letzten Punkt zurücknehmen',
    'save_route' => 'Route speichern',
    'route_name' => 'Routenname',
    'planned_route_default' => 'Route',
    'planned_route' => 'Geplante Route',
    'auto_route' => 'Wegen folgen / Auto-Route',
    'auto_route_hint' => 'Wegpunkte über einen Routing-Dienst an echte Wege anlegen. Aus zeichnet gerade Linien und bleibt vollständig offline.',
    'auto_route_routing' => 'Route wird berechnet…',
    'auto_route_fallback' => 'Route konnte nicht geladen werden — gerade Linien werden verwendet.',
    'auto_route_rate_limited' => 'Routing ist gerade limitiert — gerade Linien werden verwendet. Gleich nochmal versuchen.',
    'auto_route_too_many' => 'Zu viele Wegpunkte für Auto-Routing — gerade Linien werden verwendet.',

    // Live planning stats
    'plan_waypoints' => 'Wegpunkte',
    'plan_distance' => 'Distanz',
    'plan_duration' => 'Gesch. Dauer',
    'surfaces' => 'Untergrund',
    'surface' => [
        'asphalt' => 'Asphalt',
        'paved' => 'Befestigt',
        'unpaved' => 'Unbefestigt',
        'gravel' => 'Schotter',
        'ground' => 'Naturboden',
        'dirt' => 'Erde',
        'grass' => 'Gras',
        'sand' => 'Sand',
        'concrete' => 'Beton',
        'cobblestone' => 'Kopfsteinpflaster',
        'paving_stones' => 'Pflastersteine',
        'wood' => 'Holz',
        'compacted' => 'Verdichtet',
        'fine_gravel' => 'Feinschotter',
        'unknown' => 'Unbekannt',
        'other' => 'Sonstige',
    ],

    // Assign-to-tour modal source filter
    'filter_all' => 'Alle',
    'filter_imported' => 'Importiert',
    'filter_planned' => 'Geplant',
    'filter_recorded' => 'Aufgezeichnet',

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
