<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'
import AppFilterBase from '@/components/AppFilterBase.vue'
import AppEmptyState from '@/components/AppEmptyState.vue'

// ─── Estado principal ───────────────────────────────────────────────────────
const groups = ref([])
const products = ref([])
const loading = ref(false)
const isSaving = ref(false)
const dialog = ref(false)

// ─── Filtros y Paginación ───────────────────────────────────────────────────
const searchQuery = ref('')
const selectedStatusFilter = ref(null)
const page = ref(1)
const itemsPerPage = ref(10)
const searchProducts = ref('')
const searchBaseProduct = ref('')

const statusFilterOptions = [
  { title: 'Todos los estados', value: null },
  { title: 'Activos', value: true },
  { title: 'Inactivos', value: false },
]

// ─── Cabeceras de tabla ─────────────────────────────────────────────────────
const tableHeaders = [
  { title: 'ID', key: 'id', sortable: true, width: '70px' },
  { title: 'Nombre del Grupo', key: 'name', sortable: true },
  { title: 'Insumo Base Sustituido', key: 'base_product', sortable: false },
  { title: 'Selección', key: 'selection', sortable: false, align: 'center' },
  { title: 'Sabores / Opciones', key: 'options', sortable: false },
  { title: 'Estado', key: 'is_active', sortable: true, align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'end', width: '110px' },
]

// ─── Formulario de grupo ────────────────────────────────────────────────────
const defaultForm = () => ({
  id: null,
  name: '',
  description: '',
  base_product_id: null,
  min_selections: 1,
  max_selections: 1,
  is_active: true,
  options: [],
})

const form = ref(defaultForm())

// ─── Formulario de nueva opción ─────────────────────────────────────────────
const newOption = ref({
  name: '',
  modifier_type: 'substitute',
  extra_price_usd: 0,
  product_id: null,
  portion: 0,
  sort_order: 0,
  is_active: true,
})

// ─── Autocompletes filtrados ────────────────────────────────────────────────
const filteredProducts = computed(() => {
  if (!searchProducts.value) return products.value.slice(0, 50)
  const q = searchProducts.value.toLowerCase()
  return products.value.filter(p => p.name.toLowerCase().includes(q)).slice(0, 50)
})

const filteredBaseProducts = computed(() => {
  if (!searchBaseProduct.value) return products.value.slice(0, 50)
  const q = searchBaseProduct.value.toLowerCase()
  return products.value.filter(p => p.name.toLowerCase().includes(q)).slice(0, 50)
})

// ─── Grupos filtrados para la tabla ─────────────────────────────────────────
const filteredGroups = computed(() => {
  let list = [...groups.value]

  if (searchQuery.value && searchQuery.value.trim() !== '') {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(g => {
      const matchName = g.name?.toLowerCase().includes(q)
      const matchDesc = g.description?.toLowerCase().includes(q)
      const matchBase = g.base_product?.name?.toLowerCase().includes(q)
      const matchOpts = (g.options || []).some(o => 
        o.name?.toLowerCase().includes(q) || o.product?.name?.toLowerCase().includes(q)
      )
      return matchName || matchDesc || matchBase || matchOpts
    })
  }

  if (selectedStatusFilter.value !== null) {
    list = list.filter(g => Boolean(g.is_active) === Boolean(selectedStatusFilter.value))
  }

  return list
})

// ─── Carga de datos ─────────────────────────────────────────────────────────
const fetchGroups = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/modifier-groups')
    groups.value = data.data || []
  } catch {
    toast.error('No se pudieron cargar los grupos de modificadores.')
  } finally {
    loading.value = false
  }
}

const fetchProducts = async () => {
  try {
    const { data } = await axios.get('/products', { params: { per_page: 500, is_active: 1 } })
    products.value = data.data || data || []
  } catch {
    // Silencioso
  }
}

