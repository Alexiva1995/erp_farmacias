<template>
  <div class="restaurant-production-waste-view">
    <v-card class="mb-4" elevation="1">
      <v-card-text class="d-flex flex-wrap align-center justify-space-between gap-3 py-3">
        <div class="d-flex align-center gap-2">
          <v-avatar color="primary" variant="tonal" rounded size="40">
            <v-icon size="24">tabler-chef-hat</v-icon>
          </v-avatar>
          <div>
            <h2 class="text-h6 font-weight-bold mb-0">Producción en Lote & Mermas de Cocina</h2>
            <div class="text-caption text-medium-emphasis">Gestión de sub-recetas (salsas, masas, carnes) y control de desperdicios/bajas</div>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <v-tabs v-model="activeTab" density="compact" color="primary">
            <v-tab value="production">
              <v-icon start size="18">tabler-packages</v-icon>
              Órdenes de Producción
            </v-tab>
            <v-tab value="waste">
              <v-icon start size="18">tabler-trash-x</v-icon>
              Control de Mermas
            </v-tab>
          </v-tabs>
        </div>
      </v-card-text>
    </v-card>

    <!-- PESTAÑA 1: PRODUCCIÓN DE SUB-RECETAS -->
    <div v-if="activeTab === 'production'">
      <v-row class="mb-4" dense>
        <v-col cols="12" class="d-flex justify-end">
          <v-btn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openProductionModal"
          >
            Nueva Producción de Sub-Receta
          </v-btn>
        </v-col>
      </v-row>

      <v-card elevation="1">
        <v-table density="comfortable" hover>
          <thead>
            <tr>
              <th>Código</th>
              <th>Sub-Receta / Preparación</th>
              <th>Producto Elaborado</th>
              <th class="text-center">Multiplicador</th>
              <th class="text-center">Cantidad Real</th>
              <th class="text-right">Costo Unit. Calculado</th>
              <th class="text-right">Costo Total USD</th>
              <th>Lote Generado</th>
              <th>Estado</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in productionOrders" :key="order.id">
              <td class="font-weight-bold">{{ order.code }}</td>
              <td>{{ order.dish?.name }}</td>
              <td>{{ order.output_product?.name }}</td>
              <td class="text-center">{{ order.batch_multiplier }}x</td>
              <td class="text-center font-weight-bold text-success">{{ order.actual_quantity }}</td>
              <td class="text-right">${{ Number(order.unit_cost_calculated || 0).toFixed(4) }}</td>
              <td class="text-right font-weight-bold">${{ Number(order.total_production_cost || 0).toFixed(2) }}</td>
              <td>
                <v-chip size="x-small" color="primary" variant="tonal">
                  {{ order.lot_number || 'N/A' }}
                </v-chip>
              </td>
              <td>
                <v-chip size="small" color="success">
                  {{ order.status_label || order.status }}
                </v-chip>
              </td>
              <td class="text-caption text-medium-emphasis">{{ order.produced_at }}</td>
            </tr>
            <tr v-if="!productionOrders.length">
              <td colspan="10" class="text-center py-6 text-medium-emphasis">
                No hay órdenes de producción registradas aún.
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>
    </div>

    <!-- PESTAÑA 2: CONTROL DE MERMAS Y DESPERDICIOS -->
    <div v-else>
      <v-row class="mb-4" dense>
        <v-col cols="12" class="d-flex justify-end">
          <v-btn
            color="error"
            prepend-icon="tabler-alert-triangle"
            @click="openWasteModal"
          >
            Registrar Merma / Baja
          </v-btn>
        </v-col>
      </v-row>

      <v-card elevation="1">
        <v-table density="comfortable" hover>
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Ítem Afectado</th>
              <th>Estación</th>
              <th class="text-center">Cantidad</th>
              <th class="text-right">Costo Unitario</th>
              <th class="text-right">Pérdida Total USD</th>
              <th>Motivo de Baja</th>
              <th>Reportado Por</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="waste in kitchenWastes" :key="waste.id">
              <td>
                <v-chip size="x-small" :color="waste.item_type === 'dish' ? 'warning' : 'info'">
                  {{ waste.item_type === 'dish' ? 'Platillo' : 'Insumo' }}
                </v-chip>
              </td>
              <td class="font-weight-medium">{{ waste.item_name }}</td>
              <td>{{ waste.station_name }}</td>
              <td class="text-center font-weight-bold text-error">{{ waste.quantity }}</td>
              <td class="text-right">${{ Number(waste.unit_cost || 0).toFixed(2) }}</td>
              <td class="text-right font-weight-bold text-error">
                -${{ Number(waste.total_loss_usd || 0).toFixed(2) }}
              </td>
              <td>
                <v-chip size="small" color="error" variant="outlined">
                  {{ waste.reason_label || waste.reason }}
                </v-chip>
              </td>
              <td class="text-caption">{{ waste.user_name }}</td>
              <td class="text-caption text-medium-emphasis">{{ waste.occurred_at }}</td>
            </tr>
            <tr v-if="!kitchenWastes.length">
              <td colspan="9" class="text-center py-6 text-medium-emphasis">
                No hay mermas registradas en el período.
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>
    </div>

    <!-- DIÁLOGO: CREAR PRODUCCIÓN -->
    <v-dialog v-model="showProductionModal" max-width="550">
      <v-card>
        <v-card-title class="bg-primary text-white py-3 px-4">
          <v-icon start>tabler-tools-kitchen-2</v-icon>
          Nueva Producción de Sub-Receta
        </v-card-title>
        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitProduction">
            <v-select
              v-model="productionForm.dish_id"
              :items="subRecipeDishes"
              item-title="name"
              item-value="id"
              label="Seleccionar Sub-Receta Base"
              density="compact"
              variant="outlined"
              class="mb-3"
              required
            />
            <v-row dense>
              <v-col cols="6">
                <v-text-field
                  v-model.number="productionForm.batch_multiplier"
                  type="number"
                  step="0.1"
                  min="0.1"
                  label="Multiplicador de Tanda"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                  required
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="productionForm.actual_quantity"
                  type="number"
                  step="0.01"
                  min="0.01"
                  label="Rendimiento Real Obtenido"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
            </v-row>
            <v-textarea
              v-model="productionForm.notes"
              label="Notas u observaciones de cocción"
              rows="2"
              density="compact"
              variant="outlined"
              class="mb-3"
            />
            <v-btn type="submit" color="primary" block :loading="submitting">
              Producir y Descontar Insumos (FEFO + Yield)
            </v-btn>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: REGISTRAR MERMA -->
    <v-dialog v-model="showWasteModal" max-width="500">
      <v-card>
        <v-card-title class="bg-error text-white py-3 px-4">
          <v-icon start>tabler-trash-x</v-icon>
          Registrar Merma / Desperdicio
        </v-card-title>
        <v-card-text class="pa-4">
          <v-form @submit.prevent="submitWaste">
            <v-select
              v-model="wasteForm.reason"
              :items="wasteReasons"
              item-title="label"
              item-value="value"
              label="Motivo de la Merma / Pérdida"
              density="compact"
              variant="outlined"
              class="mb-3"
              required
            />
            <v-row dense>
              <v-col cols="6">
                <v-select
                  v-model="wasteForm.item_type"
                  :items="[{ title: 'Insumo (Producto)', value: 'product' }, { title: 'Platillo Terminado', value: 'dish' }]"
                  label="Tipo de Ítem"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="wasteForm.quantity"
                  type="number"
                  step="0.01"
                  min="0.01"
                  label="Cantidad Perdida"
                  density="compact"
                  variant="outlined"
                  class="mb-3"
                  required
                />
              </v-col>
            </v-row>
            <v-textarea
              v-model="wasteForm.notes"
              label="Explicación / Justificación"
              rows="2"
              density="compact"
              variant="outlined"
              class="mb-3"
            />
            <v-btn type="submit" color="error" block :loading="submitting">
              Registrar Baja de Cocina
            </v-btn>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

