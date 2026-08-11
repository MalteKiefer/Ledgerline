<template>
  <div
    class="relative flex min-h-[calc(100vh-120px)] flex-col gap-4 md:flex-row"
    @dragenter.prevent="onDragEnter" @dragover.prevent @dragleave.prevent="onDragLeave" @drop.prevent="onViewDrop"
  >
    <!-- Full-view drag & drop vCard import overlay -->
    <div v-show="dragDepth > 0" class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center rounded-xl border-2 border-dashed border-primary-500 bg-primary-500/10">
      <div class="rounded-xl bg-[var(--ll-elevated)] px-6 py-4 text-center shadow-lg">
        <Icon name="upload" :size="32" class="text-primary-500" />
        <div class="mt-1 text-sm font-medium">{{ t('contacts.ui.drop_here') }}</div>
      </div>
    </div>
    <!-- Books / groups rail -->
    <Card body-class="p-0" class="w-full shrink-0 self-start md:w-64">
      <div class="p-3">
        <Btn variant="solid" icon="add" block @click="openNew">{{ t('contacts.ui.new_contact') }}</Btn>
      </div>
      <nav class="space-y-0.5 px-2 pb-3">
        <div class="px-2 pb-1 pt-2 text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('contacts.ui.books') }}</div>
        <button
          class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="!bookId && !favOnly && groupId === null ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
          @click="pick(null, false)"
        >
          <Icon name="contacts" :size="20" :class="!bookId && !favOnly && groupId === null ? '' : 'text-[var(--ll-muted)]'" />
          {{ t('contacts.ui.all_books') }}
        </button>
        <button
          class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="favOnly ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
          @click="pick(null, true)"
        >
          <Icon name="star" :fill="favOnly" :size="20" :class="favOnly ? 'text-amber-500' : 'text-[var(--ll-muted)]'" />
          {{ t('contacts.ui.favorites') }}
        </button>
        <div v-for="b in c.books" :key="b.id" class="group flex items-center">
          <button
            class="flex flex-1 items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
            :class="bookId === b.id ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
            @click="pick(b.id, false)"
          >
            <Icon name="contacts" :size="20" :class="bookId === b.id ? '' : 'text-[var(--ll-muted)]'" />
            <span class="truncate">{{ b.name }}</span>
          </button>
          <Btn variant="ghost" size="xs" icon="edit" :title="t('common.rename')" class="opacity-0 group-hover:opacity-100" @click.stop="renameBook(b)" />
          <Btn v-if="c.books.length > 1" variant="ghost" size="xs" icon="delete" :title="t('common.delete')" class="mr-1 opacity-0 group-hover:opacity-100" @click.stop="removeBook(b)" />
        </div>
        <button class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-[var(--ll-muted)] hover:bg-black/[0.04] dark:hover:bg-white/5" @click="newBook">
          <Icon name="add" :size="20" />{{ t('contacts.ui.new_book') }}
        </button>

        <div class="px-2 pb-1 pt-3 text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('contacts.ui.groups') }}</div>
        <div v-for="g in c.groups" :key="g.id" class="group flex items-center">
          <button
            class="flex flex-1 items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
            :class="groupId === g.id ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
            @click="pickGroup(g.id)"
          >
            <Icon name="person" :size="20" :class="groupId === g.id ? '' : 'text-[var(--ll-muted)]'" />
            <span class="truncate">{{ g.name }}</span>
          </button>
          <Btn variant="ghost" size="xs" icon="delete" :title="t('common.delete')" class="mr-1 opacity-0 group-hover:opacity-100" @click.stop="removeGroup(g)" />
        </div>
        <button class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-[var(--ll-muted)] hover:bg-black/[0.04] dark:hover:bg-white/5" @click="newGroup">
          <Icon name="add" :size="20" />{{ t('contacts.ui.new_group') }}
        </button>
      </nav>
    </Card>

    <!-- Main: toolbar + contacts list/detail -->
    <Card body-class="flex flex-1 flex-col overflow-hidden p-0" class="flex w-full min-w-0 flex-1 flex-col self-stretch">
      <!-- Toolbar: search on the left, actions on the right -->
      <div class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] p-3">
        <TextField v-model="query" :placeholder="t('common.search')" icon="search" class="w-full sm:w-64" @update:model-value="debouncedLoad" />
        <Select v-model="c.settings.sort" :options="sortItems" class="w-32" @update:model-value="onSettingsChange" />
        <Select v-model="c.settings.display_format" :options="displayItems" class="w-36" @update:model-value="onSettingsChange" />
        <div class="ml-auto flex items-center gap-1.5">
          <Btn variant="outline" size="sm" icon="upload" @click="openImport">{{ t('contacts.ui.import') }}</Btn>
          <Btn variant="soft" size="sm" icon="content_copy" @click="openDuplicates">{{ t('contacts.ui.duplicates') }}</Btn>
          <Btn variant="ghost" size="sm" icon="download" tag="a" :href="c.exportUrl(bookId ?? undefined)" :title="t('contacts.ui.export')">{{ t('contacts.ui.export') }}</Btn>
        </div>
      </div>

      <!-- Selection bar -->
      <div v-if="selected_ids.length" class="flex shrink-0 items-center gap-1 border-b border-[var(--ll-border)] px-3 py-2">
        <span class="text-xs text-[var(--ll-muted)]">{{ t('contacts.ui.selected_count', { count: String(selected_ids.length) }) }}</span>
        <div class="ml-auto flex items-center gap-1">
          <Btn variant="ghost" size="xs" @click="selectAll">{{ t('contacts.ui.select_all') }}</Btn>
          <Btn variant="ghost" size="xs" @click="clearSelection">{{ t('contacts.ui.clear_selection') }}</Btn>
          <Btn variant="danger" size="xs" :loading="bulkBusy" @click="deleteSelected">{{ t('contacts.ui.delete_selected') }}</Btn>
        </div>
      </div>

      <!-- Contact list + detail -->
      <div class="flex min-h-0 flex-1 flex-col overflow-hidden md:flex-row">
        <!-- List -->
        <div class="w-full shrink-0 overflow-y-auto border-b border-[var(--ll-border)] md:w-[320px] md:border-b-0 md:border-r">
          <button
            v-for="row in c.contacts" :key="row.id"
            class="flex w-full items-center gap-3 border-b border-[var(--ll-border)] px-3 py-2.5 text-left last:border-0 hover:bg-black/[0.03] dark:hover:bg-white/5"
            :class="selected?.id === row.id ? 'bg-primary-500/[0.06]' : ''"
            @click="openDetail(row)"
          >
            <input
              type="checkbox" class="h-4 w-4 shrink-0 rounded border-[var(--ll-border)] accent-[var(--color-primary-500)]"
              :checked="selected_ids.includes(row.id)" @click.stop="toggleSelect(row.id)"
            >
            <span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-full text-sm font-medium" :class="TONE_BG[color(row)]">
              <img v-if="c.avatarUrl(row)" :src="bust(c.avatarUrl(row))!" class="h-full w-full object-cover">
              <template v-else>{{ initials(row) }}</template>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium">{{ displayName(row) }}</span>
              <span class="block truncate text-xs text-[var(--ll-muted)]">{{ row.org || row.emails[0]?.value || '' }}</span>
            </span>
            <Icon v-if="row.favorite" name="star" fill :size="16" class="shrink-0 text-amber-500" />
          </button>
          <div v-if="!c.contacts.length" class="px-3 py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('contacts.ui.empty') }}</div>
        </div>

        <!-- Detail -->
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
          <template v-if="detail">
            <div class="flex shrink-0 items-center gap-3 border-b border-[var(--ll-border)] p-4">
              <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-500 text-base font-medium text-white">
                <img v-if="selected && c.avatarUrl(selected)" :src="bust(c.avatarUrl(selected))!" class="h-full w-full object-cover">
                <template v-else>{{ selected ? initials(selected) : '' }}</template>
              </span>
              <h2 class="min-w-0 flex-1 truncate text-lg font-semibold">{{ str(detail.fn) }}</h2>
              <button
                class="grid h-9 w-9 shrink-0 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10"
                :title="selected?.favorite ? t('contacts.ui.favorite_remove') : t('contacts.ui.favorite_add')"
                @click="toggleFav"
              >
                <Icon name="star" :fill="!!selected?.favorite" :size="20" :class="selected?.favorite ? 'text-amber-500' : 'text-[var(--ll-muted)]'" />
              </button>
              <Btn variant="ghost" size="sm" icon="edit" :title="t('common.edit')" @click="openEdit" />
              <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="onDelete" />
            </div>
            <div class="flex-1 overflow-y-auto p-5">
              <div v-if="str(detail.org)" class="mb-4 text-sm text-[var(--ll-muted)]">{{ str(detail.org) }}<span v-if="str(detail.title)"> · {{ str(detail.title) }}</span></div>
              <div class="space-y-1">
                <a v-for="(e, i) in arr(detail.emails)" :key="'e'+i" :href="'mailto:' + e.value" class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-black/[0.03] dark:hover:bg-white/5">
                  <Icon name="mail" :size="20" class="shrink-0 text-[var(--ll-muted)]" />
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm">{{ e.value }}</span>
                    <span class="block text-xs text-[var(--ll-muted)]">{{ e.type }}</span>
                  </span>
                </a>
                <a v-for="(p, i) in arr(detail.phones)" :key="'p'+i" :href="'tel:' + p.value" class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-black/[0.03] dark:hover:bg-white/5">
                  <Icon name="call" :size="20" class="shrink-0 text-[var(--ll-muted)]" />
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm">{{ p.value }}</span>
                    <span class="block text-xs text-[var(--ll-muted)]">{{ p.type }}</span>
                  </span>
                </a>
                <a v-for="(u, i) in arr(detail.urls)" :key="'u'+i" :href="u.value" target="_blank" class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-black/[0.03] dark:hover:bg-white/5">
                  <span class="w-5 shrink-0"></span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm">{{ u.value }}</span>
                    <span class="block text-xs text-[var(--ll-muted)]">{{ t('contacts.ui.website') }}</span>
                  </span>
                </a>
              </div>
              <!-- Addresses -->
              <template v-if="addressList(detail).length">
                <div class="mb-1 mt-4 text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('contacts.ui.addresses') }}</div>
                <div class="space-y-1">
                  <div v-for="(a, i) in addressList(detail)" :key="'a'+i" class="flex items-center gap-3 rounded-lg px-2 py-2">
                    <Icon name="location_on" :size="20" class="shrink-0 text-[var(--ll-muted)]" />
                    <span class="min-w-0 flex-1">
                      <span class="block truncate text-sm">{{ a.text }}</span>
                      <span class="block text-xs text-[var(--ll-muted)]">{{ a.type }}</span>
                    </span>
                    <Btn variant="ghost" size="xs" tag="a" :href="mapUrl(a.text)" target="_blank" rel="noopener" :title="t('contacts.ui.map_open_osm')">{{ t('contacts.ui.map_open_osm') }}</Btn>
                  </div>
                </div>
              </template>
              <div v-if="str(detail.note)" class="mt-4">
                <div class="text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('contacts.ui.note') }}</div>
                <div class="mt-1 text-sm">{{ str(detail.note) }}</div>
              </div>
              <!-- Gallery photos of this contact (face recognition) -->
              <div v-if="contactPhotos.length" class="mt-4">
                <div class="mb-1 text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('gallery.contact_photos') }}</div>
                <div class="grid grid-cols-4 gap-1.5 sm:grid-cols-6">
                  <a
                    v-for="p in contactPhotos" :key="p.id" :href="'#/gallery'"
                    class="aspect-square overflow-hidden rounded-lg bg-black/[0.04] dark:bg-white/5"
                  >
                    <img v-if="p.thumb" :src="gal.thumbUrl(p.id)" loading="lazy" class="h-full w-full object-cover">
                  </a>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </Card>
  </div>

  <!-- Editor -->
  <Modal v-model="editor" :title="editing ? t('contacts.ui.edit_contact') : t('contacts.ui.new_contact')" width="640px">
    <div class="space-y-4">
      <!-- Avatar (only once the contact exists) -->
      <div v-if="editing && selected" class="flex items-center gap-3">
        <span class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-500 text-lg font-medium text-white">
          <img v-if="c.avatarUrl(selected)" :src="bust(c.avatarUrl(selected))!" class="h-full w-full object-cover">
          <template v-else>{{ initials(selected) }}</template>
        </span>
        <Btn variant="soft" size="sm" :loading="avatarBusy" @click="pickAvatar">{{ t('contacts.ui.avatar_change') }}</Btn>
        <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="onAvatarPicked">
      </div>
      <Select v-model="form.book_id" :label="t('contacts.ui.books')" :options="bookItems" />
      <div class="grid grid-cols-2 gap-3">
        <TextField v-model="form.first_name" :label="t('contacts.ui.first_name')" />
        <TextField v-model="form.last_name" :label="t('contacts.ui.last_name')" />
      </div>
      <TextField v-model="form.org" :label="t('contacts.ui.org')" />
      <TextField v-model="form.title" :label="t('contacts.ui.title')" />

      <div>
        <div class="mb-1.5 text-xs font-medium text-[var(--ll-muted)]">{{ t('contacts.ui.email') }}</div>
        <div v-for="(e, i) in form.emails" :key="'fe'+i" class="mb-2 flex items-center gap-2">
          <TextField v-model="e.value" type="email" class="flex-1" />
          <Btn variant="ghost" size="sm" icon="close" @click="form.emails.splice(i,1)" />
        </div>
        <Btn variant="ghost" size="sm" icon="add" @click="form.emails.push({ value: '', type: 'home' })">{{ t('common.add') }}</Btn>
      </div>

      <div>
        <div class="mb-1.5 text-xs font-medium text-[var(--ll-muted)]">{{ t('contacts.ui.phone') }}</div>
        <div v-for="(p, i) in form.phones" :key="'fp'+i" class="mb-2 flex items-center gap-2">
          <TextField v-model="p.value" class="flex-1" />
          <Btn variant="ghost" size="sm" icon="close" @click="form.phones.splice(i,1)" />
        </div>
        <Btn variant="ghost" size="sm" icon="add" @click="form.phones.push({ value: '', type: 'cell' })">{{ t('common.add') }}</Btn>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <TextField v-model="form.nickname" :label="t('contacts.ui.nickname')" />
        <TextField v-model="form.bday" type="date" :label="t('contacts.ui.bday')" />
      </div>

      <div>
        <div class="mb-1.5 text-xs font-medium text-[var(--ll-muted)]">{{ t('contacts.ui.url') }}</div>
        <div v-for="(u, i) in form.urls" :key="'fu'+i" class="mb-2 flex items-center gap-2">
          <TextField v-model="u.value" type="url" class="flex-1" />
          <Btn variant="ghost" size="sm" icon="close" @click="form.urls.splice(i,1)" />
        </div>
        <Btn variant="ghost" size="sm" icon="add" @click="form.urls.push({ value: '', type: 'home' })">{{ t('common.add') }}</Btn>
      </div>

      <div>
        <div class="mb-1.5 text-xs font-medium text-[var(--ll-muted)]">{{ t('contacts.ui.addresses') }}</div>
        <div v-for="(a, i) in form.addresses" :key="'fa'+i" class="mb-2 rounded-lg border border-[var(--ll-border)] p-2">
          <div class="mb-2 flex items-center justify-between gap-2">
            <TextField v-model="a.type" :label="t('contacts.ui.type')" class="w-32" />
            <Btn variant="ghost" size="sm" icon="close" @click="form.addresses.splice(i,1)" />
          </div>
          <TextField v-model="a.street" :label="t('contacts.ui.street')" class="mb-2" />
          <div class="grid grid-cols-3 gap-2">
            <TextField v-model="a.zip" :label="t('contacts.ui.zip')" />
            <TextField v-model="a.city" :label="t('contacts.ui.city')" class="col-span-2" />
          </div>
          <div class="mt-2 grid grid-cols-2 gap-2">
            <TextField v-model="a.region" :label="t('contacts.ui.region')" />
            <TextField v-model="a.country" :label="t('contacts.ui.country')" />
          </div>
        </div>
        <Btn variant="ghost" size="sm" icon="add" @click="form.addresses.push(blankAddress())">{{ t('common.add') }}</Btn>
      </div>

      <div>
        <div class="mb-1.5 text-xs font-medium text-[var(--ll-muted)]">{{ t('contacts.ui.anniversaries') }}</div>
        <div v-for="(a, i) in form.anniversaries" :key="'fan'+i" class="mb-2 flex items-center gap-2">
          <TextField v-model="a.date" type="date" class="w-44" />
          <TextField v-model="a.label" :label="t('contacts.ui.field_label')" class="flex-1" />
          <Btn variant="ghost" size="sm" icon="close" @click="form.anniversaries.splice(i,1)" />
        </div>
        <Btn variant="ghost" size="sm" icon="add" @click="form.anniversaries.push({ date: '', label: '' })">{{ t('common.add') }}</Btn>
      </div>

      <div>
        <div class="mb-1.5 text-xs font-medium text-[var(--ll-muted)]">{{ t('contacts.ui.custom_fields') }}</div>
        <div v-for="(f, i) in form.custom_fields" :key="'fc'+i" class="mb-2 flex items-center gap-2">
          <TextField v-model="f.label" :label="t('contacts.ui.field_label')" class="w-40" />
          <TextField v-model="f.value" class="flex-1" />
          <Btn variant="ghost" size="sm" icon="close" @click="form.custom_fields.splice(i,1)" />
        </div>
        <Btn variant="ghost" size="sm" icon="add" @click="form.custom_fields.push({ label: '', value: '' })">{{ t('common.add') }}</Btn>
      </div>

      <div v-if="c.groups.length">
        <div class="mb-1.5 text-xs font-medium text-[var(--ll-muted)]">{{ t('contacts.ui.groups') }}</div>
        <div class="flex flex-wrap gap-2">
          <label v-for="g in c.groups" :key="g.id" class="flex items-center gap-1.5 rounded-lg border border-[var(--ll-border)] px-2.5 py-1 text-sm">
            <input v-model="form.group_ids" type="checkbox" class="accent-primary-500" :value="g.id" >
            {{ g.name }}
          </label>
        </div>
      </div>

      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('contacts.ui.note') }}</span>
        <textarea
          v-model="form.note" rows="2"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
        ></textarea>
      </label>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="editor = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="saving" @click="save">{{ t('common.save') }}</Btn>
    </template>
  </Modal>

  <!-- Import -->
  <Modal v-model="importDialog" :title="t('contacts.ui.import')" width="480px">
    <div class="space-y-4">
      <Select v-model="importBookId" :label="t('contacts.ui.books')" :options="bookItems" />
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('contacts.ui.import') }}</span>
        <input
          type="file" accept=".vcf,text/vcard"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] file:mr-3 file:rounded-md file:border-0 file:bg-primary-500/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-600 dark:file:text-primary-300"
          @change="importFile = (($event.target as HTMLInputElement).files ? Array.from((($event.target as HTMLInputElement).files as FileList)) : null)"
        >
      </label>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="importDialog = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="importing" :disabled="!importFile || !importBookId" @click="runImport">{{ t('contacts.ui.import') }}</Btn>
    </template>
  </Modal>

  <!-- Duplicates -->
  <Modal v-model="dupDialog" :title="t('contacts.ui.duplicates')" width="720px">
    <div v-if="dupLoading" class="flex justify-center py-10">
      <span class="h-8 w-8 animate-spin rounded-full border-[3px] border-primary-500/25 border-t-primary-500"></span>
    </div>
    <div v-else-if="!dupGroups.length" class="py-10 text-center text-sm text-[var(--ll-muted)]">{{ t('contacts.dup.empty') }}</div>
    <div v-else class="space-y-3">
      <Card v-for="g in dupGroups" :key="g.signature" body-class="p-0">
        <div class="flex flex-wrap items-center gap-2 px-3 pt-3">
          <span class="text-xs text-[var(--ll-muted)]">{{ t('contacts.dup.matched_by') }}:</span>
          <Badge v-for="r in g.reasons" :key="r" tone="primary">{{ r }}</Badge>
        </div>
        <div class="px-1 py-2">
          <label v-for="m in g.contacts" :key="m.id" class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-black/[0.03] dark:hover:bg-white/5">
            <input
              type="radio" class="h-4 w-4 shrink-0 accent-[var(--color-primary-500)]" :name="'dup-' + g.signature"
              :checked="dupPrimary[g.signature] === m.id" @change="dupPrimary[g.signature] = m.id"
            >
            <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full text-sm font-medium" :class="TONE_BG[dupColor(m.id)]">
              <img v-if="m.avatar" :src="m.avatar" class="h-full w-full object-cover">
              <template v-else>{{ dupInitials(m) }}</template>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium">{{ m.fn || [m.first_name, m.last_name].filter(Boolean).join(' ') || '—' }}</span>
              <span class="block truncate text-xs text-[var(--ll-muted)]">{{ [m.org, m.emails[0], m.phones[0]].filter(Boolean).join(' · ') }}</span>
            </span>
          </label>
        </div>
        <div class="flex items-center gap-2 border-t border-[var(--ll-border)] px-3 py-2.5">
          <span class="text-xs text-[var(--ll-muted)]">{{ t('contacts.dup.keep_as_primary') }}</span>
          <div class="ml-auto flex items-center gap-2">
            <Btn variant="ghost" size="sm" :loading="dupBusy === g.signature" @click="dismissGroup(g)">{{ t('contacts.dup.dismiss') }}</Btn>
            <Btn variant="soft" size="sm" :loading="dupBusy === g.signature" @click="mergeGroup(g)">{{ t('contacts.dup.merge') }}</Btn>
          </div>
        </div>
      </Card>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="dupDialog = false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Select, Badge, Modal } from '@spa/ui';
