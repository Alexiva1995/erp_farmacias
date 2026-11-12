<template>
  <div class="overcooked-live-container position-relative overflow-hidden rounded-lg">
    <!-- Barra Flotante de Herramientas y Estado HUD -->
    <div class="overcooked-hud-bar d-flex align-center justify-space-between flex-wrap gap-2 pa-3">
      <div class="d-flex align-center gap-2 flex-wrap">
        <v-chip color="primary" variant="flat" size="small" prepend-icon="tabler-chef-hat">
          <strong>Salón en Vivo</strong>
        </v-chip>
        <v-chip color="secondary" variant="tonal" size="small" prepend-icon="tabler-ruler-2">
          {{ zoneWidthMeters }}m × {{ zoneHeightMeters }}m ({{ (zoneWidthMeters * zoneHeightMeters).toFixed(1) }} m²)
        </v-chip>
        <v-chip color="success" variant="tonal" size="small" prepend-icon="tabler-table">
          {{ props.tables.length }} Mesas
        </v-chip>
        <v-chip v-if="activeChefsCount" color="warning" variant="tonal" size="small" prepend-icon="tabler-flame">
          {{ activeChefsCount }} Chefs
        </v-chip>
        <v-chip v-if="activeWaitersCount" color="info" variant="tonal" size="small" prepend-icon="tabler-user-check">
          {{ activeWaitersCount }} Meseros
        </v-chip>
      </div>

      <div class="d-flex align-center gap-1">
        <v-btn
          icon="tabler-zoom-in"
          variant="tonal"
          size="x-small"
          color="white"
          title="Acercar (+)"
          @click="zoomIn"
        />
        <v-btn
          icon="tabler-zoom-out"
          variant="tonal"
          size="x-small"
          color="white"
          title="Alejar (-)"
          @click="zoomOut"
        />
        <v-btn
          icon="tabler-refresh"
          variant="tonal"
          size="x-small"
          color="white"
          title="Centrar y Resetear Cámara"
          @click="resetCamera"
        />
      </div>
    </div>

    <!-- Canvas Principal del Motor 2.5D -->
    <canvas
      ref="canvasRef"
      class="overcooked-canvas"
      @mousedown="handleMouseDown"
      @mousemove="handleMouseMove"
      @mouseup="handleMouseUp"
      @mouseleave="handleMouseUp"
      @click="handleClick"
    />

    <!-- Guía de Interacción Inferior -->
    <div class="overcooked-bottom-guide d-flex align-center justify-space-between pa-2 text-caption">
      <div class="d-flex align-center gap-3 flex-wrap">
        <span><v-icon size="14" color="success">tabler-circle-filled</v-icon> Disponible</span>
        <span><v-icon size="14" color="error">tabler-circle-filled</v-icon> Comensales Activos</span>
        <span><v-icon size="14" color="warning">tabler-circle-filled</v-icon> Pidiendo Cuenta</span>
        <span><v-icon size="14" color="info">tabler-circle-filled</v-icon> Reservada</span>
      </div>
      <div class="text-medium-emphasis d-none d-sm-block">
        Haz clic en cualquier mesa para abrir su comanda • Arrastra para mover la vista
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  tables: {
    type: Array,
    default: () => [],
  },
  stations: {
    type: Array,
    default: () => [],
  },
  selectedTableId: {
    type: [Number, String],
    default: null,
  },
  zone: {
    type: Object,
    default: () => null,
  },
})

const emit = defineEmits(['select-table', 'select-station'])

const canvasRef = ref(null)
let ctx = null
let animationFrameId = null

// Parámetros de Cámara y Zoom
const camera = ref({
  x: 0,
  y: 0,
  zoom: 1.0,
  isDragging: false,
  dragStartX: 0,
  dragStartY: 0,
})

// Escala métrica unificada con el diseñador (1 metro = 60px)
const cellPixelSize = 60
const offsetX = 50
const offsetY = 50

const zoneWidthMeters = computed(() => Number(props.zone?.width_meters || 12))
const zoneHeightMeters = computed(() => Number(props.zone?.height_meters || 8))
const zonePixelWidth = computed(() => Math.round(zoneWidthMeters.value * cellPixelSize))
const zonePixelHeight = computed(() => Math.round(zoneHeightMeters.value * cellPixelSize))

const worldWidth = computed(() => zonePixelWidth.value + offsetX * 2)
const worldHeight = computed(() => zonePixelHeight.value + offsetY * 2)

// Agentes de Simulación (Chefs, Meseros, Comensales, Partículas)
let chefs = []
let waiters = []
let particles = []
let floatingBubbles = []
let globalTick = 0

// Conteos computados para el HUD
const activeChefsCount = computed(() => chefs.length)
const activeWaitersCount = computed(() => waiters.length)

// Paleta de Estilos y Fondos del Restaurante
const THEME = {
  wooden: {
    bg: '#3b200b',
    line: 'rgba(255, 255, 255, 0.18)',
    border: '#d97706',
  },
  grid: {
    bg: '#10141f',
    line: 'rgba(0, 207, 232, 0.25)',
    border: '#00cfe8',
  },
  grass: {
    bg: '#122b19',
    line: 'rgba(255, 255, 255, 0.16)',
    border: '#2ecc71',
  },
  concrete: {
    bg: '#222630',
    line: 'rgba(255, 255, 255, 0.16)',
    border: '#94a3b8',
  },
}

// ==========================================
// INICIALIZACIÓN DE AGENTES BASADA EN DATOS REALES
// ==========================================

