<template>
  <div class="flex flex-wrap gap-2" :aria-busy="busy">
    <Btn v-if="canStartVersion" data-action="version" variant="outline" :disabled="busy" @click="$emit('version')">
      {{ t('invoices.quote_new_version') }}
    </Btn>
    <Btn v-if="quote.has_pending_draft" data-action="edit" tag="router-link" :to="{ name: 'finance.quotes.edit', params: { quote: quote.id } }" variant="outline">
      {{ t('common.edit') }}
    </Btn>
    <Btn v-if="quote.has_pending_draft" data-action="publish" :disabled="busy" @click="$emit('publish')">
      {{ t('invoices.quote_publish') }}
    </Btn>
    <Btn v-if="canSend" data-action="send" :disabled="busy || sendUncertain" @click="$emit('send')">
      {{ t(sendUncertain ? 'invoices.quote_delivery_uncertain' : quote.delivery?.state === 'failed' ? 'invoices.quote_send_retry' : 'invoices.quote_send') }}
    </Btn>
    <Btn data-action="accept" variant="soft" :disabled="busy || decisionBlocked" @click="$emit('accept')">
      {{ t('invoices.quote_accept') }}
    </Btn>
    <Btn data-action="decline" variant="outline" :disabled="busy || decisionBlocked" @click="$emit('decline')">
      {{ t('invoices.quote_decline') }}
    </Btn>
    <Btn data-action="duplicate" variant="outline" :disabled="busy" @click="$emit('duplicate')">
      {{ t('invoices.quote_duplicate') }}
    </Btn>
    <Btn v-if="quote.status === 'accepted'" data-action="convert" :disabled="busy || quote.has_pending_draft" @click="$emit('convert')">
      {{ t('invoices.quote_to_invoice') }}
    </Btn>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Btn } from '@spa/ui';
import type { Quote } from '@spa/modules/finance/models/quote';

const props = defineProps<{ quote: Quote; busy: boolean }>();
defineEmits<{ version: []; publish: []; send: []; accept: []; decline: []; duplicate: []; convert: [] }>();

const decisionBlocked = computed(() => props.quote.has_pending_draft
  || props.quote.current_revision === null
  || props.quote.status !== 'sent');
const canSend = computed(() => props.quote.current_revision !== null && !props.quote.has_pending_draft && props.quote.status === 'sent');
const canStartVersion = computed(() => props.quote.current_revision !== null && !props.quote.has_pending_draft && props.quote.status === 'sent');
const sendUncertain = computed(() => props.quote.delivery?.last_error_code === 'delivery_outcome_uncertain');
</script>
