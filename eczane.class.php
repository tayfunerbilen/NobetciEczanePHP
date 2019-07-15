<?php

/**
 * @author Tayfun Erbilen
 * @web https://erbilen.net
 * Class NobetciEczane
 */
class NobetciEczane
{

    const CACHE_DIR = 'cache';

    /**
     * @param $url
     * @param array $posts
     * @return mixed
     */
    public static function curl($url, $posts = [])
    {
        $data = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_REFERER => $url,
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT']
        ];
        if (count($posts) > 0) {
            $data[CURLOPT_POST] = true;
            $data[CURLOPT_POSTFIELDS] = http_build_query($posts);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        return str_replace(["\n", "\r", "\t"], null, $result);;
    }

    /**
     * @param $html
     * @param $total
     * @return array
     */
    public static function getRows($html, $total)
    {
        $data = [];
        preg_match_all('@<div class="page25-content"><div class="page25-content-header"></div><div class="col-sm-3">(.*?)</div><div class="col-sm-9">(.*?)</div>@', $html, $rows);
        if (isset($rows[2])) {
            for ($i = 0; $i <= $total - 1; $i++) {
                if (isset($rows[2][$i])) {
                    preg_match('@title="(.*?)"@', $rows[2][$i], $name);
                    preg_match('@<strong>Adres: </strong>(.*?)</p>@', $rows[2][$i], $address);
                    preg_match('@tel:([0-9 ]+)@', $rows[2][$i], $phone);
                    $data[] = [
                        'name' => isset($name[1]) ? $name[1] : '-',
                        'address' => isset($address[1]) ? $address[1] : '-',
                        'phone' => isset($phone[1]) ? str_replace(' ', null, $phone[1]) : '-'
                    ];
                }
            }
        }
        return $data;
    }

    /**
     * @param $total
     * @param $cityId
     * @return array
     */
    public static function getMore($total, $cityId)
    {
        $data = [];
        for ($i = 1; $i <= $total; $i++) {
            $result = self::curl('https://ecza.io/ajax_eczane.php', [
                'count' => $i,
                'sistem_il' => $cityId
            ]);
            $data[] = self::getRows($result, 20);
        };
        return $data;
    }

    /**
     * @param $html
     * @param $city
     * @return array
     */
    public static function Parse($html, $city)
    {
        $more = false;
        preg_match('@<strong>([0-9]+)</strong> adet@', $html, $total);
        preg_match('@sistem_il: "([0-9]+)"@', $html, $cityId);
        $total = $total[1];
        if ($total > 20) {
            $page = ceil(($total - 20) / 20);
            $total = 20;
            $more = true;
        }
        $data = self::getRows($html, $total);

        if ($more) {
            $subData = self::getMore($page, $cityId[1]);
            foreach ($subData as $sub) {
                $data = array_merge($data, $sub);
            }
        }

        # cache dosyasını oluştur
        file_put_contents(self::CACHE_DIR . '/' . $city . '_' . date('Y-m-d') . '.json', json_encode($data));

        return $data;
    }

    /**
     * @param $str
     * @return string
     */
    public static function permalink($str)
    {
        $str = mb_strtolower($str, 'UTF-8');
        $str = str_replace(
            ['ı', 'ğ', 'ü', 'ç', 'ö', 'ş', '#'],
            ['i', 'g', 'u', 'c', 'o', 's', 'sharp'],
            $str
        );
        $str = preg_replace('/[^a-z0-9]/', '-', $str);
        $str = preg_replace('/-+/', '-', $str);
        return trim($str, '-');
    }

    /**
     * @param $city
     * @return array|mixed
     */
    public static function Find($city)
    {
        $city = self::permalink($city);
        $cacheFile = self::CACHE_DIR . '/' . $city . '_' . date('Y-m-d') . '.json';
        if (file_exists($cacheFile)) {
            $result = file_get_contents($cacheFile);
            return json_decode($result, true);
        } else {
            $result = self::curl(sprintf('https://ecza.io/%s-nobetci-eczane', $city));
            return self::Parse($result, $city);
        }
    }

}
