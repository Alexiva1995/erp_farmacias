<script setup>
import { useBrandingStore } from '@/stores/useBrandingStore'

const brandingStore = useBrandingStore()
</script>

<template>
  <div class="h-100 d-flex align-center justify-md-space-between justify-center">
    <!-- 👉 Footer: left content -->
    <span class="d-flex align-center text-medium-emphasis">
      &copy;
      {{ new Date().getFullYear() }}
      {{ brandingStore.settings.footer_text || 'Todos los derechos reservados' }}
    </span>
    <!-- 👉 Footer: right content -->
    <span class="d-md-flex gap-x-4 text-primary d-none">
    </span>
  </div>
</template>
