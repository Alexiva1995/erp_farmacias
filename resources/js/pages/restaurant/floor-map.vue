<template>
  <div class="restaurant-builder-view">
    <!-- BARRA SUPERIOR: GESTIÓN DE PLANTAS/ZONAS Y MODOS -->
    <v-card class="mb-4" elevation="1">
      <v-card-text class="py-3">
        <div class="d-flex flex-wrap align-center justify-space-between gap-3">
          <!-- Selector y Configuración de Plantas / Zonas -->
          <div class="d-flex align-center flex-wrap gap-2">
            <v-tabs
              v-if="zones.length"
              v-model="selectedZoneIndex"
              density="compact"
              color="primary"
              class="zone-tabs"
            >
              <v-tab v-for="zone in zones" :key="zone.id" :value="zone.id">
                <v-icon start size="18">tabler-map-pin</v-icon>
                <span>{{ zone.name }}</span>
                <v-chip size="x-small" variant="tonal" color="primary" class="ml-1">
                  {{ zoneAreaM2(zone) }} m²
                </v-chip>
              </v-tab>
            </v-tabs>

            <div class="d-flex align-center gap-1">
              <!-- Nueva Planta / Zona -->
              <v-btn
                icon="tabler-plus"
                size="small"
                variant="tonal"
                color="primary"
                title="Nueva Zona / Planta"
                @click="openCreateZoneModal"
              />
              <!-- Editar Dimensiones y Estilo de Zona -->
              <v-btn
                v-if="currentZone"
                icon="tabler-adjustments"
                size="small"
                variant="text"
                color="secondary"
                title="Configurar Metros y Estilo"
                @click="openEditZoneModal"
              />
              <!-- Eliminar Planta -->
              <v-btn
                v-if="currentZone && zones.length > 1"
                icon="tabler-trash"
                size="small"
                variant="text"
                color="error"
                title="Eliminar Zona"
                @click="showDeleteZoneConfirm = true"
              />
            </div>
          </div>

          <!-- Modos de Vista & Acciones Principales -->
          <div class="d-flex align-center flex-wrap gap-2">
            <!-- Alternador de Modos -->
            <v-btn-toggle
              v-model="viewMode"
              mandatory
              density="compact"
              color="primary"
              rounded="lg"
            >
              <v-btn value="builder" prepend-icon="tabler-tools">
                Constructor de Espacios
              </v-btn>
              <v-btn value="live" prepend-icon="tabler-chef-hat">
                Salón en Vivo
              </v-btn>
            </v-btn-toggle>

            <!-- Acciones del Constructor -->
            <template v-if="viewMode === 'builder'">
              <v-btn
                color="success"
                variant="flat"
                size="small"
                prepend-icon="tabler-device-floppy"
                :loading="savingLayout"
                @click="saveFullRestaurantDesign"
              >
                Guardar Restaurante
              </v-btn>

              <v-menu location="bottom end">
                <template #activator="{ props: menuProps }">
                  <v-btn
                    icon="tabler-dots-vertical"
                    variant="tonal"
                    size="small"
                    color="secondary"
                    v-bind="menuProps"
                    title="Opciones avanzadas"
                  />
                </template>
                <v-list density="compact">
                  <v-list-item
                    prepend-icon="tabler-trash-x"
                    class="text-error"
                    @click="showClearZoneModal = true"
                  >
                    <v-list-item-title>Vaciar Objetos de Esta Zona</v-list-item-title>
                  </v-list-item>
                </v-list>
              </v-menu>
            </template>

            <v-btn
              icon="tabler-refresh"
              variant="text"
              size="small"
              :loading="loadingOverview"
              title="Refrescar datos"
              @click="fetchOverview(false)"
            />
          </div>
        </div>

        <!-- Barra Métrica de la Zona Activa (Con Soporte Decimal Directo) -->
        <div v-if="currentZone && viewMode === 'builder'" class="d-flex flex-wrap align-center justify-space-between gap-3 mt-3 pt-2 border-t text-caption">
          <div class="d-flex align-center flex-wrap gap-3">
            <span class="d-flex align-center gap-1">
              <v-icon size="16" color="primary">tabler-ruler-2</v-icon>
              <strong>Dimensiones:</strong>
              {{ currentZoneWidth }} m × {{ currentZoneHeight }} m
              <v-chip size="x-small" color="primary" variant="flat" class="font-weight-bold ml-1">
                {{ currentZoneArea }} m²
              </v-chip>
            </span>

            <span class="text-medium-emphasis">|</span>

            <span><strong>Mesas:</strong> {{ filteredTables.length }}</span>
            <span><strong>Equipos/Frío:</strong> {{ layoutEquipment.length }}</span>
            <span><strong>Secciones:</strong> {{ layoutAreas.length }}</span>
          </div>

          <!-- Controles Rápidos de Medidas con Decimales y Snap -->
          <div class="d-flex align-center flex-wrap gap-2">
            <div class="d-flex align-center gap-1">
              <span class="text-medium-emphasis">Ancho:</span>
              <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="changeDimension('width', -0.5)" />
              <v-text-field
                v-model.number="currentZone.width_meters"
                type="number"
                step="0.1"
                min="2"
                max="100"
                density="compact"
                variant="outlined"
                hide-details
                style="width: 75px;"
              />
              <span class="text-caption font-weight-medium">m</span>
              <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="changeDimension('width', 0.5)" />
            </div>

            <div class="d-flex align-center gap-1 ml-2">
              <span class="text-medium-emphasis">Largo:</span>
              <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="changeDimension('height', -0.5)" />
              <v-text-field
                v-model.number="currentZone.height_meters"
                type="number"
                step="0.1"
                min="2"
                max="100"
                density="compact"
                variant="outlined"
                hide-details
                style="width: 75px;"
              />
              <span class="text-caption font-weight-medium">m</span>
              <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="changeDimension('height', 0.5)" />
            </div>

            <v-btn
              size="x-small"
              variant="tonal"
              :color="snapToGrid ? 'primary' : 'secondary'"
              prepend-icon="tabler-magnet"
              class="ml-2"
              @click="snapToGrid = !snapToGrid"
            >
              Snap Cuadrícula {{ snapToGrid ? 'ON' : 'OFF' }}
            </v-btn>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <!-- MODO 1: CONSTRUCTOR DE ESPACIOS (TIPO VIDEOJUEGO / MAP EDITOR) -->
    <v-row v-if="viewMode === 'builder'" dense>
      <!-- PANEL IZQUIERDO: PALETA DE CONSTRUCCIÓN -->
      <v-col cols="12" md="4" lg="3">
        <v-card class="builder-toolbox-card" elevation="2" border>
          <!-- Navegación en Cuadrícula 2x2 Siempre Visible (Sin pestañas ocultas) -->
          <div class="pa-2 border-b bg-surface">
            <div class="text-caption font-weight-bold text-medium-emphasis mb-2 px-1">
              CONSTRUYE PASO A PASO:
            </div>
            <v-row dense>
              <v-col cols="6">
                <v-btn
                  block
                  size="small"
                  :variant="toolboxTab === 'spaces' ? 'flat' : 'tonal'"
                  :color="toolboxTab === 'spaces' ? 'primary' : 'secondary'"
                  prepend-icon="tabler-layout-grid"
                  class="text-caption font-weight-bold justify-start"
                  @click="toolboxTab = 'spaces'"
                >
                  1. Áreas
                </v-btn>
              </v-col>
              <v-col cols="6">
                <v-btn
                  block
                  size="small"
                  :variant="toolboxTab === 'kitchen' ? 'flat' : 'tonal'"
                  :color="toolboxTab === 'kitchen' ? 'warning' : 'secondary'"
                  prepend-icon="tabler-flame"
                  class="text-caption font-weight-bold justify-start"
                  @click="toolboxTab = 'kitchen'"
                >
                  2. Cocina/Frío
                </v-btn>
              </v-col>
              <v-col cols="6">
                <v-btn
                  block
                  size="small"
                  :variant="toolboxTab === 'counters' ? 'flat' : 'tonal'"
                  :color="toolboxTab === 'counters' ? 'info' : 'secondary'"
                  prepend-icon="tabler-brand-shopee"
                  class="text-caption font-weight-bold justify-start"
                  @click="toolboxTab = 'counters'"
                >
                  3. Mostrador
                </v-btn>
              </v-col>
              <v-col cols="6">
                <v-btn
                  block
                  size="small"
                  :variant="toolboxTab === 'tables' ? 'flat' : 'tonal'"
                  :color="toolboxTab === 'tables' ? 'success' : 'secondary'"
                  prepend-icon="tabler-table"
                  class="text-caption font-weight-bold justify-start"
                  @click="toolboxTab = 'tables'"
                >
                  4. Mesas/Barra
                </v-btn>
              </v-col>
            </v-row>
          </div>

          <v-card-text class="pa-2 toolbox-content">
            <!-- PESTAÑA A: CATÁLOGO DE MESAS -->
            <div v-show="toolboxTab === 'tables'">
              <div class="text-caption font-weight-bold text-medium-emphasis mb-2 text-uppercase">
                Haz clic para colocar:
              </div>

              <div class="d-flex flex-column gap-2">
                <v-btn
                  v-for="preset in tablePresets"
                  :key="preset.id"
                  variant="tonal"
                  color="primary"
                  block
                  class="justify-start text-left py-2 h-auto"
                  @click="addTablePreset(preset)"
                >
                  <v-avatar size="32" color="primary" variant="flat" class="mr-2 rounded">
                    <v-icon size="18">{{ preset.icon }}</v-icon>
                  </v-avatar>
                  <div class="d-flex flex-column text-left">
                    <span class="font-weight-bold text-caption text-truncate">{{ preset.title }}</span>
                    <span class="text-caption text-medium-emphasis" style="font-size: 11px;">
                      {{ preset.capacity }} personas • {{ preset.dimMeters }}m
                    </span>
                  </div>
                </v-btn>
              </div>
            </div>

            <!-- PESTAÑA B: MOSTRADORES & BARRAS -->
            <div v-show="toolboxTab === 'counters'">
              <div class="text-caption font-weight-bold text-medium-emphasis mb-2 text-uppercase">
                Mostradores de Salón y Atención:
              </div>

              <div class="d-flex flex-column gap-2">
                <v-btn
                  v-for="counter in counterPresets"
                  :key="counter.id"
                  variant="tonal"
                  :color="counter.color || 'info'"
                  block
                  class="justify-start text-left py-2 h-auto"
                  @click="addCounterItem(counter)"
                >
                  <v-avatar size="32" :color="counter.color || 'info'" variant="flat" class="mr-2 rounded">
                    <v-icon size="18">{{ counter.icon }}</v-icon>
                  </v-avatar>
                  <div class="d-flex flex-column text-left">
                    <span class="font-weight-bold text-caption">{{ counter.title }}</span>
                    <span class="text-caption text-medium-emphasis" style="font-size: 11px;">
                      {{ counter.desc }} • {{ counter.w }}m×{{ counter.h }}m
                    </span>
                  </div>
                </v-btn>
              </div>
            </div>

            <!-- PESTAÑA C: COCINA & ESTACIONES KDS -->
            <div v-show="toolboxTab === 'kitchen'">
              <!-- Botón Prominente: Crear Nueva Estación de Cocina -->
              <v-btn
                color="warning"
                variant="flat"
                block
                prepend-icon="tabler-plus"
                class="mb-3 font-weight-bold"
                @click="openCreateStationModal"
              >
                + Nueva Estación de Cocina
              </v-btn>

              <div class="text-caption font-weight-bold text-medium-emphasis mb-2 text-uppercase">
                Estaciones KDS Disponibles:
              </div>

              <!-- Listado de Estaciones de Cocina Registradas -->
              <div class="d-flex flex-column gap-2 mb-3">
                <v-btn
                  v-for="st in kitchenStationsList"
                  :key="`st_${st.id}`"
                  variant="tonal"
                  color="warning"
                  block
                  class="justify-start text-left py-2 h-auto"
                  @click="addStationToCanvas(st)"
                >
                  <v-avatar size="32" color="warning" variant="flat" class="mr-2 rounded">
                    <v-icon size="18">{{ st.icon || 'tabler-flame' }}</v-icon>
                  </v-avatar>
                  <div class="d-flex flex-column text-left">
                    <span class="font-weight-bold text-caption">{{ st.name }}</span>
                    <span class="text-caption text-medium-emphasis" style="font-size: 11px;">
                      Código: {{ st.code || 'KDS' }} • 2.0m×1.0m
                    </span>
                  </div>
                </v-btn>
              </div>

              <div class="text-caption font-weight-bold text-medium-emphasis mb-2 text-uppercase">
                Mobiliario de Cocina:
              </div>

              <div class="d-flex flex-column gap-2">
                <v-btn
                  v-for="equip in kitchenEquipPresets"
                  :key="equip.id"
                  variant="tonal"
                  color="secondary"
                  block
                  class="justify-start text-left py-2 h-auto"
                  @click="addEquipmentItem(equip)"
                >
                  <v-avatar size="32" color="secondary" variant="flat" class="mr-2 rounded">
                    <v-icon size="18">{{ equip.icon }}</v-icon>
                  </v-avatar>
                  <div class="d-flex flex-column text-left">
                    <span class="font-weight-bold text-caption">{{ equip.title }}</span>
                    <span class="text-caption text-medium-emphasis" style="font-size: 11px;">
                      {{ equip.desc }} • {{ equip.w }}m×{{ equip.h }}m
                    </span>
                  </div>
                </v-btn>
              </div>

              <!-- SUBSECCIÓN: FRÍO, NEVERAS Y REFRIGERADORES -->
              <div class="text-caption font-weight-bold text-medium-emphasis mt-4 mb-2 text-uppercase">
                Refrigeración & Frío Comercial:
              </div>

              <div class="d-flex flex-column gap-2">
                <v-btn
                  v-for="cooler in coolingPresets"
                  :key="cooler.id"
                  variant="tonal"
                  color="cyan-darken-1"
                  block
                  class="justify-start text-left py-2 h-auto"
                  @click="addCoolerItem(cooler)"
                >
                  <v-avatar size="32" color="cyan-darken-1" variant="flat" class="mr-2 rounded">
                    <v-icon size="18">{{ cooler.icon }}</v-icon>
                  </v-avatar>
                  <div class="d-flex flex-column text-left">
                    <span class="font-weight-bold text-caption">{{ cooler.title }}</span>
                    <span class="text-caption text-medium-emphasis" style="font-size: 11px;">
                      {{ cooler.desc }}
                    </span>
                  </div>
                </v-btn>
              </div>
            </div>

            <!-- PESTAÑA E: ÁREAS Y SECCIONES ARQUITECTÓNICAS -->
            <div v-show="toolboxTab === 'spaces'">
              <!-- Botón Prominente: Crear Nueva Sección Personalizada -->
              <v-btn
                color="primary"
                variant="flat"
                block
                prepend-icon="tabler-plus"
                class="mb-3 font-weight-bold"
                @click="openCreateCustomAreaModal"
              >
                + Nueva Sección Personalizada
              </v-btn>

              <div class="text-caption font-weight-bold text-medium-emphasis mb-2 text-uppercase">
                Secciones Prediseñadas:
              </div>

              <div class="d-flex flex-column gap-2">
                <v-btn
                  v-for="areaType in spaceAreaTypes"
                  :key="areaType.id"
                  variant="tonal"
                  :color="areaType.btnColor"
                  block
                  class="justify-start text-left py-2 h-auto"
                  @click="addArchitecturalArea(areaType)"
                >
                  <v-avatar size="32" :color="areaType.btnColor" variant="flat" class="mr-2 rounded">
                    <v-icon size="18">{{ areaType.icon }}</v-icon>
                  </v-avatar>
                  <div class="d-flex flex-column text-left">
                    <span class="font-weight-bold text-caption">{{ areaType.title }}</span>
                    <span class="text-caption text-medium-emphasis" style="font-size: 11px;">
                      {{ areaType.desc }} • {{ areaType.w }}m×{{ areaType.h }}m
                    </span>
                  </div>
                </v-btn>
              <!-- Selector Rápido de Piso 2.5D -->
              <div v-if="currentZone" class="mb-3 pa-2 rounded border bg-surface">
                <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
                  ESTILO DE PISO DEL SALÓN:
                </div>
                <v-select
                  v-model="currentZone.background_style"
                  :items="[
                    { title: 'Madera Cálida', value: 'wooden' },
                    { title: 'Damero Salón Oscuro (Overcooked)', value: 'checker_dark' },
                    { title: 'Damero Crema/Salvia (Cocina)', value: 'checker_warm' },
                    { title: 'Concreto Moderno', value: 'concrete' },
                    { title: 'Césped / Terraza', value: 'grass' },
                    { title: 'Cuadrícula Tech', value: 'grid' },
                  ]"
                  item-title="title"
                  item-value="value"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </div>

              <!-- Accesorios / Props 2.5D -->
              <div class="text-caption font-weight-bold text-medium-emphasis mt-3 mb-2 text-uppercase">
                Accesorios y Decoración 2.5D:
              </div>
              <v-row dense class="mb-3">
                <v-col
                  v-for="propItem in [
                    { type: 'plant', title: 'Planta', icon: 'tabler-plant-2', w: 0.6, h: 0.6 },
                    { type: 'awning', title: 'Toldo', icon: 'tabler-tent', w: 2.0, h: 0.8 },
                    { type: 'bench', title: 'Banco', icon: 'tabler-armchair', w: 1.5, h: 0.6 },
                    { type: 'barrel', title: 'Barril', icon: 'tabler-barrel', w: 0.6, h: 0.6 },
                  ]"
                  :key="propItem.type"
                  cols="6"
                >
                  <v-btn
                    block
                    size="small"
                    variant="tonal"
                    color="success"
                    :prepend-icon="propItem.icon"
                    class="text-caption justify-start"
                    @click="addEquipmentItem(propItem)"
                  >
                    {{ propItem.title }}
                  </v-btn>
                </v-col>
              </v-row>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- ÁREA CENTRAL: LIENZO MÉTRICO INTERACTIVO -->
      <v-col cols="12" md="8" lg="9">
        <v-card class="builder-canvas-wrapper overflow-hidden" elevation="2">
          <!-- Barra de Controles de Lienzo (Alto Contraste y Tipografía Destacada) -->
          <div class="canvas-header-bar d-flex align-center justify-space-between px-4 py-2-5 flex-wrap gap-2">
            <div class="d-flex align-center gap-2 flex-wrap">
              <v-chip size="small" color="primary" variant="flat" class="font-weight-black text-caption">
                <v-icon start size="15">tabler-ruler-measure</v-icon>
                Escala: 1m = {{ cellPixelSize }}px
              </v-chip>
              <span class="canvas-info-text font-weight-bold text-caption">
                Cuadrícula métrica interactiva • Medidas decimales y snap magnético activos
              </span>
            </div>

            <div class="d-flex align-center gap-2">
              <v-chip size="small" variant="elevated" color="surface" class="font-weight-black text-caption">
                <v-icon start size="14" color="primary">tabler-zoom-check</v-icon>
                Zoom: {{ Math.round(zoomLevel * 100) }}%
              </v-chip>
              <v-btn icon="tabler-zoom-in" size="small" variant="tonal" color="white" title="Acercar (+)" @click="zoomIn" />
              <v-btn icon="tabler-zoom-out" size="small" variant="tonal" color="white" title="Alejar (-)" @click="zoomOut" />
              <v-btn icon="tabler-refresh" size="small" variant="tonal" color="white" title="Restablecer zoom al 100%" @click="zoomLevel = 1.0" />
            </div>
          </div>

          <!-- Contenedor con Scroll para el Lienzo Métrica -->
          <div class="canvas-scroll-container pa-4" @click.self="clearSelection">
            <div
              class="metric-grid-canvas position-relative"
              :class="`zone-bg-${currentZone?.background_style || 'wooden'}`"
              :style="{
                width: `${canvasPixelWidth}px`,
                height: `${canvasPixelHeight}px`,
                transform: `scale(${zoomLevel})`,
                transformOrigin: 'top left',
              }"
              @dragover.prevent
              @drop="handleCanvasDrop"
              @click.self="clearSelection"
            >
              <!-- REGLAS MÉTRICAS EXTERIORES LIMPIAS (SIN MARCAS INTERNAS NI HUÉRFANAS) -->
              <div class="ruler-top">
                <div class="ruler-origin-badge">0m</div>
                <div
                  v-for="m in Math.floor(currentZoneWidth)"
                  :key="`m-top-${m}`"
                  class="ruler-mark-x"
                  :style="{ left: `${m * cellPixelSize}px` }"
                >
                  <span class="ruler-badge">{{ m }}m</span>
                  <div class="ruler-tick-top" />
                </div>
                <div
                  v-if="currentZoneWidth % 1 !== 0"
                  class="ruler-mark-x"
                  :style="{ left: `${currentZoneWidth * cellPixelSize}px` }"
                >
                  <span class="ruler-badge ruler-badge-end">{{ Number(currentZoneWidth).toFixed(1) }}m</span>
                  <div class="ruler-tick-top" />
                </div>
              </div>
              <div class="ruler-left">
                <div
                  v-for="m in Math.floor(currentZoneHeight)"
                  :key="`m-left-${m}`"
                  class="ruler-mark-y"
                  :style="{ top: `${m * cellPixelSize}px` }"
                >
                  <span class="ruler-badge">{{ m }}m</span>
                  <div class="ruler-tick-left" />
                </div>
                <div
                  v-if="currentZoneHeight % 1 !== 0"
                  class="ruler-mark-y"
                  :style="{ top: `${currentZoneHeight * cellPixelSize}px` }"
                >
                  <span class="ruler-badge ruler-badge-end">{{ Number(currentZoneHeight).toFixed(1) }}m</span>
                  <div class="ruler-tick-left" />
                </div>
              </div>

              <!-- 1. CAPA DE ÁREAS ARQUITECTÓNICAS (Cocina, Depósito, Terraza, Muros, Secciones) -->
              <div
                v-for="area in layoutAreas"
                :key="area.id"
                class="architectural-area-block position-absolute"
                :class="[
                  `area-type-${area.type}`,
                  { 'is-selected': selectedObject?.id === area.id && selectedObject?.kind === 'area' }
                ]"
                :style="{
                  left: `${area.x * cellPixelSize}px`,
                  top: `${area.y * cellPixelSize}px`,
                  width: `${area.w * cellPixelSize}px`,
                  height: `${area.h * cellPixelSize}px`,
                  borderColor: area.color || undefined,
                }"
                draggable="true"
                @dragstart="handleAreaDragStart(area, $event)"
                @click.stop="selectObject(area, 'area')"
              >
                <!-- Badge Único Limpio del Área -->
                <div class="area-header-pill d-flex align-center justify-space-between px-2 py-1" :style="{ borderLeft: `3px solid ${area.color || '#ff9f43'}` }">
                  <div class="d-flex align-center gap-1 overflow-hidden mr-1">
                    <v-icon size="14" color="white" class="flex-shrink-0">{{ getAreaIcon(area.type) }}</v-icon>
                    <span class="area-name-text font-weight-black text-truncate">{{ area.name }}</span>
                  </div>
                  <span class="area-dimension-tag flex-shrink-0 font-weight-black">
                    {{ Number(area.w).toFixed(1) }}×{{ Number(area.h).toFixed(1) }}m
                  </span>
                </div>

                <!-- Mini-barra Contextual Flotante -->
                <div
                  v-if="selectedObject?.id === area.id && selectedObject?.kind === 'area'"
                  class="floating-object-toolbar"
                  @mousedown.stop
                  @click.stop
                >
                  <v-btn icon="tabler-trash" size="x-small" color="error" variant="flat" title="Eliminar área" @click="deleteSelectedObject" />
                </div>

                <!-- Tirador de Redimensión Directa -->
                <div
                  v-if="selectedObject?.id === area.id && selectedObject?.kind === 'area'"
                  class="canvas-resize-handle"
                  title="Arrastra para redimensionar"
                  @mousedown.stop.prevent="startResize(area, 'area', $event)"
                />
              </div>

              <!-- 2. CAPA DE MOSTRADORES, NEVERAS Y EQUIPAMIENTO -->
              <div
                v-for="eq in layoutEquipment"
                :key="eq.id"
                class="equipment-block position-absolute d-flex flex-column align-center justify-center px-1 py-1 text-center"
                :class="[
                  `equip-kind-${eq.type || 'station'}`,
                  { 'is-selected': selectedObject?.id === eq.id && selectedObject?.kind === 'equipment' },
                  { 'is-vertical-eq': Number(eq.h) > Number(eq.w) * 1.25 && Number(eq.w) <= 1.2 }
                ]"
                :style="{
                  left: `${eq.x * cellPixelSize}px`,
                  top: `${eq.y * cellPixelSize}px`,
                  width: `${eq.w * cellPixelSize}px`,
                  height: `${eq.h * cellPixelSize}px`,
                }"
                draggable="true"
                @dragstart="handleEquipmentDragStart(eq, $event)"
                @click.stop="selectObject(eq, 'equipment')"
              >
                <div class="equipment-content-inner d-flex flex-column align-center justify-center">
                  <div class="equipment-label-row d-flex align-center justify-center gap-1">
                    <v-icon size="15" color="white" class="flex-shrink-0">{{ eq.icon || 'tabler-box' }}</v-icon>
                    <span class="equipment-title-text font-weight-black">{{ eq.name }}</span>
                  </div>
                  <div class="equipment-measure-pill font-weight-black mt-1">
                    {{ Number(eq.w).toFixed(1) }}m×{{ Number(eq.h).toFixed(1) }}m
                  </div>
                </div>

                <!-- Mini-barra Contextual Flotante -->
                <div
                  v-if="selectedObject?.id === eq.id && selectedObject?.kind === 'equipment'"
                  class="floating-object-toolbar"
                  @mousedown.stop
                  @click.stop
                >
                  <v-btn icon="tabler-arrows-exchange" size="x-small" color="info" variant="flat" title="Rotar / Invertir ancho y largo" @click="swapEquipmentDimensions(eq)" />
                  <v-btn icon="tabler-trash" size="x-small" color="error" variant="flat" title="Eliminar equipo" @click="deleteSelectedObject" />
                </div>

                <!-- Tirador de Redimensión Directa -->
                <div
                  v-if="selectedObject?.id === eq.id && selectedObject?.kind === 'equipment'"
                  class="canvas-resize-handle"
                  title="Arrastra para redimensionar"
                  @mousedown.stop.prevent="startResize(eq, 'equipment', $event)"
                />
              </div>

              <!-- 3. CAPA DE MESAS Y BARRAS (IDENTIDAD REAL DE MOBILIARIO + COLISIONES + RESIZE DIRECTO) -->
              <div
                v-for="table in filteredTables"
                :key="table.id"
                class="floor-table-node position-absolute"
                :class="[
                  `shape-${table.shape || 'square'}`,
                  table.shape === 'bar' ? 'builder-furniture-bar' : 'builder-furniture-wood',
                  { 'is-selected': selectedObject?.id === table.id && selectedObject?.kind === 'table' },
                  { 'is-colliding': isTableColliding(table) },
                  { 'has-wall-top': isTableSideBlocked(table, 'top') },
                  { 'has-wall-bottom': isTableSideBlocked(table, 'bottom') },
                  { 'has-wall-left': isTableSideBlocked(table, 'left') },
                  { 'has-wall-right': isTableSideBlocked(table, 'right') },
                ]"
                :style="{
                  left: `${table.pos_x}px`,
                  top: `${table.pos_y}px`,
                  width: `${table.width || 60}px`,
                  height: `${table.height || 60}px`,
                  transform: `rotate(${table.rotation || 0}deg)`
                }"
                draggable="true"
                @dragstart="handleTableDragStart(table, $event)"
                @click.stop="selectObject(table, 'table')"
              >
                <!-- Indicador sutil de estado operativo (punto de color en esquina) -->
                <span class="table-status-dot" :class="`dot-${table.status || 'available'}`" :title="`Estado actual: ${table.status || 'available'}`" />

                <div class="table-inner d-flex flex-column align-center justify-center text-center px-1">
                  <span class="table-title font-weight-black text-truncate w-100">{{ table.name }}</span>
                  <span class="table-capacity font-weight-bold mt-0-5">
                    <v-icon size="10" color="white" class="mr-0-5">tabler-users</v-icon>
                    {{ table.capacity }}p
                  </span>
                </div>

                <!-- Sillas que representan la capacidad real y se ocultan si el lado toca una pared -->
                <div
                  v-for="seat in getTableSeats(table)"
                  :key="seat.id"
                  class="seat-dot"
                  :class="{ 'seat-stool': seat.isBarStool }"
                  :style="{ left: `${seat.x}px`, top: `${seat.y}px` }"
                  :title="seat.isBarStool ? 'Taburete de barra' : 'Silla'"
                />

                <!-- Indicador visual de borde pegado a la pared -->
                <div v-if="isTableSideBlocked(table, 'top')" class="wall-edge-indicator edge-top" title="Lado pegado a pared (sin sillas)" />
                <div v-if="isTableSideBlocked(table, 'bottom')" class="wall-edge-indicator edge-bottom" title="Lado pegado a pared (sin sillas)" />
                <div v-if="isTableSideBlocked(table, 'left')" class="wall-edge-indicator edge-left" title="Lado pegado a pared (sin sillas)" />
                <div v-if="isTableSideBlocked(table, 'right')" class="wall-edge-indicator edge-right" title="Lado pegado a pared (sin sillas)" />

                <!-- Mini-barra Contextual Flotante sobre la Mesa Seleccionada -->
                <div
                  v-if="selectedObject?.id === table.id && selectedObject?.kind === 'table'"
                  class="floating-object-toolbar"
                  :style="{ transform: `rotate(-${table.rotation || 0}deg)` }"
                  @mousedown.stop
                  @click.stop
                >
                  <v-btn icon="tabler-rotate-clockwise-2" size="x-small" color="secondary" variant="flat" title="Rotar 90°" @click="rotateSelectedTable(90)" />
                  <v-btn icon="tabler-copy" size="x-small" color="info" variant="flat" title="Duplicar" @click="duplicateSelectedTable" />
                  <v-btn icon="tabler-pencil" size="x-small" color="primary" variant="flat" title="Editar propiedades" @click="openEditTableModal(table)" />
                  <v-btn icon="tabler-trash" size="x-small" color="error" variant="flat" title="Eliminar mesa" @click="deleteSelectedObject" />
                </div>

                <!-- Tirador de Redimensión Directa -->
                <div
                  v-if="selectedObject?.id === table.id && selectedObject?.kind === 'table'"
                  class="canvas-resize-handle"
                  title="Arrastra para redimensionar"
                  @mousedown.stop.prevent="startResize(table, 'table', $event)"
                />
              </div>
            </div>
          </div>
        </v-card>

        <!-- PANEL INFERIOR INSPECTOR: PROPIEDADES DEL OBJETO SELECCIONADO (CON DECIMALES Y CONTROL DE PARED) -->
        <v-expand-transition>
          <v-card v-if="selectedObject" class="mt-3" elevation="2" border>
            <v-card-text class="py-2 px-3">
              <div class="d-flex flex-wrap align-center justify-space-between gap-3">
                <!-- Info Objeto -->
                <div class="d-flex align-center gap-2">
                  <v-avatar color="primary" variant="tonal" rounded size="36">
                    <v-icon>{{ selectedObjectIcon }}</v-icon>
                  </v-avatar>
                  <div>
                    <div class="text-subtitle-2 font-weight-bold">{{ selectedObjectName }}</div>
                    <div class="text-caption text-medium-emphasis">
                      {{ selectedObjectSubtext }}
                    </div>
                  </div>
                </div>

                <!-- Controles de Edición de Medidas, Capacidad y Rotación -->
                <div class="d-flex align-center flex-wrap gap-2">
                  <!-- Si es Mesa o Módulo de Barra -->
                  <template v-if="selectedObject.kind === 'table'">
                    <!-- Modificar Medidas en Metros (Ancho y Largo) -->
                    <div class="d-flex align-center gap-1">
                      <span class="text-caption font-weight-bold">Ancho:</span>
                      <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="resizeSelectedTable('w', -0.1)" />
                      <v-text-field
                        :model-value="Number(((selectedObject.data.width || 60) / cellPixelSize).toFixed(1))"
                        type="number"
                        step="0.1"
                        min="0.4"
                        max="20"
                        density="compact"
                        variant="outlined"
                        hide-details
                        style="width: 72px;"
                        @update:model-value="val => setTableDimensionMeters('width', val)"
                      />
                      <span class="text-caption font-weight-bold">m</span>
                      <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="resizeSelectedTable('w', 0.1)" />
                    </div>

                    <div class="d-flex align-center gap-1 ml-2">
                      <span class="text-caption font-weight-bold">Largo:</span>
                      <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="resizeSelectedTable('h', -0.1)" />
                      <v-text-field
                        :model-value="Number(((selectedObject.data.height || 60) / cellPixelSize).toFixed(1))"
                        type="number"
                        step="0.1"
                        min="0.4"
                        max="20"
                        density="compact"
                        variant="outlined"
                        hide-details
                        style="width: 72px;"
                        @update:model-value="val => setTableDimensionMeters('height', val)"
                      />
                      <span class="text-caption font-weight-bold">m</span>
                      <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="resizeSelectedTable('h', 0.1)" />
                    </div>

                    <!-- Modificar Capacidad (Número de Personas / Sillas) -->
                    <div class="d-flex align-center gap-1 ml-2">
                      <span class="text-caption font-weight-bold">Personas:</span>
                      <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="changeSelectedTableCapacity(-1)" />
                      <v-text-field
                        v-model.number="selectedObject.data.capacity"
                        type="number"
                        step="1"
                        min="1"
                        max="50"
                        density="compact"
                        variant="outlined"
                        hide-details
                        style="width: 65px;"
                      />
                      <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="changeSelectedTableCapacity(1)" />
                    </div>

                    <v-divider vertical class="mx-1" />

                    <!-- Control de Lados Pegados a la Pared (Quita sillas del lado activo) -->
                    <div class="d-flex align-center gap-1">
                      <span class="text-caption font-weight-bold" title="Lados que están pegados a la pared">Pared 🧱:</span>
                      <v-btn
                        size="x-small"
                        :variant="isTableSideBlocked(selectedObject.data, 'top') ? 'flat' : 'tonal'"
                        :color="isTableSideBlocked(selectedObject.data, 'top') ? 'warning' : 'secondary'"
                        title="Pegar lado superior a la pared (quita sillas superiores)"
                        @click="toggleTableSideBlocked(selectedObject.data, 'top')"
                      >
                        Arriba
                      </v-btn>
                      <v-btn
                        size="x-small"
                        :variant="isTableSideBlocked(selectedObject.data, 'bottom') ? 'flat' : 'tonal'"
                        :color="isTableSideBlocked(selectedObject.data, 'bottom') ? 'warning' : 'secondary'"
                        title="Pegar lado inferior a la pared (quita sillas inferiores)"
                        @click="toggleTableSideBlocked(selectedObject.data, 'bottom')"
                      >
                        Abajo
                      </v-btn>
                      <v-btn
                        size="x-small"
                        :variant="isTableSideBlocked(selectedObject.data, 'left') ? 'flat' : 'tonal'"
                        :color="isTableSideBlocked(selectedObject.data, 'left') ? 'warning' : 'secondary'"
                        title="Pegar lado izquierdo a la pared (quita sillas izquierdas)"
                        @click="toggleTableSideBlocked(selectedObject.data, 'left')"
                      >
                        Izq
                      </v-btn>
                      <v-btn
                        size="x-small"
                        :variant="isTableSideBlocked(selectedObject.data, 'right') ? 'flat' : 'tonal'"
                        :color="isTableSideBlocked(selectedObject.data, 'right') ? 'warning' : 'secondary'"
                        title="Pegar lado derecho a la pared (quita sillas derechas)"
                        @click="toggleTableSideBlocked(selectedObject.data, 'right')"
                      >
                        Der
                      </v-btn>
                    </div>

                    <v-divider vertical class="mx-1" />

                    <span class="text-caption font-weight-bold">Rotar:</span>
                    <v-btn size="x-small" variant="tonal" color="secondary" @click="rotateSelectedTable(90)">90°</v-btn>

                    <v-btn
                      size="small"
                      variant="tonal"
                      color="secondary"
                      prepend-icon="tabler-copy"
                      @click="duplicateSelectedTable"
                    >
                      Duplicar
                    </v-btn>

                    <v-btn
                      size="small"
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-pencil"
                      @click="openEditTableModal(selectedObject.data)"
                    >
                      Propiedades
                    </v-btn>
                  </template>

                  <!-- Si es Estación, Mostrador o Nevera/Frío (Equipment) -->
                  <template v-else-if="selectedObject.kind === 'equipment'">
                    <div class="d-flex align-center gap-1">
                      <span class="text-caption text-medium-emphasis">Ancho (m):</span>
                      <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="resizeSelectedEquipment('w', -0.5)" />
                      <v-text-field
                        v-model.number="selectedObject.data.w"
                        type="number"
                        step="0.1"
                        min="0.3"
                        max="50"
                        density="compact"
                        variant="outlined"
                        hide-details
                        style="width: 76px;"
                      />
                      <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="resizeSelectedEquipment('w', 0.5)" />
                    </div>

                    <div class="d-flex align-center gap-1 ml-1">
                      <span class="text-caption text-medium-emphasis">Largo (m):</span>
                      <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="resizeSelectedEquipment('h', -0.5)" />
                      <v-text-field
                        v-model.number="selectedObject.data.h"
                        type="number"
                        step="0.1"
                        min="0.3"
                        max="50"
                        density="compact"
                        variant="outlined"
                        hide-details
                        style="width: 76px;"
                      />
                      <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="resizeSelectedEquipment('h', 0.5)" />
                    </div>

                    <v-chip size="small" variant="tonal" color="primary" class="ml-1 font-weight-bold">
                      {{ (Number(selectedObject.data.w || 1) * Number(selectedObject.data.h || 1)).toFixed(2) }} m²
                    </v-chip>

                    <v-divider vertical class="mx-1" />

                    <v-btn size="x-small" variant="tonal" color="secondary" prepend-icon="tabler-rotate" @click="rotateSelectedEquipment">
                      Girar 90°
                    </v-btn>
                  </template>

                  <!-- Si es Área / Sección Arquitectónica -->
                  <template v-else-if="selectedObject.kind === 'area'">
                    <div class="d-flex align-center gap-1">
                      <span class="text-caption text-medium-emphasis">Ancho (m):</span>
                      <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="resizeSelectedArea('w', -0.5)" />
                      <v-text-field
                        v-model.number="selectedObject.data.w"
                        type="number"
                        step="0.1"
                        min="0.5"
                        max="100"
                        density="compact"
                        variant="outlined"
                        hide-details
                        style="width: 76px;"
                      />
                      <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="resizeSelectedArea('w', 0.5)" />
                    </div>

                    <div class="d-flex align-center gap-1 ml-1">
                      <span class="text-caption text-medium-emphasis">Largo (m):</span>
                      <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="resizeSelectedArea('h', -0.5)" />
                      <v-text-field
                        v-model.number="selectedObject.data.h"
                        type="number"
                        step="0.1"
                        min="0.5"
                        max="100"
                        density="compact"
                        variant="outlined"
                        hide-details
                        style="width: 76px;"
                      />
                      <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="resizeSelectedArea('h', 0.5)" />
                    </div>

                    <v-chip size="small" variant="tonal" color="primary" class="ml-1 font-weight-bold">
                      {{ (Number(selectedObject.data.w || 1) * Number(selectedObject.data.h || 1)).toFixed(2) }} m²
                    </v-chip>
                  </template>

                  <!-- Botón Eliminar Universal -->
                  <v-btn
                    size="small"
                    variant="text"
                    color="error"
                    prepend-icon="tabler-trash"
                    @click="deleteSelectedObject"
                  >
                    Eliminar
                  </v-btn>

                  <v-btn icon="tabler-x" size="x-small" variant="text" @click="clearSelection" />
                </div>
              </div>
            </v-card-text>
          </v-card>
        </v-expand-transition>
      </v-col>
    </v-row>

    <!-- MODO 2: SALÓN EN VIVO (OPERACIÓN, COMANDAS, PRE-CUENTAS) -->
    <div v-else>
      <!-- Resumen Rápido de Estados -->
      <v-row class="mb-4" dense>
        <v-col cols="6" sm="3">
          <v-card class="pa-2 d-flex align-center gap-3" border flat>
            <v-avatar color="success" variant="tonal" rounded size="38">
              <v-icon>tabler-check</v-icon>
            </v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">Libres</div>
              <div class="text-h6 font-weight-bold">{{ availableTablesCount }}</div>
            </div>
          </v-card>
        </v-col>
        <v-col cols="6" sm="3">
          <v-card class="pa-2 d-flex align-center gap-3" border flat>
            <v-avatar color="error" variant="tonal" rounded size="38">
              <v-icon>tabler-tools-kitchen-2</v-icon>
            </v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">Ocupadas</div>
              <div class="text-h6 font-weight-bold">{{ occupiedTablesCount }}</div>
            </div>
          </v-card>
        </v-col>
        <v-col cols="6" sm="3">
          <v-card class="pa-2 d-flex align-center gap-3" border flat>
            <v-avatar color="warning" variant="tonal" rounded size="38">
              <v-icon>tabler-receipt</v-icon>
            </v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">Pre-Cuenta Pedida</div>
              <div class="text-h6 font-weight-bold">{{ billRequestedTablesCount }}</div>
            </div>
          </v-card>
        </v-col>
        <v-col cols="6" sm="3">
          <v-card class="pa-2 d-flex align-center gap-3" border flat>
            <v-avatar color="info" variant="tonal" rounded size="38">
              <v-icon>tabler-calendar-time</v-icon>
            </v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">Reservadas</div>
              <div class="text-h6 font-weight-bold">{{ reservedTablesCount }}</div>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <!-- Motor en Vivo 2.5D sincronizado con las áreas diseñadas -->
      <v-card class="live-map-card overflow-hidden" elevation="2">
        <OvercookedLiveMap
          :tables="filteredTables"
          :stations="kitchenStations"
          :zone="currentZone"
          :selected-table-id="selectedTable?.id"
          @select-table="selectAndInteractTable"
          @select-station="handleSelectStation"
        />
      </v-card>
    </div>

    <!-- MODAL: CREAR NUEVA SECCIÓN PERSONALIZADA -->
    <v-dialog v-model="showCreateCustomAreaModal" max-width="500">
      <v-card>
        <v-card-title class="bg-primary text-white py-3 px-4 d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <v-icon>tabler-layout-grid-add</v-icon>
            <span class="font-weight-bold">Nueva Sección / Área Personalizada</span>
          </div>
          <v-btn icon="tabler-x" variant="text" size="small" color="white" @click="showCreateCustomAreaModal = false" />
        </v-card-title>
        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitCreateCustomArea">
            <v-text-field
              v-model="customAreaForm.name"
              label="Nombre de la Sección"
              placeholder="Ej: Área de Lavado, Zona Buffet, Cava de Vinos, Delivery, Baños"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-tag"
              class="mb-3"
              required
            />

            <v-select
              v-model="customAreaForm.type"
              :items="[
                { title: 'Zona de Cocina (Azulejo)', value: 'kitchen' },
                { title: 'Depósito / Almacén (Concreto)', value: 'storage' },
                { title: 'Barra / Bar (Madera/Azul)', value: 'bar_zone' },
                { title: 'Terraza / Jardín (Césped)', value: 'terrace' },
                { title: 'Muro / Pared Divisoria', value: 'wall' },
                { title: 'Entrada / Recepción', value: 'entrance' },
                { title: 'Área Demarcada Genérica', value: 'pass_counter' }
              ]"
              label="Estilo de Suelo / Textura"
              density="compact"
              variant="outlined"
              class="mb-3"
            />

            <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
              Dimensiones de la Sección (con decimales):
            </div>
            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="customAreaForm.w"
                  type="number"
                  step="0.1"
                  min="0.5"
                  max="50"
                  label="Ancho en metros"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="customAreaForm.h"
                  type="number"
                  step="0.1"
                  min="0.5"
                  max="50"
                  label="Largo en metros"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
            </v-row>

            <v-alert density="compact" variant="tonal" color="primary" class="mb-3 text-caption">
              Superficie: <strong>{{ (Number(customAreaForm.w || 1) * Number(customAreaForm.h || 1)).toFixed(2) }} m²</strong>
            </v-alert>

            <div class="d-flex justify-end gap-2 mt-2">
              <v-btn variant="text" @click="showCreateCustomAreaModal = false">Cancelar</v-btn>
              <v-btn type="submit" color="primary" :loading="submittingAction">
                Crear y Colocar en Plano
              </v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL: CREAR NUEVA ESTACIÓN DE COCINA -->
    <v-dialog v-model="showCreateStationModal" max-width="500">
      <v-card>
        <v-card-title class="bg-warning text-white py-3 px-4 d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <v-icon>tabler-flame</v-icon>
            <span class="font-weight-bold">Nueva Estación de Cocina KDS</span>
          </div>
          <v-btn icon="tabler-x" variant="text" size="small" color="white" @click="showCreateStationModal = false" />
        </v-card-title>
        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitCreateStation">
            <v-text-field
              v-model="createStationForm.name"
              label="Nombre de la Estación"
              placeholder="Ej: Freidoras, Horno de Pizza, Plancha, Repostería"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-tag"
              class="mb-3"
              required
            />

            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model="createStationForm.code"
                  label="Código KDS (opcional)"
                  placeholder="Ej: FRY, PIZZA"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
              <v-col cols="6">
                <v-select
                  v-model="createStationForm.icon"
                  :items="[
                    { title: 'Fuego / Parrilla', value: 'tabler-flame' },
                    { title: 'Pizza / Horno', value: 'tabler-pizza' },
                    { title: 'Cuchillo / Corte', value: 'tabler-tools-kitchen-2' },
                    { title: 'Bebidas / Bar', value: 'tabler-glass-full' },
                    { title: 'Pescado / Mar', value: 'tabler-fish' },
                    { title: 'Panadería / Horno', value: 'tabler-bread' }
                  ]"
                  label="Icono"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
            </v-row>

            <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
              Dimensiones de la Estación en el Plano (metros con decimales):
            </div>
            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="createStationForm.width_m"
                  type="number"
                  step="0.1"
                  min="0.5"
                  max="10"
                  label="Ancho (metros)"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="createStationForm.height_m"
                  type="number"
                  step="0.1"
                  min="0.5"
                  max="10"
                  label="Largo (metros)"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
            </v-row>

            <div class="d-flex justify-end gap-2 mt-2">
              <v-btn variant="text" @click="showCreateStationModal = false">Cancelar</v-btn>
              <v-btn type="submit" color="warning" :loading="submittingAction">
                Crear y Colocar en Plano
              </v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL: GESTIÓN DE PLANTA / ZONA -->
    <v-dialog v-model="showZoneModal" max-width="520">
      <v-card>
        <v-card-title class="bg-surface-variant py-3 px-4 d-flex align-center justify-space-between">
          <span class="font-weight-bold">
            {{ isEditingZone ? 'Configurar Planta / Zona' : 'Nueva Planta / Zona del Restaurante' }}
          </span>
          <v-btn icon="tabler-x" variant="text" size="small" @click="showZoneModal = false" />
        </v-card-title>
        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitZoneForm">
            <v-text-field
              v-model="zoneForm.name"
              label="Nombre de la Zona"
              placeholder="Ej: Salón Principal, Terraza, Balcón VIP"
              prepend-inner-icon="tabler-map-pin"
              density="compact"
              variant="outlined"
              class="mb-3"
              required
            />

            <!-- Medidas en Metros con decimales -->
            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="zoneForm.width_meters"
                  type="number"
                  step="0.1"
                  min="2"
                  max="100"
                  label="Ancho en metros"
                  prepend-inner-icon="tabler-ruler"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                  required
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="zoneForm.height_meters"
                  type="number"
                  step="0.1"
                  min="2"
                  max="100"
                  label="Largo en metros"
                  prepend-inner-icon="tabler-ruler"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                  required
                />
              </v-col>
            </v-row>

            <v-alert density="compact" variant="tonal" color="primary" class="mb-3 text-caption">
              Superficie calculada: <strong>{{ (Number(zoneForm.width_meters || 12) * Number(zoneForm.height_meters || 8)).toFixed(2) }} m²</strong>
            </v-alert>

            <v-select
              v-model="zoneForm.background_style"
              :items="[
                { title: 'Madera Parquet Cálida', value: 'wooden' },
                { title: 'Cuadrícula Técnica / Plano', value: 'grid' },
                { title: 'Terraza / Césped al Aire Libre', value: 'grass' },
                { title: 'Cemento Pulido Industrial', value: 'concrete' }
              ]"
              label="Estilo de Suelo Base"
              prepend-inner-icon="tabler-brush"
              density="compact"
              variant="outlined"
              class="mb-3"
            />

            <div class="d-flex justify-end gap-2 mt-2">
              <v-btn variant="text" @click="showZoneModal = false">Cancelar</v-btn>
              <v-btn type="submit" color="primary" :loading="submittingAction">
                {{ isEditingZone ? 'Guardar Cambios' : 'Crear Zona' }}
              </v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL: EDITAR PROPIEDADES DE MESA -->
    <v-dialog v-model="showEditTableModal" max-width="480">
      <v-card v-if="editingTable">
        <v-card-title class="bg-surface-variant py-3 px-4 d-flex align-center justify-space-between">
          <span class="font-weight-bold">Propiedades: {{ editingTable.name }}</span>
          <v-btn icon="tabler-x" variant="text" size="small" @click="showEditTableModal = false" />
        </v-card-title>
        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitUpdateTable">
            <v-text-field
              v-model="editTableForm.name"
              label="Nombre / Identificador"
              density="compact"
              variant="outlined"
              class="mb-3"
              required
            />

            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="editTableForm.capacity"
                  type="number"
                  min="1"
                  max="50"
                  label="Capacidad (sillas)"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                  required
                />
              </v-col>
              <v-col cols="6">
                <v-select
                  v-model="editTableForm.shape"
                  :items="[
                    { title: 'Cuadrada', value: 'square' },
                    { title: 'Redonda', value: 'round' },
                    { title: 'Rectangular', value: 'rectangle' },
                    { title: 'Barra', value: 'bar' }
                  ]"
                  label="Forma"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
            </v-row>

            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="editTableForm.width_meters"
                  type="number"
                  step="0.1"
                  min="0.4"
                  max="20"
                  label="Ancho en metros"
                  suffix="m"
                  density="compact"
                  variant="outlined"
                  class="mb-2"
                  @update:model-value="v => editTableForm.width = Math.round(Number(v || 1) * cellPixelSize)"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="editTableForm.height_meters"
                  type="number"
                  step="0.1"
                  min="0.4"
                  max="20"
                  label="Largo en metros"
                  suffix="m"
                  density="compact"
                  variant="outlined"
                  class="mb-2"
                  @update:model-value="v => editTableForm.height = Math.round(Number(v || 1) * cellPixelSize)"
                />
              </v-col>
            </v-row>

            <v-row dense>
              <v-col cols="6">
                <v-text-field v-model.number="editTableForm.rotation" type="number" label="Giro (°)" density="compact" variant="outlined" />
              </v-col>
              <v-col cols="6">
                <v-alert density="compact" variant="tonal" color="info" class="text-caption py-1">
                  Equivalente: {{ editTableForm.width }}px × {{ editTableForm.height }}px
                </v-alert>
              </v-col>
            </v-row>

            <div class="d-flex justify-end gap-2 mt-3">
              <v-btn variant="text" @click="showEditTableModal = false">Cancelar</v-btn>
              <v-btn type="submit" color="primary" :loading="submittingAction">
                Actualizar Mesa
              </v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL: ELIMINAR ZONA -->
    <v-dialog v-model="showDeleteZoneConfirm" max-width="450">
      <v-card>
        <v-card-title class="bg-error text-white py-3 px-4">Eliminar Zona</v-card-title>
        <v-card-text class="pa-4">
          <p class="text-body-2 mb-2">
            ¿Eliminar la zona <strong>{{ currentZone?.name }}</strong>?
          </p>
          <p class="text-caption text-error mb-0">
            Se eliminarán todas sus mesas y áreas diseñadas.
          </p>
        </v-card-text>
        <v-card-actions class="px-4 pb-4 justify-end gap-2">
          <v-btn variant="text" @click="showDeleteZoneConfirm = false">Cancelar</v-btn>
          <v-btn color="error" variant="flat" :loading="submittingAction" @click="submitDeleteZone">
            Eliminar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- MODAL: VACIAR OBJETOS DE LA ZONA -->
    <v-dialog v-model="showClearZoneModal" max-width="450">
      <v-card>
        <v-card-title class="bg-warning text-white py-3 px-4 d-flex align-center gap-2">
          <v-icon>tabler-alert-triangle</v-icon>
          <span>Vaciar Zona</span>
        </v-card-title>
        <v-card-text class="pa-4">
          <p class="text-body-2 mb-2">
            ¿Deseas eliminar todas las mesas, áreas y equipos de <strong>{{ currentZone?.name }}</strong> para empezar desde un lienzo en blanco?
          </p>
        </v-card-text>
        <v-card-actions class="px-4 pb-4 justify-end gap-2">
          <v-btn variant="text" @click="showClearZoneModal = false">Cancelar</v-btn>
          <v-btn color="warning" variant="flat" :loading="submittingAction" @click="submitClearZone">
            Vaciar Lienzo
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- MODAL: ACCIONES OPERATIVAS EN SALÓN EN VIVO -->
    <v-dialog v-model="showTableActionModal" max-width="600">
      <v-card v-if="selectedTable">
        <v-card-title class="d-flex align-center justify-space-between bg-surface-variant py-3 px-4">
          <div class="d-flex align-center gap-2">
            <v-icon :color="getStatusColor(selectedTable.status)">tabler-table</v-icon>
            <span class="font-weight-bold">{{ selectedTable.name }}</span>
            <v-chip size="small" :color="getStatusColor(selectedTable.status)">
              {{ selectedTable.status_label || selectedTable.status }}
            </v-chip>
          </div>
          <v-btn icon="tabler-x" variant="text" size="small" @click="showTableActionModal = false" />
        </v-card-title>

        <v-card-text class="pa-4">
          <!-- CASO 1: MESA LIBRE -> ABRIR ORDEN -->
          <div v-if="selectedTable.status === 'available'">
            <p class="text-body-2 mb-3">Mesa libre. Indica el número de comensales para abrir la cuenta:</p>
            <v-form @submit.prevent="submitOpenOrder">
              <v-text-field
                v-model.number="openOrderForm.guests_count"
                type="number"
                min="1"
                max="50"
                label="Comensales / Sillas"
                density="compact"
                variant="outlined"
                class="mb-3"
              />
              <v-btn type="submit" color="primary" block :loading="submittingAction">
                Abrir Mesa
              </v-btn>
            </v-form>
          </div>

          <!-- CASO 2: MESA OCUPADA -->
          <div v-else>
            <div class="d-flex justify-space-between align-center mb-3">
              <div>
                <span class="text-caption text-medium-emphasis">Comensales: </span>
                <span class="font-weight-bold">{{ selectedTable.current_order?.guests_count || 1 }}</span>
              </div>
              <div class="text-h6 font-weight-bold text-primary">
                Total: ${{ Number(selectedTable.current_order?.total_amount_usd || 0).toFixed(2) }} USD
              </div>
            </div>

            <!-- Lista de Consumo -->
            <v-table density="compact" class="border rounded mb-3">
              <thead>
                <tr>
                  <th>Platillo</th>
                  <th class="text-center">Cant.</th>
                  <th class="text-right">Total USD</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in (selectedTable.current_order?.details || [])" :key="item.id">
                  <td>{{ item.name }}</td>
                  <td class="text-center">{{ item.quantity }}</td>
                  <td class="text-right">${{ (item.quantity * item.unit_price_usd).toFixed(2) }}</td>
                </tr>
                <tr v-if="!selectedTable.current_order?.details?.length">
                  <td colspan="3" class="text-center text-medium-emphasis py-2">Sin ítems todavía.</td>
                </tr>
              </tbody>
            </v-table>

            <div class="d-flex justify-end gap-2">
              <v-btn color="error" variant="text" size="small" :loading="submittingAction" @click="releaseTableAction">
                Liberar Mesa
              </v-btn>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- Widget del Simulador de Tráfico en Vivo -->
    <RestaurantSimulatorWidget
      v-if="brandingStore.enable_live_simulator"
      @updated="fetchOverview(true)"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'
