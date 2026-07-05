{{--
    Self-contained rich-text editor for composing mail.

    Dependency-free: toolbar + a contenteditable div driven by an inline Alpine
    component local to this partial (does NOT rely on app.js). It reads/writes the
    HTML on the SURROUNDING compose scope's `compose.body` string:

      - initialises the contenteditable from `compose.body` when mounted/shown,
      - writes the contenteditable's innerHTML back into `compose.body` on input.

    Include this inside a compose modal that exposes `compose.body` on its Alpine
    scope, e.g.  @include('mail._compose_editor')
--}}
<div
    x-data="{
        showLink: false,
        linkUrl: '',
        // Push the contenteditable's HTML up to the parent compose scope.
        sync() {
            compose.body = this.$refs.editor.innerHTML;
        },
        // Load the parent's HTML into the contenteditable (only when it differs,
        // so we never clobber the caret while the user is typing).
        hydrate() {
            const html = compose.body ?? '';
            if (this.$refs.editor.innerHTML !== html) {
                this.$refs.editor.innerHTML = html;
            }
        },
        cmd(name, value = null) {
            this.$refs.editor.focus();
            document.execCommand(name, false, value);
            this.sync();
        },
        openLink() {
            this.linkUrl = '';
            this.showLink = true;
            this.$nextTick(() => this.$refs.linkInput && this.$refs.linkInput.focus());
        },
        applyLink() {
            const url = (this.linkUrl || '').trim();
            if (url !== '') {
                this.cmd('createLink', url);
            }
            this.showLink = false;
            this.linkUrl = '';
        },
    }"
    x-init="hydrate(); $watch('compose.body', () => hydrate())"
    class="rounded-md border border-gray-300 bg-white focus-within:border-gray-400 focus-within:ring-1 focus-within:ring-gray-400"
