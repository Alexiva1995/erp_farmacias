import { ref, computed } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

export function useTpvDishes() {
  const enableDishes = ref(false)
  const isRestaurant = ref(false)
  const isSportsRental = ref(false)
  const isSpecialTaxpayer = ref(false)
  const allForeignSalesSpe = ref(false)
  const dishes = ref([])
  const dishesLoading = ref(false)
  const dishFilterQuery = ref('')
  const selectedDishCategory = ref(null)

  const fetchGeneralSettings = async (activeTab) => {
    try {
      let response
      try {
        response = await axios.get('/general-settings')
      } catch (err) {
        if (err.response?.status === 401) {
          response = await axios.get('/public/general-settings')
        } else {
          throw err
        }
      }
      const data = response.data
      const settings = data.data || data
      enableDishes.value = settings.enable_dishes !== undefined ? !!settings.enable_dishes : true
      isSpecialTaxpayer.value = settings.special_taxpayer_status === 'activa'
      allForeignSalesSpe.value = !!settings.all_foreign_sales_spe
      isRestaurant.value = settings.tpv_style === 'restaurant'
      isSportsRental.value = settings.tpv_style === 'sports_rental'
      if (isRestaurant.value && enableDishes.value && activeTab) {
        activeTab.value = 'menu'
      }
    } catch (error) {
      console.warn('[TPV] No se pudo cargar configuración inicial:', error)
    }
  }

  const dishCategories = computed(() => {
    const categories = new Set()
    dishes.value.forEach((d) => {
      if (d.category?.name) {
        categories.add(d.category.name)
      }
    })
    return Array.from(categories)
  })

  const filteredDishes = computed(() => {
    let list = dishes.value
    if (selectedDishCategory.value) {
      list = list.filter((d) => d.category?.name === selectedDishCategory.value)
    }
    if (dishFilterQuery.value) {
      const q = dishFilterQuery.value.toLowerCase()
      list = list.filter((d) => d.name.toLowerCase().includes(q))
    }
    return list
  })

  const fetchDishes = async () => {
    if (!enableDishes.value) return
    dishesLoading.value = true
    try {
      const { data } = await axios.get('/dishes', {
        params: { status: 1, q: dishFilterQuery.value || undefined },
      })
      dishes.value = Array.isArray(data.data) ? data.data : data
    } catch (error) {
      console.error('[TPV] Error al cargar platos:', error)
    } finally {
      dishesLoading.value = false
    }
  }

  return {
    enableDishes,
    isRestaurant,
    isSportsRental,
    isSpecialTaxpayer,
    allForeignSalesSpe,
    dishes,
    dishesLoading,
    dishFilterQuery,
    selectedDishCategory,
    dishCategories,
    filteredDishes,
    fetchGeneralSettings,
    fetchDishes,
  }
}
