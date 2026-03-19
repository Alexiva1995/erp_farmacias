<script setup>
const props = defineProps({
  buscador: String,
  tipo_identificacion_filtro: String,
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
});

const emit = defineEmits([
  "update:buscador",
  "update:tipo_identificacion_filtro",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "add-client",
  "add-existing-client",
  "export-pdf",
  "export-excel",
]);
</script>

<template>
  <VCard class="mb-6">
    <VCardText class="pa-3">
      <!-- Fila Principal: Buscador y Filtros Rápidos -->
      <VRow dense align="center" class="ma-0">
        <VCol cols="12" md="4">
          <AppTextField
            :model-value="props.buscador"
            placeholder="BUSCAR CLIENTE POR NOMBRE O ID..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            hide-details
            class="premium-input"
            @update:model-value="emit('update:buscador', $event)"
          />
        </VCol>

        <VCol cols="12" sm="4" md="2">
          <VSelect
            :model-value="props.tipo_identificacion_filtro"
            label="TIPO ID"
            :items="['V-', 'J-', 'G-', 'E-']"
            variant="outlined"
            density="compact"
            hide-details
            clearable
            prepend-inner-icon="tabler-id"
            class="premium-input"
            @update:model-value="emit('update:tipo_identificacion_filtro', $event)"
          />
        </VCol>

        <VCol cols="12" sm="4" md="3">
          <AppDateTimePicker
            :model-value="props.fechaDesde_filtro"
            placeholder="FECHA DESDE"
            clearable
            density="compact"
            hide-details
            prepend-inner-icon="tabler-calendar"
            class="premium-input"
            :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            @update:model-value="emit('update:fechaDesde_filtro', $event)"
          />
        </VCol>

        <VCol cols="12" sm="4" md="3">
          <AppDateTimePicker
            :model-value="props.fechaHasta_filtro"
            placeholder="FECHA HASTA"
            clearable
            density="compact"
            hide-details
            prepend-inner-icon="tabler-calendar"
            class="premium-input"
            :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            @update:model-value="emit('update:fechaHasta_filtro', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider class="border-opacity-10" />

    <VCardActions class="pa-3 bg-light">
      <VBtn
        color="secondary"
        variant="tonal"
        size="small"
        prepend-icon="tabler-eraser"
        class="font-weight-bold uppercase"
        @click="emit('clear')"
      >
        LIMPIAR
      </VBtn>

      <VSpacer />

      <div class="d-flex align-center gap-2">
        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn
              color="success"
              variant="tonal"
              size="small"
              prepend-icon="tabler-file-export"
              class="font-weight-bold uppercase"
              v-bind="menuProps"
            >
              EXPORTAR
            </VBtn>
          </template>
          <VList density="compact">
            <VListItem @click="emit('export-excel', 'xlsx')">
              <template #prepend>
                <VIcon icon="tabler-file-spreadsheet" size="18" color="success" />
              </template>
              <VListItemTitle>EXCEL</VListItemTitle>
            </VListItem>
            <VListItem @click="emit('export-pdf')">
              <template #prepend>
                <VIcon icon="tabler-file-type-pdf" size="18" color="error" />
              </template>
              <VListItemTitle>PDF</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>

        <VBtn
          color="primary"
          variant="flat"
          size="small"
          prepend-icon="tabler-user-plus"
          class="font-weight-black uppercase shadow-sm"
          @click="emit('add-existing-client')"
        >
          AÑADIR CLIENTE
        </VBtn>
      </div>
    </VCardActions>
  </VCard>
</template>

<style scoped>
.bg-light {
  background-color: #f8fafc !important;
}

.premium-input :deep(.v-field__input) {
  font-size: 0.8rem !important;
  font-weight: 600;
}

.premium-input :deep(.v-label) {
  font-size: 0.7rem !important;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.uppercase {
  text-transform: uppercase;
}
</style>
