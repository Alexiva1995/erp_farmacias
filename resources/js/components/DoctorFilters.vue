<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  buscador: String,
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
});

const emit = defineEmits([
  "update:buscador",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "add-doctor",
  "export-pdf",
  "export-excel",
]);

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return props.fechaDesde_filtro || props.fechaHasta_filtro;
});

const handleClear = () => {
  emit('clear');
};
</script>

<template>
  <VCard class="mb-6">
    <VCardText class="pa-3">
      <!-- Fila Principal (Buscador y Acciones) -->
      <div class="d-flex align-center gap-2 mb-1">
        <!-- Buscador Principal -->
        <div class="flex-grow-1 min-width-0">
          <AppTextField
            :model-value="props.buscador"
            placeholder="Buscar por nombre, identificación o dirección..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
            @update:model-value="emit('update:buscador', $event)"
          />
        </div>

        <!-- Grupo de Acciones -->
        <div class="d-flex align-center gap-1 flex-shrink-0">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            @click="toggleAdvancedFilters"
          >
            <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="3"
              offset-y="-3"
            />
          </VBtn>

          <!-- Exportar (Menú Icono) -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                color="success"
                variant="tonal"
                size="38"
              >
                <VIcon icon="tabler-file-export" />
                <VTooltip activator="parent" location="top">Exportar</VTooltip>
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem @click="emit('export-excel', 'xlsx')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-csv" size="18" color="success" />
                </template>
                <VListItemTitle>Excel</VListItemTitle>
              </VListItem>
              <VListItem @click="emit('export-pdf')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" size="18" color="error" />
                </template>
                <VListItemTitle>PDF</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Añadir Doctor (Solo Icono) -->
          <VBtn
            icon
            color="primary"
            variant="flat"
            size="38"
            @click="emit('add-doctor')"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top">Agregar Doctor</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1" style="block-size: 24px;" />

          <!-- Limpiar Filtros -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </div>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow dense>
            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.fechaDesde_filtro"
                placeholder="Fecha Inicial"
                clearable
                density="compact"
                hide-details
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                prepend-inner-icon="tabler-calendar-event"
                @update:model-value="emit('update:fechaDesde_filtro', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.fechaHasta_filtro"
                placeholder="Fecha Final"
                clearable
                density="compact"
                hide-details
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                prepend-inner-icon="tabler-calendar-event"
                @update:model-value="emit('update:fechaHasta_filtro', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

.min-width-0 {
  min-inline-size: 0;
}
</style>
