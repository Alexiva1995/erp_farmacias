<script setup lang="js">
// Modal unificado de discrepancias y resultados de sincronización con droguerías
import SupplierDiscrepancyTab from "@/components/dialogs/SupplierDiscrepancyTab.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { ref } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  discrepancies: {
    type: Object,
    default: () => ({
      paid_in_erp_pending_in_dronena: [],
      pending_in_erp_paid_in_dronena: [],
      total_discrepancies: 0,
    }),
  },
  syncSummary: {
    type: Object,
    default: () => ({
      updated: 0,
      created: 0,
      skipped: 0,
      total_extracted: 0,
      dronena: {},
      drocerca: {},
      mafarta: {},
      cristmedicals: {},
      dromega: {},
      drosymca: {},
    }),
  },
});

const emit = defineEmits(["update:modelValue", "close", "invoices-marked-as-paid"]);

const activeTab = ref("dronena");
const isMarkingPaid = ref(false);
const isMarkingPending = ref(false);

// Configuración declarativa de todas las droguerías sincronizables
const suppliersConfig = [
  { key: "dronena", title: "Dronena", icon: "tabler-building-warehouse" },
  { key: "drocerca", title: "Drocerca", icon: "tabler-building-factory-2" },
  { key: "mafarta", title: "Cobeca / Mafarta", icon: "tabler-building" },
  { key: "cristmedicals", title: "Cristmedicals", icon: "tabler-building-hospital" },
  { key: "dromega", title: "Droguería Mega", icon: "tabler-pill" },
  { key: "drosymca", title: "Drosymca", icon: "tabler-building-store" },
];

const getSupplierSummary = (key) => {
  return props.syncSummary?.[key] || (key === "dronena" ? props.syncSummary : {}) || {};
};

const getPaidInErpPending = (key) => {
  if (key === "dronena") {
    return (
      props.discrepancies?.paid_in_erp_pending_in_dronena ||
      props.syncSummary?.dronena?.discrepancies?.paid_in_erp_pending_in_dronena ||
      []
    );
  }
  const summary = props.syncSummary?.[key];
  return summary?.discrepancies?.[`paid_in_erp_pending_in_${key}`] || [];
};

const getPendingInErpPaid = (key) => {
  if (key === "dronena") {
    return (
      props.discrepancies?.pending_in_erp_paid_in_dronena ||
      props.syncSummary?.dronena?.discrepancies?.pending_in_erp_paid_in_dronena ||
      []
    );
  }
  const summary = props.syncSummary?.[key];
  return summary?.discrepancies?.[`pending_in_erp_paid_in_${key}`] || [];
};

const getProcessedDetails = (key) => {
  const summary = props.syncSummary?.[key];
  if (key === "dronena") {
    return summary?.details || props.syncSummary?.details || [];
  }
  return summary?.details || [];
};

const getBadgeInfo = (key) => {
  const paidPending = getPaidInErpPending(key);
  const pendingPaid = getPendingInErpPaid(key);
  const totalDiscrepancies = paidPending.length + pendingPaid.length;

  if (totalDiscrepancies > 0) {
    return { count: totalDiscrepancies, color: "warning" };
  }

  const summary = getSupplierSummary(key);
  if ((summary.created || 0) > 0) {
    return { count: `+${summary.created}`, color: "success" };
  }
  if ((summary.updated || 0) > 0) {
    return { count: summary.updated, color: "info" };
  }
  if ((summary.total_extracted || 0) > 0) {
    return { count: summary.total_extracted, color: "secondary" };
  }

  return null;
};

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("close");
};

