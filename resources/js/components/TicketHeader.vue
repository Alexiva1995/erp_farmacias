<script setup>
import { computed } from 'vue';
import { useBrandingStore } from '@/stores/useBrandingStore';
import { BASE64_LOGO_DATA } from '@/constants/logo.js';

const props = defineProps({
  logoSrc: {
    type: String,
    default: '',
  },
});

const brandingStore = useBrandingStore();

const currentLogo = computed(() => {
  if (brandingStore.settings?.app_logo) {
    return brandingStore.settings.app_logo;
  }
  if (props.logoSrc) {
    return props.logoSrc;
  }
  return BASE64_LOGO_DATA;
});
</script>

<template>
  <div class="text-center pa-2 mb-2">
    <a href="#">
      <img width="130" :src="currentLogo" alt="Logotipo de la marca" style="max-height: 80px; object-fit: contain;" />
    </a>
  </div>
  <div class="text-center">
    <span class="headerPrint font-weight-black text-uppercase">{{ brandingStore.settings?.app_name || 'TOVA' }}</span>
  </div>
</template>
