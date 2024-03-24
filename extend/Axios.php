<?php


class Axios
{
    public static function http(): \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client(['verify' => false]);
    }

    public static function toJson($content = "")
    {
        try {
            return json_decode($content, true);
        } catch (\Exception $e) {
            return false;
        }
    }
}