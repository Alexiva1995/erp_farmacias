<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const { mdAndUp } = useDisplay();

// ── Pestaña activa ──────────────────────────────────────────────────────────
const activeTab = ref("totals");

// ── Filtros de Fecha ────────────────────────────────────────────────────────
const now = new Date();
const selectedMonth = ref(now.getMonth() + 1);
const selectedYear = ref(now.getFullYear());
const filterEmployee = ref(null);

const months = [
  { title: "Enero", value: 1 },
  { title: "Febrero", value: 2 },
  { title: "Marzo", value: 3 },
  { title: "Abril", value: 4 },
  { title: "Mayo", value: 5 },
  { title: "Junio", value: 6 },
  { title: "Julio", value: 7 },
  { title: "Agosto", value: 8 },
  { title: "Septiembre", value: 9 },
  { title: "Octubre", value: 10 },
  { title: "Noviembre", value: 11 },
  { title: "Diciembre", value: 12 },
];

const years = computed(() => {
  const current = now.getFullYear();
  return [current - 1, current, current + 1];
});

// ── Datos de la Matriz ──────────────────────────────────────────────────────
const isLoading = ref(false);
const employees = ref([]);
const dailyQuota = ref(50);
const rows = ref([]);
const summary = ref({
  total_month_counts: 0,
  active_days: 0,
  daily_average: 0,
  top_employee: null,
});

const fetchMatrix = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get("/inventory/daily-quotas-matrix", {
      params: {
        month: selectedMonth.value,
        year: selectedYear.value,
        type: activeTab.value,
      },
    });

    const payload = response.data?.data ?? response.data ?? {};

    employees.value = payload.employees || [];
    dailyQuota.value = payload.daily_quota || 50;
    rows.value = payload.data || [];
    summary.value = payload.summary || {
      total_month_counts: rows.value.reduce((acc, row) => acc + (row.day_total || 0), 0),
      active_days: rows.value.filter((r) => (r.day_total || 0) > 0).length,
      daily_average: 0,
      top_employee: null,
    };
  } catch (error) {
    console.error("Error cargando matriz de cuotas:", error);
    toast.error("No se pudo cargar el reporte de cuotas diarias.");
  } finally {
    isLoading.value = false;
  }
};

watch([activeTab, selectedMonth, selectedYear], () => {
  fetchMatrix();
});

onMounted(() => {
  fetchMatrix();
});

const getInitials = (name, lastName) => {
  const n = (name || "").charAt(0);
  const l = (lastName || "").charAt(0);
  return (n + l).toUpperCase() || "U";
};

// Empleados filtrados
const filteredEmployees = computed(() => {
  if (!filterEmployee.value) return employees.value;
  return employees.value.filter((e) => e.user_id === filterEmployee.value);
});

