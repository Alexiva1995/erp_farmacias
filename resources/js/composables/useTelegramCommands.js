import { ref, computed, onMounted } from 'vue'
import axios from '@axios'
import Swal from 'sweetalert2'

/**
 * Composable robusto para la gestión y configuración de comandos de Telegram en el ERP.
 *
 * @param {string} moduleName - Identificador del módulo (ej. 'restaurante', 'farmacia', etc.)
 */
export function useTelegramCommands(moduleName) {
  const commands = ref([])
  const availableChannels = ref([])
  const loading = ref(false)
  const updatingId = ref(null)
  const testingId = ref(null)
  const search = ref('')

  // Control de diálogo de edición
  const editDialog = ref(false)
  const savingEdit = ref(false)
  const selectedCommand = ref(null)

  // Estado del Toast / Snackbar
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

  /**
   * Cargar comandos del módulo especificado.
   */
  const fetchCommands = async () => {
    loading.value = true
    try {
      const { data } = await axios.get(`/telegram/commands/${moduleName}`)
      commands.value = data.data || []
    } catch (error) {
      showToast('Error al cargar la lista de comandos de Telegram.', 'error')
    } finally {
      loading.value = false
    }
  }

  /**
   * Cargar canales activos registrados.
   */
  const fetchChannels = async () => {
    try {
      const { data } = await axios.get('/telegram/channels')
      availableChannels.value = data.data || []
    } catch (error) {
      console.error('Error al cargar la lista de canales:', error)
    }
  }

  /**
   * Alternar estado activo/inactivo con confirmación SweetAlert2 para acciones de impacto.
   */
  const toggleCommand = async (commandItem) => {
    const targetState = commandItem.is_active

    // Si se procede a desactivar el comando, solicitar confirmación
    if (!targetState) {
      const result = await Swal.fire({
        title: '¿Desactivar comando?',
        text: `El comando "${commandItem.command}" dejará de procesar solicitudes automáticas en Telegram.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF9F43',
        cancelButtonColor: '#7A0099',
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar',
        customClass: {
          confirmButton: 'v-btn v-btn--elevated bg-warning text-white me-2',
          cancelButton: 'v-btn v-btn--outlined text-secondary',
        },
        buttonsStyling: false,
      })

      if (!result.isConfirmed) {
        commandItem.is_active = true
        return
      }
    }

    updatingId.value = commandItem.id
    try {
      await axios.patch(`/telegram/commands/${commandItem.id}/toggle`, {
        is_active: targetState,
      })
      showToast(
        `Comando "${commandItem.command}" ${targetState ? 'activado' : 'desactivado'} con éxito.`,
        'success'
      )
    } catch (error) {
      commandItem.is_active = !targetState
      showToast('Error al actualizar el estado del comando.', 'error')
    } finally {
      updatingId.value = null
    }
  }

  /**
   * Reasignar canal destino a un comando específico.
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

      const { data } = await axios.put(`/telegram/commands/${commandItem.id}`, payload)
      commandItem.channel_id = newChannelId
      commandItem.channel = data.data?.channel || null

      const channelObj = availableChannels.value.find(c => c.id === newChannelId)
      showToast(`Canal asignado a: ${channelObj ? channelObj.name : 'General Principal'}`, 'success')
    } catch (error) {
      commandItem.channel_id = originalChannelId
      showToast('Error al asignar el canal destino.', 'error')
    } finally {
      updatingId.value = null
    }
  }

  /**
   * Enviar mensaje de prueba para verificar la entrega del comando en Telegram.
   */
  const testCommand = async (commandItem) => {
    testingId.value = commandItem.id
    try {
      const { data } = await axios.post(`/telegram/commands/${commandItem.id}/test`)
      showToast(data.message || `Prueba enviada para "${commandItem.command}".`, 'success')
    } catch (error) {
      const msg = error.response?.data?.message || 'Error al emitir el mensaje de prueba.'
      showToast(msg, 'error')
    } finally {
      testingId.value = null
    }
  }

  /**
   * Abrir diálogo de edición.
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
      const { data } = await axios.put(`/telegram/commands/${updatedData.id}`, updatedData)

      showToast('Comando actualizado correctamente.', 'success')
      editDialog.value = false

      const index = commands.value.findIndex(c => c.id === updatedData.id)
      if (index !== -1 && data.data) {
        commands.value[index] = data.data
      }
    } catch (error) {
      showToast('Error al guardar los cambios del comando.', 'error')
    } finally {
      savingEdit.value = false
    }
  }

  /**
   * Filtro reactivo de comandos.
   */
  const filteredCommands = computed(() => {
    if (!search.value) return commands.value
    const query = search.value.toLowerCase().trim()
    return commands.value.filter(cmd =>
      cmd.command?.toLowerCase().includes(query) ||
      cmd.alias?.toLowerCase().includes(query) ||
      (cmd.description && cmd.description.toLowerCase().includes(query))
    )
  })

  /**
   * Opciones formateadas de canales para los selectores.
   */
  const channelOptions = computed(() => [
    { title: 'General / Chat Principal', value: null },
    ...availableChannels.value.map(c => ({
      title: `${c.name} (${c.chat_id})`,
      value: c.id,
    })),
  ])

  onMounted(() => {
    fetchCommands()
    fetchChannels()
  })

  return {
    commands,
    availableChannels,
    loading,
    updatingId,
    testingId,
    search,
    editDialog,
    savingEdit,
    selectedCommand,
    snackbar,
    filteredCommands,
    channelOptions,
    fetchCommands,
    fetchChannels,
    toggleCommand,
    updateChannelAssignment,
    testCommand,
    openEditDialog,
    handleSaveCommand,
    showToast,
  }
}
