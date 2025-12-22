<script setup lang="js">
import { computed, watch } from 'vue';

const props= defineProps({
  type_of_expense:{type:String, required: true, default: () => 'normal'},
  modalFormulario: {type: Boolean, required: true},
  titulo: {type: String, required: true},
  formData: {type: Object, default: () => {}},
  formError: {type: Object, default: () => []},
  categorias: {type: Array, default: () => []},
})

const emit= defineEmits(["modalClose", 'save', 'clearErrorForm'])

const bs=[
      "Efectivo",
      "Tarjeta",
      "Pago móvil",
      "Transferencia",
    ]

const usd=[
      "Efectivo",
      "Binance",
      "PayPal",
    ]

const cop=[
      "Efectivo",
      "Transferencia",
    ]

const currencies=["BS","USD", "COP"];

const recurrencia=[
  "Mensual","Semestral","Anual"
];

const shouldShowExchangeRate = computed(() => {
  return props.formData.currency === "BS" || props.formData.currency === "COP";
});

const calculatedTaxAmount = computed(() => {
  const base = Number(props.formData.taxable_base) || 0;
  return (base * 0.16).toFixed(2);
});

const calculatedTotalAmount = computed(() => {
  const excento = Number(props.formData.exempt_amount) || 0;
  const base = Number(props.formData.taxable_base) || 0;
  const impuesto = Number(props.formData.tax_amount) || 0;
  return (excento + base + impuesto).toFixed(2);
});

const calculatedTotalUsd = computed(() => {
  const totalAmount = Number(props.formData.total_amount) || 0;
  const currency = props.formData.currency;
  const exchangeRate = Number(props.formData.exchange_rate) || 0;

  if (currency === "USD") {
    return totalAmount.toFixed(2);
  }

  if (exchangeRate > 0) {
    return (totalAmount / exchangeRate).toFixed(2);
  }

  return "0.00";
});

const getCurrencySymbol = computed(() => {
  const symbolMap = {
    BS: "Bs.",
    USD: "$",
    COP: "COP$",
  };
  return symbolMap[props.formData.currency] || "Bs.";
});

// Watch para calcular impuesto automáticamente
watch(
  () => props.formData.taxable_base,
  (newValue) => {
    if (newValue !== undefined && newValue !== null) {
      const base = Number(newValue) || 0;
      props.formData.tax_amount = parseFloat((base * 0.16).toFixed(2));
    }
  }
);

// Watch para calcular total automáticamente
watch(
  () => [
    props.formData.exempt_amount,
    props.formData.taxable_base,
    props.formData.tax_amount,
  ],
  () => {
    const excento = Number(props.formData.exempt_amount) || 0;
    const base = Number(props.formData.taxable_base) || 0;
    const impuesto = Number(props.formData.tax_amount) || 0;
    props.formData.total_amount = parseFloat((excento + base + impuesto).toFixed(2));
  },
  { deep: true }
);

// Watch para calcular total USD automáticamente
watch(
  () => [
    props.formData.total_amount,
    props.formData.currency,
    props.formData.exchange_rate,
  ],
  () => {
    const totalAmount = Number(props.formData.total_amount) || 0;
    const currency = props.formData.currency;
    const exchangeRate = Number(props.formData.exchange_rate) || 0;

    let totalUsd = 0;
    if (currency === "USD") {
      totalUsd = totalAmount;
    } else if (exchangeRate > 0) {
      totalUsd = totalAmount / exchangeRate;
    }
    
    props.formData.total_usd = parseFloat(totalUsd.toFixed(2));
  },
  { deep: true }
);

// Watch para limpiar tasa de cambio si la moneda es USD
watch(
  () => props.formData.currency,
  (newCurrency) => {
    if (newCurrency === "USD") {
      props.formData.exchange_rate = 0;
    }
  }
);

// Watch para calcular amount_bs automáticamente cuando es deducible y la moneda no es BS
watch(
  () => [
    props.formData.is_deductible,
    props.formData.currency,
    props.formData.amount,
    props.formData.conversion_rate_to_bs,
  ],
  () => {
    if (
      props.formData.is_deductible === true &&
      props.formData.currency !== "BS" &&
      props.formData.conversion_rate_to_bs > 0 &&
      props.formData.amount > 0
    ) {
      const amount = Number(props.formData.amount) || 0;
      const rate = Number(props.formData.conversion_rate_to_bs) || 0;
      props.formData.amount_bs = parseFloat((amount * rate).toFixed(2));
    } else if (props.formData.currency === "BS" && props.formData.is_deductible === true) {
      // Si la moneda es BS, amount_bs es igual a amount
      props.formData.amount_bs = parseFloat((Number(props.formData.amount) || 0).toFixed(2));
    }
  },
  { deep: true }
);

function close(){
  emit("modalClose",false)
}

// function generarFormData(estado){

//   let formData = new FormData();

