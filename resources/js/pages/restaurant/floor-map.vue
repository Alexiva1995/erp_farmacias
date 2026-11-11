<template>
  <div class="dining-floor-view">
    <!-- Barra Superior de Control y Zonas -->
    <v-card class="mb-4" elevation="1">
      <v-card-text class="py-3">
        <div class="d-flex flex-wrap align-center justify-space-between gap-3">
          <!-- Selector y Gestión de Zonas -->
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
                <v-chip
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  class="ml-1"
                >
                  {{ getTablesCountByZone(zone.id) }}
                </v-chip>
              </v-tab>
            </v-tabs>

            <div class="d-flex align-center gap-1">
              <!-- Botón Nueva Zona -->
              <v-btn
                icon="tabler-plus"
                size="small"
                variant="tonal"
                color="primary"
                title="Crear Nueva Zona"
                @click="openCreateZoneModal"
              />

              <!-- Botón Editar Zona Actual -->
              <v-btn
                v-if="currentZone"
                icon="tabler-pencil"
                size="small"
                variant="text"
                color="secondary"
                title="Editar Zona Actual"
                @click="openEditZoneModal"
              />

              <!-- Botón Eliminar Zona Actual -->
              <v-btn
                v-if="currentZone && zones.length > 1"
                icon="tabler-trash"
                size="small"
                variant="text"
                color="error"
                title="Eliminar Zona Actual"
                @click="showDeleteZoneConfirm = true"
              />
            </div>
          </div>

          <!-- Modos de Vista & Acciones Principales -->
          <div class="d-flex align-center flex-wrap gap-2">
            <!-- Alternador de Modos: Editor 2D (Diseñar) vs Restaurante en Vivo (Operación) -->
            <v-btn-toggle
              v-model="viewMode"
              mandatory
              density="compact"
              color="primary"
              rounded="lg"
            >
              <v-btn value="2d" prepend-icon="tabler-layout-dashboard">
                Editor / Diseñar Salón
              </v-btn>
              <v-btn value="live" prepend-icon="tabler-chef-hat">
                Salón en Vivo
              </v-btn>
            </v-btn-toggle>

            <!-- Acciones específicas del Modo Diseñador 2D -->
            <template v-if="viewMode === '2d'">
              <v-btn
                color="primary"
                variant="flat"
                size="small"
                prepend-icon="tabler-plus"
                :disabled="!currentZone"
                @click="openCreateTableModal"
              >
                Nueva Mesa
              </v-btn>

              <v-btn
                color="success"
                variant="flat"
                size="small"
                prepend-icon="tabler-device-floppy"
                :loading="savingLayout"
                :disabled="!filteredTables.length"
                @click="saveCurrentLayout"
              >
                Guardar Plano
              </v-btn>

              <!-- Menú de Acciones de Limpieza / Plantillas -->
              <v-menu location="bottom end">
                <template #activator="{ props: menuProps }">
                  <v-btn
                    icon="tabler-dots-vertical"
                    variant="tonal"
                    size="small"
                    color="secondary"
                    v-bind="menuProps"
                    title="Más opciones de diseño"
                  />
                </template>
                <v-list density="compact">
                  <v-list-item
                    prepend-icon="tabler-trash-x"
                    class="text-error"
                    :disabled="!filteredTables.length"
                    @click="showClearZoneModal = true"
                  >
                    <v-list-item-title>Vaciar Mesas de Esta Zona</v-list-item-title>
                  </v-list-item>
                </v-list>
              </v-menu>
            </template>

            <!-- Acciones específicas del Modo En Vivo -->
            <template v-else>
              <v-btn
                color="primary"
                variant="tonal"
                size="small"
                prepend-icon="tabler-plus"
                :disabled="!currentZone"
                @click="openCreateTableModal"
              >
                Nueva Mesa
              </v-btn>
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

        <!-- Barra informativa contextual del Diseñador -->
        <v-alert
          v-if="viewMode === '2d'"
          density="compact"
          variant="tonal"
          color="info"
          icon="tabler-info-circle"
          class="mt-3 mb-0 text-caption"
        >
          <strong>Modo Diseñador de Restaurante:</strong> Arrastra las mesas libremente para replicar la distribución física de tu local.
          Haz clic en cualquier mesa para editar su nombre, número de sillas, dimensiones o rotarla. Pulsa <strong>Guardar Plano</strong> al terminar.
        </v-alert>
      </v-card-text>
    </v-card>

    <!-- Resumen Rápido de Estados (Visible en Salón en Vivo o como referencia) -->
    <v-row v-if="viewMode === 'live'" class="mb-4" dense>
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

    <!-- Canvas Principal: Diseñador 2D o Restaurante en Vivo -->
    <v-card class="floor-canvas-card position-relative overflow-hidden mb-4" elevation="2">
      <!-- MODO SALÓN EN VIVO (2.5D) -->
      <OvercookedLiveMap
        v-if="viewMode === 'live'"
        :tables="filteredTables"
        :stations="kitchenStations"
        :selected-table-id="selectedTable?.id"
        @select-table="selectAndInteractTable"
        @select-station="handleSelectStation"
      />

      <!-- MODO EDITOR 2D / PLANO INTERACTIVO -->
      <div
        v-show="viewMode === '2d'"
        class="floor-2d-canvas pa-4"
        :class="currentZoneBackgroundClass"
        @dragover.prevent
        @drop="handleDropTable"
        @click.self="clearEditingSelection"
      >
        <!-- Rejilla de Fondo y Regla Visual -->
        <div class="canvas-watermark text-caption">
          Zona: {{ currentZone?.name || 'Salón' }} • Arrastra mesas para diseñar
        </div>

        <!-- Nodos de Mesa con Drag & Drop y Estilos de Forma -->
        <div
          v-for="table in filteredTables"
          :key="table.id"
          class="floor-table-node"
          :class="[
            `shape-${table.shape || 'square'}`,
            `status-${table.status || 'available'}`,
            { 'is-editing-selected': editingTable?.id === table.id }
          ]"
          :style="{
            left: `${table.pos_x}px`,
            top: `${table.pos_y}px`,
            width: `${table.width || 80}px`,
            height: `${table.height || 80}px`,
            transform: `rotate(${table.rotation || 0}deg)`
          }"
          draggable="true"
          @dragstart="handleDragStart(table, $event)"
          @click.stop="handleTableClickInDesigner(table)"
        >
          <div class="table-inner d-flex flex-column align-center justify-center text-center">
            <span class="table-title font-weight-bold">{{ table.name }}</span>
            <span class="table-capacity text-caption">Cap: {{ table.capacity }}</span>
            <span v-if="table.current_order" class="table-amount font-weight-bold text-caption mt-1">
              ${{ Number(table.current_order.total_amount_usd || 0).toFixed(2) }}
            </span>
          </div>

          <!-- Puntos / Sillas Visuales Alrededor -->
          <div class="seat-dot seat-top" />
          <div class="seat-dot seat-bottom" />
          <div v-if="table.capacity > 2" class="seat-dot seat-left" />
          <div v-if="table.capacity > 3" class="seat-dot seat-right" />
        </div>

        <!-- Estado Vacío si la Zona no tiene Mesas -->
        <div
          v-if="!filteredTables.length && !loadingOverview"
          class="canvas-empty-state d-flex flex-column align-center justify-center py-12"
        >
          <v-icon size="56" color="secondary" class="mb-3">tabler-layout-grid-add</v-icon>
          <h3 class="text-h6 font-weight-bold mb-1">Esta zona no tiene mesas todavía</h3>
          <p class="text-body-2 text-medium-emphasis mb-4 text-center" style="max-width: 420px;">
            Diseña el salón de tu restaurante agregando mesas cuadradas, redondas, barras o rectangulares.
          </p>
          <v-btn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openCreateTableModal"
          >
            Agregar Primera Mesa
          </v-btn>
        </div>
      </div>
    </v-card>

    <!-- PANEL FLOTANTE DE PROPIEDADES DE MESA (EN MODO DISEÑADOR 2D) -->
    <v-expand-transition>
      <v-card
        v-if="viewMode === '2d' && editingTable"
        class="mb-4"
        elevation="3"
        border
      >
        <v-card-text class="py-3 px-4">
          <div class="d-flex flex-wrap align-center justify-space-between gap-3">
            <div class="d-flex align-center gap-2">
              <v-avatar color="primary" variant="tonal" rounded size="36">
                <v-icon>tabler-table</v-icon>
              </v-avatar>
              <div>
                <div class="text-subtitle-2 font-weight-bold">{{ editingTable.name }}</div>
                <div class="text-caption text-medium-emphasis">
                  Capacidad: {{ editingTable.capacity }} pers. • Forma: {{ getShapeLabel(editingTable.shape) }}
                </div>
              </div>
            </div>

            <!-- Controles Rápidos de Rotación y Dimensiones -->
            <div class="d-flex align-center flex-wrap gap-2">
              <!-- Rotación rápida -->
              <span class="text-caption text-medium-emphasis mr-1">Rotación:</span>
              <v-btn
                size="small"
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-rotate-clockwise-2"
                @click="rotateEditingTable(-15)"
              >
                -15°
              </v-btn>
              <v-btn
                size="small"
                variant="tonal"
                color="secondary"
                append-icon="tabler-rotate-clockwise"
                @click="rotateEditingTable(15)"
              >
                +15°
              </v-btn>
              <v-btn
                size="small"
                variant="tonal"
                color="secondary"
                @click="rotateEditingTable(90)"
              >
                90°
              </v-btn>

              <v-divider vertical class="mx-1" />

              <!-- Tamaño rápido -->
              <span class="text-caption text-medium-emphasis mr-1">Tamaño:</span>
              <v-btn
                icon="tabler-minus"
                size="small"
                variant="tonal"
                color="secondary"
                title="Reducir tamaño"
                @click="scaleEditingTable(-10)"
              />
              <v-btn
                icon="tabler-plus"
                size="small"
                variant="tonal"
                color="secondary"
                title="Aumentar tamaño"
                @click="scaleEditingTable(10)"
              />

              <v-divider vertical class="mx-1" />

              <!-- Acciones de Mesa -->
              <v-btn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-pencil"
                @click="openEditTableModal(editingTable)"
              >
                Editar Propiedades
              </v-btn>

              <v-btn
                size="small"
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-copy"
                :loading="submittingAction"
                @click="duplicateEditingTable"
              >
                Duplicar
              </v-btn>

              <v-btn
                size="small"
                variant="text"
                color="error"
                prepend-icon="tabler-trash"
                :loading="submittingAction"
                @click="confirmDeleteTable(editingTable)"
              >
                Eliminar
              </v-btn>

              <v-btn
                icon="tabler-x"
                size="small"
                variant="text"
                title="Cerrar panel"
                @click="clearEditingSelection"
              />
            </div>
          </div>
        </v-card-text>
      </v-card>
    </v-expand-transition>

    <!-- MODAL: GESTIÓN DE ZONA (CREAR / EDITAR) -->
    <v-dialog v-model="showZoneModal" max-width="500">
      <v-card>
        <v-card-title class="bg-surface-variant py-3 px-4 d-flex align-center justify-space-between">
          <span class="font-weight-bold">
            {{ isEditingZone ? 'Editar Zona del Restaurante' : 'Nueva Zona del Restaurante' }}
          </span>
          <v-btn icon="tabler-x" variant="text" size="small" @click="showZoneModal = false" />
        </v-card-title>

        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitZoneForm">
            <v-text-field
              v-model="zoneForm.name"
              label="Nombre de la Zona"
              placeholder="Ej: Salón Principal, Terraza, Balcón VIP, Barra"
              prepend-inner-icon="tabler-map-pin"
              density="compact"
              variant="outlined"
              class="mb-3"
              required
            />

            <v-select
              v-model="zoneForm.background_style"
              :items="[
                { title: 'Cuadrícula Técnica / Plano', value: 'grid' },
                { title: 'Madera Parquet Clásica', value: 'wooden' },
                { title: 'Terraza / Césped al Aire Libre', value: 'grass' },
                { title: 'Cemento Pulido Industrial', value: 'concrete' }
              ]"
              label="Estilo de Suelo / Ambiente"
              prepend-inner-icon="tabler-brush"
              density="compact"
              variant="outlined"
              class="mb-3"
            />

            <v-text-field
              v-model.number="zoneForm.sort_order"
              type="number"
              label="Orden de Visualización"
              prepend-inner-icon="tabler-arrows-sort"
              density="compact"
              variant="outlined"
              class="mb-4"
            />

            <div class="d-flex justify-end gap-2">
              <v-btn variant="text" @click="showZoneModal = false">Cancelar</v-btn>
              <v-btn
                type="submit"
                color="primary"
                :loading="submittingAction"
              >
                {{ isEditingZone ? 'Guardar Cambios' : 'Crear Zona' }}
              </v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL: CONFIRMAR ELIMINAR ZONA -->
    <v-dialog v-model="showDeleteZoneConfirm" max-width="450">
      <v-card>
        <v-card-title class="bg-error text-white py-3 px-4">Eliminar Zona</v-card-title>
        <v-card-text class="pa-4">
          <p class="text-body-2 mb-2">
            ¿Estás seguro de eliminar la zona <strong>{{ currentZone?.name }}</strong>?
          </p>
          <p class="text-caption text-error mb-0">
            Esta acción eliminará todas las mesas pertenecientes a esta zona.
          </p>
        </v-card-text>
        <v-card-actions class="px-4 pb-4 justify-end gap-2">
          <v-btn variant="text" @click="showDeleteZoneConfirm = false">Cancelar</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="submittingAction"
            @click="submitDeleteZone"
          >
            Eliminar Zona
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- MODAL: CREAR MESA -->
    <v-dialog v-model="showCreateTableModal" max-width="480">
      <v-card>
        <v-card-title class="bg-surface-variant py-3 px-4 d-flex align-center justify-space-between">
          <span class="font-weight-bold">Nueva Mesa en {{ currentZone?.name }}</span>
          <v-btn icon="tabler-x" variant="text" size="small" @click="showCreateTableModal = false" />
        </v-card-title>

        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitCreateTable">
            <v-text-field
              v-model="createTableForm.name"
              label="Nombre / Identificador"
              placeholder="Ej: Mesa 1, Terraza 3, Barra 2"
              prepend-inner-icon="tabler-tag"
              density="compact"
              variant="outlined"
              class="mb-3"
              required
            />

            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="createTableForm.capacity"
                  type="number"
                  min="1"
                  max="50"
                  label="Capacidad (sillas)"
                  prepend-inner-icon="tabler-users"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                  required
                />
              </v-col>

              <v-col cols="6">
                <v-select
                  v-model="createTableForm.shape"
                  :items="[
                    { title: 'Cuadrada', value: 'square' },
                    { title: 'Redonda', value: 'round' },
                    { title: 'Rectangular', value: 'rectangle' },
                    { title: 'Barra', value: 'bar' }
                  ]"
                  label="Forma Geométrica"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                  @update:model-value="applyShapeDefaultDimensions"
                />
              </v-col>
            </v-row>

            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="createTableForm.width"
                  type="number"
                  min="40"
                  max="300"
                  label="Ancho (px)"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="createTableForm.height"
                  type="number"
                  min="40"
                  max="300"
                  label="Alto (px)"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
            </v-row>

            <div class="d-flex justify-end gap-2 mt-2">
              <v-btn variant="text" @click="showCreateTableModal = false">Cancelar</v-btn>
              <v-btn
                type="submit"
                color="primary"
                :loading="submittingAction"
              >
                Crear Mesa
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
          <span class="font-weight-bold">Editar Mesa: {{ editingTable.name }}</span>
          <v-btn icon="tabler-x" variant="text" size="small" @click="showEditTableModal = false" />
        </v-card-title>

        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitUpdateTable">
            <v-text-field
              v-model="editTableForm.name"
              label="Nombre / Identificador"
              prepend-inner-icon="tabler-tag"
              density="compact"
              variant="outlined"
              class="mb-3"
              required
            />

            <v-select
              v-model="editTableForm.dining_zone_id"
              :items="zones"
              item-title="name"
              item-value="id"
              label="Zona del Restaurante"
              prepend-inner-icon="tabler-map-pin"
              density="compact"
              variant="outlined"
              class="mb-3"
            />

            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="editTableForm.capacity"
                  type="number"
                  min="1"
                  max="50"
                  label="Capacidad (sillas)"
                  prepend-inner-icon="tabler-users"
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
                <v-text-field
                  v-model.number="editTableForm.width"
                  type="number"
                  label="Ancho (px)"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
              <v-col cols="4">
                <v-text-field
                  v-model.number="editTableForm.height"
                  type="number"
                  label="Alto (px)"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
              <v-col cols="4">
                <v-text-field
                  v-model.number="editTableForm.rotation"
                  type="number"
                  label="Giro (°)"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
            </v-row>

            <div class="d-flex justify-end gap-2 mt-2">
              <v-btn variant="text" @click="showEditTableModal = false">Cancelar</v-btn>
              <v-btn
                type="submit"
                color="primary"
                :loading="submittingAction"
              >
                Actualizar Mesa
              </v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- MODAL: CONFIRMAR VACIAR ZONA (PARA EMPEZAR DESDE CERO) -->
    <v-dialog v-model="showClearZoneModal" max-width="480">
      <v-card>
        <v-card-title class="bg-warning text-white py-3 px-4 d-flex align-center gap-2">
          <v-icon>tabler-alert-triangle</v-icon>
          <span>Vaciar Mesas de la Zona</span>
        </v-card-title>
        <v-card-text class="pa-4">
          <p class="text-body-2 mb-2">
            ¿Deseas eliminar todas las mesas de la zona <strong>{{ currentZone?.name }}</strong>?
          </p>
          <p class="text-caption text-medium-emphasis mb-0">
            Esto te permite comenzar con el lienzo totalmente limpio para diseñar la distribución exacta de tu restaurante.
          </p>
        </v-card-text>
        <v-card-actions class="px-4 pb-4 justify-end gap-2">
          <v-btn variant="text" @click="showClearZoneModal = false">Cancelar</v-btn>
          <v-btn
            color="warning"
            variant="flat"
            :loading="submittingAction"
            @click="submitClearZoneTables"
          >
            Vaciar Zona
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- MODAL: ACCIONES OPERATIVAS DE MESA (EN MODO SALÓN EN VIVO) -->
    <v-dialog v-model="showTableActionModal" max-width="650">
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
            <p class="text-body-2 mb-3">Esta mesa se encuentra libre. Abre una nueva cuenta comensal para empezar a comandar.</p>
            <v-form @submit.prevent="submitOpenOrder">
              <v-text-field
                v-model.number="openOrderForm.guests_count"
                type="number"
                min="1"
                max="50"
                label="Número de Comensales / Sillas"
                prepend-inner-icon="tabler-users"
                density="compact"
                variant="outlined"
                class="mb-3"
              />
              <v-btn
                type="submit"
                color="primary"
                block
                prepend-icon="tabler-player-play"
                :loading="submittingAction"
              >
                Abrir Mesa y Crear Orden
              </v-btn>
            </v-form>
          </div>

          <!-- CASO 2: MESA OCUPADA / PRE-CUENTA -->
          <div v-else>
            <div class="d-flex justify-space-between align-center mb-3">
              <div>
                <span class="text-caption text-medium-emphasis">Comensales: </span>
                <span class="font-weight-bold">{{ selectedTable.current_order?.guests_count || 1 }}</span>
                <span class="text-caption text-medium-emphasis ml-3">Mesero: </span>
                <span class="font-weight-bold">{{ selectedTable.current_order?.seller_name || 'N/A' }}</span>
              </div>
              <div class="text-h6 font-weight-bold text-primary">
                Total: ${{ Number(selectedTable.current_order?.total_amount_usd || 0).toFixed(2) }} USD
              </div>
            </div>

            <!-- Lista de Consumo Rápido -->
            <v-table density="compact" class="border rounded mb-4">
              <thead>
                <tr>
                  <th>Silla</th>
                  <th>Platillo / Producto</th>
                  <th class="text-center">Cant.</th>
                  <th class="text-right">Total USD</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in (selectedTable.current_order?.details || [])" :key="item.id">
                  <td>
                    <v-chip size="x-small" variant="tonal" color="primary">
                      {{ item.seat_number ? `Silla ${item.seat_number}` : 'Mesa' }}
                    </v-chip>
                  </td>
                  <td>{{ item.name }}</td>
                  <td class="text-center">{{ item.quantity }}</td>
                  <td class="text-right font-weight-medium">
                    ${{ (item.quantity * item.unit_price_usd).toFixed(2) }}
                  </td>
                </tr>
                <tr v-if="!selectedTable.current_order?.details?.length">
                  <td colspan="4" class="text-center text-medium-emphasis py-3">Sin ítems comandados todavía.</td>
                </tr>
              </tbody>
            </v-table>

            <!-- Acciones de Restaurante de Salón -->
            <v-row dense>
              <!-- Pre-Cuenta Térmica -->
              <v-col cols="12" sm="4">
                <v-btn
                  color="warning"
                  variant="tonal"
                  block
                  prepend-icon="tabler-receipt"
                  @click="openPreBillDialog"
                >
                  Pre-Cuenta
                </v-btn>
              </v-col>

              <!-- Dividir Cuenta (Split Check) -->
              <v-col cols="12" sm="4">
                <v-btn
                  color="info"
                  variant="tonal"
                  block
                  prepend-icon="tabler-arrows-split-2"
                  @click="openSplitDialog"
                >
                  Dividir Cuenta
                </v-btn>
              </v-col>

              <!-- Cambiar / Transferir Mesa -->
              <v-col cols="12" sm="4">
                <v-btn
                  color="secondary"
                  variant="tonal"
                  block
                  prepend-icon="tabler-arrows-exchange"
                  @click="openTransferDialog"
                >
                  Mover Mesa
                </v-btn>
              </v-col>
            </v-row>

            <v-divider class="my-3" />

            <div class="d-flex justify-end gap-2">
              <v-btn
                color="error"
                variant="text"
                size="small"
                prepend-icon="tabler-door-exit"
                :loading="submittingAction"
                @click="releaseTableAction"
              >
                Liberar Mesa Forzada
              </v-btn>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: PRE-CUENTA TÉRMICA -->
    <v-dialog v-model="showPreBillModal" max-width="450">
      <v-card v-if="preBillData" class="thermal-ticket-card pa-4">
        <div class="text-center mb-3">
          <div class="text-h6 font-weight-bold">{{ preBillData.restaurant_name }}</div>
          <div class="text-caption">RIF: {{ preBillData.restaurant_rif }}</div>
          <div class="text-subtitle-2 font-weight-bold mt-2">*** PRE-CUENTA DE CONSUMO ***</div>
          <div class="text-caption">Mesa: {{ preBillData.table_name }} | Comensales: {{ preBillData.guests_count }}</div>
          <div class="text-caption">{{ preBillData.date }}</div>
        </div>

        <v-divider class="my-2 dashed" />

        <div v-for="it in preBillData.items" :key="it.id" class="d-flex justify-space-between text-body-2 py-1">
          <span>{{ it.quantity }}x {{ it.name }}</span>
          <span class="font-weight-medium">${{ Number(it.subtotal_usd).toFixed(2) }}</span>
        </div>

        <v-divider class="my-2 dashed" />

        <div class="d-flex justify-space-between text-body-2">
          <span>Subtotal:</span>
          <span>${{ Number(preBillData.subtotal_usd).toFixed(2) }}</span>
        </div>

        <div class="d-flex justify-space-between text-body-2 align-center my-1">
          <span class="d-flex align-center gap-1">
            Servicio Sugerido:
            <v-select
              v-model="servicePercentageInput"
              :items="[0, 5, 10, 12, 15]"
              density="compact"
              variant="plain"
              hide-details
              style="width: 75px;"
              @update:model-value="recalculatePreBill"
            />
          </span>
          <span>${{ Number(preBillData.service_amount_usd).toFixed(2) }}</span>
        </div>

        <v-divider class="my-2" />

        <div class="d-flex justify-space-between text-h6 font-weight-bold">
          <span>TOTAL SUGERIDO:</span>
          <span class="text-primary">${{ Number(preBillData.grand_total_usd).toFixed(2) }} USD</span>
        </div>

        <div v-if="preBillData.guests_count > 1" class="text-caption text-center mt-2 text-medium-emphasis">
          Sugerido por comensal ({{ preBillData.guests_count }} pers): ${{ Number(preBillData.per_guest_usd).toFixed(2) }} USD
        </div>

        <div class="text-caption text-center mt-4 text-medium-emphasis">
          Este documento no representa factura fiscal.
        </div>

        <v-card-actions class="mt-4 pa-0 d-flex justify-space-between">
          <v-btn variant="text" size="small" @click="showPreBillModal = false">Cerrar</v-btn>
          <v-btn color="primary" variant="flat" size="small" prepend-icon="tabler-printer" @click="printPreBill">
            Imprimir Ticket
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: TRANSFERIR / MOVER MESA -->
    <v-dialog v-model="showTransferDialog" max-width="480">
      <v-card>
        <v-card-title class="bg-surface-variant py-3 px-4 d-flex align-center justify-space-between">
          <span class="font-weight-bold">Mover Comensales de Mesa</span>
          <v-btn icon="tabler-x" variant="text" size="small" @click="showTransferDialog = false" />
        </v-card-title>
        <v-card-text class="pa-4">
          <p class="text-body-2 mb-3">
            Selecciona la mesa destino a la que deseas transferir la comanda activa de <strong>{{ selectedTable?.name }}</strong>:
          </p>
          <v-select
            v-model="transferTargetTableId"
            :items="availableTransferTables"
            item-title="name"
            item-value="id"
            label="Mesa Destino"
            density="compact"
            variant="outlined"
            prepend-inner-icon="tabler-table"
            class="mb-3"
          />
          <div class="d-flex justify-end gap-2">
            <v-btn variant="text" @click="showTransferDialog = false">Cancelar</v-btn>
            <v-btn
              color="primary"
              :disabled="!transferTargetTableId"
              :loading="submittingAction"
              @click="submitTransferTable"
            >
              Transferir Mesa
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: DIVISIÓN DE CUENTAS (SPLIT CHECK) -->
    <v-dialog v-model="showSplitModal" max-width="600">
      <v-card>
        <v-card-title class="bg-primary text-white py-3 px-4 d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <v-icon>tabler-arrows-split-2</v-icon>
            <span>Dividir Cuenta - {{ selectedTable?.name }}</span>
          </div>
          <v-btn icon="tabler-x" variant="text" size="small" color="white" @click="showSplitModal = false" />
        </v-card-title>

        <v-card-text class="pa-4">
          <v-tabs v-model="splitType" color="primary" density="compact" class="mb-4">
            <v-tab value="equal">Partes Iguales</v-tab>
            <v-tab value="seats_items">Por Ítems / Asiento</v-tab>
          </v-tabs>

          <!-- OPCIÓN 1: PARTES IGUALES -->
          <div v-if="splitType === 'equal'">
            <p class="text-body-2">Divide el total exacto entre varios comensales para pagos mixtos:</p>
            <div class="d-flex align-center gap-3 mb-4">
              <v-text-field
                v-model.number="equalPartsCount"
                type="number"
                min="2"
                max="20"
                label="Cantidad de Partes"
                density="compact"
                variant="outlined"
                style="max-width: 160px;"
              />
              <div class="text-h6 font-weight-bold text-primary">
                ${{ (Number(selectedTable?.current_order?.total_amount_usd || 0) / (equalPartsCount || 1)).toFixed(2) }} USD / persona
              </div>
            </div>

            <v-btn
              color="primary"
              block
              prepend-icon="tabler-check"
              :loading="submittingAction"
              @click="submitEqualSplit"
            >
              Confirmar División en {{ equalPartsCount }} Partes
            </v-btn>
          </div>

          <!-- OPCIÓN 2: POR SILLAS / ÍTEMS -->
          <div v-else>
            <p class="text-body-2 mb-3">Selecciona los ítems que se separarán en una nueva cuenta independiente:</p>
            <v-list density="compact" select-strategy="multiple">
              <v-list-item
                v-for="item in (selectedTable?.current_order?.details || [])"
                :key="item.id"
                :value="item.id"
                class="border rounded mb-1"
                @click="toggleSplitItem(item.id)"
              >
                <template #prepend>
                  <v-checkbox-btn :model-value="selectedSplitItemIds.includes(item.id)" />
                </template>
                <v-list-item-title class="font-weight-medium">
                  {{ item.quantity }}x {{ item.name }}
                </v-list-item-title>
                <v-list-item-subtitle v-if="item.seat_number">
                  Silla {{ item.seat_number }}
                </v-list-item-subtitle>
                <template #append>
                  <span class="font-weight-bold">${{ (item.quantity * item.unit_price_usd).toFixed(2) }}</span>
                </template>
              </v-list-item>
            </v-list>

            <v-btn
              color="primary"
              block
              class="mt-4"
              prepend-icon="tabler-scissors"
              :disabled="!selectedSplitItemIds.length"
              :loading="submittingAction"
              @click="submitItemsSplit"
            >
              Separar {{ selectedSplitItemIds.length }} ítems en Nueva Cuenta
            </v-btn>
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

