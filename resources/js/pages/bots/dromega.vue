<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'
import DronenaDiscrepanciesModal from '@/components/dialogs/DronenaDiscrepanciesModal.vue'
import { useAbility } from '@casl/vue'

// Control de permisos CASL
const ability = useAbility()
const canManageBot = computed(() => ability.can('manage', 'bots') || ability.can('update', 'Supplier') || ability.can('manage', 'all'))

// Pestaña activa
const currentTab = ref('credentials')

// Estados reactivos de carga y visibilidad
const isLoading = ref(false)
const isSaving = ref(false)
const isTesting = ref(false)
const isSyncing = ref(false)
const showPassword = ref(false)
const supplierId = ref(null)
const supplierDetails = ref(null)

// Estado del modal de discrepancias y resultados
const showDiscrepanciesModal = ref(false)
const syncSummary = ref({
  updated: 0,
  created: 0,
  skipped: 0,
  total_extracted: 0,
  dromega: {},
  details: [],
})

const syncDiscrepancies = ref({
  paid_in_erp_pending_in_dromega: [],
  pending_in_erp_paid_in_dromega: [],
  total_discrepancies: 0,
})

// Modelo reactivo de configuración
const form = reactive({
  supplier_id: null,
  type: 'dromega_bot',
  host: 'https://www.drogueriamega.com/mydas',
  username: '',
  password: '',
  has_password: false,
  is_active: true,
  sync_frequency: 'daily',
})

// Control de cambios sin guardar (Dirty state)
const initialSnapshot = ref('')

const isDirty = computed(() => {
  return JSON.stringify({
    username: form.username,
    host: form.host,
    password: form.password,
    is_active: form.is_active,
    sync_frequency: form.sync_frequency,
  }) !== initialSnapshot.value
})

// Cargar datos de proveedor y configuración
const fetchSupplierData = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('/suppliers', { params: { search: 'DROMEGA' } })
    const list = data?.data || data || []
    let supplier = list.find(s => 
      s.name?.toUpperCase().includes('DROMEGA') || 
      s.name?.toUpperCase().includes('MEGA') ||
      [9, 15, 38, 1005].includes(s.id)
    )

    if (!supplier) {
      const megaRes = await axios.get('/suppliers', { params: { search: 'MEGA' } })
      const megaList = megaRes.data?.data || megaRes.data || []
      supplier = megaList[0] || list[0]
    }

    if (supplier) {
      supplierId.value = supplier.id
      supplierDetails.value = supplier
      form.supplier_id = supplier.id

      const connRes = await axios.get(`/suppliers/${supplier.id}/connection-config`).catch(() => 
        axios.get(`/suppliers/${supplier.id}/connection`)
      )
      const connData = connRes.data?.connections?.dromega_bot || connRes.data
      if (connData) {
        form.type = connData.type || 'dromega_bot'
        form.host = connData.host || 'https://www.drogueriamega.com/mydas'
        form.username = connData.username || ''
        form.has_password = Boolean(connData.has_password)
        form.is_active = connData.is_active !== undefined ? Boolean(connData.is_active) : true
        form.sync_frequency = connData.sync_frequency || 'daily'
      }
    }

    initialSnapshot.value = JSON.stringify({
      username: form.username,
      host: form.host,
      password: '',
      is_active: form.is_active,
      sync_frequency: form.sync_frequency,
    })
  } catch (error) {
    console.error('Error al cargar configuración de Droguería Mega:', error)
    toast.error('No se pudo cargar la configuración del Bot Droguería Mega.')
  } finally {
    isLoading.value = false
  }
}

// Guardar configuración
const saveConfig = async () => {
  if (!supplierId.value) {
    toast.error('No se encontró el proveedor Droguería Mega en el sistema.')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      type: 'dromega_bot',
      host: form.host || 'https://www.drogueriamega.com/mydas',
      username: form.username,
      is_active: form.is_active,
      sync_frequency: form.sync_frequency,
      pasv: true,
      has_header: true,
    }

    if (form.password) {
      payload.password = form.password
    }

    await axios.post(`/suppliers/${supplierId.value}/connection-config`, payload).catch(() =>
      axios.post(`/suppliers/${supplierId.value}/connection`, payload)
    )

    toast.success('Configuración del Bot Droguería Mega guardada correctamente.')
    form.password = ''
    await fetchSupplierData()
  } catch (error) {
    console.error('Error al guardar credenciales:', error)
    toast.error(error.response?.data?.message || 'Error al guardar la configuración.')
  } finally {
    isSaving.value = false
  }
}

