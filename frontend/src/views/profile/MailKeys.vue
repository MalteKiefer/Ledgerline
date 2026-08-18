<template>
  <Card :title="t('mail.keys.title')">
    <template #actions>
      <Btn variant="solid" size="sm" icon="key" @click="openGenerate">{{ t('mail.keys.generate') }}</Btn>
      <Btn variant="soft" size="sm" icon="add" @click="openImport('pgp')">{{ t('mail.keys.import_pgp') }}</Btn>
      <Btn variant="soft" size="sm" icon="add" @click="openImport('smime')">{{ t('mail.keys.import_smime') }}</Btn>
    </template>

    <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('mail.keys.subtitle') }}</p>

    <div v-if="loading" class="py-6 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
    <div v-else-if="!keys.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.keys.none') }}</div>
    <div v-else class="divide-y divide-[var(--ll-border)]">
      <div
        v-for="k in keys" :key="k.id"
        class="flex cursor-pointer items-start gap-3 rounded-lg py-3 transition-colors hover:bg-black/[0.02] dark:hover:bg-white/5"
        @click="openDetail(k)"
      >
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary-500/15 text-primary-600 dark:text-primary-300"><Icon name="key" :size="20" /></span>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="truncate text-sm font-medium">{{ k.label }}</span>
            <Badge :tone="k.type === 'pgp' ? 'primary' : 'info'">{{ k.type.toUpperCase() }}</Badge>
            <Badge v-if="k.algorithm" tone="gray">{{ k.algorithm }}{{ k.key_length ? ' ' + k.key_length : (k.curve ? ' ' + k.curve : '') }}</Badge>
          </div>
          <div v-if="k.key_fingerprint || k.key_id" class="truncate font-mono text-xs text-[var(--ll-muted)]">{{ t('mail.keys.fingerprint') }}: {{ k.key_fingerprint || k.key_id }}</div>
          <div v-if="k.identities?.length" class="truncate text-xs text-[var(--ll-muted)]">{{ k.identities.map(formatIdentity).join(', ') }}</div>
          <div v-if="k.expires_at" class="text-xs text-[var(--ll-muted)]">{{ new Date(k.expires_at).toLocaleDateString() }}</div>
        </div>
        <Btn v-if="k.public_key" variant="ghost" size="sm" icon="content_copy" :title="t('mail.keys.copy_public_key')" @click.stop="copyText(k.public_key)" />
        <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" :title="t('mail.keys.delete')" @click.stop="remove(k)" />
      </div>
    </div>
  </Card>

  <!-- Recipients: other people's public keys/certs, used to encrypt TO them -->
  <Card :title="t('mail.keys.recipients_title')" class="mt-6">
    <template #actions>
      <Btn variant="soft" size="sm" icon="travel_explore" @click="openKeyserverSearch">{{ t('mail.keys.recipients_search') }}</Btn>
      <Btn variant="soft" size="sm" icon="add" @click="openRecipientAdd">{{ t('mail.keys.recipients_add') }}</Btn>
    </template>

    <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('mail.keys.recipients_subtitle') }}</p>

    <div v-if="recipientsLoading" class="py-6 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
    <div v-else-if="!crypto.recipients.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.keys.recipients_none') }}</div>
    <div v-else class="divide-y divide-[var(--ll-border)]">
      <div v-for="r in crypto.recipients" :key="r.id" class="flex items-start gap-3 py-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10"><Icon name="person" :size="20" /></span>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="truncate text-sm font-medium">{{ r.label }}</span>
            <Badge :tone="r.type === 'pgp' ? 'primary' : 'info'">{{ r.type.toUpperCase() }}</Badge>
            <Badge v-if="r.key_server_id" tone="gray">{{ t('mail.keys.recipient_from_server') }}</Badge>
          </div>
          <div v-if="r.fingerprint" class="truncate font-mono text-xs text-[var(--ll-muted)]">{{ r.fingerprint }}</div>
          <div v-if="r.refreshed_at" class="text-xs text-[var(--ll-muted)]">{{ t('mail.keys.recipient_refreshed_at') }}: {{ new Date(r.refreshed_at).toLocaleString() }}</div>
        </div>
        <Btn v-if="r.type === 'pgp' && r.fingerprint" variant="ghost" size="sm" icon="sync" :loading="refreshingId === r.id" :title="t('mail.keys.recipient_refresh')" @click="doRefreshRecipient(r)" />
        <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" :title="t('mail.keys.recipient_delete')" @click="removeRecipient(r)" />
      </div>
    </div>
  </Card>

  <!-- Keyservers: HKP servers to search/publish/refresh against -->
  <Card :title="t('mail.keys.servers_title')" class="mt-6">
    <template #actions>
      <Btn variant="soft" size="sm" icon="add" @click="openServerForm(null)">{{ t('mail.keys.servers_add') }}</Btn>
    </template>

    <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('mail.keys.servers_subtitle') }}</p>

    <div v-if="!crypto.keyServers.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.keys.servers_none') }}</div>
    <div v-else class="divide-y divide-[var(--ll-border)]">
      <div v-for="srv in crypto.keyServers" :key="srv.id" class="flex items-center gap-3 py-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10"><Icon name="dns" :size="20" /></span>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="truncate text-sm font-medium">{{ srv.name }}</span>
            <Badge v-if="!srv.enabled" tone="gray">{{ t('mail.keys.servers_disabled') }}</Badge>
          </div>
          <div class="truncate text-xs text-[var(--ll-muted)]">{{ srv.url }}</div>
        </div>
        <Btn variant="ghost" size="sm" icon="edit" :title="t('common.edit')" @click="openServerForm(srv)" />
        <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" :title="t('mail.keys.servers_delete')" @click="removeServer(srv)" />
      </div>
    </div>
  </Card>

  <!-- Detail modal: every field the key/certificate itself carries -->
  <Modal v-model="detail.show" :title="t('mail.keys.details')" width="620px">
    <div v-if="detail.key" class="space-y-4">
      <div class="flex items-center gap-2">
        <span class="text-base font-medium">{{ detail.key.label }}</span>
        <Badge :tone="detail.key.type === 'pgp' ? 'primary' : 'info'">{{ detail.key.type.toUpperCase() }}</Badge>
      </div>

      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.identities') }}</span>
        <div v-if="detail.key.identities?.length" class="space-y-1">
          <div v-for="(i, idx) in detail.key.identities" :key="idx" class="text-sm">{{ formatIdentity(i) }}</div>
        </div>
        <p v-else class="text-sm text-[var(--ll-muted)]">{{ t('mail.keys.identities_none') }}</p>
      </div>

      <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
        <template v-if="detail.key.key_fingerprint">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.fingerprint') }}</dt>
          <dd class="truncate font-mono text-xs" :title="detail.key.key_fingerprint">{{ detail.key.key_fingerprint }}</dd>
        </template>
        <template v-if="detail.key.key_id">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.key_id') }}</dt>
          <dd class="truncate font-mono text-xs">{{ detail.key.key_id }}</dd>
        </template>
        <template v-if="detail.key.algorithm">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.algorithm') }}</dt>
          <dd>{{ detail.key.algorithm }}</dd>
        </template>
        <template v-if="detail.key.key_length">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.key_length') }}</dt>
          <dd>{{ detail.key.key_length }} bit</dd>
        </template>
        <template v-if="detail.key.curve">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.curve') }}</dt>
          <dd>{{ detail.key.curve }}</dd>
        </template>
        <template v-if="detail.key.issuer">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.issuer') }}</dt>
          <dd class="truncate" :title="detail.key.issuer">{{ detail.key.issuer }}</dd>
        </template>
        <template v-if="detail.key.serial">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.serial') }}</dt>
          <dd class="truncate font-mono text-xs">{{ detail.key.serial }}</dd>
        </template>
        <template v-if="detail.key.valid_from">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.valid_from') }}</dt>
          <dd>{{ new Date(detail.key.valid_from).toLocaleDateString() }}</dd>
        </template>
        <template v-if="detail.key.expires_at">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.expiry') }}</dt>
          <dd>{{ new Date(detail.key.expires_at).toLocaleDateString() }}</dd>
        </template>
        <template v-if="detail.key.created_at">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.created') }}</dt>
          <dd>{{ new Date(detail.key.created_at).toLocaleDateString() }}</dd>
        </template>
      </dl>

      <div v-if="detail.key.public_key">
        <div class="mb-1.5 flex items-center justify-between">
          <span class="text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.public_key') }}</span>
          <Btn variant="ghost" size="xs" icon="content_copy" @click="copyText(detail.key.public_key)">{{ t('common.copy') }}</Btn>
        </div>
        <textarea readonly rows="6" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 font-mono text-xs" :value="detail.key.public_key"></textarea>
      </div>
      <div v-if="detail.key.cert_pem">
        <div class="mb-1.5 flex items-center justify-between">
          <span class="text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.certificate') }}</span>
          <Btn variant="ghost" size="xs" icon="content_copy" @click="copyText(detail.key.cert_pem)">{{ t('common.copy') }}</Btn>
        </div>
        <textarea readonly rows="6" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 font-mono text-xs" :value="detail.key.cert_pem"></textarea>
      </div>

      <!-- Keyserver presence (PGP only) -->
      <div v-if="detail.key.type === 'pgp'">
        <div class="mb-1.5 flex items-center justify-between">
          <span class="text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.presence_title') }}</span>
          <Btn variant="ghost" size="xs" icon="refresh" :loading="presenceBusy" :disabled="!crypto.keyServers.length" @click="doCheckPresence(detail.key)">{{ t('mail.keys.presence_check') }}</Btn>
        </div>
        <p v-if="!crypto.keyServers.length" class="text-xs text-[var(--ll-muted)]">{{ t('mail.keys.servers_none') }}</p>
        <div v-else-if="presence.length" class="space-y-1">
          <div v-for="p in presence" :key="p.server_id" class="flex items-center gap-2 text-sm">
            <Icon :name="p.present ? 'check_circle' : 'cancel'" :size="16" :class="p.present ? 'text-green-600' : 'text-[var(--ll-muted)]'" />
            <span class="flex-1 truncate">{{ p.server_name }}</span>
            <Btn v-if="!p.present" variant="ghost" size="xs" :loading="publishBusy === p.server_id" @click="doPublish(detail.key, p.server_id)">{{ t('mail.keys.presence_publish') }}</Btn>
          </div>
        </div>
      </div>
    </div>
    <template #footer>
      <Btn v-if="detail.key" variant="ghost" size="sm" icon="lock_open" @click="openExport(detail.key)">{{ t('mail.keys.export') }}</Btn>
      <span class="flex-1"></span>
      <Btn variant="ghost" @click="detail.show = false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>

  <!-- Add recipient manually: paste an armored PGP public key or an S/MIME cert -->
  <Modal v-model="radd.show" :title="t('mail.keys.recipients_add')" width="560px">
    <div class="space-y-3">
      <TextField v-model="radd.label" :label="t('mail.keys.label')" />
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.title') }}</span>
        <div class="inline-flex rounded-lg border border-[var(--ll-border)] p-0.5">
          <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors" :class="radd.type === 'pgp' ? 'bg-primary-500 text-white' : 'text-[var(--ll-muted)]'" @click="radd.type = 'pgp'">PGP</button>
          <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors" :class="radd.type === 'smime' ? 'bg-primary-500 text-white' : 'text-[var(--ll-muted)]'" @click="radd.type = 'smime'">S/MIME</button>
        </div>
      </div>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ radd.type === 'pgp' ? t('mail.keys.recipient_material_pgp') : t('mail.keys.recipient_material_smime') }}</span>
        <textarea
          v-model="radd.material" rows="8"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 font-mono text-xs text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
          :placeholder="radd.type === 'pgp' ? '-----BEGIN PGP PUBLIC KEY BLOCK-----' : '-----BEGIN CERTIFICATE-----'"
        ></textarea>
      </label>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="radd.show = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="radd.busy" :disabled="!radd.label.trim() || !radd.material.trim()" @click="doAddRecipient">{{ t('mail.keys.add') }}</Btn>
    </template>
  </Modal>

  <!-- Keyserver search + import -->
  <Modal v-model="ksearch.show" :title="t('mail.keys.recipients_search')" width="640px">
    <div class="space-y-3">
      <div class="flex items-center gap-2">
        <TextField v-model="ksearch.query" :placeholder="t('mail.keys.search_placeholder')" icon="search" class="flex-1" @keyup.enter="doSearchKeyservers" />
        <Select v-model="ksearch.serverId" :options="serverPickOptions" class="w-44" />
        <Btn variant="solid" :loading="ksearch.busy" :disabled="!ksearch.query.trim() || !crypto.keyServers.length" @click="doSearchKeyservers">{{ t('common.search') }}</Btn>
      </div>
      <p v-if="!crypto.keyServers.length" class="text-sm text-[var(--ll-muted)]">{{ t('mail.keys.servers_none') }}</p>
      <div v-else-if="ksearch.busy" class="py-6 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
      <div v-else-if="ksearch.searched && !ksearch.results.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.keys.search_none') }}</div>
      <div v-else class="max-h-[50vh] divide-y divide-[var(--ll-border)] overflow-y-auto">
        <div v-for="(c, idx) in ksearch.results" :key="`${c.server_id}-${c.key_id}-${idx}`" class="flex items-start gap-3 py-2.5">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10"><Icon name="key" :size="18" /></span>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="truncate text-sm font-medium">{{ c.uids[0] ? formatCandidateUid(c.uids[0]) : c.key_id }}</span>
              <Badge v-if="c.revoked" tone="error">{{ t('mail.keys.search_revoked') }}</Badge>
              <Badge tone="gray">{{ c.server_name }}</Badge>
            </div>
            <div v-if="c.uids.length > 1" class="truncate text-xs text-[var(--ll-muted)]">{{ c.uids.slice(1).map(formatCandidateUid).join(', ') }}</div>
            <div class="truncate font-mono text-xs text-[var(--ll-muted)]">{{ c.fingerprint || c.key_id }}<span v-if="c.algorithm"> · {{ c.algorithm }}{{ c.bits ? ' ' + c.bits : '' }}</span></div>
          </div>
          <Btn variant="outline" size="sm" :loading="ksearch.importingId === c.key_id" :disabled="c.revoked" @click="doImportFromSearch(c)">{{ t('mail.keys.recipients_add') }}</Btn>
        </div>
      </div>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="ksearch.show = false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>

  <!-- Keyserver add/edit -->
  <Modal v-model="sform.show" :title="sform.id ? t('mail.keys.servers_edit') : t('mail.keys.servers_add')" width="520px">
    <div class="space-y-3">
      <TextField v-model="sform.name" :label="t('mail.keys.servers_name')" />
      <TextField v-model="sform.url" :label="t('mail.keys.servers_url')" placeholder="https://keys.openpgp.org" />
      <label class="flex items-center gap-2 text-sm">
        <input v-model="sform.enabled" type="checkbox" class="h-4 w-4 accent-primary-500">
        <span>{{ t('mail.keys.servers_enabled') }}</span>
      </label>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="sform.show = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="sform.busy" :disabled="!sform.name.trim() || !sform.url.trim()" @click="doSaveServer">{{ t('common.save') }}</Btn>
    </template>
  </Modal>

  <!-- Export private key (password-gated) -->
  <Modal v-model="exp.show" :title="t('mail.keys.export')" width="560px">
    <div class="space-y-3">
      <div class="flex items-start gap-2 rounded-lg border border-amber-400/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-300">
        <Icon name="warning" :size="18" class="mt-0.5 shrink-0" />
        <span>{{ t('mail.keys.export_warn') }}</span>
      </div>
      <TextField v-if="!exp.material" v-model="exp.password" :label="t('mail.keys.export_password')" type="password" autocomplete="current-password" :error="exp.err" @keyup.enter="doExport" />
      <template v-else>
        <div>
          <div class="mb-1.5 flex items-center justify-between">
            <span class="text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.export_private') }}</span>
            <Btn variant="ghost" size="xs" icon="content_copy" @click="copyText(exp.material)">{{ t('common.copy') }}</Btn>
          </div>
          <textarea readonly rows="8" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 font-mono text-xs" :value="exp.material"></textarea>
        </div>
        <div v-if="exp.certMaterial">
          <div class="mb-1.5 flex items-center justify-between">
            <span class="text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.certificate') }}</span>
            <Btn variant="ghost" size="xs" icon="content_copy" @click="copyText(exp.certMaterial)">{{ t('common.copy') }}</Btn>
          </div>
          <textarea readonly rows="6" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 font-mono text-xs" :value="exp.certMaterial"></textarea>
        </div>
      </template>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="exp.show = false">{{ t('common.close') }}</Btn>
      <Btn v-if="!exp.material" variant="solid" :loading="exp.busy" :disabled="!exp.password" @click="doExport">{{ t('mail.keys.export') }}</Btn>
    </template>
  </Modal>

  <!-- Import modal -->
  <Modal v-model="dlg.show" :title="dlg.type === 'pgp' ? t('mail.keys.import_pgp') : t('mail.keys.import_smime')" width="560px">
    <div class="space-y-3">
      <TextField v-model="dlg.label" :label="t('mail.keys.label')" />

      <!-- Source: upload from computer | choose from Files -->
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.source') }}</span>
        <div class="inline-flex rounded-lg border border-[var(--ll-border)] p-0.5">
          <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors" :class="dlg.source === 'upload' ? 'bg-primary-500 text-white' : 'text-[var(--ll-muted)]'" @click="dlg.source = 'upload'">{{ t('mail.keys.source_upload') }}</button>
          <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors" :class="dlg.source === 'files' ? 'bg-primary-500 text-white' : 'text-[var(--ll-muted)]'" @click="dlg.source = 'files'">{{ t('mail.keys.source_files') }}</button>
        </div>
      </div>

      <!-- Upload from computer -->
      <template v-if="dlg.source === 'upload'">
        <label v-if="dlg.type === 'pgp'" class="block">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.armored_private_key') }}</span>
          <textarea
            v-model="dlg.armored" rows="6"
            class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 font-mono text-xs text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
            placeholder="-----BEGIN PGP PRIVATE KEY BLOCK-----"
          ></textarea>
        </label>
        <label v-else class="block">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.p12_file') }}</span>
          <input type="file" accept=".p12,.pfx" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary-500/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-600 dark:file:text-primary-300" @change="onP12">
        </label>
      </template>

      <!-- Choose from Files -->
      <div v-else class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.pick_file') }}</span>
        <div class="flex items-center gap-2">
          <Btn variant="outline" size="sm" icon="folder" @click="openPicker">{{ dlg.fileName || t('mail.keys.pick_file') }}</Btn>
          <Btn v-if="dlg.fileId != null" variant="ghost" size="sm" icon="close" @click="dlg.fileId = null; dlg.fileName = ''" />
        </div>
      </div>

      <TextField v-model="dlg.passphrase" :label="t('mail.keys.passphrase')" type="password" autocomplete="new-password" />
    </div>
    <template #footer>
      <Btn variant="ghost" @click="dlg.show = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="dlg.busy" :disabled="!canImport" @click="doImport">{{ t('mail.keys.add') }}</Btn>
    </template>
  </Modal>

  <!-- Files picker modal -->
  <Modal v-model="picker.show" :title="t('mail.keys.pick_file')" width="560px">
    <TextField v-model="picker.q" :placeholder="t('common.search')" icon="search" inputmode="search" class="mb-3" />
    <div v-if="picker.loading" class="py-6 text-center"><Icon name="progress_activity" :size="24" class="animate-spin text-[var(--ll-muted)]" /></div>
    <div v-else-if="!pickerFiles.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.keys.none') }}</div>
    <div v-else class="max-h-[50vh] divide-y divide-[var(--ll-border)] overflow-y-auto">
      <button v-for="f in pickerFiles" :key="f.id" type="button" class="flex w-full items-center gap-3 py-2.5 text-left hover:bg-black/[0.03] dark:hover:bg-white/5" @click="chooseFile(f)">
        <Icon name="description" :size="20" class="shrink-0 text-[var(--ll-muted)]" />
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm">{{ f.name }}</span>
          <span class="block truncate text-xs text-[var(--ll-muted)]">{{ f.mime }}</span>
        </span>
      </button>
    </div>
  </Modal>

  <!-- Generate modal -->
  <Modal v-model="gen.show" :title="gen.type === 'pgp' ? t('mail.keys.generate_pgp') : t('mail.keys.generate_smime')" width="600px">
    <div v-if="gen.unavailable" class="mb-3 flex items-start gap-2 rounded-lg border border-amber-400/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-300">
      <Icon name="warning" :size="18" class="mt-0.5 shrink-0" />
      <span>{{ t('mail.keys.toolchain_unavailable') }}</span>
    </div>

    <div class="space-y-4">
      <!-- Type segmented -->
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.title') }}</span>
        <div class="inline-flex rounded-lg border border-[var(--ll-border)] p-0.5">
          <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors" :class="gen.type === 'pgp' ? 'bg-primary-500 text-white' : 'text-[var(--ll-muted)]'" @click="gen.type = 'pgp'">PGP</button>
          <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors" :class="gen.type === 'smime' ? 'bg-primary-500 text-white' : 'text-[var(--ll-muted)]'" @click="gen.type = 'smime'">S/MIME</button>
        </div>
      </div>

      <TextField v-model="gen.label" :label="t('mail.keys.label')" />

      <!-- Identities -->
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.identities') }}</span>
        <div class="space-y-2">
          <div v-for="(id, i) in gen.identities" :key="i" class="grid grid-cols-1 gap-2 rounded-lg border border-[var(--ll-border)] p-2.5 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <TextField v-model="id.name" :placeholder="t('mail.keys.identity_name')" />
            <TextField v-model="id.email" :placeholder="t('mail.keys.identity_email')" type="email" inputmode="email" :error="id.email.trim() !== '' && !isEmail(id.email) ? ' ' : undefined" />
            <TextField v-model="id.comment" :placeholder="t('mail.keys.identity_comment')" />
            <Btn v-if="gen.identities.length > 1" variant="ghost" size="sm" icon="delete" class="self-center text-red-600" @click="gen.identities.splice(i, 1)" />
          </div>
        </div>
        <Btn variant="ghost" size="sm" icon="add" class="mt-2" @click="gen.identities.push({ name: '', email: '', comment: '' })">{{ t('mail.keys.add_identity') }}</Btn>
      </div>

      <!-- PGP-only: algorithm + key length / curve + signing subkey -->
      <template v-if="gen.type === 'pgp'">
        <Select v-model="gen.algorithm" :label="t('mail.keys.algorithm')" :options="algoOptions" />
        <Select v-if="gen.algorithm === 'rsa'" v-model.number="gen.keyLength" :label="t('mail.keys.key_length')" :options="keyLengthOptions" />
        <Select v-else v-model="gen.curve" :label="t('mail.keys.curve')" :options="curveOptions" />
        <label class="flex items-center gap-2 text-sm">
          <input v-model="gen.signingSubkey" type="checkbox" class="h-4 w-4 accent-primary-500">
          <span>{{ t('mail.keys.signing_subkey') }}</span>
        </label>
      </template>

      <!-- S/MIME: key length -->
      <Select v-else v-model.number="gen.keyLength" :label="t('mail.keys.key_length')" :options="keyLengthOptions" />

      <!-- Expiry -->
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.expiry') }}</span>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="gen.neverExpire" type="checkbox" class="h-4 w-4 accent-primary-500">
          <span>{{ t('mail.keys.expiry_never') }}</span>
        </label>
        <TextField v-if="!gen.neverExpire" v-model="gen.expireYears" :label="t('mail.keys.expire_years')" type="number" inputmode="numeric" class="mt-2" />
      </div>

      <!-- Passphrase -->
      <div>
        <TextField v-model="gen.passphrase" :label="t('mail.keys.passphrase_optional')" type="password" autocomplete="new-password" />
        <p v-if="!gen.passphrase" class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ t('mail.keys.passphrase_none_warn') }}</p>
      </div>
    </div>

    <template #footer>
      <Btn variant="ghost" @click="gen.show = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="gen.busy" :disabled="!canGenerate" @click="doGenerate">{{ t('mail.keys.generate') }}</Btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Select, Badge, Modal } from '@spa/ui';
