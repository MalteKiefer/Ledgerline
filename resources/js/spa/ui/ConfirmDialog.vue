<template>
  <Modal :model-value="confirmState.open" :title="confirmState.title || t('common.confirm_title')" width="440px" @update:model-value="(v) => { if (!v) confirmCancel(); }">
    <p class="text-sm text-[var(--ll-fg)]">{{ confirmState.message }}</p>
    <TextField
      v-if="confirmState.mode === 'prompt'"
      v-model="confirmState.value"
      class="mt-3"
      :placeholder="confirmState.placeholder"
      autofocus
      @enter="confirmResolve"
    />
    <template #footer>
      <Btn variant="ghost" @click="confirmCancel">{{ confirmState.cancelLabel || t('common.cancel') }}</Btn>
      <Btn :variant="confirmState.danger ? 'danger' : 'solid'" @click="confirmResolve">
        {{ confirmState.confirmLabel || (confirmState.mode === 'prompt' ? t('common.save') : t('common.confirm')) }}
      </Btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import { Modal, TextField, Btn } from '@spa/ui';
import { confirmState, confirmResolve, confirmCancel } from '@spa/composables/useConfirm';
</script>
