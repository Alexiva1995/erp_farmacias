<script setup>
import { useDisplay } from "vuetify";
import Swal from "sweetalert2";
import { ref, computed } from "vue";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  orders: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOrders: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  isBlind: { type: Boolean, default: false },
});

const emit = defineEmits(["update:options", "view-order", "cancelar-order"]);
const { mobile } = useDisplay();
const authStore = useAuthStore();

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  const date = new Date(dateStr);
  return date.toLocaleDateString("es-ES", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};

const expandedRows = ref([]);

const toggleExpand = (itemId) => {
  const index = expandedRows.value.indexOf(itemId);
  if (index === -1) {
    expandedRows.value.push(itemId);
  } else {
    expandedRows.value.splice(index, 1);
  }
};

const paymentMethodLabels = {
  cash_cop: "Efectivo",
  bank_transfer: "Transferencia",
  cash_bs: "Efectivo",
  mobile_payment: "Pago Móvil",
  bank_transfer_bs: "Transferencia",
  card: "Tarjeta",
  cash_usd: "Efectivo",
  binance: "Binance",
  paypal: "PayPal",
  credit: "Crédito",
};

const getPaymentMethodLabel = (methodValue) => {
  return paymentMethodLabels[methodValue] || methodValue;
};

const headers = computed(() => {
  const baseHeaders = [
    { title: "ID", key: "id", sortable: true },
    { title: "Identificación", key: "identification", sortable: true },
    { title: "Cliente", key: "client_full_name", sortable: true },
  ];
  if (!props.isBlind) {
    baseHeaders.push(
      { title: "Monto", key: "total_amount", sortable: true },
      { title: "Moneda", key: "currency", sortable: true }
    );
  }
  baseHeaders.push(
    { title: "Fecha", key: "date", sortable: true },
    { title: "Acciones", key: "actions", sortable: false, align: "end" }
  );
  return baseHeaders;
});

const handleView = (orderId) => {
  emit("view-order", orderId);
};

const handleCancelled = (orderId) => {
  Swal.fire({
    title: "¿Estás seguro?",
    text: "¡Desea Cancelar la Orden!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
  }).then(async (result) => {
    if (result.isConfirmed) {
      emit("cancelar-order", orderId);
    }
  });
};

