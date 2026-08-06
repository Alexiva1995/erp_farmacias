<script setup>
import { ref, onMounted, computed } from 'vue'
import { $api } from '@/utils/api'
import TelegramCommandStatsCards from './TelegramCommandStatsCards.vue'
import TelegramCommandEditDialog from './TelegramCommandEditDialog.vue'

const props = defineProps({
  moduleName: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: 'Gestiona la activación, deshabilitación y canal de destino para cada mensaje y comando.',
  },
  icon: {
    type: String,
    default: 'tabler-message-dots',
  },
})

// Reactividad de Estado
const commands = ref([])
const availableChannels = ref([])
const loading = ref(false)
const search = ref('')
const updatingId = ref(null)

// Toast / Feedback State
const snackbar = ref({
  show: false,
  text: '',
  color: 'success',
})

// Dialog de edición
const editDialog = ref(false)
const savingEdit = ref(false)
const selectedCommand = ref(null)

/**
 * Cargar comandos del módulo especificado desde la API.
 */
const fetchCommands = async () => {
  loading.value = true
  try {
    const response = await $api(`/telegram/commands/${props.moduleName}`)
    commands.value = response.data || []
  } catch (error) {
    showToast('Error al cargar la lista de comandos de Telegram.', 'error')
  } finally {
    loading.value = false
  }
}

/**
 * Cargar canales registrados en Telegram.
 */
const fetchChannels = async () => {
  try {
    const response = await $api('/telegram/channels')
    availableChannels.value = response.data || []
  } catch (error) {
    console.error('Error al cargar lista de canales:', error)
  }
}

/**
 * Alternar el estado activo/inactivo de un comando.
 */
const toggleCommand = async (commandItem) => {
  updatingId.value = commandItem.id
  const targetState = commandItem.is_active

  try {
    await $api(`/telegram/commands/${commandItem.id}/toggle`, {
      method: 'PATCH',
      body: { is_active: targetState },
    })

    showToast(
      `Comando "${commandItem.command}" ${targetState ? 'activado' : 'desactivado'} con éxito.`,
      'success'
    )
  } catch (error) {
    // Revertir cambio en caso de error de red o servidor
    commandItem.is_active = !targetState
    showToast('Error al cambiar el estado del comando.', 'error')
  } finally {
    updatingId.value = null
  }
}

/**
 * Actualizar canal asignado a un comando de forma reactiva sin re-renderizado masivo.
 */
const updateChannelAssignment = async (commandItem, newChannelId) => {
  updatingId.value = commandItem.id
  const originalChannelId = commandItem.channel_id

  try {
    const payload = {
      command: commandItem.command,
      alias: commandItem.alias,
      description: commandItem.description,
      channel_id: newChannelId,
      is_active: commandItem.is_active,
      payload_template: commandItem.payload_template,
    }

    const response = await $api(`/telegram/commands/${commandItem.id}`, {
      method: 'PUT',
      body: payload,
    })

    // Actualizar localmente la relación canal
    commandItem.channel_id = newChannelId
    commandItem.channel = response.data?.channel || null

    const channelObj = availableChannels.value.find(c => c.id === newChannelId)
    showToast(`Canal de "${commandItem.command}" asignado a: ${channelObj ? channelObj.name : 'General Principal'}`, 'success')
  } catch (error) {
    commandItem.channel_id = originalChannelId
    showToast('Error al asignar el canal destino.', 'error')
  } finally {
    updatingId.value = null
  }
}

/**
 * Abrir modal de edición.
 */
const openEditDialog = (commandItem) => {
  selectedCommand.value = { ...commandItem }
  editDialog.value = true
}

/**
 * Guardar cambios del comando desde el diálogo.
 */
const handleSaveCommand = async (updatedData) => {
  savingEdit.value = true
  try {
    const response = await $api(`/telegram/commands/${updatedData.id}`, {
      method: 'PUT',
      body: updatedData,
    })

    showToast('Comando actualizado correctamente.', 'success')
    editDialog.value = false

    // Actualizar el elemento en la lista local de forma inmutable
    const index = commands.value.findIndex(c => c.id === updatedData.id)
    if (index !== -1 && response.data) {
      commands.value[index] = response.data
    }
  } catch (error) {
    showToast('Error al guardar cambios del comando.', 'error')
  } finally {
    savingEdit.value = false
  }
}

