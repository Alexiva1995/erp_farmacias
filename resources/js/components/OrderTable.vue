<script setup>
const props = defineProps({
  orders: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOrders: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  headers: {type: Array,required: true},
});

const emit = defineEmits(["update:options","print-order", "view-order"]);

const date = (order) => {
  const time = new Date(order);
  return time.toISOString().split("T")[0];
};

const handleView = (orderId) => {
  emit('view-order', orderId);
}

// Función para obtener la moneda y el monto total
const getPaymentSummary = (payments) => {
    // Si no hay pagos, retornamos valores por defecto
    if (!payments || payments.length === 0) {
        return { totalAmount: 0, currency: 'N/A' };
    }

    // Calcula el monto total
    const totalAmount = payments.reduce((acc, current) => acc + (current.amount || 0), 0);

    // Determina la moneda principal. Puedes usar la moneda del primer pago o la que prefieras.
    const currency = payments[0].currency || 'N/A';
    
    return { totalAmount, currency };
};

// Función para obtener el ícono de expansión
const expansionIcon = (expanded) => {
    return expanded ? 'tabler-chevron-up' : 'tabler-chevron-down';
};

// Array para controlar las filas expandidas manualmente
const expandedRows = ref([]);

// Función para alternar la expansión de una fila
const toggleExpand = (itemId) => {
  const index = expandedRows.value.indexOf(itemId);
  if (index === -1) {
    expandedRows.value.push(itemId);
  } else {
    expandedRows.value.splice(index, 1);
  }
};
const paymentMethodLabels = {
    cash_cop: 'Efectivo',
    bank_transfer: 'Transferencia',
    cash_bs: 'Efectivo',
    mobile_payment: 'Pago Móvil',
    bank_transfer_bs: 'Transferencia',
    card: 'Tarjeta',
    cash_usd: 'Efectivo',
    binance: 'Binance',
    paypal: 'PayPal',
    credit: 'Crédito',
};
const getPaymentMethodLabel = (methodValue) => {
    return paymentMethodLabels[methodValue] || methodValue;
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
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
      :expanded="expandedRows"
    >
     <template v-slot:item.identification="{ item }">
      {{ item.client.identification_type }} {{ item.client.identification }}
    </template>
    <template v-slot:item.client_full_name="{ item }">
      {{ item.client.name }} {{ item.client.last_name }}
    </template>


 <template v-slot:item.total_amount="{ item }">
        <span>
          {{ item.total_amount }}
        </span>
          <IconBtn
            v-if="item.has_multiple_currencies === true"
            @click="toggleExpand(item.id)"
          >
            <VIcon :icon="expandedRows.includes(item.id) ? 'tabler-chevron-up' : 'tabler-chevron-down'" />
          </IconBtn>
      </template>

    <template v-slot:item.currency="{ item }">
        <span v-if="item.payment_methods?.some(p => p.method === 'credit')">
          {{ item.currency }}*
        </span>
        <span v-else>
          {{ item.currency }}
        </span>
      </template>

<template v-slot:item.status="{ item }">
        <VChip
          :color="item.status === 'Completed' ? 'success' : item.status === 'Abandoned' ? 'warning' : 'error'"
        >
          <span v-if="item.status === 'Completed'">Completada</span>
          <span v-else-if="item.status === 'Abandoned'">Abandonada</span>
          <span v-else-if="item.status === 'Cancelled'">Cancelada</span>
          <span v-else>{{ item.status }}</span> </VChip>
      </template>

   <template #item.date="{ item }">
        <span>{{ date(item.order_date) }}</span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center gap-2">
          <IconBtn
            @click="handleView(item.id)">
            <VIcon icon="tabler-eye" />
          </IconBtn>
          <IconBtn
            @click="$emit('print-order', item.id)">
            <VIcon icon="tabler-printer" />
          </IconBtn>
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
                        Monto: {{ payment.currency }} {{ payment.amount }}
                    </VListItemSubtitle>
                </VListItem>
            </VList>
          </td>
        </tr>
      </template>

    </VDataTableServer>
  </VCard>
</template>