// Chefs: Solo se crean si hay estaciones reales en el equipamiento o área de cocina
const initChefs = () => {
  chefs = []
  const equipStations = (props.zone?.layout_config?.equipment || []).filter(e => e.type === 'station')
  const kitchenArea = (props.zone?.layout_config?.areas || []).find(a => a.type === 'kitchen')

  if (equipStations.length > 0) {
    equipStations.forEach((eq, idx) => {
      const sx = offsetX + Number(eq.x || 0) * cellPixelSize + (Number(eq.w || 1.5) * cellPixelSize) / 2
      const sy = offsetY + Number(eq.y || 0) * cellPixelSize + (Number(eq.h || 1.0) * cellPixelSize) / 2
      chefs.push({
        id: eq.id || idx + 1,
        name: `Chef ${eq.name || 'Cocina'}`,
        stationName: eq.name || 'Estación',
        x: sx,
        y: sy + 14,
        targetX: sx,
        targetY: sy + 14,
        homeX: sx,
        homeY: sy + 14,
        color: '#ff9f43',
        state: 'cooking',
        bobOffset: idx * 1.2,
        carryingDish: null,
        cookingProgress: (idx * 0.3) % 1.0,
        station: eq,
      })
    })
  } else if (kitchenArea && props.stations.length > 0) {
    // Si hay área de cocina y estaciones KDS registradas en el sistema
    const kw = Number(kitchenArea.w || 3) * cellPixelSize
    const kh = Number(kitchenArea.h || 2) * cellPixelSize
    const kx = offsetX + Number(kitchenArea.x || 0) * cellPixelSize
    const ky = offsetY + Number(kitchenArea.y || 0) * cellPixelSize

    props.stations.forEach((station, idx) => {
      const sx = kx + kw * ((idx + 0.5) / props.stations.length)
      const sy = ky + kh / 2 + 10
      chefs.push({
        id: station.id,
        name: `Chef ${station.code || station.name}`,
        stationName: station.name,
        x: sx,
        y: sy,
        targetX: sx,
        targetY: sy,
        homeX: sx,
        homeY: sy,
        color: station.color || '#ff9f43',
        state: 'cooking',
        bobOffset: idx * 1.5,
        carryingDish: null,
        cookingProgress: (idx * 0.25) % 1.0,
        station,
      })
    })
  }
}

// Meseros: Solo se crean si hay mesas reales en el diseño
const initWaiters = () => {
  waiters = []
  if (!props.tables || !props.tables.length) return

  const tableCount = props.tables.length
  const waiterCount = Math.min(3, Math.max(1, Math.ceil(tableCount / 4)))

  for (let i = 0; i < waiterCount; i++) {
    const t = props.tables[i % tableCount]
    const wx = offsetX + Number(t.pos_x || 0) + Number(t.width || 60) + 16
    const wy = offsetY + Number(t.pos_y || 0) + 16
    waiters.push({
      id: i + 1,
      name: i === 0 ? 'Mesero Alex' : i === 1 ? 'Mesera Sofía' : 'Mesero Carlos',
      x: wx,
      y: wy,
      targetX: wx,
      targetY: wy,
      speed: 2.2,
      state: 'patrolling',
      carryingDish: null,
      bobOffset: i * 1.5,
      targetTable: null,
      taskCooldown: 80 + i * 50,
    })
  }
}

// Partículas de Fuego y Vapor
const spawnParticle = (x, y, type = 'steam') => {
  if (particles.length > 40) particles.shift()
  particles.push({
    x: x + (Math.random() - 0.5) * 16,
    y: y,
    vx: (Math.random() - 0.5) * 0.6,
    vy: -0.7 - Math.random() * 0.7,
    radius: 3 + Math.random() * 2.5,
    alpha: 0.8,
    type,
  })
}

// Actualización de Lógica de Agentes (Máquina de Estados)
const updateAgents = () => {
  globalTick++

  // 1. Actualizar Chefs
  chefs.forEach((chef) => {
    chef.bobOffset += 0.08
    chef.cookingProgress += 0.003
    if (chef.cookingProgress > 1.0) {
      chef.cookingProgress = 0
      floatingBubbles.push({
        x: chef.x,
        y: chef.y - 45,
        text: '¡Plato Listo!',
        alpha: 1.0,
        vy: -0.6,
      })
    }

    if (globalTick % 8 === 0) {
      spawnParticle(chef.homeX, chef.homeY - 20, 'flame')
      spawnParticle(chef.homeX + 8, chef.homeY - 28, 'steam')
    }
  })

  // 2. Actualizar Meseros (Patrullan exclusivamente entre mesas reales)
  const occupiedTables = props.tables.filter(t => t.status === 'occupied' || t.status === 'bill_requested')
  const allTables = props.tables

  waiters.forEach((waiter) => {
    waiter.bobOffset += 0.12

    const dx = waiter.targetX - waiter.x
    const dy = waiter.targetY - waiter.y
    const dist = Math.hypot(dx, dy)

    if (dist > 4) {
      waiter.x += (dx / dist) * waiter.speed
      waiter.y += (dy / dist) * waiter.speed
    } else {
      waiter.taskCooldown--
      if (waiter.taskCooldown <= 0) {
        waiter.taskCooldown = 140 + Math.floor(Math.random() * 100)

        // Prioridad: atender comensales activos; si no, pasear a otra mesa del plano
        if (occupiedTables.length && Math.random() > 0.35) {
          const randomTable = occupiedTables[Math.floor(Math.random() * occupiedTables.length)]
          waiter.targetTable = randomTable
          waiter.targetX = offsetX + Number(randomTable.pos_x || 0) + Number(randomTable.width || 60) + 16
          waiter.targetY = offsetY + Number(randomTable.pos_y || 0) + 20
          waiter.carryingDish = 'Bandeja'
          waiter.state = 'serving'
        } else if (allTables.length) {
          const randomTable = allTables[Math.floor(Math.random() * allTables.length)]
          waiter.targetTable = randomTable
          waiter.targetX = offsetX + Number(randomTable.pos_x || 0) + Number(randomTable.width || 60) + 16
          waiter.targetY = offsetY + Number(randomTable.pos_y || 0) + 20
          waiter.carryingDish = null
          waiter.state = 'patrolling'
        }
      }
    }
  })

  // 3. Actualizar Partículas
  for (let i = particles.length - 1; i >= 0; i--) {
    const p = particles[i]
    p.x += p.vx
    p.y += p.vy
    p.alpha -= 0.015
    p.radius *= 0.98
    if (p.alpha <= 0) particles.splice(i, 1)
  }

  // 4. Actualizar Burbujas de Texto
  for (let i = floatingBubbles.length - 1; i >= 0; i--) {
    const b = floatingBubbles[i]
    b.y += b.vy
    b.alpha -= 0.012
    if (b.alpha <= 0) floatingBubbles.splice(i, 1)
  }
}