// Modos de trabajo: '2d' (diseño del restaurante) o 'live' (operación)
const viewMode = ref('2d')
const zones = ref([])
const tables = ref([])
const kitchenStations = ref([])
const selectedZoneIndex = ref(null)
const loadingOverview = ref(false)
const savingLayout = ref(false)
const submittingAction = ref(false)

// Modales
const showTableActionModal = ref(false)
const showPreBillModal = ref(false)
const showSplitModal = ref(false)
const showCreateTableModal = ref(false)
const showEditTableModal = ref(false)
const showTransferDialog = ref(false)
const showZoneModal = ref(false)
const showDeleteZoneConfirm = ref(false)
const showClearZoneModal = ref(false)
const isEditingZone = ref(false)

// Mesa seleccionada en operación
const selectedTable = ref(null)

// Mesa seleccionada en el diseñador 2D
const editingTable = ref(null)

// Formularios
const zoneForm = reactive({
  id: null,
  name: '',
  background_style: 'grid',
  sort_order: 0,
})

const createTableForm = reactive({
  name: '',
  capacity: 4,
  shape: 'square',
  width: 80,
  height: 80,
})

const editTableForm = reactive({
  id: null,
  dining_zone_id: null,
  name: '',
  capacity: 4,
  shape: 'square',
  width: 80,
  height: 80,
  rotation: 0,
})