// Exportar a CSV
const exportToCsv = () => {
  if (rows.value.length === 0) {
    toast.warning("No hay datos para exportar.");
    return;
  }

  const header = ["Fecha", "Total Día", ...employees.value.map((e) => `${e.name} ${e.last_name || ""}`.trim())];
  const csvRows = [header.join(",")];

  rows.value.forEach((row) => {
    const rowData = [
      row.formatted_date,
      row.day_total,
      ...employees.value.map((e) => row.users[e.user_id]?.count ?? 0),
    ];
    csvRows.push(rowData.join(","));
  });

  const blob = new Blob([csvRows.join("\n")], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `matriz_cuotas_${selectedMonth.value}_${selectedYear.value}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  toast.success("Archivo CSV generado correctamente.");
};
</script>

<template>
  <div class="quota-matrix-page pb-12">
    <!-- Header Principal -->
    <VCard variant="outlined" class="mb-4 rounded-lg bg-surface shadow-sm overflow-hidden">
      <VCardText class="pa-4">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-target-arrow" size="24" />
            </VAvatar>
            <div>
              <h1 class="text-h6 font-weight-black text-high-emphasis ma-0 text-uppercase">
                Cumplimiento de Cuotas Diarias
              </h1>
              <span class="text-xs text-medium-emphasis">
                Monitoreo del avance y metas diarias de conteos cíclicos por operador.
              </span>
            </div>
          </div>

          <!-- Controles de Fecha y Acciones -->
          <div class="d-flex align-center gap-2 flex-wrap">
            <VSelect
              v-model="selectedMonth"
              :items="months"
              label="Mes"
              density="compact"
              variant="outlined"
              hide-details
              style="min-inline-size: 130px;"
            />

            <VSelect
              v-model="selectedYear"
              :items="years"
              label="Año"
              density="compact"
              variant="outlined"
              hide-details
              style="min-inline-size: 100px;"
            />

            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              prepend-icon="tabler-file-download"
              class="font-weight-bold"
              @click="exportToCsv"
            >
              Exportar
            </VBtn>

            <VBtn
              variant="tonal"
              color="primary"
              size="small"
              prepend-icon="tabler-refresh"
              class="font-weight-bold"
              :loading="isLoading"
              :disabled="isLoading"
              @click="fetchMatrix"
            >
              Actualizar
            </VBtn>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Tarjetas de KPIs Resumen -->
    <VRow dense class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard class="rounded-lg border shadow-sm bg-surface">
          <VCardText class="pa-4 d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-clipboard-check" size="24" />
            </VAvatar>
            <div>
              <span class="text-xs text-medium-emphasis font-weight-bold uppercase">Total Mes</span>
              <div class="text-h6 font-weight-black text-high-emphasis">
                {{ summary.total_month_counts }} <span class="text-xs text-medium-emphasis font-weight-regular">conteos</span>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="rounded-lg border shadow-sm bg-surface">
          <VCardText class="pa-4 d-flex align-center gap-3">
            <VAvatar color="info" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-calendar-stats" size="24" />
            </VAvatar>
            <div>
              <span class="text-xs text-medium-emphasis font-weight-bold uppercase">Días Activos</span>
              <div class="text-h6 font-weight-black text-high-emphasis">
                {{ summary.active_days }} <span class="text-xs text-medium-emphasis font-weight-regular">días</span>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="rounded-lg border shadow-sm bg-surface">
          <VCardText class="pa-4 d-flex align-center gap-3">
            <VAvatar color="warning" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-chart-arrows" size="24" />
            </VAvatar>
            <div>
              <span class="text-xs text-medium-emphasis font-weight-bold uppercase">Promedio Diario</span>
              <div class="text-h6 font-weight-black text-high-emphasis">
                {{ summary.daily_average }} <span class="text-xs text-medium-emphasis font-weight-regular">c/día</span>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="rounded-lg border shadow-sm bg-surface">
          <VCardText class="pa-4 d-flex align-center gap-3">
            <VAvatar color="success" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-crown" size="24" />
            </VAvatar>
            <div class="overflow-hidden">
              <span class="text-xs text-medium-emphasis font-weight-bold uppercase">Top Operador</span>
              <div class="text-subtitle-2 font-weight-black text-truncate text-success" :title="summary.top_employee?.name || 'N/A'">
                {{ summary.top_employee?.name || 'N/A' }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Card con Pestañas y Matriz -->
    <VCard variant="outlined" class="rounded-lg border shadow-sm bg-surface overflow-hidden">
      <!-- Pestañas de Tipo de Conteo -->
      <VTabs v-model="activeTab" color="primary" class="border-b" density="comfortable">
        <VTab value="totals" class="text-none font-weight-bold text-xs">
          <VIcon start icon="tabler-sum" size="18" />
          Totales
        </VTab>

        <VTab value="products" class="text-none font-weight-bold text-xs">
          <VIcon start icon="tabler-package" size="18" />
          Inventario Regular
          <VChip size="x-small" class="ms-2 font-weight-black" color="primary" variant="tonal">
            Meta: {{ dailyQuota }}/día
          </VChip>
        </VTab>

        <VTab value="invoices" class="text-none font-weight-bold text-xs">
          <VIcon start icon="tabler-file-invoice" size="18" />
          Por Factura
        </VTab>

        <VTab value="sales" class="text-none font-weight-bold text-xs">
          <VIcon start icon="tabler-shopping-cart" size="18" />
          Punto de Venta
        </VTab>

        <VTab value="pending" class="text-none font-weight-bold text-xs">
          <VIcon start icon="tabler-alert-circle" size="18" />
          Discrepancias
        </VTab>
      </VTabs>

      <!-- Barra de Leyenda y Filtro de Operador -->
      <div class="d-flex align-center justify-space-between flex-wrap gap-3 pa-3 bg-var-theme-background border-b">
        <!-- Leyenda Informativa -->
        <div class="d-flex align-center gap-3 flex-wrap text-xs text-medium-emphasis">
          <span class="font-weight-bold">Leyenda:</span>
          <div class="d-flex align-center gap-1">
            <span class="legend-dot bg-success"></span>
            <span>Meta cumplida (&ge;{{ dailyQuota }})</span>
          </div>
          <div class="d-flex align-center gap-1">
            <span class="legend-dot bg-warning"></span>
            <span>En progreso</span>
          </div>
          <div class="d-flex align-center gap-1">
            <span class="legend-dot bg-secondary"></span>
            <span>Sin actividad</span>
          </div>
        </div>

        <!-- Filtro Rápido de Empleado -->
        <div v-if="employees.length > 3" style="max-inline-size: 220px;">
          <VAutocomplete
            v-model="filterEmployee"
            :items="employees"
            item-title="name"
            item-value="user_id"
            placeholder="Filtrar operador..."
            density="compact"
            variant="outlined"
            hide-details
            clearable
            prepend-inner-icon="tabler-user"
          />
        </div>
      </div>

      <!-- Estado de Carga con Skeleton -->
      <div v-if="isLoading" class="pa-6">
        <div class="d-flex align-center gap-3 mb-4">
          <VSkeletonLoader type="avatar" />
          <VSkeletonLoader type="text" class="flex-grow-1" />
        </div>
        <VSkeletonLoader type="table-row-divider@8" />
      </div>

      <!-- Estado Vacío -->
      <div v-else-if="rows.length === 0" class="text-center py-12 text-disabled">
        <VIcon icon="tabler-calendar-off" size="48" class="mb-3 opacity-50" />
        <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis">
          No hay registros de conteos
        </h3>
        <p class="text-xs text-medium-emphasis">
          No se encontraron conteos registrados para el período seleccionado.
        </p>
        <VBtn
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="tabler-refresh"
          @click="fetchMatrix"
        >
          Volver a consultar
        </VBtn>
      </div>

      <!-- Vista Desktop: Tabla Matriz Completa -->
      <div v-else-if="mdAndUp" class="table-responsive">
        <VTable hover density="compact" class="matrix-table text-no-wrap">
          <thead>
            <tr>
              <th class="text-left font-weight-black text-uppercase text-xs table-sticky-col">
                Fecha
              </th>
              <th class="text-center font-weight-black text-uppercase text-xs day-total-col">
                Total Día
              </th>
              <th
                v-for="emp in filteredEmployees"
                :key="emp.id"
                class="text-center font-weight-black px-3 py-2 employee-col"
              >
                <div class="d-flex flex-column align-center">
                  <VAvatar size="30" color="primary" variant="tonal" class="mb-1">
                    <VImg v-if="emp.photo" :src="emp.photo" />
                    <span v-else class="text-caption font-weight-black">{{ getInitials(emp.name, emp.last_name) }}</span>
                  </VAvatar>
                  <span class="text-xs font-weight-bold text-truncate employee-name-label" :title="`${emp.name} ${emp.last_name || ''}`">
                    {{ emp.name }} {{ emp.last_name ? emp.last_name.charAt(0) + '.' : '' }}
                  </span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.date">
              <!-- Columna de Fecha Fija -->
              <td class="font-weight-bold text-xs table-sticky-col">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-calendar" size="16" class="text-primary" />
                  <span>{{ row.formatted_date }}</span>
                </div>
              </td>

              <!-- Total del día -->
              <td class="text-center font-weight-black text-xs">
                <VChip
                  size="small"
                  :color="row.day_total > 0 ? 'primary' : 'secondary'"
                  :variant="row.day_total > 0 ? 'flat' : 'tonal'"
                  class="font-weight-black"
                >
                  {{ row.day_total }}
                </VChip>
              </td>

              <!-- Celda por Empleado -->
              <td
                v-for="emp in filteredEmployees"
                :key="emp.id"
                class="text-center px-2 py-2"
              >
                <template v-if="row.users[emp.user_id]">
                  <!-- Pestaña Totales -->
                  <template v-if="activeTab === 'totals'">
                    <VChip
                      v-if="row.users[emp.user_id].count > 0"
                      size="small"
                      color="primary"
                      variant="flat"
                      class="font-weight-black"
                    >
                      {{ row.users[emp.user_id].count }}
                    </VChip>
                    <span v-else class="text-caption text-disabled font-weight-bold">&mdash;</span>
                  </template>

                  <!-- Pestaña Productos (Con Meta y Cumplimiento) -->
                  <template v-else-if="activeTab === 'products'">
                    <VChip
                      v-if="row.users[emp.user_id].count > 0"
                      size="small"
                      :color="row.users[emp.user_id].fulfilled ? 'success' : 'warning'"
                      variant="flat"
                      class="font-weight-black"
                    >
                      <VIcon
                        start
                        :icon="row.users[emp.user_id].fulfilled ? 'tabler-circle-check' : 'tabler-clock'"
                        size="14"
                      />
                      {{ row.users[emp.user_id].count }}/{{ row.users[emp.user_id].quota }}
                    </VChip>
                    <span v-else class="text-caption text-disabled font-weight-bold">&mdash;</span>
                  </template>

                  <!-- Otras pestañas -->
                  <template v-else>
                    <VChip
                      v-if="row.users[emp.user_id].count > 0"
                      size="small"
                      color="info"
                      variant="tonal"
                      class="font-weight-black"
                    >
                      {{ row.users[emp.user_id].count }}
                    </VChip>
                    <span v-else class="text-caption text-disabled font-weight-bold">&mdash;</span>
                  </template>
                </template>
                <span v-else class="text-caption text-disabled font-weight-bold">&mdash;</span>
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>

      <!-- Vista Móvil: Tarjetas por Día -->
      <div v-else class="pa-4 bg-var-theme-background d-flex flex-column gap-3">
        <VCard
          v-for="row in rows"
          :key="row.date"
          class="rounded-lg border shadow-sm bg-surface"
        >
          <VCardItem class="pa-3 border-b bg-surface">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-calendar" size="18" color="primary" />
                <span class="font-weight-black text-sm">{{ row.formatted_date }}</span>
              </div>
              <VChip size="small" color="primary" variant="flat" class="font-weight-black">
                Total Día: {{ row.day_total }}
              </VChip>
            </div>
          </VCardItem>

          <VCardText class="pa-3">
            <div class="d-flex flex-wrap gap-2">
              <template v-for="emp in filteredEmployees" :key="emp.id">
                <div
                  v-if="row.users[emp.user_id] && row.users[emp.user_id].count > 0"
                  class="d-flex align-center gap-2 pa-2 rounded-lg border bg-var-theme-background flex-grow-1"
                >
                  <VAvatar size="26" color="primary" variant="tonal">
                    <span class="text-super-xs font-weight-black">{{ getInitials(emp.name, emp.last_name) }}</span>
                  </VAvatar>
                  <div class="d-flex flex-column flex-grow-1 min-width-0">
                    <span class="text-xs font-weight-bold text-truncate">{{ emp.name }}</span>
                    <span class="text-super-xs font-weight-black" :class="row.users[emp.user_id].fulfilled ? 'text-success' : 'text-primary'">
                      {{ row.users[emp.user_id].count }} {{ activeTab === 'products' ? `/${row.users[emp.user_id].quota}` : 'conteos' }}
                    </span>
                  </div>
                </div>
              </template>
            </div>
          </VCardText>
        </VCard>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.table-responsive {
  overflow-x: auto;
  inline-size: 100%;
}

.matrix-table {
  border-collapse: collapse;
}

.matrix-table th,
.matrix-table td {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
}

.table-sticky-col {
  position: sticky;
  inset-inline-start: 0;
  z-index: 2;
  background-color: rgb(var(--v-theme-surface));
  min-inline-size: 150px;
  border-inline-end: 1px solid rgba(var(--v-border-color), 0.12);
}

.day-total-col {
  inline-size: 90px;
}

.employee-col {
  min-inline-size: 130px;
}

.employee-name-label {
  max-inline-size: 110px;
}

.legend-dot {
  display: inline-block;
  inline-size: 8px;
  block-size: 8px;
  border-radius: 50%;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}
</style>

<route lang="yaml">
meta:
  action: manage
  subject: admin
</route>
