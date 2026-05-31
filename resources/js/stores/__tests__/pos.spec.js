import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { usePosStore } from '../pos'
import axios from '@/plugins/axios'

// Mockear Axios para simular peticiones HTTP y controlar las respuestas
vi.mock('@/plugins/axios', () => {
  return {
    default: {
      get: vi.fn(),
      post: vi.fn(),
    },
  }
})

describe('POS Pinia Store - Unit Tests', () => {
  beforeEach(() => {
    // Inicializar un entorno limpio de Pinia antes de cada test
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('debe enviar currency_at_order al agregar un producto (Bug 1)', async () => {
    const store = usePosStore()

    // Configurar estado inicial simulando una orden activa pendiente en USD
    store.activeOrder = { id: 10, currency: 'USD' }
    store.currency = 'USD'

    // Mockear la respuesta HTTP exitosa
    axios.post.mockResolvedValueOnce({
      data: {
        success: true,
        data: { id: 99, quantity: 2 },
      },
    })

    // Mockear loadPendingOrder para evitar efectos secundarios
    axios.get.mockResolvedValueOnce({
      data: {
        success: true,
        data: {
          order: {
            id: 10,
            currency: 'USD',
            details: [{ product_id: 1, quantity: 2 }],
          },
        },
      },
    })

    await store.addProductToOrder(1, 2, 5.0, 5.0)

    // Validar que la petición POST de Axios incluya exactamente currency_at_order
    expect(axios.post).toHaveBeenCalledWith('/tpv/orders/10/items', {
      product_id: 1,
      quantity: 2,
      price_at_product: 5.0,
      price_usd_unit: 5.0,
      currency_at_order: 'USD',
      pack_id: null,
    })
  })

  it('debe desenvolver correctamente las respuestas envueltas por ApiResponse (Bug 2)', async () => {
    const store = usePosStore()

    // Simular la respuesta de searchClient envuelta por ApiResponse { success: true, data: { found: true, client: {...} } }
    axios.get.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'successfully',
        data: {
          found: true,
          client: {
            id: 5,
            name: 'Juan Perez',
            identification: '12345678',
          },
        },
      },
    })

    const client = await store.searchClient('12345678')

    // Verificar que el store desenvuelva los datos anidados de forma exitosa
    expect(client).not.toBeNull()
    expect(client.name).toBe('Juan Perez')
    expect(store.activeClient).toEqual({
      id: 5,
      name: 'Juan Perez',
      identification: '12345678',
    })
  })

  it('debe limpiar adecuadamente el estado al completarse la venta', async () => {
    const store = usePosStore()

    // Configurar un estado con orden activa antes del cobro
    store.activeOrder = { id: 10 }
    store.cartItems = [{ product_id: 1, quantity: 2 }]
    store.activeClient = { id: 5, name: 'Juan Perez' }

    axios.post.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Compra finalizada exitosamente.',
      },
    })

    await store.completeSale({
      payments: [{ method: 'cash_usd', amount: 10.0 }],
    })

    // Validar reseteo correcto de los estados reactivos
    expect(store.activeOrder).toBeNull()
    expect(store.cartItems).toHaveLength(0)
    expect(store.activeClient).toBeNull()
  })
})
