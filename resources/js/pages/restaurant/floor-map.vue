<template>
  <div class="dining-floor-view">
    <!-- Barra Superior de Control -->
    <v-card class="mb-4" elevation="1">
      <v-card-text class="d-flex flex-wrap align-center justify-space-between gap-3 py-3">
        <!-- Selector de Zonas -->
        <div class="d-flex align-center gap-2">
          <v-tabs v-model="selectedZoneIndex" density="compact" color="primary">
            <v-tab v-for="zone in zones" :key="zone.id" :value="zone.id">
              <v-icon start size="18">tabler-map-pin</v-icon>
              {{ zone.name }}
            </v-tab>
          </v-tabs>
          <v-btn icon="tabler-plus" size="small" variant="tonal" color="primary" @click="showZoneModal = true" title="Nueva Zona" />
        </div>

        <!-- Modos de Vista & Acciones -->
        <div class="d-flex align-center flex-wrap gap-2">
          <!-- Toggle 3D vs 2D -->
          <v-btn-toggle v-model="viewMode" mandatory density="compact" color="primary" rounded="lg">
            <v-btn value="3d">
              <v-icon start size="18">tabler-box-model</v-icon>
              3D Simulator
            </v-btn>
            <v-btn value="2d">
              <v-icon start size="18">tabler-layout-dashboard</v-icon>
              2D Editor / Plano
            </v-btn>
          </v-btn-toggle>

          <v-btn
            v-if="viewMode === '2d'"
            color="success"
            variant="tonal"
            size="small"
            prepend-icon="tabler-device-floppy"
            :loading="savingLayout"
            @click="saveCurrentLayout"
          >
            Guardar Plano
          </v-btn>

          <v-btn
            color="primary"
            variant="flat"
            size="small"
            prepend-icon="tabler-table"
            @click="openCreateTableModal"
          >
            Nueva Mesa
          </v-btn>

          <v-btn
            icon="tabler-refresh"
            variant="text"
            size="small"
            :loading="loadingOverview"
            @click="fetchOverview"
          />
        </div>
      </v-card-text>
    </v-card>

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

    <!-- Canvas Principal (3D o 2D) -->
    <v-card class="floor-canvas-card position-relative overflow-hidden" elevation="2">
      <!-- MODO 3D: Simulador Interactivo Three.js -->
      <div
        v-show="viewMode === '3d'"
        ref="threeContainer"
        class="three-canvas-container"
      >
        <div class="three-overlay-legend pa-2 text-caption">
          <span class="mr-2"><v-icon size="14" color="success">tabler-circle-filled</v-icon> Libre</span>
          <span class="mr-2"><v-icon size="14" color="error">tabler-circle-filled</v-icon> Ocupada</span>
          <span class="mr-2"><v-icon size="14" color="warning">tabler-circle-filled</v-icon> Cuenta</span>
          <span><v-icon size="14" color="info">tabler-circle-filled</v-icon> Reservada</span>
          <div class="text-muted mt-1">Arrastra para rotar la cámara 3D | Clic en mesa para abrir menú</div>
        </div>
      </div>

      <!-- MODO 2D: Plano Interactivo con Drag & Drop -->
      <div
        v-show="viewMode === '2d'"
        class="floor-2d-canvas pa-4"
        @dragover.prevent
        @drop="handleDropTable"
      >
        <div
          v-for="table in filteredTables"
          :key="table.id"
          class="floor-table-node"
          :class="[
            `shape-${table.shape}`,
            `status-${table.status}`,
            { 'is-selected': selectedTable?.id === table.id }
          ]"
          :style="{
            left: `${table.pos_x}px`,
            top: `${table.pos_y}px`,
            width: `${table.width}px`,
            height: `${table.height}px`,
            transform: `rotate(${table.rotation || 0}deg)`
          }"
          draggable="true"
          @dragstart="handleDragStart(table, $event)"
          @click="selectAndInteractTable(table)"
        >
          <div class="table-inner d-flex flex-column align-center justify-center text-center">
            <span class="table-title font-weight-bold">{{ table.name }}</span>
            <span class="table-capacity text-caption">Cap: {{ table.capacity }}</span>
            <span v-if="table.current_order" class="table-amount font-weight-bold text-caption mt-1">
              ${{ Number(table.current_order.total_amount_usd || 0).toFixed(2) }}
            </span>
          </div>

          <!-- Indicador de Asientos Alrededor -->
          <div class="seat-dot seat-top" />
          <div class="seat-dot seat-bottom" />
          <div v-if="table.capacity > 2" class="seat-dot seat-left" />
          <div v-if="table.capacity > 3" class="seat-dot seat-right" />
        </div>
      </div>
    </v-card>

    <!-- MODAL DE GESTIÓN Y ACCIONES DE MESA -->
    <v-dialog v-model="showTableActionModal" max-width="650">
      <v-card v-if="selectedTable">
        <v-card-title class="d-flex align-center justify-space-between bg-surface-variant py-3 px-4">
          <div class="d-flex align-center gap-2">
            <v-icon :color="getStatusColor(selectedTable.status)">tabler-table</v-icon>
            <span class="font-weight-bold">{{ selectedTable.name }}</span>
            <v-chip size="small" :color="getStatusColor(selectedTable.status)">
              {{ selectedTable.status_label }}
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
                  @click="showTransferDialog = true"
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

    <!-- DIÁLOGO: CREAR MESA -->
    <v-dialog v-model="showCreateTableModal" max-width="450">
      <v-card>
        <v-card-title class="bg-surface-variant py-3 px-4">Nueva Mesa</v-card-title>
        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitCreateTable">
            <v-text-field
              v-model="createTableForm.name"
              label="Nombre / Identificador"
              placeholder="Ej: Mesa 4, Terraza 2"
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
                  label="Capacidad (sillas)"
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
                  label="Forma"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
            </v-row>
            <v-btn type="submit" color="primary" block :loading="submittingAction">
              Crear Mesa
            </v-btn>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

