<template>
  <div class="restaurant-production-waste-view pb-12">
    <!-- Header / Título -->
    <v-card class="mb-4" elevation="1">
      <v-card-text class="d-flex flex-wrap align-center justify-space-between gap-3 py-3">
        <div class="d-flex align-center gap-2">
          <v-avatar color="primary" variant="tonal" rounded size="40">
            <v-icon size="24">tabler-chef-hat</v-icon>
          </v-avatar>
          <div>
            <h2 class="text-h6 font-weight-bold mb-0">Producción en Lote & Mermas de Cocina</h2>
            <div class="text-caption text-medium-emphasis">Gestión de sub-recetas (salsas, masas, preparaciones base) y control de desperdicios</div>
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
        <v-col cols="12" class="d-flex justify-end gap-2">
          <v-btn
            color="secondary"
            variant="tonal"
            prepend-icon="tabler-clipboard-plus"
            @click="openSubRecipeModal"
          >
            Nueva Sub-Receta / Preparación Base
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openProductionModal"
          >
            Nueva Orden de Producción
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
              <td>
                <span class="font-weight-medium">{{ order.output_product?.name || 'Insumo Base' }}</span>
              </td>
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
              <td colspan="10" class="text-center py-8 text-medium-emphasis">
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
              <td colspan="9" class="text-center py-8 text-medium-emphasis">
                No hay mermas registradas en el período.
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>
    </div>

    <!-- DIÁLOGO: CREAR NUEVA SUB-RECETA (RECETA BASE PARA PLATOS) -->
    <v-dialog v-model="showSubRecipeModal" max-width="850px" persistent scrollable>
      <v-card class="rounded-lg overflow-hidden">
        <v-card-title class="bg-primary text-white py-3 px-4 d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <v-icon>tabler-clipboard-list</v-icon>
            <span class="text-h6 font-weight-bold">Crear Sub-Receta / Preparación Base</span>
          </div>
          <v-btn icon variant="text" color="white" size="small" @click="showSubRecipeModal = false">
            <v-icon>tabler-x</v-icon>
          </v-btn>
        </v-card-title>

        <v-card-text class="pa-4 bg-light" style="max-height: 78vh; overflow-y: auto;">
          <v-form @submit.prevent="submitSubRecipe">
            <!-- Datos Generales -->
            <v-card variant="flat" class="pa-4 bg-white border rounded-lg mb-4">
              <h3 class="text-subtitle-2 font-weight-bold text-uppercase mb-3">Información de la Preparación</h3>
              <v-row dense>
                <v-col cols="12" sm="8">
                  <v-text-field
                    v-model="subRecipeForm.name"
                    label="Nombre de la Receta / Preparación"
                    placeholder="Ej: Salsa BBQ Casera, Masa de Waffles, Carne Molida Preparada"
                    density="compact"
                    variant="outlined"
                    required
                  />
                </v-col>
                <v-col cols="12" sm="4">
                  <v-select
                    v-model="subRecipeForm.category_id"
                    :items="categories"
                    item-title="name"
                    item-value="id"
                    label="Categoría"
                    placeholder="Seleccionar"
                    density="compact"
                    variant="outlined"
                    clearable
                  />
                </v-col>

                <v-col cols="12" sm="4">
                  <v-text-field
                    v-model.number="subRecipeForm.expected_yield_quantity"
                    type="number"
                    step="0.01"
                    min="0.01"
                    label="Rendimiento / Unidades que salen"
                    placeholder="Ej: 10"
                    density="compact"
                    variant="outlined"
                    required
                  />
                </v-col>
                <v-col cols="12" sm="4">
                  <v-select
                    v-model="subRecipeForm.unit_of_measure"
                    :items="['porción', 'porciones', 'lt', 'ml', 'kg', 'gr', 'und', 'pza']"
                    label="Unidad de Medida del Insumo"
                    density="compact"
                    variant="outlined"
                  />
                </v-col>
                <v-col cols="12" sm="4">
                  <v-text-field
                    v-model.number="subRecipeForm.shelf_life_days"
                    type="number"
                    step="1"
                    min="1"
                    label="Días de Vida Útil (Caducidad)"
                    placeholder="7"
                    density="compact"
                    variant="outlined"
                  />
                </v-col>
              </v-row>
            </v-card>

            <!-- Buscador e Insumos -->
            <v-card variant="flat" class="pa-4 bg-white border rounded-lg mb-4">
              <div class="d-flex align-center justify-space-between mb-3">
                <h3 class="text-subtitle-2 font-weight-bold text-uppercase mb-0">Insumos que componen esta Receta</h3>
                <span class="text-caption text-medium-emphasis">Se descontarán al producir lotes</span>
              </div>

              <v-row dense align="center">
                <v-col cols="12" sm="6">
                  <v-autocomplete
                    v-model="selectedIngredient"
                    v-model:search="ingredientSearch"
                    :items="ingredientOptions"
                    :loading="loadingIngredients"
                    item-title="name"
                    return-object
                    label="Buscar Insumo / Ingrediente"
                    placeholder="Escribe para buscar (harina, azúcar, tomate...)"
                    density="compact"
                    variant="outlined"
                    hide-details
                    :no-filter="true"
                    clearable
                  />
                </v-col>

                <v-col cols="6" sm="3">
                  <v-text-field
                    v-model.number="ingredientPortion"
                    label="Cantidad / Porción"
                    type="number"
                    step="0.01"
                    min="0.001"
                    density="compact"
                    variant="outlined"
                    hide-details
                    :suffix="selectedIngredient?.unit_of_measure || 'und'"
                  />
                </v-col>

                <v-col cols="6" sm="3">
                  <v-btn
                    color="success"
                    block
                    height="40"
                    variant="flat"
                    prepend-icon="tabler-plus"
                    :disabled="!selectedIngredient || !ingredientPortion"
                    @click="addIngredientToSubRecipe"
                  >
                    Añadir Insumo
                  </v-btn>
                </v-col>
              </v-row>

              <!-- Tabla de Insumos Agregados -->
              <v-table density="compact" class="mt-4 border rounded">
                <thead>
                  <tr class="bg-light">
                    <th>Insumo</th>
                    <th class="text-center">Porción Requerida</th>
                    <th class="text-right">Costo Unit.</th>
                    <th class="text-right">Costo Total</th>
                    <th class="text-center" style="width: 60px;">Acción</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(ing, idx) in subRecipeForm.ingredients" :key="idx">
                    <td class="font-weight-medium">{{ ing.name }}</td>
                    <td class="text-center">{{ ing.portion }} {{ ing.unit_of_measure }}</td>
                    <td class="text-right">${{ Number(ing.unit_cost || 0).toFixed(2) }}</td>
                    <td class="text-right font-weight-bold text-primary">${{ Number(ing.designated_cost || 0).toFixed(2) }}</td>
                    <td class="text-center">
                      <v-btn icon size="x-small" color="error" variant="text" @click="subRecipeForm.ingredients.splice(idx, 1)">
                        <v-icon size="16">tabler-trash</v-icon>
                      </v-btn>
                    </td>
                  </tr>
                  <tr v-if="!subRecipeForm.ingredients.length">
                    <td colspan="5" class="text-center py-4 text-medium-emphasis text-caption">
                      No has agregado insumos a esta sub-receta aún.
                    </td>
                  </tr>
                </tbody>
              </v-table>

              <!-- Resumen de Costos de la Receta -->
              <div class="d-flex flex-wrap justify-space-between align-center mt-3 pa-3 bg-light rounded border">
                <div>
                  <span class="text-caption text-medium-emphasis">Costo Total de Preparación:</span>
                  <strong class="ms-2 text-primary">${{ subRecipeTotalCost.toFixed(2) }} USD</strong>
                </div>
                <div>
                  <span class="text-caption text-medium-emphasis">Costo Unitario por {{ subRecipeForm.unit_of_measure }}:</span>
                  <strong class="ms-2 text-success">${{ subRecipeUnitCost.toFixed(4) }} USD</strong>
                </div>
              </div>
            </v-card>

            <v-btn
              type="submit"
              color="primary"
              size="large"
              block
              height="48"
              class="font-weight-bold"
              :loading="submittingSubRecipe"
              :disabled="!subRecipeForm.name || !subRecipeForm.ingredients.length"
            >
              Guardar Sub-Receta y Habilitar en Platos
            </v-btn>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: CREAR ORDEN DE PRODUCCIÓN -->
    <v-dialog v-model="showProductionModal" max-width="550">
      <v-card>
        <v-card-title class="bg-primary text-white py-3 px-4 d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <v-icon>tabler-tools-kitchen-2</v-icon>
            <span>Nueva Producción de Sub-Receta</span>
          </div>
          <v-btn icon variant="text" color="white" size="small" @click="showProductionModal = false">
            <v-icon>tabler-x</v-icon>
          </v-btn>
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
              Producir y Descontar Insumos (FEFO + Rendimiento)
            </v-btn>
          </v-form>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- DIÁLOGO: REGISTRAR MERMA -->
    <v-dialog v-model="showWasteModal" max-width="500">
      <v-card>
        <v-card-title class="bg-error text-white py-3 px-4 d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <v-icon>tabler-trash-x</v-icon>
            <span>Registrar Merma / Desperdicio</span>
          </div>
          <v-btn icon variant="text" color="white" size="small" @click="showWasteModal = false">
            <v-icon>tabler-x</v-icon>
          </v-btn>
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
import { ref, reactive, computed, watch, onMounted } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

