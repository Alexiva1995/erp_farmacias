<script setup lang="js">
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, computed } from "vue";
import { useRoute } from "vue-router";

const route = useRoute();
const hash = route.params.hash;

const loading = ref(true);
const submitting = ref(false);
const submitted = ref(false);
const order = ref(null);
const items = ref([]);

// Diálogo para rechazar ítem específico
const rejectDialog = ref(false);
const activeItemToReject = ref(null);
const rejectionReason = ref("Sin stock");
const customReason = ref("");

const rejectionReasons = [
  { title: "Sin stock / Agotado", value: "Sin stock" },
  { title: "Producto Descontinuado", value: "Descontinuado" },
  { title: "Precio desactualizado", value: "Precio desactualizado" },
  { title: "Otro motivo (especificar)", value: "Otro" },
];

async function loadOrder() {
  loading.value = true;
  try {
    const { data } = await axios.get(`/public/orders/${hash}`);
    order.value = data;
    // Mapear ítems agregando estado de confirmación inicial
    items.value = data.items.map(item => ({
      ...item,
      // Si ya se había respondido antes, conservar la selección. De lo contrario, iniciar como aceptado
      supplier_confirmed: item.supplier_confirmed !== null ? item.supplier_confirmed : true,
      supplier_rejected_reason: item.supplier_rejected_reason || "",
    }));
  } catch (error) {
    console.error("Error al cargar la orden:", error);
    toast.error("La orden no existe o el enlace ha caducado.");
  } finally {
    loading.value = false;
  }
}

function selectAccept(item) {
  item.supplier_confirmed = true;
  item.supplier_rejected_reason = "";
}

function openRejectDialog(item) {
  activeItemToReject.value = item;
  rejectionReason.value = "Sin stock";
  customReason.value = "";
  rejectDialog.value = true;
}

function confirmReject() {
  if (!activeItemToReject.value) return;

  const reasonText = rejectionReason.value === "Otro" ? customReason.value : rejectionReason.value;
  activeItemToReject.value.supplier_confirmed = false;
  activeItemToReject.value.supplier_rejected_reason = reasonText || "Sin stock";

  rejectDialog.value = false;
  activeItemToReject.value = null;
}

const totalConfirmedAmount = computed(() => {
  return items.value
    .filter(i => i.supplier_confirmed)
    .reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
});

const confirmedCount = computed(() => {
  return items.value.filter(i => i.supplier_confirmed).length;
});

async function submitResponse() {
  const { isConfirmed } = await Swal.fire({
    title: "¿Enviar respuesta?",
    text: "Se registrará la confirmación de la orden en el sistema de la farmacia.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, enviar",
    cancelButtonText: "Cancelar",
  });

  if (!isConfirmed) return;

  submitting.value = true;
  try {
    const payload = {
      items: items.value.map(item => ({
        id: item.id,
        supplier_confirmed: item.supplier_confirmed,
        supplier_rejected_reason: item.supplier_confirmed ? null : item.supplier_rejected_reason,
      })),
    };

    await axios.post(`/public/orders/${hash}/respond`, payload);
    submitted.value = true;
    toast.success("Respuesta enviada correctamente. ¡Muchas gracias!");
  } catch (error) {
    console.error("Error al enviar la respuesta:", error);
    toast.error("Error al enviar la confirmación.");
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  if (hash) {
    loadOrder();
  }
});
</script>

