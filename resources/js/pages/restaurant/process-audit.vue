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
  <div class="process-audit-container pb-12">
    <!-- Header e Inicio de Flujo / Configuración -->
    <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
      <div>
        <h2 class="text-h4 font-weight-black text-primary">Auditoría Operativa de Tiempos</h2>
        <p class="text-xs font-weight-medium text-disabled">Módulo de cronometraje de fases de servicio y preparación</p>
      </div>
      <div class="d-flex gap-2">
        <VBtn
          color="primary"
          variant="tonal"
          prepend-icon="tabler-settings"
          @click="openNewFlowModal"
        >
          Configurar Flujos
        </VBtn>
      </div>
    </div>

    <VRow class="mb-6">
      <!-- Panel de Control y Cronómetro (Integración con estilo nativo de Vuetify) -->
      <VCol cols="12" md="7">
        <VCard class="rounded-lg border shadow-sm bg-surface">
          <VCardItem class="pa-4 pb-0">
            <template #prepend>
              <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
                <VIcon icon="tabler-hourglass-high" size="20" />
              </VAvatar>
            </template>
            <div class="d-flex align-center justify-space-between flex-wrap gap-3 w-100">
              <div>
                <VCardTitle class="text-subtitle-1 font-weight-black uppercase">Cronómetro de Auditoría</VCardTitle>
                <VCardSubtitle class="text-xs font-weight-medium text-disabled">Inicie y controle el cronómetro del flujo</VCardSubtitle>
              </div>
              <div style="min-width: 200px;">
                <VSelect
                  v-model="selectedFlowId"
                  :items="flows"
                  item-title="name"
                  item-value="id"
                  density="compact"
                  hide-details
                  prepend-inner-icon="tabler-git-commit"
                  placeholder="Seleccionar Flujo"
                />
              </div>
            </div>
          </VCardItem>

          <VCardText class="pa-4 pt-6">
            <VForm>
              <VRow dense>
                <VCol cols="12">
                  <VSelect
                    v-model="selectedOrderId"
                    :items="orders"
                    item-title="id"
                    item-value="id"
                    placeholder="Asociar Orden Activa (Opcional)"
                    density="compact"
                    clearable
                    prepend-inner-icon="tabler-clipboard-text"
                  >
                    <template #item="{ props, item }">
                      <VListItem v-bind="props">
                        <template #title>
                          <span class="font-weight-black">Orden #{{ item.raw.id }}</span>
                          <span class="ms-2 text-disabled">(${{ item.raw.total_amount_usd }} USD)</span>
                        </template>
                        <template #subtitle>
                          <span class="text-caption text-disabled">
                            Cliente: {{ item.raw.client?.name || 'Cliente de paso' }}
                          </span>
                        </template>
                      </VListItem>
                    </template>
                  </VSelect>
                </VCol>

                <VCol cols="12" md="6" class="mt-2">
                  <VSelect
                    v-model="selectedCashierId"
                    :items="employees"
                    item-title="name"
                    item-value="id"
                    placeholder="Cajero en Turno (Opcional)"
                    density="compact"
                    clearable
                    prepend-inner-icon="tabler-user"
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
                  <VSelect
                    v-model="selectedCookId"
                    :items="employees"
                    item-title="name"
                    item-value="id"
                    placeholder="Cocinero en Turno *"
                    density="compact"
                    prepend-inner-icon="tabler-chef-hat"
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

              <VDivider class="my-6" />

              <!-- Cronómetro estilo nativo Premium -->
              <div v-if="phases.length > 0" class="text-center rounded-lg py-6 px-4 bg-var-theme-background border">
                <span class="text-overline text-disabled tracking-wide uppercase">Fase {{ currentPhaseIndex + 1 }} de {{ phases.length }}</span>
                <h3 class="text-h4 font-weight-black mt-1 text-primary">{{ currentPhase?.name }}</h3>
                <p class="text-body-2 text-disabled mt-1 mb-4 italic">"{{ currentPhase?.description || 'Sin descripción' }}"</p>

                <!-- Reloj Digital con Fuente Estilo Monospace -->
                <div class="font-weight-black my-4 text-primary" style="font-size: 3.5rem; font-family: monospace; letter-spacing: 0.05em;">
                  {{ formatTime(elapsedSeconds) }}
                </div>

                <div class="d-flex justify-center align-center gap-2 flex-wrap mt-6">
                  <VBtn
                    v-if="!isTimerRunning"
                    color="success"
                    size="large"
                    prepend-icon="tabler-play"
                    class="font-weight-bold"
                    @click="startTimer"
                  >
                    Iniciar
                  </VBtn>
                  <VBtn
                    v-else
                    color="warning"
                    size="large"
                    prepend-icon="tabler-pause"
                    class="font-weight-bold"
                    @click="pauseTimer"
                  >
                    Pausar
                  </VBtn>

                  <VBtn
                    color="info"
                    size="large"
                    prepend-icon="tabler-arrow-narrow-right"
                    class="font-weight-bold"
                    @click="nextPhase"
                  >
                    Siguiente
                  </VBtn>

                  <VBtn
                    color="secondary"
                    variant="tonal"
                    size="large"
                    prepend-icon="tabler-refresh"
                    @click="resetTimer"
                  >
                    Reiniciar
                  </VBtn>
                </div>
              </div>
              <div v-else class="text-center py-8 text-disabled border rounded-lg bg-var-theme-background">
                Seleccione un flujo de medición para habilitar el cronómetro de fases.
              </div>

              <!-- Registro Integrado -->
              <div class="mt-6">
                <VBtn
                  color="primary"
                  block
                  size="large"
                  :loading="loadingForm"
                  prepend-icon="tabler-device-floppy"
                  class="font-weight-bold"
                  @click="saveAudit"
                >
                  Registrar e Integrar en Base de Datos
                </VBtn>
              </div>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Panel de Resumen de Tiempos Registrados para la Orden en Curso -->
      <VCol cols="12" md="5">
        <VCard class="rounded-lg border shadow-sm bg-surface h-100">
          <VCardItem class="pa-4 pb-0">
            <template #prepend>
              <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
                <VIcon icon="tabler-clipboard-list" size="20" />
              </VAvatar>
            </template>
            <VCardTitle class="text-subtitle-1 font-weight-black uppercase">Fases del Flujo</VCardTitle>
            <VCardSubtitle class="text-xs font-weight-medium text-disabled">Resumen de tiempos acumulados</VCardSubtitle>
          </VCardItem>

          <VCardText class="pa-4 pt-6">
            <div class="d-flex flex-column gap-3">
              <div
                v-for="(phase, index) in phases"
                :key="phase.id"
                class="d-flex align-center justify-space-between rounded-lg pa-3 border"
                :class="{ 'bg-var-theme-background border-primary': currentPhaseIndex === index }"
              >
                <div class="d-flex align-center gap-3">
                  <VAvatar
                    :color="currentPhaseIndex === index ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="32"
                    class="font-weight-black"
                  >
                    {{ index + 1 }}
                  </VAvatar>
                  <div>
                    <span class="font-weight-bold d-block text-sm">{{ phase.name }}</span>
                    <span class="text-caption text-disabled">{{ phase.description || 'Sin descripción' }}</span>
                  </div>
                </div>
                <div>
                  <span
                    class="font-weight-black text-h6"
                    :class="phaseTimes[phase.id] > 0 ? 'text-success' : currentPhaseIndex === index ? 'text-primary' : 'text-disabled'"
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
    <VRow>
      <VCol cols="12">
        <VCard class="rounded-lg border shadow-sm bg-surface">
          <VCardItem class="pa-4">
            <div class="d-flex flex-wrap align-center justify-space-between gap-4 w-100">
              <div class="d-flex align-center gap-4">
                <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
                  <VIcon icon="tabler-history" size="20" />
                </VAvatar>
                <div>
                  <VCardTitle class="text-subtitle-1 font-weight-black uppercase">Historial de Auditorías</VCardTitle>
                  <VCardSubtitle class="text-xs font-weight-medium text-disabled">Consolidado de auditorías operativas</VCardSubtitle>
                </div>
              </div>
              <div class="d-flex align-center gap-2 flex-wrap">
                <div style="min-width: 180px;">
                  <VSelect
                    v-model="selectedFlowIdFilter"
                    :items="flows"
                    item-title="name"
                    item-value="id"
                    placeholder="Filtrar por Flujo"
                    density="compact"
                    clearable
                    hide-details
                  />
                </div>
                <AppDateTimePicker
                  v-model="startDate"
                  placeholder="Fecha Inicio"
                  density="compact"
                  clearable
                  hide-details
                  style="max-width: 150px;"
                />
                <AppDateTimePicker
                  v-model="endDate"
                  placeholder="Fecha Fin"
                  density="compact"
                  clearable
                  hide-details
                  style="max-width: 150px;"
                />
              </div>
            </div>
          </VCardItem>

          <VCardText class="pa-0">
            <div class="overflow-x-auto">
              <VTable class="text-no-wrap premium-table" theme="dark">
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
                    <th class="text-center font-weight-black text-primary">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td :colspan="5 + currentFilteredFlowPhases.length" class="text-center py-6 text-disabled">Cargando registros...</td>
                  </tr>
                  <tr v-else-if="audits.length === 0">
                    <td :colspan="5 + currentFilteredFlowPhases.length" class="text-center py-6 text-disabled">No hay auditorías registradas para este periodo.</td>
                  </tr>
                  <tr v-for="audit in audits" :key="audit.id">
                    <td>
                      <span v-if="audit.order_id" class="font-weight-black">#{{ audit.order_id }}</span>
                      <span v-else class="text-disabled">N/A</span>
                    </td>
                    <td><span class="text-caption bg-var-theme-background border px-2 py-1 rounded">{{ audit.flow?.name }}</span></td>
                    <td>{{ audit.cashier ? (audit.cashier.name + ' ' + (audit.cashier.last_name || '')) : '—' }}</td>
                    <td>{{ audit.cook ? (audit.cook.name + ' ' + (audit.cook.last_name || '')) : '—' }}</td>
                    <td
                      v-for="flowPhase in currentFilteredFlowPhases"
                      :key="flowPhase.id"
                      class="text-center"
                    >
                      {{ getAuditPhaseValue(audit, flowPhase.id) }}
                    </td>
                    <td class="text-center font-weight-black text-primary">{{ formatTime(audit.total_seconds) }}</td>
                  </tr>
                </tbody>
                <!-- Promedios Dinámicos Calculados por Javascript al Pie -->
                <tfoot class="font-weight-bold" style="border-top: 2px solid var(--v-theme-primary);">
                  <tr>
                    <td colspan="4" class="text-right text-primary uppercase">Promedio Operativo</td>
                    <td
                      v-for="flowPhase in currentFilteredFlowPhases"
                      :key="flowPhase.id"
                      class="text-center text-success"
                    >
                      {{ formatTime(calculateAverageOfPhase(flowPhase.id)) }}
                    </td>
                    <td class="text-center text-primary font-weight-black">{{ formatTime(calculateAverageTotal()) }}</td>
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
      <VCard class="rounded-lg">
        <VCardTitle class="pa-4 d-flex justify-space-between align-center">
          <span class="text-h5 font-weight-black text-primary">
            {{ isEditingFlow ? 'Editar Flujo de Proceso' : 'Configurador de Flujos' }}
          </span>
          <VBtn icon="tabler-x" variant="text" color="default" @click="flowConfigDialog = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <!-- Listado de Flujos Existentes para Editar/Eliminar -->
          <div v-if="!isEditingFlow && flows.length > 0" class="mb-6">
            <span class="text-subtitle-2 font-weight-bold d-block mb-2 text-primary">Flujos Existentes</span>
            <div class="d-flex flex-column gap-2 mb-4">
              <div
                v-for="flow in flows"
                :key="flow.id"
                class="d-flex align-center justify-space-between border rounded-lg pa-3"
              >
                <div>
                  <span class="font-weight-black d-block">{{ flow.name }}</span>
                  <span class="text-caption text-disabled">{{ flow.phases.length }} fases definidas</span>
                </div>
                <div class="d-flex gap-2">
                  <VBtn color="info" density="comfortable" icon="tabler-edit" @click="openEditFlowModal(flow)" />
                  <VBtn color="error" density="comfortable" icon="tabler-trash" @click="deleteFlow(flow.id)" />
                </div>
              </div>
            </div>
            <VDivider class="my-4" />
          </div>

          <VForm>
            <VRow dense>
              <VCol cols="12">
                <VTextField
                  v-model="currentFlowForm.name"
                  label="Nombre del Flujo *"
                  placeholder="Ej: Flujo Waffle Combo o Tina Simple"
                  density="compact"
                  class="mb-3"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="currentFlowForm.description"
                  label="Descripción"
                  placeholder="Detalles sobre cuándo usar este flujo..."
                  density="compact"
                  rows="2"
                  class="mb-4"
                />
              </VCol>
            </VRow>

            <div class="d-flex justify-space-between align-center mb-4">
              <span class="text-subtitle-2 font-weight-bold text-primary">Fases de Medición</span>
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
                class="border rounded-lg pa-3 d-flex flex-column gap-2"
              >
                <div class="d-flex align-center justify-space-between">
                  <span class="text-caption font-weight-black text-primary">Fase #{{ index + 1 }}</span>
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
                      density="compact"
                      hide-details
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="phase.description"
                      label="Descripción"
                      placeholder="Ej: Cocer masa por 5 min"
                      density="compact"
                      hide-details
                    />
                  </VCol>
                </VRow>
              </div>
            </div>
          </VForm>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 justify-end">
          <VBtn color="secondary" variant="tonal" class="rounded-lg px-6" @click="flowConfigDialog = false">
            Cancelar
          </VBtn>
          <VBtn color="primary" class="rounded-lg px-6 font-weight-bold" @click="saveFlow">
            Guardar Flujo
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.premium-table th {
  font-weight: 900 !important;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.05em;
  white-space: nowrap;
}
.premium-table td {
  white-space: nowrap;
}
.max-height-phases::-webkit-scrollbar {
  width: 6px;
}
.max-height-phases::-webkit-scrollbar-thumb {
  background-color: var(--v-theme-primary);
  border-radius: 4px;
}
</style>
