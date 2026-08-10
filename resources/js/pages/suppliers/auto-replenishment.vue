<script setup lang="js">
import AutoReplenishmentFormDialog from "@/components/dialogs/AutoReplenishmentFormDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, reactive } from "vue";

const configs = ref([]);
const suppliers = ref([]);
const groups = ref([]);
const loading = ref(false);
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
  exclude_colombian: false,
  exclude_novaventa: false,
  include_ignored: true,
  stock_filter: "fallas",
  supplier_id: null,
  group_ids: [],
  schedule_expression: "0 6 * * *",
});

const tipoFiltracionOpciones = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
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

// Helper para parsear la expresión cron a algo amigable
function translateCron(cron) {
  const match = scheduleOpciones.find(o => o.value === cron);
  return match ? match.title : `Cron: ${cron}`;
}

async function loadConfigs() {
  loading.value = true;
  try {
    const { data } = await axios.get("/auto-replenishment-configs");
    // Soporte para API Resource (puede venir envuelto en data)
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
    <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-4 pa-0">
      <h1 class="text-h4 font-weight-bold d-flex align-center gap-2 mb-0">
        <VIcon icon="tabler-settings-automation" color="primary" />
        Automatización de Pedidos
      </h1>
      <VBtn color="primary" prepend-icon="tabler-plus" class="shadow-sm" @click="openCreate">
        Nueva Regla
      </VBtn>
    </div>

    <!-- Lista de configuraciones -->
    <div class="pa-0 flex-grow-1 d-flex flex-column w-100">
      <VCard class="shadow-md w-100 flex-grow-1 d-flex flex-column overflow-hidden border-0">
        <!-- Cargador de carga limpio -->
        <div v-if="loading" class="pa-12 text-center bg-white">
          <VProgressCircular indeterminate color="primary" size="38" class="mb-3" />
          <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando reglas de automatización...</div>
        </div>

        <div v-else class="table-responsive w-100 flex-grow-1">
          <VTable v-if="configs.length > 0" class="w-100 auto-replenishment-table">
          <thead>
            <tr>
              <th class="text-start">Nombre</th>
              <th class="text-start">Análisis</th>
              <th class="text-start">Frecuencia de Ejecución</th>
              <th class="text-start">Proveedor Destino</th>
              <th class="text-center">Estado</th>
              <th class="text-start">Última Corrida</th>
              <th class="text-end px-6">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in configs" :key="item.id">
              <td class="font-weight-bold py-3">{{ item.name }}</td>
              <td class="py-3">
                <div class="d-flex align-center gap-1 flex-wrap">
                  <VChip size="small" color="secondary">
                    {{ tipoFiltracionOpciones.find(o => o.value === item.tipo_filtracion)?.title }}
                  </VChip>
                  <VChip size="small" variant="outlined">
                    {{ lapsoDeTiempoOpciones.find(o => o.value === item.lapso_de_tiempo)?.title }}
                  </VChip>
                  <VChip v-if="item.exclude_colombian" size="small" color="warning" variant="tonal">
                    Sin Col
                  </VChip>
                  <VChip v-if="item.exclude_novaventa" size="small" color="warning" variant="tonal">
                    Sin Novaventa
                  </VChip>
                </div>
              </td>
              <td class="py-3">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <span class="text-body-2 font-weight-medium">
                    {{ translateCron(item.schedule_expression) }}
                  </span>
                  <code class="px-2 py-1 rounded bg-light text-primary font-weight-bold text-xs">
                    {{ item.schedule_expression }}
                  </code>
                </div>
              </td>
              <td class="py-3">
                <span v-if="item.supplier" class="font-weight-medium">{{ item.supplier.name }}</span>
                <span v-else class="text-muted italic">Todos</span>
              </td>
              <td class="text-center py-3">
                <VSwitch
                  v-model="item.is_active"
                  density="compact"
                  hide-details
                  color="success"
                  class="d-inline-flex"
                  @change="toggleActive(item)"
                />
              </td>
              <td class="py-3">
                <div v-if="item.last_run_at">
                  <div class="text-xs text-muted">{{ new Date(item.last_run_at).toLocaleString() }}</div>
                  <div class="text-xs font-weight-bold text-success">
                    {{ item.last_run_products }} prod → {{ item.last_run_orders }} órdenes
                  </div>
                </div>
                <span v-else class="text-muted text-xs">—</span>
              </td>
              <td class="text-end px-6 py-3">
                <div class="d-flex ga-1 align-center justify-end">
                  <VBtn
                    icon
                    size="32"
                    variant="tonal"
                    color="success"
                    title="Ejecutar ahora"
                    :loading="runningConfigs[item.id]"
                    :disabled="runningConfigs[item.id]"
                    @click="runConfig(item.id)"
                  >
                    <VIcon icon="tabler-play" size="16" />
                  </VBtn>
                  <VBtn
                    icon
                    size="32"
                    variant="tonal"
                    color="info"
                    title="Editar"
                    :disabled="runningConfigs[item.id]"
                    @click="openEdit(item)"
                  >
                    <VIcon icon="tabler-pencil" size="16" />
                  </VBtn>
                  <VBtn
                    icon
                    size="32"
                    variant="tonal"
                    color="error"
                    title="Eliminar"
                    :disabled="runningConfigs[item.id]"
                    @click="deleteConfig(item.id)"
                  >
                    <VIcon icon="tabler-trash" size="16" />
                  </VBtn>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>

        <VCardText v-else class="text-center py-12 text-muted">
          <VIcon icon="tabler-settings-automation" size="64" class="mb-4 text-disabled" />
          <p class="text-h6">No hay reglas de automatización creadas</p>
          <p>Haga clic en "Nueva Regla" para parametrizar la generación automática de pedidos.</p>
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
