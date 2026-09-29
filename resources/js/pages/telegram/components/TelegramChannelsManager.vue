<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from '@axios'
import Swal from 'sweetalert2'
import TelegramChannelDialog from './TelegramChannelDialog.vue'

const channels = ref([])
const loading = ref(false)
const testingId = ref(null)
const togglingId = ref(null)
const savingChannel = ref(false)

const dialogShow = ref(false)
const selectedChannel = ref(null)

const snackbar = reactive({
  show: false,
  text: '',
  color: 'success',
})

const showToast = (text, color = 'success') => {
  snackbar.text = text
  snackbar.color = color
  snackbar.show = true
}

const fetchChannels = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/telegram/channels')
    channels.value = data.data || []
  } catch (error) {
    showToast('Error al cargar la lista de canales.', 'error')
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  selectedChannel.value = null
  dialogShow.value = true
}

const openEditDialog = (channel) => {
  selectedChannel.value = { ...channel }
  dialogShow.value = true
}

const saveChannel = async (formData) => {
  savingChannel.value = true
  const isEdit = Boolean(formData.id)
  const url = isEdit ? `/api/telegram/channels/${formData.id}` : '/api/telegram/channels'
  const method = isEdit ? 'put' : 'post'

  try {
    const { data } = await axios[method](url, formData)
    showToast(data.message || (isEdit ? 'Canal actualizado con éxito.' : 'Canal registrado con éxito.'), 'success')
    dialogShow.value = false
    await fetchChannels()
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Error al persistir el canal.'
    showToast(errorMsg, 'error')
  } finally {
    savingChannel.value = false
  }
}

const toggleChannel = async (channel) => {
  togglingId.value = channel.id
  const targetState = channel.is_active

  try {
    const { data } = await axios.patch(`/api/telegram/channels/${channel.id}/toggle`, {
      is_active: targetState,
    })
    showToast(data.message || `Canal "${channel.name}" ${targetState ? 'habilitado' : 'pausado'}.`, 'success')
  } catch (error) {
    channel.is_active = !targetState
    showToast('Error al cambiar el estado del canal.', 'error')
  } finally {
    togglingId.value = null
  }
}

const testChannel = async (channel) => {
  testingId.value = channel.id
  try {
    const { data } = await axios.post(`/api/telegram/channels/${channel.id}/test`)
    showToast(data.message || 'Mensaje de prueba enviado correctamente.', 'success')
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Error al despachar mensaje de prueba.'
    showToast(errorMsg, 'error')
  } finally {
    testingId.value = null
  }
}