const openOrderForm = reactive({
  guests_count: 2,
})

// Pre-cuenta
const preBillData = ref(null)
const servicePercentageInput = ref(10)

// Split Check
const splitType = ref('equal')
const equalPartsCount = ref(2)
const selectedSplitItemIds = ref([])

// Transferir mesa
const transferTargetTableId = ref(null)

// Drag & Drop
const draggedTable = ref(null)
const dragOffset = reactive({ x: 0, y: 0 })

// Computadas
const currentZone = computed(() => {
  return zones.value.find(z => z.id === selectedZoneIndex.value) || null
})

const currentZoneBackgroundClass = computed(() => {
  const style = currentZone.value?.background_style || 'grid'
  return `zone-bg-${style}`
})

const filteredTables = computed(() => {
  if (!selectedZoneIndex.value) return tables.value
  return tables.value.filter(t => t.dining_zone_id === selectedZoneIndex.value)
})

const availableTransferTables = computed(() => {
  if (!selectedTable.value) return []
  return tables.value.filter(t => t.id !== selectedTable.value.id)
})

const availableTablesCount = computed(() => tables.value.filter(t => t.status === 'available').length)
const occupiedTablesCount = computed(() => tables.value.filter(t => t.status === 'occupied').length)
const billRequestedTablesCount = computed(() => tables.value.filter(t => t.status === 'bill_requested').length)
const reservedTablesCount = computed(() => tables.value.filter(t => t.status === 'reserved').length)

