<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  selectConDescuento: Boolean,
  tipo_de_vista: Boolean,
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  stock: String,
  selectedLaboratory: { type: Array, default: () => [] },
  selectedGroup: { type: Array, default: () => [] },
  laboratories: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
  isColombian: Boolean,
  searchQuery: String,
});

const emit = defineEmits([
  "update:selectConDescuento",
  "update:tipo_de_vista",
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:stock",
  "update:selectedLaboratory",
  "update:selectedGroup",
  "update:isColombian",
  "update:searchQuery",
  "clear",
  "generarPedido",
]);

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.selectedLaboratory?.length > 0 ||
    props.selectedGroup?.length > 0 ||
    props.isColombian ||
    props.stock !== 'all' ||
    props.lapso_de_tiempo !== '1 month' ||
    props.tipo_de_filtracion !== 'combinado'
  );
});

const precio = [
  { title: "Full", value: true },
  { title: "Descuento", value: false },
];

const tipoDeVistaOpcion = [
  { title: "Grupal", value: true },
  { title: "Individual", value: false },
];

const tipoFiltracionOpcion = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
];

const lapsoDeTiempoOpciones = [
  { title: "7 Dias", value: "7 days" },
  { title: "15 Dias", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "1 Año", value: "1 year" },
];

const stockOpciones = [
  { title: "Exceso", value: "exceso" },
  { title: "Fallas", value: "fallas" },
  { title: "Todos", value: "all" },
];
</script>

<template>
  <VCard class="mb-6 border-0 shadow-sm overflow-hidden">
    <VCardText class="pa-4">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="4" lg="5">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar producto o principio activo..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-2">
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

          <VBtn
            icon
            color="primary"
            variant="elevated"
            size="38"
            @click="emit('generarPedido')"
          >
            <VIcon icon="tabler-shopping-cart-plus" />
            <VTooltip activator="parent" location="top">Generar Pedido de Reposición</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros (Solo Icono) -->
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
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow dense>
            <VCol cols="12" sm="6" md="3">
              <VAutocomplete
                :model-value="props.selectedLaboratory"
                :items="props.laboratories"
                placeholder="Laboratorio"
                item-title="name"
                item-value="id"
                clearable
                multiple
                chips
                closable-chips
                hide-details
                density="compact"
                prepend-inner-icon="tabler-flask"
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <VAutocomplete
                :model-value="props.selectedGroup"
                :items="props.groups"
                placeholder="Grupos"
                item-title="name"
                item-value="id"
                clearable
                multiple
                chips
                closable-chips
                hide-details
                density="compact"
                prepend-inner-icon="tabler-tags"
                @update:model-value="emit('update:selectedGroup', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.lapso_de_tiempo"
                :items="lapsoDeTiempoOpciones"
                placeholder="Lapso de tiempo"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-calendar-time"
                @update:model-value="emit('update:lapso_de_tiempo', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.tipo_de_filtracion"
                :items="tipoFiltracionOpcion"
                placeholder="Calcular Por"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-calculator"
                @update:model-value="emit('update:tipo_de_filtracion', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.stock"
                :items="stockOpciones"
                placeholder="Stock"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-package"
                @update:model-value="emit('update:stock', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.tipo_de_vista"
                :items="tipoDeVistaOpcion"
                placeholder="Vista"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-layout-grid"
                @update:model-value="emit('update:tipo_de_vista', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.selectConDescuento"
                :items="precio"
                placeholder="Precio"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-tag"
                @update:model-value="emit('update:selectConDescuento', $event)"
              />
            </VCol>
            
            <VCol cols="12" sm="6" md="3" class="d-flex align-center">
              <VCheckbox
                :model-value="props.isColombian"
                label="Origen Colombia"
                color="primary"
                hide-details
                density="compact"
                class="mt-0"
                @update:model-value="emit('update:isColombian', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-2 { gap: 8px !important; }
.h-38 { height: 38px !important; }

:deep(.v-btn.h-38) {
  min-height: 38px;
}

.text-xs {
  font-size: 0.7rem !important;
  letter-spacing: 0.5px;
}
</style>
