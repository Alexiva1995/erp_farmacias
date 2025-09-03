import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export default function pdfProductsWithoutSuppliersGenerator(data) {
    const doc = new jsPDF('landscape');
    const pageWidth = doc.internal.pageSize.getWidth();
    
    // 1. Logo (opcional - elimina si no necesitas)
    try {
        const logoSrc = '/images/logoEmpresa.png';
        const logoWidth = 80;
        const logoHeight = 30;
        doc.addImage(logoSrc, 'PNG', (pageWidth - logoWidth) / 2, 15, logoWidth, logoHeight);
    } catch (error) {
        console.warn("No se pudo cargar el logo", error);
    }
    
    // 2. Encabezado
    const headerY = 50;
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text('PRODUCTOS SIN PROVEEDORES', pageWidth / 2, headerY, { align: 'center' });
    
    // 3. Fecha y detalles
    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    const today = new Date();
    const dateStr = `Generado el: ${today.getDate()}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
    doc.text(dateStr, pageWidth - 15, headerY + 10, { align: 'right' });
    
    // 4. Configuración de la tabla
    const headers = [
        [
            { content: '#', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'ID', styles: { fontStyle: 'bold' } },
            { content: 'Producto', styles: { fontStyle: 'bold' } },
            { content: 'Laboratorio', styles: { fontStyle: 'bold' } },
            { content: 'Ventas', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Promedio', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Costo Lot.', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Costo Unit.', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Stock', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Precio Venta', styles: { fontStyle: 'bold', halign: 'center' } }
        ]
    ];
    
    const rows = data.map((product, index) => {
        // Obtener el lote más reciente o el primero disponible
        const latestLot = product.lots && product.lots.length > 0 
            ? product.lots[0] 
            : null;
        
        // Formatear fecha de creación del lote
        let costLotInfo = 'Sin lotes';
        if (latestLot) {
            const createdDate = new Date(latestLot.created_at);
            const dateFormatted = `${createdDate.getDate()}/${String(createdDate.getMonth() + 1).padStart(2, '0')}/${createdDate.getFullYear()}`;
            costLotInfo = `$${parseFloat(latestLot.unit_cost || 0).toFixed(2)} (${dateFormatted})`;
        }
        
        return [
            { content: index + 1, styles: { halign: 'center' } },
            product.id || 'N/A',
            product.name || 'N/A',
            product.laboratory?.name || 'N/A',
            { 
                content: product.total_group_sales?.toString() || '0', 
                styles: { halign: 'center' } 
            },
            { 
                content: parseFloat(product.promedio_calculado || 0).toFixed(2), 
                styles: { halign: 'center' } 
            },
            { 
                content: costLotInfo, 
                styles: { halign: 'center' } 
            },
            { 
                content: `$${parseFloat(product.unit_cost || 0).toFixed(2)}`, 
                styles: { halign: 'center' } 
            },
            { 
                content: product.lote_quantity?.toString() || '0', 
                styles: { halign: 'center' } 
            },
            { 
                content: `$${parseFloat(product.sale_price || 0).toFixed(2)}`, 
                styles: { halign: 'center' } 
            }
        ];
    });
    
    // 5. Generar tabla
    autoTable(doc, {
        startY: headerY + 20,
        head: headers,
        body: rows,
        theme: 'grid',
        headStyles: { 
            fillColor: [41, 128, 185],
            textColor: 255,
            halign: 'center'
        },
        alternateRowStyles: {
            fillColor: [245, 245, 245]
        },
        styles: {
            fontSize: 8, // Reducido para que quepan más columnas
            cellPadding: 2,
            overflow: 'linebreak',
            minCellHeight: 8
        },
        columnStyles: {
            0: { cellWidth: 10, halign: 'center' },  // #
            1: { cellWidth: 15, halign: 'center' },  // ID
            2: { cellWidth: 60 },                    // Producto
            3: { cellWidth: 25 },                    // Laboratorio
            4: { cellWidth: 15, halign: 'center' },  // Ventas
            5: { cellWidth: 20, halign: 'center' },  // Promedio
            6: { cellWidth: 30, halign: 'center' },  // Costo Lot.
            7: { cellWidth: 20, halign: 'center' },  // Costo Unit.
            8: { cellWidth: 15, halign: 'center' },  // Stock
            9: { cellWidth: 20, halign: 'center' }   // Precio Venta
        },
        margin: { left: 10, right: 10 }
    });
    
    // 6. Pie de página
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(10);
        doc.text(
            `Página ${i} de ${pageCount}`,
            pageWidth / 2,
            doc.internal.pageSize.height - 10,
            { align: 'center' }
        );
    }
    
    // 7. Guardar PDF
    const fileName = `Productos_Sin_Proveedores_${today.toISOString().slice(0, 10)}.pdf`;
    doc.save(fileName);
}
