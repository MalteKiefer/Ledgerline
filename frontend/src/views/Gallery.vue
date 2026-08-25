<template>
  <div
    class="relative min-h-[calc(100vh-120px)]"
    @dragenter.prevent="onDragEnter" @dragover.prevent @dragleave.prevent="onDragLeave" @drop.prevent="onDrop"
  >
    <!-- Drag overlay -->
    <div v-show="dragDepth > 0 && !up.active" class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center rounded-xl border-2 border-dashed border-primary-500 bg-primary-500/10">
      <div class="rounded-xl bg-[var(--ll-elevated)] px-6 py-4 text-center shadow-lg">
        <Icon name="photo_library" :size="32" class="text-primary-500" />
        <div class="mt-1 text-sm font-medium">{{ t('gallery.drop_here') }}</div>
      </div>
    </div>

    <Card body-class="p-0">
      <!-- Toolbar -->
      <div class="flex items-center gap-2 border-b border-[var(--ll-border)] px-4 py-2.5">
        <h2 class="text-sm font-semibold">{{ showTrash ? t('gallery.trash') : (showArchive ? t('gallery.archive') : (showMemories ? t('gallery.memories') : (showPeople ? t('gallery.people') : t('messages.nav.gallery')))) }}</h2>
        <span v-if="!showTrash && !showPeople && !showDupes && !showShared" class="whitespace-nowrap text-xs text-[var(--ll-muted)]">
          {{ mediaCounts.ph }} {{ t('gallery.count_photos') }}<template v-if="mediaCounts.vid"> · {{ mediaCounts.vid }} {{ t('gallery.count_videos') }}</template>
        </span>
        <div v-if="!showTrash && !showPeople" class="relative ml-3 hidden sm:block">
          <Icon name="search" :size="16" class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-[var(--ll-muted)]" />
          <input
            v-model="searchQuery" type="search" :placeholder="t('gallery.search_ph')"
            class="w-52 rounded-lg border border-[var(--ll-border)] bg-transparent py-1.5 pl-8 pr-7 text-sm focus:border-primary-500 focus:outline-none"
            @keyup.enter="doSearch" @search="searchQuery ? undefined : clearSearch()"
          >
          <button v-if="searchActive" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[var(--ll-muted)] hover:text-[var(--ll-fg)]" @click="clearSearch"><Icon name="close" :size="15" /></button>
        </div>
        <!-- Actions: view switch + upload + one organized overflow menu -->
        <div class="ml-auto flex items-center gap-1.5">
          <!-- Grid size slider (desktop, grid view only) -->
          <label v-if="!showTrash && !showPeople && !showDupes && viewMode === 'grid'" class="mr-1 hidden items-center gap-1 lg:flex" :title="t('gallery.grid_size')">
            <Icon name="grid_view" :size="15" class="text-[var(--ll-muted)]" />
            <input type="range" min="2" max="12" step="1" :value="gridCols" class="w-20 accent-primary-500" @input="setGridCols(($event.target as HTMLInputElement).valueAsNumber)">
          </label>
          <!-- View segmented control (desktop) -->
          <div v-if="!showTrash" class="hidden items-center rounded-lg bg-black/[0.04] p-0.5 dark:bg-white/10 sm:flex">
            <button class="flex items-center gap-1 rounded-md px-2.5 py-1 text-sm font-medium transition" :class="viewMode === 'grid' && !showPeople ? 'bg-white text-primary-600 shadow-sm dark:bg-[#2c2c2e] dark:text-primary-300' : 'text-[var(--ll-muted)] hover:text-[var(--ll-fg)]'" @click="setView('grid')"><Icon name="grid_view" :size="16" />{{ t('gallery.view_grid') }}</button>
            <button class="flex items-center gap-1 rounded-md px-2.5 py-1 text-sm font-medium transition" :class="viewMode === 'map' && !showPeople ? 'bg-white text-primary-600 shadow-sm dark:bg-[#2c2c2e] dark:text-primary-300' : 'text-[var(--ll-muted)] hover:text-[var(--ll-fg)]'" @click="setView('map')"><Icon name="map" :size="16" />{{ t('gallery.view_map') }}</button>
          </div>
          <!-- Primary action: upload -->
          <Btn variant="solid" size="sm" icon="upload" @click="pick"><span class="hidden sm:inline">{{ t('gallery.upload') }}</span></Btn>
          <!-- Empty trash (only in trash) -->
          <Btn v-if="showTrash && trashPhotos.length" variant="ghost" size="sm" icon="delete" class="text-red-600" @click="onEmpty"><span class="hidden sm:inline">{{ t('gallery.empty_trash') }}</span></Btn>
          <!-- Overflow menu (desktop + mobile) -->
          <div class="relative">
            <button class="rounded-lg p-2 transition hover:bg-black/[0.05] dark:hover:bg-white/10" :class="menuOpen ? 'bg-black/[0.05] dark:bg-white/10' : ''" :aria-label="t('common.actions')" @click="menuOpen = !menuOpen"><Icon name="more_vert" :size="20" /></button>
            <div v-if="menuOpen" class="fixed inset-0 z-20" @click="menuOpen = false" />
            <div v-if="menuOpen" class="absolute right-0 z-30 mt-1 w-60 rounded-xl border border-[var(--ll-border)] bg-[var(--ll-elevated)] py-1 shadow-xl">
              <!-- Mobile-only: search + view toggle -->
              <div v-if="!showTrash && !showPeople" class="border-b border-[var(--ll-border)] p-2 sm:hidden">
                <input v-model="searchQuery" type="search" :placeholder="t('gallery.search_ph')" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-2 py-1.5 text-sm focus:border-primary-500 focus:outline-none" @keyup.enter="doSearch(); menuOpen = false">
              </div>
              <button v-if="!showTrash" class="flex w-full items-center gap-2.5 px-3 py-2 text-sm hover:bg-black/[0.05] dark:hover:bg-white/10 sm:hidden" @click="setView(viewMode === 'grid' ? 'map' : 'grid'); menuOpen = false"><Icon :name="viewMode === 'grid' ? 'map' : 'grid_view'" :size="18" class="text-[var(--ll-muted)]" />{{ viewMode === 'grid' ? t('gallery.view_map') : t('gallery.view_grid') }}</button>
              <div class="border-b border-[var(--ll-border)] sm:hidden" />

              <!-- Views -->
              <div v-if="!showTrash" class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('gallery.menu_view') }}</div>
              <button v-if="!showTrash" class="flex w-full items-center gap-2.5 px-3 py-2 text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" @click="(showPeople ? closePeople() : openPeople()); menuOpen = false"><Icon name="group" :size="18" class="text-[var(--ll-muted)]" /><span class="flex-1 text-left">{{ t('gallery.people') }}</span><Icon v-if="showPeople" name="check" :size="16" class="text-primary-500" /></button>
              <button v-if="!showTrash" class="flex w-full items-center gap-2.5 px-3 py-2 text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" @click="toggleMemories(); menuOpen = false"><Icon name="auto_awesome" :size="18" class="text-[var(--ll-muted)]" /><span class="flex-1 text-left">{{ t('gallery.memories') }}</span><Icon v-if="showMemories" name="check" :size="16" class="text-primary-500" /></button>
              <button v-if="!showTrash" class="flex w-full items-center gap-2.5 px-3 py-2 text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" @click="(showDupes ? closeDupes() : openDupes()); menuOpen = false"><Icon name="content_copy" :size="18" class="text-[var(--ll-muted)]" /><span class="flex-1 text-left">{{ t('gallery.duplicates') }}</span><Icon v-if="showDupes" name="check" :size="16" class="text-primary-500" /></button>

              <!-- Sharing -->
              <div v-if="!showTrash" class="border-t border-[var(--ll-border)] px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('gallery.menu_share') }}</div>
              <button v-if="!showTrash" class="flex w-full items-center gap-2.5 px-3 py-2 text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" @click="(showShared ? closeShared() : openShared()); menuOpen = false"><Icon name="folder_shared" :size="18" class="text-[var(--ll-muted)]" /><span class="flex-1 text-left">{{ t('gallery.shared_with_me') }}</span><Icon v-if="showShared" name="check" :size="16" class="text-primary-500" /></button>
              <button v-if="!showTrash && !showShared" class="flex w-full items-center gap-2.5 px-3 py-2 text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" @click="openLibraryShare(); menuOpen = false"><Icon name="share" :size="18" class="text-[var(--ll-muted)]" /><span class="flex-1 text-left">{{ t('gallery.share_gallery') }}</span></button>

              <!-- Library -->
              <div class="border-t border-[var(--ll-border)] px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('gallery.menu_library') }}</div>
              <button v-if="!showTrash" class="flex w-full items-center gap-2.5 px-3 py-2 text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" @click="toggleArchive(); menuOpen = false"><Icon name="inventory_2" :size="18" class="text-[var(--ll-muted)]" /><span class="flex-1 text-left">{{ t('gallery.archive') }}</span><Icon v-if="showArchive" name="check" :size="16" class="text-primary-500" /></button>
              <button v-if="!showTrash" class="flex w-full items-center gap-2.5 px-3 py-2 text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" :disabled="pairingLive" @click="pairLivePhotos(); menuOpen = false"><Icon :name="pairingLive ? 'progress_activity' : 'motion_photos_on'" :size="18" :class="pairingLive ? 'animate-spin text-primary-500' : 'text-[var(--ll-muted)]'" /><span class="flex-1 text-left">{{ t('gallery.pair_live') }}</span></button>
              <button v-if="!showArchive" class="flex w-full items-center gap-2.5 px-3 py-2 text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" @click="toggleTrash(); menuOpen = false"><Icon :name="showTrash ? 'photo_library' : 'delete'" :size="18" class="text-[var(--ll-muted)]" /><span class="flex-1 text-left">{{ showTrash ? t('gallery.back') : t('gallery.trash') }}</span><Icon v-if="showTrash" name="check" :size="16" class="text-primary-500" /></button>
            </div>
          </div>
        </div>
        <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="onPick">
      </div>

      <!-- People (face clusters) -->
      <div v-if="peopleGrid" class="p-3">
        <div v-if="!peopleList.length" class="py-20 text-center text-sm text-[var(--ll-muted)]">{{ t('gallery.people_none') }}</div>
        <div v-else class="grid grid-cols-3 gap-3 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-9">
          <button v-for="p in peopleList" :key="p.id" class="group flex flex-col items-center gap-1.5" @click="openPerson(p)">
            <div class="aspect-square w-full overflow-hidden rounded-full bg-black/[0.06] ring-1 ring-[var(--ll-border)] dark:bg-white/10">
              <img v-if="p.cover_face_id && !brokenFaces[p.cover_face_id]" :src="g.faceCropUrl(p.cover_face_id)" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105" @error="brokenFaces[p.cover_face_id!] = true">
              <div v-else class="flex h-full w-full items-center justify-center"><Icon name="person" :size="28" class="opacity-40" /></div>
            </div>
            <div class="w-full truncate text-center text-xs font-medium" :class="p.name ? '' : 'italic text-[var(--ll-muted)]'">{{ personLabel(p) }}</div>
            <div class="text-[10px] tabular-nums text-[var(--ll-muted)]">{{ p.count }}</div>
          </button>
        </div>
      </div>

      <!-- Person header (browsing one person's photos) -->
      <div v-if="showPeople && personView" class="flex items-center gap-2 border-b border-[var(--ll-border)] px-4 py-2">
        <Btn variant="ghost" size="sm" icon="arrow_back" @click="backToPeople">{{ t('gallery.people') }}</Btn>
        <span class="text-sm font-semibold" :class="personView.name ? '' : 'italic text-[var(--ll-muted)]'">{{ personLabel(personView) }}</span>
        <span class="text-xs text-[var(--ll-muted)]">· {{ personView.count }}</span>
        <div class="ml-auto flex items-center gap-1">
          <Btn variant="ghost" size="sm" :icon="personSort === 'desc' ? 'arrow_downward' : 'arrow_upward'" @click="togglePersonSort">{{ personSort === 'desc' ? t('gallery.sort_newest') : t('gallery.sort_oldest') }}</Btn>
          <Btn variant="ghost" size="sm" icon="edit" @click="openNamePerson">{{ t('gallery.person_rename') }}</Btn>
          <Btn variant="ghost" size="sm" icon="person_add" @click="openNamePerson">{{ t('gallery.person_link_contact') }}</Btn>
          <Btn variant="ghost" size="sm" icon="merge" @click="openMerge">{{ t('gallery.person_merge') }}</Btn>
          <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" @click="deletePerson">{{ t('common.delete') }}</Btn>
        </div>
      </div>

      <!-- Album chips -->
      <div v-if="!showTrash && !showDupes && !showPeople && viewMode === 'grid'" class="flex flex-wrap items-center gap-1.5 border-b border-[var(--ll-border)] px-4 py-2">
        <button class="rounded-full px-3 py-1 text-xs font-medium" :class="albumId === null ? 'bg-primary-500 text-white' : 'bg-black/[0.05] dark:bg-white/10'" @click="selectAlbum(null)">{{ t('gallery.all_photos') }}</button>
        <button
          v-for="a in g.albums" :key="a.id"
          class="flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium"
          :class="albumId === a.id ? 'bg-primary-500 text-white' : 'bg-black/[0.05] dark:bg-white/10'"
          @click="selectAlbum(a.id)"
        >
          <span>{{ a.name }}</span>
          <span class="tabular-nums opacity-70">{{ a.count }}</span>
          <Icon v-if="albumId === a.id" name="share" :size="14" class="opacity-80 hover:opacity-100" @click.stop="openShare(a)" />
          <Icon v-if="albumId === a.id" name="edit" :size="14" class="opacity-80 hover:opacity-100" @click.stop="renameAlbum(a)" />
          <Icon v-if="albumId === a.id" name="delete" :size="14" class="opacity-80 hover:opacity-100" @click.stop="deleteAlbum(a)" />
        </button>
        <button class="rounded-full border border-dashed border-[var(--ll-border)] px-3 py-1 text-xs text-[var(--ll-muted)] hover:text-[var(--ll-fg)]" @click="newAlbum">+ {{ t('gallery.new_album') }}</button>
      </div>

      <!-- Selection bar -->
      <div v-if="!showTrash && !showDupes && !peopleGrid && viewMode === 'grid' && selected.size" class="flex items-center gap-2 border-b border-[var(--ll-border)] bg-primary-500/5 px-4 py-2 text-sm">
        <span class="font-medium">{{ selected.size }} {{ t('gallery.selected') }}</span>
        <div class="ml-auto flex items-center gap-1">
          <div class="relative">
            <Btn variant="ghost" size="sm" icon="library_add" @click="albumMenu = !albumMenu">{{ t('gallery.add_to_album') }}</Btn>
            <div v-if="albumMenu" class="absolute right-0 z-20 mt-1 w-52 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-elevated)] py-1 shadow-lg">
              <div v-if="!g.albums.length" class="px-3 py-2 text-xs text-[var(--ll-muted)]">{{ t('gallery.no_albums') }}</div>
              <button v-for="a in g.albums" :key="a.id" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" @click="addSelectedToAlbum(a)">{{ a.name }}</button>
              <button class="block w-full border-t border-[var(--ll-border)] px-3 py-1.5 text-left text-sm text-primary-600" @click="newAlbumWithSelection">+ {{ t('gallery.new_album') }}</button>
            </div>
          </div>
          <Btn v-if="albumId !== null" variant="ghost" size="sm" icon="playlist_remove" @click="removeSelectedFromAlbum">{{ t('gallery.remove_from_album') }}</Btn>
          <Btn v-if="!showArchive" variant="ghost" size="sm" icon="edit" @click="openBulkEdit">{{ t('gallery.edit') }}</Btn>
          <Btn variant="ghost" size="sm" :icon="showArchive ? 'unarchive' : 'inventory_2'" @click="archiveSelection(!showArchive)">{{ showArchive ? t('gallery.unarchive') : t('gallery.archive_action') }}</Btn>
          <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" @click="bulkTrash">{{ t('common.delete') }}</Btn>
          <Btn variant="ghost" size="sm" icon="close" @click="clearSelection">{{ t('gallery.clear_selection') }}</Btn>
        </div>
      </div>

      <!-- Trash selection bar -->
      <div v-if="showTrash && selected.size" class="flex items-center gap-2 border-b border-[var(--ll-border)] bg-primary-500/5 px-4 py-2 text-sm">
        <span class="font-medium">{{ selected.size }} {{ t('gallery.selected') }}</span>
        <div class="ml-auto flex items-center gap-1">
          <Btn variant="ghost" size="sm" icon="restore" :disabled="trashBusy" @click="bulkRestore">{{ t('common.restore') }}</Btn>
          <Btn variant="ghost" size="sm" icon="delete_forever" class="text-red-600" :disabled="trashBusy" @click="bulkForce">{{ t('gallery.delete_forever') }}</Btn>
          <Btn variant="ghost" size="sm" icon="close" @click="clearSelection">{{ t('gallery.clear_selection') }}</Btn>
        </div>
      </div>

      <!-- Duplicates view -->
      <div v-if="showDupes && !showTrash" class="space-y-4 p-3">
        <div v-if="!dupeGroups.length" class="py-20 text-center text-sm text-[var(--ll-muted)]">{{ t('gallery.dupes_none') }}</div>
        <div v-for="(grp, gi) in dupeGroups" :key="gi" class="rounded-lg border border-[var(--ll-border)] p-2">
          <div class="mb-2 flex items-center gap-2 px-1 text-xs text-[var(--ll-muted)]">
            <span>{{ grp.photos.length }} {{ t('gallery.dupes_similar') }}</span>
            <Btn variant="ghost" size="sm" icon="delete" class="ml-auto text-red-600" @click="trashDupeGroup(gi)">{{ t('gallery.dupes_keep_one') }}</Btn>
          </div>
          <div class="grid grid-cols-5 gap-1 sm:grid-cols-8 md:grid-cols-12">
            <div v-for="(p, pi) in grp.photos" :key="p.id" class="group relative aspect-square overflow-hidden rounded-lg bg-black/[0.04] dark:bg-white/5">
              <img v-if="p.thumb" :src="g.thumbUrl(p.id)" loading="lazy" class="h-full w-full object-cover">
              <div v-else class="flex h-full w-full items-center justify-center"><Icon name="image" :size="20" class="opacity-40" /></div>
              <span v-if="pi === 0" class="pointer-events-none absolute left-1 top-1 rounded bg-primary-500 px-1 py-0.5 text-[9px] font-semibold text-white">{{ t('gallery.dupes_keep') }}</span>
              <button v-else class="absolute right-1 top-1 rounded-full bg-black/50 p-1 text-white opacity-0 transition group-hover:opacity-100 hover:bg-red-600" :title="t('common.delete')" @click="trashDupeOne(gi, p.id)"><Icon name="delete" :size="13" /></button>
            </div>
          </div>
        </div>
      </div>

      <!-- Map view -->
      <div v-show="!showTrash && !showDupes && !showPeople && !showMemories && viewMode === 'map'" class="p-3">
        <div v-if="!mapPhotos.length" class="py-20 text-center text-sm text-[var(--ll-muted)]">{{ t('gallery.no_located') }}</div>
        <div v-show="mapPhotos.length" ref="mapEl" class="h-[calc(100vh-230px)] w-full overflow-hidden rounded-lg border border-[var(--ll-border)]" />
      </div>

      <!-- Grid view -->
      <!-- Memories / auto-curation -->
      <div v-if="showMemories" class="space-y-6 p-4">
        <div v-if="memoriesLoading" class="py-16 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
        <template v-else-if="memoriesData">
          <div v-if="!memoriesData.on_this_day.length && !memoriesData.trips.length && !memoriesData.themes.length" class="py-16 text-center text-sm text-[var(--ll-muted)]">{{ t('gallery.memories_empty') }}</div>
          <!-- On this day -->
          <section v-for="d in memoriesData.on_this_day" :key="'d'+d.year">
            <h3 class="mb-2 text-sm font-semibold">{{ t('gallery.years_ago', { n: String(d.years_ago) }) }} <span class="text-[var(--ll-muted)]">· {{ d.year }}</span></h3>
            <div class="flex gap-2 overflow-x-auto pb-1">
              <img v-for="p in d.photos.slice(0, 16)" :key="p.id" :src="g.thumbUrl(p.id)" loading="lazy" class="h-28 w-28 shrink-0 cursor-pointer rounded-lg object-cover" @click="openMemory(d.photos)">
            </div>
          </section>
          <!-- Trips -->
          <section v-if="memoriesData.trips.length">
            <h3 class="mb-2 text-sm font-semibold">{{ t('gallery.trips') }}</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
              <button v-for="(tr, i) in memoriesData.trips" :key="'t'+i" class="group overflow-hidden rounded-xl border border-[var(--ll-border)] text-left" @click="openMemory(tr.photos)">
                <div class="aspect-[4/3] bg-black/[0.06] dark:bg-white/10"><img v-if="tr.cover" :src="g.thumbUrl(tr.cover)" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105"></div>
                <div class="p-2"><div class="truncate text-sm font-medium">{{ tripLabel(tr) }}</div><div class="text-xs text-[var(--ll-muted)]">{{ tr.count }} · {{ fmtDate(tr.from) }}</div></div>
              </button>
            </div>
          </section>
          <!-- Themes (CLIP) -->
          <section v-if="memoriesData.themes.length">
            <h3 class="mb-2 text-sm font-semibold">{{ t('gallery.themes') }}</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
              <button v-for="th in memoriesData.themes" :key="th.key" class="group overflow-hidden rounded-xl border border-[var(--ll-border)] text-left" @click="openMemory(th.photos)">
                <div class="aspect-square bg-black/[0.06] dark:bg-white/10"><img v-if="th.cover" :src="g.thumbUrl(th.cover)" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105"></div>
                <div class="p-2"><div class="truncate text-sm font-medium capitalize">{{ themeLabel(th.key) }}</div><div class="text-xs text-[var(--ll-muted)]">{{ th.count }}</div></div>
              </button>
            </div>
          </section>
        </template>
      </div>

      <div v-if="!showMemories && (viewMode === 'grid' || showTrash) && !showDupes && !peopleGrid" class="relative flex p-3">
        <div v-if="!current.length" class="w-full py-20 text-center text-sm text-[var(--ll-muted)]">{{ showTrash ? t('gallery.trash_empty') : (searchActive ? t('gallery.search_none') : t('gallery.empty')) }}</div>
        <div v-else class="grid min-w-0 flex-1 gap-1.5" :class="showScrubber ? 'md:pr-14' : ''" :style="gridStyle">
          <template v-for="(p, i) in current" :key="p.id">
            <div v-if="!showTrash && rowMeta[i]?.header" :id="rowMeta[i]?.monthStart ? 'g-m-' + rowMeta[i]?.mk : undefined" class="col-span-full flex scroll-mt-20 items-center gap-1.5 px-0.5 pt-2 text-xs font-semibold text-[var(--ll-muted)]">
              <button class="grid h-4 w-4 place-items-center rounded border border-[var(--ll-border)] hover:border-primary-500" :class="fullDays.has(rowMeta[i]!.day) ? 'bg-primary-500 text-white' : ''" :title="t('gallery.select_day')" @click="selectDay(rowMeta[i]!.day)"><Icon v-if="fullDays.has(rowMeta[i]!.day)" name="check" :size="12" /></button>
              {{ rowMeta[i]?.header }}
            </div>
            <div
              class="group relative aspect-square overflow-hidden rounded-lg bg-black/[0.04] dark:bg-white/5 [content-visibility:auto] [contain-intrinsic-size:180px_180px]"
              :class="selected.has(p.id) ? 'ring-2 ring-primary-500 ring-offset-1 ring-offset-[var(--ll-surface)]' : ''"
              @mouseenter="p.motion && !showTrash ? hoverId = p.id : null"
              @mouseleave="hoverId === p.id ? hoverId = -1 : null"
            >
              <!-- Media: processing/failed placeholder → thumbnail → pending spinner -->
              <div v-if="!showTrash && p.status === 'processing'" class="flex h-full w-full flex-col items-center justify-center gap-1 px-1 text-center text-[10px] text-[var(--ll-muted)]">
                <Icon name="movie" :size="22" class="opacity-50" />
                <Icon name="progress_activity" :size="14" class="animate-spin opacity-60" />
                <span>{{ t('gallery.processing') }}</span>
              </div>
              <div v-else-if="!showTrash && p.status === 'failed'" class="flex h-full w-full flex-col items-center justify-center gap-1 px-1 text-center text-[10px] text-red-500">
                <Icon name="error_outline" :size="22" class="opacity-70" />
                <span>{{ t('gallery.failed') }}</span>
              </div>
              <img
                v-else-if="p.thumb"
                :src="g.thumbUrl(p.id)" loading="lazy" draggable="false"
                class="h-full w-full cursor-pointer object-cover"
                :class="selected.has(p.id) ? 'opacity-80' : ''"
                @click="onTileClick($event, i, p)"
                @error="onThumbError"
              >
              <button
                v-else
                class="flex h-full w-full items-center justify-center text-[var(--ll-muted)]"
                :title="t('gallery.thumb_pending')"
                @click="onTileClick($event, i, p)"
              >
                <Icon name="progress_activity" :size="22" class="animate-spin opacity-60" />
              </button>
              <!-- Independent overlays -->
              <video
                v-if="p.motion && hoverId === p.id"
                :src="g.motionUrl(p.id)" muted loop autoplay playsinline
                class="pointer-events-none absolute inset-0 h-full w-full object-cover"
              />
              <span v-if="p.media_type === 'video' && p.status === 'ready' && p.thumb && !showTrash" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <Icon name="play_circle" :size="34" class="text-white/90 drop-shadow" />
              </span>
              <span v-if="p.media_type === 'video' && p.duration && !showTrash" class="pointer-events-none absolute bottom-1 right-1 rounded bg-black/50 px-1 py-0.5 text-[9px] font-semibold text-white">{{ fmtDuration(p.duration) }}</span>
              <span v-if="p.motion && !showTrash" class="pointer-events-none absolute bottom-1 right-1 flex items-center gap-0.5 rounded bg-black/50 px-1 py-0.5 text-[9px] font-semibold uppercase text-white">
                <Icon name="motion_photos_on" :size="11" /> Live
              </span>
              <button
                class="absolute left-1 top-1 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white/80 shadow transition"
                :class="selected.has(p.id) ? 'bg-primary-500' : 'bg-black/30 opacity-0 group-hover:opacity-100'"
                @click.stop="toggleAt(i, p)"
              >
                <Icon v-if="selected.has(p.id)" name="check" :size="12" class="text-white" />
              </button>
              <Icon v-if="p.lat !== null && !showTrash" name="location_on" :size="14" class="absolute bottom-1 left-1 text-white drop-shadow" />
              <Icon v-if="p.favorite && !showTrash" name="star" :size="16" class="absolute right-1 top-1 text-amber-400 drop-shadow" />
              <div v-if="showTrash" class="absolute inset-x-0 bottom-0 flex justify-center gap-1 bg-black/40 p-1">
                <button class="rounded p-1 text-white hover:bg-white/20" :title="t('common.restore')" @click.stop="onRestore(p.id)"><Icon name="restore" :size="16" /></button>
                <button class="rounded p-1 text-white hover:bg-white/20" :title="t('gallery.delete_forever')" @click.stop="onForce(p.id)"><Icon name="delete_forever" :size="16" /></button>
              </div>
            </div>
          </template>
          <!-- Infinite-scroll sentinel: loads the next keyset page as it nears view. -->
          <div v-if="!showTrash" ref="loadSentinel" class="col-span-full h-1"></div>
          <div v-if="g.loadingMore" class="col-span-full flex justify-center py-4 text-[var(--ll-muted)]">
            <Icon name="progress_activity" :size="20" class="animate-spin opacity-60" />
          </div>
        </div>
        <!-- Google-Photos-style draggable date scrubber: an invisible fixed
             rail on the right; drag/click maps to the timeline scroll position,
             a bubble shows the date. Year ticks (from /gallery/dates, the full
             histogram) are clickable to jump straight to any year — even one not
             yet loaded (keyset pagination fetches that page on demand). -->
        <div
          v-if="showScrubber" ref="railRef"
          class="fixed right-0 top-[4.75rem] bottom-3 z-20 hidden w-8 cursor-ns-resize touch-none md:block"
          @pointerenter="scrubHover = true" @pointerleave="scrubHover = false"
          @pointerdown="scrubStart" @pointermove="scrubMove" @pointerup="scrubEnd" @pointercancel="scrubEnd"
        >
          <!-- clickable year jump ticks (full range from the histogram) -->
          <button
            v-for="y in yearTicks" :key="y.year" type="button"
            class="absolute right-2.5 -translate-y-1/2 whitespace-nowrap text-[10px] font-semibold text-[var(--ll-muted)] transition-opacity duration-150 hover:text-primary-500"
            :class="scrubActive ? 'opacity-90' : 'opacity-40'" :style="{ top: y.pct + '%' }"
            @pointerdown.stop @click.stop="jumpYear(y.ym)"
          >{{ y.year }}</button>
          <!-- slim always-on thumb indicator -->
          <div class="pointer-events-none absolute right-1 h-9 w-1 -translate-y-1/2 rounded-full bg-[var(--ll-border)] transition-colors" :class="scrubActive ? 'bg-primary-500' : ''" :style="{ top: thumbTop + 'px' }" />
          <!-- date bubble (only while active) -->
          <div
            class="pointer-events-none absolute right-4 flex -translate-y-1/2 items-center whitespace-nowrap rounded-full bg-primary-500 px-2.5 py-1 text-[11px] font-medium text-white shadow-lg transition-opacity duration-150"
            :class="scrubActive ? 'opacity-100' : 'opacity-0'" :style="{ top: thumbTop + 'px' }"
          >{{ scrubLabel }}</div>
        </div>
      </div>
    </Card>

    <!-- Upload progress -->
    <Teleport to="body">
      <div v-show="up.active" class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/30">
        <div class="w-80 max-w-[90%] rounded-xl bg-[var(--ll-elevated)] px-6 py-5 shadow-xl">
          <div class="flex items-center gap-2 text-sm font-medium">
            <Icon name="upload" :size="20" class="text-primary-500" />
            {{ t('gallery.uploading') }} <span class="ml-auto tabular-nums text-[var(--ll-muted)]">{{ up.done }} / {{ up.total }}</span>
          </div>
          <div class="mt-1 truncate text-xs text-[var(--ll-muted)]">{{ up.name }}</div>
          <div class="mt-3 h-2 overflow-hidden rounded-full bg-black/[0.08] dark:bg-white/10">
            <div class="h-full rounded-full bg-primary-500 transition-all" :style="{ width: upPct + '%' }" />
          </div>
          <div class="mt-1 text-right text-xs tabular-nums text-[var(--ll-muted)]">{{ upPct }}%</div>
        </div>
      </div>
    </Teleport>

    <!-- Lightbox -->
    <Teleport to="body">
      <div v-if="viewer >= 0" class="fixed inset-0 z-[2100] flex items-center justify-center bg-black/90 transition-[padding]" :class="showInfo ? 'sm:pr-[24rem]' : ''" @click.self="viewer = -1">
        <button class="absolute top-4 z-30 rounded-full p-2 text-white/80 hover:bg-white/10" :class="showInfo ? 'right-4 sm:right-[25rem]' : 'right-4'" @click="viewer = -1"><Icon name="close" :size="24" /></button>
        <button class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/80 hover:bg-white/10" @click="step(-1)"><Icon name="chevron_left" :size="32" /></button>
        <button class="absolute top-1/2 -translate-y-1/2 rounded-full p-2 text-white/80 hover:bg-white/10" :class="showInfo ? 'right-3 sm:right-[24.5rem]' : 'right-3'" @click="step(1)"><Icon name="chevron_right" :size="32" /></button>
        <video v-if="viewerPhoto && viewerPhoto.media_type === 'video'" :src="g.playUrl(viewerPhoto.id)" autoplay controls playsinline class="max-h-[92vh] max-w-[92vw] object-contain" />
        <video v-else-if="viewerPhoto && motionPlaying && viewerPhoto.motion" :src="g.motionUrl(viewerPhoto.id)" autoplay loop controls playsinline class="max-h-[92vh] max-w-[92vw] object-contain" />
        <img v-else-if="viewerPhoto && viewerPhoto.preview" :src="g.previewUrl(viewerPhoto.id)" class="max-h-[92vh] max-w-[92vw] object-contain">
        <div v-else-if="viewerPhoto" class="flex flex-col items-center gap-2 text-white/70">
          <Icon name="progress_activity" :size="32" class="animate-spin" />
          <span class="text-sm">{{ t('gallery.processing') }}</span>
        </div>
        <!-- Detected face chips -->
        <div v-if="viewerFaces.length" class="absolute inset-x-0 bottom-16 flex flex-wrap items-center gap-1.5 px-6">
          <span v-for="f in viewerFaces" :key="f.id" class="group inline-flex items-center gap-1 rounded-full bg-black/50 py-0.5 pl-0.5 pr-2 text-xs text-white backdrop-blur">
            <img v-if="f.crop && !brokenFaces[f.id]" :src="g.faceCropUrl(f.id)" class="h-6 w-6 rounded-full object-cover" @error="brokenFaces[f.id] = true">
            <button class="max-w-[10rem] truncate hover:underline" @click="openNameFace(f)">{{ f.person_name ?? t('gallery.face_unnamed') }}</button>
            <button v-if="f.person_id" class="text-white/60 hover:text-amber-300" :title="t('gallery.set_cover')" @click="setCoverFromChip(f)"><Icon name="account_circle" :size="14" /></button>
            <button class="text-white/60 hover:text-red-400" :title="t('gallery.face_hide')" @click="hideFaceChip(f)"><Icon name="close" :size="13" /></button>
          </span>
        </div>
        <div class="absolute inset-x-0 bottom-0 flex items-center gap-3 bg-gradient-to-t from-black/70 to-transparent px-6 py-4 text-sm text-white">
          <div class="min-w-0 flex-1">
            <div class="truncate">{{ viewerPhoto?.name }}</div>
            <div class="truncate text-xs text-white/70">
              <span v-if="viewerDate">{{ viewerDate }}</span>
              <span v-if="viewerPhoto?.place"> · {{ viewerPhoto?.place }}</span>
              <span v-else-if="viewerPhoto?.camera"> · {{ viewerPhoto?.camera }}</span>
            </div>
          </div>
          <button v-if="viewerPhoto?.motion" class="rounded-full p-2 hover:bg-white/10" :class="motionPlaying ? 'text-primary-300' : ''" :title="t('gallery.play_motion')" @click="motionPlaying = !motionPlaying">
            <Icon name="motion_photos_on" :size="22" />
          </button>
          <button class="rounded-full p-2 hover:bg-white/10" :class="showInfo ? 'text-primary-300' : ''" :title="t('gallery.info')" @click="toggleInfo">
            <Icon name="info" :size="22" />
          </button>
          <button class="rounded-full p-2 hover:bg-white/10" :title="t('gallery.favorite')" @click="onFav">
            <Icon :name="viewerPhoto?.favorite ? 'star' : 'star_border'" :size="22" :class="viewerPhoto?.favorite ? 'text-amber-400' : ''" />
          </button>
          <button class="rounded-full p-2 hover:bg-white/10" :title="t('gallery.edit')" @click="openEdit"><Icon name="edit" :size="22" /></button>
          <!-- download original/edited -->
          <div class="relative">
            <button class="rounded-full p-2 hover:bg-white/10" :title="t('common.download')" @click="dlMenu = !dlMenu"><Icon name="download" :size="22" /></button>
            <div v-if="dlMenu" class="absolute bottom-full right-0 mb-1 w-44 rounded-lg border border-white/10 bg-neutral-900 py-1 text-white shadow-lg">
              <a :href="g.downloadUrl(viewerPhoto?.id ?? 0, 'original')" download class="block px-3 py-1.5 text-sm hover:bg-white/10" @click="dlMenu = false">{{ t('gallery.dl_original') }}</a>
              <a v-if="isEdited(viewerPhoto)" :href="g.downloadUrl(viewerPhoto?.id ?? 0, 'edited')" download class="block px-3 py-1.5 text-sm hover:bg-white/10" @click="dlMenu = false">{{ t('gallery.dl_edited') }}</a>
            </div>
          </div>
          <button v-if="viewerPhoto" class="rounded-full p-2 hover:bg-white/10" :title="t('gallery.comments')" @click="openComments(viewerPhoto.id)"><Icon name="chat_bubble" :size="22" /></button>
          <button v-if="viewerPhoto" class="rounded-full p-2 hover:bg-white/10" :title="viewerPhoto.archived ? t('gallery.unarchive') : t('gallery.archive_action')" @click="archiveOne(viewerPhoto, !viewerPhoto.archived)"><Icon :name="viewerPhoto.archived ? 'unarchive' : 'inventory_2'" :size="22" /></button>
          <button class="rounded-full p-2 text-red-400 hover:bg-white/10" :title="t('common.delete')" @click="onDelete"><Icon name="delete" :size="22" /></button>
        </div>

        <!-- Info sidebar: all readable EXIF + mini-map -->
        <div v-if="showInfo" class="absolute inset-y-0 right-0 z-20 flex w-full max-w-sm flex-col bg-[var(--ll-elevated)] text-[var(--ll-fg)] shadow-2xl">
          <div class="flex items-center justify-between border-b border-[var(--ll-border)] px-4 py-3">
            <h3 class="text-sm font-semibold">{{ t('gallery.info') }}</h3>
            <button class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="showInfo = false"><Icon name="close" :size="18" /></button>
          </div>
          <div class="flex-1 space-y-4 overflow-y-auto p-4 text-sm">
            <div v-if="exifLoading" class="py-10 text-center text-[var(--ll-muted)]"><Icon name="progress_activity" :size="22" class="animate-spin" /></div>
            <template v-else-if="viewerExif">
              <!-- GPS mini-map -->
              <div v-if="viewerExif.lat != null && viewerExif.lng != null">
                <div ref="exifMapEl" class="h-44 w-full overflow-hidden rounded-lg border border-[var(--ll-border)]" />
                <div class="mt-1.5 flex items-center justify-between text-xs text-[var(--ll-muted)]">
                  <span class="font-mono">{{ viewerExif.lat.toFixed(6) }}, {{ viewerExif.lng.toFixed(6) }}</span>
                  <span class="flex gap-2">
                    <a class="text-primary-600 hover:underline dark:text-primary-300" :href="`https://www.google.com/maps?q=${viewerExif.lat},${viewerExif.lng}`" target="_blank" rel="noopener">Google Maps</a>
                    <a class="text-primary-600 hover:underline dark:text-primary-300" :href="`https://maps.apple.com/?ll=${viewerExif.lat},${viewerExif.lng}`" target="_blank" rel="noopener">Apple Maps</a>
                  </span>
                </div>
              </div>
              <!-- Overview -->
              <div class="overflow-hidden rounded-xl border border-[var(--ll-border)]">
                <div class="flex items-center gap-1.5 border-b border-[var(--ll-border)] bg-[var(--ll-bg)] px-3 py-2 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">
                  <Icon name="info" :size="14" />{{ t('gallery.info_overview') }}
                </div>
                <dl class="divide-y divide-[var(--ll-border)]">
                  <div v-for="r in overviewRows" :key="r.k" class="flex items-baseline justify-between gap-3 px-3 py-1.5">
                    <dt class="shrink-0 text-xs text-[var(--ll-muted)]">{{ r.k }}</dt>
                    <dd class="break-words text-right text-[0.8rem] font-medium">{{ r.v }}</dd>
                  </div>
                </dl>
              </div>
              <!-- Full EXIF sections -->
              <div v-for="sec in exifSections" :key="sec.title" class="overflow-hidden rounded-xl border border-[var(--ll-border)]">
                <div class="flex items-center gap-1.5 border-b border-[var(--ll-border)] bg-[var(--ll-bg)] px-3 py-2 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">
                  <Icon :name="sec.sec === 'GPS' ? 'location_on' : (sec.sec === 'EXIF' ? 'photo_camera' : 'image')" :size="14" />{{ sec.title }}
                </div>
                <dl class="divide-y divide-[var(--ll-border)]">
                  <div v-for="r in sec.rows" :key="r.k" class="flex items-baseline justify-between gap-3 px-3 py-1.5">
                    <dt class="shrink-0 text-xs text-[var(--ll-muted)]">{{ r.k }}</dt>
                    <dd class="break-words text-right font-mono text-[0.78rem] font-medium">{{ r.v }}</dd>
                  </div>
                </dl>
              </div>
              <div v-if="!exifSections.length && viewerExif.lat == null" class="py-6 text-center text-xs text-[var(--ll-muted)]">{{ t('gallery.info_none') }}</div>
            </template>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Share modal (album public link + internal cross-user grants) -->
    <Teleport to="body">
      <div v-if="share.open" class="fixed inset-0 z-[2200] flex items-center justify-center bg-black/50 p-4" @click.self="share.open = false">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-[var(--ll-elevated)] shadow-xl">
          <div class="flex items-center justify-between border-b border-[var(--ll-border)] px-5 py-3">
            <h3 class="text-sm font-semibold">{{ t('gallery.share_title') }}: {{ share.albumId ? share.albumName : t('gallery.whole_gallery') }}</h3>
            <button class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="share.open = false"><Icon name="close" :size="18" /></button>
          </div>
          <div class="space-y-5 p-5">
            <!-- Public link (albums only) -->
            <div v-if="share.albumId">
              <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('gallery.public_link') }}</div>
              <template v-if="share.public">
                <div class="mb-2 flex gap-2">
                  <input :value="g.publicShareUrl(share.public.token)" readonly class="min-w-0 flex-1 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-bg)] px-2 py-1.5 text-xs">
                  <Btn variant="soft" size="sm" icon="content_copy" @click="copyShareLink">{{ t('common.copy') }}</Btn>
                </div>
                <label class="flex items-center gap-2 py-1 text-sm"><input type="checkbox" :checked="share.public.allow_download" class="accent-primary-500" @change="setAllowDownload(($event.target as HTMLInputElement).checked)">{{ t('gallery.allow_download') }}</label>
                <label class="flex items-center gap-2 py-1 text-sm"><input type="checkbox" :checked="share.public.has_password" class="accent-primary-500" @change="togglePassword(($event.target as HTMLInputElement).checked)">{{ t('gallery.password_protect') }}</label>
                <Btn variant="ghost" size="sm" icon="delete" class="mt-1 text-red-600" @click="removePublic">{{ t('gallery.remove_link') }}</Btn>
              </template>
              <Btn v-else variant="soft" size="sm" icon="link" :loading="share.busy" @click="createPublic">{{ t('gallery.create_link') }}</Btn>
            </div>
            <!-- Internal cross-user share -->
            <div>
              <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('gallery.share_with_user') }}</div>
              <form class="mb-2 flex gap-2" @submit.prevent="addInternal">
                <input v-model="share.email" type="email" :placeholder="t('common.email')" class="min-w-0 flex-1 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-bg)] px-2 py-1.5 text-sm">
                <select v-if="share.albumId" v-model="share.role" class="rounded-lg border border-[var(--ll-border)] bg-[var(--ll-bg)] px-2 py-1.5 text-sm">
                  <option value="viewer">{{ t('gallery.role_viewer') }}</option>
                  <option value="editor">{{ t('gallery.role_editor') }}</option>
                </select>
                <Btn type="submit" variant="soft" size="sm" icon="person_add" :loading="share.busy">{{ t('gallery.share_action') }}</Btn>
              </form>
              <p v-if="share.albumId" class="mb-2 text-xs text-[var(--ll-muted)]">{{ t('gallery.role_hint') }}</p>
              <ul v-if="share.internal.length" class="divide-y divide-[var(--ll-border)] rounded-lg border border-[var(--ll-border)]">
                <li v-for="s in share.internal" :key="s.id" class="flex items-center justify-between px-3 py-1.5 text-sm">
                  <span class="truncate">{{ s.recipient }}<span v-if="s.role === 'editor'" class="ml-1 rounded bg-primary-500/15 px-1 text-[10px] font-medium text-primary-600">{{ t('gallery.role_editor') }}</span></span>
                  <button class="text-red-600 hover:opacity-80" @click="removeInternal(s.id)"><Icon name="close" :size="15" /></button>
                </li>
              </ul>
              <p v-else class="text-xs text-[var(--ll-muted)]">{{ t('gallery.no_recipients') }}</p>
            </div>
            <!-- Public guest upload links (album only) -->
            <div v-if="share.albumId">
              <div class="mb-2 flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('gallery.upload_links') }}</span>
                <Btn variant="soft" size="sm" icon="add_link" :loading="share.busy" @click="addUploadLink">{{ t('gallery.create_link') }}</Btn>
              </div>
              <ul v-if="share.uploadLinks.length" class="divide-y divide-[var(--ll-border)] rounded-lg border border-[var(--ll-border)]">
                <li v-for="l in share.uploadLinks" :key="l.id" class="flex items-center gap-2 px-3 py-1.5 text-sm">
                  <span class="min-w-0 flex-1 truncate">{{ l.label || g.uploadLinkUrl(l.token) }}</span>
                  <button class="text-[var(--ll-muted)] hover:text-primary-600" :title="t('common.copy')" @click="copyUploadLink(l.token)"><Icon name="content_copy" :size="15" /></button>
                  <button class="text-red-600 hover:opacity-80" @click="removeUploadLink(l.id)"><Icon name="close" :size="15" /></button>
                </li>
              </ul>
              <p v-else class="text-xs text-[var(--ll-muted)]">{{ t('gallery.upload_links_hint') }}</p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Shared with me -->
    <Teleport to="body">
      <div v-if="showShared" class="fixed inset-0 z-[2150] flex flex-col bg-[var(--ll-bg)]">
        <div class="flex items-center gap-2 border-b border-[var(--ll-border)] px-4 py-3">
          <Btn v-if="sharedView" variant="ghost" size="sm" icon="arrow_back" @click="sharedView = null">{{ t('common.back') }}</Btn>
          <h2 class="text-sm font-semibold">{{ sharedView ? sharedView.name : t('gallery.shared_with_me') }}</h2>
          <Btn v-if="sharedView && sharedCanContribute" variant="soft" size="sm" icon="add_photo_alternate" :loading="sharedUploading" class="ml-auto" @click="pickContribute">{{ t('gallery.contribute') }}</Btn>
          <input ref="contributeInput" type="file" accept="image/*,video/*" multiple class="hidden" @change="onContribute">
          <button :class="sharedView && sharedCanContribute ? '' : 'ml-auto'" class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="closeShared"><Icon name="close" :size="20" /></button>
        </div>
        <div class="flex-1 overflow-y-auto p-3">
          <div v-if="!sharedView">
            <div v-if="!sharedList.length" class="py-20 text-center text-sm text-[var(--ll-muted)]">{{ t('gallery.shared_none') }}</div>
            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
              <button v-for="s in sharedList" :key="s.id" class="group overflow-hidden rounded-lg border border-[var(--ll-border)] text-left" @click="openSharedShare(s)">
                <div class="aspect-video w-full bg-black/[0.06] dark:bg-white/10">
                  <img v-if="s.cover" :src="g.sharedThumbUrl(s.id, s.cover)" loading="lazy" class="h-full w-full object-cover">
                </div>
                <div class="p-2">
                  <div class="truncate text-sm font-medium">{{ s.name }}</div>
                  <div class="truncate text-xs text-[var(--ll-muted)]">{{ s.owner }} · {{ s.count }} · {{ s.scope === 'library' ? t('gallery.whole_gallery') : t('gallery.info_sec_image') }}</div>
                </div>
              </button>
            </div>
          </div>
          <div v-else class="grid gap-2" :style="gridStyle">
            <button v-for="(p, i) in sharedPhotos" :key="p.id" class="aspect-square overflow-hidden rounded-lg bg-black/[0.06] dark:bg-white/10" @click="sharedViewer = i">
              <img :src="g.sharedThumbUrl(sharedView.id, p.id)" loading="lazy" class="h-full w-full object-cover">
            </button>
          </div>
        </div>
        <!-- Shared photo lightbox -->
        <div v-if="sharedViewer >= 0 && sharedView" class="fixed inset-0 z-[2160] flex items-center justify-center bg-black/90" @click.self="sharedViewer = -1">
          <button class="absolute right-4 top-4 rounded-full p-2 text-white/80 hover:bg-white/10" @click="sharedViewer = -1"><Icon name="close" :size="24" /></button>
          <button class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/80 hover:bg-white/10" @click="sharedStep(-1)"><Icon name="chevron_left" :size="32" /></button>
          <button class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/80 hover:bg-white/10" @click="sharedStep(1)"><Icon name="chevron_right" :size="32" /></button>
          <img v-if="sharedPhotos[sharedViewer]" :src="g.sharedPreviewUrl(sharedView.id, sharedPhotos[sharedViewer].id)" class="max-h-[92vh] max-w-[92vw] object-contain">
        </div>
      </div>
    </Teleport>

    <!-- Edit modal -->
    <Teleport to="body">
      <div v-if="edit.open" class="fixed inset-0 z-[2200] flex items-center justify-center bg-black/50 p-4" @click.self="edit.open = false">
        <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-[var(--ll-elevated)] shadow-xl">
          <div class="flex items-center justify-between border-b border-[var(--ll-border)] px-5 py-3">
            <h3 class="text-sm font-semibold">{{ t('gallery.edit') }}</h3>
            <button class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="edit.open = false"><Icon name="close" :size="18" /></button>
          </div>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <!-- Live preview + rotate/mirror -->
            <div>
              <div class="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-black/[0.06] dark:bg-white/5">
                <img v-if="edit.id && edit.preview" :src="g.previewUrl(edit.id)" class="max-h-full max-w-full object-contain transition-transform" :style="previewStyle">
                <Icon v-else name="image" :size="40" class="opacity-40" />
              </div>
              <div class="mt-2 flex justify-center gap-1">
                <Btn variant="ghost" size="sm" icon="rotate_left" @click="rotate(-90)">{{ t('gallery.rotate_left') }}</Btn>
                <Btn variant="ghost" size="sm" icon="rotate_right" @click="rotate(90)">{{ t('gallery.rotate_right') }}</Btn>
                <Btn :variant="edit.flip_h ? 'solid' : 'ghost'" size="sm" icon="flip" @click="edit.flip_h = !edit.flip_h">{{ t('gallery.mirror') }}</Btn>
              </div>
            </div>
            <!-- Metadata -->
            <div class="space-y-3">
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('gallery.date') }}</span>
                <input v-model="edit.date" type="date" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm">
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('gallery.time') }}</span>
                <input v-model="edit.time" type="time" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm">
              </label>
              <LocationField
                :model-value="edit.place" :lat="edit.lat" :lon="edit.lng"
                :label="t('gallery.location')"
                @update:model-value="edit.place = $event"
                @update:lat="edit.lat = $event"
                @update:lon="edit.lng = $event"
              />
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-[var(--ll-border)] px-5 py-3">
            <Btn variant="ghost" size="sm" @click="edit.open = false">{{ t('common.cancel') }}</Btn>
            <Btn variant="solid" size="sm" :loading="edit.saving" @click="saveEdit">{{ t('common.save') }}</Btn>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Bulk edit modal -->
    <Teleport to="body">
      <div v-if="bulk.open" class="fixed inset-0 z-[2200] flex items-center justify-center bg-black/50 p-4" @click.self="bulk.open = false">
        <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-[var(--ll-elevated)] shadow-xl">
          <div class="flex items-center justify-between border-b border-[var(--ll-border)] px-5 py-3">
            <h3 class="text-sm font-semibold">{{ t('gallery.bulk_edit') }} · {{ bulk.count }}</h3>
            <button class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="bulk.open = false"><Icon name="close" :size="18" /></button>
          </div>
          <div class="space-y-4 p-5">
            <p class="text-xs text-[var(--ll-muted)]">{{ t('gallery.bulk_edit_hint') }}</p>
            <div>
              <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('gallery.rotate_mirror') }}</span>
              <div class="flex gap-1">
                <Btn variant="ghost" size="sm" icon="rotate_left" @click="bulk.rotate = (bulk.rotate + 270) % 360">{{ t('gallery.rotate_left') }}</Btn>
                <Btn variant="ghost" size="sm" icon="rotate_right" @click="bulk.rotate = (bulk.rotate + 90) % 360">{{ t('gallery.rotate_right') }}</Btn>
                <Btn :variant="bulk.mirror ? 'solid' : 'ghost'" size="sm" icon="flip" @click="bulk.mirror = !bulk.mirror">{{ t('gallery.mirror') }}</Btn>
                <span v-if="bulk.rotate" class="self-center text-xs text-[var(--ll-muted)]">+{{ bulk.rotate }}°</span>
              </div>
            </div>
            <label class="block">
              <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('gallery.date') }} ({{ t('gallery.bulk_optional') }})</span>
              <div class="flex gap-2">
                <input v-model="bulk.date" type="date" class="flex-1 rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm">
                <input v-model="bulk.time" type="time" class="w-32 rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm">
              </div>
            </label>
            <LocationField
              :model-value="bulk.place" :lat="bulk.lat" :lon="bulk.lng"
              :label="t('gallery.location') + ' (' + t('gallery.bulk_optional') + ')'"
              @update:model-value="bulk.place = $event" @update:lat="bulk.lat = $event" @update:lon="bulk.lng = $event"
            />
          </div>
          <div class="flex justify-end gap-2 border-t border-[var(--ll-border)] px-5 py-3">
            <Btn variant="ghost" size="sm" @click="bulk.open = false">{{ t('common.cancel') }}</Btn>
            <Btn variant="solid" size="sm" :loading="bulk.saving" @click="saveBulkEdit">{{ t('common.save') }}</Btn>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Name a person / face (free text + address-book autocomplete) -->
    <Teleport to="body">
      <div v-if="nameM.open" class="fixed inset-0 z-[2300] flex items-center justify-center bg-black/50 p-4" @click.self="nameM.open = false">
        <div class="w-full max-w-sm rounded-xl bg-[var(--ll-elevated)] shadow-xl">
          <div class="flex items-center justify-between border-b border-[var(--ll-border)] px-5 py-3">
            <h3 class="text-sm font-semibold">{{ t('gallery.name_person') }}</h3>
            <button class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="nameM.open = false"><Icon name="close" :size="18" /></button>
          </div>
          <div class="p-5">
            <input
              v-model="nameM.query" type="text" autofocus :placeholder="t('gallery.name_placeholder')"
              class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm focus:border-primary-500 focus:outline-none"
              @input="onNameInput" @keyup.enter="saveName"
            >
            <!-- Current contact link (retroactive linking / unlinking). -->
            <div v-if="nameLinked" class="mt-2 flex items-center gap-2 rounded-lg bg-primary-500/10 px-3 py-1.5 text-xs text-primary-700 dark:text-primary-300">
              <Icon name="link" :size="14" />
              <span class="flex-1">{{ t('gallery.contact_linked') }}</span>
              <button class="font-medium hover:underline" @click="unlinkContact">{{ t('gallery.contact_unlink') }}</button>
            </div>
            <p class="mt-2 text-[11px] text-[var(--ll-muted)]">{{ t('gallery.contact_link_hint') }}</p>
            <div v-if="nameM.suggestions.length" class="mt-2 max-h-52 overflow-y-auto rounded-lg border border-[var(--ll-border)]">
              <div class="px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('gallery.from_contacts') }}</div>
              <button v-for="s in nameM.suggestions" :key="s.id" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" @click="pickSuggestion(s)">
                <Icon name="person" :size="16" class="text-[var(--ll-muted)]" />
                <span class="truncate">{{ s.name }}</span>
              </button>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-[var(--ll-border)] px-5 py-3">
            <Btn variant="ghost" size="sm" @click="nameM.open = false">{{ t('common.cancel') }}</Btn>
            <Btn variant="solid" size="sm" :loading="nameM.saving" :disabled="!nameM.query.trim()" @click="saveName">{{ t('common.save') }}</Btn>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Merge into a named person (search) -->
    <Teleport to="body">
      <div v-if="mergeM.open" class="fixed inset-0 z-[2300] flex items-center justify-center bg-black/50 p-4" @click.self="mergeM.open = false">
        <div class="flex max-h-[80vh] w-full max-w-sm flex-col rounded-xl bg-[var(--ll-elevated)] shadow-xl">
          <div class="flex items-center justify-between border-b border-[var(--ll-border)] px-5 py-3">
            <h3 class="text-sm font-semibold">{{ t('gallery.person_merge') }}</h3>
            <button class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="mergeM.open = false"><Icon name="close" :size="18" /></button>
          </div>
          <div class="p-3">
            <input
              v-model="mergeM.query" type="text" autofocus :placeholder="t('gallery.merge_search')"
              class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm focus:border-primary-500 focus:outline-none"
            >
          </div>
          <div class="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
            <div v-if="!mergeCandidates.length" class="px-3 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('gallery.person_merge_none') }}</div>
            <button
              v-for="x in mergeCandidates" :key="x.id"
              class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-black/[0.05] dark:hover:bg-white/10"
              @click="doMerge(x)"
            >
              <span class="h-8 w-8 overflow-hidden rounded-full bg-black/[0.06] ring-1 ring-[var(--ll-border)] dark:bg-white/10">
                <img v-if="x.cover_face_id && !brokenFaces[x.cover_face_id]" :src="g.faceCropUrl(x.cover_face_id)" class="h-full w-full object-cover" @error="brokenFaces[x.cover_face_id!] = true">
              </span>
              <span class="min-w-0 flex-1 truncate">{{ x.name }}</span>
              <span class="text-[10px] tabular-nums text-[var(--ll-muted)]">{{ x.count }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Comments + reactions -->
    <Teleport to="body">
      <div v-if="cmt.open" class="fixed inset-0 z-[2200] flex items-end justify-center bg-black/40 sm:items-center" @click.self="cmt.open = false">
        <div class="flex max-h-[85vh] w-full max-w-md flex-col rounded-t-2xl bg-[var(--ll-elevated)] shadow-xl sm:rounded-2xl">
          <div class="flex items-center justify-between border-b border-[var(--ll-border)] px-4 py-3">
            <h3 class="text-sm font-semibold">{{ t('gallery.comments') }}</h3>
            <button class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="cmt.open = false"><Icon name="close" :size="18" /></button>
          </div>
          <div class="flex flex-wrap gap-1 border-b border-[var(--ll-border)] px-4 py-2">
            <button v-for="e in REACTIONS" :key="e" class="rounded-full px-2 py-1 text-base transition" :class="cmt.mine === e ? 'bg-primary-500/15 ring-1 ring-primary-500' : 'hover:bg-black/[0.05] dark:hover:bg-white/10'" @click="toggleReaction(e)">
              {{ e }}<span v-if="cmt.reactions[e]" class="ml-0.5 text-xs text-[var(--ll-muted)]">{{ cmt.reactions[e] }}</span>
            </button>
          </div>
          <div class="flex-1 space-y-3 overflow-y-auto px-4 py-3">
            <p v-if="!cmt.list.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('gallery.comments_empty') }}</p>
            <div v-for="c in cmt.list" :key="c.id" class="group text-sm">
              <div class="flex items-baseline gap-2">
                <span class="font-medium">{{ c.author ?? '—' }}</span>
                <span class="text-xs text-[var(--ll-muted)]">{{ fmtDate(c.created_at) }}</span>
                <button v-if="c.mine" class="ml-auto text-[var(--ll-muted)] opacity-0 hover:text-red-600 group-hover:opacity-100" @click="removeComment(c.id)"><Icon name="delete" :size="14" /></button>
              </div>
              <p class="whitespace-pre-wrap break-words">{{ c.body }}</p>
            </div>
          </div>
          <form class="flex gap-2 border-t border-[var(--ll-border)] p-3" @submit.prevent="sendComment">
            <input v-model="cmt.draft" :placeholder="t('gallery.comment_ph')" class="min-w-0 flex-1 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-bg)] px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
            <Btn type="submit" variant="solid" size="sm" icon="send" :disabled="!cmt.draft.trim()" />
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fmtDate, fmtDateTime } from '@spa/lib/datetime';
import * as L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Btn, Icon } from '@spa/ui';
import LocationField from '@spa/components/LocationField.vue';
import { useGalleryStore, type Photo, type Album, type PhotoEdit, type Person, type Face, type ContactSuggestion, type ExifDetail, type PublicShareRow, type InternalShareRow, type SharedWithMeRow, type SharedPhoto } from '@spa/stores/gallery';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';
import { ApiError } from '@spa/api/client';

