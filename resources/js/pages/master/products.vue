<script setup>
import InventoryProductsPage from '@/pages/inventory/products/index.vue'
</script>

<template>
  <div>
    <v-alert
      type="info"
      variant="tonal"
      color="primary"
      density="compact"
      class="mb-4"
      border="start"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div>
          <strong>CATÁLOGO MAESTRO GLOBAL (tovaerp_master) — Inventario</strong>
          <span class="text-body-2 ms-2">
            Los productos creados o modificados aquí actualizan el diccionario oficial de todas las farmacias.
          </span>
        </div>
        <v-chip
          color="primary"
          size="small"
          variant="flat"
        >
          MASTER
        </v-chip>
      </div>
    </v-alert>

    <InventoryProductsPage />
  </div>
</template>
