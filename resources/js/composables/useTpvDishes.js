import { ref, computed } from 'vue'
import axios from '@/plugins/axios'

export function useTpvDishes() {
  const enableDishes = ref(false)
  const enableZoom = ref(false)
  const isRestaurant = ref(false)
  const isSportsRental = ref(false)
  const isSpecialTaxpayer = ref(false)
  const allForeignSalesSpe = ref(false)
  const fiscalMode = ref('demo')
  const dishes = ref([])
  const rawDishCategories = ref([])
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
      fiscalMode.value = settings.fiscal_mode ?? 'demo'
      enableDishes.value = settings.enable_dishes !== undefined ? !!settings.enable_dishes : true
      enableZoom.value = Boolean(settings.enable_zoom)
      isSpecialTaxpayer.value = Boolean(settings.enable_ce || settings.special_taxpayer_status === 'activa')
      allForeignSalesSpe.value = Boolean(settings.enable_ce || settings.all_foreign_sales_spe)
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
    const apiCats = Array.isArray(rawDishCategories.value) ? rawDishCategories.value : []
    // Filtrar estrictamente categorías válidas de menú (dish/dishes/both) para no mezclar categorías de farmacia
    const menuCats = apiCats.filter(
      (c) => !c.type || ['dish', 'dishes', 'both'].includes(String(c.type).toLowerCase())
    )

    const allowedIds = new Set(menuCats.map((c) => Number(c.id)))
    const result = menuCats.map((cat) => {
      const matchingDishes = dishes.value.filter(
        (d) => Number(d.category_id) === Number(cat.id) || (cat.name && d.category?.name === cat.name)
      )
      const fallbackPhoto = matchingDishes.find((d) => Boolean(d.photo_url))?.photo_url || null
      return {
        id: cat.id,
        name: cat.name,
        photo_url: cat.photo_url || fallbackPhoto,
        count: matchingDishes.length,
        isOther: false,
      }
    }).filter((cat) => cat.count > 0)

    // Agrupar platos sin categoría de menú válida (incluidos los ligados a categorías de farmacia)
    if (menuCats.length > 0) {
      const otherDishes = dishes.value.filter(
        (d) => !d.category_id || !allowedIds.has(Number(d.category_id))
      )
      if (otherDishes.length > 0) {
        result.push({
          id: 'other',
          name: 'Otros Platos',
          photo_url: otherDishes.find((d) => Boolean(d.photo_url))?.photo_url || null,
          count: otherDishes.length,
          isOther: true,
        })
      }
      return result
    }

    // Respaldo si el endpoint de categorías devolvió vacío, evitando exponer categorías de farmacia
    const fallbackMap = new Map()
    dishes.value.forEach((d) => {
      const isInventoryCat = String(d.category?.type || '').toLowerCase() === 'inventory'
      const catName = isInventoryCat ? 'Otros Platos' : (d.category?.name || 'General')
      if (!fallbackMap.has(catName)) {
        fallbackMap.set(catName, {
          id: isInventoryCat ? 'other' : (d.category_id || catName),
          name: catName,
          photo_url: isInventoryCat ? (d.photo_url || null) : (d.category?.photo_url || d.photo_url || null),
          count: 1,
          isOther: isInventoryCat,
        })
      } else {
        const entry = fallbackMap.get(catName)
        entry.count += 1
        if (!entry.photo_url && d.photo_url) {
          entry.photo_url = d.photo_url
        }
      }
    })
    return Array.from(fallbackMap.values())
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
      const [dishesResp, catsResp] = await Promise.all([
        axios.get('/dishes', { params: { status: 1, itemsPerPage: -1 } }),
        axios.get('/categories', { params: { type: 'dishes' } }).catch(() => ({ data: [] })),
      ])
      dishes.value = Array.isArray(dishesResp.data?.data) ? dishesResp.data.data : (dishesResp.data || [])
      const catsData = catsResp.data?.data ?? catsResp.data
      rawDishCategories.value = Array.isArray(catsData) ? catsData : []
    } catch (error) {
      console.error('[TPV] Error al cargar platos:', error)
    } finally {
      dishesLoading.value = false
    }
  }

  return {
    enableDishes,
    enableZoom,
    isRestaurant,
    isSportsRental,
    isSpecialTaxpayer,
    allForeignSalesSpe,
    fiscalMode,
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
