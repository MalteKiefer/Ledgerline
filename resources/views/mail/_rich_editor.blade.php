{{--
    Reusable dependency-free rich-text editor (toolbar + contenteditable).

    Params:
      $bind      Alpine lvalue on the surrounding scope holding the HTML string
                 (e.g. 'compose.body' or 'sigForm.html'). Default 'compose.body'.
      $minHeight Tailwind min-height class for the editable surface.

    The editor reads/writes that string: it hydrates the contenteditable from it
    (without clobbering the caret) and writes innerHTML back on input. Because
    $bind is substituted verbatim, it resolves up the parent Alpine scope chain.
--}}
@props(['bind' => 'compose.body', 'minHeight' => 'min-h-[20rem]'])
<div
    x-data="{
        showLink: false,
        linkUrl: '',
        sync() { {{ $bind }} = this.$refs.editor.innerHTML; },
        hydrate() {
            const html = {{ $bind }} ?? '';
            if (this.$refs.editor.innerHTML !== html) this.$refs.editor.innerHTML = html;
        },
        cmd(name, value = null) {
            this.$refs.editor.focus();
            document.execCommand(name, false, value);
            this.sync();
        },
        block(tag) { this.cmd('formatBlock', tag); },
        color(c) { this.cmd('foreColor', c); },
        openLink() {
            this.linkUrl = '';
            this.showLink = true;
            this.$nextTick(() => this.$refs.linkInput && this.$refs.linkInput.focus());
        },
        applyLink() {
            const url = (this.linkUrl || '').trim();
            if (url !== '') this.cmd('createLink', url);
            this.showLink = false; this.linkUrl = '';
        },
        onPaste(e) {
            // Paste as plain text so foreign markup/styles never leak in.
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
            this.sync();
        },
    }"
    x-init="hydrate(); $watch('{{ $bind }}', () => hydrate())"
    class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 focus-within:border-gray-400 focus-within:ring-1 focus-within:ring-gray-400"