import RestaurantSimulatorWidget from '@/components/restaurant/RestaurantSimulatorWidget.vue'
import { useBrandingStore } from '@/stores/useBrandingStore'

const brandingStore = useBrandingStore()
import OvercookedLiveMap from '@/components/restaurant/OvercookedLiveMap.vue'

// ==========================================
// ESTADOS PRINCIPALES
// ==========================================
const viewMode = ref('builder') // 'builder' (constructor) o 'live' (salón en vivo)
const toolboxTab = ref('tables')
const zones = ref([])
const tables = ref([])
const kitchenStations = ref([])
const selectedZoneIndex = ref(null)

const loadingOverview = ref(false)
const savingLayout = ref(false)
const submittingAction = ref(false)

// Modales
const showZoneModal = ref(false)
const showDeleteZoneConfirm = ref(false)
const showClearZoneModal = ref(false)
const showEditTableModal = ref(false)
const showTableActionModal = ref(false)
const showCreateStationModal = ref(false)
const showCreateCustomAreaModal = ref(false)
const isEditingZone = ref(false)

// Objeto seleccionado en el canvas del constructor
const selectedObject = ref(null) // { kind: 'table'|'area'|'equipment', id, data }

// Mesa seleccionada en salón en vivo
const selectedTable = ref(null)
const editingTable = ref(null)