// Estados Generales
const viewMode = ref('3d') // '3d' o '2d'
const zones = ref([])
const tables = ref([])
const selectedZoneIndex = ref(null)
const loadingOverview = ref(false)
const savingLayout = ref(false)
const submittingAction = ref(false)

// Modales
const showTableActionModal = ref(false)
const showPreBillModal = ref(false)
const showSplitModal = ref(false)
const showCreateTableModal = ref(false)
const showTransferDialog = ref(false)
const showZoneModal = ref(false)

// Mesa seleccionada
const selectedTable = ref(null)

// Formularios
const openOrderForm = reactive({
  guests_count: 2,
})

const createTableForm = reactive({
  name: '',
  capacity: 4,
  shape: 'square',
})

// Pre-cuenta
const preBillData = ref(null)
const servicePercentageInput = ref(10)

// Split Check
const splitType = ref('equal')
const equalPartsCount = ref(2)
const selectedSplitItemIds = ref([])

// Drag & Drop
const draggedTable = ref(null)
const dragOffset = reactive({ x: 0, y: 0 })

// Three.js References
const threeContainer = ref(null)
let threeScene = null
let threeCamera = null
let threeRenderer = null
let threeAnimationId = null
let tableMeshes = []
let raycaster = null
let mouseVector = null

// Computadas
const filteredTables = computed(() => {
  if (!selectedZoneIndex.value) return tables.value
  return tables.value.filter(t => t.dining_zone_id === selectedZoneIndex.value)
})

const availableTablesCount = computed(() => tables.value.filter(t => t.status === 'available').length)
const occupiedTablesCount = computed(() => tables.value.filter(t => t.status === 'occupied').length)
const billRequestedTablesCount = computed(() => tables.value.filter(t => t.status === 'bill_requested').length)
const reservedTablesCount = computed(() => tables.value.filter(t => t.status === 'reserved').length)

const getStatusColor = (status) => {
  switch (status) {
    case 'available': return 'success'
    case 'occupied': return 'error'
    case 'bill_requested': return 'warning'
    case 'reserved': return 'info'
    default: return 'grey'
  }
}

// Cargar overview desde API
const fetchOverview = async () => {
  loadingOverview.value = true
  try {
    const res = await axios.get('/dining-floor/overview')
    zones.value = res.data.zones || []
    tables.value = res.data.tables || []
    if (!selectedZoneIndex.value && zones.value.length) {
      selectedZoneIndex.value = zones.value[0].id
    }
    updateThreeScene()
  } catch (err) {
    toast.error('Error al cargar salón y mesas.')
  } finally {
    loadingOverview.value = false
  }
}

// Acciones de Mesa
const selectAndInteractTable = (table) => {
  selectedTable.value = table
  openOrderForm.guests_count = table.capacity || 2
  showTableActionModal.value = true
}

