<script setup>
import { computed } from "vue";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { formatAmountOnly } from "@/utils/currencyFormatter";
import { useDisplay } from "vuetify";

const props = defineProps({
  orders: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOrders: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  headers: { type: Array, required: true },
  sortBy: { type: [String, Array], default: () => [] },
  orderBy: { type: String, default: "desc" },
  showThermalPrint: { type: Boolean, default: false },
  showPrintActions: { type: Boolean, default: true },
});

const { mobile } = useDisplay();

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  const key = Array.isArray(props.sortBy) ? props.sortBy[0] : props.sortBy;
  return key ? [{ key, order: props.orderBy || "desc" }] : [];
});

const emit = defineEmits(["update:options", "print-order", "print-order-thermal", "view-order"]);

const date = (order) => {
  const time = new Date(order);
  return time.toISOString().split("T")[0];
};

const handleView = (orderId) => {
  emit("view-order", orderId);
};

const getPaymentSummary = (payments) => {
  if (!payments || payments.length === 0) {
    return { totalAmount: 0, currency: "N/A" };
  }

  const totalAmount = payments.reduce(
    (acc, current) => acc + (current.amount || 0),
    0
  );
  const currency = payments[0].currency || "N/A";
  return { totalAmount, currency };
};

const expansionIcon = (expanded) => {
  return expanded ? "tabler-chevron-up" : "tabler-chevron-down";
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
  debit_card: "T. Débito",
  credit_card: "T. Crédito",
  cash_usd: "Efectivo",
  binance: "Binance",
  paypal: "PayPal",
  credit: "Crédito",
};
const getPaymentMethodLabel = (methodValue) => {
  return paymentMethodLabels[methodValue] || methodValue;
};
const renderIdentification = (item) => {
  if (item.client?.identification_type) {
    return `${item.client.identification_type} ${item.client.identification}`;
  } else {
    return "N/A";
  }
};
const renderUsername = (item) => {
  if (item.client?.name) {
    return `${item.client.name} ${item.client.last_name}`;
  } else {
    return "N/A";
  }
};

