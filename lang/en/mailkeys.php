<?php

declare(strict_types=1);

return [
    'import_smime' => 'Import S/MIME (.p12)',
    'p12_file' => 'PKCS#12 file (.p12/.pfx)',
    'err_no_file' => 'Choose a .p12 file.',
    'err_p12' => 'Could not import this .p12 (wrong passphrase?).',
    'nav_desc' => 'PGP keys for encrypted mail',
    'title' => 'Encryption keys',
    'subtitle' => 'Import or generate PGP keys to read encrypted mail. Private keys stay sealed in your vault and never leave your browser in the clear.',
    'locked_hint' => 'Unlock your vault to manage keys.',
    'unlock' => 'Unlock',
    'import' => 'Import key',
    'generate' => 'Generate key',
    'name' => 'Name',
    'email' => 'Email',
    'armored_private' => 'Armored private key',
    'passphrase_opt' => 'Passphrase (optional)',
    'add' => 'Add',
    'no_keys' => 'No keys yet.',
    'copy_public' => 'Copy public key',
    'generating' => 'Generating…',
    'err_not_private' => 'That is not a PGP private key.',
    'err_import' => 'Could not import this key.',
    'err_generate' => 'Could not generate a key.',
    'confirm_delete' => 'Delete this key? Mail encrypted to it can no longer be read.',
    'copied' => 'Public key copied.',
];
