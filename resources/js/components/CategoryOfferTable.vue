<script setup>
import { computed } from 'vue';
import { useDisplay } from 'vuetify';

const props = defineProps({
  categoriesOffer: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOffer: { type: Number, default: 0 },
  discount: { type: Number, default: 0 },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-offer", "delete-offer"]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true, align: 'start' },
  { title: "CATEGORÍA", key: "category.name", sortable: true, width: "35%" },
  { title: "% DESC.", key: "discount_percentage", sortable: true, align: 'center' },
  { title: "VIGENCIA", key: "validity", sortable: false, width: "25%" },
  { title: "ESTADO", key: "is_active", sortable: true, align: 'center' },
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
</script>

<template>
  <div class="category-offer-container">
    <!-- Desktop View -->
    <VCard class="d-none d-md-block rounded-lg border-0 shadow-sm overflow-hidden">
      <VDataTableServer
        v-model:items-per-page="props.itemsPerPage"
        v-model:page="props.page"
        :headers="headers"
        :items="props.categoriesOffer"
        :items-length="props.totalOffer"
        :loading="props.loading"
        class="premium-table"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">#{{ item.id }}</span>
        </template>

        <template #item.category.name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis uppercase">{{ item.category?.name || 'N/A' }}</span>
            <span class="text-super-xs font-weight-bold text-disabled">ID CATEGORÍA: {{ item.category?.id || 'N/A' }}</span>
          </div>
        </template>

        <template #item.discount_percentage="{ item }">
          <VChip :color="getStatusColor(item.is_active)" size="small" variant="tonal" class="font-weight-black rounded">
            {{ item.discount_percentage }}%
          </VChip>
        </template>

        <template #item.validity="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <div class="d-flex flex-column">
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-calendar-event" size="14" color="success" />
                <span class="text-super-xs font-weight-black text-success">{{ formatDate(item.start_date) }}</span>
              </div>
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-calendar-off" size="14" color="error" />
                <span class="text-super-xs font-weight-black text-error">{{ formatDate(item.end_date) }}</span>
              </div>
            </div>
          </div>
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
                  class="rounded-lg shadow-sm"
                  @click="emit('edit-offer', item)"
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
                  class="rounded-lg shadow-sm"
                  @click="emit('delete-offer', item.id)"
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
        :items="props.categoriesOffer"
        :items-length="props.totalOffer"
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
                    <span class="text-super-xs font-weight-black text-primary uppercase">OFERTA #{{ item.raw.id }}</span>
                    <VChip :color="getStatusColor(item.raw.is_active)" size="x-small" variant="tonal" class="font-weight-black rounded">
                      {{ item.raw.discount_percentage }}% DESC.
                    </VChip>
                  </div>

                  <h3 class="text-sm font-weight-black text-high-emphasis uppercase mb-1">{{ item.raw.category?.name || 'N/A' }}</h3>
                  <p class="text-super-xs font-weight-bold text-disabled uppercase mb-3">ID CAT: {{ item.raw.category?.id || 'N/A' }}</p>

                  <VDivider class="border-dashed my-3" />

                  <div class="d-flex justify-space-between align-center">
                    <div class="d-flex flex-column gap-1">
                      <span class="text-super-xs font-weight-black text-success d-flex align-center gap-1">
                        <VIcon icon="tabler-calendar-event" size="12" /> {{ formatDate(item.raw.start_date) }}
                      </span>
                      <span class="text-super-xs font-weight-black text-error d-flex align-center gap-1">
                        <VIcon icon="tabler-calendar-off" size="12" /> {{ formatDate(item.raw.end_date) }}
                      </span>
                    </div>

                    <div class="d-flex gap-2">
                      <VBtn
                        icon="tabler-edit"
                        variant="tonal"
                        color="primary"
                        size="36"
                        class="rounded-lg shadow-sm"
                        @click="emit('edit-offer', item.raw)"
                      />
                      <VBtn
                        icon="tabler-trash"
                        variant="tonal"
                        color="error"
                        size="36"
                        class="rounded-lg shadow-sm"
                        @click="emit('delete-offer', item.raw.id)"
                      />
                    </div>
                  </div>
                </div>
              </VCard>
            </VCol>
          </VRow>
        </template>
        
        <template #no-data>
          <VCard class="rounded-lg pa-8 text-center border-dashed-2 bg-transparent">
            <VIcon icon="tabler-folder-off" size="48" color="disabled" class="mb-4" />
            <p class="text-sm font-weight-bold text-disabled uppercase">No se encontraron ofertas por categoría</p>
          </VCard>
        </template>

        <template #footer="{ page, pageCount, prevPage, nextPage }">
          <div class="d-flex align-center justify-center gap-4 mt-6">
            <VBtn
              icon="tabler-chevron-left"
              variant="tonal"
              color="primary"
              :disabled="page === 1"
              @click="prevPage"
            />
            <span class="text-xs font-weight-black text-primary uppercase">{{ page }} DE {{ pageCount }}</span>
            <VBtn
              icon="tabler-chevron-right"
              variant="tonal"
              color="primary"
              :disabled="page === pageCount"
              @click="nextPage"
            />
          </div>
        </template>
      </VDataIterator>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: #f8fafc !important;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 0.75rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.05rem !important;
  text-transform: uppercase !important;
}

.premium-table :deep(td) {
  padding-block: 8px !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  color: #334155 !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
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

.border-dashed-2 {
  border: 2px dashed rgba(var(--v-border-color), 0.2) !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
