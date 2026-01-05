<script setup>
import { computed } from "vue";

const props = defineProps({
  searchQuery: {
    type: String,
    required: true,
  },
  selectedLaboratory: {
    type: [Number, String, null],
    required: true,
  },
  startDate: {
    type: [String, null],
    required: true,
  },
  endDate: {
    type: [String, null],
    required: true,
  },
  laboratories: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  selectedLots: {
    type: Array,
    default: () => [],
  },
  isStrictSearch: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:startDate",
  "update:endDate",
  "update:isStrictSearch",
  "clear",
  "expire-selected",
]);

const searchQueryModel = computed({
  get: () => props.searchQuery,
  set: (value) => emit("update:searchQuery", value),
});

const laboratoryModel = computed({
  get: () => props.selectedLaboratory,
  set: (value) => emit("update:selectedLaboratory", value),
});

const startDateModel = computed({
  get: () => props.startDate,
  set: (value) => emit("update:startDate", value),
});

const endDateModel = computed({
  get: () => props.endDate,
  set: (value) => emit("update:endDate", value),
});

const isStrictSearchModel = computed({
  get: () => props.isStrictSearch,
  set: (value) => emit("update:isStrictSearch", value),
});

const hasSelectedLots = computed(() => props.selectedLots.length > 0);
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            v-model="searchQueryModel"
            placeholder="Buscar por Producto, Lote..."
            clearable
          />
        </VCol>

        <VCol cols="12" sm="6" md="2">
          <VAutocomplete
            v-model="laboratoryModel"
            :items="props.laboratories"
            :loading="props.loading"
            label="Laboratorio"
            placeholder="Buscar un laboratorio"
            item-title="name"
            item-value="id"
            clearable
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <AppDateTimePicker
            v-model="startDateModel"
            placeholder="Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <AppDateTimePicker
            v-model="endDateModel"
            placeholder="Hasta"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>

      <div class="d-flex align-center gap-2">
        <VCheckbox
          v-model="isStrictSearchModel"
          color="primary"
          class="me-2"
        >
          <template #label>
            <div class="d-flex align-center">
              <VIcon icon="tabler-search" class="me-2" size="20" />
              <span class="text-subtitle-1 font-weight-medium">
                ¿Búsqueda Estricta?
              </span>
            </div>
          </template>
        </VCheckbox>

        <VChip
          v-if="isStrictSearchModel"
          color="primary"
          size="small"
          class="ms-2"
        >
          <VIcon icon="tabler-alert-circle" size="14" class="me-1" />
          Modo Estricto Activo
        </VChip>
      </div>

      <VSpacer />

      <!-- Botones de acciones para productos seleccionados -->
      <div v-if="hasSelectedLots" class="d-flex align-center gap-x-3">
        <div class="text-body-2 text-medium-emphasis">
          <span class="font-weight-medium">{{ props.selectedLots.length }}</span>
          lote(s) seleccionado(s)
        </div>

        <VTooltip text="Caducar Seleccionados" location="top">
          <template #activator="{ props: tooltipProps }">
            <IconBtn
              v-bind="tooltipProps"
              color="error"
              @click="emit('expire-selected')"
            >
              <VIcon icon="tabler-calendar-off" />
            </IconBtn>
          </template>
        </VTooltip>
      </div>
    </VCardActions>
  </VCard>
</template>
