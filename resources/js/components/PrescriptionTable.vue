<script setup>
import { useDisplay } from 'vuetify';

const props = defineProps({
  prescriptions: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalPrescriptions: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "edit-prescription",
  "delete-prescription",
  "view-prescription",
]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true, align: 'start' },
  { title: "NOMBRE DE OFERTA", key: "name", sortable: true, width: "30%" },
  { title: "% DESC.", key: "discount_percentage", sortable: true, align: 'center' },
  { title: "VIGENCIA", key: "validity", sortable: false, width: "20%" },
  { title: "ESTADO", key: "is_active", sortable: true, align: 'center' },
  { title: "VIGENTE", key: "is_currently_active", sortable: true, align: 'center' },
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

const handleEdit = (prescription) => emit("edit-prescription", prescription);
const handleDelete = (prescription) => emit("delete-prescription", prescription);
const handleView = (prescription) => emit("view-prescription", prescription);
</script>

<template>
  <div class="prescription-offer-container">
    <!-- Desktop View -->
    <VCard class="d-none d-md-block rounded-xl border-0 shadow-sm overflow-hidden">
      <VDataTableServer
        v-model:items-per-page="props.itemsPerPage"
        v-model:page="props.page"
        :headers="headers"
        :items="props.prescriptions"
        :items-length="props.totalPrescriptions"
        :loading="props.loading"
        class="premium-table"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">#{{ item.id }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis uppercase leading-tight">{{ item.name }}</span>
          </div>
        </template>

        <template #item.discount_percentage="{ item }">
          <VChip color="primary" size="small" variant="tonal" class="font-weight-black rounded">
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

        <template #item.is_currently_active="{ item }">
          <VIcon 
            :icon="item.is_currently_active ? 'tabler-circle-check' : 'tabler-circle-x'" 
            :color="item.is_currently_active ? 'success' : 'warning'"
            size="24"
          />
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
                  @click="handleView(item)"
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
                  class="rounded-lg shadow-sm"
                  @click="handleDelete(item)"
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
        :items="props.prescriptions"
        :items-length="props.totalPrescriptions"
        :loading="props.loading"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #default="{ items }">
          <VRow dense>
            <VCol v-for="item in items" :key="item.id" cols="12" class="mb-4">
              <VCard class="premium-card rounded-xl border-0 overflow-hidden shadow-sm flex-row d-flex h-100">
                <div :class="`status-strip bg-${getStatusColor(item.raw.is_active)}`" />
                <div class="pa-4 flex-grow-1">
                  <div class="d-flex justify-space-between align-center mb-3">
                    <span class="text-super-xs font-weight-black text-primary uppercase">OFERTA #{{ item.raw.id }}</span>
                    <VChip :color="getStatusColor(item.raw.is_currently_active)" size="x-small" variant="tonal" class="font-weight-black rounded">
                      {{ item.raw.is_currently_active ? 'EN VIGENCIA' : 'VENCIDA/FUERA' }}
                    </VChip>
                  </div>

                  <h3 class="text-sm font-weight-black text-high-emphasis uppercase mb-1">{{ item.raw.name }}</h3>
                  <div class="d-flex align-center gap-2 mb-3">
                    <VChip color="primary" size="x-small" variant="flat" class="font-weight-black rounded">
                      {{ item.raw.discount_percentage }}% DESC.
                    </VChip>
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
                        class="rounded-xl shadow-sm"
                        @click="handleView(item.raw)"
                      />
                      <VBtn
                        icon="tabler-edit"
                        variant="tonal"
                        color="primary"
                        size="36"
                        class="rounded-xl shadow-sm"
                        @click="handleEdit(item.raw)"
                      />
                      <VBtn
                        icon="tabler-trash"
                        variant="tonal"
                        color="error"
                        size="36"
                        class="rounded-xl shadow-sm"
                        @click="handleDelete(item.raw)"
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
  font-weight: 800 !important;
  letter-spacing: 0.05rem !important;
  text-transform: uppercase !important;
}

.premium-table :deep(td) {
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
.leading-tight { line-height: 1.25 !important; }
</style>
