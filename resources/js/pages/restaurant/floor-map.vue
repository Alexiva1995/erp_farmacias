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

        <!-- Barra Métrica de la Zona Activa -->
        <div v-if="currentZone && viewMode === 'builder'" class="d-flex flex-wrap align-center justify-space-between gap-3 mt-3 pt-2 border-t text-caption">
          <div class="d-flex align-center flex-wrap gap-3">
            <span class="d-flex align-center gap-1">
              <v-icon size="16" color="primary">tabler-ruler-2</v-icon>
              <strong>Dimensiones del Local:</strong>
              {{ currentZoneWidth }} m × {{ currentZoneHeight }} m
              <v-chip size="x-small" color="primary" variant="flat" class="font-weight-bold ml-1">
                {{ currentZoneArea }} m²
              </v-chip>
            </span>

            <span class="text-medium-emphasis">|</span>

            <span>
              <strong>Mesas:</strong> {{ filteredTables.length }}
            </span>
            <span>
              <strong>Áreas Arquitectónicas:</strong> {{ layoutAreas.length }}
            </span>
          </div>

          <!-- Controles Rápidos de Medidas y Snap -->
          <div class="d-flex align-center gap-3">
            <div class="d-flex align-center gap-1">
              <span class="text-medium-emphasis">Ancho (m):</span>
              <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="changeDimension('width', -1)" />
              <strong class="mx-1">{{ currentZoneWidth }}m</strong>
              <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="changeDimension('width', 1)" />
            </div>

            <div class="d-flex align-center gap-1">
              <span class="text-medium-emphasis">Largo (m):</span>
              <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="changeDimension('height', -1)" />
              <strong class="mx-1">{{ currentZoneHeight }}m</strong>
              <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="changeDimension('height', 1)" />
            </div>

            <v-btn
              size="x-small"
              variant="tonal"
              :color="snapToGrid ? 'primary' : 'secondary'"
              prepend-icon="tabler-magnet"
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
      <v-col cols="12" md="3" lg="2">
        <v-card class="builder-toolbox-card" elevation="2" border>
          <v-tabs v-model="toolboxTab" density="compact" grow color="primary" class="border-b">
            <v-tab value="tables">
              <v-icon size="16">tabler-table</v-icon>
              <span class="text-caption ml-1">Mesas</span>
            </v-tab>
            <v-tab value="spaces">
              <v-icon size="16">tabler-layout-grid</v-icon>
              <span class="text-caption ml-1">Espacios</span>
            </v-tab>
            <v-tab value="equip">
              <v-icon size="16">tabler-flame</v-icon>
              <span class="text-caption ml-1">Equipos</span>
            </v-tab>
          </v-tabs>

          <v-card-text class="pa-2 toolbox-content">
            <!-- PESTAÑA A: CATÁLOGO DE MESAS (1 CLIC PARA AÑADIR A ESCALA) -->
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

            <!-- PESTAÑA B: PINTAR ESPACIOS / ÁREAS DEL LOCAL -->
            <div v-show="toolboxTab === 'spaces'">
              <div class="text-caption font-weight-bold text-medium-emphasis mb-2 text-uppercase">
                Áreas y Divisiones:
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
                      {{ areaType.desc }}
                    </span>
                  </div>
                </v-btn>
              </div>
            </div>

            <!-- PESTAÑA C: EQUIPAMIENTO OPERATIVO (COCINA, POS, PASE) -->
            <div v-show="toolboxTab === 'equip'">
              <div class="text-caption font-weight-bold text-medium-emphasis mb-2 text-uppercase">
                Mobiliario y Equipos:
              </div>

              <div class="d-flex flex-column gap-2">
                <v-btn
                  v-for="equip in equipmentItems"
                  :key="equip.id"
                  variant="tonal"
                  color="warning"
                  block
                  class="justify-start text-left py-2 h-auto"
                  @click="addEquipmentItem(equip)"
                >
                  <v-avatar size="32" color="warning" variant="flat" class="mr-2 rounded">
                    <v-icon size="18">{{ equip.icon }}</v-icon>
                  </v-avatar>
                  <div class="d-flex flex-column text-left">
                    <span class="font-weight-bold text-caption">{{ equip.title }}</span>
                    <span class="text-caption text-medium-emphasis" style="font-size: 11px;">
                      {{ equip.desc }}
                    </span>
                  </div>
                </v-btn>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- ÁREA CENTRAL: LIENZO MÉTRICO INTERACTIVO -->
      <v-col cols="12" md="9" lg="10">
        <v-card class="builder-canvas-wrapper overflow-hidden" elevation="2">
          <!-- Barra de Controles de Lienzo -->
          <div class="canvas-header-bar d-flex align-center justify-space-between px-3 py-2 bg-surface-variant text-caption">
            <div class="d-flex align-center gap-2">
              <v-icon size="16">tabler-grid-dots</v-icon>
              <span>Escala: 1 celda = 1 metro ({{ cellPixelSize }} px)</span>
              <span class="text-medium-emphasis">• Arrastra cualquier elemento para ubicarlo</span>
            </div>

            <div class="d-flex align-center gap-1">
              <v-btn icon="tabler-zoom-in" size="x-small" variant="text" @click="zoomIn" />
              <v-btn icon="tabler-zoom-out" size="x-small" variant="text" @click="zoomOut" />
              <v-btn icon="tabler-refresh" size="x-small" variant="text" title="Reset Zoom" @click="zoomLevel = 1.0" />
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
              <!-- REGLAS MÉTRICAS SUPERIOR E IZQUIERDA -->
              <div class="ruler-top">
                <span
                  v-for="m in currentZoneWidth"
                  :key="`m-top-${m}`"
                  class="ruler-mark-x text-caption"
                  :style="{ left: `${(m - 1) * cellPixelSize + 4}px` }"
                >
                  {{ m }}m
                </span>
              </div>
              <div class="ruler-left">
                <span
                  v-for="m in currentZoneHeight"
                  :key="`m-left-${m}`"
                  class="ruler-mark-y text-caption"
                  :style="{ top: `${(m - 1) * cellPixelSize + 2}px` }"
                >
                  {{ m }}m
                </span>
              </div>

              <!-- 1. CAPA DE ÁREAS ARQUITECTÓNICAS PINTADAS (Cocina, Depósito, Terraza, Muros) -->
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
                }"
                draggable="true"
                @dragstart="handleAreaDragStart(area, $event)"
                @click.stop="selectObject(area, 'area')"
              >
                <div class="area-badge d-flex align-center gap-1 pa-1">
                  <v-icon size="14">{{ getAreaIcon(area.type) }}</v-icon>
                  <span class="font-weight-bold text-caption">{{ area.name }}</span>
                  <span class="text-caption opacity-75">({{ area.w }}m×{{ area.h }}m)</span>
                </div>
              </div>

              <!-- 2. CAPA DE EQUIPAMIENTO (Pase, Caja, KDS) -->
              <div
                v-for="eq in layoutEquipment"
                :key="eq.id"
                class="equipment-block position-absolute d-flex flex-column align-center justify-center"
                :class="{ 'is-selected': selectedObject?.id === eq.id && selectedObject?.kind === 'equipment' }"
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
                <v-icon size="18">{{ eq.icon || 'tabler-box' }}</v-icon>
                <span class="font-weight-bold text-caption text-center">{{ eq.name }}</span>
              </div>

              <!-- 3. CAPA DE MESAS (CON SNAP Y ESTILOS) -->
              <div
                v-for="table in filteredTables"
                :key="table.id"
                class="floor-table-node position-absolute"
                :class="[
                  `shape-${table.shape || 'square'}`,
                  `status-${table.status || 'available'}`,
                  { 'is-selected': selectedObject?.id === table.id && selectedObject?.kind === 'table' }
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
                <div class="table-inner d-flex flex-column align-center justify-center text-center">
                  <span class="table-title font-weight-bold">{{ table.name }}</span>
                  <span class="table-capacity text-caption">Cap: {{ table.capacity }}</span>
                </div>

                <!-- Sillas visuales -->
                <div class="seat-dot seat-top" />
                <div class="seat-dot seat-bottom" />
                <div v-if="table.capacity > 2" class="seat-dot seat-left" />
                <div v-if="table.capacity > 3" class="seat-dot seat-right" />
              </div>
            </div>
          </div>
        </v-card>

        <!-- PANEL INFERIOR INSPECTOR: PROPIEDADES DEL OBJETO SELECCIONADO -->
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

                <!-- Controles de Edición -->
                <div class="d-flex align-center flex-wrap gap-2">
                  <!-- Si es Mesa -->
                  <template v-if="selectedObject.kind === 'table'">
                    <span class="text-caption text-medium-emphasis">Rotar:</span>
                    <v-btn size="x-small" variant="tonal" color="secondary" @click="rotateSelectedTable(-15)">-15°</v-btn>
                    <v-btn size="x-small" variant="tonal" color="secondary" @click="rotateSelectedTable(15)">+15°</v-btn>
                    <v-btn size="x-small" variant="tonal" color="secondary" @click="rotateSelectedTable(90)">90°</v-btn>

                    <v-divider vertical class="mx-1" />

                    <span class="text-caption text-medium-emphasis">Tamaño:</span>
                    <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="scaleSelectedTable(-10)" />
                    <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="scaleSelectedTable(10)" />

                    <v-divider vertical class="mx-1" />

                    <v-btn
                      size="small"
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-pencil"
                      @click="openEditTableModal(selectedObject.data)"
                    >
                      Editar Mesa
                    </v-btn>

                    <v-btn
                      size="small"
                      variant="tonal"
                      color="secondary"
                      prepend-icon="tabler-copy"
                      @click="duplicateSelectedTable"
                    >
                      Duplicar
                    </v-btn>
                  </template>

                  <!-- Si es Área Arquitectónica (Cocina, Depósito, etc.) -->
                  <template v-else-if="selectedObject.kind === 'area'">
                    <span class="text-caption text-medium-emphasis">Ancho (m):</span>
                    <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="resizeSelectedArea('w', -1)" />
                    <strong>{{ selectedObject.data.w }}m</strong>
                    <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="resizeSelectedArea('w', 1)" />

                    <span class="text-caption text-medium-emphasis ml-2">Largo (m):</span>
                    <v-btn icon="tabler-minus" size="x-small" variant="tonal" @click="resizeSelectedArea('h', -1)" />
                    <strong>{{ selectedObject.data.h }}m</strong>
                    <v-btn icon="tabler-plus" size="x-small" variant="tonal" @click="resizeSelectedArea('h', 1)" />
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

    <!-- MODAL: GESTIÓN DE PLANTA / ZONA (NOMBRE, DIMENSIONES Y ESTILO) -->
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

            <!-- Medidas en Metros y Cálculo de m² -->
            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="zoneForm.width_meters"
                  type="number"
                  min="3"
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
                  min="3"
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
              Superficie calculada: <strong>{{ (Number(zoneForm.width_meters || 12) * Number(zoneForm.height_meters || 8)).toFixed(0) }} m²</strong>
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
              <v-col cols="4">
                <v-text-field v-model.number="editTableForm.width" type="number" label="Ancho (px)" density="compact" variant="outlined" />
              </v-col>
              <v-col cols="4">
                <v-text-field v-model.number="editTableForm.height" type="number" label="Alto (px)" density="compact" variant="outlined" />
              </v-col>
              <v-col cols="4">
                <v-text-field v-model.number="editTableForm.rotation" type="number" label="Giro (°)" density="compact" variant="outlined" />
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
            ¿Deseas eliminar todas las mesas y áreas de <strong>{{ currentZone?.name }}</strong> para empezar desde un lienzo en blanco?
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
    <RestaurantSimulatorWidget @updated="fetchOverview(true)" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'
