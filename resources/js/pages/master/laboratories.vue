<script setup>
import InventoryLaboratoriesPage from '@/pages/inventory/laboratories.vue'
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
          <strong>CATÁLOGO MAESTRO GLOBAL (tovaerp_master) — Laboratorios y Grupos Corporativos</strong>
          <span class="text-body-2 ms-2">
            Los cambios se sincronizan automáticamente con el mismo ID hacia todos los tenants.
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

    <InventoryLaboratoriesPage />
  </div>
</template>