// Dragging
const draggedTable = ref(null)
const draggedArea = ref(null)
const draggedEquipment = ref(null)
const dragOffset = reactive({ x: 0, y: 0 })

// Parámetros de Escala Métrica
const cellPixelSize = 60 // 1 metro = 60px
const snapToGrid = ref(true)
const zoomLevel = ref(1.0)

// Formularios
const zoneForm = reactive({
  id: null,
  name: '',
  background_style: 'wooden',
  width_meters: 12.0,
  height_meters: 8.0,
})

const editTableForm = reactive({
  id: null,
  name: '',
  capacity: 4,
  shape: 'square',
  width: 72,
  height: 72,
  width_meters: 1.2,
  height_meters: 1.2,
  rotation: 0,
})

const createStationForm = reactive({
  name: '',
  code: '',
  color: '#ff9f43',
  icon: 'tabler-flame',
  width_m: 2.0,
  height_m: 1.0,
})

const customAreaForm = reactive({
  name: '',
  type: 'custom',
  color: '#00cfe8',
  w: 3.5,
  h: 2.5,
})

const openOrderForm = reactive({
  guests_count: 2,
})

// ==========================================
// CATÁLOGO DE OBJETOS PRE-CONFIGURADOS
// ==========================================
const tablePresets = [
  { id: 't2', title: 'Mesa 2 Personas', capacity: 2, shape: 'square', width: 60, height: 60, dimMeters: '1.0x1.0', icon: 'tabler-square' },
  { id: 't4', title: 'Mesa 4 Personas', capacity: 4, shape: 'square', width: 72, height: 72, dimMeters: '1.2x1.2', icon: 'tabler-grid-pattern' },
  { id: 't4r', title: 'Mesa Redonda 4P', capacity: 4, shape: 'round', width: 72, height: 72, dimMeters: 'Ø 1.2', icon: 'tabler-circle' },
  { id: 't6', title: 'Mesa 6 Personas', capacity: 6, shape: 'rectangle', width: 110, height: 72, dimMeters: '1.8x1.2', icon: 'tabler-rectangle' },
  { id: 't8', title: 'Mesa Imperial 8P', capacity: 8, shape: 'rectangle', width: 144, height: 72, dimMeters: '2.4x1.2', icon: 'tabler-rectangle-vertical' },
  { id: 'bar', title: 'Módulo de Barra (Con Taburetes)', capacity: 4, shape: 'bar', width: 150, height: 48, dimMeters: '2.5x0.8', icon: 'tabler-glass-full' },
]

