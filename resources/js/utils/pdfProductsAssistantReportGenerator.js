import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export default function pdfProductsAssistantReportGenerator(data) {
    // Cambiar a orientación horizontal ('landscape')
    const doc = new jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: [350, 297] // [ancho, alto] - 400mm de ancho (casi el doble de A4)
    });
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    
    // 1. Logo (opcional - elimina si no necesitas)
    try {
        const logoSrc = '/images/logoEmpresa.png'; // Ajusta la ruta
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
    doc.text('REPORTE DE PRODUCTOS', pageWidth / 2, headerY, { align: 'center' });
    
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
            { content: 'Costo Min', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Costo Max', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Costo', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Ventas', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Stock', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Promedio Ventas', styles: { fontStyle: 'bold', halign: 'center' } },
            { content: 'Análisis', styles: { fontStyle: 'bold', halign: 'center' } }
        ]
    ];
    
    const rows = data.map((product, index) => [
        { content: index + 1, styles: { halign: 'center' } },
        product.id,
        product.name,
        product.laboratory?.name || 'N/A',
        { 
            content: product.cost_min ? parseFloat(product.cost_min).toFixed(2) : '0.00',
            styles: { halign: 'right' }
        },
        { 
            content: product.cost_max ? parseFloat(product.cost_max).toFixed(2) : '0.00',
            styles: { halign: 'right' }
        },
        { 
            content: product.unit_cost ? parseFloat(product.unit_cost).toFixed(2) : '0.00',
            styles: { halign: 'right' }
        },
        { 
            content: product.total_sold_completed ? parseInt(product.total_sold_completed) : 0,
            styles: { halign: 'center' }
        },
        { 
            content: product.lote_quantity ? parseInt(product.lote_quantity) : 0,
            styles: { halign: 'center' }
        },
        { 
            content: product.promedio_calculado ? parseFloat(product.promedio_calculado).toFixed(2) : '0.00',
            styles: { halign: 'right' }
        },
        { 
            content: product.solicitar ? parseFloat(product.solicitar).toFixed(2) : '0.00',
            styles: { halign: 'right' }
        }
    ]);
    
    // 5. Generar tabla - Ajustar para orientación horizontal
    autoTable(doc, {
        startY: headerY + 20,
        head: headers,
        body: rows,
        theme: 'grid',
        headStyles: { 
            fillColor: [41, 128, 185], // Azul corporativo
            textColor: 255,
            halign: 'center'
        },
        alternateRowStyles: {
            fillColor: [245, 245, 245] // Gris claro para filas alternas
        },
        styles: {
            fontSize: 9, // Puedes aumentar un poco el tamaño ya que hay más espacio
            cellPadding: 3,
            overflow: 'linebreak'
        },
        // Ajustar anchos de columna para aprovechar el espacio horizontal
        columnStyles: {
            0: { cellWidth: 15, halign: 'center' },  // #
            1: { cellWidth: 20, halign: 'center' },  // ID
            2: { cellWidth: 60 },                    // Producto (más ancho)
            3: { cellWidth: 40 },                    // Laboratorio (más ancho)
            4: { cellWidth: 25, halign: 'right' },   // Costo Min
            5: { cellWidth: 25, halign: 'right' },   // Costo Max
            6: { cellWidth: 25, halign: 'right' },   // Costo
            7: { cellWidth: 20, halign: 'center' },  // Ventas
            8: { cellWidth: 20, halign: 'center' },  // Stock
            9: { cellWidth: 30, halign: 'right' },   // Promedio Ventas
            10: { cellWidth: 25, halign: 'right' }   // Análisis
        },
        margin: { left: 14, right: 14 },
        pageBreak: 'auto',
        tableWidth: 'auto'
    });
    
    // 6. Pie de página
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(10);
        doc.text(
            `Página ${i} de ${pageCount}`,
            pageWidth / 2,
            pageHeight - 10, // Usar pageHeight en lugar del valor fijo
            { align: 'center' }
        );
    }
    
    // 7. Guardar PDF
    const fileName = `Productos_${today.toISOString().slice(0, 10)}.pdf`;
    doc.save(fileName);
}
