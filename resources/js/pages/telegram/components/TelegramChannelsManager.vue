<script setup>
import { ref, onMounted } from 'vue'
import TelegramChannelDialog from './TelegramChannelDialog.vue'

const channels = ref([])
const loading = ref(false)
const testingId = ref(null)
const togglingId = ref(null)
const savingChannel = ref(false)

const dialogShow = ref(false)
const selectedChannel = ref(null)

const deleteConfirmDialog = ref(false)
const channelToDelete = ref(null)
const deleting = ref(false)

const snackbar = ref({
  show: false,
  text: '',
  color: 'success',
})

const fetchChannels = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/telegram/channels', {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('accessToken') || ''}`,
      },
    })
    if (response.ok) {
      const res = await response.json()
      channels.value = res.data || []
    } else {
      showToast('Error al cargar la lista de canales', 'error')
    }
  } catch (error) {
    showToast('Error de comunicación con el servidor', 'error')
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
  const isEdit = !!formData.id
  const url = isEdit ? `/api/telegram/channels/${formData.id}` : '/api/telegram/channels'
  const method = isEdit ? 'PUT' : 'POST'

  try {
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('accessToken') || ''}`,
      },
      body: JSON.stringify(formData),
    })

    if (response.ok) {
      showToast(isEdit ? 'Canal actualizado con éxito' : 'Canal registrado con éxito', 'success')
      dialogShow.value = false
      fetchChannels()
    } else {
      const errorRes = await response.json()
      showToast(errorRes.message || 'Error al guardar el canal', 'error')
    }
  } catch (error) {
    showToast('Error de servidor al guardar canal', 'error')
  } finally {
    savingChannel.value = false
  }
}

const toggleChannel = async (channel) => {
  togglingId.value = channel.id
  const targetState = channel.is_active

  try {
    const response = await fetch(`/api/telegram/channels/${channel.id}/toggle`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('accessToken') || ''}`,
      },
      body: JSON.stringify({ is_active: targetState }),
    })

    if (response.ok) {
      showToast(`Canal "${channel.name}" ${targetState ? 'habilitado' : 'pausado'}.`, 'success')
    } else {
      channel.is_active = !targetState
      showToast('Error al cambiar estado del canal', 'error')
    }
  } catch (error) {
    channel.is_active = !targetState
    showToast('Error de red al actualizar estado', 'error')
  } finally {
    togglingId.value = null
  }
}

const testChannel = async (channel) => {
  testingId.value = channel.id
  try {
    const response = await fetch(`/api/telegram/channels/${channel.id}/test`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('accessToken') || ''}`,
      },
    })
    const res = await response.json()
    if (response.ok) {
      showToast(res.message || 'Mensaje de prueba enviado correctamente', 'success')
    } else {
      showToast(res.message || 'Error al enviar mensaje de prueba', 'error')
    }
  } catch (error) {
    showToast('Error al conectar con la API de Telegram', 'error')
  } finally {
    testingId.value = null
  }
}

const confirmDelete = (channel) => {
  channelToDelete.value = channel
  deleteConfirmDialog.value = true
}

const deleteChannel = async () => {
  if (!channelToDelete.value) return
  deleting.value = true

  try {
    const response = await fetch(`/api/telegram/channels/${channelToDelete.value.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('accessToken') || ''}`,
      },
    })

    if (response.ok) {
      showToast('Canal eliminado correctamente', 'success')
      deleteConfirmDialog.value = false
      fetchChannels()
    } else {
      showToast('Error al eliminar canal', 'error')
    }
  } catch (error) {
    showToast('Error de servidor al eliminar canal', 'error')
  } finally {
    deleting.value = false
    channelToDelete.value = null
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

const showToast = (text, color = 'success') => {
  snackbar.value = {
    show: true,
    text,
    color,
  }
}

onMounted(() => {
  fetchChannels()
})
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardItem>
        <template #prepend>
          <VAvatar color="primary" variant="tonal" rounded size="42">
            <VIcon icon="tabler-topology-ring-3" size="24" />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6 font-weight-bold">
          Gestión de Canales de Telegram
        </VCardTitle>
        <VCardSubtitle>
          Agrega múltiples canales y asignales nombres legibles para direccionar las alertas por módulo.
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

      <VCardText>
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
              <td colspan="6" class="text-center py-6 text-muted">
                No hay canales configurados. Haz clic en "Añadir Canal" para crear el primero.
              </td>
            </tr>
            <tr v-for="ch in channels" :key="ch.id">
              <td style="width: 130px;">
                <VSwitch
                  v-model="ch.is_active"
                  color="success"
                  hide-details
                  density="compact"
                  :disabled="togglingId === ch.id"
                  @change="toggleChannel(ch)"
                >
                  <template #label>
                    <VChip
                      size="x-small"
                      :color="ch.is_active ? 'success' : 'secondary'"
                      variant="tonal"
                      class="ms-1"
                    >
                      {{ ch.is_active ? 'Activo' : 'Pausado' }}
                    </VChip>
                  </template>
                </VSwitch>
              </td>

              <td class="font-weight-bold text-body-1">
                {{ ch.name }}
              </td>

              <td>
                <VChip size="small" variant="flat" color="default" class="font-weight-medium">
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
                <span class="text-body-2 text-wrap" style="max-width: 250px; display: inline-block;">
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
                    Enviar mensaje de prueba a este canal
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

    <!-- Diálogo de Confirmación de Eliminación -->
    <VDialog v-model="deleteConfirmDialog" max-width="450px">
      <VCard>
        <VCardTitle class="pt-6 px-6 text-h6 font-weight-bold">
          Confirmar Eliminación
        </VCardTitle>
        <VCardText class="px-6 py-2">
          ¿Estás seguro de que deseas eliminar el canal
          <strong>"{{ channelToDelete?.name }}"</strong>? Esta acción no se puede deshacer.
        </VCardText>
        <VCardActions class="px-6 pb-6 pt-4">
          <VSpacer />
          <VBtn variant="outlined" color="secondary" @click="deleteConfirmDialog = false">
            Cancelar
          </VBtn>
          <VBtn color="error" :loading="deleting" @click="deleteChannel">
            Sí, Eliminar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="4000"
      location="top right"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>