// MOSTRADORES & BARRAS
const counterPresets = [
  { id: 'cnt_reception', title: 'Mostrador de Recepción', name: 'Recepción', type: 'counter', w: 2.5, h: 1.0, color: 'info', icon: 'tabler-user-check', desc: 'Atención y reservas' },
  { id: 'cnt_pass', title: 'Mostrador de Pase (KDS)', name: 'Pase KDS', type: 'pass_counter', w: 3.0, h: 1.0, color: 'warning', icon: 'tabler-bell', desc: 'Despacho de comandas' },
  { id: 'cnt_bar', title: 'Mostrador de Barra / Bar', name: 'Barra', type: 'counter', w: 4.0, h: 1.2, color: 'primary', icon: 'tabler-glass-full', desc: 'Licores y coctelería' },
  { id: 'cnt_cashier', title: 'Mostrador de Caja / POS', name: 'Caja POS', type: 'counter', w: 1.8, h: 1.0, color: 'success', icon: 'tabler-device-laptop', desc: 'Cobro y facturación' },
  { id: 'cnt_buffet', title: 'Mostrador Buffet / Autoservicio', name: 'Buffet', type: 'counter', w: 3.5, h: 1.5, color: 'secondary', icon: 'tabler-meat', desc: 'Isla de comida' },
]

