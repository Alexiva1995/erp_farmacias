<script setup>
import SuppliersListPage from '@/pages/suppliers/list.vue'
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
          <strong>CATÁLOGO MAESTRO GLOBAL (tovaerp_master) — Proveedores y Estructuras de Conexión</strong>
          <span class="text-body-2 ms-2">
            Los datos fiscales y estructuras técnicas (FTP/API/Excel) se propagan a todos los tenants sin alterar sus usuarios ni contraseñas.
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

    <SuppliersListPage />
  </div>
</template>
