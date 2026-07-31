<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\TodoSearchController;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TodoSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The parent wires GET /todos/search into routes/web.php; self-register a
        // matching route so this test stands alone until that lands.
        if (! Route::has('todos.search')) {
            Route::middleware(['web', 'auth'])
                ->get('/todos/search', [TodoSearchController::class, 'search'])
                ->name('todos.search');
        }
    }

    private function make(User $user, string $title, ?string $description = null, array $tags = []): Todo
    {
        $this->actingAs($user);

        return Todo::create(['title' => $title, 'description' => $description, 'tags' => $tags ?: null]);
    }

    public function test_finds_by_title_description_and_tag(): void
    {
        $user = User::factory()->create();
        $this->make($user, 'Deploy the release', 'ship to prod', ['work']);
        $this->make($user, 'Buy milk', 'groceries', ['home']);

        $this->actingAs($user);
        $this->getJson('/todos/search?q='.urlencode('Deploy'))->assertOk()->assertJsonCount(1, 'todos')->assertJsonPath('todos.0.title', 'Deploy the release');
        $this->getJson('/todos/search?q='.urlencode('groceries'))->assertOk()->assertJsonCount(1, 'todos')->assertJsonPath('todos.0.title', 'Buy milk');
        $this->getJson('/todos/search?q='.urlencode('work'))->assertOk()->assertJsonCount(1, 'todos')->assertJsonPath('todos.0.title', 'Deploy the release');
    }

    public function test_empty_query_returns_empty(): void
    {
        $user = User::factory()->create();
        $this->make($user, 'Something');

        $this->actingAs($user);
        $this->getJson('/todos/search?q='.urlencode(''))->assertOk()->assertJsonCount(0, 'todos');
        $this->getJson('/todos/search?q='.urlencode('   '))->assertOk()->assertJsonCount(0, 'todos');
    }

    public function test_owner_scoped(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->make($a, 'Alpha secret plan');

        // b searches the same term — must not see a's task.
        $this->actingAs($b);
        $this->getJson('/todos/search?q='.urlencode('secret'))->assertOk()->assertJsonCount(0, 'todos');
    }
}
