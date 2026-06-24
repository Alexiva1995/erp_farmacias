import { defineStore } from 'pinia'
import axios from '@/plugins/axios'

export const useReservationStore = defineStore('reservation', {
  state: () => {
    const localDate = new Date()
    const yyyy = localDate.getFullYear()
    const mm = String(localDate.getMonth() + 1).padStart(2, '0')
    const dd = String(localDate.getDate()).padStart(2, '0')
    return {
      selectedDate: `${yyyy}-${mm}-${dd}`,
      courtsData: [],
      loading: false,
      error: null,
    }
  },

  actions: {
    async fetchAvailability() {
      this.loading = true
      this.error = null
      try {
        const response = await axios.get('/reservations', {
          params: { date: this.selectedDate }
        })
        this.courtsData = response.data.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Error al cargar la disponibilidad'
      } finally {
        this.loading = false
      }
    },

    async createReservation(reservationData) {
      this.loading = true
      try {
        const response = await axios.post('/reservations', reservationData)
        await this.fetchAvailability()
        return response.data
      } catch (err) {
        throw err.response?.data || { message: 'Error al crear la reserva' }
      } finally {
        this.loading = false
      }
    },

    async createFixedSchedule(fixedScheduleData) {
      this.loading = true
      try {
        const response = await axios.post('/fixed-schedules', fixedScheduleData)
        await this.fetchAvailability()
        return response.data
      } catch (err) {
        throw err.response?.data || { message: 'Error al crear el horario fijo' }
      } finally {
        this.loading = false
      }
    },

    async updateFixedSchedule(id, fixedScheduleData) {
      this.loading = true
      try {
        const response = await axios.put(`/fixed-schedules/${id}`, fixedScheduleData)
        await this.fetchAvailability()
        return response.data
      } catch (err) {
        throw err.response?.data || { message: 'Error al actualizar el horario fijo' }
      } finally {
        this.loading = false
      }
    },

    async deleteFixedSchedule(id, date = null) {
      this.loading = true
      try {
        const url = date ? `/fixed-schedules/${id}?date=${date}` : `/fixed-schedules/${id}`
        await axios.delete(url)
        await this.fetchAvailability()
      } catch (err) {
        throw err.response?.data || { message: 'Error al eliminar el horario fijo' }
      } finally {
        this.loading = false
      }
    },

    async deleteReservation(id) {
      this.loading = true
      try {
        await axios.delete(`/reservations/${id}`)
        await this.fetchAvailability()
      } catch (err) {
        throw err.response?.data || { message: 'Error al cancelar la reserva' }
      } finally {
        this.loading = false
      }
    },

    setupEchoListener() {
      if (window.Echo) {
        window.Echo.channel('reservations')
          .listen('.ReservationUpdated', (e) => {
            if (e.date === this.selectedDate) {
              this.fetchAvailability()
            }
          })
      }
    },

    cleanupEchoListener() {
      if (window.Echo) {
        window.Echo.leaveChannel('reservations')
      }
    }
  }
})
