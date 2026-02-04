# Auditoría final agresiva: flujo /tpv/orderUser → POST /orders/{orderId}/complete

**Alcance:** `OrderController::completeOrder` → `OrderActionService::complete()` y cadena de observers.

---

## 1. Atomicidad de lotes (lockForUpdate vs lectura de cantidad)

### Orden de ejecución en `OrderActionService::complete()` (líneas 636–790)

| Paso | Línea(s) | Acción |
|------|----------|--------|
| 1 | 636–637 | `DB::beginTransaction()` |
| 2 | 641 | **Orden bloqueada:** `Order::where('id', $orderId->id)->lockForUpdate()->firstOrFail()` |
| 3 | 723–732 | Carga de detalles y **bloqueo de lotes:** `ProductLot::whereIn('product_id', $productIds)->...->lockForUpdate()->get()` |
| 4 | 735–771 | Lectura de cantidad **solo** desde `$lots` (que viene de `$lotsByProduct` ← `$lockedLots`) |
| 5 | 746, 755 | Uso de `$lot->quantity` (en memoria, de filas ya bloqueadas) |
| 6 | 749–751, 756–758 | `$lot->save()` dentro de `ProductLot::withoutEvents()` |

**Conclusión:** El `lockForUpdate()` sobre `ProductLot` ocurre **antes** de cualquier lectura de cantidad usada para descontar. Toda lectura de cantidad proviene de la colección `$lockedLots` cargada bajo lock.

**Simulación proceso A y B:**

- **Proceso A:** Completa orden 1 (producto X). Bloquea orden 1, luego lotes de X. Descuenta y hace `save()` de lotes. Commit.
- **Proceso B:** Completa orden 2 (mismo producto X). Bloquea orden 2. Al ejecutar `ProductLot::whereIn(..., X)->lockForUpdate()->get()`, **queda bloqueado** hasta que A haga commit/rollback.
- Cuando A hace commit, B obtiene el lock y lee las cantidades **ya actualizadas** por A.

**Riesgo:** Si B bloqueara primero los lotes y luego la orden, podría haber deadlock con A (orden → lotes vs lotes → orden). Aquí **siempre** se bloquea primero la orden y luego los lotes, por lo que el orden de locks es consistente y se reduce el riesgo de deadlock.

**Veredicto:** Correcto. El proceso B está obligado a esperar; no hay lectura de cantidad antes del lock de lotes.

---

## 2. Blindaje de créditos (request vs total de la orden)

El modelo `Credit` tiene en `$fillable`: `credit_amount`, `pending_amount`, etc. (ver `app/Models/Credit.php`).

En `OrderActionService::complete()` **no** se usa el request para montos del crédito. Se usa explícitamente el total de la orden:

**Líneas exactas (819–826):**

```php
if ($request->credit) {
    Credit::create([
        'client_id' => $request->client_id,
        'order_id' => $orderId->id,
        'credit_amount' => $orderId->total_amount,
        'pending_amount' => $orderId->total_amount,
        'credit_date' => Carbon::now(),
        'status' => 'Active'
    ]);
}
```

`credit_amount` y `pending_amount` se fijan **únicamente** con `$orderId->total_amount`. Ese valor viene del recálculo en servidor (líneas 794–796), no del body del request.

**Prueba lógica:** Aunque el frontend envíe `credit_amount: 999999`, ese valor no aparece en el array de `Credit::create()`, por lo que se ignora.

**Veredicto:** Blindaje correcto. La línea que lo garantiza es la **823** (y 824) con `'credit_amount' => $orderId->total_amount` y `'pending_amount' => $orderId->total_amount`.

---

## 3. Consistencia total (suma de pagos vs total de la orden)

Método `validatePaymentsCoverOrderTotal()` (líneas 605–632):

- Se convierte la suma de pagos a la moneda de la orden.
- Condición de rechazo: `$sumInOrderCurrency < ($orderTotal - $tolerance)` con **`$tolerance = 0.02`** (línea 609).

**Escenario:** Suma de pagos = 99.99, orden recalculada = 100.00.

- `orderTotal - tolerance = 100.00 - 0.02 = 99.98`.
- ¿99.99 < 99.98? **No.**
- No se lanza excepción → la transacción **sigue**.

**Conclusión:** Una diferencia de **1 céntimo** (pagado de menos) **no** detiene la transacción; se permite hasta **2 céntimos** de falta por la tolerancia. No hay validación de sobrepago (suma > total), lo que en muchos TPV se acepta (vuelto).

**Veredicto:** La diferencia de centavos **no** se detecta como error en el rango de la tolerancia; el código **deja pasar** hasta 2 céntimos de diferencia por debajo del total.

---

## 4. Sincronización de stock (Product.stock vs suma de lotes)

**Dentro de `complete()`:**

- Los lotes se actualizan con `ProductLot::withoutEvents(function () use ($lot) { $lot->save(); })`, por lo que **ProductLotObserver no se ejecuta** y no actualiza `Product.stock` en ese momento.
- Después se hace `$orderId->save()` (línea 809). Eso dispara **OrderObserver::updated** → **ProductObserver::handleOrderMovement($order)**.
- En `handleOrderMovement()` (ProductObserver, líneas 51–88): para cada detalle se hace `$lotsSum = (int) $product->lots()->sum('quantity')` y luego `$product->update(['stock' => $stockAfter])` (con `$stockAfter = $lotsSum`). Es decir, **Product.stock se actualiza desde la suma de lotes** en el mismo commit que los descuentos de lotes.

