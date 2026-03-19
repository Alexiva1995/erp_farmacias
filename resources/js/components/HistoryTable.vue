<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  histories: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalHistories: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "show-detailHistory"]);

const { mobile } = useDisplay();

const headers = [
  { title: "Fecha", key: "invoice_date", sortable: true },
  { title: "# Factura", key: "invoice_number", sortable: true },
  { title: "Razón Social", key: "business_name", sortable: true, width: "30%" },
  { title: "ID Fiscal", key: "id", sortable: true },
  { title: "Monto Neto", key: "exempt_amount", sortable: true, align: "end" },
  { title: "IVA", key: "iva_amount", sortable: true, align: "end" },
  { title: "Total", key: "total_amount", sortable: true, align: "end" },
  { title: "Acción", key: "actions", sortable: false, align: "center" },
];

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
};

const getAvatarColor = (id) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  return colors[id % colors.length];
};

const getInitials = (name) => {
  if (!name) return "F";
  return name.split(" ").map(n => n[0]).join("").substring(0, 2).toUpperCase();
};
</script>

<template>
  <div>
    <!-- Vista de Escritorio (Desktop Table) -->
    <VCard v-if="!mobile" class="rounded-xl border-0 shadow-sm overflow-hidden">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.histories"
        :items-length="props.totalHistories"
        :loading="props.loading"
        class="text-no-wrap premium-table"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.invoice_date="{ item }">
          <span class="text-xs font-weight-medium">{{ item.invoice_date }}</span>
        </template>

        <template #item.invoice_number="{ item }">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-hash" size="14" color="disabled" />
            <span class="text-xs font-weight-black text-primary">{{ item.invoice_number }}</span>
          </div>
        </template>

        <template #item.business_name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              :color="getAvatarColor(item.id)"
              size="32"
              variant="tonal"
              class="rounded-lg"
            >
              <span class="text-xs font-weight-black">{{ getInitials(item.business_name) }}</span>
            </VAvatar>
            <div class="d-flex flex-column truncate-text" style="max-width: 250px;">
              <span class="text-sm font-weight-bold text-high-emphasis text-capitalize truncate">
                {{ item.business_name }}
              </span>
              <span class="text-super-xs text-disabled truncate">
                {{ item.address || 'Sin dirección' }}
              </span>
            </div>
          </div>
        </template>

        <template #item.id="{ item }">
          <VChip size="x-small" variant="tonal" color="secondary" class="font-weight-black rounded">
            {{ item.id }}
          </VChip>
        </template>

        <template #item.exempt_amount="{ item }">
          <span class="text-xs font-weight-medium">{{ formatCurrency(item.exempt_amount) }}</span>
        </template>

        <template #item.iva_amount="{ item }">
          <span class="text-xs font-weight-medium text-error">{{ formatCurrency(item.iva_amount) }}</span>
        </template>

        <template #item.total_amount="{ item }">
          <span class="text-sm font-weight-black text-success">{{ formatCurrency(item.total_amount) }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center">
            <VBtn
              icon
              variant="text"
              size="32"
              color="info"
              class="rounded-lg"
              @click="emit('show-detailHistory', item)"
            >
              <VIcon icon="tabler-eye" size="18" />
              <VTooltip activator="parent" location="top">Ver Detalle Fiscal</VTooltip>
            </VBtn>
          </div>
        </template>

        <template #bottom>
          <VDivider class="opacity-10" />
          <div class="d-flex align-center justify-space-between pa-4">
            <span class="text-super-xs text-disabled font-weight-bold uppercase">
              Total: {{ props.totalHistories }} registros
            </span>
            <VPagination
              :model-value="props.page"
              :length="Math.ceil(props.totalHistories / props.itemsPerPage)"
              size="small"
              class="premium-pagination"
              @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
            />
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil (Premium Cards) -->
    <div v-else class="d-flex flex-column gap-4">
      <div v-if="props.loading" class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <template v-else-if="props.histories.length > 0">
        <VCard
          v-for="item in props.histories"
          :key="item.id"
          class="rounded-xl border-0 shadow-md premium-card overflow-hidden"
          @click="emit('show-detailHistory', item)"
        >
          <div class="premium-card-decoration"></div>
          
          <VCardText class="pa-5">
            <!-- Cabecera: Factura y Monto -->
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-2">
                <VAvatar
                  :color="getAvatarColor(item.id)"
                  size="38"
                  variant="tonal"
                  class="rounded-lg shadow-sm"
                >
                  <VIcon icon="tabler-receipt-2" size="20" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-h6 font-weight-black leading-none mb-1 text-primary">
                    #{{ item.invoice_number }}
                  </span>
                  <span class="text-super-xs font-weight-black text-disabled uppercase">Factura Fiscal</span>
                </div>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-h6 font-weight-black text-success leading-none mb-1">
                  {{ formatCurrency(item.total_amount) }}
                </span>
                <span class="text-super-xs font-weight-black text-disabled uppercase">Monto Total</span>
              </div>
            </div>

            <VDivider class="mb-4 opacity-10" />

            <!-- Razón Social -->
            <div class="mb-4">
              <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Razón Social y Dirección</span>
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-bold text-high-emphasis text-capitalize leading-tight mb-1">
                  {{ item.business_name }}
                </span>
                <span class="text-xs text-disabled truncate-text leading-tight">
                  {{ item.address || 'Sin dirección registrada' }}
                </span>
              </div>
            </div>

            <!-- Detalles Rápidos -->
            <div class="d-flex gap-3 mb-4">
              <div class="premium-stat-box flex-grow-1 pa-2 rounded-lg bg-surface-variant-opacity-2">
                <span class="text-super-xs text-disabled font-weight-bold uppercase d-block">ID Fiscal</span>
                <span class="text-xs font-weight-black">#{{ item.id }}</span>
              </div>
              <div class="premium-stat-box flex-grow-1 pa-2 rounded-lg bg-surface-variant-opacity-2">
                <span class="text-super-xs text-disabled font-weight-bold uppercase d-block">Fecha</span>
                <span class="text-xs font-weight-black">{{ item.invoice_date }}</span>
              </div>
            </div>

            <!-- Acciones -->
            <div class="d-flex gap-2 mt-2">
              <VBtn
                variant="flat"
                color="primary"
                block
                class="rounded-lg text-xs font-weight-black h-40 shadow-sm"
              >
                <VIcon start icon="tabler-eye" size="18" />
                VER DETALLES
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-4 pb-4">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalHistories / props.itemsPerPage)"
            size="small"
            rounded="circle"
            class="premium-pagination"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>

      <VAlert v-else type="info" variant="tonal" class="rounded-xl">
        No se encontraron registros fiscales en este rango.
      </VAlert>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header) {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.premium-table :deep(.v-data-table-header th) {
  height: 48px !important;
  font-size: 0.65rem !important;
  font-weight: 900 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05em !important;
  color: rgba(var(--v-theme-on-surface), 0.5) !important;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.truncate-text {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.premium-card {
  position: relative;
  transition: all 0.2s ease;
  cursor: pointer;
}

.premium-card:active {
  transform: scale(0.98);
}

.premium-card-decoration {
  position: absolute;
  top: 0;
  right: 0;
  width: 100px;
  height: 100px;
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.06) 0%, transparent 100%);
  border-radius: 0 0 0 100%;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.h-40 {
  height: 40px !important;
}

.premium-pagination :deep(.v-btn) {
  background-color: white !important;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
  box-shadow: 0 2px 4px rgba(0,0,0,0.02) !important;
}

.premium-pagination :deep(.v-pagination__item--active .v-btn) {
  background: rgb(var(--v-theme-primary)) !important;
  color: white !important;
  border: 0 !important;
}
</style>