const activeTab = ref('production')
const productionOrders = ref([])
const kitchenWastes = ref([])
const subRecipeDishes = ref([])
const categories = ref([])
const submitting = ref(false)

const showProductionModal = ref(false)
const showWasteModal = ref(false)
const showSubRecipeModal = ref(false)
const submittingSubRecipe = ref(false)

// Formulario de creación de sub-receta
const subRecipeForm = reactive({
  name: '',
  category_id: null,
  kitchen_station_id: null,
  expected_yield_quantity: 1.0,
  unit_of_measure: 'porción',
  shelf_life_days: 7,
  ingredients: [],
})

// Insumos para la sub-receta
const ingredientSearch = ref('')
const ingredientOptions = ref([])
const loadingIngredients = ref(false)
const selectedIngredient = ref(null)
const ingredientPortion = ref(1)

const subRecipeTotalCost = computed(() => {
  return subRecipeForm.ingredients.reduce((acc, ing) => acc + (Number(ing.designated_cost) || 0), 0)
})

const subRecipeUnitCost = computed(() => {
  const yieldQty = Number(subRecipeForm.expected_yield_quantity) || 1
  return yieldQty > 0 ? subRecipeTotalCost.value / yieldQty : 0
})

const searchIngredients = async (query = '') => {
  loadingIngredients.value = true
  try {
    const { data } = await axios.get('/products/autocomplete', {
      params: { q: query },
    })
    ingredientOptions.value = (data.data || []).filter(item => !item.is_base_dish)
  } catch (error) {
    console.error('Error al buscar ingredientes:', error)
  } finally {
    loadingIngredients.value = false
  }
}

