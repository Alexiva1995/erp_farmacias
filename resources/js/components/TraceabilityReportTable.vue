<script setup>
import TraceabilityMovementDetailsDialog from "@/components/dialogs/TraceabilityMovementDetailsDialog.vue";
import { formatDate } from "@/utils/formatters";
import { ref } from "vue";

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

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
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
  <VCard variant="flat">
    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
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
          <span class="font-weight-medium">#{{ item.product_id }}</span>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex align-start gap-x-4 max-width-300">
            <VAvatar
              v-if="item.product?.photo_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.product.photo_url"
              class="flex-shrink-0"
            />
            <div class="d-flex flex-column flex-grow-1 min-width-0 overflow-wrap-break text-normal-white">
              <span
                class="text-body-1 font-weight-medium text-high-emphasis leading-tight"
                :class="{ 'text-warning font-weight-bold': item.product?.psychotropic }"
              >
                {{ item.product?.name?.toUpperCase() || 'N/A' }}
                <span v-if="item.product?.iva"> (G)</span>
                <span v-if="item.product?.is_colombian_origin"> (COL)</span>
              </span>
              <span class="text-sm text-disabled" v-if="item.product?.active_ingredient">{{
                item.product.active_ingredient
              }}</span>
              <span class="text-sm text-disabled" v-if="item.product?.laboratory?.name">{{
                item.product.laboratory.name
              }}</span>
            </div>
          </div>
        </template>

        <template #item.movement_date="{ item }">
          <span class="text-no-wrap text-body-2">{{ formatDate(item.movement_date) }}</span>
        </template>

        <template #item.user.email="{ item }">
          <span class="text-body-2">{{ getUserDisplayName(item.user) }}</span>
        </template>

        <template #item.quantity="{ item }">
          <VChip
            :color="item.quantity > 0 ? 'success' : 'error'"
            size="small"
            label
            variant="tonal"
            class="font-weight-bold"
          >
            {{ item.quantity > 0 ? `+${item.quantity}` : item.quantity }}
          </VChip>
        </template>

        <template #item.movement_type="{ item }">
          <span class="text-caption text-uppercase font-weight-medium">{{ item.movement_type }}</span>
        </template>

        <template #item.reference="{ item }">
          <VBtn
            variant="tonal"
            color="primary"
            size="x-small"
            rounded="pill"
            @click="handleReferenceClick(item)"
          >
            {{
              item.order_id != null
                ? `ORD-${item.order_id}`
                : item.invoice_id != null
                ? (item.invoice?.invoice_number ?? `INV-${item.invoice_id}`)
                : `REF-${item.id}`
            }}
            <VIcon icon="tabler-external-link" class="ms-1" size="14" />
          </VBtn>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas) -->
    <div class="d-block d-md-none pa-2">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.sales.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron movimientos registrados.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.sales"
          :key="item.id"
          variant="flat"
          class="border mb-1 overflow-hidden"
          style="border-radius: 12px !important;"
        >
          <div class="pa-4">
            <div class="d-flex gap-3 align-start mb-3">
              <VAvatar
                v-if="item.product?.photo_url"
                size="44"
                variant="tonal"
                rounded
                :image="item.product.photo_url"
                class="flex-shrink-0"
              />
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1 mb-1">
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                    <span class="text-primary">#{{ item.product_id }}</span>
                    <span class="mx-1 text-disabled">|</span>
                    {{ item.product?.name }}
                  </h3>
                </div>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                  <span class="text-medium-emphasis font-weight-bold">{{ item.product?.active_ingredient }}</span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-black text-uppercase">{{ item.product?.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded mb-3">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Stock Anterior</span>
                <span class="text-base font-weight-black text-medium-emphasis">
                  {{ item.stock_before }} UNDS
                </span>
              </div>
              <div class="d-flex flex-column align-center">
                <VIcon 
                  :icon="item.quantity > 0 ? 'tabler-arrow-up-circle' : 'tabler-arrow-down-circle'" 
                  :color="item.quantity > 0 ? 'success' : 'error'"
                  size="24"
                />
                <span :class="item.quantity > 0 ? 'text-success' : 'text-error'" class="text-sm font-weight-black mt-n1">
                   {{ item.quantity > 0 ? `+${item.quantity}` : item.quantity }}
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Stock Final</span>
                <span class="text-base font-weight-black" :class="item.stock_after > 0 ? 'text-primary' : 'text-error'">
                  {{ item.stock_after }} UNDS
                </span>
              </div>
            </div>

            <div class="d-flex flex-column gap-y-1 mb-3 bg-var-theme-background pa-2 rounded-sm border-dashed-thin">
              <div class="d-flex justify-space-between align-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Tipo Movimiento</span>
                <VChip size="x-small" label color="primary" variant="flat" class="font-weight-black">{{ item.movement_type }}</VChip>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Operador</span>
                <span class="text-caption font-weight-bold text-medium-emphasis">{{ getUserDisplayName(item.user) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Fecha</span>
                <span class="text-caption font-weight-black text-high-emphasis">{{ formatDate(item.movement_date) }}</span>
              </div>
            </div>

            <VBtn 
              block 
              color="primary" 
              variant="tonal" 
              size="small"
              height="40"
              prepend-icon="tabler-external-link" 
              @click="handleReferenceClick(item)"
            >
              VER REFERENCIA: {{
                item.order_id != null
                  ? `ORD-${item.order_id}`
                  : item.invoice_id != null
                  ? (item.invoice?.invoice_number ?? `INV-${item.invoice_id}`)
                  : `ID-${item.id}`
              }}
            </VBtn>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalSales / props.itemsPerPage)"
          :total-visible="3"
          density="compact"
          size="small"
          @update:model-value="handleMobilePageChange"
        />
      </div>
    </div>
  </VCard>

  <TraceabilityMovementDetailsDialog
    v-model="showDetailsDialog"
    :movement-id="selectedMovementId"
  />
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.gap-2 {
  gap: 8px !important;
}

.gap-3 {
  gap: 12px !important;
}

.max-width-300 {
  max-inline-size: 300px;
}

.text-normal-white {
  overflow-wrap: break-word;
  white-space: normal;
}

.leading-tight {
  line-height: 1.25 !important;
}
</style>
