<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@axios'
import { useBrandingStore } from '@/stores/useBrandingStore'

const brandingStore = useBrandingStore()
const isTenantInstance = computed(() => Boolean(brandingStore.settings.is_tenant))

// Estados reactivos
const isLoading = ref(false)
const isSubmitting = ref(false)
const isDialogOpen = ref(false)
const searchQuery = ref('')
const tenants = ref([])

// Notificación / Toast feedback
const alert = reactive({
  show: false,
  type: 'success',
  message: '',
})

// Formulario de nueva farmacia
const form = reactive({
  company_name: '',
  tenant_id: '',
  admin_name: 'Administrador',
  admin_email: '',
  password: '',
})

const errors = reactive({
  company_name: '',
  tenant_id: '',
  admin_name: '',
  admin_email: '',
  password: '',
})

// Auto-generar identificador slug a partir del nombre
const handleCompanyNameInput = () => {
  if (!form.tenant_id || form.tenant_id === slugify(form.company_name.slice(0, -1))) {
    form.tenant_id = slugify(form.company_name)
  }
}

const slugify = (text) => {
  return text
    .toString()
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^\w\-]+/g, '')
    .replace(/\-\-+/g, '-')
}

// Encabezados de la tabla
const headers = [
  { title: 'Farmacia / Razón Social', key: 'company_name', sortable: true },
  { title: 'Identificador (ID)', key: 'id', sortable: true },
  { title: 'Dominio / URL de Acceso', key: 'domains', sortable: false },
  { title: 'Fecha de Creación', key: 'created_at', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
]

// Cargar lista de farmacias registradas
const fetchTenants = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/central/tenants')
    const data = response.data?.data ?? response.data
    tenants.value = Array.isArray(data) ? data : []
  } catch (error) {
    console.error('Error al cargar farmacias:', error)
    tenants.value = []
    showAlert('error', error.response?.data?.message || 'Error al conectar con el servidor central')
  } finally {
    isLoading.value = false
  }
}

// Limpiar formulario y errores
const resetForm = () => {
  form.company_name = ''
  form.tenant_id = ''
  form.admin_name = 'Administrador'
  form.admin_email = ''
  form.password = ''
  
  Object.keys(errors).forEach((key) => {
    errors[key] = ''
  })
}

const openCreateDialog = () => {
  resetForm()
  isDialogOpen.value = true
}

const closeCreateDialog = () => {
  isDialogOpen.value = false
  resetForm()
}

// Mostrar alerta / notificación
const showAlert = (type, message) => {
  alert.type = type
  alert.message = message
  alert.show = true
  setTimeout(() => {
    alert.show = false
  }, 6000)
}

// Crear farmacia y aprovisionar
const handleCreateTenant = async () => {
  // Limpiar errores previos
  Object.keys(errors).forEach((key) => {
    errors[key] = ''
  })

  isSubmitting.value = true
  try {
    const response = await axios.post('/central/tenants', {
      company_name: form.company_name,
      tenant_id: form.tenant_id,
      admin_name: form.admin_name,
      admin_email: form.admin_email,
      password: form.password,
    })

    showAlert('success', response.data.message || 'Farmacia creada y aprovisionada exitosamente')
    closeCreateDialog()
    await fetchTenants()
  } catch (error) {
    if (error.response?.status === 422 && error.response?.data?.errors) {
      const serverErrors = error.response.data.errors
      Object.keys(serverErrors).forEach((field) => {
        if (errors[field] !== undefined) {
          errors[field] = serverErrors[field][0]
        }
      })
    } else {
      showAlert('error', error.response?.data?.message || 'Ocurrió un error al aprovisionar la farmacia')
    }
  } finally {
    isSubmitting.value = false
  }
}

// Estado para modal de eliminación
const isDeleteDialogOpen = ref(false)
const isDeleting = ref(false)
const tenantToDelete = ref(null)

const confirmDeleteTenant = (tenant) => {
  tenantToDelete.value = tenant
  isDeleteDialogOpen.value = true
}

const handleDeleteTenant = async () => {
  if (!tenantToDelete.value) return
  
  const id = tenantToDelete.value.id ?? tenantToDelete.value.raw?.id
  isDeleting.value = true
  try {
    const response = await axios.delete(`/central/tenants/${id}`)
    showAlert('success', response.data.message || 'Farmacia eliminada exitosamente')
    isDeleteDialogOpen.value = false
    tenantToDelete.value = null
    await fetchTenants()
  } catch (error) {
    showAlert('error', error.response?.data?.message || 'Error al eliminar la farmacia')
  } finally {
    isDeleting.value = false
  }
}

