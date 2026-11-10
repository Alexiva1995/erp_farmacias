<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'

// ─── Estado principal ───────────────────────────────────────────────────────
const groups     = ref([])
const products   = ref([])
const loading    = ref(false)
const isSaving   = ref(false)
const dialog     = ref(false)
const searchProducts = ref('')

// ─── Formulario de grupo ────────────────────────────────────────────────────
const defaultForm = () => ({
  id: null,
  name: '',
  description: '',
  min_selections: 1,
  max_selections: 1,
  is_active: true,
  options: [],
})

const form = ref(defaultForm())

// ─── Formulario de nueva opción dentro del grupo ────────────────────────────
const newOption = ref({
  name: '',
  modifier_type: 'add',
  extra_price_usd: 0,
  product_id: null,
  portion: 0,
  sort_order: 0,
  is_active: true,
})

// ─── Productos filtrados para el autocomplete ───────────────────────────────
const filteredProducts = computed(() => {
  if (!searchProducts.value) return products.value.slice(0, 50)
  const q = searchProducts.value.toLowerCase()
  return products.value.filter(p => p.name.toLowerCase().includes(q)).slice(0, 50)
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
    // Silencioso — se reintenta al abrir el diálogo
  }
}

// ─── Abrir diálogo ──────────────────────────────────────────────────────────
const openDialog = (group = null) => {
  if (group) {
    form.value = {
      id: group.id,
      name: group.name,
      description: group.description || '',
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

// ─── Opciones del grupo ─────────────────────────────────────────────────────
const resetNewOption = () => {
  newOption.value = {
    name: '',
    modifier_type: 'add',
    extra_price_usd: 0,
    product_id: null,
    portion: 0,
    sort_order: form.value.options.length,
    is_active: true,
  }
}

const addOption = () => {
  if (!newOption.value.name.trim()) {
    toast.warning('El nombre de la opción es obligatorio.')
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

// ─── Guardar grupo ──────────────────────────────────────────────────────────
const saveGroup = async () => {
  if (!form.value.name.trim()) {
    toast.warning('El nombre del grupo es obligatorio.')
    return
  }
  if (form.value.options.length === 0) {
    toast.warning('Agrega al menos una opción al grupo.')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      name: form.value.name,
      description: form.value.description || null,
      min_selections: form.value.min_selections,
      max_selections: form.value.max_selections,
      is_active: form.value.is_active,
      options: form.value.options.map((o, idx) => ({
        ...o,
        sort_order: idx,
      })),
    }

    if (form.value.id) {
      await axios.put(`/modifier-groups/${form.value.id}`, payload)
      toast.success('Grupo actualizado correctamente.')
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

// ─── Eliminar grupo ─────────────────────────────────────────────────────────
const deleteGroup = async (group) => {
  const result = await Swal.fire({
    title: '¿Eliminar grupo?',
    text: `Se eliminará "${group.name}" y todas sus opciones.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#d33',
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`/modifier-groups/${group.id}`)
      toast.success('Grupo eliminado.')
      fetchGroups()
    } catch {
      toast.error('No se pudo eliminar el grupo.')
    }
  }
}

// ─── Tipos de modificador ────────────────────────────────────────────────────
const modifierTypes = [
  { title: 'Agregar ingrediente (add)', value: 'add' },
  { title: 'Quitar ingrediente (remove)', value: 'remove' },
  { title: 'Solo informativo (info)', value: 'info' },
]

onMounted(() => {
  fetchGroups()
  fetchProducts()
})
</script>

<template>
  <div class="modifier-groups-page pb-12">

    <!-- Encabezado -->
    <VCard variant="flat" class="border rounded-lg mb-6 pa-4">
      <div class="d-flex flex-wrap align-center justify-space-between gap-4">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="flat" size="44">
            <VIcon icon="tabler-adjustments-alt" color="white" size="24" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-uppercase mb-0">Grupos de Modificadores</h2>
            <div class="text-xs text-medium-emphasis">
              Configura opciones que el mesero elige al tomar una orden (ej: sabor de helado, término de la carne)
            </div>
          </div>
        </div>
        <VBtn color="primary" class="font-weight-bold" @click="openDialog()">
          <VIcon icon="tabler-plus" start size="18" />
          Nuevo Grupo
        </VBtn>
      </div>
    </VCard>

    <!-- Aviso instructivo -->
    <VAlert
      type="info"
      variant="tonal"
      class="mb-6 rounded-lg"
      icon="tabler-info-circle"
    >
      <strong>¿Cómo funciona?</strong>
      Crea un grupo (ej: <em>"Sabor de helado"</em>), agrega una opción por cada sabor y
      vincula cada opción al insumo correspondiente en el inventario (ej: "Helado Vainilla").
      Luego asigna el grupo a los platos desde <strong>Platos / Menú</strong>.
      Al tomar la orden, el sistema descontará automáticamente el insumo del sabor elegido.
    </VAlert>

    <!-- Lista de grupos -->
    <VRow v-if="groups.length > 0">
      <VCol
        v-for="group in groups"
        :key="group.id"
        cols="12"
        sm="6"
        md="4"
      >
        <VCard variant="flat" class="border rounded-lg pa-4 h-100 d-flex flex-column group-card">
          <div class="d-flex align-center justify-space-between mb-3">
            <VChip
              :color="group.is_active ? 'success' : 'secondary'"
              size="x-small"
              variant="flat"
              class="font-weight-black"
            >
              {{ group.is_active ? 'ACTIVO' : 'INACTIVO' }}
            </VChip>
            <div class="d-flex gap-1">
              <IconBtn size="small" color="primary" variant="tonal" @click="openDialog(group)">
                <VIcon icon="tabler-edit" size="16" />
              </IconBtn>
              <IconBtn size="small" color="error" variant="tonal" @click="deleteGroup(group)">
                <VIcon icon="tabler-trash" size="16" />
              </IconBtn>
            </div>
          </div>

          <h3 class="text-subtitle-1 font-weight-black text-high-emphasis mb-1">
            {{ group.name }}
          </h3>
          <p v-if="group.description" class="text-xs text-medium-emphasis mb-2">
            {{ group.description }}
          </p>

          <div class="text-xs text-disabled mb-3">
            Selección: {{ group.min_selections }}–{{ group.max_selections }} opciones
          </div>

          <!-- Opciones del grupo -->
          <div class="options-list">
            <div
              v-for="opt in (group.options || [])"
              :key="opt.id"
              class="option-row d-flex align-center gap-2 py-1 border-b"
            >
              <VIcon
                :icon="opt.modifier_type === 'add' ? 'tabler-plus-circle' : opt.modifier_type === 'remove' ? 'tabler-minus-circle' : 'tabler-info-circle'"
                :color="opt.modifier_type === 'add' ? 'success' : opt.modifier_type === 'remove' ? 'error' : 'info'"
                size="16"
              />
              <span class="text-xs font-weight-medium flex-grow-1">{{ opt.name }}</span>
              <span v-if="opt.product" class="text-xs text-disabled text-truncate" style="max-width:120px">
                → {{ opt.product.name }}
              </span>
              <VChip v-if="opt.extra_price_usd > 0" size="x-small" color="warning" variant="tonal">
                +${{ opt.extra_price_usd }}
              </VChip>
            </div>
          </div>

          <div v-if="!group.options || group.options.length === 0" class="text-xs text-disabled text-center py-2">
            Sin opciones configuradas
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Estado vacío -->
    <div v-else-if="!loading" class="pa-12 text-center text-disabled">
      <VIcon icon="tabler-adjustments-alt" size="64" class="opacity-30 mb-2" />
      <p>No hay grupos de modificadores. Crea el primero para comenzar.</p>
    </div>

    <div v-if="loading" class="d-flex justify-center pa-8">
      <VProgressCircular indeterminate color="primary" />
    </div>

    <!-- ─── Diálogo Crear / Editar ─────────────────────────────────────────── -->
    <VDialog v-model="dialog" max-width="680px" persistent scrollable>
      <VCard class="rounded-lg">
        <VCardTitle class="pa-4 bg-primary text-white d-flex align-center justify-space-between">
          <span class="text-subtitle-1 font-weight-black text-uppercase text-white">
            {{ form.id ? 'Editar Grupo' : 'Nuevo Grupo de Modificadores' }}
          </span>
          <VBtn icon variant="text" color="white" size="small" @click="dialog = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-5 d-flex flex-column gap-4">

          <!-- Datos del grupo -->
          <VRow dense>
            <VCol cols="12">
              <AppTextField
                v-model="form.name"
                label="Nombre del grupo *"
                placeholder="Ej: Sabor de helado, Término de la carne"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.description"
                label="Descripción (opcional)"
                placeholder="Ej: El cliente elige un sabor por bola"
              />
            </VCol>
            <VCol cols="6">
              <AppTextField
                v-model="form.min_selections"
                label="Mínimo de selecciones"
                type="number"
                min="0"
              />
            </VCol>
            <VCol cols="6">
              <AppTextField
                v-model="form.max_selections"
                label="Máximo de selecciones"
                type="number"
                min="1"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="Grupo activo"
                color="primary"
                density="compact"
                hide-details
              />
            </VCol>
          </VRow>

          <VDivider />

          <!-- Opciones existentes -->
          <div>
            <div class="text-subtitle-2 font-weight-black mb-2 d-flex align-center gap-2">
              <VIcon icon="tabler-list" size="18" color="primary" />
              Opciones del grupo ({{ form.options.length }})
            </div>

            <div v-if="form.options.length === 0" class="text-xs text-disabled text-center pa-4 border rounded-lg">
              Aún no hay opciones. Agrega la primera abajo.
            </div>

            <div
              v-for="(opt, idx) in form.options"
              :key="idx"
              class="option-edit-row d-flex align-center gap-2 py-2 border-b"
            >
              <VIcon
                :icon="opt.modifier_type === 'add' ? 'tabler-plus-circle' : opt.modifier_type === 'remove' ? 'tabler-minus-circle' : 'tabler-info-circle'"
                :color="opt.modifier_type === 'add' ? 'success' : opt.modifier_type === 'remove' ? 'error' : 'info'"
                size="18"
              />
              <div class="flex-grow-1">
                <div class="text-sm font-weight-medium">{{ opt.name }}</div>
                <div class="text-xs text-medium-emphasis">
                  Tipo: {{ opt.modifier_type }}
                  <span v-if="opt.product_id"> · Insumo: {{ productName(opt.product_id) }}</span>
                  <span v-if="opt.portion > 0"> · Porción: {{ opt.portion }}</span>
                  <span v-if="opt.extra_price_usd > 0"> · +${{ opt.extra_price_usd }}</span>
                </div>
              </div>
              <IconBtn size="small" color="error" variant="text" @click="removeOption(idx)">
                <VIcon icon="tabler-x" size="16" />
              </IconBtn>
            </div>
          </div>

          <!-- Agregar nueva opción -->
          <VCard variant="tonal" color="primary" class="pa-3 rounded-lg">
            <div class="text-subtitle-2 font-weight-black mb-3 d-flex align-center gap-2">
              <VIcon icon="tabler-plus" size="16" />
              Agregar opción
            </div>

            <VRow dense>
              <VCol cols="12" sm="6">
                <AppTextField
                  v-model="newOption.name"
                  label="Nombre de la opción *"
                  placeholder="Ej: Vainilla"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VSelect
                  v-model="newOption.modifier_type"
                  :items="modifierTypes"
                  item-title="title"
                  item-value="value"
                  label="Tipo"
                  density="compact"
                />
              </VCol>

              <!-- Vinculación al insumo de inventario -->
              <VCol cols="12">
                <VAutocomplete
                  v-model="newOption.product_id"
                  v-model:search="searchProducts"
                  :items="filteredProducts"
                  item-title="name"
                  item-value="id"
                  label="Insumo del inventario (opcional)"
                  placeholder="Buscar producto... ej: Helado Vainilla"
                  clearable
                  density="compact"
                  no-data-text="Sin resultados"
                />
              </VCol>

              <VCol cols="6">
                <AppTextField
                  v-model="newOption.portion"
                  label="Porción a descontar"
                  type="number"
                  step="0.01"
                  min="0"
                  density="compact"
                  hint="En la misma unidad del insumo (ej: gramos)"
                  persistent-hint
                />
              </VCol>
              <VCol cols="6">
                <AppTextField
                  v-model="newOption.extra_price_usd"
                  label="Costo extra USD"
                  type="number"
                  step="0.01"
                  min="0"
                  density="compact"
                  hint="0 si no hay recargo"
                  persistent-hint
                />
              </VCol>

              <VCol cols="12" class="d-flex justify-end">
                <VBtn color="primary" variant="flat" size="small" @click="addOption">
                  <VIcon icon="tabler-plus" start size="16" />
                  Agregar opción
                </VBtn>
              </VCol>
            </VRow>
          </VCard>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="dialog = false">
            Cancelar
          </VBtn>
          <VBtn color="primary" variant="flat" :loading="isSaving" @click="saveGroup">
            {{ form.id ? 'Actualizar grupo' : 'Crear grupo' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.group-card {
  transition: transform 0.2s ease;
}
.group-card:hover {
  transform: translateY(-2px);
}
.option-row,
.option-edit-row {
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
.option-row:last-child,
.option-edit-row:last-child {
  border-bottom: none;
}
</style>
