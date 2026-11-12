<template>
  <div class="overcooked-live-container position-relative overflow-hidden rounded-lg">
    <!-- Barra Flotante de Herramientas y Estado HUD -->
    <div class="overcooked-hud-bar d-flex align-center justify-space-between flex-wrap gap-2 pa-3">
      <div class="d-flex align-center gap-2 flex-wrap">
        <v-chip color="primary" variant="flat" size="small" prepend-icon="tabler-chef-hat">
          <strong>Salón en Vivo 2.5D</strong>
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
  x: 30,
  y: 20,
  zoom: 1.0,
  isDragging: false,
  dragStartX: 0,
  dragStartY: 0,
})

// Escala métrica unificada con el diseñador (1 metro = 60px)
const cellPixelSize = 60
const offsetX = 110
const offsetY = 110

const zoneWidthMeters = computed(() => Number(props.zone?.width_meters || 12))
const zoneHeightMeters = computed(() => Number(props.zone?.height_meters || 8))
const zonePixelWidth = computed(() => Math.round(zoneWidthMeters.value * cellPixelSize))
const zonePixelHeight = computed(() => Math.round(zoneHeightMeters.value * cellPixelSize))

const worldWidth = computed(() => zonePixelWidth.value + offsetX * 2)
const worldHeight = computed(() => zonePixelHeight.value + offsetY * 2)

// Agentes de Simulación
let chefs = []
let waiters = []
let particles = []
let floatingBubbles = []
let globalTick = 0

const activeChefsCount = computed(() => chefs.length)
const activeWaitersCount = computed(() => waiters.length)

// Precarga de Sprites 2.5D del Exterior (Estilo Overcooked 3D cartoon suave)
const exteriorSprites = {}
const loadExteriorSprites = () => {
  const keys = ['tree', 'awning', 'bench', 'street_lamp']
  keys.forEach((key) => {
    const img = new Image()
    img.src = `/images/restaurant-sprites/exterior/${key}.png`
    img.onload = () => {
      exteriorSprites[key] = img
    }
  })
}

// ==========================================
// INICIALIZACIÓN DE AGENTES (CONFINADOS AL INTERIOR REAL)
// ==========================================
const initChefs = () => {
  chefs = []
  const equipList = props.zone?.layout_config?.equipment || []
  const equipStations = equipList.filter(e => ['station', 'stove_pot', 'cutting_board'].includes(e.type))
  const kitchenArea = (props.zone?.layout_config?.areas || []).find(a => a.type === 'kitchen')

  if (equipStations.length > 0) {
    equipStations.forEach((eq, idx) => {
      const sx = offsetX + Number(eq.x || 0) * cellPixelSize + (Number(eq.w || 1) * cellPixelSize) / 2
      const sy = offsetY + Number(eq.y || 0) * cellPixelSize + (Number(eq.h || 1) * cellPixelSize) / 2 + 12
      chefs.push({
        id: eq.id || idx + 1,
        name: `Chef ${eq.name || 'Cocina'}`.substring(0, 14),
        stationName: eq.name || 'Estación',
        x: sx,
        y: sy,
        homeX: sx,
        homeY: sy,
        color: '#ff9f43',
        bobOffset: idx * 1.2,
        cookingProgress: (idx * 0.3) % 1.0,
        station: eq,
      })
    })
  } else if (kitchenArea) {
    // Si hay área de cocina, colocar al menos 1 chef dentro de ella
    const kw = Number(kitchenArea.w || 3) * cellPixelSize
    const kh = Number(kitchenArea.h || 2) * cellPixelSize
    const kx = offsetX + Number(kitchenArea.x || 0) * cellPixelSize
    const ky = offsetY + Number(kitchenArea.y || 0) * cellPixelSize
    const count = Math.max(1, Math.min(props.stations.length || 1, 3))

    for (let idx = 0; idx < count; idx++) {
      const st = props.stations[idx] || { id: idx + 1, name: 'Cocina', code: 'CHEF' }
      const sx = kx + kw * ((idx + 0.5) / count)
      const sy = ky + kh / 2 + 6
      chefs.push({
        id: st.id,
        name: `Chef ${st.code || st.name}`.substring(0, 14),
        stationName: st.name,
        x: sx,
        y: sy,
        homeX: sx,
        homeY: sy,
        color: st.color || '#ff9f43',
        bobOffset: idx * 1.5,
        cookingProgress: (idx * 0.25) % 1.0,
        station: st,
      })
    }
  }
}

const clampToInterior = (x, y) => {
  const minX = offsetX + 20
  const maxX = offsetX + zonePixelWidth.value - 20
  const minY = offsetY + 24
  const maxY = offsetY + zonePixelHeight.value - 20
  return {
    x: Math.max(minX, Math.min(maxX, x)),
    y: Math.max(minY, Math.min(maxY, y)),
  }
}

const initWaiters = () => {
  waiters = []
  if (!props.tables || !props.tables.length) return

  const waiterCount = Math.min(3, Math.max(1, Math.ceil(props.tables.length / 4)))
  for (let i = 0; i < waiterCount; i++) {
    const t = props.tables[i % props.tables.length]
    const pos = clampToInterior(
      offsetX + Number(t.pos_x || 0) + Number(t.width || 60) / 2 - 24,
      offsetY + Number(t.pos_y || 0) + Number(t.height || 60) + 18
    )
    waiters.push({
      id: i + 1,
      name: i === 0 ? 'Mesero Alex' : i === 1 ? 'Mesera Sofía' : 'Mesero Carlos',
      x: pos.x,
      y: pos.y,
      targetX: pos.x,
      targetY: pos.y,
      speed: 2.0,
      carryingDish: null,
      bobOffset: i * 1.5,
      taskCooldown: 80 + i * 50,
    })
  }
}

