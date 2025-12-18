<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import axios from "axios";
import { computed, defineEmits, defineProps, onMounted, ref, watch } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  creditsData: {
    type: Object,
    default: () => ({}),
  },
  selectedCurrency: {
    type: String,
    default: "USD",
  },
});

const emit = defineEmits(["update:isDialogVisible", "modal-closed"]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const payments = ref([]);
const loadingPayments = ref(false);
const filterClient = ref("");
const filterDate = ref("");
const filterCurrency = ref("");

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
};

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

const totalCredits = computed(() => {
  return props.creditsData
    .reduce((sum, credit) => {
      const creditAmount = parseFloat(credit.credit_amount) || 0;
      return sum + creditAmount;
    }, 0)
    .toFixed(2);
});

const totalPendingAmount = computed(() => {
  return props.creditsData
    .reduce((sum, credit) => {
      const pendingAmount = parseFloat(credit.pending_amount) || 0;
      return sum + pendingAmount;
    }, 0)
    .toFixed(2);
});

const filteredPayments = computed(() => {
  let filtered = payments.value;

  if (filterClient.value) {
    const search = filterClient.value.toLowerCase();
    filtered = filtered.filter((payment) =>
      payment.seller?.username?.toLowerCase().includes(search)
    );
  }

  if (filterDate.value) {
    filtered = filtered.filter((payment) => {
      const paymentDate = payment.payment_date?.split(" ")[0];
      return paymentDate === filterDate.value;
    });
  }

  if (filterCurrency.value) {
    filtered = filtered.filter(
      (payment) => payment.currency === filterCurrency.value
    );
  }

  return filtered.map((payment) => payment.payments);
});

const totalPaid = computed(() => {
  return filteredPayments.value.reduce((sum, payment) => {
    return sum + parseFloat(payment.money_returns || 0);
  }, 0);
});

const fetchPayments = async () => {
  if (!props.creditsData || !props.creditsData[0]?.client_id) return;

  loadingPayments.value = true;
  try {
    const response = await axios.post("/api/tpv/credits/payments", {
      client_id: props.creditsData[0].client_id,
    });
    console.log("Pagos recibidos:", response.data);
    console.log("Client ID:", props.creditsData[0].client_id);
    payments.value = response.data;
  } catch (error) {
    console.error("Error al cargar los pagos:", error);
    console.error("Response error:", error.response?.data);
  } finally {
    loadingPayments.value = false;
  }
};

const translateMethod = (method) => {
  const options = {
    cash_cop: "Efectivo",
    bank_transfer: "Transferencia",
    mobile_payment: "Pago Móvil",
    bank_transfer_bs: "Transferencia",
    debit_card: "T. Debito",
    credit_card: "T. Crédito",
    cash_bs: "Efectivo",
    cash_usd: "Efectivo",
    binance: "Binance",
    paypal: "PayPal",
    credit: "Crédito",
    balance: "Saldo",
  };

  return options[method] || method;
};

watch(
  () => props.isDialogVisible,
  (newVal) => {
    if (newVal) {
      fetchPayments();
    }
  }
);

onMounted(() => {
  if (props.isDialogVisible) {
    fetchPayments();
  }
});

