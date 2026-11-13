<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import RestaurantSimulatorWidget from "@/components/restaurant/RestaurantSimulatorWidget.vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore();

// Estado de KDS
const loading = ref(false);
const bumpingTicketId = ref(null);
const updatingItemId = ref(null);
const tickets = ref([]);
const stations = ref([]);
const selectedStationId = ref(null);
const fullScreen = ref(false);
const activeViewTab = ref("active"); // 'active' (En Cocina) | 'ready' (En Pase / Listos)
const soundEnabled = ref(true);
const showAllDaySummary = ref(false);
const kdsContainerRef = ref(null);
const previousTicketIds = ref(new Set());
const kdsMetrics = ref({
  completed_today: 0,
  avg_prep_minutes: 0,
  on_time_percentage: 100,
});

// Modal de Foto y Receta del Plato
const showRecipeDialog = ref(false);
const selectedDishItem = ref(null);

const isMultiStation = computed(() => {
  return brandingStore.settings?.kds_multi_station !== false;
});

const showCourseHeaders = computed(() => {
  const courses = brandingStore.settings?.kds_courses;
  return Array.isArray(courses) && courses.length > 1;
});

// Métricas en vivo
const currentTime = ref(Date.now());
let timerInterval = null;
let pollInterval = null;

// Cargar estaciones de cocina
const fetchStations = async () => {
  try {
    const { data } = await axios.get("/kitchen-stations");
    stations.value = data.data || [];
  } catch (error) {
    console.error("Error al cargar estaciones:", error);
  }
};

// Alerta sonora corta con Web Audio API al recibir nueva comanda
const playNewTicketSound = () => {
  if (!soundEnabled.value) return;
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    const ctx = new AudioCtx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = "sine";
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    gain.gain.setValueAtTime(0.08, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.25);
  } catch (e) {
    // Ignorar si el navegador bloquea audio sin interacción previa
  }
};

// Cargar comandas activas
const fetchTickets = async (silent = false) => {
  if (!silent) loading.value = true;
  try {
    const params = {
      include_ready: activeViewTab.value === "ready" ? 1 : 0,
    };
    if (isMultiStation.value && selectedStationId.value) {
      params.station_id = selectedStationId.value;
    }
    const { data } = await axios.get("/kds/tickets", { params });
    const fetched = data.data || [];

    // Detectar comandas nuevas para emitir alerta sonora
    if (silent && activeViewTab.value === "active") {
      const hasNew = fetched.some((t) => !previousTicketIds.value.has(t.id));
      if (hasNew && previousTicketIds.value.size > 0) {
        playNewTicketSound();
      }
    }
    previousTicketIds.value = new Set(fetched.map((t) => t.id));
    tickets.value = fetched;

    // Cargar métricas de rendimiento en paralelo sin bloquear
    const metricsParams = {};
    if (isMultiStation.value && selectedStationId.value) {
      metricsParams.station_id = selectedStationId.value;
    }
    axios.get("/kds/metrics", { params: metricsParams }).then((res) => {
      if (res.data?.data) kdsMetrics.value = res.data.data;
    }).catch(() => {});
  } catch (error) {
    console.error("Error al cargar comandas KDS:", error);
  } finally {
    if (!silent) loading.value = false;
  }
};

// Tickets filtrados según la pestaña activa (En Cocina vs En Pase) en orden FIFO (primero la más antigua)
const displayedTickets = computed(() => {
  const targetStatuses = activeViewTab.value === "ready"
    ? ["ready"]
    : ["queued", "preparing"];

  return tickets.value
    .map((t) => ({
      ...t,
      items: (t.items || []).filter((i) => targetStatuses.includes(i.kitchen_status)),
    }))
    .filter((t) => t.items.length > 0)
    .sort((a, b) => a.id - b.id);
});

const openRecipeModal = (item) => {
  selectedDishItem.value = item;
  showRecipeDialog.value = true;
};

