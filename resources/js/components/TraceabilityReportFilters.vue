<script setup>
import { ref, computed } from "vue";

const props = defineProps({
  searchQuery: [String, null],
  startDate: [String, null],
  endDate: [String, null],
  selectedMovementType: [String, null],
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "update:selectedMovementType",
  "clear",
  "export",
]);

const isExpanded = ref(false);

const movementTypes = [
  { title: "Venta", value: "sale" },
  { title: "Compra", value: "purchase" },
  { title: "Devolución", value: "return" },
  { title: "Ajuste", value: "adjustment" },
  { title: "Pérdida", value: "loss" },
  { title: "Caducado", value: "expired" },
];

const searchQueryModel = computed({
  get: () => props.searchQuery,
  set: (val) => emit("update:searchQuery", val),
});

const movementTypeModel = computed({
  get: () => props.selectedMovementType,
  set: (val) => emit("update:selectedMovementType", val),
});

const startDateModel = computed({
  get: () => props.startDate,
  set: (val) => emit("update:startDate", val),
});

const endDateModel = computed({
  get: () => props.endDate,
  set: (val) => emit("update:endDate", val),
});
</script>

<template>
  <VCard class="mb-6 overflow-visible" variant="flat" border>
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Acciones -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="6" lg="7">
          <AppTextField
            v-model="searchQueryModel"
            placeholder="Buscar por ID, Producto, Laboratorio..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isExpanded ? 'primary' : 'secondary'"
            size="38"
            @click="isExpanded = !isExpanded"
          >
            <VIcon :icon="isExpanded ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Menú Exportar -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                icon
                variant="tonal"
                color="success"
                size="38"
                v-bind="menuProps"
              >
                <VIcon icon="tabler-download" />
                <VTooltip activator="parent" location="top">Exportar Reporte</VTooltip>
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem @click="emit('export', 'xlsx')">
                <template #prepend>
                  <VIcon icon="tabler-file-spreadsheet" color="success" />
                </template>
                <VListItemTitle>Excel</VListItemTitle>
              </VListItem>
              <VListItem @click="emit('export', 'pdf')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" color="error" />
                </template>
                <VListItemTitle>PDF</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Limpiar Filtros -->
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
      </VRow>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isExpanded">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow dense>
            <!-- Tipo de Movimiento -->
            <VCol cols="12" sm="4">
              <VSelect
                v-model="movementTypeModel"
                :items="movementTypes"
                placeholder="Tipo de Movimiento"
                clearable
                density="compact"
                hide-details
                prepend-inner-icon="tabler-arrows-diff"
              />
            </VCol>

            <!-- Fecha Desde -->
            <VCol cols="12" sm="4">
              <AppDateTimePicker
                v-model="startDateModel"
                placeholder="Fecha Inicial"
                clearable
                density="compact"
                hide-details
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                prepend-inner-icon="tabler-calendar-event"
              />
            </VCol>

            <!-- Fecha Hasta -->
            <VCol cols="12" sm="4">
              <AppDateTimePicker
                v-model="endDateModel"
                placeholder="Fecha Final"
                clearable
                density="compact"
                hide-details
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                prepend-inner-icon="tabler-calendar-event"
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
</style>