import RestaurantSimulatorWidget from '@/components/restaurant/RestaurantSimulatorWidget.vue'
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
  width_meters: 12,
  height_meters: 8,
})

const editTableForm = reactive({
  id: null,
  name: '',
  capacity: 4,
  shape: 'square',
  width: 70,
  height: 70,
  rotation: 0,
})

const openOrderForm = reactive({
  guests_count: 2,
})

// ==========================================
// CATÁLOGO DE OBJETOS PRE-CONFIGURADOS
// ==========================================
const tablePresets = [
  { id: 't2', title: 'Mesa 2 Personas', capacity: 2, shape: 'square', width: 60, height: 60, dimMeters: '1x1', icon: 'tabler-square' },
  { id: 't4', title: 'Mesa 4 Personas', capacity: 4, shape: 'square', width: 72, height: 72, dimMeters: '1.2x1.2', icon: 'tabler-grid-pattern' },
  { id: 't4r', title: 'Mesa Redonda 4P', capacity: 4, shape: 'round', width: 72, height: 72, dimMeters: 'Ø 1.2', icon: 'tabler-circle' },
  { id: 't6', title: 'Mesa 6 Personas', capacity: 6, shape: 'rectangle', width: 110, height: 72, dimMeters: '1.8x1.2', icon: 'tabler-rectangle' },
  { id: 't8', title: 'Mesa Imperial 8P', capacity: 8, shape: 'rectangle', width: 140, height: 72, dimMeters: '2.4x1.2', icon: 'tabler-rectangle-vertical' },
  { id: 'bar', title: 'Módulo de Barra', capacity: 1, shape: 'bar', width: 50, height: 50, dimMeters: '0.8x0.8', icon: 'tabler-glass-full' },
]

