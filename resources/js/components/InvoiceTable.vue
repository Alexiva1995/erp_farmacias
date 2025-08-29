<script setup>
import { computed } from "vue";

const props = defineProps({
  invoices: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalInvoices: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  actionsMode: {
    type: String,
    default: "default",
  },
});

const emit = defineEmits([
  "update:options",
  "edit-invoice",
  "edit-invoice-form",
  "delete-invoice",
  "approve-invoice",
  "reject-invoice",
  "locate-products",
  "view-details",
]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Proveedor", key: "supplier.name", sortable: true },
  { title: "N° Factura", key: "invoice_number", sortable: true },
  { title: "N° Control", key: "control_number", sortable: true },
  { title: "Vencimiento", key: "exp_date", sortable: true },
  { title: "Total", key: "total_amount", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const formatCurrency = (value, currency) => {
  const currencyMap = { BS: "VES", Bs: "VES", COP: "COP", USD: "USD" };
  const mappedCurrency = currencyMap[currency] || currency;
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: mappedCurrency,
    minimumFractionDigits: 2,
  }).format(value);
};

const processedInvoices = computed(() => {
  return props.invoices.map((invoice) => ({ ...invoice }));
});
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
          <span class="font-weight-medium">{{
            formatCurrency(item.total_amount, item.currency)
          }}</span>
          <span
            v-if="item.currency !== 'USD'"
            class="text-caption text-medium-emphasis"
            >{{ formatCurrency(item.total_usd, "USD") }}</span
          >
        </div>
      </template>

      <template #item.actions="{ item }">
        <div v-if="props.actionsMode === 'approval'">
          <VTooltip text="Aprobar Factura"
            ><template #activator="{ props }"
              ><IconBtn
                v-bind="props"
                color="success"
                @click="emit('approve-invoice', item)"
                ><VIcon icon="tabler-thumb-up" /></IconBtn></template
          ></VTooltip>
          <VTooltip text="Rechazar Factura"
            ><template #activator="{ props }"
              ><IconBtn
                v-bind="props"
                color="error"
                @click="emit('reject-invoice', item)"
                ><VIcon icon="tabler-thumb-down" /></IconBtn></template
          ></VTooltip>
          <VTooltip text="Ver Detalles"
            ><template #activator="{ props }"
              ><IconBtn v-bind="props" @click="emit('view-details', item)"
                ><VIcon icon="tabler-eye" /></IconBtn></template
          ></VTooltip>
        </div>

        <div v-else-if="props.actionsMode === 'location'">
          <VBtn
            color="primary"
            variant="tonal"
            size="small"
            @click="emit('locate-products', item)"
            ><VIcon icon="tabler-map-pin" class="me-2" />Ubicar Productos</VBtn
          >
        </div>

        <div v-else-if="props.actionsMode === 'ordered'">
          <VTooltip text="Ver Detalles">
            <template #activator="{ props }">
              <IconBtn v-bind="props" @click="emit('view-details', item)">
                <VIcon icon="tabler-eye" />
              </IconBtn>
            </template>
          </VTooltip>
          <VTooltip text="Descargar Boucher (Próximamente)">
            <template #activator="{ props }">
              <IconBtn v-bind="props">
                <VIcon icon="tabler-download" />
              </IconBtn>
            </template>
          </VTooltip>
        </div>

        <div v-else class="d-flex">
          <VTooltip text="Editar Factura"
            ><template #activator="{ props }"
              ><IconBtn v-bind="props" @click="emit('edit-invoice-form', item)"
                ><VIcon icon="tabler-edit" /></IconBtn></template
          ></VTooltip>
          <VTooltip text="Ver Productos"
            ><template #activator="{ props }"
              ><IconBtn v-bind="props" @click="emit('edit-invoice', item)"
                ><VIcon icon="tabler-package" /></IconBtn></template
          ></VTooltip>
          <VTooltip text="Eliminar"
            ><template #activator="{ props }"
              ><IconBtn v-bind="props" @click="emit('delete-invoice', item.id)"
                ><VIcon icon="tabler-trash" /></IconBtn></template
          ></VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
