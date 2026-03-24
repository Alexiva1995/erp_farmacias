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

const handleReferenceClick = (id) => {
  selectedMovementId.value = id;
  showDetailsDialog.value = true;
};

const headers = [
  { title: "ID", key: "product_id", sortable: true, cellClass: "font-weight-black text-primary" },
  { title: "Operador", key: "user.username", sortable: true },
  { title: "S. Ant", key: "stock_before", sortable: true, align: 'center' },
  { title: "Cant.", key: "quantity", sortable: false, align: 'center' },
  { title: "S. Fin", key: "stock_after", sortable: true, align: 'center' },
  { title: "Fecha", key: "movement_date", sortable: true },
  { title: "Tipo", key: "movement_type", sortable: true },
  { title: "Referencia", key: "reference", sortable: false, align: 'center' },
];

const getMovementColor = (type) => {
  const colors = {
    'Venta': 'success',
    'Compra': 'info',
    'Devolución': 'warning',
    'Ajuste': 'secondary',
    'Pérdida': 'error',
    'Caducado': 'error',
  };
  return colors[type] || 'primary';
};

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};
</script>

<template>
  <VCard variant="flat" border>
    <VCardTitle class="pa-4 font-weight-black text-uppercase text-h6 d-flex align-center gap-2">
      <VIcon icon="tabler-history" color="primary" />
      Control de Movimientos de Psicotrópicos
    </VCardTitle>
    <VDivider />

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
        <template #item.product_id="{ item }">
          <span class="font-weight-black text-primary">{{ item.product_id }}</span>
        </template>

        <template #item.user.username="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VAvatar size="28" :color="item.user?.avatar ? '' : 'primary'" variant="tonal">
              <VImg v-if="item.user?.avatar" :src="item.user.avatar" />
              <span v-else class="text-xs font-weight-bold">{{ item.user?.username?.charAt(0).toUpperCase() }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-body-2 font-weight-bold text-high-emphasis leading-none mb-1">{{ item.user?.username }}</span>
              <span class="text-super-xs text-disabled leading-none">{{ item.user?.email }}</span>
            </div>
          </div>
        </template>

        <template #item.movement_date="{ item }">
          <span class="text-body-2 font-weight-medium">{{ formatDate(item.movement_date) }}</span>
        </template>

        <template #item.quantity="{ item }">
          <VChip
            :color="item.quantity > 0 ? 'success' : 'error'"
            size="x-small"
            label
            variant="flat"
            class="font-weight-black"
          >
            {{ item.quantity > 0 ? `+${item.quantity}` : item.quantity }}
          </VChip>
        </template>

        <template #item.movement_type="{ item }">
          <VChip
            :color="getMovementColor(item.movement_type)"
            size="x-small"
            variant="tonal"
            class="font-weight-black text-uppercase"
          >
            {{ item.movement_type }}
          </VChip>
        </template>

        <template #item.reference="{ item }">
          <VBtn
            v-if="item.order_id || item.invoice_id"
            variant="tonal"
            color="primary"
            size="x-small"
            class="font-weight-black px-2"
            @click="handleReferenceClick(item.id)"
          >
            #{{ item.order_id || item.invoice?.invoice_number || item.invoice_id }}
            <VIcon icon="tabler-external-link" class="ms-1" size="14" />
          </VBtn>
          <span v-else class="text-caption text-disabled font-weight-bold">N/A</span>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.sales.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron movimientos registrados.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.sales"
          :key="item.id"
          variant="flat"
          class="border mb-2 overflow-hidden"
          style="border-radius: 8px !important;"
        >
          <div class="pa-3">
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center gap-2">
                <VAvatar size="32" color="primary" variant="tonal">
                  <span class="text-xs font-weight-black text-uppercase">{{ item.movement_type?.charAt(0) }}</span>
                </VAvatar>
                <div>
                  <div class="text-xs font-weight-black text-uppercase tracking-wider leading-tight">
                    {{ item.movement_type }}
                  </div>
                  <div class="text-super-xs text-disabled font-weight-bold">
                    {{ formatDate(item.movement_date) }}
                  </div>
                </div>
              </div>
              <VChip
                :color="item.quantity > 0 ? 'success' : 'error'"
                size="small"
                label
                variant="flat"
                class="font-weight-black"
              >
                {{ item.quantity > 0 ? `+${item.quantity}` : item.quantity }}
              </VChip>
            </div>

            <div class="bg-var-theme-background-light rounded border-s-4 border-primary pa-2 mb-2">
              <div class="d-flex align-center gap-2 mb-1">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">ID</span>
                <span class="text-xs font-weight-black text-primary">{{ item.product_id }}</span>
              </div>
              <div class="text-sm font-weight-black text-high-emphasis truncate text-uppercase leading-tight">
                {{ item.product?.name || 'S/N' }}
              </div>
            </div>

            <div class="d-flex justify-space-between align-center bg-var-theme-background-light px-3 py-2 rounded border-dashed-thin mb-2 text-center">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">S. Ant</span>
                <span class="text-caption font-weight-black text-high-emphasis">{{ item.stock_before }}</span>
              </div>
              <div class="d-flex flex-column align-center">
                <VIcon icon="tabler-arrow-right" class="text-disabled" size="14" />
              </div>
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">S. Fin</span>
                <span class="text-caption font-weight-black text-primary">{{ item.stock_after }}</span>
              </div>
            </div>

            <div class="d-flex align-center justify-space-between pt-1">
              <div class="d-flex align-center gap-2">
                <VAvatar size="24" color="secondary" variant="tonal">
                  <span class="text-super-xs font-weight-bold">{{ item.user?.username?.charAt(0).toUpperCase() }}</span>
                </VAvatar>
                <span class="text-super-xs font-weight-black text-medium-emphasis uppercase">{{ item.user?.username }}</span>
              </div>
              
              <VBtn
                v-if="item.order_id || item.invoice_id"
                variant="tonal"
                color="primary"
                size="x-small"
                class="font-weight-black"
                @click="handleReferenceClick(item.id)"
              >
                VER DETALLE #{{ item.order_id || item.invoice?.invoice_number || item.invoice_id }}
                <VIcon icon="tabler-eye" class="ms-1" size="14" />
              </VBtn>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4 pb-2">
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

.text-xs {
  font-size: 0.75rem !important;
}

.bg-light {
  background-color: #f8fafc !important;
}

.bg-var-theme-background-light {
  background-color: rgba(var(--v-border-color), 0.03);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.leading-none {
  line-height: normal !important;
}

.leading-tight { line-height: 1.2 !important; }

.tracking-wider {
  letter-spacing: 0.05em !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
