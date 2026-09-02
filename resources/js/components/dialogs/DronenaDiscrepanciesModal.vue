<script setup>
import { computed, ref } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";

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
    }),
  },
});

const emit = defineEmits(["update:modelValue", "close", "invoices-marked-as-paid"]);

const activeTab = ref("dronena");
const isMarkingPaid = ref(false);
const isMarkingPending = ref(false);

const paidInErpPendingInPortal = computed(
  () => props.discrepancies?.paid_in_erp_pending_in_dronena || []
);
const pendingInErpPaidInPortal = computed(
  () => props.discrepancies?.pending_in_erp_paid_in_dronena || []
);
const hasDiscrepancies = computed(
  () => (paidInErpPendingInPortal.value.length + pendingInErpPaidInPortal.value.length) > 0
);

const drocercaData = computed(() => props.syncSummary?.drocerca || {});
const mafartaData = computed(() => props.syncSummary?.mafarta || {});
const cristmedicalsData = computed(() => props.syncSummary?.cristmedicals || {});
const dromegaData = computed(() => props.syncSummary?.dromega || {});
const dronenaData = computed(() => props.syncSummary?.dronena || {});

const drocercaPendingPaid = computed(
  () => drocercaData.value?.discrepancies?.pending_in_erp_paid_in_drocerca || []
);
const mafartaPendingPaid = computed(
  () => mafartaData.value?.discrepancies?.pending_in_erp_paid_in_mafarta || []
);
const cristmedicalsPendingPaid = computed(
  () => cristmedicalsData.value?.discrepancies?.pending_in_erp_paid_in_cristmedicals || []
);

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("close");
};

const handleMarkAllPendingAsPaid = async () => {
  const count = pendingInErpPaidInPortal.value.length;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Marcar las ${count} facturas como Pagadas?`,
    text: "Estas facturas ya figuran liquidadas en Dronena y pasarán automáticamente a estado Pagada (status_payment = 1) en tu ERP.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, marcar como pagadas",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#28c76f",
  });

  if (!result.isConfirmed) return;

  isMarkingPaid.value = true;
  try {
    const invoiceIds = pendingInErpPaidInPortal.value.map((i) => i.id);
    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-paid", {
      invoice_ids: invoiceIds,
    });

    toast.success(data.message || `${count} facturas marcadas como pagadas`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error("Error marcando facturas:", error);
    toast.error(error.response?.data?.message || "Error al marcar las facturas como pagadas.");
  } finally {
    isMarkingPaid.value = false;
  }
};

const handleMarkAllPaidAsPending = async () => {
  const count = paidInErpPendingInPortal.value.length;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Pasar las ${count} facturas a Pendientes (Por Pagar)?`,
    text: "Estas facturas volverán al estado Por Pagar (status_payment = 0) en tu ERP porque aún tienen saldo pendiente por cobrar en Dronena.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, pasar a Por Pagar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#ff9f43",
  });

  if (!result.isConfirmed) return;

  isMarkingPending.value = true;
  try {
    const invoiceIds = paidInErpPendingInPortal.value.map((i) => i.id);
    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-pending", {
      invoice_ids: invoiceIds,
    });

    toast.success(data.message || `${count} facturas reabiertas como pendientes exitosamente`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error("Error marcando facturas como pendientes:", error);
    toast.error(error.response?.data?.message || "Error al reabrir las facturas como pendientes.");
  } finally {
    isMarkingPending.value = false;
  }
};

const handleMarkAllDrocercaPendingAsPaid = async () => {
  const count = drocercaPendingPaid.value.length;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Marcar las ${count} facturas de Drocerca como Pagadas?`,
    text: "Estas facturas ya figuran liquidadas en Drocerca y pasarán automáticamente a estado Pagada (status_payment = 1) en tu ERP.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, marcar como pagadas",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#28c76f",
  });

  if (!result.isConfirmed) return;

  isMarkingPaid.value = true;
  try {
    const invoiceIds = drocercaPendingPaid.value.map((i) => i.id);
    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-paid", {
      invoice_ids: invoiceIds,
    });

    toast.success(data.message || `${count} facturas de Drocerca marcadas como pagadas`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error("Error marcando facturas Drocerca:", error);
    toast.error(error.response?.data?.message || "Error al marcar las facturas como pagadas.");
  } finally {
    isMarkingPaid.value = false;
  }
};

const handleMarkAllMafartaPendingAsPaid = async () => {
  const count = mafartaPendingPaid.value.length;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Marcar las ${count} facturas de Cobeca / Mafarta como Pagadas?`,
    text: "Estas facturas ya figuran liquidadas en Cobeca y pasarán automáticamente a estado Pagada (status_payment = 1) en tu ERP.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, marcar como pagadas",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#28c76f",
  });

  if (!result.isConfirmed) return;

  isMarkingPaid.value = true;
  try {
    const invoiceIds = mafartaPendingPaid.value.map((i) => i.id);
    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-paid", {
      invoice_ids: invoiceIds,
    });

    toast.success(data.message || `${count} facturas de Cobeca marcadas como pagadas`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error("Error marcando facturas Cobeca:", error);
    toast.error(error.response?.data?.message || "Error al marcar las facturas como pagadas.");
  } finally {
    isMarkingPaid.value = false;
  }
};

const dromegaPaidInErpPending = computed(
  () => dromegaData.value?.discrepancies?.paid_in_erp_pending_in_dromega || []
);
const dromegaPendingInErpPaid = computed(
  () => dromegaData.value?.discrepancies?.pending_in_erp_paid_in_dromega || []
);
const hasDromegaDiscrepancies = computed(
  () => (dromegaPaidInErpPending.value.length + dromegaPendingInErpPaid.value.length) > 0
);