const paymentHeaders = [
  { title: "Fecha", key: "date", sortable: false },
  { title: "Monto", key: "amount", sortable: false },
  { title: "Moneda", key: "currency", sortable: false },
  { title: "Método", key: "method", sortable: false },
  { title: "Vendedor", key: "seller", sortable: false },
];
</script>
<template>
  <VDialog v-model="dialogVisible" max-width="50%">
    <VCard>
      <VCardTitle class="d-flex align-center p-2">
        <span class="text-h5 font-weight-bold pr-1"></span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VCardText>
        <div class="text-center">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
        </div>
        <div class="text-center">
          <span class="font-weight-regular">J-50540695-7</span>
        </div>
        <div class="text-center">
          <span class="font-weight-regular"
            >FARMACIA BARRIO SUCRE 2024, C.A.</span
          >
        </div>
        <div class="text-center">
          <span class="font-weight-regular">CALLE PRINCIPAL LOCAL 05 (L5)</span>
        </div>
        <div class="text-center">
          <span class="font-weight-regular"
            >SECTOR BARRIO SUCRE LA FRIA TACHIRA</span
          >
        </div>
        <div class="text-center">
          <span class="font-weight-regular">ZONA POSTAL 5020</span>
        </div>
        <div class="d-flex justify-space-between align-start mb-1">
          <span class="font-weight-bold text-h6">Cliente:</span>
          <span class="font-weight-bold text-h6"
            >{{ props.creditsData[0].client.name }}
            {{ props.creditsData[0].client.last_name }}</span
          >
        </div>
        <div class="d-flex justify-space-between align-start mb-1">
          <span class="font-weight-bold text-h6">Documento:</span>
          <span class="font-weight-bold text-h6">
            {{ props.creditsData[0].client.identification_type }}
            {{ props.creditsData[0].client.identification }}</span
          >
        </div>
        <div
          v-for="credit in props.creditsData"
          :key="credit.order.id"
          class="my-4"
        >
          <VCard>
            <VCardTitle class="d-flex justify-space-between align-center">
              <span class="font-weight-bold text-h6"
                >Orden #{{ credit.order.id }}</span
              >
              <span class="text-body-2 text-medium-emphasis">
                Total: {{ credit.order.total_amount }}
                {{ credit.order.currency }}
              </span>
            </VCardTitle>
            <VDivider />
            <VCardText>
              <div
                class="scrollable-list-container"
                :class="{ 'show-scroll': credit.order.details.length > 2 }"
              >
                <VList class="card-list" density="compact" nav>
                  <VListItem
                    v-for="details in credit.order.details"
                    :key="details.product.id"
                    class="rounded-0"
                  >
                    <template #prepend>
                      <span>{{ details.quantity }} x</span>
                    </template>

                    <VListItemTitle class="font-weight-medium me-4 mx-2">
                      {{ details.product.name }}
                    </VListItemTitle>

                    <template #append>
                      <div class="d-flex align-center">
                        <span class="text-body-1 me-2">
                          {{ details.unit_price_usd }}
                          {{ credit.order.currency }}
                        </span>
                      </div>
                    </template>
                  </VListItem>
                </VList>
              </div>
            </VCardText>
          </VCard>
        </div>
        <hr />
        <div class="ticket-total d-flex justify-space-between align-center">
          <span class="font-weight-bold text-h6">TOTAL CREDITO:</span>
          <span class="text-end font-weight-bold text-h6"
            >{{ totalCredits }} USD
          </span>
        </div>
        <div class="ticket-total d-flex justify-space-between align-center">
          <span class="font-weight-bold text-h6">TOTAL CREDITO PENDIENTE:</span>
          <span class="text-end font-weight-bold text-h6"
            >{{ totalPendingAmount }} USD
          </span>
        </div>

        <VDivider class="my-4" />

        <!-- Tabla de Pagos -->
        <div class="mb-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <span class="font-weight-bold text-h6">Historial de Pagos</span>
            <VChip color="primary" size="small">
              Total Pagado: {{ totalPaid.toFixed(2) }} USD
            </VChip>
          </div>

          <!-- Filtros -->
          <VRow class="mb-3">
            <VCol cols="12" md="4">
              <VTextField
                v-model="filterClient"
                label="Buscar Vendedor"
                density="compact"
                clearable
                prepend-inner-icon="tabler-search"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model="filterDate"
                label="Fecha"
                type="date"
                density="compact"
                clearable
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="filterCurrency"
                :items="['USD', 'COP', 'BS']"
                label="Moneda"
                density="compact"
                clearable
              />
            </VCol>
          </VRow>

          <VDataTable
            :headers="paymentHeaders"
            :items="filteredPayments"
            :loading="loadingPayments"
            class="text-no-wrap"
            density="compact"
            :items-per-page="5"
            no-data-text="No hay pagos registrados"
          >
            <template v-slot:item.date="{ item }">
              <span>{{ item.date ? item.date.split(" ")[0] : "N/A" }}</span>
            </template>

            <template v-slot:item.amount="{ item }">
              <span class="font-weight-medium">
                {{ parseFloat(item.amount).toFixed(2) }}
              </span>
            </template>

            <template v-slot:item.currency="{ item }">
              <VChip
                size="x-small"
                :color="
                  item.currency === 'USD'
                    ? 'success'
                    : item.currency === 'COP'
                    ? 'info'
                    : 'primary'
                "
              >
                {{ item.currency }}
              </VChip>
            </template>

            <template v-slot:item.method="{ item }">
              <VChip size="x-small">
                {{ translateMethod(item.method) }}
              </VChip>
            </template>

            <template v-slot:item.seller="{ item }">
              <span>{{ item.seller || "N/A" }}</span>
            </template>
          </VDataTable>
        </div>
      </VCardText>
      <VDivider />
    </VCard>
  </VDialog>
</template>
<style scoped>
.card-list .v-list-item:not(:last-child) {
  padding-block: 4px !important;
  padding-block-end: 0 !important;
}

.v-list .v-list-item--nav:not(:only-child) {
  margin-block-end: 0 !important;
}
</style>
