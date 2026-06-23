<script setup>
import { computed, onMounted, ref, watch } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

// Listas e Información inicial
const orders = ref([]);
const employees = ref([]);
const audits = ref([]);
const flows = ref([]);
const totalAudits = ref(0);
const loading = ref(false);
const loadingForm = ref(false);

// Filtros e historial
const page = ref(1);
const itemsPerPage = ref(10);
const startDate = ref("");
const endDate = ref("");
const selectedFlowIdFilter = ref(null);

// Datos del formulario de nueva auditoría
const selectedFlowId = ref(null);
const selectedOrderId = ref(null);
const selectedCashierId = ref(null);
const selectedCookId = ref(null);

// Fases del flujo seleccionado
const phases = ref([]);
const currentPhaseIndex = ref(0);
const elapsedSeconds = ref(0); // Segundos transcurridos en la fase activa
const isTimerRunning = ref(false);
const timerInterval = ref(null);

// Tiempos medidos por fase (en segundos)
const phaseTimes = ref({});

// Control del diálogo del configurador de flujos
const flowConfigDialog = ref(false);
const isEditingFlow = ref(false);
const currentFlowForm = ref({
  id: null,
  name: "",
  description: "",
  is_active: true,
  phases: []
});

// Fase activa actual
const currentPhase = computed(() => {
  if (phases.value.length === 0) return null;
  return phases.value[currentPhaseIndex.value];
});

// Carga de la orden seleccionada para saber detalles
const selectedOrderDetails = computed(() => {
  if (!selectedOrderId.value) return null;
  return orders.value.find(o => o.id === selectedOrderId.value);
});

// Convertir segundos a formato MM:SS
const formatTime = (seconds) => {
  if (seconds === undefined || seconds === null) return "00:00";
  const mins = String(Math.floor(seconds / 60)).padStart(2, "0");
  const secs = String(seconds % 60).padStart(2, "0");
  return `${mins}:${secs}`;
};

// Cargar Flujos de procesos
const fetchFlows = async () => {
  try {
    const response = await axios.get("/process-audits/flows");
    flows.value = response.data.data || [];
    if (flows.value.length > 0 && !selectedFlowId.value) {
      selectedFlowId.value = flows.value[0].id;
    }
  } catch (error) {
    console.error("Error al cargar flujos de procesos:", error);
  }
};

// Cargar Órdenes activas
const fetchOrders = async () => {
  try {
    const response = await axios.get("/tpv-orders", {
      params: { limit: 50 }
    });
    const fetched = response.data.data || response.data || [];
    orders.value = fetched.filter(o => o.status !== 'Cancelled');
  } catch (error) {
    console.error("Error al cargar órdenes:", error);
    try {
      const fallbackResponse = await axios.get("/tpv/order-general");
      orders.value = fallbackResponse.data.data || [];
    } catch (fError) {
      orders.value = [
        { id: 1, total_amount_usd: 12.50, client: { name: "Cliente Local" } },
        { id: 2, total_amount_usd: 8.90, client: { name: "Cliente WhatsApp (Envío)" } }
      ];
    }
  }
};

// Cargar lista de Empleados
const fetchEmployees = async () => {
  try {
    const response = await axios.get("/employees");
    employees.value = response.data.data || response.data || [];
  } catch (error) {
    console.error("Error al cargar empleados:", error);
  }
};

// Cargar Historial de Auditorías
const fetchAudits = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/process-audits", {
      params: {
        page: page.value,
        itemsPerPage: itemsPerPage.value,
        startDate: startDate.value,
        endDate: endDate.value,
        flow_id: selectedFlowIdFilter.value
      }
    });
    audits.value = response.data.data || [];
    totalAudits.value = response.data.total || 0;
  } catch (error) {
    console.error("Error al cargar historial de auditorías:", error);
  } finally {
    loading.value = false;
  }
};