import { useMailStore, type MailKey, type MailKeyParsedIdentity, type MailKeyGenerateBody, type MailKeyImportBody, type MailKeyCurve } from '@spa/stores/mail';
import { useFilesStore, type FileEntry } from '@spa/stores/files';
import { useCryptoStore, type Recipient, type KeyServerEntry, type KeyserverCandidate, type PresenceResult } from '@spa/stores/crypto';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { ApiError } from '@spa/api/client';

const s = useMailStore();
const files = useFilesStore();
const crypto = useCryptoStore();
const { success, error } = useToast();
const keys = ref<MailKey[]>([]);
const loading = ref(false);
const recipientsLoading = ref(false);

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const isEmail = (v: string) => EMAIL_RE.test(v.trim());

// --- Import ---------------------------------------------------------------
const dlg = reactive<{
  show: boolean; busy: boolean; type: 'pgp' | 'smime';
  label: string; source: 'upload' | 'files';
  armored: string; p12: string | null; fileId: number | null; fileName: string; passphrase: string;
}>({ show: false, busy: false, type: 'pgp', label: '', source: 'upload', armored: '', p12: null, fileId: null, fileName: '', passphrase: '' });

const canImport = computed(() => {
  if (dlg.label.trim() === '') return false;
  if (dlg.source === 'files') return dlg.fileId != null;
  return dlg.type === 'pgp' ? dlg.armored.trim() !== '' : dlg.p12 !== null;
});

