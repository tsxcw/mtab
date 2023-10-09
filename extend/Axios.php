<?php


class Axios
{
    public static function http (): \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client(['verify' => false]);
    }
}