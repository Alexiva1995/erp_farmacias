<script setup>
import { onMounted, ref, watch, computed } from 'vue'
import axios from '@/plugins/axios'
import AppFilterBase from "@/components/AppFilterBase.vue"
import AppMobilePagination from "@/components/AppMobilePagination.vue"

const laboratories = ref([])
const allLaboratoriesForSelect = ref([]) // Lista completa para el modal
const groups = ref([])
const loading = ref(false)
const totalLabs = ref(0)
const searchQuery = ref('')
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref('name')
const orderBy = ref('asc')

// Diálogos
const isLabDialogOpen = ref(false)
const isGroupDialogOpen = ref(false)

// Estados de edición
const labForm = ref({ id: null, name: '', group_id: null })
const groupForm = ref({ id: null, name: '', laboratory_ids: [] })

const headers = [
  { title: "ID", key: "id", sortable: true, cellClass: 'font-weight-black text-primary' },
  { title: "Laboratorio", key: "name", sortable: true },
  { title: "Grupo Corporativo", key: "group.name", sortable: false },
  { title: "Productos", key: "products_count", sortable: true, align: 'center' },
  { title: "Acciones", key: "actions", sortable: false, align: 'right' },
]

const fetchLabs = async () => {
  loading.value = true
  try {
    const params = {
      search: searchQuery.value,
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value
    }
    const { data } = await axios.get('/inventory/laboratories-manage', { params })
    laboratories.value = data.data
    totalLabs.value = data.total
  } catch (error) {
    console.error('Error al cargar laboratorios:', error)
  } finally {
    loading.value = false
  }
}

// Función para traer TODOS los laboratorios para el selector del grupo
const fetchAllLabsForSelect = async () => {
  try {
    const { data } = await axios.get('/laboratories') // Endpoint que trae todos sin paginar
    allLaboratoriesForSelect.value = data
  } catch (error) {
    console.error('Error al cargar todos los laboratorios:', error)
  }
}

const fetchGroups = async () => {
  try {
    const { data } = await axios.get('/inventory/laboratories-manage/groups')
    groups.value = data
  } catch (error) {
    console.error('Error al cargar grupos:', error)
  }
}

const openLabEdit = (lab = null) => {
  if (lab) {
    labForm.value = { id: lab.id, name: lab.name, group_id: lab.group_id }
  } else {
    labForm.value = { id: null, name: '', group_id: null }
  }
  isLabDialogOpen.value = true
}

const openGroupEdit = (group = null) => {
  if (group) {
    groupForm.value = { 
      id: group.id, 
      name: group.name, 
      laboratory_ids: group.laboratories?.map(l => l.id) || []
    }
  } else {
    groupForm.value = { id: null, name: '', laboratory_ids: [] }
  }
  isGroupDialogOpen.value = true
}

const saveLab = async () => {
  try {
    await axios.post('/inventory/laboratories-manage', labForm.value)
    isLabDialogOpen.value = false
    fetchLabs()
  } catch (error) {
    alert('Error al guardar laboratorio')
  }
}

const saveGroup = async () => {
  try {
    await axios.post('/inventory/laboratories-manage/groups', groupForm.value)
    isGroupDialogOpen.value = false
    fetchLabs()
    fetchGroups()
  } catch (error) {
    alert('Error al guardar grupo')
  }
}

const updateTableOptions = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  if (options.sortBy?.length) {
    sortBy.value = options.sortBy[0].key
    orderBy.value = options.sortBy[0].order
  }
}

const handleClearFilters = () => {
  searchQuery.value = ''
  page.value = 1
  fetchLabs()
}

onMounted(() => {
  fetchLabs()
  fetchGroups()
  fetchAllLabsForSelect()
})

watch([searchQuery], () => {
  page.value = 1
  fetchLabs()
})

watch([page, itemsPerPage, sortBy, orderBy], () => {
  fetchLabs()
})
</script>