const spaceAreaTypes = [
  { id: 'kitchen', title: 'Zona de Cocina', type: 'kitchen', w: 4, h: 3, btnColor: 'error', icon: 'tabler-tools-kitchen-2', desc: 'Piso cerámico blanco' },
  { id: 'pass_counter', title: 'Mostrador de Pase', type: 'pass_counter', w: 3, h: 1, btnColor: 'warning', icon: 'tabler-bell', desc: 'Entrega de platos a meseros' },
  { id: 'storage', title: 'Depósito / Almacén', type: 'storage', w: 3, h: 2, btnColor: 'secondary', icon: 'tabler-archive', desc: 'Insumos y neveras' },
  { id: 'bar_zone', title: 'Zona de Barra / Bar', type: 'bar_zone', w: 4, h: 2, btnColor: 'info', icon: 'tabler-glass', desc: 'Coctelería y tragos' },
  { id: 'terrace', title: 'Terraza / Jardín', type: 'terrace', w: 4, h: 3, btnColor: 'success', icon: 'tabler-plant', desc: 'Piso deck / césped' },
  { id: 'wall', title: 'Muro / Pared', type: 'wall', w: 3, h: 1, btnColor: 'grey-darken-2', icon: 'tabler-wall', desc: 'División estructural' },
  { id: 'entrance', title: 'Entrada / Puerta', type: 'entrance', w: 2, h: 1, btnColor: 'purple', icon: 'tabler-door-enter', desc: 'Recepción y bienvenida' },
]