>
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-1 border-b border-gray-200 bg-gray-50 px-1.5 py-1">
        <button type="button" @click="cmd('bold')"
            aria-label="{{ __('mail.compose_bold') }}" title="{{ __('mail.compose_bold') }}"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-sm font-bold text-gray-700 hover:bg-gray-100">
            B
        </button>
        <button type="button" @click="cmd('italic')"
            aria-label="{{ __('mail.compose_italic') }}" title="{{ __('mail.compose_italic') }}"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-sm italic text-gray-700 hover:bg-gray-100">
            I
        </button>
        <button type="button" @click="cmd('underline')"
            aria-label="{{ __('mail.compose_underline') }}" title="{{ __('mail.compose_underline') }}"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-sm text-gray-700 underline hover:bg-gray-100">
            U
        </button>

        <span class="mx-0.5 h-5 w-px bg-gray-200" aria-hidden="true"></span>

        <button type="button" @click="cmd('insertUnorderedList')"
            aria-label="{{ __('mail.compose_bullets') }}" title="{{ __('mail.compose_bullets') }}"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-base leading-none text-gray-700 hover:bg-gray-100">
            &bull;&mdash;
        </button>
        <button type="button" @click="cmd('insertOrderedList')"
            aria-label="{{ __('mail.compose_numbers') }}" title="{{ __('mail.compose_numbers') }}"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-xs font-medium leading-none text-gray-700 hover:bg-gray-100">
            1.&mdash;
        </button>

        <button type="button" @click="openLink()"
            aria-label="{{ __('mail.compose_link') }}" title="{{ __('mail.compose_link') }}"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-gray-700 hover:bg-gray-100">
            <x-icon name="link" />
        </button>

        <span class="mx-0.5 h-5 w-px bg-gray-200" aria-hidden="true"></span>

        <button type="button" @click="cmd('justifyLeft')"
            aria-label="{{ __('mail.compose_align_left') }}" title="{{ __('mail.compose_align_left') }}"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-gray-700 hover:bg-gray-100">
            <x-icon name="bars-3-bottom-left" />
        </button>
        <button type="button" @click="cmd('justifyCenter')"
            aria-label="{{ __('mail.compose_align_center') }}" title="{{ __('mail.compose_align_center') }}"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-gray-700 hover:bg-gray-100">
            <x-icon name="bars-3" />
        </button>
        <button type="button" @click="cmd('justifyRight')"
            aria-label="{{ __('mail.compose_align_right') }}" title="{{ __('mail.compose_align_right') }}"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-gray-700 hover:bg-gray-100">
            <x-icon name="bars-3-bottom-right" />
        </button>

        <span class="mx-0.5 h-5 w-px bg-gray-200" aria-hidden="true"></span>

        {{-- Font family --}}
        <select @change="cmd('fontName', $event.target.value); $event.target.selectedIndex = 0"
            aria-label="{{ __('mail.compose_font') }}" title="{{ __('mail.compose_font') }}"
            class="min-h-9 rounded border border-gray-300 bg-white px-1.5 py-0 text-xs text-gray-700 hover:bg-gray-100 focus:border-gray-400 focus:ring-1 focus:ring-gray-400">
            <option value="" disabled selected>{{ __('mail.compose_font') }}</option>
            <option value="system-ui, sans-serif">{{ __('mail.compose_font_sans') }}</option>
            <option value="Georgia, serif">{{ __('mail.compose_font_serif') }}</option>
            <option value="ui-monospace, monospace">{{ __('mail.compose_font_mono') }}</option>
        </select>

        {{-- Font size (execCommand fontSize: 1–7) --}}
        <select @change="cmd('fontSize', $event.target.value); $event.target.selectedIndex = 0"
            aria-label="{{ __('mail.compose_size') }}" title="{{ __('mail.compose_size') }}"
            class="min-h-9 rounded border border-gray-300 bg-white px-1.5 py-0 text-xs text-gray-700 hover:bg-gray-100 focus:border-gray-400 focus:ring-1 focus:ring-gray-400">
            <option value="" disabled selected>{{ __('mail.compose_size') }}</option>
            <option value="2">{{ __('mail.compose_size_small') }}</option>
            <option value="3">{{ __('mail.compose_size_normal') }}</option>
            <option value="5">{{ __('mail.compose_size_large') }}</option>
            <option value="6">{{ __('mail.compose_size_huge') }}</option>
        </select>

        <span class="mx-0.5 h-5 w-px bg-gray-200" aria-hidden="true"></span>

        <button type="button" @click="cmd('removeFormat')"
            aria-label="{{ __('mail.compose_clear') }}" title="{{ __('mail.compose_clear') }}"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-gray-700 hover:bg-gray-100">
            <x-icon name="x-circle" />
        </button>
    </div>

    {{-- Inline link input row (toggles instead of window.prompt) --}}
    <div x-show="showLink" x-cloak
        class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-gray-50 px-2 py-2">
        <input type="url" x-ref="linkInput" x-model="linkUrl"
            @keydown.enter.prevent="applyLink()" @keydown.escape.prevent="showLink = false"
            placeholder="https://…" aria-label="{{ __('mail.compose_link_url') }}"
            class="min-w-0 flex-1 rounded-md border border-gray-300 px-2 py-1 text-sm focus:border-gray-400 focus:ring-1 focus:ring-gray-400">
        <button type="button" @click="applyLink()"
            class="inline-flex min-h-9 items-center justify-center rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-700 hover:bg-gray-100">
            {{ __('mail.compose_link_apply') }}
        </button>
        <button type="button" @click="showLink = false; linkUrl = ''"
            class="inline-flex min-h-9 items-center justify-center rounded-md px-3 text-sm text-gray-500 hover:bg-gray-100">
            {{ __('mail.compose_link_cancel') }}
        </button>
    </div>

    {{-- Editable surface --}}
    <div x-ref="editor"
        contenteditable="true"
        role="textbox"
        aria-multiline="true"
        aria-label="{{ __('mail.compose_editor_label') }}"
        @input="sync()"
        @blur="sync()"
        class="ll-compose-editor min-h-[20rem] w-full overflow-auto px-3 py-2 text-sm text-gray-900 focus:outline-none"
    ></div>
</div>