// Observar cambio de flujo seleccionado para actualizar las fases
watch(selectedFlowId, (newFlowId) => {
  resetTimer();
  currentPhaseIndex.value = 0;
  phaseTimes.value = {};
  if (newFlowId) {
    const selectedFlow = flows.value.find(f => f.id === newFlowId);
    if (selectedFlow) {
      phases.value = selectedFlow.phases || [];
      phases.value.forEach(p => {
        phaseTimes.value[p.id] = 0;
      });
    }
  } else {
    phases.value = [];
  }
});

// Controladores del Cronómetro
const startTimer = () => {
  if (isTimerRunning.value) return;
  isTimerRunning.value = true;
  timerInterval.value = setInterval(() => {
    elapsedSeconds.value++;
  }, 1000);
};

const pauseTimer = () => {
  if (!isTimerRunning.value) return;
  isTimerRunning.value = false;
  if (timerInterval.value) {
    clearInterval(timerInterval.value);
    timerInterval.value = null;
  }
};

const resetTimer = () => {
  pauseTimer();
  elapsedSeconds.value = 0;
};

const nextPhase = () => {
  if (phases.value.length === 0) return;
  
  // Guardar tiempo de la fase actual
  const currentPhaseId = currentPhase.value.id;
  phaseTimes.value[currentPhaseId] = elapsedSeconds.value;

  if (currentPhaseIndex.value < phases.value.length - 1) {
    currentPhaseIndex.value++;
    elapsedSeconds.value = 0;
    
    // Si estaba corriendo, continúa corriendo para la siguiente fase automáticamente
    if (isTimerRunning.value) {
      pauseTimer();
      startTimer();
    }
  } else {
    // Si es la última fase, detener el timer
    pauseTimer();
    toast.fire({
      icon: "success",
      title: "Medición de fases completada. Listo para registrar."
    });
  }
};

// Guardar e Integrar en Base de Datos
const saveAudit = async () => {
  if (!selectedCookId.value) {
    toast.fire({ icon: "error", title: "Debe seleccionar el Cocinero en turno." });
    return;
  }

  // Guardar la fase activa residual si no se ha saltado con "Siguiente"
  if (currentPhase.value) {
    const currentPhaseId = currentPhase.value.id;
    if (!phaseTimes.value[currentPhaseId] && elapsedSeconds.value > 0) {
      phaseTimes.value[currentPhaseId] = elapsedSeconds.value;
    }
  }

  const payloadPhases = Object.entries(phaseTimes.value).map(([phaseId, seconds]) => ({
    flow_phase_id: parseInt(phaseId),
    seconds: seconds
  }));

  // Calcular el tiempo total acumulado
  const total = Object.values(phaseTimes.value).reduce((sum, sec) => sum + sec, 0);

  loadingForm.value = true;
  const payload = {
    flow_id: selectedFlowId.value,
    order_id: selectedOrderId.value || null,
    cashier_id: selectedCashierId.value || null,
    cook_id: selectedCookId.value,
    phases: payloadPhases,
    total_seconds: total
  };

  try {
    const response = await axios.post("/process-audits", payload);
    if (response.data.success) {
      toast.fire({ icon: "success", title: "Auditoría de proceso registrada con éxito." });
      
      // Limpiar Formulario
      selectedOrderId.value = null;
      selectedCashierId.value = null;
      currentPhaseIndex.value = 0;
      elapsedSeconds.value = 0;
      
      // Reiniciar tiempos
      phases.value.forEach(p => {
        phaseTimes.value[p.id] = 0;
      });

      fetchAudits();
    }
  } catch (error) {
    console.error("Error al registrar auditoría:", error);
    toast.fire({
      icon: "error",
      title: error.response?.data?.message || "Error al registrar la auditoría."
    });
  } finally {
    loadingForm.value = false;
  }
};

// Calcular Promedio de cada fase dinámicamente
const calculateAverageOfPhase = (phaseId) => {
  if (!audits.value || audits.value.length === 0) return 0;
  let count = 0;
  let sum = 0;
  audits.value.forEach(audit => {
    const matchedPhase = audit.phases?.find(p => p.flow_phase_id === phaseId);
    if (matchedPhase) {
      sum += matchedPhase.seconds;
      count++;
    }
  });
  return count > 0 ? Math.round(sum / count) : 0;
};