const equipmentItems = [
  { id: 'eq_grill', title: 'Parrilla & Fuego', name: 'Parrilla', w: 2, h: 1, icon: 'tabler-flame', desc: 'Estación de cocción KDS' },
  { id: 'eq_prep', title: 'Mesa de Corte', name: 'Mesa Corte', w: 2, h: 1, icon: 'tabler-tools-kitchen', desc: 'Preparación y ensaladas' },
  { id: 'eq_pos', title: 'Caja / Punto POS', name: 'Caja POS', w: 1, h: 1, icon: 'tabler-device-laptop', desc: 'Cobro y facturación' },
  { id: 'eq_fridge', title: 'Cámara / Nevera', name: 'Nevera', w: 1, h: 1, icon: 'tabler-snowflake', desc: 'Refrigeración de alimentos' },
]

// ==========================================
// COMPUTADAS
// ==========================================
const currentZone = computed(() => {
  return zones.value.find(z => z.id === selectedZoneIndex.value) || zones.value[0] || null
})

const currentZoneWidth = computed(() => Math.max(4, Number(currentZone.value?.width_meters || 12)))
const currentZoneHeight = computed(() => Math.max(4, Number(currentZone.value?.height_meters || 8)))
const currentZoneArea = computed(() => currentZoneWidth.value * currentZoneHeight.value)