import { useContactsStore, type ContactRow, type ContactDetail, type ContactGroup, type DuplicateGroup, type DuplicateContact, type AddressBook } from '@spa/stores/contacts';
import { useToast } from '@spa/composables/useToast';
import { useAuthStore } from '@spa/stores/auth';
import { useGalleryStore, type Photo } from '@spa/stores/gallery';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';

// Presentational-only: maps the existing Vuetify-style color name (still returned
// by color()/dupColor() below) onto an avatar tint. No behavior/semantics changed.
const TONE_BG: Record<string, string> = {
  primary: 'bg-primary-500/15 text-primary-600 dark:text-primary-300',
  secondary: 'bg-fuchsia-500/15 text-fuchsia-600 dark:text-fuchsia-400',
  success: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
  warning: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
  error: 'bg-red-500/15 text-red-600 dark:text-red-400',
  info: 'bg-blue-500/15 text-blue-600 dark:text-blue-400',
};

const c = useContactsStore();
const gal = useGalleryStore();
const auth = useAuthStore();
const { success, error } = useToast();
const contactPhotos = ref<Photo[]>([]);
const bookId = ref<string | null>(null);
const groupId = ref<number | null>(null);
const favOnly = ref(false);
const query = ref('');
const selected = ref<ContactRow | null>(null);
const detail = ref<ContactDetail | null>(null);
const editor = ref(false);
const editing = ref(false);
const saving = ref(false);
const avatarVersion = ref(0);

