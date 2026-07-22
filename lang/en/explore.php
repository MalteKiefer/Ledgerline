<?php

declare(strict_types=1);

return [
    'title' => 'Explore',
    'subtitle' => 'Your tracks and geotagged photos on a private map.',

    // Views
    'view_media' => 'Media',
    'view_tracks' => 'Tracks',

    // Locked / empty states
    'locked' => 'Unlock your vault to view the map.',
    'empty_tracks' => 'No tracks yet. Import a GPX, KML, KMZ, TCX or FIT file to get started.',
    'empty_media' => 'No geotagged photos found. Import tracks to place time-stamped photos automatically.',
    'load_failed' => 'Could not load the map.',
    'map_unavailable' => 'The map is not available (no tile server configured).',

    // Import
    'import' => 'Import track',
    'importing' => 'Importing…',
    'import_failed' => 'Import failed',
    'import_done' => 'Track imported',
    'kmz_no_kml' => 'No KML found inside the KMZ archive.',

    // Track list / stats
    'tracks_heading' => 'Tracks',
    'distance' => 'Distance',
    'duration' => 'Duration',
    'moving_time' => 'Moving time',
    'ascent' => 'Ascent',
    'descent' => 'Descent',
    'max_speed' => 'Max speed',
    'avg_speed' => 'Avg speed',
    'points' => 'Points',
    'elevation' => 'Elevation',
    'elevation_profile' => 'Elevation profile',
    'no_elevation' => 'No elevation data',
    'delete_track' => 'Delete track',
    'delete_track_confirm' => 'Delete this track permanently? This cannot be undone.',
    'rename_track' => 'Rename track',
    'started' => 'Started',
    'ended' => 'Ended',

    // Coupling
    'coupling' => 'Photo matching',
    'match_photos' => 'Match photos to tracks',
    'matching' => 'Matching…',
    'matched' => ':n photo(s) matched',
    'source_exif' => 'From photo GPS',
    'source_interpolated' => 'From track (by time)',
    'source_manual' => 'Set manually',
    'source_none' => 'Unplaced',
    'clear_coupling' => 'Clear match',
    'assign_to_track' => 'Assign to track',

    // Settings
    'settings' => 'Matching settings',
    'time_tolerance' => 'Time tolerance (seconds)',
    'distance_tolerance' => 'Distance tolerance (metres)',
    'settings_hint' => 'How close in time and distance a photo must be to count as part of a track.',
    'save' => 'Save',
    'close' => 'Close',

    // Units
    'unit_km' => 'km',
    'unit_m' => 'm',
    'unit_kmh' => 'km/h',
];