const renderSellerName = (item) => {
  const name = item.seller?.username;
  return name ? capitalizeFirstAndLastName(name) : "N/A";
};
</script>
<template>
  <VCard variant="flat" border class="rounded-lg overflow-hidden shadow-sm">
    <template v-if="mobile">
      <VDataIterator
        :items="props.orders"
        :items-per-page="props.itemsPerPage"
        :loading="props.loading"
      >
        <template v-slot:default="{ items }">
          <div class="pa-4 d-flex flex-column gap-4">
            <VCard
              v-for="item in items"
              :key="item.raw.id"
              variant="flat"
              border
              class="rounded-lg pa-4"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-caption font-weight-bold text-primary">ID: #{{ item.raw.id }}</span>
                <VChip
                  size="x-small"
                  :color="
                    item.raw.status === 'Completed'
                      ? 'success'
                      : item.raw.status === 'Abandoned'
                      ? 'warning'
                      : 'error'
                  "
                  class="font-weight-bold text-uppercase"
                >
                  {{ item.raw.status === 'Completed' ? 'Completado' : item.raw.status }}
                </VChip>
              </div>

              <div class="mb-3">
                <div class="d-flex align-center gap-2 mb-1">
                  <VIcon size="16" color="secondary">tabler-user</VIcon>
                  <span class="text-body-2 font-weight-bold">{{ renderUsername(item.raw) }}</span>
                </div>
                <div class="d-flex align-center gap-2 mb-1 text-medium-emphasis">
                  <VIcon size="16">tabler-id</VIcon>
                  <span class="text-caption">{{ renderIdentification(item.raw) }}</span>
                </div>
                <div class="d-flex align-center gap-2 text-medium-emphasis">
                  <VIcon size="16">tabler-calendar</VIcon>
                  <span class="text-caption">{{ date(item.raw.order_date) }}</span>
                </div>
              </div>

              <VDivider class="border-dashed mb-3" />

              <div class="d-flex justify-space-between align-center">
                <div>
                  <div class="text-caption text-medium-emphasis mb-n1">Total</div>
                  <div class="text-h6 font-weight-black">
                    {{ formatAmountOnly(Number(item.raw.total_amount) || 0, item.raw.currency || 'COP') }}
                  </div>
                </div>
                <div class="d-flex gap-1">
                  <VBtn
                    icon="tabler-eye"
                    variant="tonal"
                    color="primary"
                    size="small"
                    @click="handleView(item.raw.id)"
                  />
                  <VBtn
                    v-if="showPrintActions"
                    icon="tabler-printer"
                    variant="tonal"
                    color="secondary"
                    size="small"
                    @click="$emit('print-order', item.raw.id)"
                  />
                </div>
              </div>
            </VCard>
          </div>
        </template>

        <template v-slot:no-data>
          <div class="pa-8 text-center text-medium-emphasis">
            No hay órdenes para mostrar
          </div>
        </template>
      </VDataIterator>

      <!-- Paginación Móvil -->
      <div class="pa-4 border-t d-flex justify-center">
        <VPagination
          v-model="props.page"
          :length="Math.ceil(props.totalOrders / props.itemsPerPage)"
          size="small"
          total-visible="5"
          @update:model-value="(p) => emit('update:options', { ...props, page: p })"
        />
      </div>
    </template>

    <VDataTableServer
      v-else
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="props.headers"
      :items="props.orders"
      :items-length="props.totalOrders"
      :loading="props.loading"
      :sort-by="sortByModel"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
      :expanded="expandedRows"
    >
      <!-- Skeleton Loader -->
      <template v-slot:loading>
        <VSkeletonLoader
          v-for="n in 5"
          :key="n"
          type="table-row"
          class="border-b"
        />
      </template>

      <template v-slot:item.id="{ item }">
        <span class="text-primary font-weight-black">#{{ item.id }}</span>
      </template>

      <template v-slot:item.identification="{ item }">
        {{ renderIdentification(item) }}
      </template>
      <template v-slot:item.client_full_name="{ item }">
        {{ renderUsername(item) }}
      </template>

      <template v-slot:item.seller.username="{ item }">
        {{ renderSellerName(item) }}
      </template>

      <template v-slot:item.total_amount="{ item }">
        <div class="d-flex align-center justify-end gap-1">
          <span class="text-end">
            {{ formatAmountOnly(Number(item.total_amount) || 0, item.currency || 'COP') }}
          </span>
          <IconBtn
            v-if="item.has_multiple_currencies === true"
            @click="toggleExpand(item.id)"
          >
            <VIcon
              :icon="
                expandedRows.includes(item.id)
                  ? 'tabler-chevron-up'
                  : 'tabler-chevron-down'
              "
            />
          </IconBtn>
        </div>
      </template>

      <template v-slot:item.currency="{ item }">
        <span
          v-if="
            Array.isArray(item.payment_methods) &&
            item.payment_methods?.some((p) => p.method === 'credit')
          "
        >
          {{ item.currency }}*
        </span>
        <span v-else>
          {{ item.currency }}
        </span>
      </template>

      <template v-slot:item.status="{ item }">
        <VChip
          :color="
            item.status === 'Completed'
              ? 'success'
              : item.status === 'Abandoned'
              ? 'warning'
              : 'error'
          "
        >
          <span v-if="item.status === 'Completed'">Completada</span>
          <span v-else-if="item.status === 'Abandoned'">Abandonada</span>
          <span v-else-if="item.status === 'Cancelled'">Cancelada</span>
          <span v-else>{{ item.status }}</span>
        </VChip>
      </template>

      <template #item.date="{ item }">
        <span>{{ date(item.order_date) }}</span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-2">
          <IconBtn @click="handleView(item.id)" color="primary">
            <VIcon icon="tabler-eye" />
          </IconBtn>
          <template v-if="showPrintActions">
            <IconBtn @click="$emit('print-order', item.id)">
              <VIcon icon="tabler-printer" />
            </IconBtn>
            <VTooltip v-if="showThermalPrint" text="Ticket 54mm térmico" location="top">
              <template #activator="{ props: tooltipProps }">
                <IconBtn v-bind="tooltipProps" @click="$emit('print-order-thermal', item.id)" color="secondary">
                  <VIcon icon="tabler-receipt" />
                </IconBtn>
              </template>
            </VTooltip>
          </template>
        </div>
      </template>

      <template v-slot:expanded-row="{ columns, item }">
        <tr>
          <td :colspan="columns.length">
            <VList>
              <VListItem
                v-for="(payment, index) in item.payment_methods"
                :key="index"
                class="my-2"
              >
                <VListItemTitle>
                  Método: {{ getPaymentMethodLabel(payment.method) }}
                </VListItemTitle>
                <VListItemSubtitle>
                  Monto: {{ formatAmountOnly(Number(payment.amount) || 0, payment.currency || 'COP') }}
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </td>
        </tr>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
:deep(.v-data-table) {
  .v-data-table-header th {
    background-color: #fff !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  }
}
</style>
