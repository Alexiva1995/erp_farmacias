<script setup>
import AppFilterBase from '@/components/AppFilterBase.vue'
import { computed } from 'vue'

const props = defineProps({
  search: { type: String, default: '' },
  taxType: { type: [String, null], default: null },
  period: { type: [String, null], default: null },
  status: { type: [String, null], default: null },
  startDate: { type: [String, null], default: null },
  endDate: { type: [String, null], default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:search',
  'update:taxType',
  'update:period',
  'update:status',
  'update:startDate',
  'update:endDate',
  'clear',
  'sort',
  'open-smart-paste',
  'open-manual-form',
  'open-bot-sync',
])

const sortOptions = [
  { title: 'Vencimiento Próximo', icon: 'tabler-calendar-due', key: 'due_date', order: 'asc' },
  { title: 'Vencimiento Lejano', icon: 'tabler-calendar-event', key: 'due_date', order: 'desc' },
  { title: 'Monto Mayor', icon: 'tabler-arrow-up', key: 'amount', order: 'desc' },
  { title: 'Monto Menor', icon: 'tabler-arrow-down', key: 'amount', order: 'asc' },
  { title: 'Fecha Operación Reciente', icon: 'tabler-calendar-up', key: 'operation_date', order: 'desc' },
]

const taxTypesList = [
  'ANTICIPO-ISLR',
  'IGTF',
  'IVA/35',
  'DPP',
  'ISLR',
  'PATENTE',
  'FONA',
  'FONACIT',
  'DEPORTE',
  'INCIDENCIAS',
]

const statusList = [
  { title: 'Todos los estados', value: null },
  { title: 'Pendientes por Pagar', value: 'pending' },
  { title: 'Vencidos', value: 'expired' },
  { title: 'Pagados', value: 'paid' },
]

const hasAdvancedFilters = computed(() => {
  return !!(props.taxType || props.period || props.status || props.startDate || props.endDate)
})
</script>

<template>
  <AppFilterBase
    :search="props.search"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    search-placeholder="Buscar por N° Documento, Impuesto o Periodo..."
    class="py-1"
    @update:search="emit('update:search', $event)"
    @clear="emit('clear')"
    @sort="emit('sort', $event)"
  >
    <template #actions-extra>
      <!-- Botón Pegado Inteligente (Smart Paste) -->
      <VBtn
        color="primary"
        variant="elevated"
        size="small"
        class="text-xs font-weight-bold rounded-lg ml-1 shadow-sm px-3"
        @click="emit('open-smart-paste')"
      >
        <VIcon start icon="tabler-clipboard-text" size="18" />
        Pegar desde SENIAT
      </VBtn>

      <!-- Botón Nuevo Registro Manual -->
      <VBtn
        color="secondary"
        variant="tonal"
        size="small"
        class="text-xs font-weight-bold rounded-lg ml-2 px-3"
        @click="emit('open-manual-form')"
      >
        <VIcon start icon="tabler-plus" size="18" />
        Nuevo Registro
      </VBtn>

      <!-- Botón Sincronizar Bot -->
      <VBtn
        color="warning"
        variant="tonal"
        size="small"
        class="text-xs font-weight-bold rounded-lg ml-2 px-3"
        @click="emit('open-bot-sync')"
      >
        <VIcon start icon="tabler-robot" size="18" />
        Bot SENIAT
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Tipo de Impuesto -->
      <VCol cols="12" sm="3">
        <VSelect
          :model-value="props.taxType"
          :items="taxTypesList"
          placeholder="Tipo de Impuesto"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-receipt-tax"
          color="primary"
          @update:model-value="emit('update:taxType', $event)"
        />
      </VCol>

      <!-- Estado -->
      <VCol cols="12" sm="3">
        <VSelect
          :model-value="props.status"
          :items="statusList"
          item-title="title"
          item-value="value"
          placeholder="Estado de Pago"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-checkbox"
          color="primary"
          @update:model-value="emit('update:status', $event)"
        />
      </VCol>

      <!-- Periodo -->
      <VCol cols="12" sm="2">
        <VTextField
          :model-value="props.period"
          placeholder="Periodo (MM/AAAA)"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:period', $event)"
        />
      </VCol>

      <!-- Fecha Inicial Operación -->
      <VCol cols="12" sm="2">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Desde (Operación)"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha Final Operación -->
      <VCol cols="12" sm="2">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Hasta (Operación)"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
