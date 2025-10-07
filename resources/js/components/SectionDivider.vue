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
  <div v-if="!isPdf" style="display: flex; align-items: center; margin: 16px 0;">
    <div style="flex-grow: 1; border-bottom: 1px solid #000; height: 1px;"></div>
    <span style="padding: 0 12px; font-weight: bold; white-space: nowrap;">{{ text }}</span>
    <div style="flex-grow: 1; border-bottom: 1px solid #000; height: 1px;"></div>
  </div>

   <div v-else style="text-align: center; margin: 16px 0;">
    <hr :style="{ width: width, border: '0.5px solid #000', display: 'inline-block', verticalAlign: 'middle' }" >
    <span class="divider-text">{{ text }}</span>
    <hr :style="{ width: width, border: '0.5px solid #000', display: 'inline-block', verticalAlign: 'middle' }" >
  </div>
</template>
