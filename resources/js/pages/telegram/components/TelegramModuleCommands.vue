<script setup>
import { ref, computed } from 'vue'
import { useTelegramCommands } from '@/composables/useTelegramCommands'
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

const {
  commands,
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
  toggleCommand,
  updateChannelAssignment,
  testCommand,
  openEditDialog,
  handleSaveCommand,
} = useTelegramCommands(props.moduleName)

// Filtro rápido por estado
const activeFilter = ref('all')

const finalFilteredCommands = computed(() => {
  let list = filteredCommands.value
  if (activeFilter.value === 'active') {
    list = list.filter(cmd => cmd.is_active)
  } else if (activeFilter.value === 'inactive') {
    list = list.filter(cmd => !cmd.is_active)
  }
  return list
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

    <!-- Tarjeta Principal de Tabla y Filtros -->
    <VCard border flat rounded="lg">
      <VCardText class="pb-3 pt-5">
        <VRow align="center">
          <VCol cols="12" sm="7" md="6">
            <VTextField
              v-model="search"
              placeholder="Buscar por comando, alias o descripción..."
              prepend-inner-icon="tabler-search"
              density="comfortable"
              variant="outlined"
              clearable
              hide-details="auto"
            />
          </VCol>

          <VCol cols="12" sm="5" md="6" class="d-flex justify-sm-end align-center gap-2 flex-wrap">
            <VBtnToggle
              v-model="activeFilter"
              mandatory
              density="comfortable"
              variant="outlined"
              color="primary"
            >
              <VBtn value="all" size="small">
                Todos
              </VBtn>
              <VBtn value="active" size="small">
                Activos
              </VBtn>
              <VBtn value="inactive" size="small">
                Inactivos
              </VBtn>
            </VBtnToggle>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Estado de Carga con Esqueletos -->
      <VCardText v-if="loading" class="pt-0">
        <VSkeletonLoader
          type="table-tbody"
          class="my-2"
        />
      </VCardText>

      <!-- Vista de Comandos: Tabla con soporte Responsive -->
      <VTable v-else-if="finalFilteredCommands.length > 0" class="text-no-wrap">
        <thead>
          <tr>
            <th class="text-uppercase text-caption font-weight-bold" style="width: 140px;">Estado</th>
            <th class="text-uppercase text-caption font-weight-bold">Comando</th>
            <th class="text-uppercase text-caption font-weight-bold">Nombre / Alias</th>
            <th class="text-uppercase text-caption font-weight-bold" style="width: 280px;">Canal Destino</th>
            <th class="text-uppercase text-caption font-weight-bold">Descripción</th>
            <th class="text-uppercase text-caption font-weight-bold text-center" style="width: 130px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cmd in finalFilteredCommands" :key="cmd.id">
            <td>
              <VSwitch
                v-model="cmd.is_active"
                color="success"
                hide-details="auto"
                density="comfortable"
                :disabled="updatingId === cmd.id || !$can('edit', 'TelegramConfig')"
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
              <VChip color="primary" size="small" variant="tonal" class="font-weight-bold">
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
                density="comfortable"
                variant="outlined"
                hide-details="auto"
                style="min-width: 240px;"
                :disabled="updatingId === cmd.id || !$can('edit', 'TelegramConfig')"
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
              <div class="d-flex align-center justify-center gap-1">
                <!-- Botón de Prueba Rápida -->
                <VBtn
                  icon
                  variant="text"
                  color="info"
                  size="small"
                  :loading="testingId === cmd.id"
                  :disabled="!$can('read', 'TelegramConfig')"
                  @click="testCommand(cmd)"
                >
                  <VIcon icon="tabler-send" size="18" />
                  <VTooltip activator="parent" location="top">
                    Probar envío de comando a Telegram
                  </VTooltip>
                </VBtn>

                <!-- Botón de Edición -->
                <VBtn
                  icon
                  variant="text"
                  color="default"
                  size="small"
                  :disabled="!$can('edit', 'TelegramConfig')"
                  @click="openEditDialog(cmd)"
                >
                  <VIcon icon="tabler-pencil" size="18" />
                  <VTooltip activator="parent" location="top">
                    Editar parámetros del comando
                  </VTooltip>
                </VBtn>
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>

      <!-- Estado Vacío Informativo -->
      <VCardText v-else class="text-center py-10">
        <VAvatar color="secondary" variant="tonal" size="64" class="mb-3">
          <VIcon icon="tabler-search-off" size="36" />
        </VAvatar>
        <div class="text-h6 font-weight-bold mb-1">
          No se encontraron comandos
        </div>
        <div class="text-body-2 text-medium-emphasis mb-4">
          {{ search ? `No hay resultados para la búsqueda "${search}".` : 'No existen comandos configurados para este filtro.' }}
        </div>
        <VBtn
          v-if="search || activeFilter !== 'all'"
          color="primary"
          variant="tonal"
          size="small"
          prepend-icon="tabler-x"
          @click="search = ''; activeFilter = 'all'"
        >
          Limpiar filtros
        </VBtn>
      </VCardText>
    </VCard>

    <!-- Modal Desacoplado para Edición -->
    <TelegramCommandEditDialog
      v-model="editDialog"
      :command-data="selectedCommand"
      :channel-options="channelOptions"
      :module-name="props.moduleName"
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
