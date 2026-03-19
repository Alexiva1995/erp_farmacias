<script setup>
import { ref } from 'vue';

const props = defineProps({
  search: { type: String, default: "" },
  showActiveEmployees: { type: Boolean, default: true },
});

const emit = defineEmits(["update:search", "update:showActiveEmployees", "clear", "add-employee"]);

const isCollapsed = ref(true);

const options = [
  {
    title: "Activos",
    value: true,
  },
  {
    title: "Inactivos / Despedidos",
    value: false,
  },
];
</script>

<template>
  <VCard class="mb-6 filter-card-premium overflow-hidden border-0">
    <VCardText class="pa-0">
      <!-- Sección de Búsqueda y Acciones Principales -->
      <div class="pa-4 d-flex align-center gap-3 bg-white flex-wrap flex-sm-nowrap">
        <div class="flex-grow-1 min-width-200">
          <AppTextField
            :model-value="props.search"
            placeholder="Buscar por nombre, identificación o correo..."
            clearable
            prepend-inner-icon="tabler-search"
            @update:model-value="emit('update:search', $event)"
          />
        </div>

        <div class="d-flex gap-2 w-100 w-sm-auto justify-end">
          <VBtn
            variant="tonal"
            :color="isCollapsed ? 'secondary' : 'primary'"
            @click="isCollapsed = !isCollapsed"
            class="rounded-xl font-weight-bold"
          >
            <VIcon :icon="isCollapsed ? 'tabler-filter' : 'tabler-filter-off'" class="me-1" />
            <span class="d-none d-md-inline">{{ isCollapsed ? 'Filtros' : 'Ocultar' }}</span>
          </VBtn>

          <VBtn
            color="primary"
            variant="flat"
            class="rounded-xl font-weight-black shadow-primary-sm"
            @click="emit('add-employee')"
          >
            <VIcon icon="tabler-plus" class="me-1" />
            <span>NUEVO</span>
          </VBtn>
        </div>
      </div>

      <!-- Filtros Avanzados Colapsables -->
      <VExpandTransition>
        <div v-show="!isCollapsed" class="pa-4 border-top bg-light">
          <VRow align="center">
            <VCol cols="12" sm="6" md="4">
              <VSelect
                label="Estado del Empleado"
                variant="outlined"
                density="comfortable"
                hide-details
                :items="options"
                :model-value="props.showActiveEmployees"
                prepend-inner-icon="tabler-users-group"
                @update:model-value="emit('update:showActiveEmployees', $event)"
              />
            </VCol>
            
            <VSpacer class="d-none d-md-block" />

            <VCol cols="12" sm="6" md="auto">
              <VBtn
                variant="text"
                color="secondary"
                class="font-weight-bold"
                size="small"
                @click="emit('clear')"
              >
                <VIcon icon="tabler-refresh" class="me-1" />
                LIMPIAR FILTROS
              </VBtn>
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.filter-card-premium {
  box-shadow: 0 4px 18px -4px rgba(var(--v-shadow-key-umbra-color), 0.1) !important;
}

.bg-light {
  background-color: #f8fafc !important;
}

.border-top {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.shadow-primary-sm {
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.25) !important;
}

.min-width-200 {
  min-inline-size: 200px;
}

@media (max-width: 600px) {
  .w-100 {
    inline-size: 100% !important;
  }
}
</style>
