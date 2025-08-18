<script setup>
import { computed } from "vue";

const props = defineProps({
  invoices: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalInvoices: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  actionsMode: { type: String, default: "default" },
  exchangeRates: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:options",
  "edit-invoice",
  "edit-invoice-form",
  "delete-invoice",
  "approve-invoice",
  "reject-invoice",
]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Proveedor", key: "supplier.name", sortable: true },
  { title: "N° Factura", key: "invoice_number", sortable: true },
  { title: "Vencimiento", key: "exp_date", sortable: true },
  { title: "Total", key: "total_amount", sortable: true },
  { title: "Deuda Pendiente", key: "outstanding_debt", sortable: true },
  { title: "Estado", key: "status", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const getExchangeRate = (currency) => {
  if (!currency || currency === "USD") return 1;

  const currencyMapping = {
    Bs: "BS",
    BS: "BS",
    COP: "COP",
    USD: "USD",
  };

  const mappedCurrency = currencyMapping[currency] || currency;
  const rate = props.exchangeRates.find(
    (rate) => rate.currency_code === mappedCurrency
  );

  return rate ? parseFloat(rate.rate) : 1;
};

const convertAmount = (usdAmount, targetCurrency) => {
  if (!targetCurrency || targetCurrency === "USD") {
    return usdAmount;
  }

  const rate = getExchangeRate(targetCurrency);

  return usdAmount * rate;
};

const formatCurrency = (value, currency) => {
  const currencyMap = {
    BS: "VES",
    Bs: "VES",
    COP: "COP",
    USD: "USD",
  };

  const mappedCurrency = currencyMap[currency] || currency;

  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: mappedCurrency,
    minimumFractionDigits: 2,
  }).format(value);
};

const processedInvoices = computed(() => {
  return props.invoices.map((invoice) => {
    const convertedTotal = convertAmount(
      invoice.total_amount,
      invoice.currency
    );
    const convertedDebt = convertAmount(
      invoice.outstanding_debt,
      invoice.currency
    );

    return {
      ...invoice,
      converted_total_amount: convertedTotal,
      converted_outstanding_debt: convertedDebt,
    };
  });
});

const resolveStatusVariant = (status) => {
  const statusMap = {
    Pagada: { color: "success", icon: "tabler-check" },
    Pendiente: { color: "warning", icon: "tabler-clock" },
    "Por Aprobar": { color: "info", icon: "tabler-hourglass" },
    Vencida: { color: "error", icon: "tabler-alert-triangle" },
    Anulada: { color: "secondary", icon: "tabler-circle-x" },
    Rechazada: { color: "error", icon: "tabler-thumb-down" },
  };
  return (
    statusMap[status] || { color: "default", icon: "tabler-question-mark" }
  );
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="processedInvoices"
      :items-length="props.totalInvoices"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.supplier\.name="{ item }">
        <span class="font-weight-medium">{{ item.supplier.name }}</span>
      </template>

      <template #item.total_amount="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">
            {{ formatCurrency(item.converted_total_amount, item.currency) }}
          </span>
          <span
            v-if="item.currency !== 'USD'"
            class="text-caption text-medium-emphasis"
          >
            {{ formatCurrency(item.total_amount, "USD") }}
          </span>
        </div>
      </template>

      <template #item.outstanding_debt="{ item }">
        <div class="d-flex flex-column">
          <span
            :class="item.outstanding_debt > 0 ? 'text-error' : 'text-success'"
            class="font-weight-medium"
          >
            {{ formatCurrency(item.converted_outstanding_debt, item.currency) }}
          </span>
          <span
            v-if="item.currency !== 'USD'"
            :class="item.outstanding_debt > 0 ? 'text-error' : 'text-success'"
            class="text-caption text-medium-emphasis"
          >
            {{ formatCurrency(item.outstanding_debt, "USD") }}
          </span>
        </div>
      </template>

      <template #item.status="{ item }">
        <VChip
          :color="resolveStatusVariant(item.status).color"
          size="small"
          label
        >
          {{ item.status }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div v-if="props.actionsMode === 'approval'">
          <VTooltip text="Aprobar Factura">
            <template #activator="{ props }">
              <IconBtn
                v-bind="props"
                color="success"
                @click="emit('approve-invoice', item)"
              >
                <VIcon icon="tabler-thumb-up" />
              </IconBtn>
            </template>
          </VTooltip>
          <VTooltip text="Rechazar Factura">
            <template #activator="{ props }">
              <IconBtn
                v-bind="props"
                color="error"
                @click="emit('reject-invoice', item)"
              >
                <VIcon icon="tabler-thumb-down" />
              </IconBtn>
            </template>
          </VTooltip>
          <VTooltip text="Ver Detalles">
            <template #activator="{ props }">
              <IconBtn v-bind="props" @click="emit('edit-invoice', item)">
                <VIcon icon="tabler-eye" />
              </IconBtn>
            </template>
          </VTooltip>
        </div>

        <div v-else class="d-flex">
          <VTooltip text="Editar Factura">
            <template #activator="{ props }">
              <IconBtn v-bind="props" @click="emit('edit-invoice-form', item)">
                <VIcon icon="tabler-edit" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip text="Ver Productos">
            <template #activator="{ props }">
              <IconBtn v-bind="props" @click="emit('edit-invoice', item)">
                <VIcon icon="tabler-package" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip text="Eliminar">
            <template #activator="{ props }">
              <IconBtn v-bind="props" @click="emit('delete-invoice', item.id)">
                <VIcon icon="tabler-trash" />
              </IconBtn>
            </template>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
