<script setup>
import { defineProps, defineEmits, computed, ref, watch } from "vue";
import { onMounted, onBeforeUnmount } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatDateTime } from "@/utils/formatDateTime";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  orderData: {
    type: Object,
    default: () => ({}),
  },
  totalAmount: {
    type: Number,
    default: 0,
  },
  selectedCurrency: {
    type: String,
    default: "COP",
  },
  orderProducts: {
    type: Array,
    default: () => [],
  },
  selectedDisplayCurrency: {
    type: String,
    default: "COP",
  },
});

const emit = defineEmits([
  "update:isDialogVisible",
  "purchase-completed",
  "modal-closed",
]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const currentProgress = ref(0);
const progressStages = [0, 50, 100];
const currentStageIndex = ref(0);

const balanceSwitch = ref(false);
const invoiceSwitch = ref(false);

const payments = ref([
  {
    method: null,
    amount: null,
    reference: null,
    currency: props.selectedCurrency,
  },
]);

const currencies = [
  { label: "Pesos Colombianos (COP)", value: "COP" },
  { label: "Bolívares (BS)", value: "BS" },
  { label: "Dólares (USD)", value: "USD" },
];

const paymentMethodsByCurrency = {
  COP: [
    { label: "Efectivo", value: "cash_cop" },
    { label: "Transferencia", value: "bank_transfer" },
  ],
  BS: [
    { label: "Efectivo", value: "cash_bs" },
    { label: "Pago Móvil", value: "mobile_payment" },
    { label: "Transferencia", value: "bank_transfer_bs" },
    { label: "Tarjeta", value: "card" },
  ],
  USD: [
    { label: "Efectivo", value: "cash_usd" },
    { label: "Binance", value: "binance" },
    { label: "PayPal", value: "paypal" },
    { label: "Crédito", value: "credit" },
  ],
};

/*const exchangeRates = ref({
  COP: { BS: 0.00095, USD: 0.00025 },
  BS: { COP: 1052.63, USD: 0.263 },
  USD: { COP: 4000, BS: 3.8 },
});*/
const exchangeRates = ref({});

const continueButtonText = computed(() => {
  return currentProgress.value === 100 ? "Finalizar" : "Continuar";
});

const totalPaidAmount = computed(() => {
  const total = payments.value.reduce((sum, payment) => {
    const amount = Number(payment.amount) || 0;
    if (payment.currency === props.selectedCurrency) {
      return sum + amount;
    } else {
      const rate = exchangeRates.value?.[payment.currency]?.[props.selectedCurrency];
      if (rate) {
        let convertedAmount = amount * rate;
        if (props.selectedCurrency === "COP") {
          convertedAmount = Math.ceil(convertedAmount);
        }
        return sum + convertedAmount;
      } else {
        return sum;
      }
    }
  }, 0);
  return parseFloat(total.toFixed(2));
});

const fetchExchangeRates = async () => {
  try {
    const response = await fetch("/api/exchange-rates");
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    const apiRates = await response.json();
    const formattedRates = {};
    apiRates.forEach((rateItem) => {
      const currencyCode = rateItem.currency_code;
      const rateValue = parseFloat(rateItem.rate);

      if (!formattedRates["USD"]) {
        formattedRates["USD"] = {};
      }
      formattedRates["USD"][currencyCode] = rateValue;
      if (!formattedRates[currencyCode]) {
        formattedRates[currencyCode] = {};
      }
      if (rateValue !== 0) {
        formattedRates[currencyCode]["USD"] = 1 / rateValue;
      }

      if (formattedRates["COP"] && formattedRates["BS"]) {
        formattedRates["COP"]["BS"] =
          formattedRates["COP"]["USD"] * formattedRates["USD"]["BS"];
        formattedRates["BS"]["COP"] =
          formattedRates["BS"]["USD"] * formattedRates["USD"]["COP"];
      }
    });
    exchangeRates.value = formattedRates;
  } catch (error) {
    toast.error("No se pudieron cargar las tasas de cambio.");
  }
};

const totalPaidRounded = computed(() => {
  console.log(totalPaidAmount.value);
  return parseFloat(totalPaidAmount.value.toFixed(2));
});

onMounted(() => {
  fetchExchangeRates();
});

const remainingAmount = computed(() => {
  const rawDifference = props.totalAmount - totalPaidAmount.value;
  const roundedDifference = parseFloat(rawDifference.toFixed(2));
  return roundedDifference;
});

const getConvertedRemainingAmount = (currency) => {
  const baseCurrency = props.selectedCurrency;
  const targetCurrency = currency;
  const rate = exchangeRates.value[baseCurrency]?.[targetCurrency];

  if (rate) {
    return formatCurrency(remainingAmount.value * rate, targetCurrency);
  }
  return formatCurrency(remainingAmount.value, targetCurrency);
};

const getPlaceholderText = (index, payment) => {
  if (index === 0) {
    return `Monto restante: ${formatCurrency(
      remainingAmount.value,
      props.selectedCurrency
    )}`;
  } else {
    const convertedAmount = getConvertedRemainingAmount(payment.currency);
    return `Monto restante: ${convertedAmount}`;
  }
};

const addPaymentBlock = () => {
  if (remainingAmount.value > 0) {
    payments.value.push({
      method: null,
      amount: null,
      reference: null,
      currency: props.selectedDisplayCurrency,
    });
  } else {
    toast.error("El monto total ya ha sido cubierto.");
  }
};

const closeModal = () => {
  dialogVisible.value = false;
  emit("modal-closed");
  resetProgress();
};

const handleCompletePurchase = () => {
  const tolerance = 0.01; 
  if (currentProgress.value === 50 && payments.value[0].method !== "credit") {

    if (remainingAmount.value < 0) {
      toast.error("El monto total pagado excede el monto de la compra.");
      return;
    }
    console.log(remainingAmount.value)
    if (remainingAmount.value > tolerance) {
      toast.error(
        "El monto total no ha sido cubierto. Agrega más pagos para continuar."
      );
      return;
    }

    /* if (totalPaidAmount.value > props.totalAmount) {
      toast.error("El monto total pagado excede el monto de la compra.");
      return;
    }*/

    const invalidPayment = payments.value.find(
      (p) =>
        !p.method ||
        (p.amount <= 0 &&
          !["bank_transfer_cop", "bank_transfer_bs"].includes(p.method))
    );
    if (invalidPayment) {
      toast.error(
        "Por favor, revisa y completa los campos de todos los pagos."
      );
      return;
    }
  }

  if (currentProgress.value < 100) {
    currentStageIndex.value++;
    if (currentStageIndex.value < progressStages.length) {
      currentProgress.value = progressStages[currentStageIndex.value];
    } else {
      currentProgress.value = 100;
    }
  } else {
    console.log("Completando compra...");
    console.log("Datos de la orden:", props.orderData);
    console.log("Monto total:", props.totalAmount, props.selectedCurrency);

    emit("purchase-completed", props.orderData.id, payments.value);
    dialogVisible.value = false;
    resetProgress();
  }
};

const resetProgress = () => {
  currentProgress.value = 0;
  currentStageIndex.value = 0;
  payments.value = [
    {
      method: null,
      amount: null,
      reference: null,
      currency: props.selectedCurrency,
    },
  ];
};

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

watch(
  () => props.isDialogVisible,
  (newVal) => {
    if (newVal) {
      console.log(
        "El modal se ha abierto. Moneda de la orden:",
        props.selectedCurrency
      );
      resetProgress();
    }
  }
);

const getProductPrice = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    // Default to USD price
    basePrice = product.price || 0;
  }

  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }
  return priceWithIva;
};