const spawnParticle = (x, y, type = 'steam') => {
  if (particles.length > 40) particles.shift()
  particles.push({
    x: x + (Math.random() - 0.5) * 14,
    y,
    vx: (Math.random() - 0.5) * 0.5,
    vy: -0.7 - Math.random() * 0.6,
    radius: 3 + Math.random() * 2.5,
    alpha: 0.8,
    type,
  })
}

const updateAgents = () => {
  globalTick++

  chefs.forEach((chef) => {
    chef.bobOffset += 0.08
    chef.cookingProgress += 0.003
    if (chef.cookingProgress > 1.0) {
      chef.cookingProgress = 0
      floatingBubbles.push({
        x: chef.x,
        y: chef.y - 44,
        text: '¡Plato Listo!',
        alpha: 1.0,
        vy: -0.5,
      })
    }
    if (globalTick % 9 === 0) {
      spawnParticle(chef.homeX, chef.homeY - 22, 'steam')
    }
  })

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
        waiter.taskCooldown = 140 + Math.floor(Math.random() * 90)
        const pool = occupiedTables.length && Math.random() > 0.35 ? occupiedTables : allTables
        if (pool.length) {
          const target = pool[Math.floor(Math.random() * pool.length)]
          const clamped = clampToInterior(
            offsetX + Number(target.pos_x || 0) + Number(target.width || 60) / 2,
            offsetY + Number(target.pos_y || 0) + Number(target.height || 60) + 18
          )
          waiter.targetX = clamped.x
          waiter.targetY = clamped.y
          waiter.carryingDish = occupiedTables.includes(target) ? 'Bandeja' : null
        }
      }
    }
  })

  for (let i = particles.length - 1; i >= 0; i--) {
    const p = particles[i]
    p.x += p.vx
    p.y += p.vy
    p.alpha -= 0.015
    p.radius *= 0.98
    if (p.alpha <= 0) particles.splice(i, 1)
  }

  for (let i = floatingBubbles.length - 1; i >= 0; i--) {
    const b = floatingBubbles[i]
    b.y += b.vy
    b.alpha -= 0.012
    if (b.alpha <= 0) floatingBubbles.splice(i, 1)
  }
}

// ==========================================
// RENDERIZADO PRINCIPAL 2.5D OVERCOOKED
// ==========================================
const render = () => {
  if (!ctx || !canvasRef.value) return
  const width = canvasRef.value.width
  const height = canvasRef.value.height

  ctx.clearRect(0, 0, width, height)
  ctx.save()
  ctx.translate(camera.value.x, camera.value.y)
  ctx.scale(camera.value.zoom, camera.value.zoom)

  // 1. Entorno Exterior (Calle, Acera, Paso de Cebra, Árboles)
  drawExteriorStreetAndSidewalk()

  // 2. Suelo del Restaurante (Damero / Madera) + Muros 3D Perimetrales
  drawFloorAndWalls3D()

  // 3. Áreas Arquitectónicas (Cocina Damero, Depósito, Terraza, Paredes)
  drawArchitecturalAreas()

  // 4. Equipamiento Modular 2.5D (Mesones, Ollas, Corte, Fregadero, Mostradores)
  drawEquipment25D()

  // 5. Mesas con Mantel Vichy y Sillas 2.5D
  drawDiningTables25D()

  // 6. Accesorios / Props 2.5D (Plantas, Toldo, Bancos, Atril, Lámparas)
  drawProps25D()

  // 7. Agentes Chefs y Meseros
  chefs.forEach(drawChef)
  waiters.forEach(drawWaiter)

  // 8. Partículas y Burbujas
  drawParticles()
  drawFloatingBubbles()

  ctx.restore()
  updateAgents()
  animationFrameId = requestAnimationFrame(render)
}

// ==========================================
// 1. ENTORNO EXTERIOR VIVO (ACERA, CALLE, ÁRBOLES 2.5D)
// ==========================================
const drawExteriorStreetAndSidewalk = () => {
  const exterior = props.zone?.layout_config?.exterior || { enabled: true }
  const zW = zonePixelWidth.value
  const zH = zonePixelHeight.value

  // Fondo base
  ctx.fillStyle = exterior.enabled === false ? '#0f111a' : '#2d323b'
  ctx.fillRect(-300, -300, worldWidth.value + 600, worldHeight.value + 600)

  if (exterior.enabled === false) return

  // Calle superior (Asfalto con línea punteada y paso de cebra multicolor)
  ctx.fillStyle = '#3c424d'
  ctx.fillRect(-200, -150, worldWidth.value + 400, offsetY + 60)

  // Línea blanca discontinua de la calle
  ctx.strokeStyle = 'rgba(255, 255, 255, 0.55)'
  ctx.lineWidth = 3
  ctx.setLineDash([18, 16])
  ctx.beginPath()
  ctx.moveTo(-150, 22)
  ctx.lineTo(worldWidth.value + 150, 22)
  ctx.stroke()
  ctx.setLineDash([])

  // Paso de cebra arcoíris estilo Overcooked en la esquina superior izquierda
  const zebraColors = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#a855f7']
  zebraColors.forEach((col, i) => {
    ctx.fillStyle = col
    ctx.fillRect(offsetX - 75, 8 + i * 10, 42, 6)
  })

  // Acera peatonal alrededor del restaurante
  const sidewalkPad = 48
  ctx.fillStyle = '#b4b8bf'
  drawRoundedRect(
    ctx,
    offsetX - sidewalkPad,
    offsetY - sidewalkPad,
    zW + sidewalkPad * 2,
    zH + sidewalkPad * 2,
    12,
    true,
    false
  )

  // Losa / Bordillo de acera
  ctx.strokeStyle = '#8b919c'
  ctx.lineWidth = 3
  drawRoundedRect(
    ctx,
    offsetX - sidewalkPad,
    offsetY - sidewalkPad,
    zW + sidewalkPad * 2,
    zH + sidewalkPad * 2,
    12,
    false,
    true
  )

  // Árboles y Farolas decorativas automáticas en la acera superior
  const treeCount = Math.max(2, Math.floor(zW / 240))
  for (let i = 0; i < treeCount; i++) {
    const tx = offsetX + 60 + i * (zW / treeCount)
    const ty = offsetY - 28
    drawTree25D(tx, ty, 24)
    if (exteriorSprites.street_lamp && i < treeCount - 1) {
      ctx.drawImage(exteriorSprites.street_lamp, tx + 95, ty - 24, 34, 48)
    }
  }
}

