<script setup>
import { useDisplay } from 'vuetify';

const props = defineProps({
  offers: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  total: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-offer", "delete-offer"]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true, align: 'start' },
  { title: "MESES PARA EXPIRAR", key: "months_to_expiration", sortable: true, width: "30%" },
  { title: "% DESC.", key: "discount_percentage", sortable: true, align: 'center' },
  { title: "ESTADO", key: "is_active", sortable: true, align: 'center' },
  { title: "VENTAS", key: "sales_count", sortable: false, align: 'center', width: "120px" },
  { title: "CREADO EL", key: "created_at", sortable: true, width: "20%" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center" },
];

const getStatusColor = (isActive) => isActive ? 'success' : 'error';
const getStatusText = (isActive) => isActive ? 'ACTIVA' : 'INACTIVA';

const formatDate = (dateString) => {
  if (!dateString) return 'S/F';
  return new Date(dateString).toLocaleDateString('es-ES', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
};

const handleEdit = (offer) => emit("edit-offer", offer);
const handleDelete = (id) => emit("delete-offer", id);
</script>

<template>
  <div class="expiration-offer-container">
    <!-- Desktop View -->
    <VCard class="d-none d-md-block rounded-lg border-0 shadow-sm overflow-hidden">
      <VDataTableServer
        v-model:items-per-page="props.itemsPerPage"
        v-model:page="props.page"
        :headers="headers"
        :items="props.offers"
        :items-length="props.total"
        :loading="props.loading"
        class="premium-table"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <template #item.months_to_expiration="{ item }">
          <div class="d-flex align-center gap-2 py-2">
            <VIcon icon="tabler-hourglass-high" size="18" class="text-warning" />
            <span class="text-sm font-weight-black text-high-emphasis uppercase">{{ item.months_to_expiration }} MESES</span>
          </div>
        </template>

        <template #item.discount_percentage="{ item }">
          <VChip color="primary" size="small" variant="tonal" class="font-weight-black rounded">
            {{ item.discount_percentage }}%
          </VChip>
        </template>

        <template #item.is_active="{ item }">
          <VChip
            :color="getStatusColor(item.is_active)"
            size="x-small"
            variant="flat"
            class="font-weight-black px-2"
          >
            {{ getStatusText(item.is_active) }}
          </VChip>
        </template>

        <template #item.sales_count="{ item }">
          <div class="d-flex justify-center">
            <VChip
              size="small"
              color="info"
              variant="tonal"
              class="font-weight-black rounded"
              prepend-icon="tabler-shopping-cart"
            >
              {{ item.sales_count ?? 0 }} uds.
            </VChip>
          </div>
        </template>

        <template #item.created_at="{ item }">
          <span class="text-super-xs font-weight-black text-disabled uppercase">
            {{ formatDate(item.created_at) }}
          </span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <VTooltip text="Editar Oferta" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon="tabler-edit"
                  variant="tonal"
                  color="primary"
                  size="32"
                  class="rounded-circle shadow-sm"
                  @click="handleEdit(item)"
                />
              </template>
            </VTooltip>
            <VTooltip text="Eliminar Oferta" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon="tabler-trash"
                  variant="tonal"
                  color="error"
                  size="32"
                  class="rounded-circle shadow-sm"
                  @click="handleDelete(item.id)"
                />
              </template>
            </VTooltip>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Mobile View -->
    <div class="d-md-none">
      <VDataIterator
        :items="props.offers"
        :items-length="props.total"
        :loading="props.loading"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #default="{ items }">
          <VRow dense>
            <VCol v-for="item in items" :key="item.id" cols="12" class="mb-4">
              <VCard class="premium-card rounded-lg border-0 overflow-hidden shadow-sm flex-row d-flex h-100">
                <div :class="`status-strip bg-${getStatusColor(item.raw.is_active)}`" />
                <div class="pa-4 flex-grow-1">
                  <div class="d-flex justify-space-between align-center mb-3">
                    <div class="d-flex align-center gap-1">
                      <span class="text-primary font-weight-black text-xs">{{ item.raw.id }}</span>
                      <span class="text-disabled mx-1">|</span>
                      <h3 class="text-sm font-weight-black text-high-emphasis uppercase mb-0">
                        DENTRO DE {{ item.raw.months_to_expiration }} MESES
                      </h3>
                    </div>
                    <VChip :color="getStatusColor(item.raw.is_active)" size="x-small" variant="tonal" class="font-weight-black rounded">
                      {{ getStatusText(item.raw.is_active) }}
                    </VChip>
                  </div>
                  <div class="d-flex align-center gap-2 mb-3">
                    <VChip color="primary" size="x-small" variant="flat" class="font-weight-black rounded">
                      {{ item.raw.discount_percentage }}% DESC.
                    </VChip>
                    <VChip color="info" size="x-small" variant="tonal" class="font-weight-black rounded" prepend-icon="tabler-shopping-cart">
                      {{ item.raw.sales_count ?? 0 }} uds. vendidas
                    </VChip>
                  </div>

                  <VDivider class="border-dashed my-3" />

                  <div class="d-flex justify-space-between align-center">
                    <span class="text-super-xs font-weight-black text-disabled uppercase">
                      CREADO: {{ formatDate(item.raw.created_at) }}
                    </span>

                    <div class="d-flex gap-2">
                      <VBtn
                        icon="tabler-edit"
                        variant="tonal"
                        color="primary"
                        size="36"
                        class="rounded-circle shadow-sm"
                        @click="handleEdit(item.raw)"
                      />
                      <VBtn
                        icon="tabler-trash"
                        variant="tonal"
                        color="error"
                        size="36"
                        class="rounded-circle shadow-sm"
                        @click="handleDelete(item.raw.id)"
                      />
                    </div>
                  </div>
                </div>
              </VCard>
            </VCol>
          </VRow>
        </template>
      </VDataIterator>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(thead th) {
  background-color: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.05rem !important;
  text-transform: uppercase !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.1) !important;
}

.premium-table :deep(td) {
  padding-block: 12px !important;
}

.status-strip {
  width: 6px;
  height: 100%;
}

.premium-card {
  transition: all 0.3s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.4;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
