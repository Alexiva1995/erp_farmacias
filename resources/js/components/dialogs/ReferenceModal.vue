<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  reference: { type: Array, default: () => [] },
  cashData: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:isDialogVisible", "modal-closed", "close"]);

const loading = ref(false);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const headers = [
  { title: "VENDEDOR", key: "seller_name", sortable: true },
  { title: "MÉTODO", key: "method_label", sortable: true },
  { title: "REFERENCIA", key: "reference", sortable: true },
  { title: "MONTO", key: "amount", align: "end", sortable: true },
  { title: "ESTADO", key: "actions", align: "center", sortable: false },
];

const translateMethod = (methodKey) => {
  if (!methodKey) return "Desconocido";
  const translations = {
    CARD: "Tarjeta",
    BANK_TRANSFER: "Transferencia",
    BANK_TRANSFER_BS: "Transferencia",
    BINANCE: "Binance",
    PAYPAL: "PayPal",
    MOBILE_PAYMENT: "Pago Móvil",
  };
  const key = String(methodKey).toUpperCase();
  return translations[key] || key.replace(/_/g, " ");
};

// Normalizar moneda a código ISO 4217 válido
const normalizeCurrency = (code) => {
  const map = { bs: "VES", "bs.": "VES", cop: "COP", usd: "USD", bolivar: "VES", ves: "VES" };
  const key = String(code || "").toLowerCase().trim();
  return map[key] ?? "USD";
};

const formattedReferences = computed(() => {
  if (!props.reference || !Array.isArray(props.reference)) return [];
  return props.reference.map(payment => {
    const currency = normalizeCurrency(payment.order_currency);
    return {
      ...payment,
      method_label: translateMethod(payment.method || payment.payment_method),
      amount_display: new Intl.NumberFormat("es-VE", { 
        style: "currency", 
        currency
      }).format(payment.amount || 0)
    };
  });
});

const confirmReference = async (item) => {
  try {
    loading.value = true;
    await axios.patch("/finances/cash-closure/confirm-reference", {
      order_id: item.order_id,
      reference_code: item.reference
    });
    
    // Actualizar localmente el estado de confirmación
    item.is_confirmed = true;
    toast.success("Referencia confirmada correctamente");
  } catch (error) {
    console.error("Error al confirmar referencia:", error);
    toast.error("No se pudo confirmar la referencia");
  } finally {
    loading.value = false;
  }
};

const closeModal = () => {
  dialogVisible.value = false;
  emit("modal-closed");
  emit("close");
};
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="900px"
    scrollable
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-clipboard-list"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Consolidación de Referencias
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Cierre Diario N° {{ props.cashData.id }} • Gestión de Arqueo
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeModal"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Listado de Transacciones</span>
        </div>

        <VCard
          variant="flat"
          class="rounded-xl border shadow-sm overflow-hidden bg-white"
        >
          <VDataTable
            :headers="headers"
            :items="formattedReferences"
            class="reference-table"
            density="comfortable"
            hover
            :loading="loading"
          >
            <!-- Vendedor -->
            <template #item.seller_name="{ item }">
              <div class="d-flex align-center gap-3 py-1">
                <VAvatar
                  size="32"
                  color="primary"
                  variant="tonal"
                  class="font-weight-black text-caption rounded-lg"
                >
                  {{ item.seller_name?.charAt(0).toUpperCase() }}
                </VAvatar>
                <div class="d-flex flex-column leading-none">
                  <span class="font-weight-black text-high-emphasis">{{ item.seller_name }}</span>
                  <span class="text-super-xs text-disabled uppercase mt-1">Cajero Asignado</span>
                </div>
              </div>
            </template>

            <!-- Método -->
            <template #item.method_label="{ item }">
              <VChip
                size="x-small"
                variant="flat"
                color="info"
                class="font-weight-black px-2 shadow-sm uppercase"
              >
                {{ item.method_label }}
              </VChip>
            </template>

            <!-- Referencia -->
            <template #item.reference="{ item }">
              <code class="px-2 py-1 bg-light rounded text-primary font-weight-black border border-primary border-opacity-10">{{ item.reference }}</code>
            </template>

            <!-- Monto -->
            <template #item.amount="{ item }">
              <span class="font-weight-black text-success">{{ item.amount_display }}</span>
            </template>

            <!-- Acciones / Estado -->
            <template #item.actions="{ item }">
              <div
                v-if="item.is_confirmed"
                class="d-flex align-center justify-center gap-1 text-success bg-success bg-opacity-10 rounded-pill px-3 py-1"
              >
                <VIcon
                  icon="tabler-circle-check"
                  size="16"
                />
                <span class="text-super-xs font-weight-black uppercase">Confirmada</span>
              </div>
              <VBtn
                v-else
                size="small"
                variant="flat"
                color="primary"
                class="font-weight-black rounded-lg shadow-sm text-super-xs px-4"
                @click="confirmReference(item)"
                :loading="loading"
              >
                <VIcon
                  start
                  icon="tabler-check"
                  size="14"
                />
                Confirmar
              </VBtn>
            </template>

            <template #no-data>
              <div class="py-12 text-center bg-white">
                <VIcon
                  icon="tabler-clipboard-off"
                  size="48"
                  color="disabled"
                  class="mb-3 opacity-20"
                />
                <p class="text-subtitle-2 font-weight-black text-disabled uppercase letter-spacing-1">
                  Sin referencias pendientes en este cierre
                </p>
              </div>
            </template>
          </VDataTable>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow
          no-gutters
          class="w-100"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              disabled
            >
              <VIcon
                start
                icon="tabler-printer"
                size="18"
              />
              Imprimir Arqueo
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="closeModal"
            >
              Finalizar Revisión
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e5128 100%
  );
}

.bg-light {
  background-color: #f8faff !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.reference-table :deep(.v-data-table-header) {
  background-color: #f1f5f9;
}

.reference-table :deep(.v-data-table-header th) {
  color: #64748b !important;
  font-size: 0.65rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  border-bottom: 2px solid #e2e8f0 !important;
}

.reference-table :deep(td) {
  padding-block: 10px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.05) !important;
}

.italic {
  font-style: italic;
}
</style>
