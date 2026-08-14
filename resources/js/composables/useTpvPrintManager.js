import { ref, computed, nextTick } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { THERMAL_54MM_CSS } from '@/constants/thermalTicket54.js'
import { formatOrderItemForFrontend } from '@/composables/useTpvItemFormatter.js'

export function useTpvPrintManager({
  orderData,
  isSpecialTaxpayer,
  currentGlobalDiscountDetails,
  selectedDisplayCurrency,
  finalizeAndCheckPending,
  brandingStore,
  getEffectiveRate,
}) {
  const isPrinting = ref(false)
  const itemsToPrint = ref([])
  const TotalToPrint = ref(0)
  const speSurchargeAmountPrint = ref(0)
  const paymentsForPrint = ref([])
  const changeAmountForPrint = ref(0)
  const changeAmountOriginForPrint = ref(0)
  const creditAmountForPrint = ref(0)
  const creditForPrint = ref(false)
  const recipeDiscountForPrint = ref(0)
  const doctorDiscountForPrint = ref(0)
  const companyDiscountForPrint = ref(0)
  const discountTypeForPrint = ref(null)
  const expirationDiscountForPrint = ref(0)
  const speSurchargeAmount = ref(0)

  const itemsForTicket = computed(() => {
    const globalDiscount = currentGlobalDiscountDetails.value
    if (!itemsToPrint.value || itemsToPrint.value.length === 0) return []

    return itemsToPrint.value.map((item) => {
      const qty = parseFloat(item.quantity || item.selectedQuantity || 1)
      const productPct = parseFloat(item.discount_percentage || 0)
      const globalPct = globalDiscount && globalDiscount.percentage > 0 && !item.pack_id
        ? parseFloat(globalDiscount.percentage)
        : 0
      const bestPct = Math.max(productPct, globalPct)

      if (bestPct > 0) {
        const factor = 1 - bestPct / 100
        return {
          ...item,
          price: item.price_before_discount * factor * qty,
          price_bs: item.original_price_bs * factor * qty,
          price_cop: item.original_price_cop * factor * qty,
          price_before_discount: item.price_before_discount * qty,
          selectedQuantity: qty,
          discount_percentage: bestPct,
          discount_type: globalPct > productPct ? globalDiscount.type : item.discount_type,
        }
      }

      return {
        ...item,
        selectedQuantity: qty,
      }
    })
  })

  const printTickeCompletion = async (completedOrder = null) => {
    const targetOrder = completedOrder || orderData.value
    if (!targetOrder) {
      console.error('No hay datos de orden para imprimir')
      return
    }
    
    // Si viene la orden completada desde el modal de cobro, formateamos sus ítems
    if (completedOrder) {
      orderData.value = completedOrder
      const rawDetails = completedOrder.details || completedOrder.items || completedOrder.products || []
      if (Array.isArray(rawDetails) && rawDetails.length > 0) {
        itemsToPrint.value = rawDetails.map((detail) => {
          // Si ya está formateado en el frontend
          if (detail.title || detail.name) {
            return {
              ...detail,
              title: detail.title || detail.name || detail.dish?.name || detail.product?.name || "—",
              notes: detail.notes || detail.observation || null,
            }
          }
          // Si es un modelo Eloquent OrderDetail directo
          return formatOrderItemForFrontend(detail, getEffectiveRate)
        })
      }
    }
    
    speSurchargeAmountPrint.value = parseFloat(targetOrder.spe_surcharge_amount || 0)
    if (isSpecialTaxpayer) isSpecialTaxpayer.value = parseFloat(targetOrder.spe_surcharge_amount) > 0
    isPrinting.value = true
    await nextTick()
    await new Promise((resolve) => setTimeout(resolve, 600))
    const printContents = document.getElementById('orderPrint')

    if (printContents && printContents.innerHTML.trim() !== '') {
      // Nombre del negocio tomado del brandingStore para no tener hardcodes
      const businessName = brandingStore?.settings?.name || 'TPV'
      const printWindow = window.open('', '', 'height=600,width=800')
      printWindow.document.write(`<html><head><title>Ticket 54mm - ${businessName}</title>`)
      printWindow.document.write('<style>' + THERMAL_54MM_CSS + '</style>')
      printWindow.document.write('</head><body>')
      printWindow.document.write(printContents.innerHTML)
      printWindow.document.write('</body></html>')
      printWindow.document.close()
      setTimeout(() => {
        printWindow.focus()
        printWindow.print()
        printWindow.close()
        isPrinting.value = false
        if (typeof finalizeAndCheckPending === 'function') {
          finalizeAndCheckPending()
        }
      }, 500)
    } else {
      alert('Error: El ticket está vacío. Intente de nuevo.')
      isPrinting.value = false
    }
  }

  const printFiscalPNP = async (order) => {
    if (!order?.id) return
    try {
      toast.info('Enviando a cola de impresión fiscal...')
      const response = await axios.post(`/fiscal/queue/${order.id}`)
      toast.success(response.data.message || 'Orden encolada correctamente.')
    } catch (error) {
      console.error('Error al encolar impresión fiscal:', error)
      toast.error(error.response?.data?.error || 'Error al conectar con el servidor.')
    }
  }

  return {
    isPrinting,
    itemsToPrint,
    TotalToPrint,
    speSurchargeAmountPrint,
    paymentsForPrint,
    changeAmountForPrint,
    changeAmountOriginForPrint,
    creditAmountForPrint,
    creditForPrint,
    recipeDiscountForPrint,
    doctorDiscountForPrint,
    companyDiscountForPrint,
    discountTypeForPrint,
    expirationDiscountForPrint,
    speSurchargeAmount,
    itemsForTicket,
    printTickeCompletion,
    printFiscalPNP,
  }
}
