import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

export function useTpvQuotations({
  addOrden,
  selectedClient,
  pendingQuotationProducts,
  verifyClient,
  addProductToOrder,
  fetchProducts,
}) {
  // Agrega productos de una cotización al pedido activo
  const handleAddQuotationProducts = async (productsFromQuotation) => {
    if (!productsFromQuotation || productsFromQuotation.length === 0) {
      toast.info('La cotización no tiene productos o está vacía.')
      return
    }

    for (const product of productsFromQuotation) {
      try {
        await addProductToOrder({
          productId: product.product_id,
          quantity: product.units,
        })
      } catch (error) {
        console.error(`Error al agregar el producto con ID ${product.product_id}:`, error)
      }
    }

    toast.success('Productos de la cotización agregados al pedido.')
    await fetchProducts()
  }

  const handleLoadQuotation = async (quotationId) => {
    if (!quotationId?.trim()) return false
    try {
      const response = await axios.get(`/tpv/quotations/${quotationId}/products`)
      const { products, client } = response.data

      if (!products || products.length === 0) return false

      if (client?.id) {
        const order = await addOrden(client.id)
        if (order) {
          selectedClient.value = order.client
        }
      } else {
        pendingQuotationProducts.value = products
        toast.warning('La cotización no tiene cliente. Ingrese la cédula del cliente.')
        return true
      }

      await handleAddQuotationProducts(products)
      toast.success('Cotización cargada. Productos agregados al pedido.')
      return true
    } catch (error) {
      // Si la cotización no existe (404), falla silenciosamente para continuar la verificación por cédula de cliente
      return false
    }
  }

  const handleIdentifyAndStart = async (value) => {
    if (!value) return
    const isQuotation = await handleLoadQuotation(value)
    if (isQuotation) return
    await verifyClient(value)
  }

  return {
    handleLoadQuotation,
    handleIdentifyAndStart,
    handleAddQuotationProducts,
  }
}