const handleMarkAllDromegaPendingAsPaid = async () => {
  const count = dromegaPendingInErpPaid.value.length;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Marcar las ${count} facturas de Droguería Mega como Pagadas?`,
    text: "Estas facturas ya figuran liquidadas en Droguería Mega y pasarán automáticamente a estado Pagada (status_payment = 1) en tu ERP.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, marcar como pagadas",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#28c76f",
  });

  if (!result.isConfirmed) return;

  isMarkingPaid.value = true;
  try {
    const invoiceIds = dromegaPendingInErpPaid.value.map((i) => i.id);
    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-paid", {
      invoice_ids: invoiceIds,
    });

    toast.success(data.message || `${count} facturas de Droguería Mega marcadas como pagadas`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error("Error marcando facturas Droguería Mega:", error);
    toast.error(error.response?.data?.message || "Error al marcar las facturas como pagadas.");
  } finally {
    isMarkingPaid.value = false;
  }
};

const handleMarkAllDrocercaPaidAsPending = async () => {
  const count = drocercaPaidInErpPending.value.length;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Pasar las ${count} facturas a Por Pagar?`,
    text: "Estas facturas aún registran saldo pendiente en Drocerca y volverán a estado Pendiente (status_payment = 0) en tu ERP.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, cambiar a Por Pagar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#ff9f43",
  });

  if (!result.isConfirmed) return;

  isMarkingPending.value = true;
  try {
    const invoiceIds = drocercaPaidInErpPending.value.map((i) => i.id);
    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-pending", {
      invoice_ids: invoiceIds,
    });

    toast.success(data.message || `${count} facturas pasadas a estado Por Pagar`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error("Error revirtiendo facturas Drocerca:", error);
    toast.error(error.response?.data?.message || "Error al actualizar las facturas.");
  } finally {
    isMarkingPending.value = false;
  }
};

const handleMarkAllMafartaPaidAsPending = async () => {
  const count = mafartaPaidInErpPending.value.length;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Pasar las ${count} facturas a Por Pagar?`,
    text: "Estas facturas aún registran saldo pendiente en Cobeca / Mafarta y volverán a estado Pendiente (status_payment = 0) en tu ERP.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, cambiar a Por Pagar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#ff9f43",
  });

  if (!result.isConfirmed) return;

  isMarkingPending.value = true;
  try {
    const invoiceIds = mafartaPaidInErpPending.value.map((i) => i.id);
    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-pending", {
      invoice_ids: invoiceIds,
    });

    toast.success(data.message || `${count} facturas pasadas a estado Por Pagar`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error("Error revirtiendo facturas Cobeca:", error);
    toast.error(error.response?.data?.message || "Error al actualizar las facturas.");
  } finally {
    isMarkingPending.value = false;
  }
};

const cristmedicalsPaidInErpPending = computed(
  () => cristmedicalsData.value?.discrepancies?.paid_in_erp_pending_in_cristmedicals || []
);
const cristmedicalsPendingInErpPaid = computed(
  () => cristmedicalsData.value?.discrepancies?.pending_in_erp_paid_in_cristmedicals || []
);
const drocercaPaidInErpPending = computed(
  () => drocercaData.value?.discrepancies?.paid_in_erp_pending_in_drocerca || []
);
const mafartaPaidInErpPending = computed(
  () => mafartaData.value?.discrepancies?.paid_in_erp_pending_in_mafarta || []
);

const handleMarkAllCristmedicalsPendingAsPaid = async () => {
  const count = cristmedicalsPendingInErpPaid.value.length;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Marcar las ${count} facturas de Cristmedicals como Pagadas?`,
    text: "Estas facturas ya figuran liquidadas en Cristmedicals y pasarán automáticamente a estado Pagada (status_payment = 1) en tu ERP.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, marcar como pagadas",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#28c76f",
  });

  if (!result.isConfirmed) return;

  isMarkingPaid.value = true;
  try {
    const invoiceIds = cristmedicalsPendingInErpPaid.value.map((i) => i.id);
    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-paid", {
      invoice_ids: invoiceIds,
    });

    toast.success(data.message || `${count} facturas de Cristmedicals marcadas como pagadas`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error("Error marcando facturas Cristmedicals:", error);
    toast.error(error.response?.data?.message || "Error al marcar las facturas como pagadas.");
  } finally {
    isMarkingPaid.value = false;
  }
};