type Row = Photo;

const g = useGalleryStore();
const { success, error } = useToast();

const fileInput = ref<HTMLInputElement | null>(null);
const showTrash = ref(false);
const showArchive = ref(false);
const showMemories = ref(false);
const memoriesData = ref<import('@spa/stores/gallery').MemoriesResult | null>(null);
const memoriesLoading = ref(false);
async function toggleMemories() {
  if (showMemories.value) { showMemories.value = false; return; }
  if (showPeople.value) closePeople(); if (showDupes.value) closeDupes(); if (showShared.value) closeShared();
  showTrash.value = false; showArchive.value = false; searchActive.value = false;
  showMemories.value = true; viewer.value = -1; clearSelection();
  if (!memoriesData.value) { memoriesLoading.value = true; try { memoriesData.value = await g.memories(); } catch { error(t('common.error')); } finally { memoriesLoading.value = false; } }
}
// Open a memory's photos in the main grid (reuses the search-result lane).
function openMemory(photos: Photo[]) {
  searchResults.value = photos; searchActive.value = true; showMemories.value = false; viewer.value = -1;
}
function themeLabel(key: string): string { const l = t(`gallery.theme_${key}`); return l === `gallery.theme_${key}` ? key : l; }
function tripLabel(trip: { place: string | null; from: string }): string {
  return trip.place ?? new Date(trip.from).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
}
const viewMode = ref<'grid' | 'map'>('grid');
// Grid thumbnail size (columns) — fewer columns = larger thumbnails. Persisted.
const gridCols = ref<number>(Math.min(12, Math.max(2, Number(localStorage.getItem('ll_gallery_cols')) || 6)));
const gridStyle = computed(() => ({ gridTemplateColumns: `repeat(${gridCols.value}, minmax(0, 1fr))` }));
function setGridCols(n: number) { gridCols.value = Math.min(12, Math.max(2, n || 6)); localStorage.setItem('ll_gallery_cols', String(gridCols.value)); }
const trashPhotos = ref<Photo[]>([]);
const trashBusy = ref(false);
const albumId = ref<number | null>(null);
const albumMenu = ref(false);
const menuOpen = ref(false);
/**
 * The counts in the header.
 *
 * The timeline is paginated, so counting the loaded rows showed the page size —
 * "200 photos" for a library of eighteen thousand. The server counts the whole
 * filtered set; only the views that return everything at once (trash, search,
 * duplicates) are counted here.
 */