// Prueba de conexión / Ping de credenciales
const testConnection = async () => {
  if (!form.username) {
    toast.warning('Ingresa el usuario antes de probar la conexión.')
    return
  }

  isTesting.value = true
  try {
    const payload = {
      supplier_id: supplierId.value,
      username: form.username,
      password: form.password || undefined,
      test_only: true,
    }

    const res = await axios.post('/suppliers/test-connection', payload).catch(() =>
      axios.post('/invoices/sync-dromega', { ...payload, dry_run: true })
    )

    toast.success(res.data?.message || 'Conexión con el portal Mydas verificada con éxito.')
  } catch (error) {
    console.error('Error al verificar conexión con Mydas:', error)
    toast.error(error.response?.data?.message || 'Fallo de autenticación con Droguería Mega.')
  } finally {
    isTesting.value = false
  }
}

// Ejecutar sincronización manual
const runSync = async () => {
  if (!supplierId.value) {
    toast.error('No se puede sincronizar sin un proveedor vinculado.')
    return
  }

  const result = await Swal.fire({
    title: '¿Iniciar sincronización con Droguería Mega?',
    text: 'El bot accederá a Mydas para extraer facturas, saldos, fechas de vencimiento y discrepancias.',
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Sí, sincronizar ahora',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#28C76F',
    cancelButtonColor: '#7A0099',
  })

  if (!result.isConfirmed) return

  isSyncing.value = true
  try {
    const payload = {
      supplier_id: supplierId.value,
    }
    if (form.username) payload.username = form.username
    if (form.password) payload.password = form.password

    const res = await axios.post('/invoices/sync-dromega', payload).catch(() =>
      axios.post('/sync-dromega', payload)
    )
    const resultData = res.data?.data || {}

    syncSummary.value = {
      updated: resultData.updated || 0,
      created: resultData.created || 0,
      skipped: resultData.skipped || 0,
      total_extracted: resultData.total_extracted || 0,
      dromega: resultData,
      details: resultData.details || resultData.processed || [],
    }

    syncDiscrepancies.value = resultData.discrepancies || {
      paid_in_erp_pending_in_dromega: [],
      pending_in_erp_paid_in_dromega: [],
      total_discrepancies: 0,
    }

    toast.success(res.data?.message || 'Sincronización con Droguería Mega completada.')
    showDiscrepanciesModal.value = true
  } catch (error) {
    console.error('Error al sincronizar con Droguería Mega:', error)
    toast.error(error.response?.data?.message || 'Error al ejecutar la sincronización.')
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
          Bot Droguería Mega (Dromega) — Sincronización Mydas
        </VCardTitle>

        <VCardSubtitle class="text-body-2">
          Gestión de credenciales de acceso automatizado al portal Mydas para descarga de facturas, cotejo de cuentas por pagar y detección de discrepancias.
        </VCardSubtitle>

        <template #append>
          <VBtn
            color="primary"
            variant="tonal"
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
      <!-- Contenedor Principal con Tabs -->
      <VCol cols="12" md="8">
        <VCard border flat rounded="lg">
          <VTabs v-model="currentTab" color="primary">
            <VTab value="credentials">
              <VIcon icon="tabler-key" class="me-2" size="20" />
              Credenciales Mydas
            </VTab>
            <VTab value="automation">
              <VIcon icon="tabler-settings-automation" class="me-2" size="20" />
              Automatización
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

            <VWindow v-model="currentTab">
              <!-- Tab 1: Credenciales -->
              <VWindowItem value="credentials">
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                  icon="tabler-shield-lock"
                  class="mb-6 rounded-lg"
                >
                  Las credenciales se transmiten y almacenan de forma segura para la ejecución automatizada del scraper Mydas.
                </VAlert>

                <VForm @submit.prevent="saveConfig">
                  <VRow>
                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="form.username"
                        label="Usuario / Código de Cliente"
                        placeholder="Ej: 20450"
                        prepend-inner-icon="tabler-user"
                        hint="Identificador o código de cuenta asignado en Droguería Mega"
                        persistent-hint
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
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
                        :hint="form.has_password ? 'Dejar en blanco para mantener la contraseña actual' : 'Se almacena encriptada'"
                        persistent-hint
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        @click:append-inner="showPassword = !showPassword"
                      />
                    </VCol>

                    <VCol cols="12">
                      <VTextField
                        v-model="form.host"
                        label="URL Base del Portal Mydas"
                        placeholder="https://www.drogueriamega.com/mydas"
                        prepend-inner-icon="tabler-world"
                        hint="Punto de acceso web para la sesión y consulta de estados de cuenta"
                        persistent-hint
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                      />
                    </VCol>

                    <VCol cols="12" class="d-flex flex-wrap align-center justify-space-between gap-3 mt-4 pt-4 border-t">
                      <VBtn
                        color="info"
                        variant="outlined"
                        prepend-icon="tabler-plug-connected"
                        :loading="isTesting"
                        :disabled="!form.username || isSyncing || isSaving"
                        @click="testConnection"
                      >
                        Probar Conexión
                      </VBtn>

                      <div class="d-flex align-center gap-3">
                        <VBtn
                          type="submit"
                          color="primary"
                          prepend-icon="tabler-device-floppy"
                          :loading="isSaving"
                          :disabled="!isDirty || !canManageBot"
                        >
                          Guardar Cambios
                        </VBtn>

                        <VBtn
                          color="success"
                          variant="elevated"
                          prepend-icon="tabler-player-play"
                          :loading="isSyncing"
                          :disabled="!canManageBot"
                          @click="runSync"
                        >
                          Sincronizar Ahora
                        </VBtn>
                      </div>
                    </VCol>
                  </VRow>
                </VForm>
              </VWindowItem>

              <!-- Tab 2: Automatización -->
              <VWindowItem value="automation">
                <VRow>
                  <VCol cols="12" md="6">
                    <VSwitch
                      v-model="form.is_active"
                      label="Activar Bot en Cron Automático"
                      color="primary"
                      hint="Habilita la extracción nocturna sin intervención manual"
                      persistent-hint
                      hide-details="auto"
                    />
                  </VCol>

                  <VCol cols="12" md="6">
                    <VSelect
                      v-model="form.sync_frequency"
                      label="Frecuencia de Extracción"
                      :items="[
                        { title: 'Diario (04:30 AM)', value: 'daily' },
                        { title: 'Cada 12 horas', value: 'twice_daily' },
                        { title: 'Bajo Demanda', value: 'manual' }
                      ]"
                      variant="outlined"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </VCol>

                  <VCol cols="12" class="d-flex justify-end mt-4">
                    <VBtn
                      color="primary"
                      prepend-icon="tabler-device-floppy"
                      :loading="isSaving"
                      :disabled="!isDirty || !canManageBot"
                      @click="saveConfig"
                    >
                      Guardar Parámetros
                    </VBtn>
                  </VCol>
                </VRow>
              </VWindowItem>
            </VWindow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Panel Lateral de Resumen y Contexto -->
      <VCol cols="12" md="4">
        <VCard border flat rounded="lg" class="mb-6">
          <VCardItem>
            <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-activity" color="success" size="22" />
              Estado Operativo
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2 text-medium-emphasis">Proveedor Vinculado:</span>
              <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
                {{ supplierId ? `ID: ${supplierId} (${supplierDetails?.name || 'Dromega'})` : 'No detectado' }}
              </VChip>
            </div>

            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2 text-medium-emphasis">Credenciales:</span>
              <VChip
                size="small"
                :color="form.has_password || form.username ? 'success' : 'warning'"
                variant="tonal"
              >
                {{ form.has_password ? 'Completas' : (form.username ? 'Parcial' : 'Sin configurar') }}
              </VChip>
            </div>

            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2 text-medium-emphasis">Horario Programado:</span>
              <VChip size="small" color="info" variant="tonal">
                04:30 AM Diario
              </VChip>
            </div>

            <div class="d-flex align-center justify-space-between">
              <span class="text-body-2 text-medium-emphasis">Discrepancias Previas:</span>
              <VBtn
                size="x-small"
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-eye"
                @click="showDiscrepanciesModal = true"
              >
                Ver Historial
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <VCard border flat rounded="lg">
          <VCardItem>
            <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-bulb" color="warning" size="22" />
              Capacidades de la Integración
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText class="text-body-2 text-medium-emphasis">
            <ul class="ps-4 mb-0 d-flex flex-column gap-2">
              <li>Autenticación por sesión y token CSRF en el portal Mydas.</li>
              <li>Descarga y parseo estructurado del estado de cuenta de droguería.</li>
              <li>Detección automática de discrepancias entre ERP y Droguería Mega.</li>
              <li>Almacenamiento seguro de credenciales con cifrado Laravel.</li>
            </ul>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Modal de Discrepancias -->
    <DronenaDiscrepanciesModal
      v-model="showDiscrepanciesModal"
      supplier-key="dromega"
      :discrepancies="syncDiscrepancies"
      :sync-summary="syncSummary"
      @close="showDiscrepanciesModal = false"
    />
  </div>
</template>