// ── Handlers universales de resolución de discrepancias ────────────────────
const handleMarkPendingAsPaid = async ({ supplierTitle, items }) => {
  const count = items?.length || 0;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Marcar las ${count} facturas de ${supplierTitle} como Pagadas?`,
    text: `Estas facturas ya figuran liquidadas en ${supplierTitle} y pasarán automáticamente a estado Pagada (status_payment = 1) en tu ERP.`,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, marcar como pagadas",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#28c76f",
  });

  if (!result.isConfirmed) return;

  isMarkingPaid.value = true;
  try {
    const invoiceIds = items.map((i) => i.id).filter(Boolean);
    const invoiceNumbers = items.filter((i) => !i.id).map((i) => i.invoice_number);

    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-paid", {
      invoice_ids: invoiceIds,
      invoice_numbers: invoiceNumbers,
    });

    toast.success(data.message || `${count} facturas de ${supplierTitle} marcadas como pagadas`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error(`Error marcando facturas de ${supplierTitle}:`, error);
    toast.error(error.response?.data?.message || `Error al marcar las facturas de ${supplierTitle} como pagadas.`);
  } finally {
    isMarkingPaid.value = false;
  }
};

const handleMarkPaidAsPending = async ({ supplierTitle, items }) => {
  const count = items?.length || 0;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Pasar las ${count} facturas de ${supplierTitle} a Por Pagar?`,
    text: `Estas facturas aún registran saldo pendiente en ${supplierTitle} y volverán a estado Pendiente (status_payment = 0) en tu ERP.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, cambiar a Por Pagar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#ff9f43",
  });

  if (!result.isConfirmed) return;

  isMarkingPending.value = true;
  try {
    const invoiceIds = items.map((i) => i.id).filter(Boolean);
    const invoiceNumbers = items.filter((i) => !i.id).map((i) => i.invoice_number);

    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-pending", {
      invoice_ids: invoiceIds,
      invoice_numbers: invoiceNumbers,
    });

    toast.success(data.message || `${count} facturas de ${supplierTitle} pasadas a Por Pagar`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error(`Error revirtiendo facturas de ${supplierTitle}:`, error);
    toast.error(error.response?.data?.message || `Error al actualizar las facturas de ${supplierTitle}.`);
  } finally {
    isMarkingPending.value = false;
  }
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1180"
    scrollable
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-xl overflow-hidden shadow-2xl modal-card">
      <!-- ── Encabezado Principal Vuetify 3 ────────────────────────────── -->
      <VCardItem class="bg-primary py-4 px-6">
        <div class="d-flex align-center justify-space-between w-100">
          <div class="d-flex align-center gap-3">
            <VAvatar color="surface" variant="tonal" size="42" class="rounded-lg">
              <VIcon icon="tabler-robot" size="24" color="white" />
            </VAvatar>
            <div>
              <VCardTitle class="text-white text-h6 font-weight-bold mb-0">
                Resultado de Sincronización con Droguerías
              </VCardTitle>
              <VCardSubtitle class="text-white text-caption opacity-90">
                Resumen de Dronena, Drocerca, Cobeca / Mafarta, Cristmedicals, Droguería Mega y Drosymca
              </VCardSubtitle>
            </div>
          </div>
          <VBtn icon variant="text" color="white" size="small" @click="closeDialog">
            <VIcon icon="tabler-x" size="20" />
          </VBtn>
        </div>
      </VCardItem>

      <!-- ── Pestañas Dinámicas por Droguería ──────────────────────────── -->
      <div class="bg-surface border-b px-2">
        <VTabs v-model="activeTab" color="primary" density="compact" show-arrows>
          <VTab
            v-for="sup in suppliersConfig"
            :key="sup.key"
            :value="sup.key"
          >
            <VIcon :icon="sup.icon" class="mr-1" size="18" />
            {{ sup.title }}
            <VChip
              v-if="getBadgeInfo(sup.key)"
              size="x-small"
              :color="getBadgeInfo(sup.key).color"
              variant="flat"
              class="ml-1"
            >
              {{ getBadgeInfo(sup.key).count }}
            </VChip>
          </VTab>
        </VTabs>
      </div>

      <!-- ── Contenedor de Pestañas Desacoplado ────────────────────────── -->
      <VCardText class="pa-6 modal-scroll-content">
        <VWindow v-model="activeTab">
          <VWindowItem
            v-for="sup in suppliersConfig"
            :key="sup.key"
            :value="sup.key"
          >
            <SupplierDiscrepancyTab
              :supplier-key="sup.key"
              :supplier-title="sup.title"
              :supplier-icon="sup.icon"
              :summary-data="getSupplierSummary(sup.key)"
              :paid-in-erp-pending="getPaidInErpPending(sup.key)"
              :pending-in-erp-paid="getPendingInErpPaid(sup.key)"
              :processed-details="getProcessedDetails(sup.key)"
              :is-marking-paid="isMarkingPaid"
              :is-marking-pending="isMarkingPending"
              @mark-pending-as-paid="handleMarkPendingAsPaid"
              @mark-paid-as-pending="handleMarkPaidAsPending"
            />
          </VWindowItem>
        </VWindow>
      </VCardText>

      <!-- ── Pie de Modal ──────────────────────────────────────────────── -->
      <VDivider />
      <VCardActions class="px-6 py-3 bg-surface justify-end">
        <VBtn variant="flat" color="primary" @click="closeDialog">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.modal-scroll-content {
  max-height: 75vh;
  overflow-y: auto;
}
</style>