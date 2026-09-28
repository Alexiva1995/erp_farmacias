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

        <VCol cols="12" sm="6" md="3" class="d-flex align-center justify-end ga-1 flex-wrap">
          <VBtn
            icon
            variant="tonal"
            color="primary"
            size="38"
            rounded="circle"
            :loading="loading"
            :disabled="loading"
            @click="emit('fetch')"
          >
            <VIcon icon="tabler-refresh" />
            <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
          </VBtn>

          <VBtn
            icon
            variant="tonal"
            color="secondary"
            size="38"
            rounded="circle"
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
                icon
                color="success"
                variant="tonal"
                size="38"
                rounded="circle"
                :loading="exporting"
                :disabled="loading || exporting"
              >
                <VIcon icon="tabler-file-export" />
                <VTooltip activator="parent" location="top">Exportar Reporte</VTooltip>
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem @click="emit('export-pdf')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" size="18" color="error" class="me-2" />
                </template>
                <VListItemTitle>Exportar PDF</VListItemTitle>
              </VListItem>
              <VListItem @click="emit('export-csv')">
                <template #prepend>
                  <VIcon icon="tabler-file-spreadsheet" size="18" color="success" class="me-2" />
                </template>
                <VListItemTitle>Exportar CSV / Excel</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