// ==========================================
// RENDERIZADO PRINCIPAL (CANVAS 2.5D REAL)
// ==========================================
const render = () => {
  if (!ctx || !canvasRef.value) return

  const width = canvasRef.value.width
  const height = canvasRef.value.height

  ctx.clearRect(0, 0, width, height)

  ctx.save()
  // Cámara y Zoom
  ctx.translate(camera.value.x, camera.value.y)
  ctx.scale(camera.value.zoom, camera.value.zoom)

  // 1. Suelo y Entorno Real de la Zona
  drawEnvironment()

  // 2. Áreas Arquitectónicas Reales (Cocina, Terraza, Depósito, Paredes, etc.)
  drawArchitecturalAreas()

  // 3. Equipamiento Real (Mostradores, Neveras, Estaciones KDS, Pase)
  drawEquipment()

  // 4. Mesas Reales con Sillas Dinámicas y Comensales
  drawDiningTables()

  // 5. Agentes Chefs
  chefs.forEach(drawChef)

  // 6. Agentes Meseros
  waiters.forEach(drawWaiter)

  // 7. Partículas y Burbujas
  drawParticles()
  drawFloatingBubbles()

  ctx.restore()

  updateAgents()
  animationFrameId = requestAnimationFrame(render)
}

// ==========================================
// 1. DIBUJO DEL ENTORNO (SUELO REAL DE LA ZONA)
// ==========================================
const drawEnvironment = () => {
  // Fondo oscuro exterior del lienzo
  ctx.fillStyle = '#0f111a'
  ctx.fillRect(-200, -200, worldWidth.value + 400, worldHeight.value + 400)

  const zWidth = zonePixelWidth.value
  const zHeight = zonePixelHeight.value
  const bgStyleKey = props.zone?.background_style || 'wooden'
  const currentTheme = THEME[bgStyleKey] || THEME.wooden

  // Sombra proyectada del suelo de la zona
  ctx.fillStyle = 'rgba(0, 0, 0, 0.65)'
  drawRoundedRect(ctx, offsetX + 6, offsetY + 8, zWidth, zHeight, 8, true, false)

  // Suelo de la zona activa según su estilo real
  ctx.fillStyle = currentTheme.bg
  drawRoundedRect(ctx, offsetX, offsetY, zWidth, zHeight, 8, true, false)

  // Cuadrícula métrica exacta de 1m (60px)
  ctx.strokeStyle = currentTheme.line
  ctx.lineWidth = 1
  for (let x = offsetX; x <= offsetX + zWidth; x += cellPixelSize) {
    ctx.beginPath()
    ctx.moveTo(x, offsetY)
    ctx.lineTo(x, offsetY + zHeight)
    ctx.stroke()
  }
  for (let y = offsetY; y <= offsetY + zHeight; y += cellPixelSize) {
    ctx.beginPath()
    ctx.moveTo(offsetX, y)
    ctx.lineTo(offsetX + zWidth, y)
    ctx.stroke()
  }

  // Borde perimetral del plano del restaurante
  ctx.strokeStyle = currentTheme.border
  ctx.lineWidth = 2.5
  drawRoundedRect(ctx, offsetX, offsetY, zWidth, zHeight, 8, false, true)

  // Marcadores de metro sutiles en la regla superior
  ctx.fillStyle = 'rgba(0, 0, 0, 0.75)'
  ctx.font = 'bold 9px sans-serif'
  ctx.textAlign = 'left'
  const maxMetersX = Math.ceil(zoneWidthMeters.value)
  for (let m = 1; m < maxMetersX; m++) {
    const rx = offsetX + m * cellPixelSize
    ctx.fillStyle = 'rgba(255, 213, 79, 0.45)'
    ctx.fillText(`${m}m`, rx + 3, offsetY + 12)
  }

  // Píldora con dimensiones en la esquina inferior derecha
  const tagText = `Planta: ${zoneWidthMeters.value}m × ${zoneHeightMeters.value}m (${(zoneWidthMeters.value * zoneHeightMeters.value).toFixed(1)} m²)`
  ctx.font = 'bold 10px sans-serif'
  const tagW = ctx.measureText(tagText).width + 16
  ctx.fillStyle = 'rgba(15, 23, 42, 0.85)'
  drawRoundedRect(ctx, offsetX + zWidth - tagW - 8, offsetY + zHeight - 24, tagW, 18, 4, true, false)
  ctx.strokeStyle = 'rgba(255, 213, 79, 0.5)'
  ctx.lineWidth = 1
  drawRoundedRect(ctx, offsetX + zWidth - tagW - 8, offsetY + zHeight - 24, tagW, 18, 4, false, true)
  ctx.fillStyle = '#ffd54f'
  ctx.textAlign = 'center'
  ctx.fillText(tagText, offsetX + zWidth - tagW / 2 - 8, offsetY + zHeight - 12)
}