const drawTree25D = (x, y, r = 24) => {
  // Sombra
  ctx.fillStyle = 'rgba(0, 0, 0, 0.25)'
  ctx.beginPath()
  ctx.ellipse(x + 4, y + 10, r, r * 0.65, 0, 0, Math.PI * 2)
  ctx.fill()

  if (exteriorSprites.tree) {
    ctx.drawImage(exteriorSprites.tree, x - r * 1.15, y - r * 1.25, r * 2.3, r * 2.3)
    return
  }

  // Fallback vectorial
  ctx.fillStyle = '#2e7d32'
  ctx.beginPath()
  ctx.arc(x, y, r, 0, Math.PI * 2)
  ctx.fill()
  ctx.fillStyle = '#4caf50'
  ctx.beginPath()
  ctx.arc(x - 3, y - 4, r * 0.78, 0, Math.PI * 2)
  ctx.fill()
}

// ==========================================
// 2. SUELO Y MUROS PERIMETRALES 3D
// ==========================================
const drawFloorAndWalls3D = () => {
  const zW = zonePixelWidth.value
  const zH = zonePixelHeight.value
  const bgStyle = props.zone?.background_style || 'wooden'

  // Sombra proyectada de toda la estructura
  ctx.fillStyle = 'rgba(0, 0, 0, 0.45)'
  ctx.fillRect(offsetX + 10, offsetY + 12, zW, zH)

  // Suelo según estilo elegido
  if (bgStyle === 'checker_dark') {
    drawCheckerFloor(offsetX, offsetY, zW, zH, '#2c303a', '#353a46', 30)
  } else if (bgStyle === 'checker_warm') {
    drawCheckerFloor(offsetX, offsetY, zW, zH, '#f3ead8', '#d5decb', 30)
  } else if (bgStyle === 'grass') {
    ctx.fillStyle = '#1b4332'
    ctx.fillRect(offsetX, offsetY, zW, zH)
  } else if (bgStyle === 'concrete') {
    ctx.fillStyle = '#2d3340'
    ctx.fillRect(offsetX, offsetY, zW, zH)
  } else if (bgStyle === 'grid') {
    ctx.fillStyle = '#10141f'
    ctx.fillRect(offsetX, offsetY, zW, zH)
  } else {
    // Madera cálida con tablones
    ctx.fillStyle = '#4a2c11'
    ctx.fillRect(offsetX, offsetY, zW, zH)
    ctx.strokeStyle = 'rgba(0, 0, 0, 0.25)'
    ctx.lineWidth = 1.5
    for (let y = offsetY; y < offsetY + zH; y += 20) {
      ctx.beginPath()
      ctx.moveTo(offsetX, y)
      ctx.lineTo(offsetX + zW, y)
      ctx.stroke()
    }
  }

  // Sombra interior (Ambient Occlusion) bajo las paredes
  const gradTop = ctx.createLinearGradient(0, offsetY, 0, offsetY + 22)
  gradTop.addColorStop(0, 'rgba(0, 0, 0, 0.45)')
  gradTop.addColorStop(1, 'rgba(0, 0, 0, 0)')
  ctx.fillStyle = gradTop
  ctx.fillRect(offsetX, offsetY, zW, 22)

  // Muros 3D Perimetrales (Terracota / Ladrillo estilo Overcooked)
  const wallT = 12
  ctx.fillStyle = '#8b3a22' // Base muro
  // Norte (con altura 3D)
  ctx.fillRect(offsetX - wallT, offsetY - wallT - 10, zW + wallT * 2, wallT + 10)
  // Sur
  ctx.fillRect(offsetX - wallT, offsetY + zH, zW + wallT * 2, wallT)
  // Oeste
  ctx.fillRect(offsetX - wallT, offsetY - wallT, wallT, zH + wallT * 2)
  // Este
  ctx.fillRect(offsetX + zW, offsetY - wallT, wallT, zH + wallT * 2)

  // Tope superior claro de los muros
  ctx.strokeStyle = '#c46243'
  ctx.lineWidth = 3
  ctx.strokeRect(offsetX - wallT / 2, offsetY - wallT / 2, zW + wallT, zH + wallT)

  // Ventanas decorativas en pared Norte
  const winCount = Math.max(2, Math.floor(zW / 160))
  for (let i = 0; i < winCount; i++) {
    const wx = offsetX + 45 + i * (zW / winCount)
    ctx.fillStyle = '#fde68a'
    ctx.fillRect(wx, offsetY - 14, 48, 8)
    ctx.strokeStyle = '#5c2818'
    ctx.lineWidth = 2
    ctx.strokeRect(wx, offsetY - 14, 48, 8)
  }
}

