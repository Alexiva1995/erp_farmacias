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
  drosymca: {},
  details: [],
})

const syncDiscrepancies = ref({
  paid_in_erp_pending_in_drosymca: [],
  pending_in_erp_paid_in_drosymca: [],
  total_discrepancies: 0,
})

// Modelo reactivo de configuración
const form = reactive({
  supplier_id: null,
  type: 'drosymca_bot',
  host: 'https://app.drosymca.com',
  username: '',
  password: '',
  has_password: false,
  invoice_number: '',
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
    invoice_number: form.invoice_number,
    is_active: form.is_active,
    sync_frequency: form.sync_frequency,
  }) !== initialSnapshot.value
})

// Cargar datos de proveedor y configuración
const fetchSupplierData = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('/suppliers', { params: { search: 'DROSYMCA' } })
    const list = data?.data || data || []
    let supplier = list.find(s => 
      s.name?.toUpperCase().includes('DROSYMCA') || 
      s.name?.toUpperCase().includes('SYMCA') ||
      [10, 1006].includes(s.id)
    ) || list[0]

    if (supplier) {
      supplierId.value = supplier.id
      supplierDetails.value = supplier
      form.supplier_id = supplier.id

      const connRes = await axios.get(`/suppliers/${supplier.id}/connection-config`).catch(async () => {
        return await axios.get(`/suppliers/${supplier.id}/connection`)
      })

      const connData = connRes.data?.connections?.drosymca_bot || connRes.data
      if (connData) {
        form.type = connData.type || 'drosymca_bot'
        form.host = connData.host || 'https://app.drosymca.com'
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
      invoice_number: form.invoice_number,
      is_active: form.is_active,
      sync_frequency: form.sync_frequency,
    })
  } catch (error) {
    console.error('Error al cargar configuración de Drosymca:', error)
    toast.error('No se pudo cargar la configuración del Bot Drosymca.')
  } finally {
    isLoading.value = false
  }
}

// Guardar configuración
const saveConfig = async () => {
  if (!supplierId.value) {
    toast.error('No se encontró el proveedor Drosymca en el sistema.')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      type: 'drosymca_bot',
      host: form.host || 'https://app.drosymca.com',
      username: form.username,
      is_active: form.is_active,
      sync_frequency: form.sync_frequency,
      pasv: true,
      has_header: true,
    }

    if (form.password) {
      payload.password = form.password
    }

    await axios.post(`/suppliers/${supplierId.value}/connection-config`, payload).catch(async () => {
      return await axios.post(`/suppliers/${supplierId.value}/connection`, payload)
    })
    
    toast.success('Configuración del Bot Drosymca guardada correctamente.')
    form.password = ''
    await fetchSupplierData()
  } catch (error) {
    console.error('Error al guardar credenciales de Drosymca:', error)
    toast.error(error.response?.data?.message || 'Error al guardar la configuración.')
  } finally {
    isSaving.value = false
  }
}

// Prueba de conexión / Ping
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
      axios.post('/sync-drosymca', { ...payload, dry_run: true })
    )

    toast.success(res.data?.message || 'Conexión con el portal Drosymca verificada con éxito.')
  } catch (error) {
    console.error('Error al verificar conexión con Drosymca:', error)
    toast.error(error.response?.data?.message || 'Fallo de autenticación con Drosymca.')
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
    title: '¿Iniciar sincronización con Drosymca?',
    text: 'El bot accederá a app.drosymca.com para extraer facturas pendientes, saldos y montos de retención.',
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
    if (form.invoice_number) payload.invoice_number = form.invoice_number

    const response = await axios.post('/sync-drosymca', payload)
    const data = response.data?.data || {}

    syncSummary.value = {
      updated: data.updated || 0,
      created: data.created || 0,
      skipped: data.skipped || 0,
      total_extracted: (data.updated || 0) + (data.created || 0) + (data.skipped || 0),
      drosymca: data.drosymca || data.drosymca_invoices || {},
      details: data.details || [],
    }

    syncDiscrepancies.value = {
      paid_in_erp_pending_in_drosymca: data.discrepancies?.paid_in_erp_pending_in_drosymca || [],
      pending_in_erp_paid_in_drosymca: data.discrepancies?.pending_in_erp_paid_in_drosymca || [],
      total_discrepancies: data.discrepancies?.total_discrepancies || 0,
    }

    showDiscrepanciesModal.value = true
    toast.success(response.data?.message || 'Sincronización con Drosymca completada exitosamente.')
  } catch (error) {
    console.error('Error al sincronizar con Drosymca:', error)
    toast.error(error.response?.data?.message || 'Error al ejecutar la sincronización con Drosymca.')
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
          Bot Drosymca — Sincronización de Facturas
        </VCardTitle>

        <VCardSubtitle class="text-body-2">
          Configuración de credenciales de acceso automatizado al portal de cobranzas de Drosymca para descarga de facturas, cotejo fiscal y discrepancias.
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
      <!-- Contenedor Principal con Credenciales -->
      <VCol cols="12" md="8">
        <VCard border flat rounded="lg">
          <VCardItem class="pb-2">
            <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-key" color="primary" size="22" />
              Credenciales Drosymca
            </VCardTitle>
          </VCardItem>

          <VDivider />

          <VCardText class="pt-6">
            <VProgressLinear
              v-if="isLoading"
              indeterminate
              color="primary"
              class="mb-4"
            />

            <VAlert
              type="info"
              variant="tonal"
              density="compact"
              icon="tabler-shield-lock"
              class="mb-6 rounded-lg"
            >
              Las credenciales se encriptan en el backend y se utilizan exclusivamente en las sesiones seguras del scraper.
            </VAlert>

            <VForm @submit.prevent="saveConfig">
              <VRow>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="form.username"
                    label="Usuario / Código de Cliente"
                    placeholder="Ej: usuario Drosymca"
                    prepend-inner-icon="tabler-user"
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                    hint="Usuario asignado en el portal de Drosymca"
                    persistent-hint
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
                    @click:append-inner="showPassword = !showPassword"
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="form.host"
                    label="URL del Portal Drosymca"
                    placeholder="https://app.drosymca.com"
                    prepend-inner-icon="tabler-world"
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                    hint="URL base de la plataforma web de Drosymca"
                    persistent-hint
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="form.invoice_number"
                    label="Factura Específica (Opcional)"
                    placeholder="Ej: FACT-98765"
                    prepend-inner-icon="tabler-file-invoice"
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                    hint="Dejar vacío para sincronizar todas las pendientes"
                    persistent-hint
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
                {{ supplierId ? `ID: ${supplierId} (${supplierDetails?.name || 'Drosymca'})` : 'No detectado' }}
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
                04:45 AM Diario
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
              <li>Conexión autenticada directa a <code>app.drosymca.com</code>.</li>
              <li>Consulta y extracción de facturas y comprobantes pendientes.</li>
              <li>Actualización de montos netos, base imponible, tasas e IVA.</li>
              <li>Cotejo automatizado con las cuentas por pagar del ERP.</li>
            </ul>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Modal de Discrepancias y Resumen de Sincronización -->
    <DronenaDiscrepanciesModal
      v-model="showDiscrepanciesModal"
      supplier-key="drosymca"
      :discrepancies="syncDiscrepancies"
      :sync-summary="syncSummary"
      @refresh="fetchSupplierData"
    />
  </div>
</template>