// ==========================================
// 2. ÁREAS ARQUITECTÓNICAS (COCINA, TERRAZA, DEPÓSITO, ETC.)
// ==========================================
const drawArchitecturalAreas = () => {
  const areas = props.zone?.layout_config?.areas || []

  areas.forEach((area) => {
    const ax = offsetX + Number(area.x || 0) * cellPixelSize
    const ay = offsetY + Number(area.y || 0) * cellPixelSize
    const aw = Number(area.w || 1) * cellPixelSize
    const ah = Number(area.h || 1) * cellPixelSize

    ctx.save()

    if (area.type === 'kitchen') {
      // Cocina Real: Azulejos crema/gris con borde rojo
      ctx.fillStyle = '#f4ede4'
      ctx.fillRect(ax, ay, aw, ah)

      ctx.strokeStyle = '#ded9ce'
      ctx.lineWidth = 1.2
      for (let x = ax; x <= ax + aw; x += 20) {
        ctx.beginPath()
        ctx.moveTo(x, ay)
        ctx.lineTo(x, ay + ah)
        ctx.stroke()
      }
      for (let y = ay; y <= ay + ah; y += 20) {
        ctx.beginPath()
        ctx.moveTo(ax, y)
        ctx.lineTo(ax + aw, y)
        ctx.stroke()
      }
      ctx.strokeStyle = '#e74c3c'
      ctx.lineWidth = 2.5
      ctx.strokeRect(ax, ay, aw, ah)
    } else if (area.type === 'storage') {
      // Depósito
      ctx.fillStyle = '#283747'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = '#95a5a6'
      ctx.lineWidth = 2
      ctx.strokeRect(ax, ay, aw, ah)
    } else if (area.type === 'bar_zone') {
      // Zona de Barra
      ctx.fillStyle = '#1b263b'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = '#3498db'
      ctx.lineWidth = 2
      ctx.strokeRect(ax, ay, aw, ah)
    } else if (area.type === 'terrace') {
      // Terraza / Jardín
      ctx.fillStyle = 'rgba(39, 174, 96, 0.45)'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = '#2ecc71'
      ctx.lineWidth = 2
      ctx.setLineDash([6, 4])
      ctx.strokeRect(ax, ay, aw, ah)
      ctx.setLineDash([])
    } else if (area.type === 'wall') {
      // Muro / Pared
      ctx.fillStyle = '#1c1c1c'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = '#ffb300'
      ctx.lineWidth = 2
      ctx.strokeRect(ax, ay, aw, ah)
    } else if (area.type === 'entrance') {
      // Entrada / Alfombra
      ctx.fillStyle = '#5b2c6f'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = '#bb8fce'
      ctx.lineWidth = 2
      ctx.setLineDash([5, 3])
      ctx.strokeRect(ax, ay, aw, ah)
      ctx.setLineDash([])
    } else if (area.type === 'pass_counter') {
      // Mostrador de Pase configurado como área
      ctx.fillStyle = '#e65100'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = '#ffe082'
      ctx.lineWidth = 2
      ctx.setLineDash([6, 3])
      ctx.strokeRect(ax, ay, aw, ah)
      ctx.setLineDash([])
    } else {
      // Área personalizada
      ctx.fillStyle = area.color ? `${area.color}33` : 'rgba(0, 207, 232, 0.2)'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = area.color || '#00cfe8'
      ctx.lineWidth = 2
      ctx.strokeRect(ax, ay, aw, ah)
    }

    // Cabecera del Área (Píldora Oscura con Alto Contraste)
    const pillW = Math.min(aw - 8, 120)
    ctx.fillStyle = 'rgba(12, 14, 22, 0.92)'
    drawRoundedRect(ctx, ax + 4, ay + 4, pillW, 18, 4, true, false)
    ctx.fillStyle = '#ffffff'
    ctx.font = 'bold 10px sans-serif'
    ctx.textAlign = 'left'
    ctx.fillText((area.name || area.type).substring(0, 16), ax + 10, ay + 16)

    // Marca de agua central con nombre y superficie m²
    ctx.fillStyle = 'rgba(255, 255, 255, 0.28)'
    ctx.font = 'bold 12px sans-serif'
    ctx.textAlign = 'center'
    ctx.fillText(`${(Number(area.w || 1) * Number(area.h || 1)).toFixed(1)} m²`, ax + aw / 2, ay + ah / 2 + 5)

    ctx.restore()
  })
}

// ==========================================
// 3. EQUIPAMIENTO REAL (MOSTRADORES, NEVERAS, ESTACIONES KDS)
// ==========================================
const drawEquipment = () => {
  const equipList = props.zone?.layout_config?.equipment || []

  equipList.forEach((eq) => {
    const ex = offsetX + Number(eq.x || 0) * cellPixelSize
    const ey = offsetY + Number(eq.y || 0) * cellPixelSize
    const ew = Number(eq.w || 1.5) * cellPixelSize
    const eh = Number(eq.h || 1.0) * cellPixelSize

    ctx.save()

    // Sombra del mueble
    ctx.fillStyle = 'rgba(0, 0, 0, 0.35)'
    drawRoundedRect(ctx, ex + 3, ey + 4, ew, eh, 6, true, false)

    if (eq.type === 'cooling') {
      // Nevera / Congelador (Cian / Petróleo)
      ctx.fillStyle = '#006064'
      drawRoundedRect(ctx, ex, ey, ew, eh, 6, true, false)
      ctx.fillStyle = '#00838f'
      drawRoundedRect(ctx, ex + 3, ey + 3, ew - 6, eh - 6, 4, true, false)

      // Tirador metálico
      ctx.fillStyle = '#4dd0e1'
      ctx.fillRect(ex + ew - 8, ey + 6, 3, eh - 12)

      // Texto de frío
      ctx.fillStyle = '#ffffff'
      ctx.font = 'bold 11px sans-serif'
      ctx.textAlign = 'center'
      ctx.fillText(`❄ ${eq.name || 'FRÍO'}`, ex + ew / 2, ey + eh / 2 + 4)
    } else if (eq.type === 'counter') {
      // Mostrador / Barra de Salón
      ctx.fillStyle = '#01579b'
      drawRoundedRect(ctx, ex, ey, ew, eh, 6, true, false)
      ctx.fillStyle = '#0288d1'
      drawRoundedRect(ctx, ex + 3, ey + 3, ew - 6, eh - 6, 4, true, false)

      ctx.fillStyle = '#ffffff'
      ctx.font = 'bold 11px sans-serif'
      ctx.textAlign = 'center'
      ctx.fillText(eq.name || 'MOSTRADOR', ex + ew / 2, ey + eh / 2 + 4)
    } else if (eq.type === 'pass_counter') {
      // Mostrador de Pase de Platos
      ctx.fillStyle = '#bf360c'
      drawRoundedRect(ctx, ex, ey, ew, eh, 6, true, false)
      ctx.fillStyle = '#e65100'
      drawRoundedRect(ctx, ex + 3, ey + 3, ew - 6, eh - 6, 4, true, false)

      // Campana dorada en el centro
      const bellX = ex + ew / 2
      ctx.fillStyle = '#ffd54f'
      ctx.beginPath()
      ctx.arc(bellX, ey + 10, 6, Math.PI, Math.PI * 2)
      ctx.fill()
      ctx.fillRect(bellX - 8, ey + 10, 16, 3)

      ctx.fillStyle = '#ffffff'
      ctx.font = 'bold 11px sans-serif'
      ctx.textAlign = 'center'
      ctx.fillText(eq.name || 'PASE DE PLATOS', ex + ew / 2, ey + eh / 2 + 10)
    } else if (eq.type === 'station') {
      // Estación KDS de Cocina (Magenta / Púrpura)
      ctx.fillStyle = '#880e4f'
      drawRoundedRect(ctx, ex, ey, ew, eh, 6, true, false)
      ctx.fillStyle = '#c2185b'
      drawRoundedRect(ctx, ex + 3, ey + 3, ew - 6, eh - 6, 4, true, false)

      // Hornilla o quemador
      ctx.fillStyle = '#1e1e24'
      ctx.beginPath()
      ctx.arc(ex + 18, ey + eh / 2, 8, 0, Math.PI * 2)
      ctx.fill()
      ctx.fillStyle = '#ff4757'
      ctx.beginPath()
      ctx.arc(ex + 18, ey + eh / 2, 4 + Math.sin(globalTick * 0.2) * 1.5, 0, Math.PI * 2)
      ctx.fill()

      ctx.fillStyle = '#ffffff'
      ctx.font = 'bold 11px sans-serif'
      ctx.textAlign = 'center'
      ctx.fillText(eq.name || 'ESTACIÓN', ex + ew / 2 + 8, ey + eh / 2 + 4)
    } else {
      // Otro equipamiento general
      ctx.fillStyle = '#263238'
      drawRoundedRect(ctx, ex, ey, ew, eh, 6, true, false)
      ctx.fillStyle = '#455a64'
      drawRoundedRect(ctx, ex + 3, ey + 3, ew - 6, eh - 6, 4, true, false)

      ctx.fillStyle = '#ffffff'
      ctx.font = 'bold 11px sans-serif'
      ctx.textAlign = 'center'
      ctx.fillText(eq.name || 'EQUIPO', ex + ew / 2, ey + eh / 2 + 4)
    }

    ctx.restore()
  })
}