// REFRIGERACIÓN Y FRÍO COMERCIAL
const coolingPresets = [
  { id: 'cool_fridge', title: 'Refrigerador Vertical', name: 'Refrigerador', type: 'cooling', w: 1.0, h: 1.0, icon: 'tabler-fridge', desc: '1 puerta vertical (1.0m × 1.0m)' },
  { id: 'cool_double', title: 'Refrigerador 2 Puertas', name: 'Refrig. Doble', type: 'cooling', w: 1.6, h: 1.0, icon: 'tabler-fridge', desc: 'Acero inoxidable (1.6m × 1.0m)' },
  { id: 'cool_freezer', title: 'Congelador Horizontal', name: 'Congelador', type: 'cooling', w: 1.8, h: 0.9, icon: 'tabler-snowflake', desc: 'Arcón congelador (1.8m × 0.9m)' },
  { id: 'cool_walkin', title: 'Cámara Frigorífica', name: 'Cámara Frío', type: 'cooling', w: 3.0, h: 2.5, icon: 'tabler-building-warehouse', desc: 'Walk-in cooler (3.0m × 2.5m)' },
  { id: 'cool_table', title: 'Mesa Fría de Prep', name: 'Mesa Fría', type: 'cooling', w: 2.0, h: 1.0, icon: 'tabler-salad', desc: 'Bajo mesada ensaladas (2.0m × 1.0m)' },
  { id: 'cool_display', title: 'Vitrina de Bebidas', name: 'Vitrina Fría', type: 'cooling', w: 1.4, h: 0.8, icon: 'tabler-glass', desc: 'Puertas de cristal (1.4m × 0.8m)' },
]

const spaceAreaTypes = [
  { id: 'kitchen', title: 'Zona de Cocina', type: 'kitchen', w: 4.0, h: 3.0, btnColor: 'error', icon: 'tabler-tools-kitchen-2', desc: 'Piso cerámico blanco' },
  { id: 'storage', title: 'Depósito / Almacén', type: 'storage', w: 3.0, h: 2.0, btnColor: 'secondary', icon: 'tabler-archive', desc: 'Insumos secos' },
  { id: 'bar_zone', title: 'Zona de Barra / Bar', type: 'bar_zone', w: 4.0, h: 2.0, btnColor: 'info', icon: 'tabler-glass', desc: 'Coctelería y tragos' },
  { id: 'terrace', title: 'Terraza / Jardín', type: 'terrace', w: 4.0, h: 3.0, btnColor: 'success', icon: 'tabler-plant', desc: 'Piso deck / césped' },
  { id: 'wall', title: 'Muro / Pared', type: 'wall', w: 3.0, h: 0.4, btnColor: 'grey-darken-2', icon: 'tabler-wall', desc: 'División estructural' },
  { id: 'entrance', title: 'Entrada / Puerta', type: 'entrance', w: 2.0, h: 1.0, btnColor: 'purple', icon: 'tabler-door-enter', desc: 'Recepción y bienvenida' },
]

const kitchenEquipPresets = [
  { id: 'eq_stove_pot', type: 'stove_pot', title: 'Fogón con Olla', name: 'Fogón Olla', w: 1.0, h: 1.0, icon: 'tabler-soup', desc: 'Cocción estilo Overcooked' },
  { id: 'eq_cutting', type: 'cutting_board', title: 'Mesón de Corte', name: 'Tabla Corte', w: 1.0, h: 1.0, icon: 'tabler-cut', desc: 'Preparación con tabla' },
  { id: 'eq_sink', type: 'sink', title: 'Fregadero / Pila', name: 'Fregadero', w: 1.0, h: 1.0, icon: 'tabler-droplet', desc: 'Lavado de platos' },
  { id: 'eq_ingredients', type: 'ingredient_box', title: 'Caja de Ingredientes', name: 'Insumos', w: 1.0, h: 1.0, icon: 'tabler-box', desc: 'Tomates y víveres' },
  { id: 'eq_counter_base', type: 'counter_base', title: 'Mesón Base', name: 'Mesón', w: 1.0, h: 1.0, icon: 'tabler-table-row', desc: 'Encimera modular' },
  { id: 'eq_pass', type: 'pass_counter', title: 'Cinta / Pase Platos', name: 'Pase Platos', w: 2.0, h: 1.0, icon: 'tabler-arrow-bar-right', desc: 'Entrega al salón' },
]

// ==========================================
// COMPUTADAS
// ==========================================
const currentZone = computed(() => {
  return zones.value.find(z => z.id === selectedZoneIndex.value) || zones.value[0] || null
})

const currentZoneWidth = computed(() => Math.max(2, Number(currentZone.value?.width_meters || 12.0)))
const currentZoneHeight = computed(() => Math.max(2, Number(currentZone.value?.height_meters || 8.0)))
const currentZoneArea = computed(() => (currentZoneWidth.value * currentZoneHeight.value).toFixed(2))

const canvasPixelWidth = computed(() => currentZoneWidth.value * cellPixelSize)
const canvasPixelHeight = computed(() => currentZoneHeight.value * cellPixelSize)

const filteredTables = computed(() => {
  if (!currentZone.value) return []
  return tables.value.filter(t => t.dining_zone_id === currentZone.value.id)
})

const kitchenStationsList = computed(() => {
  if (kitchenStations.value.length) return kitchenStations.value
  return [
    { id: 1, name: 'Parrilla & Fuego', code: 'GRILL', icon: 'tabler-flame' },
    { id: 2, name: 'Estación de Corte', code: 'PREP', icon: 'tabler-tools-kitchen-2' },
    { id: 3, name: 'Barra de Bebidas', code: 'BAR', icon: 'tabler-glass-full' },
  ]
})

const layoutAreas = computed({
  get() {
    if (!currentZone.value) return []
    if (!currentZone.value.layout_config) return []
    return currentZone.value.layout_config.areas || []
  },
  set(val) {
    if (!currentZone.value) return
    if (!currentZone.value.layout_config) currentZone.value.layout_config = {}
    currentZone.value.layout_config.areas = val
  }
})

const layoutEquipment = computed({
  get() {
    if (!currentZone.value) return []
    if (!currentZone.value.layout_config) return []
    return currentZone.value.layout_config.equipment || []
  },
  set(val) {
    if (!currentZone.value) return
    if (!currentZone.value.layout_config) currentZone.value.layout_config = {}
    currentZone.value.layout_config.equipment = val
  }
})

const availableTablesCount = computed(() => filteredTables.value.filter(t => t.status === 'available').length)
const occupiedTablesCount = computed(() => filteredTables.value.filter(t => t.status === 'occupied').length)
const billRequestedTablesCount = computed(() => filteredTables.value.filter(t => t.status === 'bill_requested').length)
const reservedTablesCount = computed(() => filteredTables.value.filter(t => t.status === 'reserved').length)

const zoneAreaM2 = (zone) => {
  const w = Number(zone.width_meters || 12.0)
  const h = Number(zone.height_meters || 8.0)
  return (w * h).toFixed(1)
}

const getAreaIcon = (type) => {
  const item = spaceAreaTypes.find(s => s.type === type)
  return item?.icon || 'tabler-layout'
}

const getStatusColor = (status) => {
  switch (status) {
    case 'available': return 'success'
    case 'occupied': return 'error'
    case 'bill_requested': return 'warning'
    case 'reserved': return 'info'
    default: return 'grey'
  }
}

// Datos computados para el Inspector
const selectedObjectName = computed(() => {
  if (!selectedObject.value) return ''
  return selectedObject.value.data?.name || selectedObject.value.data?.title || 'Objeto'
})

const selectedObjectIcon = computed(() => {
  if (!selectedObject.value) return 'tabler-box'
  if (selectedObject.value.kind === 'table') return 'tabler-table'
  if (selectedObject.value.kind === 'area') return getAreaIcon(selectedObject.value.data?.type)
  return selectedObject.value.data?.icon || 'tabler-flame'
})

const selectedObjectSubtext = computed(() => {
  if (!selectedObject.value) return ''
  if (selectedObject.value.kind === 'table') {
    return `Mesa • Capacidad: ${selectedObject.value.data?.capacity} pers. • ${selectedObject.value.data?.width}x${selectedObject.value.data?.height}px`
  }
  if (selectedObject.value.kind === 'area') {
    return `Área: ${Number(selectedObject.value.data?.w).toFixed(1)}m × ${Number(selectedObject.value.data?.h).toFixed(1)}m (${(Number(selectedObject.value.data?.w) * Number(selectedObject.value.data?.h)).toFixed(2)} m²)`
  }
  return `Dimensiones: ${Number(selectedObject.value.data?.w || 1).toFixed(1)}m × ${Number(selectedObject.value.data?.h || 1).toFixed(1)}m`
})

// ==========================================
// CARGA Y SINCRONIZACIÓN DE DATOS
// ==========================================
const fetchOverview = async (silent = false) => {
  if (!silent) loadingOverview.value = true
  try {
    const res = await axios.get('/dining-floor/overview')
    zones.value = res.data.zones || []
    tables.value = res.data.tables || []
    kitchenStations.value = res.data.kitchen_stations || []

    if (!selectedZoneIndex.value && zones.value.length) {
      selectedZoneIndex.value = zones.value[0].id
    }
  } catch (err) {
    if (!silent) toast.error('Error al cargar datos del restaurante.')
  } finally {
    if (!silent) loadingOverview.value = false
  }
}

// ==========================================
// CREACIÓN Y MANIPULACIÓN DE OBJETOS
// ==========================================

// 1. Agregar Mesa con 1 Clic desde Presets
const addTablePreset = async (preset) => {
  if (!currentZone.value) return
  submittingAction.value = true
  try {
    const existingCount = filteredTables.value.length
    const offsetIndex = existingCount % 8
    const gridX = 2 + (offsetIndex % 4) * 2
    const gridY = 2 + Math.floor(offsetIndex / 4) * 2

    const posX = snapToGrid.value ? gridX * cellPixelSize : 120 + offsetIndex * 30
    const posY = snapToGrid.value ? gridY * cellPixelSize : 120 + offsetIndex * 30

    const res = await axios.post('/dining-floor/tables', {
      dining_zone_id: currentZone.value.id,
      name: `Mesa ${existingCount + 1}`,
      capacity: preset.capacity,
      shape: preset.shape,
      width: preset.width,
      height: preset.height,
      pos_x: posX,
      pos_y: posY,
      rotation: 0,
    })

    toast.success(`${preset.title} agregada al plano.`)
    await fetchOverview(true)
    if (res.data?.table) {
      selectObject(res.data.table, 'table')
    }
  } catch (err) {
    toast.error('Error al agregar mesa.')
  } finally {
    submittingAction.value = false
  }
}

// 2. Agregar Mostrador
const addCounterItem = (counter) => {
  if (!currentZone.value) return
  const currentEq = [...layoutEquipment.value]
  const newCounter = {
    id: `counter_${Date.now()}`,
    name: counter.name,
    type: counter.type || 'counter',
    icon: counter.icon,
    x: 2,
    y: 3,
    w: counter.w || 3.0,
    h: counter.h || 1.0,
  }
  currentEq.push(newCounter)
  layoutEquipment.value = currentEq
  selectObject(newCounter, 'equipment')
  toast.success(`${counter.title} agregado al plano (${counter.w}m × ${counter.h}m).`)
}

// 3. Agregar Nevera / Refrigerador / Congelador
const addCoolerItem = (cooler) => {
  if (!currentZone.value) return
  const currentEq = [...layoutEquipment.value]
  const newCooler = {
    id: `cooler_${Date.now()}`,
    name: cooler.name,
    type: 'cooling',
    icon: cooler.icon,
    x: 2,
    y: 2,
    w: cooler.w || 1.5,
    h: cooler.h || 1.0,
  }
  currentEq.push(newCooler)
  layoutEquipment.value = currentEq
  selectObject(newCooler, 'equipment')
  toast.success(`${cooler.title} agregado al plano (${cooler.w}m × ${cooler.h}m).`)
}

// 4. Agregar Estación KDS Existente al Plano
const addStationToCanvas = (st) => {
  if (!currentZone.value) return
  const currentEq = [...layoutEquipment.value]
  const newStation = {
    id: `station_${st.id}_${Date.now()}`,
    station_id: st.id,
    name: st.name,
    type: 'station',
    icon: st.icon || 'tabler-flame',
    x: 1,
    y: 1,
    w: 2.0,
    h: 1.0,
  }
  currentEq.push(newStation)
  layoutEquipment.value = currentEq
  selectObject(newStation, 'equipment')
  toast.success(`Estación ${st.name} colocada en el plano.`)
}

// 5. Modal para Crear Nueva Estación de Cocina KDS
const openCreateStationModal = () => {
  createStationForm.name = ''
  createStationForm.code = ''
  createStationForm.color = '#ff9f43'
  createStationForm.icon = 'tabler-flame'
  createStationForm.width_m = 2.0
  createStationForm.height_m = 1.0
  showCreateStationModal.value = true
}

