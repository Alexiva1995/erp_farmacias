<script setup>
import { onMounted, ref, watch } from 'vue'
import axios from '@/plugins/axios'

const laboratories = ref([])
const groups = ref([])
const loading = ref(false)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)

// Diálogos
const isLabDialogOpen = ref(false)
const isGroupDialogOpen = ref(false)

// Estados de edición
const labForm = ref({ id: null, name: '', group_id: null })
const groupForm = ref({ id: null, name: '', laboratory_ids: [] })

const fetchLabs = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/inventory/laboratories-manage', {
      params: { search: search.value, page: page.value }
    })
    laboratories.value = data.data
    lastPage.value = data.last_page
  } catch (error) {
    console.error('Error al cargar laboratorios:', error)
  } finally {
    loading.value = false
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
    // Al editar grupo, necesitamos saber qué laboratorios ya están en él
    groupForm.value = { 
      id: group.id, 
      name: group.name, 
      laboratory_ids: laboratories.value.filter(l => l.group_id === group.id).map(l => l.id) 
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

onMounted(() => {
  fetchLabs()
  fetchGroups()
})

watch(search, () => {
  page.value = 1
  fetchLabs()
})
</script>

<template>
  <VContainer fluid>
    <VRow class="mb-4" align="center">
      <VCol cols="12" md="4">
        <h2 class="text-h5 font-weight-bold">Gestión de Laboratorios</h2>
      </VCol>
      <VSpacer />
      <VCol cols="12" md="6" class="d-flex gap-2">
        <VTextField
          v-model="search"
          prepend-inner-icon="tabler-search"
          placeholder="Buscar laboratorio..."
          density="compact"
          hide-details
          class="flex-grow-1"
        />
        <VBtn color="secondary" prepend-icon="tabler-layers-intersect" @click="isGroupDialogOpen = true">
          Grupos
        </VBtn>
        <VBtn color="primary" prepend-icon="tabler-plus" @click="openLabEdit()">
          Nuevo
        </VBtn>
      </VCol>
    </VRow>

    <VCard border class="mt-4 rounded-lg shadow-sm">
      <VTable density="compact">
        <thead>
          <tr>
            <th class="font-weight-black">NOMBRE</th>
            <th class="font-weight-black">GRUPO CORPORATIVO</th>
            <th class="text-center font-weight-black">PRODUCTOS</th>
            <th class="text-right font-weight-black">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="lab in laboratories" :key="lab.id">
            <td class="text-sm font-weight-bold text-uppercase">{{ lab.name }}</td>
            <td>
              <VChip v-if="lab.group" color="primary" size="x-small" variant="tonal" class="font-weight-bold">
                {{ lab.group.name }}
              </VChip>
              <span v-else class="text-caption opacity-50">Sin grupo</span>
            </td>
            <td class="text-center">
              <VChip size="x-small" color="secondary">{{ lab.products_count }}</VChip>
            </td>
            <td class="text-right">
              <VBtn icon="tabler-edit" variant="text" size="small" color="primary" @click="openLabEdit(lab)" />
            </td>
          </tr>
        </tbody>
      </VTable>
      
      <VDivider />
      
      <div class="pa-2 d-flex justify-end align-center gap-2">
        <VBtn icon="tabler-chevron-left" variant="text" size="small" :disabled="page <= 1" @click="page--; fetchLabs()" />
        <span class="text-caption">Página {{ page }} de {{ lastPage }}</span>
        <VBtn icon="tabler-chevron-right" variant="text" size="small" :disabled="page >= lastPage" @click="page++; fetchLabs()" />
      </div>
    </VCard>

    <!-- DIÁLOGO LABORATORIO -->
    <VDialog v-model="isLabDialogOpen" max-width="500">
      <VCard title="Editar Laboratorio">
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
                clearable
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="isLabDialogOpen = false">Cancelar</VBtn>
          <VBtn color="primary" @click="saveLab">Guardar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- DIÁLOGO GRUPOS -->
    <VDialog v-model="isGroupDialogOpen" max-width="700">
      <VCard title="Gestionar Grupos de Laboratorios">
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <AppTextField v-model="groupForm.name" label="Nombre del Grupo" placeholder="Ej: Corporación FARMA" />
            </VCol>
            <VCol cols="12">
              <AppAutocomplete
                v-model="groupForm.laboratory_ids"
                :items="laboratories"
                item-title="name"
                item-value="id"
                label="Laboratorios en este Grupo"
                multiple
                chips
                closable-chips
              />
            </VCol>
          </VRow>
          
          <VDivider class="my-4" />
          <p class="text-caption font-italic">O selecciona un grupo existente para editarlo:</p>
          <div class="d-flex flex-wrap gap-2">
            <VChip 
              v-for="g in groups" 
              :key="g.id" 
              link 
              @click="openGroupEdit(g)"
              :color="groupForm.id === g.id ? 'primary' : 'secondary'"
            >
              {{ g.name }}
            </VChip>
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="isGroupDialogOpen = false">Cerrar</VBtn>
          <VBtn color="primary" @click="saveGroup">Guardar Grupo</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<style scoped>
.gap-2 { gap: 8px; }
</style>
