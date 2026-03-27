<script setup>
import Swal from "sweetalert2";

const props = defineProps({
  returns: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalReturns: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});
const emit = defineEmits(["update:options", "status", "open-approve-lots"]);
const expanded = ref([]);

const headers = [
  { title: "ID", key: "order_id", sortable: true, width: "100px" },
  { title: "Usuario", key: "client", sortable: true },
  { title: "Identificación", key: "identificacion", sortable: true },
  { title: "Monto", key: "amount_refunded", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Producto", key: "product", sortable: false },
  { title: "Estado", key: "status", sortable: false },
  { title: "Acción", key: "actions", sortable: false },
];

const date = (order) => {
  const time = new Date(order);
  return time.toISOString().split("T")[0];
};

const handleApproveReturn = async (item) => {
  const result = await Swal.fire({
    title: "Aprobar devolución",
    html: `
      <p class="text-start mb-2">Para aprobar debe distribuir las <strong>${item.quantity ?? 0} unidades</strong> devueltas en lotes (stock actual + unidades devueltas).</p>
      <p class="text-start text-body-2 text-medium-emphasis">Se abrirá el modal de ajuste de lotes. La devolución se aprobará al guardar.</p>
    `,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Aceptar",
    cancelButtonText: "Cancelar",
  });

  if (result.isConfirmed) {
    emit("open-approve-lots", item);
  }
};

const handleRejectReturn = async (item) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¿Desea rechazar devolver el producto?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
  });

  if (result.isConfirmed) {
    emit("status", item, "Rejected");
  }
};
</script>

<template>
  <div class="returns-supervisor-container">
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

        <template v-slot:item.product="{ item }">
          <div class="d-flex flex-column py-2">
            <div class="d-flex align-center gap-1 mb-0 pb-0">
              <a
                :href="'/inventory/traceability?q=' + (item.product?.id || item.product_id)"
                target="_blank"
                class="text-decoration-none text-primary font-weight-black text-xs"
              >
                #{{ item.product?.id || item.product_id }}
              </a>
              <span class="text-subtitle-2 font-weight-black text-uppercase leading-tight" style="font-size: 0.85rem !important;">{{ item.product?.name || '—' }}</span>
            </div>
            <div class="text-caption leading-tight d-flex align-center gap-1 mt-0 pt-0">
              <span class="text-disabled" style="font-size: 0.75rem !important;">{{ item.product?.active_ingredient || '—' }}</span>
              <span class="text-disabled" style="font-size: 0.75rem !important;">|</span>
              <span class="text-primary font-weight-bold" style="font-size: 0.75rem !important;">{{ item.product?.laboratory?.name || item.product?.laboratory || '—' }}</span>
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
        <template #item.actions="{ item }">
          <div v-if="item.status == null" class="d-flex align-center gap-2">
            <VBtn
              icon
              size="x-small"
              color="success"
              variant="tonal"
              class="rounded-circle shadow-sm"
              @click="handleApproveReturn(item)"
            >
              <VIcon icon="tabler-circle-check" size="18" />
            </VBtn>

            <VBtn
              icon
              size="x-small"
              color="error"
              variant="tonal"
              class="rounded-circle shadow-sm"
              @click="handleRejectReturn(item)"
            >
              <VIcon icon="tabler-circle-x" size="18" />
            </VBtn>
          </div>
          <span v-else class="text-super-xs font-weight-black text-disabled uppercase">PROCESADA</span>
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
        <div class="text-sm font-weight-black text-disabled uppercase">No hay devoluciones pendientes</div>
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
              <span class="text-xs font-weight-black text-primary">ID: #{{ item.order_id }}</span>
              <VDivider vertical length="12" class="mx-1" />
              <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase">
                {{ item.order.client.identification_type }}{{ item.order.client.identification }}
              </span>
            </div>
            
            <div class="d-flex gap-2" v-if="item.status == null">
              <VBtn 
                icon="tabler-circle-check"
                size="32" 
                color="success" 
                variant="tonal" 
                class="rounded-circle shadow-sm"
                @click="handleApproveReturn(item)"
              >
                <VTooltip activator="parent" location="top">Aprobar</VTooltip>
              </VBtn>
              <VBtn 
                icon="tabler-circle-x"
                size="32" 
                color="error" 
                variant="tonal" 
                class="rounded-circle shadow-sm"
                @click="handleRejectReturn(item)"
              >
                <VTooltip activator="parent" location="top">Rechazar</VTooltip>
              </VBtn>
            </div>
          </div>

          <!-- Producto y Detalles -->
          <div class="bg-light pa-3 rounded-lg mb-3 border border-dashed">
            <div class="d-flex flex-column overflow-hidden">
              <div class="d-flex align-center gap-1 mb-0 pb-0">
                <a
                  :href="'/inventory/traceability?q=' + (item.product?.id || item.product_id)"
                  target="_blank"
                  class="text-decoration-none text-primary font-weight-black text-xs"
                >
                  #{{ item.product?.id || item.product_id }}
                </a>
                <span class="text-xs font-weight-black text high-emphasis uppercase leading-tight truncate">{{ item.product?.name || "—" }}</span>
              </div>
              <div class="text-super-xs leading-tight d-flex align-center gap-1 mt-0 pt-0">
                <span class="text-disabled font-weight-bold uppercase">{{ item.product?.active_ingredient || "—" }}</span>
                <span class="text-disabled">|</span>
                <span class="text-primary font-weight-bold uppercase">{{ item.product?.laboratory?.name || item.product?.laboratory || "—" }}</span>
              </div>
              <div class="d-flex align-center gap-1 mt-1">
                <VIcon icon="tabler-shopping-cart" size="12" class="text-primary" />
                <span class="text-super-xs font-weight-black text-primary uppercase">CANTIDAD: {{ item.quantity }}</span>
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
              <span class="text-super-xs font-weight-bold text-disabled uppercase">TOTAL:</span>
              <span class="text-sm font-weight-black text-success">${{ Number(item.amount_refunded).toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Paginación Móvil Simple (Opcional) -->
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
  background-color: white !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
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
