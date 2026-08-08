import { computed, watch } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { roundUpToNearestHundred } from '@/utils/roundUpToNearesHundred.js'

export function useTpvCalculations({
  orderItems,
  selectedDisplayCurrency,
  selectedClient,
  isSpecialTaxpayer,
  selectedDiscountType,
  selectedCompanyId,
  activeCompanyOffers,
  selectedDoctorOffer,
  currentPrescriptionDiscountPercentage,
  getItemPriceByCurrency,
  openOrderData,
  hasOpenOrder,
  isFinishingOrder,
}) {
  // Objeto de totales consolidado para evitar múltiples bucles repetidos en Vue
  const computedTotals = computed(() => {
    const items = orderItems.value || []
    const currency = selectedDisplayCurrency.value
    const isSpeClient = !!selectedClient.value?.is_spe

    let eligible = 0
    let expDiscount = 0
    let productsAmt = 0
    let ivaAmt = 0
    let origIvaAmt = 0
    let costAmt = 0
    let amtBs = 0
    let subtotalUsd = 0
    let totalUsdBase = 0
    let amtCop = 0

    items.forEach((item) => {
      const quantity = item.selectedQuantity || 0
      const priceWithDiscount = getItemPriceByCurrency(item, currency, true)
      const priceWithoutDiscount = getItemPriceByCurrency(item, currency)
      const taxRate = item.taxRate || 0
      const effectiveTaxRate = isSpeClient ? taxRate * 0.25 : taxRate

      // 1. Total elegible (excluye caducidad)
      if (item.discount_type !== 'expiration') {
        eligible += priceWithDiscount * quantity
      }

      // 2. Descuento por caducidad
      const percentage = parseFloat(item.discount_percentage || 0)
      if (item.discount_type === 'expiration' && percentage > 0) {
        let originalPrice = item.basePrice || 0
        if (currency === 'BS') {
          originalPrice = item.original_price_bs || 0
        } else if (currency === 'COP') {
          originalPrice = roundUpToNearestHundred(item.original_price_cop || 0)
        }
        expDiscount += originalPrice * quantity * (percentage / 100)
      }

      // 3. Monto de productos y costos
      productsAmt += priceWithDiscount * quantity
      costAmt += (item.unitCost || 0) * quantity

      // 4. IVA
      let iva = priceWithoutDiscount * quantity * taxRate
      origIvaAmt += iva
      if (isSpeClient) iva *= 0.25
      ivaAmt += iva

      // 5. Moneda BS
      const basePriceBs = item.price_bs || 0
      amtBs += basePriceBs * quantity * (1 + effectiveTaxRate)

      // 6. Moneda USD
      const basePriceUsd = getItemPriceByCurrency(item, 'USD', true)
      if (item.discount_type !== 'expiration') {
        subtotalUsd += basePriceUsd * quantity
      }
      totalUsdBase += basePriceUsd * quantity * (1 + effectiveTaxRate)

      // 7. Moneda COP
      const basePriceCop = item.price_cop || 0
      amtCop += basePriceCop * quantity * (1 + effectiveTaxRate)
    })

    // Descuentos Globales
    let companyDiscount = 0
    let doctorDiscount = 0
    let recipeDiscount = 0
    let globalDiscountPct = 0

    if (selectedDiscountType.value === 'Empresa' && selectedCompanyId.value) {
      const offer = activeCompanyOffers.value.find((o) => o.value === selectedCompanyId.value)
      globalDiscountPct = parseFloat(offer?.current_discount || 0)
      if (globalDiscountPct > 0) companyDiscount = eligible * (globalDiscountPct / 100)
    } else if (selectedDiscountType.value === 'Medico' && selectedDoctorOffer.value) {
      globalDiscountPct = parseFloat(selectedDoctorOffer.value.percentage || 0)
      if (globalDiscountPct > 0) doctorDiscount = eligible * (globalDiscountPct / 100)
    } else if (selectedDiscountType.value === 'Recipe') {
      globalDiscountPct = parseFloat(currentPrescriptionDiscountPercentage.value || 0)
      if (globalDiscountPct > 0) recipeDiscount = eligible * (globalDiscountPct / 100)
    }

    const baseAmount = productsAmt + ivaAmt
    const globalDiscount = companyDiscount + doctorDiscount + recipeDiscount
    let finalOrderTotal = baseAmount - globalDiscount

    if (currency === 'COP') {
      finalOrderTotal = roundUpToNearestHundred(finalOrderTotal)
    } else {
      finalOrderTotal = Math.round((finalOrderTotal + Number.EPSILON) * 100) / 100
    }

    // Impuesto IGTF / Especial
    const isSpecialTax = isSpecialTaxpayer.value && (currency === 'USD' || currency === 'COP')
    let specialTax = 0
    if (isSpecialTax) {
      specialTax = finalOrderTotal * 0.03
      if (currency === 'COP') specialTax = roundUpToNearestHundred(specialTax)
    }

    const finalOrderTotalWithTax = finalOrderTotal + specialTax

    // Ahorro SPE
    let speSavings = 0
    if (isSpeClient) {
      speSavings = origIvaAmt * 0.75
      if (currency === 'COP') speSavings = roundUpToNearestHundred(speSavings)
      else speSavings = Math.round((speSavings + Number.EPSILON) * 100) / 100
    }

    // Calculo USD Final
    const descuentoUSD = subtotalUsd * (globalDiscountPct / 100)
    let finalUsd = totalUsdBase - descuentoUSD
    if (isSpecialTax) finalUsd += finalUsd * 0.03

    return {
      eligible,
      companyDiscount,
      doctorDiscount,
      recipeDiscount,
      expDiscount,
      productsAmt,
      ivaAmt,
      orderTotal: finalOrderTotal,
      isSpecialTax,
      specialTax,
      orderTotalWithTax: finalOrderTotalWithTax,
      costAmt: parseFloat(costAmt.toFixed(2)),
      orderTotalSinDiscount: baseAmount,
      speSavings,
      amtBs,
      amtUsd: finalUsd,
      amtCop,
    }
  })

  const totalEligibleAmount = computed(() => computedTotals.value.eligible)
  const totalCompanyDiscountAmount = computed(() => computedTotals.value.companyDiscount)
  const totalDoctorDiscountAmount = computed(() => computedTotals.value.doctorDiscount)
  const totalRecipeDiscountAmount = computed(() => computedTotals.value.recipeDiscount)
  const totalExpirationDiscountAmount = computed(() => computedTotals.value.expDiscount)
  const totalProductsAmount = computed(() => computedTotals.value.productsAmt)
  const totalIVAAmount = computed(() => computedTotals.value.ivaAmt)
  const totalOrderAmount = computed(() => computedTotals.value.orderTotal)
  const appliesSpecialTax = computed(() => computedTotals.value.isSpecialTax)
  const specialTaxAmount = computed(() => computedTotals.value.specialTax)
  const totalOrderAmountWithspecialTaxAmount = computed(() => computedTotals.value.orderTotalWithTax)
  const totalOrderCost = computed(() => computedTotals.value.costAmt)
  const totalOrderAmountSinDiscount = computed(() => computedTotals.value.orderTotalSinDiscount)
  const totalSPESavings = computed(() => computedTotals.value.speSavings)
  const totalAmountBs = computed(() => computedTotals.value.amtBs)
  const totalAmountUsd = computed(() => computedTotals.value.amtUsd)
  const totalAmountCop = computed(() => computedTotals.value.amtCop)

  const updateOrderTotalsInBackend = async () => {
    if (!openOrderData?.value || !openOrderData.value.id) return
    let total =
      selectedDisplayCurrency.value === 'COP'
        ? roundUpToNearestHundred(totalOrderAmountWithspecialTaxAmount.value)
        : (Math.round((totalOrderAmountWithspecialTaxAmount.value + Number.EPSILON) * 100) / 100).toFixed(2)

    try {
      const payload = {
        total_amount: total,
        total_amount_usd: parseFloat(totalAmountUsd.value) || 0,
        total_cost: parseFloat(totalOrderCost.value) || 0,
        currency: selectedDisplayCurrency.value,
        discount_type: selectedDiscountType.value || null,
      }
      await axios.patch(`/tpv/orders/${openOrderData.value.id}`, payload)
    } catch (error) {
      if (error.response && error.response.status === 401) {
        window.location.reload()
      }
    }
  }

  if (openOrderData && hasOpenOrder && isFinishingOrder) {
    let updateTotalsTimer
    watch(
      [totalOrderAmount, selectedDisplayCurrency],
      (newValue, oldValue) => {
        if (!isFinishingOrder.value && hasOpenOrder.value && openOrderData.value?.id) {
          if (newValue[0] !== oldValue[0] || newValue[1] !== oldValue[1]) {
            clearTimeout(updateTotalsTimer)
            updateTotalsTimer = setTimeout(() => {
              updateOrderTotalsInBackend()
            }, 1000)
          }
        }
      },
      { deep: false }
    )
  }

  return {
    totalEligibleAmount,
    totalCompanyDiscountAmount,
    totalDoctorDiscountAmount,
    totalRecipeDiscountAmount,
    totalExpirationDiscountAmount,
    totalProductsAmount,
    totalIVAAmount,
    totalOrderAmount,
    appliesSpecialTax,
    specialTaxAmount,
    totalOrderAmountWithspecialTaxAmount,
    totalOrderCost,
    totalOrderAmountSinDiscount,
    totalSPESavings,
    totalAmountBs,
    totalAmountUsd,
    totalAmountCop,
    updateOrderTotalsInBackend,
  }
}