function openImport(type: 'pgp' | 'smime') {
  Object.assign(dlg, { show: true, busy: false, type, label: '', source: 'upload', armored: '', p12: null, fileId: null, fileName: '', passphrase: '' });
}

function onP12(ev: Event) {
  const file = (ev.target as HTMLInputElement).files?.[0];
  if (!file) { dlg.p12 = null; return; }
  const reader = new FileReader();
  reader.onload = () => { const r = String(reader.result); dlg.p12 = r.slice(r.indexOf(',') + 1); }; // strip data: prefix → base64
  reader.readAsDataURL(file);
}

async function doImport() {
  if (!canImport.value) return;
  dlg.busy = true;
  try {
    const body: MailKeyImportBody = {
      type: dlg.type,
      label: dlg.label.trim(),
      source: dlg.source,
      passphrase: dlg.passphrase || null,
    };
    if (dlg.source === 'files') {
      body.file_id = dlg.fileId;
    } else if (dlg.type === 'pgp') {
      body.armored_private_key = dlg.armored;
    } else {
      body.p12_base64 = dlg.p12;
    }
    await s.importKey(body);
    dlg.show = false;
    await load();
    success(t('mail.keys.imported'));
  } catch { error(t('mail.keys.import_failed')); } finally { dlg.busy = false; }
}

