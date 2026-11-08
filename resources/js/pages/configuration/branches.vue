<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'
import { useAuthStore } from '@/stores/auth'
import { useBranchStore } from '@/stores/useBranchStore'

const authStore = useAuthStore()
const branchStore = useBranchStore()

const isLoading = ref(false)
const isSaving = ref(false)
const isDialogOpen = ref(false)
const branches = ref([])

const form = reactive({
  branch_code: '',
  branch_id: '',
  company_name: '',
  branch_address: '',
  branch_phone: '',
  domain: '',
  clone_catalog: true,
})

const errors = reactive({
  branch_code: '',
  company_name: '',
})

const canCreate = computed(() => {
  return authStore.user?.role_id === 1 || authStore.user?.is_corporate_admin
})

const fetchBranches = async () => {
  isLoading.value = true
  try {
    const res = await axios.get('/branches')
    branches.value = res.data?.data || []
  } catch (err) {
    toast('Error al cargar sucursales', 'error')
  } finally {
    isLoading.value = false
  }
}

const openCreateDialog = () => {
  errors.branch_code = ''
  errors.company_name = ''
  form.branch_code = ''
  form.branch_id = ''
  form.company_name = ''
  form.branch_address = ''
  form.branch_phone = ''
  form.domain = ''
  form.clone_catalog = true
  isDialogOpen.value = true
}

const handleCodeInput = (val) => {
  form.branch_code = val.toUpperCase()
  if (!form.branch_id) {
    form.branch_id = val.toLowerCase().replace(/[^a-z0-9]/g, '')
  }
}

const submitCreateBranch = async () => {
  errors.branch_code = ''
  errors.company_name = ''

  if (!form.branch_code) {
    errors.branch_code = 'El código de sede es obligatorio'
    return
  }
  if (!form.company_name) {
    errors.company_name = 'El nombre de la sede es obligatorio'
    return
  }

  isSaving.value = true
  try {
    const res = await axios.post('/branches', form)
    toast(res.data?.message || 'Sucursal creada exitosamente', 'success')
    isDialogOpen.value = false
    await fetchBranches()
    await branchStore.fetchBranches()
  } catch (err) {
    const msg = err.response?.data?.message || 'Error al crear sucursal'
    toast(msg, 'error')
  } finally {
    isSaving.value = false
  }
}

const toggleActive = async (branch) => {
  try {
    const res = await axios.post(`/branches/${branch.id}/toggle-active`)
    toast(res.data?.message || 'Estado actualizado', 'success')
    await fetchBranches()
    await branchStore.fetchBranches()
  } catch (err) {
    toast('Error al cambiar estado', 'error')
  }
}

onMounted(() => {
  fetchBranches()
})
</script>

