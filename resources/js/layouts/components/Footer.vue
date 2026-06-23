<script setup>
import { useBrandingStore } from '@/stores/useBrandingStore'

const brandingStore = useBrandingStore()
</script>

<template>
  <div class="h-100 d-flex align-center justify-md-space-between justify-center flex-wrap gap-y-2 text-caption">
    <!-- 👉 Footer: left content -->
    <span class="d-flex align-center text-medium-emphasis">
      &copy;
      {{ new Date().getFullYear() }}
      &nbsp;{{ brandingStore.settings.app_name || 'TOVA' }}
    </span>
    <!-- 👉 Footer: right content -->
    <span class="d-flex align-center text-xs text-medium-emphasis tracking-wider">
      BY&nbsp;
      <a
        href="https://tovaerp.com/"
        target="_blank"
        rel="noopener noreferrer"
        class="text-dark font-weight-bold text-decoration-none hover-opacity"
        style="letter-spacing: 2px;"
      >
        TOVA
      </a>
    </span>
  </div>
</template>
