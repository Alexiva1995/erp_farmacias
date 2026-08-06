import { ref, computed, watch } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

export function useTpvPromotions({ orderItems, selectedClient } = {}) {
  const activeDoctorOffers = ref([])
  const loadingDoctorOffers = ref(false)

  const activePrescriptionOffers = ref([])
  const loadingPrescriptionOffers = ref(false)

  const activeCompanyOffers = ref([])
  const loadingCompanyOffers = ref(false)

  const selectedDoctorOffer = ref(null)
  const prescriptionFile = ref(null)
  const selectedCompany = ref(null)
  const selectedCompanyId = ref(null)
  const selectedDiscountType = ref(null)

  const currentPrescriptionDiscountPercentage = computed(() => {
    if (activePrescriptionOffers.value.length > 0) {
      return parseFloat(activePrescriptionOffers.value[0].discount_percentage || 0)
    }
    return 0
  })

  const fetchDoctorOffers = async () => {
    loadingDoctorOffers.value = true
    try {
      const response = await axios.get('/tpv/promotions/doctor-offer', {
        params: { per_page: 100, sort_by: 'id', sort_order: 'desc' },
      })
      if (response.data?.success) {
        activeDoctorOffers.value = response.data.data.map((offer) => ({
          id: offer.id,
          title: `${offer.doctor.name} - ${offer.discount}%`,
          value: offer.id,
          percentage: parseFloat(offer.discount),
          doctor_id: offer.doctor_id,
        }))
      }
    } catch (error) {
      console.error('Error fetching doctor offers:', error)
    } finally {
      loadingDoctorOffers.value = false
    }
  }

  const fetchPrescriptionOffers = async () => {
    loadingPrescriptionOffers.value = true
    try {
      const response = await axios.get('/tpv/promotions/prescription-offer', {
        params: { per_page: 100, sort_by: 'id', sort_order: 'desc' },
      })
      if (response.data?.success) {
        activePrescriptionOffers.value = response.data.data
      }
    } catch (error) {
      console.error('Error fetching prescription offers:', error)
    } finally {
      loadingPrescriptionOffers.value = false
    }
  }

  const fetchCompanyOffers = async (companyId = null) => {
    loadingCompanyOffers.value = true
    try {
      const params = { per_page: 100, sort_by: 'id', sort_order: 'desc' }
      if (companyId) params.company_id = companyId
      const response = await axios.get('/tpv/promotions/company-offer', { params })
      if (response.data?.data) {
        activeCompanyOffers.value = response.data.data.map((offer) => {
          const scales = offer.scales || []
          let discountText = ''
          if (scales.length > 0) {
            const percentages = scales.map((s) => parseFloat(s.discount_percentage))
            const minP = Math.min(...percentages)
            const maxP = Math.max(...percentages)
            discountText = minP === maxP ? `${minP}%` : `${minP}-${maxP}%`
          }
          return {
            title: `${offer.company?.name || 'N/A'} ${discountText ? '- ' + discountText : ''}`,
            value: offer.company_id,
            scales,
            id: offer.id,
            current_discount: offer.company?.current_discount || 0,
          }
        })
      }
    } catch (error) {
      console.error('Error fetching company offers:', error)
    } finally {
      loadingCompanyOffers.value = false
    }
  }

  const applyDiscount = (percentage, source) => {
    if (!orderItems) return
    orderItems.value = orderItems.value.map((item) => {
      if (item.discount_type === 'expiration' || item.pack_id) return item
      const rawUsd = item.original_price_usd
      const rawBs = item.original_price_bs
      const rawCop = item.original_price_cop
      const productPct = parseFloat(item.discount_percentage || 0)
      const bestPct = Math.max(percentage, productPct)
      const discountFactor = bestPct > 0 ? 1 - bestPct / 100 : 1

      return {
        ...item,
        price: rawUsd * discountFactor,
        price_bs: rawBs * discountFactor,
        price_cop: rawCop * discountFactor,
        discountApplied: true,
        discountSource: bestPct === productPct ? item.discount_type || 'individual' : source.type,
        discountSourceId: bestPct === productPct ? item.discount_source_id : source.id,
        appliedDiscountPercentage: bestPct,
      }
    })
  }

  const removeDiscount = () => {
    if (!orderItems) return
    orderItems.value = orderItems.value.map((item) => {
      if (item.discount_type === 'expiration' || item.pack_id) return item
      return {
        ...item,
        price: item.base_price,
        price_bs: item.base_price_bs,
        price_cop: item.base_price_cop,
        discountApplied: false,
        discountSource: null,
        discountSourceId: null,
        appliedDiscountPercentage: 0,
      }
    })
  }

  const validateAndApplyDoctorDiscount = () => {
    if (activeDoctorOffers.value.length === 0) return
    if (!selectedDoctorOffer.value) {
      if (selectedDiscountType.value === 'Medico') removeDiscount()
      return
    }
    const offer = activeDoctorOffers.value.find((o) => o.value === selectedDoctorOffer.value.value)
    if (!offer) {
      selectedDoctorOffer.value = null
      return
    }
    const porcentaje = parseFloat(offer.percentage || 0)
    if (porcentaje > 0) {
      applyDiscount(porcentaje, { type: 'doctor', name: offer.title, id: offer.id })
    } else {
      removeDiscount()
      selectedDoctorOffer.value = null
      toast.info('Esta oferta de médico no tiene un descuento configurado.')
    }
  }

  const validateAndApplyPrescriptionDiscount = () => {
    if (activePrescriptionOffers.value.length === 0) return
    if (!prescriptionFile.value) {
      if (selectedDiscountType.value === 'Recipe') removeDiscount()
      return
    }
    const offer = activePrescriptionOffers.value[0]
    const porcentaje = parseFloat(offer.discount_percentage || 0)
    if (porcentaje > 0) {
      applyDiscount(porcentaje, { type: 'recipe', name: 'Recipe médica', id: offer.id })
    } else {
      removeDiscount()
    }
  }

  const validateAndApplyCompanyDiscount = () => {
    if (activeCompanyOffers.value.length === 0) return
    if (!selectedCompanyId.value) {
      if (selectedDiscountType.value === 'Empresa') removeDiscount()
      return
    }
    const offer = activeCompanyOffers.value.find((o) => o.value === selectedCompanyId.value)
    if (!offer) {
      selectedCompanyId.value = null
      return
    }
    const porcentaje = parseFloat(offer.current_discount || 0)
    if (porcentaje > 0) {
      applyDiscount(porcentaje, { type: 'company', name: offer.title, id: offer.id })
      toast.success(`Descuento de empresa ${porcentaje}% habilitado para esta orden.`)
    } else {
      removeDiscount()
      selectedCompanyId.value = null
      toast.info('Esta empresa no cuenta con un descuento activo para el periodo actual.')
    }
  }

  const handlePrescriptionFileSelected = (file) => {
    prescriptionFile.value = file
    validateAndApplyPrescriptionDiscount()
    if (file && activePrescriptionOffers.value.length > 0) {
      const porcentaje = parseFloat(activePrescriptionOffers.value[0].discount_percentage || 0)
      if (porcentaje > 0) {
        toast.success(`Descuento de receta del ${porcentaje}% aplicado.`)
      } else {
        toast.info('No hay un descuento de receta activo.')
      }
    } else if (selectedDiscountType.value === 'Recipe') {
      toast.info('Descuento de receta removido.')
    }
  }

  const handleDoctorDiscountSelected = (offerId) => {
    const offer = activeDoctorOffers.value.find((o) => o.value === offerId)
    selectedDoctorOffer.value = offer
    validateAndApplyDoctorDiscount()
    if (offer) {
      toast.success(`Descuento de médico ${offer.percentage}% habilitado para esta orden.`)
    } else {
      toast.info('Descuento de médico removido.')
    }
  }

  const handleCompanyDiscountSelected = async (companyId) => {
    selectedCompanyId.value = companyId
    if (companyId) {
      await fetchCompanyOffers(companyId)
    }
    validateAndApplyCompanyDiscount()
  }

  const currentGlobalDiscountDetails = computed(() => {
    if (selectedDiscountType.value === 'Empresa' && selectedCompany.value) {
      const offer = activeCompanyOffers.value.find((o) => o.value === selectedCompany.value)
      if (offer && offer.current_discount > 0) {
        return {
          type: 'Empresa',
          percentage: parseFloat(offer.current_discount),
          label: 'Empresa',
        }
      }
    }
    if (selectedDiscountType.value === 'Medico' && selectedDoctorOffer.value) {
      const offer = activeDoctorOffers.value.find((o) => o.value === selectedDoctorOffer.value.value)
      if (offer && offer.percentage > 0) {
        return {
          type: 'Medico',
          percentage: parseFloat(offer.percentage),
          label: 'Médico',
        }
      }
    }
    if (selectedDiscountType.value === 'Recipe' && currentPrescriptionDiscountPercentage.value > 0) {
      return {
        type: 'Recipe',
        percentage: parseFloat(currentPrescriptionDiscountPercentage.value),
        label: 'Recipe',
      }
    }
    return null
  })

  if (selectedClient) {
    watch(
      () => selectedClient.value,
      async (newCliente, oldCliente) => {
        if (!newCliente || newCliente?.id === oldCliente?.id) return
        try {
          if (newCliente.company_id) {
            await fetchCompanyOffers(newCliente.company_id)
            selectedDiscountType.value = 'Empresa'
            selectedCompany.value = newCliente.company_id
          } else {
            selectedCompany.value = null
            await fetchCompanyOffers()
          }
        } catch (error) {
          console.error('Error en watcher de selectedClient:', error)
        }
      }
    )
  }

  watch(selectedDiscountType, (newValue) => {
    if (newValue !== 'Medico') selectedDoctorOffer.value = null
    if (newValue !== 'Recipe') prescriptionFile.value = null
    if (newValue !== 'Empresa') selectedCompanyId.value = null

    removeDiscount()

    if (newValue === 'Medico' && selectedDoctorOffer.value) {
      validateAndApplyDoctorDiscount()
    } else if (newValue === 'Recipe' && prescriptionFile.value) {
      validateAndApplyPrescriptionDiscount()
    } else if (newValue === 'Empresa' && selectedCompanyId.value) {
      validateAndApplyCompanyDiscount()
    }
  })

  if (orderItems) {
    let discountValidationTimer
    watch(
      () => orderItems.value.map((i) => `${i.product_id}:${i.selectedQuantity}`).join('|'),
      () => {
        if (selectedDiscountType.value === 'Empresa' && selectedCompanyId.value) {
          clearTimeout(discountValidationTimer)
          discountValidationTimer = setTimeout(() => validateAndApplyCompanyDiscount(), 300)
        }
        if (selectedDiscountType.value === 'Medico' && selectedDoctorOffer.value) {
          clearTimeout(discountValidationTimer)
          discountValidationTimer = setTimeout(() => validateAndApplyDoctorDiscount(), 300)
        }
        if (selectedDiscountType.value === 'Recipe' && prescriptionFile.value && activePrescriptionOffers.value.length > 0) {
          clearTimeout(discountValidationTimer)
          discountValidationTimer = setTimeout(() => validateAndApplyPrescriptionDiscount(), 300)
        }
      }
    )
  }

  return {
    activeDoctorOffers,
    loadingDoctorOffers,
    activePrescriptionOffers,
    loadingPrescriptionOffers,
    activeCompanyOffers,
    loadingCompanyOffers,
    selectedDoctorOffer,
    prescriptionFile,
    selectedCompany,
    selectedCompanyId,
    selectedDiscountType,
    currentPrescriptionDiscountPercentage,
    currentGlobalDiscountDetails,
    fetchDoctorOffers,
    fetchPrescriptionOffers,
    fetchCompanyOffers,
    applyDiscount,
    removeDiscount,
    validateAndApplyDoctorDiscount,
    validateAndApplyPrescriptionDiscount,
    validateAndApplyCompanyDiscount,
    handlePrescriptionFileSelected,
    handleDoctorDiscountSelected,
    handleCompanyDiscountSelected,
  }
}