<template>
  <div>
    <!-- FILTROS ESTILO PRODUCTS -->
    <AppFilterBase
      v-model:search="searchQuery"
      :show-add="true"
      add-button-text="Añadir Laboratorio"
      search-placeholder="Buscar laboratorio por nombre..."
      @clear="handleClearFilters"
      @add="openLabEdit()"
    >
      <template #search-extra>
        <VCol cols="auto">
          <VBtn color="secondary" variant="tonal" prepend-icon="tabler-layers-intersect" @click="isGroupDialogOpen = true">
            Gestionar Grupos
          </VBtn>
        </VCol>
      </template>
    </AppFilterBase>

    <VCard class="rounded-lg border shadow-sm overflow-hidden mt-4">
      <!-- VISTA ESCRITORIO -->
      <div class="d-none d-md-block">
        <VDataTableServer
          :headers="headers"
          :items="laboratories"
          :items-length="totalLabs"
          :loading="loading"
          :items-per-page="itemsPerPage"
          density="compact"
          class="text-no-wrap"
          @update:options="updateTableOptions"
        >
          <template #item.group.name="{ item }">
            <VChip v-if="item.group" color="primary" size="x-small" variant="tonal" class="font-weight-bold">
              {{ item.group.name }}
            </VChip>
            <span v-else class="text-caption opacity-50">Sin grupo</span>
          </template>

          <template #item.products_count="{ item }">
            <VChip size="x-small" :color="item.products_count > 0 ? 'info' : 'secondary'" variant="flat">
              {{ item.products_count }} <span class="ms-1 d-none d-lg-inline">PRODUCTOS</span>
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex justify-end gap-1">
              <IconBtn @click="openLabEdit(item)" color="primary" size="small">
                <VIcon icon="tabler-edit" size="18" />
                <VTooltip activator="parent">Editar Laboratorio</VTooltip>
              </IconBtn>
            </div>
          </template>
        </VDataTableServer>
      </div>

      <!-- VISTA MÓVIL (TARJETAS COMPACTAS) -->
      <div class="d-block d-md-none pa-2">
        <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-2" />
        
        <div v-if="laboratories.length === 0 && !loading" class="text-center py-8 text-disabled">
          No se encontraron laboratorios.
        </div>

        <div class="d-flex flex-column gap-2">
          <VCard
            v-for="item in laboratories"
            :key="item.id"
            variant="flat"
            class="lab-mobile-card border mb-1"
          >
            <div class="pa-3">
              <div class="d-flex justify-space-between align-start mb-2">
                <div>
                  <div class="text-xs font-weight-black text-primary mb-1">ID: {{ item.id }}</div>
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                    {{ item.name.toUpperCase() }}
                  </h3>
                </div>
                <VChip v-if="item.group" color="primary" size="x-small" variant="tonal" class="font-weight-bold">
                  {{ item.group.name }}
                </VChip>
              </div>

              <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded border-dashed-thin">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-disabled text-uppercase font-weight-black">Productos Asociados</span>
                  <span class="text-base font-weight-black text-info">
                    {{ item.products_count }} <small class="text-super-xs">SKUS</small>
                  </span>
                </div>
                <div class="d-flex gap-1">
                  <VBtn icon="tabler-edit" color="primary" variant="tonal" size="small" @click="openLabEdit(item)" />
                </div>
              </div>
            </div>
          </VCard>
        </div>

        <div class="mt-4">
          <AppMobilePagination
            :page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalLabs"
            :loading="loading"
            @change="(options) => updateTableOptions(options)"
          />
        </div>
      </div>
    </VCard>

    <!-- DIÁLOGO LABORATORIO -->
    <VDialog v-model="isLabDialogOpen" max-width="500" persistent>
      <VCard title="Configurar Laboratorio">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField v-model="labForm.name" label="Nombre del Laboratorio" placeholder="Ej: Bayer" />
            </VCol>
            <VCol cols="12">
              <AppAutocomplete
                v-model="labForm.group_id"
                :items="groups"
                item-title="name"
                item-value="id"
                label="Grupo Corporativo (Opcional)"
                placeholder="Seleccionar integración..."
                clearable
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="isLabDialogOpen = false">Cancelar</VBtn>
          <VBtn color="primary" @click="saveLab">Guardar Cambios</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- DIÁLOGO GRUPOS -->
    <VDialog v-model="isGroupDialogOpen" max-width="600">
      <VCard class="rounded-xl overflow-hidden shadow-xl border-0">
        <VCardTitle class="pa-6 bg-var-theme-background d-flex align-center">
          <VAvatar color="primary" variant="tonal" class="me-4" size="48">
            <VIcon icon="tabler-layers-intersect" size="24" />
          </VAvatar>
          <div>
             <div class="text-h6 font-weight-black leading-tight">Configuración de Grupo</div>
             <div class="text-xs text-disabled font-weight-bold text-uppercase ls-1">Gestión Corporativa</div>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="text" color="disabled" @click="isGroupDialogOpen = false" />
        </VCardTitle>

        <VCardText class="pa-6 pt-8">
          <VRow>
            <VCol cols="12">
              <p class="text-xs font-weight-black text-primary text-uppercase mb-2 ls-1">Identificación</p>
              <AppTextField 
                v-model="groupForm.name" 
                label="Nombre del Grupo Corporativo" 
                placeholder="Ej: Consorcio Farmacéutico Global" 
                persistent-placeholder
              />
            </VCol>
            
            <VCol cols="12" class="mt-2">
              <p class="text-xs font-weight-black text-primary text-uppercase mb-2 ls-1">Asignación de Marcas/Laboratorios</p>
              <AppAutocomplete
                v-model="groupForm.laboratory_ids"
                :items="allLaboratoriesForSelect"
                item-title="name"
                item-value="id"
                placeholder="Busca y añade los laboratorios que pertenecen a este grupo..."
                multiple
                chips
                closable-chips
                density="comfortable"
                variant="outlined"
                persistent-placeholder
              />
              <div class="mt-2 d-flex align-center gap-1 text-super-xs text-disabled opacity-80">
                <VIcon icon="tabler-info-circle-filled" size="14" />
                <span>Los laboratorios seleccionados se agruparán bajo este nombre corporativo en los reportes.</span>
              </div>
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-6">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" class="px-6 font-weight-black" @click="isGroupDialogOpen = false">
            DESCARTAR
          </VBtn>
          <VBtn color="primary" variant="elevated" class="px-8 font-weight-black" elevation="4" @click="saveGroup">
            GUARDAR CONFIGURACIÓN
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.lab-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs { font-size: 0.75rem !important; }
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