const submitOpenOrder = async () => {
  if (!selectedTable.value) return
  submittingAction.value = true
  try {
    const res = await axios.post(`/dining-floor/tables/${selectedTable.value.id}/open-order`, {
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

// Drag and Drop 2D
const handleDragStart = (table, event) => {
  draggedTable.value = table
  dragOffset.x = event.offsetX
  dragOffset.y = event.offsetY
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
    toast.success('Diseño de mesas guardado.')
  } catch (err) {
    toast.error('Error al guardar el plano.')
  } finally {
    savingLayout.value = false
  }
}

// Crear Mesa
const openCreateTableModal = () => {
  createTableForm.name = `Mesa ${tables.value.length + 1}`
  createTableForm.capacity = 4
  createTableForm.shape = 'square'
  showCreateTableModal.value = true
}

const submitCreateTable = async () => {
  submittingAction.value = true
  try {
    await axios.post('/dining-floor/tables', {
      dining_zone_id: selectedZoneIndex.value,
      name: createTableForm.name,
      capacity: createTableForm.capacity,
      shape: createTableForm.shape,
      pos_x: 100 + (tables.value.length * 20) % 300,
      pos_y: 100 + (tables.value.length * 20) % 200,
    })
    toast.success('Mesa creada correctamente.')
    showCreateTableModal.value = false
    await fetchOverview()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al crear mesa.')
  } finally {
    submittingAction.value = false
  }
}

// ==========================================
// SIMULADOR 3D INTERACTIVO (THREE.JS)
// ==========================================
let THREE = null

const initThree = async () => {
  if (!threeContainer.value) return

  // Importar Three.js dinámicamente o usar CDN global si está disponible
  try {
    if (window.THREE) {
      THREE = window.THREE
    } else {
      // Cargar script Three.js dinámicamente si no está en bundle
      await new Promise((resolve, reject) => {
        const script = document.createElement('script')
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js'
        script.onload = () => {
          THREE = window.THREE
          resolve()
        }
        script.onerror = reject
        document.head.appendChild(script)
      })
    }
  } catch (e) {
    console.warn('Three.js no disponible para 3D, usando fallback 2D:', e)
    viewMode.value = '2d'
    return
  }

  const width = threeContainer.value.clientWidth || 800
  const height = 550

  // 1. Scene
  threeScene = new THREE.Scene()
  threeScene.background = new THREE.Color(0x1e1e2d)

  // 2. Camera
  threeCamera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000)
  threeCamera.position.set(0, 35, 45)
  threeCamera.lookAt(0, 0, 0)

  // 3. Renderer
  threeRenderer = new THREE.WebGLRenderer({ antialias: true })
  threeRenderer.setSize(width, height)
  threeRenderer.shadowMap.enabled = true
  threeContainer.value.innerHTML = ''
  threeContainer.value.appendChild(threeRenderer.domElement)

  // 4. Luces
  const ambientLight = new THREE.AmbientLight(0xffffff, 0.7)
  threeScene.add(ambientLight)

  const dirLight = new THREE.DirectionalLight(0xffffff, 0.8)
  dirLight.position.set(20, 40, 20)
  dirLight.castShadow = true
  threeScene.add(dirLight)

  // 5. Suelo del Restaurante
  const floorGeo = new THREE.PlaneGeometry(60, 40)
  const floorMat = new THREE.MeshStandardMaterial({ color: 0x27293d, roughness: 0.8 })
  const floorMesh = new THREE.Mesh(floorGeo, floorMat)
  floorMesh.rotation.x = -Math.PI / 2
  floorMesh.receiveShadow = true
  threeScene.add(floorMesh)

  // Raycaster para clics en mesas 3D
  raycaster = new THREE.Raycaster()
  mouseVector = new THREE.Vector2()

  threeContainer.value.addEventListener('click', onThreeClick)

  // Render Loop
  const animate = () => {
    threeAnimationId = requestAnimationFrame(animate)
    threeRenderer.render(threeScene, threeCamera)
  }
  animate()

  updateThreeScene()
}

const updateThreeScene = () => {
  if (!threeScene || !THREE) return

  // Limpiar mesas previas
  tableMeshes.forEach(mesh => threeScene.remove(mesh))
  tableMeshes = []

  const currentTables = filteredTables.value

  currentTables.forEach((table, idx) => {
    // Coordenadas normalizadas a la grilla 3D
    const x3d = ((table.pos_x || 100) / 15) - 25
    const z3d = ((table.pos_y || 100) / 15) - 15

    let color = 0x28c76f // available (verde)
    if (table.status === 'occupied') color = 0xea5455 // red
    if (table.status === 'bill_requested') color = 0xff9f43 // orange
    if (table.status === 'reserved') color = 0x00cfe8 // cyan

    let tableGeo
    if (table.shape === 'round') {
      tableGeo = new THREE.CylinderGeometry(2, 2, 1.2, 32)
    } else {
      tableGeo = new THREE.BoxGeometry(3.5, 1.2, 3.5)
    }

    const tableMat = new THREE.MeshStandardMaterial({ color, roughness: 0.4 })
    const tableMesh = new THREE.Mesh(tableGeo, tableMat)
    tableMesh.position.set(x3d, 0.6, z3d)
    tableMesh.castShadow = true
    tableMesh.receiveShadow = true
    tableMesh.userData = { table }

    // Sillas alrededor
    const chairGeo = new THREE.BoxGeometry(0.8, 1, 0.8)
    const chairMat = new THREE.MeshStandardMaterial({ color: 0x4a4a5a })

    const chairOffsets = [
      [2.5, 0], [-2.5, 0], [0, 2.5], [0, -2.5]
    ]

    for (let c = 0; c < Math.min(table.capacity, 4); c++) {
      const chairMesh = new THREE.Mesh(chairGeo, chairMat)
      chairMesh.position.set(x3d + chairOffsets[c][0], 0.5, z3d + chairOffsets[c][1])
      threeScene.add(chairMesh)
      tableMeshes.push(chairMesh)
    }

    threeScene.add(tableMesh)
    tableMeshes.push(tableMesh)
  })
}

const onThreeClick = (event) => {
  if (!threeRenderer || !threeCamera) return
  const rect = threeRenderer.domElement.getBoundingClientRect()
  mouseVector.x = ((event.clientX - rect.left) / rect.width) * 2 - 1
  mouseVector.y = -((event.clientY - rect.top) / rect.height) * 2 + 1

  raycaster.setFromCamera(mouseVector, threeCamera)
  const intersects = raycaster.intersectObjects(tableMeshes)

  if (intersects.length > 0) {
    const clickedTable = intersects[0].object.userData?.table
    if (clickedTable) {
      selectAndInteractTable(clickedTable)
    }
  }
}

watch(viewMode, (newVal) => {
  if (newVal === '3d') {
    nextTick(() => {
      if (!threeScene) {
        initThree()
      } else {
        updateThreeScene()
      }
    })
  }
})

watch(selectedZoneIndex, () => {
  updateThreeScene()
})

onMounted(async () => {
  await fetchOverview()
  if (viewMode.value === '3d') {
    nextTick(() => {
      initThree()
    })
  }
})

onUnmounted(() => {
  if (threeAnimationId) {
    cancelAnimationFrame(threeAnimationId)
  }
  if (threeRenderer) {
    threeRenderer.dispose()
  }
})
</script>

<style scoped>
.dining-floor-view {
  min-height: 80vh;
}

.floor-canvas-card {
  min-height: 550px;
  background-color: #1a1a24;
  border-radius: 12px;
}

.three-canvas-container {
  width: 100%;
  height: 550px;
  position: relative;
  cursor: grab;
}

.three-overlay-legend {
  position: absolute;
  top: 12px;
  left: 12px;
  background: rgba(30, 30, 45, 0.85);
  backdrop-filter: blur(6px);
  border-radius: 8px;
  color: #fff;
  z-index: 10;
  pointer-events: none;
}

/* 2D CANVAS STYLES */
.floor-2d-canvas {
  min-height: 550px;
  position: relative;
  background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
  background-color: #1e1e2d;
  user-select: none;
}

.floor-table-node {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: box-shadow 0.2s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.floor-table-node:hover {
  transform: scale(1.05);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.5);
}

.floor-table-node.shape-square {
  border-radius: 8px;
}

.floor-table-node.shape-round {
  border-radius: 50%;
}

.floor-table-node.shape-rectangle {
  border-radius: 8px;
}

.floor-table-node.shape-bar {
  border-radius: 4px;
}

/* Colores de estado en 2D */
.floor-table-node.status-available {
  background: #28c76f;
  color: #fff;
}

.floor-table-node.status-occupied {
  background: #ea5455;
  color: #fff;
}

.floor-table-node.status-bill_requested {
  background: #ff9f43;
  color: #fff;
}

.floor-table-node.status-reserved {
  background: #00cfe8;
  color: #fff;
}

.seat-dot {
  position: absolute;
  width: 14px;
  height: 14px;
  background: #6e6b7b;
  border-radius: 50%;
}

.seat-top { top: -18px; }
.seat-bottom { bottom: -18px; }
.seat-left { left: -18px; }
.seat-right { right: -18px; }

/* Pre-cuenta ticket térmico */
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
