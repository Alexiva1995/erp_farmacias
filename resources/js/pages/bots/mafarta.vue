<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'
import DronenaDiscrepanciesModal from '@/components/dialogs/DronenaDiscrepanciesModal.vue'
import { useAbility } from '@casl/vue'

// Control de permisos CASL
const ability = useAbility()
const canManageBot = computed(() => ability.can('manage', 'bots') || ability.can('update', 'Supplier') || ability.can('manage', 'all'))

// Estado reactivo del componente
const isLoading = ref(false)
const isSaving = ref(false)
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
  mafarta: {},
  details: [],
})
const syncDiscrepancies = ref({
  paid_in_erp_pending_in_mafarta: [],
  pending_in_erp_paid_in_mafarta: [],
  total_discrepancies: 0,
})

// Datos del formulario
const form = ref({
  supplier_id: null,
  type: 'mafarta_bot',
  host: 'https://sic.drogueriascobeca.com',
  username: '',
  password: '',
  has_password: false,
  is_active: true,
  sync_frequency: 'daily',
})

// Snapshot inicial para detección de cambios (Dirty state)
const initialSnapshot = ref('')

const isDirty = computed(() => {
  return JSON.stringify({
    username: form.value.username,
    host: form.value.host,
    password: form.value.password,
  }) !== initialSnapshot.value
})

// Cargar datos del proveedor Mafarta / Cobeca y su conexión registrada
const fetchSupplierData = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('/suppliers', { params: { q: 'MAFARTA', search: 'MAFARTA', itemsPerPage: -1 } })
    const list = data?.data || data || []
    let supplier = list.find(s => 
      s.name?.toUpperCase().includes('MAFARTA') || 
      s.name?.toUpperCase().includes('COBECA')
    )

    if (!supplier) {
      const cobecaRes = await axios.get('/suppliers', { params: { q: 'COBECA', search: 'COBECA', itemsPerPage: -1 } })
      const cobecaList = cobecaRes.data?.data || cobecaRes.data || []
      supplier = cobecaList.find(s => 
        s.name?.toUpperCase().includes('MAFARTA') || 
        s.name?.toUpperCase().includes('COBECA')
      )
    }

    if (!supplier) {
      const allRes = await axios.get('/suppliers', { params: { itemsPerPage: -1 } })
      const allList = allRes.data?.data || allRes.data || []
      supplier = allList.find(s => 
        s.name?.toUpperCase().includes('MAFARTA') || 
        s.name?.toUpperCase().includes('COBECA') ||
        [23, 25].includes(s.id)
      )
    }

    if (supplier) {
      supplierId.value = supplier.id
      supplierDetails.value = supplier
      form.value.supplier_id = supplier.id

      // Cargar conexión configurada del proveedor
      const connRes = await axios.get(`/suppliers/${supplier.id}/connection-config`)
      const connData = connRes.data?.connections?.mafarta_bot || connRes.data
      if (connData) {
        form.value.type = connData.type || 'mafarta_bot'
        form.value.host = connData.host || 'https://sic.drogueriascobeca.com'
        form.value.username = connData.username || ''
        form.value.has_password = Boolean(connData.has_password)
      }
    } else {
      supplierId.value = null
      supplierDetails.value = null
      form.value.supplier_id = null
    }

    initialSnapshot.value = JSON.stringify({
      username: form.value.username,
      host: form.value.host,
      password: '',
    })
  } catch (error) {
    console.error('Error al cargar configuración de Mafarta/Cobeca:', error)
    toast.error('No se pudo cargar la configuración del Bot Mafarta / Cobeca.')
  } finally {
    isLoading.value = false
  }
}

// Guardar configuración de conexión
const saveConfig = async () => {
  if (!supplierId.value) {
    toast.error('No se encontró el proveedor Mafarta / Cobeca en el sistema.')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      type: 'mafarta_bot',
      host: form.value.host || 'https://sic.drogueriascobeca.com',
      username: form.value.username,
      pasv: true,
      has_header: true,
    }

    if (form.value.password) {
      payload.password = form.value.password
    }

    await axios.post(`/suppliers/${supplierId.value}/connection-config`, payload)
    toast.success('Configuración del Bot Mafarta / Cobeca guardada correctamente.')
    form.value.password = ''
    await fetchSupplierData()
  } catch (error) {
    console.error('Error al guardar credenciales de Mafarta / Cobeca:', error)
    toast.error(error.response?.data?.message || 'Error al guardar la configuración.')
  } finally {
    isSaving.value = false
  }
}