// Calcular promedio de tiempo total del historial cargado
const calculateAverageTotal = () => {
  if (!audits.value || audits.value.length === 0) return 0;
  const sum = audits.value.reduce((acc, curr) => acc + (Number(curr.total_seconds) || 0), 0);
  return Math.round(sum / audits.value.length);
};

// Encontrar el valor de fase para una fila específica del historial
const getAuditPhaseValue = (audit, phaseId) => {
  const matchedPhase = audit.phases?.find(p => p.flow_phase_id === phaseId);
  return matchedPhase ? formatTime(matchedPhase.seconds) : '00:00';
};

// Obtener las fases únicas representadas en el historial actual
const currentFilteredFlowPhases = computed(() => {
  if (selectedFlowIdFilter.value) {
    const matchedFlow = flows.value.find(f => f.id === selectedFlowIdFilter.value);
    return matchedFlow ? matchedFlow.phases : [];
  }
  // Si no hay filtro, mostrar fases del primer flujo como referencia
  return flows.value.length > 0 ? flows.value[0].phases : [];
});

// Métodos del configurador de flujos
const openNewFlowModal = () => {
  isEditingFlow.value = false;
  currentFlowForm.value = {
    id: null,
    name: "",
    description: "",
    is_active: true,
    phases: [
      { name: "Atención y Pago", description: "", sort_order: 1 }
    ]
  };
  flowConfigDialog.value = true;
};

const openEditFlowModal = (flow) => {
  isEditingFlow.value = true;
  currentFlowForm.value = {
    id: flow.id,
    name: flow.name,
    description: flow.description || "",
    is_active: !!flow.is_active,
    phases: flow.phases.map(p => ({ ...p }))
  };
  flowConfigDialog.value = true;
};

const addPhaseToForm = () => {
  const nextOrder = currentFlowForm.value.phases.length + 1;
  currentFlowForm.value.phases.push({
    name: "",
    description: "",
    sort_order: nextOrder
  });
};

const removePhaseFromForm = (index) => {
  currentFlowForm.value.phases.splice(index, 1);
  currentFlowForm.value.phases.forEach((p, i) => {
    p.sort_order = i + 1;
  });
};

const saveFlow = async () => {
  if (!currentFlowForm.value.name) {
    toast.fire({ icon: "error", title: "El nombre del flujo es obligatorio." });
    return;
  }
  if (currentFlowForm.value.phases.length === 0) {
    toast.fire({ icon: "error", title: "Debe ingresar al menos una fase de medición." });
    return;
  }
  if (currentFlowForm.value.phases.some(p => !p.name)) {
    toast.fire({ icon: "error", title: "Todas las fases deben tener un nombre." });
    return;
  }

  try {
    const response = await axios.post("/process-audits/flows", currentFlowForm.value);
    if (response.data.success) {
      toast.fire({ icon: "success", title: "Flujo de procesos guardado correctamente." });
      flowConfigDialog.value = false;
      await fetchFlows();
    }
  } catch (error) {
    console.error("Error al guardar flujo:", error);
    toast.fire({ icon: "error", title: "Error al guardar el flujo de procesos." });
  }
};

const deleteFlow = async (id) => {
  try {
    const confirm = window.confirm("¿Está seguro de que desea eliminar este flujo de procesos? Se perderán las mediciones asociadas.");
    if (!confirm) return;

    const response = await axios.delete(`/process-audits/flows/${id}`);
    if (response.data.success) {
      toast.fire({ icon: "success", title: "Flujo eliminado correctamente." });
      await fetchFlows();
    }
  } catch (error) {
    console.error("Error al eliminar flujo:", error);
    toast.fire({ icon: "error", title: "No se pudo eliminar el flujo." });
  }
};

// Lifecycle Hooks
onMounted(() => {
  fetchFlows();
  fetchOrders();
  fetchEmployees();
  fetchAudits();
});

