<?php
/* АКПП45: обфускация контактов в HTML-сущности (защита от харвестеров без поломки отображения) */
if (!function_exists('akpp_obf')) {
    function akpp_obf($s) {
        $o = '';
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $o .= '&#' . ord($s[$i]) . ';';
        }
        return $o;
    }
}