<template>
  <div class="public-confirmation-layout d-flex align-center justify-center min-h-screen py-12 px-4">
    <VCard v-if="!loading && order" max-width="850px" width="100%" class="shadow-xl rounded-lg overflow-hidden border">
      <!-- Encabezado Principal -->
      <div class="bg-primary text-white pa-6 text-center text-md-left d-flex flex-column flex-md-row justify-space-between align-center ga-4">
        <div>
          <h2 class="text-h5 font-weight-black mb-1">Confirmación de Orden de Compra</h2>
          <p class="text-subtitle-2 mb-0 opacity-80">Por favor, confirme disponibilidad y costos de los productos solicitados.</p>
        </div>
        <div class="text-center text-md-right">
          <div class="text-h6 font-weight-black">Orden #{{ order.id }}</div>
          <div class="text-xs opacity-75">Fecha: {{ new Date(order.order_date).toLocaleDateString() }}</div>
        </div>
      </div>

      <!-- Cuerpo del Portal -->
      <VCardText class="pa-6" v-if="!submitted">
        <!-- Info del Proveedor y Cliente -->
        <VRow class="mb-6 bg-light pa-4 rounded-lg">
          <VCol cols="12" sm="6">
            <div class="text-overline text-primary font-weight-black">CLIENTE:</div>
            <div class="text-h6 font-weight-bold">FARMACIA BS</div>
            <div class="text-body-2 text-muted">erp.farmaciabs.com</div>
          </VCol>
          <VCol cols="12" sm="6" class="border-sm-left">
            <div class="text-overline text-primary font-weight-black">PROVEEDOR:</div>
            <div class="text-h6 font-weight-bold">{{ order.supplier?.name }}</div>
            <div class="text-body-2 text-muted">RIF: {{ order.supplier?.rif || '—' }}</div>
          </VCol>
        </VRow>

        <!-- Instrucciones -->
        <div class="mb-6 alert-box d-flex align-center ga-3 pa-3 rounded bg-light-info text-info border">
          <VIcon icon="tabler-info-circle" size="24" />
          <span class="text-sm font-weight-medium">Marque con ✅ los productos que despachará y con ❌ los que no tiene disponibles.</span>
        </div>

        <!-- Tabla de ítems para Confirmar -->
        <div class="table-container border rounded overflow-hidden mb-6">
          <VTable density="comfortable">
            <thead>
              <tr class="bg-light-header">
                <th>Producto / Catálogo</th>
                <th class="text-center">Cód Proveedor</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Costo (USD)</th>
                <th class="text-right">Subtotal</th>
                <th class="text-center" style="width: 130px;">Disponibilidad</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id" :class="{'row-rejected': !item.supplier_confirmed}">
                <td>
                  <div class="font-weight-bold">{{ item.name }}</div>
                  <div v-if="!item.supplier_confirmed" class="text-xs text-error font-weight-bold">
                    Rechazado: {{ item.supplier_rejected_reason }}
                  </div>
                </td>
                <td class="text-center font-mono text-sm">{{ item.cod_supplier }}</td>
                <td class="text-right font-weight-bold">{{ item.quantity }} u.</td>
                <td class="text-right font-mono">${{ parseFloat(item.unit_cost).toFixed(2) }}</td>
                <td class="text-right font-mono font-weight-bold">${{ parseFloat(item.subtotal).toFixed(2) }}</td>
                <td class="text-center">
                  <div class="d-flex ga-1 justify-center">
                    <VBtn
                      icon
                      size="32"
                      :variant="item.supplier_confirmed ? 'flat' : 'outlined'"
                      color="success"
                      title="Disponible"
                      @click="selectAccept(item)"
                    >
                      <VIcon icon="tabler-check" size="18" />
                    </VBtn>
                    <VBtn
                      icon
                      size="32"
                      :variant="!item.supplier_confirmed ? 'flat' : 'outlined'"
                      color="error"
                      title="No disponible"
                      @click="openRejectDialog(item)"
                    >
                      <VIcon icon="tabler-x" size="18" />
                    </VBtn>
                  </div>
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>

        <!-- Resumen de Confirmación -->
        <div class="d-flex flex-column align-end border-t pt-4">
          <div class="text-body-1 mb-1">
            Productos Confirmados: <strong class="text-success">{{ confirmedCount }} / {{ items.length }}</strong>
          </div>
          <div class="text-h5 font-weight-black text-primary">
            Total Confirmado: ${{ totalConfirmedAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} USD
          </div>
        </div>
      </VCardText>

      <!-- Botón de Envío -->
      <VCardActions class="pa-6 border-t bg-light justify-space-between" v-if="!submitted">
        <span class="text-xs text-muted">Al enviar, su respuesta se registrará inmediatamente en el ERP.</span>
        <VBtn
          color="primary"
          size="large"
          class="px-8 font-weight-bold shadow-sm"
          :loading="submitting"
          @click="submitResponse"
        >
          Enviar Respuesta al Cliente
        </VBtn>
      </VCardActions>

      <!-- Pantalla de Éxito Post-Envío -->
      <div v-else class="text-center py-16 px-6 fade-in">
        <div class="success-checkmark mb-6">
          <VIcon icon="tabler-circle-check" size="96" color="success" class="animate-bounce" />
        </div>
        <h3 class="text-h4 font-weight-black text-success mb-2">¡Respuesta Registrada!</h3>
        <p class="text-subtitle-1 text-muted max-w-md mx-auto mb-6">
          Hemos recibido su confirmación y los detalles del pedido han sido actualizados en nuestro sistema. Agradecemos su respuesta oportuna.
        </p>
        <VDivider class="my-6 max-w-sm mx-auto" />
        <div class="text-xs text-muted font-mono">ID de Transacción: {{ hash }}</div>
      </div>
    </VCard>

    <!-- Cargando -->
    <div v-else-if="loading" class="text-center py-12">
      <VProgressCircular indeterminate size="64" color="primary" class="mb-4" />
      <div class="text-body-1 font-weight-bold">Cargando orden de compra...</div>
    </div>

    <!-- Error -->
    <VCard v-else max-width="500px" width="100%" class="text-center pa-8 shadow-lg border rounded-lg">
      <VIcon icon="tabler-alert-triangle" size="64" color="error" class="mb-4" />
      <h3 class="text-h5 font-weight-black text-error mb-2">Enlace no Válido</h3>
      <p class="text-body-1 text-muted mb-0">
        Este enlace de confirmación ha caducado, es inválido o la orden ya ha sido cancelada en el ERP.
      </p>
    </VCard>

    <!-- Modal de Motivo de Rechazo -->
    <VDialog v-model="rejectDialog" max-width="450px" persistent>
      <VCard>
        <VCardTitle class="px-6 py-4 bg-error text-white font-weight-bold d-flex align-center ga-2">
          <VIcon icon="tabler-x" />
          Marcar Producto como No Disponible
        </VCardTitle>
        <VCardText class="pa-6">
          <p class="text-body-2 mb-4 font-weight-bold text-muted">
            Producto: {{ activeItemToReject?.name }}
          </p>
          <VSelect
            v-model="rejectionReason"
            :items="rejectionReasons"
            label="Motivo del rechazo"
            density="comfortable"
            class="mb-4"
          />
          <VTextarea
            v-if="rejectionReason === 'Otro'"
            v-model="customReason"
            label="Escriba el motivo detallado"
            placeholder="Especifique la razón..."
            rows="3"
            required
          />
        </VCardText>
        <VCardActions class="px-6 pb-6">
          <VSpacer />
          <VBtn variant="outlined" color="secondary" @click="rejectDialog = false">Cancelar</VBtn>
          <VBtn color="error" @click="confirmReject">Confirmar Rechazo</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.public-confirmation-layout {
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}
.font-mono {
  font-family: monospace;
}
.row-rejected {
  background-color: #fff5f5;
  color: #c92a2a;
}
.bg-light-header {
  background-color: #f1f3f5;
}
.bg-light-info {
  background-color: #e3faf2;
}
.text-info {
  color: #0ca678 !important;
}
.fade-in {
  animation: fadeIn 0.6s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-bounce {
  animation: bounce 2s infinite;
}
@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}
</style>
