<script setup>
const props = defineProps({
  stats: { type: Object, default: () => {} },
  dateRange: { type: String, default: "" },
  dataDetailed: { type: Boolean, default: false },
  selectedCurrency: { type: String, default: "" },
  selectedOption: { type: String, default: "" },
});

const emit = defineEmits([
  "update:dateRange",
  "update:dataDetailed",
  "update:selectedCurrency",
  "update:selectedTab",
  "update:selectedOption",
  "clear",
]);

const options = {
  BS: [
    {
      title: "Efectivo",
      value: "CASH_BS",
    },
    {
      title: "Tarjeta",
      value: "CARD_BS",
    },
    {
      title: "Pago móvil",
      value: "MOBILE_BS",
    },
    {
      title: "Transferencia",
      value: "TRANSFER_BS",
    },
  ],
  COP: [
    {
      title: "Efectivo",
      value: "CASH_COP",
    },
    {
      title: "Transferencia",
      value: "TRANSFER_COP",
    },
  ],
  USD: [
    {
      title: "Efectivo",
      value: "CASH_USD",
    },
    {
      title: "Binance",
      value: "BINANCE_USD",
    },
    {
      title: "PayPal",
      value: "PAYPAL_USD",
    },
    {
      title: "Crédito",
      value: "CREDIT_USD",
    },
  ],
};

function toggleCurrency(cur) {
  emit("update:selectedCurrency", props.selectedCurrency === cur ? "" : cur);

  if (props.selectedCurrency === cur) {
    emit("update:selectedOption", "");
    emit("update:dataDetailed", false);
    return;
  }

  if (cur) {
    const first =
      cur === "USD"
        ? options.USD[0].value
        : cur === "BS"
        ? options.BS[0].value
        : options.COP[0].value;

    emit("update:selectedOption", first);
  }
}
</script>

<template>
  <VRow class="mb-4">
    <VCol md="3" @click="toggleCurrency('USD')">
      <VCard
        class="cursor-pointer"
        :class="
          selectedCurrency === 'USD' &&
          'border-opacity-50 border-warning border-lg pa-0 ma-0'
        "
      >
        <VCardText>
          <h5 class="text-h3">
            {{
              Intl.NumberFormat("es", { notation: "standard" }).format(
                props.stats.total_usd
              )
            }}
          </h5>
          <div class="text-sm">
            {{ `USD - ${dateRange != null ? "mensual" : "total"}` }}
          </div>
        </VCardText>
        <SparklineCard :stats="props.stats['USD'] || []" color="warning" />
      </VCard>
    </VCol>
    <VCol md="3" @click="toggleCurrency('COP')">
      <VCard
        class="cursor-pointer"
        :class="
          selectedCurrency === 'COP' &&
          'border-opacity-50 border-primary border-lg pa-0 ma-0'
        "
      >
        <VCardText>
          <h5 class="text-h3">
            {{
              Intl.NumberFormat("es", {
                notation: "standard",
                maximumFractionDigits: 0,
              }).format(props.stats.total_cop)
            }}
          </h5>
          <div class="text-sm">
            {{ `Pesos (COP) - ${dateRange != null ? "mensual" : "total"}` }}
          </div>
        </VCardText>
        <SparklineCard :stats="props.stats['COP'] || []" color="primary" />
      </VCard>
    </VCol>
    <VCol md="3" @click="toggleCurrency('BS')">
      <VCard
        class="cursor-pointer"
        :class="
          selectedCurrency === 'BS' &&
          'border-opacity-50 border-error border-lg pa-0 ma-0'
        "
      >
        <VCardText>
          <h5 class="text-h3">
            {{
              Intl.NumberFormat("es", { notation: "standard" }).format(
                props.stats.total_bs
              )
            }}
          </h5>
          <div class="text-sm">
            {{ `Bolívares (BS) - ${dateRange != null ? "mensual" : "total"}` }}
          </div>
        </VCardText>
        <SparklineCard
          :title="
            Intl.NumberFormat('en', { notation: 'standard' }).format(
              props.stats.total_bs
            )
          "
          :description="`Bolivares (BS) - ${
            dateRange != null ? 'mensual' : 'total'
          }`"
          :stats="props.stats['BS'] || []"
          color="error"
        />
      </VCard>
    </VCol>

    <VCol md="3">
      <VCard>
        <VCardText class="d-flex flex-column align-center justify-center">
          <VAvatar
            density="compact"
            :rounded="lg"
            variant="tonal"
            class="text-success"
            size="60"
          >
            <VIcon icon="tabler-currency-dollar" size="40"></VIcon>
          </VAvatar>
          <h5 class="text-h3 pt-2 mb-2">
            {{
              Intl.NumberFormat("es", { notation: "standard" }).format(
                props.stats["total_value"] ?? 0
              )
            }}
          </h5>
          <div class="text-body-1">Total en USD</div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
  <VCard class="mb-4">
    <VCardText>
      <VCardTitle>Flujo de caja</VCardTitle>

      <VRow class="align-center justify-between">
        <VCol>
          <AppDateTimePicker
            :model-value="props.dateRange"
            @update:model-value="emit('update:dateRange', $event)"
            label="Fechas"
            placeholder="Seleccionar rango"
            :config="{ mode: 'range' }"
          />
        </VCol>

        <VCol class="mt-5">
          <VSwitch
            :model-value="props.dataDetailed"
            @update:model-value="
              emit('update:dataDetailed', $event);
              if ($event && props.selectedCurrency === '') {
                emit('update:selectedCurrency', 'USD');
                emit('update:selectedOption', options.USD.at(0).value);
              }
            "
            label="Detallado"
            inset
          />
        </VCol>
        <VCol class="mt-5">
          <VSelect
            v-if="props.dataDetailed"
            label="Seleccione una pestaña"
            :model-value="props.selectedOption"
            :items="options[props.selectedCurrency ?? 'USD']"
            @update:model-value="emit('update:selectedOption', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
