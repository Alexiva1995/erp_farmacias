<script setup>
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery: String,
  startDate: {
    type: String,
    default: null,
  },
  endDate: {
    type: String,
    default: null,
  },
  showDateFilters: {
    type: Boolean,
    default: false,
  },
    showStateFilters: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "update:searchQuery",
  "clear",
  "update:startDate",
  "update:endDate",
]);

</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="3">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Vendedor"
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <template v-if="props.showDateFilters">
          <VCol cols="12" sm="6" md="4">
            <AppDateTimePicker
              :model-value="props.startDate"
              placeholder="Fecha Desde"
              clearable
              :config="{
                altInput: true,
                altFormat: 'Y-m-d',
                dateFormat: 'Y-m-d',
              }"
              @update:model-value="emit('update:startDate', $event)"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <AppDateTimePicker
              :model-value="props.endDate"
              placeholder="Fecha Hasta"
              clearable
              :config="{
                altInput: true,
                altFormat: 'Y-m-d',
                dateFormat: 'Y-m-d',
              }"
              @update:model-value="emit('update:endDate', $event)"
            />
          </VCol>
        </template>
      </VRow>
    </VCardText>
    <VDivider />
    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