const getTablesCountByZone = (zoneId) => {
  return tables.value.filter(t => t.dining_zone_id === zoneId).length
}

const getShapeLabel = (shape) => {
  switch (shape) {
    case 'square': return 'Cuadrada'
    case 'round': return 'Redonda'
    case 'rectangle': return 'Rectangular'
    case 'bar': return 'Barra'
    default: return shape || 'Cuadrada'
  }
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

// Cargar overview del restaurante
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

    // Si la mesa que se estaba editando aún existe, refrescarla
    if (editingTable.value) {
      const refreshed = tables.value.find(t => t.id === editingTable.value.id)
      if (refreshed) editingTable.value = refreshed
    }
  } catch (err) {
    if (!silent) toast.error('Error al cargar salón y mesas.')
  } finally {
    if (!silent) loadingOverview.value = false
  }
}

// ==========================================
// GESTIÓN DE ZONAS DEL RESTAURANTE
// ==========================================
const openCreateZoneModal = () => {
  isEditingZone.value = false
  zoneForm.id = null
  zoneForm.name = `Zona ${zones.value.length + 1}`
  zoneForm.background_style = 'grid'
  zoneForm.sort_order = zones.value.length + 1
  showZoneModal.value = true
}

const openEditZoneModal = () => {
  if (!currentZone.value) return
  isEditingZone.value = true
  zoneForm.id = currentZone.value.id
  zoneForm.name = currentZone.value.name
  zoneForm.background_style = currentZone.value.background_style || 'grid'
  zoneForm.sort_order = currentZone.value.sort_order || 1
  showZoneModal.value = true
}

