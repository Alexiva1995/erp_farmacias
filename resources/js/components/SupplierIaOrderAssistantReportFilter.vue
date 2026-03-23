<script setup>
const props = defineProps({
  checkColombia: { type: Boolean, required: true },
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  laboratories: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:selectedLaboratory",
  "update:selectProducts",
  "update:checkColombia",
  "clear",
  "export-excel",
  "export-pdf",
]);

const tipoFiltracionOpcion = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
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
</script>

<template>
  <VCard class="mb-6 rounded-xl border-0 shadow-sm overflow-hidden">
    <VExpansionPanels variant="accordion" class="premium-expansion-panels">
      <VExpansionPanel elevation="0">
        <template #title>
          <div class="d-flex align-center gap-2 w-full">
            <VIcon icon="tabler-filter" size="20" color="primary" />
            <span class="text-subtitle-1 font-weight-bold">Filtros de Análisis</span>
            <VSpacer />
            <div class="d-flex align-center gap-2 me-4" @click.stop>
              <VBtn 
                color="secondary" 
                variant="tonal" 
                size="small" 
                prepend-icon="tabler-filter-off"
                class="rounded-lg"
                @click="emit('clear')"
              >
                Limpiar
              </VBtn>
            </div>
          </div>
        </template>

        <VExpansionPanelText class="pa-0">
          <VDivider class="opacity-10" />
          <div class="pa-5">
            <VRow>
              <VCol cols="12" md="6" lg="3">
                <div class="text-xs font-weight-bold text-disabled mb-1 text-uppercase">Productos</div>
                <AppAutocomplete
                  :items="props.products"
                  placeholder="Todos los productos"
                  item-title="name"
                  item-value="id"
                  clearable
                  chips
                  multiple
                  closable-chips
                  hide-details="auto"
                  class="premium-input-compact"
                  @update:model-value="emit('update:selectProducts', $event)"
                />
              </VCol>

              <VCol cols="12" md="6" lg="3">
                <div class="text-xs font-weight-bold text-disabled mb-1 text-uppercase">Laboratorios</div>
                <AppAutocomplete
                  :items="props.laboratories"
                  placeholder="Todos los laboratorios"
                  item-title="name"
                  item-value="id"
                  clearable
                  chips
                  multiple
                  closable-chips
                  hide-details="auto"
                  class="premium-input-compact"
                  @update:model-value="emit('update:selectedLaboratory', $event)"
                />
              </VCol>

              <VCol cols="12" sm="6" md="3" lg="2">
                <div class="text-xs font-weight-bold text-disabled mb-1 text-uppercase">Calcular por</div>
                <AppSelect
                  :model-value="props.tipo_de_filtracion"
                  :items="tipoFiltracionOpcion"
                  hide-details="auto"
                  class="premium-input-compact"
                  @update:model-value="emit('update:tipo_de_filtracion', $event)"
                />
              </VCol>

              <VCol cols="12" sm="6" md="3" lg="2">
                <div class="text-xs font-weight-bold text-disabled mb-1 text-uppercase">Lapso de tiempo</div>
                <AppSelect
                  :model-value="props.lapso_de_tiempo"
                  :items="lapsoDeTiempoOpciones"
                  hide-details="auto"
                  class="premium-input-compact"
                  @update:model-value="emit('update:lapso_de_tiempo', $event)"
                />
              </VCol>

              <VCol cols="12" sm="6" md="3" lg="2" class="d-flex align-end pb-3">
                <VCheckbox
                  :model-value="props.checkColombia"
                  label="Origen Colombia"
                  color="primary"
                  density="compact"
                  hide-details
                  class="mt-0"
                  @update:model-value="emit('update:checkColombia', $event)"
                />
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-3 mt-6">
              <VMenu>
                <template #activator="{ props: menuProps }">
                  <VBtn
                    color="success"
                    variant="flat"
                    prepend-icon="tabler-file-download"
                    class="rounded-lg shadow-sm"
                    v-bind="menuProps"
                  >
                    Exportar Reporte
                  </VBtn>
                </template>
                <VList class="rounded-lg shadow-lg border">
                  <VListItem @click="emit('export-excel', 'xlsx')" class="py-2">
                    <template #prepend>
                      <VIcon icon="tabler-file-spreadsheet" class="me-2" color="success" />
                    </template>
                    <VListItemTitle class="font-weight-bold text-success">Excel (.xlsx)</VListItemTitle>
                  </VListItem>
                  <VDivider />
                  <VListItem @click="emit('export-pdf')" class="py-2">
                    <template #prepend>
                      <VIcon icon="tabler-file-type-pdf" class="me-2" color="error" />
                    </template>
                    <VListItemTitle class="font-weight-bold text-error">PDF (.pdf)</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>
            </div>
          </div>
        </VExpansionPanelText>
      </VExpansionPanel>
    </VExpansionPanels>
  </VCard>
</template>

<style scoped>
.premium-expansion-panels :deep(.v-expansion-panel-title) {
  padding-block: 12px;
  padding-inline: 20px;
}

.premium-expansion-panels :deep(.v-expansion-panel-title--active) {
  background-color: rgba(var(--v-theme-primary), 2%);
}

.premium-input-compact :deep(.v-field__input) {
  min-block-size: 38px !important;
  padding-block: 0 !important;
}

.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-4 { gap: 16px; }

.text-xs {
  font-size: 0.7rem !important;
  letter-spacing: 0.5px;
}
</style>