const mediaCounts = computed(() => {
  const showingWholeResult = showTrash.value || searchActive.value;
  if (!showingWholeResult && g.totals) return { ph: g.totals.images, vid: g.totals.videos };
  let ph = 0; let vid = 0;
  for (const p of current.value) { if (p.media_type === 'video') vid++; else ph++; }
  return { ph, vid };
});
const dlMenu = ref(false);

const selected = ref<Set<number>>(new Set());
let anchor = -1;
const hoverId = ref(-1);
const motionPlaying = ref(false);

const searchQuery = ref('');
const searchActive = ref(false);
const searchResults = ref<Photo[]>([]);
const showDupes = ref(false);
const dupeGroups = ref<{ photos: Photo[] }[]>([]);

// People / faces (opt-in face recognition)
const showPeople = ref(false);
const peopleList = ref<Person[]>([]);
const personView = ref<Person | null>(null);
// Face crops whose image 404'd (missing crop file) → fall back to a placeholder.
const brokenFaces = reactive<Record<number, boolean>>({});
const peopleGrid = computed(() => showPeople.value && !personView.value);
const viewerFaces = ref<Face[]>([]);

// LIVE photo list — never copied, so thumbnail/status patches from the poll
// reflect on the exact tile without rebuilding the whole grid.
const current = computed<Row[]>(() => {
  if (showTrash.value) return trashPhotos.value;
  return searchActive.value ? searchResults.value : g.photos;
});

