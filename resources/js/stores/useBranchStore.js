import { defineStore } from 'pinia'
import axios from '@axios'
import { ref, computed } from 'vue'

export const useBranchStore = defineStore('branch', () => {
  const branches = ref([])
  const activeBranchId = ref(sessionStorage.getItem('active_branch_id') || '')
  const isLoading = ref(false)
  const canCreateBranches = ref(false)
  const currentBranchInfo = ref(null)

  const activeBranch = computed(() => {
    if (!branches.value.length) return currentBranchInfo.value
    return branches.value.find(b => b.id === activeBranchId.value) || currentBranchInfo.value || branches.value[0]
  })

  const isOperatingOtherBranch = computed(() => {
    if (!currentBranchInfo.value || !activeBranchId.value) return false
    return activeBranchId.value !== currentBranchInfo.value.id
  })

  const fetchBranches = async () => {
    isLoading.value = true
    try {
      const response = await axios.get('/branches')
      branches.value = response.data?.data || []
      
      // Si no hay sucursal seleccionada, predeterminar a la sucursal del dominio actual
      if (!activeBranchId.value && branches.value.length > 0) {
        const defaultBranch = branches.value.find(b => b.is_matriz) || branches.value[0]
        if (defaultBranch) {
          activeBranchId.value = defaultBranch.id
          sessionStorage.setItem('active_branch_id', defaultBranch.id)
        }
      }
    } catch (e) {
      console.warn('[useBranchStore] Error al cargar sucursales:', e)
    } finally {
      isLoading.value = false
    }
  }

  const switchBranch = (branchId) => {
    if (activeBranchId.value === branchId) return
    activeBranchId.value = branchId
    sessionStorage.setItem('active_branch_id', branchId)
    // Recargar la ventana para re-inicializar el estado y consultas en la sede seleccionada
    window.location.reload()
  }

  const resetToLocalBranch = () => {
    if (currentBranchInfo.value?.id) {
      switchBranch(currentBranchInfo.value.id)
    } else {
      sessionStorage.removeItem('active_branch_id')
      window.location.reload()
    }
  }

  return {
    branches,
    activeBranchId,
    activeBranch,
    isOperatingOtherBranch,
    isLoading,
    canCreateBranches,
    currentBranchInfo,
    fetchBranches,
    switchBranch,
    resetToLocalBranch,
  }
})