const submitZoneForm = async () => {
  if (!zoneForm.name.trim()) {
    toast.error('El nombre de la zona es obligatorio.')
    return
  }

  submittingAction.value = true
  try {
    if (isEditingZone.value && zoneForm.id) {
      await axios.put(`/dining-floor/zones/${zoneForm.id}`, {
        name: zoneForm.name,
        background_style: zoneForm.background_style,
        sort_order: zoneForm.sort_order,
      })
      toast.success('Zona actualizada correctamente.')
    } else {
      const res = await axios.post('/dining-floor/zones', {
        name: zoneForm.name,
        background_style: zoneForm.background_style,
        sort_order: zoneForm.sort_order,
      })
      toast.success('Zona creada correctamente.')
      if (res.data?.zone?.id) {
        selectedZoneIndex.value = res.data.zone.id
      }
    }
    showZoneModal.value = false
    await fetchOverview()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al guardar la zona.')
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
    editingTable.value = null
    await fetchOverview()
  } catch (err) {
    toast.error('Error al eliminar la zona.')
  } finally {
    submittingAction.value = false
  }
}

// ==========================================
// DISEÑADOR DE MESAS (MODO 2D)
// ==========================================
const handleTableClickInDesigner = (table) => {
  editingTable.value = table
}

const clearEditingSelection = () => {
  editingTable.value = null
}

