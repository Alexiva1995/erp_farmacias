import crm from './crm'
import finances from './finances'
import fiscal from './fiscal'
import inventory from './inventory'
import InventoryCycle from './InventoryCycle'
import invoice from './invoice'
import iva from './iva'
import lot from './lot'
import suppliers from './suppliers'
import tpv from './tpv'

export default [...inventory, ...lot, ...tpv, ...crm, ...fiscal, ...finances, ...InventoryCycle, ...suppliers, ...invoice, ...iva]
