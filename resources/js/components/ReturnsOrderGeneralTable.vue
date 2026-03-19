<script setup>
const props = defineProps({
  returns: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalReturns: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});
const emit = defineEmits(["update:options"]);
const expanded = ref([]);

const headers = [
  { title: "ID", key: "id", sortable: true, width: "100px" },
  { title: "N° Orden", key: "order_id", sortable: true, width: "100px" },
  { title: "Usuario", key: "client", sortable: true },
  { title: "Identificación", key: "identificacion", sortable: true },
  { title: "Monto", key: "amount_refunded", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Producto", key: "product", sortable: false },
];

const date = (order) => {
  const time = new Date(order);
  return time.toISOString().split("T")[0];
};

</script>

<template>
  <div class="returns-general-container">
    <!-- Vista de Escritorio (Tabla Premium) -->
    <VCard class="d-none d-md-block elevation-1 border-0 rounded-lg overflow-hidden">
      <VDataTableServer
        v-model:expanded="expanded"
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.returns"
        :items-length="props.totalReturns"
        :loading="props.loading"
        item-key="id"
        class="text-no-wrap premium-table"
        fixed-header
        height="auto"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <span class="font-weight-black text-secondary">#{{ item.id }}</span>
        </template>

        <template #item.order_id="{ item }">
          <span class="font-weight-black text-primary">#{{ item.order_id }}</span>
        </template>

        <template #item.client="{ item }">
          <div class="d-flex align-center gap-2">
            <VAvatar size="24" color="primary" variant="tonal" class="rounded">
              <VIcon icon="tabler-user" size="14" />
            </VAvatar>
            <span class="font-weight-bold truncate" style="max-inline-size: 150px;">
              {{ item.order?.seller?.username ?? "—" }}
            </span>
          </div>
        </template>

        <template #item.identificacion="{ item }">
          <span class="text-xs font-weight-bold text-medium-emphasis uppercase">
            {{ item.order.client.identification_type }}{{ item.order.client.identification }}
          </span>
        </template>

        <template #item.amount_refunded="{ item }">
          <span class="font-weight-black text-success">
            ${{ Number(item.amount_refunded).toFixed(2) }}
          </span>
        </template>

        <template #item.date="{ item }">
          <div class="d-flex align-center gap-1">
            <VIcon icon="tabler-calendar" size="14" class="text-disabled" />
            <span class="text-xs font-weight-bold">{{ date(item.return_date) }}</span>
          </div>
        </template>

        <template #item.product="{ item }">
          <div class="d-flex align-center gap-2">
            <VAvatar size="32" color="info" variant="tonal" class="rounded">
              <VIcon icon="tabler-package" size="18" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-sm font-weight-black uppercase leading-tight truncate" style="max-inline-size: 250px;">
                {{ item.product.name }}
              </span>
              <span v-if="item.product?.laboratory?.name" class="text-super-xs font-weight-bold text-disabled uppercase">
                {{ item.product.laboratory.name }}
              </span>
            </div>
          </div>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            class="font-weight-black rounded uppercase"
            :color="
              item.status == null
                ? 'warning'
                : item.status === 'Approved'
                ? 'success'
                : 'error'
            "
          >
            <template #prepend>
              <VIcon
                size="14"
                start
                :icon="
                  item.status == null
                    ? 'tabler-clock-filled'
                    : item.status === 'Approved'
                    ? 'tabler-circle-check-filled'
                    : 'tabler-circle-x-filled'
                "
              />
            </template>
            {{ item.status == null ? 'Pendiente' : item.status === 'Approved' ? 'Aprobado' : 'Rechazado' }}
          </VChip>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil (Premium Cards) -->
    <div class="d-block d-md-none mt-4">
      <div v-if="props.loading" class="d-flex justify-center py-8">
        <VProgressCircular indeterminate color="primary" />
      </div>
      
      <div v-else-if="props.returns.length === 0" class="text-center py-8 bg-white rounded-lg border border-dashed">
        <VIcon icon="tabler-trash-x" size="48" color="disabled" class="mb-2" />
        <div class="text-sm font-weight-black text-disabled uppercase">No hay registros de devoluciones</div>
      </div>

      <div v-for="item in props.returns" :key="item.id" class="premium-card mb-4 bg-white rounded-lg elevation-2 overflow-hidden border-0">
        <!-- Badge Lateral de Estado -->
        <div 
          class="status-strip" 
          :class="{
            'bg-warning': item.status == null,
            'bg-success': item.status === 'Approved',
            'bg-error': item.status === 'Rejected'
          }"
        ></div>

        <div class="pa-4">
          <!-- Cabecera Tarjeta -->
          <div class="d-flex justify-space-between align-center mb-3">
            <div class="d-flex align-center gap-2">
              <span class="text-xs font-weight-black text-secondary">#{{ item.id }}</span>
              <VDivider vertical length="12" class="mx-1" />
              <span class="text-xs font-weight-black text-primary">ORDEN: #{{ item.order_id }}</span>
            </div>
            
            <VChip
              size="x-small"
              variant="tonal"
              class="font-weight-black rounded-sm uppercase"
              :color="item.status == null ? 'warning' : item.status === 'Approved' ? 'success' : 'error'"
            >
              {{ item.status == null ? 'Pendiente' : item.status === 'Approved' ? 'Aprobado' : 'Rechazado' }}
            </VChip>
          </div>

          <!-- Producto y Detalles -->
          <div class="bg-light pa-3 rounded-lg mb-3 border border-dashed">
            <div class="d-flex align-start gap-3">
              <VAvatar size="40" color="primary" variant="tonal" class="rounded">
                <VIcon icon="tabler-package" size="22" />
              </VAvatar>
              <div class="d-flex flex-column overflow-hidden">
                <span class="text-xs font-weight-black text-high-emphasis uppercase leading-tight truncate">
                  {{ item.product.name }}
                </span>
                <span class="text-super-xs font-weight-bold text-disabled uppercase">
                  {{ item.product?.laboratory?.name ?? 'SIN LABORATORIO' }}
                </span>
                <div class="d-flex align-center justify-space-between mt-1">
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-shopping-cart" size="12" class="text-primary" />
                    <span class="text-super-xs font-weight-black text-primary uppercase">CANT: {{ item.quantity }}</span>
                  </div>
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-user" size="12" class="text-disabled" />
                    <span class="text-super-xs font-weight-bold text-disabled uppercase">{{ item.order?.seller?.username }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer Tarjeta -->
          <div class="d-flex justify-space-between align-center pt-1">
            <div class="d-flex align-center gap-1">
              <VIcon icon="tabler-calendar" size="14" class="text-disabled" />
              <span class="text-super-xs font-weight-bold text-disabled">{{ date(item.return_date) }}</span>
            </div>
            <div class="d-flex align-center gap-1 text-end">
              <span class="text-sm font-weight-black text-success">${{ Number(item.amount_refunded).toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Paginación Móvil -->
      <div class="mt-4 pb-6">
        <VPagination
          v-if="props.totalReturns > props.itemsPerPage"
          :model-value="props.page"
          :length="Math.ceil(props.totalReturns / props.itemsPerPage)"
          density="compact"
          color="primary"
          @update:model-value="(val) => emit('update:options', { page: val, itemsPerPage: props.itemsPerPage, sortBy: [] })"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: #f8fafc !important;
  font-size: 0.75rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.05rem !important;
  text-transform: uppercase !important;
}

.premium-table :deep(td) {
  font-size: 0.8rem !important;
}

.premium-card {
  position: relative;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.status-strip {
  position: absolute;
  inline-size: 4px;
  inset-block: 0;
  inset-inline-start: 0;
}

.bg-light {
  background-color: #f1f5f9 !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25 !important;
}

.uppercase {
  text-transform: uppercase;
}
</style>