const canvasPixelWidth = computed(() => currentZoneWidth.value * cellPixelSize)
const canvasPixelHeight = computed(() => currentZoneHeight.value * cellPixelSize)

const filteredTables = computed(() => {
  if (!currentZone.value) return []
  return tables.value.filter(t => t.dining_zone_id === currentZone.value.id)
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
  const w = Number(zone.width_meters || 12)
  const h = Number(zone.height_meters || 8)
  return (w * h).toFixed(0)
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
    return `Capacidad: ${selectedObject.value.data?.capacity} pers. • ${selectedObject.value.data?.width}x${selectedObject.value.data?.height}px`
  }
  if (selectedObject.value.kind === 'area') {
    return `Área de ${selectedObject.value.data?.w}m × ${selectedObject.value.data?.h}m (${selectedObject.value.data?.w * selectedObject.value.data?.h} m²)`
  }
  return `Dimensiones: ${selectedObject.value.data?.w}m × ${selectedObject.value.data?.h}m`
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

// 2. Agregar Área Arquitectónica (Cocina, Pase, Depósito, Muro)
const addArchitecturalArea = (areaType) => {
  if (!currentZone.value) return
  const currentAreas = [...layoutAreas.value]
  const newArea = {
    id: `area_${Date.now()}`,
    type: areaType.type,
    name: areaType.title,
    x: 1,
    y: 1,
    w: areaType.w,
    h: areaType.h,
  }
  currentAreas.push(newArea)
  layoutAreas.value = currentAreas
  selectObject(newArea, 'area')
  toast.info(`${areaType.title} agregada. Ubícala en la cuadrícula.`)
}

// 3. Agregar Equipamiento
const addEquipmentItem = (equip) => {
  if (!currentZone.value) return
  const currentEq = [...layoutEquipment.value]
  const newEq = {
    id: `eq_${Date.now()}`,
    name: equip.title,
    icon: equip.icon,
    x: 2,
    y: 2,
    w: equip.w,
    h: equip.h,
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
// DRAG AND DROP EN EL LIENZO MÉTRICO
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
      finalX = Math.round(finalX / (cellPixelSize / 2)) * (cellPixelSize / 2)
      finalY = Math.round(finalY / (cellPixelSize / 2)) * (cellPixelSize / 2)
    }

    draggedTable.value.pos_x = finalX
    draggedTable.value.pos_y = finalY
    draggedTable.value = null
  }

  if (draggedArea.value) {
    let gridX = Math.round(rawX / cellPixelSize)
    let gridY = Math.round(rawY / cellPixelSize)
    gridX = Math.max(0, Math.min(currentZoneWidth.value - draggedArea.value.w, gridX))
    gridY = Math.max(0, Math.min(currentZoneHeight.value - draggedArea.value.h, gridY))

    draggedArea.value.x = gridX
    draggedArea.value.y = gridY
    draggedArea.value = null
  }

  if (draggedEquipment.value) {
    let gridX = Math.round(rawX / cellPixelSize)
    let gridY = Math.round(rawY / cellPixelSize)
    gridX = Math.max(0, Math.min(currentZoneWidth.value - draggedEquipment.value.w, gridX))
    gridY = Math.max(0, Math.min(currentZoneHeight.value - draggedEquipment.value.h, gridY))

    draggedEquipment.value.x = gridX
    draggedEquipment.value.y = gridY
    draggedEquipment.value = null
  }
}

