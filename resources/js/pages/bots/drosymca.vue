<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

// Estado reactivo
const isLoading = ref(false)
const isSaving = ref(false)
const isSyncing = ref(false)
const showPassword = ref(false)
const supplierId = ref(null)

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

// Cargar datos del proveedor Drosymca y su conexión
const fetchSupplierData = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('/suppliers', { params: { search: 'DROSYMCA' } })
    const list = data?.data || data || []
    let supplier = list.find(s => 
      s.name?.toUpperCase().includes('DROSYMCA') || 
      s.name?.toUpperCase().includes('SYMCA') ||
      s.id === 1006
    ) || list[0]

    if (supplier) {
      supplierId.value = supplier.id
      form.value.supplier_id = supplier.id

      // Cargar conexión configurada
      const connRes = await axios.get(`/suppliers/${supplier.id}/connection`)
      if (connRes.data && connRes.data.type) {
        form.value.type = connRes.data.type || 'drosymca_bot'
        form.value.host = connRes.data.host || 'https://app.drosymca.com'
        form.value.username = connRes.data.username || ''
        form.value.has_password = Boolean(connRes.data.has_password)
      }
    }
  } catch (error) {
    console.error('Error al cargar configuración de Drosymca:', error)
    toast.error('No se pudo cargar la configuración de Drosymca')
  } finally {
    isLoading.value = false
  }
}

// Guardar configuración
const saveConfig = async () => {
  if (!supplierId.value) {
    toast.error('No se encontró el proveedor Drosymca registrado')
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

    await axios.post(`/suppliers/${supplierId.value}/connection`, payload)
    toast.success('Configuración del Bot Drosymca guardada correctamente')
    form.value.password = ''
    fetchSupplierData()
  } catch (error) {
    console.error('Error al guardar credenciales de Drosymca:', error)
    toast.error(error.response?.data?.message || 'Error al guardar la configuración')
  } finally {
    isSaving.value = false
  }
}

// Ejecutar sincronización manual con el bot de Drosymca
const runSync = async () => {
  isSyncing.value = true
  try {
    const payload = {
      supplier_id: supplierId.value,
    }
    if (form.value.username) payload.username = form.value.username
    if (form.value.password) payload.password = form.value.password
    if (form.value.invoice_number) payload.invoice_number = form.value.invoice_number

    const res = await axios.post('/sync-drosymca', payload)
    toast.success(res.data?.message || 'Sincronización con Drosymca completada exitosamente')
  } catch (error) {
    console.error('Error al sincronizar con Drosymca:', error)
    toast.error(error.response?.data?.message || 'Error al ejecutar la sincronización con Drosymca')
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
                    hint="Dejar vacío para sincronizar todas las pendientes"
                    persistent-hint
                  />
                </VCol>

                <VCol cols="12" class="d-flex align-center gap-4 mt-2">
                  <VBtn
                    type="submit"
                    color="primary"
                    prepend-icon="tabler-device-floppy"
                    :loading="isSaving"
                  >
                    Guardar Configuración
                  </VBtn>

                  <VBtn
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
  </div>
</template>