// ==========================================
// 4. MESAS REALES CON SILLAS Y COMENSALES
// ==========================================
const drawDiningTables = () => {
  props.tables.forEach((table) => {
    const tx = offsetX + Number(table.pos_x || 0)
    const ty = offsetY + Number(table.pos_y || 0)
    const tw = Number(table.width || 60)
    const th = Number(table.height || 60)
    const isRound = table.shape === 'round'
    const isSelected = props.selectedTableId === table.id

    ctx.save()

    // 1. Sombra de la Mesa
    ctx.fillStyle = 'rgba(0, 0, 0, 0.35)'
    ctx.beginPath()
    if (isRound) {
      ctx.arc(tx + tw / 2 + 3, ty + th / 2 + 5, tw / 2 + 2, 0, Math.PI * 2)
    } else {
      ctx.roundRect(tx + 3, ty + 5, tw, th, 8)
    }
    ctx.fill()

    // 2. Sillas alrededor de la mesa proporcionales a su capacidad real
    drawChairs(table, tx, ty, tw, th)

    // 3. Comensales si está ocupada
    if (table.status === 'occupied' || table.status === 'bill_requested') {
      drawGuests(table, tx, ty, tw, th)
    }

    // 4. Color y Borde de la Mesa según estado (idéntico al diseñador)
    let tableBg = '#16a34a' // available
    let tableBorder = '#22c55e'

    if (table.status === 'occupied') {
      tableBg = '#dc2626'
      tableBorder = '#f87171'
    } else if (table.status === 'bill_requested') {
      tableBg = '#ea580c'
      tableBorder = '#fb923c'
    } else if (table.status === 'reserved') {
      tableBg = '#0284c7'
      tableBorder = '#38bdf8'
    }

    // Tablero de la Mesa
    ctx.fillStyle = tableBg
    ctx.strokeStyle = tableBorder
    ctx.lineWidth = 2.5

    ctx.beginPath()
    if (isRound) {
      ctx.arc(tx + tw / 2, ty + th / 2, tw / 2, 0, Math.PI * 2)
    } else if (table.shape === 'bar') {
      ctx.roundRect(tx, ty, tw, th, 5)
    } else {
      ctx.roundRect(tx, ty, tw, th, 8)
    }
    ctx.fill()
    ctx.stroke()

    // Resaltado si está seleccionada
    if (isSelected) {
      ctx.strokeStyle = '#00cfe8'
      ctx.lineWidth = 3.5
      ctx.beginPath()
      if (isRound) {
        ctx.arc(tx + tw / 2, ty + th / 2, tw / 2 + 6 + Math.sin(globalTick * 0.15) * 2, 0, Math.PI * 2)
      } else {
        ctx.roundRect(tx - 4, ty - 4, tw + 8, th + 8, 10)
      }
      ctx.stroke()
    }

    // Detalles sobre la mesa si está ocupada (platos / comida)
    if (table.status === 'occupied' || table.status === 'bill_requested') {
      ctx.fillStyle = '#ffffff'
      ctx.beginPath()
      ctx.arc(tx + tw / 2 - 12, ty + th / 2, 5, 0, Math.PI * 2)
      ctx.arc(tx + tw / 2 + 12, ty + th / 2, 5, 0, Math.PI * 2)
      ctx.fill()
      ctx.fillStyle = '#e11d48'
      ctx.beginPath()
      ctx.arc(tx + tw / 2 - 12, ty + th / 2, 2.5, 0, Math.PI * 2)
      ctx.fill()
    }

    // Nombre de la Mesa con texto nítido y alto contraste
    ctx.fillStyle = '#ffffff'
    ctx.font = 'bold 12px sans-serif'
    ctx.textAlign = 'center'
    ctx.fillText(table.name || 'Mesa', tx + tw / 2, ty + th / 2 - 2)

    // Píldora de Capacidad o Total USD de comanda
    if (table.current_order?.total_amount_usd) {
      ctx.fillStyle = '#ffd54f'
      ctx.font = 'bold 10px sans-serif'
      ctx.fillText(`$${Number(table.current_order.total_amount_usd).toFixed(2)}`, tx + tw / 2, ty + th / 2 + 14)
    } else {
      ctx.fillStyle = 'rgba(255, 255, 255, 0.85)'
      ctx.font = 'bold 9px sans-serif'
      ctx.fillText(`${table.capacity || 2}p`, tx + tw / 2, ty + th / 2 + 13)
    }

    // Burbujas flotantes sobre la mesa
    if (table.status === 'bill_requested') {
      drawStatusBubble(tx + tw / 2, ty - 18, '💰 ¡Cuenta!', '#ea580c')
    } else if (table.status === 'occupied') {
      drawStatusBubble(tx + tw / 2, ty - 18, '🍽️ Comiendo', '#dc2626')
    }

    ctx.restore()
  })
}

