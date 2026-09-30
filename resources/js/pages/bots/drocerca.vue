<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useAbility } from '@casl/vue'

// Control de permisos CASL
const ability = useAbility()
const canManageBot = computed(() => ability.can('manage', 'bots') || ability.can('update', 'Supplier') || ability.can('manage', 'all'))

// Estados reactivos de interfaz
const isLoading = ref(false)
const isSaving = ref(false)
const isSyncing = ref(false)
const showPassword = ref(false)
const activeTab = ref('connection')
const formRef = ref(null)

// Identificador del proveedor
const supplierId = ref(null)

// Modelo de datos del formulario
const form = reactive({
  type: 'drocerca_bot',
  host: 'http://drocerca.proteoerp.org:8082/proteoerp/portalcli',
  username: '',
  password: '',
  has_password: false,
  is_active: true,
  sync_frequency: 'daily',
  cron_expression: '04:30 AM Diario',
  auto_download_pdf: true,
})

// Snapshot para rastreo de cambios (Dirty State)
const originalForm = ref({
  username: '',
  host: '',
  is_active: true,
  auto_download_pdf: true,
})

const isDirty = computed(() => {
  return (
    form.username !== originalForm.value.username ||
    form.host !== originalForm.value.host ||
    form.password.length > 0 ||
    form.is_active !== originalForm.value.is_active ||
    form.auto_download_pdf !== originalForm.value.auto_download_pdf
  )
})

// Reglas de validación reactivas
const rules = {
  required: value => Boolean(value) || 'Este campo es obligatorio',
  url: value => {
    try {
      new URL(value)
      return true
    } catch {
      return 'Debe ingresar una URL válida (http/https)'
    }
  },
}

// Cargar configuración existente del proveedor y conexión
const fetchSupplierData = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('/suppliers', { params: { search: 'DROCERCA' } })
    const list = data?.data || data || []
    const supplier = list.find(s => 
      s.name?.toUpperCase().includes('DROCERCA') || 
      s.name?.toUpperCase().includes('CERCA')
    ) || list[0]

    if (supplier) {
      supplierId.value = supplier.id

      const connRes = await axios.get(`/suppliers/${supplier.id}/connection`)
      if (connRes.data) {
        form.type = connRes.data.type || 'drocerca_bot'
        form.host = connRes.data.host || 'http://drocerca.proteoerp.org:8082/proteoerp/portalcli'
        form.username = connRes.data.username || ''
        form.has_password = Boolean(connRes.data.has_password)
        form.is_active = connRes.data.is_active !== undefined ? Boolean(connRes.data.is_active) : true
      }
    }

    // Actualizar snapshot para dirty state
    originalForm.value = {
      username: form.username,
      host: form.host,
      is_active: form.is_active,
      auto_download_pdf: form.auto_download_pdf,
    }
  } catch (error) {
    console.error('Error al cargar configuración de Drocerca:', error)
    toast.error('No se pudo cargar la configuración de Drocerca')
  } finally {
    isLoading.value = false
  }
}

// Guardar configuración
const saveConfig = async () => {
  if (!supplierId.value) {
    toast.error('No se encontró el proveedor Drocerca registrado en el sistema')
    return
  }

  if (formRef.value) {
    const { valid } = await formRef.value.validate()
    if (!valid) return
  }

  isSaving.value = true
  try {
    const payload = {
      type: 'drocerca_bot',
      host: form.host,
      username: form.username,
      is_active: form.is_active,
      pasv: true,
      has_header: true,
    }

    if (form.password) {
      payload.password = form.password
    }

    await axios.post(`/suppliers/${supplierId.value}/connection`, payload)
    toast.success('Configuración del Bot Drocerca guardada correctamente')
    form.password = ''
    await fetchSupplierData()
  } catch (error) {
    console.error('Error al guardar credenciales de Drocerca:', error)
    toast.error(error.response?.data?.message || 'Error al persistir la configuración')
  } finally {
    isSaving.value = false
  }
}

