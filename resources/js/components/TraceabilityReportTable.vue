<script setup>
import TraceabilityMovementDetailsDialog from "@/components/dialogs/TraceabilityMovementDetailsDialog.vue";
import { ref } from "vue";

const getUserDisplayName = (user) => {
  if (!user) return "N/A";
  
  // Si tiene employee con name y last_name, usar esos
  if (user.employee?.name && user.employee?.last_name) {
    return `${user.employee.name} ${user.employee.last_name}`;
  }
  
  // Si solo tiene employee.name
  if (user.employee?.name) {
    return user.employee.name;
  }
  
  // Fallback a username o email
  return user.username || user.email || "N/A";
};

const props = defineProps({
  sales: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSales: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const showDetailsDialog = ref(false);
const selectedMovementId = ref(null);

const handleReferenceClick = (item) => {
  selectedMovementId.value = item.id;
  showDetailsDialog.value = true;
};

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Producto", key: "product.name", sortable: true, width: "300px" },
  { title: "Stock A", key: "stock_before", sortable: true },
  { title: "Cantidad", key: "quantity", sortable: false },
  { title: "Stock F", key: "stock_after", sortable: true },
  { title: "Fecha", key: "movement_date", sortable: true },
  { title: "Tipo", key: "movement_type", sortable: true },
  { title: "Operador", key: "user.email", sortable: true },
  { title: "Referencia", key: "reference", sortable: true },
];
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.sales"
      :items-length="props.totalSales"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.product_id }}</span>
      </template>

      <template #item.product.name="{ item }">
        <div class="d-flex align-start gap-x-4" style=" inline-size: 100%;max-inline-size: 300px;">
          <VAvatar
            v-if="item.product?.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.product.photo_url"
            style="flex-shrink: 0;"
          />
          <div class="d-flex flex-column" style=" flex: 1;min-inline-size: 0; overflow-wrap: break-word; word-wrap: break-word;">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 
                'text-warning font-weight-bold': item.product?.psychotropic == 1 || item.product?.psychotropic === true
              }"
              style=" line-height: 1.4; overflow-wrap: break-word; white-space: normal;word-wrap: break-word;"
            >
              {{ item.product?.name?.toUpperCase() || 'N/A' }}
              <span v-if="item.product?.iva == 1 || item.product?.iva === true"> (G)</span>
              <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true"> (COL)</span>
            </span>
            <span class="text-sm text-disabled" v-if="item.product?.active_ingredient" style=" line-height: 1.3; overflow-wrap: break-word; white-space: normal;word-wrap: break-word;">{{
              item.product.active_ingredient
            }}</span>
            <span class="text-sm text-disabled" v-if="item.product?.laboratory?.name" style=" line-height: 1.3; overflow-wrap: break-word; white-space: normal;word-wrap: break-word;">{{
              item.product.laboratory.name
            }}</span>
          </div>
        </div>
      </template>

      <template #item.movement_date="{ item }">
        <span>{{ new Date(item.movement_date).toLocaleDateString() }}</span>
      </template>

      <template #item.user.email="{ item }">
        <span>{{ getUserDisplayName(item.user) }}</span>
      </template>

      <template #item.customer.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{
              item.user.username
            }}</span>
            <span class="text-sm text-disabled">{{ item.user.email }}</span>
          </div>
        </div>
      </template>

      <template #item.quantity="{ item }">
        <span
          :class="{
            'text-success': item.quantity > 0,
            'text-error': item.quantity < 0,
          }"
          class="font-weight-medium"
        >
          {{ item.quantity > 0 ? `+${item.quantity}` : item.quantity }}
        </span>
      </template>

      <template #item.movement_type="{ item }">
        {{ item.movement_type }}
      </template>

      <template #item.total_amount="{ item }">
        <span class="font-weight-medium text-high-emphasis"
          >${{
            parseFloat(
              item.order_id != null
                ? item.order.total_amount
                : item.invoice_id != null
                ? item.invoice.total_amount
                : "N/A"
            ).toFixed(2)
          }}</span
        >
      </template>
      <template #item.reference="{ item }">
        <VBtn
          variant="text"
          color="primary"
          size="small"
          @click="handleReferenceClick(item)"
          @mouseenter="() => {
            if (item.invoice_id) {
              console.log('DEBUG Referencia - Item completo:', item);
              console.log('DEBUG Referencia - Invoice:', item.invoice);
              console.log('DEBUG Referencia - invoice_number:', item.invoice?.invoice_number);
              console.log('DEBUG Referencia - invoice_id:', item.invoice_id);
            }
          }"
        >
          {{
            item.order_id != null
              ? item.order_id
              : item.invoice_id != null
              ? (item.invoice?.invoice_number ?? item.invoice_id)
              : item.id
          }}
          <VIcon icon="tabler-external-link" class="ms-1" size="16" />
        </VBtn>
      </template>
    </VDataTableServer>
  </VCard>

  <TraceabilityMovementDetailsDialog
    v-model="showDetailsDialog"
    :movement-id="selectedMovementId"
  />
</template>

<style scoped>
:deep(.v-data-table td:nth-child(2)) {
  overflow: hidden !important;
  inline-size: 300px !important;
  max-inline-size: 300px !important;
  overflow-wrap: break-word !important;
  padding-block: 12px !important;
  vertical-align: top !important;
  white-space: normal !important;
  word-wrap: break-word !important;
}

:deep(.v-data-table th:nth-child(2)) {
  inline-size: 300px !important;
  max-inline-size: 300px !important;
  white-space: normal !important;
}

:deep(.v-data-table__wrapper) {
  overflow-x: auto;
}
</style>
