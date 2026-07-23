// Bundled i18n catalog for the Ledgerline browser extension.
// Does NOT use chrome.i18n _locales — fully self-contained.
//
// Key naming convention: <screen>.<key>
// Placeholders use :name syntax (e.g. :domain, :label, :rpId).

const MESSAGES = {
    en: {
        // --- pair screen ---
        'pair.hint': 'Open your Ledgerline profile, start a command-line/extension pairing and copy the code. Approve the device there after connecting.',
        'pair.server_url': 'Server URL',
        'pair.server_ph': 'https://home.example.com',
        'pair.code_label': 'Pairing code',
        'pair.code_ph': 'paste code',
        'pair.connect': 'Connect',
        'pair.waiting': 'Waiting for approval…',
        'pair.error': 'Pairing failed or timed out.',

        // --- unlock screen ---
        'unlock.unpair': 'Unpair',
        'unlock.hint': 'Enter your vault passphrase to unlock. It stays in this browser session only — never sent to the server.',
        'unlock.pass_label': 'Vault passphrase',
        'unlock.action': 'Unlock',
        'unlock.loading': 'Unlocking…',
        'unlock.wrong': 'Wrong passphrase.',

        // --- main list header buttons ---
        'list.new_login': 'New login',
        'list.generate': 'Generate password',
        'list.refresh': 'Refresh from server',
        'list.lock': 'Lock',

        // --- view switcher ---
        'nav.passwords': 'Passwords',
        'nav.bookmarks': 'Bookmarks',

        // --- list panel ---
        'list.search_ph': 'Search…',
        'list.all_items': 'All items (:count)',
        'list.no_folder': 'No folder (:count)',
        'list.nothing_found': 'Nothing found',
        'list.show_all': 'Show all items (:count)',

        // --- item type labels ---
        'type.login': 'Login',
        'type.password': 'Password',
        'type.card': 'Card',
        'type.wifi': 'Wi-Fi',
        'type.license': 'License',
        'type.server': 'Server',
        'type.passkey': 'Passkey',
        'type.identity': 'Identity',
        'type.secure_note': 'Secure note',

        // --- detail view ---
        'detail.copy': 'Copy',
        'detail.reveal': 'Reveal',
        'detail.open': 'Open',
        'detail.edit': 'Edit',
        'detail.scan2fa': 'Scan a 2FA QR code',
        'detail.trash': 'Move to trash',
        'detail.fill': 'Fill on this page',
        'detail.fill_card': 'Fill card on this page',
        'detail.empty': 'Select an entry to view its details.',
        'detail.shared_badge': '(shared)',

        // field labels
        'field.cardholder': 'Cardholder',
        'field.card_number': 'Card number',
        'field.expiry': 'Expiry',
        'field.cvv': 'CVV',
        'field.username': 'Username',
        'field.password': 'Password',
        'field.totp': 'One-time code',
        'field.website': 'Website',
        'field.note': 'Note',
        'field.passkey': 'Passkey',

        // 2FA hint
        'detail.tfa_hint': 'This website offers two-factor authentication. Add a one-time code to this login.',
        'detail.tfa_how': 'How to enable it',

        // passkey remove
        'detail.remove_passkey_title': 'Remove passkey',
        'detail.remove_passkey_confirm': 'Remove :label from this login?',
        'detail.remove_passkey_locked': 'Unlock the vault first.',
        'detail.remove_passkey_error': 'Could not remove passkey.',

        // trash
        'detail.trash_confirm': 'Move this entry to the trash?',
        'detail.trash_locked': 'Unlock the vault first.',
        'detail.trash_error': 'Could not delete.',

        // 2FA scan
        'detail.scan_notfound': 'No 2FA QR code found on the current tab. Make sure the QR is visible, then try again.',
        'detail.scan_locked': 'Unlock the vault first.',
        'detail.scan_error': 'Could not save the code.',
        'detail.scan_capture_error': 'Could not capture the tab to scan a QR code.',

        // --- edit view ---
        'edit.username': 'Username',
        'edit.password': 'Password',
        'edit.website_url': 'Website URL',
        'edit.totp_secret': 'TOTP secret',
        'edit.totp_keep': ' (leave blank to keep existing)',
        'edit.note': 'Note',
        'edit.save': 'Save',
        'edit.cancel': 'Cancel',
        'edit.saving': 'Saving…',
        'edit.locked': 'Unlock the vault first.',
        'edit.error': 'Could not save.',

        // --- new login ---
        'new.back': 'Back',
        'new.title': 'Title',
        'new.username': 'Username',
        'new.password': 'Password',
        'new.generate': 'Generate',
        'new.copy': 'Copy',
        'new.website': 'Website',
        'new.save': 'Save login',
        'new.saving': 'Saving…',
        'new.validation': 'Enter at least a title or website.',
        'new.locked': 'Unlock the vault first.',
        'new.error': 'Could not save.',

        // --- password generator ---
        'gen.back': 'Back',
        'gen.regenerate': 'Regenerate',
        'gen.copy': 'Copy',
        'gen.chars': 'Characters',
        'gen.words': 'Memorable words',
        'gen.length_label': 'Length: ',
        'gen.words_label': 'Words: ',
        'gen.upper': 'A–Z',
        'gen.lower': 'a–z',
        'gen.digits': '0–9',
        'gen.symbols': '!@#',
        'gen.similar': 'Allow look-alike characters',
        'gen.sep_space': 'Space',
        'gen.sep_none': 'None',
        'gen.capitalize': 'Capitalize',
        'gen.add_number': 'Add number',
        'gen.copy_clipboard': 'Copy to clipboard',

        // --- bookmarks ---
        'bm.save_page': 'Save page',
        'bm.new_folder': 'New folder here',
        'bm.search_ph': 'Search all bookmarks…',
        'bm.browse': 'Browse',
        'bm.import_browser': 'Import from browser',
        'bm.importing': 'Importing…',
        'bm.import_done': ':added imported, :skipped skipped',
        'bm.import_none': 'No bookmarks found in the browser.',
        'bm.import_error': 'Import failed.',
        'bm.import_locked': 'Unlock the vault first.',
        'bm.favorites': 'Favorites',
        'bm.read_later': 'Read later',
        'bm.all_crumb': 'All',
        'bm.item_count_one': ':count item',
        'bm.item_count_other': ':count items',
        'bm.rename_folder': 'Rename',
        'bm.delete_folder': 'Delete folder',
        'bm.rename_prompt': 'Rename folder',
        'bm.rename_error': 'Could not rename folder.',
        'bm.delete_confirm': 'Delete folder ":name"? Bookmarks inside move to All; subfolders move up one level.',
        'bm.delete_error': 'Could not delete folder.',
        'bm.new_folder_prompt': 'New folder name',
        'bm.create_error': 'Could not create folder.',
        'bm.edit': 'Edit',
        'bm.trash': 'Move to trash',
        'bm.trash_confirm': 'Move this bookmark to trash?',
        'bm.trash_error': 'Could not delete.',
        'bm.empty_folder': 'This folder is empty',
        'bm.empty_search': 'No bookmarks found',
        'bm.untitled': 'Untitled',
        'bm.unsafe_url': 'Unsafe URL — edit the bookmark to fix it.',

        // bookmark edit / save page form
        'bm.title': 'Title',
        'bm.url': 'URL',
        'bm.description': 'Description',
        'bm.tags': 'Tags (comma-separated)',
        'bm.folder': 'Folder',
        'bm.no_folder': 'All (no folder)',
        'bm.favorite': 'Favorite',
        'bm.read_later_check': 'Read later',
        'bm.save': 'Save',
        'bm.cancel': 'Cancel',
        'bm.saving': 'Saving…',
        'bm.save_error': 'Could not save.',
        'bm.save_page_heading': 'Save current page',
        'bm.save_bookmark': 'Save bookmark',

        // --- content.js notifications ---
        'notify.2fa_copied': '2FA code copied',
        'notify.pw_filled': 'Password filled & copied',
        'notify.saved': 'Saved to Ledgerline',
        'notify.save_locked': 'Unlock Ledgerline to save',
        'notify.save_error': 'Could not save',
        'notify.updated': 'Password updated in Ledgerline',
        'notify.update_locked': 'Unlock Ledgerline to update',
        'notify.update_error': 'Could not update',

        // --- content.js save/update prompts ---
        'save.heading': 'Save this login to Ledgerline?',
        'save.title_label': 'Title',
        'save.username_line': 'Username: :username',
        'save.no_username': 'No username detected',
        'save.not_now': 'Not now',
        'save.save': 'Save',
        'save.saving': 'Saving…',

        'update.heading': 'Update password for :domain?',
        'update.no_username': 'No username',
        'update.not_now': 'Not now',
        'update.update': 'Update',
        'update.updating': 'Updating…',

        // --- content.js passkey prompts ---
        'passkey.sign_in_prompt': 'Sign in to :rpId?',
        'passkey.cancel': 'Cancel',
        'passkey.sign_in': 'Sign in',
        'passkey.save_heading': 'Save passkey for :rpId?',
        'passkey.user_label': 'User: :userName',
        'passkey.attach_section': 'Add to existing login',
        'passkey.new_entry': 'New passkey entry',

        // --- content.js generator panel ---
        'gen.fill_password': 'Fill password',
        'gen.suggest_title': 'Suggest a password…',
        'gen.suggest_sub': 'Configurable · shown before filling',

        // --- content.js in-field badge ---
        'badge.title': 'Ledgerline — fill',
    },

    de: {
        // --- pair screen ---
        'pair.hint': 'Öffne dein Ledgerline-Profil, starte eine Befehlszeilen-/Erweiterungs-Kopplung und kopiere den Code. Bestätige das Gerät dort nach dem Verbinden.',
        'pair.server_url': 'Server-URL',
        'pair.server_ph': 'https://home.example.com',
        'pair.code_label': 'Kopplungscode',
        'pair.code_ph': 'Code einfügen',
        'pair.connect': 'Verbinden',
        'pair.waiting': 'Warte auf Bestätigung…',
        'pair.error': 'Kopplung fehlgeschlagen oder abgelaufen.',

        // --- unlock screen ---
        'unlock.unpair': 'Entkoppeln',
        'unlock.hint': 'Tresor-Passphrase eingeben zum Entsperren. Sie verbleibt nur in dieser Browser-Sitzung — wird nie an den Server gesendet.',
        'unlock.pass_label': 'Tresor-Passphrase',
        'unlock.action': 'Entsperren',
        'unlock.loading': 'Wird entsperrt…',
        'unlock.wrong': 'Falsche Passphrase.',

        // --- main list header buttons ---
        'list.new_login': 'Neuer Login',
        'list.generate': 'Passwort generieren',
        'list.refresh': 'Vom Server aktualisieren',
        'list.lock': 'Sperren',

        // --- view switcher ---
        'nav.passwords': 'Passwörter',
        'nav.bookmarks': 'Lesezeichen',

        // --- list panel ---
        'list.search_ph': 'Suchen…',
        'list.all_items': 'Alle Einträge (:count)',
        'list.no_folder': 'Kein Tresor (:count)',
        'list.nothing_found': 'Nichts gefunden',
        'list.show_all': 'Alle Einträge anzeigen (:count)',

        // --- item type labels ---
        'type.login': 'Login',
        'type.password': 'Passwort',
        'type.card': 'Karte',
        'type.wifi': 'WLAN',
        'type.license': 'Lizenz',
        'type.server': 'Server',
        'type.passkey': 'Passkey',
        'type.identity': 'Identität',
        'type.secure_note': 'Sichere Notiz',

        // --- detail view ---
        'detail.copy': 'Kopieren',
        'detail.reveal': 'Anzeigen',
        'detail.open': 'Öffnen',
        'detail.edit': 'Bearbeiten',
        'detail.scan2fa': '2FA-QR-Code scannen',
        'detail.trash': 'In den Papierkorb',
        'detail.fill': 'Auf dieser Seite ausfüllen',
        'detail.fill_card': 'Karte auf dieser Seite ausfüllen',
        'detail.empty': 'Eintrag auswählen, um Details anzuzeigen.',
        'detail.shared_badge': '(geteilt)',

        // field labels
        'field.cardholder': 'Karteninhaber',
        'field.card_number': 'Kartennummer',
        'field.expiry': 'Ablaufdatum',
        'field.cvv': 'CVV',
        'field.username': 'Benutzername',
        'field.password': 'Passwort',
        'field.totp': 'Einmalcode',
        'field.website': 'Website',
        'field.note': 'Notiz',
        'field.passkey': 'Passkey',

        // 2FA hint
        'detail.tfa_hint': 'Diese Website bietet Zwei-Faktor-Authentifizierung. Füge diesem Login einen Einmalcode hinzu.',
        'detail.tfa_how': 'So wird es aktiviert',

        // passkey remove
        'detail.remove_passkey_title': 'Passkey entfernen',
        'detail.remove_passkey_confirm': ':label von diesem Login entfernen?',
        'detail.remove_passkey_locked': 'Tresor zuerst entsperren.',
        'detail.remove_passkey_error': 'Passkey konnte nicht entfernt werden.',

        // trash
        'detail.trash_confirm': 'Diesen Eintrag in den Papierkorb verschieben?',
        'detail.trash_locked': 'Tresor zuerst entsperren.',
        'detail.trash_error': 'Löschen nicht möglich.',

        // 2FA scan
        'detail.scan_notfound': 'Kein 2FA-QR-Code auf dem aktuellen Tab gefunden. QR sichtbar machen und erneut versuchen.',
        'detail.scan_locked': 'Tresor zuerst entsperren.',
        'detail.scan_error': 'Code konnte nicht gespeichert werden.',
        'detail.scan_capture_error': 'Tab konnte zum QR-Code-Scannen nicht aufgenommen werden.',

        // --- edit view ---
        'edit.username': 'Benutzername',
        'edit.password': 'Passwort',
        'edit.website_url': 'Website-URL',
        'edit.totp_secret': 'TOTP-Geheimnis',
        'edit.totp_keep': ' (leer lassen, um bestehendes zu behalten)',
        'edit.note': 'Notiz',
        'edit.save': 'Speichern',
        'edit.cancel': 'Abbrechen',
        'edit.saving': 'Wird gespeichert…',
        'edit.locked': 'Tresor zuerst entsperren.',
        'edit.error': 'Speichern nicht möglich.',

        // --- new login ---
        'new.back': 'Zurück',
        'new.title': 'Titel',
        'new.username': 'Benutzername',
        'new.password': 'Passwort',
        'new.generate': 'Generieren',
        'new.copy': 'Kopieren',
        'new.website': 'Website',
        'new.save': 'Login speichern',
        'new.saving': 'Wird gespeichert…',
        'new.validation': 'Mindestens Titel oder Website eingeben.',
        'new.locked': 'Tresor zuerst entsperren.',
        'new.error': 'Speichern nicht möglich.',

        // --- password generator ---
        'gen.back': 'Zurück',
        'gen.regenerate': 'Neu generieren',
        'gen.copy': 'Kopieren',
        'gen.chars': 'Zeichen',
        'gen.words': 'Merkbare Wörter',
        'gen.length_label': 'Länge: ',
        'gen.words_label': 'Wörter: ',
        'gen.upper': 'A–Z',
        'gen.lower': 'a–z',
        'gen.digits': '0–9',
        'gen.symbols': '!@#',
        'gen.similar': 'Ähnliche Zeichen erlauben',
        'gen.sep_space': 'Leerzeichen',
        'gen.sep_none': 'Keines',
        'gen.capitalize': 'Großschreiben',
        'gen.add_number': 'Zahl hinzufügen',
        'gen.copy_clipboard': 'In Zwischenablage kopieren',

        // --- bookmarks ---
        'bm.save_page': 'Seite speichern',
        'bm.new_folder': 'Neuer Ordner hier',
        'bm.search_ph': 'Alle Lesezeichen durchsuchen…',
        'bm.browse': 'Durchsuchen',
        'bm.import_browser': 'Aus Browser importieren',
        'bm.importing': 'Importiere…',
        'bm.import_done': ':added importiert, :skipped übersprungen',
        'bm.import_none': 'Keine Lesezeichen im Browser gefunden.',
        'bm.import_error': 'Import fehlgeschlagen.',
        'bm.import_locked': 'Zuerst den Tresor entsperren.',
        'bm.favorites': 'Favoriten',
        'bm.read_later': 'Später lesen',
        'bm.all_crumb': 'Alle',
        'bm.item_count_one': ':count Eintrag',
        'bm.item_count_other': ':count Einträge',
        'bm.rename_folder': 'Umbenennen',
        'bm.delete_folder': 'Ordner löschen',
        'bm.rename_prompt': 'Ordner umbenennen',
        'bm.rename_error': 'Ordner konnte nicht umbenannt werden.',
        'bm.delete_confirm': 'Ordner \":name\" löschen? Lesezeichen darin werden nach \"Alle\" verschoben; Unterordner eine Ebene nach oben.',
        'bm.delete_error': 'Ordner konnte nicht gelöscht werden.',
        'bm.new_folder_prompt': 'Name des neuen Ordners',
        'bm.create_error': 'Ordner konnte nicht erstellt werden.',
        'bm.edit': 'Bearbeiten',
        'bm.trash': 'In den Papierkorb',
        'bm.trash_confirm': 'Dieses Lesezeichen in den Papierkorb verschieben?',
        'bm.trash_error': 'Löschen nicht möglich.',
        'bm.empty_folder': 'Dieser Ordner ist leer',
        'bm.empty_search': 'Keine Lesezeichen gefunden',
        'bm.untitled': 'Ohne Titel',
        'bm.unsafe_url': 'Unsichere URL — Lesezeichen bearbeiten, um sie zu korrigieren.',

        // bookmark edit / save page form
        'bm.title': 'Titel',
        'bm.url': 'URL',
        'bm.description': 'Beschreibung',
        'bm.tags': 'Schlagwörter (kommagetrennt)',
        'bm.folder': 'Ordner',
        'bm.no_folder': 'Alle (kein Ordner)',
        'bm.favorite': 'Favorit',
        'bm.read_later_check': 'Später lesen',
        'bm.save': 'Speichern',
        'bm.cancel': 'Abbrechen',
        'bm.saving': 'Wird gespeichert…',
        'bm.save_error': 'Speichern nicht möglich.',
        'bm.save_page_heading': 'Aktuelle Seite speichern',
        'bm.save_bookmark': 'Lesezeichen speichern',

        // --- content.js notifications ---
        'notify.2fa_copied': '2FA-Code kopiert',
        'notify.pw_filled': 'Passwort eingefügt & kopiert',
        'notify.saved': 'In Ledgerline gespeichert',
        'notify.save_locked': 'Ledgerline entsperren, um zu speichern',
        'notify.save_error': 'Speichern nicht möglich',
        'notify.updated': 'Passwort in Ledgerline aktualisiert',
        'notify.update_locked': 'Ledgerline entsperren, um zu aktualisieren',
        'notify.update_error': 'Aktualisieren nicht möglich',

        // --- content.js save/update prompts ---
        'save.heading': 'Diesen Login in Ledgerline speichern?',
        'save.title_label': 'Titel',
        'save.username_line': 'Benutzername: :username',
        'save.no_username': 'Kein Benutzername erkannt',
        'save.not_now': 'Nicht jetzt',
        'save.save': 'Speichern',
        'save.saving': 'Wird gespeichert…',

        'update.heading': 'Passwort für :domain aktualisieren?',
        'update.no_username': 'Kein Benutzername',
        'update.not_now': 'Nicht jetzt',
        'update.update': 'Aktualisieren',
        'update.updating': 'Wird aktualisiert…',

        // --- content.js passkey prompts ---
        'passkey.sign_in_prompt': 'Bei :rpId anmelden?',
        'passkey.cancel': 'Abbrechen',
        'passkey.sign_in': 'Anmelden',
        'passkey.save_heading': 'Passkey für :rpId speichern?',
        'passkey.user_label': 'Benutzer: :userName',
        'passkey.attach_section': 'Zu bestehendem Login hinzufügen',
        'passkey.new_entry': 'Neuer Passkey-Eintrag',

        // --- content.js generator panel ---
        'gen.fill_password': 'Passwort einfügen',
        'gen.suggest_title': 'Passwort vorschlagen…',
        'gen.suggest_sub': 'Konfigurierbar · wird vor dem Einfügen angezeigt',

        // --- content.js in-field badge ---
        'badge.title': 'Ledgerline — ausfüllen',
    },

    ru: {
        // --- pair screen ---
        'pair.hint': 'Откройте профиль Ledgerline, запустите сопряжение через командную строку/расширение и скопируйте код. Подтвердите устройство там после подключения.',
        'pair.server_url': 'URL сервера',
        'pair.server_ph': 'https://home.example.com',
        'pair.code_label': 'Код сопряжения',
        'pair.code_ph': 'вставьте код',
        'pair.connect': 'Подключить',
        'pair.waiting': 'Ожидание подтверждения…',
        'pair.error': 'Сопряжение не удалось или истекло время ожидания.',

        // --- unlock screen ---
        'unlock.unpair': 'Отсоединить',
        'unlock.hint': 'Введите кодовую фразу хранилища для разблокировки. Она остаётся только в этой сессии браузера — на сервер не отправляется.',
        'unlock.pass_label': 'Кодовая фраза хранилища',
        'unlock.action': 'Разблокировать',
        'unlock.loading': 'Разблокировка…',
        'unlock.wrong': 'Неверная кодовая фраза.',

        // --- main list header buttons ---
        'list.new_login': 'Новый вход',
        'list.generate': 'Создать пароль',
        'list.refresh': 'Обновить с сервера',
        'list.lock': 'Заблокировать',

        // --- view switcher ---
        'nav.passwords': 'Пароли',
        'nav.bookmarks': 'Закладки',

        // --- list panel ---
        'list.search_ph': 'Поиск…',
        'list.all_items': 'Все записи (:count)',
        'list.no_folder': 'Без папки (:count)',
        'list.nothing_found': 'Ничего не найдено',
        'list.show_all': 'Показать все записи (:count)',

        // --- item type labels ---
        'type.login': 'Вход',
        'type.password': 'Пароль',
        'type.card': 'Карта',
        'type.wifi': 'Wi-Fi',
        'type.license': 'Лицензия',
        'type.server': 'Сервер',
        'type.passkey': 'Passkey',
        'type.identity': 'Личность',
        'type.secure_note': 'Защищённая заметка',

        // --- detail view ---
        'detail.copy': 'Копировать',
        'detail.reveal': 'Показать',
        'detail.open': 'Открыть',
        'detail.edit': 'Изменить',
        'detail.scan2fa': 'Сканировать QR-код 2FA',
        'detail.trash': 'В корзину',
        'detail.fill': 'Заполнить на этой странице',
        'detail.fill_card': 'Заполнить карту на этой странице',
        'detail.empty': 'Выберите запись для просмотра.',
        'detail.shared_badge': '(общий)',

        // field labels
        'field.cardholder': 'Держатель карты',
        'field.card_number': 'Номер карты',
        'field.expiry': 'Срок действия',
        'field.cvv': 'CVV',
        'field.username': 'Имя пользователя',
        'field.password': 'Пароль',
        'field.totp': 'Одноразовый код',
        'field.website': 'Сайт',
        'field.note': 'Заметка',
        'field.passkey': 'Passkey',

        // 2FA hint
        'detail.tfa_hint': 'Этот сайт поддерживает двухфакторную аутентификацию. Добавьте одноразовый код к этому входу.',
        'detail.tfa_how': 'Как включить',

        // passkey remove
        'detail.remove_passkey_title': 'Удалить passkey',
        'detail.remove_passkey_confirm': 'Удалить :label из этого входа?',
        'detail.remove_passkey_locked': 'Сначала разблокируйте хранилище.',
        'detail.remove_passkey_error': 'Не удалось удалить passkey.',

        // trash
        'detail.trash_confirm': 'Переместить эту запись в корзину?',
        'detail.trash_locked': 'Сначала разблокируйте хранилище.',
        'detail.trash_error': 'Не удалось удалить.',

        // 2FA scan
        'detail.scan_notfound': 'QR-код 2FA на текущей вкладке не найден. Убедитесь, что QR виден, и попробуйте снова.',
        'detail.scan_locked': 'Сначала разблокируйте хранилище.',
        'detail.scan_error': 'Не удалось сохранить код.',
        'detail.scan_capture_error': 'Не удалось сделать снимок вкладки для сканирования QR-кода.',

        // --- edit view ---
        'edit.username': 'Имя пользователя',
        'edit.password': 'Пароль',
        'edit.website_url': 'URL сайта',
        'edit.totp_secret': 'Секрет TOTP',
        'edit.totp_keep': ' (оставьте пустым, чтобы сохранить текущий)',
        'edit.note': 'Заметка',
        'edit.save': 'Сохранить',
        'edit.cancel': 'Отмена',
        'edit.saving': 'Сохранение…',
        'edit.locked': 'Сначала разблокируйте хранилище.',
        'edit.error': 'Не удалось сохранить.',

        // --- new login ---
        'new.back': 'Назад',
        'new.title': 'Название',
        'new.username': 'Имя пользователя',
        'new.password': 'Пароль',
        'new.generate': 'Сгенерировать',
        'new.copy': 'Копировать',
        'new.website': 'Сайт',
        'new.save': 'Сохранить вход',
        'new.saving': 'Сохранение…',
        'new.validation': 'Введите хотя бы название или сайт.',
        'new.locked': 'Сначала разблокируйте хранилище.',
        'new.error': 'Не удалось сохранить.',

        // --- password generator ---
        'gen.back': 'Назад',
        'gen.regenerate': 'Обновить',
        'gen.copy': 'Копировать',
        'gen.chars': 'Символы',
        'gen.words': 'Запоминаемые слова',
        'gen.length_label': 'Длина: ',
        'gen.words_label': 'Слов: ',
        'gen.upper': 'A–Z',
        'gen.lower': 'a–z',
        'gen.digits': '0–9',
        'gen.symbols': '!@#',
        'gen.similar': 'Разрешить похожие символы',
        'gen.sep_space': 'Пробел',
        'gen.sep_none': 'Нет',
        'gen.capitalize': 'Заглавные буквы',
        'gen.add_number': 'Добавить цифру',
        'gen.copy_clipboard': 'Копировать в буфер обмена',

        // --- bookmarks ---
        'bm.save_page': 'Сохранить страницу',
        'bm.new_folder': 'Новая папка здесь',
        'bm.search_ph': 'Поиск по закладкам…',
        'bm.browse': 'Обзор',
        'bm.import_browser': 'Импорт из браузера',
        'bm.importing': 'Импорт…',
        'bm.import_done': ':added импортировано, :skipped пропущено',
        'bm.import_none': 'В браузере не найдено закладок.',
        'bm.import_error': 'Не удалось импортировать.',
        'bm.import_locked': 'Сначала разблокируйте хранилище.',
        'bm.favorites': 'Избранное',
        'bm.read_later': 'Прочитать позже',
        'bm.all_crumb': 'Все',
        'bm.item_count_one': ':count запись',
        'bm.item_count_other': ':count записей',
        'bm.rename_folder': 'Переименовать',
        'bm.delete_folder': 'Удалить папку',
        'bm.rename_prompt': 'Переименовать папку',
        'bm.rename_error': 'Не удалось переименовать папку.',
        'bm.delete_confirm': 'Удалить папку \":name\"? Закладки внутри переместятся в \"Все\"; вложенные папки поднимутся на уровень выше.',
        'bm.delete_error': 'Не удалось удалить папку.',
        'bm.new_folder_prompt': 'Название новой папки',
        'bm.create_error': 'Не удалось создать папку.',
        'bm.edit': 'Изменить',
        'bm.trash': 'В корзину',
        'bm.trash_confirm': 'Переместить эту закладку в корзину?',
        'bm.trash_error': 'Не удалось удалить.',
        'bm.empty_folder': 'Эта папка пуста',
        'bm.empty_search': 'Закладки не найдены',
        'bm.untitled': 'Без названия',
        'bm.unsafe_url': 'Небезопасный URL — исправьте закладку.',

        // bookmark edit / save page form
        'bm.title': 'Название',
        'bm.url': 'URL',
        'bm.description': 'Описание',
        'bm.tags': 'Теги (через запятую)',
        'bm.folder': 'Папка',
        'bm.no_folder': 'Все (без папки)',
        'bm.favorite': 'Избранное',
        'bm.read_later_check': 'Прочитать позже',
        'bm.save': 'Сохранить',
        'bm.cancel': 'Отмена',
        'bm.saving': 'Сохранение…',
        'bm.save_error': 'Не удалось сохранить.',
        'bm.save_page_heading': 'Сохранить текущую страницу',
        'bm.save_bookmark': 'Сохранить закладку',

        // --- content.js notifications ---
        'notify.2fa_copied': 'Код 2FA скопирован',
        'notify.pw_filled': 'Пароль вставлен и скопирован',
        'notify.saved': 'Сохранено в Ledgerline',
        'notify.save_locked': 'Разблокируйте Ledgerline для сохранения',
        'notify.save_error': 'Не удалось сохранить',
        'notify.updated': 'Пароль обновлён в Ledgerline',
        'notify.update_locked': 'Разблокируйте Ledgerline для обновления',
        'notify.update_error': 'Не удалось обновить',

        // --- content.js save/update prompts ---
        'save.heading': 'Сохранить этот вход в Ledgerline?',
        'save.title_label': 'Название',
        'save.username_line': 'Имя пользователя: :username',
        'save.no_username': 'Имя пользователя не обнаружено',
        'save.not_now': 'Не сейчас',
        'save.save': 'Сохранить',
        'save.saving': 'Сохранение…',

        'update.heading': 'Обновить пароль для :domain?',
        'update.no_username': 'Нет имени пользователя',
        'update.not_now': 'Не сейчас',
        'update.update': 'Обновить',
        'update.updating': 'Обновление…',

        // --- content.js passkey prompts ---
        'passkey.sign_in_prompt': 'Войти на :rpId?',
        'passkey.cancel': 'Отмена',
        'passkey.sign_in': 'Войти',
        'passkey.save_heading': 'Сохранить passkey для :rpId?',
        'passkey.user_label': 'Пользователь: :userName',
        'passkey.attach_section': 'Добавить к существующему входу',
        'passkey.new_entry': 'Новая запись passkey',

        // --- content.js generator panel ---
        'gen.fill_password': 'Вставить пароль',
        'gen.suggest_title': 'Предложить пароль…',
        'gen.suggest_sub': 'Настраиваемый · отображается перед вставкой',

        // --- content.js in-field badge ---
        'badge.title': 'Ledgerline — заполнить',
    },
};