>
    <div class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 px-1.5 py-1">
        @php $btn = 'inline-flex min-h-9 min-w-9 items-center justify-center rounded px-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'; @endphp
        <button type="button" @click="cmd('undo')" title="{{ __('mail.compose_undo') }}" class="{{ $btn }}"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
        <button type="button" @click="cmd('redo')" title="{{ __('mail.compose_redo') }}" class="{{ $btn }}"><x-icon name="arrow-uturn-right" class="h-4 w-4" /></button>
        <span class="mx-0.5 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>

        {{-- Paragraph / heading style --}}
        <select @change="block($event.target.value); $event.target.selectedIndex = 0" title="{{ __('mail.compose_style') }}"
            class="min-h-9 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-1.5 text-xs text-gray-700 dark:text-gray-300">
            <option value="" disabled selected>{{ __('mail.compose_style') }}</option>
            <option value="p">{{ __('mail.compose_style_p') }}</option>
            <option value="h1">{{ __('mail.compose_style_h1') }}</option>
            <option value="h2">{{ __('mail.compose_style_h2') }}</option>
            <option value="h3">{{ __('mail.compose_style_h3') }}</option>
            <option value="pre">{{ __('mail.compose_style_code') }}</option>
        </select>
        <span class="mx-0.5 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>

        <button type="button" @click="cmd('bold')" title="{{ __('mail.compose_bold') }}" class="{{ $btn }} font-bold">B</button>
        <button type="button" @click="cmd('italic')" title="{{ __('mail.compose_italic') }}" class="{{ $btn }} italic">I</button>
        <button type="button" @click="cmd('underline')" title="{{ __('mail.compose_underline') }}" class="{{ $btn }} underline">U</button>
        <button type="button" @click="cmd('strikeThrough')" title="{{ __('mail.compose_strike') }}" class="{{ $btn }} line-through">S</button>
        <label class="{{ $btn }} cursor-pointer" title="{{ __('mail.compose_color') }}">
            <span class="h-4 w-4 rounded-full border border-gray-300 dark:border-gray-600" style="background:linear-gradient(135deg,#ef4444,#3b82f6)"></span>
            <input type="color" class="sr-only" @input="color($event.target.value)">
        </label>
        <span class="mx-0.5 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>

        <button type="button" @click="cmd('insertUnorderedList')" title="{{ __('mail.compose_bullets') }}" class="{{ $btn }}">&bull;&mdash;</button>
        <button type="button" @click="cmd('insertOrderedList')" title="{{ __('mail.compose_numbers') }}" class="{{ $btn }} text-xs">1.&mdash;</button>
        <button type="button" @click="cmd('outdent')" title="{{ __('mail.compose_outdent') }}" class="{{ $btn }}">&laquo;</button>
        <button type="button" @click="cmd('indent')" title="{{ __('mail.compose_indent') }}" class="{{ $btn }}">&raquo;</button>
        <button type="button" @click="block('blockquote')" title="{{ __('mail.compose_quote') }}" class="{{ $btn }}">&ldquo;</button>
        <span class="mx-0.5 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>

        <button type="button" @click="openLink()" title="{{ __('mail.compose_link') }}" class="{{ $btn }}"><x-icon name="link" class="h-4 w-4" /></button>
        <button type="button" @click="cmd('unlink')" title="{{ __('mail.compose_unlink') }}" class="{{ $btn }}"><x-icon name="link-slash" class="h-4 w-4" /></button>
        <button type="button" @click="cmd('insertHorizontalRule')" title="{{ __('mail.compose_hr') }}" class="{{ $btn }}">&mdash;</button>
        <span class="mx-0.5 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>

        <button type="button" @click="cmd('justifyLeft')" title="{{ __('mail.compose_align_left') }}" class="{{ $btn }}"><x-icon name="bars-3-bottom-left" class="h-4 w-4" /></button>
        <button type="button" @click="cmd('justifyCenter')" title="{{ __('mail.compose_align_center') }}" class="{{ $btn }}"><x-icon name="bars-3" class="h-4 w-4" /></button>
        <button type="button" @click="cmd('justifyRight')" title="{{ __('mail.compose_align_right') }}" class="{{ $btn }}"><x-icon name="bars-3-bottom-right" class="h-4 w-4" /></button>
        <span class="mx-0.5 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>

        <select @change="cmd('fontName', $event.target.value); $event.target.selectedIndex = 0" title="{{ __('mail.compose_font') }}"
            class="min-h-9 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-1.5 text-xs text-gray-700 dark:text-gray-300">
            <option value="" disabled selected>{{ __('mail.compose_font') }}</option>
            <option value="system-ui, sans-serif">{{ __('mail.compose_font_sans') }}</option>
            <option value="Georgia, serif">{{ __('mail.compose_font_serif') }}</option>
            <option value="ui-monospace, monospace">{{ __('mail.compose_font_mono') }}</option>
        </select>
        <select @change="cmd('fontSize', $event.target.value); $event.target.selectedIndex = 0" title="{{ __('mail.compose_size') }}"
            class="min-h-9 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-1.5 text-xs text-gray-700 dark:text-gray-300">
            <option value="" disabled selected>{{ __('mail.compose_size') }}</option>
            <option value="2">{{ __('mail.compose_size_small') }}</option>
            <option value="3">{{ __('mail.compose_size_normal') }}</option>
            <option value="5">{{ __('mail.compose_size_large') }}</option>
            <option value="6">{{ __('mail.compose_size_huge') }}</option>
        </select>
        <span class="mx-0.5 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
        <button type="button" @click="cmd('removeFormat')" title="{{ __('mail.compose_clear') }}" class="{{ $btn }}"><x-icon name="x-circle" class="h-4 w-4" /></button>
    </div>

    <div x-show="showLink" x-cloak class="flex flex-wrap items-center gap-2 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 px-2 py-2">
        <input type="url" x-ref="linkInput" x-model="linkUrl"
            @keydown.enter.prevent="applyLink()" @keydown.escape.prevent="showLink = false"
            placeholder="https://…" class="min-w-0 flex-1 rounded-md border border-gray-300 dark:border-gray-700 px-2 py-1 text-sm">
        <button type="button" @click="applyLink()" class="inline-flex min-h-9 items-center rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('mail.compose_link_apply') }}</button>
        <button type="button" @click="showLink = false; linkUrl = ''" class="inline-flex min-h-9 items-center rounded-md px-3 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('mail.compose_link_cancel') }}</button>
    </div>

    <div x-ref="editor" contenteditable="true" role="textbox" aria-multiline="true"
        aria-label="{{ __('mail.compose_editor_label') }}"
        @input="sync()" @blur="sync()" @paste="onPaste($event)"
        class="ll-compose-editor {{ $minHeight }} w-full overflow-auto px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none"></div>
</div>
