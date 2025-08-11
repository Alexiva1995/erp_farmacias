<script setup>
const props = defineProps({
  invoices: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalInvoices: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  actionsMode: { type: String, default: "default" },
});

const emit = defineEmits([
  "update:options",
  "edit-invoice",
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

const formatCurrency = (value, currency) => {
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: currency,
    minimumFractionDigits: 2,
  }).format(value);
};

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
      :items="props.invoices"
      :items-length="props.totalInvoices"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.supplier\.name="{ item }">
        <span class="font-weight-medium">{{ item.supplier.name }}</span>
      </template>

      <template #item.total_amount="{ item }">
        <span class="font-weight-medium">
          {{
            formatCurrency(
              item.total_amount,
              item.currency === "Bs" ? "VES" : item.currency
            )
          }}
        </span>
      </template>

      <template #item.outstanding_debt="{ item }">
        <span
          :class="item.outstanding_debt > 0 ? 'text-error' : 'text-success'"
        >
          {{
            formatCurrency(
              item.outstanding_debt,
              item.currency === "Bs" ? "VES" : item.currency
            )
          }}
        </span>
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

        <div v-else>
          <IconBtn @click="emit('edit-invoice', item)">
            <VIcon icon="tabler-edit" />
          </IconBtn>
          <IconBtn @click="emit('delete-invoice', item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
