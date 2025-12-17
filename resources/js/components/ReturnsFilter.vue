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
  { title: "Pendiente", value: "Pending" },
  { title: "Aprobado", value: "Approved" },
  { title: "Rechazado", value: "Rejected" },
];
</script>

<template>
  <VCard title="Filtro" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="3" md="3">
          <VTextField
            v-model="props.search"
            placeholder="Buscar por producto o numero orden..."
            clearable
            @update:model-value="emit('update:search', $event)"
          />
        </VCol>
        <VCol cols="12" sm="3" md="3">
          <AppDateTimePicker
            v-model="props.startDate"
            placeholder="Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <AppDateTimePicker
            v-model="props.endDate"
            placeholder="Hasta"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
        <VCol cols="12" sm="3" md="3">
          <VSelect
            v-model="props.status"
            :items="statuses"
            placeholder="Estado"
            clearable=""
            label="Estado"
            @update:model-value="emit('update:status', $event)"
          />
        </VCol>
        <VCol cols="12" sm="3" md="3">
          <VSelect
            v-model="props.seller"
            :items="props.sellers"
            item-title="username"
            item-value="id"
            clearable=""
            placeholder="Vendedor"
            @update:model-value="emit('update:seller', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtro
      </VBtn>
    </VCardActions>
  </VCard>
</template>
