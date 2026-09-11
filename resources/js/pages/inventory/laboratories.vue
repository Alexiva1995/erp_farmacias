<script setup>
import { onMounted, ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'
import AppFilterBase from "@/components/AppFilterBase.vue"
import AppMobilePagination from "@/components/AppMobilePagination.vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import LaboratoryEditDialog from "@/components/dialogs/LaboratoryEditDialog.vue"
import LaboratoryGroupDialog from "@/components/dialogs/LaboratoryGroupDialog.vue"
import LaboratoryMobileCard from "@/components/cards/LaboratoryMobileCard.vue"
import { toast } from "@/plugins/sweetalert"
import Swal from "sweetalert2"
import { useAbility } from "@casl/vue"
import { useBrandingStore } from "@/stores/useBrandingStore"

const { can } = useAbility()
const router = useRouter()

// --- Estado global ---
const activeTab = ref('laboratories')

// --- Laboratorios ---
const laboratories = ref([])
const allLaboratoriesForSelect = ref([])
const groups = ref([])
const loading = ref(false)
const totalLabs = ref(0)
const searchQuery = ref('')
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref('units_count')
const orderBy = ref('desc')

// --- Grupos tab ---
const groupSearch = ref('')

const isLabDialogOpen = ref(false)
const isGroupDialogOpen = ref(false)

const currentLab = ref({ id: null, name: '', group_id: null })
const currentGroup = ref({ id: null, name: '', laboratory_ids: [] })

const isSavingLab = ref(false)
const isSavingGroup = ref(false)

const brandingStore = useBrandingStore()
const isRestaurant = computed(() => false)
const enableBrandGroups = computed(() => brandingStore.settings.enable_brand_groups ?? false)

const formatUnits = (units) => {
  const num = Number(units || 0)
  return num % 1 === 0 ? num.toString() : num.toFixed(2).replace('.', ',')
}

const headers = computed(() => {
  const list = [
    {
      title: "ID",
      key: "id",
      sortable: true,
      cellClass: 'font-weight-black text-primary d-none d-sm-table-cell',
      headerClass: 'd-none d-sm-table-cell',
      width: '80px',
    },
    { title: isRestaurant.value ? "Marca" : "Laboratorio", key: "name", sortable: true, width: '35%' },
  ];
  if (enableBrandGroups.value) {
    list.push({ title: isRestaurant.value ? "Grupo de Marcas" : "Grupo Corporativo", key: "group.name", sortable: false });
  }
  list.push(
    { title: "Productos", key: "products_count", sortable: true, align: 'center' },
    { title: "Unidades", key: "units_count", sortable: true, align: 'end' },
    { title: "Acciones", key: "actions", sortable: false, align: 'center', width: '130px' }
  );
  return list;
})

const groupHeaders = [
  { title: "ID", key: "id", sortable: true, cellClass: 'font-weight-black text-primary' },
  { title: "Grupo", key: "name", sortable: true },
  { title: "Laboratorios", key: "labs_count", sortable: false, align: 'center' },
  { title: "Acciones", key: "actions", sortable: false, align: 'right' },
]

// --- Filtro de grupos en la pestaña ---
const filteredGroups = computed(() => {
  if (!groupSearch.value) return groups.value
  const lower = groupSearch.value.toLowerCase()
  return groups.value.filter(g => g.name.toLowerCase().includes(lower))
})

const fetchLabs = async () => {
  loading.value = true
  try {
    const params = {
      search: searchQuery.value || undefined,
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    }
    const { data } = await axios.get('/inventory/laboratories-manage', { params })
    laboratories.value = data.data
    totalLabs.value = data.total
  } catch (error) {
    console.error("Error al cargar laboratorios:", error)
    toast.error("Error al obtener el listado de laboratorios.")
  } finally {
    loading.value = false
  }
}

const fetchAllLabsForSelect = async () => {
  try {
    const { data } = await axios.get('/laboratories')
    allLaboratoriesForSelect.value = data
  } catch (error) {
    console.error("Error al cargar laboratorios para select:", error)
  }
}

const fetchGroups = async () => {
  try {
    const { data } = await axios.get('/inventory/laboratories-manage/groups')
    groups.value = data
  } catch (error) {
    console.error("Error al cargar grupos:", error)
  }
}

const openLabEdit = (lab = null) => {
  currentLab.value = lab
    ? { id: lab.id, name: lab.name, group_id: lab.group_id }
    : { id: null, name: '', group_id: null }
  isLabDialogOpen.value = true
}

const openGroupEdit = (group = null) => {
  currentGroup.value = group
    ? { id: group.id, name: group.name, laboratory_ids: group.laboratories?.map(l => l.id) || [] }
    : { id: null, name: '', laboratory_ids: [] }
  isGroupDialogOpen.value = true
}

const goToProducts = (lab) => {
  router.push({ path: '/inventory/products', query: { laboratoryId: lab.id } })
}

const saveLab = async (labData) => {
  if (!labData.name || !labData.name.trim()) {
    toast.error("El nombre es obligatorio")
    return
  }
  isSavingLab.value = true
  try {
    await axios.post('/inventory/laboratories-manage', labData)
    toast.success("Guardado correctamente")
    isLabDialogOpen.value = false
    await fetchLabs()
    await fetchAllLabsForSelect()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al guardar')
  } finally {
    isSavingLab.value = false
  }
}

const saveGroup = async (groupData) => {
  if (!groupData.name || !groupData.name.trim()) {
    toast.error("El nombre del grupo es obligatorio")
    return
  }
  isSavingGroup.value = true
  try {
    await axios.post('/inventory/laboratories-manage/groups', groupData)
    toast.success("Grupo actualizado correctamente")
    isGroupDialogOpen.value = false
    await Promise.all([fetchLabs(), fetchGroups()])
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al guardar el grupo')
  } finally {
    isSavingGroup.value = false
  }
}

const deleteLab = async (id) => {
  const result = await Swal.fire({
    title: isRestaurant.value ? "¿Borrar marca?" : "¿Borrar laboratorio?",
    text: isRestaurant.value
      ? "Los productos asociados quedarán sin marca asignada."
      : "Los productos asociados quedarán sin laboratorio asignado.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, borrar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "v-theme-error",
    reverseButtons: true,
  })
  if (result.isConfirmed) {
    try {
      await axios.delete(`/inventory/laboratories-manage/${id}`)
      toast.success(isRestaurant.value ? "Marca eliminada. Productos desvinculados." : "Laboratorio eliminado. Productos desvinculados.")
      await fetchLabs()
    } catch (error) {
      toast.error(error.response?.data?.message || "Error al eliminar")
    }
  }
}

const deleteGroup = async (id) => {
  const result = await Swal.fire({
    title: "¿Eliminar grupo?",
    text: "Los laboratorios asociados quedarán sin grupo asignado.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "v-theme-error",
    reverseButtons: true,
  })
  if (result.isConfirmed) {
    try {
      await axios.delete(`/inventory/laboratories-manage/groups/${id}`)
      toast.success("Grupo eliminado")
      await Promise.all([fetchGroups(), fetchLabs()])
    } catch (error) {
      toast.error(error.response?.data?.message || "Error al eliminar el grupo")
    }
  }
}

const updateTableOptions = o => {
  page.value = o.page
  itemsPerPage.value = o.itemsPerPage
  if (o.sortBy?.length) {
    sortBy.value = o.sortBy[0].key
    orderBy.value = o.sortBy[0].order
  }
}

let searchDebounce
watch(searchQuery, () => {
  page.value = 1
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    fetchLabs()
  }, 300)
})