type Field = { value: string; type?: string };
type Addr = { type: string; street: string; city: string; region: string; zip: string; country: string };
type CustomField = { label: string; value: string };
type Anniversary = { date: string; label: string };
const form = reactive<{
  book_id: string; first_name: string; last_name: string; org: string; title: string; nickname: string; bday: string; note: string;
  emails: Field[]; phones: Field[]; urls: Field[]; addresses: Addr[]; anniversaries: Anniversary[]; custom_fields: CustomField[]; group_ids: number[];
}>(
  { book_id: '', first_name: '', last_name: '', org: '', title: '', nickname: '', bday: '', note: '', emails: [], phones: [], urls: [], addresses: [], anniversaries: [], custom_fields: [], group_ids: [] },
);
function blankAddress(): Addr { return { type: 'home', street: '', city: '', region: '', zip: '', country: '' }; }

// Selection (bulk)
const selected_ids = ref<string[]>([]);
const bulkBusy = ref(false);

// Import
const importDialog = ref(false);
const importFile = ref<File | File[] | null>(null);
const importBookId = ref('');
const importing = ref(false);

// Duplicates
const dupDialog = ref(false);
const dupLoading = ref(false);
const dupGroups = ref<DuplicateGroup[]>([]);
const dupPrimary = reactive<Record<string, string>>({});
const dupBusy = ref<string | null>(null);

