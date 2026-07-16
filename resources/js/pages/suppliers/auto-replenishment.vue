<script setup lang="js">
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref } from "vue";

const configs = ref([]);
const suppliers = ref([]);
const groups = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const dialogLoading = ref(false);

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

async function loadConfigs() {
  loading.value = true;
  try {
    const { data } = await axios.get("/auto-replenishment-configs");
    configs.value = data;
  } catch (error) {
    toast.error("Error al cargar configuraciones");
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
  dialogVisible.value = true;
}

function openEdit(item) {
  configForm.value = {
    ...item,
    group_ids: item.group_ids || [],
  };
  dialogVisible.value = true;
}

async function saveConfig() {
  dialogLoading.value = true;
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
    toast.error("Error al guardar la configuración");
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
    <VCard v-if="!loading" class="shadow-md">
      <VTable v-if="configs.length > 0">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Análisis</th>
            <th>Frecuencia (Cron)</th>
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
              <code class="px-2 py-1 rounded bg-light text-primary font-weight-bold">
                {{ item.schedule_expression }}
              </code>
            </td>
            <td>
              <span v-if="item.supplier">{{ item.supplier.name }}</span>
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
              <div class="d-flex ga-1">
                <VBtn icon size="32" variant="tonal" color="success" title="Ejecutar ahora" @click="runConfig(item.id)">
                  <VIcon icon="tabler-play" size="16" />
                </VBtn>
                <VBtn icon size="32" variant="tonal" color="info" title="Editar" @click="openEdit(item)">
                  <VIcon icon="tabler-pencil" size="16" />
                </VBtn>
                <VBtn icon size="32" variant="tonal" color="error" title="Eliminar" @click="deleteConfig(item.id)">
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
    </VCard>

    <div v-else class="text-center py-12">
      <VProgressCircular indeterminate size="64" color="primary" />
    </div>

    <!-- Modal Formulario -->
    <VDialog v-model="dialogVisible" max-width="650px">
      <VCard :loading="dialogLoading">
        <VCardTitle class="px-6 py-4 bg-primary text-white d-flex align-center justify-space-between">
          <span class="text-h6 font-weight-bold">{{ configForm.id ? 'Editar Regla de Reposición' : 'Nueva Regla de Reposición' }}</span>
          <VBtn icon color="white" variant="text" size="sm" @click="dialogVisible = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-6">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="configForm.name" label="Nombre descriptivo de la regla" required placeholder="Ej: Reposición Diaria Urgentes" />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect v-model="configForm.tipo_filtracion" :items="tipoFiltracionOpciones" label="Método de Análisis" />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect v-model="configForm.lapso_de_tiempo" :items="lapsoDeTiempoOpciones" label="Periodo de Ventas" />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField v-model.number="configForm.min_solicitar" type="number" label="Cantidad mínima a solicitar" min="0.01" />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect
                v-model="configForm.schedule_expression"
                :items="scheduleOpciones"
                label="Frecuencia de Ejecución"
                hint="Permite expresiones cron personalizadas"
                persistent-hint
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
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VSwitch v-model="configForm.con_descuento" label="Usar precios con descuento del proveedor" color="primary" />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="px-6 pb-6">
          <VSpacer />
          <VBtn variant="outlined" color="secondary" @click="dialogVisible = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="dialogLoading" @click="saveConfig">Guardar Regla</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.auto-replenishment-view {
  max-width: 1200px;
  margin: 0 auto;
}
</style>
