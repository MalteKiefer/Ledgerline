<?php

declare(strict_types=1);

return [
    'heading' => 'Downloads',
    'subheading' => 'Deine Export-Archive. Werden im Hintergrund erstellt und 7 Tage aufbewahrt.',
    'empty' => 'Noch keine Downloads. Exportiere Fotos oder Dateien, dann erscheinen sie hier.',
    'refresh' => 'Aktualisieren',
    'settings' => 'Download-Einstellungen',
    'queued_toast' => 'Export gestartet – erscheint unter Downloads, sobald er bereit ist.',
    'new_ready' => 'Neue Downloads bereit',

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

    'part' => 'Teil :n',
    'expires' => 'Läuft ab :when',
    'download' => 'Herunterladen',
    'delete' => 'Löschen',
    'delete_selected' => 'Auswahl löschen',
    'selected' => ':count ausgewählt',
    'confirm_delete' => 'Ausgewählte Downloads löschen? Die Dateien werden sofort entfernt.',
    'building_hint' => 'Dieser Export wird noch vorbereitet – er ist gleich bereit.',

    'error' => [
        'too_many' => 'Es werden bereits :max Exporte vorbereitet. Warte, bis einer fertig ist, bevor du einen weiteren startest.',
        'stuck' => 'Die Vorbereitung wurde unterbrochen und nicht abgeschlossen. Bitte starte den Export erneut.',
    ],

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
