<template>
  <div class="flex flex-col gap-4 md:flex-row" style="min-height:calc(100vh - 120px)">
    <!-- Sidebar -->
    <Card :body-class="'p-0'" class="w-full shrink-0 self-start md:w-60">
      <div class="p-3">
        <Btn variant="solid" block icon="upload" @click="pickUpload">{{ t('files.upload') }}</Btn>
        <input ref="uploadInput" type="file" multiple class="hidden" @change="onUpload" >
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
        <h2 v-else class="text-sm font-semibold">{{ view === 'favorites' ? t('files.favorites') : t('files.trash') }}</h2>

        <div class="ml-auto flex items-center gap-1">
          <Btn v-if="view==='files'" variant="ghost" size="sm" icon="create_new_folder" @click="newFolder">{{ t('files.new_folder') }}</Btn>
          <Btn v-if="view==='files' && cwd!==null" variant="ghost" size="sm" icon="folder_zip" @click="zipFolder">{{ t('files.download_zip') }}</Btn>
          <Btn variant="ghost" size="sm" icon="storage" @click="openStorage">{{ t('files.storage') }}</Btn>
          <Btn v-if="view==='trash' && trashFiles.length" variant="ghost" size="sm" icon="delete" class="text-red-600" @click="emptyTrash">{{ t('files.empty_trash') }}</Btn>
          <Btn variant="ghost" size="sm" :icon="layout==='grid' ? 'view_list' : 'grid_view'" @click="layout = layout==='grid' ? 'list' : 'grid'" />
        </div>
      </div>
      <div class="border-t border-[var(--ll-border)]" />

      <!-- Search -->
      <div class="flex items-center gap-2 border-b border-[var(--ll-border)] px-4 py-2.5">
        <div class="w-full max-w-xs">
          <TextField v-model="query" :placeholder="searching ? t('files.searching') : t('files.search')" icon="search" />
        </div>
        <Icon v-if="searching" name="progress_activity" :size="18" class="animate-spin text-[var(--ll-muted)]" />
      </div>

      <!-- Label filter bar -->
      <div v-if="view!=='trash' && s.labels.length" class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] px-4 py-2.5">
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
      <div v-if="selected.length" class="flex items-center gap-2 border-b border-[var(--ll-border)] bg-primary-500/5 px-4 py-2.5">
        <span class="text-xs font-medium">{{ selected.length }} {{ t('files.selected_word') }}</span>
        <div class="ml-auto flex items-center gap-1">
          <Btn variant="ghost" size="sm" icon="folder_zip" @click="zipSelected">{{ t('files.download_zip') }}</Btn>
          <Btn variant="ghost" size="sm" @click="selected = []">{{ t('common.close') }}</Btn>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto p-2">
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
                <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-50 min-w-48 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
                  <template v-if="!row._folder && view!=='trash'">
                    <DropdownMenuItem as="a" :href="s.rawUrl(row.raw as FileEntry)" :class="menuItemCls"><Icon name="download" :size="18" />{{ t('files.download') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="fav(row.raw as FileEntry)"><Icon name="star" :size="18" />{{ t('files.favorite') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="doRename(row)"><Icon name="drive_file_rename_outline" :size="18" />{{ t('files.rename') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openInfo(row)"><Icon name="info" :size="18" />{{ t('files.info') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openVersions(row)"><Icon name="history" :size="18" />{{ t('files.versions') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemCls" @select="openShare(row)"><Icon name="share" :size="18" />{{ t('files.share') }}</DropdownMenuItem>
                    <DropdownMenuItem :class="menuItemDangerCls" @select="doTrash(row)"><Icon name="delete" :size="18" />{{ t('files.trash') }}</DropdownMenuItem>
                  </template>
                  <template v-else-if="row._folder && view!=='trash'">
                    <DropdownMenuItem :class="menuItemCls" @select="doRename(row)"><Icon name="drive_file_rename_outline" :size="18" />{{ t('files.rename') }}</DropdownMenuItem>
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
                    <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-50 min-w-48 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
                      <template v-if="!row._folder && view!=='trash'">
                        <DropdownMenuItem as="a" :href="s.rawUrl(row.raw as FileEntry)" :class="menuItemCls"><Icon name="download" :size="18" />{{ t('files.download') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemCls" @select="fav(row.raw as FileEntry)"><Icon name="star" :size="18" />{{ t('files.favorite') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemCls" @select="doRename(row)"><Icon name="drive_file_rename_outline" :size="18" />{{ t('files.rename') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemCls" @select="openInfo(row)"><Icon name="info" :size="18" />{{ t('files.info') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemCls" @select="openVersions(row)"><Icon name="history" :size="18" />{{ t('files.versions') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemCls" @select="openShare(row)"><Icon name="share" :size="18" />{{ t('files.share') }}</DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemDangerCls" @select="doTrash(row)"><Icon name="delete" :size="18" />{{ t('files.trash') }}</DropdownMenuItem>
                      </template>
                      <template v-else-if="row._folder && view!=='trash'">
                        <DropdownMenuItem :class="menuItemCls" @select="doRename(row)"><Icon name="drive_file_rename_outline" :size="18" />{{ t('files.rename') }}</DropdownMenuItem>
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
  <Modal v-model="info.show" :title="t('files.info_title')" width="480px">
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
          <div class="text-xs text-[var(--ll-muted)]">{{ v.created_at ? new Date(v.created_at).toLocaleString() : '—' }}</div>
        </div>
        <Btn variant="ghost" size="sm" icon="download" :title="t('files.version_download')" @click="downloadVersion(v.id)" />
        <Btn variant="ghost" size="sm" icon="restore" :title="t('files.version_restore')" @click="restoreVersion(v.id)" />
      </div>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="versionsDlg.show=false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>

  <!-- Share dialog -->
  <Modal v-model="shareDlg.show" :title="t('files.share_dialog_title')" width="520px">
    <div class="flex flex-col gap-3">
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
    </div>
    <template #footer>
      <Btn v-if="shareDlg.share" variant="danger" icon="delete" :loading="shareDlg.busy" class="mr-auto" @click="revokeShare">{{ t('files.share_revoke') }}</Btn>
      <Btn variant="ghost" @click="shareDlg.show=false">{{ t('common.close') }}</Btn>
      <Btn v-if="!shareDlg.share" variant="solid" :loading="shareDlg.busy" @click="createShareLink">{{ t('files.share_create_link') }}</Btn>
      <Btn v-else variant="solid" :loading="shareDlg.busy" @click="updateShareLink">{{ t('files.share_update') }}</Btn>
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

  <!-- File preview: preview pane + always-visible info sidebar -->
  <Modal v-model="previewOpen" :title="preview?.name" width="64rem">
    <div v-if="preview" class="flex flex-col md:h-[calc(70vh-2.5rem)] md:flex-row">
      <!-- Preview pane -->
      <div class="flex min-h-[45vh] flex-1 items-center justify-center overflow-auto rounded-lg bg-black/[0.03] dark:bg-white/5 md:min-h-0">
        <img v-if="previewKind(preview) === 'image'" :src="s.rawUrl(preview)" class="max-h-full max-w-full object-contain" >
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
            <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-50 min-w-48 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
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
            <dd>{{ preview.updated_at ? new Date(preview.updated_at).toLocaleString() : '—' }}</dd>
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
import { ref, computed, onMounted, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { DropdownMenuRoot, DropdownMenuTrigger, DropdownMenuPortal, DropdownMenuContent, DropdownMenuItem } from 'reka-ui';
import { Icon, Btn, Card, TextField, Badge, Modal } from '@spa/ui';
import { useFilesStore, type FileEntry, type FileFolder, type FileLabel, type FileVersion, type FileShare, type FileStats } from '@spa/stores/files';
import { categoryMsym, categoryTint, formatBytes, isImage, FOLDER_TINT } from '@spa/lib/file-categories';
import { useToast } from '@spa/composables/useToast';

interface Row { _k: string; _folder: boolean; _icon: string; _tint: string; _img: boolean; _labels: FileLabel[]; id: number; name: string; raw: FileEntry | FileFolder }

const s = useFilesStore();
const { success, error } = useToast();
const view = ref<'files' | 'favorites' | 'trash'>('files');
const layout = ref<'grid' | 'list'>('list');
const cwd = ref<number | null>(null);
const query = ref('');
const searching = ref(false);
const serverResults = ref<FileEntry[] | null>(null);
const activeLabels = ref<number[]>([]);
const selected = ref<number[]>([]);
const uploadInput = ref<HTMLInputElement | null>(null);
const trashFiles = ref<FileEntry[]>([]);
const trashFolders = ref<FileFolder[]>([]);
const preview = ref<FileEntry | null>(null);
const previewOpen = ref(false);
const tagInput = ref('');

// Presentational-only constants for the re-skinned nav + dropdown-menu items.
const navItems = [
  { v: 'files' as const, icon: 'folder', label: 'files.all_files' },
  { v: 'favorites' as const, icon: 'star', label: 'files.favorites' },
  { v: 'trash' as const, icon: 'delete', label: 'files.trash' },
];
const menuItemCls = 'flex cursor-pointer items-center gap-2.5 rounded-md px-3 py-1.5 text-sm outline-none hover:bg-black/[0.05] dark:hover:bg-white/10';
const menuItemDangerCls = 'flex cursor-pointer items-center gap-2.5 rounded-md px-3 py-1.5 text-sm text-red-600 outline-none hover:bg-red-500/10';

const info = ref<{ show: boolean; busy: boolean; file: FileEntry | null; name: string; tags: string; note: string; labelIds: number[] }>({ show: false, busy: false, file: null, name: '', tags: '', note: '', labelIds: [] });
const versionsDlg = ref<{ show: boolean; loading: boolean; file: FileEntry | null; list: FileVersion[] }>({ show: false, loading: false, file: null, list: [] });
const shareDlg = ref<{ show: boolean; busy: boolean; file: FileEntry | null; share: FileShare | null; allowDownload: boolean; password: string; expires: string }>({ show: false, busy: false, file: null, share: null, allowDownload: true, password: '', expires: '' });
const labelsDlg = ref<{ show: boolean; busy: boolean; editing: FileLabel | null; name: string; color: string }>({ show: false, busy: false, editing: null, name: '', color: '#6b7280' });
const storageDlg = ref<{ show: boolean; loading: boolean; data: FileStats | null }>({ show: false, loading: false, data: null });

onMounted(() => s.load());

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

async function setView(v: 'files' | 'favorites' | 'trash') {
  view.value = v;
  if (v === 'trash') { const r = await s.loadTrash(); trashFiles.value = r.files; trashFolders.value = r.folders; }
}
function open(row: Row) {
  if (row._folder) { view.value = 'files'; cwd.value = row.id; return; }
  preview.value = row.raw as FileEntry;
  previewOpen.value = true;
}
function previewKind(f: FileEntry): 'image' | 'pdf' | 'video' | 'audio' | 'text' | 'other' {
  const m = (f.mime || '').toLowerCase();
  if (isImage(f.name, f.mime)) return 'image';
  if (m === 'application/pdf' || f.name.toLowerCase().endsWith('.pdf')) return 'pdf';
  if (m.startsWith('video/')) return 'video';
  if (m.startsWith('audio/')) return 'audio';
  if (m.startsWith('text/') || /\.(txt|md|csv|log|json|xml|yml|yaml)$/i.test(f.name)) return 'text';
  return 'other';
}
function pickUpload() { uploadInput.value?.click(); }
async function onUpload(e: Event) {
  const list = (e.target as HTMLInputElement).files;
  if (!list) return;
  try { for (const f of Array.from(list)) await s.upload(f, cwd.value); await s.load(); success(t('common.saved')); }
  catch { error(t('common.error')); }
}
async function newFolder() {
  const name = prompt(t('files.new_folder'));
  if (name) { await s.createFolder(name, cwd.value); }
}
async function fav(f: FileEntry) { await s.toggleFav(f); await s.load(); }
async function doRename(row: Row) {
  const name = prompt(t('files.rename'), row.name);
  if (!name) return;
  if (row._folder) await s.renameFolder(row.raw as FileFolder, name); else await s.rename(row.raw as FileEntry, name);
  await s.load();
}
async function doTrash(row: Row) {
  if (row._folder) await s.trashFolder(row.raw as FileFolder); else await s.trashFile(row.raw as FileEntry);
  await s.load();
}
async function doRestore(row: Row) { await s.restoreFile(row.id); await setView('trash'); await s.load(); }
async function doForce(row: Row) { if (!confirm(t('common.confirm_delete'))) return; await s.forceFile(row.id); await setView('trash'); }
async function emptyTrash() { if (!confirm(t('common.confirm_delete'))) return; await s.emptyTrash(); await setView('trash'); await s.load(); }

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

// ---- ZIP ----
async function zipFolder() { try { await s.zip({ folder_id: cwd.value }); } catch { error(t('common.error')); } }
async function zipSelected() {
  if (!selected.value.length) return;
  try { await s.zip({ ids: [...selected.value] }); } catch { error(t('common.error')); }
}

// ---- Info / tags / note ----
function openInfo(row: Row) {
  const f = row.raw as FileEntry;
  info.value = { show: true, busy: false, file: f, name: f.name, tags: (f.tags ?? []).join(', '), note: f.note ?? '', labelIds: (f.labels ?? []).map((l) => l.id) };
}
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
  if (!confirm(t('files.version_restore_confirm'))) return;
  try {
    await s.restoreVersion(f, version);
    await s.load();
    versionsDlg.value.show = false;
    success(t('common.saved'));
  } catch { error(t('common.error')); }
}

// ---- Share ----
function openShare(row: Row) {
  const f = row.raw as FileEntry;
  shareDlg.value = { show: true, busy: false, file: f, share: null, allowDownload: true, password: '', expires: '' };
}
async function createShareLink() {
  const f = shareDlg.value.file;
  if (!f) return;
  shareDlg.value.busy = true;
  try {
    const r = await s.createShare(f.id, {
      allow_download: shareDlg.value.allowDownload,
      password: shareDlg.value.password || undefined,
      expires_at: shareDlg.value.expires || null,
    });
    shareDlg.value.share = r.share;
    success(t('common.saved'));
  } catch { error(t('common.error')); }
  finally { shareDlg.value.busy = false; }
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
  if (!confirm(t('common.confirm_delete'))) return;
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
  const name = prompt(t('files.rename'), f.name);
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
