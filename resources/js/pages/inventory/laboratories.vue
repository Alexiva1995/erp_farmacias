<script setup>
import { onMounted, ref, watch, computed } from 'vue'
import axios from '@/plugins/axios'
import AppFilterBase from "@/components/AppFilterBase.vue"
import AppMobilePagination from "@/components/AppMobilePagination.vue"
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";

const laboratories = ref([])
const allLaboratoriesForSelect = ref([])
const groups = ref([])
const loading = ref(false)
const totalLabs = ref(0)
const searchQuery = ref('')
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref('name')
const orderBy = ref('asc')

const isLabDialogOpen = ref(false)
const isGroupDialogOpen = ref(false)

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
    const params = { search: searchQuery.value, page: page.value, itemsPerPage: itemsPerPage.value, sortBy: sortBy.value, orderBy: orderBy.value }
    const { data } = await axios.get('/inventory/laboratories-manage', { params })
    laboratories.value = data.data
    totalLabs.value = data.total
  } catch (error) { console.error(error) } finally { loading.value = false }
}

const fetchAllLabsForSelect = async () => {
  try {
    const { data } = await axios.get('/laboratories')
    allLaboratoriesForSelect.value = data
  } catch (error) { console.error(error) }
}

const fetchGroups = async () => {
  try {
    const { data } = await axios.get('/inventory/laboratories-manage/groups')
    groups.value = data
  } catch (error) { console.error(error) }
}

const openLabEdit = (lab = null) => {
  labForm.value = lab ? { id: lab.id, name: lab.name, group_id: lab.group_id } : { id: null, name: '', group_id: null }
  isLabDialogOpen.value = true
}

const deleteLab = async (id) => {
  const result = await Swal.fire({
    title: "¿Borrar laboratorio?",
    text: "Los productos asociados quedarán sin laboratorio.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, borrar",
    cancelButtonText: "Cancelar",
    customClass: {
      confirmButton: 'v-btn v-btn--variant-flat bg-error text-white h-auto py-2 px-6 rounded-lg font-weight-black uppercase ms-3',
      cancelButton: 'v-btn v-btn--variant-tonal text-secondary h-auto py-2 px-6 rounded-lg font-weight-black uppercase'
    }
  });
  if (result.isConfirmed) {
    try {
      await axios.delete(`/inventory/laboratories-manage/${id}`)
      toast.success("Eliminado")
      fetchLabs()
    } catch (error) { toast.error("Error") }
  }
}

const openGroupEdit = (group = null) => {
  groupForm.value = group ? { id: group.id, name: group.name, laboratory_ids: group.laboratories?.map(l => l.id) || [] } : { id: null, name: '', laboratory_ids: [] }
  isGroupDialogOpen.value = true
}

const saveLab = async () => {
  try {
    await axios.post('/inventory/laboratories-manage', labForm.value)
    toast.success("Guardado")
    isLabDialogOpen.value = false
    fetchLabs()
  } catch (error) { toast.error('Error') }
}

const saveGroup = async () => {
  try {
    await axios.post('/inventory/laboratories-manage/groups', groupForm.value)
    toast.success("Grupo actualizado")
    isGroupDialogOpen.value = false
    fetchLabs(); fetchGroups()
  } catch (error) { toast.error('Error') }
}

const updateTableOptions = o => {
  page.value = o.page; itemsPerPage.value = o.itemsPerPage
  if (o.sortBy?.length) { sortBy.value = o.sortBy[0].key; orderBy.value = o.sortBy[0].order }
}

onMounted(() => { fetchLabs(); fetchGroups(); fetchAllLabsForSelect() })
watch([searchQuery], () => { page.value = 1; fetchLabs() })
watch([page, itemsPerPage, sortBy, orderBy], () => fetchLabs())
</script>