// --- Files picker ---------------------------------------------------------
const picker = reactive<{ show: boolean; loading: boolean; q: string }>({ show: false, loading: false, q: '' });

async function openPicker() {
  picker.show = true; picker.q = '';
  if (!files.files.length) {
    picker.loading = true;
    try { await files.load(); } catch { error(t('common.error')); } finally { picker.loading = false; }
  }
}

const pickerFiles = computed(() => {
  const q = picker.q.trim().toLowerCase();
  return q ? files.files.filter((f) => f.name.toLowerCase().includes(q)) : files.files;
});

function chooseFile(f: FileEntry) {
  dlg.fileId = f.id; dlg.fileName = f.name; picker.show = false;
}

// --- Generate -------------------------------------------------------------
const gen = reactive<{
  show: boolean; busy: boolean; unavailable: boolean; type: 'pgp' | 'smime';
  label: string; identities: { name: string; email: string; comment: string }[];
  passphrase: string; neverExpire: boolean; expireYears: string;
  algorithm: 'rsa' | 'ecc'; keyLength: number; curve: MailKeyCurve; signingSubkey: boolean;
}>({
  show: false, busy: false, unavailable: false, type: 'pgp', label: '',
  identities: [{ name: '', email: '', comment: '' }],
  passphrase: '', neverExpire: true, expireYears: '2',
  algorithm: 'ecc', keyLength: 3072, curve: 'ed25519', signingSubkey: false,
});

