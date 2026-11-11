<template>
  <div class="overcooked-live-container position-relative overflow-hidden rounded-lg">
    <!-- Barra Flotante de Herramientas y Estado HUD -->
    <div class="overcooked-hud-bar d-flex align-center justify-space-between flex-wrap gap-2 pa-3">
      <div class="d-flex align-center gap-2">
        <v-chip color="primary" variant="flat" size="small" prepend-icon="tabler-chef-hat">
          <strong>Restaurante en Vivo</strong>
        </v-chip>
        <v-chip color="success" variant="tonal" size="small" prepend-icon="tabler-clock">
          60 FPS Activo
        </v-chip>
        <v-chip v-if="activeChefsCount" color="warning" variant="tonal" size="small" prepend-icon="tabler-flame">
          {{ activeChefsCount }} Chefs en Cocina
        </v-chip>
        <v-chip v-if="activeWaitersCount" color="info" variant="tonal" size="small" prepend-icon="tabler-user-check">
          {{ activeWaitersCount }} Meseros en Salón
        </v-chip>
      </div>

      <div class="d-flex align-center gap-1">
        <v-btn
          icon="tabler-zoom-in"
          variant="tonal"
          size="x-small"
          color="secondary"
          title="Acercar"
          @click="zoomIn"
        />
        <v-btn
          icon="tabler-zoom-out"
          variant="tonal"
          size="x-small"
          color="secondary"
          title="Alejar"
          @click="zoomOut"
        />
        <v-btn
          icon="tabler-refresh"
          variant="tonal"
          size="x-small"
          color="secondary"
          title="Resetear Cámara"
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
      <div class="d-flex align-center gap-3">
        <span><v-icon size="14" color="success">tabler-circle-filled</v-icon> Mesa Libre</span>
        <span><v-icon size="14" color="error">tabler-circle-filled</v-icon> Mesa Comiendo</span>
        <span><v-icon size="14" color="warning">tabler-circle-filled</v-icon> Pidiendo Cuenta</span>
        <span><v-icon size="14" color="info">tabler-circle-filled</v-icon> Reservada</span>
      </div>
      <div class="text-medium-emphasis">
        Haz clic en cualquier mesa o chef para interactuar • Arrastra para mover la cámara
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

// Dimensiones Virtuales del Mundo
const worldWidth = 1400
const worldHeight = 850

// Agentes de Simulación (Chefs, Meseros, Comensales, Partículas)
let chefs = []
let waiters = []
let particles = []
let floatingBubbles = []
let globalTick = 0

// Conteos computados para el HUD
const activeChefsCount = computed(() => chefs.length || props.stations.length || 3)
const activeWaitersCount = computed(() => waiters.length || 2)

// Paleta de Estilos Overcooked
const THEME = {
  bgKitchenFloor: '#f4ede4',
  bgKitchenTiles: '#e2d3c1',
  bgDiningFloor: '#c99a6b',
  bgDiningWoodDark: '#b07f4f',
  kitchenWall: '#3d4451',
  kitchenCounter: '#5c6475',
  counterTop: '#cfd6e4',
  passCounter: '#96593a',
  passCounterTop: '#e0c29e',
  carpetRed: '#a8292b',
  carpetBorder: '#e4b647',
}

// Inicialización de Agentes Chefs según las Estaciones
const initChefs = () => {
  chefs = []
  const stationList = props.stations.length ? props.stations : [
    { id: 1, name: 'Parrilla & Fuego', code: 'GRILL', color: 'error', icon: 'tabler-flame' },
    { id: 2, name: 'Estación de Corte', code: 'PREP', color: 'success', icon: 'tabler-tools-kitchen-2' },
    { id: 3, name: 'Barra de Bebidas', code: 'BAR', color: 'info', icon: 'tabler-glass-full' },
  ]

  const kitchenArea = props.zone?.layout_config?.areas?.find(a => a.type === 'kitchen')
  const scale = 65
  const offsetX = 50
  const offsetY = 50

  stationList.forEach((station, index) => {
    let stationX = 140 + index * 160
    let stationY = 130
    if (kitchenArea) {
      stationX = offsetX + kitchenArea.x * scale + 50 + (index % 3) * 100
      stationY = offsetY + kitchenArea.y * scale + 40 + Math.floor(index / 3) * 50
    }

    chefs.push({
      id: station.id,
      name: `Chef ${station.code || station.name}`,
      stationName: station.name,
      x: stationX,
      y: stationY + 35,
      targetX: stationX,
      targetY: stationY + 35,
      homeX: stationX,
      homeY: stationY + 35,
      color: station.color || '#e056fd',
      state: 'cooking', // 'cooking', 'delivering', 'idle'
      bobOffset: Math.random() * Math.PI * 2,
      carryingDish: null,
      cookingProgress: 0.3 + (index * 0.25) % 0.7,
      station,
    })
  })
}

