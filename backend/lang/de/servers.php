<?php

declare(strict_types=1);

return [
    'title' => 'Server',
    'subtitle' => 'Überwachung per SSH — auf dem Zielserver wird kein Agent installiert.',
    'add' => 'Server hinzufügen',
    'edit' => 'Server bearbeiten',
    'none' => 'Noch keine Server.',
    'refresh' => 'Aktualisieren',
    'refresh_all' => 'Alle aktualisieren',
    'refresh_queued' => 'Aktualisierung eingereiht.',
    'delete_confirm' => ':name aus der Überwachung entfernen? Der Server selbst bleibt unberührt.',
    'group_other' => 'Ohne Gruppe',

    // Verbindungsformular
    'name' => 'Name',
    'host' => 'Host',
    'port' => 'Port',
    'username' => 'Benutzer',
    'auth_type' => 'Anmeldung',
    'auth_key' => 'Privater Schlüssel',
    'auth_password' => 'Passwort',
    'password' => 'Passwort',
    'private_key' => 'Privater Schlüssel',
    'private_key_hint' => 'OpenSSH oder PEM. Wird verschlüsselt gespeichert und nie wieder angezeigt.',
    'passphrase' => 'Passphrase des Schlüssels',
    'secret_kept' => 'Leer lassen, um das gespeicherte Geheimnis zu behalten.',
    'group' => 'Gruppe',
    'note' => 'Notiz',
    'enabled' => 'Regelmäßig abfragen',
    'restricted_key' => 'Schlüssel ist auf dem Ziel eingeschränkt (Forced Command)',
    'restricted_key_hint' => 'Empfohlen. Der Schlüssel kann dann nur noch die Momentaufnahme ausgeben, selbst wenn er gestohlen wird.',

    // Verbindungstest / Hostschlüssel
    'test' => 'Verbindung testen',
    'testing' => 'Verbinde…',
    'test_ok' => 'Verbindung hergestellt.',
    'fingerprint' => 'Fingerabdruck des Hostschlüssels',
    'fingerprint_confirm' => 'Vor dem Speichern mit dem Zielserver vergleichen. Er wird festgeschrieben, jede spätere Verbindung muss ihn vorweisen.',
    'fingerprint_hint' => 'Auf dem Server: ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub',
    'test_first' => 'Erst die Verbindung testen — der Hostschlüssel muss vor dem Speichern bestätigt werden.',

    // Probe-Skript (Forced-Command-Einrichtung)
    'script_title' => 'Schlüssel auf dem Zielserver einschränken',
    'script_intro' => 'Dieses Skript nach /usr/local/bin/ll-facts legen (ausführbar), dann dem Schlüssel in ~/.ssh/authorized_keys voranstellen:',
    'script_authorized' => 'command="/usr/local/bin/ll-facts",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty',
    'script_outro' => 'Der Schlüssel kann danach nichts außer der Momentaufnahme ausgeben. Unprivilegierten Benutzer verwenden, niemals root, kein sudo.',

    // Status / Fakten
    'status_ok' => 'Erreichbar',
    'status_fail' => 'Nicht erreichbar',
    'status_unknown' => 'Noch nicht abgefragt',
    'checked' => 'Geprüft',
    'os' => 'Betriebssystem',
    'kernel' => 'Kernel',
    'uptime' => 'Laufzeit',
    'load' => 'Last',
    'cpu' => 'CPU',
    'memory' => 'Arbeitsspeicher',
    'swap' => 'Auslagerung',
    'disks' => 'Dateisysteme',
    'ports' => 'Offene Ports',
    'containers' => 'Container',
    'updates' => 'Offene Updates',
    'updates_unknown' => 'Unbekannt',
    'failed_units' => 'Fehlgeschlagene Dienste',
    'reboot_required' => 'Neustart erforderlich',
    'cores' => ':n Kerne',
    'history' => 'Verlauf',
    'duration' => 'Abfrage dauerte :ms ms',

    // Fehlergründe der Abfrage
    'error' => [
        'unsafe_host' => 'Verbindung zu diesem Host wird verweigert.',
        'no_host_key' => 'Der Server hat keinen verwertbaren Hostschlüssel geliefert.',
        'fingerprint_mismatch' => 'Der Hostschlüssel weicht vom festgeschriebenen ab — Verbindung verweigert.',
        'no_credentials' => 'Für diese Anmeldeart sind keine Zugangsdaten gespeichert.',
        'auth_failed' => 'Die Anmeldung wurde abgelehnt.',
        'unexpected_output' => 'Der Server hat geantwortet, aber keine Momentaufnahme geliefert. Ist der Forced Command korrekt eingerichtet?',
    ],

    'notify' => [
        'down' => ':name ist nicht erreichbar',
        'up' => ':name ist wieder erreichbar',
        'reboot' => ':name braucht einen Neustart',
        'disk' => ':name: :mount ist zu :pct % belegt',
        'units' => ':name: :count Dienst(e) fehlgeschlagen',
    ],
];