const algoOptions = computed(() => [
  { title: t('mail.keys.algo_ecc'), value: 'ecc' },
  { title: t('mail.keys.algo_rsa'), value: 'rsa' },
]);
const keyLengthOptions = [
  { title: '2048', value: 2048 },
  { title: '3072', value: 3072 },
  { title: '4096', value: 4096 },
];
const curveOptions: { title: string; value: MailKeyCurve }[] = [
  { title: 'ed25519', value: 'ed25519' },
  { title: 'nistp256', value: 'nistp256' },
  { title: 'nistp384', value: 'nistp384' },
  { title: 'nistp521', value: 'nistp521' },
  { title: 'brainpoolP256r1', value: 'brainpoolP256r1' },
  { title: 'brainpoolP384r1', value: 'brainpoolP384r1' },
  { title: 'brainpoolP512r1', value: 'brainpoolP512r1' },
];

const canGenerate = computed(() => gen.label.trim() !== '' && gen.identities.some((i) => isEmail(i.email)));

function openGenerate() {
  Object.assign(gen, {
    show: true, busy: false, unavailable: false, type: 'pgp', label: '',
    identities: [{ name: '', email: '', comment: '' }],
    passphrase: '', neverExpire: true, expireYears: '2',
    algorithm: 'ecc', keyLength: 3072, curve: 'ed25519', signingSubkey: false,
  });
}

