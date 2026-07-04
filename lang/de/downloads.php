<?php

declare(strict_types=1);

return [
    'heading' => 'Downloads',
    'subheading' => 'Deine Export-Archive. Werden im Hintergrund erstellt und 7 Tage aufbewahrt.',
    'empty' => 'Noch keine Downloads. Exportiere Fotos oder Dateien, dann erscheinen sie hier.',
    'refresh' => 'Aktualisieren',
    'settings' => 'Download-Einstellungen',
    'queued_toast' => 'Export gestartet – erscheint unter Downloads, sobald er bereit ist.',

    'title' => [
        'gallery' => '{1}:count Foto|[2,*]:count Fotos',
        'files' => '{1}:count Element|[2,*]:count Elemente',
    ],

    'status' => [
        'queued' => 'In Warteschlange',
        'processing' => 'Wird vorbereitet …',
        'ready' => 'Bereit',
        'failed' => 'Fehlgeschlagen',
    ],

    'source' => [
        'gallery' => 'Galerie',
        'files' => 'Dateien',
    ],
    'variant' => [
        'original' => 'Original',
        'edited' => 'Bearbeitet',
    ],

    'parts' => 'Teile',
    'part' => 'Teil :n',
    'size' => 'Größe',
    'created' => 'Erstellt',
    'expires' => 'Läuft ab :when',
    'download' => 'Herunterladen',
    'download_all' => 'Alle Teile herunterladen',
    'delete' => 'Löschen',
    'delete_selected' => 'Auswahl löschen',
    'select_all' => 'Alle auswählen',
    'selected' => ':count ausgewählt',
    'confirm_delete' => 'Ausgewählte Downloads löschen? Die Dateien werden sofort entfernt.',
    'building_hint' => 'Dieser Export wird noch vorbereitet – er ist gleich bereit.',

    'notify' => [
        'ready_title' => 'Download bereit',
        'ready_body' => 'Dein Export „:title" steht zum Herunterladen bereit.',
        'failed_title' => 'Export fehlgeschlagen',
        'failed_body' => 'Dein Export „:title" konnte nicht erstellt werden.',
    ],

    'settings_page' => [
        'heading' => 'Downloads & Exporte',
        'subheading' => 'Wie Export-Archive erstellt werden und wie du erfährst, dass sie bereit sind.',
        'zip_heading' => 'Maximale Zip-Teilgröße',
        'zip_hint' => 'Größere Exporte werden in mehrere Zip-Teile aufgeteilt, damit keine Datei dies überschreitet. 0 = ein einziges Zip (kein Limit).',
        'files_max' => 'Datei-Export – max. MB pro Zip',
        'gallery_max' => 'Galerie-Export – max. MB pro Zip',
        'notify_heading' => 'Benachrichtigen, wenn ein Download bereit ist',
        'notify_hint' => 'NTFY, Mail und Webhook nutzen die unter Benachrichtigungen hinterlegten Zugangsdaten.',
        'notify_desktop' => 'In-App-Glocke',
        'notify_ntfy' => 'NTFY-Push',
        'notify_mail' => 'E-Mail',
        'notify_webhook' => 'Webhook',
        'save' => 'Speichern',
    ],
];