const handleMarkAllCristmedicalsPaidAsPending = async () => {
  const count = cristmedicalsPaidInErpPending.value.length;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Pasar las ${count} facturas a Por Pagar?`,
    text: "Estas facturas aún registran saldo pendiente en Cristmedicals y volverán a estado Pendiente (status_payment = 0) en tu ERP.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, cambiar a Por Pagar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#ff9f43",
  });

  if (!result.isConfirmed) return;

  isMarkingPending.value = true;
  try {
    const invoiceIds = cristmedicalsPaidInErpPending.value.map((i) => i.id);
    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-pending", {
      invoice_ids: invoiceIds,
    });

    toast.success(data.message || `${count} facturas pasadas a estado Por Pagar`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error("Error revirtiendo facturas Cristmedicals:", error);
    toast.error(error.response?.data?.message || "Error al actualizar las facturas.");
  } finally {
    isMarkingPending.value = false;
  }
};

const handleMarkAllDromegaPaidAsPending = async () => {
  const count = dromegaPaidInErpPending.value.length;
  if (count === 0) return;

  const result = await Swal.fire({
    title: `¿Pasar las ${count} facturas a Por Pagar?`,
    text: "Estas facturas aún registran saldo pendiente en Droguería Mega y volverán a estado Pendiente (status_payment = 0) en tu ERP.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, cambiar a Por Pagar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#ff9f43",
  });

  if (!result.isConfirmed) return;

  isMarkingPending.value = true;
  try {
    const invoiceIds = dromegaPaidInErpPending.value.map((i) => i.id);
    const { data } = await axios.post("/finances/pending-payments/invoices/bulk-mark-as-pending", {
      invoice_ids: invoiceIds,
    });

    toast.success(data.message || `${count} facturas pasadas a estado Por Pagar`);
    emit("invoices-marked-as-paid");
    closeDialog();
  } catch (error) {
    console.error("Error revirtiendo facturas Droguería Mega:", error);
    toast.error(error.response?.data?.message || "Error al actualizar las facturas.");
  } finally {
    isMarkingPending.value = false;
  }
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1000"
    scrollable
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-xl overflow-hidden shadow-2xl modal-card">
      <!-- Encabezado con Vuetify 3 puro (bg-primary y texto blanco) -->
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
                Resumen de Dronena, Drocerca, Cobeca / Mafarta y Cristmedicals
              </VCardSubtitle>
            </div>
          </div>
          <VBtn icon variant="text" color="white" size="small" @click="closeDialog">
            <VIcon icon="tabler-x" size="20" />
          </VBtn>
        </div>
      </VCardItem>

      <!-- Pestañas de Navegación por Droguería -->
      <div class="bg-surface border-b px-4">
        <VTabs v-model="activeTab" color="primary" density="compact">
          <VTab value="dronena">
            <VIcon icon="tabler-building-warehouse" class="mr-2" size="18" />
            Dronena
            <VChip
              v-if="hasDiscrepancies"
              size="x-small"
              color="warning"
              variant="flat"
              class="ml-2"
            >
              {{ paidInErpPendingInPortal.length + pendingInErpPaidInPortal.length }}
            </VChip>
          </VTab>
          <VTab value="drocerca">
            <VIcon icon="tabler-building-factory-2" class="mr-2" size="18" />
            Drocerca
            <VChip
              v-if="(drocercaPaidInErpPending.length + drocercaPendingPaid.length) > 0"
              size="x-small"
              color="warning"
              variant="flat"
              class="ml-2"
            >
              {{ drocercaPaidInErpPending.length + drocercaPendingPaid.length }}
            </VChip>
            <VChip
              v-else-if="(drocercaData.created || 0) > 0"
              size="x-small"
              color="success"
              variant="flat"
              class="ml-2"
            >
              +{{ drocercaData.created }} nuevas
            </VChip>
          </VTab>
          <VTab value="mafarta">
            <VIcon icon="tabler-building" class="mr-2" size="18" />
            Cobeca / Mafarta
            <VChip
              v-if="(mafartaPaidInErpPending.length + mafartaPendingPaid.length) > 0"
              size="x-small"
              color="warning"
              variant="flat"
              class="ml-2"
            >
              {{ mafartaPaidInErpPending.length + mafartaPendingPaid.length }}
            </VChip>
            <VChip
              v-else-if="(mafartaData.created || 0) > 0"
              size="x-small"
              color="success"
              variant="flat"
              class="ml-2"
            >
              +{{ mafartaData.created }} nuevas
            </VChip>
          </VTab>
          <VTab value="cristmedicals">
            <VIcon icon="tabler-building-hospital" class="mr-2" size="18" />
            Cristmedicals
            <VChip
              v-if="(cristmedicalsPaidInErpPending.length + cristmedicalsPendingInErpPaid.length) > 0"
              size="x-small"
              color="warning"
              variant="flat"
              class="ml-2"
            >
              {{ cristmedicalsPaidInErpPending.length + cristmedicalsPendingInErpPaid.length }}
            </VChip>
            <VChip
              v-else-if="(cristmedicalsData.created || 0) > 0"
              size="x-small"
              color="success"
              variant="flat"
              class="ml-2"
            >
              +{{ cristmedicalsData.created }} nuevas
            </VChip>
            <VChip
              v-else-if="(cristmedicalsData.updated || 0) > 0"
              size="x-small"
              color="info"
              variant="flat"
              class="ml-2"
            >
              {{ cristmedicalsData.updated }} act.
            </VChip>
          </VTab>
          <VTab value="dromega">
            <VIcon icon="tabler-pill" class="mr-2" size="18" />
            Droguería Mega
            <VChip
              v-if="hasDromegaDiscrepancies"
              size="x-small"
              color="warning"
              variant="flat"
              class="ml-2"
            >
              {{ dromegaPaidInErpPending.length + dromegaPendingInErpPaid.length }}
            </VChip>
            <VChip
              v-else-if="(dromegaData.created || 0) > 0"
              size="x-small"
              color="success"
              variant="flat"
              class="ml-2"
            >
              +{{ dromegaData.created }} nuevas
            </VChip>
            <VChip
              v-else-if="(dromegaData.updated || 0) > 0"
              size="x-small"
              color="info"
              variant="flat"
              class="ml-2"
            >
              {{ dromegaData.updated }} act.
            </VChip>
          </VTab>
        </VTabs>
      </div>

      <!-- Contenedor con Scroll nativo -->
      <VCardText class="pa-6 modal-scroll-content">
        <!-- V-WINDOW CON LAS PESTAÑAS -->
        <VWindow v-model="activeTab">
          <!-- ================= TAB DRONENA ================= -->
          <VWindowItem value="dronena">
            <!-- Resumen Rápido Dronena -->
            <VRow class="mb-4">
              <VCol cols="12" sm="4">
                <VCard variant="tonal" color="info" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Documentos en Portal</div>
                  <div class="text-h5 font-weight-bold">{{ dronenaData.total_extracted || props.syncSummary?.total_extracted || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="4">
                <VCard variant="tonal" color="success" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Actualizadas en ERP</div>
                  <div class="text-h5 font-weight-bold">{{ dronenaData.updated || props.syncSummary?.updated || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="4">
                <VCard
                  variant="tonal"
                  :color="hasDiscrepancies ? 'warning' : 'success'"
                  class="pa-3 rounded-lg text-center"
                >
                  <div class="text-caption text-medium-emphasis">Diferencias Detectadas</div>
                  <div class="text-h5 font-weight-bold">
                    {{ (paidInErpPendingInPortal.length + pendingInErpPaidInPortal.length) }}
                  </div>
                </VCard>
              </VCol>
            </VRow>

            <!-- Caso 1: Pagadas en ERP pero aún figuran PENDIENTES en Dronena -->
            <div v-if="paidInErpPendingInPortal.length > 0" class="mb-6">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-alert-circle" color="warning" size="22" />
                  <h4 class="text-subtitle-1 font-weight-bold text-warning mb-0">
                    Pagadas en el ERP pero aún PENDIENTES en Dronena ({{ paidInErpPendingInPortal.length }})
                  </h4>
                </div>

                <VBtn
                  color="warning"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-arrow-back-up"
                  class="rounded-lg shadow-sm font-weight-bold"
                  :loading="isMarkingPending"
                  @click="handleMarkAllPaidAsPending"
                >
                  Pasar a Por Pagar ({{ paidInErpPendingInPortal.length }})
                </VBtn>
              </div>

              <p class="text-caption text-medium-emphasis mb-3">
                Estas facturas ya las registraste como pagadas en el ERP, pero en el portal de Dronena todavía aparecen con saldo pendiente por cobrar:
              </p>

              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-right font-weight-bold">Monto ERP</th>
                    <th class="text-right font-weight-bold">Saldo en Dronena</th>
                    <th class="text-center font-weight-bold">Estado Portal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in paidInErpPendingInPortal" :key="item.id">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-right">{{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}</td>
                    <td class="text-right font-weight-bold text-error">
                      {{ Number(item.portal_amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} Bs
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="warning" variant="tonal" class="font-weight-medium">
                        Por Cobrar ({{ item.portal_type || 'FA' }})
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Caso 2: Pendientes en ERP pero ya NO figuran pendientes en Dronena CON BOTÓN DE ACCIÓN -->
            <div v-if="pendingInErpPaidInPortal.length > 0" class="mb-6">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-circle-check" color="info" size="22" />
                  <h4 class="text-subtitle-1 font-weight-bold text-info mb-0">
                    Pendientes en ERP pero LIQUIDADAS en Dronena ({{ pendingInErpPaidInPortal.length }})
                  </h4>
                </div>

                <VBtn
                  color="success"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-check-all"
                  class="rounded-lg shadow-sm font-weight-bold"
                  :loading="isMarkingPaid"
                  @click="handleMarkAllPendingAsPaid"
                >
                  Marcar Todas como Pagadas ({{ pendingInErpPaidInPortal.length }})
                </VBtn>
              </div>

              <p class="text-caption text-medium-emphasis mb-3">
                Estas facturas están pendientes en tu ERP pero en Dronena ya fueron cobradas/liquidadas. Puedes pasarlas a estado Pagadas directamente:
              </p>

              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-right font-weight-bold">Monto en ERP</th>
                    <th class="text-center font-weight-bold">Estado en ERP</th>
                    <th class="text-center font-weight-bold">Estado Portal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in pendingInErpPaidInPortal" :key="item.id">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-right font-weight-bold">
                      {{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="error" variant="tonal">Por Pagar</VChip>
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="success" variant="tonal">Liquidada en Dronena</VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Facturas Procesadas / Actualizadas desde Dronena -->
            <div v-if="dronenaData.details && dronenaData.details.length > 0" class="mt-6 mb-6">
              <h4 class="text-subtitle-1 font-weight-bold mb-2">Facturas Procesadas desde Dronena</h4>
              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-center font-weight-bold">Acción</th>
                    <th class="text-center font-weight-bold">Vencimiento</th>
                    <th class="text-center font-weight-bold">Indexada (FA$)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in dronenaData.details" :key="idx">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.action === 'updated' ? 'info' : 'success'" variant="tonal">
                        {{ item.action === 'updated' ? 'Actualizada' : item.action }}
                      </VChip>
                    </td>
                    <td class="text-center">{{ item.exp_date || 'N/A' }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.is_indexed ? 'error' : 'secondary'" variant="tonal">
                        {{ item.is_indexed ? 'Sí' : 'No' }}
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Estado Sin Discrepancias Dronena -->
            <div v-if="!hasDiscrepancies && (!dronenaData.details || dronenaData.details.length === 0)" class="text-center py-6">
              <VAvatar color="success" variant="tonal" size="56" class="mb-3">
                <VIcon icon="tabler-check" size="32" color="success" />
              </VAvatar>
              <h3 class="text-h6 font-weight-bold mb-1">¡Cuentas Dronena Cuadradas!</h3>
              <p class="text-body-2 text-medium-emphasis mb-0">
                No se encontraron discrepancias en Dronena. Todas las facturas coinciden entre el portal y tu ERP.
              </p>
            </div>
          </VWindowItem>

          <!-- ================= TAB DROCERCA ================= -->
          <VWindowItem value="drocerca">
            <VRow class="mb-4">
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="info" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Documentos en Portal</div>
                  <div class="text-h5 font-weight-bold">{{ drocercaData.total_extracted || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="success" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Actualizadas en ERP</div>
                  <div class="text-h5 font-weight-bold">{{ drocercaData.updated || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="primary" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Nuevas Creadas</div>
                  <div class="text-h5 font-weight-bold">{{ drocercaData.created || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard
                  variant="tonal"
                  :color="(drocercaPaidInErpPending.length + drocercaPendingPaid.length) > 0 ? 'warning' : 'success'"
                  class="pa-3 rounded-lg text-center"
                >
                  <div class="text-caption text-medium-emphasis">Diferencias Detectadas</div>
                  <div class="text-h5 font-weight-bold">
                    {{ (drocercaPaidInErpPending.length + drocercaPendingPaid.length) }}
                  </div>
                </VCard>
              </VCol>
            </VRow>

            <!-- Caso 1: Pagadas en ERP pero aún PENDIENTES en Drocerca -->
            <div v-if="drocercaPaidInErpPending.length > 0" class="mb-6">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-alert-circle" color="warning" size="22" />
                  <h4 class="text-subtitle-1 font-weight-bold text-warning mb-0">
                    Pagadas en el ERP pero aún PENDIENTES en Drocerca ({{ drocercaPaidInErpPending.length }})
                  </h4>
                </div>

                <VBtn
                  color="warning"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-arrow-back-up"
                  class="rounded-lg shadow-sm font-weight-bold"
                  :loading="isMarkingPending"
                  @click="handleMarkAllDrocercaPaidAsPending"
                >
                  Pasar a Por Pagar ({{ drocercaPaidInErpPending.length }})
                </VBtn>
              </div>

              <p class="text-caption text-medium-emphasis mb-3">
                Estas facturas ya las registraste como pagadas en el ERP, pero en Drocerca todavía aparecen con saldo pendiente por cobrar:
              </p>

              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-right font-weight-bold">Monto ERP</th>
                    <th class="text-right font-weight-bold">Saldo Drocerca</th>
                    <th class="text-center font-weight-bold">Estado Portal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in drocercaPaidInErpPending" :key="item.id">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-right">{{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}</td>
                    <td class="text-right font-weight-bold text-error">
                      {{ Number(item.portal_amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="warning" variant="tonal" class="font-weight-medium">
                        Por Cobrar
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Caso 2: Pendientes en ERP pero ya LIQUIDADAS en Drocerca -->
            <div v-if="drocercaPendingPaid && drocercaPendingPaid.length > 0" class="mb-6">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-circle-check" color="info" size="22" />
                  <h4 class="text-subtitle-1 font-weight-bold text-info mb-0">
                    Pendientes en ERP pero LIQUIDADAS en Drocerca ({{ drocercaPendingPaid.length }})
                  </h4>
                </div>

                <VBtn
                  color="success"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-check-all"
                  class="rounded-lg shadow-sm font-weight-bold"
                  :loading="isMarkingPaid"
                  @click="handleMarkAllDrocercaPendingAsPaid"
                >
                  Marcar Todas como Pagadas ({{ drocercaPendingPaid.length }})
                </VBtn>
              </div>

              <p class="text-caption text-medium-emphasis mb-3">
                Estas facturas están registradas como pendientes en tu ERP pero ya no figuran en Efectos por Pagar de Drocerca (fueron liquidadas):
              </p>

              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-right font-weight-bold">Monto ERP</th>
                    <th class="text-center font-weight-bold">Estado ERP</th>
                    <th class="text-center font-weight-bold">Estado Drocerca</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in drocercaPendingPaid" :key="item.id">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-right font-weight-bold">
                      {{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="error" variant="tonal">Por Pagar</VChip>
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="success" variant="tonal">Liquidada en Drocerca</VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <div v-if="drocercaData.details && drocercaData.details.length > 0">
              <h4 class="text-subtitle-1 font-weight-bold mb-2">Facturas Procesadas desde Drocerca</h4>
              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-center font-weight-bold">Acción</th>
                    <th class="text-center font-weight-bold">Vencimiento</th>
                    <th class="text-center font-weight-bold">Indexada (FA$)</th>
                    <th class="text-right font-weight-bold">Total USD</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in drocercaData.details" :key="idx">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.action === 'created' ? 'success' : 'info'" variant="tonal">
                        {{ item.action === 'created' ? 'Nueva Creada' : 'Actualizada' }}
                      </VChip>
                    </td>
                    <td class="text-center">{{ item.exp_date || 'N/A' }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.is_indexed ? 'error' : 'secondary'" variant="tonal">
                        {{ item.is_indexed ? 'Sí' : 'No' }}
                      </VChip>
                    </td>
                    <td class="text-right font-weight-bold">${{ Number(item.total_usd || 0).toFixed(2) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <div v-if="(!drocercaData.details || drocercaData.details.length === 0) && (drocercaPaidInErpPending.length + drocercaPendingPaid.length === 0)" class="text-center py-6">
              <VIcon icon="tabler-check-circle" size="40" color="success" class="mb-2" />
              <p class="text-body-2 text-medium-emphasis mb-0">
                Sincronización con Drocerca ejecutada correctamente. Todas las cuentas están al día y no hay diferencias.
              </p>
            </div>
          </VWindowItem>

          <!-- ================= TAB MAFARTA ================= -->
          <VWindowItem value="mafarta">
            <VRow class="mb-4">
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="info" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Documentos en Portal</div>
                  <div class="text-h5 font-weight-bold">{{ mafartaData.total_extracted || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="success" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Actualizadas en ERP</div>
                  <div class="text-h5 font-weight-bold">{{ mafartaData.updated || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="primary" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Nuevas Creadas</div>
                  <div class="text-h5 font-weight-bold">{{ mafartaData.created || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard
                  variant="tonal"
                  :color="(mafartaPaidInErpPending.length + mafartaPendingPaid.length) > 0 ? 'warning' : 'success'"
                  class="pa-3 rounded-lg text-center"
                >
                  <div class="text-caption text-medium-emphasis">Diferencias Detectadas</div>
                  <div class="text-h5 font-weight-bold">
                    {{ (mafartaPaidInErpPending.length + mafartaPendingPaid.length) }}
                  </div>
                </VCard>
              </VCol>
            </VRow>

            <!-- Caso 1: Pagadas en ERP pero aún PENDIENTES en Cobeca / Mafarta -->
            <div v-if="mafartaPaidInErpPending.length > 0" class="mb-6">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-alert-circle" color="warning" size="22" />
                  <h4 class="text-subtitle-1 font-weight-bold text-warning mb-0">
                    Pagadas en el ERP pero aún PENDIENTES en Cobeca ({{ mafartaPaidInErpPending.length }})
                  </h4>
                </div>

                <VBtn
                  color="warning"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-arrow-back-up"
                  class="rounded-lg shadow-sm font-weight-bold"
                  :loading="isMarkingPending"
                  @click="handleMarkAllMafartaPaidAsPending"
                >
                  Pasar a Por Pagar ({{ mafartaPaidInErpPending.length }})
                </VBtn>
              </div>

              <p class="text-caption text-medium-emphasis mb-3">
                Estas facturas ya las registraste como pagadas en el ERP, pero en Cobeca / Mafarta todavía aparecen con saldo pendiente por cobrar:
              </p>

              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-right font-weight-bold">Monto ERP</th>
                    <th class="text-right font-weight-bold">Saldo Cobeca</th>
                    <th class="text-center font-weight-bold">Estado Portal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in mafartaPaidInErpPending" :key="item.id">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-right">{{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}</td>
                    <td class="text-right font-weight-bold text-error">
                      {{ Number(item.portal_amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="warning" variant="tonal" class="font-weight-medium">
                        Por Cobrar
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Caso 2: Facturas pendientes en ERP pero ya liquidadas en Cobeca / Mafarta -->
            <div v-if="mafartaPendingPaid && mafartaPendingPaid.length > 0" class="mb-6">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-circle-check" color="info" size="22" />
                  <h4 class="text-subtitle-1 font-weight-bold text-info mb-0">
                    Pendientes en ERP pero LIQUIDADAS en Cobeca ({{ mafartaPendingPaid.length }})
                  </h4>
                </div>

                <VBtn
                  color="success"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-check-all"
                  class="rounded-lg shadow-sm font-weight-bold"
                  :loading="isMarkingPaid"
                  @click="handleMarkAllMafartaPendingAsPaid"
                >
                  Marcar Todas como Pagadas ({{ mafartaPendingPaid.length }})
                </VBtn>
              </div>

              <p class="text-caption text-medium-emphasis mb-3">
                Estas facturas están registradas como pendientes en tu ERP pero ya no figuran en Cobeca (fueron liquidadas):
              </p>

              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-right font-weight-bold">Monto ERP</th>
                    <th class="text-center font-weight-bold">Estado ERP</th>
                    <th class="text-center font-weight-bold">Estado Cobeca</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in mafartaPendingPaid" :key="item.id">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-right font-weight-bold">
                      {{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="error" variant="tonal">Por Pagar</VChip>
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="success" variant="tonal">Liquidada en Cobeca</VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Tabla de Facturas Procesadas / Actualizadas de Mafarta -->
            <div v-if="mafartaData.details && mafartaData.details.length > 0">
              <h4 class="text-subtitle-1 font-weight-bold mb-2">Facturas Procesadas desde Cobeca / Mafarta</h4>
              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-center font-weight-bold">Acción</th>
                    <th class="text-center font-weight-bold">Vencimiento</th>
                    <th class="text-center font-weight-bold">Indexada</th>
                    <th class="text-right font-weight-bold">Total USD</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in mafartaData.details" :key="idx">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.action === 'created' ? 'success' : 'info'" variant="tonal">
                        {{ item.action === 'created' ? 'Nueva Creada' : 'Actualizada' }}
                      </VChip>
                    </td>
                    <td class="text-center">{{ item.exp_date || 'N/A' }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.is_indexed ? 'error' : 'secondary'" variant="tonal">
                        {{ item.is_indexed ? 'Sí' : 'No' }}
                      </VChip>
                    </td>
                    <td class="text-right font-weight-bold">${{ Number(item.total_usd || 0).toFixed(2) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <div v-if="(!mafartaData.details || mafartaData.details.length === 0) && (mafartaPaidInErpPending.length + mafartaPendingPaid.length === 0)" class="text-center py-6">
              <VIcon icon="tabler-check-circle" size="40" color="success" class="mb-2" />
              <p class="text-body-2 text-medium-emphasis mb-0">
                Sincronización con Cobeca / Mafarta ejecutada correctamente. No hay facturas pendientes de procesar.
              </p>
            </div>
          </VWindowItem>

          <!-- ================= TAB CRISTMEDICALS ================= -->
          <VWindowItem value="cristmedicals">
            <!-- Resumen Rápido Cristmedicals -->
            <VRow class="mb-4">
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="info" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Facturas en Portal</div>
                  <div class="text-h5 font-weight-bold">{{ cristmedicalsData.total_extracted || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="success" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Actualizadas en ERP</div>
                  <div class="text-h5 font-weight-bold">{{ cristmedicalsData.updated || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="primary" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Nuevas Creadas</div>
                  <div class="text-h5 font-weight-bold">{{ cristmedicalsData.created || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard
                  variant="tonal"
                  :color="(cristmedicalsPaidInErpPending.length + cristmedicalsPendingInErpPaid.length) > 0 ? 'warning' : 'success'"
                  class="pa-3 rounded-lg text-center"
                >
                  <div class="text-caption text-medium-emphasis">Diferencias Detectadas</div>
                  <div class="text-h5 font-weight-bold">
                    {{ (cristmedicalsPaidInErpPending.length + cristmedicalsPendingInErpPaid.length) }}
                  </div>
                </VCard>
              </VCol>
            </VRow>

            <!-- Caso 1: Pagadas en ERP pero aún PENDIENTES en Cristmedicals -->
            <div v-if="cristmedicalsPaidInErpPending.length > 0" class="mb-6">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-alert-circle" color="warning" size="22" />
                  <h4 class="text-subtitle-1 font-weight-bold text-warning mb-0">
                    Pagadas en el ERP pero aún PENDIENTES en Cristmedicals ({{ cristmedicalsPaidInErpPending.length }})
                  </h4>
                </div>

                <VBtn
                  color="warning"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-arrow-back-up"
                  class="rounded-lg shadow-sm font-weight-bold"
                  :loading="isMarkingPending"
                  @click="handleMarkAllCristmedicalsPaidAsPending"
                >
                  Pasar a Por Pagar ({{ cristmedicalsPaidInErpPending.length }})
                </VBtn>
              </div>

              <p class="text-caption text-medium-emphasis mb-3">
                Estas facturas ya las registraste como pagadas en el ERP, pero en Cristmedicals todavía aparecen con saldo pendiente por cobrar:
              </p>

              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-right font-weight-bold">Monto ERP</th>
                    <th class="text-right font-weight-bold">Saldo Bs (Portal)</th>
                    <th class="text-right font-weight-bold">Saldo USD</th>
                    <th class="text-center font-weight-bold">Estado Portal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in cristmedicalsPaidInErpPending" :key="item.id">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-right">{{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}</td>
                    <td class="text-right font-weight-bold text-error">
                      Bs. {{ Number(item.portal_amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                    </td>
                    <td class="text-right font-weight-bold text-error">
                      ${{ Number(item.portal_amount_usd || 0).toFixed(2) }}
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="warning" variant="tonal" class="font-weight-medium">
                        Por Cobrar
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Caso 2: Pendientes en ERP pero ya LIQUIDADAS en Cristmedicals -->
            <div v-if="cristmedicalsPendingInErpPaid && cristmedicalsPendingInErpPaid.length > 0" class="mb-6">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-circle-check" color="info" size="22" />
                  <h4 class="text-subtitle-1 font-weight-bold text-info mb-0">
                    Pendientes en ERP pero LIQUIDADAS en Cristmedicals ({{ cristmedicalsPendingInErpPaid.length }})
                  </h4>
                </div>

                <VBtn
                  color="success"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-check-all"
                  class="rounded-lg shadow-sm font-weight-bold"
                  :loading="isMarkingPaid"
                  @click="handleMarkAllCristmedicalsPendingAsPaid"
                >
                  Marcar Todas como Pagadas ({{ cristmedicalsPendingInErpPaid.length }})
                </VBtn>
              </div>

              <p class="text-caption text-medium-emphasis mb-3">
                Estas facturas figuran como pendientes en tu ERP, pero en Cristmedicals ya no aparecen en el estado de cuenta por cobrar (fueron liquidadas):
              </p>

              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-right font-weight-bold">Monto Registrado</th>
                    <th class="text-center font-weight-bold">Estado en ERP</th>
                    <th class="text-center font-weight-bold">Estado Cristmedicals</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in cristmedicalsPendingInErpPaid" :key="item.id">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-right">{{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}</td>
                    <td class="text-center">
                      <VChip size="x-small" color="error" variant="tonal">
                        Pendiente
                      </VChip>
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="success" variant="tonal" class="font-weight-medium">
                        Liquidada (Pagada)
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Tabla de Facturas Procesadas / Actualizadas de Cristmedicals -->
            <div v-if="cristmedicalsData.details && cristmedicalsData.details.length > 0">
              <h4 class="text-subtitle-1 font-weight-bold mb-2">Facturas Procesadas desde Cristmedicals</h4>
              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-center font-weight-bold">Acción</th>
                    <th class="text-center font-weight-bold">Vencimiento</th>
                    <th class="text-right font-weight-bold">Saldo Neto USD</th>
                    <th class="text-right font-weight-bold">Saldo con Desc. USD</th>
                    <th class="text-right font-weight-bold">Total Bs. (Real a Pagar)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in cristmedicalsData.details" :key="idx">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.action === 'created' ? 'success' : 'info'" variant="tonal">
                        {{ item.action === 'created' ? 'Nueva Creada' : 'Actualizada' }}
                      </VChip>
                    </td>
                    <td class="text-center">{{ item.exp_date || 'N/A' }}</td>
                    <td class="text-right font-weight-medium">${{ Number(item.total_usd || 0).toFixed(2) }}</td>
                    <td class="text-right font-weight-bold text-success">${{ Number(item.saldo_con_desc_usd || 0).toFixed(2) }}</td>
                    <td class="text-right font-weight-bold text-primary">Bs. {{ Number(item.total_bs || 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </div>
            <div v-else class="text-center py-6">
              <VIcon icon="tabler-check-circle" size="40" color="success" class="mb-2" />
              <p class="text-body-2 text-medium-emphasis mb-0">
                Sincronización con Cristmedicals ejecutada correctamente. No hay facturas pendientes de procesar.
              </p>
            </div>
          </VWindowItem>

          <!-- ================= TAB DROGUERIA MEGA (DROMEGA) ================= -->
          <VWindowItem value="dromega">
            <!-- Resumen Rápido Dromega -->
            <VRow class="mb-4">
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="info" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Facturas en Portal</div>
                  <div class="text-h5 font-weight-bold">{{ dromegaData.total_extracted || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="success" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Actualizadas en ERP</div>
                  <div class="text-h5 font-weight-bold">{{ dromegaData.updated || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="primary" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Nuevas Creadas</div>
                  <div class="text-h5 font-weight-bold">{{ dromegaData.created || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard
                  variant="tonal"
                  :color="hasDromegaDiscrepancies ? 'warning' : 'success'"
                  class="pa-3 rounded-lg text-center"
                >
                  <div class="text-caption text-medium-emphasis">Diferencias Detectadas</div>
                  <div class="text-h5 font-weight-bold">
                    {{ (dromegaPaidInErpPending.length + dromegaPendingInErpPaid.length) }}
                  </div>
                </VCard>
              </VCol>
            </VRow>

            <!-- Caso 1: Pagadas en ERP pero aún figuran PENDIENTES en Droguería Mega -->
            <div v-if="dromegaPaidInErpPending.length > 0" class="mb-6">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-alert-circle" color="warning" size="22" />
                  <h4 class="text-subtitle-1 font-weight-bold text-warning mb-0">
                    Pagadas en el ERP pero aún PENDIENTES en Droguería Mega ({{ dromegaPaidInErpPending.length }})
                  </h4>
                </div>

                <VBtn
                  color="warning"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-arrow-back-up"
                  class="rounded-lg shadow-sm font-weight-bold"
                  :loading="isMarkingPending"
                  @click="handleMarkAllDromegaPaidAsPending"
                >
                  Pasar a Por Pagar ({{ dromegaPaidInErpPending.length }})
                </VBtn>
              </div>

              <p class="text-caption text-medium-emphasis mb-3">
                Estas facturas ya las registraste como pagadas en el ERP, pero en el estado de cuenta de Droguería Mega todavía aparecen con saldo pendiente por cobrar:
              </p>

              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-right font-weight-bold">Monto ERP</th>
                    <th class="text-right font-weight-bold">Saldo Bs (Dromega)</th>
                    <th class="text-right font-weight-bold">Saldo USD (Dromega)</th>
                    <th class="text-center font-weight-bold">Estado Portal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in dromegaPaidInErpPending" :key="item.id">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-right">{{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}</td>
                    <td class="text-right font-weight-bold text-error">
                      Bs. {{ Number(item.portal_amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                    </td>
                    <td class="text-right font-weight-bold text-error">
                      ${{ Number(item.portal_amount_usd || 0).toFixed(2) }}
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="warning" variant="tonal" class="font-weight-medium">
                        Por Cobrar (Pendiente)
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Caso 2: Pendientes en ERP pero ya NO figuran en Droguería Mega (LIQUIDADAS) CON BOTÓN DE ACCIÓN -->
            <div v-if="dromegaPendingInErpPaid.length > 0" class="mb-6">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-circle-check" color="info" size="22" />
                  <h4 class="text-subtitle-1 font-weight-bold text-info mb-0">
                    Pendientes en ERP pero LIQUIDADAS en Droguería Mega ({{ dromegaPendingInErpPaid.length }})
                  </h4>
                </div>

                <VBtn
                  color="success"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-check-all"
                  class="rounded-lg shadow-sm font-weight-bold"
                  :loading="isMarkingPaid"
                  @click="handleMarkAllDromegaPendingAsPaid"
                >
                  Marcar Todas como Pagadas ({{ dromegaPendingInErpPaid.length }})
                </VBtn>
              </div>

              <p class="text-caption text-medium-emphasis mb-3">
                Estas facturas figuran como pendientes en tu ERP, pero en Droguería Mega ya no aparecen en el estado de cuenta por cobrar (ya fueron pagadas / liquidadas):
              </p>

              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-right font-weight-bold">Monto Registrado</th>
                    <th class="text-center font-weight-bold">Estado en ERP</th>
                    <th class="text-center font-weight-bold">Estado en Dromega</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in dromegaPendingInErpPaid" :key="item.id">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-right">{{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}</td>
                    <td class="text-center">
                      <VChip size="x-small" color="error" variant="tonal">
                        Pendiente
                      </VChip>
                    </td>
                    <td class="text-center">
                      <VChip size="x-small" color="success" variant="tonal" class="font-weight-medium">
                        Liquidada (Pagada)
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Tabla de Facturas Procesadas / Actualizadas de Dromega -->
            <div v-if="dromegaData.details && dromegaData.details.length > 0">
              <h4 class="text-subtitle-1 font-weight-bold mb-2">Facturas Procesadas desde Droguería Mega</h4>
              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-center font-weight-bold">Acción</th>
                    <th class="text-center font-weight-bold">Fecha Pago</th>
                    <th class="text-center font-weight-bold">Vencimiento</th>
                    <th class="text-center font-weight-bold">Indexada</th>
                    <th class="text-right font-weight-bold">Saldo USD</th>
                    <th class="text-right font-weight-bold">Total Bs.</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in dromegaData.details" :key="idx">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.action === 'created' ? 'success' : 'info'" variant="tonal">
                        {{ item.action === 'created' ? 'Nueva Creada' : 'Actualizada' }}
                      </VChip>
                    </td>
                    <td class="text-center">{{ item.payment_date || 'N/A' }}</td>
                    <td class="text-center">{{ item.exp_date || 'N/A' }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.is_indexed ? 'warning' : 'secondary'" variant="tonal">
                        {{ item.is_indexed ? 'Indexada' : 'No Indexada' }}
                      </VChip>
                    </td>
                    <td class="text-right font-weight-medium">${{ Number(item.total_usd || 0).toFixed(2) }}</td>
                    <td class="text-right font-weight-bold text-primary">Bs. {{ Number(item.total_bs || 0).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </div>
            <div v-else class="text-center py-6">
              <VIcon icon="tabler-check-circle" size="40" color="success" class="mb-2" />
              <p class="text-body-2 text-medium-emphasis mb-0">
                Sincronización con Droguería Mega ejecutada correctamente.
              </p>
            </div>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-6 justify-end bg-surface">
        <VBtn color="primary" variant="flat" class="px-6 rounded-lg" @click="closeDialog">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.modal-card {
  max-height: 85vh;
  display: flex;
  flex-direction: column;
}

.modal-scroll-content {
  overflow-y: auto;
  max-height: calc(85vh - 180px);
}

.table-header-row th {
  background-color: rgba(var(--v-theme-on-surface), 0.04) !important;
  color: rgb(var(--v-theme-on-surface)) !important;
}
</style>