**Conclusión:** En el flujo de completar orden, **no** hay puerta trasera: el stock del producto se actualiza vía `handleOrderMovement` desde `SUM(lotes)`.

**Si un lote se elimina manualmente:** Si la eliminación se hace con Eloquent (ej. `ProductLot::find($id)->delete()`), se dispara **ProductLotObserver::deleted**, que llama a `updateProductStockAndPrice($product)` y recalcula `product.stock` desde la suma de lotes. El stock general se ajusta.

**Otras escrituras de `Product.stock` (fuera de complete):**

- **ProductLotObserver:** al crear/actualizar/eliminar lotes **con** eventos (no es el caso del complete, que usa `withoutEvents`).
- **ProductObserver:** cuando el producto **no** tiene lotes y se cambia `stock` manualmente.
- **Comandos:** `product:calculate-stock`, `product:balance-stock`, etc.

Esas rutas pueden hacer que `product.stock` no coincida temporalmente con la suma de lotes si se mezclan con actualizaciones manuales o comandos; en el flujo **completar orden** la única fuente de verdad usada para stock es la suma de lotes.

**Veredicto:** En el flujo `/orders/{orderId}/complete` el stock se sincroniza desde la suma de lotes. Si un lote se borra por Eloquent, el observer ajusta el stock. Las “puertas traseras” son otros flujos (comandos, productos sin lotes); no invalidan la consistencia dentro de complete.

---

## 5. Idempotencia (mismo ID de orden dos veces)

Líneas 638–648:

```php
$order = Order::where('id', $orderId->id)->lockForUpdate()->firstOrFail();
if ($order->status === Order::COMPLETED || $order->status === 'paid') {
    DB::commit();
    $order->load(['seller', 'client', 'details.product']);
    return [
        'orderCompletada' => $order,
        'already_completed' => true,
    ];
}
```

**Escenario:** El frontend envía dos veces el mismo `orderId` en un segundo por error de red.

- **Primera petición:** Bloquea la orden, ve `status !== COMPLETED`, procesa pagos, lotes, crédito, etc., pone `status = COMPLETED`, commit.
- **Segunda petición:** Espera el lock de la primera. Al obtenerlo, lee la orden ya con `status === Order::COMPLETED`. Entra al `if`, hace commit sin reprocesar y devuelve `already_completed => true`.

No se vuelven a crear pagos en caja, ni a descontar lotes, ni a crear otro crédito.

**Veredicto:** Idempotencia correcta para órdenes ya completadas.

---

## 6. Veredicto final: 10.000 chocolates en una hora

**Qué podría fallar primero (brutalmente honesto):**

1. **Contención de locks en `product_lots`**  
   Muchas órdenes tocando los mismos productos (ej. un chocolate en todas) generan cola en `ProductLot::whereIn(...)->lockForUpdate()->get()`. Con tiempo de lock por transacción alto (muchos ítems, facturación, cierre de caja), pueden aparecer **timeouts** (Lock wait timeout exceeded) o lentitud severa. El primer cuello de botella real es este.

2. **Tolerancia de 2 céntimos**  
   A 10.000 ventas, permitir hasta 2 céntimos de falta por venta implica hasta 200 unidades monetarias de diferencia aceptada. Es un riesgo de integridad/recaudación acotado pero no nulo.

3. **Cierre de caja sin lock**  
   Se obtiene `CashClosing::where('status', OPEN)->where('seller_id', ...)->first()` sin `lockForUpdate()`. Con muchos vendedores podría no ser el primer fallo, pero con alta concurrencia en el mismo vendedor existe riesgo de condiciones de carrera al actualizar el mismo cierre.

4. **Orden de locks y deadlocks**  
   El orden actual (orden → lotes) es uniforme, lo que reduce deadlocks. Si en el futuro algo bloqueara primero lotes y luego orden, el riesgo de deadlock subiría.

**Resumen:** Lo más probable es que **falle primero** la contención en los locks de `product_lots` (timeouts o lentitud extrema) al vender 10.000 chocolates en una hora, seguido por el efecto acumulado de la tolerancia de pagos y, en menor medida, la falta de lock en el cierre de caja en escenarios de mucha concurrencia por vendedor.

---

## Recomendaciones concretas

| Prioridad | Acción |
|-----------|--------|
| Alta | Reducir `$tolerance` a `0.001` (1 milésima) o validar igualdad estricta si no hay redondeos multi-moneda; o rechazar explícitamente cuando `abs($sumInOrderCurrency - $orderTotal) > 0.005`. |
| Media | Al actualizar el cierre de caja, cargar la fila con `CashClosing::where(...)->lockForUpdate()->first()` para evitar condiciones de carrera entre dos completes del mismo vendedor. |
| Baja | Monitorear lock wait timeouts en `product_lots`; si aparecen, valorar partición por producto o cola de órdenes por producto. |
