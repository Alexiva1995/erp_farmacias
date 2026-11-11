<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'
import AppFilterBase from '@/components/AppFilterBase.vue'
import AppEmptyState from '@/components/AppEmptyState.vue'

// ─── Estado principal ───────────────────────────────────────────────────────
const groups = ref([])
const loading = ref(false)
const isSaving = ref(false)
const dialog = ref(false)

// ─── Filtros y Paginación ───────────────────────────────────────────────────
const searchQuery = ref('')
const selectedStatusFilter = ref(null)
const page = ref(1)
const itemsPerPage = ref(10)

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

// ─── Catálogo reactivo de productos para autocompletes ───────────────────────
const productCatalog = ref(new Map())
const baseProductOptions = ref([])
const optionProductOptions = ref([])
const searchBaseInput = ref('')
const searchOptionInput = ref('')
const loadingBaseProducts = ref(false)
const loadingOptionProducts = ref(false)

let baseDebounceTimer = null
let optionDebounceTimer = null

// Registro de productos en el mapa de nombres
const registerProducts = (items) => {
  if (!Array.isArray(items)) return
  items.forEach((p) => {
    if (p && p.id) {
      productCatalog.value.set(p.id, p.name || `ID #${p.id}`)
    }
  })
}

// Búsqueda en servidor de insumo base a sustituir
const searchBaseProducts = async (q = '') => {
  loadingBaseProducts.value = true
  try {
    const { data } = await axios.get('/products/autocomplete', {
      params: { q: q ? q.trim() : '', include_sub_recipes: 1 },
    })
    const items = data.data || data || []
    registerProducts(items)

    // Preservar el producto actualmente seleccionado si existe
    if (form.value.base_product_id && !items.some(i => i.id === form.value.base_product_id)) {
      items.unshift({
        id: form.value.base_product_id,
        name: productName(form.value.base_product_id),
      })
    }

    baseProductOptions.value = items
  } catch (error) {
    console.error('Error al buscar insumos base:', error)
  } finally {
    loadingBaseProducts.value = false
  }
}

// Búsqueda en servidor de insumo a descontar por sabor
const searchOptionProducts = async (q = '') => {
  loadingOptionProducts.value = true
  try {
    const { data } = await axios.get('/products/autocomplete', {
      params: { q: q ? q.trim() : '', include_sub_recipes: 1 },
    })
    const items = data.data || data || []
    registerProducts(items)

    if (newOption.value.product_id && !items.some(i => i.id === newOption.value.product_id)) {
      items.unshift({
        id: newOption.value.product_id,
        name: productName(newOption.value.product_id),
      })
    }

    optionProductOptions.value = items
  } catch (error) {
    console.error('Error al buscar insumos de opciones:', error)
  } finally {
    loadingOptionProducts.value = false
  }
}

// Observadores de búsqueda reactiva en tiempo real con debounce
watch(searchBaseInput, (val) => {
  clearTimeout(baseDebounceTimer)
  baseDebounceTimer = setTimeout(() => {
    searchBaseProducts(val || '')
  }, 250)
})

watch(searchOptionInput, (val) => {
  clearTimeout(optionDebounceTimer)
  optionDebounceTimer = setTimeout(() => {
    searchOptionProducts(val || '')
  }, 250)
})

// ─── Formulario de grupo (sin descripción) ──────────────────────────────────
const defaultForm = () => ({
  id: null,
  name: '',
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

// ─── Grupos filtrados para la tabla ─────────────────────────────────────────
const filteredGroups = computed(() => {
  let list = [...groups.value]

  if (searchQuery.value && searchQuery.value.trim() !== '') {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter((g) => {
      const matchName = g.name?.toLowerCase().includes(q)
      const matchBase = g.base_product?.name?.toLowerCase().includes(q)
      const matchOpts = (g.options || []).some((o) =>
        o.name?.toLowerCase().includes(q) || o.product?.name?.toLowerCase().includes(q)
      )
      return matchName || matchBase || matchOpts
    })
  }

  if (selectedStatusFilter.value !== null) {
    list = list.filter((g) => Boolean(g.is_active) === Boolean(selectedStatusFilter.value))
  }

  return list
})

// ─── Carga de grupos ────────────────────────────────────────────────────────
const fetchGroups = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/modifier-groups')
    const list = data.data || []
    groups.value = list

    // Registrar en caché todos los productos que vengan en los grupos
    list.forEach((g) => {
      if (g.base_product) {
        productCatalog.value.set(g.base_product.id, g.base_product.name)
      }
      (g.options || []).forEach((opt) => {
        if (opt.product) {
          productCatalog.value.set(opt.product.id, opt.product.name)
        }
      })
    })
  } catch {
    toast.error('No se pudieron cargar los grupos de modificadores.')
  } finally {
    loading.value = false
  }
}