async function doGenerate() {
  if (!canGenerate.value) return;
  gen.busy = true;
  gen.unavailable = false;
  try {
    const identities = gen.identities
      .filter((i) => isEmail(i.email))
      .map((i) => ({ email: i.email.trim(), name: i.name.trim() || null, comment: i.comment.trim() || null }));
    const years = Math.min(100, Math.max(1, Math.trunc(Number(gen.expireYears) || 1)));
    const body: MailKeyGenerateBody = {
      type: gen.type,
      label: gen.label.trim(),
      identities,
      passphrase: gen.passphrase || null,
      expire_years: gen.neverExpire ? null : years,
    };
    if (gen.type === 'pgp') {
      body.algorithm = gen.algorithm;
      if (gen.algorithm === 'rsa') body.key_length = gen.keyLength;
      else body.curve = gen.curve;
      body.signing_subkey = gen.signingSubkey;
    } else {
      body.key_length = gen.keyLength;
    }
    await s.generateKey(body);
    gen.show = false;
    await load();
    success(t('mail.keys.generated'));
  } catch (e) {
    if (e instanceof ApiError && e.status === 501) { gen.unavailable = true; }
    else { error(t('mail.keys.generate_failed')); }
  } finally { gen.busy = false; }
}

// --- List -----------------------------------------------------------------
onMounted(() => { load(); loadRecipients(); crypto.loadKeyServers().catch(() => {}); });
async function load() {
  loading.value = true;
  try { keys.value = (await s.loadKeys()).keys; } catch { error(t('common.error')); } finally { loading.value = false; }
}
async function loadRecipients() {
  recipientsLoading.value = true;
  try { await crypto.load(); } catch { error(t('common.error')); } finally { recipientsLoading.value = false; }
}

