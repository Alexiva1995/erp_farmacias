<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import RestaurantSimulatorWidget from "@/components/restaurant/RestaurantSimulatorWidget.vue";

// Estado de KDS
const loading = ref(false);
const tickets = ref([]);
const stations = ref([]);
const selectedStationId = ref(null);
const fullScreen = ref(false);

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

// Cargar comandas activas
const fetchTickets = async (silent = false) => {
  if (!silent) loading.value = true;
  try {
    const params = {};
    if (selectedStationId.value) {
      params.station_id = selectedStationId.value;
    }
    const { data } = await axios.get("/kds/tickets", { params });
    tickets.value = data.data || [];
  } catch (error) {
    console.error("Error al cargar comandas KDS:", error);
  } finally {
    if (!silent) loading.value = false;
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
  if (!next) return;

  try {
    await axios.patch(`/kds/items/${item.id}/status`, { status: next });
    toast.success(`Plato actualizado a ${next === "preparing" ? "Preparando" : "Listo"}`);
    fetchTickets(true);
  } catch (error) {
    console.error("Error al actualizar plato:", error);
    toast.error("No se pudo actualizar el estado.");
  }
};

// Despachar comanda completa (Bump Ticket)
const handleBumpTicket = async (ticket) => {
  try {
    await axios.post(`/kds/orders/${ticket.id}/bump`, {
      station_id: selectedStationId.value,
    });
    toast.success(`Comanda #${ticket.id} despachada.`);
    fetchTickets(true);
  } catch (error) {
    console.error("Error al despachar comanda:", error);
    toast.error("No se pudo despachar la comanda.");
  }
};

// Cálculo de tiempo transcurrido (mm:ss) y estado semáforo
const getElapsedTime = (commandedAt) => {
  if (!commandedAt) return "00:00";
  const start = new Date(commandedAt).getTime();
  const diffSec = Math.max(0, Math.floor((currentTime.value - start) / 1000));
  const min = Math.floor(diffSec / 60);
  const sec = diffSec % 60;
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

// Pantalla completa táctil
const toggleFullScreen = () => {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(() => {});
    fullScreen.value = true;
  } else {
    if (document.exitFullscreen) {
      document.exitFullscreen().catch(() => {});
      fullScreen.value = false;
    }
  }
};

onMounted(() => {
  fetchStations();
  fetchTickets();

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
  if (timerInterval) clearInterval(timerInterval);
  if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
  <div class="kds-view pb-12">
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
        <div class="d-flex align-center gap-3">
          <VChip color="info" variant="tonal" size="default" class="font-weight-bold">
            <VIcon icon="tabler-hourglass-empty" start size="16" />
            En Cola: {{ inQueueCount }}
          </VChip>

          <VChip color="warning" variant="tonal" size="default" class="font-weight-bold">
            <VIcon icon="tabler-flame" start size="16" />
            En Fuego: {{ inPreparingCount }}
          </VChip>

          <VChip color="primary" variant="tonal" size="default" class="font-weight-black">
            <VIcon icon="tabler-receipt" start size="16" />
            Comandas: {{ tickets.length }}
          </VChip>

          <VBtn icon variant="tonal" color="secondary" size="small" @click="fetchTickets(false)">
            <VIcon icon="tabler-refresh" />
          </VBtn>

          <VBtn icon variant="tonal" color="secondary" size="small" @click="toggleFullScreen">
            <VIcon :icon="fullScreen ? 'tabler-minimize' : 'tabler-maximize'" />
          </VBtn>
        </div>
      </div>

      <!-- Filtro Táctil por Estación de Cocina -->
      <VDivider />
      <div class="pa-2 px-4 bg-light d-flex flex-wrap gap-2 align-center">
        <span class="text-xs font-weight-black uppercase text-medium-emphasis me-2">Estación:</span>
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
      </div>
    </VCard>

    <!-- Estado Vacío (Sin Comandas Pendientes) -->
    <div v-if="tickets.length === 0 && !loading" class="pa-16 text-center text-disabled">
      <VIcon icon="tabler-clipboard-check" size="72" class="mb-4 opacity-30 text-success" />
      <h3 class="text-h6 font-weight-black uppercase text-high-emphasis opacity-60">
        ¡Cocina al día! Sin comandas pendientes
      </h3>
      <p class="text-sm">Las órdenes comandadas desde el TPV aparecerán automáticamente aquí.</p>
    </div>

    <!-- Grid Táctil de Comandas (Tickets) -->
    <VRow v-else>
      <VCol
        v-for="ticket in tickets"
        :key="ticket.id"
        cols="12"
        sm="6"
        md="4"
        lg="3"
        class="d-flex"
      >
        <VCard
          variant="flat"
          class="w-100 border rounded-lg overflow-hidden d-flex flex-column ticket-card shadow-sm"
        >
          <!-- Encabezado de la Comanda con Semáforo de Tiempo -->
          <div
            class="pa-3 text-white d-flex align-center justify-space-between"
            :class="`bg-${getUrgencyColor(ticket.items?.[0]?.commanded_at, ticket.items?.[0]?.preparation_time_minutes)}`"
          >
            <div>
              <div class="d-flex align-center gap-2">
                <span class="font-weight-black text-subtitle-1">#{{ ticket.id }}</span>
                <VChip size="x-small" color="white" variant="flat" class="text-dark font-weight-black">
                  {{ ticket.items?.length }} ítems
                </VChip>
              </div>
              <div class="text-super-xs font-weight-bold opacity-90 truncate-line" style="max-width: 140px;">
                {{ ticket.client_name }}
              </div>
            </div>

            <!-- Cronómetro en Vivo -->
            <div class="text-right">
              <div class="font-weight-black text-h6 font-mono leading-none">
                {{ getElapsedTime(ticket.items?.[0]?.commanded_at) }}
              </div>
              <div class="text-super-xs opacity-75">
                Estimado: {{ ticket.items?.[0]?.preparation_time_minutes || 15 }}m
              </div>
            </div>
          </div>

          <!-- Cuerpo: Lista de Platos por Tiempos -->
          <VCardText class="pa-3 flex-grow-1 bg-surface d-flex flex-column gap-3 overflow-y-auto" style="max-height: 480px;">
            <div
              v-for="(courseItems, courseNum) in groupItemsByCourse(ticket.items)"
              :key="courseNum"
              class="course-section"
            >
              <!-- Separador de Tiempo (Entrada / Fuerte / Postre) -->
              <div class="d-flex align-center gap-2 mb-2">
                <div class="course-badge text-super-xs font-weight-black uppercase px-2 py-1 rounded bg-light border">
                  {{ courseItems[0]?.course_label || `${courseNum}° Tiempo` }}
                </div>
                <VDivider />
              </div>

              <!-- Fila Táctil de Cada Plato -->
              <div class="d-flex flex-column gap-2">
                <div
                  v-for="item in courseItems"
                  :key="item.id"
                  class="dish-item-card pa-2 rounded border cursor-pointer transition-all"
                  :class="{
                    'bg-warning-light border-warning': item.kitchen_status === 'preparing',
                    'bg-light border': item.kitchen_status === 'queued',
                  }"
                  @click="handleNextStatus(item)"
                >
                  <div class="d-flex align-center justify-space-between mb-1">
                    <div class="d-flex align-center gap-2">
                      <span class="font-weight-black text-body-1 text-primary">
                        {{ item.quantity }}x
                      </span>
                      <span class="font-weight-bold text-sm text-high-emphasis text-uppercase">
                        {{ item.dish_name }}
                      </span>
                    </div>

                    <!-- Estado Actual Táctil -->
                    <VChip
                      :color="item.kitchen_status_color"
                      size="x-small"
                      label
                      class="font-weight-black text-uppercase"
                    >
                      <VIcon
                        :icon="item.kitchen_status === 'preparing' ? 'tabler-flame' : 'tabler-clock'"
                        start
                        size="12"
                      />
                      {{ item.kitchen_status_label }}
                    </VChip>
                  </div>

                  <!-- Estación KDS asociada -->
                  <div v-if="item.kitchen_station" class="mb-1">
                    <VChip size="x-small" :color="item.kitchen_station.color || 'secondary'" variant="tonal">
                      <VIcon :icon="item.kitchen_station.icon || 'tabler-flame'" start size="12" />
                      {{ item.kitchen_station.name }}
                    </VChip>
                  </div>

                  <!-- Preferencias de Cocina y Exclusiones (Sin Cebolla, etc.) -->
                  <div v-if="item.notes" class="mt-1 pa-1 bg-amber-lighten-5 border rounded text-xs text-amber-darken-4 font-weight-bold">
                    <VIcon icon="tabler-note" start size="14" />
                    {{ item.notes }}
                  </div>

                  <!-- Modificadores Seleccionados -->
                  <div v-if="item.modifiers && item.modifiers.length > 0" class="mt-1 d-flex flex-wrap gap-1">
                    <VChip
                      v-for="mod in item.modifiers"
                      :key="mod.id"
                      size="x-small"
                      color="secondary"
                      variant="outlined"
                    >
                      {{ mod.name }}
                    </VChip>
                  </div>
                </div>
              </div>
            </div>
          </VCardText>

          <VDivider />

          <!-- Acción Rápida: Despachar Comanda Completa -->
          <div class="pa-2 bg-light">
            <VBtn
              color="success"
              variant="flat"
              block
              size="default"
              class="font-weight-black text-uppercase rounded"
              @click="handleBumpTicket(ticket)"
            >
              <VIcon icon="tabler-check" start size="18" />
              Despachar Comanda
            </VBtn>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Widget del Simulador de Tráfico en Vivo -->
    <RestaurantSimulatorWidget @updated="fetchTickets(true)" />
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

.bg-warning-light {
  background-color: #fffbeb !important;
}
</style>
