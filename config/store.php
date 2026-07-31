<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sealed-root history depth
    |--------------------------------------------------------------------------
    |
    | How many previous sealed root ciphertexts to retain per (user, store) so a
    | silently-dropped record can be recovered by pulling an earlier version. The
    | roots are small (module manifests / sharded pointer tables), so this is cheap.
    | Older versions beyond this depth are pruned on each save.
    |
    */

    'history_versions' => (int) env('STORE_HISTORY_VERSIONS', 20),

];
