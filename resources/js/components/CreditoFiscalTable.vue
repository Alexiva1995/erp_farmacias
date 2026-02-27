<script setup>
const props = defineProps({
  expensesData: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const headers = [
  { title: "Proveedor", key: "supplier_name", sortable: true, width: "20%" },
  { title: "#Factura", key: "invoice_number", sortable: true, width: "15%" },
  { title: "RIF", key: "supplier_rif", sortable: true, width: "15%" },
  {
    title: "Razón Social",
    key: "supplier_business_name",
    sortable: true,
    width: "20%",
  },
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

// Calcular el monto imponible (removido por uso directo de BD)
// const calculateImponible = ...

// Obtener color para el tipo de gasto
const getExpenseTypeColor = (item) => {
  if (item.is_deductible) {
    return "success";
  }
  return "info";
};

// Obtener estado del gasto según los montos
const getExpenseStatus = (item) => {
  const ivaAmount = parseFloat(item.iva_amount) || 0;
  const isDeductible = item.is_deductible;

  if (ivaAmount > 0 && isDeductible) {
    return { text: "Deducible c/IVA", color: "success" };
  } else if (ivaAmount > 0) {
    return { text: "Con IVA", color: "info" };
  } else if (isDeductible) {
    return { text: "Deducible s/IVA", color: "warning" };
  } else {
    return { text: "No deducible", color: "error" };
  }
};

// Obtener categoría del gasto
const getCategoryChipColor = (categoryName) => {
  const colors = ["primary", "secondary", "success", "info", "warning"];
  const hash = categoryName.split("").reduce((a, b) => a + b.charCodeAt(0), 0);
  return colors[hash % colors.length];
};
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center">
      <VIcon icon="tabler-receipt-2" class="me-2" />
      Gastos con IVA (Crédito Fiscal)
      <VSpacer />
      <VChip color="info" size="small" variant="tonal">
        {{ totalRecords }} gastos
      </VChip>
    </VCardTitle>

    <VDivider />

    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.expensesData"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.supplier_name="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-1 font-weight-medium text-high-emphasis">
            {{ item.supplier_name || "N/A" }}
          </span>
          <VChip
            v-if="item.category_name"
            :color="getCategoryChipColor(item.category_name)"
            variant="tonal"
            size="x-small"
            class="mt-1 align-self-start"
          >
            {{ item.category_name }}
          </VChip>
        </div>
      </template>

      <template #item.invoice_number="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-1 font-weight-medium">
            {{ item.invoice_number || "N/A" }}
          </span>
          <span class="text-xs text-disabled">
            {{ formatDate(item.expense_date) }}
          </span>
        </div>
      </template>

      <template #item.supplier_rif="{ item }">
        <span class="font-weight-medium">{{ item.supplier_rif || "N/A" }}</span>
      </template>

      <template #item.supplier_business_name="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-1 font-weight-medium text-high-emphasis">
            {{ item.supplier_business_name || item.supplier_name || "N/A" }}
          </span>
        </div>
      </template>

      <template #item.exempt_amount="{ item }">
        <div class="text-end">
          <span
            class="font-weight-medium"
            :class="item.exempt_amount > 0 ? 'text-info' : 'text-disabled'"
          >
            {{ formatCurrency(item.exempt_amount || 0) }}
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
              :class="item.iva_amount > 0 ? 'text-success' : 'text-disabled'"
            >
              {{ formatCurrency(item.iva_amount || 0) }}
            </span>
            <span v-if="item.is_deductible" class="text-xs text-success">
              Deducible
            </span>
          </div>
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
            No hay gastos con IVA
          </div>
          <div class="text-body-2 text-disabled">
            No se encontraron gastos con IVA para el período seleccionado
          </div>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