// Per-row day/month header metadata, parallel to `current` (same index).
// Reads only the capture date → recomputes only when the set/order/dates
// change, NOT when a thumbnail settles.
interface RowMeta { day: string; header?: string; mk: string; monthStart?: string }
const rowMeta = computed<RowMeta[]>(() => {
  let last = ''; let lastMonth = '';
  return current.value.map((p) => {
    const iso = p.taken_at ?? p.created_at;
    const day = dayLabel(iso);
    const header = day !== last ? day : undefined; last = day;
    const mk = monthKey(iso);
    const monthStart = mk !== lastMonth ? mk : undefined; lastMonth = mk;
    return { day, header, mk, monthStart };
  });
});

function monthKey(iso: string | null): string {
  if (!iso) return '0000-00';
  try { const d = new Date(iso); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; } catch { return '0000-00'; }
}

// Right-rail date scrubber: unique months in display order (year shown once).
const scrubber = computed(() => {
  const out: { key: string; year: number; label: string; firstYear: boolean }[] = [];
  let lastYear = -1;
  for (const r of rowMeta.value) {
    if (!r.monthStart) continue;
    const [y, m] = r.mk.split('-').map((n) => Number(n));
    if (!y) continue;
    out.push({ key: r.mk, year: y, label: new Date(y, m - 1, 1).toLocaleDateString(undefined, { month: 'short' }), firstYear: y !== lastYear });
    lastYear = y;
  }
  return out;
});
const showScrubber = computed(() => viewMode.value === 'grid' && !showTrash.value && !searchActive.value && (scrubber.value.length > 3 || allMonths.value.length > 3));

