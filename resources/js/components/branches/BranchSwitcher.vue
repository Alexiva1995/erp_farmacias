<script setup>
import { onMounted } from 'vue'
import { useBranchStore } from '@/stores/useBranchStore'
import { useAuthStore } from '@/stores/auth'

const branchStore = useBranchStore()
const authStore = useAuthStore()

// Solo mostrar selector si el usuario es Admin (role_id === 1) o corporate admin
const canSwitch = computed(() => {
  const role = authStore.user?.role_id
  const isCorporate = authStore.user?.is_corporate_admin
  return (role === 1 || isCorporate) && branchStore.branches.length > 1
})

onMounted(() => {
  branchStore.fetchBranches()
})
</script>

<template>
  <div v-if="canSwitch" class="d-flex align-center me-2">
    <v-menu location="bottom end" transition="scale-transition">
      <template #activator="{ props }">
        <v-btn
          v-bind="props"
          :color="branchStore.isOperatingOtherBranch ? 'warning' : 'primary'"
          variant="tonal"
          size="small"
          rounded="pill"
          class="text-none px-3 font-weight-medium"
        >
          <v-icon
            start
            icon="tabler-building-store"
            size="18"
            class="me-1"
          />
          <span class="d-none d-sm-inline">Sede: </span>
          <span class="text-truncate max-width-150">
            {{ branchStore.activeBranch?.branch_code || branchStore.activeBranch?.company_name || 'Seleccionar' }}
          </span>
          <v-icon end icon="tabler-chevron-down" size="14" />
        </v-btn>
      </template>

      <v-card min-width="260" class="elevation-4">
        <v-card-item class="py-2 px-3 border-b">
          <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold">
            Sucursales del Grupo
          </div>
        </v-card-item>

        <v-list density="compact" class="py-1">
          <v-list-item
            v-for="b in branchStore.branches"
            :key="b.id"
            :value="b.id"
            :active="branchStore.activeBranchId === b.id"
            color="primary"
            @click="branchStore.switchBranch(b.id)"
          >
            <template #prepend>
              <v-icon
                :icon="b.is_matriz ? 'tabler-building' : 'tabler-building-store'"
                size="18"
                :color="branchStore.activeBranchId === b.id ? 'primary' : 'default'"
              />
            </template>
            <v-list-item-title class="font-weight-medium">
              {{ b.company_name }}
            </v-list-item-title>
            <v-list-item-subtitle class="text-caption">
              Código: {{ b.branch_code }} {{ b.is_matriz ? '• Matriz' : '' }}
            </v-list-item-subtitle>
            <template #append>
              <v-icon
                v-if="branchStore.activeBranchId === b.id"
                icon="tabler-check"
                color="primary"
                size="16"
              />
            </template>
          </v-list-item>
        </v-list>

        <v-divider />

        <v-card-actions class="pa-2 bg-var-theme-background">
          <v-btn
            block
            size="small"
            variant="text"
            color="secondary"
            to="/configuration/branches"
          >
            <v-icon start icon="tabler-settings" size="16" />
            Gestionar Sucursales
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-menu>
  </div>
</template>

<style scoped>
.max-width-150 {
  max-width: 150px;
}
</style>
