<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves all owned records from one team to another.
 *
 * Fixes data that the teams migration parked in the "Default Team" but that
 * belongs to a real Pocket-ID group team. Run e.g.:
 *
 *   php artisan teams:reassign default group:engineering
 *   php artisan teams:reassign 1 3 --with-members
 *
 * Teams may be given by id or key. With --with-members, members of the source
 * team are also added to the target team.
 */
class ReassignTeamData extends Command
{
    protected $signature = 'teams:reassign {from : Source team id or key} {to : Target team id or key} {--with-members : Also add the source team members to the target team}';

    protected $description = 'Move all customers, contacts, branches, projects and files from one team to another';

    public function handle(): int
    {
        $from = $this->resolveTeam((string) $this->argument('from'));
        $to = $this->resolveTeam((string) $this->argument('to'));

        if ($from === null || $to === null) {
            $this->error('Both a valid source and target team are required. Run "php artisan teams:list".');

            return self::FAILURE;
        }

        if ($from->is($to)) {
            $this->error('Source and target teams are the same.');

            return self::FAILURE;
        }

        $moved = DB::transaction(function () use ($from, $to): array {
            $counts = [];
            foreach ([
                'customers' => Customer::class,
                'contacts' => Contact::class,
                'branches' => Branch::class,
                'projects' => Project::class,
                'files' => File::class,
            ] as $label => $model) {
                $counts[$label] = $model::withoutGlobalScopes()
                    ->where('team_id', $from->id)
                    ->update(['team_id' => $to->id]);
            }

            if ($this->option('with-members')) {
                $to->users()->syncWithoutDetaching($from->users()->pluck('users.id'));
            }

            return $counts;
        });

        $this->info("Reassigned data from \"{$from->name}\" (#{$from->id}) to \"{$to->name}\" (#{$to->id}):");
        foreach ($moved as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        return self::SUCCESS;
    }

    /**
     * Resolve a team by numeric id or by key.
     */
    private function resolveTeam(string $identifier): ?Team
    {
        return Team::query()
            ->when(ctype_digit($identifier), fn ($q) => $q->where('id', (int) $identifier))
            ->when(! ctype_digit($identifier), fn ($q) => $q->where('key', $identifier))
            ->first();
    }
}
