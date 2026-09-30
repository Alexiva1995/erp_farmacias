import { ref, computed } from 'vue'
import axios from '@axios'

/**
 * Composable para gestionar la carga, activación y asignación de canales de comandos de Telegram.
 *
 * @param {string} moduleName - Nombre del módulo de ERP (ej. 'farmacia', 'alquileres', etc.)
 */
export function useTelegramCommands(moduleName) {
  const commands = ref([])
  const availableChannels = ref([])
  const loading = ref(false)
  const updatingId = ref(null)
  const search = ref('')

  const snackbar = ref({
    show: false,
    text: '',
    color: 'success',
  })

  const showToast = (text, color = 'success') => {
    snackbar.value = {
      show: true,
      text,
      color,
    }
  }

  const fetchCommands = async () => {
    loading.value = true
    try {
      const { data } = await axios.get(`/api/telegram/commands/${moduleName}`)
      commands.value = data.data || []
    } catch (error) {
      showToast('Error al cargar la lista de comandos de Telegram.', 'error')
    } finally {
      loading.value = false
    }
  }

  const fetchChannels = async () => {
    try {
      const { data } = await axios.get('/api/telegram/channels')
      availableChannels.value = data.data || []
    } catch (error) {
      console.error('Error al cargar lista de canales:', error)
    }
  }

  const toggleCommand = async (commandItem) => {
    updatingId.value = commandItem.id
    const targetState = commandItem.is_active

    try {
      await axios.patch(`/api/telegram/commands/${commandItem.id}/toggle`, {
        is_active: targetState,
      })
      showToast(
        `Comando "${commandItem.command}" ${targetState ? 'activado' : 'desactivado'} con éxito.`,
        'success'
      )
    } catch (error) {
      commandItem.is_active = !targetState
      showToast('Error al cambiar el estado del comando.', 'error')
    } finally {
      updatingId.value = null
    }
  }

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

      const { data } = await axios.put(`/api/telegram/commands/${commandItem.id}`, payload)
      commandItem.channel_id = newChannelId
      commandItem.channel = data.data?.channel || null

      const channelObj = availableChannels.value.find(c => c.id === newChannelId)
      showToast(`Canal asignado: ${channelObj ? channelObj.name : 'General Principal'}`, 'success')
    } catch (error) {
      commandItem.channel_id = originalChannelId
      showToast('Error al asignar el canal destino.', 'error')
    } finally {
      updatingId.value = null
    }
  }

  const filteredCommands = computed(() => {
    if (!search.value) return commands.value
    const query = search.value.toLowerCase().trim()
    return commands.value.filter(cmd =>
      cmd.command?.toLowerCase().includes(query) ||
      cmd.alias?.toLowerCase().includes(query) ||
      (cmd.description && cmd.description.toLowerCase().includes(query))
    )
  })

  const channelOptions = computed(() => [
    { title: 'General / Chat Principal', value: null },
    ...availableChannels.value.map(c => ({
      title: `${c.name} (${c.chat_id})`,
      value: c.id,
    })),
  ])

  return {
    commands,
    availableChannels,
    loading,
    updatingId,
    search,
    snackbar,
    filteredCommands,
    channelOptions,
    fetchCommands,
    fetchChannels,
    toggleCommand,
    updateChannelAssignment,
    showToast,
  }
}
