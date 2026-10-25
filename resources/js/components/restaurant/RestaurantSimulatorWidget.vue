<template>
  <div class="restaurant-simulator-widget">
    <!-- Botón Flotante Discreto -->
    <v-btn
      color="primary"
      size="large"
      elevation="4"
      rounded="pill"
      class="simulator-trigger-btn"
      @click="drawer = !drawer"
    >
      <v-badge
        v-if="isRunning"
        dot
        color="success"
        location="top start"
      >
        <v-icon start size="20">tabler-activity</v-icon>
      </v-badge>
      <v-icon v-else start size="20">tabler-player-play</v-icon>
      <span class="d-none d-sm-inline">Simulador En Vivo</span>
      <v-chip
        v-if="isRunning"
        size="x-small"
        color="success"
        class="ml-2"
      >
        Activo ({{ timerSeconds }}s)
      </v-chip>
    </v-btn>

    <!-- Drawer Lateral con Controles de Tráfico -->
    <v-navigation-drawer
      v-model="drawer"
      location="right"
      temporary
      width="380"
      class="simulator-drawer pa-4"
    >
      <div class="d-flex align-center justify-space-between mb-4">
        <div class="d-flex align-center gap-2">
          <v-avatar color="primary" variant="tonal" rounded size="36">
            <v-icon>tabler-cpu</v-icon>
          </v-avatar>
          <div>
            <h3 class="text-subtitle-1 font-weight-bold mb-0">Suite de Simulación</h3>
            <span class="text-caption text-medium-emphasis">Tráfico en Tiempo Real</span>
          </div>
        </div>
        <v-btn icon="tabler-x" variant="text" size="small" @click="drawer = false" />
      </div>

      <!-- Métricas en Vivo -->
      <v-card border flat class="mb-4 pa-3 bg-surface-variant">
        <div class="d-flex justify-space-between align-center text-caption mb-1">
          <span>Mesas Ocupadas:</span>
          <span class="font-weight-bold">{{ statusData.occupied_tables || 0 }} / {{ statusData.total_tables || 0 }}</span>
        </div>
        <div class="d-flex justify-space-between align-center text-caption mb-1">
          <span>Pre-cuentas Solicitadas:</span>
          <span class="font-weight-bold text-warning">{{ statusData.bill_requested_tables || 0 }}</span>
        </div>
        <div class="d-flex justify-space-between align-center text-caption">
          <span>En Cocina (KDS):</span>
          <span class="font-weight-bold text-primary">
            {{ statusData.kitchen?.queued || 0 }} cola | {{ statusData.kitchen?.preparing || 0 }} fuego | {{ statusData.kitchen?.ready || 0 }} listo
          </span>
        </div>
      </v-card>

      <!-- Botón de Tráfico Automático Continuo -->
      <v-btn
        :color="isRunning ? 'error' : 'success'"
        block
        size="large"
        class="mb-3 font-weight-bold"
        :prepend-icon="isRunning ? 'tabler-player-pause' : 'tabler-player-play'"
        @click="toggleAutoSimulation"
      >
        {{ isRunning ? 'Detener Generación Continua' : 'Iniciar Tráfico Automático (1 min)' }}
      </v-btn>

      <div class="text-caption text-medium-emphasis mb-3 text-center">
        {{ isRunning ? '⚡ Generando eventos en segundo plano cada 45 segundos...' : 'Puedes disparar eventos individuales con 1 clic:' }}
      </div>

      <!-- Disparadores Manuales Inmediatos -->
      <div class="d-flex flex-column gap-2 mb-4">
        <v-btn
          color="primary"
          variant="tonal"
          block
          size="small"
          prepend-icon="tabler-receipt"
          :loading="loadingAction === 'order'"
          @click="triggerOrder"
        >
          Generar Comanda Inmediata
        </v-btn>

        <v-btn
          color="warning"
          variant="tonal"
          block
          size="small"
          prepend-icon="tabler-flame"
          :loading="loadingAction === 'kitchen'"
          @click="advanceKitchen"
        >
          Avanzar Estado en Cocina (KDS)
        </v-btn>

        <v-btn
          color="info"
          variant="tonal"
          block
          size="small"
          prepend-icon="tabler-bell-ringing"
          :loading="loadingAction === 'guest'"
          @click="triggerGuest"
        >
          Simular Acción Comensal (Cuenta / QR)
        </v-btn>

        <v-btn
          color="secondary"
          variant="tonal"
          block
          size="small"
          prepend-icon="tabler-repeat"
          :loading="loadingAction === 'cycle'"
          @click="runFullCycle"
        >
          Ejecutar Ciclo Completo (Ráfaga)
        </v-btn>
      </div>

      <v-divider class="my-3" />

      <!-- Feed en Vivo de Eventos -->
      <div class="text-caption font-weight-bold mb-2 d-flex justify-space-between align-center">
        <span>Historial de Eventos en Vivo:</span>
        <v-btn
          variant="plain"
          size="x-small"
          color="error"
          @click="eventLogs = []"
        >
          Limpiar
        </v-btn>
      </div>

      <div class="event-feed-container border rounded pa-2 mb-4">
        <div v-for="(log, idx) in eventLogs" :key="idx" class="event-item py-1 border-b text-caption">
          <span class="text-medium-emphasis mr-1">[{{ log.time }}]</span>
          <span :class="log.color">{{ log.text }}</span>
        </div>
        <div v-if="!eventLogs.length" class="text-center text-medium-emphasis py-4 text-caption">
          Sin eventos recientes. Inicia el tráfico para ver actividad en vivo.
        </div>
      </div>

      <!-- Restablecer Salón -->
      <v-btn
        color="error"
        variant="text"
        block
        size="small"
        prepend-icon="tabler-trash"
        :loading="loadingAction === 'reset'"
        @click="resetTables"
      >
        Liberar y Limpiar Salón de Pruebas
      </v-btn>
    </v-navigation-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

