<?php

declare(strict_types=1);

return [
    'title' => 'Explore',
    'subtitle' => 'Your tracks and geotagged photos on a private map.',
    'search_ph' => 'Search a place, POI, coordinates or Google-Maps link',
    'search_go' => 'Search',
    'search_result' => 'Found: :place',
    'search_not_found' => 'Nothing found for that search.',
    'search_failed' => 'Search failed. Try again.',

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
    'min_elevation' => 'Min elevation',
    'max_elevation' => 'Max elevation',
    'delete_track' => 'Delete track',
    'delete_track_confirm' => 'Delete this track permanently? This cannot be undone.',
    'rename_track' => 'Rename track',
    'edit_name' => 'Edit name',
    'cancel' => 'Cancel',
    'started' => 'Started',
    'ended' => 'Ended',
    'note' => 'Note',
    'note_placeholder' => 'Add a note…',
    'details' => 'Details',
    'back' => 'Back',
    'coupled_photos' => 'Photos on this track',
    'no_coupled_photos' => 'No photos linked yet. Use “Add photos” to attach some.',
    'add_photos' => 'Add photos',
    'search_photos' => 'Search photos…',
    'no_photos' => 'No photos in your gallery.',
    'done' => 'Done',

    // Search
    'search_tracks' => 'Search tracks…',
    'search_media' => 'Search photos…',
    'no_search_results' => 'No matches.',

    // Tour planning
    'plan_tour' => 'Plan a tour',
    'planning_hint' => 'Click the map to add waypoints.',
    'undo_point' => 'Undo last point',
    'save_route' => 'Save route',
    'route_name' => 'Route name',
    'planned_route_default' => 'Route',
    'planned_route' => 'Planned route',
    'auto_route' => 'Follow paths / Auto-route',
    'auto_route_hint' => 'Snap waypoints onto real paths via a routing service. Off draws straight lines and stays fully offline.',
    'auto_route_routing' => 'Routing…',
    'auto_route_fallback' => 'Could not fetch a route — using straight lines.',
    'auto_route_rate_limited' => 'Routing is rate-limited right now — using straight lines. Try again shortly.',
    'auto_route_too_many' => 'Too many waypoints to auto-route — using straight lines.',

    // Live planning stats
    'plan_waypoints' => 'Waypoints',
    'plan_distance' => 'Distance',
    'plan_duration' => 'Est. duration',
    'surfaces' => 'Surfaces',
    'surface' => [
        'asphalt' => 'Asphalt',
        'paved' => 'Paved',
        'unpaved' => 'Unpaved',
        'gravel' => 'Gravel',
        'ground' => 'Ground',
        'dirt' => 'Dirt',
        'grass' => 'Grass',
        'sand' => 'Sand',
        'concrete' => 'Concrete',
        'cobblestone' => 'Cobblestone',
        'paving_stones' => 'Paving stones',
        'wood' => 'Wood',
        'compacted' => 'Compacted',
        'fine_gravel' => 'Fine gravel',
        'unknown' => 'Unknown',
        'other' => 'Other',
    ],

    // Assign-to-tour modal source filter
    'filter_all' => 'All',
    'filter_imported' => 'Imported',
    'filter_planned' => 'Planned',
    'filter_recorded' => 'Recorded',

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
