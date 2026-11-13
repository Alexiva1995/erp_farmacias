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
const previousTicketIds = ref(new Set());

// Modal de Foto y Receta del Plato
const showRecipeDialog = ref(false);
const selectedDishItem = ref(null);

const isMultiStation = computed(() => {
  return brandingStore.settings?.kds_multi_station !== false;
});

const showCourseHeaders = computed(() => {
  const courses = brandingStore.settings?.kds_courses;
  if (Array.isArray(courses)) {
    return courses.length > 1;
  }
  return true;
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
  } catch (error) {
    console.error("Error al cargar comandas KDS:", error);
  } finally {
    if (!silent) loading.value = false;
  }
};

// Tickets filtrados según la pestaña activa (En Cocina vs En Pase)
const displayedTickets = computed(() => {
  if (activeViewTab.value === "ready") {
    return tickets.value
      .map((t) => ({
        ...t,
        items: (t.items || []).filter((i) => i.kitchen_status === "ready"),
      }))
      .filter((t) => t.items.length > 0);
  }
  return tickets.value
    .map((t) => ({
      ...t,
      items: (t.items || []).filter(
        (i) => i.kitchen_status === "queued" || i.kitchen_status === "preparing"
      ),
    }))
    .filter((t) => t.items.length > 0);
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
    <VRow v-else>
      <VCol
        v-for="ticket in displayedTickets"
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
                <span class="font-weight-black text-subtitle-1 text-uppercase">
                  {{ ticket.dining_table_name || `Orden #${ticket.id}` }}
                </span>
                <VChip size="x-small" color="white" variant="flat" class="text-dark font-weight-black">
                  #{{ ticket.id }} • {{ ticket.items?.length }} ítems
                </VChip>
              </div>
              <div class="text-super-xs font-weight-bold opacity-90 truncate-line" style="max-width: 185px;">
                {{ ticket.seller_name }} • {{ ticket.client_name }}
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
                    'opacity-50 pointer-events-none': updatingItemId === item.id,
                  }"
                  @click="handleNextStatus(item)"
                >
                  <div class="d-flex align-start justify-space-between gap-2 mb-1">
                    <div class="d-flex align-baseline gap-2">
                      <span class="font-weight-black text-subtitle-1 text-primary">
                        {{ item.quantity }}x
                      </span>
                      <span class="font-weight-bold text-body-2 text-high-emphasis text-uppercase">
                        {{ item.dish_name }}
                      </span>
                    </div>

                    <!-- Estado Actual Táctil -->
                    <VChip
                      :color="item.kitchen_status_color"
                      size="x-small"
                      label
                      class="font-weight-black text-uppercase flex-shrink-0"
                    >
                      <VIcon
                        :icon="item.kitchen_status === 'preparing' ? 'tabler-flame' : 'tabler-clock'"
                        start
                        size="12"
                      />
                      {{ item.kitchen_status_label }}
                    </VChip>
                  </div>

                  <!-- Estación KDS, Comensal, Botón Foto/Receta e indicación táctil -->
                  <div class="d-flex align-center justify-space-between gap-2 mt-1">
                    <div class="d-flex align-center gap-1 flex-wrap">
                      <VChip
                        v-if="item.kitchen_station && isMultiStation"
                        size="x-small"
                        :color="item.kitchen_station.color || 'secondary'"
                        variant="tonal"
                      >
                        <VIcon :icon="item.kitchen_station.icon || 'tabler-flame'" start size="12" />
                        {{ item.kitchen_station.name }}
                      </VChip>
                      <VChip
                        v-if="item.seat_number"
                        size="x-small"
                        color="primary"
                        variant="tonal"
                      >
                        Silla {{ item.seat_number }}
                      </VChip>
                      <VBtn
                        size="x-small"
                        variant="tonal"
                        color="info"
                        prepend-icon="tabler-book-2"
                        @click.stop="openRecipeModal(item)"
                      >
                        Receta / Foto
                      </VBtn>
                    </div>
                    <span v-if="activeViewTab === 'active'" class="text-super-xs text-medium-emphasis ms-auto">
                      {{ item.kitchen_status === 'queued' ? 'Tocar: Iniciar' : 'Tocar: Listo' }}
                    </span>
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

                  <!-- Preferencias de Cocina y Exclusiones (Sin Cebolla, etc.) -->
                  <div v-if="item.notes" class="mt-2 pa-2 dish-note-box rounded text-xs font-weight-bold d-flex align-start gap-1">
                    <VIcon icon="tabler-alert-circle" size="15" class="flex-shrink-0 mt-0.5" color="warning" />
                    <span>{{ item.notes }}</span>
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
          <div v-if="activeViewTab === 'active'" class="pa-2 bg-light">
            <VBtn
              color="success"
              variant="flat"
              block
              size="default"
              class="font-weight-black text-uppercase rounded"
              :loading="bumpingTicketId === ticket.id"
              :disabled="bumpingTicketId === ticket.id"
              @click="handleBumpTicket(ticket)"
            >
              <VIcon icon="tabler-check" start size="18" />
              Despachar Comanda
            </VBtn>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Modal: Foto de Emplatado y Receta Técnica -->
    <VDialog v-model="showRecipeDialog" max-width="640">
      <VCard v-if="selectedDishItem">
        <VCardTitle class="d-flex align-center justify-space-between bg-surface-variant py-3 px-4">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-tools-kitchen-2" color="primary" />
            <span class="font-weight-bold text-uppercase">{{ selectedDishItem.dish_name }}</span>
            <VChip size="x-small" color="primary" variant="flat">
              {{ selectedDishItem.quantity }}x
            </VChip>
          </div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="showRecipeDialog = false" />
        </VCardTitle>

        <VCardText class="pa-4">
          <VRow>
            <VCol cols="12" sm="5">
              <VImg
                v-if="selectedDishItem.photo_url"
                :src="selectedDishItem.photo_url"
                height="200"
                cover
                class="rounded-lg border"
              />
              <div
                v-else
                class="d-flex flex-column align-center justify-center rounded-lg border bg-light text-medium-emphasis"
                style="height: 200px;"
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
                    <th class="text-right">Cant. Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="ing in selectedDishItem.recipe_ingredients" :key="ing.id">
                    <td>{{ ing.name }}</td>
                    <td class="text-right font-weight-bold">
                      {{ (ing.portion * selectedDishItem.quantity).toFixed(2) }} {{ ing.unit }}
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
  background-color: rgba(var(--v-theme-surface), 1);
  border-inline-start: 4px solid rgb(var(--v-theme-info)) !important;
}

.dish-item-preparing {
  background-color: rgba(var(--v-theme-warning), 0.1) !important;
  border-color: rgba(var(--v-theme-warning), 0.45) !important;
  border-inline-start: 4px solid rgb(var(--v-theme-warning)) !important;
}

.dish-note-box {
  background-color: rgba(var(--v-theme-warning), 0.12);
  border: 1px solid rgba(var(--v-theme-warning), 0.35);
  color: rgb(var(--v-theme-on-surface));
}
</style>
