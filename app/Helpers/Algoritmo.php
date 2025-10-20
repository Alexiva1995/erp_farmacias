<?php


namespace App\Helpers;


class Algoritmo
{


    // esta funcion retorna el indice del elemento si lo encuentra de no encontrarlo responde con un -1
    static function busquedaBinariaAsociativa($array, $clave, $valor)
    {
        $inicio = 0;
        $fin = count($array) - 1;

        while ($inicio <= $fin) {
            $medio = floor(($inicio + $fin) / 2);
            $elementoMedio = $array[$medio];

            // echo "Comparando: {$elementoMedio[$clave]} con $valor\n";

            if ($elementoMedio[$clave] == $valor) {
                return $medio;
            }

            if ($valor < $elementoMedio[$clave]) {
                $fin = $medio - 1;
            } else {
                $inicio = $medio + 1;
            }
        }

        return -1;
    }
}