const showToast = (text, color = 'success') => {
  snackbar.value = {
    show: true,
    text,
    color,
  }
}

// Filtro Reactivo Computado
const filteredCommands = computed(() => {
  if (!search.value) return commands.value
  const query = search.value.toLowerCase().trim()
  return commands.value.filter(cmd =>
    cmd.command?.toLowerCase().includes(query) ||
    cmd.alias?.toLowerCase().includes(query) ||
    (cmd.description && cmd.description.toLowerCase().includes(query))
  )
})

// Opciones de Canales
const channelOptions = computed(() => {
  return [
    { title: 'General / Chat Principal', value: null },
    ...availableChannels.value.map(c => ({
      title: `${c.name} (${c.chat_id})`,
      value: c.id,
    })),
  ]
})

onMounted(() => {
  fetchCommands()
  fetchChannels()
})
</script>

<template>
  <div>
    <!-- Encabezado Principal del Módulo -->
    <VCard class="mb-6" border flat rounded="lg">
      <VCardItem>
        <template #prepend>
          <VAvatar color="primary" variant="tonal" rounded size="48" class="me-2">
            <VIcon :icon="props.icon" size="28" />
          </VAvatar>
        </template>

        <VCardTitle class="text-h5 font-weight-bold">
          {{ props.title }}
        </VCardTitle>

        <VCardSubtitle class="text-body-2">
          {{ props.subtitle }}
        </VCardSubtitle>

        <template #append>
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-refresh"
            :loading="loading"
            @click="fetchCommands"
          >
            Actualizar
          </VBtn>
        </template>
      </VCardItem>
    </VCard>

    <!-- Tarjetas de Métricas Resumen -->
    <TelegramCommandStatsCards :commands="commands" />

    <!-- Tarjeta Principal de Tabla y Buscador -->
    <VCard border flat rounded="lg">
      <VCardText class="pb-3 pt-5">
        <VRow align="center">
          <VCol cols="12" sm="8" md="6">
            <VTextField
              v-model="search"
              placeholder="Buscar por comando, alias o descripción..."
              prepend-inner-icon="tabler-search"
              density="compact"
              variant="outlined"
              clearable
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Estado de Carga con Esqueletos -->
      <VCardText v-if="loading" class="pt-0">
        <VSkeletonLoader
          type="table-row-divider@4"
          class="my-2"
        />
      </VCardText>

      <!-- Vista de Escritorio: Tabla Elegante -->
      <VTable v-else-if="filteredCommands.length > 0" class="d-none d-md-table text-no-wrap">
        <thead>
          <tr>
            <th class="text-uppercase text-caption font-weight-bold" style="width: 140px;">Estado</th>
            <th class="text-uppercase text-caption font-weight-bold">Comando</th>
            <th class="text-uppercase text-caption font-weight-bold">Nombre / Alias</th>
            <th class="text-uppercase text-caption font-weight-bold" style="width: 260px;">Canal Destino Asignado</th>
            <th class="text-uppercase text-caption font-weight-bold">Descripción</th>
            <th class="text-uppercase text-caption font-weight-bold text-center" style="width: 100px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cmd in filteredCommands" :key="cmd.id">
            <td>
              <VSwitch
                v-model="cmd.is_active"
                color="success"
                hide-details
                density="compact"
                :disabled="updatingId === cmd.id"
                @change="toggleCommand(cmd)"
              >
                <template #label>
                  <VChip
                    size="x-small"
                    :color="cmd.is_active ? 'success' : 'secondary'"
                    variant="tonal"
                    class="ms-1 font-weight-medium"
                  >
                    {{ cmd.is_active ? 'Activo' : 'Inactivo' }}
                  </VChip>
                </template>
              </VSwitch>
            </td>
            <td>
              <VChip color="primary" size="small" variant="flat" class="font-weight-bold">
                {{ cmd.command }}
              </VChip>
            </td>
            <td class="font-weight-bold text-high-emphasis">
              {{ cmd.alias }}
            </td>
            <td>
              <VSelect
                :model-value="cmd.channel_id"
                :items="channelOptions"
                item-title="title"
                item-value="value"
                density="compact"
                variant="outlined"
                hide-details
                style="width: 100%; min-width: 220px;"
                :disabled="updatingId === cmd.id"
                @update:model-value="(val) => updateChannelAssignment(cmd, val)"
              >
                <template #selection="{ item }">
                  <VChip size="small" variant="tonal" color="info" class="text-truncate">
                    <VIcon icon="tabler-brand-telegram" size="14" class="me-1" />
                    {{ item.title }}
                  </VChip>
                </template>
              </VSelect>
            </td>
            <td>
              <span class="text-body-2 text-medium-emphasis text-wrap" style="max-width: 320px; display: inline-block;">
                {{ cmd.description || 'Sin descripción asignada' }}
              </span>
            </td>
            <td class="text-center">
              <VBtn
                icon
                variant="text"
                color="default"
                size="small"
                @click="openEditDialog(cmd)"
              >
                <VIcon icon="tabler-pencil" size="18" />
                <VTooltip activator="parent" location="top">
                  Editar detalles del comando
                </VTooltip>
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>

      <!-- Vista Móvil Adaptativa: Cards -->
      <div v-else-if="filteredCommands.length > 0" class="d-md-none px-4 pb-4">
        <VCard
          v-for="cmd in filteredCommands"
          :key="cmd.id"
          class="mb-3"
          border
          flat
          rounded="lg"
        >
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-2">
              <VChip color="primary" size="small" variant="flat" class="font-weight-bold">
                {{ cmd.command }}
              </VChip>

              <VSwitch
                v-model="cmd.is_active"
                color="success"
                hide-details
                density="compact"
                :disabled="updatingId === cmd.id"
                @change="toggleCommand(cmd)"
              />
            </div>

            <div class="text-subtitle-1 font-weight-bold mb-1">
              {{ cmd.alias }}
            </div>

            <div class="text-body-2 text-medium-emphasis mb-3">
              {{ cmd.description || 'Sin descripción asignada' }}
            </div>

            <VDivider class="mb-3" />

            <div class="d-flex align-center justify-space-between">
              <div style="flex-grow: 1;" class="me-2">
                <VSelect
                  :model-value="cmd.channel_id"
                  :items="channelOptions"
                  item-title="title"
                  item-value="value"
                  density="compact"
                  variant="outlined"
                  hide-details
                  :disabled="updatingId === cmd.id"
                  @update:model-value="(val) => updateChannelAssignment(cmd, val)"
                />
              </div>

              <VBtn
                icon
                variant="tonal"
                color="primary"
                size="small"
                @click="openEditDialog(cmd)"
              >
                <VIcon icon="tabler-pencil" size="18" />
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </div>

      <!-- Estado Vacío Informativo -->
      <VCardText v-else class="text-center py-10">
        <VAvatar color="secondary" variant="tonal" size="64" class="mb-3">
          <VIcon icon="tabler-search-off" size="36" />
        </VAvatar>
        <div class="text-h6 font-weight-bold mb-1">
          No se encontraron comandos
        </div>
        <div class="text-body-2 text-medium-emphasis mb-4">
          {{ search ? `No hay resultados para la búsqueda "${search}".` : 'No existen comandos configurados para este módulo.' }}
        </div>
        <VBtn
          v-if="search"
          color="primary"
          variant="tonal"
          size="small"
          prepend-icon="tabler-x"
          @click="search = ''"
        >
          Limpiar filtro de búsqueda
        </VBtn>
      </VCardText>
    </VCard>

    <!-- Modal Desacoplado para Edición -->
    <TelegramCommandEditDialog
      v-model="editDialog"
      :command-data="selectedCommand"
      :channel-options="channelOptions"
      :saving="savingEdit"
      @save="handleSaveCommand"
    />

    <!-- Toast de Notificación Vuetify -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="4000"
      location="top right"
      rounded="lg"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>
