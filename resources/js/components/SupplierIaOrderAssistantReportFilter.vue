<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  laboratories: { type: Array, default: () => [] },
  selectedLaboratory: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
  selectProducts: { type: Array, default: () => [] },
  showIgnored: { type: Boolean, default: false },
  suppliers: { type: Array, default: () => [] },
  selectedSupplierId: [Number, String],
  globalDiscountPercent: [Number, String],
  onlyBestSupplier: { type: Boolean, default: false },
  stock: { type: String, default: "all" },
  generatingPdf: { type: Boolean, default: false },
  exportingExcel: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:selectedLaboratory",
  "update:selectProducts",
  "clear",
  "clear-ignore",
  "export-excel",
  "export-pdf",
  "update:showIgnored",
  "update:selectedSupplierId",
  "update:globalDiscountPercent",
  "update:onlyBestSupplier",
]);

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.selectedLaboratory?.length > 0 ||
    props.selectedSupplierId != null ||
    props.onlyBestSupplier ||
    props.lapso_de_tiempo !== '3 month' ||
    props.tipo_de_filtracion !== 'stockout_adjusted_rop' ||
    props.stock !== 'all'
  );
});

const tipoFiltracionOpcion = [
  { title: "Stockout-Adjusted ROP PLUS (Inteligencia de Demanda)", value: "stockout_adjusted_rop_plus" },
  { title: "Stockout-Adjusted ROP (Predeterminado)",               value: "stockout_adjusted_rop"      },
  { title: "Ponderado (Óptimo ROP)",                              value: "weighted"                   },
  { title: "Promedio",                                           value: "average"                    },
  { title: "Ventas",                                             value: "sales"                      },
  { title: "Combinado",                                          value: "combinado"                  },
];

const lapsoDeTiempoOpciones = [
  { title: "15 Dias", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "12 Meses", value: "12 month" },
  { title: "18 Meses", value: "18 month" },
  { title: "24 Meses", value: "24 month" },
];

const stockOpciones = [
  { title: "Exceso", value: "exceso" },
  { title: "Fallas", value: "fallas" },
  { title: "Todos",  value: "all"    },
];

const supplierMatchOptions = [
  { title: "Todas las ofertas", value: false },
  { title: "Solo más económico", value: true },
];
</script>

<template>
  <VCard class="mb-6 border-0 shadow-sm overflow-hidden">
    <VCardText class="pa-4">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal (Autocomplete de Productos) -->
        <VCol cols="12" md="4" lg="4">
          <VAutocomplete
            :model-value="props.selectProducts"
            :items="props.products"
            placeholder="Buscar por productos..."
            item-title="name"
            item-value="id"
            clearable
            chips
            multiple
            closable-chips
            hide-details
            density="compact"
            prepend-inner-icon="tabler-search"
            @update:model-value="emit('update:selectProducts', $event)"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            rounded="circle"
            class="shadow-sm"
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
          <VMenu :disabled="props.generatingPdf || props.exportingExcel">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                color="success"
                variant="tonal"
                size="38"
                rounded="circle"
                class="shadow-sm"
                :loading="props.generatingPdf || props.exportingExcel"
                :disabled="props.generatingPdf || props.exportingExcel"
              >
                <VIcon icon="tabler-file-download" />
                <VTooltip activator="parent" location="top">Exportar Reporte</VTooltip>
              </VBtn>
            </template>
            <VList density="compact" class="rounded-lg shadow-lg border">
              <VListItem @click="emit('export-excel', 'xlsx')" class="py-2" :disabled="props.generatingPdf || props.exportingExcel">
                <template #prepend>
                  <VIcon icon="tabler-file-spreadsheet" class="me-2" color="success" />
                </template>
                <VListItemTitle class="font-weight-bold text-success">Excel (.xlsx)</VListItemTitle>
              </VListItem>
              <VDivider />
              <VListItem @click="emit('export-pdf')" class="py-2" :disabled="props.generatingPdf || props.exportingExcel">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" class="me-2" color="error" />
                </template>
                <VListItemTitle class="font-weight-bold text-error">PDF (.pdf)</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Limpiar Ignore -->
          <VBtn
            icon
            variant="tonal"
            color="warning"
            size="38"
            rounded="circle"
            class="shadow-sm"
            @click="emit('clear-ignore')"
          >
            <VIcon icon="tabler-eye-check" />
            <VTooltip activator="parent" location="top">Restaurar Ocultos (Ignore)</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            rounded="circle"
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
          
          <div class="d-flex align-center flex-nowrap overflow-x-auto gap-2 py-1">
            <!-- Laboratorios -->
            <div style="min-width: 130px; flex: 0 1 140px;">
              <VAutocomplete
                :model-value="props.selectedLaboratory"
                :items="props.laboratories"
                placeholder="Laboratorios"
                item-title="name"
                item-value="id"
                clearable
                chips
                multiple
                closable-chips
                hide-details
                density="compact"
                prepend-inner-icon="tabler-flask"
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </div>

            <!-- Calcular por -->
            <div style="min-width: 175px; flex: 1 1 175px;">
              <VSelect
                :model-value="props.tipo_de_filtracion"
                :items="tipoFiltracionOpcion"
                placeholder="Calcular por"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-calculator"
                @update:model-value="emit('update:tipo_de_filtracion', $event)"
              />
            </div>

            <!-- Lapso de tiempo -->
            <div style="min-width: 115px; flex: 0 1 120px;">
              <VSelect
                :model-value="props.lapso_de_tiempo"
                :items="lapsoDeTiempoOpciones"
                placeholder="Lapso"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-calendar-time"
                @update:model-value="emit('update:lapso_de_tiempo', $event)"
              />
            </div>

            <!-- Stock -->
            <div style="min-width: 100px; flex: 0 1 105px;">
              <VSelect
                :model-value="props.stock"
                :items="stockOpciones"
                placeholder="Stock"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-box"
                @update:model-value="emit('update:stock', $event)"
              />
            </div>

            <!-- Filtro Coincidencias / Mejor Oferta -->
            <div style="min-width: 165px; flex: 0 1 175px;">
              <VSelect
                :model-value="props.onlyBestSupplier"
                :items="supplierMatchOptions"
                placeholder="Ofertas"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-award"
                @update:model-value="emit('update:onlyBestSupplier', $event)"
              />
            </div>

            <!-- Selector de Proveedor -->
            <div style="min-width: 150px; flex: 1 1 160px;">
              <VAutocomplete
                :model-value="props.selectedSupplierId"
                :items="props.suppliers"
                placeholder="Proveedor"
                item-title="name"
                item-value="id"
                clearable
                hide-details
                density="compact"
                prepend-inner-icon="tabler-truck-delivery"
                @update:model-value="emit('update:selectedSupplierId', $event)"
              />
            </div>

            <!-- Descuento Global -->
            <div style="min-width: 90px; flex: 0 1 95px;">
              <VTextField
                :model-value="props.globalDiscountPercent"
                type="number"
                placeholder="Desc."
                hide-details
                density="compact"
                prepend-inner-icon="tabler-percentage"
                suffix="%"
                @update:model-value="emit('update:globalDiscountPercent', $event)"
              />
            </div>
          </div>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-4 { gap: 16px; }

.text-xs {
  font-size: 0.7rem !important;
  letter-spacing: 0.5px;
}
</style>
