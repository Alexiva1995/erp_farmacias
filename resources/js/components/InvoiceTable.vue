<script setup>
import { computed } from "vue";
import { formatCurrency as globalFormatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  invoices: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalInvoices: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  isAdmin: { type: Boolean, required: true },
  actionsMode: {
    type: String,
    default: "default",
  },
  highlightedId: {
    type: [Number, String],
    default: null,
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
  "return-invoice",
]);

const headers = computed(() => {
  const baseHeaders = [
    { title: "ID", key: "id", sortable: true },
    { title: "Proveedor", key: "supplier.name", sortable: true },
    { title: "N° Factura", key: "invoice_number", sortable: true },
    { title: "N° Control", key: "control_number", sortable: true },
    { title: "Emisión", key: "created_invoice_date", sortable: true },
    { title: "Vencimiento", key: "exp_date", sortable: true },
  ];

  if (props.actionsMode === "location") {
    baseHeaders.push({ title: "Localización", key: "locations_summary", sortable: true });
  }

  baseHeaders.push(
    { title: "Total", key: "total_amount", sortable: true },
    { title: "Acciones", key: "actions", sortable: false, align: "center" }
  );

  return baseHeaders;
});

const formatCurrency = (value, currency) => {
  return globalFormatCurrency(Number(value), currency);
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  return new Date(dateString).toLocaleDateString("es-VE", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
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
      class="text-no-wrap invoice-table"
      :row-props="
        (data) => ({
          class:
            data.item.id === props.highlightedId ? 'highlighted-row' : '',
        })
      "
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
      <template #item.exp_date="{ item }">
        {{ formatDate(item.exp_date) }}
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex ga-2">
          <!-- Botón Devolver: Visible en todas las vistas EXCEPTO en 'Por Ordenar' (location) -->
          <div v-if="props.isAdmin && props.actionsMode !== 'location'">
            <VBtn
              v-bind="props"
              color="primary"
              variant="tonal"
              size="small"
              @click="emit('return-invoice', item.id)"
            >
              <VIcon icon="tabler-arrow-back-up" class="me-2" />
              Devolver
            </VBtn>
          </div>

          <div v-if="props.actionsMode === 'approval'">
            <VTooltip text="Revisar y Aprobar">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  color="primary"
                  variant="tonal"
                  size="small"
                  @click="emit('edit-invoice', item)"
                >
                  <VIcon icon="tabler-eye" class="me-2" />
                  Revisar
                </VBtn>
              </template>
            </VTooltip>
          </div>

          <div v-else-if="props.actionsMode === 'location'">
            <VBtn
              color="primary"
              variant="tonal"
              size="small"
              @click="emit('locate-products', item)"
              ><VIcon icon="tabler-map-pin" class="me-2" />Ubicar
              Productos</VBtn
            >
          </div>

          <div v-else-if="props.actionsMode === 'ordered'">
            <VTooltip text="Ver Detalles">
              <template #activator="{ props }">
                <IconBtn v-bind="props" @click="emit('view-details', item)" color="info">
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
                ><IconBtn
                  v-bind="props"
                  @click="emit('edit-invoice-form', item)"
                  color="warning"
                  ><VIcon icon="tabler-edit" /></IconBtn></template
            ></VTooltip>
            <VTooltip text="Ver Productos"
              ><template #activator="{ props }"
                ><IconBtn v-bind="props" @click="emit('edit-invoice', item)"
                  ><VIcon icon="tabler-package" /></IconBtn></template
            ></VTooltip>
            <VTooltip text="Eliminar"
              ><template #activator="{ props }"
                ><IconBtn
                  v-bind="props"
                  @click="emit('delete-invoice', item.id)"
                  color="error"
                  ><VIcon icon="tabler-trash" /></IconBtn></template
            ></VTooltip>
          </div>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
.invoice-table :deep(.highlighted-row) {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
  transition: background-color 0.3s ease;
}

.invoice-table :deep(.highlighted-row:hover) {
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
}
</style>