// Ejecutar sincronización manual previa confirmación modal
const runSync = async () => {
  const confirmed = await confirmDialog({
    title: '¿Ejecutar Sincronización?',
    text: 'El bot iniciará sesión en el portal de Drocerca para extraer facturas pendientes y totales fiscales. ¿Deseas continuar?',
    icon: 'info',
    confirmButtonText: 'Sí, Sincronizar Ahora',
    cancelButtonText: 'Cancelar',
  })

  if (!confirmed) return

  isSyncing.value = true
  try {
    const payload = {
      supplier_id: supplierId.value,
    }
    if (form.username) payload.username = form.username
    if (form.password) payload.password = form.password

    const res = await axios.post('/sync-drocerca', payload)
    toast.success(res.data?.message || 'Sincronización completada exitosamente')
  } catch (error) {
    console.error('Error al sincronizar con Drocerca:', error)
    toast.error(error.response?.data?.message || 'Error al ejecutar la extracción')
  } finally {
    isSyncing.value = false
  }
}

onMounted(() => {
  fetchSupplierData()
})
</script>

<template>
  <div>
    <!-- Encabezado Principal -->
    <VCard class="mb-6" border flat rounded="lg">
      <VCardItem>
        <template #prepend>
          <VAvatar color="primary" variant="tonal" rounded size="48" class="me-2">
            <VIcon icon="tabler-robot" size="28" />
          </VAvatar>
        </template>

        <VCardTitle class="text-h5 font-weight-bold">
          Bot Drocerca — Extracción y Sincronización
        </VCardTitle>

        <VCardSubtitle class="text-body-2">
          Gestión de credenciales y automatización de descargas de facturas para el portal ProteoERP Drocerca.
        </VCardSubtitle>

        <template #append>
          <VBtn
            color="primary"
            variant="tonal"
            density="comfortable"
            prepend-icon="tabler-refresh"
            :loading="isLoading"
            @click="fetchSupplierData"
          >
            Actualizar
          </VBtn>
        </template>
      </VCardItem>
    </VCard>

    <VRow>
      <!-- Contenedor Principal de Ajustes con Tabs -->
      <VCol cols="12" md="8">
        <VCard border flat rounded="lg">
          <VTabs v-model="activeTab" color="primary" density="comfortable">
            <VTab value="connection">
              <VIcon icon="tabler-key" class="me-2" size="20" />
              Conexión y Credenciales
            </VTab>
            <VTab value="automation">
              <VIcon icon="tabler-settings-automation" class="me-2" size="20" />
              Automatización & Reglas
            </VTab>
          </VTabs>

          <VDivider />

          <VCardText class="pt-6">
            <VProgressLinear
              v-if="isLoading"
              indeterminate
              color="primary"
              class="mb-4"
            />

            <VWindow v-model="activeTab">
              <!-- Tab 1: Conexión y Credenciales -->
              <VWindowItem value="connection">
                <VAlert
                  type="info"
                  variant="tonal"
                  density="comfortable"
                  icon="tabler-info-circle"
                  class="mb-6 rounded-lg"
                >
                  El bot se conecta automáticamente a <strong>http://drocerca.proteoerp.org:8082/proteoerp/portalcli</strong>, accede a la sección de Facturación, descarga cada PDF digital de NovusFactura y extrae Número de Control, Fecha de Vencimiento, Tipo de Cambio (Tasa), Base Exenta, Base Imponible e IVA.
                </VAlert>

                <VForm ref="formRef" @submit.prevent="saveConfig">
                  <VRow>
                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="form.username"
                        label="Usuario / Código de Cliente"
                        placeholder="Ej: W008B3"
                        prepend-inner-icon="tabler-user"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        hint="Usuario asignado en Drocerca"
                        persistent-hint
                        :rules="[rules.required]"
                        :disabled="isLoading || isSaving || !canManageBot"
                      />
                    </VCol>

                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        label="Contraseña de Acceso"
                        :placeholder="form.has_password ? '•••••••••••• (Configurada)' : 'Ingresa la contraseña'"
                        prepend-inner-icon="tabler-lock"
                        :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        hint="Se almacena encriptada de forma segura"
                        persistent-hint
                        :disabled="isLoading || isSaving || !canManageBot"
                        @click:append-inner="showPassword = !showPassword"
                      />
                    </VCol>

                    <VCol cols="12">
                      <VTextField
                        v-model="form.host"
                        label="URL del Portal de Clientes"
                        placeholder="http://drocerca.proteoerp.org:8082/proteoerp/portalcli"
                        prepend-inner-icon="tabler-world"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        hint="URL base de la plataforma web de Drocerca"
                        persistent-hint
                        :rules="[rules.required, rules.url]"
                        :disabled="isLoading || isSaving || !canManageBot"
                      />
                    </VCol>

                    <VCol cols="12" class="d-flex align-center flex-wrap gap-3 mt-3">
                      <VBtn
                        v-if="canManageBot"
                        type="submit"
                        color="primary"
                        density="comfortable"
                        prepend-icon="tabler-device-floppy"
                        :loading="isSaving"
                        :disabled="!isDirty || isLoading"
                      >
                        Guardar Configuración
                      </VBtn>

                      <VSpacer />

                      <VBtn
                        color="primary"
                        variant="tonal"
                        density="comfortable"
                        prepend-icon="tabler-player-play"
                        :loading="isSyncing"
                        :disabled="isLoading || isSaving"
                        @click="runSync"
                      >
                        Ejecutar Sincronización Ahora
                      </VBtn>
                    </VCol>
                  </VRow>
                </VForm>
              </VWindowItem>

              <!-- Tab 2: Automatización & Reglas -->
              <VWindowItem value="automation">
                <VRow>
                  <VCol cols="12">
                    <VSwitch
                      v-model="form.is_active"
                      color="primary"
                      label="Activar ejecución automática en tareas programadas"
                      variant="outlined"
                      density="comfortable"
                      hide-details="auto"
                      :disabled="!canManageBot"
                    />
                  </VCol>
                  <VCol cols="12">
                    <VSwitch
                      v-model="form.auto_download_pdf"
                      color="primary"
                      label="Descargar y parsear automáticamente facturas digitales PDF"
                      variant="outlined"
                      density="comfortable"
                      hide-details="auto"
                      :disabled="!canManageBot"
                    />
                  </VCol>
                </VRow>
              </VWindowItem>
            </VWindow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Panel Lateral de Estado e Información -->
      <VCol cols="12" md="4">
        <VCard border flat rounded="lg" class="mb-6">
          <VCardItem>
            <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-activity" color="success" size="22" />
              Estado del Bot Drocerca
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText class="py-3">
            <VList density="compact" class="py-0">
              <VListItem class="px-0">
                <template #prepend>
                  <VIcon icon="tabler-building" size="18" class="me-2 text-medium-emphasis" />
                </template>
                <VListItemTitle class="text-body-2 text-medium-emphasis">Proveedor vinculado:</VListItemTitle>
                <template #append>
                  <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
                    {{ supplierId ? `ID ${supplierId} (Drocerca)` : 'No detectado' }}
                  </VChip>
                </template>
              </VListItem>

              <VListItem class="px-0">
                <template #prepend>
                  <VIcon icon="tabler-shield-lock" size="18" class="me-2 text-medium-emphasis" />
                </template>
                <VListItemTitle class="text-body-2 text-medium-emphasis">Credenciales:</VListItemTitle>
                <template #append>
                  <VChip
                    size="small"
                    :color="form.has_password ? 'success' : (form.username ? 'warning' : 'error')"
                    variant="tonal"
                  >
                    {{ form.has_password ? 'Configuradas' : (form.username ? 'Incompletas' : 'Sin configurar') }}
                  </VChip>
                </template>
              </VListItem>

              <VListItem class="px-0">
                <template #prepend>
                  <VIcon icon="tabler-clock" size="18" class="me-2 text-medium-emphasis" />
                </template>
                <VListItemTitle class="text-body-2 text-medium-emphasis">Tarea Automática (Cron):</VListItemTitle>
                <template #append>
                  <VChip size="small" color="info" variant="tonal">
                    {{ form.cron_expression }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>

        <VCard border flat rounded="lg">
          <VCardItem>
            <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-bulb" color="warning" size="22" />
              ¿Qué hace este Bot?
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText class="text-body-2 text-medium-emphasis">
            <ul class="ps-4 mb-0 d-flex flex-column gap-2">
              <li>Inicia sesión automáticamente en <code>portalcli</code> de ProteoERP Drocerca.</li>
              <li>Extrae las facturas desde el módulo de Facturación.</li>
              <li>Descarga y lee cada PDF de NovusFactura para extraer fecha de vencimiento, número de control, tasa BCV, base exenta, imponible e IVA.</li>
              <li>Almacena los PDFs en el ERP y actualiza el módulo de cuentas por pagar.</li>
            </ul>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
