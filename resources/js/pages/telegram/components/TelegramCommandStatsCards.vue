<script setup>
import { computed } from 'vue'

const props = defineProps({
  commands: {
    type: Array,
    default: () => [],
  },
})

const totalCommands = computed(() => props.commands.length)
const activeCommands = computed(() => props.commands.filter(c => c.is_active).length)
const inactiveCommands = computed(() => props.commands.filter(c => !c.is_active).length)
const assignedChannelsCount = computed(() => {
  const uniqueChannels = new Set(
    props.commands.map(c => c.channel_id).filter(id => id !== null && id !== undefined)
  )
  return uniqueChannels.size
})
</script>

<template>
  <VRow class="mb-4">
    <!-- Total Comandos -->
    <VCol cols="12" sm="6" md="3">
      <VCard class="h-100" border flat>
        <VCardText class="d-flex align-center justify-space-between">
          <div>
            <div class="text-caption text-uppercase font-weight-medium text-medium-emphasis mb-1">
              Total Comandos
            </div>
            <div class="text-h4 font-weight-black">
              {{ totalCommands }}
            </div>
          </div>
          <VAvatar color="primary" variant="tonal" size="44" rounded>
            <VIcon icon="tabler-terminal-2" size="26" />
          </VAvatar>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Comandos Activos -->
    <VCol cols="12" sm="6" md="3">
      <VCard class="h-100" border flat>
        <VCardText class="d-flex align-center justify-space-between">
          <div>
            <div class="text-caption text-uppercase font-weight-medium text-medium-emphasis mb-1">
              Activos
            </div>
            <div class="text-h4 font-weight-black text-success">
              {{ activeCommands }}
            </div>
          </div>
          <VAvatar color="success" variant="tonal" size="44" rounded>
            <VIcon icon="tabler-circle-check" size="26" />
          </VAvatar>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Comandos Inactivos -->
    <VCol cols="12" sm="6" md="3">
      <VCard class="h-100" border flat>
        <VCardText class="d-flex align-center justify-space-between">
          <div>
            <div class="text-caption text-uppercase font-weight-medium text-medium-emphasis mb-1">
              Inactivos
            </div>
            <div class="text-h4 font-weight-black text-warning">
              {{ inactiveCommands }}
            </div>
          </div>
          <VAvatar color="warning" variant="tonal" size="44" rounded>
            <VIcon icon="tabler-circle-x" size="26" />
          </VAvatar>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Canales Vinculados -->
    <VCol cols="12" sm="6" md="3">
      <VCard class="h-100" border flat>
        <VCardText class="d-flex align-center justify-space-between">
          <div>
            <div class="text-caption text-uppercase font-weight-medium text-medium-emphasis mb-1">
              Canales Únicos
            </div>
            <div class="text-h4 font-weight-black text-info">
              {{ assignedChannelsCount }}
            </div>
          </div>
          <VAvatar color="info" variant="tonal" size="44" rounded>
            <VIcon icon="tabler-brand-telegram" size="26" />
          </VAvatar>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