// ---- Google-Photos-style scrubber ----------------------------------------
const railRef = ref<HTMLElement | null>(null);
const scrubHover = ref(false);
const scrubDrag = ref(false);
const scrubActive = computed(() => scrubHover.value || scrubDrag.value);
const thumbTop = ref(0);           // px from the rail's top edge
const scrubLabel = ref('');

// Full month histogram from the server (/gallery/dates) — the timeline is now
// keyset-paginated, so the scrubber can't derive the whole date range from the
// loaded rows. Newest-first { ym, count }.
const allMonths = ref<{ ym: string; count: number }[]>([]);
const loadDates = () => g.dates().then((m) => { allMonths.value = m; }).catch(() => { /* best-effort */ });

// Year jump ticks: one per distinct year in the full histogram, positioned by
// cumulative photo count (an estimate of where the year sits in the timeline).
// Clicking a tick jumps straight to that year via cursor_ym — no need to have
// scrolled/loaded that far. Covers the whole library regardless of what's loaded.
const yearTicks = computed(() => {
  const total = allMonths.value.reduce((s, m) => s + m.count, 0) || 1;
  const out: { year: number; ym: string; pct: number }[] = [];
  let cum = 0; let lastYear = -1;
  for (const m of allMonths.value) {
    const year = Number(m.ym.slice(0, 4));
    if (year !== lastYear && Number.isFinite(year)) { out.push({ year, ym: m.ym, pct: Math.min(98, (cum / total) * 100) }); lastYear = year; }
    cum += m.count;
  }
  return out;
});
function jumpYear(ym: string) {
  void g.jumpToMonth(ym).then(() => (document.scrollingElement || document.documentElement).scrollTo({ top: 0 }));
}

function scrollMetrics() {
  const el = (document.scrollingElement || document.documentElement) as HTMLElement;
  return { top: el.scrollTop, max: Math.max(1, el.scrollHeight - el.clientHeight) };
}
// Which month is currently at the top of the viewport → bubble label.
function currentMonthLabel(): string {
  const y = window.scrollY + 120;
  let hit: { label: string; year: number } | null = null;
  for (const m of scrubber.value) {
    const a = document.getElementById('g-m-' + m.key);
    if (!a) continue;
    if (a.offsetTop <= y) hit = m; else break;
  }
  const m = hit ?? scrubber.value[0];
  return m ? `${m.label} ${m.year}` : '';
}
let syncQueued = false;
function syncScrubber() {
  if (syncQueued) return;
  syncQueued = true;
  requestAnimationFrame(() => {
    syncQueued = false;
    const rail = railRef.value; if (!rail) return;
    const { top, max } = scrollMetrics();
    const f = Math.min(1, Math.max(0, top / max));
    thumbTop.value = f * rail.getBoundingClientRect().height;
    if (scrubActive.value) scrubLabel.value = currentMonthLabel();
  });
}
function scrubToClientY(clientY: number) {
  const rail = railRef.value; if (!rail) return;
  const b = rail.getBoundingClientRect();
  const f = Math.min(1, Math.max(0, (clientY - b.top) / b.height));
  const { max } = scrollMetrics();
  (document.scrollingElement || document.documentElement).scrollTo({ top: f * max });
  thumbTop.value = f * b.height;
  scrubLabel.value = currentMonthLabel();
}
function scrubStart(e: PointerEvent) {
  scrubDrag.value = true;
  try { railRef.value?.setPointerCapture(e.pointerId); } catch { /* ignore */ }

  scrubToClientY(e.clientY);
}
function scrubMove(e: PointerEvent) { if (scrubDrag.value) scrubToClientY(e.clientY); }
function scrubEnd(e: PointerEvent) {
  scrubDrag.value = false;
  try { railRef.value?.releasePointerCapture(e.pointerId); } catch { /* ignore */ }
}

// Day → photo-ids map (O(n), stable across thumb patches).
const dayGroups = computed(() => {
  const m = new Map<string, number[]>();
  for (const p of current.value) {
    const d = dayLabel(p.taken_at ?? p.created_at);
    const arr = m.get(d); if (arr) arr.push(p.id); else m.set(d, [p.id]);
  }
  return m;
});
// Set of days whose every photo is selected — recomputes on selection change
// only, so the header checkmarks are O(1) per render instead of O(days×photos).
const fullDays = computed(() => {
  const s = new Set<string>();
  for (const [d, ids] of dayGroups.value) {
    if (ids.length && ids.every((id) => selected.value.has(id))) s.add(d);
  }
  return s;
});
function selectDay(day: string) {
  const ids = dayGroups.value.get(day) ?? [];
  const s = new Set(selected.value);
  const allSel = ids.every((id) => s.has(id));
  for (const id of ids) { if (allSel) s.delete(id); else s.add(id); }
  selected.value = s;
}
const mapPhotos = computed(() => g.photos.filter((p) => p.lat !== null && p.lng !== null));
const viewer = ref(-1);
const viewerPhoto = computed(() => (viewer.value >= 0 ? current.value[viewer.value] ?? null : null));
const viewerDate = computed(() => { const p = viewerPhoto.value; return p ? fullDate(p.taken_at ?? p.created_at) : ''; });

const up = reactive({ active: false, done: 0, total: 0, name: '', frac: 0 });
const upPct = computed(() => (up.total ? Math.min(100, Math.round(((up.done + up.frac) / up.total) * 100)) : 0));
const dragDepth = ref(0);

const edit = reactive({ open: false, saving: false, id: 0, version: 0, date: '', time: '', place: '' as string, lat: null as number | null, lng: null as number | null, rotation: 0, flip_h: false, baseRotation: 0, baseFlip: false, preview: false });
const bulk = reactive({ open: false, saving: false, count: 0, rotate: 0, mirror: false, date: '', time: '', place: '' as string, lat: null as number | null, lng: null as number | null });