const updateItemDirectStatus = async (item, status, label) => {
  if (updatingItemId.value === item.id) return;
  updatingItemId.value = item.id;
  try {
    await axios.patch(`/kds/items/${item.id}/status`, { status });
    toast.success(`Plato actualizado a ${label}`);
    fetchTickets(true);
  } catch (error) {
    console.error("Error al actualizar plato:", error);
    toast.error("No se pudo actualizar el estado.");
  } finally {
    updatingItemId.value = null;
  }
};

// Conteo total de ítems por estación
const totalPendingCount = computed(() => {
  return tickets.value.reduce((acc, ticket) => acc + (ticket.items?.length || 0), 0);
});

const inQueueCount = computed(() => {
  return tickets.value.reduce((acc, ticket) => {
    return acc + (ticket.items?.filter((i) => i.kitchen_status === "queued").length || 0);
  }, 0);
});

const inPreparingCount = computed(() => {
  return tickets.value.reduce((acc, ticket) => {
    return acc + (ticket.items?.filter((i) => i.kitchen_status === "preparing").length || 0);
  }, 0);
});

// Cambiar estado individual de un plato
const handleNextStatus = async (item) => {
  const nextMap = {
    queued: "preparing",
    preparing: "ready",
  };
  const next = nextMap[item.kitchen_status];
  if (!next || updatingItemId.value === item.id) return;

  updatingItemId.value = item.id;
  try {
    await axios.patch(`/kds/items/${item.id}/status`, { status: next });
    toast.success(`Plato actualizado a ${next === "preparing" ? "Preparando" : "Listo"}`);
    fetchTickets(true);
  } catch (error) {
    console.error("Error al actualizar plato:", error);
    toast.error("No se pudo actualizar el estado.");
  } finally {
    updatingItemId.value = null;
  }
};

// Despachar comanda completa (Bump Ticket)
const handleBumpTicket = async (ticket) => {
  if (bumpingTicketId.value === ticket.id) return;

  bumpingTicketId.value = ticket.id;
  try {
    await axios.post(`/kds/orders/${ticket.id}/bump`, {
      station_id: selectedStationId.value,
    });
    toast.success(`Comanda #${ticket.id} despachada.`);
    fetchTickets(true);
  } catch (error) {
    console.error("Error al despachar comanda:", error);
    toast.error("No se pudo despachar la comanda.");
  } finally {
    bumpingTicketId.value = null;
  }
};

// Resumen consolidado All-Day por platillo (para producción por lotes)
const allDaySummary = computed(() => {
  const map = new Map();
  displayedTickets.value.forEach((ticket) => {
    (ticket.items || []).forEach((item) => {
      const key = item.dish_name || "Plato";
      const current = map.get(key) || { name: key, qty: 0 };
      current.qty += Number(item.quantity || 1);
      map.set(key, current);
    });
  });
  return Array.from(map.values()).sort((a, b) => b.qty - a.qty);
});

// Cálculo de tiempo transcurrido (mm:ss o Xh Ym si >= 60m) y estado semáforo
const getElapsedTime = (commandedAt) => {
  if (!commandedAt) return "00:00";
  const start = new Date(commandedAt).getTime();
  const diffSec = Math.max(0, Math.floor((currentTime.value - start) / 1000));
  const min = Math.floor(diffSec / 60);
  const sec = diffSec % 60;
  if (min >= 60) {
    const hrs = Math.floor(min / 60);
    const remMin = min % 60;
    return `${hrs}h ${String(remMin).padStart(2, "0")}m`;
  }
  return `${String(min).padStart(2, "0")}:${String(sec).padStart(2, "0")}`;
};

// Semáforo inteligente según tiempo transcurrido vs estimado
const getUrgencyColor = (commandedAt, prepMinutes = 15) => {
  if (!commandedAt) return "success";
  const start = new Date(commandedAt).getTime();
  const elapsedMin = (currentTime.value - start) / (1000 * 60);

  if (elapsedMin >= prepMinutes) return "error"; // Retrasado (> 100%)
  if (elapsedMin >= prepMinutes * 0.7) return "warning"; // Precaución (70% - 100%)
  return "success"; // A tiempo (< 70%)
};

// Agrupar ítems de un ticket por curso/tiempo
const groupItemsByCourse = (items = []) => {
  const groups = {};
  items.forEach((item) => {
    const course = item.course_number || 1;
    if (!groups[course]) groups[course] = [];
    groups[course].push(item);
  });
  return groups;
};

