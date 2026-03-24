<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { useDisplay } from 'vuetify';

const props = defineProps({
  companies: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  totalCompanies: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-offer", "delete-offer", "view-offer"]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true, align: 'start' },
  { title: "EMPRESA", key: "company_name", sortable: true, width: "30%" },
  { title: "% DESC.", key: "discount_percentage", sortable: false, align: 'center' },
  { title: "RANGO VOL.", key: "volume_range", sortable: false, align: 'center' },
  { title: "VIGENCIA", key: "validity", sortable: false, width: "20%" },
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

const getDiscountPercentage = (scales) => {
  if (!scales || scales.length === 0) return "N/A";
  if (scales.length === 1) return `${scales[0].discount_percentage}%`;
  const min = Math.min(...scales.map((s) => s.discount_percentage));
  const max = Math.max(...scales.map((s) => s.discount_percentage));
  return `${min}% - ${max}%`;
};

const getVolumeRange = (scales) => {
  if (!scales || scales.length === 0) return { min: 0, max: 0 };
  const min = Math.min(...scales.map((s) => s.min_amount));
  const max = Math.max(...scales.map((s) => s.max_amount));
  return { min, max };
};
</script>

<template>
  <div class="company-offer-container">
    <!-- Desktop View -->
    <VCard class="d-none d-md-block rounded-lg border-0 shadow-sm overflow-hidden">
      <VDataTableServer
        v-model:items-per-page="props.itemsPerPage"
        v-model:page="props.page"
        :headers="headers"
        :items="props.companies"
        :items-length="props.totalCompanies"
        :loading="props.loading"
        class="premium-table"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">#{{ item.id }}</span>
        </template>

        <template #item.company_name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis uppercase">{{ item.company_name }}</span>
            <span class="text-super-xs font-weight-bold text-disabled">ID EMPRESA: {{ item.company_id }}</span>
          </div>
        </template>

        <template #item.discount_percentage="{ item }">
          <VChip color="primary" size="small" variant="tonal" class="font-weight-black rounded">
            {{ getDiscountPercentage(item.scales) }}
          </VChip>
        </template>

        <template #item.volume_range="{ item }">
          <div class="d-flex flex-column align-center py-1">
            <span class="text-xs font-weight-black text-high-emphasis">{{ formatCurrency(getVolumeRange(item.scales).min, 'USD') }}</span>
            <span class="text-super-xs font-weight-bold text-disabled uppercase">A {{ formatCurrency(getVolumeRange(item.scales).max, 'USD') }}</span>
          </div>
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
            <VTooltip text="Ver Detalles" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon="tabler-eye"
                  variant="tonal"
                  color="info"
                  size="32"
                  class="rounded-lg shadow-sm"
                  @click="emit('view-offer', item)"
                />
              </template>
            </VTooltip>
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
                  @click="emit('delete-offer', item)"
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
        :items="props.companies"
        :items-length="props.totalCompanies"
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
                      {{ getDiscountPercentage(item.raw.scales) }} DESC.
                    </VChip>
                  </div>

                  <h3 class="text-sm font-weight-black text-high-emphasis uppercase mb-1">{{ item.raw.company_name }}</h3>
                  <div class="d-flex align-center gap-2 mb-3">
                    <span class="text-super-xs font-weight-bold text-disabled uppercase">ID EMP: {{ item.raw.company_id }}</span>
                    <VDivider vertical length="12" class="mx-1" />
                    <span class="text-super-xs font-weight-black text-primary uppercase">{{ formatCurrency(getVolumeRange(item.raw.scales).min, 'USD') }}+</span>
                  </div>

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
                        icon="tabler-eye"
                        variant="tonal"
                        color="info"
                        size="36"
                        class="rounded-lg shadow-sm"
                        @click="emit('view-offer', item.raw)"
                      />
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
                        @click="emit('delete-offer', item.raw)"
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
.premium-table :deep(th) {
  background-color: #f8fafc !important;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 0.75rem !important;
  font-weight: 950 !important;
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

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
