import axios from '@/plugins/axios'

export const getItemPriceByCurrency = (item, currency, useBase = false) => {
  if (!item) return 0

  if (item.fixed_price !== undefined && item.fixed_price !== null) {
    return Number(item.fixed_price) || 0
  }

  const curr = String(currency || 'USD').toUpperCase()

  if (curr === 'BS') {
    const val = useBase
      ? (item.base_price_bs ?? item.original_price_bs ?? item.price_bs ?? item.unit_price_bs)
      : (item.price_bs ?? item.unit_price_bs ?? item.base_price_bs)
    if (val !== undefined && val !== null) return Number(val) || 0
    const baseUsd = Number(item.price ?? item.unit_price ?? item.base_price ?? 0)
    const rateBs = Number(item.exchange_rate_bs ?? item.rate_bs ?? 1)
    return parseFloat((baseUsd * rateBs).toFixed(2))
  } else if (curr === 'COP') {
    const val = useBase
      ? (item.base_price_cop ?? item.original_price_cop ?? item.price_cop ?? item.unit_price_cop)
      : (item.price_cop ?? item.unit_price_cop ?? item.base_price_cop)
    if (val !== undefined && val !== null) return Number(val) || 0
    const baseUsd = Number(item.price ?? item.unit_price ?? item.base_price ?? 0)
    const rateCop = Number(item.exchange_rate_cop ?? item.rate_cop ?? 4000)
    return Math.round(baseUsd * rateCop)
  } else {
    const val = useBase
      ? (item.base_price ?? item.original_price_usd ?? item.price ?? item.unit_price_usd ?? item.unit_price)
      : (item.price ?? item.sale_price ?? item.unit_price_usd ?? item.unit_price ?? item.base_price)
    return Number(val) || 0
  }
}

export function formatOrderItemForFrontend(backendItem, getEffectiveRate) {
  // Ítem CANCHA
  if (backendItem.product_type === 'court' && backendItem.court) {
    const court = backendItem.court
    const rateCop = getEffectiveRate('USD', 'COP') || 1
    const rateBs = getEffectiveRate('USD', 'BS') || 1

    let priceUsd
    if (backendItem.unit_price_usd && parseFloat(backendItem.unit_price_usd) > 0) {
      priceUsd = parseFloat(backendItem.unit_price_usd)
    } else if (court.price && parseFloat(court.price) > 0) {
      priceUsd = parseFloat(court.price) / rateCop
    } else {
      priceUsd = 0
    }

    const priceCop = Math.round(priceUsd * rateCop)
    const priceBs = parseFloat((priceUsd * rateBs).toFixed(2))

    return {
      order_detail_id: backendItem.id,
      product_id: null,
      dish_id: null,
      court_id: court.id,
      title: court.name,
      active_ingredient: 'Cancha',
      itemCode: null,
      price: priceUsd,
      price_before_discount: priceUsd,
      price_bs: priceBs,
      price_cop: priceCop,
      base_price: priceUsd,
      base_price_bs: priceBs,
      base_price_cop: priceCop,
      unitCost: 0,
      basePrice: priceUsd,
      original_price_usd: priceUsd,
      original_price_bs: priceBs,
      original_price_cop: priceCop,
      availableQuantity: 9999,
      selectedQuantity: parseFloat(backendItem.quantity) || 0,
      laboratory: 'Alquiler Deportivo',
      taxRate: 0,
      pack_id: null,
      discount_percentage: 0,
      discount_type: null,
      discount_source_id: null,
      original_pack_config: null,
      has_pack_discount: false,
      is_dish: false,
      is_court: true,
    }
  }

  // Ítem PLATO
  if (backendItem.product_type === 'dish' && backendItem.dish) {
    const dish = backendItem.dish
    const rateCop = getEffectiveRate('USD', 'COP') || 1
    const rateBs = getEffectiveRate('USD', 'BS') || 1
    const priceCop = parseFloat(dish.price) || 0
    const priceUsd = parseFloat((priceCop / rateCop).toFixed(4))
    const priceBs = parseFloat((priceUsd * rateBs).toFixed(2))

    return {
      order_detail_id: backendItem.id,
      product_id: null,
      dish_id: dish.id,
      title: dish.name,
      active_ingredient: dish.category?.name || 'Plato',
      itemCode: null,
      price: priceUsd,
      price_before_discount: priceUsd,
      price_bs: priceBs,
      price_cop: priceCop,
      base_price: priceUsd,
      base_price_bs: priceBs,
      base_price_cop: priceCop,
      unitCost: 0,
      basePrice: priceUsd,
      original_price_usd: priceUsd,
      original_price_bs: priceBs,
      original_price_cop: priceCop,
      availableQuantity: 9999,
      selectedQuantity: parseFloat(backendItem.quantity) || 0,
      laboratory: 'Restaurante',
      taxRate: 0,
      pack_id: null,
      discount_percentage: 0,
      discount_type: null,
      discount_source_id: null,
      original_pack_config: null,
      has_pack_discount: false,
      is_dish: true,
    }
  }

  // Ítem PRODUCTO NORMAL
  const product = backendItem.product || {}
  const originalPrice = parseFloat(product.price ?? product.sale_price ?? backendItem.price_usd_unit ?? backendItem.price_at_product ?? 0)
  const originalPriceBs = parseFloat(product.price_bs ?? backendItem.price_bs ?? 0)
  const originalPriceCop = parseFloat(product.price_cop ?? backendItem.price_cop ?? 0)

  const backendDiscount = parseFloat(backendItem.discount_percentage) || 0
  const hasPackDiscount = !!(backendItem.pack_id || backendItem.pack_config)
  const discountFactor = backendDiscount > 0 ? 1 - backendDiscount / 100 : 1

  const discountedPrice = originalPrice * discountFactor
  const discountedPriceBs = originalPriceBs * discountFactor
  const discountedPriceCop = Math.round(originalPriceCop * discountFactor)

  // Leer la tasa de impuesto: primero tax_rate numérico, luego el flag booleano `iva`, fallback 0
  const rawTaxRate = parseFloat(product.tax_rate)
  const taxMultiplier = !isNaN(rawTaxRate) ? rawTaxRate : (product.iva == 1 ? 0.16 : 0)

  return {
    order_detail_id: backendItem.id,
    product_id: product.id,
    dish_id: null,
    title: product.name,
    active_ingredient: product.active_ingredient,
    itemCode: product.barcode,
    price: discountedPrice,
    price_before_discount: originalPrice,
    price_bs: discountedPriceBs,
    price_cop: discountedPriceCop,
    base_price: discountedPrice,
    base_price_bs: discountedPriceBs,
    base_price_cop: discountedPriceCop,
    unitCost: parseFloat(product.unit_cost) || 0,
    basePrice: originalPrice,
    original_price_usd: originalPrice,
    original_price_bs: originalPriceBs,
    original_price_cop: originalPriceCop,
    availableQuantity:
      parseInt(product.valid_stock_sum) || parseInt(product.lots_sum_quantity),
    selectedQuantity: parseInt(backendItem.quantity) || 0,
    laboratory: product.laboratory ? product.laboratory.name : 'N/A',
    taxRate: taxMultiplier,
    pack_id: backendItem.pack_id || null,
    discount_percentage: parseFloat(backendItem.discount_percentage) || 0,
    discount_type: backendItem.discount_type || null,
    discount_source_id: backendItem.discount_source_id || null,
    original_pack_config:
      backendItem.pack_config || backendItem.product?.pack_config || null,
    has_pack_discount: hasPackDiscount,
    is_dish: false,
  }
}