let thumbPoll: ReturnType<typeof setInterval> | null = null;
const route = useRoute();
const router = useRouter();
// Deep-open from global search (?open=<id>): open the lightbox for that photo.
function openPhotoById(id: number) {
  const idx = current.value.findIndex((p) => p.id === id);
  if (idx >= 0) viewer.value = idx;
}
watch(() => route.query.open, (v) => { const id = Number(v); if (id) void nextTick(() => openPhotoById(id)); });

// ---- Deep links: mirror the current view/album/person/open photo in the URL
// query so a reload or shared link lands on the same place. `restoring` gates
// the write-watch while we apply an incoming URL.
let restoring = false;
function buildQuery(): Record<string, string> {
  const q: Record<string, string> = {};
  if (showTrash.value) q.view = 'trash';
  else if (showArchive.value) q.view = 'archive';
  else if (showDupes.value) q.view = 'dupes';
  else if (showPeople.value) q.view = 'people';
  if (personView.value) q.person = String(personView.value.id);
  if (albumId.value !== null) q.album = String(albumId.value);
  const p = current.value[viewer.value];
  if (p) q.photo = String(p.id);
  return q;
}
watch([showTrash, showArchive, showDupes, showPeople, personView, albumId, viewer], () => {
  if (restoring) return;
  const q = buildQuery();
  const keys = ['view', 'person', 'album', 'photo'];
  const cur: Record<string, string> = {};
  for (const k of keys) { const v = route.query[k]; if (typeof v === 'string') cur[k] = v; }
  if (JSON.stringify(q) !== JSON.stringify(cur)) void router.replace({ query: q });
});
// Apply the URL query on load: restore the view, then open the deep-linked photo.
async function applyRoute() {
  restoring = true;
  const q = route.query;
  albumId.value = q.album ? Number(q.album) : null;
  try {
    if (q.view === 'trash') await toggleTrash();
    else if (q.view === 'archive') await toggleArchive();
    else if (q.view === 'dupes') await openDupes();
    else if (q.view === 'people') {
      await openPeople();
      if (q.person) { const p = peopleList.value.find((x) => x.id === Number(q.person)); if (p) await openPerson(p); }
    } else {
      await (albumId.value !== null ? refresh() : g.load());
    }
  } catch { /* fall back to empty view */ }
  finally { restoring = false; }
  const pid = Number(q.photo || q.open);
  if (pid) await nextTick(() => openPhotoById(pid));
}

const loadSentinel = ref<HTMLElement | null>(null);
let moreObserver: IntersectionObserver | null = null;
// Load the next timeline page when the sentinel near the bottom scrolls into
// view — but only in the plain timeline (not search/trash/album-person grids,
// which are their own sets). Keyset cursor drives it; no-op when exhausted.
function maybeLoadMore() {
  if (searchActive.value || showTrash.value || showArchive.value || showDupes.value || showPeople.value) return;
  if (g.nextCursor === null || g.loadingMore) return;
  void g.loadMore();
}

onMounted(() => {
  void applyRoute();
  void loadDates();
  void g.loadAlbums();
  window.addEventListener('keydown', onKey); window.addEventListener('focus', onFocus);
  window.addEventListener('scroll', syncScrubber, { passive: true });
  window.addEventListener('resize', onScrubResize);
  void nextTick(() => { syncScrubber(); });
  moreObserver = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) maybeLoadMore(); }, { rootMargin: '800px' });
  void nextTick(() => { if (loadSentinel.value) moreObserver?.observe(loadSentinel.value); });
  // Thumbnails are generated by a worker after upload; while any are still
  // pending, poll so the grid swaps the spinner for the image once ready.
  thumbPoll = setInterval(() => {
    if (!showTrash.value && !showArchive.value && !showMemories.value && !searchActive.value && !showDupes.value && !showPeople.value && !up.active && !edit.open && g.photos.some((p) => !p.thumb || p.status === 'processing')) void g.mergeData();
  }, 4000);
});
onUnmounted(() => {
  window.removeEventListener('keydown', onKey); window.removeEventListener('focus', onFocus);
  window.removeEventListener('scroll', syncScrubber);
  window.removeEventListener('resize', onScrubResize);
  if (thumbPoll) clearInterval(thumbPoll);
  moreObserver?.disconnect();
  destroyMap();
  destroyExifMap();
});
function onScrubResize() { syncScrubber(); }
// Re-attach the infinite-scroll observer whenever the sentinel remounts (e.g. an
// empty album, then photos appear, or switching back to the timeline grid).
watch(loadSentinel, (el) => { if (el && moreObserver) { moreObserver.disconnect(); moreObserver.observe(el); } });
// Re-measure tick positions when the timeline changes (photos load / album switch).
watch(() => [g.photos.length, scrubber.value.length, viewMode.value], () => {
  void nextTick(() => { syncScrubber(); });
});
watch(scrubActive, (a) => { if (a) { scrubLabel.value = currentMonthLabel(); } });
function onFocus() { if (!document.hidden && !up.active && !showTrash.value && !showArchive.value && !searchActive.value && !showPeople.value) void g.load(albumId.value ?? undefined); }

function dayLabel(iso: string | null): string {
  if (!iso) return '';
  return fmtDate(iso);
}
function fullDate(iso: string | null): string {
  if (!iso) return '';
  return fmtDateTime(iso);
}
function fmtDuration(sec: number): string {
  const s = Math.max(0, Math.round(sec));
  const m = Math.floor(s / 60);
  return `${m}:${String(s % 60).padStart(2, '0')}`;
}
function isEdited(p: Photo | null): boolean { return !!p && (p.rotation !== 0 || p.flip_h); }
function transformStyle(p: Photo) { return { transform: `rotate(${p.rotation}deg) scaleX(${p.flip_h ? -1 : 1})` }; }
// The preview image is already baked at the photo's CURRENT orientation, so the
// live edit preview applies only the pending DELTA from that baseline.
const previewStyle = computed(() => {
  const dr = ((edit.rotation - edit.baseRotation) % 360 + 360) % 360;
  return { transform: `rotate(${dr}deg) scaleX(${edit.flip_h !== edit.baseFlip ? -1 : 1})` };
});

function pick() { fileInput.value?.click(); }
function onPick(e: Event) { const l = (e.target as HTMLInputElement).files; if (l) void uploadList(l); (e.target as HTMLInputElement).value = ''; }
function onThumbError(e: Event) { (e.target as HTMLImageElement).style.visibility = 'hidden'; }

function hasFiles(e: DragEvent) { return Array.from(e.dataTransfer?.types ?? []).includes('Files'); }
function onDragEnter(e: DragEvent) { if (hasFiles(e)) dragDepth.value++; }
function onDragLeave(e: DragEvent) { if (hasFiles(e)) dragDepth.value = Math.max(0, dragDepth.value - 1); }
function onDrop(e: DragEvent) { dragDepth.value = 0; if (!hasFiles(e)) return; const l = e.dataTransfer?.files; if (l && l.length) void uploadList(l); }

function baseName(name: string): string { return name.replace(/\.[^.]+$/, '').toLowerCase(); }
function isMotionFile(f: File): boolean { return f.type.startsWith('video/') || /\.(mov|mp4|m4v|qt)$/i.test(f.name); }
// Not every picture announces itself: a browser that does not know HEIC/HEIF
// hands back an empty File.type, and filtering on the type alone dropped those
// files from the upload without a word — the very format a Live Photo still
// comes in. The extension is the fallback, exactly as it already is for clips.
function isImageFile(f: File): boolean {
  return f.type.startsWith('image/') || (f.type === '' && /\.(heic|heif|jpe?g|png|webp|gif|avif|tiff?|bmp)$/i.test(f.name));
}

async function uploadList(list: FileList) {
  const all = Array.from(list);
  const images = all.filter(isImageFile);
  const motions = all.filter(isMotionFile);
  if (!images.length && !motions.length) return;
  Object.assign(up, { active: true, done: 0, total: images.length + motions.length, name: '', frac: 0 });
  // Pair a Live Photo's .MOV to its still by base name (IMG_1234.HEIC ↔ IMG_1234.MOV).
  // Seed with the existing library so a clip uploaded in a later batch still merges.
  const idByBase = new Map<string, number>();
  for (const p of g.photos) idByBase.set(baseName(p.name), p.id);
  let dupes = 0;
  try {
    for (const f of images) {
      up.name = f.name; up.frac = 0;
      const r = await g.upload(f, (fr) => { up.frac = fr; });
      if (r.duplicate) dupes++;
      idByBase.set(baseName(f.name), r.photo.id);
      up.frac = 0; up.done++;
    }
    for (const f of motions) {
      up.name = f.name; up.frac = 0;
      const id = idByBase.get(baseName(f.name));
      if (id !== undefined) {
        // Video paired to a just-uploaded/existing still → Apple Live Photo clip.
        await g.attachMotion(id, f, (fr) => { up.frac = fr; });
      } else {
        // Standalone video → upload as its own entry (processed on the worker).
        const r = await g.upload(f, (fr) => { up.frac = fr; });
        if (r.duplicate) dupes++;
      }
      up.frac = 0; up.done++;
    }
    await refresh();
    await g.loadAlbums();
    success(t('common.saved'));
    if (dupes > 0) success(t('gallery.dupes_skipped', { n: String(dupes) }));
  } catch { error(t('common.error')); } finally { up.active = false; }
}
function refresh() { return g.load(albumId.value ?? undefined).then(() => loadDates()); }
function setView(m: 'grid' | 'map') { if (showPeople.value) closePeople(); viewMode.value = m; if (m === 'map') void nextTick().then(syncMap); }

// ---- Semantic search (CLIP) ----
async function doSearch() {
  const q = searchQuery.value.trim();
  if (!q) { clearSearch(); return; }
  clearSelection();
  try { searchResults.value = await g.search(q); searchActive.value = true; }
  catch { error(t('common.error')); }
}
function clearSearch() { searchActive.value = false; searchResults.value = []; searchQuery.value = ''; }

