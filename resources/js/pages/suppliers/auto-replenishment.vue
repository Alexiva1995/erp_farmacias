<script setup lang="js">
import AutoReplenishmentFormDialog from "@/components/dialogs/AutoReplenishmentFormDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, onMounted, reactive, ref } from "vue";

const configs = ref([]);
const suppliers = ref([]);
const groups = ref([]);
const loading = ref(false);
const searchQuery = ref("");
const dialogVisible = ref(false);
const dialogLoading = ref(false);

// Control de carga individual para las ejecuciones de cada regla
const runningConfigs = reactive({});
// Errores de validación devueltos por el backend
const formErrors = ref({});

const configForm = ref({
  id: null,
  name: "",
  is_active: true,
  tipo_filtracion: "average",
  lapso_de_tiempo: "1 month",
  min_solicitar: 1,
  con_descuento: false,
  max_price_increase_percentage: null,
  exclude_colombian: false,
  exclude_novaventa: false,
  include_ignored: true,
  stock_filter: "fallas",
  supplier_id: null,
  group_ids: [],
  schedule_expression: "0 6 * * *",
});

const defaultForm = () => ({
  id: null,
  name: "",
  is_active: true,
  tipo_filtracion: "average",
  lapso_de_tiempo: "1 month",
  min_solicitar: 1,
  con_descuento: false,
  max_price_increase_percentage: null,
  exclude_colombian: false,
  exclude_novaventa: false,
  include_ignored: true,
  stock_filter: "fallas",
  supplier_id: null,
  group_ids: [],
  schedule_expression: "0 6 * * *",
});

const tipoFiltracionOpciones = [
  { title: "Stockout-Adjusted ROP PLUS (Inteligencia de Demanda)", value: "stockout_adjusted_rop_plus" },
  { title: "Stockout-Adjusted ROP (Predeterminado)",               value: "stockout_adjusted_rop"      },
  { title: "Ponderado (Óptimo ROP)",                              value: "weighted"                   },
  { title: "Promedio",                                           value: "average"                    },
  { title: "Ventas",                                             value: "sales"                      },
  { title: "Combinado",                                          value: "combinado"                  },
];

const lapsoDeTiempoOpciones = [
  { title: "7 Días", value: "7 days" },
  { title: "15 Días", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "1 Año", value: "1 year" },
];

const scheduleOpciones = [
  { title: "Todos los días a las 6:00 AM", value: "0 6 * * *" },
  { title: "Todos los días a las 8:00 AM", value: "0 8 * * *" },
  { title: "Cada Lunes a las 6:00 AM", value: "0 6 * * 1" },
  { title: "Cada 12 Horas", value: "0 */12 * * *" },
  { title: "Cada Hora", value: "0 * * * *" },
];

// Helper para formatear la frecuencia de forma limpia (ej. DIARIO (8:00 AM))
function translateCron(cron) {
  if (cron === "0 6 * * *") return "DIARIO (6:00 AM)";
  if (cron === "0 8 * * *") return "DIARIO (8:00 AM)";
  if (cron === "0 6 * * 1") return "SEMANAL (Lunes 6:00 AM)";
  if (cron === "0 */12 * * *") return "CADA 12 HORAS";
  if (cron === "0 * * * *") return "CADA HORA";
  const match = scheduleOpciones.find(o => o.value === cron);
  return match ? match.title.replace("Todos los días", "DIARIO").toUpperCase() : "DIARIO";
}

