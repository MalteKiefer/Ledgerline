<template>
  <div class="flex min-h-[calc(100vh-120px)] flex-col gap-4 md:h-[calc(100vh-9.5rem)] md:min-h-0 md:flex-row md:gap-0 md:overflow-hidden md:rounded-xl md:border md:border-[var(--ll-border)] md:bg-[var(--ll-surface)]">
    <!-- Left rail: accounts, folders, trash, labels, saved searches -->
    <Card body-class="p-0" class="w-full shrink-0 self-start md:h-full md:w-64 md:overflow-y-auto md:!rounded-none md:!border-y-0 md:!border-l-0 md:border-r md:border-r-[var(--ll-border)] md:!shadow-none">
      <div class="flex items-center gap-2 border-b border-[var(--ll-border)] p-3">
        <Btn variant="solid" icon="add" block class="h-[38px]" @click="openAccountEditor(null)">{{ t('mail.accounts.add') }}</Btn>
      </div>
      <nav class="space-y-0.5 px-2 pb-3">
        <!-- Unified inbox -->
        <button
          class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="isUnified ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
          @click="pickUnified"
        >
          <Icon name="all_inbox" :size="20" :class="isUnified ? '' : 'text-[var(--ll-muted)]'" />
          <span class="flex-1 text-left">{{ t('mail.list.all_mailboxes') }}</span>
          <span v-if="s.folderTotals.unread && isUnified" class="rounded-full bg-primary-500 px-1.5 text-[10px] font-semibold text-white">{{ s.folderTotals.unread }}</span>
        </button>

        <!-- Accounts + their folders -->
        <div v-if="!s.accounts.length" class="px-2.5 py-3 text-xs text-[var(--ll-muted)]">{{ t('mail.accounts.none') }}</div>
        <div v-for="a in s.accounts" :key="a.id">
          <div class="group flex items-center">
            <button
              class="flex flex-1 items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
              :class="filters.accountId === a.id ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
              @click="pickAccount(a)"
            >
              <span class="relative">
                <Icon name="inbox" :size="20" :class="filters.accountId === a.id ? '' : 'text-[var(--ll-muted)]'" />
                <span v-if="a.status === 'syncing'" class="absolute -right-1 -top-1 h-2 w-2 animate-pulse rounded-full bg-primary-500" />
                <span v-else-if="a.status === 'error'" class="absolute -right-1 -top-1 h-2 w-2 rounded-full bg-red-500" />
              </span>
              <span class="min-w-0 flex-1 truncate text-left">{{ a.name }}</span>
              <span v-if="unreadForAccount(a.id)" class="rounded-full bg-primary-500 px-1.5 text-[10px] font-semibold text-white">{{ unreadForAccount(a.id) }}</span>
            </button>
            <DropdownMenuRoot>
              <DropdownMenuTrigger class="mr-1 grid h-7 w-7 shrink-0 place-items-center rounded-md opacity-0 hover:bg-black/[0.05] group-hover:opacity-100 dark:hover:bg-white/10" @click.stop>
                <Icon name="more_vert" :size="16" />
              </DropdownMenuTrigger>
              <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] min-w-48 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
                <DropdownMenuItem v-if="a.status === 'syncing'" :class="menuItem" @select="doCancelSync(a)"><Icon name="sync_disabled" :size="18" />{{ t('mail.accounts.cancel_sync') }}</DropdownMenuItem>
                <DropdownMenuItem v-else :class="menuItem" @select="doSyncNow(a)"><Icon name="sync" :size="18" />{{ t('mail.accounts.sync_now') }}</DropdownMenuItem>
                <DropdownMenuItem :class="menuItem" @select="openAccountEditor(a)"><Icon name="edit" :size="18" />{{ t('mail.accounts.edit') }}</DropdownMenuItem>
                <DropdownMenuItem :class="menuItem" @select="openLogs(a)"><Icon name="receipt_long" :size="18" />{{ t('mail.accounts.view_logs') }}</DropdownMenuItem>
                <DropdownMenuItem :class="menuItemDanger" @select="removeAccount(a)"><Icon name="delete" :size="18" />{{ t('mail.accounts.delete') }}</DropdownMenuItem>
              </DropdownMenuContent></DropdownMenuPortal>
            </DropdownMenuRoot>
          </div>
          <!-- Folder tree (only for the selected account) -->
          <div v-if="filters.accountId === a.id" class="mb-1 mt-0.5 space-y-0.5 pl-4">
            <button
              v-for="f in foldersForAccount(a.id)" :key="f.folder"
              class="flex w-full items-center gap-2 rounded-lg py-1.5 pl-3 pr-2 text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
              :class="filters.folder === f.folder && !filters.trashed ? 'bg-primary-500/10 font-medium text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'"
              @click="pickFolder(f.folder)"
            >
              <Icon :name="folderIcon(f.folder)" :size="16" />
              <span class="min-w-0 flex-1 truncate text-left">{{ f.folder }}</span>
              <span v-if="f.unread" class="text-[10px] font-semibold">{{ f.unread }}</span>
            </button>
          </div>
        </div>
        <button class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5" :class="draftListActive ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''" @click="pickDrafts">
          <Icon name="drafts" :size="20" :class="draftListActive ? '' : 'text-[var(--ll-muted)]'" /><span class="flex-1 text-left">{{ t('mail.send.drafts') }}</span><span v-if="drafts.length" class="rounded-full bg-black/[0.06] px-1.5 text-[10px] dark:bg-white/10">{{ drafts.length }}</span>
        </button>

        <!-- Trash -->
        <div class="my-1 border-t border-[var(--ll-border)]" />
        <button
          class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="filters.trashed ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
          @click="pickTrash"
        >
          <Icon name="delete" :size="20" :class="filters.trashed ? '' : 'text-[var(--ll-muted)]'" />
          {{ t('mail.list.trash') }}
        </button>

        <!-- Labels -->
        <div class="flex items-center px-2 pb-1 pt-3">
          <span class="flex-1 text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('mail.extras.labels') }}</span>
          <Btn variant="ghost" size="xs" icon="settings" :title="t('mail.extras.labels')" @click="openLabelsMgr" />
        </div>
        <button
          v-for="l in s.labels" :key="l.id"
          class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="filters.label === l.id ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
          @click="pickLabel(l.id)"
        >
          <span class="h-3 w-3 shrink-0 rounded-full" :style="{ background: l.color }" />
          <span class="min-w-0 flex-1 truncate text-left">{{ l.name }}</span>
          <span v-if="l.message_count" class="text-[10px] text-[var(--ll-muted)]">{{ l.message_count }}</span>
        </button>

        <!-- Saved searches -->
        <template v-if="s.savedSearches.length">
          <div class="px-2 pb-1 pt-3 text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('mail.extras.saved_searches') }}</div>
          <div v-for="ss in s.savedSearches" :key="ss.id" class="group flex items-center">
            <button class="flex flex-1 items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm hover:bg-black/[0.04] dark:hover:bg-white/5" @click="applySaved(ss)">
              <Icon name="saved_search" :size="18" class="text-[var(--ll-muted)]" />
              <span class="min-w-0 flex-1 truncate text-left">{{ ss.name }}</span>
            </button>
            <Btn variant="ghost" size="xs" icon="close" class="mr-1 opacity-0 group-hover:opacity-100" @click.stop="removeSaved(ss)" />
          </div>
        </template>
      </nav>
    </Card>

    <!-- Center: toolbar + envelope table -->
    <Card body-class="flex flex-1 flex-col overflow-hidden p-0" class="flex w-full min-w-0 flex-1 flex-col self-stretch md:!rounded-none md:!border-0 md:!shadow-none">
      <!-- Toolbar -->
      <div class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] p-3">
        <Btn variant="solid" size="sm" icon="edit_square" @click="openCompose">{{ t('mail.send.compose') }}</Btn>
        <span v-if="draftListActive" class="text-sm font-semibold">{{ t('mail.send.drafts') }}</span>
        <div class="relative flex min-w-48 flex-1 items-center gap-1">
          <TextField
            id="mail-search" v-model="filters.q" :placeholder="t('mail.list.search_placeholder')" icon="search" class="flex-1"
            autocomplete="off" @update:model-value="debouncedReload" @enter="rememberSearch(); reload();"
            @focus="searchFocused = true" @blur="blurSearch"
          />
          <div v-if="searchFocused && !filters.q && searchHistory.length" class="absolute left-0 top-full z-20 mt-1 w-72 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-elevated)] p-1 shadow-lg">
            <div :class="menuSection">{{ t('mail.list.recent_searches') }}</div>
            <button
              v-for="term in searchHistory" :key="term" type="button"
              class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-black/[0.05] dark:hover:bg-white/10"
              @mousedown.prevent="filters.q = term; reload();"
            >
              <Icon name="history" :size="15" class="shrink-0 text-[var(--ll-muted)]" />
              <span class="min-w-0 flex-1 truncate">{{ term }}</span>
            </button>
          </div>
          <Btn variant="ghost" size="xs" icon="help" :title="t('mail.list.search_help')" @click="searchHelp = !searchHelp" />
          <div v-if="searchHelp" class="absolute right-0 top-full z-20 mt-1 w-80 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-elevated)] p-3 text-xs shadow-lg">
            <div class="mb-2 flex items-center gap-2"><span class="flex-1 font-semibold">{{ t('mail.list.search_help') }}</span><Btn variant="ghost" size="xs" icon="close" @click="searchHelp = false" /></div>
            <p class="mb-2 text-[var(--ll-muted)]">{{ t('mail.list.search_help_intro') }}</p>
            <ul class="space-y-1">
              <li v-for="tip in searchTips" :key="tip" class="font-mono text-[0.7rem]">{{ tip }}</li>
            </ul>
            <div class="mt-3 border-t border-[var(--ll-border)] pt-2">
              <div class="mb-1 font-semibold">{{ t('mail.list.keys') }}</div>
              <ul class="space-y-0.5">
                <li v-for="k in keyTips" :key="k" class="text-[0.7rem]">{{ k }}</li>
              </ul>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-1.5">
          <TextField v-model="dateFrom" type="date" :placeholder="t('mail.list.date_from')" class="w-36" @update:model-value="onDate" />
          <TextField v-model="dateTo" type="date" :placeholder="t('mail.list.date_to')" class="w-36" @update:model-value="onDate" />
        </div>
        <div class="ml-auto flex items-center gap-1.5">
          <Btn :variant="filters.seen === false ? 'soft' : 'ghost'" size="sm" @click="toggleUnread">{{ t('mail.list.unread') }}</Btn>
          <Btn :variant="chipOn('is:starred') ? 'soft' : 'ghost'" size="sm" icon="star" :title="t('mail.list.only_starred')" @click="toggleChip('is:starred')" />
          <Btn :variant="chipOn('has:attachment') ? 'soft' : 'ghost'" size="sm" icon="attach_file" :title="t('mail.list.only_attachment')" @click="toggleChip('has:attachment')" />
          <Btn :variant="filters.spam === true ? 'soft' : 'ghost'" size="sm" @click="toggleSpam">{{ t('mail.list.spam') }}</Btn>
          <DropdownMenuRoot>
            <DropdownMenuTrigger class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('mail.extras.export')">
              <Icon name="more_vert" :size="18" />
            </DropdownMenuTrigger>
            <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] min-w-52 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
              <DropdownMenuItem :class="menuItem" @select="markFolderRead"><Icon name="mark_email_read" :size="18" />{{ t('mail.actions.mark_all_read') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="saveCurrentSearch"><Icon name="bookmark_add" :size="18" />{{ t('mail.extras.save_search') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="doExport('mbox')"><Icon name="download" :size="18" />{{ t('mail.extras.export_mbox') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="doExport('zip')"><Icon name="folder_zip" :size="18" />{{ t('mail.extras.export_zip') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="openRules"><Icon name="rule" :size="18" />{{ t('mail.extras.rules') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="openAttachments"><Icon name="attach_file" :size="18" />{{ t('mail.extras.attachments') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="openStats"><Icon name="storage" :size="18" />{{ t('mail.extras.stats') }}</DropdownMenuItem>
            </DropdownMenuContent></DropdownMenuPortal>
          </DropdownMenuRoot>
        </div>
      </div>

      <!-- Selection bar -->
      <div v-if="s.selected.length" class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] bg-primary-500/5 px-3 py-2">
        <label class="inline-flex items-center gap-2 text-xs font-medium">
          <input type="checkbox" class="accent-primary-500" :checked="allSelected" @change="toggleSelectAll">
          {{ t('mail.actions.selected_n', { n: String(s.selected.length) }) }}
        </label>

        <div class="ml-auto flex flex-wrap items-center gap-1">
          <Btn variant="ghost" size="xs" icon="mark_email_read" :loading="bulkBusy" @click="bulkSeen(true)">{{ t('mail.actions.mark_read') }}</Btn>
          <Btn variant="ghost" size="xs" icon="mark_email_unread" :loading="bulkBusy" @click="bulkSeen(false)">{{ t('mail.actions.mark_unread') }}</Btn>
          <Btn variant="ghost" size="xs" icon="star" :loading="bulkBusy" :title="t('mail.actions.flag')" @click="bulkFlag(true)" />
          <Btn variant="ghost" size="xs" icon="star_border" :loading="bulkBusy" :title="t('mail.actions.unflag')" @click="bulkFlag(false)" />
          <Btn v-if="!filters.trashed" variant="ghost" size="xs" icon="delete" :loading="bulkBusy" @click="bulkTrash">{{ t('mail.actions.trash') }}</Btn>
          <Btn v-else variant="ghost" size="xs" icon="restore" :loading="bulkBusy" @click="bulkRestore">{{ t('mail.actions.restore') }}</Btn>

          <!-- Everything else in one grouped menu, rather than a second
               dropdown standing next to the buttons. -->
          <DropdownMenuRoot>
            <DropdownMenuTrigger class="grid h-7 w-7 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('common.actions')">
              <Icon name="more_vert" :size="16" />
            </DropdownMenuTrigger>
            <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] max-h-[70vh] min-w-56 overflow-y-auto rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
              <template v-if="s.labels.length">
                <div :class="menuSection">{{ t('mail.extras.labels') }}</div>
                <DropdownMenuItem v-for="l in s.labels" :key="l.id" :class="menuItem" @select="bulkLabel(l.id)">
                  <span class="h-3 w-3 shrink-0 rounded-full" :style="{ background: l.color }" />
                  <span class="flex-1 truncate">{{ l.name }}</span>
                  <Icon v-if="selectionHasLabel(l.id)" name="check" :size="15" class="text-[var(--ll-muted)]" />
                </DropdownMenuItem>
                <div class="my-1 h-px bg-[var(--ll-border)]" />
              </template>

              <div :class="menuSection">{{ t('mail.extras.export') }}</div>
              <DropdownMenuItem :class="menuItem" @select="doExport('mbox')"><Icon name="download" :size="18" />{{ t('mail.extras.export_mbox') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="doExport('zip')"><Icon name="folder_zip" :size="18" />{{ t('mail.extras.export_zip') }}</DropdownMenuItem>

              <div class="my-1 h-px bg-[var(--ll-border)]" />
              <DropdownMenuItem :class="menuItem" @select="s.selected = []"><Icon name="close" :size="18" />{{ t('mail.actions.clear_selection') }}</DropdownMenuItem>
            </DropdownMenuContent></DropdownMenuPortal>
          </DropdownMenuRoot>
        </div>
      </div>

      <!-- Table -->
      <div v-if="threadView && !draftListActive" class="flex items-center gap-2 border-b border-[var(--ll-border)] bg-primary-500/5 px-3 py-2 text-xs">
        <Icon name="forum" :size="15" class="text-primary-600 dark:text-primary-300" />
        <span class="flex-1">{{ t('mail.reader.thread_view') }}</span>
        <Btn variant="ghost" size="xs" icon="close" @click="clearThread">{{ t('common.clear') }}</Btn>
      </div>
      <div class="flex-1 overflow-y-auto">
        <div v-if="loading" class="py-16 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
        <div v-else-if="draftListActive && !drafts.length" class="py-16 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.list.empty') }}</div>
        <table v-else-if="draftListActive" class="w-full table-fixed text-sm"><thead class="sticky top-0 z-[1] bg-[var(--ll-surface)]"><tr class="border-b border-[var(--ll-border)] text-left text-xs text-[var(--ll-muted)]"><th class="w-[32%] py-2 pl-3 pr-3">{{ t('mail.send.from_email') }}</th><th class="py-2 pr-3">{{ t('mail.list.col_subject') }}</th><th class="w-28 py-2 pr-3 text-right">{{ t('mail.list.col_date') }}</th></tr></thead><tbody><tr v-for="draft in drafts" :key="draft.id" class="cursor-pointer border-b border-[var(--ll-border)] hover:bg-black/[0.02] dark:hover:bg-white/5" @click="openDraft(draft)"><td class="py-2.5 pl-3 pr-3"><div class="flex min-w-0 items-center gap-2"><Icon name="drafts" :size="17" class="text-primary-600" /><span class="truncate">{{ accountName(draft.mail_account_id) }}</span></div></td><td class="py-2.5 pr-3"><div class="truncate font-medium">{{ draft.subject || t('mail.send.new_draft') }}</div><div class="truncate text-xs text-[var(--ll-muted)]">{{ (draft.to ?? []).join(', ') || t('mail.send.to') }}</div></td><td class="py-2.5 pr-3 text-right text-xs text-[var(--ll-muted)]">{{ fmtDate(draft.updated_at) }}</td></tr></tbody></table>
        <div v-else-if="!s.messages.length" class="py-16 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.list.empty') }}</div>
        <!-- Phone: two-line cards. The table has fixed column widths and, with
             the columns picker, possibly six of them - unreadable on a narrow
             screen, and shrinking the columns would only make it unreadable in
             a different way. -->
        <div v-else class="divide-y divide-[var(--ll-border)] md:hidden">
          <button
            v-for="(m, ri) in s.messages" :key="`c-${m.id}`" type="button"
            class="flex w-full items-start gap-2.5 px-3 py-2.5 text-left"
            :class="[!m.seen ? 'font-semibold' : '', reader?.id === m.id ? 'bg-primary-500/[0.06]' : '']"
            @click="cursor = ri; openReader(m)"
          >
            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full text-[11px] font-semibold text-white" :style="{ background: avatarColour(m) }">{{ initials(m) }}</span>
            <span class="min-w-0 flex-1">
              <span class="flex items-baseline gap-2">
                <span class="min-w-0 flex-1 truncate text-sm">{{ senderLabel(m) }}</span>
                <span class="shrink-0 text-[11px] font-normal text-[var(--ll-muted)]">{{ fmtDate(m.date || m.created_at) }}</span>
              </span>
              <span class="block truncate text-sm">{{ m.subject || '—' }}</span>
              <span v-if="m.snippet" class="block truncate text-xs font-normal text-[var(--ll-muted)]">{{ m.snippet }}</span>
            </span>
            <span class="mt-0.5 flex shrink-0 flex-col items-end gap-1">
              <Icon v-if="!m.seen" name="circle" :size="9" class="text-primary-500" />
              <Icon v-if="m.flagged" name="star" :size="14" class="text-amber-500" />
              <Icon v-if="m.has_attachment" name="attach_file" :size="14" class="text-[var(--ll-muted)]" />
            </span>
          </button>
        </div>
        <table v-if="s.messages.length" class="hidden w-full table-fixed text-sm md:table">
          <thead class="sticky top-0 z-[1] bg-[var(--ll-surface)]">
            <tr class="border-b border-[var(--ll-border)] text-left text-xs text-[var(--ll-muted)]">
              <th class="w-9 pl-3"><input type="checkbox" class="accent-primary-500" :checked="allSelected" @change="toggleSelectAll"></th>
              <th class="w-8 cursor-pointer select-none py-2 text-center" :title="t('mail.list.sort_flagged')" @click="sortBy('flagged')"><Icon name="star" :size="15" :class="filters.sort === 'flagged' ? 'text-amber-500' : ''" /></th>
              <th
                v-for="col in activeColumns" :key="col"
                class="select-none py-2 pr-3" :class="[columnWidth(col), columnAlign(col), sortKeyFor(col) ? 'cursor-pointer' : '']"
                @click="sortKeyFor(col) && sortBy(sortKeyFor(col)!)"
              >
                <SortLabel
                  v-if="sortKeyFor(col)" :label="columnLabel(col)" :active-key="sortKeyFor(col)!"
                  :sort="listSort" :justify="columnAlign(col) === 'text-right' ? 'end' : 'start'"
                />
                <span v-else>{{ columnLabel(col) }}</span>
              </th>
              <th class="w-9">
                <DropdownMenuRoot>
                  <DropdownMenuTrigger as-child><button type="button" class="p-1" :title="t('mail.list.columns')"><Icon name="view_column" :size="16" /></button></DropdownMenuTrigger>
                  <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] max-h-[70vh] min-w-56 overflow-y-auto rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
                    <div :class="menuSection">{{ t('mail.list.columns') }}</div>
                    <div v-for="col in ALL_COLUMNS" :key="col" class="flex items-center gap-1 px-2 py-1 text-sm">
                      <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                        <input type="checkbox" class="accent-primary-500" :checked="activeColumns.includes(col)" @change="toggleColumn(col)">
                        <span class="truncate">{{ columnLabel(col) }}</span>
                      </label>
                      <button type="button" class="p-0.5 disabled:opacity-30" :disabled="!canMove(col, -1)" :title="t('mail.list.columns_up')" @click="moveColumn(col, -1)"><Icon name="arrow_upward" :size="14" /></button>
                      <button type="button" class="p-0.5 disabled:opacity-30" :disabled="!canMove(col, 1)" :title="t('mail.list.columns_down')" @click="moveColumn(col, 1)"><Icon name="arrow_downward" :size="14" /></button>
                    </div>
                    <div class="my-1 h-px bg-[var(--ll-border)]" />
                    <div :class="menuSection">{{ t('mail.list.density') }}</div>
                    <DropdownMenuItem :class="menuItem" @select="setDensity('comfortable')"><Icon name="density_medium" :size="18" />{{ t('mail.list.density_comfortable') }}<Icon v-if="density === 'comfortable'" name="check" :size="15" class="ml-auto" /></DropdownMenuItem>
                    <DropdownMenuItem :class="menuItem" @select="setDensity('compact')"><Icon name="density_small" :size="18" />{{ t('mail.list.density_compact') }}<Icon v-if="density === 'compact'" name="check" :size="15" class="ml-auto" /></DropdownMenuItem>
                    <div class="my-1 h-px bg-[var(--ll-border)]" />
                    <DropdownMenuItem :class="menuItem" @select="resetColumns"><Icon name="restart_alt" :size="18" />{{ t('mail.list.columns_reset') }}</DropdownMenuItem>
                  </DropdownMenuContent></DropdownMenuPortal>
                </DropdownMenuRoot>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(m, ri) in s.messages" :id="`mail-row-${m.id}`" :key="m.id"
              class="cursor-pointer border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5"
              :class="[!m.seen ? 'font-semibold' : '', reader?.id === m.id ? 'bg-primary-500/[0.06]' : '', cursor === ri ? 'ring-1 ring-inset ring-primary-500/60' : '']"
              @click="cursor = ri; openReader(m)"
            >
              <td class="w-9 pl-3"><input type="checkbox" class="accent-primary-500" :checked="s.selected.includes(m.id)" @click.stop="toggleSelect(m.id)"></td>
              <td class="w-8 text-center align-middle">
                <button
                  type="button" class="group/star p-1 align-middle" :title="m.flagged ? t('mail.actions.unflag') : t('mail.actions.flag')"
                  :aria-pressed="m.flagged" @click.stop="toggleFlag(m)"
                >
                  <Icon
                    :name="m.flagged ? 'star' : 'star_border'" :size="17"
                    :class="m.flagged ? 'text-amber-500' : 'text-transparent group-hover/star:text-[var(--ll-muted)] group-hover:text-[var(--ll-muted)]'"
                  />
                </button>
              </td>
              <td v-for="col in activeColumns" :key="col" class="pr-3 align-middle" :class="[density === 'compact' ? 'py-1' : 'py-2.5', columnAlign(col), col === 'date' || col === 'size' ? 'truncate text-xs font-normal text-[var(--ll-muted)]' : '']">
                <!-- from -->
                <div v-if="col === 'from'" class="flex items-center gap-2">
                  <span class="h-2 w-2 shrink-0 rounded-full" :class="m.seen ? 'bg-transparent' : 'bg-primary-500'" />
                  <span v-if="density !== 'compact'" class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-[10px] font-semibold text-white" :style="{ background: avatarColour(m) }">{{ initials(m) }}</span>
                  <!-- Clicking the sender filters by them: faster than typing
                       from: and it is the same search term either way. -->
                  <span class="min-w-0 flex-1 truncate hover:underline" @click.stop="filterBySender(m)">{{ senderLabel(m) }}</span>
                </div>
                <span v-else-if="col === 'to'" class="block truncate">{{ recipientLabel(m) }}</span>
                <!-- subject: always present, carries the inline markers -->
                <div v-else-if="col === 'subject'" class="flex items-center gap-1.5">
                  <Icon v-if="m.answered && !activeColumns.includes('answered')" name="reply" :size="15" class="shrink-0 text-[var(--ll-muted)]" :title="t('mail.list.answered')" />
                  <span class="min-w-0 flex-1 truncate">
                    {{ m.subject || '—' }}
                    <span v-if="m.snippet && !activeColumns.includes('snippet')" class="font-normal text-[var(--ll-muted)]"> — {{ m.snippet }}</span>
                  </span>
                  <template v-if="!activeColumns.includes('labels')">
                    <span v-for="l in (m.labels || [])" :key="l.id" class="hidden shrink-0 rounded px-1.5 py-0.5 text-[0.6rem] font-medium sm:inline" :style="{ background: `color-mix(in srgb, ${l.color} 15%, transparent)`, color: l.color }">{{ l.name }}</span>
                  </template>
                  <Icon v-if="m.encrypted_type && !activeColumns.includes('security')" name="lock" :size="15" class="shrink-0 text-[var(--ll-muted)]" :title="t('mail.reader.encrypted')" />
                  <Icon v-if="m.spam && !activeColumns.includes('spam')" name="report" :size="15" class="shrink-0 text-amber-500" :title="t('mail.list.spam')" />
                  <Icon v-if="m.has_attachment && !activeColumns.includes('attachment')" name="attach_file" :size="15" class="shrink-0 text-[var(--ll-muted)]" :title="t('mail.list.attachment')" />
                </div>
                <span v-else-if="col === 'snippet'" class="block truncate font-normal text-[var(--ll-muted)]">{{ m.snippet || '' }}</span>
                <div v-else-if="col === 'labels'" class="flex items-center gap-1">
                  <span v-for="l in (m.labels || [])" :key="l.id" class="shrink-0 truncate rounded px-1.5 py-0.5 text-[0.6rem] font-medium" :style="{ background: `color-mix(in srgb, ${l.color} 15%, transparent)`, color: l.color }">{{ l.name }}</span>
                </div>
                <span v-else-if="col === 'folder'" class="block truncate text-xs font-normal text-[var(--ll-muted)]">{{ m.folder }}</span>
                <span v-else-if="col === 'account'" class="block truncate text-xs font-normal text-[var(--ll-muted)]">{{ accountName(m.account_id) }}</span>
                <Icon v-else-if="col === 'attachment' && m.has_attachment" name="attach_file" :size="15" class="text-[var(--ll-muted)]" :title="t('mail.list.attachment')" />
                <Icon v-else-if="col === 'security' && m.encrypted_type" name="lock" :size="15" class="text-[var(--ll-muted)]" :title="t('mail.reader.encrypted')" />
                <Icon v-else-if="col === 'answered' && m.answered" name="reply" :size="15" class="text-[var(--ll-muted)]" :title="t('mail.list.answered')" />
                <Icon v-else-if="col === 'spam' && m.spam" name="report" :size="15" class="text-amber-500" :title="t('mail.list.spam')" />
                <template v-else-if="col === 'size'">{{ fmtBytes(m.size) }}</template>
                <template v-else-if="col === 'date'">{{ fmtDate(m.date || m.created_at) }}</template>
              </td>
              <td class="w-9" />
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination: the shared pager, so a 335-page folder can be reached by
           typing a number instead of stepping through it. -->
      <Pager
        v-if="!draftListActive"
        :page="s.meta.current_page" :per-page="s.meta.per_page" :total="s.meta.total"
        :options="[25, 50, 100, 200]"
        @update:page="goto" @update:per-page="setPerPage"
      />
    </Card>

    <!-- Reader pane: docked beside the list on desktop, full screen on small displays. -->
  <!-- Drag handle between list and reader (desktop only; on a phone the reader
       is full screen and there is nothing to split). -->
  <div
    v-if="readerOpen" class="hidden w-1 shrink-0 cursor-col-resize bg-transparent transition-colors hover:bg-primary-500/40 md:block"
    :class="splitDragging ? 'bg-primary-500/60' : ''"
    @pointerdown="splitStart"
  />
  <aside
    v-if="readerOpen"
    class="fixed inset-0 z-[1500] flex min-h-0 flex-col overflow-y-auto bg-[var(--ll-surface)] shadow-2xl md:static md:z-auto md:w-auto md:shrink-0 md:border-l md:border-[var(--ll-border)] md:shadow-none"
    :style="readerStyle"
  >
    <div v-if="readerOpen && reader" class="flex min-h-0 flex-1 flex-col gap-4 p-4 md:p-5">
      <div class="sticky top-0 z-10 -mt-4 -mx-4 flex items-center gap-3 border-b border-[var(--ll-border)] bg-[var(--ll-surface)] px-4 py-3 md:-mt-5 md:-mx-5 md:px-5">
        <div class="min-w-0 flex-1">
          <div class="truncate text-base font-semibold">{{ reader.subject || t('mail.reader.subject') }}</div>
          <div class="truncate text-xs text-[var(--ll-muted)]">{{ reader.from_name || reader.from_email }}</div>
        </div>
        <Btn variant="ghost" size="sm" icon="keyboard_arrow_up" :disabled="!hasNeighbour(-1)" :title="t('mail.reader.previous')" @click="moveCursor(-1)" />
        <Btn variant="ghost" size="sm" icon="keyboard_arrow_down" :disabled="!hasNeighbour(1)" :title="t('mail.reader.next')" @click="moveCursor(1)" />
        <Btn variant="ghost" size="sm" icon="close" :title="t('common.close')" @click="readerOpen = false" />
      </div>
      <!-- Header -->
      <div class="flex flex-col gap-1.5">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-sm font-semibold">{{ reader.from_name || reader.from_email }}</span>
          <span v-if="reader.from_name && reader.from_email" class="text-xs text-[var(--ll-muted)]">&lt;{{ reader.from_email }}&gt;</span>
          <span class="ml-auto text-xs text-[var(--ll-muted)]">{{ fmtDateTime(reader.date || reader.created_at) }}</span>
        </div>
        <div class="text-xs text-[var(--ll-muted)]"><span class="font-medium">{{ t('mail.reader.to') }}:</span> {{ addrList(reader.to) }}</div>
        <div v-if="reader.cc?.length" class="text-xs text-[var(--ll-muted)]"><span class="font-medium">{{ t('mail.reader.cc') }}:</span> {{ addrList(reader.cc) }}</div>
        <div class="flex flex-wrap items-center gap-1.5 pt-1">
          <Badge v-if="reader.spam" tone="warning">{{ t('mail.reader.spam') }}</Badge>
          <Badge v-if="reader.spf" :tone="authTone(reader.spf)">{{ t('mail.reader.spf') }}: {{ reader.spf }}</Badge>
          <Badge v-if="reader.dkim" :tone="authTone(reader.dkim)">{{ t('mail.reader.dkim') }}: {{ reader.dkim }}</Badge>
          <Badge v-if="reader.dmarc" :tone="authTone(reader.dmarc)">{{ t('mail.reader.dmarc') }}: {{ reader.dmarc }}</Badge>
          <Badge v-if="reader.encrypted_type === 'pgp'" tone="info">{{ t('mail.reader.encrypted_pgp') }}<span v-if="reader.decrypt_status"> · {{ reader.decrypt_status }}</span></Badge>
          <Badge v-if="reader.encrypted_type === 'smime'" tone="info">{{ t('mail.reader.encrypted_smime') }}<span v-if="reader.decrypt_status"> · {{ reader.decrypt_status }}</span></Badge>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-1 border-y border-[var(--ll-border)] py-2">
        <template v-if="readerCanSend">
          <Btn variant="soft" size="sm" icon="reply" :title="t('mail.send.reply')" @click="openReply(false)" />
          <Btn variant="ghost" size="sm" icon="reply_all" :title="t('mail.send.reply_all')" @click="openReply(true)" />
          <Btn variant="ghost" size="sm" icon="forward" :title="t('mail.send.forward')" @click="openForward" />
          <span class="mx-0.5 h-4 w-px bg-[var(--ll-border)]" />
        </template>
        <span v-else class="mr-1 inline-flex items-center gap-1 text-xs text-[var(--ll-muted)]"><Icon name="info" :size="14" />{{ t('mail.send.no_smtp') }}</span>
        <Btn v-if="!reader.trashed" variant="ghost" size="sm" icon="delete" :title="t('mail.actions.trash')" @click="readerTrash(reader)" />
        <Btn v-else variant="ghost" size="sm" icon="restore" :title="t('mail.actions.restore')" @click="readerRestore(reader)" />
        <DropdownMenuRoot>
          <DropdownMenuTrigger class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('mail.reader.more_actions')"><Icon name="more_vert" :size="18" /></DropdownMenuTrigger>
          <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="start" class="z-[1600] min-w-48 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
            <DropdownMenuItem :class="menuItem" @select="doPushBack(reader)"><Icon name="move_to_inbox" :size="18" />{{ t('mail.actions.push_back') }}</DropdownMenuItem>
            <DropdownMenuItem :class="menuItemDanger" @select="doDeleteOrigin(reader)"><Icon name="delete_sweep" :size="18" />{{ t('mail.actions.delete_origin') }}</DropdownMenuItem>
            <DropdownMenuItem v-if="reader.thread_id" :class="menuItem" @select="viewThread"><Icon name="forum" :size="18" />{{ t('mail.reader.view_thread_list') }}</DropdownMenuItem>
            <DropdownMenuItem :class="menuItem" :disabled="printing" @select="printMessage"><Icon name="print" :size="18" />{{ t('mail.reader.print') }}</DropdownMenuItem>
            <DropdownMenuItem :class="menuItem" :disabled="pdfExporting" @select="exportPdf"><Icon name="picture_as_pdf" :size="18" />{{ t('mail.reader.export_pdf') }}</DropdownMenuItem>
            <DropdownMenuItem v-if="unsubscribeTargets.length" :class="menuItem" @select="unsubscribeOpen = true"><Icon name="unsubscribe" :size="18" />{{ t('mail.reader.unsubscribe') }}</DropdownMenuItem>
            <DropdownMenuItem :class="menuItem" @select="downloadEml"><Icon name="download" :size="18" />{{ t('mail.reader.download_eml') }}</DropdownMenuItem>
            <DropdownMenuItem :class="menuItem" @select="readerSetSeen(!reader.seen)"><Icon :name="reader.seen ? 'mark_email_unread' : 'mark_email_read'" :size="18" />{{ reader.seen ? t('mail.actions.mark_unread') : t('mail.actions.mark_read') }}</DropdownMenuItem>
            <DropdownMenuItem v-if="hasHtml" :class="menuItem" @select="toggleRemote"><Icon :name="remoteOn ? 'visibility_off' : 'visibility'" :size="18" />{{ remoteOn ? t('mail.reader.block_remote') : t('mail.reader.load_remote') }}</DropdownMenuItem>
            <DropdownMenuItem :class="menuItem" @select="showHeaders = !showHeaders"><Icon :name="showHeaders ? 'expand_less' : 'expand_more'" :size="18" />{{ t('mail.reader.original_headers') }}</DropdownMenuItem>
            <template v-if="s.labels.length">
              <div class="my-1 border-t border-[var(--ll-border)]" />
              <div class="px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('mail.extras.labels') }}</div>
            </template>
            <DropdownMenuItem v-for="l in s.labels" :key="l.id" :class="menuItem" @select="toggleReaderLabel(l.id)">
              <span class="h-3 w-3 rounded-full" :style="{ background: l.color }" />
              <span class="flex-1">{{ l.name }}</span>
              <Icon v-if="readerHasLabel(l.id)" name="check" :size="16" class="text-primary-600 dark:text-primary-300" />
            </DropdownMenuItem>
          </DropdownMenuContent></DropdownMenuPortal>
        </DropdownMenuRoot>
        <span class="ml-auto inline-flex items-center gap-1.5 pr-1 text-xs text-[var(--ll-muted)]"><Icon :name="remoteOn ? 'visibility' : 'shield'" :size="15" />{{ remoteOn ? t('mail.reader.remote_loaded') : t('mail.reader.remote_blocked') }}</span>
      </div>

      <!-- Earlier messages of the conversation, collapsed. -->
      <div v-if="inThread" class="divide-y divide-[var(--ll-border)] rounded-lg border border-[var(--ll-border)]">
        <div class="flex items-center gap-2 px-3 py-1.5 text-[0.65rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">
          <Icon name="forum" :size="14" />
          {{ t('mail.reader.conversation_n', { n: String(threadMessages.length) }) }}
          <button type="button" class="ml-auto font-medium normal-case underline hover:text-primary-500" @click="viewThread">{{ t('mail.reader.view_thread_list') }}</button>
        </div>
        <button
          v-for="tm in threadMessages" :key="tm.id" type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs hover:bg-black/[0.03] dark:hover:bg-white/5"
          :class="tm.id === reader.id ? 'bg-primary-500/[0.07]' : ''"
          @click="tm.id === reader.id ? undefined : openReader(tm)"
        >
          <Icon :name="tm.id === reader.id ? 'expand_less' : 'expand_more'" :size="15" class="shrink-0 text-[var(--ll-muted)]" />
          <span class="w-40 shrink-0 truncate" :class="!tm.seen ? 'font-semibold' : ''">{{ senderLabel(tm) }}</span>
          <span class="min-w-0 flex-1 truncate text-[var(--ll-muted)]">{{ tm.id === reader.id ? t('mail.reader.shown_below') : (tm.snippet || '') }}</span>
          <Icon v-if="tm.has_attachment" name="attach_file" :size="14" class="shrink-0 text-[var(--ll-muted)]" />
          <span class="shrink-0 tabular-nums text-[var(--ll-muted)]">{{ fmtDate(tm.date || tm.created_at) }}</span>
        </button>
      </div>

      <pre v-if="showHeaders" class="max-h-52 overflow-auto rounded-lg bg-black/[0.03] p-3 text-xs dark:bg-white/5">{{ reader.headers_raw || '—' }}</pre>

      <!-- Body -->
      <iframe
        v-if="hasHtml"
        :key="reader.id"
        :src="s.bodyUrl(reader.id, remoteOn)"
        sandbox=""
        class="h-[48vh] w-full rounded-lg border border-[var(--ll-border)] bg-white"
      ></iframe>
      <pre v-else-if="reader.text_body" class="max-h-[48vh] overflow-auto whitespace-pre-wrap break-words rounded-lg border border-[var(--ll-border)] p-3 text-sm">{{ reader.text_body }}</pre>
      <div v-else class="rounded-lg border border-[var(--ll-border)] p-6 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.reader.no_body') }}</div>

      <!-- Attachments -->
      <div v-if="reader.attachments?.length">
        <div class="mb-1.5 text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('mail.reader.attachments') }}</div>
        <div class="divide-y divide-[var(--ll-border)]">
          <div v-for="att in reader.attachments" :key="att.id" class="flex items-center gap-2 py-2">
            <Icon name="attach_file" :size="18" class="shrink-0 text-[var(--ll-muted)]" />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm">{{ att.filename || att.content_type || '—' }}</span>
              <span class="block text-xs text-[var(--ll-muted)]">{{ fmtBytes(att.size) }}<span v-if="att.inline"> · cid</span></span>
            </span>
            <Btn variant="ghost" size="xs" icon="visibility" tag="a" :href="s.attachmentRawUrl(att.id)" target="_blank" rel="noopener" :title="t('mail.reader.attachment_view')" />
            <Btn variant="ghost" size="xs" icon="download" tag="a" :href="s.attachmentRawUrl(att.id, true)" :title="t('mail.reader.attachment_download')" />
            <DropdownMenuRoot>
              <DropdownMenuTrigger class="grid h-7 w-7 place-items-center rounded-md hover:bg-black/[0.05] dark:hover:bg-white/10"><Icon name="more_vert" :size="16" /></DropdownMenuTrigger>
              <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] min-w-44 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
                <div class="px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('mail.reader.save_section') }}</div>
                <DropdownMenuItem :class="menuItem" @select="openAttSaveToFiles(att.id)"><Icon name="folder" :size="18" />{{ t('mail.reader.save_to_files') }}</DropdownMenuItem>
                <DropdownMenuItem :class="menuItem" @select="saveAtt(att.id, 'paperless')"><Icon name="description" :size="18" />{{ t('mail.reader.save_to_paperless') }}</DropdownMenuItem>
                <!-- Invoices arrive by mail; from here the receipt inbox does the
                     rest (OCR, amount/date, partner, matching against a booking). -->
                <DropdownMenuItem v-if="auth.can('finance')" :class="menuItem" @select="saveAtt(att.id, 'finance')"><Icon name="receipt_long" :size="18" />{{ t('mail.reader.save_to_finance') }}</DropdownMenuItem>
                <div class="my-1 border-t border-[var(--ll-border)]" />
                <div class="px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('mail.reader.security_section') }}</div>
                <DropdownMenuItem :class="menuItem" :disabled="attachmentVirusLoading === att.id" @select="scanAttachment(att.id)"><Icon name="security" :size="18" />{{ t('mail.reader.virustotal_check') }}</DropdownMenuItem>
              </DropdownMenuContent></DropdownMenuPortal>
            </DropdownMenuRoot>
            <Badge v-if="attachmentVirusResults[att.id]?.stats" :tone="(attachmentVirusResults[att.id]?.stats?.malicious ?? 0) || (attachmentVirusResults[att.id]?.stats?.suspicious ?? 0) ? 'error' : 'success'">{{ (attachmentVirusResults[att.id]?.stats?.malicious ?? 0) + (attachmentVirusResults[att.id]?.stats?.suspicious ?? 0) }}</Badge>
          </div>
        </div>
      </div>
    </div>
  </aside>
  <section
    v-if="compose.show && !compose.minimized"
    class="fixed bottom-3 right-4 z-[1500] flex h-[min(48rem,calc(100vh-6rem))] w-[min(44rem,calc(100vw-2rem))] min-h-0 flex-col overflow-hidden rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] shadow-2xl"
    @dragenter.prevent="composeDragDepth++" @dragover.prevent
    @dragleave.prevent="composeDragDepth = Math.max(0, composeDragDepth - 1)"
    @drop.prevent="onComposeDrop"
  >
    <div v-if="composeDragDepth > 0" class="pointer-events-none absolute inset-0 z-10 grid place-items-center bg-primary-500/10 backdrop-blur-[1px]">
      <div class="rounded-lg bg-primary-500 px-3 py-2 text-sm font-medium text-white shadow-lg">{{ t('mail.send.drop_here') }}</div>
    </div>
      <header class="border-b border-[var(--ll-border)] bg-[var(--ll-surface)]">
        <div class="flex items-center gap-2 px-4 py-3 md:px-5">
          <div class="min-w-0 flex-1"><div class="text-base font-semibold">{{ composeTitle }}</div><div class="flex items-center gap-1 text-xs text-[var(--ll-muted)]"><Icon :name="composeSaving ? 'sync' : 'cloud_done'" :size="14" :class="composeSaving ? 'animate-spin' : ''" />{{ composeSaving ? t('mail.send.draft_saving') : t('mail.send.draft_saved') }}</div></div>
          <Btn variant="solid" size="sm" icon="send" :loading="compose.sending" :title="t('mail.send.send_hint')" @click="doSend">{{ t('mail.send.send') }}</Btn>
          <Btn variant="ghost" size="sm" icon="minimize" :title="t('mail.send.minimize')" @click="compose.minimized = true" />
          <DropdownMenuRoot>
            <DropdownMenuTrigger class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('mail.send.more_actions')"><Icon name="more_vert" :size="18" /></DropdownMenuTrigger>
            <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] min-w-52 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
              <div class="px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('mail.send.draft_section') }}</div>
              <DropdownMenuItem :class="menuItem" :disabled="composeSaving" @select="saveDraftNow"><Icon name="save" :size="18" />{{ t('mail.send.save_now') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItemDanger" @select="discardCompose"><Icon name="delete" :size="18" />{{ t('mail.send.discard_draft') }}</DropdownMenuItem>
            </DropdownMenuContent></DropdownMenuPortal>
          </DropdownMenuRoot>
          <Btn variant="ghost" size="sm" icon="close" :title="t('common.close')" @click="closeCompose" />
        </div>
        <div class="flex flex-wrap items-center gap-1 border-t border-[var(--ll-border)] px-4 py-2 md:px-5" role="toolbar" :aria-label="t('mail.send.compose_toolbar')">
          <label class="inline-flex cursor-pointer"><input type="file" multiple class="hidden" @change="onComposeFiles"><Btn variant="ghost" size="xs" icon="upload_file" :title="t('mail.send.from_device')" /></label>
          <Btn variant="ghost" size="xs" icon="folder" :title="t('mail.send.from_files')" @click="openAssetPicker('files')" />
          <Btn variant="ghost" size="xs" icon="photo_library" :title="t('mail.send.from_gallery')" @click="openAssetPicker('gallery')" />
          <span v-if="composeAttachmentCount" class="mr-1 text-xs font-medium text-[var(--ll-muted)]">{{ composeAttachmentCount }}</span>
          <Btn variant="ghost" size="xs" icon="lock" :class="composeShowCrypto ? 'bg-primary-500/10 text-primary-700 dark:text-primary-300' : ''" :title="t('mail.send.security_options')" @click="openCryptoOptions" />
          <DropdownMenuRoot>
            <DropdownMenuTrigger class="grid h-7 w-7 place-items-center rounded-md hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('mail.send.delivery_options')"><Icon name="tune" :size="18" /></DropdownMenuTrigger>
            <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="start" class="z-[1600] min-w-64 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
              <div v-if="compose.mode !== 'reply'" class="px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('mail.send.layout_section') }}</div>
              <DropdownMenuCheckboxItem v-if="compose.mode !== 'reply'" :checked="composeShowCc" :class="menuItem" @select.prevent="composeShowCc = !composeShowCc"><Icon name="group" :size="18" />Cc</DropdownMenuCheckboxItem>
              <DropdownMenuCheckboxItem v-if="compose.mode === 'compose'" :checked="composeShowBcc" :class="menuItem" @select.prevent="composeShowBcc = !composeShowBcc"><Icon name="visibility_off" :size="18" />Bcc</DropdownMenuCheckboxItem>
              <div class="my-1 border-t border-[var(--ll-border)]" />
              <div class="px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('mail.send.delivery_options') }}</div>
              <DropdownMenuCheckboxItem :checked="compose.readReceipt" :class="menuItem" @select.prevent="compose.readReceipt = !compose.readReceipt"><Icon name="mark_email_unread" :size="18" />{{ t('mail.send.read_receipt') }}</DropdownMenuCheckboxItem>
              <DropdownMenuCheckboxItem :checked="compose.highPriority" :class="menuItem" @select.prevent="compose.highPriority = !compose.highPriority"><Icon name="priority_high" :size="18" />{{ t('mail.send.high_priority') }}</DropdownMenuCheckboxItem>
              <div class="px-3 pb-2 pt-1" @click.stop><label class="block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.send.sent_folder') }}<input v-model="compose.sentFolder" class="mt-1 w-full rounded-md border border-[var(--ll-border)] bg-transparent px-2 py-1.5 text-sm outline-none focus:border-primary-500" :placeholder="t('mail.send.sent_folder_hint')"></label></div>
            </DropdownMenuContent></DropdownMenuPortal>
          </DropdownMenuRoot>
        </div>
      </header>
      <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4 md:p-5">
        <div class="grid gap-2 sm:grid-cols-2">
          <Select v-if="compose.mode === 'compose'" v-model.number="compose.accountId" :label="t('mail.send.from_email')" :options="composeAccountItems" />
          <Select v-if="compose.accountId" v-model.number="compose.signatureId" :label="t('mail.send.signature')" :options="composeSignatureItems" />
        </div>
        <div v-if="compose.mode === 'reply'" class="rounded-lg bg-black/[0.03] px-3 py-2 text-xs dark:bg-white/5"><span class="font-medium">{{ t('mail.send.to') }}:</span> {{ compose.recipientHint || '—' }}<span v-if="compose.replyAll" class="ml-1 text-[var(--ll-muted)]">· {{ t('mail.send.reply_all') }}</span></div>
        <template v-if="compose.mode !== 'reply'">
          <div class="relative"><TextField v-model="compose.to" :label="t('mail.send.to')" placeholder="name@example.com, …" autocomplete="off" @update:model-value="lookupRecipients" />
            <div v-if="recipientSuggestions.length" class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] shadow-lg">
              <button v-for="entry in recipientSuggestions" :key="entry.email" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-black/[0.04] dark:hover:bg-white/5" @click="addRecipient(entry.email)"><Icon name="person" :size="16" class="text-[var(--ll-muted)]" /><span class="min-w-0 flex-1 truncate">{{ entry.name || entry.email }}</span><span v-if="entry.name" class="truncate text-xs text-[var(--ll-muted)]">{{ entry.email }}</span></button>
            </div>
          </div>
          <TextField v-if="composeShowCc" v-model="compose.cc" :label="t('mail.send.cc')" placeholder="name@example.com, …" autocomplete="off" />
          <TextField v-if="compose.mode === 'compose' && composeShowBcc" v-model="compose.bcc" :label="t('mail.send.bcc')" placeholder="name@example.com, …" autocomplete="off" />
        </template>
        <TextField v-if="compose.mode === 'compose'" v-model="compose.subject" :label="t('mail.send.subject')" />
        <div v-if="composeShowCrypto" class="space-y-3 rounded-lg border border-primary-500/25 bg-primary-500/[0.04] p-3 dark:bg-primary-500/10">
          <div class="flex items-center gap-2"><Icon name="lock" :size="18" class="text-primary-600" /><div><div class="text-sm font-semibold">{{ t('mail.send.security_options') }}</div><p class="text-xs text-[var(--ll-muted)]">{{ t('mail.send.security_hint') }}</p></div></div>
          <div class="grid gap-2 sm:grid-cols-3"><Select v-model="compose.cryptoMode" :label="t('mail.send.crypto_mode')" :options="cryptoModeItems" /><Select v-if="compose.cryptoMode !== 'none'" v-model="compose.cryptoType" :label="t('mail.send.crypto_type')" :options="cryptoTypeItems" /><Select v-if="compose.cryptoMode !== 'none'" v-model.number="compose.signingKeyId" :label="t('mail.send.signing_key')" :options="signingKeyItems" /></div>
          <div v-if="compose.cryptoMode === 'encrypt' || compose.cryptoMode === 'sign_encrypt'"><div class="mb-1 text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.send.recipient_keys') }}</div><div v-if="!recipientKeyItems.length" class="text-xs text-[var(--ll-muted)]">{{ t('mail.send.no_recipient_keys') }}</div><label v-for="key in recipientKeyItems" :key="key.value" class="mr-4 inline-flex items-center gap-2 text-sm"><input v-model="compose.recipientKeyIds" type="checkbox" :value="Number(key.value)" class="accent-primary-500">{{ key.title }}</label></div>
        </div>
        <RichTextEditor v-model="compose.html" :placeholder="t('mail.send.body')" :labels="composeEditorLabels" @update:model-value="onComposeRichText" />
        <div class="rounded-lg border border-[var(--ll-border)] p-3">
          <div class="mb-2 flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('mail.send.attachments') }}</span><span class="text-xs text-[var(--ll-muted)]">{{ t('mail.send.attachments_hint') }}</span></div>
          <div v-if="!compose.files.length && !compose.fileIds.length && !compose.galleryPhotoIds.length" class="text-xs text-[var(--ll-muted)]">{{ t('mail.send.attachments_hint') }}</div>
          <div v-for="(f, i) in compose.files" :key="`local-${i}`" class="flex items-center gap-2 py-1 text-sm"><Icon name="attach_file" :size="15" /><span class="min-w-0 flex-1 truncate">{{ f.name }}</span><span class="text-xs text-[var(--ll-muted)]">{{ fmtBytes(f.size) }}</span><Btn variant="ghost" size="xs" icon="close" @click="removeComposeFile(i)" /></div>
          <div v-for="f in selectedFiles" :key="`file-${f.id}`" class="flex items-center gap-2 py-1 text-sm"><Icon name="description" :size="15" /><span class="min-w-0 flex-1 truncate">{{ f.name }}</span><span class="text-xs text-[var(--ll-muted)]">{{ fmtBytes(f.size) }}</span><Btn variant="ghost" size="xs" icon="close" @click="removeFileAttachment(f.id)" /></div>
          <div v-for="photo in selectedPhotos" :key="`photo-${photo.id}`" class="flex items-center gap-2 py-1 text-sm"><Icon name="image" :size="15" /><span class="min-w-0 flex-1 truncate">{{ photo.name }}</span><span class="text-xs text-[var(--ll-muted)]">{{ fmtBytes(photo.size) }}</span><Btn variant="ghost" size="xs" icon="close" @click="removeGalleryAttachment(photo.id)" /></div>
        </div>
      </div>
      <footer class="flex items-center gap-2 border-t border-[var(--ll-border)] px-4 py-3 md:px-5"><span class="flex-1 text-xs text-[var(--ll-muted)]">{{ compose.draftId ? t('mail.send.draft_saved') : t('mail.send.new_draft') }}</span><span class="hidden text-xs text-[var(--ll-muted)] sm:inline">{{ t('mail.send.send_hint') }}</span><Btn variant="solid" icon="send" :loading="compose.sending" @click="doSend">{{ t('mail.send.send') }}</Btn></footer>
    </section>
  </div>

  <div v-if="drafts.length || compose.show" class="fixed bottom-3 right-4 z-40 flex max-w-[min(70rem,calc(100vw-2rem))] gap-1 overflow-x-auto rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-xl">
    <button v-if="compose.show" class="flex min-w-44 items-center gap-2 rounded-md bg-primary-500/10 px-3 py-2 text-left text-xs text-primary-700 hover:bg-primary-500/15" @click="compose.minimized = false"><Icon name="edit_square" :size="16" /><span class="min-w-0 flex-1 truncate">{{ compose.subject || t('mail.send.new_draft') }}</span><Icon v-if="compose.minimized" name="expand_less" :size="16" /></button>
    <button v-for="draft in drafts.filter((draft) => draft.id !== compose.draftId)" :key="draft.id" class="flex min-w-40 items-center gap-2 rounded-md px-3 py-2 text-left text-xs hover:bg-black/[0.04] dark:hover:bg-white/5" @click="openDraft(draft)"><Icon name="drafts" :size="16" class="text-primary-600" /><span class="min-w-0 flex-1 truncate">{{ draft.subject || t('mail.send.new_draft') }}</span></button>
  </div>

  <!-- Account editor modal -->
  <Modal v-model="editor.show" :title="editor.id ? t('mail.accounts.edit') : t('mail.accounts.add')" width="640px">
    <div class="space-y-3">
      <div v-if="!editor.id" class="grid grid-cols-2 gap-2 border-b border-[var(--ll-border)] pb-3 text-xs font-semibold">
        <div class="flex items-center gap-2" :class="editor.step === 1 ? 'text-primary-600' : 'text-[var(--ll-muted)]'"><span class="grid h-5 w-5 place-items-center rounded-full" :class="editor.step === 1 ? 'bg-primary-500 text-white' : 'bg-black/[0.06] dark:bg-white/10'">1</span>{{ t('mail.setup.discover') }}</div>
        <div class="flex items-center gap-2" :class="editor.step === 2 ? 'text-primary-600' : 'text-[var(--ll-muted)]'"><span class="grid h-5 w-5 place-items-center rounded-full" :class="editor.step === 2 ? 'bg-primary-500 text-white' : 'bg-black/[0.06] dark:bg-white/10'">2</span>{{ t('mail.setup.details') }}</div>
      </div>
      <template v-if="!editor.id && editor.step === 1">
        <div class="rounded-xl bg-primary-500/[0.07] p-4">
          <div class="mb-1 flex items-center gap-2 text-sm font-semibold"><Icon name="auto_awesome" :size="18" />{{ t('mail.setup.title') }}</div>
          <p class="text-xs leading-5 text-[var(--ll-muted)]">{{ t('mail.setup.hint') }}</p>
        </div>
        <TextField v-model="editor.email" :label="t('mail.setup.email')" type="email" inputmode="email" autocomplete="email" @enter="discoverAccount" />
        <div v-if="editor.discovery" class="rounded-lg px-3 py-2 text-xs" :class="editor.discovery.domain_resolves ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/10 text-amber-700 dark:text-amber-300'">
          {{ editor.discovery.domain_resolves ? t('mail.setup.dns_ok') : t('mail.setup.dns_missing') }}<span v-if="editor.discovery.outlook_autodiscover"> · {{ t('mail.setup.outlook_found') }}</span>
        </div>
        <Btn variant="solid" class="w-full" icon="search" :loading="editor.detecting" @click="discoverAccount">{{ t('mail.setup.detect') }}</Btn>
        <Btn variant="ghost" class="w-full" @click="editor.step = 2">{{ t('mail.setup.manual') }}</Btn>
      </template>
      <template v-else>
      <TextField v-model="editor.form.name" :label="t('mail.form.name')" />
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2"><TextField v-model="editor.form.host" :label="t('mail.form.host')" /></div>
        <TextField v-model.number="editor.form.port" :label="t('mail.form.port')" type="number" inputmode="numeric" />
      </div>
      <TextField v-model="editor.form.username" :label="t('mail.form.username')" autocomplete="off" />
      <TextField v-model="editor.form.password" :label="t('mail.form.password')" type="password" autocomplete="new-password" :hint="editor.id ? t('mail.form.password_keep') : undefined" />
      <Select v-model="editor.form.encryption" :label="t('mail.form.encryption')" :options="encItems" @update:model-value="applyImapPort" />

      <!-- SMTP (outgoing) — enables compose / reply / forward -->
      <div class="mt-1 border-t border-[var(--ll-border)] pt-3">
        <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[var(--ll-muted)]"><Icon name="send" :size="15" />{{ t('mail.send.compose') }}</div>
        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-2"><TextField v-model="editor.form.smtp_host" :label="t('mail.send.smtp_host')" autocomplete="off" /></div>
          <TextField v-model.number="editor.form.smtp_port" :label="t('mail.send.smtp_port')" type="number" inputmode="numeric" />
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3">
          <TextField v-model="editor.form.smtp_username" :label="t('mail.send.smtp_username')" autocomplete="off" />
          <TextField v-model="editor.form.smtp_password" :label="t('mail.send.smtp_password')" type="password" autocomplete="new-password" :hint="editor.id && editor.hasSmtpPassword ? t('mail.form.password_keep') : undefined" />
        </div>
        <div class="mt-3"><Select v-model="editor.form.smtp_encryption" :label="t('mail.send.smtp_encryption')" :options="encItems" @update:model-value="applySmtpPort" /></div>
        <div class="mt-3 grid grid-cols-2 gap-3">
          <TextField v-model="editor.form.from_name" :label="t('mail.send.from_name')" />
          <TextField v-model="editor.form.from_email" :label="t('mail.send.from_email')" type="email" inputmode="email" autocomplete="off" />
        </div>
      </div>

      <div>
        <div class="mb-1.5 text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.form.folders') }}</div>
        <div class="mb-1.5 flex flex-wrap gap-1.5">
          <Badge v-for="(f, i) in editor.folders" :key="i" tone="gray">
            {{ f }}
            <button type="button" class="grid place-items-center rounded-full text-[var(--ll-muted)] hover:text-red-600" @click="editor.folders.splice(i, 1)"><Icon name="close" :size="13" /></button>
          </Badge>
        </div>
        <TextField v-model="folderInput" :placeholder="t('mail.form.folders_hint')" @enter="addFolder" />
      </div>
      <TextField v-model="editor.form.backfill_since" :label="t('mail.form.backfill_since')" type="date" />
      <TextField v-model.number="editor.form.sync_interval_minutes" :label="t('mail.form.sync_interval')" type="number" inputmode="numeric" :hint="t('mail.form.sync_interval_hint')" />
      <label class="flex items-center gap-2 text-sm"><input v-model="editor.form.enabled" type="checkbox" class="accent-primary-500">{{ t('mail.form.enabled') }}</label>
      <label class="flex items-center gap-2 text-sm"><input v-model="editor.form.skip_spam" type="checkbox" class="accent-primary-500">{{ t('mail.form.skip_spam') }}</label>
      <label class="flex items-center gap-2 text-sm"><input v-model="editor.form.delete_after_import" type="checkbox" class="accent-primary-500">{{ t('mail.form.delete_after_import') }}</label>
      <p v-if="editor.form.delete_after_import" class="rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-600 dark:text-amber-400">{{ t('mail.form.delete_after_import_warn') }}</p>
      <div v-if="editor.testResult" class="rounded-lg px-3 py-2 text-xs" :class="editor.testResult.ok ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'">
        {{ editor.testResult.ok ? t('mail.accounts.test_ok') : t('mail.accounts.test_failed') }}<span v-if="editor.testResult.detail"> — {{ editor.testResult.detail }}</span>
      </div>
      </template>
    </div>
    <template #footer>
      <Btn v-if="editor.id" variant="outline" :loading="editor.testing" class="mr-auto" @click="runTest">{{ t('mail.accounts.test') }}</Btn>
      <Btn v-else-if="editor.step === 2" variant="ghost" class="mr-auto" @click="editor.step = 1">{{ t('mail.setup.back') }}</Btn>
      <Btn variant="ghost" @click="editor.show = false">{{ t('mail.form.cancel') }}</Btn>
      <Btn v-if="editor.id || editor.step === 2" variant="solid" :loading="editor.saving" @click="saveAccount">{{ t('mail.form.save') }}</Btn>
    </template>
  </Modal>

  <!-- Sync log modal -->
  <Modal v-model="logsDlg.show" :title="t('mail.logs.title')" width="720px">
    <div class="mb-3 flex items-center gap-2">
      <Select v-model="logsDlg.level" :options="logLevelItems" class="w-40" @update:model-value="refreshLogs" />
      <Btn variant="ghost" size="sm" icon="refresh" @click="refreshLogs" />
    </div>
    <div v-if="!s.logs.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.logs.empty') }}</div>
    <div v-else class="max-h-[50vh] divide-y divide-[var(--ll-border)] overflow-y-auto">
      <div v-for="log in s.logs" :key="log.id" class="flex items-start gap-2 py-2 text-xs">
        <Badge :tone="log.level === 'error' ? 'error' : log.level === 'warn' ? 'warning' : 'gray'">{{ t('mail.logs.level_' + log.level) }}</Badge>
        <div class="min-w-0 flex-1">
          <div class="font-medium">{{ log.event }}<span v-if="log.folder" class="text-[var(--ll-muted)]"> · {{ log.folder }}</span></div>
          <div v-if="log.message" class="break-words text-[var(--ll-muted)]">{{ log.message }}</div>
        </div>
        <span class="shrink-0 text-[var(--ll-muted)]">{{ fmtDateTime(log.created_at) }}</span>
      </div>
    </div>
    <template #footer><Btn variant="ghost" @click="logsDlg.show = false">{{ t('common.close') }}</Btn></template>
  </Modal>

  <!-- Labels manager modal -->
  <Modal v-model="labelsDlg.show" :title="t('mail.extras.labels')" width="440px">
    <div v-if="s.labels.length" class="mb-2 divide-y divide-[var(--ll-border)]">
      <div v-for="l in s.labels" :key="l.id" class="flex items-center gap-2 py-2">
        <span class="h-5 w-5 shrink-0 rounded-full" :style="{ background: l.color }" />
        <span class="flex-1 text-sm">{{ l.name }}</span>
        <Btn variant="ghost" size="sm" icon="edit" @click="editLabel(l)" />
        <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" @click="removeLabel(l)" />
      </div>
    </div>
    <div class="flex items-end gap-2 border-t border-[var(--ll-border)] pt-3">
      <input v-model="labelsDlg.color" type="color" class="h-10 w-10 shrink-0 cursor-pointer rounded-lg border border-[var(--ll-border)] bg-transparent p-0.5">
      <div class="flex-1"><TextField v-model="labelsDlg.name" :label="t('mail.extras.label_name')" @enter="saveLabel" /></div>
      <Btn variant="solid" :loading="labelsDlg.busy" @click="saveLabel">{{ labelsDlg.editing ? t('common.save') : t('mail.extras.add_label') }}</Btn>
    </div>
    <template #footer><Btn variant="ghost" @click="labelsDlg.show = false">{{ t('common.close') }}</Btn></template>
  </Modal>

  <!-- Every attachment the account holds — "the mail with the PDF" without
       remembering which mail it was. -->
  <Modal v-model="attDlg.show" :title="t('mail.extras.attachments')" width="60rem">
    <div class="mb-3 flex flex-wrap items-center gap-2">
      <TextField v-model="attDlg.q" :placeholder="t('mail.extras.attachments_search')" icon="search" class="min-w-52 flex-1" @update:model-value="loadAttachments" />
      <Select v-model="attDlg.type" :options="attTypeItems" class="w-44" @update:modelValue="loadAttachments" />
    </div>
    <div v-if="attDlg.loading" class="py-10 text-center"><Icon name="progress_activity" :size="24" class="animate-spin text-[var(--ll-muted)]" /></div>
    <div v-else-if="!attDlg.rows.length" class="py-10 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.extras.attachments_empty') }}</div>
    <table v-else class="w-full table-fixed text-sm">
      <thead><tr class="border-b border-[var(--ll-border)] text-left text-xs text-[var(--ll-muted)]">
        <th class="w-[34%] py-2 pr-3">{{ t('mail.reader.attachments') }}</th>
        <th class="py-2 pr-3">{{ t('mail.list.col_subject') }}</th>
        <th class="w-20 py-2 pr-3 text-right">{{ t('mail.list.col_size') }}</th>
        <th class="w-24 py-2 pr-3 text-right">{{ t('mail.list.col_date') }}</th>
        <th class="w-16" />
      </tr></thead>
      <tbody>
        <tr v-for="row in attDlg.rows" :key="row.id" class="border-b border-[var(--ll-border)] last:border-0">
          <td class="py-2 pr-3"><div class="flex min-w-0 items-center gap-2"><Icon name="attach_file" :size="15" class="shrink-0 text-[var(--ll-muted)]" /><span class="truncate">{{ row.filename || '—' }}</span></div></td>
          <td class="py-2 pr-3">
            <button type="button" class="min-w-0 max-w-full truncate text-left hover:text-primary-500" @click="openFromAttachment(row)">{{ row.subject || '—' }}</button>
            <div class="truncate text-xs text-[var(--ll-muted)]">{{ row.from }}</div>
          </td>
          <td class="py-2 pr-3 text-right text-xs text-[var(--ll-muted)]">{{ fmtBytes(row.size) }}</td>
          <td class="py-2 pr-3 text-right text-xs text-[var(--ll-muted)]">{{ fmtDate(row.date) }}</td>
          <td class="py-2 text-right"><a :href="s.attachmentRawUrl(row.id, true)" class="inline-grid h-7 w-7 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('common.download')"><Icon name="download" :size="16" /></a></td>
        </tr>
      </tbody>
    </table>
    <template #footer><Btn variant="ghost" @click="attDlg.show = false">{{ t('common.close') }}</Btn></template>
  </Modal>

  <!-- Unsubscribe: shows the target before anything happens, and the user acts -->
  <Modal v-model="unsubscribeOpen" :title="t('mail.reader.unsubscribe')" width="480px">
    <p class="mb-3 text-sm text-[var(--ll-muted)]">{{ t('mail.reader.unsubscribe_hint') }}</p>
    <div class="divide-y divide-[var(--ll-border)] rounded-lg border border-[var(--ll-border)]">
      <div v-for="target in unsubscribeTargets" :key="target.value" class="flex items-center gap-2 px-3 py-2 text-sm">
        <Icon :name="target.kind === 'mailto' ? 'mail' : 'open_in_new'" :size="16" class="shrink-0 text-[var(--ll-muted)]" />
        <span class="min-w-0 flex-1 truncate">{{ target.label }}</span>
        <Btn variant="soft" size="xs" @click="doUnsubscribe(target)">
          {{ target.kind === 'mailto' ? t('mail.reader.unsubscribe_write') : t('mail.reader.unsubscribe_open') }}
        </Btn>
      </div>
    </div>
    <template #footer><Btn variant="ghost" @click="unsubscribeOpen = false">{{ t('common.close') }}</Btn></template>
  </Modal>

  <!-- Rules modal -->
  <Modal v-model="rulesDlg.show" :title="t('mail.extras.rules')" width="640px">
    <div v-if="s.rules.length" class="mb-3 divide-y divide-[var(--ll-border)]">
      <div v-for="r in s.rules" :key="r.id" class="flex items-center gap-2 py-2">
        <Icon name="rule" :size="18" class="text-[var(--ll-muted)]" />
        <span class="min-w-0 flex-1 truncate text-sm">
          {{ r.name }}
          <span class="ml-1 text-xs text-[var(--ll-muted)]">{{ ruleSummary(r) }}</span>
        </span>
        <Badge v-if="!r.enabled" tone="gray">off</Badge>
        <Btn variant="ghost" size="sm" icon="play_arrow" :loading="rulesDlg.busy" :title="t('mail.extras.rule_apply')" @click="runRule(r)" />
        <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" @click="removeRule(r)" />
      </div>
    </div>
    <div class="space-y-3 border-t border-[var(--ll-border)] pt-3">
      <TextField v-model="rulesForm.name" :label="t('mail.extras.rule_match')" placeholder="Name" />
      <div class="grid grid-cols-2 gap-3">
        <TextField v-model="rulesForm.from" :label="t('mail.extras.rule_from')" />
        <TextField v-model="rulesForm.to" :label="t('mail.extras.rule_to')" />
        <TextField v-model="rulesForm.subject" :label="t('mail.extras.rule_subject')" />
        <TextField v-model="rulesForm.folder" :label="t('mail.extras.rule_folder')" />
      </div>
      <label class="flex items-center gap-1.5 text-sm"><input v-model="rulesForm.has_attachment" type="checkbox" class="accent-primary-500">{{ t('mail.extras.rule_has_attachment') }}</label>
      <div class="flex flex-wrap items-center gap-3 text-sm">
        <span class="text-xs text-[var(--ll-muted)]">{{ t('mail.extras.rule_action') }}:</span>
        <label class="flex items-center gap-1.5"><input v-model="rulesForm.mark_read" type="checkbox" class="accent-primary-500">{{ t('mail.extras.action_mark_read') }}</label>
        <label class="flex items-center gap-1.5"><input v-model="rulesForm.trash" type="checkbox" class="accent-primary-500">{{ t('mail.extras.action_trash') }}</label>
        <label class="flex items-center gap-1.5"><input v-model="rulesForm.skip" type="checkbox" class="accent-primary-500">{{ t('mail.extras.action_skip') }}</label>
        <label v-if="auth.can('finance')" class="flex items-center gap-1.5"><input v-model="rulesForm.file_receipt" type="checkbox" class="accent-primary-500">{{ t('mail.extras.action_file_receipt') }}</label>
      </div>
      <p v-if="rulesForm.file_receipt" class="text-xs text-[var(--ll-muted)]">{{ t('mail.extras.action_file_receipt_hint') }}</p>
      <Select v-if="s.labels.length" v-model="rulesForm.add_label" :label="t('mail.extras.action_add_label')" :options="labelRuleItems" />
    </div>
    <p class="mt-3 text-xs text-[var(--ll-muted)]">{{ t('mail.extras.rule_apply_hint') }}</p>
    <template #footer>
      <Btn variant="ghost" @click="rulesDlg.show = false">{{ t('common.close') }}</Btn>
      <Btn v-if="s.rules.length" variant="ghost" :loading="rulesDlg.busy" icon="play_arrow" @click="runRule(null)">{{ t('mail.extras.rule_apply_all') }}</Btn>
      <Btn variant="solid" :loading="rulesDlg.busy" @click="saveRule">{{ t('mail.extras.add_rule') }}</Btn>
    </template>
  </Modal>

  <!-- Stats modal -->
  <Modal v-model="statsDlg.show" :title="t('mail.extras.stats')" width="560px">
    <div v-if="statsDlg.loading" class="py-6 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
    <template v-else-if="statsDlg.data">
      <div class="mb-3 flex gap-6 text-sm">
        <div><div class="text-xs text-[var(--ll-muted)]">{{ t('mail.list.col_subject') }}</div><div class="font-semibold">{{ statsDlg.data.total_messages }}</div></div>
        <div><div class="text-xs text-[var(--ll-muted)]">{{ t('mail.extras.stats') }}</div><div class="font-semibold">{{ fmtBytes(statsDlg.data.total_bytes) }}</div></div>
      </div>
      <div class="divide-y divide-[var(--ll-border)]">
        <div v-for="(pf, i) in statsDlg.data.per_folder" :key="i" class="flex items-center justify-between py-1.5 text-sm">
          <span class="min-w-0 truncate">{{ accountName(pf.account_id) }} · {{ pf.folder }}</span>
          <span class="ml-2 shrink-0 text-xs text-[var(--ll-muted)]">{{ pf.count }} · {{ fmtBytes(pf.bytes) }}</span>
        </div>
      </div>
    </template>
    <template #footer><Btn variant="ghost" @click="statsDlg.show = false">{{ t('common.close') }}</Btn></template>
  </Modal>

  <!-- Compose / reply / forward modal -->
  <!-- Save attachment to a chosen Files folder -->
  <Modal v-model="attSave.show" :title="t('mail.reader.save_to_files')" width="440px">
    <div v-if="attSave.loading" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</div>
    <div v-else class="max-h-80 overflow-y-auto">
      <button
        v-for="o in attFolderOptions" :key="String(o.id)"
        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
        @click="saveAttToFolder(o.id)"
      >
        <Icon name="folder" :size="18" class="text-[var(--ll-muted)]" />{{ o.label }}
      </button>
    </div>
  </Modal>

  <Modal v-model="assetPicker.show" :title="assetPicker.kind === 'files' ? t('mail.send.attach_file') : t('mail.send.attach_media')" width="52rem">
    <div class="space-y-3"><TextField v-model="assetPicker.q" label="Suchen" icon="search" />
      <div class="grid max-h-[55vh] grid-cols-2 gap-2 overflow-y-auto p-1 sm:grid-cols-3 lg:grid-cols-4">
        <button v-for="item in assetPickerRows" :key="item.id" class="group relative overflow-hidden rounded-lg border text-left transition hover:border-primary-500" :class="isAssetSelected(item.id) ? 'border-primary-500 ring-2 ring-primary-500/20' : 'border-[var(--ll-border)]'" @click="selectAsset(item.id)">
          <div class="grid aspect-[4/3] place-items-center bg-black/[0.03] dark:bg-white/[0.05]"><img v-if="assetPicker.kind === 'gallery' && (item as Photo).thumb" :src="galleryStore.thumbUrl(item.id)" :alt="item.name" class="h-full w-full object-cover"><img v-else-if="assetPicker.kind === 'files' && (item as FileEntry).mime.startsWith('image/')" :src="filesStore.thumbUrl(item as FileEntry)" :alt="item.name" class="h-full w-full object-cover"><Icon v-else :name="assetPicker.kind === 'gallery' ? 'image' : 'description'" :size="32" class="text-[var(--ll-muted)]" /></div>
          <div class="p-2"><div class="truncate text-xs font-medium">{{ item.name }}</div><div class="mt-0.5 text-[0.7rem] text-[var(--ll-muted)]">{{ fmtBytes(item.size) }}</div></div><span v-if="isAssetSelected(item.id)" class="absolute right-1.5 top-1.5 grid h-5 w-5 place-items-center rounded-full bg-primary-500 text-white"><Icon name="check" :size="14" /></span>
        </button>
      </div>
    </div>
    <template #footer><Btn variant="ghost" @click="assetPicker.show = false">{{ t('common.close') }}</Btn></template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fmtDate as libDate, fmtDateTime as libDateTime } from '@spa/lib/datetime';
import { trans as t } from 'laravel-vue-i18n';
import { DropdownMenuRoot, DropdownMenuTrigger, DropdownMenuPortal, DropdownMenuContent, DropdownMenuItem, DropdownMenuCheckboxItem } from 'reka-ui';
import { Icon, Btn, Card, TextField, Select, Badge, Modal, SortLabel, Pager } from '@spa/ui';
import { useMailStore, accountCanSend, type MailAccount, type MailMessage, type MailLabel, type MailSavedSearch, type MailRule, type MailStats, type MailAddress, type AccountBody, type MailAutoconfig, type MailSignature, type VirusTotalResult, type MailDraft, type MailAttachmentRow } from '@spa/stores/mail';
import { useFilesStore, type FileEntry } from '@spa/stores/files';
import { useGalleryStore, type Photo } from '@spa/stores/gallery';
import { useCryptoStore } from '@spa/stores/crypto';
import RichTextEditor from '@spa/components/RichTextEditor.vue';
import { api, ApiError } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { useAuthStore } from '@spa/stores/auth';
import { useProfileStore } from '@spa/stores/profile';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';
import { renderInvoicePdfBlob } from '@spa/shared/invoice-print';
import DOMPurify from 'dompurify';
import { parseUnsubscribe, type UnsubscribeTarget } from '@spa/shared/unsubscribe';

const s = useMailStore();
const auth = useAuthStore();
const filesStore = useFilesStore();
const galleryStore = useGalleryStore();
const cryptoStore = useCryptoStore();
const route = useRoute();
const router = useRouter();
const { success, error, toast, undoable } = useToast();
const p = useProfileStore();
const filters = s.filters;

const menuItem = 'flex cursor-pointer items-center gap-2.5 rounded-md px-3 py-1.5 text-sm outline-none hover:bg-black/[0.05] dark:hover:bg-white/10';
// Section heading inside a menu: the label list and the export actions are
// different kinds of thing, and an unbroken list of both reads as neither.
const menuSection = 'px-3 pb-0.5 pt-1.5 text-[0.65rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]';
const menuItemDanger = 'flex cursor-pointer items-center gap-2.5 rounded-md px-3 py-1.5 text-sm text-red-600 outline-none hover:bg-red-500/10';

const loading = ref(false);
const reader = ref<MailMessage | null>(null);
const readerOpen = ref(false);
const showHeaders = ref(false);
const remoteOn = ref(false);
const printing = ref(false);
const pdfExporting = ref(false);
const attachmentVirusLoading = ref<string | null>(null);
const attachmentVirusResults = reactive<Record<string, VirusTotalResult | undefined>>({});
const signatures = ref<MailSignature[]>([]);
const drafts = ref<MailDraft[]>([]);
const draftListActive = ref(false);
const dateFrom = ref('');
const dateTo = ref('');

const isUnified = computed(() => !draftListActive.value && filters.accountId === null && filters.label === null && !filters.trashed);
const hasHtml = computed(() => reader.value?.html != null);
const allSelected = computed(() => s.messages.length > 0 && s.messages.every((m) => s.selected.includes(m.id)));

// Accounts that can send (mirror backend hasSmtp: smtp_host + from_email set).
const sendableAccounts = computed(() => s.accounts.filter(accountCanSend));
const readerAccount = computed(() => {
  const id = reader.value?.account_id;
  return id != null ? (s.accounts.find((a) => a.id === id) ?? null) : null;
});
const readerCanSend = computed(() => !!readerAccount.value && accountCanSend(readerAccount.value));

const encItems = [
  { title: t('mail.form.enc_ssl'), value: 'ssl' },
  { title: t('mail.form.enc_tls'), value: 'tls' },
  { title: t('mail.form.enc_starttls'), value: 'starttls' },
  { title: t('mail.form.enc_none'), value: 'none' },
];
const logLevelItems = [
  { title: t('common.none'), value: '' },
  { title: t('mail.logs.level_info'), value: 'info' },
  { title: t('mail.logs.level_warn'), value: 'warn' },
  { title: t('mail.logs.level_error'), value: 'error' },
];
const labelRuleItems = computed(() => [{ title: t('common.none'), value: 0 }, ...s.labels.map((l) => ({ title: l.name, value: l.id }))]);

function foldersForAccount(id: number) { return s.folders.filter((f) => f.account_id === id); }
function inboxFolder(id: number): string | null {
  const folders = foldersForAccount(id);
  const exact = folders.find((f) => /^inbox$/i.test(f.folder));
  if (exact) return exact.folder;

  const localized = folders.find((f) => /(?:posteingang|inbox)/i.test(f.folder));
  return localized?.folder ?? null;
}
function unreadForAccount(id: number) { return s.folders.filter((f) => f.account_id === id).reduce((n, f) => n + f.unread, 0); }
function accountName(id: number | null) { return id == null ? '—' : (s.accounts.find((a) => a.id === id)?.name ?? '—'); }
function folderIcon(f: string) {
  const n = f.toLowerCase();
  if (n.includes('sent')) return 'send';
  if (n.includes('draft')) return 'drafts';
  if (n.includes('trash') || n.includes('deleted')) return 'delete';
  if (n.includes('spam') || n.includes('junk')) return 'report';
  if (n.includes('archive')) return 'archive';
  return 'folder';
}
function addrList(list: MailAddress[]) { return (list || []).map((a) => a.name || a.email).join(', '); }
function authTone(v: string): 'success' | 'warning' | 'error' | 'gray' {
  const x = (v || '').toLowerCase();
  if (x === 'pass') return 'success';
  if (x === 'fail') return 'error';
  if (x === 'none' || x === 'neutral') return 'gray';
  return 'warning';
}
function fmtDate(iso: string | null) { return libDate(iso); }
function fmtDateTime(iso: string | null) { return libDateTime(iso); }
function fmtBytes(n: number) { if (!n) return '0 B'; const u = ['B', 'KB', 'MB', 'GB']; const i = Math.min(u.length - 1, Math.floor(Math.log(n) / Math.log(1024))); return `${(n / 1024 ** i).toFixed(i ? 1 : 0)} ${u[i]}`; }
function senderLabel(message: MailMessage): string {
  if (message.from_name?.trim()) return message.from_name.trim();
  const alias = message.from_email?.match(/^(.+)_([a-z0-9]{6,})@simplelogin\.co$/i);
  if (alias) return alias[1].replaceAll('_at_', '@').replaceAll('_', '.');
  return message.from_email || '—';
}

// ---- Deep links: mirror the selected account/folder/label + open message in
// the URL query so a reload or shared link lands on the same place. `restoring`
// gates the write-watch while we apply an incoming URL.
let restoring = false;
function buildQuery(): Record<string, string> {
  const q: Record<string, string> = {};
  if (filters.accountId !== null) q.account = String(filters.accountId);
  if (filters.folder) q.folder = filters.folder;
  if (filters.label !== null) q.label = String(filters.label);
  if (readerOpen.value && reader.value) q.message = String(reader.value.id);
  return q;
}
watch([() => filters.accountId, () => filters.folder, () => filters.label, readerOpen, reader], () => {
  if (restoring) return;
  const q = buildQuery();
  const keys = ['account', 'folder', 'label', 'message'];
  const cur: Record<string, string> = {};
  for (const k of keys) { const v = route.query[k]; if (typeof v === 'string') cur[k] = v; }
  if (JSON.stringify(q) !== JSON.stringify(cur)) void router.replace({ query: q });
});
// Apply the URL query on load: restore account/folder/label, load the list,
// then reopen the deep-linked message (best-effort — skip if not in the list).
async function applyRoute() {
  restoring = true;
  try {
    const q = route.query;
    const label = q.label ? Number(q.label) : null;
    const account = q.account ? Number(q.account) : null;
    if (label !== null) {
      filters.label = label;
    } else if (account !== null) {
      filters.accountId = account;
      filters.folder = typeof q.folder === 'string' ? q.folder : null;
    }
    await s.loadFolders(filters.accountId);
    await reload();
    const mid = q.message;
    if (typeof mid === 'string' && mid) {
      const m = s.messages.find((x) => x.id === mid);
      if (m) await openReader(m);
    }
  } catch { /* fall back to the default view */ }
  finally { restoring = false; }
}

// --- Loading -----------------------------------------------------------------
let statusTimer: ReturnType<typeof setInterval> | undefined;
onMounted(async () => {
  window.addEventListener('keydown', onKey);
  const savedPerPage = Number(localStorage.getItem('ll_mail_per_page'));
  if ([25, 50, 100, 200].includes(savedPerPage)) s.meta.per_page = savedPerPage;
  // The column choice is a display preference; without it the list would show
  // the default set until the profile page happened to be visited.
  if (!p.prefs) void p.loadPrefs();
  await Promise.all([s.loadAccounts(), s.loadLabels(), s.loadSavedSearches(), s.loadDrafts().then((rows) => { drafts.value = rows; }), api.get<{ signatures: MailSignature[] }>('/api/v1/mail/signatures').then((r) => { signatures.value = r.signatures; })]);
  await applyRoute();
  statusTimer = setInterval(async () => {
    if (s.accounts.some((a) => a.status === 'syncing')) { await s.pollStatus(); await s.loadFolders(filters.accountId); }
  }, 5000);
});
onBeforeUnmount(() => { if (statusTimer) clearInterval(statusTimer); window.removeEventListener('keydown', onKey); });

const searchHelp = ref(false);
const searchFocused = ref(false);

// Recent searches, on this display. Only terms that were actually submitted
// land here — recording every keystroke would fill the list with prefixes of
// one search.
const HISTORY_KEY = 'll_mail_searches';
const searchHistory = ref<string[]>(JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]') as string[]);
// A click on a suggestion is a mousedown then a blur; closing on blur without
// the delay would remove the item before the click lands on it.
function blurSearch() { window.setTimeout(() => { searchFocused.value = false; }, 150); }

function rememberSearch() {
  const term = filters.q.trim();
  if (term.length < 2) return;
  const next = [term, ...searchHistory.value.filter((x) => x !== term)].slice(0, 8);
  searchHistory.value = next;
  localStorage.setItem(HISTORY_KEY, JSON.stringify(next));
}

/**
 * Quick filters are search terms, not a parallel filter set.
 *
 * That way a chip composes with whatever is typed, survives a saved search, and
 * there is one place where "what is being filtered" lives.
 */
const chipOn = (term: string) => filters.q.split(/\s+/).includes(term);
function toggleChip(term: string) {
  const parts = filters.q.split(/\s+/).filter(Boolean);
  const at = parts.indexOf(term);
  if (at >= 0) parts.splice(at, 1); else parts.push(term);
  filters.q = parts.join(' ');
  void reload();
}
const searchTips = computed(() => [
  t('mail.list.search_help_from'), t('mail.list.search_help_to'), t('mail.list.search_help_subject'),
  t('mail.list.search_help_folder'), t('mail.list.search_help_is'), t('mail.list.search_help_has'),
  t('mail.list.search_help_date'),
]);


// ---- Keyboard ------------------------------------------------------------
// A mailbox with sixteen thousand messages is read with the hands on the
// keyboard, so the list has a cursor of its own, independent of the selection
// (the checkboxes) and of what the reader shows.
const cursor = ref(-1);

/**
 * Never while typing or while a dialog is up.
 *
 * The same rule the file browser learned: a shortcut that fires inside a text
 * field eats the character, and one that fires behind a modal acts on something
 * the reader cannot see.
 */
function keysBlocked(): boolean {
  const el = document.activeElement as HTMLElement | null;
  if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable)) return true;
  return compose.show || editor.show || rulesDlg.show || statsDlg.show || labelsDlg.show
    || logsDlg.show || attSave.show || assetPicker.show || searchHelp.value;
}

/** Move the cursor and open what it lands on, so j/k reads rather than points. */
async function moveCursor(delta: number) {
  const rows = s.messages;
  if (!rows.length) return;
  // Opening a message by clicking it leaves the cursor unset; start from the
  // row the reader shows so the arrows continue from what is on screen.
  const from = cursor.value >= 0 ? cursor.value : rows.findIndex((m) => m.id === reader.value?.id);
  const next = from < 0 ? (delta > 0 ? 0 : rows.length - 1) : Math.min(rows.length - 1, Math.max(0, from + delta));
  cursor.value = next;
  const row = rows[next];
  document.getElementById(`mail-row-${row.id}`)?.scrollIntoView({ block: 'nearest' });
  if (readerOpen.value) await openReader(row);
}

// ---- Row presentation ----------------------------------------------------
// Density and the split are display choices about THIS screen, so they live in
// localStorage rather than the profile — unlike the columns, which say what a
// reader wants to see and belong to the account.
const density = ref<'comfortable' | 'compact'>(localStorage.getItem('ll_mail_density') === 'compact' ? 'compact' : 'comfortable');
function setDensity(next: 'comfortable' | 'compact') {
  density.value = next;
  localStorage.setItem('ll_mail_density', next);
}

/** Initials of the sender — something to aim at when scanning a long list. */
function initials(m: MailMessage): string {
  const name = (m.from_name || m.from_email || '').trim();
  if (!name) return '?';
  const parts = name.replace(/[<>"]/g, '').split(/[\s.@_-]+/).filter(Boolean);
  const first = parts[0]?.[0] ?? '';
  const second = parts.length > 1 ? parts[1][0] : '';
  return (first + second).toUpperCase() || '?';
}

/**
 * A stable colour per sender, so the same correspondent looks the same every
 * time. Hashed from the address rather than assigned in order — order changes
 * with every reload.
 */
function avatarColour(m: MailMessage): string {
  const key = (m.from_email || m.from_name || '?').toLowerCase();
  let hash = 0;
  for (let i = 0; i < key.length; i++) hash = (hash * 31 + key.charCodeAt(i)) % 360;
  return `hsl(${hash} 55% 45%)`;
}

/** Click a sender to see everything from them — the same term the box takes. */
function filterBySender(m: MailMessage) {
  const who = m.from_email || m.from_name;
  if (!who) return;
  filters.q = `from:${who}`;
  void reload();
}

// ---- Attachment overview -------------------------------------------------
const attDlg = reactive<{ show: boolean; loading: boolean; q: string; type: string; rows: MailAttachmentRow[] }>({
  show: false, loading: false, q: '', type: '', rows: [],
});
const attTypeItems = computed(() => [
  { value: '', title: t('mail.extras.attachments_all') },
  { value: 'pdf', title: 'PDF' },
  { value: 'image', title: t('mail.extras.attachments_images') },
  { value: 'document', title: t('mail.extras.attachments_documents') },
  { value: 'other', title: t('mail.extras.attachments_other') },
]);
let attTimer: ReturnType<typeof setTimeout> | undefined;
function loadAttachments() {
  if (attTimer) clearTimeout(attTimer);
  attTimer = setTimeout(async () => {
    attDlg.loading = true;
    try {
      // Scoped to the account/folder in view: the overview answers "what came in
      // here", not "everything I ever received".
      const r = await s.attachments({ q: attDlg.q, type: attDlg.type, accountId: filters.accountId, folder: filters.folder });
      attDlg.rows = r.data;
    } catch { error(t('common.error')); } finally { attDlg.loading = false; }
  }, 180);
}
function openAttachments() { attDlg.show = true; loadAttachments(); }

/** Jump from an attachment to the message it came from. */
async function openFromAttachment(row: MailAttachmentRow) {
  attDlg.show = false;
  try { reader.value = await s.show(row.message_id); readerOpen.value = true; }
  catch { error(t('mail.toast.load_failed')); }
}

// ---- Split -----------------------------------------------------------------
// The reader was a fixed 44% of the width. Where that split belongs depends on
// the screen and on what is being read, so it is draggable and remembered — in
// localStorage rather than the profile, because it describes this display, and
// the same account on a laptop and on a wide monitor wants different answers.
const SPLIT_KEY = 'll_mail_split';
const splitPct = ref(Number(localStorage.getItem(SPLIT_KEY)) || 44);
const splitDragging = ref(false);
// Clamped so neither pane can be dragged away entirely — a list of zero width
// is not a layout, it is a broken one.
const readerStyle = computed(() => ({ flexBasis: `${Math.min(75, Math.max(25, splitPct.value))}%` }));

function splitStart(e: PointerEvent) {
  splitDragging.value = true;
  const move = (ev: PointerEvent) => {
    const host = (e.target as HTMLElement).parentElement;
    if (!host) return;
    const b = host.getBoundingClientRect();
    splitPct.value = Math.min(75, Math.max(25, ((b.right - ev.clientX) / b.width) * 100));
  };
  const up = () => {
    splitDragging.value = false;
    localStorage.setItem(SPLIT_KEY, String(Math.round(splitPct.value)));
    window.removeEventListener('pointermove', move);
    window.removeEventListener('pointerup', up);
  };
  window.addEventListener('pointermove', move);
  window.addEventListener('pointerup', up);
}

// ---- Unsubscribe ---------------------------------------------------------
// The List-Unsubscribe header (RFC 2369) is already in headers_raw, so this
// needs no new server call. RFC 8058 one-click POST is deliberately NOT used:
// posting on the user's behalf confirms to the sender that the address is live,
// and doing it server-side would additionally hand them this server's address.
// The link is opened in the browser, where the user can see where it goes.
const unsubscribeOpen = ref(false);
const unsubscribeTargets = computed(() => parseUnsubscribe(reader.value?.headers_raw ?? null));

function doUnsubscribe(target: UnsubscribeTarget) {
  unsubscribeOpen.value = false;
  if (target.kind === 'mailto') {
    // Compose it here rather than handing the address to the OS mail client,
    // which is very likely not this app.
    const address = target.label;
    const params = new URLSearchParams(target.value.split('?')[1] ?? '');
    openCompose();
    compose.to = address;
    compose.subject = params.get('subject') || 'unsubscribe';
    compose.body = params.get('body') || 'unsubscribe';
    return;
  }
  // noopener: the opened page must not get a handle on this window.
  window.open(target.value, '_blank', 'noopener,noreferrer');
}

/** Whether there is a row in that direction — for the reader's arrows. */
function hasNeighbour(delta: number): boolean {
  const rows = s.messages;
  if (!rows.length) return false;
  const at = cursor.value >= 0 ? cursor.value : rows.findIndex((m) => m.id === reader.value?.id);
  if (at < 0) return true;
  return at + delta >= 0 && at + delta < rows.length;
}

/** The row a shortcut acts on: the cursor, else what the reader shows. */
function focusedRow(): MailMessage | null {
  const rows = s.messages;
  if (cursor.value >= 0 && cursor.value < rows.length) return rows[cursor.value];
  return reader.value;
}

async function onKey(e: KeyboardEvent) {
  if (e.metaKey || e.ctrlKey || e.altKey) return;

  // Escape closes what is open, and it must work from inside the search field
  // too — that is the one place a blocked shortcut would be wrong.
  if (e.key === 'Escape') {
    if (searchHelp.value) { searchHelp.value = false; return; }
    if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
    if (readerOpen.value) readerOpen.value = false;
    return;
  }
  if (keysBlocked()) return;

  const row = focusedRow();
  switch (e.key) {
    case 'j': case 'ArrowDown': e.preventDefault(); await moveCursor(1); return;
    case 'k': case 'ArrowUp': e.preventDefault(); await moveCursor(-1); return;
    case 'n': e.preventDefault(); await moveCursor(1); return;
    case 'p': e.preventDefault(); await moveCursor(-1); return;
    case 'Enter': case 'o':
      if (!row) return;
      e.preventDefault(); await openReader(row); return;
    case '/':
      e.preventDefault();
      // By id, not by a guess at which input comes first in the DOM.
      (document.querySelector('#mail-search input, input#mail-search') as HTMLInputElement | null)?.focus();
      return;
    case 'x':
      if (!row) return;
      e.preventDefault(); toggleSelect(row.id); return;
    case 's':
      if (!row) return;
      e.preventDefault(); await toggleFlag(row); return;
    case 'u':
      if (!row) return;
      e.preventDefault();
      try { await s.setSeen([row.id], !row.seen); row.seen = !row.seen; } catch { error(t('mail.toast.load_failed')); }
      return;
    case 'r':
      if (!reader.value) return;
      e.preventDefault(); openReply(false); return;
    case 'a':
      if (!reader.value) return;
      e.preventDefault(); openReply(true); return;
    case 'f':
      if (!reader.value) return;
      e.preventDefault(); openForward(); return;
    case 'c':
      e.preventDefault(); openCompose(); return;
    case '#': case 'Delete':
      if (!row) return;
      e.preventDefault();
      // Reuse the bulk path so trashing one row behaves exactly like trashing
      // several, including what happens to the open reader.
      s.selected.splice(0, s.selected.length, row.id);
      await bulkTrash();
      return;
    default: return;
  }
}

// ---- List columns --------------------------------------------------------
// Which columns exist, in the order the picker offers them. Every one is a
// field the row already carries — no extra query. `subject` is not here: a
// message list without a subject is not a list of messages, so it is always
// rendered and cannot be switched off. The checkbox and the star are controls
// rather than data and are likewise fixed.
const ALL_COLUMNS = ['from', 'to', 'snippet', 'labels', 'folder', 'account', 'attachment', 'security', 'answered', 'spam', 'size', 'date'] as const;
type ColumnKey = typeof ALL_COLUMNS[number];

/** The default set: what a mail list needs to be readable. */
const DEFAULT_COLUMNS: string[] = ['from', 'subject', 'size', 'date'];

/**
 * The active columns, always including subject.
 *
 * The stored preference is null until the picker is used, so a column added in
 * a later release appears for everyone instead of only for accounts that never
 * touched the picker.
 */
const activeColumns = computed<string[]>(() => {
  const saved = p.prefs?.mail_columns;
  const chosen = Array.isArray(saved) && saved.length ? saved.slice() : DEFAULT_COLUMNS.slice();
  if (!chosen.includes('subject')) {
    // Keep it where a reader expects it: after the sender, else at the front.
    const at = chosen.indexOf('from');
    chosen.splice(at >= 0 ? at + 1 : 0, 0, 'subject');
  }
  return chosen;
});

const COLUMN_WIDTH: Record<string, string> = {
  from: 'w-[26%]', to: 'w-[20%]', folder: 'w-24', account: 'w-28',
  attachment: 'w-8', security: 'w-8', answered: 'w-8', spam: 'w-8',
  labels: 'w-32', size: 'w-20', date: 'w-28',
};
/** Which server sort key a column maps to; null = not sortable. */
const COLUMN_SORT: Record<string, string | null> = {
  from: 'from', subject: 'subject', size: 'size', date: 'date', folder: 'folder',
  to: null, snippet: null, labels: null, account: null, attachment: null,
  security: null, answered: null, spam: null,
};

const columnWidth = (col: string) => COLUMN_WIDTH[col] ?? '';
const columnAlign = (col: string) => (col === 'size' || col === 'date' ? 'text-right' : col === 'attachment' || col === 'security' || col === 'answered' || col === 'spam' ? 'text-center' : '');
const sortKeyFor = (col: string) => COLUMN_SORT[col] ?? null;
const columnLabel = (col: string) => t(`mail.list.col_${col}`);

function recipientLabel(m: MailMessage): string {
  const list = (m.to ?? []) as MailAddress[];
  if (!list.length) return '—';
  const first = list[0].name?.trim() || list[0].email || '—';
  return list.length > 1 ? `${first} +${list.length - 1}` : first;
}

/** Persist the picker. Columns are a display preference, like units and clock. */
async function saveColumns(next: string[]) {
  try {
    await p.savePrefs({ mail_columns: next.filter((c) => c !== 'subject') });
  } catch { error(t('common.error')); }
}

function toggleColumn(col: ColumnKey) {
  const next = activeColumns.value.slice();
  const at = next.indexOf(col);
  if (at >= 0) next.splice(at, 1);
  else next.splice(ALL_COLUMNS.indexOf(col) >= ALL_COLUMNS.indexOf('size') ? next.length : Math.max(1, next.length - 2), 0, col);
  void saveColumns(next);
}

const canMove = (col: string, delta: number) => {
  const at = activeColumns.value.indexOf(col);
  return at >= 0 && at + delta >= 0 && at + delta < activeColumns.value.length;
};

function moveColumn(col: string, delta: number) {
  if (!canMove(col, delta)) return;
  const next = activeColumns.value.slice();
  const at = next.indexOf(col);
  next.splice(at + delta, 0, next.splice(at, 1)[0]);
  void saveColumns(next);
}

/** Back to the default set — sending nothing means "default", not "no columns". */
const resetColumns = () => { void saveColumns([]); };

const keyTips = computed(() => [
  t('mail.list.keys_move'), t('mail.list.keys_open'), t('mail.list.keys_reply'),
  t('mail.list.keys_flag'), t('mail.list.keys_unread'), t('mail.list.keys_select'),
  t('mail.list.keys_trash'), t('mail.list.keys_compose'), t('mail.list.keys_search'),
]);

/** What the sortable headers render their arrow from. */
const listSort = computed(() => ({ key: filters.sort, dir: filters.dir }));

/**
 * Clicking a column sorts by it; clicking the active one reverses.
 *
 * The first direction differs per column because "sorted" means something
 * different for each: newest mail first, but names A-Z, and the largest mail is
 * the one worth finding.
 */
function sortBy(key: string) {
  if (filters.sort === key) {
    filters.dir = filters.dir === 'asc' ? 'desc' : 'asc';
  } else {
    filters.sort = key;
    filters.dir = key === 'from' || key === 'subject' ? 'asc' : 'desc';
  }
  void reload();
}

/**
 * Star a message. Written straight to the row on success so the list does not
 * have to be re-fetched for a single flag.
 */
async function toggleFlag(m: MailMessage) {
  const next = !m.flagged;
  try {
    await s.setFlagged([m.id], next);
    m.flagged = next;
    if (reader.value?.id === m.id) reader.value.flagged = next;
  } catch { error(t('mail.toast.flag_failed')); }
}

/** Rows per page. Kept in localStorage: it describes this display, like the split. */
function setPerPage(n: number) {
  s.meta.per_page = n;
  localStorage.setItem('ll_mail_per_page', String(n));
  void goto(1);   // page 7 of the old size is not page 7 of the new one
}

async function reload() {
  if (draftListActive.value) return;
  loading.value = true;
  s.selected = [];
  try { await s.loadMessages(1); } catch { error(t('mail.toast.load_failed')); } finally { loading.value = false; }
}
async function goto(page: number) {
  loading.value = true;
  try { await s.loadMessages(page); } catch { error(t('mail.toast.load_failed')); } finally { loading.value = false; }
}
let debTimer: ReturnType<typeof setTimeout> | undefined;
function debouncedReload() { clearTimeout(debTimer); debTimer = setTimeout(reload, 300); }
function onDate() { filters.dateFrom = dateFrom.value || null; filters.dateTo = dateTo.value || null; reload(); }

// --- Rail navigation ---------------------------------------------------------
async function pickUnified() { draftListActive.value = false; s.resetFilters(); await s.loadFolders(null); reload(); }
async function pickAccount(a: MailAccount) {
  draftListActive.value = false;
  s.resetFilters();
  filters.accountId = a.id;
  await s.loadFolders(a.id);
  filters.folder = inboxFolder(a.id);
  reload();
}
function pickFolder(folder: string) { draftListActive.value = false; filters.folder = folder; filters.trashed = false; reload(); }
function pickTrash() { draftListActive.value = false; filters.trashed = true; filters.label = null; reload(); }
function pickLabel(id: number) { draftListActive.value = false; s.resetFilters(); filters.label = id; s.loadFolders(null); reload(); }
async function pickDrafts() { draftListActive.value = true; readerOpen.value = false; s.selected = []; drafts.value = await s.loadDrafts(); }
function toggleUnread() { filters.seen = filters.seen === false ? null : false; reload(); }
function toggleSpam() { filters.spam = filters.spam === true ? null : true; reload(); }

// --- Selection ---------------------------------------------------------------
function toggleSelect(id: string) { const i = s.selected.indexOf(id); if (i >= 0) s.selected.splice(i, 1); else s.selected.push(id); }
function toggleSelectAll() { s.selected = allSelected.value ? [] : s.messages.map((m) => m.id); }

// --- Reader ------------------------------------------------------------------
async function openReader(m: MailMessage) {
  showHeaders.value = false; remoteOn.value = false;
  readerOpen.value = true;
  try {
    reader.value = await s.show(m.id);
    if (!m.seen) { await s.setSeen([m.id], true); m.seen = true; if (reader.value) reader.value.seen = true; refreshCounts(); }
    void loadThread(m);
  } catch { error(t('mail.toast.load_failed')); }
}

// ---- Conversation --------------------------------------------------------
// The messages of the open message's thread, oldest first. Only the open one
// carries a body: an expanded message renders an iframe, and one document per
// message in a forty-message thread is forty documents.
const threadMessages = ref<MailMessage[]>([]);
async function loadThread(m: MailMessage) {
  if (!m.thread_id) { threadMessages.value = []; return; }
  // Already showing this conversation — the stack does not need re-fetching
  // just because another of its messages was expanded.
  if (threadMessages.value.some((x) => x.id === m.id) && threadMessages.value[0]?.thread_id === m.thread_id) return;
  try {
    const rows = await s.thread(m.thread_id);
    threadMessages.value = rows.length > 1 ? rows : [];
  } catch { threadMessages.value = []; }
}
const inThread = computed(() => threadMessages.value.length > 1);
function toggleRemote() { remoteOn.value = !remoteOn.value; }

async function readerDocument(): Promise<string | null> {
  if (!reader.value) return null;
  try { return await api.text(`/api/v1/mail/messages/${reader.value.id}/body`); }
  catch { error(t('common.error')); return null; }
}
function fileStem(): string { return `mail-${(reader.value?.date || reader.value?.created_at || new Date().toISOString()).slice(0, 10)}`; }
function downloadEml() {
  if (!reader.value) return;
  const link = document.createElement('a');
  link.href = s.rawUrl(reader.value.id, true);
  link.download = `${fileStem()}.eml`;
  document.body.appendChild(link); link.click(); link.remove();
}
async function printMessage() {
  if (printing.value) return;
  const popup = window.open('', '_blank', 'width=960,height=720');
  if (!popup) { error(t('common.error')); return; }
  printing.value = true;
  try {
    const body = await readerDocument();
    if (!body) { popup.close(); return; }
    popup.opener = null;
    popup.document.open();
    // The popup is same-origin with the app, so the print document must carry
    // its own CSP. Prepend it when the server document has no <head> to patch,
    // rather than silently writing the body without one.
    const printCsp = `<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; img-src data:">`;
    popup.document.write(body.includes('</head>') ? body.replace('</head>', `${printCsp}</head>`) : printCsp + body);
    popup.document.close();
    popup.addEventListener('load', () => { popup.focus(); popup.print(); }, { once: true });
  } finally { printing.value = false; }
}
async function exportPdf() {
  if (pdfExporting.value) return;
  pdfExporting.value = true;
  try {
    const body = await readerDocument();
    if (!body) return;
    const doc = new DOMParser().parseFromString(body, 'text/html');
    doc.querySelectorAll('style').forEach((node) => node.remove());
    const node = document.createElement('div');
    node.style.cssText = 'position:fixed;left:-10000px;top:0;width:794px;padding:32px;background:#fff;color:#111;z-index:-1;';
    // The reader body is server-sanitised, but rendering it here leaves the
    // sandboxed iframe + strict CSP behind and puts message markup directly in
    // the app origin. Sanitise again on this side so a parser differential or
    // mutation-XSS in the stored HTML cannot execute with our session.
    node.innerHTML = DOMPurify.sanitize(doc.body.innerHTML, { FORBID_TAGS: ['style'], FORBID_ATTR: ['srcset'] });
    document.body.appendChild(node);
    await nextTick();
    const blob = await renderInvoicePdfBlob(node);
    node.remove();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url; link.download = `${fileStem()}.pdf`;
    document.body.appendChild(link); link.click(); link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch { error(t('common.error')); }
  finally { pdfExporting.value = false; }
}

async function refreshCounts() { try { await s.loadFolders(filters.accountId); } catch { /* non-fatal */ } }

async function readerTrash(m: MailMessage) { try { await s.trash([m.id]); readerOpen.value = false; await reload(); refreshCounts(); } catch { error(t('common.error')); } }
async function readerRestore(m: MailMessage) { try { await s.restore([m.id]); readerOpen.value = false; await reload(); } catch { error(t('common.error')); } }
async function readerSetSeen(seen: boolean) {
  const message = reader.value;
  if (!message) return;
  try { await s.setSeen([message.id], seen); message.seen = seen; const row = s.messages.find((m) => m.id === message.id); if (row) row.seen = seen; refreshCounts(); }
  catch { error(t('common.error')); }
}

async function doPushBack(m: MailMessage) {
  if (!await confirmAsk(t('mail.actions.confirm_push_back'))) return;
  try { await s.pushBack(m.id, m.folder); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function doDeleteOrigin(m: MailMessage) {
  if (!await confirmAsk(t('mail.actions.confirm_delete_origin'), { danger: true })) return;
  try { await s.deleteOrigin(m.id, m.folder); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function saveAtt(attId: string, target: 'files' | 'paperless' | 'finance') {
  const done = { files: 'mail.toast.saved_to_files', paperless: 'mail.toast.saved_to_paperless', finance: 'mail.toast.saved_to_finance' }[target];
  try { await s.saveAttachment(attId, target); success(t(done)); }
  catch { error(t('mail.toast.save_attachment_failed')); }
}
async function scanAttachment(attId: string) {
  attachmentVirusLoading.value = attId;
  try {
    const result = await s.virusTotalAttachment(attId);
    attachmentVirusResults[attId] = result;
    if (!result.known) success(t('mail.reader.virustotal_unknown'));
    else if ((result.stats?.malicious ?? 0) + (result.stats?.suspicious ?? 0) > 0) error(t('mail.reader.virustotal_detected'));
    else success(t('mail.reader.virustotal_clean'));
  } catch (e) {
    const code = e instanceof ApiError ? (e.body as { error?: string } | null)?.error : null;
    error(t(code === 'virustotal_not_configured' ? 'files.virustotal_not_configured'
      : code === 'virustotal_invalid_api_key' ? 'files.virustotal_invalid_api_key'
        : code === 'virustotal_rate_limited' ? 'files.virustotal_rate_limited' : 'common.error'));
  } finally { attachmentVirusLoading.value = null; }
}

// Save-to-Files with a destination folder picker (instead of always root).
interface FilesFolder { id: number; name: string; parent_id: number | null }
const attSave = reactive<{ show: boolean; attId: string | null; folders: FilesFolder[]; loading: boolean }>({ show: false, attId: null, folders: [], loading: false });
async function openAttSaveToFiles(attId: string) {
  attSave.attId = attId; attSave.show = true; attSave.loading = true;
  try { attSave.folders = (await api.get<{ folders: FilesFolder[] }>('/api/v1/files/folders')).folders ?? []; }
  catch { attSave.folders = []; } finally { attSave.loading = false; }
}
const attFolderOptions = computed(() => {
  const byId = new Map(attSave.folders.map((f) => [f.id, f]));
  const path = (f: FilesFolder): string => {
    const parts: string[] = []; let cur: FilesFolder | undefined = f; const seen = new Set<number>();
    while (cur && !seen.has(cur.id)) { seen.add(cur.id); parts.unshift(cur.name); cur = cur.parent_id != null ? byId.get(cur.parent_id) : undefined; }
    return parts.join(' / ');
  };
  return [{ id: null as number | null, label: t('files.all_files') }, ...attSave.folders.map((f) => ({ id: f.id, label: path(f) }))];
});
async function saveAttToFolder(folderId: number | null) {
  if (!attSave.attId) return;
  try { await s.saveAttachment(attSave.attId, 'files', folderId); attSave.show = false; success(t('mail.toast.saved_to_files')); }
  catch { error(t('mail.toast.save_attachment_failed')); }
}

// --- Bulk --------------------------------------------------------------------
const bulkBusy = ref(false);
async function bulkSeen(seen: boolean) {
  if (!s.selected.length || bulkBusy.value) return;
  const ids = [...s.selected];
  bulkBusy.value = true;
  try {
    await s.setSeen(ids, seen);
    await reload(); refreshCounts();
    undoable(t('mail.actions.marked_n', { n: String(ids.length) }), t('common.undo'), () => { void undoSeen(ids, !seen); });
  } catch { error(t('common.error')); } finally { bulkBusy.value = false; }
}
async function undoSeen(ids: string[], seen: boolean) {
  try { await s.setSeen(ids, seen); await reload(); refreshCounts(); } catch { error(t('common.error')); }
}

/**
 * Trash the selection, offering to take it back.
 *
 * No confirmation dialog: trashing here is a soft hide the undo reverses, and a
 * prompt in front of a reversible action is the kind that gets clicked through
 * without reading — which then makes the irreversible ones read the same way.
 */
async function bulkTrash() {
  if (!s.selected.length || bulkBusy.value) return;
  const ids = [...s.selected];
  bulkBusy.value = true;
  try {
    await s.trash(ids);
    await reload(); refreshCounts();
    undoable(t('mail.actions.trashed_n', { n: String(ids.length) }), t('common.undo'), () => { void undoTrash(ids); });
  } catch { error(t('common.error')); } finally { bulkBusy.value = false; }
}
async function undoTrash(ids: string[]) {
  try { await s.restore(ids); await reload(); refreshCounts(); } catch { error(t('common.error')); }
}
async function bulkRestore() { if (!s.selected.length) return; try { await s.restore([...s.selected]); await reload(); } catch { error(t('common.error')); } }

/** Star or unstar the selection — the endpoint already takes a list. */
async function bulkFlag(flagged: boolean) {
  if (!s.selected.length || bulkBusy.value) return;
  const ids = [...s.selected];
  bulkBusy.value = true;
  try {
    await s.setFlagged(ids, flagged);
    for (const m of s.messages) if (ids.includes(m.id)) m.flagged = flagged;
  } catch { error(t('mail.toast.flag_failed')); } finally { bulkBusy.value = false; }
}

/**
 * Mark a whole folder read, not just the loaded page.
 *
 * The page is what the selection can reach; this asks the server for the unread
 * ids under the current filter first, so "all" means all.
 */
async function markFolderRead() {
  if (bulkBusy.value) return;
  bulkBusy.value = true;
  try {
    const ids = await s.unreadIds();
    if (!ids.length) { toast(t('mail.actions.nothing_unread')); return; }
    await s.setSeen(ids, true);
    await reload(); refreshCounts();
    undoable(t('mail.actions.marked_n', { n: String(ids.length) }), t('common.undo'), () => { void undoSeen(ids, false); });
  } catch { error(t('common.error')); } finally { bulkBusy.value = false; }
}
// Toggle a label across the selection: remove it if every selected message
// already carries it, otherwise add it (uses the setLabels remove[] arm).
function selectionHasLabel(id: number): boolean {
  const sel = s.messages.filter((m) => s.selected.includes(m.id));
  return sel.length > 0 && sel.every((m) => (m.labels ?? []).some((l) => l.id === id));
}
async function bulkLabel(id: number) {
  if (!s.selected.length) return;
  const remove = selectionHasLabel(id);
  try { await s.setLabels([...s.selected], remove ? [] : [id], remove ? [id] : []); await Promise.all([reload(), s.loadLabels()]); }
  catch { error(t('common.error')); }
}

// --- Threading ---------------------------------------------------------------
const threadView = computed(() => filters.threadId !== null);
function viewThread() {
  if (!reader.value?.thread_id) return;
  filters.threadId = reader.value.thread_id;
  readerOpen.value = false;
  reload();
}
function clearThread() { filters.threadId = null; reload(); }

// --- Per-message labels in the reader ----------------------------------------
function readerHasLabel(id: number): boolean { return (reader.value?.labels ?? []).some((l) => l.id === id); }
async function toggleReaderLabel(id: number) {
  const m = reader.value;
  if (!m) return;
  const has = readerHasLabel(id);
  try {
    await s.setLabels([m.id], has ? [] : [id], has ? [id] : []);
    reader.value = await s.show(m.id);
    await Promise.all([reload(), s.loadLabels()]);
  } catch { error(t('common.error')); }
}

// --- Accounts ----------------------------------------------------------------
const folderInput = ref('');
const editor = reactive<{ show: boolean; id: number | null; step: 1 | 2; email: string; detecting: boolean; discovery: MailAutoconfig | null; saving: boolean; testing: boolean; folders: string[]; hasSmtpPassword: boolean; testResult: { ok: boolean; detail: string } | null; form: AccountBody }>({
  show: false, id: null, step: 1, email: '', detecting: false, discovery: null, saving: false, testing: false, folders: [], hasSmtpPassword: false, testResult: null,
  form: { name: '', host: '', port: 993, username: '', password: '', encryption: 'ssl', smtp_host: '', smtp_port: null, smtp_username: '', smtp_password: '', smtp_encryption: 'starttls', from_name: '', from_email: '', folders: null, backfill_since: null, delete_after_import: false, skip_spam: true, enabled: true, sync_interval_minutes: null },
});
function openAccountEditor(a: MailAccount | null) {
  editor.testResult = null; folderInput.value = '';
  editor.step = a ? 2 : 1; editor.email = ''; editor.discovery = null;
  editor.hasSmtpPassword = a?.has_smtp_password ?? false;
  if (a) {
    editor.id = a.id; editor.folders = [...(a.folders ?? [])];
    Object.assign(editor.form, { name: a.name, host: a.host, port: a.port, username: a.username, password: '', encryption: a.encryption, smtp_host: a.smtp_host ?? '', smtp_port: a.smtp_port, smtp_username: a.smtp_username ?? '', smtp_password: '', smtp_encryption: a.smtp_encryption ?? 'starttls', from_name: a.from_name ?? '', from_email: a.from_email ?? '', folders: a.folders, backfill_since: a.backfill_since, delete_after_import: a.delete_after_import, skip_spam: a.skip_spam, enabled: a.enabled, sync_interval_minutes: a.sync_interval_minutes });
  } else {
    editor.id = null; editor.folders = [];
    Object.assign(editor.form, { name: '', host: '', port: 993, username: '', password: '', encryption: 'ssl', smtp_host: '', smtp_port: null, smtp_username: '', smtp_password: '', smtp_encryption: 'starttls', from_name: '', from_email: '', folders: null, backfill_since: null, delete_after_import: false, skip_spam: true, enabled: true, sync_interval_minutes: null });
  }
  editor.show = true;
}
function defaultPort(encryption: string, protocol: 'imap' | 'smtp'): number {
  if (encryption === 'ssl' || encryption === 'tls') return protocol === 'imap' ? 993 : 465;
  return protocol === 'imap' ? 143 : 587;
}
function applyImapPort(encryption: string) { editor.form.port = defaultPort(encryption, 'imap'); }
function applySmtpPort(encryption: string) { editor.form.smtp_port = defaultPort(encryption, 'smtp'); }
async function discoverAccount() {
  const email = editor.email.trim().toLowerCase();
  if (!email || !email.includes('@')) { error(t('mail.setup.email_required')); return; }
  editor.detecting = true;
  try {
    const { configuration } = await s.autoconfig(email);
    editor.discovery = configuration;
    const imap = configuration.imap;
    const smtp = configuration.smtp;
    const local = email.slice(0, email.indexOf('@'));
    Object.assign(editor.form, {
      name: editor.form.name || email,
      host: imap?.host ?? editor.form.host,
      port: imap?.port ?? editor.form.port,
      username: imap?.username?.replace('%EMAILADDRESS%', email).replace('%EMAILLOCALPART%', local) ?? email,
      encryption: imap?.encryption ?? editor.form.encryption,
      smtp_host: smtp?.host ?? editor.form.smtp_host,
      smtp_port: smtp?.port ?? editor.form.smtp_port,
      smtp_username: smtp?.username?.replace('%EMAILADDRESS%', email).replace('%EMAILLOCALPART%', local) ?? email,
      smtp_encryption: smtp?.encryption ?? editor.form.smtp_encryption,
      from_email: email,
    });
    editor.step = 2;
  } catch { error(t('mail.setup.detect_failed')); } finally { editor.detecting = false; }
}
function addFolder() { const v = folderInput.value.trim(); if (v && !editor.folders.includes(v)) editor.folders.push(v); folderInput.value = ''; }
async function saveAccount() {
  editor.saving = true;
  try {
    const body: AccountBody = { ...editor.form, folders: editor.folders.length ? editor.folders : null };
    if (editor.id && !body.password) delete (body as Partial<AccountBody>).password;
    await s.saveAccount(body, editor.id);
    editor.show = false;
    await s.loadAccounts();
    success(t('mail.toast.saved'));
  } catch { error(t('mail.toast.save_failed')); } finally { editor.saving = false; }
}
async function runTest() {
  if (!editor.id) return;
  editor.testing = true; editor.testResult = null;
  try { editor.testResult = await s.testAccount(editor.id); } catch { editor.testResult = { ok: false, detail: '' }; } finally { editor.testing = false; }
}
async function doSyncNow(a: MailAccount) { try { await s.syncNow(a.id); a.status = 'syncing'; success(t('mail.toast.sync_started')); } catch { error(t('common.error')); } }
async function doCancelSync(a: MailAccount) { try { await s.cancelSync(a.id); a.status = 'idle'; success(t('mail.toast.sync_cancelled')); } catch { error(t('common.error')); } }
async function removeAccount(a: MailAccount) {
  if (!await confirmAsk(t('mail.accounts.delete_confirm'), { danger: true })) return;
  try {
    await s.deleteAccount(a.id);
    if (filters.accountId === a.id) { s.resetFilters(); await s.loadFolders(null); }
    await Promise.all([s.loadAccounts(), reload()]);
    success(t('mail.toast.deleted'));
  } catch { error(t('common.error')); }
}

// --- Sync logs ---------------------------------------------------------------
const logsDlg = reactive<{ show: boolean; accountId: number | null; level: string }>({ show: false, accountId: null, level: '' });
async function openLogs(a: MailAccount) { logsDlg.accountId = a.id; logsDlg.level = ''; logsDlg.show = true; await refreshLogs(); }
async function refreshLogs() { if (logsDlg.accountId == null) return; try { await s.loadLogs(logsDlg.accountId, logsDlg.level || null); } catch { error(t('common.error')); } }

// --- Labels manager ----------------------------------------------------------
const labelsDlg = reactive<{ show: boolean; busy: boolean; editing: MailLabel | null; name: string; color: string }>({ show: false, busy: false, editing: null, name: '', color: '#6366f1' });
function openLabelsMgr() { labelsDlg.show = true; labelsDlg.editing = null; labelsDlg.name = ''; labelsDlg.color = '#6366f1'; }
function editLabel(l: MailLabel) { labelsDlg.editing = l; labelsDlg.name = l.name; labelsDlg.color = l.color; }
async function saveLabel() {
  const name = labelsDlg.name.trim(); if (!name) return;
  labelsDlg.busy = true;
  try {
    if (labelsDlg.editing) await s.updateLabel(labelsDlg.editing.id, name, labelsDlg.color);
    else await s.createLabel(name, labelsDlg.color);
    await s.loadLabels();
    labelsDlg.editing = null; labelsDlg.name = ''; labelsDlg.color = '#6366f1';
  } catch { error(t('common.error')); } finally { labelsDlg.busy = false; }
}
async function removeLabel(l: MailLabel) {
  if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return;
  try { await s.deleteLabel(l.id); if (filters.label === l.id) filters.label = null; await s.loadLabels(); } catch { error(t('common.error')); }
}

// --- Rules -------------------------------------------------------------------
const rulesDlg = reactive<{ show: boolean; busy: boolean }>({ show: false, busy: false });
const rulesForm = reactive<{ name: string; from: string; to: string; subject: string; folder: string; has_attachment: boolean; mark_read: boolean; trash: boolean; skip: boolean; file_receipt: boolean; add_label: number }>({ name: '', from: '', to: '', subject: '', folder: '', has_attachment: false, mark_read: false, trash: false, skip: false, file_receipt: false, add_label: 0 });
async function openRules() { rulesDlg.show = true; try { await s.loadRules(); } catch { /* ignore */ } }
async function saveRule() {
  const name = rulesForm.name.trim(); if (!name) return;
  rulesDlg.busy = true;
  try {
    const rule: MailRule = {
      name, enabled: true, priority: (s.rules.length + 1) * 10,
      // Send only what was filled in: an empty string as a condition would
      // match nothing, which is not the same as "no condition".
      match: {
        from: rulesForm.from || null, to: rulesForm.to || null,
        subject: rulesForm.subject || null, folder: rulesForm.folder || null,
        has_attachment: rulesForm.has_attachment || null,
      },
      action: {
        mark_read: rulesForm.mark_read || null, trash: rulesForm.trash || null,
        skip: rulesForm.skip || null, file_receipt: rulesForm.file_receipt || null,
        add_label: Number(rulesForm.add_label) || null,
      },
    };
    await s.createRule(rule);
    await s.loadRules();
    Object.assign(rulesForm, { name: '', from: '', to: '', subject: '', folder: '', has_attachment: false, mark_read: false, trash: false, skip: false, file_receipt: false, add_label: 0 });
  } catch { error(t('common.error')); } finally { rulesDlg.busy = false; }
}
/**
 * Run a rule (or all of them) over mail that is already archived.
 *
 * Confirmed first: it walks the whole archive and can mark thousands of
 * messages read or trash them, which the undo toast cannot take back.
 */
async function runRule(rule: MailRule | null) {
  if (rulesDlg.busy) return;
  if (!await confirmAsk(rule ? t('mail.extras.rule_apply_confirm', { name: rule.name }) : t('mail.extras.rule_apply_all_confirm'))) return;
  rulesDlg.busy = true;
  try {
    await s.applyRules(rule?.id);
    success(t('mail.extras.rule_apply_started'));
  } catch { error(t('common.error')); } finally { rulesDlg.busy = false; }
}

/** What a rule does, in one line — a list of names says nothing. */
function ruleSummary(r: MailRule): string {
  const conditions: string[] = [];
  if (r.match.from) conditions.push(`from:${r.match.from}`);
  if (r.match.to) conditions.push(`to:${r.match.to}`);
  if (r.match.subject) conditions.push(`subject:${r.match.subject}`);
  if (r.match.folder) conditions.push(`folder:${r.match.folder}`);
  if (r.match.has_attachment) conditions.push(t('mail.extras.rule_has_attachment'));
  const actions: string[] = [];
  if (r.action.skip) actions.push(t('mail.extras.action_skip'));
  if (r.action.trash) actions.push(t('mail.extras.action_trash'));
  if (r.action.mark_read) actions.push(t('mail.extras.action_mark_read'));
  if (r.action.file_receipt) actions.push(t('mail.extras.action_file_receipt'));
  if (r.action.add_label) actions.push(s.labels.find((l) => l.id === r.action.add_label)?.name ?? '');
  return `${conditions.join(' ')} → ${actions.filter(Boolean).join(', ')}`;
}

async function removeRule(r: MailRule) { if (!r.id || !await confirmAsk(t('common.confirm_delete'), { danger: true })) return; try { await s.deleteRule(r.id); await s.loadRules(); } catch { error(t('common.error')); } }

// --- Saved searches ----------------------------------------------------------
async function saveCurrentSearch() {
  const name = await promptAsk(t('mail.extras.save_search'));
  if (!name) return;
  const f = { accountId: filters.accountId, folder: filters.folder, q: filters.q, seen: filters.seen, spam: filters.spam, label: filters.label, trashed: filters.trashed, dateFrom: filters.dateFrom, dateTo: filters.dateTo };
  try { await s.saveSearch(name, f); await s.loadSavedSearches(); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function applySaved(ss: MailSavedSearch) {
  s.resetFilters();
  const f = ss.filters as Partial<typeof filters>;
  Object.assign(filters, { accountId: f.accountId ?? null, folder: f.folder ?? null, q: f.q ?? '', seen: f.seen ?? null, spam: f.spam ?? null, label: f.label ?? null, trashed: f.trashed ?? false, dateFrom: f.dateFrom ?? null, dateTo: f.dateTo ?? null });
  // Keep the date inputs in sync with the restored range.
  dateFrom.value = filters.dateFrom ?? ''; dateTo.value = filters.dateTo ?? '';
  await s.loadFolders(filters.accountId); reload();
}
async function removeSaved(ss: MailSavedSearch) { try { await s.deleteSavedSearch(ss.id); await s.loadSavedSearches(); } catch { error(t('common.error')); } }

// --- Export + stats ----------------------------------------------------------
async function doExport(format: 'mbox' | 'zip') {
  try {
    const payload: { format: 'mbox' | 'zip'; ids?: string[]; folder?: string | null; label?: number | null } = { format };
    if (s.selected.length) payload.ids = [...s.selected];
    else if (filters.folder) payload.folder = filters.folder;
    else if (filters.label != null) payload.label = filters.label;
    await s.exportMessages(payload);
  } catch { error(t('common.error')); }
}
const statsDlg = reactive<{ show: boolean; loading: boolean; data: MailStats | null }>({ show: false, loading: false, data: null });
async function openStats() { statsDlg.show = true; statsDlg.loading = true; try { statsDlg.data = await s.loadStats(); } catch { error(t('common.error')); } finally { statsDlg.loading = false; } }

// --- Compose / reply / forward -----------------------------------------------
const compose = reactive<{
  show: boolean; minimized: boolean; mode: 'compose' | 'reply' | 'forward'; sending: boolean;
  sourceId: string | null; replyAll: boolean; accountId: number | null; signatureId: number | null; recipientHint: string;
  to: string; cc: string; bcc: string; subject: string; body: string; html: string; sentFolder: string; files: File[];
  fileIds: number[]; galleryPhotoIds: number[]; readReceipt: boolean; highPriority: boolean; draftId: string | null; cryptoMode: 'none' | 'sign' | 'encrypt' | 'sign_encrypt'; cryptoType: 'pgp' | 'smime'; signingKeyId: number | null; recipientKeyIds: number[];
}>({ show: false, minimized: false, mode: 'compose', sending: false, sourceId: null, replyAll: false, accountId: null, signatureId: null, recipientHint: '', to: '', cc: '', bcc: '', subject: '', body: '', html: '', sentFolder: '', files: [], fileIds: [], galleryPhotoIds: [], readReceipt: false, highPriority: false, draftId: null, cryptoMode: 'none', cryptoType: 'pgp', signingKeyId: null, recipientKeyIds: [] });
const composeSaving = ref(false);
const composeShowCc = ref(false);
const composeShowBcc = ref(false);
const composeShowCrypto = ref(false);
const recipientSuggestions = ref<{ name: string; email: string }[]>([]);
let recipientTimer: ReturnType<typeof setTimeout> | null = null;
let draftTimer: ReturnType<typeof setTimeout> | null = null;
const assetPicker = reactive<{ show: boolean; kind: 'files' | 'gallery'; q: string }>({ show: false, kind: 'files', q: '' });

const composeTitle = computed(() =>
  compose.mode === 'reply' ? (compose.replyAll ? t('mail.send.reply_all') : t('mail.send.reply'))
    : compose.mode === 'forward' ? t('mail.send.forward') : t('mail.send.compose'));
const composeAccountItems = computed(() => sendableAccounts.value.map((a) => ({ title: `${a.name} · ${a.from_email}`, value: a.id })));
const composeSignatureItems = computed(() => [{ title: t('common.none'), value: -1 }, ...signatures.value.filter((signature) => signature.account_ids.includes(Number(compose.accountId))).map((signature) => ({ title: signature.name, value: signature.id }))]);
const composeEditorLabels = computed(() => ({ toolbar: t('mail.send.compose_toolbar'), format: t('mail.send.format'), text: t('mail.send.text'), heading: t('mail.send.heading'), bold: t('mail.send.bold'), italic: t('mail.send.italic'), underline: t('mail.send.underline'), bullets: t('mail.send.bullet_list'), numbers: t('mail.send.numbered_list'), color: t('mail.send.color'), link: t('mail.send.link'), image: t('mail.send.image'), clear: t('mail.send.clear_formatting'), font: t('mail.send.font'), size: t('mail.send.size'), quote: t('mail.send.quote'), align_left: t('mail.send.align_left'), align_center: t('mail.send.align_center'), align_right: t('mail.send.align_right') }));
const cryptoModeItems = computed(() => [{ title: t('mail.send.crypto_none'), value: 'none' }, { title: t('mail.send.crypto_sign'), value: 'sign' }, { title: t('mail.send.crypto_encrypt'), value: 'encrypt' }, { title: t('mail.send.crypto_sign_encrypt'), value: 'sign_encrypt' }]);
const cryptoTypeItems = computed(() => [{ title: 'OpenPGP', value: 'pgp' }, { title: 'S/MIME', value: 'smime' }]);
const signingKeyItems = computed(() => cryptoStore.keys.filter((key) => key.type === compose.cryptoType && key.has_private).map((key) => ({ title: key.label, value: key.id })));
const recipientKeyItems = computed(() => cryptoStore.recipients.filter((key) => key.type === compose.cryptoType).map((key) => ({ title: key.label, value: key.id })));
async function openCryptoOptions() { composeShowCrypto.value = !composeShowCrypto.value; if (composeShowCrypto.value) { try { await cryptoStore.load(); } catch { error(t('common.error')); } } }
function defaultSignature(accountId: number | null): number | null { return signatures.value.find((signature) => accountId != null && signature.default_account_ids.includes(accountId))?.id ?? null; }
watch(() => compose.accountId, (accountId) => { if (compose.show) compose.signatureId = defaultSignature(accountId); });
watch(() => [compose.to, compose.cc, compose.bcc, compose.subject, compose.sentFolder, compose.signatureId, compose.readReceipt, compose.highPriority, compose.fileIds.join(','), compose.galleryPhotoIds.join(',')], () => scheduleDraft());

function parseEmails(str: string): string[] { return str.split(/[,;\n]+/).map((x) => x.trim()).filter(Boolean); }
function resetComposeFields() {
  Object.assign(compose, { minimized: false, to: '', cc: '', bcc: '', subject: '', body: '', html: '', sentFolder: '', files: [], fileIds: [], galleryPhotoIds: [], readReceipt: false, highPriority: false, draftId: null, recipientHint: '', replyAll: false, sourceId: null, signatureId: null, cryptoMode: 'none', cryptoType: 'pgp', signingKeyId: null, recipientKeyIds: [] });
  composeShowCc.value = false; composeShowBcc.value = false; composeShowCrypto.value = false;
}
const selectedFiles = computed(() => (filesStore.files as FileEntry[]).filter((file) => compose.fileIds.includes(file.id)));
const selectedPhotos = computed(() => (galleryStore.photos as Photo[]).filter((photo) => compose.galleryPhotoIds.includes(photo.id)));
const composeAttachmentCount = computed(() => compose.files.length + compose.fileIds.length + compose.galleryPhotoIds.length);
const assetPickerRows = computed(() => {
  const q = assetPicker.q.trim().toLowerCase();
  const rows = assetPicker.kind === 'files' ? filesStore.files : galleryStore.photos;
  return rows.filter((row) => !q || row.name.toLowerCase().includes(q)).slice(0, 250);
});
function isAssetSelected(id: number) { return assetPicker.kind === 'files' ? compose.fileIds.includes(id) : compose.galleryPhotoIds.includes(id); }
async function openAssetPicker(kind: 'files' | 'gallery') {
  assetPicker.kind = kind; assetPicker.q = '';
  try { if (kind === 'files' && !filesStore.files.length) await filesStore.load(); if (kind === 'gallery' && !galleryStore.photos.length) await galleryStore.load(); assetPicker.show = true; } catch { error(t('common.error')); }
}
// Files dropped on the compose window. A depth counter, not a boolean: a
// dragleave also fires when the pointer crosses into a CHILD element, and a
// boolean makes the overlay flicker across the editor.
const composeDragDepth = ref(0);
function onComposeDrop(e: DragEvent) {
  composeDragDepth.value = 0;
  const files = Array.from(e.dataTransfer?.files ?? []);
  if (!files.length) return;   // dragging text or a link is not an attachment
  addComposeFiles(files);
}

function selectAsset(id: number) { const ids = assetPicker.kind === 'files' ? compose.fileIds : compose.galleryPhotoIds; const index = ids.indexOf(id); if (index >= 0) ids.splice(index, 1); else if (ids.length + compose.files.length < 20) ids.push(id); scheduleDraft(); }
function removeFileAttachment(id: number) { compose.fileIds = compose.fileIds.filter((value) => value !== id); scheduleDraft(); }
function removeGalleryAttachment(id: number) { compose.galleryPhotoIds = compose.galleryPhotoIds.filter((value) => value !== id); scheduleDraft(); }
function onComposeRichText(html: string) { compose.html = html; compose.body = new DOMParser().parseFromString(html, 'text/html').body.innerText; scheduleDraft(); }
function setEditorContent() { /* v-model keeps the shared editor synchronized. */ }
function addRecipient(email: string) {
  const current = compose.to;
  const prefix = current.replace(/\s*[^,;\n]*$/, '').replace(/[\s,;]+$/, '').trim();
  compose.to = prefix === '' ? email : `${prefix}, ${email}`;
  recipientSuggestions.value = [];
  scheduleDraft();
}
function lookupRecipients() {
  if (recipientTimer) clearTimeout(recipientTimer);
  recipientTimer = setTimeout(async () => {
    const q = compose.to.split(/[,;\n]/).pop()?.trim() ?? '';
    if (q.length < 2) { recipientSuggestions.value = []; return; }
    // /contacts/suggest searches server-side. The previous version fetched
    // /contacts/data — the WHOLE address book — on every keystroke and filtered
    // it here, so each typed letter moved the entire book over the wire.
    try {
      const data = await api.get<{ contacts: { fn?: string | null; emails?: string[] | null }[] }>(`/api/v1/contacts/suggest?q=${encodeURIComponent(q)}`);
      recipientSuggestions.value = (data.contacts ?? [])
        .flatMap((contact) => (contact.emails ?? []).map((email) => ({ name: contact.fn ?? '', email })))
        .slice(0, 8);
    } catch { recipientSuggestions.value = []; }
  }, 180);
}
function draftPayload(): Omit<MailDraft, 'id' | 'updated_at'> { return { mail_account_id: compose.accountId, mode: compose.mode, source_message_id: compose.sourceId, to: parseEmails(compose.to), cc: parseEmails(compose.cc), bcc: parseEmails(compose.bcc), subject: compose.subject || null, text_body: compose.body || null, html_body: compose.html || null, mail_signature_id: compose.signatureId, sent_folder: compose.sentFolder || null, file_ids: compose.fileIds, gallery_photo_ids: compose.galleryPhotoIds, read_receipt: compose.readReceipt, high_priority: compose.highPriority, crypto_mode: compose.cryptoMode, crypto_type: compose.cryptoType, signing_key_id: compose.signingKeyId, recipient_key_ids: compose.recipientKeyIds }; }
async function persistDraft() {
  if (!compose.show || compose.sending) return;
  composeSaving.value = true;
  try {
    const row = compose.draftId ? await s.updateDraft(compose.draftId, draftPayload()) : await s.createDraft(draftPayload());
    compose.draftId = row.id;
    const index = drafts.value.findIndex((draft) => draft.id === row.id);
    if (index >= 0) drafts.value[index] = row; else drafts.value.unshift(row);
  } catch { /* The next edit retries without losing the local draft. */ } finally { composeSaving.value = false; }
}
function scheduleDraft() {
  if (!compose.show || compose.sending) return;
  if (draftTimer) clearTimeout(draftTimer);
  draftTimer = setTimeout(() => { void persistDraft(); }, 900);
}
async function saveDraftNow() { if (draftTimer) clearTimeout(draftTimer); await persistDraft(); }
async function closeCompose() { if (draftTimer) clearTimeout(draftTimer); await persistDraft(); compose.show = false; }
async function discardCompose() { if (draftTimer) clearTimeout(draftTimer); if (compose.draftId) { await s.deleteDraft(compose.draftId); drafts.value = drafts.value.filter((draft) => draft.id !== compose.draftId); } resetComposeFields(); compose.show = false; }
function openDraft(draft: MailDraft) {
  resetComposeFields();
  Object.assign(compose, { show: true, draftId: draft.id, mode: draft.mode, accountId: draft.mail_account_id, sourceId: draft.source_message_id, to: (draft.to ?? []).join(', '), cc: (draft.cc ?? []).join(', '), bcc: (draft.bcc ?? []).join(', '), subject: draft.subject ?? '', body: draft.text_body ?? '', html: draft.html_body ?? '', sentFolder: draft.sent_folder ?? '', signatureId: draft.mail_signature_id, fileIds: draft.file_ids ?? [], galleryPhotoIds: draft.gallery_photo_ids ?? [], readReceipt: draft.read_receipt, highPriority: draft.high_priority, cryptoMode: draft.crypto_mode ?? 'none', cryptoType: draft.crypto_type ?? 'pgp', signingKeyId: draft.signing_key_id ?? null, recipientKeyIds: draft.recipient_key_ids ?? [] });
  composeShowCc.value = compose.cc !== ''; composeShowBcc.value = compose.bcc !== '';
  readerOpen.value = false; setEditorContent();
}

function openCompose() {
  if (!sendableAccounts.value.length) { error(t('mail.send.no_smtp')); return; }
  resetComposeFields();
  compose.mode = 'compose';
  compose.accountId = sendableAccounts.value[0].id;
  compose.signatureId = defaultSignature(compose.accountId);
  compose.show = true;
  readerOpen.value = false;
  setEditorContent();
}
function openReply(all: boolean) {
  if (!reader.value || !readerCanSend.value) { error(t('mail.send.no_smtp')); return; }
  resetComposeFields();
  compose.mode = 'reply';
  compose.sourceId = reader.value.id;
  compose.replyAll = all;
  compose.accountId = reader.value.account_id;
  compose.signatureId = defaultSignature(compose.accountId);
  compose.recipientHint = reader.value.reply_to || reader.value.from_email || reader.value.from_name || '';
  compose.show = true;
  readerOpen.value = false;
  setEditorContent();
}
function openForward() {
  if (!reader.value || !readerCanSend.value) { error(t('mail.send.no_smtp')); return; }
  resetComposeFields();
  compose.mode = 'forward';
  compose.sourceId = reader.value.id;
  compose.accountId = reader.value.account_id;
  compose.signatureId = defaultSignature(compose.accountId);
  compose.show = true;
  readerOpen.value = false;
  setEditorContent();
}

function onComposeFiles(e: Event) {
  const input = e.target as HTMLInputElement;
  if (input.files) addComposeFiles(Array.from(input.files));
  input.value = '';
}
function addComposeFiles(files: File[]) {
  const accepted = files.slice(0, Math.max(0, 20 - compose.files.length - compose.fileIds.length - compose.galleryPhotoIds.length));
  compose.files.push(...accepted);
  scheduleDraft();
}
function removeComposeFile(i: number) { compose.files.splice(i, 1); scheduleDraft(); }

// Surface the backend's machine error code (ApiError body { ok:false, error }) as a toast.
function sendErr(e: unknown): string {
  if (e instanceof ApiError) {
    const code = (e.body as { error?: string } | null)?.error;
    if (code === 'no_smtp') return t('mail.send.no_smtp');
    if (code === 'no_recipient') return t('mail.send.no_recipient');
    if (code === 'empty_body') return t('mail.send.empty_body');
  }
  return t('mail.send.send_failed');
}

async function doSend() {
  if (compose.sending) return;
  compose.sending = true;
  try {
    if (compose.mode === 'compose') {
      const to = parseEmails(compose.to);
      const cc = parseEmails(compose.cc);
      const bcc = parseEmails(compose.bcc);
      if (!to.length && !cc.length && !bcc.length) { error(t('mail.send.no_recipient')); return; }
      if (!compose.body.trim() && !compose.files.length && !compose.fileIds.length && !compose.galleryPhotoIds.length) { error(t('mail.send.empty_body')); return; }
      const sent = compose.sentFolder.trim() || null;
      await s.compose({ account_id: Number(compose.accountId), to, cc, bcc, subject: compose.subject || null, text: compose.body || null, html: compose.html || null, signature_id: compose.signatureId, sent_folder: sent, files: compose.files, file_ids: compose.fileIds, gallery_photo_ids: compose.galleryPhotoIds, read_receipt: compose.readReceipt, high_priority: compose.highPriority, crypto_mode: compose.cryptoMode, crypto_type: compose.cryptoType, signing_key_id: compose.signingKeyId, recipient_key_ids: compose.recipientKeyIds });
    } else if (compose.mode === 'reply') {
      if (!compose.body.trim() && !compose.files.length && !compose.fileIds.length && !compose.galleryPhotoIds.length) { error(t('mail.send.empty_body')); return; }
      await s.reply(String(compose.sourceId), { text: compose.body || null, html: compose.html || null, signature_id: compose.signatureId, all: compose.replyAll, sent_folder: compose.sentFolder.trim() || null, files: compose.files, file_ids: compose.fileIds, gallery_photo_ids: compose.galleryPhotoIds, read_receipt: compose.readReceipt, high_priority: compose.highPriority, crypto_mode: compose.cryptoMode, crypto_type: compose.cryptoType, signing_key_id: compose.signingKeyId, recipient_key_ids: compose.recipientKeyIds });
    } else {
      const to = parseEmails(compose.to);
      if (!to.length) { error(t('mail.send.no_recipient')); return; }
      // Forward needs no body — the server attaches the original .eml + a header.
      await s.forward(String(compose.sourceId), { to, cc: parseEmails(compose.cc), text: compose.body || null, html: compose.html || null, signature_id: compose.signatureId, sent_folder: compose.sentFolder.trim() || null, files: compose.files, file_ids: compose.fileIds, gallery_photo_ids: compose.galleryPhotoIds, read_receipt: compose.readReceipt, high_priority: compose.highPriority, crypto_mode: compose.cryptoMode, crypto_type: compose.cryptoType, signing_key_id: compose.signingKeyId, recipient_key_ids: compose.recipientKeyIds });
    }
    if (compose.draftId) await s.deleteDraft(compose.draftId); drafts.value = drafts.value.filter((draft) => draft.id !== compose.draftId); compose.show = false;
    success(t('mail.send.sent'));
  } catch (e) { error(sendErr(e)); } finally { compose.sending = false; }
}
</script>
