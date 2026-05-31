import { test, expect } from '@playwright/test';

test.describe('Pruebas del Dashboard y Widget de Vencimientos (E2E)', () => {

  test.beforeEach(async ({ page }) => {
    // Incrementar el tiempo de espera para el entorno Laragon
    test.setTimeout(60000);

    // Mock de CSRF requerido para iniciar la app
    await page.route('**/sanctum/csrf-cookie', async (route) => {
      await route.fulfill({
        status: 200,
        headers: { 'set-cookie': 'XSRF-TOKEN=mock; path=/' }
      });
    });

    // 1. Mock de configuraciones generales
    await page.route('**/api/general-settings', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            app_name: 'Farmacia E2E',
            primary_color: '#E20074',
            secondary_color: '#7A0099',
          }
        })
      });
    });

    // 2. Mock del usuario autenticado (Admin)
    await page.route('**/api/user', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          id: 1,
          name: 'Administrador E2E',
          username: 'admin_e2e',
          role_id: 1,
        })
      });
    });

    // 3. Mock de otros endpoints del dashboard para evitar fallos o demoras
    await page.route('**/api/dashboard/stats**', async (route) => {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ units: 10, sales: 500, expenses: 100, profit: 400 }) });
    });
    
    await page.route('**/api/dashboard/analytics-data**', async (route) => {
      await route.fulfill({ 
        status: 200, 
        contentType: 'application/json', 
        body: JSON.stringify({ 
          average_daily_sales: 100,
          historical_averages: [],
          total_monthly_sales: 3000,
          weekly_metrics: { sales: { value: 500, change: 5 }, profit: { value: 200, change: 10 }, orders: { value: 50, change: 2 } },
          orders_summary: { completed: 45, cancelled: 5, total: 50 },
          daily_earnings: [],
          lab_summary_amount: [],
          lab_summary_units: [],
          auto_orders_summary: { pending: 0, sent: 0, completed: 0, total: 0 },
          expirations_summary: [],
          conversion_summary: [],
          promotions_summary: [],
          packs_summary: [],
          sellers_ranking: [],
          exchange_rates: [],
          system_profitability: 25.2
        }) 
      });
    });
  });

  test('Debe renderizar correctamente la lista de productos vencidos vendidos este mes', async ({ page }) => {
    // 1. Interceptar el endpoint de productos vendidos por vencer con datos de prueba envueltos en data
    await page.route('**/api/dashboard/expiring-sold-products', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 99901,
              product_name: 'Ibuprofeno 400mg E2E',
              laboratory_name: 'LAB-PRUEBA',
              lot_number: 'LOTE-E2E-XYZ',
              expiration_date: '2026-05-30',
              quantity: 5,
              sold_date: '2026-05-31T10:00:00Z',
              user_name: 'Cajero de Prueba'
            }
          ]
        })
      });
    });

    // 2. Ir a la página de inicio (Dashboard)
    await page.goto('/');

    // 3. Buscar el widget y verificar que se muestre con la información correctamente mapeada
    const titleLocator = page.locator('text=Productos que Vencían este Mes y se Vendieron');
    await expect(titleLocator).toBeVisible({ timeout: 15000 });

    const productNameLocator = page.locator('text=Ibuprofeno 400mg E2E');
    await expect(productNameLocator).toBeVisible({ timeout: 15000 });

    const lotNumberLocator = page.locator('text=LOTE-E2E-XYZ');
    await expect(lotNumberLocator).toBeVisible();

    const labLocator = page.locator('text=LAB-PRUEBA');
    await expect(labLocator).toBeVisible();

    const quantityLocator = page.locator('text=5 Unidades Vendidas');
    await expect(quantityLocator).toBeVisible();

    const sellerLocator = page.locator('text=Vendido por: Cajero de Prueba');
    await expect(sellerLocator).toBeVisible();
  });

  test('Debe mostrar el estado vacio "Sin perdidas por caducidad" cuando no hay productos vencidos vendidos', async ({ page }) => {
    // 1. Interceptar el endpoint con un array vacio
    await page.route('**/api/dashboard/expiring-sold-products', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: []
        })
      });
    });

    // 2. Ir a la página de inicio
    await page.goto('/');

    // 3. Verificar que se muestre el estado vacío
    const emptyStateTitle = page.locator('text=¡Sin pérdidas por caducidad!');
    await expect(emptyStateTitle).toBeVisible({ timeout: 15000 });

    const emptyStateText = page.locator('text=No se registran ventas de productos con vencimiento en el mes en curso.');
    await expect(emptyStateText).toBeVisible();
  });

});
