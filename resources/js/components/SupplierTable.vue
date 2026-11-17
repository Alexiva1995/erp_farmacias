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
  isMaster: { type: Boolean, default: false },
});

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  return [{ key: props.sortBy, order: props.orderBy || 'asc' }];
});

import { useDisplay } from "vuetify";
import AppMobilePagination from "@/components/AppMobilePagination.vue";

const { mobile } = useDisplay();

const isRestaurant = computed(() => brandingStore.isRestaurant);
const canManageSuppliers = computed(() => authStore.isAdmin || authStore.isSupervisor);

const emit = defineEmits([
  "update:options",
  "edit-supplier",
  "delete-supplier",
  "commercial-panel",
  "supplier-pending-invoices",
  "view-products",
  "check-supplier-api",
  "config-connection",
  "view-connection-history",
  "merge-supplier",
]);

const getInitials = (name) => {
  if (!name) return "PR";
  const parts = String(name).trim().split(/\s+/);
  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
  }
  return String(name).substring(0, 2).toUpperCase();
};

const getScoreVariationLabel = (breakdown) => {
  const pv = breakdown?.price_variation;
  if (!pv || pv.details?.no_baseline) return "Sin base OC/Catálogo";
  const avg = pv.details?.avg_variation_pct;
  if (avg === undefined || avg === null) return `${pv.score ?? 0} / 15 pts`;
  const sign = Number(avg) > 0 ? "+" : "";
  return `${pv.score ?? 0} / 15 pts (${sign}${avg}%)`;
};

const isCommercialSupplier = (item) => {
  return item?.type !== "externo" && item?.type !== "gasto";
};

const dayShortMap = {
  monday: "Lun",
  tuesday: "Mar",
  wednesday: "Mié",
  thursday: "Jue",
  friday: "Vie",
  saturday: "Sáb",
};

const todayDayKey = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"][new Date().getDay()];

const isSyncStale = (dateString) => {
  if (!dateString) return false;
  const d = new Date(dateString);
  if (isNaN(d.getTime())) return false;
  return (Date.now() - d.getTime()) > 48 * 60 * 60 * 1000;
};

const formatChannelLabel = (type) => {
  const t = String(type || "file").toLowerCase();
  if (t === "ftp" || t === "sftp") return "FTP";
  if (t === "api" || t === "http") return "API";
  return "Excel";
};

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

