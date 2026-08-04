<x-layouts.app :title="__('account.nav_account')">
    <div class="mx-auto w-full max-w-[1700px]">
        @include('profile._header', ['title' => __('account.nav_account')])

        {{-- Avatar: upload with square crop (max 10 MB), or remove --}}
        <div class="mt-5 ll-card flex flex-wrap items-center gap-4"
             x-data="{
                has: {{ $user->avatar ? 'true' : 'false' }},
                src: '{{ route('profile.avatar') }}?v=' + Date.now(),
                busy: false,
                error: '',
                maxBytes: 10 * 1024 * 1024,
                async pick(e) {
                    const file = e.target.files && e.target.files[0];
                    e.target.value = '';
                    if (!file) return;
                    this.error = '';
                    if (!/^image\//.test(file.type)) { this.error = @js(__('pages.profile.avatar_not_image')); return; }
                    if (file.size > this.maxBytes) { this.error = @js(__('pages.profile.avatar_too_big')); return; }
                    const bytes = await window.llCrop(file);
                    if (!bytes) return; // cancelled
                    this.busy = true;
                    try {
                        const fd = new FormData();
                        fd.append('_token', '{{ csrf_token() }}');
                        fd.append('avatar', new Blob([bytes], { type: 'image/jpeg' }), 'avatar.jpg');
                        const res = await fetch('{{ route('profile.avatar.store') }}', { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
                        if (!res.ok) throw new Error('upload failed');
                        this.has = true;
                        this.src = '{{ route('profile.avatar') }}?v=' + Date.now();
                        window.llToast?.(@js(__('pages.profile.avatar_saved')));
                    } catch { this.error = @js(__('pages.profile.avatar_failed')); }
                    finally { this.busy = false; }
                },
                async remove() {
                    if (!await this.$store.confirm.ask(@js(__('pages.profile.avatar_remove_confirm')))) return;
                    this.busy = true;
                    try {
                        const res = await fetch('{{ route('profile.avatar.destroy') }}', { method: 'DELETE', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
                        if (!res.ok) throw new Error('remove failed');
                        this.has = false;
                    } catch { this.error = @js(__('pages.profile.avatar_failed')); }
                    finally { this.busy = false; }
                },
             }">
            <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-full bg-accent/10 ring-1 ring-black/[0.06] dark:ring-white/10">
                <img x-show="has" :src="src" alt="" class="h-full w-full object-cover">
                <span x-show="!has" class="flex h-full w-full items-center justify-center text-2xl font-semibold text-accent">{{ strtoupper(mb_substr($user->name ?: $user->email, 0, 1)) }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('pages.profile.avatar') }}</p>
                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ __('pages.profile.avatar_upload_hint') }}</p>
                <x-alert variant="error" x-show="error" x-cloak x-text="error" class="mt-2 !px-2.5 !py-1.5 text-xs" />
                <div class="mt-2 flex flex-wrap gap-2">
                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-accent px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:opacity-90" :class="busy ? 'pointer-events-none opacity-60' : ''">
                        <x-icon name="arrow-up-tray" class="h-4 w-4" />
                        <span x-text="has ? @js(__('pages.profile.avatar_change')) : @js(__('pages.profile.avatar_upload'))"></span>
                        <input type="file" accept="image/*" class="hidden" @change="pick($event)">
                    </label>
                    <x-button variant="secondary" size="sm" x-show="has" ::disabled="busy" @click="remove()">{{ __('common.delete') }}</x-button>
                </div>
            </div>
        </div>

        <div class="mt-4 ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            @php
                $rows = [
                    ['icon' => 'user', 'tint' => '#7066f5', 'label' => __('pages.profile.name'), 'value' => $user->name ?: '—'],
                    ['icon' => 'envelope', 'tint' => '#3b9fd6', 'label' => __('pages.profile.email'), 'value' => $user->email ?: '—'],
                    ['icon' => 'shield-check', 'tint' => '#59ad6b', 'label' => __('pages.profile.email_verified'), 'value' => $user->email_verified_at ? __('pages.profile.verified_yes', ['date' => $user->email_verified_at->format('Y-m-d')]) : __('pages.profile.verified_no')],
                    ['icon' => 'shield-check', 'tint' => '#6b7280', 'label' => __('pages.profile.role'), 'value' => ucfirst((string) $user->role)],
                    ['icon' => 'clock', 'tint' => '#3fae9f', 'label' => __('pages.profile.account_created'), 'value' => $user->created_at?->format('Y-m-d H:i') ?: '—'],
                ];
            @endphp
            @foreach ($rows as $r)
                <div class="flex items-center gap-3.5 px-4 py-3">
                    <span class="ll-chip h-8 w-8 shrink-0" style="--chip: {{ $r['tint'] }}"><x-icon name="{{ $r['icon'] }}" class="h-4 w-4" /></span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $r['label'] }}</span>
                    <span class="ml-auto min-w-0 truncate text-right text-sm {{ ($r['mono'] ?? false) ? 'font-mono text-xs' : '' }} text-gray-900 dark:text-gray-100">{{ $r['value'] }}</span>
                </div>
            @endforeach
        </div>
        <p class="mt-3 px-1 text-xs text-gray-400 dark:text-gray-500">{{ __('pages.profile.subtitle') }}</p>
    </div>
</x-layouts.app>
