import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useSupplierConnectionStore } from '../supplierConnection'

describe('SupplierConnection Pinia Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.useFakeTimers()
  })

  it('debe inicializarse con valores de conexion inactivos', () => {
    const store = useSupplierConnectionStore()
    expect(store.hasPendingConnectionJob).toBe(false)
    expect(store.connectionStartedAt).toBeNull()
  })

  it('debe inicializar conexion al invocar startConnection', () => {
    const store = useSupplierConnectionStore()
    const now = Date.now()

    store.startConnection()

    expect(store.hasPendingConnectionJob).toBe(true)
    expect(store.connectionStartedAt).toBeGreaterThanOrEqual(now)
  })

  it('debe limpiar adecuadamente al invocar resetConnection', () => {
    const store = useSupplierConnectionStore()
    store.startConnection()

    store.resetConnection()

    expect(store.hasPendingConnectionJob).toBe(false)
    expect(store.connectionStartedAt).toBeNull()
  })

  it('debe decidir si se detiene el polling de acuerdo con el timeout de 10 minutos', () => {
    const store = useSupplierConnectionStore()

    // Sin conexión iniciada debe retornar true (detenerse)
    expect(store.shouldStopPolling()).toBe(true)

    store.startConnection()
    
    // Al iniciar debe ser false (seguir polleando)
    expect(store.shouldStopPolling()).toBe(false)

    // Adelantar reloj por 5 minutos (sigue polleando)
    vi.advanceTimersByTime(5 * 60 * 1000)
    expect(store.shouldStopPolling()).toBe(false)

    // Adelantar otros 6 minutos (acumula 11 minutos, debe detenerse)
    vi.advanceTimersByTime(6 * 60 * 1000)
    expect(store.shouldStopPolling()).toBe(true)
  })
})
