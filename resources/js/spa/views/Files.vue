<template>
  <div class="d-flex flex-column flex-md-row ga-4" style="min-height:calc(100vh - 120px)">
    <!-- Sidebar -->
    <v-card rounded="xl" border flat width="240" class="flex-shrink-0 d-flex flex-column" style="align-self:flex-start">
      <div class="pa-3">
        <v-btn color="primary" block :prepend-icon="mdiUpload" @click="pickUpload">{{ t('files.upload') }}</v-btn>
        <input ref="uploadInput" type="file" multiple class="d-none" @change="onUpload" >
      </div>
      <v-list density="compact" nav>
        <v-list-item :active="view==='files'" :prepend-icon="mdiFolder" :title="t('files.all_files')" @click="setView('files')" />
        <v-list-item :active="view==='favorites'" :prepend-icon="mdiStar" :title="t('files.favorites')" @click="setView('favorites')" />
        <v-list-item :active="view==='trash'" :prepend-icon="mdiDelete" :title="t('files.trash')" @click="setView('trash')" />
      </v-list>
      <v-divider />
      <div class="pa-3 mt-auto" v-if="s.usage">
        <v-progress-linear v-if="s.usage.quota" :model-value="quotaPct" color="primary" height="6" rounded class="mb-1" />
        <div class="text-caption text-medium-emphasis">{{ fmt(s.usage.used) }}<span v-if="s.usage.quota"> / {{ fmt(s.usage.quota) }}</span></div>
      </div>
    </v-card>

    <!-- Main -->
    <v-card rounded="xl" border flat class="flex-grow-1 d-flex flex-column" style="min-width:0">
      <v-toolbar flat color="surface" density="comfortable">
        <template v-if="view==='files'">
          <v-breadcrumbs :items="crumbs" density="compact" class="pa-0">
            <template #item="{ item }"><a class="cursor-pointer text-primary" @click="cwd = (item as unknown as { value: number | null }).value">{{ item.title }}</a></template>
          </v-breadcrumbs>
        </template>
        <v-toolbar-title v-else>{{ view === 'favorites' ? t('files.favorites') : t('files.trash') }}</v-toolbar-title>
        <v-spacer />
        <v-btn v-if="view==='files'" variant="text" size="small" :prepend-icon="mdiFolderPlus" @click="newFolder">{{ t('files.new_folder') }}</v-btn>
        <v-btn v-if="view==='files' && cwd!==null" variant="text" size="small" @click="zipFolder">
          <span class="msym mr-1" style="font-size:18px">folder_zip</span>{{ t('files.download_zip') }}
        </v-btn>
        <v-btn variant="text" size="small" @click="openStorage">
          <span class="msym mr-1" style="font-size:18px">storage</span>{{ t('files.storage') }}
        </v-btn>
        <v-btn v-if="view==='trash' && trashFiles.length" variant="text" size="small" color="error" @click="emptyTrash">{{ t('files.empty_trash') }}</v-btn>
        <v-btn variant="text" size="small" :icon="layout==='grid' ? mdiViewList : mdiViewGrid" @click="layout = layout==='grid' ? 'list' : 'grid'" />
      </v-toolbar>
      <v-divider />
      <div class="px-4 py-2 border-b">
        <v-text-field v-model="query" :placeholder="searching ? t('files.searching') : t('files.search')" :prepend-inner-icon="mdiMagnify" :loading="searching" variant="solo-filled" flat density="compact" hide-details single-line style="max-width:320px" />
      </div>

      <!-- Label filter bar -->
      <div v-if="view!=='trash' && s.labels.length" class="px-4 py-2 border-b d-flex align-center flex-wrap ga-2">
        <span class="text-caption text-medium-emphasis mr-1">{{ t('files.filtered_by') }}</span>
        <v-chip
          v-for="l in (s.labels as FileLabel[])" :key="l.id" size="small" label
          :variant="activeLabels.includes(l.id) ? 'flat' : 'tonal'"
          :color="l.color"
          @click="toggleLabelFilter(l.id)"
        >
          <span class="msym mr-1" style="font-size:14px">label</span>{{ l.name }}
        </v-chip>
        <v-spacer />
        <v-btn variant="text" size="x-small" @click="openLabels">
          <span class="msym mr-1" style="font-size:16px">sell</span>{{ t('files.labels_manage') }}
        </v-btn>
      </div>

      <!-- Selection bar -->
      <div v-if="selected.length" class="px-4 py-2 border-b d-flex align-center ga-2 bg-surface-variant">
        <span class="text-caption">{{ selected.length }} {{ t('files.selected_word') }}</span>
        <v-spacer />
        <v-btn variant="text" size="small" @click="zipSelected">
          <span class="msym mr-1" style="font-size:18px">folder_zip</span>{{ t('files.download_zip') }}
        </v-btn>
        <v-btn variant="text" size="small" @click="selected = []">{{ t('common.close') }}</v-btn>
      </div>

      <div class="flex-grow-1 overflow-y-auto pa-2">
        <div v-if="!rows.length" class="text-center text-medium-emphasis py-10">{{ view==='trash' ? t('files.trash_empty') : t('files.empty_explorer') }}</div>

        <!-- Grid -->
        <div v-else-if="layout==='grid'" class="d-flex flex-wrap ga-3 pa-2">
          <v-card v-for="row in rows" :key="row._k" width="150" rounded="lg" border flat class="pa-0 overflow-hidden" @dblclick="open(row)">
            <div class="d-flex align-center justify-center" style="height:110px;background:rgb(var(--v-theme-surface-variant))">
              <v-img v-if="row._img" :src="s.thumbUrl(row.raw as FileEntry)" cover height="110" width="150" />
              <span v-else class="msym" style="font-size:40px" :style="{ color: row._tint }">{{ row._icon }}</span>
            </div>
            <div class="pa-2 d-flex align-center ga-1">
              <span class="text-caption text-truncate flex-grow-1" :title="row.name">{{ row.name }}</span>
              <v-btn size="x-small" variant="text" :icon="mdiDotsVertical" @click.stop="menuFor(row, $event)" />
            </div>
          </v-card>
        </div>

        <!-- List -->
        <v-list v-else density="comfortable">
          <v-list-item v-for="row in rows" :key="row._k" @click="open(row)">
            <template #prepend>
              <v-checkbox-btn
                v-if="!row._folder && view!=='trash'"
                :model-value="selected.includes(row.id)"
                density="compact" hide-details class="mr-1"
                @click.stop="toggleSelect(row.id)"
              />
              <v-avatar :color="row._tint" size="36" rounded="lg">
                <span class="msym" style="font-size:20px;color:#fff">{{ row._icon }}</span>
              </v-avatar>
            </template>
            <v-list-item-title>{{ row.name }}</v-list-item-title>
            <v-list-item-subtitle v-if="!row._folder">
              {{ fmt((row.raw as FileEntry).size) }}
              <v-chip
                v-for="l in row._labels" :key="l.id" size="x-small" label class="ml-1"
                variant="tonal" :color="l.color"
              >{{ l.name }}</v-chip>
            </v-list-item-subtitle>
            <template #append>
              <v-btn variant="text" size="small" :icon="mdiDotsVertical" @click.stop="menuFor(row, $event)" />
            </template>
          </v-list-item>
        </v-list>
      </div>
    </v-card>
  </div>

  <!-- Row action menu -->
  <v-menu v-model="menu.show" :target="menu.target" location="bottom end">
    <v-list density="compact">
      <template v-if="menu.row && !menu.row._folder && view!=='trash'">
        <v-list-item :prepend-icon="mdiDownload" :title="t('files.download')" :href="s.rawUrl(menu.row.raw as FileEntry)" />
        <v-list-item :prepend-icon="mdiStar" :title="t('files.favorite')" @click="fav(menu.row.raw as FileEntry)" />
        <v-list-item :prepend-icon="mdiPencil" :title="t('files.rename')" @click="doRename(menu.row)" />
        <v-list-item :title="t('files.info')" @click="openInfo(menu.row)">
          <template #prepend><span class="msym mr-2" style="font-size:20px">info</span></template>
        </v-list-item>
        <v-list-item :title="t('files.versions')" @click="openVersions(menu.row)">
          <template #prepend><span class="msym mr-2" style="font-size:20px">history</span></template>
        </v-list-item>
        <v-list-item :title="t('files.share')" @click="openShare(menu.row)">
          <template #prepend><span class="msym mr-2" style="font-size:20px">share</span></template>
        </v-list-item>
        <v-list-item :prepend-icon="mdiDelete" base-color="error" :title="t('files.trash')" @click="doTrash(menu.row)" />
      </template>
      <template v-else-if="menu.row && menu.row._folder && view!=='trash'">
        <v-list-item :prepend-icon="mdiPencil" :title="t('files.rename')" @click="doRename(menu.row)" />
        <v-list-item :prepend-icon="mdiDelete" base-color="error" :title="t('files.trash')" @click="doTrash(menu.row)" />
      </template>
      <template v-else-if="menu.row">
        <v-list-item :prepend-icon="mdiRestore" :title="t('files.restore')" @click="doRestore(menu.row)" />
        <v-list-item :prepend-icon="mdiDeleteForever" base-color="error" :title="t('common.delete')" @click="doForce(menu.row)" />
      </template>
    </v-list>
  </v-menu>

  <!-- Info dialog -->
  <v-dialog v-model="info.show" max-width="480">
    <v-card rounded="lg" border flat>
      <v-card-title class="d-flex align-center ga-2">
        <span class="msym" style="font-size:22px">info</span>{{ t('files.info_title') }}
      </v-card-title>
      <v-card-text class="d-flex flex-column ga-3">
        <v-text-field v-model="info.name" :label="t('files.info_name')" variant="outlined" density="comfortable" hide-details />
        <div class="d-flex ga-4">
          <div><div class="text-caption text-medium-emphasis">{{ t('files.info_mime') }}</div><div>{{ info.file?.mime || '—' }}</div></div>
          <div><div class="text-caption text-medium-emphasis">{{ t('files.info_size') }}</div><div>{{ info.file ? fmt(info.file.size) : '—' }}</div></div>
        </div>
        <v-text-field v-model="info.tags" :label="t('files.info_tags')" :placeholder="t('files.tags_placeholder')" variant="outlined" density="comfortable" hide-details />
        <v-textarea v-model="info.note" :label="t('files.note')" :placeholder="t('files.note_placeholder')" variant="outlined" density="comfortable" rows="3" hide-details />
        <div v-if="s.labels.length">
          <div class="text-caption text-medium-emphasis mb-1">{{ t('files.info_labels') }}</div>
          <v-chip-group v-model="info.labelIds" multiple column>
            <v-chip v-for="l in (s.labels as FileLabel[])" :key="l.id" :value="l.id" size="small" label filter :color="l.color" variant="tonal">{{ l.name }}</v-chip>
          </v-chip-group>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="info.show=false">{{ t('common.cancel') }}</v-btn>
        <v-btn color="primary" :loading="info.busy" @click="saveInfo">{{ t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Versions dialog -->
  <v-dialog v-model="versionsDlg.show" max-width="480">
    <v-card rounded="lg" border flat>
      <v-card-title class="d-flex align-center ga-2">
        <span class="msym" style="font-size:22px">history</span>{{ t('files.versions') }}
      </v-card-title>
      <v-card-text>
        <div v-if="versionsDlg.loading" class="text-center py-6"><v-progress-circular indeterminate /></div>
        <div v-else-if="!versionsDlg.list.length" class="text-center text-medium-emphasis py-6">{{ t('files.versions_none') }}</div>
        <v-list v-else density="comfortable">
          <v-list-item v-for="v in versionsDlg.list" :key="v.id">
            <v-list-item-title>{{ fmt(v.size) }}</v-list-item-title>
            <v-list-item-subtitle>{{ v.created_at ? new Date(v.created_at).toLocaleString() : '—' }}</v-list-item-subtitle>
            <template #append>
              <v-btn size="small" variant="text" :title="t('files.version_download')" @click="downloadVersion(v.id)">
                <span class="msym" style="font-size:20px">download</span>
              </v-btn>
              <v-btn size="small" variant="text" :title="t('files.version_restore')" @click="restoreVersion(v.id)">
                <span class="msym" style="font-size:20px">restore</span>
              </v-btn>
            </template>
          </v-list-item>
        </v-list>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="versionsDlg.show=false">{{ t('common.close') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Share dialog -->
  <v-dialog v-model="shareDlg.show" max-width="520">
    <v-card rounded="lg" border flat>
      <v-card-title class="d-flex align-center ga-2">
        <span class="msym" style="font-size:22px">share</span>{{ t('files.share_dialog_title') }}
      </v-card-title>
      <v-card-text class="d-flex flex-column ga-3">
        <template v-if="shareDlg.share">
          <v-text-field
            :model-value="s.shareUrl(shareDlg.share.token)" :label="t('files.share_link_label')"
            variant="outlined" density="comfortable" readonly hide-details
            :append-inner-icon="mdiContentCopy" @click:append-inner="copyShare"
          />
          <v-btn variant="tonal" size="small" block @click="copyShare">
            <span class="msym mr-1" style="font-size:18px">link</span>{{ t('files.share_copy') }}
          </v-btn>
        </template>
        <v-switch v-model="shareDlg.allowDownload" :label="t('files.share_allow_download')" color="primary" density="compact" hide-details inset />
        <v-text-field v-model="shareDlg.password" :label="t('files.share_password')" type="password" variant="outlined" density="comfortable" hide-details autocomplete="new-password" />
        <v-text-field v-model="shareDlg.expires" :label="t('files.share_expiry')" type="date" variant="outlined" density="comfortable" hide-details />
      </v-card-text>
      <v-card-actions>
        <v-btn v-if="shareDlg.share" color="error" variant="text" :loading="shareDlg.busy" @click="revokeShare">
          <span class="msym mr-1" style="font-size:18px">delete</span>{{ t('files.share_revoke') }}
        </v-btn>
        <v-spacer />
        <v-btn variant="text" @click="shareDlg.show=false">{{ t('common.close') }}</v-btn>
        <v-btn v-if="!shareDlg.share" color="primary" :loading="shareDlg.busy" @click="createShareLink">{{ t('files.share_create_link') }}</v-btn>
        <v-btn v-else color="primary" :loading="shareDlg.busy" @click="updateShareLink">{{ t('files.share_update') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Manage labels dialog -->
  <v-dialog v-model="labelsDlg.show" max-width="440">
    <v-card rounded="lg" border flat>
      <v-card-title class="d-flex align-center ga-2">
        <span class="msym" style="font-size:22px">sell</span>{{ t('files.labels_title') }}
      </v-card-title>
      <v-card-text>
        <div v-if="!s.labels.length" class="text-medium-emphasis mb-3">{{ t('files.labels_none') }}</div>
        <v-list v-else density="compact" class="mb-2">
          <v-list-item v-for="l in (s.labels as FileLabel[])" :key="l.id">
            <template #prepend>
              <v-avatar size="20" :color="l.color" class="mr-2" />
            </template>
            <v-list-item-title>{{ l.name }}</v-list-item-title>
            <template #append>
              <v-btn size="small" variant="text" @click="editLabel(l)"><span class="msym" style="font-size:18px">edit</span></v-btn>
              <v-btn size="small" variant="text" color="error" @click="removeLabel(l)"><span class="msym" style="font-size:18px">delete</span></v-btn>
            </template>
          </v-list-item>
        </v-list>
        <v-divider class="mb-3" />
        <div class="d-flex align-center ga-2">
          <input type="color" v-model="labelsDlg.color" style="width:40px;height:40px;border:none;background:none;cursor:pointer" >
          <v-text-field v-model="labelsDlg.name" :label="t('files.label_name')" variant="outlined" density="comfortable" hide-details class="flex-grow-1" @keyup.enter="saveLabel" />
          <v-btn color="primary" :loading="labelsDlg.busy" @click="saveLabel">{{ labelsDlg.editing ? t('common.save') : t('files.label_add') }}</v-btn>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="labelsDlg.show=false">{{ t('common.close') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Storage stats dialog -->
  <v-dialog v-model="storageDlg.show" max-width="520">
    <v-card rounded="lg" border flat>
      <v-card-title class="d-flex align-center ga-2">
        <span class="msym" style="font-size:22px">storage</span>{{ t('files.storage') }}
      </v-card-title>
      <v-card-text>
        <div v-if="storageDlg.loading" class="text-center py-6"><v-progress-circular indeterminate /></div>
        <template v-else-if="storageDlg.data">
          <div class="text-caption text-medium-emphasis">{{ t('files.storage_used_only', { used: fmt(storageDlg.data.used) }) }}</div>
          <div class="text-subtitle-2 mt-3 mb-1">{{ t('files.storage_by_type') }}</div>
          <v-list density="compact">
            <v-list-item v-for="(size, type) in storageDlg.data.by_type" :key="type">
              <v-list-item-title class="text-capitalize">{{ type }}</v-list-item-title>
              <template #append><span class="text-caption">{{ fmt(size) }}</span></template>
            </v-list-item>
          </v-list>
          <div class="text-subtitle-2 mt-3 mb-1">{{ t('files.duplicates') }}</div>
          <div v-if="!storageDlg.data.duplicates.length" class="text-medium-emphasis text-caption">{{ t('files.duplicates_none') }}</div>
          <v-list v-else density="compact">
            <template v-for="(grp, gi) in storageDlg.data.duplicates" :key="gi">
              <v-list-item v-for="d in grp" :key="d.id" :title="d.name" :subtitle="d.path">
                <template #append><span class="text-caption">{{ fmt(d.size) }}</span></template>
              </v-list-item>
              <v-divider v-if="gi < storageDlg.data.duplicates.length - 1" />
            </template>
          </v-list>
        </template>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="storageDlg.show=false">{{ t('common.close') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <!-- File preview -->
  <v-dialog v-model="previewOpen" max-width="1100" scrollable>
    <v-card v-if="preview" rounded="lg" class="d-flex flex-column" style="height:88vh">
      <v-toolbar flat color="surface" density="comfortable">
        <v-avatar size="30" variant="tonal" :color="undefined" class="ml-2"><span class="msym" style="font-size:18px">{{ categoryMsym(preview.name, preview.mime) }}</span></v-avatar>
        <v-toolbar-title class="text-body-1 ml-2">{{ preview.name }}</v-toolbar-title>
        <v-spacer />
        <v-btn variant="text" size="small" :icon="mdiInformationOutline" @click="openInfo(mapFile(preview))" />
        <v-btn variant="text" size="small" :icon="mdiDownload" :href="s.rawUrl(preview)" />
        <v-btn variant="text" size="small" :icon="mdiOpenInNew" :href="s.rawUrl(preview)" target="_blank" />
        <v-btn variant="text" size="small" :icon="mdiClose" @click="previewOpen = false" />
      </v-toolbar>
      <v-divider />
      <div class="flex-grow-1 d-flex align-center justify-center bg-surface-variant" style="overflow:auto">
        <img v-if="previewKind(preview) === 'image'" :src="s.rawUrl(preview)" style="max-width:100%;max-height:100%;object-fit:contain" >
        <iframe v-else-if="previewKind(preview) === 'pdf'" :src="s.rawUrl(preview)" style="width:100%;height:100%;border:0" ></iframe>
        <video v-else-if="previewKind(preview) === 'video'" :src="s.rawUrl(preview)" controls style="max-width:100%;max-height:100%"></video>
        <audio v-else-if="previewKind(preview) === 'audio'" :src="s.rawUrl(preview)" controls></audio>
        <iframe v-else-if="previewKind(preview) === 'text'" :src="s.rawUrl(preview)" style="width:100%;height:100%;border:0;background:#fff"></iframe>
        <div v-else class="text-center pa-10 text-medium-emphasis">
          <span class="msym d-block mb-3" style="font-size:56px">{{ categoryMsym(preview.name, preview.mime) }}</span>
          <div>{{ preview.name }}</div>
          <v-btn class="mt-4" color="primary" variant="tonal" :prepend-icon="mdiDownload" :href="s.rawUrl(preview)">{{ t('files.download') }}</v-btn>
        </div>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiUpload, mdiFolder, mdiStar, mdiDelete, mdiFolderPlus, mdiMagnify, mdiViewGrid, mdiViewList, mdiDotsVertical, mdiDownload, mdiPencil, mdiRestore, mdiDeleteForever, mdiContentCopy, mdiInformationOutline, mdiOpenInNew, mdiClose } from '@mdi/js';
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
const menu = ref<{ show: boolean; target: [number, number]; row: Row | null }>({ show: false, target: [0, 0], row: null });
const preview = ref<FileEntry | null>(null);
const previewOpen = ref(false);

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
function menuFor(row: Row, ev: MouseEvent) { menu.value = { show: true, target: [ev.clientX, ev.clientY], row }; }
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
</script>