const totalSelectedQuantity = computed(() => {
  let total = 0;
  props.orderProducts.forEach((product) => {
    const quantity = parseInt(product.selectedQuantity);
    if (!isNaN(quantity) && quantity > 0) {
      total += quantity;
    }
  });
  return total;
});
</script>
<template>
  <VDialog v-model="dialogVisible" max-width="500px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">Compra</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />

      <div class="demo-space-y px-4 pt-4">
        <VProgressLinear
          v-model="currentProgress"
          color="primary"
          height="10"
          rounded
        />
        <div class="d-flex flex-wrap justify-space-between">
          <p class="text-center mt-2 text-subtitle-2 text-medium-emphasis">
            Detalles de compra
          </p>
          <p class="text-center mt-2 text-subtitle-2 text-medium-emphasis">
            Métodos de pago
          </p>
          <p class="text-center mt-2 text-subtitle-2 text-medium-emphasis">
            Ticke de compra
          </p>
        </div>
      </div>

      <VCardText v-if="currentProgress === 0">
        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6">Total de productos:</p>
          <VChip
            label
            :color="chipColor"
            variant="tonal"
            density="default"
            size="small"
            draggable="false"
            class="ms-auto"
          >
            <span class="font-weight-medium">{{ totalSelectedQuantity }}</span>
          </VChip>
        </div>

        <div
          class="scrollable-list-container"
          :class="{ 'show-scroll': props.orderProducts.length > 2 }"
        >
          <VList class="card-list" density="compact" nav>
            <VListItem
              v-for="product in props.orderProducts"
              :key="product.id"
              class="rounded-0"
            >
              <template #prepend>
                <span>{{ product.selectedQuantity }} x</span>
              </template>

              <VListItemTitle class="font-weight-medium me-4 mx-2">{{
                product.title
              }}</VListItemTitle>
              <VListItemSubtitle class="mx-2"
                >{{ product.active_ingredient }}
                {{ product.laboratory }}</VListItemSubtitle
              >

              <template #append>
                <div class="d-flex align-center">
                  <span class="text-body-1 me-2">{{
                    formatCurrency(
                      getProductPrice(product, props.selectedCurrency) *
                        product.selectedQuantity,
                      props.selectedCurrency
                    )
                  }}</span>
                </div>
              </template>
            </VListItem>
          </VList>
        </div>
        <VDivider />
        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-4">Total a pagar:</p>
          <p class="font-weight-bold text-h6 mt-4">
            {{ formatCurrency(totalAmount, props.selectedCurrency) }}
          </p>
        </div>
      </VCardText>

      <VCardText v-else-if="currentProgress === 50">
        <div class="d-flex flex-wrap justify-space-between">
          <span>Saldo</span>
          <VSwitch v-model="balanceSwitch" />
        </div>
        <div class="d-flex flex-wrap justify-space-between">
          <span>Factura</span>
          <VSwitch v-model="invoiceSwitch" />
        </div>
        <VDivider class="my-4" />

        <div
          v-for="(payment, index) in payments"
          :key="index"
          class="payment-block"
        >
          <p class="font-weight-bold text-h6 mt-4">
            Método de Pago #{{ index + 1 }}
            <span v-if="index > 0"> ({{ payment.currency }})</span>
          </p>

          <VSelect
            v-if="index > 0"
            v-model="payment.currency"
            :items="currencies"
            item-title="label"
            item-value="value"
            label="Moneda del Pago"
            class="mt-4"
          />

          <div class="my-4">
            <VRadioGroup v-model="payment.method" inline>
              <VRadio
                v-for="method in paymentMethodsByCurrency[payment.currency] ||
                []"
                :key="method.value"
                :label="method.label"
                :value="method.value"
              />
            </VRadioGroup>
          </div>

          <VTextField
            v-if="payment.method && !['credit'].includes(payment.method)"
            v-model.number="payment.amount"
            label="Monto del pago"
            :placeholder="getPlaceholderText(index, payment)"
            type="number"
            class="my-4"
          />

          <VTextField
            v-if="
              payment.method &&
              [
                'bank_transfer',
                'bank_transfer_bs',
                'mobile_payment',
                'card',
              ].includes(payment.method)
            "
            v-model="payment.reference"
            label="Número de Referencia"
            placeholder="Ingresa el número de referencia del pago"
            class="m-2"
          />

          <VTextField
            v-if="payment.method === 'credit'"
            :model-value="formatCurrency(remainingAmount, payment.currency)"
            label="Monto del crédito"
            readonly
            class="mt-4"
          />
          <VDivider class="mt-4" />
        </div>

        <div class="d-flex justify-center mt-4">
          <VBtn
            variant="text"
            color="primary"
            @click="addPaymentBlock"
            :disabled="remainingAmount <= 0 || payments[0].method === 'credit'"
          >
            <VIcon start icon="tabler-plus" />
            Agregar otro método de pago
          </VBtn>
        </div>

        <VDivider />
        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-4">Total a pagar:</p>
          <p class="font-weight-bold text-h6 mt-4">
            {{ formatCurrency(totalAmount, props.selectedCurrency) }}
          </p>
        </div>

        <div
          v-if="remainingAmount > 0"
          class="d-flex flex-wrap justify-space-between"
        >
          <p class="font-weight-bold text-h6">Monto Restante:</p>
          <p class="font-weight-bold text-h6 text-error">
            {{ formatCurrency(remainingAmount, props.selectedCurrency) }}
          </p>
        </div>
      </VCardText>

      <VCardText v-else-if="currentProgress === 100">
        <div class="text-center">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
        </div>
        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6 mt-4">
            Orden N° {{ props.orderData.id }}
          </span>
          <div class="text-end">
            <span class="d-block font-weight-bold text-h6 mt-4">
              Fecha {{ formatDateTime(props.orderData.created_at, "date") }}
            </span>
            <span class="d-block font-weight-bold text-h6">
              {{ formatDateTime(props.orderData.created_at, "time") }}
            </span>
          </div>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6"> Cajero </span>
          <span class="font-weight-bold text-h6">
            {{ props.orderData.seller_id }}
          </span>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6"> Cedula </span>
          <span class="font-weight-bold text-h6">
            {{ props.orderData.client.identification_type }}
            {{ props.orderData.client.identification }}
          </span>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <span class="font-weight-bold text-h6"> Cliente </span>
          <span class="font-weight-bold text-h6">
            {{ props.orderData.client.name }}
            {{ props.orderData.client.last_name }}
          </span>
        </div>

        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6">Métodos de pago</p>
          <p class="font-weight-bold text-h6"></p>
        </div>
        <div
          class="scrollable-list-container"
          :class="{ 'show-scroll': props.orderProducts.length > 2 }"
        >
          <VList class="card-list" density="compact" nav>
            <VListItem
              v-for="product in props.orderProducts"
              :key="product.id"
              class="rounded-0"
            >
              <template #prepend>
                <span>{{ product.selectedQuantity }} x</span>
              </template>

              <VListItemTitle class="font-weight-medium me-4 mx-2">{{
                product.title
              }}</VListItemTitle>
              <VListItemSubtitle class="mx-2"
                >{{ product.active_ingredient }}
                {{ product.laboratory }}</VListItemSubtitle
              >

              <template #append>
                <div class="d-flex align-center">
                  <span class="text-body-1 me-2">{{
                    formatCurrency(
                      getProductPrice(product, props.selectedCurrency) *
                        product.selectedQuantity,
                      props.selectedCurrency
                    )
                  }}</span>
                </div>
              </template>
            </VListItem>
          </VList>
        </div>
        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-4">Total a pagar:</p>
          <p class="font-weight-bold text-h6 mt-4">
            {{ formatCurrency(totalAmount, props.selectedCurrency) }}
          </p>
        </div>
      </VCardText>

      <VCardActions class="p-4 d-flex flex-wrap justify-space-between">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeModal"
          class="flex-grow-1"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleCompletePurchase"
          class="flex-grow-1"
        >
          {{ continueButtonText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.scrollable-list-container {
  max-height: 95px;
  overflow-y: hidden;
  transition: overflow-y 0.3s ease-in-out;
}
.scrollable-list-container.show-scroll {
  overflow-y: auto;
}
</style>
