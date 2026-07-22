<script setup>
import { computed, defineEmits, defineProps } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  cashData: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(["update:isDialogVisible", "refresh"]);

const dialogVisible = computed({
  get() {
    return props.isDialogVisible;
  },
  set(value) {
    emit("update:isDialogVisible", value);
  },
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
};



// Desglosa las discrepancias de una caja individual
const getMismatchesList = (closing) => {
  const list = [];
  const mismatches = closing.blind_mismatches 
    ? (typeof closing.blind_mismatches === 'string' ? JSON.parse(closing.blind_mismatches) : closing.blind_mismatches) 
    : [];

  if (!Array.isArray(mismatches)) return [];

  mismatches.forEach((m) => {
    let label = "";
    let systemVal = 0;
    let declaredVal = 0;
    let currency = "USD";
    let type = m;

    if (m === "usd" || m === "usd_cash") {
      label = "Efectivo USD";
      systemVal = parseFloat(closing.usd_cash || 0) + parseFloat(closing.usd_cash_payment_credit || 0);
      declaredVal = parseFloat(closing.declared_usd || 0);
      currency = "USD";
    } else if (m === "cop" || m === "cop_cash") {
      label = "Efectivo COP";
      systemVal = parseFloat(closing.cop_cash || 0) + parseFloat(closing.cop_cash_payment_credit || 0);
      declaredVal = parseFloat(closing.declared_cop || 0);
      currency = "COP";
    } else if (m === "bs_card") {
      label = "Tarjetas BS (POS)";
      systemVal = parseFloat(closing.bs_card_debito || 0) + parseFloat(closing.bs_card_credit || 0);
      declaredVal = parseFloat(closing.declared_bs_card || 0);
      currency = "BS";
    } else if (m === "bs_mobile") {
      label = "Pago Móvil / Transf BS";
      systemVal = parseFloat(closing.bs_transfer || 0) + parseFloat(closing.bs_mobile || 0);
      declaredVal = parseFloat(closing.declared_bs_mobile || 0);
      currency = "BS";
    } else if (m === "credit") {
      label = "Crédito USD";
      systemVal = parseFloat(closing.usd_credit || 0);
      declaredVal = parseFloat(closing.declared_credit || 0);
      currency = "USD";
    } else if (m === "cop_transfer") {
      label = "Transferencia COP";
      systemVal = parseFloat(closing.cop_transfer || 0);
      declaredVal = parseFloat(closing.declared_cop_transfer || 0);
      currency = "COP";
    }

    const difference = declaredVal - systemVal;

    // Si la moneda es COP y es un sobrante positivo, no lo catalogamos como descuadre a gestionar
    if (currency === 'COP' && difference >= 0) {
      return;
    }

    if (label) {
      list.push({
        type,
        label,
        systemVal,
        declaredVal,
        difference,
        currency,
        isSobrante: difference > 0,
      });
    }
  });

  return list;
};

// Obtenemos solo las cajas que tienen descuadres reales y no son sobrantes de COP
const closuresWithMismatches = computed(() => {
  const closings = props.cashData.cash_closings || [];
  return closings.filter((c) => {
    const list = getMismatchesList(c);
    return list.length > 0;
  });
});

// Llama al backend para procesar y aceptar la discrepancia
const handleAcceptMismatch = async (closingId, item) => {
  try {
    toast.info("Conciliando descuadre en sistema...");
    const { data } = await axios.post("/finances/cash-closure/mismatches/accept", {
      cash_closing_id: closingId,
      currency: item.currency,
      mismatch_type: item.type,
      difference: item.difference,
    });

    if (data.status === "success") {
      toast.success("Descuadre aceptado y flujo ajustado exitosamente.");
      emit("refresh");
      
      // Si ya no quedan descuadres en ninguna caja, cerramos el modal
      setTimeout(() => {
        if (closuresWithMismatches.value.length === 0) {
          closeModal();
        }
      }, 500);
    }
  } catch (error) {
    console.error("Error al procesar el descuadre:", error);
    const msg = error.response?.data?.message || "Ocurrió un error inesperado.";
    toast.error(msg);
  }
};
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="850px"
    scrollable
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Header -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-alert-triangle" size="24" color="error" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Conciliación de Descuadres y Discrepancias
            </h2>
            <span class="text-white opacity-75 font-weight-bold text-xs mt-1 uppercase">
              Cierre Diario N° {{ props.cashData?.id }}
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="closeModal" />
        </div>
      </VCardTitle>

      <!-- Content -->
      <VCardText class="pa-4 pa-sm-6 bg-light">
        <div v-if="closuresWithMismatches.length === 0" class="text-center py-10">
          <VIcon icon="tabler-circle-check" size="64" color="success" class="mb-3" />
          <h3 class="text-h6 font-weight-black text-success">¡Todo Cuadrado!</h3>
          <p class="text-disabled text-sm">No se registran descuadres pendientes en este cierre diario.</p>
        </div>

        <div v-else class="d-flex flex-column gap-6">
          <div v-for="closing in closuresWithMismatches" :key="closing.id" class="mismatch-box bg-white rounded-xl border pa-4 shadow-sm">
            <!-- Info Vendedor -->
            <div class="d-flex align-center justify-space-between border-b pb-3 mb-3">
              <div class="d-flex align-center gap-3">
                <VAvatar color="error" size="36" variant="tonal" class="font-weight-black rounded-lg">
                  {{ (closing.seller?.username || '?').charAt(0).toUpperCase() }}
                </VAvatar>
                <div>
                  <h4 class="text-subtitle-1 font-weight-black text-high-emphasis text-capitalize leading-none">
                    {{ closing.seller?.username }}
                  </h4>
                  <span class="text-super-xs font-weight-bold text-disabled uppercase">Caja #{{ closing.id }}</span>
                </div>
              </div>
              <VChip color="error" size="small" variant="flat" class="font-weight-black uppercase">
                PENDIENTE AUDITORÍA
              </VChip>
            </div>

            <!-- Tabla de Discrepancias por Moneda/Método -->
            <VTable density="compact" class="bg-transparent text-no-wrap mb-4">
              <thead>
                <tr>
                  <th class="text-caption font-weight-black text-disabled uppercase pl-0">Método / Moneda</th>
                  <th class="text-caption font-weight-black text-disabled uppercase text-end">Sistema (Teórico)</th>
                  <th class="text-caption font-weight-black text-disabled uppercase text-end">Declarado (Físico)</th>
                  <th class="text-caption font-weight-black text-disabled uppercase text-end">Diferencia</th>
                  <th class="text-caption font-weight-black text-disabled uppercase text-center" style="width: 140px;">Acción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in getMismatchesList(closing)" :key="item.type" class="mismatch-row">
                  <td class="font-weight-bold pl-0 py-2">{{ item.label }}</td>
                  <td class="text-end font-weight-bold py-2">{{ formatCurrency(item.systemVal, item.currency) }}</td>
                  <td class="text-end font-weight-bold py-2">{{ formatCurrency(item.declaredVal, item.currency) }}</td>
                  <td class="text-end py-2">
                    <span :class="item.isSobrante ? 'text-success' : 'text-error'" class="font-weight-black">
                      {{ item.difference > 0 ? '+' : '' }}{{ formatCurrency(item.difference, item.currency) }}
                    </span>
                    <div class="text-super-xs font-weight-bold uppercase" :class="item.isSobrante ? 'text-success' : 'text-error'">
                      {{ item.isSobrante ? 'Sobrante (Abono)' : 'Faltante (Descuento)' }}
                    </div>
                  </td>
                  <td class="text-center py-2">
                    <VBtn
                      size="small"
                      :color="item.isSobrante ? 'success' : 'error'"
                      variant="flat"
                      class="rounded-lg font-weight-black text-xs px-2"
                      @click="handleAcceptMismatch(closing.id, item)"
                    >
                      Aceptar
                    </VBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>

            <!-- Comentarios del Cajero -->
            <div class="bg-grey-lighten-4 rounded-lg pa-3 border text-caption" style="line-height: 1.4;">
              <strong class="text-disabled uppercase d-block mb-1">Notas / Justificación del cajero:</strong>
              <span class="text-medium-emphasis font-weight-medium italic">{{ closing.blind_note || 'Sin comentarios registrados por el cajero.' }}</span>
            </div>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-white border-t px-6">
        <VBtn variant="tonal" color="secondary" block height="48" class="font-weight-black rounded-lg" @click="closeModal">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
}
.border-b {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.1);
}
.mismatch-box {
  transition: all 0.2s ease-in-out;
}
.mismatch-box:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important;
}
.mismatch-row:hover {
  background-color: rgba(var(--v-theme-error), 0.02) !important;
}
</style>
