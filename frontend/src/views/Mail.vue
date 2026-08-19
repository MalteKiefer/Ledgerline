<template>
  <div class="flex min-h-[calc(100vh-120px)] flex-col gap-4 md:h-[calc(100vh-9.5rem)] md:min-h-0 md:flex-row md:gap-0 md:overflow-hidden md:rounded-xl md:border md:border-[var(--ll-border)] md:bg-[var(--ll-surface)]">
    <!-- Left rail: accounts, folders, trash, labels, saved searches -->
    <Card body-class="p-0" class="w-full shrink-0 self-start md:h-full md:w-64 md:overflow-y-auto md:!rounded-none md:!border-y-0 md:!border-l-0 md:border-r md:border-r-[var(--ll-border)] md:!shadow-none">
      <div class="flex items-center gap-2 border-b border-[var(--ll-border)] p-3">
        <Btn variant="solid" icon="add" block @click="openAccountEditor(null)">{{ t('mail.accounts.add') }}</Btn>
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
        <TextField v-model="filters.q" :placeholder="t('mail.list.search_placeholder')" icon="search" class="min-w-48 flex-1" @update:model-value="debouncedReload" @enter="reload" />
        <div class="flex items-center gap-1.5">
          <TextField v-model="dateFrom" type="date" :placeholder="t('mail.list.date_from')" class="w-36" @update:model-value="onDate" />
          <TextField v-model="dateTo" type="date" :placeholder="t('mail.list.date_to')" class="w-36" @update:model-value="onDate" />
        </div>
        <div class="ml-auto flex items-center gap-1.5">
          <Btn :variant="filters.seen === false ? 'soft' : 'ghost'" size="sm" @click="toggleUnread">{{ t('mail.list.unread') }}</Btn>
          <Btn :variant="filters.spam === true ? 'soft' : 'ghost'" size="sm" @click="toggleSpam">{{ t('mail.list.spam') }}</Btn>
          <DropdownMenuRoot>
            <DropdownMenuTrigger class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('mail.extras.export')">
              <Icon name="more_vert" :size="18" />
            </DropdownMenuTrigger>
            <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] min-w-52 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
              <DropdownMenuItem :class="menuItem" @select="saveCurrentSearch"><Icon name="bookmark_add" :size="18" />{{ t('mail.extras.save_search') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="doExport('mbox')"><Icon name="download" :size="18" />{{ t('mail.extras.export_mbox') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="doExport('zip')"><Icon name="folder_zip" :size="18" />{{ t('mail.extras.export_zip') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="openRules"><Icon name="rule" :size="18" />{{ t('mail.extras.rules') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItem" @select="openStats"><Icon name="storage" :size="18" />{{ t('mail.extras.stats') }}</DropdownMenuItem>
            </DropdownMenuContent></DropdownMenuPortal>
          </DropdownMenuRoot>
        </div>
      </div>

      <!-- Selection bar -->
      <div v-if="s.selected.length" class="flex flex-wrap items-center gap-1 border-b border-[var(--ll-border)] bg-primary-500/5 px-3 py-2">
        <span class="text-xs font-medium">{{ s.selected.length }}</span>
        <div class="ml-auto flex flex-wrap items-center gap-1">
          <Btn variant="ghost" size="xs" icon="mark_email_read" @click="bulkSeen(true)">{{ t('mail.actions.mark_read') }}</Btn>
          <Btn variant="ghost" size="xs" icon="mark_email_unread" @click="bulkSeen(false)">{{ t('mail.actions.mark_unread') }}</Btn>
          <DropdownMenuRoot v-if="s.labels.length">
            <DropdownMenuTrigger class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs hover:bg-black/[0.05] dark:hover:bg-white/10"><Icon name="label" :size="15" />{{ t('mail.extras.labels') }}</DropdownMenuTrigger>
            <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] min-w-44 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
              <DropdownMenuItem v-for="l in s.labels" :key="l.id" :class="menuItem" @select="bulkLabel(l.id)"><span class="h-3 w-3 rounded-full" :style="{ background: l.color }" />{{ l.name }}</DropdownMenuItem>
            </DropdownMenuContent></DropdownMenuPortal>
          </DropdownMenuRoot>
          <Btn v-if="!filters.trashed" variant="ghost" size="xs" icon="delete" @click="bulkTrash">{{ t('mail.actions.trash') }}</Btn>
          <Btn v-else variant="ghost" size="xs" icon="restore" @click="bulkRestore">{{ t('mail.actions.restore') }}</Btn>
          <Btn variant="ghost" size="xs" @click="s.selected = []">{{ t('common.close') }}</Btn>
        </div>
      </div>

      <!-- Table -->
      <div v-if="threadView" class="flex items-center gap-2 border-b border-[var(--ll-border)] bg-primary-500/5 px-3 py-2 text-xs">
        <Icon name="forum" :size="15" class="text-primary-600 dark:text-primary-300" />
        <span class="flex-1">{{ t('mail.reader.thread_view') }}</span>
        <Btn variant="ghost" size="xs" icon="close" @click="clearThread">{{ t('common.clear') }}</Btn>
      </div>
      <div class="flex-1 overflow-y-auto">
        <div v-if="loading" class="py-16 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
        <div v-else-if="!s.messages.length" class="py-16 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.list.empty') }}</div>
        <table v-else class="w-full table-fixed text-sm">
          <thead class="sticky top-0 z-[1] bg-[var(--ll-surface)]">
            <tr class="border-b border-[var(--ll-border)] text-left text-xs text-[var(--ll-muted)]">
              <th class="w-9 pl-3"><input type="checkbox" class="accent-primary-500" :checked="allSelected" @change="toggleSelectAll"></th>
              <th class="w-[36%] py-2 pr-3">{{ t('mail.list.col_from') }}</th>
              <th class="py-2 pr-3">{{ t('mail.list.col_subject') }}</th>
              <th class="w-28 py-2 pr-3 text-right">{{ t('mail.list.col_date') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="m in s.messages" :key="m.id"
              class="cursor-pointer border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5"
              :class="[!m.seen ? 'font-semibold' : '', reader?.id === m.id ? 'bg-primary-500/[0.06]' : '']"
              @click="openReader(m)"
            >
              <td class="w-9 pl-3"><input type="checkbox" class="accent-primary-500" :checked="s.selected.includes(m.id)" @click.stop="toggleSelect(m.id)"></td>
              <td class="py-2.5 pr-3 align-middle">
                <div class="flex items-center gap-2">
                  <span class="h-2 w-2 shrink-0 rounded-full" :class="m.seen ? 'bg-transparent' : 'bg-primary-500'" />
                  <span class="min-w-0 flex-1 truncate">
                    <span class="truncate">{{ senderLabel(m) }}</span>
                    <span v-if="isUnified || !filters.folder" class="ml-1.5 rounded bg-black/[0.04] px-1.5 py-0.5 text-[0.6rem] font-medium text-[var(--ll-muted)] dark:bg-white/[0.07]">{{ m.folder }}</span>
                  </span>
                </div>
              </td>
              <td class="py-2.5 pr-3 align-middle">
                <div class="flex items-center gap-1.5">
                  <span class="min-w-0 flex-1 truncate">{{ m.subject || '—' }}</span>
                  <span v-for="l in (m.labels || [])" :key="l.id" class="hidden shrink-0 rounded px-1.5 py-0.5 text-[0.6rem] font-medium sm:inline" :style="{ background: `color-mix(in srgb, ${l.color} 15%, transparent)`, color: l.color }">{{ l.name }}</span>
                  <Icon v-if="m.encrypted_type" name="lock" :size="15" class="shrink-0 text-[var(--ll-muted)]" :title="t('mail.reader.encrypted')" />
                  <Icon v-if="m.spam" name="report" :size="15" class="shrink-0 text-amber-500" :title="t('mail.list.spam')" />
                  <Icon v-if="m.has_attachment" name="attach_file" :size="15" class="shrink-0 text-[var(--ll-muted)]" :title="t('mail.list.attachment')" />
                </div>
              </td>
              <td class="truncate py-2.5 pr-3 text-right text-xs text-[var(--ll-muted)]">{{ fmtDate(m.date || m.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="s.meta.last_page > 1" class="flex items-center justify-between border-t border-[var(--ll-border)] px-3 py-2 text-sm">
        <span class="text-xs text-[var(--ll-muted)]">{{ s.meta.current_page }} / {{ s.meta.last_page }} · {{ s.meta.total }}</span>
        <div class="flex items-center gap-1">
          <Btn variant="ghost" size="sm" icon="chevron_left" :disabled="s.meta.current_page <= 1" @click="goto(s.meta.current_page - 1)" />
          <Btn variant="ghost" size="sm" icon="chevron_right" :disabled="s.meta.current_page >= s.meta.last_page" @click="goto(s.meta.current_page + 1)" />
        </div>
      </div>
    </Card>

    <!-- Reader pane: docked beside the list on desktop, full screen on small displays. -->
  <aside v-if="readerOpen" class="fixed inset-0 z-[1500] flex min-h-0 flex-col overflow-y-auto bg-[var(--ll-surface)] shadow-2xl md:static md:z-auto md:w-auto md:basis-[44%] md:shrink-0 md:border-l md:border-[var(--ll-border)] md:shadow-none">
    <div v-if="reader" class="flex min-h-0 flex-1 flex-col gap-4 p-4 md:p-5">
      <div class="sticky top-0 z-10 -mt-4 -mx-4 flex items-center gap-3 border-b border-[var(--ll-border)] bg-[var(--ll-surface)] px-4 py-3 md:-mt-5 md:-mx-5 md:px-5">
        <div class="min-w-0 flex-1">
          <div class="truncate text-base font-semibold">{{ reader.subject || t('mail.reader.subject') }}</div>
          <div class="truncate text-xs text-[var(--ll-muted)]">{{ reader.from_name || reader.from_email }}</div>
        </div>
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
            <DropdownMenuItem v-if="reader.thread_id" :class="menuItem" @select="viewThread"><Icon name="forum" :size="18" />{{ t('mail.reader.view_thread') }}</DropdownMenuItem>
            <DropdownMenuItem :class="menuItem" :disabled="printing" @select="printMessage"><Icon name="print" :size="18" />{{ t('mail.reader.print') }}</DropdownMenuItem>
            <DropdownMenuItem :class="menuItem" :disabled="pdfExporting" @select="exportPdf"><Icon name="picture_as_pdf" :size="18" />{{ t('mail.reader.export_pdf') }}</DropdownMenuItem>
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
            <Btn variant="ghost" size="xs" icon="download" tag="a" :href="s.attachmentRawUrl(att.id)" :title="t('mail.reader.attachment_download')" />
            <DropdownMenuRoot>
              <DropdownMenuTrigger class="grid h-7 w-7 place-items-center rounded-md hover:bg-black/[0.05] dark:hover:bg-white/10"><Icon name="more_vert" :size="16" /></DropdownMenuTrigger>
              <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] min-w-44 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
                <DropdownMenuItem :class="menuItem" @select="openAttSaveToFiles(att.id)"><Icon name="folder" :size="18" />{{ t('mail.reader.save_to_files') }}</DropdownMenuItem>
                <DropdownMenuItem :class="menuItem" @select="saveAtt(att.id, 'paperless')"><Icon name="description" :size="18" />{{ t('mail.reader.save_to_paperless') }}</DropdownMenuItem>
              </DropdownMenuContent></DropdownMenuPortal>
            </DropdownMenuRoot>
          </div>
        </div>
      </div>
    </div>
    </aside>
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

  <!-- Rules modal -->
  <Modal v-model="rulesDlg.show" :title="t('mail.extras.rules')" width="640px">
    <div v-if="s.rules.length" class="mb-3 divide-y divide-[var(--ll-border)]">
      <div v-for="r in s.rules" :key="r.id" class="flex items-center gap-2 py-2">
        <Icon name="rule" :size="18" class="text-[var(--ll-muted)]" />
        <span class="min-w-0 flex-1 truncate text-sm">{{ r.name }}</span>
        <Badge v-if="!r.enabled" tone="gray">off</Badge>
        <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" @click="removeRule(r)" />
      </div>
    </div>
    <div class="space-y-3 border-t border-[var(--ll-border)] pt-3">
      <TextField v-model="rulesForm.name" :label="t('mail.extras.rule_match')" placeholder="Name" />
      <div class="grid grid-cols-2 gap-3">
        <TextField v-model="rulesForm.from" label="from" />
        <TextField v-model="rulesForm.subject" label="subject" />
      </div>
      <div class="flex flex-wrap items-center gap-3 text-sm">
        <span class="text-xs text-[var(--ll-muted)]">{{ t('mail.extras.rule_action') }}:</span>
        <label class="flex items-center gap-1.5"><input v-model="rulesForm.mark_read" type="checkbox" class="accent-primary-500">{{ t('mail.extras.action_mark_read') }}</label>
        <label class="flex items-center gap-1.5"><input v-model="rulesForm.trash" type="checkbox" class="accent-primary-500">{{ t('mail.extras.action_trash') }}</label>
        <label class="flex items-center gap-1.5"><input v-model="rulesForm.skip" type="checkbox" class="accent-primary-500">{{ t('mail.extras.action_skip') }}</label>
      </div>
      <Select v-if="s.labels.length" v-model="rulesForm.add_label" :label="t('mail.extras.action_add_label')" :options="labelRuleItems" />
    </div>
    <template #footer>
      <Btn variant="ghost" @click="rulesDlg.show = false">{{ t('common.close') }}</Btn>
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

  <Modal v-model="compose.show" :title="composeTitle" width="40rem">
    <div class="space-y-3">
      <!-- Account picker (compose only) -->
      <Select v-if="compose.mode === 'compose'" v-model.number="compose.accountId" :label="t('mail.send.from_email')" :options="composeAccountItems" />

      <!-- Reply recipient (server-derived, read-only) -->
      <div v-if="compose.mode === 'reply'" class="rounded-lg bg-black/[0.03] px-3 py-2 text-xs dark:bg-white/5">
        <span class="font-medium">{{ t('mail.send.to') }}:</span> {{ compose.recipientHint || '—' }}
        <span v-if="compose.replyAll" class="ml-1 text-[var(--ll-muted)]">· {{ t('mail.send.reply_all') }}</span>
      </div>

      <!-- Recipients (compose / forward) -->
      <template v-if="compose.mode !== 'reply'">
        <TextField v-model="compose.to" :label="t('mail.send.to')" placeholder="name@example.com, …" autocomplete="off" />
        <TextField v-model="compose.cc" :label="t('mail.send.cc')" placeholder="name@example.com, …" autocomplete="off" />
        <TextField v-if="compose.mode === 'compose'" v-model="compose.bcc" :label="t('mail.send.bcc')" placeholder="name@example.com, …" autocomplete="off" />
      </template>

      <!-- Subject (compose only; reply/forward derive Re:/Fwd: server-side) -->
      <TextField v-if="compose.mode === 'compose'" v-model="compose.subject" :label="t('mail.send.subject')" />

      <!-- Optional Sent-folder override (blank → the account's default "Sent"). -->
      <TextField v-model="compose.sentFolder" :label="t('mail.send.sent_folder')" :placeholder="t('mail.send.sent_folder_hint')" autocomplete="off" />

      <!-- Body -->
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.send.body') }}</span>
        <textarea
          v-model="compose.body" rows="8"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] placeholder:text-[var(--ll-muted)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
          :placeholder="t('mail.send.body')"
        ></textarea>
      </label>

      <!-- Attachments (compose only, multipart upload) -->
      <div v-if="compose.mode === 'compose'">
        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-[var(--ll-border)] px-3 py-1.5 text-sm hover:bg-black/[0.03] dark:hover:bg-white/5">
          <Icon name="attach_file" :size="16" />{{ t('mail.send.attachments') }}
          <input type="file" multiple class="hidden" @change="onComposeFiles">
        </label>
        <div v-if="compose.files.length" class="mt-2 space-y-1">
          <div v-for="(f, i) in compose.files" :key="i" class="flex items-center gap-2 rounded-lg bg-black/[0.03] px-2.5 py-1.5 text-sm dark:bg-white/5">
            <Icon name="attach_file" :size="15" class="shrink-0 text-[var(--ll-muted)]" />
            <span class="min-w-0 flex-1 truncate">{{ f.name }}</span>
            <span class="shrink-0 text-xs text-[var(--ll-muted)]">{{ fmtBytes(f.size) }}</span>
            <button type="button" class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-[var(--ll-muted)] hover:bg-black/[0.06] hover:text-red-600 dark:hover:bg-white/10" @click="removeComposeFile(i)"><Icon name="close" :size="14" /></button>
          </div>
        </div>
      </div>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="compose.show = false">{{ t('mail.form.cancel') }}</Btn>
      <Btn variant="solid" icon="send" :loading="compose.sending" @click="doSend">{{ t('mail.send.send') }}</Btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fmtDate as libDate, fmtDateTime as libDateTime } from '@spa/lib/datetime';
import { trans as t } from 'laravel-vue-i18n';
import { DropdownMenuRoot, DropdownMenuTrigger, DropdownMenuPortal, DropdownMenuContent, DropdownMenuItem } from 'reka-ui';
import { Icon, Btn, Card, TextField, Select, Badge, Modal } from '@spa/ui';
import { useMailStore, accountCanSend, type MailAccount, type MailMessage, type MailLabel, type MailSavedSearch, type MailRule, type MailStats, type MailAddress, type AccountBody, type MailAutoconfig } from '@spa/stores/mail';
import { api, ApiError } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';
import { renderInvoicePdfBlob } from '@spa/shared/invoice-print';

const s = useMailStore();
const route = useRoute();
const router = useRouter();
const { success, error } = useToast();
const filters = s.filters;

const menuItem = 'flex cursor-pointer items-center gap-2.5 rounded-md px-3 py-1.5 text-sm outline-none hover:bg-black/[0.05] dark:hover:bg-white/10';
const menuItemDanger = 'flex cursor-pointer items-center gap-2.5 rounded-md px-3 py-1.5 text-sm text-red-600 outline-none hover:bg-red-500/10';

const loading = ref(false);
const reader = ref<MailMessage | null>(null);
const readerOpen = ref(false);
const showHeaders = ref(false);
const remoteOn = ref(false);
const printing = ref(false);
const pdfExporting = ref(false);
const dateFrom = ref('');
const dateTo = ref('');

const isUnified = computed(() => filters.accountId === null && filters.label === null && !filters.trashed);
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
  await Promise.all([s.loadAccounts(), s.loadLabels(), s.loadSavedSearches()]);
  await applyRoute();
  statusTimer = setInterval(async () => {
    if (s.accounts.some((a) => a.status === 'syncing')) { await s.pollStatus(); await s.loadFolders(filters.accountId); }
  }, 5000);
});
onBeforeUnmount(() => { if (statusTimer) clearInterval(statusTimer); });

async function reload() {
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
async function pickUnified() { s.resetFilters(); await s.loadFolders(null); reload(); }
async function pickAccount(a: MailAccount) { s.resetFilters(); filters.accountId = a.id; await s.loadFolders(a.id); reload(); }
function pickFolder(folder: string) { filters.folder = folder; filters.trashed = false; reload(); }
function pickTrash() { filters.trashed = true; filters.label = null; reload(); }
function pickLabel(id: number) { s.resetFilters(); filters.label = id; s.loadFolders(null); reload(); }
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
  } catch { error(t('mail.toast.load_failed')); }
}
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
    popup.document.write(body.replace('</head>', '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:"></head>'));
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
    node.innerHTML = doc.body.innerHTML;
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
async function saveAtt(attId: string, target: 'files' | 'paperless') {
  try { await s.saveAttachment(attId, target); success(target === 'files' ? t('mail.toast.saved_to_files') : t('mail.toast.saved_to_paperless')); }
  catch { error(t('mail.toast.save_attachment_failed')); }
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
async function bulkSeen(seen: boolean) { if (!s.selected.length) return; try { await s.setSeen([...s.selected], seen); await reload(); refreshCounts(); } catch { error(t('common.error')); } }
async function bulkTrash() { if (!s.selected.length || !await confirmAsk(t('mail.actions.confirm_trash'))) return; try { await s.trash([...s.selected]); await reload(); refreshCounts(); } catch { error(t('common.error')); } }
async function bulkRestore() { if (!s.selected.length) return; try { await s.restore([...s.selected]); await reload(); } catch { error(t('common.error')); } }
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
const rulesForm = reactive<{ name: string; from: string; subject: string; mark_read: boolean; trash: boolean; skip: boolean; add_label: number }>({ name: '', from: '', subject: '', mark_read: false, trash: false, skip: false, add_label: 0 });
async function openRules() { rulesDlg.show = true; try { await s.loadRules(); } catch { /* ignore */ } }
async function saveRule() {
  const name = rulesForm.name.trim(); if (!name) return;
  rulesDlg.busy = true;
  try {
    const rule: MailRule = {
      name, enabled: true, priority: (s.rules.length + 1) * 10,
      match: { from: rulesForm.from || null, subject: rulesForm.subject || null },
      action: { mark_read: rulesForm.mark_read || null, trash: rulesForm.trash || null, skip: rulesForm.skip || null, add_label: Number(rulesForm.add_label) || null },
    };
    await s.createRule(rule);
    await s.loadRules();
    Object.assign(rulesForm, { name: '', from: '', subject: '', mark_read: false, trash: false, skip: false, add_label: 0 });
  } catch { error(t('common.error')); } finally { rulesDlg.busy = false; }
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
  show: boolean; mode: 'compose' | 'reply' | 'forward'; sending: boolean;
  sourceId: string | null; replyAll: boolean; accountId: number | null; recipientHint: string;
  to: string; cc: string; bcc: string; subject: string; body: string; sentFolder: string; files: File[];
}>({ show: false, mode: 'compose', sending: false, sourceId: null, replyAll: false, accountId: null, recipientHint: '', to: '', cc: '', bcc: '', subject: '', body: '', sentFolder: '', files: [] });

const composeTitle = computed(() =>
  compose.mode === 'reply' ? (compose.replyAll ? t('mail.send.reply_all') : t('mail.send.reply'))
    : compose.mode === 'forward' ? t('mail.send.forward') : t('mail.send.compose'));
const composeAccountItems = computed(() => sendableAccounts.value.map((a) => ({ title: `${a.name} · ${a.from_email}`, value: a.id })));

function parseEmails(str: string): string[] { return str.split(/[,;\n]+/).map((x) => x.trim()).filter(Boolean); }
function resetComposeFields() { Object.assign(compose, { to: '', cc: '', bcc: '', subject: '', body: '', sentFolder: '', files: [], recipientHint: '', replyAll: false, sourceId: null }); }

function openCompose() {
  if (!sendableAccounts.value.length) { error(t('mail.send.no_smtp')); return; }
  resetComposeFields();
  compose.mode = 'compose';
  compose.accountId = sendableAccounts.value[0].id;
  compose.show = true;
}
function openReply(all: boolean) {
  if (!reader.value || !readerCanSend.value) { error(t('mail.send.no_smtp')); return; }
  resetComposeFields();
  compose.mode = 'reply';
  compose.sourceId = reader.value.id;
  compose.replyAll = all;
  compose.accountId = reader.value.account_id;
  compose.recipientHint = reader.value.reply_to || reader.value.from_email || reader.value.from_name || '';
  compose.show = true;
}
function openForward() {
  if (!reader.value || !readerCanSend.value) { error(t('mail.send.no_smtp')); return; }
  resetComposeFields();
  compose.mode = 'forward';
  compose.sourceId = reader.value.id;
  compose.accountId = reader.value.account_id;
  compose.show = true;
}

function onComposeFiles(e: Event) {
  const input = e.target as HTMLInputElement;
  if (input.files) compose.files.push(...Array.from(input.files));
  input.value = '';
}
function removeComposeFile(i: number) { compose.files.splice(i, 1); }

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
  compose.sending = true;
  try {
    if (compose.mode === 'compose') {
      const to = parseEmails(compose.to);
      const cc = parseEmails(compose.cc);
      const bcc = parseEmails(compose.bcc);
      if (!to.length && !cc.length && !bcc.length) { error(t('mail.send.no_recipient')); return; }
      if (!compose.body.trim() && !compose.files.length) { error(t('mail.send.empty_body')); return; }
      const sent = compose.sentFolder.trim() || null;
      await s.compose({ account_id: Number(compose.accountId), to, cc, bcc, subject: compose.subject || null, text: compose.body || null, sent_folder: sent, files: compose.files });
    } else if (compose.mode === 'reply') {
      if (!compose.body.trim()) { error(t('mail.send.empty_body')); return; }
      await s.reply(String(compose.sourceId), { text: compose.body, all: compose.replyAll, sent_folder: compose.sentFolder.trim() || null });
    } else {
      const to = parseEmails(compose.to);
      if (!to.length) { error(t('mail.send.no_recipient')); return; }
      // Forward needs no body — the server attaches the original .eml + a header.
      await s.forward(String(compose.sourceId), { to, cc: parseEmails(compose.cc), text: compose.body || null, sent_folder: compose.sentFolder.trim() || null });
    }
    compose.show = false;
    success(t('mail.send.sent'));
  } catch (e) { error(sendErr(e)); } finally { compose.sending = false; }
}
</script>
