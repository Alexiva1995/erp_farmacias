import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useBrandingStore } from '../useBrandingStore'
import axios from '@axios'

vi.mock('@axios', () => ({
  default: {
    get: vi.fn(),
  },
}))

describe('Branding Pinia Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('debe contener los valores de branding por defecto', () => {
    const store = useBrandingStore()
    expect(store.settings.app_name).toBe('ERP Farmacia')
    expect(store.settings.primary_color).toBe('#E20074')
    expect(store.isLoading).toBe(false)
  })

  it('debe convertir correctamente colores HEX a formato RGB compatible con Vuetify 3 CSS', () => {
    const store = useBrandingStore()
    const rgb = store.hexToRgb('#E20074')
    expect(rgb).toBe('226, 0, 116')
  })

  it('debe inyectar las propiedades CSS de colores en document.documentElement al hacer fetchSettings exitoso', async () => {
    const store = useBrandingStore()

    // Configurar respuesta de API simulada
    const mockSettings = {
      app_name: 'Farmacia Nueva',
      primary_color: '#00FF00', // Verde Puro (0, 255, 0)
      secondary_color: '#0000FF', // Azul Puro (0, 0, 255)
    }

    axios.get.mockResolvedValueOnce({
      data: {
        success: true,
        data: mockSettings,
      },
    })

    // Mock document.documentElement.style.setProperty
    const setPropertySpy = vi.spyOn(document.documentElement.style, 'setProperty')

    await store.fetchSettings()

    expect(axios.get).toHaveBeenCalledWith('/api/general-settings')
    expect(store.settings.app_name).toBe('Farmacia Nueva')
    expect(store.settings.primary_color).toBe('#00FF00')

    // Verificar inyección CSS de colores primary
    expect(setPropertySpy).toHaveBeenCalledWith('--v-global-theme-primary', '0, 255, 0')
    expect(setPropertySpy).toHaveBeenCalledWith('--v-theme-primary', '0, 255, 0')

    // Verificar inyección CSS de colores secondary
    expect(setPropertySpy).toHaveBeenCalledWith('--v-global-theme-secondary', '0, 0, 255')
    expect(setPropertySpy).toHaveBeenCalledWith('--v-theme-secondary', '0, 0, 255')
  })
})
