<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\FileEntry;
use App\Models\GalleryPhoto;
use App\Models\Invoice;
use App\Models\MailMessage;
use App\Models\Note;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One search across every module the user has enabled: Files (content + OCR),
 * Notes, Gallery (filename + photo OCR), Contacts, Mail, Calendar and Finance.
 * Every query is owner-scoped (OwnsUserData global scope) and module-gated
 * (User::canModule). Each module is best-effort — a failure in one never breaks
 * the whole search. Results are grouped; the SPA maps a group to its route.
 */
class GlobalSearchController extends Controller
{
    private const PER_GROUP = 8;

    public function search(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $q = trim($request->string('q')->value());
        if (mb_strlen($q) < 2) {
            return response()->json(['q' => $q, 'groups' => []]);
        }
        $like = '%'.$q.'%';
        $pg = DB::getDriverName() === 'pgsql';
        $groups = [];

        if ($user->canModule('files')) {
            $groups[] = $this->wrap('files', $this->try(fn (): array => FileEntry::query()
                ->where(function (Builder $w) use ($q, $like, $pg): void {
                    $w->where('name', 'like', $like);
                    $pg
                        ? $w->orWhereRaw("to_tsvector('simple', coalesce(search_text, '')) @@ plainto_tsquery('simple', ?)", [$q])
                        : $w->orWhere('search_text', 'like', $like);
                })
                ->orderByDesc('updated_at')->limit(self::PER_GROUP)->get()
                ->map(fn (FileEntry $f): array => ['id' => $f->id, 'title' => $f->name, 'subtitle' => $f->mime])->all()));
        }

        if ($user->canModule('notes')) {
            $groups[] = $this->wrap('notes', $this->try(fn (): array => Note::query()
                ->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like))
                ->orderByDesc('updated_at')->limit(self::PER_GROUP)->get()
                ->map(fn (Note $n): array => [
                    'id' => $n->id,
                    'title' => (string) ($n->title !== null && $n->title !== '' ? $n->title : __('notes.untitled')),
                    'subtitle' => mb_substr(trim((string) $n->body), 0, 80),
                ])->all()));
        }

        if ($user->canModule('gallery')) {
            $groups[] = $this->wrap('gallery', $this->try(fn (): array => GalleryPhoto::query()
                ->where(function (Builder $w) use ($q, $like, $pg): void {
                    $w->where('name', 'like', $like);
                    $pg
                        ? $w->orWhereRaw("to_tsvector('simple', coalesce(ocr_text, '')) @@ plainto_tsquery('simple', ?)", [$q])
                        : $w->orWhere('ocr_text', 'like', $like);
                })
                ->orderByDesc('id')->limit(self::PER_GROUP)->get()
                ->map(fn (GalleryPhoto $p): array => ['id' => $p->id, 'title' => $p->name, 'subtitle' => $p->media_type])->all()));
        }

        if ($user->canModule('contacts')) {
            $groups[] = $this->wrap('contacts', $this->try(fn (): array => Contact::query()
                ->where(fn (Builder $w) => $w->where('fn', 'like', $like)
                    ->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhere('org', 'like', $like))
                ->orderBy('fn')->limit(self::PER_GROUP)->get()
                ->map(fn (Contact $c): array => ['id' => $c->id, 'title' => (string) $c->fn, 'subtitle' => (string) $c->org])->all()));
        }

        if ($user->canModule('mail')) {
            $groups[] = $this->wrap('mail', $this->try(fn (): array => MailMessage::query()
                ->where(function (Builder $w) use ($q, $like, $pg): void {
                    $w->where('subject', 'like', $like)->orWhere('from_name', 'like', $like)->orWhere('from_email', 'like', $like);
                    $pg
                        ? $w->orWhereRaw("to_tsvector('simple', coalesce(search_text, '')) @@ plainto_tsquery('simple', ?)", [$q])
                        : $w->orWhere('search_text', 'like', $like);
                })
                ->orderByDesc('id')->limit(self::PER_GROUP)->get()
                ->map(fn (MailMessage $m): array => ['id' => $m->id, 'title' => (string) $m->subject, 'subtitle' => (string) $m->from_name])->all()));
        }

        if ($user->canModule('calendar')) {
            $groups[] = $this->wrap('calendar', $this->try(fn (): array => CalendarEvent::query()
                ->where(fn (Builder $w) => $w->where('summary', 'like', $like)
                    ->orWhere('location', 'like', $like)->orWhere('description', 'like', $like))
                ->orderByDesc('id')->limit(self::PER_GROUP)->get()
                ->map(fn (CalendarEvent $e): array => ['id' => $e->id, 'title' => (string) $e->summary, 'subtitle' => (string) $e->location])->all()));
        }

        if ($user->canModule('finance')) {
            $groups[] = $this->wrap('finance', $this->try(fn (): array => Invoice::query()
                ->where(function (Builder $w) use ($like): void {
                    $w->where('number', 'like', $like)->orWhere('customer', 'like', $like);
                })
                ->orderByDesc('id')->limit(self::PER_GROUP)->get()
                ->map(function (Invoice $i): array {
                    $cust = is_array($i->customer) && isset($i->customer['name']) && is_string($i->customer['name']) ? $i->customer['name'] : '';

                    return ['id' => $i->id, 'title' => (string) ($i->number ?? __('invoices.draft')), 'subtitle' => $cust];
                })->all()));
        }

        return response()->json(['q' => $q, 'groups' => array_values(array_filter($groups))]);
    }

    /**
     * @param  callable(): array<int, array<string,mixed>>  $fn
     * @return array<int, array<string,mixed>>
     */
    private function try(callable $fn): array
    {
        try {
            return $fn();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<int, array<string,mixed>>  $items
     * @return array{module: string, items: array<int, array<string,mixed>>}|null
     */
    private function wrap(string $module, array $items): ?array
    {
        return $items === [] ? null : ['module' => $module, 'items' => $items];
    }
}
