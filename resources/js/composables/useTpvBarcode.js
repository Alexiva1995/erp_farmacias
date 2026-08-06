import { ref, onMounted, onUnmounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

/**
 * Composable de captura de código de barras para TPV.
 *
 * Estrategia: detección por VELOCIDAD DE TIPEO (timing-based).
 * - Los lectores de barcode inyectan todos los caracteres en < 50ms
 * - Una cajera escribiendo a mano demora > 150ms entre teclas
 * - Capturamos TODOS los keydown (fase capture) sin importar qué elemento tiene el foco
 * - Cuando detectamos velocidad de scanner: prevenimos que los chars vayan al input activo
 * - Se elimina el residuo del primer carácter (ej. '7') que entra antes de confirmar la ráfaga
 */
export function useTpvBarcode({
  addProductToOrder,
  BARCODE_LENGTH_THRESHOLD = 8,
  SCANNER_MAX_INTERVAL_MS = 50,  // intervalo máximo entre chars de un scanner real
  HUMAN_PAUSE_MS = 250,          // pausa mayor → el usuario empezó a escribir de nuevo
}) {
  const barcodeSearchQuery = ref('')
  let barcodeInputTimer = null

  // Estado interno del detector de barcode
  let buffer = ''
  let keyTimestamps = []
  let scannerDetected = false
  let barcodeTimer = null

  // ─── Procesamiento del barcode ────────────────────────────────────────────
  const addProductToOrderByBarcode = async (barcode) => {
    try {
      const response = await axios.get(`/barcode/${barcode}`)
      const productDetails = response.data
      await addProductToOrder({ productId: productDetails.id, quantity: 1, productData: productDetails })
    } catch (error) {
      console.error('Error al agregar producto por código de barras:', error.response ? error.response.data : error.message)
      toast.error('Producto no encontrado o error al agregar por código de barras.')
    }
  }

  const resetBuffer = () => {
    buffer = ''
    keyTimestamps = []
    scannerDetected = false
    clearTimeout(barcodeTimer)
  }

  const tryProcessBarcode = () => {
    if (buffer.length >= BARCODE_LENGTH_THRESHOLD && scannerDetected) {
      const barcode = buffer
      resetBuffer()

      // Asegurar que el input enfocado no quede con residuos del escaneo
      const activeEl = document.activeElement
      if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
        if (activeEl.value.length <= 2) {
          activeEl.value = ''
          activeEl.dispatchEvent(new Event('input', { bubbles: true }))
          activeEl.dispatchEvent(new Event('change', { bubbles: true }))
        }
      }

      addProductToOrderByBarcode(barcode)
    } else {
      resetBuffer()
    }
  }

  // ─── Listener global en fase CAPTURE ─────────────────────────────────────
  const handleGlobalKeydown = (event) => {
    const now = Date.now()

    // Enter: fin del scan (la mayoría de lectores envían Enter al terminar)
    if (event.key === 'Enter') {
      if (scannerDetected && buffer.length >= BARCODE_LENGTH_THRESHOLD) {
        event.preventDefault()
        event.stopPropagation()
        tryProcessBarcode()
      } else {
        resetBuffer()
      }
      return
    }

    // Teclas de control (Backspace, Tab, Shift, etc.) — ignorar excepto Enter
    if (event.key.length > 1) {
      if (keyTimestamps.length > 0 && now - keyTimestamps[keyTimestamps.length - 1] > HUMAN_PAUSE_MS) {
        resetBuffer()
      }
      return
    }

    // ── Detección de pausa larga entre chars → resetear ──
    if (keyTimestamps.length > 0 && now - keyTimestamps[keyTimestamps.length - 1] > HUMAN_PAUSE_MS) {
      resetBuffer()
    }

    keyTimestamps.push(now)
    buffer += event.key

    // Detectar velocidad de scanner: se necesitan al menos 2 chars para medir
    if (keyTimestamps.length >= 2) {
      const lastInterval = keyTimestamps[keyTimestamps.length - 1] - keyTimestamps[keyTimestamps.length - 2]
      if (lastInterval <= SCANNER_MAX_INTERVAL_MS) {
        if (!scannerDetected) {
          scannerDetected = true

          // Limpiar el primer carácter (ej: '7') que se alcanzó a inyectar en el input activo antes de confirmar ráfaga
          const activeEl = document.activeElement
          if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
            const firstChar = buffer[0]
            if (activeEl.value.endsWith(firstChar) || activeEl.value === firstChar) {
              activeEl.value = activeEl.value.slice(0, -firstChar.length)
              activeEl.dispatchEvent(new Event('input', { bubbles: true }))
              activeEl.dispatchEvent(new Event('change', { bubbles: true }))
            }
          }
        }
      }
    }

    // Una vez detectado el scanner, prevenimos que los chars lleguen al input activo
    if (scannerDetected) {
      event.preventDefault()
      event.stopPropagation()
    }

    // Disparo por timeout: en caso de que el scanner no envíe Enter
    clearTimeout(barcodeTimer)
    barcodeTimer = setTimeout(() => {
      tryProcessBarcode()
    }, 80)
  }

  onMounted(() => {
    document.addEventListener('keydown', handleGlobalKeydown, true)
  })

  onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeydown, true)
    clearTimeout(barcodeTimer)
    clearTimeout(barcodeInputTimer)
    resetBuffer()
  })

  return {
    barcodeSearchQuery,
    barcodeInputTimer,
    addProductToOrderByBarcode,
  }
}