// ─── Abrir / Cerrar Diálogo ─────────────────────────────────────────────────
const openDialog = (group = null) => {
  if (group) {
    form.value = {
      id: group.id,
      name: group.name,
      base_product_id: group.base_product_id || null,
      min_selections: group.min_selections,
      max_selections: group.max_selections,
      is_active: group.is_active,
      options: (group.options || []).map((o) => ({ ...o })),
    }

    if (group.base_product) {
      productCatalog.value.set(group.base_product.id, group.base_product.name)
    }
  } else {
    form.value = defaultForm()
  }

  resetNewOption()
  dialog.value = true

  // Cargar catálogo inicial de opciones para autocompletes
  searchBaseProducts('')
  searchOptionProducts('')
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
  return productCatalog.value.get(productId) || `ID #${productId}`
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
      description: null,
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
            variant="outlined"
            density="comfortable"
            class="rounded-lg"
            clearable
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

          <!-- Nombre -->
          <template #item.name="{ item }">
            <div class="py-2">
              <span class="font-weight-black text-sm text-high-emphasis">
                {{ item.name }}
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
                  <span v-if="opt.product || opt.product_id" class="opacity-70 ms-1 text-super-xs">
                    ({{ opt.product?.name || productName(opt.product_id) }})
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

    <!-- ── Modal Diálogo Crear / Editar (Estilo ProductEditDialog Premium) ──── -->
    <VDialog
      v-model="dialog"
      max-width="860px"
      persistent
      scrollable
      content-class="d-flex"
    >
      <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
        <!-- Cabecera con degradado del tema -->
        <VCardTitle class="pa-0">
          <div class="header-gradient d-flex align-center shadow-sm pa-4">
            <VAvatar
              color="white"
              variant="flat"
              size="40"
              class="me-3 elevation-1 rounded-lg"
            >
              <VIcon
                icon="tabler-adjustments-alt"
                size="24"
                class="modal-avatar-icon"
              />
            </VAvatar>
            <div class="d-flex flex-column leading-none">
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 uppercase">
                {{ form.id ? 'Editar Grupo de Modificadores' : 'Añadir Nuevo Grupo de Modificadores' }}
              </h2>
              <div class="d-flex align-center gap-2 mt-1">
                <span
                  class="text-white opacity-75 uppercase font-weight-bold"
                  style="font-size: 0.65rem; letter-spacing: 0.05em;"
                >
                  Gestión de Sabores y Personalizaciones para Platos de Restaurante
                </span>
              </div>
            </div>
            <VSpacer />
            <VBtn
              icon="tabler-x"
              variant="text"
              color="white"
              size="small"
              @click="dialog = false"
            />
          </div>
        </VCardTitle>

        <!-- Contenido del Diálogo con fondo suave y tarjetas internas -->
        <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-6">

          <!-- 1. Información General del Grupo -->
          <div class="d-flex flex-column gap-3">
            <div class="d-flex align-center gap-2">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">
                Información del Grupo
              </span>
            </div>

            <VCard variant="flat" class="pa-4 pa-sm-5 bg-surface rounded-xl border shadow-sm">
              <VRow dense>
                <!-- Nombre del Grupo -->
                <VCol cols="12" md="8">
                  <VTextField
                    v-model="form.name"
                    label="Nombre del Grupo *"
                    placeholder="Ej: Sabor de Helado"
                    variant="outlined"
                    density="comfortable"
                    class="rounded-lg font-weight-black"
                    hide-details="auto"
                    required
                  />
                </VCol>

                <!-- Switch Activo -->
                <VCol cols="12" md="4" class="d-flex align-center justify-md-end pt-1">
                  <VSwitch
                    v-model="form.is_active"
                    label="Grupo Activo"
                    color="primary"
                    density="compact"
                    hide-details
                  />
                </VCol>

                <!-- Insumo Base que sustituye en recetas con búsqueda dinámica contra servidor -->
                <VCol cols="12">
                  <VAutocomplete
                    v-model="form.base_product_id"
                    v-model:search="searchBaseInput"
                    :items="baseProductOptions"
                    item-title="name"
                    item-value="id"
                    :loading="loadingBaseProducts"
                    :custom-filter="() => true"
                    label="¿Qué Insumo de la Receta Sustituye este Grupo? (Ej: Helado General)"
                    placeholder="Escribe para buscar cualquier insumo en el inventario..."
                    variant="outlined"
                    density="comfortable"
                    class="rounded-lg font-weight-bold"
                    clearable
                    prepend-inner-icon="tabler-replace"
                    hint="Al seleccionar un insumo base, los sabores elegidos heredarán automáticamente los gramos de la receta de cada plato sin tener que indicar porción por sabor."
                    persistent-hint
                    no-data-text="Escribe el nombre del insumo para buscar..."
                  />
                </VCol>

                <!-- Mínimo y Máximo de Selecciones -->
                <VCol cols="12" sm="6" class="mt-2">
                  <VTextField
                    v-model="form.min_selections"
                    label="Mínimo de Opciones Requeridas"
                    type="number"
                    min="0"
                    variant="outlined"
                    density="comfortable"
                    class="rounded-lg"
                    hint="1 si es obligatorio elegir (ej: 1 sabor)"
                    persistent-hint
                  />
                </VCol>
                <VCol cols="12" sm="6" class="mt-2">
                  <VTextField
                    v-model="form.max_selections"
                    label="Máximo de Opciones Permitidas"
                    type="number"
                    min="1"
                    variant="outlined"
                    density="comfortable"
                    class="rounded-lg"
                    hint="1 para selección única"
                    persistent-hint
                  />
                </VCol>
              </VRow>
            </VCard>
          </div>

          <!-- 2. Sabores y Opciones del Grupo -->
          <div class="d-flex flex-column gap-3">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-2">
                <div class="header-indicator primary shadow-sm" />
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">
                  Sabores / Opciones Configuradas ({{ form.options.length }})
                </span>
              </div>
            </div>

            <VCard variant="flat" class="pa-4 pa-sm-5 bg-surface rounded-xl border shadow-sm">
              <!-- Tabla de Opciones añadidas -->
              <VTable v-if="form.options.length > 0" density="compact" class="border rounded-lg overflow-hidden mb-4">
                <thead>
                  <tr class="bg-light">
                    <th class="text-left font-weight-bold text-xs uppercase py-2">Sabor / Opción</th>
                    <th class="text-left font-weight-bold text-xs uppercase py-2">Tipo</th>
                    <th class="text-left font-weight-bold text-xs uppercase py-2">Insumo a Descontar</th>
                    <th class="text-left font-weight-bold text-xs uppercase py-2">Porción</th>
                    <th class="text-right font-weight-bold text-xs uppercase py-2">Precio Extra</th>
                    <th class="text-center font-weight-bold text-xs uppercase py-2" style="width: 50px;">Quitar</th>
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

              <div v-else class="pa-6 text-center text-disabled border rounded-lg mb-4 bg-light">
                <VIcon icon="tabler-bulb" size="28" class="opacity-40 mb-1" />
                <p class="text-xs mb-0 font-weight-medium">Aún no hay sabores ni opciones configuradas en este grupo.</p>
              </div>

              <!-- Bloque para Añadir Nuevo Sabor con inputs flotantes del sistema -->
              <div class="pa-4 bg-light rounded-xl border">
                <div class="text-xs font-weight-black uppercase mb-3 d-flex align-center gap-1 text-primary">
                  <VIcon icon="tabler-plus" size="16" />
                  Añadir Sabor u Opción
                </div>

                <VRow dense>
                  <VCol cols="12" md="5">
                    <VTextField
                      v-model="newOption.name"
                      label="Nombre de la Opción *"
                      placeholder="Ej: Vainilla, Chocolate, Fresa"
                      variant="outlined"
                      density="comfortable"
                      class="rounded-lg font-weight-black bg-surface"
                      hide-details="auto"
                    />
                  </VCol>
                  <VCol cols="12" md="4">
                    <VSelect
                      v-model="newOption.modifier_type"
                      :items="modifierTypes"
                      item-title="title"
                      item-value="value"
                      label="Tipo de Modificador"
                      variant="outlined"
                      density="comfortable"
                      class="rounded-lg bg-surface"
                      hide-details="auto"
                    />
                  </VCol>
                  <VCol cols="12" md="3">
                    <VTextField
                      v-model="newOption.extra_price_usd"
                      label="Precio Extra USD"
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder="0.00"
                      variant="outlined"
                      density="comfortable"
                      class="rounded-lg bg-surface"
                      hide-details="auto"
                    />
                  </VCol>

                  <!-- Insumo del inventario a descontar con búsqueda dinámica contra servidor -->
                  <VCol cols="12" md="8">
                    <VAutocomplete
                      v-model="newOption.product_id"
                      v-model:search="searchOptionInput"
                      :items="optionProductOptions"
                      item-title="name"
                      item-value="id"
                      :loading="loadingOptionProducts"
                      :custom-filter="() => true"
                      label="Insumo en Inventario a Descontar (Ej: Helado Vainilla)"
                      placeholder="Escribe para buscar cualquier insumo en el inventario..."
                      variant="outlined"
                      density="comfortable"
                      class="rounded-lg bg-surface"
                      clearable
                      hide-details="auto"
                      no-data-text="Escribe el nombre del insumo para buscar..."
                    />
                  </VCol>
                  <VCol cols="12" md="4" class="d-flex align-center justify-end">
                    <VBtn
                      color="primary"
                      variant="flat"
                      height="48"
                      block
                      class="font-weight-black rounded-lg text-button uppercase shadow-primary"
                      @click="addOption"
                    >
                      <VIcon icon="tabler-plus" start size="18" />
                      Agregar Sabor
                    </VBtn>
                  </VCol>
                </VRow>
              </div>
            </VCard>
          </div>
        </VCardText>

        <!-- Botones de Acción Estándar de la App -->
        <VCardActions class="pa-4 pa-sm-6 bg-surface border-t">
          <VRow dense class="w-100 ma-0">
            <VCol cols="6" class="pa-1">
              <VBtn
                color="secondary"
                variant="outlined"
                height="48"
                block
                class="font-weight-black rounded-lg text-button uppercase"
                @click="dialog = false"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="6" class="pa-1">
              <VBtn
                color="primary"
                variant="flat"
                height="48"
                block
                class="font-weight-black rounded-lg shadow-primary text-button uppercase"
                :loading="isSaving"
                @click="saveGroup"
              >
                <VIcon icon="tabler-device-floppy" start size="18" />
                {{ form.id ? 'Guardar Cambios' : 'Crear Grupo' }}
              </VBtn>
            </VCol>
          </VRow>
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
.letter-spacing-1 {
  letter-spacing: 1px !important;
}
.uppercase {
  text-transform: uppercase;
}
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-secondary)) 0%, rgb(var(--v-theme-primary)) 100%) !important;
}
.detail-dialog-card {
  border-radius: 12px !important;
}
.modal-avatar-icon {
  color: rgb(var(--v-theme-primary)) !important;
}
.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}
.header-indicator {
  border-radius: 8px !important;
  block-size: 16px;
  inline-size: 4px;
}
.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}
.premium-table :deep(th) {
  background-color: #f8fafc !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
