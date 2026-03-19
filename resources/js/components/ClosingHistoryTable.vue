<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  closing: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalClosing: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "print-cash"]);
const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Acción", key: "actions", sortable: false, align: "end" },
];

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  const date = new Date(dateStr);
  return date.toLocaleDateString("es-ES", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};
</script>

<template>
  <div class="closing-history-container">
    <!-- Vista de Escritorio (Tabla) -->
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.closing"
      :items-length="props.totalClosing"
      :loading="props.loading"
      class="premium-table"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-bold text-primary">#{{ item.id }}</span>
      </template>

      <template #item.date="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-2 font-weight-medium">{{ formatDate(item.closing_date) }}</span>
          <span class="text-xs text-disabled">{{ item.created_at ? new Date(item.created_at).toLocaleTimeString() : '' }}</span>
        </div>
      </template>

      <template #item.actions="{ item }">
        <VTooltip text="Imprimir Comprobante">
          <template #activator="{ props: tooltip }">
            <VBtn
              v-bind="tooltip"
              icon="tabler-printer"
              variant="tonal"
              color="primary"
              size="32"
              class="rounded-lg"
              @click="emit('print-cash', item)"
            />
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil (Cards) -->
    <div v-else class="mobile-cards-container pa-4">
      <VRow>
        <VCol v-for="item in props.closing" :key="item.id" cols="12">
          <VCard class="rounded-xl border shadow-sm history-card" variant="flat">
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-start mb-3">
                <div class="d-flex align-center gap-3">
                  <VAvatar color="primary" variant="tonal" rounded size="40" class="rounded-lg">
                    <VIcon icon="tabler-hash" size="20" />
                  </VAvatar>
                  <div>
                    <div class="text-h6 font-weight-black text-primary leading-none">#{{ item.id }}</div>
                    <div class="text-caption text-disabled font-weight-medium uppercase mt-1">
                      Cierre Final
                    </div>
                  </div>
                </div>
                <VBtn
                  icon="tabler-printer"
                  variant="tonal"
                  color="primary"
                  size="40"
                  class="rounded-lg"
                  @click="emit('print-cash', item)"
                />
              </div>

              <VDivider class="my-3 border-dashed" />

              <div class="d-flex justify-space-between align-center">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-calendar" size="16" color="disabled" />
                  <span class="text-body-2 font-weight-semibold">{{ formatDate(item.closing_date) }}</span>
                </div>
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-clock" size="16" color="disabled" />
                  <span class="text-caption text-medium-emphasis">
                    {{ item.created_at ? new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—' }}
                  </span>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Paginación Móvil Simbolizada -->
      <div class="d-flex justify-center mt-4">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalClosing / props.itemsPerPage)"
          density="comfortable"
          variant="tonal"
          @update:model-value="(val) => emit('update:options', { page: val, itemsPerPage: props.itemsPerPage })"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header) {
  background-color: rgba(var(--v-theme-on-surface), 2%) !important;
}

.premium-table :deep(.v-data-table-header th) {
  block-size: 44px !important;
  color: rgba(var(--v-theme-on-surface), 50%) !important;
  font-size: 0.65rem !important;
  font-weight: 900 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.history-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.history-card:active {
  transform: scale(0.98);
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.15;
}

.leading-none {
  line-height: 1 !important;
}
</style>
