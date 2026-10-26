export default [
  {
    title: 'Configuración',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'gear'],
        size: 'sm',
      },
    },
    action: 'manage',
    subject: 'admin',
    children: [
      {
        title: 'Datos de Farmacia',
        to: 'configuration-pharmacy',
      },
      {
        title: 'Roles',
        to: 'configuration-roles',
      },
      {
        title: 'E-commerce',
        to: 'configuration-branding',
      },
      {
        title: 'Menú E-commerce',
        to: 'configuration-menu',
      },
      {
        title: 'Tipo y Fiscal',
        to: 'configuration',
      },
      {
        title: 'Inventario',
        to: 'configuration-inventory',
      },
      {
        title: 'TPV',
        to: 'configuration-tpv',
      },
      {
        title: 'CRM',
        to: 'configuration-crm',
      },
      {
        title: 'RRHH',
        to: 'configuration-rrhh',
      },
      {
        title: 'Gastos',
        to: 'configuration-expenses',
      },
      {
        title: 'Finanzas',
        to: 'configuration-finances',
      },
      {
        title: 'BI',
        to: 'configuration-bi',
      },
      {
        title: 'IA Assistence',
        to: 'configuration-ia-assistant',
      },
      {
        title: 'Telegram',
        to: 'configuration-telegram',
      },
      {
        title: 'Productos',
        to: 'configuration-products',
      },
      {
        title: 'Facturas',
        to: 'configuration-invoices',
      },
      {
        title: 'Proveedores',
        to: 'configuration-suppliers',
      },
      {
        title: 'Sincronizaciones (Gmail)',
        to: 'configuration-sync',
      },
      {
        title: 'Correos',
        to: 'configuration-emails',
      },
      {
        title: 'Importar Datos',
        children: [
          {
            title: 'Generales',
            to: 'configuration-import',
          },
          {
            title: 'Hybrid',
            to: 'configuration-import-hybrid',
          },
          {
            title: 'A2',
            to: { name: 'configuration-import-hybrid', query: { tab: 'a2_sync_tab' } },
          },
        ],
      },
      {
        title: 'Empresas (SaaS)',
        to: 'configuration-tenants',
      },
    ],
  }, 
]