const applyShapeDefaultDimensions = (shape) => {
  switch (shape) {
    case 'square':
      createTableForm.width = 80
      createTableForm.height = 80
      break
    case 'round':
      createTableForm.width = 80
      createTableForm.height = 80
      break
    case 'rectangle':
      createTableForm.width = 120
      createTableForm.height = 80
      break
    case 'bar':
      createTableForm.width = 60
      createTableForm.height = 60
      break
  }
}

const openCreateTableModal = () => {
  const currentCount = filteredTables.value.length
  createTableForm.name = `Mesa ${currentCount + 1}`
  createTableForm.capacity = 4
  createTableForm.shape = 'square'
  createTableForm.width = 80
  createTableForm.height = 80
  showCreateTableModal.value = true
}

const submitCreateTable = async () => {
  if (!selectedZoneIndex.value) {
    toast.error('Debes seleccionar o crear una zona primero.')
    return
  }

  submittingAction.value = true
  try {
    const tableOffset = (filteredTables.value.length * 30) % 300
    const res = await axios.post('/dining-floor/tables', {
      dining_zone_id: selectedZoneIndex.value,
      name: createTableForm.name,
      capacity: createTableForm.capacity,
      shape: createTableForm.shape,
      width: createTableForm.width || 80,
      height: createTableForm.height || 80,
      pos_x: 60 + tableOffset,
      pos_y: 60 + tableOffset,
      rotation: 0,
    })
    toast.success('Mesa agregada al plano.')
    showCreateTableModal.value = false
    await fetchOverview()
    if (res.data?.table) {
      editingTable.value = res.data.table
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al crear mesa.')
  } finally {
    submittingAction.value = false
  }
}

const openEditTableModal = (table) => {
  editTableForm.id = table.id
  editTableForm.dining_zone_id = table.dining_zone_id
  editTableForm.name = table.name
  editTableForm.capacity = table.capacity
  editTableForm.shape = table.shape || 'square'
  editTableForm.width = table.width || 80
  editTableForm.height = table.height || 80
  editTableForm.rotation = table.rotation || 0
  showEditTableModal.value = true
}

const submitUpdateTable = async () => {
  if (!editTableForm.id) return
  submittingAction.value = true
  try {
    const res = await axios.put(`/dining-floor/tables/${editTableForm.id}`, {
      dining_zone_id: editTableForm.dining_zone_id,
      name: editTableForm.name,
      capacity: editTableForm.capacity,
      shape: editTableForm.shape,
      width: editTableForm.width,
      height: editTableForm.height,
      rotation: editTableForm.rotation,
    })
    toast.success('Propiedades de la mesa actualizadas.')
    showEditTableModal.value = false
    editingTable.value = res.data.table || null
    await fetchOverview()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al actualizar mesa.')
  } finally {
    submittingAction.value = false
  }
}

const rotateEditingTable = async (degrees) => {
  if (!editingTable.value) return
  const currentRotation = Number(editingTable.value.rotation || 0)
  const newRotation = (currentRotation + degrees) % 360
  editingTable.value.rotation = newRotation
}

const scaleEditingTable = async (delta) => {
  if (!editingTable.value) return
  const currentWidth = Number(editingTable.value.width || 80)
  const currentHeight = Number(editingTable.value.height || 80)
  const newWidth = Math.max(50, Math.min(300, currentWidth + delta))
  const newHeight = Math.max(50, Math.min(300, currentHeight + delta))
  editingTable.value.width = newWidth
  editingTable.value.height = newHeight
}

const duplicateEditingTable = async () => {
  if (!editingTable.value) return
  submittingAction.value = true
  try {
    await axios.post('/dining-floor/tables', {
      dining_zone_id: editingTable.value.dining_zone_id || selectedZoneIndex.value,
      name: `${editingTable.value.name} (Copia)`,
      capacity: editingTable.value.capacity,
      shape: editingTable.value.shape || 'square',
      width: editingTable.value.width || 80,
      height: editingTable.value.height || 80,
      pos_x: Math.min(800, Number(editingTable.value.pos_x || 100) + 30),
      pos_y: Math.min(600, Number(editingTable.value.pos_y || 100) + 30),
      rotation: editingTable.value.rotation || 0,
    })
    toast.success('Mesa duplicada.')
    await fetchOverview()
  } catch (err) {
    toast.error('Error al duplicar mesa.')
  } finally {
    submittingAction.value = false
  }
}

const confirmDeleteTable = async (table) => {
  if (!confirm(`¿Eliminar la ${table.name}?`)) return
  submittingAction.value = true
  try {
    await axios.delete(`/dining-floor/tables/${table.id}`)
    toast.info(`${table.name} eliminada.`)
    editingTable.value = null
    await fetchOverview()
  } catch (err) {
    toast.error('Error al eliminar la mesa.')
  } finally {
    submittingAction.value = false
  }
}

const submitClearZoneTables = async () => {
  if (!currentZone.value) return
  const tablesToDelete = filteredTables.value
  if (!tablesToDelete.length) {
    showClearZoneModal.value = false
    return
  }

  submittingAction.value = true
  try {
    await Promise.all(tablesToDelete.map(t => axios.delete(`/dining-floor/tables/${t.id}`)))
    toast.success('Mesas vaciadas. ¡Ahora puedes diseñar tu salón desde cero!')
    showClearZoneModal.value = false
    editingTable.value = null
    await fetchOverview()
  } catch (err) {
    toast.error('Error al vaciar mesas de la zona.')
  } finally {
    submittingAction.value = false
  }
}

// Drag and Drop 2D
const handleDragStart = (table, event) => {
  draggedTable.value = table
  dragOffset.x = event.offsetX
  dragOffset.y = event.offsetY
  editingTable.value = table
}

const handleDropTable = (event) => {
  if (!draggedTable.value) return
  const containerRect = event.currentTarget.getBoundingClientRect()
  const newX = Math.max(10, Math.round(event.clientX - containerRect.left - dragOffset.x))
  const newY = Math.max(10, Math.round(event.clientY - containerRect.top - dragOffset.y))

  draggedTable.value.pos_x = newX
  draggedTable.value.pos_y = newY
  draggedTable.value = null
}

const saveCurrentLayout = async () => {
  savingLayout.value = true
  try {
    const payload = tables.value.map(t => ({
      id: t.id,
      pos_x: t.pos_x,
      pos_y: t.pos_y,
      width: t.width,
      height: t.height,
      rotation: t.rotation,
    }))
    await axios.post('/dining-floor/layout', { tables: payload })
    toast.success('Diseño y distribución de mesas guardados.')
  } catch (err) {
    toast.error('Error al guardar el plano.')
  } finally {
    savingLayout.value = false
  }
}

// ==========================================
// OPERACIÓN EN SALÓN EN VIVO
// ==========================================
const handleSelectStation = (station) => {
  if (!station) return
  toast.info(`Estación activa: ${station.name || 'Cocina'}`)
}

const selectAndInteractTable = (table) => {
  selectedTable.value = table
  openOrderForm.guests_count = table.capacity || 2
  showTableActionModal.value = true
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

// Pre-cuenta
const openPreBillDialog = async () => {
  if (!selectedTable.value?.current_order?.id) return
  try {
    const res = await axios.post(`/orders/${selectedTable.value.current_order.id}/pre-bill`, {
      service_percentage: servicePercentageInput.value,
    })
    preBillData.value = res.data.pre_bill
    showPreBillModal.value = true
    await fetchOverview()
  } catch (err) {
    toast.error('Error al generar pre-cuenta.')
  }
}

const recalculatePreBill = async () => {
  await openPreBillDialog()
}

const printPreBill = () => {
  window.print()
}

// Split Check
const openSplitDialog = () => {
  selectedSplitItemIds.value = []
  showSplitModal.value = true
}

const toggleSplitItem = (id) => {
  const index = selectedSplitItemIds.value.indexOf(id)
  if (index > -1) {
    selectedSplitItemIds.value.splice(index, 1)
  } else {
    selectedSplitItemIds.value.push(id)
  }
}

const submitEqualSplit = async () => {
  if (!selectedTable.value?.current_order?.id) return
  submittingAction.value = true
  try {
    const res = await axios.post(`/orders/${selectedTable.value.current_order.id}/split`, {
      type: 'equal',
      parts: equalPartsCount.value,
    })
    toast.success(res.data.message || 'Cuenta dividida en partes iguales.')
    showSplitModal.value = false
    showTableActionModal.value = false
    await fetchOverview()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al dividir cuenta.')
  } finally {
    submittingAction.value = false
  }
}

const submitItemsSplit = async () => {
  if (!selectedTable.value?.current_order?.id) return
  submittingAction.value = true
  try {
    const res = await axios.post(`/orders/${selectedTable.value.current_order.id}/split`, {
      type: 'seats_items',
      splits: [
        { detail_ids: selectedTable.value.current_order.details.filter(d => !selectedSplitItemIds.value.includes(d.id)).map(d => d.id) },
        { detail_ids: selectedSplitItemIds.value },
      ],
    })
    toast.success(res.data.message || 'Cuenta dividida por ítems.')
    showSplitModal.value = false
    showTableActionModal.value = false
    await fetchOverview()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al dividir por ítems.')
  } finally {
    submittingAction.value = false
  }
}

// Transferir Mesa
const openTransferDialog = () => {
  transferTargetTableId.value = null
  showTransferDialog.value = true
}

const submitTransferTable = async () => {
  if (!selectedTable.value || !transferTargetTableId.value) return
  submittingAction.value = true
  try {
    await axios.post(`/dining-floor/tables/${selectedTable.value.id}/transfer`, {
      target_table_id: transferTargetTableId.value,
    })
    toast.success('Mesa transferida exitosamente.')
    showTransferDialog.value = false
    showTableActionModal.value = false
    await fetchOverview()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al transferir mesa.')
  } finally {
    submittingAction.value = false
  }
}

let floorPollInterval = null

onMounted(async () => {
  await fetchOverview()

  // Polling silencioso solo en Modo En Vivo para no interrumpir el diseño 2D
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
.dining-floor-view {
  min-height: 80vh;
}

.zone-tabs :deep(.v-tab) {
  text-transform: none;
  font-weight: 500;
}

.floor-canvas-card {
  min-height: 600px;
  background-color: #12121a;
  border-radius: 12px;
}

/* 2D CANVAS STYLES */
.floor-2d-canvas {
  min-height: 580px;
  position: relative;
  user-select: none;
  transition: background-color 0.3s ease;
}

.canvas-watermark {
  position: absolute;
  top: 12px;
  right: 16px;
  opacity: 0.35;
  pointer-events: none;
  color: #fff;
  letter-spacing: 0.5px;
}

/* FONDOS TEMÁTICOS POR ZONA */
.zone-bg-grid {
  background-color: #1e1e2d;
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.08) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
  background-size: 25px 25px;
}

.zone-bg-wooden {
  background-color: #4a2c11;
  background-image:
    repeating-linear-gradient(90deg, #5c3818 0, #5c3818 40px, #42250d 41px, #42250d 80px),
    repeating-linear-gradient(0deg, rgba(0, 0, 0, 0.2) 0, rgba(0, 0, 0, 0.2) 1px, transparent 1px, transparent 90px);
}

.zone-bg-grass {
  background-color: #1a3824;
  background-image:
    radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to right, rgba(0, 0, 0, 0.1) 1px, transparent 1px);
  background-size: 25px 25px;
}

.zone-bg-concrete {
  background-color: #2b2e38;
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: 30px 30px;
}

.floor-table-node {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  transition: transform 0.1s ease, box-shadow 0.2s ease, border 0.2s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}

.floor-table-node:active {
  cursor: grabbing;
}

.floor-table-node:hover {
  transform: scale(1.04);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.6);
}

.floor-table-node.is-editing-selected {
  outline: 3px solid #00cfe8;
  outline-offset: 3px;
  box-shadow: 0 0 20px rgba(0, 207, 232, 0.7);
}

/* FORMAS DE MESAS */
.floor-table-node.shape-square {
  border-radius: 8px;
}

.floor-table-node.shape-round {
  border-radius: 50%;
}

.floor-table-node.shape-rectangle {
  border-radius: 10px;
}

.floor-table-node.shape-bar {
  border-radius: 6px;
}

/* COLORES DE ESTADO EN 2D */
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

.floor-table-node.status-reserved {
  background: linear-gradient(135deg, #00cfe8, #009cb0);
  color: #fff;
}

.seat-dot {
  position: absolute;
  width: 14px;
  height: 14px;
  background: #e2e8f0;
  border: 2px solid #475569;
  border-radius: 50%;
  pointer-events: none;
}

.seat-top { top: -18px; }
.seat-bottom { bottom: -18px; }
.seat-left { left: -18px; }
.seat-right { right: -18px; }

.canvas-empty-state {
  min-height: 500px;
}

/* PRE-CUENTA TÉRMICA */
.thermal-ticket-card {
  font-family: 'Courier New', Courier, monospace;
  background-color: #fdfdfd;
  color: #111;
  border: 1px dashed #bbb;
}

.dashed {
  border-style: dashed;
}
</style>