// Cálculo dinámico de sillas de la mesa (idéntico al diseñador de floor-map)
const getTableSeatsPositions = (table, tx, ty, tw, th) => {
  const cap = Math.max(1, Number(table.capacity || 2))
  const shape = table.shape || 'square'
  const seats = []

  const isBlocked = (side) => {
    if (table.blocked_sides && typeof table.blocked_sides[side] !== 'undefined') {
      return !!table.blocked_sides[side]
    }
    const threshold = 18
    const px = Number(table.pos_x || 0)
    const py = Number(table.pos_y || 0)
    const zW = zonePixelWidth.value
    const zH = zonePixelHeight.value

    if (side === 'top' && py <= threshold) return true
    if (side === 'left' && px <= threshold) return true
    if (side === 'right' && (px + tw) >= (zW - threshold)) return true
    if (side === 'bottom' && (py + th) >= (zH - threshold)) return true
    return false
  }

  const blocked = {
    top: isBlocked('top'),
    bottom: isBlocked('bottom'),
    left: isBlocked('left'),
    right: isBlocked('right'),
  }

  // Redonda
  if (shape === 'round') {
    const rx = tw / 2 + 10
    const ry = th / 2 + 10
    for (let i = 0; i < cap; i++) {
      const angle = (2 * Math.PI * i) / cap - Math.PI / 2
      const sx = tx + tw / 2 + rx * Math.cos(angle)
      const sy = ty + th / 2 + ry * Math.sin(angle)
      seats.push({ x: sx, y: sy, isStool: false })
    }
    return seats
  }

  // Módulo de Barra
  if (shape === 'bar') {
    let clientSide = 'bottom'
    if (blocked.bottom && !blocked.top) clientSide = 'top'
    else if (blocked.top && !blocked.bottom) clientSide = 'bottom'
    else if (blocked.left && !blocked.right) clientSide = 'right'
    else if (blocked.right && !blocked.left) clientSide = 'left'

    if (!blocked[clientSide]) {
      for (let i = 0; i < cap; i++) {
        const offset = tw * ((i + 0.5) / cap)
        const sy = clientSide === 'bottom' ? ty + th + 9 : ty - 9
        seats.push({ x: tx + offset, y: sy, isStool: true })
      }
    }
    return seats
  }

  // Cuadrada o Rectangular
  let topCount = 0
  let bottomCount = 0
  let leftCount = 0
  let rightCount = 0

  if (cap === 1) {
    bottomCount = 1
  } else if (cap === 2) {
    if (tw >= th) {
      topCount = 1
      bottomCount = 1
    } else {
      leftCount = 1
      rightCount = 1
    }
  } else if (cap === 3) {
    topCount = 1
    bottomCount = 2
  } else if (cap === 4) {
    topCount = 1
    bottomCount = 1
    leftCount = 1
    rightCount = 1
  } else {
    if (tw >= th) {
      leftCount = 1
      rightCount = 1
      const rem = cap - 2
      topCount = Math.ceil(rem / 2)
      bottomCount = Math.floor(rem / 2)
    } else {
      topCount = 1
      bottomCount = 1
      const rem = cap - 2
      leftCount = Math.ceil(rem / 2)
      rightCount = Math.floor(rem / 2)
    }
  }

  if (blocked.top) topCount = 0
  if (blocked.bottom) bottomCount = 0
  if (blocked.left) leftCount = 0
  if (blocked.right) rightCount = 0

  for (let i = 0; i < topCount; i++) {
    seats.push({ x: tx + tw * ((i + 0.5) / topCount), y: ty - 9, isStool: false })
  }
  for (let i = 0; i < bottomCount; i++) {
    seats.push({ x: tx + tw * ((i + 0.5) / bottomCount), y: ty + th + 9, isStool: false })
  }
  for (let i = 0; i < leftCount; i++) {
    seats.push({ x: tx - 9, y: ty + th * ((i + 0.5) / leftCount), isStool: false })
  }
  for (let i = 0; i < rightCount; i++) {
    seats.push({ x: tx + tw + 9, y: ty + th * ((i + 0.5) / rightCount), isStool: false })
  }

  return seats
}

// Dibujo de sillas alrededor de la mesa
const drawChairs = (table, tx, ty, tw, th) => {
  const seats = getTableSeatsPositions(table, tx, ty, tw, th)

  seats.forEach((seat) => {
    ctx.fillStyle = seat.isStool ? '#fbbf24' : '#f8fafc'
    ctx.strokeStyle = seat.isStool ? '#78350f' : '#334155'
    ctx.lineWidth = 1.8

    ctx.beginPath()
    if (seat.isStool) {
      drawRoundedRect(ctx, seat.x - 6, seat.y - 6, 12, 12, 3, true, true)
    } else {
      ctx.arc(seat.x, seat.y, 6, 0, Math.PI * 2)
      ctx.fill()
      ctx.stroke()
    }
  })
}

// Comensales sentados
const drawGuests = (table, tx, ty, tw, th) => {
  const seats = getTableSeatsPositions(table, tx, ty, tw, th)
  const hairColors = ['#451a03', '#1e293b', '#b45309', '#0284c7']

  seats.forEach((seat, idx) => {
    ctx.fillStyle = '#fde047'
    ctx.beginPath()
    ctx.arc(seat.x, seat.y, 6.5, 0, Math.PI * 2)
    ctx.fill()

    ctx.fillStyle = hairColors[idx % hairColors.length]
    ctx.beginPath()
    ctx.arc(seat.x, seat.y - 2, 6.5, Math.PI, Math.PI * 2)
    ctx.fill()
  })
}