const drawer = ref(false)
const isRunning = ref(false)
const timerSeconds = ref(45)
const loadingAction = ref(null)
const eventLogs = ref([])

const statusData = reactive({
  total_tables: 0,
  occupied_tables: 0,
  bill_requested_tables: 0,
  kitchen: { queued: 0, preparing: 0, ready: 0 },
})

let intervalId = null
let countdownId = null

const fetchStatus = async () => {
  try {
    const res = await axios.get('/restaurant/simulator/status')
    Object.assign(statusData, res.data)
  } catch (e) {
    // Silencioso
  }
}

const addLog = (text, type = 'info') => {
  const time = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  let color = 'text-primary'
  if (type === 'success') color = 'text-success'
  if (type === 'warning') color = 'text-warning'
  if (type === 'error') color = 'text-error'

  eventLogs.value.unshift({ time, text, color })
  if (eventLogs.value.length > 20) eventLogs.value.pop()
}

const emit = defineEmits(['updated'])

const triggerOrder = async () => {
  loadingAction.value = 'order'
  try {
    const res = await axios.post('/restaurant/simulator/trigger-order')
    if (res.data?.success) {
      addLog(res.data.message, 'success')
      toast.success(res.data.message)
      fetchStatus()
      emit('updated')
    }
  } catch (err) {
    toast.error('Error al simular orden.')
  } finally {
    loadingAction.value = null
  }
}

const advanceKitchen = async () => {
  loadingAction.value = 'kitchen'
  try {
    const res = await axios.post('/restaurant/simulator/advance-kitchen')
    if (res.data?.success) {
      addLog(res.data.message, 'warning')
      toast.info(res.data.message)
      fetchStatus()
      emit('updated')
    } else {
      toast.info(res.data?.message || 'Sin ítems en cocina.')
    }
  } catch (err) {
    toast.error('Error al avanzar cocina.')
  } finally {
    loadingAction.value = null
  }
}

const triggerGuest = async () => {
  loadingAction.value = 'guest'
  try {
    const res = await axios.post('/restaurant/simulator/trigger-guest')
    if (res.data?.success) {
      addLog(res.data.message, 'warning')
      toast.warning(res.data.message)
      fetchStatus()
      emit('updated')
    } else {
      toast.info(res.data?.message || 'Sin mesas ocupadas.')
    }
  } catch (err) {
    toast.error('Error al simular acción de comensal.')
  } finally {
    loadingAction.value = null
  }
}

const runFullCycle = async () => {
  loadingAction.value = 'cycle'
  try {
    const res = await axios.post('/restaurant/simulator/trigger-cycle')
    if (res.data?.success) {
      const summary = res.data.cycle_summary
      if (summary.order?.success) addLog(summary.order.message, 'success')
      if (summary.kitchen?.success) addLog(summary.kitchen.message, 'warning')
      if (summary.guest?.success) addLog(summary.guest.message, 'info')
      toast.success('Ciclo de tráfico ejecutado.')
      fetchStatus()
      emit('updated')
    }
  } catch (err) {
    toast.error('Error en ciclo completo.')
  } finally {
    loadingAction.value = null
  }
}

const resetTables = async () => {
  loadingAction.value = 'reset'
  try {
    const res = await axios.post('/restaurant/simulator/reset')
    addLog(res.data.message, 'error')
    toast.info(res.data.message)
    fetchStatus()
    emit('updated')
  } catch (err) {
    toast.error('Error al resetear salón.')
  } finally {
    loadingAction.value = null
  }
}

const toggleAutoSimulation = () => {
  if (isRunning.value) {
    stopAuto()
  } else {
    startAuto()
  }
}

const startAuto = () => {
  isRunning.value = true
  timerSeconds.value = 45
  toast.success('Simulación automática activada (ciclos cada 45s).')

  // Ejecutar un primer ciclo inmediato
  runFullCycle()

  countdownId = setInterval(() => {
    if (timerSeconds.value > 1) {
      timerSeconds.value--
    } else {
      timerSeconds.value = 45
    }
  }, 1000)

  intervalId = setInterval(() => {
    runFullCycle()
  }, 45000)
}

const stopAuto = () => {
  isRunning.value = false
  if (intervalId) clearInterval(intervalId)
  if (countdownId) clearInterval(countdownId)
  toast.info('Simulación automática detenida.')
}

onMounted(() => {
  fetchStatus()
})

onUnmounted(() => {
  stopAuto()
})
</script>

<style scoped>
.simulator-trigger-btn {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 999;
}

.simulator-drawer {
  z-index: 1000;
}

.event-feed-container {
  max-height: 180px;
  overflow-y: auto;
  background-color: rgba(var(--v-theme-surface-variant), 0.3);
}

.event-item:last-child {
  border-bottom: none !important;
}
</style>