// Confirmar y ejecutar sincronización manual con el bot
const runSync = async () => {
  if (!supplierId.value) {
    toast.error('No se puede sincronizar sin un proveedor vinculado.')
    return
  }

  const result = await Swal.fire({
    title: '¿Iniciar sincronización con Mafarta / Cobeca?',
    text: 'El bot se autenticará en la plataforma SIC para consultar el estado de cuenta, facturas pendientes y actualizar saldos indexados.',
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Sí, ejecutar sincronización',
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
    if (form.value.username) payload.username = form.value.username
    if (form.value.password) payload.password = form.value.password

    const res = await axios.post('/sync-mafarta', payload)
    const resultData = res.data?.data || {}

    syncSummary.value = {
      updated: resultData.updated || 0,
      created: resultData.created || 0,
      skipped: resultData.skipped || 0,
      total_extracted: resultData.total_extracted || 0,
      mafarta: resultData,
      details: resultData.details || [],
    }

    syncDiscrepancies.value = resultData.discrepancies || {
      paid_in_erp_pending_in_mafarta: [],
      pending_in_erp_paid_in_mafarta: [],
      total_discrepancies: 0,
    }

    toast.success(res.data?.message || 'Sincronización con Mafarta / Cobeca completada exitosamente.')
    showDiscrepanciesModal.value = true
  } catch (error) {
    console.error('Error al sincronizar con Mafarta / Cobeca:', error)
    toast.error(error.response?.data?.message || 'Error al ejecutar la sincronización con Mafarta / Cobeca.')
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
          Bot Cobeca / Mafarta — Extracción y Sincronización
        </VCardTitle>

        <VCardSubtitle class="text-body-2">
          Configuración de credenciales de acceso automatizado a la plataforma SIC Droguerías Cobeca para consulta y sincronización de facturas, vencimientos y saldos indexados.
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
      <!-- Formulario de Configuración -->
      <VCol cols="12" md="8">
        <VCard border flat rounded="lg">
          <VCardItem>
            <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-key" color="primary" size="22" />
              Credenciales de Acceso
            </VCardTitle>
            <VCardSubtitle class="text-body-2">
              Parámetros de autenticación para que el bot acceda a la plataforma SIC de Droguerías Cobeca.
            </VCardSubtitle>
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
              icon="tabler-info-circle"
              class="mb-6 rounded-lg"
            >
              El bot se conecta a <strong>https://sic.drogueriascobeca.com</strong> mediante autenticación de servicio SIC, consulta el estado de cuenta y sincroniza fecha de vencimiento, tasa y saldo indexado.
            </VAlert>

            <VForm @submit.prevent="saveConfig">
              <VRow>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="form.username"
                    label="Usuario / RIF / Código de Cliente"
                    placeholder="Ej: J123456780 o usuario SIC"
                    prepend-inner-icon="tabler-user"
                    hint="Usuario o RIF asignado en la plataforma SIC Cobeca"
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
                    :hint="form.has_password ? 'Dejar en blanco para mantener la contraseña actual' : 'Se almacena encriptada de forma segura'"
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
                    label="URL de la Plataforma SIC"
                    placeholder="https://sic.drogueriascobeca.com"
                    prepend-inner-icon="tabler-world"
                    hint="URL base del portal API/SIC de Droguerías Cobeca"
                    persistent-hint
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                  />
                </VCol>

                <VCol v-if="canManageBot" cols="12" class="mt-2">
                  <VRow>
                    <VCol cols="12" sm="6">
                      <VBtn
                        type="submit"
                        color="primary"
                        block
                        prepend-icon="tabler-device-floppy"
                        :loading="isSaving"
                        :disabled="!isDirty"
                      >
                        Guardar Configuración
                      </VBtn>
                    </VCol>

                    <VCol cols="12" sm="6">
                      <VBtn
                        color="success"
                        variant="tonal"
                        block
                        prepend-icon="tabler-player-play"
                        :loading="isSyncing"
                        @click="runSync"
                      >
                        Ejecutar Sincronización Ahora
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Panel Lateral de Estado e Información -->
      <VCol cols="12" md="4">
        <VCard border flat rounded="lg" class="mb-6">
          <VCardItem>
            <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-activity" color="success" size="22" />
              Estado del Servicio
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2 text-medium-emphasis">Proveedor vinculado:</span>
              <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
                {{ supplierId ? `ID: ${supplierId} (${supplierDetails?.name || 'Cobeca/Mafarta'})` : 'No detectado' }}
              </VChip>
            </div>

            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2 text-medium-emphasis">Credenciales:</span>
              <VChip
                size="small"
                :color="form.has_password || form.username ? 'success' : 'warning'"
                variant="tonal"
              >
                {{ form.has_password ? 'Configuradas' : (form.username ? 'Parcial' : 'Sin configurar') }}
              </VChip>
            </div>

            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2 text-medium-emphasis">Tipo de Integración:</span>
              <VChip size="small" color="secondary" variant="tonal">
                API SIC Token Auth
              </VChip>
            </div>
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
              <li>Se autentica directamente con los servicios SIC de Droguerías Cobeca.</li>
              <li>Obtiene el estado de cuenta y lista de facturas pendientes con sus fechas de vencimiento.</li>
              <li>Detecta automáticamente si la factura está indexada o dolarizada.</li>
              <li>Actualiza los saldos y números de control en el ERP.</li>
            </ul>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Modal de Discrepancias y Resultados -->
    <DronenaDiscrepanciesModal
      v-model="showDiscrepanciesModal"
      supplier-key="mafarta"
      :discrepancies="syncDiscrepancies"
      :sync-summary="syncSummary"
      @close="showDiscrepanciesModal = false"
    />
  </div>
</template>
