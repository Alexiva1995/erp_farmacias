<script setup>
import { computed } from 'vue';

const props = defineProps({
  loading: { type: Boolean, default: false },
  exporting: { type: Boolean, default: false },
  startDate: { type: String, required: true },
  endDate: { type: String, required: true },
  sellerId: { type: [Number, String, null], default: null },
  sellers: { type: Array, default: () => [] },
});

const emit = defineEmits([
  'update:startDate',
  'update:endDate',
  'update:sellerId',
  'fetch',
  'reset',
  'export-pdf',
  'export-csv',
]);

const localStartDate = computed({
  get: () => props.startDate,
  set: (val) => emit('update:startDate', val),
});

const localEndDate = computed({
  get: () => props.endDate,
  set: (val) => emit('update:endDate', val),
});

const localSellerId = computed({
  get: () => props.sellerId,
  set: (val) => emit('update:sellerId', val),
});
</script>

<template>
  <VCard class="mb-6 rounded-lg" variant="outlined">
    <VCardText class="pa-4">
      <VRow align="center" dense>
        <VCol cols="12" sm="6" md="3">
          <VTextField
            v-model="localStartDate"
            type="date"
            label="Fecha Inicio"
            :disabled="loading"
            density="comfortable"
            variant="outlined"
            hide-details="auto"
            prepend-inner-icon="tabler-calendar"
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VTextField
            v-model="localEndDate"
            type="date"
            label="Fecha Fin"
            :disabled="loading"
            density="comfortable"
            variant="outlined"
            hide-details="auto"
            prepend-inner-icon="tabler-calendar"
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VAutocomplete
            v-model="localSellerId"
            :items="sellers"
            item-title="name"
            item-value="id"
            label="Vendedor / Cajero"
            placeholder="Todos los vendedores"
            clearable
            :disabled="loading"
            density="comfortable"
            variant="outlined"
            hide-details="auto"
            prepend-inner-icon="tabler-user"
          />
        </VCol>

        <VCol cols="12" sm="6" md="3" class="d-flex align-center justify-end ga-2">
          <VBtn
            color="primary"
            variant="flat"
            prepend-icon="tabler-refresh"
            :loading="loading"
            :disabled="loading"
            @click="emit('fetch')"
          >
            Filtrar
          </VBtn>

          <VBtn
            color="secondary"
            variant="tonal"
            icon="tabler-eraser"
            :disabled="loading"
            @click="emit('reset')"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Restablecer Filtros</VTooltip>
          </VBtn>

          <VMenu location="bottom end">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                color="primary"
                variant="outlined"
                prepend-icon="tabler-download"
                :loading="exporting"
                :disabled="loading || exporting"
              >
                Exportar
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem prepend-icon="tabler-file-type-pdf" title="Exportar a PDF" @click="emit('export-pdf')" />
              <VListItem prepend-icon="tabler-file-spreadsheet" title="Exportar a CSV / Excel" @click="emit('export-csv')" />
            </VList>
          </VMenu>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
