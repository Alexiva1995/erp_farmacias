<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalItems: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const formatEmployeeName = (user) => {
  if (!user) return "N/A";
  
  // Si tiene employee con name y last_name, usar esos
  if (user.employee_name && user.employee_last_name) {
    const name = user.employee_name.trim();
    const lastName = user.employee_last_name.trim();
    // Capitalize: primera letra mayúscula, resto minúsculas
    const formattedName = name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();
    const formattedLastName = lastName.charAt(0).toUpperCase() + lastName.slice(1).toLowerCase();
    return `${formattedName} ${formattedLastName}`;
  }
  
  // Si solo tiene employee.name
  if (user.employee_name) {
    const name = user.employee_name.trim();
    return name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();
  }
  
  // Fallback a name (username o email)
  if (user.name) {
    return user.name;
  }
  
  return "N/A";
};

const headers = [
  { title: "Nombre de Producto", key: "product.name", sortable: true, width: "250px", maxWidth: "250px" },
  { title: "Laboratorio", key: "product.laboratory.name", sortable: true },
  { title: "Cantidad", key: "discrepancy", align: "center", sortable: true },
  { title: "Costo", key: "product.unit_cost", align: "end", sortable: true },
  { title: "Usuario Conteo", key: "user.name", sortable: true },
  { title: "Supervisor Aprobación", key: "supervisor.name", sortable: true },
  { title: "Monto", key: "amount", align: "end", sortable: true },
];

/*const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(value);
};*/
</script>

<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items="props.items"
      :items-length="props.totalItems"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :loading="props.loading"
      item-value="id"
      no-data-text="No hay diferencias registradas para el cierre."
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.discrepancy="{ item }">
        <VChip
          :color="item.discrepancy > 0 ? 'success' : 'error'"
          label
          size="small"
        >
          <VIcon
            :icon="item.discrepancy > 0 ? 'tabler-plus' : 'tabler-minus'"
            start
          />
          {{ Math.abs(item.discrepancy) }}
        </VChip>
      </template>

      <template #item.amount="{ item }">
        <span
          :class="item.discrepancy > 0 ? 'text-success' : 'text-error'"
          class="font-weight-medium"
        >
          {{ formatCurrency(item.product.sale_price * item.discrepancy) }}
        </span>
      </template>

      <template #item.product.name="{ item }">
        <span class="font-weight-medium text-truncate d-inline-block" style="max-width: 250px;" :title="item.product.name">
          {{ item.product.name }}
        </span>
      </template>

      <template #item.product.laboratory.name="{ item }">
        <span>{{ item.product.laboratory?.name || "N/A" }}</span>
      </template>

      <template #item.product.unit_cost="{ item }">
        <span class="font-weight-medium">
          {{ formatCurrency(parseFloat(item.product.unit_cost || 0)) }}
        </span>
      </template>

      <template #item.user.name="{ item }">
        <span>{{ formatEmployeeName(item.user) }}</span>
      </template>

      <template #item.supervisor.name="{ item }">
        <span>{{ formatEmployeeName(item.supervisor) }}</span>
      </template>
    </VDataTableServer>
  </VCard>
</template>
