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
const isTestingToken = ref(false)
const showTokenHelp = ref(false)
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
  session_token: '',
  has_session_token: false,
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
    session_token: form.value.session_token,
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
        form.value.session_token = connData.session_token || ''
        form.value.has_session_token = Boolean(connData.has_session_token || connData.session_token)
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
      session_token: form.value.session_token,
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
      session_token: form.value.session_token || '',
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

// Validar y activar token de sesión en vivo
const testAndSaveToken = async () => {
  if (!supplierId.value) {
    toast.error('No se encontró el proveedor vinculado.')
    return
  }

  if (!form.value.session_token || !form.value.session_token.trim()) {
    toast.warning('Por favor pega un Token de Sesión JWT antes de verificar.')
    return
  }

  isTestingToken.value = true
  try {
    const { data } = await axios.post(`/suppliers/${supplierId.value}/mafarta-token`, {
      session_token: form.value.session_token.trim(),
      username: form.value.username,
      save: true,
    })

    if (data.success) {
      toast.success(data.message || 'Token de sesión verificado y guardado con éxito.')
      form.value.has_session_token = true
      await fetchSupplierData()
    } else {
      toast.error(data.message || 'El token no es válido en Cobeca.')
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al validar el token de sesión con Cobeca.')
  } finally {
    isTestingToken.value = false
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
    if (form.value.session_token) payload.session_token = form.value.session_token

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
    const errorMsg = error.response?.data?.message || 'Error al ejecutar la sincronización con Mafarta / Cobeca.'
    if (errorMsg.includes('Turnstile') || errorMsg.includes('Cloudflare')) {
      Swal.fire({
        title: 'Verificación Cloudflare Turnstile Requerida',
        html: `
          <div style="font-size: 13px; text-align: left; line-height: 1.5;">
            <p style="margin-bottom: 8px;">Droguerías Cobeca implementó verificación Cloudflare Turnstile en su pantalla de login.</p>
            <p style="margin-bottom: 8px; font-weight: 600; color: #ff9f43;">Solución recomendada (Bypass directo):</p>
            <ol style="margin-bottom: 0; padding-left: 18px;">
              <li>Inicia sesión normalmente en <strong>sic.drogueriascobeca.com/autenticacion</strong> en tu navegador.</li>
              <li>Abre la consola (F12) y ejecuta: <code>localStorage.getItem('token')</code>.</li>
              <li>Copia el token y pégalo en el recuadro <strong>Token de Sesión JWT</strong> para sincronizar sin bloqueos.</li>
            </ol>
          </div>
        `,
        icon: 'warning',
        confirmButtonText: 'Entendido, voy a ingresar el token',
        confirmButtonColor: '#ff9f43',
      })
      showTokenHelp.value = true
    } else {
      toast.error(errorMsg)
    }
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

                <VCol cols="12">
                  <VCard variant="tonal" color="warning" class="pa-4 rounded-lg">
                    <div class="d-flex align-center justify-space-between mb-2">
                      <div class="d-flex align-center gap-2">
                        <VIcon icon="tabler-shield-check" color="warning" size="22" />
                        <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
                          Token de Sesión Cloudflare Turnstile (Bypass Automático)
                        </span>
                      </div>
                      <div class="d-flex align-center gap-2">
                        <VChip
                          size="x-small"
                          :color="form.has_session_token ? 'success' : 'warning'"
                          variant="elevated"
                        >
                          {{ form.has_session_token ? 'Token Activo' : 'Sin Token' }}
                        </VChip>
                        <VBtn
                          size="x-small"
                          variant="text"
                          color="warning"
                          icon="tabler-help"
                          @click="showTokenHelp = !showTokenHelp"
                        />
                      </div>
                    </div>

                    <p class="text-caption text-medium-emphasis mb-3">
                      Si Cobeca bloquea el inicio de sesión con el captcha Cloudflare Turnstile, pega aquí el Token JWT de tu sesión activa del navegador para consultar facturas y estados de cuenta directamente.
                    </p>

                    <VTextarea
                      v-model="form.session_token"
                      label="Token JWT de Sesión (Bearer Token)"
                      placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
                      variant="outlined"
                      density="compact"
                      rows="3"
                      auto-grow
                      class="mb-3"
                      hide-details="auto"
                    />

                    <div class="d-flex justify-space-between align-center flex-wrap gap-2">
                      <div class="d-flex gap-2">
                        <VBtn
                          color="secondary"
                          variant="tonal"
                          size="small"
                          prepend-icon="tabler-external-link"
                          href="https://sic.drogueriascobeca.com/autenticacion"
                          target="_blank"
                        >
                          Abrir Portal Cobeca SIC
                        </VBtn>

                        <VBtn
                          color="primary"
                          variant="outlined"
                          size="small"
                          prepend-icon="tabler-download"
                          href="/extensions/conector-cobeca.zip"
                          download="conector-cobeca.zip"
                        >
                          Descargar Extensión (.zip)
                        </VBtn>
                      </div>

                      <div class="d-flex gap-2">
                        <VBtn
                          v-if="canManageBot"
                          color="warning"
                          variant="flat"
                          size="small"
                          prepend-icon="tabler-check"
                          :loading="isTestingToken"
                          :disabled="!form.session_token"
                          @click="testAndSaveToken"
                        >
                          Validar y Activar Token
                        </VBtn>
                      </div>
                    </div>

                    <!-- Ayuda desplegable de cómo extraer el token -->
                    <VExpandTransition>
                      <div v-if="showTokenHelp" class="mt-3 pt-3 border-t">
                        <span class="text-caption font-weight-bold d-block mb-1">
                          ¿Cómo obtener el token en 10 segundos desde tu navegador?
                        </span>
                        <ol class="text-caption text-medium-emphasis ps-4 mb-0">
                          <li>Inicia sesión normalmente en <strong>https://sic.drogueriascobeca.com/autenticacion</strong>.</li>
                          <li>Presiona <strong>F12</strong> (o clic derecho > Inspeccionar) y ve a la pestaña <strong>Consola</strong>.</li>
                          <li>Escribe <code>localStorage.getItem('token')</code> o copia el valor del header <code>Authorization: Bearer ...</code> en la pestaña <strong>Red (Network)</strong>.</li>
                          <li>Pega el texto aquí y haz clic en <strong>Validar y Activar Token</strong>.</li>
                        </ol>
                      </div>
                    </VExpandTransition>
                  </VCard>
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

            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2 text-medium-emphasis">Bypass Cloudflare:</span>
              <VChip
                size="small"
                :color="form.has_session_token ? 'success' : 'grey'"
                variant="tonal"
              >
                {{ form.has_session_token ? 'Token Activo' : 'Inactivo / Opcional' }}
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
