<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  packs: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalPacks: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-pack", "delete-pack", "view-pack", "toggle-status"]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true, width: "80px" },
  { title: "NOMBRE DEL PACK", key: "name", sortable: true },
  { title: "PRODUCTOS", key: "products_count", sortable: true, width: "120px" },
  { title: "PRECIO TOTAL", key: "total_price", sortable: true, width: "140px" },
  { title: "CANT. MÁX", key: "max_quantity", sortable: true, width: "120px" },
  { title: "FECHA LÍMITE", key: "max_sale_date", sortable: true, width: "140px" },
  { title: "ESTADO", key: "is_active", sortable: true, width: "100px" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center", width: "160px" },
];

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'USD'
  }).format(amount || 0);
};

const handleEdit = (pack) => {
  emit('edit-pack', pack);
};

const handleDelete = (pack) => {
  emit('delete-pack', pack);
};

const handleView = (pack) => {
  emit('view-pack', pack);
};

const handleToggleStatus = (pack) => {
  emit('toggle-status', pack);
};
</script>

<template>
  <div class="pack-table-container">
    <template v-if="mobile">
      <VDataIterator
        :items="props.packs"
        :items-per-page="props.itemsPerPage"
        :loading="props.loading"
      >
        <template v-slot:default="{ items }">
          <div class="pa-2 d-flex flex-column gap-3">
            <VCard
              v-for="item in items"
              :key="item.raw.id"
              variant="flat"
              border
              class="rounded-lg pa-3"
            >
              <div class="d-flex justify-space-between align-start mb-2">
                <div class="d-flex align-center gap-1 mb-1">
                  <span class="text-primary font-weight-black text-xs">{{ item.raw.id }}</span>
                  <span class="text-disabled mx-1">|</span>
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight mb-0">
                    {{ item.raw.name.toUpperCase() }}
                  </h3>
                </div>
                <VSwitch
                  :model-value="item.raw.is_active"
                  density="compact"
                  color="success"
                  hide-details
                  @update:model-value="handleToggleStatus(item.raw)"
                />
              </div>

              <div class="d-flex flex-wrap gap-2 mb-3">
                <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                  {{ Object.keys(item.raw.pack_config || {}).length }} Prods
                </VChip>
                <VChip v-if="item.raw.max_sale_date" size="x-small" color="info" variant="tonal" class="font-weight-bold">
                  <VIcon start size="12">tabler-calendar-event</VIcon>
                  {{ formatDate(item.raw.max_sale_date) }}
                </VChip>
              </div>

              <VDivider class="border-dashed mb-3" />

              <div class="d-flex justify-space-between align-center">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-medium-emphasis uppercase font-weight-bold mb-n1">Precio del Pack</span>
                  <span class="text-h6 font-weight-950 text-success">
                    {{ formatCurrency(item.raw.total_price) }}
                  </span>
                </div>
                <div class="d-flex gap-1">
                  <VBtn icon="tabler-eye" variant="tonal" color="info" size="32" @click="handleView(item.raw)" />
                  <VBtn icon="tabler-edit" variant="tonal" color="warning" size="32" @click="handleEdit(item.raw)" />
                  <VBtn icon="tabler-trash" variant="tonal" color="error" size="32" @click="handleDelete(item.raw)" />
                </div>
              </div>
            </VCard>
          </div>
        </template>
        <template v-slot:no-data>
          <div class="pa-8 text-center text-medium-emphasis uppercase font-weight-bold text-xs">
            No hay packs configurados
          </div>
        </template>
      </VDataIterator>

      <!-- Paginación Móvil -->
      <div class="pa-4 border-t d-flex justify-center">
        <VPagination
          v-model="props.page"
          :length="Math.ceil(props.totalPacks / props.itemsPerPage)"
          size="small"
          total-visible="5"
          @update:model-value="(p) => emit('update:options', { ...props, page: p })"
        />
      </div>
    </template>

    <VCard class="d-none d-md-block rounded-lg border-0 shadow-sm overflow-hidden">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.packs"
        :items-length="props.totalPacks"
        :loading="props.loading"
        class="text-no-wrap premium-table"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <span class="text-primary font-weight-black">{{ item.id }}</span>
        </template>

        <template #item.name="{ item }">
          <span class="text-subtitle-2 font-weight-black text-high-emphasis text-uppercase">{{ item.name }}</span>
        </template>

        <template #item.products_count="{ item }">
          <VChip variant="tonal" color="info" size="small" class="font-weight-bold">
            <VIcon start size="14">tabler-package</VIcon>
            {{ Object.keys(item.pack_config || {}).length }}
          </VChip>
        </template>

        <template #item.total_price="{ item }">
          <span class="font-weight-black text-primary text-subtitle-2">
            {{ formatCurrency(item.total_price) }}
          </span>
        </template>

        <template #item.max_quantity="{ item }">
          <span v-if="item.max_quantity" class="font-weight-medium">
            {{ item.max_quantity }}
          </span>
          <span v-else class="text-disabled italic font-weight-medium">Ilimitado</span>
        </template>

        <template #item.max_sale_date="{ item }">
          <div class="d-flex align-center gap-1 text-medium-emphasis">
            <VIcon icon="tabler-calendar" size="16" />
            <span class="font-weight-medium">{{ formatDate(item.max_sale_date) }}</span>
          </div>
        </template>

        <template #item.is_active="{ item }">
          <VSwitch
            :model-value="item.is_active"
            density="compact"
            color="success"
            hide-details
            class="d-inline-flex"
            @update:model-value="handleToggleStatus(item)"
          />
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <VBtn
              icon="tabler-eye"
              variant="tonal"
              size="32"
              color="info"
              class="rounded-lg shadow-sm"
              @click="handleView(item)"
            />
            <VBtn
              icon="tabler-edit"
              variant="tonal"
              size="32"
              color="warning"
              class="rounded-lg shadow-sm"
              @click="handleEdit(item)"
            />
            <VBtn
              icon="tabler-trash"
              variant="tonal"
              size="32"
              color="error"
              class="rounded-lg shadow-sm"
              @click="handleDelete(item)"
            />
          </div>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>

<style scoped>
.premium-table :deep(thead th) {
  background-color: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px;
  text-transform: uppercase !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.1) !important;
}

.premium-table :deep(td) {
  padding-block: 16px !important;
}

.text-super-xs {
  font-size: 0.68rem !important;
  line-height: normal;
}

.font-weight-950 { font-weight: 950 !important; }
.leading-tight { line-height: 1.25 !important; }

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 5%) !important;
}

.border-dashed {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}
</style>
