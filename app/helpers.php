<?php
if (! function_exists('myFetchContents')) {

    function myFetchContents($file)
    {
        if(!$xml = file_get_contents($file))
        {
            throw new Exception('Load Failed');
        }
    }

}

if (!function_exists('removeAccents')) {
    function removeAccents($string) {
        $search = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'];
        $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N'];
        return str_replace($search, $replace, $string);
    }
}