const drawCheckerFloor = (x, y, w, h, col1, col2, size = 30) => {
  ctx.save()
  ctx.beginPath()
  ctx.rect(x, y, w, h)
  ctx.clip()

  const cols = Math.ceil(w / size)
  const rows = Math.ceil(h / size)
  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < cols; c++) {
      ctx.fillStyle = (r + c) % 2 === 0 ? col1 : col2
      ctx.fillRect(x + c * size, y + r * size, size, size)
    }
  }
  ctx.restore()
}

// ==========================================
// 3. ÁREAS ARQUITECTÓNICAS (DAMERO COCINA + MUROS)
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
      // Damero clásico Overcooked en Cocina
      drawCheckerFloor(ax, ay, aw, ah, '#f3ead8', '#b8c4bb', 20)
      ctx.strokeStyle = '#8b3a22'
      ctx.lineWidth = 3
      ctx.strokeRect(ax, ay, aw, ah)
    } else if (area.type === 'storage') {
      drawCheckerFloor(ax, ay, aw, ah, '#283747', '#212f3d', 24)
      ctx.strokeStyle = '#7f8c8d'
      ctx.lineWidth = 2
      ctx.strokeRect(ax, ay, aw, ah)
    } else if (area.type === 'terrace') {
      ctx.fillStyle = 'rgba(46, 204, 113, 0.35)'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = '#2ecc71'
      ctx.lineWidth = 2
      ctx.strokeRect(ax, ay, aw, ah)
    } else if (area.type === 'wall') {
      // Muro divisor 3D
      ctx.fillStyle = '#5c2818'
      ctx.fillRect(ax, ay + 6, aw, ah)
      ctx.fillStyle = '#a04529'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = '#d47a5b'
      ctx.lineWidth = 1.5
      ctx.strokeRect(ax, ay, aw, ah)
    } else if (area.type === 'entrance') {
      ctx.fillStyle = '#a8292b'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = '#ffd54f'
      ctx.lineWidth = 2
      ctx.strokeRect(ax + 3, ay + 3, aw - 6, ah - 6)
    } else {
      ctx.fillStyle = area.color ? `${area.color}44` : 'rgba(0, 207, 232, 0.22)'
      ctx.fillRect(ax, ay, aw, ah)
      ctx.strokeStyle = area.color || '#00cfe8'
      ctx.lineWidth = 2
      ctx.strokeRect(ax, ay, aw, ah)
    }

    // Etiqueta discreta del área
    ctx.fillStyle = 'rgba(15, 23, 42, 0.85)'
    drawRoundedRect(ctx, ax + 4, ay + 4, Math.min(aw - 8, 95), 16, 4, true, false)
    ctx.fillStyle = '#ffffff'
    ctx.font = 'bold 9px sans-serif'
    ctx.textAlign = 'left'
    ctx.fillText((area.name || area.type).substring(0, 14), ax + 8, ay + 15)
    ctx.restore()
  })
}

// ==========================================
// 4. EQUIPAMIENTO MODULAR 2.5D (MESONES, OLLAS, CORTE, MOSTRADORES)
// ==========================================
const drawCounterBlock3D = (x, y, w, h, topColor, frontColor, borderColor) => {
  const frontH = Math.min(12, h * 0.25)
  // Sombra
  ctx.fillStyle = 'rgba(0, 0, 0, 0.32)'
  drawRoundedRect(ctx, x + 3, y + 5, w, h, 4, true, false)
  // Cara frontal 3D
  ctx.fillStyle = frontColor
  drawRoundedRect(ctx, x, y + frontH, w, h - frontH + 4, 4, true, false)
  // Encimera superior
  ctx.fillStyle = topColor
  drawRoundedRect(ctx, x, y, w, h - frontH, 4, true, false)
  ctx.strokeStyle = borderColor
  ctx.lineWidth = 1.5
  drawRoundedRect(ctx, x, y, w, h - frontH, 4, false, true)
}

