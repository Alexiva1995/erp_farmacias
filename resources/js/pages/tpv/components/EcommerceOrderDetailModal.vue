<script setup>
import { formatDateSimple } from "@/utils/formatters";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  order: {
    type: Object,
    default: null,
  },
  processingAction: {
    type: Boolean,
    default: false,
  },
  getStatusColor: {
    type: Function,
    required: true,
  },
  getStatusLabel: {
    type: Function,
    required: true,
  },
  getPaymentLabel: {
    type: Function,
    required: true,
  },
});

const emit = defineEmits([
  "update:modelValue",
  "approve",
  "ship",
  "complete",
  "cancel",
]);

const closeDialog = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="580"
    :scrollable="false"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard v-if="order" style="border-radius: 8px; overflow: hidden;">
      <!-- Cabecera -->
      <div class="header-gradient pa-3 d-flex align-center text-white">
        <VIcon icon="tabler-file-invoice" size="18" class="me-2" />
        <div>
          <div class="text-subtitle-2 font-weight-black text-white">Pedido #{{ order.id }}</div>
          <div class="text-caption text-white opacity-70" style="font-size: 10px;">
            {{ formatDateSimple(order.created_at) }}
          </div>
        </div>
        <VSpacer />
        <VChip
          :color="getStatusColor(order.status)"
          size="x-small"
          variant="flat"
          class="font-weight-black me-2"
        >
          {{ getStatusLabel(order.status) }}
        </VChip>
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="white"
          size="x-small"
          @click="closeDialog"
        />
      </div>

      <VCardText class="pa-4" style="overflow: hidden;">
        <!-- Datos del Cliente: grid 2 columnas -->
        <div class="section-label mb-1">Datos del Cliente</div>
        <div class="info-grid mb-3">
          <div class="info-cell">
            <span class="info-key">Nombre</span>
            <span class="info-val font-weight-bold">{{ order.customer_name }}</span>
          </div>
          <div class="info-cell">
            <span class="info-key">Documento</span>
            <span class="info-val">
              {{ (order.customer_document_type || '') + (order.customer_document_number || '—') }}
            </span>
          </div>
          <div class="info-cell">
            <span class="info-key">Teléfono</span>
            <span class="info-val">{{ order.customer_phone || '—' }}</span>
          </div>
          <div class="info-cell">
            <span class="info-key">Correo</span>
            <span class="info-val" style="word-break: break-all;">{{ order.customer_email || '—' }}</span>
          </div>
          <div class="info-cell">
            <span class="info-key">Método de Pago</span>
            <span class="info-val font-weight-bold text-primary">
              {{ getPaymentLabel(order.payment_method) }}
            </span>
          </div>
          <div class="info-cell">
            <span class="info-key">Dirección de Entrega</span>
            <span class="info-val">{{ order.shipping_address || 'Retiro en local' }}</span>
          </div>
        </div>

        <!-- Ítems del pedido -->
        <div class="section-label mb-1">Ítems del Pedido</div>
        <table class="items-table mb-2">
          <thead>
            <tr>
              <th class="text-left">Producto</th>
              <th class="text-center" style="width: 50px;">Cant.</th>
              <th class="text-right" style="width: 90px;">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.items" :key="item.id">
              <td>
                <div class="font-weight-medium" style="font-size: 12px;">{{ item.product_name }}</div>
                <div v-if="item.variant_value" style="font-size: 10px; color: #999;">{{ item.variant_value }}</div>
              </td>
              <td class="text-center" style="font-size: 12px;">{{ item.quantity }}</td>
              <td class="text-right font-weight-bold" style="font-size: 12px;">
                {{ Number(item.price * item.quantity).toFixed(2) }}
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Total -->
        <div
          class="d-flex justify-space-between align-center px-2 py-2"
          style="background: rgba(var(--v-theme-primary), 0.06); border-radius: 6px;"
        >
          <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
            Total General
          </span>
          <span class="text-primary font-weight-black" style="font-size: 18px;">
            {{ Number(order.total_amount).toFixed(2) }}
          </span>
        </div>

        <!-- Notas -->
        <div
          v-if="order.notes"
          class="mt-2 pa-2"
          style="background: #f9f9f9; border-left: 3px solid #ddd; font-size: 11px; color: #666; font-style: italic;"
        >
          "📋 {{ order.notes }}"
        </div>
      </VCardText>

      <!-- Acciones -->
      <VDivider />
      <VCardActions class="pa-3 gap-2">
        <VBtn
          v-if="order.status === 'Pending'"
          color="primary"
          variant="flat"
          height="38"
          class="font-weight-black flex-grow-1"
          :loading="processingAction"
          :disabled="processingAction"
          @click="emit('approve', order.id)"
        >
          <VIcon icon="tabler-discount-check" size="16" class="me-1" /> Aprobar
        </VBtn>

        <VBtn
          v-if="order.status === 'Paid'"
          color="info"
          variant="flat"
          height="38"
          class="font-weight-black flex-grow-1"
          :loading="processingAction"
          :disabled="processingAction"
          @click="emit('ship', order.id)"
        >
          <VIcon icon="tabler-truck" size="16" class="me-1" /> Marcar Enviado
        </VBtn>

        <VBtn
          v-if="order.status === 'Shipped'"
          color="success"
          variant="flat"
          height="38"
          class="font-weight-black flex-grow-1"
          :loading="processingAction"
          :disabled="processingAction"
          @click="emit('complete', order.id)"
        >
          <VIcon icon="tabler-circle-check" size="16" class="me-1" /> Completar Venta
        </VBtn>

        <VBtn
          v-if="order.status !== 'Completed' && order.status !== 'Cancelled'"
          color="error"
          variant="tonal"
          height="38"
          class="font-weight-black flex-grow-1"
          :loading="processingAction"
          :disabled="processingAction"
          @click="emit('cancel', order.id)"
        >
          <VIcon icon="tabler-circle-x" size="16" class="me-1" /> Cancelar
        </VBtn>

        <VBtn
          v-if="order.status === 'Completed' || order.status === 'Cancelled'"
          color="secondary"
          variant="tonal"
          height="38"
          class="font-weight-black flex-grow-1"
          @click="closeDialog"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.section-label {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.5);
  border-left: 3px solid rgb(var(--v-theme-primary));
  padding-left: 8px;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px 12px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 6px;
  padding: 10px;
}

.info-cell {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.info-key {
  font-size: 10px;
  color: rgba(var(--v-theme-on-surface), 0.45);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-val {
  font-size: 13px;
  color: rgba(var(--v-theme-on-surface), 0.87);
}

.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}
.items-table th {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.45);
  padding: 4px 6px;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.15);
}
.items-table td {
  padding: 5px 6px;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.07);
  vertical-align: middle;
}
.gap-2 { gap: 8px !important; }
</style>
