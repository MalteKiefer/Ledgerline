<template>
  <v-container class="fill-height" fluid>
    <v-row justify="center" align="center">
      <v-col cols="12" class="text-center">
        <div class="text-h2 text-medium-emphasis mb-2">404</div>
        <p class="text-medium-emphasis mb-4">{{ t('errors.not_found') }}</p>
        <v-btn color="primary" variant="tonal" to="/">{{ t('common.back') }}</v-btn>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
</script>
