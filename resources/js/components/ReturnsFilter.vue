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
  { title: "Pendiente", value: "pending" },
  { title: "Aprobado", value: "Approved" },
  { title: "Rechazado", value: "Rejected" },
];
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="4" md="2">
          <VTextField
            :model-value="props.search"
            placeholder="Buscar por producto o n° orden..."
            clearable
            density="compact"
            hide-details
            @update:model-value="emit('update:search', $event)"
          />
        </VCol>
        <VCol cols="12" sm="4" md="2">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="Desde"
            clearable
            density="compact"
            hide-details
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>
        <VCol cols="12" sm="4" md="2">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="Hasta"
            clearable
            density="compact"
            hide-details
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
        <VCol cols="12" sm="4" md="2">
          <VSelect
            :model-value="props.status"
            :items="statuses"
            placeholder="Estado"
            clearable
            density="compact"
            hide-details
            @update:model-value="emit('update:status', $event)"
          />
        </VCol>
        <VCol cols="12" sm="4" md="2">
          <VSelect
            :model-value="props.seller"
            :items="props.sellers ?? []"
            item-title="username"
            item-value="id"
            clearable
            placeholder="Vendedor"
            density="compact"
            hide-details
            @update:model-value="emit('update:seller', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
