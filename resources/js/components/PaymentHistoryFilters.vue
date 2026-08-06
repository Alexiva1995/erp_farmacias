<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";

const props = defineProps({
  searchQuery: String,
  selectedSupplier: [Number, String, null],
  selectedCurrency: [String, null],
  selectedPaymentMethod: [String, null],
  startDate: [String, null],
  endDate: [String, null],
  suppliers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
  "update:selectedCurrency",
  "update:selectedPaymentMethod",
  "update:startDate",
  "update:endDate",
  "clear",
  "refresh",
  "export",
]);

const currencies = [
  { value: "VES", label: "VES - Bolívar Venezolano" },
  { value: "USD", label: "USD - Dólar Americano" },
  { value: "COP", label: "COP - Peso Colombiano" },
];

const paymentMethods = [
  { value: "USD", label: "USD - Dólar / Zelle / Cash" },
  { value: "VES", label: "VES - Transferencia / Pago Móvil" },
  { value: "COP", label: "COP - Pesos" },
];
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    search-placeholder="Buscar factura, proveedor o referencia..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #actions>
      <VBtn
        color="success"
        variant="tonal"
        size="small"
        prepend-icon="tabler-file-spreadsheet"
        class="font-weight-black rounded-lg"
        @click="emit('export')"
      >
        Exportar Excel
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Proveedor -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          :model-value="props.selectedSupplier"
          :items="props.suppliers"
          item-title="name"
          item-value="id"
          placeholder="Proveedor"
          density="compact"
          hide-details
          variant="outlined"
          clearable
          prepend-inner-icon="tabler-building-store"
          @update:model-value="emit('update:selectedSupplier', $event)"
        />
      </VCol>

      <!-- Moneda -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.selectedCurrency"
          :items="currencies"
          item-title="label"
          item-value="value"
          placeholder="Moneda"
          density="compact"
          hide-details
          variant="outlined"
          clearable
          prepend-inner-icon="tabler-coin"
          @update:model-value="emit('update:selectedCurrency', $event)"
        />
      </VCol>

      <!-- Método de Pago -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.selectedPaymentMethod"
          :items="paymentMethods"
          item-title="label"
          item-value="value"
          placeholder="Método de Pago"
          density="compact"
          hide-details
          variant="outlined"
          clearable
          prepend-inner-icon="tabler-wallet"
          @update:model-value="emit('update:selectedPaymentMethod', $event)"
        />
      </VCol>

      <!-- Rango de Fechas -->
      <VCol cols="12" sm="6" md="2">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Desde"
          density="compact"
          hide-details
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Hasta"
          density="compact"
          hide-details
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
