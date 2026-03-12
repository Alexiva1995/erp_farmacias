<script setup>
import { defineProps } from 'vue';

const props = defineProps({
  text: {
    type: String,
    required: true,
  },
  isPdf: {
    type: Boolean,
    default: false,
  },
  width: {
    type: String,
    default: true,
  },
});
</script>

<template>
  <div v-if="!isPdf" style="display: flex; align-items: center; margin-block: 16px; margin-inline: 0;">
    <div style="flex-grow: 1; block-size: 1px; border-block-end: 1px solid #000;"></div>
    <span style=" font-weight: bold;padding-block: 0; padding-inline: 12px; white-space: nowrap;">{{ text }}</span>
    <div style="flex-grow: 1; block-size: 1px; border-block-end: 1px solid #000;"></div>
  </div>

   <div v-else style=" margin-block: 16px; margin-inline: 0;text-align: center;">
    <hr :style="{ width: width, border: '0.5px solid #000', display: 'inline-block', verticalAlign: 'middle' }" >
    <span class="divider-text">{{ text }}</span>
    <hr :style="{ width: width, border: '0.5px solid #000', display: 'inline-block', verticalAlign: 'middle' }" >
  </div>
</template>