const confirmDelete = async (channel) => {
  const result = await Swal.fire({
    title: '¿Eliminar canal?',
    text: `¿Estás seguro de que deseas eliminar el canal "${channel.name}"? Esta acción no se puede deshacer.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#FF4C51',
    cancelButtonColor: '#808390',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
  })

  if (!result.isConfirmed) return

  try {
    const { data } = await axios.delete(`/api/telegram/channels/${channel.id}`)
    showToast(data.message || 'Canal eliminado correctamente.', 'success')
    await fetchChannels()
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Error al eliminar el canal.'
    showToast(errorMsg, 'error')
  }
}

const getModuleBadgeColor = (moduleName) => {
  switch (moduleName) {
    case 'farmacia': return 'success'
    case 'restaurante': return 'warning'
    case 'cosmeticos': return 'purple'
    case 'alquileres': return 'info'
    default: return 'secondary'
  }
}

onMounted(() => {
  fetchChannels()
})
</script>

<template>
  <div>
    <VCard>
      <VCardItem class="pb-4">
        <template #prepend>
          <VAvatar color="primary" variant="tonal" rounded size="42">
            <VIcon icon="tabler-topology-ring-3" size="24" />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6 font-weight-bold">
          Gestión de Canales de Telegram
        </VCardTitle>
        <VCardSubtitle class="text-body-2">
          Asigna canales específicos para direccionar alertas automáticas por módulo del sistema.
        </VCardSubtitle>

        <template #append>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openCreateDialog"
          >
            Añadir Canal
          </VBtn>
        </template>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-0">
        <VProgressLinear
          v-if="loading"
          indeterminate
          color="primary"
          class="mb-4"
        />

        <VTable class="text-no-wrap">
          <thead>
            <tr>
              <th class="text-uppercase text-caption font-weight-bold">Estado</th>
              <th class="text-uppercase text-caption font-weight-bold">Nombre del Canal</th>
              <th class="text-uppercase text-caption font-weight-bold">Chat ID</th>
              <th class="text-uppercase text-caption font-weight-bold">Módulo</th>
              <th class="text-uppercase text-caption font-weight-bold">Descripción</th>
              <th class="text-uppercase text-caption font-weight-bold text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="channels.length === 0 && !loading">
              <td colspan="6" class="text-center py-8 text-medium-emphasis">
                <VIcon icon="tabler-topology-star-ring-3" size="40" class="mb-2 opacity-50" />
                <div class="text-body-2 font-weight-medium">No hay canales de Telegram registrados.</div>
                <div class="text-caption">Haz clic en "Añadir Canal" para configurar el primero.</div>
              </td>
            </tr>
            <tr v-for="ch in channels" :key="ch.id">
              <td style="width: 140px;">
                <VSwitch
                  v-model="ch.is_active"
                  color="success"
                  hide-details="auto"
                  density="comfortable"
                  :disabled="togglingId === ch.id"
                  @change="toggleChannel(ch)"
                >
                  <template #label>
                    <VChip
                      size="x-small"
                      :color="ch.is_active ? 'success' : 'secondary'"
                      variant="tonal"
                      class="ms-1 font-weight-medium"
                    >
                      {{ ch.is_active ? 'Activo' : 'Pausado' }}
                    </VChip>
                  </template>
                </VSwitch>
              </td>

              <td class="font-weight-bold text-body-2">
                {{ ch.name }}
              </td>

              <td>
                <VChip size="small" variant="tonal" color="default" class="font-weight-medium">
                  {{ ch.chat_id }}
                </VChip>
              </td>

              <td>
                <VChip
                  size="small"
                  variant="tonal"
                  :color="getModuleBadgeColor(ch.module)"
                  class="text-uppercase font-weight-bold"
                >
                  {{ ch.module }}
                </VChip>
              </td>

              <td>
                <span class="text-body-2 text-wrap" style="max-width: 280px; display: inline-block;">
                  {{ ch.description || 'Sin descripción' }}
                </span>
              </td>

              <td class="text-center">
                <VBtn
                  icon
                  variant="text"
                  color="info"
                  size="small"
                  :loading="testingId === ch.id"
                  @click="testChannel(ch)"
                >
                  <VIcon icon="tabler-send" size="18" />
                  <VTooltip activator="parent" location="top">
                    Enviar mensaje de prueba en vivo
                  </VTooltip>
                </VBtn>

                <VBtn
                  icon
                  variant="text"
                  color="default"
                  size="small"
                  @click="openEditDialog(ch)"
                >
                  <VIcon icon="tabler-pencil" size="18" />
                  <VTooltip activator="parent" location="top">
                    Editar datos del canal
                  </VTooltip>
                </VBtn>

                <VBtn
                  icon
                  variant="text"
                  color="error"
                  size="small"
                  @click="confirmDelete(ch)"
                >
                  <VIcon icon="tabler-trash" size="18" />
                  <VTooltip activator="parent" location="top">
                    Eliminar canal
                  </VTooltip>
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <!-- Modal de Creación / Edición -->
    <TelegramChannelDialog
      v-model="dialogShow"
      :channel-data="selectedChannel"
      :saving="savingChannel"
      @save="saveChannel"
    />

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="4000"
      location="top right"
      variant="flat"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>
