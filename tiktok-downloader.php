<?php

/**
 * Simple TikTok video downloader (without watermark)
 *
 * @package TikTok
 * @version 1.0.1
 * @author  Aethletic <hello@botify.ru>
 * @see     https://github.com/aethletic/tiktok-downloader
 */
class TikTok
{
    public $url;

    public function __construct($url = null)
    {
        $this->url = $url;
    }

    public function url($url) {
        $this->url = $url;
        return $this;
    }

    private function getVideoWithOutWatermark($url)
    {
        $binary = file_get_contents($url);
        preg_match_all('/vid:(.+?)\%/', $binary, $matches);
        return 'https://api2.musical.ly/aweme/v1/playwm/?video_id=' . $matches[1][0];
    }

    private function getVideoWithWatermark()
    {
        $html = $this->get($this->url);
        preg_match_all('/{"props"(.+?)<\/script>/', $html, $matches);

        if (sizeof($matches[1]) == 0) {
            return false;
        }

        $data = '{"props"' . $matches[1][0];
        $data = json_decode($data, true);

        // полная структура: https://pastebin.com/9fi0QRPf
        $res['user']['verified'] = $data['props']['pageProps']['videoData']['authorInfos']['verified'];
        $res['user']['username'] = $data['props']['pageProps']['videoData']['authorInfos']['uniqueId'];
        $res['user']['name'] = $data['props']['pageProps']['videoData']['authorInfos']['nickName'];
        $res['user']['avatar'] = $data['props']['pageProps']['videoData']['authorInfos']['covers'][0];

        $res['user']['stats']['followers'] = $data['props']['pageProps']['videoData']['authorStats']['followerCount'];
        $res['user']['stats']['likes'] = $data['props']['pageProps']['videoData']['authorStats']['heartCount'];

        $res['music']['title'] = $data['props']['pageProps']['videoData']['musicInfos']['musicName'];
        $res['music']['author'] = $data['props']['pageProps']['videoData']['musicInfos']['authorName'];
        $res['music']['cover'] = $data['props']['pageProps']['videoData']['musicInfos']['covers'][0];
        $res['music']['page'] = $data['props']['pageProps']['videoObjectPageProps']['videoProps']['audio']['mainEntityOfPage']['@id'];
        $res['music']['link'] = $this->getAudioLink($res['music']['page']);

        $res['video']['cover'] = $data['props']['pageProps']['videoData']['itemInfos']['covers'][0];
        $res['video']['links']['raw'] = $data['props']['pageProps']['videoData']['itemInfos']['video']['urls'][0];
        $res['video']['meta'] = $data['props']['pageProps']['videoData']['itemInfos']['video']['videoMeta'];
        $res['video']['text'] = $data['props']['pageProps']['videoData']['itemInfos']['text'];

        return $res;
    }

    private function getAudioLink($url)
    {
        $html = $this->get($url);
        preg_match_all('/{"props"(.+?)<\/script>/', $html, $matches);
        $data = '{"props"' . $matches[1][0];
        $data = json_decode($data, true);
        return $data['props']['pageProps']['musicInfo']['music']['playUrl'];
    }

    public function getData()
    {
        if ($this->url == '') {
            return false;
        }

        $res = $this->getVideoWithWatermark();

        if (!$res) {
            return false;
        }

        if ($res['video']['links']['raw'] == '') {
            return false;
        }

        $res['video']['links']['clean'] = preg_replace('/[\x00-\x1F\x7F]/u', '',$this->getVideoWithOutWatermark($res['video']['links']['raw']));

        return $res;
    }

    private function get($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 60,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.10240',
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }
}
}