const drawEquipment25D = () => {
  const equipList = props.zone?.layout_config?.equipment || []

  equipList.forEach((eq) => {
    const ex = offsetX + Number(eq.x || 0) * cellPixelSize
    const ey = offsetY + Number(eq.y || 0) * cellPixelSize
    const ew = Number(eq.w || 1) * cellPixelSize
    const eh = Number(eq.h || 1) * cellPixelSize
    const cx = ex + ew / 2
    const cy = ey + eh / 2 - 4

    ctx.save()

    if (eq.type === 'cooling') {
      // Nevera 3D Acero Inoxidable
      drawCounterBlock3D(ex, ey, ew, eh, '#0097a7', '#006064', '#4dd0e1')
      ctx.fillStyle = '#e0f7fa'
      ctx.font = 'bold 10px sans-serif'
      ctx.textAlign = 'center'
      ctx.fillText(`❄ ${eq.name || 'FRÍO'}`, cx, cy + 4)
    } else if (eq.type === 'counter') {
      // Mostrador Recepción / Caja 3D
      drawCounterBlock3D(ex, ey, ew, eh, '#d49a59', '#7b4c24', '#f3c68f')
      // Monitor pequeño encima
      ctx.fillStyle = '#1e293b'
      ctx.fillRect(cx - 8, cy - 8, 16, 10)
      ctx.fillStyle = '#38bdf8'
      ctx.fillRect(cx - 6, cy - 6, 12, 6)

      ctx.fillStyle = '#ffffff'
      ctx.font = 'bold 10px sans-serif'
      ctx.textAlign = 'center'
      ctx.fillText((eq.name || 'Mostrador').substring(0, 14), cx, cy + 12)
    } else if (eq.type === 'pass_counter') {
      // Cinta / Pase de Platos 2.5D
      drawCounterBlock3D(ex, ey, ew, eh, '#64748b', '#334155', '#cbd5e1')
      // Platos blancos listos
      ctx.fillStyle = '#ffffff'
      ctx.beginPath()
      ctx.arc(cx - 12, cy, 7, 0, Math.PI * 2)
      ctx.arc(cx + 12, cy, 7, 0, Math.PI * 2)
      ctx.fill()
    } else if (eq.type === 'stove_pot' || eq.type === 'station') {
      // Mesón con Hornilla y Olla 2.5D
      drawCounterBlock3D(ex, ey, ew, eh, '#e2b073', '#9a6332', '#f7d6a8')
      // Quemador negro
      ctx.fillStyle = '#1e293b'
      ctx.beginPath()
      ctx.arc(cx, cy, 12, 0, Math.PI * 2)
      ctx.fill()
      // Olla metálica
      ctx.fillStyle = '#94a3b8'
      ctx.beginPath()
      ctx.arc(cx, cy - 2, 9, 0, Math.PI * 2)
      ctx.fill()
      ctx.fillStyle = '#ea580c'
      ctx.beginPath()
      ctx.arc(cx, cy - 2, 6, 0, Math.PI * 2)
      ctx.fill()
    } else if (eq.type === 'cutting_board') {
      // Mesón con Tabla de Corte
      drawCounterBlock3D(ex, ey, ew, eh, '#e2b073', '#9a6332', '#f7d6a8')
      ctx.fillStyle = '#ffffff'
      drawRoundedRect(ctx, cx - 12, cy - 8, 24, 16, 2, true, false)
      // Cuchillo
      ctx.fillStyle = '#cbd5e1'
      ctx.fillRect(cx - 6, cy - 2, 12, 3)
    } else if (eq.type === 'sink') {
      // Fregadero 3D
      drawCounterBlock3D(ex, ey, ew, eh, '#cbd5e1', '#64748b', '#f1f5f9')
      ctx.fillStyle = '#38bdf8'
      drawRoundedRect(ctx, cx - 12, cy - 8, 24, 16, 3, true, false)
    } else if (eq.type === 'ingredient_box') {
      // Caja de Ingredientes (Tomates)
      drawCounterBlock3D(ex, ey, ew, eh, '#b45309', '#78350f', '#fbbf24')
      ctx.fillStyle = '#ef4444'
      ctx.beginPath()
      ctx.arc(cx - 5, cy - 2, 4, 0, Math.PI * 2)
      ctx.arc(cx + 5, cy - 2, 4, 0, Math.PI * 2)
      ctx.arc(cx, cy + 4, 4, 0, Math.PI * 2)
      ctx.fill()
    } else if (eq.type === 'plant') {
      ctx.fillStyle = 'rgba(0, 0, 0, 0.25)'
      ctx.beginPath()
      ctx.ellipse(cx + 2, cy + 6, 12, 8, 0, 0, Math.PI * 2)
      ctx.fill()
      ctx.fillStyle = '#9a3412'
      ctx.beginPath()
      ctx.arc(cx, cy + 2, 10, 0, Math.PI * 2)
      ctx.fill()
      ctx.fillStyle = '#16a34a'
      ctx.beginPath()
      ctx.arc(cx, cy - 2, 12, 0, Math.PI * 2)
      ctx.fill()
    } else if (eq.type === 'awning') {
      if (exteriorSprites.awning) {
        ctx.drawImage(exteriorSprites.awning, ex, ey, ew, eh)
      } else {
        const stripes = Math.max(4, Math.floor(ew / 12))
        const sw = ew / stripes
        for (let i = 0; i < stripes; i++) {
          ctx.fillStyle = i % 2 === 0 ? '#dc2626' : '#ffffff'
          ctx.fillRect(ex + i * sw, ey, sw + 0.5, eh)
        }
        ctx.strokeStyle = '#7f1d1d'
        ctx.lineWidth = 2
        ctx.strokeRect(ex, ey, ew, eh)
      }
    } else if (eq.type === 'bench') {
      if (exteriorSprites.bench) {
        ctx.drawImage(exteriorSprites.bench, ex, ey, ew, eh)
      } else {
        drawCounterBlock3D(ex, ey, ew, eh, '#b45309', '#78350f', '#fbbf24')
      }
    } else if (eq.type === 'barrel') {
      ctx.fillStyle = '#78350f'
      ctx.beginPath()
      ctx.arc(cx, cy, 14, 0, Math.PI * 2)
      ctx.fill()
      ctx.strokeStyle = '#94a3b8'
      ctx.lineWidth = 2
      ctx.stroke()
    } else {
      // Mesón base
      drawCounterBlock3D(ex, ey, ew, eh, '#e2b073', '#9a6332', '#f7d6a8')
    }

    ctx.restore()
  })
}

