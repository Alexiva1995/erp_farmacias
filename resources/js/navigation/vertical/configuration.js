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
        title: 'Generales',
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
        title: 'Importar Datos',
        to: 'configuration-import',
      },
    ],
  }, 
]