// Inicialización de Agentes Meseros
const initWaiters = () => {
  waiters = [
    {
      id: 1,
      name: 'Mesero Alex',
      x: 350,
      y: 340,
      targetX: 350,
      targetY: 340,
      speed: 2.2,
      state: 'patrolling', // 'patrolling', 'carrying_order', 'attending_call'
      carryingDish: null,
      bobOffset: 0,
      targetTable: null,
      taskCooldown: 120,
    },
    {
      id: 2,
      name: 'Mesera Sofía',
      x: 750,
      y: 350,
      targetX: 750,
      targetY: 350,
      speed: 2.4,
      state: 'patrolling',
      carryingDish: null,
      bobOffset: 1.5,
      targetTable: null,
      taskCooldown: 200,
    },
  ]
}

// Partículas de Fuego y Vapor
const spawnParticle = (x, y, type = 'steam') => {
  if (particles.length > 50) particles.shift()
  particles.push({
    x: x + (Math.random() - 0.5) * 16,
    y: y,
    vx: (Math.random() - 0.5) * 0.8,
    vy: -0.8 - Math.random() * 0.8,
    radius: 3 + Math.random() * 3,
    alpha: 0.8,
    type, // 'steam', 'flame', 'sparkle'
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
      // Emite una burbuja de plato listo en el pase
      floatingBubbles.push({
        x: chef.x,
        y: chef.y - 45,
        text: '¡Plato Listo!',
        alpha: 1.0,
        vy: -0.6,
      })
    }

    // Partículas de fuego/vapor sobre la estación
    if (globalTick % 6 === 0) {
      spawnParticle(chef.homeX, chef.homeY - 20, 'flame')
      spawnParticle(chef.homeX + 10, chef.homeY - 30, 'steam')
    }
  })

  // 2. Actualizar Meseros
  const occupiedTables = props.tables.filter(t => t.status === 'occupied' || t.status === 'bill_requested')

  waiters.forEach((waiter) => {
    waiter.bobOffset += 0.12

    // Movimiento hacia el destino
    const dx = waiter.targetX - waiter.x
    const dy = waiter.targetY - waiter.y
    const dist = Math.hypot(dx, dy)

    if (dist > 4) {
      waiter.x += (dx / dist) * waiter.speed
      waiter.y += (dy / dist) * waiter.speed
    } else {
      // Llegó a su destino -> Cambiar de tarea
      waiter.taskCooldown--
      if (waiter.taskCooldown <= 0) {
        waiter.taskCooldown = 150 + Math.floor(Math.random() * 120)

        // Asignar nueva mesa o ir a la barra de pase
        if (occupiedTables.length && Math.random() > 0.4) {
          const randomTable = occupiedTables[Math.floor(Math.random() * occupiedTables.length)]
          waiter.targetTable = randomTable
          // Coordenada junto a la mesa en el salón
          waiter.targetX = (randomTable.pos_x || 300) + 70
          waiter.targetY = (randomTable.pos_y || 400) + 30
          waiter.carryingDish = 'Bandeja'
          waiter.state = 'serving'
        } else {
          // Volver al pasillo central / pase
          waiter.targetX = 250 + Math.random() * 450
          waiter.targetY = 280 + Math.random() * 80
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
    if (p.alpha <= 0) {
      particles.splice(i, 1)
    }
  }

  // 4. Actualizar Burbujas de Texto
  for (let i = floatingBubbles.length - 1; i >= 0; i--) {
    const b = floatingBubbles[i]
    b.y += b.vy
    b.alpha -= 0.012
    if (b.alpha <= 0) {
      floatingBubbles.splice(i, 1)
    }
  }
}

// ==========================================
// RENDERIZADO PRINCIPAL (CANVAS 2.5D)
// ==========================================
const render = () => {
  if (!ctx || !canvasRef.value) return

  const width = canvasRef.value.width
  const height = canvasRef.value.height

  ctx.clearRect(0, 0, width, height)

  ctx.save()
  // Aplicar Cámara y Zoom
  ctx.translate(camera.value.x, camera.value.y)
  ctx.scale(camera.value.zoom, camera.value.zoom)

  // 1. Dibujar Pisos y Escenario General (Cocina vs Salón)
  drawEnvironment()

  // 2. Dibujar Estaciones de Cocina
  drawKitchenStations()

  // 3. Dibujar Mostrador de Pase (Pass Counter)
  drawPassCounter()

  // 4. Dibujar Mesas y Comensales
  drawDiningTables()

  // 5. Dibujar Chefs
  chefs.forEach(drawChef)

  // 6. Dibujar Meseros
  waiters.forEach(drawWaiter)

  // 7. Dibujar Partículas y Burbujas
  drawParticles()
  drawFloatingBubbles()

  ctx.restore()

  updateAgents()
  animationFrameId = requestAnimationFrame(render)
}

// 1. DIBUJO DEL ENTORNO (PISOS Y PAREDES)
const drawEnvironment = () => {
  // Fondo global cálido
  ctx.fillStyle = '#1e1e2d'
  ctx.fillRect(-100, -100, worldWidth + 200, worldHeight + 200)

  const areas = props.zone?.layout_config?.areas || []

  if (areas.length > 0) {
    // Modo Plano Personalizado del Usuario
    const scale = 65
    const offsetX = 50
    const offsetY = 50

    // Piso base del salón comedor
    ctx.fillStyle = THEME.bgDiningFloor
    ctx.fillRect(offsetX, offsetY, worldWidth - 100, worldHeight - 100)

    // Vetas de madera
    ctx.strokeStyle = THEME.bgDiningWoodDark
    ctx.lineWidth = 1
    for (let y = offsetY; y <= worldHeight - 50; y += 45) {
      ctx.beginPath()
      ctx.moveTo(offsetX, y)
      ctx.lineTo(worldWidth - 50, y)
      ctx.stroke()
    }

    // Dibujar cada área arquitectónica personalizada
    areas.forEach((area) => {
      const ax = offsetX + area.x * scale
      const ay = offsetY + area.y * scale
      const aw = area.w * scale
      const ah = area.h * scale

      if (area.type === 'kitchen') {
        // Azulejos de cocina
        ctx.fillStyle = THEME.bgKitchenFloor
        ctx.fillRect(ax, ay, aw, ah)

        ctx.strokeStyle = THEME.bgKitchenTiles
        ctx.lineWidth = 1.2
        for (let x = ax; x <= ax + aw; x += 30) {
          ctx.beginPath()
          ctx.moveTo(x, ay)
          ctx.lineTo(x, ay + ah)
          ctx.stroke()
        }
        for (let y = ay; y <= ay + ah; y += 30) {
          ctx.beginPath()
          ctx.moveTo(ax, y)
          ctx.lineTo(ax + aw, y)
          ctx.stroke()
        }
        ctx.strokeStyle = '#e74c3c'
        ctx.lineWidth = 2
        ctx.strokeRect(ax, ay, aw, ah)

        ctx.fillStyle = '#333'
        ctx.font = 'bold 11px sans-serif'
        ctx.textAlign = 'left'
        ctx.fillText(area.name || 'COCINA', ax + 8, ay + 18)
      } else if (area.type === 'storage') {
        // Depósito
        ctx.fillStyle = '#2c3e50'
        ctx.fillRect(ax, ay, aw, ah)
        ctx.strokeStyle = '#7f8c8d'
        ctx.lineWidth = 2
        ctx.strokeRect(ax, ay, aw, ah)

        ctx.fillStyle = '#fff'
        ctx.font = 'bold 11px sans-serif'
        ctx.textAlign = 'left'
        ctx.fillText(area.name || 'DEPÓSITO', ax + 8, ay + 18)
      } else if (area.type === 'terrace') {
        // Terraza / Césped
        ctx.fillStyle = '#27ae60'
        ctx.fillRect(ax, ay, aw, ah)
        ctx.strokeStyle = '#2ecc71'
        ctx.lineWidth = 2
        ctx.strokeRect(ax, ay, aw, ah)

        ctx.fillStyle = '#fff'
        ctx.font = 'bold 11px sans-serif'
        ctx.textAlign = 'left'
        ctx.fillText(area.name || 'TERRAZA', ax + 8, ay + 18)
      } else if (area.type === 'wall') {
        // Muro
        ctx.fillStyle = '#1a1a1a'
        ctx.fillRect(ax, ay, aw, ah)
      } else if (area.type === 'entrance') {
        // Alfombra de entrada
        ctx.fillStyle = THEME.carpetRed
        ctx.fillRect(ax, ay, aw, ah)
        ctx.strokeStyle = THEME.carpetBorder
        ctx.lineWidth = 2.5
        ctx.strokeRect(ax + 3, ay + 3, aw - 6, ah - 6)

        ctx.fillStyle = '#fff'
        ctx.font = 'bold 11px sans-serif'
        ctx.textAlign = 'center'
        ctx.fillText('ENTRADA', ax + aw / 2, ay + ah / 2 + 4)
      } else if (area.type === 'pass_counter') {
        // Mostrador de Pase
        ctx.fillStyle = THEME.passCounter
        ctx.fillRect(ax, ay, aw, ah)
        ctx.fillStyle = THEME.passCounterTop
        ctx.fillRect(ax + 2, ay + 2, aw - 4, ah - 4)

        ctx.fillStyle = '#333'
        ctx.font = 'bold 10px sans-serif'
        ctx.textAlign = 'center'
        ctx.fillText('PASE DE PLATOS', ax + aw / 2, ay + ah / 2 + 3)
      }
    })
    return
  }

  // --- MODO FALLBACK (Si el usuario aún no configuró áreas) ---
  const kitchenHeight = 270
  ctx.fillStyle = THEME.bgKitchenFloor
  ctx.fillRect(40, 40, worldWidth - 80, kitchenHeight)

  // Patrón de Azulejos de Cocina
  ctx.strokeStyle = THEME.bgKitchenTiles
  ctx.lineWidth = 1.5
  const tileSize = 35
  for (let x = 40; x <= worldWidth - 40; x += tileSize) {
    ctx.beginPath()
    ctx.moveTo(x, 40)
    ctx.lineTo(x, 40 + kitchenHeight)
    ctx.stroke()
  }
  for (let y = 40; y <= 40 + kitchenHeight; y += tileSize) {
    ctx.beginPath()
    ctx.moveTo(40, y)
    ctx.lineTo(worldWidth - 40, y)
    ctx.stroke()
  }

  // Borde superior de pared de cocina
  ctx.fillStyle = THEME.kitchenWall
  ctx.fillRect(40, 25, worldWidth - 80, 20)

  // --- ZONA SALÓN (Inferior) ---
  const salonY = 40 + kitchenHeight + 25
  const salonHeight = worldHeight - salonY - 20
  ctx.fillStyle = THEME.bgDiningFloor
  ctx.fillRect(40, salonY, worldWidth - 80, salonHeight)

  // Vetas de Madera de Parquet en Salón
  ctx.strokeStyle = THEME.bgDiningWoodDark
  ctx.lineWidth = 1
  for (let y = salonY; y <= salonY + salonHeight; y += 45) {
    ctx.beginPath()
    ctx.moveTo(40, y)
    ctx.lineTo(worldWidth - 40, y)
    ctx.stroke()
  }

  // Alfombra de Entrada / Recepción (Extremo Derecho)
  const carpetX = worldWidth - 200
  ctx.fillStyle = THEME.carpetRed
  ctx.fillRect(carpetX, salonY + 40, 120, salonHeight - 80)
  ctx.strokeStyle = THEME.carpetBorder
  ctx.lineWidth = 3
  ctx.strokeRect(carpetX + 4, salonY + 44, 112, salonHeight - 88)

  // Letrero Chibi de "Entrada / Hostess"
  ctx.fillStyle = '#fff'
  ctx.font = 'bold 11px sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText('ENTRADA SALÓN', carpetX + 60, salonY + 65)
}

// 2. DIBUJO DE ESTACIONES DE COCINA
const drawKitchenStations = () => {
  const stationList = props.stations.length ? props.stations : [
    { id: 1, name: 'Parrilla & Fuego', code: 'GRILL', icon: '🔥' },
    { id: 2, name: 'Estación de Corte', code: 'PREP', icon: '🥗' },
    { id: 3, name: 'Barra de Bebidas', code: 'BAR', icon: '🍹' },
  ]

  const kitchenArea = props.zone?.layout_config?.areas?.find(a => a.type === 'kitchen')
  const scale = 65
  const offsetX = 50
  const offsetY = 50

  stationList.forEach((st, idx) => {
    let x = 140 + idx * 160
    let y = 80
    if (kitchenArea) {
      x = offsetX + kitchenArea.x * scale + 50 + (idx % 3) * 100
      y = offsetY + kitchenArea.y * scale + 40 + Math.floor(idx / 3) * 50
    }

    // Mueble de Cocina con Acero Inoxidable
    ctx.fillStyle = '#3f4654'
    drawRoundedRect(ctx, x - 55, y, 110, 65, 8, true, false)

    // Superficie Superior
    ctx.fillStyle = '#cfd6e4'
    drawRoundedRect(ctx, x - 50, y + 4, 100, 35, 6, true, false)

    // Detalle de Fogón / Utensilios
    if (idx === 0) {
      // Hornillas
      ctx.fillStyle = '#1e1e24'
      ctx.beginPath()
      ctx.arc(x - 20, y + 22, 10, 0, Math.PI * 2)
      ctx.arc(x + 20, y + 22, 10, 0, Math.PI * 2)
      ctx.fill()
      // Fuego parpadeante
      ctx.fillStyle = '#ff4757'
      ctx.beginPath()
      ctx.arc(x - 20, y + 22, 5 + Math.sin(globalTick * 0.2) * 2, 0, Math.PI * 2)
      ctx.fill()
    } else if (idx === 1) {
      // Tablas de cortar de madera
      ctx.fillStyle = '#b7791f'
      ctx.fillRect(x - 30, y + 14, 25, 16)
      ctx.fillStyle = '#38a169'
      ctx.fillRect(x + 10, y + 16, 18, 12)
    } else {
      // Máquina de café / vasos
      ctx.fillStyle = '#4a5568'
      ctx.fillRect(x - 25, y + 10, 20, 22)
      ctx.fillStyle = '#3182ce'
      ctx.fillRect(x + 10, y + 12, 16, 18)
    }

    // Cartel Colgante con Nombre
    ctx.fillStyle = '#2d3748'
    drawRoundedRect(ctx, x - 45, y - 24, 90, 20, 4, true, false)
    ctx.fillStyle = '#fff'
    ctx.font = 'bold 10px sans-serif'
    ctx.textAlign = 'center'
    ctx.fillText(`${st.name.substring(0, 14)}`, x, y - 10)
  })
}

// 3. MOSTRADOR DE PASE (PASS COUNTER)
const drawPassCounter = () => {
  const passArea = props.zone?.layout_config?.areas?.find(a => a.type === 'pass_counter')
  const scale = 65
  const offsetX = 50
  const offsetY = 50

  let counterX = 60
  let counterY = 280
  let counterWidth = worldWidth - 120

  if (passArea) {
    counterX = offsetX + passArea.x * scale
    counterY = offsetY + passArea.y * scale
    counterWidth = passArea.w * scale
  }

  // Sombra del mostrador
  ctx.fillStyle = 'rgba(0, 0, 0, 0.25)'
  ctx.fillRect(counterX, counterY + 16, counterWidth, 12)

  // Cuerpo de Madera
  ctx.fillStyle = THEME.passCounter
  drawRoundedRect(ctx, counterX, counterY - 10, counterWidth, 24, 6, true, false)

  // Encimera
  ctx.fillStyle = THEME.passCounterTop
  drawRoundedRect(ctx, counterX - 5, counterY - 14, counterWidth + 10, 12, 6, true, false)

  // Campana de Pedidos en el centro
  const bellX = counterX + counterWidth / 2
  ctx.fillStyle = '#d4af37' // Dorado
  ctx.beginPath()
  ctx.arc(bellX, counterY - 18, 9, Math.PI, Math.PI * 2)
  ctx.fill()
  ctx.fillRect(bellX - 11, counterY - 18, 22, 4)

  // Texto "ZONA DE PASE (KDS)"
  ctx.fillStyle = '#5c3a21'
  ctx.font = 'bold 10px sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText('PASE DE PLATOS & COMANDAS', bellX, counterY + 28)
}

// 4. MESAS Y COMENSALES
const drawDiningTables = () => {
  const tableList = props.tables.length ? props.tables : [
    { id: 1, name: 'Mesa 1', status: 'occupied', pos_x: 180, pos_y: 420, capacity: 4, shape: 'square' },
    { id: 2, name: 'Mesa 2', status: 'available', pos_x: 360, pos_y: 420, capacity: 4, shape: 'round' },
    { id: 3, name: 'Mesa 3', status: 'bill_requested', pos_x: 540, pos_y: 420, capacity: 4, shape: 'square' },
    { id: 4, name: 'Mesa 4', status: 'occupied', pos_x: 720, pos_y: 420, capacity: 6, shape: 'rectangle' },
    { id: 5, name: 'Mesa 5', status: 'available', pos_x: 260, pos_y: 600, capacity: 4, shape: 'round' },
    { id: 6, name: 'Mesa 6', status: 'available', pos_x: 480, pos_y: 600, capacity: 4, shape: 'square' },
  ]

  tableList.forEach((table) => {
    const tx = (table.pos_x || 200) + 40
    const ty = (table.pos_y || 400) + 40
    const isRound = table.shape === 'round'
    const isSelected = props.selectedTableId === table.id

    // Sombra de Mesa
    ctx.fillStyle = 'rgba(0, 0, 0, 0.28)'
    ctx.beginPath()
    if (isRound) {
      ctx.arc(tx + 4, ty + 8, 42, 0, Math.PI * 2)
    } else {
      ctx.roundRect(tx - 38, ty - 34, 84, 84, 12)
    }
    ctx.fill()

    // Sillas alrededor
    drawChairs(tx, ty, table.capacity || 4, isRound)

    // Si está ocupada, dibujar comensales en las sillas
    if (table.status === 'occupied' || table.status === 'bill_requested') {
      drawGuests(tx, ty, Math.min(table.capacity || 4, 4), table.status)
    }

    // Tablero de la Mesa
    let tableBg = '#ffffff'
    let tableBorder = '#cbd5e1'
    if (table.status === 'occupied') {
      tableBg = '#fee2e2'
      tableBorder = '#ef4444'
    } else if (table.status === 'bill_requested') {
      tableBg = '#fef3c7'
      tableBorder = '#f59e0b'
    } else if (table.status === 'available') {
      tableBg = '#ecfdf5'
      tableBorder = '#10b981'
    }

    if (isSelected) {
      tableBorder = '#3b82f6'
      // Anillo de pulso de selección
      ctx.strokeStyle = '#3b82f6'
      ctx.lineWidth = 3
      ctx.beginPath()
      ctx.arc(tx, ty, 48 + Math.sin(globalTick * 0.1) * 3, 0, Math.PI * 2)
      ctx.stroke()
    }

    ctx.fillStyle = tableBg
    ctx.strokeStyle = tableBorder
    ctx.lineWidth = 3

    ctx.beginPath()
    if (isRound) {
      ctx.arc(tx, ty, 40, 0, Math.PI * 2)
    } else {
      ctx.roundRect(tx - 40, ty - 40, 80, 80, 10)
    }
    ctx.fill()
    ctx.stroke()

    // Mantel decorativo estilo Overcooked
    ctx.fillStyle = tableBorder
    ctx.beginPath()
    if (isRound) {
      ctx.arc(tx, ty, 16, 0, Math.PI * 2)
    } else {
      ctx.roundRect(tx - 18, ty - 18, 36, 36, 6)
    }
    ctx.fill()

    // Platos encima de la mesa si está ocupada
    if (table.status === 'occupied' || table.status === 'bill_requested') {
      ctx.fillStyle = '#ffffff'
      ctx.beginPath()
      ctx.arc(tx - 14, ty - 8, 7, 0, Math.PI * 2)
      ctx.arc(tx + 14, ty + 8, 7, 0, Math.PI * 2)
      ctx.fill()
      ctx.fillStyle = '#e11d48'
      ctx.beginPath()
      ctx.arc(tx - 14, ty - 8, 3.5, 0, Math.PI * 2)
      ctx.fill()
    }

    // Nombre de la Mesa
    ctx.fillStyle = '#1e293b'
    ctx.font = 'bold 12px sans-serif'
    ctx.textAlign = 'center'
    ctx.fillText(table.name, tx, ty + 4)

    // Total USD de la comanda si existe
    if (table.current_order?.total_amount_usd) {
      ctx.fillStyle = '#059669'
      ctx.font = 'bold 10px sans-serif'
      ctx.fillText(`$${Number(table.current_order.total_amount_usd).toFixed(2)}`, tx, ty + 20)
    }

    // Globo de Estado Sobre la Mesa
    if (table.status === 'bill_requested') {
      drawStatusBubble(tx, ty - 55, '💰 ¡Cuenta!', '#f59e0b')
    } else if (table.status === 'occupied') {
      drawStatusBubble(tx, ty - 55, '🍽️ Comiendo', '#ef4444')
    }
  })
}

// Sillas alrededor de la mesa
const drawChairs = (tx, ty, capacity, isRound) => {
  const chairDistance = isRound ? 48 : 46
  const chairPositions = [
    { x: tx, y: ty - chairDistance }, // Arriba
    { x: tx, y: ty + chairDistance }, // Abajo
    { x: tx - chairDistance, y: ty }, // Izquierda
    { x: tx + chairDistance, y: ty }, // Derecha
  ]

  chairPositions.slice(0, Math.min(capacity, 4)).forEach((pos) => {
    ctx.fillStyle = '#78350f'
    drawRoundedRect(ctx, pos.x - 11, pos.y - 11, 22, 22, 5, true, false)
    ctx.fillStyle = '#d97706'
    drawRoundedRect(ctx, pos.x - 9, pos.y - 9, 18, 18, 4, true, false)
  })
}

// Comensales sentados
const drawGuests = (tx, ty, count) => {
  const offsets = [
    { x: tx, y: ty - 48 },
    { x: tx, y: ty + 48 },
    { x: tx - 48, y: ty },
    { x: tx + 48, y: ty },
  ]

  const hairColors = ['#451a03', '#1e293b', '#b45309', '#0284c7']

  for (let i = 0; i < count; i++) {
    const pos = offsets[i]
    // Cabeza del comensal estilo Chibi
    ctx.fillStyle = '#fde047' // Piel cálida
    ctx.beginPath()
    ctx.arc(pos.x, pos.y, 10, 0, Math.PI * 2)
    ctx.fill()

    // Cabello
    ctx.fillStyle = hairColors[i % hairColors.length]
    ctx.beginPath()
    ctx.arc(pos.x, pos.y - 3, 10, Math.PI, Math.PI * 2)
    ctx.fill()
  }
}

// 5. DIBUJO DE CHEFS (ESTILO OVERCOOKED)
const drawChef = (chef) => {
  const bobY = Math.sin(chef.bobOffset) * 2.5
  const cx = chef.x
  const cy = chef.y + bobY

  // Sombra suave en los pies
  ctx.fillStyle = 'rgba(0, 0, 0, 0.3)'
  ctx.beginPath()
  ctx.ellipse(cx, chef.y + 16, 14, 6, 0, 0, Math.PI * 2)
  ctx.fill()

  // Cuerpo y Delantal
  ctx.fillStyle = '#ffffff'
  drawRoundedRect(ctx, cx - 11, cy - 6, 22, 20, 6, true, false)

  // Delantal coloreado según la estación
  ctx.fillStyle = chef.color === 'error' ? '#ef4444' : chef.color === 'success' ? '#10b981' : '#3b82f6'
  ctx.fillRect(cx - 7, cy - 2, 14, 14)

  // Cabeza Chibi
  ctx.fillStyle = '#fde68a'
  ctx.beginPath()
  ctx.arc(cx, cy - 10, 11, 0, Math.PI * 2)
  ctx.fill()

  // Ojos tiernos
  ctx.fillStyle = '#1e293b'
  ctx.beginPath()
  ctx.arc(cx - 4, cy - 10, 2, 0, Math.PI * 2)
  ctx.arc(cx + 4, cy - 10, 2, 0, Math.PI * 2)
  ctx.fill()

  // Bigotito o expresión
  ctx.strokeStyle = '#78350f'
  ctx.lineWidth = 1.5
  ctx.beginPath()
  ctx.arc(cx, cy - 6, 3, 0, Math.PI)
  ctx.stroke()

  // Gorro Alto de Chef Esponjado
  ctx.fillStyle = '#ffffff'
  ctx.strokeStyle = '#e2e8f0'
  ctx.lineWidth = 1
  ctx.beginPath()
  ctx.arc(cx - 7, cy - 23, 7, 0, Math.PI * 2)
  ctx.arc(cx + 7, cy - 23, 7, 0, Math.PI * 2)
  ctx.arc(cx, cy - 27, 8, 0, Math.PI * 2)
  ctx.fill()
  ctx.stroke()
  ctx.fillRect(cx - 10, cy - 19, 20, 6)

  // Sartén o Cuchillo animado en la mano
  const panAngle = Math.sin(globalTick * 0.15) * 0.3
  ctx.save()
  ctx.translate(cx + 12, cy)
  ctx.rotate(panAngle)
  ctx.fillStyle = '#334155'
  ctx.fillRect(0, -2, 10, 4)
  ctx.beginPath()
  ctx.arc(12, 0, 7, 0, Math.PI * 2)
  ctx.fill()
  ctx.restore()

  // Barra Circular de Progreso de Cocción sobre la cabeza
  ctx.strokeStyle = '#e2e8f0'
  ctx.lineWidth = 3
  ctx.beginPath()
  ctx.arc(cx, cy - 38, 7, 0, Math.PI * 2)
  ctx.stroke()

  ctx.strokeStyle = '#10b981'
  ctx.beginPath()
  ctx.arc(cx, cy - 38, 7, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * chef.cookingProgress)
  ctx.stroke()

  // Nombre del Chef
  ctx.fillStyle = '#ffffff'
  ctx.font = 'bold 9px sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText(chef.name, cx, cy + 28)
}

// 6. DIBUJO DE MESEROS (ESTILO OVERCOOKED)
const drawWaiter = (waiter) => {
  const bobY = Math.sin(waiter.bobOffset) * 2.5
  const wx = waiter.x
  const wy = waiter.y + bobY

  // Sombra en los pies
  ctx.fillStyle = 'rgba(0, 0, 0, 0.3)'
  ctx.beginPath()
  ctx.ellipse(wx, waiter.y + 16, 14, 6, 0, 0, Math.PI * 2)
  ctx.fill()

  // Chaleco elegante y Camisa blanca
  ctx.fillStyle = '#ffffff'
  drawRoundedRect(ctx, wx - 11, wy - 6, 22, 20, 6, true, false)
  ctx.fillStyle = '#1e1b4b'
  ctx.fillRect(wx - 10, wy - 4, 6, 16)
  ctx.fillRect(wx + 4, wy - 4, 6, 16)

  // Pajarita roja
  ctx.fillStyle = '#dc2626'
  ctx.beginPath()
  ctx.arc(wx, wy - 4, 2.5, 0, Math.PI * 2)
  ctx.fill()

  // Cabeza Chibi
  ctx.fillStyle = '#fde68a'
  ctx.beginPath()
  ctx.arc(wx, wy - 10, 11, 0, Math.PI * 2)
  ctx.fill()

  // Cabello
  ctx.fillStyle = '#451a03'
  ctx.beginPath()
  ctx.arc(wx, wy - 13, 11, Math.PI, Math.PI * 2)
  ctx.fill()

  // Ojos
  ctx.fillStyle = '#1e293b'
  ctx.beginPath()
  ctx.arc(wx - 4, wy - 10, 2, 0, Math.PI * 2)
  ctx.arc(wx + 4, wy - 10, 2, 0, Math.PI * 2)
  ctx.fill()

  // Bandeja de Mozo si lleva pedido
  if (waiter.carryingDish) {
    ctx.fillStyle = '#cbd5e1'
    ctx.beginPath()
    ctx.ellipse(wx + 12, wy - 2, 10, 4, 0, 0, Math.PI * 2)
    ctx.fill()

    // Campana de plata brillante
    ctx.fillStyle = '#94a3b8'
    ctx.beginPath()
    ctx.arc(wx + 12, wy - 5, 5, Math.PI, Math.PI * 2)
    ctx.fill()
  }

  // Nombre del Mesero
  ctx.fillStyle = '#1e293b'
  ctx.font = 'bold 9px sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText(waiter.name, wx, wy + 26)
}

// 7. DIBUJO DE PARTÍCULAS
const drawParticles = () => {
  particles.forEach((p) => {
    ctx.save()
    ctx.globalAlpha = p.alpha
    if (p.type === 'flame') {
      ctx.fillStyle = '#ff7675'
    } else {
      ctx.fillStyle = '#ffffff'
    }
    ctx.beginPath()
    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2)
    ctx.fill()
    ctx.restore()
  })
}

// 8. BURBUJAS DE ESTADO FLOTANTES
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
  const padding = 10
  const bubbleW = textWidth + padding * 2
  const bubbleH = 20

  // Fondo de la burbuja
  ctx.fillStyle = '#ffffff'
  ctx.strokeStyle = color
  ctx.lineWidth = 2
  drawRoundedRect(ctx, x - bubbleW / 2, y - bubbleH / 2, bubbleW, bubbleH, 10, true, true)

  // Triángulo inferior
  ctx.fillStyle = '#ffffff'
  ctx.beginPath()
  ctx.moveTo(x - 4, y + bubbleH / 2)
  ctx.lineTo(x + 4, y + bubbleH / 2)
  ctx.lineTo(x, y + bubbleH / 2 + 5)
  ctx.fill()

  // Texto
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

// Detección de Clic en Mesas o Chefs
const handleClick = (e) => {
  if (!canvasRef.value) return
  const rect = canvasRef.value.getBoundingClientRect()
  const mouseScreenX = e.clientX - rect.left
  const mouseScreenY = e.clientY - rect.top

  // Transformar coordenadas de pantalla a coordenadas del mundo
  const worldX = (mouseScreenX - camera.value.x) / camera.value.zoom
  const worldY = (mouseScreenY - camera.value.y) / camera.value.zoom

  // 1. Chequear clic en mesas
  const tableList = props.tables.length ? props.tables : []
  for (const table of tableList) {
    const tx = (table.pos_x || 200) + 40
    const ty = (table.pos_y || 400) + 40
    const dist = Math.hypot(worldX - tx, worldY - ty)
    if (dist <= 45) {
      emit('select-table', table)
      return
    }
  }

  // 2. Chequear clic en chefs
  for (const chef of chefs) {
    const dist = Math.hypot(worldX - chef.x, worldY - chef.y)
    if (dist <= 30) {
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
  camera.value.zoom = Math.max(camera.value.zoom - 0.15, 0.6)
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
    canvasRef.value.height = 600
  }
}

watch(() => props.stations, () => {
  initChefs()
}, { deep: true })

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
  min-height: 600px;
  background-color: #12121a;
  box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.6);
}

.overcooked-hud-bar {
  position: absolute;
  top: 10px;
  left: 10px;
  right: 10px;
  z-index: 10;
  background: rgba(22, 22, 34, 0.85);
  backdrop-filter: blur(8px);
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.overcooked-bottom-guide {
  position: absolute;
  bottom: 8px;
  left: 10px;
  right: 10px;
  z-index: 10;
  background: rgba(22, 22, 34, 0.85);
  backdrop-filter: blur(8px);
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #fff;
}

.overcooked-canvas {
  width: 100%;
  height: 600px;
  display: block;
  cursor: grab;
}

.overcooked-canvas:active {
  cursor: grabbing;
}
</style>
