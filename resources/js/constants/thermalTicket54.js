/**
 * CSS completo para ticket térmico 54mm.
 * Producto: fuente al menos 2pt más pequeña (8px vs 10px base) con !important para evitar sobrescritura.
 */
export const THERMAL_54MM_CSS = `
  @page { size: 54mm auto; margin: 0; padding: 0; }
  html, body {
    width: 54mm !important;
    max-width: 54mm !important;
    min-width: 54mm !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 10px !important;
    line-height: 1.25 !important;
    color: #000000 !important;
    background: #ffffff !important;
  }
  body * {
    box-sizing: border-box !important;
    color: #000000 !important;
    background: transparent !important;
  }
  .thermal-54-ticket {
    width: 54mm !important;
    max-width: 54mm !important;
    min-width: 54mm !important;
    padding: 2mm !important;
    margin: 0 !important;
    background: #ffffff !important;
    color: #000000 !important;
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 10px !important;
    line-height: 1.25 !important;
  }
  .thermal-header {
    text-align: center !important;
    margin-bottom: 2mm !important;
    padding-bottom: 2mm !important;
    border-bottom: 1px dashed #000000 !important;
  }
  .thermal-logo {
    display: block !important;
    margin: 0 auto 1mm !important;
    max-width: 40mm !important;
    height: auto !important;
    filter: grayscale(100%) contrast(1.1) !important;
  }
  .thermal-rif { font-size: 9px !important; font-weight: bold !important; margin: 0 0 0.5mm !important; }
  .thermal-company { font-size: 10px !important; font-weight: bold !important; margin: 0 0 0.5mm !important; }
  .thermal-address { font-size: 9px !important; margin: 0 !important; line-height: 1.15 !important; }
  .thermal-data { margin-bottom: 2mm !important; }
  .thermal-data-row {
    display: flex !important;
    justify-content: space-between !important;
    margin: 0.5mm 0 !important;
    font-size: 10px !important;
  }
  .thermal-label { font-weight: bold !important; flex-shrink: 0 !important; }
  .thermal-value { text-align: right !important; word-break: break-word !important; margin-left: 2mm !important; }
  .thermal-products {
    margin-bottom: 2mm !important;
    border-top: 1px dashed #000000 !important;
    padding-top: 1mm !important;
  }
  .thermal-products-head {
    display: flex !important;
    font-size: 10px !important;
    font-weight: bold !important;
    padding: 0.5mm 0 !important;
    border-bottom: 1px dashed #000000 !important;
  }
  .thermal-col-qty { width: 10mm !important; flex-shrink: 0 !important; }
  .thermal-col-desc { flex: 1 !important; min-width: 0 !important; padding: 0 1mm !important; }
  .thermal-col-amount { width: 18mm !important; flex-shrink: 0 !important; text-align: right !important; }
  .thermal-product-row {
    display: flex !important;
    align-items: flex-start !important;
    padding: 1mm 0 !important;
    font-size: 10px !important;
    border-bottom: 1px solid #000000 !important;
  }
  .thermal-product-row .thermal-col-desc { display: flex !important; flex-direction: column !important; }
  /* Producto: 2pt más pequeño que el resto (8px vs 10px) - !important para que ninguna regla lo sobrescriba */
  .thermal-product-name {
    font-size: 8px !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
  }
  .thermal-product-meta { font-size: 8px !important; margin-top: 0.5mm !important; color: #000000 !important; }
  .thermal-totals { margin-top: 2mm !important; padding-top: 1mm !important; font-size: 10px !important; }
  .thermal-total-row { display: flex !important; justify-content: space-between !important; margin: 0.5mm 0 !important; }
  .thermal-total-block {
    border-top: 2px solid #000000 !important;
    border-bottom: 2px solid #000000 !important;
    padding: 1.5mm 0 !important;
    margin: 1mm 0 !important;
  }
  .thermal-total-main { font-size: 11px !important; font-weight: bold !important; }
  .thermal-footer {
    text-align: center !important;
    font-size: 11px !important;
    font-weight: bold !important;
    margin-top: 3mm !important;
    padding-top: 2mm !important;
    border-top: 1px dashed #000000 !important;
  }
`;