// ==========================================
// 5. MESAS CON MANTEL VICHY Y SILLAS 2.5D
// ==========================================
const drawDiningTables25D = () => {
  props.tables.forEach((table) => {
    const tx = offsetX + Number(table.pos_x || 0)
    const ty = offsetY + Number(table.pos_y || 0)
    const tw = Number(table.width || 60)
    const th = Number(table.height || 60)
    const isRound = table.shape === 'round'
    const isSelected = props.selectedTableId === table.id

    ctx.save()

    // 1. Sombra
    ctx.fillStyle = 'rgba(0, 0, 0, 0.35)'
    ctx.beginPath()
    if (isRound) {
      ctx.arc(tx + tw / 2 + 4, ty + th / 2 + 6, tw / 2 + 2, 0, Math.PI * 2)
    } else {
      ctx.roundRect(tx + 4, ty + 6, tw, th, 8)
    }
    ctx.fill()

    // 2. Sillas 2.5D acolchadas
    drawChairs25D(table, tx, ty, tw, th)

    // 3. Comensales si está ocupada
    if (table.status === 'occupied' || table.status === 'bill_requested') {
      drawGuests25D(table, tx, ty, tw, th)
    }

    // 4. Cuerpo 3D de la Mesa / Barra
    const frontH = 8
    const isBar = table.shape === 'bar'
    const clothStyle = table.cloth_style || (isBar ? 'bar_dark' : 'vichy_red')

    // Faldón inferior 3D
    ctx.fillStyle = isBar ? '#1e293b' : '#5d4037'
    ctx.beginPath()
    if (isRound) {
      ctx.arc(tx + tw / 2, ty + th / 2 + frontH, tw / 2, 0, Math.PI * 2)
    } else {
      ctx.roundRect(tx, ty + frontH, tw, th, 8)
    }
    ctx.fill()

    // Superficie superior con Mantel Vichy o Madera
    if (clothStyle === 'vichy_red' && !isBar) {
      ctx.save()
      ctx.beginPath()
      if (isRound) ctx.arc(tx + tw / 2, ty + th / 2, tw / 2, 0, Math.PI * 2)
      else ctx.roundRect(tx, ty, tw, th, 8)
      ctx.clip()
      drawCheckerFloor(tx, ty, tw, th, '#ffffff', '#ef4444', 10)
      ctx.restore()
    } else if (clothStyle === 'vichy_blue' && !isBar) {
      ctx.save()
      ctx.beginPath()
      if (isRound) ctx.arc(tx + tw / 2, ty + th / 2, tw / 2, 0, Math.PI * 2)
      else ctx.roundRect(tx, ty, tw, th, 8)
      ctx.clip()
      drawCheckerFloor(tx, ty, tw, th, '#ffffff', '#3b82f6', 10)
      ctx.restore()
    } else {
      ctx.fillStyle = isBar ? '#334155' : '#8d6e63'
      ctx.beginPath()
      if (isRound) ctx.arc(tx + tw / 2, ty + th / 2, tw / 2, 0, Math.PI * 2)
      else ctx.roundRect(tx, ty, tw, th, 8)
      ctx.fill()
    }

    // Borde de estado operativo
    let statusColor = '#22c55e'
    if (table.status === 'occupied') statusColor = '#ef4444'
    else if (table.status === 'bill_requested') statusColor = '#f59e0b'
    else if (table.status === 'reserved') statusColor = '#38bdf8'

    ctx.strokeStyle = isSelected ? '#00cfe8' : statusColor
    ctx.lineWidth = isSelected ? 4 : 2.5
    ctx.beginPath()
    if (isRound) ctx.arc(tx + tw / 2, ty + th / 2, tw / 2, 0, Math.PI * 2)
    else ctx.roundRect(tx, ty, tw, th, 8)
    ctx.stroke()

    // Platos si está ocupada
    if (table.status === 'occupied' || table.status === 'bill_requested') {
      ctx.fillStyle = '#ffffff'
      ctx.beginPath()
      ctx.arc(tx + tw / 2 - 12, ty + th / 2, 6, 0, Math.PI * 2)
      ctx.arc(tx + tw / 2 + 12, ty + th / 2, 6, 0, Math.PI * 2)
      ctx.fill()
    }

    // Píldora oscura con el nombre de la mesa para máxima legibilidad sobre el mantel
    const label = table.name || 'Mesa'
    ctx.font = 'bold 11px sans-serif'
    const lw = Math.min(tw - 6, ctx.measureText(label).width + 12)
    ctx.fillStyle = 'rgba(15, 23, 42, 0.85)'
    drawRoundedRect(ctx, tx + tw / 2 - lw / 2, ty + th / 2 - 10, lw, 20, 6, true, false)

    ctx.fillStyle = '#ffffff'
    ctx.textAlign = 'center'
    ctx.fillText(label, tx + tw / 2, ty + th / 2 + 4)

    if (table.status === 'bill_requested') {
      drawStatusBubble(tx + tw / 2, ty - 16, '💰 ¡Cuenta!', '#ea580c')
    } else if (table.status === 'occupied') {
      drawStatusBubble(tx + tw / 2, ty - 16, '🍽️ Comiendo', '#dc2626')
    }

    ctx.restore()
  })
}

