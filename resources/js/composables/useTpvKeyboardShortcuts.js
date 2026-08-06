import { onMounted, onUnmounted } from 'vue'

/**
 * Atajos de teclado globales para el TPV de cajera (Ergonomía de Caja Rápida).
 *
 * F12       → Abrir modal de cobro / Procesar Pago
 * F2        → Foco directo en el buscador de productos
 * F4 o F8   → Rotar entre monedas activas (USD -> BS -> COP -> USD)
 * Alt + C   → Cancelar/Abandonar orden actual
 * Esc       → Cerrar cualquier modal abierto
 */
export function useTpvKeyboardShortcuts({
  openBuysModal,
  closeBuysModal,
  showBuysModal,
  hasOpenOrder,
  showRegisterClientModal,
  cancelarOrder,
  selectedDisplayCurrency,
  handleCurrencyChanged,
  isCurrencyChanging,
  onSelectPaymentMethod,
  onCompletePurchase,
}) {
  const currencies = ['USD', 'BS', 'COP']

  const toggleCurrency = () => {
    if (!selectedDisplayCurrency || isCurrencyChanging?.value) return
    const current = selectedDisplayCurrency.value || 'COP'
    const currentIndex = currencies.indexOf(current)
    const nextIndex = (currentIndex + 1) % currencies.length
    const nextCurrency = currencies[nextIndex]

    if (typeof handleCurrencyChanged === 'function') {
      handleCurrencyChanged(nextCurrency, isCurrencyChanging)
    } else {
      selectedDisplayCurrency.value = nextCurrency
    }
  }

  const handleShortcut = (event) => {
    // F12 -> Cobro inmediato
    if (event.key === 'F12') {
      event.preventDefault()
      if (hasOpenOrder.value && !showBuysModal.value) {
        openBuysModal()
      }
      return
    }

    // F8 o F4 -> Rotar cambio de moneda (USD <-> BS <-> COP)
    if (event.key === 'F8' || event.key === 'F4') {
      event.preventDefault()
      toggleCurrency()
      return
    }

    // F2 -> Foco inmediato en buscador principal de productos
    if (event.key === 'F2') {
      event.preventDefault()
      const productSearchInput = document.querySelector('.order-filters-search-input input') || document.querySelector('input[placeholder*="Buscar"]')
      if (productSearchInput) {
        productSearchInput.focus()
        productSearchInput.select()
      }
      return
    }

    // Alt + C -> Cancelar orden rápida
    if (event.altKey && (event.key === 'c' || event.key === 'C')) {
      event.preventDefault()
      if (hasOpenOrder.value && typeof cancelarOrder === 'function') {
        cancelarOrder()
      }
      return
    }

    // Escape -> Cerrar modales aunque se esté en un input
    if (event.key === 'Escape') {
      if (showBuysModal.value) {
        closeBuysModal()
      } else if (showRegisterClientModal.value) {
        showRegisterClientModal.value = false
      }
      return
    }

    // Atajos dentro del Modal de Cobro (Alt + Números 1 al 9 o Teclado Numérico)
    if (showBuysModal.value && event.altKey) {
      const key = event.key.toLowerCase()

      // Alt + Enter -> Completar Venta
      if (key === 'enter') {
        event.preventDefault()
        if (typeof onCompletePurchase === 'function') {
          onCompletePurchase()
        }
        return
      }

      // Mapa 100% numérico para teclado de caja POS
      const shortcutMap = {
        '1': { method: 'cash_usd', currency: 'USD' },        // Alt + 1 -> Efectivo USD
        '2': { method: 'cash_cop', currency: 'COP' },        // Alt + 2 -> Efectivo COP
        '3': { method: 'cash_bs', currency: 'BS' },          // Alt + 3 -> Efectivo BS
        '4': { method: 'mobile_payment', currency: 'BS' },   // Alt + 4 -> Pago Móvil BS
        '5': { method: 'debit_card', currency: 'BS' },       // Alt + 5 -> Débito BS
        '6': { method: 'credit_card', currency: 'BS' },      // Alt + 6 -> Crédito BS
        '7': { method: 'bank_transfer_bs', currency: 'BS' }, // Alt + 7 -> Transferencia BS
        '8': { method: 'binance', currency: 'USD' },         // Alt + 8 -> Binance USD
        '9': { method: 'paypal', currency: 'USD' },          // Alt + 9 -> PayPal USD
        '0': { method: 'balance', currency: 'USD' },         // Alt + 0 -> Saldo a Favor
      }

      const target = shortcutMap[key]
      if (target && typeof onSelectPaymentMethod === 'function') {
        event.preventDefault()
        onSelectPaymentMethod(target.method, target.currency)
      }
    }
  }

  onMounted(() => {
    document.addEventListener('keydown', handleShortcut)
  })

  onUnmounted(() => {
    document.removeEventListener('keydown', handleShortcut)
  })
}