// Watchers de paginación e historial
watch([page, itemsPerPage, startDate, endDate, selectedFlowIdFilter], () => {
  fetchAudits();
});
</script>

<template>
  <div class="process-audit-container pa-6">
    <!-- Header e Inicio de Flujo / Configuración -->
    <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
      <div>
        <h2 class="text-h4 font-weight-black text-warning">Auditoría Operativa de Procesos</h2>
        <p class="text-white-50">Gestión de tiempos y flujos críticos de servicio</p>
      </div>
      <div class="d-flex gap-2">
        <VBtn
          color="warning"
          variant="tonal"
          prepend-icon="tabler-settings"
          @click="openNewFlowModal"
        >
          Configurar Flujos
        </VBtn>
      </div>
    </div>

    <VRow>
      <!-- Panel de Control y Cronómetro (Look Oscuro e Industrial) -->
      <VCol cols="12" md="7">
        <VCard class="rounded-xl overflow-hidden elevation-8 border-dark bg-industrial-dark text-white">
          <VCardItem class="bg-carbon py-4">
            <div class="d-flex align-center justify-space-between flex-wrap gap-3">
              <div class="d-flex align-center gap-3">
                <VAvatar color="warning" variant="flat" size="40" rounded="lg">
                  <VIcon icon="tabler-hourglass-high" color="white" />
                </VAvatar>
                <div>
                  <VCardTitle class="text-h5 font-weight-black text-warning">Cronómetro de Auditoría</VCardTitle>
                  <VCardSubtitle class="text-white-50">Audite la orden seleccionando un flujo dinámico</VCardSubtitle>
                </div>
              </div>
              <div style="min-width: 200px;">
                <span class="label-industrial text-warning font-weight-bold text-caption">Flujo de Medición</span>
                <VSelect
                  v-model="selectedFlowId"
                  :items="flows"
                  item-title="name"
                  item-value="id"
                  variant="solo"
                  density="compact"
                  class="mt-1"
                  bg-color="rgb(30, 30, 30)"
                  theme="dark"
                />
              </div>
            </div>
          </VCardItem>

          <VCardText class="pa-6">
            <VForm>
              <!-- Selectores Opcionales y Obligatorios -->
              <VRow dense>
                <VCol cols="12">
                  <span class="label-industrial text-white-50 font-weight-bold">1. Selección de Orden Activa (Opcional)</span>
                  <VSelect
                    v-model="selectedOrderId"
                    :items="orders"
                    item-title="id"
                    item-value="id"
                    placeholder="Puede asociar una orden ahora o al finalizar..."
                    variant="solo"
                    density="comfortable"
                    class="mt-1 border-input-industrial"
                    bg-color="rgb(30, 30, 30)"
                    theme="dark"
                    clearable
                  >
                    <template #item="{ props, item }">
                      <VListItem v-bind="props">
                        <template #title>
                          <span class="font-weight-black text-warning">Orden #{{ item.raw.id }}</span>
                          <span class="ms-2 text-white-50">(${{ item.raw.total_amount_usd }} USD)</span>
                        </template>
                        <template #subtitle>
                          <span class="text-caption text-white-70">
                            Cliente: {{ item.raw.client?.name || 'Cliente de paso' }}
                          </span>
                        </template>
                      </VListItem>
                    </template>
                  </VSelect>
                </VCol>

                <VCol cols="12" md="6" class="mt-2">
                  <span class="label-industrial text-white-50 font-weight-bold">2. Cajero en Turno (Opcional)</span>
                  <VSelect
                    v-model="selectedCashierId"
                    :items="employees"
                    item-title="name"
                    item-value="id"
                    placeholder="Seleccione cajero..."
                    variant="solo"
                    density="comfortable"
                    class="mt-1 border-input-industrial"
                    bg-color="rgb(30, 30, 30)"
                    theme="dark"
                    clearable
                  >
                    <template #item="{ props, item }">
                      <VListItem v-bind="props" :title="item.raw.name + ' ' + (item.raw.last_name || '')" />
                    </template>
                    <template #selection="{ item }">
                      <span>{{ item.raw.name }} {{ item.raw.last_name }}</span>
                    </template>
                  </VSelect>
                </VCol>

                <VCol cols="12" md="6" class="mt-2">
                  <span class="label-industrial text-warning font-weight-bold">3. Cocinero en Turno *</span>
                  <VSelect
                    v-model="selectedCookId"
                    :items="employees"
                    item-title="name"
                    item-value="id"
                    placeholder="Obligatorio para iniciar..."
                    variant="solo"
                    density="comfortable"
                    class="mt-1 border-input-industrial"
                    bg-color="rgb(30, 30, 30)"
                    theme="dark"
                  >
                    <template #item="{ props, item }">
                      <VListItem v-bind="props" :title="item.raw.name + ' ' + (item.raw.last_name || '')" />
                    </template>
                    <template #selection="{ item }">
                      <span>{{ item.raw.name }} {{ item.raw.last_name }}</span>
                    </template>
                  </VSelect>
                </VCol>
              </VRow>

              <VDivider class="my-6 border-industrial" />

              <!-- Sección Cronómetro Digital Interactivo -->
              <div v-if="phases.length > 0" class="timer-section text-center rounded-xl py-6 px-4 bg-black-industrial border-dark-neon">
                <span class="text-overline text-white-50 tracking-wide uppercase">Fase {{ currentPhaseIndex + 1 }} de {{ phases.length }}</span>
                <h3 class="text-h4 font-weight-black text-warning mt-1">{{ currentPhase?.name }}</h3>
                <p class="text-body-2 text-white-50 mt-1 mb-4 italic">"{{ currentPhase?.description || 'Sin descripción' }}"</p>

                <!-- Reloj Digital Neón -->
                <div class="digital-clock font-weight-bold my-4 text-neon-yellow">
                  {{ formatTime(elapsedSeconds) }}
                </div>

                <!-- Botones Touch Grandes de Control -->
                <div class="d-flex justify-center align-center gap-4 flex-wrap mt-6">
                  <VBtn
                    v-if="!isTimerRunning"
                    color="success"
                    size="large"
                    variant="elevated"
                    class="rounded-xl px-8 font-weight-black uppercase"
                    @click="startTimer"
                  >
                    <VIcon icon="tabler-play" class="me-2" />
                    Iniciar
                  </VBtn>
                  <VBtn
                    v-else
                    color="warning"
                    size="large"
                    variant="elevated"
                    class="rounded-xl px-8 font-weight-black uppercase"
                    @click="pauseTimer"
                  >
                    <VIcon icon="tabler-pause" class="me-2" />
                    Detener
                  </VBtn>

                  <VBtn
                    color="info"
                    size="large"
                    variant="flat"
                    class="rounded-xl px-8 font-weight-black uppercase"
                    @click="nextPhase"
                  >
                    <VIcon icon="tabler-arrow-narrow-right" class="me-2" />
                    Siguiente
                  </VBtn>

                  <VBtn
                    color="secondary"
                    size="large"
                    variant="tonal"
                    class="rounded-xl px-6"
                    @click="resetTimer"
                  >
                    <VIcon icon="tabler-refresh" class="me-2" />
                    Reiniciar
                  </VBtn>
                </div>
              </div>
              <div v-else class="text-center py-6 text-white-50">
                Seleccione o configure un flujo de medición para iniciar el cronómetro.
              </div>

              <!-- Registro Integrado -->
              <div class="mt-6">
                <VBtn
                  color="warning"
                  block
                  size="x-large"
                  variant="flat"
                  class="rounded-xl font-weight-black uppercase border-neon-glow"
                  :loading="loadingForm"
                  @click="saveAudit"
                >
                  <VIcon icon="tabler-database-import" class="me-2" />
                  Registrar e Integrar en Base de Datos
                </VBtn>
              </div>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Panel de Resumen de Tiempos Registrados para la Orden en Curso -->
      <VCol cols="12" md="5">
        <VCard class="rounded-xl overflow-hidden border-dark elevation-8 bg-industrial-dark text-white h-100">
          <VCardItem class="bg-carbon py-4">
            <VCardTitle class="text-h6 font-weight-bold text-white">Auditoría en Curso</VCardTitle>
            <VCardSubtitle class="text-white-50">Resumen y tiempos medidos por fase</VCardSubtitle>
          </VCardItem>

          <VCardText class="pa-6">
            <div class="d-flex flex-column gap-4">
              <div
                v-for="(phase, index) in phases"
                :key="phase.id"
                class="d-flex align-center justify-space-between rounded-lg pa-3 bg-black-industrial border-dark"
                :class="{ 'border-active-neon': currentPhaseIndex === index }"
              >
                <div class="d-flex align-center gap-3">
                  <VAvatar
                    :color="currentPhaseIndex === index ? 'warning' : 'secondary'"
                    variant="tonal"
                    size="32"
                    class="font-weight-black"
                  >
                    {{ index + 1 }}
                  </VAvatar>
                  <div>
                    <span class="font-weight-bold d-block text-white">{{ phase.name }}</span>
                    <span class="text-super-xs text-white-50">{{ phase.description || 'Sin descripción' }}</span>
                  </div>
                </div>
                <div>
                  <span
                    class="font-weight-black text-h6"
                    :class="phaseTimes[phase.id] > 0 ? 'text-success' : currentPhaseIndex === index ? 'text-warning font-neon-glow' : 'text-white-30'"
                  >
                    {{ phaseTimes[phase.id] > 0 ? formatTime(phaseTimes[phase.id]) : currentPhaseIndex === index ? formatTime(elapsedSeconds) : '00:00' }}
                  </span>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Sección de Historial con Promedios Dinámicos al Pie -->
    <VRow class="mt-6">
      <VCol cols="12">
        <VCard class="rounded-xl overflow-hidden border-dark bg-industrial-dark text-white">
          <VCardItem class="bg-carbon py-4">
            <div class="d-flex flex-wrap align-center justify-space-between gap-4">
              <div class="d-flex align-center gap-4">
                <VCardTitle class="text-h6 font-weight-bold text-white">Historial de Auditorías</VCardTitle>
                <div style="min-width: 180px;">
                  <VSelect
                    v-model="selectedFlowIdFilter"
                    :items="flows"
                    item-title="name"
                    item-value="id"
                    placeholder="Filtrar por Flujo"
                    variant="solo"
                    density="compact"
                    clearable
                    hide-details
                  />
                </div>
              </div>
              <div class="d-flex align-center gap-2 flex-wrap">
                <AppDateTimePicker
                  v-model="startDate"
                  placeholder="Fecha Inicio"
                  class="bg-dark-select rounded-lg"
                  density="compact"
                  style="max-width: 150px;"
                />
                <AppDateTimePicker
                  v-model="endDate"
                  placeholder="Fecha Fin"
                  class="bg-dark-select rounded-lg"
                  density="compact"
                  style="max-width: 150px;"
                />
              </div>
            </div>
          </VCardItem>

          <VCardText class="pa-0">
            <div class="overflow-x-auto">
              <VTable class="table-industrial" theme="dark">
                <thead>
                  <tr>
                    <th>Orden</th>
                    <th>Flujo</th>
                    <th>Cajero</th>
                    <th>Cocinero</th>
                    <th
                      v-for="flowPhase in currentFilteredFlowPhases"
                      :key="flowPhase.id"
                      class="text-center"
                    >
                      {{ flowPhase.name }}
                    </th>
                    <th class="text-center font-weight-black">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td :colspan="5 + currentFilteredFlowPhases.length" class="text-center py-6 text-white-50">Cargando registros...</td>
                  </tr>
                  <tr v-else-if="audits.length === 0">
                    <td :colspan="5 + currentFilteredFlowPhases.length" class="text-center py-6 text-white-50">No hay auditorías registradas para este periodo.</td>
                  </tr>
                  <tr v-for="audit in audits" :key="audit.id">
                    <td>
                      <span v-if="audit.order_id" class="font-weight-black text-warning">#{{ audit.order_id }}</span>
                      <span v-else class="text-white-30">N/A</span>
                    </td>
                    <td><span class="text-caption bg-secondary-dark px-2 py-1 rounded">{{ audit.flow?.name }}</span></td>
                    <td>{{ audit.cashier ? (audit.cashier.name + ' ' + (audit.cashier.last_name || '')) : 'Sin registrar' }}</td>
                    <td>{{ audit.cook ? (audit.cook.name + ' ' + (audit.cook.last_name || '')) : 'Sin registrar' }}</td>
                    <td
                      v-for="flowPhase in currentFilteredFlowPhases"
                      :key="flowPhase.id"
                      class="text-center"
                    >
                      {{ getAuditPhaseValue(audit, flowPhase.id) }}
                    </td>
                    <td class="text-center font-weight-black text-warning">{{ formatTime(audit.total_seconds) }}</td>
                  </tr>
                </tbody>
                <!-- Promedios Dinámicos Calculados por Javascript al Pie -->
                <tfoot class="bg-carbon-footer font-weight-bold">
                  <tr>
                    <td colspan="4" class="text-right text-warning uppercase">Promedio Operativo</td>
                    <td
                      v-for="flowPhase in currentFilteredFlowPhases"
                      :key="flowPhase.id"
                      class="text-center text-success"
                    >
                      {{ formatTime(calculateAverageOfPhase(flowPhase.id)) }}
                    </td>
                    <td class="text-center text-warning font-weight-black">{{ formatTime(calculateAverageTotal()) }}</td>
                  </tr>
                </tfoot>
              </VTable>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Diálogo Configurador de Flujos y Fases -->
    <VDialog v-model="flowConfigDialog" max-width="700px" persistent>
      <VCard class="bg-industrial-dark text-white rounded-xl">
        <VCardTitle class="bg-carbon py-4 d-flex justify-space-between align-center">
          <span class="text-h5 font-weight-black text-warning">
            {{ isEditingFlow ? 'Editar Flujo de Proceso' : 'Configurador de Flujos' }}
          </span>
          <VBtn icon="tabler-x" variant="text" color="white" @click="flowConfigDialog = false" />
        </VCardTitle>
        <VCardText class="pa-6">
          <!-- Listado de Flujos Existentes para Editar/Eliminar -->
          <div v-if="!isEditingFlow && flows.length > 0" class="mb-6">
            <span class="label-industrial text-warning font-weight-bold d-block mb-2">Flujos Existentes</span>
            <div class="d-flex flex-column gap-2 mb-4">
              <div
                v-for="flow in flows"
                :key="flow.id"
                class="d-flex align-center justify-space-between bg-black-industrial border-dark rounded-lg pa-3"
              >
                <div>
                  <span class="font-weight-black d-block text-white">{{ flow.name }}</span>
                  <span class="text-caption text-white-50">{{ flow.phases.length }} fases definidas</span>
                </div>
                <div class="d-flex gap-2">
                  <VBtn color="info" density="comfortable" icon="tabler-edit" @click="openEditFlowModal(flow)" />
                  <VBtn color="error" density="comfortable" icon="tabler-trash" @click="deleteFlow(flow.id)" />
                </div>
              </div>
            </div>
            <VDivider class="border-industrial my-4" />
          </div>

          <VForm>
            <VRow dense>
              <VCol cols="12">
                <VTextField
                  v-model="currentFlowForm.name"
                  label="Nombre del Flujo *"
                  placeholder="Ej: Flujo Waffle Combo o Tina Simple"
                  variant="solo"
                  bg-color="rgb(30, 30, 30)"
                  theme="dark"
                  class="mb-3"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="currentFlowForm.description"
                  label="Descripción"
                  placeholder="Detalles sobre cuándo usar este flujo..."
                  variant="solo"
                  bg-color="rgb(30, 30, 30)"
                  theme="dark"
                  rows="2"
                  class="mb-4"
                />
              </VCol>
            </VRow>

            <div class="d-flex justify-space-between align-center mb-4">
              <span class="label-industrial text-warning font-weight-bold">Fases de Medición</span>
              <VBtn
                color="success"
                size="small"
                prepend-icon="tabler-plus"
                @click="addPhaseToForm"
              >
                Añadir Fase
              </VBtn>
            </div>

            <!-- Fases Editables -->
            <div class="d-flex flex-column gap-3 max-height-phases overflow-y-auto pr-2" style="max-height: 250px;">
              <div
                v-for="(phase, index) in currentFlowForm.phases"
                :key="index"
                class="bg-black-industrial border-dark rounded-lg pa-3 d-flex flex-column gap-2"
              >
                <div class="d-flex align-center justify-space-between">
                  <span class="text-caption font-weight-black text-warning">Fase #{{ index + 1 }}</span>
                  <VBtn
                    v-if="currentFlowForm.phases.length > 1"
                    color="error"
                    variant="text"
                    icon="tabler-trash"
                    density="comfortable"
                    @click="removePhaseFromForm(index)"
                  />
                </div>
                <VRow dense>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="phase.name"
                      label="Nombre de Fase *"
                      placeholder="Ej: Preparación de Masa"
                      variant="solo"
                      density="compact"
                      bg-color="rgb(40, 40, 40)"
                      theme="dark"
                      hide-details
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="phase.description"
                      label="Instrucciones/Descripción"
                      placeholder="Ej: Cocer masa por 5 min"
                      variant="solo"
                      density="compact"
                      bg-color="rgb(40, 40, 40)"
                      theme="dark"
                      hide-details
                    />
                  </VCol>
                </VRow>
              </div>
            </div>
          </VForm>
        </VCardText>
        <VCardActions class="pa-6 bg-carbon justify-end">
          <VBtn color="secondary" variant="tonal" class="rounded-lg px-6" @click="flowConfigDialog = false">
            Cancelar
          </VBtn>
          <VBtn color="warning" variant="flat" class="rounded-lg px-6 font-weight-bold" @click="saveFlow">
            Guardar Flujo
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.bg-industrial-dark {
  background-color: #1e1e1e !important;
}
.bg-carbon {
  background-color: #2b2b2b !important;
}
.bg-carbon-footer {
  background-color: #242424 !important;
  border-top: 2px solid #ffb900 !important;
}
.bg-black-industrial {
  background-color: #121212 !important;
}
.border-dark {
  border: 1px solid #333333 !important;
}
.border-dark-neon {
  border: 2px solid #333333 !important;
}
.border-active-neon {
  border: 1px solid #ffb900 !important;
  box-shadow: 0 0 10px rgba(255, 185, 0, 0.2);
}
.text-neon-yellow {
  color: #ffb900 !important;
  text-shadow: 0 0 15px rgba(255, 185, 0, 0.4);
}
.font-neon-glow {
  text-shadow: 0 0 8px rgba(255, 185, 0, 0.3);
}
.border-neon-glow {
  border: 1px solid #ffb900 !important;
}
.digital-clock {
  font-size: 4rem;
  letter-spacing: 0.1em;
  font-family: "Courier New", Courier, monospace;
}
.table-industrial th {
  background-color: #2b2b2b !important;
  color: #ffb900 !important;
  font-weight: 900 !important;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.05em;
  white-space: nowrap;
}
.table-industrial td {
  border-bottom: 1px solid #333333 !important;
  white-space: nowrap;
}
.label-industrial {
  font-size: 0.8rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}
.text-white-50 {
  color: rgba(255, 255, 255, 0.5) !important;
}
.text-white-70 {
  color: rgba(255, 255, 255, 0.7) !important;
}
.text-white-30 {
  color: rgba(255, 255, 255, 0.3) !important;
}
.text-super-xs {
  font-size: 0.7rem;
}
.max-height-phases::-webkit-scrollbar {
  width: 6px;
}
.max-height-phases::-webkit-scrollbar-thumb {
  background-color: #ffb900;
  border-radius: 4px;
}
</style>
