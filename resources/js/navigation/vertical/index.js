import balance from './balance'
import crm from './crm'
import finances from './finances'
import fiscal from './fiscal'
import furniture from './furniture'
import inventory from './inventory'
import InventoryCycle from './InventoryCycle'
import invoice from './invoice'
import islr from './islr'
import iva from './iva'
import loan from './loan'
import lot from './lot'
import productivity from './productivity'
import rrhh from './rrhh'
import suppliers from './suppliers'
import tpv from './tpv'




export default [...inventory, ...lot, ...tpv, ...crm, ...rrhh, ...fiscal, ...finances, ...InventoryCycle, ...suppliers, ...invoice, ...balance, ...furniture, ...loan, ...iva, ...productivity, ...islr]

