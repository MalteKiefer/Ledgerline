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

/**
 * Lists all teams with their record counts.
 *
 * Useful for finding the source/target keys or ids for teams:reassign after
 * the migration parked existing data in the "Default Team".
 */
class ListTeams extends Command
{
    protected $signature = 'teams:list';

    protected $description = 'List all teams with their owned-record counts';

    public function handle(): int
    {
        $rows = Team::query()->orderBy('id')->get()->map(fn (Team $team): array => [
            $team->id,
            $team->key,
            $team->name,
            $team->users()->count(),
            Customer::withoutGlobalScopes()->where('team_id', $team->id)->count(),
            Contact::withoutGlobalScopes()->where('team_id', $team->id)->count(),
            Branch::withoutGlobalScopes()->where('team_id', $team->id)->count(),
            Project::withoutGlobalScopes()->where('team_id', $team->id)->count(),
            File::withoutGlobalScopes()->where('team_id', $team->id)->count(),
        ])->all();

        $this->table(
            ['ID', 'Key', 'Name', 'Members', 'Customers', 'Contacts', 'Branches', 'Projects', 'Files'],
            $rows,
        );

        return self::SUCCESS;
    }
}