const headers = computed(() => {
  if (props.isMaster) {
    return [
      { title: "ID Master", key: "id", sortable: true },
      { title: "Droguería / RIF", key: "name", sortable: true },
      { title: "Tipo de Integración", key: "primary_connection_type", sortable: false },
      { title: "Plantilla Excel", key: "has_structure", sortable: false },
      { title: "Verticales", key: "business_types", sortable: false },
      { title: "Estado", key: "status", sortable: false },
      { title: "Acciones Técnicas", key: "actions", sortable: false },
    ];
  }

  return [
    { title: "Proveedor", key: "name", sortable: true },
    { title: "Condiciones", key: "credit_days", sortable: true },
    { title: "Pedidos", key: "dispatch_days", sortable: false },
    { title: "Catálogo", key: "last_sync_at", sortable: true },
    { title: "Deuda", key: "debt", sortable: true, align: "end" },
    { title: "Score", key: "latest_score_value", sortable: true },
    { title: "Acciones", key: "actions", sortable: false, align: "end" },
  ];
});
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
          <div class="pa-8 text-center bg-surface">
            <VProgressCircular indeterminate color="primary" size="36" class="mb-2" />
            <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando proveedores...</div>
          </div>
        </template>

        <!-- Columnas personalizadas existentes -->
        <template #item.id="{ item }">
          <span class="text-sm font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <template #item.primary_connection_type="{ item }">
          <div class="d-flex align-center gap-1">
            <VChip
              v-if="item.primary_connection_type === 'ftp' || item.primary_connection_type === 'sftp'"
              color="info"
              size="small"
              variant="tonal"
              prepend-icon="tabler-server"
            >
              FTP / SFTP
            </VChip>
            <VChip
              v-else-if="item.primary_connection_type === 'api' || item.primary_connection_type === 'http'"
              color="primary"
              size="small"
              variant="tonal"
              prepend-icon="tabler-api"
            >
              API
            </VChip>
            <VChip
              v-else
              color="success"
              size="small"
              variant="tonal"
              prepend-icon="tabler-file-spreadsheet"
            >
              Excel por Correo
            </VChip>
          </div>
        </template>

        <template #item.has_structure="{ item }">
          <div class="d-flex align-center gap-1">
            <VChip
              v-if="item.has_structure"
              color="success"
              size="small"
              variant="flat"
              prepend-icon="tabler-check"
            >
              Mapeo Listo
            </VChip>
            <VChip
              v-else-if="item.primary_connection_type === 'api' || item.primary_connection_type === 'ftp'"
              color="info"
              size="small"
              variant="outlined"
            >
              Automático
            </VChip>
            <VChip
              v-else
              color="warning"
              size="small"
              variant="tonal"
              prepend-icon="tabler-alert-triangle"
            >
              Pendiente Mapeo
            </VChip>
          </div>
        </template>

        <template #item.business_types="{ item }">
          <div class="d-flex align-center flex-wrap gap-1">
            <template v-if="item.business_types && item.business_types.length > 0">
              <VChip
                v-if="item.business_types.includes('pharmacy') || item.business_types.includes('drogueria')"
                size="x-small"
                color="primary"
                variant="tonal"
                prepend-icon="tabler-pill"
              >
                Farmacia
              </VChip>
              <VChip
                v-if="item.business_types.includes('restaurant')"
                size="x-small"
                color="success"
                variant="tonal"
                prepend-icon="tabler-tools-kitchen-2"
              >
                Restaurante
              </VChip>
              <VChip
                v-if="item.business_types.includes('minimarket')"
                size="x-small"
                color="secondary"
                variant="tonal"
                prepend-icon="tabler-building-store"
              >
                Bodegón
              </VChip>
              <VChip
                v-if="item.business_types.includes('fast_food')"
                size="x-small"
                color="warning"
                variant="tonal"
                prepend-icon="tabler-burger"
              >
                Comida Rápida
              </VChip>
            </template>
            <VChip
              v-else
              size="x-small"
              color="primary"
              variant="tonal"
              prepend-icon="tabler-pill"
            >
              Farmacia
            </VChip>
          </div>
        </template>

        <template #item.status="{ item }">
          <VChip
            color="success"
            size="small"
            variant="tonal"
            prepend-icon="tabler-circle-check"
          >
            Activo en Master
          </VChip>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-1">
            <VAvatar
              size="48"
              :color="item.logo_url ? 'white' : 'secondary'"
              :variant="item.logo_url ? 'flat' : 'tonal'"
              rounded="lg"
              :class="['flex-shrink-0', item.logo_url ? 'border bg-white pa-1' : '']"
            >
              <VImg v-if="item.logo_url" :src="item.logo_url" :cover="false" />
              <span v-else class="text-xs font-weight-bold">{{ getInitials(item.name) }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <div class="d-flex align-center ga-2 flex-wrap">
                <span class="text-sm font-weight-bold text-primary">{{ item.id }}</span>
                <span class="text-sm font-weight-bold text-high-emphasis">
                  {{ item.name }}
                </span>
                <VChip
                  v-if="item.type === 'externo'"
                  size="x-small"
                  color="secondary"
                  variant="outlined"
                  label
                  class="text-xxs font-weight-bold px-1.5"
                >
                  Gastos
                </VChip>
              </div>
              <div v-if="item.rif" class="text-xxs font-weight-bold text-medium-emphasis">
                {{ item.rif }}
              </div>
            </div>
          </div>
        </template>

        <template #item.credit_days="{ item }">
          <div
            v-if="isCommercialSupplier(item)"
            class="d-flex flex-column ga-1"
            :class="{ 'cursor-pointer': canManageSuppliers && !isRestaurant }"
            @click="canManageSuppliers && !isRestaurant ? emit('commercial-panel', item) : null"
          >
            <span class="text-xs font-weight-bold text-high-emphasis">
              {{ (item.credit_days ?? 0) > 0 ? `${item.credit_days} días` : 'Contado' }}
            </span>
            <div class="d-flex align-center ga-2 text-xs text-medium-emphasis">
              <span v-if="(item.max_early_payment_discount ?? 0) > 0" class="text-success font-weight-medium">
                Pronto pago: {{ item.max_early_payment_discount }}%
              </span>
              <span v-if="(item.min_order_amount ?? 0) > 0">
                Mín: ${{ Number(item.min_order_amount).toFixed(0) }}
              </span>
              <span v-if="!(item.max_early_payment_discount > 0) && !(item.min_order_amount > 0)" class="text-disabled">
                Sin acuerdos extra
              </span>
            </div>
          </div>
          <span v-else class="text-caption text-disabled">—</span>
        </template>

        <template #item.dispatch_days="{ item }">
          <div v-if="isCommercialSupplier(item)" class="d-flex flex-column ga-1">
            <div class="d-flex align-center flex-wrap ga-2">
              <VChip
                v-if="Array.isArray(item.order_days) && item.order_days.includes(todayDayKey)"
                size="x-small"
                color="primary"
                variant="tonal"
                class="text-xxs font-weight-bold px-1.5"
              >
                Pedir hoy
              </VChip>
              <span v-if="item.dispatch_days && item.dispatch_days.length > 0" class="text-xs font-weight-medium text-high-emphasis">
                {{ item.dispatch_days.map(d => dayShortMap[d] || d).join(' ') }}
              </span>
              <span v-else-if="!(Array.isArray(item.order_days) && item.order_days.includes(todayDayKey))" class="text-xs text-disabled">
                Sin días asignados
              </span>
            </div>

            <span
              v-if="(item.sent_orders_count ?? 0) > 0"
              class="text-xs font-weight-bold"
              :class="item.is_order_overdue ? 'text-error' : 'text-success'"
            >
              {{ item.sent_orders_count }}<template v-if="item.sent_order_date"> - {{ item.sent_order_date }}</template>
            </span>
          </div>
          <span v-else class="text-caption text-disabled">—</span>
        </template>

        <template #item.last_sync_at="{ item }">
          <div v-if="isCommercialSupplier(item)" class="d-flex align-center ga-2 text-xs">
            <VTooltip
              :text="checkingApiId === item.id ? 'Sincronizando...' : (item.latest_sync_status?.message || 'Estado de Conexión')"
              location="top"
            >
              <template #activator="{ props: dotProps }">
                <VIcon
                  v-bind="dotProps"
                  size="9"
                  :color="checkingApiId === item.id ? 'warning' : (item.latest_sync_status?.status === 'failed' ? 'error' : (item.latest_sync_status?.status === 'partial' || isSyncStale(item.last_sync_at) ? 'warning' : 'success'))"
                  icon="tabler-circle-filled"
                  :class="checkingApiId === item.id ? 'spin-icon' : ''"
                />
              </template>
            </VTooltip>
            <span class="font-weight-medium text-high-emphasis">
              {{ formatDate(item.last_sync_at) || 'Sin sincr.' }}
            </span>
            <span v-if="item.last_sync_at" class="text-medium-emphasis">
              {{ formatTime(item.last_sync_at) }}
            </span>
            <span
              v-if="!props.isMaster && item.products_count !== undefined"
              class="text-primary font-weight-bold cursor-pointer"
              @click="emit('view-products', item)"
            >
              {{ item.products_count }} prod.
            </span>
          </div>
          <span v-else class="text-caption text-disabled">—</span>
        </template>

        <template #item.debt="{ item }">
          <div
            class="d-flex flex-column align-end"
            :class="{ 'cursor-pointer': (item.debt ?? 0) > 0 && canManageSuppliers && !props.isMaster }"
            @click="(item.debt ?? 0) > 0 && canManageSuppliers && !props.isMaster ? emit('supplier-pending-invoices', item) : null"
          >
            <VChip
              :color="(item.debt ?? 0) > 0 ? 'error' : 'success'"
              size="small"
              variant="tonal"
              class="font-weight-bold rounded-lg"
            >
              <template v-if="(item.debt ?? 0) > 0">
                ${{ Number(item.debt).toLocaleString("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                <span v-if="(item.overdue_debt ?? 0) > 0" class="text-error ms-1">
                  (${{ Number(item.overdue_debt).toLocaleString("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }})
                </span>
              </template>
              <template v-else>
                Al día ($0,00)
              </template>
            </VChip>
            <span v-if="(item.unpaid_invoices_count ?? 0) > 0" class="text-xs text-medium-emphasis mt-0.5 font-weight-medium">
              {{ item.unpaid_invoices_count }} fact.<span v-if="(item.overdue_invoices_count ?? 0) > 0" class="text-error font-weight-bold"> ({{ item.overdue_invoices_count }} venc.)</span>
            </span>
          </div>
        </template>

        <template #item.latest_score_value="{ item }">
          <div v-if="item.latest_score_value !== null && item.latest_score_value !== undefined" class="d-flex align-center gap-2">
            <VTooltip location="top" max-width="340" content-class="score-breakdown-tooltip pa-0 shadow-lg">
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
                    <span class="text-disabled">Completez (Fill Rate):</span>
                    <span class="font-weight-bold" :class="item.score_breakdown.fill_rate?.score !== null ? 'text-success' : 'text-disabled'">
                      {{ item.score_breakdown.fill_rate?.score !== null ? `${item.score_breakdown.fill_rate.score} / 30 pts` : 'Sin OC' }}
                    </span>
                  </div>

                  <div class="d-flex justify-space-between align-center py-0.5">
                    <span class="text-disabled">A Tiempo (On-Time):</span>
                    <span class="font-weight-bold" :class="item.score_breakdown.on_time?.score !== null ? 'text-success' : 'text-disabled'">
                      {{ item.score_breakdown.on_time?.score !== null ? `${item.score_breakdown.on_time.score} / 20 pts` : 'Sin OC' }}
                    </span>
                  </div>

                  <div class="d-flex justify-space-between align-center py-0.5">
                    <span class="text-disabled">Calidad y Devoluciones:</span>
                    <span class="font-weight-bold text-success">
                      {{ item.score_breakdown.quality?.score ?? 0 }} / 25 pts
                    </span>
                  </div>

                  <div class="d-flex justify-space-between align-center py-0.5">
                    <span class="text-disabled">Variación de Precio:</span>
                    <span class="font-weight-bold text-success">
                      {{ getScoreVariationLabel(item.score_breakdown) }}
                    </span>
                  </div>

                  <div class="d-flex justify-space-between align-center py-0.5">
                    <span class="text-disabled">Condiciones Comerciales:</span>
                    <span class="font-weight-bold text-success">
                      {{ item.score_breakdown.commercial_conditions?.score ?? 0 }} / 10 pts
                    </span>
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
          <div class="d-flex align-center justify-center gap-1">
            <VTooltip v-if="!isRestaurant && isCommercialSupplier(item)" text="Sincronizar catálogo" location="top">
              <template #activator="{ props: syncBtnProps }">
                <VBtn
                  v-bind="syncBtnProps"
                  icon="tabler-refresh"
                  variant="tonal"
                  :color="checkingApiId === item.id ? 'warning' : 'primary'"
                  size="30"
                  class="rounded-circle"
                  :loading="checkingApiId === item.id"
                  :disabled="checkingApiId === item.id"
                  @click="emit('check-supplier-api', item)"
                />
              </template>
            </VTooltip>

            <VMenu location="bottom end">
              <template #activator="{ props: menuProps }">
                <VBtn
                  v-bind="menuProps"
                  icon="tabler-dots-vertical"
                  variant="tonal"
                  color="secondary"
                  size="30"
                  class="rounded-circle shadow-sm"
                />
              </template>

              <VList density="compact" min-width="210">
                <VListItem @click="emit('edit-supplier', item)" prepend-icon="tabler-edit">
                  <VListItemTitle>Editar Datos</VListItemTitle>
                </VListItem>

                <VListItem
                  v-if="!props.isMaster && isCommercialSupplier(item)"
                  @click="emit('view-products', item)"
                  prepend-icon="tabler-packages"
                  base-color="info"
                >
                  <VListItemTitle>Ver Catálogo de Productos</VListItemTitle>
                </VListItem>

                <VListItem
                  v-if="canManageSuppliers && !isRestaurant && isCommercialSupplier(item)"
                  @click="emit('config-connection', item)"
                  prepend-icon="tabler-plug-connected"
                  base-color="warning"
                >
                  <VListItemTitle>Configurar Conexión</VListItemTitle>
                </VListItem>

                <VListItem
                  v-if="!isRestaurant && isCommercialSupplier(item)"
                  @click="emit('view-connection-history', item)"
                  prepend-icon="tabler-history"
                  base-color="info"
                >
                  <VListItemTitle>Historial de Conexiones</VListItemTitle>
                </VListItem>

                <VListItem
                  v-if="canManageSuppliers && !isRestaurant && !props.isMaster && isCommercialSupplier(item)"
                  @click="emit('commercial-panel', item)"
                  prepend-icon="tabler-settings-dollar"
                  base-color="primary"
                >
                  <VListItemTitle>Configuración Comercial</VListItemTitle>
                </VListItem>

                <VListItem
                  v-if="canManageSuppliers && props.isMaster"
                  @click="emit('merge-supplier', item)"
                  prepend-icon="tabler-arrows-join-2"
                  base-color="warning"
                >
                  <VListItemTitle>Fusionar Proveedor</VListItemTitle>
                </VListItem>

                <VDivider v-if="canManageSuppliers" />

                <VListItem
                  v-if="canManageSuppliers"
                  base-color="error"
                  @click="emit('delete-supplier', item.id)"
                  prepend-icon="tabler-trash"
                >
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
      <div v-if="loading" class="pa-8 text-center rounded-lg border shadow-sm bg-surface">
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
              <div class="d-flex align-center gap-3">
                <VAvatar size="38" color="primary" variant="tonal" rounded="lg" class="flex-shrink-0">
                  <VImg v-if="item.logo_url" :src="item.logo_url" cover />
                  <span v-else class="text-xs font-weight-bold">{{ getInitials(item.name) }}</span>
                </VAvatar>
                <div>
                  <div class="d-flex align-center gap-2">
                    <div class="text-sm font-weight-bold line-clamp-1">{{ item.name ?? 'Sin nombre' }}</div>
                    <VChip
                      v-if="item.type === 'externo'"
                      size="x-small"
                      color="secondary"
                      variant="tonal"
                      label
                      class="text-xxs font-weight-bold px-1.5"
                    >
                      Gastos
                    </VChip>
                  </div>
                  <div class="text-xs text-disabled">
                    ID: {{ item.id }} <span v-if="item.rif">• RIF: {{ item.rif }}</span>
                  </div>
                </div>
              </div>
              <div v-if="!isRestaurant && isCommercialSupplier(item)" class="d-flex align-center gap-1">
                <VIcon
                  size="10"
                  :color="checkingApiId === item.id ? 'warning' : (item.latest_sync_status?.status === 'failed' ? 'error' : (item.latest_sync_status?.status === 'partial' ? 'warning' : 'success'))"
                  icon="tabler-circle-filled"
                  :class="checkingApiId === item.id ? 'spin-icon' : ''"
                />
              </div>
            </div>

            <VDivider class="mb-3 border-dashed" />

            <div class="d-flex justify-space-between align-center mb-3">
              <div
                class="d-flex flex-column"
                :class="{ 'cursor-pointer': (item.debt ?? 0) > 0 && canManageSuppliers && !props.isMaster }"
                @click="(item.debt ?? 0) > 0 && canManageSuppliers && !props.isMaster ? emit('supplier-pending-invoices', item) : null"
              >
                <span class="text-caption text-disabled mb-1">Deuda Actual</span>
                <span :class="(item.debt ?? 0) > 0 ? 'text-error' : 'text-success'" class="text-h6 font-weight-bold">
                  ${{ (item.debt ?? 0).toLocaleString("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </span>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-caption text-disabled mb-1">{{ isRestaurant ? "Última Compra" : "Última SIC" }}</span>
                <span class="text-xs font-weight-bold text-high-emphasis">{{ formatDate(item.last_sync_at) || '—' }}</span>
                <span class="text-xxs text-disabled">{{ formatTime(item.last_sync_at) }}</span>
              </div>
            </div>

            <div v-if="!props.isMaster && isCommercialSupplier(item) && item.products_count !== undefined" class="mb-3">
              <VChip
                size="small"
                color="info"
                variant="tonal"
                prepend-icon="tabler-packages"
                class="cursor-pointer font-weight-bold"
                @click="emit('view-products', item)"
              >
                Ver Catálogo ({{ item.products_count }} productos)
              </VChip>
            </div>

            <div class="d-flex justify-space-between align-center mt-2 pa-2 bg-light-surface rounded-lg border">
              <div class="d-flex align-center gap-1 cursor-pointer">
                <VTooltip location="top" max-width="320">
                  <template #activator="{ props: tooltipProps }">
                    <div v-bind="tooltipProps" class="d-flex align-center gap-1">
                      <VIcon icon="tabler-star-filled" color="warning" size="14" />
                      <span class="text-caption font-weight-bold">{{ item.latest_score_value !== null && item.latest_score_value !== undefined ? Number(item.latest_score_value).toFixed(1) : '—' }}</span>
                    </div>
                  </template>
                  <div class="pa-1">
                    <div class="text-caption font-weight-black border-b pb-1 mb-1 d-flex justify-space-between">
                      <span>Evaluación (90 Días)</span>
                      <span class="text-primary">{{ item.latest_score_value ? Number(item.latest_score_value).toFixed(1) : 0 }}/100</span>
                    </div>
                    <div v-if="item.score_breakdown" class="text-caption">
                      <div class="d-flex justify-space-between align-center py-0.5">
                        <span class="text-disabled">Completez (Fill Rate):</span>
                        <span class="font-weight-bold" :class="item.score_breakdown.fill_rate?.score !== null ? 'text-success' : 'text-disabled'">
                          {{ item.score_breakdown.fill_rate?.score !== null ? `${item.score_breakdown.fill_rate.score} / 30 pts` : 'Sin OC' }}
                        </span>
                      </div>
                      <div class="d-flex justify-space-between align-center py-0.5">
                        <span class="text-disabled">A Tiempo (On-Time):</span>
                        <span class="font-weight-bold" :class="item.score_breakdown.on_time?.score !== null ? 'text-success' : 'text-disabled'">
                          {{ item.score_breakdown.on_time?.score !== null ? `${item.score_breakdown.on_time.score} / 20 pts` : 'Sin OC' }}
                        </span>
                      </div>
                      <div class="d-flex justify-space-between align-center py-0.5">
                        <span class="text-disabled">Calidad y Devoluciones:</span>
                        <span class="font-weight-bold text-success">
                          {{ item.score_breakdown.quality?.score ?? 0 }} / 25 pts
                        </span>
                      </div>
                      <div class="d-flex justify-space-between align-center py-0.5">
                        <span class="text-disabled">Variación de Precio:</span>
                        <span class="font-weight-bold text-success">
                          {{ getScoreVariationLabel(item.score_breakdown) }}
                        </span>
                      </div>
                      <div class="d-flex justify-space-between align-center py-0.5">
                        <span class="text-disabled">Condiciones Comerciales:</span>
                        <span class="font-weight-bold text-success">
                          {{ item.score_breakdown.commercial_conditions?.score ?? 0 }} / 10 pts
                        </span>
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
                  v-if="!props.isMaster && isCommercialSupplier(item)"
                  icon="tabler-packages"
                  variant="tonal"
                  color="info"
                  size="32"
                  @click="emit('view-products', item)"
                />
                <VBtn
                  v-if="canManageSuppliers && props.isMaster"
                  icon="tabler-arrows-join-2"
                  variant="tonal"
                  color="warning"
                  size="32"
                  @click="emit('merge-supplier', item)"
                />
                <VBtn
                  v-if="canManageSuppliers && !isRestaurant && isCommercialSupplier(item)"
                  icon="tabler-plug-connected"
                  variant="tonal"
                  color="warning"
                  size="32"
                  @click="emit('config-connection', item)"
                />
                <VBtn
                  v-if="!isRestaurant && isCommercialSupplier(item)"
                  icon="tabler-history"
                  variant="tonal"
                  color="info"
                  size="32"
                  @click="emit('view-connection-history', item)"
                />
                <VBtn
                  v-if="!isRestaurant && isCommercialSupplier(item)"
                  icon="tabler-api"
                  variant="tonal"
                  :color="checkingApiId === item.id ? 'warning' : 'success'"
                  size="32"
                  :disabled="checkingApiId === item.id"
                  @click="emit('check-supplier-api', item)"
                />
                <VBtn
                  v-if="canManageSuppliers && !isRestaurant && !props.isMaster && isCommercialSupplier(item)"
                  icon="tabler-settings-dollar"
                  variant="tonal"
                  color="primary"
                  size="32"
                  @click="emit('commercial-panel', item)"
                />
                <VBtn
                  v-if="canManageSuppliers && !props.isMaster"
                  icon="tabler-credit-card-pay"
                  variant="tonal"
                  color="secondary"
                  size="32"
                  @click="emit('supplier-pending-invoices', item)"
                />
                <VBtn
                  v-if="canManageSuppliers"
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
  background-color: rgb(var(--v-theme-surface)) !important;
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
