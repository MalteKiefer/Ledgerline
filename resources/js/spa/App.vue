<template>
  <v-app>
    <router-view />
    <v-snackbar v-model="toastState.show" :color="toastState.color" location="bottom right" timeout="4000">
      {{ toastState.text }}
    </v-snackbar>
  </v-app>
</template>

<script setup lang="ts">
import { toastState } from '@spa/composables/useToast';
</script>
