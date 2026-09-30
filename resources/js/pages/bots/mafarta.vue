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
  type: 'mafarta_bot',
  host: 'https://sic.drogueriascobeca.com',
  username: '',
  password: '',
  has_password: false,
  is_active: true,
  sync_frequency: 'daily',
})

// Cargar datos del proveedor Mafarta / Cobeca y su conexión
const fetchSupplierData = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('/suppliers', { params: { search: 'MAFARTA' } })
    const list = data?.data || data || []
    let supplier = list.find(s => 
      s.name?.toUpperCase().includes('MAFARTA') || 
      s.name?.toUpperCase().includes('COBECA') ||
      s.id === 23
    )

    if (!supplier) {
      const cobecaRes = await axios.get('/suppliers', { params: { search: 'COBECA' } })
      const cobecaList = cobecaRes.data?.data || cobecaRes.data || []
      supplier = cobecaList[0] || list[0]
    }

    if (supplier) {
      supplierId.value = supplier.id
      form.value.supplier_id = supplier.id

      // Cargar conexión configurada
      const connRes = await axios.get(`/suppliers/${supplier.id}/connection`)
      if (connRes.data && connRes.data.type) {
        form.value.type = connRes.data.type || 'mafarta_bot'
        form.value.host = connRes.data.host || 'https://sic.drogueriascobeca.com'
        form.value.username = connRes.data.username || ''
        form.value.has_password = Boolean(connRes.data.has_password)
      }
    }
  } catch (error) {
    console.error('Error al cargar configuración de Mafarta/Cobeca:', error)
    toast.error('No se pudo cargar la configuración de Mafarta / Cobeca')
  } finally {
    isLoading.value = false
  }
}

// Guardar configuración
const saveConfig = async () => {
  if (!supplierId.value) {
    toast.error('No se encontró el proveedor Mafarta / Cobeca registrado')
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

    await axios.post(`/suppliers/${supplierId.value}/connection`, payload)
    toast.success('Configuración del Bot Mafarta / Cobeca guardada correctamente')
    form.value.password = ''
    fetchSupplierData()
  } catch (error) {
    console.error('Error al guardar credenciales de Mafarta / Cobeca:', error)
    toast.error(error.response?.data?.message || 'Error al guardar la configuración')
  } finally {
    isSaving.value = false
  }
}

// Ejecutar sincronización manual con el bot de Mafarta / Cobeca
const runSync = async () => {
  isSyncing.value = true
  try {
    const payload = {
      supplier_id: supplierId.value,
    }
    if (form.value.username) payload.username = form.value.username
    if (form.value.password) payload.password = form.value.password

    const res = await axios.post('/sync-mafarta', payload)
    toast.success(res.data?.message || 'Sincronización con Mafarta / Cobeca completada exitosamente')
  } catch (error) {
    console.error('Error al sincronizar con Mafarta / Cobeca:', error)
    toast.error(error.response?.data?.message || 'Error al ejecutar la sincronización con Mafarta / Cobeca')
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
          Configuración de credenciales de acceso automatizado a la plataforma SIC Droguerías Cobeca para consulta y sincronización de facturas, vencimientos y montos en divisas.
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
              Credenciales del Bot Mafarta / Cobeca
            </VCardTitle>
            <VCardSubtitle>
              Ingresa los datos para la conexión y extracción automatizada con la plataforma SIC de Droguerías Cobeca.
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
              El bot se conecta a <strong>https://sic.drogueriascobeca.com</strong> mediante autenticación por token, consulta el estado de cuenta oficial de Cobeca/Mafarta, obtiene el detalle de cada factura y sincroniza fecha de vencimiento, tasa y saldo indexado.
            </VAlert>

            <VForm @submit.prevent="saveConfig">
              <VRow>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="form.username"
                    label="Usuario / RIF / Código de Cliente"
                    placeholder="Ej: J123456780 o usuario SIC"
                    prepend-inner-icon="tabler-user"
                    hint="Usuario asignado en la plataforma SIC Cobeca"
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

                <VCol cols="12">
                  <VTextField
                    v-model="form.host"
                    label="URL de la Plataforma SIC"
                    placeholder="https://sic.drogueriascobeca.com"
                    prepend-inner-icon="tabler-world"
                    hint="URL base del portal API/SIC de Droguerías Cobeca"
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
              Estado del Bot Mafarta / Cobeca
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2 text-medium-emphasis">Proveedor vinculado:</span>
              <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
                {{ supplierId ? `ID: ${supplierId} (Mafarta/Cobeca)` : 'No detectado' }}
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
              <VChip size="small" color="info" variant="tonal">
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
  </div>
</template>