// Pantalla completa táctil sobre el contenedor KDS (oculta sidebar y topbar)
const toggleFullScreen = () => {
  const target = kdsContainerRef.value || document.documentElement;
  if (!document.fullscreenElement) {
    target.requestFullscreen().catch(() => {});
    fullScreen.value = true;
  } else {
    if (document.exitFullscreen) {
      document.exitFullscreen().catch(() => {});
      fullScreen.value = false;
    }
  }
};

// Atajos de teclado estilo Bump Bar (1-9 despachar, A: All-Day, R: Refrescar, F: Fullscreen, P: Pase)
const handleKeydown = (e) => {
  if (showRecipeDialog.value) return;
  const tag = e.target?.tagName?.toLowerCase();
  if (tag === "input" || tag === "textarea") return;

  if (e.key >= "1" && e.key <= "9" && activeViewTab.value === "active") {
    const idx = Number(e.key) - 1;
    const targetTicket = displayedTickets.value[idx];
    if (targetTicket) {
      e.preventDefault();
      handleBumpTicket(targetTicket);
    }
  } else if (e.key.toLowerCase() === "a") {
    showAllDaySummary.value = !showAllDaySummary.value;
  } else if (e.key.toLowerCase() === "r") {
    fetchTickets(false);
  } else if (e.key.toLowerCase() === "f") {
    toggleFullScreen();
  } else if (e.key.toLowerCase() === "p") {
    activeViewTab.value = activeViewTab.value === "active" ? "ready" : "active";
    fetchTickets(false);
  }
};

onMounted(() => {
  fetchStations();
  fetchTickets();
  window.addEventListener("keydown", handleKeydown);

  // Actualizar cronómetro cada segundo
  timerInterval = setInterval(() => {
    currentTime.value = Date.now();
  }, 1000);

  // Polling silencioso cada 5 segundos para recibir comandas nuevas en tiempo real
  pollInterval = setInterval(() => {
    fetchTickets(true);
  }, 5000);
});

