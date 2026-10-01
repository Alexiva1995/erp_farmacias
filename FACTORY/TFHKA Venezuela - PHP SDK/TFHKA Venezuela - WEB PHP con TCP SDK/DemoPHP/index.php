<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="description" content="Demo PHP para inpresoras fiscales">
    <meta name="author" content="integration@thefactoryhka.com">
    <link rel="icon" href="../../favicon.ico">

    <title>TFHKA DEMO PHP</title>

	<link href="css/style.css" rel="stylesheet">
    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <link href="css/ie10-viewport-bug-workaround.css" rel="stylesheet">

	<link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css"
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
<body>
<?php
	$cImpresora=file('docs/impresora.txt');
	if(!$cImpresora){//el documento está vacío, toma localhost y el puerto 8090
		$service_port = 8090;
		$address = gethostbyname('localhost');
	}else{//el documento tiene data
		$service_port = $cImpresora[1];
		$address = $cImpresora[0];
	}
?>
    	<script>
function jsRemoveWindowLoad() {
    // eliminamos el div que bloquea pantalla
    $("#WindowLoad").remove();

}

function jsShowWindowLoad(mensaje) {
    //eliminamos si existe un div ya bloqueando
    jsRemoveWindowLoad();

    //si no enviamos mensaje se pondra este por defecto
    if (mensaje === undefined) mensaje = "Procesando la información<br>Espere por favor";

    //centrar imagen gif
    height = 20;//El div del titulo, para que se vea mas arriba (H)
    var ancho = 0;
    var alto = 0;

    //obtenemos el ancho y alto de la ventana de nuestro navegador, compatible con todos los navegadores
    if (window.innerWidth == undefined) ancho = window.screen.width;
    else ancho = window.innerWidth;
    if (window.innerHeight == undefined) alto = window.screen.height;
    else alto = window.innerHeight;

    //operación necesaria para centrar el div que muestra el mensaje
    var heightdivsito = alto/2 - parseInt(height)/2;//Se utiliza en el margen superior, para centrar

   //imagen que aparece mientras nuestro div es mostrado y da apariencia de cargando
    imgCentro = "<div style='text-align:center;height:" + alto + "px;'><div  style='color:#000;margin-top:" + heightdivsito + "px; font-size:20px;font-weight:bold'>" + mensaje + "</div><img  src='img/load.gif'></div>";

        //creamos el div que bloquea grande------------------------------------------
        div = document.createElement("div");
        div.id = "WindowLoad"
        div.style.width = ancho + "px";
        div.style.height = alto + "px";
        $("body").append(div);

        //creamos un input text para que el foco se plasme en este y el usuario no pueda escribir en nada de atras
        input = document.createElement("input");
        input.id = "focusInput";
        input.type = "text"

        //asignamos el div que bloquea
        $("#WindowLoad").append(input);

        //asignamos el foco y ocultamos el input text
        $("#focusInput").focus();
        $("#focusInput").hide();

        //centramos el div del texto
        $("#WindowLoad").html(imgCentro);

}