// Avatar upload
const avatarInput = ref<HTMLInputElement | null>(null);
const avatarBusy = ref(false);

const bookItems = computed(() => c.books.map((b) => ({ title: b.name, value: b.id })));

onMounted(() => c.load());

function str(v: unknown): string { return typeof v === 'string' ? v : ''; }
function arr(v: unknown): Field[] { return Array.isArray(v) ? (v as Field[]) : []; }
function initials(r: ContactRow): string { return ((r.first_name?.[0] ?? '') + (r.last_name?.[0] ?? '') || r.fn?.[0] || '?').toUpperCase(); }
// Displayed name honours the list display-format preference (last_first → "Last, First").
function displayName(r: { fn?: string | null; first_name?: string | null; last_name?: string | null }): string {
  const first = r.first_name || '', last = r.last_name || '';
  if (c.settings.display_format === 'last_first' && (first || last)) return [last, first].filter(Boolean).join(', ');
  return r.fn || [first, last].filter(Boolean).join(' ') || '—';
}
const sortItems = computed(() => [
  { title: t('contacts.ui.sort_first'), value: 'first_name' },
  { title: t('contacts.ui.sort_last'), value: 'last_name' },
]);
const displayItems = computed(() => [
  { title: t('contacts.ui.display_first_last'), value: 'first_last' },
  { title: t('contacts.ui.display_last_first'), value: 'last_first' },
]);
async function onSettingsChange() {
  try { await c.saveSettings(c.settings.sort, c.settings.display_format); await reload(); }
  catch { error(t('common.error')); }
}
function color(r: ContactRow): string { const p = ['primary', 'secondary', 'success', 'warning', 'error', 'info']; let h = 0; for (const ch of r.id) h = (h + ch.charCodeAt(0)) % p.length; return p[h]; }
function bust(url: string | null): string | null {
  if (!url) return null;
  if (!avatarVersion.value) return url;
  return url + (url.includes('?') ? '&' : '?') + '_a=' + avatarVersion.value;
}
function addressList(d: ContactDetail): { text: string; type: string }[] {
  const list = Array.isArray(d.addresses) ? d.addresses as Record<string, string>[] : [];
  return list
    .map((a) => ({ text: [a.street, a.zip, a.city, a.country].filter(Boolean).join(', '), type: str(a.type) }))
    .filter((a) => a.text !== '');
}
function mapUrl(text: string): string { return `https://www.openstreetmap.org/search?query=${encodeURIComponent(text)}`; }

