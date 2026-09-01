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
const dronenaData = computed(() => props.syncSummary?.dronena || {});

const drocercaPendingPaid = computed(
  () => drocercaData.value?.discrepancies?.pending_in_erp_paid_in_drocerca || []
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
                Resumen de Dronena, Drocerca y Cobeca / Mafarta
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
              v-if="(drocercaData.created || 0) > 0"
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
                  <div class="text-caption text-medium-emphasis">Nuevas Creadas</div>
                  <div class="text-h5 font-weight-bold">{{ drocercaData.created || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="primary" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Actualizadas</div>
                  <div class="text-h5 font-weight-bold">{{ drocercaData.updated || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="3">
                <VCard variant="tonal" color="secondary" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Sin Cambios / Omitidas</div>
                  <div class="text-h5 font-weight-bold">{{ drocercaData.skipped || 0 }}</div>
                </VCard>
              </VCol>
            </VRow>

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

            <!-- Facturas pendientes en ERP pero ya liquidadas en Drocerca -->
            <div v-if="drocercaPendingPaid && drocercaPendingPaid.length > 0" class="mt-4 mb-6">
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
          </VWindowItem>

          <!-- ================= TAB MAFARTA ================= -->
          <VWindowItem value="mafarta">
            <VRow class="mb-4">
              <VCol cols="12" sm="4">
                <VCard variant="tonal" color="info" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Documentos en Portal</div>
                  <div class="text-h5 font-weight-bold">{{ mafartaData.total_extracted || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="4">
                <VCard variant="tonal" color="success" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Actualizadas en ERP</div>
                  <div class="text-h5 font-weight-bold">{{ mafartaData.updated || 0 }}</div>
                </VCard>
              </VCol>
              <VCol cols="12" sm="4">
                <VCard variant="tonal" color="secondary" class="pa-3 rounded-lg text-center">
                  <div class="text-caption text-medium-emphasis">Omitidas / No en ERP</div>
                  <div class="text-h5 font-weight-bold">{{ mafartaData.skipped || 0 }}</div>
                </VCard>
              </VCol>
            </VRow>

            <div v-if="mafartaData.details && mafartaData.details.length > 0">
              <h4 class="text-subtitle-1 font-weight-bold mb-2">Facturas Cobeca / Mafarta</h4>
              <VTable density="compact" class="border rounded-lg mb-2">
                <thead>
                  <tr class="table-header-row">
                    <th class="text-left font-weight-bold">N° Factura</th>
                    <th class="text-left font-weight-bold">N° Control</th>
                    <th class="text-center font-weight-bold">Acción</th>
                    <th class="text-center font-weight-bold">Vencimiento</th>
                    <th class="text-center font-weight-bold">Indexación</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in mafartaData.details" :key="idx">
                    <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                    <td>{{ item.control_number || 'N/A' }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.action === 'updated' ? 'success' : 'secondary'" variant="tonal">
                        {{ item.action === 'updated' ? 'Actualizada' : 'No registrada' }}
                      </VChip>
                    </td>
                    <td class="text-center">{{ item.exp_date || 'N/A' }}</td>
                    <td class="text-center">
                      <VChip size="x-small" :color="item.is_indexed ? 'error' : 'secondary'" variant="tonal">
                        {{ item.is_indexed ? 'Indexada' : 'No indexada' }}
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
            <div v-else class="text-center py-6">
              <VIcon icon="tabler-check-circle" size="40" color="success" class="mb-2" />
              <p class="text-body-2 text-medium-emphasis mb-0">
                Sincronización con Cobeca / Mafarta ejecutada correctamente.
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