<template>
  <div>
    <AppFilterBase
      v-model:search="searchQuery"
      :show-add="true"
      add-button-text="Añadir Laboratorio"
      @clear="searchQuery = ''; fetchLabs()"
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

    <VCard class="rounded-lg border shadow-sm mt-4 overflow-hidden">
      <div class="d-none d-md-block">
        <VDataTableServer
          :headers="headers"
          :items="laboratories"
          :items-length="totalLabs"
          :loading="loading"
          @update:options="updateTableOptions"
          density="compact"
        >
          <template #item.group.name="{ item }">
            <VChip v-if="item.group" color="primary" size="x-small" variant="tonal" class="font-weight-bold uppercase">{{ item.group.name }}</VChip>
            <span v-else class="text-caption opacity-50">Sin grupo</span>
          </template>
          <template #item.products_count="{ item }"><VChip size="x-small" color="info" variant="flat">{{ item.products_count }}</VChip></template>
          <template #item.actions="{ item }">
            <div class="d-flex justify-end gap-1 px-2">
              <IconBtn @click="openLabEdit(item)" color="primary"><VIcon icon="tabler-edit" size="18" /></IconBtn>
              <IconBtn @click="deleteLab(item.id)" color="error"><VIcon icon="tabler-trash" size="18" /></IconBtn>
            </div>
          </template>
        </VDataTableServer>
      </div>

      <div class="d-block d-md-none pa-2">
        <div class="d-flex flex-column gap-2">
          <VCard v-for="item in laboratories" :key="item.id" variant="flat" class="border mb-1 rounded-lg">
            <div class="pa-3">
              <div class="d-flex justify-space-between align-start mb-2">
                <div>
                  <div class="text-xs font-weight-black text-primary mb-1">ID: {{ item.id }}</div>
                  <h3 class="text-sm font-weight-black text-uppercase leading-tight">{{ item.name }}</h3>
                </div>
                <VChip v-if="item.group" color="primary" size="x-small" variant="tonal" class="font-weight-bold uppercase">{{ item.group.name }}</VChip>
              </div>
              <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded">
                <span class="text-base font-weight-black text-info">{{ item.products_count }} <small>SKUS</small></span>
                <div class="d-flex gap-1">
                  <VBtn icon="tabler-edit" color="primary" variant="tonal" size="small" @click="openLabEdit(item)" />
                  <VBtn icon="tabler-trash" color="error" variant="tonal" size="small" @click="deleteLab(item.id)" />
                </div>
              </div>
            </div>
          </VCard>
        </div>
        <AppMobilePagination :page="page" :items-per-page="itemsPerPage" :total-items="totalLabs" @change="updateTableOptions" />
      </div>
    </VCard>

    <!-- MODAL LABORATORIO PREMIUM -->
    <VDialog v-model="isLabDialogOpen" max-width="500">
      <VCard class="rounded-xl shadow-xl border-0">
        <VCardTitle class="pa-6 bg-var-theme-background d-flex align-center font-weight-black text-uppercase">
          <VAvatar color="primary" variant="tonal" class="me-4"><VIcon icon="tabler-flask" /></VAvatar>
          {{ labForm.id ? 'Editar' : 'Nuevo' }} Laboratorio
          <VSpacer /><VBtn icon="tabler-x" variant="text" @click="isLabDialogOpen = false" />
        </VCardTitle>
        <VCardText class="pa-6">
          <AppTextField v-model="labForm.name" label="Nombre" class="mb-4" />
          <AppAutocomplete v-model="labForm.group_id" :items="groups" item-title="name" item-value="id" label="Grupo" clearable />
        </VCardText>
        <VDivider />
        <VCardActions class="pa-6">
          <VSpacer /><VBtn color="secondary" variant="tonal" @click="isLabDialogOpen = false">CANCELAR</VBtn>
          <VBtn color="primary" variant="elevated" @click="saveLab">GUARDAR</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- MODAL GRUPOS PREMIUM -->
    <VDialog v-model="isGroupDialogOpen" max-width="600">
      <VCard class="rounded-xl shadow-xl border-0">
        <VCardTitle class="pa-6 bg-var-theme-background d-flex align-center font-weight-black text-uppercase">
          <VAvatar color="primary" variant="tonal" class="me-4"><VIcon icon="tabler-layers-intersect" /></VAvatar>
          Configurar Grupo
          <VSpacer /><VBtn icon="tabler-x" variant="text" @click="isGroupDialogOpen = false" />
        </VCardTitle>
        <VCardText class="pa-6">
          <AppTextField v-model="groupForm.name" label="Nombre del Grupo" class="mb-4" />
          <AppAutocomplete v-model="groupForm.laboratory_ids" :items="allLaboratoriesForSelect" item-title="name" item-value="id" label="Laboratorios" multiple chips closable-chips />
        </VCardText>
        <VDivider />
        <VCardActions class="pa-6">
          <VSpacer /><VBtn color="secondary" variant="tonal" @click="isGroupDialogOpen = false">DESCARTAR</VBtn>
          <VBtn color="primary" variant="elevated" @click="saveGroup">GUARDAR</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.bg-var-theme-background { background-color: rgba(var(--v-border-color), 0.05); }
.text-super-xs { font-size: 0.65rem !important; }
:deep(.v-data-table th) { font-size: 0.75rem !important; font-weight: 700 !important; text-transform: uppercase; }
</style>
