<script setup lang="js">
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
    group_ids: item.group_ids || [],
  };
  formErrors.value = {};
  dialogVisible.value = true;
}

async function saveConfig() {
  dialogLoading.value = true;
  formErrors.value = {};
  try {
    if (configForm.value.id) {
      await axios.put(`/auto-replenishment-configs/${configForm.value.id}`, configForm.value);
      toast.success("Configuración actualizada correctamente");
    } else {
      await axios.post("/auto-replenishment-configs", configForm.value);
      toast.success("Configuración creada correctamente");
    }
    dialogVisible.value = false;
    await loadConfigs();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      formErrors.value = error.response.data.errors || {};
      toast.error("Por favor verifique los campos requeridos.");
    } else {
      toast.error("Error al guardar la configuración");
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
  <div class="auto-replenishment-view pa-6">
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold d-flex align-center gap-2">
          <VIcon icon="tabler-settings-automation" color="primary" />
          Automatización de Pedidos
        </h1>
        <p class="text-subtitle-1 text-muted">
          Configure reglas periódicas automáticas para que el sistema genere borradores de órdenes de compra sin intervención humana.
        </p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" class="shadow-sm" @click="openCreate">
        Nueva Regla
      </VBtn>
    </div>

    <!-- Lista de configuraciones -->
    <VCard class="shadow-md">
      <!-- Skeleton loader para prevenir Layout Shift brusco -->
      <div v-if="loading" class="pa-6">
        <v-skeleton-loader type="table-thead, table-tbody" />
      </div>

      <div v-else class="table-responsive">
        <VTable v-if="configs.length > 0">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Análisis</th>
              <th>Frecuencia de Ejecución</th>
              <th>Proveedor Destino</th>
              <th>Estado</th>
              <th>Última Corrida</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in configs" :key="item.id">
              <td class="font-weight-bold">{{ item.name }}</td>
              <td>
                <VChip size="small" color="secondary" class="mr-1">
                  {{ tipoFiltracionOpciones.find(o => o.value === item.tipo_filtracion)?.title }}
                </VChip>
                <VChip size="small" variant="outlined">
                  {{ lapsoDeTiempoOpciones.find(o => o.value === item.lapso_de_tiempo)?.title }}
                </VChip>
              </td>
              <td>
                <span class="text-body-2 font-weight-medium mr-2">
                  {{ translateCron(item.schedule_expression) }}
                </span>
                <code class="px-2 py-1 rounded bg-light text-primary font-weight-bold text-xs">
                  {{ item.schedule_expression }}
                </code>
              </td>
              <td>
                <span v-if="item.supplier" class="font-weight-medium">{{ item.supplier.name }}</span>
                <span v-else class="text-muted italic">Todos</span>
              </td>
              <td>
                <VSwitch
                  v-model="item.is_active"
                  density="compact"
                  hide-details
                  color="success"
                  @change="toggleActive(item)"
                />
              </td>
              <td>
                <div v-if="item.last_run_at">
                  <div class="text-xs text-muted">{{ new Date(item.last_run_at).toLocaleString() }}</div>
                  <div class="text-xs font-weight-bold text-success">
                    {{ item.last_run_products }} prod → {{ item.last_run_orders }} órdenes
                  </div>
                </div>
                <span v-else class="text-muted text-xs">—</span>
              </td>
              <td>
                <div class="d-flex ga-1 align-center">
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

    <!-- Modal Formulario -->
    <VDialog
      v-model="dialogVisible"
      max-width="680px"
      persistent
      scrollable
    >
      <VCard class="detail-dialog-card rounded-xl overflow-hidden border-0 shadow-xl bg-surface" :loading="dialogLoading">
        <!-- Header Premium Institucional -->
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
              <VIcon icon="tabler-settings-automation" color="primary" size="22" />
            </VAvatar>
            <div class="d-flex flex-column leading-none text-white">
              <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
                {{ configForm.id ? 'Editar Regla' : 'Nueva Regla' }}
              </h2>
              <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
                Reposición Automática
              </span>
            </div>
            <VSpacer />
            <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="dialogVisible = false" />
          </div>
        </VCardTitle>

        <VCardText class="pa-4 pa-sm-6 bg-light" style="overflow-y: auto;">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="configForm.name"
                label="Nombre descriptivo de la regla"
                required
                placeholder="Ej: Reposición Diaria Urgentes"
                :error-messages="formErrors.name"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect
                v-model="configForm.tipo_filtracion"
                :items="tipoFiltracionOpciones"
                label="Método de Análisis"
                :error-messages="formErrors.tipo_filtracion"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect
                v-model="configForm.lapso_de_tiempo"
                :items="lapsoDeTiempoOpciones"
                label="Periodo de Ventas"
                :error-messages="formErrors.lapso_de_tiempo"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="configForm.min_solicitar"
                type="number"
                label="Cantidad mínima a solicitar"
                min="0"
                step="any"
                :error-messages="formErrors.min_solicitar"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect
                v-model="configForm.schedule_expression"
                :items="scheduleOpciones"
                label="Frecuencia de Ejecución"
                hint="Expresión cron programada para la automatización"
                persistent-hint
                :error-messages="formErrors.schedule_expression"
              />
            </VCol>

            <VCol cols="12">
              <VAutocomplete
                v-model="configForm.supplier_id"
                :items="suppliers"
                label="Proveedor preferido (Opcional)"
                item-title="name"
                item-value="id"
                clearable
                placeholder="Todos los proveedores"
                :error-messages="formErrors.supplier_id"
              />
            </VCol>

            <VCol cols="12">
              <VAutocomplete
                v-model="configForm.group_ids"
                :items="groups"
                label="Limitar a Grupos de Producto (Opcional)"
                item-title="name"
                item-value="id"
                multiple
                chips
                closable-chips
                placeholder="Todos los grupos"
                :error-messages="formErrors.group_ids"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="configForm.con_descuento"
                label="Usar precios con descuento del proveedor"
                color="primary"
                :error-messages="formErrors.con_descuento"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
          <VRow dense class="w-100 ma-0">
            <VCol cols="6" class="pa-1">
              <VBtn
                color="secondary"
                variant="tonal"
                height="50"
                block
                class="font-weight-black rounded-lg uppercase"
                @click="dialogVisible = false"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="6" class="pa-1">
              <VBtn
                color="primary"
                variant="flat"
                height="50"
                block
                class="font-weight-black rounded-lg shadow-primary uppercase"
                :loading="dialogLoading"
                @click="saveConfig"
              >
                <VIcon start icon="tabler-device-floppy" size="18" />
                Guardar Regla
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
</style>
