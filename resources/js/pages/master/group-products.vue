<script setup>
import InventoryGroupProductsPage from '@/pages/inventory/group-products.vue'
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
          <strong>CATÁLOGO MAESTRO GLOBAL (tovaerp_master) — Grupos de Productos</strong>
          <span class="text-body-2 ms-2">
            Los grupos administrados aquí se replican automáticamente en todas las sucursales.
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

    <InventoryGroupProductsPage />
  </div>
</template>
