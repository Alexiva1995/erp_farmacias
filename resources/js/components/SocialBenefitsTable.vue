<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  employees: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "fire-employee",
  "download-settlement",
  "upload-signed",
  "download-signed",
]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: false, width: "80px" },
  { title: "EMPLEADO", key: "full_name", sortable: false, width: "30%" },
  { title: "IDENTIFICACIÓN", key: "identification", sortable: false, width: "15%" },
  { title: "CORREO ELECTRÓNICO", key: "email", sortable: false, width: "20%" },
  { title: "ESTADO LIQUIDACIÓN", key: "settlement_date", sortable: false, align: "center", width: "15%" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center", width: "150px" },
];

const formatDate = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleDateString("es-VE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};

const getStatusColor = (settlementDate) => settlementDate ? "success" : "secondary";
const getStatusText = (settlementDate) => settlementDate ? "LIQUIDADO" : "PENDIENTE";
</script>

<template>
  <div class="social-benefits-container">
    <!-- Vista de Escritorio -->
    <VCard class="border shadow-sm overflow-hidden rounded-lg">
      <VDataTableServer
        :headers="headers"
        :items-per-page="props.itemsPerPage"
        :items="props.employees"
        :items-length="props.total"
        :loading="props.loading"
        :page="props.page"
        class="premium-table text-no-wrap"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <template #item.full_name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis uppercase">{{ item.name }} {{ item.last_name }}</span>
            <span class="text-super-xs font-weight-bold text-disabled uppercase">{{ item.position?.name || 'Cargo no especificado' }}</span>
          </div>
        </template>

        <template #item.identification="{ item }">
          <span class="text-sm font-weight-black text-high-emphasis">{{ item.identification }}</span>
        </template>

        <template #item.email="{ item }">
          <span class="text-sm font-weight-black text-medium-emphasis">{{ item.email }}</span>
        </template>

        <template #item.settlement_date="{ item }">
          <div class="d-flex flex-column align-center">
            <VChip
              :color="getStatusColor(item.settlement_date)"
              size="x-small"
              variant="flat"
              class="font-weight-black px-2 rounded"
            >
              {{ getStatusText(item.settlement_date) }}
            </VChip>
            <span v-if="item.settlement_date" class="text-super-xs font-weight-bold text-disabled mt-1 uppercase">
              {{ formatDate(item.settlement_date) }}
            </span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <template v-if="!item.settlement_date">
              <VTooltip text="Procesar Liquidación" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon="tabler-file-analytics"
                    variant="tonal"
                    color="primary"
                    size="32"
                    class="rounded-circle shadow-sm"
                    @click="emit('fire-employee', item)"
                  />
                </template>
              </VTooltip>
            </template>

            <template v-else>
              <VTooltip text="Descargar PDF" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon="tabler-file-type-pdf"
                    variant="tonal"
                    color="primary"
                    size="32"
                    class="rounded-circle shadow-sm"
                    @click="emit('download-settlement', item)"
                  />
                </template>
              </VTooltip>

              <VTooltip text="Subir Firmado" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon="tabler-cloud-upload"
                    variant="tonal"
                    color="warning"
                    size="32"
                    class="rounded-circle shadow-sm"
                    @click="emit('upload-signed', item)"
                  />
                </template>
              </VTooltip>

              <VTooltip v-if="item.signed_document_path" text="Descargar Firmado" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon="tabler-download"
                    variant="tonal"
                    color="success"
                    size="32"
                    class="rounded-circle shadow-sm"
                    @click="emit('download-signed', item)"
                  />
                </template>
              </VTooltip>
            </template>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil -->
    <div class="d-md-none">
      <VDataIterator
        :items="props.employees"
        :items-length="props.total"
        :loading="props.loading"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #default="{ items }">
          <VRow dense>
            <VCol v-for="item in items" :key="item.id" cols="12" class="mb-2">
              <VCard class="premium-card rounded-lg border-0 overflow-hidden shadow-sm flex-row d-flex h-100">
                <div :class="`status-strip bg-${getStatusColor(item.raw.settlement_date)}`" />
                <div class="pa-3 flex-grow-1">
                  <div class="d-flex justify-space-between align-center mb-2">
                    <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                      <span class="text-primary text-xs">{{ item.raw.id }}</span>
                      <span class="mx-1 text-disabled">|</span>
                      {{ item.raw.name }} {{ item.raw.last_name }}
                    </h3>
                    <VChip :color="getStatusColor(item.raw.settlement_date)" size="x-small" variant="tonal" class="font-weight-black rounded">
                      {{ getStatusText(item.raw.settlement_date) }}
                    </VChip>
                  </div>
                  <div class="d-flex align-center gap-2 mb-2">
                    <VIcon icon="tabler-id" size="14" class="text-disabled" />
                    <span class="text-xs font-weight-bold text-medium-emphasis">{{ item.raw.identification }}</span>
                  </div>

                  <VDivider class="border-opacity-10 my-2" />

                  <div class="d-flex justify-space-between align-center">
                    <div class="d-flex flex-column">
                      <span class="text-super-xs text-disabled uppercase font-weight-black">Liquidación</span>
                      <span class="text-xs font-weight-black text-high-emphasis uppercase">{{ formatDate(item.raw.settlement_date) }}</span>
                    </div>

                    <div class="d-flex gap-1">
                        <VBtn
                          v-if="!item.raw.settlement_date"
                          icon="tabler-file-analytics"
                          variant="tonal"
                          color="primary"
                          size="32"
                          class="rounded-circle shadow-sm"
                          @click="emit('fire-employee', item.raw)"
                        />
                        <template v-else>
                          <VBtn
                            icon="tabler-file-type-pdf"
                            variant="tonal"
                            color="primary"
                            size="32"
                            class="rounded-circle shadow-sm"
                            @click="emit('download-settlement', item.raw)"
                          />
                          <VBtn
                            icon="tabler-cloud-upload"
                            variant="tonal"
                            color="warning"
                            size="32"
                            class="rounded-circle shadow-sm"
                            @click="emit('upload-signed', item.raw)"
                          />
                        </template>
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
  color: #334155 !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
}

.status-strip {
  width: 4px;
  height: 100%;
}

.premium-card {
  transition: all 0.3s ease;
  background: rgb(var(--v-theme-surface));
}

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.shadow-sm {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

.leading-tight {
  line-height: 1.25 !important;
}
</style>