// ==========================================
// 6. ACCESORIOS / PROPS 2.5D
// ==========================================
const drawProps25D = () => {
  const propList = props.zone?.layout_config?.props || []
  propList.forEach((p) => {
    const px = offsetX + Number(p.x || 0) * cellPixelSize
    const py = offsetY + Number(p.y || 0) * cellPixelSize
    const pw = Number(p.w || 0.8) * cellPixelSize
    const ph = Number(p.h || 0.8) * cellPixelSize
    const cx = px + pw / 2
    const cy = py + ph / 2

    ctx.save()
    if (p.type === 'plant') {
      // Maceta terracota + hojas verdes
      ctx.fillStyle = 'rgba(0, 0, 0, 0.25)'
      ctx.beginPath()
      ctx.ellipse(cx + 2, cy + 6, 12, 8, 0, 0, Math.PI * 2)
      ctx.fill()
      ctx.fillStyle = '#9a3412'
      ctx.beginPath()
      ctx.arc(cx, cy + 2, 10, 0, Math.PI * 2)
      ctx.fill()
      ctx.fillStyle = '#16a34a'
      ctx.beginPath()
      ctx.arc(cx, cy - 2, 12, 0, Math.PI * 2)
      ctx.fill()
      ctx.fillStyle = '#4ade80'
      ctx.beginPath()
      ctx.arc(cx - 3, cy - 4, 6, 0, Math.PI * 2)
      ctx.fill()
    } else if (p.type === 'awning') {
      // Toldo a rayas rojas y blancas
      const stripes = Math.max(4, Math.floor(pw / 12))
      const sw = pw / stripes
      for (let i = 0; i < stripes; i++) {
        ctx.fillStyle = i % 2 === 0 ? '#dc2626' : '#ffffff'
        ctx.fillRect(px + i * sw, py, sw + 0.5, ph)
      }
      ctx.strokeStyle = '#7f1d1d'
      ctx.lineWidth = 2
      ctx.strokeRect(px, py, pw, ph)
    } else if (p.type === 'tree') {
      drawTree25D(cx, cy, Math.min(pw, ph) / 2)
    } else if (p.type === 'bench') {
      drawCounterBlock3D(px, py, pw, ph, '#b45309', '#78350f', '#fbbf24')
    } else if (p.type === 'barrel') {
      ctx.fillStyle = '#78350f'
      ctx.beginPath()
      ctx.arc(cx, cy, 14, 0, Math.PI * 2)
      ctx.fill()
      ctx.strokeStyle = '#94a3b8'
      ctx.lineWidth = 2
      ctx.stroke()
    } else {
      drawCounterBlock3D(px, py, pw, ph, '#64748b', '#334155', '#cbd5e1')
    }
    ctx.restore()
  })
}

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
    if (side === 'top' && py <= threshold) return true
    if (side === 'left' && px <= threshold) return true
    if (side === 'right' && (px + tw) >= (zonePixelWidth.value - threshold)) return true
    if (side === 'bottom' && (py + th) >= (zonePixelHeight.value - threshold)) return true
    return false
  }

  const blocked = {
    top: isBlocked('top'),
    bottom: isBlocked('bottom'),
    left: isBlocked('left'),
    right: isBlocked('right'),
  }

  if (shape === 'round') {
    const rx = tw / 2 + 12
    const ry = th / 2 + 12
    for (let i = 0; i < cap; i++) {
      const angle = (2 * Math.PI * i) / cap - Math.PI / 2
      seats.push({ x: tx + tw / 2 + rx * Math.cos(angle), y: ty + th / 2 + ry * Math.sin(angle), isStool: false })
    }
    return seats
  }

  if (shape === 'bar') {
    let clientSide = 'bottom'
    if (blocked.bottom && !blocked.top) clientSide = 'top'
    else if (blocked.top && !blocked.bottom) clientSide = 'bottom'
    if (!blocked[clientSide]) {
      for (let i = 0; i < cap; i++) {
        const offset = tw * ((i + 0.5) / cap)
        const sy = clientSide === 'bottom' ? ty + th + 12 : ty - 12
        seats.push({ x: tx + offset, y: sy, isStool: true })
      }
    }
    return seats
  }

  let topCount = 0, bottomCount = 0, leftCount = 0, rightCount = 0
  if (cap === 1) bottomCount = 1
  else if (cap === 2) {
    if (tw >= th) { topCount = 1; bottomCount = 1 } else { leftCount = 1; rightCount = 1 }
  } else if (cap === 3) { topCount = 1; bottomCount = 2 }
  else if (cap === 4) { topCount = 1; bottomCount = 1; leftCount = 1; rightCount = 1 }
  else {
    if (tw >= th) {
      leftCount = 1; rightCount = 1
      topCount = Math.ceil((cap - 2) / 2)
      bottomCount = Math.floor((cap - 2) / 2)
    } else {
      topCount = 1; bottomCount = 1
      leftCount = Math.ceil((cap - 2) / 2)
      rightCount = Math.floor((cap - 2) / 2)
    }
  }

  if (blocked.top) topCount = 0
  if (blocked.bottom) bottomCount = 0
  if (blocked.left) leftCount = 0
  if (blocked.right) rightCount = 0

  for (let i = 0; i < topCount; i++) seats.push({ x: tx + tw * ((i + 0.5) / topCount), y: ty - 11, isStool: false })
  for (let i = 0; i < bottomCount; i++) seats.push({ x: tx + tw * ((i + 0.5) / bottomCount), y: ty + th + 11, isStool: false })
  for (let i = 0; i < leftCount; i++) seats.push({ x: tx - 11, y: ty + th * ((i + 0.5) / leftCount), isStool: false })
  for (let i = 0; i < rightCount; i++) seats.push({ x: tx + tw + 11, y: ty + th * ((i + 0.5) / rightCount), isStool: false })

  return seats
}

const drawChairs25D = (table, tx, ty, tw, th) => {
  const seats = getTableSeatsPositions(table, tx, ty, tw, th)
  seats.forEach((seat) => {
    // Sombra
    ctx.fillStyle = 'rgba(0, 0, 0, 0.25)'
    ctx.beginPath()
    ctx.arc(seat.x + 2, seat.y + 3, 8, 0, Math.PI * 2)
    ctx.fill()

    // Base madera + cojín naranja Overcooked
    ctx.fillStyle = seat.isStool ? '#fbbf24' : '#ea580c'
    ctx.strokeStyle = '#7c2d12'
    ctx.lineWidth = 2
    ctx.beginPath()
    ctx.arc(seat.x, seat.y, 7.5, 0, Math.PI * 2)
    ctx.fill()
    ctx.stroke()

    // Brillo del cojín
    ctx.fillStyle = '#fdba74'
    ctx.beginPath()
    ctx.arc(seat.x - 1.5, seat.y - 1.5, 3, 0, Math.PI * 2)
    ctx.fill()
  })
}