// ─── Abrir / Cerrar Diálogo ─────────────────────────────────────────────────
const openDialog = (group = null) => {
  if (group) {
    form.value = {
      id: group.id,
      name: group.name,
      description: group.description || '',
      base_product_id: group.base_product_id || null,
      min_selections: group.min_selections,
      max_selections: group.max_selections,
      is_active: group.is_active,
      options: (group.options || []).map(o => ({ ...o })),
    }
  } else {
    form.value = defaultForm()
  }
  resetNewOption()
  dialog.value = true
}

const resetNewOption = () => {
  newOption.value = {
    name: '',
    modifier_type: form.value.base_product_id ? 'substitute' : 'add',
    extra_price_usd: 0,
    product_id: null,
    portion: 0,
    sort_order: form.value.options.length,
    is_active: true,
  }
}

const addOption = () => {
  if (!newOption.value.name.trim()) {
    toast.warning('El nombre del sabor/opción es obligatorio.')
    return
  }
  form.value.options.push({ ...newOption.value })
  resetNewOption()
}

const removeOption = (index) => {
  form.value.options.splice(index, 1)
}

const productName = (productId) => {
  if (!productId) return '—'
  const p = products.value.find(p => p.id === productId)
  return p ? p.name : `ID ${productId}`
}

// ─── Guardar Grupo ──────────────────────────────────────────────────────────
const saveGroup = async () => {
  if (!form.value.name.trim()) {
    toast.warning('El nombre del grupo es obligatorio.')
    return
  }
  if (form.value.options.length === 0) {
    toast.warning('Agrega al menos una opción o sabor al grupo.')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      name: form.value.name,
      description: form.value.description || null,
      base_product_id: form.value.base_product_id || null,
      min_selections: Number(form.value.min_selections),
      max_selections: Number(form.value.max_selections),
      is_active: Boolean(form.value.is_active),
      options: form.value.options.map((o, idx) => ({
        ...o,
        modifier_type: form.value.base_product_id && o.modifier_type === 'add' ? 'substitute' : o.modifier_type,
        sort_order: idx,
      })),
    }

    if (form.value.id) {
      await axios.put(`/modifier-groups/${form.value.id}`, payload)
      toast.success('Grupo de modificadores actualizado.')
    } else {
      await axios.post('/modifier-groups', payload)
      toast.success('Grupo de modificadores creado.')
    }
    dialog.value = false
    fetchGroups()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error al guardar el grupo.')
  } finally {
    isSaving.value = false
  }
}

