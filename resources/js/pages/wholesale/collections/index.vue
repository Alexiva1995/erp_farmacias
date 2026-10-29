<script setup>
import { ref, onMounted } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';

// ─── Estados ───────────────────────────────────────────────────────────────────
const loading = ref(false);
const paymentsList = ref([]);
const searchQuery = ref('');

// Modales de Acción
const rejectDialog = ref(false);
const selectedPayment = ref(null);
const rejectionReason = ref('');
const processingAction = ref(false);

// ─── Carga de Pagos Pendientes ─────────────────────────────────────────────────
const fetchPendingPayments = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/wholesale/collections/pending-payments', {
      params: { search: searchQuery.value, status: 'pending_verification' },
    });
    paymentsList.value = response.data?.data?.data || [];
  } catch (error) {
    toast.error('Error al cargar pagos pendientes por conciliar');
  } finally {
    loading.value = false;
  }
};

// ─── Aprobación y Conciliación ─────────────────────────────────────────────────
const approvePayment = async (payment) => {
  processingAction.value = true;
  try {
    await axios.post(`/wholesale/collections/payments/${payment.id}/approve`);
    toast.success(`Pago #${payment.id} conciliado y aplicado con éxito.`);
    fetchPendingPayments();
  } catch (error) {
    const msg = error.response?.data?.message || 'Error al aprobar el pago';
    toast.error(msg);
  } finally {
    processingAction.value = false;
  }
};

// ─── Rechazo de Pago ──────────────────────────────────────────────────────────
const openRejectModal = (payment) => {
  selectedPayment.value = payment;
  rejectionReason.value = '';
  rejectDialog.value = true;
};

const confirmRejectPayment = async () => {
  if (!rejectionReason.value.trim()) {
    toast.warning('Debes ingresar el motivo del rechazo.');
    return;
  }

  processingAction.value = true;
  try {
    await axios.post(`/wholesale/collections/payments/${selectedPayment.value.id}/reject`, {
      reason: rejectionReason.value,
    });
    toast.success('Pago rechazado y notificado a la farmacia.');
    rejectDialog.value = false;
    fetchPendingPayments();
  } catch (error) {
    const msg = error.response?.data?.message || 'Error al rechazar el pago';
    toast.error(msg);
  } finally {
    processingAction.value = false;
  }
};

onMounted(() => {
  fetchPendingPayments();
});
</script>

<template>
  <div class="wholesale-collections">
    <v-card elevation="1">
      <v-card-title class="d-flex justify-space-between align-center">
        <span class="text-h6 font-weight-bold">Bandeja de Conciliación de Cobranzas B2B</span>
        <v-btn color="primary" variant="outlined" prepend-icon="mdi-refresh" @click="fetchPendingPayments">
          Actualizar
        </v-btn>
      </v-card-title>

      <v-card-text>
        <v-text-field
          v-model="searchQuery"
          label="Buscar por referencia bancaria o nombre de farmacia..."
          variant="outlined"
          density="compact"
          prepend-inner-icon="mdi-magnify"
          class="mb-3"
          clearable
          @keyup.enter="fetchPendingPayments"
        />

        <v-data-table
          :headers="[
            { title: 'Fecha', key: 'payment_date' },
            { title: 'Farmacia / Cliente', key: 'client.name' },
            { title: 'Origen', key: 'source_channel' },
            { title: 'Referencia / Banco', key: 'reference_number' },
            { title: 'Monto USD', key: 'amount_usd_equivalent' },
            { title: 'Retención IVA', key: 'withheld_iva_amount' },
            { title: 'Acciones', key: 'actions', sortable: false },
          ]"
          :items="paymentsList"
          :loading="loading"
          density="compact"
          hover
        >
          <template #item.payment_date="{ item }">
            {{ item.payment_date ? item.payment_date.substring(0, 10) : 'N/A' }}
          </template>

          <template #item.source_channel="{ item }">
            <v-chip size="x-small" :color="item.source_channel === 'erp_tenant_sync' ? 'success' : 'info'" variant="flat">
              {{ item.source_channel === 'erp_tenant_sync' ? 'Sync ERP ↔ ERP' : 'Marketplace B2B' }}
            </v-chip>
          </template>

          <template #item.reference_number="{ item }">
            <span class="font-weight-bold">{{ item.reference_number || 'S/R' }}</span>
            <div class="text-caption text-grey">{{ item.bank_name || 'Transferencia' }}</div>
          </template>

          <template #item.amount_usd_equivalent="{ item }">
            <span class="font-weight-bold text-success">${{ Number(item.amount_usd_equivalent).toFixed(2) }}</span>
          </template>

          <template #item.withheld_iva_amount="{ item }">
            <span v-if="item.withheld_iva_amount > 0" class="text-caption font-weight-bold text-primary">
              ${{ Number(item.withheld_iva_amount).toFixed(2) }} ({{ item.retention_voucher_number || 'VOUCHER' }})
            </span>
            <span v-else class="text-caption text-grey">0.00</span>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex ga-1">
              <v-btn
                size="small"
                color="success"
                variant="flat"
                prepend-icon="mdi-check"
                :loading="processingAction"
                @click="approvePayment(item)"
              >
                Aprobar
              </v-btn>
              <v-btn
                size="small"
                color="error"
                variant="outlined"
                prepend-icon="mdi-close"
                :loading="processingAction"
                @click="openRejectModal(item)"
              >
                Rechazar
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- Diálogo de Rechazo con Justificación -->
    <v-dialog v-model="rejectDialog" max-width="450px">
      <v-card>
        <v-card-title class="bg-error text-white">Rechazar Pago / Comprobante</v-card-title>
        <v-card-text class="pa-4">
          <p class="text-caption mb-2">Ingresa el motivo exacto del rechazo para notificar a la farmacia:</p>
          <v-textarea
            v-model="rejectionReason"
            label="Motivo del Rechazo"
            placeholder="Ej. Referencia bancaria no encontrada en cuenta o monto no coincide..."
            variant="outlined"
            density="compact"
            rows="3"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="rejectDialog = false">Cancelar</v-btn>
          <v-btn color="error" variant="flat" :loading="processingAction" @click="confirmRejectPayment">
            Confirmar Rechazo
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