async function copyText(text: string) {
  try { await navigator.clipboard.writeText(text); success(t('mail.keys.copied')); } catch { error(t('common.error')); }
}
async function remove(k: MailKey) {
  if (!await confirmAsk(t('mail.keys.delete_confirm'), { danger: true })) return;
  try {
    await s.deleteKey(k.id);
    keys.value = keys.value.filter((x) => x.id !== k.id);
    if (detail.key?.id === k.id) detail.show = false;
  } catch { error(t('common.error')); }
}

// --- Detail modal -----------------------------------------------------------
const detail = reactive<{ show: boolean; key: MailKey | null }>({ show: false, key: null });
function openDetail(k: MailKey) { detail.key = k; detail.show = true; presence.value = []; }

/** "Name (Comment) <email>" — whichever parts a parsed identity actually has. */
function formatIdentity(i: MailKeyParsedIdentity): string {
  const parts: string[] = [];
  if (i.name) parts.push(i.name);
  if (i.comment) parts.push(`(${i.comment})`);
  if (i.email) parts.push(`<${i.email}>`);
  return parts.length ? parts.join(' ') : t('mail.keys.identities_none');
}
/** Same shape, for a keyserver search result's uid — name/email/comment already split by the server. */
function formatCandidateUid(u: { name: string | null; email: string | null; comment: string | null }): string {
  const parts: string[] = [];
  if (u.name) parts.push(u.name);
  if (u.comment) parts.push(`(${u.comment})`);
  if (u.email) parts.push(`<${u.email}>`);
  return parts.length ? parts.join(' ') : '?';
}

// --- Recipients (other people's public keys/certs) ------------------------
const radd = reactive<{ show: boolean; busy: boolean; type: 'pgp' | 'smime'; label: string; material: string }>(
  { show: false, busy: false, type: 'pgp', label: '', material: '' },
);
function openRecipientAdd() { Object.assign(radd, { show: true, busy: false, type: 'pgp', label: '', material: '' }); }
async function doAddRecipient() {
  if (!radd.label.trim() || !radd.material.trim()) return;
  radd.busy = true;
  try {
    const recipient = await crypto.importRecipient({ type: radd.type, label: radd.label.trim(), material: radd.material.trim() });
    crypto.recipients.push(recipient);
    radd.show = false;
    success(t('mail.keys.recipient_added'));
  } catch { error(t('mail.keys.recipient_add_failed')); } finally { radd.busy = false; }
}

async function removeRecipient(r: Recipient) {
  if (!await confirmAsk(t('mail.keys.recipient_delete_confirm'), { danger: true })) return;
  try {
    await crypto.deleteRecipient(r.id);
    crypto.recipients = crypto.recipients.filter((x) => x.id !== r.id);
  } catch { error(t('common.error')); }
}

const refreshingId = ref<number | null>(null);
async function doRefreshRecipient(r: Recipient) {
  refreshingId.value = r.id;
  try {
    const updated = await crypto.refreshRecipient(r.id);
    const idx = crypto.recipients.findIndex((x) => x.id === r.id);
    if (idx !== -1) crypto.recipients[idx] = updated;
    success(t('mail.keys.recipient_refreshed'));
  } catch (e) {
    // A manually-pasted recipient (no known origin server) is searched
    // across every enabled keyserver instead of just one — distinguish the
    // outcomes by the server's `error` code, not just the HTTP status,
    // since both the known-origin and search-fallback paths can now 404
    // ("not found") or 422 ("nothing to search with"/"mismatch").
    const code = e instanceof ApiError && e.body && typeof e.body === 'object' && 'error' in e.body
      ? String((e.body as { error: unknown }).error) : null;
    if (code === 'no_servers') error(t('mail.keys.recipient_refresh_no_servers'));
    else if (code === 'fingerprint_mismatch') error(t('mail.keys.recipient_refresh_mismatch'));
    else if (e instanceof ApiError && e.status === 404) error(t('mail.keys.recipient_refresh_gone'));
    else error(t('common.error'));
  } finally { refreshingId.value = null; }
}

