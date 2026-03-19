<script setup>
import { ref } from 'vue';

const props = defineProps({
  buscador: String,
  tipo_empresa_filtro: String,
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
});

const emit = defineEmits([
  "update:buscador",
  "update:tipo_empresa_filtro",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "add-company",
  "export-pdf",
  "export-excel",
]);

const isFiltersVisible = ref(false);
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
            placeholder="Buscar por nombre, RIF o dirección..."
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
            :color="isFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            @click="isFiltersVisible = !isFiltersVisible"
          >
            <VIcon :icon="isFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
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
                  <VIcon icon="tabler-file-spreadsheet" size="18" color="success" />
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

          <!-- Añadir Empresa (Solo Icono) -->
          <VBtn
            icon
            color="primary"
            variant="flat"
            size="38"
            @click="emit('add-company')"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top">Nueva Empresa</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1" style="block-size: 24px;" />

          <!-- Resetear Filtros -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </div>

      <VExpandTransition>
        <div v-show="isFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          <VRow dense>
            <VCol cols="12" sm="6" md="3">
              <VSelect
                :model-value="props.tipo_empresa_filtro"
                label="Tipo de Empresa"
                :items="['Empresa', 'Clinica']"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @update:model-value="emit('update:tipo_empresa_filtro', $event)"
              />
            </VCol>
            
            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.fechaDesde_filtro"
                label="Fecha Desde"
                placeholder="Seleccionar"
                clearable
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar-event"
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:fechaDesde_filtro', $event)"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.fechaHasta_filtro"
                label="Fecha Hasta"
                placeholder="Seleccionar"
                clearable
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar-event"
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:fechaHasta_filtro', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>