onMounted(() => {
  if (!isTenantInstance.value) {
    fetchTenants()
  }
})
</script>

<template>
  <div v-if="isTenantInstance">
    <VAlert
      type="warning"
      variant="tonal"
      class="mb-6"
      title="Módulo Exclusivo de la Instancia Central (Master)"
    >
      La gestión y aprovisionamiento de farmacias SaaS solo está habilitada desde el dominio maestro central del sistema.
    </VAlert>
  </div>

  <div v-else>
    <!-- Encabezado de la Sección -->
    <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between mb-6 ga-3">
      <div>
        <h1 class="text-h5 font-weight-bold text-high-emphasis mb-1">
          Gestión de Farmacias (Tenants)
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Aprovisiona nuevas instancias de farmacias independientes con catálogos y formatos maestros preconfigurados.
        </p>
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        class="font-weight-medium"
        @click="openCreateDialog"
      >
        Nueva Farmacia
      </VBtn>
    </div>

    <!-- Alertas globales -->
    <VAlert
      v-if="alert.show"
      :type="alert.type"
      variant="tonal"
      closable
      class="mb-6"
      @click:close="alert.show = false"
    >
      {{ alert.message }}
    </VAlert>

    <!-- Tabla Principal de Farmacias -->
    <VCard variant="outlined">
      <VCardText class="pa-4">
        <VRow align="center" justify="space-between">
          <VCol cols="12" sm="6" md="4">
            <VTextField
              v-model="searchQuery"
              density="compact"
              placeholder="Buscar por farmacia o subdominio..."
              prepend-inner-icon="tabler-search"
              variant="outlined"
              hide-details
              clearable
            />
          </VCol>
          <VCol cols="auto">
            <VBtn
              variant="text"
              icon="tabler-refresh"
              :loading="isLoading"
              @click="fetchTenants"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDataTable
        :headers="headers"
        :items="tenants"
        :search="searchQuery"
        :loading="isLoading"
        loading-text="Cargando farmacias registradas..."
        no-data-text="No hay farmacias registradas actualmente"
        hover
        class="elevation-0"
      >
        <!-- Columna Farmacia -->
        <template #item.company_name="{ item }">
          <div class="d-flex align-center ga-3 py-2">
            <VAvatar color="primary" variant="tonal" size="38">
              <VIcon icon="tabler-building-store" size="20" />
            </VAvatar>
            <div>
              <div class="font-weight-semibold text-high-emphasis">
                {{ item.company_name ?? item.raw?.company_name }}
              </div>
              <span class="text-caption text-medium-emphasis">
                BD: tovaerp_tenant_{{ item.id ?? item.raw?.id }}
              </span>
            </div>
          </div>
        </template>

        <!-- Columna Identificador -->
        <template #item.id="{ item }">
          <VChip size="small" variant="outlined" color="primary" class="font-weight-medium">
            {{ item.id ?? item.raw?.id }}
          </VChip>
        </template>

        <!-- Columna Dominios -->
        <template #item.domains="{ item }">
          <div v-if="(item.domains ?? item.raw?.domains)?.length > 0">
            <div v-for="d in (item.domains ?? item.raw?.domains)" :key="d.id" class="d-flex align-center ga-1 my-1">
              <a
                :href="'https://' + d.domain"
                target="_blank"
                rel="noopener noreferrer"
                class="text-decoration-none text-primary text-body-2 font-weight-medium d-inline-flex align-center ga-1"
              >
                <span>{{ d.domain }}</span>
                <VIcon icon="tabler-external-link" size="14" />
              </a>
            </div>
          </div>
          <span v-else class="text-caption text-medium-emphasis">
            {{ item.id ?? item.raw?.id }}.tovaerp.com
          </span>
        </template>

        <!-- Columna Fecha -->
        <template #item.created_at="{ item }">
          <span class="text-body-2 text-medium-emphasis">
            {{ (item.created_at ?? item.raw?.created_at) ? new Date(item.created_at ?? item.raw?.created_at).toLocaleDateString('es-ES') : '—' }}
          </span>
        </template>

        <!-- Columna Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-end ga-1">
            <VBtn
              v-if="(item.domains ?? item.raw?.domains)?.length > 0"
              :href="'https://' + (item.domains ?? item.raw?.domains)[0].domain"
              target="_blank"
              variant="text"
              color="primary"
              size="small"
              prepend-icon="tabler-login"
            >
              Ingresar
            </VBtn>

            <VBtn
              icon="tabler-trash"
              variant="text"
              color="error"
              size="small"
              @click="confirmDeleteTenant(item)"
            />
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Modal de Confirmación de Eliminación -->
    <VDialog v-model="isDeleteDialogOpen" max-width="480" persistent>
      <VCard class="pa-2">
        <VCardTitle class="d-flex align-center ga-2 text-error pa-4">
          <VIcon icon="tabler-alert-triangle" size="26" />
          <span class="text-h6 font-weight-bold">¿Eliminar Farmacia?</span>
        </VCardTitle>

        <VCardText class="pa-4 pt-0">
          <p class="text-body-1 mb-2">
            ¿Estás seguro de que deseas eliminar permanentemente a
            <strong>{{ tenantToDelete?.company_name ?? tenantToDelete?.raw?.company_name ?? tenantToDelete?.id ?? tenantToDelete?.raw?.id }}</strong>?
          </p>
          <p class="text-caption text-medium-emphasis mb-0">
            Esta acción eliminará el subdominio, sus configuraciones y destruirá su base de datos aislada. Esta operación no se puede deshacer.
          </p>
        </VCardText>

        <VCardActions class="pa-4 justify-end ga-2">
          <VBtn
            variant="outlined"
            color="secondary"
            :disabled="isDeleting"
            @click="isDeleteDialogOpen = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            :loading="isDeleting"
            prepend-icon="tabler-trash"
            @click="handleDeleteTenant"
          >
            {{ isDeleting ? 'Eliminando...' : 'Eliminar Farmacia' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal de Creación / Aprovisionamiento de Farmacia -->
    <VDialog v-model="isDialogOpen" max-width="600" persistent>
      <VCard class="pa-2">
        <VCardTitle class="d-flex align-center justify-space-between pa-4">
          <div class="d-flex align-center ga-2">
            <VIcon icon="tabler-building-hospital" color="primary" size="26" />
            <span class="text-h6 font-weight-bold">Aprovisionar Nueva Farmacia</span>
          </div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="closeCreateDialog" :disabled="isSubmitting" />
        </VCardTitle>

        <VCardText class="pa-4">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Al crear la farmacia, el sistema creará automáticamente su base de datos aislada, correrá todas las migraciones e importará el catálogo maestro de proveedores con credenciales limpias.
          </p>

          <VRow>
            <!-- Nombre de la Farmacia -->
            <VCol cols="12">
              <VTextField
                v-model="form.company_name"
                label="Nombre de la Farmacia / Empresa *"
                placeholder="Ej: Farmacia Zorca Salud C.A."
                variant="outlined"
                :error-messages="errors.company_name"
                :disabled="isSubmitting"
                @input="handleCompanyNameInput"
              />
            </VCol>

            <!-- Subdominio / Tenant ID -->
            <VCol cols="12">
              <VTextField
                v-model="form.tenant_id"
                label="Identificador de Subdominio *"
                placeholder="zorcasalud"
                suffix=".tovaerp.com"
                variant="outlined"
                :error-messages="errors.tenant_id"
                :disabled="isSubmitting"
                hint="Solo letras minúsculas, números y guiones. Será la URL de acceso de la farmacia."
                persistent-hint
              />
            </VCol>

            <!-- Nombre de Administrador -->
            <VCol cols="12" sm="6">
              <VTextField
                v-model="form.admin_name"
                label="Nombre del Administrador"
                placeholder="Ej: Administrador"
                variant="outlined"
                :error-messages="errors.admin_name"
                :disabled="isSubmitting"
              />
            </VCol>

            <!-- Email de Administrador -->
            <VCol cols="12" sm="6">
              <VTextField
                v-model="form.admin_email"
                label="Correo Electrónico de Acceso *"
                placeholder="admin@farmacia.com"
                type="email"
                variant="outlined"
                :error-messages="errors.admin_email"
                :disabled="isSubmitting"
              />
            </VCol>

            <!-- Contraseña inicial -->
            <VCol cols="12">
              <VTextField
                v-model="form.password"
                label="Contraseña de Acceso *"
                placeholder="••••••••"
                type="password"
                variant="outlined"
                :error-messages="errors.password"
                :disabled="isSubmitting"
                hint="Mínimo 6 caracteres."
                persistent-hint
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4 justify-end ga-2">
          <VBtn
            variant="outlined"
            color="secondary"
            :disabled="isSubmitting"
            @click="closeCreateDialog"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :loading="isSubmitting"
            prepend-icon="tabler-circle-check"
            @click="handleCreateTenant"
          >
            {{ isSubmitting ? 'Aprovisionando...' : 'Crear y Aprovisionar' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