const activeTab = ref('production')
const productionOrders = ref([])
const kitchenWastes = ref([])
const subRecipeDishes = ref([])
const submitting = ref(false)

const showProductionModal = ref(false)
const showWasteModal = ref(false)

const productionForm = reactive({
  dish_id: null,
  batch_multiplier: 1.0,
  actual_quantity: null,
  notes: '',
})

const wasteForm = reactive({
  item_type: 'product',
  product_id: 1,
  dish_id: null,
  quantity: 1.0,
  reason: 'burned_spoiled',
  notes: '',
})

const wasteReasons = [
  { label: 'Quemado / Malogrado', value: 'burned_spoiled' },
  { label: 'Vencimiento / Caducado', value: 'expired' },
  { label: 'Merma de Limpieza / Cocción', value: 'preparation_trim' },
  { label: 'Comida de Personal', value: 'staff_meal' },
  { label: 'Cortesía de la Casa', value: 'courtesy' },
  { label: 'Caída / Accidente', value: 'dropped' },
]

const loadData = async () => {
  try {
    const [resProd, resWaste, resDishes] = await Promise.all([
      axios.get('/restaurant/production/orders'),
      axios.get('/restaurant/waste/records'),
      axios.get('/dishes?per_page=100'),
    ])
    productionOrders.value = resProd.data?.data || []
    kitchenWastes.value = resWaste.data?.data || []
    subRecipeDishes.value = (resDishes.data?.data || []).map(d => ({
      id: d.id,
      name: d.name,
    }))
  } catch (err) {
    console.error('Error cargando producción/mermas:', err)
  }
}

const openProductionModal = () => {
  if (subRecipeDishes.value.length) {
    productionForm.dish_id = subRecipeDishes.value[0].id
  }
  showProductionModal.value = true
}

const submitProduction = async () => {
  submitting.value = true
  try {
    const res = await axios.post('/restaurant/production/orders', productionForm)
    toast.success(res.data?.message || 'Producción completada.')
    showProductionModal.value = false
    await loadData()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error en orden de producción.')
  } finally {
    submitting.value = false
  }
}

const openWasteModal = () => {
  showWasteModal.value = true
}

const submitWaste = async () => {
  submitting.value = true
  try {
    const res = await axios.post('/restaurant/waste/records', wasteForm)
    toast.success(res.data?.message || 'Merma registrada.')
    showWasteModal.value = false
    await loadData()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al registrar merma.')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>
