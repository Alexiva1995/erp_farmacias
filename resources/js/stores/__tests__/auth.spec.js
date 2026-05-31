import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '../auth'
import axios from '@/plugins/axios'

// Mocks para CASL Ability y Axios
vi.mock('@/plugins/ability.js', () => ({
  buildAbilityForRules: vi.fn(() => ({
    update: vi.fn(),
  })),
}))

vi.mock('@casl/vue', () => ({
  useAbility: vi.fn(() => ({
    update: vi.fn(),
  })),
}))

vi.mock('@/plugins/axios', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

describe('Auth Pinia Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('debe computar correctamente los roles y estados de autenticación', () => {
    const store = useAuthStore()

    // Caso No Autenticado
    expect(store.isAuthenticated).toBe(false)
    expect(store.isAdmin).toBeFalsy()
    expect(store.isSupervisor).toBeFalsy()
    expect(store.isVendedor).toBeFalsy()

    // Caso Admin
    store.user = { id: 1, role_id: 1 }
    expect(store.isAuthenticated).toBe(true)
    expect(store.isAdmin).toBe(true)
    expect(store.isSupervisor).toBe(false)

    // Caso Supervisor
    store.user = { id: 2, role_id: 2 }
    expect(store.isSupervisor).toBe(true)
    expect(store.isAdmin).toBe(false)

    // Caso Vendedor
    store.user = { id: 3, role_id: 3 }
    expect(store.isVendedor).toBe(true)
    expect(store.isSupervisor).toBe(false)
  })

  it('debe manejar reintentos y setear el usuario al hacer fetchUser exitoso', async () => {
    const store = useAuthStore()

    axios.get.mockResolvedValueOnce({
      data: { id: 10, name: 'Alexi', role_id: 1 },
    })

    await store.fetchUser()

    expect(axios.get).toHaveBeenCalledWith('/user')
    expect(store.user).toEqual({ id: 10, name: 'Alexi', role_id: 1 })
    expect(store.isLoaded).toBe(true)
  })

  it('debe setear user a null en caso de recibir error 401 en fetchUser', async () => {
    const store = useAuthStore()

    axios.get.mockRejectedValueOnce({
      response: { status: 401 },
    })

    await store.fetchUser()

    expect(store.user).toBeNull()
    expect(store.isLoaded).toBe(true)
  })

  it('debe limpiar el usuario y hacer redirección al hacer logout', async () => {
    // Mockear window.location
    const originalLocation = window.location
    delete window.location
    window.location = { href: '' }

    const store = useAuthStore()
    store.user = { id: 1, role_id: 1 }

    axios.post.mockResolvedValueOnce({})

    await store.logout()

    expect(axios.post).toHaveBeenCalledWith('/logout')
    expect(store.user).toBeNull()
    expect(window.location.href).toBe('/login')

    window.location = originalLocation
  })
})
