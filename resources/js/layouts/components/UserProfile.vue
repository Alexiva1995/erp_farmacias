<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const handleLogout = async () => {
  await authStore.logout()
}

// Obtener nombre y apellido del usuario
const userName = computed(() => {
  const user = authStore.user
  if (!user) return 'Usuario'
  
  if (user.employee?.name && user.employee?.last_name) {
    return `${user.employee.name} ${user.employee.last_name}`
  }
  if (user.employee?.name) {
    return user.employee.name
  }
  if (user.employee_name && user.employee_last_name) {
    return `${user.employee_name} ${user.employee_last_name}`
  }
  if (user.employee_name) {
    return user.employee_name
  }
  if (user.name) {
    return user.name
  }
  return user.username || 'Usuario'
})

const userRole = computed(() => (authStore.isAdmin ? 'Admin' : 'Usuario'))
const userAvatar = computed(() => authStore.user?.employee?.photo || authStore.user?.photo || '/src/assets/images/avatars/avatar-1.png')
</script>

<template>
  <div class="d-flex align-center gap-2 cursor-pointer select-none py-1 px-2 rounded-lg hover-bg">
    <VBadge
      dot
      location="bottom right"
      offset-x="3"
      offset-y="3"
      bordered
      color="success"
    >
      <VAvatar
        color="primary"
        variant="tonal"
        size="38"
      >
        <VImg :src="userAvatar" />
      </VAvatar>
    </VBadge>

    <div class="d-none d-sm-flex flex-column text-left">
      <span class="text-sm font-weight-bold text-high-emphasis leading-tight">{{ userName }}</span>
      <span class="text-xs text-disabled">{{ userRole }}</span>
    </div>

    <!-- SECTION Menu -->
    <VMenu
      activator="parent"
      width="230"
      location="bottom end"
      offset="14px"
    >
      <VList>
        <!-- 👉 User Avatar & Name -->
        <VListItem>
          <template #prepend>
            <VListItemAction start>
              <VBadge
                dot
                location="bottom right"
                offset-x="3"
                offset-y="3"
                color="success"
              >
                <VAvatar
                  color="primary"
                  variant="tonal"
                >
                  <VImg :src="userAvatar" />
                </VAvatar>
              </VBadge>
            </VListItemAction>
          </template>

          <VListItemTitle class="font-weight-semibold">
            {{ userName }}
          </VListItemTitle>
          <VListItemSubtitle>{{ userRole }}</VListItemSubtitle>
        </VListItem>

        <VDivider class="my-2" />

        <!-- 👉 Perfil -->
        <VListItem link>
          <template #prepend>
            <VIcon
              class="me-2"
              icon="tabler-user"
              size="22"
            />
          </template>

          <VListItemTitle>Perfil</VListItemTitle>
        </VListItem>

        <!-- Divider -->
        <VDivider class="my-2" />

        <!-- 👉 Cerrar Sesión -->
        <VListItem @click="handleLogout">
          <template #prepend>
            <VIcon
              class="me-2"
              icon="tabler-logout"
              size="22"
            />
          </template>
          <VListItemTitle>Cerrar Sesión</VListItemTitle>
        </VListItem>
      </VList>
    </VMenu>
    <!-- !SECTION -->
  </div>
</template>

<style scoped>
.hover-bg {
  transition: background-color 0.2s ease;
}
.hover-bg:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.05);
}
.leading-tight {
  line-height: 1.2 !important;
}
</style>
