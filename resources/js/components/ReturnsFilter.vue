<script setup>
const props = defineProps({
  search: String,
  status: String,
  supplier: String,
  startDate: String,
  endDate: String,
  seller: String,
  sellers: Array,
});

const emit = defineEmits([
  "update:search",
  "update:status",
  "update:supplier",
  "update:startDate",
  "update:endDate",
  "update:seller",
  "clear",
]);
const statuses = [
  { title: "Todas", value: "" },
  { title: "Pendiente", value: "pending" },
  { title: "Aprobado", value: "Approved" },
  { title: "Rechazado", value: "Rejected" },
];
</script>

<template>
  <VCard class="mb-6 elevation-1 border-0 rounded-lg overflow-hidden">
    <VCardText class="pa-4">
      <VRow dense>
        <VCol cols="12" sm="6" md="2">
          <AppTextField
            :model-value="props.search"
            placeholder="BUSCAR PRODUCTO O N° ORDEN..."
            label="BÚSQUEDA"
            clearable
            density="compact"
            prepend-inner-icon="tabler-search"
            class="premium-input"
            @update:model-value="emit('update:search', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="DESDE"
            label="FECHA INICIO"
            clearable
            density="compact"
            prepend-inner-icon="tabler-calendar-event"
            class="premium-input"
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="HASTA"
            label="FECHA FIN"
            clearable
            density="compact"
            prepend-inner-icon="tabler-calendar-check"
            class="premium-input"
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <VSelect
            :model-value="props.status"
            :items="statuses"
            label="ESTADO"
            placeholder="TODAS"
            clearable
            density="compact"
            prepend-inner-icon="tabler-filter-cog"
            variant="outlined"
            class="premium-input"
            @update:model-value="emit('update:status', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.seller"
            :items="props.sellers ?? []"
            item-title="username"
            item-value="id"
            label="VENDEDOR"
            placeholder="CUALQUIER VENDEDOR"
            clearable
            density="compact"
            prepend-inner-icon="tabler-user-search"
            variant="outlined"
            class="premium-input"
            @update:model-value="emit('update:seller', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="1" class="d-flex align-end pb-1">
          <VBtn
            color="secondary"
            variant="tonal"
            block
            height="38"
            class="font-weight-black rounded-lg text-xs"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-refresh" size="18" />
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.premium-input :deep(.v-field__input) {
  font-size: 0.8rem !important;
  font-weight: 600;
  text-transform: uppercase;
}

.premium-input :deep(.v-label) {
  font-size: 0.7rem !important;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.text-xs { font-size: 0.75rem !important; }
</style>
