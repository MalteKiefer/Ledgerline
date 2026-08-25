<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentDeadline;
use App\Models\FileEntry;
use App\Models\FinanceReceipt;
use App\Models\User;
use App\Services\Deadlines\DeadlineScanner;
use App\Support\DeadlineReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deadlines read out of document text.
 *
 * The reader is where trust is won or lost: a document is full of dates, and
 * reporting all of them would bury the one that matters. So the tests pin both
 * sides — the wording that must be understood, and the dates that must be left
 * alone.
 */
class DeadlineTest extends TestCase
{
    use RefreshDatabase;

    private function read(string $text): array
    {
        return (new DeadlineReader)->read($text);
    }

    public function test_it_reads_the_wordings_that_actually_appear(): void
    {
        $cases = [
            ['Vertragsende: 31.12.2027', '2027-12-31', 'contract_end'],
            ['Kündigungsfrist bis 30.09.2027', '2027-09-30', 'notice'],
            ['Garantie bis 15. März 2028', '2028-03-15', 'warranty'],
            ['Gültig bis 01.06.2027', '2027-06-01', 'expiry'],
            ['Valid until 2027-08-09', '2027-08-09', 'expiry'],
            ['Warranty expires December 24, 2027', '2027-12-24', 'warranty'],
            // A card or certificate states a month, and the deadline is its end.
            ['Ablaufdatum 07/2027', '2027-07-31', 'expiry'],
        ];

        foreach ($cases as [$text, $date, $kind]) {
            $found = $this->read($text);
            $this->assertCount(1, $found, $text);
            $this->assertSame($date, $found[0]['due_on'], $text);
            $this->assertSame($kind, $found[0]['kind'], $text);
            $this->assertNotSame('', $found[0]['evidence'], 'a finding must show the sentence it came from');
        }
    }

    public function test_a_date_without_a_word_saying_what_it_means_is_ignored(): void
    {
        // Every one of these is a date on a real document, and none is a deadline.
        $this->assertSame([], $this->read('Rechnungsdatum: 05.08.2026'));
        $this->assertSame([], $this->read('Leistungszeitraum 01.01.2026 - 31.12.2026'));
        $this->assertSame([], $this->read('Buchungstag 12.03.2026 Wertstellung 13.03.2026'));
        $this->assertSame([], $this->read('Bestellt am 2026-02-01'));
    }

    public function test_the_label_leads_and_the_date_follows_on_the_same_line(): void
    {
        // The invoice date printed BEFORE the label must not be taken as the
        // deadline, and a value on the next line belongs to a different field.
        $found = $this->read('05.08.2026 Rechnung — gültig bis 31.10.2027');
        $this->assertSame('2027-10-31', $found[0]['due_on']);

        $this->assertSame([], $this->read("Gültig bis\n31.10.2027"));
    }

    public function test_a_misread_date_is_refused(): void
    {
        $this->assertSame([], $this->read('Gültig bis 31.02.2027'), '31 February is a misread');
        $this->assertSame([], $this->read('Gültig bis 01.01.2199'), 'a century out is a scanning artefact');
    }

    public function test_scanning_records_findings_and_never_resurrects_a_dismissed_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = new FileEntry;
        $file->forceFill([
            'user_id' => $user->id, 'name' => 'Mietvertrag.pdf', 'storage_path' => 'files/x',
            'mime' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('a', 64),
        ])->save();
        // saveQuietly: the file observer indexes text on save and would overwrite
        // this fixture with the (empty) result of reading a blob that is not there.
        $file->forceFill(['search_text' => 'Mietvertrag. Vertragsende: 31.12.2027. Kündigungsfrist bis 30.09.2027.'])->saveQuietly();

        $stats = app(DeadlineScanner::class)->scanUser((int) $user->id);
        $this->assertSame(2, $stats['new']);
        $this->assertSame(2, DocumentDeadline::query()->count());

        // A finding carries where it came from, or the reader cannot judge it.
        $first = DocumentDeadline::query()->orderBy('due_on')->firstOrFail();
        $this->assertSame('file', $first->source_type);
        $this->assertSame((int) $file->id, $first->source_id);
        $this->assertNull($first->confirmed_at, 'a finding is a suggestion until confirmed');
        $this->assertSame('Mietvertrag.pdf', $first->label);

        // Judged and rejected — a second scan must not bring it back.
        $first->forceFill(['dismissed_at' => now()])->save();
        $again = app(DeadlineScanner::class)->scanUser((int) $user->id);
        $this->assertSame(0, $again['new'], 'rescanning creates nothing new');
        $this->assertNotNull($first->fresh()?->dismissed_at, 'the dismissal survives a rescan');
        $this->assertSame(2, DocumentDeadline::query()->withoutGlobalScopes()->count(), 'no duplicates piled up');
    }

    public function test_receipt_text_is_scanned_too_and_stays_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $receipt = new FinanceReceipt;
        $receipt->forceFill([
            'user_id' => $owner->id, 'blob_path' => 'invoices/a', 'name' => 'Versicherung.pdf',
            'ocr' => 'Police. Laufzeit bis 01.04.2028.',
        ])->save();

        app(DeadlineScanner::class)->scanUser((int) $owner->id);
        $this->assertSame('receipt', DocumentDeadline::query()->firstOrFail()->source_type);

        // A stranger's scan sees none of it, and the list is owner-scoped.
        $stranger = User::factory()->create();
        $this->assertSame(0, app(DeadlineScanner::class)->scanUser((int) $stranger->id)['new']);
        app('auth')->forgetGuards();
        $token = $stranger->createToken('t', ['device'])->plainTextToken;
        $this->getJson(route('api.deadlines.index'), ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertExactJson(['deadlines' => []]);
    }

    public function test_confirming_and_correcting_a_finding(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deadline = new DocumentDeadline;
        $deadline->forceFill([
            'user_id' => $user->id, 'source_type' => 'file', 'source_id' => 1,
            'due_on' => '2027-12-31', 'kind' => 'contract_end', 'reminded_at' => now(),
        ])->save();

        $this->putJson(route('api.deadlines.update', $deadline->id), ['confirmed' => true])->assertOk();
        $this->assertNotNull($deadline->fresh()?->confirmed_at);

        // Correcting the date makes it a different deadline, so a reminder that
        // already went out must not count as sent for the new one.
        $this->putJson(route('api.deadlines.update', $deadline->id), ['due_on' => '2028-01-31'])->assertOk();
        $this->assertNull($deadline->fresh()?->reminded_at);
    }

    public function test_only_a_confirmed_deadline_is_reminded_about(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $soon = now()->addDays(5)->toDateString();
        foreach ([[1, 'unconfirmed', null], [2, 'confirmed', now()]] as [$sourceId, $label, $confirmed]) {
            (new DocumentDeadline)->forceFill([
                'user_id' => $user->id, 'source_type' => 'file', 'source_id' => $sourceId,
                'due_on' => $soon, 'kind' => 'expiry', 'label' => $label, 'confirmed_at' => $confirmed,
            ])->save();
        }

        $this->artisan('deadlines:remind')->assertExitCode(0);

        // Exactly one reminder: the guess must not wake anybody.
        $this->assertSame(1, DocumentDeadline::query()->whereNotNull('reminded_at')->count());
        $this->assertSame('confirmed', DocumentDeadline::query()->whereNotNull('reminded_at')->firstOrFail()->label);
    }
}
