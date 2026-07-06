<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\AuthBackend;
use App\Dav\Files\FileDavBackend;
use App\Dav\Files\FilesRoot;
use App\Dav\PrincipalBackend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Sabre\DAV\Auth\Plugin;
use Sabre\DAV\Locks\Backend\PDO as LocksBackend;
use Sabre\DAV\Locks\Plugin as LocksPlugin;
use Sabre\DAV\Server;
use Sabre\DAV\TemporaryFileFilterPlugin;
use Sabre\DAVACL\PrincipalCollection;
use Tests\TestCase;

class WebDavServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_tree_builds_with_files_root_and_locks(): void
    {
        $principals = app(PrincipalBackend::class);
        $server = new Server([
            new PrincipalCollection($principals),
            new FilesRoot($principals, app(FileDavBackend::class)),
        ]);
        $server->addPlugin(new Plugin(app(AuthBackend::class)));
        $server->addPlugin(new LocksPlugin(new LocksBackend(DB::connection()->getPdo())));
        $server->addPlugin(new TemporaryFileFilterPlugin(sys_get_temp_dir().'/ll-webdav-test'));

        $this->assertInstanceOf(Server::class, $server);
    }
}