// ==========================================
// CONTROLES DE OBJETO SELECCIONADO
// ==========================================
const rotateSelectedTable = (deg) => {
  if (selectedObject.value?.kind !== 'table') return
  const table = selectedObject.value.data
  table.rotation = ((Number(table.rotation || 0) + deg) % 360)
}

const scaleSelectedTable = (delta) => {
  if (selectedObject.value?.kind !== 'table') return
  const table = selectedObject.value.data
  table.width = Math.max(40, Math.min(300, (Number(table.width || 60) + delta)))
  table.height = Math.max(40, Math.min(300, (Number(table.height || 60) + delta)))
}

const resizeSelectedArea = (dimension, delta) => {
  if (selectedObject.value?.kind !== 'area') return
  const area = selectedObject.value.data
  if (dimension === 'w') {
    area.w = Math.max(1, Math.min(currentZoneWidth.value - area.x, area.w + delta))
  } else {
    area.h = Math.max(1, Math.min(currentZoneHeight.value - area.y, area.h + delta))
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
    toast.info('Área retirada del plano.')
  } else if (selectedObject.value.kind === 'equipment') {
    const eqId = selectedObject.value.id
    layoutEquipment.value = layoutEquipment.value.filter(e => e.id !== eqId)
    clearSelection()
    toast.info('Equipo retirado.')
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
    }))
    if (tablePayload.length) {
      await axios.post('/dining-floor/layout', { tables: tablePayload })
    }

    // 2. Guardar dimensiones y layout_config de la zona
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

// Modificar Dimensiones de Zona al Vuelo
const changeDimension = (dimension, delta) => {
  if (!currentZone.value) return
  if (dimension === 'width') {
    currentZone.value.width_meters = Math.max(4, Math.min(100, currentZoneWidth.value + delta))
  } else {
    currentZone.value.height_meters = Math.max(4, Math.min(100, currentZoneHeight.value + delta))
  }
}

// ==========================================
// MODALES Y FORMULARIOS DE ZONA Y MESAS
// ==========================================
const openCreateZoneModal = () => {
  isEditingZone.value = false
  zoneForm.id = null
  zoneForm.name = `Planta ${zones.value.length + 1}`
  zoneForm.width_meters = 12
  zoneForm.height_meters = 8
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
        width_meters: zoneForm.width_meters,
        height_meters: zoneForm.height_meters,
        background_style: zoneForm.background_style,
      })
      toast.success('Dimensiones de la zona actualizadas.')
    } else {
      const res = await axios.post('/dining-floor/zones', {
        name: zoneForm.name,
        width_meters: zoneForm.width_meters,
        height_meters: zoneForm.height_meters,
        background_style: zoneForm.background_style,
        layout_config: {
          areas: [
            { id: 'kitchen_1', type: 'kitchen', name: 'Cocina', x: 1, y: 1, w: 4, h: 3 },
            { id: 'pass_1', type: 'pass_counter', name: 'Mostrador de Pase', x: 1, y: 4, w: 4, h: 1 },
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
    toast.error('Error al abrir mesa.')
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
  background-color: #12121a;
  border-radius: 8px;
}

.canvas-scroll-container {
  overflow: auto;
  max-height: 680px;
  min-height: 520px;
  background-color: #0f1016;
}

/* LIENZO MÉTRICO CON CUADRÍCULA */
.metric-grid-canvas {
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  user-select: none;
  transition: transform 0.1s ease;
}

/* FONDOS SEGÚN ESTILO */
.zone-bg-wooden {
  background-color: #432812;
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.08) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
  background-size: 60px 60px;
}

.zone-bg-grid {
  background-color: #191c28;
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.12) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.12) 1px, transparent 1px);
  background-size: 60px 60px;
}

.zone-bg-grass {
  background-color: #1b3824;
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 60px 60px;
}

.zone-bg-concrete {
  background-color: #2b2e38;
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.08) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
  background-size: 60px 60px;
}

