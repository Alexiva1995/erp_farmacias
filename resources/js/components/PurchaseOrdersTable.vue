<script setup>
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
]);

const headers = [
  { title: "Id", key: "id", sortable: false, width: "80px" },
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
  <VDataTableServer
    v-model:items-per-page="props.itemsPerPage"
    v-model:page="props.page"
    :headers="headers"
    :items="props.purchaseOrders"
    :items-length="props.totalPurchaseOrders"
    :loading="props.loading"
    @update:options="emit('update:options', $event)"
    hover
    class="premium-po-table"
  >
    <!-- ID de Orden -->
    <template #item.id="{ item }">
      <div class="d-flex align-center">
        <VAvatar
          size="36"
          variant="tonal"
          color="primary"
          class="font-weight-bold"
        >
          <span class="text-caption">#{{ item.id }}</span>
        </VAvatar>
      </div>
    </template>

    <!-- Proveedor -->
    <template #item.supplier_name="{ item }">
      <div class="d-flex flex-column py-2">
        <span class="text-body-2 font-weight-bold text-high-emphasis">
          {{ item.supplier_name }}
        </span>
        <div v-if="item.phone" class="d-flex align-center text-caption text-medium-emphasis mt-1">
          <VIcon icon="tabler-phone" size="12" class="me-1 text-primary" />
          {{ item.phone }}
        </div>
      </div>
    </template>

    <!-- Unidades -->
    <template #item.total_quantity="{ item }">
      <VChip size="small" variant="flat" color="secondary" class="font-weight-medium">
        {{ item.total_quantity }} u.
      </VChip>
    </template>

    <!-- Monto Total -->
    <template #item.total_amount="{ item }">
      <div class="d-flex flex-column">
        <span class="font-weight-bold text-primary text-body-2">
          $ {{ Number(item.total_amount).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
        </span>
      </div>
    </template>

    <!-- Estado -->
    <template #item.status="{ item }">
      <VChip
        :color="getStatusColor(item.status)"
        size="x-small"
        label
        variant="tonal"
        class="font-weight-bold"
      >
        {{ getStatusLabel(item.status) }}
      </VChip>
    </template>

    <!-- Fecha -->
    <template #item.order_date="{ item }">
      <div class="d-flex flex-column">
        <span class="text-body-2 font-medium">
          {{ formatDate(item.order_date) }}
        </span>
        <span v-if="formatTime(item.order_date)" class="text-xxs text-medium-emphasis">
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
            size="16" 
            color="success" 
          />
          <span class="text-body-2">{{ formatDate(item.tentative_delivery_date) }}</span>
       </div>
    </template>

    <!-- Acciones -->
    <template #item.actions="{ item }">
      <div class="d-flex align-center justify-center gap-2">
        <VTooltip text="Gestionar Orden" location="top">
          <template #activator="{ props: tooltipProps }">
            <VBtn
              v-bind="tooltipProps"
              icon
              size="x-small"
              variant="flat"
              color="primary"
              class="rounded-lg shadow-sm"
              @click="emit('manage', item)"
            >
              <VIcon icon="tabler-settings" size="18" />
            </VBtn>
          </template>
        </VTooltip>

        <VTooltip v-if="isAdmin" text="Eliminar Orden" location="top">
          <template #activator="{ props: tooltipProps }">
            <VBtn
              v-bind="tooltipProps"
              icon
              size="x-small"
              variant="tonal"
              color="error"
              class="rounded-lg"
              @click="emit('delete-purchaseOrder', item.id)"
            >
              <VIcon icon="tabler-trash" size="18" />
            </VBtn>
          </template>
        </VTooltip>

        <VTooltip text="WhatsApp" location="top">
          <template #activator="{ props: tooltipProps }">
            <VBtn
              v-bind="tooltipProps"
              icon
              size="x-small"
              variant="tonal"
              color="success"
              class="rounded-lg"
              :disabled="!item.phone"
              :href="item.phone ? `https://wa.me/${item.phone.replace(/\D/g, '')}?text=Hola, envío orden de compra #${item.id}` : undefined"
              target="_blank"
            >
              <VIcon icon="tabler-brand-whatsapp" size="18" />
            </VBtn>
          </template>
        </VTooltip>
      </div>
    </template>
  </VDataTableServer>
</template>

<style scoped>
.premium-po-table {
  border-radius: 0 !important;
}

.text-xxs {
  font-size: 0.7rem;
}

:deep(.v-data-table__th) {
  background-color: rgb(var(--v-theme-surface)) !important;
  color: rgb(var(--v-theme-on-surface), 0.6) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

.gap-2 {
  gap: 8px;
}
</style>
