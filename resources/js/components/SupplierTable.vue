<script setup>
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { computed } from "vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
const authStore = useAuthStore();
const brandingStore = useBrandingStore();

const props = defineProps({
  suppliers: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSupplier: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: 'asc' },
  checkingApiId: { type: Number, default: null },
});

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  return [{ key: props.sortBy, order: props.orderBy || 'asc' }];
});

import { useDisplay } from "vuetify";
import AppMobilePagination from "@/components/AppMobilePagination.vue";

const { mobile } = useDisplay();

const isRestaurant = computed(() => false);

const emit = defineEmits([
  "update:options",
  "edit-supplier",
  "delete-supplier",
  "commercial-panel",
  "supplier-pending-invoices",
  "check-supplier-api",
  "config-connection",
  "view-connection-history",
  "sync-dronena-bot",
  "sync-drosymca-bot",
  "merge-supplier",
  "toggle-supplier-status",
]);

const formatDate = (dateString) => {
  if (!dateString) return null;
  try {
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return dateString;
    return d.toLocaleDateString("es-VE", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  } catch (e) {
    return dateString;
  }
};

const formatTime = (dateString) => {
  if (!dateString) return "";
  try {
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return "";
    return d.toLocaleTimeString("es-VE", {
      hour: "2-digit",
      minute: "2-digit",
      hour12: true,
    });
  } catch (e) {
    return "";
  }
};

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Nombre", key: "name", sortable: true },
  { title: "ÚLTIMA SIC", key: "last_sync_at", sortable: true },
  { title: "Deuda", key: "debt", sortable: true },
  { title: "Calificación", key: "latest_score_value", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
];
</script>

<template>
  <div class="supplier-table-container">
    <VCard v-if="!mobile" class="rounded-lg border shadow-sm">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.suppliers"
        :items-length="props.totalSupplier"
        :loading="props.loading"
        :sort-by="sortByModel"
        class="text-no-wrap premium-data-table"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #no-data>
          <AppEmptyState
            title="No hay proveedores"
            message="No se encontraron proveedores registrados en el sistema."
            icon="tabler-truck-off"
          />
        </template>
        <!-- Loader para Carga en Desktop -->
        <template #loading>
          <div class="pa-8 text-center bg-white">
            <VProgressCircular indeterminate color="primary" size="36" class="mb-2" />
            <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando proveedores...</div>
          </div>
        </template>

        <!-- Columnas personalizadas existentes -->
        <template #item.id="{ item }">
          <span class="text-sm font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2" :class="{ 'opacity-60': item.is_active === false }">
            <span class="text-sm font-weight-bold text-high-emphasis">
              {{ item.name }}
            </span>
            <VChip
              v-if="item.is_active === false"
              size="x-small"
              color="secondary"
              variant="flat"
              label
              class="text-xxs font-weight-bold px-1.5"
            >
              Inactivo
            </VChip>
          </div>
        </template>

        <template #item.last_sync_at="{ item }">
          <div v-if="item.last_sync_at" class="d-flex flex-column">
            <span class="text-xs font-weight-medium text-high-emphasis">
              {{ formatDate(item.last_sync_at) }}
            </span>
            <span class="text-xxs text-disabled">
              {{ formatTime(item.last_sync_at) }}
            </span>
          </div>
          <span v-else class="text-caption text-disabled">—</span>
        </template>

        <template #item.debt="{ item }">
          <div class="d-flex flex-column align-end">
            <VChip
              :color="(item.debt ?? 0) > 0 ? 'error' : 'success'"
              size="small"
              variant="flat"
              class="font-weight-bold rounded-lg"
            >
              ${{ (item.debt ?? 0).toLocaleString("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </VChip>
            <span v-if="item.debt > 0" class="text-xxs text-error mt-1">Deuda Pendiente</span>
          </div>
        </template>

        <template #item.latest_score_value="{ item }">
          <div v-if="item.latest_score_value !== null && item.latest_score_value !== undefined" class="d-flex align-center gap-2">
            <VTooltip location="top" max-width="320" content-class="score-breakdown-tooltip pa-0 shadow-lg">
              <template #activator="{ props: tooltipProps }">
                <div v-bind="tooltipProps" class="d-flex align-center gap-2 cursor-pointer">
                  <VRating
                    :model-value="Number(item.latest_score_value) / 20"
                    length="5"
                    readonly
                    size="16"
                    color="warning"
                    active-color="warning"
                    half-increments
                  />
                  <span class="text-caption font-weight-black text-high-emphasis">
                    {{ Number(item.latest_score_value).toFixed(1) }}
                  </span>
                  <VIcon icon="tabler-info-circle" size="14" color="secondary" class="opacity-70" />
                </div>
              </template>

              <!-- Desglose de Evaluación en Tooltip -->
              <div class="pa-3 bg-surface text-high-emphasis rounded-lg border">
                <div class="d-flex justify-space-between align-center mb-2 pb-2 border-b">
                  <span class="text-xs font-weight-black text-primary uppercase">Evaluación (90 Días)</span>
                  <VChip size="x-small" color="warning" variant="flat" class="font-weight-black">
                    {{ Number(item.latest_score_value).toFixed(1) }} / 100
                  </VChip>
                </div>

                <div v-if="item.score_breakdown" class="d-flex flex-column gap-1 text-xs">
                  <div class="d-flex justify-space-between align-center py-0.5">
                    <span class="text-disabled">📦 Completez (Fill Rate):</span>
                    <span class="font-weight-bold" :class="item.score_breakdown.fill_rate?.score !== null ? 'text-success' : 'text-disabled'">
                      {{ item.score_breakdown.fill_rate?.score !== null ? `${item.score_breakdown.fill_rate.score} / 30 pts` : 'N/A (Sin OC)' }}
                    </span>
                  </div>

                  <div class="d-flex justify-space-between align-center py-0.5">
                    <span class="text-disabled">⏱️ A Tiempo (On-Time):</span>
                    <span class="font-weight-bold" :class="item.score_breakdown.on_time?.score !== null ? 'text-success' : 'text-disabled'">
                      {{ item.score_breakdown.on_time?.score !== null ? `${item.score_breakdown.on_time.score} / 20 pts` : 'N/A (Sin OC)' }}
                    </span>
                  </div>

                  <div class="d-flex justify-space-between align-center py-0.5">
                    <span class="text-disabled">⭐ Calidad y Devoluciones:</span>
                    <span class="font-weight-bold text-success">
                      {{ item.score_breakdown.quality?.score ?? 0 }} / 25 pts
                    </span>
                  </div>

                  <div class="d-flex justify-space-between align-center py-0.5">
                    <span class="text-disabled">📑 Precisión Administrativa:</span>
                    <span class="font-weight-bold text-success">
                      {{ item.score_breakdown.admin_accuracy?.score ?? 0 }} / 15 pts
                    </span>
                  </div>

                  <div class="d-flex justify-space-between align-center py-0.5">
                    <span class="text-disabled">🤝 Condiciones Comerciales:</span>
                    <span class="font-weight-bold text-success">
                      {{ item.score_breakdown.commercial_conditions?.score ?? 0 }} / 10 pts
                    </span>
                  </div>

                  <div v-if="item.score_breakdown.is_rescaled" class="mt-2 pt-1 border-t text-xxs text-info font-weight-medium">
                    * Proveedor sin órdenes de compra: puntaje reescalado al 100% sobre facturas.
                  </div>
                </div>
                <div v-else class="text-xs text-disabled py-1">
                  Sin desglose detallado registrado.
                </div>
              </div>
            </VTooltip>
          </div>
          <span v-else class="text-caption text-disabled">N/A</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center">
             <VTooltip v-if="!isRestaurant" text="Estado de Conexión" location="top">
              <template #activator="{ props }">
                <VIcon
                  v-bind="props"
                  size="10"
                  :color="checkingApiId === item.id ? 'warning' : 'success'"
                  icon="tabler-circle-filled"
                  :class="checkingApiId === item.id ? 'spin-icon' : 'pulse-icon'"
                  class="me-2"
                />
              </template>
            </VTooltip>

            <VMenu location="bottom end">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  icon="tabler-dots-vertical"
                  variant="tonal"
                  color="secondary"
                  size="30"
                  class="rounded-circle shadow-sm"
                />
              </template>

              <VList density="compact" min-width="200">
                <VListItem @click="emit('edit-supplier', item)" prepend-icon="tabler-edit">
                  <VListItemTitle>Editar Datos</VListItemTitle>
                </VListItem>

                <VListItem v-if="authStore.isAdmin && !isRestaurant" @click="emit('config-connection', item)" prepend-icon="tabler-plug-connected" base-color="warning">
                  <VListItemTitle>Configurar Conexión</VListItemTitle>
                </VListItem>

                <VListItem v-if="!isRestaurant" @click="emit('view-connection-history', item)" prepend-icon="tabler-history" base-color="info">
                  <VListItemTitle>Historial de Conexiones</VListItemTitle>
                </VListItem>

                <VListItem
                  v-if="(item.name && (item.name.toUpperCase().includes('NENA') || item.name.toUpperCase().includes('DRONENA')))"
                  @click="emit('sync-dronena-bot', item)"
                  prepend-icon="tabler-robot"
                  base-color="info"
                >
                  <VListItemTitle>Sincronizar Facturas (Bot Dronena)</VListItemTitle>
                </VListItem>

                <VListItem
                  v-if="(item.name && (item.name.toUpperCase().includes('DROSYM') || item.name.toUpperCase().includes('DROSI')))"
                  @click="emit('sync-drosymca-bot', item)"
                  prepend-icon="tabler-robot"
                  base-color="primary"
                >
                  <VListItemTitle>Sincronizar Facturas (Bot Drosymca)</VListItemTitle>
                </VListItem>

                <VListItem v-if="!isRestaurant" :disabled="checkingApiId === item.id" @click="emit('check-supplier-api', item)" prepend-icon="tabler-api">
                  <VListItemTitle>Sincronizar</VListItemTitle>
                </VListItem>

                <VListItem v-if="authStore.isAdmin && !isRestaurant" @click="emit('commercial-panel', item)" prepend-icon="tabler-settings-dollar" base-color="primary">
                  <VListItemTitle>Configuración Comercial</VListItemTitle>
                </VListItem>

                <VListItem v-if="authStore.isAdmin" @click="emit('supplier-pending-invoices', item)" prepend-icon="tabler-credit-card-pay">
                   <VListItemTitle>Facturas Pendientes</VListItemTitle>
                </VListItem>

                <VListItem v-if="authStore.isAdmin" @click="emit('merge-supplier', item)" prepend-icon="tabler-arrows-join-2" base-color="warning">
                  <VListItemTitle>Fusionar Proveedor</VListItemTitle>
                </VListItem>

                <VListItem
                  v-if="authStore.isAdmin"
                  :base-color="item.is_active === false ? 'success' : 'warning'"
                  @click="emit('toggle-supplier-status', item)"
                  :prepend-icon="item.is_active === false ? 'tabler-toggle-right' : 'tabler-power'"
                >
                  <VListItemTitle>{{ item.is_active === false ? 'Activar Proveedor' : 'Desactivar Proveedor' }}</VListItemTitle>
                </VListItem>

                <VDivider v-if="authStore.isAdmin" />

                <VListItem v-if="authStore.isAdmin" base-color="error" @click="emit('delete-supplier', item.id)" prepend-icon="tabler-trash">
                  <VListItemTitle>Eliminar</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista de Tarjetas para Móvil -->
    <div v-else class="mobile-supplier-cards d-flex flex-column gap-4">
      <div v-if="loading" class="pa-8 text-center rounded-lg border shadow-sm bg-white">
        <VProgressCircular indeterminate color="primary" size="36" class="mb-2" />
        <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando proveedores...</div>
      </div>

      <template v-else>
        <VCard
          v-for="item in props.suppliers"
          :key="item.id"
          class="supplier-mobile-card rounded-lg border shadow-sm"
        >
          <VCardText class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div>
                <div class="d-flex align-center gap-2">
                  <div class="text-sm font-weight-bold line-clamp-1" :class="{ 'opacity-60': item.is_active === false }">{{ item.name ?? 'Sin nombre' }}</div>
                  <VChip
                    v-if="item.is_active === false"
                    size="x-small"
                    color="secondary"
                    variant="flat"
                    label
                    class="text-xxs font-weight-bold px-1.5"
                  >
                    Inactivo
                  </VChip>
                </div>
                <div class="text-xs text-disabled">ID: {{ item.id }} <span v-if="item.rif">• RIF: {{ item.rif }}</span></div>
              </div>
              <div v-if="!isRestaurant" class="d-flex align-center gap-1">
                <VIcon
                  size="10"
                  :color="checkingApiId === item.id ? 'warning' : 'success'"
                  icon="tabler-circle-filled"
                  :class="checkingApiId === item.id ? 'spin-icon' : ''"
                />
              </div>
            </div>

            <VDivider class="mb-3 border-dashed" />

            <div class="d-flex justify-space-between align-center mb-4">
              <div class="d-flex flex-column">
                <span class="text-caption text-disabled mb-1">Deuda Actual</span>
                <span :class="(item.debt ?? 0) > 0 ? 'text-error' : 'text-success'" class="text-h6 font-weight-bold">
                  ${{ (item.debt ?? 0).toLocaleString("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </span>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-caption text-disabled mb-1">Última SIC</span>
                <span class="text-xs font-weight-bold text-high-emphasis">{{ formatDate(item.last_sync_at) || '—' }}</span>
                <span class="text-xxs text-disabled">{{ formatTime(item.last_sync_at) }}</span>
              </div>
            </div>

            <div class="d-flex justify-space-between align-center mt-2 pa-2 bg-light-surface rounded-lg border">
              <div class="d-flex align-center gap-1 cursor-pointer">
                <VTooltip location="top" max-width="320">
                  <template #activator="{ props: tooltipProps }">
                    <div v-bind="tooltipProps" class="d-flex align-center gap-1">
                      <VIcon icon="tabler-star-filled" color="warning" size="14" />
                      <span class="text-caption font-weight-bold">{{ item.latest_score_value ? Number(item.latest_score_value).toFixed(1) : '—' }}</span>
                    </div>
                  </template>
                  <div class="pa-1">
                    <div class="text-caption font-weight-black border-b pb-1 mb-1 d-flex justify-space-between">
                      <span>Evaluación (90 Días)</span>
                      <span class="text-primary">{{ item.latest_score_value ? Number(item.latest_score_value).toFixed(1) : 0 }}/100</span>
                    </div>
                    <div v-if="item.score_breakdown" class="text-caption">
                      <div class="d-flex justify-space-between align-center py-0.5">
                        <span class="text-disabled">📦 Completez (Fill Rate):</span>
                        <span class="font-weight-bold" :class="item.score_breakdown.fill_rate?.score !== null ? 'text-success' : 'text-disabled'">
                          {{ item.score_breakdown.fill_rate?.score !== null ? `${item.score_breakdown.fill_rate.score} / 30 pts` : 'N/A' }}
                        </span>
                      </div>
                      <div class="d-flex justify-space-between align-center py-0.5">
                        <span class="text-disabled">⏱️ A Tiempo (On-Time):</span>
                        <span class="font-weight-bold" :class="item.score_breakdown.on_time?.score !== null ? 'text-success' : 'text-disabled'">
                          {{ item.score_breakdown.on_time?.score !== null ? `${item.score_breakdown.on_time.score} / 20 pts` : 'N/A' }}
                        </span>
                      </div>
                      <div class="d-flex justify-space-between align-center py-0.5">
                        <span class="text-disabled">🛡️ Calidad y Devoluciones:</span>
                        <span class="font-weight-bold text-success">
                          {{ item.score_breakdown.quality?.score ?? 0 }} / 25 pts
                        </span>
                      </div>
                      <div class="d-flex justify-space-between align-center py-0.5">
                        <span class="text-disabled">📑 Precisión Administrativa:</span>
                        <span class="font-weight-bold text-success">
                          {{ item.score_breakdown.admin_accuracy?.score ?? 0 }} / 15 pts
                        </span>
                      </div>
                      <div class="d-flex justify-space-between align-center py-0.5">
                        <span class="text-disabled">🤝 Condiciones Comerciales:</span>
                        <span class="font-weight-bold text-success">
                          {{ item.score_breakdown.commercial_conditions?.score ?? 0 }} / 10 pts
                        </span>
                      </div>
                      <div v-if="item.score_breakdown.is_rescaled" class="mt-2 pt-1 border-t text-xxs text-info font-weight-medium">
                        * Proveedor sin órdenes de compra: puntaje reescalado al 100% sobre facturas.
                      </div>
                    </div>
                    <div v-else class="text-xs text-disabled py-1">
                      Sin desglose detallado registrado.
                    </div>
                  </div>
                </VTooltip>
              </div>

              <div class="d-flex gap-1 flex-wrap">
                <VBtn
                  icon="tabler-edit"
                  variant="tonal"
                  color="primary"
                  size="32"
                  @click="emit('edit-supplier', item)"
                />
                <VBtn
                  v-if="authStore.isAdmin"
                  icon="tabler-arrows-join-2"
                  variant="tonal"
                  color="warning"
                  size="32"
                  @click="emit('merge-supplier', item)"
                />
                <VBtn
                  v-if="authStore.isAdmin && !isRestaurant"
                  icon="tabler-plug-connected"
                  variant="tonal"
                  color="warning"
                  size="32"
                  @click="emit('config-connection', item)"
                />
                <VBtn
                  v-if="!isRestaurant"
                  icon="tabler-history"
                  variant="tonal"
                  color="info"
                  size="32"
                  @click="emit('view-connection-history', item)"
                />
                <VBtn
                  v-if="!isRestaurant"
                  icon="tabler-api"
                  variant="tonal"
                  :color="checkingApiId === item.id ? 'warning' : 'success'"
                  size="32"
                  :disabled="checkingApiId === item.id"
                  @click="emit('check-supplier-api', item)"
                />
                <VBtn
                  v-if="authStore.isAdmin && !isRestaurant"
                  icon="tabler-settings-dollar"
                  variant="tonal"
                  color="primary"
                  size="32"
                  @click="emit('commercial-panel', item)"
                />
                <VBtn
                  v-if="authStore.isAdmin"
                  icon="tabler-credit-card-pay"
                  variant="tonal"
                  color="secondary"
                  size="32"
                  @click="emit('supplier-pending-invoices', item)"
                />
                <VBtn
                  v-if="authStore.isAdmin"
                  :icon="item.is_active === false ? 'tabler-toggle-right' : 'tabler-power'"
                  variant="tonal"
                  :color="item.is_active === false ? 'success' : 'warning'"
                  size="32"
                  @click="emit('toggle-supplier-status', item)"
                />
                <VBtn
                  v-if="authStore.isAdmin"
                  icon="tabler-trash"
                  variant="tonal"
                  color="error"
                  size="32"
                  @click="emit('delete-supplier', item.id)"
                />
              </div>
            </div>
          </VCardText>
        </VCard>

        <div v-if="props.suppliers.length === 0" class="text-center py-10">
          <VIcon icon="tabler-search-off" size="48" color="disabled" class="mb-2" />
          <p class="text-body-2 text-disabled">No se encontraron proveedores</p>
        </div>

        <div class="mt-4">
          <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalSupplier"
            :loading="props.loading"
            :sort-by="props.sortBy"
            :order-by="props.orderBy"
            @change="(options) => emit('update:options', options)"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.pulse-icon {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% {
    opacity: 1;
    transform: scale(1);
  }

  50% {
    opacity: 0.5;
    transform: scale(1.2);
  }

  100% {
    opacity: 1;
    transform: scale(1);
  }
}

.premium-data-table :deep(th) {
  block-size: 52px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  background-color: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.premium-data-table :deep(td) {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.1) !important;
  padding-block: 12px !important;
}

.supplier-mobile-card {
  box-shadow: 0 4px 15px 0 rgba(0, 0, 0, 5%) !important;
  transition: transform 0.2s;
}

.supplier-mobile-card:active {
  transform: scale(0.98);
}

.line-clamp-1 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
  line-clamp: 1;
}

.bg-light-surface {
  background-color: rgba(var(--v-theme-on-surface), 4%) !important;
}

.text-xxs {
  font-size: 0.65rem;
}
</style>
