<script setup>
import dayjs from 'dayjs';

defineProps({
  groupByCorporate: {
    type: Boolean,
    required: true
  },
  startDate: {
    type: String,
    required: true
  },
  endDate: {
    type: String,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits([
  'update:groupByCorporate',
  'update:startDate',
  'update:endDate',
  'refresh',
  'exportPdf',
  'exportCsv'
]);

// Accesos directos de rangos de fechas
const applyPreset = (preset) => {
  const today = dayjs();
  let start = today;
  const end = today.format('YYYY-MM-DD');

  switch (preset) {
    case 'this_month':
      start = today.startOf('month');
      break;
    case 'last_30_days':
      start = today.subtract(30, 'day');
      break;
    case 'this_quarter':
      start = today.startOf('quarter' in today ? 'quarter' : 'month');
      break;
    case 'this_year':
      start = today.startOf('year');
      break;
  }

  emit('update:startDate', start.format('YYYY-MM-DD'));
  emit('update:endDate', end);
};
</script>

<template>
  <VCard border class="mb-4 rounded-lg">
    <VCardText class="pa-4">
      <VRow align="center" class="ga-y-3">
        <!-- Toggle Individual / Corporativo -->
        <VCol cols="12" md="4" lg="3">
          <VBtnToggle
            :model-value="groupByCorporate"
            @update:model-value="emit('update:groupByCorporate', $event)"
            mandatory
            color="primary"
            variant="outlined"
            density="comfortable"
            class="w-100"
            :disabled="loading"
          >
            <VBtn :value="false" class="flex-grow-1">
              <VIcon icon="tabler-building" class="me-1" size="18" />
              Individual
            </VBtn>
            <VBtn :value="true" class="flex-grow-1">
              <VIcon icon="tabler-building-community" class="me-1" size="18" />
              Corporativo
            </VBtn>
          </VBtnToggle>
        </VCol>

        <!-- Presets Rápidos de Fechas -->
        <VCol cols="12" md="8" lg="4" class="d-flex flex-wrap align-center ga-1">
          <VChip
            size="small"
            variant="tonal"
            color="primary"
            class="cursor-pointer"
            :disabled="loading"
            @click="applyPreset('this_month')"
          >
            Mes Actual
          </VChip>
          <VChip
            size="small"
            variant="tonal"
            color="secondary"
            class="cursor-pointer"
            :disabled="loading"
            @click="applyPreset('last_30_days')"
          >
            Últimos 30d
          </VChip>
          <VChip
            size="small"
            variant="tonal"
            color="info"
            class="cursor-pointer"
            :disabled="loading"
            @click="applyPreset('this_year')"
          >
            Año Actual
          </VChip>
        </VCol>

        <!-- Rango de Fechas -->
        <VCol cols="12" sm="5" md="4" lg="2">
          <VTextField
            :model-value="startDate"
            @update:model-value="emit('update:startDate', $event)"
            type="date"
            label="Fecha Inicio"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            prepend-inner-icon="tabler-calendar"
            :disabled="loading"
          />
        </VCol>

        <VCol cols="12" sm="5" md="4" lg="2">
          <VTextField
            :model-value="endDate"
            @update:model-value="emit('update:endDate', $event)"
            type="date"
            label="Fecha Fin"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            prepend-inner-icon="tabler-calendar"
            :disabled="loading"
          />
        </VCol>

        <!-- Botones de Acción (Refresco y Exportar) -->
        <VCol cols="12" sm="2" md="4" lg="1" class="d-flex justify-end align-center ga-2">
          <VBtn
            icon="tabler-refresh"
            variant="tonal"
            color="primary"
            density="comfortable"
            :loading="loading"
            :disabled="loading"
            @click="emit('refresh')"
          >
            <VIcon icon="tabler-refresh" />
            <VTooltip activator="parent" location="top">Actualizar datos</VTooltip>
          </VBtn>

          <VMenu location="bottom end">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon="tabler-download"
                variant="outlined"
                color="secondary"
                density="comfortable"
                :disabled="loading"
              >
                <VIcon icon="tabler-download" />
                <VTooltip activator="parent" location="top">Exportar reporte</VTooltip>
              </VBtn>
            </template>
            <VList density="compact" elevation="2">
              <VListItem @click="emit('exportPdf')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" color="error" class="me-2" />
                </template>
                <VListItemTitle class="font-weight-medium">Exportar PDF</VListItemTitle>
              </VListItem>
              <VListItem @click="emit('exportCsv')">
                <template #prepend>
                  <VIcon icon="tabler-file-spreadsheet" color="success" class="me-2" />
                </template>
                <VListItemTitle class="font-weight-medium">Exportar CSV</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