const submitCreateStation = async () => {
  if (!createStationForm.name.trim()) {
    toast.error('Indica el nombre de la estación.')
    return
  }

  const generatedCode = (createStationForm.code || createStationForm.name.substring(0, 5)).toUpperCase().replace(/\s+/g, '')

  submittingAction.value = true
  try {
    const res = await axios.post('/kitchen-stations', {
      name: createStationForm.name,
      code: generatedCode,
      color: createStationForm.color,
      icon: createStationForm.icon,
    })

    const newStationData = res.data?.data || res.data
    toast.success('¡Nueva estación de cocina creada con éxito!')
    showCreateStationModal.value = false

    const currentEq = [...layoutEquipment.value]
    const newCanvasItem = {
      id: `station_${newStationData?.id || Date.now()}`,
      station_id: newStationData?.id,
      name: createStationForm.name,
      type: 'station',
      icon: createStationForm.icon,
      x: 1,
      y: 1,
      w: Number(createStationForm.width_m || 2.0),
      h: Number(createStationForm.height_m || 1.0),
    }
    currentEq.push(newCanvasItem)
    layoutEquipment.value = currentEq
    selectObject(newCanvasItem, 'equipment')

    await fetchOverview(true)
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al crear estación de cocina.')
  } finally {
    submittingAction.value = false
  }
}

// 6. Modal para Crear Nueva Sección / Área Personalizada
const openCreateCustomAreaModal = () => {
  customAreaForm.name = ''
  customAreaForm.type = 'custom'
  customAreaForm.color = '#00cfe8'
  customAreaForm.w = 3.5
  customAreaForm.h = 2.5
  showCreateCustomAreaModal.value = true
}

const submitCreateCustomArea = () => {
  if (!customAreaForm.name.trim()) {
    toast.error('Indica el nombre de la sección.')
    return
  }

  const currentAreas = [...layoutAreas.value]
  const newArea = {
    id: `area_${Date.now()}`,
    name: customAreaForm.name,
    type: customAreaForm.type,
    color: customAreaForm.color,
    x: 1,
    y: 1,
    w: Number(customAreaForm.w || 3.0),
    h: Number(customAreaForm.h || 2.0),
  }
  currentAreas.push(newArea)
  layoutAreas.value = currentAreas
  selectObject(newArea, 'area')
  showCreateCustomAreaModal.value = false
  toast.success(`Sección "${customAreaForm.name}" añadida al plano (${customAreaForm.w}m × ${customAreaForm.h}m).`)
}

// 7. Agregar Área Arquitectónica Prediseñada
const addArchitecturalArea = (areaType) => {
  if (!currentZone.value) return
  const currentAreas = [...layoutAreas.value]
  const newArea = {
    id: `area_${Date.now()}`,
    type: areaType.type,
    name: areaType.title,
    x: 1,
    y: 1,
    w: Number(areaType.w || 3.0),
    h: Number(areaType.h || 2.0),
  }
  currentAreas.push(newArea)
  layoutAreas.value = currentAreas
  selectObject(newArea, 'area')
  toast.info(`${areaType.title} agregada. Ubícala en la cuadrícula.`)
}

// 8. Agregar Equipamiento Genérico
const addEquipmentItem = (equip) => {
  if (!currentZone.value) return
  const currentEq = [...layoutEquipment.value]
  const newEq = {
    id: `eq_${Date.now()}`,
    name: equip.title,
    icon: equip.icon,
    type: equip.type || 'equipment',
    x: 2,
    y: 2,
    w: Number(equip.w || 2.0),
    h: Number(equip.h || 1.0),
  }
  currentEq.push(newEq)
  layoutEquipment.value = currentEq
  selectObject(newEq, 'equipment')
  toast.info(`${equip.title} agregado al plano.`)
}

// Selección de Objeto
const selectObject = (data, kind) => {
  selectedObject.value = { kind, id: data.id, data }
}

const clearSelection = () => {
  selectedObject.value = null
}

// ==========================================
// DRAG AND DROP EN EL LIENZO MÉTRICO (CON SOPORTE DECIMAL)
// ==========================================
const handleTableDragStart = (table, event) => {
  draggedTable.value = table
  dragOffset.x = event.offsetX
  dragOffset.y = event.offsetY
  selectObject(table, 'table')
}

const handleAreaDragStart = (area, event) => {
  draggedArea.value = area
  dragOffset.x = event.offsetX
  dragOffset.y = event.offsetY
  selectObject(area, 'area')
}

const handleEquipmentDragStart = (eq, event) => {
  draggedEquipment.value = eq
  dragOffset.x = event.offsetX
  dragOffset.y = event.offsetY
  selectObject(eq, 'equipment')
}

const handleCanvasDrop = (event) => {
  const rect = event.currentTarget.getBoundingClientRect()
  const rawX = (event.clientX - rect.left - dragOffset.x) / zoomLevel.value
  const rawY = (event.clientY - rect.top - dragOffset.y) / zoomLevel.value

  if (draggedTable.value) {
    let finalX = Math.max(0, Math.min(canvasPixelWidth.value - (draggedTable.value.width || 60), rawX))
    let finalY = Math.max(0, Math.min(canvasPixelHeight.value - (draggedTable.value.height || 60), rawY))

    if (snapToGrid.value) {
      // Snap a medios metros (30px) respetando límite de planta
      finalX = Math.round(finalX / (cellPixelSize / 2)) * (cellPixelSize / 2)
      finalY = Math.round(finalY / (cellPixelSize / 2)) * (cellPixelSize / 2)
      finalX = Math.max(0, Math.min(canvasPixelWidth.value - (draggedTable.value.width || 60), finalX))
      finalY = Math.max(0, Math.min(canvasPixelHeight.value - (draggedTable.value.height || 60), finalY))
    }

    draggedTable.value.pos_x = finalX
    draggedTable.value.pos_y = finalY
    draggedTable.value = null
  }

  if (draggedArea.value) {
    let gridX = rawX / cellPixelSize
    let gridY = rawY / cellPixelSize

    if (snapToGrid.value) {
      // Snap a 0.5m
      gridX = Math.round(gridX * 2) / 2
      gridY = Math.round(gridY * 2) / 2
    } else {
      gridX = Math.round(gridX * 10) / 10
      gridY = Math.round(gridY * 10) / 10
    }

    gridX = Math.max(0, Math.min(currentZoneWidth.value - (draggedArea.value.w || 1), gridX))
    gridY = Math.max(0, Math.min(currentZoneHeight.value - (draggedArea.value.h || 1), gridY))

    draggedArea.value.x = gridX
    draggedArea.value.y = gridY
    draggedArea.value = null
  }

  if (draggedEquipment.value) {
    let gridX = rawX / cellPixelSize
    let gridY = rawY / cellPixelSize

    if (snapToGrid.value) {
      // Snap a 0.5m
      gridX = Math.round(gridX * 2) / 2
      gridY = Math.round(gridY * 2) / 2
    } else {
      gridX = Math.round(gridX * 10) / 10
      gridY = Math.round(gridY * 10) / 10
    }

    gridX = Math.max(0, Math.min(currentZoneWidth.value - (draggedEquipment.value.w || 1), gridX))
    gridY = Math.max(0, Math.min(currentZoneHeight.value - (draggedEquipment.value.h || 1), gridY))

    draggedEquipment.value.x = gridX
    draggedEquipment.value.y = gridY
    draggedEquipment.value = null
  }
}

// ==========================================
// CONTROLES DE OBJETO SELECCIONADO (REDIMENSIONAR / ROTAR / PAREDES)
// ==========================================
const rotateSelectedTable = (deg) => {
  if (selectedObject.value?.kind !== 'table') return
  const table = selectedObject.value.data
  table.rotation = ((Number(table.rotation || 0) + deg) % 360)
}

// Establecer dimensión métrica directa de mesa (decimales)
const setTableDimensionMeters = (dimension, meters) => {
  if (!selectedObject.value || selectedObject.value.kind !== 'table') return
  const m = Math.max(0.4, Math.min(25, Number(meters || 1.0)))
  selectedObject.value.data[dimension] = Math.round(m * cellPixelSize)
}

// Redimensionar mesa con saltos decimales (+/- 0.1m)
const resizeSelectedTable = (dimension, deltaMeters) => {
  if (!selectedObject.value || selectedObject.value.kind !== 'table') return
  const prop = dimension === 'w' ? 'width' : 'height'
  const currentPx = Number(selectedObject.value.data[prop] || 60)
  const currentM = currentPx / cellPixelSize
  const newM = Math.max(0.4, Math.min(25, Number((currentM + deltaMeters).toFixed(1))))
  selectedObject.value.data[prop] = Math.round(newM * cellPixelSize)
}

// Modificar número de personas / sillas de la mesa
const changeSelectedTableCapacity = (delta) => {
  if (!selectedObject.value || selectedObject.value.kind !== 'table') return
  const current = Number(selectedObject.value.data.capacity || 2)
  selectedObject.value.data.capacity = Math.max(1, Math.min(50, current + delta))
}

// Detección automática y manual de lados pegados a la pared
const isTableSideBlocked = (table, side) => {
  if (!table) return false

  // 1. Chequeo manual si el usuario bloqueó ese lado explícitamente
  if (table.blocked_sides && table.blocked_sides[side] !== undefined) {
    return Boolean(table.blocked_sides[side])
  }

  const px = Number(table.pos_x || 0)
  const py = Number(table.pos_y || 0)
  const pw = Number(table.width || 60)
  const ph = Number(table.height || 60)
  const zoneW = Number(canvasPixelWidth.value || 720)
  const zoneH = Number(canvasPixelHeight.value || 480)

  const wallThreshold = 25 // 25px de distancia al límite de pared

  // 2. Detección automática con el perímetro exterior de la planta
  if (side === 'top' && py <= wallThreshold) return true
  if (side === 'left' && px <= wallThreshold) return true
  if (side === 'right' && px + pw >= zoneW - wallThreshold) return true
  if (side === 'bottom' && py + ph >= zoneH - wallThreshold) return true

  // 3. Detección automática con áreas arquitectónicas de tipo "wall" (muros divisores)
  const walls = layoutAreas.value.filter(a => a.type === 'wall')
  for (const w of walls) {
    const wx1 = w.x * cellPixelSize
    const wy1 = w.y * cellPixelSize
    const wx2 = (w.x + w.w) * cellPixelSize
    const wy2 = (w.y + w.h) * cellPixelSize

    const overlapX = px < wx2 && (px + pw) > wx1
    const overlapY = py < wy2 && (py + ph) > wy1

    if (overlapX) {
      if (side === 'top' && Math.abs(py - wy2) <= wallThreshold) return true
      if (side === 'bottom' && Math.abs((py + ph) - wy1) <= wallThreshold) return true
    }
    if (overlapY) {
      if (side === 'left' && Math.abs(px - wx2) <= wallThreshold) return true
      if (side === 'right' && Math.abs((px + pw) - wx1) <= wallThreshold) return true
    }
  }

  return false
}

// Conmutar manualmente el estado de pared de un lado de la mesa
const toggleTableSideBlocked = (table, side) => {
  if (!table) return
  if (!table.blocked_sides) {
    table.blocked_sides = {
      top: isTableSideBlocked(table, 'top'),
      bottom: isTableSideBlocked(table, 'bottom'),
      left: isTableSideBlocked(table, 'left'),
      right: isTableSideBlocked(table, 'right'),
    }
  }
  table.blocked_sides[side] = !table.blocked_sides[side]
}

