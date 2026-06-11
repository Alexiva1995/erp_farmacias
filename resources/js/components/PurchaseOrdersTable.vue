<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  purchaseOrders: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalPurchaseOrders: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  isAdmin: { type: Boolean, required: true },
});

const emit = defineEmits([
  "update:options",
  "manage",
  "delete-purchaseOrder",
  "revert",
]);

const { mobile } = useDisplay();

const headers = [
  { title: "Id", key: "id", sortable: false, width: "100px" },
  { title: "Proveedor", key: "supplier_name", sortable: false },
  { title: "Unidades", key: "total_quantity", sortable: false, align: "center" },
  { title: "Monto Total", key: "total_amount", sortable: false },
  { title: "Estado", key: "status", sortable: false, align: "center" },
  { title: "Fecha Solicitud", key: "order_date", sortable: false },
  { title: "Entrega Est.", key: "tentative_delivery_date", sortable: false },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const getStatusLabel = (status) => {
  const labels = {
    0: "PENDIENTE",
    1: "ENVIADO",
    2: "COMPLETADO",
  };
  if (status === true || status === 2) return "COMPLETADO";
  if (status === 1) return "ENVIADO";
  return labels[status] || "PENDIENTE";
};

const getStatusColor = (status) => {
  const colors = {
    0: "warning",
    1: "info",
    2: "success",
  };
  if (status === true || status === 2) return "success";
  if (status === 1) return "info";
  return colors[status] || "warning";
};

const formatDate = (dateString) => {
  if (!dateString) return "No def.";
  const date = new Date(dateString);
  return date.toLocaleDateString("es-ES", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

const formatTime = (dateString) => {
  if (!dateString || dateString.length < 11) return "";
  const date = new Date(dateString);
  return date.toLocaleTimeString("es-ES", {
    hour: "2-digit",
    minute: "2-digit",
  });
};
</script>

<template>
  <div class="purchase-orders-container">
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.purchaseOrders"
      :items-length="props.totalPurchaseOrders"
      :loading="props.loading"
      @update:options="emit('update:options', $event)"
      hover
      class="premium-table rounded-lg border shadow-sm"
    >
      <!-- ID de Orden -->
      <template #item.id="{ item }">
        <span class="text-sm font-weight-black text-primary">{{ item.id }}</span>
      </template>

      <!-- Proveedor -->
      <template #item.supplier_name="{ item }">
        <div class="d-flex flex-column py-2">
          <span class="text-sm font-weight-black text-high-emphasis">
            {{ item.supplier_name }}
          </span>
          <div v-if="item.phone" class="d-flex align-center text-xs text-disabled mt-1">
            <VIcon icon="tabler-phone" size="14" class="me-1 text-primary" />
            {{ item.phone }}
          </div>
        </div>
      </template>

      <!-- Unidades -->
      <template #item.total_quantity="{ item }">
        <VChip size="small" variant="tonal" color="secondary" class="font-weight-black rounded-lg">
          {{ item.total_quantity }} u.
        </VChip>
      </template>

      <!-- Monto Total -->
      <template #item.total_amount="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-black text-primary text-sm">
            $ {{ Number(item.total_amount).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </span>
        </div>
      </template>

      <!-- Estado -->
      <template #item.status="{ item }">
        <VChip
          :color="getStatusColor(item.status)"
          size="x-small"
          variant="tonal"
          class="font-weight-black rounded-lg"
        >
          {{ getStatusLabel(item.status) }}
        </VChip>
      </template>

      <!-- Fecha -->
      <template #item.order_date="{ item }">
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-medium">
            {{ formatDate(item.order_date) }}
          </span>
          <span v-if="formatTime(item.order_date)" class="text-xxs text-disabled">
            {{ formatTime(item.order_date) }}
          </span>
        </div>
      </template>

      <!-- Fecha Entrega -->
      <template #item.tentative_delivery_date="{ item }">
         <div class="d-flex align-center gap-1">
            <VIcon 
              v-if="item.tentative_delivery_date" 
              icon="tabler-truck" 
              size="18" 
              color="success-opacity" 
            />
            <span class="text-sm font-weight-bold" :class="item.tentative_delivery_date ? 'text-success' : 'text-disabled'">
              {{ formatDate(item.tentative_delivery_date) }}
            </span>
         </div>
      </template>

      <!-- Acciones -->
      <template #item.actions="{ item }">
        <div class="d-flex align-center justify-center gap-2">
          <!-- Botón Devolver a Enviada (sólo si está COMPLETADA) -->
          <VBtn
            v-if="item.status === 2"
            icon
            size="32"
            variant="tonal"
            color="warning"
            class="rounded-circle shadow-sm"
            @click="emit('revert', item)"
          >
            <VIcon icon="tabler-arrow-back-up" size="18" />
            <VTooltip activator="parent" location="top">Devolver a Enviada</VTooltip>
          </VBtn>

          <VBtn
            icon
            size="32"
            variant="tonal"
            color="primary"
            class="rounded-circle shadow-sm"
            @click="emit('manage', item)"
          >
            <VIcon icon="tabler-settings" size="18" />
            <VTooltip activator="parent" location="top">Gestionar</VTooltip>
          </VBtn>

          <VBtn
            v-if="isAdmin"
            icon
            size="32"
            variant="tonal"
            color="error"
            class="rounded-circle shadow-sm"
            @click="emit('delete-purchaseOrder', item.id)"
          >
            <VIcon icon="tabler-trash" size="18" />
            <VTooltip activator="parent" location="top">Eliminar</VTooltip>
          </VBtn>

          <VBtn
            icon
            size="32"
            variant="tonal"
            color="success"
            class="rounded-circle shadow-sm"
            :disabled="!item.phone"
            :href="item.phone ? `https://wa.me/${item.phone.replace(/\D/g, '')}?text=Hola, envío orden de compra #${item.id}` : undefined"
            target="_blank"
          >
            <VIcon icon="tabler-brand-whatsapp" size="18" />
            <VTooltip activator="parent" location="top">WhatsApp</VTooltip>
          </VBtn>
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil (Cards) -->
    <div v-else class="mobile-cards-view d-flex flex-column gap-4 pa-4 pb-16">
      <template v-if="props.purchaseOrders.length > 0">
        <VCard
          v-for="item in props.purchaseOrders"
          :key="item.id"
          variant="flat"
          border
          class="rounded-lg overflow-hidden premium-card"
        >
          <div class="premium-card-header pa-4 bg-var-theme-background border-b position-relative">
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="d-flex align-center gap-2">
                <span class="text-sm font-weight-black text-primary">#{{ item.id }}</span>
                <div class="d-flex flex-column ms-1">
                  <span class="text-xxs text-disabled text-uppercase font-weight-black leading-none mb-1">Fecha Solicitud</span>
                  <span class="text-xs font-weight-black text-high-emphasis uppercase">{{ formatDate(item.order_date) }}</span>
                </div>
              </div>
              <VChip
                :color="getStatusColor(item.status)"
                size="x-small"
                label
                variant="flat"
                class="font-weight-black rounded"
              >
                {{ getStatusLabel(item.status) }}
              </VChip>
            </div>
            <div class="text-sm font-weight-black text-high-emphasis mt-3 uppercase truncate-2-lines leading-tight">
              {{ item.supplier_name }}
            </div>
            <div class="premium-card-decoration" :class="`bg-${getStatusColor(item.status)}-opacity`"></div>
          </div>

          <VCardText class="pa-4">
            <VRow no-gutters class="gap-y-4">
              <VCol cols="6">
                <span class="text-xxs text-disabled text-uppercase d-block mb-1 font-weight-black">Monto Total</span>
                <span class="text-sm font-weight-black text-primary">
                  $ {{ Number(item.total_amount).toLocaleString('es-ES', { minimumFractionDigits: 2 }) }}
                </span>
              </VCol>
              <VCol cols="6" class="text-right">
                <span class="text-xxs text-disabled text-uppercase d-block mb-1 font-weight-black">Unidades</span>
                <VChip size="x-small" variant="tonal" color="secondary" class="font-weight-black">
                  {{ item.total_quantity }} u.
                </VChip>
              </VCol>
              <VCol v-if="item.tentative_delivery_date" cols="12" class="mt-2 bg-success-opacity-2 pa-2 rounded-lg d-flex align-center gap-2">
                <VIcon icon="tabler-truck" size="14" color="success" />
                <span class="text-xs font-weight-bold text-success">Entrega: {{ formatDate(item.tentative_delivery_date) }}</span>
              </VCol>
            </VRow>

            <div class="d-flex align-center gap-2 mt-4">
              <!-- Botón Devolver a Enviada en móvil -->
              <VBtn
                v-if="item.status === 2"
                icon
                variant="tonal"
                color="warning"
                class="rounded-lg"
                size="32"
                @click="emit('revert', item)"
              >
                <VIcon icon="tabler-arrow-back-up" size="18" />
              </VBtn>

              <VBtn
                color="primary"
                variant="tonal"
                class="flex-grow-1 font-weight-black rounded-lg"
                size="small"
                @click="emit('manage', item)"
              >
                GESTIONAR
              </VBtn>
              
              <VBtn
                icon
                variant="tonal"
                color="success"
                class="rounded-lg"
                size="32"
                :disabled="!item.phone"
                :href="item.phone ? `https://wa.me/${item.phone.replace(/\D/g, '')}?text=Hola, envío orden de compra #${item.id}` : undefined"
                target="_blank"
              >
                <VIcon icon="tabler-brand-whatsapp" size="18" />
              </VBtn>

              <VBtn
                v-if="isAdmin"
                icon
                variant="tonal"
                color="error"
                class="rounded-lg"
                size="32"
                @click="emit('delete-purchaseOrder', item.id)"
              >
                <VIcon icon="tabler-trash" size="18" />
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </template>

      <div v-else-if="!props.loading" class="text-center py-10 opacity-50">
        <VIcon icon="tabler-clipboard-off" size="48" class="mb-2" />
        <div class="text-xs font-weight-black uppercase">No hay órdenes de compra</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: white !important;
  block-size: 52px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.premium-table :deep(td) {
  padding-block: 12px !important;
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.1) !important;
}
.text-disabled { color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity)) !important; }

.premium-card {
  transition: all 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.premium-card-decoration {
  position: absolute;
  border-radius: 0 0 0 100%;
  block-size: 60px;
  inline-size: 60px;
  inset-block-start: 0;
  inset-inline-end: 0;
  opacity: 0.1;
  pointer-events: none;
}

.bg-success-opacity { background: linear-gradient(135deg, rgb(var(--v-theme-success)) 0%, transparent 100%); }
.bg-info-opacity { background: linear-gradient(135deg, rgb(var(--v-theme-info)) 0%, transparent 100%); }
.bg-warning-opacity { background: linear-gradient(135deg, rgb(var(--v-theme-warning)) 0%, transparent 100%); }

.bg-success-opacity-2 { background-color: rgba(var(--v-theme-success), 0.05) !important; }

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

