import { ref } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

export function useTpvPacks({
  orderItems,
  loading,
  updateOrderItemQuantity,
  addProductToOrder,
  fetchOpenOrder,
}) {
  const packs = ref([])
  const loadingPacks = ref(false)
  const packsPage = ref(1)
  const packsItemsPerPage = ref(12)
  const totalPacks = ref(0)
  const selectedPack = ref(null)
  const showPackDetailsModal = ref(false)

  const fetchPacks = async () => {
    loadingPacks.value = true
    try {
      const response = await axios.get('/tpv/promotions/product-packs', {
        params: {
          page: packsPage.value,
          itemsPerPage: packsItemsPerPage.value,
        },
      })
      if (response.data?.data) {
        packs.value = response.data.data
        totalPacks.value = response.data.total || response.data.data.length
      }
    } catch (error) {
      console.error('Error fetching packs:', error)
      toast.error('Error al cargar los packs.')
    } finally {
      loadingPacks.value = false
    }
  }

  const updatePacksOptions = (options) => {
    packsPage.value = options.page
    packsItemsPerPage.value = options.itemsPerPage
    fetchPacks()
  }

  const handleViewPackDetails = async (item) => {
    try {
      const response = await axios.get(`/tpv/promotions/product-packs/${item.id}`)
      selectedPack.value = response.data.data
      showPackDetailsModal.value = true
    } catch (error) {
      console.error('Error al obtener los detalles del pack:', error)
    }
  }

  const handleAddPackToOrder = async ({ pack, quantity }) => {
    let configStr = pack.pack_config
    if (!configStr) {
      const itemWithConfig = orderItems.value.find(
        (i) => i.pack_id === pack.id && i.original_pack_config
      )
      configStr = itemWithConfig?.original_pack_config
    }

    if (!configStr) {
      console.error('No se encontró la configuración del pack ID:', pack.id)
      return
    }

    const productsToAdd = JSON.parse(configStr)
    loading.value = true

    try {
      const itemsInOrderBelongingToPack = orderItems.value.filter(
        (item) => item.pack_id === pack.id
      )

      if (itemsInOrderBelongingToPack.length > 0) {
        for (const item of itemsInOrderBelongingToPack) {
          const productConfig = productsToAdd[item.product_id]
          const unitsPerPack =
            typeof productConfig === 'object' && productConfig !== null
              ? productConfig.quantity || 1
              : productConfig || 1
          const totalToAdd = unitsPerPack * quantity

          await updateOrderItemQuantity({
            productId: item.product_id,
            quantity: item.selectedQuantity + totalToAdd,
            orderDetailId: item.order_detail_id,
            packId: pack.id,
          })
        }
      } else {
        for (const [productId, config] of Object.entries(productsToAdd)) {
          const unitsPerPack = typeof config === 'object' ? config.quantity || 1 : config
          const productPrice = typeof config === 'object' ? config.sale_price : null

          await addProductToOrder({
            productId: parseInt(productId),
            quantity: unitsPerPack * quantity,
            packId: pack.id,
            customPrice: productPrice,
          })
        }
      }

      await fetchOpenOrder()
      toast.success(`Pack actualizado: +${quantity} unidad(es) de pack.`)
    } catch (e) {
      console.error('Error al procesar el pack:', e)
      toast.error('Error al actualizar las cantidades del pack.')
    } finally {
      loading.value = false
    }
  }

  return {
    packs,
    loadingPacks,
    packsPage,
    packsItemsPerPage,
    totalPacks,
    selectedPack,
    showPackDetailsModal,
    fetchPacks,
    updatePacksOptions,
    handleViewPackDetails,
    handleAddPackToOrder,
  }
}