const normalizeMethods = (data) => {
  if (!data) return [];
  if (Array.isArray(data)) return data;
  if (typeof data === "object" && data.legacy && typeof data.legacy === "string") {
    try {
      const cleanedString = data.legacy.replace(/\\"/g, '"');
      const parsedArray = JSON.parse(cleanedString);
      if (Array.isArray(parsedArray)) return parsedArray;
    } catch (e) {
      console.error("Error normalizando pagos legacy:", e);
      return [];
    }
  }
  return [];
};

const hasCreditPayment = (rawData) => {
  const methods = normalizeMethods(rawData);
  if (methods.length === 0) return false;
  return methods.some((p) => {
    if (!p) return false;
    const isCreditNew = p.method === "credit";
    const isCreditOld = p.metodoPago === "credit";
    return isCreditNew || isCreditOld;
  });
};
</script>

<template>
  <div class="orders-table-container">
    <!-- Vista de Escritorio (Tabla) -->
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.orders"
      :items-length="props.totalOrders"
      :loading="props.loading"
      class="premium-table"
      @update:options="(options) => emit('update:options', options)"
      :expanded="expandedRows"
    >
      <template v-slot:item.id="{ item }">
        <span class="text-sm font-weight-black text-primary">{{ item.id }}</span>
      </template>

      <template v-slot:item.identification="{ item }">
        <span class="text-sm">{{ item.client?.identification_type }} {{ item.client?.identification }}</span>
      </template>

      <template v-slot:item.client_full_name="{ item }">
        <span class="text-sm font-weight-medium">{{ item.client?.name }} {{ item.client?.last_name }}</span>
      </template>

      <template v-slot:item.total_amount="{ item }">
        <div class="d-flex align-center gap-2">
          <span class="text-sm font-weight-bold">{{ item.total_amount }}</span>
          <VBtn
            v-if="item.has_multiple_currencies"
            icon="tabler-info-circle"
            variant="text"
            size="24"
            color="secondary"
            @click="toggleExpand(item.id)"
          />
        </div>
      </template>

      <template v-slot:item.currency="{ item }">
        <VChip
          :color="hasCreditPayment(item.payment_methods) ? 'error' : 'primary'"
          variant="tonal"
          size="x-small"
          class="font-weight-bold"
        >
          {{ item.currency }}{{ hasCreditPayment(item.payment_methods) ? '*' : '' }}
        </VChip>
      </template>

      <template #item.date="{ item }">
        <span class="text-sm">{{ formatDate(item.order_date) }}</span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-2">
          <VBtn
            icon="tabler-eye"
            variant="tonal"
            color="info"
            size="32"
            class="rounded-lg"
            @click="handleView(item.id)"
          />
          <VBtn
            v-if="authStore.user?.role_id === 1 || authStore.user?.role_id === 2"
            icon="tabler-trash"
            variant="tonal"
            color="error"
            size="32"
            class="rounded-lg"
            @click="handleCancelled(item.id)"
          />
        </div>
      </template>

      <template v-slot:expanded-row="{ columns, item }">
        <tr>
          <td :colspan="columns.length" class="bg-surface-variant overflow-hidden">
            <div class="pa-4 bg-light-primary rounded-lg mx-4 my-2 border-dashed">
              <h4 class="text-xs font-weight-black uppercase text-disabled mb-3">Desglose Multimoneda</h4>
              <div class="d-flex flex-wrap gap-4">
                <div v-for="(payment, index) in item.payment_methods" :key="index" class="d-flex flex-column">
                  <span class="text-xs text-medium-emphasis uppercase">{{ getPaymentMethodLabel(payment.method) }}</span>
                  <span class="text-body-2 font-weight-bold">{{ payment.currency }} {{ payment.amount }}</span>
                </div>
              </div>
            </div>
          </td>
        </tr>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil (Cards) -->
    <div v-else class="mobile-cards-container pa-4">
      <VRow>
        <VCol v-for="item in props.orders" :key="item.id" cols="12">
          <VCard class="rounded-lg border shadow-sm order-card" variant="flat">
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-start mb-3">
                <div class="d-flex align-center gap-3">
                  <VAvatar color="primary" variant="tonal" size="40" class="rounded-lg">
                    <VIcon icon="tabler-shopping-cart" size="20" />
                  </VAvatar>
                  <div>
                    <div class="text-lg font-weight-black text-primary leading-none">{{ item.id }}</div>
                    <div class="text-caption text-disabled font-weight-medium uppercase mt-1">
                      {{ formatDate(item.order_date) }}
                    </div>
                  </div>
                </div>
                <div class="d-flex gap-2">
                  <VBtn
                    icon="tabler-eye"
                    variant="tonal"
                    color="info"
                    size="40"
                    class="rounded-lg"
                    @click="handleView(item.id)"
                  />
                  <VBtn
                    v-if="authStore.user?.role_id === 1 || authStore.user?.role_id === 2"
                    icon="tabler-trash"
                    variant="tonal"
                    color="error"
                    size="40"
                    class="rounded-lg"
                    @click="handleCancelled(item.id)"
                  />
                </div>
              </div>

              <VDivider class="my-3 border-dashed" />

              <div class="mb-3">
                <div class="text-caption text-disabled uppercase font-weight-bold mb-1">Cliente</div>
                <div class="text-body-2 font-weight-medium">
                  {{ item.client?.name }} {{ item.client?.last_name }}
                  <span class="text-caption text-disabled d-block">
                    {{ item.client?.identification_type }} {{ item.client?.identification }}
                  </span>
                </div>
              </div>

              <div v-if="!props.isBlind" class="d-flex justify-space-between align-end">
                <div>
                  <div class="text-caption text-disabled uppercase font-weight-bold mb-1">Monto Total</div>
                    <div class="text-lg font-weight-bold">
                      {{ item.total_amount }} 
                    <VChip
                      :color="hasCreditPayment(item.payment_methods) ? 'error' : 'primary'"
                      variant="tonal"
                      size="x-small"
                      class="ml-1"
                    >
                      {{ item.currency }}
                    </VChip>
                  </div>
                </div>
                <VBtn
                  v-if="item.has_multiple_currencies"
                  variant="text"
                  color="secondary"
                  density="compact"
                  class="text-caption"
                  @click="toggleExpand(item.id)"
                >
                  {{ expandedRows.includes(item.id) ? 'Ocultar' : 'Ver Detalles' }}
                </VBtn>
              </div>

              <VExpandTransition>
                <div v-show="expandedRows.includes(item.id)" class="mt-4 pt-4 border-t-dashed">
                  <div class="d-flex flex-wrap gap-4">
                    <div v-for="(payment, index) in item.payment_methods" :key="index" class="d-flex flex-column">
                      <span class="text-xs text-disabled uppercase">{{ getPaymentMethodLabel(payment.method) }}</span>
                      <span class="text-body-2 font-weight-bold">{{ payment.currency }} {{ payment.amount }}</span>
                    </div>
                  </div>
                </div>
              </VExpandTransition>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <div class="d-flex justify-center mt-4">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalOrders / props.itemsPerPage)"
          density="comfortable"
          variant="tonal"
          @update:model-value="(val) => emit('update:options', { page: val, itemsPerPage: props.itemsPerPage })"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header th) {
  background: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  block-size: 44px !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-table :deep(.v-data-table__td) {
  padding-block: 12px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}

.order-card {
  transition: transform 0.2s ease;
}

.order-card:active {
  transform: scale(0.98);
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.15;
}

.border-t-dashed {
  border-block-start: 1px dashed rgba(var(--v-border-color), 0.15);
}

.bg-light-primary {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.leading-none {
  line-height: 1 !important;
}
</style>