// ─── Eliminar Grupo ─────────────────────────────────────────────────────────
const deleteGroup = async (group) => {
  const result = await Swal.fire({
    title: '¿Eliminar grupo?',
    text: `Se eliminará "${group.name}" y todas sus opciones configuradas.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#d33',
    reverseButtons: true,
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`/modifier-groups/${group.id}`)
      toast.success('Grupo eliminado exitosamente.')
      fetchGroups()
    } catch {
      toast.error('No se pudo eliminar el grupo.')
    }
  }
}

const modifierTypes = [
  { title: 'Sustituir insumo de receta (misma porción)', value: 'substitute' },
  { title: 'Agregar ingrediente extra (add)', value: 'add' },
  { title: 'Quitar ingrediente (remove)', value: 'remove' },
  { title: 'Solo informativo / instrucción (info)', value: 'info' },
]

onMounted(() => {
  fetchGroups()
  fetchProducts()
})
</script>

<template>
  <div class="modifier-groups-view pb-12">

    <!-- ── Filtros y Buscador Premium (Estilo Inventario Productos) ────────── -->
    <AppFilterBase
      v-model:search="searchQuery"
      search-placeholder="Buscar por grupo, sabor o insumo..."
      :show-add="true"
      add-button-text="Nuevo Grupo"
      :show-advanced="true"
      :has-advanced-filters="selectedStatusFilter !== null"
      @clear="searchQuery = ''; selectedStatusFilter = null"
      @add="openDialog()"
    >
      <template #advanced-filters>
        <VCol cols="12" sm="6" md="4">
          <VSelect
            v-model="selectedStatusFilter"
            :items="statusFilterOptions"
            item-title="title"
            item-value="value"
            placeholder="Filtrar por Estado"
            clearable
            density="compact"
            hide-details
          />
        </VCol>
      </template>
    </AppFilterBase>

    <!-- ── Tabla Principal (Estilo VDataTableServer / Premium Table) ───────── -->
    <VCard variant="flat" class="border rounded-lg bg-surface overflow-hidden">
      <!-- Loading Skeleton en carga inicial -->
      <div v-if="loading && groups.length === 0" class="pa-6">
        <VSkeletonLoader type="table-row-divider@5" class="bg-transparent" />
      </div>

      <!-- Empty State estándar del proyecto -->
      <div v-else-if="!loading && filteredGroups.length === 0" class="pa-12 text-center">
        <AppEmptyState
          title="No se encontraron grupos"
          message="No hay grupos de modificadores registrados o que coincidan con la búsqueda."
          icon="tabler-adjustments-alt"
        />
      </div>

      <!-- Vista Tabla Desktop -->
      <div v-else class="d-none d-md-block">
        <VDataTable
          :headers="tableHeaders"
          :items="filteredGroups"
          :items-per-page="itemsPerPage"
          :page="page"
          density="compact"
          class="premium-table text-no-wrap"
        >
          <!-- ID -->
          <template #item.id="{ item }">
            <span class="font-weight-black text-primary text-xs font-mono">
              #{{ item.id }}
            </span>
          </template>

          <!-- Nombre y Descripción -->
          <template #item.name="{ item }">
            <div class="d-flex flex-column py-2">
              <span class="font-weight-black text-sm text-high-emphasis">
                {{ item.name }}
              </span>
              <span v-if="item.description" class="text-xs text-medium-emphasis">
                {{ item.description }}
              </span>
            </div>
          </template>

          <!-- Insumo Base Sustituido -->
          <template #item.base_product="{ item }">
            <div v-if="item.base_product || item.base_product_id">
              <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
                <VIcon icon="tabler-replace" start size="14" />
                {{ item.base_product?.name || productName(item.base_product_id) }}
              </VChip>
            </div>
            <span v-else class="text-disabled text-xs">— (Adición / Extra)</span>
          </template>

          <!-- Selección (min - max) -->
          <template #item.selection="{ item }">
            <VChip size="x-small" variant="outlined" color="secondary" class="font-weight-bold font-mono">
              {{ item.min_selections }}–{{ item.max_selections }} opción(es)
            </VChip>
          </template>

          <!-- Opciones / Sabores configurados -->
          <template #item.options="{ item }">
            <div class="d-flex flex-wrap align-center gap-1 py-1" style="max-width: 360px;">
              <template v-if="item.options && item.options.length > 0">
                <VChip
                  v-for="opt in item.options.slice(0, 4)"
                  :key="opt.id || opt.name"
                  size="x-small"
                  variant="tonal"
                  :color="opt.modifier_type === 'substitute' ? 'primary' : opt.modifier_type === 'add' ? 'success' : 'secondary'"
                  class="font-weight-medium"
                >
                  {{ opt.name }}
                  <span v-if="opt.product" class="opacity-70 ms-1 text-super-xs">
                    ({{ opt.product.name }})
                  </span>
                </VChip>
                <VChip
                  v-if="item.options.length > 4"
                  size="x-small"
                  variant="flat"
                  color="grey-lighten-3"
                  class="font-weight-bold text-disabled"
                >
                  +{{ item.options.length - 4 }} más
                </VChip>
              </template>
              <span v-else class="text-xs text-disabled">Sin opciones</span>
            </div>
          </template>

          <!-- Estado -->
          <template #item.is_active="{ item }">
            <VChip
              size="x-small"
              :color="item.is_active ? 'success' : 'secondary'"
              variant="flat"
              class="font-weight-black text-uppercase"
            >
              {{ item.is_active ? 'Activo' : 'Inactivo' }}
            </VChip>
          </template>

          <!-- Acciones (IconBtn estándar de la app) -->
          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-end gap-1">
              <IconBtn
                size="small"
                color="primary"
                variant="tonal"
                title="Editar grupo"
                @click="openDialog(item)"
              >
                <VIcon icon="tabler-edit" size="18" />
              </IconBtn>
              <IconBtn
                size="small"
                color="error"
                variant="tonal"
                title="Eliminar grupo"
                @click="deleteGroup(item)"
              >
                <VIcon icon="tabler-trash" size="18" />
              </IconBtn>
            </div>
          </template>
        </VDataTable>
      </div>

      <!-- Vista Móvil (Cards estilo inventario/platos) -->
      <div v-if="filteredGroups.length > 0" class="d-block d-md-none pa-3">
        <div class="d-flex flex-column gap-3">
          <VCard
            v-for="item in filteredGroups"
            :key="item.id"
            variant="flat"
            class="border rounded-lg pa-3"
          >
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center gap-2">
                <span class="font-mono text-xs font-weight-black text-primary">#{{ item.id }}</span>
                <span class="text-subtitle-2 font-weight-black text-high-emphasis">{{ item.name }}</span>
              </div>
              <VChip
                size="x-small"
                :color="item.is_active ? 'success' : 'secondary'"
                variant="flat"
                class="font-weight-black text-uppercase"
              >
                {{ item.is_active ? 'Activo' : 'Inactivo' }}
              </VChip>
            </div>

            <div v-if="item.base_product || item.base_product_id" class="mb-2">
              <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                <VIcon icon="tabler-replace" start size="12" />
                Sustituye: {{ item.base_product?.name || productName(item.base_product_id) }}
              </VChip>
            </div>

            <div class="d-flex flex-wrap gap-1 mb-3">
              <VChip
                v-for="opt in (item.options || [])"
                :key="opt.id || opt.name"
                size="x-small"
                variant="tonal"
                color="secondary"
              >
                {{ opt.name }}
              </VChip>
            </div>

            <div class="d-flex align-center justify-space-between pt-2 border-t">
              <span class="text-xs text-disabled">
                {{ item.min_selections }}–{{ item.max_selections }} opción(es)
              </span>
              <div class="d-flex gap-1">
                <IconBtn size="small" color="primary" variant="tonal" @click="openDialog(item)">
                  <VIcon icon="tabler-edit" size="16" />
                </IconBtn>
                <IconBtn size="small" color="error" variant="tonal" @click="deleteGroup(item)">
                  <VIcon icon="tabler-trash" size="16" />
                </IconBtn>
              </div>
            </div>
          </VCard>
        </div>
      </div>
    </VCard>

    <!-- ── Modal Diálogo Crear / Editar (Estilo ProductEditDialog) ────────── -->
    <VDialog v-model="dialog" max-width="840px" persistent scrollable>
      <VCard class="rounded-lg">
        <!-- Cabecera del Diálogo -->
        <VCardTitle class="d-flex align-center justify-space-between pa-4 bg-primary text-white">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-adjustments-alt" color="white" />
            <span class="text-subtitle-1 font-weight-black text-uppercase text-white">
              {{ form.id ? 'Editar Grupo de Modificadores' : 'Nuevo Grupo de Modificadores' }}
            </span>
          </div>
          <VBtn icon variant="text" color="white" size="small" @click="dialog = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-5 d-flex flex-column gap-4">

          <!-- Tarjeta 1: Información General del Grupo -->
          <VCard variant="flat" class="border rounded-lg pa-4">
            <div class="d-flex align-center justify-space-between mb-3">
              <h4 class="text-subtitle-2 font-weight-black text-high-emphasis text-uppercase">
                1. Información Básica del Grupo
              </h4>
              <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                Configuración
              </VChip>
            </div>

            <VRow dense>
              <VCol cols="12" sm="8">
                <AppTextField
                  v-model="form.name"
                  label="Nombre del Grupo *"
                  placeholder="Ej: Sabor de helado, Término de la carne, Tipo de pan"
                  density="compact"
                  required
                />
              </VCol>
              <VCol cols="12" sm="4">
                <div class="d-flex align-center justify-end h-100 pt-2">
                  <VSwitch
                    v-model="form.is_active"
                    label="Grupo Activo"
                    color="primary"
                    density="compact"
                    hide-details
                  />
                </div>
              </VCol>

              <VCol cols="12">
                <AppTextField
                  v-model="form.description"
                  label="Descripción (Opcional)"
                  placeholder="Ej: El cliente elige un sabor por cada bola de helado"
                  density="compact"
                />
              </VCol>

              <!-- Insumo base a sustituir en recetas -->
              <VCol cols="12">
                <VAutocomplete
                  v-model="form.base_product_id"
                  v-model:search="searchBaseProduct"
                  :items="filteredBaseProducts"
                  item-title="name"
                  item-value="id"
                  label="Insumo base que sustituye en la receta (Ej: Helado General)"
                  placeholder="Seleccionar insumo de la receta..."
                  clearable
                  density="compact"
                  prepend-inner-icon="tabler-replace"
                  hint="Si seleccionas un insumo base (ej: Helado General), cualquier sabor elegido heredará automáticamente los gramos exactos que tenga la receta de cada plato."
                  persistent-hint
                  no-data-text="No se encontraron productos en inventario"
                />
              </VCol>

              <VCol cols="12" sm="6" class="mt-2">
                <AppTextField
                  v-model="form.min_selections"
                  label="Mínimo de Opciones Requeridas"
                  type="number"
                  min="0"
                  density="compact"
                  hint="1 si es obligatorio elegir (ej: 1 sabor)"
                  persistent-hint
                />
              </VCol>
              <VCol cols="12" sm="6" class="mt-2">
                <AppTextField
                  v-model="form.max_selections"
                  label="Máximo de Opciones Permitidas"
                  type="number"
                  min="1"
                  density="compact"
                  hint="1 para selección única"
                  persistent-hint
                />
              </VCol>
            </VRow>
          </VCard>

          <!-- Tarjeta 2: Listado de Opciones / Sabores Actuales -->
          <VCard variant="flat" class="border rounded-lg pa-4">
            <div class="d-flex align-center justify-space-between mb-3">
              <h4 class="text-subtitle-2 font-weight-black text-high-emphasis text-uppercase d-flex align-center gap-2">
                <VIcon icon="tabler-list" size="18" color="primary" />
                2. Sabores / Opciones Configuradas ({{ form.options.length }})
              </h4>
            </div>

            <!-- Tabla de opciones añadidas -->
            <VTable v-if="form.options.length > 0" density="compact" class="border rounded-lg overflow-hidden mb-4">
              <thead>
                <tr>
                  <th class="text-left font-weight-bold text-xs uppercase">Sabor / Opción</th>
                  <th class="text-left font-weight-bold text-xs uppercase">Tipo</th>
                  <th class="text-left font-weight-bold text-xs uppercase">Insumo a Descontar</th>
                  <th class="text-left font-weight-bold text-xs uppercase">Porción</th>
                  <th class="text-right font-weight-bold text-xs uppercase">Precio Extra</th>
                  <th class="text-center font-weight-bold text-xs uppercase" style="width: 50px;">Quitar</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(opt, idx) in form.options" :key="idx">
                  <td class="font-weight-black text-sm">
                    {{ opt.name }}
                  </td>
                  <td>
                    <VChip
                      size="x-small"
                      :color="opt.modifier_type === 'substitute' ? 'primary' : opt.modifier_type === 'add' ? 'success' : opt.modifier_type === 'remove' ? 'error' : 'secondary'"
                      variant="tonal"
                      class="font-weight-medium"
                    >
                      {{ opt.modifier_type }}
                    </VChip>
                  </td>
                  <td>
                    <span v-if="opt.product_id" class="font-weight-medium text-xs text-high-emphasis">
                      {{ productName(opt.product_id) }}
                    </span>
                    <span v-else class="text-disabled text-xs">—</span>
                  </td>
                  <td>
                    <span v-if="form.base_product_id" class="text-xs text-primary font-weight-bold">
                      Gramos de receta
                    </span>
                    <span v-else-if="opt.portion > 0" class="text-xs font-mono">
                      {{ opt.portion }}
                    </span>
                    <span v-else class="text-disabled text-xs">—</span>
                  </td>
                  <td class="text-right font-mono text-xs">
                    <span v-if="opt.extra_price_usd > 0" class="font-weight-bold text-success">
                      +${{ Number(opt.extra_price_usd).toFixed(2) }}
                    </span>
                    <span v-else class="text-disabled">$0.00</span>
                  </td>
                  <td class="text-center">
                    <IconBtn size="small" color="error" variant="text" @click="removeOption(idx)">
                      <VIcon icon="tabler-trash" size="16" />
                    </IconBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>

            <div v-else class="pa-4 text-center text-disabled border rounded-lg mb-4">
              <VIcon icon="tabler-bulb" size="24" class="opacity-40 mb-1" />
              <p class="text-xs mb-0">Aún no hay sabores ni opciones configuradas en este grupo.</p>
            </div>

            <!-- Formulario Inline para agregar nuevo sabor -->
            <VCard variant="tonal" color="primary" class="pa-3 rounded-lg border">
              <div class="text-xs font-weight-black uppercase mb-3 d-flex align-center gap-1">
                <VIcon icon="tabler-plus" size="14" />
                Agregar Nuevo Sabor / Opción al Grupo
              </div>

              <VRow dense>
                <VCol cols="12" sm="5">
                  <AppTextField
                    v-model="newOption.name"
                    label="Nombre de la Opción *"
                    placeholder="Ej: Vainilla, Chocolate, Fresa"
                    density="compact"
                  />
                </VCol>
                <VCol cols="12" sm="4">
                  <VSelect
                    v-model="newOption.modifier_type"
                    :items="modifierTypes"
                    item-title="title"
                    item-value="value"
                    label="Tipo de Modificador"
                    density="compact"
                  />
                </VCol>
                <VCol cols="12" sm="3">
                  <AppTextField
                    v-model="newOption.extra_price_usd"
                    label="Precio Extra USD"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    density="compact"
                  />
                </VCol>

                <!-- Insumo del inventario a descontar -->
                <VCol cols="12" sm="8">
                  <VAutocomplete
                    v-model="newOption.product_id"
                    v-model:search="searchProducts"
                    :items="filteredProducts"
                    item-title="name"
                    item-value="id"
                    label="Insumo en Inventario a Descontar (Ej: Helado Vainilla)"
                    placeholder="Buscar producto de inventario..."
                    clearable
                    density="compact"
                    no-data-text="Sin resultados en inventario"
                  />
                </VCol>
                <VCol cols="12" sm="4" class="d-flex align-center justify-end pt-2">
                  <VBtn color="primary" variant="flat" size="small" block height="40" @click="addOption">
                    <VIcon icon="tabler-plus" start size="16" />
                    Agregar Opción
                  </VBtn>
                </VCol>
              </VRow>
            </VCard>
          </VCard>
        </VCardText>

        <VDivider />

        <!-- Acciones del Diálogo (Botones idénticos a los estándares) -->
        <VCardActions class="pa-4 bg-surface">
          <VSpacer />
          <VBtn
            variant="outlined"
            color="secondary"
            class="font-weight-bold"
            @click="dialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            class="font-weight-bold"
            :loading="isSaving"
            @click="saveGroup"
          >
            <VIcon icon="tabler-device-floppy" start size="18" />
            {{ form.id ? 'Guardar Cambios' : 'Crear Grupo' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.font-mono {
  font-family: monospace;
}
.text-super-xs {
  font-size: 0.65rem;
}
.premium-table :deep(th) {
  background-color: #f8fafc !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