watch([page, itemsPerPage, sortBy, orderBy], () => {
  fetchLabs()
})

onMounted(async () => {
  try {
    await Promise.all([
      fetchLabs(),
      fetchGroups(),
      fetchAllLabsForSelect(),
    ])
  } catch (error) {
    console.error("Error al inicializar laboratorios:", error)
  }
})
</script>

<template>
  <VContainer fluid class="pa-0">
    <VTabs v-model="activeTab" class="mb-4" color="primary">
      <VTab value="laboratories">
        <VIcon :icon="isRestaurant ? 'tabler-tags' : 'tabler-flask'" class="me-2" size="18" />
        {{ isRestaurant ? 'Marcas' : 'Laboratorios' }}
      </VTab>
      <VTab v-if="enableBrandGroups" value="groups">
        <VIcon icon="tabler-layers-intersect" class="me-2" size="18" />
        {{ isRestaurant ? 'Grupos de Marcas' : 'Grupos Corporativos' }}
        <VChip class="ms-2" size="x-small" color="primary" variant="tonal">{{ groups.length }}</VChip>
      </VTab>
    </VTabs>

    <!-- ===================== TAB LABORATORIOS ===================== -->
    <VWindow v-model="activeTab">
      <VWindowItem value="laboratories">
        <AppFilterBase
          v-model:search="searchQuery"
          :show-add="true"
          :add-button-text="isRestaurant ? 'Añadir Marca' : 'Añadir Laboratorio'"
          @clear="searchQuery = ''; fetchLabs()"
          @add="openLabEdit()"
        />

        <VCard class="rounded-lg border shadow-sm mt-4 overflow-hidden">
          <!-- Cabecera Estándar -->
          <VCardTitle class="d-flex align-center pa-4">
            <span class="text-h6 font-weight-bold">Listado de {{ isRestaurant ? 'Marcas' : 'Laboratorios' }}</span>
            <VSpacer />
            <VChip size="small" color="primary" variant="tonal" class="font-weight-black">
              {{ totalLabs }} {{ isRestaurant ? 'MARCAS' : 'LABORATORIOS' }}
            </VChip>
          </VCardTitle>

          <VDivider />

          <!-- Desktop -->
          <div class="d-none d-md-block">
            <VDataTableServer
              :headers="headers"
              :items="laboratories"
              :items-length="totalLabs"
              :loading="loading"
              :sort-by="[{ key: sortBy, order: orderBy }]"
              @update:options="updateTableOptions"
              density="compact"
              hover
              class="text-no-wrap"
            >
              <template #item.id="{ item }">
                <span class="font-weight-black text-primary">
                  {{ item.id }}
                </span>
              </template>

              <template #item.name="{ item }">
                <div class="d-flex align-center gap-2 py-2">
                  <div class="header-indicator success rounded-pill"></div>
                  <span class="text-sm font-weight-black text-high-emphasis text-uppercase">{{ item.name }}</span>
                </div>
              </template>

              <template #item.group.name="{ item }">
                <VChip v-if="item.group" color="primary" size="x-small" variant="tonal" class="font-weight-bold uppercase">{{ item.group.name }}</VChip>
                <span v-else class="text-caption opacity-50">Sin grupo</span>
              </template>

              <template #item.products_count="{ item }">
                <div class="text-center">
                  <VChip
                    :color="item.products_count > 0 ? 'primary' : 'secondary'"
                    size="x-small"
                    variant="tonal"
                    label
                    class="font-weight-black"
                  >
                    {{ item.products_count }} {{ item.products_count === 1 ? 'REF' : 'REFS' }}
                  </VChip>
                </div>
              </template>

              <template #item.units_count="{ item }">
                <div class="text-end">
                  <VChip
                    :color="item.units_count > 0 ? 'success' : 'secondary'"
                    size="x-small"
                    variant="tonal"
                    label
                    class="font-weight-black"
                  >
                    {{ formatUnits(item.units_count) }} UNDS
                  </VChip>
                </div>
              </template>

              <template #item.actions="{ item }">
                <div class="d-flex align-center justify-center gap-1 px-2">
                  <VTooltip text="Ver productos" location="top">
                    <template #activator="{ props: tooltipProps }">
                      <IconBtn v-bind="tooltipProps" @click="goToProducts(item)" color="info" size="small">
                        <VIcon icon="tabler-eye" size="18" />
                      </IconBtn>
                    </template>
                  </VTooltip>
                  <VTooltip text="Editar" location="top">
                    <template #activator="{ props: tooltipProps }">
                      <IconBtn v-bind="tooltipProps" @click="openLabEdit(item)" color="warning" size="small">
                        <VIcon icon="tabler-edit" size="18" />
                      </IconBtn>
                    </template>
                  </VTooltip>
                  <VTooltip v-if="can('manage', 'admin')" text="Eliminar" location="top">
                    <template #activator="{ props: tooltipProps }">
                      <IconBtn v-bind="tooltipProps" @click="deleteLab(item.id)" color="error" size="small">
                        <VIcon icon="tabler-trash" size="18" />
                      </IconBtn>
                    </template>
                  </VTooltip>
                </div>
              </template>

              <template #no-data>
                <AppEmptyState
                  :title="`No se encontraron ${isRestaurant ? 'marcas' : 'laboratorios'}`"
                  :message="isRestaurant 
                    ? 'Registra tus marcas para clasificar tus productos y facilitar su búsqueda en el inventario.' 
                    : 'Registra laboratorios para asociar la procedencia de tus productos farmacéuticos.'"
                  :icon="isRestaurant ? 'tabler-tags' : 'tabler-flask'"
                >
                  <template #actions>
                    <VBtn
                      color="primary"
                      variant="flat"
                      prepend-icon="tabler-plus"
                      @click="openLabEdit()"
                    >
                      Crear {{ isRestaurant ? 'Marca' : 'Laboratorio' }}
                    </VBtn>
                  </template>
                </AppEmptyState>
              </template>
            </VDataTableServer>
          </div>

          <!-- Mobile -->
          <div class="d-block d-md-none pa-2">
            <!-- Mobile Loader (Skeleton) -->
            <div v-if="loading" class="d-flex flex-column gap-2">
              <VProgressLinear indeterminate color="primary" class="mb-2" />
              <VSkeletonLoader v-for="i in 3" :key="i" type="list-item-two-line" class="mb-2 rounded-lg border" />
            </div>

            <!-- Mobile List (Data loaded) -->
            <div v-else-if="laboratories.length" class="d-flex flex-column gap-2">
              <LaboratoryMobileCard
                v-for="item in laboratories"
                :key="item.id"
                :item="item"
                :enable-brand-groups="enableBrandGroups"
                :can-delete="can('manage', 'admin')"
                @view="goToProducts"
                @edit="openLabEdit"
                @delete="deleteLab"
              />
              <AppMobilePagination :page="page" :items-per-page="itemsPerPage" :total-items="totalLabs" @change="updateTableOptions" />
            </div>

            <!-- Mobile Empty State -->
            <div v-else>
              <AppEmptyState
                :title="`No hay ${isRestaurant ? 'marcas' : 'laboratorios'}`"
                message="Registra tu primer elemento para comenzar."
                :icon="isRestaurant ? 'tabler-tags' : 'tabler-flask'"
              >
                <template #actions>
                  <VBtn
                    color="primary"
                    variant="flat"
                    size="small"
                    prepend-icon="tabler-plus"
                    @click="openLabEdit()"
                  >
                    Crear {{ isRestaurant ? 'Marca' : 'Laboratorio' }}
                  </VBtn>
                </template>
              </AppEmptyState>
            </div>
          </div>
        </VCard>
      </VWindowItem>

      <!-- ===================== TAB GRUPOS ===================== -->
      <VWindowItem value="groups">
        <AppFilterBase
          v-model:search="groupSearch"
          :show-add="true"
          add-button-text="Añadir Grupo"
          @clear="groupSearch = ''"
          @add="openGroupEdit()"
        />

        <VCard class="rounded-lg border shadow-sm overflow-hidden mt-4">
          <!-- Desktop -->
          <div class="d-none d-md-block">
            <VDataTable
              :headers="groupHeaders"
              :items="filteredGroups"
              density="compact"
              :loading="loading"
            >
              <template #item.labs_count="{ item }">
                <VChip size="x-small" color="primary" variant="tonal">
                  {{ item.laboratories?.length ?? 0 }}
                </VChip>
              </template>
              <template #item.actions="{ item }">
                <div class="d-flex justify-end gap-1 px-2">
                  <IconBtn @click="openGroupEdit(item)" color="primary" v-tooltip="'Editar grupo'">
                    <VIcon icon="tabler-edit" size="18" />
                  </IconBtn>
                  <IconBtn v-if="can('manage', 'admin')" @click="deleteGroup(item.id)" color="error" v-tooltip="'Eliminar grupo'">
                    <VIcon icon="tabler-trash" size="18" />
                  </IconBtn>
                </div>
              </template>
            </VDataTable>
          </div>

          <!-- Mobile grupos -->
          <div class="d-block d-md-none pa-2">
            <VCard
              v-for="group in filteredGroups"
              :key="group.id"
              variant="flat"
              class="border mb-2 rounded-lg"
            >
              <div class="pa-3 d-flex align-center justify-space-between">
                <div>
                  <div class="text-xs font-weight-black text-primary mb-1">ID: {{ group.id }}</div>
                  <div class="font-weight-black text-sm text-uppercase">{{ group.name }}</div>
                  <VChip size="x-small" color="primary" variant="tonal" class="mt-1">
                    {{ group.laboratories?.length ?? 0 }} labs
                  </VChip>
                </div>
                <div class="d-flex gap-1">
                  <VBtn icon="tabler-edit" color="primary" variant="tonal" size="small" @click="openGroupEdit(group)" />
                  <VBtn v-if="can('manage', 'admin')" icon="tabler-trash" color="error" variant="tonal" size="small" @click="deleteGroup(group.id)" />
                </div>
              </div>
            </VCard>
            <div v-if="!filteredGroups.length" class="text-center pa-6 text-disabled">
              Sin grupos registrados
            </div>
          </div>
        </VCard>
      </VWindowItem>
    </VWindow>

    <!-- DIÁLOGOS DESACOPLADOS -->
    <LaboratoryEditDialog
      v-model="isLabDialogOpen"
      :lab="currentLab"
      :groups="groups"
      :enable-brand-groups="enableBrandGroups"
      :is-restaurant="isRestaurant"
      :loading="isSavingLab"
      @save="saveLab"
    />

    <LaboratoryGroupDialog
      v-model="isGroupDialogOpen"
      :group="currentGroup"
      :laboratories="allLaboratoriesForSelect"
      :loading="isSavingGroup"
      @save="saveGroup"
    />
  </VContainer>
</template>

<style scoped>
.bg-var-theme-background { background-color: rgba(var(--v-border-color), 0.05); }
.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.15);
}
.text-super-xs { font-size: 0.65rem !important; }
.text-xs { font-size: 0.75rem !important; }
:deep(.v-data-table th) { font-size: 0.75rem !important; font-weight: 700 !important; text-transform: uppercase; }

.header-indicator {
  block-size: 16px;
  inline-size: 3px;
}

.header-indicator.success {
  background: linear-gradient(to bottom, #10b981, #059669);
}
</style>

