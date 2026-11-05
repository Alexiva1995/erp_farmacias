<?php
if(isset($_POST['configFrmSubmit']) && !empty($_POST['ipTcp']) && !empty($_POST['puertoTcp'])){
    
    // Submitted form data
    $ipTcp   = $_POST['ipTcp'];
    $puertoTcp  = $_POST['puertoTcp'];
    
    /*
     * Escribir los datos dela impresora en el archivo
     */
    $lineas=0;
    $file = "docs/impresora.txt"; 
    $fp = fopen($file, "w+");
    $datos = array(0 => $ipTcp,
				   2 => "\r\n",
                   1 => $puertoTcp);
    $write = fputs($fp, "");
    
    foreach($datos as $campo => $cmd)
            {
                $write = fputs($fp, $cmd);
                $lineas++;
            }
                fclose($fp);

    // respuesta
    if($lineas>=3){
        $status = 'ok';
    }else{
        $status = 'err';
    }
    
    // Output status
    echo $status;die;
}