<template>
  <div>
    <!-- Encabezado -->
    <v-row class="mb-4 align-center">
      <v-col cols="12" md="8">
        <h2 class="text-h4 font-weight-bold mb-1">
          Sucursales & Sedes
        </h2>
        <p class="text-body-1 text-medium-emphasis mb-0">
          Gestiona las distintas sedes operativas de tu farmacia y cambia de contexto de trabajo con un clic.
        </p>
      </v-col>
      <v-col cols="12" md="4" class="text-md-end">
        <v-btn
          v-if="canCreate"
          color="primary"
          prepend-icon="tabler-plus"
          class="text-none"
          @click="openCreateDialog"
        >
          Nueva Sucursal
        </v-btn>
      </v-col>
    </v-row>

    <!-- Estado de carga -->
    <v-row v-if="isLoading">
      <v-col v-for="i in 3" :key="i" cols="12" md="4">
        <v-skeleton-loader type="card" class="elevation-1 rounded-lg" />
      </v-col>
    </v-row>

    <!-- Listado de Sucursales -->
    <v-row v-else-if="branches.length > 0">
      <v-col
        v-for="b in branches"
        :key="b.id"
        cols="12"
        md="6"
        lg="4"
      >
        <v-card
          class="h-100 elevation-1 rounded-lg d-flex flex-column"
          :class="{ 'border-primary': branchStore.activeBranchId === b.id }"
        >
          <v-card-item>
            <template #prepend>
              <v-avatar
                :color="b.is_matriz ? 'primary' : 'info'"
                variant="tonal"
                rounded="lg"
                size="42"
              >
                <v-icon :icon="b.is_matriz ? 'tabler-building' : 'tabler-building-store'" size="24" />
              </v-avatar>
            </template>
            <v-card-title class="font-weight-bold">
              {{ b.company_name }}
            </v-card-title>
            <v-card-subtitle>
              Código: <span class="font-weight-medium">{{ b.branch_code }}</span>
              <v-chip
                v-if="b.is_matriz"
                size="x-small"
                color="primary"
                class="ms-2"
                label
              >
                Casa Matriz
              </v-chip>
            </v-card-subtitle>
          </v-card-item>

          <v-card-text class="flex-grow-1 pt-2">
            <div class="d-flex align-center mb-2 text-body-2">
              <v-icon icon="tabler-map-pin" size="18" class="me-2 text-medium-emphasis" />
              <span>{{ b.branch_address || 'Dirección no especificada' }}</span>
            </div>
            <div class="d-flex align-center mb-2 text-body-2">
              <v-icon icon="tabler-phone" size="18" class="me-2 text-medium-emphasis" />
              <span>{{ b.branch_phone || 'Teléfono no especificado' }}</span>
            </div>
            <div class="d-flex align-center text-body-2">
              <v-icon icon="tabler-world" size="18" class="me-2 text-medium-emphasis" />
              <a :href="b.url" target="_blank" class="text-decoration-none text-primary font-weight-medium">
                {{ b.domain }}
              </a>
            </div>
          </v-card-text>

          <v-divider />

          <v-card-actions class="pa-3">
            <v-chip
              size="small"
              :color="b.is_branch_active ? 'success' : 'error'"
              variant="tonal"
              label
            >
              {{ b.is_branch_active ? 'Operativa' : 'Inactiva' }}
            </v-chip>

            <v-spacer />

            <v-btn
              v-if="branchStore.activeBranchId !== b.id"
              size="small"
              color="primary"
              variant="outlined"
              class="text-none"
              @click="branchStore.switchBranch(b.id)"
            >
              Operar Esta Sede
            </v-btn>
            <v-chip
              v-else
              size="small"
              color="primary"
              variant="flat"
            >
              Sede Activa
            </v-chip>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <!-- Estado vacío -->
    <v-card v-else class="pa-8 text-center rounded-lg elevation-1">
      <v-icon icon="tabler-building-store" size="48" color="primary" class="mb-3" />
      <h3 class="text-h6 font-weight-bold mb-2">No se encontraron sucursales registradas</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">
        Crea la primera sucursal para tu farmacia y gestiona inventarios y ventas por sede.
      </p>
      <v-btn
        v-if="canCreate"
        color="primary"
        prepend-icon="tabler-plus"
        class="text-none"
        @click="openCreateDialog"
      >
        Crear Primera Sucursal
      </v-btn>
    </v-card>

    <!-- Diálogo de Creación de Sucursal -->
    <v-dialog v-model="isDialogOpen" max-width="600" persistent>
      <v-card class="rounded-lg">
        <v-card-title class="pa-4 d-flex align-center">
          <v-avatar color="primary" variant="tonal" size="36" class="me-3">
            <v-icon icon="tabler-building-plus" size="20" />
          </v-avatar>
          <span class="font-weight-bold">Nueva Sucursal</span>
          <v-spacer />
          <v-btn icon="tabler-x" variant="text" size="small" @click="isDialogOpen = false" />
        </v-card-title>

        <v-divider />

        <v-card-text class="pa-4">
          <v-row>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.branch_code"
                label="Código de Sucursal *"
                placeholder="Ej. NORTE, CENTRO, 02"
                :error-messages="errors.branch_code"
                variant="outlined"
                density="compact"
                @update:model-value="handleCodeInput"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.branch_id"
                label="Identificador Técnico (Slug)"
                placeholder="ej: sede_norte"
                variant="outlined"
                density="compact"
                hint="Se usará para la base de datos de la sede"
                persistent-hint
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="form.company_name"
                label="Nombre Comercial de la Sede *"
                placeholder="Ej. Farmacia BS - Sede Norte"
                :error-messages="errors.company_name"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="form.branch_address"
                label="Dirección Física"
                placeholder="Ej. Av. Principal, C.C. Las Delicias, Local 4"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.branch_phone"
                label="Teléfono de Contacto"
                placeholder="Ej. 0276-1234567"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.domain"
                label="Subdominio (Opcional)"
                placeholder="ej: sedenorte.tovaerp.com"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12">
              <v-checkbox
                v-model="form.clone_catalog"
                label="Clonar catálogo de productos activos (con existencia en 0)"
                density="compact"
                color="primary"
                hide-details
              />
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-3">
          <v-spacer />
          <v-btn
            variant="text"
            color="secondary"
            class="text-none"
            :disabled="isSaving"
            @click="isDialogOpen = false"
          >
            Cancelar
          </v-btn>
          <v-btn
            color="primary"
            class="text-none"
            :loading="isSaving"
            @click="submitCreateBranch"
          >
            Crear y Aprovisionar Sede
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
