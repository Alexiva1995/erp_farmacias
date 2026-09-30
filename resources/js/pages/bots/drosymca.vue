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
  drosymca: {},
  details: [],
})
const syncDiscrepancies = ref({
  paid_in_erp_pending_in_drosymca: [],
  pending_in_erp_paid_in_drosymca: [],
  total_discrepancies: 0,
})

// Datos del formulario
const form = ref({
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

// Snapshot inicial para detección de cambios (Dirty state)
const initialSnapshot = ref('')

const isDirty = computed(() => {
  return JSON.stringify({
    username: form.value.username,
    host: form.value.host,
    password: form.value.password,
    invoice_number: form.value.invoice_number,
  }) !== initialSnapshot.value
})

// Cargar datos del proveedor Drosymca y su conexión registrada
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
      form.value.supplier_id = supplier.id

      // Cargar conexión configurada del proveedor
      const connRes = await axios.get(`/suppliers/${supplier.id}/connection-config`).catch(async () => {
        return await axios.get(`/suppliers/${supplier.id}/connection`)
      })

      const connData = connRes.data?.connections?.drosymca_bot || connRes.data
      if (connData) {
        form.value.type = connData.type || 'drosymca_bot'
        form.value.host = connData.host || 'https://app.drosymca.com'
        form.value.username = connData.username || ''
        form.value.has_password = Boolean(connData.has_password)
      }
    }

    initialSnapshot.value = JSON.stringify({
      username: form.value.username,
      host: form.value.host,
      password: '',
      invoice_number: form.value.invoice_number,
    })
  } catch (error) {
    console.error('Error al cargar configuración de Drosymca:', error)
    toast.error('No se pudo cargar la configuración del Bot Drosymca.')
  } finally {
    isLoading.value = false
  }
}

// Guardar configuración de conexión
const saveConfig = async () => {
  if (!supplierId.value) {
    toast.error('No se encontró el proveedor Drosymca en el sistema.')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      type: 'drosymca_bot',
      host: form.value.host || 'https://app.drosymca.com',
      username: form.value.username,
      pasv: true,
      has_header: true,
    }

    if (form.value.password) {
      payload.password = form.value.password
    }

    await axios.post(`/suppliers/${supplierId.value}/connection-config`, payload).catch(async () => {
      return await axios.post(`/suppliers/${supplierId.value}/connection`, payload)
    })
    toast.success('Configuración del Bot Drosymca guardada correctamente.')
    form.value.password = ''
    await fetchSupplierData()
  } catch (error) {
    console.error('Error al guardar credenciales de Drosymca:', error)
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
    title: '¿Iniciar sincronización con Drosymca?',
    html: `
      <div class="text-start text-body-2">
        <p class="mb-2">El bot automatizado realizará las siguientes acciones:</p>
        <ul class="ps-4 mb-0">
          <li>Iniciar sesión en el portal web de <strong>Drosymca</strong> (<code>app.drosymca.com</code>).</li>
          <li>Consultar y extraer todas las facturas y comprobantes pendientes de pago.</li>
          <li>Actualizar o registrar montos, vencimientos y tasas oficiales.</li>
        </ul>
      </div>
    `,
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Sí, ejecutar sincronización',
    cancelButtonText: 'Cancelar',
    customClass: {
      confirmButton: 'v-btn v-btn--elevated bg-primary text-white me-3',
      cancelButton: 'v-btn v-btn--outlined text-secondary',
    },
    buttonsStyling: false,
  })

  if (!result.isConfirmed) return

  isSyncing.value = true
  try {
    const payload = {
      supplier_id: supplierId.value,
    }
    if (form.value.username) payload.username = form.value.username
    if (form.value.password) payload.password = form.value.password
    if (form.value.invoice_number) payload.invoice_number = form.value.invoice_number

    const response = await axios.post('/sync-drosymca', payload)
    const data = response.data?.data || {}

    // Resumen para el modal unificado de resultados
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
    toast.error(error.response?.data?.message || 'Error crítico al ejecutar la sincronización con Drosymca.')
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
          Bot Drosymca — Extracción y Sincronización
        </VCardTitle>

        <VCardSubtitle class="text-body-2">
          Configuración de credenciales de acceso automatizado a la plataforma de cobranzas de Drosymca para sincronización de facturas, vencimientos y comprobantes.
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
              Credenciales del Bot Drosymca
            </VCardTitle>
            <VCardSubtitle>
              Ingresa los datos para la conexión y extracción automatizada con el portal de Drosymca.
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
              El bot se conecta a <strong>https://app.drosymca.com</strong>, autentica el usuario de cobranza/facturación, extrae las facturas pendientes con sus fechas de vencimiento, montos fiscales y tasa oficial.
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

                <VCol cols="12" class="d-flex align-center gap-4 mt-2">
                  <VBtn
                    v-if="canManageBot"
                    type="submit"
                    color="primary"
                    prepend-icon="tabler-device-floppy"
                    :loading="isSaving"
                    :disabled="!isDirty"
                  >
                    Guardar Configuración
                  </VBtn>

                  <VBtn
                    v-if="canManageBot"
                    color="success"
                    variant="tonal"
                    prepend-icon="tabler-player-play"
                    :loading="isSyncing"
                    @click="runSync"
                  >
                    Ejecutar Sincronización Ahora
                  </VBtn>
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
              Estado del Bot Drosymca
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2 text-medium-emphasis">Proveedor vinculado:</span>
              <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
                {{ supplierId ? `ID: ${supplierId} (Drosymca)` : 'No detectado' }}
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
              <span class="text-body-2 text-medium-emphasis">Tarea Automática (Cron):</span>
              <VChip size="small" color="info" variant="tonal">
                04:45 AM Diario
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
              <li>Se conecta a <code>app.drosymca.com</code> con sesión autenticada.</li>
              <li>Consulta facturas y comprobantes emitidos pendientes de pago.</li>
              <li>Extrae fechas de vencimiento, montos netos, base imponible e IVA.</li>
              <li>Ejecución desatendida programada a las 04:45 AM cada madrugada.</li>
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