// ---- Near-duplicates (CLIP) ----
async function openDupes() {
  if (showPeople.value) closePeople();
  showDupes.value = true; clearSearch(); clearSelection(); viewer.value = -1;
  try { dupeGroups.value = await g.duplicates(); } catch { error(t('common.error')); }
}
function closeDupes() { showDupes.value = false; dupeGroups.value = []; }
async function trashDupeGroup(gi: number) {
  const grp = dupeGroups.value[gi];
  if (!grp) return;
  const ids = grp.photos.slice(1).map((p) => p.id); // keep the first
  if (!ids.length) return;
  try { await g.bulkDestroy(ids); dupeGroups.value = await g.duplicates(); await refresh(); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function trashDupeOne(gi: number, id: number) {
  try { await g.bulkDestroy([id]); dupeGroups.value = await g.duplicates(); await refresh(); } catch { error(t('common.error')); }
}

// ---- People / faces (opt-in face recognition) ----
const personSort = ref<'asc' | 'desc'>('desc');
async function openPeople() {
  showPeople.value = true; personView.value = null; clearSearch(); closeDupes(); clearSelection(); viewer.value = -1;
  try { peopleList.value = await g.people(); } catch { error(t('common.error')); }
}
function closePeople() { showPeople.value = false; personView.value = null; peopleList.value = []; }
async function openPerson(p: Person) {
  personView.value = p; personSort.value = 'desc';
  try { await g.browsePerson(p.id, personSort.value); } catch { error(t('common.error')); }
}
async function togglePersonSort() {
  const p = personView.value; if (!p) return;
  personSort.value = personSort.value === 'desc' ? 'asc' : 'desc';
  try { await g.browsePerson(p.id, personSort.value); } catch { error(t('common.error')); }
}
function backToPeople() { personView.value = null; }
async function deletePerson() {
  const p = personView.value; if (!p) return;
  if (!(await confirmAsk(t('gallery.person_delete_confirm')))) return;
  try { await g.deletePerson(p.id); success(t('common.saved')); personView.value = null; peopleList.value = await g.people(); } catch { error(t('common.error')); }
}
function personLabel(p: Person): string { return p.name ?? t('gallery.person_unknown'); }

// Merge modal — pick a NAMED person to merge the current one into (with search).
const mergeM = reactive({ open: false, query: '' });
function openMerge() { mergeM.open = true; mergeM.query = ''; }
const mergeCandidates = computed(() => {
  const cur = personView.value?.id;
  const q = mergeM.query.trim().toLowerCase();
  return peopleList.value.filter((p) => p.id !== cur && p.name && (q === '' || p.name.toLowerCase().includes(q)));
});
async function doMerge(into: Person) {
  const from = personView.value; if (!from || into.id === from.id) return;
  try { await g.mergePeople(from.id, into.id); success(t('common.saved')); mergeM.open = false; personView.value = null; peopleList.value = await g.people(); }
  catch { error(t('common.error')); }
}

// Naming modal (free text + address-book autocomplete), used for a person or a face.
const nameM = reactive({
  open: false, saving: false, target: 'person' as 'person' | 'face',
  personId: 0, face: null as Face | null, query: '', suggestions: [] as ContactSuggestion[],
});
let nameDebounce: ReturnType<typeof setTimeout> | null = null;
// Is the modal targeting a person that is already linked to a contact?
const nameLinked = computed(() => nameM.target === 'person' && !!personView.value?.contact_id);
async function loadNameSuggestions(q: string) {
  if (q.trim().length < 2) { nameM.suggestions = []; return; }
  try { nameM.suggestions = await g.nameSuggest(q.trim()); } catch { nameM.suggestions = []; }
}
function openNamePerson() {
  const p = personView.value; if (!p) return;
  Object.assign(nameM, { open: true, target: 'person', personId: p.id, face: null, query: p.name ?? '', suggestions: [] });
  // Seed contact suggestions on open so an already-named person can be linked
  // to a contact right away (no need to retype the name first).
  void loadNameSuggestions(p.name ?? '');
}
function openNameFace(f: Face) {
  Object.assign(nameM, { open: true, target: 'face', personId: 0, face: f, query: f.person_name ?? '', suggestions: [] });
  void loadNameSuggestions(f.person_name ?? '');
}
function onNameInput() {
  if (nameDebounce) clearTimeout(nameDebounce);
  const q = nameM.query.trim();
  if (q.length < 2) { nameM.suggestions = []; return; }
  nameDebounce = setTimeout(() => { void loadNameSuggestions(q); }, 200);
}
async function pickSuggestion(s: ContactSuggestion) {
  nameM.saving = true;
  try {
    if (nameM.target === 'person') {
      // Preserve an existing custom name when linking retroactively; only adopt
      // the contact's name when the person was still unnamed.
      const keepName = personView.value?.name?.trim();
      personView.value = await g.updatePerson(nameM.personId, keepName ? { contact_id: s.id, name: keepName } : { contact_id: s.id });
    } else if (nameM.face) {
      await g.assignFace(nameM.face.id, { contact_id: s.id });
      nameM.face.person_name = s.name;
    }
    nameM.open = false; success(t('common.saved'));
    await refreshPeople();
  } catch { error(t('common.error')); } finally { nameM.saving = false; }
}
async function saveName() {
  const name = nameM.query.trim(); if (!name) return;
  nameM.saving = true;
  try {
    if (nameM.target === 'person') {
      // Rename only — leave any existing contact link untouched (omit contact_id).
      personView.value = await g.updatePerson(nameM.personId, { name });
    } else if (nameM.face) {
      await g.assignFace(nameM.face.id, { name });
      nameM.face.person_name = name;
    }
    nameM.open = false; success(t('common.saved'));
    await refreshPeople();
  } catch { error(t('common.error')); } finally { nameM.saving = false; }
}
async function unlinkContact() {
  if (nameM.target !== 'person') return;
  nameM.saving = true;
  try {
    personView.value = await g.updatePerson(nameM.personId, { contact_id: null });
    nameM.open = false; success(t('common.saved'));
    await refreshPeople();
  } catch { error(t('common.error')); } finally { nameM.saving = false; }
}
// Keep the people grid (and merge candidates) in sync after a name/link change.
async function refreshPeople() { try { peopleList.value = await g.people(); } catch { /* keep stale */ } }

// Lightbox face chips
async function loadViewerFaces() {
  const p = viewerPhoto.value;
  viewerFaces.value = [];
  if (!p || p.media_type !== 'image') return;
  try { viewerFaces.value = await g.photoFaces(p.id); } catch { /* face ML off → no chips */ }
}
async function setCoverFromChip(f: Face) {
  if (f.person_id === null) return;
  try { await g.setFaceCover(f.person_id, f.id); success(t('common.saved')); await refreshPeople(); } catch { error(t('common.error')); }
}
async function hideFaceChip(f: Face) {
  try { await g.hideFace(f.id); viewerFaces.value = viewerFaces.value.filter((x) => x.id !== f.id); } catch { error(t('common.error')); }
}
watch(viewer, () => { void loadViewerFaces(); });

// ---- Lightbox info sidebar (full EXIF + mini-map) ----
const showInfo = ref(false);
const viewerExif = ref<ExifDetail | null>(null);
const exifLoading = ref(false);
const exifMapEl = ref<HTMLElement | null>(null);
let exifMap: L.Map | null = null;

// Friendly section titles; unknown sections fall back to their raw name.
const EXIF_TITLES: Record<string, string> = {
  IFD0: t('gallery.info_sec_image'), EXIF: t('gallery.info_sec_capture'), GPS: t('gallery.info_sec_gps'),
  COMPUTED: t('gallery.info_sec_computed'), INTEROP: t('gallery.info_sec_interop'), THUMBNAIL: t('gallery.info_sec_thumb'),
};

function humanSize(b: number): string {
  if (b < 1024) return `${b} B`;
  if (b < 1048576) return `${(b / 1024).toFixed(1)} KB`;
  return `${(b / 1048576).toFixed(2)} MB`;
}

const overviewRows = computed<{ k: string; v: string }[]>(() => {
  const e = viewerExif.value;
  if (!e) return [];
  const rows: { k: string; v: string }[] = [{ k: t('gallery.info_filename'), v: e.name }];
  if (e.mime) rows.push({ k: 'MIME', v: e.mime });
  rows.push({ k: t('gallery.info_size'), v: humanSize(e.size) });
  if (e.width && e.height) rows.push({ k: t('gallery.info_dimensions'), v: `${e.width} × ${e.height}` });
  if (e.taken_at) rows.push({ k: t('gallery.info_taken'), v: fullDate(e.taken_at) });
  if (e.camera) rows.push({ k: t('gallery.info_camera'), v: e.camera });
  if (e.place) rows.push({ k: t('gallery.info_place'), v: e.place });
  return rows;
});

// Noise / redundant keys hidden from the sidebar (pointers, version blobs, the
// COMPUTED html string, and the GPS rationals already shown as the map + decimal).
const EXIF_NOISE = new Set([
  'html', 'ByteOrderMotorola', 'IsColor', 'ExifVersion', 'FlashPixVersion', 'FlashpixVersion',
  'ComponentsConfiguration', 'YCbCrPositioning', 'Exif_IFD_Pointer', 'GPS_IFD_Pointer',
  'Interoperability_IFD_Pointer', 'InteroperabilityIndex', 'InteroperabilityVersion',
  'GPSLatitude', 'GPSLongitude', 'GPSLatitudeRef', 'GPSLongitudeRef', 'GPSVersion', 'GPSVersionID',
  'MakerNote', 'UserComment', 'FileSource', 'SceneType', 'Thumbnail.JPEGInterchangeFormat', 'Thumbnail.JPEGInterchangeFormatLength',
]);
const SECTION_ORDER = ['IFD0', 'EXIF', 'GPS', 'COMPUTED', 'INTEROP', 'THUMBNAIL'];

// Human-readable key label: split camelCase, keep acronyms tight (ISO/GPS/FNumber).
function prettyKey(k: string): string {
  return k
    .replace(/_/g, ' ')
    .replace(/([a-z\d])([A-Z])/g, '$1 $2')
    .replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
    .trim();
}

// Units / friendly values for well-known tags; falls back to the raw string.
function formatExif(key: string, v: string): string {
  const num = Number(v);
  switch (key) {
    case 'ExposureTime': return `${v} s`;
    case 'ShutterSpeedValue': return `${v}`;
    case 'FNumber': case 'ApertureFNumber': case 'ApertureValue': case 'MaxApertureValue': return `ƒ/${v}`;
    case 'FocalLength': case 'FocalLengthIn35mmFilm': return `${v} mm`;
    case 'ISOSpeedRatings': case 'PhotographicSensitivity': case 'ExposureIndex': return `ISO ${v}`;
    case 'ExposureBiasValue': return `${v} EV`;
    case 'GPSAltitude': return `${v} m`;
    case 'ColorSpace': return v === '1' ? 'sRGB' : v === '65535' ? 'Uncalibrated' : v;
    case 'Orientation': return ({ '1': 'Normal', '3': '180°', '6': '90° CW', '8': '90° CCW' } as Record<string, string>)[v] ?? v;
    case 'ResolutionUnit': return v === '2' ? 'inch' : v === '3' ? 'cm' : v;
    default: return Number.isFinite(num) && key.endsWith('Value') ? String(num) : v;
  }
}

const exifSections = computed<{ sec: string; title: string; rows: { k: string; v: string }[] }[]>(() => {
  const e = viewerExif.value;
  if (!e?.exif) return [];
  return Object.entries(e.exif)
    .map(([sec, rows]) => ({
      sec,
      title: EXIF_TITLES[sec] ?? sec,
      rows: Object.entries(rows)
        .filter(([k]) => !EXIF_NOISE.has(k))
        .map(([k, v]) => ({ k: prettyKey(k), v: formatExif(k, v) })),
    }))
    .filter((s) => s.rows.length > 0)
    .sort((a, b) => {
      const ia = SECTION_ORDER.indexOf(a.sec); const ib = SECTION_ORDER.indexOf(b.sec);
      return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
    });
});

function destroyExifMap() { if (exifMap) { exifMap.remove(); exifMap = null; } }

function initExifMap() {
  const e = viewerExif.value;
  destroyExifMap();
  if (!showInfo.value || !e || e.lat == null || e.lng == null || !exifMapEl.value) return;
  exifMap = L.map(exifMapEl.value, { attributionControl: true, scrollWheelZoom: false });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(exifMap);
  // Custom SVG pin — Leaflet's default marker PNG asset 404s under the bundler.
  const pin = L.divIcon({
    className: '',
    html: '<svg width="30" height="42" viewBox="0 0 24 34" xmlns="http://www.w3.org/2000/svg"><path d="M12 0C5.4 0 0 5.4 0 12c0 8.5 12 22 12 22s12-13.5 12-22C24 5.4 18.6 0 12 0z" fill="#6750a4" stroke="#fff" stroke-width="1.5"/><circle cx="12" cy="12" r="4.5" fill="#fff"/></svg>',
    iconSize: [30, 42], iconAnchor: [15, 42],
  });
  L.marker([e.lat, e.lng], { icon: pin }).addTo(exifMap);
  exifMap.setView([e.lat, e.lng], 14);
  setTimeout(() => exifMap?.invalidateSize(), 60);
}

async function loadViewerExif() {
  const p = viewerPhoto.value;
  if (!p) { viewerExif.value = null; destroyExifMap(); return; }
  exifLoading.value = true;
  try { viewerExif.value = await g.loadExif(p.id); } catch { viewerExif.value = null; }
  finally { exifLoading.value = false; }
  await nextTick();
  initExifMap();
}

function toggleInfo() { showInfo.value = !showInfo.value; if (showInfo.value) void loadViewerExif(); else destroyExifMap(); }

watch(viewer, () => {
  if (showInfo.value) void loadViewerExif();
  else { viewerExif.value = null; destroyExifMap(); }
});

// ---- Sharing (owner side) ----
const share = reactive<{
  open: boolean; albumId: number | null; albumName: string; role: 'viewer' | 'editor';
  public: PublicShareRow | null; internal: InternalShareRow[]; uploadLinks: import('@spa/stores/gallery').UploadLinkRow[]; email: string; busy: boolean;
}>({ open: false, albumId: null, albumName: '', role: 'viewer', public: null, internal: [], uploadLinks: [], email: '', busy: false });

async function refreshShareState() {
  try {
    const r = await g.loadShares();
    share.public = share.albumId ? (r.public.find((p) => p.album_id === share.albumId) ?? null) : null;
    share.internal = r.internal.filter((s) => (share.albumId ? s.album_id === share.albumId : s.album_id === null));
    share.uploadLinks = (r.upload_links ?? []).filter((l) => l.album_id === share.albumId);
  } catch { error(t('common.error')); }
}
function openShare(a: Album) { share.open = true; share.albumId = a.id; share.albumName = a.name; share.email = ''; share.role = 'viewer'; void refreshShareState(); }
function openLibraryShare() { share.open = true; share.albumId = null; share.albumName = ''; share.email = ''; share.role = 'viewer'; void refreshShareState(); }
async function createPublic() {
  if (!share.albumId) return;
  share.busy = true;
  try { share.public = await g.createPublicShare({ album_id: share.albumId }); } catch { error(t('common.error')); } finally { share.busy = false; }
}
async function removePublic() {
  if (!share.public) return;
  try { await g.deletePublicShare(share.public.id); share.public = null; } catch { error(t('common.error')); }
}
async function setAllowDownload(v: boolean) {
  if (!share.public) return;
  try { share.public = await g.updatePublicShare(share.public.id, { allow_download: v }); } catch { error(t('common.error')); }
}
async function togglePassword(on: boolean) {
  if (!share.public) return;
  try {
    if (on) {
      const pw = await promptAsk(t('gallery.set_password'), {});
      if (!pw) { await refreshShareState(); return; }
      share.public = await g.updatePublicShare(share.public.id, { password: pw });
    } else {
      share.public = await g.updatePublicShare(share.public.id, { clear_password: true });
    }
  } catch { error(t('common.error')); }
}
async function addInternal() {
  if (!share.email.trim()) return;
  share.busy = true;
  try { await g.shareInternal({ email: share.email.trim(), album_id: share.albumId, role: share.albumId ? share.role : 'viewer' }); share.email = ''; await refreshShareState(); success(t('common.saved')); }
  catch (e) { error(e instanceof ApiError && e.status === 422 ? t('gallery.recipient_invalid') : t('common.error')); }
  finally { share.busy = false; }
}
async function removeInternal(id: number) {
  try { await g.deleteInternalShare(id); await refreshShareState(); } catch { error(t('common.error')); }
}
async function addUploadLink() {
  if (!share.albumId) return;
  share.busy = true;
  try { await g.createUploadLink({ album_id: share.albumId }); await refreshShareState(); success(t('common.saved')); }
  catch { error(t('common.error')); } finally { share.busy = false; }
}
async function removeUploadLink(id: number) {
  try { await g.deleteUploadLink(id); await refreshShareState(); } catch { error(t('common.error')); }
}
async function copyUploadLink(token: string) {
  try { await navigator.clipboard.writeText(g.uploadLinkUrl(token)); success(t('common.copied')); } catch { error(t('common.error')); }
}
async function copyShareLink() {
  if (!share.public) return;
  try { await navigator.clipboard.writeText(g.publicShareUrl(share.public.token)); success(t('common.copied')); } catch { error(t('common.error')); }
}

// ---- Shared with me (recipient side) ----
const showShared = ref(false);
const sharedList = ref<SharedWithMeRow[]>([]);
const sharedView = ref<SharedWithMeRow | null>(null);
const sharedPhotos = ref<SharedPhoto[]>([]);
const sharedViewer = ref(-1);
async function openShared() {
  showShared.value = true; sharedView.value = null; sharedViewer.value = -1;
  if (showPeople.value) closePeople(); if (showDupes.value) closeDupes();
  try { sharedList.value = await g.sharedWithMe(); } catch { error(t('common.error')); }
}
function closeShared() { showShared.value = false; sharedView.value = null; sharedViewer.value = -1; }
// ---- Comments + reactions ----
const REACTIONS = ['❤️', '👍', '😂', '😮', '😢', '🔥'];
const cmt = reactive<{ open: boolean; photo: number; list: import('@spa/stores/gallery').Comment[]; reactions: Record<string, number>; mine: string | null; draft: string }>({ open: false, photo: 0, list: [], reactions: {}, mine: null, draft: '' });
async function openComments(photo: number) {
  cmt.open = true; cmt.photo = photo; cmt.list = []; cmt.reactions = {}; cmt.mine = null; cmt.draft = '';
  try { const r = await g.comments(photo); cmt.list = r.comments; cmt.reactions = r.reactions; cmt.mine = r.my_reaction; } catch { error(t('common.error')); }
}
async function sendComment() {
  const body = cmt.draft.trim(); if (!body) return;
  try { await g.addComment(cmt.photo, body); cmt.draft = ''; const r = await g.comments(cmt.photo); cmt.list = r.comments; } catch { error(t('common.error')); }
}
async function removeComment(id: number) {
  try { await g.deleteComment(id); cmt.list = cmt.list.filter((c) => c.id !== id); } catch { error(t('common.error')); }
}
async function toggleReaction(emoji: string) {
  try { const r = await g.react(cmt.photo, cmt.mine === emoji ? null : emoji); const fresh = await g.comments(cmt.photo); cmt.reactions = fresh.reactions; cmt.mine = r.my_reaction; } catch { error(t('common.error')); }
}

const sharedCanContribute = ref(false);
const sharedUploading = ref(false);
const contributeInput = ref<HTMLInputElement | null>(null);
async function openSharedShare(s: SharedWithMeRow) {
  sharedView.value = s; sharedViewer.value = -1; sharedCanContribute.value = false;
  try { const r = await g.browseShared(s.id); sharedPhotos.value = r.photos; sharedCanContribute.value = !!r.can_contribute; } catch { error(t('common.error')); }
}
function pickContribute() { contributeInput.value?.click(); }
async function onContribute(e: Event) {
  const input = e.target as HTMLInputElement;
  const files = Array.from(input.files ?? []); input.value = '';
  const s = sharedView.value; if (!s || !files.length) return;
  sharedUploading.value = true;
  let ok = 0;
  for (const f of files) { try { await g.contributeShared(s.id, f); ok++; } catch { /* count below */ } }
  sharedUploading.value = false;
  if (ok) { success(t('common.saved')); try { sharedPhotos.value = (await g.browseShared(s.id)).photos; } catch { /* keep */ } }
  if (ok < files.length) error(t('common.error'));
}
function sharedStep(d: number) {
  const n = sharedPhotos.value.length; if (!n) { sharedViewer.value = -1; return; }
  sharedViewer.value = (sharedViewer.value + d + n) % n;
}

// ---- Multi-select ----
function selectAlbum(id: number | null) { albumId.value = id; clearSelection(); clearSearch(); void refresh(); }
function clearSelection() { selected.value = new Set(); anchor = -1; albumMenu.value = false; }
function toggle(id: number) { const s = new Set(selected.value); if (s.has(id)) s.delete(id); else s.add(id); selected.value = s; }
function toggleAt(i: number, p: Row) { toggle(p.id); anchor = i; }
function selectRange(a: number, b: number) {
  const [lo, hi] = a < b ? [a, b] : [b, a];
  const s = new Set(selected.value);
  for (let i = lo; i <= hi; i++) { const p = current.value[i]; if (p) s.add(p.id); }
  selected.value = s;
}
function onTileClick(e: MouseEvent, i: number, p: Row) {
  if (e.shiftKey && anchor >= 0) { selectRange(anchor, i); return; }
  if (e.ctrlKey || e.metaKey) { toggle(p.id); anchor = i; return; }
  if (selected.value.size > 0) { toggle(p.id); anchor = i; return; }
  openViewer(i);
}
async function bulkTrash() {
  const ids = [...selected.value];
  if (!ids.length || !await confirmAsk(t('gallery.bulk_delete_confirm', { n: String(ids.length) }), { danger: true })) return;
  try { await g.bulkDestroy(ids); clearSelection(); await refresh(); await g.loadAlbums(); success(t('common.saved')); } catch { error(t('common.error')); }
}

// ---- Albums ----
async function newAlbum() {
  const name = (await promptAsk(t('gallery.album_name')))?.trim();
  if (!name) return;
  try { await g.createAlbum(name); await g.loadAlbums(); } catch { error(t('common.error')); }
}
async function newAlbumWithSelection() {
  const name = (await promptAsk(t('gallery.album_name')))?.trim();
  if (!name) return;
  try { const r = await g.createAlbum(name); await g.addToAlbum(r.album.id, [...selected.value]); await g.loadAlbums(); clearSelection(); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function renameAlbum(a: Album) {
  const name = (await promptAsk(t('gallery.album_name'), { value: a.name }))?.trim();
  if (!name || name === a.name) return;
  try { await g.renameAlbum(a.id, name); await g.loadAlbums(); } catch { error(t('common.error')); }
}
async function deleteAlbum(a: Album) {
  if (!await confirmAsk(t('gallery.delete_album_confirm'), { danger: true })) return;
  try { await g.deleteAlbum(a.id); if (albumId.value === a.id) selectAlbum(null); await g.loadAlbums(); } catch { error(t('common.error')); }
}
async function addSelectedToAlbum(a: Album) {
  try { await g.addToAlbum(a.id, [...selected.value]); await g.loadAlbums(); clearSelection(); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function removeSelectedFromAlbum() {
  if (albumId.value === null) return;
  try { await g.removeFromAlbum(albumId.value, [...selected.value]); clearSelection(); await refresh(); await g.loadAlbums(); } catch { error(t('common.error')); }
}

// ---- Lightbox ----
/**
 * Fold Live Photo pairs that already sit in the library as two tiles (a still
 * and its short clip) into one entry. New uploads are paired on the way in;
 * this is for everything that landed before that, or in two separate batches.
 *
 * The clip is not deleted — its bytes become the live part of the photo. That is
 * what the confirmation says, because "merge" and "delete the video" would feel
 * the same from the outside and only one of them is true.
 */
const pairingLive = ref(false);
async function pairLivePhotos() {
  if (!await confirmAsk(t('gallery.pair_live_confirm'))) return;
  pairingLive.value = true;
  try {
    const { merged } = await g.pairLivePhotos();
    if (merged > 0) { await g.load(); success(t('gallery.pair_live_done', { count: String(merged) })); }
    else success(t('gallery.pair_live_none'));
  } catch { error(t('common.error')); } finally { pairingLive.value = false; }
}

function openViewer(i: number) { viewer.value = i; dlMenu.value = false; motionPlaying.value = false; }
function step(d: number) {
  if (viewer.value < 0) return;
  const n = current.value.length;
  if (!n) { viewer.value = -1; return; }
  viewer.value = (viewer.value + d + n) % n;
  dlMenu.value = false; motionPlaying.value = false;
}
function onKey(e: KeyboardEvent) {
  if (edit.open) return;
  if (viewer.value < 0) return;
  if (e.key === 'Escape') viewer.value = -1;
  else if (e.key === 'ArrowLeft') step(-1);
  else if (e.key === 'ArrowRight') step(1);
}
async function onFav() {
  const p = viewerPhoto.value; if (!p) return;
  const next = !p.favorite;
  try { await g.favorite(p.id, next); p.favorite = next; } catch { error(t('common.error')); }
}
async function onDelete() {
  const p = viewerPhoto.value; if (!p) return;
  if (!await confirmAsk(t('gallery.delete_confirm'), { danger: true })) return;
  try { await g.destroy(p.id); viewer.value = -1; await refresh(); await g.loadAlbums(); success(t('common.saved')); } catch { error(t('common.error')); }
}

// ---- Edit ----
function openEdit() {
  const p = viewerPhoto.value; if (!p) return;
  const iso = p.taken_at ?? '';
  Object.assign(edit, {
    open: true, saving: false, id: p.id, version: p.version,
    date: iso ? iso.slice(0, 10) : '', time: iso ? iso.slice(11, 16) : '',
    place: p.place ?? '', lat: p.lat, lng: p.lng, rotation: p.rotation, flip_h: p.flip_h,
    baseRotation: p.rotation, baseFlip: p.flip_h, preview: p.preview,
  });
  dlMenu.value = false;
}
function rotate(delta: number) { edit.rotation = (((edit.rotation + delta) % 360) + 360) % 360; }

// ---- Bulk edit (selected photos) ----
function openBulkEdit() {
  Object.assign(bulk, { open: true, saving: false, count: selected.value.size, rotate: 0, mirror: false, date: '', time: '', place: '', lat: null, lng: null });
}
async function saveBulkEdit() {
  const ids = [...selected.value];
  if (!ids.length) { bulk.open = false; return; }
  bulk.saving = true;
  const takenAt = bulk.date ? `${bulk.date} ${bulk.time || '00:00'}:00` : null;
  const setPlace = bulk.place.trim() !== '' || bulk.lat !== null;
  try {
    for (const id of ids) {
      const p = g.photos.find((x) => x.id === id);
      if (!p) continue;
      const patch: PhotoEdit = {};
      if (bulk.rotate) patch.rotation = (p.rotation + bulk.rotate) % 360;
      if (bulk.mirror) patch.flip_h = !p.flip_h;
      if (takenAt) patch.taken_at = takenAt;
      if (setPlace) { patch.place = bulk.place || null; patch.lat = bulk.lat; patch.lng = bulk.lng; }
      if (Object.keys(patch).length) await g.update(id, patch);
    }
    bulk.open = false; clearSelection();
    await refresh();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { bulk.saving = false; }
}
async function saveEdit() {
  edit.saving = true;
  const takenAt = edit.date ? `${edit.date} ${edit.time || '00:00'}:00` : null;
  try {
    const r = await g.update(edit.id, {
      taken_at: takenAt, place: edit.place || null, lat: edit.lat, lng: edit.lng,
      rotation: edit.rotation, flip_h: edit.flip_h, version: edit.version,
    });
    // patch the in-memory photo so grid/lightbox reflect it immediately
    const idx = g.photos.findIndex((x) => x.id === edit.id);
    if (idx >= 0) g.photos[idx] = r.photo;
    edit.open = false;
    success(t('common.saved'));
    await refresh();
    if (viewMode.value === 'map') void nextTick().then(syncMap);
  } catch (e: unknown) {
    error((e as { status?: number })?.status === 409 ? t('gallery.edit_conflict') : t('common.error'));
    await refresh();
  } finally { edit.saving = false; }
}

// ---- Trash ----
async function toggleTrash() {
  if (showPeople.value) closePeople();
  showTrash.value = !showTrash.value;
  viewer.value = -1; clearSelection(); closeDupes();
  if (showTrash.value) { showArchive.value = false; try { trashPhotos.value = await g.trash(); } catch { error(t('common.error')); } }
  else await refresh();
}
async function toggleArchive() {
  if (showPeople.value) closePeople();
  showArchive.value = !showArchive.value;
  viewer.value = -1; clearSelection(); closeDupes(); if (showShared.value) closeShared();
  if (showArchive.value) { showTrash.value = false; searchActive.value = false; try { await g.loadArchived(); } catch { error(t('common.error')); } }
  else await refresh();
}
// Archive/unarchive selection (or single from lightbox).
async function archiveSelection(archived: boolean) {
  const ids = [...selected.value]; if (!ids.length) return;
  try {
    await g.bulkArchive(ids, archived);
    clearSelection();
    if (showArchive.value) await g.loadArchived(); else await refresh();
    success(t('common.saved'));
  } catch { error(t('common.error')); }
}
async function archiveOne(p: Row, archived: boolean) {
  try {
    await g.archive(p.id, archived);
    viewer.value = -1;
    if (showArchive.value) await g.loadArchived(); else await refresh();
  } catch { error(t('common.error')); }
}
async function onRestore(id: number) {
  trashBusy.value = true;
  try { await g.restore(id); trashPhotos.value = await g.trash(); await refresh(); success(t('gallery.restored')); }
  catch { error(t('common.error')); }
  finally { trashBusy.value = false; }
}
async function onForce(id: number) {
  if (!await confirmAsk(t('gallery.delete_forever_confirm'), { danger: true })) return;
  trashBusy.value = true;
  try { await g.forceDelete(id); trashPhotos.value = await g.trash(); success(t('gallery.deleted_forever')); }
  catch { error(t('common.error')); }
  finally { trashBusy.value = false; }
}
async function bulkRestore() {
  const ids = [...selected.value];
  if (!ids.length) return;
  trashBusy.value = true;
  try {
    for (const id of ids) await g.restore(id);
    trashPhotos.value = await g.trash(); await refresh(); clearSelection();
    success(t('gallery.restored'));
  } catch { error(t('common.error')); }
  finally { trashBusy.value = false; }
}
async function bulkForce() {
  const ids = [...selected.value];
  if (!ids.length) return;
  if (!await confirmAsk(t('gallery.delete_forever_confirm'), { danger: true })) return;
  trashBusy.value = true;
  try {
    for (const id of ids) await g.forceDelete(id);
    trashPhotos.value = await g.trash(); clearSelection();
    success(t('gallery.deleted_forever'));
  } catch { error(t('common.error')); }
  finally { trashBusy.value = false; }
}
async function onEmpty() {
  if (!await confirmAsk(t('gallery.delete_forever_confirm'), { danger: true })) return;
  try { await g.emptyTrash(); trashPhotos.value = []; } catch { error(t('common.error')); }
}

// ---- Map ----
const mapEl = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let markers: L.LayerGroup | null = null;
function destroyMap() { if (map) { map.remove(); map = null; markers = null; } }
function syncMap() {
  const pts = mapPhotos.value;
  if (viewMode.value !== 'map' || !pts.length || !mapEl.value) return;
  if (!map) {
    map = L.map(mapEl.value, { attributionControl: true, scrollWheelZoom: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    markers = L.layerGroup().addTo(map);
  }
  markers?.clearLayers();
  const bounds: L.LatLngExpression[] = [];
  for (const p of pts) {
    const ll: L.LatLngExpression = [p.lat as number, p.lng as number];
    bounds.push(ll);
    const inner = p.thumb
      ? `<img src="${g.thumbUrl(p.id)}" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)">`
      : '<div style="width:28px;height:28px;border-radius:50%;background:#6750a4;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>';
    const icon = L.divIcon({ className: 'll-gallery-pin', html: inner, iconSize: [44, 44], iconAnchor: [22, 22] });
    const idx = g.photos.findIndex((x) => x.id === p.id);
    L.marker(ll, { icon }).addTo(markers as L.LayerGroup).on('click', () => openViewer(idx));
  }
  if (bounds.length === 1) map.setView(bounds[0], 15);
  else map.fitBounds(bounds as L.LatLngBoundsExpression, { padding: [40, 40] });
  setTimeout(() => map?.invalidateSize(), 60);
}
watch(() => g.photos, () => { if (viewMode.value === 'map') void nextTick().then(syncMap); });
</script>

<style>
.ll-gallery-pin { background: transparent; border: none; }
</style>
