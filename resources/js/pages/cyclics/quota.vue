<script setup>
import { ref, onMounted, watch, computed } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

// ── Pestaña activa ──────────────────────────────────────────────────────────
const activeTab = ref("totals");

// ── Filtros de Fecha ────────────────────────────────────────────────────────
const now = new Date();
const selectedMonth = ref(now.getMonth() + 1);
const selectedYear = ref(now.getFullYear());

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

const fetchMatrix = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get("/inventory/daily-quotas-matrix", {
      params: {
        month: selectedMonth.value,
        year: selectedYear.value,
        type: activeTab.value,
      },
    });

    employees.value  = data.employees || [];
    dailyQuota.value = data.daily_quota || 50;
    rows.value       = data.data || [];
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

// Resumen del mes
const totalMonthCounts = computed(() => {
  return rows.value.reduce((acc, row) => acc + (row.day_total || 0), 0);
});
</script>

<template>
  <div class="quota-matrix-page">
    <!-- Encabezado con Filtros y Resumen -->
    <VCard variant="outlined" class="mb-4 rounded-lg bg-surface">
      <VCardItem class="py-3 px-4">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex align-center gap-3">
            <div class="header-icon-box rounded-lg d-flex align-center justify-center">
              <VIcon icon="tabler-target-arrow" class="text-primary" size="24" />
            </div>
            <div>
              <h3 class="text-h6 font-weight-bold text-high-emphasis ma-0">
                Cumplimiento de Cuotas Diarias
              </h3>
              <span class="text-caption text-medium-emphasis">
                Monitorea el avance y cumplimiento de conteos cíclicos por operador y por día
              </span>
            </div>
          </div>

          <div class="d-flex align-center gap-3 flex-wrap">
            <VChip color="primary" variant="tonal" size="small" class="font-weight-bold">
              <VIcon start icon="mdi-counter" size="16" />
              Total Mes: {{ totalMonthCounts }} conteos
            </VChip>

            <VSelect
              v-model="selectedMonth"
              :items="months"
              label="Mes"
              density="compact"
              variant="outlined"
              hide-details
              class="filter-select-month"
            />
            <VSelect
              v-model="selectedYear"
              :items="years"
              label="Año"
              density="compact"
              variant="outlined"
              hide-details
              class="filter-select-year"
            />
            <VBtn
              variant="tonal"
              color="primary"
              size="small"
              :loading="isLoading"
              :disabled="isLoading"
              @click="fetchMatrix"
            >
              <VIcon icon="tabler-refresh" class="mr-1" />
              Actualizar
            </VBtn>
          </div>
        </div>
      </VCardItem>
    </VCard>

    <!-- Card con Pestañas y Matriz -->
    <VCard variant="outlined" class="rounded-lg bg-surface overflow-hidden">
      <VTabs v-model="activeTab" color="primary" align-tabs="start" class="border-b">
        <VTab value="totals" class="text-none font-weight-bold">
          <VIcon start icon="mdi-sigma" />
          Totales
        </VTab>

        <VTab value="products" class="text-none font-weight-medium">
          <VIcon start icon="mdi-package-variant-closed" />
          Inventario Regular
          <VChip size="x-small" class="ml-2" color="primary" variant="tonal">
            Cuota: {{ dailyQuota }}/día
          </VChip>
        </VTab>

        <VTab value="invoices" class="text-none font-weight-medium">
          <VIcon start icon="mdi-file-document-outline" />
          Por Factura
        </VTab>

        <VTab value="sales" class="text-none font-weight-medium">
          <VIcon start icon="mdi-cart-outline" />
          Por Punto de Venta
        </VTab>

        <VTab value="pending" class="text-none font-weight-medium">
          <VIcon start icon="mdi-clock-alert-outline" />
          Pendientes (Discrepancias)
        </VTab>
      </VTabs>

      <!-- Estado de Carga con Skeleton -->
      <div v-if="isLoading" class="pa-6">
        <div class="d-flex align-center gap-3 mb-4">
          <VSkeletonLoader type="avatar" />
          <VSkeletonLoader type="text" class="flex-grow-1" />
        </div>
        <VSkeletonLoader type="table" />
      </div>

      <!-- Tabla Matriz de Escritorio -->
      <div v-else class="table-responsive">
        <VTable hover class="matrix-table text-no-wrap">
          <thead>
            <tr>
              <th class="text-left font-weight-bold table-sticky-col">
                Fecha
              </th>
              <th class="text-center font-weight-bold day-total-col">
                Total Día
              </th>
              <th
                v-for="emp in employees"
                :key="emp.id"
                class="text-center font-weight-bold px-3 py-2 employee-col"
              >
                <div class="d-flex flex-column align-center">
                  <VAvatar size="28" color="primary" variant="tonal" class="mb-1">
                    <VImg v-if="emp.photo" :src="emp.photo" />
                    <span v-else class="text-caption font-weight-bold">{{ getInitials(emp.name, emp.last_name) }}</span>
                  </VAvatar>
                  <span class="text-caption font-weight-medium text-truncate employee-name-label">
                    {{ emp.name }} {{ emp.last_name ? emp.last_name.charAt(0) + '.' : '' }}
                  </span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td :colspan="employees.length + 2" class="text-center py-8 text-medium-emphasis">
                No hay registros de conteos para el período seleccionado.
              </td>
            </tr>

            <tr v-for="row in rows" :key="row.date">
              <!-- Columna de Fecha Fija -->
              <td class="font-weight-medium text-caption table-sticky-col">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-calendar" size="16" class="text-medium-emphasis" />
                  <span>{{ row.formatted_date }}</span>
                </div>
              </td>

              <!-- Total del día -->
              <td class="text-center font-weight-bold text-caption">
                <VChip
                  size="x-small"
                  :color="row.day_total > 0 ? 'primary' : 'secondary'"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ row.day_total }}
                </VChip>
              </td>

              <!-- Celda por Empleado -->
              <td
                v-for="emp in employees"
                :key="emp.id"
                class="text-center px-2 py-2"
              >
                <template v-if="row.users[emp.user_id]">
                  <!-- Pestaña Totales: Mostrar total general sumado del día -->
                  <template v-if="activeTab === 'totals'">
                    <VChip
                      v-if="row.users[emp.user_id].count > 0"
                      size="small"
                      color="primary"
                      variant="flat"
                      class="font-weight-bold"
                    >
                      <VIcon start icon="mdi-sigma" size="14" />
                      {{ row.users[emp.user_id].count }}
                    </VChip>
                    <span v-else class="text-caption text-disabled">—</span>
                  </template>

                  <!-- Pestaña Productos: Mostrar cuota (ej. 50/50) y color verde si cumple -->
                  <template v-else-if="activeTab === 'products'">
                    <VChip
                      v-if="row.users[emp.user_id].count > 0"
                      size="small"
                      :color="row.users[emp.user_id].fulfilled ? 'success' : 'warning'"
                      variant="flat"
                      class="font-weight-bold"
                    >
                      <VIcon
                        start
                        :icon="row.users[emp.user_id].fulfilled ? 'mdi-check-circle' : 'mdi-clock-outline'"
                        size="14"
                      />
                      {{ row.users[emp.user_id].count }}/{{ row.users[emp.user_id].quota }}
                    </VChip>
                    <span v-else class="text-caption text-disabled">—</span>
                  </template>

                  <!-- Otras pestañas: Mostrar cantidad contada -->
                  <template v-else>
                    <VChip
                      v-if="row.users[emp.user_id].count > 0"
                      size="small"
                      color="info"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ row.users[emp.user_id].count }}
                    </VChip>
                    <span v-else class="text-caption text-disabled">—</span>
                  </template>
                </template>
                <span v-else class="text-caption text-disabled">—</span>
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.header-icon-box {
  inline-size: 42px;
  block-size: 42px;
  background-color: rgba(var(--v-theme-primary), 0.1);
}

.filter-select-month {
  inline-size: 140px;
}

.filter-select-year {
  inline-size: 110px;
}

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
  min-inline-size: 160px;
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
</style>

<route lang="yaml">
meta:
  action: manage
  subject: admin
</route>

