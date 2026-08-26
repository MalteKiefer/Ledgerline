<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project planning: dates and a customer on the project, tasks under it, and
 * hours against those tasks.
 *
 * This is what closes the chain the quote started: a quote becomes a project,
 * its service lines become tasks carrying the hours they were estimated at, the
 * hours actually worked are logged against them, and the billable ones go back
 * out as invoice lines. Each link is a plain foreign key, so any of them can be
 * missing without breaking the rest — a project needs no quote, a task needs no
 * estimate, an hour needs no task.
 *
 * `finance_projects` keeps everything it had: nested parents, hand-typed
 * expenses, business/private. Only additive columns here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_projects', function (Blueprint $table): void {
            // planned → active → done, with on_hold and cancelled as the two
            // states that are neither progress nor completion.
            $table->string('status', 16)->default('planned');
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            // What it may cost, net. Compared against the ledger the project
            // already carries; nothing is enforced — a project over budget is
            // information, not an error.
            $table->decimal('budget_net', 12, 2)->nullable();
            // Whose project it is, and where it came from.
            $table->foreignId('partner_id')->nullable()->constrained('finance_partners')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('finance_quotes')->nullOnDelete();

            $table->index(['user_id', 'status']);
        });

        Schema::create('finance_project_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_project_id')->constrained('finance_projects')->cascadeOnDelete();

            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->string('status', 16)->default('open');
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            // Hours quoted for this piece of work. Fractional, because a quote
            // says 2.5 hours as readily as 2.
            $table->decimal('estimate_hours', 10, 2)->nullable();
            // A milestone is a task with no work in it: a date that matters.
            $table->boolean('is_milestone')->default(false);
            // Hand-ordered, because task order is a judgement, not a date.
            $table->unsignedInteger('sort')->default(0);
            // Which quote line this came from, when it came from one.
            $table->foreignId('finance_product_id')->nullable()->constrained('finance_products')->nullOnDelete();

            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'finance_project_id', 'sort']);
        });

        Schema::create('finance_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_project_id')->constrained('finance_projects')->cascadeOnDelete();
            // Hours may be booked on the project without a task: not everything
            // worth logging was planned.
            $table->foreignId('finance_project_task_id')->nullable()
                ->constrained('finance_project_tasks')->nullOnDelete();

            $table->date('date');
            $table->decimal('hours', 8, 2);
            $table->text('description')->nullable();
            // Whether it goes on an invoice. Default yes, because the usual case
            // for tracked time is that someone pays for it.
            $table->boolean('billable')->default(true);
            // The rate at the time it was worked, frozen here rather than read
            // from the partner later: a rate change must not rewrite the past.
            $table->decimal('hourly_rate', 10, 2)->nullable();
            // Which invoice took it. Set once and never cleared: an hour that
            // has been billed must not be billed again.
            $table->foreignId('invoiced_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'finance_project_id', 'date']);
            $table->index(['user_id', 'invoiced_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_time_entries');
        Schema::dropIfExists('finance_project_tasks');
        Schema::table('finance_projects', function (Blueprint $table): void {
            $table->dropColumn(['status', 'starts_on', 'due_on', 'budget_net', 'partner_id', 'quote_id']);
        });
    }
};