//   Object.entries(estado).forEach(([key, value]) => {
//     if (value instanceof File) {
//       formData.append(key, value); // Archivo (Blob/File)
//     } else if (typeof value === 'object' && value !== null) {
//       formData.append(key, JSON.stringify(value)); // Objetos anidados
//     } else if (value === true || value === false) {
//       formData.append(key, value);
//     } else {
//       formData.append(key, value); // Strings/números
//     }
//   });

//   return formData
// }


function submitForm(){
  console.log("data XD => ",props.formData)
  emit("clearErrorForm")
  // let data=generarFormData(props.formData)
  emit("save",props.formData)
}
</script>
<template>
  <VDialog :model-value="props.modalFormulario" max-width="800px" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">{{ props.titulo }}</span>
        <VSpacer />
        <VBtn icon variant="text" @click="close">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VContainer>
        <VRow>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VTextField
              v-model="props.formData.name"
              :error-messages="props.formError.name"
              label="Nombre"
              type="text"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-model="props.formData.category_id"
              label="Categoria"
              :items="props.categorias"
              :error-messages="props.formError.category_id"
              item-title="name"
              item-value="id"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-model="props.formData.currency"
              label="Moneda"
              :items="currencies"
              :error-messages="props.formError.currency"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-if="props.formData.currency == 'BS'"
              v-model="props.formData.count"
              label="Método de Pago"
              :items="bs"
              :error-messages="props.formError.count"
            />
            <VSelect
              v-if="props.formData.currency == 'USD'"
              v-model="props.formData.count"
              label="Método de Pago"
              :items="usd"
              :error-messages="props.formError.count"
            />
            <VSelect
              v-if="props.formData.currency == 'COP'"
              v-model="props.formData.count"
              label="Método de Pago"
              :items="cop"
              :error-messages="props.formError.count"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" v-if="type_of_expense == 'recurrente'">
            <VSelect
              v-model="props.formData.recurrence"
              label="Recurrencia"
              :items="recurrencia"
              :error-messages="props.formError.recurrencia"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" v-if="type_of_expense == 'normal'">
            <AppDateTimePicker
              v-model="props.formData.expense_date"
              :error-messages="props.formError.expense_date"
              label="Fecha"
              variant="outlined"
              :config="{
                altInput: true,
                altFormat: 'Y-m-d',
                dateFormat: 'Y-m-d',
              }"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VSelect
              v-model="props.formData.is_deductible"
              label="Es Deducible"
              :items="[
                { title: 'No', value: false },
                { title: 'Sí', value: true }
              ]"
              :error-messages="props.formError.is_deductible"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VCheckbox v-model="props.formData.iva" class="mt-0 pt-0">
              <template v-slot:label>IVA</template>
            </VCheckbox>
          </VCol>
          <VCol
            cols="12" sm="6" md="6"
            v-if="props.formData.is_deductible === true && props.formData.currency !== 'BS'"
          >
            <VTextField
              v-model.number="props.formData.conversion_rate_to_bs"
              :error-messages="props.formError.conversion_rate_to_bs"
              label="Tasa de Conversión a BS"
              type="number"
              variant="outlined"
              hint="Ingrese la tasa de cambio para convertir a Bolívares"
              persistent-hint
            />
          </VCol>
          <VCol
            cols="12" sm="6" md="6"
            v-if="props.formData.iva == true || props.formData.is_deductible === true"
          >
            <VTextField
              v-model.number="props.formData.amount_bs"
              :error-messages="props.formError.amount_bs"
              label="Monto Bs"
              type="number"
              variant="outlined"
              :readonly="props.formData.is_deductible === true && props.formData.currency !== 'BS' && props.formData.conversion_rate_to_bs > 0"
            />
          </VCol>

        </VRow>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="props.formData.exempt_amount"
              :error-messages="props.formError.exempt_amount"
              label="Monto Exento IVA"
              type="number"
              variant="outlined"
              :prefix="getCurrencySymbol"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="props.formData.taxable_base"
              :error-messages="props.formError.taxable_base"
              label="Base Imponible 16%"
              type="number"
              variant="outlined"
              :prefix="getCurrencySymbol"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="props.formData.tax_amount"
              :error-messages="props.formError.tax_amount"
              label="Impuesto 16%"
              type="number"
              variant="outlined"
              :prefix="getCurrencySymbol"
              readonly
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="props.formData.total_amount"
              :error-messages="props.formError.total_amount"
              label="Total Factura"
              type="number"
              variant="outlined"
              :prefix="getCurrencySymbol"
              readonly
            />
          </VCol>
          <VCol v-if="shouldShowExchangeRate" cols="12" md="4">
            <VTextField
              v-model.number="props.formData.exchange_rate"
              :error-messages="props.formError.exchange_rate"
              label="Tasa de Cambio"
              type="number"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              v-model.number="props.formData.total_usd"
              :error-messages="props.formError.total_usd"
              label="Total Referencia (USD)"
              type="number"
              variant="outlined"
              prefix="$"
              readonly
            />
          </VCol>
        </VRow>
      </VContainer>
      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="close"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
          >Cancelar</VBtn
        >
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
          >Guardar Cambios</VBtn
        >
      </VCardActions>
    </VCard>
  </VDialog>
</template>
