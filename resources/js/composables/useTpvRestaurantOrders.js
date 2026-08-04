import { ref } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

export function useTpvRestaurantOrders({
  isLoadingInitialOrder,
  hasOpenOrder,
  openOrderData,
  reservedOrderData,
  selectedClient,
  selectedDisplayCurrency,
  defaultCurrency,
  orderItems,
  isSportsRental,
  fetchOpenOrder,
  formatOrderItemForFrontend,
  getEffectiveRate,
}) {
  const pedidosList = ref([])
  const loadingPedidos = ref(false)
  const showPedidosModal = ref(false)

  const fetchPedidosList = async () => {
    loadingPedidos.value = true
    try {
      const response = await axios.get('/tpv/orders/active-pending')
      pedidosList.value = response.data.data || []
    } catch (error) {
      console.error('Error al cargar pedidos activos:', error)
    } finally {
      loadingPedidos.value = false
    }
  }

  const openPedidosModal = () => {
    fetchPedidosList()
    showPedidosModal.value = true
  }

  const selectPedido = async (pedido) => {
    try {
      await axios.post(`/tpv/orders/${pedido.id}/activate`)
      showPedidosModal.value = false
      await fetchOpenOrder()
      toast.success('Pedido cargado correctamente.')
    } catch (error) {
      console.error('Error al cargar pedido:', error)
      toast.error('No se pudo cargar el pedido.')
    }
  }

  const selectReservation = async (reserva) => {
    try {
      isLoadingInitialOrder.value = true
      await axios.post(`/reservations/${reserva.id}/activate-tpv`)
      await fetchOpenOrder()
      toast.success('Reservación precargada correctamente.')
    } catch (error) {
      console.error('Error al precargar reservación:', error)
      toast.error('No se pudo precargar la reservación.')
    } finally {
      isLoadingInitialOrder.value = false
    }
  }

  const handleNoShow = async (reserva) => {
    try {
      isLoadingInitialOrder.value = true
      await axios.patch(`/reservations/${reserva.id}/status`, { status: 'no_show' })
      toast.success('Estado de la reserva actualizado a: Faltó.')
      await fetchPedidosList()
    } catch (error) {
      console.error('Error al registrar inasistencia:', error)
      toast.error('No se pudo registrar la inasistencia.')
    } finally {
      isLoadingInitialOrder.value = false
    }
  }

  return {
    pedidosList,
    loadingPedidos,
    showPedidosModal,
    fetchPedidosList,
    openPedidosModal,
    selectPedido,
    selectReservation,
    handleNoShow,
  }
}