const drawGuests25D = (table, tx, ty, tw, th) => {
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

const drawChef = (chef) => {
  const bobY = Math.sin(chef.bobOffset) * 2.5
  const cx = chef.x
  const cy = chef.y + bobY

  ctx.fillStyle = 'rgba(0, 0, 0, 0.3)'
  ctx.beginPath()
  ctx.ellipse(cx, chef.y + 14, 12, 5, 0, 0, Math.PI * 2)
  ctx.fill()

  ctx.fillStyle = '#ffffff'
  drawRoundedRect(ctx, cx - 10, cy - 6, 20, 18, 5, true, false)
  ctx.fillStyle = '#ff9f43'
  ctx.fillRect(cx - 6, cy - 2, 12, 12)

  ctx.fillStyle = '#fde68a'
  ctx.beginPath()
  ctx.arc(cx, cy - 9, 10, 0, Math.PI * 2)
  ctx.fill()

  ctx.fillStyle = '#1e293b'
  ctx.beginPath()
  ctx.arc(cx - 3.5, cy - 9, 1.8, 0, Math.PI * 2)
  ctx.arc(cx + 3.5, cy - 9, 1.8, 0, Math.PI * 2)
  ctx.fill()

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

  ctx.fillStyle = '#ffffff'
  ctx.font = 'bold 9px sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText(chef.name, cx, cy + 24)
}

const drawWaiter = (waiter) => {
  const bobY = Math.sin(waiter.bobOffset) * 2.5
  const wx = waiter.x
  const wy = waiter.y + bobY

  ctx.fillStyle = 'rgba(0, 0, 0, 0.3)'
  ctx.beginPath()
  ctx.ellipse(wx, waiter.y + 14, 12, 5, 0, 0, Math.PI * 2)
  ctx.fill()

  ctx.fillStyle = '#ffffff'
  drawRoundedRect(ctx, wx - 10, wy - 6, 20, 18, 5, true, false)
  ctx.fillStyle = '#1e1b4b'
  ctx.fillRect(wx - 9, wy - 4, 5, 14)
  ctx.fillRect(wx + 4, wy - 4, 5, 14)

  ctx.fillStyle = '#dc2626'
  ctx.beginPath()
  ctx.arc(wx, wy - 4, 2, 0, Math.PI * 2)
  ctx.fill()

  ctx.fillStyle = '#fde68a'
  ctx.beginPath()
  ctx.arc(wx, wy - 9, 10, 0, Math.PI * 2)
  ctx.fill()

  ctx.fillStyle = '#451a03'
  ctx.beginPath()
  ctx.arc(wx, wy - 12, 10, Math.PI, Math.PI * 2)
  ctx.fill()

  ctx.fillStyle = '#1e293b'
  ctx.beginPath()
  ctx.arc(wx - 3.5, wy - 9, 1.8, 0, Math.PI * 2)
  ctx.arc(wx + 3.5, wy - 9, 1.8, 0, Math.PI * 2)
  ctx.fill()

  if (waiter.carryingDish) {
    ctx.fillStyle = '#cbd5e1'
    ctx.beginPath()
    ctx.ellipse(wx + 10, wy - 2, 8, 3.5, 0, 0, Math.PI * 2)
    ctx.fill()
  }

  ctx.fillStyle = '#ffffff'
  ctx.font = 'bold 9px sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText(waiter.name, wx, wy + 24)
}

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
  const bubbleW = textWidth + 16
  const bubbleH = 18

  ctx.fillStyle = '#ffffff'
  ctx.strokeStyle = color
  ctx.lineWidth = 1.8
  drawRoundedRect(ctx, x - bubbleW / 2, y - bubbleH / 2, bubbleW, bubbleH, 8, true, true)

  ctx.fillStyle = '#0f172a'
  ctx.textAlign = 'center'
  ctx.fillText(text, x, y + 3.5)
}

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

const handleClick = (e) => {
  if (!canvasRef.value) return
  const rect = canvasRef.value.getBoundingClientRect()
  const worldX = (e.clientX - rect.left - camera.value.x) / camera.value.zoom
  const worldY = (e.clientY - rect.top - camera.value.y) / camera.value.zoom

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

  for (const chef of chefs) {
    if (Math.hypot(worldX - chef.x, worldY - chef.y) <= 25) {
      emit('select-station', chef.station)
      return
    }
  }
}

const zoomIn = () => { camera.value.zoom = Math.min(camera.value.zoom + 0.15, 2.0) }
const zoomOut = () => { camera.value.zoom = Math.max(camera.value.zoom - 0.15, 0.5) }
const resetCamera = () => { camera.value.x = 30; camera.value.y = 20; camera.value.zoom = 1.0 }

const resizeCanvas = () => {
  if (!canvasRef.value) return
  const parent = canvasRef.value.parentElement
  if (parent) {
    canvasRef.value.width = parent.clientWidth || 1000
    canvasRef.value.height = 640
  }
}

watch(
  [() => props.zone, () => props.tables, () => props.stations],
  () => {
    initChefs()
    initWaiters()
  },
  { deep: true, immediate: true }
)

onMounted(() => {
  loadExteriorSprites()
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
  if (animationFrameId) cancelAnimationFrame(animationFrameId)
  window.removeEventListener('resize', resizeCanvas)
})
</script>

<style scoped>
.overcooked-live-container {
  min-height: 640px;
  background-color: #1e2229;
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
  height: 640px;
  display: block;
  cursor: grab;
}

.overcooked-canvas:active {
  cursor: grabbing;
}
</style>