// ==========================================
// 5. DIBUJO DE CHEFS (ESTILO OVERCOOKED)
// ==========================================
const drawChef = (chef) => {
  const bobY = Math.sin(chef.bobOffset) * 2.5
  const cx = chef.x
  const cy = chef.y + bobY

  // Sombra
  ctx.fillStyle = 'rgba(0, 0, 0, 0.3)'
  ctx.beginPath()
  ctx.ellipse(cx, chef.y + 14, 12, 5, 0, 0, Math.PI * 2)
  ctx.fill()

  // Cuerpo y Delantal
  ctx.fillStyle = '#ffffff'
  drawRoundedRect(ctx, cx - 10, cy - 6, 20, 18, 5, true, false)

  // Delantal coloreado
  ctx.fillStyle = '#ff9f43'
  ctx.fillRect(cx - 6, cy - 2, 12, 12)

  // Cabeza Chibi
  ctx.fillStyle = '#fde68a'
  ctx.beginPath()
  ctx.arc(cx, cy - 9, 10, 0, Math.PI * 2)
  ctx.fill()

  // Ojos
  ctx.fillStyle = '#1e293b'
  ctx.beginPath()
  ctx.arc(cx - 3.5, cy - 9, 1.8, 0, Math.PI * 2)
  ctx.arc(cx + 3.5, cy - 9, 1.8, 0, Math.PI * 2)
  ctx.fill()

  // Gorro de Chef
  ctx.fillStyle = '#ffffff'
  ctx.strokeStyle = '#e2e8f0'
  ctx.lineWidth = 1
  ctx.beginPath()
  ctx.arc(cx - 6, cy - 20, 6, 0, Math.PI * 2)
  ctx.arc(cx + 6, cy - 20, 6, 0, Math.PI * 2)
  ctx.arc(cx, cy - 24, 7, 0, Math.PI * 2)
  ctx.fill()
  ctx.stroke()
  ctx.fillRect(cx - 8, cy - 17, 16, 5)

  // Sartén animada
  const panAngle = Math.sin(globalTick * 0.15) * 0.3
  ctx.save()
  ctx.translate(cx + 10, cy)
  ctx.rotate(panAngle)
  ctx.fillStyle = '#334155'
  ctx.fillRect(0, -2, 8, 3)
  ctx.beginPath()
  ctx.arc(10, 0, 6, 0, Math.PI * 2)
  ctx.fill()
  ctx.restore()

  // Progreso de cocción circular
  ctx.strokeStyle = '#e2e8f0'
  ctx.lineWidth = 2.5
  ctx.beginPath()
  ctx.arc(cx, cy - 34, 6, 0, Math.PI * 2)
  ctx.stroke()
  ctx.strokeStyle = '#10b981'
  ctx.beginPath()
  ctx.arc(cx, cy - 34, 6, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * chef.cookingProgress)
  ctx.stroke()

  // Nombre del Chef
  ctx.fillStyle = '#ffffff'
  ctx.font = 'bold 9px sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText(chef.name, cx, cy + 24)
}

// ==========================================
// 6. DIBUJO DE MESEROS
// ==========================================
const drawWaiter = (waiter) => {
  const bobY = Math.sin(waiter.bobOffset) * 2.5
  const wx = waiter.x
  const wy = waiter.y + bobY

  // Sombra
  ctx.fillStyle = 'rgba(0, 0, 0, 0.3)'
  ctx.beginPath()
  ctx.ellipse(wx, waiter.y + 14, 12, 5, 0, 0, Math.PI * 2)
  ctx.fill()

  // Camisa y chaleco
  ctx.fillStyle = '#ffffff'
  drawRoundedRect(ctx, wx - 10, wy - 6, 20, 18, 5, true, false)
  ctx.fillStyle = '#1e1b4b'
  ctx.fillRect(wx - 9, wy - 4, 5, 14)
  ctx.fillRect(wx + 4, wy - 4, 5, 14)

  // Pajarita roja
  ctx.fillStyle = '#dc2626'
  ctx.beginPath()
  ctx.arc(wx, wy - 4, 2, 0, Math.PI * 2)
  ctx.fill()

  // Cabeza Chibi
  ctx.fillStyle = '#fde68a'
  ctx.beginPath()
  ctx.arc(wx, wy - 9, 10, 0, Math.PI * 2)
  ctx.fill()

  // Cabello
  ctx.fillStyle = '#451a03'
  ctx.beginPath()
  ctx.arc(wx, wy - 12, 10, Math.PI, Math.PI * 2)
  ctx.fill()

  // Ojos
  ctx.fillStyle = '#1e293b'
  ctx.beginPath()
  ctx.arc(wx - 3.5, wy - 9, 1.8, 0, Math.PI * 2)
  ctx.arc(wx + 3.5, wy - 9, 1.8, 0, Math.PI * 2)
  ctx.fill()

  // Bandeja si lleva pedido
  if (waiter.carryingDish) {
    ctx.fillStyle = '#cbd5e1'
    ctx.beginPath()
    ctx.ellipse(wx + 10, wy - 2, 8, 3.5, 0, 0, Math.PI * 2)
    ctx.fill()
    ctx.fillStyle = '#94a3b8'
    ctx.beginPath()
    ctx.arc(wx + 10, wy - 5, 4.5, Math.PI, Math.PI * 2)
    ctx.fill()
  }

  // Nombre del Mesero
  ctx.fillStyle = '#ffffff'
  ctx.font = 'bold 9px sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText(waiter.name, wx, wy + 24)
}

// ==========================================
// 7. PARTÍCULAS Y BURBUJAS
// ==========================================
const drawParticles = () => {
  particles.forEach((p) => {
    ctx.save()
    ctx.globalAlpha = p.alpha
    ctx.fillStyle = p.type === 'flame' ? '#ff7675' : '#ffffff'
    ctx.beginPath()
    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2)
    ctx.fill()
    ctx.restore()
  })
}

