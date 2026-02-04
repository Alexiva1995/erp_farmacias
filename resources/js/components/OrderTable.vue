<script setup>
import { computed } from "vue";
import { capitalizeFirstAndLastName } from "@/@core/utils/formatters";
import { formatAmountOnly } from "@/utils/currencyFormatter";

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
});

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
  <VCard>
    <VDataTableServer
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
          <IconBtn @click="handleView(item.id)" color="info">
            <VIcon icon="tabler-eye" />
          </IconBtn>
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