// --- Keyservers (HKP) -------------------------------------------------------
const sform = reactive<{ show: boolean; busy: boolean; id: number | null; name: string; url: string; enabled: boolean }>(
  { show: false, busy: false, id: null, name: '', url: '', enabled: true },
);
function openServerForm(srv: KeyServerEntry | null) {
  Object.assign(sform, srv
    ? { show: true, busy: false, id: srv.id, name: srv.name, url: srv.url, enabled: srv.enabled }
    : { show: true, busy: false, id: null, name: '', url: '', enabled: true });
}
async function doSaveServer() {
  if (!sform.name.trim() || !sform.url.trim()) return;
  sform.busy = true;
  try {
    if (sform.id != null) {
      const updated = await crypto.updateKeyServer(sform.id, { name: sform.name.trim(), url: sform.url.trim(), enabled: sform.enabled });
      const idx = crypto.keyServers.findIndex((x) => x.id === sform.id);
      if (idx !== -1) crypto.keyServers[idx] = updated;
    } else {
      crypto.keyServers.push(await crypto.createKeyServer({ name: sform.name.trim(), url: sform.url.trim(), enabled: sform.enabled }));
    }
    sform.show = false;
    success(t('mail.keys.servers_saved'));
  } catch { error(t('common.error')); } finally { sform.busy = false; }
}
async function removeServer(srv: KeyServerEntry) {
  if (!await confirmAsk(t('mail.keys.servers_delete_confirm'), { danger: true })) return;
  try {
    await crypto.deleteKeyServer(srv.id);
    crypto.keyServers = crypto.keyServers.filter((x) => x.id !== srv.id);
  } catch { error(t('common.error')); }
}

// --- Keyserver search + import ---------------------------------------------
const ksearch = reactive<{
  show: boolean; busy: boolean; searched: boolean; query: string; serverId: string;
  results: KeyserverCandidate[]; importingId: string | null;
}>({ show: false, busy: false, searched: false, query: '', serverId: '', results: [], importingId: null });

const serverPickOptions = computed(() => [
  { title: t('mail.keys.search_all_servers'), value: '' },
  ...crypto.keyServers.filter((x) => x.enabled).map((x) => ({ title: x.name, value: String(x.id) })),
]);

function openKeyserverSearch() {
  Object.assign(ksearch, { show: true, busy: false, searched: false, query: '', serverId: '', results: [], importingId: null });
}
async function doSearchKeyservers() {
  const q = ksearch.query.trim();
  if (!q) return;
  ksearch.busy = true;
  try {
    ksearch.results = await crypto.searchKeyservers(q, ksearch.serverId ? Number(ksearch.serverId) : undefined);
    ksearch.searched = true;
  } catch { error(t('mail.keys.search_failed')); } finally { ksearch.busy = false; }
}
async function doImportFromSearch(c: KeyserverCandidate) {
  ksearch.importingId = c.key_id;
  try {
    const label = c.uids[0] ? formatCandidateUid(c.uids[0]) : c.key_id;
    const recipient = await crypto.importFromKeyserver(c.server_id, c.key_id, label);
    crypto.recipients.push(recipient);
    success(t('mail.keys.recipient_added'));
  } catch { error(t('mail.keys.recipient_add_failed')); } finally { ksearch.importingId = null; }
}

// --- Own-key keyserver presence + publish + export --------------------------
const presence = ref<PresenceResult[]>([]);
const presenceBusy = ref(false);
const publishBusy = ref<number | null>(null);

async function doCheckPresence(k: MailKey) {
  presenceBusy.value = true;
  try { presence.value = await crypto.checkPresence(k.id); } catch { error(t('common.error')); } finally { presenceBusy.value = false; }
}
async function doPublish(k: MailKey, serverId: number) {
  publishBusy.value = serverId;
  try {
    await crypto.publishKey(k.id, serverId);
    await doCheckPresence(k);
    success(t('mail.keys.presence_published'));
  } catch { error(t('mail.keys.presence_publish_failed')); } finally { publishBusy.value = null; }
}

const exp = reactive<{ show: boolean; busy: boolean; keyId: number | null; password: string; err: string; material: string; certMaterial: string }>(
  { show: false, busy: false, keyId: null, password: '', err: '', material: '', certMaterial: '' },
);
function openExport(k: MailKey) {
  Object.assign(exp, { show: true, busy: false, keyId: k.id, password: '', err: '', material: '', certMaterial: '' });
}
async function doExport() {
  if (exp.keyId == null || !exp.password) return;
  exp.busy = true;
  exp.err = '';
  try {
    const r = await s.exportKey(exp.keyId, exp.password);
    exp.material = r.private_key;
    exp.certMaterial = r.cert_pem || '';
  } catch (e) {
    if (e instanceof ApiError && e.fields?.current_password?.length) exp.err = e.fields.current_password[0];
    else error(t('common.error'));
  } finally { exp.busy = false; }
}
</script>