function submitConfigForm(){
    var ipTcp = $('#inputIP').val();
    var puertoTcp = $('#inputPuerto').val();
    if(ipTcp.trim() == '' ){
        alert('Por favor indica la direccion IP.');
        $('#inputIP').focus();
        return false;
    }else if(puertoTcp.trim() == '' ){
        alert('Poir favor indicar el puerto.');
        $('#inputPuerto').focus();
        return false;
    }else{
        $.ajax({
            type:'POST',
            url:'configurar_impresora.php',
            data:{'configFrmSubmit':1,'ipTcp':ipTcp,'puertoTcp':puertoTcp},
            beforeSend: function () {
                $('.submitBtn').attr("disabled","disabled");
                $('.modal-body').css('opacity', '.5');
            },
            success:function(msg){
                if(msg == 'ok'){
                    $('#inputIp').val('');
                    $('#inputPuerto').val('');
                    $('.statusMsg').html('<span style="color:green;">Datos actualizados.</p>');
                }else{
                    $('.statusMsg').html('<span style="color:red;">Ocurrió un error, por favor intente nuevamente.</span>');
                }
                $('.submitBtn').removeAttr("disabled");
                $('.modal-body').css('opacity', '');
            }
        });
    }
}
</script>

	<nav class="navbar navbar-inverse navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="index.php">THE FACTORY HKA</a></li>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
          <ul class="nav navbar-nav navbar-right">
            <li><a href="#"><i class="glyphicon glyphicon-calendar"></i><?php
            date_default_timezone_set ("America/Caracas");
								$dia=date("l");
								if ($dia=="Monday") $dia="Lunes";
								if ($dia=="Tuesday") $dia="Martes";
								if ($dia=="Wednesday") $dia="Miércoles";
								if ($dia=="Thursday") $dia="Jueves";
								if ($dia=="Friday") $dia="Viernes";
								if ($dia=="Saturday") $dia="Sabado";
								if ($dia=="Sunday") $dia="Domingo";

								$mes=date("F");
								if ($mes=="January") $mes="Enero";
								if ($mes=="February") $mes="Febrero";
								if ($mes=="March") $mes="Marzo";
								if ($mes=="April") $mes="Abril";
								if ($mes=="May") $mes="Mayo";
								if ($mes=="June") $mes="Junio";
								if ($mes=="July") $mes="Julio";
								if ($mes=="August") $mes="Agosto";
								if ($mes=="September") $mes="Setiembre";
								if ($mes=="October") $mes="Octubre";
								if ($mes=="November") $mes="Noviembre";
								if ($mes=="December") $mes="Diciembre";

								$ano=date("Y");
								$dia2=date("d");
								echo "$dia, $dia2 de $mes del $ano";
								?></a></li>
            <li><a data-toggle="modal" data-target="#modalForm" ><i class="glyphicon glyphicon-print"></i> Configurar Impresora</a></li>


          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </nav>

 <div id="info" class="pull-right"><br><span class="label label-success">Impresora configurada en: <?php echo $address.":".$service_port?></span><br></div>
 <div class="container" id="mainmain">
	<div class="row">
    <div class="col-md-4 col-lg-4"><a href="index.php?accion=CheckFprinter" onclick="jsShowWindowLoad('Procesando')"><i class="glyphicon glyphicon-check"></i><br> Chequear Impresora</a></div>
	<div class="col-md-4 col-lg-4"><a href="index.php?accion=Factura" onclick="jsShowWindowLoad('Procesando')"><i class="glyphicon glyphicon-file"></i><br> Ejemplo Factura</a></div>
    <div class="col-md-4 col-lg-4"><a href="index.php?accion=NotaCredito" onclick="jsShowWindowLoad('Procesando')"><i class="glyphicon glyphicon-trash"></i><br> Nota de Crédito</a></div>
    </div>
    <div class="row">
    <div class="col-md-4 col-lg-4"><a href="index.php?accion=ReporteX" onclick="jsShowWindowLoad('Procesando')"><i class="glyphicon glyphicon-paste"></i><br> Reporte X</a></div>
    <div class="col-md-4 col-lg-4"><a href="index.php?accion=ReporteZ" onclick="jsShowWindowLoad('Procesando')"><i class="glyphicon glyphicon-copy"></i><br> Reporte Z</a></div>
	<div class="col-md-4 col-lg-4"><a href="index.php?accion=StatusS1" onclick="jsShowWindowLoad('Procesando')"><i class="glyphicon glyphicon-list"></i><br> Status S1</a></div>

    </div>

     <?php
	error_reporting(0);
	require_once "TfhkaPHPTCP.php";

	$Foperacion = null;
    if(isset($_GET['accion']))
    { $Foperacion = $_GET['accion'];
	$itObj = new Tfhka($address,$service_port);}
	if (isset($Foperacion)){
		echo "<br><br><br>";
		$out="";
   		if ($Foperacion == "CheckFprinter") {
  	 		if($itObj->CheckFprinter()){
				echo "<div align = 'center'><B>Impresora Disponible</B></div>";
                $out = $itObj->ReadFpStatus();

                echo "<div align = 'center'><B>Estado y Error: ".$out."</B></div>";
                
			}else{
				echo "<div align = 'center'><B><font color = 'red'>No se pudo conectar con la impresora</font></B></div>";
			}

   		}
		elseif ($Foperacion == "Factura") {
			$lineas=0;
			$factura = array(0 => "!000000001000001000Harina\n",
							 1 => "!000000001500001500Jamon\n",
							 2 => "\"000000002000001000Patilla\n",
							 3 => "#000000005000001000Caja de Whisky\n",
							 4 => "101");
			$file = "Factura1.txt";
            $fp = fopen($file, "w+");
            $write = fputs($fp, "");

			foreach($factura as $campo => $cmd)
			{
		    	$write = fputs($fp, $cmd);
				$lineas++;
			}
                fclose($fp);
                $out =  $itObj->SendFileCmd($file);
				if($out===$lineas){
					echo "<div align = 'center'><B>Archivo procesado correctamente</B></div>";
				}else{
					echo "<div align = 'center'><B><font color = 'red'>Error enviando archivo, verifique los datos y el estado de la impresora</font></B></div>";
				}

	  	}elseif ($Foperacion == "NotaCredito") {
			$lineas=0;
			$devolucion = array(0 => "iS*Pedro Mendez\n",
								1 => "iR*12.345.678\n",
								2 => "iF*0000001\n",
			               		3 => "iI*Z4A1234567\n",
								4 => "iD*18-01-2014\n",
							 	5 => "d0000000001000001000Harina\n",
							 	6 => "d1000000001500001500Jamon\n",
							 	7 => "d2000000002500003000Patilla\n",
							 	8 => "d3000000005000001000Caja de Wisky\n",
							 	9 => "101");

			$file = "NotaCredito.txt";
            $fp = fopen($file, "w+");
            $write = fputs($fp, "");
			foreach($devolucion as $campo => $cmd)
			{
		     	   $write = fputs($fp, $cmd);
				   $lineas++;
			}
             fclose($fp);
            $out =  $itObj->SendFileCmd($file);
				if($out===$lineas){
					echo "<div align = 'center'><B>Archivo procesado correctamente</B></div>";
				}else{
					echo "<div align = 'center'><B><font color = 'red'>Error enviando archivo, verifique los datos y el estado de la impresora</font></B></div>";
				}

	  	}elseif ($Foperacion == "ReporteX") {
			if($itObj->PrintXReport()){
				echo "<div align = 'center'><B>Reporte Impreso</B></div>";
			}else{
				echo "<div align = 'center'><B><font color = 'green'>No se pudo imprimir el Reporte solicitado</font></B></div>";
			}

	  	}elseif ($Foperacion == "ReporteZ") {
			if($itObj->PrintZReport()){
				echo "<div align = 'center'><B>Reporte Impreso</B></div>";
			}else{
				echo "<div align = 'center'><B><font color = 'green'>No se pudo imprimir el Reporte solicitado</font></B></div>";
			}
	  	}elseif ($Foperacion == "StatusS1") {
			$out =  $itObj->UploadStatus("S1");
			echo "<div align = 'center'><B>Numero de cajero asignado: ".$out[0]."<br>
			Total de ventas diarias: ".$out[1]."<br>
			Número de la última Factura: ".$out[2]."<br>
			Catidad de Facturas emitidas en el día: ".$out[3]."<br>
			Número de la última Nota de Débito: ".$out[4]."<br>
			Cantidad de Notas de Débito emitidas en el día: ".$out[5]."<br>
			Número de la última Nota de Crédito: ".$out[6]."<br>
			Cantidad de Notas de Crédito emitidas en el día: ".$out[7]."<br>
			Núimero del último Documento No Fiscal: ".$out[8]."<br>
			Cantidad de Documentos No Fiscales emitidos en el día: ".$out[9]."<br>
			Contador de Reportes de Memoria Fiscal: ".$out[10]."<br>
			Contador de Cierres Diarios: ".$out[11]."<br>
			RIF: ".$out[12]."<br>
			Número de Registro de la Máquina: ".$out[13]."<br>
			Hora Actual de la impresora: ".$out[14]."<br>
			Hora Actual de la impresora: ".$out[15]."</B></div>";
		}
		echo "<script>";
		echo "jsRemoveWindowLoad()";
		echo "</script>";
	}