const drawFloatingBubbles = () => {
  floatingBubbles.forEach((b) => {
    ctx.save()
    ctx.globalAlpha = Math.max(0, b.alpha)
    drawStatusBubble(b.x, b.y, b.text, '#10b981')
    ctx.restore()
  })
}

const drawStatusBubble = (x, y, text, color) => {
  ctx.font = 'bold 10px sans-serif'
  const textWidth = ctx.measureText(text).width
  const padding = 8
  const bubbleW = textWidth + padding * 2
  const bubbleH = 18

  ctx.fillStyle = '#ffffff'
  ctx.strokeStyle = color
  ctx.lineWidth = 1.8
  drawRoundedRect(ctx, x - bubbleW / 2, y - bubbleH / 2, bubbleW, bubbleH, 8, true, true)

  ctx.fillStyle = '#ffffff'
  ctx.beginPath()
  ctx.moveTo(x - 3, y + bubbleH / 2)
  ctx.lineTo(x + 3, y + bubbleH / 2)
  ctx.lineTo(x, y + bubbleH / 2 + 4)
  ctx.fill()

  ctx.fillStyle = '#0f172a'
  ctx.textAlign = 'center'
  ctx.fillText(text, x, y + 3.5)
}

// Utilidad: Rectángulo con esquinas redondeadas
const drawRoundedRect = (context, x, y, width, height, radius, fill, stroke) => {
  context.beginPath()
  context.moveTo(x + radius, y)
  context.lineTo(x + width - radius, y)
  context.quadraticCurveTo(x + width, y, x + width, y + radius)
  context.lineTo(x + width, y + height - radius)
  context.quadraticCurveTo(x + width, y + height, x + width - radius, y + height)
  context.lineTo(x + radius, y + height)
  context.quadraticCurveTo(x, y + height, x, y + height - radius)
  context.lineTo(x, y + radius)
  context.quadraticCurveTo(x, y, x + radius, y)
  context.closePath()
  if (fill) context.fill()
  if (stroke) context.stroke()
}

// ==========================================
// INTERACCIÓN DE RATÓN Y CÁMARA
// ==========================================
const handleMouseDown = (e) => {
  camera.value.isDragging = true
  camera.value.dragStartX = e.clientX - camera.value.x
  camera.value.dragStartY = e.clientY - camera.value.y
}

const handleMouseMove = (e) => {
  if (!camera.value.isDragging) return
  camera.value.x = e.clientX - camera.value.dragStartX
  camera.value.y = e.clientY - camera.value.dragStartY
}

const handleMouseUp = () => {
  camera.value.isDragging = false
}

// Detección de Clic en Mesas o Chefs basada en coordenadas reales
const handleClick = (e) => {
  if (!canvasRef.value) return
  const rect = canvasRef.value.getBoundingClientRect()
  const mouseScreenX = e.clientX - rect.left
  const mouseScreenY = e.clientY - rect.top

  const worldX = (mouseScreenX - camera.value.x) / camera.value.zoom
  const worldY = (mouseScreenY - camera.value.y) / camera.value.zoom

  // 1. Chequear clic en mesas reales
  for (const table of props.tables) {
    const tx = offsetX + Number(table.pos_x || 0)
    const ty = offsetY + Number(table.pos_y || 0)
    const tw = Number(table.width || 60)
    const th = Number(table.height || 60)

    if (worldX >= tx && worldX <= tx + tw && worldY >= ty && worldY <= ty + th) {
      emit('select-table', table)
      return
    }
  }

  // 2. Chequear clic en chefs
  for (const chef of chefs) {
    const dist = Math.hypot(worldX - chef.x, worldY - chef.y)
    if (dist <= 25) {
      floatingBubbles.push({
        x: chef.x,
        y: chef.y - 45,
        text: `👨‍🍳 ${chef.stationName}`,
        alpha: 1.0,
        vy: -0.6,
      })
      emit('select-station', chef.station)
      return
    }
  }
}

// Zoom y Controles
const zoomIn = () => {
  camera.value.zoom = Math.min(camera.value.zoom + 0.15, 2.0)
}

const zoomOut = () => {
  camera.value.zoom = Math.max(camera.value.zoom - 0.15, 0.5)
}

const resetCamera = () => {
  camera.value.x = 0
  camera.value.y = 0
  camera.value.zoom = 1.0
}

// Resize Canvas responsivo
const resizeCanvas = () => {
  if (!canvasRef.value) return
  const parent = canvasRef.value.parentElement
  if (parent) {
    canvasRef.value.width = parent.clientWidth || 1000
    canvasRef.value.height = 620
  }
}

// Observar reactividad de zonas, mesas y estaciones KDS
watch(
  [() => props.zone, () => props.tables, () => props.stations],
  () => {
    initChefs()
    initWaiters()
  },
  { deep: true, immediate: true }
)

onMounted(() => {
  if (canvasRef.value) {
    ctx = canvasRef.value.getContext('2d')
    resizeCanvas()
    window.addEventListener('resize', resizeCanvas)
    initChefs()
    initWaiters()
    render()
  }
})

onUnmounted(() => {
  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId)
  }
  window.removeEventListener('resize', resizeCanvas)
})
</script>

<style scoped>
.overcooked-live-container {
  min-height: 620px;
  background-color: #0b0d14;
  box-shadow: inset 0 0 25px rgba(0, 0, 0, 0.7);
}

.overcooked-hud-bar {
  position: absolute;
  top: 10px;
  left: 10px;
  right: 10px;
  z-index: 10;
  background: rgba(18, 20, 30, 0.88);
  backdrop-filter: blur(8px);
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.overcooked-bottom-guide {
  position: absolute;
  bottom: 8px;
  left: 10px;
  right: 10px;
  z-index: 10;
  background: rgba(18, 20, 30, 0.88);
  backdrop-filter: blur(8px);
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #fff;
}

.overcooked-canvas {
  width: 100%;
  height: 620px;
  display: block;
  cursor: grab;
}

.overcooked-canvas:active {
  cursor: grabbing;
}
</style>
