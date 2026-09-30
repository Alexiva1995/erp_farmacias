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

// Estados reactivos de interfaz
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
  drocerca: {},
  details: [],
})
const syncDiscrepancies = ref({
  paid_in_erp_pending_in_drocerca: [],
  pending_in_erp_paid_in_drocerca: [],
  total_discrepancies: 0,
})

// Datos del formulario
const form = ref({
  supplier_id: null,
  type: 'drocerca_bot',
  host: 'http://drocerca.proteoerp.org:8082/proteoerp/portalcli',
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

// Cargar datos del proveedor Drocerca y su conexión registrada
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
      supplierDetails.value = supplier
      form.value.supplier_id = supplier.id

      // Cargar conexión configurada del proveedor
      const connRes = await axios.get(`/suppliers/${supplier.id}/connection-config`).catch(() => 
        axios.get(`/suppliers/${supplier.id}/connection`)
      )
      const connData = connRes.data?.connections?.drocerca_bot || connRes.data
      if (connData) {
        form.value.type = connData.type || 'drocerca_bot'
        form.value.host = connData.host || 'http://drocerca.proteoerp.org:8082/proteoerp/portalcli'
        form.value.username = connData.username || ''
        form.value.has_password = Boolean(connData.has_password)
      }
    }

    initialSnapshot.value = JSON.stringify({
      username: form.value.username,
      host: form.value.host,
      password: '',
    })
  } catch (error) {
    console.error('Error al cargar configuración de Drocerca:', error)
    toast.error('No se pudo cargar la configuración del Bot Drocerca.')
  } finally {
    isLoading.value = false
  }
}