// Formateador amigable de fecha y hora
function formatDate(dateStr) {
  if (!dateStr) return "—";
  const date = new Date(dateStr);
  return date.toLocaleString("es-ES", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

// Helper para obtener las exclusiones y filtros activos de una regla para el tooltip
function getActiveExclusions(item) {
  const list = [];
  if (item.exclude_colombian) list.push("Excluye productos Plan Colombia");
  if (item.exclude_novaventa) list.push("Excluye productos Novaventa");
  if (item.max_price_increase_percentage !== null && item.max_price_increase_percentage !== undefined) {
    list.push(`Sobrecosto máx. permitido: +${item.max_price_increase_percentage}%`);
  }
  if (item.con_descuento) list.push("Aplica precios con descuento");
  if (item.include_ignored === false) list.push("Omite productos ignorados");
  if (item.supplier) list.push(`Proveedor exclusivo: ${item.supplier.name}`);
  if (Array.isArray(item.group_ids) && item.group_ids.length > 0) {
    list.push(`Limitado a ${item.group_ids.length} grupo(s) de productos`);
  }
  return list;
}

// KPIs Computados
const totalRulesCount = computed(() => configs.value.length);
const activeRulesCount = computed(() => configs.value.filter(c => c.is_active).length);
const totalOrdersGenerated = computed(() =>
  configs.value.reduce((acc, c) => acc + (Number(c.last_run_orders) || 0), 0)
);

// Filtrado reactivo para la tabla
const filteredConfigs = computed(() => {
  if (!searchQuery.value.trim()) return configs.value;
  const q = searchQuery.value.toLowerCase().trim();
  return configs.value.filter(item => {
    const nameMatch = item.name?.toLowerCase().includes(q);
    const supplierMatch = item.supplier?.name?.toLowerCase().includes(q);
    const cronMatch = item.schedule_expression?.toLowerCase().includes(q);
    const tipoMatch = item.tipo_filtracion?.toLowerCase().includes(q);
    return nameMatch || supplierMatch || cronMatch || tipoMatch;
  });
});

async function loadConfigs() {
  loading.value = true;
  try {
    const { data } = await axios.get("/auto-replenishment-configs");
    configs.value = data.data ?? data;
  } catch (error) {
    toast.error("Error al cargar las configuraciones de automatización");
  } finally {
    loading.value = false;
  }
}

async function loadDependencies() {
  try {
    const resSuppliers = await axios.get("/suppliers", { params: { itemsPerPage: -1 } });
    suppliers.value = resSuppliers.data.data ?? resSuppliers.data;

    const resGroups = await axios.get("/groups/consult-all");
    groups.value = resGroups.data.data ?? [];
  } catch (error) {
    console.error("Error al cargar dependencias", error);
  }
}

function openCreate() {
  configForm.value = defaultForm();
  formErrors.value = {};
  dialogVisible.value = true;
}

function openEdit(item) {
  configForm.value = {
    ...item,
    max_price_increase_percentage: item.max_price_increase_percentage ?? null,
    include_ignored: item.include_ignored ?? true,
    group_ids: item.group_ids || [],
  };
  formErrors.value = {};
  dialogVisible.value = true;
}

async function saveConfig() {
  dialogLoading.value = true;
  formErrors.value = {};
  try {
    const payload = {
      ...configForm.value,
      max_price_increase_percentage: configForm.value.max_price_increase_percentage !== null && configForm.value.max_price_increase_percentage !== ""
        ? Number(configForm.value.max_price_increase_percentage)
        : null,
      supplier_id: configForm.value.supplier_id ? Number(configForm.value.supplier_id) : null,
      group_ids: Array.isArray(configForm.value.group_ids) ? configForm.value.group_ids : [],
    };

    if (payload.id) {
      await axios.put(`/auto-replenishment-configs/${payload.id}`, payload);
      toast.success("Configuración actualizada correctamente");
    } else {
      await axios.post("/auto-replenishment-configs", payload);
      toast.success("Configuración creada correctamente");
    }
    dialogVisible.value = false;
    await loadConfigs();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      formErrors.value = error.response.data.errors || {};
      toast.error("Por favor verifique los campos requeridos.");
    } else {
      toast.error(error.response?.data?.message || "Error al guardar la configuración");
    }
  } finally {
    dialogLoading.value = false;
  }
}

async function deleteConfig(id) {
  const { isConfirmed } = await Swal.fire({
    title: "¿Eliminar configuración?",
    text: "Esta acción no se puede deshacer.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
  });

  if (!isConfirmed) return;

  try {
    await axios.delete(`/auto-replenishment-configs/${id}`);
    toast.success("Configuración eliminada");
    await loadConfigs();
  } catch (error) {
    toast.error("Error al eliminar la configuración");
  }
}

async function runConfig(id) {
  if (runningConfigs[id]) return;
  runningConfigs[id] = true;
  toast.info("Ejecutando proceso de reposición automática...");
  try {
    const { data } = await axios.post(`/auto-replenishment-configs/${id}/run`);
    Swal.fire({
      title: "Ejecución Completada",
      html: `Se procesó la regla de reposición correctamente:<br><br>
             <strong>Productos analizados:</strong> ${data.last_run_products}<br>
             <strong>Órdenes creadas/actualizadas:</strong> ${data.last_run_orders}`,
      icon: "success",
    });
    await loadConfigs();
  } catch (error) {
    toast.error("Ocurrió un error al ejecutar la configuración.");
  } finally {
    runningConfigs[id] = false;
  }
}

async function toggleActive(item) {
  try {
    await axios.put(`/auto-replenishment-configs/${item.id}`, {
      is_active: item.is_active,
    });
    toast.success(item.is_active ? "Automatización activada" : "Automatización desactivada");
  } catch (error) {
    item.is_active = !item.is_active;
    toast.error("Error al cambiar estado");
  }
}

onMounted(() => {
  loadConfigs();
  loadDependencies();
});
</script>

<template>
  <div class="auto-replenishment-view w-100 flex-grow-1 d-flex flex-column pa-0">
    <!-- Header principal -->
    <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-4 pa-0">
      <div>
        <h1 class="text-h4 font-weight-bold d-flex align-center gap-2 mb-1">
          <VIcon icon="tabler-settings-automation" color="primary" />
          Automatización de Pedidos
        </h1>
        <p class="text-body-2 text-muted mb-0">
          Programación y control de compras automáticas según modelos de reposición e inventario
        </p>
      </div>

      <VBtn color="primary" prepend-icon="tabler-plus" class="shadow-sm" @click="openCreate">
        Nueva Regla
      </VBtn>
    </div>

    <!-- Tarjetas de Resumen KPI -->
    <VRow class="mb-4">
      <VCol cols="12" sm="4">
        <VCard elevation="1" class="pa-4 rounded-lg d-flex align-center border">
          <VAvatar color="primary" variant="tonal" rounded size="48" class="me-3">
            <VIcon icon="tabler-settings-cog" size="26" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold leading-tight">{{ totalRulesCount }}</div>
            <div class="text-caption text-muted">Total de Reglas</div>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4">
        <VCard elevation="1" class="pa-4 rounded-lg d-flex align-center border">
          <VAvatar color="success" variant="tonal" rounded size="48" class="me-3">
            <VIcon icon="tabler-player-play" size="26" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold leading-tight text-success">
              {{ activeRulesCount }} <span class="text-caption text-muted font-weight-regular">/ {{ totalRulesCount }}</span>
            </div>
            <div class="text-caption text-muted">Reglas Activas</div>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4">
        <VCard elevation="1" class="pa-4 rounded-lg d-flex align-center border">
          <VAvatar color="info" variant="tonal" rounded size="48" class="me-3">
            <VIcon icon="tabler-shopping-cart-check" size="26" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold leading-tight text-info">{{ totalOrdersGenerated }}</div>
            <div class="text-caption text-muted">Órdenes Generadas (Últimas Corridas)</div>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Lista de configuraciones -->
    <div class="pa-0 flex-grow-1 d-flex flex-column w-100">
      <VCard class="shadow-md w-100 flex-grow-1 d-flex flex-column overflow-hidden border">
        <!-- Barra de Búsqueda y Filtro de Tabla -->
        <VCardText class="pa-4 border-b bg-surface d-flex align-center justify-space-between flex-wrap gap-3">
          <div style="max-width: 380px; width: 100%;">
            <VTextField
              v-model="searchQuery"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-search"
              placeholder="Buscar por regla, proveedor o cron..."
              hide-details
              clearable
            />
          </div>
          <div class="text-caption text-muted">
            Mostrando <strong>{{ filteredConfigs.length }}</strong> de <strong>{{ configs.length }}</strong> reglas
          </div>
        </VCardText>

        <!-- Cargador de carga limpio -->
        <div v-if="loading" class="pa-12 text-center bg-white">
          <VProgressCircular indeterminate color="primary" size="38" class="mb-3" />
          <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando reglas de automatización...</div>
        </div>

        <div v-else class="table-responsive w-100 flex-grow-1">
          <VTable v-if="filteredConfigs.length > 0" class="w-100 auto-replenishment-table" hover>
            <thead>
              <tr>
                <th class="text-start font-weight-bold">Análisis</th>
                <th class="text-start font-weight-bold">Frecuencia de Ejecución</th>
                <th class="text-start font-weight-bold">Última Corrida</th>
                <th class="text-end px-6 font-weight-bold">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in filteredConfigs" :key="item.id">
                <!-- Análisis (Nombre + Algoritmo + Chips de Lapso y Exclusiones alineados del mismo tamaño) -->
                <td class="py-3">
                  <div class="d-flex flex-column gap-1">
                    <div class="d-flex align-center gap-2">
                      <span class="font-weight-bold text-high-emphasis text-body-1">
                        {{ item.name }}
                      </span>
                      <VChip
                        v-if="!item.is_active"
                        size="x-small"
                        color="secondary"
                        variant="outlined"
                      >
                        Inactivo
                      </VChip>
                    </div>

                    <div class="text-body-2 text-muted">
                      {{ tipoFiltracionOpciones.find(o => o.value === item.tipo_filtracion)?.title || item.tipo_filtracion }}
                    </div>

                    <!-- Chip de Lapso y Badge Agrupado con Tooltip -->
                    <div class="d-flex align-center gap-1.5 flex-wrap mt-0.5">
                      <VChip size="small" variant="tonal" color="primary" class="font-weight-bold">
                        {{ lapsoDeTiempoOpciones.find(o => o.value === item.lapso_de_tiempo)?.title || item.lapso_de_tiempo }}
                      </VChip>

                      <VTooltip
                        v-if="getActiveExclusions(item).length > 0"
                        location="top"
                      >
                        <template #activator="{ props: tooltipProps }">
                          <VChip
                            v-bind="tooltipProps"
                            size="small"
                            color="warning"
                            variant="tonal"
                            class="font-weight-bold cursor-pointer"
                          >
                            <VIcon icon="tabler-filter" size="14" class="me-1" />
                            {{ getActiveExclusions(item).length }} {{ getActiveExclusions(item).length === 1 ? 'Filtro' : 'Filtros' }}
                          </VChip>
                        </template>

                        <div class="pa-1 text-caption text-high-emphasis">
                          <div class="font-weight-bold mb-1 border-b pb-0.5 text-warning">
                            Filtros y Exclusiones Activas
                          </div>
                          <ul class="ps-3 ma-0 text-xs">
                            <li v-for="(exc, idx) in getActiveExclusions(item)" :key="idx" class="py-0.5">
                              {{ exc }}
                            </li>
                          </ul>
                        </div>
                      </VTooltip>
                    </div>
                  </div>
                </td>

                <!-- Frecuencia de Ejecución (Limpia con formato DIARIO) -->
                <td class="py-3">
                  <div class="d-flex align-center gap-1.5 text-body-2 font-weight-medium text-high-emphasis">
                    <VIcon icon="tabler-clock" size="18" class="text-muted" />
                    <span>{{ translateCron(item.schedule_expression) }}</span>
                  </div>
                </td>

                <!-- Última Corrida -->
                <td class="py-3">
                  <div v-if="item.last_run_at" class="d-flex flex-column gap-1">
                    <div class="text-caption text-muted d-flex align-center gap-1">
                      <VIcon icon="tabler-calendar-time" size="14" />
                      {{ formatDate(item.last_run_at) }}
                    </div>
                    <div>
                      <VChip size="x-small" color="success" variant="tonal" class="font-weight-bold">
                        <VIcon icon="tabler-circle-check" size="12" class="me-1" />
                        {{ item.last_run_products }} prod → {{ item.last_run_orders }} órdenes
                      </VChip>
                    </div>
                  </div>
                  <div v-else class="text-caption text-disabled d-flex align-center gap-1">
                    <VIcon icon="tabler-clock-pause" size="14" />
                    Sin ejecuciones previas
                  </div>
                </td>

                <!-- Acciones con estilo del sistema -->
                <td class="text-end px-6 py-3">
                  <div class="d-flex ga-1.5 align-center justify-end">
                    <!-- Ejecutar Ahora -->
                    <VTooltip text="Ejecutar regla ahora" location="top">
                      <template #activator="{ props: tooltipProps }">
                        <VBtn
                          v-bind="tooltipProps"
                          icon
                          size="32"
                          variant="tonal"
                          color="success"
                          class="rounded-circle shadow-sm"
                          :loading="runningConfigs[item.id]"
                          :disabled="runningConfigs[item.id]"
                          @click="runConfig(item.id)"
                        >
                          <VIcon icon="tabler-player-play" size="16" />
                        </VBtn>
                      </template>
                    </VTooltip>

                    <!-- Editar -->
                    <VTooltip text="Editar configuración" location="top">
                      <template #activator="{ props: tooltipProps }">
                        <VBtn
                          v-bind="tooltipProps"
                          icon
                          size="32"
                          variant="tonal"
                          color="primary"
                          class="rounded-circle shadow-sm"
                          :disabled="runningConfigs[item.id]"
                          @click="openEdit(item)"
                        >
                          <VIcon icon="tabler-edit" size="16" />
                        </VBtn>
                      </template>
                    </VTooltip>

                    <!-- Menú Contextual para Acciones Secundarias / Destructivas -->
                    <VMenu location="bottom end">
                      <template #activator="{ props: menuProps }">
                        <VBtn
                          v-bind="menuProps"
                          icon
                          size="32"
                          variant="tonal"
                          color="secondary"
                          class="rounded-circle shadow-sm"
                          :disabled="runningConfigs[item.id]"
                        >
                          <VIcon icon="tabler-dots-vertical" size="16" />
                        </VBtn>
                      </template>
                      <VList density="compact" min-width="180">
                        <VListItem
                          density="compact"
                          :prepend-icon="item.is_active ? 'tabler-toggle-right' : 'tabler-toggle-left'"
                          :title="item.is_active ? 'Desactivar Regla' : 'Activar Regla'"
                          :color="item.is_active ? 'warning' : 'success'"
                          @click="item.is_active = !item.is_active; toggleActive(item)"
                        />
                        <VDivider class="my-1" />
                        <VListItem
                          density="compact"
                          color="error"
                          class="text-error"
                          prepend-icon="tabler-trash"
                          title="Eliminar regla"
                          @click="deleteConfig(item.id)"
                        />
                      </VList>
                    </VMenu>
                  </div>
                </td>
              </tr>
            </tbody>
          </VTable>

          <!-- Estado Vacío por Búsqueda -->
          <VCardText v-else-if="searchQuery" class="text-center py-12 text-muted">
            <VIcon icon="tabler-search-off" size="48" class="mb-3 text-disabled" />
            <p class="text-h6 mb-1">Sin resultados</p>
            <p class="text-body-2 text-muted">No se encontraron reglas que coincidan con "{{ searchQuery }}".</p>
            <VBtn variant="tonal" size="small" @click="searchQuery = ''">Limpiar búsqueda</VBtn>
          </VCardText>

          <!-- Estado Vacío General -->
          <VCardText v-else class="text-center py-12 text-muted">
            <VIcon icon="tabler-settings-automation" size="56" class="mb-3 text-disabled" />
            <p class="text-h6 mb-1">No hay reglas de automatización creadas</p>
            <p class="text-body-2 text-muted mb-4">Parametrice la generación automática de pedidos para optimizar su inventario.</p>
            <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">Crear primera regla</VBtn>
          </VCardText>
        </div>
      </VCard>
    </div>

    <!-- Modal Formulario Desacoplado -->
    <AutoReplenishmentFormDialog
      v-model="dialogVisible"
      :config-form="configForm"
      :dialog-loading="dialogLoading"
      :form-errors="formErrors"
      :suppliers="suppliers"
      :groups="groups"
      @save="saveConfig"
    />
  </div>
</template>

<style lang="scss">
.auto-replenishment-view {
  width: 100% !important;
  max-width: 100% !important;
  min-height: 100% !important;
  flex: 1 1 auto;
}

.auto-replenishment-table {
  width: 100% !important;
}

.table-responsive {
  width: 100%;
  flex: 1 1 auto;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
</style>