?>

 </div>


	<div class="clearfix visible-xs"></div>

<!-- Modal -->
<div class="modal fade" id="modalForm" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                    <span class="sr-only">Close</span>
                </button>
                <h4 class="modal-title" id="myModalLabel">Configurar Impresora</h4>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <p class="statusMsg"></p>
                <form role="form">
                    <div class="form-group">
                        <label for="inputIp">IP del TCPListener</label>
                        <input type="text" class="form-control" id="inputIP" placeholder="Ingresa la IP del TCPListener"/>
                    </div>
                    <div class="form-group">
                        <label for="inputPuerto">Puerto</label>
                        <input type="number" min="5000" max="20000" class="form-control" id="inputPuerto" placeholder="Ingresa el puerto del TCPListener "/>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="submit" class="btn btn-primary submitBtn" onclick="submitConfigForm()">Guardar</button>
            </div>
        </div>
    </div>
</div>


    <footer class="footer">
    	<div class="container">
        	<div class="clearfix">
            	<div class="pull-right">
                	<ul class="list-inline">
                    	<li> <a href="https://www.facebook.com/factoryhka/"> <i class="fa fa-facebook-square fa-2x">  </i> </a> </li>
                    	<li> <a href="https://twitter.com/TheFactoryHKA"> <i class="fa fa-twitter-square fa-2x">  </i> </a> </li>
                    	<li> <a href="https://www.instagram.com/thefactory.hka/"> <i class="fa fa-instagram fa-2x">  </i> </a> </li>
                    	<li> <a href="http://www.thefactoryhka.com/ve/contacto/index"><i class="fa fa-address-card fa-2x"></i></a> </li>
               		</ul>
            	</div>
            	<div class="pull-left">
                	<p> Copyright <i class="glyphicon glyphicon-copyright-mark"></i> The Factory HKA, C.A. 2017. </p>
             	</div>

            </div>
    	</div>
    </footer>


	<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="js/bootstrap.min.js"></script>


</body>
</html>
