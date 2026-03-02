<script setup>
const props = defineProps({
  fiscalData: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const headers = [
  { title: "#Orden", key: "order_id", sortable: true, width: "10%" },
  { title: "#Factura", key: "invoice_number", sortable: true, width: "15%" },
  { title: "RIF", key: "identification", sortable: true, width: "15%" },
  { title: "Razón Social", key: "business_name", sortable: true, width: "20%" },
  {
    title: "Exento",
    key: "exempt_amount",
    sortable: true,
    align: "end",
    width: "10%",
  },
  {
    title: "Imponible",
    key: "imponible",
    sortable: true,
    align: "end",
    width: "10%",
  },
  {
    title: "IVA",
    key: "iva_amount",
    sortable: true,
    align: "end",
    width: "10%",
  },
  {
    title: "Total",
    key: "total_amount",
    sortable: true,
    align: "end",
    width: "10%",
  },
];

const formatCurrency = (amount) => {
  const number = parseFloat(amount) || 0;
  return "Bs. " + number.toLocaleString("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  return new Date(dateString).toLocaleDateString("es-CO", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
};

// Calcular el monto imponible (eliminado por venir del backend)
// const calculateImponible... (eliminado)

// Obtener color para el monto de IVA
const getIvaColor = (ivaAmount) => {
  if (!ivaAmount || ivaAmount === 0) return "text-disabled";
  return "text-success";
};

// Obtener estado de la factura según los montos
const getFacturaStatus = (item) => {
  const iva = parseFloat(item.iva_amount) || 0;
  const exento = parseFloat(item.exempt_amount) || 0;
  const total = parseFloat(item.total_amount) || 0;

  if (iva > 0) {
    return { text: "Con IVA", color: "success" };
  } else if (exento === total) {
    return { text: "Exenta", color: "info" };
  } else {
    return { text: "Sin IVA", color: "warning" };
  }
};
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center">
      <VIcon icon="tabler-receipt" class="me-2" />
      Facturas de Ventas (Débito Fiscal)
      <VSpacer />
      <VChip color="warning" size="small" variant="tonal">
        {{ totalRecords }} facturas
      </VChip>
    </VCardTitle>

    <VDivider />

    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.fiscalData"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.order_id="{ item }">
        <span class="font-weight-medium text-primary"
          >#{{ item.order_id }}</span
        >
      </template>

      <template #item.invoice_number="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-1 font-weight-medium">
            {{ item.invoice_number }}
          </span>
          <span class="text-xs text-disabled">
            {{ formatDate(item.invoice_date) }}
          </span>
        </div>
      </template>

      <template #item.identification="{ item }">
        <span class="font-weight-medium">{{ item.identification }}</span>
      </template>

      <template #item.business_name="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-1 font-weight-medium text-high-emphasis">
            {{ item.business_name }}
          </span>
        </div>
      </template>

      <template #item.exempt_amount="{ item }">
        <div class="text-end">
          <span
            class="font-weight-medium"
            :class="item.exempt_amount > 0 ? 'text-info' : 'text-disabled'"
          >
            {{ formatCurrency(item.exempt_amount) }}
          </span>
        </div>
      </template>

      <template #item.imponible="{ item }">
        <div class="text-end">
          <span class="font-weight-medium">
            {{ formatCurrency(item.taxable_base) }}
          </span>
        </div>
      </template>

      <template #item.iva_amount="{ item }">
        <div class="text-end">
          <div class="d-flex flex-column align-end">
            <span
              class="font-weight-bold"
              :class="getIvaColor(item.iva_amount)"
            >
              {{ formatCurrency(item.iva_amount) }}
            </span>
            <span v-if="item.spe" class="text-xs text-warning"> +SPE </span>
          </div>
        </div>
      </template>

      <template #item.total_amount="{ item }">
        <div class="text-end">
          <span class="font-weight-bold text-high-emphasis">
            {{ formatCurrency(item.total_amount) }}
          </span>
        </div>
      </template>

      <!-- Loading state -->
      <template #loading>
        <VSkeletonLoader type="table-row@10" />
      </template>

      <!-- No data state -->
      <template #no-data>
        <div class="text-center pa-6">
          <VIcon
            icon="tabler-receipt-off"
            size="48"
            class="mb-2 text-disabled"
          />
          <div class="text-body-1 font-weight-medium mb-1">
            No hay facturas de ventas
          </div>
          <div class="text-body-2 text-disabled">
            No se encontraron facturas con IVA para el período seleccionado
          </div>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
