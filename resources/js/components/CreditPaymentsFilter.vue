<script setup>
import AppDateTimePicker from "@/@core/components/app-form-elements/AppDateTimePicker.vue";

const props = defineProps({
  client: String,
  date: String,
  currency: String,
});

const emit = defineEmits([
  "update:client",
  "update:date",
  "update:currency",
  "clear",
]);

const currencies = ["USD", "COP", "BS"];
</script>

<template>
  <VCard title="Filtro" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="4">
          <AppTextField
            :model-value="props.client"
            placeholder="Buscar por cliente"
            clearable
            @update:model-value="emit('update:client', $event)"
          />
        </VCol>
        <VCol cols="12" sm="4">
          <AppDateTimePicker
            :model-value="props.date"
            placeholder="Buscar por fecha de pago"
            clearable
            @update:model-value="emit('update:date', $event)"
          />
        </VCol>
        <VCol cols="12" sm="4">
          <VSelect
            :model-value="props.currency"
            :items="currencies"
            label="Moneda"
            clearable
            @update:model-value="emit('update:currency', $event)"
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
