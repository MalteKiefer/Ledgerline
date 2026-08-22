<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Servers\RoleDetector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RoleDetectorTest extends TestCase
{
    /** `systemctl show` blocks as the real command prints them. */
    private function units(string ...$blocks): string
    {
        return implode("\n\n", $blocks);
    }

    private function unit(string $id, string $load = 'loaded', string $active = 'active'): string
    {
        return "Id={$id}\nLoadState={$load}\nActiveState={$active}";
    }

    #[Test]
    public function a_service_that_is_not_installed_is_not_a_role(): void
    {
        // `systemctl is-active` reports a missing unit and a stopped one the
        // same way. LoadState is the field that tells them apart, and getting
        // this wrong would give every Debian box every role in the list.
        $units = $this->units(
            $this->unit('postfix.service', load: 'not-found', active: 'inactive'),
            $this->unit('nginx.service', load: 'not-found', active: 'inactive'),
        );

        $result = (new RoleDetector)->detect($units, '', []);

        $this->assertSame([], $result['roles']);
        $this->assertSame([], $result['services'], 'absent services must not be listed at all');
    }

    #[Test]
    public function an_installed_but_stopped_service_still_counts_as_a_role(): void
    {
        // A mail server with Postfix stopped is still a mail server, and the
        // page should say so rather than pretend the machine does something
        // else.
        $units = $this->units($this->unit('postfix.service', active: 'inactive'));

        $result = (new RoleDetector)->detect($units, '', []);

        $this->assertSame(['mail'], $result['roles']);
        $this->assertFalse($result['services'][0]['active']);
        $this->assertTrue($result['services'][0]['installed']);
    }

    #[Test]
    public function services_running_as_containers_count_the_same(): void
    {
        // The case that made two sources necessary: this host runs its database
        // and media server as containers and has nothing but Docker under
        // systemd. Reading only units would call it "a Docker host" — true,
        // and useless.
        $units = $this->units($this->unit('docker.service'));
        $containers = [
            ['name' => 'db', 'status' => 'Up 2 days', 'image' => 'pgvector/pgvector:pg18'],
            ['name' => 'cache', 'status' => 'Up 2 days', 'image' => 'valkey/valkey:8-alpine'],
            ['name' => 'films', 'status' => 'Up 1 day', 'image' => 'jellyfin/jellyfin:latest'],
        ];

        $result = (new RoleDetector)->detect($units, '', $containers);

        $this->assertContains('database', $result['roles']);
        $this->assertContains('media', $result['roles']);
        $this->assertContains('containers', $result['roles']);
    }

    #[Test]
    public function the_image_decides_what_a_container_is_not_its_name(): void
    {
        // A container called "mail" running nginx is a web server. The name is
        // whatever somebody typed in a compose file; the image is a fact.
        $containers = [['name' => 'mail', 'status' => 'Up', 'image' => 'nginx:1.27-alpine']];

        $result = (new RoleDetector)->detect('', '', $containers);

        $this->assertSame(['web'], $result['roles']);
    }

    #[Test]
    public function a_tag_is_not_searched_for_service_names(): void
    {
        // `:16-alpine` should not make an Alpine-tagged image look like
        // anything; only the repository half is matched.
        $containers = [['name' => 'x', 'status' => 'Up', 'image' => 'ghcr.io/example/app:redis-compat']];

        $result = (new RoleDetector)->detect('', '', $containers);

        $this->assertSame([], $result['roles']);
    }

    #[Test]
    public function proxmox_is_recognised_and_implies_virtualisation(): void
    {
        $result = (new RoleDetector)->detect('', "pve-manager/8.2.4/abc (running kernel: 6.8.8-2-pve)\n", []);

        $this->assertSame('proxmox', $result['platform']);
        $this->assertContains('virtualisation', $result['roles']);
    }

    #[Test]
    public function an_unremarkable_host_has_no_platform_and_no_roles(): void
    {
        $result = (new RoleDetector)->detect('', '', []);

        $this->assertNull($result['platform']);
        $this->assertSame([], $result['roles']);
    }
}