/* MARCAS REGLA MÉTRICA */
.ruler-top {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 18px;
  pointer-events: none;
}

.ruler-mark-x {
  position: absolute;
  font-size: 10px;
  color: rgba(255, 255, 255, 0.4);
  font-weight: bold;
}

.ruler-left {
  position: absolute;
  top: 0;
  left: 0;
  bottom: 0;
  width: 24px;
  pointer-events: none;
}

.ruler-mark-y {
  position: absolute;
  font-size: 10px;
  color: rgba(255, 255, 255, 0.4);
  font-weight: bold;
}

/* CAPAS ARQUITECTÓNICAS */
.architectural-area-block {
  border-radius: 6px;
  cursor: grab;
  transition: box-shadow 0.2s ease, outline 0.2s ease;
  overflow: hidden;
}

.architectural-area-block:active {
  cursor: grabbing;
}

.area-type-kitchen {
  background: repeating-linear-gradient(45deg, #ded9ce, #ded9ce 12px, #f2ede4 12px, #f2ede4 24px);
  border: 3px solid #e74c3c;
  color: #111;
}

.area-type-pass_counter {
  background: #d35400;
  border: 2px dashed #f39c12;
  color: #fff;
}

.area-type-storage {
  background: #34495e;
  border: 2px solid #7f8c8d;
  color: #fff;
}

.area-type-bar_zone {
  background: #2c3e50;
  border: 2px solid #3498db;
  color: #fff;
}

.area-type-terrace {
  background: rgba(46, 204, 113, 0.25);
  border: 2px dashed #2ecc71;
  color: #fff;
}

.area-type-wall {
  background: #222;
  border: 2px solid #111;
  color: #fff;
}

.area-type-entrance {
  background: #8e44ad;
  border: 2px dashed #9b59b6;
  color: #fff;
}

.area-badge {
  background: rgba(0, 0, 0, 0.65);
  color: #fff;
  border-radius: 4px;
  display: inline-flex;
}

.architectural-area-block.is-selected {
  outline: 3px solid #00cfe8;
  box-shadow: 0 0 20px rgba(0, 207, 232, 0.7);
}

/* EQUIPAMIENTO */
.equipment-block {
  background: #2c3e50;
  border: 2px solid #f1c40f;
  border-radius: 6px;
  color: #fff;
  cursor: grab;
}

.equipment-block.is-selected {
  outline: 3px solid #00cfe8;
}

/* MESAS */
.floor-table-node {
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  transition: transform 0.1s ease, box-shadow 0.2s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
  z-index: 10;
}

.floor-table-node:active {
  cursor: grabbing;
}

.floor-table-node.is-selected {
  outline: 3px solid #00cfe8;
  outline-offset: 3px;
  box-shadow: 0 0 20px rgba(0, 207, 232, 0.8);
}

.floor-table-node.shape-square { border-radius: 8px; }
.floor-table-node.shape-round { border-radius: 50%; }
.floor-table-node.shape-rectangle { border-radius: 10px; }
.floor-table-node.shape-bar { border-radius: 6px; }

.floor-table-node.status-available {
  background: linear-gradient(135deg, #28c76f, #1f9d55);
  color: #fff;
}

.floor-table-node.status-occupied {
  background: linear-gradient(135deg, #ea5455, #bd3435);
  color: #fff;
}

.floor-table-node.status-bill_requested {
  background: linear-gradient(135deg, #ff9f43, #d9771e);
  color: #fff;
}

.seat-dot {
  position: absolute;
  width: 12px;
  height: 12px;
  background: #e2e8f0;
  border: 2px solid #475569;
  border-radius: 50%;
  pointer-events: none;
}

.seat-top { top: -14px; }
.seat-bottom { bottom: -14px; }
.seat-left { left: -14px; }
.seat-right { right: -14px; }

.live-map-card {
  min-height: 600px;
  background-color: #12121a;
  border-radius: 8px;
}
</style>
