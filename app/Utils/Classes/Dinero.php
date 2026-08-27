<?php

declare(strict_types=1);

namespace App\Utils\Classes;

use App\Utils\Enums\Separador;

class Dinero
{
    public function __construct(
        public int $valor,
    ) {}

    public static function deString(
        string $str_dinero,
        Separador $sep_decimales = Separador::Coma,
        Separador $sep_millares = Separador::Punto,
    ): ?self {
        if ($sep_decimales === $sep_millares) {
            return null; // Excepcion Formato Dinero Invalido
        }
        $str_dinero = trim($str_dinero);
        $str_dinero = str_replace(
            $sep_millares->value,
            '',
            $str_dinero,
        );
        if ($str_dinero === "") {
            return null; // Excepcion Formato Dinero Invalido
        }
        $arr_dinero = explode($sep_decimales->value, $str_dinero);
        if (count($arr_dinero) > 2) {
            return null; // Excepcion Formato Dinero Invalido
        }
        $str_euros = $arr_dinero[0] !== "" ? $arr_dinero[0] : "0";
        $str_centimos = $arr_dinero[1] ?? "00";
        if (!ctype_digit($str_euros)) {
            return null; // Excepcion Formato Dinero Invalido
        }
        if (!ctype_digit($str_centimos)) {
            return null; // Excepcion Formato Dinero Invalido
        }
        $str_centimos = substr($str_centimos . "00", 0, 2);
        $int_euros = (int) $str_euros;
        $int_centimos = (int) $str_centimos;
        $valor = $int_euros * 100 + $int_centimos;
        return new self($valor);
    }

    public function aEuros(
        Separador $sep_decimales = Separador::Coma,
        Separador $sep_millares = Separador::Punto,
    ): string {
        $str_valor = (string) $this->valor;
        $str_valor = str_pad($str_valor, 3, "0", STR_PAD_LEFT);
        $str_centimos = substr($str_valor, -2);
        $str_euros = substr($str_valor, 0, -2);
        $str_euros = strrev($str_euros);
        $arr_euros = str_split($str_euros, 3);
        $str_dinero = implode($sep_millares->value, $arr_euros);
        $str_dinero = strrev($str_dinero);
        $str_dinero = $str_dinero . $sep_decimales->value . $str_centimos;
        return $str_dinero;
    }
}