// Distribución geométrica dinámica de sillas según capacidad y paredes
const getTableSeats = (table) => {
  const cap = Math.max(1, Number(table.capacity || 2))
  const w = Number(table.width || 60)
  const h = Number(table.height || 60)
  const shape = table.shape || 'square'
  const seats = []

  const blocked = {
    top: isTableSideBlocked(table, 'top'),
    bottom: isTableSideBlocked(table, 'bottom'),
    left: isTableSideBlocked(table, 'left'),
    right: isTableSideBlocked(table, 'right'),
  }

  // A. Mesas Redondas: distribución polar circular
  if (shape === 'round') {
    const rx = w / 2 + 8
    const ry = h / 2 + 8
    const zoneW = Number(canvasPixelWidth.value || 720)
    const zoneH = Number(canvasPixelHeight.value || 480)

    for (let i = 0; i < cap; i++) {
      const angle = (2 * Math.PI * i) / cap - Math.PI / 2
      const sx = w / 2 + rx * Math.cos(angle)
      const sy = h / 2 + ry * Math.sin(angle)

      const absX = Number(table.pos_x || 0) + sx
      const absY = Number(table.pos_y || 0) + sy

      const hitsTopWall = absY <= 15 && blocked.top
      const hitsBottomWall = absY >= zoneH - 15 && blocked.bottom
      const hitsLeftWall = absX <= 15 && blocked.left
      const hitsRightWall = absX >= zoneW - 15 && blocked.right

      if (!hitsTopWall && !hitsBottomWall && !hitsLeftWall && !hitsRightWall) {
        seats.push({
          id: `seat_${i}`,
          x: sx - 6,
          y: sy - 6,
          side: 'round',
        })
      }
    }
    return seats
  }

  // B. Módulo de Barra: taburetes colocados en el frente/lado de clientes
  if (shape === 'bar') {
    let clientSide = 'bottom'
    if (blocked.bottom && !blocked.top) clientSide = 'top'
    else if (blocked.top && !blocked.bottom) clientSide = 'bottom'
    else if (blocked.left && !blocked.right) clientSide = 'right'
    else if (blocked.right && !blocked.left) clientSide = 'left'

    if (!blocked[clientSide]) {
      for (let i = 0; i < cap; i++) {
        const offset = w * ((i + 0.5) / cap)
        const sy = clientSide === 'bottom' ? h + 2 : -14
        seats.push({
          id: `bar_seat_${i}`,
          x: offset - 6,
          y: sy,
          side: clientSide,
          isBarStool: true,
        })
      }
    }
    return seats
  }

  // C. Mesas Cuadradas y Rectangulares: distribución por 4 lados
  let topCount = 0
  let bottomCount = 0
  let leftCount = 0
  let rightCount = 0

  if (cap === 1) {
    bottomCount = 1
  } else if (cap === 2) {
    if (w >= h) {
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
    // 5, 6, 8, 10, 12...
    if (w >= h) {
      leftCount = 1
      rightCount = 1
      const remaining = cap - 2
      topCount = Math.ceil(remaining / 2)
      bottomCount = Math.floor(remaining / 2)
    } else {
      topCount = 1
      bottomCount = 1
      const remaining = cap - 2
      leftCount = Math.ceil(remaining / 2)
      rightCount = Math.floor(remaining / 2)
    }
  }

  // SI UN LADO ESTÁ PEGADO A LA PARED, SE REMUEVEN LAS SILLAS DE ESE LADO
  if (blocked.top) topCount = 0
  if (blocked.bottom) bottomCount = 0
  if (blocked.left) leftCount = 0
  if (blocked.right) rightCount = 0

  // Coordenadas calculadas
  for (let i = 0; i < topCount; i++) {
    const x = w * ((i + 0.5) / topCount) - 6
    seats.push({ id: `top_${i}`, x, y: -14, side: 'top' })
  }
  for (let i = 0; i < bottomCount; i++) {
    const x = w * ((i + 0.5) / bottomCount) - 6
    seats.push({ id: `bottom_${i}`, x, y: h + 2, side: 'bottom' })
  }
  for (let i = 0; i < leftCount; i++) {
    const y = h * ((i + 0.5) / leftCount) - 6
    seats.push({ id: `left_${i}`, x: -14, y, side: 'left' })
  }
  for (let i = 0; i < rightCount; i++) {
    const y = h * ((i + 0.5) / rightCount) - 6
    seats.push({ id: `right_${i}`, x: w + 2, y, side: 'right' })
  }

  return seats
}

// Redimensionar Estaciones, Mostradores y Neveras (con decimales)
const resizeSelectedEquipment = (dimension, delta) => {
  if (selectedObject.value?.kind !== 'equipment') return
  const eq = selectedObject.value.data
  if (dimension === 'w') {
    const currentVal = Number(eq.w || 1.0)
    eq.w = Math.max(0.3, Math.min(currentZoneWidth.value - eq.x, Math.round((currentVal + delta) * 10) / 10))
  } else {
    const currentVal = Number(eq.h || 1.0)
    eq.h = Math.max(0.3, Math.min(currentZoneHeight.value - eq.y, Math.round((currentVal + delta) * 10) / 10))
  }
}

// Girar Estaciones, Mostradores y Neveras
const rotateSelectedEquipment = () => {
  if (selectedObject.value?.kind !== 'equipment') return
  const eq = selectedObject.value.data
  const temp = eq.w || 2.0
  eq.w = eq.h || 1.0
  eq.h = temp
}

const resizeSelectedArea = (dimension, delta) => {
  if (selectedObject.value?.kind !== 'area') return
  const area = selectedObject.value.data
  if (dimension === 'w') {
    const currentVal = Number(area.w || 1.0)
    area.w = Math.max(0.5, Math.min(currentZoneWidth.value - area.x, Math.round((currentVal + delta) * 10) / 10))
  } else {
    const currentVal = Number(area.h || 1.0)
    area.h = Math.max(0.5, Math.min(currentZoneHeight.value - area.y, Math.round((currentVal + delta) * 10) / 10))
  }
}

const duplicateSelectedTable = async () => {
  if (selectedObject.value?.kind !== 'table') return
  const t = selectedObject.value.data
  submittingAction.value = true
  try {
    await axios.post('/dining-floor/tables', {
      dining_zone_id: t.dining_zone_id || currentZone.value.id,
      name: `${t.name} (Copia)`,
      capacity: t.capacity,
      shape: t.shape,
      width: t.width,
      height: t.height,
      pos_x: Math.min(canvasPixelWidth.value - 80, Number(t.pos_x || 50) + 40),
      pos_y: Math.min(canvasPixelHeight.value - 80, Number(t.pos_y || 50) + 40),
      rotation: t.rotation || 0,
    })
    toast.success('Mesa duplicada.')
    await fetchOverview(true)
  } catch (err) {
    toast.error('Error al duplicar mesa.')
  } finally {
    submittingAction.value = false
  }
}

const deleteSelectedObject = async () => {
  if (!selectedObject.value) return

  if (selectedObject.value.kind === 'table') {
    const tableId = selectedObject.value.id
    if (!confirm('¿Eliminar esta mesa del plano?')) return
    submittingAction.value = true
    try {
      await axios.delete(`/dining-floor/tables/${tableId}`)
      toast.info('Mesa eliminada.')
      clearSelection()
      await fetchOverview(true)
    } catch (err) {
      toast.error('Error al eliminar mesa.')
    } finally {
      submittingAction.value = false
    }
  } else if (selectedObject.value.kind === 'area') {
    const areaId = selectedObject.value.id
    layoutAreas.value = layoutAreas.value.filter(a => a.id !== areaId)
    clearSelection()
    toast.info('Sección retirada del plano.')
  } else if (selectedObject.value.kind === 'equipment') {
    const eqId = selectedObject.value.id
    layoutEquipment.value = layoutEquipment.value.filter(e => e.id !== eqId)
    clearSelection()
    toast.info('Elemento retirado del plano.')
  }
}

// ==========================================
// GUARDAR DISEÑO COMPLETO (MESAS + ÁREAS + METROS)
// ==========================================
const saveFullRestaurantDesign = async () => {
  if (!currentZone.value) return
  savingLayout.value = true
  try {
    // 1. Guardar distribución de mesas
    const tablePayload = filteredTables.value.map(t => ({
      id: t.id,
      pos_x: t.pos_x,
      pos_y: t.pos_y,
      width: t.width,
      height: t.height,
      rotation: t.rotation,
      capacity: t.capacity,
    }))
    if (tablePayload.length) {
      await axios.post('/dining-floor/layout', { tables: tablePayload })
    }

    // 2. Guardar dimensiones decimales y layout_config de la zona
    await axios.put(`/dining-floor/zones/${currentZone.value.id}`, {
      name: currentZone.value.name,
      background_style: currentZone.value.background_style || 'wooden',
      width_meters: currentZoneWidth.value,
      height_meters: currentZoneHeight.value,
      layout_config: {
        areas: layoutAreas.value,
        equipment: layoutEquipment.value,
      },
    })

    toast.success('¡Diseño del restaurante guardado exitosamente!')
  } catch (err) {
    toast.error('Error al guardar el diseño.')
  } finally {
    savingLayout.value = false
  }
}

// Modificar Dimensiones de Zona al Vuelo (con decimales)
const changeDimension = (dimension, delta) => {
  if (!currentZone.value) return
  if (dimension === 'width') {
    const currentVal = Number(currentZone.value.width_meters || 12.0)
    currentZone.value.width_meters = Math.max(2.0, Math.min(100.0, Math.round((currentVal + delta) * 10) / 10))
  } else {
    const currentVal = Number(currentZone.value.height_meters || 8.0)
    currentZone.value.height_meters = Math.max(2.0, Math.min(100.0, Math.round((currentVal + delta) * 10) / 10))
  }
}

// ==========================================
// MODALES Y FORMULARIOS DE ZONA Y MESAS
// ==========================================
const openCreateZoneModal = () => {
  isEditingZone.value = false
  zoneForm.id = null
  zoneForm.name = `Planta ${zones.value.length + 1}`
  zoneForm.width_meters = 12.0
  zoneForm.height_meters = 8.0
  zoneForm.background_style = 'wooden'
  showZoneModal.value = true
}

const openEditZoneModal = () => {
  if (!currentZone.value) return
  isEditingZone.value = true
  zoneForm.id = currentZone.value.id
  zoneForm.name = currentZone.value.name
  zoneForm.width_meters = currentZoneWidth.value
  zoneForm.height_meters = currentZoneHeight.value
  zoneForm.background_style = currentZone.value.background_style || 'wooden'
  showZoneModal.value = true
}

const submitZoneForm = async () => {
  if (!zoneForm.name.trim()) {
    toast.error('Indica un nombre para la zona.')
    return
  }

  submittingAction.value = true
  try {
    if (isEditingZone.value && zoneForm.id) {
      await axios.put(`/dining-floor/zones/${zoneForm.id}`, {
        name: zoneForm.name,
        width_meters: Number(zoneForm.width_meters),
        height_meters: Number(zoneForm.height_meters),
        background_style: zoneForm.background_style,
      })
      toast.success('Dimensiones de la zona actualizadas.')
    } else {
      const res = await axios.post('/dining-floor/zones', {
        name: zoneForm.name,
        width_meters: Number(zoneForm.width_meters),
        height_meters: Number(zoneForm.height_meters),
        background_style: zoneForm.background_style,
        layout_config: {
          areas: [
            { id: 'kitchen_1', type: 'kitchen', name: 'Cocina', x: 1, y: 1, w: 4.0, h: 3.0 },
            { id: 'pass_1', type: 'pass_counter', name: 'Mostrador de Pase', x: 1, y: 4, w: 3.0, h: 1.0 },
          ],
        },
      })
      toast.success('Nueva zona creada.')
      if (res.data?.zone?.id) {
        selectedZoneIndex.value = res.data.zone.id
      }
    }
    showZoneModal.value = false
    await fetchOverview()
  } catch (err) {
    toast.error('Error al guardar zona.')
  } finally {
    submittingAction.value = false
  }
}

const submitDeleteZone = async () => {
  if (!currentZone.value) return
  submittingAction.value = true
  try {
    await axios.delete(`/dining-floor/zones/${currentZone.value.id}`)
    toast.info('Zona eliminada.')
    showDeleteZoneConfirm.value = false
    selectedZoneIndex.value = null
    clearSelection()
    await fetchOverview()
  } catch (err) {
    toast.error('Error al eliminar zona.')
  } finally {
    submittingAction.value = false
  }
}

const submitClearZone = async () => {
  if (!currentZone.value) return
  submittingAction.value = true
  try {
    // Vaciar mesas
    await Promise.all(filteredTables.value.map(t => axios.delete(`/dining-floor/tables/${t.id}`)))
    // Vaciar áreas y equipamiento
    layoutAreas.value = []
    layoutEquipment.value = []
    await axios.put(`/dining-floor/zones/${currentZone.value.id}`, {
      name: currentZone.value.name,
      layout_config: { areas: [], equipment: [] },
    })
    toast.success('Lienzo vaciado para diseñar desde cero.')
    showClearZoneModal.value = false
    clearSelection()
    await fetchOverview()
  } catch (err) {
    toast.error('Error al vaciar zona.')
  } finally {
    submittingAction.value = false
  }
}

const openEditTableModal = (table) => {
  editingTable.value = table
  editTableForm.id = table.id
  editTableForm.name = table.name
  editTableForm.capacity = table.capacity
  editTableForm.shape = table.shape || 'square'
  editTableForm.width = table.width || 60
  editTableForm.height = table.height || 60
  editTableForm.width_meters = Number(((table.width || 60) / cellPixelSize).toFixed(1))
  editTableForm.height_meters = Number(((table.height || 60) / cellPixelSize).toFixed(1))
  editTableForm.rotation = table.rotation || 0
  showEditTableModal.value = true
}

const submitUpdateTable = async () => {
  if (!editTableForm.id) return
  submittingAction.value = true
  try {
    await axios.put(`/dining-floor/tables/${editTableForm.id}`, {
      dining_zone_id: currentZone.value.id,
      name: editTableForm.name,
      capacity: editTableForm.capacity,
      shape: editTableForm.shape,
      width: editTableForm.width,
      height: editTableForm.height,
      rotation: editTableForm.rotation,
    })
    toast.success('Mesa actualizada.')
    showEditTableModal.value = false
    await fetchOverview(true)
  } catch (err) {
    toast.error('Error al actualizar mesa.')
  } finally {
    submittingAction.value = false
  }
}

// ==========================================
// SALÓN EN VIVO (OPERACIÓN)
// ==========================================
const selectAndInteractTable = (table) => {
  selectedTable.value = table
  openOrderForm.guests_count = table.capacity || 2
  showTableActionModal.value = true
}

const handleSelectStation = (station) => {
  if (!station) return
  toast.info(`Estación activa: ${station.name || 'Cocina'}`)
}

const submitOpenOrder = async () => {
  if (!selectedTable.value) return
  submittingAction.value = true
  try {
    await axios.post(`/dining-floor/tables/${selectedTable.value.id}/open-order`, {
      guests_count: openOrderForm.guests_count,
    })
    toast.success('Mesa abierta con éxito.')
    showTableActionModal.value = false
    await fetchOverview()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al abrir mesa.')
  } finally {
    submittingAction.value = false
  }
}

const releaseTableAction = async () => {
  if (!selectedTable.value) return
  submittingAction.value = true
  try {
    await axios.post(`/dining-floor/tables/${selectedTable.value.id}/release`)
    toast.info('Mesa liberada.')
    showTableActionModal.value = false
    await fetchOverview()
  } catch (err) {
    toast.error('Error al liberar mesa.')
  } finally {
    submittingAction.value = false
  }
}

const zoomIn = () => {
  zoomLevel.value = Math.min(1.8, zoomLevel.value + 0.1)
}

const zoomOut = () => {
  zoomLevel.value = Math.max(0.6, zoomLevel.value - 0.1)
}

// ==========================================
// MANIPULACIÓN DIRECTA (RESIZE EN LIENZO) Y COLISIONES
// ==========================================
let activeResize = null

const startResize = (obj, kind, event) => {
  activeResize = {
    obj,
    kind,
    startX: event.clientX,
    startY: event.clientY,
    initW: kind === 'table' ? Number(obj.width || 60) : Number(obj.w || 1) * cellPixelSize,
    initH: kind === 'table' ? Number(obj.height || 60) : Number(obj.h || 1) * cellPixelSize,
  }
  window.addEventListener('mousemove', onResizeMove)
  window.addEventListener('mouseup', stopResize)
}

const onResizeMove = (event) => {
  if (!activeResize) return
  const { obj, kind, startX, startY, initW, initH } = activeResize
  const dx = (event.clientX - startX) / zoomLevel.value
  const dy = (event.clientY - startY) / zoomLevel.value

  const stepPx = snapToGrid.value ? (cellPixelSize * 0.5) : (cellPixelSize * 0.1)
  const rawW = Math.max(cellPixelSize * 0.5, initW + dx)
  const rawH = Math.max(cellPixelSize * 0.5, initH + dy)

  const snappedW = Math.round(rawW / stepPx) * stepPx
  const snappedH = Math.round(rawH / stepPx) * stepPx

  if (kind === 'table') {
    const maxW = canvasPixelWidth.value - Number(obj.pos_x || 0)
    const maxH = canvasPixelHeight.value - Number(obj.pos_y || 0)
    obj.width = Math.max(30, Math.min(maxW, Math.round(snappedW)))
    obj.height = Math.max(30, Math.min(maxH, Math.round(snappedH)))
  } else {
    const maxWm = currentZoneWidth.value - Number(obj.x || 0)
    const maxHm = currentZoneHeight.value - Number(obj.y || 0)
    obj.w = Math.max(0.4, Math.min(maxWm, Number((snappedW / cellPixelSize).toFixed(1))))
    obj.h = Math.max(0.4, Math.min(maxHm, Number((snappedH / cellPixelSize).toFixed(1))))
  }
}

const stopResize = () => {
  activeResize = null
  window.removeEventListener('mousemove', onResizeMove)
  window.removeEventListener('mouseup', stopResize)
}

// Invertir ancho y alto de un mostrador o equipo rápidamente
const swapEquipmentDimensions = (eq) => {
  if (!eq) return
  const oldW = eq.w
  eq.w = eq.h
  eq.h = oldW
}

// Detectar si una mesa se superpone con otra mesa o sale de los límites de planta
const isTableColliding = (table) => {
  const x1 = Number(table.pos_x || 0)
  const y1 = Number(table.pos_y || 0)
  const w1 = Number(table.width || 60)
  const h1 = Number(table.height || 60)

  if (x1 < 0 || y1 < 0 || (x1 + w1) > canvasPixelWidth.value + 1 || (y1 + h1) > canvasPixelHeight.value + 1) {
    return true
  }

  for (const other of filteredTables.value) {
    if (other.id === table.id) continue
    const x2 = Number(other.pos_x || 0)
    const y2 = Number(other.pos_y || 0)
    const w2 = Number(other.width || 60)
    const h2 = Number(other.height || 60)

    const overlap = x1 < x2 + w2 - 4 && x1 + w1 - 4 > x2 && y1 < y2 + h2 - 4 && y1 + h1 - 4 > y2
    if (overlap) return true
  }

  return false
}

// Polling silencioso solo en Modo En Vivo
let floorPollInterval = null

onMounted(async () => {
  await fetchOverview()
  floorPollInterval = setInterval(() => {
    if (viewMode.value === 'live') {
      fetchOverview(true)
    }
  }, 5000)
})

onUnmounted(() => {
  if (floorPollInterval) clearInterval(floorPollInterval)
  stopResize()
})
</script>

<style scoped>
.restaurant-builder-view {
  min-height: 85vh;
}

.zone-tabs :deep(.v-tab) {
  text-transform: none;
  font-weight: 500;
}

.builder-toolbox-card {
  max-height: 720px;
  overflow-y: auto;
}

.toolbox-content {
  max-height: 660px;
  overflow-y: auto;
}

.builder-canvas-wrapper {
  background-color: #0b0c10;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.canvas-header-bar {
  background: #14161f;
  border-bottom: 2px solid rgba(255, 255, 255, 0.12);
  color: #ffffff;
}

.canvas-info-text {
  color: #f1f5f9 !important;
  letter-spacing: 0.3px;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
}

.canvas-scroll-container {
  overflow: auto;
  max-height: 700px;
  min-height: 540px;
  background-color: #090a0f;
  padding: 44px 30px 40px 52px !important;
}

/* LIENZO MÉTRICO CON CUADRÍCULA Y BORDE ESTRUCTURAL ARQUITECTÓNICO */
.metric-grid-canvas {
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.85);
  border: 3px solid #ffb300;
  border-radius: 8px;
  user-select: none;
  transition: transform 0.1s ease;
  position: relative;
}