let ingredientDebounceTimer = null
watch(ingredientSearch, (val) => {
  clearTimeout(ingredientDebounceTimer)
  ingredientDebounceTimer = setTimeout(() => {
    searchIngredients(val || '')
  }, 300)
})

const addIngredientToSubRecipe = () => {
  if (!selectedIngredient.value || !ingredientPortion.value) return

  const portion = parseFloat(ingredientPortion.value)
  const unitCost = parseFloat(selectedIngredient.value.unit_cost || 0)
  const presentation = parseFloat(selectedIngredient.value.presentation || 1)
  const cost = parseFloat(((portion * unitCost) / presentation).toFixed(4))

  const existing = subRecipeForm.ingredients.find(i => i.product_id === selectedIngredient.value.id)
  if (existing) {
    existing.portion += portion
    existing.designated_cost = parseFloat((existing.designated_cost + cost).toFixed(4))
  } else {
    subRecipeForm.ingredients.push({
      product_id: selectedIngredient.value.id,
      name: selectedIngredient.value.name,
      portion: portion,
      unit_cost: unitCost,
      presentation: presentation,
      unit_of_measure: selectedIngredient.value.unit_of_measure || 'und',
      designated_cost: cost,
    })
  }

  selectedIngredient.value = null
  ingredientPortion.value = 1
  ingredientSearch.value = ''
  toast.success('Insumo añadido a la receta.')
}

const openSubRecipeModal = () => {
  subRecipeForm.name = ''
  subRecipeForm.category_id = null
  subRecipeForm.kitchen_station_id = null
  subRecipeForm.expected_yield_quantity = 1.0
  subRecipeForm.unit_of_measure = 'porción'
  subRecipeForm.shelf_life_days = 7
  subRecipeForm.ingredients = []
  searchIngredients('')
  showSubRecipeModal.value = true
}

const submitSubRecipe = async () => {
  if (!subRecipeForm.name.trim()) {
    toast.error('El nombre de la sub-receta es obligatorio.')
    return
  }
  if (!subRecipeForm.ingredients.length) {
    toast.error('Debes agregar al menos un insumo a la sub-receta.')
    return
  }

  submittingSubRecipe.value = true
  try {
    const { data } = await axios.post('/restaurant/production/sub-recipes', subRecipeForm)
    toast.success(data.message || 'Sub-receta creada con éxito.')
    showSubRecipeModal.value = false
    await loadData()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al guardar la sub-receta.')
  } finally {
    submittingSubRecipe.value = false
  }
}

// Formulario de producción
const productionForm = reactive({
  dish_id: null,
  batch_multiplier: 1.0,
  actual_quantity: null,
  notes: '',
})

// Formulario de mermas
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
    const [resProd, resWaste, resDishes, resCats] = await Promise.all([
      axios.get('/restaurant/production/orders'),
      axios.get('/restaurant/waste/records'),
      axios.get('/dishes?per_page=100'),
      axios.get('/categories', { params: { type: 'dishes' } }),
    ])
    productionOrders.value = resProd.data?.data || []
    kitchenWastes.value = resWaste.data?.data || []
    subRecipeDishes.value = (resDishes.data?.data || []).map(d => ({
      id: d.id,
      name: d.name,
      status: d.status,
    }))
    categories.value = resCats.data || []
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

<style scoped>
.restaurant-production-waste-view {
  min-height: 100vh;
}
.bg-light {
  background-color: #f8fafc !important;
}
</style>
