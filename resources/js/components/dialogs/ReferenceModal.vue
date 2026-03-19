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
    <VCard class="rounded-xl overflow-hidden border shadow-sm">
      <!-- Header Premium -->
      <VCardTitle class="pa-0 border-b bg-primary">
        <div class="d-flex align-center justify-space-between w-100 pa-6">
          <div class="d-flex align-center gap-3">
             <VAvatar color="white" variant="tonal" class="rounded-lg shadow-sm">
                <VIcon icon="tabler-clipboard-list" color="white" />
             </VAvatar>
             <div>
               <h3 class="text-h6 font-weight-black text-white mb-0 uppercase leading-none">CONSOLIDACIÓN DE REFERENCIAS</h3>
               <span class="text-xs text-white opacity-80 font-weight-medium uppercase">Cierre Diario N° {{ props.cashData.id }} • Gestión de Arqueo</span>
             </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="text"
            color="white"
            @click="closeModal"
            class="rounded-lg"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-0">
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
            <div class="d-flex align-center gap-2">
              <VAvatar size="28" color="primary" variant="tonal">
                <span class="text-caption font-weight-bold">{{ item.seller_name?.charAt(0).toUpperCase() }}</span>
              </VAvatar>
              <span class="font-weight-medium">{{ item.seller_name }}</span>
            </div>
          </template>

          <!-- Método -->
          <template #item.method_label="{ item }">
            <VChip size="small" variant="tonal" color="info" label>
              {{ item.method_label }}
            </VChip>
          </template>

          <!-- Referencia -->
          <template #item.reference="{ item }">
            <code class="text-primary font-weight-bold">{{ item.reference }}</code>
          </template>

          <!-- Monto -->
          <template #item.amount="{ item }">
            <span class="font-weight-bold text-success">{{ item.amount_display }}</span>
          </template>

          <!-- Acciones / Estado -->
          <template #item.actions="{ item }">
            <div v-if="item.is_confirmed" class="d-flex align-center justify-center gap-1 text-success">
              <VIcon icon="tabler-circle-check" size="20" />
              <span class="text-caption font-weight-bold">CONFIRMADA</span>
            </div>
            <VBtn
              v-else
              size="small"
              variant="elevated"
              color="primary"
              prepend-icon="tabler-check"
              @click="confirmReference(item)"
              :loading="loading"
            >
              Confirmar
            </VBtn>
          </template>

          <template #no-data>
            <div class="py-10 text-center">
              <VIcon icon="tabler-clipboard-off" size="48" color="disabled" class="mb-2" />
              <p class="text-body-1 text-disabled">No hay referencias pendientes para este cierre</p>
            </div>
          </template>
        </VDataTable>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex flex-column gap-2">
        <VBtn
          color="secondary"
          variant="tonal"
          prepend-icon="tabler-printer"
          class="flex-grow-1 w-100"
          @click="closeModal"
          disabled
        >
          Imprimir Reporte Arqueo
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="flex-grow-1 w-100"
          @click="closeModal"
        >
          Finalizar Revisión
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.reference-table :deep(.v-data-table-header) {
  background-color: #f8fafc;
}

.reference-table :deep(th) {
  color: #64748b !important;
  font-size: 0.75rem !important;
  font-weight: bold !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.reference-table :deep(td) {
  padding-block: 12px !important;
}
</style>
