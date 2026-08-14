<template>
  <div
    class="relative flex flex-col gap-4 md:flex-row" style="min-height:calc(100vh - 120px)"
    @dragenter.prevent="onDragEnter" @dragover.prevent @dragleave.prevent="onDragLeave" @drop.prevent="onViewDrop"
  >
    <!-- Full-view drag & drop upload overlay -->
    <div v-show="dragDepth > 0 && !uploadState.active" class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center rounded-xl border-2 border-dashed border-primary-500 bg-primary-500/10">
      <div class="rounded-xl bg-[var(--ll-elevated)] px-6 py-4 text-center shadow-lg">
        <Icon name="upload" :size="32" class="text-primary-500" />
        <div class="mt-1 text-sm font-medium">{{ t('files.drop_here') }}</div>
        <div class="text-xs text-[var(--ll-muted)]">{{ folderPath(cwd) || t('files.root') }}</div>
      </div>
    </div>

    <!-- Upload name-conflict prompt (teleported, above everything) -->
    <Teleport to="body">
      <div v-if="conflict.show" class="fixed inset-0 z-[2100] flex items-center justify-center bg-black/40">
        <div class="w-96 max-w-[92%] rounded-xl bg-[var(--ll-elevated)] px-6 py-5 shadow-xl">
          <div class="text-sm font-semibold">{{ t('files.conflict_title') }}</div>
          <p class="mt-1 break-words text-sm text-[var(--ll-muted)]">{{ t('files.conflict_body', { name: conflict.name }) }}</p>
          <label class="mt-3 flex items-center gap-2 text-sm">
            <input v-model="conflictAll" type="checkbox" class="accent-primary-500">
            {{ t('files.conflict_apply_all') }}
          </label>
          <div class="mt-4 flex flex-wrap justify-end gap-2">
            <Btn variant="ghost" size="sm" @click="resolveConflict('skip')">{{ t('files.conflict_skip') }}</Btn>
            <Btn variant="soft" size="sm" @click="resolveConflict('copy')">{{ t('files.conflict_copy') }}</Btn>
            <Btn variant="solid" size="sm" class="!bg-red-600" @click="resolveConflict('overwrite')">{{ t('files.conflict_overwrite') }}</Btn>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Upload progress modal (teleported so it sits above all page content) -->
    <Teleport to="body">
      <div v-show="uploadState.active && !conflict.show" class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/30">
        <div class="w-80 max-w-[90%] rounded-xl bg-[var(--ll-elevated)] px-6 py-5 shadow-xl">
          <div class="flex items-center gap-2 text-sm font-medium">
            <Icon name="upload" :size="20" class="text-primary-500" />
            {{ t('files.uploading') }} <span class="ml-auto tabular-nums text-[var(--ll-muted)]">{{ uploadState.done }} / {{ uploadState.total }}</span>
          </div>
          <div class="mt-1 truncate text-xs text-[var(--ll-muted)]">{{ uploadState.name }}</div>
          <div class="mt-3 h-2 overflow-hidden rounded-full bg-black/[0.08] dark:bg-white/10">
            <div class="h-full rounded-full bg-primary-500 transition-all" :style="{ width: uploadPct + '%' }" />
          </div>
          <div class="mt-1 text-right text-xs tabular-nums text-[var(--ll-muted)]">{{ uploadPct }}%</div>
        </div>
      </div>
    </Teleport>
    <!-- Sidebar -->
    <Card :body-class="'p-0'" class="w-full shrink-0 self-start md:w-60">
      <div class="p-3">
        <Btn variant="solid" block icon="upload" @click="pickUpload">{{ t('files.upload') }}</Btn>
        <Btn variant="ghost" size="sm" block icon="drive_folder_upload" class="mt-1" @click="pickUploadDir">{{ t('files.upload_folder') }}</Btn>
        <input ref="uploadInput" type="file" multiple class="hidden" @change="onUpload" >
        <input ref="uploadDirInput" type="file" webkitdirectory multiple class="hidden" @change="onUploadDir" >
      </div>
      <nav class="space-y-0.5 px-2 pb-2">
        <button
          v-for="nav in navItems" :key="nav.v"
          class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="view===nav.v ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
          @click="setView(nav.v)"
        >
          <Icon :name="nav.icon" :size="20" :class="view===nav.v ? 'text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'" />
          {{ t(nav.label) }}
        </button>
      </nav>
      <!-- External storage mounts -->
      <div class="border-t border-[var(--ll-border)] px-2 py-2">
        <div class="flex items-center justify-between px-1.5 pb-1">
          <span class="text-[10px] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('files.mounts') }}</span>
          <button class="rounded p-0.5 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('files.mount_add')" @click="openMountForm()"><Icon name="add" :size="16" /></button>
        </div>
        <button
          v-for="m in mnt.mounts" :key="m.id"
          class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="view==='mount' && activeMount?.id===m.id ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
          @click="openMount(m)"
        >
          <Icon :name="m.type==='s3' ? 'cloud' : 'dns'" :size="18" class="text-[var(--ll-muted)]" />
          <span class="min-w-0 flex-1 truncate text-left">{{ m.name }}</span>
          <Icon v-if="m.read_only" name="lock" :size="13" class="text-[var(--ll-muted)]" />
        </button>
        <p v-if="!mnt.mounts.length" class="px-2 py-1 text-xs text-[var(--ll-muted)]">{{ t('files.mount_none') }}</p>
      </div>
      <div v-if="s.usage" class="border-t border-[var(--ll-border)] p-3">
        <div v-if="s.usage.quota" class="mb-1.5 h-1.5 w-full overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10">
          <div class="h-full rounded-full bg-primary-500" :style="{ width: quotaPct + '%' }" />
        </div>
        <div class="text-xs text-[var(--ll-muted)]">{{ fmt(s.usage.used) }}<span v-if="s.usage.quota"> / {{ fmt(s.usage.quota) }}</span></div>
      </div>
    </Card>

    <!-- Main -->
    <Card :body-class="'flex flex-1 flex-col overflow-hidden p-0'" class="flex min-w-0 flex-1 flex-col overflow-hidden">
      <!-- Toolbar -->
      <div class="flex flex-wrap items-center gap-2 px-4 py-3">
        <nav v-if="view==='files'" class="flex flex-wrap items-center gap-1 text-sm">
          <template v-for="(c, i) in crumbs" :key="i">
            <Icon v-if="i>0" name="chevron_right" :size="16" class="text-[var(--ll-muted)]" />
            <button
              class="rounded px-1 py-0.5 hover:bg-black/[0.04] dark:hover:bg-white/5"
              :class="i===crumbs.length-1 ? 'font-medium' : 'text-primary-600 dark:text-primary-300'"
              @click="cwd = c.value"
            >{{ c.title }}</button>
          </template>
        </nav>
        <nav v-else-if="view==='mount'" class="flex flex-wrap items-center gap-1 text-sm">
          <Icon :name="activeMount?.type==='s3' ? 'cloud' : 'dns'" :size="16" class="text-[var(--ll-muted)]" />
          <button class="rounded px-1 py-0.5 text-primary-600 hover:bg-black/[0.04] dark:text-primary-300 dark:hover:bg-white/5" @click="mountGo('')">{{ activeMount?.name }}</button>
          <template v-for="(c, i) in mountCrumbs" :key="i">
            <Icon name="chevron_right" :size="16" class="text-[var(--ll-muted)]" />
            <button class="rounded px-1 py-0.5 hover:bg-black/[0.04] dark:hover:bg-white/5" :class="i===mountCrumbs.length-1 ? 'font-medium' : 'text-primary-600 dark:text-primary-300'" @click="mountGo(c.path)">{{ c.name }}</button>
          </template>
        </nav>
        <h2 v-else class="text-sm font-semibold">{{ view === 'favorites' ? t('files.favorites') : view === 'shared' ? t('files.shared_by_me') : t('files.trash') }}</h2>

        <div class="ml-auto flex items-center gap-1">
          <Btn v-if="view==='files'" variant="ghost" size="sm" icon="create_new_folder" @click="newFolder">{{ t('files.new_folder') }}</Btn>
          <Btn v-if="view==='files' && cwd!==null" variant="ghost" size="sm" icon="folder_zip" @click="zipFolder">{{ t('files.download_zip') }}</Btn>
          <template v-if="view==='mount'">
            <Btn v-if="!mountRO" variant="soft" size="sm" icon="upload" :loading="mountBusy" @click="pickMountUpload">{{ t('files.upload') }}</Btn>
            <Btn v-if="!mountRO" variant="ghost" size="sm" icon="create_new_folder" @click="mountMkdir">{{ t('files.new_folder') }}</Btn>
            <Btn variant="ghost" size="sm" icon="settings" @click="openMountForm(activeMount)">{{ t('common.edit') }}</Btn>
            <input ref="mountUploadInput" type="file" multiple class="hidden" @change="onMountUpload">
          </template>
          <Btn v-if="view!=='mount'" variant="ghost" size="sm" icon="storage" @click="openStorage">{{ t('files.storage') }}</Btn>
          <Btn v-if="view!=='mount'" variant="ghost" size="sm" icon="history" @click="openActivity">{{ t('files.activity') }}</Btn>
          <Btn v-if="view==='trash' && trashFiles.length" variant="ghost" size="sm" icon="delete" class="text-red-600" @click="emptyTrash">{{ t('files.empty_trash') }}</Btn>
          <Btn v-if="view!=='mount'" variant="ghost" size="sm" :icon="layout==='grid' ? 'view_list' : 'grid_view'" @click="layout = layout==='grid' ? 'list' : 'grid'" />
        </div>
      </div>
      <div class="border-t border-[var(--ll-border)]" />

      <!-- Search -->
      <div v-if="view!=='shared' && view!=='mount'" class="flex items-center gap-2 border-b border-[var(--ll-border)] px-4 py-2.5">
        <div class="w-full max-w-xs">
          <TextField v-model="query" :placeholder="searching ? t('files.searching') : t('files.search')" icon="search" />
        </div>
        <Icon v-if="searching" name="progress_activity" :size="18" class="animate-spin text-[var(--ll-muted)]" />
      </div>

      <!-- Label filter bar -->
      <div v-if="view!=='trash' && view!=='shared' && view!=='mount' && s.labels.length" class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] px-4 py-2.5">
        <span class="mr-1 text-xs text-[var(--ll-muted)]">{{ t('files.filtered_by') }}</span>
        <button
          v-for="l in (s.labels as FileLabel[])" :key="l.id" type="button"
          class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium transition-colors"
          :style="activeLabels.includes(l.id)
            ? { background: l.color, color: '#fff' }
            : { background: `color-mix(in srgb, ${l.color} 15%, transparent)`, color: l.color }"
          @click="toggleLabelFilter(l.id)"
        >
          <Icon name="label" :size="14" />{{ l.name }}
        </button>
        <Btn variant="ghost" size="xs" icon="sell" class="ml-auto" @click="openLabels">{{ t('files.labels_manage') }}</Btn>
      </div>

      <!-- Selection bar -->
      <div v-if="selected.length && view!=='mount'" class="flex items-center gap-2 border-b border-[var(--ll-border)] bg-primary-500/5 px-4 py-2.5">
        <span class="text-xs font-medium">{{ selected.length }} {{ t('files.selected_word') }}</span>
        <div class="ml-auto flex items-center gap-1">
          <Btn variant="ghost" size="sm" icon="folder_zip" @click="zipSelected">{{ t('files.download_zip') }}</Btn>
          <Btn variant="ghost" size="sm" icon="archive" @click="openArchive">{{ t('files.archive_create') }}</Btn>
          <Btn variant="ghost" size="sm" icon="drive_file_move" @click="openBulk('move')">{{ t('files.move') }}</Btn>
          <Btn variant="ghost" size="sm" icon="content_copy" @click="openBulk('copy')">{{ t('files.copy') }}</Btn>
          <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" @click="bulkTrash">{{ t('files.trash') }}</Btn>
          <Btn variant="ghost" size="sm" @click="selected = []">{{ t('common.close') }}</Btn>
        </div>
      </div>

      <!-- Shared by me: public links + cross-user folder shares, with revoke -->
      <div v-if="view==='shared'" class="flex-1 space-y-6 overflow-y-auto p-4">
        <div>
          <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('files.shared_links') }}</h3>
          <div v-if="!myLinks.length" class="text-sm text-[var(--ll-muted)]">{{ t('files.shared_none') }}</div>
          <div v-for="sh in myLinks" :key="'l'+sh.id" class="flex items-center gap-3 border-b border-[var(--ll-border)] py-2.5 last:border-0">
            <Icon :name="sh.kind==='folder' ? 'folder' : 'description'" :size="20" class="text-[var(--ll-muted)]" />
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-medium">{{ sh.name || ('#'+sh.id) }}</div>
              <div class="flex flex-wrap items-center gap-1.5 text-xs text-[var(--ll-muted)]">
                <Badge v-if="sh.needs_password" tone="warning">{{ t('files.share_password') }}</Badge>
                <Badge v-if="!sh.allow_download" tone="gray">{{ t('files.share_no_download') }}</Badge>
                <Badge v-if="sh.expires_at" :tone="expiryTone(sh.expires_at)">{{ expiresLabel(sh.expires_at) }}</Badge>
              </div>
            </div>
            <Btn variant="ghost" size="sm" icon="content_copy" :title="t('files.share_copy')" @click="copyLink(sh)" />
            <Btn variant="ghost" size="sm" icon="link_off" class="text-red-600" :title="t('files.share_revoke')" @click="revokeLink(sh)">{{ t('files.share_revoke') }}</Btn>
          </div>
        </div>
        <div>
          <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('files.shared_with_users') }}</h3>
          <div v-if="!myFolderShares.length" class="text-sm text-[var(--ll-muted)]">{{ t('files.shared_none') }}</div>
          <div v-for="sh in myFolderShares" :key="'f'+sh.id" class="border-b border-[var(--ll-border)] py-2.5 last:border-0">
            <div class="flex items-center gap-3">
              <Icon :name="sh.kind==='folder' ? 'folder_shared' : 'description'" :size="20" class="text-[var(--ll-muted)]" />
              <div class="min-w-0 flex-1 truncate text-sm font-medium">{{ sh.folder_name || sh.file_name || ('#'+sh.id) }}</div>
              <Btn variant="ghost" size="sm" icon="person_remove" class="text-red-600" :title="t('files.share_revoke')" @click="revokeFolderShare(sh)">{{ t('files.share_revoke') }}</Btn>
            </div>
            <div class="mt-1 flex flex-wrap gap-1.5 pl-8">
              <span v-for="m in sh.members" :key="m.id" class="inline-flex items-center gap-1 rounded-full bg-black/[0.05] px-2 py-0.5 text-xs dark:bg-white/10">
                {{ m.email || m.name || m.user_id }} · {{ m.role }}
                <button class="text-[var(--ll-muted)] hover:text-red-600" @click="revokeMember(sh, m)">×</button>
              </span>
            </div>
          </div>
        </div>
        <div>
          <div class="mb-2 flex items-center justify-between">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('files.ul_section') }}</h3>
            <Btn variant="soft" size="sm" icon="add_link" @click="createUploadLink">{{ t('files.ul_create') }}</Btn>
          </div>
          <p class="mb-2 text-xs text-[var(--ll-muted)]">{{ t('files.ul_hint') }}</p>
          <div v-if="!myUploadLinks.length" class="text-sm text-[var(--ll-muted)]">{{ t('files.shared_none') }}</div>
          <div v-for="l in myUploadLinks" :key="'u'+l.id" class="flex items-center gap-3 border-b border-[var(--ll-border)] py-2.5 last:border-0">
            <Icon name="cloud_upload" :size="20" class="text-[var(--ll-muted)]" />
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-medium">{{ l.label || t('files.ul_title') }}</div>
              <div class="flex flex-wrap items-center gap-1.5 text-xs text-[var(--ll-muted)]">
                <span>→ {{ l.folder_name || t('files.root') }}</span>
                <Badge v-if="l.expires_at" :tone="expiryTone(l.expires_at)">{{ expiresLabel(l.expires_at) }}</Badge>
              </div>
            </div>
            <Btn variant="ghost" size="sm" icon="content_copy" :title="t('files.share_copy')" @click="copyUploadLink(l)" />
            <Btn variant="ghost" size="sm" icon="link_off" class="text-red-600" :title="t('files.share_revoke')" @click="revokeUploadLink(l)">{{ t('files.share_revoke') }}</Btn>
          </div>
        </div>
      </div>

      <!-- External mount browser -->
      <div v-else-if="view==='mount'" class="flex-1 overflow-y-auto p-2">
        <div v-if="mountLoading" class="py-16 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
        <div v-else-if="!mountListing.dirs.length && !mountListing.files.length" class="py-16 text-center text-sm text-[var(--ll-muted)]">{{ t('files.mount_empty') }}</div>
        <table v-else class="w-full text-sm">
          <tbody>
            <tr v-for="d in mountListing.dirs" :key="'d:'+d.path" class="border-b border-[var(--ll-border)]/60 hover:bg-black/[0.03] dark:hover:bg-white/5">
              <td class="cursor-pointer py-2 pl-2" @click="mountGo(d.path)"><Icon name="folder" :size="18" class="mr-2 inline text-primary-500" />{{ d.name }}</td>
              <td class="w-24 text-right text-xs text-[var(--ll-muted)]" />
              <td class="w-10 pr-2 text-right">
                <button v-if="!mountRO" class="rounded p-1 text-[var(--ll-muted)] hover:text-red-600" :title="t('common.delete')" @click="mountDelete(d, true)"><Icon name="delete" :size="16" /></button>
              </td>
            </tr>
            <tr v-for="f in mountListing.files" :key="'f:'+f.path" class="border-b border-[var(--ll-border)]/60 hover:bg-black/[0.03] dark:hover:bg-white/5">
              <td class="py-2 pl-2"><Icon name="description" :size="18" class="mr-2 inline text-[var(--ll-muted)]" />{{ f.name }}</td>
              <td class="w-24 text-right text-xs text-[var(--ll-muted)]">{{ f.size != null ? fmt(f.size) : '' }}</td>
              <td class="w-16 whitespace-nowrap pr-2 text-right">
                <a :href="mnt.downloadUrl(activeMount!.id, f.path)" class="rounded p-1 text-[var(--ll-muted)] hover:text-primary-600" :title="t('common.download')"><Icon name="download" :size="16" /></a>
                <button v-if="!mountRO" class="rounded p-1 text-[var(--ll-muted)] hover:text-red-600" :title="t('common.delete')" @click="mountDelete(f, false)"><Icon name="delete" :size="16" /></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="flex-1 overflow-y-auto p-2">
        <div v-if="!rows.length" class="py-16 text-center text-sm text-[var(--ll-muted)]">{{ view==='trash' ? t('files.trash_empty') : t('files.empty_explorer') }}</div>

        <!-- Grid -->
        <div v-else-if="layout==='grid'" class="grid grid-cols-2 gap-3 p-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
          <div
            v-for="row in rows" :key="row._k"
            class="overflow-hidden rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] transition-colors hover:border-primary-500/40"
            @dblclick="open(row)"
          >
            <div class="flex h-28 items-center justify-center bg-black/[0.03] dark:bg-white/5">
              <img v-if="row._img" :src="s.thumbUrl(row.raw as FileEntry)" class="h-full w-full object-cover" >
              <Icon v-else :name="row._icon" :size="40" :class="row._folder ? 'text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'" />
            </div>
            <div class="flex items-center gap-1 p-2">
              <span class="flex-1 truncate text-xs" :title="row.name">{{ row.name }}</span>
              <DropdownMenuRoot>
                <DropdownMenuTrigger class="grid h-7 w-7 shrink-0 place-items-center rounded-md hover:bg-black/[0.05] dark:hover:bg-white/10" @click.stop>
                  <Icon name="more_vert" :size="16" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] min-w-48 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
                  <template v-if="!row._folder && view!=='trash'">
                    <DropdownMenuItem as="a" :href="s.rawUrl(row.raw as FileEntry)" :class="menuItemCls"><Icon name="download" :size="18" />{{ t('files.download') }}</DropdownMenuItem>
                    <DropdownMenuItem v-if="s.isArchive(String(row.name))" :class="menuItemCls" @select="doExtract(row)"><Icon name="unarchive" :size="18" />{{ t('files.archive_extract') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="fav(row.raw as FileEntry)"><Icon name="star" :size="18" />{{ t('files.favorite') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="doRename(row)"><Icon name="drive_file_rename_outline" :size="18" />{{ t('files.rename') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openMove(row)"><Icon name="drive_file_move" :size="18" />{{ t('files.move') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openInfo(row)"><Icon name="info" :size="18" />{{ t('files.info') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openVersions(row)"><Icon name="history" :size="18" />{{ t('files.versions') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openShare(row)"><Icon name="share" :size="18" />{{ t('files.share') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemDangerCls" @select="doTrash(row)"><Icon name="delete" :size="18" />{{ t('files.trash') }}</DropdownMenuItem>
                  </template>
                  <template v-else-if="row._folder && view!=='trash'">
                    <DropdownMenuItem :class="menuItemCls" @select="doRename(row)"><Icon name="drive_file_rename_outline" :size="18" />{{ t('files.rename') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openMove(row)"><Icon name="drive_file_move" :size="18" />{{ t('files.move') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openShare(row)"><Icon name="share" :size="18" />{{ t('files.share') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemDangerCls" @select="doTrash(row)"><Icon name="delete" :size="18" />{{ t('files.trash') }}</DropdownMenuItem>
                  </template>
                  <template v-else>
                    <DropdownMenuItem :class="menuItemCls" @select="doRestore(row)"><Icon name="restore" :size="18" />{{ t('files.restore') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemDangerCls" @select="doForce(row)"><Icon name="delete_forever" :size="18" />{{ t('common.delete') }}</DropdownMenuItem>
                  </template>
                </DropdownMenuContent></DropdownMenuPortal>
              </DropdownMenuRoot>
            </div>
          </div>
        </div>

        <!-- List -->
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <tbody>
              <tr
                v-for="row in rows" :key="row._k"
                class="cursor-pointer border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5"
                @click="open(row)"
              >
                <td v-if="view!=='trash'" class="w-9 pl-3">
                  <input
                    v-if="!row._folder" type="checkbox" class="accent-primary-500"
                    :checked="selected.includes(row.id)"
                    @click.stop="toggleSelect(row.id)"
                  >
                </td>
                <td class="py-2 pl-2 pr-3">
                  <div class="flex items-center gap-3">
                    <span
                      class="grid h-9 w-9 shrink-0 place-items-center rounded-lg"
                      :class="row._folder ? 'bg-primary-500/15 text-primary-600 dark:text-primary-300' : 'bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10'"
                    >
                      <Icon :name="row._icon" :size="20" />
                    </span>
                    <div class="min-w-0">
                      <div class="truncate">{{ row.name }}</div>
                      <div v-if="!row._folder" class="flex flex-wrap items-center gap-1 text-xs text-[var(--ll-muted)]">
                        <span class="ll-mono">{{ fmt((row.raw as FileEntry).size) }}</span>
                        <span
                          v-for="l in row._labels" :key="l.id"
                          class="rounded px-1.5 py-0.5 text-[0.65rem] font-medium"
                          :style="{ background: `color-mix(in srgb, ${l.color} 15%, transparent)`, color: l.color }"
                        >{{ l.name }}</span>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="w-10 pr-3 text-right">
                  <DropdownMenuRoot>
                    <DropdownMenuTrigger class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" @click.stop>
                      <Icon name="more_vert" :size="18" />
                    </DropdownMenuTrigger>
                    <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] min-w-48 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
                      <template v-if="!row._folder && view!=='trash'">
                        <DropdownMenuItem as="a" :href="s.rawUrl(row.raw as FileEntry)" :class="menuItemCls"><Icon name="download" :size="18" />{{ t('files.download') }}</DropdownMenuItem>
                        <DropdownMenuItem v-if="s.isArchive(String(row.name))" :class="menuItemCls" @select="doExtract(row)"><Icon name="unarchive" :size="18" />{{ t('files.archive_extract') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemCls" @select="fav(row.raw as FileEntry)"><Icon name="star" :size="18" />{{ t('files.favorite') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemCls" @select="doRename(row)"><Icon name="drive_file_rename_outline" :size="18" />{{ t('files.rename') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openMove(row)"><Icon name="drive_file_move" :size="18" />{{ t('files.move') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemCls" @select="openInfo(row)"><Icon name="info" :size="18" />{{ t('files.info') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemCls" @select="openVersions(row)"><Icon name="history" :size="18" />{{ t('files.versions') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemCls" @select="openShare(row)"><Icon name="share" :size="18" />{{ t('files.share') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemDangerCls" @select="doTrash(row)"><Icon name="delete" :size="18" />{{ t('files.trash') }}</DropdownMenuItem>
                      </template>
                      <template v-else-if="row._folder && view!=='trash'">
                        <DropdownMenuItem :class="menuItemCls" @select="doRename(row)"><Icon name="drive_file_rename_outline" :size="18" />{{ t('files.rename') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openMove(row)"><Icon name="drive_file_move" :size="18" />{{ t('files.move') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemDangerCls" @select="doTrash(row)"><Icon name="delete" :size="18" />{{ t('files.trash') }}</DropdownMenuItem>
                      </template>
                      <template v-else>
                        <DropdownMenuItem :class="menuItemCls" @select="doRestore(row)"><Icon name="restore" :size="18" />{{ t('files.restore') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemDangerCls" @select="doForce(row)"><Icon name="delete_forever" :size="18" />{{ t('common.delete') }}</DropdownMenuItem>
                      </template>
                    </DropdownMenuContent></DropdownMenuPortal>
                  </DropdownMenuRoot>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </Card>
  </div>

  <!-- Info dialog -->
  <Modal v-model="info.show" :title="t('files.info_title')" width="560px">
    <div class="flex flex-col gap-3">
      <TextField v-model="info.name" :label="t('files.info_name')" />
      <div class="flex gap-6">
        <div><div class="text-xs text-[var(--ll-muted)]">{{ t('files.info_mime') }}</div><div class="text-sm">{{ info.file?.mime || '—' }}</div></div>
        <div><div class="text-xs text-[var(--ll-muted)]">{{ t('files.info_size') }}</div><div class="text-sm ll-mono">{{ info.file ? fmt(info.file.size) : '—' }}</div></div>
      </div>
      <TextField v-model="info.tags" :label="t('files.info_tags')" :placeholder="t('files.tags_placeholder')" />
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('files.note') }}</span>
        <textarea
          v-model="info.note" :placeholder="t('files.note_placeholder')" rows="3"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] placeholder:text-[var(--ll-muted)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
        ></textarea>
      </label>
      <div v-if="s.labels.length">
        <div class="mb-1.5 text-xs text-[var(--ll-muted)]">{{ t('files.info_labels') }}</div>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="l in (s.labels as FileLabel[])" :key="l.id" type="button"
            class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium transition-colors"
            :style="info.labelIds.includes(l.id)
              ? { background: l.color, color: '#fff' }
              : { background: `color-mix(in srgb, ${l.color} 15%, transparent)`, color: l.color }"
            @click="info.labelIds.includes(l.id) ? info.labelIds.splice(info.labelIds.indexOf(l.id), 1) : info.labelIds.push(l.id)"
          >{{ l.name }}</button>
        </div>
      </div>

      <!-- Read-only rich detail (Stufe 0/1/2). -->
      <div class="mt-1 border-t border-[var(--ll-border)] pt-3">
        <div v-if="infoLoading" class="text-sm text-[var(--ll-muted)]">…</div>
        <div v-else-if="infoDetail" class="flex flex-col gap-3 text-sm">
          <!-- General -->
          <dl class="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-1">
            <dt class="text-xs text-[var(--ll-muted)]">{{ t('files.info_path') }}</dt>
            <dd class="ll-mono truncate" :title="infoDetail.path">{{ infoDetail.path }}</dd>
            <dt class="text-xs text-[var(--ll-muted)]">{{ t('files.info_uploaded') }}</dt>
            <dd>{{ infoDetail.created_at ? fmtDateTime(infoDetail.created_at) : '—' }}</dd>
            <dt class="text-xs text-[var(--ll-muted)]">{{ t('files.info_modified') }}</dt>
            <dd>{{ infoDetail.updated_at ? fmtDateTime(infoDetail.updated_at) : '—' }}</dd>
            <template v-if="infoDetail.versions > 1">
              <dt class="text-xs text-[var(--ll-muted)]">{{ t('files.info_version') }}</dt>
              <dd>{{ t('files.info_version_n', { v: String(infoDetail.version), n: String(infoDetail.versions) }) }}</dd>
            </template>
            <template v-if="infoDetail.sha256">
              <dt class="text-xs text-[var(--ll-muted)]">{{ t('files.info_checksum') }}</dt>
              <dd class="ll-mono truncate" :title="infoDetail.sha256">{{ infoDetail.sha256.slice(0, 16) }}…</dd>
            </template>
          </dl>

          <!-- Type-specific metadata (Stufe 1). -->
          <div v-if="infoDetail.metadata && Object.keys(infoDetail.metadata.fields).length">
            <div class="mb-1 text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ metaKindLabel(infoDetail.metadata.kind) }}</div>
            <dl class="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-0.5">
              <template v-for="(v, k) in infoDetail.metadata.fields" :key="k">
                <dt class="text-xs text-[var(--ll-muted)]">{{ k }}</dt>
                <dd class="truncate" :title="v">{{ v }}</dd>
              </template>
            </dl>
          </div>

          <!-- Content snippet (Stufe 2). -->
          <div v-if="infoDetail.snippet">
            <div class="mb-1 text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('files.info_content') }}</div>
            <p class="line-clamp-3 text-[var(--ll-muted)]">{{ infoDetail.snippet }}</p>
          </div>

          <!-- Sharing status (Stufe 0). -->
          <div v-if="infoDetail.share" class="flex items-center gap-2 text-[var(--ll-muted)]">
            <Icon name="share" :size="16" />
            <span>{{ t('files.info_shared') }}<template v-if="infoDetail.share.protected"> · {{ t('files.info_protected') }}</template><template v-if="infoDetail.share.expires_at"> · {{ t('files.info_expires', { d: fmtDateTime(infoDetail.share.expires_at) }) }}</template></span>
          </div>

          <!-- Duplicates (Stufe 2 = only). -->
          <div v-if="infoDetail.duplicates.length">
            <div class="mb-1 text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('files.info_duplicates', { n: String(infoDetail.duplicates.length) }) }}</div>
            <div v-for="d in infoDetail.duplicates" :key="d.id" class="flex items-center gap-2 py-0.5">
              <Icon name="content_copy" :size="14" class="text-[var(--ll-muted)]" />
              <span class="truncate">{{ d.name }}</span>
              <span class="truncate text-xs text-[var(--ll-muted)]">{{ d.path }}</span>
            </div>
          </div>

          <!-- Recent activity (Stufe 0). -->
          <div v-if="infoDetail.activity.length">
            <div class="mb-1 text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('files.info_activity') }}</div>
            <div v-for="a in infoDetail.activity" :key="a.id" class="flex items-center justify-between py-0.5 text-xs">
              <span>{{ actLabel(a.action) }}<template v-if="a.actor"> · {{ a.actor }}</template></span>
              <span class="text-[var(--ll-muted)]">{{ fmtDateTime(a.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="info.show=false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="info.busy" @click="saveInfo">{{ t('common.save') }}</Btn>
    </template>
  </Modal>

  <!-- Versions dialog -->
  <Modal v-model="versionsDlg.show" :title="t('files.versions')" width="480px">
    <div v-if="versionsDlg.loading" class="py-6 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
    <div v-else-if="!versionsDlg.list.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('files.versions_none') }}</div>
    <div v-else class="divide-y divide-[var(--ll-border)]">
      <div v-for="v in versionsDlg.list" :key="v.id" class="flex items-center gap-2 py-2.5">
        <div class="min-w-0 flex-1">
          <div class="text-sm ll-mono">{{ fmt(v.size) }}</div>
          <div class="text-xs text-[var(--ll-muted)]">{{ v.created_at ? fmtDate(v.created_at) : '—' }}</div>
        </div>
        <Btn variant="ghost" size="sm" icon="download" :title="t('files.version_download')" @click="downloadVersion(v.id)" />
        <Btn variant="ghost" size="sm" icon="restore" :title="t('files.version_restore')" @click="restoreVersion(v.id)" />
      </div>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="versionsDlg.show=false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>

  <!-- Share dialog: public link + share-with-a-registered-user (both files and folders) -->
  <Modal v-model="shareDlg.show" :title="t('files.share_title')" width="520px">
    <!-- Tab bar: public link vs. share with a registered user (both share kinds) -->
    <div class="mb-4 flex gap-1 rounded-lg bg-black/[0.04] p-0.5 dark:bg-white/5">
      <button
        v-for="tab in shareTabs" :key="tab.v" type="button"
        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
        :class="shareDlg.tab===tab.v ? 'bg-[var(--ll-surface)] text-primary-600 shadow-sm dark:text-primary-300' : 'text-[var(--ll-muted)]'"
        @click="shareDlg.tab = tab.v"
      >{{ t(tab.label) }}</button>
    </div>

    <!-- Public link -->
    <div v-show="shareDlg.tab==='link'" class="flex flex-col gap-3">
      <template v-if="shareDlg.share">
        <div>
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('files.share_link_label') }}</span>
          <div class="flex items-center gap-2">
            <input
              :value="s.shareUrl(shareDlg.share.token)" readonly
              class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm ll-mono focus:outline-none focus:ring-2 focus:ring-primary-500/40"
            >
            <Btn variant="outline" icon="content_copy" @click="copyShare" />
          </div>
        </div>
        <Btn variant="soft" size="sm" block icon="link" @click="copyShare">{{ t('files.share_copy') }}</Btn>
      </template>
      <button type="button" class="flex items-center gap-3" @click="shareDlg.allowDownload = !shareDlg.allowDownload">
        <span class="relative h-5 w-9 rounded-full transition-colors" :class="shareDlg.allowDownload ? 'bg-primary-500' : 'bg-black/15 dark:bg-white/20'">
          <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white transition-transform" :class="shareDlg.allowDownload ? 'translate-x-4' : 'translate-x-0.5'" />
        </span>
        <span class="text-sm">{{ t('files.share_allow_download') }}</span>
      </button>
      <TextField v-model="shareDlg.password" :label="t('files.share_password')" type="password" autocomplete="new-password" />
      <TextField v-model="shareDlg.expires" :label="t('files.share_expiry')" type="date" />
      <div class="flex items-center gap-2 pt-1">
        <Btn v-if="shareDlg.share" variant="danger" icon="delete" size="sm" :loading="shareDlg.busy" class="mr-auto" @click="revokeShare">{{ t('files.share_revoke') }}</Btn>
        <Btn v-if="!shareDlg.share" variant="solid" size="sm" class="ml-auto" :loading="shareDlg.busy" @click="createShareLink">{{ t('files.share_create_link') }}</Btn>
        <Btn v-else variant="solid" size="sm" :loading="shareDlg.busy" @click="updateShareLink">{{ t('files.share_update') }}</Btn>
      </div>
    </div>

    <!-- Share with a registered user (files and folders) -->
    <div v-show="shareDlg.tab==='users'" class="flex flex-col gap-3">
      <div class="flex items-end gap-2">
        <div class="flex-1"><TextField v-model="shareDlg.inviteEmail" :label="t('files.sf_recipient_email')" type="email" autocomplete="off" @enter="inviteUser" /></div>
        <div class="w-32"><Select v-model="shareDlg.inviteRole" :label="t('files.sf_role')" :options="roleOptions" /></div>
      </div>
      <Btn variant="solid" size="sm" icon="person_add" :loading="shareDlg.inviteBusy" @click="inviteUser">{{ t('files.folder_share_add') }}</Btn>

      <div v-if="shareDlg.members.length" class="mt-1 divide-y divide-[var(--ll-border)]">
        <div v-for="m in shareDlg.members" :key="m.user_id" class="flex items-center gap-2 py-2">
          <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary-500/15 text-xs font-medium text-primary-600 dark:text-primary-300">
            {{ (m.name || m.email || '?').slice(0, 1).toUpperCase() }}
          </span>
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm">{{ m.name || m.email }}</div>
            <div v-if="m.name" class="truncate text-xs text-[var(--ll-muted)]">{{ m.email }}</div>
          </div>
          <div class="w-28 shrink-0"><Select :model-value="m.role" :options="roleOptions" @update:model-value="changeMemberRole(m, $event as 'viewer'|'editor')" /></div>
          <Btn variant="ghost" size="sm" icon="person_remove" class="text-red-600" :title="t('files.sf_remove_member')" @click="removeMember(m)" />
        </div>
      </div>
      <div v-else class="py-4 text-center text-sm text-[var(--ll-muted)]">{{ t('files.folder_members') }} —</div>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="shareDlg.show=false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>

  <!-- Manage labels dialog -->
  <Modal v-model="labelsDlg.show" :title="t('files.labels_title')" width="440px">
    <div v-if="!s.labels.length" class="mb-3 text-sm text-[var(--ll-muted)]">{{ t('files.labels_none') }}</div>
    <div v-else class="mb-2 divide-y divide-[var(--ll-border)]">
      <div v-for="l in (s.labels as FileLabel[])" :key="l.id" class="flex items-center gap-2 py-2">
        <span class="h-5 w-5 shrink-0 rounded-full" :style="{ background: l.color }" />
        <span class="flex-1 text-sm">{{ l.name }}</span>
        <Btn variant="ghost" size="sm" icon="edit" @click="editLabel(l)" />
        <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" @click="removeLabel(l)" />
      </div>
    </div>
    <div class="border-t border-[var(--ll-border)] pt-3">
      <div class="flex items-end gap-2">
        <input type="color" v-model="labelsDlg.color" class="h-10 w-10 shrink-0 cursor-pointer rounded-lg border border-[var(--ll-border)] bg-transparent p-0.5" >
        <div class="flex-1">
          <TextField v-model="labelsDlg.name" :label="t('files.label_name')" @enter="saveLabel" />
        </div>
        <Btn variant="solid" :loading="labelsDlg.busy" @click="saveLabel">{{ labelsDlg.editing ? t('common.save') : t('files.label_add') }}</Btn>
      </div>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="labelsDlg.show=false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>

  <!-- Activity feed dialog -->
  <Modal v-model="activityDlg.show" :title="t('files.activity')" width="560px">
    <div v-if="activityDlg.loading" class="py-6 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
    <div v-else-if="!activityDlg.rows.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('files.activity_empty') }}</div>
    <ul v-else class="max-h-[60vh] divide-y divide-[var(--ll-border)] overflow-y-auto">
      <li v-for="a in activityDlg.rows" :key="a.id" class="flex items-start gap-3 py-2.5">
        <Icon :name="activityIcon(a.action)" :size="18" class="mt-0.5 shrink-0 text-[var(--ll-muted)]" />
        <div class="min-w-0 flex-1">
          <div class="truncate text-sm"><span class="font-medium">{{ activityLabel(a) }}</span></div>
          <div class="text-xs text-[var(--ll-muted)]">
            <span v-if="a.actor">{{ a.actor }} · </span>{{ fmtDate(a.created_at) }}
          </div>
        </div>
      </li>
    </ul>
    <template #footer>
      <Btn variant="ghost" @click="activityDlg.show=false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>

  <!-- Mount add/edit dialog -->
  <Modal v-model="mountForm.show" :title="mountForm.id ? t('files.mount_edit') : t('files.mount_add')" width="520px">
    <div class="space-y-3">
      <div class="flex gap-2">
        <button v-for="tp in (['s3','sftp'] as const)" :key="tp" class="flex-1 rounded-lg border px-3 py-2 text-sm" :class="mountForm.type===tp ? 'border-primary-500 bg-primary-500/10 text-primary-600' : 'border-[var(--ll-border)]'" :disabled="!!mountForm.id" @click="mountForm.type=tp">{{ tp==='s3' ? 'S3' : 'SFTP' }}</button>
      </div>
      <TextField v-model="mountForm.name" :label="t('files.mount_name')" />
      <template v-if="mountForm.type==='s3'">
        <TextField v-model="mountForm.bucket" label="Bucket" />
        <TextField v-model="mountForm.region" label="Region" />
        <TextField v-model="mountForm.endpoint" :label="t('files.mount_endpoint')" placeholder="https://…" />
        <TextField v-model="mountForm.key" label="Access Key" />
        <TextField v-model="mountForm.secret" type="password" label="Secret Key" :placeholder="mountForm.id ? '••••••' : ''" />
        <TextField v-model="mountForm.path_prefix" :label="t('files.mount_prefix')" />
        <label class="flex items-center gap-2 text-sm"><input v-model="mountForm.use_path_style" type="checkbox" class="accent-primary-500">{{ t('files.mount_path_style') }}</label>
      </template>
      <template v-else>
        <div class="flex gap-2"><TextField v-model="mountForm.host" class="flex-1" :label="t('files.mount_host')" /><TextField v-model="mountForm.port" label="Port" class="w-24" /></div>
        <TextField v-model="mountForm.username" :label="t('files.mount_user')" />
        <TextField v-model="mountForm.password" type="password" :label="t('files.mount_password')" :placeholder="mountForm.id ? '••••••' : ''" />
        <TextField v-model="mountForm.root" :label="t('files.mount_root')" />
      </template>
      <label class="flex items-center gap-2 text-sm"><input v-model="mountForm.read_only" type="checkbox" class="accent-primary-500">{{ t('files.mount_readonly') }}</label>
      <p v-if="mountForm.err" class="text-sm text-red-600">{{ mountForm.err }}</p>
    </div>
    <template #footer>
      <Btn v-if="mountForm.id" variant="ghost" class="!text-red-600" @click="deleteMount">{{ t('common.delete') }}</Btn>
      <div class="flex-1" />
      <Btn variant="ghost" :loading="mountForm.testing" @click="testMount">{{ t('files.mount_test') }}</Btn>
      <Btn variant="solid" :loading="mountForm.busy" @click="saveMount">{{ t('common.save') }}</Btn>
    </template>
  </Modal>

  <!-- Storage stats dialog -->
  <Modal v-model="storageDlg.show" :title="t('files.storage')" width="520px">
    <div v-if="storageDlg.loading" class="py-6 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
    <template v-else-if="storageDlg.data">
      <div class="text-xs text-[var(--ll-muted)]">{{ t('files.storage_used_only', { used: fmt(storageDlg.data.used) }) }}</div>
      <div class="mb-1 mt-3 text-sm font-semibold">{{ t('files.storage_by_type') }}</div>
      <div class="divide-y divide-[var(--ll-border)]">
        <div v-for="(size, type) in storageDlg.data.by_type" :key="type" class="flex items-center justify-between py-2">
          <span class="text-sm capitalize">{{ type }}</span>
          <span class="text-xs ll-mono">{{ fmt(size) }}</span>
        </div>
      </div>
      <div class="mb-1 mt-3 text-sm font-semibold">{{ t('files.duplicates') }}</div>
      <div v-if="!storageDlg.data.duplicates.length" class="text-xs text-[var(--ll-muted)]">{{ t('files.duplicates_none') }}</div>
      <div v-else class="divide-y divide-[var(--ll-border)]">
        <div v-for="(grp, gi) in storageDlg.data.duplicates" :key="gi" class="py-1">
          <div v-for="d in grp" :key="d.id" class="flex items-center justify-between py-1">
            <div class="min-w-0">
              <div class="truncate text-sm">{{ d.name }}</div>
              <div class="truncate text-xs text-[var(--ll-muted)]">{{ d.path }}</div>
            </div>
            <span class="ml-2 shrink-0 text-xs ll-mono">{{ fmt(d.size) }}</span>
          </div>
        </div>
      </div>
    </template>
    <template #footer>
      <Btn variant="ghost" @click="storageDlg.show=false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>

  <!-- Move file/folder into another folder -->
  <Modal v-model="moveDlg.show" :title="t('files.move')" width="440px">
    <template v-if="moveDlg.row">
      <p class="mb-2 truncate text-sm text-[var(--ll-muted)]">{{ moveDlg.row.name }}</p>
      <div class="max-h-80 overflow-y-auto">
        <button
          v-for="o in moveTargets" :key="String(o.id)"
          class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
          @click="doMove(o.id)"
        >
          <Icon name="folder" :size="18" class="text-[var(--ll-muted)]" />{{ o.label }}
        </button>
      </div>
    </template>
  </Modal>

  <!-- Bulk move / copy the selection into a folder -->
  <Modal v-model="bulkDlg.show" :title="bulkDlg.mode === 'copy' ? t('files.copy') : t('files.move')" width="440px">
    <p class="mb-2 text-sm text-[var(--ll-muted)]">{{ selected.length }} {{ t('files.selected_word') }}</p>
    <div class="max-h-80 overflow-y-auto">
      <button
        v-for="o in bulkTargets" :key="String(o.id)"
        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
        @click="doBulk(o.id)"
      >
        <Icon name="folder" :size="18" class="text-[var(--ll-muted)]" />{{ o.label }}
      </button>
    </div>
  </Modal>

  <!-- Create an archive from the selection (format / compression / password) -->
  <Modal v-model="archiveDlg.show" :title="t('files.archive_title')" width="460px">
    <div class="space-y-3">
      <p class="text-sm text-[var(--ll-muted)]">{{ selected.length }} {{ t('files.selected_word') }}</p>
      <label class="block text-sm">
        <span class="mb-1 block font-medium">{{ t('files.archive_format') }}</span>
        <select v-model="archiveDlg.format" class="w-full rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] px-3 py-2 text-sm">
          <option value="zip">ZIP (.zip)</option>
          <option value="tar.gz">TAR.GZ (.tar.gz)</option>
          <option value="tar.xz">TAR.XZ (.tar.xz)</option>
          <option value="7z">7-Zip (.7z)</option>
        </select>
      </label>
      <label class="block text-sm">
        <span class="mb-1 block font-medium">{{ t('files.archive_level') }}</span>
        <select v-model.number="archiveDlg.level" class="w-full rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] px-3 py-2 text-sm">
          <option :value="0">{{ t('files.archive_level_store') }}</option>
          <option :value="1">{{ t('files.archive_level_fast') }}</option>
          <option :value="6">{{ t('files.archive_level_normal') }}</option>
          <option :value="9">{{ t('files.archive_level_max') }}</option>
        </select>
      </label>
      <label class="block text-sm">
        <span class="mb-1 block font-medium">{{ t('files.archive_name') }}</span>
        <input v-model="archiveDlg.name" type="text" :placeholder="'archive'" class="w-full rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] px-3 py-2 text-sm">
      </label>
      <label v-if="archiveDlg.format === 'zip' || archiveDlg.format === '7z'" class="block text-sm">
        <span class="mb-1 block font-medium">{{ t('files.archive_password') }}</span>
        <input v-model="archiveDlg.password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] px-3 py-2 text-sm">
        <span class="mt-1 block text-xs text-[var(--ll-muted)]">{{ t('files.archive_password_hint') }}</span>
      </label>
      <div class="flex justify-end gap-2 pt-1">
        <Btn variant="ghost" size="sm" @click="archiveDlg.show = false">{{ t('common.close') }}</Btn>
        <Btn size="sm" :loading="archiveDlg.busy" @click="doArchive">{{ t('files.archive_create') }}</Btn>
      </div>
    </div>
  </Modal>

  <!-- Create a public upload link: browse the folder tree to pick the target -->
  <Modal v-model="ulDlg" :title="t('files.ul_create')" width="720px">
    <div class="space-y-3">
      <!-- Folder browser -->
      <div>
        <label class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('files.ul_target') }}</label>
        <div class="rounded-lg border border-[var(--ll-border)]">
          <!-- Breadcrumb -->
          <div class="flex flex-wrap items-center gap-1 border-b border-[var(--ll-border)] px-3 py-2 text-sm">
            <template v-for="(c, i) in pickerCrumbs" :key="String(c.id)">
              <Icon v-if="i > 0" name="chevron_right" :size="16" class="text-[var(--ll-muted)]" />
              <button class="rounded px-1.5 py-0.5 hover:bg-black/[0.04] dark:hover:bg-white/5" :class="c.id === pickerCwd ? 'font-semibold text-primary-600 dark:text-primary-300' : ''" @click="pickerCwd = c.id">{{ c.name }}</button>
            </template>
          </div>
          <!-- Children (click a folder to open it) -->
          <div class="h-64 overflow-y-auto p-1">
            <button
              v-for="fo in pickerChildren" :key="fo.id" type="button"
              class="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
              @click="pickerCwd = fo.id"
            >
              <Icon name="folder" :size="18" class="text-primary-500" />
              <span class="min-w-0 flex-1 truncate">{{ fo.name }}</span>
              <Icon name="chevron_right" :size="18" class="text-[var(--ll-muted)]" />
            </button>
            <div v-if="!pickerChildren.length" class="px-3 py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('files.ul_empty_folder') }}</div>
          </div>
          <!-- New subfolder here -->
          <div class="flex items-center gap-2 border-t border-[var(--ll-border)] px-3 py-2">
            <template v-if="ulShowNew">
              <TextField v-model="ulNewFolder" class="flex-1" :placeholder="t('files.folder_name_ph')" @keydown.enter.prevent="pickerCreateFolder" />
              <Btn variant="solid" size="sm" :loading="ulBusy" :disabled="!ulNewFolder.trim()" @click="pickerCreateFolder">{{ t('common.add') }}</Btn>
              <Btn variant="ghost" size="sm" @click="ulShowNew = false">{{ t('common.cancel') }}</Btn>
            </template>
            <Btn v-else variant="ghost" size="sm" icon="create_new_folder" @click="ulShowNew = true">{{ t('files.new_folder') }}</Btn>
          </div>
        </div>
        <p class="mt-1 text-xs" :class="pickerCwd == null ? 'text-amber-600 dark:text-amber-400' : 'text-[var(--ll-muted)]'">
          {{ pickerCwd == null ? t('files.ul_pick_folder') : t('files.ul_target_is', { path: folderPath(pickerCwd) }) }}
        </p>
      </div>

      <TextField v-model="ulForm.label" :label="t('files.ul_label_prompt')" />
      <div>
        <label class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('files.ul_expiry') }}</label>
        <Select v-model.number="ulForm.days" :options="expiryOptions" />
      </div>
      <TextField v-model="ulForm.password" :label="t('files.ul_password')" type="password" autocomplete="new-password" :hint="t('files.ul_password_hint')" />
    </div>
    <template #footer>
      <Btn variant="ghost" @click="ulDlg = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="ulBusy" :disabled="pickerCwd == null" @click="submitUploadLink">{{ t('files.ul_create') }}</Btn>
    </template>
  </Modal>

  <!-- File preview: preview pane + always-visible info sidebar -->
  <Modal v-model="previewOpen" :title="preview?.name" width="64rem">
    <div v-if="preview" class="flex flex-col md:h-[calc(70vh-2.5rem)] md:flex-row">
      <!-- Preview pane -->
      <div class="flex min-h-[45vh] flex-1 items-center justify-center overflow-auto rounded-lg bg-black/[0.03] dark:bg-white/5 md:min-h-0">
        <StlViewer v-if="previewKind(preview) === 'stl'" :src="s.rawUrl(preview)" class="h-full w-full" />
        <img v-else-if="previewKind(preview) === 'image'" :src="s.rawUrl(preview)" class="max-h-full max-w-full object-contain" >
        <iframe v-else-if="previewKind(preview) === 'pdf'" :src="s.rawUrl(preview)" class="h-full w-full border-0"></iframe>
        <video v-else-if="previewKind(preview) === 'video'" :src="s.rawUrl(preview)" controls class="max-h-full max-w-full"></video>
        <audio v-else-if="previewKind(preview) === 'audio'" :src="s.rawUrl(preview)" controls></audio>
        <iframe v-else-if="previewKind(preview) === 'text'" :src="s.rawUrl(preview)" class="h-full w-full border-0 bg-white"></iframe>
        <div v-else class="p-10 text-center text-[var(--ll-muted)]">
          <Icon :name="categoryMsym(preview.name, preview.mime)" :size="56" class="mb-3 block" />
          <div class="text-sm">{{ preview.name }}</div>
          <Btn variant="soft" icon="download" tag="a" :href="s.rawUrl(preview)" class="mt-4">{{ t('files.download') }}</Btn>
        </div>
      </div>

      <!-- Info sidebar (always visible on md+, stacks below on mobile) -->
      <aside class="mt-4 shrink-0 md:mt-0 md:ml-4 md:w-72 md:overflow-y-auto md:border-l md:border-[var(--ll-border)] md:pl-4">
        <div class="flex items-center gap-1.5">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary-500/15 text-primary-600 dark:text-primary-300">
            <Icon :name="categoryMsym(preview.name, preview.mime)" :size="20" />
          </span>
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-medium" :title="preview.name">{{ preview.name }}</div>
            <div class="truncate text-xs text-[var(--ll-muted)]">{{ preview.mime || '—' }}</div>
          </div>
          <!-- Favorite toggle -->
          <button
            type="button"
            class="grid h-8 w-8 shrink-0 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10"
            :class="preview.favorite ? 'text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'"
            :title="t('files.favorite')"
            @click="previewFav()"
          >
            <Icon name="star" :size="18" :fill="preview.favorite" />
          </button>
          <!-- Actions menu -->
          <DropdownMenuRoot>
            <DropdownMenuTrigger class="grid h-8 w-8 shrink-0 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10">
              <Icon name="more_vert" :size="18" />
            </DropdownMenuTrigger>
            <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] min-w-48 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
              <DropdownMenuItem as="a" :href="s.rawUrl(preview)" :class="menuItemCls"><Icon name="download" :size="18" />{{ t('files.download') }}</DropdownMenuItem>
              <DropdownMenuItem as="a" :href="s.rawUrl(preview)" target="_blank" :class="menuItemCls"><Icon name="open_in_new" :size="18" />{{ t('common.open') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItemCls" @select="previewRename()"><Icon name="drive_file_rename_outline" :size="18" />{{ t('files.rename') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItemCls" @select="openVersions(mapFile(preview))"><Icon name="history" :size="18" />{{ t('files.versions') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItemCls" @select="openShare(mapFile(preview))"><Icon name="share" :size="18" />{{ t('files.share') }}</DropdownMenuItem>
              <DropdownMenuItem :class="menuItemDangerCls" @select="previewTrash()"><Icon name="delete" :size="18" />{{ t('files.trash') }}</DropdownMenuItem>
            </DropdownMenuContent></DropdownMenuPortal>
          </DropdownMenuRoot>
        </div>

        <dl class="mt-4 space-y-3 text-sm">
          <div>
            <dt class="text-xs text-[var(--ll-muted)]">{{ t('files.info_size') }}</dt>
            <dd class="ll-mono">{{ fmt(preview.size) }}</dd>
          </div>
          <div>
            <dt class="text-xs text-[var(--ll-muted)]">{{ t('files.info_modified') }}</dt>
            <dd>{{ preview.updated_at ? fmtDate(preview.updated_at) : '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-[var(--ll-muted)]">{{ t('files.folder') }}</dt>
            <dd class="break-words">{{ folderPath(preview.file_folder_id) }}</dd>
          </div>

          <!-- Tags (editable: removable chips + add input) -->
          <div>
            <dt class="mb-1 text-xs text-[var(--ll-muted)]">{{ t('files.info_tags') }}</dt>
            <dd>
              <div v-if="preview.tags?.length" class="mb-1.5 flex flex-wrap gap-1">
                <Badge v-for="tg in preview.tags" :key="tg" tone="gray">
                  {{ tg }}
                  <button type="button" class="grid place-items-center rounded-full text-[var(--ll-muted)] hover:text-red-600" :title="t('common.delete')" @click="removeTag(tg)"><Icon name="close" :size="14" /></button>
                </Badge>
              </div>
              <TextField v-model="tagInput" :placeholder="t('files.tags_placeholder')" @enter="addTag" />
            </dd>
          </div>

          <!-- Labels (toggle on/off from available set) -->
          <div v-if="s.labels.length">
            <dt class="mb-1 text-xs text-[var(--ll-muted)]">{{ t('files.info_labels') }}</dt>
            <dd class="flex flex-wrap gap-1.5">
              <button
                v-for="l in (s.labels as FileLabel[])" :key="l.id" type="button"
                class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium transition-colors"
                :style="previewHasLabel(l.id)
                  ? { background: l.color, color: '#fff' }
                  : { background: `color-mix(in srgb, ${l.color} 15%, transparent)`, color: l.color }"
                @click="toggleLabel(l.id)"
              >
                <Icon name="label" :size="13" />{{ l.name }}
              </button>
            </dd>
          </div>

          <div v-if="preview.note">
            <dt class="text-xs text-[var(--ll-muted)]">{{ t('files.note') }}</dt>
            <dd class="whitespace-pre-wrap break-words">{{ preview.note }}</dd>
          </div>
        </dl>
      </aside>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { fmtDateTime } from '@spa/lib/datetime';
import { trans as t } from 'laravel-vue-i18n';
import { DropdownMenuRoot, DropdownMenuTrigger, DropdownMenuPortal, DropdownMenuContent, DropdownMenuItem } from 'reka-ui';
import { Icon, Btn, Card, TextField, Badge, Modal, Select } from '@spa/ui';
import StlViewer from '@spa/components/StlViewer.vue';
import { useFilesStore, type FileEntry, type FileFolder, type FileLabel, type FileVersion, type FileShare, type FileStats, type FolderShare, type FolderShareMember, type UploadLink, type FileActivity, type FileInfo } from '@spa/stores/files';
import { ApiError } from '@spa/api/client';
import { useMountsStore, type Mount, type MountEntry } from '@spa/stores/mounts';
import { categoryMsym, categoryTint, formatBytes, isImage, FOLDER_TINT } from '@spa/lib/file-categories';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';

interface Row { _k: string; _folder: boolean; _icon: string; _tint: string; _img: boolean; _labels: FileLabel[]; id: number; name: string; raw: FileEntry | FileFolder }

const s = useFilesStore();
const { success, error } = useToast();
const view = ref<'files' | 'favorites' | 'shared' | 'trash' | 'mount'>('files');
const layout = ref<'grid' | 'list'>('list');
const cwd = ref<number | null>(null);
const query = ref('');
const searching = ref(false);
const serverResults = ref<FileEntry[] | null>(null);
const activeLabels = ref<number[]>([]);
const selected = ref<number[]>([]);
const uploadInput = ref<HTMLInputElement | null>(null);
const uploadDirInput = ref<HTMLInputElement | null>(null);
const moveDlg = ref<{ show: boolean; row: Row | null }>({ show: false, row: null });
const trashFiles = ref<FileEntry[]>([]);
const trashFolders = ref<FileFolder[]>([]);
const preview = ref<FileEntry | null>(null);
const previewOpen = ref(false);
const tagInput = ref('');

// Presentational-only constants for the re-skinned nav + dropdown-menu items.
const navItems = [
  { v: 'files' as const, icon: 'folder', label: 'files.all_files' },
  { v: 'favorites' as const, icon: 'star', label: 'files.favorites' },
  { v: 'shared' as const, icon: 'share', label: 'files.shared_by_me' },
  { v: 'trash' as const, icon: 'delete', label: 'files.trash' },
];
const menuItemCls = 'flex cursor-pointer items-center gap-2.5 rounded-md px-3 py-1.5 text-sm outline-none hover:bg-black/[0.05] dark:hover:bg-white/10';
const menuItemDangerCls = 'flex cursor-pointer items-center gap-2.5 rounded-md px-3 py-1.5 text-sm text-red-600 outline-none hover:bg-red-500/10';

const info = ref<{ show: boolean; busy: boolean; file: FileEntry | null; name: string; tags: string; note: string; labelIds: number[] }>({ show: false, busy: false, file: null, name: '', tags: '', note: '', labelIds: [] });
const infoDetail = ref<FileInfo | null>(null);
const infoLoading = ref(false);
const versionsDlg = ref<{ show: boolean; loading: boolean; file: FileEntry | null; list: FileVersion[] }>({ show: false, loading: false, file: null, list: [] });
const shareDlg = ref<{
  show: boolean; busy: boolean; kind: 'file' | 'folder'; targetId: number; tab: 'link' | 'users';
  share: FileShare | null; allowDownload: boolean; password: string; expires: string;
  folderShareId: number | null; members: FolderShareMember[]; inviteEmail: string; inviteRole: 'viewer' | 'editor'; inviteBusy: boolean;
}>({ show: false, busy: false, kind: 'file', targetId: 0, tab: 'link', share: null, allowDownload: true, password: '', expires: '', folderShareId: null, members: [], inviteEmail: '', inviteRole: 'viewer', inviteBusy: false });
const shareTabs = [{ v: 'link' as const, label: 'files.share_link_label' }, { v: 'users' as const, label: 'files.folder_share_add' }];
const roleOptions = computed(() => [{ title: t('files.sf_role_viewer'), value: 'viewer' }, { title: t('files.sf_role_editor'), value: 'editor' }]);
const labelsDlg = ref<{ show: boolean; busy: boolean; editing: FileLabel | null; name: string; color: string }>({ show: false, busy: false, editing: null, name: '', color: '#6b7280' });
const storageDlg = ref<{ show: boolean; loading: boolean; data: FileStats | null }>({ show: false, loading: false, data: null });
const activityDlg = ref<{ show: boolean; loading: boolean; rows: FileActivity[] }>({ show: false, loading: false, rows: [] });
async function openActivity() {
  activityDlg.value = { show: true, loading: true, rows: [] };
  try { activityDlg.value.rows = await s.activity(); }
  catch { error(t('common.error')); }
  finally { activityDlg.value.loading = false; }
}
const ACTIVITY_ICON: Record<string, string> = {
  upload: 'upload', external_upload: 'cloud_upload', rename: 'edit', move: 'drive_file_move',
  version: 'history', trash: 'delete', restore: 'restore', delete: 'delete_forever', share: 'share',
};
function activityIcon(action: string): string { return ACTIVITY_ICON[action] ?? 'description'; }
function activityLabel(a: FileActivity): string {
  const name = a.file_name ?? (typeof a.meta?.name === 'string' ? a.meta.name : (typeof a.meta?.to === 'string' ? a.meta.to : t('files.activity_a_file')));
  if (a.action === 'rename' && typeof a.meta?.from === 'string') return t('files.act_rename', { from: a.meta.from as string, to: (a.meta.to as string) ?? name });
  return t(`files.act_${a.action}`, { name }) || `${a.action}: ${name}`;
}
function fmtDate(iso: string): string { return fmtDateTime(iso); }

// Keep the listing fresh without a hard reload: refresh when the tab regains
// focus (owner comes back after an external upload) + a light periodic poll.
async function refreshView() {
  try {
    await s.load();
    if (view.value === 'shared') await loadShared();
    if (view.value === 'trash') { const r = await s.loadTrash(); trashFiles.value = r.files; trashFolders.value = r.folders; }
  } catch { /* transient */ }
}
function onFocus() { if (!document.hidden) void refreshView(); }
let pollTimer: ReturnType<typeof setInterval> | undefined;
const route = useRoute();
// Deep-open from global search (?open=<id>): fetch the file and show its preview.
async function openById(id: number) {
  try { preview.value = await s.getEntry(id); previewOpen.value = true; } catch { error(t('common.error')); }
}
watch(() => route.query.open, (v) => { const id = Number(v); if (id) void openById(id); });

onMounted(() => {
  void s.load();
  void mnt.load();
  const openId = Number(route.query.open);
  if (openId) void openById(openId);
  window.addEventListener('focus', onFocus);
  document.addEventListener('visibilitychange', onFocus);
  pollTimer = setInterval(() => { if (!document.hidden && !uploadState.value.active && !conflict.value.show) void refreshView(); }, 20_000);
});

// ---- External mounts ----
const mnt = useMountsStore();
const activeMount = ref<Mount | null>(null);
const mountPath = ref('');
const mountListing = ref<{ dirs: MountEntry[]; files: MountEntry[] }>({ dirs: [], files: [] });
const mountLoading = ref(false);
const mountRO = ref(false);
const mountBusy = ref(false);
const mountUploadInput = ref<HTMLInputElement | null>(null);
const mountCrumbs = computed(() => {
  const out: { name: string; path: string }[] = [];
  let acc = '';
  for (const seg of mountPath.value.split('/').filter(Boolean)) { acc = acc ? `${acc}/${seg}` : seg; out.push({ name: seg, path: acc }); }
  return out;
});
async function openMount(m: Mount) { activeMount.value = m; view.value = 'mount'; await mountGo(''); }
async function mountGo(path: string) {
  if (!activeMount.value) return;
  mountPath.value = path; mountLoading.value = true;
  try { const r = await mnt.list(activeMount.value.id, path); mountListing.value = { dirs: r.dirs, files: r.files }; mountRO.value = r.read_only; }
  catch { error(t('common.error')); mountListing.value = { dirs: [], files: [] }; }
  finally { mountLoading.value = false; }
}
function pickMountUpload() { mountUploadInput.value?.click(); }
async function onMountUpload(e: Event) {
  const input = e.target as HTMLInputElement; const files = Array.from(input.files ?? []); input.value = '';
  if (!activeMount.value || !files.length) return;
  mountBusy.value = true;
  try { for (const f of files) await mnt.upload(activeMount.value.id, f, mountPath.value); await mountGo(mountPath.value); }
  catch { error(t('common.error')); } finally { mountBusy.value = false; }
}
async function mountMkdir() {
  if (!activeMount.value) return;
  const name = await promptAsk(t('files.new_folder')); if (!name) return;
  try { await mnt.mkdir(activeMount.value.id, mountPath.value, name); await mountGo(mountPath.value); } catch { error(t('common.error')); }
}
async function mountDelete(entry: MountEntry, dir: boolean) {
  if (!activeMount.value) return;
  if (!await confirmAsk(t('files.mount_delete_confirm', { name: entry.name }), { danger: true })) return;
  try { await mnt.deletePath(activeMount.value.id, entry.path, dir); await mountGo(mountPath.value); } catch { error(t('common.error')); }
}

interface MountForm {
  show: boolean; id: number | null; type: 's3' | 'sftp'; name: string;
  region: string; bucket: string; endpoint: string; key: string; secret: string; path_prefix: string; use_path_style: boolean;
  host: string; port: number; username: string; password: string; root: string;
  read_only: boolean; busy: boolean; testing: boolean; err: string;
}
const emptyMountForm = (): MountForm => ({ show: false, id: null, type: 's3', name: '', region: 'us-east-1', bucket: '', endpoint: '', key: '', secret: '', path_prefix: '', use_path_style: true, host: '', port: 22, username: '', password: '', root: '', read_only: false, busy: false, testing: false, err: '' });
const mountForm = ref<MountForm>(emptyMountForm());
function openMountForm(m?: Mount | null) {
  mountForm.value = { ...emptyMountForm(), show: true, id: m?.id ?? null, type: m?.type ?? 's3', name: m?.name ?? '', read_only: m?.read_only ?? false };
}
function mountPayload(): Record<string, unknown> {
  const f = mountForm.value;
  return { name: f.name, type: f.type, read_only: f.read_only,
    region: f.region, bucket: f.bucket, endpoint: f.endpoint, key: f.key, secret: f.secret, path_prefix: f.path_prefix, use_path_style: f.use_path_style,
    host: f.host, port: f.port, username: f.username, password: f.password, root: f.root };
}
async function saveMount() {
  const f = mountForm.value; f.busy = true; f.err = '';
  try {
    if (f.id) await mnt.update(f.id as number, mountPayload()); else await mnt.create(mountPayload());
    await mnt.load(); f.show = false; success(t('common.saved'));
  } catch (e) { f.err = e instanceof ApiError ? ((e.body as { message?: string } | null)?.message ?? t('files.mount_unreachable')) : t('common.error'); }
  finally { f.busy = false; }
}
async function testMount() {
  const f = mountForm.value; f.testing = true; f.err = '';
  try { const r = await mnt.test(mountPayload()); if (r.ok) success(t('files.mount_ok')); else f.err = r.message ?? t('files.mount_unreachable'); }
  catch (e) { f.err = e instanceof ApiError ? ((e.body as { message?: string } | null)?.message ?? t('files.mount_unreachable')) : t('common.error'); }
  finally { f.testing = false; }
}
async function deleteMount() {
  const f = mountForm.value; if (!f.id) return;
  if (!await confirmAsk(t('files.mount_delete_confirm', { name: f.name as string }), { danger: true })) return;
  try {
    await mnt.remove(f.id as number); await mnt.load(); f.show = false;
    if (activeMount.value?.id === f.id) { view.value = 'files'; activeMount.value = null; }
  } catch { error(t('common.error')); }
}
onUnmounted(() => {
  window.removeEventListener('focus', onFocus);
  document.removeEventListener('visibilitychange', onFocus);
  if (pollTimer) clearInterval(pollTimer);
});

const expiryOptions = computed(() => [
  { title: t('files.ul_exp_1d'), value: 1 },
  { title: t('files.ul_exp_7d'), value: 7 },
  { title: t('files.ul_exp_30d'), value: 30 },
  { title: t('files.ul_exp_90d'), value: 90 },
]);

const quotaPct = computed(() => (s.usage?.quota ? Math.min(100, (s.usage.used / s.usage.quota) * 100) : 0));
function fmt(n: number) { return formatBytes(n); }

function mapFile(f: FileEntry): Row { return { _k: `f${f.id}`, _folder: false, _icon: categoryMsym(f.name, f.mime), _tint: categoryTint(f.name, f.mime), _img: isImage(f.name, f.mime), _labels: f.labels ?? [], id: f.id, name: f.name, raw: f }; }
function mapFolder(fo: FileFolder): Row { return { _k: `d${fo.id}`, _folder: true, _icon: 'folder', _tint: FOLDER_TINT, _img: false, _labels: [], id: fo.id, name: fo.name, raw: fo }; }

function labelMatch(f: FileEntry): boolean {
  if (!activeLabels.value.length) return true;
  const ids = (f.labels ?? []).map((l) => l.id);
  return activeLabels.value.some((id) => ids.includes(id));
}

const rows = computed<Row[]>(() => {
  const q = query.value.trim().toLowerCase();
  if (view.value === 'trash') {
    return [...trashFolders.value.map(mapFolder), ...trashFiles.value.map(mapFile)].filter((r) => !q || r.name.toLowerCase().includes(q));
  }
  // Server-side content search drives the listing when a query is present.
  if (q && serverResults.value) {
    return serverResults.value.filter(labelMatch).map(mapFile);
  }
  let files = s.files as FileEntry[];
  let folders: FileFolder[] = [];
  if (view.value === 'favorites') {
    files = files.filter((f) => f.favorite);
  } else {
    folders = (s.folders as FileFolder[]).filter((fo) => fo.parent_id === cwd.value);
    files = files.filter((f) => f.file_folder_id === cwd.value);
  }
  files = files.filter(labelMatch);
  let out = [...folders.map(mapFolder), ...files.map(mapFile)];
  if (q) out = out.filter((r) => r.name.toLowerCase().includes(q));
  return out;
});

// Human-readable folder path for the preview sidebar (root → … → parent).
function folderPath(folderId: number | null): string {
  const stack: string[] = [];
  const guard = new Set<number>();
  let id = folderId;
  while (id != null && !guard.has(id)) {
    guard.add(id);
    const fo = (s.folders as FileFolder[]).find((x) => x.id === id);
    if (!fo) break;
    stack.unshift(fo.name);
    id = fo.parent_id;
  }
  return [t('files.all_files'), ...stack].join(' / ');
}

const crumbs = computed(() => {
  const chain: { title: string; value: number | null }[] = [{ title: t('files.all_files'), value: null }];
  let id = cwd.value;
  const stack: FileFolder[] = [];
  while (id != null) {
    const fo = (s.folders as FileFolder[]).find((x) => x.id === id);
    if (!fo) break;
    stack.unshift(fo); id = fo.parent_id;
  }
  stack.forEach((fo) => chain.push({ title: fo.name, value: fo.id }));
  return chain;
});

// ---- Server content search (debounced) ----
let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(query, (v) => {
  const q = v.trim();
  if (searchTimer) clearTimeout(searchTimer);
  if (view.value === 'trash' || !q) { serverResults.value = null; searching.value = false; return; }
  searching.value = true;
  searchTimer = setTimeout(async () => {
    try { const r = await s.search(q); serverResults.value = r.files; }
    catch { serverResults.value = []; }
    finally { searching.value = false; }
  }, 300);
});

// Clear transient selection when the listing context changes.
watch([view, cwd], () => { selected.value = []; });

async function setView(v: 'files' | 'favorites' | 'shared' | 'trash') {
  view.value = v;
  selected.value = [];
  if (v === 'trash') { const r = await s.loadTrash(); trashFiles.value = r.files; trashFolders.value = r.folders; }
  if (v === 'shared') await loadShared();
}

// ---- Shared by me (public links + cross-user folder shares) ----
const myLinks = ref<FileShare[]>([]);
const myFolderShares = ref<FolderShare[]>([]);
const myUploadLinks = ref<UploadLink[]>([]);
async function loadShared() {
  try {
    [myLinks.value, myFolderShares.value, myUploadLinks.value] = await Promise.all([
      s.loadShares(), s.loadFolderShares().then((r) => r.shares), s.loadUploadLinks(),
    ]);
  } catch { error(t('common.error')); }
}
// Create-link flow: navigate the folder tree like a file browser to pick the
// target folder (the folder you're viewing IS the target), mandatory expiry,
// optional password.
const ulDlg = ref(false);
const ulForm = reactive<{ label: string; days: number; password: string }>({ label: '', days: 7, password: '' });
const ulBusy = ref(false);
const pickerCwd = ref<number | null>(null); // folder currently browsed = upload target
const ulNewFolder = ref('');
const ulShowNew = ref(false);

// Child folders of the browsed folder (a "column" in the file browser).
const pickerChildren = computed(() => (s.folders as FileFolder[]).filter((fo) => fo.parent_id === pickerCwd.value).sort((a, b) => a.name.localeCompare(b.name)));
// Breadcrumb from Root down to the browsed folder.
const pickerCrumbs = computed(() => {
  const map = new Map((s.folders as FileFolder[]).map((fo) => [fo.id, fo]));
  const chain: { id: number | null; name: string }[] = [];
  let id = pickerCwd.value;
  while (id != null) { const fo = map.get(id); if (!fo) break; chain.unshift({ id: fo.id, name: fo.name }); id = fo.parent_id; }
  chain.unshift({ id: null, name: t('files.root') });
  return chain;
});

function createUploadLink() {
  pickerCwd.value = cwd.value;
  ulNewFolder.value = ''; ulShowNew.value = false;
  Object.assign(ulForm, { label: '', days: 7, password: '' });
  ulDlg.value = true;
}
async function pickerCreateFolder() {
  const name = ulNewFolder.value.trim();
  if (!name) return;
  ulBusy.value = true;
  try {
    const id = await s.createFolderId(name, pickerCwd.value);
    await s.load();
    pickerCwd.value = id; // descend into the freshly created folder
    ulNewFolder.value = ''; ulShowNew.value = false;
  } catch { error(t('common.error')); } finally { ulBusy.value = false; }
}
async function submitUploadLink() {
  if (pickerCwd.value == null) { error(t('files.ul_folder_required')); return; }
  ulBusy.value = true;
  try {
    const expires = new Date(Date.now() + ulForm.days * 86_400_000).toISOString();
    await s.createUploadLink({ file_folder_id: pickerCwd.value, label: ulForm.label || undefined, expires_at: expires, password: ulForm.password || undefined });
    ulDlg.value = false;
    await Promise.all([s.load(), loadShared()]);
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { ulBusy.value = false; }
}
async function copyUploadLink(l: UploadLink) {
  try { await navigator.clipboard.writeText(s.uploadLinkUrl(l.token)); success(t('files.share_copied')); } catch { /* ignore */ }
}
async function revokeUploadLink(l: UploadLink) {
  if (!await confirmAsk(t('files.share_revoke_confirm'), { danger: true })) return;
  try { await s.deleteUploadLink(l.id); await loadShared(); success(t('common.saved')); } catch { error(t('common.error')); }
}
// Remaining share time as "noch Xd Yh" (or "abgelaufen"), for links with an expiry.
function expiresLabel(iso: string): string {
  const ms = new Date(iso).getTime() - Date.now();
  if (ms <= 0) return t('files.share_expired');
  const hours = Math.floor(ms / 3_600_000);
  const d = Math.floor(hours / 24);
  const h = hours % 24;
  return t('files.share_expires_in', { d: String(d), h: String(h) });
}
function expiryTone(iso: string): 'warning' | 'gray' {
  return new Date(iso).getTime() - Date.now() <= 24 * 3_600_000 ? 'warning' : 'gray';
}
async function copyLink(sh: FileShare) {
  try { await navigator.clipboard.writeText(s.shareUrl(sh.token)); success(t('files.share_copied')); } catch { /* ignore */ }
}
async function revokeLink(sh: FileShare) {
  if (!await confirmAsk(t('files.share_revoke_confirm'), { danger: true })) return;
  try { await s.deleteShare(sh.id); await loadShared(); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function revokeFolderShare(sh: FolderShare) {
  if (!await confirmAsk(t('files.share_revoke_confirm'), { danger: true })) return;
  try { await s.deleteFolderShare(sh.id); await loadShared(); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function revokeMember(sh: FolderShare, m: FolderShareMember) {
  try { await s.removeShareMember(sh.id, m.user_id); await loadShared(); success(t('common.saved')); } catch { error(t('common.error')); }
}
function open(row: Row) {
  if (row._folder) { view.value = 'files'; cwd.value = row.id; return; }
  preview.value = row.raw as FileEntry;
  previewOpen.value = true;
}
function previewKind(f: FileEntry): 'image' | 'pdf' | 'video' | 'audio' | 'text' | 'stl' | 'other' {
  const m = (f.mime || '').toLowerCase();
  if (/\.stl$/i.test(f.name) || m === 'model/stl' || m === 'application/sla') return 'stl';
  if (isImage(f.name, f.mime)) return 'image';
  if (m === 'application/pdf' || f.name.toLowerCase().endsWith('.pdf')) return 'pdf';
  if (m.startsWith('video/')) return 'video';
  if (m.startsWith('audio/')) return 'audio';
  if (m.startsWith('text/') || /\.(txt|md|csv|log|json|xml|yml|yaml)$/i.test(f.name)) return 'text';
  return 'other';
}
function pickUpload() { uploadInput.value?.click(); }
function pickUploadDir() { uploadDirInput.value?.click(); }
async function onUploadDir(e: Event) {
  const input = e.target as HTMLInputElement;
  const list = input.files;
  if (!list || !list.length) { input.value = ''; return; }
  try {
    // Rebuild the picked directory tree, then place each file. `webkitRelativePath`
    // is "root/sub/file.ext"; folders are created once and memoised by path.
    const dirCache = new Map<string, number | null>();
    dirCache.set('', cwd.value);
    const ensureDir = async (rel: string): Promise<number | null> => {
      if (dirCache.has(rel)) return dirCache.get(rel) ?? null;
      const slash = rel.lastIndexOf('/');
      const parentRel = slash >= 0 ? rel.slice(0, slash) : '';
      const name = slash >= 0 ? rel.slice(slash + 1) : rel;
      const parentId = await ensureDir(parentRel);
      const id = await s.createFolderId(name, parentId);
      dirCache.set(rel, id);
      return id;
    };
    const allFiles = Array.from(list);
    conflictAll.value = false;
    const all: { v: ConflictAction | null } = { v: null };
    // Existing-name map per target folder (reused across files in that folder).
    const dirNames = new Map<number | null, Map<string, FileEntry>>();
    uploadState.value = { active: true, done: 0, total: allFiles.length, name: '', frac: 0 };
    for (const f of allFiles) {
      const rel = (f as File & { webkitRelativePath?: string }).webkitRelativePath ?? '';
      const dir = rel.includes('/') ? rel.slice(0, rel.lastIndexOf('/')) : '';
      uploadState.value.name = f.name;
      uploadState.value.frac = 0;
      const targetId = await ensureDir(dir);
      if (!dirNames.has(targetId)) dirNames.set(targetId, entriesIn(targetId));
      const existing = dirNames.get(targetId) as Map<string, FileEntry>;
      const prog = (fr: number) => { uploadState.value.frac = fr; };
      const action = await decideConflict(f.name, existing, all);
      if (action === 'skip') { uploadState.value.done++; continue; }
      if (action === 'overwrite') {
        await s.replaceContent(existing.get(f.name.toLowerCase()) as FileEntry, f, prog);
      } else if (action === 'copy') {
        const name = uniqueName(f.name, new Set([...existing.keys()]));
        await s.upload(new File([f], name, { type: f.type }), targetId, prog);
        existing.set(name.toLowerCase(), {} as FileEntry);
      } else {
        await s.upload(f, targetId, prog);
        existing.set(f.name.toLowerCase(), {} as FileEntry);
      }
      uploadState.value.frac = 0;
      uploadState.value.done++;
    }
    await s.load();
    success(t('common.saved'));
  } catch { error(t('common.error')); }
  finally { input.value = ''; uploadState.value.active = false; }
}
function openMove(row: Row) { moveDlg.value = { show: true, row }; }
async function doMove(target: number | null) {
  const row = moveDlg.value.row;
  if (!row) return;
  moveDlg.value.show = false;
  try {
    if (row._folder) {
      await s.moveFolder(row.raw as FileFolder, target);
    } else {
      const f = row.raw as FileEntry;
      const existing = entriesIn(target);
      conflictAll.value = false;
      const action = await decideConflict(f.name, existing, { v: null });
      if (action === 'skip') return;
      await placeFile(f, target, false, action, existing);
    }
    await s.load();
    success(t('common.saved'));
  } catch { error(t('common.error')); }
}
const moveTargets = computed(() => {
  const opts: { id: number | null; label: string }[] = [{ id: null, label: t('files.all_files') }];
  const selfId = moveDlg.value.row?._folder ? moveDlg.value.row.id : null;
  for (const fo of s.folders as FileFolder[]) {
    if (fo.id === selfId) continue; // a folder can't move into itself (subtree guarded server-side)
    opts.push({ id: fo.id, label: folderPath(fo.id) });
  }
  return opts;
});
const uploadState = ref<{ active: boolean; done: number; total: number; name: string; frac: number }>(
  { active: false, done: 0, total: 0, name: '', frac: 0 },
);
const uploadPct = computed(() => {
  const u = uploadState.value;
  return u.total ? Math.min(100, Math.round(((u.done + u.frac) / u.total) * 100)) : 0;
});
// Upload-conflict resolution: when a file's name already exists in the target
// folder, ask overwrite / skip / keep-both (with an "apply to all" option).
type ConflictAction = 'overwrite' | 'skip' | 'copy';
const conflict = ref<{ show: boolean; name: string; resolve: ((a: ConflictAction | 'all-overwrite' | 'all-skip' | 'all-copy') => void) | null }>(
  { show: false, name: '', resolve: null },
);
function askConflict(name: string): Promise<ConflictAction | 'all-overwrite' | 'all-skip' | 'all-copy'> {
  return new Promise((resolve) => { conflict.value = { show: true, name, resolve }; });
}
function resolveConflict(action: ConflictAction) {
  const r = conflict.value.resolve;
  conflict.value.show = false;
  conflict.value.resolve = null;
  r?.(conflictAll.value ? (`all-${action}` as const) : action);
}
const conflictAll = ref(false);
// "photo.png" → "photo (2).png", bumping until the name is free in the folder.
function uniqueName(name: string, taken: Set<string>): string {
  const dot = name.lastIndexOf('.');
  const base = dot > 0 ? name.slice(0, dot) : name;
  const ext = dot > 0 ? name.slice(dot) : '';
  let i = 2;
  let candidate = `${base} (${i})${ext}`;
  while (taken.has(candidate.toLowerCase())) { i++; candidate = `${base} (${i})${ext}`; }
  return candidate;
}
// Lowercased file names currently in a folder (id or null=root), + the entry
// (so callers can overwrite/trash the colliding target).
function entriesIn(folderId: number | null): Map<string, FileEntry> {
  const m = new Map<string, FileEntry>();
  for (const fe of s.files as FileEntry[]) if (fe.file_folder_id === folderId) m.set(fe.name.toLowerCase(), fe);
  return m;
}
// Decide what to do for a colliding name; null = no conflict (proceed as-is).
// `all` carries an "apply to all" choice across the batch.
async function decideConflict(name: string, existing: Map<string, FileEntry>, all: { v: ConflictAction | null }): Promise<ConflictAction | null> {
  if (!existing.has(name.toLowerCase())) return null;
  if (all.v) return all.v;
  const choice = await askConflict(name);
  if (choice.startsWith('all-')) { all.v = choice.slice(4) as ConflictAction; return all.v; }
  return choice as ConflictAction;
}

async function uploadList(list: FileList | File[]) {
  const files = Array.from(list);
  if (!files.length) return;
  const existing = entriesIn(cwd.value);
  conflictAll.value = false;
  const all: { v: ConflictAction | null } = { v: null };

  uploadState.value = { active: true, done: 0, total: files.length, name: '', frac: 0 };
  try {
    for (const f of files) {
      uploadState.value.name = f.name;
      uploadState.value.frac = 0;
      const prog = (fr: number) => { uploadState.value.frac = fr; };
      const action = await decideConflict(f.name, existing, all);
      if (action === 'skip') { uploadState.value.done++; continue; }
      if (action === 'overwrite') {
        await s.replaceContent(existing.get(f.name.toLowerCase()) as FileEntry, f, prog);
      } else if (action === 'copy') {
        const name = uniqueName(f.name, new Set([...existing.keys()]));
        await s.upload(new File([f], name, { type: f.type }), cwd.value, prog);
        existing.set(name.toLowerCase(), {} as FileEntry);
      } else {
        await s.upload(f, cwd.value, prog);
        existing.set(f.name.toLowerCase(), {} as FileEntry);
      }
      uploadState.value.frac = 0;
      uploadState.value.done++;
    }
    await s.load();
    success(t('common.saved'));
  } catch { error(t('common.error')); }
  finally { uploadState.value.active = false; conflict.value.show = false; }
}
async function onUpload(e: Event) {
  const list = (e.target as HTMLInputElement).files;
  if (list) await uploadList(list);
}

// Full-view drag & drop. A depth counter avoids flicker as the drag crosses
// child elements (dragleave fires on every child boundary).
const dragDepth = ref(0);
function hasFiles(e: DragEvent) { return Array.from(e.dataTransfer?.types ?? []).includes('Files'); }
function onDragEnter(e: DragEvent) { if (hasFiles(e)) dragDepth.value++; }
function onDragLeave(e: DragEvent) { if (hasFiles(e)) dragDepth.value = Math.max(0, dragDepth.value - 1); }
async function onViewDrop(e: DragEvent) {
  dragDepth.value = 0;
  const list = e.dataTransfer?.files;
  if (list && list.length) await uploadList(list);
}
async function newFolder() {
  const name = await promptAsk(t('files.new_folder'));
  if (name) { await s.createFolder(name, cwd.value); }
}
async function fav(f: FileEntry) { await s.toggleFav(f); await s.load(); }
async function doRename(row: Row) {
  const name = await promptAsk(t('files.rename'), { value: row.name });
  if (!name) return;
  if (row._folder) await s.renameFolder(row.raw as FileFolder, name); else await s.rename(row.raw as FileEntry, name);
  await s.load();
}
async function doTrash(row: Row) {
  if (row._folder) await s.trashFolder(row.raw as FileFolder); else await s.trashFile(row.raw as FileEntry);
  await s.load();
}
async function doRestore(row: Row) { if (row._folder) await s.restoreFolder(row.id); else await s.restoreFile(row.id); await setView('trash'); await s.load(); }
async function doForce(row: Row) { if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return; if (row._folder) await s.forceFolder(row.id); else await s.forceFile(row.id); await setView('trash'); }
async function emptyTrash() { if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return; await s.emptyTrash(); await setView('trash'); await s.load(); }

// ---- Label filter ----
function toggleLabelFilter(id: number) {
  const i = activeLabels.value.indexOf(id);
  if (i >= 0) activeLabels.value.splice(i, 1); else activeLabels.value.push(id);
}

// ---- Multi-select ----
function toggleSelect(id: number) {
  const i = selected.value.indexOf(id);
  if (i >= 0) selected.value.splice(i, 1); else selected.value.push(id);
}
// Checkboxes only appear on files (not folders), so the selection is file ids.
const selectedFiles = computed(() => (s.files as FileEntry[]).filter((f) => selected.value.includes(f.id)));

async function bulkTrash() {
  if (!selectedFiles.value.length) return;
  if (!await confirmAsk(t('files.bulk_trash_confirm', { n: String(selectedFiles.value.length) }), { danger: true })) return;
  try { for (const f of selectedFiles.value) await s.trashFile(f); selected.value = []; await s.load(); success(t('common.saved')); }
  catch { error(t('common.error')); }
}

const bulkDlg = ref<{ show: boolean; mode: 'move' | 'copy' }>({ show: false, mode: 'move' });
function openBulk(mode: 'move' | 'copy') { bulkDlg.value = { show: true, mode }; }
const bulkTargets = computed(() => {
  const opts: { id: number | null; label: string }[] = [{ id: null, label: t('files.all_files') }];
  for (const fo of s.folders as FileFolder[]) opts.push({ id: fo.id, label: folderPath(fo.id) });
  return opts;
});
async function doBulk(target: number | null) {
  const files = selectedFiles.value;
  if (!files.length) return;
  const copy = bulkDlg.value.mode === 'copy';
  bulkDlg.value.show = false;
  const existing = entriesIn(target);
  conflictAll.value = false;
  const all: { v: ConflictAction | null } = { v: null };
  try {
    for (const f of files) {
      if (f.file_folder_id === target && !copy) continue; // already there (move no-op)
      const action = await decideConflict(f.name, existing, all);
      if (action === 'skip') continue;
      await placeFile(f, target, copy, action, existing);
    }
    selected.value = [];
    await s.load();
    success(t('common.saved'));
  } catch { error(t('common.error')); }
}
// Move/copy one file into `target`, honouring an optional conflict action.
async function placeFile(f: FileEntry, target: number | null, copy: boolean, action: ConflictAction | null, existing: Map<string, FileEntry>) {
  const key = f.name.toLowerCase();
  if (action === 'overwrite') {
    const victim = existing.get(key);
    if (victim && victim.id !== f.id) await s.trashFile(victim);
    existing.delete(key);
    if (copy) {
      const r = await s.copy(f, target); // lands as "name (copy)" → rename back to name
      await s.rename(r.file, f.name);
    } else {
      await s.move(f, target);
    }
    existing.set(key, {} as FileEntry);
  } else if (action === 'copy') {
    // keep both
    if (copy) { await s.copy(f, target); } // backend appends " (copy)"
    else { const name = uniqueName(f.name, new Set([...existing.keys()])); const r = await s.rename(f, name); await s.move(r.file, target); existing.set(name.toLowerCase(), {} as FileEntry); }
  } else {
    // no conflict
    if (copy) await s.copy(f, target); else await s.move(f, target);
    existing.set(key, {} as FileEntry);
  }
}

// ---- ZIP ----
async function zipFolder() { try { await s.zip({ folder_id: cwd.value }); } catch { error(t('common.error')); } }
async function zipSelected() {
  if (!selected.value.length) return;
  try { await s.zip({ ids: [...selected.value] }); } catch { error(t('common.error')); }
}

// ---- Archive (create) / extract ----
const archiveDlg = ref<{ show: boolean; busy: boolean; format: string; level: number; name: string; password: string }>({ show: false, busy: false, format: 'zip', level: 6, name: '', password: '' });
function openArchive() {
  if (!selected.value.length) return;
  archiveDlg.value = { show: true, busy: false, format: 'zip', level: 6, name: '', password: '' };
}
async function doArchive() {
  if (!selected.value.length) return;
  const d = archiveDlg.value;
  d.busy = true;
  try {
    const hasPw = (d.format === 'zip' || d.format === '7z') && d.password !== '';
    await s.createArchive({ ids: [...selected.value], target_folder_id: cwd.value, format: d.format, level: d.level, name: d.name || undefined, password: hasPw ? d.password : undefined });
    d.show = false; selected.value = []; await s.load(); success(t('files.archive_created'));
  } catch { error(t('files.archive_failed')); } finally { d.busy = false; }
}
async function doExtract(row: { id: number; name: string }) {
  const pw = await promptAsk(t('files.archive_extract_password'));
  if (pw === null) return; // cancelled
  try {
    await s.extractArchive(row.id, { password: pw || undefined, target_folder_id: cwd.value });
    success(t('files.archive_extracting', { name: String(row.name) }));
    // The worker fills the new folder; refresh a couple of times so it shows up.
    setTimeout(() => void s.load(), 1500);
    setTimeout(() => void s.load(), 5000);
  } catch { error(t('files.archive_failed')); }
}

// ---- Info / tags / note ----
async function openInfo(row: Row) {
  const f = row.raw as FileEntry;
  info.value = { show: true, busy: false, file: f, name: f.name, tags: (f.tags ?? []).join(', '), note: f.note ?? '', labelIds: (f.labels ?? []).map((l) => l.id) };
  infoDetail.value = null;
  infoLoading.value = true;
  try { infoDetail.value = await s.fileInfo(f.id); } catch { /* best-effort */ } finally { infoLoading.value = false; }
}
// Localised label for a metadata group / activity action, falling back to the raw
// key value when no translation exists (t returns the key itself on a miss).
function metaKindLabel(kind: string): string { const k = 'files.meta_kind_' + kind; const s = t(k); return s === k ? kind : s; }
function actLabel(action: string): string { const k = 'files.act_' + action; const s = t(k); return s === k ? action : s; }
async function saveInfo() {
  const f = info.value.file;
  if (!f) return;
  info.value.busy = true;
  try {
    const tags = info.value.tags.split(',').map((x) => x.trim()).filter(Boolean);
    await s.setFileLabels(f, info.value.labelIds);
    await s.updateEntry(f, { name: info.value.name, tags, note: info.value.note || null });
    await s.load();
    info.value.show = false;
    success(t('common.saved'));
  } catch { error(t('common.error')); }
  finally { info.value.busy = false; }
}

// ---- Versions ----
async function openVersions(row: Row) {
  const f = row.raw as FileEntry;
  versionsDlg.value = { show: true, loading: true, file: f, list: [] };
  try { const r = await s.versions(f); versionsDlg.value.list = r.versions; }
  catch { error(t('common.error')); }
  finally { versionsDlg.value.loading = false; }
}
function downloadVersion(version: number) {
  const f = versionsDlg.value.file;
  if (f) window.open(s.versionRawUrl(f, version), '_blank');
}
async function restoreVersion(version: number) {
  const f = versionsDlg.value.file;
  if (!f) return;
  if (!await confirmAsk(t('files.version_restore_confirm'), { danger: true })) return;
  try {
    await s.restoreVersion(f, version);
    await s.load();
    versionsDlg.value.show = false;
    success(t('common.saved'));
  } catch { error(t('common.error')); }
}

// ---- Share ----
async function openShare(row: Row) {
  shareDlg.value = {
    show: true, busy: false, kind: row._folder ? 'folder' : 'file', targetId: row.id, tab: 'link',
    share: null, allowDownload: true, password: '', expires: '',
    folderShareId: null, members: [], inviteEmail: '', inviteRole: 'viewer', inviteBusy: false,
  };
  await loadShareMembers();
}
// Load the existing cross-user share (folder OR file) for the current target so
// its member roster shows when the dialog opens.
async function loadShareMembers() {
  try {
    const r = await s.loadFolderShares();
    const sh = shareDlg.value.kind === 'file'
      ? r.shares.find((x) => x.kind === 'file' && x.file_id === shareDlg.value.targetId)
      : r.shares.find((x) => x.kind === 'folder' && x.file_folder_id === shareDlg.value.targetId);
    shareDlg.value.folderShareId = sh?.id ?? null;
    shareDlg.value.members = sh?.members ?? [];
  } catch { /* non-fatal: the member list stays empty */ }
}
async function createShareLink() {
  if (!shareDlg.value.targetId) return;
  shareDlg.value.busy = true;
  try {
    const payload = {
      allow_download: shareDlg.value.allowDownload,
      password: shareDlg.value.password || undefined,
      expires_at: shareDlg.value.expires || null,
    };
    const r = shareDlg.value.kind === 'folder'
      ? await s.createFolderShareLink(shareDlg.value.targetId, payload)
      : await s.createShare(shareDlg.value.targetId, payload);
    shareDlg.value.share = r.share;
    success(t('common.saved'));
  } catch { error(t('common.error')); }
  finally { shareDlg.value.busy = false; }
}
// ---- Share with a registered user (folder only) ----
async function inviteUser() {
  const email = shareDlg.value.inviteEmail.trim();
  if (!email || !shareDlg.value.targetId) return;
  shareDlg.value.inviteBusy = true;
  try {
    const r = await s.shareToUser(shareDlg.value.kind === 'file'
      ? { kind: 'file', file_id: shareDlg.value.targetId, email, role: shareDlg.value.inviteRole }
      : { kind: 'folder', file_folder_id: shareDlg.value.targetId, email, role: shareDlg.value.inviteRole });
    shareDlg.value.folderShareId = r.share.id;
    shareDlg.value.members = r.share.members;
    shareDlg.value.inviteEmail = '';
    success(t('files.folder_shared'));
  } catch (e) {
    error(e instanceof ApiError && e.status === 422 ? t('files.sf_recipient_not_found') : t('common.error'));
  } finally { shareDlg.value.inviteBusy = false; }
}
async function changeMemberRole(m: FolderShareMember, role: 'viewer' | 'editor') {
  if (shareDlg.value.folderShareId == null || m.role === role) return;
  try {
    const r = await s.updateShareMember(shareDlg.value.folderShareId, { user_id: m.user_id, role });
    shareDlg.value.members = r.share.members;
  } catch { error(t('common.error')); }
}
async function removeMember(m: FolderShareMember) {
  if (shareDlg.value.folderShareId == null) return;
  if (!await confirmAsk(t('files.sf_remove_member'), { danger: true })) return;
  try {
    await s.removeShareMember(shareDlg.value.folderShareId, m.user_id);
    shareDlg.value.members = shareDlg.value.members.filter((x) => x.user_id !== m.user_id);
  } catch { error(t('common.error')); }
}
async function updateShareLink() {
  const sh = shareDlg.value.share;
  if (!sh) return;
  shareDlg.value.busy = true;
  try {
    const r = await s.updateShare(sh.id, sh.version, {
      allow_download: shareDlg.value.allowDownload,
      password: shareDlg.value.password || undefined,
      remove_password: !shareDlg.value.password,
      expires_at: shareDlg.value.expires || null,
    });
    shareDlg.value.share = r.share;
    success(t('common.saved'));
  } catch { error(t('common.error')); }
  finally { shareDlg.value.busy = false; }
}
async function revokeShare() {
  const sh = shareDlg.value.share;
  if (!sh) return;
  shareDlg.value.busy = true;
  try { await s.deleteShare(sh.id); shareDlg.value.share = null; success(t('common.saved')); }
  catch { error(t('common.error')); }
  finally { shareDlg.value.busy = false; }
}
async function copyShare() {
  const sh = shareDlg.value.share;
  if (!sh) return;
  try { await navigator.clipboard.writeText(s.shareUrl(sh.token)); success(t('files.share_copied')); }
  catch { error(t('common.error')); }
}

// ---- Labels management ----
function openLabels() { labelsDlg.value = { show: true, busy: false, editing: null, name: '', color: '#6b7280' }; }
function editLabel(l: FileLabel) { labelsDlg.value.editing = l; labelsDlg.value.name = l.name; labelsDlg.value.color = l.color; }
async function saveLabel() {
  const name = labelsDlg.value.name.trim();
  if (!name) return;
  labelsDlg.value.busy = true;
  try {
    if (labelsDlg.value.editing) await s.updateLabel(labelsDlg.value.editing.id, name, labelsDlg.value.color);
    else await s.createLabel(name, labelsDlg.value.color);
    await s.load();
    labelsDlg.value.editing = null; labelsDlg.value.name = ''; labelsDlg.value.color = '#6b7280';
  } catch { error(t('common.error')); }
  finally { labelsDlg.value.busy = false; }
}
async function removeLabel(l: FileLabel) {
  if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return;
  try {
    await s.deleteLabel(l.id);
    const i = activeLabels.value.indexOf(l.id);
    if (i >= 0) activeLabels.value.splice(i, 1);
    await s.load();
  } catch { error(t('common.error')); }
}

// ---- Storage ----
async function openStorage() {
  storageDlg.value = { show: true, loading: true, data: null };
  try { storageDlg.value.data = await s.stats(); }
  catch { error(t('common.error')); }
  finally { storageDlg.value.loading = false; }
}

// ---- Preview sidebar metadata (favorite / tags / labels) ----
// Re-point `preview` at the freshly loaded store object so its version + fields
// stay current after each optimistic save+reload (same handling as the rest of the view).
function syncPreview() {
  const cur = preview.value;
  if (!cur) return;
  const fresh = (s.files as FileEntry[]).find((f) => f.id === cur.id);
  if (fresh) preview.value = fresh;
}
async function previewFav() {
  const f = preview.value;
  if (!f) return;
  try { await s.toggleFav(f); await s.load(); syncPreview(); }
  catch { error(t('common.error')); }
}
async function previewRename() {
  const f = preview.value;
  if (!f) return;
  const name = await promptAsk(t('files.rename'), { value: f.name });
  if (!name) return;
  try { await s.rename(f, name); await s.load(); syncPreview(); }
  catch { error(t('common.error')); }
}
async function previewTrash() {
  const f = preview.value;
  if (!f) return;
  try { await s.trashFile(f); previewOpen.value = false; await s.load(); }
  catch { error(t('common.error')); }
}
function previewHasLabel(id: number): boolean {
  return (preview.value?.labels ?? []).some((l) => l.id === id);
}
async function toggleLabel(id: number) {
  const f = preview.value;
  if (!f) return;
  const cur = (f.labels ?? []).map((l) => l.id);
  const next = previewHasLabel(id) ? cur.filter((x) => x !== id) : [...cur, id];
  try { await s.setFileLabels(f, next); await s.load(); syncPreview(); }
  catch { error(t('common.error')); }
}
async function saveTags(tags: string[]) {
  const f = preview.value;
  if (!f) return;
  try { await s.updateEntry(f, { tags }); await s.load(); syncPreview(); }
  catch { error(t('common.error')); }
}
async function commitTags(added: string[]) {
  const f = preview.value;
  if (!f || !added.length) return;
  await saveTags(Array.from(new Set([...(f.tags ?? []), ...added])));
}
async function removeTag(tag: string) {
  const f = preview.value;
  if (!f) return;
  await saveTags((f.tags ?? []).filter((x) => x !== tag));
}
// Enter commits the whole input; a typed comma commits the completed token(s).
async function addTag() {
  const parts = tagInput.value.split(',').map((x) => x.trim()).filter(Boolean);
  tagInput.value = '';
  await commitTags(parts);
}
watch(tagInput, (v) => {
  if (!v.includes(',')) return;
  const parts = v.split(',');
  tagInput.value = parts.pop() ?? '';
  void commitTags(parts.map((x) => x.trim()).filter(Boolean));
});
</script>
