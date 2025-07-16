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
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:startDate",
  "update:endDate",
  "clear",
  "expire-selected",
  "apply-offer-selected",
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

const hasSelectedLots = computed(() => props.selectedLots.length > 0);
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="6">
          <AppTextField
            v-model="searchQueryModel"
            placeholder="Buscar por Producto, Lote..."
            clearable
          />
        </VCol>

        <VCol cols="12" sm="6" md="6">
          <VAutocomplete
            v-model="laboratoryModel"
            :items="props.laboratories"
            :loading="props.loading"
            label="Laboratorio"
            placeholder="Selecciona un laboratorio"
            item-title="name"
            item-value="id"
            clearable
          />
        </VCol>

        <VCol cols="12" sm="6" md="6">
          <AppDateTimePicker
            v-model="startDateModel"
            placeholder="Vencimiento Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
          />
        </VCol>
        <VCol cols="12" sm="6" md="6">
          <AppDateTimePicker
            v-model="endDateModel"
            placeholder="Vencimiento Hasta"
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

    <VCardActions class="pa-4 px-6">
      <div class="d-flex justify-space-between align-center w-100">
        <!-- Botón de limpiar filtros -->
        <VBtn color="secondary" variant="outlined" @click="emit('clear')">
          Limpiar Filtros
        </VBtn>

        <!-- Botones de acciones para productos seleccionados -->
        <div v-if="hasSelectedLots" class="d-flex align-center gap-x-3">
          <div class="text-body-2 text-medium-emphasis">
            <span class="font-weight-medium">{{ selectedLots.length }}</span>
            lote(s) seleccionado(s)
          </div>

          <VBtn
            variant="tonal"
            prepend-icon="tabler-percentage"
            @click="emit('apply-offer-selected')"
          >
            Generar Oferta
          </VBtn>

          <VBtn
            variant="tonal"
            color="warning"
            prepend-icon="tabler-calendar-off"
            @click="emit('expire-selected')"
          >
            Caducar Seleccionados
          </VBtn>
        </div>
      </div>
    </VCardActions>
  </VCard>
</template>