// Resolve locale: chrome.i18n UI language → 2-letter prefix → one of en/de/ru, default en.
const SUPPORTED = ['en', 'de', 'ru'];
function _resolveLocale() {
    try {
        const raw = (typeof chrome !== 'undefined' && chrome.i18n?.getUILanguage?.())
            || (typeof navigator !== 'undefined' && navigator.language)
            || 'en';
        const lang = String(raw).toLowerCase().slice(0, 2);
        return SUPPORTED.includes(lang) ? lang : 'en';
    } catch (_e) {
        return 'en';
    }
}
export const LOCALE = _resolveLocale();

/**
 * Translate a catalog key.  Falls back to en, then to the bare key.
 * Substitutes :name placeholders from the subs object.
 * Also handles {n} index-style placeholders for compatibility.
 *
 * @param {string} key
 * @param {Record<string,string>} [subs]
 * @returns {string}
 */
export function t(key, subs = {}) {
    let msg = MESSAGES[LOCALE]?.[key] ?? MESSAGES.en?.[key] ?? key;
    for (const [k, v] of Object.entries(subs)) {
        msg = msg.replaceAll(':' + k, String(v));
    }
    // {n} index-style substitutions
    const vals = Object.values(subs);
    msg = msg.replace(/\{(\d+)\}/g, (_m, i) => vals[+i] ?? _m);
    return msg;
}