onUnmounted(() => {
  window.removeEventListener("keydown", handleKeydown);
  if (timerInterval) clearInterval(timerInterval);
  if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
  <div ref="kdsContainerRef" class="kds-view bg-background pa-2 pb-12 overflow-y-auto">
    <!-- Header de Cocina / KDS Bar -->
    <VCard variant="flat" class="border rounded-lg mb-4 bg-surface">
      <div class="pa-4 d-flex flex-wrap align-center justify-space-between gap-4">
        <!-- Título e Identidad -->
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="flat" size="44" class="elevation-1">
            <VIcon icon="tabler-chef-hat" color="white" size="24" />
          </VAvatar>
          <div>
            <div class="d-flex align-center gap-2">
              <h2 class="text-h6 font-weight-black text-uppercase mb-0">
                KDS — Pantalla de Cocina
              </h2>
              <VChip size="x-small" color="success" variant="flat" class="font-weight-black">
                EN VIVO
              </VChip>
            </div>
            <div class="text-xs text-medium-emphasis">
              Gestión y despacho táctil de comandas en tiempo real
            </div>
          </div>
        </div>

        <!-- Indicadores / KPIs Rápidos de Cocina -->
        <div class="d-flex align-center flex-wrap gap-2">
          <VChip color="info" variant="tonal" size="large" class="font-weight-black">
            <VIcon icon="tabler-hourglass-empty" start size="18" />
            En Cola: <span class="ms-1 text-h6 font-weight-black">{{ inQueueCount }}</span>
          </VChip>

          <VChip color="warning" variant="tonal" size="large" class="font-weight-black">
            <VIcon icon="tabler-flame" start size="18" />
            En Fuego: <span class="ms-1 text-h6 font-weight-black">{{ inPreparingCount }}</span>
          </VChip>

          <VChip color="primary" variant="tonal" size="large" class="font-weight-black">
            <VIcon icon="tabler-receipt" start size="18" />
            Comandas: <span class="ms-1 text-h6 font-weight-black">{{ displayedTickets.length }}</span>
          </VChip>

          <VChip
            v-if="kdsMetrics.completed_today > 0"
            color="success"
            variant="tonal"
            size="default"
            class="font-weight-bold"
            :title="`Despachados hoy: ${kdsMetrics.completed_today} platos`"
          >
            <VIcon icon="tabler-clock-check" start size="16" />
            Prom: {{ kdsMetrics.avg_prep_minutes }}m ({{ kdsMetrics.on_time_percentage }}% SLA)
          </VChip>

          <VBtn
            size="small"
            :variant="showAllDaySummary ? 'flat' : 'tonal'"
            color="info"
            prepend-icon="tabler-list-numbers"
            @click="showAllDaySummary = !showAllDaySummary"
          >
            All-Day
          </VBtn>

          <VBtn
            icon
            variant="tonal"
            :color="soundEnabled ? 'primary' : 'secondary'"
            size="small"
            :title="soundEnabled ? 'Silenciar alertas' : 'Activar alertas sonoras'"
            @click="soundEnabled = !soundEnabled"
          >
            <VIcon :icon="soundEnabled ? 'tabler-volume' : 'tabler-volume-off'" />
          </VBtn>

          <VBtn
            icon
            variant="tonal"
            color="secondary"
            size="small"
            :loading="loading"
            @click="fetchTickets(false)"
          >
            <VIcon icon="tabler-refresh" />
          </VBtn>

          <VBtn icon variant="tonal" color="secondary" size="small" @click="toggleFullScreen">
            <VIcon :icon="fullScreen ? 'tabler-minimize' : 'tabler-maximize'" />
          </VBtn>
        </div>
      </div>

      <!-- Pestañas de Flujo (En Cocina vs En Pase) y Filtro por Estación -->
      <VDivider />
      <div class="pa-2 px-4 bg-light d-flex flex-wrap gap-2 align-center justify-space-between">
        <div class="d-flex flex-wrap gap-2 align-center">
          <VBtnToggle
            v-model="activeViewTab"
            mandatory
            density="compact"
            color="primary"
            variant="outlined"
            @update:model-value="fetchTickets(false)"
          >
            <VBtn value="active" size="small" prepend-icon="tabler-flame">
              En Cocina
            </VBtn>
            <VBtn value="ready" size="small" prepend-icon="tabler-bell-ringing">
              En Pase / Listos
            </VBtn>
          </VBtnToggle>

          <template v-if="isMultiStation">
            <VDivider vertical class="mx-1" />
            <span class="text-xs font-weight-black uppercase text-medium-emphasis">Estación:</span>
            <VBtn
              size="small"
              :variant="selectedStationId === null ? 'flat' : 'outlined'"
              :color="selectedStationId === null ? 'primary' : 'secondary'"
              class="font-weight-bold"
              @click="selectedStationId = null; fetchTickets(false)"
            >
              Todas ({{ totalPendingCount }})
            </VBtn>

            <VBtn
              v-for="st in stations"
              :key="st.id"
              size="small"
              :variant="selectedStationId === st.id ? 'flat' : 'outlined'"
              :color="selectedStationId === st.id ? 'primary' : 'secondary'"
              class="font-weight-bold"
              @click="selectedStationId = st.id; fetchTickets(false)"
            >
              <VIcon :icon="st.icon || 'tabler-chef-hat'" start size="16" />
              {{ st.name }}
            </VBtn>
          </template>
        </div>
      </div>

      <!-- Barra Consolidada All-Day (Resumen por Plato) -->
      <VExpandTransition>
        <div v-if="showAllDaySummary && allDaySummary.length > 0">
          <VDivider />
          <div class="pa-3 bg-surface d-flex flex-wrap align-center gap-2">
            <span class="text-xs font-weight-black text-uppercase text-medium-emphasis me-2">
              Total Pendiente por Plato:
            </span>
            <VChip
              v-for="row in allDaySummary"
              :key="row.name"
              size="small"
              color="primary"
              variant="tonal"
              class="font-weight-bold"
            >
              <span class="font-weight-black me-1">{{ row.qty }}x</span> {{ row.name }}
            </VChip>
          </div>
        </div>
      </VExpandTransition>
    </VCard>

    <!-- Estado de Carga Inicial -->
    <VRow v-if="loading && displayedTickets.length === 0">
      <VCol v-for="n in 4" :key="n" cols="12" sm="6" md="4" lg="3">
        <VSkeletonLoader type="card, list-item-two-line, actions" class="rounded-lg border" />
      </VCol>
    </VRow>

    <!-- Estado Vacío (Sin Comandas Pendientes) -->
    <div v-else-if="displayedTickets.length === 0 && !loading" class="pa-16 text-center text-disabled">
      <VIcon icon="tabler-clipboard-check" size="72" class="mb-4 opacity-30 text-success" />
      <h3 class="text-h6 font-weight-black uppercase text-high-emphasis opacity-60">
        {{ activeViewTab === 'ready' ? 'Sin platos esperando en el pase' : '¡Cocina al día! Sin comandas pendientes' }}
      </h3>
      <p class="text-sm">
        {{ activeViewTab === 'ready' ? 'Los platos marcados como listos aparecerán aquí hasta ser entregados.' : 'Las órdenes comandadas desde el TPV aparecerán automáticamente aquí.' }}
      </p>
    </div>

    <!-- Grid Táctil de Comandas (Tickets) -->
    <VRow v-else align="start">
      <VCol
        v-for="(ticket, ticketIdx) in displayedTickets"
        :key="ticket.id"
        cols="12"
        sm="6"
        md="4"
        lg="3"
      >
        <VCard
          variant="flat"
          class="w-100 border rounded-lg overflow-hidden d-flex flex-column ticket-card shadow-sm"
          style="max-height: calc(100vh - 210px);"
        >
          <!-- Barra superior de urgencia (sin saturar toda la cabecera) -->
          <div
            style="height: 6px; width: 100%;"
            :class="`bg-${getUrgencyColor(ticket.items?.[0]?.commanded_at, ticket.items?.[0]?.preparation_time_minutes)}`"
          />

          <!-- Encabezado limpio de la Comanda: ID - Mesa (PrimerNombre) -> Cronómetro -->
          <div class="pa-3 bg-surface border-b flex-shrink-0">
            <div class="d-flex align-center justify-space-between gap-2">
              <div class="font-weight-black text-subtitle-1 text-uppercase text-truncate" style="min-width: 0; flex: 1;">
                <span class="text-primary">{{ ticket.id }}</span>
                <span class="text-high-emphasis"> - {{ ticket.dining_table_name || 'Salón' }}</span>
                <span v-if="ticket.seller_name" class="text-medium-emphasis font-weight-bold text-body-2 text-capitalize">
                  ({{ String(ticket.seller_name).trim().split(/\s+/)[0] }})
                </span>
              </div>

              <VChip
                :color="getUrgencyColor(ticket.items?.[0]?.commanded_at, ticket.items?.[0]?.preparation_time_minutes)"
                variant="flat"
                size="small"
                class="font-weight-black font-mono flex-shrink-0"
              >
                {{ getElapsedTime(ticket.items?.[0]?.commanded_at) }}
              </VChip>
            </div>
          </div>

          <!-- Cuerpo: Lista de Platos por Tiempos (con scroll interno si excede la pantalla) -->
          <VCardText class="pa-2 bg-surface d-flex flex-column gap-2 flex-grow-1 overflow-y-auto">
            <div
              v-for="(courseItems, courseNum) in groupItemsByCourse(ticket.items)"
              :key="courseNum"
              class="course-section"
            >
              <!-- Separador de Tiempo (Entrada / Fuerte / Postre) -->
              <div v-if="showCourseHeaders" class="d-flex align-center gap-2 mb-2">
                <VChip
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  label
                  class="font-weight-black text-uppercase flex-shrink-0"
                >
                  {{ courseItems[0]?.course_label || `${courseNum}° Tiempo` }}
                </VChip>
                <VDivider />
              </div>

              <!-- Fila Táctil de Cada Plato -->
              <div class="d-flex flex-column gap-2">
                <div
                  v-for="item in courseItems"
                  :key="item.id"
                  class="dish-item-card pa-3 rounded border cursor-pointer transition-all"
                  :class="{
                    'dish-item-preparing': item.kitchen_status === 'preparing',
                    'dish-item-queued': item.kitchen_status === 'queued',
                    'dish-item-ready': item.kitchen_status === 'ready',
                    'opacity-50 pointer-events-none': updatingItemId === item.id,
                  }"
                  @click="handleNextStatus(item)"
                >
                  <!-- Fila 1: Cantidad (solo si > 1) + Nombre del plato + Ícono de Receta a la derecha -->
                  <div class="d-flex align-start justify-space-between gap-2">
                    <div class="d-flex align-baseline gap-1" style="min-width: 0; flex: 1;">
                      <span
                        v-if="item.quantity > 1"
                        class="font-weight-black text-h6 text-primary flex-shrink-0"
                        style="line-height: 1.1;"
                      >
                        {{ item.quantity }}<span class="text-body-1 font-weight-bold text-lowercase">x</span>
                      </span>
                      <span class="font-weight-black text-body-1 text-high-emphasis text-uppercase" style="line-height: 1.15;">
                        {{ item.dish_name }}
                      </span>
                    </div>

                    <VBtn
                      icon="tabler-book-2"
                      size="x-small"
                      variant="tonal"
                      color="info"
                      class="flex-shrink-0"
                      title="Ver Receta y Foto"
                      @click.stop="openRecipeModal(item)"
                    />
                  </div>

                  <!-- Estación KDS (solo si hay múltiples estaciones) -->
                  <div v-if="item.kitchen_station && isMultiStation" class="mt-1">
                    <VChip
                      size="x-small"
                      :color="item.kitchen_station.color || 'secondary'"
                      variant="tonal"
                    >
                      <VIcon :icon="item.kitchen_station.icon || 'tabler-flame'" start size="12" />
                      {{ item.kitchen_station.name }}
                    </VChip>
                  </div>

                  <!-- Acciones de Pase (solo en pestaña En Pase / Listos) -->
                  <div v-if="activeViewTab === 'ready'" class="d-flex align-center gap-2 mt-2" @click.stop>
                    <VBtn
                      size="x-small"
                      color="success"
                      variant="flat"
                      prepend-icon="tabler-check"
                      class="flex-grow-1"
                      :loading="updatingItemId === item.id"
                      @click="updateItemDirectStatus(item, 'served', 'Servido')"
                    >
                      Entregado al Mesero
                    </VBtn>
                    <VBtn
                      size="x-small"
                      color="warning"
                      variant="tonal"
                      icon="tabler-arrow-back-up"
                      title="Revertir a cocina"
                      :loading="updatingItemId === item.id"
                      @click="updateItemDirectStatus(item, 'preparing', 'Preparando')"
                    />
                  </div>

                  <!-- Detalles estilo lista (Notas / Término y Modificadores) -->
                  <ul
                    v-if="item.notes || (item.modifiers && item.modifiers.length > 0)"
                    class="dish-details-list mt-1 ps-4 mb-0 text-body-2 font-weight-medium text-medium-emphasis"
                  >
                    <li v-for="mod in (item.modifiers || [])" :key="mod.id">
                      {{ mod.name }}
                    </li>
                    <li v-if="item.notes" class="text-warning-darken-2">
                      {{ item.notes }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </VCardText>

          <VDivider />

          <!-- Acción Rápida: Completar Comanda (siempre visible al pie) -->
          <div v-if="activeViewTab === 'active'" class="pa-2 bg-light flex-shrink-0">
            <VBtn
              color="success"
              variant="flat"
              block
              size="small"
              class="font-weight-bold text-uppercase rounded"
              :loading="bumpingTicketId === ticket.id"
              :disabled="bumpingTicketId === ticket.id"
              @click="handleBumpTicket(ticket)"
            >
              <VIcon icon="tabler-check" start size="16" />
              Completar
            </VBtn>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Modal: Foto de Emplatado y Receta Técnica -->
    <VDialog v-model="showRecipeDialog" max-width="680">
      <VCard v-if="selectedDishItem" class="rounded-lg">
        <VCardTitle class="d-flex align-center justify-space-between bg-surface border-b py-3 px-4">
          <div class="d-flex align-center gap-2">
            <VAvatar color="primary" variant="tonal" size="34" class="rounded-lg">
              <VIcon icon="tabler-tools-kitchen-2" size="20" />
            </VAvatar>
            <span class="font-weight-black text-subtitle-1 text-uppercase">{{ selectedDishItem.dish_name }}</span>
            <VChip size="small" color="primary" variant="flat" class="font-weight-black">
              {{ selectedDishItem.quantity }}x
            </VChip>
          </div>
          <VBtn icon="tabler-x" variant="text" color="secondary" size="small" @click="showRecipeDialog = false" />
        </VCardTitle>

        <VCardText class="pa-4">
          <VRow>
            <VCol cols="12" sm="5">
              <VImg
                v-if="selectedDishItem.photo_url"
                :src="selectedDishItem.photo_url"
                height="210"
                cover
                class="rounded-lg border"
              />
              <div
                v-else
                class="d-flex flex-column align-center justify-center rounded-lg border bg-light text-medium-emphasis"
                style="height: 210px;"
              >
                <VIcon icon="tabler-photo-off" size="42" class="mb-2 opacity-50" />
                <span class="text-caption">Sin foto de emplatado</span>
              </div>
            </VCol>

            <VCol cols="12" sm="7">
              <div class="text-subtitle-2 font-weight-bold mb-1">Instrucciones / Descripción</div>
              <p class="text-body-2 text-medium-emphasis mb-3">
                {{ selectedDishItem.description || 'Sin instrucciones adicionales registradas en la ficha del plato.' }}
              </p>

              <div class="text-subtitle-2 font-weight-bold mb-2">
                Ingredientes de Receta (para {{ selectedDishItem.quantity }}x)
              </div>
              <VTable v-if="selectedDishItem.recipe_ingredients?.length" density="compact" class="border rounded">
                <thead>
                  <tr>
                    <th>Ingrediente</th>
                    <th class="text-right" style="white-space: nowrap;">Porción</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="ing in selectedDishItem.recipe_ingredients" :key="ing.id">
                    <td class="font-weight-medium">{{ ing.name }}</td>
                    <td class="text-right font-weight-black text-primary" style="white-space: nowrap;">
                      {{ parseFloat((ing.portion * selectedDishItem.quantity).toFixed(4)) }} {{ ing.unit }}
                    </td>
                  </tr>
                </tbody>
              </VTable>
              <VAlert v-else density="compact" variant="tonal" color="info" class="text-caption">
                Este plato no tiene ingredientes registrados en su escandallo.
              </VAlert>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Widget del Simulador de Tráfico en Vivo -->
    <RestaurantSimulatorWidget
      v-if="brandingStore.enable_live_simulator"
      @updated="fetchTickets(true)"
    />
  </div>
</template>

<style scoped>
.kds-view {
  min-height: 100vh;
}

.ticket-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.ticket-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08) !important;
}

.font-mono {
  font-family: monospace, monospace;
}

.truncate-line {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dish-item-card:hover {
  filter: brightness(0.97);
}

.dish-item-queued {
  background-color: #ffffff !important;
  border-inline-start: 4px solid rgba(var(--v-theme-on-surface), 0.2) !important;
}

.dish-item-preparing {
  background-color: rgba(var(--v-theme-warning), 0.18) !important;
  border-color: rgba(var(--v-theme-warning), 0.55) !important;
  border-inline-start: 4px solid rgb(var(--v-theme-warning)) !important;
}

.dish-item-ready {
  background-color: rgba(var(--v-theme-success), 0.18) !important;
  border-color: rgba(var(--v-theme-success), 0.55) !important;
  border-inline-start: 4px solid rgb(var(--v-theme-success)) !important;
}

.dish-details-list {
  list-style-type: none;
}

.dish-note-box {
  background-color: rgba(var(--v-theme-warning), 0.12);
  border: 1px solid rgba(var(--v-theme-warning), 0.35);
  color: rgb(var(--v-theme-on-surface));
}
</style>