// Guardar configuración de conexión
const saveConfig = async () => {
  if (!supplierId.value) {
    toast.error('No se encontró el proveedor Drocerca en el sistema.')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      type: 'drocerca_bot',
      host: form.value.host || 'http://drocerca.proteoerp.org:8082/proteoerp/portalcli',
      username: form.value.username,
      pasv: true,
      has_header: true,
    }

    if (form.value.password) {
      payload.password = form.value.password
    }

    await axios.post(`/suppliers/${supplierId.value}/connection-config`, payload).catch(() =>
      axios.post(`/suppliers/${supplierId.value}/connection`, payload)
    )
    toast.success('Configuración del Bot Drocerca guardada correctamente.')
    form.value.password = ''
    await fetchSupplierData()
  } catch (error) {
    console.error('Error al guardar credenciales de Drocerca:', error)
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
    title: '¿Iniciar sincronización con Drocerca?',
    text: 'El bot se conectará al portal ProteoERP para descargar facturas, tasas y actualizar cuentas por pagar.',
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

    const res = await axios.post('/sync-drocerca', payload)
    const resultData = res.data?.data || {}

    syncSummary.value = {
      updated: resultData.updated || 0,
      created: resultData.created || 0,
      skipped: resultData.skipped || 0,
      total_extracted: resultData.total_extracted || 0,
      drocerca: resultData,
      details: resultData.details || [],
    }

    syncDiscrepancies.value = resultData.discrepancies || {
      paid_in_erp_pending_in_drocerca: [],
      pending_in_erp_paid_in_drocerca: [],
      total_discrepancies: 0,
    }

    toast.success(res.data?.message || 'Sincronización con Drocerca completada exitosamente.')
    showDiscrepanciesModal.value = true
  } catch (error) {
    console.error('Error al sincronizar con Drocerca:', error)
    toast.error(error.response?.data?.message || 'Error al ejecutar la sincronización con Drocerca.')
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
          Configuración de credenciales de acceso automatizado al portal ProteoERP Drocerca para descarga y sincronización de facturas.
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
      <!-- Formulario de Configuración Principal -->
      <VCol cols="12" md="8">
        <VCard border flat rounded="lg">
          <VCardItem>
            <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-key" color="primary" size="22" />
              Credenciales de Acceso
            </VCardTitle>
            <VCardSubtitle class="text-body-2">
              Parámetros de autenticación para que el bot acceda a la plataforma web de Drocerca.
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
              density="comfortable"
              icon="tabler-info-circle"
              class="mb-6 rounded-lg"
            >
              El bot se conecta automáticamente a <strong>http://drocerca.proteoerp.org:8082/proteoerp/portalcli</strong>, accede a la sección de Facturación, descarga cada PDF digital de NovusFactura y extrae Número de Control, Fecha de Vencimiento, Tipo de Cambio (Tasa), Base Exenta, Base Imponible e IVA.
            </VAlert>

            <VForm @submit.prevent="saveConfig">
              <VRow>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="form.username"
                    label="Usuario / Código de Cliente"
                    placeholder="Ej: W008B3"
                    prepend-inner-icon="tabler-user"
                    hint="Código o usuario comercial asignado por Drocerca"
                    persistent-hint
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                    :disabled="isLoading || isSaving || !canManageBot"
                  />
                </VCol>

                <VCol cols="12" md="6">
                  <VTextField
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    label="Contraseña del Portal"
                    :placeholder="form.has_password ? '•••••••••••• (Configurada)' : 'Ingresa la contraseña'"
                    prepend-inner-icon="tabler-lock"
                    :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                    :hint="form.has_password ? 'Dejar en blanco para mantener la contraseña actual' : 'Se almacena encriptada de forma segura'"
                    persistent-hint
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                    :disabled="isLoading || isSaving || !canManageBot"
                    @click:append-inner="showPassword = !showPassword"
                  />
                </VCol>

                <VCol cols="12">
                  <VTextField
                    v-model="form.host"
                    label="URL del Portal Drocerca"
                    placeholder="http://drocerca.proteoerp.org:8082/proteoerp/portalcli"
                    prepend-inner-icon="tabler-world"
                    hint="URL base de la plataforma web de Drocerca"
                    persistent-hint
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                    :disabled="isLoading || isSaving || !canManageBot"
                  />
                </VCol>

                <VCol cols="12" class="mt-2">
                  <VRow>
                    <VCol cols="12" sm="6">
                      <VBtn
                        v-if="canManageBot"
                        type="submit"
                        color="primary"
                        block
                        density="comfortable"
                        prepend-icon="tabler-device-floppy"
                        :loading="isSaving"
                        :disabled="!isDirty || isLoading"
                      >
                        Guardar Configuración
                      </VBtn>
                    </VCol>

                    <VCol cols="12" :sm="canManageBot ? 6 : 12">
                      <VBtn
                        color="success"
                        variant="tonal"
                        block
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
                {{ supplierId ? `ID: ${supplierId} (${supplierDetails?.name || 'Drocerca'})` : 'No detectado' }}
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

            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2 text-medium-emphasis">Tarea Automática (Cron):</span>
              <VChip size="small" color="info" variant="tonal">
                04:30 AM Diario
              </VChip>
            </div>

            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2 text-medium-emphasis">Tipo de Integración:</span>
              <VChip size="small" color="secondary" variant="tonal">
                ProteoERP / NovusFactura
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
              <li>Inicia sesión automáticamente en <code>portalcli</code> de ProteoERP Drocerca.</li>
              <li>Extrae las facturas pendientes desde el módulo de Facturación.</li>
              <li>Descarga y lee cada PDF de NovusFactura para extraer fecha de vencimiento, número de control, tasa BCV, base exenta, imponible e IVA.</li>
              <li>Almacena los PDFs en el ERP y actualiza el módulo de cuentas por pagar.</li>
            </ul>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Modal Unificado de Discrepancias y Resultados -->
    <DronenaDiscrepanciesModal
      v-model="showDiscrepanciesModal"
      supplier-key="drocerca"
      :discrepancies="syncDiscrepancies"
      :sync-summary="syncSummary"
      @close="showDiscrepanciesModal = false"
    />
  </div>
</template>