/* FONDOS SEGÚN ESTILO CON CUADRÍCULA DE ALTA DEFINICIÓN */
.zone-bg-wooden {
  background-color: #3b200b;
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.18) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.18) 1px, transparent 1px);
  background-size: 60px 60px;
}

.zone-bg-grid {
  background-color: #10141f;
  background-image:
    linear-gradient(to right, rgba(0, 207, 232, 0.25) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(0, 207, 232, 0.25) 1px, transparent 1px);
  background-size: 60px 60px;
}

.zone-bg-grass {
  background-color: #122b19;
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.16) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.16) 1px, transparent 1px);
  background-size: 60px 60px;
}

.zone-bg-concrete {
  background-color: #222630;
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.16) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.16) 1px, transparent 1px);
  background-size: 60px 60px;
}

/* REGLAS MÉTRICAS EXTERIORES CON PÍLDORAS DE ALTO CONTRASTE */
.ruler-top {
  position: absolute;
  top: -34px;
  left: 0;
  right: 0;
  height: 34px;
  pointer-events: none;
  z-index: 30;
}

.ruler-origin-badge {
  position: absolute;
  left: 0;
  top: 2px;
  font-size: 11px;
  font-weight: 900;
  color: #ffd54f !important;
  background: #0f172a;
  padding: 2px 7px;
  border-radius: 4px;
  border: 1px solid #ffd54f;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.9);
  letter-spacing: 0.5px;
  transform: translateX(-50%);
}

.ruler-mark-x {
  position: absolute;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  top: 2px;
}

.ruler-left {
  position: absolute;
  top: 0;
  left: -46px;
  bottom: 0;
  width: 46px;
  pointer-events: none;
  z-index: 30;
}

.ruler-mark-y {
  position: absolute;
  transform: translateY(-50%);
  display: flex;
  flex-direction: row;
  align-items: center;
  left: 2px;
}

.ruler-badge {
  font-size: 11px;
  font-weight: 900;
  color: #ffffff !important;
  background: #090d16;
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px solid rgba(255, 255, 255, 0.45);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.9);
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.ruler-tick-top {
  width: 2px;
  height: 7px;
  background: #ffb300;
  margin-top: 2px;
}

.ruler-tick-left {
  height: 2px;
  width: 7px;
  background: #ffb300;
  margin-left: 3px;
}

/* MARCADORES INTERNOS DENTRO DEL LIENZO */
.inner-grid-markers {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 1;
}

.inner-grid-tag-x {
  position: absolute;
  top: 4px;
  font-size: 10px;
  font-weight: 900;
  color: #ffd54f !important;
  background: rgba(0, 0, 0, 0.8);
  padding: 1px 5px;
  border-radius: 3px;
  border: 1px solid rgba(255, 213, 79, 0.5);
  text-shadow: 0 1px 2px #000;
}

.inner-grid-tag-y {
  position: absolute;
  left: 4px;
  font-size: 10px;
  font-weight: 900;
  color: #ffd54f !important;
  background: rgba(0, 0, 0, 0.8);
  padding: 1px 5px;
  border-radius: 3px;
  border: 1px solid rgba(255, 213, 79, 0.5);
  text-shadow: 0 1px 2px #000;
}

.zone-boundary-tag {
  position: absolute;
  bottom: 6px;
  right: 10px;
  background: rgba(15, 23, 42, 0.92);
  color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 5px;
  border: 1px solid rgba(255, 179, 0, 0.6);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
  pointer-events: none;
  z-index: 20;
}

/* CAPAS ARQUITECTÓNICAS (COCINA, DEPÓSITO, TERRAZA, SECCIONES) */
.architectural-area-block {
  border-radius: 6px;
  cursor: grab;
  transition: box-shadow 0.2s ease, outline 0.2s ease;
  overflow: hidden;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.5);
  user-select: none;
}

.architectural-area-block:active {
  cursor: grabbing;
}

.architectural-area-block.is-selected {
  outline: 3px solid #00cfe8 !important;
  box-shadow: 0 0 25px rgba(0, 207, 232, 0.9) !important;
  z-index: 15;
}

.area-header-pill {
  background: rgba(12, 14, 22, 0.95);
  border-bottom: 2px solid rgba(255, 255, 255, 0.2);
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);
}

.area-name-text {
  color: #ffffff !important;
  font-size: 12px;
  letter-spacing: 0.3px;
  text-shadow: 0 1px 3px #000000;
}

.area-dimension-tag {
  background: #ff9f43;
  color: #000000 !important;
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 3px;
  letter-spacing: 0.2px;
  text-shadow: none;
}

.area-center-watermark {
  position: absolute;
  inset: 26px 0 0 0;
  pointer-events: none;
  opacity: 0.45;
  text-transform: uppercase;
}

.watermark-name {
  font-size: 15px;
  letter-spacing: 2px;
  color: #ffffff;
  text-shadow: 0 1px 4px #000000, 0 0 8px rgba(0, 0, 0, 0.9);
}

.watermark-surface {
  font-size: 12px;
  letter-spacing: 1px;
  color: #ffd54f;
  text-shadow: 0 1px 3px #000000;
}

.area-type-kitchen {
  background: repeating-linear-gradient(45deg, #ded9ce, #ded9ce 12px, #f2ede4 12px, #f2ede4 24px);
  border: 3px solid #e74c3c;
  color: #111;
}

.area-type-storage {
  background: #283747;
  border: 2px solid #95a5a6;
  color: #fff;
}

.area-type-bar_zone {
  background: #1b263b;
  border: 2px solid #3498db;
  color: #fff;
}

.area-type-terrace {
  background: rgba(39, 174, 96, 0.35);
  border: 2px dashed #2ecc71;
  color: #fff;
}

.area-type-wall {
  background: #1c1c1c;
  border: 2px solid #ffb300;
  color: #fff;
}

.area-type-entrance {
  background: #5b2c6f;
  border: 2px dashed #bb8fce;
  color: #fff;
}

.area-type-custom {
  background: rgba(0, 207, 232, 0.2);
  border: 2px dashed #00cfe8;
  color: #fff;
}

/* MOSTRADORES, NEVERAS, CONGELADORES Y ESTACIONES KDS (ALTA DEFINICIÓN Y COLOR VIVO) */
.equipment-block {
  border-radius: 6px;
  color: #ffffff !important;
  cursor: grab;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.7);
  transition: box-shadow 0.2s ease, outline 0.2s ease;
  user-select: none;
  border: 2px solid rgba(255, 255, 255, 0.35);
  z-index: 12;
}

.equipment-block:active {
  cursor: grabbing;
}

.equipment-block.is-selected {
  outline: 3px solid #00cfe8 !important;
  box-shadow: 0 0 25px rgba(0, 207, 232, 0.95) !important;
  z-index: 20;
}

.equipment-title-text {
  color: #ffffff !important;
  font-size: 13px;
  font-weight: 900;
  letter-spacing: 0.3px;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9), 0 0 8px rgba(0, 0, 0, 0.8);
}

.equipment-measure-pill {
  background: rgba(0, 0, 0, 0.85);
  color: #ffe082 !important;
  font-size: 11px;
  font-weight: 900;
  padding: 1px 7px;
  border-radius: 4px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  letter-spacing: 0.4px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.7);
}

.equip-kind-counter {
  background: linear-gradient(135deg, #0288d1, #01579b) !important;
  border: 2px solid #81d4fa !important;
}

.equip-kind-pass_counter {
  background: linear-gradient(135deg, #e65100, #bf360c) !important;
  border: 2px dashed #ffe082 !important;
  box-shadow: 0 0 14px rgba(255, 152, 0, 0.5) !important;
}

.equip-kind-station {
  background: linear-gradient(135deg, #c2185b, #880e4f) !important;
  border: 2px solid #ff80ab !important;
  box-shadow: 0 0 14px rgba(233, 30, 99, 0.5) !important;
}

.equip-kind-cooling {
  background: linear-gradient(135deg, #00838f, #006064) !important;
  border: 2px solid #4dd0e1 !important;
  box-shadow: 0 0 15px rgba(0, 229, 255, 0.5) !important;
}

.equip-kind-equipment {
  background: linear-gradient(135deg, #455a64, #263238) !important;
  border: 2px solid #cfd8dc !important;
}

/* MESAS DE COMEDOR (TEXTO DESTACADO Y CONTRASTE MÁXIMO) */
.floor-table-node {
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  transition: transform 0.1s ease, box-shadow 0.2s ease;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.65);
  user-select: none;
  z-index: 10;
  border: 2px solid rgba(255, 255, 255, 0.4);
}

.floor-table-node:active {
  cursor: grabbing;
}

.floor-table-node.is-selected {
  outline: 3px solid #00cfe8;
  outline-offset: 3px;
  box-shadow: 0 0 25px rgba(0, 207, 232, 0.95);
  z-index: 22;
}

.table-title {
  font-size: 13px;
  font-weight: 900;
  color: #ffffff !important;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.95), 0 0 6px rgba(0, 0, 0, 0.8);
  letter-spacing: 0.3px;
}

.table-capacity {
  background: rgba(0, 0, 0, 0.8);
  color: #ffffff !important;
  font-size: 10px;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  white-space: nowrap;
}

.floor-table-node.shape-square { border-radius: 8px; }
.floor-table-node.shape-round { border-radius: 50%; }
.floor-table-node.shape-rectangle { border-radius: 10px; }
.floor-table-node.shape-bar { border-radius: 6px; }

.floor-table-node.status-available {
  background: linear-gradient(135deg, #16a34a, #15803d);
  color: #fff;
}

.floor-table-node.status-occupied {
  background: linear-gradient(135deg, #dc2626, #b91c1c);
  color: #fff;
}

.floor-table-node.status-bill_requested {
  background: linear-gradient(135deg, #ea580c, #c2410c);
  color: #fff;
}

.seat-dot {
  position: absolute;
  width: 12px;
  height: 12px;
  background: #f8fafc;
  border: 2px solid #334155;
  border-radius: 50%;
  pointer-events: none;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.6);
  z-index: 11;
  transition: all 0.15s ease;
}

.seat-dot.seat-stool {
  background: #fbbf24;
  border: 2px solid #78350f;
  border-radius: 3px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.7);
}

.wall-edge-indicator {
  position: absolute;
  pointer-events: none;
  background: repeating-linear-gradient(45deg, #1e293b, #1e293b 4px, #f59e0b 4px, #f59e0b 8px);
  border: 1px solid #ffd54f;
  z-index: 7;
  opacity: 0.95;
  box-shadow: 0 0 6px rgba(245, 158, 11, 0.6);
}
.edge-top { top: 0; left: 0; right: 0; height: 4px; }
.edge-bottom { bottom: 0; left: 0; right: 0; height: 4px; }
.edge-left { top: 0; bottom: 0; left: 0; width: 4px; }
.edge-right { top: 0; bottom: 0; right: 0; width: 4px; }

.equipment-block.is-vertical-eq .equipment-content-inner {
  transform: rotate(-90deg);
  white-space: nowrap;
}

.ruler-badge-end {
  background: #e91e63 !important;
  border-color: #ffffff !important;
}

/* MANIPULACIÓN DIRECTA EN EL LIENZO (TIRADOR RESIZE + MINI BARRA FLOTANTE) */
.canvas-resize-handle {
  position: absolute;
  right: -6px;
  bottom: -6px;
  width: 14px;
  height: 14px;
  background: #00cfe8;
  border: 2px solid #ffffff;
  border-radius: 3px;
  cursor: nwse-resize;
  z-index: 35;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.85);
}

.canvas-resize-handle:hover {
  transform: scale(1.2);
  background: #ffd54f;
}

.floating-object-toolbar {
  position: absolute;
  top: -36px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(15, 23, 42, 0.96);
  padding: 3px 6px;
  border-radius: 8px;
  border: 1px solid rgba(0, 207, 232, 0.65);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.8);
  z-index: 40;
  white-space: nowrap;
}

/* IDENTIDAD VISUAL DE MOBILIARIO EN MODO CONSTRUCTOR */
.builder-furniture-wood {
  background: linear-gradient(135deg, #8d6e63, #5d4037) !important;
  border: 2px solid #d7ccc8 !important;
  color: #ffffff;
}

.builder-furniture-bar {
  background: linear-gradient(135deg, #37474f, #263238) !important;
  border: 2px solid #90a4ae !important;
  box-shadow: inset 0 4px 0 rgba(255, 213, 79, 0.45), 0 6px 16px rgba(0, 0, 0, 0.65) !important;
  color: #ffffff;
}

.table-status-dot {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 9px;
  height: 9px;
  border-radius: 50%;
  border: 1.5px solid #ffffff;
  z-index: 12;
}
.dot-available { background: #22c55e; }
.dot-occupied { background: #ef4444; }
.dot-bill_requested { background: #f59e0b; }
.dot-reserved { background: #38bdf8; }

.floor-table-node.is-colliding {
  outline: 3px dashed #ff4757 !important;
  outline-offset: 2px;
  box-shadow: 0 0 18px rgba(255, 71, 87, 0.95) !important;
}

.live-map-card {
  min-height: 600px;
  background-color: #12121a;
  border-radius: 8px;
}
</style>
