import{B as S}from"./logo-egh8C_cI.js";import{c as O}from"./formatters-Bb26pLux.js";import{f as u}from"./currencyFormatter-DvlbJxwx.js";import{f as y}from"./formatDateTime-CPebMkyR.js";import{r as P}from"./roundUpToNearesHundred-BV92GKJE.js";import{_ as L}from"./_plugin-vue_export-helper-DlAUqK2U.js";import{P as v,c as d,g as t,q as s,a1 as R,t as n,y as h,F as x,l as w,e as c}from"./main-BsasJLwu.js";const z={class:"thermal-54-ticket"},N={class:"thermal-header"},B=["src"],E={class:"thermal-data"},_={class:"thermal-data-row"},I={class:"thermal-value"},M={class:"thermal-data-row"},j={class:"thermal-value"},U={class:"thermal-data-row"},q={class:"thermal-value"},$={class:"thermal-data-row"},F={class:"thermal-value thermal-value-cliente"},H={key:0,class:"thermal-data-row thermal-data-row-tel"},G={class:"thermal-value"},Q={class:"thermal-products"},V={class:"thermal-col-desc"},X={class:"thermal-product-name"},J={class:"thermal-col-amount"},W={class:"thermal-totals"},Z={key:0,class:"thermal-total-row"},K={key:1,class:"thermal-total-row"},Y={class:"thermal-total-block"},tt={class:"thermal-total-row thermal-total-main"},at={key:3,class:"thermal-total-row"},et={key:4,class:"thermal-total-row"},ot={__name:"OrderTicketThermal54",props:{orderData:{type:Object,default:()=>({})},totalAmount:{type:Number,default:0},selectedCurrency:{type:String,default:"COP"},orderProducts:{type:Array,default:()=>[]},payments:{type:Array,default:()=>[]},changeAmount:{type:Number,default:0},creditAmount:{type:Number,default:0},credit:{type:Boolean,default:!1},companyDiscountTotal:{type:Number,default:0},selectedDiscountType:{type:String,default:null},doctorDiscountTotal:{type:Number,default:0},recipeDiscountTotal:{type:Number,default:0},isSpecialTaxpayer:{type:Boolean,default:!1},speSurchargeAmount:{type:[Number,String],default:0}},setup(o){const p=o,A=(e,a)=>{const r=e.taxRate||0;let l=0;a==="BS"?l=e.price_bs||0:a==="COP"?l=e.price_cop||0:l=e.price||0;let i=l*(1+r);return a==="COP"&&(i=P(i)),i},C=e=>{const a=A(e,p.selectedCurrency),r=e.selectedQuantity||0;return a*r},T=(e,a)=>{const r={COP:[{label:"Efectivo",value:"cash_cop"},{label:"Transferencia",value:"bank_transfer"}],BS:[{label:"Efectivo",value:"cash_bs"},{label:"Pago Móvil",value:"mobile_payment"},{label:"Transferencia",value:"bank_transfer_bs"},{label:"Tarjeta",value:"card"},{label:"T. Débito",value:"debit_card"},{label:"T. Crédito",value:"credit_card"}],USD:[{label:"Efectivo",value:"cash_usd"},{label:"Binance",value:"binance"},{label:"PayPal",value:"paypal"},{label:"Crédito",value:"credit"},{label:"Saldo",value:"balance"}]},i=(r[a]||Object.values(r).flat()).find(m=>m.value===e);return i?i.label:(e||"").replace(/_/g," ").toUpperCase()},f=v(()=>{const e=(p.selectedDiscountType||"").toLowerCase();p.selectedCurrency;const r={empresa:{label:"Descuento Empresa",amount:p.companyDiscountTotal},company:{label:"Descuento Empresa",amount:p.companyDiscountTotal},medico:{label:"Descuento Médico",amount:p.doctorDiscountTotal},doctor:{label:"Descuento Médico",amount:p.doctorDiscountTotal},recipe:{label:"Descuento Recipe",amount:p.recipeDiscountTotal}}[e];return r&&r.amount>0?r:null}),g=e=>e.laboratory||null,D=e=>{const a=e.selectedQuantity??"",r=(e.title||"—").toUpperCase(),l=g(e)?String(g(e)).toUpperCase():"";return(l?`${a} X ${r} ${l}`:`${a} X ${r}`).trim()},k=v(()=>{var i;const e=(i=p.orderData)==null?void 0:i.client;if(!e)return"—";const a=[e.name,e.last_name].filter(Boolean).join(" ").trim(),r=e.identification?e.identification_type?`${e.identification_type}${e.identification}`:e.identification:"",l=[a,r].filter(Boolean);return l.length?l.join(" · "):"—"});return(e,a)=>{var r,l,i;return c(),d("div",z,[t("header",N,[t("img",{class:"thermal-logo",src:s(S),alt:"Logo"},null,8,B),a[0]||(a[0]=R('<div class="thermal-rif" data-v-9ec4c4fb>J-50540695-7</div><div class="thermal-company" data-v-9ec4c4fb>FARMACIA BARRIO SUCRE 2024, C.A.</div><div class="thermal-address" data-v-9ec4c4fb>CALLE PRINCIPAL LOCAL 05 (L5)</div><div class="thermal-address" data-v-9ec4c4fb>SECTOR BARRIO SUCRE LA FRIA TACHIRA</div><div class="thermal-address" data-v-9ec4c4fb>ZONA POSTAL 5020</div>',5))]),t("div",E,[t("div",_,[a[1]||(a[1]=t("span",{class:"thermal-label"},"Nº Orden:",-1)),t("span",I,n(o.orderData.id),1)]),t("div",M,[a[2]||(a[2]=t("span",{class:"thermal-label"},"Fecha:",-1)),t("span",j,n(s(y)(o.orderData.created_at,"date"))+" "+n(s(y)(o.orderData.created_at,"time")),1)]),t("div",U,[a[3]||(a[3]=t("span",{class:"thermal-label"},"Cajero:",-1)),t("span",q,n((r=o.orderData.seller)!=null&&r.username?s(O)(o.orderData.seller.username):"—"),1)]),t("div",$,[a[4]||(a[4]=t("span",{class:"thermal-label"},"Cliente:",-1)),t("span",F,n(k.value),1)]),(l=o.orderData.client)!=null&&l.phone?(c(),d("div",H,[a[5]||(a[5]=t("span",{class:"thermal-label"},"Tel:",-1)),t("span",G,n(o.orderData.client.phone),1)])):h("",!0)]),t("div",Q,[a[6]||(a[6]=t("div",{class:"thermal-products-head"},[t("span",{class:"thermal-col-desc"},"Producto"),t("span",{class:"thermal-col-amount"},"Monto")],-1)),(c(!0),d(x,null,w(o.orderProducts,(m,b)=>(c(),d("div",{key:m.id||b,class:"thermal-product-row"},[t("div",V,[t("span",X,n(D(m)),1)]),t("span",J,n(s(u)(C(m),o.selectedCurrency)),1)]))),128))]),t("div",W,[f.value?(c(),d("div",Z,[t("span",null,n(f.value.label),1),t("span",null,"- "+n(s(u)(f.value.amount,o.selectedCurrency)),1)])):h("",!0),o.isSpecialTaxpayer&&o.speSurchargeAmount?(c(),d("div",K,[a[7]||(a[7]=t("span",null,"Recargo SPE (3%)",-1)),t("span",null,n(s(u)(Number(o.speSurchargeAmount),o.selectedCurrency)),1)])):h("",!0),t("div",Y,[t("div",tt,[a[8]||(a[8]=t("span",null,"TOTAL",-1)),t("span",null,n(s(u)(o.totalAmount,o.selectedCurrency)),1)])]),(i=o.payments)!=null&&i.length?(c(!0),d(x,{key:2},w(o.payments,(m,b)=>(c(),d("div",{class:"thermal-total-row",key:b},[t("span",null,n(T(m.method,m.currency))+" ("+n(m.currency)+")",1),t("span",null,n(s(u)(m.amount||0,m.currency)),1)]))),128)):h("",!0),o.credit?(c(),d("div",at,[a[9]||(a[9]=t("span",null,"Crédito",-1)),t("span",null,n(s(u)(o.creditAmount,o.selectedCurrency)),1)])):h("",!0),o.changeAmount>0?(c(),d("div",et,[a[10]||(a[10]=t("span",null,"Devolución",-1)),t("span",null,n(s(u)(o.changeAmount,"COP")),1)])):h("",!0)]),a[11]||(a[11]=t("footer",{class:"thermal-footer"}," ¡GRACIAS POR SU COMPRA! ",-1))])}}},ct=L(ot,[["__scopeId","data-v-9ec4c4fb"]]),pt=`
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
`;export{ct as O,pt as T};