let debTimer: ReturnType<typeof setTimeout> | undefined;
function debouncedLoad() { clearTimeout(debTimer); debTimer = setTimeout(reload, 300); }
function reload() {
  return c.load({
    book_id: bookId.value ?? undefined,
    group_id: groupId.value ?? undefined,
    favorites: favOnly.value,
    q: query.value || undefined,
  });
}

function pick(b: string | null, fav: boolean) { bookId.value = b; favOnly.value = fav; groupId.value = null; reload(); }
function pickGroup(id: number) { groupId.value = id; bookId.value = null; favOnly.value = false; reload(); }
async function openDetail(row: ContactRow) {
  selected.value = row; detail.value = await c.show(row.id);
  contactPhotos.value = auth.can('gallery') ? await gal.contactPhotos(row.id) : [];
}

async function toggleFav() {
  if (!selected.value) return;
  const next = !selected.value.favorite;
  await c.favorite(selected.value.id, next);
  selected.value.favorite = next;
}
async function onDelete() {
  if (!selected.value || !await confirmAsk(t('contacts.ui.delete_confirm'), { danger: true })) return;
  await c.destroy(selected.value.id);
  detail.value = null; selected.value = null; await reload();
}

// --- Selection / bulk delete ---
function toggleSelect(id: string) {
  const i = selected_ids.value.indexOf(id);
  if (i >= 0) selected_ids.value.splice(i, 1);
  else selected_ids.value.push(id);
}
function selectAll() { selected_ids.value = c.contacts.map((r) => r.id); }
function clearSelection() { selected_ids.value = []; }
async function deleteSelected() {
  if (!selected_ids.value.length || !await confirmAsk(t('contacts.ui.delete_selected_confirm', { count: String(selected_ids.value.length) }), { danger: true })) return;
  bulkBusy.value = true;
  try {
    await c.bulkDestroy([...selected_ids.value]);
    if (selected.value && selected_ids.value.includes(selected.value.id)) { detail.value = null; selected.value = null; }
    clearSelection();
    await reload();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { bulkBusy.value = false; }
}

function openNew() {
  editing.value = false;
  Object.assign(form, { book_id: c.books[0]?.id ?? '', first_name: '', last_name: '', org: '', title: '', nickname: '', bday: '', note: '', emails: [{ value: '', type: 'home' }], phones: [{ value: '', type: 'cell' }], urls: [], addresses: [], anniversaries: [], custom_fields: [], group_ids: [] });
  editor.value = true;
}
function openEdit() {
  if (!detail.value) return;
  editing.value = true;
  const d = detail.value;
  const addrs = Array.isArray(d.addresses) ? (d.addresses as Record<string, unknown>[]) : [];
  const cfs = Array.isArray(d.custom_fields) ? (d.custom_fields as Record<string, unknown>[]) : [];
  const anns = Array.isArray(d.anniversaries) ? (d.anniversaries as Record<string, unknown>[]) : [];
  Object.assign(form, {
    book_id: d.book, first_name: str(d.first_name), last_name: str(d.last_name), org: str(d.org), title: str(d.title),
    nickname: str(d.nickname), bday: str(d.bday), note: str(d.note),
    emails: arr(d.emails).map((e) => ({ ...e })), phones: arr(d.phones).map((p) => ({ ...p })),
    urls: arr(d.urls).map((u) => ({ ...u })),
    addresses: addrs.map((a) => ({ type: str(a.type), street: str(a.street), city: str(a.city), region: str(a.region), zip: str(a.zip), country: str(a.country) })),
    anniversaries: anns.map((a) => ({ date: str(a.date), label: str(a.label) })),
    custom_fields: cfs.map((f) => ({ label: str(f.label), value: str(f.value) })),
    group_ids: [...(d.group_ids ?? [])],
  });
  editor.value = true;
}
async function save() {
  saving.value = true;
  try {
    const body: Record<string, unknown> = {
      book_id: form.book_id, first_name: form.first_name, last_name: form.last_name, org: form.org, title: form.title,
      nickname: form.nickname, bday: form.bday, note: form.note,
      fn: [form.first_name, form.last_name].filter(Boolean).join(' ') || form.org,
      emails: form.emails.filter((e) => e.value), phones: form.phones.filter((p) => p.value),
      urls: form.urls.filter((u) => u.value),
      addresses: form.addresses.filter((a) => a.street || a.city || a.zip || a.region || a.country),
      anniversaries: form.anniversaries.filter((a) => a.date),
      custom_fields: form.custom_fields.filter((f) => f.value),
      group_ids: form.group_ids.map((g) => String(g)),
    };
    if (editing.value && selected.value) await c.update(selected.value.id, body);
    else await c.create(body);
    editor.value = false;
    await reload();
    if (editing.value && selected.value) detail.value = await c.show(selected.value.id);
    success(t('contacts.ui.saved'));
  } catch { error(t('common.error')); } finally { saving.value = false; }
}

async function newBook() { const name = await promptAsk(t('contacts.ui.new_book')); if (name) { await c.createBook(name); await c.load(); } }
async function renameBook(b: AddressBook) {
  const name = await promptAsk(t('common.rename'), { value: b.name });
  if (!name || name === b.name) return;
  try { await c.updateBook(b.id, name); await c.load(); success(t('contacts.ui.saved')); } catch { error(t('common.error')); }
}
async function removeBook(b: AddressBook) {
  if (!await confirmAsk(t('contacts.ui.delete_book_confirm'), { danger: true })) return;
  try {
    await c.deleteBook(b.id);
    if (bookId.value === b.id) { bookId.value = null; }
    await reload();
    success(t('contacts.ui.saved'));
  } catch { error(t('common.error')); }
}

// --- Groups ---
async function newGroup() {
  const name = await promptAsk(t('contacts.ui.new_group'));
  if (!name) return;
  try { await c.createGroup(name); await reload(); success(t('contacts.ui.saved')); }
  catch { error(t('common.error')); }
}
async function removeGroup(g: ContactGroup) {
  if (!await confirmAsk(t('contacts.ui.delete_group_confirm'), { danger: true })) return;
  try {
    await c.deleteGroup(g.id);
    if (groupId.value === g.id) { groupId.value = null; }
    await reload();
  } catch { error(t('common.error')); }
}

// --- Full-view drag & drop vCard import ---
const dragDepth = ref(0);
function hasFiles(e: DragEvent) { return Array.from(e.dataTransfer?.types ?? []).includes('Files'); }
function onDragEnter(e: DragEvent) { if (hasFiles(e)) dragDepth.value++; }
function onDragLeave(e: DragEvent) { if (hasFiles(e)) dragDepth.value = Math.max(0, dragDepth.value - 1); }
async function onViewDrop(e: DragEvent) {
  dragDepth.value = 0;
  const files = Array.from(e.dataTransfer?.files ?? []).filter((f) => /\.vcf$|\.vcard$/i.test(f.name) || f.type === 'text/vcard');
  const book = bookId.value ?? c.books[0]?.id ?? '';
  if (!files.length || !book) return;
  importing.value = true;
  try {
    let created = 0; let updated = 0; let skipped = 0;
    for (const f of files) {
      const r = await c.importVcf(f, book);
      created += r.created; updated += r.updated; skipped += r.skipped;
    }
    await reload();
    success(t('contacts.ui.import_result', { created: String(created), updated: String(updated), skipped: String(skipped) }));
  } catch { error(t('common.error')); } finally { importing.value = false; }
}

// --- Import / export ---
function openImport() {
  importFile.value = null;
  importBookId.value = bookId.value ?? c.books[0]?.id ?? '';
  importDialog.value = true;
}
async function runImport() {
  const file = Array.isArray(importFile.value) ? importFile.value[0] : importFile.value;
  if (!file || !importBookId.value) return;
  importing.value = true;
  try {
    const r = await c.importVcf(file, importBookId.value);
    importDialog.value = false;
    await reload();
    success(t('contacts.ui.import_result', { created: String(r.created), updated: String(r.updated), skipped: String(r.skipped) }));
  } catch { error(t('common.error')); } finally { importing.value = false; }
}

// --- Duplicates ---
function dupInitials(m: DuplicateContact): string { return ((m.first_name?.[0] ?? '') + (m.last_name?.[0] ?? '') || m.fn?.[0] || '?').toUpperCase(); }
function dupColor(id: string): string { const p = ['primary', 'secondary', 'success', 'warning', 'error', 'info']; let h = 0; for (const ch of id) h = (h + ch.charCodeAt(0)) % p.length; return p[h]; }
async function openDuplicates() {
  dupDialog.value = true;
  dupLoading.value = true;
  try {
    dupGroups.value = await c.loadDuplicates();
    for (const g of dupGroups.value) dupPrimary[g.signature] = g.contacts[0]?.id ?? '';
  } catch { error(t('common.error')); } finally { dupLoading.value = false; }
}
async function mergeGroup(g: DuplicateGroup) {
  const primary = dupPrimary[g.signature] || g.contacts[0]?.id;
  if (!primary || !await confirmAsk(t('contacts.dup.merge_confirm'), { danger: true })) return;
  dupBusy.value = g.signature;
  try {
    await c.mergeDuplicates({ primary_id: primary, ids: g.contacts.map((m) => m.id) });
    dupGroups.value = dupGroups.value.filter((x) => x.signature !== g.signature);
    await reload();
    success(t('contacts.ui.saved'));
  } catch { error(t('common.error')); } finally { dupBusy.value = null; }
}
async function dismissGroup(g: DuplicateGroup) {
  dupBusy.value = g.signature;
  try {
    await c.dismissDuplicate({ ids: g.contacts.map((m) => m.id) });
    dupGroups.value = dupGroups.value.filter((x) => x.signature !== g.signature);
  } catch { error(t('common.error')); } finally { dupBusy.value = null; }
}

// --- Avatar upload ---
function pickAvatar() { avatarInput.value?.click(); }
// Center-crop the picked image to a square and downscale to 512px before upload
// (the backend only aspect-scales, so this is what makes avatars square).
async function cropSquare(file: File): Promise<Blob> {
  try {
    const bmp = await createImageBitmap(file);
    const side = Math.min(bmp.width, bmp.height);
    const size = Math.min(512, side);
    const canvas = document.createElement('canvas');
    canvas.width = size; canvas.height = size;
    const ctx = canvas.getContext('2d');
    if (!ctx) return file;
    ctx.drawImage(bmp, (bmp.width - side) / 2, (bmp.height - side) / 2, side, side, 0, 0, size, size);
    return await new Promise<Blob>((resolve) => canvas.toBlob((b) => resolve(b ?? file), 'image/jpeg', 0.88));
  } catch {
    return file; // fall back to the raw file if the browser can't decode it
  }
}
async function onAvatarPicked(ev: Event) {
  const input = ev.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file || !selected.value) return;
  avatarBusy.value = true;
  try {
    await c.uploadAvatar(selected.value.id, await cropSquare(file));
    avatarVersion.value = Date.now();
    selected.value.has_photo = true;
    await reload();
    detail.value = await c.show(selected.value.id);
    success(t('contacts.ui.saved'));
  } catch { error(t('common.error')); } finally { avatarBusy.value = false; input.value = ''; }
}
</script>
